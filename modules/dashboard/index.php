<?php
require_once '../../config/database.php';

// FITUR INTERAKTIF: Ubah Status Pesanan Langsung dari Dashboard (Khusus Konsumen & Primer)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status_order'])) {
    if (in_array($_SESSION['user']['role'], ['konsumen', 'primer'])) {
        $stmtUpd = $pdo->prepare("UPDATE orders SET status_order = ? WHERE id_order = ?");
        $stmtUpd->execute([$_POST['status_order'], (int)$_POST['id_order']]);
        header("Location: index.php?msg=" . urlencode("Status pesanan berhasil diubah menjadi: " . $_POST['status_order']));
        exit;
    }
}

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

$error = $_GET['error'] ?? '';
$msg   = $_GET['msg'] ?? '';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    /* PERAPIAN SIDEBAR AGAR BERSIH & 1 BARIS */
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

    /* LAYOUT & KARTU CLEAN MODERN */
    .kop-grid-4 {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
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
    .kop-card {
        background: #FFFFFF;
        border-radius: 18px;
        border: 1px solid #E2E8F0;
        box-shadow: 0 2px 10px rgba(15, 23, 42, 0.03);
    }
    .kop-action-card {
        background: #FFFFFF;
        border-radius: 16px;
        border: 1px solid #E2E8F0;
        padding: 15px 16px;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 13px;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.025);
        transition: all 0.2s ease;
    }
    .kop-action-card:hover {
        transform: translateY(-2px);
        border-color: #10B981;
        background: #F0FDF4;
        box-shadow: 0 8px 18px rgba(16, 185, 129, 0.1);
    }
    .kop-kpi-card {
        background: #FFFFFF;
        border-radius: 18px;
        border: 1px solid #E2E8F0;
        padding: 18px 20px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        box-shadow: 0 2px 10px rgba(15, 23, 42, 0.025);
        transition: transform 0.2s ease;
    }
    .kop-kpi-card:hover {
        transform: translateY(-2px);
    }
    .kop-kpi-value {
        font-size: clamp(1.2rem, 1.55vw, 1.5rem);
        font-weight: 800;
        color: #0F172A;
        letter-spacing: -0.4px;
        line-height: 1.25;
        margin: 10px 0 6px;
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
    }
    .kop-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        text-decoration: none;
        white-space: nowrap;
        transition: all 0.2s ease;
        cursor: pointer;
    }
    .kop-btn:hover {
        filter: brightness(1.05);
        transform: translateY(-1px);
    }
    .kop-pill {
        padding: 7px 14px;
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
    .kop-pill:hover {
        border-color: #10B981;
        color: #065F46;
    }
    .kop-pill.active {
        background: #059669;
        color: #FFFFFF;
        border-color: #059669;
        box-shadow: 0 3px 10px rgba(5, 150, 105, 0.22);
    }
    .kop-row-hover:hover {
        background-color: #F8FAFC;
    }
    .mobile-nav-trigger {
        display: none;
    }
    .kop-search-box {
        padding: 8px 13px;
        border: 1px solid #CBD5E1;
        border-radius: 10px;
        font-size: 12.5px;
        min-width: 200px;
        outline: none;
        transition: border-color 0.2s;
    }
    .kop-search-box:focus {
        border-color: #10B981;
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

    /* TABEL OTOMATIS JADI KARTU DI HP (TANPA GESER-GESER SAMPING) */
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
            border: 1px solid #E2E8F0 !important;
            border-radius: 14px;
            padding: 14px;
            margin-bottom: 12px;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03);
        }
        .kop-responsive-table td {
            padding: 5px 0 !important;
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
            color: #64748B;
            text-align: left;
            flex-shrink: 0;
        }
        .kop-responsive-table td.td-top-head {
            border-bottom: 1px dashed #E2E8F0 !important;
            padding-bottom: 9px !important;
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
        .kop-grid-4, .kop-grid-2 {
            grid-template-columns: minmax(0, 1fr);
        }
        .kop-hero-box {
            padding: 22px 20px !important;
        }
        .kop-hero-title {
            font-size: 19px !important;
        }
        .kop-hero-actions {
            width: 100%;
        }
        .kop-hero-actions a {
            flex: 1;
        }
        .kop-search-box {
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

<?php if ($error): ?>
<div style="background:#FEF2F2; border:1px solid #FECACA; border-left:5px solid #DC2626; color:#991B1B; padding:13px 18px; border-radius:12px; margin-bottom:18px; font-weight:700; font-size:13.5px; display:flex; align-items:center; gap:10px;">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <span><?= htmlspecialchars($error) ?></span>
</div>
<?php endif; ?>

<?php if ($msg): ?>
<div style="background:#ECFDF5; border:1px solid #A7F3D0; border-left:5px solid #059669; color:#065F46; padding:13px 18px; border-radius:12px; margin-bottom:18px; font-weight:700; font-size:13.5px; display:flex; align-items:center; gap:10px;">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
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

    <!-- 1. BANNER UTAMA YANG RAMAH & JELAS -->
    <div class="kop-hero-box" style="background:linear-gradient(125deg, #064E3B 0%, #059669 100%); border-radius:20px; padding:26px 30px; color:#FFFFFF; margin-bottom:18px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:18px; box-shadow:0 10px 25px rgba(5, 150, 105, 0.16); position:relative; overflow:hidden;">
        <div style="position:absolute; right:-30px; bottom:-50px; width:200px; height:200px; border-radius:50%; background:rgba(255,255,255,0.08); pointer-events:none;"></div>
        <div style="max-width:600px; position:relative; z-index:2;">
            <div style="display:inline-flex; align-items:center; gap:7px; background:rgba(255,255,255,0.16); color:#ECFDF5; font-size:11px; font-weight:800; padding:5px 12px; border-radius:99px; margin-bottom:10px;">
                <span style="width:7px; height:7px; border-radius:50%; background:#FBBF24; display:inline-block;"></span>
                <span>PUSAT KENDALI KOPERASI</span>
            </div>
            <h2 class="kop-hero-title" style="margin:0 0 6px; font-size:22px; font-weight:800; line-height:1.3;">
                Semua Urusan Koperasi Beres dalam Satu Layar
            </h2>
            <p style="margin:0; font-size:13.5px; color:#ECFDF5; line-height:1.55; opacity:0.95;">
                Cek stok hasil panen dari tiap daerah, pantau pesanan pembeli yang masuk, dan atur pengiriman barang dengan gampang.
            </p>
        </div>

        <div class="kop-hero-actions" style="display:flex; gap:10px; flex-wrap:wrap; position:relative; z-index:2;">
            <a href="../wilayah/index.php" class="kop-btn" style="background:#FBBF24; color:#0F172A; padding:11px 17px; border-radius:12px; font-weight:800; font-size:13px; box-shadow:0 4px 12px rgba(251,191,36,0.3);">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <span>Tambah Wilayah</span>
            </a>
            <a href="../early_warning/index.php" class="kop-btn" style="background:rgba(255,255,255,0.18); border:1px solid rgba(255,255,255,0.35); color:#FFFFFF; padding:11px 17px; border-radius:12px; font-weight:700; font-size:13px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                <span>Cek Stok Menipis (<?= count($ews_items) ?>)</span>
            </a>
        </div>
    </div>

    <!-- 2. MENU PINTASAN CEPAT (4 TOMBOL JELAS) -->
    <div class="kop-grid-4" style="margin-bottom:18px;">
        <a href="../order_routing/index.php" class="kop-action-card">
            <div style="width:40px; height:40px; border-radius:12px; background:#DCFCE7; color:#059669; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="1" y="3" width="15" height="13" rx="2"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
            </div>
            <div style="min-width:0; flex:1;">
                <div style="font-size:13.5px; font-weight:800; color:#0F172A; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">Atur Pengiriman</div>
                <div style="font-size:11.5px; color:#64748B; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-top:2px;">Buat surat jalan barang</div>
            </div>
        </a>

        <a href="../settlement/index.php" class="kop-action-card">
            <div style="width:40px; height:40px; border-radius:12px; background:#DBEAFE; color:#2563EB; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/></svg>
            </div>
            <div style="min-width:0; flex:1;">
                <div style="font-size:13.5px; font-weight:800; color:#0F172A; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">Bagi Hasil Dana</div>
                <div style="font-size:11.5px; color:#64748B; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-top:2px;">Cairkan uang ke petani</div>
            </div>
        </a>

        <a href="../regulasi_harga/index.php" class="kop-action-card">
            <div style="width:40px; height:40px; border-radius:12px; background:#FEF3C7; color:#D97706; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            </div>
            <div style="min-width:0; flex:1;">
                <div style="font-size:13.5px; font-weight:800; color:#0F172A; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">Atur Harga Jual</div>
                <div style="font-size:11.5px; color:#64748B; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-top:2px;">Patokan harga &amp; untung</div>
            </div>
        </a>

        <a href="../laporan_eksekutif/index.php" class="kop-action-card">
            <div style="width:40px; height:40px; border-radius:12px; background:#F3E8FF; color:#7C3AED; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            </div>
            <div style="min-width:0; flex:1;">
                <div style="font-size:13.5px; font-weight:800; color:#0F172A; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">Cetak Laporan</div>
                <div style="font-size:11.5px; color:#64748B; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-top:2px;">Unduh rekap Excel &amp; PDF</div>
            </div>
        </a>
    </div>

    <!-- 3. INFO STOK MENIPIS (OTOMATIS MUNCUL KALAU STOK < 50) -->
    <?php if (!empty($ews_items)): ?>
    <div style="background:#FFFBEB; border:1px solid #FDE68A; border-left:5px solid #F59E0B; border-radius:16px; padding:16px 20px; margin-bottom:18px;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:12px;">
            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                <span style="display:inline-flex; align-items:center; gap:6px; background:#DC2626; color:#FFFFFF; font-size:11px; font-weight:800; padding:4px 11px; border-radius:99px;">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    <span>STOK MAU HABIS</span>
                </span>
                <strong style="font-size:14px; color:#92400E;">
                    Ada <?= count($ews_items) ?> barang yang stoknya tinggal sedikit (di bawah 50)
                </strong>
            </div>
            <a href="../early_warning/index.php" class="kop-btn" style="background:#DC2626; color:#FFFFFF; padding:8px 15px; border-radius:10px; font-size:12px; font-weight:700;">
                <span>Minta Cabang Kirim Stok</span>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </a>
        </div>

        <div class="kop-grid-2">
            <?php foreach ($ews_items as $ew): ?>
            <div style="background:#FFFFFF; border:1px solid #FDE68A; border-radius:12px; padding:12px 15px; display:flex; justify-content:space-between; align-items:center; gap:12px;">
                <div style="min-width:0;">
                    <div style="font-size:13.5px; font-weight:800; color:#0F172A; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars($ew['nama_produk']) ?></div>
                    <div style="font-size:11.5px; color:#64748B; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-top:2px;"><?= htmlspecialchars($ew['nama_koperasi']) ?> • Sudah laku <?= (int)$ew['total_terjual'] ?> <?= htmlspecialchars($ew['satuan']) ?></div>
                </div>
                <div style="text-align:right; flex-shrink:0; background:#FEF2F2; padding:6px 12px; border-radius:10px;">
                    <div style="font-size:13.5px; font-weight:900; color:#DC2626; white-space:nowrap;">Sisa <?= (int)$ew['stok'] ?> <?= htmlspecialchars($ew['satuan']) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- 4. 4 KARTU RINGKASAN ANGKA UTAMA -->
    <div class="kop-grid-4" style="margin-bottom:20px;">
        <div class="kop-kpi-card">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                <span style="font-size:12.5px; color:#64748B; font-weight:700;">Wilayah Cabang</span>
                <div style="width:36px; height:36px; border-radius:11px; background:#DCFCE7; color:#059669; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                </div>
            </div>
            <div class="kop-kpi-value"><?= $jml_divisi ?> Kab/Kota</div>
            <div style="font-size:12px; color:#059669; font-weight:600;"><?= $jml_primer ?> koperasi cabang aktif</div>
        </div>

        <div class="kop-kpi-card">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                <span style="font-size:12.5px; color:#64748B; font-weight:700;">Total Stok Barang</span>
                <div style="width:36px; height:36px; border-radius:11px; background:#DBEAFE; color:#2563EB; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                </div>
            </div>
            <div class="kop-kpi-value"><?= number_format($total_stok, 0, ',', '.') ?> Unit</div>
            <div style="font-size:12px; color:#2563EB; font-weight:600;"><?= $jml_produk ?> jenis produk siap jual</div>
        </div>

        <div class="kop-kpi-card">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                <span style="font-size:12.5px; color:#64748B; font-weight:700;">Pesanan Masuk</span>
                <div style="width:36px; height:36px; border-radius:11px; background:#FEF3C7; color:#D97706; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                </div>
            </div>
            <div class="kop-kpi-value"><?= $total_order ?> Pesanan</div>
            <div style="font-size:12px; color:#D97706; font-weight:600;">Tercatat langsung di pusat</div>
        </div>

        <div class="kop-kpi-card">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                <span style="font-size:12.5px; color:#64748B; font-weight:700;">Total Uang Masuk</span>
                <div style="width:36px; height:36px; border-radius:11px; background:#F3E8FF; color:#7C3AED; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                </div>
            </div>
            <div class="kop-kpi-value" style="color:#064E3B;"><?= rupiah($total_omzet) ?></div>
            <div style="font-size:12px; color:#7C3AED; font-weight:600;">QRIS, Transfer &amp; Tempo</div>
        </div>
    </div>

    <!-- 5. GRAFIK STOK & JENIS PRODUK -->
    <div class="kop-grid-2-1" style="margin-bottom:20px;">
        <div class="kop-card" style="padding:22px 24px; display:flex; flex-direction:column; justify-content:space-between;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:14px; flex-wrap:wrap; gap:8px;">
                <div>
                    <h3 style="margin:0; font-size:16px; font-weight:800; color:#0F172A;">Stok Barang per Cabang Koperasi</h3>
                    <div style="font-size:12.5px; color:#64748B; margin-top:2px;">Lihat cabang kecamatan mana yang paling banyak menyetor barang ke toko pusat.</div>
                </div>
                <span style="display:inline-flex; align-items:center; gap:6px; background:#DCFCE7; color:#166534; font-size:11px; font-weight:800; padding:4px 10px; border-radius:99px;">
                    <span style="width:6px; height:6px; border-radius:50%; background:#16A34A;"></span>
                    <span>UPDATE OTOMATIS</span>
                </span>
            </div>
            <div style="position:relative; height:245px; width:100%;">
                <canvas id="chartPrimer"></canvas>
            </div>
        </div>

        <div class="kop-card" style="padding:22px 24px; display:flex; flex-direction:column; justify-content:space-between;">
            <div>
                <h3 style="margin:0; font-size:16px; font-weight:800; color:#0F172A;">Jenis Produk Tersedia</h3>
                <div style="font-size:12.5px; color:#64748B; margin-top:2px; margin-bottom:12px;">Pembagian stok barang berdasarkan kategorinya.</div>
            </div>
            <div style="position:relative; height:245px; width:100%; display:flex; align-items:center; justify-content:center;">
                <canvas id="chartKategori"></canvas>
            </div>
        </div>
    </div>

    <!-- 6. ALUR KERJA BARANG (DARI PETANI SAMPAI PEMBELI) -->
    <div class="kop-card" style="padding:20px 24px; margin-bottom:20px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; flex-wrap:wrap; gap:8px;">
            <div style="display:flex; align-items:center; gap:8px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2.3"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                <span style="font-size:15.5px; font-weight:800; color:#0F172A;">Alur Kerja Barang (Dari Petani sampai Pembeli)</span>
            </div>
            <span style="font-size:12px; color:#64748B; font-weight:600;">Semua tahap tercatat otomatis</span>
        </div>

        <div class="kop-grid-4">
            <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-top:3px solid #10B981; padding:15px 16px; border-radius:14px;">
                <div style="font-size:11px; font-weight:800; color:#059669;">1. PETANI PANEN</div>
                <div style="font-size:16px; font-weight:800; color:#0F172A; margin:5px 0 3px;"><?= $jml_pasokan ?> Setoran</div>
                <div style="font-size:12px; color:#64748B;">Dari <?= $jml_petani ?> petani mitra terdaftar</div>
            </div>
            <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-top:3px solid #3B82F6; padding:15px 16px; border-radius:14px;">
                <div style="font-size:11px; font-weight:800; color:#2563EB;">2. CABANG KECAMATAN</div>
                <div style="font-size:16px; font-weight:800; color:#0F172A; margin:5px 0 3px;"><?= $jml_primer ?> Cabang</div>
                <div style="font-size:12px; color:#64748B;">Cek kualitas &amp; kemas barang</div>
            </div>
            <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-top:3px solid #F59E0B; padding:15px 16px; border-radius:14px;">
                <div style="font-size:11px; font-weight:800; color:#D97706;">3. TOKO PUSAT</div>
                <div style="font-size:16px; font-weight:800; color:#0F172A; margin:5px 0 3px;"><?= $jml_produk ?> Produk</div>
                <div style="font-size:12px; color:#64748B;">Siap dipesan pembeli di katalog</div>
            </div>
            <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-top:3px solid #8B5CF6; padding:15px 16px; border-radius:14px;">
                <div style="font-size:11px; font-weight:800; color:#7C3AED;">4. PEMBELI ORDER</div>
                <div style="font-size:16px; font-weight:800; color:#0F172A; margin:5px 0 3px;"><?= $total_order ?> Pesanan</div>
                <div style="font-size:12px; color:#64748B;">Langsung diproses &amp; dikirim</div>
            </div>
        </div>
    </div>

    <!-- 7. PESANAN PEMBELI TERBARU (OTOMATIS JADI KARTU DI HP) & SETORAN PANEN TERBARU -->
    <div class="kop-grid-2-1">
        <!-- KOLOM KIRI: PESANAN PEMBELI TERBARU -->
        <div class="kop-card" style="padding:22px 24px;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:14px; flex-wrap:wrap; gap:10px;">
                <div>
                    <h3 style="margin:0; font-size:16.5px; font-weight:800; color:#0F172A;">Pesanan Pembeli Terbaru</h3>
                    <div style="font-size:12.5px; color:#64748B; margin-top:2px;">Klik tombol status di tiap pesanan kalau barang sudah diproses atau dikirim.</div>
                </div>
                <a href="../transaksi/index.php" class="kop-btn" style="background:#ECFDF5; color:#059669; border:1px solid #A7F3D0; padding:8px 14px; border-radius:10px; font-size:12px; font-weight:700;">
                    <span>Lihat Semua Pesanan</span>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                </a>
            </div>

            <!-- TOMBOL FILTER STATUS + PENCARIAN -->
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:16px; padding-bottom:12px; border-bottom:1px solid #F1F5F9;">
                <div style="display:flex; gap:6px; flex-wrap:wrap;" id="orderFilterBar">
                    <button type="button" class="kop-pill active" onclick="filterOrders('ALL', this)">Semua</button>
                    <button type="button" class="kop-pill" onclick="filterOrders('Menunggu Konfirmasi', this)">Menunggu</button>
                    <button type="button" class="kop-pill" onclick="filterOrders('Diproses Koperasi', this)">Diproses</button>
                    <button type="button" class="kop-pill" onclick="filterOrders('Dikirim', this)">Dikirim</button>
                    <button type="button" class="kop-pill" onclick="filterOrders('Selesai', this)">Selesai</button>
                </div>
                <input type="text" id="searchOrderInput" onkeyup="searchOrders()" placeholder="Cari nomor invoice atau pembeli..." class="kop-search-box">
            </div>

            <div>
                <table id="dashboardOrderTable" class="kop-responsive-table" style="width:100%; border-collapse:collapse; text-align:left; font-size:13px;">
                    <thead>
                        <tr style="background:#F8FAFC; border-bottom:2px solid #E2E8F0; color:#475569; font-size:11.5px; text-transform:uppercase; letter-spacing:0.4px;">
                            <th style="padding:11px 12px;">No. Invoice</th>
                            <th style="padding:11px 12px;">Nama Pembeli</th>
                            <th style="padding:11px 12px;">Total Bayar</th>
                            <th style="padding:11px 12px;">Pembayaran</th>
                            <th style="padding:11px 12px;">Status Pesanan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="5" style="padding:32px 16px; text-align:center;">
                                    <div style="font-weight:700; color:#334155; margin-bottom:4px;">Belum ada pesanan masuk</div>
                                    <div style="font-size:12.5px; color:#64748B;">Coba login pakai akun <strong>Buyer</strong> lalu belanja barang supaya pesannya muncul di sini.</div>
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
                            <td data-label="Nama Pembeli" style="padding:12px; font-weight:700; color:#0F172A;"><?= htmlspecialchars($o['nama_buyer']) ?></td>
                            <td data-label="Total Bayar" style="padding:12px; font-weight:800; color:#064E3B; font-variant-numeric:tabular-nums;"><?= rupiah($o['total_bayar']) ?></td>
                            <td data-label="Pembayaran" style="padding:12px;">
                                <span style="background:#F1F5F9; color:#334155; padding:4px 10px; border-radius:8px; font-size:11.5px; font-weight:700; display:inline-block;">
                                    <?= htmlspecialchars($o['metode_bayar']) ?>
                                </span>
                            </td>
                            <td data-label="Status Pesanan" class="td-full-action" style="padding:12px;">
                                <form method="POST" style="margin:0;">
                                    <input type="hidden" name="update_status_order" value="1">
                                    <input type="hidden" name="id_order" value="<?= $o['id_order'] ?>">
                                    <select name="status_order" onchange="this.form.submit()" style="padding:7px 11px; border-radius:9px; border:1px solid <?= $sel_bdr ?>; font-size:12px; font-weight:800; background:<?= $sel_bg ?>; color:<?= $sel_col ?>; cursor:pointer; outline:none;">
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
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                    <h3 style="margin:0; font-size:16px; font-weight:800; color:#0F172A;">Setoran Panen Terbaru</h3>
                    <span style="font-size:11px; font-weight:800; color:#059669; background:#ECFDF5; padding:3px 10px; border-radius:99px;">Dari Petani</span>
                </div>
                <p style="margin:0 0 14px; font-size:12.5px; color:#64748B;">Hasil panen yang baru masuk ke cabang koperasi.</p>

                <?php if (empty($pasokan_recent)): ?>
                    <div style="font-size:12.5px; color:#64748B; background:#F8FAFC; padding:16px; border-radius:12px; border:1px dashed #CBD5E1; line-height:1.5;">
                        Belum ada setoran panen baru. Login pakai akun <strong>Petani</strong> untuk mengirim hasil panen.
                    </div>
                <?php else: ?>
                    <div style="display:flex; flex-direction:column; gap:10px;">
                        <?php foreach ($pasokan_recent as $pr): 
                            $acc = $pr['status_pasokan'] === 'Diterima Primer';
                        ?>
                        <div style="padding:12px 14px; background:#F8FAFC; border:1px solid #E2E8F0; border-radius:12px; display:flex; justify-content:space-between; align-items:center; gap:10px;">
                            <div style="min-width:0;">
                                <div style="font-size:13.5px; font-weight:700; color:#0F172A; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars($pr['nama_komoditas']) ?></div>
                                <div style="font-size:11.5px; color:#64748B; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-top:2px;"><?= htmlspecialchars($pr['nama_petani']) ?> • <?= (int)$pr['jumlah'] ?> <?= htmlspecialchars($pr['satuan']) ?></div>
                            </div>
                            <span style="background:<?= $acc ? '#DCFCE7' : '#FEF3C7' ?>; color:<?= $acc ? '#166534' : '#92400E' ?>; font-size:11px; font-weight:800; padding:4px 10px; border-radius:99px; white-space:nowrap; flex-shrink:0;">
                                <?= $acc ? 'Sudah Diterima' : 'Menunggu Cek' ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <a href="../pasokan/index.php" class="kop-btn" style="margin-top:16px; width:100%; box-sizing:border-box; padding:11px; border-radius:11px; background:#F8FAFC; border:1px solid #CBD5E1; color:#334155; font-size:12.5px; font-weight:700;">
                <span>Lihat Semua Setoran Panen</span>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </a>
        </div>
    </div>

    <script>
        // Grafik Batang: Stok Barang per Cabang
        const ctxPrimer = document.getElementById('chartPrimer').getContext('2d');
        new Chart(ctxPrimer, {
            type: 'bar',
            data: {
                labels: <?= json_encode($labels_primer) ?>,
                datasets: [
                    { label: 'Total Stok Barang', data: <?= json_encode($data_stok_primer) ?>, backgroundColor: '#10B981', borderRadius: 8, barPercentage: 0.48 },
                    { label: 'Jumlah Jenis Produk', data: <?= json_encode($data_item_primer) ?>, backgroundColor: '#F59E0B', borderRadius: 8, barPercentage: 0.48 }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'top', labels: { usePointStyle: true, font: { size: 12, weight: 'bold' } } } },
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
                cutout: '66%',
                plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, padding: 12, font: { size: 11.5 } } } }
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

    $list_pending = $pdo->query("SELECT ps.*, u.nama_lengkap AS nama_petani FROM pasokan_petani ps JOIN users u ON ps.id_petani = u.id_user WHERE ps.status_pasokan = 'Menunggu Verifikasi'" . ($id_prm > 0 ? " AND ps.id_primer = $id_prm" : "") . " ORDER BY ps.id_pasokan DESC LIMIT 5")->fetchAll();
    $list_ews_prm = [];
    try {
        $list_ews_prm = $pdo->query("SELECT * FROM instruksi_pasokan" . $where_prm . " ORDER BY id_instruksi DESC LIMIT 4")->fetchAll();
    } catch (Exception $e) {}
    ?>
    <div class="kop-hero-box" style="background:linear-gradient(125deg, #064E3B 0%, #059669 100%); color:#FFFFFF; padding:26px 30px; border-radius:20px; margin-bottom:20px; box-shadow:0 10px 25px rgba(5,150,105,0.16);">
        <span style="display:inline-block; background:rgba(255,255,255,0.16); color:#ECFDF5; font-size:11px; font-weight:800; padding:5px 12px; border-radius:99px;">CABANG KOPERASI KECAMATAN</span>
        <h2 class="kop-hero-title" style="margin:10px 0 6px; font-size:22px; font-weight:800;"><?= htmlspecialchars($user['nama_koperasi'] ?? 'Koperasi Primer') ?></h2>
        <p style="margin:0 0 18px; color:#ECFDF5; font-size:13.5px; max-width:640px; line-height:1.55;">
            Cek kualitas panen dari petani wilayah Kecamatan <?= htmlspecialchars($user['kecamatan'] ?? '-') ?>, penuhi permintaan stok dari pusat, dan pajang produk siap jual.
        </p>
        <div class="kop-hero-actions" style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="../pasokan/index.php" class="kop-btn" style="background:#FBBF24; color:#0F172A; padding:11px 17px; border-radius:12px; font-weight:800; font-size:13px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><polyline points="20 6 9 17 4 12"/></svg>
                <span>Cek Setoran Petani (<?= $pasokan_pending ?>)</span>
            </a>
            <a href="../produk/index.php" class="kop-btn" style="background:#FFFFFF; color:#064E3B; padding:11px 17px; border-radius:12px; font-weight:800; font-size:13px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <span>Atur Etalase Produk (<?= $produk_tayang ?>)</span>
            </a>
            <a href="../settlement/index.php" class="kop-btn" style="background:rgba(255,255,255,0.18); border:1px solid rgba(255,255,255,0.35); color:#FFFFFF; padding:11px 17px; border-radius:12px; font-weight:700; font-size:13px;">
                <span>Cek Pencairan Uang</span>
            </a>
        </div>
    </div>

    <div class="kop-grid-2-1">
        <div class="kop-card" style="padding:22px 24px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
                <h3 style="margin:0; font-size:16px; font-weight:800; color:#0F172A;">Setoran Petani Perlu Dicek</h3>
                <a href="../pasokan/index.php" style="font-size:12.5px; font-weight:700; color:#059669; text-decoration:none; white-space:nowrap;">Lihat Semua →</a>
            </div>
            <?php if (empty($list_pending)): ?>
                <div style="padding:24px; text-align:center; background:#F8FAFC; border-radius:12px; color:#64748B; font-size:13px;">
                    Semua setoran panen petani sudah dicek.
                </div>
            <?php else: ?>
                <div style="display:flex; flex-direction:column; gap:10px;">
                    <?php foreach ($list_pending as $lp): ?>
                    <div style="padding:13px 16px; background:#F8FAFC; border:1px solid #E2E8F0; border-radius:14px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                        <div>
                            <div style="font-weight:800; color:#0F172A; font-size:13.5px;"><?= htmlspecialchars($lp['nama_komoditas']) ?> (<?= (int)$lp['jumlah'] ?> <?= htmlspecialchars($lp['satuan']) ?>)</div>
                            <div style="font-size:12px; color:#64748B;">Petani: <?= htmlspecialchars($lp['nama_petani']) ?> • Nilai: <?= rupiah($lp['total_nilai']) ?></div>
                        </div>
                        <a href="../pasokan/index.php" class="kop-btn" style="background:#059669; color:#FFFFFF; padding:8px 15px; border-radius:9px; font-size:12px; font-weight:700;">Cek &amp; Terima</a>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="kop-card" style="padding:22px 24px;">
            <h3 style="margin:0 0 12px; font-size:16px; font-weight:800; color:#0F172A;">Permintaan Stok dari Pusat</h3>
            <?php if (empty($list_ews_prm)): ?>
                <div style="font-size:12.5px; color:#64748B; background:#F8FAFC; padding:16px; border-radius:12px;">Belum ada permintaan stok mendesak dari pusat.</div>
            <?php else: ?>
                <div style="display:flex; flex-direction:column; gap:10px;">
                    <?php foreach ($list_ews_prm as $ep): ?>
                    <div style="padding:13px; background:#FFFBEB; border:1px solid #FDE68A; border-radius:12px;">
                        <div style="font-size:11px; font-weight:800; color:#D97706;"><?= htmlspecialchars($ep['kode_instruksi']) ?> • <?= htmlspecialchars($ep['tingkat_urgensi']) ?></div>
                        <div style="font-size:13.5px; font-weight:800; color:#0F172A; margin:3px 0;"><?= htmlspecialchars($ep['nama_komoditas']) ?> (Butuh: <?= (int)$ep['target_jumlah'] ?> <?= htmlspecialchars($ep['satuan']) ?>)</div>
                        <div style="font-size:12px; color:#475569;"><?= htmlspecialchars($ep['catatan_pusat']) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php elseif ($role === 'petani'): ?>
    <?php
    $stmtP = $pdo->prepare("SELECT COUNT(*) as tot, COALESCE(SUM(total_nilai),0) as nilai FROM pasokan_petani WHERE id_petani = ?");
    $stmtP->execute([$user['id_user']]);
    $statPetani = $stmtP->fetch();

    $stmtHist = $pdo->prepare("SELECT * FROM pasokan_petani WHERE id_petani = ? ORDER BY id_pasokan DESC LIMIT 5");
    $stmtHist->execute([$user['id_user']]);
    $hist_petani = $stmtHist->fetchAll();
    ?>
    <div class="kop-hero-box" style="background:linear-gradient(125deg, #064E3B 0%, #059669 100%); color:#FFFFFF; padding:26px 30px; border-radius:20px; margin-bottom:20px; box-shadow:0 10px 25px rgba(5,150,105,0.16);">
        <span style="display:inline-block; background:rgba(255,255,255,0.16); color:#ECFDF5; font-size:11px; font-weight:800; padding:5px 12px; border-radius:99px;">HALAMAN PETANI MITRA</span>
        <h2 class="kop-hero-title" style="margin:10px 0 6px; font-size:22px; font-weight:800;">Halo, <?= htmlspecialchars($user['nama_lengkap']) ?></h2>
        <p style="margin:0 0 18px; color:#ECFDF5; font-size:13.5px; max-width:620px; line-height:1.55;">
            Kamu sudah mencatat <strong><?= $statPetani['tot'] ?> kali setor panen</strong> dengan total nilai <strong><?= rupiah($statPetani['nilai']) ?></strong>. Jual hasil panenmu langsung ke koperasi tanpa potongan tengkulak.
        </p>
        <div class="kop-hero-actions" style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="../pasokan/index.php" class="kop-btn" style="background:#FBBF24; color:#0F172A; padding:11px 19px; border-radius:12px; font-weight:800; font-size:13px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <span>Kirim Hasil Panen Baru</span>
            </a>
            <a href="../settlement/index.php" class="kop-btn" style="background:#FFFFFF; color:#064E3B; padding:11px 19px; border-radius:12px; font-weight:800; font-size:13px;">
                <span>Cek Uang Hasil Panen</span>
            </a>
            <a href="../regulasi_harga/index.php" class="kop-btn" style="background:rgba(255,255,255,0.18); border:1px solid rgba(255,255,255,0.35); color:#FFFFFF; padding:11px 19px; border-radius:12px; font-weight:700; font-size:13px;">
                <span>Daftar Harga Beli Koperasi</span>
            </a>
        </div>
    </div>

    <div class="kop-card" style="padding:22px 24px;">
        <h3 style="margin:0 0 14px; font-size:16.5px; font-weight:800; color:#0F172A;">Catatan Setoran Panen Terakhir</h3>
        <?php if (empty($hist_petani)): ?>
            <div style="padding:24px; text-align:center; background:#F8FAFC; border-radius:12px; color:#64748B; font-size:13px;">
                Belum ada catatan setor panen. Klik tombol <strong>Kirim Hasil Panen Baru</strong> di atas untuk mulai menyetor.
            </div>
        <?php else: ?>
            <table class="kop-responsive-table" style="width:100%; border-collapse:collapse; text-align:left; font-size:13px;">
                <thead>
                    <tr style="background:#F8FAFC; border-bottom:2px solid #E2E8F0; color:#475569;">
                        <th style="padding:11px;">Kode Setoran</th>
                        <th style="padding:11px;">Nama Hasil Panen</th>
                        <th style="padding:11px;">Jumlah</th>
                        <th style="padding:11px;">Total Harga</th>
                        <th style="padding:11px;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($hist_petani as $hp): ?>
                    <tr class="kop-mobile-card-row" style="border-bottom:1px solid #F1F5F9;">
                        <td data-label="Kode Setoran" class="td-top-head" style="padding:11px; font-family:monospace; font-weight:800; color:#059669;"><?= htmlspecialchars($hp['kode_pasokan']) ?></td>
                        <td data-label="Hasil Panen" style="padding:11px; font-weight:700;"><?= htmlspecialchars($hp['nama_komoditas']) ?></td>
                        <td data-label="Jumlah" style="padding:11px;"><?= (int)$hp['jumlah'] ?> <?= htmlspecialchars($hp['satuan']) ?></td>
                        <td data-label="Total Harga" style="padding:11px; font-weight:800;"><?= rupiah($hp['total_nilai']) ?></td>
                        <td data-label="Status" style="padding:11px;">
                            <span style="background:<?= $hp['status_pasokan']=='Diterima Primer' ? '#DCFCE7' : '#FEF3C7' ?>; color:<?= $hp['status_pasokan']=='Diterima Primer' ? '#166534' : '#92400E' ?>; padding:4px 10px; border-radius:99px; font-size:11.5px; font-weight:800;">
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
    <div class="kop-hero-box" style="background:linear-gradient(125deg, #064E3B 0%, #059669 100%); color:#FFFFFF; padding:28px 30px; border-radius:20px; margin-bottom:20px; box-shadow:0 10px 25px rgba(5,150,105,0.16);">
        <span style="display:inline-block; background:rgba(255,255,255,0.16); color:#ECFDF5; font-size:11px; font-weight:800; padding:5px 12px; border-radius:99px;">TOKO RESMI KOPNOVA</span>
        <h2 class="kop-hero-title" style="margin:10px 0 8px; font-size:23px; font-weight:800;">Belanja Hasil Panen Segar Langsung dari Koperasi</h2>
        <p style="color:#ECFDF5; margin:0 0 20px; max-width:600px; font-size:13.5px; line-height:1.55;">
            Pilih berbagai komoditas dari banyak daerah dalam satu kali checkout dengan stok jelas dan pengiriman terjamin.
        </p>
        <div class="kop-hero-actions" style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="../marketplace/index.php" class="kop-btn" style="background:#FBBF24; color:#0F172A; padding:11px 20px; border-radius:12px; font-weight:800; font-size:13px;">
                <span>Lihat Semua Produk</span>
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </a>
            <a href="../marketplace/keranjang.php" class="kop-btn" style="background:rgba(255,255,255,0.18); border:1px solid rgba(255,255,255,0.35); color:#FFFFFF; padding:11px 19px; border-radius:12px; font-weight:700; font-size:13px;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                <span>Keranjang Saya (<?= $cart_count ?>)</span>
            </a>
        </div>
    </div>

    <div class="kop-card" style="padding:22px 24px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h3 style="margin:0; font-size:16.5px; font-weight:800; color:#0F172A;">Produk Pilihan Siap Kirim</h3>
            <a href="../marketplace/index.php" style="font-size:12.5px; font-weight:700; color:#059669; text-decoration:none; white-space:nowrap;">Buka Katalog →</a>
        </div>
        <div class="kop-grid-4">
            <?php foreach ($katalog_featured as $kf): ?>
            <div style="border:1px solid #E2E8F0; border-radius:16px; padding:16px; background:#F8FAFC; display:flex; flex-direction:column; justify-content:space-between;">
                <div>
                    <span style="font-size:11px; font-weight:800; color:#059669; background:#DCFCE7; padding:3px 10px; border-radius:99px; display:inline-block;"><?= htmlspecialchars($kf['nama_divisi']) ?></span>
                    <div style="font-size:14.5px; font-weight:800; color:#0F172A; margin:8px 0 4px;"><?= htmlspecialchars($kf['nama_produk']) ?></div>
                    <div style="font-size:12px; color:#64748B;"><?= htmlspecialchars($kf['nama_koperasi']) ?></div>
                </div>
                <div style="margin-top:14px; padding-top:12px; border-top:1px solid #E2E8F0; display:flex; justify-content:space-between; align-items:center; gap:8px;">
                    <div style="min-width:0;">
                        <div style="font-size:14.5px; font-weight:900; color:#064E3B; white-space:nowrap;"><?= rupiah($kf['harga_jual']) ?></div>
                        <div style="font-size:11px; color:#64748B;">Stok: <?= (int)$kf['stok'] ?> <?= htmlspecialchars($kf['satuan']) ?></div>
                    </div>
                    <a href="../marketplace/index.php" class="kop-btn" style="background:#059669; color:#FFFFFF; padding:8px 13px; border-radius:9px; font-size:12px; font-weight:700;">Beli</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

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