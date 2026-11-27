<?php
require_once '../../config/database.php';
cekAkses(['konsumen', 'primer', 'petani']);

$role = $_SESSION['user']['role'];
$user = $_SESSION['user'];

// PROSES 1: Kirim Instruksi Pengadaan Pasokan dari Konsumen Pusat ke Koperasi Primer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'kirim_instruksi') {
    verify_csrf();
    cekAkses(['konsumen']);

    $id_primer     = (int)($_POST['id_primer'] ?? 0);
    $nama_kom      = trim($_POST['nama_komoditas'] ?? '');
    $kategori      = $_POST['kategori'] ?? '';
    $target_jumlah = (int)($_POST['target_jumlah'] ?? 0);
    $satuan        = trim($_POST['satuan'] ?? '');
    $urgensi       = $_POST['tingkat_urgensi'] ?? '';
    $catatan       = trim($_POST['catatan_pusat'] ?? '');

    $valid_kategori = ['Komoditas Tanaman', 'Saprodi', 'Alsintan', 'Produk Olahan', 'Produk Lainnya'];
    $valid_urgensi  = ['Kritis (< 20)', 'Waspada (< 50)', 'Prediksi Musiman'];

    if ($id_primer <= 0 || $nama_kom === '' || $satuan === '' || $target_jumlah <= 0 || !in_array($kategori, $valid_kategori, true) || !in_array($urgensi, $valid_urgensi, true)) {
        header("Location: index.php?err=" . urlencode("Data permintaan stok belum lengkap. Pastikan jumlah target lebih dari 0 dan semua kolom sudah diisi."));
        exit;
    }

    $kode = 'EWS-' . date('ymd') . '-' . random_int(100, 999);
    $stmt = $pdo->prepare("INSERT INTO instruksi_pasokan (kode_instruksi, id_primer, nama_komoditas, kategori, target_jumlah, satuan, tingkat_urgensi, catatan_pusat) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $kode,
        $id_primer,
        $nama_kom,
        $kategori,
        $target_jumlah,
        $satuan,
        $urgensi,
        $catatan
    ]);

    header("Location: index.php?msg=" . urlencode("Permintaan tambah stok ($kode) berhasil dikirim ke cabang koperasi."));
    exit;
}

// PROSES 2: Update Progres Instruksi oleh Koperasi Primer / Konsumen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'update_progres') {
    verify_csrf();
    cekAkses(['konsumen', 'primer']);

    $id_ins       = (int)($_POST['id_instruksi'] ?? 0);
    $status       = $_POST['status_instruksi'] ?? '';
    $valid_status = ['Terkirim ke Primer', 'Diteruskan ke Petani', 'Terpenuhi'];

    if (!in_array($status, $valid_status, true)) {
        header("Location: index.php?err=" . urlencode("Status progres tidak valid."));
        exit;
    }

    if ($role === 'primer') {
        $id_primer_login = (int)($user['id_primer'] ?? 0);
        $stmt = $pdo->prepare("UPDATE instruksi_pasokan SET status_instruksi = ? WHERE id_instruksi = ? AND id_primer = ?");
        $stmt->execute([$status, $id_ins, $id_primer_login]);
    } else {
        $stmt = $pdo->prepare("UPDATE instruksi_pasokan SET status_instruksi = ? WHERE id_instruksi = ?");
        $stmt->execute([$status, $id_ins]);
    }

    if ($stmt->rowCount() > 0) {
        header("Location: index.php?msg=" . urlencode("Status permintaan stok diperbarui menjadi: " . $status));
    } else {
        header("Location: index.php?err=" . urlencode("Data tidak ditemukan atau bukan wewenang cabang Anda."));
    }
    exit;
}

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

