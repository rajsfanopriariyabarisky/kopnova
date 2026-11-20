<?php
require_once '../../config/database.php';

// FITUR INTERAKTIF: Update Status Pesanan Langsung dari Dashboard (Khusus Konsumen & Primer)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status_order'])) {
    if (in_array($_SESSION['user']['role'], ['konsumen', 'primer'])) {
        $stmtUpd = $pdo->prepare("UPDATE orders SET status_order = ? WHERE id_order = ?");
        $stmtUpd->execute([$_POST['status_order'], $_POST['id_order']]);
        header("Location: index.php?msg=Status pesanan berhasil diperbarui menjadi " . urlencode($_POST['status_order']));
        exit;
    }
}

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

$error = $_GET['error'] ?? '';
$msg   = $_GET['msg'] ?? '';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<?php if ($error): ?>
<div style="background:#FEE2E2; border:1px solid #FCA5A5; color:#991B1B; padding:12px 18px; border-radius:12px; margin-bottom:20px; font-weight:700; font-size:13.5px;">
    ⚠ <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<?php if ($msg): ?>
<div style="background:#DCFCE7; border:1px solid #86EFAC; color:#166534; padding:12px 18px; border-radius:12px; margin-bottom:20px; font-weight:700; font-size:13.5px;">
    ✓ <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<?php if ($role === 'konsumen'): ?>
    <?php
    // Query Metrik Utama Konsumen
    $jml_divisi    = $pdo->query("SELECT COUNT(*) FROM divisi_wilayah")->fetchColumn();
    $jml_primer    = $pdo->query("SELECT COUNT(*) FROM koperasi_primer")->fetchColumn();
    $jml_petani    = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'petani'")->fetchColumn();
    $jml_pasokan   = $pdo->query("SELECT COUNT(*) FROM pasokan_petani")->fetchColumn();
    $jml_produk    = $pdo->query("SELECT COUNT(*) FROM produk_etalase")->fetchColumn();
    $total_stok    = $pdo->query("SELECT COALESCE(SUM(stok),0) FROM produk_etalase")->fetchColumn();
    $total_order   = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    $total_omzet   = $pdo->query("SELECT COALESCE(SUM(total_bayar),0) FROM orders")->fetchColumn();

    // EARLY WARNING SYSTEM: Deteksi Produk dengan Stok < 50
    $ews_items = $pdo->query("SELECT pr.*, kp.nama_koperasi, kp.kecamatan,
                              COALESCE((SELECT SUM(oi.qty) FROM order_items oi WHERE oi.id_produk = pr.id_produk), 0) AS total_terjual
                              FROM produk_etalase pr
                              JOIN koperasi_primer kp ON pr.id_primer = kp.id_primer
                              WHERE pr.stok < 50
                              ORDER BY pr.stok ASC LIMIT 4")->fetchAll();

    // Query Pesanan Buyer Terbaru
    $orders = $pdo->query("SELECT o.*, u.nama_lengkap as nama_buyer 
                           FROM orders o 
                           JOIN users u ON o.id_buyer = u.id_user 
                           ORDER BY o.id_order DESC LIMIT 6")->fetchAll();

    // Query Monitoring Pasokan Petani Terbaru
    $pasokan_recent = $pdo->query("SELECT ps.*, u.nama_lengkap as nama_petani, kp.nama_koperasi 
                                   FROM pasokan_petani ps 
                                   JOIN users u ON ps.id_petani = u.id_user 
                                   JOIN koperasi_primer kp ON ps.id_primer = kp.id_primer 
                                   ORDER BY ps.id_pasokan DESC LIMIT 4")->fetchAll();

    // Data Chart 1: Performa Unit Koperasi Primer
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

    // Data Chart 2: Komposisi Kategori Produk
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

    <!-- 1. EXECUTIVE HERO BANNER -->
    <div style="background:linear-gradient(105deg, #064E3B 0%, #047857 60%, rgba(6,78,59,0.85) 100%), url('<?= BASE_URL ?>/assets/img/hero-bg.jpg'); background-size:cover; background-position:right center; border-radius:20px; padding:26px 32px; color:#FFFFFF; margin-bottom:24px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:20px; box-shadow:0 12px 28px rgba(6,78,59,0.16);">
        <div style="max-width:620px;">
            <span style="display:inline-block; background:rgba(255,255,255,0.16); border:1px solid rgba(167,243,208,0.35); color:#A7F3D0; font-size:11px; font-weight:800; padding:4px 12px; border-radius:99px; letter-spacing:0.6px; margin-bottom:10px;">
                EXECUTIVE COMMAND CENTER • KOPERASI KONSUMEN
            </span>
            <h2 style="margin:0 0 8px; font-size:24px; font-weight:800; line-height:1.2;">
                Kendali Penuh Rantai Pasok &amp; Transaksi Lintas Wilayah
            </h2>
            <p style="margin:0; font-size:13.5px; color:#D1FAE5; line-height:1.55;">
                Pantau analitik stok koperasi primer, deteksi dini stok menipis, dan verifikasi setiap pesanan Buyer secara real-time.
            </p>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="../wilayah/index.php" style="background:#F59E0B; color:#0F172A; text-decoration:none; padding:11px 18px; border-radius:12px; font-weight:800; font-size:13px; box-shadow:0 4px 12px rgba(245,158,11,0.35);">
                + Tambah Divisi / Kecamatan
            </a>
            <a href="../early_warning/index.php" style="background:rgba(255,255,255,0.15); border:1px solid rgba(255,255,255,0.35); color:#FFFFFF; text-decoration:none; padding:11px 18px; border-radius:12px; font-weight:700; font-size:13px;">
                Radar Stok Kritis (<?= count($ews_items) ?>)
            </a>
        </div>
    </div>

    <!-- PANEL NOTIFIKASI OTOMATIS: EARLY WARNING SYSTEM (MUNCUL JIKA ADA STOK < 50) -->
    <?php if (!empty($ews_items)): ?>
    <div style="background:linear-gradient(90deg, #FEF2F2 0%, #FFFBEB 100%); border:1px solid #FECACA; border-left:6px solid #DC2626; border-radius:16px; padding:18px 22px; margin-bottom:24px; box-shadow:0 6px 18px rgba(220,38,38,0.08);">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:14px;">
            <div style="display:flex; align-items:center; gap:10px;">
                <span style="background:#DC2626; color:#FFFFFF; font-size:11px; font-weight:900; padding:4px 10px; border-radius:99px; letter-spacing:0.4px;">
                    ⚠ PERINGATAN STOK KRITIS
                </span>
                <strong style="font-size:15px; color:#991B1B;">
                    Terdeteksi <?= count($ews_items) ?> Komoditas dengan Stok Menipis (&lt; 50)
                </strong>
            </div>
            <a href="../early_warning/index.php" style="background:#DC2626; color:#FFFFFF; text-decoration:none; padding:8px 16px; border-radius:10px; font-size:12px; font-weight:800;">
                Kirim Instruksi Pasokan ke Primer &amp; Petani →
            </a>
        </div>

        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(230px, 1fr)); gap:12px;">
            <?php foreach ($ews_items as $ew): ?>
            <div style="background:#FFFFFF; border:1px solid #FECACA; border-radius:12px; padding:12px 14px; display:flex; justify-content:space-between; align-items:center; gap:10px;">
                <div>
                    <div style="font-size:13.5px; font-weight:800; color:#0F172A;"><?= htmlspecialchars($ew['nama_produk']) ?></div>
                    <div style="font-size:11px; color:#64748B;"><?= htmlspecialchars($ew['nama_koperasi']) ?> • Terjual: <?= (int)$ew['total_terjual'] ?></div>
                </div>
                <div style="text-align:right; flex-shrink:0;">
                    <div style="font-size:15px; font-weight:900; color:#DC2626;"><?= (int)$ew['stok'] ?> <?= htmlspecialchars($ew['satuan']) ?></div>
                    <div style="font-size:10px; font-weight:700; color:#991B1B;">Sisa Stok</div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- 2. 4 KPI CARDS UTAMA -->
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:18px; margin-bottom:24px;">
        <div style="background:#FFFFFF; padding:20px 22px; border-radius:16px; border:1px solid #E2E8F0; box-shadow:0 4px 12px rgba(0,0,0,0.02); display:flex; justify-content:space-between; align-items:flex-start;">
            <div>
                <div style="font-size:12px; color:#64748B; font-weight:700;">Cakupan Divisi Wilayah</div>
                <div style="font-size:28px; font-weight:900; color:#0F172A; margin:6px 0 4px;"><?= $jml_divisi ?> Kab/Kota</div>
                <div style="font-size:12px; color:#059669; font-weight:600;">Terhubung <?= $jml_primer ?> Unit Primer</div>
            </div>
            <div style="width:42px; height:42px; border-radius:12px; background:#DCFCE7; color:#059669; display:flex; align-items:center; justify-content:center;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            </div>
        </div>

        <div style="background:#FFFFFF; padding:20px 22px; border-radius:16px; border:1px solid #E2E8F0; box-shadow:0 4px 12px rgba(0,0,0,0.02); display:flex; justify-content:space-between; align-items:flex-start;">
            <div>
                <div style="font-size:12px; color:#64748B; font-weight:700;">Stok Etalase Nasional</div>
                <div style="font-size:28px; font-weight:900; color:#0F172A; margin:6px 0 4px;"><?= number_format($total_stok, 0, ',', '.') ?></div>
                <div style="font-size:12px; color:#2563EB; font-weight:600;"><?= $jml_produk ?> Varian Siap Jual</div>
            </div>
            <div style="width:42px; height:42px; border-radius:12px; background:#DBEAFE; color:#2563EB; display:flex; align-items:center; justify-content:center;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
            </div>
        </div>

        <div style="background:#FFFFFF; padding:20px 22px; border-radius:16px; border:1px solid #E2E8F0; box-shadow:0 4px 12px rgba(0,0,0,0.02); display:flex; justify-content:space-between; align-items:flex-start;">
            <div>
                <div style="font-size:12px; color:#64748B; font-weight:700;">Pesanan Buyer Masuk</div>
                <div style="font-size:28px; font-weight:900; color:#0F172A; margin:6px 0 4px;"><?= $total_order ?> Order</div>
                <div style="font-size:12px; color:#D97706; font-weight:600;">Terpusat di Dashboard Ini</div>
            </div>
            <div style="width:42px; height:42px; border-radius:12px; background:#FEF3C7; color:#D97706; display:flex; align-items:center; justify-content:center;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
            </div>
        </div>

        <div style="background:#FFFFFF; padding:20px 22px; border-radius:16px; border:1px solid #E2E8F0; box-shadow:0 4px 12px rgba(0,0,0,0.02); display:flex; justify-content:space-between; align-items:flex-start;">
            <div>
                <div style="font-size:12px; color:#64748B; font-weight:700;">Akumulasi Nilai Transaksi</div>
                <div style="font-size:23px; font-weight:900; color:#064E3B; margin:8px 0 6px;"><?= rupiah($total_omzet) ?></div>
                <div style="font-size:12px; color:#7C3AED; font-weight:600;">QRIS • VA • Termin B2B</div>
            </div>
            <div style="width:42px; height:42px; border-radius:12px; background:#F3E8FF; color:#7C3AED; display:flex; align-items:center; justify-content:center;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            </div>
        </div>
    </div>

    <!-- 3. SECTION ANALYTICS CHARTS -->
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(340px, 1fr)); gap:22px; margin-bottom:24px;">
        <div style="background:#FFFFFF; border-radius:16px; border:1px solid #E2E8F0; padding:22px; grid-column:span 2; box-shadow:0 4px 12px rgba(0,0,0,0.02);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; flex-wrap:wrap; gap:8px;">
                <div>
                    <h3 style="margin:0; font-size:16.5px; color:#0F172A;">Grafik Kapasitas Stok &amp; Varian per Koperasi Primer</h3>
                    <div style="font-size:12px; color:#64748B;">Perbandingan kontribusi stok komoditas dari tiap kecamatan ke etalase pusat.</div>
                </div>
                <span style="background:#DCFCE7; color:#166534; font-size:11px; font-weight:800; padding:4px 10px; border-radius:99px;">LIVE DATA</span>
            </div>
            <div style="height:250px; width:100%;">
                <canvas id="chartPrimer"></canvas>
            </div>
        </div>

        <div style="background:#FFFFFF; border-radius:16px; border:1px solid #E2E8F0; padding:22px; box-shadow:0 4px 12px rgba(0,0,0,0.02);">
            <h3 style="margin:0 0 4px; font-size:16.5px; color:#0F172A;">Proporsi Klaster Produk</h3>
            <div style="font-size:12px; color:#64748B; margin-bottom:14px;">Distribusi stok berdasarkan kategori ICARE.</div>
            <div style="height:230px; width:100%; display:flex; align-items:center; justify-content:center;">
                <canvas id="chartKategori"></canvas>
            </div>
        </div>
    </div>

    <!-- 4. VISUALISASI ALUR RANTAI PASOK (HULU KE HILIR LIVE STATUS) -->
    <div style="background:#FFFFFF; border-radius:16px; border:1px solid #E2E8F0; padding:20px 24px; margin-bottom:24px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:8px;">
            <div style="font-size:15px; font-weight:800; color:#0F172A;">Alur Rantai Pasok Terintegrasi (Hulu → Hilir)</div>
            <span style="font-size:12px; color:#64748B;">Sinkronisasi Otomatis Antar 4 Role RBAC</span>
        </div>
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:14px;">
            <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-left:4px solid #10B981; padding:14px; border-radius:12px;">
                <div style="font-size:11px; font-weight:800; color:#059669;">TAHAP 1 • HULU PETANI</div>
                <div style="font-size:16px; font-weight:800; color:#0F172A; margin:4px 0;"><?= $jml_pasokan ?> Setoran Panen</div>
                <div style="font-size:12px; color:#64748B;">Dari <?= $jml_petani ?> akun petani terdaftar</div>
            </div>
            <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-left:4px solid #3B82F6; padding:14px; border-radius:12px;">
                <div style="font-size:11px; font-weight:800; color:#2563EB;">TAHAP 2 • KOPERASI PRIMER</div>
                <div style="font-size:16px; font-weight:800; color:#0F172A; margin:4px 0;"><?= $jml_primer ?> Unit Kecamatan</div>
                <div style="font-size:12px; color:#64748B;">Verifikasi mutu &amp; kurasi produk</div>
            </div>
            <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-left:4px solid #F59E0B; padding:14px; border-radius:12px;">
                <div style="font-size:11px; font-weight:800; color:#D97706;">TAHAP 3 • ETALASE KONSUMEN</div>
                <div style="font-size:16px; font-weight:800; color:#0F172A; margin:4px 0;"><?= $jml_produk ?> Katalog Aktif</div>
                <div style="font-size:12px; color:#64748B;">Siap dibeli melalui Marketplace</div>
            </div>
            <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-left:4px solid #8B5CF6; padding:14px; border-radius:12px;">
                <div style="font-size:11px; font-weight:800; color:#7C3AED;">TAHAP 4 • HILIR BUYER</div>
                <div style="font-size:16px; font-weight:800; color:#0F172A; margin:4px 0;"><?= $total_order ?> Transaksi Masuk</div>
                <div style="font-size:12px; color:#64748B;">Tercatat langsung di Pusat</div>
            </div>
        </div>
    </div>

    <!-- 5. GRID BAWAH: ARUS TRANSAKSI BUYER REAL-TIME & PANTAUAN PASOKAN HULU -->
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(340px, 1fr)); gap:22px; align-items:start;">
        <div style="background:#FFFFFF; border-radius:16px; border:1px solid #E2E8F0; padding:22px; grid-column:span 2; box-shadow:0 4px 12px rgba(0,0,0,0.02);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:10px;">
                <div>
                    <h3 style="margin:0; font-size:17px; color:#0F172A;">Arus Transaksi Buyer Terbaru (Real-Time)</h3>
                    <div style="font-size:12px; color:#64748B;">Kelola konfirmasi dan status pengiriman pesanan yang masuk dari Buyer.</div>
                </div>
                <a href="../transaksi/index.php" style="background:#F0FDF4; color:#059669; border:1px solid #BBF7D0; padding:7px 14px; border-radius:8px; font-size:12px; font-weight:700; text-decoration:none;">
                    Buku Besar Lengkap →
                </a>
            </div>

            <div style="overflow-x:auto;">
                <table style="width:100%; border-collapse:collapse; text-align:left; font-size:13.5px;">
                    <thead>
                        <tr style="background:#F8FAFC; border-bottom:2px solid #E2E8F0; color:#475569;">
                            <th style="padding:12px;">No. Invoice</th>
                            <th style="padding:12px;">Buyer</th>
                            <th style="padding:12px;">Total Nominal</th>
                            <th style="padding:12px;">Metode</th>
                            <th style="padding:12px;">Status &amp; Kontrol</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="5" style="padding:28px 16px; text-align:center;">
                                    <div style="font-weight:700; color:#334155; margin-bottom:4px;">Belum ada pesanan masuk dari Buyer</div>
                                    <div style="font-size:12.5px; color:#64748B;">Simulasikan pembelian melalui akun <strong>Buyer</strong> untuk melihat transaksi masuk otomatis ke tabel ini.</div>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($orders as $o): ?>
                        <tr style="border-bottom:1px solid #F1F5F9;">
                            <td style="padding:12px;">
                                <div style="font-weight:800; color:#064E3B;"><?= htmlspecialchars($o['no_invoice']) ?></div>
                                <div style="font-size:11px; color:#64748B;"><?= date('d M Y, H:i', strtotime($o['tanggal_order'])) ?></div>
                            </td>
                            <td style="padding:12px; font-weight:600;"><?= htmlspecialchars($o['nama_buyer']) ?></td>
                            <td style="padding:12px; font-weight:800; color:#0F172A;"><?= rupiah($o['total_bayar']) ?></td>
                            <td style="padding:12px;">
                                <span style="background:#F1F5F9; color:#334155; padding:4px 9px; border-radius:6px; font-size:11.5px; font-weight:600;">
                                    <?= htmlspecialchars($o['metode_bayar']) ?>
                                </span>
                            </td>
                            <td style="padding:12px;">
                                <form method="POST" style="display:flex; gap:6px; align-items:center;">
                                    <input type="hidden" name="update_status_order" value="1">
                                    <input type="hidden" name="id_order" value="<?= $o['id_order'] ?>">
                                    <select name="status_order" onchange="this.form.submit()" style="padding:6px 10px; border-radius:8px; border:1px solid #CBD5E1; font-size:12px; font-weight:700; background:#FEF3C7; color:#92400E; cursor:pointer;">
                                        <?php foreach (['Menunggu Konfirmasi', 'Diproses Koperasi', 'Dikirim', 'Selesai'] as $st): ?>
                                            <option value="<?= $st ?>" <?= $o['status_order'] === $st ? 'selected' : '' ?>><?= $st ?></option>
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

        <div style="background:#FFFFFF; border-radius:16px; border:1px solid #E2E8F0; padding:22px; box-shadow:0 4px 12px rgba(0,0,0,0.02);">
            <h3 style="margin:0 0 4px; font-size:16px; color:#0F172A;">Aktivitas Pasokan Hulu Terbaru</h3>
            <p style="margin:0 0 14px; font-size:12px; color:#64748B;">Setoran panen petani ke Koperasi Primer.</p>

            <?php if (empty($pasokan_recent)): ?>
                <div style="font-size:12.5px; color:#64748B; background:#F8FAFC; padding:14px; border-radius:10px; line-height:1.5;">
                    Belum ada aktivitas setoran panen dari petani. Login sebagai akun <strong>Petani</strong> untuk mengirim komoditas panen.
                </div>
            <?php else: ?>
                <div style="display:flex; flex-direction:column; gap:10px;">
                    <?php foreach ($pasokan_recent as $pr): ?>
                    <div style="padding:10px 12px; background:#F8FAFC; border:1px solid #E2E8F0; border-radius:10px; display:flex; justify-content:space-between; align-items:center; gap:8px;">
                        <div>
                            <div style="font-size:13px; font-weight:700; color:#0F172A;"><?= htmlspecialchars($pr['nama_komoditas']) ?></div>
                            <div style="font-size:11px; color:#64748B;"><?= htmlspecialchars($pr['nama_petani']) ?> → <?= htmlspecialchars($pr['nama_koperasi']) ?></div>
                        </div>
                        <span style="background:<?= $pr['status_pasokan']=='Diterima Primer' ? '#DCFCE7' : '#FEF3C7' ?>; color:<?= $pr['status_pasokan']=='Diterima Primer' ? '#166534' : '#92400E' ?>; font-size:10.5px; font-weight:800; padding:3px 8px; border-radius:99px; white-space:nowrap;">
                            <?= $pr['status_pasokan'] ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        const ctxPrimer = document.getElementById('chartPrimer').getContext('2d');
        new Chart(ctxPrimer, {
            type: 'bar',
            data: {
                labels: <?= json_encode($labels_primer) ?>,
                datasets: [
                    { label: 'Total Volume Stok', data: <?= json_encode($data_stok_primer) ?>, backgroundColor: '#10B981', borderRadius: 8, barPercentage: 0.55 },
                    { label: 'Jumlah Varian Produk', data: <?= json_encode($data_item_primer) ?>, backgroundColor: '#F59E0B', borderRadius: 8, barPercentage: 0.55 }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'top', labels: { usePointStyle: true, font: { size: 12, weight: 'bold' } } } },
                scales: { y: { beginAtZero: true, grid: { color: '#F1F5F9' } }, x: { grid: { display: false } } }
            }
        });

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
                plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, font: { size: 11 } } } }
            }
        });
    </script>

