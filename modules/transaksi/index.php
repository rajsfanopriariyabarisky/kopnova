<?php
require_once '../../config/database.php';
cekAkses(['konsumen', 'primer', 'buyer']);

$role = $_SESSION['user']['role'] ?? '';
$user = $_SESSION['user'] ?? [];

function redirectTransaksi(string $type, string $text): void {
    header("Location: index.php?" . $type . "=" . urlencode($text));
    exit;
}

// PROSES: Update Status Pesanan (Khusus Koperasi Konsumen Pusat)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'update_status_order') {
    verify_csrf();
    cekAkses(['konsumen']);

    $id_order     = (int)($_POST['id_order'] ?? 0);
    $status_order = trim($_POST['status_order'] ?? '');
    $valid_status = ['Menunggu Pembayaran', 'Diproses Koperasi', 'Dikirim', 'Selesai', 'Dibatalkan'];

    if ($id_order <= 0 || !in_array($status_order, $valid_status, true)) {
        redirectTransaksi('err', 'Status transaksi tidak valid.');
    }

    try {
        $stmtUpd = $pdo->prepare("UPDATE orders SET status_order = ? WHERE id_order = ?");
        $stmtUpd->execute([$status_order, $id_order]);

        if ($stmtUpd->rowCount() > 0) {
            redirectTransaksi('msg', "Status transaksi berhasil diperbarui menjadi: {$status_order}");
        } else {
            redirectTransaksi('err', 'Data transaksi tidak ditemukan atau status tidak berubah.');
        }
    } catch (PDOException $e) {
        redirectTransaksi('err', 'Gagal memperbarui status transaksi. Silakan coba kembali.');
    }
}

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

$id_user_login   = (int)($user['id_user'] ?? 0);
$id_primer_login = (int)($user['id_primer'] ?? 0);

