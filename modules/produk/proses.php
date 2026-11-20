<?php
require_once '../../config/database.php';
cekAkses(['primer', 'konsumen']);

if (isset($_POST['aksi']) && $_POST['aksi'] === 'tambah_produk') {
    list($id_primer, $id_divisi) = explode('|', $_POST['primer_divisi']);
    $foto = uploadFoto('foto_produk', 'produk') ?? 'default.jpg';
    $kode = "PRD-" . rand(1000, 9999);

    $stmt = $pdo->prepare("INSERT INTO produk_etalase (id_divisi, id_primer, kode_produk, nama_produk, kategori, harga_jual, stok, satuan, foto_produk, deskripsi) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$id_divisi, $id_primer, $kode, $_POST['nama_produk'], $_POST['kategori'], $_POST['harga_jual'], $_POST['stok'], $_POST['satuan'], $foto, $_POST['deskripsi']]);

    header("Location: index.php?msg=Produk berhasil dipublikasikan ke etalase Online Shop Buyer.");
    exit;
}

if (isset($_GET['hapus'])) {
    $stmt = $pdo->prepare("DELETE FROM produk_etalase WHERE id_produk = ?");
    $stmt->execute([$_GET['hapus']]);
    header("Location: index.php?msg=Produk dihapus dari etalase.");
    exit;
}
?>