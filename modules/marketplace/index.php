<?php
require_once '../../config/database.php';
cekAkses(['buyer']);
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

$produk_list = $pdo->query("SELECT pr.*, kp.nama_koperasi, kp.kecamatan, d.nama_divisi 
                            FROM produk_etalase pr 
                            JOIN koperasi_primer kp ON pr.id_primer = kp.id_primer 
                            JOIN divisi_wilayah d ON pr.id_divisi = d.id_divisi 
                            WHERE pr.stok > 0 ORDER BY pr.id_produk DESC")->fetchAll();

// Ekstrak daftar kategori unik secara dinamis untuk filter cepat
$kategori_unik = [];
foreach ($produk_list as $item) {
    $kat = trim((string)($item['kategori'] ?? ''));
    if ($kat !== '' && !in_array($kat, $kategori_unik, true)) {
        $kategori_unik[] = $kat;
    }
}
$total_cart_items = (int)($cart_count ?? 0);
?>

<style>
    /* PERAPIAN SIDEBAR & LAYOUT CLEAN MODERN */
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

    .mkt-toolbar {
        background: #FFFFFF;
        border-radius: 16px;
        border: 1px solid #E2E8F0;
        padding: 16px 20px;
        margin-bottom: 22px;
        box-shadow: 0 2px 10px rgba(15, 23, 42, 0.03);
    }
    .mkt-search-wrap {
        position: relative;
        flex: 1;
        min-width: 230px;
    }
    .mkt-search-input {
        width: 100%;
        box-sizing: border-box;
        padding: 10px 14px 10px 38px;
        border: 1px solid #CBD5E1;
        border-radius: 11px;
        font-size: 13px;
        color: #0F172A;
        background: #F8FAFC;
        outline: none;
        transition: border-color 0.2s, background 0.2s, box-shadow 0.2s;
    }
    .mkt-search-input:focus {
        background: #FFFFFF;
        border-color: #059669;
        box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.12);
    }
    .mkt-sort-select {
        padding: 10px 13px;
        border: 1px solid #CBD5E1;
        border-radius: 11px;
        font-size: 13px;
        font-weight: 600;
        color: #334155;
        background: #FFFFFF;
        cursor: pointer;
        outline: none;
    }
    .mkt-sort-select:focus {
        border-color: #059669;
    }
    .mkt-pill {
        padding: 7px 14px;
        border-radius: 99px;
        font-size: 12px;
        font-weight: 700;
        border: 1px solid #E2E8F0;
        background: #F8FAFC;
        color: #475569;
        cursor: pointer;
        transition: all 0.18s ease;
        white-space: nowrap;
    }
    .mkt-pill:hover {
        border-color: #10B981;
        color: #065F46;
    }
    .mkt-pill.active {
        background: #064E3B;
        color: #FFFFFF;
        border-color: #064E3B;
        box-shadow: 0 3px 10px rgba(6, 78, 59, 0.2);
    }

    /* GRID KATALOG RESPONSIF */
    .mkt-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(265px, 1fr));
        gap: 20px;
    }
    .mkt-card {
        background: #FFFFFF;
        border-radius: 18px;
        border: 1px solid #E2E8F0;
        overflow: hidden;
        box-shadow: 0 2px 10px rgba(15, 23, 42, 0.03);
        display: flex;
        flex-direction: column;
        transition: transform 0.22s ease, box-shadow 0.22s ease, border-color 0.22s ease;
    }
    .mkt-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 26px rgba(15, 23, 42, 0.08);
        border-color: #CBD5E1;
    }
    .mkt-thumb-wrap {
        position: relative;
        width: 100%;
        height: 192px;
        background: #F1F5F9;
        overflow: hidden;
    }
    .mkt-thumb {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
        transition: transform 0.35s ease;
    }
    .mkt-card:hover .mkt-thumb {
        transform: scale(1.04);
    }
    .mkt-desc {
        margin: 0 0 14px;
        font-size: 12.5px;
        color: #64748B;
        line-height: 1.5;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        min-height: 37px;
    }

    /* STEPPER QTY RAMAH SENTUH */
    .mkt-stepper {
        display: inline-flex;
        align-items: center;
        border: 1px solid #CBD5E1;
        border-radius: 10px;
        overflow: hidden;
        background: #F8FAFC;
        flex-shrink: 0;
    }
    .mkt-step-btn {
        width: 32px;
        height: 38px;
        border: none;
        background: transparent;
        color: #0F172A;
        font-size: 16px;
        font-weight: 800;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background 0.15s;
    }
    .mkt-step-btn:hover {
        background: #E2E8F0;
    }
    .mkt-qty-input {
        width: 42px;
        height: 38px;
        border: none;
        border-left: 1px solid #E2E8F0;
        border-right: 1px solid #E2E8F0;
        background: #FFFFFF;
        text-align: center;
        font-weight: 800;
        font-size: 13px;
        color: #0F172A;
        outline: none;
        -moz-appearance: textfield;
    }
    .mkt-qty-input::-webkit-outer-spin-button,
    .mkt-qty-input::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }
    .mkt-add-btn {
        flex: 1;
        height: 38px;
        background: #059669;
        color: #FFFFFF;
        border: none;
        border-radius: 10px;
        font-weight: 700;
        font-size: 13px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        white-space: nowrap;
        transition: background 0.2s, transform 0.15s;
    }
    .mkt-add-btn:hover {
        background: #047857;
    }
    .mkt-add-btn:active {
        transform: scale(0.98);
    }

    .mobile-nav-trigger {
        display: none;
    }

    /* MENU DRAWER UNTUK HP (< 960px) */
    @media (max-width: 960px) {
        #mainSidebar {
            position: fixed !important;
            left: -280px;
            top: 0;
            bottom: 0;
            transition: left 0.28s cubic-bezier(0.4, 0, 0.2, 1);
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
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            margin-bottom: 14px;
            box-shadow: 0 4px 12px rgba(6, 78, 59, 0.2);
        }
    }

    /* OPTIMASI LAYAR PONSEL (< 640px) */
    @media (max-width: 640px) {
        .mkt-hero {
            padding: 22px 20px !important;
        }
        .mkt-hero-title {
            font-size: 19px !important;
        }
        .mkt-cart-btn {
            width: 100%;
            justify-content: center !important;
        }
        .mkt-grid {
            grid-template-columns: minmax(0, 1fr);
            gap: 16px;
        }
        .mkt-thumb-wrap {
            height: 180px;
        }
        .mkt-sort-select {
            width: 100%;
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
<div id="kopBackdrop" onclick="toggleKopSidebar()" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.45); z-index:45; backdrop-filter:blur(2px);"></div>

<!-- BANNER HEADER STORE CLEAN & PROFESIONAL -->
<div class="mkt-hero" style="background:linear-gradient(125deg, #064E3B 0%, #059669 100%); border-radius:20px; padding:26px 30px; color:#FFFFFF; margin-bottom:20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:18px; box-shadow:0 10px 25px rgba(5, 150, 105, 0.16); position:relative; overflow:hidden;">
    <div style="position:absolute; right:-30px; bottom:-50px; width:210px; height:210px; border-radius:50%; background:rgba(255,255,255,0.08); pointer-events:none;"></div>
    <div style="max-width:600px; position:relative; z-index:2;">
        <div style="display:inline-flex; align-items:center; gap:7px; background:rgba(255,255,255,0.16); color:#ECFDF5; font-size:11px; font-weight:800; padding:5px 12px; border-radius:99px; margin-bottom:10px; letter-spacing:0.3px;">
            <span style="width:7px; height:7px; border-radius:50%; background:#34D399; display:inline-block;"></span>
            <span>PASOKAN LANGSUNG DARI KOPERASI PRIMER</span>
        </div>
        <h2 class="mkt-hero-title" style="margin:0 0 6px; font-size:23px; font-weight:800; line-height:1.3;">
            KOPNOVA Agribusiness Store
        </h2>
        <p style="margin:0; font-size:13.5px; color:#ECFDF5; line-height:1.55; opacity:0.95;">
            Belanja komoditas panen segar dan produk olahan koperasi secara transparan langsung dari sentra produksi daerah.
        </p>
    </div>

    <a href="keranjang.php" class="mkt-cart-btn" style="display:inline-flex; align-items:center; gap:10px; background:#FFFFFF; color:#064E3B; text-decoration:none; padding:12px 18px; border-radius:13px; font-weight:800; font-size:13.5px; box-shadow:0 6px 18px rgba(0,0,0,0.14); position:relative; z-index:2; transition:transform 0.2s;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
        <span>Keranjang Belanja</span>
        <span style="background:#059669; color:#FFFFFF; font-size:11.5px; font-weight:800; padding:3px 9px; border-radius:99px; font-variant-numeric:tabular-nums;">
            <?= $total_cart_items ?> Item
        </span>
    </a>
</div>

<?php if (isset($_GET['msg'])): ?>
<div style="background:#ECFDF5; border:1px solid #A7F3D0; border-left:5px solid #059669; color:#065F46; padding:13px 18px; border-radius:12px; margin-bottom:20px; font-weight:700; font-size:13.5px; display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
    <div style="display:flex; align-items:center; gap:10px;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
        <span><?= htmlspecialchars($_GET['msg']) ?></span>
    </div>
    <a href="keranjang.php" style="color:#064E3B; font-size:12.5px; font-weight:800; text-decoration:underline;">Buka Keranjang →</a>
</div>
<?php endif; ?>

<?php if (empty($produk_list)): ?>
    <div style="background:#FFFFFF; padding:48px 24px; border-radius:18px; text-align:center; border:1px solid #E2E8F0; box-shadow:0 2px 10px rgba(15,23,42,0.03);">
        <div style="width:52px; height:52px; border-radius:14px; background:#F1F5F9; color:#64748B; display:flex; align-items:center; justify-content:center; margin:0 auto 14px;">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
        </div>
        <h3 style="margin:0 0 6px; font-size:18px; color:#0F172A;">Etalase Produk Masih Kosong</h3>
        <p style="color:#64748B; margin:0 auto; max-width:440px; font-size:13.5px; line-height:1.55;">
            Silakan login terlebih dahulu sebagai akun <strong>Koperasi Primer</strong> untuk mengunggah foto dan produk jualan ke etalase.
        </p>
    </div>
<?php else: ?>

<!-- BILAH PENCARIAN, FILTER KATEGORI & URUTKAN -->
<div class="mkt-toolbar">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:14px;">
        <div class="mkt-search-wrap">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#64748B" stroke-width="2.2" style="position:absolute; left:13px; top:50%; transform:translateY(-50%); pointer-events:none;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" id="mktSearchInput" onkeyup="filterMarketplace()" placeholder="Cari nama produk, koperasi asal, atau kecamatan..." class="mkt-search-input">
        </div>

        <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
            <span id="mktCountLabel" style="font-size:12px; font-weight:700; color:#64748B; white-space:nowrap;">
                Menampilkan <?= count($produk_list) ?> produk
            </span>
            <select id="mktSortSelect" onchange="sortMarketplace()" class="mkt-sort-select" aria-label="Urutkan produk">
                <option value="newest">Urutkan: Terbaru</option>
                <option value="price_asc">Harga: Terendah</option>
                <option value="price_desc">Harga: Tertinggi</option>
                <option value="stock_desc">Stok: Terbanyak</option>
            </select>
        </div>
    </div>

    <!-- FILTER PILL KATEGORI -->
    <div style="display:flex; gap:7px; flex-wrap:wrap; padding-top:12px; border-top:1px solid #F1F5F9;" id="mktCategoryBar">
        <button type="button" class="mkt-pill active" onclick="setMktCategory('ALL', this)">Semua Produk (<?= count($produk_list) ?>)</button>
        <?php foreach ($kategori_unik as $kat): ?>
            <button type="button" class="mkt-pill" onclick="setMktCategory('<?= htmlspecialchars($kat, ENT_QUOTES) ?>', this)">
                <?= htmlspecialchars($kat) ?>
            </button>
        <?php endforeach; ?>
    </div>
</div>

<!-- GRID KATALOG ONLINE SHOP -->
<div class="mkt-grid" id="mktProductGrid">
    <?php foreach ($produk_list as $idx => $pr): 
        $stok_val = (int)$pr['stok'];
        $harga_val = (float)$pr['harga_jual'];
    ?>
    <div class="mkt-card mkt-product-item"
         data-order="<?= $idx ?>"
         data-category="<?= htmlspecialchars($pr['kategori'], ENT_QUOTES) ?>"
         data-price="<?= $harga_val ?>"
         data-stock="<?= $stok_val ?>">

        <div class="mkt-thumb-wrap">
            <img src="<?= BASE_URL ?>/uploads/produk/<?= htmlspecialchars($pr['foto_produk']) ?>"
                 alt="<?= htmlspecialchars($pr['nama_produk']) ?>"
                 onerror="this.src='https://placehold.co/480x300/064e3b/ffffff?text=Produk+KOPNOVA'"
                 class="mkt-thumb">

            <span style="position:absolute; top:12px; left:12px; background:rgba(6, 78, 59, 0.92); color:#ECFDF5; font-size:11px; font-weight:700; padding:4px 11px; border-radius:99px; backdrop-filter:blur(4px);">
                <?= htmlspecialchars($pr['kategori']) ?>
            </span>

            <?php if ($stok_val < 20): ?>
            <span style="position:absolute; top:12px; right:12px; background:#FEF2F2; color:#B91C1C; border:1px solid #FECACA; font-size:10.5px; font-weight:800; padding:3px 9px; border-radius:99px;">
                Stok Terbatas
            </span>
            <?php endif; ?>
        </div>

        <div style="padding:18px; flex:1; display:flex; flex-direction:column; justify-content:space-between;">
            <div>
                <div style="display:flex; align-items:center; gap:5px; font-size:11.5px; color:#B45309; font-weight:700;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" style="flex-shrink:0;"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    <span><?= htmlspecialchars($pr['nama_divisi']) ?> • Kec. <?= htmlspecialchars($pr['kecamatan']) ?></span>
                </div>

                <h3 style="margin:6px 0 3px; font-size:16.5px; font-weight:800; color:#0F172A; line-height:1.35;">
                    <?= htmlspecialchars($pr['nama_produk']) ?>
                </h3>

                <div style="font-size:11.5px; color:#64748B; font-weight:600; margin-bottom:8px;">
                    Unit: <?= htmlspecialchars($pr['nama_koperasi']) ?>
                </div>

                <p class="mkt-desc"><?= htmlspecialchars($pr['deskripsi'] ?: 'Komoditas segar berkualitas standar distribusi KOPNOVA.') ?></p>
            </div>

            <div style="padding-top:12px; border-top:1px solid #F1F5F9;">
                <div style="display:flex; justify-content:space-between; align-items:baseline; gap:8px; margin-bottom:12px;">
                    <div style="font-size:18.5px; font-weight:900; color:#064E3B; font-variant-numeric:tabular-nums;">
                        <?= rupiah($pr['harga_jual']) ?><span style="font-size:12px; font-weight:600; color:#64748B;">/<?= htmlspecialchars($pr['satuan']) ?></span>
                    </div>
                    <div style="font-size:11.5px; font-weight:700; color:<?= $stok_val < 20 ? '#DC2626' : '#475569' ?>; background:<?= $stok_val < 20 ? '#FEF2F2' : '#F8FAFC' ?>; padding:3px 9px; border-radius:8px; border:1px solid <?= $stok_val < 20 ? '#FECACA' : '#E2E8F0' ?>;">
                        Stok: <?= number_format($stok_val, 0, ',', '.') ?>
                    </div>
                </div>

                <form action="proses_cart.php" method="POST" style="display:flex; align-items:center; gap:8px; margin:0;">
                    <?php if (function_exists('csrf_field')) echo csrf_field(); ?>
                    <input type="hidden" name="aksi" value="add">
                    <input type="hidden" name="id_produk" value="<?= (int)$pr['id_produk'] ?>">

                    <div class="mkt-stepper">
                        <button type="button" class="mkt-step-btn" onclick="stepMktQty(this, -1)" aria-label="Kurangi jumlah">−</button>
                        <input type="number" name="qty" value="1" min="1" max="<?= $stok_val ?>" class="mkt-qty-input" onchange="clampMktQty(this)">
                        <button type="button" class="mkt-step-btn" onclick="stepMktQty(this, 1)" aria-label="Tambah jumlah">+</button>
                    </div>

                    <button type="submit" class="mkt-add-btn">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                        <span>+ Keranjang</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- PESAN KOSONG SAAT FILTER / PENCARIAN TIDAK COCOK -->
<div id="mktEmptyFilter" style="display:none; background:#FFFFFF; padding:40px 24px; border-radius:18px; text-align:center; border:1px solid #E2E8F0; margin-top:10px;">
    <h3 style="margin:0 0 6px; font-size:17px; color:#0F172A;">Produk Tidak Ditemukan</h3>
    <p style="margin:0 0 14px; font-size:13.5px; color:#64748B;">Coba ubah kata kunci pencarian atau pilih kategori lain.</p>
    <button type="button" onclick="resetMktFilters()" style="background:#064E3B; color:#FFFFFF; border:none; padding:9px 18px; border-radius:10px; font-size:13px; font-weight:700; cursor:pointer;">
        Tampilkan Semua Produk
    </button>
</div>

<?php endif; ?>

<script>
let activeMktCategory = 'ALL';

function setMktCategory(cat, btn) {
    activeMktCategory = cat;
    document.querySelectorAll('#mktCategoryBar .mkt-pill').forEach(el => el.classList.remove('active'));
    btn.classList.add('active');
    filterMarketplace();
}

function filterMarketplace() {
    const input = document.getElementById('mktSearchInput');
    const q = input ? input.value.toLowerCase().trim() : '';
    const cards = document.querySelectorAll('.mkt-product-item');
    let visibleCount = 0;

    cards.forEach(card => {
        const cat = card.getAttribute('data-category');
        const txt = card.innerText.toLowerCase();
        const matchCat  = (activeMktCategory === 'ALL' || cat === activeMktCategory);
        const matchText = txt.includes(q);

        if (matchCat && matchText) {
            card.style.display = 'flex';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });

    const countLabel = document.getElementById('mktCountLabel');
    if (countLabel) {
        countLabel.textContent = 'Menampilkan ' + visibleCount + ' produk';
    }

    const emptyBox = document.getElementById('mktEmptyFilter');
    if (emptyBox) {
        emptyBox.style.display = visibleCount === 0 ? 'block' : 'none';
    }
}

function sortMarketplace() {
    const select = document.getElementById('mktSortSelect');
    const grid   = document.getElementById('mktProductGrid');
    if (!select || !grid) return;

    const mode  = select.value;
    const items = Array.from(grid.querySelectorAll('.mkt-product-item'));

    items.sort((a, b) => {
        if (mode === 'price_asc') {
            return parseFloat(a.getAttribute('data-price')) - parseFloat(b.getAttribute('data-price'));
        } else if (mode === 'price_desc') {
            return parseFloat(b.getAttribute('data-price')) - parseFloat(a.getAttribute('data-price'));
        } else if (mode === 'stock_desc') {
            return parseInt(b.getAttribute('data-stock'), 10) - parseInt(a.getAttribute('data-stock'), 10);
        } else {
            return parseInt(a.getAttribute('data-order'), 10) - parseInt(b.getAttribute('data-order'), 10);
        }
    });

    items.forEach(item => grid.appendChild(item));
}

function resetMktFilters() {
    const input = document.getElementById('mktSearchInput');
    if (input) input.value = '';
    const firstPill = document.querySelector('#mktCategoryBar .mkt-pill');
    if (firstPill) setMktCategory('ALL', firstPill);
}

// Kontrol Stepper Jumlah Pesanan
function stepMktQty(btn, delta) {
    const input = btn.parentElement.querySelector('.mkt-qty-input');
    if (!input) return;
    const min = parseInt(input.min, 10) || 1;
    const max = parseInt(input.max, 10) || 9999;
    let val   = parseInt(input.value, 10) || min;
    val = Math.min(max, Math.max(min, val + delta));
    input.value = val;
}

function clampMktQty(input) {
    const min = parseInt(input.min, 10) || 1;
    const max = parseInt(input.max, 10) || 9999;
    let val   = parseInt(input.value, 10) || min;
    input.value = Math.min(max, Math.max(min, val));
}

// Buka/Tutup Menu Sidebar di Layar HP
function toggleKopSidebar() {
    const sb = document.getElementById('mainSidebar');
    const bd = document.getElementById('kopBackdrop');
    if (!sb) return;
    const isOpen = sb.classList.toggle('open');
    if (bd) bd.style.display = isOpen ? 'block' : 'none';
}
</script>

<?php require_once '../../includes/footer.php'; ?>