// Query Transaksi berdasarkan Role (Fail-Closed)
if ($role === 'buyer') {
    $stmt = $pdo->prepare("
        SELECT o.*, u.nama_lengkap AS nama_buyer, u.no_hp AS hp_buyer
        FROM orders o
        JOIN users u ON o.id_buyer = u.id_user
        WHERE o.id_buyer = ?
        ORDER BY o.id_order DESC
    ");
    $stmt->execute([$id_user_login]);
    $orders = $stmt->fetchAll();
} elseif ($role === 'primer') {
    $stmt = $pdo->prepare("
        SELECT o.*, u.nama_lengkap AS nama_buyer, u.no_hp AS hp_buyer
        FROM orders o
        JOIN users u ON o.id_buyer = u.id_user
        WHERE EXISTS (
            SELECT 1
            FROM order_items oi
            JOIN produk_etalase pr ON oi.id_produk = pr.id_produk
            WHERE oi.id_order = o.id_order AND pr.id_primer = ?
        )
        ORDER BY o.id_order DESC
    ");
    $stmt->execute([$id_primer_login]);
    $orders = $stmt->fetchAll();
} else {
    $orders = $pdo->query("
        SELECT o.*, u.nama_lengkap AS nama_buyer, u.no_hp AS hp_buyer
        FROM orders o
        JOIN users u ON o.id_buyer = u.id_user
        ORDER BY o.id_order DESC
    ")->fetchAll();
}

// Ambil seluruh rincian item pesanan sekaligus (Eliminasi N+1 Query)
$items_by_order = [];
if (!empty($orders)) {
    $order_ids    = array_map(fn($row) => (int)$row['id_order'], $orders);
    $placeholders = implode(',', array_fill(0, count($order_ids), '?'));

    $sqlItems = "
        SELECT oi.*, pr.nama_produk, pr.kode_produk, pr.satuan,
               kp.id_primer, kp.nama_koperasi, kp.kecamatan
        FROM order_items oi
        JOIN produk_etalase pr ON oi.id_produk = pr.id_produk
        JOIN koperasi_primer kp ON pr.id_primer = kp.id_primer
        WHERE oi.id_order IN ({$placeholders})
    ";
    $paramsItems = $order_ids;

    if ($role === 'primer') {
        $sqlItems .= " AND kp.id_primer = ? ";
        $paramsItems[] = $id_primer_login;
    }

    $sqlItems .= " ORDER BY oi.id_order DESC, oi.id_item ASC ";

    $stmtItems = $pdo->prepare($sqlItems);
    $stmtItems->execute($paramsItems);

    foreach ($stmtItems->fetchAll() as $it) {
        $oid = (int)$it['id_order'];
        $items_by_order[$oid][] = $it;
    }
}

// Hitung metrik ringkasan & siapkan metadata pencarian
$total_transaksi = count($orders);
$total_nominal   = 0;
$count_proses    = 0;
$count_selesai   = 0;
$count_batal     = 0;

$prepared_orders = [];

foreach ($orders as $o) {
    $oid        = (int)$o['id_order'];
    $orderItems = $items_by_order[$oid] ?? [];
    $st_raw     = trim((string)($o['status_order'] ?? 'Diproses Koperasi'));
    $st_lower   = strtolower($st_raw);

    // Klasifikasi bucket filter status
    if (str_contains($st_lower, 'selesai') || str_contains($st_lower, 'lunas')) {
        $bucket = 'SELESAI';
        $count_selesai++;
    } elseif (str_contains($st_lower, 'batal')) {
        $bucket = 'BATAL';
        $count_batal++;
    } else {
        $bucket = 'PROSES';
        $count_proses++;
    }

    // Nominal relevan (untuk primer hitung porsi unitnya, untuk konsumen/buyer hitung total_bayar)
    $unit_subtotal = 0;
    $item_names    = [];
    $modal_items   = [];

    foreach ($orderItems as $it) {
        $unit_subtotal += (float)$it['subtotal'];
        $item_names[]   = $it['nama_produk'] ?? '';
        $modal_items[]  = [
            'nama_produk'    => $it['nama_produk'] ?? '-',
            'kode_produk'    => $it['kode_produk'] ?? '-',
            'qty'            => (int)($it['qty'] ?? 0),
            'satuan'         => $it['satuan'] ?? '',
            'harga_satuan'   => rupiah((float)($it['harga_satuan'] ?? 0)),
            'subtotal'       => rupiah((float)($it['subtotal'] ?? 0)),
            'nama_koperasi'  => $it['nama_koperasi'] ?? '-',
            'kecamatan'      => $it['kecamatan'] ?? '-',
            'status_routing' => $it['status_routing'] ?? 'Menunggu Instruksi'
        ];
    }

    $nominal_row = ($role === 'primer') ? $unit_subtotal : (float)($o['total_bayar'] ?? 0);
    if ($bucket !== 'BATAL') {
        $total_nominal += $nominal_row;
    }

    $search_blob = strtolower(implode(' ', [
        $o['no_invoice'] ?? '',
        $o['no_surat_jalan'] ?? '',
        $o['nama_buyer'] ?? '',
        $o['hp_buyer'] ?? '',
        $o['metode_bayar'] ?? '',
        $o['alamat_pengiriman'] ?? '',
        $st_raw,
        implode(' ', $item_names)
    ]));

    $o['bucket']        = $bucket;
    $o['unit_subtotal'] = $unit_subtotal;
    $o['item_count']    = count($orderItems);
    $o['modal_items']   = $modal_items;
    $o['search_blob']   = $search_blob;
    $prepared_orders[]  = $o;
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

    .trx-wrap {
        display: flex;
        flex-direction: column;
        gap: 22px;
        color: #0F172A;
    }

    /* HERO BANNER KONSISTEN DENGAN MODUL KOPNOVA */
    .trx-hero {
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
    .trx-eyebrow {
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
    .trx-eyebrow-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: #FBBF24;
    }
    .trx-hero-title {
        margin: 0 0 6px;
        font-size: 22px;
        font-weight: 800;
        letter-spacing: -0.3px;
        line-height: 1.25;
    }
    .trx-hero-sub {
        margin: 0;
        font-size: 13.5px;
        color: rgba(236, 253, 245, 0.88);
        max-width: 630px;
        line-height: 1.5;
    }
    .trx-hero-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
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
    .trx-hero-btn:hover {
        background: #FBBF24;
    }
    .trx-hero-btn:active {
        transform: scale(0.98);
    }

    /* ALERT NOTIFIKASI */
    .trx-alert {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 13px 16px;
        border-radius: 12px;
        font-size: 13.5px;
        font-weight: 600;
    }
    .trx-alert-success {
        background: #DCFCE7;
        border: 1px solid #86EFAC;
        color: #166534;
    }
    .trx-alert-error {
        background: #FEE2E2;
        border: 1px solid #FCA5A5;
        color: #991B1B;
    }
    .trx-alert-close {
        background: none;
        border: none;
        color: inherit;
        font-size: 18px;
        font-weight: 800;
        cursor: pointer;
        padding: 0 4px;
        opacity: 0.75;
    }
    .trx-alert-close:hover {
        opacity: 1;
    }

    /* 4 KARTU KPI RINGKASAN */
    .trx-grid-4 {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 16px;
    }
    .trx-kpi {
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
    .trx-kpi.clickable {
        cursor: pointer;
    }
    .trx-kpi:hover {
        border-color: #10B981;
        transform: translateY(-2px);
    }
    .trx-kpi-val {
        font-size: clamp(1.25rem, 1.75vw, 1.6rem);
        font-weight: 800;
        color: #0F172A;
        margin: 8px 0 3px;
        font-variant-numeric: tabular-nums;
    }

    /* CARD UTAMA & TOOLBAR */
    .trx-card {
        background: #FFFFFF;
        border-radius: 16px;
        border: 1px solid #E2E8F0;
        padding: 22px;
        box-shadow: 0 2px 10px rgba(15, 23, 42, 0.03);
    }
    .trx-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 16px;
        padding-bottom: 13px;
        border-bottom: 1px solid #F1F5F9;
    }
    .trx-pill {
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
    .trx-pill:hover {
        border-color: #10B981;
        color: #065F46;
    }
    .trx-pill.active {
        background: #064E3B;
        color: #FFFFFF;
        border-color: #064E3B;
    }
    .trx-search {
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
    .trx-search:focus {
        outline: none;
        border-color: #10B981;
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.14);
    }

    /* TABEL DESKTOP */
    .trx-table-wrap {
        width: 100%;
        overflow-x: auto;
        border: 1px solid #E2E8F0;
        border-radius: 12px;
    }
    .trx-responsive-table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
        font-size: 13.5px;
    }
    .trx-responsive-table thead tr {
        background: #F8FAFC;
        border-bottom: 1.5px solid #E2E8F0;
    }
    .trx-responsive-table th {
        padding: 12px 14px;
        font-size: 11.5px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #475569;
        white-space: nowrap;
    }
    .trx-responsive-table td {
        padding: 13px 14px;
        border-bottom: 1px solid #F1F5F9;
        color: #1E293B;
        vertical-align: middle;
    }
    .trx-responsive-table tbody tr:last-child td {
        border-bottom: none;
    }
    .trx-row-hover:hover {
        background-color: #F8FAFC;
    }

    /* BADGES, SELECT & BUTTONS */
    .trx-inv {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 7px;
        background: #ECFDF5;
        border: 1px solid #A7F3D0;
        color: #064E3B;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 12px;
        font-weight: 800;
    }
    .trx-badge {
        display: inline-block;
        padding: 4px 11px;
        border-radius: 99px;
        font-size: 11.5px;
        font-weight: 800;
        white-space: nowrap;
    }
    .trx-status-select {
        padding: 6px 10px;
        border-radius: 9px;
        font-family: inherit;
        font-size: 11.5px;
        font-weight: 800;
        cursor: pointer;
        outline: none;
    }
    .trx-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        border: none;
        border-radius: 8px;
        font-family: inherit;
        font-size: 12px;
        font-weight: 700;
        padding: 7px 11px;
        text-decoration: none;
        cursor: pointer;
        white-space: nowrap;
        transition: background-color 0.15s ease, transform 0.14s ease;
    }
    .trx-btn:active {
        transform: scale(0.98);
    }
    .trx-btn-light {
        background: #F1F5F9;
        color: #0F172A;
        border: 1px solid #CBD5E1;
    }
    .trx-btn-light:hover {
        background: #E2E8F0;
    }
    .trx-btn-emerald {
        background: #064E3B;
        color: #FFFFFF;
    }
    .trx-btn-emerald:hover {
        background: #042F24;
    }

    /* MODAL RINCIAN ITEM (ZERO LAG) */
    .trx-modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.58);
        z-index: 999;
        align-items: center;
        justify-content: center;
        padding: 16px;
    }
    .trx-modal-overlay.show {
        display: flex;
    }
    .trx-modal-box {
        background: #FFFFFF;
        border: 1px solid #E2E8F0;
        border-radius: 18px;
        width: 100%;
        max-width: 620px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 22px 46px rgba(15, 23, 42, 0.24);
    }
    .trx-modal-head {
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

    @media (max-width: 1120px) {
        .trx-grid-4 {
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
        .trx-table-wrap {
            border: none;
            border-radius: 0;
            overflow: visible;
        }
        .trx-responsive-table thead {
            display: none;
        }
        .trx-responsive-table,
        .trx-responsive-table tbody,
        .trx-responsive-table tr,
        .trx-responsive-table td {
            display: block;
            width: 100%;
            box-sizing: border-box;
        }
        .trx-responsive-table tr.trx-mobile-card {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 14px;
            padding: 14px;
            margin-bottom: 12px;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03);
        }
        .trx-responsive-table td {
            padding: 5px 0 !important;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            border: none !important;
            text-align: right;
            max-width: 100% !important;
        }
        .trx-responsive-table td::before {
            content: attr(data-label);
            font-size: 11.5px;
            font-weight: 700;
            color: #64748B;
            text-align: left;
            flex-shrink: 0;
        }
        .trx-responsive-table td.td-card-header {
            border-bottom: 1px dashed #E2E8F0 !important;
            padding-bottom: 9px !important;
            margin-bottom: 4px;
            justify-content: space-between;
            text-align: left;
        }
        .trx-responsive-table td.td-card-header::before {
            display: none;
        }
        .trx-responsive-table td.td-card-action {
            border-top: 1px solid #F1F5F9 !important;
            padding-top: 10px !important;
            margin-top: 6px;
            flex-direction: column;
            align-items: stretch;
        }
        .trx-responsive-table td.td-card-action::before {
            margin-bottom: 5px;
        }
        .trx-responsive-table td.td-card-action .trx-action-group {
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 7px;
        }
        .trx-responsive-table td.td-card-action a,
        .trx-responsive-table td.td-card-action button {
            width: 100%;
            padding: 9px 12px;
            font-size: 13px;
        }
    }

    @media (max-width: 640px) {
        .trx-grid-4 {
            grid-template-columns: minmax(0, 1fr);
        }
        .trx-hero {
            padding: 20px;
        }
        .trx-hero-title {
            font-size: 19px;
        }
        .trx-hero-btn {
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

<div class="trx-wrap">

    <!-- BANNER HEADER KONSISTEN -->
    <section class="trx-hero">
        <div>
            <div class="trx-eyebrow">
                <span class="trx-eyebrow-dot"></span>
                <span><?= $role === 'buyer' ? 'RIWAYAT BELANJA & INVOICE RESMI' : 'BUKU BESAR & MONITORING TRANSAKSI' ?></span>
            </div>
            <h2 class="trx-hero-title">
                <?= $role === 'buyer' ? 'Riwayat Pesanan Saya' : ($role === 'primer' ? 'Monitoring Transaksi Unit Koperasi Primer' : 'Pusat Monitoring Transaksi Masuk') ?>
            </h2>
            <p class="trx-hero-sub">
                <?= $role === 'buyer'
                    ? 'Pantau status pembayaran, proses penyiapan komoditas dari koperasi kecamatan, dan unduh invoice resmi pesanan Anda.'
                    : 'Rekap seluruh transaksi pembelian yang terhubung ke rantai pasok Koperasi Konsumen Pusat beserta status pemenuhannya.' ?>
            </p>
        </div>

        <?php if (in_array($role, ['konsumen', 'primer'], true)): ?>
            <a href="../order_routing/index.php" class="trx-hero-btn">
                <span>Konsolidasi &amp; Surat Jalan</span>
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </a>
        <?php else: ?>
            <a href="../produk/index.php" class="trx-hero-btn">
                <span>Belanja Produk Koperasi</span>
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </a>
        <?php endif; ?>
    </section>

    <!-- ALERT NOTIFIKASI -->
    <?php if (isset($_GET['msg']) && $_GET['msg'] !== ''): ?>
        <div class="trx-alert trx-alert-success">
            <span>✓ <?= htmlspecialchars($_GET['msg']) ?></span>
            <button type="button" class="trx-alert-close" onclick="this.parentElement.remove()" aria-label="Tutup">&times;</button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['err']) && $_GET['err'] !== ''): ?>
        <div class="trx-alert trx-alert-error">
            <span>! <?= htmlspecialchars($_GET['err']) ?></span>
            <button type="button" class="trx-alert-close" onclick="this.parentElement.remove()" aria-label="Tutup">&times;</button>
        </div>
    <?php endif; ?>

    <!-- 4 KARTU RINGKASAN TRANSAKSI -->
    <section class="trx-grid-4">
        <div class="trx-kpi clickable" style="border-left:4px solid #059669;" onclick="quickSelectTrxFilter('ALL')" title="Tampilkan semua transaksi">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                <span style="font-size:12px; color:#64748B; font-weight:700;">Total Transaksi</span>
                <div style="width:35px; height:35px; border-radius:10px; background:#DCFCE7; color:#059669; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                </div>
            </div>
            <div class="trx-kpi-val"><?= $total_transaksi ?> Invoice</div>
            <div style="font-size:11.5px; color:#64748B;">Tercatat pada buku besar</div>
        </div>

        <div class="trx-kpi" style="border-left:4px solid #064E3B;">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                <span style="font-size:12px; color:#64748B; font-weight:700;">
                    <?= $role === 'buyer' ? 'Total Belanja Aktif' : ($role === 'primer' ? 'Nilai Pasokan Unit' : 'Total Nilai Transaksi') ?>
                </span>
                <div style="width:35px; height:35px; border-radius:10px; background:#ECFDF5; color:#064E3B; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                </div>
            </div>
            <div class="trx-kpi-val" style="color:#065F46;"><?= rupiah($total_nominal) ?></div>
            <div style="font-size:11.5px; color:#64748B;">Di luar transaksi dibatalkan</div>
        </div>

        <div class="trx-kpi clickable" style="border-left:4px solid #D97706;" onclick="quickSelectTrxFilter('PROSES')" title="Filter transaksi dalam proses">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                <span style="font-size:12px; color:#64748B; font-weight:700;">Dalam Proses</span>
                <div style="width:35px; height:35px; border-radius:10px; background:#FEF3C7; color:#D97706; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </div>
            </div>
            <div class="trx-kpi-val" style="color:#B45309;"><?= $count_proses ?> Pesanan</div>
            <div style="font-size:11.5px; color:#64748B;">Menunggu / diproses / dikirim</div>
        </div>

        <div class="trx-kpi clickable" style="border-left:4px solid #2563EB;" onclick="quickSelectTrxFilter('SELESAI')" title="Filter transaksi selesai">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                <span style="font-size:12px; color:#64748B; font-weight:700;">Transaksi Selesai</span>
                <div style="width:35px; height:35px; border-radius:10px; background:#DBEAFE; color:#2563EB; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
            </div>
            <div class="trx-kpi-val" style="color:#1E40AF;"><?= $count_selesai ?> Pesanan</div>
            <div style="font-size:11.5px; color:#64748B;">Diterima &amp; terverifikasi</div>
        </div>
    </section>

    <!-- TABEL MONITORING TRANSAKSI -->
    <section class="trx-card" id="trxTableSection">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:10px; margin-bottom:14px;">
            <div>
                <h3 style="margin:0; font-size:16.5px; font-weight:800; color:#0F172A;">Daftar Transaksi &amp; Dokumen Invoice</h3>
                <div style="font-size:12.5px; color:#64748B; margin-top:2px;">Klik tombol <strong>Rincian</strong> untuk melihat komoditas tiap transaksi atau cetak dokumen Invoice &amp; Surat Jalan.</div>
            </div>
        </div>

        <!-- FILTER PILLS & PENCARIAN LIVE -->
        <div class="trx-toolbar">
            <div style="display:flex; gap:6px; flex-wrap:wrap;" id="trxFilterBar">
                <button type="button" class="trx-pill active" data-filter="ALL" onclick="filterTransaksi('ALL', this)">Semua (<?= $total_transaksi ?>)</button>
                <button type="button" class="trx-pill" data-filter="PROSES" onclick="filterTransaksi('PROSES', this)">Dalam Proses (<?= $count_proses ?>)</button>
                <button type="button" class="trx-pill" data-filter="SELESAI" onclick="filterTransaksi('SELESAI', this)">Selesai (<?= $count_selesai ?>)</button>
                <?php if ($count_batal > 0): ?>
                    <button type="button" class="trx-pill" data-filter="BATAL" onclick="filterTransaksi('BATAL', this)">Dibatalkan (<?= $count_batal ?>)</button>
                <?php endif; ?>
            </div>

            <div style="position:relative; min-width:230px; flex:1; max-width:340px;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#64748B" stroke-width="2.2" style="position:absolute; left:11px; top:50%; transform:translateY(-50%); pointer-events:none;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="search" id="trxSearchInput" oninput="applyTrxFilters()" placeholder="Cari invoice, buyer, produk, tujuan..." class="trx-search">
            </div>
        </div>

        <div class="trx-table-wrap">
            <table class="trx-responsive-table">
                <thead>
                    <tr>
                        <th>No. Invoice &amp; Tanggal</th>
                        <?php if ($role !== 'buyer'): ?>
                            <th>Buyer</th>
                        <?php endif; ?>
                        <th><?= $role === 'primer' ? 'Nilai Unit / Total' : 'Total Pembayaran' ?></th>
                        <th>Metode &amp; Tujuan</th>
                        <th>Status</th>
                        <th style="text-align:right;">Dokumen &amp; Rincian</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($prepared_orders)): ?>
                        <tr>
                            <td colspan="<?= $role !== 'buyer' ? 6 : 5 ?>" style="padding:28px; text-align:center; color:#64748B;">
                                Belum ada data transaksi yang tercatat.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($prepared_orders as $o):
                            $st_val   = (string)($o['status_order'] ?? 'Diproses Koperasi');
                            $bucket   = $o['bucket'];

                            $badge_bg = '#FEF3C7'; $badge_col = '#92400E'; $badge_bdr = '#FDE68A';
                            if ($bucket === 'SELESAI') {
                                $badge_bg = '#DCFCE7'; $badge_col = '#166534'; $badge_bdr = '#BBF7D0';
                            } elseif ($bucket === 'BATAL') {
                                $badge_bg = '#FEE2E2'; $badge_col = '#991B1B'; $badge_bdr = '#FECACA';
                            } elseif (str_contains(strtolower($st_val), 'kirim')) {
                                $badge_bg = '#DBEAFE'; $badge_col = '#1E40AF'; $badge_bdr = '#BFDBFE';
                            }

                            $detailPayload = json_encode([
                                'no_invoice'     => $o['no_invoice'] ?? '-',
                                'no_surat_jalan' => !empty($o['no_surat_jalan']) ? $o['no_surat_jalan'] : 'Belum Terbit',
                                'tanggal'        => date('d M Y, H:i', strtotime($o['tanggal_order'])),
                                'nama_buyer'     => $o['nama_buyer'] ?? '-',
                                'hp_buyer'       => $o['hp_buyer'] ?: '-',
                                'alamat'         => $o['alamat_pengiriman'] ?? '-',
                                'metode'         => $o['metode_bayar'] ?? '-',
                                'status'         => $st_val,
                                'total_bayar'    => rupiah((float)($o['total_bayar'] ?? 0)),
                                'items'          => $o['modal_items']
                            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                        ?>
                        <tr class="trx-row-hover trx-mobile-card trx-data-row"
                            data-bucket="<?= $bucket ?>"
                            data-search="<?= htmlspecialchars($o['search_blob'], ENT_QUOTES) ?>">
                            <td class="td-card-header">
                                <div>
                                    <span class="trx-inv"><?= htmlspecialchars($o['no_invoice']) ?></span>
                                    <div style="font-size:11.5px; color:#64748B; margin-top:4px;">
                                        <?= date('d M Y, H:i', strtotime($o['tanggal_order'])) ?> • <?= $o['item_count'] ?> Item
                                    </div>
                                </div>
                            </td>

                            <?php if ($role !== 'buyer'): ?>
                            <td data-label="Nama Buyer">
                                <div>
                                    <div style="font-weight:700; color:#0F172A;"><?= htmlspecialchars($o['nama_buyer']) ?></div>
                                    <div style="font-size:11.5px; color:#64748B;"><?= htmlspecialchars($o['hp_buyer'] ?: '-') ?></div>
                                </div>
                            </td>
                            <?php endif; ?>

                            <td data-label="<?= $role === 'primer' ? 'Nilai Unit' : 'Total Bayar' ?>">
                                <?php if ($role === 'primer'): ?>
                                    <div>
                                        <div style="font-weight:800; color:#065F46; font-variant-numeric:tabular-nums;"><?= rupiah($o['unit_subtotal']) ?></div>
                                        <div style="font-size:11px; color:#64748B; font-variant-numeric:tabular-nums;">Total Inv: <?= rupiah((float)$o['total_bayar']) ?></div>
                                    </div>
                                <?php else: ?>
                                    <span style="font-weight:800; color:#0F172A; font-variant-numeric:tabular-nums;"><?= rupiah((float)$o['total_bayar']) ?></span>
                                <?php endif; ?>
                            </td>

                            <td data-label="Metode & Tujuan" style="max-width:250px;">
                                <div>
                                    <div style="font-size:12px; font-weight:700; color:#334155;"><?= htmlspecialchars($o['metode_bayar']) ?></div>
                                    <div style="font-size:12px; color:#64748B; margin-top:2px; line-height:1.4;"><?= htmlspecialchars($o['alamat_pengiriman']) ?></div>
                                </div>
                            </td>

                            <td data-label="Status">
                                <?php if ($role === 'konsumen'): ?>
                                    <form method="POST" style="margin:0;">
                                        <?= $csrf_input ?>
                                        <input type="hidden" name="aksi" value="update_status_order">
                                        <input type="hidden" name="id_order" value="<?= (int)$o['id_order'] ?>">
                                        <select name="status_order" onchange="this.form.submit()" class="trx-status-select" style="background:<?= $badge_bg ?>; color:<?= $badge_col ?>; border:1px solid <?= $badge_bdr ?>;">
                                            <?php
                                            $status_options = ['Menunggu Pembayaran', 'Diproses Koperasi', 'Dikirim', 'Selesai', 'Dibatalkan'];
                                            if (!in_array($st_val, $status_options, true)) {
                                                array_unshift($status_options, $st_val);
                                            }
                                            foreach ($status_options as $opt):
                                            ?>
                                                <option value="<?= htmlspecialchars($opt) ?>" <?= $st_val === $opt ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($opt) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                <?php else: ?>
                                    <span class="trx-badge" style="background:<?= $badge_bg ?>; color:<?= $badge_col ?>; border:1px solid <?= $badge_bdr ?>;">
                                        <?= htmlspecialchars($st_val) ?>
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td data-label="Aksi" class="td-card-action" style="text-align:right;">
                                <div class="trx-action-group" style="display:inline-flex; gap:6px; flex-wrap:wrap; justify-content:flex-end;">
                                    <button type="button" onclick='openTrxDetailModal(<?= $detailPayload ?>)' class="trx-btn trx-btn-light">
                                        Rincian
                                    </button>
                                    <a href="../order_routing/surat_jalan.php?id_order=<?= (int)$o['id_order'] ?>" target="_blank" class="trx-btn trx-btn-emerald">
                                        Invoice / SJ
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <tr id="trxEmptyFilterRow" style="display:none;">
                            <td colspan="<?= $role !== 'buyer' ? 6 : 5 ?>" style="padding:26px; text-align:center; color:#64748B;">
                                Tidak ada transaksi yang cocok dengan filter status atau kata kunci pencarian.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

</div>

<!-- MODAL RINCIAN ITEM TRANSAKSI (ZERO LAG) -->
<div id="modalTrxDetail" class="trx-modal-overlay" onclick="if(event.target===this) closeTrxDetailModal()">
    <div class="trx-modal-box">
        <div class="trx-modal-head">
            <div>
                <div style="font-weight:800; font-size:15.5px;" id="md_trx_inv">Rincian Transaksi</div>
                <div style="font-size:11.5px; color:#A7F3D0; margin-top:2px;" id="md_trx_meta">Detail produk &amp; titik pasokan</div>
            </div>
            <button type="button" onclick="closeTrxDetailModal()" style="background:rgba(255,255,255,0.12); border:none; width:30px; height:30px; border-radius:8px; font-size:18px; cursor:pointer; color:#FFFFFF;">&times;</button>
        </div>

        <div style="padding:20px 22px 22px;">
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(210px, 1fr)); gap:12px; background:#F8FAFC; border:1px solid #E2E8F0; border-radius:12px; padding:14px 16px; margin-bottom:16px; font-size:12.5px;">
                <div>
                    <div style="color:#64748B; font-weight:700; font-size:11px; text-transform:uppercase;">Penerima &amp; Tujuan</div>
                    <div style="font-weight:800; color:#0F172A; margin-top:3px;" id="md_trx_buyer">-</div>
                    <div style="color:#475569; margin-top:2px; line-height:1.4;" id="md_trx_alamat">-</div>
                </div>
                <div>
                    <div style="color:#64748B; font-weight:700; font-size:11px; text-transform:uppercase;">Logistik &amp; Pembayaran</div>
                    <div style="color:#0F172A; margin-top:3px;">No. SJ: <strong id="md_trx_sj">-</strong></div>
                    <div style="color:#0F172A; margin-top:2px;">Metode: <strong id="md_trx_metode">-</strong></div>
                    <div style="color:#065F46; margin-top:2px;">Total Invoice: <strong id="md_trx_total">-</strong></div>
                </div>
            </div>

            <div style="font-size:12.5px; font-weight:800; color:#0F172A; margin-bottom:8px;">Daftar Komoditas dalam Pesanan:</div>
            <div id="md_trx_items" style="display:flex; flex-direction:column; gap:8px;"></div>

            <div style="display:flex; justify-content:flex-end; margin-top:18px;">
                <button type="button" onclick="closeTrxDetailModal()" class="trx-btn trx-btn-emerald" style="padding:9px 18px;">
                    Tutup Rincian
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let currentTrxBucket = 'ALL';

function filterTransaksi(bucket, btn) {
    currentTrxBucket = bucket;
    document.querySelectorAll('#trxFilterBar .trx-pill').forEach(function(el) {
        el.classList.remove('active');
    });
    if (btn) btn.classList.add('active');
    applyTrxFilters();
}

function quickSelectTrxFilter(bucket) {
    const btn = document.querySelector('#trxFilterBar .trx-pill[data-filter="' + bucket + '"]');
    filterTransaksi(bucket, btn);
    const sec = document.getElementById('trxTableSection');
    if (sec) sec.scrollIntoView({ behavior: 'smooth' });
}

function applyTrxFilters() {
    const q = (document.getElementById('trxSearchInput')?.value || '').toLowerCase().trim();
    const rows = document.querySelectorAll('.trx-data-row');
    const emptyRow = document.getElementById('trxEmptyFilterRow');
    let visibleCount = 0;

    rows.forEach(function(row) {
        const bkt = row.getAttribute('data-bucket');
        const txt = row.getAttribute('data-search') || '';
        const matchBkt  = (currentTrxBucket === 'ALL' || bkt === currentTrxBucket);
        const matchText = !q || txt.indexOf(q) !== -1;
        const show = matchBkt && matchText;
        row.style.display = show ? '' : 'none';
        if (show) visibleCount++;
    });

    if (emptyRow) {
        emptyRow.style.display = (rows.length > 0 && visibleCount === 0) ? '' : 'none';
    }
}

function escapeHtml(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function openTrxDetailModal(data) {
    const modal = document.getElementById('modalTrxDetail');
    if (!modal || !data) return;

    document.getElementById('md_trx_inv').textContent    = 'Invoice ' + data.no_invoice;
    document.getElementById('md_trx_meta').textContent   = data.tanggal + ' • Status: ' + data.status;
    document.getElementById('md_trx_buyer').textContent  = data.nama_buyer + ' (' + data.hp_buyer + ')';
    document.getElementById('md_trx_alamat').textContent = data.alamat;
    document.getElementById('md_trx_sj').textContent     = data.no_surat_jalan;
    document.getElementById('md_trx_metode').textContent = data.metode;
    document.getElementById('md_trx_total').textContent  = data.total_bayar;

    const listEl = document.getElementById('md_trx_items');
    if (!data.items || data.items.length === 0) {
        listEl.innerHTML = '<div style="padding:14px; text-align:center; color:#64748B; border:1px dashed #CBD5E1; border-radius:10px; font-size:12.5px;">Tidak ada rincian item untuk transaksi ini.</div>';
    } else {
        listEl.innerHTML = data.items.map(function(it) {
            return '<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; padding:11px 13px; border:1px solid #E2E8F0; border-radius:11px; background:#FFFFFF;">' +
                '<div>' +
                    '<div style="font-weight:800; font-size:13px; color:#0F172A;">' + escapeHtml(it.nama_produk) + ' <span style="font-weight:600; color:#64748B; font-size:11.5px;">(' + escapeHtml(it.kode_produk) + ')</span></div>' +
                    '<div style="font-size:11.5px; color:#64748B; margin-top:2px;">Asal: ' + escapeHtml(it.nama_koperasi) + ' • Kec. ' + escapeHtml(it.kecamatan) + '</div>' +
                '</div>' +
                '<div style="text-align:right;">' +
                    '<div style="font-weight:800; font-size:13px; color:#065F46; font-variant-numeric:tabular-nums;">' + escapeHtml(it.subtotal) + '</div>' +
                    '<div style="font-size:11px; color:#475569; margin-top:2px;">' + it.qty + ' ' + escapeHtml(it.satuan) + ' • ' + escapeHtml(it.status_routing) + '</div>' +
                '</div>' +
            '</div>';
        }).join('');
    }

    modal.classList.add('show');
}

function closeTrxDetailModal() {
    const modal = document.getElementById('modalTrxDetail');
    if (modal) modal.classList.remove('show');
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeTrxDetailModal();
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