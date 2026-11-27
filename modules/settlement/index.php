<?php
require_once '../../config/database.php';
cekAkses(['konsumen', 'primer', 'petani']);

$role = $_SESSION['user']['role'] ?? '';
$user = $_SESSION['user'] ?? [];

function redirectSettlement(string $type, string $text): void {
    header("Location: index.php?" . $type . "=" . urlencode($text));
    exit;
}

// 1. SINKRONISASI OTOMATIS TANPA N+1 QUERY
try {
    // Muat aturan rasio SHU aktif per kategori sekaligus
    $reg_map  = [];
    $stmtRegs = $pdo->query("
        SELECT kategori, persen_petani, persen_primer
        FROM harga_acuan_shu
        WHERE status_regulasi = 'Aktif'
        ORDER BY id_regulasi DESC
    ");
    foreach ($stmtRegs->fetchAll() as $r) {
        if (!isset($reg_map[$r['kategori']])) {
            $reg_map[$r['kategori']] = [
                'petani' => (float)$r['persen_petani'],
                'primer' => (float)$r['persen_primer'],
            ];
        }
    }

    // Muat pasangan (id_order, id_primer) yang sudah tercatat di settlement_dana
    $existing_pairs = [];
    $stmtEx = $pdo->query("SELECT id_order, id_primer FROM settlement_dana");
    foreach ($stmtEx->fetchAll() as $ex) {
        $existing_pairs[(int)$ex['id_order'] . '_' . (int)$ex['id_primer']] = true;
    }

    // Agregasi pesanan berstatus 'Selesai' per (id_order, id_primer, kategori)
    $completed_orders = $pdo->query("
        SELECT o.id_order, pr.id_primer, pr.kategori, SUM(oi.subtotal) AS bruto
        FROM orders o
        JOIN order_items oi ON o.id_order = oi.id_order
        JOIN produk_etalase pr ON oi.id_produk = pr.id_produk
        WHERE o.status_order = 'Selesai'
        GROUP BY o.id_order, pr.id_primer, pr.kategori
    ")->fetchAll();

    $aggregated_settlements = [];
    foreach ($completed_orders as $co) {
        $oid     = (int)$co['id_order'];
        $pid     = (int)$co['id_primer'];
        $pairKey = $oid . '_' . $pid;

        if (isset($existing_pairs[$pairKey])) {
            continue;
        }

        $kat      = $co['kategori'] ?? '';
        $p_petani = $reg_map[$kat]['petani'] ?? 85.00;
        $p_primer = $reg_map[$kat]['primer'] ?? 10.00;

        $bruto      = (float)$co['bruto'];
        $h_petani   = round(($bruto * $p_petani) / 100, 2);
        $k_primer   = round(($bruto * $p_primer) / 100, 2);
        $k_konsumen = round($bruto - $h_petani - $k_primer, 2);

        if (!isset($aggregated_settlements[$pairKey])) {
            $aggregated_settlements[$pairKey] = [
                'id_order'     => $oid,
                'id_primer'    => $pid,
                'bruto'        => 0.0,
                'hak_petani'   => 0.0,
                'kas_primer'   => 0.0,
                'kas_konsumen' => 0.0,
            ];
        }

        $aggregated_settlements[$pairKey]['bruto']        += $bruto;
        $aggregated_settlements[$pairKey]['hak_petani']   += $h_petani;
        $aggregated_settlements[$pairKey]['kas_primer']   += $k_primer;
        $aggregated_settlements[$pairKey]['kas_konsumen'] += $k_konsumen;
    }

    if (!empty($aggregated_settlements)) {
        $ins = $pdo->prepare("
            INSERT IGNORE INTO settlement_dana
            (kode_settlement, id_order, id_primer, nominal_bruto, hak_petani, kas_primer, kas_konsumen)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($aggregated_settlements as $agg) {
            $kode_stl = 'STL-' . date('Ymd') . '-' . $agg['id_order'] . '-' . $agg['id_primer'];
            $ins->execute([
                $kode_stl,
                $agg['id_order'],
                $agg['id_primer'],
                $agg['bruto'],
                $agg['hak_petani'],
                $agg['kas_primer'],
                $agg['kas_konsumen']
            ]);
        }
    }
} catch (PDOException $e) {
    // Tetap lanjutkan render halaman meskipun sinkronisasi otomatis melewati kendala sementara
}

// 2. PROSES 1: Pencairan Dana dari Koperasi Konsumen Pusat ke Koperasi Primer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'cair_ke_primer') {
    verify_csrf();
    cekAkses(['konsumen']);

    $id_stl = (int)($_POST['id_settlement'] ?? 0);
    if ($id_stl <= 0) {
        redirectSettlement('err', 'ID settlement tidak valid.');
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE settlement_dana
            SET status_pencairan = 'Dicairkan ke Primer', tanggal_cair_primer = NOW()
            WHERE id_settlement = ? AND status_pencairan = 'Menunggu Pencairan Pusat'
        ");
        $stmt->execute([$id_stl]);

        if ($stmt->rowCount() > 0) {
            redirectSettlement('msg', 'Dana hak pembayaran & kas unit berhasil ditransfer ke rekening Koperasi Primer.');
        } else {
            redirectSettlement('err', 'Data settlement tidak valid atau sudah dicairkan sebelumnya.');
        }
    } catch (PDOException $e) {
        redirectSettlement('err', 'Gagal memproses pencairan dana ke Koperasi Primer.');
    }
}

// 3. PROSES 2: Penerusan Dana dari Koperasi Primer ke Petani Pemasok Hulu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'teruskan_ke_petani') {
    verify_csrf();
    cekAkses(['konsumen', 'primer']);

    $id_stl = (int)($_POST['id_settlement'] ?? 0);
    if ($id_stl <= 0) {
        redirectSettlement('err', 'ID settlement tidak valid.');
    }

    try {
        if ($role === 'primer') {
            $id_primer_login = (int)($user['id_primer'] ?? 0);
            $stmt = $pdo->prepare("
                UPDATE settlement_dana
                SET status_pencairan = 'Diteruskan ke Petani', tanggal_cair_petani = NOW()
                WHERE id_settlement = ? AND id_primer = ? AND status_pencairan = 'Dicairkan ke Primer'
            ");
            $stmt->execute([$id_stl, $id_primer_login]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE settlement_dana
                SET status_pencairan = 'Diteruskan ke Petani', tanggal_cair_petani = NOW()
                WHERE id_settlement = ? AND status_pencairan = 'Dicairkan ke Primer'
            ");
            $stmt->execute([$id_stl]);
        }

        if ($stmt->rowCount() > 0) {
            redirectSettlement('msg', 'Dana hasil panen telah resmi diteruskan ke Petani Pemasok.');
        } else {
            redirectSettlement('err', 'Dana belum dicairkan dari pusat atau bukan wewenang unit koperasi Anda.');
        }
    } catch (PDOException $e) {
        redirectSettlement('err', 'Gagal memproses penerusan dana ke Petani Pemasok.');
    }
}

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

// 4. AMBIL DATA SETTLEMENT BERDASARKAN ROLE (PREPARED STATEMENT)
$sql = "
    SELECT s.*, o.no_invoice, o.metode_bayar, o.tanggal_order,
           u.nama_lengkap AS nama_buyer,
           kp.nama_koperasi, kp.kecamatan, kp.ketua_koperasi,
           d.nama_divisi
    FROM settlement_dana s
    JOIN orders o ON s.id_order = o.id_order
    JOIN users u ON o.id_buyer = u.id_user
    JOIN koperasi_primer kp ON s.id_primer = kp.id_primer
    JOIN divisi_wilayah d ON kp.id_divisi = d.id_divisi
";

$params = [];
if (in_array($role, ['primer', 'petani'], true)) {
    $sql .= " WHERE s.id_primer = ? ";
    $params[] = (int)($user['id_primer'] ?? 0);
}
$sql .= " ORDER BY s.id_settlement DESC ";

$stmtList = $pdo->prepare($sql);
$stmtList->execute($params);
$settlements = $stmtList->fetchAll();

// Hitung agregasi KPI & jumlah per status
$tot_bruto      = 0.0;
$tot_petani     = 0.0;
$tot_primer     = 0.0;
$tot_konsumen   = 0.0;
$tot_pending    = 0.0;

$count_all      = count($settlements);
$count_menunggu = 0;
$count_primer   = 0;
$count_petani   = 0;

foreach ($settlements as $st) {
    $tot_bruto    += (float)$st['nominal_bruto'];
    $tot_petani   += (float)$st['hak_petani'];
    $tot_primer   += (float)$st['kas_primer'];
    $tot_konsumen += (float)$st['kas_konsumen'];

    $status_str = $st['status_pencairan'] ?? 'Menunggu Pencairan Pusat';
    if ($status_str === 'Diteruskan ke Petani') {
        $count_petani++;
    } elseif ($status_str === 'Dicairkan ke Primer') {
        $count_primer++;
        $tot_pending += ((float)$st['hak_petani'] + (float)$st['kas_primer']);
    } else {
        $count_menunggu++;
        $tot_pending += ((float)$st['hak_petani'] + (float)$st['kas_primer']);
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

    .stl-wrap {
        display: flex;
        flex-direction: column;
        gap: 22px;
        color: #0F172A;
    }

    /* HERO BANNER KONSISTEN DENGAN MODUL KOPNOVA */
    .stl-hero {
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
    .stl-eyebrow {
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
    .stl-eyebrow-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: #FBBF24;
    }
    .stl-hero-title {
        margin: 0 0 6px;
        font-size: 22px;
        font-weight: 800;
        letter-spacing: -0.3px;
        line-height: 1.25;
    }
    .stl-hero-sub {
        margin: 0;
        font-size: 13.5px;
        color: rgba(236, 253, 245, 0.88);
        max-width: 640px;
        line-height: 1.5;
    }
    .stl-hero-btn {
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
    .stl-hero-btn:hover {
        background: #FBBF24;
    }
    .stl-hero-btn:active {
        transform: scale(0.98);
    }

    /* ALERT NOTIFIKASI */
    .stl-alert {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 13px 16px;
        border-radius: 12px;
        font-size: 13.5px;
        font-weight: 600;
    }
    .stl-alert-success {
        background: #DCFCE7;
        border: 1px solid #86EFAC;
        color: #166534;
    }
    .stl-alert-error {
        background: #FEE2E2;
        border: 1px solid #FCA5A5;
        color: #991B1B;
    }
    .stl-alert-close {
        background: none;
        border: none;
        color: inherit;
        font-size: 18px;
        font-weight: 800;
        cursor: pointer;
        padding: 0 4px;
        opacity: 0.75;
    }
    .stl-alert-close:hover {
        opacity: 1;
    }

    /* 4 KARTU KPI ARUS KEUANGAN & SHU */
    .stl-grid-4 {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 16px;
    }
    .stl-kpi {
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
    .stl-kpi.clickable {
        cursor: pointer;
    }
    .stl-kpi:hover {
        border-color: #10B981;
        transform: translateY(-2px);
    }
    .stl-kpi-val {
        font-size: clamp(1.2rem, 1.65vw, 1.52rem);
        font-weight: 800;
        color: #0F172A;
        margin: 8px 0 3px;
        font-variant-numeric: tabular-nums;
    }

    /* CARD UTAMA & TOOLBAR */
    .stl-card {
        background: #FFFFFF;
        border-radius: 16px;
        border: 1px solid #E2E8F0;
        padding: 22px;
        box-shadow: 0 2px 10px rgba(15, 23, 42, 0.03);
    }
    .stl-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 16px;
        padding-bottom: 13px;
        border-bottom: 1px solid #F1F5F9;
    }
    .stl-pill {
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
    .stl-pill:hover {
        border-color: #10B981;
        color: #065F46;
    }
    .stl-pill.active {
        background: #064E3B;
        color: #FFFFFF;
        border-color: #064E3B;
    }
    .stl-search {
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
    .stl-search:focus {
        outline: none;
        border-color: #10B981;
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.14);
    }

    /* TABEL DESKTOP */
    .stl-table-wrap {
        width: 100%;
        overflow-x: auto;
        border: 1px solid #E2E8F0;
        border-radius: 12px;
    }
    .stl-responsive-table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
        font-size: 13.5px;
    }
    .stl-responsive-table thead tr {
        background: #F8FAFC;
        border-bottom: 1.5px solid #E2E8F0;
    }
    .stl-responsive-table th {
        padding: 12px 14px;
        font-size: 11.5px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #475569;
        white-space: nowrap;
    }
    .stl-responsive-table td {
        padding: 14px;
        border-bottom: 1px solid #F1F5F9;
        color: #1E293B;
        vertical-align: middle;
    }
    .stl-responsive-table tbody tr:last-child td {
        border-bottom: none;
    }
    .stl-row-hover:hover {
        background-color: #F8FAFC;
    }

    /* BADGES & BUTTONS */
    .stl-code {
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
    .stl-badge {
        display: inline-block;
        padding: 4px 11px;
        border-radius: 99px;
        font-size: 11.5px;
        font-weight: 800;
        white-space: nowrap;
    }
    .stl-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        border: none;
        border-radius: 8px;
        font-family: inherit;
        font-size: 12px;
        font-weight: 700;
        padding: 7.5px 12px;
        text-decoration: none;
        cursor: pointer;
        white-space: nowrap;
        width: 178px;
        box-sizing: border-box;
        transition: background-color 0.15s ease, transform 0.14s ease;
    }
    .stl-btn:active {
        transform: scale(0.98);
    }
    .stl-btn-emerald {
        background: #059669;
        color: #FFFFFF;
    }
    .stl-btn-emerald:hover {
        background: #047857;
    }
    .stl-btn-amber {
        background: #D97706;
        color: #FFFFFF;
    }
    .stl-btn-amber:hover {
        background: #B45309;
    }
    .stl-btn-light {
        background: #F1F5F9;
        color: #0F172A;
        border: 1px solid #CBD5E1;
    }
    .stl-btn-light:hover {
        background: #E2E8F0;
    }

    .mobile-nav-trigger {
        display: none;
    }

    @media (max-width: 1120px) {
        .stl-grid-4 {
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
        .stl-table-wrap {
            border: none;
            border-radius: 0;
            overflow: visible;
        }
        .stl-responsive-table thead {
            display: none;
        }
        .stl-responsive-table,
        .stl-responsive-table tbody,
        .stl-responsive-table tr,
        .stl-responsive-table td {
            display: block;
            width: 100%;
            box-sizing: border-box;
        }
        .stl-responsive-table tr.stl-mobile-card {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 14px;
            padding: 14px;
            margin-bottom: 12px;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03);
        }
        .stl-responsive-table td {
            padding: 6px 0 !important;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            border: none !important;
            text-align: right;
            max-width: 100% !important;
        }
        .stl-responsive-table td::before {
            content: attr(data-label);
            font-size: 11.5px;
            font-weight: 700;
            color: #64748B;
            text-align: left;
            flex-shrink: 0;
        }
        .stl-responsive-table td.td-card-header {
            border-bottom: 1px dashed #E2E8F0 !important;
            padding-bottom: 9px !important;
            margin-bottom: 4px;
            justify-content: flex-start;
            text-align: left;
        }
        .stl-responsive-table td.td-card-header::before {
            display: none;
        }
        .stl-responsive-table td.td-card-action {
            border-top: 1px solid #F1F5F9 !important;
            padding-top: 10px !important;
            margin-top: 6px;
            flex-direction: column;
            align-items: stretch;
        }
        .stl-responsive-table td.td-card-action::before {
            margin-bottom: 5px;
        }
        .stl-responsive-table td.td-card-action .stl-action-group {
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 7px;
        }
        .stl-responsive-table td.td-card-action .stl-btn {
            width: 100%;
            padding: 9px 12px;
            font-size: 13px;
        }
    }

    @media (max-width: 640px) {
        .stl-grid-4 {
            grid-template-columns: minmax(0, 1fr);
        }
        .stl-hero {
            padding: 20px;
        }
        .stl-hero-title {
            font-size: 19px;
        }
        .stl-hero-btn {
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

<div class="stl-wrap">

    <!-- BANNER HEADER KONSISTEN -->
    <section class="stl-hero">
        <div>
            <div class="stl-eyebrow">
                <span class="stl-eyebrow-dot"></span>
                <span>TREASURY &amp; DISBURSEMENT • CASHFLOW SETTLEMENT</span>
            </div>
            <h2 class="stl-hero-title">Rekap Keuangan &amp; Settlement Pencairan Dana</h2>
            <p class="stl-hero-sub">
                Distribusi hak pembayaran dan SHU otomatis dari Koperasi Konsumen Pusat ke Koperasi Primer dan Petani Pemasok untuk transaksi berstatus <strong>Selesai</strong>.
            </p>
        </div>

        <a href="../transaksi/index.php" class="stl-hero-btn">
            <span>Kelola Pesanan Selesai</span>
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
        </a>
    </section>

    <!-- ALERT NOTIFIKASI -->
    <?php if (isset($_GET['msg']) && $_GET['msg'] !== ''): ?>
        <div class="stl-alert stl-alert-success">
            <span>✓ <?= htmlspecialchars($_GET['msg']) ?></span>
            <button type="button" class="stl-alert-close" onclick="this.parentElement.remove()" aria-label="Tutup">&times;</button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['err']) && $_GET['err'] !== ''): ?>
        <div class="stl-alert stl-alert-error">
            <span>! <?= htmlspecialchars($_GET['err']) ?></span>
            <button type="button" class="stl-alert-close" onclick="this.parentElement.remove()" aria-label="Tutup">&times;</button>
        </div>
    <?php endif; ?>

    <!-- 4 KARTU KPI ARUS KEUANGAN & SHU -->
    <section class="stl-grid-4">
        <div class="stl-kpi clickable" style="border-left:4px solid #064E3B;" onclick="quickSelectStlFilter('ALL')" title="Tampilkan semua batch settlement">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                <span style="font-size:12px; color:#065F46; font-weight:700;">Total Settlement Bruto</span>
                <div style="width:35px; height:35px; border-radius:10px; background:#ECFDF5; color:#064E3B; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                </div>
            </div>
            <div class="stl-kpi-val" style="color:#064E3B;"><?= rupiah($tot_bruto) ?></div>
            <div style="font-size:11.5px; color:#64748B;">Dari <?= $count_all ?> batch transaksi selesai</div>
        </div>

        <div class="stl-kpi" style="border-left:4px solid #10B981;">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                <span style="font-size:12px; color:#059669; font-weight:700;">Alokasi Hak Petani Hulu</span>
                <div style="width:35px; height:35px; border-radius:10px; background:#DCFCE7; color:#059669; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
                </div>
            </div>
            <div class="stl-kpi-val"><?= rupiah($tot_petani) ?></div>
            <div style="font-size:11.5px; color:#64748B;">Diteruskan melalui Koperasi Primer</div>
        </div>

        <div class="stl-kpi" style="border-left:4px solid #D97706;">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                <span style="font-size:12px; color:#92400E; font-weight:700;">Kas Operasional Primer</span>
                <div style="width:35px; height:35px; border-radius:10px; background:#FEF3C7; color:#D97706; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                </div>
            </div>
            <div class="stl-kpi-val" style="color:#B45309;"><?= rupiah($tot_primer) ?></div>
            <div style="font-size:11.5px; color:#64748B;">Margin kurasi &amp; logistik kecamatan</div>
        </div>

        <div class="stl-kpi" style="border-left:4px solid #2563EB;">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                <span style="font-size:12px; color:#1E40AF; font-weight:700;">Kas Koperasi Konsumen</span>
                <div style="width:35px; height:35px; border-radius:10px; background:#DBEAFE; color:#2563EB; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                </div>
            </div>
            <div class="stl-kpi-val" style="color:#1E40AF;"><?= rupiah($tot_konsumen) ?></div>
            <div style="font-size:11.5px; color:#64748B;">SHU &amp; pengembangan ekosistem pusat</div>
        </div>
    </section>

    <!-- TABEL DAFTAR SETTLEMENT & PENCAIRAN DANA BERJENJANG -->
    <section class="stl-card" id="stlTableSection">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:10px; margin-bottom:14px;">
            <div>
                <h3 style="margin:0; font-size:16.5px; font-weight:800; color:#0F172A;">Buku Besar Settlement &amp; Status Transfer Pencairan</h3>
                <div style="font-size:12.5px; color:#64748B; margin-top:2px;">Alur pencairan berjenjang: <strong>Koperasi Konsumen (Pusat)</strong> &rarr; <strong>Koperasi Primer</strong> &rarr; <strong>Petani Pemasok</strong>.</div>
            </div>
            <?php if ($tot_pending > 0): ?>
                <span style="background:#FEF3C7; border:1px solid #FDE68A; color:#92400E; font-size:12px; font-weight:800; padding:6px 14px; border-radius:99px;">
                    Menunggu Pelunasan Hulu: <?= rupiah($tot_pending) ?>
                </span>
            <?php endif; ?>
        </div>

        <!-- FILTER PILLS & PENCARIAN LIVE -->
        <div class="stl-toolbar">
            <div style="display:flex; gap:6px; flex-wrap:wrap;" id="stlFilterBar">
                <button type="button" class="stl-pill active" data-filter="ALL" onclick="filterSettlement('ALL', this)">Semua (<?= $count_all ?>)</button>
                <button type="button" class="stl-pill" data-filter="Menunggu Pencairan Pusat" onclick="filterSettlement('Menunggu Pencairan Pusat', this)">Menunggu Pusat (<?= $count_menunggu ?>)</button>
                <button type="button" class="stl-pill" data-filter="Dicairkan ke Primer" onclick="filterSettlement('Dicairkan ke Primer', this)">Di Koperasi Primer (<?= $count_primer ?>)</button>
                <button type="button" class="stl-pill" data-filter="Diteruskan ke Petani" onclick="filterSettlement('Diteruskan ke Petani', this)">Selesai ke Petani (<?= $count_petani ?>)</button>
            </div>

            <div style="position:relative; min-width:230px; flex:1; max-width:340px;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#64748B" stroke-width="2.2" style="position:absolute; left:11px; top:50%; transform:translateY(-50%); pointer-events:none;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="search" id="stlSearchInput" oninput="applyStlFilters()" placeholder="Cari kode settlement, invoice, koperasi..." class="stl-search">
            </div>
        </div>

        <div class="stl-table-wrap">
            <table class="stl-responsive-table">
                <thead>
                    <tr>
                        <th>Kode Settlement &amp; Invoice</th>
                        <th>Koperasi Primer Tujuan</th>
                        <th>Rincian Distribusi Dana (SHU)</th>
                        <th>Status Alur Transfer</th>
                        <th style="text-align:right;">Otorisasi &amp; Slip</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($settlements)): ?>
                        <tr>
                            <td colspan="5" style="padding:36px 20px; text-align:center;">
                                <div style="font-weight:800; font-size:15px; color:#334155; margin-bottom:6px;">Belum Ada Transaksi Berstatus "Selesai" untuk Di-Settlement</div>
                                <div style="font-size:13px; color:#64748B; max-width:520px; margin:0 auto;">
                                    Ubah status pesanan Buyer menjadi <strong>Selesai</strong> pada menu <em>Monitoring Transaksi Masuk</em>, maka sistem otomatis menghitung pembagian dana di tabel ini.
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($settlements as $s):
                            $st = $s['status_pencairan'] ?? 'Menunggu Pencairan Pusat';
                            if ($st === 'Diteruskan ke Petani') {
                                $badge_bg = '#DCFCE7'; $badge_col = '#166534'; $badge_bdr = '#BBF7D0';
                            } elseif ($st === 'Dicairkan ke Primer') {
                                $badge_bg = '#DBEAFE'; $badge_col = '#1E40AF'; $badge_bdr = '#BFDBFE';
                            } else {
                                $badge_bg = '#FEF3C7'; $badge_col = '#92400E'; $badge_bdr = '#FDE68A';
                            }

                            $search_blob = strtolower(implode(' ', [
                                $s['kode_settlement'] ?? '',
                                $s['no_invoice'] ?? '',
                                $s['nama_buyer'] ?? '',
                                $s['nama_koperasi'] ?? '',
                                $s['kecamatan'] ?? '',
                                $s['nama_divisi'] ?? '',
                                $s['ketua_koperasi'] ?? '',
                                $st
                            ]));
                        ?>
                        <tr class="stl-row-hover stl-mobile-card stl-data-row"
                            data-status="<?= htmlspecialchars($st, ENT_QUOTES) ?>"
                            data-search="<?= htmlspecialchars($search_blob, ENT_QUOTES) ?>">
                            <td class="td-card-header">
                                <div>
                                    <span class="stl-code"><?= htmlspecialchars($s['kode_settlement']) ?></span>
                                    <div style="font-weight:800; color:#0F172A; margin-top:4px;"><?= htmlspecialchars($s['no_invoice']) ?></div>
                                    <div style="font-size:11.5px; color:#64748B; margin-top:2px;">Buyer: <?= htmlspecialchars($s['nama_buyer']) ?></div>
                                </div>
                            </td>

                            <td data-label="Koperasi Primer">
                                <div>
                                    <div style="font-weight:800; color:#0F172A;"><?= htmlspecialchars($s['nama_koperasi']) ?></div>
                                    <div style="font-size:11.5px; color:#64748B;">Kec. <?= htmlspecialchars($s['kecamatan']) ?> (<?= htmlspecialchars($s['nama_divisi']) ?>)</div>
                                    <div style="font-size:11px; color:#475569; margin-top:1px;">PIC: <?= htmlspecialchars($s['ketua_koperasi']) ?></div>
                                </div>
                            </td>

                            <td data-label="Distribusi SHU" style="min-width:240px;">
                                <div style="font-variant-numeric:tabular-nums;">
                                    <div style="font-weight:800; color:#0F172A; margin-bottom:4px;">Bruto: <?= rupiah((float)$s['nominal_bruto']) ?></div>
                                    <div style="display:flex; flex-direction:column; gap:2px; font-size:11.5px;">
                                        <span style="color:#059669; font-weight:700;">• Hak Petani: <?= rupiah((float)$s['hak_petani']) ?></span>
                                        <span style="color:#D97706; font-weight:700;">• Kas Primer: <?= rupiah((float)$s['kas_primer']) ?></span>
                                        <span style="color:#2563EB; font-weight:700;">• Kas Konsumen: <?= rupiah((float)$s['kas_konsumen']) ?></span>
                                    </div>
                                </div>
                            </td>

                            <td data-label="Status Transfer">
                                <div>
                                    <span class="stl-badge" style="background:<?= $badge_bg ?>; color:<?= $badge_col ?>; border:1px solid <?= $badge_bdr ?>;">
                                        <?= htmlspecialchars($st) ?>
                                    </span>
                                    <?php if (!empty($s['tanggal_cair_primer'])): ?>
                                        <div style="font-size:11px; color:#64748B; margin-top:4px;">Ke Primer: <?= date('d/m/Y H:i', strtotime($s['tanggal_cair_primer'])) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($s['tanggal_cair_petani'])): ?>
                                        <div style="font-size:11px; color:#059669; font-weight:600; margin-top:1px;">Ke Petani: <?= date('d/m/Y H:i', strtotime($s['tanggal_cair_petani'])) ?></div>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <td data-label="Otorisasi & Slip" class="td-card-action" style="text-align:right;">
                                <div class="stl-action-group" style="display:inline-flex; flex-direction:column; gap:6px; align-items:flex-end;">
                                    <?php if ($role === 'konsumen' && $st === 'Menunggu Pencairan Pusat'): ?>
                                        <form method="POST" style="margin:0;" onsubmit="return confirm('Konfirmasi pencairan dana settlement <?= htmlspecialchars($s['kode_settlement'], ENT_QUOTES) ?> ke <?= htmlspecialchars($s['nama_koperasi'], ENT_QUOTES) ?>?')">
                                            <?= $csrf_input ?>
                                            <input type="hidden" name="aksi" value="cair_ke_primer">
                                            <input type="hidden" name="id_settlement" value="<?= (int)$s['id_settlement'] ?>">
                                            <button type="submit" class="stl-btn stl-btn-emerald">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                                                <span>Cairkan ke Primer</span>
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if (in_array($role, ['konsumen', 'primer'], true) && $st === 'Dicairkan ke Primer'): ?>
                                        <form method="POST" style="margin:0;" onsubmit="return confirm('Konfirmasi penerusan dana hasil panen kepada Petani Pemasok unit ini?')">
                                            <?= $csrf_input ?>
                                            <input type="hidden" name="aksi" value="teruskan_ke_petani">
                                            <input type="hidden" name="id_settlement" value="<?= (int)$s['id_settlement'] ?>">
                                            <button type="submit" class="stl-btn stl-btn-amber">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><polyline points="20 6 9 17 4 12"/></svg>
                                                <span>Teruskan ke Petani</span>
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <a href="bukti_pencairan.php?id=<?= (int)$s['id_settlement'] ?>" target="_blank" class="stl-btn stl-btn-light">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                                        <span>Slip Pencairan</span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <tr id="stlEmptyFilterRow" style="display:none;">
                            <td colspan="5" style="padding:26px; text-align:center; color:#64748B;">
                                Tidak ada data settlement yang cocok dengan filter status atau kata kunci pencarian.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

</div>

<script>
let currentStlStatus = 'ALL';

function filterSettlement(status, btn) {
    currentStlStatus = status;
    document.querySelectorAll('#stlFilterBar .stl-pill').forEach(function(el) {
        el.classList.remove('active');
    });
    if (btn) btn.classList.add('active');
    applyStlFilters();
}

function quickSelectStlFilter(status) {
    const btn = document.querySelector('#stlFilterBar .stl-pill[data-filter="' + status + '"]');
    filterSettlement(status, btn);
    const sec = document.getElementById('stlTableSection');
    if (sec) sec.scrollIntoView({ behavior: 'smooth' });
}

function applyStlFilters() {
    const q = (document.getElementById('stlSearchInput')?.value || '').toLowerCase().trim();
    const rows = document.querySelectorAll('.stl-data-row');
    const emptyRow = document.getElementById('stlEmptyFilterRow');
    let visibleCount = 0;

    rows.forEach(function(row) {
        const st  = row.getAttribute('data-status');
        const txt = row.getAttribute('data-search') || '';
        const matchSt   = (currentStlStatus === 'ALL' || st === currentStlStatus);
        const matchText = !q || txt.indexOf(q) !== -1;
        const show = matchSt && matchText;
        row.style.display = show ? '' : 'none';
        if (show) visibleCount++;
    });

    if (emptyRow) {
        emptyRow.style.display = (rows.length > 0 && visibleCount === 0) ? '' : 'none';
    }
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