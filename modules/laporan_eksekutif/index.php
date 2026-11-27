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
$tot_omzet     = (float)$pdo->query("SELECT COALESCE(SUM(total_bayar),0) FROM orders")->fetchColumn();
$tot_serapan   = (float)$pdo->query("SELECT COALESCE(SUM(total_nilai),0) FROM pasokan_petani WHERE status_pasokan = 'Diterima Primer'")->fetchColumn();
$tot_volume    = (int)$pdo->query("SELECT COALESCE(SUM(jumlah),0) FROM pasokan_petani WHERE status_pasokan = 'Diterima Primer'")->fetchColumn();
$tot_transaksi = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();

// Ambil Riwayat Audit Trail Terbaru
$audit_logs = $pdo->query("SELECT * FROM audit_trail_laporan ORDER BY id_audit DESC LIMIT 15")->fetchAll();
?>

<style>
    /* PERAPIAN SIDEBAR & SISTEM LAYOUT CLEAN MODERN */
    #mainSidebar {
        width: 262px !important;
    }
    #mainSidebar a {
        font-size: 13px !important;
        padding: 10px 13px !important;
        gap: 10px !important;
        white-space: nowrap !important;
    }
    #mainSidebar a svg {
        flex-shrink: 0 !important;
        width: 17px !important;
        height: 17px !important;
    }

    .lap-grid-4 {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 16px;
    }
    .lap-grid-3 {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 14px;
    }
    .lap-card {
        background: #FFFFFF;
        border-radius: 18px;
        border: 1px solid #E2E8F0;
        box-shadow: 0 2px 10px rgba(15, 23, 42, 0.03);
    }
    .lap-kpi {
        background: #FFFFFF;
        border-radius: 16px;
        border: 1px solid #E2E8F0;
        padding: 18px 20px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.025);
        transition: transform 0.2s ease;
    }
    .lap-kpi:hover {
        transform: translateY(-2px);
    }
    .lap-kpi-val {
        font-size: clamp(1.18rem, 1.5vw, 1.48rem);
        font-weight: 900;
        color: #0F172A;
        margin: 8px 0 4px;
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
        letter-spacing: -0.4px;
    }
    .lap-input {
        width: 100%;
        box-sizing: border-box;
        padding: 10px 12px;
        border: 1px solid #CBD5E1;
        border-radius: 11px;
        margin-top: 6px;
        font-size: 13px;
        font-weight: 600;
        color: #0F172A;
        background: #FFFFFF;
        outline: none;
        transition: border-color 0.2s, box-shadow 0.2s;
    }
    .lap-input:focus {
        border-color: #059669;
        box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.12);
    }
    .lap-export-card {
        border-radius: 15px;
        padding: 16px 18px;
        text-align: left;
        cursor: pointer;
        display: flex;
        align-items: flex-start;
        gap: 14px;
        transition: transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease;
        width: 100%;
    }
    .lap-export-card:hover {
        transform: translateY(-2px);
        filter: brightness(1.03);
    }
    .lap-pill {
        padding: 6px 13px;
        border-radius: 99px;
        font-size: 12px;
        font-weight: 700;
        border: 1px solid #E2E8F0;
        background: #F8FAFC;
        color: #475569;
        cursor: pointer;
        transition: all 0.18s ease;
        white-space: nowrap;
    }
    .lap-pill:hover {
        border-color: #10B981;
        color: #065F46;
    }
    .lap-pill.active {
        background: #064E3B;
        color: #FFFFFF;
        border-color: #064E3B;
        box-shadow: 0 3px 10px rgba(6, 78, 59, 0.2);
    }
    .lap-preset-btn {
        padding: 5px 11px;
        border-radius: 8px;
        font-size: 11.5px;
        font-weight: 700;
        border: 1px solid #CBD5E1;
        background: #F8FAFC;
        color: #334155;
        cursor: pointer;
        transition: all 0.15s ease;
        white-space: nowrap;
    }
    .lap-preset-btn:hover,
    .lap-preset-btn.active {
        background: #ECFDF5;
        border-color: #059669;
        color: #065F46;
    }
    .lap-search {
        padding: 8px 13px;
        border: 1px solid #CBD5E1;
        border-radius: 10px;
        font-size: 12.5px;
        min-width: 210px;
        outline: none;
        transition: border-color 0.2s;
    }
    .lap-search:focus {
        border-color: #10B981;
    }
    .lap-row-hover:hover {
        background-color: #F8FAFC;
    }
    .mobile-nav-trigger {
        display: none;
    }

    /* TABLET & LAPTOP SEDANG */
    @media (max-width: 1180px) {
        .lap-grid-4 {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .lap-grid-3 {
            grid-template-columns: minmax(0, 1fr);
        }
    }

    /* MENU DRAWER UNTUK HP (< 960px) */
    @media (max-width: 960px) {
        #mainSidebar {
            position: fixed !important;
            left: -280px;
            top: 0;
            bottom: 0;
            transition: left 0.28s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 12px 0 32px rgba(0, 0, 0, 0.28);
        }
        #mainSidebar.open {
            left: 0 !important;
        }
        .mobile-nav-trigger {
            display: inline-flex !important;
            align-items: center;
            gap: 8px;
            background: #064E3B;
            color: #FFFFFF;
            border: none;
            padding: 10px 15px;
            border-radius: 11px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            margin-bottom: 14px;
            box-shadow: 0 4px 12px rgba(6, 78, 59, 0.2);
        }
    }

    /* TABEL AUDIT TRAIL OTOMATIS JADI KARTU DI HP (< 768px) */
    @media (max-width: 768px) {
        .lap-responsive-table thead {
            display: none;
        }
        .lap-responsive-table,
        .lap-responsive-table tbody,
        .lap-responsive-table tr,
        .lap-responsive-table td {
            display: block;
            width: 100%;
            box-sizing: border-box;
        }
        .lap-responsive-table tr.lap-mobile-card {
            background: #FFFFFF;
            border: 1px solid #E2E8F0 !important;
            border-radius: 14px;
            padding: 14px;
            margin-bottom: 12px;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03);
        }
        .lap-responsive-table td {
            padding: 5px 0 !important;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            border: none !important;
            text-align: right;
        }
        .lap-responsive-table td::before {
            content: attr(data-label);
            font-size: 11.5px;
            font-weight: 700;
            color: #64748B;
            text-align: left;
            flex-shrink: 0;
        }
        .lap-responsive-table td.td-card-top {
            border-bottom: 1px dashed #E2E8F0 !important;
            padding-bottom: 9px !important;
            margin-bottom: 4px;
            justify-content: space-between;
            text-align: left;
        }
        .lap-responsive-table td.td-card-top::before {
            display: none;
        }
    }

    /* LAYAR HP KECIL (< 640px) */
    @media (max-width: 640px) {
        .lap-grid-4 {
            grid-template-columns: minmax(0, 1fr);
        }
        .lap-hero {
            padding: 22px 20px !important;
        }
        .lap-hero-title {
            font-size: 19px !important;
        }
        .lap-search {
            width: 100%;
            min-width: 0;
            box-sizing: border-box;
        }
    }
