<?php
require_once '../../config/database.php';
cekAkses(['konsumen', 'primer']);

$user = $_SESSION['user'] ?? [];
$role = $user['role'] ?? '';

$id_divisi = $_GET['id_divisi'] ?? 'all';
$tgl_mulai = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['tgl_mulai'] ?? '') ? $_GET['tgl_mulai'] : date('Y-m-01');
$tgl_akhir = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['tgl_akhir'] ?? '') ? $_GET['tgl_akhir'] : date('Y-m-d');

if ($tgl_mulai > $tgl_akhir) {
    [$tgl_mulai, $tgl_akhir] = [$tgl_akhir, $tgl_mulai];
}

$tujuan = trim($_GET['tujuan'] ?? 'Kementerian Koperasi & UKM');
if ($tujuan === '') {
    $tujuan = 'Kementerian Koperasi & UKM';
}

if ($role === 'primer') {
    $id_primer_lock = (int)($user['id_primer'] ?? 0);
    $where_scope    = " AND kp.id_primer = {$id_primer_lock}";
    $where_pr_scope = " AND pr.id_primer = {$id_primer_lock}";
    $periode_str    = "{$tgl_mulai} s/d {$tgl_akhir} (Primer ID-{$id_primer_lock})";
} else {
    $div_id         = ($id_divisi !== 'all') ? (int)$id_divisi : 0;
    $where_scope    = ($div_id > 0) ? " AND d.id_divisi = {$div_id}" : "";
    $where_pr_scope = $where_scope;
    $periode_str    = "{$tgl_mulai} s/d {$tgl_akhir} (Divisi: " . ($div_id > 0 ? "ID-{$div_id}" : 'Nasional') . ")";
}

$kode_audit   = 'AUD-PDF-' . date('YmdHis');
$jenis_audit  = mb_substr("Laporan Eksekutif ({$tujuan})", 0, 100, 'UTF-8');
$periode_safe = mb_substr($periode_str, 0, 80, 'UTF-8');

try {
    $pdo->prepare("
        INSERT INTO audit_trail_laporan
        (kode_audit, id_user, nama_user, role_user, jenis_laporan, format_file, filter_periode, ip_address)
        VALUES (?, ?, ?, ?, ?, 'PDF PRINT', ?, ?)
    ")->execute([
        $kode_audit,
        (int)($user['id_user'] ?? 0),
        $user['nama_lengkap'] ?? 'Pengguna',
        $role,
        $jenis_audit,
        $periode_safe,
        $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
    ]);
} catch (PDOException $e) {
    // Tetap lanjutkan pratinjau cetak
}

