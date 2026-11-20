<?php
require_once 'config/database.php';

// Jika sudah login, langsung arahkan ke dashboard
if (isset($_SESSION['user'])) {
    header("Location: modules/dashboard/index.php");
    exit;
}

$error = $_GET['error'] ?? '';
$selected_role = $_GET['role'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    $stmt = $pdo->prepare("SELECT u.*, d.nama_divisi, p.nama_koperasi, p.kecamatan 
                           FROM users u 
                           LEFT JOIN divisi_wilayah d ON u.id_divisi = d.id_divisi 
                           LEFT JOIN koperasi_primer p ON u.id_primer = p.id_primer 
                           WHERE u.username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && ($password === '123' || password_verify($password, $user['password']))) {
        $_SESSION['user'] = $user;
        header("Location: modules/dashboard/index.php");
        exit;
    } else {
        $error = "Username atau kata sandi tidak sesuai. Silakan periksa kembali.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk Sistem | KOPNOVA Supply Chain Portal</title>
</head>
<body style="margin:0; padding:24px; font-family:'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background:radial-gradient(circle at 15% 20%, #2D5A4A 0%, #17352B 55%, #0D211A 100%); min-height:100vh; display:flex; align-items:center; justify-content:center; box-sizing:border-box; position:relative; overflow-x:hidden;">

    <!-- DEKORASI BOKEH CAHAYA LATAR -->
    <div style="position:fixed; top:-60px; left:-60px; width:280px; height:280px; border-radius:50%; background:rgba(255,255,255,0.04); pointer-events:none;"></div>
    <div style="position:fixed; bottom:-80px; right:8%; width:340px; height:340px; border-radius:50%; background:rgba(95,163,159,0.08); pointer-events:none;"></div>
    <div style="position:fixed; top:18%; right:12%; width:90px; height:90px; border-radius:50%; background:rgba(255,255,255,0.05); pointer-events:none;"></div>

    <!-- MAIN SPLIT-CARD CONTAINER -->
    <div style="width:100%; max-width:1040px; min-height:610px; background:#1E4638; border-radius:30px; box-shadow:0 30px 60px rgba(0,0,0,0.45); display:flex; flex-wrap:wrap; overflow:hidden; position:relative; z-index:10;">

        <!-- SISI KIRI: FULL ILUSTRASI BACKGROUND MENGISI SELURUH AREA -->
        <div style="flex:1 1 480px; min-height:360px; background-image:linear-gradient(180deg, rgba(6,55,38,0.45) 0%, rgba(6,55,38,0.08) 45%, rgba(6,45,31,0.78) 100%), url('assets/img/hero-bg.svg'); background-size:cover; background-position:center right; padding:32px 68px 26px 36px; display:flex; flex-direction:column; justify-content:space-between; position:relative; box-sizing:border-box;">
            
            <!-- HEADER LOGO KIRI ATAS (GLASS PILL AGAR KONTRAS DI ATAS GAMBAR) -->
            <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; z-index:2; flex-wrap:wrap;">
                <a href="index.php" style="display:inline-flex; align-items:center; gap:10px; text-decoration:none; background:rgba(255,255,255,0.92); backdrop-filter:blur(8px); padding:8px 16px 8px 10px; border-radius:99px; box-shadow:0 6px 18px rgba(0,0,0,0.12);">
                    <div style="width:36px; height:36px; background:linear-gradient(135deg, #1B5E20, #2E7D32); border-radius:50%; display:flex; align-items:center; justify-content:center; color:#FFFFFF; font-weight:900; font-size:18px;">K</div>
                    <div>
                        <div style="color:#1B5E20; font-weight:900; font-size:16px; letter-spacing:0.4px; line-height:1.1;">KOPNOVA</div>
                        <div style="color:#4E7A65; font-size:10px; font-weight:700; letter-spacing:0.4px;">SUPPLY CHAIN SYSTEM</div>
                    </div>
                </a>

                <a href="index.php" style="font-size:12px; color:#1B5E20; text-decoration:none; font-weight:700; background:rgba(255,255,255,0.92); backdrop-filter:blur(8px); padding:8px 15px; border-radius:99px; box-shadow:0 6px 18px rgba(0,0,0,0.12);">
                    ← Beranda
                </a>
            </div>

            <!-- BAGIAN BAWAH PANEL KIRI: BADGE EKOSISTEM & COPYRIGHT -->
            <div style="z-index:2; margin-top:auto;">
                <div style="display:inline-flex; align-items:center; gap:8px; background:rgba(255,255,255,0.92); backdrop-filter:blur(8px); padding:7px 14px; border-radius:99px; box-shadow:0 6px 16px rgba(0,0,0,0.15); margin-bottom:12px;">
                    <span style="width:8px; height:8px; border-radius:50%; background:#10B981; flex-shrink:0;"></span>
                    <span style="font-size:11px; font-weight:800; color:#0F172A;">Petani • Primer • Konsumen • Buyer</span>
                </div>

                <p style="margin:0 0 14px; font-size:13px; color:#F8FAFC; line-height:1.5; text-shadow:0 2px 6px rgba(0,0,0,0.45); max-width:360px;">
                    Satu gerbang terpadu untuk pengelolaan pasokan pertanian, etalase komoditas, dan transaksi lintas wilayah.
                </p>

                <div style="font-size:11px; color:#D1FAE5; line-height:1.4; text-shadow:0 1px 4px rgba(0,0,0,0.5);">
                    <div>© <?= date('Y') ?> KOPNOVA Agribusiness Ecosystem</div>
                    <div style="color:#A7F3D0;">Powered by Cooperative Digital Infrastructure</div>
                </div>
            </div>

            <!-- PEMISAH GELOMBANG ORGANIK (SVG WAVE DIVIDER MENYATU KE PANEL KANAN) -->
            <svg viewBox="0 0 100 600" preserveAspectRatio="none" style="position:absolute; top:0; right:-1px; width:76px; height:100%; z-index:2; pointer-events:none;">
                <path d="M0,0 C45,140 95,240 60,390 C35,490 65,550 40,600 L100,600 L100,0 Z" fill="#1E4638"></path>
            </svg>
        </div>

        <!-- SISI KANAN: FORM LOGIN HIJAU GELAP ELEGAN -->
        <div style="flex:1 1 420px; background:#1E4638; padding:48px 46px 28px; display:flex; flex-direction:column; justify-content:space-between; color:#FFFFFF; box-sizing:border-box;">
            
            <div style="max-width:340px; width:100%; margin:auto;">
                <h1 style="margin:0 0 6px; font-size:32px; font-weight:700; letter-spacing:-0.3px; color:#FFFFFF;">Login</h1>
                <p style="margin:0 0 24px; font-size:13px; color:#A7C7BC;">Masuk ke ruang kerja RBAC sesuai peran Anda.</p>

                <?php if ($error): ?>
                    <div style="background:rgba(220,38,38,0.22); border:1px solid rgba(248,113,113,0.45); color:#FECACA; padding:10px 14px; border-radius:12px; font-size:12px; margin-bottom:18px; font-weight:600;">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" style="display:flex; flex-direction:column; gap:16px;">
                    <div>
                        <label style="display:block; font-size:13px; font-weight:600; color:#E2EFEA; margin-bottom:6px;">Username</label>
                        <input type="text" name="username" id="usernameInput" value="<?= htmlspecialchars($selected_role) ?>" placeholder="Enter your username" required
                               style="width:100%; box-sizing:border-box; padding:13px 18px; border-radius:99px; border:1px solid rgba(255,255,255,0.12); background:#132D24; color:#FFFFFF; font-size:14px; outline:none;">
                    </div>

                    <div>
                        <label style="display:block; font-size:13px; font-weight:600; color:#E2EFEA; margin-bottom:6px;">Password</label>
                        <div style="position:relative;">
                            <input type="password" name="password" id="passwordInput" placeholder="Enter your password" required
                                   style="width:100%; box-sizing:border-box; padding:13px 46px 13px 18px; border-radius:99px; border:1px solid rgba(255,255,255,0.12); background:#132D24; color:#FFFFFF; font-size:14px; outline:none;">
                            <button type="button" onclick="togglePassword()" id="toggleBtn" style="position:absolute; right:14px; top:50%; transform:translateY(-50%); background:none; border:none; color:#8BB8AA; font-size:11px; font-weight:700; cursor:pointer;">
                                Lihat
                            </button>
                        </div>
                        <div style="text-align:right; margin-top:6px;">
                            <span style="font-size:11px; color:#7BB3AE; text-decoration:underline; cursor:pointer;" onclick="alert('Gunakan akun cepat di bawah dengan kata sandi: 123')">Lupa Password?</span>
                        </div>
                    </div>

                    <button type="submit"
                            style="width:100%; padding:13px; border-radius:99px; border:none; background:#62A39E; color:#FFFFFF; font-weight:700; font-size:15px; cursor:pointer; margin-top:4px; box-shadow:0 8px 20px rgba(0,0,0,0.2);">
                        Login to Workspace
                    </button>
                </form>

                <!-- SELECTOR AKSES CEPAT 4 ROLE RBAC -->
                <div style="margin-top:24px; padding-top:16px; border-top:1px solid rgba(255,255,255,0.1); text-align:center;">
                    <div style="font-size:11px; color:#9BC2B6; margin-bottom:10px;">
                        Pilih Akun Uji Coba Cepat <span style="color:#62A39E; font-weight:700;">(Sandi: 123)</span>
                    </div>
                    <div style="display:flex; flex-wrap:wrap; justify-content:center; gap:6px;">
                        <button type="button" onclick="isiDemo('konsumen', this)" class="role-pill" style="background:rgba(255,255,255,0.1); color:#E2EFEA; border:1px solid rgba(255,255,255,0.18); padding:6px 12px; border-radius:99px; font-size:11px; font-weight:600; cursor:pointer;">Konsumen</button>
                        <button type="button" onclick="isiDemo('primer', this)" class="role-pill" style="background:rgba(255,255,255,0.1); color:#E2EFEA; border:1px solid rgba(255,255,255,0.18); padding:6px 12px; border-radius:99px; font-size:11px; font-weight:600; cursor:pointer;">Primer</button>
                        <button type="button" onclick="isiDemo('petani', this)" class="role-pill" style="background:rgba(255,255,255,0.1); color:#E2EFEA; border:1px solid rgba(255,255,255,0.18); padding:6px 12px; border-radius:99px; font-size:11px; font-weight:600; cursor:pointer;">Petani</button>
                        <button type="button" onclick="isiDemo('buyer', this)" class="role-pill" style="background:rgba(255,255,255,0.1); color:#E2EFEA; border:1px solid rgba(255,255,255,0.18); padding:6px 12px; border-radius:99px; font-size:11px; font-weight:600; cursor:pointer;">Buyer</button>
                    </div>
                </div>
            </div>

            <!-- FOOTER KANAN BAWAH -->
            <div style="display:flex; justify-content:space-between; align-items:flex-end; flex-wrap:wrap; gap:10px; font-size:11px; color:#8BB8AA; margin-top:24px;">
                <a href="index.php" style="color:#7BB3AE; text-decoration:underline;">Syarat & Ketentuan</a>
                <div style="text-align:right;">
                    <div>Butuh bantuan akses? Hubungi</div>
                    <span style="color:#7BB3AE; text-decoration:underline;">admin@kopnova.id</span>
                </div>
            </div>
        </div>

    </div>

    <script>
        function isiDemo(roleUser, btn) {
            document.getElementById('usernameInput').value = roleUser;
            document.getElementById('passwordInput').value = '123';

            document.querySelectorAll('.role-pill').forEach(el => {
                el.style.background = 'rgba(255,255,255,0.1)';
                el.style.color = '#E2EFEA';
                el.style.borderColor = 'rgba(255,255,255,0.18)';
            });

            if (btn) {
                btn.style.background = '#62A39E';
                btn.style.color = '#0D211A';
                btn.style.borderColor = '#62A39E';
            }
        }

        function togglePassword() {
            const input = document.getElementById('passwordInput');
            const btn = document.getElementById('toggleBtn');
            if (input.type === 'password') {
                input.type = 'text';
                btn.textContent = 'Sembunyi';
            } else {
                input.type = 'password';
                btn.textContent = 'Lihat';
            }
        }

        window.addEventListener('DOMContentLoaded', () => {
            const u = document.getElementById('usernameInput').value;
            if (u) {
                document.getElementById('passwordInput').value = '123';
            }
        });
    </script>
</body>
</html>