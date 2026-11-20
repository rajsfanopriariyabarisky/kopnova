<?php
require_once '../../config/database.php';
cekAkses(['konsumen']);
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

// Ambil data Divisi Wilayah & Koperasi Primer untuk dropdown
$divisi_list = $pdo->query("SELECT * FROM divisi_wilayah ORDER BY nama_divisi ASC")->fetchAll();
$primer_list = $pdo->query("SELECT p.*, d.nama_divisi FROM koperasi_primer p JOIN divisi_wilayah d ON p.id_divisi = d.id_divisi ORDER BY d.nama_divisi ASC, p.kecamatan ASC")->fetchAll();

// Ambil semua user beserta relasi wilayahnya
$users_list = $pdo->query("SELECT u.*, d.nama_divisi, p.nama_koperasi, p.kecamatan 
                           FROM users u 
                           LEFT JOIN divisi_wilayah d ON u.id_divisi = d.id_divisi 
                           LEFT JOIN koperasi_primer p ON u.id_primer = p.id_primer 
                           ORDER BY FIELD(u.role, 'konsumen', 'primer', 'petani', 'buyer'), u.id_user DESC")->fetchAll();

// Hitung ringkasan per role
$count_role = ['konsumen' => 0, 'primer' => 0, 'petani' => 0, 'buyer' => 0];
foreach ($users_list as $u) {
    if (isset($count_role[$u['role']])) {
        $count_role[$u['role']]++;
    }
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px; margin-bottom:24px;">
    <div>
        <h2 style="margin:0 0 4px; font-size:22px; color:#0F172A;">Manajemen Akun & Otorisasi User Lintas Wilayah</h2>
        <p style="margin:0; font-size:14px; color:#64748B;">Terbitkan kredensial login untuk Koperasi Primer kecamatan baru, Petani Pemasok, dan Mitra Buyer secara mandiri.</p>
    </div>
    <button onclick="openModal('modalTambahUser')" style="background:#059669; color:#FFFFFF; border:none; padding:12px 20px; border-radius:12px; font-weight:700; font-size:13.5px; cursor:pointer; box-shadow:0 4px 12px rgba(5,150,105,0.28);">
        + Terbitkan Akun Baru
    </button>
</div>

<?php if (isset($_GET['msg'])): ?>
<div style="background:#DCFCE7; border:1px solid #86EFAC; color:#166534; padding:12px 18px; border-radius:12px; margin-bottom:20px; font-weight:700; font-size:13.5px;">
    ✓ <?= htmlspecialchars($_GET['msg']) ?>
</div>
<?php endif; ?>

<?php if (isset($_GET['err'])): ?>
<div style="background:#FEE2E2; border:1px solid #FCA5A5; color:#991B1B; padding:12px 18px; border-radius:12px; margin-bottom:20px; font-weight:700; font-size:13.5px;">
    ⚠ <?= htmlspecialchars($_GET['err']) ?>
</div>
<?php endif; ?>

<!-- RINGKASAN AKTOR TERDAFTAR -->
<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:16px; margin-bottom:24px;">
    <div style="background:#FFFFFF; padding:18px 20px; border-radius:14px; border:1px solid #E2E8F0; border-left:4px solid #059669;">
        <div style="font-size:12px; color:#64748B; font-weight:700;">Koperasi Konsumen (Pusat)</div>
        <div style="font-size:24px; font-weight:900; color:#0F172A; margin-top:4px;"><?= $count_role['konsumen'] ?> Akun</div>
    </div>
    <div style="background:#FFFFFF; padding:18px 20px; border-radius:14px; border:1px solid #E2E8F0; border-left:4px solid #D97706;">
        <div style="font-size:12px; color:#64748B; font-weight:700;">Admin Koperasi Primer</div>
        <div style="font-size:24px; font-weight:900; color:#0F172A; margin-top:4px;"><?= $count_role['primer'] ?> Akun</div>
    </div>
    <div style="background:#FFFFFF; padding:18px 20px; border-radius:14px; border:1px solid #E2E8F0; border-left:4px solid #2563EB;">
        <div style="font-size:12px; color:#64748B; font-weight:700;">Petani / Pemasok Hulu</div>
        <div style="font-size:24px; font-weight:900; color:#0F172A; margin-top:4px;"><?= $count_role['petani'] ?> Akun</div>
    </div>
    <div style="background:#FFFFFF; padding:18px 20px; border-radius:14px; border:1px solid #E2E8F0; border-left:4px solid #7C3AED;">
        <div style="font-size:12px; color:#64748B; font-weight:700;">Mitra Buyer / Pembeli</div>
        <div style="font-size:24px; font-weight:900; color:#0F172A; margin-top:4px;"><?= $count_role['buyer'] ?> Akun</div>
    </div>
</div>

<!-- FILTER & PENCARIAN AKUN -->
<div style="background:#FFFFFF; padding:16px 20px; border-radius:14px; border:1px solid #E2E8F0; margin-bottom:20px; display:flex; gap:12px; flex-wrap:wrap;">
    <input type="text" id="searchUser" onkeyup="filterUsers()" placeholder="Cari nama lengkap, username, divisi kabupaten, atau kecamatan..." style="flex:1; min-width:230px; padding:10px 14px; border:1px solid #CBD5E1; border-radius:10px; font-size:13.5px; outline:none;">
    <select id="filterRole" onchange="filterUsers()" style="padding:10px 14px; border:1px solid #CBD5E1; border-radius:10px; font-size:13.5px; background:#FFFFFF; font-weight:600; color:#334155;">
        <option value="">Semua Role RBAC</option>
        <option value="konsumen">Koperasi Konsumen</option>
        <option value="primer">Koperasi Primer</option>
        <option value="petani">Petani Pemasok</option>
        <option value="buyer">Buyer / Pembeli</option>
    </select>
</div>

<!-- TABEL DAFTAR PENGGUNA -->
<div style="background:#FFFFFF; border-radius:16px; border:1px solid #E2E8F0; overflow:hidden; box-shadow:0 4px 12px rgba(0,0,0,0.02);">
    <div style="overflow-x:auto;">
        <table id="userTable" style="width:100%; border-collapse:collapse; text-align:left; font-size:13.5px;">
            <thead>
                <tr style="background:#064E3B; color:#FFFFFF;">
                    <th style="padding:14px;">Nama Lengkap / Badan</th>
                    <th style="padding:14px;">Username Login</th>
                    <th style="padding:14px;">Role RBAC</th>
                    <th style="padding:14px;">Penempatan Wilayah</th>
                    <th style="padding:14px;">Kontak</th>
                    <th style="padding:14px; text-align:center;">Aksi Manajemen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users_list as $u): 
                    $badge_bg = '#DCFCE7'; $badge_col = '#166534';
                    if ($u['role'] === 'primer') { $badge_bg = '#FEF3C7'; $badge_col = '#92400E'; }
                    elseif ($u['role'] === 'petani') { $badge_bg = '#DBEAFE'; $badge_col = '#1E40AF'; }
                    elseif ($u['role'] === 'buyer') { $badge_bg = '#F3E8FF'; $badge_col = '#6B21A8'; }
                ?>
                <tr style="border-bottom:1px solid #F1F5F9;">
                    <td style="padding:14px;">
                        <div style="font-weight:700; color:#0F172A;"><?= htmlspecialchars($u['nama_lengkap']) ?></div>
                        <div style="font-size:11.5px; color:#64748B;"><?= htmlspecialchars($u['alamat'] ?: 'Alamat belum dilengkapi') ?></div>
                    </td>
                    <td style="padding:14px; font-family:monospace; font-weight:700; color:#047857; font-size:13px;">
                        @<?= htmlspecialchars($u['username']) ?>
                    </td>
                    <td style="padding:14px;">
                        <span style="background:<?= $badge_bg ?>; color:<?= $badge_col ?>; padding:4px 11px; border-radius:99px; font-size:11.5px; font-weight:800; text-transform:uppercase;">
                            <?= htmlspecialchars($u['role']) ?>
                        </span>
                    </td>
                    <td style="padding:14px;">
                        <?php if ($u['role'] === 'buyer'): ?>
                            <span style="color:#64748B; font-size:12.5px;">Akses Marketplace Nasional</span>
                        <?php else: ?>
                            <div style="font-weight:600; color:#1E293B;"><?= htmlspecialchars($u['nama_divisi'] ?? 'Semua Divisi') ?></div>
                            <?php if ($u['nama_koperasi']): ?>
                                <div style="font-size:11.5px; color:#059669;"><?= htmlspecialchars($u['nama_koperasi']) ?> (Kec. <?= htmlspecialchars($u['kecamatan']) ?>)</div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td style="padding:14px; font-size:13px; color:#475569;">
                        <?= htmlspecialchars($u['no_hp'] ?: '-') ?>
                    </td>
                    <td style="padding:14px; text-align:center; white-space:nowrap;">
                        <button onclick='openEditUserModal(<?= json_encode($u) ?>)' style="background:#FEF3C7; color:#92400E; border:none; padding:7px 11px; border-radius:8px; font-weight:700; font-size:12px; cursor:pointer; margin-right:4px;">Edit</button>
                        <a href="proses.php?reset_pw=<?= $u['id_user'] ?>" onclick="return confirm('Reset kata sandi akun @<?= htmlspecialchars($u['username']) ?> menjadi default (123)?')" style="background:#E0F2FE; color:#0369A1; text-decoration:none; padding:7px 11px; border-radius:8px; font-weight:700; font-size:12px; margin-right:4px;">Reset Sandi</a>
                        <?php if ($u['id_user'] != $user['id_user']): ?>
                            <a href="proses.php?hapus=<?= $u['id_user'] ?>" onclick="return confirm('Hapus permanen akun @<?= htmlspecialchars($u['username']) ?>?')" style="background:#FEE2E2; color:#991B1B; text-decoration:none; padding:7px 11px; border-radius:8px; font-weight:700; font-size:12px;">Hapus</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL 1: TERBITKAN AKUN BARU -->
<div id="modalTambahUser" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.65); z-index:999; align-items:center; justify-content:center; padding:18px;">
    <div style="background:#FFFFFF; width:100%; max-width:540px; border-radius:20px; padding:28px; box-shadow:0 25px 50px rgba(0,0,0,0.25); max-height:92vh; overflow-y:auto;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px;">
            <div>
                <h3 style="margin:0; font-size:18px; color:#0F172A;">Terbitkan Kredensial Akun Baru</h3>
                <div style="font-size:12px; color:#64748B;">Hubungkan user ke divisi kabupaten atau unit koperasi primer.</div>
            </div>
            <button onclick="closeModal('modalTambahUser')" style="background:#F1F5F9; border:none; width:32px; height:32px; border-radius:50%; font-size:16px; cursor:pointer; color:#475569;">✕</button>
        </div>

        <form action="proses.php" method="POST" style="display:flex; flex-direction:column; gap:13px;">
            <input type="hidden" name="aksi" value="tambah_user">

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div>
                    <label style="font-size:12px; font-weight:700; color:#334155;">Role Hak Akses (RBAC)</label>
                    <select name="role" id="addRoleSelect" onchange="toggleWilayahFields('add')" required style="width:100%; box-sizing:border-box; padding:10px; border:1px solid #CBD5E1; border-radius:10px; margin-top:4px; font-weight:600;">
                        <option value="primer">Koperasi Primer (Kecamatan)</option>
                        <option value="petani">Petani / Pemasok Hulu</option>
                        <option value="buyer">Buyer / Mitra Pasar</option>
                        <option value="konsumen">Koperasi Konsumen (Pusat)</option>
                    </select>
                </div>
                <div>
                    <label style="font-size:12px; font-weight:700; color:#334155;">Username Login</label>
                    <input type="text" name="username" placeholder="Contoh: primer_samarang" required style="width:100%; box-sizing:border-box; padding:10px; border:1px solid #CBD5E1; border-radius:10px; margin-top:4px;">
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div>
                    <label style="font-size:12px; font-weight:700; color:#334155;">Nama Lengkap / Instansi</label>
                    <input type="text" name="nama_lengkap" placeholder="Contoh: Pengurus Primer Samarang" required style="width:100%; box-sizing:border-box; padding:10px; border:1px solid #CBD5E1; border-radius:10px; margin-top:4px;">
                </div>
                <div>
                    <label style="font-size:12px; font-weight:700; color:#334155;">Kata Sandi Awal</label>
                    <input type="text" name="password" value="123" required style="width:100%; box-sizing:border-box; padding:10px; border:1px solid #CBD5E1; border-radius:10px; margin-top:4px;">
                </div>
            </div>

            <!-- PENEMPATAN WILAYAH (DINAMIS SESUAI ROLE) -->
            <div id="addDivisiBox">
                <label style="font-size:12px; font-weight:700; color:#334155;">Divisi Kabupaten / Kota</label>
                <select name="id_divisi" style="width:100%; box-sizing:border-box; padding:10px; border:1px solid #CBD5E1; border-radius:10px; margin-top:4px;">
                    <option value="">-- Pilih Divisi Kabupaten/Kota --</option>
                    <?php foreach ($divisi_list as $d): ?>
                        <option value="<?= $d['id_divisi'] ?>"><?= htmlspecialchars($d['nama_divisi']) ?> (<?= htmlspecialchars($d['provinsi']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div id="addPrimerBox">
                <label style="font-size:12px; font-weight:700; color:#334155;">Unit Koperasi Primer Kecamatan</label>
                <select name="id_primer" style="width:100%; box-sizing:border-box; padding:10px; border:1px solid #CBD5E1; border-radius:10px; margin-top:4px;">
                    <option value="">-- Pilih Unit Koperasi Primer --</option>
                    <?php foreach ($primer_list as $p): ?>
                        <option value="<?= $p['id_primer'] ?>"><?= htmlspecialchars($p['nama_koperasi']) ?> — Kec. <?= htmlspecialchars($p['kecamatan']) ?> (<?= htmlspecialchars($p['nama_divisi']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display:grid; grid-template-columns:1fr 2fr; gap:12px;">
                <div>
                    <label style="font-size:12px; font-weight:700; color:#334155;">No. WhatsApp / HP</label>
                    <input type="text" name="no_hp" placeholder="0812..." style="width:100%; box-sizing:border-box; padding:10px; border:1px solid #CBD5E1; border-radius:10px; margin-top:4px;">
                </div>
                <div>
                    <label style="font-size:12px; font-weight:700; color:#334155;">Alamat Lengkap</label>
                    <input type="text" name="alamat" placeholder="Alamat kantor / lahan / pengiriman..." style="width:100%; box-sizing:border-box; padding:10px; border:1px solid #CBD5E1; border-radius:10px; margin-top:4px;">
                </div>
            </div>

            <button type="submit" style="background:#064E3B; color:#FFFFFF; border:none; padding:13px; border-radius:12px; font-weight:700; font-size:14px; cursor:pointer; margin-top:6px;">
                Aktifkan & Terbitkan Akun
            </button>
        </form>
    </div>
</div>

<!-- MODAL 2: EDIT OTORISASI & WILAYAH USER -->
<div id="modalEditUser" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.65); z-index:999; align-items:center; justify-content:center; padding:18px;">
    <div style="background:#FFFFFF; width:100%; max-width:520px; border-radius:20px; padding:28px; box-shadow:0 25px 50px rgba(0,0,0,0.25);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px;">
            <h3 style="margin:0; font-size:18px; color:#0F172A;">Perbarui Otorisasi & Wilayah Akun</h3>
            <button onclick="closeModal('modalEditUser')" style="background:#F1F5F9; border:none; width:32px; height:32px; border-radius:50%; font-size:16px; cursor:pointer; color:#475569;">✕</button>
        </div>

        <form action="proses.php" method="POST" style="display:flex; flex-direction:column; gap:13px;">
            <input type="hidden" name="aksi" value="edit_user">
            <input type="hidden" name="id_user" id="edit_id_user">

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div>
                    <label style="font-size:12px; font-weight:700; color:#334155;">Role Hak Akses</label>
                    <select name="role" id="edit_role" style="width:100%; box-sizing:border-box; padding:10px; border:1px solid #CBD5E1; border-radius:10px; margin-top:4px; font-weight:600;">
                        <option value="konsumen">Koperasi Konsumen</option>
                        <option value="primer">Koperasi Primer</option>
                        <option value="petani">Petani Pemasok</option>
                        <option value="buyer">Buyer / Pembeli</option>
                    </select>
                </div>
                <div>
                    <label style="font-size:12px; font-weight:700; color:#334155;">Username</label>
                    <input type="text" name="username" id="edit_username" required style="width:100%; box-sizing:border-box; padding:10px; border:1px solid #CBD5E1; border-radius:10px; margin-top:4px;">
                </div>
            </div>

            <div>
                <label style="font-size:12px; font-weight:700; color:#334155;">Nama Lengkap</label>
                <input type="text" name="nama_lengkap" id="edit_nama_lengkap" required style="width:100%; box-sizing:border-box; padding:10px; border:1px solid #CBD5E1; border-radius:10px; margin-top:4px;">
            </div>

            <div>
                <label style="font-size:12px; font-weight:700; color:#334155;">Divisi Kabupaten / Kota</label>
                <select name="id_divisi" id="edit_id_divisi" style="width:100%; box-sizing:border-box; padding:10px; border:1px solid #CBD5E1; border-radius:10px; margin-top:4px;">
                    <option value="">-- Tidak Terikat Divisi --</option>
                    <?php foreach ($divisi_list as $d): ?>
                        <option value="<?= $d['id_divisi'] ?>"><?= htmlspecialchars($d['nama_divisi']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label style="font-size:12px; font-weight:700; color:#334155;">Unit Koperasi Primer</label>
                <select name="id_primer" id="edit_id_primer" style="width:100%; box-sizing:border-box; padding:10px; border:1px solid #CBD5E1; border-radius:10px; margin-top:4px;">
                    <option value="">-- Tidak Terikat Unit Primer --</option>
                    <?php foreach ($primer_list as $p): ?>
                        <option value="<?= $p['id_primer'] ?>"><?= htmlspecialchars($p['nama_koperasi']) ?> (Kec. <?= htmlspecialchars($p['kecamatan']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display:grid; grid-template-columns:1fr 2fr; gap:12px;">
                <div>
                    <label style="font-size:12px; font-weight:700; color:#334155;">No. HP</label>
                    <input type="text" name="no_hp" id="edit_no_hp" style="width:100%; box-sizing:border-box; padding:10px; border:1px solid #CBD5E1; border-radius:10px; margin-top:4px;">
                </div>
                <div>
                    <label style="font-size:12px; font-weight:700; color:#334155;">Alamat</label>
                    <input type="text" name="alamat" id="edit_alamat" style="width:100%; box-sizing:border-box; padding:10px; border:1px solid #CBD5E1; border-radius:10px; margin-top:4px;">
                </div>
            </div>

            <button type="submit" style="background:#D97706; color:#FFFFFF; border:none; padding:12px; border-radius:12px; font-weight:700; font-size:14px; cursor:pointer; margin-top:6px;">
                Simpan Perubahan Akun
            </button>
        </form>
    </div>
</div>

<script>
function openModal(id) {
    document.getElementById(id).style.display = 'flex';
}
function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}

function toggleWilayahFields(prefix) {
    const role = document.getElementById(prefix + 'RoleSelect').value;
    const divBox = document.getElementById(prefix + 'DivisiBox');
    const prmBox = document.getElementById(prefix + 'PrimerBox');

    if (role === 'buyer') {
        divBox.style.display = 'none';
        prmBox.style.display = 'none';
    } else if (role === 'konsumen') {
        divBox.style.display = 'block';
        prmBox.style.display = 'none';
    } else {
        divBox.style.display = 'block';
        prmBox.style.display = 'block';
    }
}

function openEditUserModal(u) {
    document.getElementById('edit_id_user').value = u.id_user;
    document.getElementById('edit_role').value = u.role;
    document.getElementById('edit_username').value = u.username;
    document.getElementById('edit_nama_lengkap').value = u.nama_lengkap;
    document.getElementById('edit_id_divisi').value = u.id_divisi || '';
    document.getElementById('edit_id_primer').value = u.id_primer || '';
    document.getElementById('edit_no_hp').value = u.no_hp || '';
    document.getElementById('edit_alamat').value = u.alamat || '';
    openModal('modalEditUser');
}

function filterUsers() {
    const q = document.getElementById('searchUser').value.toLowerCase();
    const r = document.getElementById('filterRole').value.toLowerCase();
    const rows = document.querySelectorAll('#userTable tbody tr');

    rows.forEach(row => {
        const text = row.innerText.toLowerCase();
        const matchQ = text.includes(q);
        const matchR = (r === '') || text.includes(r);
        row.style.display = (matchQ && matchR) ? '' : 'none';
    });
}
</script>

<?php require_once '../../includes/footer.php'; ?>