$stmt1 = $pdo->prepare("
    SELECT d.nama_divisi, kp.nama_koperasi, kp.kecamatan,
           COALESCE(SUM(ps.jumlah), 0) AS total_volume,
           COALESCE(SUM(ps.total_nilai), 0) AS total_nilai_serapan
    FROM koperasi_primer kp
    JOIN divisi_wilayah d ON kp.id_divisi = d.id_divisi
    LEFT JOIN pasokan_petani ps ON kp.id_primer = ps.id_primer
         AND ps.status_pasokan = 'Diterima Primer'
         AND DATE(ps.tanggal_setor) BETWEEN :tgl_mulai AND :tgl_akhir
    WHERE 1=1 {$where_scope}
    GROUP BY kp.id_primer
    ORDER BY d.nama_divisi ASC, kp.nama_koperasi ASC
");
$stmt1->execute(['tgl_mulai' => $tgl_mulai, 'tgl_akhir' => $tgl_akhir]);
$serapan_wilayah = $stmt1->fetchAll();

$stmt2 = $pdo->prepare("
    SELECT pr.kode_produk, pr.nama_produk, pr.kategori, pr.satuan,
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
    WHERE 1=1 {$where_pr_scope}
    GROUP BY pr.id_produk
    ORDER BY total_omzet DESC
");
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Eksekutif KOPNOVA - <?= htmlspecialchars($kode_audit) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
    <style>
        @page {
            size: A4 portrait;
            margin: 14mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            margin: 0;
            padding: 24px 16px;
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #E2E8F0;
            color: #0F172A;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .toolbar-print {
            max-width: 860px;
            margin: 0 auto 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            background: #042F24;
            color: #FFFFFF;
            padding: 13px 20px;
            border-radius: 14px;
            box-shadow: 0 8px 20px rgba(4, 47, 36, 0.18);
        }
        .btn-tool {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            border: none;
            padding: 9px 16px;
            border-radius: 9px;
            font-family: inherit;
            font-weight: 800;
            font-size: 13px;
            cursor: pointer;
            text-decoration: none;
        }
        .report-page {
            max-width: 860px;
            margin: 0 auto;
            background: #FFFFFF;
            padding: 40px 44px;
            border-radius: 16px;
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.1);
            border: 1px solid #CBD5E1;
        }
        .kop-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            border-bottom: 3px solid #064E3B;
            padding-bottom: 16px;
            margin-bottom: 20px;
        }
        .meta-box {
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 12px;
            padding: 14px 18px;
            margin-bottom: 20px;
            font-size: 12.5px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .kpi-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
            margin-bottom: 24px;
            font-variant-numeric: tabular-nums;
        }
        .doc-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            margin-bottom: 22px;
            font-variant-numeric: tabular-nums;
        }
        .doc-table th {
            background: #064E3B;
            color: #FFFFFF;
            padding: 9px 10px;
            border: 1px solid #064E3B;
            text-align: left;
            font-weight: 800;
        }
        .doc-table td {
            padding: 8px 10px;
            border: 1px solid #CBD5E1;
            vertical-align: middle;
        }
        .ttd-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            text-align: center;
            margin-top: 34px;
            font-size: 12px;
        }
        @media (max-width: 640px) {
            .report-page {
                padding: 22px 18px;
            }
            .kop-header, .meta-box, .kpi-row, .ttd-grid {
                grid-template-columns: 1fr;
                flex-direction: column;
                text-align: left !important;
            }
            .kop-header > div:last-child, .meta-box > div:last-child {
                text-align: left !important;
            }
            .ttd-grid {
                text-align: center !important;
                gap: 24px;
            }
        }
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background: #FFFFFF !important;
                padding: 0 !important;
            }
            .report-page {
                box-shadow: none !important;
                border: none !important;
                border-radius: 0 !important;
                margin: 0 !important;
                padding: 0 !important;
                max-width: 100% !important;
            }
        }
    </style>
