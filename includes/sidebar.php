<?php
$uri = $_SERVER['REQUEST_URI'];
function navStyle($active) {
    return $active
        ? "display:flex; align-items:center; gap:12px; padding:11px 14px; border-radius:12px; text-decoration:none; font-size:13.5px; font-weight:700; color:#FFFFFF; background:linear-gradient(90deg, #10B981 0%, #059669 100%); box-shadow:0 6px 16px rgba(16,185,129,0.28);"
        : "display:flex; align-items:center; gap:12px; padding:11px 14px; border-radius:12px; text-decoration:none; font-size:13.5px; font-weight:500; color:#CBD5E1; background:transparent;";
}
?>
<aside id="mainSidebar" style="width:268px; height:100vh; position:sticky; top:0; background:linear-gradient(180deg, #063E2E 0%, #042B20 100%); color:#FFFFFF; display:flex; flex-direction:column; justify-content:space-between; flex-shrink:0; border-right:1px solid rgba(255,255,255,0.08); box-sizing:border-box; z-index:50;">
    
    <!-- BAGIAN ATAS: 1 LOGO UTAMA & MENU NAVIGASI -->
    <div style="display:flex; flex-direction:column; flex:1; overflow-y:auto;">
        
        <!-- BRAND HEADER -->
        <div style="padding:22px 20px; border-bottom:1px solid rgba(255,255,255,0.08); display:flex; align-items:center; gap:12px; flex-shrink:0;">
            <div style="width:40px; height:40px; background:linear-gradient(135deg, #10B981, #F59E0B); border-radius:12px; display:flex; align-items:center; justify-content:center; color:#042B20; font-weight:900; font-size:19px; box-shadow:0 4px 12px rgba(16,185,129,0.3);">K</div>
            <div>
                <div style="font-size:18px; font-weight:900; letter-spacing:0.6px; color:#FFFFFF; line-height:1.1;">KOPNOVA</div>
                <div style="font-size:11px; color:#6EE7B7; margin-top:2px; font-weight:600;">
                    <?= $user['nama_divisi'] ? htmlspecialchars($user['nama_divisi']) : 'Ekosistem Terpadu' ?>
                </div>
            </div>
        </div>

        <!-- MENU NAVIGASI RBAC -->
        <div style="padding:18px 14px; display:flex; flex-direction:column; gap:6px;">
            <div style="font-size:10.5px; text-transform:uppercase; letter-spacing:1.2px; color:#6EE7B7; padding:2px 10px 6px; font-weight:800; opacity:0.85;">
                Navigasi Workspace
            </div>

            <a href="<?= BASE_URL ?>/modules/dashboard/index.php" style="<?= navStyle(strpos($uri, 'dashboard') !== false) ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/></svg>
                <span>Dashboard <?= ucfirst($role) ?></span>
            </a>

            <?php if ($role === 'konsumen'): ?>
                <div style="font-size:10.5px; text-transform:uppercase; letter-spacing:1.2px; color:#6EE7B7; padding:14px 10px 4px; font-weight:800; opacity:0.85;">
                    Tata Kelola &amp; Pusat
                </div>
                <a href="<?= BASE_URL ?>/modules/wilayah/index.php" style="<?= navStyle(strpos($uri, 'wilayah') !== false) ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    <span>Divisi Wilayah &amp; Primer</span>
                </a>
                <a href="<?= BASE_URL ?>/modules/users/index.php" style="<?= navStyle(strpos($uri, 'users') !== false) ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    <span>Manajemen Akun &amp; User</span>
                </a>
                <a href="<?= BASE_URL ?>/modules/regulasi_harga/index.php" style="<?= navStyle(strpos($uri, 'regulasi_harga') !== false) ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    <span>Harga Acuan &amp; Margin SHU</span>
                </a>
                <a href="<?= BASE_URL ?>/modules/early_warning/index.php" style="<?= navStyle(strpos($uri, 'early_warning') !== false) ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    <span>Early Warning &amp; Restock</span>
                </a>
                <a href="<?= BASE_URL ?>/modules/order_routing/index.php" style="<?= navStyle(strpos($uri, 'order_routing') !== false) ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="1" y="3" width="15" height="13" rx="2"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                    <span>Order Routing &amp; Surat Jalan</span>
                </a>
                <a href="<?= BASE_URL ?>/modules/settlement/index.php" style="<?= navStyle(strpos($uri, 'settlement') !== false) ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/></svg>
                    <span>Keuangan &amp; Settlement Dana</span>
                </a>
                <a href="<?= BASE_URL ?>/modules/laporan_eksekutif/index.php" style="<?= navStyle(strpos($uri, 'laporan_eksekutif') !== false) ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    <span>Ekspor Laporan &amp; Audit Trail</span>
                </a>
                <a href="<?= BASE_URL ?>/modules/produk/index.php" style="<?= navStyle(strpos($uri, 'produk') !== false) ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                    <span>Katalog Produk Nasional</span>
                </a>
                <a href="<?= BASE_URL ?>/modules/transaksi/index.php" style="<?= navStyle(strpos($uri, 'transaksi') !== false) ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                    <span>Semua Transaksi Masuk</span>
                </a>
            <?php endif; ?>

            <?php if ($role === 'primer'): ?>
                <div style="font-size:10.5px; text-transform:uppercase; letter-spacing:1.2px; color:#6EE7B7; padding:14px 10px 4px; font-weight:800; opacity:0.85;">
                    Operasional Kecamatan
                </div>
                <a href="<?= BASE_URL ?>/modules/pasokan/index.php" style="<?= navStyle(strpos($uri, 'pasokan') !== false) ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    <span>Verifikasi Pasokan Petani</span>
                </a>
                <a href="<?= BASE_URL ?>/modules/early_warning/index.php" style="<?= navStyle(strpos($uri, 'early_warning') !== false) ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    <span>Instruksi Restock Pusat</span>
                </a>
                <a href="<?= BASE_URL ?>/modules/produk/index.php" style="<?= navStyle(strpos($uri, 'produk') !== false) ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                    <span>Upload &amp; Etalase Produk</span>
                </a>
                <a href="<?= BASE_URL ?>/modules/order_routing/index.php" style="<?= navStyle(strpos($uri, 'order_routing') !== false) ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="1" y="3" width="15" height="13" rx="2"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                    <span>Picking List Kecamatan</span>
                </a>
                <a href="<?= BASE_URL ?>/modules/settlement/index.php" style="<?= navStyle(strpos($uri, 'settlement') !== false) ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/></svg>
                    <span>Pencairan Dana &amp; Kas Unit</span>
                </a>
                <a href="<?= BASE_URL ?>/modules/laporan_eksekutif/index.php" style="<?= navStyle(strpos($uri, 'laporan_eksekutif') !== false) ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    <span>Ekspor Laporan Unit</span>
                </a>
                <a href="<?= BASE_URL ?>/modules/regulasi_harga/index.php" style="<?= navStyle(strpos($uri, 'regulasi_harga') !== false) ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    <span>Standar Harga Pusat (HAP)</span>
                </a>
                <a href="<?= BASE_URL ?>/modules/transaksi/index.php" style="<?= navStyle(strpos($uri, 'transaksi') !== false) ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    <span>Pesanan Wilayah</span>
                </a>
            <?php endif; ?>

            <?php if ($role === 'petani'): ?>
                <div style="font-size:10.5px; text-transform:uppercase; letter-spacing:1.2px; color:#6EE7B7; padding:14px 10px 4px; font-weight:800; opacity:0.85;">
                    Distribusi Panen
                </div>
                <a href="<?= BASE_URL ?>/modules/pasokan/index.php" style="<?= navStyle(strpos($uri, 'pasokan') !== false) ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
                    <span>Setor Hasil Panen Baru</span>
                </a>
                <a href="<?= BASE_URL ?>/modules/early_warning/index.php" style="<?= navStyle(strpos($uri, 'early_warning') !== false) ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    <span>Target Panen &amp; Restock</span>
                </a>
                <a href="<?= BASE_URL ?>/modules/settlement/index.php" style="<?= navStyle(strpos($uri, 'settlement') !== false) ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/></svg>
                    <span>Rekap Pencairan Hasil Panen</span>
                </a>
                <a href="<?= BASE_URL ?>/modules/regulasi_harga/index.php" style="<?= navStyle(strpos($uri, 'regulasi_harga') !== false) ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    <span>Acuan Harga Beli (HAP)</span>
                </a>
            <?php endif; ?>

            <?php if ($role === 'buyer'): ?>
                <div style="font-size:10.5px; text-transform:uppercase; letter-spacing:1.2px; color:#6EE7B7; padding:14px 10px 4px; font-weight:800; opacity:0.85;">
                    Marketplace Agribisnis
                </div>
                <a href="<?= BASE_URL ?>/modules/marketplace/index.php" style="<?= navStyle(strpos($uri, 'marketplace/index') !== false) ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/></svg>
                    <span>Belanja Komoditas</span>
                </a>
                <a href="<?= BASE_URL ?>/modules/marketplace/keranjang.php" style="<?= navStyle(strpos($uri, 'keranjang') !== false) ?> justify-content:space-between;">
                    <span style="display:flex; align-items:center; gap:12px;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                        <span>Keranjang Belanja</span>
                    </span>
                    <span style="background:#F59E0B; color:#0F172A; font-size:11px; font-weight:900; padding:2px 8px; border-radius:99px;"><?= $cart_count ?></span>
                </a>
                <a href="<?= BASE_URL ?>/modules/transaksi/index.php" style="<?= navStyle(strpos($uri, 'transaksi') !== false) ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                    <span>Riwayat Pembelian</span>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- TOMBOL LOGOUT SELALU TERKUNCI RAPI DI BAWAH LAYAR -->
    <div style="padding:16px; border-top:1px solid rgba(255,255,255,0.08); background:#042B20; flex-shrink:0;">
        <a href="<?= BASE_URL ?>/logout.php" style="display:flex; align-items:center; justify-content:center; gap:8px; padding:11px; background:#DC2626; color:#FFFFFF; text-decoration:none; border-radius:12px; font-size:13px; font-weight:700; box-shadow:0 4px 12px rgba(220,38,38,0.3);">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            <span>Keluar Sesi</span>
        </a>
    </div>
