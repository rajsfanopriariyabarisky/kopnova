<?php
require_once '../../config/database.php';
cekAkses(['konsumen', 'primer', 'petani']);

$role = $_SESSION['user']['role'] ?? '';
$user = $_SESSION['user'] ?? [];

function redirectEws(string $type, string $text): void {
    header("Location: index.php?" . $type . "=" . urlencode($text));
    exit;
}

// PROSES 1: Kirim Instruksi Pengadaan Pasokan dari Konsumen Pusat ke Koperasi Primer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'kirim_instruksi') {
    verify_csrf();
    cekAkses(['konsumen']);

    $id_primer     = (int)($_POST['id_primer'] ?? 0);
    $nama_kom      = trim($_POST['nama_komoditas'] ?? '');
    $kategori      = trim($_POST['kategori'] ?? '');
    $target_jumlah = (int)($_POST['target_jumlah'] ?? 0);
    $satuan        = trim($_POST['satuan'] ?? '');
    $urgensi       = trim($_POST['tingkat_urgensi'] ?? '');
    $catatan       = trim($_POST['catatan_pusat'] ?? '');

    $valid_kategori = ['Komoditas Tanaman', 'Saprodi', 'Alsintan', 'Produk Olahan', 'Produk Lainnya'];
    $valid_urgensi  = ['Kritis (< 20)', 'Waspada (< 50)', 'Prediksi Musiman'];

    if (
        $id_primer <= 0 ||
        $nama_kom === '' ||
        $satuan === '' ||
        $target_jumlah <= 0 ||
        !in_array($kategori, $valid_kategori, true) ||
        !in_array($urgensi, $valid_urgensi, true)
    ) {
        redirectEws('err', 'Data permintaan stok belum lengkap. Pastikan jumlah target lebih dari 0 dan semua kolom wajib diisi.');
    }

    try {
        $cekPrimer = $pdo->prepare("SELECT COUNT(*) FROM koperasi_primer WHERE id_primer = ?");
        $cekPrimer->execute([$id_primer]);
        if ((int)$cekPrimer->fetchColumn() === 0) {
            redirectEws('err', 'Cabang Koperasi Primer tujuan tidak ditemukan.');
        }

        $kode = 'EWS-' . date('ymd') . '-' . random_int(100, 999);
        $stmt = $pdo->prepare("
            INSERT INTO instruksi_pasokan
            (kode_instruksi, id_primer, nama_komoditas, kategori, target_jumlah, satuan, tingkat_urgensi, catatan_pusat)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
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

        redirectEws('msg', "Permintaan tambah stok ({$kode}) berhasil dikirim ke cabang koperasi.");
    } catch (PDOException $e) {
        redirectEws('err', 'Gagal menyimpan permintaan tambah stok. Silakan coba kembali.');
    }
}

// PROSES 2: Update Progres Instruksi oleh Koperasi Primer / Konsumen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'update_progres') {
    verify_csrf();
    cekAkses(['konsumen', 'primer']);

    $id_ins       = (int)($_POST['id_instruksi'] ?? 0);
    $status       = trim($_POST['status_instruksi'] ?? '');
    $valid_status = ['Terkirim ke Primer', 'Diteruskan ke Petani', 'Terpenuhi'];

    if ($id_ins <= 0 || !in_array($status, $valid_status, true)) {
        redirectEws('err', 'Status progres permintaan stok tidak valid.');
    }

    try {
        if ($role === 'primer') {
            $id_primer_login = (int)($user['id_primer'] ?? 0);
            $stmt = $pdo->prepare("UPDATE instruksi_pasokan SET status_instruksi = ? WHERE id_instruksi = ? AND id_primer = ?");
            $stmt->execute([$status, $id_ins, $id_primer_login]);
        } else {
            $stmt = $pdo->prepare("UPDATE instruksi_pasokan SET status_instruksi = ? WHERE id_instruksi = ?");
            $stmt->execute([$status, $id_ins]);
        }

        if ($stmt->rowCount() > 0) {
            redirectEws('msg', "Status permintaan stok diperbarui menjadi: {$status}");
        } else {
            redirectEws('err', 'Data tidak ditemukan, status tidak berubah, atau bukan wewenang cabang Anda.');
        }
    } catch (PDOException $e) {
        redirectEws('err', 'Gagal memperbarui status progres permintaan stok.');
    }
}

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

