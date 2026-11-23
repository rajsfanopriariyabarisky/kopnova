<?php
require_once '../../config/database.php';
cekAkses(['konsumen', 'primer']);

$user = $_SESSION['user'];
$role = $user['role'];

$id_divisi = $_GET['id_divisi'] ?? 'all';
$tgl_mulai = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['tgl_mulai'] ?? '') ? $_GET['tgl_mulai'] : date('Y-m-01');
$tgl_akhir = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['tgl_akhir'] ?? '') ? $_GET['tgl_akhir'] : date('Y-m-d');
$tujuan    = $_GET['tujuan'] ?? 'Kementerian Koperasi & UKM';

if ($role === 'primer') {
    $id_primer_lock = (int)($user['id_primer'] ?? 0);
    $where_scope    = " AND kp.id_primer = $id_primer_lock";
    $where_pr_scope = " AND pr.id_primer = $id_primer_lock";
    $periode_str    = "$tgl_mulai s/d $tgl_akhir (Primer ID-$id_primer_lock)";
} else {
    $div_id         = ($id_divisi !== 'all') ? (int)$id_divisi : 0;
    $where_scope    = ($div_id > 0) ? " AND d.id_divisi = $div_id" : "";
    $where_pr_scope = $where_scope;
    $periode_str    = "$tgl_mulai s/d $tgl_akhir (Divisi: " . ($div_id > 0 ? "ID-$div_id" : 'Nasional') . ")";
}

