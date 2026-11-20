<?php
require_once '../../config/database.php';
cekAkses(['buyer']);
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

$cart = $_SESSION['cart'] ?? [];
$items = [];
$grand_total = 0;

if (!empty($cart)) {
    $ids = implode(',', array_map('intval', array_keys($cart)));
    $stmt = $pdo->query("SELECT * FROM produk_etalase WHERE id_produk IN ($ids)");
    while ($row = $stmt->fetch()) {
        $qty = $cart[$row['id_produk']];
        $subtotal = $qty * $row['harga_jual'];
        $grand_total += $subtotal;
        $row['qty'] = $qty;
        $row['subtotal'] = $subtotal;
        $items[] = $row;
    }
}
?>

<div style="margin-bottom:24px;">
    <h2 style="margin:0 0 4px; font-size:22px;">Keranjang Belanja & Checkout</h2>
    <p style="margin:0; font-size:14px; color:#64748B;">Pesanan yang Anda checkout akan langsung tercatat di Dashboard Utama Koperasi Konsumen.</p>
</div>

<?php if (empty($items)): ?>
    <div style="background:#FFFFFF; padding:40px; border-radius:14px; text-align:center; border:1px solid #E2E8F0;">
        <h3 style="margin:0 0 10px;">Keranjang Belanja Anda Masih Kosong</h3>
        <a href="index.php" style="display:inline-block; background:#064E3B; color:#FFFFFF; text-decoration:none; padding:11px 22px; border-radius:8px; font-weight:700;">Mulai Pilih Produk</a>
    </div>
<?php else: ?>
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr)); gap:24px; align-items:start;">
        <!-- DAFTAR ITEM KERANJANG -->
        <div style="background:#FFFFFF; padding:24px; border-radius:14px; border:1px solid #E2E8F0; grid-column:span 2;">
            <table style="width:100%; border-collapse:collapse; text-align:left; font-size:14px;">
                <thead>
                    <tr style="border-bottom:2px solid #E2E8F0; color:#475569;">
                        <th style="padding:10px;">Produk</th>
                        <th style="padding:10px;">Harga</th>
                        <th style="padding:10px;">Jumlah</th>
                        <th style="padding:10px;">Subtotal</th>
                        <th style="padding:10px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $it): ?>
                    <tr style="border-bottom:1px solid #E2E8F0;">
                        <td style="padding:12px; display:flex; align-items:center; gap:12px;">
                            <img src="<?= BASE_URL ?>/uploads/produk/<?= htmlspecialchars($it['foto_produk']) ?>" onerror="this.src='https://placehold.co/60x60'" style="width:50px; height:50px; object-fit:cover; border-radius:8px;">
                            <strong><?= htmlspecialchars($it['nama_produk']) ?></strong>
                        </td>
                        <td style="padding:12px;"><?= rupiah($it['harga_jual']) ?></td>
                        <td style="padding:12px;"><?= $it['qty'] ?> <?= htmlspecialchars($it['satuan']) ?></td>
                        <td style="padding:12px; font-weight:700; color:#064E3B;"><?= rupiah($it['subtotal']) ?></td>
                        <td style="padding:12px;">
                            <a href="proses_cart.php?hapus=<?= $it['id_produk'] ?>" style="color:#DC2626; font-size:13px; font-weight:700; text-decoration:none;">Hapus</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- RINGKASAN CHECKOUT -->
        <div style="background:#FFFFFF; padding:24px; border-radius:14px; border:1px solid #E2E8F0;">
            <h3 style="margin:0 0 16px; font-size:17px;">Ringkasan Pembayaran</h3>
            <div style="display:flex; justify-content:space-between; font-size:18px; font-weight:800; color:#064E3B; padding-bottom:16px; border-bottom:1px solid #E2E8F0; margin-bottom:16px;">
                <span>Total Tagihan</span>
                <span><?= rupiah($grand_total) ?></span>
            </div>
            <form action="proses_cart.php" method="POST" style="display:flex; flex-direction:column; gap:12px;">
                <input type="hidden" name="aksi" value="checkout">
                <div>
                    <label style="font-size:12px; font-weight:700; color:#475569;">Metode Pembayaran</label>
                    <select name="metode_bayar" style="width:100%; box-sizing:border-box; padding:10px; border:1px solid #CBD5E1; border-radius:8px; margin-top:4px;">
                        <option value="QRIS Instan">QRIS Instan Koperasi</option>
                        <option value="Transfer Virtual Account">Transfer Virtual Account</option>
                        <option value="Termin B2B">Termin B2B</option>
                    </select>
                </div>
                <div>
                    <label style="font-size:12px; font-weight:700; color:#475569;">Alamat Pengiriman</label>
                    <textarea name="alamat_pengiriman" rows="3" required style="width:100%; box-sizing:border-box; padding:10px; border:1px solid #CBD5E1; border-radius:8px; margin-top:4px;"><?= htmlspecialchars($user['alamat'] ?? '') ?></textarea>
                </div>
                <button type="submit" style="background:#D97706; color:#FFFFFF; border:none; padding:13px; border-radius:10px; font-weight:800; font-size:15px; cursor:pointer;">Buat Pesanan Sekarang</button>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>