<?php
require_once '../../config/database.php';
cekAkses(['konsumen', 'primer']);

$user = $_SESSION['user'];
$role = $user['role'];

$id_divisi = $_GET['id_divisi'] ?? 'all';
$tgl_mulai = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['tgl_mulai'] ?? '') ? $_GET['tgl_mulai'] : date('Y-m-01');
$tgl_akhir = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['tgl_akhir'] ?? '') ? $_GET['tgl_akhir'] : date('Y-m-d');
$tujuan    = $_GET['tujuan'] ?? 'Integrasi Sistem Perbankan';

if ($role === 'primer') {
    $id_primer_lock = (int)($user['id_primer'] ?? 0);
    $where_scope    = " AND kp.id_primer = $id_primer_lock";
    $periode_str    = "$tgl_mulai s/d $tgl_akhir (Primer ID-$id_primer_lock)";
} else {
    $div_id      = ($id_divisi !== 'all') ? (int)$id_divisi : 0;
    $where_scope = ($div_id > 0) ? " AND d.id_divisi = $div_id" : "";
    $periode_str = "$tgl_mulai s/d $tgl_akhir (Divisi: " . ($div_id > 0 ? "ID-$div_id" : 'Nasional') . ")";
}

$kode_audit = 'AUD-CSV-' . date('YmdHis');
$pdo->prepare("INSERT INTO audit_trail_laporan (kode_audit, id_user, nama_user, role_user, jenis_laporan, format_file, filter_periode, ip_address) VALUES (?, ?, ?, ?, ?, 'CSV (.CSV)', ?, ?)")
    ->execute([$kode_audit, (int)$user['id_user'], $user['nama_lengkap'], $role, "Dataset Raw ($tujuan)", $periode_str, $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1']);

$stmt = $pdo->prepare("SELECT o.no_invoice, o.tanggal_order, u.nama_lengkap AS buyer, pr.kode_produk, pr.nama_produk, pr.kategori, d.nama_divisi, kp.nama_koperasi, oi.qty, pr.satuan, oi.harga_satuan, oi.subtotal, o.metode_bayar, o.status_order
                       FROM order_items oi
                       JOIN orders o ON oi.id_order = o.id_order
                       JOIN users u ON o.id_buyer = u.id_user
                       JOIN produk_etalase pr ON oi.id_produk = pr.id_produk
                       JOIN koperasi_primer kp ON pr.id_primer = kp.id_primer
                       JOIN divisi_wilayah d ON pr.id_divisi = d.id_divisi
                       WHERE DATE(o.tanggal_order) BETWEEN :tgl_mulai AND :tgl_akhir
                       $where_scope
                       ORDER BY o.id_order DESC");
$stmt->execute(['tgl_mulai' => $tgl_mulai, 'tgl_akhir' => $tgl_akhir]);
$rows = $stmt->fetchAll();

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