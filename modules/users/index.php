<?php
require_once '../../config/database.php';
cekAkses(['konsumen']);
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

$current_user_id = (int)($user['id_user'] ?? ($_SESSION['user']['id_user'] ?? 0));

// Ambil data Divisi Wilayah & Koperasi Primer untuk dropdown dan filter
$divisi_list = $pdo->query("SELECT * FROM divisi_wilayah ORDER BY nama_divisi ASC")->fetchAll();
$primer_list = $pdo->query("
    SELECT p.*, d.nama_divisi
    FROM koperasi_primer p
    JOIN divisi_wilayah d ON p.id_divisi = d.id_divisi
    ORDER BY d.nama_divisi ASC, p.kecamatan ASC
")->fetchAll();

// Ambil semua user beserta relasi wilayahnya
$users_list = $pdo->query("
    SELECT u.*, d.nama_divisi, p.nama_koperasi, p.kecamatan
    FROM users u
    LEFT JOIN divisi_wilayah d ON u.id_divisi = d.id_divisi
    LEFT JOIN koperasi_primer p ON u.id_primer = p.id_primer
    ORDER BY FIELD(u.role, 'konsumen', 'primer', 'petani', 'buyer'), u.id_user DESC
")->fetchAll();

// Hitung ringkasan per role
$count_role = ['konsumen' => 0, 'primer' => 0, 'petani' => 0, 'buyer' => 0];
foreach ($users_list as $u) {
    if (isset($count_role[$u['role']])) {
        $count_role[$u['role']]++;
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
    .usr-wrap {
        display: flex;
        flex-direction: column;
        gap: 22px;
        color: #0F172A;
    }

    /* HERO HEADER KONSISTEN DENGAN KOPNOVA */
    .usr-hero {
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
    .usr-eyebrow {
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
    .usr-eyebrow-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: #34D399;
    }
    .usr-hero-title {
        margin: 0 0 6px;
        font-size: 22px;
        font-weight: 800;
        letter-spacing: -0.3px;
        line-height: 1.25;
    }
    .usr-hero-sub {
        margin: 0;
        font-size: 13.5px;
        color: rgba(236, 253, 245, 0.88);
        max-width: 630px;
        line-height: 1.5;
    }
    .usr-btn-hero {
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
    .usr-btn-hero:hover {
        background: #FBBF24;
    }
    .usr-btn-hero:active {
        transform: scale(0.98);
    }

    /* ALERT NOTIFIKASI */
    .usr-alert {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 13px 16px;
        border-radius: 12px;
        font-size: 13.5px;
        font-weight: 600;
    }
    .usr-alert-success {
        background: #DCFCE7;
        border: 1px solid #86EFAC;
        color: #166534;
    }
    .usr-alert-error {
        background: #FEE2E2;
        border: 1px solid #FCA5A5;
        color: #991B1B;
    }
    .usr-alert-close {
        background: none;
        border: none;
        color: inherit;
        font-size: 18px;
        font-weight: 800;
        cursor: pointer;
        padding: 0 4px;
        opacity: 0.75;
    }
    .usr-alert-close:hover {
        opacity: 1;
    }

    /* KARTU RINGKASAN PER ROLE */
    .usr-kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
        gap: 16px;
    }
    .usr-kpi-card {
        background: #FFFFFF;
        border: 1px solid #E2E8F0;
        border-radius: 14px;
        padding: 18px 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.025);
        cursor: pointer;
        transition: border-color 0.16s ease, transform 0.16s ease;
    }
    .usr-kpi-card:hover {
        border-color: #10B981;
        transform: translateY(-2px);
    }
    .usr-kpi-label {
        font-size: 12px;
        font-weight: 700;
        color: #64748B;
    }
    .usr-kpi-val {
        font-size: 23px;
        font-weight: 800;
        color: #0F172A;
        margin-top: 4px;
        line-height: 1.15;
    }
    .usr-kpi-icon {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    /* CARD UTAMA & TOOLBAR FILTER */
    .usr-card {
        background: #FFFFFF;
        border: 1px solid #E2E8F0;
        border-radius: 16px;
        padding: 22px;
        box-shadow: 0 2px 10px rgba(15, 23, 42, 0.03);
    }
    .usr-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 16px;
    }
    .usr-filters {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        width: 100%;
    }
    .usr-input, .usr-select {
        box-sizing: border-box;
        padding: 9.5px 13px;
        border: 1px solid #CBD5E1;
        border-radius: 10px;
        font-family: inherit;
        font-size: 13.5px;
        color: #0F172A;
        background: #FFFFFF;
        transition: border-color 0.16s ease, box-shadow 0.16s ease;
    }
    .usr-input:focus, .usr-select:focus {
        outline: none;
        border-color: #10B981;
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.14);
    }

    /* TABEL RESPONSIVE */
    .usr-table-wrap {
        width: 100%;
        overflow-x: auto;
        border: 1px solid #E2E8F0;
        border-radius: 12px;
    }
    .usr-table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
        font-size: 13.5px;
        min-width: 820px;
    }
    .usr-table thead tr {
        background: #F8FAFC;
        border-bottom: 1.5px solid #E2E8F0;
    }
    .usr-table th {
        padding: 12px 14px;
        font-size: 11.5px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #475569;
        white-space: nowrap;
    }
    .usr-table td {
        padding: 13px 14px;
        border-bottom: 1px solid #F1F5F9;
        color: #1E293B;
        vertical-align: middle;
    }
    .usr-table tbody tr:last-child td {
        border-bottom: none;
    }
    .usr-table tbody tr:hover {
        background: #F8FAFC;
    }

    /* AVATAR & BADGES */
    .usr-avatar {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        font-weight: 800;
        font-size: 13.5px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .usr-code {
        display: inline-block;
        padding: 3px 9px;
        border-radius: 7px;
        background: #F1F5F9;
        border: 1px solid #CBD5E1;
        color: #065F46;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 12.5px;
        font-weight: 700;
    }
    .usr-role-pill {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 99px;
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.4px;
    }

    /* ACTION BUTTONS DI TABEL */
    .usr-act {
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
    .usr-act-edit {
        background: #FEF3C7;
        border-color: #FDE68A;
        color: #92400E;
    }
    .usr-act-edit:hover {
        background: #FDE68A;
    }
    .usr-act-reset {
        background: #E0F2FE;
        border-color: #BAE6FD;
        color: #0369A1;
    }
    .usr-act-reset:hover {
        background: #BAE6FD;
    }
    .usr-act-del {
        background: #FEF2F2;
        border-color: #FECACA;
        color: #DC2626;
    }
    .usr-act-del:hover {
        background: #FEE2E2;
    }

    /* MODAL RINGAN (ZERO LAG) */
    .usr-modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.58);
        z-index: 999;
        align-items: center;
        justify-content: center;
        padding: 16px;
    }
    .usr-modal-overlay.show {
        display: flex;
    }
    .usr-modal-box {
        background: #FFFFFF;
        border: 1px solid #E2E8F0;
        border-radius: 18px;
        width: 100%;
        max-width: 540px;
        max-height: 92vh;
        overflow-y: auto;
        box-shadow: 0 22px 46px rgba(15, 23, 42, 0.24);
    }
    .usr-modal-head {
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
    .usr-form-body {
        padding: 20px 22px 22px;
        display: flex;
        flex-direction: column;
        gap: 13px;
    }
    .usr-grid-2 {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }
    .usr-field {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }
    .usr-label {
        font-size: 12px;
        font-weight: 700;
        color: #334155;
    }
    .usr-btn-submit {
        width: 100%;
        padding: 12px 16px;
        border-radius: 11px;
        border: none;
        font-family: inherit;
        font-size: 13.5px;
        font-weight: 700;
        cursor: pointer;
        color: #FFFFFF;
        transition: background-color 0.16s ease;
    }

    @media (max-width: 640px) {
        .usr-hero {
            padding: 20px;
        }
        .usr-hero-title {
            font-size: 19px;
        }
        .usr-btn-hero {
            width: 100%;
        }
        .usr-grid-2 {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="usr-wrap">

    <!-- HERO HEADER -->
    <section class="usr-hero">
        <div>
            <div class="usr-eyebrow">
                <span class="usr-eyebrow-dot"></span>
                <span>OTORISASI &amp; KREDENSIAL RBAC</span>
            </div>
            <h2 class="usr-hero-title">Manajemen Akun &amp; Otorisasi User Lintas Wilayah</h2>
            <p class="usr-hero-sub">
                Terbitkan kredensial login untuk Koperasi Primer tingkat kecamatan, Petani Pemasok hulu, dan Mitra Buyer pasar secara terpusat.
            </p>
        </div>

        <button type="button" class="usr-btn-hero" onclick="openUserModal('modalTambahUser')">
            + Terbitkan Akun Baru
        </button>
    </section>

    <!-- ALERT NOTIFIKASI -->
    <?php if (isset($_GET['msg']) && $_GET['msg'] !== ''): ?>
        <div class="usr-alert usr-alert-success">
            <span>✓ <?= htmlspecialchars($_GET['msg']) ?></span>
            <button type="button" class="usr-alert-close" onclick="this.parentElement.remove()" aria-label="Tutup">&times;</button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['err']) && $_GET['err'] !== ''): ?>
        <div class="usr-alert usr-alert-error">
            <span>! <?= htmlspecialchars($_GET['err']) ?></span>
            <button type="button" class="usr-alert-close" onclick="this.parentElement.remove()" aria-label="Tutup">&times;</button>
        </div>
    <?php endif; ?>

    <!-- RINGKASAN AKTOR TERDAFTAR (KLIK UNTUK FILTER CEPAT) -->
    <section class="usr-kpi-grid">
        <div class="usr-kpi-card" style="border-left:4px solid #059669;" onclick="quickFilterRole('konsumen')" title="Klik untuk filter Koperasi Konsumen">
            <div>
                <div class="usr-kpi-label">Koperasi Konsumen (Pusat)</div>
                <div class="usr-kpi-val"><?= $count_role['konsumen'] ?> Akun</div>
            </div>
            <div class="usr-kpi-icon" style="background:#DCFCE7; color:#059669;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M3 21h18"/><path d="M5 21V7l8-4v18"/><path d="M19 21V11l-6-4"/></svg>
            </div>
        </div>

        <div class="usr-kpi-card" style="border-left:4px solid #D97706;" onclick="quickFilterRole('primer')" title="Klik untuk filter Koperasi Primer">
            <div>
                <div class="usr-kpi-label">Admin Koperasi Primer</div>
                <div class="usr-kpi-val"><?= $count_role['primer'] ?> Akun</div>
            </div>
            <div class="usr-kpi-icon" style="background:#FEF3C7; color:#D97706;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            </div>
        </div>

        <div class="usr-kpi-card" style="border-left:4px solid #2563EB;" onclick="quickFilterRole('petani')" title="Klik untuk filter Petani Pemasok">
            <div>
                <div class="usr-kpi-label">Petani / Pemasok Hulu</div>
                <div class="usr-kpi-val"><?= $count_role['petani'] ?> Akun</div>
            </div>
            <div class="usr-kpi-icon" style="background:#DBEAFE; color:#2563EB;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
            </div>
        </div>

        <div class="usr-kpi-card" style="border-left:4px solid #7C3AED;" onclick="quickFilterRole('buyer')" title="Klik untuk filter Mitra Buyer">
            <div>
                <div class="usr-kpi-label">Mitra Buyer / Pembeli</div>
                <div class="usr-kpi-val"><?= $count_role['buyer'] ?> Akun</div>
            </div>
            <div class="usr-kpi-icon" style="background:#F3E8FF; color:#7C3AED;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/></svg>
            </div>
        </div>
    </section>

    <!-- FILTER & TABEL DAFTAR PENGGUNA -->
    <section class="usr-card">
        <div class="usr-toolbar">
            <div class="usr-filters">
                <input type="search" id="searchUser" class="usr-input" style="flex:1; min-width:230px;" oninput="filterUsers()" placeholder="Cari nama lengkap, @username, divisi kabupaten, kecamatan, atau no. HP...">

                <select id="filterRole" class="usr-select" style="min-width:185px; font-weight:600;" onchange="filterUsers()">
                    <option value="">Semua Role RBAC</option>
                    <option value="konsumen">Koperasi Konsumen</option>
                    <option value="primer">Koperasi Primer</option>
                    <option value="petani">Petani Pemasok</option>
                    <option value="buyer">Buyer / Pembeli</option>
                </select>

                <select id="filterDivisiUser" class="usr-select" style="min-width:205px; font-weight:600;" onchange="filterUsers()">
                    <option value="">Semua Divisi Wilayah</option>
                    <?php foreach ($divisi_list as $d): ?>
                        <option value="<?= (int)$d['id_divisi'] ?>"><?= htmlspecialchars($d['nama_divisi']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="usr-table-wrap">
            <table class="usr-table" id="userTable">
                <thead>
                    <tr>
                        <th>Nama Lengkap / Badan</th>
                        <th>Username Login</th>
                        <th>Role RBAC</th>
                        <th>Penempatan Wilayah</th>
                        <th>Kontak</th>
                        <th style="text-align:right;">Aksi Manajemen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users_list)): ?>
                        <tr>
                            <td colspan="6" style="text-align:center; padding:28px; color:#64748B;">
                                Belum ada akun pengguna yang terdaftar.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users_list as $u):
                            $badge_bg = '#DCFCE7'; $badge_col = '#166534';
                            if ($u['role'] === 'primer') { $badge_bg = '#FEF3C7'; $badge_col = '#92400E'; }
                            elseif ($u['role'] === 'petani') { $badge_bg = '#DBEAFE'; $badge_col = '#1E40AF'; }
                            elseif ($u['role'] === 'buyer') { $badge_bg = '#F3E8FF'; $badge_col = '#6B21A8'; }

                            $inisial = strtoupper(substr(trim($u['nama_lengkap'] ?? 'U'), 0, 1));
                            $search_blob = strtolower(
                                ($u['nama_lengkap'] ?? '') . ' ' .
                                ($u['username'] ?? '') . ' ' .
                                ($u['nama_divisi'] ?? '') . ' ' .
                                ($u['nama_koperasi'] ?? '') . ' ' .
                                ($u['kecamatan'] ?? '') . ' ' .
                                ($u['no_hp'] ?? '')
                            );
                        ?>
                        <tr data-role="<?= htmlspecialchars($u['role']) ?>"
                            data-divisi="<?= (int)($u['id_divisi'] ?? 0) ?>"
                            data-search="<?= htmlspecialchars($search_blob) ?>">
                            <td>
                                <div style="display:flex; align-items:center; gap:11px;">
                                    <div class="usr-avatar" style="background:<?= $badge_bg ?>; color:<?= $badge_col ?>;">
                                        <?= htmlspecialchars($inisial) ?>
                                    </div>
                                    <div style="min-width:0;">
                                        <div style="font-weight:700; color:#0F172A;">
                                            <?= htmlspecialchars($u['nama_lengkap']) ?>
                                            <?php if ((int)$u['id_user'] === $current_user_id): ?>
                                                <span style="font-size:10.5px; background:#ECFDF5; color:#047857; border:1px solid #A7F3D0; padding:1px 7px; border-radius:99px; margin-left:4px;">Anda</span>
                                            <?php endif; ?>
                                        </div>
                                        <div style="font-size:11.5px; color:#64748B; max-width:260px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                            <?= htmlspecialchars($u['alamat'] ?: 'Alamat belum dilengkapi') ?>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <span class="usr-code">@<?= htmlspecialchars($u['username']) ?></span>
                            </td>

                            <td>
                                <span class="usr-role-pill" style="background:<?= $badge_bg ?>; color:<?= $badge_col ?>;">
                                    <?= htmlspecialchars($u['role']) ?>
                                </span>
                            </td>

                            <td>
                                <?php if ($u['role'] === 'buyer'): ?>
                                    <span style="color:#64748B; font-size:12.5px; font-weight:600;">Akses Marketplace Nasional</span>
                                <?php else: ?>
                                    <div style="font-weight:700; color:#1E293B;"><?= htmlspecialchars($u['nama_divisi'] ?? 'Semua Divisi Pusat') ?></div>
                                    <?php if (!empty($u['nama_koperasi'])): ?>
                                        <div style="font-size:11.5px; color:#059669; font-weight:600;">
                                            <?= htmlspecialchars($u['nama_koperasi']) ?> (Kec. <?= htmlspecialchars($u['kecamatan']) ?>)
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>

                            <td style="font-size:13px; color:#475569; font-weight:600;">
                                <?= htmlspecialchars($u['no_hp'] ?: '-') ?>
                            </td>

                            <td style="text-align:right; white-space:nowrap;">
                                <button type="button" class="usr-act usr-act-edit"
                                    onclick='openEditUserModal(<?= json_encode([
                                        "id_user"      => (int)$u["id_user"],
                                        "role"         => $u["role"],
                                        "username"     => $u["username"],
                                        "nama_lengkap" => $u["nama_lengkap"],
                                        "id_divisi"    => $u["id_divisi"] ?? "",
                                        "id_primer"    => $u["id_primer"] ?? "",
                                        "no_hp"        => $u["no_hp"] ?? "",
                                        "alamat"       => $u["alamat"] ?? ""
                                    ], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                    Edit
                                </button>

                                <form method="POST" action="proses.php" style="display:inline; margin-left:4px;" onsubmit="return confirm('Reset kata sandi akun @<?= htmlspecialchars(addslashes($u['username'])) ?> menjadi default (123)?');">
                                    <?= $csrf_input ?>
                                    <input type="hidden" name="aksi" value="reset_pw">
                                    <input type="hidden" name="id_user" value="<?= (int)$u['id_user'] ?>">
                                    <button type="submit" class="usr-act usr-act-reset">Reset Sandi</button>
                                </form>

                                <?php if ((int)$u['id_user'] !== $current_user_id): ?>
                                    <form method="POST" action="proses.php" style="display:inline; margin-left:4px;" onsubmit="return confirm('Yakin ingin menghapus akun @<?= htmlspecialchars(addslashes($u['username'])) ?> secara permanen?');">
                                        <?= $csrf_input ?>
                                        <input type="hidden" name="aksi" value="hapus_user">
                                        <input type="hidden" name="id_user" value="<?= (int)$u['id_user'] ?>">
                                        <button type="submit" class="usr-act usr-act-del">Hapus</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <tr id="userEmptyFilterRow" style="display:none;">
                            <td colspan="6" style="text-align:center; padding:26px; color:#64748B;">
                                Tidak ada akun pengguna yang sesuai dengan kata kunci atau filter yang dipilih.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

