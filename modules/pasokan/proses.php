<?php
require_once '../../config/database.php';
cekAkses(['petani', 'primer']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

verify_csrf();
$aksi = $_POST['aksi'] ?? '';

if ($aksi === 'setor_petani') {
    cekAkses(['petani']);

    $jumlah = (int)($_POST['jumlah'] ?? 0);
    $harga  = (float)($_POST['harga_tawar'] ?? 0);
    $id_primer = (int)($_POST['id_primer'] ?? 0);

    if ($jumlah <= 0 || $harga <= 0 || $id_primer <= 0) {
        header("Location: index.php?err=Jumlah, harga tawar, dan koperasi tujuan harus lebih dari 0.");
        exit;
    }

    $foto = uploadFoto('foto_pasokan', 'pasokan') ?? 'default.jpg';
    $kode = "PSK-" . date('Ymd') . "-" . random_int(100, 999);
    $total = $jumlah * $harga;

    $stmt = $pdo->prepare("INSERT INTO pasokan_petani (kode_pasokan, id_petani, id_primer, nama_komoditas, kategori, jumlah, satuan, harga_tawar, total_nilai, foto_pasokan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $kode,
        $_SESSION['user']['id_user'],
        $id_primer,
        trim($_POST['nama_komoditas']),
        trim($_POST['kategori']),
        $jumlah,
        trim($_POST['satuan']),
        $harga,
        $total,
        $foto
    ]);

    header("Location: index.php?msg=Setoran panen $kode berhasil dikirim ke Koperasi Primer.");
    exit;
}

if ($aksi === 'terima_pasokan') {
    cekAkses(['primer']);

    $id_pasokan = (int)($_POST['id_pasokan'] ?? 0);
    $id_primer_login = (int)($_SESSION['user']['id_primer'] ?? 0);

    // Ownership check: pastikan pasokan memang ditujukan untuk koperasi primer yang sedang login
    $stmt = $pdo->prepare("UPDATE pasokan_petani SET status_pasokan = 'Diterima Primer' WHERE id_pasokan = ? AND id_primer = ? AND status_pasokan != 'Diterima Primer'");
    $stmt->execute([$id_pasokan, $id_primer_login]);

    if ($stmt->rowCount() > 0) {
        header("Location: index.php?msg=Pasokan petani telah diverifikasi dan diterima.");
    } else {
        header("Location: index.php?err=Pasokan tidak ditemukan atau bukan wewenang koperasi Anda.");
    }
    exit;
}
?>