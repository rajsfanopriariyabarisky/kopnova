<?php
$user       = $user ?? ($_SESSION['user'] ?? []);
$role       = $role ?? ($user['role'] ?? '');
$cart_count = $cart_count ?? (isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0);

if (!isset($role_badge)) {
    $role_labels = [
        'konsumen' => 'Koperasi Konsumen (Pusat)',
        'primer'   => 'Koperasi Primer Kecamatan',
        'petani'   => 'Petani / Pemasok Hulu',
        'buyer'    => 'Buyer / Mitra Pasar'
    ];
    $role_badge = $role_labels[$role] ?? strtoupper($role);
}

$uri = $_SERVER['REQUEST_URI'] ?? '';

if (!function_exists('kopNavClass')) {
    function kopNavClass(bool $active): string {
        return $active ? 'kop-link active' : 'kop-link';
    }
}

$hari_id  = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
$bulan_id = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$tgl_indo = $hari_id[(int)date('w')] . ', ' . date('d') . ' ' . $bulan_id[(int)date('n')] . ' ' . date('Y');
$initial  = strtoupper(substr(trim($user['nama_lengkap'] ?? 'K'), 0, 1));
?>

<style>
    :root {
        --kop-sb-w: 264px;
        --kop-sb-mini: 76px;
        --kop-sb-ease: 0.25s cubic-bezier(0.22, 1, 0.36, 1);
    }

    html, body {
        font-family: 'Plus Jakarta Sans', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif !important;
        overflow-x: clip !important;
        margin: 0;
        padding: 0;
        background: #FAFBF7;
    }

    /* SIDEBAR FIXED DI KIRI (ANTI IKUT SCROLL) */
    #mainSidebar {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        bottom: 0 !important;
        width: var(--kop-sb-w) !important;
        height: 100vh !important;
        background: linear-gradient(180deg, #042F24 0%, #03241B 60%, #021A14 100%) !important;
        border-right: 1px solid rgba(52, 211, 153, 0.16);
        color: #FFFFFF;
        display: flex !important;
        flex-direction: column !important;
        justify-content: space-between !important;
        box-sizing: border-box;
        z-index: 100 !important;
        user-select: none;
        overflow: visible !important;
        transition: width var(--kop-sb-ease), transform var(--kop-sb-ease) !important;
        will-change: width, transform;
    }

    /* KONTEN KANAN MENYESUAIKAN OTOMATIS */
    body > main,
    .kop-main {
        margin-left: var(--kop-sb-w) !important;
        width: calc(100% - var(--kop-sb-w)) !important;
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        min-width: 0;
        background: #FAFBF7;
        transition: margin-left var(--kop-sb-ease), width var(--kop-sb-ease) !important;
    }

    /* TOMBOL RAIL HANDLE DI GARIS BATAS KANAN SIDEBAR */
    .kop-rail-handle {
        position: absolute;
        top: 25px;
        right: -13px;
        width: 26px;
        height: 26px;
        border-radius: 50%;
        background: #042F24;
        border: 1.5px solid #10B981;
        color: #6EE7B7;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        z-index: 110;
        box-shadow: 0 4px 12px rgba(2, 26, 20, 0.35);
        transition: background-color 0.18s ease, color 0.18s ease, transform 0.25s cubic-bezier(0.22, 1, 0.36, 1);
    }
    .kop-rail-handle:hover {
        background: #10B981;
        color: #021A14;
    }
    .kop-rail-handle svg {
        transition: transform 0.25s cubic-bezier(0.22, 1, 0.36, 1);
    }

    /* HEADER BRAND BERSIH (TANPA KOTAK GLASS) */
    .kop-brand-bar {
        padding: 18px 18px 16px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        flex-shrink: 0;
        overflow: hidden;
    }

    .kop-logo-mark {
        width: 38px;
        height: 38px;
        border-radius: 12px;
        background: linear-gradient(135deg, #10B981 0%, #059669 55%, #F59E0B 100%);
        color: #021A14;
        font-weight: 800;
        font-size: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        box-shadow: 0 6px 16px rgba(16, 185, 129, 0.25);
    }

    /* AREA SCROLL MENU */
    .kop-sb-scroll {
        flex: 1;
        overflow-y: auto;
        overflow-x: hidden !important;
        padding: 16px 12px 18px;
        display: flex;
        flex-direction: column;
        gap: 18px;
        scrollbar-width: thin;
        scrollbar-color: rgba(52, 211, 153, 0.28) transparent;
    }
    .kop-sb-scroll::-webkit-scrollbar { width: 4px; }
    .kop-sb-scroll::-webkit-scrollbar-track { background: transparent; }
    .kop-sb-scroll::-webkit-scrollbar-thumb { background: rgba(52, 211, 153, 0.28); border-radius: 99px; }

    /* HEADER SECTION DENGAN GARIS HALUS */
    .kop-sec-title {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 0 10px;
        margin-bottom: 7px;
        font-size: 10px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.9px;
        color: #6EE7B7;
        opacity: 0.85;
        white-space: nowrap;
    }
    .kop-sec-title::after {
        content: "";
        flex: 1;
        height: 1px;
        background: linear-gradient(90deg, rgba(110, 231, 183, 0.22), transparent);
    }

    .kop-sec-mini-line {
        display: none;
        width: 22px;
        height: 1px;
        background: rgba(255, 255, 255, 0.16);
        margin: 2px auto 8px;
    }

    .kop-menu-stack {
        display: flex;
        flex-direction: column;
        gap: 3px;
    }

    /* ITEM NAVIGASI DENGAN LEFT ACCENT BAR */
    .kop-link {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 9.5px 12px;
        border-radius: 10px;
        text-decoration: none;
        font-size: 12.8px;
        font-weight: 600;
        color: rgba(226, 232, 240, 0.84);
        border: 1px solid transparent;
        white-space: nowrap;
        transition: background-color 0.18s ease, color 0.18s ease, border-color 0.18s ease, transform 0.18s ease;
    }
    .kop-link:hover {
        background: rgba(255, 255, 255, 0.06);
        color: #FFFFFF;
        transform: translateX(3px);
    }
    .kop-link.active {
        background: rgba(16, 185, 129, 0.16);
        border-color: rgba(52, 211, 153, 0.28);
        color: #FFFFFF;
        font-weight: 700;
    }
    .kop-link.active::before {
        content: "";
        position: absolute;
        left: 0;
        top: 7px;
        bottom: 7px;
        width: 3.5px;
        border-radius: 0 4px 4px 0;
        background: #F59E0B;
        box-shadow: 0 0 10px rgba(245, 158, 11, 0.7);
    }
    .kop-link.active:hover {
        transform: none;
    }

    .kop-link svg {
        width: 17px;
        height: 17px;
        flex-shrink: 0;
        color: #6EE7B7;
        transition: color 0.18s ease, transform 0.2s ease;
    }
    .kop-link:hover svg {
        color: #FFFFFF;
        transform: scale(1.08);
    }
    .kop-link.active svg {
        color: #FBBF24;
    }

    /* FOOTER USER & KELUAR SESI */
    .kop-sb-footer {
        padding: 12px 14px 14px;
        border-top: 1px solid rgba(255, 255, 255, 0.08);
        background: rgba(2, 26, 20, 0.65);
        display: flex;
        flex-direction: column;
        gap: 10px;
        flex-shrink: 0;
        overflow: hidden;
    }

    .kop-user-strip {
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 0;
    }

    .kop-avatar-box {
        width: 34px;
        height: 34px;
        border-radius: 10px;
        background: rgba(16, 185, 129, 0.2);
        border: 1px solid rgba(52, 211, 153, 0.4);
        color: #6EE7B7;
        font-weight: 800;
        font-size: 13px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .kop-logout-action {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 9.5px 12px;
        border-radius: 10px;
        background: rgba(239, 68, 68, 0.16);
        border: 1px solid rgba(248, 113, 113, 0.32);
        color: #FCA5A5;
        text-decoration: none;
        font-size: 12.5px;
        font-weight: 700;
        white-space: nowrap;
        transition: background-color 0.18s ease, color 0.18s ease, border-color 0.18s ease;
    }
    .kop-logout-action:hover {
        background: #DC2626;
        border-color: #EF4444;
        color: #FFFFFF;
    }

    /* MODE RINGKAS / MINI RAIL DESKTOP (>= 768px) */
    @media (min-width: 768px) {
        body.kop-rail-mini #mainSidebar {
            width: var(--kop-sb-mini) !important;
        }
        body.kop-rail-mini > main,
        body.kop-rail-mini .kop-main {
            margin-left: var(--kop-sb-mini) !important;
            width: calc(100% - var(--kop-sb-mini)) !important;
        }
        body.kop-rail-mini .kop-rail-handle svg {
            transform: rotate(180deg);
        }
        body.kop-rail-mini .kop-brand-bar {
            justify-content: center;
            padding: 18px 10px 16px;
        }
        body.kop-rail-mini .kop-hide-mini,
        body.kop-rail-mini .kop-sec-title {
            display: none !important;
        }
        body.kop-rail-mini .kop-sec-mini-line {
            display: block !important;
        }
        body.kop-rail-mini .kop-sb-scroll {
            padding: 14px 10px;
            gap: 12px;
        }
        body.kop-rail-mini .kop-link {
            justify-content: center;
            padding: 11px 0;
            width: 44px;
            height: 44px;
            margin: 0 auto;
        }
        body.kop-rail-mini .kop-link:hover {
            transform: none;
        }
        body.kop-rail-mini .kop-link.active::before {
            top: 9px;
            bottom: 9px;
        }
        body.kop-rail-mini .kop-sb-footer {
            padding: 12px 10px 14px;
            align-items: center;
        }
        body.kop-rail-mini .kop-user-strip {
            justify-content: center;
        }
        body.kop-rail-mini .kop-logout-action {
            width: 42px;
            height: 40px;
            padding: 0;
        }
        .kop-mobile-only {
            display: none !important;
        }
        .kop-desktop-only {
            display: flex !important;
        }
    }

    /* FLOATING TOOLTIP SAAT MODE MINI RAIL */
    #kopRailTooltip {
        position: fixed;
        left: 86px;
        transform: translateY(-50%);
        background: #021A14;
        color: #FFFFFF;
        border: 1px solid rgba(245, 158, 11, 0.55);
        padding: 6px 12px;
        border-radius: 9px;
        font-size: 12px;
        font-weight: 700;
        white-space: nowrap;
        pointer-events: none;
        opacity: 0;
        transition: opacity 0.14s ease;
        box-shadow: 0 10px 24px rgba(0, 0, 0, 0.25);
        z-index: 9999;
    }

    /* BACKDROP MOBILE */
    #kopMobileBackdrop {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(2, 26, 20, 0.58);
        z-index: 90;
    }

    /* MODE DRAWER MOBILE (< 768px) */
    @media (max-width: 767px) {
        #mainSidebar {
            width: 270px !important;
            transform: translateX(-105%) !important;
        }
        #mainSidebar.mobile-open {
            transform: translateX(0) !important;
        }
        body > main,
        .kop-main {
            margin-left: 0 !important;
            width: 100% !important;
        }
        .kop-desktop-only {
            display: none !important;
        }
        .kop-mobile-only {
            display: inline-flex !important;
        }
        .kop-topbar-portal-link {
            display: none !important;
        }
        .kop-dashboard-wrap .mobile-nav-trigger {
            display: none !important;
        }
    }
