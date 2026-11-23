<?php
require_once '../../config/database.php';
cekAkses(['petani', 'primer']);
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

$primer_list = $pdo->query("SELECT p.*, d.nama_divisi FROM koperasi_primer p JOIN divisi_wilayah d ON p.id_divisi = d.id_divisi")->fetchAll();

if ($role === 'petani') {
    $stmt = $pdo->prepare("SELECT ps.*, kp.nama_koperasi, kp.kecamatan FROM pasokan_petani ps JOIN koperasi_primer kp ON ps.id_primer = kp.id_primer WHERE ps.id_petani = ? ORDER BY ps.id_pasokan DESC");
    $stmt->execute([$user['id_user']]);
} else {
    $stmt = $pdo->query("SELECT ps.*, u.nama_lengkap as nama_petani, kp.nama_koperasi FROM pasokan_petani ps JOIN users u ON ps.id_petani = u.id_user JOIN koperasi_primer kp ON ps.id_primer = kp.id_primer ORDER BY ps.id_pasokan DESC");
}
$pasokan_list = $stmt->fetchAll();
?>

<div style="margin-bottom:24px;">
    <h2 style="margin:0 0 4px; font-size:22px;"><?= $role === 'petani' ? 'Pengajuan Setoran Panen ke Koperasi Primer' : 'Verifikasi Pasokan Masuk dari Petani' ?></h2>
    <p style="margin:0; font-size:14px; color:#64748B;">Alur hulu rantai pasok: Petani mengirimkan komoditas beserta bukti foto kualitas untuk diverifikasi oleh Koperasi Primer.</p>
</div>

<?php if (isset($_GET['msg'])): ?>
<div style="background:#DCFCE7; color:#166534; padding:12px 16px; border-radius:10px; margin-bottom:20px; font-weight:600; font-size:14px;">
    ✓ <?= htmlspecialchars($_GET['msg']) ?>
</div>
<?php endif; ?>

