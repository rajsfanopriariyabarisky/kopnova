<?php
require_once '../../config/database.php';
cekAkses(['konsumen', 'primer', 'petani']);

$role = $_SESSION['user']['role'] ?? '';
$user = $_SESSION['user'] ?? [];
$id   = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    die("Parameter kode settlement tidak valid.");
}

$sql = "
    SELECT s.*, o.no_invoice, o.metode_bayar, o.tanggal_order,
           u.nama_lengkap AS nama_buyer,
           kp.nama_koperasi, kp.kecamatan, kp.ketua_koperasi,
           d.nama_divisi
    FROM settlement_dana s
    JOIN orders o ON s.id_order = o.id_order
    JOIN users u ON o.id_buyer = u.id_user
    JOIN koperasi_primer kp ON s.id_primer = kp.id_primer
    JOIN divisi_wilayah d ON kp.id_divisi = d.id_divisi
    WHERE s.id_settlement = ?
";

$params = [$id];
if (in_array($role, ['primer', 'petani'], true)) {
    $sql .= " AND s.id_primer = ?";
    $params[] = (int)($user['id_primer'] ?? 0);
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$stl = $stmt->fetch();

if (!$stl) {
    http_response_code(404);
    die("Bukti pencairan dana tidak ditemukan atau bukan wewenang unit Anda.");
}

// Ambil rincian item komoditas milik Koperasi Primer terkait pada order ini
$stmtItems = $pdo->prepare("
    SELECT oi.qty, oi.harga_satuan, oi.subtotal,
           pr.kode_produk, pr.nama_produk, pr.satuan
    FROM order_items oi
    JOIN produk_etalase pr ON oi.id_produk = pr.id_produk
    WHERE oi.id_order = ? AND pr.id_primer = ?
    ORDER BY oi.id_item ASC
");
$stmtItems->execute([(int)$stl['id_order'], (int)$stl['id_primer']]);
$items = $stmtItems->fetchAll();

$st_cair = $stl['status_pencairan'] ?? 'Menunggu Pencairan Pusat';
$st_bg   = '#FEF3C7'; $st_col = '#92400E'; $st_bdr = '#FDE68A';
if ($st_cair === 'Diteruskan ke Petani') {
    $st_bg = '#DCFCE7'; $st_col = '#166534'; $st_bdr = '#BBF7D0';
} elseif ($st_cair === 'Dicairkan ke Primer') {
    $st_bg = '#DBEAFE'; $st_col = '#1E40AF'; $st_bdr = '#BFDBFE';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Slip Pencairan Dana - <?= htmlspecialchars($stl['kode_settlement']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
    <style>
        @page {
            size: A4 portrait;
            margin: 14mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            margin: 0;
            padding: 24px 16px;
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #E2E8F0;
            color: #0F172A;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .toolbar-print {
            max-width: 800px;
            margin: 0 auto 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            background: #042F24;
            color: #FFFFFF;
            padding: 13px 20px;
            border-radius: 14px;
            box-shadow: 0 8px 20px rgba(4, 47, 36, 0.18);
        }
        .btn-tool {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            border: none;
            padding: 9px 16px;
            border-radius: 9px;
            font-family: inherit;
            font-weight: 800;
            font-size: 13px;
            cursor: pointer;
            text-decoration: none;
        }
        .voucher {
            max-width: 800px;
            margin: 0 auto;
            background: #FFFFFF;
            padding: 36px 40px;
            border-radius: 16px;
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.1);
            border: 1px solid #CBD5E1;
        }
        .kop-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            border-bottom: 3px solid #064E3B;
            padding-bottom: 16px;
            margin-bottom: 20px;
        }
        .info-box-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            background: #F8FAFC;
            padding: 16px 18px;
            border-radius: 12px;
            border: 1px solid #E2E8F0;
            margin-bottom: 20px;
            font-size: 13px;
        }
        .doc-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12.5px;
            margin-bottom: 20px;
            font-variant-numeric: tabular-nums;
        }
        .doc-table th {
            background: #064E3B;
            color: #FFFFFF;
            padding: 10px 12px;
            border: 1px solid #064E3B;
            text-align: left;
            font-weight: 800;
        }
        .doc-table td {
            padding: 10px 12px;
            border: 1px solid #CBD5E1;
            vertical-align: middle;
        }
        .ttd-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            text-align: center;
            margin-top: 32px;
            font-size: 12px;
        }
        @media (max-width: 640px) {
            .voucher {
                padding: 22px 18px;
            }
            .kop-header, .info-box-grid, .ttd-grid {
                grid-template-columns: 1fr;
                flex-direction: column;
                text-align: left !important;
            }
            .kop-header > div:last-child, .info-box-grid > div:last-child {
                text-align: left !important;
            }
            .ttd-grid {
                text-align: center !important;
                gap: 24px;
            }
        }
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background: #FFFFFF !important;
                padding: 0 !important;
            }
            .voucher {
                box-shadow: none !important;
                border: none !important;
                border-radius: 0 !important;
                margin: 0 !important;
                padding: 0 !important;
                max-width: 100% !important;
            }
        }
    </style>
