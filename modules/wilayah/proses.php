<?php
require_once '../../config/database.php';
cekAkses(['konsumen']);

if ($_POST['aksi'] === 'tambah_divisi') {
    $stmt = $pdo->prepare("INSERT INTO divisi_wilayah (kode_divisi, nama_divisi, provinsi) VALUES (?, ?, ?)");
    $stmt->execute([$_POST['kode_divisi'], $_POST['nama_divisi'], $_POST['provinsi']]);
    header("Location: index.php?msg=Divisi Kabupaten/Kota baru berhasil ditambahkan.");
    exit;
}

if ($_POST['aksi'] === 'tambah_primer') {
    $stmt = $pdo->prepare("INSERT INTO koperasi_primer (id_divisi, kode_primer, nama_koperasi, kecamatan, ketua_koperasi) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$_POST['id_divisi'], $_POST['kode_primer'], $_POST['nama_koperasi'], $_POST['kecamatan'], $_POST['ketua_koperasi']]);
    header("Location: index.php?msg=Unit Koperasi Primer kecamatan berhasil didaftarkan.");
    exit;
}
?>