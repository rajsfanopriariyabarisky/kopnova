<?php
require_once '../../config/database.php';
cekAkses(['primer', 'konsumen']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

verify_csrf();
$role = $_SESSION['user']['role'] ?? '';
$user = $_SESSION['user'] ?? [];
$aksi = $_POST['aksi'] ?? '';

function redirectProduk(string $type, string $text): void {
    header("Location: index.php?" . $type . "=" . urlencode($text));
    exit;
}

function resolvePrimerDivisi(PDO $pdo, string $role, array $user, string $rawPair): array {
    if ($role === 'primer') {
        $id_primer = (int)($user['id_primer'] ?? 0);
        $stmtDiv = $pdo->prepare("SELECT id_divisi FROM koperasi_primer WHERE id_primer = ?");
        $stmtDiv->execute([$id_primer]);
        $id_divisi = (int)$stmtDiv->fetchColumn();
        return [$id_primer, $id_divisi];
    }

    $parts = explode('|', $rawPair);
    $id_primer = (int)($parts[0] ?? 0);
    $id_divisi = (int)($parts[1] ?? 0);
    return [$id_primer, $id_divisi];
}

try {
    /* =========================================================================
       1. TAMBAH PRODUK BARU KE ETALASE
       ========================================================================= */
    if ($aksi === 'tambah_produk') {
        [$id_primer, $id_divisi] = resolvePrimerDivisi($pdo, $role, $user, $_POST['primer_divisi'] ?? '0|0');

        $nama_produk = trim($_POST['nama_produk'] ?? '');
        $kategori    = trim($_POST['kategori'] ?? 'Komoditas Tanaman');
        $harga_jual  = (float)($_POST['harga_jual'] ?? 0);
        $stok        = (int)($_POST['stok'] ?? 0);
        $satuan      = trim($_POST['satuan'] ?? 'Kg');
        $deskripsi   = trim($_POST['deskripsi'] ?? '');

        if ($id_primer <= 0 || $id_divisi <= 0 || $nama_produk === '' || $satuan === '' || $harga_jual <= 0 || $stok < 0) {
            redirectProduk('err', 'Data unit koperasi, nama produk, harga jual (> 0), atau stok tidak valid.');
        }

        $foto = uploadFoto('foto_produk', 'produk') ?? 'default.jpg';
        $kode = 'PRD-' . random_int(1000, 9999);

        $stmt = $pdo->prepare("
            INSERT INTO produk_etalase
            (id_divisi, id_primer, kode_produk, nama_produk, kategori, harga_jual, stok, satuan, foto_produk, deskripsi)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $id_divisi,
            $id_primer,
            $kode,
            $nama_produk,
            $kategori,
            $harga_jual,
            $stok,
            $satuan,
            $foto,
            $deskripsi
        ]);

        redirectProduk('msg', "Produk '{$nama_produk}' ({$kode}) berhasil dipublikasikan ke etalase Online Shop Buyer.");
    }

    /* =========================================================================
       2. EDIT & RESTOCK PRODUK ETALASE
       ========================================================================= */
    if ($aksi === 'edit_produk') {
        $id_produk   = (int)($_POST['id_produk'] ?? 0);
        $nama_produk = trim($_POST['nama_produk'] ?? '');
        $kategori    = trim($_POST['kategori'] ?? 'Komoditas Tanaman');
        $harga_jual  = (float)($_POST['harga_jual'] ?? 0);
        $stok        = (int)($_POST['stok'] ?? 0);
        $satuan      = trim($_POST['satuan'] ?? 'Kg');
        $deskripsi   = trim($_POST['deskripsi'] ?? '');

        if ($id_produk <= 0 || $nama_produk === '' || $satuan === '' || $harga_jual <= 0 || $stok < 0) {
            redirectProduk('err', 'Data pembaruan produk tidak valid.');
        }

        // Ambil data produk lama untuk verifikasi kepemilikan & manajemen file foto
        if ($role === 'primer') {
            $stmtOld = $pdo->prepare("SELECT * FROM produk_etalase WHERE id_produk = ? AND id_primer = ?");
            $stmtOld->execute([$id_produk, (int)($user['id_primer'] ?? 0)]);
        } else {
            $stmtOld = $pdo->prepare("SELECT * FROM produk_etalase WHERE id_produk = ?");
            $stmtOld->execute([$id_produk]);
        }

        $oldProduk = $stmtOld->fetch();
        if (!$oldProduk) {
            redirectProduk('err', 'Produk tidak ditemukan atau bukan milik koperasi Anda.');
        }

        if ($role === 'konsumen' && !empty($_POST['primer_divisi'])) {
            [$id_primer, $id_divisi] = resolvePrimerDivisi($pdo, $role, $user, $_POST['primer_divisi']);
        } else {
            $id_primer = (int)$oldProduk['id_primer'];
            $id_divisi = (int)$oldProduk['id_divisi'];
        }

        $fotoFinal = $oldProduk['foto_produk'];
        if (!empty($_FILES['foto_produk']['name']) && ($_FILES['foto_produk']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $uploaded = uploadFoto('foto_produk', 'produk');
            if ($uploaded) {
                $fotoFinal = $uploaded;
                // Hapus file lama jika bukan default.jpg
                if (!empty($oldProduk['foto_produk']) && $oldProduk['foto_produk'] !== 'default.jpg') {
                    $oldPath = __DIR__ . '/../../uploads/produk/' . basename($oldProduk['foto_produk']);
                    if (is_file($oldPath)) {
                        @unlink($oldPath);
                    }
                }
            }
        }

        $stmtUpd = $pdo->prepare("
            UPDATE produk_etalase
            SET id_divisi = ?, id_primer = ?, nama_produk = ?, kategori = ?, harga_jual = ?, stok = ?, satuan = ?, foto_produk = ?, deskripsi = ?
            WHERE id_produk = ?
        ");
        $stmtUpd->execute([
            $id_divisi,
            $id_primer,
            $nama_produk,
            $kategori,
            $harga_jual,
            $stok,
            $satuan,
            $fotoFinal,
            $deskripsi,
            $id_produk
        ]);

        redirectProduk('msg', "Data & stok produk '{$nama_produk}' berhasil diperbarui.");
    }

    /* =========================================================================
       3. HAPUS PRODUK DARI ETALASE
       ========================================================================= */
    if ($aksi === 'hapus_produk') {
        $id_produk = (int)($_POST['id_produk'] ?? 0);
        if ($id_produk <= 0) {
            redirectProduk('err', 'ID produk tidak valid.');
        }

        if ($role === 'primer') {
            $stmtGet = $pdo->prepare("SELECT foto_produk FROM produk_etalase WHERE id_produk = ? AND id_primer = ?");
            $stmtGet->execute([$id_produk, (int)($user['id_primer'] ?? 0)]);
        } else {
            $stmtGet = $pdo->prepare("SELECT foto_produk FROM produk_etalase WHERE id_produk = ?");
            $stmtGet->execute([$id_produk]);
        }

        $target = $stmtGet->fetch();
        if (!$target) {
            redirectProduk('err', 'Produk tidak ditemukan atau bukan milik koperasi Anda.');
        }

        if ($role === 'primer') {
            $stmtDel = $pdo->prepare("DELETE FROM produk_etalase WHERE id_produk = ? AND id_primer = ?");
            $stmtDel->execute([$id_produk, (int)($user['id_primer'] ?? 0)]);
        } else {
            $stmtDel = $pdo->prepare("DELETE FROM produk_etalase WHERE id_produk = ?");
            $stmtDel->execute([$id_produk]);
        }

        if ($stmtDel->rowCount() > 0 && !empty($target['foto_produk']) && $target['foto_produk'] !== 'default.jpg') {
            $filePath = __DIR__ . '/../../uploads/produk/' . basename($target['foto_produk']);
            if (is_file($filePath)) {
                @unlink($filePath);
            }
        }

        redirectProduk('msg', 'Produk berhasil dihapus dari etalase.');
    }

    redirectProduk('err', 'Aksi manajemen produk tidak dikenali.');
} catch (PDOException $e) {
    redirectProduk('err', 'Operasi gagal diproses karena produk masih terhubung dengan riwayat pesanan Buyer.');
}