</head>
<body>

    <div class="no-print toolbar-print">
        <div style="font-size:13px; font-weight:700;">Pratinjau Voucher Pencairan Dana &amp; Distribusi SHU</div>
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <a href="index.php" class="btn-tool" style="background:rgba(255,255,255,0.14); color:#FFFFFF;">
                &larr; Kembali
            </a>
            <button type="button" onclick="window.print()" class="btn-tool" style="background:#F59E0B; color:#042F24;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                <span>Cetak Bukti Pencairan</span>
            </button>
            <button type="button" onclick="window.close()" class="btn-tool" style="background:rgba(255,255,255,0.14); color:#FFFFFF;">
                Tutup
            </button>
        </div>
    </div>

    <div class="voucher">

        <!-- KOP DOKUMEN -->
        <div class="kop-header">
            <div style="display:flex; align-items:center; gap:12px;">
                <div style="width:44px; height:44px; background:#064E3B; color:#FFFFFF; border-radius:11px; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:21px; flex-shrink:0;">K</div>
                <div>
                    <div style="font-size:19px; font-weight:800; color:#064E3B; letter-spacing:-0.3px;">KOPNOVA TREASURY</div>
                    <div style="font-size:11px; font-weight:800; color:#059669; letter-spacing:0.4px;">DIVISI KEUANGAN &amp; PENCAIRAN DANA RANTAI PASOK</div>
                </div>
            </div>
            <div style="text-align:right;">
                <div style="font-size:15.5px; font-weight:800; color:#0F172A;">BUKTI SETTLEMENT DANA</div>
                <div style="font-size:12.5px; font-weight:800; color:#059669; font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; margin-top:2px;">
                    <?= htmlspecialchars($stl['kode_settlement']) ?>
                </div>
                <div style="font-size:11px; color:#64748B; margin-top:2px;">
                    Ref. Invoice: <strong><?= htmlspecialchars($stl['no_invoice']) ?></strong> • Tgl Order: <?= date('d M Y', strtotime($stl['tanggal_order'])) ?>
                </div>
            </div>
        </div>

        <!-- DETAIL PENERIMA DANA -->
        <div class="info-box-grid">
            <div>
                <div style="font-size:10.5px; font-weight:800; color:#64748B; text-transform:uppercase; letter-spacing:0.5px;">Unit Koperasi Penerima</div>
                <div style="font-weight:800; font-size:14.5px; color:#0F172A; margin:4px 0 3px;"><?= htmlspecialchars($stl['nama_koperasi']) ?></div>
                <div style="color:#475569; line-height:1.45;">Kec. <?= htmlspecialchars($stl['kecamatan']) ?> • <?= htmlspecialchars($stl['nama_divisi']) ?></div>
                <div style="color:#475569; margin-top:2px;">Ketua / PIC Unit: <strong><?= htmlspecialchars($stl['ketua_koperasi']) ?></strong></div>
            </div>
            <div style="text-align:right;">
                <div style="font-size:10.5px; font-weight:800; color:#64748B; text-transform:uppercase; letter-spacing:0.5px;">Status &amp; Sumber Dana</div>
                <div style="margin:5px 0;">
                    <span style="display:inline-block; background:<?= $st_bg ?>; color:<?= $st_col ?>; border:1px solid <?= $st_bdr ?>; padding:3px 10px; border-radius:99px; font-size:11.5px; font-weight:800;">
                        <?= htmlspecialchars($st_cair) ?>
                    </span>
                </div>
                <div style="color:#475569;">Buyer: <strong><?= htmlspecialchars($stl['nama_buyer']) ?></strong> (<?= htmlspecialchars($stl['metode_bayar']) ?>)</div>
                <?php if (!empty($stl['tanggal_cair_primer'])): ?>
                    <div style="font-size:11.5px; color:#475569; margin-top:2px;">Cair ke Primer: <?= date('d M Y, H:i', strtotime($stl['tanggal_cair_primer'])) ?> WIB</div>
                <?php endif; ?>
                <?php if (!empty($stl['tanggal_cair_petani'])): ?>
                    <div style="font-size:11.5px; color:#059669; font-weight:700; margin-top:1px;">Diteruskan ke Petani: <?= date('d M Y, H:i', strtotime($stl['tanggal_cair_petani'])) ?> WIB</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- RINCIAN KOMODITAS YANG DI-SETTLEMENT -->
        <?php if (!empty($items)): ?>
        <div style="font-size:12px; font-weight:800; color:#0F172A; margin-bottom:7px; text-transform:uppercase; letter-spacing:0.4px;">
            A. Rincian Komoditas Pasokan Unit
        </div>
        <table class="doc-table">
            <thead>
                <tr>
                    <th style="width:40px; text-align:center;">No</th>
                    <th>Kode &amp; Nama Komoditas</th>
                    <th style="text-align:right;">Volume</th>
                    <th style="text-align:right;">Harga Satuan</th>
                    <th style="text-align:right;">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $idx => $it): ?>
                <tr>
                    <td style="text-align:center; color:#64748B;"><?= $idx + 1 ?></td>
                    <td>
                        <span style="font-family:monospace; font-size:11px; color:#059669; font-weight:700;"><?= htmlspecialchars($it['kode_produk']) ?></span>
                        <span style="font-weight:700; color:#0F172A; margin-left:4px;"><?= htmlspecialchars($it['nama_produk']) ?></span>
                    </td>
                    <td style="text-align:right;"><?= number_format((float)$it['qty'], 0, ',', '.') ?> <?= htmlspecialchars($it['satuan']) ?></td>
                    <td style="text-align:right;"><?= rupiah((float)$it['harga_satuan']) ?></td>
                    <td style="text-align:right; font-weight:700;"><?= rupiah((float)$it['subtotal']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <!-- TABEL RINCIAN PEMBAGIAN KAS & HAK -->
        <div style="font-size:12px; font-weight:800; color:#0F172A; margin-bottom:7px; text-transform:uppercase; letter-spacing:0.4px;">
            <?= !empty($items) ? 'B. ' : '' ?>Alokasi Distribusi Dana &amp; SHU Berjenjang
        </div>
        <table class="doc-table" style="margin-bottom:24px;">
            <thead>
                <tr>
                    <th>Komponen Distribusi Dana</th>
                    <th>Penerima Manfaat</th>
                    <th style="text-align:right;">Nominal Rupiah</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>1. Hak Pembayaran Hasil Panen</strong></td>
                    <td>Petani Pemasok Binaan (via Primer)</td>
                    <td style="text-align:right; font-weight:800; color:#059669;"><?= rupiah((float)$stl['hak_petani']) ?></td>
                </tr>
                <tr>
                    <td><strong>2. Kas Operasional &amp; Margin Unit</strong></td>
                    <td><?= htmlspecialchars($stl['nama_koperasi']) ?></td>
                    <td style="text-align:right; font-weight:800; color:#D97706;"><?= rupiah((float)$stl['kas_primer']) ?></td>
                </tr>
                <tr>
                    <td><strong>3. Kas SHU &amp; Sistem Pusat</strong></td>
                    <td>Koperasi Konsumen Pusat (Nasional)</td>
                    <td style="text-align:right; font-weight:800; color:#2563EB;"><?= rupiah((float)$stl['kas_konsumen']) ?></td>
                </tr>
            </tbody>
            <tfoot>
                <tr style="background:#F0FDF4;">
                    <td colspan="2" style="text-align:right; font-weight:800; color:#064E3B;">TOTAL NILAI TRANSAKSI BRUTO</td>
                    <td style="text-align:right; font-weight:800; font-size:14.5px; color:#064E3B;"><?= rupiah((float)$stl['nominal_bruto']) ?></td>
                </tr>
            </tfoot>
        </table>

        <!-- TANDA TANGAN 3 PIHAK -->
        <div class="ttd-grid">
            <div>
                <div style="color:#64748B;">Menyetujui Transfer,</div>
                <div style="font-weight:800; margin-top:2px;">Bendahara Konsumen Pusat</div>
                <div style="height:58px;"></div>
                <div style="border-top:1px solid #94A3B8; display:inline-block; padding-top:4px; min-width:155px; font-weight:700;">Otorisasi Pusat</div>
            </div>
            <div>
                <div style="color:#64748B;">Diterima &amp; Disalurkan,</div>
                <div style="font-weight:800; margin-top:2px;"><?= htmlspecialchars($stl['nama_koperasi']) ?></div>
                <div style="height:58px;"></div>
                <div style="border-top:1px solid #94A3B8; display:inline-block; padding-top:4px; min-width:155px; font-weight:700;"><?= htmlspecialchars($stl['ketua_koperasi']) ?></div>
            </div>
            <div>
                <div style="color:#64748B;">Penerima Akhir Hulu,</div>
                <div style="font-weight:800; margin-top:2px;">Perwakilan Petani Pemasok</div>
                <div style="height:58px;"></div>
                <div style="border-top:1px solid #94A3B8; display:inline-block; padding-top:4px; min-width:155px; font-weight:700;">Tanda Terima Petani</div>
            </div>
        </div>

        <div style="margin-top:24px; padding-top:12px; border-top:1px dashed #CBD5E1; display:flex; justify-content:space-between; flex-wrap:wrap; gap:8px; font-size:10.5px; color:#64748B;">
            <span>Dicetak melalui Sistem KOPNOVA Treasury pada <?= date('d M Y, H:i') ?> WIB</span>
            <span>Dokumen sah sebagai bukti audit internal distribusi SHU koperasi</span>
        </div>

    </div>
</body>
</html>