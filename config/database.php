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
    error_log("Koneksi Database KOPNOVA Gagal: " . $e->getMessage());
    die("Sistem sedang mengalami gangguan koneksi basis data. Silakan coba beberapa saat lagi.");
}

// Dynamic BASE_URL (otomatis mendeteksi localhost maupun domain hosting)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$hostName = $_SERVER['HTTP_HOST'] ?? 'localhost';
$projectRoot = str_replace('\\', '/', realpath(__DIR__ . '/..'));
$docRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
$subFolder = ($docRoot && strpos($projectRoot, $docRoot) === 0) ? substr($projectRoot, strlen($docRoot)) : '/kopnova';
define('BASE_URL', rtrim($protocol . $hostName . $subFolder, '/'));

function rupiah($angka) {
    return "Rp " . number_format((float)$angka, 0, ',', '.');
}

// ==========================================
// CSRF PROTECTION HELPERS
// ==========================================
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function verify_csrf() {
    $token = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        die("Permintaan ditolak (Invalid CSRF Token). Silakan muat ulang halaman dan coba lagi.");
    }
}

// ==========================================
// RBAC MIDDLEWARE
// ==========================================
function cekAkses(array $allowed_roles) {
    if (!isset($_SESSION['user'])) {
        header("Location: " . BASE_URL . "/login.php?error=Silakan masuk terlebih dahulu.");
        exit;
    }
    if (!in_array($_SESSION['user']['role'], $allowed_roles, true)) {
        header("Location: " . BASE_URL . "/modules/dashboard/index.php?error=Akses ditolak! Fitur ini bukan untuk role Anda.");
        exit;
    }
}

// ==========================================
// HELPER UPLOAD FOTO AMAN (MIME + SIZE CHECK)
// ==========================================
function uploadFoto($fileInput, $targetFolder) {
    if (!isset($_FILES[$fileInput]) || $_FILES[$fileInput]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    // Batasi ukuran maksimal 2MB
    if ($_FILES[$fileInput]['size'] > 2 * 1024 * 1024) {
        return null;
    }

    $ext = strtolower(pathinfo($_FILES[$fileInput]['name'], PATHINFO_EXTENSION));
    $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($ext, $allowedExt, true)) {
        return null;
    }

    // Cek MIME type asli dari isi biner file, bukan cuma namanya
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $_FILES[$fileInput]['tmp_name']);
    finfo_close($finfo);

    $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mime, $allowedMime, true)) {
        return null;
    }

    $dir = __DIR__ . "/../uploads/" . $targetFolder . "/";
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $newName = uniqid('KOPNOVA_') . '.' . $ext;
    if (move_uploaded_file($_FILES[$fileInput]['tmp_name'], $dir . $newName)) {
        return $newName;
    }
    return null;
}
?>