</head>
<body>

    <div class="no-print toolbar-print">
        <div style="font-size:13px; font-weight:700;">Pratinjau Laporan Eksekutif Standar Korporat / Kementerian</div>
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <a href="index.php" class="btn-tool" style="background:rgba(255,255,255,0.14); color:#FFFFFF;">
                &larr; Kembali
            </a>
            <button type="button" onclick="window.print()" class="btn-tool" style="background:#F59E0B; color:#042F24;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                <span>Cetak / Simpan PDF</span>
            </button>
            <button type="button" onclick="window.close()" class="btn-tool" style="background:rgba(255,255,255,0.14); color:#FFFFFF;">
                Tutup
            </button>
        </div>
    </div>

    <div class="report-page">

        <!-- KOP SURAT KORPORAT -->
        <div class="kop-header">
            <div style="display:flex; align-items:center; gap:14px;">
                <div style="width:46px; height:46px; background:#064E3B; color:#FFFFFF; border-radius:12px; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:22px; flex-shrink:0;">K</div>
                <div>
                    <div style="font-size:20px; font-weight:800; color:#064E3B; letter-spacing:-0.3px;">KOPNOVA INDONESIA</div>
                    <div style="font-size:11.5px; font-weight:800; color:#059669; letter-spacing:0.3px;">KOPERASI KONSUMEN RANTAI PASOK AGRIBISNIS NASIONAL</div>
                    <div style="font-size:11px; color:#64748B; margin-top:2px;">Laporan Kinerja Konsolidasi Hulu–Hilir &amp; Audit Distribusi Komoditas</div>
                </div>
            </div>
            <div style="text-align:right;">
                <div style="font-size:14px; font-weight:800; color:#0F172A;">EXECUTIVE REPORT</div>
                <div style="font-size:11.5px; font-weight:800; color:#059669; font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; margin-top:2px;">
                    <?= htmlspecialchars($kode_audit) ?>
                </div>
                <div style="font-size:11px; color:#64748B; margin-top:2px;"><?= date('d F Y') ?></div>
            </div>
        </div>

        <!-- METADATA DOKUMEN -->
        <div class="meta-box">
            <div>
                <div>Peruntukan Resmi: <strong><?= htmlspecialchars($tujuan) ?></strong></div>
                <div style="margin-top:4px;">Periode Pelaporan: <strong><?= htmlspecialchars($tgl_mulai) ?> s/d <?= htmlspecialchars($tgl_akhir) ?></strong></div>
            </div>
            <div style="text-align:right;">
                <div>Otoritas Penerbit: <strong><?= htmlspecialchars($user['nama_lengkap'] ?? 'Pengguna') ?></strong></div>
                <div style="margin-top:4px;">Status Validasi: <strong style="color:#059669;">TERVERIFIKASI SISTEM</strong></div>
            </div>
        </div>

        <!-- RINGKASAN KPI UTAMA -->
        <div class="kpi-row">
            <div style="border:1px solid #CBD5E1; border-top:4px solid #064E3B; padding:12px 14px; border-radius:10px;">
                <div style="font-size:10.5px; font-weight:800; color:#64748B;">TOTAL OMZET PENJUALAN</div>
                <div style="font-size:16.5px; font-weight:800; color:#064E3B; margin-top:4px;"><?= rupiah($grand_omzet) ?></div>
            </div>
            <div style="border:1px solid #CBD5E1; border-top:4px solid #059669; padding:12px 14px; border-radius:10px;">
                <div style="font-size:10.5px; font-weight:800; color:#64748B;">NILAI SERAPAN PETANI</div>
                <div style="font-size:16.5px; font-weight:800; color:#0F172A; margin-top:4px;"><?= rupiah($grand_serapan) ?></div>
            </div>
            <div style="border:1px solid #CBD5E1; border-top:4px solid #D97706; padding:12px 14px; border-radius:10px;">
                <div style="font-size:10.5px; font-weight:800; color:#64748B;">VOLUME KOMODITAS HULU</div>
                <div style="font-size:16.5px; font-weight:800; color:#0F172A; margin-top:4px;"><?= number_format($grand_volume, 0, ',', '.') ?> Kg/Unit</div>
            </div>
        </div>

        <!-- TABEL 1: SERAPAN WILAYAH -->
        <div style="font-size:12.5px; font-weight:800; color:#064E3B; margin-bottom:8px; text-transform:uppercase; letter-spacing:0.3px;">
            I. Rekapitulasi Serapan Hasil Panen Petani per Kabupaten &amp; Koperasi Primer
        </div>
        <table class="doc-table">
            <thead>
                <tr>
                    <th style="width:38px; text-align:center;">No</th>
                    <th>Divisi Kabupaten</th>
                    <th>Unit Koperasi Primer</th>
                    <th style="text-align:right;">Volume Serapan</th>
                    <th style="text-align:right;">Nilai Serapan (Rp)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($serapan_wilayah)): ?>
                    <tr>
                        <td colspan="5" style="text-align:center; color:#64748B; padding:16px;">Belum ada data serapan pada cakupan wilayah dan periode ini.</td>
                    </tr>
                <?php else: ?>
                    <?php $n1 = 1; foreach ($serapan_wilayah as $sw): ?>
                    <tr>
                        <td style="text-align:center;"><?= $n1++ ?></td>
                        <td><?= htmlspecialchars($sw['nama_divisi']) ?></td>
                        <td><?= htmlspecialchars($sw['nama_koperasi']) ?> (Kec. <?= htmlspecialchars($sw['kecamatan']) ?>)</td>
                        <td style="text-align:right;"><?= number_format((float)$sw['total_volume'], 0, ',', '.') ?></td>
                        <td style="text-align:right; font-weight:700;"><?= rupiah((float)$sw['total_nilai_serapan']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr style="background:#F0FDF4; font-weight:800;">
                    <td colspan="3" style="text-align:right;">TOTAL SERAPAN HULU</td>
                    <td style="text-align:right;"><?= number_format($grand_volume, 0, ',', '.') ?></td>
                    <td style="text-align:right; color:#064E3B;"><?= rupiah($grand_serapan) ?></td>
                </tr>
            </tfoot>
        </table>

        <!-- TABEL 2: OMZET KOMODITAS -->
        <div style="font-size:12.5px; font-weight:800; color:#064E3B; margin-bottom:8px; text-transform:uppercase; letter-spacing:0.3px;">
            II. Rekapitulasi Omzet &amp; Kontribusi Penjualan per Komoditas
        </div>
        <table class="doc-table" style="margin-bottom:26px;">
            <thead>
                <tr>
                    <th style="width:38px; text-align:center;">No</th>
                    <th>Komoditas</th>
                    <th>Kategori</th>
                    <th style="text-align:right;">Terjual</th>
                    <th style="text-align:right;">Omzet Penjualan (Rp)</th>
                    <th style="text-align:right;">Share (%)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($omzet_komoditas)): ?>
                    <tr>
                        <td colspan="6" style="text-align:center; color:#64748B; padding:16px;">Belum ada data penjualan komoditas pada periode ini.</td>
                    </tr>
                <?php else: ?>
                    <?php $n2 = 1; foreach ($omzet_komoditas as $ok):
                        $share = $grand_omzet > 0 ? (((float)$ok['total_omzet'] / $grand_omzet) * 100) : 0;
                    ?>
                    <tr>
                        <td style="text-align:center;"><?= $n2++ ?></td>
                        <td><strong><?= htmlspecialchars($ok['nama_produk']) ?></strong></td>
                        <td><?= htmlspecialchars($ok['kategori']) ?></td>
                        <td style="text-align:right;"><?= number_format((float)$ok['qty_terjual'], 0, ',', '.') ?> <?= htmlspecialchars($ok['satuan']) ?></td>
                        <td style="text-align:right; font-weight:700;"><?= rupiah((float)$ok['total_omzet']) ?></td>
                        <td style="text-align:right;"><?= number_format($share, 2, ',', '.') ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr style="background:#F0FDF4; font-weight:800;">
                    <td colspan="4" style="text-align:right;">GRAND TOTAL OMZET</td>
                    <td style="text-align:right; color:#064E3B;"><?= rupiah($grand_omzet) ?></td>
                    <td style="text-align:right;">100,00%</td>
                </tr>
            </tfoot>
        </table>

        <!-- LEMBAR PENGESAHAN KORPORAT -->
        <div class="ttd-grid">
            <div>
                <div style="color:#64748B;">Disusun &amp; Diverifikasi Oleh,</div>
                <div style="font-weight:800; color:#0F172A; margin-top:2px;">
                    <?= $role === 'primer' ? 'Pengurus Koperasi Primer' : 'Manajer Keuangan &amp; Audit KOPNOVA' ?>
                </div>
                <div style="height:60px;"></div>
                <div style="border-top:1px solid #94A3B8; display:inline-block; padding-top:4px; min-width:190px; font-weight:700;">
                    <?= htmlspecialchars($user['nama_lengkap'] ?? 'Pengguna') ?>
                </div>
            </div>
            <div>
                <div style="color:#64748B;">Mengetahui &amp; Mengesahkan,</div>
                <div style="font-weight:800; color:#0F172A; margin-top:2px;">Ketua Pengurus Koperasi Konsumen Pusat</div>
                <div style="height:60px;"></div>
                <div style="border-top:1px solid #94A3B8; display:inline-block; padding-top:4px; min-width:190px; font-weight:700;">Pimpinan Pusat KOPNOVA</div>
            </div>
        </div>

        <div style="margin-top:24px; padding-top:12px; border-top:1px dashed #CBD5E1; display:flex; justify-content:space-between; flex-wrap:wrap; gap:8px; font-size:10.5px; color:#64748B;">
            <span>Dicetak melalui Sistem KOPNOVA Executive Reporting pada <?= date('d M Y, H:i') ?> WIB</span>
            <span>Kode Audit: <?= htmlspecialchars($kode_audit) ?></span>
        </div>

    </div>
</body>
</html>