</style>

<!-- TOOLTIP KHUSUS MODE RINGKAS -->
<div id="kopRailTooltip"></div>

<!-- BACKDROP GELAP MOBILE -->
<div id="kopMobileBackdrop" onclick="kopToggleSidebar(false)"></div>

<aside id="mainSidebar" aria-label="Navigasi Utama">
    <!-- EDGE RAIL HANDLE (DESKTOP BUKA/TUTUP) -->
    <button type="button" class="kop-rail-handle kop-desktop-only" onclick="kopToggleSidebar()" title="Buka / Tutup Sidebar" aria-label="Buka atau Tutup Sidebar">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="15 18 9 12 15 6"></polyline>
        </svg>
    </button>

    <!-- BRAND HEADER -->
    <div class="kop-brand-bar">
        <a href="<?= BASE_URL ?>/modules/dashboard/index.php" style="display:flex; align-items:center; gap:11px; text-decoration:none; min-width:0;">
            <div class="kop-logo-mark">K</div>
            <div class="kop-hide-mini" style="min-width:0; line-height:1.2;">
                <div style="font-size:16px; font-weight:800; color:#FFFFFF; letter-spacing:0.4px;">KOPNOVA</div>
                <div style="font-size:10.5px; color:#FBBF24; font-weight:700; margin-top:2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                    <?= !empty($user['nama_divisi']) ? htmlspecialchars($user['nama_divisi']) : 'Agri Supply OS' ?>
                </div>
            </div>
        </a>

        <!-- TOMBOL TUTUP KHUSUS HP -->
        <button type="button" class="kop-mobile-only" onclick="kopToggleSidebar(false)" aria-label="Tutup Menu" style="width:32px; height:32px; border-radius:9px; background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.16); color:#E2E8F0; align-items:center; justify-content:center; cursor:pointer; flex-shrink:0;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
    </div>

    <!-- MENU NAVIGASI PER SECTION -->
    <nav class="kop-sb-scroll">

        <!-- SECTION 1: RINGKASAN -->
        <div>
            <div class="kop-sec-title">Ringkasan</div>
            <div class="kop-menu-stack">
                <a href="<?= BASE_URL ?>/modules/dashboard/index.php" class="<?= kopNavClass(strpos($uri, 'dashboard') !== false) ?>" data-tip="Dashboard <?= ucfirst(htmlspecialchars($role)) ?>">
                    <span style="display:flex; align-items:center; gap:11px; min-width:0;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/></svg>
                        <span class="kop-hide-mini" style="overflow:hidden; text-overflow:ellipsis;">Dashboard <?= ucfirst(htmlspecialchars($role)) ?></span>
                    </span>
                </a>
            </div>
        </div>

        <?php if ($role === 'konsumen'): ?>
            <!-- SECTION 2: MASTER DATA & WILAYAH -->
            <div>
                <div class="kop-sec-title">Master Data &amp; Wilayah</div>
                <div class="kop-sec-mini-line"></div>
                <div class="kop-menu-stack">
                    <a href="<?= BASE_URL ?>/modules/wilayah/index.php" class="<?= kopNavClass(strpos($uri, 'wilayah') !== false) ?>" data-tip="Divisi Wilayah & Primer">
                        <span style="display:flex; align-items:center; gap:11px; min-width:0;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                            <span class="kop-hide-mini" style="overflow:hidden; text-overflow:ellipsis;">Divisi Wilayah &amp; Primer</span>
                        </span>
                    </a>
                    <a href="<?= BASE_URL ?>/modules/users/index.php" class="<?= kopNavClass(strpos($uri, 'users') !== false) ?>" data-tip="Manajemen Akun & User">
                        <span style="display:flex; align-items:center; gap:11px; min-width:0;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            <span class="kop-hide-mini" style="overflow:hidden; text-overflow:ellipsis;">Manajemen Akun &amp; User</span>
                        </span>
                    </a>
                    <a href="<?= BASE_URL ?>/modules/produk/index.php" class="<?= kopNavClass(strpos($uri, 'produk') !== false) ?>" data-tip="Katalog Produk Nasional">
                        <span style="display:flex; align-items:center; gap:11px; min-width:0;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                            <span class="kop-hide-mini" style="overflow:hidden; text-overflow:ellipsis;">Katalog Produk Nasional</span>
                        </span>
                    </a>
                </div>
            </div>

            <!-- SECTION 3: OPERASIONAL & LOGISTIK -->
            <div>
                <div class="kop-sec-title">Operasional &amp; Logistik</div>
                <div class="kop-sec-mini-line"></div>
                <div class="kop-menu-stack">
                    <a href="<?= BASE_URL ?>/modules/early_warning/index.php" class="<?= kopNavClass(strpos($uri, 'early_warning') !== false) ?>" data-tip="Early Warning & Restock">
                        <span style="display:flex; align-items:center; gap:11px; min-width:0;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                            <span class="kop-hide-mini" style="overflow:hidden; text-overflow:ellipsis;">Early Warning &amp; Restock</span>
                        </span>
                    </a>
                    <a href="<?= BASE_URL ?>/modules/order_routing/index.php" class="<?= kopNavClass(strpos($uri, 'order_routing') !== false) ?>" data-tip="Order Routing & Surat Jalan">
                        <span style="display:flex; align-items:center; gap:11px; min-width:0;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1"><rect x="1" y="3" width="15" height="13" rx="2"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                            <span class="kop-hide-mini" style="overflow:hidden; text-overflow:ellipsis;">Order Routing &amp; Surat Jalan</span>
                        </span>
                    </a>
                    <a href="<?= BASE_URL ?>/modules/transaksi/index.php" class="<?= kopNavClass(strpos($uri, 'transaksi') !== false) ?>" data-tip="Semua Transaksi Masuk">
                        <span style="display:flex; align-items:center; gap:11px; min-width:0;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                            <span class="kop-hide-mini" style="overflow:hidden; text-overflow:ellipsis;">Semua Transaksi Masuk</span>
                        </span>
                    </a>
                </div>
            </div>

            <!-- SECTION 4: KEUANGAN & KEBIJAKAN -->
            <div>
                <div class="kop-sec-title">Keuangan &amp; Kebijakan</div>
                <div class="kop-sec-mini-line"></div>
                <div class="kop-menu-stack">
                    <a href="<?= BASE_URL ?>/modules/regulasi_harga/index.php" class="<?= kopNavClass(strpos($uri, 'regulasi_harga') !== false) ?>" data-tip="Harga Acuan & Margin SHU">
                        <span style="display:flex; align-items:center; gap:11px; min-width:0;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                            <span class="kop-hide-mini" style="overflow:hidden; text-overflow:ellipsis;">Harga Acuan &amp; Margin SHU</span>
                        </span>
                    </a>
                    <a href="<?= BASE_URL ?>/modules/settlement/index.php" class="<?= kopNavClass(strpos($uri, 'settlement') !== false) ?>" data-tip="Keuangan & Settlement Dana">
                        <span style="display:flex; align-items:center; gap:11px; min-width:0;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/></svg>
                            <span class="kop-hide-mini" style="overflow:hidden; text-overflow:ellipsis;">Keuangan &amp; Settlement Dana</span>
                        </span>
                    </a>
                    <a href="<?= BASE_URL ?>/modules/laporan_eksekutif/index.php" class="<?= kopNavClass(strpos($uri, 'laporan_eksekutif') !== false) ?>" data-tip="Ekspor Laporan & Audit Trail">
                        <span style="display:flex; align-items:center; gap:11px; min-width:0;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                            <span class="kop-hide-mini" style="overflow:hidden; text-overflow:ellipsis;">Ekspor Laporan &amp; Audit Trail</span>
                        </span>
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($role === 'primer'): ?>
            <!-- SECTION 2: HULU & STOK UNIT -->
            <div>
                <div class="kop-sec-title">Hulu &amp; Stok Unit</div>
                <div class="kop-sec-mini-line"></div>
                <div class="kop-menu-stack">
                    <a href="<?= BASE_URL ?>/modules/pasokan/index.php" class="<?= kopNavClass(strpos($uri, 'pasokan') !== false) ?>" data-tip="Verifikasi Pasokan Petani">
                        <span style="display:flex; align-items:center; gap:11px; min-width:0;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                            <span class="kop-hide-mini" style="overflow:hidden; text-overflow:ellipsis;">Verifikasi Pasokan Petani</span>
                        </span>
                    </a>
                    <a href="<?= BASE_URL ?>/modules/early_warning/index.php" class="<?= kopNavClass(strpos($uri, 'early_warning') !== false) ?>" data-tip="Instruksi Restock Pusat">
                        <span style="display:flex; align-items:center; gap:11px; min-width:0;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                            <span class="kop-hide-mini" style="overflow:hidden; text-overflow:ellipsis;">Instruksi Restock Pusat</span>
                        </span>
                    </a>
                    <a href="<?= BASE_URL ?>/modules/produk/index.php" class="<?= kopNavClass(strpos($uri, 'produk') !== false) ?>" data-tip="Upload & Etalase Produk">
                        <span style="display:flex; align-items:center; gap:11px; min-width:0;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                            <span class="kop-hide-mini" style="overflow:hidden; text-overflow:ellipsis;">Upload &amp; Etalase Produk</span>
                        </span>
                    </a>
                </div>
            </div>

            <!-- SECTION 3: DISTRIBUSI & ORDER -->
            <div>
                <div class="kop-sec-title">Distribusi &amp; Order</div>
                <div class="kop-sec-mini-line"></div>
                <div class="kop-menu-stack">
                    <a href="<?= BASE_URL ?>/modules/order_routing/index.php" class="<?= kopNavClass(strpos($uri, 'order_routing') !== false) ?>" data-tip="Picking List Kecamatan">
                        <span style="display:flex; align-items:center; gap:11px; min-width:0;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1"><rect x="1" y="3" width="15" height="13" rx="2"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                            <span class="kop-hide-mini" style="overflow:hidden; text-overflow:ellipsis;">Picking List Kecamatan</span>
                        </span>
                    </a>
                    <a href="<?= BASE_URL ?>/modules/transaksi/index.php" class="<?= kopNavClass(strpos($uri, 'transaksi') !== false) ?>" data-tip="Pesanan Wilayah">
                        <span style="display:flex; align-items:center; gap:11px; min-width:0;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                            <span class="kop-hide-mini" style="overflow:hidden; text-overflow:ellipsis;">Pesanan Wilayah</span>
                        </span>
                    </a>
                </div>
            </div>

            <!-- SECTION 4: KEUANGAN & LAPORAN -->
            <div>
                <div class="kop-sec-title">Keuangan &amp; Laporan</div>
                <div class="kop-sec-mini-line"></div>
                <div class="kop-menu-stack">
                    <a href="<?= BASE_URL ?>/modules/settlement/index.php" class="<?= kopNavClass(strpos($uri, 'settlement') !== false) ?>" data-tip="Pencairan Dana & Kas Unit">
                        <span style="display:flex; align-items:center; gap:11px; min-width:0;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/></svg>
                            <span class="kop-hide-mini" style="overflow:hidden; text-overflow:ellipsis;">Pencairan Dana &amp; Kas Unit</span>
                        </span>
                    </a>
                    <a href="<?= BASE_URL ?>/modules/regulasi_harga/index.php" class="<?= kopNavClass(strpos($uri, 'regulasi_harga') !== false) ?>" data-tip="Standar Harga Pusat (HAP)">
                        <span style="display:flex; align-items:center; gap:11px; min-width:0;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                            <span class="kop-hide-mini" style="overflow:hidden; text-overflow:ellipsis;">Standar Harga Pusat (HAP)</span>
                        </span>
                    </a>
                    <a href="<?= BASE_URL ?>/modules/laporan_eksekutif/index.php" class="<?= kopNavClass(strpos($uri, 'laporan_eksekutif') !== false) ?>" data-tip="Ekspor Laporan Unit">
                        <span style="display:flex; align-items:center; gap:11px; min-width:0;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                            <span class="kop-hide-mini" style="overflow:hidden; text-overflow:ellipsis;">Ekspor Laporan Unit</span>
                        </span>
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($role === 'petani'): ?>
            <!-- SECTION 2: AKTIVITAS PANEN -->
            <div>
                <div class="kop-sec-title">Aktivitas Panen</div>
                <div class="kop-sec-mini-line"></div>
                <div class="kop-menu-stack">
                    <a href="<?= BASE_URL ?>/modules/pasokan/index.php" class="<?= kopNavClass(strpos($uri, 'pasokan') !== false) ?>" data-tip="Setor Hasil Panen Baru">
                        <span style="display:flex; align-items:center; gap:11px; min-width:0;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
                            <span class="kop-hide-mini" style="overflow:hidden; text-overflow:ellipsis;">Setor Hasil Panen Baru</span>
                        </span>
                    </a>
                    <a href="<?= BASE_URL ?>/modules/early_warning/index.php" class="<?= kopNavClass(strpos($uri, 'early_warning') !== false) ?>" data-tip="Target Panen & Restock">
                        <span style="display:flex; align-items:center; gap:11px; min-width:0;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                            <span class="kop-hide-mini" style="overflow:hidden; text-overflow:ellipsis;">Target Panen &amp; Restock</span>
                        </span>
                    </a>
                </div>
            </div>

            <!-- SECTION 3: PENDAPATAN & ACUAN -->
            <div>
                <div class="kop-sec-title">Pendapatan &amp; Acuan</div>
                <div class="kop-sec-mini-line"></div>
                <div class="kop-menu-stack">
                    <a href="<?= BASE_URL ?>/modules/settlement/index.php" class="<?= kopNavClass(strpos($uri, 'settlement') !== false) ?>" data-tip="Rekap Pencairan Hasil Panen">
                        <span style="display:flex; align-items:center; gap:11px; min-width:0;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/></svg>
                            <span class="kop-hide-mini" style="overflow:hidden; text-overflow:ellipsis;">Rekap Pencairan Hasil Panen</span>
                        </span>
                    </a>
                    <a href="<?= BASE_URL ?>/modules/regulasi_harga/index.php" class="<?= kopNavClass(strpos($uri, 'regulasi_harga') !== false) ?>" data-tip="Acuan Harga Beli (HAP)">
                        <span style="display:flex; align-items:center; gap:11px; min-width:0;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                            <span class="kop-hide-mini" style="overflow:hidden; text-overflow:ellipsis;">Acuan Harga Beli (HAP)</span>
                        </span>
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($role === 'buyer'): ?>
            <!-- SECTION 2: MARKETPLACE & ORDER -->
            <div>
                <div class="kop-sec-title">Marketplace &amp; Order</div>
                <div class="kop-sec-mini-line"></div>
                <div class="kop-menu-stack">
                    <a href="<?= BASE_URL ?>/modules/marketplace/index.php" class="<?= kopNavClass(strpos($uri, 'marketplace/index') !== false) ?>" data-tip="Belanja Komoditas">
                        <span style="display:flex; align-items:center; gap:11px; min-width:0;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/></svg>
                            <span class="kop-hide-mini" style="overflow:hidden; text-overflow:ellipsis;">Belanja Komoditas</span>
                        </span>
                    </a>
                    <a href="<?= BASE_URL ?>/modules/marketplace/keranjang.php" class="<?= kopNavClass(strpos($uri, 'keranjang') !== false) ?>" data-tip="Keranjang Belanja (<?= (int)$cart_count ?>)">
                        <span style="display:flex; align-items:center; gap:11px; min-width:0;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                            <span class="kop-hide-mini" style="overflow:hidden; text-overflow:ellipsis;">Keranjang Belanja</span>
                        </span>
                        <span class="kop-hide-mini" style="background:#F59E0B; color:#021A14; font-size:10.5px; font-weight:800; padding:2px 8px; border-radius:99px; flex-shrink:0;">
                            <?= (int)$cart_count ?>
                        </span>
                    </a>
                    <a href="<?= BASE_URL ?>/modules/transaksi/index.php" class="<?= kopNavClass(strpos($uri, 'transaksi') !== false) ?>" data-tip="Riwayat Pembelian">
                        <span style="display:flex; align-items:center; gap:11px; min-width:0;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                            <span class="kop-hide-mini" style="overflow:hidden; text-overflow:ellipsis;">Riwayat Pembelian</span>
                        </span>
                    </a>
                </div>
            </div>
        <?php endif; ?>

    </nav>

    <!-- FOOTER AKUN & KELUAR SESI -->
    <div class="kop-sb-footer">
        <div class="kop-user-strip" title="<?= htmlspecialchars($user['nama_lengkap'] ?? 'Pengguna KOPNOVA') ?>">
            <div class="kop-avatar-box"><?= htmlspecialchars($initial) ?></div>
            <div class="kop-hide-mini" style="min-width:0; line-height:1.25;">
                <div style="font-size:12px; font-weight:700; color:#FFFFFF; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                    <?= htmlspecialchars($user['nama_lengkap'] ?? 'Pengguna') ?>
                </div>
                <div style="font-size:10.5px; color:#6EE7B7; font-weight:600; text-transform:capitalize; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                    Role: <?= htmlspecialchars($role) ?>
                </div>
            </div>
        </div>

        <a href="<?= BASE_URL ?>/logout.php" class="kop-logout-action" title="Keluar Sesi">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" style="flex-shrink:0;"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            <span class="kop-hide-mini">Keluar Sesi</span>
        </a>
    </div>
