<?php
require_once '../../config/database.php';
cekAkses(['konsumen', 'primer']);

$user = $_SESSION['user'] ?? ['id_user' => 1, 'nama_lengkap' => 'Admin Pusat KOPNOVA', 'role' => 'konsumen'];
$role = $user['role'] ?? 'konsumen';

// Pastikan tabel audit tersedia
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
$tujuan    = $_GET['tujuan'] ?? 'Kementerian Koperasi & UKM';

// Catat ke Audit Trail
$kode_audit  = 'AUD-XLS-' . date('YmdHis');
$periode_str = "$tgl_mulai s/d $tgl_akhir (Divisi: " . ($id_divisi === 'all' ? 'Nasional' : "ID-$id_divisi") . ")";
$pdo->prepare("INSERT INTO audit_trail_laporan (kode_audit, id_user, nama_user, role_user, jenis_laporan, format_file, filter_periode, ip_address) VALUES (?, ?, ?, ?, ?, 'EXCEL (.XLS)', ?, ?)")
    ->execute([$kode_audit, (int)$user['id_user'], $user['nama_lengkap'], $role, "Laporan Eksekutif ($tujuan)", $periode_str, $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1']);

// Query 1: Volume & Nilai Serapan Petani per Kabupaten & Koperasi Primer
$where_div = ($id_divisi !== 'all') ? " AND d.id_divisi = " . (int)$id_divisi : "";
$serapan_wilayah = $pdo->query("SELECT d.kode_divisi, d.nama_divisi, d.provinsi, kp.nama_koperasi, kp.kecamatan,
                                COALESCE(SUM(ps.jumlah), 0) AS total_volume,
                                COALESCE(SUM(ps.total_nilai), 0) AS total_nilai_serapan,
                                COUNT(ps.id_pasokan) AS frekuensi_setor
                                FROM koperasi_primer kp
                                JOIN divisi_wilayah d ON kp.id_divisi = d.id_divisi
                                LEFT JOIN pasokan_petani ps ON kp.id_primer = ps.id_primer AND ps.status_pasokan = 'Diterima Primer'
                                WHERE 1=1 $where_div
                                GROUP BY kp.id_primer
                                ORDER BY d.nama_divisi ASC, kp.nama_koperasi ASC")->fetchAll();

// Query 2: Omzet & Kinerja Penjualan per Komoditas
$omzet_komoditas = $pdo->query("SELECT pr.kode_produk, pr.nama_produk, pr.kategori, pr.satuan, pr.harga_jual, pr.stok,
                                COALESCE(SUM(oi.qty), 0) AS qty_terjual,
                                COALESCE(SUM(oi.subtotal), 0) AS total_omzet
                                FROM produk_etalase pr
                                JOIN divisi_wilayah d ON pr.id_divisi = d.id_divisi
                                LEFT JOIN order_items oi ON pr.id_produk = oi.id_produk
                                WHERE 1=1 $where_div
                                GROUP BY pr.id_produk
                                ORDER BY total_omzet DESC")->fetchAll();

// Query 3: Rincian Transaksi Invoice Masuk
$transaksi_list = $pdo->query("SELECT o.no_invoice, o.tanggal_order, u.nama_lengkap AS nama_buyer, o.metode_bayar, o.status_order, o.total_bayar
                               FROM orders o
                               JOIN users u ON o.id_buyer = u.id_user
                               ORDER BY o.id_order DESC")->fetchAll();

$filename = "Laporan_Eksekutif_KOPNOVA_" . date('Ymd_His') . ".xls";
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<style>
    body { font-family: 'Calibri', 'Segoe UI', Arial, sans-serif; font-size: 11pt; color: #0F172A; }
    .title { font-size: 16pt; font-weight: bold; color: #064E3B; }
    .subtitle { font-size: 11pt; font-weight: bold; color: #059669; }
    .meta { font-size: 10pt; color: #334155; }
    .sec-head { font-size: 12pt; font-weight: bold; background-color: #E2E8F0; color: #0F172A; padding: 6px; }
    th { background-color: #064E3B; color: #FFFFFF; font-weight: bold; border: 1px solid #064E3B; padding: 8px; text-align: center; }
    td { border: 1px solid #CBD5E1; padding: 6px 8px; }
    .num { mso-number-format: "\#\,\#\#0"; text-align: right; }
    .curr { mso-number-format: "\0022Rp\0022\ \#\,\#\#0"; text-align: right; }
    .pct { mso-number-format: "0.00%"; text-align: right; }
    .total-row td { background-color: #DCFCE7; font-weight: bold; color: #064E3B; border-top: 2px solid #064E3B; border-bottom: 2px solid #064E3B; }
</style>
</head>
<body>

<table>
    <tr><td colspan="7" class="title" style="border:none;">KOPNOVA — KOPERASI KONSUMEN RANTAI PASOK AGRIBISNIS</td></tr>
    <tr><td colspan="7" class="subtitle" style="border:none;">LAPORAN EKSEKUTIF KONSOLIDASI SERAPAN HULU &amp; OMZET HILIR</td></tr>
    <tr><td colspan="7" class="meta" style="border:none;">Peruntukan Resmi: <b><?= htmlspecialchars($tujuan) ?></b> | Periode: <b><?= htmlspecialchars($tgl_mulai) ?> s/d <?= htmlspecialchars($tgl_akhir) ?></b></td></tr>
    <tr><td colspan="7" class="meta" style="border:none;">Kode Audit Dokumen: <b><?= $kode_audit ?></b> | Dicetak Oleh: <?= htmlspecialchars($user['nama_lengkap']) ?> (<?= date('d/m/Y H:i') ?> WIB)</td></tr>
    <tr><td colspan="7" style="border:none;"></td></tr>

    <!-- BAGIAN I: SERAPAN PETANI PER KABUPATEN & KOPERASI PRIMER -->
    <tr><td colspan="7" class="sec-head">I. REKAPITULASI SERAPAN HASIL PANEN PETANI PER KABUPATEN &amp; KOPERASI PRIMER</td></tr>
    <tr>
        <th>No</th>
        <th>Divisi Kabupaten / Kota</th>
        <th>Unit Koperasi Primer</th>
        <th>Kecamatan</th>
        <th>Frekuensi Setor</th>
        <th>Volume Terserap (Kg/Unit)</th>
        <th>Nilai Serapan Petani (Rp)</th>
    </tr>
    <?php
    $row_start_1 = 8;
    $r1 = $row_start_1;
    $no = 1;
    if (empty($serapan_wilayah)) {
        echo '<tr><td colspan="7" style="text-align:center;">Belum ada data wilayah primer.</td></tr>';
        $r1++;
    } else {
        foreach ($serapan_wilayah as $sw):
    ?>
    <tr>
        <td style="text-align:center;"><?= $no++ ?></td>
        <td><?= htmlspecialchars($sw['nama_divisi']) ?></td>
        <td><?= htmlspecialchars($sw['nama_koperasi']) ?></td>
        <td><?= htmlspecialchars($sw['kecamatan']) ?></td>
        <td class="num"><?= (int)$sw['frekuensi_setor'] ?></td>
        <td class="num"><?= (int)$sw['total_volume'] ?></td>
        <td class="curr"><?= (float)$sw['total_nilai_serapan'] ?></td>
    </tr>
    <?php $r1++; endforeach; } ?>
    <?php $row_end_1 = max($row_start_1, $r1 - 1); ?>
    <tr class="total-row">
        <td colspan="4" style="text-align:right;">TOTAL AKUMULASI SERAPAN HULU</td>
        <td class="num" x:fmla="=SUM(E<?= $row_start_1 ?>:E<?= $row_end_1 ?>)">0</td>
        <td class="num" x:fmla="=SUM(F<?= $row_start_1 ?>:F<?= $row_end_1 ?>)">0</td>
        <td class="curr" x:fmla="=SUM(G<?= $row_start_1 ?>:G<?= $row_end_1 ?>)">0</td>
    </tr>
    <tr><td colspan="7" style="border:none;"></td></tr>

    <!-- BAGIAN II: OMZET & SHARE KONTRIBUSI PER KOMODITAS -->
    <?php
    $row_sec2_title = $r1 + 2;
    $row_sec2_head  = $row_sec2_title + 1;
    $row_start_2    = $row_sec2_head + 1;
    $jml_baris_2    = max(1, count($omzet_komoditas));
    $total_row_2    = $row_start_2 + $jml_baris_2;
    ?>
    <tr><td colspan="7" class="sec-head">II. ANALISIS KINERJA OMZET &amp; KONTRIBUSI PER KOMODITAS</td></tr>
    <tr>
        <th>No</th>
        <th>Kode &amp; Nama Komoditas</th>
        <th>Kategori Klaster</th>
        <th>Harga Jual Satuan (Rp)</th>
        <th>Volume Terjual</th>
        <th>Total Omzet Penjualan (Rp)</th>
        <th>Share Kontribusi (%)</th>
    </tr>
    <?php
    $r2 = $row_start_2;
    $no2 = 1;
    if (empty($omzet_komoditas)) {
        echo '<tr><td colspan="7" style="text-align:center;">Belum ada data produk komoditas.</td></tr>';
        $r2++;
    } else {
        foreach ($omzet_komoditas as $ok):
    ?>
    <tr>
        <td style="text-align:center;"><?= $no2++ ?></td>
        <td><?= htmlspecialchars($ok['kode_produk'] . ' - ' . $ok['nama_produk']) ?></td>
        <td><?= htmlspecialchars($ok['kategori']) ?></td>
        <td class="curr"><?= (float)$ok['harga_jual'] ?></td>
        <td class="num"><?= (int)$ok['qty_terjual'] ?></td>
        <td class="curr"><?= (float)$ok['total_omzet'] ?></td>
        <td class="pct" x:fmla="=IF($F$<?= $total_row_2 ?>>0, F<?= $r2 ?>/$F$<?= $total_row_2 ?>, 0)">0</td>
    </tr>
    <?php $r2++; endforeach; } ?>
    <?php $row_end_2 = max($row_start_2, $r2 - 1); ?>
    <tr class="total-row">
        <td colspan="3" style="text-align:right;">TOTAL &amp; RATA-RATA HARGA KOMODITAS</td>
        <td class="curr" x:fmla="=AVERAGE(D<?= $row_start_2 ?>:D<?= $row_end_2 ?>)">0</td>
        <td class="num" x:fmla="=SUM(E<?= $row_start_2 ?>:E<?= $row_end_2 ?>)">0</td>
        <td class="curr" x:fmla="=SUM(F<?= $row_start_2 ?>:F<?= $row_end_2 ?>)">0</td>
        <td class="pct" x:fmla="=SUM(G<?= $row_start_2 ?>:G<?= $row_end_2 ?>)">1</td>
    </tr>
    <tr><td colspan="7" style="border:none;"></td></tr>

    <!-- BAGIAN III: BUKU BESAR TRANSAKSI INVOICE BUYER -->
    <?php
    $row_sec3_title = $total_row_2 + 2;
    $row_sec3_head  = $row_sec3_title + 1;
    $row_start_3    = $row_sec3_head + 1;
    ?>
    <tr><td colspan="7" class="sec-head">III. REGISTER INVOICE PENJUALAN &amp; ARUS KAS MASUK BUYER</td></tr>
    <tr>
        <th>No</th>
        <th>Nomor Invoice</th>
        <th>Tanggal Transaksi</th>
        <th>Mitra Buyer</th>
        <th>Metode Pembayaran</th>
        <th>Status Pesanan</th>
        <th>Nominal Invoice (Rp)</th>
    </tr>
    <?php
    $r3 = $row_start_3;
    $no3 = 1;
    if (empty($transaksi_list)) {
        echo '<tr><td colspan="7" style="text-align:center;">Belum ada transaksi masuk.</td></tr>';
        $r3++;
    } else {
        foreach ($transaksi_list as $tl):
    ?>
    <tr>
        <td style="text-align:center;"><?= $no3++ ?></td>
        <td><?= htmlspecialchars($tl['no_invoice']) ?></td>
        <td><?= date('d/m/Y H:i', strtotime($tl['tanggal_order'])) ?></td>
        <td><?= htmlspecialchars($tl['nama_buyer']) ?></td>
        <td><?= htmlspecialchars($tl['metode_bayar']) ?></td>
        <td><?= htmlspecialchars($tl['status_order']) ?></td>
        <td class="curr"><?= (float)$tl['total_bayar'] ?></td>
    </tr>
    <?php $r3++; endforeach; } ?>
    <?php $row_end_3 = max($row_start_3, $r3 - 1); ?>
    <tr class="total-row">
        <td colspan="6" style="text-align:right;">GRAND TOTAL PENERIMAAN KAS PENJUALAN</td>
        <td class="curr" x:fmla="=SUM(G<?= $row_start_3 ?>:G<?= $row_end_3 ?>)">0</td>
    </tr>
</table>

</body>
</html>