<?php
require_once '../../config/database.php';
cekAkses(['konsumen', 'primer', 'buyer']);

$role     = $_SESSION['user']['role'] ?? '';
$user     = $_SESSION['user'] ?? [];
$id_order = (int)($_GET['id_order'] ?? 0);

if ($id_order <= 0) {
    http_response_code(400);
    die("Parameter ID pesanan tidak valid.");
}

if ($role === 'buyer') {
    $stmt = $pdo->prepare("
        SELECT o.*, u.nama_lengkap AS nama_buyer, u.no_hp AS hp_buyer
        FROM orders o
        JOIN users u ON o.id_buyer = u.id_user
        WHERE o.id_order = ? AND o.id_buyer = ?
    ");
    $stmt->execute([$id_order, (int)($user['id_user'] ?? 0)]);
} elseif ($role === 'primer') {
    $id_primer_login = (int)($user['id_primer'] ?? 0);
    $stmt = $pdo->prepare("
        SELECT o.*, u.nama_lengkap AS nama_buyer, u.no_hp AS hp_buyer
        FROM orders o
        JOIN users u ON o.id_buyer = u.id_user
        WHERE o.id_order = ? AND EXISTS (
            SELECT 1 FROM order_items oi
            JOIN produk_etalase pr ON oi.id_produk = pr.id_produk
            WHERE oi.id_order = o.id_order AND pr.id_primer = ?
        )
    ");
    $stmt->execute([$id_order, $id_primer_login]);
} else {
    $stmt = $pdo->prepare("
        SELECT o.*, u.nama_lengkap AS nama_buyer, u.no_hp AS hp_buyer
        FROM orders o
        JOIN users u ON o.id_buyer = u.id_user
        WHERE o.id_order = ?
    ");
    $stmt->execute([$id_order]);
}

$order = $stmt->fetch();

if (!$order) {
    http_response_code(404);
    die("Dokumen Surat Jalan tidak ditemukan atau Anda tidak memiliki akses.");
}

// Hanya Koperasi Konsumen yang berhak menerbitkan nomor surat jalan resmi
$is_draft = false;
if (empty($order['no_surat_jalan'])) {
    if ($role === 'konsumen') {
        $no_sj = 'SJ-KPN-' . date('Ymd') . '-' . str_pad((string)$id_order, 4, '0', STR_PAD_LEFT);
        $pdo->prepare("UPDATE orders SET no_surat_jalan = ? WHERE id_order = ?")->execute([$no_sj, $id_order]);
        $order['no_surat_jalan'] = $no_sj;
    } else {
        $order['no_surat_jalan'] = 'DRAFT-BELUM-TERBIT';
        $is_draft = true;
    }
}

$stmtItems = $pdo->prepare("
    SELECT oi.*, pr.nama_produk, pr.kode_produk, pr.satuan,
           kp.id_primer, kp.nama_koperasi, kp.kecamatan, kp.ketua_koperasi,
           d.nama_divisi
    FROM order_items oi
    JOIN produk_etalase pr ON oi.id_produk = pr.id_produk
    JOIN koperasi_primer kp ON pr.id_primer = kp.id_primer
    JOIN divisi_wilayah d ON pr.id_divisi = d.id_divisi
    WHERE oi.id_order = ?
    ORDER BY kp.id_primer ASC, oi.id_item ASC
");
$stmtItems->execute([$id_order]);
$items = $stmtItems->fetchAll();

$id_primer_login = (int)($user['id_primer'] ?? 0);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Surat Jalan Resmi - <?= htmlspecialchars($order['no_surat_jalan']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
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
        .no-print-bar {
            max-width: 840px;
            margin: 0 auto 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            background: #042F24;
            color: #FFFFFF;
            padding: 13px 20px;
            border-radius: 14px;
            box-shadow: 0 8px 20px rgba(4, 47, 36, 0.18);
        }
        .btn-print {
            background: #F59E0B;
            color: #042F24;
            border: none;
            padding: 9px 18px;
            border-radius: 9px;
            font-family: inherit;
            font-weight: 800;
            font-size: 13px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 7px;
        }
        .btn-print:hover {
            background: #FBBF24;
        }
        .btn-close {
            background: rgba(255, 255, 255, 0.14);
            color: #FFFFFF;
            border: 1px solid rgba(255, 255, 255, 0.22);
            padding: 9px 15px;
            border-radius: 9px;
            font-family: inherit;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
        }
        .btn-close:hover {
            background: rgba(255, 255, 255, 0.22);
        }
        .sheet {
            max-width: 840px;
            margin: 0 auto;
            background: #FFFFFF;
            padding: 38px 44px;
            border-radius: 14px;
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.1);
        }
        .kop-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 16px;
            border-bottom: 3px solid #064E3B;
            padding-bottom: 18px;
            margin-bottom: 22px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            background: #F8FAFC;
            padding: 16px 20px;
            border-radius: 10px;
            border: 1px solid #E2E8F0;
            margin-bottom: 22px;
            font-size: 13px;
        }
        .info-right {
            text-align: right;
        }
        .table-scroll {
            width: 100%;
            overflow-x: auto;
            margin-bottom: 22px;
        }
        .doc-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12.5px;
        }
        .doc-table th {
            background: #064E3B;
            color: #FFFFFF;
            padding: 10px;
            border: 1px solid #064E3B;
            font-weight: 700;
        }
        .doc-table td {
            padding: 10px;
            border: 1px solid #CBD5E1;
            vertical-align: top;
        }
        .row-own-unit {
            background: #ECFDF5;
        }
        .sign-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            text-align: center;
            margin-top: 34px;
            font-size: 12px;
            break-inside: avoid;
        }

        @media (max-width: 680px) {
            .sheet {
                padding: 22px 18px;
            }
            .info-grid {
                grid-template-columns: 1fr;
            }
            .info-right {
                text-align: left;
            }
            .sign-grid {
                grid-template-columns: 1fr;
                gap: 26px;
            }
        }

        @page {
            size: A4;
            margin: 12mm;
        }

        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background: #FFFFFF !important;
                padding: 0 !important;
            }
            .sheet {
                box-shadow: none !important;
                border: none !important;
                border-radius: 0 !important;
                margin: 0 !important;
                padding: 0 !important;
                max-width: 100% !important;
            }
            .table-scroll {
                overflow: visible !important;
            }
        }
    </style>
