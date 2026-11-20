<?php
require_once '../../config/database.php';
cekAkses(['konsumen']);

// 1. TERBITKAN AKUN BARU
if (isset($_POST['aksi']) && $_POST['aksi'] === 'tambah_user') {
    $username = trim($_POST['username']);
    $nama     = trim($_POST['nama_lengkap']);
    $role     = $_POST['role'];
    $passRaw  = trim($_POST['password']) ?: '123';
    $passHash = password_hash($passRaw, PASSWORD_DEFAULT);

    // Cek duplikasi username
    $cek = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $cek->execute([$username]);
    if ($cek->fetchColumn() > 0) {
        header("Location: index.php?err=Username @$username sudah terdaftar. Gunakan username lain.");
        exit;
    }

    $id_divisi = !empty($_POST['id_divisi']) ? (int)$_POST['id_divisi'] : null;
    $id_primer = !empty($_POST['id_primer']) ? (int)$_POST['id_primer'] : null;

    if ($role === 'buyer') {
        $id_divisi = null;
        $id_primer = null;
    } elseif ($role === 'konsumen') {
        $id_primer = null;
    }

    $stmt = $pdo->prepare("INSERT INTO users (id_divisi, id_primer, nama_lengkap, username, password, role, no_hp, alamat) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $id_divisi,
        $id_primer,
        $nama,
        $username,
        $passHash,
        $role,
        trim($_POST['no_hp']),
        trim($_POST['alamat'])
    ]);

    header("Location: index.php?msg=Akun @$username ($role) berhasil diterbitkan dan siap digunakan login.");
    exit;
}

// 2. EDIT OTORISASI & WILAYAH AKUN
if (isset($_POST['aksi']) && $_POST['aksi'] === 'edit_user') {
    $id_user   = (int)$_POST['id_user'];
    $username  = trim($_POST['username']);
    $nama      = trim($_POST['nama_lengkap']);
    $role      = $_POST['role'];
    $id_divisi = !empty($_POST['id_divisi']) ? (int)$_POST['id_divisi'] : null;
    $id_primer = !empty($_POST['id_primer']) ? (int)$_POST['id_primer'] : null;

    // Pastikan username tidak bentrok dengan user lain
    $cek = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id_user != ?");
    $cek->execute([$username, $id_user]);
    if ($cek->fetchColumn() > 0) {
        header("Location: index.php?err=Username @$username sudah digunakan oleh akun lain.");
        exit;
    }

    $stmt = $pdo->prepare("UPDATE users SET id_divisi=?, id_primer=?, nama_lengkap=?, username=?, role=?, no_hp=?, alamat=? WHERE id_user=?");
    $stmt->execute([
        $id_divisi,
        $id_primer,
        $nama,
        $username,
        $role,
        trim($_POST['no_hp']),
        trim($_POST['alamat']),
        $id_user
    ]);

    header("Location: index.php?msg=Data otorisasi dan penempatan wilayah akun @$username berhasil diperbarui.");
    exit;
}

// 3. RESET KATA SANDI KE DEFAULT (123)
if (isset($_GET['reset_pw'])) {
    $id_user  = (int)$_GET['reset_pw'];
    $passHash = password_hash('123', PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE users SET password = ? WHERE id_user = ?")->execute([$passHash, $id_user]);
    header("Location: index.php?msg=Kata sandi akun berhasil direset menjadi: 123");
    exit;
}

// 4. HAPUS AKUN
if (isset($_GET['hapus'])) {
    $id_user = (int)$_GET['hapus'];
    if ($id_user === (int)$_SESSION['user']['id_user']) {
        header("Location: index.php?err=Anda tidak dapat menghapus akun yang sedang aktif digunakan.");
        exit;
    }
    $pdo->prepare("DELETE FROM users WHERE id_user = ?")->execute([$id_user]);
    header("Location: index.php?msg=Akun pengguna berhasil dihapus dari sistem.");
    exit;
}
?>