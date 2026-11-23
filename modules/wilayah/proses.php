<?php
require_once '../../config/database.php';
cekAkses(['konsumen']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

verify_csrf();
$aksi = $_POST['aksi'] ?? '';

if ($aksi === 'tambah_divisi') {
    $kode     = trim($_POST['kode_divisi'] ?? '');
    $nama     = trim($_POST['nama_divisi'] ?? '');
    $provinsi = trim($_POST['provinsi'] ?? '');

    if ($kode === '' || $nama === '' || $provinsi === '') {
        header("Location: index.php?err=Semua kolom divisi wilayah wajib diisi.");
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO divisi_wilayah (kode_divisi, nama_divisi, provinsi) VALUES (?, ?, ?)");
    $stmt->execute([$kode, $nama, $provinsi]);
    header("Location: index.php?msg=Divisi Kabupaten/Kota baru berhasil ditambahkan.");
    exit;
}

if ($aksi === 'tambah_primer') {
    $id_divisi = (int)($_POST['id_divisi'] ?? 0);
    $kode      = trim($_POST['kode_primer'] ?? '');
    $nama      = trim($_POST['nama_koperasi'] ?? '');
    $kecamatan = trim($_POST['kecamatan'] ?? '');
    $ketua     = trim($_POST['ketua_koperasi'] ?? '');

    if ($id_divisi <= 0 || $kode === '' || $nama === '' || $kecamatan === '' || $ketua === '') {
        header("Location: index.php?err=Semua kolom unit koperasi primer wajib diisi.");
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO koperasi_primer (id_divisi, kode_primer, nama_koperasi, kecamatan, ketua_koperasi) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$id_divisi, $kode, $nama, $kecamatan, $ketua]);
    header("Location: index.php?msg=Unit Koperasi Primer kecamatan berhasil didaftarkan.");
    exit;
}
?>