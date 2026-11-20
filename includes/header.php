<?php
if (!isset($pdo)) {
    require_once __DIR__ . '/../config/database.php';
}
if (!isset($_SESSION['user'])) {
    header("Location: " . BASE_URL . "/login.php");
    exit;
}
$user = $_SESSION['user'];
$role = $user['role'];
$cart_count = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;

$role_labels = [
    'konsumen' => 'Koperasi Konsumen (Pusat)',
    'primer'   => 'Koperasi Primer Kecamatan',
    'petani'   => 'Petani / Pemasok Hulu',
    'buyer'    => 'Buyer / Mitra Pasar'
];
$role_badge = $role_labels[$role] ?? strtoupper($role);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KOPNOVA | <?= htmlspecialchars($role_badge) ?></title>
</head>
<body style="margin:0; padding:0; font-family:'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background:#F1F5F3; color:#0F172A; display:flex; min-height:100vh; overflow-x:hidden;">