</style>

<!-- TOMBOL BUKA MENU UNTUK LAYAR HP -->
<div>
    <button type="button" class="mobile-nav-trigger" onclick="toggleKopSidebar()">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        <span>Buka Menu Navigasi</span>
    </button>
</div>
<div id="kopBackdrop" onclick="toggleKopSidebar()" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.45); z-index:45; backdrop-filter:blur(2px);"></div>

<!-- BANNER HEADER CLEAN & RAMAH -->
<div class="lap-hero" style="background:linear-gradient(125deg, #064E3B 0%, #059669 100%); border-radius:20px; padding:26px 30px; color:#FFFFFF; margin-bottom:20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:18px; box-shadow:0 10px 25px rgba(5, 150, 105, 0.16); position:relative; overflow:hidden;">
    <div style="position:absolute; right:-30px; bottom:-50px; width:200px; height:200px; border-radius:50%; background:rgba(255,255,255,0.08); pointer-events:none;"></div>
    <div style="max-width:640px; position:relative; z-index:2;">
        <div style="display:inline-flex; align-items:center; gap:7px; background:rgba(255,255,255,0.16); color:#ECFDF5; font-size:11px; font-weight:800; padding:5px 12px; border-radius:99px; margin-bottom:10px;">
            <span style="width:7px; height:7px; border-radius:50%; background:#FBBF24; display:inline-block;"></span>
            <span>PUSAT LAPORAN &amp; ARSIP DOKUMEN</span>
        </div>
        <h2 class="lap-hero-title" style="margin:0 0 6px; font-size:22px; font-weight:800; line-height:1.3;">
            Cetak &amp; Unduh Laporan Resmi dalam Sekali Klik
        </h2>
        <p style="margin:0; font-size:13.5px; color:#ECFDF5; line-height:1.55; opacity:0.95;">
            Pilih wilayah dan tanggal laporan, lalu unduh dalam format Excel siap hitung, PDF siap cetak untuk pengajuan, atau file data CSV.
        </p>
    </div>
