<?php
require_once '../../config/database.php';
cekAkses(['buyer']);
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

$user        = $_SESSION['user'] ?? [];
$cart        = $_SESSION['cart'] ?? [];
$items       = [];
$grand_total = 0;
$total_qty   = 0;
$has_stock_issue = false;

if (!empty($cart)) {
    $cart_ids     = array_map('intval', array_keys($cart));
    $placeholders = implode(',', array_fill(0, count($cart_ids), '?'));

    $stmt = $pdo->prepare("
        SELECT pr.*, kp.nama_koperasi, d.nama_divisi
        FROM produk_etalase pr
        JOIN koperasi_primer kp ON pr.id_primer = kp.id_primer
        JOIN divisi_wilayah d ON pr.id_divisi = d.id_divisi
        WHERE pr.id_produk IN ({$placeholders})
    ");
    $stmt->execute($cart_ids);

    $found_ids = [];
    while ($row = $stmt->fetch()) {
        $pid       = (int)$row['id_produk'];
        $found_ids[] = $pid;
        $qty       = max(1, (int)($cart[$pid] ?? 1));
        $stok_now  = (int)$row['stok'];

        if ($stok_now <= 0 || $qty > $stok_now) {
            $has_stock_issue = true;
        }

        $subtotal     = $qty * (float)$row['harga_jual'];
        $grand_total += $subtotal;
        $total_qty   += $qty;

        $row['qty']      = $qty;
        $row['subtotal'] = $subtotal;
        $items[]         = $row;
    }

    // Bersihkan ID produk yang sudah dihapus dari database
    foreach ($cart_ids as $cid) {
        if (!in_array($cid, $found_ids, true)) {
            unset($_SESSION['cart'][$cid]);
        }
    }
}
?>

<style>
    /* KONSISTENSI SIDEBAR & LAYOUT KERANJANG */
    #mainSidebar {
        width: 262px !important;
    }
    #mainSidebar a {
        font-size: 13px !important;
        padding: 10px 13px !important;
        gap: 10px !important;
        white-space: nowrap !important;
    }
    #mainSidebar a svg {
        flex-shrink: 0 !important;
        width: 17px !important;
        height: 17px !important;
    }

    .cart-wrap {
        display: flex;
        flex-direction: column;
        gap: 20px;
        color: #0F172A;
    }

    .cart-hero {
        background: linear-gradient(135deg, #042F24 0%, #064E3B 58%, #047857 100%);
        border-radius: 18px;
        padding: 24px 26px;
        color: #FFFFFF;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 16px;
        box-shadow: 0 10px 26px rgba(4, 47, 36, 0.14);
        border: 1px solid rgba(16, 185, 129, 0.25);
    }
    .cart-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        background: rgba(255, 255, 255, 0.12);
        border: 1px solid rgba(255, 255, 255, 0.18);
        color: #6EE7B7;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 0.7px;
        text-transform: uppercase;
        padding: 5px 12px;
        border-radius: 99px;
        margin-bottom: 10px;
    }
    .cart-eyebrow-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: #FBBF24;
    }
    .cart-back-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: #FFFFFF;
        color: #064E3B;
        text-decoration: none;
        padding: 11px 16px;
        border-radius: 12px;
        font-weight: 800;
        font-size: 13px;
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.14);
        white-space: nowrap;
    }
    .cart-back-btn:hover {
        background: #ECFDF5;
    }

    /* LAYOUT 2 KOLOM RESPONSIF */
    .cart-layout {
        display: grid;
        grid-template-columns: minmax(0, 1.65fr) minmax(300px, 1fr);
        gap: 20px;
        align-items: start;
    }
    .cart-card {
        background: #FFFFFF;
        border-radius: 16px;
        border: 1px solid #E2E8F0;
        padding: 22px;
        box-shadow: 0 2px 10px rgba(15, 23, 42, 0.03);
    }

    .cart-item-list {
        display: flex;
        flex-direction: column;
        gap: 14px;
    }
    .cart-item-row {
        display: grid;
        grid-template-columns: 72px minmax(0, 1fr) auto auto;
        align-items: center;
        gap: 14px;
        padding: 14px;
        border: 1px solid #E2E8F0;
        border-radius: 14px;
        background: #FFFFFF;
    }
    .cart-item-thumb {
        width: 72px;
        height: 72px;
        object-fit: cover;
        border-radius: 11px;
        border: 1px solid #E2E8F0;
        background: #F8FAFC;
    }

    /* STEPPER QTY DI KERANJANG */
    .cart-stepper {
        display: inline-flex;
        align-items: center;
        border: 1px solid #CBD5E1;
        border-radius: 10px;
        overflow: hidden;
        background: #F8FAFC;
    }
    .cart-step-btn {
        width: 30px;
        height: 34px;
        border: none;
        background: transparent;
        color: #0F172A;
        font-family: inherit;
        font-size: 15px;
        font-weight: 800;
        cursor: pointer;
    }
    .cart-step-btn:hover {
        background: #E2E8F0;
    }
    .cart-qty-input {
        width: 44px;
        height: 34px;
        border: none;
        border-left: 1px solid #E2E8F0;
        border-right: 1px solid #E2E8F0;
        background: #FFFFFF;
        text-align: center;
        font-family: inherit;
        font-weight: 800;
        font-size: 13px;
        color: #0F172A;
        -moz-appearance: textfield;
    }
    .cart-qty-input:focus {
        outline: none;
    }
    .cart-qty-input::-webkit-outer-spin-button,
    .cart-qty-input::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }

    .cart-del-btn {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        background: #FEF2F2;
        color: #DC2626;
        border: 1px solid #FECACA;
        padding: 7px 11px;
        border-radius: 9px;
        font-family: inherit;
        font-size: 12px;
        font-weight: 700;
        cursor: pointer;
    }
    .cart-del-btn:hover {
        background: #FEE2E2;
    }

    .cart-input {
        width: 100%;
        box-sizing: border-box;
        padding: 10px 12px;
        border: 1px solid #CBD5E1;
        border-radius: 10px;
        margin-top: 5px;
        font-family: inherit;
        font-size: 13px;
        color: #0F172A;
        background: #FFFFFF;
    }
    .cart-input:focus {
        outline: none;
        border-color: #10B981;
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.14);
    }

    .cart-checkout-btn {
        width: 100%;
        background: #D97706;
        color: #FFFFFF;
        border: none;
        padding: 13px 16px;
        border-radius: 11px;
        font-family: inherit;
        font-weight: 800;
        font-size: 14.5px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        transition: background-color 0.15s ease;
    }
    .cart-checkout-btn:hover {
        background: #B45309;
    }
    .cart-checkout-btn:disabled {
        background: #94A3B8;
        cursor: not-allowed;
    }

    .mobile-nav-trigger {
        display: none;
    }

    @media (max-width: 960px) {
        .cart-layout {
            grid-template-columns: minmax(0, 1fr);
        }
        #mainSidebar {
            position: fixed !important;
            left: -280px;
            top: 0;
            bottom: 0;
            transition: left 0.25s ease;
            box-shadow: 12px 0 32px rgba(0, 0, 0, 0.28);
        }
        #mainSidebar.open {
            left: 0 !important;
        }
        .mobile-nav-trigger {
            display: inline-flex !important;
            align-items: center;
            gap: 8px;
            background: #064E3B;
            color: #FFFFFF;
            border: none;
            padding: 10px 15px;
            border-radius: 11px;
            font-family: inherit;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            margin-bottom: 14px;
        }
    }

    @media (max-width: 640px) {
        .cart-hero {
            padding: 20px;
        }
        .cart-back-btn {
            width: 100%;
            justify-content: center;
        }
        .cart-item-row {
            grid-template-columns: 64px minmax(0, 1fr);
        }
        .cart-item-controls {
            grid-column: 1 / -1;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 10px;
            border-top: 1px dashed #E2E8F0;
        }
        .cart-item-actions {
            grid-column: 1 / -1;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
    }
</style>

<!-- TOMBOL BUKA MENU UNTUK LAYAR HP -->
<div>
    <button type="button" class="mobile-nav-trigger" onclick="toggleKopSidebar()">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        <span>Buka Menu Navigasi</span>
    </button>
</div>
<div id="kopBackdrop" onclick="toggleKopSidebar()" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.5); z-index:45;"></div>

