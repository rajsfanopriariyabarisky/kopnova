<?php
require_once '../../config/database.php';
cekAkses(['petani', 'primer']);
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

$role = $role ?? ($_SESSION['user']['role'] ?? '');
$user = $user ?? ($_SESSION['user'] ?? []);

$primer_list = $pdo->query("SELECT p.*, d.nama_divisi FROM koperasi_primer p JOIN divisi_wilayah d ON p.id_divisi = d.id_divisi ORDER BY d.nama_divisi ASC, p.nama_koperasi ASC")->fetchAll();

if ($role === 'petani') {
    $stmt = $pdo->prepare("SELECT ps.*, kp.nama_koperasi, kp.kecamatan 
                           FROM pasokan_petani ps 
                           JOIN koperasi_primer kp ON ps.id_primer = kp.id_primer 
                           WHERE ps.id_petani = ? 
                           ORDER BY ps.id_pasokan DESC");
    $stmt->execute([$user['id_user']]);
} else {
    $stmt = $pdo->query("SELECT ps.*, u.nama_lengkap AS nama_petani, kp.nama_koperasi, kp.kecamatan 
                         FROM pasokan_petani ps 
                         JOIN users u ON ps.id_petani = u.id_user 
                         JOIN koperasi_primer kp ON ps.id_primer = kp.id_primer 
                         ORDER BY ps.id_pasokan DESC");
}
$pasokan_list = $stmt->fetchAll();

// Hitung ringkasan statistik dari daftar pasokan
$total_setoran      = count($pasokan_list);
$total_menunggu     = 0;
$total_diterima     = 0;
$nilai_diterima     = 0;

foreach ($pasokan_list as $item) {
    if (($item['status_pasokan'] ?? '') === 'Diterima Primer') {
        $total_diterima++;
        $nilai_diterima += (float)($item['total_nilai'] ?? 0);
    } else {
        $total_menunggu++;
    }
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

    .pas-grid-3 {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 16px;
    }
    .pas-card {
        background: #FFFFFF;
        border-radius: 18px;
        border: 1px solid #E2E8F0;
        box-shadow: 0 2px 10px rgba(15, 23, 42, 0.03);
    }
    .pas-kpi {
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
    .pas-kpi:hover {
        transform: translateY(-2px);
    }
    .pas-kpi-val {
        font-size: clamp(1.25rem, 1.7vw, 1.55rem);
        font-weight: 900;
        color: #0F172A;
        margin: 8px 0 3px;
        font-variant-numeric: tabular-nums;
    }

    .pas-form-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 16px;
    }
    .pas-input {
        width: 100%;
        box-sizing: border-box;
        padding: 10px 12px;
        border: 1px solid #CBD5E1;
        border-radius: 11px;
        margin-top: 6px;
        font-size: 13px;
        font-weight: 600;
        color: #0F172A;
        background: #FFFFFF;
        outline: none;
        transition: border-color 0.2s, box-shadow 0.2s;
    }
    .pas-input:focus {
        border-color: #059669;
        box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.12);
    }
    .pas-unit-chip {
        padding: 3px 9px;
        border-radius: 6px;
        border: 1px solid #CBD5E1;
        background: #F8FAFC;
        color: #475569;
        font-size: 11px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.15s;
    }
    .pas-unit-chip:hover {
        background: #ECFDF5;
        border-color: #059669;
        color: #065F46;
    }
    .pas-upload-box {
        margin-top: 6px;
        border: 1.5px dashed #94A3B8;
        border-radius: 12px;
        padding: 10px 12px;
        background: #F8FAFC;
        display: flex;
        align-items: center;
        gap: 12px;
        cursor: pointer;
        transition: border-color 0.2s, background 0.2s;
    }
    .pas-upload-box:hover {
        border-color: #059669;
        background: #ECFDF5;
    }

    .pas-pill {
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
    .pas-pill:hover {
        border-color: #10B981;
        color: #065F46;
    }
    .pas-pill.active {
        background: #064E3B;
        color: #FFFFFF;
        border-color: #064E3B;
        box-shadow: 0 3px 10px rgba(6, 78, 59, 0.2);
    }
    .pas-search {
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
    .pas-search:focus {
        background: #FFFFFF;
        border-color: #059669;
    }
    .pas-row-hover:hover {
        background-color: #F8FAFC;
    }
    .pas-thumb {
        width: 54px;
        height: 54px;
        object-fit: cover;
        border-radius: 10px;
        border: 1px solid #CBD5E1;
        cursor: zoom-in;
        transition: transform 0.18s ease;
        display: block;
    }
    .pas-thumb:hover {
        transform: scale(1.06);
    }
    .pas-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        border: none;
        text-decoration: none;
        cursor: pointer;
        white-space: nowrap;
        transition: filter 0.18s ease, transform 0.15s ease;
    }
    .pas-btn:hover {
        filter: brightness(1.06);
        transform: translateY(-1px);
    }
    .mobile-nav-trigger {
        display: none;
    }

    /* TABLET & LAPTOP SEDANG */
    @media (max-width: 1120px) {
        .pas-form-grid {
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
        .pas-responsive-table thead {
            display: none;
        }
        .pas-responsive-table,
        .pas-responsive-table tbody,
        .pas-responsive-table tr,
        .pas-responsive-table td {
            display: block;
            width: 100%;
            box-sizing: border-box;
        }
        .pas-responsive-table tr.pas-mobile-card {
            background: #FFFFFF;
            border: 1px solid #E2E8F0 !important;
            border-radius: 15px;
            padding: 14px;
            margin-bottom: 12px;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03);
        }
        .pas-responsive-table td {
            padding: 5px 0 !important;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            border: none !important;
            text-align: right;
        }
        .pas-responsive-table td::before {
            content: attr(data-label);
            font-size: 11.5px;
            font-weight: 700;
            color: #64748B;
            text-align: left;
            flex-shrink: 0;
        }
        .pas-responsive-table td.td-card-top {
            border-bottom: 1px dashed #E2E8F0 !important;
            padding-bottom: 10px !important;
            margin-bottom: 4px;
            justify-content: flex-start;
            text-align: left;
        }
        .pas-responsive-table td.td-card-top::before {
            display: none;
        }
        .pas-responsive-table td.td-card-action {
            border-top: 1px solid #F1F5F9 !important;
            padding-top: 10px !important;
            margin-top: 6px;
            flex-direction: column;
            align-items: stretch;
        }
        .pas-responsive-table td.td-card-action::before {
            margin-bottom: 5px;
        }
        .pas-responsive-table td.td-card-action a {
            width: 100%;
            box-sizing: border-box;
            padding: 10px 14px !important;
            font-size: 13px !important;
        }
    }

    /* LAYAR HP COMPACT (< 640px) */
    @media (max-width: 640px) {
        .pas-grid-3, .pas-form-grid {
            grid-template-columns: minmax(0, 1fr);
        }
        .pas-hero {
            padding: 22px 20px !important;
        }
        .pas-hero-title {
            font-size: 19px !important;
        }
        .pas-hero-btn, .pas-submit-btn {
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
<div class="pas-hero" style="background:linear-gradient(125deg, #064E3B 0%, #059669 100%); border-radius:20px; padding:26px 30px; color:#FFFFFF; margin-bottom:20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:18px; box-shadow:0 10px 25px rgba(5, 150, 105, 0.16); position:relative; overflow:hidden;">
    <div style="position:absolute; right:-30px; bottom:-50px; width:210px; height:210px; border-radius:50%; background:rgba(255,255,255,0.08); pointer-events:none;"></div>
    <div style="max-width:620px; position:relative; z-index:2;">
        <div style="display:inline-flex; align-items:center; gap:7px; background:rgba(255,255,255,0.16); color:#ECFDF5; font-size:11px; font-weight:800; padding:5px 12px; border-radius:99px; margin-bottom:10px; letter-spacing:0.3px;">
            <span style="width:7px; height:7px; border-radius:50%; background:#34D399; display:inline-block;"></span>
            <span><?= $role === 'petani' ? 'SETORAN PANEN HULU KE KOPERASI' : 'VERIFIKASI KUALITAS & PENERIMAAN PANEN' ?></span>
        </div>
        <h2 class="pas-hero-title" style="margin:0 0 6px; font-size:22px; font-weight:800; line-height:1.3;">
            <?= $role === 'petani' ? 'Pengajuan Setoran Panen ke Koperasi Primer' : 'Verifikasi Pasokan Masuk dari Petani' ?>
        </h2>
        <p style="margin:0; font-size:13.5px; color:#ECFDF5; line-height:1.55; opacity:0.95;">
            <?= $role === 'petani'
                ? 'Kirim data komoditas hasil panen beserta foto kualitas agar segera diverifikasi dan diserap oleh Koperasi Primer.'
                : 'Periksa bukti foto kualitas, volume, dan harga penawaran petani sebelum mengonfirmasi penerimaan pasokan.' ?>
        </p>
    </div>

    <?php if ($role === 'petani'): ?>
    <a href="#formSetorPanen" class="pas-btn pas-hero-btn" style="background:#FBBF24; color:#0F172A; padding:12px 18px; border-radius:12px; font-weight:800; font-size:13px; box-shadow:0 4px 14px rgba(251,191,36,0.35); position:relative; z-index:2;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        <span>Buat Setoran Baru</span>
    </a>
    <?php endif; ?>
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

<!-- 3 KARTU RINGKASAN PASOKAN -->
<div class="pas-grid-3" style="margin-bottom:22px;">
    <div class="pas-kpi" style="border-left:5px solid #064E3B;">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
            <span style="font-size:12px; color:#64748B; font-weight:700;">Total Riwayat Setoran</span>
            <div style="width:35px; height:35px; border-radius:10px; background:#DCFCE7; color:#059669; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
            </div>
        </div>
        <div class="pas-kpi-val"><?= $total_setoran ?> Pengajuan</div>
        <div style="font-size:11.5px; color:#64748B;">Seluruh pengiriman tercatat</div>
    </div>

    <div class="pas-kpi" style="border-left:5px solid #F59E0B;">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
            <span style="font-size:12px; color:#64748B; font-weight:700;">Menunggu Verifikasi</span>
            <div style="width:35px; height:35px; border-radius:10px; background:#FEF3C7; color:#D97706; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
        </div>
        <div class="pas-kpi-val" style="color:#B45309;"><?= $total_menunggu ?> Pengajuan</div>
        <div style="font-size:11.5px; color:#64748B;">Perlu pengecekan unit primer</div>
    </div>

    <div class="pas-kpi" style="border-left:5px solid #059669;">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
            <span style="font-size:12px; color:#64748B; font-weight:700;">Diterima Primer (<?= $total_diterima ?>)</span>
            <div style="width:35px; height:35px; border-radius:10px; background:#ECFDF5; color:#059669; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
        </div>
        <div class="pas-kpi-val" style="color:#064E3B;"><?= rupiah($nilai_diterima) ?></div>
        <div style="font-size:11.5px; color:#059669; font-weight:600;">Total nilai panen terverifikasi</div>
    </div>
</div>

<?php if ($role === 'petani'): ?>
<!-- FORMULIR KIRIM HASIL PANEN (KHUSUS PETANI) -->
<div id="formSetorPanen" class="pas-card" style="padding:24px 26px; margin-bottom:24px;">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:10px; margin-bottom:18px; padding-bottom:14px; border-bottom:1px solid #F1F5F9;">
        <div>
            <span style="display:inline-block; background:#ECFDF5; color:#065F46; font-size:11px; font-weight:800; padding:3px 10px; border-radius:99px; margin-bottom:5px;">FORMULIR HULU</span>
            <h3 style="margin:0; font-size:17px; font-weight:800; color:#0F172A;">Formulir Kirim Hasil Panen</h3>
            <div style="font-size:12.5px; color:#64748B; margin-top:2px;">Lengkapi detail komoditas, volume, harga penawaran, dan foto hasil panen terbaru.</div>
        </div>

        <!-- LIVE ESTIMASI NILAI -->
        <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:12px; padding:10px 14px; text-align:right;">
            <div style="font-size:11px; font-weight:700; color:#64748B;">Estimasi Total Nilai Setoran</div>
            <div id="pasEstimasiTotal" style="font-size:17px; font-weight:900; color:#064E3B; font-variant-numeric:tabular-nums;">Rp 0</div>
        </div>
    </div>

    <form action="proses.php" method="POST" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="setor_petani">

        <div class="pas-form-grid">
            <div>
                <label style="font-size:12px; font-weight:700; color:#334155;">Koperasi Primer Tujuan</label>
                <select name="id_primer" required class="pas-input">
                    <?php foreach ($primer_list as $kp): ?>
                        <option value="<?= (int)$kp['id_primer'] ?>"><?= htmlspecialchars($kp['nama_koperasi']) ?> (<?= htmlspecialchars($kp['nama_divisi']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label style="font-size:12px; font-weight:700; color:#334155;">Nama Komoditas</label>
                <input type="text" name="nama_komoditas" placeholder="Contoh: Kentang Granola Super" required class="pas-input">
            </div>

            <div>
                <label style="font-size:12px; font-weight:700; color:#334155;">Kategori Produk</label>
                <select name="kategori" class="pas-input">
                    <option value="Komoditas Tanaman">Komoditas Tanaman</option>
                    <option value="Saprodi">Saprodi</option>
                    <option value="Produk Olahan">Produk Olahan</option>
                </select>
            </div>

            <div>
                <div style="display:flex; justify-content:space-between; align-items:center; gap:6px; flex-wrap:wrap;">
                    <label style="font-size:12px; font-weight:700; color:#334155;">Volume &amp; Satuan</label>
                    <div style="display:flex; gap:4px;">
                        <button type="button" class="pas-unit-chip" onclick="setPasSatuan('Kg')">Kg</button>
                        <button type="button" class="pas-unit-chip" onclick="setPasSatuan('Kwintal')">Kwintal</button>
                        <button type="button" class="pas-unit-chip" onclick="setPasSatuan('Karung')">Karung</button>
                    </div>
                </div>
                <div style="display:flex; gap:8px; margin-top:6px;">
                    <input type="number" name="jumlah" id="pasJumlah" oninput="hitungEstimasiPasokan()" placeholder="Jumlah" min="1" required class="pas-input" style="margin-top:0; width:62%;">
                    <input type="text" name="satuan" id="pasSatuan" value="Kg" required class="pas-input" style="margin-top:0; width:38%;">
                </div>
            </div>

            <div>
                <label style="font-size:12px; font-weight:700; color:#334155;">Harga Penawaran / Satuan (Rp)</label>
                <input type="number" name="harga_tawar" id="pasHarga" oninput="hitungEstimasiPasokan()" placeholder="Contoh: 12000" min="1" required class="pas-input">
            </div>

            <div>
                <label style="font-size:12px; font-weight:700; color:#334155;">Foto Bukti Hasil Panen</label>
                <label class="pas-upload-box" for="pasFotoInput">
                    <img id="pasFotoPreview" src="https://placehold.co/64x64/e2e8f0/475569?text=Foto" alt="Pratinjau" style="width:44px; height:44px; object-fit:cover; border-radius:9px; border:1px solid #CBD5E1; flex-shrink:0;">
                    <div style="min-width:0; flex:1;">
                        <div id="pasFotoName" style="font-size:12.5px; font-weight:700; color:#0F172A; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">Pilih atau Ambil Foto Panen</div>
                        <div style="font-size:11px; color:#64748B;">Format JPG/PNG dari galeri atau kamera</div>
                    </div>
                </label>
                <input type="file" name="foto_pasokan" id="pasFotoInput" accept="image/*" required onchange="previewFotoPasokan(this)" style="position:absolute; width:1px; height:1px; opacity:0; overflow:hidden;">
            </div>
        </div>

        <div style="margin-top:18px; padding-top:16px; border-top:1px solid #F1F5F9; display:flex; justify-content:flex-end;">
            <button type="submit" class="pas-btn pas-submit-btn" style="background:#059669; color:#FFFFFF; padding:12px 22px; border-radius:12px; font-weight:800; font-size:13.5px; box-shadow:0 4px 14px rgba(5,150,105,0.25);">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                <span>Kirim Pasokan ke Koperasi Primer</span>
            </button>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- DAFTAR RIWAYAT & VERIFIKASI PASOKAN -->
<div class="pas-card" style="padding:22px 24px;">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:10px; margin-bottom:14px;">
        <div>
            <h3 style="margin:0 0 3px; font-size:16.5px; font-weight:800; color:#0F172A;">
                <?= $role === 'petani' ? 'Riwayat Setoran Panen Anda' : 'Daftar Pasokan Masuk dari Petani' ?>
            </h3>
            <div style="font-size:12.5px; color:#64748B;">Klik foto komoditas untuk memperbesar gambar pemeriksaan kualitas.</div>
        </div>
    </div>

    <!-- FILTER STATUS & PENCARIAN LIVE -->
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:16px; padding-bottom:12px; border-bottom:1px solid #F1F5F9;">
        <div style="display:flex; gap:6px; flex-wrap:wrap;" id="pasFilterBar">
            <button type="button" class="pas-pill active" onclick="filterPasokan('ALL', this)">Semua (<?= $total_setoran ?>)</button>
            <button type="button" class="pas-pill" onclick="filterPasokan('Menunggu Verifikasi', this)">Menunggu Verifikasi (<?= $total_menunggu ?>)</button>
            <button type="button" class="pas-pill" onclick="filterPasokan('Diterima Primer', this)">Diterima Primer (<?= $total_diterima ?>)</button>
        </div>

        <div style="position:relative; min-width:230px; flex:1; max-width:320px;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#64748B" stroke-width="2.2" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); pointer-events:none;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" id="pasSearchInput" onkeyup="applyPasokanFilters()" placeholder="Cari kode, komoditas, petani..." class="pas-search">
        </div>
    </div>

    <div>
        <table class="pas-responsive-table" style="width:100%; border-collapse:collapse; text-align:left; font-size:13px;">
            <thead>
                <tr style="background:#064E3B; color:#FFFFFF; font-size:11.5px; text-transform:uppercase; letter-spacing:0.4px;">
                    <th style="padding:12px 14px; border-radius:10px 0 0 0;">Komoditas &amp; Foto</th>
                    <th style="padding:12px 14px;">Kode Setoran</th>
                    <th style="padding:12px 14px;"><?= $role === 'petani' ? 'Koperasi Tujuan' : 'Petani &amp; Unit Primer' ?></th>
                    <th style="padding:12px 14px;">Volume</th>
                    <th style="padding:12px 14px;">Total Nilai</th>
                    <th style="padding:12px 14px; <?= $role !== 'primer' ? 'border-radius:0 10px 0 0;' : '' ?>">Status</th>
                    <?php if ($role === 'primer'): ?>
                        <th style="padding:12px 14px; text-align:center; border-radius:0 10px 0 0;">Aksi Verifikasi</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pasokan_list)): ?>
                    <tr>
                        <td colspan="<?= $role === 'primer' ? 7 : 6 ?>" style="padding:30px; text-align:center; color:#64748B;">
                            Belum ada data pasokan panen yang tercatat.
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($pasokan_list as $ps): 
                    $is_done = (($ps['status_pasokan'] ?? '') === 'Diterima Primer');
                    $foto_url = BASE_URL . '/uploads/pasokan/' . htmlspecialchars($ps['foto_pasokan']);
                ?>
                <tr class="pas-row-hover pas-mobile-card pas-data-row"
                    data-status="<?= htmlspecialchars($ps['status_pasokan']) ?>"
                    style="border-bottom:1px solid #F1F5F9;">

                    <td class="td-card-top" style="padding:12px 14px;">
                        <div style="display:flex; align-items:center; gap:12px;">
                            <img src="<?= $foto_url ?>"
                                 alt="<?= htmlspecialchars($ps['nama_komoditas']) ?>"
                                 onclick="openPasFotoModal(this.src, '<?= htmlspecialchars($ps['nama_komoditas'] . ' (' . $ps['kode_pasokan'] . ')', ENT_QUOTES) ?>')"
                                 onerror="this.src='https://placehold.co/120x120/e2e8f0/475569?text=Panen'"
                                 class="pas-thumb">
                            <div>
                                <div style="font-weight:800; color:#0F172A; font-size:13.5px;"><?= htmlspecialchars($ps['nama_komoditas']) ?></div>
                                <div style="font-size:11.5px; color:#64748B; margin-top:2px;"><?= htmlspecialchars($ps['kategori'] ?? 'Komoditas') ?></div>
                            </div>
                        </div>
                    </td>

                    <td data-label="Kode Setoran" style="padding:12px 14px;">
                        <span style="font-family:monospace; font-weight:800; color:#047857; background:#ECFDF5; padding:4px 9px; border-radius:7px; font-size:12px;">
                            <?= htmlspecialchars($ps['kode_pasokan']) ?>
                        </span>
                    </td>

                    <td data-label="<?= $role === 'petani' ? 'Koperasi Tujuan' : 'Petani & Unit' ?>" style="padding:12px 14px;">
                        <?php if ($role === 'petani'): ?>
                            <div style="font-weight:700; color:#0F172A;"><?= htmlspecialchars($ps['nama_koperasi']) ?></div>
                            <?php if (!empty($ps['kecamatan'])): ?>
                                <div style="font-size:11.5px; color:#64748B;">Kec. <?= htmlspecialchars($ps['kecamatan']) ?></div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div style="font-weight:800; color:#0F172A;"><?= htmlspecialchars($ps['nama_petani'] ?? '-') ?></div>
                            <div style="font-size:11.5px; color:#64748B;"><?= htmlspecialchars($ps['nama_koperasi']) ?></div>
                        <?php endif; ?>
                    </td>

                    <td data-label="Volume" style="padding:12px 14px; font-weight:700; color:#334155; font-variant-numeric:tabular-nums;">
                        <?= number_format((float)$ps['jumlah'], 0, ',', '.') ?> <?= htmlspecialchars($ps['satuan']) ?>
                    </td>

                    <td data-label="Total Nilai" style="padding:12px 14px; font-weight:800; color:#064E3B; font-variant-numeric:tabular-nums;">
                        <?= rupiah($ps['total_nilai']) ?>
                    </td>

                    <td data-label="Status" style="padding:12px 14px;">
                        <span style="background:<?= $is_done ? '#DCFCE7' : '#FEF3C7' ?>; color:<?= $is_done ? '#166534' : '#92400E' ?>; padding:5px 11px; border-radius:99px; font-size:11.5px; font-weight:800; display:inline-block;">
                            <?= htmlspecialchars($ps['status_pasokan']) ?>
                        </span>
                    </td>

                    <?php if ($role === 'primer'): ?>
                    <td data-label="Tindakan" class="td-card-action" style="padding:12px 14px; text-align:center;">
                        <?php if (!$is_done): ?>
                            <a href="proses.php?terima=<?= (int)$ps['id_pasokan'] ?>"
                               onclick="return confirm('Konfirmasi terima pasokan <?= htmlspecialchars($ps['nama_komoditas'], ENT_QUOTES) ?> dari petani?')"
                               class="pas-btn"
                               style="background:#059669; color:#FFFFFF; padding:8px 14px; border-radius:9px; font-size:12px; font-weight:700; box-shadow:0 3px 10px rgba(5,150,105,0.2);">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                <span>Terima Pasokan</span>
                            </a>
                        <?php else: ?>
                            <span style="display:inline-flex; align-items:center; justify-content:center; gap:5px; color:#059669; font-size:12px; font-weight:700;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                <span>Terverifikasi</span>
                            </span>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL LIGHTBOX PRATINJAU FOTO PANEN -->
<div id="pasFotoModal" onclick="if(event.target===this) closePasFotoModal()" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.75); z-index:999; align-items:center; justify-content:center; padding:16px; backdrop-filter:blur(3px);">
    <div style="background:#FFFFFF; width:100%; max-width:480px; border-radius:20px; overflow:hidden; box-shadow:0 25px 50px rgba(0,0,0,0.3);">
        <div style="padding:14px 18px; border-bottom:1px solid #E2E8F0; display:flex; justify-content:space-between; align-items:center; gap:10px;">
            <div id="pasFotoModalTitle" style="font-size:14px; font-weight:800; color:#0F172A;">Bukti Foto Panen</div>
            <button type="button" onclick="closePasFotoModal()" style="background:#F1F5F9; border:none; width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; cursor:pointer;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#475569" stroke-width="2.4"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div style="padding:16px; background:#F8FAFC; text-align:center;">
            <img id="pasFotoModalImg" src="" alt="Bukti Foto" style="max-width:100%; max-height:65vh; border-radius:12px; object-fit:contain;">
        </div>
    </div>
</div>

<script>
// Kalkulator Estimasi Total Nilai Setoran
function hitungEstimasiPasokan() {
    const jmlEl = document.getElementById('pasJumlah');
    const hrgEl = document.getElementById('pasHarga');
    const outEl = document.getElementById('pasEstimasiTotal');
    if (!jmlEl || !hrgEl || !outEl) return;

    const jml = parseFloat(jmlEl.value) || 0;
    const hrg = parseFloat(hrgEl.value) || 0;
    const total = jml * hrg;
    outEl.textContent = 'Rp ' + total.toLocaleString('id-ID');
}

function setPasSatuan(unit) {
    const el = document.getElementById('pasSatuan');
    if (el) el.value = unit;
}

// Pratinjau Foto Sebelum Unggah
function previewFotoPasokan(input) {
    const preview = document.getElementById('pasFotoPreview');
    const nameLbl = document.getElementById('pasFotoName');
    if (input.files && input.files[0]) {
        const file = input.files[0];
        if (nameLbl) nameLbl.textContent = file.name;
        const reader = new FileReader();
        reader.onload = function(e) {
            if (preview) preview.src = e.target.result;
        };
        reader.readAsDataURL(file);
    }
}

// Filter & Pencarian Daftar Pasokan
let activePasStatus = 'ALL';
function filterPasokan(status, btn) {
    activePasStatus = status;
    document.querySelectorAll('#pasFilterBar .pas-pill').forEach(el => el.classList.remove('active'));
    btn.classList.add('active');
    applyPasokanFilters();
}

function applyPasokanFilters() {
    const q = (document.getElementById('pasSearchInput')?.value || '').toLowerCase().trim();
    document.querySelectorAll('.pas-data-row').forEach(row => {
        const st  = row.getAttribute('data-status');
        const txt = row.innerText.toLowerCase();
        const matchSt  = (activePasStatus === 'ALL' || st === activePasStatus);
        const matchTxt = txt.includes(q);
        row.style.display = (matchSt && matchTxt) ? '' : 'none';
    });
}

// Lightbox Foto
function openPasFotoModal(src, title) {
    const modal = document.getElementById('pasFotoModal');
    const img   = document.getElementById('pasFotoModalImg');
    const ttl   = document.getElementById('pasFotoModalTitle');
    if (img) img.src = src;
    if (ttl) ttl.textContent = title;
    if (modal) modal.style.display = 'flex';
}

function closePasFotoModal() {
    const modal = document.getElementById('pasFotoModal');
    if (modal) modal.style.display = 'none';
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