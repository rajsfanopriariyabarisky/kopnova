<?php
require_once '../../config/database.php';
cekAkses(['konsumen']);
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

$divisi_list = $pdo->query("SELECT * FROM divisi_wilayah ORDER BY id_divisi DESC")->fetchAll();
$primer_list = $pdo->query("SELECT p.*, d.nama_divisi FROM koperasi_primer p JOIN divisi_wilayah d ON p.id_divisi = d.id_divisi ORDER BY p.id_primer DESC")->fetchAll();
?>

<div style="margin-bottom:24px;">
    <h2 style="margin:0 0 4px; font-size:22px;">Manajemen Wilayah Universal & Koperasi Primer</h2>
    <p style="margin:0; font-size:14px; color:#64748B;">Tambahkan Divisi Kabupaten/Kota baru beserta daftar Kecamatan & Koperasi Primer di bawahnya.</p>
</div>

<?php if (isset($_GET['msg'])): ?>
<div style="background:#DCFCE7; color:#166534; padding:12px 16px; border-radius:10px; margin-bottom:20px; font-weight:600; font-size:14px;">
    ✓ <?= htmlspecialchars($_GET['msg']) ?>
</div>
<?php endif; ?>

<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr)); gap:22px; margin-bottom:28px;">
    <!-- FORM TAMBAH DIVISI KABUPATEN/KOTA -->
    <div style="background:#FFFFFF; padding:22px; border-radius:14px; border:1px solid #E2E8F0;">
        <h3 style="margin:0 0 14px; font-size:16px; color:#064E3B;">1. Tambah Divisi Kabupaten / Kota</h3>
        <form action="proses.php" method="POST" style="display:flex; flex-direction:column; gap:10px;">
            <input type="hidden" name="aksi" value="tambah_divisi">
            <input type="text" name="kode_divisi" placeholder="Kode Divisi (Contoh: DIV-TSM)" required style="padding:10px; border:1px solid #CBD5E1; border-radius:8px;">
            <input type="text" name="nama_divisi" placeholder="Nama Divisi (Contoh: Divisi Kabupaten Tasikmalaya)" required style="padding:10px; border:1px solid #CBD5E1; border-radius:8px;">
            <input type="text" name="provinsi" placeholder="Provinsi (Contoh: Jawa Barat)" required style="padding:10px; border:1px solid #CBD5E1; border-radius:8px;">
            <button type="submit" style="background:#064E3B; color:#FFFFFF; border:none; padding:11px; border-radius:8px; font-weight:700; cursor:pointer;">Simpan Divisi Wilayah</button>
        </form>
    </div>

    <!-- FORM TAMBAH KECAMATAN & KOPERASI PRIMER -->
    <div style="background:#FFFFFF; padding:22px; border-radius:14px; border:1px solid #E2E8F0;">
        <h3 style="margin:0 0 14px; font-size:16px; color:#064E3B;">2. Tambah Koperasi Primer Kecamatan</h3>
        <form action="proses.php" method="POST" style="display:flex; flex-direction:column; gap:10px;">
            <input type="hidden" name="aksi" value="tambah_primer">
            <select name="id_divisi" required style="padding:10px; border:1px solid #CBD5E1; border-radius:8px;">
                <option value="">-- Pilih Divisi Kabupaten/Kota --</option>
                <?php foreach ($divisi_list as $d): ?>
                    <option value="<?= $d['id_divisi'] ?>"><?= htmlspecialchars($d['nama_divisi']) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="kode_primer" placeholder="Kode Unit (Contoh: PRM-SMR)" required style="padding:10px; border:1px solid #CBD5E1; border-radius:8px;">
            <input type="text" name="kecamatan" placeholder="Nama Kecamatan (Contoh: Samarang)" required style="padding:10px; border:1px solid #CBD5E1; border-radius:8px;">
            <input type="text" name="nama_koperasi" placeholder="Nama Koperasi Primer" required style="padding:10px; border:1px solid #CBD5E1; border-radius:8px;">
            <input type="text" name="ketua_koperasi" placeholder="Nama Ketua Pengurus" required style="padding:10px; border:1px solid #CBD5E1; border-radius:8px;">
            <button type="submit" style="background:#D97706; color:#FFFFFF; border:none; padding:11px; border-radius:8px; font-weight:700; cursor:pointer;">Daftarkan Koperasi Primer</button>
        </form>
    </div>
</div>

<!-- DAFTAR KOPERASI PRIMER AKTIF -->
<div style="background:#FFFFFF; padding:24px; border-radius:14px; border:1px solid #E2E8F0;">
    <h3 style="margin:0 0 16px; font-size:17px;">Daftar Jaringan Koperasi Primer Lintas Wilayah</h3>
    <div style="overflow-x:auto;">
        <table style="width:100%; border-collapse:collapse; text-align:left; font-size:14px;">
            <thead>
                <tr style="background:#F8FAFC; border-bottom:2px solid #E2E8F0;">
                    <th style="padding:12px;">Divisi Kabupaten</th>
                    <th style="padding:12px;">Kecamatan</th>
                    <th style="padding:12px;">Kode</th>
                    <th style="padding:12px;">Nama Koperasi Primer</th>
                    <th style="padding:12px;">Ketua</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($primer_list as $pr): ?>
                <tr style="border-bottom:1px solid #E2E8F0;">
                    <td style="padding:12px; font-weight:700; color:#047857;"><?= htmlspecialchars($pr['nama_divisi']) ?></td>
                    <td style="padding:12px;"><?= htmlspecialchars($pr['kecamatan']) ?></td>
                    <td style="padding:12px;"><?= htmlspecialchars($pr['kode_primer']) ?></td>
                    <td style="padding:12px; font-weight:600;"><?= htmlspecialchars($pr['nama_koperasi']) ?></td>
                    <td style="padding:12px;"><?= htmlspecialchars($pr['ketua_koperasi']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>