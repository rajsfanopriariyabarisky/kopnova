# KOPNOVA — Ekosistem Rantai Pasok Agribisnis Terpadu

Sistem informasi manajemen rantai pasok terintegrasi yang menghubungkan **Petani Pemasok Hulu**, **Koperasi Primer (Tingkat Kecamatan)**, **Koperasi Konsumen (Pusat Nasional)**, dan **Mitra Buyer Hilir** dalam satu siklus arus barang dan arus kas (*end-to-end*).

---

## Fitur Utama

* **Multi-Role Access Control (RBAC):** Pembagian *workspace* dan hak akses spesifik untuk peran `Konsumen`, `Primer`, `Petani`, dan `Buyer`.
* **Tata Kelola Wilayah & Unit:** Manajemen Divisi Kabupaten/Kota dan jaringan Koperasi Primer di tiap kecamatan.
* **Regulasi Harga Acuan (HAP/HET) & Simulasi SHU:** Standarisasi harga beli petani dan harga jual pasar dengan skema bagi hasil otomatis (**85% Petani : 10% Primer : 5% Konsumen**).
* **Marketplace B2B Multi-Wilayah:** Pembelian komoditas lintas kabupaten dalam satu keranjang dan satu pembayaran terpusat.
* **Order Routing & Surat Jalan Multi-Kecamatan:** Pemecahan otomatis pesanan lintas wilayah menjadi *Picking List* per kecamatan beserta dokumen Surat Jalan siap cetak.
* **Early Warning System (EWS) & Instruksi Panen:** Deteksi dini stok kritis (`< 50` unit) dan pengiriman surat tugas *restock* ke Koperasi Primer & Petani.
* **Settlement & Pencairan Dana (*Disbursement*):** Pencatatan dan distribusi pembayaran berjenjang dari Pusat ke Koperasi Primer hingga Petani Pemasok dilengkapi bukti resi resmi.
* **Ekspor Laporan Eksekutif & Audit Trail:** Unduh laporan konsolidasi satu klik dalam format **Excel (.XLS dengan rumus aktif)**, **PDF Siap Cetak**, dan **CSV** beserta pencatatan log audit otomatis.

---

## Teknologi yang Digunakan

* **Backend:** Native PHP 8+ (PDO MySQL)
* **Database:** MySQL / MariaDB (`db_kopnova`)
* **Frontend:** HTML5, CSS3 (Custom Responsive Layout), Vanilla JavaScript

---

## Cara Instalasi Lokal (XAMPP)

1. *Clone* repositori ini ke folder `htdocs`:
   ```bash
   git clone [https://github.com/rajsfanopriariyabarisky/kopnova.git](https://github.com/rajsfanopriariyabarisky/kopnova.git)
Jalankan Apache dan MySQL melalui XAMPP Control Panel.

Buka http://localhost/phpmyadmin, buat database baru bernama db_kopnova, lalu impor file db_kopnova.sql.

Sesuaikan koneksi database (jika diperlukan) pada file config/database.php.

Akses aplikasi melalui browser di http://localhost/kopnova.

Akun Demo Default
Peran (Role)	Username	Password
Koperasi Konsumen (Pusat)	konsumen	123
Koperasi Primer (Cikajang)	primer	123
Koperasi Primer (Pangalengan)	primer_pangalengan	123
Petani Pemasok	petani	123
Mitra Buyer	buyer	123
Changelog Pembaruan
[v1.0.0] - 20 Nov 2026

Rilis penuh modul inti rantai pasok (RBAC, Wilayah, Manajemen User, Pasokan Hulu, Etalase, & Checkout).

Penambahan modul Regulasi Harga Acuan & Simulator SHU.

Penambahan modul Order Routing & Surat Jalan Multi-Kecamatan.

Penambahan modul Early Warning System & Instruksi Restock.

Penambahan modul Settlement Pencairan Dana & Voucher Resmi.

Penambahan modul Ekspor Laporan Eksekutif (Excel Formula, PDF Print, CSV) & Audit Trail.
