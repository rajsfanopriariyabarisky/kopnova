<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = 'localhost';
$dbname = 'db_kopnova';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Koneksi Database KOPNOVA Gagal: " . $e->getMessage());
}

define('BASE_URL', 'http://localhost/kopnova');

function rupiah($angka) {
    return "Rp " . number_format((float)$angka, 0, ',', '.');
}

// RBAC MIDDLEWARE: Memastikan user sudah login dan memiliki role yang diizinkan
function cekAkses(array $allowed_roles) {
    if (!isset($_SESSION['user'])) {
        header("Location: " . BASE_URL . "/login.php?error=Silakan masuk terlebih dahulu.");
        exit;
    }
    if (!in_array($_SESSION['user']['role'], $allowed_roles)) {
        header("Location: " . BASE_URL . "/modules/dashboard/index.php?error=Akses ditolak! Fitur ini bukan untuk role Anda.");
        exit;
    }
}

// Helper Upload Foto Aman
function uploadFoto($fileInput, $targetFolder) {
    if (!isset($_FILES[$fileInput]) || $_FILES[$fileInput]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $ext = strtolower(pathinfo($_FILES[$fileInput]['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($ext, $allowed)) {
        return null;
    }
    $dir = __DIR__ . "/../uploads/" . $targetFolder . "/";
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    $newName = uniqid('KOPNOVA_') . '.' . $ext;
    if (move_uploaded_file($_FILES[$fileInput]['tmp_name'], $dir . $newName)) {
        return $newName;
    }
    return null;
}
?>