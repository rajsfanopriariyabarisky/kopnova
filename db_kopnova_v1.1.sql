-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 27 Nov 2026 pada 07.52
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.2.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_kopnova`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `audit_trail_laporan`
--

CREATE TABLE `audit_trail_laporan` (
  `id_audit` int(11) NOT NULL,
  `kode_audit` varchar(35) NOT NULL,
  `id_user` int(11) NOT NULL,
  `nama_user` varchar(100) NOT NULL,
  `role_user` varchar(30) NOT NULL,
  `jenis_laporan` varchar(100) NOT NULL,
  `format_file` enum('EXCEL (.XLS)','CSV (.CSV)','PDF PRINT') NOT NULL,
  `filter_periode` varchar(80) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `audit_trail_laporan`
--

INSERT INTO `audit_trail_laporan` (`id_audit`, `kode_audit`, `id_user`, `nama_user`, `role_user`, `jenis_laporan`, `format_file`, `filter_periode`, `ip_address`, `created_at`) VALUES
(1, 'AUD-XLS-20261126093000', 1, 'Koperasi Konsumen Pusat (Nasional)', 'konsumen', 'Laporan Eksekutif (Kementerian Koperasi & UKM)', 'EXCEL (.XLS)', '2026-11-01 s/d 2026-11-26 (Divisi: Nasional)', '127.0.0.1', '2026-11-26 09:30:00'),
(2, 'AUD-XLS-20261127031551', 1, 'Koperasi Konsumen Pusat (Nasional)', 'konsumen', 'Laporan Eksekutif (Kementerian Koperasi & UKM)', 'EXCEL (.XLS)', '2026-11-01 s/d 2026-11-27 (Divisi: Nasional)', '::1', '2026-11-27 09:15:51'),
(3, 'AUD-PDF-20261127042836', 1, 'Koperasi Konsumen Pusat (Nasional)', 'konsumen', 'Laporan Eksekutif (Kementerian Koperasi & UKM)', 'PDF PRINT', '2026-11-01 s/d 2026-11-27 (Divisi: Nasional)', '::1', '2026-11-27 10:28:36'),
(4, 'AUD-PDF-20261127044711', 1, 'Koperasi Konsumen Pusat (Nasional)', 'konsumen', 'Laporan Eksekutif (Kementerian Koperasi & UKM)', 'PDF PRINT', '2026-11-01 s/d 2026-11-27 (Divisi: Nasional)', '::1', '2026-11-27 10:47:11'),
(5, 'AUD-CSV-20261127044733', 1, 'Koperasi Konsumen Pusat (Nasional)', 'konsumen', 'Dataset Raw (Kementerian Koperasi & UKM)', 'CSV (.CSV)', '2026-11-01 s/d 2026-11-27 (Divisi: Nasional)', '::1', '2026-11-27 10:47:33');

-- --------------------------------------------------------

--
-- Struktur dari tabel `divisi_wilayah`
--

CREATE TABLE `divisi_wilayah` (
  `id_divisi` int(11) NOT NULL,
  `kode_divisi` varchar(20) NOT NULL,
  `nama_divisi` varchar(100) NOT NULL,
  `provinsi` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `divisi_wilayah`
--

INSERT INTO `divisi_wilayah` (`id_divisi`, `kode_divisi`, `nama_divisi`, `provinsi`, `created_at`) VALUES
(1, 'DIV-GRT', 'Divisi Kabupaten Garut', 'Jawa Barat', '2026-11-20 03:01:24'),
(2, 'DIV-BDG', 'Divisi Kabupaten Bandung', 'Jawa Barat', '2026-11-20 03:01:24'),
(3, 'Bogor', 'Cibinong', 'Jawa Barat', '2026-11-20 04:11:43'),
(4, 'BGR', 'Bogor', 'Jawa Barat', '2026-11-20 06:38:34');

-- --------------------------------------------------------

--
-- Struktur dari tabel `harga_acuan_shu`
--

CREATE TABLE `harga_acuan_shu` (
  `id_regulasi` int(11) NOT NULL,
  `kode_regulasi` varchar(25) NOT NULL,
  `nama_komoditas` varchar(100) NOT NULL,
  `kategori` enum('Komoditas Tanaman','Saprodi','Alsintan','Produk Olahan','Produk Lainnya') NOT NULL,
  `satuan` varchar(20) NOT NULL,
  `hap_petani` decimal(12,2) NOT NULL COMMENT 'Harga Acuan Pembelian Minimum dari Petani',
  `het_buyer` decimal(12,2) NOT NULL COMMENT 'Harga Jual Standar ke Buyer',
  `persen_petani` decimal(5,2) NOT NULL DEFAULT 85.00,
  `persen_primer` decimal(5,2) NOT NULL DEFAULT 10.00,
  `persen_konsumen` decimal(5,2) NOT NULL DEFAULT 5.00,
  `status_regulasi` enum('Aktif','Arsip') DEFAULT 'Aktif',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `harga_acuan_shu`
--

INSERT INTO `harga_acuan_shu` (`id_regulasi`, `kode_regulasi`, `nama_komoditas`, `kategori`, `satuan`, `hap_petani`, `het_buyer`, `persen_petani`, `persen_primer`, `persen_konsumen`, `status_regulasi`, `updated_at`) VALUES
(1, 'REG-2026-001', 'Kentang Granola Grade A', 'Komoditas Tanaman', 'Kg', 12000.00, 14500.00, 85.00, 10.00, 5.00, 'Aktif', '2026-11-20 04:35:04'),
(2, 'REG-2026-002', 'Biji Kopi Arabika Green Bean', 'Produk Olahan', 'Kg', 68000.00, 82000.00, 84.00, 11.00, 5.00, 'Aktif', '2026-11-20 04:35:04'),
(3, 'REG-2026-003', 'Pupuk Organik Kompos Granul', 'Saprodi', 'Karung', 42000.00, 50000.00, 80.00, 13.00, 7.00, 'Aktif', '2026-11-20 04:35:04');

-- --------------------------------------------------------

--
-- Struktur dari tabel `instruksi_pasokan`
--

CREATE TABLE `instruksi_pasokan` (
  `id_instruksi` int(11) NOT NULL,
  `kode_instruksi` varchar(30) NOT NULL,
  `id_primer` int(11) NOT NULL,
  `nama_komoditas` varchar(100) NOT NULL,
  `kategori` enum('Komoditas Tanaman','Saprodi','Alsintan','Produk Olahan','Produk Lainnya') NOT NULL,
  `target_jumlah` int(11) NOT NULL,
  `satuan` varchar(20) NOT NULL,
  `tingkat_urgensi` enum('Kritis (< 20)','Waspada (< 50)','Prediksi Musiman') DEFAULT 'Waspada (< 50)',
  `catatan_pusat` text DEFAULT NULL,
  `status_instruksi` enum('Terkirim ke Primer','Diteruskan ke Petani','Terpenuhi') DEFAULT 'Terkirim ke Primer',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `instruksi_pasokan`
--

INSERT INTO `instruksi_pasokan` (`id_instruksi`, `kode_instruksi`, `id_primer`, `nama_komoditas`, `kategori`, `target_jumlah`, `satuan`, `tingkat_urgensi`, `catatan_pusat`, `status_instruksi`, `created_at`) VALUES
(1, 'EWS-20261125-001', 3, 'Biji Kopi Arabika Green Bean', 'Produk Olahan', 250, 'Kg', 'Waspada (< 50)', 'Permintaan B2B kafe meningkat tajam, mohon segera himpun panen kopi petani Pangalengan.', 'Terkirim ke Primer', '2026-11-25 14:00:00'),
(2, 'EWS-20261126-002', 2, 'Cabai Merah Keriting Super', 'Komoditas Tanaman', 150, 'Kg', 'Kritis (< 20)', 'Stok di bawah batas kritis 20 Kg. Prioritas pengumpulan panen minggu ini.', 'Diteruskan ke Petani', '2026-11-26 08:45:00');

-- --------------------------------------------------------

--
-- Struktur dari tabel `koperasi_primer`
--

CREATE TABLE `koperasi_primer` (
  `id_primer` int(11) NOT NULL,
  `id_divisi` int(11) NOT NULL,
  `kode_primer` varchar(20) NOT NULL,
  `nama_koperasi` varchar(100) NOT NULL,
  `kecamatan` varchar(100) NOT NULL,
  `ketua_koperasi` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `koperasi_primer`
--

INSERT INTO `koperasi_primer` (`id_primer`, `id_divisi`, `kode_primer`, `nama_koperasi`, `kecamatan`, `ketua_koperasi`) VALUES
(1, 1, 'PRM-CKJ', 'Koperasi Primer Cikajang', 'Cikajang', 'Deden Kurniawan'),
(2, 1, 'PRM-CSR', 'Koperasi Primer Cisurupan', 'Cisurupan', 'Asep Saepudin'),
(3, 2, 'PRM-PGL', 'Koperasi Primer Pangalengan', 'Pangalengan', 'Hendra Wijaya'),
(4, 4, 'BGR', 'koperasi anak A1 - Sukaraja', 'Sukaraja', 'Fahri');

-- --------------------------------------------------------

--
-- Struktur dari tabel `orders`
--

CREATE TABLE `orders` (
  `id_order` int(11) NOT NULL,
  `no_invoice` varchar(30) NOT NULL,
  `id_buyer` int(11) NOT NULL,
  `total_bayar` decimal(15,2) NOT NULL,
  `metode_bayar` enum('QRIS Instan','Transfer Virtual Account','Termin B2B') DEFAULT 'QRIS Instan',
  `alamat_pengiriman` text NOT NULL,
  `status_order` enum('Menunggu Konfirmasi','Diproses Koperasi','Dikirim','Selesai') DEFAULT 'Menunggu Konfirmasi',
  `tanggal_order` datetime DEFAULT current_timestamp(),
  `no_surat_jalan` varchar(35) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `orders`
--

INSERT INTO `orders` (`id_order`, `no_invoice`, `id_buyer`, `total_bayar`, `metode_bayar`, `alamat_pengiriman`, `status_order`, `tanggal_order`, `no_surat_jalan`) VALUES
(1, 'INV-KOP-20261122-001', 4, 4640000.00, 'Termin B2B', 'Gudang Distribusi PT Agro Retail Nusantara, Jakarta Timur', 'Selesai', '2026-11-22 11:20:00', 'SJ-KOP-20261122-001'),
(2, 'INV-KOP-20261127-002', 8, 2260000.00, 'Transfer Virtual Account', 'Pasar Induk Kramat Jati Blok C12, Jakarta Timur', 'Diproses Koperasi', '2026-11-27 09:14:59', 'SJ-KPN-20261127-0002');

-- --------------------------------------------------------

--
-- Struktur dari tabel `order_items`
--

CREATE TABLE `order_items` (
  `id_item` int(11) NOT NULL,
  `id_order` int(11) NOT NULL,
  `id_produk` int(11) NOT NULL,
  `qty` int(11) NOT NULL,
  `harga_satuan` decimal(12,2) NOT NULL,
  `subtotal` decimal(15,2) NOT NULL,
  `status_routing` enum('Menunggu Instruksi','Instruksi Terkirim','Siap Diambil Primer','Terkonsolidasi Pusat') NOT NULL DEFAULT 'Menunggu Instruksi'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `order_items`
--

INSERT INTO `order_items` (`id_item`, `id_order`, `id_produk`, `qty`, `harga_satuan`, `subtotal`, `status_routing`) VALUES
(1, 1, 1, 100, 30000.00, 3000000.00, 'Terkonsolidasi Pusat'),
(2, 1, 2, 20, 82000.00, 1640000.00, 'Terkonsolidasi Pusat'),
(3, 2, 1, 30, 30000.00, 900000.00, 'Menunggu Instruksi'),
(4, 2, 2, 10, 82000.00, 820000.00, 'Menunggu Instruksi'),
(5, 2, 3, 10, 50000.00, 500000.00, 'Menunggu Instruksi');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pasokan_petani`
--

