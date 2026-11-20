<?php
require_once '../../config/database.php';
cekAkses(['konsumen', 'primer', 'petani']);

// AUTO-MIGRATE: Otomatis membuat tabel harga_acuan_shu jika belum ada di database
$pdo->exec("CREATE TABLE IF NOT EXISTS `harga_acuan_shu` (
  `id_regulasi` int(11) NOT NULL AUTO_INCREMENT,
  `kode_regulasi` varchar(25) NOT NULL,
  `nama_komoditas` varchar(100) NOT NULL,
  `kategori` enum('Komoditas Tanaman','Saprodi','Alsintan','Produk Olahan','Produk Lainnya') NOT NULL,
  `satuan` varchar(20) NOT NULL,
  `hap_petani` decimal(12,2) NOT NULL,
  `het_buyer` decimal(12,2) NOT NULL,
  `persen_petani` decimal(5,2) NOT NULL DEFAULT 85.00,
  `persen_primer` decimal(5,2) NOT NULL DEFAULT 10.00,
  `persen_konsumen` decimal(5,2) NOT NULL DEFAULT 5.00,
  `status_regulasi` enum('Aktif','Arsip') DEFAULT 'Aktif',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_regulasi`),
  UNIQUE KEY `kode_regulasi` (`kode_regulasi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// Isi data awal standar jika tabel baru saja dibuat dan masih kosong
$cek_isi = $pdo->query("SELECT COUNT(*) FROM harga_acuan_shu")->fetchColumn();
if ($cek_isi == 0) {
    $pdo->exec("INSERT IGNORE INTO `harga_acuan_shu` (`kode_regulasi`, `nama_komoditas`, `kategori`, `satuan`, `hap_petani`, `het_buyer`, `persen_petani`, `persen_primer`, `persen_konsumen`, `status_regulasi`) VALUES
    ('REG-2026-001', 'Kentang Granola Grade A', 'Komoditas Tanaman', 'Kg', 12000.00, 14500.00, 85.00, 10.00, 5.00, 'Aktif'),
    ('REG-2026-002', 'Biji Kopi Arabika Green Bean', 'Produk Olahan', 'Kg', 68000.00, 82000.00, 84.00, 11.00, 5.00, 'Aktif'),
    ('REG-2026-003', 'Pupuk Organik Kompos Granul', 'Saprodi', 'Karung', 42000.00, 50000.00, 80.00, 13.00, 7.00, 'Aktif')");
}

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

$regulasi_list = $pdo->query("SELECT * FROM harga_acuan_shu ORDER BY id_regulasi DESC")->fetchAll();
$is_pusat = ($role === 'konsumen');
?>

<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px; margin-bottom:24px;">
    <div>
        <span style="display:inline-block; background:#FEF3C7; color:#92400E; font-size:11px; font-weight:800; padding:4px 12px; border-radius:99px; margin-bottom:6px;">
            PRICE & MARGIN CONTROL • REGULASI SHU
        </span>
        <h2 style="margin:0 0 4px; font-size:22px; color:#0F172A;">Penetapan Harga Acuan (HAP) & Margin Bagi Hasil</h2>
        <p style="margin:0; font-size:14px; color:#64748B;">
            Standarisasi harga beli hulu petani, harga jual hilir buyer, dan skema distribusi SHU antar aktor koperasi.
        </p>
    </div>
    <?php if ($is_pusat): ?>
    <button onclick="openModal('modalTambahRegulasi')" style="background:#059669; color:#FFFFFF; border:none; padding:12px 20px; border-radius:12px; font-weight:700; font-size:13.5px; cursor:pointer; box-shadow:0 4px 12px rgba(5,150,105,0.28);">
        + Tetapkan Harga Acuan Baru
    </button>
    <?php endif; ?>
</div>

<?php if (isset($_GET['msg'])): ?>
<div style="background:#DCFCE7; border:1px solid #86EFAC; color:#166534; padding:12px 18px; border-radius:12px; margin-bottom:20px; font-weight:700; font-size:13.5px;">
    ✓ <?= htmlspecialchars($_GET['msg']) ?>
</div>
<?php endif; ?>

<?php if (isset($_GET['err'])): ?>
<div style="background:#FEE2E2; border:1px solid #FCA5A5; color:#991B1B; padding:12px 18px; border-radius:12px; margin-bottom:20px; font-weight:700; font-size:13.5px;">
    ⚠ <?= htmlspecialchars($_GET['err']) ?>
</div>
<?php endif; ?>

<!-- KARTU SIMULATOR KALKULATOR BAGI HASIL SHU INTERAKTIF -->
<div style="background:linear-gradient(105deg, #064E3B 0%, #047857 100%); border-radius:20px; padding:24px 28px; color:#FFFFFF; margin-bottom:24px; box-shadow:0 12px 28px rgba(6,78,59,0.16);">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:16px;">
        <div>
            <h3 style="margin:0 0 4px; font-size:17px; font-weight:800;">Simulator Transparansi Bagi Hasil (Hulu → Hilir)</h3>
            <div style="font-size:12.5px; color:#D1FAE5;">Uji skema pembagian hasil penjualan dari Buyer ke Petani, Koperasi Primer, dan Koperasi Konsumen.</div>
        </div>
        <span style="background:rgba(255,255,255,0.15); border:1px solid rgba(255,255,255,0.3); padding:5px 12px; border-radius:99px; font-size:11.5px; font-weight:700;">
            Standar Rekomendasi: 85% : 10% : 5%
        </span>
    </div>

    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(170px, 1fr)); gap:14px; background:rgba(255,255,255,0.08); padding:16px; border-radius:14px; border:1px solid rgba(255,255,255,0.14);">
        <div>
            <label style="font-size:11px; font-weight:700; color:#A7F3D0;">Harga Jual Buyer (Rp)</label>
            <input type="number" id="simHarga" value="15000" oninput="hitungSimulasi()" style="width:100%; box-sizing:border-box; padding:9px 12px; border-radius:10px; border:none; margin-top:4px; font-weight:800; font-size:14px; color:#0F172A;">
        </div>
        <div>
            <label style="font-size:11px; font-weight:700; color:#A7F3D0;">Hak Petani (%)</label>
            <input type="number" id="simPetani" value="85" oninput="hitungSimulasi()" style="width:100%; box-sizing:border-box; padding:9px 12px; border-radius:10px; border:none; margin-top:4px; font-weight:800; font-size:14px; color:#0F172A;">
        </div>
        <div>
            <label style="font-size:11px; font-weight:700; color:#A7F3D0;">Kas Primer (%)</label>
            <input type="number" id="simPrimer" value="10" oninput="hitungSimulasi()" style="width:100%; box-sizing:border-box; padding:9px 12px; border-radius:10px; border:none; margin-top:4px; font-weight:800; font-size:14px; color:#0F172A;">
        </div>
        <div>
            <label style="font-size:11px; font-weight:700; color:#A7F3D0;">Kas Konsumen (%)</label>
            <input type="number" id="simKonsumen" value="5" oninput="hitungSimulasi()" style="width:100%; box-sizing:border-box; padding:9px 12px; border-radius:10px; border:none; margin-top:4px; font-weight:800; font-size:14px; color:#0F172A;">
        </div>
    </div>

    <!-- HASIL SIMULASI REAL-TIME -->
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:14px; margin-top:16px;">
        <div style="background:#FFFFFF; color:#0F172A; padding:14px 18px; border-radius:12px; border-left:5px solid #10B981;">
            <div style="font-size:11px; font-weight:800; color:#059669;">DITERIMA PETANI HULU</div>
            <div id="outPetani" style="font-size:20px; font-weight:900; margin-top:2px;">Rp 12.750</div>
        </div>
        <div style="background:#FFFFFF; color:#0F172A; padding:14px 18px; border-radius:12px; border-left:5px solid #F59E0B;">
            <div style="font-size:11px; font-weight:800; color:#D97706;">KAS KOPERASI PRIMER</div>
            <div id="outPrimer" style="font-size:20px; font-weight:900; margin-top:2px;">Rp 1.500</div>
        </div>
        <div style="background:#FFFFFF; color:#0F172A; padding:14px 18px; border-radius:12px; border-left:5px solid #3B82F6;">
            <div style="font-size:11px; font-weight:800; color:#2563EB;">KAS KOPERASI KONSUMEN</div>
            <div id="outKonsumen" style="font-size:20px; font-weight:900; margin-top:2px;">Rp 750</div>
        </div>
    </div>
</div>

<!-- TABEL DAFTAR REGULASI HARGA ACUAN & MARGIN -->
<div style="background:#FFFFFF; border-radius:16px; border:1px solid #E2E8F0; overflow:hidden; box-shadow:0 4px 12px rgba(0,0,0,0.02);">
    <div style="padding:18px 22px; border-bottom:1px solid #E2E8F0; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
        <div>
            <h3 style="margin:0; font-size:16.5px; color:#0F172A;">Daftar Ketetapan Harga Acuan & Rasio Margin Nasional</h3>
            <div style="font-size:12px; color:#64748B;">Menjadi pedoman resmi seluruh Koperasi Primer kecamatan agar tidak terjadi selisih harga antar-wilayah.</div>
        </div>
    </div>

    <div style="overflow-x:auto;">
        <table style="width:100%; border-collapse:collapse; text-align:left; font-size:13.5px;">
            <thead>
                <tr style="background:#064E3B; color:#FFFFFF;">
                    <th style="padding:14px;">Kode & Komoditas</th>
                    <th style="padding:14px;">HAP Petani (Hulu)</th>
                    <th style="padding:14px;">Harga Jual Buyer</th>
                    <th style="padding:14px;">Skema Bagi Hasil (Petani : Primer : Konsumen)</th>
                    <th style="padding:14px;">Status</th>
                    <?php if ($is_pusat): ?>
                        <th style="padding:14px; text-align:center;">Kontrol Pusat</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($regulasi_list)): ?>
                    <tr><td colspan="6" style="padding:26px; text-align:center; color:#64748B;">Belum ada ketetapan harga acuan yang diterbitkan.</td></tr>
                <?php endif; ?>
                <?php foreach ($regulasi_list as $r): 
                    $nom_petani   = ($r['het_buyer'] * $r['persen_petani']) / 100;
                    $nom_primer   = ($r['het_buyer'] * $r['persen_primer']) / 100;
                    $nom_konsumen = ($r['het_buyer'] * $r['persen_konsumen']) / 100;
                ?>
                <tr style="border-bottom:1px solid #F1F5F9;">
                    <td style="padding:14px;">
                        <span style="font-size:11px; font-family:monospace; font-weight:800; color:#059669;"><?= htmlspecialchars($r['kode_regulasi']) ?></span>
                        <div style="font-weight:800; color:#0F172A; font-size:14.5px;"><?= htmlspecialchars($r['nama_komoditas']) ?></div>
                        <div style="font-size:11.5px; color:#64748B;"><?= htmlspecialchars($r['kategori']) ?> • Satuan: <?= htmlspecialchars($r['satuan']) ?></div>
                    </td>
                    <td style="padding:14px;">
                        <div style="font-weight:800; color:#064E3B;"><?= rupiah($r['hap_petani']) ?></div>
                        <div style="font-size:11px; color:#64748B;">Batas minimum beli</div>
                    </td>
                    <td style="padding:14px;">
                        <div style="font-weight:800; color:#D97706;"><?= rupiah($r['het_buyer']) ?></div>
                        <div style="font-size:11px; color:#64748B;">Harga etalase pasar</div>
                    </td>
                    <td style="padding:14px; min-width:250px;">
                        <div style="display:flex; justify-content:space-between; font-size:11.5px; font-weight:800; margin-bottom:5px;">
                            <span style="color:#059669;">Petani <?= (float)$r['persen_petani'] ?>% (<?= rupiah($nom_petani) ?>)</span>
                            <span style="color:#D97706;">Prm <?= (float)$r['persen_primer'] ?>%</span>
                            <span style="color:#2563EB;">Ksm <?= (float)$r['persen_konsumen'] ?>%</span>
                        </div>
                        <div style="display:flex; height:8px; border-radius:99px; overflow:hidden; background:#E2E8F0;">
                            <div style="width:<?= $r['persen_petani'] ?>%; background:#10B981;" title="Hak Petani"></div>
                            <div style="width:<?= $r['persen_primer'] ?>%; background:#F59E0B;" title="Kas Primer"></div>
                            <div style="width:<?= $r['persen_konsumen'] ?>%; background:#3B82F6;" title="Kas Konsumen"></div>
                        </div>
                    </td>
                    <td style="padding:14px;">
                        <span style="background:<?= $r['status_regulasi']==='Aktif' ? '#DCFCE7' : '#F1F5F9' ?>; color:<?= $r['status_regulasi']==='Aktif' ? '#166534' : '#475569' ?>; padding:4px 11px; border-radius:99px; font-size:11.5px; font-weight:800;">
                            <?= $r['status_regulasi'] ?>
                        </span>
                    </td>
                    <?php if ($is_pusat): ?>
                    <td style="padding:14px; text-align:center; white-space:nowrap;">
                        <button onclick='openEditRegulasi(<?= json_encode($r) ?>)' style="background:#FEF3C7; color:#92400E; border:none; padding:7px 12px; border-radius:8px; font-weight:700; font-size:12px; cursor:pointer; margin-right:4px;">Ubah Tarif</button>
                        <a href="proses.php?hapus=<?= $r['id_regulasi'] ?>" onclick="return confirm('Hapus ketetapan harga acuan ini?')" style="background:#FEE2E2; color:#991B1B; text-decoration:none; padding:7px 12px; border-radius:8px; font-weight:700; font-size:12px;">Hapus</a>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($is_pusat): ?>
