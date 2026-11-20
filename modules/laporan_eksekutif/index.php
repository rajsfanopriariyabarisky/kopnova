<?php
require_once '../../config/database.php';
cekAkses(['konsumen', 'primer']);

// AUTO-MIGRATE: Buat tabel log audit trail ekspor laporan otomatis jika belum ada
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

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

$divisi_list = $pdo->query("SELECT * FROM divisi_wilayah ORDER BY nama_divisi ASC")->fetchAll();

// Statistik Ringkas untuk Pratinjau Laporan
$tot_omzet     = $pdo->query("SELECT COALESCE(SUM(total_bayar),0) FROM orders")->fetchColumn();
$tot_serapan   = $pdo->query("SELECT COALESCE(SUM(total_nilai),0) FROM pasokan_petani WHERE status_pasokan = 'Diterima Primer'")->fetchColumn();
$tot_volume    = $pdo->query("SELECT COALESCE(SUM(jumlah),0) FROM pasokan_petani WHERE status_pasokan = 'Diterima Primer'")->fetchColumn();
$tot_transaksi = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();

// Ambil Riwayat Audit Trail Terbaru
$audit_logs = $pdo->query("SELECT * FROM audit_trail_laporan ORDER BY id_audit DESC LIMIT 12")->fetchAll();
?>

<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px; margin-bottom:24px;">
    <div>
        <span style="display:inline-block; background:#E0E7FF; color:#3730A3; font-size:11px; font-weight:800; padding:4px 12px; border-radius:99px; margin-bottom:6px;">
            CORPORATE REPORTING &amp; AUDIT TRAIL • ONE-CLICK EXPORT
        </span>
        <h2 style="margin:0 0 4px; font-size:22px; color:#0F172A;">Ekspor Laporan Eksekutif &amp; Audit Trail</h2>
        <p style="margin:0; font-size:14px; color:#64748B;">
            Unduh laporan terstandar korporat &amp; perbankan (dilengkapi rumus otomatis Excel, CSV mentah, dan dokumen PDF siap cetak).
        </p>
    </div>
</div>

<!-- 4 KPI PRATINJAU DATA LAPORAN -->
<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:16px; margin-bottom:24px;">
    <div style="background:#FFFFFF; padding:18px 20px; border-radius:14px; border:1px solid #E2E8F0; border-left:5px solid #064E3B;">
        <div style="font-size:11.5px; color:#64748B; font-weight:800;">OMZET PENJUALAN HILIR</div>
        <div style="font-size:22px; font-weight:900; color:#064E3B; margin-top:4px;"><?= rupiah($tot_omzet) ?></div>
        <div style="font-size:11px; color:#64748B; margin-top:2px;">Dari <?= $tot_transaksi ?> invoice tervalidasi</div>
    </div>

    <div style="background:#FFFFFF; padding:18px 20px; border-radius:14px; border:1px solid #E2E8F0; border-left:5px solid #059669;">
        <div style="font-size:11.5px; color:#64748B; font-weight:800;">NILAI SERAPAN PETANI HULU</div>
        <div style="font-size:22px; font-weight:900; color:#0F172A; margin-top:4px;"><?= rupiah($tot_serapan) ?></div>
        <div style="font-size:11px; color:#059669; margin-top:2px; font-weight:600;">Status Diterima Koperasi Primer</div>
    </div>

    <div style="background:#FFFFFF; padding:18px 20px; border-radius:14px; border:1px solid #E2E8F0; border-left:5px solid #D97706;">
        <div style="font-size:11.5px; color:#64748B; font-weight:800;">VOLUME KOMODITAS TERSERAP</div>
        <div style="font-size:22px; font-weight:900; color:#0F172A; margin-top:4px;"><?= number_format($tot_volume, 0, ',', '.') ?> Unit/Kg</div>
        <div style="font-size:11px; color:#64748B; margin-top:2px;">Akumulasi lintas kabupaten</div>
    </div>

    <div style="background:#FFFFFF; padding:18px 20px; border-radius:14px; border:1px solid #E2E8F0; border-left:5px solid #2563EB;">
        <div style="font-size:11.5px; color:#64748B; font-weight:800;">JEJAK AUDIT EKSPOR</div>
        <div style="font-size:22px; font-weight:900; color:#0F172A; margin-top:4px;"><?= count($audit_logs) ?> Dokumen</div>
        <div style="font-size:11px; color:#64748B; margin-top:2px;">Tercatat otomatis di sistem</div>
    </div>
