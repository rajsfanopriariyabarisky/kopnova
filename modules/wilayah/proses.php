<?php
require_once '../../config/database.php';
cekAkses(['konsumen']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

verify_csrf();
$aksi = $_POST['aksi'] ?? '';

function redirectWilayah(string $type, string $text): void {
    header("Location: index.php?" . $type . "=" . urlencode($text));
    exit;
}

try {
    /* =========================================================================
       1. TAMBAH DIVISI KABUPATEN / KOTA
       ========================================================================= */
    if ($aksi === 'tambah_divisi') {
        $kode     = strtoupper(trim($_POST['kode_divisi'] ?? ''));
        $nama     = trim($_POST['nama_divisi'] ?? '');
        $provinsi = trim($_POST['provinsi'] ?? '');

        if ($kode === '' || $nama === '' || $provinsi === '') {
            redirectWilayah('err', 'Semua kolom divisi wilayah wajib diisi.');
        }

        $cek = $pdo->prepare("SELECT COUNT(*) FROM divisi_wilayah WHERE kode_divisi = ?");
        $cek->execute([$kode]);
        if ((int)$cek->fetchColumn() > 0) {
            redirectWilayah('err', "Kode Divisi '{$kode}' sudah terdaftar. Gunakan kode lain.");
        }

        $stmt = $pdo->prepare("INSERT INTO divisi_wilayah (kode_divisi, nama_divisi, provinsi) VALUES (?, ?, ?)");
        $stmt->execute([$kode, $nama, $provinsi]);
        redirectWilayah('msg', 'Divisi Kabupaten/Kota baru berhasil ditambahkan.');
    }

    /* =========================================================================
       2. EDIT DIVISI KABUPATEN / KOTA
       ========================================================================= */
    if ($aksi === 'edit_divisi') {
        $id_divisi = (int)($_POST['id_divisi'] ?? 0);
        $kode      = strtoupper(trim($_POST['kode_divisi'] ?? ''));
        $nama      = trim($_POST['nama_divisi'] ?? '');
        $provinsi  = trim($_POST['provinsi'] ?? '');

        if ($id_divisi <= 0 || $kode === '' || $nama === '' || $provinsi === '') {
            redirectWilayah('err', 'Data pembaruan divisi wilayah tidak lengkap.');
        }

        $cek = $pdo->prepare("SELECT COUNT(*) FROM divisi_wilayah WHERE kode_divisi = ? AND id_divisi != ?");
        $cek->execute([$kode, $id_divisi]);
        if ((int)$cek->fetchColumn() > 0) {
            redirectWilayah('err', "Kode Divisi '{$kode}' sudah digunakan divisi lain.");
        }

        $stmt = $pdo->prepare("UPDATE divisi_wilayah SET kode_divisi = ?, nama_divisi = ?, provinsi = ? WHERE id_divisi = ?");
        $stmt->execute([$kode, $nama, $provinsi, $id_divisi]);
        redirectWilayah('msg', 'Data Divisi Kabupaten/Kota berhasil diperbarui.');
    }

    /* =========================================================================
       3. HAPUS DIVISI KABUPATEN / KOTA
       ========================================================================= */
    if ($aksi === 'hapus_divisi') {
        $id_divisi = (int)($_POST['id_divisi'] ?? 0);
        if ($id_divisi <= 0) {
            redirectWilayah('err', 'ID Divisi tidak valid.');
        }

        $cekAnak = $pdo->prepare("SELECT COUNT(*) FROM koperasi_primer WHERE id_divisi = ?");
        $cekAnak->execute([$id_divisi]);
        $jumlahUnit = (int)$cekAnak->fetchColumn();

        if ($jumlahUnit > 0) {
            redirectWilayah('err', "Divisi tidak dapat dihapus karena masih menaungi {$jumlahUnit} Koperasi Primer.");
        }

        $stmt = $pdo->prepare("DELETE FROM divisi_wilayah WHERE id_divisi = ?");
        $stmt->execute([$id_divisi]);
        redirectWilayah('msg', 'Divisi Kabupaten/Kota berhasil dihapus.');
    }

    /* =========================================================================
       4. TAMBAH KOPERASI PRIMER KECAMATAN
       ========================================================================= */
    if ($aksi === 'tambah_primer') {
        $id_divisi = (int)($_POST['id_divisi'] ?? 0);
        $kode      = strtoupper(trim($_POST['kode_primer'] ?? ''));
        $nama      = trim($_POST['nama_koperasi'] ?? '');
        $kecamatan = trim($_POST['kecamatan'] ?? '');
        $ketua     = trim($_POST['ketua_koperasi'] ?? '');

        if ($id_divisi <= 0 || $kode === '' || $nama === '' || $kecamatan === '' || $ketua === '') {
            redirectWilayah('err', 'Semua kolom unit koperasi primer wajib diisi.');
        }

        $cek = $pdo->prepare("SELECT COUNT(*) FROM koperasi_primer WHERE kode_primer = ?");
        $cek->execute([$kode]);
        if ((int)$cek->fetchColumn() > 0) {
            redirectWilayah('err', "Kode Unit '{$kode}' sudah terdaftar. Gunakan kode lain.");
        }

        $stmt = $pdo->prepare("INSERT INTO koperasi_primer (id_divisi, kode_primer, nama_koperasi, kecamatan, ketua_koperasi) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$id_divisi, $kode, $nama, $kecamatan, $ketua]);
        redirectWilayah('msg', 'Unit Koperasi Primer kecamatan berhasil didaftarkan.');
    }

    /* =========================================================================
       5. EDIT KOPERASI PRIMER KECAMATAN
       ========================================================================= */
    if ($aksi === 'edit_primer') {
        $id_primer = (int)($_POST['id_primer'] ?? 0);
        $id_divisi = (int)($_POST['id_divisi'] ?? 0);
        $kode      = strtoupper(trim($_POST['kode_primer'] ?? ''));
        $nama      = trim($_POST['nama_koperasi'] ?? '');
        $kecamatan = trim($_POST['kecamatan'] ?? '');
        $ketua     = trim($_POST['ketua_koperasi'] ?? '');

        if ($id_primer <= 0 || $id_divisi <= 0 || $kode === '' || $nama === '' || $kecamatan === '' || $ketua === '') {
            redirectWilayah('err', 'Data pembaruan unit koperasi primer tidak lengkap.');
        }

        $cek = $pdo->prepare("SELECT COUNT(*) FROM koperasi_primer WHERE kode_primer = ? AND id_primer != ?");
        $cek->execute([$kode, $id_primer]);
        if ((int)$cek->fetchColumn() > 0) {
            redirectWilayah('err', "Kode Unit '{$kode}' sudah digunakan koperasi lain.");
        }

        $stmt = $pdo->prepare("UPDATE koperasi_primer SET id_divisi = ?, kode_primer = ?, nama_koperasi = ?, kecamatan = ?, ketua_koperasi = ? WHERE id_primer = ?");
        $stmt->execute([$id_divisi, $kode, $nama, $kecamatan, $ketua, $id_primer]);
        redirectWilayah('msg', 'Data Koperasi Primer berhasil diperbarui.');
    }

    /* =========================================================================
       6. HAPUS KOPERASI PRIMER KECAMATAN
       ========================================================================= */
    if ($aksi === 'hapus_primer') {
        $id_primer = (int)($_POST['id_primer'] ?? 0);
        if ($id_primer <= 0) {
            redirectWilayah('err', 'ID Koperasi Primer tidak valid.');
        }

        $stmt = $pdo->prepare("DELETE FROM koperasi_primer WHERE id_primer = ?");
        $stmt->execute([$id_primer]);
        redirectWilayah('msg', 'Unit Koperasi Primer berhasil dihapus.');
    }

    redirectWilayah('err', 'Aksi tidak dikenali.');
} catch (PDOException $e) {
    redirectWilayah('err', 'Operasi gagal diproses karena data masih terhubung dengan modul transaksi/stok.');
}