<?php if ($role === 'petani'): ?>
<div style="background:#FFFFFF; padding:24px; border-radius:14px; border:1px solid #E2E8F0; margin-bottom:26px;">
    <h3 style="margin:0 0 16px; font-size:17px; color:#064E3B;">Formulir Kirim Hasil Panen</h3>
    <form action="proses.php" method="POST" enctype="multipart/form-data" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:14px;">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="setor_petani">
        <div>
            <label style="font-size:12px; font-weight:700; color:#475569;">Koperasi Primer Tujuan</label>
            <select name="id_primer" required style="width:100%; box-sizing:border-box; padding:10px; border:1px solid #CBD5E1; border-radius:8px; margin-top:4px;">
                <?php foreach ($primer_list as $kp): ?>
                    <option value="<?= $kp['id_primer'] ?>"><?= htmlspecialchars($kp['nama_koperasi']) ?> (<?= htmlspecialchars($kp['nama_divisi']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="font-size:12px; font-weight:700; color:#475569;">Nama Komoditas</label>
            <input type="text" name="nama_komoditas" placeholder="Contoh: Kentang Granola Super" required style="width:100%; box-sizing:border-box; padding:10px; border:1px solid #CBD5E1; border-radius:8px; margin-top:4px;">
        </div>
        <div>
            <label style="font-size:12px; font-weight:700; color:#475569;">Kategori</label>
            <select name="kategori" style="width:100%; box-sizing:border-box; padding:10px; border:1px solid #CBD5E1; border-radius:8px; margin-top:4px;">
                <option value="Komoditas Tanaman">Komoditas Tanaman</option>
                <option value="Saprodi">Saprodi</option>
                <option value="Produk Olahan">Produk Olahan</option>
            </select>
        </div>
        <div>
            <label style="font-size:12px; font-weight:700; color:#475569;">Volume & Satuan</label>
            <div style="display:flex; gap:6px; margin-top:4px;">
                <input type="number" name="jumlah" placeholder="Jumlah" required style="width:60%; padding:10px; border:1px solid #CBD5E1; border-radius:8px;">
                <input type="text" name="satuan" value="Kg" required style="width:40%; padding:10px; border:1px solid #CBD5E1; border-radius:8px;">
            </div>
        </div>
        <div>
            <label style="font-size:12px; font-weight:700; color:#475569;">Harga Penawaran / Satuan (Rp)</label>
            <input type="number" name="harga_tawar" placeholder="12000" required style="width:100%; box-sizing:border-box; padding:10px; border:1px solid #CBD5E1; border-radius:8px; margin-top:4px;">
        </div>
        <div>
            <label style="font-size:12px; font-weight:700; color:#475569;">Upload Foto Hasil Panen</label>
            <input type="file" name="foto_pasokan" accept="image/*" required style="width:100%; box-sizing:border-box; padding:8px; border:1px solid #CBD5E1; border-radius:8px; margin-top:4px;">
        </div>
        <div style="grid-column:1 / -1;">
            <button type="submit" style="background:#059669; color:#FFFFFF; border:none; padding:12px 24px; border-radius:10px; font-weight:700; cursor:pointer;">Kirim Pasokan ke Koperasi Primer</button>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- TABEL RIWAYAT & VERIFIKASI PASOKAN -->
<div style="background:#FFFFFF; padding:24px; border-radius:14px; border:1px solid #E2E8F0;">
    <h3 style="margin:0 0 16px; font-size:17px;">Daftar Pasokan Petani</h3>
    <div style="overflow-x:auto;">
        <table style="width:100%; border-collapse:collapse; text-align:left; font-size:14px;">
            <thead>
                <tr style="background:#F8FAFC; border-bottom:2px solid #E2E8F0;">
                    <th style="padding:12px;">Foto</th>
                    <th style="padding:12px;">Kode</th>
                    <th style="padding:12px;">Komoditas</th>
                    <th style="padding:12px;">Volume</th>
                    <th style="padding:12px;">Total Nilai</th>
                    <th style="padding:12px;">Status</th>
                    <?php if ($role === 'primer'): ?><th style="padding:12px;">Aksi Verifikasi</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pasokan_list)): ?>
                    <tr><td colspan="7" style="padding:18px; text-align:center; color:#64748B;">Belum ada data pasokan panen.</td></tr>
                <?php endif; ?>
                <?php foreach ($pasokan_list as $ps): ?>
                <tr style="border-bottom:1px solid #E2E8F0;">
                    <td style="padding:12px;">
                        <img src="<?= BASE_URL ?>/uploads/pasokan/<?= htmlspecialchars($ps['foto_pasokan']) ?>" onerror="this.src='https://placehold.co/60x60/e2e8f0/475569?text=Panen'" style="width:54px; height:54px; object-fit:cover; border-radius:8px; border:1px solid #CBD5E1;">
                    </td>
                    <td style="padding:12px; font-weight:700; color:#047857;"><?= htmlspecialchars($ps['kode_pasokan']) ?></td>
                    <td style="padding:12px;">
                        <div style="font-weight:700;"><?= htmlspecialchars($ps['nama_komoditas']) ?></div>
                        <div style="font-size:12px; color:#64748B;"><?= htmlspecialchars($ps['nama_koperasi']) ?></div>
                    </td>
                    <td style="padding:12px;"><?= $ps['jumlah'] ?> <?= htmlspecialchars($ps['satuan']) ?></td>
                    <td style="padding:12px; font-weight:700;"><?= rupiah($ps['total_nilai']) ?></td>
                    <td style="padding:12px;">
                        <span style="background:<?= $ps['status_pasokan']=='Diterima Primer' ? '#DCFCE7' : '#FEF3C7' ?>; color:<?= $ps['status_pasokan']=='Diterima Primer' ? '#166534' : '#92400E' ?>; padding:4px 10px; border-radius:99px; font-size:12px; font-weight:700;">
                            <?= $ps['status_pasokan'] ?>
                        </span>
                    </td>
                    <?php if ($role === 'primer'): ?>
                    <td style="padding:12px;">
                        <?php if ($ps['status_pasokan'] === 'Menunggu Verifikasi'): ?>
                            <a href="proses.php?terima=<?= $ps['id_pasokan'] ?>" style="background:#059669; color:#FFFFFF; text-decoration:none; padding:6px 12px; border-radius:6px; font-size:12px; font-weight:700;">Terima Pasokan</a>
                        <?php else: ?>
                            <span style="color:#64748B; font-size:12px;">Terverifikasi</span>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>