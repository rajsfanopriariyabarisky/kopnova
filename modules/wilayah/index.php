<?php
require_once '../../config/database.php';
cekAkses(['konsumen']);
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

// Ambil daftar Divisi Kabupaten/Kota beserta jumlah Koperasi Primer di bawahnya
$divisi_list = $pdo->query("
    SELECT d.*, COUNT(p.id_primer) AS total_primer
    FROM divisi_wilayah d
    LEFT JOIN koperasi_primer p ON d.id_divisi = p.id_divisi
    GROUP BY d.id_divisi
    ORDER BY d.id_divisi DESC
")->fetchAll();

// Ambil daftar Koperasi Primer lintas wilayah
$primer_list = $pdo->query("
    SELECT p.*, d.nama_divisi, d.kode_divisi, d.provinsi
    FROM koperasi_primer p
    JOIN divisi_wilayah d ON p.id_divisi = d.id_divisi
    ORDER BY p.id_primer DESC
")->fetchAll();

// Hitung ringkasan statistik wilayah
$total_divisi   = count($divisi_list);
$total_primer   = count($primer_list);
$total_provinsi = count(array_unique(array_filter(array_column($divisi_list, 'provinsi'))));

// Siapkan input CSRF agar sinkron dengan verify_csrf() di proses.php
$csrf_input = '';
if (function_exists('csrf_field')) {
    $csrf_input = csrf_field();
} elseif (!empty($_SESSION['csrf_token'])) {
    $csrf_input = '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') . '">';
}
?>

<style>
    .wil-wrap {
        display: flex;
        flex-direction: column;
        gap: 22px;
        color: #0F172A;
    }

    /* HERO BANNER KONSISTEN DENGAN DASHBOARD KOPNOVA */
    .wil-hero {
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
    .wil-hero-eyebrow {
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
    .wil-hero-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: #34D399;
    }
    .wil-hero-title {
        margin: 0 0 6px;
        font-size: 22px;
        font-weight: 800;
        letter-spacing: -0.3px;
        line-height: 1.25;
    }
    .wil-hero-sub {
        margin: 0;
        font-size: 13.5px;
        color: rgba(236, 253, 245, 0.88);
        max-width: 640px;
        line-height: 1.5;
    }
    .wil-hero-stats {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }
    .wil-stat-pill {
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.18);
        border-radius: 13px;
        padding: 10px 15px;
        min-width: 105px;
    }
    .wil-stat-num {
        font-size: 18px;
        font-weight: 800;
        color: #FFFFFF;
        line-height: 1.15;
    }
    .wil-stat-lbl {
        font-size: 11px;
        font-weight: 600;
        color: #A7F3D0;
        margin-top: 2px;
    }

    /* ALERT NOTIFIKASI */
    .wil-alert {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 13px 16px;
        border-radius: 12px;
        font-size: 13.5px;
        font-weight: 600;
    }
    .wil-alert-success {
        background: #DCFCE7;
        border: 1px solid #86EFAC;
        color: #166534;
    }
    .wil-alert-error {
        background: #FEE2E2;
        border: 1px solid #FCA5A5;
        color: #991B1B;
    }
    .wil-alert-close {
        background: none;
        border: none;
        color: inherit;
        font-size: 16px;
        font-weight: 800;
        cursor: pointer;
        padding: 0 4px;
        opacity: 0.75;
    }
    .wil-alert-close:hover {
        opacity: 1;
    }

    /* GRID 2 KOLOM FORM INPUT */
    .wil-form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(310px, 1fr));
        gap: 20px;
    }

    .wil-card {
        background: #FFFFFF;
        border: 1px solid #E2E8F0;
        border-radius: 16px;
        padding: 22px;
        box-shadow: 0 2px 10px rgba(15, 23, 42, 0.03);
    }

    .wil-card-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 16px;
        padding-bottom: 14px;
        border-bottom: 1px solid #F1F5F9;
    }
    .wil-step-badge {
        display: inline-block;
        font-size: 10.5px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        padding: 4px 10px;
        border-radius: 99px;
        margin-bottom: 6px;
    }
    .wil-step-emerald {
        background: #DCFCE7;
        color: #166534;
    }
    .wil-step-amber {
        background: #FEF3C7;
        color: #92400E;
    }
    .wil-card-title {
        margin: 0;
        font-size: 16.5px;
        font-weight: 800;
        color: #0F172A;
    }
    .wil-card-desc {
        margin: 3px 0 0;
        font-size: 12.5px;
        color: #64748B;
    }

    /* FORM FIELDS */
    .wil-form {
        display: flex;
        flex-direction: column;
        gap: 13px;
    }
    .wil-row-2 {
        display: grid;
        grid-template-columns: 1fr 1.6fr;
        gap: 12px;
    }
    .wil-field {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }
    .wil-label {
        font-size: 12px;
        font-weight: 700;
        color: #334155;
    }
    .wil-input, .wil-select {
        width: 100%;
        box-sizing: border-box;
        padding: 10px 13px;
        border: 1px solid #CBD5E1;
        border-radius: 10px;
        font-family: inherit;
        font-size: 13.5px;
        color: #0F172A;
        background: #FFFFFF;
        transition: border-color 0.16s ease, box-shadow 0.16s ease;
    }
    .wil-input:focus, .wil-select:focus {
        outline: none;
        border-color: #10B981;
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.14);
    }
    .wil-uppercase {
        text-transform: uppercase;
        font-weight: 700;
        letter-spacing: 0.4px;
    }

    /* TOMBOL AKSI */
    .wil-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 11px 16px;
        border-radius: 10px;
        border: none;
        font-family: inherit;
        font-size: 13.5px;
        font-weight: 700;
        cursor: pointer;
        transition: background-color 0.16s ease, transform 0.14s ease;
    }
    .wil-btn:active {
        transform: scale(0.98);
    }
    .wil-btn-emerald {
        background: #064E3B;
        color: #FFFFFF;
    }
    .wil-btn-emerald:hover {
        background: #042F24;
    }
    .wil-btn-amber {
        background: #D97706;
        color: #FFFFFF;
    }
    .wil-btn-amber:hover {
        background: #B45309;
    }
    .wil-btn-ghost {
        background: #F1F5F9;
        color: #334155;
        border: 1px solid #CBD5E1;
    }
    .wil-btn-ghost:hover {
        background: #E2E8F0;
    }

    /* CHIP DAFTAR DIVISI KABUPATEN/KOTA */
    .wil-divisi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 12px;
    }
    .wil-divisi-item {
        background: #F8FAFC;
        border: 1px solid #E2E8F0;
        border-radius: 12px;
        padding: 13px 15px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        gap: 10px;
        transition: border-color 0.16s ease, background-color 0.16s ease;
    }
    .wil-divisi-item:hover {
        border-color: #10B981;
        background: #F0FDF4;
    }

    /* TOOLBAR FILTER TABEL */
    .wil-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 16px;
    }
    .wil-filter-group {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    /* TABEL RESPONSIVE */
    .wil-table-wrap {
        width: 100%;
        overflow-x: auto;
        border: 1px solid #E2E8F0;
        border-radius: 12px;
    }
    .wil-table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
        font-size: 13.5px;
        min-width: 720px;
    }
    .wil-table thead tr {
        background: #F8FAFC;
        border-bottom: 1.5px solid #E2E8F0;
    }
    .wil-table th {
        padding: 12px 14px;
        font-size: 11.5px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #475569;
        white-space: nowrap;
    }
    .wil-table td {
        padding: 13px 14px;
        border-bottom: 1px solid #F1F5F9;
        color: #1E293B;
        vertical-align: middle;
    }
    .wil-table tbody tr:last-child td {
        border-bottom: none;
    }
    .wil-table tbody tr:hover {
        background: #F8FAFC;
    }

    /* BADGES & ICON BUTTONS */
    .wil-code-pill {
        display: inline-block;
        padding: 3px 9px;
        border-radius: 7px;
        background: #F1F5F9;
        border: 1px solid #CBD5E1;
        color: #0F172A;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: 0.3px;
    }
    .wil-prov-pill {
        display: inline-block;
        padding: 3px 9px;
        border-radius: 99px;
        background: #ECFDF5;
        border: 1px solid #A7F3D0;
        color: #065F46;
        font-size: 11.5px;
        font-weight: 700;
    }
    .wil-act-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 6px 10px;
        border-radius: 8px;
        font-family: inherit;
        font-size: 12px;
        font-weight: 700;
        border: 1px solid transparent;
        cursor: pointer;
        text-decoration: none;
        transition: background-color 0.15s ease;
    }
    .wil-act-edit {
        background: #EFF6FF;
        border-color: #BFDBFE;
        color: #1D4ED8;
    }
    .wil-act-edit:hover {
        background: #DBEAFE;
    }
    .wil-act-del {
        background: #FEF2F2;
        border-color: #FECACA;
        color: #DC2626;
    }
    .wil-act-del:hover {
        background: #FEE2E2;
    }

    /* MODAL RINGAN (TANPA BLUR BERAT AGAR ZERO LAG) */
    .wil-modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.55);
        z-index: 999;
        align-items: center;
        justify-content: center;
        padding: 16px;
    }
    .wil-modal-overlay.show {
        display: flex;
    }
    .wil-modal-box {
        background: #FFFFFF;
        border: 1px solid #E2E8F0;
        border-radius: 16px;
        width: 100%;
        max-width: 460px;
        overflow: hidden;
        box-shadow: 0 20px 40px rgba(15, 23, 42, 0.22);
    }
    .wil-modal-head {
        background: #042F24;
        color: #FFFFFF;
        padding: 15px 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    @media (max-width: 640px) {
        .wil-hero {
            padding: 20px;
        }
        .wil-hero-title {
            font-size: 19px;
        }
        .wil-row-2 {
            grid-template-columns: 1fr;
        }
        .wil-stat-pill {
            flex: 1;
        }
    }
</style>

<div class="wil-wrap">

    <!-- HERO HEADER -->
    <section class="wil-hero">
        <div>
            <div class="wil-hero-eyebrow">
                <span class="wil-hero-dot"></span>
                <span>TATA KELOLA WILAYAH &amp; JARINGAN UNIT</span>
            </div>
            <h2 class="wil-hero-title">Manajemen Wilayah Universal &amp; Koperasi Primer</h2>
            <p class="wil-hero-sub">
                Kelola struktur Divisi Kabupaten/Kota beserta unit Koperasi Primer tingkat Kecamatan untuk pemetaan pasokan dan distribusi rantai pasok nasional.
            </p>
        </div>

        <div class="wil-hero-stats">
            <div class="wil-stat-pill">
                <div class="wil-stat-num"><?= $total_divisi ?></div>
                <div class="wil-stat-lbl">Divisi Kab/Kota</div>
            </div>
            <div class="wil-stat-pill">
                <div class="wil-stat-num"><?= $total_primer ?></div>
                <div class="wil-stat-lbl">Koperasi Primer</div>
            </div>
            <div class="wil-stat-pill">
                <div class="wil-stat-num"><?= $total_provinsi ?></div>
                <div class="wil-stat-lbl">Provinsi Aktif</div>
            </div>
        </div>
    </section>

    <!-- ALERT NOTIFIKASI SUKSES / ERROR -->
    <?php if (isset($_GET['msg']) && $_GET['msg'] !== ''): ?>
        <div class="wil-alert wil-alert-success" id="wilAlertBox">
            <span>✓ <?= htmlspecialchars($_GET['msg']) ?></span>
            <button type="button" class="wil-alert-close" onclick="this.parentElement.remove()" aria-label="Tutup">&times;</button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['err']) && $_GET['err'] !== ''): ?>
        <div class="wil-alert wil-alert-error" id="wilAlertBox">
            <span>! <?= htmlspecialchars($_GET['err']) ?></span>
            <button type="button" class="wil-alert-close" onclick="this.parentElement.remove()" aria-label="Tutup">&times;</button>
        </div>
    <?php endif; ?>

    <!-- FORM INPUT 2 KOLOM -->
    <section class="wil-form-grid">
        <!-- 1. FORM TAMBAH DIVISI KABUPATEN/KOTA -->
        <div class="wil-card">
            <div class="wil-card-head">
                <div>
                    <span class="wil-step-badge wil-step-emerald">Langkah 1 • Wilayah Induk</span>
                    <h3 class="wil-card-title">Tambah Divisi Kabupaten / Kota</h3>
                    <p class="wil-card-desc">Daftarkan divisi koordinator tingkat kabupaten atau kota baru.</p>
                </div>
            </div>

            <form action="proses.php" method="POST" class="wil-form">
                <?= $csrf_input ?>
                <input type="hidden" name="aksi" value="tambah_divisi">

                <div class="wil-row-2">
                    <div class="wil-field">
                        <label class="wil-label" for="kode_divisi">Kode Divisi *</label>
                        <input type="text" id="kode_divisi" name="kode_divisi" class="wil-input wil-uppercase" placeholder="DIV-TSM" maxlength="20" required>
                    </div>
                    <div class="wil-field">
                        <label class="wil-label" for="provinsi">Provinsi *</label>
                        <input type="text" id="provinsi" name="provinsi" class="wil-input" placeholder="Contoh: Jawa Barat" required>
                    </div>
                </div>

                <div class="wil-field">
                    <label class="wil-label" for="nama_divisi">Nama Divisi Kabupaten / Kota *</label>
                    <input type="text" id="nama_divisi" name="nama_divisi" class="wil-input" placeholder="Contoh: Divisi Kabupaten Tasikmalaya" required>
                </div>

                <button type="submit" class="wil-btn wil-btn-emerald" style="margin-top:4px;">
                    + Simpan Divisi Wilayah
                </button>
            </form>
        </div>

        <!-- 2. FORM TAMBAH KOPERASI PRIMER KECAMATAN -->
        <div class="wil-card">
            <div class="wil-card-head">
                <div>
                    <span class="wil-step-badge wil-step-amber">Langkah 2 • Unit Cabang</span>
                    <h3 class="wil-card-title">Tambah Koperasi Primer Kecamatan</h3>
                    <p class="wil-card-desc">Hubungkan unit koperasi kecamatan ke divisi kabupaten terkait.</p>
                </div>
            </div>

            <form action="proses.php" method="POST" class="wil-form">
                <?= $csrf_input ?>
                <input type="hidden" name="aksi" value="tambah_primer">

                <div class="wil-field">
                    <label class="wil-label" for="id_divisi">Divisi Kabupaten / Kota Induk *</label>
                    <select id="id_divisi" name="id_divisi" class="wil-select" required>
                        <option value="">-- Pilih Divisi Kabupaten/Kota --</option>
                        <?php foreach ($divisi_list as $d): ?>
                            <option value="<?= (int)$d['id_divisi'] ?>">
                                <?= htmlspecialchars($d['nama_divisi']) ?> (<?= htmlspecialchars($d['provinsi']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="wil-row-2">
                    <div class="wil-field">
                        <label class="wil-label" for="kode_primer">Kode Unit *</label>
                        <input type="text" id="kode_primer" name="kode_primer" class="wil-input wil-uppercase" placeholder="PRM-SMR" maxlength="20" required>
                    </div>
                    <div class="wil-field">
                        <label class="wil-label" for="kecamatan">Nama Kecamatan *</label>
                        <input type="text" id="kecamatan" name="kecamatan" class="wil-input" placeholder="Contoh: Samarang" required>
                    </div>
                </div>

                <div class="wil-row-2" style="grid-template-columns: 1.2fr 1fr;">
                    <div class="wil-field">
                        <label class="wil-label" for="nama_koperasi">Nama Koperasi Primer *</label>
                        <input type="text" id="nama_koperasi" name="nama_koperasi" class="wil-input" placeholder="Koperasi Primer Samarang" required>
                    </div>
                    <div class="wil-field">
                        <label class="wil-label" for="ketua_koperasi">Ketua Pengurus *</label>
                        <input type="text" id="ketua_koperasi" name="ketua_koperasi" class="wil-input" placeholder="Nama Ketua" required>
                    </div>
                </div>

                <button type="submit" class="wil-btn wil-btn-amber" style="margin-top:4px;">
                    + Daftarkan Koperasi Primer
                </button>
            </form>
        </div>
    </section>

    <!-- RINGKASAN DIVISI KABUPATEN / KOTA TERDAFTAR -->
    <section class="wil-card">
        <div class="wil-card-head" style="margin-bottom:14px;">
            <div>
                <h3 class="wil-card-title">Daftar Divisi Kabupaten / Kota Terdaftar</h3>
                <p class="wil-card-desc">Klik tombol ubah untuk memperbarui data divisi atau hapus jika belum memiliki unit cabang.</p>
            </div>
            <span class="wil-prov-pill"><?= $total_divisi ?> Divisi Aktif</span>
        </div>

        <?php if (empty($divisi_list)): ?>
            <div style="text-align:center; padding:26px; color:#64748B; font-size:13.5px; background:#F8FAFC; border-radius:12px; border:1px dashed #CBD5E1;">
                Belum ada data Divisi Kabupaten/Kota. Silakan tambahkan melalui formulir di atas.
            </div>
        <?php else: ?>
            <div class="wil-divisi-grid">
                <?php foreach ($divisi_list as $d): ?>
                    <div class="wil-divisi-item">
                        <div>
                            <div style="display:flex; align-items:center; justify-content:space-between; gap:8px; margin-bottom:6px;">
                                <span class="wil-code-pill"><?= htmlspecialchars($d['kode_divisi']) ?></span>
                                <span style="font-size:11.5px; font-weight:700; color:#047857;">
                                    <?= (int)$d['total_primer'] ?> Unit Primer
                                </span>
                            </div>
                            <div style="font-size:14px; font-weight:800; color:#0F172A; line-height:1.3;">
                                <?= htmlspecialchars($d['nama_divisi']) ?>
                            </div>
                            <div style="font-size:12px; color:#64748B; margin-top:2px;">
                                Provinsi <?= htmlspecialchars($d['provinsi']) ?>
                            </div>
                        </div>

                        <div style="display:flex; align-items:center; gap:6px; padding-top:8px; border-top:1px solid #E2E8F0;">
                            <button type="button" class="wil-act-btn wil-act-edit" style="flex:1;"
                                onclick='openEditDivisi(<?= json_encode([
                                    "id_divisi"   => (int)$d["id_divisi"],
                                    "kode_divisi" => $d["kode_divisi"],
                                    "nama_divisi" => $d["nama_divisi"],
                                    "provinsi"    => $d["provinsi"]
                                ], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                Edit Divisi
                            </button>

                            <form action="proses.php" method="POST" style="margin:0;" onsubmit="return confirm('Hapus divisi <?= htmlspecialchars(addslashes($d['nama_divisi'])) ?>?');">
                                <?= $csrf_input ?>
                                <input type="hidden" name="aksi" value="hapus_divisi">
                                <input type="hidden" name="id_divisi" value="<?= (int)$d['id_divisi'] ?>">
                                <button type="submit" class="wil-act-btn wil-act-del" title="Hapus Divisi">Hapus</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- TABEL DAFTAR JARINGAN KOPERASI PRIMER LINTAS WILAYAH -->
    <section class="wil-card">
        <div class="wil-toolbar">
            <div>
                <h3 class="wil-card-title">Daftar Jaringan Koperasi Primer Lintas Wilayah</h3>
                <p class="wil-card-desc">Menampilkan seluruh unit koperasi tingkat kecamatan yang aktif di jaringan KOPNOVA.</p>
            </div>

            <div class="wil-filter-group">
                <select id="filterDivisiSelect" class="wil-select" style="width:auto; min-width:200px; padding:8.5px 12px; font-size:13px;" onchange="filterPrimerTable()">
                    <option value="">Semua Divisi Kabupaten</option>
                    <?php foreach ($divisi_list as $d): ?>
                        <option value="<?= (int)$d['id_divisi'] ?>"><?= htmlspecialchars($d['nama_divisi']) ?></option>
                    <?php endforeach; ?>
                </select>

                <input type="search" id="searchPrimerInput" class="wil-input" style="width:230px; padding:8.5px 12px; font-size:13px;" placeholder="Cari kecamatan, kode, ketua..." oninput="filterPrimerTable()">
            </div>
        </div>

        <div class="wil-table-wrap">
            <table class="wil-table" id="primerTable">
                <thead>
                    <tr>
                        <th style="width:46px;">No</th>
                        <th>Divisi Kabupaten</th>
                        <th>Kecamatan</th>
                        <th>Kode Unit</th>
                        <th>Nama Koperasi Primer</th>
                        <th>Ketua Pengurus</th>
                        <th style="text-align:right;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($primer_list)): ?>
                        <tr>
                            <td colspan="7" style="text-align:center; padding:28px; color:#64748B;">
                                Belum ada unit Koperasi Primer yang terdaftar.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($primer_list as $idx => $pr): ?>
                            <tr data-divisi="<?= (int)$pr['id_divisi'] ?>"
                                data-search="<?= htmlspecialchars(strtolower($pr['nama_divisi'] . ' ' . $pr['kecamatan'] . ' ' . $pr['kode_primer'] . ' ' . $pr['nama_koperasi'] . ' ' . $pr['ketua_koperasi'])) ?>">
                                <td style="color:#64748B; font-weight:700;"><?= $idx + 1 ?></td>
                                <td>
                                    <div style="font-weight:700; color:#047857;"><?= htmlspecialchars($pr['nama_divisi']) ?></div>
                                    <div style="font-size:11.5px; color:#64748B;"><?= htmlspecialchars($pr['provinsi']) ?></div>
                                </td>
                                <td style="font-weight:600;"><?= htmlspecialchars($pr['kecamatan']) ?></td>
                                <td><span class="wil-code-pill"><?= htmlspecialchars($pr['kode_primer']) ?></span></td>
                                <td style="font-weight:700; color:#0F172A;"><?= htmlspecialchars($pr['nama_koperasi']) ?></td>
                                <td><?= htmlspecialchars($pr['ketua_koperasi']) ?></td>
                                <td style="text-align:right; white-space:nowrap;">
                                    <button type="button" class="wil-act-btn wil-act-edit"
                                        onclick='openEditPrimer(<?= json_encode([
                                            "id_primer"      => (int)$pr["id_primer"],
                                            "id_divisi"      => (int)$pr["id_divisi"],
                                            "kode_primer"    => $pr["kode_primer"],
                                            "kecamatan"      => $pr["kecamatan"],
                                            "nama_koperasi"  => $pr["nama_koperasi"],
                                            "ketua_koperasi" => $pr["ketua_koperasi"]
                                        ], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                        Edit
                                    </button>

                                    <form action="proses.php" method="POST" style="display:inline; margin-left:4px;" onsubmit="return confirm('Hapus unit <?= htmlspecialchars(addslashes($pr['nama_koperasi'])) ?>?');">
                                        <?= $csrf_input ?>
                                        <input type="hidden" name="aksi" value="hapus_primer">
                                        <input type="hidden" name="id_primer" value="<?= (int)$pr['id_primer'] ?>">
                                        <button type="submit" class="wil-act-btn wil-act-del">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr id="primerEmptyFilterRow" style="display:none;">
                            <td colspan="7" style="text-align:center; padding:24px; color:#64748B;">
                                Tidak ada Koperasi Primer yang cocok dengan filter pencarian.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