CREATE TABLE `pasokan_petani` (
  `id_pasokan` int(11) NOT NULL,
  `kode_pasokan` varchar(30) NOT NULL,
  `id_petani` int(11) NOT NULL,
  `id_primer` int(11) NOT NULL,
  `nama_komoditas` varchar(100) NOT NULL,
  `kategori` enum('Komoditas Tanaman','Saprodi','Alsintan','Produk Olahan','Produk Lainnya') NOT NULL,
  `jumlah` int(11) NOT NULL,
  `satuan` varchar(20) NOT NULL,
  `harga_tawar` decimal(12,2) NOT NULL,
  `total_nilai` decimal(15,2) NOT NULL,
  `foto_pasokan` varchar(255) DEFAULT 'default.jpg',
  `status_pasokan` enum('Menunggu Verifikasi','Diterima Primer','Ditolak') DEFAULT 'Menunggu Verifikasi',
  `tanggal_setor` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `pasokan_petani`
--

INSERT INTO `pasokan_petani` (`id_pasokan`, `kode_pasokan`, `id_petani`, `id_primer`, `nama_komoditas`, `kategori`, `jumlah`, `satuan`, `harga_tawar`, `total_nilai`, `foto_pasokan`, `status_pasokan`, `tanggal_setor`) VALUES
(1, 'PSK-20261120-829', 3, 1, 'Kentang Granola Super', 'Komoditas Tanaman', 100, 'Kg', 30000.00, 3000000.00, 'KOPNOVA_6affec214280c.jpg', 'Diterima Primer', '2026-11-20 13:43:45'),
(2, 'PSK-20261121-101', 7, 3, 'Biji Kopi Arabika Preanger', 'Produk Olahan', 200, 'Kg', 68000.00, 13600000.00, 'default.jpg', 'Diterima Primer', '2026-11-21 09:15:00'),
(3, 'PSK-20261124-102', 3, 1, 'Pupuk Organik Kompos Granul', 'Saprodi', 150, 'Karung', 42000.00, 6300000.00, 'default.jpg', 'Diterima Primer', '2026-11-24 10:30:00'),
(4, 'PSK-20261127-103', 3, 1, 'Wortel Organik Cikajang', 'Komoditas Tanaman', 120, 'Kg', 9500.00, 1140000.00, 'default.jpg', 'Menunggu Verifikasi', '2026-11-27 09:14:59');

-- --------------------------------------------------------

--
-- Struktur dari tabel `produk_etalase`
--

CREATE TABLE `produk_etalase` (
  `id_produk` int(11) NOT NULL,
  `id_divisi` int(11) NOT NULL,
  `id_primer` int(11) NOT NULL,
  `kode_produk` varchar(20) NOT NULL,
  `nama_produk` varchar(100) NOT NULL,
  `kategori` enum('Komoditas Tanaman','Saprodi','Alsintan','Produk Olahan','Produk Lainnya') NOT NULL,
  `harga_jual` decimal(12,2) NOT NULL,
  `stok` int(11) NOT NULL DEFAULT 0,
  `satuan` varchar(20) NOT NULL,
  `foto_produk` varchar(255) DEFAULT 'default.jpg',
  `deskripsi` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `produk_etalase`
--

INSERT INTO `produk_etalase` (`id_produk`, `id_divisi`, `id_primer`, `kode_produk`, `nama_produk`, `kategori`, `harga_jual`, `stok`, `satuan`, `foto_produk`, `deskripsi`, `created_at`) VALUES
(1, 1, 1, 'PRD-5706', 'Kentang Granola Premium', 'Komoditas Tanaman', 30000.00, 100, 'Kg', 'KOPNOVA_6affed739b852.jpg', 'Kualitas Super Premium Impor Langsung dari petani kita yang paling tampan', '2026-11-20 06:49:23'),
(2, 2, 3, 'PRD-8801', 'Biji Kopi Arabika Green Bean', 'Produk Olahan', 82000.00, 24, 'Kg', 'default.jpg', 'Kopi Arabika petik merah khas Pangalengan kualitas ekspor. Stok kritis siap restock.', '2026-11-27 02:14:59'),
(3, 1, 1, 'PRD-8802', 'Pupuk Organik Kompos Granul', 'Saprodi', 50000.00, 140, 'Karung', 'default.jpg', 'Pupuk organik siap pakai produksi kelompok tani Cikajang.', '2026-11-27 02:14:59'),
(4, 1, 2, 'PRD-8803', 'Cabai Merah Keriting Super', 'Komoditas Tanaman', 38000.00, 18, 'Kg', 'default.jpg', 'Cabai segar hasil panen petani Cisurupan (Stok menipis < 20 Kg).', '2026-11-27 02:14:59');

-- --------------------------------------------------------

--
-- Struktur dari tabel `settlement_dana`
--

CREATE TABLE `settlement_dana` (
  `id_settlement` int(11) NOT NULL,
  `kode_settlement` varchar(35) NOT NULL,
  `id_order` int(11) NOT NULL,
  `id_primer` int(11) NOT NULL,
  `nominal_bruto` decimal(15,2) NOT NULL,
  `hak_petani` decimal(15,2) NOT NULL,
  `kas_primer` decimal(15,2) NOT NULL,
  `kas_konsumen` decimal(15,2) NOT NULL,
  `status_pencairan` enum('Menunggu Pencairan Pusat','Dicairkan ke Primer','Diteruskan ke Petani') NOT NULL DEFAULT 'Menunggu Pencairan Pusat',
  `tanggal_cair_primer` datetime DEFAULT NULL,
  `tanggal_cair_petani` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `settlement_dana`
--

INSERT INTO `settlement_dana` (`id_settlement`, `kode_settlement`, `id_order`, `id_primer`, `nominal_bruto`, `hak_petani`, `kas_primer`, `kas_konsumen`, `status_pencairan`, `tanggal_cair_primer`, `tanggal_cair_petani`, `created_at`) VALUES
(1, 'STL-20261127-1-1', 1, 1, 3000000.00, 2550000.00, 300000.00, 150000.00, 'Menunggu Pencairan Pusat', NULL, NULL, '2026-11-27 02:15:17'),
(2, 'STL-20261127-1-3', 1, 3, 1640000.00, 1377600.00, 180400.00, 82000.00, 'Diteruskan ke Petani', '2026-11-27 10:28:01', '2026-11-27 10:28:04', '2026-11-27 02:15:17');

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id_user` int(11) NOT NULL,
  `id_divisi` int(11) DEFAULT NULL,
  `id_primer` int(11) DEFAULT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('konsumen','primer','petani','buyer') NOT NULL,
  `no_hp` varchar(20) DEFAULT NULL,
  `alamat` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id_user`, `id_divisi`, `id_primer`, `nama_lengkap`, `username`, `password`, `role`, `no_hp`, `alamat`) VALUES
(1, 1, NULL, 'Koperasi Konsumen Pusat (Nasional)', 'konsumen', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'konsumen', '081200000001', 'Kantor Pusat Koperasi Konsumen'),
(2, 1, 1, 'Admin Primer Cikajang', 'primer', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'primer', '081200000002', 'Jl. Raya Cikajang No. 12'),
(3, 1, 1, 'Kang Ujang (Petani Hortikultura)', 'petani', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'petani', '081200000003', 'Desa Girijaya, Kec. Cikajang'),
(4, NULL, NULL, 'PT Agro Retail Nusantara (Buyer)', 'buyer', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'buyer', '081200000004', 'Kawasan Industri & Ritel Modern'),
(5, 1, 2, 'Admin Primer Cisurupan', 'primer_cisurupan', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'primer', '081200000022', 'Jl. Raya Cisurupan No. 8, Garut'),
(6, 2, 3, 'Admin Primer Pangalengan', 'primer_pangalengan', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'primer', '081200000023', 'Jl. Raya Pangalengan No. 45, Bandung'),
(7, 2, 3, 'Pak Cecep (Petani Kopi Arabika)', 'petani_cecep', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'petani', '081200000032', 'Desa Margamulya, Kec. Pangalengan'),
(8, NULL, NULL, 'CV Pangan Segar Mandiri (Buyer)', 'buyer_pangan', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'buyer', '081200000042', 'Pasar Induk Kramat Jati, Jakarta Timur');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `audit_trail_laporan`
--
ALTER TABLE `audit_trail_laporan`
  ADD PRIMARY KEY (`id_audit`);

--
-- Indeks untuk tabel `divisi_wilayah`
--
ALTER TABLE `divisi_wilayah`
  ADD PRIMARY KEY (`id_divisi`),
  ADD UNIQUE KEY `kode_divisi` (`kode_divisi`);

--
-- Indeks untuk tabel `harga_acuan_shu`
--
ALTER TABLE `harga_acuan_shu`
  ADD PRIMARY KEY (`id_regulasi`),
  ADD UNIQUE KEY `kode_regulasi` (`kode_regulasi`);

--
-- Indeks untuk tabel `instruksi_pasokan`
--
ALTER TABLE `instruksi_pasokan`
  ADD PRIMARY KEY (`id_instruksi`),
  ADD UNIQUE KEY `kode_instruksi` (`kode_instruksi`);

--
-- Indeks untuk tabel `koperasi_primer`
--
ALTER TABLE `koperasi_primer`
  ADD PRIMARY KEY (`id_primer`),
  ADD UNIQUE KEY `kode_primer` (`kode_primer`),
  ADD KEY `id_divisi` (`id_divisi`);

--
-- Indeks untuk tabel `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id_order`),
  ADD UNIQUE KEY `no_invoice` (`no_invoice`),
  ADD KEY `id_buyer` (`id_buyer`);

--
-- Indeks untuk tabel `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id_item`),
  ADD KEY `id_order` (`id_order`),
  ADD KEY `id_produk` (`id_produk`);

