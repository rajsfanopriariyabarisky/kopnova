<?php
require_once '../../config/database.php';
cekAkses(['petani', 'primer']);

if (isset($_POST['aksi']) && $_POST['aksi'] === 'setor_petani') {
    $foto = uploadFoto('foto_pasokan', 'pasokan') ?? 'default.jpg';
    $kode = "PSK-" . date('Ymd') . "-" . rand(100, 999);
    $jumlah = (int)$_POST['jumlah'];
    $harga = (float)$_POST['harga_tawar'];
    $total = $jumlah * $harga;

    $stmt = $pdo->prepare("INSERT INTO pasokan_petani (kode_pasokan, id_petani, id_primer, nama_komoditas, kategori, jumlah, satuan, harga_tawar, total_nilai, foto_pasokan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$kode, $_SESSION['user']['id_user'], $_POST['id_primer'], $_POST['nama_komoditas'], $_POST['kategori'], $jumlah, $_POST['satuan'], $harga, $total, $foto]);

    header("Location: index.php?msg=Setoran panen $kode berhasil dikirim ke Koperasi Primer.");
    exit;
}

if (isset($_GET['terima'])) {
    cekAkses(['primer']);
    $stmt = $pdo->prepare("UPDATE pasokan_petani SET status_pasokan = 'Diterima Primer' WHERE id_pasokan = ?");
    $stmt->execute([$_GET['terima']]);
    header("Location: index.php?msg=Pasokan petani telah diverifikasi dan diterima.");
    exit;
}
?>