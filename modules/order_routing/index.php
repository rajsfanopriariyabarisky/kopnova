<?php
require_once '../../config/database.php';
cekAkses(['konsumen', 'primer']);

$role = $_SESSION['user']['role'];
$user = $_SESSION['user'];

// PROSES 1: Terbitkan Instruksi Picking List Serentak ke Semua Kecamatan Terkait (Khusus Konsumen)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'terbitkan_routing') {
    verify_csrf();
    cekAkses(['konsumen']);

    $id_order = (int)($_POST['id_order'] ?? 0);
    if ($id_order <= 0) {
        header("Location: index.php?err=" . urlencode("ID pesanan tidak valid."));
        exit;
    }

    $no_sj = 'SJ-KPN-' . date('Ymd') . '-' . str_pad((string)$id_order, 4, '0', STR_PAD_LEFT);

    $pdo->prepare("UPDATE orders SET no_surat_jalan = COALESCE(NULLIF(no_surat_jalan, ''), ?), status_order = 'Diproses Koperasi' WHERE id_order = ?")
        ->execute([$no_sj, $id_order]);

    $pdo->prepare("UPDATE order_items SET status_routing = 'Instruksi Terkirim' WHERE id_order = ? AND status_routing = 'Menunggu Instruksi'")
        ->execute([$id_order]);

    header("Location: index.php?msg=" . urlencode("Instruksi Picking List multi-kecamatan berhasil diterbitkan ($no_sj)."));
    exit;
}

// PROSES 2: Update Status Picking Per Kecamatan / Per Item (Dengan Ownership & Role Check)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'update_item_routing') {
    verify_csrf();

    $id_item        = (int)($_POST['id_item'] ?? 0);
    $status_routing = $_POST['status_routing'] ?? '';

    $allowed_statuses = ($role === 'konsumen')
        ? ['Menunggu Instruksi', 'Instruksi Terkirim', 'Siap Diambil Primer', 'Terkonsolidasi Pusat']
        : ['Instruksi Terkirim', 'Siap Diambil Primer'];

    if ($id_item <= 0 || !in_array($status_routing, $allowed_statuses, true)) {
        header("Location: index.php?err=" . urlencode("Status routing tidak valid atau di luar wewenang role Anda."));
        exit;
    }

    if ($role === 'primer') {
        $id_primer_login = (int)($user['id_primer'] ?? 0);
        $stmt = $pdo->prepare("UPDATE order_items oi
                               JOIN produk_etalase pr ON oi.id_produk = pr.id_produk
                               SET oi.status_routing = ?
                               WHERE oi.id_item = ? AND pr.id_primer = ? AND oi.status_routing <> 'Terkonsolidasi Pusat'");
        $stmt->execute([$status_routing, $id_item, $id_primer_login]);
    } else {
        $stmt = $pdo->prepare("UPDATE order_items SET status_routing = ? WHERE id_item = ?");
        $stmt->execute([$status_routing, $id_item]);
    }

    if ($stmt->rowCount() > 0) {
        header("Location: index.php?msg=" . urlencode("Status kesiapan barang kecamatan diperbarui menjadi: " . $status_routing));
    } else {
        header("Location: index.php?err=" . urlencode("Item tidak berubah, sudah terkonsolidasi pusat, atau bukan wewenang unit Anda."));
    }
    exit;
}

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

