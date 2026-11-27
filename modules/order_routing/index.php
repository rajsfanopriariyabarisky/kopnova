<?php
require_once '../../config/database.php';
cekAkses(['konsumen', 'primer']);

$role = $_SESSION['user']['role'] ?? '';
$user = $_SESSION['user'] ?? [];

function redirectRouting(string $type, string $text): void {
    header("Location: index.php?" . $type . "=" . urlencode($text));
    exit;
}

// PROSES 1: Terbitkan Instruksi Picking List Serentak ke Semua Kecamatan Terkait (Khusus Konsumen)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'terbitkan_routing') {
    verify_csrf();
    cekAkses(['konsumen']);

    $id_order = (int)($_POST['id_order'] ?? 0);
    if ($id_order <= 0) {
        redirectRouting('err', 'ID pesanan tidak valid.');
    }

    try {
        $cekOrder = $pdo->prepare("SELECT id_order, no_surat_jalan FROM orders WHERE id_order = ?");
        $cekOrder->execute([$id_order]);
        $targetOrder = $cekOrder->fetch();

        if (!$targetOrder) {
            redirectRouting('err', 'Data pesanan tidak ditemukan.');
        }

        $pdo->beginTransaction();

        $no_sj = !empty($targetOrder['no_surat_jalan'])
            ? $targetOrder['no_surat_jalan']
            : 'SJ-KPN-' . date('Ymd') . '-' . str_pad((string)$id_order, 4, '0', STR_PAD_LEFT);

        $pdo->prepare("
            UPDATE orders
            SET no_surat_jalan = COALESCE(NULLIF(no_surat_jalan, ''), ?),
                status_order   = CASE WHEN status_order = 'Menunggu Pembayaran' THEN status_order ELSE 'Diproses Koperasi' END
            WHERE id_order = ?
        ")->execute([$no_sj, $id_order]);

        $pdo->prepare("
            UPDATE order_items
            SET status_routing = 'Instruksi Terkirim'
            WHERE id_order = ? AND status_routing = 'Menunggu Instruksi'
        ")->execute([$id_order]);

        $pdo->commit();
        redirectRouting('msg', "Instruksi Picking List multi-kecamatan berhasil diterbitkan ({$no_sj}).");
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        redirectRouting('err', 'Gagal menerbitkan instruksi routing. Silakan coba kembali.');
    }
}

// PROSES 2: Update Status Picking Per Kecamatan / Per Item (Dengan Ownership & Role Check)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'update_item_routing') {
    verify_csrf();

    $id_item        = (int)($_POST['id_item'] ?? 0);
    $status_routing = trim($_POST['status_routing'] ?? '');

    $allowed_statuses = ($role === 'konsumen')
        ? ['Menunggu Instruksi', 'Instruksi Terkirim', 'Siap Diambil Primer', 'Terkonsolidasi Pusat']
        : ['Instruksi Terkirim', 'Siap Diambil Primer'];

    if ($id_item <= 0 || !in_array($status_routing, $allowed_statuses, true)) {
        redirectRouting('err', 'Status routing tidak valid atau di luar wewenang role Anda.');
    }

    try {
        if ($role === 'primer') {
            $id_primer_login = (int)($user['id_primer'] ?? 0);
            $stmt = $pdo->prepare("
                UPDATE order_items oi
                JOIN produk_etalase pr ON oi.id_produk = pr.id_produk
                SET oi.status_routing = ?
                WHERE oi.id_item = ? AND pr.id_primer = ? AND oi.status_routing <> 'Terkonsolidasi Pusat'
            ");
            $stmt->execute([$status_routing, $id_item, $id_primer_login]);
        } else {
            $stmt = $pdo->prepare("UPDATE order_items SET status_routing = ? WHERE id_item = ?");
            $stmt->execute([$status_routing, $id_item]);
        }

        if ($stmt->rowCount() > 0) {
            redirectRouting('msg', "Status kesiapan barang kecamatan diperbarui menjadi: {$status_routing}");
        } else {
            redirectRouting('err', 'Item tidak berubah, sudah terkonsolidasi pusat, atau bukan wewenang unit Anda.');
        }
    } catch (PDOException $e) {
        redirectRouting('err', 'Gagal memperbarui status kesiapan barang.');
    }
}

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

// Ambil seluruh pesanan buyer
$orders = $pdo->query("
    SELECT o.*, u.nama_lengkap AS nama_buyer, u.no_hp AS hp_buyer
    FROM orders o
    JOIN users u ON o.id_buyer = u.id_user
    ORDER BY o.id_order DESC
")->fetchAll();

// Ambil seluruh item pesanan sekaligus (Eliminasi N+1 Query)
$items_by_order = [];
if (!empty($orders)) {
    $order_ids    = array_map(fn($row) => (int)$row['id_order'], $orders);
    $placeholders = implode(',', array_fill(0, count($order_ids), '?'));

    $sqlItems = "
        SELECT oi.*, pr.nama_produk, pr.kode_produk, pr.satuan,
               kp.id_primer, kp.nama_koperasi, kp.kecamatan, kp.ketua_koperasi,
               d.nama_divisi
        FROM order_items oi
        JOIN produk_etalase pr ON oi.id_produk = pr.id_produk
        JOIN koperasi_primer kp ON pr.id_primer = kp.id_primer
        JOIN divisi_wilayah d ON pr.id_divisi = d.id_divisi
        WHERE oi.id_order IN ({$placeholders})
    ";
    $paramsItems = $order_ids;

    if ($role === 'primer') {
        $sqlItems .= " AND kp.id_primer = ? ";
        $paramsItems[] = (int)($user['id_primer'] ?? 0);
    }

    $sqlItems .= " ORDER BY oi.id_order DESC, kp.id_primer ASC, oi.id_item ASC ";

    $stmtAllItems = $pdo->prepare($sqlItems);
    $stmtAllItems->execute($paramsItems);

    foreach ($stmtAllItems->fetchAll() as $it) {
        $oid = (int)$it['id_order'];
        $items_by_order[$oid][] = $it;
    }
}

// Pre-proses pengelompokan item per Koperasi Primer agar statistik & tampilan akurat untuk semua role
$prepared_orders    = [];
$total_orders_aktif = 0;
$total_sj_terbit    = 0;
$total_titik_pickup = 0;

foreach ($orders as $o) {
    $oid   = (int)$o['id_order'];
    $items = $items_by_order[$oid] ?? [];

    if (empty($items)) {
        continue;
    }

    $grouped_primer = [];
    $total_items    = 0;
    $ready_items    = 0;
    $search_tokens  = [
        $o['no_invoice'] ?? '',
        $o['no_surat_jalan'] ?? '',
        $o['nama_buyer'] ?? '',
        $o['hp_buyer'] ?? '',
        $o['alamat_pengiriman'] ?? ''
    ];

    foreach ($items as $it) {
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
            $search_tokens[] = $it['nama_koperasi'] ?? '';
            $search_tokens[] = $it['kecamatan'] ?? '';
            $search_tokens[] = $it['nama_divisi'] ?? '';
        }

        $grouped_primer[$key]['items'][] = $it;
        $search_tokens[] = $it['nama_produk'] ?? '';
        $total_items++;

        if (in_array($it['status_routing'], ['Siap Diambil Primer', 'Terkonsolidasi Pusat'], true)) {
            $grouped_primer[$key]['ready_count']++;
            $ready_items++;
        }
    }

    $total_orders_aktif++;
    if (!empty($o['no_surat_jalan'])) {
        $total_sj_terbit++;
    }
    $total_titik_pickup += count($grouped_primer);

    $o['grouped_primer'] = $grouped_primer;
    $o['total_items']    = $total_items;
    $o['ready_items']    = $ready_items;
    $o['search_blob']    = strtolower(implode(' ', $search_tokens));
    $prepared_orders[]   = $o;
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

    .rt-wrap {
        display: flex;
        flex-direction: column;
        gap: 22px;
        color: #0F172A;
    }

    /* HERO BANNER KONSISTEN DENGAN MODUL KOPNOVA */
    .rt-hero {
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
    .rt-eyebrow {
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
    .rt-eyebrow-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: #FBBF24;
    }
    .rt-hero-title {
        margin: 0 0 6px;
        font-size: 22px;
        font-weight: 800;
        letter-spacing: -0.3px;
        line-height: 1.25;
    }
    .rt-hero-sub {
        margin: 0;
        font-size: 13.5px;
        color: rgba(236, 253, 245, 0.88);
        max-width: 630px;
        line-height: 1.5;
    }
    .rt-hero-link {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: #F59E0B;
        color: #042F24;
        text-decoration: none;
        padding: 12px 18px;
        border-radius: 12px;
        font-weight: 800;
        font-size: 13px;
        box-shadow: 0 6px 18px rgba(245, 158, 11, 0.3);
        transition: background-color 0.16s ease, transform 0.14s ease;
        white-space: nowrap;
    }
    .rt-hero-link:hover {
        background: #FBBF24;
    }
    .rt-hero-link:active {
        transform: scale(0.98);
    }

    /* ALERT NOTIFIKASI */
    .rt-alert {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 13px 16px;
        border-radius: 12px;
        font-size: 13.5px;
        font-weight: 600;
    }
    .rt-alert-success {
        background: #DCFCE7;
        border: 1px solid #86EFAC;
        color: #166534;
    }
    .rt-alert-error {
        background: #FEE2E2;
        border: 1px solid #FCA5A5;
        color: #991B1B;
    }
    .rt-alert-close {
        background: none;
        border: none;
        color: inherit;
        font-size: 18px;
        font-weight: 800;
        cursor: pointer;
        padding: 0 4px;
        opacity: 0.75;
    }
    .rt-alert-close:hover {
        opacity: 1;
    }

    /* 4 KARTU RINGKASAN OPERASIONAL */
    .rt-grid-4 {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 16px;
    }
    .rt-kpi {
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
    .rt-kpi.clickable {
        cursor: pointer;
    }
    .rt-kpi:hover {
        border-color: #10B981;
        transform: translateY(-2px);
    }
    .rt-kpi-val {
        font-size: clamp(1.3rem, 1.8vw, 1.65rem);
        font-weight: 800;
        color: #0F172A;
        margin: 8px 0 3px;
        font-variant-numeric: tabular-nums;
    }

    /* BILAH FILTER & PENCARIAN */
    .rt-toolbar {
        background: #FFFFFF;
        border-radius: 16px;
        border: 1px solid #E2E8F0;
        padding: 15px 18px;
        box-shadow: 0 2px 10px rgba(15, 23, 42, 0.025);
    }
    .rt-pill {
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
    .rt-pill:hover {
        border-color: #10B981;
        color: #065F46;
    }
    .rt-pill.active {
        background: #064E3B;
        color: #FFFFFF;
        border-color: #064E3B;
    }
    .rt-search {
        padding: 9px 13px 9px 36px;
        border: 1px solid #CBD5E1;
        border-radius: 10px;
        font-family: inherit;
        font-size: 13px;
        color: #0F172A;
        width: 100%;
        box-sizing: border-box;
        background: #F8FAFC;
        transition: border-color 0.16s ease, background-color 0.16s ease, box-shadow 0.16s ease;
    }
    .rt-search:focus {
        outline: none;
        background: #FFFFFF;
        border-color: #10B981;
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.14);
    }

    /* KARTU ORDER & TITIK PICKUP */
    .rt-order-card {
        background: #FFFFFF;
        border-radius: 16px;
        border: 1px solid #E2E8F0;
        overflow: hidden;
        box-shadow: 0 3px 14px rgba(15, 23, 42, 0.03);
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
    .rt-inv-code {
        display: inline-block;
        padding: 3px 9px;
        border-radius: 7px;
        background: #ECFDF5;
        border: 1px solid #A7F3D0;
        color: #064E3B;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 14px;
        font-weight: 800;
    }
    .rt-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        border: none;
        text-decoration: none;
        padding: 10px 15px;
        border-radius: 10px;
        font-family: inherit;
        font-weight: 700;
        font-size: 12.5px;
        cursor: pointer;
        white-space: nowrap;
        transition: background-color 0.16s ease, transform 0.14s ease;
    }
    .rt-btn:active {
        transform: scale(0.98);
    }
    .rt-btn-emerald {
        background: #064E3B;
        color: #FFFFFF;
    }
    .rt-btn-emerald:hover {
        background: #042F24;
    }
    .rt-btn-dark {
        background: #0F172A;
        color: #FFFFFF;
    }
    .rt-btn-dark:hover {
        background: #1E293B;
    }

    .rt-pickup-grid {
        padding: 20px 22px;
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(330px, 1fr));
        gap: 16px;
    }
    .rt-pickup-card {
        border: 1px solid #CBD5E1;
        border-radius: 14px;
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
        font-family: inherit;
        font-size: 11.5px;
        font-weight: 800;
        cursor: pointer;
        outline: none;
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
        .rt-grid-4 {
            grid-template-columns: minmax(0, 1fr);
        }
        .rt-hero {
            padding: 20px;
        }
        .rt-hero-title {
            font-size: 19px;
        }
        .rt-hero-link {
            width: 100%;
            justify-content: center;
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
<div id="kopBackdrop" onclick="toggleKopSidebar()" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.5); z-index:45;"></div>

<div class="rt-wrap">

    <!-- BANNER HEADER KONSISTEN -->
    <section class="rt-hero">
        <div>
            <div class="rt-eyebrow">
                <span class="rt-eyebrow-dot"></span>
                <span>LOGISTICS &amp; MULTI-DISTRICT ORDER ROUTING</span>
            </div>
            <h2 class="rt-hero-title">Konsolidasi &amp; Surat Jalan Multi-Kecamatan</h2>
            <p class="rt-hero-sub">
                Kelompokkan pesanan Buyer lintas kecamatan menjadi instruksi penyiapan barang (Picking List) per Koperasi Primer dan terbitkan Surat Jalan resmi terpadu.
            </p>
        </div>

        <a href="../transaksi/index.php" class="rt-hero-link">
            <span>Buku Besar Transaksi</span>
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
        </a>
    </section>

    <!-- ALERT NOTIFIKASI -->
    <?php if (isset($_GET['msg']) && $_GET['msg'] !== ''): ?>
        <div class="rt-alert rt-alert-success">
            <span>✓ <?= htmlspecialchars($_GET['msg']) ?></span>
            <button type="button" class="rt-alert-close" onclick="this.parentElement.remove()" aria-label="Tutup">&times;</button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['err']) && $_GET['err'] !== ''): ?>
        <div class="rt-alert rt-alert-error">
            <span>! <?= htmlspecialchars($_GET['err']) ?></span>
            <button type="button" class="rt-alert-close" onclick="this.parentElement.remove()" aria-label="Tutup">&times;</button>
        </div>
    <?php endif; ?>

    <!-- 4 KARTU RINGKASAN OPERASIONAL LOGISTIK -->
    <section class="rt-grid-4">
        <div class="rt-kpi clickable" style="border-left:4px solid #059669;" onclick="quickSelectRoutingFilter('ALL')" title="Tampilkan semua pesanan">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                <span style="font-size:12px; color:#64748B; font-weight:700;">Pesanan Aktif</span>
                <div style="width:35px; height:35px; border-radius:10px; background:#DCFCE7; color:#059669; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                </div>
            </div>
            <div class="rt-kpi-val"><?= $total_orders_aktif ?> Invoice</div>
            <div style="font-size:11.5px; color:#64748B;">Order masuk untuk diproses</div>
        </div>

        <div class="rt-kpi clickable" style="border-left:4px solid #2563EB;" onclick="quickSelectRoutingFilter('HAS_SJ')" title="Filter pesanan yang sudah terbit Surat Jalan">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                <span style="font-size:12px; color:#64748B; font-weight:700;">Surat Jalan Terbit</span>
                <div style="width:35px; height:35px; border-radius:10px; background:#DBEAFE; color:#2563EB; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
            </div>
            <div class="rt-kpi-val" style="color:#1E40AF;"><?= $total_sj_terbit ?> Dokumen</div>
            <div style="font-size:11.5px; color:#64748B;">Picking list sudah disebar</div>
        </div>

        <div class="rt-kpi clickable" style="border-left:4px solid #D97706;" onclick="quickSelectRoutingFilter('NO_SJ')" title="Filter pesanan yang belum terbit Surat Jalan">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                <span style="font-size:12px; color:#64748B; font-weight:700;">Menunggu Routing</span>
                <div style="width:35px; height:35px; border-radius:10px; background:#FEF3C7; color:#D97706; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </div>
            </div>
            <div class="rt-kpi-val" style="color:#B45309;"><?= max(0, $total_orders_aktif - $total_sj_terbit) ?> Pesanan</div>
            <div style="font-size:11.5px; color:#64748B;">Belum diterbitkan surat jalan</div>
        </div>

        <div class="rt-kpi" style="border-left:4px solid #064E3B;">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                <span style="font-size:12px; color:#64748B; font-weight:700;">Titik Pick-Up</span>
                <div style="width:35px; height:35px; border-radius:10px; background:#ECFDF5; color:#064E3B; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                </div>
            </div>
            <div class="rt-kpi-val"><?= $total_titik_pickup ?> Titik</div>
            <div style="font-size:11.5px; color:#64748B;">Koperasi primer kecamatan</div>
        </div>
    </section>

    <?php if (empty($prepared_orders)): ?>
        <section style="background:#FFFFFF; border-radius:16px; border:1px solid #E2E8F0; padding:44px 24px; text-align:center; box-shadow:0 2px 10px rgba(15,23,42,0.03);">
            <div style="width:52px; height:52px; border-radius:14px; background:#F1F5F9; color:#64748B; display:flex; align-items:center; justify-content:center; margin:0 auto 14px;">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
            </div>
            <div style="font-size:17.5px; font-weight:800; color:#0F172A; margin-bottom:6px;">Belum Ada Pesanan Buyer untuk Dikonsolidasikan</div>
            <p style="font-size:13.5px; color:#64748B; max-width:520px; margin:0 auto 18px; line-height:1.55;">
                Ketika Buyer melakukan checkout produk dari beberapa kecamatan dalam satu keranjang, sistem otomatis mengelompokkan titik pengambilan (Picking List) di halaman ini.
            </p>
            <a href="../produk/index.php" class="rt-btn rt-btn-emerald">
                <span>Cek Ketersediaan Stok Etalase</span>
            </a>
        </section>
    <?php else: ?>

        <!-- BILAH FILTER & PENCARIAN LIVE (ZERO LAG) -->
        <section class="rt-toolbar" id="rtFilterSection">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                <div style="display:flex; gap:7px; flex-wrap:wrap;" id="rtFilterBar">
                    <button type="button" class="rt-pill active" data-filter="ALL" onclick="filterRoutingOrders('ALL', this)">Semua (<?= count($prepared_orders) ?>)</button>
                    <button type="button" class="rt-pill" data-filter="NO_SJ" onclick="filterRoutingOrders('NO_SJ', this)">Belum Terbit SJ</button>
                    <button type="button" class="rt-pill" data-filter="HAS_SJ" onclick="filterRoutingOrders('HAS_SJ', this)">Sudah Terbit SJ</button>
                    <button type="button" class="rt-pill" data-filter="PENDING_READY" onclick="filterRoutingOrders('PENDING_READY', this)">Perlu Penyiapan</button>
                </div>

                <div style="position:relative; min-width:240px; flex:1; max-width:340px;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#64748B" stroke-width="2.2" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); pointer-events:none;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="search" id="rtSearchInput" oninput="applyRoutingFilters()" placeholder="Cari invoice, buyer, kecamatan, produk..." class="rt-search">
                </div>
            </div>
        </section>

        <div style="display:flex; flex-direction:column; gap:20px;" id="rtOrdersContainer">
            <?php foreach ($prepared_orders as $o):
                $grouped_primer     = $o['grouped_primer'];
                $jumlah_titik_ambil = count($grouped_primer);
                $has_sj             = !empty($o['no_surat_jalan']);
                $all_ready          = ($o['total_items'] > 0 && $o['ready_items'] === $o['total_items']);
                $pct_ready          = $o['total_items'] > 0 ? round(($o['ready_items'] / $o['total_items']) * 100) : 0;
            ?>

            <article class="rt-order-card rt-order-item"
                     data-has-sj="<?= $has_sj ? '1' : '0' ?>"
                     data-all-ready="<?= $all_ready ? '1' : '0' ?>"
                     data-search="<?= htmlspecialchars($o['search_blob'], ENT_QUOTES) ?>">

                <div class="rt-order-head">
                    <div style="flex:1; min-width:240px;">
                        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                            <span class="rt-inv-code"><?= htmlspecialchars($o['no_invoice']) ?></span>

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

                        <div style="font-size:13px; color:#334155; margin-top:8px; line-height:1.45;">
                            Buyer: <strong><?= htmlspecialchars($o['nama_buyer']) ?></strong>
                            <span style="color:#64748B;">(<?= htmlspecialchars($o['hp_buyer'] ?: '-') ?>)</span>
                            • Tujuan: <span style="color:#0F172A; font-weight:600;"><?= htmlspecialchars($o['alamat_pengiriman']) ?></span>
                        </div>
                    </div>

                    <div class="rt-order-actions" style="display:flex; gap:8px; flex-wrap:wrap;">
                        <?php if ($role === 'konsumen'): ?>
                            <form method="POST" style="margin:0;">
                                <?= $csrf_input ?>
                                <input type="hidden" name="aksi" value="terbitkan_routing">
                                <input type="hidden" name="id_order" value="<?= (int)$o['id_order'] ?>">
                                <button type="submit" class="rt-btn rt-btn-emerald">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                                    <span><?= !$has_sj ? 'Terbitkan Picking List & Surat Jalan' : 'Sinkronkan Instruksi Primer' ?></span>
                                </button>
                            </form>
                        <?php endif; ?>

                        <a href="surat_jalan.php?id_order=<?= (int)$o['id_order'] ?>" target="_blank" class="rt-btn rt-btn-dark">
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
                                        <div style="font-size:11.5px; color:#475569; margin-top:2px; font-variant-numeric:tabular-nums;">
                                            Volume Ambil: <strong><?= number_format((int)$item['qty'], 0, ',', '.') ?> <?= htmlspecialchars($item['satuan']) ?></strong> • <?= rupiah($item['subtotal']) ?>
                                        </div>
                                    </div>

                                    <?php if ($role === 'primer' && $cur_st === 'Terkonsolidasi Pusat'): ?>
                                        <span style="background:#DCFCE7; color:#166534; border:1px solid #BBF7D0; padding:6px 11px; border-radius:9px; font-size:11.5px; font-weight:800;">
                                            Terkonsolidasi Pusat
                                        </span>
                                    <?php else: ?>
                                        <form method="POST" style="margin:0;">
                                            <?= $csrf_input ?>
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
            </article>
            <?php endforeach; ?>
        </div>

        <div id="rtEmptyFilter" style="display:none; background:#FFFFFF; border-radius:16px; border:1px solid #E2E8F0; padding:38px 24px; text-align:center;">
            <div style="font-size:16.5px; font-weight:800; color:#0F172A; margin-bottom:6px;">Pesanan Tidak Ditemukan</div>
            <p style="font-size:13px; color:#64748B; margin:0 0 14px;">Coba ubah kata kunci pencarian atau pilih filter status lainnya.</p>
            <button type="button" onclick="resetRoutingFilters()" class="rt-btn rt-btn-emerald">
                Tampilkan Semua Pesanan
            </button>
        </div>

    <?php endif; ?>

</div>

<script>
let activeRoutingFilter = 'ALL';

function filterRoutingOrders(mode, btn) {
    activeRoutingFilter = mode;
    document.querySelectorAll('#rtFilterBar .rt-pill').forEach(function(el) {
        el.classList.remove('active');
    });
    if (btn) btn.classList.add('active');
    applyRoutingFilters();
}

function quickSelectRoutingFilter(mode) {
    const btn = document.querySelector('#rtFilterBar .rt-pill[data-filter="' + mode + '"]');
    filterRoutingOrders(mode, btn);
    const sec = document.getElementById('rtFilterSection');
    if (sec) sec.scrollIntoView({ behavior: 'smooth' });
}

function applyRoutingFilters() {
    const input = document.getElementById('rtSearchInput');
    const q     = input ? input.value.toLowerCase().trim() : '';
    const cards = document.querySelectorAll('.rt-order-item');
    let visible = 0;

    cards.forEach(function(card) {
        const hasSj    = card.getAttribute('data-has-sj') === '1';
        const allReady = card.getAttribute('data-all-ready') === '1';
        const txt      = card.getAttribute('data-search') || '';

        let matchMode = true;
        if (activeRoutingFilter === 'NO_SJ')              matchMode = !hasSj;
        else if (activeRoutingFilter === 'HAS_SJ')        matchMode = hasSj;
        else if (activeRoutingFilter === 'PENDING_READY') matchMode = !allReady;

        const matchText = !q || txt.indexOf(q) !== -1;
        if (matchMode && matchText) {
            card.style.display = 'block';
            visible++;
        } else {
            card.style.display = 'none';
        }
    });

    const emptyBox = document.getElementById('rtEmptyFilter');
    if (emptyBox) {
        emptyBox.style.display = (cards.length > 0 && visible === 0) ? 'block' : 'none';
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