<?php elseif ($role === 'primer'): ?>
    <?php
    $pasokan_pending = $pdo->query("SELECT COUNT(*) FROM pasokan_petani WHERE status_pasokan = 'Menunggu Verifikasi'")->fetchColumn();
    $produk_tayang   = $pdo->query("SELECT COUNT(*) FROM produk_etalase")->fetchColumn();
    ?>
    <div style="background:linear-gradient(105deg, #064E3B 0%, #047857 100%); color:#FFFFFF; padding:28px 32px; border-radius:20px; margin-bottom:24px;">
        <span style="background:rgba(255,255,255,0.16); color:#A7F3D0; font-size:11px; font-weight:800; padding:4px 12px; border-radius:99px;">UNIT OPERASIONAL KECAMATAN</span>
        <h2 style="margin:10px 0 6px; font-size:24px;"><?= htmlspecialchars($user['nama_koperasi'] ?? 'Koperasi Primer') ?></h2>
        <p style="margin:0 0 20px; color:#D1FAE5; font-size:14px; max-width:620px;">
            Verifikasi kualitas pasokan panen dari petani wilayah Kecamatan <?= htmlspecialchars($user['kecamatan'] ?? '-') ?>, lalu unggah foto produk terbaik ke etalase penjualan Buyer.
        </p>
        <div style="display:flex; gap:12px; flex-wrap:wrap;">
            <a href="../pasokan/index.php" style="background:#F59E0B; color:#0F172A; padding:11px 20px; border-radius:12px; text-decoration:none; font-weight:800; font-size:13.5px;">
                Verifikasi Pasokan (<?= $pasokan_pending ?> Menunggu)
            </a>
            <a href="../early_warning/index.php" style="background:#DC2626; color:#FFFFFF; padding:11px 20px; border-radius:12px; text-decoration:none; font-weight:800; font-size:13.5px;">
                Cek Instruksi Restock Pusat
            </a>
            <a href="../produk/index.php" style="background:#FFFFFF; color:#064E3B; padding:11px 20px; border-radius:12px; text-decoration:none; font-weight:800; font-size:13.5px;">
                + Upload Foto &amp; Produk Jual (<?= $produk_tayang ?> Tayang)
            </a>
        </div>
    </div>

