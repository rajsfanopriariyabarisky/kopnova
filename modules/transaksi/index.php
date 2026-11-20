<?php
require_once '../../config/database.php';
cekAkses(['konsumen', 'primer', 'buyer']);
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

if ($role === 'buyer') {
    $stmt = $pdo->prepare("SELECT o.*, u.nama_lengkap as nama_buyer FROM orders o JOIN users u ON o.id_buyer = u.id_user WHERE o.id_buyer = ? ORDER BY o.id_order DESC");
    $stmt->execute([$user['id_user']]);
} else {
    $stmt = $pdo->query("SELECT o.*, u.nama_lengkap as nama_buyer FROM orders o JOIN users u ON o.id_buyer = u.id_user ORDER BY o.id_order DESC");
}
$orders = $stmt->fetchAll();
?>

<div style="margin-bottom:24px;">
    <h2 style="margin:0 0 4px; font-size:22px;"><?= $role === 'buyer' ? 'Riwayat Pesanan Saya' : 'Pusat Monitoring Transaksi Masuk' ?></h2>
    <p style="margin:0; font-size:14px; color:#64748B;">Rekap seluruh transaksi pembelian dari Buyer yang terhubung ke Koperasi Konsumen.</p>
</div>

<?php if (isset($_GET['msg'])): ?>
<div style="background:#DCFCE7; color:#166534; padding:12px 16px; border-radius:10px; margin-bottom:20px; font-weight:600; font-size:14px;">
    ✓ <?= htmlspecialchars($_GET['msg']) ?>
</div>
<?php endif; ?>

<div style="background:#FFFFFF; padding:24px; border-radius:14px; border:1px solid #E2E8F0;">
    <div style="overflow-x:auto;">
        <table style="width:100%; border-collapse:collapse; text-align:left; font-size:14px;">
            <thead>
                <tr style="background:#064E3B; color:#FFFFFF;">
                    <th style="padding:12px;">No. Invoice</th>
                    <th style="padding:12px;">Tanggal</th>
                    <th style="padding:12px;">Nama Buyer</th>
                    <th style="padding:12px;">Total Pembayaran</th>
                    <th style="padding:12px;">Metode</th>
                    <th style="padding:12px;">Alamat Tujuan</th>
                    <th style="padding:12px;">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                    <tr><td colspan="7" style="padding:20px; text-align:center; color:#64748B;">Belum ada data transaksi.</td></tr>
                <?php endif; ?>
                <?php foreach ($orders as $o): ?>
                <tr style="border-bottom:1px solid #E2E8F0;">
                    <td style="padding:12px; font-weight:700; color:#047857;"><?= htmlspecialchars($o['no_invoice']) ?></td>
                    <td style="padding:12px;"><?= date('d M Y H:i', strtotime($o['tanggal_order'])) ?></td>
                    <td style="padding:12px; font-weight:600;"><?= htmlspecialchars($o['nama_buyer']) ?></td>
                    <td style="padding:12px; font-weight:700;"><?= rupiah($o['total_bayar']) ?></td>
                    <td style="padding:12px;"><?= htmlspecialchars($o['metode_bayar']) ?></td>
                    <td style="padding:12px; font-size:13px; color:#475569;"><?= htmlspecialchars($o['alamat_pengiriman']) ?></td>
                    <td style="padding:12px;">
                        <span style="background:#FEF3C7; color:#92400E; padding:4px 10px; border-radius:99px; font-size:12px; font-weight:700;"><?= $o['status_order'] ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>