<!-- MODAL TAMBAH REGULASI HARGA -->
<div id="modalTambahRegulasi" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.65); z-index:999; align-items:center; justify-content:center; padding:18px;">
    <div style="background:#FFFFFF; width:100%; max-width:520px; border-radius:20px; padding:28px; box-shadow:0 25px 50px rgba(0,0,0,0.25);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px;">
            <h3 style="margin:0; font-size:18px; color:#0F172A;">Tetapkan Harga Acuan & Margin Baru</h3>
            <button onclick="closeModal('modalTambahRegulasi')" style="background:#F1F5F9; border:none; width:32px; height:32px; border-radius:50%; font-size:16px; cursor:pointer;">✕</button>
        </div>

        <form action="proses.php" method="POST" style="display:flex; flex-direction:column; gap:13px;">
            <input type="hidden" name="aksi" value="tambah_regulasi">

            <div>
                <label style="font-size:12px; font-weight:700; color:#334155;">Nama Komoditas Standar</label>
                <input type="text" name="nama_komoditas" placeholder="Contoh: Kubis Segar Grade A" required style="width:100%; box-sizing:border-box; padding:10px; border:1px solid #CBD5E1; border-radius:10px; margin-top:4px;">
            </div>

            <div style="display:grid; grid-template-columns:2fr 1fr; gap:12px;">
                <div>
                    <label style="font-size:12px; font-weight:700; color:#334155;">Kategori Klaster</label>
                    <select name="kategori" style="width:100%; box-sizing:border-box; padding:10px; border:1px solid #CBD5E1; border-radius:10px; margin-top:4px;">
                        <option value="Komoditas Tanaman">Komoditas Tanaman</option>
                        <option value="Saprodi">Saprodi</option>
                        <option value="Alsintan">Alsintan</option>
                        <option value="Produk Olahan">Produk Olahan</option>
                        <option value="Produk Lainnya">Produk Lainnya</option>
                    </select>
                </div>
                <div>
                    <label style="font-size:12px; font-weight:700; color:#334155;">Satuan</label>
                    <input type="text" name="satuan" value="Kg" required style="width:100%; box-sizing:border-box; padding:10px; border:1px solid #CBD5E1; border-radius:10px; margin-top:4px;">
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div>
                    <label style="font-size:12px; font-weight:700; color:#059669;">HAP Petani Hulu (Rp)</label>
                    <input type="number" name="hap_petani" placeholder="10000" required style="width:100%; box-sizing:border-box; padding:10px; border:1px solid #CBD5E1; border-radius:10px; margin-top:4px;">
                </div>
                <div>
                    <label style="font-size:12px; font-weight:700; color:#D97706;">Harga Jual Buyer (Rp)</label>
                    <input type="number" name="het_buyer" placeholder="12500" required style="width:100%; box-sizing:border-box; padding:10px; border:1px solid #CBD5E1; border-radius:10px; margin-top:4px;">
                </div>
            </div>

            <div style="background:#F8FAFC; padding:12px; border-radius:12px; border:1px solid #E2E8F0;">
                <div style="font-size:11.5px; font-weight:800; color:#334155; margin-bottom:8px;">Rasio Bagi Hasil SHU (Total harus 100%)</div>
                <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px;">
                    <div>
                        <label style="font-size:11px; color:#059669; font-weight:700;">Petani (%)</label>
                        <input type="number" step="0.1" name="persen_petani" value="85" required style="width:100%; box-sizing:border-box; padding:8px; border:1px solid #CBD5E1; border-radius:8px; margin-top:3px;">
                    </div>
                    <div>
                        <label style="font-size:11px; color:#D97706; font-weight:700;">Primer (%)</label>
                        <input type="number" step="0.1" name="persen_primer" value="10" required style="width:100%; box-sizing:border-box; padding:8px; border:1px solid #CBD5E1; border-radius:8px; margin-top:3px;">
                    </div>
                    <div>
                        <label style="font-size:11px; color:#2563EB; font-weight:700;">Konsumen (%)</label>
                        <input type="number" step="0.1" name="persen_konsumen" value="5" required style="width:100%; box-sizing:border-box; padding:8px; border:1px solid #CBD5E1; border-radius:8px; margin-top:3px;">
                    </div>
                </div>
            </div>

            <button type="submit" style="background:#064E3B; color:#FFFFFF; border:none; padding:13px; border-radius:12px; font-weight:700; font-size:14px; cursor:pointer;">
                Terbitkan Ketetapan Harga Nasional
            </button>
        </form>
    </div>
