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
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KOPNOVA | Ekosistem Digital Rantai Pasok & Agribisnis Terpadu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --emerald-950: #042F24;
            --emerald-900: #064E3B;
            --emerald-800: #1B5E20;
            --emerald-700: #2E7D32;
            --emerald-600: #10B981;
            --emerald-100: #DCFCE7;
            --emerald-50: #F0FDF4;
            --sage-surface: #E8F0E2;
            --bg-page: #F2F5EE;
            --bg-canvas: #FAFBF7;
            --slate-900: #0F172A;
            --slate-700: #334155;
            --slate-600: #475569;
            --slate-500: #64748B;
            --border-light: #E2E8F0;
            --shadow-sm: 0 4px 14px rgba(6, 78, 59, 0.06);
            --shadow-md: 0 12px 30px rgba(6, 78, 59, 0.09);
            --shadow-lg: 0 22px 48px rgba(6, 78, 59, 0.14);
            --radius-xl: 28px;
            --radius-lg: 20px;
            --radius-md: 14px;
            --transition-fast: 0.22s cubic-bezier(0.22, 1, 0.36, 1);
            --transition-smooth: 0.38s cubic-bezier(0.22, 1, 0.36, 1);
        }

        *, *::before, *::after {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
            overflow-x: hidden;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Plus Jakarta Sans', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: var(--bg-page);
            color: var(--slate-900);
            line-height: 1.6;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        .page-shell {
            max-width: 1380px;
            margin: 0 auto;
            background: var(--bg-canvas);
            box-shadow: 0 0 50px rgba(15, 23, 42, 0.06);
            overflow-x: hidden;
        }

        /* ==================== 1. NAVBAR (ZERO-LAG STICKY) ==================== */
        .navbar-wrap {
            padding: 14px 3.5% 0;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .main-nav {
            background: rgba(255, 255, 255, 0.97);
            padding: 10px 20px;
            border-radius: 99px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 8px 24px rgba(6, 78, 59, 0.08);
            border: 1px solid rgba(226, 232, 240, 0.95);
            transition: box-shadow var(--transition-fast), transform var(--transition-fast);
        }

        .main-nav.scrolled {
            box-shadow: 0 12px 32px rgba(6, 78, 59, 0.13);
        }

        .brand-logo {
            display: flex;
            align-items: center;
            gap: 11px;
            text-decoration: none;
            flex-shrink: 0;
        }

        .brand-mark {
            width: 38px;
            height: 38px;
            background: linear-gradient(135deg, var(--emerald-800), var(--emerald-600));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #FFFFFF;
            font-weight: 800;
            font-size: 17px;
            box-shadow: 0 4px 12px rgba(46, 125, 50, 0.28);
            transition: transform var(--transition-fast);
        }

        .brand-logo:hover .brand-mark {
            transform: rotate(-6deg) scale(1.05);
        }

        .brand-text-wrap {
            display: flex;
            flex-direction: column;
        }

        .brand-name {
            color: var(--emerald-800);
            font-weight: 800;
            font-size: 19px;
            letter-spacing: -0.4px;
            line-height: 1.1;
        }

        .brand-sub {
            font-size: 9.5px;
            font-weight: 700;
            color: var(--slate-500);
            letter-spacing: 0.6px;
        }

        .desktop-menu {
            display: flex;
            gap: 28px;
            align-items: center;
        }

        .nav-link {
            color: var(--slate-600);
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            position: relative;
            padding: 6px 2px;
            transition: color var(--transition-fast);
        }

        .nav-link::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            width: 100%;
            height: 2px;
            background: var(--emerald-700);
            border-radius: 2px;
            transform: scaleX(0);
            transform-origin: right;
            transition: transform var(--transition-fast);
        }

        .nav-link:hover, .nav-link.active {
            color: var(--emerald-800);
            font-weight: 700;
        }

        .nav-link:hover::after, .nav-link.active::after {
            transform: scaleX(1);
            transform-origin: left;
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }

        .btn-shop-link {
            color: var(--emerald-800);
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 700;
            padding: 8px 14px;
            border-radius: 99px;
            transition: background-color var(--transition-fast);
        }

        .btn-shop-link:hover {
            background-color: var(--emerald-50);
        }

        .btn-primary-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            background: linear-gradient(135deg, var(--emerald-800), var(--emerald-700));
            color: #FFFFFF;
            padding: 10px 20px;
            border-radius: 99px;
            text-decoration: none;
            font-weight: 700;
            font-size: 13.5px;
            border: none;
            cursor: pointer;
            box-shadow: 0 6px 16px rgba(46, 125, 50, 0.25);
            transition: transform var(--transition-fast), box-shadow var(--transition-fast), filter var(--transition-fast);
            white-space: nowrap;
        }

        .btn-primary-pill:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 22px rgba(46, 125, 50, 0.34);
            filter: brightness(1.05);
        }

        .btn-primary-pill:active {
            transform: translateY(0) scale(0.98);
        }

        .mobile-menu-btn {
            display: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 1px solid #CBD5E1;
            background: #F8FAFC;
            color: var(--slate-900);
            align-items: center;
            justify-content: center;
            cursor: pointer;
            padding: 0;
            transition: background-color var(--transition-fast);
        }

        .mobile-menu-btn:hover {
            background: var(--emerald-50);
            border-color: var(--emerald-700);
            color: var(--emerald-800);
        }

        .mobile-dropdown {
            display: none;
            background: #FFFFFF;
            margin-top: 10px;
            padding: 18px 20px;
            border-radius: 22px;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.14);
            border: 1px solid var(--border-light);
            flex-direction: column;
            gap: 10px;
            animation: slideDownFade 0.24s cubic-bezier(0.22, 1, 0.36, 1) forwards;
        }

        .mobile-dropdown.open {
            display: flex;
        }

        .mobile-dropdown a {
            color: var(--slate-700);
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            padding: 8px 4px;
            border-bottom: 1px solid #F1F5F9;
        }

        .mobile-dropdown a.mobile-cta {
            background: var(--emerald-50);
            color: var(--emerald-800);
            text-align: center;
            font-weight: 800;
            padding: 11px;
            border-radius: 12px;
            border: 1px solid #BBF7D0;
            margin-top: 4px;
        }

        /* ==================== 2. HERO SECTION (2-COLUMN DEPTH) ==================== */
        .hero-section {
            margin: 16px 3.5% 0;
            border-radius: var(--radius-xl);
            overflow: hidden;
            position: relative;
            min-height: 540px;
            display: flex;
            align-items: center;
            background-color: var(--emerald-900);
            background-image:
                linear-gradient(110deg, rgba(4, 47, 36, 0.94) 0%, rgba(6, 78, 59, 0.85) 52%, rgba(6, 55, 38, 0.68) 100%),
                url('assets/img/hero-bg.svg');
            background-size: cover;
            background-position: center right;
            box-shadow: var(--shadow-lg);
        }

        .hero-grid {
            width: 100%;
            padding: 58px 5% 84px;
            display: grid;
            grid-template-columns: 1.18fr 0.82fr;
            gap: 36px;
            align-items: center;
            position: relative;
            z-index: 2;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.25);
            color: #ECFDF5;
            padding: 6px 15px;
            border-radius: 99px;
            font-size: 11.5px;
            font-weight: 700;
            letter-spacing: 0.6px;
            margin-bottom: 18px;
        }

        .pulse-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #4ADE80;
            display: inline-block;
            animation: pulseScale 2.2s infinite ease-in-out;
        }

        .hero-title {
            font-size: clamp(28px, 4vw, 48px);
            font-weight: 800;
            line-height: 1.14;
            color: #FFFFFF;
            margin: 0 0 16px;
            letter-spacing: -0.6px;
        }

        .hero-title span {
            color: #6EE7B7;
        }

        .hero-desc {
            font-size: 15.5px;
            color: #D1FAE5;
            margin: 0 0 28px;
            line-height: 1.65;
            max-width: 560px;
        }

        .hero-cta-group {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .btn-hero-primary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #10B981;
            color: #042F24;
            padding: 13px 26px;
            border-radius: 99px;
            text-decoration: none;
            font-weight: 800;
            font-size: 14px;
            box-shadow: 0 10px 24px rgba(16, 185, 129, 0.32);
            transition: transform var(--transition-fast), box-shadow var(--transition-fast), background-color var(--transition-fast);
        }

        .btn-hero-primary:hover {
            transform: translateY(-2px);
            background: #34D399;
            box-shadow: 0 14px 28px rgba(16, 185, 129, 0.42);
        }

        .btn-hero-secondary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.35);
            color: #FFFFFF;
            padding: 13px 24px;
            border-radius: 99px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: background-color var(--transition-fast), transform var(--transition-fast);
        }

        .btn-hero-secondary:hover {
            background: rgba(255, 255, 255, 0.22);
            transform: translateY(-2px);
        }

        /* Kartu Telemetry Kanan Hero */
        .hero-telemetry-card {
            background: rgba(255, 255, 255, 0.96);
            border-radius: 24px;
            padding: 24px;
            color: var(--slate-900);
            box-shadow: 0 24px 48px rgba(4, 47, 36, 0.35);
            border: 1px solid rgba(255, 255, 255, 0.8);
            animation: floatSlow 6s ease-in-out infinite;
        }

        .telemetry-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 14px;
            margin-bottom: 16px;
            border-bottom: 1px solid var(--border-light);
        }

        .telemetry-title {
            font-size: 12px;
            font-weight: 800;
            color: var(--emerald-800);
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }

        .telemetry-live-tag {
            font-size: 10.5px;
            font-weight: 800;
            background: var(--emerald-100);
            color: var(--emerald-800);
            padding: 3px 10px;
            border-radius: 99px;
        }

        .telemetry-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 16px;
        }

        .stat-box {
            background: var(--bg-canvas);
            border: 1px solid var(--border-light);
            border-radius: 14px;
            padding: 12px 10px;
            text-align: center;
        }

        .stat-num {
            font-size: 22px;
            font-weight: 800;
            color: var(--emerald-800);
            line-height: 1.1;
        }

        .stat-label {
            font-size: 11px;
            font-weight: 600;
            color: var(--slate-500);
            margin-top: 4px;
        }

        .telemetry-flow {
            background: var(--emerald-50);
            border: 1px solid #BBF7D0;
            border-radius: 14px;
            padding: 12px 14px;
        }

        .flow-label {
            font-size: 11px;
            font-weight: 700;
            color: var(--emerald-800);
            margin-bottom: 6px;
            display: flex;
            justify-content: space-between;
        }

        .flow-pills {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 4px;
            font-size: 11px;
            font-weight: 800;
            color: var(--slate-900);
        }

        .flow-node {
            background: #FFFFFF;
            padding: 5px 9px;
            border-radius: 8px;
            border: 1px solid #DCFCE7;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.03);
        }

        /* ==================== 3. FLOATING COMMAND BAR ==================== */
        .quick-bar-container {
            max-width: 980px;
            margin: -40px auto 0;
            padding: 0 4%;
            position: relative;
            z-index: 25;
        }

        .quick-bar-card {
            background: #FFFFFF;
            border-radius: 24px;
            padding: 14px 20px;
            box-shadow: 0 18px 40px rgba(6, 78, 59, 0.14);
            border: 2px solid #D1FAE5;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
        }

        .quick-field {
            flex: 1 1 220px;
            min-width: 0;
        }

        .quick-divider {
            width: 1px;
            height: 38px;
            background: var(--border-light);
        }

        .quick-label {
            font-size: 10.5px;
            font-weight: 800;
            color: var(--slate-500);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .quick-select {
            width: 100%;
            border: none;
            font-family: inherit;
            font-size: 14px;
            font-weight: 700;
            color: var(--slate-900);
            background: transparent;
            outline: none;
            cursor: pointer;
            margin-top: 3px;
            padding: 2px 0;
        }

        .quick-value {
            font-size: 14px;
            font-weight: 800;
            color: var(--emerald-800);
            margin-top: 3px;
        }

        /* ==================== 4. SPLIT SECTION (TENTANG) ==================== */
        .section-container {
            max-width: 1140px;
            margin: 76px auto;
            padding: 0 5%;
        }

        .split-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 46px;
            align-items: center;
        }

        .visual-frame {
            position: relative;
            padding: 16px;
        }

        .badge-circle-top {
            position: absolute;
            top: 0;
            left: 0;
            background: linear-gradient(135deg, var(--emerald-800), var(--emerald-700));
            color: #FFFFFF;
            width: 88px;
            height: 88px;
            border-radius: 50%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            box-shadow: 0 12px 26px rgba(46, 125, 50, 0.35);
            z-index: 5;
            border: 4px solid var(--bg-canvas);
        }

        .visual-img-box {
            border-radius: 24px;
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            border: 6px solid #FFFFFF;
            background: var(--emerald-900);
        }

        .visual-img-box img {
            width: 100%;
            height: 300px;
            object-fit: cover;
            display: block;
            transition: transform var(--transition-smooth);
        }

        .visual-frame:hover .visual-img-box img {
            transform: scale(1.03);
        }

        .badge-pill-bottom {
            position: absolute;
            bottom: 4px;
            right: 4px;
            background: #FFFFFF;
            padding: 11px 18px;
            border-radius: 99px;
            box-shadow: 0 12px 28px rgba(15, 23, 42, 0.12);
            display: flex;
            align-items: center;
            gap: 9px;
            border: 1px solid var(--border-light);
            z-index: 5;
        }

        .eyebrow {
            display: inline-block;
            color: var(--emerald-700);
            font-weight: 800;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .section-heading {
            font-size: clamp(24px, 3vw, 34px);
            font-weight: 800;
            color: var(--slate-900);
            margin: 8px 0 14px;
            line-height: 1.2;
            letter-spacing: -0.4px;
        }

        .feature-mini-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 14px;
            margin: 22px 0 26px;
        }

        .feature-mini-card {
            background: #FFFFFF;
            padding: 16px;
            border-radius: 16px;
            border: 1px solid var(--border-light);
            box-shadow: var(--shadow-sm);
            transition: transform var(--transition-fast), border-color var(--transition-fast), box-shadow var(--transition-fast);
        }

        .feature-mini-card:hover {
            transform: translateY(-3px);
            border-color: #86EFAC;
            box-shadow: var(--shadow-md);
        }

        .feature-icon-box {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            background: var(--emerald-50);
            color: var(--emerald-800);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
        }

        /* ==================== 5. 4 AKTOR RBAC SECTION ==================== */
        .rbac-section {
            background: linear-gradient(180deg, #E8F0E2 0%, #DFEAD7 100%);
            padding: 68px 4.5%;
            border-radius: var(--radius-xl);
            margin: 0 3.5%;
            border: 1px solid #D3E2C8;
        }

        .section-header-center {
            text-align: center;
            max-width: 660px;
            margin: 0 auto 40px;
        }

        .role-grid {
            max-width: 1140px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
        }

        .role-card {
            background: #FFFFFF;
            border-radius: 22px;
            padding: 24px;
            box-shadow: var(--shadow-sm);
            border: 1px solid rgba(226, 232, 240, 0.9);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
            transition: transform var(--transition-fast), box-shadow var(--transition-fast), border-color var(--transition-fast);
        }

        .role-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--emerald-700);
            opacity: 0.85;
        }

        .role-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-lg);
            border-color: #86EFAC;
        }

        .role-top-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
        }

        .role-step-pill {
            font-weight: 800;
            font-size: 10.5px;
            padding: 4px 11px;
            border-radius: 99px;
            letter-spacing: 0.3px;
        }

        .role-icon {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .role-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            background: var(--emerald-700);
            color: #FFFFFF;
            text-decoration: none;
            padding: 11px 18px;
            border-radius: 99px;
            font-size: 13px;
            font-weight: 700;
            transition: background-color var(--transition-fast), transform var(--transition-fast);
        }

        .role-btn:hover {
            background: var(--emerald-800);
            transform: translateY(-1px);
        }

        /* ==================== 6. ETALASE KOMODITAS ==================== */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
        }

        .product-card {
            background: #FFFFFF;
            border-radius: 22px;
            padding: 16px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-light);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: transform var(--transition-fast), box-shadow var(--transition-fast), border-color var(--transition-fast);
        }

        .product-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-lg);
            border-color: #86EFAC;
        }

        .product-thumb-wrap {
            position: relative;
            border-radius: 16px;
            overflow: hidden;
            height: 190px;
            margin-bottom: 14px;
            background: #F1F5F9;
        }

        .product-thumb-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: transform var(--transition-smooth);
        }

        .product-card:hover .product-thumb-wrap img {
            transform: scale(1.06);
        }

        .product-cat-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background: rgba(6, 78, 59, 0.9);
            color: #FFFFFF;
            font-size: 10.5px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 99px;
        }

        .product-stock-badge {
            position: absolute;
            bottom: 10px;
            right: 10px;
            background: rgba(255, 255, 255, 0.94);
            color: var(--emerald-800);
            font-size: 10.5px;
            font-weight: 800;
            padding: 4px 10px;
            border-radius: 99px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.12);
        }

        .product-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 14px;
            margin-top: 14px;
            border-top: 1px solid #F1F5F9;
        }

        /* ==================== 7. FOOTER ==================== */
        .site-footer {
            background: var(--emerald-900);
            color: #D1FAE5;
            padding: 48px 5% 28px;
            border-radius: 28px 28px 0 0;
        }

        .footer-grid {
            max-width: 1140px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1.5fr 1fr 1fr;
            gap: 32px;
            padding-bottom: 28px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.12);
        }

        .footer-links {
            list-style: none;
            padding: 0;
            margin: 10px 0 0;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .footer-links a {
            color: #A7F3D0;
            text-decoration: none;
            font-size: 13px;
            transition: color var(--transition-fast);
        }

        .footer-links a:hover {
            color: #FFFFFF;
        }

        .footer-bottom {
            max-width: 1140px;
            margin: 18px auto 0;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            font-size: 12px;
            color: #6EE7B7;
        }

        /* ==================== GPU SCROLL REVEAL & KEYFRAMES ==================== */
        .reveal {
            opacity: 0;
            transform: translate3d(0, 22px, 0);
            transition: opacity 0.55s cubic-bezier(0.22, 1, 0.36, 1), transform 0.55s cubic-bezier(0.22, 1, 0.36, 1);
        }

        .reveal.is-visible {
            opacity: 1;
            transform: translate3d(0, 0, 0);
        }

        @keyframes pulseScale {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.35); opacity: 0.75; }
        }

        @keyframes floatSlow {
            0%, 100% { transform: translate3d(0, 0, 0); }
            50% { transform: translate3d(0, -7px, 0); }
        }

        @keyframes slideDownFade {
            from { opacity: 0; transform: translate3d(0, -10px, 0); }
            to { opacity: 1; transform: translate3d(0, 0, 0); }
        }

        /* ==================== RESPONSIVE BREAKPOINTS ==================== */
        @media (max-width: 960px) {
            .hero-grid {
                grid-template-columns: 1fr;
                padding: 44px 5% 74px;
            }
            .hero-telemetry-card {
                animation: none;
            }
            .footer-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 860px) {
            .desktop-menu, .btn-shop-link, .brand-sub {
                display: none !important;
            }
            .mobile-menu-btn {
                display: inline-flex !important;
            }
        }

        @media (max-width: 720px) {
            .quick-divider {
                display: none;
            }
            .quick-bar-card {
                padding: 16px;
                border-radius: 20px;
            }
            .footer-grid {
                grid-template-columns: 1fr;
                gap: 24px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .reveal, .hero-telemetry-card, .pulse-dot {
                animation: none !important;
                transition: none !important;
                opacity: 1 !important;
                transform: none !important;
            }
        }
    </style>
</head>
<body>

    <div class="page-shell">

        <!-- 1. TOP NAVBAR RESPONSIVE -->
        <header class="navbar-wrap" id="topNavbarWrap">
            <nav class="main-nav" id="mainNav">
                <a href="index.php" class="brand-logo">
                    <div class="brand-mark">K</div>
                    <div class="brand-text-wrap">
                        <span class="brand-name">KOPNOVA</span>
                        <span class="brand-sub">AGRI SUPPLY CHAIN</span>
                    </div>
                </a>

                <div class="desktop-menu" id="desktopMenu">
                    <a href="#beranda" class="nav-link active">Beranda</a>
                    <a href="#tentang" class="nav-link">Ekosistem</a>
                    <a href="#alur-role" class="nav-link">4 Aktor RBAC</a>
                    <a href="#komoditas" class="nav-link">Komoditas</a>
                </div>

                <div class="nav-actions">
                    <a href="login.php?role=buyer" class="btn-shop-link">Belanja</a>
                    <a href="login.php" class="btn-primary-pill">
                        Masuk
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                    </a>
                    <button type="button" class="mobile-menu-btn" id="mobileMenuBtn" onclick="toggleMobileMenu()" aria-label="Buka Menu">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round">
                            <line x1="3" y1="6" x2="21" y2="6"></line>
                            <line x1="3" y1="12" x2="21" y2="12"></line>
                            <line x1="3" y1="18" x2="21" y2="18"></line>
                        </svg>
                    </button>
                </div>
            </nav>

            <!-- DROPDOWN MENU KHUSUS MOBILE -->
            <div class="mobile-dropdown" id="mobileDropdown">
                <a href="#beranda" onclick="closeMobileMenu()">Beranda</a>
                <a href="#tentang" onclick="closeMobileMenu()">Tentang Ekosistem</a>
                <a href="#alur-role" onclick="closeMobileMenu()">4 Aktor RBAC</a>
                <a href="#komoditas" onclick="closeMobileMenu()">Etalase Komoditas</a>
                <a href="login.php?role=buyer" class="mobile-cta">Masuk Sebagai Buyer (Belanja)</a>
            </div>
        </header>

        <!-- 2. HERO SECTION DENGAN TELEMETRY CARD -->
        <section id="beranda" class="hero-section">
            <div class="hero-grid">
                <div>
                    <span class="hero-badge">
                        <span class="pulse-dot"></span>
                        DIGITALISASI RANTAI PASOK PERTANIAN
                    </span>
                    <h1 class="hero-title">
                        Menghubungkan Hasil Panen Petani <span>Langsung ke Pasar Modern.</span>
                    </h1>
                    <p class="hero-desc">
                        Platform terpadu multi-wilayah yang menyatukan Petani Pemasok, Koperasi Primer kecamatan, Koperasi Konsumen pusat, dan Buyer dalam satu alur transaksi transparan.
                    </p>
                    <div class="hero-cta-group">
                        <a href="login.php" class="btn-hero-primary">
                            Mulai Sekarang
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                        </a>
                        <a href="#alur-role" class="btn-hero-secondary">
                            Pelajari Alur Sistem
                        </a>
                    </div>
                </div>

                <!-- KARTU TELEMETRI REAL-TIME (MENGISI SISI KANAN HERO) -->
                <div class="hero-telemetry-card">
                    <div class="telemetry-header">
                        <span class="telemetry-title">Ringkasan Ekosistem Aktif</span>
                        <span class="telemetry-live-tag">● Terintegrasi</span>
                    </div>

                    <div class="telemetry-stats">
                        <div class="stat-box">
                            <div class="stat-num"><?= (int)$total_divisi ?></div>
                            <div class="stat-label">Divisi Wilayah</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-num"><?= (int)$total_primer ?></div>
                            <div class="stat-label">Koperasi Primer</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-num"><?= (int)$total_produk ?></div>
                            <div class="stat-label">Komoditas Siap</div>
                        </div>
                    </div>

                    <div class="telemetry-flow">
                        <div class="flow-label">
                            <span>Alur Distribusi & Bagi Hasil SHU</span>
                            <span>Transparan</span>
                        </div>
                        <div class="flow-pills">
                            <span class="flow-node">Petani</span>
                            <span>→</span>
                            <span class="flow-node">Primer</span>
                            <span>→</span>
                            <span class="flow-node">Pusat</span>
                            <span>→</span>
                            <span class="flow-node">Buyer</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- 3. FLOATING QUICK ACCESS BAR -->
        <div class="quick-bar-container reveal">
            <form action="login.php" method="GET" class="quick-bar-card">
                <div class="quick-field">
                    <div class="quick-label">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#2E7D32" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                        Pilih Peran Akses
                    </div>
                    <select name="role" class="quick-select">
                        <option value="buyer">Buyer / Pembeli Komoditas</option>
                        <option value="petani">Petani / Pemasok Hulu</option>
                        <option value="primer">Koperasi Primer Kecamatan</option>
                        <option value="konsumen">Koperasi Konsumen (Pusat)</option>
                    </select>
                </div>

                <div class="quick-divider"></div>

                <div class="quick-field">
                    <div class="quick-label">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#2E7D32" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>
                        Cakupan Ekosistem Aktif
                    </div>
                    <div class="quick-value">
                        <?= (int)$total_divisi ?> Divisi Kab/Kota • <?= (int)$total_primer ?> Koperasi Primer
                    </div>
                </div>

                <button type="submit" class="btn-primary-pill" style="padding:13px 26px; font-size:14px;">
                    Akses Workspace
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                </button>
            </form>
        </div>

        <!-- 4. SPLIT SECTION: TENTANG KOPNOVA -->
        <section id="tentang" class="section-container reveal">
            <div class="split-grid">
                <!-- KIRI: KARTU VISUAL DENGAN FLOATING BADGE -->
                <div class="visual-frame">
                    <div class="badge-circle-top">
                        <span style="font-size:19px; font-weight:800; line-height:1;">100%</span>
                        <span style="font-size:10px; font-weight:600; margin-top:3px;">Terintegrasi</span>
                    </div>

                    <div class="visual-img-box">
                        <img src="assets/img/hero-bg.svg" alt="Ekosistem Koperasi KOPNOVA">
                    </div>

                    <div class="badge-pill-bottom">
                        <span style="width:10px; height:10px; background:#F59E0B; border-radius:50%; display:inline-block;"></span>
                        <span style="font-size:12.5px; font-weight:800; color:#0F172A;">Hulu ke Hilir Real-Time</span>
                    </div>
                </div>

                <!-- KANAN: NARASI & KEUNGGULAN -->
                <div>
                    <span class="eyebrow">Mengapa KOPNOVA?</span>
                    <h2 class="section-heading">
                        Tata Kelola Koperasi Modern Tanpa Sekat Wilayah.
                    </h2>
                    <p style="font-size:15px; color:#475569; margin:0; line-height:1.68;">
                        <strong style="color:#0F172A;">KOPNOVA</strong> dirancang fleksibel untuk berbagai divisi kabupaten dan kecamatan. Setiap hasil panen dari petani tercatat secara digital, diverifikasi oleh Koperasi Primer, dan langsung dapat dibeli oleh Buyer melalui etalase visual.
                    </p>

                    <div class="feature-mini-grid">
                        <div class="feature-mini-card">
                            <div class="feature-icon-box">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"></polygon><line x1="8" y1="2" x2="8" y2="18"></line><line x1="16" y1="6" x2="16" y2="22"></line></svg>
                            </div>
                            <div style="font-weight:800; color:#1B5E20; font-size:14px; margin-bottom:3px;">Multi-Kabupaten</div>
                            <div style="font-size:12.5px; color:#64748B;">Kelola divisi wilayah & koperasi kecamatan secara terpusat.</div>
                        </div>

                        <div class="feature-mini-card">
                            <div class="feature-icon-box">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                            </div>
                            <div style="font-weight:800; color:#1B5E20; font-size:14px; margin-bottom:3px;">RBAC 4 Peran</div>
                            <div style="font-size:12.5px; color:#64748B;">Hak akses terpisah, terenkripsi & aman untuk tiap aktor.</div>
                        </div>

                        <div class="feature-mini-card">
                            <div class="feature-icon-box">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                            </div>
                            <div style="font-weight:800; color:#1B5E20; font-size:14px; margin-bottom:3px;">Settlement SHU</div>
                            <div style="font-size:12.5px; color:#64748B;">Pembagian hak petani, kas primer & pusat otomatis.</div>
                        </div>

                        <div class="feature-mini-card">
                            <div class="feature-icon-box">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                            </div>
                            <div style="font-weight:800; color:#1B5E20; font-size:14px; margin-bottom:3px;">Early Warning Stok</div>
                            <div style="font-size:12.5px; color:#64748B;">Instruksi pasokan otomatis saat komoditas menipis.</div>
                        </div>
                    </div>

                    <div style="display:flex; align-items:center; flex-wrap:wrap; gap:16px;">
                        <a href="login.php" class="btn-primary-pill" style="padding:12px 26px; font-size:14px;">
                            Masuk ke Sistem
                        </a>
                        <a href="#komoditas" style="color:#1B5E20; font-weight:700; font-size:14px; text-decoration:none; display:inline-flex; align-items:center; gap:5px;">
                            Lihat Etalase Produk →
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- 5. SECTION 4 AKTOR EKOSISTEM -->
        <section id="alur-role" class="rbac-section reveal">
            <div class="section-header-center">
                <span class="eyebrow">Arsitektur RBAC Terstruktur</span>
                <h2 class="section-heading">4 Peran Utama dalam Rantai Pasok KOPNOVA</h2>
                <p style="font-size:14.5px; color:#475569; margin:0;">Setiap pengguna mendapatkan ruang kerja khusus sesuai tanggung jawabnya dari proses panen hingga transaksi selesai.</p>
            </div>

            <div class="role-grid">
                <!-- Card 1 -->
                <div class="role-card">
                    <div>
                        <div class="role-top-row">
                            <span class="role-step-pill" style="background:#DCFCE7; color:#166534;">01 • HULU PRODUKSI</span>
                            <div class="role-icon" style="background:#F0FDF4; color:#166534;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"></path><path d="M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12"></path></svg>
                            </div>
                        </div>
                        <h3 style="margin:0 0 8px; font-size:18px; font-weight:800; color:#0F172A;">Petani / Pemasok</h3>
                        <p style="font-size:13.5px; color:#64748B; margin:0 0 20px; line-height:1.6;">Mengajukan setoran hasil panen lengkap dengan foto kualitas komoditas dan harga penawaran ke koperasi kecamatan.</p>
                    </div>
                    <a href="login.php?role=petani" class="role-btn">Portal Petani →</a>
                </div>

                <!-- Card 2 -->
                <div class="role-card">
                    <div>
                        <div class="role-top-row">
                            <span class="role-step-pill" style="background:#FEF3C7; color:#92400E;">02 • KOPERASI KECAMATAN</span>
                            <div class="role-icon" style="background:#FFFBEB; color:#B45309;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                            </div>
                        </div>
                        <h3 style="margin:0 0 8px; font-size:18px; font-weight:800; color:#0F172A;">Koperasi Primer</h3>
                        <p style="font-size:13.5px; color:#64748B; margin:0 0 20px; line-height:1.6;">Memverifikasi kualitas pasokan petani, mengelola stok kecamatan, dan mempublikasikan foto produk ke etalase penjualan.</p>
                    </div>
                    <a href="login.php?role=primer" class="role-btn">Portal Primer →</a>
                </div>

                <!-- Card 3 -->
                <div class="role-card">
                    <div>
                        <div class="role-top-row">
                            <span class="role-step-pill" style="background:#DBEAFE; color:#1E40AF;">03 • PUSAT KENDALI</span>
                            <div class="role-icon" style="background:#EFF6FF; color:#1D4ED8;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                            </div>
                        </div>
                        <h3 style="margin:0 0 8px; font-size:18px; font-weight:800; color:#0F172A;">Koperasi Konsumen</h3>
                        <p style="font-size:13.5px; color:#64748B; margin:0 0 20px; line-height:1.6;">Mengatur divisi kabupaten dan kecamatan secara universal, regulasi harga HAP/HET, serta memantau laporan nasional.</p>
                    </div>
                    <a href="login.php?role=konsumen" class="role-btn">Portal Konsumen →</a>
                </div>

                <!-- Card 4 -->
                <div class="role-card">
                    <div>
                        <div class="role-top-row">
                            <span class="role-step-pill" style="background:#F3E8FF; color:#6B21A8;">04 • HILIR & PASAR</span>
                            <div class="role-icon" style="background:#FAF5FF; color:#7E22CE;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
                            </div>
                        </div>
                        <h3 style="margin:0 0 8px; font-size:18px; font-weight:800; color:#0F172A;">Buyer / Konsumen</h3>
                        <p style="font-size:13.5px; color:#64748B; margin:0 0 20px; line-height:1.6;">Berbelanja komoditas pertanian melalui katalog visual interaktif, keranjang belanja, dan checkout instan.</p>
                    </div>
                    <a href="login.php?role=buyer" class="role-btn">Mulai Belanja →</a>
                </div>
            </div>
        </section>

        <!-- 6. SECTION ETALASE KOMODITAS UNGGULAN -->
        <section id="komoditas" class="section-container reveal">
            <div class="section-header-center">
                <span class="eyebrow">Katalog Real-Time</span>
                <h2 class="section-heading">Etalase Komoditas & Produk Koperasi</h2>
                <p style="font-size:14.5px; color:#64748B; margin:0;">Produk segar dan olahan terverifikasi yang siap dipesan langsung melalui akun Buyer KOPNOVA.</p>
            </div>

            <div class="product-grid">
                <?php if (!empty($preview_produk)): ?>
                    <?php foreach ($preview_produk as $item): ?>
                    <div class="product-card">
                        <div>
                            <div class="product-thumb-wrap">
                                <img src="uploads/produk/<?= htmlspecialchars($item['foto_produk']) ?>" onerror="this.src='assets/img/hero-bg.svg'" alt="<?= htmlspecialchars($item['nama_produk']) ?>">
                                <span class="product-cat-badge"><?= htmlspecialchars($item['kategori'] ?? 'Komoditas') ?></span>
                                <span class="product-stock-badge">Stok: <?= (int)$item['stok'] ?> <?= htmlspecialchars($item['satuan']) ?></span>
                            </div>
                            <div style="font-size:11.5px; font-weight:700; color:#2E7D32;">
                                <?= htmlspecialchars($item['nama_divisi']) ?> • <?= htmlspecialchars($item['nama_koperasi']) ?>
                            </div>
                            <h3 style="margin:5px 0 8px; font-size:18px; font-weight:800; color:#0F172A; line-height:1.3;">
                                <?= htmlspecialchars($item['nama_produk']) ?>
                            </h3>
                            <p style="font-size:13px; color:#64748B; margin:0; line-height:1.55;">
                                <?= htmlspecialchars($item['deskripsi'] ?: 'Komoditas terverifikasi koperasi primer siap distribusi.') ?>
                            </p>
                        </div>
                        <div class="product-footer">
                            <div>
                                <div style="font-size:10.5px; font-weight:700; color:#64748B;">Harga Koperasi</div>
                                <span style="font-weight:800; font-size:16px; color:#1B5E20;">
                                    <?= rupiah($item['harga_jual']) ?><span style="font-size:12px; font-weight:600; color:#64748B;">/<?= htmlspecialchars($item['satuan']) ?></span>
                                </span>
                            </div>
                            <a href="login.php?role=buyer" class="btn-primary-pill" style="padding:9px 18px; font-size:12.5px;">
                                Pesan →
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="product-card">
                        <div>
                            <div class="product-thumb-wrap">
                                <img src="assets/img/hero-bg.svg" alt="Komoditas Tanaman Segar">
                                <span class="product-cat-badge">Komoditas Tanaman</span>
                            </div>
                            <h3 style="margin:0 0 8px; font-size:18px; font-weight:800; color:#0F172A;">Komoditas Tanaman Segar</h3>
                            <p style="font-size:13px; color:#64748B; margin:0;">Hasil panen hortikultura dan pangan langsung dari petani binaan koperasi primer kecamatan.</p>
                        </div>
                        <div class="product-footer">
                            <span style="font-weight:800; font-size:14px; color:#1B5E20;">Siap Distribusi</span>
                            <a href="login.php?role=buyer" class="btn-primary-pill" style="padding:9px 18px; font-size:12.5px;">Lihat Katalog →</a>
                        </div>
                    </div>

                    <div class="product-card">
                        <div>
                            <div class="product-thumb-wrap">
                                <img src="assets/img/hero-bg.svg" alt="Saprodi & Alsintan Terpadu">
                                <span class="product-cat-badge">Saprodi & Alsintan</span>
                            </div>
                            <h3 style="margin:0 0 8px; font-size:18px; font-weight:800; color:#0F172A;">Saprodi & Alsintan Terpadu</h3>
                            <p style="font-size:13px; color:#64748B; margin:0;">Penyediaan sarana produksi pertanian, pupuk organik, dan alat mesin pertanian berstandar.</p>
                        </div>
                        <div class="product-footer">
                            <span style="font-weight:800; font-size:14px; color:#1B5E20;">Stok Tersedia</span>
                            <a href="login.php?role=buyer" class="btn-primary-pill" style="padding:9px 18px; font-size:12.5px;">Lihat Katalog →</a>
                        </div>
                    </div>

                    <div class="product-card">
                        <div>
                            <div class="product-thumb-wrap">
                                <img src="assets/img/hero-bg.svg" alt="Produk Olahan Hilirisasi">
                                <span class="product-cat-badge">Produk Olahan</span>
                            </div>
                            <h3 style="margin:0 0 8px; font-size:18px; font-weight:800; color:#0F172A;">Produk Olahan Hilirisasi</h3>
                            <p style="font-size:13px; color:#64748B; margin:0;">Produk bernilai tambah tinggi hasil pengolahan UMKM dan koperasi primer siap pasar ritel.</p>
                        </div>
                        <div class="product-footer">
                            <span style="font-weight:800; font-size:14px; color:#1B5E20;">Kualitas Ekspor</span>
                            <a href="login.php?role=buyer" class="btn-primary-pill" style="padding:9px 18px; font-size:12.5px;">Lihat Katalog →</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- 7. FOOTER PROFESIONAL -->
        <footer class="site-footer">
            <div class="footer-grid">
                <div>
                    <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
                        <div style="width:34px; height:34px; background:#10B981; color:#042F24; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:16px;">K</div>
                        <span style="font-size:21px; font-weight:800; color:#FFFFFF; letter-spacing:0.3px;">KOPNOVA</span>
                    </div>
                    <p style="font-size:13.5px; color:#A7F3D0; margin:0 0 18px; max-width:380px; line-height:1.6;">
                        Ekosistem Digital Rantai Pasok Koperasi & Agribisnis Multi-Wilayah yang menghubungkan hulu produksi petani hingga pasar modern.
                    </p>
                    <a href="login.php" class="btn-hero-primary" style="padding:10px 22px; font-size:13px;">
                        Masuk Dashboard →
                    </a>
                </div>

                <div>
                    <div style="font-size:13px; font-weight:800; color:#FFFFFF; text-transform:uppercase; letter-spacing:0.6px;">Navigasi Halaman</div>
                    <ul class="footer-links">
                        <li><a href="#beranda">Beranda Utama</a></li>
                        <li><a href="#tentang">Tentang Ekosistem</a></li>
                        <li><a href="#alur-role">Arsitektur 4 Aktor</a></li>
                        <li><a href="#komoditas">Etalase Komoditas</a></li>
                    </ul>
                </div>

                <div>
                    <div style="font-size:13px; font-weight:800; color:#FFFFFF; text-transform:uppercase; letter-spacing:0.6px;">Portal Akses RBAC</div>
                    <ul class="footer-links">
                        <li><a href="login.php?role=petani">Login Petani / Pemasok</a></li>
                        <li><a href="login.php?role=primer">Login Koperasi Primer</a></li>
                        <li><a href="login.php?role=konsumen">Login Koperasi Konsumen</a></li>
                        <li><a href="login.php?role=buyer">Login Buyer / Pembeli</a></li>
                    </ul>
                </div>
            </div>

            <div class="footer-bottom">
                <span>© <?= date('Y') ?> KOPNOVA Supply Chain System. Seluruh hak cipta dilindungi.</span>
                <span>Integrasi • Kolaborasi • Digitalisasi</span>
            </div>
        </footer>

    </div>

    <!-- LIGHTWEIGHT ZERO-LAG SCRIPT -->
    <script>
        const mobileDropdown = document.getElementById('mobileDropdown');
        const mainNav = document.getElementById('mainNav');

        function toggleMobileMenu() {
            mobileDropdown.classList.toggle('open');
        }

        function closeMobileMenu() {
            mobileDropdown.classList.remove('open');
        }

        // Tutup menu mobile otomatis saat resize ke desktop atau klik di luar header
        window.addEventListener('resize', () => {
            if (window.innerWidth >= 860) {
                closeMobileMenu();
            }
        }, { passive: true });

        document.addEventListener('click', (e) => {
            const headerWrap = document.getElementById('topNavbarWrap');
            if (headerWrap && !headerWrap.contains(e.target)) {
                closeMobileMenu();
            }
        });

        // Efek shadow halus saat scroll (passive listener, nol beban CPU)
        window.addEventListener('scroll', () => {
            if (window.scrollY > 24) {
                mainNav.classList.add('scrolled');
            } else {
                mainNav.classList.remove('scrolled');
            }
        }, { passive: true });

        // GPU Scroll Reveal sekali jalan
        document.addEventListener('DOMContentLoaded', () => {
            const reveals = document.querySelectorAll('.reveal');
            if ('IntersectionObserver' in window) {
                const observer = new IntersectionObserver((entries, obs) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            entry.target.classList.add('is-visible');
                            obs.unobserve(entry.target);
                        }
                    });
                }, { threshold: 0.12 });

                reveals.forEach(el => observer.observe(el));
            } else {
                reveals.forEach(el => el.classList.add('is-visible'));
            }
        });
    </script>

</body>
</html>