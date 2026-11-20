<?php
require_once '../../config/database.php';
cekAkses(['konsumen', 'primer', 'buyer']);

$id_order = (int)($_GET['id_order'] ?? 0);

$stmt = $pdo->prepare("SELECT o.*, u.nama_lengkap AS nama_buyer, u.no_hp AS hp_buyer 
                       FROM orders o 
                       JOIN users u ON o.id_buyer = u.id_user 
                       WHERE o.id_order = ?");
$stmt->execute([$id_order]);
$order = $stmt->fetch();

if (!$order) {
    die("Dokumen Surat Jalan tidak ditemukan.");
}

// Otomatis generate nomor surat jalan jika dicetak sebelum tombol routing diklik
if (empty($order['no_surat_jalan'])) {
    $no_sj = 'SJ-KPN-' . date('Ymd') . '-' . str_pad($id_order, 4, '0', STR_PAD_LEFT);
    $pdo->prepare("UPDATE orders SET no_surat_jalan = ? WHERE id_order = ?")->execute([$no_sj, $id_order]);
    $order['no_surat_jalan'] = $no_sj;
}

$stmtItems = $pdo->prepare("SELECT oi.*, pr.nama_produk, pr.kode_produk, pr.satuan, kp.nama_koperasi, kp.kecamatan, kp.ketua_koperasi, d.nama_divisi 
                            FROM order_items oi 
                            JOIN produk_etalase pr ON oi.id_produk = pr.id_produk 
                            JOIN koperasi_primer kp ON pr.id_primer = kp.id_primer 
                            JOIN divisi_wilayah d ON pr.id_divisi = d.id_divisi 
                            WHERE oi.id_order = ? 
                            ORDER BY kp.id_primer ASC");
$stmtItems->execute([$id_order]);
$items = $stmtItems->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Surat Jalan Resmi - <?= htmlspecialchars($order['no_surat_jalan']) ?></title>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: #FFFFFF !important; padding: 0 !important; }
            .sheet { box-shadow: none !important; border: none !important; margin: 0 !important; max-width: 100% !important; }
        }
    </style>
</head>
<body style="margin:0; padding:24px; font-family:'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background:#E2E8F0; color:#0F172A;">

    <!-- ACTION BAR CETAK (TIDAK IKUT TERCETAK) -->
    <div class="no-print" style="max-width:840px; margin:0 auto 16px; display:flex; justify-content:space-between; align-items:center; background:#064E3B; color:#FFFFFF; padding:12px 20px; border-radius:12px;">
        <div style="font-size:13px; font-weight:700;">Pratinjau Dokumen Resmi Logistik & Invoice Multi-Kecamatan</div>
        <div style="display:flex; gap:10px;">
            <button onclick="window.print()" style="background:#F59E0B; color:#0F172A; border:none; padding:8px 18px; border-radius:8px; font-weight:800; font-size:13px; cursor:pointer;">
                🖨 Cetak / Simpan PDF
            </button>
            <button onclick="window.close()" style="background:rgba(255,255,255,0.18); color:#FFFFFF; border:none; padding:8px 14px; border-radius:8px; font-weight:700; font-size:13px; cursor:pointer;">
                Tutup
            </button>
        </div>
    </div>

    <!-- LEMBAR DOKUMEN A4 -->
    <div class="sheet" style="max-width:840px; margin:0 auto; background:#FFFFFF; padding:38px 44px; border-radius:14px; box-shadow:0 12px 30px rgba(0,0,0,0.12); box-sizing:border-box;">
        
        <!-- KOP SURAT KOPNOVA -->
        <div style="display:flex; justify-content:space-between; align-items:flex-start; border-bottom:3px solid #064E3B; padding-bottom:18px; margin-bottom:22px;">
            <div style="display:flex; align-items:center; gap:14px;">
                <div style="width:48px; height:48px; background:#064E3B; color:#FFFFFF; border-radius:12px; display:flex; align-items:center; justify-content:center; font-weight:900; font-size:24px;">K</div>
                <div>
                    <div style="font-size:22px; font-weight:900; color:#064E3B; letter-spacing:0.5px;">KOPNOVA</div>
                    <div style="font-size:12px; font-weight:700; color:#059669;">KOPERASI KONSUMEN PUSAT • RANTAI PASOK AGRIBISNIS</div>
                    <div style="font-size:11px; color:#64748B;">Sistem Distribusi Terpadu Lintas Kabupaten & Koperasi Primer Kecamatan</div>
                </div>
            </div>
            <div style="text-align:right;">
                <div style="font-size:18px; font-weight:900; color:#0F172A;">SURAT JALAN & INVOICE</div>
                <div style="font-size:13px; font-weight:800; color:#059669; margin-top:2px;"><?= htmlspecialchars($order['no_surat_jalan']) ?></div>
                <div style="font-size:11.5px; color:#64748B;">Ref. Invoice: <?= htmlspecialchars($order['no_invoice']) ?></div>
            </div>
        </div>

        <!-- INFORMASI TUJUAN PENGIRIMAN & PEMBAYARAN -->
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; background:#F8FAFC; padding:16px 20px; border-radius:10px; border:1px solid #E2E8F0; margin-bottom:22px; font-size:13px;">
            <div>
                <div style="font-size:11px; font-weight:800; color:#64748B; text-transform:uppercase;">Kepada Yth. (Penerima / Buyer)</div>
                <div style="font-weight:800; font-size:15px; color:#0F172A; margin:3px 0;"><?= htmlspecialchars($order['nama_buyer']) ?></div>
                <div style="color:#475569;">Kontak: <?= htmlspecialchars($order['hp_buyer'] ?: '-') ?></div>
                <div style="color:#475569; margin-top:2px;">Alamat Tujuan: <?= htmlspecialchars($order['alamat_pengiriman']) ?></div>
            </div>
            <div style="text-align:right;">
                <div style="font-size:11px; font-weight:800; color:#64748B; text-transform:uppercase;">Detail Transaksi & Logistik</div>
                <div style="margin-top:4px;">Tanggal Terbit: <strong><?= date('d F Y, H:i', strtotime($order['tanggal_order'])) ?></strong></div>
                <div style="margin-top:2px;">Metode Pembayaran: <strong><?= htmlspecialchars($order['metode_bayar']) ?></strong></div>
                <div style="margin-top:2px;">Status Pengiriman: <strong><?= htmlspecialchars($order['status_order']) ?></strong></div>
            </div>
        </div>

        <!-- TABEL KONSOLIDASI PICKING LIST LINTAS KECAMATAN -->
        <div style="font-size:13px; font-weight:800; color:#064E3B; margin-bottom:8px;">
            Rincian Konsolidasi Komoditas & Titik Pengambilan Koperasi Primer:
        </div>
        <table style="width:100%; border-collapse:collapse; font-size:12.5px; margin-bottom:22px;">
            <thead>
                <tr style="background:#064E3B; color:#FFFFFF; text-align:left;">
                    <th style="padding:10px; border:1px solid #064E3B;">No</th>
                    <th style="padding:10px; border:1px solid #064E3B;">Unit Koperasi Primer (Asal Barang)</th>
                    <th style="padding:10px; border:1px solid #064E3B;">Komoditas / Produk</th>
                    <th style="padding:10px; border:1px solid #064E3B; text-align:center;">Qty</th>
                    <th style="padding:10px; border:1px solid #064E3B; text-align:right;">Harga Satuan</th>
                    <th style="padding:10px; border:1px solid #064E3B; text-align:right;">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php $no = 1; foreach ($items as $it): ?>
                <tr>
                    <td style="padding:10px; border:1px solid #CBD5E1; text-align:center;"><?= $no++ ?></td>
                    <td style="padding:10px; border:1px solid #CBD5E1;">
                        <strong><?= htmlspecialchars($it['nama_koperasi']) ?></strong><br>
                        <span style="font-size:11px; color:#64748B;">Kec. <?= htmlspecialchars($it['kecamatan']) ?> (<?= htmlspecialchars($it['nama_divisi']) ?>)</span>
                    </td>
                    <td style="padding:10px; border:1px solid #CBD5E1;">
                        <strong><?= htmlspecialchars($it['nama_produk']) ?></strong><br>
                        <span style="font-size:11px; color:#64748B;">Kode: <?= htmlspecialchars($it['kode_produk']) ?></span>
                    </td>
                    <td style="padding:10px; border:1px solid #CBD5E1; text-align:center; font-weight:700;">
                        <?= $it['qty'] ?> <?= htmlspecialchars($it['satuan']) ?>
                    </td>
                    <td style="padding:10px; border:1px solid #CBD5E1; text-align:right;">
                        <?= rupiah($it['harga_satuan']) ?>
                    </td>
                    <td style="padding:10px; border:1px solid #CBD5E1; text-align:right; font-weight:700;">
                        <?= rupiah($it['subtotal']) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background:#F0FDF4;">
                    <td colspan="5" style="padding:12px; border:1px solid #CBD5E1; text-align:right; font-weight:800; font-size:14px; color:#064E3B;">
                        TOTAL TAGIHAN PEMBAYARAN
                    </td>
                    <td style="padding:12px; border:1px solid #CBD5E1; text-align:right; font-weight:900; font-size:15px; color:#064E3B;">
                        <?= rupiah($order['total_bayar']) ?>
                    </td>
                </tr>
            </tfoot>
        </table>

        <!-- KOLOM OTORISASI TANDA TANGAN 3 PIHAK -->
        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:20px; text-align:center; margin-top:36px; font-size:12px;">
            <div>
                <div style="color:#64748B;">Diterbitkan Oleh,</div>
                <div style="font-weight:800; color:#0F172A; margin-top:2px;">Koperasi Konsumen Pusat</div>
                <div style="height:64px;"></div>
                <div style="border-top:1px solid #94A3B8; display:inline-block; padding-top:4px; min-width:160px; font-weight:700;">Admin Pusat KOPNOVA</div>
            </div>
            <div>
                <div style="color:#64748B;">Armada & Konsolidasi,</div>
                <div style="font-weight:800; color:#0F172A; margin-top:2px;">Koordinator Logistik</div>
                <div style="height:64px;"></div>
                <div style="border-top:1px solid #94A3B8; display:inline-block; padding-top:4px; min-width:160px; font-weight:700;">Petugas Pengiriman</div>
            </div>
            <div>
                <div style="color:#64748B;">Diterima Baik Oleh,</div>
                <div style="font-weight:800; color:#0F172A; margin-top:2px;"><?= htmlspecialchars($order['nama_buyer']) ?></div>
                <div style="height:64px;"></div>
                <div style="border-top:1px solid #94A3B8; display:inline-block; padding-top:4px; min-width:160px; font-weight:700;">Penerima Barang</div>
            </div>
        </div>

    </div>
</body>
</html> 