<?php
require_once '../../config/database.php';
cekAkses(['konsumen', 'primer', 'petani']);

$role = $_SESSION['user']['role'];
$user = $_SESSION['user'];

// SINKRONISASI OTOMATIS: Agregasi per (id_order, id_primer) tanpa kehilangan nominal beda kategori
$completed_orders = $pdo->query("SELECT o.id_order, pr.id_primer, pr.kategori, SUM(oi.subtotal) AS bruto
                                 FROM orders o
                                 JOIN order_items oi ON o.id_order = oi.id_order
                                 JOIN produk_etalase pr ON oi.id_produk = pr.id_produk
                                 WHERE o.status_order = 'Selesai'
                                 GROUP BY o.id_order, pr.id_primer, pr.kategori")->fetchAll();

// Kelompokkan terlebih dahulu per (id_order + id_primer) agar pembagian SHU tiap kategori terakumulasi utuh
$aggregated_settlements = [];
foreach ($completed_orders as $co) {
    $pairKey = $co['id_order'] . '_' . $co['id_primer'];

    $stmtReg = $pdo->prepare("SELECT persen_petani, persen_primer, persen_konsumen FROM harga_acuan_shu WHERE kategori = ? AND status_regulasi = 'Aktif' LIMIT 1");
    $stmtReg->execute([$co['kategori']]);
    $reg = $stmtReg->fetch();

    $p_petani   = $reg ? (float)$reg['persen_petani'] : 85.00;
    $p_primer   = $reg ? (float)$reg['persen_primer'] : 10.00;

    $bruto      = (float)$co['bruto'];
    $h_petani   = round(($bruto * $p_petani) / 100, 2);
    $k_primer   = round(($bruto * $p_primer) / 100, 2);
    $k_konsumen = round($bruto - $h_petani - $k_primer, 2);

    if (!isset($aggregated_settlements[$pairKey])) {
        $aggregated_settlements[$pairKey] = [
            'id_order'     => (int)$co['id_order'],
            'id_primer'    => (int)$co['id_primer'],
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

foreach ($aggregated_settlements as $agg) {
    $cek = $pdo->prepare("SELECT COUNT(*) FROM settlement_dana WHERE id_order = ? AND id_primer = ?");
    $cek->execute([$agg['id_order'], $agg['id_primer']]);

    if ((int)$cek->fetchColumn() === 0) {
        $kode_stl = 'STL-' . date('Ymd') . '-' . $agg['id_order'] . '-' . $agg['id_primer'];
        $ins = $pdo->prepare("INSERT IGNORE INTO settlement_dana (kode_settlement, id_order, id_primer, nominal_bruto, hak_petani, kas_primer, kas_konsumen) VALUES (?, ?, ?, ?, ?, ?, ?)");
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

// PROSES 1: Pencairan Dana dari Koperasi Konsumen Pusat ke Koperasi Primer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'cair_ke_primer') {
    verify_csrf();
    cekAkses(['konsumen']);

    $id_stl = (int)($_POST['id_settlement'] ?? 0);
    // Kunci transisi status: hanya boleh dari 'Menunggu Pencairan Pusat'
    $stmt = $pdo->prepare("UPDATE settlement_dana 
                           SET status_pencairan = 'Dicairkan ke Primer', tanggal_cair_primer = NOW() 
                           WHERE id_settlement = ? AND status_pencairan = 'Menunggu Pencairan Pusat'");
    $stmt->execute([$id_stl]);

    if ($stmt->rowCount() > 0) {
        header("Location: index.php?msg=Dana hak pembayaran & kas unit berhasil ditransfer ke rekening Koperasi Primer.");
    } else {
        header("Location: index.php?err=Data settlement tidak valid atau sudah dicairkan sebelumnya.");
    }
    exit;
}

// PROSES 2: Penerusan Dana dari Koperasi Primer ke Petani Pemasok Hulu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'teruskan_ke_petani') {
    verify_csrf();
    cekAkses(['konsumen', 'primer']);

    $id_stl = (int)($_POST['id_settlement'] ?? 0);

    if ($role === 'primer') {
        $id_primer_login = (int)($user['id_primer'] ?? 0);
        // Ownership check + state machine check: wajib berstatus 'Dicairkan ke Primer' dan milik primer terkait
        $stmt = $pdo->prepare("UPDATE settlement_dana 
                               SET status_pencairan = 'Diteruskan ke Petani', tanggal_cair_petani = NOW() 
                               WHERE id_settlement = ? AND id_primer = ? AND status_pencairan = 'Dicairkan ke Primer'");
        $stmt->execute([$id_stl, $id_primer_login]);
    } else {
        $stmt = $pdo->prepare("UPDATE settlement_dana 
                               SET status_pencairan = 'Diteruskan ke Petani', tanggal_cair_petani = NOW() 
                               WHERE id_settlement = ? AND status_pencairan = 'Dicairkan ke Primer'");
        $stmt->execute([$id_stl]);
    }

    if ($stmt->rowCount() > 0) {
        header("Location: index.php?msg=Dana hasil panen telah resmi diteruskan ke Petani Pemasok.");
    } else {
        header("Location: index.php?err=Dana belum dicairkan dari pusat atau bukan wewenang koperasi Anda.");
    }
    exit;
}

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

$sql = "SELECT s.*, o.no_invoice, o.metode_bayar, o.tanggal_order, u.nama_lengkap AS nama_buyer, kp.nama_koperasi, kp.kecamatan, kp.ketua_koperasi, d.nama_divisi
        FROM settlement_dana s
        JOIN orders o ON s.id_order = o.id_order
        JOIN users u ON o.id_buyer = u.id_user
        JOIN koperasi_primer kp ON s.id_primer = kp.id_primer
        JOIN divisi_wilayah d ON kp.id_divisi = d.id_divisi";

if (in_array($role, ['primer', 'petani'], true)) {
    $sql .= " WHERE s.id_primer = " . (int)($user['id_primer'] ?? 0);
}
$sql .= " ORDER BY s.id_settlement DESC";
$settlements = $pdo->query($sql)->fetchAll();

$tot_bruto     = 0;
$tot_petani    = 0;
$tot_primer    = 0;
$tot_konsumen  = 0;
$tot_pending   = 0;

foreach ($settlements as $st) {
    $tot_bruto    += $st['nominal_bruto'];
    $tot_petani   += $st['hak_petani'];
    $tot_primer   += $st['kas_primer'];
    $tot_konsumen += $st['kas_konsumen'];
    if ($st['status_pencairan'] !== 'Diteruskan ke Petani') {
        $tot_pending += ($st['hak_petani'] + $st['kas_primer']);
    }
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px; margin-bottom:24px;">
    <div>
        <span style="display:inline-block; background:#DCFCE7; color:#166534; font-size:11px; font-weight:800; padding:4px 12px; border-radius:99px; margin-bottom:6px;">
            TREASURY &amp; DISBURSEMENT • CASHFLOW SETTLEMENT
        </span>
        <h2 style="margin:0 0 4px; font-size:22px; color:#0F172A;">Rekap Keuangan &amp; Settlement Pencairan Dana</h2>
        <p style="margin:0; font-size:14px; color:#64748B;">
            Distribusi hak pembayaran dan SHU otomatis dari Koperasi Konsumen ke Koperasi Primer dan Petani Pemasok untuk transaksi berstatus <strong>Selesai</strong>.
        </p>
    </div>
    <a href="../transaksi/index.php" style="background:#FFFFFF; border:1px solid #CBD5E1; color:#0F172A; text-decoration:none; padding:11px 18px; border-radius:12px; font-weight:700; font-size:13px;">
        Kelola Status Pesanan Selesai →
    </a>
</div>

<?php if (isset($_GET['msg'])): ?>
<div style="background:#DCFCE7; border:1px solid #86EFAC; color:#166534; padding:12px 18px; border-radius:12px; margin-bottom:20px; font-weight:700; font-size:13.5px;">
    ✓ <?= htmlspecialchars($_GET['msg']) ?>
</div>
<?php endif; ?>

<?php if (isset($_GET['err'])): ?>
<div style="background:#FEE2E2; border:1px solid #FCA5A5; color:#991B1B; padding:12px 18px; border-radius:12px; margin-bottom:20px; font-weight:700; font-size:13.5px;">
    ✕ <?= htmlspecialchars($_GET['err']) ?>
</div>
<?php endif; ?>

<!-- 4 KARTU KPI ARUS KEUANGAN & SHU -->
<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:16px; margin-bottom:24px;">
    <div style="background:linear-gradient(135deg, #064E3B 0%, #047857 100%); color:#FFFFFF; padding:20px; border-radius:16px; box-shadow:0 8px 20px rgba(6,78,59,0.16);">
        <div style="font-size:11.5px; color:#A7F3D0; font-weight:800;">TOTAL DANA SETTLEMENT (BRUTO)</div>
        <div style="font-size:23px; font-weight:900; margin:6px 0 4px;"><?= rupiah($tot_bruto) ?></div>
        <div style="font-size:11.5px; color:#D1FAE5;">Dari <?= count($settlements) ?> batch transaksi selesai</div>
    </div>

    <div style="background:#FFFFFF; padding:20px; border-radius:16px; border:1px solid #E2E8F0; border-left:5px solid #10B981;">
        <div style="font-size:11.5px; color:#059669; font-weight:800;">ALOKASI HAK PETANI HULU</div>
        <div style="font-size:22px; font-weight:900; color:#0F172A; margin:6px 0 4px;"><?= rupiah($tot_petani) ?></div>
        <div style="font-size:11.5px; color:#64748B;">Diteruskan melalui Koperasi Primer</div>
    </div>

    <div style="background:#FFFFFF; padding:20px; border-radius:16px; border:1px solid #E2E8F0; border-left:5px solid #F59E0B;">
        <div style="font-size:11.5px; color:#D97706; font-weight:800;">KAS OPERASIONAL PRIMER</div>
        <div style="font-size:22px; font-weight:900; color:#0F172A; margin:6px 0 4px;"><?= rupiah($tot_primer) ?></div>
        <div style="font-size:11.5px; color:#64748B;">Margin kurasi &amp; logistik kecamatan</div>
    </div>

    <div style="background:#FFFFFF; padding:20px; border-radius:16px; border:1px solid #E2E8F0; border-left:5px solid #3B82F6;">
        <div style="font-size:11.5px; color:#2563EB; font-weight:800;">KAS KOPERASI KONSUMEN</div>
        <div style="font-size:22px; font-weight:900; color:#0F172A; margin:6px 0 4px;"><?= rupiah($tot_konsumen) ?></div>
        <div style="font-size:11.5px; color:#64748B;">SHU &amp; pengembangan ekosistem pusat</div>
    </div>
</div>

<!-- TABEL DAFTAR SETTLEMENT & PENCAIRAN DANA BERJENJANG -->
<div style="background:#FFFFFF; border-radius:16px; border:1px solid #E2E8F0; overflow:hidden; box-shadow:0 4px 12px rgba(0,0,0,0.02);">
    <div style="padding:18px 22px; border-bottom:1px solid #E2E8F0; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
        <div>
            <h3 style="margin:0; font-size:16.5px; color:#0F172A;">Buku Besar Settlement &amp; Status Transfer Pencairan</h3>
            <div style="font-size:12px; color:#64748B;">Alur pencairan: <strong>Koperasi Konsumen (Pusat)</strong> → <strong>Koperasi Primer</strong> → <strong>Petani Pemasok</strong>.</div>
        </div>
        <?php if ($tot_pending > 0): ?>
            <span style="background:#FEF3C7; color:#92400E; font-size:12px; font-weight:800; padding:6px 14px; border-radius:99px;">
                Menunggu Pelunasan Hulu: <?= rupiah($tot_pending) ?>
            </span>
        <?php endif; ?>
    </div>

    <div style="overflow-x:auto;">
        <table style="width:100%; border-collapse:collapse; text-align:left; font-size:13px;">
            <thead>
                <tr style="background:#064E3B; color:#FFFFFF;">
                    <th style="padding:13px 15px;">Kode Settlement &amp; Invoice</th>
                    <th style="padding:13px 15px;">Koperasi Primer Tujuan</th>
                    <th style="padding:13px 15px;">Rincian Distribusi Dana (SHU)</th>
                    <th style="padding:13px 15px;">Status Alur Transfer</th>
                    <th style="padding:13px 15px; text-align:center;">Otorisasi &amp; Slip</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($settlements)): ?>
                    <tr>
                        <td colspan="5" style="padding:36px 20px; text-align:center;">
                            <div style="font-weight:800; font-size:15px; color:#334155; margin-bottom:6px;">Belum Ada Transaksi Berstatus "Selesai" untuk Di-Settlement</div>
                            <div style="font-size:13px; color:#64748B; max-width:520px; margin:0 auto;">
                                Ubah status pesanan Buyer menjadi <strong>Selesai</strong> pada menu <em>Semua Transaksi Masuk</em> atau <em>Dashboard</em>, maka sistem otomatis menghitung pembagian dana di tabel ini.
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($settlements as $s): 
                    $st = $s['status_pencairan'];
                    if ($st === 'Diteruskan ke Petani') {
                        $badge_bg = '#DCFCE7'; $badge_col = '#166534';
                    } elseif ($st === 'Dicairkan ke Primer') {
                        $badge_bg = '#DBEAFE'; $badge_col = '#1E40AF';
                    } else {
                        $badge_bg = '#FEF3C7'; $badge_col = '#92400E';
                    }
                ?>
                <tr style="border-bottom:1px solid #F1F5F9;">
                    <td style="padding:13px 15px;">
                        <div style="font-family:monospace; font-weight:800; color:#059669; font-size:12px;"><?= htmlspecialchars($s['kode_settlement']) ?></div>
                        <div style="font-weight:800; color:#0F172A; margin-top:2px;"><?= htmlspecialchars($s['no_invoice']) ?></div>
                        <div style="font-size:11px; color:#64748B;">Buyer: <?= htmlspecialchars($s['nama_buyer']) ?></div>
                    </td>

                    <td style="padding:13px 15px;">
                        <div style="font-weight:800; color:#0F172A;"><?= htmlspecialchars($s['nama_koperasi']) ?></div>
                        <div style="font-size:11.5px; color:#64748B;">Kec. <?= htmlspecialchars($s['kecamatan']) ?> (<?= htmlspecialchars($s['nama_divisi']) ?>)</div>
                        <div style="font-size:11px; color:#475569;">PIC: <?= htmlspecialchars($s['ketua_koperasi']) ?></div>
                    </td>

                    <td style="padding:13px 15px; min-width:235px;">
                        <div style="font-weight:900; color:#0F172A; margin-bottom:4px;">Bruto: <?= rupiah($s['nominal_bruto']) ?></div>
                        <div style="display:flex; flex-direction:column; gap:2px; font-size:11.5px;">
                            <span style="color:#059669; font-weight:700;">• Hak Petani: <?= rupiah($s['hak_petani']) ?></span>
                            <span style="color:#D97706; font-weight:700;">• Kas Primer: <?= rupiah($s['kas_primer']) ?></span>
                            <span style="color:#2563EB; font-weight:700;">• Kas Konsumen: <?= rupiah($s['kas_konsumen']) ?></span>
                        </div>
                    </td>

                    <td style="padding:13px 15px;">
                        <span style="display:inline-block; background:<?= $badge_bg ?>; color:<?= $badge_col ?>; padding:5px 11px; border-radius:99px; font-size:11.5px; font-weight:800;">
                            <?= htmlspecialchars($st) ?>
                        </span>
                        <?php if (!empty($s['tanggal_cair_primer'])): ?>
                            <div style="font-size:10.5px; color:#64748B; margin-top:4px;">Ke Primer: <?= date('d/m/Y H:i', strtotime($s['tanggal_cair_primer'])) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($s['tanggal_cair_petani'])): ?>
                            <div style="font-size:10.5px; color:#059669; font-weight:600;">Ke Petani: <?= date('d/m/Y H:i', strtotime($s['tanggal_cair_petani'])) ?></div>
                        <?php endif; ?>
                    </td>

                    <td style="padding:13px 15px; text-align:center;">
                        <div style="display:flex; flex-direction:column; gap:6px; align-items:center;">
                            <?php if ($role === 'konsumen' && $st === 'Menunggu Pencairan Pusat'): ?>
                                <form method="POST" style="margin:0;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="aksi" value="cair_ke_primer">
                                    <input type="hidden" name="id_settlement" value="<?= $s['id_settlement'] ?>">
                                    <button type="submit" style="background:#059669; color:#FFFFFF; border:none; padding:7px 12px; border-radius:8px; font-weight:700; font-size:11.5px; cursor:pointer; width:175px;">
                                        💸 Cairkan ke Primer
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php if (in_array($role, ['konsumen', 'primer'], true) && $st === 'Dicairkan ke Primer'): ?>
                                <form method="POST" style="margin:0;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="aksi" value="teruskan_ke_petani">
                                    <input type="hidden" name="id_settlement" value="<?= $s['id_settlement'] ?>">
                                    <button type="submit" style="background:#D97706; color:#FFFFFF; border:none; padding:7px 12px; border-radius:8px; font-weight:700; font-size:11.5px; cursor:pointer; width:175px;">
                                        🌾 Teruskan ke Petani
                                    </button>
                                </form>
                            <?php endif; ?>

                            <a href="bukti_pencairan.php?id=<?= $s['id_settlement'] ?>" target="_blank" style="background:#F1F5F9; border:1px solid #CBD5E1; color:#0F172A; text-decoration:none; padding:6px 12px; border-radius:8px; font-weight:700; font-size:11.5px; width:150px; display:inline-block;">
                                🖨 Slip Pencairan
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>