</div>

<!-- MODAL EDIT REGULASI HARGA -->
<div id="modalEditRegulasi" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.65); z-index:999; align-items:center; justify-content:center; padding:18px;">
    <div style="background:#FFFFFF; width:100%; max-width:520px; border-radius:20px; padding:28px; box-shadow:0 25px 50px rgba(0,0,0,0.25);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px;">
            <h3 style="margin:0; font-size:18px; color:#0F172A;">Perbarui Tarif & Rasio Margin</h3>
            <button onclick="closeModal('modalEditRegulasi')" style="background:#F1F5F9; border:none; width:32px; height:32px; border-radius:50%; font-size:16px; cursor:pointer;">✕</button>
        </div>

        <form action="proses.php" method="POST" style="display:flex; flex-direction:column; gap:13px;">
            <input type="hidden" name="aksi" value="edit_regulasi">
            <input type="hidden" name="id_regulasi" id="edit_id_regulasi">

            <div>
                <label style="font-size:12px; font-weight:700; color:#334155;">Nama Komoditas</label>
                <input type="text" name="nama_komoditas" id="edit_nama_komoditas" required style="width:100%; box-sizing:border-box; padding:10px; border:1px solid #CBD5E1; border-radius:10px; margin-top:4px;">
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div>
                    <label style="font-size:12px; font-weight:700; color:#059669;">HAP Petani Hulu (Rp)</label>
                    <input type="number" name="hap_petani" id="edit_hap_petani" required style="width:100%; box-sizing:border-box; padding:10px; border:1px solid #CBD5E1; border-radius:10px; margin-top:4px;">
                </div>
                <div>
                    <label style="font-size:12px; font-weight:700; color:#D97706;">Harga Jual Buyer (Rp)</label>
                    <input type="number" name="het_buyer" id="edit_het_buyer" required style="width:100%; box-sizing:border-box; padding:10px; border:1px solid #CBD5E1; border-radius:10px; margin-top:4px;">
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px;">
                <div>
                    <label style="font-size:11px; color:#059669; font-weight:700;">Petani (%)</label>
                    <input type="number" step="0.1" name="persen_petani" id="edit_persen_petani" required style="width:100%; box-sizing:border-box; padding:8px; border:1px solid #CBD5E1; border-radius:8px; margin-top:3px;">
                </div>
                <div>
                    <label style="font-size:11px; color:#D97706; font-weight:700;">Primer (%)</label>
                    <input type="number" step="0.1" name="persen_primer" id="edit_persen_primer" required style="width:100%; box-sizing:border-box; padding:8px; border:1px solid #CBD5E1; border-radius:8px; margin-top:3px;">
                </div>
                <div>
                    <label style="font-size:11px; color:#2563EB; font-weight:700;">Konsumen (%)</label>
                    <input type="number" step="0.1" name="persen_konsumen" id="edit_persen_konsumen" required style="width:100%; box-sizing:border-box; padding:8px; border:1px solid #CBD5E1; border-radius:8px; margin-top:3px;">
                </div>
            </div>

            <div>
                <label style="font-size:12px; font-weight:700; color:#334155;">Status Ketetapan</label>
                <select name="status_regulasi" id="edit_status_regulasi" style="width:100%; box-sizing:border-box; padding:10px; border:1px solid #CBD5E1; border-radius:10px; margin-top:4px;">
                    <option value="Aktif">Aktif</option>
                    <option value="Arsip">Arsip</option>
                </select>
            </div>

            <button type="submit" style="background:#D97706; color:#FFFFFF; border:none; padding:12px; border-radius:12px; font-weight:700; font-size:14px; cursor:pointer;">
                Simpan Perubahan Tarif
            </button>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