</div>

<!-- MODAL 1: TERBITKAN AKUN BARU -->
<div id="modalTambahUser" class="usr-modal-overlay" onclick="if(event.target===this) closeUserModal('modalTambahUser')">
    <div class="usr-modal-box">
        <div class="usr-modal-head">
            <div>
                <div style="font-weight:800; font-size:15.5px;">Terbitkan Kredensial Akun Baru</div>
                <div style="font-size:11.5px; color:#A7F3D0; margin-top:2px;">Hubungkan akun ke divisi kabupaten atau unit koperasi primer.</div>
            </div>
            <button type="button" onclick="closeUserModal('modalTambahUser')" style="background:rgba(255,255,255,0.12); border:none; width:30px; height:30px; border-radius:8px; font-size:16px; cursor:pointer; color:#FFFFFF;">&times;</button>
        </div>

        <form action="proses.php" method="POST" class="usr-form-body">
            <?= $csrf_input ?>
            <input type="hidden" name="aksi" value="tambah_user">

            <div class="usr-grid-2">
                <div class="usr-field">
                    <label class="usr-label" for="addRoleSelect">Role Hak Akses (RBAC) *</label>
                    <select name="role" id="addRoleSelect" class="usr-select" onchange="toggleWilayahFields('add')" required style="width:100%; font-weight:600;">
                        <option value="primer">Koperasi Primer (Kecamatan)</option>
                        <option value="petani">Petani / Pemasok Hulu</option>
                        <option value="buyer">Buyer / Mitra Pasar</option>
                        <option value="konsumen">Koperasi Konsumen (Pusat)</option>
                    </select>
                </div>
                <div class="usr-field">
                    <label class="usr-label" for="add_username">Username Login *</label>
                    <input type="text" id="add_username" name="username" class="usr-input" placeholder="Contoh: primer_samarang" required style="width:100%;">
                </div>
            </div>

            <div class="usr-grid-2">
                <div class="usr-field">
                    <label class="usr-label" for="add_nama">Nama Lengkap / Instansi *</label>
                    <input type="text" id="add_nama" name="nama_lengkap" class="usr-input" placeholder="Pengurus Primer Samarang" required style="width:100%;">
                </div>
                <div class="usr-field">
                    <label class="usr-label" for="add_password">Kata Sandi Awal *</label>
                    <input type="text" id="add_password" name="password" class="usr-input" value="123" required style="width:100%;">
                </div>
            </div>

            <!-- PENEMPATAN WILAYAH DINAMIS SESUAI ROLE -->
            <div class="usr-field" id="addDivisiBox">
                <label class="usr-label" for="add_id_divisi">Divisi Kabupaten / Kota</label>
                <select name="id_divisi" id="add_id_divisi" class="usr-select" onchange="filterPrimerOptions('add')" style="width:100%;">
                    <option value="">-- Pilih Divisi Kabupaten/Kota --</option>
                    <?php foreach ($divisi_list as $d): ?>
                        <option value="<?= (int)$d['id_divisi'] ?>"><?= htmlspecialchars($d['nama_divisi']) ?> (<?= htmlspecialchars($d['provinsi']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="usr-field" id="addPrimerBox">
                <label class="usr-label" for="add_id_primer">Unit Koperasi Primer Kecamatan</label>
                <select name="id_primer" id="add_id_primer" class="usr-select" style="width:100%;">
                    <option value="">-- Pilih Unit Koperasi Primer --</option>
                    <?php foreach ($primer_list as $p): ?>
                        <option value="<?= (int)$p['id_primer'] ?>" data-divisi="<?= (int)$p['id_divisi'] ?>">
                            <?= htmlspecialchars($p['nama_koperasi']) ?> — Kec. <?= htmlspecialchars($p['kecamatan']) ?> (<?= htmlspecialchars($p['nama_divisi']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="usr-grid-2" style="grid-template-columns: 1fr 1.7fr;">
                <div class="usr-field">
                    <label class="usr-label" for="add_no_hp">No. WhatsApp / HP</label>
                    <input type="text" id="add_no_hp" name="no_hp" class="usr-input" placeholder="0812..." style="width:100%;">
                </div>
                <div class="usr-field">
                    <label class="usr-label" for="add_alamat">Alamat Lengkap</label>
                    <input type="text" id="add_alamat" name="alamat" class="usr-input" placeholder="Alamat kantor / lahan / pengiriman..." style="width:100%;">
                </div>
            </div>

            <button type="submit" class="usr-btn-submit" style="background:#064E3B; margin-top:4px;">
                Aktifkan &amp; Terbitkan Akun
            </button>
        </form>
    </div>
</div>

<!-- MODAL 2: EDIT OTORISASI & WILAYAH USER -->
<div id="modalEditUser" class="usr-modal-overlay" onclick="if(event.target===this) closeUserModal('modalEditUser')">
    <div class="usr-modal-box">
        <div class="usr-modal-head">
            <div>
                <div style="font-weight:800; font-size:15.5px;">Perbarui Otorisasi &amp; Wilayah Akun</div>
                <div style="font-size:11.5px; color:#A7F3D0; margin-top:2px;">Sesuaikan hak akses RBAC, wilayah tugas, dan kontak pengguna.</div>
            </div>
            <button type="button" onclick="closeUserModal('modalEditUser')" style="background:rgba(255,255,255,0.12); border:none; width:30px; height:30px; border-radius:8px; font-size:16px; cursor:pointer; color:#FFFFFF;">&times;</button>
        </div>

        <form action="proses.php" method="POST" class="usr-form-body">
            <?= $csrf_input ?>
            <input type="hidden" name="aksi" value="edit_user">
            <input type="hidden" name="id_user" id="edit_id_user">

            <div class="usr-grid-2">
                <div class="usr-field">
                    <label class="usr-label" for="editRoleSelect">Role Hak Akses *</label>
                    <select name="role" id="editRoleSelect" class="usr-select" onchange="toggleWilayahFields('edit')" required style="width:100%; font-weight:600;">
                        <option value="konsumen">Koperasi Konsumen</option>
                        <option value="primer">Koperasi Primer</option>
                        <option value="petani">Petani Pemasok</option>
                        <option value="buyer">Buyer / Pembeli</option>
                    </select>
                </div>
                <div class="usr-field">
                    <label class="usr-label" for="edit_username">Username *</label>
                    <input type="text" name="username" id="edit_username" class="usr-input" required style="width:100%;">
                </div>
            </div>

            <div class="usr-field">
                <label class="usr-label" for="edit_nama_lengkap">Nama Lengkap / Instansi *</label>
                <input type="text" name="nama_lengkap" id="edit_nama_lengkap" class="usr-input" required style="width:100%;">
            </div>

            <div class="usr-field" id="editDivisiBox">
                <label class="usr-label" for="edit_id_divisi">Divisi Kabupaten / Kota</label>
                <select name="id_divisi" id="edit_id_divisi" class="usr-select" onchange="filterPrimerOptions('edit')" style="width:100%;">
                    <option value="">-- Tidak Terikat Divisi --</option>
                    <?php foreach ($divisi_list as $d): ?>
                        <option value="<?= (int)$d['id_divisi'] ?>"><?= htmlspecialchars($d['nama_divisi']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="usr-field" id="editPrimerBox">
                <label class="usr-label" for="edit_id_primer">Unit Koperasi Primer</label>
                <select name="id_primer" id="edit_id_primer" class="usr-select" style="width:100%;">
                    <option value="">-- Tidak Terikat Unit Primer --</option>
                    <?php foreach ($primer_list as $p): ?>
                        <option value="<?= (int)$p['id_primer'] ?>" data-divisi="<?= (int)$p['id_divisi'] ?>">
                            <?= htmlspecialchars($p['nama_koperasi']) ?> (Kec. <?= htmlspecialchars($p['kecamatan']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="usr-grid-2" style="grid-template-columns: 1fr 1.7fr;">
                <div class="usr-field">
                    <label class="usr-label" for="edit_no_hp">No. WhatsApp / HP</label>
                    <input type="text" name="no_hp" id="edit_no_hp" class="usr-input" style="width:100%;">
                </div>
                <div class="usr-field">
                    <label class="usr-label" for="edit_alamat">Alamat Lengkap</label>
                    <input type="text" name="alamat" id="edit_alamat" class="usr-input" style="width:100%;">
                </div>
            </div>

            <button type="submit" class="usr-btn-submit" style="background:#D97706; margin-top:4px;">
                Simpan Perubahan Akun
            </button>
        </form>
    </div>
</div>

<script>
    function openUserModal(id) {
        const el = document.getElementById(id);
        if (el) el.classList.add('show');
        if (id === 'modalTambahUser') {
            toggleWilayahFields('add');
            filterPrimerOptions('add');
        }
    }

    function closeUserModal(id) {
        const el = document.getElementById(id);
        if (el) el.classList.remove('show');
    }

    function toggleWilayahFields(prefix) {
        const role   = document.getElementById(prefix + 'RoleSelect').value;
        const divBox = document.getElementById(prefix + 'DivisiBox');
        const prmBox = document.getElementById(prefix + 'PrimerBox');

        if (role === 'buyer') {
            divBox.style.display = 'none';
            prmBox.style.display = 'none';
        } else if (role === 'konsumen') {
            divBox.style.display = 'flex';
            prmBox.style.display = 'none';
        } else {
            divBox.style.display = 'flex';
            prmBox.style.display = 'flex';
        }
    }

    function filterPrimerOptions(prefix) {
        const divSelect = document.getElementById(prefix + '_id_divisi');
        const prmSelect = document.getElementById(prefix + '_id_primer');
        if (!divSelect || !prmSelect) return;

        const selectedDiv = divSelect.value;
        let currentValid = false;

        Array.from(prmSelect.options).forEach(function (opt) {
            if (!opt.value) {
                opt.hidden = false;
                return;
            }
            const optDiv = opt.getAttribute('data-divisi');
            const match = !selectedDiv || optDiv === selectedDiv;
            opt.hidden = !match;
            if (match && opt.value === prmSelect.value) {
                currentValid = true;
            }
        });

        if (prmSelect.value && !currentValid) {
            prmSelect.value = '';
        }
    }

    function openEditUserModal(u) {
        document.getElementById('edit_id_user').value = u.id_user;
        document.getElementById('editRoleSelect').value = u.role;
        document.getElementById('edit_username').value = u.username;
        document.getElementById('edit_nama_lengkap').value = u.nama_lengkap;
        document.getElementById('edit_id_divisi').value = u.id_divisi || '';

        toggleWilayahFields('edit');
        filterPrimerOptions('edit');

        document.getElementById('edit_id_primer').value = u.id_primer || '';
        document.getElementById('edit_no_hp').value = u.no_hp || '';
        document.getElementById('edit_alamat').value = u.alamat || '';

        openUserModal('modalEditUser');
    }

    function quickFilterRole(roleName) {
        const roleSelect = document.getElementById('filterRole');
        roleSelect.value = (roleSelect.value === roleName) ? '' : roleName;
        filterUsers();
    }

    function filterUsers() {
        const q = document.getElementById('searchUser').value.toLowerCase().trim();
        const r = document.getElementById('filterRole').value;
        const d = document.getElementById('filterDivisiUser').value;
        const rows = document.querySelectorAll('#userTable tbody tr[data-role]');
        const emptyRow = document.getElementById('userEmptyFilterRow');
        let visibleCount = 0;

        rows.forEach(function (row) {
            const matchQ = !q || row.getAttribute('data-search').indexOf(q) !== -1;
            const matchR = !r || row.getAttribute('data-role') === r;
            const matchD = !d || row.getAttribute('data-divisi') === d;
            const show = matchQ && matchR && matchD;
            row.style.display = show ? '' : 'none';
            if (show) visibleCount++;
        });

        if (emptyRow) {
            emptyRow.style.display = (rows.length > 0 && visibleCount === 0) ? '' : 'none';
        }
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeUserModal('modalTambahUser');
            closeUserModal('modalEditUser');
        }
    });
</script>

<?php require_once '../../includes/footer.php'; ?>