<div class="cart-wrap">

    <!-- HERO HEADER -->
    <section class="cart-hero">
        <div>
            <div class="cart-eyebrow">
                <span class="cart-eyebrow-dot"></span>
                <span>KERANJANG BELANJA &amp; CHECKOUT B2B/RETAIL</span>
            </div>
            <h2 style="margin:0 0 6px; font-size:22px; font-weight:800; line-height:1.25;">Keranjang Belanja &amp; Checkout</h2>
            <p style="margin:0; font-size:13.5px; color:rgba(236,253,245,0.88); max-width:580px; line-height:1.5;">
                Periksa kembali jumlah pesanan komoditas Anda sebelum membuat invoice resmi ke Koperasi Konsumen.
            </p>
        </div>

        <a href="index.php" class="cart-back-btn">
            &larr; Lanjut Belanja
        </a>
    </section>

    <?php if (isset($_GET['msg']) && $_GET['msg'] !== ''): ?>
    <div style="background:#ECFDF5; border:1px solid #A7F3D0; border-left:5px solid #059669; color:#065F46; padding:13px 18px; border-radius:12px; font-weight:700; font-size:13.5px; display:flex; align-items:center; gap:10px;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
        <span><?= htmlspecialchars($_GET['msg']) ?></span>
    </div>
    <?php endif; ?>

    <?php if (isset($_GET['err']) && $_GET['err'] !== ''): ?>
    <div style="background:#FEF2F2; border:1px solid #FECACA; border-left:5px solid #DC2626; color:#991B1B; padding:13px 18px; border-radius:12px; font-weight:700; font-size:13.5px; display:flex; align-items:center; gap:10px;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <span><?= htmlspecialchars($_GET['err']) ?></span>
    </div>
    <?php endif; ?>

    <?php if (empty($items)): ?>
        <div class="cart-card" style="padding:48px 24px; text-align:center;">
            <div style="width:54px; height:54px; border-radius:14px; background:#ECFDF5; color:#059669; display:flex; align-items:center; justify-content:center; margin:0 auto 14px;">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
            </div>
            <h3 style="margin:0 0 6px; font-size:18px; color:#0F172A;">Keranjang Belanja Anda Masih Kosong</h3>
            <p style="margin:0 auto 18px; max-width:420px; font-size:13.5px; color:#64748B; line-height:1.55;">
                Pilih komoditas hasil panen atau produk olahan koperasi terlebih dahulu di etalase marketplace.
            </p>
            <a href="index.php" style="display:inline-flex; align-items:center; gap:8px; background:#064E3B; color:#FFFFFF; text-decoration:none; padding:11px 22px; border-radius:10px; font-weight:700; font-size:13.5px;">
                Mulai Pilih Produk
            </a>
        </div>
    <?php else: ?>
        <div class="cart-layout">
            <!-- DAFTAR ITEM KERANJANG -->
            <div class="cart-card">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; margin-bottom:16px; padding-bottom:12px; border-bottom:1px solid #F1F5F9;">
                    <h3 style="margin:0; font-size:16.5px; font-weight:800; color:#0F172A;">
                        Daftar Komoditas Dipilih (<?= count($items) ?> Jenis)
                    </h3>
                    <span style="font-size:12px; font-weight:700; color:#64748B;">
                        Total Kuantitas: <?= number_format($total_qty, 0, ',', '.') ?> Unit
                    </span>
                </div>

                <div class="cart-item-list">
                    <?php foreach ($items as $it):
                        $stok_ok = ((int)$it['stok'] >= (int)$it['qty']) && ((int)$it['stok'] > 0);
                    ?>
                    <div class="cart-item-row" style="<?= !$stok_ok ? 'border-color:#FECACA; background:#FEF2F2;' : '' ?>">
                        <img src="<?= BASE_URL ?>/uploads/produk/<?= htmlspecialchars($it['foto_produk']) ?>"
                             alt="<?= htmlspecialchars($it['nama_produk']) ?>"
                             loading="lazy"
                             decoding="async"
                             onerror="this.src='https://placehold.co/120x120/064e3b/ffffff?text=KOPNOVA'"
                             class="cart-item-thumb">

                        <div>
                            <div style="font-size:11px; font-weight:700; color:#B45309;">
                                <?= htmlspecialchars($it['nama_koperasi']) ?> &bull; <?= htmlspecialchars($it['nama_divisi']) ?>
                            </div>
                            <div style="font-size:15px; font-weight:800; color:#0F172A; margin:2px 0;">
                                <?= htmlspecialchars($it['nama_produk']) ?>
                            </div>
                            <div style="font-size:12.5px; color:#475569; font-weight:600;">
                                <?= rupiah($it['harga_jual']) ?> / <?= htmlspecialchars($it['satuan']) ?>
                                <span style="margin-left:8px; font-size:11.5px; color:<?= $stok_ok ? '#059669' : '#DC2626' ?>;">
                                    (Stok: <?= number_format((int)$it['stok'], 0, ',', '.') ?>)
                                </span>
                            </div>
                            <?php if (!$stok_ok): ?>
                                <div style="font-size:11.5px; font-weight:700; color:#DC2626; margin-top:4px;">
                                    Jumlah melebihi stok tersedia. Kurangi jumlah atau hapus item ini.
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- STEPPER UPDATE QTY (POST + CSRF) -->
                        <div class="cart-item-controls">
                            <form action="proses_cart.php" method="POST" style="margin:0;">
                                <?php if (function_exists('csrf_field')) echo csrf_field(); ?>
                                <input type="hidden" name="aksi" value="update">
                                <input type="hidden" name="id_produk" value="<?= (int)$it['id_produk'] ?>">
                                <div class="cart-stepper">
                                    <button type="button" class="cart-step-btn" onclick="stepCartQty(this, -1)" aria-label="Kurangi">&minus;</button>
                                    <input type="number" name="qty" value="<?= (int)$it['qty'] ?>" min="1" max="<?= max(1, (int)$it['stok']) ?>" class="cart-qty-input" onchange="this.form.submit()">
                                    <button type="button" class="cart-step-btn" onclick="stepCartQty(this, 1)" aria-label="Tambah">+</button>
                                </div>
                            </form>
                        </div>

                        <!-- SUBTOTAL & TOMBOL HAPUS (POST + CSRF) -->
                        <div class="cart-item-actions" style="text-align:right;">
                            <div style="font-size:15px; font-weight:800; color:#064E3B; margin-bottom:6px; font-variant-numeric:tabular-nums;">
                                <?= rupiah($it['subtotal']) ?>
                            </div>
                            <form action="proses_cart.php" method="POST" style="margin:0;" onsubmit="return confirm('Hapus <?= htmlspecialchars($it['nama_produk'], ENT_QUOTES) ?> dari keranjang?');">
                                <?php if (function_exists('csrf_field')) echo csrf_field(); ?>
                                <input type="hidden" name="aksi" value="hapus">
                                <input type="hidden" name="id_produk" value="<?= (int)$it['id_produk'] ?>">
                                <button type="submit" class="cart-del-btn">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                                    <span>Hapus</span>
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- RINGKASAN CHECKOUT -->
            <div class="cart-card">
                <h3 style="margin:0 0 14px; font-size:16.5px; font-weight:800; color:#0F172A;">Ringkasan Pembayaran</h3>

                <div style="display:flex; justify-content:space-between; font-size:13px; color:#64748B; margin-bottom:8px;">
                    <span>Total Jenis Komoditas</span>
                    <strong style="color:#0F172A;"><?= count($items) ?> Produk</strong>
                </div>
                <div style="display:flex; justify-content:space-between; font-size:13px; color:#64748B; margin-bottom:14px;">
                    <span>Total Kuantitas</span>
                    <strong style="color:#0F172A;"><?= number_format($total_qty, 0, ',', '.') ?> Unit</strong>
                </div>

                <div style="display:flex; justify-content:space-between; align-items:baseline; font-size:18px; font-weight:800; color:#064E3B; padding:14px 0; border-top:1px solid #E2E8F0; border-bottom:1px solid #E2E8F0; margin-bottom:16px; font-variant-numeric:tabular-nums;">
                    <span>Total Tagihan</span>
                    <span><?= rupiah($grand_total) ?></span>
                </div>

                <form action="proses_cart.php" method="POST" style="display:flex; flex-direction:column; gap:13px; margin:0;">
                    <?php if (function_exists('csrf_field')) echo csrf_field(); ?>
                    <input type="hidden" name="aksi" value="checkout">

                    <div>
                        <label for="metode_bayar" style="font-size:12px; font-weight:700; color:#334155;">Metode Pembayaran</label>
                        <select name="metode_bayar" id="metode_bayar" class="cart-input" required>
                            <option value="QRIS Instan">QRIS Instan Koperasi</option>
                            <option value="Transfer Virtual Account">Transfer Virtual Account</option>
                            <option value="Termin B2B">Termin B2B</option>
                        </select>
                    </div>

                    <div>
                        <label for="alamat_pengiriman" style="font-size:12px; font-weight:700; color:#334155;">Alamat Pengiriman</label>
                        <textarea name="alamat_pengiriman" id="alamat_pengiriman" rows="3" required placeholder="Masukkan alamat lengkap tujuan pengiriman..." class="cart-input"><?= htmlspecialchars($user['alamat'] ?? '') ?></textarea>
                    </div>

                    <button type="submit" class="cart-checkout-btn" <?= $has_stock_issue ? 'disabled' : '' ?>>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><polyline points="20 6 9 17 4 12"/></svg>
                        <span><?= $has_stock_issue ? 'Sesuaikan Stok Terlebih Dahulu' : 'Buat Pesanan Sekarang' ?></span>
                    </button>
                </form>
            </div>
        </div>
    <?php endif; ?>

</div>

<script>
function stepCartQty(btn, delta) {
    const form  = btn.closest('form');
    const input = form ? form.querySelector('.cart-qty-input') : null;
    if (!input) return;

    const min = parseInt(input.min, 10) || 1;
    const max = parseInt(input.max, 10) || 9999;
    const cur = parseInt(input.value, 10) || min;
    const nxt = Math.min(max, Math.max(min, cur + delta));

    if (nxt !== cur) {
        input.value = nxt;
        form.submit();
    }
}

function toggleKopSidebar() {
    const sb = document.getElementById('mainSidebar');
    const bd = document.getElementById('kopBackdrop');
    if (!sb) return;
    const isOpen = sb.classList.toggle('open');
    if (bd) bd.style.display = isOpen ? 'block' : 'none';
}
</script>

<?php require_once '../../includes/footer.php'; ?>