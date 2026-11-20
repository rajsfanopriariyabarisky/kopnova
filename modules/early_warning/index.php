<?php
require_once '../../config/database.php';
cekAkses(['konsumen', 'primer', 'petani']);

// AUTO-MIGRATE: Buat tabel instruksi_pasokan otomatis jika belum ada
$pdo->exec("CREATE TABLE IF NOT EXISTS `instruksi_pasokan` (
  `id_instruksi` int(11) NOT NULL AUTO_INCREMENT,
  `kode_instruksi` varchar(30) NOT NULL,
  `id_primer` int(11) NOT NULL,
  `nama_komoditas` varchar(100) NOT NULL,
  `kategori` enum('Komoditas Tanaman','Saprodi','Alsintan','Produk Olahan','Produk Lainnya') NOT NULL,
  `target_jumlah` int(11) NOT NULL,
  `satuan` varchar(20) NOT NULL,
  `tingkat_urgensi` enum('Kritis (< 20)','Waspada (< 50)','Prediksi Musiman') DEFAULT 'Waspada (< 50)',
  `catatan_pusat` text DEFAULT NULL,
  `status_instruksi` enum('Terkirim ke Primer','Diteruskan ke Petani','Terpenuhi') DEFAULT 'Terkirim ke Primer',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id_instruksi`),
  UNIQUE KEY `kode_instruksi` (`kode_instruksi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// PROSES 1: Kirim Instruksi Pengadaan Pasokan dari Konsumen Pusat ke Koperasi Primer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'kirim_instruksi') {
    if ($_SESSION['user']['role'] === 'konsumen') {
        $kode = 'EWS-' . date('ymd') . '-' . rand(100, 999);
        $stmt = $pdo->prepare("INSERT INTO instruksi_pasokan (kode_instruksi, id_primer, nama_komoditas, kategori, target_jumlah, satuan, tingkat_urgensi, catatan_pusat) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $kode,
            (int)$_POST['id_primer'],
            trim($_POST['nama_komoditas']),
            $_POST['kategori'],
            (int)$_POST['target_jumlah'],
            trim($_POST['satuan']),
            $_POST['tingkat_urgensi'],
            trim($_POST['catatan_pusat'])
        ]);
        header("Location: index.php?msg=Instruksi pengadaan pasokan ($kode) berhasil dikirim ke Koperasi Primer.");
        exit;
    }
}

// PROSES 2: Update Progres Instruksi oleh Koperasi Primer / Konsumen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'update_progres') {
    $id_ins = (int)$_POST['id_instruksi'];
    $status = $_POST['status_instruksi'];
    $pdo->prepare("UPDATE instruksi_pasokan SET status_instruksi = ? WHERE id_instruksi = ?")->execute([$status, $id_ins]);
    header("Location: index.php?msg=Status progres instruksi pasokan diperbarui menjadi: $status");
    exit;
}

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