</div>

<!-- TOAST INFO SAAT EKSPOR DIKLIK -->
<div id="exportToast" style="display:none; background:#ECFDF5; border:1px solid #A7F3D0; border-left:5px solid #059669; color:#065F46; padding:13px 18px; border-radius:12px; margin-bottom:18px; font-weight:700; font-size:13.5px; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
    <div style="display:flex; align-items:center; gap:10px;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
        <span id="exportToastText">Dokumen sedang disiapkan di tab baru.</span>
    </div>
    <button type="button" onclick="location.reload()" style="background:#059669; color:#FFFFFF; border:none; padding:6px 12px; border-radius:8px; font-size:12px; font-weight:700; cursor:pointer;">
        Perbarui Daftar Riwayat
    </button>
</div>

<!-- 4 KARTU RINGKASAN ANGKA UTAMA -->
<div class="lap-grid-4" style="margin-bottom:22px;">
    <div class="lap-kpi" style="border-left:5px solid #064E3B;">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
            <span style="font-size:12px; color:#64748B; font-weight:700;">Total Penjualan</span>
            <div style="width:35px; height:35px; border-radius:10px; background:#DCFCE7; color:#059669; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            </div>
        </div>
        <div class="lap-kpi-val" style="color:#064E3B;"><?= rupiah($tot_omzet) ?></div>
        <div style="font-size:11.5px; color:#64748B;">Dari <?= $tot_transaksi ?> transaksi pembeli</div>
    </div>

    <div class="lap-kpi" style="border-left:5px solid #10B981;">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
            <span style="font-size:12px; color:#64748B; font-weight:700;">Dana ke Petani</span>
            <div style="width:35px; height:35px; border-radius:10px; background:#ECFDF5; color:#059669; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/></svg>
            </div>
        </div>
        <div class="lap-kpi-val"><?= rupiah($tot_serapan) ?></div>
        <div style="font-size:11.5px; color:#059669; font-weight:600;">Panen sudah diterima cabang</div>
    </div>

    <div class="lap-kpi" style="border-left:5px solid #F59E0B;">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
            <span style="font-size:12px; color:#64748B; font-weight:700;">Volume Terserap</span>
            <div style="width:35px; height:35px; border-radius:10px; background:#FEF3C7; color:#D97706; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
            </div>
        </div>
        <div class="lap-kpi-val"><?= number_format($tot_volume, 0, ',', '.') ?> Unit</div>
        <div style="font-size:11.5px; color:#64748B;">Gabungan seluruh daerah</div>
    </div>

    <div class="lap-kpi" style="border-left:5px solid #3B82F6;">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
            <span style="font-size:12px; color:#64748B; font-weight:700;">Dokumen Tercatat</span>
            <div style="width:35px; height:35px; border-radius:10px; background:#DBEAFE; color:#2563EB; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            </div>
        </div>
        <div class="lap-kpi-val"><?= count($audit_logs) ?> File</div>
        <div style="font-size:11.5px; color:#64748B;">Tersimpan di jejak audit</div>
    </div>
</div>

