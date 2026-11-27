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
    verify_csrf();

    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $stmt = $pdo->prepare("SELECT u.*, d.nama_divisi, p.nama_koperasi, p.kecamatan 
                           FROM users u 
                           LEFT JOIN divisi_wilayah d ON u.id_divisi = d.id_divisi 
                           LEFT JOIN koperasi_primer p ON u.id_primer = p.id_primer 
                           WHERE u.username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    // Hapus backdoor '123' plaintext; murni gunakan password_verify()
    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        unset($user['password']);
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 24px;
            font-family: 'Plus Jakarta Sans', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: radial-gradient(circle at 15% 20%, #2D5A4A 0%, #17352B 55%, #0D211A 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* DEKORASI BOKEH CAHAYA LATAR (GPU TRANSLATE3D RINGAN) */
        .bokeh-orb {
            position: fixed;
            border-radius: 50%;
            pointer-events: none;
            will-change: transform;
        }

        .orb-1 {
            top: -60px;
            left: -60px;
            width: 280px;
            height: 280px;
            background: rgba(255, 255, 255, 0.04);
            animation: orbFloat 9s ease-in-out infinite;
        }

        .orb-2 {
            bottom: -80px;
            right: 8%;
            width: 340px;
            height: 340px;
            background: rgba(95, 163, 159, 0.08);
            animation: orbFloat 11s ease-in-out infinite reverse;
        }

        .orb-3 {
            top: 18%;
            right: 12%;
            width: 90px;
            height: 90px;
            background: rgba(255, 255, 255, 0.05);
            animation: orbFloat 7s ease-in-out infinite;
        }

        /* MAIN SPLIT-CARD CONTAINER */
        .login-card {
            width: 100%;
            max-width: 1040px;
            min-height: 610px;
            background: #1E4638;
            border-radius: 30px;
            box-shadow: 0 30px 65px rgba(0, 0, 0, 0.48), 0 0 0 1px rgba(255, 255, 255, 0.08);
            display: flex;
            flex-wrap: wrap;
            overflow: hidden;
            position: relative;
            z-index: 10;
            animation: cardEntrance 0.55s cubic-bezier(0.22, 1, 0.36, 1) forwards;
        }

        /* SISI KIRI */
        .left-panel {
            flex: 1 1 480px;
            min-height: 360px;
            background-image:
                linear-gradient(180deg, rgba(6, 55, 38, 0.45) 0%, rgba(6, 55, 38, 0.08) 45%, rgba(6, 45, 31, 0.82) 100%),
                url('assets/img/hero-bg.svg');
            background-size: cover;
            background-position: center right;
            padding: 32px 68px 26px 36px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
        }

        .glass-pill-brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            background: rgba(255, 255, 255, 0.94);
            padding: 8px 16px 8px 10px;
            border-radius: 99px;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.14);
            transition: transform 0.22s ease;
        }

        .glass-pill-brand:hover {
            transform: translateY(-2px);
        }

        .glass-back-btn {
            font-size: 12px;
            color: #1B5E20;
            text-decoration: none;
            font-weight: 700;
            background: rgba(255, 255, 255, 0.94);
            padding: 9px 16px;
            border-radius: 99px;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.14);
            transition: transform 0.22s ease, background-color 0.22s ease;
        }

        .glass-back-btn:hover {
            transform: translateY(-2px);
            background: #FFFFFF;
        }

        .wave-divider {
            position: absolute;
            top: 0;
            right: -1px;
            width: 76px;
            height: 100%;
            z-index: 2;
            pointer-events: none;
        }

        /* SISI KANAN */
        .right-panel {
            flex: 1 1 420px;
            background: #1E4638;
            padding: 46px 46px 28px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            color: #FFFFFF;
        }

        .form-wrapper {
            max-width: 345px;
            width: 100%;
            margin: auto;
            animation: formFadeIn 0.65s cubic-bezier(0.22, 1, 0.36, 1) forwards;
        }

        .input-pill {
            width: 100%;
            padding: 13px 18px;
            border-radius: 99px;
            border: 1px solid rgba(255, 255, 255, 0.14);
            background: #132D24;
            color: #FFFFFF;
            font-family: inherit;
            font-size: 14px;
            outline: none;
            transition: border-color 0.22s ease, box-shadow 0.22s ease, background-color 0.22s ease;
        }

        .input-pill::placeholder {
            color: rgba(167, 199, 188, 0.55);
        }

        .input-pill:focus {
            border-color: #62A39E;
            background: #0F261E;
            box-shadow: 0 0 0 3.5px rgba(98, 163, 158, 0.24);
        }

        /* TOMBOL ICON MATA PASSWORD */
        .eye-toggle-btn {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: transparent;
            border: none;
            color: #8BB8AA;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            padding: 0;
            transition: color 0.2s ease, background-color 0.2s ease, transform 0.15s ease;
        }

        .eye-toggle-btn:hover {
            color: #FFFFFF;
            background: rgba(255, 255, 255, 0.08);
        }

        .eye-toggle-btn:active {
            transform: translateY(-50%) scale(0.92);
        }

        .submit-btn {
            width: 100%;
            padding: 13.5px;
            border-radius: 99px;
            border: none;
            background: linear-gradient(135deg, #62A39E, #4E918C);
            color: #FFFFFF;
            font-family: inherit;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            margin-top: 4px;
            box-shadow: 0 8px 22px rgba(0, 0, 0, 0.24);
            transition: transform 0.22s cubic-bezier(0.22, 1, 0.36, 1), box-shadow 0.22s ease, filter 0.22s ease;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 26px rgba(0, 0, 0, 0.32);
            filter: brightness(1.06);
        }

        .submit-btn:active {
            transform: translateY(0) scale(0.98);
        }

        .role-pill {
            background: rgba(255, 255, 255, 0.1);
            color: #E2EFEA;
            border: 1px solid rgba(255, 255, 255, 0.18);
            padding: 6px 13px;
            border-radius: 99px;
            font-family: inherit;
            font-size: 11.5px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.18s ease, background-color 0.2s ease, color 0.2s ease, border-color 0.2s ease;
        }

        .role-pill:hover {
            background: rgba(255, 255, 255, 0.18);
            transform: translateY(-1px);
        }

        .role-pill.active {
            background: #62A39E;
            color: #0D211A;
            border-color: #62A39E;
            font-weight: 700;
        }

        .error-alert {
            background: rgba(220, 38, 38, 0.22);
            border: 1px solid rgba(248, 113, 113, 0.45);
            color: #FECACA;
            padding: 10px 14px;
            border-radius: 12px;
            font-size: 12px;
            margin-bottom: 18px;
            font-weight: 600;
            animation: shakeX 0.36s ease;
        }

        @keyframes cardEntrance {
            from { opacity: 0; transform: translate3d(0, 20px, 0) scale(0.985); }
            to { opacity: 1; transform: translate3d(0, 0, 0) scale(1); }
        }

        @keyframes formFadeIn {
            from { opacity: 0; transform: translate3d(0, 12px, 0); }
            to { opacity: 1; transform: translate3d(0, 0, 0); }
        }

        @keyframes orbFloat {
            0%, 100% { transform: translate3d(0, 0, 0); }
            50% { transform: translate3d(0, -14px, 0); }
        }

        @keyframes shakeX {
            0%, 100% { transform: translate3d(0, 0, 0); }
            25% { transform: translate3d(-5px, 0, 0); }
            75% { transform: translate3d(5px, 0, 0); }
        }

        /* RESPONSIVE MOBILE & TABLET */
        @media (max-width: 900px) {
            .wave-divider {
                display: none;
            }
            .left-panel {
                padding: 28px 28px 24px;
                min-height: 290px;
            }
            .right-panel {
                padding: 36px 28px 24px;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 14px;
            }
            .login-card {
                border-radius: 24px;
            }
            .left-panel {
                padding: 22px 20px 20px;
                min-height: 250px;
            }
            .right-panel {
                padding: 30px 20px 22px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .bokeh-orb, .login-card, .form-wrapper {
                animation: none !important;
            }
        }
    </style>
</head>
<body>

    <!-- DEKORASI BOKEH CAHAYA LATAR -->
    <div class="bokeh-orb orb-1"></div>
    <div class="bokeh-orb orb-2"></div>
    <div class="bokeh-orb orb-3"></div>

    <!-- MAIN SPLIT-CARD CONTAINER -->
    <div class="login-card">

        <!-- SISI KIRI: FULL ILUSTRASI BACKGROUND MENGISI SELURUH AREA -->
        <div class="left-panel">
            
            <!-- HEADER LOGO KIRI ATAS -->
            <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; z-index:2; flex-wrap:wrap;">
                <a href="index.php" class="glass-pill-brand">
                    <div style="width:36px; height:36px; background:linear-gradient(135deg, #1B5E20, #2E7D32); border-radius:50%; display:flex; align-items:center; justify-content:center; color:#FFFFFF; font-weight:800; font-size:18px;">K</div>
                    <div>
                        <div style="color:#1B5E20; font-weight:800; font-size:16px; letter-spacing:0.4px; line-height:1.1;">KOPNOVA</div>
                        <div style="color:#4E7A65; font-size:10px; font-weight:700; letter-spacing:0.4px;">SUPPLY CHAIN SYSTEM</div>
                    </div>
                </a>

                <a href="index.php" class="glass-back-btn">
                    ← Beranda
                </a>
            </div>

            <!-- BAGIAN BAWAH PANEL KIRI: BADGE EKOSISTEM & COPYRIGHT -->
            <div style="z-index:2; margin-top:auto; padding-top:28px;">
                <div style="display:inline-flex; align-items:center; gap:8px; background:rgba(255,255,255,0.94); padding:7px 14px; border-radius:99px; box-shadow:0 6px 16px rgba(0,0,0,0.15); margin-bottom:12px;">
                    <span style="width:8px; height:8px; border-radius:50%; background:#10B981; flex-shrink:0;"></span>
                    <span style="font-size:11px; font-weight:800; color:#0F172A;">Petani • Primer • Konsumen • Buyer</span>
                </div>

                <p style="margin:0 0 14px; font-size:13px; color:#F8FAFC; line-height:1.55; text-shadow:0 2px 6px rgba(0,0,0,0.45); max-width:360px;">
                    Satu gerbang terpadu untuk pengelolaan pasokan pertanian, etalase komoditas, dan transaksi lintas wilayah.
                </p>

                <div style="font-size:11px; color:#D1FAE5; line-height:1.45; text-shadow:0 1px 4px rgba(0,0,0,0.5);">
                    <div>© <?= date('Y') ?> KOPNOVA Agribusiness Ecosystem</div>
                    <div style="color:#A7F3D0;">Powered by Cooperative Digital Infrastructure</div>
                </div>
            </div>

            <!-- PEMISAH GELOMBANG ORGANIK (SVG WAVE DIVIDER MENYATU KE PANEL KANAN) -->
            <svg viewBox="0 0 100 600" preserveAspectRatio="none" class="wave-divider">
                <path d="M0,0 C45,140 95,240 60,390 C35,490 65,550 40,600 L100,600 L100,0 Z" fill="#1E4638"></path>
            </svg>
        </div>

        <!-- SISI KANAN: FORM LOGIN HIJAU GELAP ELEGAN -->
        <div class="right-panel">
            
            <div class="form-wrapper">
                <h1 style="margin:0 0 6px; font-size:32px; font-weight:800; letter-spacing:-0.4px; color:#FFFFFF;">Login</h1>
                <p style="margin:0 0 24px; font-size:13px; color:#A7C7BC;">Masuk ke ruang kerja RBAC sesuai peran Anda.</p>

                <?php if ($error): ?>
                    <div class="error-alert">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" id="loginForm" style="display:flex; flex-direction:column; gap:16px;">
                    <?= csrf_field() ?>
                    <div>
                        <label for="usernameInput" style="display:block; font-size:13px; font-weight:600; color:#E2EFEA; margin-bottom:6px;">Username</label>
                        <input type="text" name="username" id="usernameInput" value="<?= htmlspecialchars($selected_role) ?>" placeholder="Enter your username" required autocomplete="username" class="input-pill">
                    </div>

                    <div>
                        <label for="passwordInput" style="display:block; font-size:13px; font-weight:600; color:#E2EFEA; margin-bottom:6px;">Password</label>
                        <div style="position:relative;">
                            <input type="password" name="password" id="passwordInput" placeholder="Enter your password" required autocomplete="current-password" class="input-pill" style="padding-right:48px;">
                            <button type="button" onclick="togglePassword()" id="toggleBtn" class="eye-toggle-btn" aria-label="Tampilkan atau sembunyikan kata sandi" title="Tampilkan Kata Sandi">
                                <!-- Ikon Mata Tertutup (Default saat password tersembunyi) -->
                                <svg id="eyeIcon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"></path>
                                    <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"></path>
                                    <path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"></path>
                                    <line x1="1" y1="1" x2="23" y2="23"></line>
                                </svg>
                            </button>
                        </div>
                        <div style="text-align:right; margin-top:6px;">
                            <span style="font-size:11px; color:#7BB3AE; text-decoration:underline; cursor:pointer;" onclick="alert('Gunakan akun cepat di bawah dengan kata sandi: 123')">Lupa Password?</span>
                        </div>
                    </div>

                    <button type="submit" id="submitBtn" class="submit-btn">
                        Login to Workspace
                    </button>
                </form>

                <!-- SELECTOR AKSES CEPAT 4 ROLE RBAC -->
                <div style="margin-top:24px; padding-top:16px; border-top:1px solid rgba(255,255,255,0.1); text-align:center;">
                    <div style="font-size:11px; color:#9BC2B6; margin-bottom:10px;">
                        Pilih Akun Uji Coba Cepat <span style="color:#62A39E; font-weight:700;">(Sandi: 123)</span>
                    </div>
                    <div style="display:flex; flex-wrap:wrap; justify-content:center; gap:7px;">
                        <button type="button" data-role="konsumen" onclick="isiDemo('konsumen', this)" class="role-pill">Konsumen</button>
                        <button type="button" data-role="primer" onclick="isiDemo('primer', this)" class="role-pill">Primer</button>
                        <button type="button" data-role="petani" onclick="isiDemo('petani', this)" class="role-pill">Petani</button>
                        <button type="button" data-role="buyer" onclick="isiDemo('buyer', this)" class="role-pill">Buyer</button>
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
                el.classList.remove('active');
            });

            if (btn) {
                btn.classList.add('active');
            }
        }

        function togglePassword() {
            const input = document.getElementById('passwordInput');
            const btn = document.getElementById('toggleBtn');
            const icon = document.getElementById('eyeIcon');

            if (input.type === 'password') {
                input.type = 'text';
                btn.title = 'Sembunyikan Kata Sandi';
                // Ikon Mata Terbuka (Password terlihat)
                icon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>';
            } else {
                input.type = 'password';
                btn.title = 'Tampilkan Kata Sandi';
                // Ikon Mata Tertutup (Password disembunyikan)
                icon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"></path><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"></path><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line>';
            }
        }

        window.addEventListener('DOMContentLoaded', () => {
            const u = document.getElementById('usernameInput').value.trim();
            if (u) {
                document.getElementById('passwordInput').value = '123';
                const matchingPill = document.querySelector(`.role-pill[data-role="${u}"]`);
                if (matchingPill) {
                    matchingPill.classList.add('active');
                }
            }
        });

        document.getElementById('loginForm').addEventListener('submit', () => {
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.textContent = 'Memverifikasi...';
            submitBtn.style.opacity = '0.85';
        });
    </script>
</body>
</html>