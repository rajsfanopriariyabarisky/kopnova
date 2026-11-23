<?php
require_once '../../config/database.php';
cekAkses(['konsumen']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

verify_csrf();
$aksi = $_POST['aksi'] ?? '';

// Helper internal untuk validasi role & konsistensi wilayah
function normalizeRoleWilayah($role, $rawDivisi, $rawPrimer) {
    $allowedRoles = ['konsumen', 'primer', 'petani', 'buyer'];
    if (!in_array($role, $allowedRoles, true)) {
        header("Location: index.php?err=Role pengguna tidak valid.");
        exit;
    }

    $id_divisi = !empty($rawDivisi) ? (int)$rawDivisi : null;
    $id_primer = !empty($rawPrimer) ? (int)$rawPrimer : null;

    if ($role === 'buyer') {
        $id_divisi = null;
        $id_primer = null;
    } elseif ($role === 'konsumen') {
        $id_primer = null;
    }

    return [$id_divisi, $id_primer];
}

// 1. TERBITKAN AKUN BARU
if ($aksi === 'tambah_user') {
    $username = trim($_POST['username'] ?? '');
    $nama     = trim($_POST['nama_lengkap'] ?? '');
    $role     = $_POST['role'] ?? '';
    $passRaw  = trim($_POST['password'] ?? '') ?: '123';
    $passHash = password_hash($passRaw, PASSWORD_DEFAULT);

    [$id_divisi, $id_primer] = normalizeRoleWilayah($role, $_POST['id_divisi'] ?? null, $_POST['id_primer'] ?? null);

    $cek = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $cek->execute([$username]);
    if ($cek->fetchColumn() > 0) {
        header("Location: index.php?err=Username @$username sudah terdaftar. Gunakan username lain.");
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO users (id_divisi, id_primer, nama_lengkap, username, password, role, no_hp, alamat) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $id_divisi,
        $id_primer,
        $nama,
        $username,
        $passHash,
        $role,
        trim($_POST['no_hp'] ?? ''),
        trim($_POST['alamat'] ?? '')
    ]);

    header("Location: index.php?msg=Akun @$username ($role) berhasil diterbitkan dan siap digunakan login.");
    exit;
}

// 2. EDIT OTORISASI & WILAYAH AKUN
if ($aksi === 'edit_user') {
    $id_user  = (int)($_POST['id_user'] ?? 0);
    $username = trim($_POST['username'] ?? '');
    $nama     = trim($_POST['nama_lengkap'] ?? '');
    $role     = $_POST['role'] ?? '';

    [$id_divisi, $id_primer] = normalizeRoleWilayah($role, $_POST['id_divisi'] ?? null, $_POST['id_primer'] ?? null);

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
        trim($_POST['no_hp'] ?? ''),
        trim($_POST['alamat'] ?? ''),
        $id_user
    ]);

    header("Location: index.php?msg=Data otorisasi dan penempatan wilayah akun @$username berhasil diperbarui.");
    exit;
}

// 3. RESET KATA SANDI KE DEFAULT (123)
if ($aksi === 'reset_pw') {
    $id_user  = (int)($_POST['id_user'] ?? 0);
    $passHash = password_hash('123', PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE users SET password = ? WHERE id_user = ?")->execute([$passHash, $id_user]);
    header("Location: index.php?msg=Kata sandi akun berhasil direset menjadi: 123");
    exit;
}

// 4. HAPUS AKUN
if ($aksi === 'hapus_user') {
    $id_user = (int)($_POST['id_user'] ?? 0);
    if ($id_user === (int)$_SESSION['user']['id_user']) {
        header("Location: index.php?err=Anda tidak dapat menghapus akun yang sedang aktif digunakan.");
        exit;
    }
    $pdo->prepare("DELETE FROM users WHERE id_user = ?")->execute([$id_user]);
    header("Location: index.php?msg=Akun pengguna berhasil dihapus dari sistem.");
    exit;
}
?>