function openModal(id) { document.getElementById(id).style.display = 'flex'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; }

function openEditRegulasi(r) {
    document.getElementById('edit_id_regulasi').value = r.id_regulasi;
    document.getElementById('edit_nama_komoditas').value = r.nama_komoditas;
    document.getElementById('edit_hap_petani').value = r.hap_petani;
    document.getElementById('edit_het_buyer').value = r.het_buyer;
    document.getElementById('edit_persen_petani').value = r.persen_petani;
    document.getElementById('edit_persen_primer').value = r.persen_primer;
    document.getElementById('edit_persen_konsumen').value = r.persen_konsumen;
    document.getElementById('edit_status_regulasi').value = r.status_regulasi;
    openModal('modalEditRegulasi');
}

function formatRupiah(angka) {
    return 'Rp ' + Math.round(angka).toLocaleString('id-ID');
}

function hitungSimulasi() {
    const harga = parseFloat(document.getElementById('simHarga').value) || 0;
    const pPetani = parseFloat(document.getElementById('simPetani').value) || 0;
    const pPrimer = parseFloat(document.getElementById('simPrimer').value) || 0;
    const pKonsumen = parseFloat(document.getElementById('simKonsumen').value) || 0;

    document.getElementById('outPetani').textContent = formatRupiah((harga * pPetani) / 100);
    document.getElementById('outPrimer').textContent = formatRupiah((harga * pPrimer) / 100);
    document.getElementById('outKonsumen').textContent = formatRupiah((harga * pKonsumen) / 100);
}
</script>

<?php require_once '../../includes/footer.php'; ?>