<?php elseif ($role === 'petani'): ?>
    <?php
    $stmtP = $pdo->prepare("SELECT COUNT(*) as tot, COALESCE(SUM(total_nilai),0) as nilai FROM pasokan_petani WHERE id_petani = ?");
    $stmtP->execute([$user['id_user']]);
    $statPetani = $stmtP->fetch();
    ?>
    <div style="background:linear-gradient(105deg, #064E3B 0%, #047857 100%); color:#FFFFFF; padding:28px 32px; border-radius:20px; margin-bottom:24px;">
        <span style="background:rgba(255,255,255,0.16); color:#A7F3D0; font-size:11px; font-weight:800; padding:4px 12px; border-radius:99px;">PORTAL PRODUSEN HULU</span>
        <h2 style="margin:10px 0 6px; font-size:24px;">Halo, <?= htmlspecialchars($user['nama_lengkap']) ?></h2>
        <p style="margin:0 0 20px; color:#D1FAE5; font-size:14px; max-width:620px;">
            Total <?= $statPetani['tot'] ?> pengajuan panen tercatat dengan estimasi nilai pasokan <strong><?= rupiah($statPetani['nilai']) ?></strong>. Setorkan hasil panen terbaru beserta foto kualitas ke Koperasi Primer.
        </p>
        <div style="display:flex; gap:12px; flex-wrap:wrap;">
            <a href="../pasokan/index.php" style="background:#F59E0B; color:#0F172A; padding:12px 24px; border-radius:12px; text-decoration:none; font-weight:800; font-size:14px;">
                + Ajukan Setoran Hasil Panen
            </a>
            <a href="../early_warning/index.php" style="background:rgba(255,255,255,0.16); border:1px solid rgba(255,255,255,0.35); color:#FFFFFF; padding:12px 20px; border-radius:12px; text-decoration:none; font-weight:700; font-size:14px;">
                Lihat Permintaan Panen Pusat
            </a>
        </div>
    </div>

