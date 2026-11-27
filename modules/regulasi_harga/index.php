<?php
require_once '../../config/database.php';
cekAkses(['konsumen', 'primer', 'petani']);

$role = $_SESSION['user']['role'] ?? '';

// AUTO-MIGRATE: Otomatis membuat tabel harga_acuan_shu jika belum ada di database
$pdo->exec("CREATE TABLE IF NOT EXISTS `harga_acuan_shu` (
  `id_regulasi` int(11) NOT NULL AUTO_INCREMENT,
  `kode_regulasi` varchar(25) NOT NULL,
  `nama_komoditas` varchar(100) NOT NULL,
  `kategori` enum('Komoditas Tanaman','Saprodi','Alsintan','Produk Olahan','Produk Lainnya') NOT NULL,
  `satuan` varchar(20) NOT NULL,
  `hap_petani` decimal(12,2) NOT NULL,
  `het_buyer` decimal(12,2) NOT NULL,
  `persen_petani` decimal(5,2) NOT NULL DEFAULT 85.00,
  `persen_primer` decimal(5,2) NOT NULL DEFAULT 10.00,
  `persen_konsumen` decimal(5,2) NOT NULL DEFAULT 5.00,
  `status_regulasi` enum('Aktif','Arsip') DEFAULT 'Aktif',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_regulasi`),
  UNIQUE KEY `kode_regulasi` (`kode_regulasi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// Isi data awal standar jika tabel baru saja dibuat dan masih kosong
$cek_isi = (int)$pdo->query("SELECT COUNT(*) FROM harga_acuan_shu")->fetchColumn();
if ($cek_isi === 0) {
    $pdo->exec("INSERT IGNORE INTO `harga_acuan_shu` (`kode_regulasi`, `nama_komoditas`, `kategori`, `satuan`, `hap_petani`, `het_buyer`, `persen_petani`, `persen_primer`, `persen_konsumen`, `status_regulasi`) VALUES
    ('REG-2026-001', 'Kentang Granola Grade A', 'Komoditas Tanaman', 'Kg', 12000.00, 14500.00, 85.00, 10.00, 5.00, 'Aktif'),
    ('REG-2026-002', 'Biji Kopi Arabika Green Bean', 'Produk Olahan', 'Kg', 68000.00, 82000.00, 84.00, 11.00, 5.00, 'Aktif'),
    ('REG-2026-003', 'Pupuk Organik Kompos Granul', 'Saprodi', 'Karung', 42000.00, 50000.00, 80.00, 13.00, 7.00, 'Aktif')");
}

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

$regulasi_list = $pdo->query("SELECT * FROM harga_acuan_shu ORDER BY FIELD(status_regulasi, 'Aktif', 'Arsip'), id_regulasi DESC")->fetchAll();
$is_pusat      = ($role === 'konsumen');

// Hitung metrik ringkasan
$total_regulasi = count($regulasi_list);
$count_aktif    = 0;
$count_arsip    = 0;
$sum_p_petani   = 0;

foreach ($regulasi_list as $item) {
    if (($item['status_regulasi'] ?? 'Aktif') === 'Aktif') {
        $count_aktif++;
        $sum_p_petani += (float)$item['persen_petani'];
    } else {
        $count_arsip++;
    }
}
$avg_p_petani = $count_aktif > 0 ? round($sum_p_petani / $count_aktif, 1) : 85.0;

// Helper CSRF input
$csrf_input = '';
if (function_exists('csrf_field')) {
    $csrf_input = csrf_field();
} elseif (!empty($_SESSION['csrf_token'])) {
    $csrf_input = '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') . '">';
}
?>

<style>
    /* KONSISTENSI SIDEBAR & WRAPPER UTAMA */
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

    .reg-wrap {
        display: flex;
        flex-direction: column;
        gap: 22px;
        color: #0F172A;
    }

    /* HERO BANNER KONSISTEN DENGAN MODUL KOPNOVA */
    .reg-hero {
        background: linear-gradient(135deg, #042F24 0%, #064E3B 58%, #047857 100%);
        border-radius: 18px;
        padding: 24px 26px;
        color: #FFFFFF;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 18px;
        box-shadow: 0 10px 26px rgba(4, 47, 36, 0.14);
        border: 1px solid rgba(16, 185, 129, 0.25);
    }
    .reg-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        background: rgba(255, 255, 255, 0.12);
        border: 1px solid rgba(255, 255, 255, 0.18);
        color: #6EE7B7;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 0.7px;
        text-transform: uppercase;
        padding: 5px 12px;
        border-radius: 99px;
        margin-bottom: 10px;
    }
    .reg-eyebrow-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: #FBBF24;
    }
    .reg-hero-title {
        margin: 0 0 6px;
        font-size: 22px;
        font-weight: 800;
        letter-spacing: -0.3px;
        line-height: 1.25;
    }
    .reg-hero-sub {
        margin: 0;
        font-size: 13.5px;
        color: rgba(236, 253, 245, 0.88);
        max-width: 630px;
        line-height: 1.5;
    }
    .reg-hero-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        background: #F59E0B;
        color: #042F24;
        border: none;
        padding: 12px 20px;
        border-radius: 12px;
        font-family: inherit;
        font-size: 13.5px;
        font-weight: 800;
        cursor: pointer;
        box-shadow: 0 6px 18px rgba(245, 158, 11, 0.32);
        transition: background-color 0.16s ease, transform 0.14s ease;
        white-space: nowrap;
    }
    .reg-hero-btn:hover {
        background: #FBBF24;
    }
    .reg-hero-btn:active {
        transform: scale(0.98);
    }

    /* ALERT NOTIFIKASI */
    .reg-alert {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 13px 16px;
        border-radius: 12px;
        font-size: 13.5px;
        font-weight: 600;
    }
    .reg-alert-success {
        background: #DCFCE7;
        border: 1px solid #86EFAC;
        color: #166534;
    }
    .reg-alert-error {
        background: #FEE2E2;
        border: 1px solid #FCA5A5;
        color: #991B1B;
    }
    .reg-alert-close {
        background: none;
        border: none;
        color: inherit;
        font-size: 18px;
        font-weight: 800;
        cursor: pointer;
        padding: 0 4px;
        opacity: 0.75;
    }
    .reg-alert-close:hover {
        opacity: 1;
    }

    /* 3 KARTU RINGKASAN KPI */
    .reg-grid-3 {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 16px;
    }
    .reg-kpi {
        background: #FFFFFF;
        border-radius: 14px;
        border: 1px solid #E2E8F0;
        padding: 18px 20px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.025);
        transition: border-color 0.16s ease, transform 0.16s ease;
    }
    .reg-kpi.clickable {
        cursor: pointer;
    }
    .reg-kpi:hover {
        border-color: #10B981;
        transform: translateY(-2px);
    }
    .reg-kpi-val {
        font-size: clamp(1.3rem, 1.8vw, 1.6rem);
        font-weight: 800;
        color: #0F172A;
        margin: 8px 0 3px;
        font-variant-numeric: tabular-nums;
    }

    /* KARTU SIMULATOR SHU */
    .reg-sim-card {
        background: linear-gradient(135deg, #064E3B 0%, #047857 100%);
        border-radius: 18px;
        padding: 22px 24px;
        color: #FFFFFF;
        box-shadow: 0 10px 24px rgba(6, 78, 59, 0.14);
        border: 1px solid rgba(16, 185, 129, 0.28);
    }
    .reg-sim-grid-in {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(165px, 1fr));
        gap: 12px;
        background: rgba(255, 255, 255, 0.09);
        padding: 15px;
        border-radius: 14px;
        border: 1px solid rgba(255, 255, 255, 0.16);
    }
    .reg-sim-input {
        width: 100%;
        box-sizing: border-box;
        padding: 9px 12px;
        border-radius: 10px;
        border: none;
        margin-top: 5px;
        font-family: inherit;
        font-weight: 800;
        font-size: 14px;
        color: #0F172A;
        background: #FFFFFF;
        font-variant-numeric: tabular-nums;
    }
    .reg-sim-input:focus {
        outline: 2px solid #FBBF24;
    }
    .reg-sim-out-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
        margin-top: 14px;
    }
    .reg-sim-out {
        background: #FFFFFF;
        color: #0F172A;
        padding: 14px 16px;
        border-radius: 12px;
    }

    /* CARD UTAMA & TOOLBAR */
    .reg-card {
        background: #FFFFFF;
        border-radius: 16px;
        border: 1px solid #E2E8F0;
        padding: 22px;
        box-shadow: 0 2px 10px rgba(15, 23, 42, 0.03);
    }
    .reg-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 16px;
        padding-bottom: 13px;
        border-bottom: 1px solid #F1F5F9;
    }
    .reg-pill {
        padding: 7px 13px;
        border-radius: 99px;
        font-family: inherit;
        font-size: 12px;
        font-weight: 700;
        border: 1px solid #E2E8F0;
        background: #F8FAFC;
        color: #475569;
        cursor: pointer;
        transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease;
        white-space: nowrap;
    }
    .reg-pill:hover {
        border-color: #10B981;
        color: #065F46;
    }
    .reg-pill.active {
        background: #064E3B;
        color: #FFFFFF;
        border-color: #064E3B;
    }
    .reg-search {
        padding: 8.5px 13px 8.5px 35px;
        border: 1px solid #CBD5E1;
        border-radius: 10px;
        font-family: inherit;
        font-size: 13px;
        color: #0F172A;
        background: #FFFFFF;
        width: 100%;
        box-sizing: border-box;
        transition: border-color 0.16s ease, box-shadow 0.16s ease;
    }
    .reg-search:focus {
        outline: none;
        border-color: #10B981;
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.14);
    }

    /* TABEL DESKTOP */
    .reg-table-wrap {
        width: 100%;
        overflow-x: auto;
        border: 1px solid #E2E8F0;
        border-radius: 12px;
    }
    .reg-responsive-table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
        font-size: 13.5px;
    }
    .reg-responsive-table thead tr {
        background: #F8FAFC;
        border-bottom: 1.5px solid #E2E8F0;
    }
    .reg-responsive-table th {
        padding: 12px 14px;
        font-size: 11.5px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #475569;
        white-space: nowrap;
    }
    .reg-responsive-table td {
        padding: 14px;
        border-bottom: 1px solid #F1F5F9;
        color: #1E293B;
        vertical-align: middle;
    }
    .reg-responsive-table tbody tr:last-child td {
        border-bottom: none;
    }
    .reg-row-hover:hover {
        background-color: #F8FAFC;
    }

    /* BADGES & BUTTONS */
    .reg-code {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 7px;
        background: #ECFDF5;
        border: 1px solid #A7F3D0;
        color: #064E3B;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 11.5px;
        font-weight: 800;
    }
    .reg-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        border: none;
        border-radius: 8px;
        font-family: inherit;
        font-size: 12px;
        font-weight: 700;
        padding: 7px 12px;
        cursor: pointer;
        white-space: nowrap;
        transition: background-color 0.15s ease, transform 0.14s ease;
    }
    .reg-btn:active {
        transform: scale(0.98);
    }
    .reg-btn-amber {
        background: #FEF3C7;
        color: #92400E;
        border: 1px solid #FDE68A;
    }
    .reg-btn-amber:hover {
        background: #FDE68A;
    }
    .reg-btn-red {
        background: #FEE2E2;
        color: #991B1B;
        border: 1px solid #FECACA;
    }
    .reg-btn-red:hover {
        background: #FECACA;
    }

    /* MODAL RINGAN (ZERO LAG) */
    .reg-modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.58);
        z-index: 999;
        align-items: center;
        justify-content: center;
        padding: 16px;
    }
    .reg-modal-overlay.show {
        display: flex;
    }
    .reg-modal-box {
        background: #FFFFFF;
        border: 1px solid #E2E8F0;
        border-radius: 18px;
        width: 100%;
        max-width: 530px;
        max-height: 92vh;
        overflow-y: auto;
        box-shadow: 0 22px 46px rgba(15, 23, 42, 0.24);
    }
    .reg-modal-head {
        background: #042F24;
        color: #FFFFFF;
        padding: 16px 22px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        position: sticky;
        top: 0;
        z-index: 2;
    }
    .reg-label {
        display: block;
        font-size: 12px;
        font-weight: 700;
        color: #334155;
    }
    .reg-input {
        width: 100%;
        box-sizing: border-box;
        padding: 10px 12px;
        border: 1px solid #CBD5E1;
        border-radius: 10px;
        margin-top: 5px;
        font-family: inherit;
        font-size: 13px;
        font-weight: 600;
        color: #0F172A;
        background: #FFFFFF;
        transition: border-color 0.16s ease, box-shadow 0.16s ease;
    }
    .reg-input:focus {
        outline: none;
        border-color: #10B981;
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.14);
    }
    .reg-form-row-2 {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }
    .reg-form-row-2-wide {
        display: grid;
        grid-template-columns: 1.7fr 1fr;
        gap: 12px;
    }
    .reg-form-row-3 {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 10px;
    }
    .mobile-nav-trigger {
        display: none;
    }

    @media (max-width: 1080px) {
        .reg-grid-3 {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 960px) {
        #mainSidebar {
            position: fixed !important;
            left: -280px;
            top: 0;
            bottom: 0;
            transition: left 0.25s ease;
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
            font-family: inherit;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            margin-bottom: 14px;
        }
    }

    /* TABEL OTOMATIS JADI KARTU DI LAYAR HP (< 768px) */
    @media (max-width: 768px) {
        .reg-sim-out-grid {
            grid-template-columns: 1fr;
        }
        .reg-table-wrap {
            border: none;
            border-radius: 0;
            overflow: visible;
        }
        .reg-responsive-table thead {
            display: none;
        }
        .reg-responsive-table,
        .reg-responsive-table tbody,
        .reg-responsive-table tr,
        .reg-responsive-table td {
            display: block;
            width: 100%;
            box-sizing: border-box;
        }
        .reg-responsive-table tr.reg-mobile-card {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 14px;
            padding: 14px;
            margin-bottom: 12px;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03);
        }
        .reg-responsive-table td {
            padding: 6px 0 !important;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            border: none !important;
            text-align: right;
            max-width: 100% !important;
        }
        .reg-responsive-table td::before {
            content: attr(data-label);
            font-size: 11.5px;
            font-weight: 700;
            color: #64748B;
            text-align: left;
            flex-shrink: 0;
        }
        .reg-responsive-table td.td-card-header {
            border-bottom: 1px dashed #E2E8F0 !important;
            padding-bottom: 9px !important;
            margin-bottom: 4px;
            justify-content: flex-start;
            text-align: left;
        }
        .reg-responsive-table td.td-card-header::before {
            display: none;
        }
        .reg-responsive-table td.td-card-ratio {
            flex-direction: column;
            align-items: stretch;
            text-align: left;
        }
        .reg-responsive-table td.td-card-ratio::before {
            margin-bottom: 4px;
        }
        .reg-responsive-table td.td-card-action {
            border-top: 1px solid #F1F5F9 !important;
            padding-top: 10px !important;
            margin-top: 6px;
            flex-direction: column;
            align-items: stretch;
        }
        .reg-responsive-table td.td-card-action::before {
            margin-bottom: 5px;
        }
        .reg-responsive-table td.td-card-action .reg-action-group {
            width: 100%;
            display: flex;
            gap: 8px;
        }
        .reg-responsive-table td.td-card-action .reg-action-group button,
        .reg-responsive-table td.td-card-action .reg-action-group form {
            flex: 1;
        }
        .reg-responsive-table td.td-card-action .reg-action-group form button {
            width: 100%;
        }
    }

    @media (max-width: 640px) {
        .reg-grid-3, .reg-form-row-2, .reg-form-row-2-wide, .reg-form-row-3 {
            grid-template-columns: minmax(0, 1fr);
        }
        .reg-hero {
            padding: 20px;
        }
        .reg-hero-title {
            font-size: 19px;
        }
        .reg-hero-btn {
            width: 100%;
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
<div id="kopBackdrop" onclick="toggleKopSidebar()" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.5); z-index:45;"></div>

<div class="reg-wrap">

    <!-- BANNER HEADER KONSISTEN -->
    <section class="reg-hero">
        <div>
            <div class="reg-eyebrow">
                <span class="reg-eyebrow-dot"></span>
                <span>PRICE &amp; MARGIN CONTROL • REGULASI SHU</span>
            </div>
            <h2 class="reg-hero-title">Penetapan Harga Acuan (HAP) &amp; Margin Bagi Hasil</h2>
            <p class="reg-hero-sub">
                Standarisasi harga beli hulu petani (HAP), harga jual hilir buyer (HET), serta skema distribusi SHU antar aktor koperasi lintas kecamatan.
            </p>
        </div>

        <?php if ($is_pusat): ?>
            <button type="button" onclick="openModal('modalTambahRegulasi')" class="reg-hero-btn">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <span>Tetapkan Harga Acuan Baru</span>
            </button>
        <?php endif; ?>
    </section>

    <!-- ALERT NOTIFIKASI -->
    <?php if (isset($_GET['msg']) && $_GET['msg'] !== ''): ?>
        <div class="reg-alert reg-alert-success">
            <span>✓ <?= htmlspecialchars($_GET['msg']) ?></span>
            <button type="button" class="reg-alert-close" onclick="this.parentElement.remove()" aria-label="Tutup">&times;</button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['err']) && $_GET['err'] !== ''): ?>
        <div class="reg-alert reg-alert-error">
            <span>! <?= htmlspecialchars($_GET['err']) ?></span>
            <button type="button" class="reg-alert-close" onclick="this.parentElement.remove()" aria-label="Tutup">&times;</button>
        </div>
    <?php endif; ?>

    <!-- 3 KARTU RINGKASAN KPI -->
    <section class="reg-grid-3">
        <div class="reg-kpi clickable" style="border-left:4px solid #059669;" onclick="quickSelectRegFilter('Aktif')" title="Filter regulasi aktif">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                <span style="font-size:12px; color:#065F46; font-weight:700;">Regulasi Harga Aktif</span>
                <div style="width:36px; height:36px; border-radius:10px; background:#DCFCE7; color:#059669; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
            </div>
            <div class="reg-kpi-val" style="color:#065F46;"><?= $count_aktif ?> Komoditas</div>
            <div style="font-size:11.5px; color:#64748B;">Berlaku aktif sebagai acuan cabang</div>
        </div>

        <div class="reg-kpi" style="border-left:4px solid #D97706;">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                <span style="font-size:12px; color:#92400E; font-weight:700;">Rata-Rata Porsi Petani</span>
                <div style="width:36px; height:36px; border-radius:10px; background:#FEF3C7; color:#D97706; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                </div>
            </div>
            <div class="reg-kpi-val" style="color:#B45309;"><?= number_format($avg_p_petani, 1, ',', '.') ?>%</div>
            <div style="font-size:11.5px; color:#64748B;">Dari harga jual hilir buyer (HET)</div>
        </div>

        <div class="reg-kpi clickable" style="border-left:4px solid #2563EB;" onclick="quickSelectRegFilter('ALL')" title="Tampilkan semua ketetapan harga">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                <span style="font-size:12px; color:#1E40AF; font-weight:700;">Total Ketetapan Tercatat</span>
                <div style="width:36px; height:36px; border-radius:10px; background:#DBEAFE; color:#2563EB; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                </div>
            </div>
            <div class="reg-kpi-val"><?= $total_regulasi ?> Ketetapan</div>
            <div style="font-size:11.5px; color:#64748B;"><?= $count_arsip ?> ketetapan dalam status arsip</div>
        </div>
    </section>

    <!-- KARTU SIMULATOR KALKULATOR BAGI HASIL SHU INTERAKTIF -->
    <section class="reg-sim-card">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px; margin-bottom:16px;">
            <div>
                <h3 style="margin:0 0 4px; font-size:17px; font-weight:800;">Simulator Transparansi Bagi Hasil (Hulu &rarr; Hilir)</h3>
                <div style="font-size:12.5px; color:#D1FAE5;">Uji skema pembagian hasil penjualan dari Buyer ke Petani, Koperasi Primer, dan Koperasi Konsumen.</div>
            </div>
            <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                <select id="simPresetSelect" onchange="loadSimulatorPreset(this)" style="padding:6px 12px; border-radius:99px; border:1px solid rgba(255,255,255,0.3); background:rgba(255,255,255,0.14); color:#FFFFFF; font-family:inherit; font-size:12px; font-weight:700; cursor:pointer; outline:none;">
                    <option value="" style="color:#0F172A;">Standar Rekomendasi (85 : 10 : 5)</option>
                    <?php foreach ($regulasi_list as $rp):
                        if (($rp['status_regulasi'] ?? '') !== 'Aktif') continue;
                        $presetJson = htmlspecialchars(json_encode([
                            'het'      => (float)$rp['het_buyer'],
                            'petani'   => (float)$rp['persen_petani'],
                            'primer'   => (float)$rp['persen_primer'],
                            'konsumen' => (float)$rp['persen_konsumen']
                        ]), ENT_QUOTES, 'UTF-8');
                    ?>
                        <option value="<?= $presetJson ?>" style="color:#0F172A;">
                            Muat: <?= htmlspecialchars($rp['nama_komoditas']) ?> (<?= rupiah((float)$rp['het_buyer']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <span id="simTotalBadge" style="background:rgba(16,185,129,0.28); border:1px solid rgba(110,231,183,0.5); color:#ECFDF5; padding:5px 12px; border-radius:99px; font-size:11.5px; font-weight:800;">
                    Total Rasio: 100%
                </span>
            </div>
        </div>

        <div class="reg-sim-grid-in">
            <div>
                <label for="simHarga" style="font-size:11.5px; font-weight:700; color:#A7F3D0;">Harga Jual Buyer (Rp)</label>
                <input type="number" id="simHarga" value="15000" min="0" oninput="hitungSimulasi()" class="reg-sim-input">
            </div>
            <div>
                <label for="simPetani" style="font-size:11.5px; font-weight:700; color:#A7F3D0;">Hak Petani (%)</label>
                <input type="number" id="simPetani" value="85" step="0.1" min="0" max="100" oninput="hitungSimulasi()" class="reg-sim-input">
            </div>
            <div>
                <label for="simPrimer" style="font-size:11.5px; font-weight:700; color:#A7F3D0;">Kas Primer (%)</label>
                <input type="number" id="simPrimer" value="10" step="0.1" min="0" max="100" oninput="hitungSimulasi()" class="reg-sim-input">
            </div>
            <div>
                <label for="simKonsumen" style="font-size:11.5px; font-weight:700; color:#A7F3D0;">Kas Konsumen (%)</label>
                <input type="number" id="simKonsumen" value="5" step="0.1" min="0" max="100" oninput="hitungSimulasi()" class="reg-sim-input">
            </div>
        </div>

        <!-- HASIL SIMULASI REAL-TIME -->
        <div class="reg-sim-out-grid">
            <div class="reg-sim-out" style="border-left:5px solid #10B981;">
                <div style="font-size:11px; font-weight:800; color:#059669;">DITERIMA PETANI HULU</div>
                <div id="outPetani" style="font-size:20px; font-weight:800; margin-top:3px; font-variant-numeric:tabular-nums;">Rp 12.750</div>
            </div>
            <div class="reg-sim-out" style="border-left:5px solid #F59E0B;">
                <div style="font-size:11px; font-weight:800; color:#D97706;">KAS KOPERASI PRIMER</div>
                <div id="outPrimer" style="font-size:20px; font-weight:800; margin-top:3px; font-variant-numeric:tabular-nums;">Rp 1.500</div>
            </div>
            <div class="reg-sim-out" style="border-left:5px solid #2563EB;">
                <div style="font-size:11px; font-weight:800; color:#2563EB;">KAS KOPERASI KONSUMEN</div>
                <div id="outKonsumen" style="font-size:20px; font-weight:800; margin-top:3px; font-variant-numeric:tabular-nums;">Rp 750</div>
            </div>
        </div>
    </section>

    <!-- TABEL DAFTAR REGULASI HARGA ACUAN & MARGIN -->
    <section class="reg-card" id="regTableSection">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:10px; margin-bottom:14px;">
            <div>
                <h3 style="margin:0; font-size:16.5px; font-weight:800; color:#0F172A;">Daftar Ketetapan Harga Acuan &amp; Rasio Margin Nasional</h3>
                <div style="font-size:12.5px; color:#64748B; margin-top:2px;">Menjadi pedoman resmi seluruh Koperasi Primer kecamatan agar tidak terjadi selisih harga antar-wilayah.</div>
            </div>
        </div>

        <!-- FILTER PILLS & PENCARIAN LIVE -->
        <div class="reg-toolbar">
            <div style="display:flex; gap:6px; flex-wrap:wrap;" id="regFilterBar">
                <button type="button" class="reg-pill active" data-filter="ALL" onclick="filterRegulasi('ALL', this)">Semua (<?= $total_regulasi ?>)</button>
                <button type="button" class="reg-pill" data-filter="Aktif" onclick="filterRegulasi('Aktif', this)">Aktif (<?= $count_aktif ?>)</button>
                <button type="button" class="reg-pill" data-filter="Arsip" onclick="filterRegulasi('Arsip', this)">Arsip (<?= $count_arsip ?>)</button>
            </div>

            <div style="position:relative; min-width:230px; flex:1; max-width:340px;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#64748B" stroke-width="2.2" style="position:absolute; left:11px; top:50%; transform:translateY(-50%); pointer-events:none;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="search" id="regSearchInput" oninput="applyRegFilters()" placeholder="Cari kode regulasi, komoditas, kategori..." class="reg-search">
            </div>
        </div>

        <div class="reg-table-wrap">
            <table class="reg-responsive-table">
                <thead>
                    <tr>
                        <th>Kode &amp; Komoditas</th>
                        <th>HAP Petani (Hulu)</th>
                        <th>Harga Jual Buyer</th>
                        <th>Skema Bagi Hasil (Petani : Primer : Konsumen)</th>
                        <th>Status</th>
                        <?php if ($is_pusat): ?>
                            <th style="text-align:right;">Kontrol Pusat</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($regulasi_list)): ?>
                        <tr>
                            <td colspan="<?= $is_pusat ? 6 : 5 ?>" style="padding:28px; text-align:center; color:#64748B;">
                                Belum ada ketetapan harga acuan yang diterbitkan.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($regulasi_list as $r):
                            $het          = (float)$r['het_buyer'];
                            $hap          = (float)$r['hap_petani'];
                            $p_petani     = (float)$r['persen_petani'];
                            $p_primer     = (float)$r['persen_primer'];
                            $p_konsumen   = (float)$r['persen_konsumen'];
                            $nom_petani   = ($het * $p_petani) / 100;
                            $st_reg       = $r['status_regulasi'] ?? 'Aktif';

                            $search_blob = strtolower(
                                ($r['kode_regulasi'] ?? '') . ' ' .
                                ($r['nama_komoditas'] ?? '') . ' ' .
                                ($r['kategori'] ?? '') . ' ' .
                                ($r['satuan'] ?? '')
                            );

                            $editPayload = json_encode([
                                'id_regulasi'     => (int)$r['id_regulasi'],
                                'kode_regulasi'   => $r['kode_regulasi'],
                                'nama_komoditas'  => $r['nama_komoditas'],
                                'kategori'        => $r['kategori'],
                                'satuan'          => $r['satuan'],
                                'hap_petani'      => $hap,
                                'het_buyer'       => $het,
                                'persen_petani'   => $p_petani,
                                'persen_primer'   => $p_primer,
                                'persen_konsumen' => $p_konsumen,
                                'status_regulasi' => $st_reg
                            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                        ?>
                        <tr class="reg-row-hover reg-mobile-card reg-data-row"
                            data-status="<?= htmlspecialchars($st_reg, ENT_QUOTES) ?>"
                            data-search="<?= htmlspecialchars($search_blob, ENT_QUOTES) ?>">
                            <td class="td-card-header">
                                <div>
                                    <span class="reg-code"><?= htmlspecialchars($r['kode_regulasi']) ?></span>
                                    <div style="font-weight:800; color:#0F172A; font-size:14px; margin-top:4px;"><?= htmlspecialchars($r['nama_komoditas']) ?></div>
                                    <div style="font-size:11.5px; color:#64748B; margin-top:2px;">
                                        <?= htmlspecialchars($r['kategori']) ?> • Satuan: <?= htmlspecialchars($r['satuan']) ?>
                                    </div>
                                </div>
                            </td>
                            <td data-label="HAP Petani (Hulu)">
                                <div>
                                    <div style="font-weight:800; color:#064E3B; font-variant-numeric:tabular-nums;"><?= rupiah($hap) ?></div>
                                    <div style="font-size:11px; color:#64748B;">Batas minimum beli</div>
                                </div>
                            </td>
                            <td data-label="Harga Jual Buyer">
                                <div>
                                    <div style="font-weight:800; color:#D97706; font-variant-numeric:tabular-nums;"><?= rupiah($het) ?></div>
                                    <div style="font-size:11px; color:#64748B;">Harga etalase pasar</div>
                                </div>
                            </td>
                            <td data-label="Skema Bagi Hasil" class="td-card-ratio" style="min-width:255px;">
                                <div style="width:100%;">
                                    <div style="display:flex; justify-content:space-between; gap:6px; font-size:11.5px; font-weight:800; margin-bottom:5px; font-variant-numeric:tabular-nums;">
                                        <span style="color:#059669;">Petani <?= $p_petani ?>% (<?= rupiah($nom_petani) ?>)</span>
                                        <span style="color:#D97706;">Prm <?= $p_primer ?>%</span>
                                        <span style="color:#2563EB;">Ksm <?= $p_konsumen ?>%</span>
                                    </div>
                                    <div style="display:flex; height:8px; border-radius:99px; overflow:hidden; background:#E2E8F0;">
                                        <div style="width:<?= max(0, min(100, $p_petani)) ?>%; background:#10B981;" title="Hak Petani"></div>
                                        <div style="width:<?= max(0, min(100, $p_primer)) ?>%; background:#F59E0B;" title="Kas Primer"></div>
                                        <div style="width:<?= max(0, min(100, $p_konsumen)) ?>%; background:#3B82F6;" title="Kas Konsumen"></div>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Status">
                                <span style="background:<?= $st_reg === 'Aktif' ? '#DCFCE7' : '#F1F5F9' ?>; color:<?= $st_reg === 'Aktif' ? '#166534' : '#475569' ?>; border:1px solid <?= $st_reg === 'Aktif' ? '#BBF7D0' : '#CBD5E1' ?>; padding:4px 11px; border-radius:99px; font-size:11.5px; font-weight:800; display:inline-block;">
                                    <?= htmlspecialchars($st_reg) ?>
                                </span>
                            </td>
                            <?php if ($is_pusat): ?>
                            <td data-label="Kontrol Pusat" class="td-card-action" style="text-align:right; white-space:nowrap;">
                                <div class="reg-action-group" style="display:inline-flex; gap:6px;">
                                    <button type="button" onclick='openEditRegulasi(<?= $editPayload ?>)' class="reg-btn reg-btn-amber">
                                        Ubah Tarif
                                    </button>
                                    <form action="proses.php" method="POST" style="margin:0; display:inline;" onsubmit="return confirm('Hapus ketetapan harga acuan <?= htmlspecialchars($r['kode_regulasi'], ENT_QUOTES) ?> ini?')">
                                        <?= $csrf_input ?>
                                        <input type="hidden" name="aksi" value="hapus_regulasi">
                                        <input type="hidden" name="id_regulasi" value="<?= (int)$r['id_regulasi'] ?>">
                                        <button type="submit" class="reg-btn reg-btn-red">
                                            Hapus
                                        </button>
                                    </form>
                                </div>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                        <tr id="regEmptyFilterRow" style="display:none;">
                            <td colspan="<?= $is_pusat ? 6 : 5 ?>" style="padding:26px; text-align:center; color:#64748B;">
                                Tidak ada ketetapan harga acuan yang cocok dengan filter status atau kata kunci pencarian.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

</div>

<?php if ($is_pusat): ?>
<!-- MODAL TAMBAH REGULASI HARGA (ZERO LAG) -->
<div id="modalTambahRegulasi" class="reg-modal-overlay" onclick="if(event.target===this) closeModal('modalTambahRegulasi')">
    <div class="reg-modal-box">
        <div class="reg-modal-head">
            <div>
                <div style="font-weight:800; font-size:15.5px;">Tetapkan Harga Acuan &amp; Margin Baru</div>
                <div style="font-size:11.5px; color:#A7F3D0; margin-top:2px;">Standar harga nasional berlaku otomatis di seluruh Koperasi Primer.</div>
            </div>
            <button type="button" onclick="closeModal('modalTambahRegulasi')" style="background:rgba(255,255,255,0.12); border:none; width:30px; height:30px; border-radius:8px; font-size:18px; cursor:pointer; color:#FFFFFF;">&times;</button>
        </div>

        <form action="proses.php" method="POST" style="padding:20px 22px 22px; display:flex; flex-direction:column; gap:13px;">
            <?= $csrf_input ?>
            <input type="hidden" name="aksi" value="tambah_regulasi">

            <div>
                <label class="reg-label" for="add_nama_komoditas">Nama Komoditas Standar *</label>
                <input type="text" id="add_nama_komoditas" name="nama_komoditas" placeholder="Contoh: Kubis Segar Grade A" required class="reg-input">
            </div>

            <div class="reg-form-row-2-wide">
                <div>
                    <label class="reg-label" for="add_kategori">Kategori Klaster *</label>
                    <select id="add_kategori" name="kategori" class="reg-input">
                        <option value="Komoditas Tanaman">Komoditas Tanaman</option>
                        <option value="Saprodi">Saprodi</option>
                        <option value="Alsintan">Alsintan</option>
                        <option value="Produk Olahan">Produk Olahan</option>
                        <option value="Produk Lainnya">Produk Lainnya</option>
                    </select>
                </div>
                <div>
                    <label class="reg-label" for="add_satuan">Satuan *</label>
                    <input type="text" id="add_satuan" name="satuan" value="Kg" required class="reg-input">
                </div>
            </div>

            <div class="reg-form-row-2">
                <div>
                    <label class="reg-label" for="add_hap_petani" style="color:#059669;">HAP Petani Hulu (Rp) *</label>
                    <input type="number" id="add_hap_petani" name="hap_petani" min="1" step="1" placeholder="10000" required class="reg-input">
                </div>
                <div>
                    <label class="reg-label" for="add_het_buyer" style="color:#D97706;">Harga Jual Buyer (Rp) *</label>
                    <input type="number" id="add_het_buyer" name="het_buyer" min="1" step="1" placeholder="12500" required class="reg-input">
                </div>
            </div>

            <div style="background:#F8FAFC; padding:12px 14px; border-radius:12px; border:1px solid #E2E8F0;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                    <span style="font-size:11.5px; font-weight:800; color:#334155;">Rasio Bagi Hasil SHU (Wajib 100%)</span>
                    <span id="add_ratio_sum" style="font-size:11.5px; font-weight:800; color:#059669;">Total: 100%</span>
                </div>
                <div class="reg-form-row-3">
                    <div>
                        <label for="add_p_petani" style="font-size:11px; color:#059669; font-weight:700;">Petani (%)</label>
                        <input type="number" step="0.1" min="0" max="100" id="add_p_petani" name="persen_petani" value="85" oninput="updateModalRatioSum('add')" required class="reg-input">
                    </div>
                    <div>
                        <label for="add_p_primer" style="font-size:11px; color:#D97706; font-weight:700;">Primer (%)</label>
                        <input type="number" step="0.1" min="0" max="100" id="add_p_primer" name="persen_primer" value="10" oninput="updateModalRatioSum('add')" required class="reg-input">
                    </div>
                    <div>
                        <label for="add_p_konsumen" style="font-size:11px; color:#2563EB; font-weight:700;">Konsumen (%)</label>
                        <input type="number" step="0.1" min="0" max="100" id="add_p_konsumen" name="persen_konsumen" value="5" oninput="updateModalRatioSum('add')" required class="reg-input">
                    </div>
                </div>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:4px;">
                <button type="button" onclick="closeModal('modalTambahRegulasi')" style="background:#F1F5F9; color:#334155; border:1px solid #CBD5E1; padding:10px 16px; border-radius:10px; font-family:inherit; font-size:13px; font-weight:700; cursor:pointer;">Batal</button>
                <button type="submit" style="background:#064E3B; color:#FFFFFF; border:none; padding:10px 18px; border-radius:10px; font-family:inherit; font-weight:700; font-size:13px; cursor:pointer;">
                    Terbitkan Ketetapan Harga
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDIT REGULASI HARGA (ZERO LAG) -->
<div id="modalEditRegulasi" class="reg-modal-overlay" onclick="if(event.target===this) closeModal('modalEditRegulasi')">
    <div class="reg-modal-box">
        <div class="reg-modal-head">
            <div>
                <div style="font-weight:800; font-size:15.5px;" id="edit_modal_title">Perbarui Tarif &amp; Rasio Margin</div>
                <div style="font-size:11.5px; color:#A7F3D0; margin-top:2px;">Perubahan tarif langsung berlaku sebagai pedoman transaksi berikutnya.</div>
            </div>
            <button type="button" onclick="closeModal('modalEditRegulasi')" style="background:rgba(255,255,255,0.12); border:none; width:30px; height:30px; border-radius:8px; font-size:18px; cursor:pointer; color:#FFFFFF;">&times;</button>
        </div>

        <form action="proses.php" method="POST" style="padding:20px 22px 22px; display:flex; flex-direction:column; gap:13px;">
            <?= $csrf_input ?>
            <input type="hidden" name="aksi" value="edit_regulasi">
            <input type="hidden" name="id_regulasi" id="edit_id_regulasi">

            <div>
                <label class="reg-label" for="edit_nama_komoditas">Nama Komoditas *</label>
                <input type="text" name="nama_komoditas" id="edit_nama_komoditas" required class="reg-input">
            </div>

            <div class="reg-form-row-2-wide">
                <div>
                    <label class="reg-label" for="edit_kategori">Kategori Klaster *</label>
                    <select name="kategori" id="edit_kategori" class="reg-input">
                        <option value="Komoditas Tanaman">Komoditas Tanaman</option>
                        <option value="Saprodi">Saprodi</option>
                        <option value="Alsintan">Alsintan</option>
                        <option value="Produk Olahan">Produk Olahan</option>
                        <option value="Produk Lainnya">Produk Lainnya</option>
                    </select>
                </div>
                <div>
                    <label class="reg-label" for="edit_satuan">Satuan *</label>
                    <input type="text" name="satuan" id="edit_satuan" required class="reg-input">
                </div>
            </div>

            <div class="reg-form-row-2">
                <div>
                    <label class="reg-label" for="edit_hap_petani" style="color:#059669;">HAP Petani Hulu (Rp) *</label>
                    <input type="number" name="hap_petani" id="edit_hap_petani" min="1" step="1" required class="reg-input">
                </div>
                <div>
                    <label class="reg-label" for="edit_het_buyer" style="color:#D97706;">Harga Jual Buyer (Rp) *</label>
                    <input type="number" name="het_buyer" id="edit_het_buyer" min="1" step="1" required class="reg-input">
                </div>
            </div>

            <div style="background:#F8FAFC; padding:12px 14px; border-radius:12px; border:1px solid #E2E8F0;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                    <span style="font-size:11.5px; font-weight:800; color:#334155;">Rasio Bagi Hasil SHU (Wajib 100%)</span>
                    <span id="edit_ratio_sum" style="font-size:11.5px; font-weight:800; color:#059669;">Total: 100%</span>
                </div>
                <div class="reg-form-row-3">
                    <div>
                        <label for="edit_persen_petani" style="font-size:11px; color:#059669; font-weight:700;">Petani (%)</label>
                        <input type="number" step="0.1" min="0" max="100" name="persen_petani" id="edit_persen_petani" oninput="updateModalRatioSum('edit')" required class="reg-input">
                    </div>
                    <div>
                        <label for="edit_persen_primer" style="font-size:11px; color:#D97706; font-weight:700;">Primer (%)</label>
                        <input type="number" step="0.1" min="0" max="100" name="persen_primer" id="edit_persen_primer" oninput="updateModalRatioSum('edit')" required class="reg-input">
                    </div>
                    <div>
                        <label for="edit_persen_konsumen" style="font-size:11px; color:#2563EB; font-weight:700;">Konsumen (%)</label>
                        <input type="number" step="0.1" min="0" max="100" name="persen_konsumen" id="edit_persen_konsumen" oninput="updateModalRatioSum('edit')" required class="reg-input">
                    </div>
                </div>
            </div>

            <div>
                <label class="reg-label" for="edit_status_regulasi">Status Ketetapan</label>
                <select name="status_regulasi" id="edit_status_regulasi" class="reg-input">
                    <option value="Aktif">Aktif</option>
                    <option value="Arsip">Arsip</option>
                </select>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:4px;">
                <button type="button" onclick="closeModal('modalEditRegulasi')" style="background:#F1F5F9; color:#334155; border:1px solid #CBD5E1; padding:10px 16px; border-radius:10px; font-family:inherit; font-size:13px; font-weight:700; cursor:pointer;">Batal</button>
                <button type="submit" style="background:#D97706; color:#FFFFFF; border:none; padding:10px 18px; border-radius:10px; font-family:inherit; font-weight:700; font-size:13px; cursor:pointer;">
                    Simpan Perubahan Tarif
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
function openModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.add('show');
}
function closeModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.remove('show');
}

function updateModalRatioSum(prefix) {
    const p1 = parseFloat(document.getElementById(prefix === 'add' ? 'add_p_petani' : 'edit_persen_petani')?.value) || 0;
    const p2 = parseFloat(document.getElementById(prefix === 'add' ? 'add_p_primer' : 'edit_persen_primer')?.value) || 0;
    const p3 = parseFloat(document.getElementById(prefix === 'add' ? 'add_p_konsumen' : 'edit_persen_konsumen')?.value) || 0;
    const total = Math.round((p1 + p2 + p3) * 100) / 100;
    const badge = document.getElementById(prefix + '_ratio_sum');
    if (badge) {
        badge.textContent = 'Total: ' + total + '%';
        badge.style.color = (total === 100) ? '#059669' : '#DC2626';
    }
}

function openEditRegulasi(r) {
    document.getElementById('edit_modal_title').textContent   = 'Perbarui Tarif (' + r.kode_regulasi + ')';
    document.getElementById('edit_id_regulasi').value         = r.id_regulasi;
    document.getElementById('edit_nama_komoditas').value      = r.nama_komoditas;
    document.getElementById('edit_kategori').value            = r.kategori;
    document.getElementById('edit_satuan').value              = r.satuan;
    document.getElementById('edit_hap_petani').value          = r.hap_petani;
    document.getElementById('edit_het_buyer').value           = r.het_buyer;
    document.getElementById('edit_persen_petani').value       = r.persen_petani;
    document.getElementById('edit_persen_primer').value       = r.persen_primer;
    document.getElementById('edit_persen_konsumen').value     = r.persen_konsumen;
    document.getElementById('edit_status_regulasi').value     = r.status_regulasi;
    updateModalRatioSum('edit');
    openModal('modalEditRegulasi');
}

function formatRupiah(angka) {
    return 'Rp ' + Math.round(angka).toLocaleString('id-ID');
}

function loadSimulatorPreset(sel) {
    if (!sel.value) {
        document.getElementById('simHarga').value    = 15000;
        document.getElementById('simPetani').value   = 85;
        document.getElementById('simPrimer').value   = 10;
        document.getElementById('simKonsumen').value = 5;
    } else {
        try {
            const d = JSON.parse(sel.value);
            document.getElementById('simHarga').value    = d.het;
            document.getElementById('simPetani').value   = d.petani;
            document.getElementById('simPrimer').value   = d.primer;
            document.getElementById('simKonsumen').value = d.konsumen;
        } catch (e) {}
    }
    hitungSimulasi();
}

function hitungSimulasi() {
    const harga     = parseFloat(document.getElementById('simHarga').value) || 0;
    const pPetani   = parseFloat(document.getElementById('simPetani').value) || 0;
    const pPrimer   = parseFloat(document.getElementById('simPrimer').value) || 0;
    const pKonsumen = parseFloat(document.getElementById('simKonsumen').value) || 0;

    const totalPct = Math.round((pPetani + pPrimer + pKonsumen) * 100) / 100;
    const badge = document.getElementById('simTotalBadge');
    if (badge) {
        if (totalPct === 100) {
            badge.textContent = 'Total Rasio: 100%';
            badge.style.background = 'rgba(16,185,129,0.28)';
            badge.style.borderColor = 'rgba(110,231,183,0.5)';
        } else {
            badge.textContent = 'Total Rasio: ' + totalPct + '% (Harus 100%)';
            badge.style.background = 'rgba(220,38,38,0.45)';
            badge.style.borderColor = 'rgba(252,165,165,0.8)';
        }
    }

    document.getElementById('outPetani').textContent   = formatRupiah((harga * pPetani) / 100);
    document.getElementById('outPrimer').textContent   = formatRupiah((harga * pPrimer) / 100);
    document.getElementById('outKonsumen').textContent = formatRupiah((harga * pKonsumen) / 100);
}

// Filter & Pencarian Daftar Regulasi (Zero-Lag)
let currentRegStatus = 'ALL';

function filterRegulasi(status, btn) {
    currentRegStatus = status;
    document.querySelectorAll('#regFilterBar .reg-pill').forEach(function(el) {
        el.classList.remove('active');
    });
    if (btn) btn.classList.add('active');
    applyRegFilters();
}

function quickSelectRegFilter(status) {
    const btn = document.querySelector('#regFilterBar .reg-pill[data-filter="' + status + '"]');
    filterRegulasi(status, btn);
    const sec = document.getElementById('regTableSection');
    if (sec) sec.scrollIntoView({ behavior: 'smooth' });
}

function applyRegFilters() {
    const q = (document.getElementById('regSearchInput')?.value || '').toLowerCase().trim();
    const rows = document.querySelectorAll('.reg-data-row');
    const emptyRow = document.getElementById('regEmptyFilterRow');
    let visibleCount = 0;

    rows.forEach(function(row) {
        const st  = row.getAttribute('data-status');
        const txt = row.getAttribute('data-search') || '';
        const matchSt   = (currentRegStatus === 'ALL' || st === currentRegStatus);
        const matchText = !q || txt.indexOf(q) !== -1;
        const show = matchSt && matchText;
        row.style.display = show ? '' : 'none';
        if (show) visibleCount++;
    });

    if (emptyRow) {
        emptyRow.style.display = (rows.length > 0 && visibleCount === 0) ? '' : 'none';
    }
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal('modalTambahRegulasi');
        closeModal('modalEditRegulasi');
    }
});

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