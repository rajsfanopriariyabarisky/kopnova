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
    $no_sj    = 'SJ-KPN-' . date('Ymd') . '-' . str_pad($id_order, 4, '0', STR_PAD_LEFT);

    $pdo->prepare("UPDATE orders SET no_surat_jalan = COALESCE(no_surat_jalan, ?), status_order = 'Diproses Koperasi' WHERE id_order = ?")
        ->execute([$no_sj, $id_order]);

    $pdo->prepare("UPDATE order_items SET status_routing = 'Instruksi Terkirim' WHERE id_order = ? AND status_routing = 'Menunggu Instruksi'")
        ->execute([$id_order]);

    header("Location: index.php?msg=Instruksi Picking List multi-kecamatan berhasil diterbitkan ($no_sj).");
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

    if (!in_array($status_routing, $allowed_statuses, true)) {
        header("Location: index.php?err=Status routing tidak valid atau di luar wewenang role Anda.");
        exit;
    }

    if ($role === 'primer') {
        $id_primer_login = (int)($user['id_primer'] ?? 0);
        $stmt = $pdo->prepare("UPDATE order_items oi
                               JOIN produk_etalase pr ON oi.id_produk = pr.id_produk
                               SET oi.status_routing = ?
                               WHERE oi.id_item = ? AND pr.id_primer = ?");
        $stmt->execute([$status_routing, $id_item, $id_primer_login]);
    } else {
        $stmt = $pdo->prepare("UPDATE order_items SET status_routing = ? WHERE id_item = ?");
        $stmt->execute([$status_routing, $id_item]);
    }

    if ($stmt->rowCount() > 0) {
        header("Location: index.php?msg=Status kesiapan barang kecamatan berhasil diperbarui menjadi: $status_routing");
    } else {
        header("Location: index.php?err=Item tidak ditemukan atau bukan wewenang koperasi Anda.");
    }
    exit;
}

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

