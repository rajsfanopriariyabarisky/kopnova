<?php
require_once '../../config/database.php';

// FITUR INTERAKTIF: Ubah Status Pesanan Langsung dari Dashboard (Khusus Konsumen & Primer)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status_order'])) {
    if (in_array($_SESSION['user']['role'] ?? '', ['konsumen', 'primer'])) {
        $stmtUpd = $pdo->prepare("UPDATE orders SET status_order = ? WHERE id_order = ?");
        $stmtUpd->execute([$_POST['status_order'], (int)$_POST['id_order']]);
        header("Location: index.php?msg=" . urlencode("Status pesanan berhasil diubah menjadi: " . $_POST['status_order']));
        exit;
    }
}

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

// Pastikan variabel sesi terbaca eksplisit oleh editor & aman saat runtime
$user       = $user ?? ($_SESSION['user'] ?? []);
$role       = $role ?? ($user['role'] ?? '');
$cart_count = $cart_count ?? (isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0);

$error = $_GET['error'] ?? '';
$msg   = $_GET['msg'] ?? '';
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    :root {
        --kop-forest-950: #042F24;
        --kop-forest-900: #064E3B;
        --kop-emerald-800: #1B5E20;
        --kop-emerald-700: #2E7D32;
        --kop-emerald-600: #059669;
        --kop-emerald-500: #10B981;
        --kop-emerald-100: #DCFCE7;
        --kop-emerald-50: #F0FDF4;
        --kop-canvas: #FAFBF7;
        --kop-slate-900: #0F172A;
        --kop-slate-700: #334155;
        --kop-slate-600: #475569;
        --kop-slate-500: #64748B;
        --kop-border: #E2E8F0;
        --kop-shadow-sm: 0 3px 12px rgba(6, 78, 59, 0.04);
        --kop-shadow-md: 0 10px 26px rgba(6, 78, 59, 0.08);
        --kop-ease: 0.22s cubic-bezier(0.22, 1, 0.36, 1);
    }

    /* SINKRONISASI FONT PLUS JAKARTA SANS KE SELURUH WORKSPACE */
    body,
    .kop-dashboard-wrap,
    .kop-dashboard-wrap input,
    .kop-dashboard-wrap select,
    .kop-dashboard-wrap button,
    .kop-dashboard-wrap table,
    #mainSidebar {
        font-family: 'Plus Jakarta Sans', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif !important;
        -webkit-font-smoothing: antialiased;
    }

    body {
        background-color: var(--kop-canvas) !important;
        color: var(--kop-slate-900);
    }

    /* PERAPIAN SIDEBAR AGAR BERSIH & 1 BARIS */
    #mainSidebar {
        width: 264px !important;
    }
    #mainSidebar a {
        font-size: 13px !important;
        padding: 10px 14px !important;
        gap: 10px !important;
        white-space: nowrap !important;
        border-radius: 12px !important;
    }
    #mainSidebar a svg {
        flex-shrink: 0 !important;
        width: 17px !important;
        height: 17px !important;
    }

    .kop-dashboard-wrap {
        width: 100%;
        max-width: 1380px;
        margin: 0 auto;
        padding-bottom: 24px;
    }

    /* GRID SYSTEM */
    .kop-grid-4 {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 16px;
    }
    .kop-grid-3 {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 16px;
    }
    .kop-grid-2-1 {
        display: grid;
        grid-template-columns: minmax(0, 1.85fr) minmax(0, 1fr);
        gap: 20px;
        align-items: stretch;
    }
    .kop-grid-2 {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
    }

    /* SECTION HEADER & HELPER LABELS */
    .kop-section-head {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 12px;
    }
    .kop-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        color: var(--kop-emerald-700);
    }
    .kop-section-title {
        margin: 2px 0 0;
        font-size: 16.5px;
        font-weight: 800;
        color: var(--kop-slate-900);
        letter-spacing: -0.25px;
    }
    .kop-section-desc {
        margin: 2px 0 0;
        font-size: 12.5px;
        color: var(--kop-slate-500);
        line-height: 1.5;
    }

    /* HERO BANNER */
    .kop-hero-box {
        background: linear-gradient(120deg, var(--kop-forest-950) 0%, var(--kop-forest-900) 55%, var(--kop-emerald-700) 100%);
        border-radius: 22px;
        padding: 28px 32px;
        color: #FFFFFF;
        margin-bottom: 22px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 20px;
        box-shadow: 0 14px 32px rgba(6, 78, 59, 0.16);
        position: relative;
        overflow: hidden;
    }
    .kop-hero-orb {
        position: absolute;
        right: -40px;
        bottom: -60px;
        width: 220px;
        height: 220px;
        border-radius: 50%;
        background: rgba(16, 185, 129, 0.12);
        pointer-events: none;
    }
    .kop-hero-pill {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        background: rgba(255, 255, 255, 0.14);
        border: 1px solid rgba(255, 255, 255, 0.24);
        color: #ECFDF5;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 0.5px;
        padding: 5px 13px;
        border-radius: 99px;
        margin-bottom: 10px;
    }
    .kop-hero-title {
        margin: 0 0 6px;
        font-size: clamp(20px, 2.2vw, 24px);
        font-weight: 800;
        line-height: 1.25;
        letter-spacing: -0.4px;
    }
    .kop-hero-sub {
        margin: 0;
        font-size: 13.5px;
        color: #D1FAE5;
        line-height: 1.58;
        max-width: 620px;
    }

    /* ELEVATED CARDS */
    .kop-card {
        background: #FFFFFF;
        border-radius: 20px;
        border: 1px solid var(--kop-border);
        box-shadow: var(--kop-shadow-sm);
    }

    /* SHORTCUT ACTION CARDS */
    .kop-action-card {
        background: #FFFFFF;
        border-radius: 18px;
        border: 1px solid var(--kop-border);
        padding: 16px 18px;
        text-decoration: none;
        display: flex;
        align-items: flex-start;
        gap: 14px;
        box-shadow: var(--kop-shadow-sm);
        transition: transform var(--kop-ease), border-color var(--kop-ease), box-shadow var(--kop-ease), background-color var(--kop-ease);
    }
    .kop-action-card:hover {
        transform: translateY(-3px);
        border-color: var(--kop-emerald-500);
        background: var(--kop-emerald-50);
        box-shadow: var(--kop-shadow-md);
    }
    .kop-action-tag {
        display: inline-block;
        font-size: 10px;
        font-weight: 800;
        letter-spacing: 0.4px;
        text-transform: uppercase;
        padding: 2px 8px;
        border-radius: 99px;
        margin-bottom: 4px;
    }

    /* KPI CARDS */
    .kop-kpi-card {
        background: #FFFFFF;
        border-radius: 20px;
        border: 1px solid var(--kop-border);
        padding: 20px 22px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        box-shadow: var(--kop-shadow-sm);
        position: relative;
        overflow: hidden;
        transition: transform var(--kop-ease), box-shadow var(--kop-ease), border-color var(--kop-ease);
    }
    .kop-kpi-card:hover {
        transform: translateY(-3px);
        box-shadow: var(--kop-shadow-md);
        border-color: #BBF7D0;
    }
    .kop-kpi-value {
        font-size: clamp(1.25rem, 1.65vw, 1.55rem);
        font-weight: 800;
        color: var(--kop-slate-900);
        letter-spacing: -0.5px;
        line-height: 1.2;
        margin: 10px 0 6px;
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
    }

    /* PIPELINE STEPS (ALUR KERJA VISUAL) */
    .kop-pipeline-step {
        background: var(--kop-canvas);
        border: 1px solid var(--kop-border);
        border-radius: 16px;
        padding: 16px 18px;
        position: relative;
        transition: transform var(--kop-ease), border-color var(--kop-ease);
    }
    .kop-pipeline-step:hover {
        transform: translateY(-2px);
        border-color: #A7F3D0;
    }
    .kop-step-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 10.5px;
        font-weight: 800;
        padding: 4px 10px;
        border-radius: 99px;
        margin-bottom: 8px;
    }

    /* BUTTONS & FILTER PILLS */
    .kop-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        text-decoration: none;
        white-space: nowrap;
        transition: transform var(--kop-ease), filter var(--kop-ease), box-shadow var(--kop-ease);
        cursor: pointer;
    }
    .kop-btn:hover {
        filter: brightness(1.04);
        transform: translateY(-2px);
    }
    .kop-btn:active {
        transform: translateY(0) scale(0.98);
    }

    .kop-pill {
        padding: 7px 14px;
        border-radius: 99px;
        font-size: 12px;
        font-weight: 700;
        border: 1px solid var(--kop-border);
        background: #F8FAFC;
        color: var(--kop-slate-600);
        cursor: pointer;
        transition: background-color var(--kop-ease), color var(--kop-ease), border-color var(--kop-ease);
        white-space: nowrap;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .kop-pill:hover {
        border-color: var(--kop-emerald-500);
        color: var(--kop-forest-900);
    }
    .kop-pill.active {
        background: var(--kop-emerald-700);
        color: #FFFFFF;
        border-color: var(--kop-emerald-700);
        box-shadow: 0 4px 12px rgba(46, 125, 50, 0.22);
    }
    .kop-pill-count {
        font-size: 10.5px;
        padding: 1px 7px;
        border-radius: 99px;
        background: rgba(15, 23, 42, 0.08);
    }
    .kop-pill.active .kop-pill-count {
        background: rgba(255, 255, 255, 0.22);
        color: #FFFFFF;
    }

    .kop-row-hover {
        transition: background-color 0.16s ease;
    }
    .kop-row-hover:hover {
        background-color: #F8FAFC;
    }

    .mobile-nav-trigger {
        display: none;
    }

    .kop-search-box {
        padding: 9px 14px;
        border: 1px solid #CBD5E1;
        border-radius: 12px;
        font-size: 12.5px;
        min-width: 220px;
        outline: none;
        background: #FAFBF7;
        transition: border-color var(--kop-ease), background-color var(--kop-ease), box-shadow var(--kop-ease);
    }
    .kop-search-box:focus {
        border-color: var(--kop-emerald-500);
        background: #FFFFFF;
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.14);
    }

    /* ZERO-LAG BACKDROP */
    #kopBackdrop {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(4, 47, 36, 0.52);
        z-index: 45;
    }

    /* RESPONSIVE LAPTOP SEDANG / TABLET */
    @media (max-width: 1220px) {
        .kop-grid-4 {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .kop-grid-2-1 {
            grid-template-columns: minmax(0, 1fr);
        }
    }

    /* MENU DRAWER UNTUK HP & TABLET (< 960px) */
    @media (max-width: 960px) {
        #mainSidebar {
            position: fixed !important;
            left: -280px;
            top: 0;
            bottom: 0;
            transition: left 0.26s cubic-bezier(0.22, 1, 0.36, 1);
            box-shadow: 12px 0 32px rgba(0, 0, 0, 0.28);
            z-index: 60 !important;
        }
        #mainSidebar.open {
            left: 0 !important;
        }
        .mobile-nav-trigger {
            display: inline-flex !important;
            align-items: center;
            gap: 8px;
            background: var(--kop-forest-900);
            color: #FFFFFF;
            border: none;
            padding: 10px 16px;
            border-radius: 99px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            margin-bottom: 14px;
            box-shadow: 0 6px 16px rgba(6, 78, 59, 0.2);
        }
        .kop-grid-3 {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    /* TABEL OTOMATIS JADI KARTU DI HP (TANPA SCROLL SAMPING) */
    @media (max-width: 768px) {
        .kop-responsive-table thead {
            display: none;
        }
        .kop-responsive-table,
        .kop-responsive-table tbody,
        .kop-responsive-table tr,
        .kop-responsive-table td {
            display: block;
            width: 100%;
            box-sizing: border-box;
        }
        .kop-responsive-table tr.kop-mobile-card-row {
            background: #FFFFFF;
            border: 1px solid var(--kop-border) !important;
            border-radius: 16px;
            padding: 14px 16px;
            margin-bottom: 12px;
            box-shadow: var(--kop-shadow-sm);
        }
        .kop-responsive-table td {
            padding: 6px 0 !important;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            border: none !important;
            white-space: normal !important;
            text-align: right;
        }
        .kop-responsive-table td::before {
            content: attr(data-label);
            font-size: 11.5px;
            font-weight: 700;
            color: var(--kop-slate-500);
            text-align: left;
            flex-shrink: 0;
        }
        .kop-responsive-table td.td-top-head {
            border-bottom: 1px dashed var(--kop-border) !important;
            padding-bottom: 10px !important;
            margin-bottom: 4px;
        }
        .kop-responsive-table td.td-full-action {
            border-top: 1px solid #F1F5F9 !important;
            padding-top: 10px !important;
            margin-top: 6px;
            flex-direction: column;
            align-items: stretch;
        }
        .kop-responsive-table td.td-full-action::before {
            margin-bottom: 5px;
            content: "Ubah Status Pesanan:";
        }
        .kop-responsive-table td.td-full-action form,
        .kop-responsive-table td.td-full-action select {
            width: 100%;
            padding: 9px 12px !important;
            font-size: 13px !important;
        }
    }

    /* LAYAR HP KECIL (< 640px) */
    @media (max-width: 640px) {
        .kop-grid-4, .kop-grid-3, .kop-grid-2 {
            grid-template-columns: minmax(0, 1fr);
        }
        .kop-hero-box {
            padding: 22px 20px !important;
            border-radius: 18px;
        }
        .kop-hero-actions {
            width: 100%;
        }
        .kop-hero-actions a {
            flex: 1 1 100%;
        }
        .kop-search-box {
            width: 100%;
            min-width: 0;
        }
    }
</style>

<div class="kop-dashboard-wrap">

    <!-- TOMBOL BUKA MENU UNTUK LAYAR HP -->
    <div>
        <button type="button" class="mobile-nav-trigger" onclick="toggleKopSidebar()">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            <span>Menu Navigasi</span>
        </button>
    </div>
    <div id="kopBackdrop" onclick="toggleKopSidebar()"></div>

    <?php if ($error): ?>
    <div style="background:#FEF2F2; border:1px solid #FECACA; border-left:5px solid #DC2626; color:#991B1B; padding:14px 18px; border-radius:14px; margin-bottom:18px; font-weight:700; font-size:13.5px; display:flex; align-items:center; gap:10px;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" style="flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <span><?= htmlspecialchars($error) ?></span>
    </div>
    <?php endif; ?>

    <?php if ($msg): ?>
    <div style="background:#ECFDF5; border:1px solid #A7F3D0; border-left:5px solid #059669; color:#065F46; padding:14px 18px; border-radius:14px; margin-bottom:18px; font-weight:700; font-size:13.5px; display:flex; align-items:center; gap:10px;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex-shrink:0;"><polyline points="20 6 9 17 4 12"/></svg>
        <span><?= htmlspecialchars($msg) ?></span>
    </div>
    <?php endif; ?>

    <?php if ($role === 'konsumen'): ?>
        <?php
        // Ambil Ringkasan Data Utama
        $jml_divisi    = (int)$pdo->query("SELECT COUNT(*) FROM divisi_wilayah")->fetchColumn();
        $jml_primer    = (int)$pdo->query("SELECT COUNT(*) FROM koperasi_primer")->fetchColumn();
        $jml_petani    = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'petani'")->fetchColumn();
        $jml_pasokan   = (int)$pdo->query("SELECT COUNT(*) FROM pasokan_petani")->fetchColumn();
        $jml_produk    = (int)$pdo->query("SELECT COUNT(*) FROM produk_etalase")->fetchColumn();
        $total_stok    = (int)$pdo->query("SELECT COALESCE(SUM(stok),0) FROM produk_etalase")->fetchColumn();
        $total_order   = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
        $total_omzet   = (float)$pdo->query("SELECT COALESCE(SUM(total_bayar),0) FROM orders")->fetchColumn();

        // Deteksi Produk yang Stoknya Tinggal Sedikit (< 50)
        $ews_items = $pdo->query("SELECT pr.*, kp.nama_koperasi, kp.kecamatan,
                                  COALESCE((SELECT SUM(oi.qty) FROM order_items oi WHERE oi.id_produk = pr.id_produk), 0) AS total_terjual
                                  FROM produk_etalase pr
                                  JOIN koperasi_primer kp ON pr.id_primer = kp.id_primer
                                  WHERE pr.stok < 50
                                  ORDER BY pr.stok ASC LIMIT 4")->fetchAll();

        // Daftar Pesanan Pembeli Terbaru
        $orders = $pdo->query("SELECT o.*, u.nama_lengkap as nama_buyer 
                               FROM orders o 
                               JOIN users u ON o.id_buyer = u.id_user 
                               ORDER BY o.id_order DESC LIMIT 10")->fetchAll();

        // Hitung jumlah per status untuk badge filter
        $count_status = [
            'ALL' => count($orders),
            'Menunggu Konfirmasi' => 0,
            'Diproses Koperasi' => 0,
            'Dikirim' => 0,
            'Selesai' => 0
        ];
        foreach ($orders as $ord_c) {
            if (isset($count_status[$ord_c['status_order']])) {
                $count_status[$ord_c['status_order']]++;
            }
        }

        // Daftar Setoran Panen Terbaru dari Petani
        $pasokan_recent = $pdo->query("SELECT ps.*, u.nama_lengkap as nama_petani, kp.nama_koperasi 
                                       FROM pasokan_petani ps 
                                       JOIN users u ON ps.id_petani = u.id_user 
                                       JOIN koperasi_primer kp ON ps.id_primer = kp.id_primer 
                                       ORDER BY ps.id_pasokan DESC LIMIT 5")->fetchAll();

        // Data Grafik 1: Stok Barang per Cabang
        $primer_chart = $pdo->query("SELECT kp.nama_koperasi, COALESCE(SUM(pr.stok),0) as total_stok, COUNT(pr.id_produk) as jml_produk 
                                     FROM koperasi_primer kp 
                                     LEFT JOIN produk_etalase pr ON kp.id_primer = pr.id_primer 
                                     GROUP BY kp.id_primer")->fetchAll();
        $labels_primer = [];
        $data_stok_primer = [];
        $data_item_primer = [];
        foreach ($primer_chart as $pc) {
            $labels_primer[] = str_replace('Koperasi Primer ', '', $pc['nama_koperasi']);
            $data_stok_primer[] = (int)$pc['total_stok'];
            $data_item_primer[] = (int)$pc['jml_produk'];
        }

        // Data Grafik 2: Pembagian Jenis Produk
        $kat_stats = $pdo->query("SELECT kategori, COALESCE(SUM(stok),0) as tot_stok FROM produk_etalase GROUP BY kategori")->fetchAll();
        $labels_kat = [];
        $data_kat = [];
        foreach ($kat_stats as $ks) {
            $labels_kat[] = $ks['kategori'];
            $data_kat[] = (int)$ks['tot_stok'];
        }
        if (empty($labels_kat)) {
            $labels_kat = ['Komoditas Tanaman', 'Saprodi', 'Alsintan', 'Produk Olahan'];
            $data_kat = [0, 0, 0, 0];
        }
        ?>

        <!-- 1. BANNER UTAMA PUSAT KENDALI -->
        <div class="kop-hero-box">
            <div class="kop-hero-orb"></div>
            <div style="position:relative; z-index:2;">
                <div class="kop-hero-pill">
                    <span style="width:7px; height:7px; border-radius:50%; background:#4ADE80; display:inline-block;"></span>
                    <span>RUANG KERJA KOPERASI KONSUMEN (PUSAT)</span>
                </div>
                <h2 class="kop-hero-title">
                    Pusat Kendali Rantai Pasok &amp; Distribusi Nasional
                </h2>
                <p class="kop-hero-sub">
                    Halaman ini berfungsi memantau stok hasil panen dari setiap wilayah, memproses pesanan masuk dari Buyer, mengatur harga acuan SHU, serta mencairkan dana ke Koperasi Primer dan Petani.
                </p>
            </div>

            <div class="kop-hero-actions" style="display:flex; gap:10px; flex-wrap:wrap; position:relative; z-index:2;">
                <a href="../wilayah/index.php" class="kop-btn" style="background:#10B981; color:#042F24; padding:12px 19px; border-radius:99px; font-weight:800; font-size:13px; box-shadow:0 8px 20px rgba(16,185,129,0.3);">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    <span>Kelola Wilayah &amp; Cabang</span>
                </a>
                <a href="../early_warning/index.php" class="kop-btn" style="background:rgba(255,255,255,0.14); border:1px solid rgba(255,255,255,0.32); color:#FFFFFF; padding:12px 19px; border-radius:99px; font-weight:700; font-size:13px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                    <span>Peringatan Stok (<?= count($ews_items) ?>)</span>
                </a>
            </div>
        </div>

        <!-- 2. PINTASAN TUGAS UTAMA -->
        <div style="margin-bottom:22px;">
            <div class="kop-section-head">
                <div>
                    <span class="kop-eyebrow">Akses Cepat Tugas Utama</span>
                    <h3 class="kop-section-title">Pilih Tindakan Operasional</h3>
                </div>
                <span class="kop-section-desc">Klik salah satu kartu di bawah untuk langsung menuju modul terkait</span>
            </div>

            <div class="kop-grid-4">
                <a href="../order_routing/index.php" class="kop-action-card">
                    <div style="width:42px; height:42px; border-radius:13px; background:#DCFCE7; color:#166534; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="1" y="3" width="15" height="13" rx="2"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                    </div>
                    <div style="min-width:0; flex:1;">
                        <span class="kop-action-tag" style="background:#DCFCE7; color:#166534;">Logistik &amp; Kirim</span>
                        <div style="font-size:14px; font-weight:800; color:#0F172A;">Atur Pengiriman</div>
                        <div style="font-size:12px; color:#64748B; margin-top:2px; line-height:1.45;">Teruskan pesanan ke cabang &amp; terbitkan surat jalan.</div>
                    </div>
                </a>

                <a href="../settlement/index.php" class="kop-action-card">
                    <div style="width:42px; height:42px; border-radius:13px; background:#DBEAFE; color:#1E40AF; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/></svg>
                    </div>
                    <div style="min-width:0; flex:1;">
                        <span class="kop-action-tag" style="background:#DBEAFE; color:#1E40AF;">Keuangan &amp; SHU</span>
                        <div style="font-size:14px; font-weight:800; color:#0F172A;">Bagi Hasil Dana</div>
                        <div style="font-size:12px; color:#64748B; margin-top:2px; line-height:1.45;">Cairkan pembayaran hasil panen ke primer &amp; petani.</div>
                    </div>
                </a>

                <a href="../regulasi_harga/index.php" class="kop-action-card">
                    <div style="width:42px; height:42px; border-radius:13px; background:#FEF3C7; color:#92400E; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    </div>
                    <div style="min-width:0; flex:1;">
                        <span class="kop-action-tag" style="background:#FEF3C7; color:#92400E;">Standar Harga</span>
                        <div style="font-size:14px; font-weight:800; color:#0F172A;">Regulasi HAP &amp; HET</div>
                        <div style="font-size:12px; color:#64748B; margin-top:2px; line-height:1.45;">Tetapkan harga minimum petani &amp; persentase margin.</div>
                    </div>
                </a>

                <a href="../laporan_eksekutif/index.php" class="kop-action-card">
                    <div style="width:42px; height:42px; border-radius:13px; background:#F3E8FF; color:#6B21A8; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    </div>
                    <div style="min-width:0; flex:1;">
                        <span class="kop-action-tag" style="background:#F3E8FF; color:#6B21A8;">Audit &amp; Rekap</span>
                        <div style="font-size:14px; font-weight:800; color:#0F172A;">Laporan Eksekutif</div>
                        <div style="font-size:12px; color:#64748B; margin-top:2px; line-height:1.45;">Ekspor rekap transaksi &amp; audit ke Excel, CSV, atau PDF.</div>
                    </div>
                </a>
            </div>
        </div>

        <!-- 3. PERINGATAN STOK MENIPIS (EARLY WARNING SYSTEM) -->
        <?php if (!empty($ews_items)): ?>
        <div style="background:#FFFBEB; border:1px solid #FDE68A; border-left:5px solid #F59E0B; border-radius:18px; padding:18px 22px; margin-bottom:22px; box-shadow:var(--kop-shadow-sm);">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:14px;">
                <div>
                    <div style="display:flex; align-items:center; gap:9px; flex-wrap:wrap;">
                        <span style="display:inline-flex; align-items:center; gap:5px; background:#DC2626; color:#FFFFFF; font-size:10.5px; font-weight:800; padding:4px 11px; border-radius:99px; letter-spacing:0.3px;">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                            <span>EARLY WARNING STOK</span>
                        </span>
                        <strong style="font-size:14.5px; color:#92400E;">
                            Perhatian: <?= count($ews_items) ?> komoditas memiliki stok di bawah 50 unit
                        </strong>
                    </div>
                    <div style="font-size:12px; color:#78350F; margin-top:3px;">
                        Segera kirim instruksi pengumpulan panen ke koperasi cabang terkait agar etalase tidak kehabisan barang.
                    </div>
                </div>
                <a href="../early_warning/index.php" class="kop-btn" style="background:#DC2626; color:#FFFFFF; padding:9px 16px; border-radius:99px; font-size:12px; font-weight:700;">
                    <span>Kirim Instruksi Restock</span>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                </a>
            </div>

            <div class="kop-grid-2">
                <?php foreach ($ews_items as $ew): ?>
                <div style="background:#FFFFFF; border:1px solid #FDE68A; border-radius:14px; padding:12px 16px; display:flex; justify-content:space-between; align-items:center; gap:12px;">
                    <div style="min-width:0;">
                        <div style="font-size:13.5px; font-weight:800; color:#0F172A; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars($ew['nama_produk']) ?></div>
                        <div style="font-size:11.5px; color:#64748B; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-top:2px;">Cabang: <?= htmlspecialchars($ew['nama_koperasi']) ?> • Terjual <?= (int)$ew['total_terjual'] ?> <?= htmlspecialchars($ew['satuan']) ?></div>
                    </div>
                    <div style="text-align:right; flex-shrink:0; background:#FEF2F2; border:1px solid #FECACA; padding:6px 12px; border-radius:10px;">
                        <div style="font-size:13px; font-weight:800; color:#DC2626; white-space:nowrap;">Sisa <?= (int)$ew['stok'] ?> <?= htmlspecialchars($ew['satuan']) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- 4. RINGKASAN ANGKA UTAMA (KPI) -->
        <div style="margin-bottom:22px;">
            <div class="kop-section-head">
                <div>
                    <span class="kop-eyebrow">Ringkasan Kinerja Ekosistem</span>
                    <h3 class="kop-section-title">Statistik Utama Saat Ini</h3>
                </div>
                <span class="kop-section-desc">Data dihitung langsung dari seluruh transaksi dan stok aktif</span>
            </div>

            <div class="kop-grid-4">
                <div class="kop-kpi-card">
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                        <span style="font-size:12.5px; color:#475569; font-weight:700;">Cakupan Wilayah</span>
                        <div style="width:36px; height:36px; border-radius:11px; background:#DCFCE7; color:#166534; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                        </div>
                    </div>
                    <div class="kop-kpi-value"><?= $jml_divisi ?> Kab/Kota</div>
                    <div style="font-size:12px; color:#1B5E20; font-weight:600;">Menaungi <?= $jml_primer ?> koperasi primer aktif</div>
                </div>

                <div class="kop-kpi-card">
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                        <span style="font-size:12.5px; color:#475569; font-weight:700;">Ketersediaan Stok</span>
                        <div style="width:36px; height:36px; border-radius:11px; background:#DBEAFE; color:#1E40AF; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                        </div>
                    </div>
                    <div class="kop-kpi-value"><?= number_format($total_stok, 0, ',', '.') ?> Unit</div>
                    <div style="font-size:12px; color:#1E40AF; font-weight:600;">Tersebar di <?= $jml_produk ?> katalog etalase</div>
                </div>

                <div class="kop-kpi-card">
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                        <span style="font-size:12.5px; color:#475569; font-weight:700;">Total Transaksi Buyer</span>
                        <div style="width:36px; height:36px; border-radius:11px; background:#FEF3C7; color:#92400E; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                        </div>
                    </div>
                    <div class="kop-kpi-value"><?= $total_order ?> Pesanan</div>
                    <div style="font-size:12px; color:#92400E; font-weight:600;">Order masuk dari pembeli mitra</div>
                </div>

                <div class="kop-kpi-card">
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                        <span style="font-size:12.5px; color:#475569; font-weight:700;">Akumulasi Nilai Transaksi</span>
                        <div style="width:36px; height:36px; border-radius:11px; background:#F3E8FF; color:#6B21A8; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                        </div>
                    </div>
                    <div class="kop-kpi-value" style="color:#064E3B;"><?= rupiah($total_omzet) ?></div>
                    <div style="font-size:12px; color:#6B21A8; font-weight:600;">Bruto sebelum pembagian SHU</div>
                </div>
            </div>
        </div>

        <!-- 5. GRAFIK ANALISIS STOK & KATEGORI -->
        <div class="kop-grid-2-1" style="margin-bottom:22px;">
            <div class="kop-card" style="padding:22px 24px; display:flex; flex-direction:column; justify-content:space-between;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:14px; flex-wrap:wrap; gap:8px;">
                    <div>
                        <span class="kop-eyebrow">Grafik Distribusi Cabang</span>
                        <h3 style="margin:2px 0 0; font-size:16px; font-weight:800; color:#0F172A;">Perbandingan Stok per Koperasi Primer</h3>
                        <div style="font-size:12.5px; color:#64748B; margin-top:2px;">Memudahkan pusat melihat cabang kecamatan dengan pasokan terbanyak vs yang perlu dibantu.</div>
                    </div>
                    <span style="display:inline-flex; align-items:center; gap:6px; background:#DCFCE7; color:#166534; font-size:11px; font-weight:800; padding:4px 11px; border-radius:99px;">
                        <span style="width:6px; height:6px; border-radius:50%; background:#16A34A;"></span>
                        <span>REAL-TIME</span>
                    </span>
                </div>
                <div style="position:relative; height:245px; width:100%;">
                    <canvas id="chartPrimer"></canvas>
                </div>
            </div>

            <div class="kop-card" style="padding:22px 24px; display:flex; flex-direction:column; justify-content:space-between;">
                <div>
                    <span class="kop-eyebrow">Komposisi Komoditas</span>
                    <h3 style="margin:2px 0 0; font-size:16px; font-weight:800; color:#0F172A;">Proporsi Stok per Kategori</h3>
                    <div style="font-size:12.5px; color:#64748B; margin-top:2px; margin-bottom:12px;">Menunjukkan sebaran stok antara tanaman segar, olahan, saprodi, dan alsintan.</div>
                </div>
                <div style="position:relative; height:245px; width:100%; display:flex; align-items:center; justify-content:center;">
                    <canvas id="chartKategori"></canvas>
                </div>
            </div>
        </div>

        <!-- 6. ALUR KERJA RANTAI PASOK (HULU KE HILIR) -->
        <div class="kop-card" style="padding:22px 24px; margin-bottom:22px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:8px;">
                <div>
                    <span class="kop-eyebrow">Peta Alur Sistem</span>
                    <h3 style="margin:2px 0 0; font-size:16px; font-weight:800; color:#0F172A;">Bagaimana Barang Bergerak dari Petani ke Pembeli</h3>
                </div>
                <span style="font-size:12px; color:#64748B; font-weight:600; background:#F1F5F9; padding:5px 12px; border-radius:99px;">4 Tahap Terintegrasi</span>
            </div>

            <div class="kop-grid-4">
                <div class="kop-pipeline-step" style="border-top:3px solid #10B981;">
                    <span class="kop-step-badge" style="background:#DCFCE7; color:#166534;">TAHAP 01 • HULU</span>
                    <div style="font-size:14.5px; font-weight:800; color:#0F172A;">Petani Setor Panen</div>
                    <div style="font-size:18px; font-weight:800; color:#1B5E20; margin:6px 0 3px;"><?= $jml_pasokan ?> Setoran</div>
                    <div style="font-size:12px; color:#64748B; line-height:1.45;">Dikirim oleh <?= $jml_petani ?> petani mitra terdaftar ke koperasi kecamatan.</div>
                </div>

                <div class="kop-pipeline-step" style="border-top:3px solid #3B82F6;">
                    <span class="kop-step-badge" style="background:#DBEAFE; color:#1E40AF;">TAHAP 02 • SORTIR</span>
                    <div style="font-size:14.5px; font-weight:800; color:#0F172A;">Cabang Primer Verifikasi</div>
                    <div style="font-size:18px; font-weight:800; color:#1E40AF; margin:6px 0 3px;"><?= $jml_primer ?> Koperasi</div>
                    <div style="font-size:12px; color:#64748B; line-height:1.45;">Mengecek mutu panen, menimbang stok, lalu menayangkan ke etalase.</div>
                </div>

                <div class="kop-pipeline-step" style="border-top:3px solid #F59E0B;">
                    <span class="kop-step-badge" style="background:#FEF3C7; color:#92400E;">TAHAP 03 • ETALASE</span>
                    <div style="font-size:14.5px; font-weight:800; color:#0F172A;">Katalog Pusat Siap Jual</div>
                    <div style="font-size:18px; font-weight:800; color:#B45309; margin:6px 0 3px;"><?= $jml_produk ?> Komoditas</div>
                    <div style="font-size:12px; color:#64748B; line-height:1.45;">Produk terstandar harga HET siap dipesan dalam skala ritel maupun B2B.</div>
                </div>

                <div class="kop-pipeline-step" style="border-top:3px solid #8B5CF6;">
                    <span class="kop-step-badge" style="background:#F3E8FF; color:#6B21A8;">TAHAP 04 • HILIR</span>
                    <div style="font-size:14.5px; font-weight:800; color:#0F172A;">Pembeli Checkout &amp; Kirim</div>
                    <div style="font-size:18px; font-weight:800; color:#6B21A8; margin:6px 0 3px;"><?= $total_order ?> Pesanan</div>
                    <div style="font-size:12px; color:#64748B; line-height:1.45;">Order diproses, surat jalan diterbitkan, dan dana SHU dibagikan.</div>
                </div>
            </div>
        </div>

        <!-- 7. AREA OPERASIONAL: PESANAN TERBARU & SETORAN PANEN TERBARU -->
        <div class="kop-grid-2-1">
            <!-- KOLOM KIRI: PESANAN PEMBELI TERBARU -->
            <div class="kop-card" style="padding:22px 24px;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:14px; flex-wrap:wrap; gap:10px;">
                    <div>
                        <span class="kop-eyebrow">Kontrol Transaksi</span>
                        <h3 style="margin:2px 0 0; font-size:16.5px; font-weight:800; color:#0F172A;">Pesanan Pembeli Terbaru</h3>
                        <div style="font-size:12.5px; color:#64748B; margin-top:2px;">Pilih dropdown <strong>Status Pesanan</strong> untuk memperbarui tahap pengiriman barang ke Buyer.</div>
                    </div>
                    <a href="../transaksi/index.php" class="kop-btn" style="background:#ECFDF5; color:#1B5E20; border:1px solid #A7F3D0; padding:8px 15px; border-radius:99px; font-size:12px; font-weight:700;">
                        <span>Semua Transaksi</span>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                    </a>
                </div>

                <!-- TOMBOL FILTER STATUS + PENCARIAN -->
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:16px; padding-bottom:14px; border-bottom:1px solid #F1F5F9;">
                    <div style="display:flex; gap:6px; flex-wrap:wrap;" id="orderFilterBar">
                        <button type="button" class="kop-pill active" onclick="filterOrders('ALL', this)">Semua <span class="kop-pill-count"><?= $count_status['ALL'] ?></span></button>
                        <button type="button" class="kop-pill" onclick="filterOrders('Menunggu Konfirmasi', this)">Menunggu <span class="kop-pill-count"><?= $count_status['Menunggu Konfirmasi'] ?></span></button>
                        <button type="button" class="kop-pill" onclick="filterOrders('Diproses Koperasi', this)">Diproses <span class="kop-pill-count"><?= $count_status['Diproses Koperasi'] ?></span></button>
                        <button type="button" class="kop-pill" onclick="filterOrders('Dikirim', this)">Dikirim <span class="kop-pill-count"><?= $count_status['Dikirim'] ?></span></button>
                        <button type="button" class="kop-pill" onclick="filterOrders('Selesai', this)">Selesai <span class="kop-pill-count"><?= $count_status['Selesai'] ?></span></button>
                    </div>
                    <input type="text" id="searchOrderInput" onkeyup="searchOrders()" placeholder="Cari invoice atau nama buyer..." class="kop-search-box">
                </div>

                <div>
                    <table id="dashboardOrderTable" class="kop-responsive-table" style="width:100%; border-collapse:collapse; text-align:left; font-size:13px;">
                        <thead>
                            <tr style="background:#FAFBF7; border-bottom:2px solid #E2E8F0; color:#475569; font-size:11px; text-transform:uppercase; letter-spacing:0.5px;">
                                <th style="padding:11px 12px;">No. Invoice</th>
                                <th style="padding:11px 12px;">Pembeli</th>
                                <th style="padding:11px 12px;">Total Bayar</th>
                                <th style="padding:11px 12px;">Metode</th>
                                <th style="padding:11px 12px;">Status Pengiriman</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($orders)): ?>
                                <tr>
                                    <td colspan="5" style="padding:32px 16px; text-align:center;">
                                        <div style="font-weight:800; color:#334155; margin-bottom:4px;">Belum ada pesanan masuk</div>
                                        <div style="font-size:12.5px; color:#64748B;">Gunakan akun <strong>Buyer</strong> untuk mencoba membuat pesanan agar tampil di daftar ini.</div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            <?php foreach ($orders as $o): 
                                $st = $o['status_order'];
                                $sel_bg = '#FEF3C7'; $sel_col = '#92400E'; $sel_bdr = '#FDE68A';
                                if ($st === 'Diproses Koperasi') { $sel_bg = '#DBEAFE'; $sel_col = '#1E40AF'; $sel_bdr = '#BFDBFE'; }
                                elseif ($st === 'Dikirim')       { $sel_bg = '#E0E7FF'; $sel_col = '#3730A3'; $sel_bdr = '#C7D2FE'; }
                                elseif ($st === 'Selesai')       { $sel_bg = '#DCFCE7'; $sel_col = '#166534'; $sel_bdr = '#BBF7D0'; }
                            ?>
                            <tr class="kop-row-hover order-data-row kop-mobile-card-row" data-status="<?= htmlspecialchars($st) ?>" style="border-bottom:1px solid #F1F5F9;">
                                <td data-label="No. Invoice" class="td-top-head" style="padding:12px;">
                                    <div>
                                        <div style="font-weight:800; color:#064E3B; font-family:monospace; font-size:12.5px;"><?= htmlspecialchars($o['no_invoice']) ?></div>
                                        <div style="font-size:11px; color:#64748B; margin-top:2px;"><?= date('d M Y, H:i', strtotime($o['tanggal_order'])) ?></div>
                                    </div>
                                </td>
                                <td data-label="Pembeli" style="padding:12px; font-weight:700; color:#0F172A;"><?= htmlspecialchars($o['nama_buyer']) ?></td>
                                <td data-label="Total Bayar" style="padding:12px; font-weight:800; color:#064E3B; font-variant-numeric:tabular-nums;"><?= rupiah($o['total_bayar']) ?></td>
                                <td data-label="Metode" style="padding:12px;">
                                    <span style="background:#F1F5F9; color:#334155; padding:4px 10px; border-radius:8px; font-size:11px; font-weight:700; display:inline-block;">
                                        <?= htmlspecialchars($o['metode_bayar']) ?>
                                    </span>
                                </td>
                                <td data-label="Status Pengiriman" class="td-full-action" style="padding:12px;">
                                    <form method="POST" style="margin:0;">
                                        <input type="hidden" name="update_status_order" value="1">
                                        <input type="hidden" name="id_order" value="<?= $o['id_order'] ?>">
                                        <select name="status_order" onchange="this.form.submit()" style="padding:7px 11px; border-radius:10px; border:1px solid <?= $sel_bdr ?>; font-size:12px; font-weight:800; background:<?= $sel_bg ?>; color:<?= $sel_col ?>; cursor:pointer; outline:none;">
                                            <?php foreach (['Menunggu Konfirmasi', 'Diproses Koperasi', 'Dikirim', 'Selesai'] as $opt): ?>
                                                <option value="<?= $opt ?>" <?= $st === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- KOLOM KANAN: SETORAN PANEN TERBARU -->
            <div class="kop-card" style="padding:22px 24px; display:flex; flex-direction:column; justify-content:space-between;">
                <div>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2px;">
                        <span class="kop-eyebrow">Pasokan Hulu</span>
                        <span style="font-size:10.5px; font-weight:800; color:#1B5E20; background:#DCFCE7; padding:3px 10px; border-radius:99px;">Terbaru</span>
                    </div>
                    <h3 style="margin:0 0 4px; font-size:16px; font-weight:800; color:#0F172A;">Setoran Panen Petani</h3>
                    <p style="margin:0 0 14px; font-size:12.5px; color:#64748B;">Pantau komoditas yang baru disetorkan petani ke koperasi cabang.</p>

                    <?php if (empty($pasokan_recent)): ?>
                        <div style="font-size:12.5px; color:#64748B; background:#FAFBF7; padding:18px; border-radius:14px; border:1px dashed #CBD5E1; line-height:1.5;">
                            Belum ada setoran panen baru. Login menggunakan akun <strong>Petani</strong> untuk mengirim hasil panen.
                        </div>
                    <?php else: ?>
                        <div style="display:flex; flex-direction:column; gap:10px;">
                            <?php foreach ($pasokan_recent as $pr): 
                                $acc = $pr['status_pasokan'] === 'Diterima Primer';
                            ?>
                            <div style="padding:12px 14px; background:#FAFBF7; border:1px solid #E2E8F0; border-radius:14px; display:flex; justify-content:space-between; align-items:center; gap:10px;">
                                <div style="min-width:0;">
                                    <div style="font-size:13.5px; font-weight:800; color:#0F172A; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars($pr['nama_komoditas']) ?></div>
                                    <div style="font-size:11.5px; color:#64748B; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-top:2px;"><?= htmlspecialchars($pr['nama_petani']) ?> • <?= (int)$pr['jumlah'] ?> <?= htmlspecialchars($pr['satuan']) ?></div>
                                </div>
                                <span style="background:<?= $acc ? '#DCFCE7' : '#FEF3C7' ?>; color:<?= $acc ? '#166534' : '#92400E' ?>; font-size:11px; font-weight:800; padding:4px 10px; border-radius:99px; white-space:nowrap; flex-shrink:0;">
                                    <?= $acc ? 'Diterima' : 'Menunggu Cek' ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <a href="../pasokan/index.php" class="kop-btn" style="margin-top:16px; width:100%; box-sizing:border-box; padding:11px; border-radius:99px; background:#F0FDF4; border:1px solid #BBF7D0; color:#1B5E20; font-size:12.5px; font-weight:700;">
                    <span>Lihat Semua Riwayat Pasokan</span>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                </a>
            </div>
        </div>

        <script>
            Chart.defaults.font.family = "'Plus Jakarta Sans', 'Segoe UI', sans-serif";
            Chart.defaults.color = '#475569';

            // Grafik Batang: Stok Barang per Cabang
            const ctxPrimer = document.getElementById('chartPrimer').getContext('2d');
            new Chart(ctxPrimer, {
                type: 'bar',
                data: {
                    labels: <?= json_encode($labels_primer) ?>,
                    datasets: [
                        { label: 'Total Stok Barang', data: <?= json_encode($data_stok_primer) ?>, backgroundColor: '#10B981', borderRadius: 8, barPercentage: 0.46 },
                        { label: 'Jumlah Jenis Produk', data: <?= json_encode($data_item_primer) ?>, backgroundColor: '#F59E0B', borderRadius: 8, barPercentage: 0.46 }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: { duration: 450 },
                    plugins: { legend: { position: 'top', labels: { usePointStyle: true, font: { size: 12, weight: '700' } } } },
                    scales: { y: { beginAtZero: true, grid: { color: '#F1F5F9' } }, x: { grid: { display: false } } }
                }
            });

            // Grafik Donat: Jenis Produk Tersedia
            const ctxKat = document.getElementById('chartKategori').getContext('2d');
            new Chart(ctxKat, {
                type: 'doughnut',
                data: {
                    labels: <?= json_encode($labels_kat) ?>,
                    datasets: [{ data: <?= json_encode($data_kat) ?>, backgroundColor: ['#059669', '#3B82F6', '#F59E0B', '#8B5CF6', '#EC4899'], borderWidth: 2, borderColor: '#FFFFFF' }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: { duration: 450 },
                    cutout: '66%',
                    plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, padding: 12, font: { size: 11.5, weight: '600' } } } }
                }
            });

            // Filter & Pencarian Pesanan Pembeli
            let currentStatusFilter = 'ALL';
            function filterOrders(status, btn) {
                currentStatusFilter = status;
                document.querySelectorAll('#orderFilterBar .kop-pill').forEach(el => el.classList.remove('active'));
                btn.classList.add('active');
                applyOrderFilters();
            }
            function searchOrders() {
                applyOrderFilters();
            }
            function applyOrderFilters() {
                const q = document.getElementById('searchOrderInput').value.toLowerCase();
                document.querySelectorAll('.order-data-row').forEach(row => {
                    const st = row.getAttribute('data-status');
                    const txt = row.innerText.toLowerCase();
                    const matchStatus = (currentStatusFilter === 'ALL' || st === currentStatusFilter);
                    const matchText   = txt.includes(q);
                    row.style.display = (matchStatus && matchText) ? '' : 'none';
                });
            }
        </script>

    <?php elseif ($role === 'primer'): ?>
        <?php
        $id_prm = (int)($user['id_primer'] ?? 0);
        $where_prm = $id_prm > 0 ? " WHERE id_primer = $id_prm" : "";
        $pasokan_pending = (int)$pdo->query("SELECT COUNT(*) FROM pasokan_petani WHERE status_pasokan = 'Menunggu Verifikasi'" . ($id_prm > 0 ? " AND id_primer = $id_prm" : ""))->fetchColumn();
        $produk_tayang   = (int)$pdo->query("SELECT COUNT(*) FROM produk_etalase" . $where_prm)->fetchColumn();
        $total_stok_prm  = (int)$pdo->query("SELECT COALESCE(SUM(stok),0) FROM produk_etalase" . $where_prm)->fetchColumn();

        $list_pending = $pdo->query("SELECT ps.*, u.nama_lengkap AS nama_petani FROM pasokan_petani ps JOIN users u ON ps.id_petani = u.id_user WHERE ps.status_pasokan = 'Menunggu Verifikasi'" . ($id_prm > 0 ? " AND ps.id_primer = $id_prm" : "") . " ORDER BY ps.id_pasokan DESC LIMIT 5")->fetchAll();
        $list_ews_prm = [];
        try {
            $list_ews_prm = $pdo->query("SELECT * FROM instruksi_pasokan" . $where_prm . " ORDER BY id_instruksi DESC LIMIT 4")->fetchAll();
        } catch (Exception $e) {}
        ?>
        <!-- HERO BANNER PRIMER -->
        <div class="kop-hero-box">
            <div class="kop-hero-orb"></div>
            <div style="position:relative; z-index:2;">
                <div class="kop-hero-pill">
                    <span style="width:7px; height:7px; border-radius:50%; background:#4ADE80; display:inline-block;"></span>
                    <span>RUANG KERJA KOPERASI PRIMER KECAMATAN</span>
                </div>
                <h2 class="kop-hero-title"><?= htmlspecialchars($user['nama_koperasi'] ?? 'Koperasi Primer') ?></h2>
                <p class="kop-hero-sub">
                    Halaman ini digunakan untuk memverifikasi setoran panen petani Kecamatan <?= htmlspecialchars($user['kecamatan'] ?? '-') ?>, menayangkan stok ke katalog jual, dan menindaklanjuti permintaan pasokan dari pusat.
                </p>
            </div>
            <div class="kop-hero-actions" style="display:flex; gap:10px; flex-wrap:wrap; position:relative; z-index:2;">
                <a href="../pasokan/index.php" class="kop-btn" style="background:#10B981; color:#042F24; padding:12px 18px; border-radius:99px; font-weight:800; font-size:13px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    <span>Verifikasi Panen (<?= $pasokan_pending ?>)</span>
                </a>
                <a href="../produk/index.php" class="kop-btn" style="background:#FFFFFF; color:#064E3B; padding:12px 18px; border-radius:99px; font-weight:800; font-size:13px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    <span>Kelola Etalase (<?= $produk_tayang ?>)</span>
                </a>
                <a href="../settlement/index.php" class="kop-btn" style="background:rgba(255,255,255,0.14); border:1px solid rgba(255,255,255,0.32); color:#FFFFFF; padding:12px 18px; border-radius:99px; font-weight:700; font-size:13px;">
                    <span>Pencairan Dana</span>
                </a>
            </div>
        </div>

        <!-- RINGKASAN CABANG PRIMER -->
        <div class="kop-grid-3" style="margin-bottom:22px;">
            <div class="kop-kpi-card">
                <span style="font-size:12.5px; color:#475569; font-weight:700;">Menunggu Verifikasi</span>
                <div class="kop-kpi-value" style="color:#B45309;"><?= $pasokan_pending ?> Setoran</div>
                <div style="font-size:12px; color:#64748B;">Hasil panen petani perlu dicek mutunya</div>
            </div>
            <div class="kop-kpi-card">
                <span style="font-size:12.5px; color:#475569; font-weight:700;">Produk Tayang di Katalog</span>
                <div class="kop-kpi-value" style="color:#1B5E20;"><?= $produk_tayang ?> Jenis</div>
                <div style="font-size:12px; color:#64748B;">Komoditas cabang yang siap dibeli Buyer</div>
            </div>
            <div class="kop-kpi-card">
                <span style="font-size:12.5px; color:#475569; font-weight:700;">Total Stok Gudang Primer</span>
                <div class="kop-kpi-value"><?= number_format($total_stok_prm, 0, ',', '.') ?> Unit</div>
                <div style="font-size:12px; color:#64748B;">Akumulasi stok aktif siap distribusi</div>
            </div>
        </div>

        <div class="kop-grid-2-1">
            <div class="kop-card" style="padding:22px 24px;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:14px; gap:10px;">
                    <div>
                        <span class="kop-eyebrow">Tugas Prioritas</span>
                        <h3 style="margin:2px 0 0; font-size:16px; font-weight:800; color:#0F172A;">Setoran Petani Perlu Dicek</h3>
                        <div style="font-size:12.5px; color:#64748B;">Terima setoran yang lolos kualitas agar masuk ke stok koperasi.</div>
                    </div>
                    <a href="../pasokan/index.php" style="font-size:12.5px; font-weight:700; color:#1B5E20; text-decoration:none; white-space:nowrap;">Semua Pasokan →</a>
                </div>
                <?php if (empty($list_pending)): ?>
                    <div style="padding:26px; text-align:center; background:#FAFBF7; border-radius:14px; border:1px dashed #CBD5E1; color:#64748B; font-size:13px;">
                        Semua setoran panen petani sudah selesai diperiksa.
                    </div>
                <?php else: ?>
                    <div style="display:flex; flex-direction:column; gap:10px;">
                        <?php foreach ($list_pending as $lp): ?>
                        <div style="padding:14px 16px; background:#FAFBF7; border:1px solid #E2E8F0; border-radius:14px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                            <div>
                                <div style="font-weight:800; color:#0F172A; font-size:14px;"><?= htmlspecialchars($lp['nama_komoditas']) ?> <span style="color:#1B5E20;">(<?= (int)$lp['jumlah'] ?> <?= htmlspecialchars($lp['satuan']) ?>)</span></div>
                                <div style="font-size:12px; color:#64748B; margin-top:2px;">Petani: <?= htmlspecialchars($lp['nama_petani']) ?> • Nilai: <strong><?= rupiah($lp['total_nilai']) ?></strong></div>
                            </div>
                            <a href="../pasokan/index.php" class="kop-btn" style="background:#2E7D32; color:#FFFFFF; padding:8px 16px; border-radius:99px; font-size:12px; font-weight:700;">Cek &amp; Terima →</a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="kop-card" style="padding:22px 24px;">
                <span class="kop-eyebrow">Arahan Pusat</span>
                <h3 style="margin:2px 0 4px; font-size:16px; font-weight:800; color:#0F172A;">Instruksi Pengumpulan Stok</h3>
                <p style="margin:0 0 14px; font-size:12.5px; color:#64748B;">Permintaan restock dari Koperasi Konsumen saat stok menipis.</p>
                <?php if (empty($list_ews_prm)): ?>
                    <div style="font-size:12.5px; color:#64748B; background:#FAFBF7; padding:18px; border-radius:14px; border:1px dashed #CBD5E1;">Belum ada instruksi pasokan mendesak dari pusat.</div>
                <?php else: ?>
                    <div style="display:flex; flex-direction:column; gap:10px;">
                        <?php foreach ($list_ews_prm as $ep): ?>
                        <div style="padding:14px; background:#FFFBEB; border:1px solid #FDE68A; border-radius:14px;">
                            <div style="font-size:11px; font-weight:800; color:#B45309;"><?= htmlspecialchars($ep['kode_instruksi']) ?> • <?= htmlspecialchars($ep['tingkat_urgensi']) ?></div>
                            <div style="font-size:13.5px; font-weight:800; color:#0F172A; margin:4px 0;"><?= htmlspecialchars($ep['nama_komoditas']) ?> (Target: <?= (int)$ep['target_jumlah'] ?> <?= htmlspecialchars($ep['satuan']) ?>)</div>
                            <div style="font-size:12px; color:#475569; line-height:1.45;"><?= htmlspecialchars($ep['catatan_pusat']) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <?php elseif ($role === 'petani'): ?>
        <?php
        $stmtP = $pdo->prepare("SELECT COUNT(*) as tot, COALESCE(SUM(total_nilai),0) as nilai FROM pasokan_petani WHERE id_petani = ?");
        $stmtP->execute([$user['id_user'] ?? 0]);
        $statPetani = $stmtP->fetch();

        $stmtAcc = $pdo->prepare("SELECT COUNT(*) FROM pasokan_petani WHERE id_petani = ? AND status_pasokan = 'Diterima Primer'");
        $stmtAcc->execute([$user['id_user'] ?? 0]);
        $totDiterima = (int)$stmtAcc->fetchColumn();

        $stmtHist = $pdo->prepare("SELECT * FROM pasokan_petani WHERE id_petani = ? ORDER BY id_pasokan DESC LIMIT 5");
        $stmtHist->execute([$user['id_user'] ?? 0]);
        $hist_petani = $stmtHist->fetchAll();
        ?>
        <!-- HERO BANNER PETANI -->
        <div class="kop-hero-box">
            <div class="kop-hero-orb"></div>
            <div style="position:relative; z-index:2;">
                <div class="kop-hero-pill">
                    <span style="width:7px; height:7px; border-radius:50%; background:#4ADE80; display:inline-block;"></span>
                    <span>PORTAL PETANI PEMASOK HULU</span>
                </div>
                <h2 class="kop-hero-title">Halo, <?= htmlspecialchars($user['nama_lengkap'] ?? 'Petani Mitra') ?></h2>
                <p class="kop-hero-sub">
                    Gunakan halaman ini untuk mengajukan setoran hasil panen ke Koperasi Primer, memantau status verifikasi kualitas, dan mengecek pencairan dana hasil panen Anda.
                </p>
            </div>
            <div class="kop-hero-actions" style="display:flex; gap:10px; flex-wrap:wrap; position:relative; z-index:2;">
                <a href="../pasokan/index.php" class="kop-btn" style="background:#10B981; color:#042F24; padding:12px 20px; border-radius:99px; font-weight:800; font-size:13px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    <span>Setor Hasil Panen Baru</span>
                </a>
                <a href="../settlement/index.php" class="kop-btn" style="background:#FFFFFF; color:#064E3B; padding:12px 19px; border-radius:99px; font-weight:800; font-size:13px;">
                    <span>Cek Pencairan Dana</span>
                </a>
                <a href="../regulasi_harga/index.php" class="kop-btn" style="background:rgba(255,255,255,0.14); border:1px solid rgba(255,255,255,0.32); color:#FFFFFF; padding:12px 19px; border-radius:99px; font-weight:700; font-size:13px;">
                    <span>Acuan Harga HAP</span>
                </a>
            </div>
        </div>

        <!-- KPI PETANI -->
        <div class="kop-grid-3" style="margin-bottom:22px;">
            <div class="kop-kpi-card">
                <span style="font-size:12.5px; color:#475569; font-weight:700;">Total Pengajuan Panen</span>
                <div class="kop-kpi-value"><?= (int)($statPetani['tot'] ?? 0) ?> Kali Setor</div>
                <div style="font-size:12px; color:#64748B;">Riwayat setoran hasil bumi Anda</div>
            </div>
            <div class="kop-kpi-card">
                <span style="font-size:12.5px; color:#475569; font-weight:700;">Lolos Verifikasi Primer</span>
                <div class="kop-kpi-value" style="color:#1B5E20;"><?= $totDiterima ?> Setoran</div>
                <div style="font-size:12px; color:#64748B;">Diterima dan siap masuk etalase jual</div>
            </div>
            <div class="kop-kpi-card">
                <span style="font-size:12.5px; color:#475569; font-weight:700;">Akumulasi Nilai Panen</span>
                <div class="kop-kpi-value" style="color:#064E3B;"><?= rupiah($statPetani['nilai'] ?? 0) ?></div>
                <div style="font-size:12px; color:#64748B;">Total nilai transaksi panen tercatat</div>
            </div>
        </div>

        <div class="kop-card" style="padding:22px 24px;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:16px; flex-wrap:wrap; gap:10px;">
                <div>
                    <span class="kop-eyebrow">Riwayat Transaksi Hulu</span>
                    <h3 style="margin:2px 0 0; font-size:16.5px; font-weight:800; color:#0F172A;">Catatan Setoran Panen Terakhir</h3>
                    <div style="font-size:12.5px; color:#64748B;">Status akan berubah menjadi <strong>Diterima Primer</strong> setelah diperiksa petugas koperasi.</div>
                </div>
                <a href="../pasokan/index.php" class="kop-btn" style="background:#F0FDF4; color:#1B5E20; border:1px solid #BBF7D0; padding:8px 15px; border-radius:99px; font-size:12px; font-weight:700;">Lihat Semua →</a>
            </div>
            <?php if (empty($hist_petani)): ?>
                <div style="padding:26px; text-align:center; background:#FAFBF7; border-radius:14px; border:1px dashed #CBD5E1; color:#64748B; font-size:13px;">
                    Belum ada catatan setor panen. Klik tombol <strong>Setor Hasil Panen Baru</strong> di atas untuk mulai menyetor.
                </div>
            <?php else: ?>
                <table class="kop-responsive-table" style="width:100%; border-collapse:collapse; text-align:left; font-size:13px;">
                    <thead>
                        <tr style="background:#FAFBF7; border-bottom:2px solid #E2E8F0; color:#475569; font-size:11px; text-transform:uppercase; letter-spacing:0.5px;">
                            <th style="padding:11px 12px;">Kode Setoran</th>
                            <th style="padding:11px 12px;">Komoditas Panen</th>
                            <th style="padding:11px 12px;">Volume</th>
                            <th style="padding:11px 12px;">Total Nilai</th>
                            <th style="padding:11px 12px;">Status Verifikasi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($hist_petani as $hp): ?>
                        <tr class="kop-row-hover kop-mobile-card-row" style="border-bottom:1px solid #F1F5F9;">
                            <td data-label="Kode Setoran" class="td-top-head" style="padding:12px; font-family:monospace; font-weight:800; color:#064E3B;"><?= htmlspecialchars($hp['kode_pasokan']) ?></td>
                            <td data-label="Komoditas" style="padding:12px; font-weight:700;"><?= htmlspecialchars($hp['nama_komoditas']) ?></td>
                            <td data-label="Volume" style="padding:12px;"><?= (int)$hp['jumlah'] ?> <?= htmlspecialchars($hp['satuan']) ?></td>
                            <td data-label="Total Nilai" style="padding:12px; font-weight:800; color:#064E3B;"><?= rupiah($hp['total_nilai']) ?></td>
                            <td data-label="Status" style="padding:12px;">
                                <span style="background:<?= $hp['status_pasokan']=='Diterima Primer' ? '#DCFCE7' : '#FEF3C7' ?>; color:<?= $hp['status_pasokan']=='Diterima Primer' ? '#166534' : '#92400E' ?>; padding:4px 11px; border-radius:99px; font-size:11.5px; font-weight:800;">
                                    <?= htmlspecialchars($hp['status_pasokan']) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

    <?php elseif ($role === 'buyer'): ?>
        <?php
        $katalog_featured = $pdo->query("SELECT pr.*, kp.nama_koperasi, d.nama_divisi FROM produk_etalase pr JOIN koperasi_primer kp ON pr.id_primer = kp.id_primer JOIN divisi_wilayah d ON pr.id_divisi = d.id_divisi WHERE pr.stok > 0 ORDER BY pr.id_produk DESC LIMIT 4")->fetchAll();
        ?>
        <!-- HERO BANNER BUYER -->
        <div class="kop-hero-box">
            <div class="kop-hero-orb"></div>
            <div style="position:relative; z-index:2;">
                <div class="kop-hero-pill">
                    <span style="width:7px; height:7px; border-radius:50%; background:#4ADE80; display:inline-block;"></span>
                    <span>ETALASE BELANJA RESMI KOPNOVA</span>
                </div>
                <h2 class="kop-hero-title">Belanja Komoditas Segar Langsung dari Koperasi</h2>
                <p class="kop-hero-sub">
                    Pilih hasil panen terverifikasi dari berbagai koperasi kecamatan dalam satu keranjang belanja, lengkap dengan transparansi stok dan pengiriman terjadwal.
                </p>
            </div>
            <div class="kop-hero-actions" style="display:flex; gap:10px; flex-wrap:wrap; position:relative; z-index:2;">
                <a href="../marketplace/index.php" class="kop-btn" style="background:#10B981; color:#042F24; padding:12px 22px; border-radius:99px; font-weight:800; font-size:13px;">
                    <span>Buka Katalog Lengkap</span>
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                </a>
                <a href="../marketplace/keranjang.php" class="kop-btn" style="background:rgba(255,255,255,0.14); border:1px solid rgba(255,255,255,0.32); color:#FFFFFF; padding:12px 20px; border-radius:99px; font-weight:700; font-size:13px;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                    <span>Keranjang Belanja (<?= (int)$cart_count ?>)</span>
                </a>
            </div>
        </div>

        <div class="kop-card" style="padding:22px 24px;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:18px; flex-wrap:wrap; gap:10px;">
                <div>
                    <span class="kop-eyebrow">Rekomendasi Katalog</span>
                    <h3 style="margin:2px 0 0; font-size:16.5px; font-weight:800; color:#0F172A;">Komoditas Unggulan Siap Kirim</h3>
                    <div style="font-size:12.5px; color:#64748B;">Klik tombol <strong>Pesan</strong> untuk memasukkan produk ke keranjang atau melihat detail ukuran.</div>
                </div>
                <a href="../marketplace/index.php" class="kop-btn" style="background:#F0FDF4; color:#1B5E20; border:1px solid #BBF7D0; padding:8px 15px; border-radius:99px; font-size:12px; font-weight:700;">Lihat Semua Produk →</a>
            </div>
            <div class="kop-grid-4">
                <?php foreach ($katalog_featured as $kf): ?>
                <div style="border:1px solid #E2E8F0; border-radius:18px; padding:16px; background:#FAFBF7; display:flex; flex-direction:column; justify-content:space-between; transition:transform var(--kop-ease), border-color var(--kop-ease);">
                    <div>
                        <span style="font-size:10.5px; font-weight:800; color:#1B5E20; background:#DCFCE7; padding:3px 10px; border-radius:99px; display:inline-block;"><?= htmlspecialchars($kf['nama_divisi']) ?></span>
                        <div style="font-size:15px; font-weight:800; color:#0F172A; margin:9px 0 4px; line-height:1.3;"><?= htmlspecialchars($kf['nama_produk']) ?></div>
                        <div style="font-size:12px; color:#64748B;"><?= htmlspecialchars($kf['nama_koperasi']) ?></div>
                    </div>
                    <div style="margin-top:16px; padding-top:12px; border-top:1px solid #E2E8F0; display:flex; justify-content:space-between; align-items:center; gap:8px;">
                        <div style="min-width:0;">
                            <div style="font-size:15px; font-weight:800; color:#064E3B; white-space:nowrap;"><?= rupiah($kf['harga_jual']) ?></div>
                            <div style="font-size:11px; color:#64748B;">Stok: <?= (int)$kf['stok'] ?> <?= htmlspecialchars($kf['satuan']) ?></div>
                        </div>
                        <a href="../marketplace/index.php" class="kop-btn" style="background:#2E7D32; color:#FFFFFF; padding:8px 15px; border-radius:99px; font-size:12px; font-weight:700;">Pesan</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

</div>

<script>
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