<!-- PANEL GENERATOR ONE-CLICK EXPORT -->
<div class="lap-card" style="padding:24px 26px; margin-bottom:24px;">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px; margin-bottom:18px; border-bottom:1px solid #F1F5F9; padding-bottom:14px;">
        <div>
            <h3 style="margin:0 0 4px; font-size:16.5px; font-weight:800; color:#0F172A;">Atur Filter &amp; Unduh Laporan</h3>
            <div style="font-size:12.5px; color:#64748B;">Tentukan daerah, tanggal, dan tujuan laporan, lalu pilih jenis file di bawah.</div>
        </div>

        <!-- PINTASAN RENTANG TANGGAL CEPAT -->
        <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
            <span style="font-size:11.5px; font-weight:700; color:#64748B; margin-right:2px;">Periode Cepat:</span>
            <button type="button" class="lap-preset-btn active" onclick="setDatePreset('month', this)">Bulan Ini</button>
            <button type="button" class="lap-preset-btn" onclick="setDatePreset('7days', this)">7 Hari</button>
            <button type="button" class="lap-preset-btn" onclick="setDatePreset('30days', this)">30 Hari</button>
            <button type="button" class="lap-preset-btn" onclick="setDatePreset('year', this)">Tahun Ini</button>
        </div>
    </div>

    <form id="formExport" method="GET" target="_blank">
        <div class="lap-grid-4" style="margin-bottom:22px;">
            <div>
                <label style="font-size:12px; font-weight:700; color:#334155;">Pilih Wilayah</label>
                <select name="id_divisi" class="lap-input">
                    <option value="all">Semua Wilayah (Nasional)</option>
                    <?php foreach ($divisi_list as $d): ?>
                        <option value="<?= $d['id_divisi'] ?>"><?= htmlspecialchars($d['nama_divisi']) ?> (<?= htmlspecialchars($d['provinsi']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label style="font-size:12px; font-weight:700; color:#334155;">Dari Tanggal</label>
                <input type="date" name="tgl_mulai" id="tgl_mulai" value="<?= date('Y-m-01') ?>" class="lap-input">
            </div>

            <div>
                <label style="font-size:12px; font-weight:700; color:#334155;">Sampai Tanggal</label>
                <input type="date" name="tgl_akhir" id="tgl_akhir" value="<?= date('Y-m-d') ?>" class="lap-input">
            </div>

            <div>
                <label style="font-size:12px; font-weight:700; color:#334155;">Tujuan Laporan</label>
                <select name="tujuan" class="lap-input">
                    <option value="Kementerian Koperasi & UKM">Kementerian Koperasi &amp; UKM</option>
                    <option value="Dinas Pertanian & Ketahanan Pangan">Dinas Pertanian &amp; Ketahanan Pangan</option>
                    <option value="Mitra Perbankan & Lembaga Pembiayaan">Mitra Perbankan &amp; Pembiayaan</option>
                    <option value="Audit Internal Rapat Anggota Tahunan (RAT)">Laporan Rapat Anggota (RAT)</option>
                </select>
            </div>
        </div>

        <!-- 3 TOMBOL EKSPOR EKSEKUTIF -->
        <div class="lap-grid-3">
            <button type="submit" formaction="export_excel.php" onclick="notifyExport('Laporan Excel (.XLS)')" class="lap-export-card" style="background:linear-gradient(135deg, #065F46 0%, #059669 100%); color:#FFFFFF; border:none; box-shadow:0 6px 16px rgba(5,150,105,0.22);">
                <div style="width:42px; height:42px; border-radius:12px; background:rgba(255,255,255,0.16); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2.2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/><line x1="9" y1="3" x2="9" y2="21"/></svg>
                </div>
                <div>
                    <div style="font-size:14px; font-weight:800;">Unduh Laporan Excel (.XLS)</div>
                    <div style="font-size:11.5px; color:#D1FAE5; margin-top:3px; line-height:1.45;">Otomatis terisi rumus hitung total, rata-rata, dan kop resmi koperasi.</div>
                </div>
            </button>

            <button type="submit" formaction="laporan_pdf.php" onclick="notifyExport('Dokumen Cetak PDF')" class="lap-export-card" style="background:linear-gradient(135deg, #1E293B 0%, #0F172A 100%); color:#FFFFFF; border:none; box-shadow:0 6px 16px rgba(15,23,42,0.18);">
                <div style="width:42px; height:42px; border-radius:12px; background:rgba(255,255,255,0.14); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2.2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                </div>
                <div>
                    <div style="font-size:14px; font-weight:800;">Cetak Laporan Resmi (PDF)</div>
                    <div style="font-size:11.5px; color:#CBD5E1; margin-top:3px; line-height:1.45;">Tampilan rapi ukuran kertas A4 lengkap dengan kolom tanda tangan.</div>
                </div>
            </button>

            <button type="submit" formaction="export_csv.php" onclick="notifyExport('Data Mentah (.CSV)')" class="lap-export-card" style="background:#F8FAFC; color:#0F172A; border:1.5px solid #CBD5E1;">
                <div style="width:42px; height:42px; border-radius:12px; background:#E2E8F0; color:#334155; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                </div>
                <div>
                    <div style="font-size:14px; font-weight:800;">Unduh Data Mentah (.CSV)</div>
                    <div style="font-size:11.5px; color:#64748B; margin-top:3px; line-height:1.45;">Format tabel ringan untuk olah data lanjutan atau sistem bank.</div>
                </div>
            </button>
        </div>
    </form>
</div>

<!-- TABEL AUDIT TRAIL LOGGING (OTOMATIS JADI KARTU DI HP) -->
<div class="lap-card" style="padding:22px 24px;">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px; margin-bottom:14px;">
        <div>
            <h3 style="margin:0 0 3px; font-size:16.5px; font-weight:800; color:#0F172A;">Riwayat Unduh &amp; Cetak Dokumen (Audit Trail)</h3>
            <div style="font-size:12.5px; color:#64748B;">Setiap dokumen yang diunduh otomatis tercatat beserta nama pengunduh dan waktunya.</div>
        </div>
        <button type="button" onclick="location.reload()" style="display:inline-flex; align-items:center; gap:6px; background:#F8FAFC; border:1px solid #CBD5E1; color:#334155; padding:8px 14px; border-radius:10px; font-size:12px; font-weight:700; cursor:pointer;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
            <span>Segarkan Daftar</span>
        </button>
    </div>

    <!-- FILTER FORMAT & PENCARIAN -->
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:16px; padding-bottom:12px; border-bottom:1px solid #F1F5F9;">
        <div style="display:flex; gap:6px; flex-wrap:wrap;" id="auditFilterBar">
            <button type="button" class="lap-pill active" onclick="filterAudit('ALL', this)">Semua</button>
            <button type="button" class="lap-pill" onclick="filterAudit('EXCEL (.XLS)', this)">Excel (.XLS)</button>
            <button type="button" class="lap-pill" onclick="filterAudit('PDF PRINT', this)">PDF Print</button>
            <button type="button" class="lap-pill" onclick="filterAudit('CSV (.CSV)', this)">CSV (.CSV)</button>
        </div>
        <input type="text" id="searchAuditInput" onkeyup="searchAudit()" placeholder="Cari kode audit atau pengunduh..." class="lap-search">
    </div>

    <div>
        <table class="lap-responsive-table" style="width:100%; border-collapse:collapse; text-align:left; font-size:13px;">
            <thead>
                <tr style="background:#064E3B; color:#FFFFFF; font-size:11.5px; text-transform:uppercase; letter-spacing:0.4px;">
                    <th style="padding:12px 14px; border-radius:10px 0 0 0;">Kode Audit &amp; Waktu</th>
                    <th style="padding:12px 14px;">Pengunduh</th>
                    <th style="padding:12px 14px;">Jenis Laporan</th>
                    <th style="padding:12px 14px;">Periode &amp; Wilayah</th>
                    <th style="padding:12px 14px; border-radius:0 10px 0 0;">Format</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($audit_logs)): ?>
                    <tr>
                        <td colspan="5" style="padding:28px; text-align:center; color:#64748B;">
                            Belum ada riwayat pengunduhan laporan. Klik salah satu tombol ekspor di atas untuk mencobanya.
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($audit_logs as $log): 
                    $fmt = $log['format_file'];
                    $fmt_bg = '#DCFCE7'; $fmt_col = '#166534';
                    if ($fmt === 'PDF PRINT')   { $fmt_bg = '#E0E7FF'; $fmt_col = '#3730A3'; }
                    elseif ($fmt === 'CSV (.CSV)') { $fmt_bg = '#FEF3C7'; $fmt_col = '#92400E'; }
                ?>
                <tr class="lap-row-hover lap-mobile-card audit-data-row" data-format="<?= htmlspecialchars($fmt) ?>" style="border-bottom:1px solid #F1F5F9;">
                    <td class="td-card-top" style="padding:12px 14px;">
                        <div>
                            <div style="font-family:monospace; font-weight:800; color:#059669; font-size:12.5px;"><?= htmlspecialchars($log['kode_audit']) ?></div>
                            <div style="font-size:11px; color:#64748B; margin-top:2px;"><?= date('d M Y, H:i:s', strtotime($log['created_at'])) ?> WIB</div>
                        </div>
                    </td>
                    <td data-label="Pengunduh" style="padding:12px 14px;">
                        <div>
                            <div style="font-weight:700; color:#0F172A;"><?= htmlspecialchars($log['nama_user']) ?></div>
                            <div style="font-size:11px; color:#64748B;">Akses: <?= strtoupper(htmlspecialchars($log['role_user'])) ?></div>
                        </div>
                    </td>
                    <td data-label="Jenis Laporan" style="padding:12px 14px; font-weight:600; color:#334155;">
                        <?= htmlspecialchars($log['jenis_laporan']) ?>
                    </td>
                    <td data-label="Periode" style="padding:12px 14px; font-size:12px; color:#475569;">
                        <?= htmlspecialchars($log['filter_periode']) ?>
                    </td>
                    <td data-label="Format File" style="padding:12px 14px;">
                        <span style="background:<?= $fmt_bg ?>; color:<?= $fmt_col ?>; padding:4px 11px; border-radius:99px; font-size:11px; font-weight:800; display:inline-block;">
                            <?= htmlspecialchars($fmt) ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Pintasan Rentang Tanggal 1-Klik
function setDatePreset(type, btn) {
    document.querySelectorAll('.lap-preset-btn').forEach(el => el.classList.remove('active'));
    btn.classList.add('active');

    const now = new Date();
    const endStr = now.toISOString().split('T')[0];
    let start = new Date(now);

    if (type === 'month') {
        start = new Date(now.getFullYear(), now.getMonth(), 1);
    } else if (type === '7days') {
        start.setDate(now.getDate() - 7);
    } else if (type === '30days') {
        start.setDate(now.getDate() - 30);
    } else if (type === 'year') {
        start = new Date(now.getFullYear(), 0, 1);
    }

    const yyyy = start.getFullYear();
    const mm   = String(start.getMonth() + 1).padStart(2, '0');
    const dd   = String(start.getDate()).padStart(2, '0');

    document.getElementById('tgl_mulai').value = `${yyyy}-${mm}-${dd}`;
    document.getElementById('tgl_akhir').value = endStr;
}

// Notifikasi Ramah Saat Unduh / Cetak
function notifyExport(label) {
    const toast = document.getElementById('exportToast');
    const txt   = document.getElementById('exportToastText');
    if (toast && txt) {
        txt.textContent = label + ' sedang disiapkan di tab baru dan otomatis dicatat ke riwayat.';
        toast.style.display = 'flex';
    }
}

// Filter & Pencarian Riwayat Audit Trail
let currentAuditFormat = 'ALL';
function filterAudit(fmt, btn) {
    currentAuditFormat = fmt;
    document.querySelectorAll('#auditFilterBar .lap-pill').forEach(el => el.classList.remove('active'));
    btn.classList.add('active');
    applyAuditFilters();
}
function searchAudit() {
    applyAuditFilters();
}
function applyAuditFilters() {
    const q = document.getElementById('searchAuditInput').value.toLowerCase();
    document.querySelectorAll('.audit-data-row').forEach(row => {
        const fmt = row.getAttribute('data-format');
        const txt = row.innerText.toLowerCase();
        const matchFmt  = (currentAuditFormat === 'ALL' || fmt === currentAuditFormat);
        const matchText = txt.includes(q);
        row.style.display = (matchFmt && matchText) ? '' : 'none';
    });
}

// Buka/Tutup Menu Sidebar di Layar HP
function toggleKopSidebar() {
    const sb = document.getElementById('mainSidebar');
    const bd = document.getElementById('kopBackdrop');
    if (!sb) return;
    const isOpen = sb.classList.toggle('open');
    if (bd) bd.style.display = isOpen ? 'block' : 'none';
}
</script>

<?php require_once '../../includes/footer.php'; ?>