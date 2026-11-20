<?php
require_once '../../config/database.php';
cekAkses(['buyer']);
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

$produk_list = $pdo->query("SELECT pr.*, kp.nama_koperasi, kp.kecamatan, d.nama_divisi 
                            FROM produk_etalase pr 
                            JOIN koperasi_primer kp ON pr.id_primer = kp.id_primer 
                            JOIN divisi_wilayah d ON pr.id_divisi = d.id_divisi 
                            WHERE pr.stok > 0 ORDER BY pr.id_produk DESC")->fetchAll();
?>

<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:24px;">
    <div>
        <h2 style="margin:0 0 4px; font-size:24px; color:#0F172A;">KOPNOVA Agribusiness Store</h2>
        <p style="margin:0; font-size:14px; color:#64748B;">Pilih komoditas segar dan produk koperasi langsung dari sumbernya.</p>
    </div>
    <a href="keranjang.php" style="background:#064E3B; color:#FFFFFF; text-decoration:none; padding:12px 20px; border-radius:10px; font-weight:700; font-size:14px;">
        Lihat Keranjang Belanja (<?= $cart_count ?> Item) →
    </a>
</div>

<?php if (isset($_GET['msg'])): ?>
<div style="background:#DCFCE7; color:#166534; padding:12px 16px; border-radius:10px; margin-bottom:20px; font-weight:600; font-size:14px;">
    ✓ <?= htmlspecialchars($_GET['msg']) ?>
</div>
<?php endif; ?>

<?php if (empty($produk_list)): ?>
    <div style="background:#FFFFFF; padding:40px; border-radius:14px; text-align:center; border:1px solid #E2E8F0;">
        <h3 style="margin:0 0 8px;">Etalase Produk Masih Kosong</h3>
        <p style="color:#64748B; margin:0;">Silakan login terlebih dahulu sebagai akun <strong>Koperasi Primer</strong> untuk mengunggah foto & produk jualan.</p>
    </div>
<?php endif; ?>

<!-- GRID ONLINE SHOP -->
<div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(260px, 1fr)); gap:22px;">
    <?php foreach ($produk_list as $pr): ?>
    <div style="background:#FFFFFF; border-radius:16px; border:1px solid #E2E8F0; overflow:hidden; box-shadow:0 4px 12px rgba(0,0,0,0.04); display:flex; flex-direction:column;">
        <div style="position:relative;">
            <img src="<?= BASE_URL ?>/uploads/produk/<?= htmlspecialchars($pr['foto_produk']) ?>" onerror="this.src='https://placehold.co/400x260/064e3b/ffffff?text=Produk+KOPNOVA'" style="width:100%; height:190px; object-fit:cover;">
            <span style="position:absolute; top:12px; left:12px; background:rgba(6,78,59,0.9); color:#A7F3D0; font-size:11px; font-weight:700; padding:4px 10px; border-radius:99px;">
                <?= htmlspecialchars($pr['kategori']) ?>
            </span>
        </div>

        <div style="padding:18px; flex:1; display:flex; flex-direction:column; justify-content:space-between;">
            <div>
                <div style="font-size:11px; color:#D97706; font-weight:700;"><?= htmlspecialchars($pr['nama_divisi']) ?> • Kec. <?= htmlspecialchars($pr['kecamatan']) ?></div>
                <h3 style="margin:4px 0 6px; font-size:17px; color:#0F172A;"><?= htmlspecialchars($pr['nama_produk']) ?></h3>
                <p style="margin:0 0 12px; font-size:13px; color:#64748B;"><?= htmlspecialchars($pr['deskripsi']) ?></p>
            </div>

            <div>
                <div style="display:flex; justify-content:space-between; align-items:baseline; margin-bottom:12px;">
                    <div style="font-size:19px; font-weight:800; color:#064E3B;"><?= rupiah($pr['harga_jual']) ?><span style="font-size:12px; font-weight:500; color:#64748B;">/<?= htmlspecialchars($pr['satuan']) ?></span></div>
                    <div style="font-size:12px; color:#475569;">Tersedia: <strong><?= $pr['stok'] ?></strong></div>
                </div>

                <form action="proses_cart.php" method="POST" style="display:flex; gap:8px;">
                    <input type="hidden" name="aksi" value="add">
                    <input type="hidden" name="id_produk" value="<?= $pr['id_produk'] ?>">
                    <input type="number" name="qty" value="1" min="1" max="<?= $pr['stok'] ?>" style="width:68px; padding:9px; border:1px solid #CBD5E1; border-radius:8px; text-align:center; font-weight:700;">
                    <button type="submit" style="flex:1; background:#059669; color:#FFFFFF; border:none; padding:10px; border-radius:8px; font-weight:700; font-size:13px; cursor:pointer;">+ Keranjang</button>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>