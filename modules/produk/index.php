<?php
require_once '../../config/database.php';
cekAkses(['primer', 'konsumen']);
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

$current_user = $user ?? ($_SESSION['user'] ?? []);
$role         = $current_user['role'] ?? '';
$user_primer  = (int)($current_user['id_primer'] ?? 0);

// Ambil daftar unit koperasi primer & katalog produk sesuai role
if ($role === 'primer') {
    $stmtPrimer = $pdo->prepare("
        SELECT p.*, d.nama_divisi
        FROM koperasi_primer p
        JOIN divisi_wilayah d ON p.id_divisi = d.id_divisi
        WHERE p.id_primer = ?
    ");
    $stmtPrimer->execute([$user_primer]);
    $primer_list = $stmtPrimer->fetchAll();

    $stmtProduk = $pdo->prepare("
        SELECT pr.*, kp.nama_koperasi, d.nama_divisi
        FROM produk_etalase pr
        JOIN koperasi_primer kp ON pr.id_primer = kp.id_primer
        JOIN divisi_wilayah d ON pr.id_divisi = d.id_divisi
        WHERE pr.id_primer = ?
        ORDER BY pr.id_produk DESC
    ");
    $stmtProduk->execute([$user_primer]);
    $produk_list = $stmtProduk->fetchAll();
} else {
    $primer_list = $pdo->query("
        SELECT p.*, d.nama_divisi
        FROM koperasi_primer p
        JOIN divisi_wilayah d ON p.id_divisi = d.id_divisi
        ORDER BY d.nama_divisi ASC, p.nama_koperasi ASC
    ")->fetchAll();

    $produk_list = $pdo->query("
        SELECT pr.*, kp.nama_koperasi, d.nama_divisi
        FROM produk_etalase pr
        JOIN koperasi_primer kp ON pr.id_primer = kp.id_primer
        JOIN divisi_wilayah d ON pr.id_divisi = d.id_divisi
        ORDER BY pr.id_produk DESC
    ")->fetchAll();
}

// Hitung ringkasan statistik etalase
$total_produk       = count($produk_list);
$total_stok_menipis = 0;
$total_unit_stok    = 0;
$kategori_unik      = [];

foreach ($produk_list as $item) {
    $stok_item = (int)($item['stok'] ?? 0);
    $total_unit_stok += $stok_item;
    if ($stok_item < 20) {
        $total_stok_menipis++;
    }
    $kat = trim((string)($item['kategori'] ?? ''));
    if ($kat !== '' && !in_array($kat, $kategori_unik, true)) {
        $kategori_unik[] = $kat;
    }
}

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

    .prd-wrap {
        display: flex;
        flex-direction: column;
        gap: 22px;
        color: #0F172A;
    }

    /* HERO HEADER KONSISTEN DENGAN MODUL WILAYAH & USERS */
    .prd-hero {
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
    .prd-eyebrow {
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
    .prd-eyebrow-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: #34D399;
    }
    .prd-hero-title {
        margin: 0 0 6px;
        font-size: 22px;
        font-weight: 800;
        letter-spacing: -0.3px;
        line-height: 1.25;
    }
    .prd-hero-sub {
        margin: 0;
        font-size: 13.5px;
        color: rgba(236, 253, 245, 0.88);
        max-width: 630px;
        line-height: 1.5;
    }
    .prd-hero-btn {
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
        text-decoration: none;
        cursor: pointer;
        box-shadow: 0 6px 18px rgba(245, 158, 11, 0.32);
        transition: background-color 0.16s ease, transform 0.14s ease;
        white-space: nowrap;
    }
    .prd-hero-btn:hover {
        background: #FBBF24;
    }
    .prd-hero-btn:active {
        transform: scale(0.98);
    }

    /* ALERT NOTIFIKASI */
    .prd-alert {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 13px 16px;
        border-radius: 12px;
        font-size: 13.5px;
        font-weight: 600;
    }
    .prd-alert-success {
        background: #DCFCE7;
        border: 1px solid #86EFAC;
        color: #166534;
    }
    .prd-alert-error {
        background: #FEE2E2;
        border: 1px solid #FCA5A5;
        color: #991B1B;
    }
    .prd-alert-close {
        background: none;
        border: none;
        color: inherit;
        font-size: 18px;
        font-weight: 800;
        cursor: pointer;
        padding: 0 4px;
        opacity: 0.75;
    }
    .prd-alert-close:hover {
        opacity: 1;
    }

    /* 3 KARTU KPI RINGKASAN */
    .prd-grid-3 {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 16px;
    }
    .prd-kpi {
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
    .prd-kpi:hover {
        border-color: #10B981;
        transform: translateY(-2px);
    }
    .prd-kpi-val {
        font-size: clamp(1.25rem, 1.7vw, 1.55rem);
        font-weight: 800;
        color: #0F172A;
        margin: 8px 0 3px;
        font-variant-numeric: tabular-nums;
    }

    /* CARD CONTAINER & FORM UPLOAD + LIVE PREVIEW */
    .prd-card {
        background: #FFFFFF;
        border-radius: 16px;
        border: 1px solid #E2E8F0;
        padding: 22px;
        box-shadow: 0 2px 10px rgba(15, 23, 42, 0.03);
    }
    .prd-form-layout {
        display: grid;
        grid-template-columns: minmax(0, 1.85fr) minmax(260px, 0.85fr);
        gap: 22px;
        align-items: start;
    }
    .prd-form-fields {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 13px;
    }
    .prd-span-full {
        grid-column: 1 / -1;
    }
    .prd-label {
        display: block;
        font-size: 12px;
        font-weight: 700;
        color: #334155;
    }
    .prd-input {
        width: 100%;
        box-sizing: border-box;
        padding: 10px 12px;
        border: 1px solid #CBD5E1;
        border-radius: 10px;
        margin-top: 5px;
        font-family: inherit;
        font-size: 13.5px;
        font-weight: 600;
        color: #0F172A;
        background: #FFFFFF;
        transition: border-color 0.16s ease, box-shadow 0.16s ease;
    }
    .prd-input:focus {
        outline: none;
        border-color: #10B981;
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.14);
    }
    .prd-unit-chip {
        padding: 2px 8px;
        border-radius: 6px;
        border: 1px solid #CBD5E1;
        background: #F8FAFC;
        color: #475569;
        font-family: inherit;
        font-size: 11px;
        font-weight: 700;
        cursor: pointer;
        transition: background-color 0.14s ease, border-color 0.14s ease, color 0.14s ease;
    }
    .prd-unit-chip:hover {
        background: #ECFDF5;
        border-color: #059669;
        color: #065F46;
    }
    .prd-upload-box {
        margin-top: 5px;
        border: 1.5px dashed #94A3B8;
        border-radius: 11px;
        padding: 9px 12px;
        background: #F8FAFC;
        display: flex;
        align-items: center;
        gap: 11px;
        cursor: pointer;
        transition: border-color 0.16s ease, background-color 0.16s ease;
    }
    .prd-upload-box:hover {
        border-color: #059669;
        background: #ECFDF5;
    }
    .prd-submit-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        background: #064E3B;
        color: #FFFFFF;
        border: none;
        padding: 12px 22px;
        border-radius: 11px;
        font-family: inherit;
        font-size: 13.5px;
        font-weight: 700;
        cursor: pointer;
        transition: background-color 0.16s ease, transform 0.14s ease;
    }
    .prd-submit-btn:hover {
        background: #042F24;
    }
    .prd-submit-btn:active {
        transform: scale(0.98);
    }

    /* LIVE PREVIEW CARD */
    .prd-preview-box {
        background: #F8FAFC;
        border: 1px solid #E2E8F0;
        border-radius: 14px;
        padding: 14px;
    }
    .prd-preview-card {
        background: #FFFFFF;
        border-radius: 13px;
        border: 1px solid #E2E8F0;
        overflow: hidden;
        box-shadow: 0 3px 10px rgba(15, 23, 42, 0.04);
    }

    /* TOOLBAR & FILTER PILLS */
    .prd-pill {
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
    .prd-pill:hover {
        border-color: #10B981;
        color: #065F46;
    }
    .prd-pill.active {
        background: #064E3B;
        color: #FFFFFF;
        border-color: #064E3B;
    }
    .prd-search {
        width: 100%;
        box-sizing: border-box;
        padding: 9px 13px 9px 35px;
        border: 1px solid #CBD5E1;
        border-radius: 10px;
        font-family: inherit;
        font-size: 13px;
        color: #0F172A;
        background: #FFFFFF;
        transition: border-color 0.16s ease, box-shadow 0.16s ease;
    }
    .prd-search:focus {
        outline: none;
        border-color: #10B981;
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.14);
    }
    .prd-sort {
        padding: 9px 12px;
        border: 1px solid #CBD5E1;
        border-radius: 10px;
        font-family: inherit;
        font-size: 12.5px;
        font-weight: 700;
        color: #334155;
        background: #FFFFFF;
        cursor: pointer;
    }
    .prd-sort:focus {
        outline: none;
        border-color: #10B981;
    }

    /* GRID KATALOG PRODUK TAYANG */
    .prd-catalog-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(265px, 1fr));
        gap: 18px;
    }
    .prd-item-card {
        background: #FFFFFF;
        border-radius: 16px;
        border: 1px solid #E2E8F0;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        box-shadow: 0 2px 10px rgba(15, 23, 42, 0.03);
        transition: transform 0.18s ease, border-color 0.18s ease, box-shadow 0.18s ease;
    }
    .prd-item-card:hover {
        transform: translateY(-2px);
        border-color: #10B981;
        box-shadow: 0 10px 22px rgba(15, 23, 42, 0.07);
    }
    .prd-thumb-wrap {
        position: relative;
        width: 100%;
        height: 180px;
        background: #F1F5F9;
        overflow: hidden;
    }
    .prd-thumb {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }
    .prd-code-tag {
        display: inline-block;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 11px;
        font-weight: 700;
        color: #475569;
        background: #F1F5F9;
        border: 1px solid #E2E8F0;
        padding: 2px 7px;
        border-radius: 6px;
    }
    .prd-desc {
        margin: 0 0 12px;
        font-size: 12.5px;
        color: #64748B;
        line-height: 1.5;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        min-height: 37px;
    }
    .prd-act-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
        padding: 6px 10px;
        border-radius: 8px;
        font-family: inherit;
        font-size: 12px;
        font-weight: 700;
        border: 1px solid transparent;
        cursor: pointer;
        text-decoration: none;
        transition: background-color 0.15s ease, color 0.15s ease;
    }
    .prd-edit-btn {
        background: #FEF3C7;
        border-color: #FDE68A;
        color: #92400E;
    }
    .prd-edit-btn:hover {
        background: #FDE68A;
    }
    .prd-del-btn {
        background: #FEF2F2;
        border-color: #FECACA;
        color: #DC2626;
    }
    .prd-del-btn:hover {
        background: #FEE2E2;
    }

    /* MODAL EDIT / RESTOCK RINGAN (ZERO LAG) */
    .prd-modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.58);
        z-index: 999;
        align-items: center;
        justify-content: center;
        padding: 16px;
    }
    .prd-modal-overlay.show {
        display: flex;
    }
    .prd-modal-box {
        background: #FFFFFF;
        border: 1px solid #E2E8F0;
        border-radius: 18px;
        width: 100%;
        max-width: 520px;
        max-height: 92vh;
        overflow-y: auto;
        box-shadow: 0 22px 46px rgba(15, 23, 42, 0.24);
    }
    .prd-modal-head {
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

    .mobile-nav-trigger {
        display: none;
    }

    /* TABLET & LAPTOP SEDANG */
    @media (max-width: 1120px) {
        .prd-form-layout {
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

    /* OPTIMASI LAYAR PONSEL (< 640px) */
    @media (max-width: 640px) {
        .prd-grid-3, .prd-form-fields {
            grid-template-columns: minmax(0, 1fr);
        }
        .prd-hero {
            padding: 20px;
        }
        .prd-hero-title {
            font-size: 19px;
        }
        .prd-hero-btn, .prd-submit-btn {
            width: 100%;
        }
        .prd-catalog-grid {
            grid-template-columns: minmax(0, 1fr);
        }
        .prd-sort {
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

<div class="prd-wrap">

    <!-- BANNER HEADER KONSISTEN -->
    <section class="prd-hero">
        <div>
            <div class="prd-eyebrow">
                <span class="prd-eyebrow-dot"></span>
                <span>MANAJEMEN KATALOG &amp; ETALASE KOPERASI</span>
            </div>
            <h2 class="prd-hero-title">Posting &amp; Kelola Produk Etalase Buyer</h2>
            <p class="prd-hero-sub">
                Unggah foto komoditas berkualitas dan atur ketersediaan stok agar produk koperasi tampil menarik di Marketplace Buyer.
            </p>
        </div>

        <a href="#formPostingProduk" class="prd-hero-btn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            <span>Tambah Produk Baru</span>
        </a>
    </section>

    <!-- ALERT NOTIFIKASI -->
    <?php if (isset($_GET['msg']) && $_GET['msg'] !== ''): ?>
        <div class="prd-alert prd-alert-success">
            <span>✓ <?= htmlspecialchars($_GET['msg']) ?></span>
            <button type="button" class="prd-alert-close" onclick="this.parentElement.remove()" aria-label="Tutup">&times;</button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['err']) && $_GET['err'] !== ''): ?>
        <div class="prd-alert prd-alert-error">
            <span>! <?= htmlspecialchars($_GET['err']) ?></span>
            <button type="button" class="prd-alert-close" onclick="this.parentElement.remove()" aria-label="Tutup">&times;</button>
        </div>
    <?php endif; ?>

    <!-- 3 KARTU RINGKASAN ETALASE -->
    <section class="prd-grid-3">
        <div class="prd-kpi" style="border-left:4px solid #064E3B;">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                <span style="font-size:12px; color:#64748B; font-weight:700;">Total Produk Tayang</span>
                <div style="width:36px; height:36px; border-radius:10px; background:#DCFCE7; color:#059669; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                </div>
            </div>
            <div class="prd-kpi-val"><?= $total_produk ?> Katalog</div>
            <div style="font-size:11.5px; color:#64748B;">Aktif terlihat di halaman Buyer</div>
        </div>

        <div class="prd-kpi" style="border-left:4px solid #DC2626;">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                <span style="font-size:12px; color:#64748B; font-weight:700;">Perlu Restock (&lt; 20)</span>
                <div style="width:36px; height:36px; border-radius:10px; background:#FEE2E2; color:#DC2626; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                </div>
            </div>
            <div class="prd-kpi-val" style="color:<?= $total_stok_menipis > 0 ? '#DC2626' : '#0F172A' ?>;"><?= $total_stok_menipis ?> Produk</div>
            <div style="font-size:11.5px; color:#64748B;">Sisa stok di bawah 20 unit</div>
        </div>

        <div class="prd-kpi" style="border-left:4px solid #059669;">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                <span style="font-size:12px; color:#64748B; font-weight:700;">Total Volume Tersedia</span>
                <div style="width:36px; height:36px; border-radius:10px; background:#ECFDF5; color:#059669; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
            </div>
            <div class="prd-kpi-val"><?= number_format($total_unit_stok, 0, ',', '.') ?> Unit</div>
            <div style="font-size:11.5px; color:#059669; font-weight:600;">Akumulasi stok siap jual</div>
        </div>
    </section>

    <!-- FORM UPLOAD PRODUK BARU + LIVE PREVIEW -->
    <section id="formPostingProduk" class="prd-card">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:10px; margin-bottom:18px; padding-bottom:14px; border-bottom:1px solid #F1F5F9;">
            <div>
                <span style="display:inline-block; background:#DCFCE7; color:#166534; font-size:10.5px; font-weight:800; letter-spacing:0.5px; padding:4px 10px; border-radius:99px; margin-bottom:6px; text-transform:uppercase;">Publikasi Etalase</span>
                <h3 style="margin:0; font-size:16.5px; font-weight:800; color:#0F172A;">Posting Produk Baru ke Marketplace Buyer</h3>
                <div style="font-size:12.5px; color:#64748B; margin-top:2px;">Lengkapi detail komoditas dan lihat pratinjau tampilan kartu sebelum dipublikasikan.</div>
            </div>
        </div>

        <div class="prd-form-layout">
            <!-- KOLOM KIRI: INPUT FORM -->
            <form action="proses.php" method="POST" enctype="multipart/form-data">
                <?= $csrf_input ?>
                <input type="hidden" name="aksi" value="tambah_produk">

                <div class="prd-form-fields">
                    <div>
                        <label class="prd-label" for="inpPrimerDivisi">Unit Koperasi Primer *</label>
                        <select name="primer_divisi" id="inpPrimerDivisi" onchange="updateLiveProductPreview()" required class="prd-input">
                            <?php if (empty($primer_list)): ?>
                                <option value="">-- Unit Koperasi Primer Belum Terdaftar --</option>
                            <?php else: ?>
                                <?php foreach ($primer_list as $kp): ?>
                                    <option value="<?= (int)$kp['id_primer'] ?>|<?= (int)$kp['id_divisi'] ?>"
                                            data-koperasi="<?= htmlspecialchars($kp['nama_koperasi'], ENT_QUOTES) ?>"
                                            data-divisi="<?= htmlspecialchars($kp['nama_divisi'], ENT_QUOTES) ?>">
                                        <?= htmlspecialchars($kp['nama_koperasi']) ?> - <?= htmlspecialchars($kp['nama_divisi']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div>
                        <label class="prd-label" for="inpNamaProduk">Nama Produk Jual *</label>
                        <input type="text" name="nama_produk" id="inpNamaProduk" oninput="updateLiveProductPreview()" placeholder="Contoh: Kentang Granola Grade A" required class="prd-input">
                    </div>

                    <div>
                        <label class="prd-label" for="inpKategori">Kategori Komoditas *</label>
                        <select name="kategori" id="inpKategori" onchange="updateLiveProductPreview()" class="prd-input">
                            <option value="Komoditas Tanaman">Komoditas Tanaman</option>
                            <option value="Saprodi">Saprodi</option>
                            <option value="Alsintan">Alsintan</option>
                            <option value="Produk Olahan">Produk Olahan</option>
                            <option value="Produk Lainnya">Produk Lainnya</option>
                        </select>
                    </div>

                    <div>
                        <label class="prd-label" for="inpHargaJual">Harga Jual Buyer (Rp) *</label>
                        <input type="number" name="harga_jual" id="inpHargaJual" oninput="updateLiveProductPreview()" placeholder="Contoh: 15000" min="1" required class="prd-input">
                    </div>

                    <div>
                        <div style="display:flex; justify-content:space-between; align-items:center; gap:6px; flex-wrap:wrap;">
                            <label class="prd-label" for="inpStok">Stok &amp; Satuan *</label>
                            <div style="display:flex; gap:4px;">
                                <button type="button" class="prd-unit-chip" onclick="setPrdSatuan('Kg')">Kg</button>
                                <button type="button" class="prd-unit-chip" onclick="setPrdSatuan('Kwintal')">Kwintal</button>
                                <button type="button" class="prd-unit-chip" onclick="setPrdSatuan('Karung')">Karung</button>
                                <button type="button" class="prd-unit-chip" onclick="setPrdSatuan('Pack')">Pack</button>
                            </div>
                        </div>
                        <div style="display:flex; gap:8px; margin-top:5px;">
                            <input type="number" name="stok" id="inpStok" oninput="updateLiveProductPreview()" placeholder="Stok" min="1" required class="prd-input" style="margin-top:0; width:60%;">
                            <input type="text" name="satuan" id="inpSatuan" oninput="updateLiveProductPreview()" value="Kg" required class="prd-input" style="margin-top:0; width:40%;">
                        </div>
                    </div>

                    <div>
                        <label class="prd-label" for="inpFotoProduk">Foto Produk (Wajib Jelas) *</label>
                        <label class="prd-upload-box" for="inpFotoProduk">
                            <img id="inpFotoMini" src="https://placehold.co/64x64/e2e8f0/475569?text=Foto" alt="Mini" style="width:40px; height:40px; object-fit:cover; border-radius:8px; border:1px solid #CBD5E1; flex-shrink:0;">
                            <div style="min-width:0; flex:1;">
                                <div id="inpFotoLabel" style="font-size:12.5px; font-weight:700; color:#0F172A; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">Pilih Foto Produk</div>
                                <div style="font-size:11px; color:#64748B;">JPG/PNG dari galeri atau kamera</div>
                            </div>
                        </label>
                        <input type="file" name="foto_produk" id="inpFotoProduk" accept="image/*" required onchange="previewProdukImage(this)" style="position:absolute; width:1px; height:1px; opacity:0; overflow:hidden;">
                    </div>

                    <div class="prd-span-full">
                        <label class="prd-label" for="inpDeskripsi">Deskripsi Singkat Produk</label>
                        <input type="text" name="deskripsi" id="inpDeskripsi" oninput="updateLiveProductPreview()" placeholder="Kualitas sortir super, siap kirim partai besar maupun ritel..." class="prd-input">
                    </div>

                    <div class="prd-span-full" style="margin-top:4px;">
                        <button type="submit" class="prd-submit-btn">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                            <span>Publikasikan ke Etalase Buyer</span>
                        </button>
                    </div>
                </div>
            </form>

            <!-- KOLOM KANAN: PRATINJAU LANGSUNG TAMPILAN DI BUYER -->
            <div class="prd-preview-box">
                <div style="font-size:11px; font-weight:800; color:#64748B; text-transform:uppercase; letter-spacing:0.4px; margin-bottom:10px; display:flex; align-items:center; justify-content:space-between;">
                    <span>Pratinjau Kartu Buyer</span>
                    <span style="background:#DCFCE7; color:#166534; padding:2px 8px; border-radius:99px; font-size:10px;">Live Preview</span>
                </div>

                <div class="prd-preview-card">
                    <div style="position:relative; height:145px; background:#E2E8F0;">
                        <img id="prevCardImg" src="https://placehold.co/480x280/064e3b/ffffff?text=Pratinjau+Produk" alt="Preview" style="width:100%; height:100%; object-fit:cover; display:block;">
                        <span id="prevCardKat" style="position:absolute; top:10px; left:10px; background:rgba(4,47,36,0.92); color:#ECFDF5; font-size:10.5px; font-weight:700; padding:3px 9px; border-radius:99px;">
                            Komoditas Tanaman
                        </span>
                    </div>
                    <div style="padding:14px;">
                        <div id="prevCardDivisi" style="font-size:11px; font-weight:700; color:#059669;">Divisi Wilayah</div>
                        <div id="prevCardNama" style="font-size:15px; font-weight:800; color:#0F172A; margin:3px 0;">Nama Produk Jual</div>
                        <div id="prevCardKoperasi" style="font-size:11.5px; color:#64748B; margin-bottom:8px;">Unit Koperasi Primer</div>
                        <div style="display:flex; justify-content:space-between; align-items:baseline; padding-top:8px; border-top:1px solid #F1F5F9;">
                            <div style="font-size:15.5px; font-weight:800; color:#064E3B;">
                                <span id="prevCardHarga">Rp 0</span><span style="font-size:11px; font-weight:600; color:#64748B;">/<span id="prevCardSatuan1">Kg</span></span>
                            </div>
                            <div style="font-size:11.5px; font-weight:700; color:#475569;">
                                Stok: <span id="prevCardStok">0</span> <span id="prevCardSatuan2">Kg</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- BILAH FILTER & PENCARIAN PRODUK TAYANG -->
    <section class="prd-card" style="padding:18px 22px;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:14px;">
            <div>
                <h3 style="margin:0 0 2px; font-size:16.5px; font-weight:800; color:#0F172A;">Daftar Produk Tayang di Etalase</h3>
                <div style="font-size:12.5px; color:#64748B;">Pantau harga, sisa stok, dan kelola katalog aktif koperasi.</div>
            </div>

            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap; flex:1; justify-content:flex-end; min-width:240px;">
                <div style="position:relative; flex:1; min-width:200px; max-width:300px;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#64748B" stroke-width="2.2" style="position:absolute; left:11px; top:50%; transform:translateY(-50%); pointer-events:none;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="search" id="prdSearchInput" oninput="filterCatalogProducts()" placeholder="Cari produk, kode, koperasi..." class="prd-search">
                </div>

                <select id="prdSortSelect" onchange="sortCatalogProducts()" class="prd-sort" aria-label="Urutkan produk">
                    <option value="newest">Urutkan: Terbaru</option>
                    <option value="stock_asc">Stok: Paling Sedikit</option>
                    <option value="price_asc">Harga: Terendah</option>
                    <option value="price_desc">Harga: Tertinggi</option>
                </select>
            </div>
        </div>

        <!-- FILTER PILLS -->
        <div style="display:flex; gap:7px; flex-wrap:wrap; padding-top:12px; border-top:1px solid #F1F5F9;" id="prdFilterBar">
            <button type="button" class="prd-pill active" onclick="setPrdFilter('ALL', this)">Semua (<?= $total_produk ?>)</button>
            <button type="button" class="prd-pill" onclick="setPrdFilter('LOW_STOCK', this)">Stok Menipis (<?= $total_stok_menipis ?>)</button>
            <?php foreach ($kategori_unik as $kat): ?>
                <button type="button" class="prd-pill" onclick="setPrdFilter('<?= htmlspecialchars($kat, ENT_QUOTES) ?>', this)">
                    <?= htmlspecialchars($kat) ?>
                </button>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- GRID PREVIEW PRODUK YANG SUDAH TAYANG -->
    <?php if (empty($produk_list)): ?>
        <div class="prd-card" style="padding:44px 24px; text-align:center;">
            <div style="font-size:17px; font-weight:800; color:#0F172A; margin-bottom:6px;">Belum Ada Produk di Etalase</div>
            <p style="margin:0; font-size:13.5px; color:#64748B;">Gunakan formulir di atas untuk mempublikasikan produk pertama ke Marketplace Buyer.</p>
        </div>
    <?php else: ?>
        <div class="prd-catalog-grid" id="prdCatalogGrid">
            <?php foreach ($produk_list as $idx => $pr):
                $stok_val  = (int)$pr['stok'];
                $harga_val = (float)$pr['harga_jual'];
                $kat_val   = (string)($pr['kategori'] ?? 'Komoditas Tanaman');
                $kode_val  = (string)($pr['kode_produk'] ?? 'PRD-' . (int)$pr['id_produk']);
                $search_blob = strtolower(
                    $kode_val . ' ' .
                    $pr['nama_produk'] . ' ' .
                    $pr['nama_koperasi'] . ' ' .
                    $pr['nama_divisi'] . ' ' .
                    $kat_val
                );
            ?>
            <div class="prd-item-card prd-catalog-item"
                 data-order="<?= $idx ?>"
                 data-category="<?= htmlspecialchars($kat_val, ENT_QUOTES) ?>"
                 data-stock="<?= $stok_val ?>"
                 data-price="<?= $harga_val ?>"
                 data-search="<?= htmlspecialchars($search_blob, ENT_QUOTES) ?>">

                <div class="prd-thumb-wrap">
                    <img src="<?= BASE_URL ?>/uploads/produk/<?= htmlspecialchars($pr['foto_produk']) ?>"
                         alt="<?= htmlspecialchars($pr['nama_produk']) ?>"
                         onerror="this.src='https://placehold.co/480x300/064e3b/ffffff?text=Foto+Produk'"
                         class="prd-thumb"
                         loading="lazy">

                    <span style="position:absolute; top:11px; left:11px; background:rgba(4, 47, 36, 0.92); color:#ECFDF5; font-size:10.5px; font-weight:700; padding:4px 10px; border-radius:99px;">
                        <?= htmlspecialchars($kat_val) ?>
                    </span>

                    <?php if ($stok_val < 20): ?>
                    <span style="position:absolute; top:11px; right:11px; background:#FEF2F2; color:#B91C1C; border:1px solid #FECACA; font-size:10.5px; font-weight:800; padding:3px 9px; border-radius:99px;">
                        Stok Menipis
                    </span>
                    <?php endif; ?>
                </div>

                <div style="padding:16px 18px; flex:1; display:flex; flex-direction:column; justify-content:space-between;">
                    <div>
                        <div style="display:flex; justify-content:space-between; align-items:center; gap:6px;">
                            <span style="font-size:11.5px; font-weight:700; color:#059669;">
                                <?= htmlspecialchars($pr['nama_divisi']) ?>
                            </span>
                            <span class="prd-code-tag"><?= htmlspecialchars($kode_val) ?></span>
                        </div>

                        <h4 style="margin:5px 0 3px; font-size:15.5px; font-weight:800; color:#0F172A; line-height:1.35;">
                            <?= htmlspecialchars($pr['nama_produk']) ?>
                        </h4>
                        <div style="font-size:12px; color:#64748B; font-weight:600; margin-bottom:8px;">
                            <?= htmlspecialchars($pr['nama_koperasi']) ?>
                        </div>
                        <p class="prd-desc">
                            <?= htmlspecialchars($pr['deskripsi'] ?: 'Komoditas unggulan koperasi siap distribusi.') ?>
                        </p>
                    </div>

                    <div>
                        <div style="font-size:17px; font-weight:800; color:#064E3B; font-variant-numeric:tabular-nums;">
                            <?= rupiah($pr['harga_jual']) ?> <span style="font-size:12px; font-weight:600; color:#64748B;">/ <?= htmlspecialchars($pr['satuan']) ?></span>
                        </div>

                        <div style="display:flex; justify-content:space-between; align-items:center; gap:6px; margin-top:12px; padding-top:12px; border-top:1px solid #F1F5F9; flex-wrap:wrap;">
                            <span style="font-size:11.5px; font-weight:700; color:<?= $stok_val < 20 ? '#DC2626' : '#334155' ?>; background:<?= $stok_val < 20 ? '#FEF2F2' : '#F8FAFC' ?>; padding:4px 9px; border-radius:8px; border:1px solid <?= $stok_val < 20 ? '#FECACA' : '#E2E8F0' ?>;">
                                Stok: <?= number_format($stok_val, 0, ',', '.') ?> <?= htmlspecialchars($pr['satuan']) ?>
                            </span>

                            <div style="display:flex; align-items:center; gap:5px;">
                                <button type="button" class="prd-act-btn prd-edit-btn"
                                    onclick='openEditProdukModal(<?= json_encode([
                                        "id_produk"   => (int)$pr["id_produk"],
                                        "id_primer"   => (int)$pr["id_primer"],
                                        "id_divisi"   => (int)$pr["id_divisi"],
                                        "nama_produk" => $pr["nama_produk"],
                                        "kategori"    => $kat_val,
                                        "harga_jual"  => (float)$pr["harga_jual"],
                                        "stok"        => $stok_val,
                                        "satuan"      => $pr["satuan"],
                                        "deskripsi"   => $pr["deskripsi"] ?? ""
                                    ], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                    Edit / Stok
                                </button>

                                <form action="proses.php" method="POST" style="margin:0;" onsubmit="return confirm('Hapus produk <?= htmlspecialchars(addslashes($pr['nama_produk'])) ?> dari etalase?');">
                                    <?= $csrf_input ?>
                                    <input type="hidden" name="aksi" value="hapus_produk">
                                    <input type="hidden" name="id_produk" value="<?= (int)$pr['id_produk'] ?>">
                                    <button type="submit" class="prd-act-btn prd-del-btn">Hapus</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div id="prdEmptyFilter" class="prd-card" style="display:none; padding:38px 24px; text-align:center;">
            <div style="font-size:16.5px; font-weight:800; color:#0F172A; margin-bottom:6px;">Produk Tidak Ditemukan</div>
            <p style="margin:0 0 14px; font-size:13px; color:#64748B;">Coba ubah kata kunci pencarian atau pilih filter kategori lainnya.</p>
            <button type="button" onclick="resetPrdFilters()" class="prd-submit-btn" style="padding:9px 18px; font-size:13px;">
                Tampilkan Semua Produk
            </button>
        </div>
    <?php endif; ?>

</div>

<!-- MODAL EDIT & RESTOCK PRODUK -->
<div id="modalEditProduk" class="prd-modal-overlay" onclick="if(event.target===this) closeEditProdukModal()">
    <div class="prd-modal-box">
        <div class="prd-modal-head">
            <div>
                <div style="font-weight:800; font-size:15.5px;">Perbarui Data &amp; Restock Produk</div>
                <div style="font-size:11.5px; color:#A7F3D0; margin-top:2px;">Ubah harga, tambah ketersediaan stok, atau perbarui foto produk.</div>
            </div>
            <button type="button" onclick="closeEditProdukModal()" style="background:rgba(255,255,255,0.12); border:none; width:30px; height:30px; border-radius:8px; font-size:18px; cursor:pointer; color:#FFFFFF;">&times;</button>
        </div>

        <form action="proses.php" method="POST" enctype="multipart/form-data" style="padding:20px 22px 22px; display:flex; flex-direction:column; gap:13px;">
            <?= $csrf_input ?>
            <input type="hidden" name="aksi" value="edit_produk">
            <input type="hidden" name="id_produk" id="edit_id_produk">

            <?php if ($role === 'konsumen'): ?>
            <div>
                <label class="prd-label" for="edit_primer_divisi">Unit Koperasi Primer *</label>
                <select name="primer_divisi" id="edit_primer_divisi" required class="prd-input">
                    <?php foreach ($primer_list as $kp): ?>
                        <option value="<?= (int)$kp['id_primer'] ?>|<?= (int)$kp['id_divisi'] ?>">
                            <?= htmlspecialchars($kp['nama_koperasi']) ?> - <?= htmlspecialchars($kp['nama_divisi']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="prd-form-fields">
                <div>
                    <label class="prd-label" for="edit_nama_produk">Nama Produk *</label>
                    <input type="text" name="nama_produk" id="edit_nama_produk" required class="prd-input">
                </div>
                <div>
                    <label class="prd-label" for="edit_kategori">Kategori *</label>
                    <select name="kategori" id="edit_kategori" class="prd-input">
                        <option value="Komoditas Tanaman">Komoditas Tanaman</option>
                        <option value="Saprodi">Saprodi</option>
                        <option value="Alsintan">Alsintan</option>
                        <option value="Produk Olahan">Produk Olahan</option>
                        <option value="Produk Lainnya">Produk Lainnya</option>
                    </select>
                </div>
            </div>

            <div class="prd-form-fields">
                <div>
                    <label class="prd-label" for="edit_harga_jual">Harga Jual Buyer (Rp) *</label>
                    <input type="number" name="harga_jual" id="edit_harga_jual" min="1" required class="prd-input">
                </div>
                <div>
                    <label class="prd-label" for="edit_stok">Stok &amp; Satuan *</label>
                    <div style="display:flex; gap:8px; margin-top:5px;">
                        <input type="number" name="stok" id="edit_stok" min="0" required class="prd-input" style="margin-top:0; width:60%;">
                        <input type="text" name="satuan" id="edit_satuan" required class="prd-input" style="margin-top:0; width:40%;">
                    </div>
                </div>
            </div>

            <div>
                <label class="prd-label" for="edit_deskripsi">Deskripsi Singkat</label>
                <input type="text" name="deskripsi" id="edit_deskripsi" class="prd-input">
            </div>

            <div>
                <label class="prd-label" for="edit_foto_produk">Ganti Foto Produk (Opsional)</label>
                <input type="file" name="foto_produk" id="edit_foto_produk" accept="image/*" class="prd-input" style="padding:8px 10px; font-size:12.5px;">
                <div style="font-size:11px; color:#64748B; margin-top:3px;">Biarkan kosong jika tidak ingin mengganti foto yang sudah tayang.</div>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:4px;">
                <button type="button" onclick="closeEditProdukModal()" style="background:#F1F5F9; color:#334155; border:1px solid #CBD5E1; padding:10px 16px; border-radius:10px; font-family:inherit; font-size:13px; font-weight:700; cursor:pointer;">Batal</button>
                <button type="submit" style="background:#D97706; color:#FFFFFF; border:none; padding:10px 18px; border-radius:10px; font-family:inherit; font-size:13px; font-weight:700; cursor:pointer;">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
// Update Live Preview saat mengisi form produk baru
function updateLiveProductPreview() {
    const selPrimer = document.getElementById('inpPrimerDivisi');
    const namaInp   = document.getElementById('inpNamaProduk');
    const katInp    = document.getElementById('inpKategori');
    const hargaInp  = document.getElementById('inpHargaJual');
    const stokInp   = document.getElementById('inpStok');
    const satInp    = document.getElementById('inpSatuan');

    if (selPrimer && selPrimer.selectedOptions[0]) {
        const opt = selPrimer.selectedOptions[0];
        document.getElementById('prevCardDivisi').textContent   = opt.getAttribute('data-divisi') || 'Divisi Wilayah';
        document.getElementById('prevCardKoperasi').textContent = opt.getAttribute('data-koperasi') || 'Unit Koperasi Primer';
    }
    if (namaInp) {
        document.getElementById('prevCardNama').textContent = namaInp.value.trim() || 'Nama Produk Jual';
    }
    if (katInp) {
        document.getElementById('prevCardKat').textContent = katInp.value || 'Komoditas Tanaman';
    }
    if (hargaInp) {
        const h = parseFloat(hargaInp.value) || 0;
        document.getElementById('prevCardHarga').textContent = 'Rp ' + h.toLocaleString('id-ID');
    }
    if (stokInp) {
        const s = parseInt(stokInp.value, 10) || 0;
        document.getElementById('prevCardStok').textContent = s.toLocaleString('id-ID');
    }
    if (satInp) {
        const u = satInp.value.trim() || 'Kg';
        document.getElementById('prevCardSatuan1').textContent = u;
        document.getElementById('prevCardSatuan2').textContent = u;
    }
}

function setPrdSatuan(unit) {
    const el = document.getElementById('inpSatuan');
    if (el) {
        el.value = unit;
        updateLiveProductPreview();
    }
}

function previewProdukImage(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const lbl  = document.getElementById('inpFotoLabel');
        if (lbl) lbl.textContent = file.name;

        const reader = new FileReader();
        reader.onload = function(e) {
            const mini = document.getElementById('inpFotoMini');
            const card = document.getElementById('prevCardImg');
            if (mini) mini.src = e.target.result;
            if (card) card.src = e.target.result;
        };
        reader.readAsDataURL(file);
    }
}

// Filter & Pencarian Katalog Produk Tayang
let activePrdFilter = 'ALL';

function setPrdFilter(mode, btn) {
    activePrdFilter = mode;
    document.querySelectorAll('#prdFilterBar .prd-pill').forEach(function(el) {
        el.classList.remove('active');
    });
    btn.classList.add('active');
    filterCatalogProducts();
}

function filterCatalogProducts() {
    const q     = (document.getElementById('prdSearchInput')?.value || '').toLowerCase().trim();
    const items = document.querySelectorAll('.prd-catalog-item');
    let visible = 0;

    items.forEach(function(card) {
        const cat   = card.getAttribute('data-category');
        const stock = parseInt(card.getAttribute('data-stock'), 10) || 0;
        const txt   = card.getAttribute('data-search') || '';

        let matchMode = true;
        if (activePrdFilter === 'LOW_STOCK') {
            matchMode = stock < 20;
        } else if (activePrdFilter !== 'ALL') {
            matchMode = (cat === activePrdFilter);
        }

        const matchText = !q || txt.indexOf(q) !== -1;
        if (matchMode && matchText) {
            card.style.display = 'flex';
            visible++;
        } else {
            card.style.display = 'none';
        }
    });

    const emptyBox = document.getElementById('prdEmptyFilter');
    if (emptyBox) {
        emptyBox.style.display = (items.length > 0 && visible === 0) ? 'block' : 'none';
    }
}

function sortCatalogProducts() {
    const select = document.getElementById('prdSortSelect');
    const grid   = document.getElementById('prdCatalogGrid');
    if (!select || !grid) return;

    const mode  = select.value;
    const items = Array.from(grid.querySelectorAll('.prd-catalog-item'));

    items.sort(function(a, b) {
        if (mode === 'stock_asc') {
            return parseInt(a.getAttribute('data-stock'), 10) - parseInt(b.getAttribute('data-stock'), 10);
        } else if (mode === 'price_asc') {
            return parseFloat(a.getAttribute('data-price')) - parseFloat(b.getAttribute('data-price'));
        } else if (mode === 'price_desc') {
            return parseFloat(b.getAttribute('data-price')) - parseFloat(a.getAttribute('data-price'));
        } else {
            return parseInt(a.getAttribute('data-order'), 10) - parseInt(b.getAttribute('data-order'), 10);
        }
    });

    items.forEach(function(item) {
        grid.appendChild(item);
    });
}

function resetPrdFilters() {
    const input = document.getElementById('prdSearchInput');
    if (input) input.value = '';
    const firstPill = document.querySelector('#prdFilterBar .prd-pill');
    if (firstPill) setPrdFilter('ALL', firstPill);
}

// Modal Edit & Restock Produk
function openEditProdukModal(data) {
    document.getElementById('edit_id_produk').value   = data.id_produk;
    document.getElementById('edit_nama_produk').value = data.nama_produk;
    document.getElementById('edit_kategori').value    = data.kategori;
    document.getElementById('edit_harga_jual').value  = data.harga_jual;
    document.getElementById('edit_stok').value        = data.stok;
    document.getElementById('edit_satuan').value      = data.satuan;
    document.getElementById('edit_deskripsi').value   = data.deskripsi || '';
    document.getElementById('edit_foto_produk').value = '';

    const selPrimer = document.getElementById('edit_primer_divisi');
    if (selPrimer) {
        selPrimer.value = data.id_primer + '|' + data.id_divisi;
    }

    document.getElementById('modalEditProduk').classList.add('show');
}

function closeEditProdukModal() {
    const modal = document.getElementById('modalEditProduk');
    if (modal) modal.classList.remove('show');
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeEditProdukModal();
    }
});

// Inisialisasi nilai awal Live Preview & Sidebar Drawer HP
document.addEventListener('DOMContentLoaded', updateLiveProductPreview);

function toggleKopSidebar() {
    const sb = document.getElementById('mainSidebar');
    const bd = document.getElementById('kopBackdrop');
    if (!sb) return;
    const isOpen = sb.classList.toggle('open');
    if (bd) bd.style.display = isOpen ? 'block' : 'none';
}
</script>

<?php require_once '../../includes/footer.php'; ?>