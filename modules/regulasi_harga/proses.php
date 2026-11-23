<?php
require_once '../../config/database.php';
cekAkses(['konsumen']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

verify_csrf();
$aksi = $_POST['aksi'] ?? '';

if ($aksi === 'tambah_regulasi' || $aksi === 'edit_regulasi') {
    $p_petani   = (float)($_POST['persen_petani'] ?? 0);
    $p_primer   = (float)($_POST['persen_primer'] ?? 0);
    $p_konsumen = (float)($_POST['persen_konsumen'] ?? 0);
    $hap        = (float)($_POST['hap_petani'] ?? 0);
    $het        = (float)($_POST['het_buyer'] ?? 0);

    if ($p_petani < 0 || $p_primer < 0 || $p_konsumen < 0 || round($p_petani + $p_primer + $p_konsumen, 2) !== 100.00) {
        header("Location: index.php?err=Setiap persentase SHU tidak boleh negatif dan totalnya wajib tepat 100%.");
        exit;
    }

    if ($hap <= 0 || $het <= 0) {
        header("Location: index.php?err=Harga acuan HAP dan HET harus lebih dari 0.");
        exit;
    }

    if ($aksi === 'tambah_regulasi') {
        $kode = 'REG-' . date('Y') . '-' . random_int(100, 999);
        $stmt = $pdo->prepare("INSERT INTO harga_acuan_shu (kode_regulasi, nama_komoditas, kategori, satuan, hap_petani, het_buyer, persen_petani, persen_primer, persen_konsumen) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $kode,
            trim($_POST['nama_komoditas']),
            $_POST['kategori'],
            trim($_POST['satuan']),
            $hap,
            $het,
            $p_petani,
            $p_primer,
            $p_konsumen
        ]);
        header("Location: index.php?msg=Ketetapan harga acuan ($kode) berhasil diterbitkan dan berlaku untuk seluruh Koperasi Primer.");
        exit;
    }

    $stmt = $pdo->prepare("UPDATE harga_acuan_shu SET nama_komoditas=?, hap_petani=?, het_buyer=?, persen_petani=?, persen_primer=?, persen_konsumen=?, status_regulasi=? WHERE id_regulasi=?");
    $stmt->execute([
        trim($_POST['nama_komoditas']),
        $hap,
        $het,
        $p_petani,
        $p_primer,
        $p_konsumen,
        $_POST['status_regulasi'],
        (int)($_POST['id_regulasi'] ?? 0)
    ]);
    header("Location: index.php?msg=Ketetapan harga acuan dan rasio SHU berhasil diperbarui.");
    exit;
}

if ($aksi === 'hapus_regulasi') {
    $pdo->prepare("DELETE FROM harga_acuan_shu WHERE id_regulasi = ?")->execute([(int)($_POST['id_regulasi'] ?? 0)]);
    header("Location: index.php?msg=Regulasi harga acuan berhasil dihapus.");
    exit;
}
?>