--
-- Indeks untuk tabel `pasokan_petani`
--
ALTER TABLE `pasokan_petani`
  ADD PRIMARY KEY (`id_pasokan`),
  ADD UNIQUE KEY `kode_pasokan` (`kode_pasokan`),
  ADD KEY `id_petani` (`id_petani`),
  ADD KEY `id_primer` (`id_primer`);

--
-- Indeks untuk tabel `produk_etalase`
--
ALTER TABLE `produk_etalase`
  ADD PRIMARY KEY (`id_produk`),
  ADD UNIQUE KEY `kode_produk` (`kode_produk`),
  ADD KEY `id_divisi` (`id_divisi`),
  ADD KEY `id_primer` (`id_primer`);

--
-- Indeks untuk tabel `settlement_dana`
--
ALTER TABLE `settlement_dana`
  ADD PRIMARY KEY (`id_settlement`),
  ADD UNIQUE KEY `kode_settlement` (`kode_settlement`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id_user`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `id_divisi` (`id_divisi`),
  ADD KEY `id_primer` (`id_primer`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `audit_trail_laporan`
--
ALTER TABLE `audit_trail_laporan`
  MODIFY `id_audit` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `divisi_wilayah`
--
ALTER TABLE `divisi_wilayah`
  MODIFY `id_divisi` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `harga_acuan_shu`
--
ALTER TABLE `harga_acuan_shu`
  MODIFY `id_regulasi` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `instruksi_pasokan`
--
ALTER TABLE `instruksi_pasokan`
  MODIFY `id_instruksi` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `koperasi_primer`
--
ALTER TABLE `koperasi_primer`
  MODIFY `id_primer` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `orders`
--
ALTER TABLE `orders`
  MODIFY `id_order` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id_item` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `pasokan_petani`
--
ALTER TABLE `pasokan_petani`
  MODIFY `id_pasokan` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `produk_etalase`
--
ALTER TABLE `produk_etalase`
  MODIFY `id_produk` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `settlement_dana`
--
ALTER TABLE `settlement_dana`
  MODIFY `id_settlement` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id_user` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `koperasi_primer`
--
ALTER TABLE `koperasi_primer`
  ADD CONSTRAINT `koperasi_primer_ibfk_1` FOREIGN KEY (`id_divisi`) REFERENCES `divisi_wilayah` (`id_divisi`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`id_buyer`) REFERENCES `users` (`id_user`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`id_order`) REFERENCES `orders` (`id_order`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`id_produk`) REFERENCES `produk_etalase` (`id_produk`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `pasokan_petani`
--
ALTER TABLE `pasokan_petani`
  ADD CONSTRAINT `pasokan_petani_ibfk_1` FOREIGN KEY (`id_petani`) REFERENCES `users` (`id_user`) ON DELETE CASCADE,
  ADD CONSTRAINT `pasokan_petani_ibfk_2` FOREIGN KEY (`id_primer`) REFERENCES `koperasi_primer` (`id_primer`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `produk_etalase`
--
ALTER TABLE `produk_etalase`
  ADD CONSTRAINT `produk_etalase_ibfk_1` FOREIGN KEY (`id_divisi`) REFERENCES `divisi_wilayah` (`id_divisi`) ON DELETE CASCADE,
  ADD CONSTRAINT `produk_etalase_ibfk_2` FOREIGN KEY (`id_primer`) REFERENCES `koperasi_primer` (`id_primer`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`id_divisi`) REFERENCES `divisi_wilayah` (`id_divisi`) ON DELETE SET NULL,
  ADD CONSTRAINT `users_ibfk_2` FOREIGN KEY (`id_primer`) REFERENCES `koperasi_primer` (`id_primer`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