// Query Analisis Stok Kritis (< 50) + Total Permintaan Buyer per Produk
$produk_analisis = $pdo->query("SELECT pr.*, kp.nama_koperasi, kp.kecamatan, d.nama_divisi,
                                COALESCE((SELECT SUM(oi.qty) FROM order_items oi WHERE oi.id_produk = pr.id_produk), 0) AS total_terjual
                                FROM produk_etalase pr
                                JOIN koperasi_primer kp ON pr.id_primer = kp.id_primer
                                JOIN divisi_wilayah d ON pr.id_divisi = d.id_divisi
                                ORDER BY pr.stok ASC, total_terjual DESC")->fetchAll();

// Pisahkan produk kritis (< 50)
$stok_kritis = array_filter($produk_analisis, fn($p) => (int)$p['stok'] < 50);

// Daftar Koperasi Primer untuk dropdown
$primer_list = $pdo->query("SELECT kp.*, d.nama_divisi FROM koperasi_primer kp JOIN divisi_wilayah d ON kp.id_divisi = d.id_divisi ORDER BY d.nama_divisi ASC")->fetchAll();

// Daftar Instruksi Pasokan yang sudah dikirim
if ($role === 'primer' && !empty($user['id_primer'])) {
    $stmtIns = $pdo->prepare("SELECT ip.*, kp.nama_koperasi, kp.kecamatan, d.nama_divisi 
                              FROM instruksi_pasokan ip 
                              JOIN koperasi_primer kp ON ip.id_primer = kp.id_primer 
                              JOIN divisi_wilayah d ON kp.id_divisi = d.id_divisi 
                              WHERE ip.id_primer = ? ORDER BY ip.id_instruksi DESC");
    $stmtIns->execute([$user['id_primer']]);
    $instruksi_list = $stmtIns->fetchAll();
} else {
    $instruksi_list = $pdo->query("SELECT ip.*, kp.nama_koperasi, kp.kecamatan, d.nama_divisi 
                                   FROM instruksi_pasokan ip 
                                   JOIN koperasi_primer kp ON ip.id_primer = kp.id_primer 
                                   JOIN divisi_wilayah d ON kp.id_divisi = d.id_divisi 
                                   ORDER BY ip.id_instruksi DESC")->fetchAll();
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px; margin-bottom:24px;">
    <div>
        <span style="display:inline-block; background:#FEE2E2; color:#991B1B; font-size:11px; font-weight:800; padding:4px 12px; border-radius:99px; margin-bottom:6px;">
            EARLY WARNING SYSTEM • PROACTIVE SUPPLY RADAR
        </span>
        <h2 style="margin:0 0 4px; font-size:22px; color:#0F172A;">Peringatan Stok Kritis & Prediksi Panen</h2>
        <p style="margin:0; font-size:14px; color:#64748B;">
            Deteksi dini komoditas dengan stok menipis (&lt; 50) terhadap tren permintaan Buyer untuk memicu instruksi pasokan ke Koperasi Primer &amp; Petani.
        </p>
    </div>
    <?php if ($role === 'konsumen'): ?>
    <button onclick="openInstruksiModal()" style="background:#DC2626; color:#FFFFFF; border:none; padding:12px 20px; border-radius:12px; font-weight:700; font-size:13.5px; cursor:pointer; box-shadow:0 4px 14px rgba(220,38,38,0.28);">
        ⚡ Kirim Instruksi Panen / Restock
    </button>
    <?php endif; ?>
</div>

<?php if (isset($_GET['msg'])): ?>
<div style="background:#DCFCE7; border:1px solid #86EFAC; color:#166534; padding:12px 18px; border-radius:12px; margin-bottom:20px; font-weight:700; font-size:13.5px;">
    ✓ <?= htmlspecialchars($_GET['msg']) ?>
</div>
<?php endif; ?>

<!-- 3 KPI RADAR RESIKO -->
<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:16px; margin-bottom:24px;">
    <div style="background:#FFFFFF; padding:18px 20px; border-radius:14px; border:1px solid #FECACA; border-left:5px solid #DC2626;">
        <div style="font-size:12px; color:#991B1B; font-weight:800;">Komoditas Stok Kritis (&lt; 50)</div>
        <div style="font-size:28px; font-weight:900; color:#DC2626; margin-top:4px;"><?= count($stok_kritis) ?> Produk</div>
        <div style="font-size:11.5px; color:#64748B; margin-top:2px;">Memerlukan pasokan segera dari hulu</div>
    </div>

    <div style="background:#FFFFFF; padding:18px 20px; border-radius:14px; border:1px solid #FDE68A; border-left:5px solid #D97706;">
        <div style="font-size:12px; color:#92400E; font-weight:800;">Instruksi Restock Aktif</div>
        <div style="font-size:28px; font-weight:900; color:#0F172A; margin-top:4px;"><?= count($instruksi_list) ?> Surat Tugas</div>
        <div style="font-size:11.5px; color:#64748B; margin-top:2px;">Diteruskan ke Koperasi Primer &amp; Petani</div>
    </div>

    <div style="background:#FFFFFF; padding:18px 20px; border-radius:14px; border:1px solid #BBF7D0; border-left:5px solid #059669;">
        <div style="font-size:12px; color:#065F46; font-weight:800;">Total Varian Terpantau</div>
        <div style="font-size:28px; font-weight:900; color:#0F172A; margin-top:4px;"><?= count($produk_analisis) ?> Katalog</div>
        <div style="font-size:11.5px; color:#64748B; margin-top:2px;">Terintegrasi lintas kabupaten</div>
    </div>
</div>

<!-- SECTION 1: MATRIKS DETEKSI STOK KRITIS & PREDIKSI KEBUTUHAN -->
<div style="background:#FFFFFF; border-radius:16px; border:1px solid #E2E8F0; overflow:hidden; margin-bottom:26px; box-shadow:0 4px 12px rgba(0,0,0,0.02);">
    <div style="padding:18px 22px; border-bottom:1px solid #E2E8F0; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
        <div>
            <h3 style="margin:0; font-size:16.5px; color:#0F172A;">Radar Ketersediaan Stok vs Permintaan Buyer (Real-Time)</h3>
            <div style="font-size:12px; color:#64748B;">Komoditas dengan sisa stok di bawah 50 otomatis ditandai merah/kuning untuk segera di-restock.</div>
        </div>
    </div>

    <div style="overflow-x:auto;">
        <table style="width:100%; border-collapse:collapse; text-align:left; font-size:13.5px;">
            <thead>
                <tr style="background:#064E3B; color:#FFFFFF;">
                    <th style="padding:13px 16px;">Produk & Asal Primer</th>
                    <th style="padding:13px 16px;">Sisa Stok Etalase</th>
                    <th style="padding:13px 16px;">Total Permintaan Buyer</th>
                    <th style="padding:13px 16px;">Rekomendasi Target Panen</th>
                    <th style="padding:13px 16px;">Level Peringatan</th>
                    <?php if ($role === 'konsumen'): ?>
                        <th style="padding:13px 16px; text-align:center;">Tindakan Proaktif</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($produk_analisis)): ?>
                    <tr><td colspan="6" style="padding:26px; text-align:center; color:#64748B;">Belum ada data produk di etalase untuk dianalisis.</td></tr>
                <?php endif; ?>
                <?php foreach ($produk_analisis as $pa): 
                    $stok = (int)$pa['stok'];
                    $terjual = (int)$pa['total_terjual'];
                    $rekomendasi_panen = max(100, ($terjual * 2) + (100 - $stok));

                    if ($stok < 20) {
                        $lvl_bg = '#FEE2E2'; $lvl_col = '#991B1B'; $lvl_txt = 'KRITIS (< 20)';
                    } elseif ($stok < 50) {
                        $lvl_bg = '#FEF3C7'; $lvl_col = '#92400E'; $lvl_txt = 'WASPADA (< 50)';
                    } else {
                        $lvl_bg = '#DCFCE7'; $lvl_col = '#166534'; $lvl_txt = 'STOK AMAN';
                    }
                ?>
                <tr style="border-bottom:1px solid #F1F5F9; background:<?= $stok < 50 ? '#FFFBEB' : '#FFFFFF' ?>;">
                    <td style="padding:13px 16px;">
                        <div style="font-weight:800; color:#0F172A;"><?= htmlspecialchars($pa['nama_produk']) ?></div>
                        <div style="font-size:11.5px; color:#64748B;"><?= htmlspecialchars($pa['nama_koperasi']) ?> • <?= htmlspecialchars($pa['nama_divisi']) ?></div>
                    </td>
                    <td style="padding:13px 16px;">
                        <span style="font-size:15px; font-weight:900; color:<?= $stok < 50 ? '#DC2626' : '#059669' ?>;">
                            <?= number_format($stok, 0, ',', '.') ?> <?= htmlspecialchars($pa['satuan']) ?>
                        </span>
                    </td>
                    <td style="padding:13px 16px; font-weight:700; color:#334155;">
                        <?= number_format($terjual, 0, ',', '.') ?> <?= htmlspecialchars($pa['satuan']) ?> terjual
                    </td>
                    <td style="padding:13px 16px;">
                        <span style="font-weight:800; color:#064E3B;">+<?= number_format($rekomendasi_panen, 0, ',', '.') ?> <?= htmlspecialchars($pa['satuan']) ?></span>
                        <div style="font-size:11px; color:#64748B;">Buffer stok 14 hari ke depan</div>
                    </td>
                    <td style="padding:13px 16px;">
                        <span style="background:<?= $lvl_bg ?>; color:<?= $lvl_col ?>; padding:4px 11px; border-radius:99px; font-size:11px; font-weight:800;">
                            <?= $lvl_txt ?>
                        </span>
                    </td>
                    <?php if ($role === 'konsumen'): ?>
                    <td style="padding:13px 16px; text-align:center;">
                        <button onclick='openInstruksiModal(<?= json_encode([
                            "id_primer" => $pa["id_primer"],
                            "nama_komoditas" => $pa["nama_produk"],
                            "kategori" => $pa["kategori"],
                            "satuan" => $pa["satuan"],
                            "target" => $rekomendasi_panen,
                            "urgensi" => $stok < 20 ? "Kritis (< 20)" : "Waspada (< 50)"
                        ]) ?>)' style="background:#064E3B; color:#FFFFFF; border:none; padding:7px 14px; border-radius:8px; font-weight:700; font-size:12px; cursor:pointer;">
                            Instruksikan Restock →
                        </button>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- SECTION 2: RIWAYAT & STATUS INSTRUKSI PASOKAN KE KOPERASI PRIMER & PETANI -->
