<?php
require_once '../../config/database.php';
cekAkses(['buyer']);

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

verify_csrf();
$aksi = $_POST['aksi'] ?? '';

// 1. Tambah ke keranjang (tervalidasi terhadap stok database)
if ($aksi === 'add') {
    $id  = (int)($_POST['id_produk'] ?? 0);
    $qty = max(1, (int)($_POST['qty'] ?? 1));

    if ($id <= 0) {
        header("Location: index.php?err=" . urlencode("Produk tidak valid."));
        exit;
    }

    $stmt = $pdo->prepare("SELECT nama_produk, satuan, stok FROM produk_etalase WHERE id_produk = ? LIMIT 1");
    $stmt->execute([$id]);
    $produk = $stmt->fetch();

    if (!$produk || (int)$produk['stok'] <= 0) {
        header("Location: index.php?err=" . urlencode("Maaf, stok produk tersebut sedang habis."));
        exit;
    }

    $stok_tersedia = (int)$produk['stok'];
    $qty_lama      = (int)($_SESSION['cart'][$id] ?? 0);
    $qty_baru      = min($stok_tersedia, $qty_lama + $qty);

    $_SESSION['cart'][$id] = $qty_baru;

    if ($qty_lama + $qty > $stok_tersedia) {
        $msg = "Jumlah '{$produk['nama_produk']}' di keranjang disesuaikan ke batas stok maksimum ({$stok_tersedia} {$produk['satuan']}).";
    } else {
        $msg = "Produk '{$produk['nama_produk']}' berhasil ditambahkan ke keranjang belanja.";
    }

    header("Location: index.php?msg=" . urlencode($msg));
    exit;
}

// 2. Perbarui jumlah item di keranjang
if ($aksi === 'update') {
    $id  = (int)($_POST['id_produk'] ?? 0);
    $qty = (int)($_POST['qty'] ?? 1);

    if ($id <= 0 || !isset($_SESSION['cart'][$id])) {
        header("Location: keranjang.php");
        exit;
    }

    if ($qty <= 0) {
        unset($_SESSION['cart'][$id]);
        header("Location: keranjang.php?msg=" . urlencode("Produk dihapus dari keranjang."));
        exit;
    }

    $stmt = $pdo->prepare("SELECT nama_produk, satuan, stok FROM produk_etalase WHERE id_produk = ? LIMIT 1");
    $stmt->execute([$id]);
    $produk = $stmt->fetch();

    if (!$produk || (int)$produk['stok'] <= 0) {
        unset($_SESSION['cart'][$id]);
        header("Location: keranjang.php?err=" . urlencode("Produk dihapus karena stok sudah habis."));
        exit;
    }

    $stok_tersedia         = (int)$produk['stok'];
    $qty_final             = min($stok_tersedia, $qty);
    $_SESSION['cart'][$id] = $qty_final;

    if ($qty > $stok_tersedia) {
        header("Location: keranjang.php?err=" . urlencode("Stok '{$produk['nama_produk']}' hanya tersedia {$stok_tersedia} {$produk['satuan']}."));
    } else {
        header("Location: keranjang.php?msg=" . urlencode("Jumlah '{$produk['nama_produk']}' diperbarui menjadi {$qty_final} {$produk['satuan']}."));
    }
    exit;
}

// 3. Hapus dari keranjang (Wajib POST + CSRF)
if ($aksi === 'hapus') {
    $id = (int)($_POST['id_produk'] ?? 0);
    unset($_SESSION['cart'][$id]);
    header("Location: keranjang.php?msg=" . urlencode("Produk berhasil dihapus dari keranjang."));
    exit;
}

// 4. Checkout -> Kunci Stok (FOR UPDATE) & Buat Order Atomik
if ($aksi === 'checkout') {
    if (empty($_SESSION['cart'])) {
        header("Location: keranjang.php?err=" . urlencode("Keranjang belanja Anda masih kosong."));
        exit;
    }

    $metode_valid = ['QRIS Instan', 'Transfer Virtual Account', 'Termin B2B'];
    $metode_bayar = trim($_POST['metode_bayar'] ?? '');
    $alamat       = trim($_POST['alamat_pengiriman'] ?? '');

    if (!in_array($metode_bayar, $metode_valid, true)) {
        header("Location: keranjang.php?err=" . urlencode("Metode pembayaran yang dipilih tidak valid."));
        exit;
    }

    if ($alamat === '') {
        header("Location: keranjang.php?err=" . urlencode("Alamat pengiriman wajib diisi lengkap."));
        exit;
    }

    $pdo->beginTransaction();
    try {
        $cartIds      = array_map('intval', array_keys($_SESSION['cart']));
        $placeholders = implode(',', array_fill(0, count($cartIds), '?'));

        // Row-level lock mencegah race condition / overselling saat checkout bersamaan
        $stmt = $pdo->prepare("SELECT * FROM produk_etalase WHERE id_produk IN ({$placeholders}) FOR UPDATE");
        $stmt->execute($cartIds);
        $produk_db = $stmt->fetchAll();

        if (count($produk_db) !== count($cartIds)) {
            throw new Exception("Sebagian produk di keranjang sudah tidak tersedia. Silakan periksa kembali keranjang Anda.");
        }

        $total_bayar = 0;
        foreach ($produk_db as $p) {
            $qty = (int)$_SESSION['cart'][$p['id_produk']];
            if ($qty <= 0 || (int)$p['stok'] < $qty) {
                throw new Exception("Stok untuk produk '" . $p['nama_produk'] . "' tidak mencukupi (tersisa: " . $p['stok'] . " " . $p['satuan'] . ").");
            }
            $total_bayar += $qty * (float)$p['harga_jual'];
        }

        $no_invoice = "INV-KPN-" . date('Ymd') . "-" . random_int(100, 999);
        $insOrder   = $pdo->prepare("
            INSERT INTO orders (no_invoice, id_buyer, total_bayar, metode_bayar, alamat_pengiriman)
            VALUES (?, ?, ?, ?, ?)
        ");
        $insOrder->execute([
            $no_invoice,
            (int)$_SESSION['user']['id_user'],
            $total_bayar,
            $metode_bayar,
            $alamat
        ]);
        $id_order = (int)$pdo->lastInsertId();

        $insItem = $pdo->prepare("
            INSERT INTO order_items (id_order, id_produk, qty, harga_satuan, subtotal)
            VALUES (?, ?, ?, ?, ?)
        ");
        $updStok = $pdo->prepare("
            UPDATE produk_etalase
            SET stok = stok - ?
            WHERE id_produk = ? AND stok >= ?
        ");

        foreach ($produk_db as $p) {
            $qty = (int)$_SESSION['cart'][$p['id_produk']];
            $sub = $qty * (float)$p['harga_jual'];

            $insItem->execute([$id_order, $p['id_produk'], $qty, $p['harga_jual'], $sub]);
            $updStok->execute([$qty, $p['id_produk'], $qty]);

            if ($updStok->rowCount() === 0) {
                throw new Exception("Gagal memotong stok untuk produk '" . $p['nama_produk'] . "'.");
            }
        }

        $pdo->commit();
        unset($_SESSION['cart']);
        header("Location: ../transaksi/index.php?msg=" . urlencode("Pesanan {$no_invoice} berhasil dibuat dan diteruskan ke Koperasi Konsumen."));
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        header("Location: keranjang.php?err=" . urlencode($e->getMessage()));
        exit;
    }
}

header("Location: index.php");
exit;