$orders = $pdo->query("SELECT o.*, u.nama_lengkap AS nama_buyer, u.no_hp AS hp_buyer 
                       FROM orders o 
                       JOIN users u ON o.id_buyer = u.id_user 
                       ORDER BY o.id_order DESC")->fetchAll();

$total_orders_aktif = count($orders);
$total_sj_terbit    = 0;
foreach ($orders as $ord) {
    if (!empty($ord['no_surat_jalan'])) $total_sj_terbit++;
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px; margin-bottom:24px;">
    <div>
        <span style="display:inline-block; background:#DBEAFE; color:#1E40AF; font-size:11px; font-weight:800; padding:4px 12px; border-radius:99px; margin-bottom:6px;">
            LOGISTICS & MULTI-DISTRICT ORDER ROUTING
        </span>
        <h2 style="margin:0 0 4px; font-size:22px; color:#0F172A;">Konsolidasi & Surat Jalan Multi-Kecamatan</h2>
        <p style="margin:0; font-size:14px; color:#64748B;">
            Pecah pesanan Buyer lintas kecamatan menjadi instruksi pengambilan barang (Picking List) per Koperasi Primer dan cetak Surat Jalan resmi.
        </p>
    </div>
    <a href="../transaksi/index.php" style="background:#FFFFFF; border:1px solid #CBD5E1; color:#0F172A; text-decoration:none; padding:11px 18px; border-radius:12px; font-weight:700; font-size:13px;">
        Lihat Buku Besar Transaksi →
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

<!-- RINGKASAN OPERASIONAL LOGISTIK -->
<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:16px; margin-bottom:24px;">
    <div style="background:#FFFFFF; padding:18px 20px; border-radius:14px; border:1px solid #E2E8F0; border-left:4px solid #059669;">
        <div style="font-size:12px; color:#64748B; font-weight:700;">Total Pesanan Masuk</div>
        <div style="font-size:26px; font-weight:900; color:#0F172A; margin-top:4px;"><?= $total_orders_aktif ?> Invoice</div>
    </div>
    <div style="background:#FFFFFF; padding:18px 20px; border-radius:14px; border:1px solid #E2E8F0; border-left:4px solid #2563EB;">
        <div style="font-size:12px; color:#64748B; font-weight:700;">Surat Jalan Diterbitkan</div>
        <div style="font-size:26px; font-weight:900; color:#0F172A; margin-top:4px;"><?= $total_sj_terbit ?> Dokumen</div>
    </div>
    <div style="background:#FFFFFF; padding:18px 20px; border-radius:14px; border:1px solid #E2E8F0; border-left:4px solid #D97706;">
        <div style="font-size:12px; color:#64748B; font-weight:700;">Menunggu Routing Pusat</div>
        <div style="font-size:26px; font-weight:900; color:#0F172A; margin-top:4px;"><?= max(0, $total_orders_aktif - $total_sj_terbit) ?> Pesanan</div>
    </div>
</div>

<?php if (empty($orders)): ?>
    <div style="background:#FFFFFF; border-radius:18px; border:1px solid #E2E8F0; padding:42px 24px; text-align:center;">
        <div style="font-size:17px; font-weight:800; color:#0F172A; margin-bottom:6px;">Belum Ada Pesanan Buyer untuk Dikonsolidasikan</div>
        <p style="font-size:13.5px; color:#64748B; max-width:520px; margin:0 auto 18px;">
            Ketika Buyer melakukan checkout beberapa produk dari kecamatan berbeda dalam satu keranjang, sistem otomatis mengelompokkan titik pengambilan (Picking List) di halaman ini.
        </p>
        <a href="../produk/index.php" style="display:inline-block; background:#059669; color:#FFFFFF; text-decoration:none; padding:10px 20px; border-radius:10px; font-weight:700; font-size:13px;">
            Cek Ketersediaan Stok Etalase
        </a>
    </div>
<?php else: ?>

    <div style="display:flex; flex-direction:column; gap:22px;">
        <?php foreach ($orders as $o): 
            $stmtItems = $pdo->prepare("SELECT oi.*, pr.nama_produk, pr.kode_produk, pr.satuan, kp.id_primer, kp.nama_koperasi, kp.kecamatan, kp.ketua_koperasi, d.nama_divisi 
                                        FROM order_items oi 
                                        JOIN produk_etalase pr ON oi.id_produk = pr.id_produk 
                                        JOIN koperasi_primer kp ON pr.id_primer = kp.id_primer 
                                        JOIN divisi_wilayah d ON pr.id_divisi = d.id_divisi 
                                        WHERE oi.id_order = ? 
                                        ORDER BY kp.id_primer ASC");
            $stmtItems->execute([$o['id_order']]);
            $items = $stmtItems->fetchAll();

            $grouped_primer = [];
            foreach ($items as $it) {
                if ($role === 'primer' && !empty($user['id_primer']) && (int)$it['id_primer'] !== (int)$user['id_primer']) {
                    continue;
                }
                $key = $it['id_primer'];
                if (!isset($grouped_primer[$key])) {
                    $grouped_primer[$key] = [
                        'nama_koperasi'  => $it['nama_koperasi'],
                        'kecamatan'      => $it['kecamatan'],
                        'nama_divisi'    => $it['nama_divisi'],
                        'ketua_koperasi' => $it['ketua_koperasi'],
                        'items'          => []
                    ];
                }
                $grouped_primer[$key]['items'][] = $it;
            }

            if (empty($grouped_primer)) continue;
            $jumlah_titik_ambil = count($grouped_primer);
        ?>

        <div style="background:#FFFFFF; border-radius:18px; border:1px solid #E2E8F0; overflow:hidden; box-shadow:0 6px 18px rgba(0,0,0,0.03);">
            <div style="background:#F8FAFC; padding:18px 24px; border-bottom:1px solid #E2E8F0; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px;">
                <div>
                    <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                        <span style="font-size:16px; font-weight:900; color:#064E3B;"><?= htmlspecialchars($o['no_invoice']) ?></span>
                        <span style="background:#FEF3C7; color:#92400E; font-size:11px; font-weight:800; padding:3px 10px; border-radius:99px;">
                            <?= $jumlah_titik_ambil ?> Titik Pengambilan Kecamatan
                        </span>
                        <?php if (!empty($o['no_surat_jalan'])): ?>
                            <span style="background:#DCFCE7; color:#166534; font-size:11px; font-weight:800; padding:3px 10px; border-radius:99px;">
                                SJ: <?= htmlspecialchars($o['no_surat_jalan']) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div style="font-size:13px; color:#334155; margin-top:4px;">
                        Buyer: <strong><?= htmlspecialchars($o['nama_buyer']) ?></strong> (<?= htmlspecialchars($o['hp_buyer'] ?: '-') ?>) • Tujuan: <?= htmlspecialchars($o['alamat_pengiriman']) ?>
                    </div>
                </div>

                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <?php if ($role === 'konsumen'): ?>
                        <form method="POST" style="margin:0;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="aksi" value="terbitkan_routing">
                            <input type="hidden" name="id_order" value="<?= $o['id_order'] ?>">
                            <button type="submit" style="background:#059669; color:#FFFFFF; border:none; padding:9px 16px; border-radius:10px; font-weight:700; font-size:12.5px; cursor:pointer;">
                                <?= empty($o['no_surat_jalan']) ? '⚡ Terbitkan Picking List & Surat Jalan' : '↻ Sinkronkan Instruksi Primer' ?>
                            </button>
                        </form>
                    <?php endif; ?>

                    <a href="surat_jalan.php?id_order=<?= $o['id_order'] ?>" target="_blank" style="background:#0F172A; color:#FFFFFF; text-decoration:none; padding:9px 16px; border-radius:10px; font-weight:700; font-size:12.5px; display:inline-flex; align-items:center; gap:6px;">
                        🖨 Cetak Surat Jalan & Invoice
                    </a>
                </div>
            </div>

            <div style="padding:20px 24px; display:grid; grid-template-columns:repeat(auto-fit, minmax(310px, 1fr)); gap:16px;">
                <?php $titik = 1; foreach ($grouped_primer as $gp): ?>
                <div style="border:1px solid #CBD5E1; border-radius:14px; padding:16px; background:#FAFBF7;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px; padding-bottom:10px; border-bottom:1px dashed #CBD5E1;">
                        <div>
                            <span style="font-size:10.5px; font-weight:800; color:#059669; text-transform:uppercase;">Titik Pick-Up #<?= $titik++ ?> • <?= htmlspecialchars($gp['nama_divisi']) ?></span>
                            <div style="font-size:15px; font-weight:800; color:#0F172A; margin-top:2px;"><?= htmlspecialchars($gp['nama_koperasi']) ?></div>
                            <div style="font-size:11.5px; color:#64748B;">Kec. <?= htmlspecialchars($gp['kecamatan']) ?> • PIC: <?= htmlspecialchars($gp['ketua_koperasi']) ?></div>
                        </div>
                    </div>

                    <div style="display:flex; flex-direction:column; gap:10px;">
                        <?php foreach ($gp['items'] as $item): 
                            $opsi_status = ($role === 'konsumen')
                                ? ['Menunggu Instruksi', 'Instruksi Terkirim', 'Siap Diambil Primer', 'Terkonsolidasi Pusat']
                                : ['Menunggu Instruksi', 'Instruksi Terkirim', 'Siap Diambil Primer'];
                        ?>
                        <div style="background:#FFFFFF; padding:10px 12px; border-radius:10px; border:1px solid #E2E8F0; display:flex; justify-content:space-between; align-items:center; gap:8px; flex-wrap:wrap;">
                            <div>
                                <div style="font-size:13px; font-weight:700; color:#0F172A;"><?= htmlspecialchars($item['nama_produk']) ?></div>
                                <div style="font-size:11.5px; color:#475569;">Volume Ambil: <strong><?= $item['qty'] ?> <?= htmlspecialchars($item['satuan']) ?></strong> • <?= rupiah($item['subtotal']) ?></div>
                            </div>

                            <form method="POST" style="margin:0;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="aksi" value="update_item_routing">
                                <input type="hidden" name="id_item" value="<?= $item['id_item'] ?>">
                                <select name="status_routing" onchange="this.form.submit()" style="padding:5px 8px; border-radius:7px; border:1px solid #CBD5E1; font-size:11px; font-weight:700; background:<?= $item['status_routing']==='Terkonsolidasi Pusat' ? '#DCFCE7' : '#FEF3C7' ?>; color:<?= $item['status_routing']==='Terkonsolidasi Pusat' ? '#166534' : '#92400E' ?>; cursor:pointer;">
                                    <?php foreach ($opsi_status as $sr): ?>
                                        <option value="<?= $sr ?>" <?= $item['status_routing'] === $sr ? 'selected' : '' ?>><?= $sr ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>