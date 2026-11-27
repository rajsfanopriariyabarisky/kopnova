<?php
if (!isset($pdo)) {
    require_once __DIR__ . '/../config/database.php';
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user'])) {
    header("Location: " . BASE_URL . "/login.php");
    exit;
}

$user       = $_SESSION['user'];
$role       = $user['role'] ?? '';
$cart_count = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;

$role_labels = [
    'konsumen' => 'Koperasi Konsumen (Pusat)',
    'primer'   => 'Koperasi Primer Kecamatan',
    'petani'   => 'Petani / Pemasok Hulu',
    'buyer'    => 'Buyer / Mitra Pasar'
];
$role_badge = $role_labels[$role] ?? strtoupper($role);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KOPNOVA | <?= htmlspecialchars($role_badge) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --kop-forest-950: #042F24;
            --kop-forest-900: #064E3B;
            --kop-emerald-800: #1B5E20;
            --kop-emerald-700: #2E7D32;
            --kop-emerald-600: #059669;
            --kop-emerald-500: #10B981;
            --kop-emerald-100: #DCFCE7;
            --kop-emerald-50: #F0FDF4;
            --kop-canvas: #FAFBF7;
            --kop-slate-900: #0F172A;
            --kop-slate-700: #334155;
            --kop-slate-600: #475569;
            --kop-slate-500: #64748B;
            --kop-border: #E2E8F0;
            --kop-ease: 0.22s cubic-bezier(0.22, 1, 0.36, 1);
        }

        *, *::before, *::after {
            box-sizing: border-box;
        }

        html, body {
            margin: 0;
            padding: 0;
            font-family: 'Plus Jakarta Sans', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: var(--kop-canvas);
            color: var(--kop-slate-900);
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
        }

        body.kop-shell {
            display: flex;
            min-height: 100vh;
            width: 100%;
        }

        /* SIDEBAR UTAMA (DESKTOP) */
        #mainSidebar {
            width: 266px;
            height: 100vh;
            position: sticky;
            top: 0;
            background: linear-gradient(180deg, var(--kop-forest-950) 0%, #032219 100%);
            color: #FFFFFF;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            flex-shrink: 0;
            border-right: 1px solid rgba(255, 255, 255, 0.08);
            z-index: 60;
            will-change: transform;
        }

        .kop-sidebar-scroll {
            display: flex;
            flex-direction: column;
            flex: 1;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: rgba(110, 231, 183, 0.25) transparent;
        }
        .kop-sidebar-scroll::-webkit-scrollbar {
            width: 5px;
        }
        .kop-sidebar-scroll::-webkit-scrollbar-thumb {
            background: rgba(110, 231, 183, 0.25);
            border-radius: 99px;
        }

        .kop-nav-link {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 10px 13px;
            border-radius: 12px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            color: #CBD5E1;
            background: transparent;
            white-space: nowrap;
            transition: background-color var(--kop-ease), color var(--kop-ease), transform var(--kop-ease);
        }
        .kop-nav-link svg {
            flex-shrink: 0;
            width: 17px;
            height: 17px;
        }
        .kop-nav-link:hover {
            background: rgba(255, 255, 255, 0.08);
            color: #FFFFFF;
            transform: translateX(2px);
        }
        .kop-nav-link.active {
            font-weight: 700;
            color: #FFFFFF;
            background: linear-gradient(90deg, #10B981 0%, #059669 100%);
            box-shadow: 0 6px 16px rgba(16, 185, 129, 0.26);
        }
        .kop-nav-link.active:hover {
            transform: none;
        }

        .kop-sidebar-close {
            display: none;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.16);
            color: #FFFFFF;
            width: 34px;
            height: 34px;
            border-radius: 10px;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            flex-shrink: 0;
        }

        .kop-logout-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 11px;
            background: #DC2626;
            color: #FFFFFF;
            text-decoration: none;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 700;
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.28);
            transition: filter var(--kop-ease), transform var(--kop-ease);
        }
        .kop-logout-btn:hover {
            filter: brightness(1.06);
            transform: translateY(-1px);
        }

        /* MAIN WORKSPACE & STICKY TOPBAR */
        .kop-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
            background-color: var(--kop-canvas);
        }

        .kop-topbar {
            background: #FFFFFF;
            padding: 13px 28px;
            border-bottom: 1px solid var(--kop-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            position: sticky;
            top: 0;
            z-index: 40;
        }

        .kop-topbar-menu-btn {
            display: none;
            align-items: center;
            justify-content: center;
            gap: 7px;
            background: var(--kop-forest-900);
            color: #FFFFFF;
            border: none;
            padding: 9px 14px;
            border-radius: 11px;
            font-family: inherit;
            font-size: 12.5px;
            font-weight: 700;
            cursor: pointer;
            flex-shrink: 0;
            transition: background-color var(--kop-ease);
        }
        .kop-topbar-menu-btn:hover {
            background: var(--kop-forest-950);
        }

        .kop-topbar-link {
            background: #F8FAFC;
            border: 1px solid #CBD5E1;
            color: var(--kop-slate-700);
            text-decoration: none;
            padding: 7px 14px;
            border-radius: 99px;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
            transition: border-color var(--kop-ease), background-color var(--kop-ease), color var(--kop-ease);
        }
        .kop-topbar-link:hover {
            border-color: var(--kop-emerald-500);
            background: var(--kop-emerald-50);
            color: var(--kop-forest-900);
        }

        .kop-content-container {
            padding: 24px 28px 40px;
            flex: 1;
            max-width: 1380px;
            width: 100%;
            margin: 0 auto;
        }

        /* Sembunyikan tombol menu duplikat di dalam konten jika topbar sudah menyediakan */
        .kop-dashboard-wrap .mobile-nav-trigger {
            display: none !important;
        }

        /* ZERO-LAG BACKDROP MOBILE */
        #kopBackdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(4, 47, 36, 0.54);
            z-index: 50;
        }

        /* RESPONSIVE TABLET & MOBILE (<= 960px) */
        @media (max-width: 960px) {
            #mainSidebar {
                position: fixed;
                left: 0;
                top: 0;
                bottom: 0;
                transform: translateX(-105%);
                transition: transform 0.25s cubic-bezier(0.22, 1, 0.36, 1);
                box-shadow: 12px 0 32px rgba(0, 0, 0, 0.28);
            }
            #mainSidebar.open {
                transform: translateX(0);
            }
            .kop-sidebar-close {
                display: inline-flex;
            }
            .kop-topbar-menu-btn {
                display: inline-flex;
            }
            .kop-topbar {
                padding: 12px 18px;
            }
            .kop-content-container {
                padding: 18px 18px 32px;
            }
        }

        /* RESPONSIVE HP KECIL (<= 640px) */
        @media (max-width: 640px) {
            .kop-topbar {
                padding: 11px 14px;
                gap: 8px;
            }
            .kop-topbar-portal-link {
                display: none;
            }
            .kop-role-pill {
                font-size: 10.5px !important;
                padding: 5px 10px !important;
            }
            .kop-content-container {
                padding: 14px 14px 28px;
            }
        }
    </style>
</head>
<body class="kop-shell">