<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($company['company_name'] ?? 'ISP Provider') ?> - High-Speed Internet Services</title>
    <meta name="description" content="<?= htmlspecialchars($landingSettings['hero_subtitle'] ?? 'Fast, reliable internet for your home and business') ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: <?= htmlspecialchars($landingSettings['primary_color'] ?? '#7C3AED') ?>;
            --primary-rgb: 124, 58, 237;
            --grad-start: #7C3AED;
            --grad-mid: #2563EB;
            --grad-end: #06B6D4;
            --dark: #0B0F1A;
            --dark-card: #111827;
            --glass-bg: rgba(255, 255, 255, 0.06);
            --glass-border: rgba(255, 255, 255, 0.12);
            --white: #FFFFFF;
            --gray-100: #F3F4F6;
            --gray-200: #E5E7EB;
            --gray-300: #D1D5DB;
            --gray-400: #9CA3AF;
            --gray-500: #6B7280;
            --gray-600: #4B5563;
            --gray-700: #374151;
            --gray-800: #1F2937;
            --gray-900: #111827;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            color: var(--white);
            overflow-x: hidden;
            background: var(--dark);
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
            background: rgba(11, 15, 26, 0.92);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            box-shadow: 0 1px 0 rgba(255, 255, 255, 0.06);
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
        }

        .navbar-brand .brand-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--grad-start), var(--grad-end));
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
            color: rgba(255, 255, 255, 0.7) !important;
            padding: 0.5rem 1rem !important;
            transition: all 0.3s ease;
        }

        .nav-link:hover { color: var(--white) !important; }

        .btn-nav-login {
            border: 1.5px solid rgba(255, 255, 255, 0.25);
            color: var(--white) !important;
            padding: 0.5rem 1.25rem !important;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            background: transparent;
        }

        .btn-nav-login:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.5);
        }

        .btn-nav-cta {
            background: linear-gradient(135deg, var(--grad-start), var(--grad-mid));
            color: var(--white) !important;
            padding: 0.5rem 1.5rem !important;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            border: none;
            transition: all 0.3s ease;
            box-shadow: 0 2px 12px rgba(var(--primary-rgb), 0.4);
        }

        .btn-nav-cta:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 24px rgba(var(--primary-rgb), 0.6);
        }

        .navbar-toggler {
            border-color: rgba(255, 255, 255, 0.2);
            padding: 0.4rem 0.6rem;
        }

        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28255, 255, 255, 0.85%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        }

        .hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
            background: var(--dark);
        }

        .hero-mesh {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background:
                radial-gradient(ellipse 80% 50% at 50% -20%, rgba(124, 58, 237, 0.3), transparent),
                radial-gradient(ellipse 60% 40% at 80% 50%, rgba(37, 99, 235, 0.2), transparent),
                radial-gradient(ellipse 50% 60% at 20% 80%, rgba(6, 182, 212, 0.15), transparent);
            z-index: 1;
        }

        .hero-grid-pattern {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image:
                linear-gradient(rgba(255, 255, 255, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
            background-size: 60px 60px;
            z-index: 1;
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
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            color: var(--grad-end);
            padding: 0.5rem 1.25rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            backdrop-filter: blur(10px);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .hero-badge i { color: var(--grad-end); }

        .hero-title {
            font-size: 4.5rem;
            font-weight: 800;
            color: var(--white);
            line-height: 1.05;
            margin-bottom: 1.5rem;
            letter-spacing: -0.03em;
        }

        .hero-title .text-gradient {
            background: linear-gradient(135deg, var(--grad-start) 0%, var(--grad-mid) 50%, var(--grad-end) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-description {
            font-size: 1.15rem;
            color: rgba(255, 255, 255, 0.6);
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
            background: linear-gradient(135deg, var(--grad-start), var(--grad-mid));
            color: var(--white);
            padding: 0.9rem 2.25rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            transition: all 0.3s ease;
            box-shadow: 0 4px 24px rgba(var(--primary-rgb), 0.5);
        }

        .btn-hero-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 40px rgba(var(--primary-rgb), 0.7);
            color: var(--white);
        }

        .btn-hero-secondary {
            background: var(--glass-bg);
            border: 1.5px solid var(--glass-border);
            color: var(--white);
            padding: 0.9rem 2.25rem;
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
            background: rgba(255, 255, 255, 0.12);
            border-color: rgba(255, 255, 255, 0.3);
            color: var(--white);
            transform: translateY(-2px);
        }

        .btn-hero-outline {
            background: transparent;
            border: 1.5px solid rgba(239, 68, 68, 0.4);
            color: #FCA5A5;
            padding: 0.9rem 2.25rem;
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
            border-color: rgba(239, 68, 68, 0.6);
            color: #FCA5A5;
        }

        .hero-visual {
            position: relative;
            z-index: 2;
        }

        .hero-glass-card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            padding: 2.5rem;
            backdrop-filter: blur(20px);
            text-align: center;
        }

        .speed-display {
            margin-bottom: 2rem;
        }

        .speed-display .speed-number {
            font-size: 5rem;
            font-weight: 800;
            font-family: 'Space Grotesk', sans-serif;
            background: linear-gradient(135deg, var(--grad-start), var(--grad-mid), var(--grad-end));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1;
        }

        .speed-display .speed-unit {
            font-size: 1.5rem;
            color: rgba(255, 255, 255, 0.5);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 4px;
        }

        .hero-metrics {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
        }

        .hero-metric {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 12px;
            padding: 1rem;
            text-align: center;
        }

        .hero-metric-value {
            font-size: 1.4rem;
            font-weight: 700;
            font-family: 'Space Grotesk', sans-serif;
            background: linear-gradient(135deg, var(--grad-start), var(--grad-end));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-metric-label {
            font-size: 0.7rem;
            color: rgba(255, 255, 255, 0.4);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 0.25rem;
        }

        .hero-float-badge {
            position: absolute;
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 14px;
            padding: 0.875rem 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            backdrop-filter: blur(20px);
            animation: badgeFloat 4s ease-in-out infinite;
            z-index: 3;
        }

        .hero-float-badge i { font-size: 1.5rem; }
        .hero-float-badge strong { display: block; font-size: 1rem; font-weight: 700; }
        .hero-float-badge span { font-size: 0.75rem; color: rgba(255, 255, 255, 0.5); }

        .float-badge-1 { top: 30px; right: -20px; }
        .float-badge-2 { bottom: 80px; left: -30px; animation-delay: 1.5s; }

        @keyframes badgeFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-12px); }
        }

        .section { padding: 6rem 0; position: relative; }

        .section-dark { background: var(--dark); }

        .section-gradient {
            background: linear-gradient(180deg, rgba(124, 58, 237, 0.05) 0%, var(--dark) 100%);
        }

        .section-header {
            text-align: center;
            margin-bottom: 3.5rem;
        }

        .section-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: linear-gradient(135deg, rgba(124, 58, 237, 0.15), rgba(6, 182, 212, 0.15));
            border: 1px solid rgba(124, 58, 237, 0.2);
            color: var(--grad-end);
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
            color: var(--white);
            margin-bottom: 1rem;
            letter-spacing: -0.02em;
        }

        .section-subtitle {
            font-size: 1.05rem;
            color: rgba(255, 255, 255, 0.5);
            max-width: 600px;
            margin: 0 auto;
            line-height: 1.75;
        }

        .feature-card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 2rem;
            height: 100%;
            backdrop-filter: blur(10px);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--grad-start), var(--grad-mid), var(--grad-end));
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-8px);
            border-color: rgba(124, 58, 237, 0.3);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
        }

        .feature-card:hover::before { opacity: 1; }

        .feature-icon {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--grad-start), var(--grad-mid));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            color: var(--white);
            margin-bottom: 1.25rem;
        }

        .feature-card h4 {
            font-size: 1.15rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
        }

        .feature-card p {
            color: rgba(255, 255, 255, 0.5);
            line-height: 1.7;
            font-size: 0.9rem;
            margin: 0;
        }

        .packages-section {
            background: linear-gradient(180deg, var(--dark) 0%, rgba(124, 58, 237, 0.03) 50%, var(--dark) 100%);
        }

        .package-card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            padding: 2.5rem 2rem;
            text-align: center;
            height: 100%;
            backdrop-filter: blur(10px);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .package-card::before {
            content: '';
            position: absolute;
            top: -1px;
            left: -1px;
            right: -1px;
            bottom: -1px;
            border-radius: 24px;
            padding: 2px;
            background: linear-gradient(135deg, transparent 40%, rgba(124, 58, 237, 0.3), rgba(6, 182, 212, 0.3));
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            opacity: 0;
            transition: opacity 0.4s ease;
        }

        .package-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .package-card:hover::before { opacity: 1; }

        .package-card.popular {
            background: linear-gradient(135deg, rgba(124, 58, 237, 0.12), rgba(37, 99, 235, 0.08));
            border-color: rgba(124, 58, 237, 0.3);
        }

        .package-card.popular::before { opacity: 1; }

        .package-badge {
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(135deg, var(--grad-start), var(--grad-mid));
            color: var(--white);
            padding: 0.4rem 1.5rem;
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
            background: linear-gradient(135deg, rgba(124, 58, 237, 0.15), rgba(6, 182, 212, 0.15));
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 1.5rem;
            color: var(--grad-end);
        }

        .package-name {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .package-speed {
            margin-bottom: 0.75rem;
        }

        .package-speed-value {
            font-size: 3.5rem;
            font-weight: 800;
            font-family: 'Space Grotesk', sans-serif;
            background: linear-gradient(135deg, var(--grad-start), var(--grad-end));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1;
        }

        .package-speed-unit {
            font-size: 1.1rem;
            color: rgba(255, 255, 255, 0.5);
            font-weight: 600;
        }

        .package-price {
            margin-bottom: 1.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .package-currency {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.5);
            font-weight: 600;
            vertical-align: super;
        }

        .package-amount {
            font-size: 2.25rem;
            font-weight: 800;
            font-family: 'Space Grotesk', sans-serif;
        }

        .package-period {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.4);
        }

        .package-features {
            list-style: none;
            padding: 0;
            margin: 0 0 2rem;
            text-align: left;
        }

        .package-features li {
            padding: 0.5rem 0;
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .package-features li i {
            color: var(--grad-end);
            font-size: 0.85rem;
        }

        .package-btn {
            display: block;
            width: 100%;
            padding: 0.8rem 1.5rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.95rem;
            text-decoration: none;
            transition: all 0.3s ease;
            text-align: center;
        }

        .package-btn.primary {
            background: linear-gradient(135deg, var(--grad-start), var(--grad-mid));
            color: var(--white);
            border: none;
            box-shadow: 0 4px 20px rgba(var(--primary-rgb), 0.4);
        }

        .package-btn.primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(var(--primary-rgb), 0.6);
            color: var(--white);
        }

        .package-btn.outline {
            background: transparent;
            border: 1.5px solid rgba(255, 255, 255, 0.2);
            color: var(--white);
        }

        .package-btn.outline:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(124, 58, 237, 0.5);
            color: var(--white);
        }

        .testimonials-section {
            background: var(--dark);
        }

        .testimonial-card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 2rem;
            height: 100%;
            backdrop-filter: blur(10px);
            transition: all 0.4s ease;
            position: relative;
        }

        .testimonial-card:hover {
            transform: translateY(-4px);
            border-color: rgba(124, 58, 237, 0.3);
        }

        .testimonial-quote {
            font-size: 2.5rem;
            background: linear-gradient(135deg, var(--grad-start), var(--grad-end));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            opacity: 0.3;
            position: absolute;
            top: 16px;
            right: 24px;
        }

        .testimonial-text {
            color: rgba(255, 255, 255, 0.7);
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
            border: 2px solid rgba(124, 58, 237, 0.3);
        }

        .testimonial-info h5 {
            font-weight: 700;
            color: var(--white);
            margin-bottom: 0;
            font-size: 0.95rem;
        }

        .testimonial-info span {
            color: rgba(255, 255, 255, 0.4);
            font-size: 0.8rem;
        }

        .testimonial-rating {
            color: #F59E0B;
            font-size: 0.8rem;
        }

        .stats-section {
            background: linear-gradient(135deg, var(--grad-start), var(--grad-mid), var(--grad-end));
            padding: 5rem 0;
            position: relative;
            overflow: hidden;
        }

        .stats-section::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.08'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
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
            color: rgba(255, 255, 255, 0.8);
            text-transform: uppercase;
            letter-spacing: 1.5px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .cta-section {
            background: var(--dark);
            padding: 6rem 0;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .cta-section::before {
            content: '';
            position: absolute;
            width: 600px;
            height: 600px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(124, 58, 237, 0.15), transparent 70%);
            top: -300px;
            right: -200px;
        }

        .cta-section::after {
            content: '';
            position: absolute;
            width: 500px;
            height: 500px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(6, 182, 212, 0.1), transparent 70%);
            bottom: -250px;
            left: -150px;
        }

        .cta-glass {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            padding: 4rem 3rem;
            backdrop-filter: blur(10px);
            position: relative;
        }

        .cta-title {
            font-size: 2.75rem;
            font-weight: 800;
            color: var(--white);
            margin-bottom: 1rem;
            letter-spacing: -0.02em;
        }

        .cta-subtitle {
            font-size: 1.1rem;
            color: rgba(255, 255, 255, 0.5);
            margin-bottom: 2.5rem;
            max-width: 550px;
            margin-left: auto;
            margin-right: auto;
            line-height: 1.7;
        }

        .cta-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-cta-primary {
            background: linear-gradient(135deg, var(--grad-start), var(--grad-mid));
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
            background: var(--glass-bg);
            border: 1.5px solid var(--glass-border);
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
            background: rgba(255, 255, 255, 0.12);
            border-color: rgba(255, 255, 255, 0.3);
            color: var(--white);
        }

        .contact-section { background: var(--dark); }

        .contact-card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            padding: 2rem;
            text-align: center;
            height: 100%;
            backdrop-filter: blur(10px);
            transition: all 0.4s ease;
        }

        .contact-card:hover {
            border-color: rgba(124, 58, 237, 0.3);
            transform: translateY(-4px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .contact-icon {
            width: 64px;
            height: 64px;
            border-radius: 16px;
            background: linear-gradient(135deg, var(--grad-start), var(--grad-mid));
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
            color: rgba(255, 255, 255, 0.4);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .contact-value {
            font-size: 1rem;
            font-weight: 600;
            color: var(--white);
            line-height: 1.6;
        }

        .contact-value a {
            color: var(--white);
            text-decoration: none;
            transition: color 0.3s;
        }

        .contact-value a:hover { color: var(--grad-end); }

        .map-container {
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid var(--glass-border);
        }

        footer {
            background: var(--dark);
            color: rgba(255, 255, 255, 0.5);
            padding: 4rem 0 2rem;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
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
            background: linear-gradient(135deg, var(--grad-start), var(--grad-end));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1rem;
        }

        .footer-desc {
            color: rgba(255, 255, 255, 0.4);
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

        .footer-links { list-style: none; padding: 0; margin: 0; }
        .footer-links li { margin-bottom: 0.75rem; }

        .footer-links a {
            color: rgba(255, 255, 255, 0.4);
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
            color: rgba(255, 255, 255, 0.4);
            font-size: 0.9rem;
        }

        .footer-contact-item i {
            color: var(--grad-end);
            font-size: 1rem;
            margin-top: 0.15rem;
        }

        .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.06);
            margin-top: 3rem;
            padding-top: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .footer-bottom-text { color: rgba(255, 255, 255, 0.3); font-size: 0.85rem; }

        .footer-bottom-links { display: flex; gap: 1.5rem; }

        .footer-bottom-links a {
            color: rgba(255, 255, 255, 0.3);
            text-decoration: none;
            font-size: 0.85rem;
            transition: color 0.3s;
        }

        .footer-bottom-links a:hover { color: var(--white); }

        .social-links {
            display: flex;
            gap: 0.75rem;
        }

        .social-link {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            color: rgba(255, 255, 255, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .social-link:hover {
            background: linear-gradient(135deg, var(--grad-start), var(--grad-mid));
            border-color: transparent;
            color: var(--white);
            transform: translateY(-3px);
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
            .hero-visual { margin-top: 3rem; }
            .cta-glass { padding: 3rem 2rem; }
        }

        @media (max-width: 767px) {
            .hero {
                text-align: center;
                padding: 120px 0 60px;
            }
            .hero-title { font-size: 2.25rem; }
            .hero-description { margin: 0 auto 2rem; }
            .hero-buttons { justify-content: center; }
            .hero-float-badge { display: none; }
            .package-speed-value { font-size: 2.5rem; }
            .footer-bottom { text-align: center; justify-content: center; }
            .footer-bottom-links { justify-content: center; }
            .section { padding: 4rem 0; }
            .cta-glass { padding: 2.5rem 1.5rem; }
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
                    <li class="nav-item"><a class="nav-link" href="#features">Features</a></li>
                    <li class="nav-item"><a class="nav-link" href="#packages">Packages</a></li>
                    <li class="nav-item"><a class="nav-link" href="#testimonials">Reviews</a></li>
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
        <div class="hero-mesh"></div>
        <div class="hero-grid-pattern"></div>
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="hero-content">
                        <div class="hero-badge">
                            <i class="bi bi-lightning-charge-fill"></i>
                            <?= htmlspecialchars($landingSettings['hero_badge'] ?? 'Ultra-Fast Fiber Internet') ?>
                        </div>
                        <h1 class="hero-title">
                            <?= htmlspecialchars($landingSettings['hero_title'] ?? 'Blazing Fast') ?>
                            <br><span class="text-gradient">Internet Speed</span>
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
                    </div>
                </div>
                <div class="col-lg-6 d-none d-lg-block">
                    <div class="hero-visual">
                        <div class="hero-glass-card">
                            <div class="speed-display">
                                <div class="speed-number">1 Gbps</div>
                                <div class="speed-unit">Download Speed</div>
                            </div>
                            <div class="hero-metrics">
                                <div class="hero-metric">
                                    <div class="hero-metric-value">1ms</div>
                                    <div class="hero-metric-label">Latency</div>
                                </div>
                                <div class="hero-metric">
                                    <div class="hero-metric-value">99.9%</div>
                                    <div class="hero-metric-label">Uptime</div>
                                </div>
                                <div class="hero-metric">
                                    <div class="hero-metric-value">24/7</div>
                                    <div class="hero-metric-label">Support</div>
                                </div>
                            </div>
                        </div>
                        <div class="hero-float-badge float-badge-1">
                            <i class="bi bi-lightning-charge-fill" style="color: var(--grad-end);"></i>
                            <div>
                                <strong>Fiber Optic</strong>
                                <span>FTTH Connection</span>
                            </div>
                        </div>
                        <div class="hero-float-badge float-badge-2">
                            <i class="bi bi-shield-check" style="color: #34D399;"></i>
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

    <section id="features" class="section section-gradient">
        <div class="container">
            <div class="section-header animate-on-scroll">
                <div class="section-tag">Why Choose Us</div>
                <h2 class="section-title">Service Features</h2>
                <p class="section-subtitle">We deliver more than just internet. Discover what makes us the preferred ISP in the region.</p>
            </div>
            <div class="row g-4">
                <div class="col-lg-3 col-md-6">
                    <div class="feature-card animate-on-scroll">
                        <div class="feature-icon">
                            <i class="bi bi-lightning-charge-fill"></i>
                        </div>
                        <h4>Ultra-Fast Speeds</h4>
                        <p>Blazing fast fiber connections up to 1 Gbps. Perfect for streaming, gaming, and working from home.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="feature-card animate-on-scroll">
                        <div class="feature-icon">
                            <i class="bi bi-shield-check"></i>
                        </div>
                        <h4>Secure Network</h4>
                        <p>Enterprise-grade security with DDoS protection, keeping your data and devices safe at all times.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="feature-card animate-on-scroll">
                        <div class="feature-icon">
                            <i class="bi bi-headset"></i>
                        </div>
                        <h4>24/7 Support</h4>
                        <p>Round-the-clock customer support via phone, email, and WhatsApp. We're always here for you.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="feature-card animate-on-scroll">
                        <div class="feature-icon">
                            <i class="bi bi-infinity"></i>
                        </div>
                        <h4>Unlimited Data</h4>
                        <p>No data caps, no throttling. Enjoy truly unlimited internet usage without any restrictions.</p>
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
                            <br><small style="color: rgba(255,255,255,0.4);">Support: <?= htmlspecialchars($contactSettings['support_hours'] ?? '24/7') ?></small>
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
            <div class="cta-glass">
                <h2 class="cta-title animate-on-scroll">Ready to Get Connected?</h2>
                <p class="cta-subtitle animate-on-scroll">Join thousands of satisfied customers enjoying high-speed internet. Get started today!</p>
                <div class="cta-buttons animate-on-scroll">
                    <a href="?page=order" class="btn-cta-primary">
                        <i class="bi bi-rocket-takeoff"></i>Get Started Today
                    </a>
                    <?php
                    $whatsappNum = $contactSettings['whatsapp_number'] ?? $contactSettings['contact_phone'] ?? $company['company_phone'] ?? '';
                    $whatsappNum = preg_replace('/[^0-9]/', '', $whatsappNum);
                    ?>
                    <?php if (!empty($whatsappNum)): ?>
                    <a href="https://wa.me/<?= htmlspecialchars($whatsappNum) ?>?text=<?= urlencode('Hi, I\'m interested in your internet packages!') ?>" class="btn-cta-secondary" target="_blank">
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
                <div class="col-lg-4">
                    <div class="footer-brand">
                        <span class="brand-icon"><i class="bi bi-broadcast-pin"></i></span>
                        <?= htmlspecialchars($company['company_name'] ?? 'ISP Provider') ?>
                    </div>
                    <p class="footer-desc">Providing fast, reliable internet services for homes and businesses. Your connectivity partner for the digital age.</p>
                    <div class="social-links">
                        <?php if (!empty($contactSettings['social_facebook'])): ?>
                        <a href="<?= htmlspecialchars($contactSettings['social_facebook']) ?>" class="social-link" target="_blank"><i class="bi bi-facebook"></i></a>
                        <?php endif; ?>
                        <?php if (!empty($contactSettings['social_twitter'])): ?>
                        <a href="<?= htmlspecialchars($contactSettings['social_twitter']) ?>" class="social-link" target="_blank"><i class="bi bi-twitter-x"></i></a>
                        <?php endif; ?>
                        <?php if (!empty($contactSettings['social_instagram'])): ?>
                        <a href="<?= htmlspecialchars($contactSettings['social_instagram']) ?>" class="social-link" target="_blank"><i class="bi bi-instagram"></i></a>
                        <?php endif; ?>
                        <?php if (!empty($contactSettings['social_linkedin'])): ?>
                        <a href="<?= htmlspecialchars($contactSettings['social_linkedin']) ?>" class="social-link" target="_blank"><i class="bi bi-linkedin"></i></a>
                        <?php endif; ?>
                        <?php if (!empty($contactSettings['social_tiktok'])): ?>
                        <a href="<?= htmlspecialchars($contactSettings['social_tiktok']) ?>" class="social-link" target="_blank"><i class="bi bi-tiktok"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-6">
                    <h6 class="footer-title">Quick Links</h6>
                    <ul class="footer-links">
                        <li><a href="#home">Home</a></li>
                        <li><a href="#features">Features</a></li>
                        <li><a href="#packages">Packages</a></li>
                        <li><a href="#contact">Contact</a></li>
                        <li><a href="?page=order">Order Now</a></li>
                    </ul>
                </div>
                <div class="col-lg-2 col-md-4 col-6">
                    <h6 class="footer-title">Services</h6>
                    <ul class="footer-links">
                        <li><a href="#packages">Home Internet</a></li>
                        <li><a href="#packages">Business Plans</a></li>
                        <li><a href="#packages">Fiber Optic</a></li>
                        <li><a href="?page=login">My Account</a></li>
                    </ul>
                </div>
                <div class="col-lg-4 col-md-4">
                    <h6 class="footer-title">Contact Info</h6>
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
                <div class="footer-bottom-links">
                    <a href="#">Privacy Policy</a>
                    <a href="#">Terms of Service</a>
                </div>
            </div>
        </div>
    </footer>

    <?php if (!empty($whatsappNum)): ?>
    <a href="https://wa.me/<?= htmlspecialchars($whatsappNum) ?>" class="whatsapp-float" target="_blank" title="Chat on WhatsApp">
        <i class="bi bi-whatsapp"></i>
    </a>
    <?php endif; ?>

    <div class="modal fade" id="complaintModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="background: var(--dark-card); border: 1px solid var(--glass-border); border-radius: 20px;">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title" style="font-family: 'Space Grotesk', sans-serif; font-weight: 700;">Report an Issue</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="complaintForm">
                        <div class="mb-3">
                            <label class="form-label" style="color: rgba(255,255,255,0.7); font-size: 0.9rem;">Your Name</label>
                            <input type="text" class="form-control" name="name" required style="background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.12); color: white; border-radius: 10px; padding: 0.7rem 1rem;">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" style="color: rgba(255,255,255,0.7); font-size: 0.9rem;">Phone / Account Number</label>
                            <input type="text" class="form-control" name="phone" required style="background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.12); color: white; border-radius: 10px; padding: 0.7rem 1rem;">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" style="color: rgba(255,255,255,0.7); font-size: 0.9rem;">Issue Description</label>
                            <textarea class="form-control" name="description" rows="4" required style="background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.12); color: white; border-radius: 10px; padding: 0.7rem 1rem;"></textarea>
                        </div>
                        <button type="submit" class="btn w-100" style="background: linear-gradient(135deg, var(--grad-start), var(--grad-mid)); color: white; border: none; padding: 0.75rem; border-radius: 10px; font-weight: 600;">
                            <i class="bi bi-send me-2"></i>Submit Report
                        </button>
                    </form>
                    <div id="complaintResult" class="mt-3" style="display: none;"></div>
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

        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                }
            });
        }, { threshold: 0.1 });

        document.querySelectorAll('.animate-on-scroll').forEach(function(el) {
            observer.observe(el);
        });

        document.querySelectorAll('.stat-value[data-count]').forEach(function(el) {
            const target = parseInt(el.getAttribute('data-count'));
            const observer2 = new IntersectionObserver(function(entries) {
                if (entries[0].isIntersecting) {
                    let current = 0;
                    const increment = target / 60;
                    const timer = setInterval(function() {
                        current += increment;
                        if (current >= target) {
                            el.textContent = target.toLocaleString() + '+';
                            clearInterval(timer);
                        } else {
                            el.textContent = Math.floor(current).toLocaleString();
                        }
                    }, 30);
                    observer2.unobserve(el);
                }
            }, { threshold: 0.5 });
            observer2.observe(el);
        });

        document.getElementById('complaintForm').addEventListener('submit', function(e) {
            e.preventDefault();
            var formData = new FormData(this);
            var resultDiv = document.getElementById('complaintResult');

            fetch('public/api/oms-notify.php', {
                method: 'POST',
                body: JSON.stringify({
                    action: 'submit_complaint',
                    name: formData.get('name'),
                    phone: formData.get('phone'),
                    description: formData.get('description')
                }),
                headers: { 'Content-Type': 'application/json' }
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                resultDiv.style.display = 'block';
                if (data.success) {
                    resultDiv.innerHTML = '<div class="alert" style="background: rgba(16,185,129,0.15); border: 1px solid rgba(16,185,129,0.3); color: #34D399; border-radius: 10px;"><i class="bi bi-check-circle me-2"></i>' + (data.message || 'Your complaint has been submitted successfully!') + '</div>';
                    document.getElementById('complaintForm').reset();
                } else {
                    resultDiv.innerHTML = '<div class="alert" style="background: rgba(239,68,68,0.15); border: 1px solid rgba(239,68,68,0.3); color: #FCA5A5; border-radius: 10px;"><i class="bi bi-exclamation-circle me-2"></i>' + (data.message || 'Failed to submit. Please try again.') + '</div>';
                }
            })
            .catch(function() {
                resultDiv.style.display = 'block';
                resultDiv.innerHTML = '<div class="alert" style="background: rgba(239,68,68,0.15); border: 1px solid rgba(239,68,68,0.3); color: #FCA5A5; border-radius: 10px;"><i class="bi bi-exclamation-circle me-2"></i>Network error. Please try again.</div>';
            });
        });
    </script>
</body>
</html>