// Query Analisis Stok Kritis (< 50) + Total Permintaan Buyer per Produk (Fail-closed untuk Primer & Petani)
if (in_array($role, ['primer', 'petani'], true)) {
    $stmtProd = $pdo->prepare("SELECT pr.*, kp.nama_koperasi, kp.kecamatan, d.nama_divisi,
                               COALESCE((SELECT SUM(oi.qty) FROM order_items oi WHERE oi.id_produk = pr.id_produk), 0) AS total_terjual
                               FROM produk_etalase pr
                               JOIN koperasi_primer kp ON pr.id_primer = kp.id_primer
                               JOIN divisi_wilayah d ON pr.id_divisi = d.id_divisi
                               WHERE pr.id_primer = ?
                               ORDER BY pr.stok ASC, total_terjual DESC");
    $stmtProd->execute([(int)($user['id_primer'] ?? 0)]);
    $produk_analisis = $stmtProd->fetchAll();
} else {
    $produk_analisis = $pdo->query("SELECT pr.*, kp.nama_koperasi, kp.kecamatan, d.nama_divisi,
                                    COALESCE((SELECT SUM(oi.qty) FROM order_items oi WHERE oi.id_produk = pr.id_produk), 0) AS total_terjual
                                    FROM produk_etalase pr
                                    JOIN koperasi_primer kp ON pr.id_primer = kp.id_primer
                                    JOIN divisi_wilayah d ON pr.id_divisi = d.id_divisi
                                    ORDER BY pr.stok ASC, total_terjual DESC")->fetchAll();
}

$stok_kritis = array_filter($produk_analisis, fn($p) => (int)$p['stok'] < 50);

$primer_list = ($role === 'konsumen')
    ? $pdo->query("SELECT kp.*, d.nama_divisi FROM koperasi_primer kp JOIN divisi_wilayah d ON kp.id_divisi = d.id_divisi ORDER BY d.nama_divisi ASC")->fetchAll()
    : [];

// Daftar Instruksi Pasokan (Fail-closed untuk Primer & Petani)
if (in_array($role, ['primer', 'petani'], true)) {
    $stmtIns = $pdo->prepare("SELECT ip.*, kp.nama_koperasi, kp.kecamatan, d.nama_divisi 
                              FROM instruksi_pasokan ip 
                              JOIN koperasi_primer kp ON ip.id_primer = kp.id_primer 
                              JOIN divisi_wilayah d ON kp.id_divisi = d.id_divisi 
                              WHERE ip.id_primer = ? ORDER BY ip.id_instruksi DESC");
    $stmtIns->execute([(int)($user['id_primer'] ?? 0)]);
    $instruksi_list = $stmtIns->fetchAll();
} else {
    $instruksi_list = $pdo->query("SELECT ip.*, kp.nama_koperasi, kp.kecamatan, d.nama_divisi 
                                   FROM instruksi_pasokan ip 
                                   JOIN koperasi_primer kp ON ip.id_primer = kp.id_primer 
                                   JOIN divisi_wilayah d ON kp.id_divisi = d.id_divisi 
                                   ORDER BY ip.id_instruksi DESC")->fetchAll();
}
?>

<style>
    /* PERAPIAN SIDEBAR & LAYOUT CLEAN MODERN */
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

    .ews-grid-3 {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 16px;
    }
    .ews-card {
        background: #FFFFFF;
        border-radius: 18px;
        border: 1px solid #E2E8F0;
        box-shadow: 0 2px 10px rgba(15, 23, 42, 0.03);
    }
    .ews-kpi {
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
    .ews-kpi:hover {
        transform: translateY(-2px);
    }
    .ews-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        border: none;
        text-decoration: none;
        white-space: nowrap;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .ews-btn:hover {
        filter: brightness(1.06);
        transform: translateY(-1px);
    }
    .ews-pill {
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
    .ews-pill:hover {
        border-color: #10B981;
        color: #065F46;
    }
    .ews-pill.active {
        background: #064E3B;
        color: #FFFFFF;
        border-color: #064E3B;
        box-shadow: 0 3px 10px rgba(6, 78, 59, 0.2);
    }
    .ews-search {
        padding: 8px 13px;
        border: 1px solid #CBD5E1;
        border-radius: 10px;
        font-size: 12.5px;
        min-width: 220px;
        outline: none;
        transition: border-color 0.2s;
    }
    .ews-search:focus {
        border-color: #10B981;
    }
    .ews-row-hover:hover {
        background-color: #F8FAFC !important;
    }
    .ews-form-row-2 {
        display: grid;
        grid-template-columns: 1.7fr 1fr;
        gap: 12px;
    }
    .ews-form-row-3 {
        display: grid;
        grid-template-columns: 1fr 1fr 1.2fr;
        gap: 10px;
    }
    .mobile-nav-trigger {
        display: none;
    }

    /* TABLET & LAPTOP KECIL */
    @media (max-width: 1080px) {
        .ews-grid-3 {
            grid-template-columns: repeat(2, minmax(0, 1fr));
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

    /* TABEL OTOMATIS JADI KARTU DI LAYAR HP (< 768px) */
    @media (max-width: 768px) {
        .ews-responsive-table thead {
            display: none;
        }
        .ews-responsive-table,
        .ews-responsive-table tbody,
        .ews-responsive-table tr,
        .ews-responsive-table td {
            display: block;
            width: 100%;
            box-sizing: border-box;
        }
        .ews-responsive-table tr.ews-mobile-card {
            background: #FFFFFF !important;
            border: 1px solid #E2E8F0 !important;
            border-radius: 14px;
            padding: 14px;
            margin-bottom: 12px;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03);
        }
        .ews-responsive-table tr.ews-mobile-card.is-low-stock {
            border-left: 4px solid #DC2626 !important;
            background: #FFFDFD !important;
        }
        .ews-responsive-table td {
            padding: 5px 0 !important;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            border: none !important;
            text-align: right;
            max-width: 100% !important;
        }
        .ews-responsive-table td::before {
            content: attr(data-label);
            font-size: 11.5px;
            font-weight: 700;
            color: #64748B;
            text-align: left;
            flex-shrink: 0;
        }
        .ews-responsive-table td.td-card-header {
            border-bottom: 1px dashed #E2E8F0 !important;
            padding-bottom: 9px !important;
            margin-bottom: 4px;
            text-align: left;
            justify-content: flex-start;
        }
        .ews-responsive-table td.td-card-header::before {
            display: none;
        }
        .ews-responsive-table td.td-card-action {
            border-top: 1px solid #F1F5F9 !important;
            padding-top: 10px !important;
            margin-top: 6px;
            flex-direction: column;
            align-items: stretch;
        }
        .ews-responsive-table td.td-card-action::before {
            margin-bottom: 5px;
        }
        .ews-responsive-table td.td-card-action button,
        .ews-responsive-table td.td-card-action form,
        .ews-responsive-table td.td-card-action select {
            width: 100%;
            padding: 9px 12px !important;
            font-size: 13px !important;
        }
    }

    /* LAYAR HP COMPACT (< 640px) */
    @media (max-width: 640px) {
        .ews-grid-3, .ews-form-row-2, .ews-form-row-3 {
            grid-template-columns: minmax(0, 1fr);
        }
        .ews-hero {
            padding: 22px 20px !important;
        }
        .ews-hero-title {
            font-size: 19px !important;
        }
        .ews-hero-btn {
            width: 100%;
        }
        .ews-search {
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
<div class="ews-hero" style="background:linear-gradient(125deg, #064E3B 0%, #059669 100%); border-radius:20px; padding:26px 30px; color:#FFFFFF; margin-bottom:20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:18px; box-shadow:0 10px 25px rgba(5, 150, 105, 0.16); position:relative; overflow:hidden;">
    <div style="position:absolute; right:-30px; bottom:-50px; width:200px; height:200px; border-radius:50%; background:rgba(255,255,255,0.08); pointer-events:none;"></div>
    <div style="max-width:620px; position:relative; z-index:2;">
        <div style="display:inline-flex; align-items:center; gap:7px; background:rgba(255,255,255,0.16); color:#ECFDF5; font-size:11px; font-weight:800; padding:5px 12px; border-radius:99px; margin-bottom:10px;">
            <span style="width:7px; height:7px; border-radius:50%; background:#FBBF24; display:inline-block;"></span>
            <span>PANTAU STOK &amp; RESTOCK OTOMATIS</span>
        </div>
        <h2 class="ews-hero-title" style="margin:0 0 6px; font-size:22px; font-weight:800; line-height:1.3;">
            Deteksi Stok Menipis &amp; Minta Panen Baru
        </h2>
        <p style="margin:0; font-size:13.5px; color:#ECFDF5; line-height:1.55; opacity:0.95;">
            Sistem otomatis menandai barang yang stoknya tinggal sedikit (di bawah 50) supaya koperasi bisa langsung minta pasokan ke cabang dan petani.
        </p>
    </div>

    <?php if ($role === 'konsumen'): ?>
    <button type="button" onclick="openInstruksiModal()" class="ews-btn ews-hero-btn" style="background:#FBBF24; color:#0F172A; padding:12px 18px; border-radius:12px; font-weight:800; font-size:13px; box-shadow:0 4px 14px rgba(251,191,36,0.35); position:relative; z-index:2;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        <span>Minta Tambah Stok ke Cabang</span>
    </button>
    <?php endif; ?>
</div>

<?php if (isset($_GET['msg'])): ?>
<div style="background:#ECFDF5; border:1px solid #A7F3D0; border-left:5px solid #059669; color:#065F46; padding:13px 18px; border-radius:12px; margin-bottom:18px; font-weight:700; font-size:13.5px; display:flex; align-items:center; gap:10px;">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
    <span><?= htmlspecialchars($_GET['msg']) ?></span>
</div>
<?php endif; ?>

<?php if (isset($_GET['err'])): ?>
<div style="background:#FEF2F2; border:1px solid #FECACA; border-left:5px solid #DC2626; color:#991B1B; padding:13px 18px; border-radius:12px; margin-bottom:18px; font-weight:700; font-size:13.5px; display:flex; align-items:center; gap:10px;">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <span><?= htmlspecialchars($_GET['err']) ?></span>
</div>
<?php endif; ?>

<!-- 3 KARTU RINGKASAN -->
<div class="ews-grid-3" style="margin-bottom:22px;">
    <div class="ews-kpi" style="border-left:5px solid #DC2626;">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
            <span style="font-size:12.5px; color:#991B1B; font-weight:800;">Stok Mau Habis (&lt; 50)</span>
            <div style="width:36px; height:36px; border-radius:11px; background:#FEE2E2; color:#DC2626; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            </div>
        </div>
        <div style="font-size:26px; font-weight:900; color:#DC2626; margin:8px 0 4px; font-variant-numeric:tabular-nums;"><?= count($stok_kritis) ?> Barang</div>
        <div style="font-size:12px; color:#64748B;">Perlu segera diisi ulang dari petani</div>
    </div>

    <div class="ews-kpi" style="border-left:5px solid #F59E0B;">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
            <span style="font-size:12.5px; color:#92400E; font-weight:800;">Permintaan Restock</span>
            <div style="width:36px; height:36px; border-radius:11px; background:#FEF3C7; color:#D97706; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            </div>
        </div>
        <div style="font-size:26px; font-weight:900; color:#0F172A; margin:8px 0 4px; font-variant-numeric:tabular-nums;"><?= count($instruksi_list) ?> Tugas</div>
        <div style="font-size:12px; color:#64748B;">Diteruskan ke cabang &amp; petani</div>
    </div>

    <div class="ews-kpi" style="border-left:5px solid #059669;">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
            <span style="font-size:12.5px; color:#065F46; font-weight:800;">Semua Produk Dipantau</span>
            <div style="width:36px; height:36px; border-radius:11px; background:#DCFCE7; color:#059669; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
            </div>
        </div>
        <div style="font-size:26px; font-weight:900; color:#0F172A; margin:8px 0 4px; font-variant-numeric:tabular-nums;"><?= count($produk_analisis) ?> Produk</div>
        <div style="font-size:12px; color:#64748B;">Terpantau aktif di katalog toko</div>
    </div>
</div>

<!-- SECTION 1: PANTAUAN STOK BARANG VS PERMINTAAN PEMBELI -->
<div class="ews-card" style="padding:22px 24px; margin-bottom:24px;">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:10px; margin-bottom:14px;">
        <div>
            <h3 style="margin:0; font-size:16.5px; font-weight:800; color:#0F172A;">Pantauan Stok Barang vs Permintaan Pembeli</h3>
            <div style="font-size:12.5px; color:#64748B; margin-top:2px;">Barang dengan sisa stok di bawah 50 otomatis ditandai supaya bisa langsung diminta tambah stok.</div>
        </div>
    </div>

    <!-- FILTER PILLS & PENCARIAN LIVE -->
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:16px; padding-bottom:12px; border-bottom:1px solid #F1F5F9;">
        <div style="display:flex; gap:6px; flex-wrap:wrap;" id="stockFilterBar">
            <button type="button" class="ews-pill active" onclick="filterStocks('ALL', this)">Semua</button>
            <button type="button" class="ews-pill" onclick="filterStocks('KRITIS', this)">Kritis (&lt; 20)</button>
            <button type="button" class="ews-pill" onclick="filterStocks('WASPADA', this)">Waspada (&lt; 50)</button>
            <button type="button" class="ews-pill" onclick="filterStocks('AMAN', this)">Stok Aman</button>
        </div>
        <input type="text" id="searchStockInput" onkeyup="searchStocks()" placeholder="Cari nama produk atau cabang..." class="ews-search">
    </div>

    <div>
        <table class="ews-responsive-table" style="width:100%; border-collapse:collapse; text-align:left; font-size:13px;">
            <thead>
                <tr style="background:#064E3B; color:#FFFFFF; font-size:11.5px; text-transform:uppercase; letter-spacing:0.4px;">
                    <th style="padding:12px 14px; border-radius:10px 0 0 0;">Produk &amp; Asal Cabang</th>
                    <th style="padding:12px 14px;">Sisa Stok</th>
                    <th style="padding:12px 14px;">Sudah Terjual</th>
                    <th style="padding:12px 14px;">Saran Tambah Stok</th>
                    <th style="padding:12px 14px;">Status Stok</th>
                    <?php if ($role === 'konsumen'): ?>
                        <th style="padding:12px 14px; text-align:center; border-radius:0 10px 0 0;">Aksi Cepat</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($produk_analisis)): ?>
                    <tr><td colspan="6" style="padding:28px; text-align:center; color:#64748B;">Belum ada data produk di etalase untuk dipantau.</td></tr>
                <?php endif; ?>
                <?php foreach ($produk_analisis as $pa): 
                    $stok = (int)$pa['stok'];
                    $terjual = (int)$pa['total_terjual'];
                    $rekomendasi_panen = max(100, ($terjual * 2) + (100 - $stok));

                    if ($stok < 20) {
                        $lvl_code = 'KRITIS';
                        $lvl_bg = '#FEE2E2'; $lvl_col = '#991B1B'; $lvl_txt = 'Kritis (< 20)';
                    } elseif ($stok < 50) {
                        $lvl_code = 'WASPADA';
                        $lvl_bg = '#FEF3C7'; $lvl_col = '#92400E'; $lvl_txt = 'Waspada (< 50)';
                    } else {
                        $lvl_code = 'AMAN';
                        $lvl_bg = '#DCFCE7'; $lvl_col = '#166534'; $lvl_txt = 'Stok Aman';
                    }

                    $modalPayload = json_encode([
                        "id_primer"      => (int)$pa["id_primer"],
                        "nama_komoditas" => $pa["nama_produk"],
                        "kategori"       => $pa["kategori"],
                        "satuan"         => $pa["satuan"],
                        "target"         => $rekomendasi_panen,
                        "urgensi"        => $stok < 20 ? "Kritis (< 20)" : "Waspada (< 50)"
                    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                ?>
                <tr class="ews-row-hover ews-mobile-card stock-data-row <?= $stok < 50 ? 'is-low-stock' : '' ?>" data-level="<?= $lvl_code ?>" style="border-bottom:1px solid #F1F5F9; background:<?= $stok < 50 ? '#FFFBEB' : '#FFFFFF' ?>;">
                    <td class="td-card-header" style="padding:13px 14px;">
                        <div>
                            <div style="font-weight:800; color:#0F172A; font-size:13.5px;"><?= htmlspecialchars($pa['nama_produk']) ?></div>
                            <div style="font-size:11.5px; color:#64748B; margin-top:2px;"><?= htmlspecialchars($pa['nama_koperasi']) ?> • <?= htmlspecialchars($pa['nama_divisi']) ?></div>
                        </div>
                    </td>
                    <td data-label="Sisa Stok" style="padding:13px 14px;">
                        <span style="font-size:14.5px; font-weight:900; color:<?= $stok < 50 ? '#DC2626' : '#059669' ?>; font-variant-numeric:tabular-nums;">
                            <?= number_format($stok, 0, ',', '.') ?> <?= htmlspecialchars($pa['satuan']) ?>
                        </span>
                    </td>
                    <td data-label="Sudah Terjual" style="padding:13px 14px; font-weight:700; color:#334155;">
                        <?= number_format($terjual, 0, ',', '.') ?> <?= htmlspecialchars($pa['satuan']) ?>
                    </td>
                    <td data-label="Saran Tambah" style="padding:13px 14px;">
                        <div>
                            <span style="font-weight:800; color:#064E3B;">+<?= number_format($rekomendasi_panen, 0, ',', '.') ?> <?= htmlspecialchars($pa['satuan']) ?></span>
                            <div style="font-size:11px; color:#64748B;">Cadangan 2 minggu</div>
                        </div>
                    </td>
                    <td data-label="Status Stok" style="padding:13px 14px;">
                        <span style="background:<?= $lvl_bg ?>; color:<?= $lvl_col ?>; padding:4px 11px; border-radius:99px; font-size:11px; font-weight:800; display:inline-block;">
                            <?= $lvl_txt ?>
                        </span>
                    </td>
                    <?php if ($role === 'konsumen'): ?>
                    <td data-label="Tindakan" class="td-card-action" style="padding:13px 14px; text-align:center;">
                        <button type="button" onclick='openInstruksiModal(<?= $modalPayload ?>)' class="ews-btn" style="background:#064E3B; color:#FFFFFF; padding:8px 14px; border-radius:9px; font-weight:700; font-size:12px;">
                            <span>Minta Stok</span>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                        </button>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- SECTION 2: RIWAYAT & STATUS PERMINTAAN STOK (PUSAT -> CABANG -> PETANI) -->
<div class="ews-card" style="padding:22px 24px;">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:10px; margin-bottom:14px;">
        <div>
            <h3 style="margin:0; font-size:16.5px; font-weight:800; color:#0F172A;">Riwayat Permintaan Stok (Pusat → Cabang → Petani)</h3>
            <div style="font-size:12.5px; color:#64748B; margin-top:2px;">Pantau apakah cabang kecamatan dan petani sudah menindaklanjuti permintaan stok dari pusat.</div>
        </div>
    </div>

    <!-- FILTER STATUS INSTRUKSI -->
    <div style="display:flex; gap:6px; flex-wrap:wrap; margin-bottom:16px; padding-bottom:12px; border-bottom:1px solid #F1F5F9;" id="insFilterBar">
        <button type="button" class="ews-pill active" onclick="filterInstruksi('ALL', this)">Semua</button>
        <button type="button" class="ews-pill" onclick="filterInstruksi('Terkirim ke Primer', this)">Terkirim ke Cabang</button>
        <button type="button" class="ews-pill" onclick="filterInstruksi('Diteruskan ke Petani', this)">Diteruskan ke Petani</button>
        <button type="button" class="ews-pill" onclick="filterInstruksi('Terpenuhi', this)">Sudah Terpenuhi</button>
    </div>

    <div>
        <table class="ews-responsive-table" style="width:100%; border-collapse:collapse; text-align:left; font-size:13px;">
            <thead>
                <tr style="background:#F8FAFC; border-bottom:2px solid #E2E8F0; color:#475569; font-size:11.5px; text-transform:uppercase; letter-spacing:0.4px;">
                    <th style="padding:12px 14px;">Kode &amp; Waktu</th>
                    <th style="padding:12px 14px;">Cabang Tujuan</th>
                    <th style="padding:12px 14px;">Barang &amp; Target</th>
                    <th style="padding:12px 14px;">Catatan Pusat</th>
                    <th style="padding:12px 14px;">Status Pemenuhan</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($instruksi_list)): ?>
                    <tr><td colspan="5" style="padding:28px; text-align:center; color:#64748B;">Belum ada permintaan tambah stok yang dikirim.</td></tr>
                <?php endif; ?>
                <?php foreach ($instruksi_list as $ins): 
                    $st_ins = $ins['status_instruksi'];
                    $sel_bg = '#FEF3C7'; $sel_col = '#92400E'; $sel_bdr = '#FDE68A';
                    if ($st_ins === 'Diteruskan ke Petani') { $sel_bg = '#DBEAFE'; $sel_col = '#1E40AF'; $sel_bdr = '#BFDBFE'; }
                    elseif ($st_ins === 'Terpenuhi')        { $sel_bg = '#DCFCE7'; $sel_col = '#166534'; $sel_bdr = '#BBF7D0'; }
                ?>
                <tr class="ews-row-hover ews-mobile-card ins-data-row" data-status="<?= htmlspecialchars($st_ins) ?>" style="border-bottom:1px solid #F1F5F9;">
                    <td class="td-card-header" style="padding:12px 14px;">
                        <div>
                            <div style="font-weight:800; color:#059669; font-family:monospace; font-size:12.5px;"><?= htmlspecialchars($ins['kode_instruksi']) ?></div>
                            <div style="font-size:11px; color:#64748B; margin-top:2px;"><?= date('d M Y, H:i', strtotime($ins['created_at'])) ?></div>
                        </div>
                    </td>
                    <td data-label="Cabang Tujuan" style="padding:12px 14px;">
                        <div>
                            <div style="font-weight:700; color:#0F172A;"><?= htmlspecialchars($ins['nama_koperasi']) ?></div>
                            <div style="font-size:11.5px; color:#64748B;">Kec. <?= htmlspecialchars($ins['kecamatan']) ?> (<?= htmlspecialchars($ins['nama_divisi']) ?>)</div>
                        </div>
                    </td>
                    <td data-label="Barang & Target" style="padding:12px 14px;">
                        <div>
                            <div style="font-weight:800; color:#0F172A;"><?= htmlspecialchars($ins['nama_komoditas']) ?></div>
                            <div style="font-size:12px; color:#D97706; font-weight:700;">Target: <?= number_format($ins['target_jumlah'], 0, ',', '.') ?> <?= htmlspecialchars($ins['satuan']) ?></div>
                        </div>
                    </td>
                    <td data-label="Catatan" style="padding:12px 14px; font-size:12px; color:#475569; max-width:250px;">
                        <?= htmlspecialchars($ins['catatan_pusat'] ?: 'Segera koordinasikan panen dengan kelompok tani binaan.') ?>
                    </td>
                    <td data-label="Ubah Status Progres" class="td-card-action" style="padding:12px 14px;">
                        <?php if (in_array($role, ['konsumen', 'primer'], true)): ?>
                            <form method="POST" style="margin:0;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="aksi" value="update_progres">
                                <input type="hidden" name="id_instruksi" value="<?= $ins['id_instruksi'] ?>">
                                <select name="status_instruksi" onchange="this.form.submit()" style="padding:7px 11px; border-radius:9px; border:1px solid <?= $sel_bdr ?>; font-size:12px; font-weight:800; background:<?= $sel_bg ?>; color:<?= $sel_col ?>; cursor:pointer; outline:none;">
                                    <?php foreach (['Terkirim ke Primer', 'Diteruskan ke Petani', 'Terpenuhi'] as $st): ?>
                                        <option value="<?= $st ?>" <?= $st_ins === $st ? 'selected' : '' ?>><?= $st ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        <?php else: ?>
                            <span style="background:<?= $sel_bg ?>; color:<?= $sel_col ?>; padding:5px 11px; border-radius:99px; font-size:11.5px; font-weight:800; display:inline-block;">
                                <?= htmlspecialchars($st_ins) ?>
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($role === 'konsumen'): ?>
<!-- MODAL KIRIM PERMINTAAN TAMBAH STOK KE CABANG & PETANI -->
<div id="modalInstruksi" onclick="if(event.target===this) closeInstruksiModal()" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.62); z-index:999; align-items:center; justify-content:center; padding:16px; backdrop-filter:blur(2px);">
    <div style="background:#FFFFFF; width:100%; max-width:520px; max-height:92vh; overflow-y:auto; border-radius:20px; padding:24px 26px; box-shadow:0 25px 50px rgba(0,0,0,0.25);">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; margin-bottom:18px; padding-bottom:14px; border-bottom:1px solid #F1F5F9;">
            <div>
                <span style="display:inline-block; background:#FEF2F2; color:#DC2626; font-size:10.5px; font-weight:800; padding:3px 10px; border-radius:99px; margin-bottom:5px;">SURAT TUGAS RESTOCK</span>
                <h3 style="margin:0; font-size:18px; font-weight:800; color:#0F172A;">Minta Tambah Stok ke Cabang</h3>
                <div style="font-size:12px; color:#64748B; margin-top:2px;">Cabang kecamatan akan meneruskan permintaan ini ke petani mitra.</div>
            </div>
            <button type="button" onclick="closeInstruksiModal()" style="background:#F1F5F9; border:none; width:34px; height:34px; border-radius:50%; display:flex; align-items:center; justify-content:center; cursor:pointer; flex-shrink:0;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#475569" stroke-width="2.4"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <form method="POST" style="display:flex; flex-direction:column; gap:14px;">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="kirim_instruksi">

            <div>
                <label style="font-size:12px; font-weight:700; color:#334155;">Pilih Cabang Koperasi Kecamatan</label>
                <select name="id_primer" id="ins_id_primer" required style="width:100%; box-sizing:border-box; padding:10px 12px; border:1px solid #CBD5E1; border-radius:10px; margin-top:5px; font-weight:600; font-size:13px; background:#FFFFFF;">
                    <?php foreach ($primer_list as $p): ?>
                        <option value="<?= $p['id_primer'] ?>"><?= htmlspecialchars($p['nama_koperasi']) ?> — Kec. <?= htmlspecialchars($p['kecamatan']) ?> (<?= htmlspecialchars($p['nama_divisi']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="ews-form-row-2">
                <div>
                    <label style="font-size:12px; font-weight:700; color:#334155;">Nama Barang / Komoditas</label>
                    <input type="text" name="nama_komoditas" id="ins_nama_komoditas" placeholder="Contoh: Kentang Granola" required style="width:100%; box-sizing:border-box; padding:10px 12px; border:1px solid #CBD5E1; border-radius:10px; margin-top:5px; font-size:13px;">
                </div>
                <div>
                    <label style="font-size:12px; font-weight:700; color:#334155;">Kategori</label>
                    <select name="kategori" id="ins_kategori" style="width:100%; box-sizing:border-box; padding:10px 12px; border:1px solid #CBD5E1; border-radius:10px; margin-top:5px; font-size:13px; background:#FFFFFF;">
                        <option value="Komoditas Tanaman">Komoditas Tanaman</option>
                        <option value="Saprodi">Saprodi</option>
                        <option value="Alsintan">Alsintan</option>
                        <option value="Produk Olahan">Produk Olahan</option>
                        <option value="Produk Lainnya">Produk Lainnya</option>
                    </select>
                </div>
            </div>

            <div class="ews-form-row-3">
                <div>
                    <label style="font-size:12px; font-weight:700; color:#334155;">Jumlah Target</label>
                    <input type="number" name="target_jumlah" id="ins_target" value="150" min="1" required style="width:100%; box-sizing:border-box; padding:10px 12px; border:1px solid #CBD5E1; border-radius:10px; margin-top:5px; font-size:13px; font-weight:700;">
                </div>
                <div>
                    <label style="font-size:12px; font-weight:700; color:#334155;">Satuan</label>
                    <input type="text" name="satuan" id="ins_satuan" value="Kg" required style="width:100%; box-sizing:border-box; padding:10px 12px; border:1px solid #CBD5E1; border-radius:10px; margin-top:5px; font-size:13px;">
                </div>
                <div>
                    <label style="font-size:12px; font-weight:700; color:#DC2626;">Tingkat Prioritas</label>
                    <select name="tingkat_urgensi" id="ins_urgensi" style="width:100%; box-sizing:border-box; padding:10px 12px; border:1px solid #CBD5E1; border-radius:10px; margin-top:5px; font-weight:700; font-size:13px; background:#FFFFFF;">
                        <option value="Kritis (< 20)">Kritis (&lt; 20)</option>
                        <option value="Waspada (< 50)" selected>Waspada (&lt; 50)</option>
                        <option value="Prediksi Musiman">Prediksi Musiman</option>
                    </select>
                </div>
            </div>

            <div>
                <label style="font-size:12px; font-weight:700; color:#334155;">Catatan untuk Cabang &amp; Petani</label>
                <textarea name="catatan_pusat" id="ins_catatan" rows="2" placeholder="Tulis pesan jadwal panen, kualitas barang, atau target pengiriman..." style="width:100%; box-sizing:border-box; padding:10px 12px; border:1px solid #CBD5E1; border-radius:10px; margin-top:5px; font-family:inherit; font-size:13px;"></textarea>
            </div>

            <button type="submit" class="ews-btn" style="background:#059669; color:#FFFFFF; padding:13px; border-radius:12px; font-weight:800; font-size:13.5px; margin-top:4px; box-shadow:0 4px 14px rgba(5,150,105,0.25);">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                <span>Kirim Permintaan Sekarang</span>
            </button>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
function openInstruksiModal(data = null) {
    const modal = document.getElementById('modalInstruksi');
    if (!modal) return;

    if (data) {
        document.getElementById('ins_id_primer').value      = data.id_primer;
        document.getElementById('ins_nama_komoditas').value = data.nama_komoditas;
        document.getElementById('ins_kategori').value       = data.kategori;
        document.getElementById('ins_satuan').value         = data.satuan;
        document.getElementById('ins_target').value         = data.target;
        document.getElementById('ins_urgensi').value        = data.urgensi;
    } else {
        document.getElementById('ins_nama_komoditas').value = '';
        document.getElementById('ins_kategori').value       = 'Komoditas Tanaman';
        document.getElementById('ins_satuan').value         = 'Kg';
        document.getElementById('ins_target').value         = 150;
        document.getElementById('ins_urgensi').value        = 'Waspada (< 50)';
        document.getElementById('ins_catatan').value        = '';
    }
    modal.style.display = 'flex';
}

function closeInstruksiModal() {
    const modal = document.getElementById('modalInstruksi');
    if (modal) modal.style.display = 'none';
}

// Filter & Pencarian Pantauan Stok
let currentStockLevel = 'ALL';
function filterStocks(level, btn) {
    currentStockLevel = level;
    document.querySelectorAll('#stockFilterBar .ews-pill').forEach(el => el.classList.remove('active'));
    btn.classList.add('active');
    applyStockFilters();
}
function searchStocks() {
    applyStockFilters();
}
function applyStockFilters() {
    const q = document.getElementById('searchStockInput').value.toLowerCase();
    document.querySelectorAll('.stock-data-row').forEach(row => {
        const lvl = row.getAttribute('data-level');
        const txt = row.innerText.toLowerCase();
        const matchLvl  = (currentStockLevel === 'ALL' || lvl === currentStockLevel);
        const matchText = txt.includes(q);
        row.style.display = (matchLvl && matchText) ? '' : 'none';
    });
}

// Filter Riwayat Permintaan Stok
function filterInstruksi(status, btn) {
    document.querySelectorAll('#insFilterBar .ews-pill').forEach(el => el.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.ins-data-row').forEach(row => {
        const st = row.getAttribute('data-status');
        row.style.display = (status === 'ALL' || st === status) ? '' : 'none';
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