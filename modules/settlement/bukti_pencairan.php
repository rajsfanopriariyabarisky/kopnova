<?php
require_once '../../config/database.php';
cekAkses(['konsumen', 'primer', 'petani']);

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT s.*, o.no_invoice, o.metode_bayar, o.tanggal_order, u.nama_lengkap AS nama_buyer, kp.nama_koperasi, kp.kecamatan, kp.ketua_koperasi, d.nama_divisi
                       FROM settlement_dana s
                       JOIN orders o ON s.id_order = o.id_order
                       JOIN users u ON o.id_buyer = u.id_user
                       JOIN koperasi_primer kp ON s.id_primer = kp.id_primer
                       JOIN divisi_wilayah d ON kp.id_divisi = d.id_divisi
                       WHERE s.id_settlement = ?");
$stmt->execute([$id]);
$stl = $stmt->fetch();

if (!$stl) {
    die("Bukti pencairan dana tidak ditemukan.");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Slip Pencairan Dana - <?= htmlspecialchars($stl['kode_settlement']) ?></title>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: #FFFFFF !important; padding: 0 !important; }
            .voucher { box-shadow: none !important; border: 1px solid #CBD5E1 !important; margin: 0 !important; max-width: 100% !important; }
        }
    </style>
</head>
<body style="margin:0; padding:24px; font-family:'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background:#E2E8F0; color:#0F172A;">

    <div class="no-print" style="max-width:780px; margin:0 auto 16px; display:flex; justify-content:space-between; align-items:center; background:#064E3B; color:#FFFFFF; padding:12px 20px; border-radius:12px;">
        <div style="font-size:13px; font-weight:700;">Pratinjau Voucher Pencairan Dana &amp; Distribusi SHU</div>
        <div style="display:flex; gap:10px;">
            <button onclick="window.print()" style="background:#F59E0B; color:#0F172A; border:none; padding:8px 18px; border-radius:8px; font-weight:800; font-size:13px; cursor:pointer;">
                🖨 Cetak Bukti Pencairan
            </button>
            <button onclick="window.close()" style="background:rgba(255,255,255,0.18); color:#FFFFFF; border:none; padding:8px 14px; border-radius:8px; font-weight:700; font-size:13px; cursor:pointer;">
                Tutup
            </button>
        </div>
    </div>

    <div class="voucher" style="max-width:780px; margin:0 auto; background:#FFFFFF; padding:36px 42px; border-radius:14px; box-shadow:0 12px 30px rgba(0,0,0,0.12); box-sizing:border-box;">
        
        <!-- KOP DOKUMEN -->
        <div style="display:flex; justify-content:space-between; align-items:flex-start; border-bottom:3px solid #064E3B; padding-bottom:16px; margin-bottom:20px;">
            <div style="display:flex; align-items:center; gap:12px;">
                <div style="width:44px; height:44px; background:#064E3B; color:#FFFFFF; border-radius:10px; display:flex; align-items:center; justify-content:center; font-weight:900; font-size:22px;">K</div>
                <div>
                    <div style="font-size:20px; font-weight:900; color:#064E3B;">KOPNOVA TREASURY</div>
                    <div style="font-size:11.5px; font-weight:700; color:#059669;">DIVISI KEUANGAN &amp; PENCAIRAN DANA RANTAI PASOK</div>
                </div>
            </div>
            <div style="text-align:right;">
                <div style="font-size:16px; font-weight:900; color:#0F172A;">BUKTI SETTLEMENT DANA</div>
                <div style="font-size:12.5px; font-weight:800; color:#059669; font-family:monospace;"><?= htmlspecialchars($stl['kode_settlement']) ?></div>
                <div style="font-size:11px; color:#64748B;">Ref. Invoice: <?= htmlspecialchars($stl['no_invoice']) ?></div>
            </div>
        </div>

        <!-- DETAIL PENERIMA DANA -->
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; background:#F8FAFC; padding:16px; border-radius:10px; border:1px solid #E2E8F0; margin-bottom:20px; font-size:13px;">
            <div>
                <div style="font-size:11px; font-weight:800; color:#64748B; text-transform:uppercase;">Unit Koperasi Penerima</div>
                <div style="font-weight:800; font-size:15px; color:#0F172A; margin:3px 0;"><?= htmlspecialchars($stl['nama_koperasi']) ?></div>
                <div style="color:#475569;">Kec. <?= htmlspecialchars($stl['kecamatan']) ?> • <?= htmlspecialchars($stl['nama_divisi']) ?></div>
                <div style="color:#475569;">Ketua/PIC: <?= htmlspecialchars($stl['ketua_koperasi']) ?></div>
            </div>
            <div style="text-align:right;">
                <div style="font-size:11px; font-weight:800; color:#64748B; text-transform:uppercase;">Status Pencairan</div>
                <div style="font-weight:800; font-size:14px; color:#059669; margin:3px 0;"><?= htmlspecialchars($stl['status_pencairan']) ?></div>
                <div style="color:#475569;">Sumber Dana: <?= htmlspecialchars($stl['nama_buyer']) ?></div>
                <div style="color:#475569;">Metode Masuk: <?= htmlspecialchars($stl['metode_bayar']) ?></div>
            </div>
        </div>

        <!-- TABEL RINCIAN PEMBAGIAN KAS & HAK -->
        <table style="width:100%; border-collapse:collapse; font-size:13px; margin-bottom:24px;">
            <thead>
                <tr style="background:#064E3B; color:#FFFFFF; text-align:left;">
                    <th style="padding:11px; border:1px solid #064E3B;">Komponen Distribusi Dana</th>
                    <th style="padding:11px; border:1px solid #064E3B;">Penerima Manfaat</th>
                    <th style="padding:11px; border:1px solid #064E3B; text-align:right;">Nominal Rupiah</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="padding:11px; border:1px solid #CBD5E1;"><strong>1. Hak Pembayaran Hasil Panen</strong></td>
                    <td style="padding:11px; border:1px solid #CBD5E1;">Petani Pemasok Binaan (via Primer)</td>
                    <td style="padding:11px; border:1px solid #CBD5E1; text-align:right; font-weight:800; color:#059669;"><?= rupiah($stl['hak_petani']) ?></td>
                </tr>
                <tr>
                    <td style="padding:11px; border:1px solid #CBD5E1;"><strong>2. Kas Operasional &amp; Margin Unit</strong></td>
                    <td style="padding:11px; border:1px solid #CBD5E1;"><?= htmlspecialchars($stl['nama_koperasi']) ?></td>
                    <td style="padding:11px; border:1px solid #CBD5E1; text-align:right; font-weight:800; color:#D97706;"><?= rupiah($stl['kas_primer']) ?></td>
                </tr>
                <tr>
                    <td style="padding:11px; border:1px solid #CBD5E1;"><strong>3. Kas SHU &amp; Sistem Pusat</strong></td>
                    <td style="padding:11px; border:1px solid #CBD5E1;">Koperasi Konsumen Pusat (Nasional)</td>
                    <td style="padding:11px; border:1px solid #CBD5E1; text-align:right; font-weight:800; color:#2563EB;"><?= rupiah($stl['kas_konsumen']) ?></td>
                </tr>
            </tbody>
            <tfoot>
                <tr style="background:#F0FDF4;">
                    <td colspan="2" style="padding:12px; border:1px solid #CBD5E1; text-align:right; font-weight:800; color:#064E3B;">TOTAL NILAI TRANSAKSI BRUTO</td>
                    <td style="padding:12px; border:1px solid #CBD5E1; text-align:right; font-weight:900; font-size:15px; color:#064E3B;"><?= rupiah($stl['nominal_bruto']) ?></td>
                </tr>
            </tfoot>
        </table>

        <!-- TANDA TANGAN 3 PIHAK -->
        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:18px; text-align:center; margin-top:34px; font-size:12px;">
            <div>
                <div style="color:#64748B;">Menyetujui Transfer,</div>
                <div style="font-weight:800;">Bendahara Konsumen Pusat</div>
                <div style="height:60px;"></div>
                <div style="border-top:1px solid #94A3B8; display:inline-block; padding-top:4px; min-width:155px; font-weight:700;">Otorisasi Pusat</div>
            </div>
            <div>
                <div style="color:#64748B;">Diterima &amp; Disalurkan,</div>
                <div style="font-weight:800;"><?= htmlspecialchars($stl['nama_koperasi']) ?></div>
                <div style="height:60px;"></div>
                <div style="border-top:1px solid #94A3B8; display:inline-block; padding-top:4px; min-width:155px; font-weight:700;"><?= htmlspecialchars($stl['ketua_koperasi']) ?></div>
            </div>
            <div>
                <div style="color:#64748B;">Penerima Akhir Hulu,</div>
                <div style="font-weight:800;">Perwakilan Petani Pemasok</div>
                <div style="height:60px;"></div>
                <div style="border-top:1px solid #94A3B8; display:inline-block; padding-top:4px; min-width:155px; font-weight:700;">Tanda Terima Petani</div>
            </div>
        </div>

    </div>
</body>
</html>