<?php elseif ($role === 'buyer'): ?>
    <div style="background:linear-gradient(105deg, #064E3B 0%, #047857 70%), url('<?= BASE_URL ?>/assets/img/hero-bg.jpg'); background-size:cover; background-position:right center; color:#FFFFFF; padding:32px; border-radius:20px;">
        <span style="background:rgba(255,255,255,0.16); color:#A7F3D0; font-size:11px; font-weight:800; padding:4px 12px; border-radius:99px;">KOPNOVA AGRIBUSINESS STORE</span>
        <h2 style="margin:10px 0 8px; font-size:26px;">Belanja Komoditas Langsung dari Jaringan Koperasi</h2>
        <p style="color:#D1FAE5; margin:0 0 22px; max-width:580px; font-size:14px;">
            Pilih berbagai komoditas segar, saprodi, dan produk olahan berkualitas dari berbagai divisi kabupaten dengan transparansi stok dan pengiriman terjamin.
        </p>
        <div style="display:flex; gap:12px; flex-wrap:wrap;">
            <a href="../marketplace/index.php" style="background:#F59E0B; color:#0F172A; padding:12px 24px; border-radius:12px; text-decoration:none; font-weight:800; font-size:14px;">
                Jelajahi Katalog Produk →
            </a>
            <a href="../marketplace/keranjang.php" style="background:rgba(255,255,255,0.16); border:1px solid rgba(255,255,255,0.35); color:#FFFFFF; padding:12px 20px; border-radius:12px; text-decoration:none; font-weight:700; font-size:14px;">
                Keranjang Belanja (<?= $cart_count ?>)
            </a>
        </div>
    </div>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>