</head>
<body>

    <!-- ACTION BAR CETAK (TIDAK IKUT TERCETAK) -->
    <div class="no-print no-print-bar">
        <div style="font-size:13px; font-weight:700;">Pratinjau Dokumen Resmi Logistik &amp; Invoice Multi-Kecamatan</div>
        <div style="display:flex; gap:10px;">
            <button type="button" onclick="window.print()" class="btn-print">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                <span>Cetak / Simpan PDF</span>
            </button>
            <button type="button" onclick="tutupPratinjau()" class="btn-close">
                Tutup
            </button>
        </div>
    </div>

    <!-- LEMBAR DOKUMEN A4 -->
    <div class="sheet">

        <!-- KOP SURAT KOPNOVA -->
        <div class="kop-header">
            <div style="display:flex; align-items:center; gap:14px;">
                <div style="width:48px; height:48px; background:#064E3B; color:#FFFFFF; border-radius:12px; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:24px; flex-shrink:0;">K</div>
                <div>
                    <div style="font-size:22px; font-weight:800; color:#064E3B; letter-spacing:0.4px;">KOPNOVA</div>
                    <div style="font-size:12px; font-weight:700; color:#059669;">KOPERASI KONSUMEN PUSAT • RANTAI PASOK AGRIBISNIS</div>
                    <div style="font-size:11px; color:#64748B;">Sistem Distribusi Terpadu Lintas Kabupaten &amp; Koperasi Primer Kecamatan</div>
                </div>
            </div>
            <div style="text-align:right;">
                <div style="font-size:17px; font-weight:800; color:#0F172A;">SURAT JALAN &amp; INVOICE</div>
                <div style="font-size:13px; font-weight:800; color:<?= $is_draft ? '#B45309' : '#059669' ?>; margin-top:3px; font-family:monospace;">
                    <?= htmlspecialchars($order['no_surat_jalan']) ?>
                </div>
                <div style="font-size:11.5px; color:#64748B; margin-top:2px;">Ref. Invoice: <?= htmlspecialchars($order['no_invoice']) ?></div>
            </div>
        </div>

        <?php if ($is_draft): ?>
            <div style="background:#FEF3C7; border:1px solid #FDE68A; color:#92400E; padding:10px 14px; border-radius:8px; font-size:12px; font-weight:700; margin-bottom:18px;">
                Catatan: Dokumen ini masih berstatus DRAFT karena nomor Surat Jalan resmi belum diterbitkan oleh Koperasi Konsumen Pusat.
            </div>
        <?php endif; ?>

        <!-- INFORMASI TUJUAN PENGIRIMAN & PEMBAYARAN -->
        <div class="info-grid">
            <div>
                <div style="font-size:11px; font-weight:800; color:#64748B; text-transform:uppercase; letter-spacing:0.4px;">Kepada Yth. (Penerima / Buyer)</div>
                <div style="font-weight:800; font-size:15px; color:#0F172A; margin:4px 0;"><?= htmlspecialchars($order['nama_buyer']) ?></div>
                <div style="color:#475569;">Kontak: <?= htmlspecialchars($order['hp_buyer'] ?: '-') ?></div>
                <div style="color:#475569; margin-top:3px; line-height:1.45;">Alamat Tujuan: <?= htmlspecialchars($order['alamat_pengiriman']) ?></div>
            </div>
            <div class="info-right">
                <div style="font-size:11px; font-weight:800; color:#64748B; text-transform:uppercase; letter-spacing:0.4px;">Detail Transaksi &amp; Logistik</div>
                <div style="margin-top:5px;">Tanggal Terbit: <strong><?= date('d F Y, H:i', strtotime($order['tanggal_order'])) ?></strong></div>
                <div style="margin-top:3px;">Metode Pembayaran: <strong><?= htmlspecialchars($order['metode_bayar']) ?></strong></div>
                <div style="margin-top:3px;">Status Pengiriman: <strong><?= htmlspecialchars($order['status_order']) ?></strong></div>
            </div>
        </div>

        <!-- TABEL KONSOLIDASI PICKING LIST LINTAS KECAMATAN -->
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; margin-bottom:8px;">
            <div style="font-size:13px; font-weight:800; color:#064E3B;">
                Rincian Konsolidasi Komoditas &amp; Titik Pengambilan Koperasi Primer:
            </div>
            <?php if ($role === 'primer' && $id_primer_login > 0): ?>
                <div style="font-size:11px; font-weight:700; color:#065F46; background:#ECFDF5; border:1px solid #A7F3D0; padding:3px 9px; border-radius:99px;">
                    Baris hijau = titik ambil unit koperasi Anda
                </div>
            <?php endif; ?>
        </div>

        <div class="table-scroll">
            <table class="doc-table">
                <thead>
                    <tr style="text-align:left;">
                        <th style="text-align:center; width:42px;">No</th>
                        <th>Unit Koperasi Primer (Asal Barang)</th>
                        <th>Komoditas / Produk</th>
                        <th style="text-align:center;">Qty</th>
                        <th style="text-align:right;">Harga Satuan</th>
                        <th style="text-align:right;">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; foreach ($items as $it):
                        $is_own_unit = ($role === 'primer' && $id_primer_login > 0 && (int)$it['id_primer'] === $id_primer_login);
                    ?>
                    <tr class="<?= $is_own_unit ? 'row-own-unit' : '' ?>">
                        <td style="text-align:center; font-variant-numeric:tabular-nums;"><?= $no++ ?></td>
                        <td>
                            <strong><?= htmlspecialchars($it['nama_koperasi']) ?></strong><br>
                            <span style="font-size:11px; color:#64748B;">Kec. <?= htmlspecialchars($it['kecamatan']) ?> (<?= htmlspecialchars($it['nama_divisi']) ?>)</span>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($it['nama_produk']) ?></strong><br>
                            <span style="font-size:11px; color:#64748B;">Kode: <?= htmlspecialchars($it['kode_produk']) ?></span>
                        </td>
                        <td style="text-align:center; font-weight:700; font-variant-numeric:tabular-nums; white-space:nowrap;">
                            <?= number_format((int)$it['qty'], 0, ',', '.') ?> <?= htmlspecialchars($it['satuan']) ?>
                        </td>
                        <td style="text-align:right; font-variant-numeric:tabular-nums; white-space:nowrap;">
                            <?= rupiah($it['harga_satuan']) ?>
                        </td>
                        <td style="text-align:right; font-weight:700; font-variant-numeric:tabular-nums; white-space:nowrap;">
                            <?= rupiah($it['subtotal']) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:#F0FDF4;">
                        <td colspan="5" style="padding:12px; text-align:right; font-weight:800; font-size:13.5px; color:#064E3B;">
                            TOTAL TAGIHAN PEMBAYARAN
                        </td>
                        <td style="padding:12px; text-align:right; font-weight:800; font-size:14.5px; color:#064E3B; font-variant-numeric:tabular-nums; white-space:nowrap;">
                            <?= rupiah($order['total_bayar']) ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- KOLOM OTORISASI TANDA TANGAN 3 PIHAK -->
        <div class="sign-grid">
            <div>
                <div style="color:#64748B;">Diterbitkan Oleh,</div>
                <div style="font-weight:800; color:#0F172A; margin-top:2px;">Koperasi Konsumen Pusat</div>
                <div style="height:64px;"></div>
                <div style="border-top:1px solid #94A3B8; display:inline-block; padding-top:5px; min-width:165px; font-weight:700;">Admin Pusat KOPNOVA</div>
            </div>
            <div>
                <div style="color:#64748B;">Armada &amp; Konsolidasi,</div>
                <div style="font-weight:800; color:#0F172A; margin-top:2px;">Koordinator Logistik</div>
                <div style="height:64px;"></div>
                <div style="border-top:1px solid #94A3B8; display:inline-block; padding-top:5px; min-width:165px; font-weight:700;">Petugas Pengiriman</div>
            </div>
            <div>
                <div style="color:#64748B;">Diterima Baik Oleh,</div>
                <div style="font-weight:800; color:#0F172A; margin-top:2px;"><?= htmlspecialchars($order['nama_buyer']) ?></div>
                <div style="height:64px;"></div>
                <div style="border-top:1px solid #94A3B8; display:inline-block; padding-top:5px; min-width:165px; font-weight:700;">Penerima Barang</div>
            </div>
        </div>

    </div>

    <script>
    function tutupPratinjau() {
        if (window.opener && !window.opener.closed) {
            window.close();
        } else if (window.history.length > 1) {
            window.history.back();
        } else {
            window.location.href = 'index.php';
        }
    }
    </script>
</body>
</html>