<div style="background:#FFFFFF; border-radius:16px; border:1px solid #E2E8F0; overflow:hidden; box-shadow:0 4px 12px rgba(0,0,0,0.02);">
    <div style="padding:18px 22px; border-bottom:1px solid #E2E8F0;">
        <h3 style="margin:0; font-size:16.5px; color:#0F172A;">Log Instruksi Pengadaan Pasokan (Pusat → Primer → Petani)</h3>
        <div style="font-size:12px; color:#64748B;">Pantau tindak lanjut Koperasi Primer kecamatan dan Petani dalam memenuhi target komoditas.</div>
    </div>

    <div style="overflow-x:auto;">
        <table style="width:100%; border-collapse:collapse; text-align:left; font-size:13.5px;">
            <thead>
                <tr style="background:#F8FAFC; border-bottom:2px solid #E2E8F0; color:#475569;">
                    <th style="padding:12px 16px;">Kode & Tanggal</th>
                    <th style="padding:12px 16px;">Unit Koperasi Primer Tujuan</th>
                    <th style="padding:12px 16px;">Komoditas & Target Volume</th>
                    <th style="padding:12px 16px;">Catatan Instruksi Pusat</th>
                    <th style="padding:12px 16px;">Status Pemenuhan</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($instruksi_list)): ?>
                    <tr><td colspan="5" style="padding:26px; text-align:center; color:#64748B;">Belum ada instruksi pengadaan pasokan yang diterbitkan.</td></tr>
                <?php endif; ?>
                <?php foreach ($instruksi_list as $ins): ?>
                <tr style="border-bottom:1px solid #F1F5F9;">
                    <td style="padding:12px 16px;">
                        <div style="font-weight:800; color:#059669; font-family:monospace;"><?= htmlspecialchars($ins['kode_instruksi']) ?></div>
                        <div style="font-size:11px; color:#64748B;"><?= date('d M Y, H:i', strtotime($ins['created_at'])) ?></div>
                    </td>
                    <td style="padding:12px 16px;">
                        <div style="font-weight:700; color:#0F172A;"><?= htmlspecialchars($ins['nama_koperasi']) ?></div>
                        <div style="font-size:11.5px; color:#64748B;">Kec. <?= htmlspecialchars($ins['kecamatan']) ?> (<?= htmlspecialchars($ins['nama_divisi']) ?>)</div>
                    </td>
                    <td style="padding:12px 16px;">
                        <div style="font-weight:800; color:#0F172A;"><?= htmlspecialchars($ins['nama_komoditas']) ?></div>
                        <div style="font-size:12px; color:#D97706; font-weight:700;">Target: <?= number_format($ins['target_jumlah'], 0, ',', '.') ?> <?= htmlspecialchars($ins['satuan']) ?></div>
                    </td>
                    <td style="padding:12px 16px; font-size:12.5px; color:#475569; max-width:260px;">
                        <?= htmlspecialchars($ins['catatan_pusat'] ?: 'Segera koordinasikan panen dengan kelompok tani binaan.') ?>
                    </td>
                    <td style="padding:12px 16px;">
                        <?php if (in_array($role, ['konsumen', 'primer'])): ?>
                            <form method="POST" style="margin:0;">
                                <input type="hidden" name="aksi" value="update_progres">
                                <input type="hidden" name="id_instruksi" value="<?= $ins['id_instruksi'] ?>">
                                <select name="status_instruksi" onchange="this.form.submit()" style="padding:6px 10px; border-radius:8px; border:1px solid #CBD5E1; font-size:12px; font-weight:700; background:<?= $ins['status_instruksi']==='Terpenuhi' ? '#DCFCE7' : '#FEF3C7' ?>; color:<?= $ins['status_instruksi']==='Terpenuhi' ? '#166534' : '#92400E' ?>; cursor:pointer;">
                                    <?php foreach (['Terkirim ke Primer', 'Diteruskan ke Petani', 'Terpenuhi'] as $st): ?>
                                        <option value="<?= $st ?>" <?= $ins['status_instruksi'] === $st ? 'selected' : '' ?>><?= $st ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        <?php else: ?>
                            <span style="background:#FEF3C7; color:#92400E; padding:4px 10px; border-radius:99px; font-size:11.5px; font-weight:800;">
                                <?= htmlspecialchars($ins['status_instruksi']) ?>
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL KIRIM INSTRUKSI PASOKAN KE PRIMER & PETANI -->
<div id="modalInstruksi" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.65); z-index:999; align-items:center; justify-content:center; padding:18px;">
    <div style="background:#FFFFFF; width:100%; max-width:520px; border-radius:20px; padding:28px; box-shadow:0 25px 50px rgba(0,0,0,0.25);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px;">
            <div>
                <h3 style="margin:0; font-size:18px; color:#0F172A;">Kirim Instruksi Pasokan Hulu</h3>
                <div style="font-size:12px; color:#64748B;">Perintah pengadaan komoditas ke Koperasi Primer &amp; Petani.</div>
            </div>
            <button onclick="document.getElementById('modalInstruksi').style.display='none'" style="background:#F1F5F9; border:none; width:32px; height:32px; border-radius:50%; font-size:16px; cursor:pointer;">✕</button>
        </div>

        <form method="POST" style="display:flex; flex-direction:column; gap:13px;">
            <input type="hidden" name="aksi" value="kirim_instruksi">

            <div>
                <label style="font-size:12px; font-weight:700; color:#334155;">Koperasi Primer Kecamatan Tujuan</label>
                <select name="id_primer" id="ins_id_primer" required style="width:100%; box-sizing:border-box; padding:10px; border:1px solid #CBD5E1; border-radius:10px; margin-top:4px; font-weight:600;">
                    <?php foreach ($primer_list as $p): ?>
                        <option value="<?= $p['id_primer'] ?>"><?= htmlspecialchars($p['nama_koperasi']) ?> — Kec. <?= htmlspecialchars($p['kecamatan']) ?> (<?= htmlspecialchars($p['nama_divisi']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display:grid; grid-template-columns:2fr 1fr; gap:12px;">
                <div>
                    <label style="font-size:12px; font-weight:700; color:#334155;">Nama Komoditas</label>
                    <input type="text" name="nama_komoditas" id="ins_nama_komoditas" placeholder="Contoh: Kentang Granola" required style="width:100%; box-sizing:border-box; padding:10px; border:1px solid #CBD5E1; border-radius:10px; margin-top:4px;">
                </div>
                <div>
                    <label style="font-size:12px; font-weight:700; color:#334155;">Kategori</label>
                    <select name="kategori" id="ins_kategori" style="width:100%; box-sizing:border-box; padding:10px; border:1px solid #CBD5E1; border-radius:10px; margin-top:4px;">
                        <option value="Komoditas Tanaman">Komoditas Tanaman</option>
                        <option value="Saprodi">Saprodi</option>
                        <option value="Alsintan">Alsintan</option>
                        <option value="Produk Olahan">Produk Olahan</option>
                        <option value="Produk Lainnya">Produk Lainnya</option>
                    </select>
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr 1.2fr; gap:10px;">
                <div>
                    <label style="font-size:12px; font-weight:700; color:#334155;">Target Volume</label>
                    <input type="number" name="target_jumlah" id="ins_target" value="150" required style="width:100%; box-sizing:border-box; padding:10px; border:1px solid #CBD5E1; border-radius:10px; margin-top:4px;">
                </div>
                <div>
                    <label style="font-size:12px; font-weight:700; color:#334155;">Satuan</label>
                    <input type="text" name="satuan" id="ins_satuan" value="Kg" required style="width:100%; box-sizing:border-box; padding:10px; border:1px solid #CBD5E1; border-radius:10px; margin-top:4px;">
                </div>
                <div>
                    <label style="font-size:12px; font-weight:700; color:#DC2626;">Tingkat Urgensi</label>
                    <select name="tingkat_urgensi" id="ins_urgensi" style="width:100%; box-sizing:border-box; padding:10px; border:1px solid #CBD5E1; border-radius:10px; margin-top:4px; font-weight:700;">
                        <option value="Kritis (< 20)">Kritis (&lt; 20)</option>
                        <option value="Waspada (< 50)" selected>Waspada (&lt; 50)</option>
                        <option value="Prediksi Musiman">Prediksi Musiman</option>
                    </select>
                </div>
            </div>

            <div>
                <label style="font-size:12px; font-weight:700; color:#334155;">Catatan Operasional untuk Primer &amp; Petani</label>
                <textarea name="catatan_pusat" rows="2" placeholder="Instruksi jadwal panen, standar grading, atau target pengiriman..." style="width:100%; box-sizing:border-box; padding:10px; border:1px solid #CBD5E1; border-radius:10px; margin-top:4px; font-family:inherit;"></textarea>
            </div>

            <button type="submit" style="background:#DC2626; color:#FFFFFF; border:none; padding:13px; border-radius:12px; font-weight:700; font-size:14px; cursor:pointer;">
                ⚡ Kirim Instruksi Sekarang
            </button>
        </form>
    </div>
</div>

<script>
function openInstruksiModal(data = null) {
    if (data) {
        document.getElementById('ins_id_primer').value = data.id_primer;
        document.getElementById('ins_nama_komoditas').value = data.nama_komoditas;
        document.getElementById('ins_kategori').value = data.kategori;
        document.getElementById('ins_satuan').value = data.satuan;
        document.getElementById('ins_target').value = data.target;
        document.getElementById('ins_urgensi').value = data.urgensi;
    }
    document.getElementById('modalInstruksi').style.display = 'flex';
}
</script>

<?php require_once '../../includes/footer.php'; ?>