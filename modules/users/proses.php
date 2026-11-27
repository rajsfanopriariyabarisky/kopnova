<?php
require_once '../../config/database.php';
cekAkses(['konsumen']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

verify_csrf();
$aksi = $_POST['aksi'] ?? '';

function redirectUsers(string $type, string $text): void {
    header("Location: index.php?" . $type . "=" . urlencode($text));
    exit;
}

function normalizeRoleWilayah(string $role, $rawDivisi, $rawPrimer): array {
    $allowedRoles = ['konsumen', 'primer', 'petani', 'buyer'];
    if (!in_array($role, $allowedRoles, true)) {
        redirectUsers('err', 'Role pengguna tidak valid.');
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

try {
    /* =========================================================================
       1. TERBITKAN AKUN BARU
       ========================================================================= */
    if ($aksi === 'tambah_user') {
        $username = trim($_POST['username'] ?? '');
        $nama     = trim($_POST['nama_lengkap'] ?? '');
        $role     = trim($_POST['role'] ?? '');
        $passRaw  = trim($_POST['password'] ?? '') ?: '123';

        if ($username === '' || $nama === '' || $role === '') {
            redirectUsers('err', 'Username, nama lengkap, dan role wajib diisi.');
        }

        [$id_divisi, $id_primer] = normalizeRoleWilayah($role, $_POST['id_divisi'] ?? null, $_POST['id_primer'] ?? null);

        $cek = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $cek->execute([$username]);
        if ((int)$cek->fetchColumn() > 0) {
            redirectUsers('err', "Username @{$username} sudah terdaftar. Gunakan username lain.");
        }

        $passHash = password_hash($passRaw, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("
            INSERT INTO users (id_divisi, id_primer, nama_lengkap, username, password, role, no_hp, alamat)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
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

        redirectUsers('msg', "Akun @{$username} ({$role}) berhasil diterbitkan dan siap digunakan login.");
    }

    /* =========================================================================
       2. EDIT OTORISASI & WILAYAH AKUN
       ========================================================================= */
    if ($aksi === 'edit_user') {
        $id_user  = (int)($_POST['id_user'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $nama     = trim($_POST['nama_lengkap'] ?? '');
        $role     = trim($_POST['role'] ?? '');

        if ($id_user <= 0 || $username === '' || $nama === '' || $role === '') {
            redirectUsers('err', 'Data pembaruan akun pengguna tidak lengkap.');
        }

        [$id_divisi, $id_primer] = normalizeRoleWilayah($role, $_POST['id_divisi'] ?? null, $_POST['id_primer'] ?? null);

        $cek = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id_user != ?");
        $cek->execute([$username, $id_user]);
        if ((int)$cek->fetchColumn() > 0) {
            redirectUsers('err', "Username @{$username} sudah digunakan oleh akun lain.");
        }

        $stmt = $pdo->prepare("
            UPDATE users
            SET id_divisi = ?, id_primer = ?, nama_lengkap = ?, username = ?, role = ?, no_hp = ?, alamat = ?
            WHERE id_user = ?
        ");
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

        redirectUsers('msg', "Data otorisasi dan penempatan wilayah akun @{$username} berhasil diperbarui.");
    }

    /* =========================================================================
       3. RESET KATA SANDI KE DEFAULT (123)
       ========================================================================= */
    if ($aksi === 'reset_pw') {
        $id_user = (int)($_POST['id_user'] ?? 0);
        if ($id_user <= 0) {
            redirectUsers('err', 'ID akun pengguna tidak valid.');
        }

        $passHash = password_hash('123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id_user = ?");
        $stmt->execute([$passHash, $id_user]);

        redirectUsers('msg', 'Kata sandi akun berhasil direset menjadi: 123');
    }

    /* =========================================================================
       4. HAPUS AKUN PENGGUNA
       ========================================================================= */
    if ($aksi === 'hapus_user') {
        $id_user = (int)($_POST['id_user'] ?? 0);
        $current_id = (int)($_SESSION['user']['id_user'] ?? 0);

        if ($id_user <= 0) {
            redirectUsers('err', 'ID akun pengguna tidak valid.');
        }

        if ($id_user === $current_id) {
            redirectUsers('err', 'Anda tidak dapat menghapus akun yang sedang aktif digunakan.');
        }

        $stmt = $pdo->prepare("DELETE FROM users WHERE id_user = ?");
        $stmt->execute([$id_user]);

        redirectUsers('msg', 'Akun pengguna berhasil dihapus dari sistem.');
    }

    redirectUsers('err', 'Aksi manajemen akun tidak dikenali.');
} catch (PDOException $e) {
    redirectUsers('err', 'Operasi gagal diproses karena akun masih terhubung dengan riwayat transaksi atau pasokan.');
}