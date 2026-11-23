<?php
require_once '../../config/database.php';
cekAkses(['primer', 'konsumen']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

verify_csrf();
$role = $_SESSION['user']['role'];
$user = $_SESSION['user'];
$aksi = $_POST['aksi'] ?? '';

if ($aksi === 'tambah_produk') {
    if ($role === 'primer') {
        $id_primer = (int)($user['id_primer'] ?? 0);
        $stmtDiv = $pdo->prepare("SELECT id_divisi FROM koperasi_primer WHERE id_primer = ?");
        $stmtDiv->execute([$id_primer]);
        $id_divisi = (int)$stmtDiv->fetchColumn();
    } else {
        $parts = explode('|', $_POST['primer_divisi'] ?? '0|0');
        $id_primer = (int)($parts[0] ?? 0);
        $id_divisi = (int)($parts[1] ?? 0);
    }

    $harga_jual = (float)($_POST['harga_jual'] ?? 0);
    $stok       = (int)($_POST['stok'] ?? 0);

    if ($id_primer <= 0 || $id_divisi <= 0 || $harga_jual <= 0 || $stok < 0) {
        header("Location: index.php?err=Data unit koperasi, harga jual (> 0), atau stok tidak valid.");
        exit;
    }

    $foto = uploadFoto('foto_produk', 'produk') ?? 'default.jpg';
    $kode = "PRD-" . random_int(1000, 9999);

    $stmt = $pdo->prepare("INSERT INTO produk_etalase (id_divisi, id_primer, kode_produk, nama_produk, kategori, harga_jual, stok, satuan, foto_produk, deskripsi) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $id_divisi,
        $id_primer,
        $kode,
        trim($_POST['nama_produk']),
        trim($_POST['kategori']),
        $harga_jual,
        $stok,
        trim($_POST['satuan']),
        $foto,
        trim($_POST['deskripsi'] ?? '')
    ]);

    header("Location: index.php?msg=Produk berhasil dipublikasikan ke etalase Online Shop Buyer.");
    exit;
}

if ($aksi === 'hapus_produk') {
    $id_produk = (int)($_POST['id_produk'] ?? 0);

    if ($role === 'primer') {
        $stmt = $pdo->prepare("DELETE FROM produk_etalase WHERE id_produk = ? AND id_primer = ?");
        $stmt->execute([$id_produk, (int)($user['id_primer'] ?? 0)]);
    } else {
        $stmt = $pdo->prepare("DELETE FROM produk_etalase WHERE id_produk = ?");
        $stmt->execute([$id_produk]);
    }

    if ($stmt->rowCount() > 0) {
        header("Location: index.php?msg=Produk dihapus dari etalase.");
    } else {
        header("Location: index.php?err=Produk tidak ditemukan atau bukan milik koperasi Anda.");
    }
    exit;
}
?>