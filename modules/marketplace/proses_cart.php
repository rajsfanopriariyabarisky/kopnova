<?php
require_once '../../config/database.php';
cekAkses(['buyer']);

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

verify_csrf();
$aksi = $_POST['aksi'] ?? '';

// 1. Tambah ke keranjang
if ($aksi === 'add') {
    $id  = (int)($_POST['id_produk'] ?? 0);
    $qty = max(1, (int)($_POST['qty'] ?? 1));

    if ($id > 0) {
        $_SESSION['cart'][$id] = ($_SESSION['cart'][$id] ?? 0) + $qty;
    }
    header("Location: index.php?msg=Produk berhasil ditambahkan ke keranjang belanja.");
    exit;
}

// 2. Hapus dari keranjang (Wajib POST + CSRF)
if ($aksi === 'hapus') {
    $id = (int)($_POST['id_produk'] ?? 0);
    unset($_SESSION['cart'][$id]);
    header("Location: keranjang.php?msg=Produk dihapus dari keranjang.");
    exit;
}

// 3. Checkout -> Kunci Stok (FOR UPDATE) & Buat Order Atomik
if ($aksi === 'checkout' && !empty($_SESSION['cart'])) {
    $metode_bayar = trim($_POST['metode_bayar'] ?? '');
    $alamat       = trim($_POST['alamat_pengiriman'] ?? '');

    if ($metode_bayar === '' || $alamat === '') {
        header("Location: keranjang.php?err=Metode pembayaran dan alamat pengiriman wajib diisi.");
        exit;
    }

    $pdo->beginTransaction();
    try {
        $cartIds = array_map('intval', array_keys($_SESSION['cart']));
        $placeholders = implode(',', array_fill(0, count($cartIds), '?'));

        // Row-level lock mencegah race condition / overselling saat checkout bersamaan
        $stmt = $pdo->prepare("SELECT * FROM produk_etalase WHERE id_produk IN ($placeholders) FOR UPDATE");
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
        $insOrder = $pdo->prepare("INSERT INTO orders (no_invoice, id_buyer, total_bayar, metode_bayar, alamat_pengiriman) VALUES (?, ?, ?, ?, ?)");
        $insOrder->execute([$no_invoice, $_SESSION['user']['id_user'], $total_bayar, $metode_bayar, $alamat]);
        $id_order = $pdo->lastInsertId();

        $insItem = $pdo->prepare("INSERT INTO order_items (id_order, id_produk, qty, harga_satuan, subtotal) VALUES (?, ?, ?, ?, ?)");
        $updStok = $pdo->prepare("UPDATE produk_etalase SET stok = stok - ? WHERE id_produk = ? AND stok >= ?");

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
        header("Location: ../transaksi/index.php?msg=Pesanan $no_invoice berhasil dibuat dan diteruskan ke Koperasi Konsumen.");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        header("Location: keranjang.php?err=" . urlencode($e->getMessage()));
        exit;
    }
}
?>