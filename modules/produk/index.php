<?php
require_once '../../config/database.php';
cekAkses(['primer', 'konsumen']);
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

$primer_list = $pdo->query("SELECT p.*, d.nama_divisi FROM koperasi_primer p JOIN divisi_wilayah d ON p.id_divisi = d.id_divisi")->fetchAll();
$produk_list = $pdo->query("SELECT pr.*, kp.nama_koperasi, d.nama_divisi FROM produk_etalase pr JOIN koperasi_primer kp ON pr.id_primer = kp.id_primer JOIN divisi_wilayah d ON pr.id_divisi = d.id_divisi ORDER BY pr.id_produk DESC")->fetchAll();
?>

<div style="margin-bottom:24px;">
    <h2 style="margin:0 0 4px; font-size:22px;">Posting & Etalase Produk Koperasi (Upload Visual)</h2>
    <p style="margin:0; font-size:14px; color:#64748B;">Unggah foto produk berkualitas agar tampil menarik di etalase Online Shop Buyer.</p>
</div>

<?php if (isset($_GET['msg'])): ?>
<div style="background:#DCFCE7; color:#166534; padding:12px 16px; border-radius:10px; margin-bottom:20px; font-weight:600; font-size:14px;">
    ✓ <?= htmlspecialchars($_GET['msg']) ?>
</div>
<?php endif; ?>

<!-- FORM UPLOAD PRODUK BARU -->
<div style="background:#FFFFFF; padding:24px; border-radius:14px; border:1px solid #E2E8F0; margin-bottom:28px;">
    <h3 style="margin:0 0 16px; font-size:17px; color:#064E3B;">Posting Produk Baru ke Marketplace Buyer</h3>
    <form action="proses.php" method="POST" enctype="multipart/form-data" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:14px;">
        <input type="hidden" name="aksi" value="tambah_produk">
        <div>
            <label style="font-size:12px; font-weight:700; color:#475569;">Unit Koperasi Primer</label>
            <select name="primer_divisi" required style="width:100%; box-sizing:border-box; padding:10px; border:1px solid #CBD5E1; border-radius:8px; margin-top:4px;">
                <?php foreach ($primer_list as $kp): ?>
                    <option value="<?= $kp['id_primer'] ?>|<?= $kp['id_divisi'] ?>"><?= htmlspecialchars($kp['nama_koperasi']) ?> - <?= htmlspecialchars($kp['nama_divisi']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="font-size:12px; font-weight:700; color:#475569;">Nama Produk Jual</label>
            <input type="text" name="nama_produk" placeholder="Contoh: Kentang Granola Grade A" required style="width:100%; box-sizing:border-box; padding:10px; border:1px solid #CBD5E1; border-radius:8px; margin-top:4px;">
        </div>
        <div>
            <label style="font-size:12px; font-weight:700; color:#475569;">Kategori Komoditas</label>
            <select name="kategori" style="width:100%; box-sizing:border-box; padding:10px; border:1px solid #CBD5E1; border-radius:8px; margin-top:4px;">
                <option value="Komoditas Tanaman">Komoditas Tanaman</option>
                <option value="Saprodi">Saprodi</option>
                <option value="Alsintan">Alsintan</option>
                <option value="Produk Olahan">Produk Olahan</option>
                <option value="Produk Lainnya">Produk Lainnya</option>
            </select>
        </div>
        <div>
            <label style="font-size:12px; font-weight:700; color:#475569;">Harga Jual Buyer (Rp)</label>
            <input type="number" name="harga_jual" placeholder="15000" required style="width:100%; box-sizing:border-box; padding:10px; border:1px solid #CBD5E1; border-radius:8px; margin-top:4px;">
        </div>
        <div>
            <label style="font-size:12px; font-weight:700; color:#475569;">Stok & Satuan</label>
            <div style="display:flex; gap:6px; margin-top:4px;">
                <input type="number" name="stok" placeholder="Stok" required style="width:60%; padding:10px; border:1px solid #CBD5E1; border-radius:8px;">
                <input type="text" name="satuan" value="Kg" required style="width:40%; padding:10px; border:1px solid #CBD5E1; border-radius:8px;">
            </div>
        </div>
        <div>
            <label style="font-size:12px; font-weight:700; color:#475569;">Upload Foto Produk (Wajib Jelas)</label>
            <input type="file" name="foto_produk" accept="image/*" required style="width:100%; box-sizing:border-box; padding:8px; border:1px solid #CBD5E1; border-radius:8px; margin-top:4px;">
        </div>
        <div style="grid-column:1 / -1;">
            <label style="font-size:12px; font-weight:700; color:#475569;">Deskripsi Singkat Produk</label>
            <input type="text" name="deskripsi" placeholder="Kualitas sortir super, siap kirim partai besar maupun ritel..." style="width:100%; box-sizing:border-box; padding:10px; border:1px solid #CBD5E1; border-radius:8px; margin-top:4px;">
        </div>
        <div style="grid-column:1 / -1;">
            <button type="submit" style="background:#064E3B; color:#FFFFFF; border:none; padding:12px 24px; border-radius:10px; font-weight:700; cursor:pointer;">Publikasikan ke Etalase Buyer</button>
        </div>
    </form>
</div>

<!-- GRID PREVIEW PRODUK YANG SUDAH TAYANG -->
<div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(250px, 1fr)); gap:20px;">
    <?php foreach ($produk_list as $pr): ?>
    <div style="background:#FFFFFF; border-radius:14px; border:1px solid #E2E8F0; overflow:hidden; display:flex; flex-direction:column;">
        <img src="<?= BASE_URL ?>/uploads/produk/<?= htmlspecialchars($pr['foto_produk']) ?>" onerror="this.src='https://placehold.co/400x260/064e3b/ffffff?text=Foto+Produk'" style="width:100%; height:170px; object-fit:cover;">
        <div style="padding:16px; flex:1; display:flex; flex-direction:column; justify-content:space-between;">
            <div>
                <span style="font-size:11px; font-weight:700; color:#059669;"><?= htmlspecialchars($pr['nama_divisi']) ?></span>
                <h4 style="margin:4px 0 6px; font-size:16px; color:#0F172A;"><?= htmlspecialchars($pr['nama_produk']) ?></h4>
                <div style="font-size:12px; color:#64748B; margin-bottom:10px;"><?= htmlspecialchars($pr['nama_koperasi']) ?></div>
            </div>
            <div>
                <div style="font-size:17px; font-weight:800; color:#064E3B;"><?= rupiah($pr['harga_jual']) ?> <span style="font-size:12px; font-weight:500; color:#64748B;">/ <?= htmlspecialchars($pr['satuan']) ?></span></div>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:10px; padding-top:10px; border-top:1px solid #F1F5F9;">
                    <span style="font-size:12px; font-weight:600;">Stok: <?= $pr['stok'] ?> <?= htmlspecialchars($pr['satuan']) ?></span>
                    <a href="proses.php?hapus=<?= $pr['id_produk'] ?>" onclick="return confirm('Hapus produk ini dari etalase?')" style="color:#DC2626; font-size:12px; font-weight:700; text-decoration:none;">Hapus</a>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>