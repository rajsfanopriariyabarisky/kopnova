<?php
require_once '../../config/database.php';
cekAkses(['konsumen', 'primer']);

$user = $_SESSION['user'] ?? ['id_user' => 1, 'nama_lengkap' => 'Admin Pusat KOPNOVA', 'role' => 'konsumen'];
$role = $user['role'] ?? 'konsumen';

$pdo->exec("CREATE TABLE IF NOT EXISTS `audit_trail_laporan` (
  `id_audit` int(11) NOT NULL AUTO_INCREMENT,
  `kode_audit` varchar(35) NOT NULL,
  `id_user` int(11) NOT NULL,
  `nama_user` varchar(100) NOT NULL,
  `role_user` varchar(30) NOT NULL,
  `jenis_laporan` varchar(100) NOT NULL,
  `format_file` enum('EXCEL (.XLS)','CSV (.CSV)','PDF PRINT') NOT NULL,
  `filter_periode` varchar(80) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id_audit`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$id_divisi = $_GET['id_divisi'] ?? 'all';
$tgl_mulai = $_GET['tgl_mulai'] ?? date('Y-m-01');
$tgl_akhir = $_GET['tgl_akhir'] ?? date('Y-m-d');
$tujuan    = $_GET['tujuan'] ?? 'Integrasi Sistem Perbankan';

// Catat ke Audit Trail
$kode_audit  = 'AUD-CSV-' . date('YmdHis');
$periode_str = "$tgl_mulai s/d $tgl_akhir (Divisi: " . ($id_divisi === 'all' ? 'Nasional' : "ID-$id_divisi") . ")";
$pdo->prepare("INSERT INTO audit_trail_laporan (kode_audit, id_user, nama_user, role_user, jenis_laporan, format_file, filter_periode, ip_address) VALUES (?, ?, ?, ?, ?, 'CSV (.CSV)', ?, ?)")
    ->execute([$kode_audit, (int)$user['id_user'], $user['nama_lengkap'], $role, "Dataset Raw ($tujuan)", $periode_str, $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1']);

$rows = $pdo->query("SELECT o.no_invoice, o.tanggal_order, u.nama_lengkap AS buyer, pr.kode_produk, pr.nama_produk, pr.kategori, d.nama_divisi, kp.nama_koperasi, oi.qty, pr.satuan, oi.harga_satuan, oi.subtotal, o.metode_bayar, o.status_order
                     FROM order_items oi
                     JOIN orders o ON oi.id_order = o.id_order
                     JOIN users u ON o.id_buyer = u.id_user
                     JOIN produk_etalase pr ON oi.id_produk = pr.id_produk
                     JOIN koperasi_primer kp ON pr.id_primer = kp.id_primer
                     JOIN divisi_wilayah d ON pr.id_divisi = d.id_divisi
                     ORDER BY o.id_order DESC")->fetchAll();

$filename = "Dataset_KOPNOVA_" . date('Ymd_His') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header("Content-Disposition: attachment; filename=\"$filename\"");

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

fputcsv($output, ['NO_INVOICE', 'TANGGAL', 'MITRA_BUYER', 'KODE_PRODUK', 'NAMA_KOMODITAS', 'KATEGORI', 'DIVISI_KABUPATEN', 'KOPERASI_PRIMER', 'QTY', 'SATUAN', 'HARGA_SATUAN', 'SUBTOTAL_RP', 'METODE_BAYAR', 'STATUS_ORDER'], ';');

foreach ($rows as $r) {
    fputcsv($output, [
        $r['no_invoice'],
        $r['tanggal_order'],
        $r['buyer'],
        $r['kode_produk'],
        $r['nama_produk'],
        $r['kategori'],
        $r['nama_divisi'],
        $r['nama_koperasi'],
        $r['qty'],
        $r['satuan'],
        $r['harga_satuan'],
        $r['subtotal'],
        $r['metode_bayar'],
        $r['status_order']
    ], ';');
}
fclose($output);
exit;