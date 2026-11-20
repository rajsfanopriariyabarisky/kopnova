<?php
require_once 'config/database.php';

// Ambil data statistik dinamis untuk ditampilkan di landing page
$total_divisi = $pdo->query("SELECT COUNT(*) FROM divisi_wilayah")->fetchColumn();
$total_primer = $pdo->query("SELECT COUNT(*) FROM koperasi_primer")->fetchColumn();
$total_produk = $pdo->query("SELECT COUNT(*) FROM produk_etalase")->fetchColumn();

// Ambil 3 produk terbaru untuk preview etalase di bagian bawah
$preview_produk = $pdo->query("SELECT pr.*, kp.nama_koperasi, d.nama_divisi 
                               FROM produk_etalase pr 
                               JOIN koperasi_primer kp ON pr.id_primer = kp.id_primer 
                               JOIN divisi_wilayah d ON pr.id_divisi = d.id_divisi 
                               ORDER BY pr.id_produk DESC LIMIT 3")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id" style="scroll-behavior:smooth;">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KOPNOVA | Ekosistem Digital Rantai Pasok & Agribisnis Terpadu</title>
</head>
<body style="margin:0; padding:0; font-family:'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color:#F4F6F0; color:#0F172A; line-height:1.6; overflow-x:hidden;">

    <!-- WRAPPER UTAMA -->
    <div style="max-width:1360px; margin:0 auto; background:#FAFBF7; box-shadow:0 0 40px rgba(0,0,0,0.06); overflow-x:hidden;">

        <!-- 1. TOP NAVBAR RESPONSIVE (1 BARIS RAMPING DI HP & DESKTOP) -->
        <header id="topNavbarWrap" style="padding:14px 4% 0; position:sticky; top:0; z-index:100;">
            <nav id="mainNav" style="background:rgba(255,255,255,0.96); backdrop-filter:blur(10px); padding:10px 18px; border-radius:99px; display:flex; justify-content:space-between; align-items:center; box-shadow:0 8px 24px rgba(6,78,59,0.09); border:1px solid #E2E8F0; position:relative;">
                
                <!-- LOGO KOPNOVA -->
                <a href="index.php" style="display:flex; align-items:center; gap:10px; text-decoration:none; flex-shrink:0;">
                    <div style="width:36px; height:36px; background:linear-gradient(135deg, #1B5E20, #2E7D32); border-radius:50%; display:flex; align-items:center; justify-content:center; color:#FFFFFF; font-weight:900; font-size:17px;">K</div>
                    <span style="color:#1B5E20; font-weight:900; font-size:19px; letter-spacing:-0.3px;">KOPNOVA</span>
                </a>

                <!-- MENU DESKTOP (OTOMATIS DISEMBUNYIKAN DI LAYAR HP) -->
                <div id="desktopMenu" style="display:flex; gap:24px; align-items:center;">
                    <a href="#beranda" style="color:#0F172A; text-decoration:none; font-size:14px; font-weight:700;">Beranda</a>
                    <a href="#tentang" style="color:#475569; text-decoration:none; font-size:14px; font-weight:600;">Ekosistem</a>
                    <a href="#alur-role" style="color:#475569; text-decoration:none; font-size:14px; font-weight:600;">4 Aktor RBAC</a>
                    <a href="#komoditas" style="color:#475569; text-decoration:none; font-size:14px; font-weight:600;">Komoditas</a>
                </div>

                <!-- TOMBOL AKSI KANAN + HAMBURGER MOBILE -->
                <div style="display:flex; align-items:center; gap:8px; flex-shrink:0;">
                    <a id="desktopShopLink" href="login.php?role=buyer" style="color:#1B5E20; text-decoration:none; font-size:13px; font-weight:700; padding:8px 12px;">Belanja</a>
                    <a href="login.php" style="background:#2E7D32; color:#FFFFFF; padding:9px 18px; border-radius:99px; text-decoration:none; font-weight:700; font-size:13px; box-shadow:0 4px 12px rgba(46,125,50,0.28); white-space:nowrap;">
                        Masuk →
                    </a>
                    <!-- TOMBOL HAMBURGER KHUSUS HP -->
                    <button type="button" id="mobileMenuBtn" onclick="toggleMobileMenu()" aria-label="Menu" style="display:none; width:38px; height:38px; border-radius:50%; border:1px solid #CBD5E1; background:#F8FAFC; color:#0F172A; align-items:center; justify-content:center; cursor:pointer; padding:0;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round">
                            <line x1="3" y1="6" x2="21" y2="6"></line>
                            <line x1="3" y1="12" x2="21" y2="12"></line>
                            <line x1="3" y1="18" x2="21" y2="18"></line>
                        </svg>
                    </button>
                </div>
            </nav>

            <!-- DROPDOWN MENU KHUSUS HP (MUNCUL SAAT HAMBURGER DIKLIK) -->
            <div id="mobileDropdown" style="display:none; background:#FFFFFF; margin-top:10px; padding:16px 20px; border-radius:20px; box-shadow:0 16px 36px rgba(0,0,0,0.14); border:1px solid #E2E8F0; flex-direction:column; gap:12px;">
                <a href="#beranda" onclick="closeMobileMenu()" style="color:#0F172A; text-decoration:none; font-size:14px; font-weight:700; padding:6px 0; border-bottom:1px solid #F1F5F9;">Beranda</a>
                <a href="#tentang" onclick="closeMobileMenu()" style="color:#334155; text-decoration:none; font-size:14px; font-weight:600; padding:6px 0; border-bottom:1px solid #F1F5F9;">Tentang Ekosistem</a>
                <a href="#alur-role" onclick="closeMobileMenu()" style="color:#334155; text-decoration:none; font-size:14px; font-weight:600; padding:6px 0; border-bottom:1px solid #F1F5F9;">4 Aktor RBAC</a>
                <a href="#komoditas" onclick="closeMobileMenu()" style="color:#334155; text-decoration:none; font-size:14px; font-weight:600; padding:6px 0; border-bottom:1px solid #F1F5F9;">Etalase Komoditas</a>
                <a href="login.php?role=buyer" style="background:#F0FDF4; color:#1B5E20; text-align:center; text-decoration:none; font-size:13.5px; font-weight:800; padding:10px; border-radius:12px; border:1px solid #BBF7D0;">
                    Masuk Sebagai Buyer (Belanja)
                </a>
            </div>
        </header>

        <!-- 2. HERO SECTION DENGAN BACKGROUND ILUSTRASI KOPERASI -->
        <section id="beranda" style="margin:14px 3% 0; border-radius:26px; overflow:hidden; position:relative; min-height:510px; display:flex; align-items:center; background-image:linear-gradient(180deg, rgba(6,60,42,0.88) 0%, rgba(6,78,59,0.78) 10%, rgba(6,55,38,0.65) 50%), url('assets/img/hero-bg.svg'); background-size:cover; background-position:center right;">
            <div style="padding:52px 4% 76px; max-width:600px; color:#FFFFFF; position:relative; z-index:2;">
                <span style="display:inline-flex; align-items:center; gap:6px; background:rgba(255,255,255,0.16); backdrop-filter:blur(6px); border:1px solid rgba(255,255,255,0.28); color:#ECFDF5; padding:6px 14px; border-radius:99px; font-size:11px; font-weight:700; letter-spacing:0.5px; margin-bottom:16px;">
                    ● DIGITALISASI RANTAI PASOK PERTANIAN
                </span>
                <h1 style="font-size:clamp(26px, 4.2vw, 48px); font-weight:800; line-height:1.15; margin:0 0 16px; letter-spacing:-0.4px;">
                    Menghubungkan Hasil Panen Petani Langsung ke Pasar Modern.
                </h1>
                <p style="font-size:15px; color:#E2E8F0; margin:0 0 26px; line-height:1.6;">
                    Platform terpadu multi-wilayah yang menyatukan Petani Pemasok, Koperasi Primer kecamatan, Koperasi Konsumen pusat, dan Buyer dalam satu alur transaksi transparan.
                </p>
                <div style="display:flex; flex-wrap:wrap; gap:12px;">
                    <a href="login.php" style="background:#2E7D32; border:2px solid #4ADE80; color:#FFFFFF; padding:12px 24px; border-radius:99px; text-decoration:none; font-weight:700; font-size:14px; box-shadow:0 8px 20px rgba(0,0,0,0.2);">
                        Mulai Sekarang
                    </a>
                    <a href="#alur-role" style="background:rgba(255,255,255,0.14); backdrop-filter:blur(6px); border:1px solid rgba(255,255,255,0.4); color:#FFFFFF; padding:12px 22px; border-radius:99px; text-decoration:none; font-weight:600; font-size:14px;">
                        Pelajari Alur Sistem
                    </a>
                </div>
            </div>
        </section>

        <!-- 3. FLOATING ACTION / QUICK ACCESS BAR (RESPONSIF DI HP & DESKTOP) -->
        <div style="max-width:960px; margin:-36px auto 0; padding:0 5%; position:relative; z-index:20;">
            <div id="quickBarOuter" style="background:#8FA97E; padding:10px; border-radius:26px; box-shadow:0 14px 30px rgba(27,94,32,0.16);">
                <form action="login.php" method="GET" id="quickBarInner" style="background:#FFFFFF; border-radius:20px; padding:14px 18px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:14px;">
                    <div style="flex:1 1 200px; min-width:0;">
                        <div style="font-size:10.5px; font-weight:800; color:#64748B; text-transform:uppercase; letter-spacing:0.4px;">Pilih Peran Akses</div>
                        <select name="role" style="width:100%; border:none; font-size:14px; font-weight:700; color:#0F172A; background:transparent; outline:none; cursor:pointer; margin-top:3px; padding:2px 0;">
                            <option value="buyer">Buyer / Pembeli Komoditas</option>
                            <option value="petani">Petani / Pemasok Hulu</option>
                            <option value="primer">Koperasi Primer Kecamatan</option>
                            <option value="konsumen">Koperasi Konsumen (Pusat)</option>
                        </select>
                    </div>
                    <div style="flex:1 1 200px; min-width:0;">
                        <div style="font-size:10.5px; font-weight:800; color:#64748B; text-transform:uppercase; letter-spacing:0.4px;">Cakupan Ekosistem Aktif</div>
                        <div style="font-size:13.5px; font-weight:800; color:#1B5E20; margin-top:3px;">
                            <?= $total_divisi ?> Divisi Kab/Kota • <?= $total_primer ?> Primer
                        </div>
                    </div>
                    <button type="submit" style="flex:1 1 160px; background:#2E7D32; color:#FFFFFF; border:none; padding:13px 22px; border-radius:99px; font-weight:700; font-size:14px; cursor:pointer; text-align:center;">
                        Akses Workspace →
                    </button>
                </form>
            </div>
        </div>

        <!-- 4. SPLIT SECTION: TENTANG KOPNOVA + FLOATING BADGE -->
        <section id="tentang" style="max-width:1120px; margin:68px auto; padding:0 6%; display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:40px; align-items:center;">
            <!-- KIRI: KARTU VISUAL DENGAN FLOATING BADGE -->
            <div style="position:relative; padding:14px;">
                <!-- Badge Atas Kiri -->
                <div style="position:absolute; top:0; left:0; background:#2E7D32; color:#FFFFFF; width:84px; height:84px; border-radius:50%; display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center; box-shadow:0 10px 25px rgba(46,125,50,0.35); z-index:5; border:4px solid #FAFBF7;">
                    <span style="font-size:18px; font-weight:900; line-height:1;">100%</span>
                    <span style="font-size:9.5px; font-weight:600; margin-top:2px;">Terintegrasi</span>
                </div>

                <!-- Gambar Utama -->
                <div style="border-radius:22px; overflow:hidden; box-shadow:0 18px 36px rgba(0,0,0,0.1); border:5px solid #FFFFFF;">
                    <img src="assets/img/hero-bg.svg" alt="Ekosistem Koperasi KOPNOVA" style="width:100%; height:270px; object-fit:cover; display:block;">
                </div>

                <!-- Badge Bawah Kanan -->
                <div style="position:absolute; bottom:2px; right:2px; background:#FFFFFF; padding:10px 16px; border-radius:99px; box-shadow:0 10px 25px rgba(0,0,0,0.12); display:flex; align-items:center; gap:8px; border:1px solid #E2E8F0;">
                    <span style="width:10px; height:10px; background:#F59E0B; border-radius:50%; display:inline-block;"></span>
                    <span style="font-size:12px; font-weight:800; color:#0F172A;">Hulu ke Hilir Real-Time</span>
                </div>
            </div>

            <!-- KANAN: NARASI & COPYWRITING -->
            <div>
                <span style="color:#2E7D32; font-weight:800; font-size:12px; text-transform:uppercase; letter-spacing:1px;">Mengapa KOPNOVA?</span>
                <h2 style="font-size:clamp(24px, 3vw, 32px); font-weight:800; color:#0F172A; margin:8px 0 14px; line-height:1.2;">
                    Tata Kelola Koperasi Modern Tanpa Sekat Wilayah.
                </h2>
                <p style="font-size:14.5px; color:#475569; margin:0 0 18px; line-height:1.65;">
                    <strong>KOPNOVA</strong> dirancang fleksibel untuk berbagai divisi kabupaten dan kecamatan. Setiap hasil panen dari petani tercatat secara digital, diverifikasi oleh Koperasi Primer, dan langsung dapat dibeli oleh Buyer melalui etalase visual.
                </p>
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:12px; margin-bottom:24px;">
                    <div style="background:#FFFFFF; padding:14px; border-radius:12px; border:1px solid #E2E8F0;">
                        <div style="font-weight:800; color:#1B5E20; font-size:14px;">✓ Multi-Kabupaten</div>
                        <div style="font-size:12px; color:#64748B;">Tambah divisi wilayah & kecamatan secara mandiri.</div>
                    </div>
                    <div style="background:#FFFFFF; padding:14px; border-radius:12px; border:1px solid #E2E8F0;">
                        <div style="font-weight:800; color:#1B5E20; font-size:14px;">✓ RBAC 4 Peran</div>
                        <div style="font-size:12px; color:#64748B;">Hak akses terpisah & aman untuk tiap aktor.</div>
                    </div>
                </div>
                <div style="display:flex; align-items:center; flex-wrap:wrap; gap:16px;">
                    <a href="login.php" style="background:#2E7D32; color:#FFFFFF; padding:12px 24px; border-radius:99px; text-decoration:none; font-weight:700; font-size:14px;">
                        Masuk ke Sistem
                    </a>
                    <a href="#komoditas" style="color:#1B5E20; font-weight:700; font-size:14px; text-decoration:none;">
                        Lihat Etalase Produk →
                    </a>
                </div>
            </div>
        </section>

        <!-- 5. SECTION 4 AKTOR EKOSISTEM (ALUR ATAS KE BAWAH) -->
        <section id="alur-role" style="background:#E6EFE0; padding:60px 5%; border-radius:28px; margin:0 3%;">
            <div style="max-width:1120px; margin:0 auto;">
                <div style="text-align:center; max-width:640px; margin:0 auto 36px;">
                    <span style="color:#2E7D32; font-weight:800; font-size:12px; text-transform:uppercase; letter-spacing:1px;">Arsitektur RBAC Terstruktur</span>
                    <h2 style="font-size:clamp(22px, 3vw, 30px); font-weight:800; color:#0F172A; margin:8px 0 10px;">4 Peran Utama dalam Rantai Pasok KOPNOVA</h2>
                    <p style="font-size:14px; color:#475569; margin:0;">Setiap pengguna mendapatkan ruang kerja khusus sesuai tanggung jawabnya dari proses panen hingga transaksi selesai.</p>
                </div>

                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(230px, 1fr)); gap:18px;">
                    <!-- Card 1 -->
                    <div style="background:#FFFFFF; border-radius:20px; padding:22px; box-shadow:0 10px 25px rgba(0,0,0,0.04); display:flex; flex-direction:column; justify-content:space-between;">
                        <div>
                            <span style="display:inline-block; background:#DCFCE7; color:#166534; font-weight:800; font-size:11px; padding:4px 12px; border-radius:99px; margin-bottom:12px;">01 • HULU PRODUKSI</span>
                            <h3 style="margin:0 0 8px; font-size:17px; color:#0F172A;">Petani / Pemasok</h3>
                            <p style="font-size:13px; color:#64748B; margin:0 0 18px;">Mengajukan setoran hasil panen lengkap dengan foto kualitas komoditas dan harga penawaran ke koperasi kecamatan.</p>
                        </div>
                        <a href="login.php?role=petani" style="display:block; text-align:center; background:#2E7D32; color:#FFFFFF; text-decoration:none; padding:10px 18px; border-radius:99px; font-size:13px; font-weight:700;">Portal Petani</a>
                    </div>

                    <!-- Card 2 -->
                    <div style="background:#FFFFFF; border-radius:20px; padding:22px; box-shadow:0 10px 25px rgba(0,0,0,0.04); display:flex; flex-direction:column; justify-content:space-between;">
                        <div>
                            <span style="display:inline-block; background:#FEF3C7; color:#92400E; font-weight:800; font-size:11px; padding:4px 12px; border-radius:99px; margin-bottom:12px;">02 • KOPERASI KECAMATAN</span>
                            <h3 style="margin:0 0 8px; font-size:17px; color:#0F172A;">Koperasi Primer</h3>
                            <p style="font-size:13px; color:#64748B; margin:0 0 18px;">Memverifikasi kualitas pasokan petani, mengelola stok kecamatan, dan mempublikasikan foto produk ke etalase penjualan.</p>
                        </div>
                        <a href="login.php?role=primer" style="display:block; text-align:center; background:#2E7D32; color:#FFFFFF; text-decoration:none; padding:10px 18px; border-radius:99px; font-size:13px; font-weight:700;">Portal Primer</a>
                    </div>

                    <!-- Card 3 -->
                    <div style="background:#FFFFFF; border-radius:20px; padding:22px; box-shadow:0 10px 25px rgba(0,0,0,0.04); display:flex; flex-direction:column; justify-content:space-between;">
                        <div>
                            <span style="display:inline-block; background:#DBEAFE; color:#1E40AF; font-weight:800; font-size:11px; padding:4px 12px; border-radius:99px; margin-bottom:12px;">03 • PUSAT KENDALI</span>
                            <h3 style="margin:0 0 8px; font-size:17px; color:#0F172A;">Koperasi Konsumen</h3>
                            <p style="font-size:13px; color:#64748B; margin:0 0 18px;">Mengatur divisi kabupaten dan kecamatan secara universal serta memantau seluruh arus transaksi masuk di Dashboard Utama.</p>
                        </div>
                        <a href="login.php?role=konsumen" style="display:block; text-align:center; background:#2E7D32; color:#FFFFFF; text-decoration:none; padding:10px 18px; border-radius:99px; font-size:13px; font-weight:700;">Portal Konsumen</a>
                    </div>

                    <!-- Card 4 -->
                    <div style="background:#FFFFFF; border-radius:20px; padding:22px; box-shadow:0 10px 25px rgba(0,0,0,0.04); display:flex; flex-direction:column; justify-content:space-between;">
                        <div>
                            <span style="display:inline-block; background:#F3E8FF; color:#6B21A8; font-weight:800; font-size:11px; padding:4px 12px; border-radius:99px; margin-bottom:12px;">04 • HILIR & PASAR</span>
                            <h3 style="margin:0 0 8px; font-size:17px; color:#0F172A;">Buyer / Konsumen</h3>
                            <p style="font-size:13px; color:#64748B; margin:0 0 18px;">Berbelanja komoditas pertanian melalui katalog visual interaktif, keranjang belanja, dan checkout instan.</p>
                        </div>
                        <a href="login.php?role=buyer" style="display:block; text-align:center; background:#2E7D32; color:#FFFFFF; text-decoration:none; padding:10px 18px; border-radius:99px; font-size:13px; font-weight:700;">Mulai Belanja</a>
                    </div>
                </div>
            </div>
        </section>

        <!-- 6. SECTION ETALASE KOMODITAS UNGGULAN -->
        <section id="komoditas" style="max-width:1120px; margin:64px auto; padding:0 6%;">
            <div style="text-align:center; margin-bottom:36px;">
                <h2 style="font-size:clamp(22px, 3vw, 30px); font-weight:800; color:#0F172A; margin:0 0 8px;">Etalase Komoditas & Produk Koperasi</h2>
                <p style="font-size:14px; color:#64748B; margin:0;">Produk segar dan olahan yang siap dipesan langsung melalui akun Buyer KOPNOVA.</p>
            </div>

            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:22px;">
                <?php if (!empty($preview_produk)): ?>
                    <?php foreach ($preview_produk as $item): ?>
                    <div style="background:#FFFFFF; border-radius:20px; padding:16px; box-shadow:0 10px 28px rgba(0,0,0,0.05); border:1px solid #E2E8F0; display:flex; flex-direction:column; justify-content:space-between;">
                        <div>
                            <img src="uploads/produk/<?= htmlspecialchars($item['foto_produk']) ?>" onerror="this.src='assets/img/hero-bg.svg'" style="width:100%; height:180px; object-fit:cover; border-radius:14px; margin-bottom:14px;">
                            <div style="font-size:11px; font-weight:700; color:#2E7D32;"><?= htmlspecialchars($item['nama_divisi']) ?> • <?= htmlspecialchars($item['nama_koperasi']) ?></div>
                            <h3 style="margin:4px 0 8px; font-size:17px; color:#0F172A;"><?= htmlspecialchars($item['nama_produk']) ?></h3>
                            <p style="font-size:13px; color:#64748B; margin:0 0 16px;"><?= htmlspecialchars($item['deskripsi'] ?: 'Komoditas terverifikasi koperasi primer siap distribusi.') ?></p>
                        </div>
                        <div style="display:flex; justify-content:space-between; align-items:center; padding-top:12px; border-top:1px solid #F1F5F9;">
                            <span style="font-weight:800; font-size:15px; color:#1B5E20;"><?= rupiah($item['harga_jual']) ?>/<?= htmlspecialchars($item['satuan']) ?></span>
                            <a href="login.php?role=buyer" style="background:#2E7D32; color:#FFFFFF; text-decoration:none; padding:8px 16px; border-radius:99px; font-size:12px; font-weight:700;">Pesan</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="background:#FFFFFF; border-radius:20px; padding:16px; box-shadow:0 10px 28px rgba(0,0,0,0.05); border:1px solid #E2E8F0;">
                        <img src="assets/img/hero-bg.svg" style="width:100%; height:180px; object-fit:cover; border-radius:14px; margin-bottom:14px;">
                        <h3 style="margin:0 0 8px; font-size:17px; color:#0F172A;">Komoditas Tanaman Segar</h3>
                        <p style="font-size:13px; color:#64748B; margin:0 0 18px;">Hasil panen hortikultura dan pangan langsung dari petani binaan koperasi primer kecamatan.</p>
                        <a href="login.php?role=buyer" style="display:inline-block; background:#2E7D32; color:#FFFFFF; text-decoration:none; padding:9px 20px; border-radius:99px; font-size:13px; font-weight:700;">Lihat Katalog</a>
                    </div>

                    <div style="background:#FFFFFF; border-radius:20px; padding:16px; box-shadow:0 10px 28px rgba(0,0,0,0.05); border:1px solid #E2E8F0;">
                        <img src="assets/img/hero-bg.svg" style="width:100%; height:180px; object-fit:cover; border-radius:14px; margin-bottom:14px;">
                        <h3 style="margin:0 0 8px; font-size:17px; color:#0F172A;">Saprodi & Alsintan Terpadu</h3>
                        <p style="font-size:13px; color:#64748B; margin:0 0 18px;">Penyediaan sarana produksi pertanian, pupuk organik, dan alat mesin pertanian berstandar.</p>
                        <a href="login.php?role=buyer" style="display:inline-block; background:#2E7D32; color:#FFFFFF; text-decoration:none; padding:9px 20px; border-radius:99px; font-size:13px; font-weight:700;">Lihat Katalog</a>
                    </div>

                    <div style="background:#FFFFFF; border-radius:20px; padding:16px; box-shadow:0 10px 28px rgba(0,0,0,0.05); border:1px solid #E2E8F0;">
                        <img src="assets/img/hero-bg.svg" style="width:100%; height:180px; object-fit:cover; border-radius:14px; margin-bottom:14px;">
                        <h3 style="margin:0 0 8px; font-size:17px; color:#0F172A;">Produk Olahan Hilirisasi</h3>
                        <p style="font-size:13px; color:#64748B; margin:0 0 18px;">Produk bernilai tambah tinggi hasil pengolahan UMKM dan koperasi primer siap pasar ritel.</p>
                        <a href="login.php?role=buyer" style="display:inline-block; background:#2E7D32; color:#FFFFFF; text-decoration:none; padding:9px 20px; border-radius:99px; font-size:13px; font-weight:700;">Lihat Katalog</a>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- 7. FOOTER PROFESIONAL -->
        <footer style="background:#064E3B; color:#D1FAE5; padding:40px 6% 26px; border-radius:26px 26px 0 0;">
            <div style="max-width:1120px; margin:0 auto; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:18px; padding-bottom:22px; border-bottom:1px solid rgba(255,255,255,0.12);">
                <div>
                    <div style="font-size:21px; font-weight:900; color:#FFFFFF; letter-spacing:0.5px;">KOPNOVA</div>
                    <div style="font-size:13px; color:#A7F3D0; margin-top:4px;">Ekosistem Digital Rantai Pasok Koperasi & Agribisnis Multi-Wilayah</div>
                </div>
                <div>
                    <a href="login.php" style="display:inline-block; background:#10B981; color:#064E3B; padding:10px 22px; border-radius:99px; text-decoration:none; font-weight:800; font-size:13px;">Masuk Dashboard</a>
                </div>
            </div>
            <div style="max-width:1120px; margin:16px auto 0; display:flex; justify-content:space-between; flex-wrap:wrap; gap:8px; font-size:12px; color:#6EE7B7;">
                <span>© <?= date('Y') ?> KOPNOVA Supply Chain System. Seluruh hak cipta dilindungi.</span>
                <span>Integrasi • Kolaborasi • Digitalisasi</span>
            </div>
        </footer>

    </div>

    <!-- SCRIPT RESPONSIVE NAVBAR & MOBILE DRAWER -->
    <script>
        function applyResponsiveLayout() {
            const isMobile = window.innerWidth < 820;
            const desktopMenu = document.getElementById('desktopMenu');
            const desktopShop = document.getElementById('desktopShopLink');
            const mobileBtn   = document.getElementById('mobileMenuBtn');
            const mobileDrop  = document.getElementById('mobileDropdown');
            const quickOuter  = document.getElementById('quickBarOuter');
            const quickInner  = document.getElementById('quickBarInner');

            if (isMobile) {
                desktopMenu.style.display = 'none';
                desktopShop.style.display = 'none';
                mobileBtn.style.display   = 'flex';
                quickOuter.style.borderRadius = '24px';
                quickInner.style.borderRadius = '18px';
            } else {
                desktopMenu.style.display = 'flex';
                desktopShop.style.display = 'inline-block';
                mobileBtn.style.display   = 'none';
                mobileDrop.style.display  = 'none';
                quickOuter.style.borderRadius = '99px';
                quickInner.style.borderRadius = '99px';
            }
        }

        function toggleMobileMenu() {
            const drop = document.getElementById('mobileDropdown');
            drop.style.display = (drop.style.display === 'flex') ? 'none' : 'flex';
        }

        function closeMobileMenu() {
            document.getElementById('mobileDropdown').style.display = 'none';
        }

        window.addEventListener('resize', applyResponsiveLayout);
        window.addEventListener('DOMContentLoaded', applyResponsiveLayout);
    </script>

</body>
</html>