$kode_audit = 'AUD-PDF-' . date('YmdHis');
$pdo->prepare("INSERT INTO audit_trail_laporan (kode_audit, id_user, nama_user, role_user, jenis_laporan, format_file, filter_periode, ip_address) VALUES (?, ?, ?, ?, ?, 'PDF PRINT', ?, ?)")
    ->execute([$kode_audit, (int)$user['id_user'], $user['nama_lengkap'], $role, "Laporan Eksekutif ($tujuan)", $periode_str, $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1']);

$stmt1 = $pdo->prepare("SELECT d.nama_divisi, kp.nama_koperasi, kp.kecamatan,
                        COALESCE(SUM(ps.jumlah), 0) AS total_volume,
                        COALESCE(SUM(ps.total_nilai), 0) AS total_nilai_serapan
                        FROM koperasi_primer kp
                        JOIN divisi_wilayah d ON kp.id_divisi = d.id_divisi
                        LEFT JOIN pasokan_petani ps ON kp.id_primer = ps.id_primer 
                             AND ps.status_pasokan = 'Diterima Primer'
                             AND DATE(ps.tanggal_setor) BETWEEN :tgl_mulai AND :tgl_akhir
                        WHERE 1=1 $where_scope
                        GROUP BY kp.id_primer
                        ORDER BY d.nama_divisi ASC");
$stmt1->execute(['tgl_mulai' => $tgl_mulai, 'tgl_akhir' => $tgl_akhir]);
$serapan_wilayah = $stmt1->fetchAll();

$stmt2 = $pdo->prepare("SELECT pr.kode_produk, pr.nama_produk, pr.kategori, pr.satuan,
                        COALESCE(SUM(oi_filtered.qty), 0) AS qty_terjual,
                        COALESCE(SUM(oi_filtered.subtotal), 0) AS total_omzet
                        FROM produk_etalase pr
                        JOIN divisi_wilayah d ON pr.id_divisi = d.id_divisi
                        LEFT JOIN (
                            SELECT oi.id_produk, oi.qty, oi.subtotal
                            FROM order_items oi
                            JOIN orders o ON oi.id_order = o.id_order
                            WHERE DATE(o.tanggal_order) BETWEEN :tgl_mulai AND :tgl_akhir
                        ) oi_filtered ON pr.id_produk = oi_filtered.id_produk
                        WHERE 1=1 $where_pr_scope
                        GROUP BY pr.id_produk
                        ORDER BY total_omzet DESC");
$stmt2->execute(['tgl_mulai' => $tgl_mulai, 'tgl_akhir' => $tgl_akhir]);
$omzet_komoditas = $stmt2->fetchAll();

$grand_serapan = array_sum(array_column($serapan_wilayah, 'total_nilai_serapan'));
$grand_volume  = array_sum(array_column($serapan_wilayah, 'total_volume'));
$grand_omzet   = array_sum(array_column($omzet_komoditas, 'total_omzet'));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Eksekutif KOPNOVA - <?= $kode_audit ?></title>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: #FFFFFF !important; padding: 0 !important; }
            .report-page { box-shadow: none !important; border: none !important; margin: 0 !important; max-width: 100% !important; }
        }
    </style>
</head>
<body style="margin:0; padding:24px; font-family:'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background:#E2E8F0; color:#0F172A;">

    <div class="no-print" style="max-width:860px; margin:0 auto 16px; display:flex; justify-content:space-between; align-items:center; background:#064E3B; color:#FFFFFF; padding:12px 20px; border-radius:12px;">
        <div style="font-size:13px; font-weight:700;">Pratinjau Laporan Eksekutif Standar Korporat / Kementerian</div>
        <div style="display:flex; gap:10px;">
            <button onclick="window.print()" style="background:#F59E0B; color:#0F172A; border:none; padding:8px 18px; border-radius:8px; font-weight:800; font-size:13px; cursor:pointer;">
                🖨 Cetak / Simpan PDF
            </button>
            <button onclick="window.close()" style="background:rgba(255,255,255,0.18); color:#FFFFFF; border:none; padding:8px 14px; border-radius:8px; font-weight:700; font-size:13px; cursor:pointer;">
                Tutup
            </button>
        </div>
    </div>

    <div class="report-page" style="max-width:860px; margin:0 auto; background:#FFFFFF; padding:42px 48px; border-radius:14px; box-shadow:0 12px 30px rgba(0,0,0,0.12); box-sizing:border-box;">
        
        <!-- KOP SURAT KORPORAT -->
        <div style="display:flex; justify-content:space-between; align-items:flex-start; border-bottom:3px solid #064E3B; padding-bottom:16px; margin-bottom:20px;">
            <div style="display:flex; align-items:center; gap:14px;">
                <div style="width:48px; height:48px; background:#064E3B; color:#FFFFFF; border-radius:12px; display:flex; align-items:center; justify-content:center; font-weight:900; font-size:24px;">K</div>
                <div>
                    <div style="font-size:22px; font-weight:900; color:#064E3B;">KOPNOVA INDONESIA</div>
                    <div style="font-size:12px; font-weight:700; color:#059669;">KOPERASI KONSUMEN RANTAI PASOK AGRIBISNIS NASIONAL</div>
                    <div style="font-size:11px; color:#64748B;">Laporan Kinerja Konsolidasi Hulu–Hilir &amp; Audit Distribusi Komoditas</div>
                </div>
            </div>
            <div style="text-align:right;">
                <div style="font-size:14px; font-weight:900; color:#0F172A;">EXECUTIVE REPORT</div>
                <div style="font-size:11.5px; font-weight:800; color:#059669; font-family:monospace;"><?= $kode_audit ?></div>
                <div style="font-size:11px; color:#64748B;"><?= date('d F Y') ?></div>
            </div>
        </div>

        <!-- METADATA DOKUMEN -->
        <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:10px; padding:14px 18px; margin-bottom:22px; font-size:12.5px; display:grid; grid-template-columns:1fr 1fr; gap:10px;">
            <div>
                <div>Peruntukan Resmi: <strong><?= htmlspecialchars($tujuan) ?></strong></div>
                <div style="margin-top:3px;">Periode Pelaporan: <strong><?= htmlspecialchars($tgl_mulai) ?> s/d <?= htmlspecialchars($tgl_akhir) ?></strong></div>
            </div>
            <div style="text-align:right;">
                <div>Otoritas Penerbit: <strong><?= htmlspecialchars($user['nama_lengkap']) ?></strong></div>
                <div style="margin-top:3px;">Status Validasi: <strong style="color:#059669;">TERVERIFIKASI SISTEM</strong></div>
            </div>
        </div>

        <!-- RINGKASAN KPI UTAMA -->
        <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:14px; margin-bottom:24px;">
            <div style="border:1px solid #CBD5E1; border-top:4px solid #064E3B; padding:12px 14px; border-radius:8px;">
                <div style="font-size:10.5px; font-weight:800; color:#64748B;">TOTAL OMZET PENJUALAN</div>
                <div style="font-size:17px; font-weight:900; color:#064E3B; margin-top:3px;"><?= rupiah($grand_omzet) ?></div>
            </div>
            <div style="border:1px solid #CBD5E1; border-top:4px solid #059669; padding:12px 14px; border-radius:8px;">
                <div style="font-size:10.5px; font-weight:800; color:#64748B;">NILAI SERAPAN PETANI</div>
                <div style="font-size:17px; font-weight:900; color:#0F172A; margin-top:3px;"><?= rupiah($grand_serapan) ?></div>
            </div>
            <div style="border:1px solid #CBD5E1; border-top:4px solid #D97706; padding:12px 14px; border-radius:8px;">
                <div style="font-size:10.5px; font-weight:800; color:#64748B;">VOLUME KOMODITAS HULU</div>
                <div style="font-size:17px; font-weight:900; color:#0F172A; margin-top:3px;"><?= number_format($grand_volume, 0, ',', '.') ?> Kg/Unit</div>
            </div>
        </div>

        <!-- TABEL 1: SERAPAN WILAYAH -->
        <div style="font-size:13px; font-weight:800; color:#064E3B; margin-bottom:8px;">I. Rekapitulasi Serapan Hasil Panen Petani per Kabupaten &amp; Koperasi Primer</div>
        <table style="width:100%; border-collapse:collapse; font-size:12px; margin-bottom:22px;">
            <thead>
                <tr style="background:#064E3B; color:#FFFFFF; text-align:left;">
                    <th style="padding:9px; border:1px solid #064E3B;">No</th>
                    <th style="padding:9px; border:1px solid #064E3B;">Divisi Kabupaten</th>
                    <th style="padding:9px; border:1px solid #064E3B;">Unit Koperasi Primer</th>
                    <th style="padding:9px; border:1px solid #064E3B; text-align:right;">Volume Serapan</th>
                    <th style="padding:9px; border:1px solid #064E3B; text-align:right;">Nilai Serapan (Rp)</th>
                </tr>
            </thead>
            <tbody>
                <?php $n1 = 1; foreach ($serapan_wilayah as $sw): ?>
                <tr>
                    <td style="padding:8px; border:1px solid #CBD5E1; text-align:center;"><?= $n1++ ?></td>
                    <td style="padding:8px; border:1px solid #CBD5E1;"><?= htmlspecialchars($sw['nama_divisi']) ?></td>
                    <td style="padding:8px; border:1px solid #CBD5E1;"><?= htmlspecialchars($sw['nama_koperasi']) ?> (Kec. <?= htmlspecialchars($sw['kecamatan']) ?>)</td>
                    <td style="padding:8px; border:1px solid #CBD5E1; text-align:right;"><?= number_format($sw['total_volume'], 0, ',', '.') ?></td>
                    <td style="padding:8px; border:1px solid #CBD5E1; text-align:right; font-weight:700;"><?= rupiah($sw['total_nilai_serapan']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background:#F0FDF4; font-weight:800;">
                    <td colspan="3" style="padding:9px; border:1px solid #CBD5E1; text-align:right;">TOTAL SERAPAN HULU</td>
                    <td style="padding:9px; border:1px solid #CBD5E1; text-align:right;"><?= number_format($grand_volume, 0, ',', '.') ?></td>
                    <td style="padding:9px; border:1px solid #CBD5E1; text-align:right; color:#064E3B;"><?= rupiah($grand_serapan) ?></td>
                </tr>
            </tfoot>
        </table>

        <!-- TABEL 2: OMZET KOMODITAS -->
        <div style="font-size:13px; font-weight:800; color:#064E3B; margin-bottom:8px;">II. Rekapitulasi Omzet &amp; Kontribusi Penjualan per Komoditas</div>
        <table style="width:100%; border-collapse:collapse; font-size:12px; margin-bottom:26px;">
            <thead>
                <tr style="background:#064E3B; color:#FFFFFF; text-align:left;">
                    <th style="padding:9px; border:1px solid #064E3B;">No</th>
                    <th style="padding:9px; border:1px solid #064E3B;">Komoditas</th>
                    <th style="padding:9px; border:1px solid #064E3B;">Kategori</th>
                    <th style="padding:9px; border:1px solid #064E3B; text-align:right;">Terjual</th>
                    <th style="padding:9px; border:1px solid #064E3B; text-align:right;">Omzet Penjualan (Rp)</th>
                    <th style="padding:9px; border:1px solid #064E3B; text-align:right;">Share (%)</th>
                </tr>
            </thead>
            <tbody>
                <?php $n2 = 1; foreach ($omzet_komoditas as $ok): 
                    $share = $grand_omzet > 0 ? ($ok['total_omzet'] / $grand_omzet) * 100 : 0;
                ?>
                <tr>
                    <td style="padding:8px; border:1px solid #CBD5E1; text-align:center;"><?= $n2++ ?></td>
                    <td style="padding:8px; border:1px solid #CBD5E1;"><strong><?= htmlspecialchars($ok['nama_produk']) ?></strong></td>
                    <td style="padding:8px; border:1px solid #CBD5E1;"><?= htmlspecialchars($ok['kategori']) ?></td>
                    <td style="padding:8px; border:1px solid #CBD5E1; text-align:right;"><?= number_format($ok['qty_terjual'], 0, ',', '.') ?> <?= htmlspecialchars($ok['satuan']) ?></td>
                    <td style="padding:8px; border:1px solid #CBD5E1; text-align:right; font-weight:700;"><?= rupiah($ok['total_omzet']) ?></td>
                    <td style="padding:8px; border:1px solid #CBD5E1; text-align:right;"><?= number_format($share, 2, ',', '.') ?>%</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background:#F0FDF4; font-weight:800;">
                    <td colspan="4" style="padding:9px; border:1px solid #CBD5E1; text-align:right;">GRAND TOTAL OMZET</td>
                    <td style="padding:9px; border:1px solid #CBD5E1; text-align:right; color:#064E3B;"><?= rupiah($grand_omzet) ?></td>
                    <td style="padding:9px; border:1px solid #CBD5E1; text-align:right;">100,00%</td>
                </tr>
            </tfoot>
        </table>

        <!-- LEMBAR PENGESAHAN KORPORAT -->
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:30px; text-align:center; margin-top:36px; font-size:12px;">
            <div>
                <div style="color:#64748B;">Disusun &amp; Diverifikasi Oleh,</div>
                <div style="font-weight:800; color:#0F172A; margin-top:2px;">Manajer Keuangan &amp; Audit KOPNOVA</div>
                <div style="height:64px;"></div>
                <div style="border-top:1px solid #94A3B8; display:inline-block; padding-top:4px; min-width:190px; font-weight:700;"><?= htmlspecialchars($user['nama_lengkap']) ?></div>
            </div>
            <div>
                <div style="color:#64748B;">Mengetahui &amp; Mengesahkan,</div>
                <div style="font-weight:800; color:#0F172A; margin-top:2px;">Ketua Pengurus Koperasi Konsumen Pusat</div>
                <div style="height:64px;"></div>
                <div style="border-top:1px solid #94A3B8; display:inline-block; padding-top:4px; min-width:190px; font-weight:700;">Pimpinan Pusat KOPNOVA</div>
            </div>
        </div>

    </div>
</body>
</html>