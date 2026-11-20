<?php
require_once '../../config/database.php';
cekAkses(['buyer']);

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Tambah ke keranjang
if (isset($_POST['aksi']) && $_POST['aksi'] === 'add') {
    $id = (int)$_POST['id_produk'];
    $qty = max(1, (int)$_POST['qty']);
    $_SESSION['cart'][$id] = ($_SESSION['cart'][$id] ?? 0) + $qty;
    header("Location: index.php?msg=Produk berhasil ditambahkan ke keranjang belanja.");
    exit;
}

// Hapus dari keranjang
if (isset($_GET['hapus'])) {
    unset($_SESSION['cart'][(int)$_GET['hapus']]);
    header("Location: keranjang.php");
    exit;
}

// Checkout -> Masuk ke Dashboard Utama Koperasi Konsumen & Potong Stok
if (isset($_POST['aksi']) && $_POST['aksi'] === 'checkout' && !empty($_SESSION['cart'])) {
    $pdo->beginTransaction();
    try {
        $ids = implode(',', array_map('intval', array_keys($_SESSION['cart'])));
        $stmt = $pdo->query("SELECT * FROM produk_etalase WHERE id_produk IN ($ids)");
        $produk_db = $stmt->fetchAll();

        $total_bayar = 0;
        foreach ($produk_db as $p) {
            $qty = $_SESSION['cart'][$p['id_produk']];
            $total_bayar += $qty * $p['harga_jual'];
        }

        $no_invoice = "INV-KPN-" . date('Ymd') . "-" . rand(100, 999);
        $insOrder = $pdo->prepare("INSERT INTO orders (no_invoice, id_buyer, total_bayar, metode_bayar, alamat_pengiriman) VALUES (?, ?, ?, ?, ?)");
        $insOrder->execute([$no_invoice, $_SESSION['user']['id_user'], $total_bayar, $_POST['metode_bayar'], $_POST['alamat_pengiriman']]);
        $id_order = $pdo->lastInsertId();

        $insItem = $pdo->prepare("INSERT INTO order_items (id_order, id_produk, qty, harga_satuan, subtotal) VALUES (?, ?, ?, ?, ?)");
        $updStok = $pdo->prepare("UPDATE produk_etalase SET stok = GREATEST(0, stok - ?) WHERE id_produk = ?");

        foreach ($produk_db as $p) {
            $qty = $_SESSION['cart'][$p['id_produk']];
            $sub = $qty * $p['harga_jual'];
            $insItem->execute([$id_order, $p['id_produk'], $qty, $p['harga_jual'], $sub]);
            $updStok->execute([$qty, $p['id_produk']]);
        }

        $pdo->commit();
        unset($_SESSION['cart']);
        header("Location: ../transaksi/index.php?msg=Pesanan $no_invoice berhasil dibuat dan diteruskan ke Koperasi Konsumen.");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Gagal memproses pesanan: " . $e->getMessage());
    }
}
?>