</div>

<!-- MODAL EDIT DIVISI KABUPATEN / KOTA -->
<div id="modalEditDivisi" class="wil-modal-overlay" onclick="if(event.target===this) closeModal('modalEditDivisi')">
    <div class="wil-modal-box">
        <div class="wil-modal-head">
            <strong style="font-size:15px;">Edit Divisi Kabupaten / Kota</strong>
            <button type="button" onclick="closeModal('modalEditDivisi')" style="background:none; border:none; color:#FFFFFF; font-size:20px; cursor:pointer;">&times;</button>
        </div>
        <form action="proses.php" method="POST" class="wil-form" style="padding:20px;">
            <?= $csrf_input ?>
            <input type="hidden" name="aksi" value="edit_divisi">
            <input type="hidden" name="id_divisi" id="edit_id_divisi">

            <div class="wil-field">
                <label class="wil-label">Kode Divisi *</label>
                <input type="text" name="kode_divisi" id="edit_kode_divisi" class="wil-input wil-uppercase" required>
            </div>
            <div class="wil-field">
                <label class="wil-label">Nama Divisi Kabupaten / Kota *</label>
                <input type="text" name="nama_divisi" id="edit_nama_divisi" class="wil-input" required>
            </div>
            <div class="wil-field">
                <label class="wil-label">Provinsi *</label>
                <input type="text" name="provinsi" id="edit_provinsi" class="wil-input" required>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:6px;">
                <button type="button" class="wil-btn wil-btn-ghost" onclick="closeModal('modalEditDivisi')">Batal</button>
                <button type="submit" class="wil-btn wil-btn-emerald">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDIT KOPERASI PRIMER -->