</aside>

<main class="kop-main">
    <!-- TOPBAR WORKSPACE -->
    <header style="background:#FFFFFF; padding:13px 26px; border-bottom:1px solid #E2E8F0; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; position:sticky; top:0; z-index:40;">
        <div style="display:flex; align-items:center; gap:12px; min-width:0;">
            <!-- TOMBOL MENU KHUSUS HP -->
            <button type="button" onclick="kopToggleSidebar(true)" title="Buka Menu" class="kop-mobile-only" style="width:36px; height:36px; border-radius:10px; background:#042F24; border:none; color:#FFFFFF; align-items:center; justify-content:center; cursor:pointer; flex-shrink:0;">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>

            <div style="min-width:0;">
                <div style="font-size:11.5px; color:#64748B; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                    <?= $tgl_indo ?> • Pusat Kendali Rantai Pasok
                </div>
                <div style="font-size:15.5px; font-weight:800; color:#0F172A; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                    <?= htmlspecialchars($user['nama_lengkap'] ?? 'Pengguna KOPNOVA') ?>
                </div>
            </div>
        </div>

        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
            <a href="<?= BASE_URL ?>/index.php" class="kop-topbar-portal-link" style="background:#F8FAFC; border:1px solid #CBD5E1; color:#334155; text-decoration:none; padding:7px 14px; border-radius:99px; font-size:12px; font-weight:700; white-space:nowrap;">
                Portal Utama ↗
            </a>
            <?php if ($role === 'buyer'): ?>
                <a href="<?= BASE_URL ?>/modules/marketplace/keranjang.php" style="background:#FEF3C7; border:1px solid #FDE68A; color:#92400E; text-decoration:none; padding:6px 13px; border-radius:99px; font-size:12px; font-weight:800; white-space:nowrap;">
                    Keranjang (<?= (int)$cart_count ?>)
                </a>
            <?php endif; ?>
            <span style="background:#DCFCE7; border:1px solid #86EFAC; color:#166534; font-size:11.5px; font-weight:800; padding:6px 13px; border-radius:99px; letter-spacing:0.2px; white-space:nowrap;">
                <?= htmlspecialchars($role_badge) ?>
            </span>
        </div>
    </header>

    <div style="padding:24px 26px 40px; flex:1; max-width:1380px; width:100%; box-sizing:border-box; margin:0 auto;">

