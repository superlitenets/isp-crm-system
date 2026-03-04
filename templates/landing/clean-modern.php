<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($company['company_name'] ?? 'ISP Provider') ?> - High-Speed Internet Services</title>
    <meta name="description" content="<?= htmlspecialchars($landingSettings['hero_subtitle'] ?? 'Fast, reliable internet for your home and business') ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: <?= htmlspecialchars($landingSettings['primary_color'] ?? '#4F46E5') ?>;
            --primary-rgb: 79, 70, 229;
            --primary-dark: #3730A3;
            --primary-light: #EEF2FF;
            --secondary: #06B6D4;
            --accent: #10B981;
            --accent-rgb: 16, 185, 129;
            --gray-900: #111827;
            --gray-800: #1F2937;
            --gray-700: #374151;
            --gray-600: #6B7280;
            --gray-500: #9CA3AF;
            --gray-400: #D1D5DB;
            --gray-300: #E5E7EB;
            --gray-200: #F3F4F6;
            --gray-100: #F9FAFB;
            --white: #FFFFFF;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            color: var(--gray-900);
            overflow-x: hidden;
            background: var(--white);
        }

        .navbar {
            padding: 1rem 0;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            transition: all 0.3s ease;
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid transparent;
        }

        .navbar.scrolled {
            box-shadow: 0 1px 20px rgba(0,0,0,0.06);
            border-bottom: 1px solid var(--gray-300);
            padding: 0.6rem 0;
        }

        .navbar-brand {
            font-weight: 800;
            font-size: 1.4rem;
            color: var(--gray-900) !important;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .navbar-brand .brand-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--primary);
            display: inline-block;
        }

        .nav-link {
            font-weight: 500;
            font-size: 0.95rem;
            color: var(--gray-600) !important;
            padding: 0.5rem 1rem !important;
            transition: color 0.3s ease;
        }

        .nav-link:hover { color: var(--primary) !important; }

        .btn-nav-login {
            color: var(--gray-700) !important;
            padding: 0.5rem 1.25rem !important;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            border: 1px solid var(--gray-300);
            background: transparent;
        }

        .btn-nav-login:hover {
            background: var(--gray-100);
            border-color: var(--primary);
            color: var(--primary) !important;
        }

        .btn-nav-cta {
            background: var(--primary);
            color: var(--white) !important;
            padding: 0.5rem 1.5rem !important;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            border: none;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(var(--primary-rgb), 0.25);
        }

        .btn-nav-cta:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 16px rgba(var(--primary-rgb), 0.35);
        }

        .navbar-toggler {
            border-color: var(--gray-300);
            padding: 0.4rem 0.6rem;
        }

        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%2855, 65, 81, 0.85%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        }

        .hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
            background: linear-gradient(180deg, var(--white) 0%, var(--gray-100) 100%);
            padding-top: 80px;
        }

        .hero-wave {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            z-index: 1;
        }

        .hero-content {
            position: relative;
            z-index: 2;
        }

        .hero-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--primary-light);
            color: var(--primary);
            padding: 0.4rem 1rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }

        .hero-tag i { font-size: 0.9rem; }

        .hero-title {
            font-size: 3.5rem;
            font-weight: 800;
            color: var(--gray-900);
            line-height: 1.15;
            margin-bottom: 1.25rem;
            letter-spacing: -0.03em;
        }

        .hero-title .highlight {
            color: var(--primary);
            position: relative;
        }

        .hero-title .highlight::after {
            content: '';
            position: absolute;
            bottom: 4px;
            left: 0;
            width: 100%;
            height: 8px;
            background: rgba(var(--primary-rgb), 0.15);
            border-radius: 4px;
            z-index: -1;
        }

        .hero-description {
            font-size: 1.1rem;
            color: var(--gray-600);
            line-height: 1.8;
            margin-bottom: 2rem;
            max-width: 520px;
        }

        .hero-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 2.5rem;
        }

        .btn-primary-gradient {
            background: var(--primary);
            color: var(--white);
            padding: 0.875rem 2rem;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1rem;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            transition: all 0.3s ease;
            box-shadow: 0 4px 14px rgba(var(--primary-rgb), 0.3);
        }

        .btn-primary-gradient:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 24px rgba(var(--primary-rgb), 0.4);
            color: var(--white);
        }

        .btn-outline-soft {
            background: var(--white);
            border: 1.5px solid var(--gray-300);
            color: var(--gray-700);
            padding: 0.875rem 2rem;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .btn-outline-soft:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-light);
        }

        .btn-outline-danger-soft {
            background: transparent;
            border: 1.5px solid #FCA5A5;
            color: #DC2626;
            padding: 0.875rem 2rem;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-outline-danger-soft:hover {
            background: #FEF2F2;
            border-color: #EF4444;
            color: #DC2626;
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
            color: var(--gray-500);
            font-size: 0.9rem;
        }

        .hero-trust-item i { color: var(--accent); font-size: 1rem; }

        .hero-visual {
            position: relative;
            z-index: 2;
        }

        .hero-stats-card {
            background: var(--white);
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: 0 20px 60px rgba(0,0,0,0.08);
            border: 1px solid var(--gray-300);
        }

        .hero-stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }

        .hero-stat-box {
            text-align: center;
            padding: 1.25rem;
            background: var(--gray-100);
            border-radius: 14px;
            transition: all 0.3s ease;
        }

        .hero-stat-box:hover { background: var(--primary-light); }

        .hero-stat-value {
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--primary);
        }

        .hero-stat-label {
            font-size: 0.8rem;
            color: var(--gray-500);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 0.25rem;
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
            font-size: 2.5rem;
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

        .features-section {
            background: var(--white);
        }

        .feature-card {
            background: var(--white);
            border: 1px solid var(--gray-300);
            border-radius: 16px;
            padding: 2rem;
            height: 100%;
            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: var(--primary);
            transform: scaleX(0);
            transition: transform 0.4s ease;
            transform-origin: left;
        }

        .feature-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 16px 40px rgba(0,0,0,0.06);
            border-color: transparent;
        }

        .feature-card:hover::before { transform: scaleX(1); }

        .feature-icon {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            background: var(--primary-light);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            margin-bottom: 1.25rem;
            transition: all 0.3s ease;
        }

        .feature-card:hover .feature-icon {
            background: var(--primary);
            color: var(--white);
        }

        .feature-title {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 0.75rem;
        }

        .feature-desc {
            color: var(--gray-600);
            font-size: 0.95rem;
            line-height: 1.7;
        }

        .packages-section {
            background: var(--gray-100);
        }

        .package-card {
            background: var(--white);
            border: 1px solid var(--gray-300);
            border-radius: 20px;
            padding: 2.5rem 2rem;
            text-align: center;
            height: 100%;
            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
        }

        .package-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 50px rgba(0,0,0,0.08);
        }

        .package-card.popular {
            border: 2px solid var(--primary);
            box-shadow: 0 10px 30px rgba(var(--primary-rgb), 0.12);
        }

        .package-badge {
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            background: var(--primary);
            color: var(--white);
            padding: 0.35rem 1.5rem;
            border-radius: 0 0 12px 12px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .package-icon {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            background: var(--primary-light);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin: 0 auto 1.25rem;
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
            color: var(--primary);
            line-height: 1;
        }

        .package-speed-unit {
            font-size: 1rem;
            color: var(--gray-500);
            font-weight: 500;
        }

        .package-price {
            margin-bottom: 1.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--gray-300);
        }

        .package-currency {
            font-size: 1rem;
            font-weight: 600;
            color: var(--gray-500);
            vertical-align: super;
        }

        .package-amount {
            font-size: 2.25rem;
            font-weight: 800;
            color: var(--gray-900);
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
        }

        .package-features li {
            padding: 0.5rem 0;
            color: var(--gray-700);
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .package-features li i {
            color: var(--accent);
            font-size: 0.9rem;
        }

        .package-btn {
            width: 100%;
            padding: 0.75rem;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .package-btn.primary {
            background: var(--primary);
            color: var(--white);
            border: none;
            box-shadow: 0 4px 14px rgba(var(--primary-rgb), 0.3);
        }

        .package-btn.primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(var(--primary-rgb), 0.4);
            color: var(--white);
        }

        .package-btn.outline {
            background: transparent;
            color: var(--primary);
            border: 1.5px solid var(--primary);
        }

        .package-btn.outline:hover {
            background: var(--primary);
            color: var(--white);
        }

        .stats-section {
            background: var(--primary);
            padding: 5rem 0;
        }

        .stat-card {
            text-align: center;
            padding: 1.5rem;
        }

        .stat-value {
            font-size: 3rem;
            font-weight: 800;
            color: var(--white);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: rgba(255,255,255,0.75);
            text-transform: uppercase;
            letter-spacing: 1.5px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .testimonials-section {
            background: var(--white);
        }

        .testimonial-card {
            background: var(--gray-100);
            border-radius: 16px;
            padding: 2rem;
            height: 100%;
            border: 1px solid var(--gray-300);
            transition: all 0.3s ease;
            position: relative;
        }

        .testimonial-card:hover {
            box-shadow: 0 10px 30px rgba(0,0,0,0.06);
            border-color: transparent;
        }

        .testimonial-quote {
            font-size: 2rem;
            color: var(--primary);
            opacity: 0.2;
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
            width: 48px;
            height: 48px;
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

        .contact-section {
            background: var(--gray-100);
        }

        .contact-card {
            background: var(--white);
            border-radius: 16px;
            padding: 2rem;
            text-align: center;
            height: 100%;
            transition: all 0.3s ease;
            border: 1px solid var(--gray-300);
        }

        .contact-card:hover {
            box-shadow: 0 10px 30px rgba(var(--primary-rgb), 0.08);
            transform: translateY(-4px);
            border-color: transparent;
        }

        .contact-icon {
            width: 60px;
            height: 60px;
            border-radius: 14px;
            background: var(--primary-light);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            margin: 0 auto 1.25rem;
            transition: all 0.3s ease;
        }

        .contact-card:hover .contact-icon {
            background: var(--primary);
            color: var(--white);
        }

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
            box-shadow: 0 10px 40px rgba(0,0,0,0.06);
            border: 1px solid var(--gray-300);
        }

        .cta-section {
            background: var(--white);
            padding: 6rem 0;
            text-align: center;
        }

        .cta-card {
            background: var(--primary);
            border-radius: 24px;
            padding: 4rem 3rem;
            position: relative;
            overflow: hidden;
        }

        .cta-card::before {
            content: '';
            position: absolute;
            width: 400px;
            height: 400px;
            border-radius: 50%;
            background: rgba(255,255,255,0.08);
            top: -200px;
            right: -100px;
        }

        .cta-card::after {
            content: '';
            position: absolute;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            background: rgba(255,255,255,0.05);
            bottom: -150px;
            left: -50px;
        }

        .cta-title {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--white);
            margin-bottom: 1rem;
            position: relative;
            z-index: 1;
            letter-spacing: -0.02em;
        }

        .cta-subtitle {
            font-size: 1.1rem;
            color: rgba(255,255,255,0.8);
            margin-bottom: 2rem;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
            position: relative;
            z-index: 1;
            line-height: 1.7;
        }

        .cta-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }

        .btn-cta-white {
            background: var(--white);
            color: var(--primary);
            padding: 0.875rem 2.5rem;
            border-radius: 10px;
            font-weight: 700;
            font-size: 1rem;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            transition: all 0.3s ease;
            box-shadow: 0 4px 14px rgba(0,0,0,0.15);
        }

        .btn-cta-white:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.2);
            color: var(--primary);
        }

        .btn-cta-outline {
            background: transparent;
            border: 1.5px solid rgba(255,255,255,0.4);
            color: var(--white);
            padding: 0.875rem 2.5rem;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .btn-cta-outline:hover {
            background: rgba(255,255,255,0.15);
            border-color: rgba(255,255,255,0.7);
            color: var(--white);
        }

        footer {
            background: var(--gray-900);
            color: var(--gray-500);
            padding: 4rem 0 2rem;
        }

        .footer-brand {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--white);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .footer-brand .brand-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--primary);
            display: inline-block;
        }

        .footer-desc {
            color: var(--gray-500);
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
        }

        .footer-links {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .footer-links li { margin-bottom: 0.75rem; }

        .footer-links a {
            color: var(--gray-500);
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
            color: var(--gray-500);
            font-size: 0.9rem;
        }

        .footer-contact-item i {
            color: var(--primary);
            font-size: 1rem;
            margin-top: 0.15rem;
        }

        .footer-social {
            display: flex;
            gap: 0.75rem;
            margin-top: 1.5rem;
        }

        .footer-social a {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: rgba(255,255,255,0.06);
            color: var(--gray-500);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            text-decoration: none;
            transition: all 0.3s ease;
            border: 1px solid rgba(255,255,255,0.08);
        }

        .footer-social a:hover {
            background: var(--primary);
            color: var(--white);
            border-color: var(--primary);
        }

        .footer-bottom {
            border-top: 1px solid rgba(255,255,255,0.08);
            margin-top: 3rem;
            padding-top: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .footer-bottom-text {
            color: var(--gray-500);
            font-size: 0.85rem;
        }

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
            transform: translateY(20px);
            transition: all 0.6s ease;
        }

        .animate-on-scroll.visible {
            opacity: 1;
            transform: translateY(0);
        }

        @media (max-width: 991px) {
            .hero-title { font-size: 2.75rem; }
            .section-title { font-size: 2rem; }
            .cta-title { font-size: 2rem; }
            .stat-value { font-size: 2.5rem; }
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
            .hero-stats-grid { grid-template-columns: repeat(2, 1fr); }
            .package-speed-value { font-size: 2.5rem; }
            .footer-bottom { text-align: center; justify-content: center; }
            .section { padding: 4rem 0; }
            .cta-card { padding: 3rem 1.5rem; }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="/">
                <span class="brand-dot"></span>
                <?= htmlspecialchars($company['company_name'] ?? 'ISP Provider') ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item"><a class="nav-link" href="#home">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="#features">Features</a></li>
                    <li class="nav-item"><a class="nav-link" href="#packages">Packages</a></li>
                    <li class="nav-item"><a class="nav-link" href="#testimonials">Testimonials</a></li>
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
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="hero-content">
                        <div class="hero-tag">
                            <i class="bi bi-lightning-charge-fill"></i>
                            <?= htmlspecialchars($landingSettings['hero_badge'] ?? 'Ultra-Fast Fiber Internet') ?>
                        </div>
                        <h1 class="hero-title">
                            <?= htmlspecialchars($landingSettings['hero_title'] ?? 'Lightning Fast') ?>
                            <span class="highlight">Internet Speed</span>
                        </h1>
                        <p class="hero-description">
                            <?= htmlspecialchars($landingSettings['hero_subtitle'] ?? 'Experience blazing fast fiber internet for your home and business. Stream, game, work, and connect with the fastest speeds in your area.') ?>
                        </p>
                        <div class="hero-buttons">
                            <a href="#packages" class="btn-primary-gradient">
                                <i class="bi bi-arrow-right-circle"></i>View Packages
                            </a>
                            <a href="?page=order" class="btn-outline-soft">
                                <i class="bi bi-telephone"></i>Order Now
                            </a>
                            <button type="button" class="btn-outline-danger-soft" data-bs-toggle="modal" data-bs-target="#complaintModal">
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
                    <div class="hero-visual">
                        <div class="hero-stats-card">
                            <div class="text-center mb-4">
                                <div style="font-size: 3.5rem; font-weight: 800; color: var(--primary);">10 Gbps</div>
                                <div style="color: var(--gray-500); font-size: 0.9rem; text-transform: uppercase; letter-spacing: 2px;">Maximum Speed</div>
                            </div>
                            <div class="hero-stats-grid">
                                <div class="hero-stat-box">
                                    <div class="hero-stat-value">1ms</div>
                                    <div class="hero-stat-label">Latency</div>
                                </div>
                                <div class="hero-stat-box">
                                    <div class="hero-stat-value">99.9%</div>
                                    <div class="hero-stat-label">Uptime</div>
                                </div>
                                <div class="hero-stat-box">
                                    <div class="hero-stat-value">24/7</div>
                                    <div class="hero-stat-label">Support</div>
                                </div>
                                <div class="hero-stat-box">
                                    <div class="hero-stat-value">Free</div>
                                    <div class="hero-stat-label">Installation</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <svg class="hero-wave" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 120" preserveAspectRatio="none">
            <path fill="#FFFFFF" d="M0,64L48,58.7C96,53,192,43,288,48C384,53,480,75,576,80C672,85,768,75,864,64C960,53,1056,43,1152,42.7C1248,43,1344,53,1392,58.7L1440,64L1440,120L1392,120C1344,120,1248,120,1152,120C1056,120,960,120,864,120C768,120,672,120,576,120C480,120,384,120,288,120C192,120,96,120,48,120L0,120Z"></path>
        </svg>
    </section>

    <section id="features" class="section features-section">
        <div class="container">
            <div class="section-header animate-on-scroll">
                <div class="section-tag">Why Choose Us</div>
                <h2 class="section-title">Features You'll Love</h2>
                <p class="section-subtitle">We provide enterprise-grade internet solutions with the reliability and speed your home or business needs.</p>
            </div>
            <div class="row g-4">
                <div class="col-lg-3 col-md-6">
                    <div class="feature-card animate-on-scroll">
                        <div class="feature-icon"><i class="bi bi-lightning-charge"></i></div>
                        <h4 class="feature-title">Blazing Fast Speed</h4>
                        <p class="feature-desc">Fiber-optic speeds up to 10 Gbps with symmetrical upload and download for seamless performance.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="feature-card animate-on-scroll">
                        <div class="feature-icon"><i class="bi bi-shield-check"></i></div>
                        <h4 class="feature-title">99.9% Uptime</h4>
                        <p class="feature-desc">Redundant infrastructure and proactive monitoring ensure your connection stays rock-solid.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="feature-card animate-on-scroll">
                        <div class="feature-icon"><i class="bi bi-headset"></i></div>
                        <h4 class="feature-title">24/7 Support</h4>
                        <p class="feature-desc">Our expert support team is available around the clock via phone, email, or WhatsApp.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="feature-card animate-on-scroll">
                        <div class="feature-icon"><i class="bi bi-infinity"></i></div>
                        <h4 class="feature-title">Unlimited Data</h4>
                        <p class="feature-desc">No data caps, no throttling, no fair usage policies. Truly unlimited internet usage.</p>
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

    <section class="stats-section">
        <div class="container">
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

    <section id="testimonials" class="section testimonials-section">
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
        <div class="container">
            <div class="cta-card animate-on-scroll">
                <h2 class="cta-title">Ready to Get Connected?</h2>
                <p class="cta-subtitle">Join thousands of satisfied customers enjoying high-speed internet. Get started today!</p>
                <div class="cta-buttons">
                    <a href="?page=order" class="btn-cta-white">
                        <i class="bi bi-rocket-takeoff"></i>Get Started Today
                    </a>
                    <?php 
                    $whatsappNum = $contactSettings['whatsapp_number'] ?? $contactSettings['contact_phone'] ?? $company['company_phone'] ?? '';
                    $whatsappNum = preg_replace('/[^0-9+]/', '', $whatsappNum);
                    ?>
                    <?php if (!empty($whatsappNum)): ?>
                    <a href="https://wa.me/<?= htmlspecialchars($whatsappNum) ?>?text=Hi,%20I'm%20interested%20in%20your%20internet%20packages" target="_blank" class="btn-cta-outline">
                        <i class="bi bi-whatsapp"></i>Chat on WhatsApp
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <footer>
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4 col-md-6">
                    <div class="footer-brand">
                        <span class="brand-dot"></span>
                        <?= htmlspecialchars($company['company_name'] ?? 'ISP Provider') ?>
                    </div>
                    <p class="footer-desc">
                        <?= htmlspecialchars($landingSettings['about_text'] ?? 'Providing fast, reliable, and affordable internet connectivity for homes and businesses.') ?>
                    </p>
                    <div class="footer-social">
                        <?php if (!empty($contactSettings['social_facebook'])): ?>
                        <a href="<?= htmlspecialchars($contactSettings['social_facebook']) ?>" target="_blank"><i class="bi bi-facebook"></i></a>
                        <?php endif; ?>
                        <?php if (!empty($contactSettings['social_twitter'])): ?>
                        <a href="<?= htmlspecialchars($contactSettings['social_twitter']) ?>" target="_blank"><i class="bi bi-twitter-x"></i></a>
                        <?php endif; ?>
                        <?php if (!empty($contactSettings['social_instagram'])): ?>
                        <a href="<?= htmlspecialchars($contactSettings['social_instagram']) ?>" target="_blank"><i class="bi bi-instagram"></i></a>
                        <?php endif; ?>
                        <?php if (!empty($contactSettings['social_linkedin'])): ?>
                        <a href="<?= htmlspecialchars($contactSettings['social_linkedin']) ?>" target="_blank"><i class="bi bi-linkedin"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-lg-2 col-md-6">
                    <h5 class="footer-title">Quick Links</h5>
                    <ul class="footer-links">
                        <li><a href="#home">Home</a></li>
                        <li><a href="#features">Features</a></li>
                        <li><a href="#packages">Packages</a></li>
                        <li><a href="#contact">Contact</a></li>
                        <li><a href="?page=order">Order Now</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-6">
                    <h5 class="footer-title">Services</h5>
                    <ul class="footer-links">
                        <li><a href="#packages">Home Internet</a></li>
                        <li><a href="#packages">Business Internet</a></li>
                        <li><a href="#packages">Fiber Optic</a></li>
                        <li><a href="#packages">Dedicated Lines</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-6">
                    <h5 class="footer-title">Contact Info</h5>
                    <?php if (!empty($phone)): ?>
                    <div class="footer-contact-item">
                        <i class="bi bi-telephone"></i>
                        <span><?= htmlspecialchars($phone) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($email)): ?>
                    <div class="footer-contact-item">
                        <i class="bi bi-envelope"></i>
                        <span><?= htmlspecialchars($email) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($address)): ?>
                    <div class="footer-contact-item">
                        <i class="bi bi-geo-alt"></i>
                        <span><?= htmlspecialchars($address) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="footer-bottom">
                <div class="footer-bottom-text">
                    &copy; <?= date('Y') ?> <?= htmlspecialchars($company['company_name'] ?? 'ISP Provider') ?>. All rights reserved.
                </div>
            </div>
        </div>
    </footer>

    <?php if (!empty($whatsappNum)): ?>
    <a href="https://wa.me/<?= htmlspecialchars($whatsappNum) ?>" target="_blank" class="whatsapp-float">
        <i class="bi bi-whatsapp"></i>
    </a>
    <?php endif; ?>

    <div class="modal fade" id="complaintModal" tabindex="-1" aria-labelledby="complaintModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 16px; border: none;">
                <div class="modal-header" style="border-bottom: 1px solid var(--gray-300); padding: 1.25rem 1.5rem;">
                    <h5 class="modal-title" id="complaintModalLabel" style="font-weight: 700;">
                        <i class="bi bi-exclamation-triangle text-danger me-2"></i>Report an Issue
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="padding: 1.5rem;">
                    <form id="complaintForm">
                        <div class="mb-3">
                            <label class="form-label" style="font-weight: 600; color: var(--gray-700);">Your Name</label>
                            <input type="text" class="form-control" name="name" required style="border-radius: 10px; padding: 0.75rem 1rem; border-color: var(--gray-300);">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" style="font-weight: 600; color: var(--gray-700);">Phone / Account Number</label>
                            <input type="text" class="form-control" name="phone" required style="border-radius: 10px; padding: 0.75rem 1rem; border-color: var(--gray-300);">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" style="font-weight: 600; color: var(--gray-700);">Issue Description</label>
                            <textarea class="form-control" name="description" rows="4" required style="border-radius: 10px; padding: 0.75rem 1rem; border-color: var(--gray-300);"></textarea>
                        </div>
                        <button type="submit" class="btn w-100" style="background: var(--primary); color: white; border-radius: 10px; padding: 0.75rem; font-weight: 600;">
                            <i class="bi bi-send me-2"></i>Submit Report
                        </button>
                    </form>
                    <div id="complaintResult" class="mt-3" style="display:none;"></div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });

        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry, index) => {
                if (entry.isIntersecting) {
                    setTimeout(() => {
                        entry.target.classList.add('visible');
                    }, index * 100);
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1 });

        document.querySelectorAll('.animate-on-scroll').forEach(el => observer.observe(el));

        document.querySelectorAll('.stat-value[data-count]').forEach(el => {
            const target = parseInt(el.getAttribute('data-count'));
            const observer = new IntersectionObserver((entries) => {
                if (entries[0].isIntersecting) {
                    let current = 0;
                    const increment = Math.ceil(target / 60);
                    const timer = setInterval(() => {
                        current += increment;
                        if (current >= target) {
                            el.textContent = target.toLocaleString() + '+';
                            clearInterval(timer);
                        } else {
                            el.textContent = current.toLocaleString();
                        }
                    }, 30);
                    observer.unobserve(el);
                }
            }, { threshold: 0.5 });
            observer.observe(el);
        });

        document.getElementById('complaintForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const resultDiv = document.getElementById('complaintResult');
            
            fetch('?page=landing&action=complaint', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                resultDiv.style.display = 'block';
                if (data.success) {
                    resultDiv.innerHTML = '<div class="alert alert-success" style="border-radius: 10px;"><i class="bi bi-check-circle me-2"></i>' + data.message + '</div>';
                    this.reset();
                } else {
                    resultDiv.innerHTML = '<div class="alert alert-danger" style="border-radius: 10px;"><i class="bi bi-x-circle me-2"></i>' + (data.message || 'Failed to submit. Please try again.') + '</div>';
                }
            })
            .catch(() => {
                resultDiv.style.display = 'block';
                resultDiv.innerHTML = '<div class="alert alert-danger" style="border-radius: 10px;"><i class="bi bi-x-circle me-2"></i>Network error. Please try again.</div>';
            });
        });

        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    e.preventDefault();
                    const navbarHeight = document.querySelector('.navbar').offsetHeight;
                    window.scrollTo({
                        top: target.offsetTop - navbarHeight,
                        behavior: 'smooth'
                    });
                    const navCollapse = document.querySelector('.navbar-collapse');
                    if (navCollapse?.classList.contains('show')) {
                        new bootstrap.Collapse(navCollapse).hide();
                    }
                }
            });
        });
    </script>
</body>
</html>