</div>

<!-- PANEL GENERATOR ONE-CLICK EXPORT -->
<div style="background:#FFFFFF; border-radius:18px; border:1px solid #E2E8F0; padding:24px 28px; margin-bottom:26px; box-shadow:0 6px 18px rgba(0,0,0,0.03);">
    <div style="margin-bottom:18px; border-bottom:1px solid #E2E8F0; padding-bottom:14px;">
        <h3 style="margin:0 0 4px; font-size:17px; color:#0F172A;">Generator Laporan Eksekutif Terpadu</h3>
        <div style="font-size:12.5px; color:#64748B;">Pilih cakupan wilayah dan rentang periode, lalu klik format ekspor yang dibutuhkan untuk Kementerian, Dinas, atau Perbankan.</div>
    </div>

    <form id="formExport" method="GET" target="_blank">
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(210px, 1fr)); gap:16px; margin-bottom:22px;">
            <div>
                <label style="font-size:12px; font-weight:700; color:#334155;">Cakupan Divisi Wilayah</label>
                <select name="id_divisi" style="width:100%; box-sizing:border-box; padding:10px 12px; border:1px solid #CBD5E1; border-radius:10px; margin-top:5px; font-weight:600; font-size:13.5px;">
                    <option value="all">Seluruh Wilayah Nasional (Konsolidasi)</option>
                    <?php foreach ($divisi_list as $d): ?>
                        <option value="<?= $d['id_divisi'] ?>"><?= htmlspecialchars($d['nama_divisi']) ?> (<?= htmlspecialchars($d['provinsi']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label style="font-size:12px; font-weight:700; color:#334155;">Dari Tanggal</label>
                <input type="date" name="tgl_mulai" value="<?= date('Y-m-01') ?>" style="width:100%; box-sizing:border-box; padding:10px 12px; border:1px solid #CBD5E1; border-radius:10px; margin-top:5px; font-size:13.5px;">
            </div>

            <div>
                <label style="font-size:12px; font-weight:700; color:#334155;">Sampai Tanggal</label>
                <input type="date" name="tgl_akhir" value="<?= date('Y-m-d') ?>" style="width:100%; box-sizing:border-box; padding:10px 12px; border:1px solid #CBD5E1; border-radius:10px; margin-top:5px; font-size:13.5px;">
            </div>

            <div>
                <label style="font-size:12px; font-weight:700; color:#334155;">Peruntukan Dokumen</label>
                <select name="tujuan" style="width:100%; box-sizing:border-box; padding:10px 12px; border:1px solid #CBD5E1; border-radius:10px; margin-top:5px; font-weight:600; font-size:13.5px;">
                    <option value="Kementerian Koperasi & UKM">Kementerian Koperasi &amp; UKM</option>
                    <option value="Dinas Pertanian & Ketahanan Pangan">Dinas Pertanian &amp; Ketahanan Pangan</option>
                    <option value="Mitra Perbankan & Lembaga Pembiayaan">Mitra Perbankan &amp; Pembiayaan</option>
                    <option value="Audit Internal Rapat Anggota Tahunan (RAT)">Audit Internal RAT Koperasi</option>
                </select>
            </div>
        </div>

        <!-- 3 TOMBOL EKSPOR EKSEKUTIF -->
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(250px, 1fr)); gap:14px;">
            <button type="submit" formaction="export_excel.php" style="background:linear-gradient(135deg, #065F46 0%, #059669 100%); color:#FFFFFF; border:none; padding:15px 18px; border-radius:12px; text-align:left; cursor:pointer; box-shadow:0 6px 16px rgba(5,150,105,0.25);">
                <div style="font-size:14px; font-weight:800;">📊 Unduh Laporan Excel (.XLS)</div>
                <div style="font-size:11.5px; color:#D1FAE5; margin-top:3px;">Dilengkapi Rumus Aktif (=SUM, =AVERAGE, % Share) &amp; Kop Resmi</div>
            </button>

            <button type="submit" formaction="laporan_pdf.php" style="background:linear-gradient(135deg, #1E293B 0%, #0F172A 100%); color:#FFFFFF; border:none; padding:15px 18px; border-radius:12px; text-align:left; cursor:pointer; box-shadow:0 6px 16px rgba(15,23,42,0.2);">
                <div style="font-size:14px; font-weight:800;">🖨 Cetak Laporan Eksekutif (PDF / A4)</div>
                <div style="font-size:11.5px; color:#CBD5E1; margin-top:3px;">Format Siap Cetak Standar Korporat &amp; Lembar Pengesahan</div>
            </button>

            <button type="submit" formaction="export_csv.php" style="background:#F8FAFC; color:#0F172A; border:2px solid #CBD5E1; padding:15px 18px; border-radius:12px; text-align:left; cursor:pointer;">
                <div style="font-size:14px; font-weight:800;">📁 Unduh Dataset Mentah (.CSV)</div>
                <div style="font-size:11.5px; color:#64748B; margin-top:3px;">Format UTF-8 Semicolon untuk Integrasi Sistem Bank / ERP</div>
            </button>
        </div>
    </form>
</div>

<!-- TABEL AUDIT TRAIL LOGGING -->
<div style="background:#FFFFFF; border-radius:16px; border:1px solid #E2E8F0; overflow:hidden; box-shadow:0 4px 12px rgba(0,0,0,0.02);">
    <div style="padding:18px 22px; border-bottom:1px solid #E2E8F0; display:flex; justify-content:space-between; align-items:center;">
        <div>
            <h3 style="margin:0; font-size:16.5px; color:#0F172A;">Audit Trail Riwayat Pengunduhan &amp; Pencetakan Dokumen</h3>
            <div style="font-size:12px; color:#64748B;">Setiap ekspor data dicatat otomatis beserta identitas pengunduh, stempel waktu, dan parameter filter.</div>
        </div>
        <button onclick="location.reload()" style="background:#F1F5F9; border:1px solid #CBD5E1; padding:7px 14px; border-radius:8px; font-size:12px; font-weight:700; cursor:pointer;">
            ↻ Segarkan Log
        </button>
    </div>

    <div style="overflow-x:auto;">
        <table style="width:100%; border-collapse:collapse; text-align:left; font-size:13px;">
            <thead>
                <tr style="background:#064E3B; color:#FFFFFF;">
                    <th style="padding:12px 16px;">Kode Audit &amp; Waktu</th>
                    <th style="padding:12px 16px;">Aktor Pengunduh</th>
                    <th style="padding:12px 16px;">Jenis &amp; Peruntukan Laporan</th>
                    <th style="padding:12px 16px;">Periode Filter</th>
                    <th style="padding:12px 16px;">Format</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($audit_logs)): ?>
                    <tr><td colspan="5" style="padding:26px; text-align:center; color:#64748B;">Belum ada riwayat pengunduhan laporan. Klik salah satu tombol ekspor di atas untuk menguji pencatatan Audit Trail.</td></tr>
                <?php endif; ?>
                <?php foreach ($audit_logs as $log): ?>
                <tr style="border-bottom:1px solid #F1F5F9;">
                    <td style="padding:12px 16px;">
                        <div style="font-family:monospace; font-weight:800; color:#059669;"><?= htmlspecialchars($log['kode_audit']) ?></div>
                        <div style="font-size:11px; color:#64748B;"><?= date('d M Y, H:i:s', strtotime($log['created_at'])) ?> WIB</div>
                    </td>
                    <td style="padding:12px 16px;">
                        <div style="font-weight:700; color:#0F172A;"><?= htmlspecialchars($log['nama_user']) ?></div>
                        <div style="font-size:11px; color:#64748B;">Role: <?= strtoupper(htmlspecialchars($log['role_user'])) ?></div>
                    </td>
                    <td style="padding:12px 16px; font-weight:600; color:#334155;">
                        <?= htmlspecialchars($log['jenis_laporan']) ?>
                    </td>
                    <td style="padding:12px 16px; font-size:12px; color:#475569;">
                        <?= htmlspecialchars($log['filter_periode']) ?>
                    </td>
                    <td style="padding:12px 16px;">
                        <span style="background:#DCFCE7; color:#166534; padding:4px 10px; border-radius:99px; font-size:11px; font-weight:800;">
                            <?= htmlspecialchars($log['format_file']) ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>