<div id="modalEditPrimer" class="wil-modal-overlay" onclick="if(event.target===this) closeModal('modalEditPrimer')">
    <div class="wil-modal-box">
        <div class="wil-modal-head">
            <strong style="font-size:15px;">Edit Koperasi Primer Kecamatan</strong>
            <button type="button" onclick="closeModal('modalEditPrimer')" style="background:none; border:none; color:#FFFFFF; font-size:20px; cursor:pointer;">&times;</button>
        </div>
        <form action="proses.php" method="POST" class="wil-form" style="padding:20px;">
            <?= $csrf_input ?>
            <input type="hidden" name="aksi" value="edit_primer">
            <input type="hidden" name="id_primer" id="edit_id_primer">

            <div class="wil-field">
                <label class="wil-label">Divisi Kabupaten / Kota *</label>
                <select name="id_divisi" id="edit_primer_divisi" class="wil-select" required>
                    <?php foreach ($divisi_list as $d): ?>
                        <option value="<?= (int)$d['id_divisi'] ?>"><?= htmlspecialchars($d['nama_divisi']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="wil-row-2">
                <div class="wil-field">
                    <label class="wil-label">Kode Unit *</label>
                    <input type="text" name="kode_primer" id="edit_kode_primer" class="wil-input wil-uppercase" required>
                </div>
                <div class="wil-field">
                    <label class="wil-label">Kecamatan *</label>
                    <input type="text" name="kecamatan" id="edit_kecamatan" class="wil-input" required>
                </div>
            </div>
            <div class="wil-field">
                <label class="wil-label">Nama Koperasi Primer *</label>
                <input type="text" name="nama_koperasi" id="edit_nama_koperasi" class="wil-input" required>
            </div>
            <div class="wil-field">
                <label class="wil-label">Ketua Pengurus *</label>
                <input type="text" name="ketua_koperasi" id="edit_ketua_koperasi" class="wil-input" required>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:6px;">
                <button type="button" class="wil-btn wil-btn-ghost" onclick="closeModal('modalEditPrimer')">Batal</button>
                <button type="submit" class="wil-btn wil-btn-amber">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
    function filterPrimerTable() {
        const divVal = document.getElementById('filterDivisiSelect').value;
        const q = document.getElementById('searchPrimerInput').value.toLowerCase().trim();
        const rows = document.querySelectorAll('#primerTable tbody tr[data-divisi]');
        const emptyRow = document.getElementById('primerEmptyFilterRow');
        let visibleCount = 0;

        rows.forEach(function (row) {
            const matchDiv = !divVal || row.getAttribute('data-divisi') === divVal;
            const matchTxt = !q || row.getAttribute('data-search').indexOf(q) !== -1;
            const show = matchDiv && matchTxt;
            row.style.display = show ? '' : 'none';
            if (show) visibleCount++;
        });

        if (emptyRow) {
            emptyRow.style.display = (rows.length > 0 && visibleCount === 0) ? '' : 'none';
        }
    }

    function openEditDivisi(data) {
        document.getElementById('edit_id_divisi').value = data.id_divisi;
        document.getElementById('edit_kode_divisi').value = data.kode_divisi;
        document.getElementById('edit_nama_divisi').value = data.nama_divisi;
        document.getElementById('edit_provinsi').value = data.provinsi;
        document.getElementById('modalEditDivisi').classList.add('show');
    }

    function openEditPrimer(data) {
        document.getElementById('edit_id_primer').value = data.id_primer;
        document.getElementById('edit_primer_divisi').value = data.id_divisi;
        document.getElementById('edit_kode_primer').value = data.kode_primer;
        document.getElementById('edit_kecamatan').value = data.kecamatan;
        document.getElementById('edit_nama_koperasi').value = data.nama_koperasi;
        document.getElementById('edit_ketua_koperasi').value = data.ketua_koperasi;
        document.getElementById('modalEditPrimer').classList.add('show');
    }

    function closeModal(id) {
        const el = document.getElementById(id);
        if (el) el.classList.remove('show');
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeModal('modalEditDivisi');
            closeModal('modalEditPrimer');
        }
    });
</script>

<?php require_once '../../includes/footer.php'; ?>