</aside>

<main style="flex:1; display:flex; flex-direction:column; min-width:0; background:#F1F5F3;">
    <!-- TOPBAR MODERN -->
    <header style="background:#FFFFFF; padding:14px 28px; border-bottom:1px solid #E2E8F0; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; position:sticky; top:0; z-index:40;">
        <div>
            <div style="font-size:11.5px; color:#64748B; font-weight:600;">
                <?= date('l, d F Y') ?> • Pusat Kendali Rantai Pasok
            </div>
            <div style="font-size:16px; font-weight:800; color:#0F172A;">
                <?= htmlspecialchars($user['nama_lengkap']) ?>
            </div>
        </div>

        <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
            <a href="<?= BASE_URL ?>/index.php" style="background:#F8FAFC; border:1px solid #CBD5E1; color:#334155; text-decoration:none; padding:7px 14px; border-radius:99px; font-size:12px; font-weight:700;">
                Lihat Portal Utama ↗
            </a>
            <?php if ($role === 'buyer'): ?>
                <a href="<?= BASE_URL ?>/modules/marketplace/keranjang.php" style="background:#FEF3C7; border:1px solid #FDE68A; color:#92400E; text-decoration:none; padding:7px 14px; border-radius:99px; font-size:12px; font-weight:800;">
                    Keranjang (<?= $cart_count ?>)
                </a>
            <?php endif; ?>
            <span style="background:#DCFCE7; border:1px solid #86EFAC; color:#166534; font-size:11.5px; font-weight:800; padding:7px 14px; border-radius:99px; letter-spacing:0.3px;">
                <?= htmlspecialchars($role_badge) ?>
            </span>
        </div>
    </header>

    <div style="padding:26px 28px 42px; flex:1; max-width:1320px; width:100%; box-sizing:border-box; margin:0 auto;">