<?php
require_once '../../config/database.php';
cekAkses(['konsumen']);

function redirectRegulasi(string $type, string $text): void {
    header("Location: index.php?" . $type . "=" . urlencode($text));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

verify_csrf();
$aksi = trim($_POST['aksi'] ?? '');

$valid_kategori = ['Komoditas Tanaman', 'Saprodi', 'Alsintan', 'Produk Olahan', 'Produk Lainnya'];
$valid_status   = ['Aktif', 'Arsip'];

if ($aksi === 'tambah_regulasi' || $aksi === 'edit_regulasi') {
    $nama_kom   = trim($_POST['nama_komoditas'] ?? '');
    $kategori   = trim($_POST['kategori'] ?? '');
    $satuan     = trim($_POST['satuan'] ?? '');
    $p_petani   = (float)($_POST['persen_petani'] ?? 0);
    $p_primer   = (float)($_POST['persen_primer'] ?? 0);
    $p_konsumen = (float)($_POST['persen_konsumen'] ?? 0);
    $hap        = (float)($_POST['hap_petani'] ?? 0);
    $het        = (float)($_POST['het_buyer'] ?? 0);

    if ($nama_kom === '' || $satuan === '' || !in_array($kategori, $valid_kategori, true)) {
        redirectRegulasi('err', 'Nama komoditas, kategori klaster, dan satuan wajib diisi dengan valid.');
    }

    if ($hap <= 0 || $het <= 0) {
        redirectRegulasi('err', 'Harga acuan HAP dan Harga Jual Buyer (HET) harus lebih dari 0.');
    }

    if ($het < $hap) {
        redirectRegulasi('err', 'Harga Jual Buyer (HET) tidak boleh lebih rendah dari batas minimum HAP Petani.');
    }

    if ($p_petani < 0 || $p_primer < 0 || $p_konsumen < 0 || round($p_petani + $p_primer + $p_konsumen, 2) !== 100.00) {
        redirectRegulasi('err', 'Setiap persentase SHU tidak boleh negatif dan totalnya wajib tepat 100%.');
    }

    try {
        if ($aksi === 'tambah_regulasi') {
            $kode = 'REG-' . date('Y') . '-' . random_int(100, 999);
            $stmt = $pdo->prepare("
                INSERT INTO harga_acuan_shu
                (kode_regulasi, nama_komoditas, kategori, satuan, hap_petani, het_buyer, persen_petani, persen_primer, persen_konsumen)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $kode,
                $nama_kom,
                $kategori,
                $satuan,
                $hap,
                $het,
                $p_petani,
                $p_primer,
                $p_konsumen
            ]);
            redirectRegulasi('msg', "Ketetapan harga acuan ({$kode}) berhasil diterbitkan dan berlaku untuk seluruh Koperasi Primer.");
        }

        $id_regulasi = (int)($_POST['id_regulasi'] ?? 0);
        $status_reg  = trim($_POST['status_regulasi'] ?? 'Aktif');

        if ($id_regulasi <= 0 || !in_array($status_reg, $valid_status, true)) {
            redirectRegulasi('err', 'Data regulasi atau status ketetapan tidak valid.');
        }

        $stmt = $pdo->prepare("
            UPDATE harga_acuan_shu
            SET nama_komoditas = ?,
                kategori = ?,
                satuan = ?,
                hap_petani = ?,
                het_buyer = ?,
                persen_petani = ?,
                persen_primer = ?,
                persen_konsumen = ?,
                status_regulasi = ?
            WHERE id_regulasi = ?
        ");
        $stmt->execute([
            $nama_kom,
            $kategori,
            $satuan,
            $hap,
            $het,
            $p_petani,
            $p_primer,
            $p_konsumen,
            $status_reg,
            $id_regulasi
        ]);
        redirectRegulasi('msg', 'Ketetapan harga acuan dan rasio SHU berhasil diperbarui.');
    } catch (PDOException $e) {
        redirectRegulasi('err', 'Terjadi kesalahan saat menyimpan ketetapan harga acuan.');
    }
}

if ($aksi === 'hapus_regulasi') {
    $id_regulasi = (int)($_POST['id_regulasi'] ?? 0);
    if ($id_regulasi <= 0) {
        redirectRegulasi('err', 'ID regulasi tidak valid.');
    }

    try {
        $stmtDel = $pdo->prepare("DELETE FROM harga_acuan_shu WHERE id_regulasi = ?");
        $stmtDel->execute([$id_regulasi]);

        if ($stmtDel->rowCount() > 0) {
            redirectRegulasi('msg', 'Regulasi harga acuan berhasil dihapus.');
        } else {
            redirectRegulasi('err', 'Data regulasi harga acuan tidak ditemukan.');
        }
    } catch (PDOException $e) {
        redirectRegulasi('err', 'Gagal menghapus regulasi harga acuan karena masih terkait dengan data lain.');
    }
}

redirectRegulasi('err', 'Aksi permintaan tidak dikenali.');