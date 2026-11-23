-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 20 Nov 2026 pada 08.40
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
(1, 'PSK-20261120-829', 3, 1, 'Kentang Granola Super', 'Komoditas Tanaman', 100, 'Kg', 30000.00, 3000000.00, 'KOPNOVA_6affec214280c.jpg', 'Diterima Primer', '2026-11-20 13:43:45');

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
(1, 1, 1, 'PRD-5706', 'Kentang Granola Premium', 'Komoditas Tanaman', 30000.00, 100, 'Kg', 'KOPNOVA_6affed739b852.jpg', 'Kualitas Super Premium Impor Langsung dari petani kita yang paling tampan', '2026-11-20 06:49:23');

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
(1, 1, NULL, 'Koperasi Konsumen Pusat (Nasional)', 'konsumen', '$2y$12$KN.yA0OOkNzCnE1hVsaLrO07KQqtpscSBX3K.xnDBb3Qf7MiZ72Sa', 'konsumen', '081200000001', 'Kantor Pusat Koperasi Konsumen'),
(2, 1, 1, 'Admin Primer Cikajang', 'primer', '$2y$12$KN.yA0OOkNzCnE1hVsaLrO07KQqtpscSBX3K.xnDBb3Qf7MiZ72Sa', 'primer', '081200000002', 'Jl. Raya Cikajang No. 12'),
(3, 1, 1, 'Kang Ujang (Petani Hortikultura)', 'petani', '$2y$12$KN.yA0OOkNzCnE1hVsaLrO07KQqtpscSBX3K.xnDBb3Qf7MiZ72Sa', 'petani', '081200000003', 'Desa Girijaya, Kec. Cikajang'),
(4, NULL, NULL, 'PT Agro Retail Nusantara (Buyer)', 'buyer', '$2y$12$KN.yA0OOkNzCnE1hVsaLrO07KQqtpscSBX3K.xnDBb3Qf7MiZ72Sa', 'buyer', '081200000004', 'Kawasan Industri & Ritel Modern'),
(5, 1, 2, 'Admin Primer Cisurupan', 'primer_cisurupan', '$2y$12$KN.yA0OOkNzCnE1hVsaLrO07KQqtpscSBX3K.xnDBb3Qf7MiZ72Sa', 'primer', '081200000022', 'Jl. Raya Cisurupan No. 8, Garut'),
(6, 2, 3, 'Admin Primer Pangalengan', 'primer_pangalengan', '$2y$12$KN.yA0OOkNzCnE1hVsaLrO07KQqtpscSBX3K.xnDBb3Qf7MiZ72Sa', 'primer', '081200000023', 'Jl. Raya Pangalengan No. 45, Bandung'),
(7, 2, 3, 'Pak Cecep (Petani Kopi Arabika)', 'petani_cecep', '$2y$12$KN.yA0OOkNzCnE1hVsaLrO07KQqtpscSBX3K.xnDBb3Qf7MiZ72Sa', 'petani', '081200000032', 'Desa Margamulya, Kec. Pangalengan'),
(8, NULL, NULL, 'CV Pangan Segar Mandiri (Buyer)', 'buyer_pangan', '$2y$12$KN.yA0OOkNzCnE1hVsaLrO07KQqtpscSBX3K.xnDBb3Qf7MiZ72Sa', 'buyer', '081200000042', 'Pasar Induk Kramat Jati, Jakarta Timur');

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
  MODIFY `id_audit` int(11) NOT NULL AUTO_INCREMENT;

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
  MODIFY `id_instruksi` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `koperasi_primer`
--
ALTER TABLE `koperasi_primer`
  MODIFY `id_primer` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `orders`
--
ALTER TABLE `orders`
  MODIFY `id_order` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id_item` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `pasokan_petani`
--
ALTER TABLE `pasokan_petani`
  MODIFY `id_pasokan` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `produk_etalase`
--
ALTER TABLE `produk_etalase`
  MODIFY `id_produk` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `settlement_dana`
--
ALTER TABLE `settlement_dana`
  MODIFY `id_settlement` int(11) NOT NULL AUTO_INCREMENT;

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

ALTER TABLE `settlement_dana`
  ADD UNIQUE KEY `uniq_order_primer` (`id_order`, `id_primer`),
  ADD CONSTRAINT `fk_settlement_order` FOREIGN KEY (`id_order`) REFERENCES `orders` (`id_order`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_settlement_primer` FOREIGN KEY (`id_primer`) REFERENCES `koperasi_primer` (`id_primer`) ON DELETE CASCADE;