<script>
    (function () {
        const STORAGE_KEY = 'kop_rail_mini';
        const sidebar  = document.getElementById('mainSidebar');
        const backdrop = document.getElementById('kopMobileBackdrop');
        const tooltip  = document.getElementById('kopRailTooltip');

        if (window.innerWidth >= 768 && localStorage.getItem(STORAGE_KEY) === '1') {
            document.body.classList.add('kop-rail-mini');
        }

        window.kopToggleSidebar = function (forceState) {
            if (!sidebar) return;

            if (window.innerWidth < 768) {
                const isOpen = (typeof forceState === 'boolean')
                    ? forceState
                    : !sidebar.classList.contains('mobile-open');

                sidebar.classList.toggle('mobile-open', isOpen);
                if (backdrop) backdrop.style.display = isOpen ? 'block' : 'none';
                document.body.style.overflow = isOpen ? 'hidden' : '';
            } else {
                const isMini = (typeof forceState === 'boolean')
                    ? !forceState
                    : !document.body.classList.contains('kop-rail-mini');

                document.body.classList.toggle('kop-rail-mini', isMini);
                localStorage.setItem(STORAGE_KEY, isMini ? '1' : '0');
                if (!isMini && tooltip) tooltip.style.opacity = '0';
            }
        };

        window.toggleKopSidebar = window.kopToggleSidebar;

        document.querySelectorAll('#mainSidebar .kop-link').forEach(function (link) {
            link.addEventListener('mouseenter', function () {
                if (window.innerWidth >= 768 && document.body.classList.contains('kop-rail-mini') && tooltip) {
                    const rect = link.getBoundingClientRect();
                    tooltip.textContent = link.getAttribute('data-tip') || '';
                    tooltip.style.top = (rect.top + rect.height / 2) + 'px';
                    tooltip.style.opacity = '1';
                }
            });
            link.addEventListener('mouseleave', function () {
                if (tooltip) tooltip.style.opacity = '0';
            });
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && window.innerWidth < 768) {
                window.kopToggleSidebar(false);
            }
        });

        window.addEventListener('resize', function () {
            if (window.innerWidth >= 768) {
                if (sidebar) sidebar.classList.remove('mobile-open');
                if (backdrop) backdrop.style.display = 'none';
                document.body.style.overflow = '';
            } else {
                document.body.classList.remove('kop-rail-mini');
                if (tooltip) tooltip.style.opacity = '0';
            }
        });

        const activeLink = document.querySelector('#mainSidebar .kop-link.active');
        if (activeLink) {
            activeLink.scrollIntoView({ block: 'nearest' });
        }
    })();
</script>