$orders = $pdo->query("SELECT o.*, u.nama_lengkap AS nama_buyer, u.no_hp AS hp_buyer 
                       FROM orders o 
                       JOIN users u ON o.id_buyer = u.id_user 
                       ORDER BY o.id_order DESC")->fetchAll();

// Pre-proses pengelompokan item per Koperasi Primer agar statistik & tampilan akurat untuk semua role
$prepared_orders     = [];
$total_orders_aktif  = 0;
$total_sj_terbit     = 0;
$total_titik_pickup  = 0;

$stmtItems = $pdo->prepare("SELECT oi.*, pr.nama_produk, pr.kode_produk, pr.satuan, kp.id_primer, kp.nama_koperasi, kp.kecamatan, kp.ketua_koperasi, d.nama_divisi 
                            FROM order_items oi 
                            JOIN produk_etalase pr ON oi.id_produk = pr.id_produk 
                            JOIN koperasi_primer kp ON pr.id_primer = kp.id_primer 
                            JOIN divisi_wilayah d ON pr.id_divisi = d.id_divisi 
                            WHERE oi.id_order = ? 
                            ORDER BY kp.id_primer ASC");

foreach ($orders as $o) {
    $stmtItems->execute([(int)$o['id_order']]);
    $items = $stmtItems->fetchAll();

    $grouped_primer = [];
    $total_items    = 0;
    $ready_items    = 0;

    foreach ($items as $it) {
        if ($role === 'primer' && !empty($user['id_primer']) && (int)$it['id_primer'] !== (int)$user['id_primer']) {
            continue;
        }
        $key = (int)$it['id_primer'];
        if (!isset($grouped_primer[$key])) {
            $grouped_primer[$key] = [
                'nama_koperasi'  => $it['nama_koperasi'],
                'kecamatan'      => $it['kecamatan'],
                'nama_divisi'    => $it['nama_divisi'],
                'ketua_koperasi' => $it['ketua_koperasi'],
                'items'          => [],
                'ready_count'    => 0
            ];
        }
        $grouped_primer[$key]['items'][] = $it;
        $total_items++;

        if (in_array($it['status_routing'], ['Siap Diambil Primer', 'Terkonsolidasi Pusat'], true)) {
            $grouped_primer[$key]['ready_count']++;
            $ready_items++;
        }
    }

    if (empty($grouped_primer)) {
        continue;
    }

    $total_orders_aktif++;
    if (!empty($o['no_surat_jalan'])) {
        $total_sj_terbit++;
    }
    $total_titik_pickup += count($grouped_primer);

    $o['grouped_primer'] = $grouped_primer;
    $o['total_items']    = $total_items;
    $o['ready_items']    = $ready_items;
    $prepared_orders[]   = $o;
}
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

    .rt-grid-4 {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 16px;
    }
    .rt-kpi {
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
    .rt-kpi:hover {
        transform: translateY(-2px);
    }
    .rt-kpi-val {
        font-size: clamp(1.3rem, 1.8vw, 1.65rem);
        font-weight: 900;
        color: #0F172A;
        margin: 8px 0 3px;
        font-variant-numeric: tabular-nums;
    }

    .rt-toolbar {
        background: #FFFFFF;
        border-radius: 16px;
        border: 1px solid #E2E8F0;
        padding: 15px 18px;
        margin-bottom: 20px;
        box-shadow: 0 2px 10px rgba(15, 23, 42, 0.025);
    }
    .rt-pill {
        padding: 7px 13px;
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
    .rt-pill:hover {
        border-color: #10B981;
        color: #065F46;
    }
    .rt-pill.active {
        background: #064E3B;
        color: #FFFFFF;
        border-color: #064E3B;
        box-shadow: 0 3px 10px rgba(6, 78, 59, 0.2);
    }
    .rt-search {
        padding: 9px 13px 9px 36px;
        border: 1px solid #CBD5E1;
        border-radius: 10px;
        font-size: 12.5px;
        width: 100%;
        box-sizing: border-box;
        background: #F8FAFC;
        outline: none;
        transition: border-color 0.2s, background 0.2s;
    }
    .rt-search:focus {
        background: #FFFFFF;
        border-color: #059669;
    }

    .rt-order-card {
        background: #FFFFFF;
        border-radius: 18px;
        border: 1px solid #E2E8F0;
        overflow: hidden;
        box-shadow: 0 4px 16px rgba(15, 23, 42, 0.035);
    }
    .rt-order-head {
        background: #F8FAFC;
        padding: 18px 22px;
        border-bottom: 1px solid #E2E8F0;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        flex-wrap: wrap;
        gap: 14px;
    }
    .rt-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        border: none;
        text-decoration: none;
        padding: 10px 15px;
        border-radius: 11px;
        font-weight: 700;
        font-size: 12.5px;
        cursor: pointer;
        white-space: nowrap;
        transition: filter 0.18s ease, transform 0.15s ease;
    }
    .rt-btn:hover {
        filter: brightness(1.06);
        transform: translateY(-1px);
    }

    .rt-pickup-grid {
        padding: 20px 22px;
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(330px, 1fr));
        gap: 16px;
    }
    .rt-pickup-card {
        border: 1px solid #CBD5E1;
        border-radius: 15px;
        padding: 16px;
        background: #FAFAF9;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    .rt-item-row {
        background: #FFFFFF;
        padding: 11px 13px;
        border-radius: 11px;
        border: 1px solid #E2E8F0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }
    .rt-status-select {
        padding: 7px 10px;
        border-radius: 9px;
        font-size: 11.5px;
        font-weight: 800;
        cursor: pointer;
        outline: none;
        transition: border-color 0.2s;
    }

    .mobile-nav-trigger {
        display: none;
    }

    /* TABLET & LAPTOP SEDANG */
    @media (max-width: 1120px) {
        .rt-grid-4 {
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

    /* OPTIMASI LAYAR PONSEL (< 640px) */
    @media (max-width: 640px) {
        .rt-grid-4 {
            grid-template-columns: minmax(0, 1fr);
        }
        .rt-hero {
            padding: 22px 20px !important;
        }
        .rt-hero-title {
            font-size: 19px !important;
        }
        .rt-hero-link {
            width: 100%;
            justify-content: center !important;
        }
        .rt-order-head {
            padding: 16px;
        }
        .rt-order-actions {
            width: 100%;
            flex-direction: column;
        }
        .rt-order-actions form,
        .rt-order-actions button,
        .rt-order-actions a {
            width: 100%;
            box-sizing: border-box;
        }
        .rt-pickup-grid {
            padding: 14px;
            grid-template-columns: minmax(0, 1fr);
        }
        .rt-item-row {
            flex-direction: column;
            align-items: stretch;
        }
        .rt-item-row form,
        .rt-item-row select {
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
<div id="kopBackdrop" onclick="toggleKopSidebar()" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.45); z-index:45; backdrop-filter:blur(2px);"></div>

<!-- BANNER HEADER CLEAN & PROFESIONAL -->
<div class="rt-hero" style="background:linear-gradient(125deg, #064E3B 0%, #059669 100%); border-radius:20px; padding:26px 30px; color:#FFFFFF; margin-bottom:20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:18px; box-shadow:0 10px 25px rgba(5, 150, 105, 0.16); position:relative; overflow:hidden;">
    <div style="position:absolute; right:-30px; bottom:-50px; width:210px; height:210px; border-radius:50%; background:rgba(255,255,255,0.08); pointer-events:none;"></div>
    <div style="max-width:620px; position:relative; z-index:2;">
        <div style="display:inline-flex; align-items:center; gap:7px; background:rgba(255,255,255,0.16); color:#ECFDF5; font-size:11px; font-weight:800; padding:5px 12px; border-radius:99px; margin-bottom:10px; letter-spacing:0.3px;">
            <span style="width:7px; height:7px; border-radius:50%; background:#34D399; display:inline-block;"></span>
            <span>LOGISTICS &amp; MULTI-DISTRICT ORDER ROUTING</span>
        </div>
        <h2 class="rt-hero-title" style="margin:0 0 6px; font-size:22px; font-weight:800; line-height:1.3;">
            Konsolidasi &amp; Surat Jalan Multi-Kecamatan
        </h2>
        <p style="margin:0; font-size:13.5px; color:#ECFDF5; line-height:1.55; opacity:0.95;">
            Kelompokkan pesanan Buyer lintas kecamatan menjadi instruksi penyiapan barang (Picking List) per Koperasi Primer dan cetak Surat Jalan resmi.
        </p>
    </div>

    <a href="../transaksi/index.php" class="rt-hero-link" style="display:inline-flex; align-items:center; gap:8px; background:#FFFFFF; color:#064E3B; text-decoration:none; padding:12px 18px; border-radius:12px; font-weight:800; font-size:13px; box-shadow:0 6px 18px rgba(0,0,0,0.14); position:relative; z-index:2;">
        <span>Buku Besar Transaksi</span>
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
    </a>
</div>

<?php if (isset($_GET['msg'])): ?>
<div style="background:#ECFDF5; border:1px solid #A7F3D0; border-left:5px solid #059669; color:#065F46; padding:13px 18px; border-radius:12px; margin-bottom:20px; font-weight:700; font-size:13.5px; display:flex; align-items:center; gap:10px;">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex-shrink:0;"><polyline points="20 6 9 17 4 12"/></svg>
    <span><?= htmlspecialchars($_GET['msg']) ?></span>
</div>
<?php endif; ?>

<?php if (isset($_GET['err'])): ?>
<div style="background:#FEF2F2; border:1px solid #FECACA; border-left:5px solid #DC2626; color:#991B1B; padding:13px 18px; border-radius:12px; margin-bottom:20px; font-weight:700; font-size:13.5px; display:flex; align-items:center; gap:10px;">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" style="flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <span><?= htmlspecialchars($_GET['err']) ?></span>
</div>
<?php endif; ?>

<!-- 4 KARTU RINGKASAN OPERASIONAL LOGISTIK -->
<div class="rt-grid-4" style="margin-bottom:22px;">
    <div class="rt-kpi" style="border-left:5px solid #059669;">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
            <span style="font-size:12px; color:#64748B; font-weight:700;">Pesanan Aktif</span>
            <div style="width:35px; height:35px; border-radius:10px; background:#DCFCE7; color:#059669; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            </div>
        </div>
        <div class="rt-kpi-val"><?= $total_orders_aktif ?> Invoice</div>
        <div style="font-size:11.5px; color:#64748B;">Order masuk untuk diproses</div>
    </div>

    <div class="rt-kpi" style="border-left:5px solid #2563EB;">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
            <span style="font-size:12px; color:#64748B; font-weight:700;">Surat Jalan Terbit</span>
            <div style="width:35px; height:35px; border-radius:10px; background:#DBEAFE; color:#2563EB; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
        </div>
        <div class="rt-kpi-val" style="color:#1E40AF;"><?= $total_sj_terbit ?> Dokumen</div>
        <div style="font-size:11.5px; color:#64748B;">Picking list sudah disebar</div>
    </div>

    <div class="rt-kpi" style="border-left:5px solid #D97706;">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
            <span style="font-size:12px; color:#64748B; font-weight:700;">Menunggu Routing</span>
            <div style="width:35px; height:35px; border-radius:10px; background:#FEF3C7; color:#D97706; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
        </div>
        <div class="rt-kpi-val" style="color:#B45309;"><?= max(0, $total_orders_aktif - $total_sj_terbit) ?> Pesanan</div>
        <div style="font-size:11.5px; color:#64748B;">Belum diterbitkan surat jalan</div>
    </div>

    <div class="rt-kpi" style="border-left:5px solid #064E3B;">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
            <span style="font-size:12px; color:#64748B; font-weight:700;">Titik Pick-Up</span>
            <div style="width:35px; height:35px; border-radius:10px; background:#ECFDF5; color:#064E3B; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            </div>
        </div>
        <div class="rt-kpi-val"><?= $total_titik_pickup ?> Titik</div>
        <div style="font-size:11.5px; color:#64748B;">Koperasi primer kecamatan</div>
    </div>
</div>

<?php if (empty($prepared_orders)): ?>
    <div style="background:#FFFFFF; border-radius:18px; border:1px solid #E2E8F0; padding:44px 24px; text-align:center; box-shadow:0 2px 10px rgba(15,23,42,0.03);">
        <div style="width:52px; height:52px; border-radius:14px; background:#F1F5F9; color:#64748B; display:flex; align-items:center; justify-content:center; margin:0 auto 14px;">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
        </div>
        <div style="font-size:17.5px; font-weight:800; color:#0F172A; margin-bottom:6px;">Belum Ada Pesanan Buyer untuk Dikonsolidasikan</div>
        <p style="font-size:13.5px; color:#64748B; max-width:520px; margin:0 auto 18px; line-height:1.55;">
            Ketika Buyer melakukan checkout produk dari beberapa kecamatan dalam satu keranjang, sistem otomatis mengelompokkan titik pengambilan (Picking List) di halaman ini.
        </p>
        <a href="../produk/index.php" style="display:inline-flex; align-items:center; gap:7px; background:#059669; color:#FFFFFF; text-decoration:none; padding:10px 20px; border-radius:10px; font-weight:700; font-size:13px;">
            <span>Cek Ketersediaan Stok Etalase</span>
        </a>
    </div>
<?php else: ?>

    <!-- BILAH FILTER & PENCARIAN LIVE -->
    <div class="rt-toolbar">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
            <div style="display:flex; gap:7px; flex-wrap:wrap;" id="rtFilterBar">
                <button type="button" class="rt-pill active" onclick="filterRoutingOrders('ALL', this)">Semua (<?= count($prepared_orders) ?>)</button>
                <button type="button" class="rt-pill" onclick="filterRoutingOrders('NO_SJ', this)">Belum Terbit SJ</button>
                <button type="button" class="rt-pill" onclick="filterRoutingOrders('HAS_SJ', this)">Sudah Terbit SJ</button>
                <button type="button" class="rt-pill" onclick="filterRoutingOrders('PENDING_READY', this)">Perlu Penyiapan</button>
            </div>

            <div style="position:relative; min-width:240px; flex:1; max-width:340px;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#64748B" stroke-width="2.2" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); pointer-events:none;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" id="rtSearchInput" onkeyup="applyRoutingFilters()" placeholder="Cari invoice, buyer, kecamatan..." class="rt-search">
            </div>
        </div>
    </div>

    <div style="display:flex; flex-direction:column; gap:20px;" id="rtOrdersContainer">
        <?php foreach ($prepared_orders as $o): 
            $grouped_primer     = $o['grouped_primer'];
            $jumlah_titik_ambil = count($grouped_primer);
            $has_sj             = !empty($o['no_surat_jalan']);
            $all_ready          = ($o['total_items'] > 0 && $o['ready_items'] === $o['total_items']);
            $pct_ready          = $o['total_items'] > 0 ? round(($o['ready_items'] / $o['total_items']) * 100) : 0;
        ?>

        <div class="rt-order-card rt-order-item"
             data-has-sj="<?= $has_sj ? '1' : '0' ?>"
             data-all-ready="<?= $all_ready ? '1' : '0' ?>">

            <div class="rt-order-head">
                <div style="flex:1; min-width:240px;">
                    <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                        <span style="font-size:16px; font-weight:900; color:#064E3B; font-family:monospace;"><?= htmlspecialchars($o['no_invoice']) ?></span>

                        <span style="background:#FEF3C7; color:#92400E; font-size:11px; font-weight:800; padding:3px 10px; border-radius:99px;">
                            <?= $jumlah_titik_ambil ?> Titik Kecamatan
                        </span>

                        <?php if ($has_sj): ?>
                            <span style="background:#DCFCE7; color:#166534; font-size:11px; font-weight:800; padding:3px 10px; border-radius:99px;">
                                SJ: <?= htmlspecialchars($o['no_surat_jalan']) ?>
                            </span>
                        <?php else: ?>
                            <span style="background:#FEE2E2; color:#991B1B; font-size:11px; font-weight:800; padding:3px 10px; border-radius:99px;">
                                Menunggu Routing
                            </span>
                        <?php endif; ?>

                        <span style="background:<?= $all_ready ? '#DCFCE7' : '#DBEAFE' ?>; color:<?= $all_ready ? '#166534' : '#1E40AF' ?>; font-size:11px; font-weight:800; padding:3px 10px; border-radius:99px;">
                            Kesiapan: <?= $o['ready_items'] ?>/<?= $o['total_items'] ?> Item (<?= $pct_ready ?>%)
                        </span>
                    </div>

                    <div style="font-size:13px; color:#334155; margin-top:7px; line-height:1.45;">
                        Buyer: <strong><?= htmlspecialchars($o['nama_buyer']) ?></strong>
                        <span style="color:#64748B;">(<?= htmlspecialchars($o['hp_buyer'] ?: '-') ?>)</span>
                        • Tujuan: <span style="color:#0F172A; font-weight:600;"><?= htmlspecialchars($o['alamat_pengiriman']) ?></span>
                    </div>
                </div>

                <div class="rt-order-actions" style="display:flex; gap:8px; flex-wrap:wrap;">
                    <?php if ($role === 'konsumen'): ?>
                        <form method="POST" style="margin:0;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="aksi" value="terbitkan_routing">
                            <input type="hidden" name="id_order" value="<?= (int)$o['id_order'] ?>">
                            <button type="submit" class="rt-btn" style="background:#059669; color:#FFFFFF; box-shadow:0 4px 12px rgba(5,150,105,0.22);">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                                <span><?= !$has_sj ? 'Terbitkan Picking List & Surat Jalan' : 'Sinkronkan Instruksi Primer' ?></span>
                            </button>
                        </form>
                    <?php endif; ?>

                    <a href="surat_jalan.php?id_order=<?= (int)$o['id_order'] ?>" target="_blank" class="rt-btn" style="background:#0F172A; color:#FFFFFF;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                        <span>Cetak Surat Jalan &amp; Invoice</span>
                    </a>
                </div>
            </div>

            <div class="rt-pickup-grid">
                <?php $titik = 1; foreach ($grouped_primer as $gp): 
                    $gp_total = count($gp['items']);
                    $gp_ready = (int)$gp['ready_count'];
                    $gp_done  = ($gp_total > 0 && $gp_ready === $gp_total);
                ?>
                <div class="rt-pickup-card" style="border-color:<?= $gp_done ? '#A7F3D0' : '#CBD5E1' ?>;">
                    <div>
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:8px; margin-bottom:12px; padding-bottom:10px; border-bottom:1px dashed #CBD5E1;">
                            <div>
                                <span style="font-size:10.5px; font-weight:800; color:#059669; text-transform:uppercase; letter-spacing:0.3px;">
                                    Titik Pick-Up #<?= $titik++ ?> • <?= htmlspecialchars($gp['nama_divisi']) ?>
                                </span>
                                <div style="font-size:15px; font-weight:800; color:#0F172A; margin-top:2px;"><?= htmlspecialchars($gp['nama_koperasi']) ?></div>
                                <div style="font-size:11.5px; color:#64748B; margin-top:2px;">
                                    Kec. <?= htmlspecialchars($gp['kecamatan']) ?> • PIC: <?= htmlspecialchars($gp['ketua_koperasi']) ?>
                                </div>
                            </div>
                            <span style="background:<?= $gp_done ? '#DCFCE7' : '#F1F5F9' ?>; color:<?= $gp_done ? '#166534' : '#475569' ?>; font-size:11px; font-weight:800; padding:3px 9px; border-radius:99px; white-space:nowrap;">
                                <?= $gp_ready ?>/<?= $gp_total ?> Siap
                            </span>
                        </div>

                        <div style="display:flex; flex-direction:column; gap:10px;">
                            <?php foreach ($gp['items'] as $item): 
                                $cur_st = $item['status_routing'];

                                $st_bg = '#FEF3C7'; $st_col = '#92400E'; $st_bdr = '#FDE68A';
                                if ($cur_st === 'Menunggu Instruksi') {
                                    $st_bg = '#F1F5F9'; $st_col = '#475569'; $st_bdr = '#CBD5E1';
                                } elseif ($cur_st === 'Instruksi Terkirim') {
                                    $st_bg = '#DBEAFE'; $st_col = '#1E40AF'; $st_bdr = '#BFDBFE';
                                } elseif ($cur_st === 'Terkonsolidasi Pusat') {
                                    $st_bg = '#DCFCE7'; $st_col = '#166534'; $st_bdr = '#BBF7D0';
                                }
                            ?>
                            <div class="rt-item-row">
                                <div style="flex:1; min-width:160px;">
                                    <div style="font-size:13px; font-weight:800; color:#0F172A;"><?= htmlspecialchars($item['nama_produk']) ?></div>
                                    <div style="font-size:11.5px; color:#475569; margin-top:2px;">
                                        Volume Ambil: <strong><?= number_format((int)$item['qty'], 0, ',', '.') ?> <?= htmlspecialchars($item['satuan']) ?></strong> • <?= rupiah($item['subtotal']) ?>
                                    </div>
                                </div>

                                <?php if ($role === 'primer' && $cur_st === 'Terkonsolidasi Pusat'): ?>
                                    <span style="background:#DCFCE7; color:#166534; border:1px solid #BBF7D0; padding:6px 11px; border-radius:9px; font-size:11.5px; font-weight:800;">
                                        Terkonsolidasi Pusat
                                    </span>
                                <?php else: ?>
                                    <form method="POST" style="margin:0;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="aksi" value="update_item_routing">
                                        <input type="hidden" name="id_item" value="<?= (int)$item['id_item'] ?>">
                                        <select name="status_routing" onchange="this.form.submit()" class="rt-status-select" style="border:1px solid <?= $st_bdr ?>; background:<?= $st_bg ?>; color:<?= $st_col ?>;">
                                            <?php if ($role === 'konsumen'): ?>
                                                <?php foreach (['Menunggu Instruksi', 'Instruksi Terkirim', 'Siap Diambil Primer', 'Terkonsolidasi Pusat'] as $sr): ?>
                                                    <option value="<?= $sr ?>" <?= $cur_st === $sr ? 'selected' : '' ?>><?= $sr ?></option>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <?php if ($cur_st === 'Menunggu Instruksi'): ?>
                                                    <option value="Menunggu Instruksi" selected disabled>Menunggu Instruksi</option>
                                                <?php endif; ?>
                                                <?php foreach (['Instruksi Terkirim', 'Siap Diambil Primer'] as $sr): ?>
                                                    <option value="<?= $sr ?>" <?= $cur_st === $sr ? 'selected' : '' ?>><?= $sr ?></option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                    </form>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div id="rtEmptyFilter" style="display:none; background:#FFFFFF; border-radius:18px; border:1px solid #E2E8F0; padding:38px 24px; text-align:center; margin-top:12px;">
        <div style="font-size:16.5px; font-weight:800; color:#0F172A; margin-bottom:6px;">Pesanan Tidak Ditemukan</div>
        <p style="font-size:13px; color:#64748B; margin:0 0 14px;">Coba ubah kata kunci pencarian atau pilih filter status lainnya.</p>
        <button type="button" onclick="resetRoutingFilters()" style="background:#064E3B; color:#FFFFFF; border:none; padding:9px 18px; border-radius:10px; font-size:13px; font-weight:700; cursor:pointer;">
            Tampilkan Semua Pesanan
        </button>
    </div>

<?php endif; ?>

<script>
let activeRoutingFilter = 'ALL';

function filterRoutingOrders(mode, btn) {
    activeRoutingFilter = mode;
    document.querySelectorAll('#rtFilterBar .rt-pill').forEach(el => el.classList.remove('active'));
    btn.classList.add('active');
    applyRoutingFilters();
}

function applyRoutingFilters() {
    const input = document.getElementById('rtSearchInput');
    const q     = input ? input.value.toLowerCase().trim() : '';
    const cards = document.querySelectorAll('.rt-order-item');
    let visible = 0;

    cards.forEach(card => {
        const hasSj    = card.getAttribute('data-has-sj') === '1';
        const allReady = card.getAttribute('data-all-ready') === '1';
        const txt      = card.innerText.toLowerCase();

        let matchMode = true;
        if (activeRoutingFilter === 'NO_SJ')         matchMode = !hasSj;
        else if (activeRoutingFilter === 'HAS_SJ')   matchMode = hasSj;
        else if (activeRoutingFilter === 'PENDING_READY') matchMode = !allReady;

        const matchText = txt.includes(q);
        if (matchMode && matchText) {
            card.style.display = 'block';
            visible++;
        } else {
            card.style.display = 'none';
        }
    });

    const emptyBox = document.getElementById('rtEmptyFilter');
    if (emptyBox) {
        emptyBox.style.display = visible === 0 ? 'block' : 'none';
    }
}

function resetRoutingFilters() {
    const input = document.getElementById('rtSearchInput');
    if (input) input.value = '';
    const firstPill = document.querySelector('#rtFilterBar .rt-pill');
    if (firstPill) filterRoutingOrders('ALL', firstPill);
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