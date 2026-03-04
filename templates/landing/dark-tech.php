<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($company['company_name'] ?? 'ISP Provider') ?> - High-Speed Internet Services</title>
    <meta name="description" content="<?= htmlspecialchars($landingSettings['hero_subtitle'] ?? 'Fast, reliable internet for your home and business') ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: <?= htmlspecialchars($landingSettings['primary_color'] ?? '#4F46E5') ?>;
            --primary-rgb: 79, 70, 229;
            --primary-dark: #3730A3;
            --primary-light: #EEF2FF;
            --secondary: #06B6D4;
            --accent: #10B981;
            --accent-rgb: 16, 185, 129;
            --orange: #F59E0B;
            --dark: #0F172A;
            --dark-light: #1E293B;
            --dark-card: #1a2332;
            --gray-900: #111827;
            --gray-800: #1F2937;
            --gray-700: #374151;
            --gray-600: #6B7280;
            --gray-500: #9CA3AF;
            --gray-400: #D1D5DB;
            --gray-200: #E5E7EB;
            --gray-100: #F3F4F6;
            --gray-50: #F9FAFB;
            --white: #FFFFFF;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html { scroll-behavior: smooth; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            color: var(--gray-900);
            overflow-x: hidden;
            background: var(--white);
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Space Grotesk', 'Inter', sans-serif;
        }

        .navbar {
            padding: 1rem 0;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            background: transparent;
        }

        .navbar.scrolled {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 0.6rem 0;
        }

        .navbar-brand {
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 700;
            font-size: 1.5rem;
            color: var(--white) !important;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            transition: color 0.3s;
        }

        .navbar.scrolled .navbar-brand { color: var(--gray-900) !important; }

        .navbar-brand .brand-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
        }

        .nav-link {
            font-weight: 500;
            font-size: 0.95rem;
            color: rgba(255,255,255,0.85) !important;
            padding: 0.5rem 1rem !important;
            transition: all 0.3s ease;
            position: relative;
        }

        .navbar.scrolled .nav-link { color: var(--gray-700) !important; }
        .nav-link:hover { color: var(--white) !important; }
        .navbar.scrolled .nav-link:hover { color: var(--primary) !important; }

        .nav-link::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 0;
            height: 2px;
            background: var(--secondary);
            transition: all 0.3s ease;
            transform: translateX(-50%);
        }

        .nav-link:hover::after { width: 60%; }

        .btn-nav-login {
            border: 1.5px solid rgba(255,255,255,0.4);
            color: var(--white) !important;
            padding: 0.5rem 1.25rem !important;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            background: transparent;
        }

        .btn-nav-login:hover {
            background: rgba(255,255,255,0.15);
            border-color: rgba(255,255,255,0.6);
        }

        .navbar.scrolled .btn-nav-login {
            border-color: var(--gray-300, #D1D5DB);
            color: var(--gray-700) !important;
        }

        .navbar.scrolled .btn-nav-login:hover {
            background: var(--gray-100);
            border-color: var(--primary);
            color: var(--primary) !important;
        }

        .btn-nav-cta {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: var(--white) !important;
            padding: 0.5rem 1.5rem !important;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            border: none;
            transition: all 0.3s ease;
            box-shadow: 0 2px 10px rgba(var(--primary-rgb), 0.3);
        }

        .btn-nav-cta:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(var(--primary-rgb), 0.5);
        }

        .navbar-toggler {
            border-color: rgba(255,255,255,0.3);
            padding: 0.4rem 0.6rem;
        }

        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28255, 255, 255, 0.85%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        }

        .navbar.scrolled .navbar-toggler { border-color: var(--gray-300, #D1D5DB); }
        .navbar.scrolled .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%2855, 65, 81, 0.85%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        }

        .hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #0F172A 0%, #1E293B 40%, #0F172A 100%);
        }

        #heroCanvas {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
        }

        .hero-gradient-orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            z-index: 1;
        }

        .hero-orb-1 {
            width: 500px;
            height: 500px;
            background: rgba(var(--primary-rgb), 0.15);
            top: -100px;
            right: -100px;
            animation: orbFloat 15s ease-in-out infinite;
        }

        .hero-orb-2 {
            width: 400px;
            height: 400px;
            background: rgba(6, 182, 212, 0.1);
            bottom: -50px;
            left: -50px;
            animation: orbFloat 20s ease-in-out infinite reverse;
        }

        .hero-orb-3 {
            width: 300px;
            height: 300px;
            background: rgba(var(--accent-rgb), 0.08);
            top: 40%;
            left: 30%;
            animation: orbFloat 25s ease-in-out infinite;
        }

        @keyframes orbFloat {
            0%, 100% { transform: translate(0, 0) scale(1); }
            25% { transform: translate(30px, -40px) scale(1.05); }
            50% { transform: translate(-20px, 20px) scale(0.95); }
            75% { transform: translate(40px, 30px) scale(1.02); }
        }

        .hero-content {
            position: relative;
            z-index: 2;
            padding-top: 100px;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(var(--primary-rgb), 0.15);
            border: 1px solid rgba(var(--primary-rgb), 0.25);
            color: #A5B4FC;
            padding: 0.5rem 1.25rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 500;
            margin-bottom: 1.5rem;
            backdrop-filter: blur(10px);
        }

        .hero-badge i { color: var(--secondary); }

        .hero-title {
            font-size: 4rem;
            font-weight: 800;
            color: var(--white);
            line-height: 1.1;
            margin-bottom: 1.5rem;
            letter-spacing: -0.02em;
        }

        .hero-title .text-gradient {
            background: linear-gradient(135deg, var(--secondary) 0%, var(--accent) 50%, #34D399 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-description {
            font-size: 1.15rem;
            color: #94A3B8;
            line-height: 1.75;
            margin-bottom: 2.5rem;
            max-width: 520px;
        }

        .hero-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 3rem;
        }

        .btn-hero-primary {
            background: linear-gradient(135deg, var(--primary), #6366F1);
            color: var(--white);
            padding: 0.875rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            transition: all 0.3s ease;
            box-shadow: 0 4px 20px rgba(var(--primary-rgb), 0.4);
        }

        .btn-hero-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(var(--primary-rgb), 0.6);
            color: var(--white);
        }

        .btn-hero-secondary {
            background: rgba(255,255,255,0.08);
            border: 1.5px solid rgba(255,255,255,0.2);
            color: var(--white);
            padding: 0.875rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .btn-hero-secondary:hover {
            background: rgba(255,255,255,0.15);
            border-color: rgba(255,255,255,0.4);
            color: var(--white);
            transform: translateY(-2px);
        }

        .btn-hero-outline {
            background: transparent;
            border: 1.5px solid rgba(239, 68, 68, 0.5);
            color: #FCA5A5;
            padding: 0.875rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-hero-outline:hover {
            background: rgba(239, 68, 68, 0.1);
            border-color: rgba(239, 68, 68, 0.7);
            color: #FCA5A5;
        }

        .hero-trust {
            display: flex;
            align-items: center;
            gap: 2rem;
            flex-wrap: wrap;
        }

        .hero-trust-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #64748B;
            font-size: 0.9rem;
        }

        .hero-trust-item i { color: var(--accent); font-size: 1rem; }

        .hero-visual {
            position: relative;
            z-index: 2;
        }

        .hero-card {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 24px;
            padding: 2.5rem;
            backdrop-filter: blur(20px);
            text-align: center;
        }

        .speed-ring {
            width: 200px;
            height: 200px;
            margin: 0 auto 2rem;
            position: relative;
        }

        .speed-ring svg { width: 100%; height: 100%; transform: rotate(-90deg); }

        .speed-ring-bg {
            fill: none;
            stroke: rgba(255,255,255,0.08);
            stroke-width: 8;
        }

        .speed-ring-fill {
            fill: none;
            stroke: url(#speedGradient);
            stroke-width: 8;
            stroke-linecap: round;
            stroke-dasharray: 565;
            stroke-dashoffset: 141;
            transition: stroke-dashoffset 2s ease;
        }

        .speed-ring-value {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
        }

        .speed-ring-value strong {
            display: block;
            font-size: 3rem;
            font-weight: 800;
            color: var(--white);
            font-family: 'Space Grotesk', sans-serif;
            line-height: 1;
        }

        .speed-ring-value span {
            font-size: 0.85rem;
            color: #64748B;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .hero-stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
        }

        .hero-stat {
            text-align: center;
            padding: 1rem;
            background: rgba(255,255,255,0.03);
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.06);
        }

        .hero-stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--white);
            font-family: 'Space Grotesk', sans-serif;
        }

        .hero-stat-label {
            font-size: 0.75rem;
            color: #64748B;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 0.25rem;
        }

        .hero-float-badge {
            position: absolute;
            background: rgba(255,255,255,0.95);
            border-radius: 14px;
            padding: 0.875rem 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            animation: badgeFloat 4s ease-in-out infinite;
            z-index: 3;
        }

        .hero-float-badge i {
            font-size: 1.5rem;
            color: var(--primary);
        }

        .hero-float-badge strong {
            display: block;
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--gray-900);
        }

        .hero-float-badge span {
            font-size: 0.75rem;
            color: var(--gray-600);
        }

        .float-badge-1 {
            top: 30px;
            right: -30px;
            animation-delay: 0s;
        }

        .float-badge-2 {
            bottom: 80px;
            left: -40px;
            animation-delay: 1.5s;
        }

        @keyframes badgeFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-12px); }
        }

        .section {
            padding: 6rem 0;
        }

        .section-header {
            text-align: center;
            margin-bottom: 3.5rem;
        }

        .section-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--primary-light);
            color: var(--primary);
            padding: 0.4rem 1.25rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 1rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }

        .section-title {
            font-size: 2.75rem;
            font-weight: 800;
            color: var(--gray-900);
            margin-bottom: 1rem;
            letter-spacing: -0.02em;
        }

        .section-subtitle {
            font-size: 1.05rem;
            color: var(--gray-600);
            max-width: 600px;
            margin: 0 auto;
            line-height: 1.75;
        }

        .how-it-works {
            background: var(--gray-50);
        }

        .step-card {
            text-align: center;
            padding: 2.5rem 2rem;
            position: relative;
        }

        .step-number {
            width: 64px;
            height: 64px;
            border-radius: 16px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: var(--white);
            font-size: 1.5rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-family: 'Space Grotesk', sans-serif;
        }

        .step-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 0.75rem;
        }

        .step-desc {
            color: var(--gray-600);
            line-height: 1.7;
            font-size: 0.95rem;
        }

        .step-connector {
            position: absolute;
            top: 54px;
            right: -40px;
            width: 80px;
            height: 2px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            z-index: 1;
        }

        .about-section {
            background: var(--white);
        }

        .about-image {
            position: relative;
        }

        .about-image img {
            border-radius: 20px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.1);
            width: 100%;
            object-fit: cover;
        }

        .about-badge {
            position: absolute;
            bottom: -20px;
            right: -10px;
            background: linear-gradient(135deg, var(--primary), #6366F1);
            color: var(--white);
            padding: 1.25rem 1.5rem;
            border-radius: 16px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(var(--primary-rgb), 0.4);
        }

        .about-badge strong {
            display: block;
            font-size: 2.25rem;
            font-weight: 800;
            font-family: 'Space Grotesk', sans-serif;
        }

        .about-badge span { font-size: 0.85rem; opacity: 0.9; }

        .about-content h2 {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--gray-900);
            margin-bottom: 1.25rem;
            letter-spacing: -0.02em;
        }

        .about-content p {
            color: var(--gray-600);
            line-height: 1.8;
            margin-bottom: 2rem;
        }

        .about-features {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.25rem;
            margin-bottom: 2rem;
        }

        .about-feature {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .about-feature-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            background: var(--primary-light);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            flex-shrink: 0;
        }

        .about-feature-text {
            font-weight: 600;
            color: var(--gray-800);
            font-size: 0.95rem;
        }

        .services-section {
            background: var(--gray-50);
        }

        .service-card {
            background: var(--white);
            border-radius: 20px;
            padding: 0;
            height: 100%;
            border: 1px solid var(--gray-200);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
        }

        .service-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 50px rgba(0,0,0,0.08);
            border-color: transparent;
        }

        .service-card-img {
            width: 100%;
            height: 180px;
            object-fit: cover;
        }

        .service-card-body { padding: 1.75rem; }

        .service-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin-bottom: 1.25rem;
            margin-top: -40px;
            position: relative;
            z-index: 1;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .service-icon.blue { background: linear-gradient(135deg, var(--primary), #6366F1); color: white; }
        .service-icon.green { background: linear-gradient(135deg, var(--accent), #34D399); color: white; }
        .service-icon.orange { background: linear-gradient(135deg, var(--orange), #FBBF24); color: white; }
        .service-icon.purple { background: linear-gradient(135deg, #8B5CF6, #A78BFA); color: white; }

        .service-title {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 0.75rem;
        }

        .service-desc {
            color: var(--gray-600);
            font-size: 0.9rem;
            line-height: 1.7;
            margin: 0;
        }

        .features-section {
            background: var(--white);
        }

        .feature-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .feature-list li {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            margin-bottom: 1.25rem;
            padding: 1.25rem;
            background: var(--gray-50);
            border-radius: 14px;
            transition: all 0.3s ease;
            border: 1px solid transparent;
        }

        .feature-list li:hover {
            background: var(--white);
            border-color: var(--primary-light);
            box-shadow: 0 4px 20px rgba(var(--primary-rgb), 0.08);
            transform: translateX(4px);
        }

        .feature-list .f-icon {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--primary), #6366F1);
            color: var(--white);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            flex-shrink: 0;
        }

        .feature-list h5 {
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 0.25rem;
            font-size: 1.05rem;
        }

        .feature-list p {
            color: var(--gray-600);
            margin: 0;
            font-size: 0.9rem;
            line-height: 1.6;
        }

        .features-image img {
            width: 100%;
            border-radius: 20px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.1);
        }

        .packages-section {
            background: var(--gray-50);
        }

        .package-card {
            background: var(--white);
            border-radius: 20px;
            padding: 2.5rem 2rem;
            text-align: center;
            border: 2px solid var(--gray-200);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .package-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 25px 50px rgba(0,0,0,0.08);
        }

        .package-card.popular {
            border-color: var(--primary);
            box-shadow: 0 15px 40px rgba(var(--primary-rgb), 0.15);
        }

        .package-card.popular:hover {
            box-shadow: 0 25px 50px rgba(var(--primary-rgb), 0.25);
        }

        .package-badge {
            position: absolute;
            top: -14px;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(135deg, var(--primary), #6366F1);
            color: var(--white);
            padding: 0.35rem 1.5rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .package-icon {
            width: 60px;
            height: 60px;
            border-radius: 14px;
            background: var(--primary-light);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin: 0 auto 1.25rem;
        }

        .package-card.popular .package-icon {
            background: linear-gradient(135deg, var(--primary), #6366F1);
            color: var(--white);
        }

        .package-name {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 0.75rem;
        }

        .package-speed {
            margin-bottom: 1rem;
        }

        .package-speed-value {
            font-size: 3rem;
            font-weight: 800;
            color: var(--gray-900);
            line-height: 1;
            font-family: 'Space Grotesk', sans-serif;
        }

        .package-speed-unit {
            font-size: 1rem;
            color: var(--gray-500);
            font-weight: 500;
        }

        .package-price {
            margin-bottom: 1.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .package-currency {
            font-size: 0.9rem;
            color: var(--gray-500);
            font-weight: 500;
            vertical-align: super;
        }

        .package-amount {
            font-size: 2.25rem;
            font-weight: 800;
            color: var(--primary);
            font-family: 'Space Grotesk', sans-serif;
        }

        .package-period {
            font-size: 0.9rem;
            color: var(--gray-500);
        }

        .package-features {
            list-style: none;
            padding: 0;
            margin: 0 0 2rem;
            text-align: left;
            flex-grow: 1;
        }

        .package-features li {
            padding: 0.5rem 0;
            color: var(--gray-700);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.95rem;
        }

        .package-features li i { color: var(--accent); font-size: 0.9rem; }

        .package-btn {
            width: 100%;
            padding: 0.875rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .package-btn.outline {
            background: transparent;
            border: 2px solid var(--gray-200);
            color: var(--gray-700);
        }

        .package-btn.outline:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-light);
        }

        .package-btn.primary {
            background: linear-gradient(135deg, var(--primary), #6366F1);
            border: 2px solid transparent;
            color: var(--white);
            box-shadow: 0 4px 15px rgba(var(--primary-rgb), 0.3);
        }

        .package-btn.primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(var(--primary-rgb), 0.5);
        }

        .testimonials-section {
            background: var(--white);
        }

        .testimonial-card {
            background: var(--gray-50);
            border-radius: 20px;
            padding: 2rem;
            height: 100%;
            border: 1px solid var(--gray-200);
            transition: all 0.4s ease;
            position: relative;
        }

        .testimonial-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.06);
            background: var(--white);
        }

        .testimonial-quote {
            font-size: 2.5rem;
            color: var(--primary);
            opacity: 0.15;
            position: absolute;
            top: 16px;
            right: 24px;
        }

        .testimonial-text {
            color: var(--gray-700);
            line-height: 1.8;
            margin-bottom: 1.5rem;
            font-size: 0.95rem;
        }

        .testimonial-author {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .testimonial-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary-light);
        }

        .testimonial-info h5 {
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 0;
            font-size: 0.95rem;
        }

        .testimonial-info span {
            color: var(--gray-500);
            font-size: 0.8rem;
        }

        .testimonial-rating {
            color: #F59E0B;
            font-size: 0.8rem;
        }

        .stats-section {
            background: var(--dark);
            padding: 5rem 0;
            position: relative;
            overflow: hidden;
        }

        .stats-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }

        .stat-card {
            text-align: center;
            padding: 1.5rem;
            position: relative;
        }

        .stat-value {
            font-size: 3rem;
            font-weight: 800;
            color: var(--white);
            font-family: 'Space Grotesk', sans-serif;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #64748B;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .faq-section {
            background: var(--gray-50);
        }

        .faq-item {
            background: var(--white);
            border-radius: 14px;
            margin-bottom: 0.75rem;
            border: 1px solid var(--gray-200);
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .faq-item:hover {
            border-color: rgba(var(--primary-rgb), 0.2);
        }

        .faq-question {
            padding: 1.25rem 1.5rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            font-weight: 600;
            color: var(--gray-900);
            font-size: 1rem;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
        }

        .faq-question:hover { color: var(--primary); }

        .faq-question i {
            transition: transform 0.3s;
            color: var(--primary);
            font-size: 0.9rem;
        }

        .faq-question:not(.collapsed) i { transform: rotate(180deg); }

        .faq-answer {
            padding: 0 1.5rem 1.25rem;
            color: var(--gray-600);
            line-height: 1.75;
            font-size: 0.95rem;
        }

        .contact-section {
            background: var(--white);
        }

        .contact-card {
            background: var(--gray-50);
            border-radius: 16px;
            padding: 2rem;
            text-align: center;
            height: 100%;
            transition: all 0.4s ease;
            border: 1px solid transparent;
        }

        .contact-card:hover {
            background: var(--white);
            border-color: var(--primary-light);
            box-shadow: 0 10px 30px rgba(var(--primary-rgb), 0.08);
            transform: translateY(-4px);
        }

        .contact-icon {
            width: 64px;
            height: 64px;
            border-radius: 16px;
            background: linear-gradient(135deg, var(--primary), #6366F1);
            color: var(--white);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin: 0 auto 1.25rem;
            transition: transform 0.3s ease;
        }

        .contact-card:hover .contact-icon { transform: scale(1.05); }

        .contact-label {
            font-size: 0.8rem;
            color: var(--gray-500);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .contact-value {
            font-size: 1rem;
            font-weight: 600;
            color: var(--gray-900);
            line-height: 1.6;
        }

        .contact-value a {
            color: var(--gray-900);
            text-decoration: none;
            transition: color 0.3s;
        }

        .contact-value a:hover { color: var(--primary); }

        .map-container {
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0,0,0,0.08);
            border: 1px solid var(--gray-200);
        }

        .cta-section {
            background: linear-gradient(135deg, var(--dark) 0%, #1E293B 100%);
            padding: 6rem 0;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .cta-section::before {
            content: '';
            position: absolute;
            width: 500px;
            height: 500px;
            border-radius: 50%;
            background: rgba(var(--primary-rgb), 0.1);
            filter: blur(80px);
            top: -200px;
            right: -100px;
        }

        .cta-section::after {
            content: '';
            position: absolute;
            width: 400px;
            height: 400px;
            border-radius: 50%;
            background: rgba(6, 182, 212, 0.08);
            filter: blur(80px);
            bottom: -200px;
            left: -100px;
        }

        .cta-title {
            font-size: 2.75rem;
            font-weight: 800;
            color: var(--white);
            margin-bottom: 1rem;
            position: relative;
            letter-spacing: -0.02em;
        }

        .cta-subtitle {
            font-size: 1.1rem;
            color: #94A3B8;
            margin-bottom: 2.5rem;
            max-width: 550px;
            margin-left: auto;
            margin-right: auto;
            position: relative;
            line-height: 1.7;
        }

        .cta-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            position: relative;
        }

        .btn-cta-primary {
            background: linear-gradient(135deg, var(--primary), #6366F1);
            color: var(--white);
            padding: 0.875rem 2.5rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            transition: all 0.3s ease;
            box-shadow: 0 4px 20px rgba(var(--primary-rgb), 0.4);
        }

        .btn-cta-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(var(--primary-rgb), 0.6);
            color: var(--white);
        }

        .btn-cta-secondary {
            background: rgba(255,255,255,0.08);
            border: 1.5px solid rgba(255,255,255,0.2);
            color: var(--white);
            padding: 0.875rem 2.5rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .btn-cta-secondary:hover {
            background: rgba(255,255,255,0.15);
            border-color: rgba(255,255,255,0.4);
            color: var(--white);
        }

        .social-links {
            display: flex;
            gap: 0.75rem;
            justify-content: center;
            margin-top: 2rem;
            position: relative;
        }

        .social-link {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: rgba(255,255,255,0.08);
            color: #94A3B8;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            text-decoration: none;
            transition: all 0.3s ease;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .social-link:hover {
            background: rgba(255,255,255,0.15);
            color: var(--white);
            transform: translateY(-3px);
        }

        footer {
            background: var(--dark);
            color: #94A3B8;
            padding: 4rem 0 2rem;
            border-top: 1px solid rgba(255,255,255,0.06);
        }

        .footer-brand {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--white);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .footer-brand .brand-icon {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1rem;
        }

        .footer-desc {
            color: #64748B;
            margin-bottom: 1.5rem;
            max-width: 320px;
            line-height: 1.7;
            font-size: 0.9rem;
        }

        .footer-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--white);
            margin-bottom: 1.25rem;
            font-family: 'Space Grotesk', sans-serif;
        }

        .footer-links {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .footer-links li { margin-bottom: 0.75rem; }

        .footer-links a {
            color: #64748B;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .footer-links a:hover {
            color: var(--white);
            padding-left: 4px;
        }

        .footer-contact-item {
            display: flex;
            gap: 0.75rem;
            margin-bottom: 0.875rem;
            color: #64748B;
            font-size: 0.9rem;
        }

        .footer-contact-item i {
            color: var(--secondary);
            font-size: 1rem;
            margin-top: 0.15rem;
        }

        .footer-bottom {
            border-top: 1px solid rgba(255,255,255,0.06);
            margin-top: 3rem;
            padding-top: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .footer-bottom-text {
            color: #475569;
            font-size: 0.85rem;
        }

        .footer-bottom-links {
            display: flex;
            gap: 1.5rem;
        }

        .footer-bottom-links a {
            color: #475569;
            text-decoration: none;
            font-size: 0.85rem;
            transition: color 0.3s;
        }

        .footer-bottom-links a:hover { color: var(--white); }

        .whatsapp-float {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #25D366, #128C7E);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.75rem;
            text-decoration: none;
            box-shadow: 0 6px 25px rgba(37, 211, 102, 0.4);
            z-index: 999;
            transition: all 0.3s ease;
        }

        .whatsapp-float:hover {
            transform: scale(1.08) translateY(-2px);
            box-shadow: 0 8px 35px rgba(37, 211, 102, 0.6);
            color: white;
        }

        .animate-on-scroll {
            opacity: 0;
            transform: translateY(25px);
            transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .animate-on-scroll.visible {
            opacity: 1;
            transform: translateY(0);
        }

        @media (max-width: 991px) {
            .hero-title { font-size: 3rem; }
            .section-title { font-size: 2.25rem; }
            .cta-title { font-size: 2.25rem; }
            .stat-value { font-size: 2.5rem; }
            .step-connector { display: none; }
            .hero-visual { margin-top: 3rem; }
        }

        @media (max-width: 767px) {
            .hero {
                text-align: center;
                padding: 120px 0 60px;
            }
            .hero-title { font-size: 2.25rem; }
            .hero-description { margin: 0 auto 2rem; }
            .hero-buttons { justify-content: center; }
            .hero-trust { justify-content: center; }
            .hero-float-badge { display: none; }
            .about-features { grid-template-columns: 1fr; }
            .package-speed-value { font-size: 2.5rem; }
            .footer-bottom { text-align: center; justify-content: center; }
            .footer-bottom-links { justify-content: center; }
            .section { padding: 4rem 0; }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="/">
                <span class="brand-icon"><i class="bi bi-broadcast-pin"></i></span>
                <?= htmlspecialchars($company['company_name'] ?? 'ISP Provider') ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item"><a class="nav-link" href="#home">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="#about">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="#services">Services</a></li>
                    <li class="nav-item"><a class="nav-link" href="#features">Features</a></li>
                    <li class="nav-item"><a class="nav-link" href="#packages">Packages</a></li>
                    <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
                    <li class="nav-item ms-lg-2">
                        <a class="btn-nav-login nav-link" href="?page=login">
                            <i class="bi bi-box-arrow-in-right me-1"></i>Login
                        </a>
                    </li>
                    <li class="nav-item ms-lg-1">
                        <a class="btn-nav-cta nav-link" href="?page=order">Get Started</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <section id="home" class="hero">
        <canvas id="heroCanvas"></canvas>
        <div class="hero-gradient-orb hero-orb-1"></div>
        <div class="hero-gradient-orb hero-orb-2"></div>
        <div class="hero-gradient-orb hero-orb-3"></div>
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="hero-content animate__animated animate__fadeInUp">
                        <div class="hero-badge">
                            <i class="bi bi-lightning-charge-fill"></i>
                            <?= htmlspecialchars($landingSettings['hero_badge'] ?? 'Ultra-Fast Fiber Internet') ?>
                        </div>
                        <h1 class="hero-title">
                            <?= htmlspecialchars($landingSettings['hero_title'] ?? 'Lightning Fast') ?>
                            <span class="text-gradient">Internet Speed</span>
                        </h1>
                        <p class="hero-description">
                            <?= htmlspecialchars($landingSettings['hero_subtitle'] ?? 'Experience blazing fast fiber internet for your home and business. Stream, game, work, and connect with the fastest speeds in your area.') ?>
                        </p>
                        <div class="hero-buttons">
                            <a href="#packages" class="btn-hero-primary">
                                <i class="bi bi-arrow-right-circle"></i>View Packages
                            </a>
                            <a href="?page=order" class="btn-hero-secondary">
                                <i class="bi bi-telephone"></i>Order Now
                            </a>
                            <button type="button" class="btn-hero-outline" data-bs-toggle="modal" data-bs-target="#complaintModal">
                                <i class="bi bi-exclamation-triangle"></i>Report Issue
                            </button>
                        </div>
                        <div class="hero-trust">
                            <div class="hero-trust-item">
                                <i class="bi bi-check-circle-fill"></i>
                                <span>No Hidden Fees</span>
                            </div>
                            <div class="hero-trust-item">
                                <i class="bi bi-check-circle-fill"></i>
                                <span>Free Installation</span>
                            </div>
                            <div class="hero-trust-item">
                                <i class="bi bi-check-circle-fill"></i>
                                <span>24/7 Support</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 d-none d-lg-block">
                    <div class="hero-visual animate__animated animate__fadeInRight animate__delay-1s">
                        <div class="hero-card">
                            <div class="speed-ring">
                                <svg viewBox="0 0 200 200">
                                    <defs>
                                        <linearGradient id="speedGradient" x1="0%" y1="0%" x2="100%" y2="0%">
                                            <stop offset="0%" stop-color="var(--primary)"/>
                                            <stop offset="50%" stop-color="var(--secondary)"/>
                                            <stop offset="100%" stop-color="var(--accent)"/>
                                        </linearGradient>
                                    </defs>
                                    <circle cx="100" cy="100" r="90" class="speed-ring-bg"/>
                                    <circle cx="100" cy="100" r="90" class="speed-ring-fill"/>
                                </svg>
                                <div class="speed-ring-value">
                                    <strong>1 Gbps</strong>
                                    <span>Download</span>
                                </div>
                            </div>
                            <div class="hero-stats-grid">
                                <div class="hero-stat">
                                    <div class="hero-stat-value">1ms</div>
                                    <div class="hero-stat-label">Latency</div>
                                </div>
                                <div class="hero-stat">
                                    <div class="hero-stat-value">99.9%</div>
                                    <div class="hero-stat-label">Uptime</div>
                                </div>
                                <div class="hero-stat">
                                    <div class="hero-stat-value">24/7</div>
                                    <div class="hero-stat-label">Support</div>
                                </div>
                            </div>
                        </div>
                        <div class="hero-float-badge float-badge-1">
                            <i class="bi bi-lightning-charge-fill"></i>
                            <div>
                                <strong>Fiber Optic</strong>
                                <span>FTTH Connection</span>
                            </div>
                        </div>
                        <div class="hero-float-badge float-badge-2">
                            <i class="bi bi-shield-check" style="color: var(--accent);"></i>
                            <div>
                                <strong>Secure</strong>
                                <span>DDoS Protected</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section how-it-works">
        <div class="container">
            <div class="section-header animate-on-scroll">
                <div class="section-tag">Simple Process</div>
                <h2 class="section-title">How It Works</h2>
                <p class="section-subtitle">Get connected in three simple steps. We make switching to fiber easy and hassle-free.</p>
            </div>
            <div class="row g-4 justify-content-center">
                <div class="col-lg-4 col-md-6">
                    <div class="step-card animate-on-scroll">
                        <div class="step-number">1</div>
                        <h4 class="step-title">Choose Your Plan</h4>
                        <p class="step-desc">Browse our flexible packages and select the speed that best suits your home or business needs.</p>
                        <div class="step-connector d-none d-lg-block"></div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="step-card animate-on-scroll">
                        <div class="step-number">2</div>
                        <h4 class="step-title">We Install For Free</h4>
                        <p class="step-desc">Our certified technicians will install your fiber connection and set up your router at no extra cost.</p>
                        <div class="step-connector d-none d-lg-block"></div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="step-card animate-on-scroll">
                        <div class="step-number">3</div>
                        <h4 class="step-title">Enjoy Fast Internet</h4>
                        <p class="step-desc">Start streaming, gaming, and working with blazing-fast speeds and 24/7 dedicated support.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="about" class="section about-section">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-6">
                    <div class="about-image animate-on-scroll">
                        <img src="https://images.unsplash.com/photo-1544197150-b99a580bb7a8?w=600&h=500&fit=crop" alt="About Us" class="img-fluid">
                        <div class="about-badge">
                            <strong>10+</strong>
                            <span>Years Experience</span>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="about-content animate-on-scroll">
                        <div class="section-tag">About Us</div>
                        <h2><?= htmlspecialchars($company['company_name'] ?? 'Your Trusted ISP Partner') ?></h2>
                        <p>
                            <?= htmlspecialchars($landingSettings['about_text'] ?? 'We are a leading Internet Service Provider committed to delivering fast, reliable, and affordable internet solutions. Our state-of-the-art fiber network ensures you stay connected with the best speeds in the region.') ?>
                        </p>
                        <div class="about-features">
                            <div class="about-feature">
                                <div class="about-feature-icon"><i class="bi bi-shield-check"></i></div>
                                <div class="about-feature-text">Secure Network</div>
                            </div>
                            <div class="about-feature">
                                <div class="about-feature-icon"><i class="bi bi-speedometer2"></i></div>
                                <div class="about-feature-text">Ultra-Fast Speeds</div>
                            </div>
                            <div class="about-feature">
                                <div class="about-feature-icon"><i class="bi bi-headset"></i></div>
                                <div class="about-feature-text">24/7 Support</div>
                            </div>
                            <div class="about-feature">
                                <div class="about-feature-icon"><i class="bi bi-award"></i></div>
                                <div class="about-feature-text">Award Winning</div>
                            </div>
                        </div>
                        <a href="#contact" class="btn-hero-primary" style="display: inline-flex;">
                            <i class="bi bi-arrow-right"></i>Learn More
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="services" class="section services-section">
        <div class="container">
            <div class="section-header animate-on-scroll">
                <div class="section-tag">Our Services</div>
                <h2 class="section-title">What We Offer</h2>
                <p class="section-subtitle">Comprehensive internet solutions tailored to meet your needs, whether you're streaming, gaming, or running a business.</p>
            </div>
            <div class="row g-4">
                <div class="col-lg-3 col-md-6">
                    <div class="service-card animate-on-scroll">
                        <img src="https://images.unsplash.com/photo-1544197150-b99a580bb7a8?w=400&h=250&fit=crop" alt="Fiber Internet" class="service-card-img">
                        <div class="service-card-body">
                            <div class="service-icon blue"><i class="bi bi-router"></i></div>
                            <h4 class="service-title">Fiber Internet</h4>
                            <p class="service-desc">Blazing fast fiber optic internet with speeds up to 1Gbps for seamless streaming and gaming.</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="service-card animate-on-scroll">
                        <img src="https://images.unsplash.com/photo-1497366216548-37526070297c?w=400&h=250&fit=crop" alt="Business Solutions" class="service-card-img">
                        <div class="service-card-body">
                            <div class="service-icon green"><i class="bi bi-building"></i></div>
                            <h4 class="service-title">Business Solutions</h4>
                            <p class="service-desc">Dedicated business internet with guaranteed uptime, static IPs, and priority support.</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="service-card animate-on-scroll">
                        <img src="https://images.unsplash.com/photo-1516044734145-07ca8eef8731?w=400&h=250&fit=crop" alt="Wireless Networks" class="service-card-img">
                        <div class="service-card-body">
                            <div class="service-icon orange"><i class="bi bi-wifi"></i></div>
                            <h4 class="service-title">Wireless Networks</h4>
                            <p class="service-desc">High-speed wireless solutions for areas where fiber isn't available, with reliable coverage.</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="service-card animate-on-scroll">
                        <img src="https://images.unsplash.com/photo-1553775927-a071d5a6a39a?w=400&h=250&fit=crop" alt="24/7 Support" class="service-card-img">
                        <div class="service-card-body">
                            <div class="service-icon purple"><i class="bi bi-headset"></i></div>
                            <h4 class="service-title">24/7 Support</h4>
                            <p class="service-desc">Round-the-clock customer support via phone, email, and WhatsApp to resolve issues quickly.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="features" class="section features-section">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-6">
                    <div class="features-image animate-on-scroll">
                        <img src="https://images.unsplash.com/photo-1551434678-e076c223a692?w=600&h=500&fit=crop" alt="Why Choose Us">
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="animate-on-scroll">
                        <div class="section-tag">Why Choose Us</div>
                        <h2 class="section-title mb-4" style="text-align:left;">The Best ISP in Your Area</h2>
                        <ul class="feature-list">
                            <li>
                                <div class="f-icon"><i class="bi bi-lightning-charge"></i></div>
                                <div>
                                    <h5>Fastest Speeds</h5>
                                    <p>Get speeds up to 1Gbps with our cutting-edge fiber network infrastructure.</p>
                                </div>
                            </li>
                            <li>
                                <div class="f-icon"><i class="bi bi-graph-up-arrow"></i></div>
                                <div>
                                    <h5>99.9% Uptime</h5>
                                    <p>Our reliable network ensures you stay connected when it matters most.</p>
                                </div>
                            </li>
                            <li>
                                <div class="f-icon"><i class="bi bi-currency-dollar"></i></div>
                                <div>
                                    <h5>Affordable Plans</h5>
                                    <p>Competitive pricing with no hidden fees or surprise charges.</p>
                                </div>
                            </li>
                            <li>
                                <div class="f-icon"><i class="bi bi-tools"></i></div>
                                <div>
                                    <h5>Free Installation</h5>
                                    <p>Professional installation at no extra cost with a free WiFi router included.</p>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="packages" class="section packages-section">
        <div class="container">
            <div class="section-header animate-on-scroll">
                <div class="section-tag">Pricing Plans</div>
                <h2 class="section-title">Choose Your Package</h2>
                <p class="section-subtitle">Select the perfect plan that fits your needs. All packages include free installation and router.</p>
            </div>
            <div class="row g-4 justify-content-center">
                <?php if (empty($packages)): ?>
                <div class="col-lg-4 col-md-6">
                    <div class="package-card animate-on-scroll">
                        <div class="package-icon"><i class="bi bi-house"></i></div>
                        <h3 class="package-name">Basic Home</h3>
                        <div class="package-speed">
                            <span class="package-speed-value">10</span>
                            <span class="package-speed-unit">Mbps</span>
                        </div>
                        <div class="package-price">
                            <span class="package-currency">KES</span>
                            <span class="package-amount">1,500</span>
                            <span class="package-period">/month</span>
                        </div>
                        <ul class="package-features">
                            <li><i class="bi bi-check-circle-fill"></i> Unlimited Data</li>
                            <li><i class="bi bi-check-circle-fill"></i> Free Installation</li>
                            <li><i class="bi bi-check-circle-fill"></i> Free WiFi Router</li>
                            <li><i class="bi bi-check-circle-fill"></i> 24/7 Support</li>
                        </ul>
                        <a href="?page=order" class="btn package-btn outline">Select Plan</a>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="package-card popular animate-on-scroll">
                        <span class="package-badge">Most Popular</span>
                        <div class="package-icon"><i class="bi bi-speedometer2"></i></div>
                        <h3 class="package-name">Family Plus</h3>
                        <div class="package-speed">
                            <span class="package-speed-value">30</span>
                            <span class="package-speed-unit">Mbps</span>
                        </div>
                        <div class="package-price">
                            <span class="package-currency">KES</span>
                            <span class="package-amount">2,500</span>
                            <span class="package-period">/month</span>
                        </div>
                        <ul class="package-features">
                            <li><i class="bi bi-check-circle-fill"></i> Unlimited Data</li>
                            <li><i class="bi bi-check-circle-fill"></i> Free Installation</li>
                            <li><i class="bi bi-check-circle-fill"></i> Free WiFi Router</li>
                            <li><i class="bi bi-check-circle-fill"></i> Priority Support</li>
                        </ul>
                        <a href="?page=order" class="btn package-btn primary">Select Plan</a>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="package-card animate-on-scroll">
                        <div class="package-icon"><i class="bi bi-lightning-charge"></i></div>
                        <h3 class="package-name">Business Pro</h3>
                        <div class="package-speed">
                            <span class="package-speed-value">100</span>
                            <span class="package-speed-unit">Mbps</span>
                        </div>
                        <div class="package-price">
                            <span class="package-currency">KES</span>
                            <span class="package-amount">5,000</span>
                            <span class="package-period">/month</span>
                        </div>
                        <ul class="package-features">
                            <li><i class="bi bi-check-circle-fill"></i> Unlimited Data</li>
                            <li><i class="bi bi-check-circle-fill"></i> Static IP Address</li>
                            <li><i class="bi bi-check-circle-fill"></i> Business Router</li>
                            <li><i class="bi bi-check-circle-fill"></i> Dedicated Support</li>
                        </ul>
                        <a href="?page=order" class="btn package-btn outline">Select Plan</a>
                    </div>
                </div>
                <?php else: ?>
                    <?php 
                    $totalPackages = count($packages);
                    $middleIndex = floor($totalPackages / 2);
                    foreach ($packages as $index => $pkg): 
                        $isPopular = ($index === $middleIndex);
                        $speed = $pkg['speed'] ?? '10';
                        $speedNum = preg_replace('/[^0-9]/', '', $speed);
                        $speedUnit = preg_replace('/[0-9]/', '', $speed) ?: 'Mbps';
                    ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="package-card <?= $isPopular ? 'popular' : '' ?> animate-on-scroll">
                            <?php if ($isPopular): ?>
                            <span class="package-badge">Most Popular</span>
                            <?php endif; ?>
                            <div class="package-icon">
                                <i class="bi bi-<?= $index === 0 ? 'house' : ($isPopular ? 'speedometer2' : 'lightning-charge') ?>"></i>
                            </div>
                            <h3 class="package-name"><?= htmlspecialchars($pkg['name']) ?></h3>
                            <div class="package-speed">
                                <span class="package-speed-value"><?= htmlspecialchars($speedNum) ?></span>
                                <span class="package-speed-unit"><?= htmlspecialchars($speedUnit) ?></span>
                            </div>
                            <div class="package-price">
                                <span class="package-currency">KES</span>
                                <span class="package-amount"><?= number_format($pkg['price']) ?></span>
                                <span class="package-period">/month</span>
                            </div>
                            <ul class="package-features">
                                <?php 
                                $features = [];
                                if (!empty($pkg['features'])) {
                                    $features = is_string($pkg['features']) ? json_decode($pkg['features'], true) : $pkg['features'];
                                }
                                if (empty($features)) {
                                    $features = ['Unlimited Data', 'Free Installation', 'Free WiFi Router', '24/7 Support'];
                                }
                                foreach ($features as $feature): 
                                ?>
                                <li><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($feature) ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <a href="?page=order&package=<?= $pkg['id'] ?>" class="btn package-btn <?= $isPopular ? 'primary' : 'outline' ?>">
                                Select Plan
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="section testimonials-section">
        <div class="container">
            <div class="section-header animate-on-scroll">
                <div class="section-tag">Testimonials</div>
                <h2 class="section-title">What Our Customers Say</h2>
                <p class="section-subtitle">Don't just take our word for it. Here's what our satisfied customers have to say.</p>
            </div>
            <div class="row g-4">
                <div class="col-lg-4 col-md-6">
                    <div class="testimonial-card animate-on-scroll">
                        <i class="bi bi-quote testimonial-quote"></i>
                        <p class="testimonial-text">"The internet speed is incredible! I can now work from home, stream movies, and my kids can game online - all at the same time without any lag."</p>
                        <div class="testimonial-author">
                            <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=100&h=100&fit=crop&crop=face" alt="Customer" class="testimonial-avatar">
                            <div class="testimonial-info">
                                <h5>James Mwangi</h5>
                                <span>Home User, Nairobi</span>
                                <div class="testimonial-rating">
                                    <i class="bi bi-star-fill"></i>
                                    <i class="bi bi-star-fill"></i>
                                    <i class="bi bi-star-fill"></i>
                                    <i class="bi bi-star-fill"></i>
                                    <i class="bi bi-star-fill"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="testimonial-card animate-on-scroll">
                        <i class="bi bi-quote testimonial-quote"></i>
                        <p class="testimonial-text">"As a business owner, reliable internet is crucial. Their 99.9% uptime guarantee has been true - we haven't experienced any significant downtime in over a year."</p>
                        <div class="testimonial-author">
                            <img src="https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=100&h=100&fit=crop&crop=face" alt="Customer" class="testimonial-avatar">
                            <div class="testimonial-info">
                                <h5>Sarah Wanjiku</h5>
                                <span>Business Owner, Karen</span>
                                <div class="testimonial-rating">
                                    <i class="bi bi-star-fill"></i>
                                    <i class="bi bi-star-fill"></i>
                                    <i class="bi bi-star-fill"></i>
                                    <i class="bi bi-star-fill"></i>
                                    <i class="bi bi-star-fill"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="testimonial-card animate-on-scroll">
                        <i class="bi bi-quote testimonial-quote"></i>
                        <p class="testimonial-text">"Excellent customer support! When I had an issue, their team responded within minutes via WhatsApp and resolved it the same day. Highly recommend!"</p>
                        <div class="testimonial-author">
                            <img src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=100&h=100&fit=crop&crop=face" alt="Customer" class="testimonial-avatar">
                            <div class="testimonial-info">
                                <h5>David Ochieng</h5>
                                <span>Software Developer, Westlands</span>
                                <div class="testimonial-rating">
                                    <i class="bi bi-star-fill"></i>
                                    <i class="bi bi-star-fill"></i>
                                    <i class="bi bi-star-fill"></i>
                                    <i class="bi bi-star-fill"></i>
                                    <i class="bi bi-star-half"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="stats-section">
        <div class="container position-relative">
            <div class="row g-4">
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card animate-on-scroll">
                        <div class="stat-value" data-count="5000">0</div>
                        <div class="stat-label">Happy Customers</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card animate-on-scroll">
                        <div class="stat-value" data-count="500">0</div>
                        <div class="stat-label">Businesses Served</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card animate-on-scroll">
                        <div class="stat-value" data-count="1000">0</div>
                        <div class="stat-label">Active Connections</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card animate-on-scroll">
                        <div class="stat-value">99.9%</div>
                        <div class="stat-label">Network Uptime</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section faq-section">
        <div class="container">
            <div class="section-header animate-on-scroll">
                <div class="section-tag">FAQ</div>
                <h2 class="section-title">Frequently Asked Questions</h2>
                <p class="section-subtitle">Find answers to common questions about our internet services.</p>
            </div>
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="animate-on-scroll">
                        <div class="faq-item">
                            <button class="faq-question" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                What internet speeds do you offer?
                                <i class="bi bi-chevron-down"></i>
                            </button>
                            <div id="faq1" class="collapse show">
                                <div class="faq-answer">We offer a range of speeds from 10 Mbps to 1 Gbps, depending on your location and package. Our fiber plans deliver symmetrical upload and download speeds, perfect for working from home, streaming, and gaming.</div>
                            </div>
                        </div>
                        <div class="faq-item">
                            <button class="faq-question collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                Is installation really free?
                                <i class="bi bi-chevron-down"></i>
                            </button>
                            <div id="faq2" class="collapse">
                                <div class="faq-answer">Yes! We provide free standard installation for all residential and business packages. This includes running the fiber cable to your premises and setting up the WiFi router. Non-standard installations may incur additional charges.</div>
                            </div>
                        </div>
                        <div class="faq-item">
                            <button class="faq-question collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                How long does installation take?
                                <i class="bi bi-chevron-down"></i>
                            </button>
                            <div id="faq3" class="collapse">
                                <div class="faq-answer">Once you place your order, installation is typically completed within 24-48 hours in areas where our fiber infrastructure is already available. For new areas, it may take 3-5 business days.</div>
                            </div>
                        </div>
                        <div class="faq-item">
                            <button class="faq-question collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                                Do you have data caps or limits?
                                <i class="bi bi-chevron-down"></i>
                            </button>
                            <div id="faq4" class="collapse">
                                <div class="faq-answer">All our fiber packages come with truly unlimited data. There are no hidden data caps, throttling, or fair usage policies. Enjoy unrestricted internet usage 24/7.</div>
                            </div>
                        </div>
                        <div class="faq-item">
                            <button class="faq-question collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq5">
                                What payment methods do you accept?
                                <i class="bi bi-chevron-down"></i>
                            </button>
                            <div id="faq5" class="collapse">
                                <div class="faq-answer">We accept M-Pesa, bank transfers, credit/debit cards, and cash payments. Monthly billing can be set up with automatic M-Pesa payments for your convenience.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php 
    $contactSettings = $contactSettings ?? [];
    ?>
    <section id="contact" class="section contact-section">
        <div class="container">
            <div class="section-header animate-on-scroll">
                <div class="section-tag">Get In Touch</div>
                <h2 class="section-title">Contact Us</h2>
                <p class="section-subtitle">Have questions? We're here to help. Reach out through any of these channels.</p>
            </div>
            <div class="row g-4 justify-content-center mb-5">
                <?php $phone = $contactSettings['contact_phone'] ?? $company['company_phone'] ?? ''; ?>
                <?php if (!empty($phone)): ?>
                <div class="col-lg-3 col-md-6">
                    <div class="contact-card animate-on-scroll">
                        <div class="contact-icon"><i class="bi bi-telephone"></i></div>
                        <div class="contact-label">Phone</div>
                        <div class="contact-value">
                            <a href="tel:<?= htmlspecialchars($phone) ?>"><?= htmlspecialchars($phone) ?></a>
                        </div>
                        <?php if (!empty($contactSettings['contact_phone_2'])): ?>
                        <div class="contact-value mt-2">
                            <a href="tel:<?= htmlspecialchars($contactSettings['contact_phone_2']) ?>"><?= htmlspecialchars($contactSettings['contact_phone_2']) ?></a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php $email = $contactSettings['contact_email'] ?? $company['company_email'] ?? ''; ?>
                <?php if (!empty($email)): ?>
                <div class="col-lg-3 col-md-6">
                    <div class="contact-card animate-on-scroll">
                        <div class="contact-icon"><i class="bi bi-envelope"></i></div>
                        <div class="contact-label">Email</div>
                        <div class="contact-value">
                            <a href="mailto:<?= htmlspecialchars($email) ?>"><?= htmlspecialchars($email) ?></a>
                        </div>
                        <?php if (!empty($contactSettings['contact_email_support'])): ?>
                        <div class="contact-value mt-2">
                            <small>Support:</small><br>
                            <a href="mailto:<?= htmlspecialchars($contactSettings['contact_email_support']) ?>"><?= htmlspecialchars($contactSettings['contact_email_support']) ?></a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php $address = $contactSettings['contact_address'] ?? $company['company_address'] ?? ''; ?>
                <?php if (!empty($address)): ?>
                <div class="col-lg-3 col-md-6">
                    <div class="contact-card animate-on-scroll">
                        <div class="contact-icon"><i class="bi bi-geo-alt"></i></div>
                        <div class="contact-label">Address</div>
                        <div class="contact-value">
                            <?= htmlspecialchars($address) ?>
                            <?php if (!empty($contactSettings['contact_city'])): ?>
                            <br><?= htmlspecialchars($contactSettings['contact_city']) ?>, <?= htmlspecialchars($contactSettings['contact_country'] ?? 'Kenya') ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="col-lg-3 col-md-6">
                    <div class="contact-card animate-on-scroll">
                        <div class="contact-icon"><i class="bi bi-clock"></i></div>
                        <div class="contact-label">Working Hours</div>
                        <div class="contact-value">
                            <?= htmlspecialchars($contactSettings['working_days'] ?? 'Monday - Friday') ?>
                            <br><?= htmlspecialchars($contactSettings['working_hours'] ?? '8:00 AM - 5:00 PM') ?>
                            <br><small style="color: var(--gray-500);">Support: <?= htmlspecialchars($contactSettings['support_hours'] ?? '24/7') ?></small>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!empty($contactSettings['map_embed_url'])): ?>
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="map-container animate-on-scroll">
                        <iframe src="<?= htmlspecialchars($contactSettings['map_embed_url']) ?>"
                                width="100%" height="400" style="border:0;" allowfullscreen="" loading="lazy"></iframe>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="cta-section">
        <div class="container position-relative">
            <h2 class="cta-title animate-on-scroll">Ready to Get Connected?</h2>
            <p class="cta-subtitle animate-on-scroll">Join thousands of satisfied customers enjoying high-speed internet. Get started today!</p>
            <?php $whatsappNum = preg_replace('/[^0-9]/', '', $contactSettings['contact_whatsapp'] ?? $contactSettings['contact_phone'] ?? ''); ?>
            <div class="cta-buttons animate-on-scroll">
                <a href="?page=order" class="btn-cta-primary">
                    <i class="bi bi-rocket-takeoff"></i>Get Started Today
                </a>
                <?php if (!empty($whatsappNum)): ?>
                <a href="https://wa.me/<?= $whatsappNum ?>?text=Hi,%20I'm%20interested%20in%20your%20internet%20services" class="btn-cta-secondary" target="_blank">
                    <i class="bi bi-whatsapp"></i>Contact Sales
                </a>
                <?php else: ?>
                <a href="#contact" class="btn-cta-secondary">
                    <i class="bi bi-telephone"></i>Contact Sales
                </a>
                <?php endif; ?>
            </div>

            <?php
            $hasSocial = !empty($contactSettings['social_facebook']) || !empty($contactSettings['social_twitter']) ||
                         !empty($contactSettings['social_instagram']) || !empty($contactSettings['social_linkedin']);
            if ($hasSocial): ?>
            <div class="social-links animate-on-scroll">
                <?php if (!empty($contactSettings['social_facebook'])): ?>
                <a href="<?= htmlspecialchars($contactSettings['social_facebook']) ?>" class="social-link" target="_blank" title="Facebook"><i class="bi bi-facebook"></i></a>
                <?php endif; ?>
                <?php if (!empty($contactSettings['social_twitter'])): ?>
                <a href="<?= htmlspecialchars($contactSettings['social_twitter']) ?>" class="social-link" target="_blank" title="Twitter"><i class="bi bi-twitter-x"></i></a>
                <?php endif; ?>
                <?php if (!empty($contactSettings['social_instagram'])): ?>
                <a href="<?= htmlspecialchars($contactSettings['social_instagram']) ?>" class="social-link" target="_blank" title="Instagram"><i class="bi bi-instagram"></i></a>
                <?php endif; ?>
                <?php if (!empty($contactSettings['social_linkedin'])): ?>
                <a href="<?= htmlspecialchars($contactSettings['social_linkedin']) ?>" class="social-link" target="_blank" title="LinkedIn"><i class="bi bi-linkedin"></i></a>
                <?php endif; ?>
                <?php if (!empty($contactSettings['social_youtube'])): ?>
                <a href="<?= htmlspecialchars($contactSettings['social_youtube']) ?>" class="social-link" target="_blank" title="YouTube"><i class="bi bi-youtube"></i></a>
                <?php endif; ?>
                <?php if (!empty($contactSettings['social_tiktok'])): ?>
                <a href="<?= htmlspecialchars($contactSettings['social_tiktok']) ?>" class="social-link" target="_blank" title="TikTok"><i class="bi bi-tiktok"></i></a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <footer>
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <div class="footer-brand">
                        <span class="brand-icon"><i class="bi bi-broadcast-pin"></i></span>
                        <?= htmlspecialchars($company['company_name'] ?? 'ISP Provider') ?>
                    </div>
                    <p class="footer-desc">
                        <?= htmlspecialchars($landingSettings['footer_text'] ?? 'Your trusted partner for fast, reliable internet connectivity. Connecting homes and businesses with cutting-edge technology.') ?>
                    </p>
                    <?php if ($hasSocial): ?>
                    <div class="d-flex gap-2">
                        <?php if (!empty($contactSettings['social_facebook'])): ?>
                        <a href="<?= htmlspecialchars($contactSettings['social_facebook']) ?>" class="social-link" target="_blank"><i class="bi bi-facebook"></i></a>
                        <?php endif; ?>
                        <?php if (!empty($contactSettings['social_twitter'])): ?>
                        <a href="<?= htmlspecialchars($contactSettings['social_twitter']) ?>" class="social-link" target="_blank"><i class="bi bi-twitter-x"></i></a>
                        <?php endif; ?>
                        <?php if (!empty($contactSettings['social_instagram'])): ?>
                        <a href="<?= htmlspecialchars($contactSettings['social_instagram']) ?>" class="social-link" target="_blank"><i class="bi bi-instagram"></i></a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="col-lg-2 col-md-4 col-6 mb-4">
                    <h6 class="footer-title">Quick Links</h6>
                    <ul class="footer-links">
                        <li><a href="#home">Home</a></li>
                        <li><a href="#about">About Us</a></li>
                        <li><a href="#packages">Packages</a></li>
                        <li><a href="#contact">Contact</a></li>
                    </ul>
                </div>
                <div class="col-lg-2 col-md-4 col-6 mb-4">
                    <h6 class="footer-title">Services</h6>
                    <ul class="footer-links">
                        <li><a href="#services">Home Internet</a></li>
                        <li><a href="#services">Business Internet</a></li>
                        <li><a href="#services">Fiber Optic</a></li>
                        <li><a href="#services">Wireless</a></li>
                    </ul>
                </div>
                <div class="col-lg-2 col-md-4 col-6 mb-4">
                    <h6 class="footer-title">Support</h6>
                    <ul class="footer-links">
                        <li><a href="?page=login">Login</a></li>
                        <li><a href="#contact">Help Center</a></li>
                        <li><a href="#" data-bs-toggle="modal" data-bs-target="#complaintModal">Report Issue</a></li>
                        <li><a href="#faq">FAQs</a></li>
                    </ul>
                </div>
                <div class="col-lg-2 col-md-4 col-6 mb-4">
                    <h6 class="footer-title">Contact</h6>
                    <?php if (!empty($phone)): ?>
                    <div class="footer-contact-item">
                        <i class="bi bi-telephone"></i>
                        <div>
                            <a href="tel:<?= htmlspecialchars($phone) ?>" style="color: #64748B; text-decoration: none;"><?= htmlspecialchars($phone) ?></a>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($email)): ?>
                    <div class="footer-contact-item">
                        <i class="bi bi-envelope"></i>
                        <div>
                            <a href="mailto:<?= htmlspecialchars($email) ?>" style="color: #64748B; text-decoration: none;"><?= htmlspecialchars($email) ?></a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="footer-bottom">
                <div class="footer-bottom-text">
                    &copy; <?= date('Y') ?> <?= htmlspecialchars($company['company_name'] ?? 'ISP Provider') ?>. All rights reserved.
                </div>
                <div class="footer-bottom-links">
                    <a href="#">Terms of Service</a>
                    <a href="#">Privacy Policy</a>
                </div>
            </div>
        </div>
    </footer>

    <?php $whatsapp = $contactSettings['contact_whatsapp'] ?? ''; ?>
    <?php if (!empty($whatsapp)): ?>
    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $whatsapp) ?>" class="whatsapp-float" target="_blank" title="Chat on WhatsApp">
        <i class="bi bi-whatsapp"></i>
    </a>
    <?php endif; ?>

    <div class="modal fade" id="complaintModal" tabindex="-1" aria-labelledby="complaintModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 16px; border: none;">
                <div class="modal-header" style="background: linear-gradient(135deg, #EF4444, #DC2626); border-radius: 16px 16px 0 0; border: none;">
                    <h5 class="modal-title text-white" id="complaintModalLabel">
                        <i class="bi bi-exclamation-triangle me-2"></i>Report an Issue
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="complaintForm" action="?page=submit_complaint" method="POST">
                    <div class="modal-body" style="padding: 1.5rem;">
                        <div id="complaintSuccess" class="alert alert-success d-none">
                            <i class="bi bi-check-circle me-2"></i>Your complaint has been submitted successfully. We'll get back to you soon.
                        </div>
                        <div id="complaintError" class="alert alert-danger d-none"></div>
                        <div class="mb-3">
                            <label class="form-label fw-medium">Your Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" required style="border-radius: 10px; padding: 0.7rem 1rem;">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-medium">Phone Number <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control" name="phone" placeholder="e.g., 0712345678" required style="border-radius: 10px; padding: 0.7rem 1rem;">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-medium">Email</label>
                            <input type="email" class="form-control" name="email" style="border-radius: 10px; padding: 0.7rem 1rem;">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-medium">Issue Category <span class="text-danger">*</span></label>
                            <select class="form-select" name="category" required style="border-radius: 10px; padding: 0.7rem 1rem;">
                                <option value="">Select category...</option>
                                <option value="connectivity">Internet Connectivity</option>
                                <option value="speed">Slow Speed</option>
                                <option value="billing">Billing Issue</option>
                                <option value="equipment">Equipment Problem</option>
                                <option value="service">Service Quality</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-medium">Subject <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="subject" placeholder="Brief summary of your issue" required style="border-radius: 10px; padding: 0.7rem 1rem;">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-medium">Description <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="description" rows="4" placeholder="Please describe your issue in detail..." required style="border-radius: 10px; padding: 0.7rem 1rem;"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-medium">Location/Address</label>
                            <input type="text" class="form-control" name="location" placeholder="Your location or service address" style="border-radius: 10px; padding: 0.7rem 1rem;">
                        </div>
                        <input type="hidden" name="honeypot" value="">
                    </div>
                    <div class="modal-footer" style="border: none; padding: 0 1.5rem 1.5rem;">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal" style="border-radius: 10px; padding: 0.6rem 1.5rem;">Cancel</button>
                        <button type="submit" class="btn btn-danger" id="submitComplaint" style="border-radius: 10px; padding: 0.6rem 1.5rem;">
                            <i class="bi bi-send me-2"></i>Submit Complaint
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (function() {
            const canvas = document.getElementById('heroCanvas');
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            let particles = [];
            let w, h;

            function resize() {
                w = canvas.width = canvas.offsetWidth;
                h = canvas.height = canvas.offsetHeight;
            }

            function initParticles() {
                particles = [];
                const count = Math.min(80, Math.floor(w * h / 15000));
                for (let i = 0; i < count; i++) {
                    particles.push({
                        x: Math.random() * w,
                        y: Math.random() * h,
                        vx: (Math.random() - 0.5) * 0.5,
                        vy: (Math.random() - 0.5) * 0.5,
                        r: Math.random() * 2 + 1,
                        o: Math.random() * 0.5 + 0.1
                    });
                }
            }

            function draw() {
                ctx.clearRect(0, 0, w, h);
                for (let i = 0; i < particles.length; i++) {
                    const p = particles[i];
                    p.x += p.vx;
                    p.y += p.vy;
                    if (p.x < 0) p.x = w;
                    if (p.x > w) p.x = 0;
                    if (p.y < 0) p.y = h;
                    if (p.y > h) p.y = 0;
                    ctx.beginPath();
                    ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
                    ctx.fillStyle = 'rgba(148, 163, 184, ' + p.o + ')';
                    ctx.fill();
                    for (let j = i + 1; j < particles.length; j++) {
                        const p2 = particles[j];
                        const dx = p.x - p2.x;
                        const dy = p.y - p2.y;
                        const dist = Math.sqrt(dx * dx + dy * dy);
                        if (dist < 150) {
                            ctx.beginPath();
                            ctx.moveTo(p.x, p.y);
                            ctx.lineTo(p2.x, p2.y);
                            ctx.strokeStyle = 'rgba(148, 163, 184, ' + (0.08 * (1 - dist / 150)) + ')';
                            ctx.lineWidth = 0.5;
                            ctx.stroke();
                        }
                    }
                }
                requestAnimationFrame(draw);
            }

            resize();
            initParticles();
            draw();
            window.addEventListener('resize', function() { resize(); initParticles(); });
        })();

        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                if (href === '#') return;
                e.preventDefault();
                const target = document.querySelector(href);
                if (target) {
                    const navHeight = document.querySelector('.navbar').offsetHeight;
                    const targetPosition = target.getBoundingClientRect().top + window.pageYOffset - navHeight;
                    window.scrollTo({ top: targetPosition, behavior: 'smooth' });
                }
            });
        });

        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });

        const observerOptions = { threshold: 0.1, rootMargin: '0px 0px -50px 0px' };
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    if (entry.target.querySelector('.stat-value[data-count]')) {
                        entry.target.querySelectorAll('.stat-value[data-count]').forEach(el => {
                            const target = parseInt(el.dataset.count);
                            let current = 0;
                            const increment = target / 60;
                            const timer = setInterval(() => {
                                current += increment;
                                if (current >= target) {
                                    el.textContent = target.toLocaleString() + '+';
                                    clearInterval(timer);
                                } else {
                                    el.textContent = Math.floor(current).toLocaleString() + '+';
                                }
                            }, 16);
                        });
                    }
                }
            });
        }, observerOptions);
        document.querySelectorAll('.animate-on-scroll').forEach(el => observer.observe(el));

        document.getElementById('complaintForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const form = this;
            const submitBtn = document.getElementById('submitComplaint');
            const successDiv = document.getElementById('complaintSuccess');
            const errorDiv = document.getElementById('complaintError');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';
            successDiv.classList.add('d-none');
            errorDiv.classList.add('d-none');
            fetch(form.action, { method: 'POST', body: new FormData(form) })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    successDiv.innerHTML = '<i class="bi bi-check-circle me-2"></i>' + data.message;
                    if (data.complaint_number) {
                        successDiv.innerHTML += '<br><small>Reference: ' + data.complaint_number + '</small>';
                    }
                    successDiv.classList.remove('d-none');
                    form.reset();
                    setTimeout(() => {
                        const modal = bootstrap.Modal.getInstance(document.getElementById('complaintModal'));
                        if (modal) modal.hide();
                    }, 3000);
                } else {
                    errorDiv.textContent = data.error || 'An error occurred. Please try again.';
                    errorDiv.classList.remove('d-none');
                }
            })
            .catch(error => {
                errorDiv.textContent = 'Network error. Please try again.';
                errorDiv.classList.remove('d-none');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-send me-2"></i>Submit Complaint';
            });
        });
    </script>
</body>
</html>