<?php
require_once '../../config/database.php';
cekAkses(['konsumen']);

if (isset($_POST['aksi']) && $_POST['aksi'] === 'tambah_regulasi') {
    $p_petani   = (float)$_POST['persen_petani'];
    $p_primer   = (float)$_POST['persen_primer'];
    $p_konsumen = (float)$_POST['persen_konsumen'];

    if (round($p_petani + $p_primer + $p_konsumen, 2) !== 100.00) {
        header("Location: index.php?err=Total persentase bagi hasil (Petani + Primer + Konsumen) wajib tepat 100%.");
        exit;
    }

    $kode = 'REG-' . date('Y') . '-' . rand(100, 999);
    $stmt = $pdo->prepare("INSERT INTO harga_acuan_shu (kode_regulasi, nama_komoditas, kategori, satuan, hap_petani, het_buyer, persen_petani, persen_primer, persen_konsumen) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $kode,
        trim($_POST['nama_komoditas']),
        $_POST['kategori'],
        trim($_POST['satuan']),
        (float)$_POST['hap_petani'],
        (float)$_POST['het_buyer'],
        $p_petani,
        $p_primer,
        $p_konsumen
    ]);

    header("Location: index.php?msg=Ketetapan harga acuan ($kode) berhasil diterbitkan dan berlaku untuk seluruh Koperasi Primer.");
    exit;
}

if (isset($_POST['aksi']) && $_POST['aksi'] === 'edit_regulasi') {
    $p_petani   = (float)$_POST['persen_petani'];
    $p_primer   = (float)$_POST['persen_primer'];
    $p_konsumen = (float)$_POST['persen_konsumen'];

    if (round($p_petani + $p_primer + $p_konsumen, 2) !== 100.00) {
        header("Location: index.php?err=Total persentase bagi hasil wajib tepat 100%.");
        exit;
    }

    $stmt = $pdo->prepare("UPDATE harga_acuan_shu SET nama_komoditas=?, hap_petani=?, het_buyer=?, persen_petani=?, persen_primer=?, persen_konsumen=?, status_regulasi=? WHERE id_regulasi=?");
    $stmt->execute([
        trim($_POST['nama_komoditas']),
        (float)$_POST['hap_petani'],
        (float)$_POST['het_buyer'],
        $p_petani,
        $p_primer,
        $p_konsumen,
        $_POST['status_regulasi'],
        (int)$_POST['id_regulasi']
    ]);

    header("Location: index.php?msg=Ketetapan harga acuan dan rasio SHU berhasil diperbarui.");
    exit;
}

if (isset($_GET['hapus'])) {
    $pdo->prepare("DELETE FROM harga_acuan_shu WHERE id_regulasi = ?")->execute([(int)$_GET['hapus']]);
    header("Location: index.php?msg=Regulasi harga acuan berhasil dihapus.");
    exit;
}
?>