// Query Analisis Stok Kritis (< 50) + Total Permintaan Buyer per Produk (Fail-closed untuk Primer & Petani)
if (in_array($role, ['primer', 'petani'], true)) {
    $stmtProd = $pdo->prepare("
        SELECT pr.*, kp.nama_koperasi, kp.kecamatan, d.nama_divisi,
               COALESCE((SELECT SUM(oi.qty) FROM order_items oi WHERE oi.id_produk = pr.id_produk), 0) AS total_terjual
        FROM produk_etalase pr
        JOIN koperasi_primer kp ON pr.id_primer = kp.id_primer
        JOIN divisi_wilayah d ON pr.id_divisi = d.id_divisi
        WHERE pr.id_primer = ?
        ORDER BY pr.stok ASC, total_terjual DESC
    ");
    $stmtProd->execute([(int)($user['id_primer'] ?? 0)]);
    $produk_analisis = $stmtProd->fetchAll();
} else {
    $produk_analisis = $pdo->query("
        SELECT pr.*, kp.nama_koperasi, kp.kecamatan, d.nama_divisi,
               COALESCE((SELECT SUM(oi.qty) FROM order_items oi WHERE oi.id_produk = pr.id_produk), 0) AS total_terjual
        FROM produk_etalase pr
        JOIN koperasi_primer kp ON pr.id_primer = kp.id_primer
        JOIN divisi_wilayah d ON pr.id_divisi = d.id_divisi
        ORDER BY pr.stok ASC, total_terjual DESC
    ")->fetchAll();
}

// Hitung ringkasan kategori stok
$count_kritis  = 0;
$count_waspada = 0;
$count_aman    = 0;
foreach ($produk_analisis as $item) {
    $s = (int)$item['stok'];
    if ($s < 20) {
        $count_kritis++;
    } elseif ($s < 50) {
        $count_waspada++;
    } else {
        $count_aman++;
    }
}
$total_stok_menipis = $count_kritis + $count_waspada;

$primer_list = ($role === 'konsumen')
    ? $pdo->query("
        SELECT kp.*, d.nama_divisi
        FROM koperasi_primer kp
        JOIN divisi_wilayah d ON kp.id_divisi = d.id_divisi
        ORDER BY d.nama_divisi ASC, kp.nama_koperasi ASC
    ")->fetchAll()
    : [];

// Daftar Instruksi Pasokan (Fail-closed untuk Primer & Petani)
if (in_array($role, ['primer', 'petani'], true)) {
    $stmtIns = $pdo->prepare("
        SELECT ip.*, kp.nama_koperasi, kp.kecamatan, d.nama_divisi
        FROM instruksi_pasokan ip
        JOIN koperasi_primer kp ON ip.id_primer = kp.id_primer
        JOIN divisi_wilayah d ON kp.id_divisi = d.id_divisi
        WHERE ip.id_primer = ?
        ORDER BY ip.id_instruksi DESC
    ");
    $stmtIns->execute([(int)($user['id_primer'] ?? 0)]);
    $instruksi_list = $stmtIns->fetchAll();
} else {
    $instruksi_list = $pdo->query("
        SELECT ip.*, kp.nama_koperasi, kp.kecamatan, d.nama_divisi
        FROM instruksi_pasokan ip
        JOIN koperasi_primer kp ON ip.id_primer = kp.id_primer
        JOIN divisi_wilayah d ON kp.id_divisi = d.id_divisi
        ORDER BY ip.id_instruksi DESC
    ")->fetchAll();
}

$instruksi_berjalan = 0;
foreach ($instruksi_list as $ins_item) {
    if (($ins_item['status_instruksi'] ?? '') !== 'Terpenuhi') {
        $instruksi_berjalan++;
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

    .ews-wrap {
        display: flex;
        flex-direction: column;
        gap: 22px;
        color: #0F172A;
    }

    /* HERO BANNER KONSISTEN DENGAN MODUL KOPNOVA */
    .ews-hero {
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
    .ews-eyebrow {
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
    .ews-eyebrow-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: #FBBF24;
    }
    .ews-hero-title {
        margin: 0 0 6px;
        font-size: 22px;
        font-weight: 800;
        letter-spacing: -0.3px;
        line-height: 1.25;
    }
    .ews-hero-sub {
        margin: 0;
        font-size: 13.5px;
        color: rgba(236, 253, 245, 0.88);
        max-width: 630px;
        line-height: 1.5;
    }
    .ews-hero-btn {
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
        cursor: pointer;
        box-shadow: 0 6px 18px rgba(245, 158, 11, 0.32);
        transition: background-color 0.16s ease, transform 0.14s ease;
        white-space: nowrap;
    }
    .ews-hero-btn:hover {
        background: #FBBF24;
    }
    .ews-hero-btn:active {
        transform: scale(0.98);
    }

    /* ALERT NOTIFIKASI */
    .ews-alert {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 13px 16px;
        border-radius: 12px;
        font-size: 13.5px;
        font-weight: 600;
    }
    .ews-alert-success {
        background: #DCFCE7;
        border: 1px solid #86EFAC;
        color: #166534;
    }
    .ews-alert-error {
        background: #FEE2E2;
        border: 1px solid #FCA5A5;
        color: #991B1B;
    }
    .ews-alert-close {
        background: none;
        border: none;
        color: inherit;
        font-size: 18px;
        font-weight: 800;
        cursor: pointer;
        padding: 0 4px;
        opacity: 0.75;
    }
    .ews-alert-close:hover {
        opacity: 1;
    }

    /* 3 KARTU KPI RINGKASAN */
    .ews-grid-3 {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 16px;
    }
    .ews-kpi {
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
    .ews-kpi.clickable {
        cursor: pointer;
    }
    .ews-kpi:hover {
        border-color: #10B981;
        transform: translateY(-2px);
    }
    .ews-kpi-val {
        font-size: clamp(1.3rem, 1.8vw, 1.6rem);
        font-weight: 800;
        color: #0F172A;
        margin: 8px 0 3px;
        font-variant-numeric: tabular-nums;
    }

    /* CARD UTAMA */
    .ews-card {
        background: #FFFFFF;
        border-radius: 16px;
        border: 1px solid #E2E8F0;
        padding: 22px;
        box-shadow: 0 2px 10px rgba(15, 23, 42, 0.03);
    }

    /* FILTER PILLS & SEARCH */
    .ews-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 16px;
        padding-bottom: 13px;
        border-bottom: 1px solid #F1F5F9;
    }
    .ews-pill {
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
    .ews-pill:hover {
        border-color: #10B981;
        color: #065F46;
    }
    .ews-pill.active {
        background: #064E3B;
        color: #FFFFFF;
        border-color: #064E3B;
    }
    .ews-search {
        padding: 8.5px 13px;
        border: 1px solid #CBD5E1;
        border-radius: 10px;
        font-family: inherit;
        font-size: 13px;
        color: #0F172A;
        background: #FFFFFF;
        min-width: 230px;
        transition: border-color 0.16s ease, box-shadow 0.16s ease;
    }
    .ews-search:focus {
        outline: none;
        border-color: #10B981;
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.14);
    }

    /* TABEL DESKTOP */
    .ews-table-wrap {
        width: 100%;
        overflow-x: auto;
        border: 1px solid #E2E8F0;
        border-radius: 12px;
    }
    .ews-responsive-table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
        font-size: 13.5px;
    }
    .ews-responsive-table thead tr {
        background: #F8FAFC;
        border-bottom: 1.5px solid #E2E8F0;
    }
    .ews-responsive-table th {
        padding: 12px 14px;
        font-size: 11.5px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #475569;
        white-space: nowrap;
    }
    .ews-responsive-table td {
        padding: 13px 14px;
        border-bottom: 1px solid #F1F5F9;
        color: #1E293B;
        vertical-align: middle;
    }
    .ews-responsive-table tbody tr:last-child td {
        border-bottom: none;
    }
    .ews-row-hover:hover {
        background-color: #F8FAFC !important;
    }

    /* BADGES & TOMBOL AKSI */
    .ews-code {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 7px;
        background: #F1F5F9;
        border: 1px solid #CBD5E1;
        color: #065F46;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 12px;
        font-weight: 700;
    }
    .ews-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        border: none;
        border-radius: 9px;
        font-family: inherit;
        font-size: 12px;
        font-weight: 700;
        text-decoration: none;
        white-space: nowrap;
        cursor: pointer;
        transition: background-color 0.16s ease, transform 0.14s ease;
    }
    .ews-btn:active {
        transform: scale(0.98);
    }
    .ews-btn-emerald {
        background: #064E3B;
        color: #FFFFFF;
        padding: 8px 13px;
    }
    .ews-btn-emerald:hover {
        background: #042F24;
    }

    /* MODAL RINGAN (ZERO LAG) */
    .ews-modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.58);
        z-index: 999;
        align-items: center;
        justify-content: center;
        padding: 16px;
    }
    .ews-modal-overlay.show {
        display: flex;
    }
    .ews-modal-box {
        background: #FFFFFF;
        border: 1px solid #E2E8F0;
        border-radius: 18px;
        width: 100%;
        max-width: 530px;
        max-height: 92vh;
        overflow-y: auto;
        box-shadow: 0 22px 46px rgba(15, 23, 42, 0.24);
    }
    .ews-modal-head {
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
    .ews-label {
        display: block;
        font-size: 12px;
        font-weight: 700;
        color: #334155;
    }
    .ews-input {
        width: 100%;
        box-sizing: border-box;
        padding: 10px 12px;
        border: 1px solid #CBD5E1;
        border-radius: 10px;
        margin-top: 5px;
        font-family: inherit;
        font-size: 13px;
        font-weight: 600;
        color: #0F172A;
        background: #FFFFFF;
        transition: border-color 0.16s ease, box-shadow 0.16s ease;
    }
    .ews-input:focus {
        outline: none;
        border-color: #10B981;
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.14);
    }
    .ews-form-row-2 {
        display: grid;
        grid-template-columns: 1.65fr 1fr;
        gap: 12px;
    }
    .ews-form-row-3 {
        display: grid;
        grid-template-columns: 1fr 1fr 1.25fr;
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
        .ews-table-wrap {
            border: none;
            border-radius: 0;
            overflow: visible;
        }
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
            padding: 20px;
        }
        .ews-hero-title {
            font-size: 19px;
        }
        .ews-hero-btn {
            width: 100%;
        }
        .ews-search {
            width: 100%;
            min-width: 0;
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

<div class="ews-wrap">

    <!-- BANNER HEADER KONSISTEN -->
    <section class="ews-hero">
        <div>
            <div class="ews-eyebrow">
                <span class="ews-eyebrow-dot"></span>
                <span>EARLY WARNING SYSTEM &amp; RESTOCK PASOKAN</span>
            </div>
            <h2 class="ews-hero-title">Deteksi Stok Menipis &amp; Permintaan Panen Baru</h2>
            <p class="ews-hero-sub">
                Sistem otomatis menandai komoditas dengan sisa stok di bawah 50 unit agar koperasi pusat dan cabang dapat segera mengoordinasikan pasokan bersama petani mitra.
            </p>
        </div>

        <?php if ($role === 'konsumen'): ?>
            <button type="button" onclick="openInstruksiModal()" class="ews-hero-btn">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <span>Minta Tambah Stok ke Cabang</span>
            </button>
        <?php endif; ?>
    </section>

    <!-- ALERT NOTIFIKASI -->
    <?php if (isset($_GET['msg']) && $_GET['msg'] !== ''): ?>
        <div class="ews-alert ews-alert-success">
            <span>✓ <?= htmlspecialchars($_GET['msg']) ?></span>
            <button type="button" class="ews-alert-close" onclick="this.parentElement.remove()" aria-label="Tutup">&times;</button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['err']) && $_GET['err'] !== ''): ?>
        <div class="ews-alert ews-alert-error">
            <span>! <?= htmlspecialchars($_GET['err']) ?></span>
            <button type="button" class="ews-alert-close" onclick="this.parentElement.remove()" aria-label="Tutup">&times;</button>
        </div>
    <?php endif; ?>

    <!-- 3 KARTU RINGKASAN -->
    <section class="ews-grid-3">
        <div class="ews-kpi clickable" style="border-left:4px solid #DC2626;" onclick="quickSelectStockFilter('KRITIS')" title="Klik untuk filter stok kritis">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                <span style="font-size:12px; color:#991B1B; font-weight:700;">Stok Menipis (&lt; 50 Unit)</span>
                <div style="width:36px; height:36px; border-radius:10px; background:#FEE2E2; color:#DC2626; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                </div>
            </div>
            <div class="ews-kpi-val" style="color:<?= $total_stok_menipis > 0 ? '#DC2626' : '#0F172A' ?>;"><?= $total_stok_menipis ?> Barang</div>
            <div style="font-size:11.5px; color:#64748B;">
                <?= $count_kritis ?> Kritis (&lt; 20) • <?= $count_waspada ?> Waspada (&lt; 50)
            </div>
        </div>

        <div class="ews-kpi clickable" style="border-left:4px solid #D97706;" onclick="document.getElementById('sectionRiwayatIns').scrollIntoView({behavior:'smooth'})" title="Lihat riwayat permintaan restock">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                <span style="font-size:12px; color:#92400E; font-weight:700;">Permintaan Restock</span>
                <div style="width:36px; height:36px; border-radius:10px; background:#FEF3C7; color:#D97706; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                </div>
            </div>
            <div class="ews-kpi-val"><?= count($instruksi_list) ?> Tugas</div>
            <div style="font-size:11.5px; color:#64748B;"><?= $instruksi_berjalan ?> sedang berjalan ke cabang &amp; petani</div>
        </div>

        <div class="ews-kpi clickable" style="border-left:4px solid #059669;" onclick="quickSelectStockFilter('ALL')" title="Tampilkan semua produk terpantau">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                <span style="font-size:12px; color:#065F46; font-weight:700;">Semua Produk Dipantau</span>
                <div style="width:36px; height:36px; border-radius:10px; background:#DCFCE7; color:#059669; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                </div>
            </div>
            <div class="ews-kpi-val"><?= count($produk_analisis) ?> Produk</div>
            <div style="font-size:11.5px; color:#059669; font-weight:600;"><?= $count_aman ?> produk dalam batas stok aman</div>
        </div>
    </section>

    <!-- SECTION 1: PANTAUAN STOK BARANG VS PERMINTAAN PEMBELI -->
    <section class="ews-card" id="sectionPantauanStok">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:10px; margin-bottom:14px;">
            <div>
                <h3 style="margin:0; font-size:16.5px; font-weight:800; color:#0F172A;">Pantauan Stok Barang vs Permintaan Pembeli</h3>
                <div style="font-size:12.5px; color:#64748B; margin-top:2px;">Barang dengan sisa stok di bawah 50 otomatis ditandai supaya bisa langsung diminta tambah stok.</div>
            </div>
        </div>

        <!-- FILTER PILLS & PENCARIAN LIVE -->
        <div class="ews-toolbar">
            <div style="display:flex; gap:6px; flex-wrap:wrap;" id="stockFilterBar">
                <button type="button" class="ews-pill active" data-filter="ALL" onclick="filterStocks('ALL', this)">Semua (<?= count($produk_analisis) ?>)</button>
                <button type="button" class="ews-pill" data-filter="KRITIS" onclick="filterStocks('KRITIS', this)">Kritis &lt; 20 (<?= $count_kritis ?>)</button>
                <button type="button" class="ews-pill" data-filter="WASPADA" onclick="filterStocks('WASPADA', this)">Waspada &lt; 50 (<?= $count_waspada ?>)</button>
                <button type="button" class="ews-pill" data-filter="AMAN" onclick="filterStocks('AMAN', this)">Stok Aman (<?= $count_aman ?>)</button>
            </div>
            <input type="search" id="searchStockInput" oninput="applyStockFilters()" placeholder="Cari nama produk, koperasi, atau divisi..." class="ews-search">
        </div>

        <div class="ews-table-wrap">
            <table class="ews-responsive-table" id="stockTable">
                <thead>
                    <tr>
                        <th>Produk &amp; Asal Cabang</th>
                        <th>Sisa Stok</th>
                        <th>Sudah Terjual</th>
                        <th>Saran Tambah Stok</th>
                        <th>Status Stok</th>
                        <?php if ($role === 'konsumen'): ?>
                            <th style="text-align:right;">Aksi Cepat</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($produk_analisis)): ?>
                        <tr>
                            <td colspan="<?= $role === 'konsumen' ? 6 : 5 ?>" style="padding:28px; text-align:center; color:#64748B;">
                                Belum ada data produk di etalase untuk dipantau.
                            </td>
                        </tr>
                    <?php else: ?>
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

                            $search_blob = strtolower(
                                ($pa['nama_produk'] ?? '') . ' ' .
                                ($pa['nama_koperasi'] ?? '') . ' ' .
                                ($pa['nama_divisi'] ?? '') . ' ' .
                                ($pa['kategori'] ?? '')
                            );

                            $modalPayload = json_encode([
                                "id_primer"      => (int)$pa["id_primer"],
                                "nama_komoditas" => $pa["nama_produk"],
                                "kategori"       => $pa["kategori"],
                                "satuan"         => $pa["satuan"],
                                "target"         => $rekomendasi_panen,
                                "urgensi"        => $stok < 20 ? "Kritis (< 20)" : "Waspada (< 50)"
                            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                        ?>
                        <tr class="ews-row-hover ews-mobile-card stock-data-row <?= $stok < 50 ? 'is-low-stock' : '' ?>"
                            data-level="<?= $lvl_code ?>"
                            data-search="<?= htmlspecialchars($search_blob, ENT_QUOTES) ?>"
                            style="background:<?= $stok < 50 ? '#FFFBEB' : '#FFFFFF' ?>;">
                            <td class="td-card-header">
                                <div>
                                    <div style="font-weight:800; color:#0F172A; font-size:13.5px;"><?= htmlspecialchars($pa['nama_produk']) ?></div>
                                    <div style="font-size:11.5px; color:#64748B; margin-top:2px;">
                                        <?= htmlspecialchars($pa['nama_koperasi']) ?> • <?= htmlspecialchars($pa['nama_divisi']) ?>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Sisa Stok">
                                <span style="font-size:14.5px; font-weight:800; color:<?= $stok < 50 ? '#DC2626' : '#059669' ?>; font-variant-numeric:tabular-nums;">
                                    <?= number_format($stok, 0, ',', '.') ?> <?= htmlspecialchars($pa['satuan']) ?>
                                </span>
                            </td>
                            <td data-label="Sudah Terjual" style="font-weight:700; color:#334155; font-variant-numeric:tabular-nums;">
                                <?= number_format($terjual, 0, ',', '.') ?> <?= htmlspecialchars($pa['satuan']) ?>
                            </td>
                            <td data-label="Saran Tambah">
                                <div>
                                    <span style="font-weight:800; color:#064E3B; font-variant-numeric:tabular-nums;">
                                        +<?= number_format($rekomendasi_panen, 0, ',', '.') ?> <?= htmlspecialchars($pa['satuan']) ?>
                                    </span>
                                    <div style="font-size:11px; color:#64748B;">Cadangan 2 minggu</div>
                                </div>
                            </td>
                            <td data-label="Status Stok">
                                <span style="background:<?= $lvl_bg ?>; color:<?= $lvl_col ?>; padding:4px 11px; border-radius:99px; font-size:11px; font-weight:800; display:inline-block;">
                                    <?= $lvl_txt ?>
                                </span>
                            </td>
                            <?php if ($role === 'konsumen'): ?>
                            <td data-label="Tindakan" class="td-card-action" style="text-align:right;">
                                <button type="button" onclick='openInstruksiModal(<?= $modalPayload ?>)' class="ews-btn ews-btn-emerald">
                                    <span>Minta Stok</span>
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                                </button>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                        <tr id="stockEmptyFilterRow" style="display:none;">
                            <td colspan="<?= $role === 'konsumen' ? 6 : 5 ?>" style="padding:26px; text-align:center; color:#64748B;">
                                Tidak ada produk yang cocok dengan filter status atau kata kunci pencarian.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- SECTION 2: RIWAYAT & STATUS PERMINTAAN STOK (PUSAT -> CABANG -> PETANI) -->
    <section class="ews-card" id="sectionRiwayatIns">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:10px; margin-bottom:14px;">
            <div>
                <h3 style="margin:0; font-size:16.5px; font-weight:800; color:#0F172A;">Riwayat Permintaan Stok (Pusat &rarr; Cabang &rarr; Petani)</h3>
                <div style="font-size:12.5px; color:#64748B; margin-top:2px;">Pantau apakah cabang kecamatan dan petani sudah menindaklanjuti permintaan stok dari pusat.</div>
            </div>
        </div>

        <!-- FILTER STATUS INSTRUKSI & PENCARIAN -->
        <div class="ews-toolbar">
            <div style="display:flex; gap:6px; flex-wrap:wrap;" id="insFilterBar">
                <button type="button" class="ews-pill active" onclick="filterInstruksi('ALL', this)">Semua (<?= count($instruksi_list) ?>)</button>
                <button type="button" class="ews-pill" onclick="filterInstruksi('Terkirim ke Primer', this)">Terkirim ke Cabang</button>
                <button type="button" class="ews-pill" onclick="filterInstruksi('Diteruskan ke Petani', this)">Diteruskan ke Petani</button>
                <button type="button" class="ews-pill" onclick="filterInstruksi('Terpenuhi', this)">Sudah Terpenuhi</button>
            </div>
            <input type="search" id="searchInsInput" oninput="applyInsFilters()" placeholder="Cari kode EWS, komoditas, atau cabang..." class="ews-search">
        </div>

        <div class="ews-table-wrap">
            <table class="ews-responsive-table" id="insTable">
                <thead>
                    <tr>
                        <th>Kode &amp; Waktu</th>
                        <th>Cabang Tujuan</th>
                        <th>Barang &amp; Target</th>
                        <th>Catatan Pusat</th>
                        <th>Status Pemenuhan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($instruksi_list)): ?>
                        <tr>
                            <td colspan="5" style="padding:28px; text-align:center; color:#64748B;">
                                Belum ada permintaan tambah stok yang dikirim.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($instruksi_list as $ins):
                            $st_ins = $ins['status_instruksi'];
                            $sel_bg = '#FEF3C7'; $sel_col = '#92400E'; $sel_bdr = '#FDE68A';
                            if ($st_ins === 'Diteruskan ke Petani') {
                                $sel_bg = '#DBEAFE'; $sel_col = '#1E40AF'; $sel_bdr = '#BFDBFE';
                            } elseif ($st_ins === 'Terpenuhi') {
                                $sel_bg = '#DCFCE7'; $sel_col = '#166534'; $sel_bdr = '#BBF7D0';
                            }

                            $ins_search = strtolower(
                                ($ins['kode_instruksi'] ?? '') . ' ' .
                                ($ins['nama_koperasi'] ?? '') . ' ' .
                                ($ins['kecamatan'] ?? '') . ' ' .
                                ($ins['nama_divisi'] ?? '') . ' ' .
                                ($ins['nama_komoditas'] ?? '')
                            );
                        ?>
                        <tr class="ews-row-hover ews-mobile-card ins-data-row"
                            data-status="<?= htmlspecialchars($st_ins, ENT_QUOTES) ?>"
                            data-search="<?= htmlspecialchars($ins_search, ENT_QUOTES) ?>">
                            <td class="td-card-header">
                                <div>
                                    <span class="ews-code"><?= htmlspecialchars($ins['kode_instruksi']) ?></span>
                                    <div style="font-size:11px; color:#64748B; margin-top:4px;"><?= date('d M Y, H:i', strtotime($ins['created_at'])) ?></div>
                                </div>
                            </td>
                            <td data-label="Cabang Tujuan">
                                <div>
                                    <div style="font-weight:700; color:#0F172A;"><?= htmlspecialchars($ins['nama_koperasi']) ?></div>
                                    <div style="font-size:11.5px; color:#64748B;">Kec. <?= htmlspecialchars($ins['kecamatan']) ?> (<?= htmlspecialchars($ins['nama_divisi']) ?>)</div>
                                </div>
                            </td>
                            <td data-label="Barang & Target">
                                <div>
                                    <div style="font-weight:800; color:#0F172A;"><?= htmlspecialchars($ins['nama_komoditas']) ?></div>
                                    <div style="font-size:12px; color:#D97706; font-weight:700; font-variant-numeric:tabular-nums;">
                                        Target: <?= number_format((int)$ins['target_jumlah'], 0, ',', '.') ?> <?= htmlspecialchars($ins['satuan']) ?>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Catatan" style="font-size:12px; color:#475569; max-width:250px;">
                                <?= htmlspecialchars($ins['catatan_pusat'] ?: 'Segera koordinasikan panen dengan kelompok tani binaan.') ?>
                            </td>
                            <td data-label="Ubah Status Progres" class="td-card-action">
                                <?php if (in_array($role, ['konsumen', 'primer'], true)): ?>
                                    <form method="POST" style="margin:0;">
                                        <?= $csrf_input ?>
                                        <input type="hidden" name="aksi" value="update_progres">
                                        <input type="hidden" name="id_instruksi" value="<?= (int)$ins['id_instruksi'] ?>">
                                        <select name="status_instruksi" onchange="this.form.submit()" style="padding:7px 11px; border-radius:9px; border:1px solid <?= $sel_bdr ?>; font-family:inherit; font-size:12px; font-weight:800; background:<?= $sel_bg ?>; color:<?= $sel_col ?>; cursor:pointer; outline:none;">
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
                        <tr id="insEmptyFilterRow" style="display:none;">
                            <td colspan="5" style="padding:26px; text-align:center; color:#64748B;">
                                Tidak ada riwayat permintaan stok yang cocok dengan filter atau kata kunci pencarian.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

</div>

<?php if ($role === 'konsumen'): ?>
<!-- MODAL KIRIM PERMINTAAN TAMBAH STOK KE CABANG & PETANI (ZERO LAG) -->
<div id="modalInstruksi" class="ews-modal-overlay" onclick="if(event.target===this) closeInstruksiModal()">
    <div class="ews-modal-box">
        <div class="ews-modal-head">
            <div>
                <div style="font-weight:800; font-size:15.5px;">Minta Tambah Stok ke Cabang</div>
                <div style="font-size:11.5px; color:#A7F3D0; margin-top:2px;">Cabang kecamatan akan meneruskan surat tugas restock ke petani mitra.</div>
            </div>
            <button type="button" onclick="closeInstruksiModal()" style="background:rgba(255,255,255,0.12); border:none; width:30px; height:30px; border-radius:8px; font-size:18px; cursor:pointer; color:#FFFFFF;">&times;</button>
        </div>

        <form method="POST" style="padding:20px 22px 22px; display:flex; flex-direction:column; gap:13px;">
            <?= $csrf_input ?>
            <input type="hidden" name="aksi" value="kirim_instruksi">

            <div>
                <label class="ews-label" for="ins_id_primer">Pilih Cabang Koperasi Kecamatan *</label>
                <select name="id_primer" id="ins_id_primer" required class="ews-input">
                    <?php if (empty($primer_list)): ?>
                        <option value="">-- Belum ada Cabang Koperasi Primer --</option>
                    <?php else: ?>
                        <?php foreach ($primer_list as $p): ?>
                            <option value="<?= (int)$p['id_primer'] ?>">
                                <?= htmlspecialchars($p['nama_koperasi']) ?> — Kec. <?= htmlspecialchars($p['kecamatan']) ?> (<?= htmlspecialchars($p['nama_divisi']) ?>)
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <div class="ews-form-row-2">
                <div>
                    <label class="ews-label" for="ins_nama_komoditas">Nama Barang / Komoditas *</label>
                    <input type="text" name="nama_komoditas" id="ins_nama_komoditas" placeholder="Contoh: Kentang Granola" required class="ews-input">
                </div>
                <div>
                    <label class="ews-label" for="ins_kategori">Kategori *</label>
                    <select name="kategori" id="ins_kategori" class="ews-input">
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
                    <label class="ews-label" for="ins_target">Jumlah Target *</label>
                    <input type="number" name="target_jumlah" id="ins_target" value="150" min="1" required class="ews-input">
                </div>
                <div>
                    <label class="ews-label" for="ins_satuan">Satuan *</label>
                    <input type="text" name="satuan" id="ins_satuan" value="Kg" required class="ews-input">
                </div>
                <div>
                    <label class="ews-label" for="ins_urgensi" style="color:#DC2626;">Tingkat Prioritas *</label>
                    <select name="tingkat_urgensi" id="ins_urgensi" class="ews-input">
                        <option value="Kritis (< 20)">Kritis (&lt; 20)</option>
                        <option value="Waspada (< 50)" selected>Waspada (&lt; 50)</option>
                        <option value="Prediksi Musiman">Prediksi Musiman</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="ews-label" for="ins_catatan">Catatan untuk Cabang &amp; Petani</label>
                <textarea name="catatan_pusat" id="ins_catatan" rows="2" placeholder="Tulis pesan jadwal panen, kualitas barang, atau target pengiriman..." class="ews-input"></textarea>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:4px;">
                <button type="button" onclick="closeInstruksiModal()" style="background:#F1F5F9; color:#334155; border:1px solid #CBD5E1; padding:10px 16px; border-radius:10px; font-family:inherit; font-size:13px; font-weight:700; cursor:pointer;">Batal</button>
                <button type="submit" class="ews-btn" style="background:#064E3B; color:#FFFFFF; padding:10px 18px; border-radius:10px; font-size:13px;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                    <span>Kirim Permintaan Sekarang</span>
                </button>
            </div>
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
    modal.classList.add('show');
}

function closeInstruksiModal() {
    const modal = document.getElementById('modalInstruksi');
    if (modal) modal.classList.remove('show');
}

// Filter & Pencarian Pantauan Stok (Zero-Lag via data-search)
let currentStockLevel = 'ALL';

function filterStocks(level, btn) {
    currentStockLevel = level;
    document.querySelectorAll('#stockFilterBar .ews-pill').forEach(function(el) {
        el.classList.remove('active');
    });
    if (btn) btn.classList.add('active');
    applyStockFilters();
}

function quickSelectStockFilter(level) {
    const btn = document.querySelector('#stockFilterBar .ews-pill[data-filter="' + level + '"]');
    filterStocks(level, btn);
    const sec = document.getElementById('sectionPantauanStok');
    if (sec) sec.scrollIntoView({ behavior: 'smooth' });
}

function applyStockFilters() {
    const q = (document.getElementById('searchStockInput')?.value || '').toLowerCase().trim();
    const rows = document.querySelectorAll('.stock-data-row');
    const emptyRow = document.getElementById('stockEmptyFilterRow');
    let visibleCount = 0;

    rows.forEach(function(row) {
        const lvl = row.getAttribute('data-level');
        const txt = row.getAttribute('data-search') || '';
        const matchLvl  = (currentStockLevel === 'ALL' || lvl === currentStockLevel);
        const matchText = !q || txt.indexOf(q) !== -1;
        const show = matchLvl && matchText;
        row.style.display = show ? '' : 'none';
        if (show) visibleCount++;
    });

    if (emptyRow) {
        emptyRow.style.display = (rows.length > 0 && visibleCount === 0) ? '' : 'none';
    }
}

// Filter & Pencarian Riwayat Permintaan Stok
let currentInsStatus = 'ALL';

function filterInstruksi(status, btn) {
    currentInsStatus = status;
    document.querySelectorAll('#insFilterBar .ews-pill').forEach(function(el) {
        el.classList.remove('active');
    });
    if (btn) btn.classList.add('active');
    applyInsFilters();
}

function applyInsFilters() {
    const q = (document.getElementById('searchInsInput')?.value || '').toLowerCase().trim();
    const rows = document.querySelectorAll('.ins-data-row');
    const emptyRow = document.getElementById('insEmptyFilterRow');
    let visibleCount = 0;

    rows.forEach(function(row) {
        const st  = row.getAttribute('data-status');
        const txt = row.getAttribute('data-search') || '';
        const matchSt   = (currentInsStatus === 'ALL' || st === currentInsStatus);
        const matchText = !q || txt.indexOf(q) !== -1;
        const show = matchSt && matchText;
        row.style.display = show ? '' : 'none';
        if (show) visibleCount++;
    });

    if (emptyRow) {
        emptyRow.style.display = (rows.length > 0 && visibleCount === 0) ? '' : 'none';
    }
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeInstruksiModal();
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