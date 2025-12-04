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
            --primary: <?= htmlspecialchars($landingSettings['primary_color'] ?? '#0066FF') ?>;
            --primary-dark: #0052CC;
            --primary-light: #E6F0FF;
            --secondary: #6C5CE7;
            --accent: #00D4AA;
            --dark: #0A1628;
            --gray-900: #1A1F36;
            --gray-800: #2D3748;
            --gray-600: #718096;
            --gray-400: #A0AEC0;
            --gray-200: #E2E8F0;
            --gray-100: #F7FAFC;
        }
        
        * {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: var(--gray-100);
            color: var(--gray-900);
            overflow-x: hidden;
        }
        
        .navbar {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            box-shadow: 0 1px 0 rgba(0,0,0,0.05);
            padding: 0.75rem 0;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            transition: all 0.3s ease;
        }
        
        .navbar.scrolled {
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        
        .navbar-brand {
            font-weight: 800;
            font-size: 1.5rem;
            color: var(--primary) !important;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .navbar-brand i {
            font-size: 1.75rem;
        }
        
        .nav-link {
            font-weight: 500;
            color: var(--gray-800) !important;
            padding: 0.5rem 1rem !important;
            transition: color 0.2s;
        }
        
        .nav-link:hover {
            color: var(--primary) !important;
        }
        
        .btn-primary {
            background: var(--primary);
            border: none;
            padding: 0.75rem 1.75rem;
            font-weight: 600;
            border-radius: 50px;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(0, 102, 255, 0.3);
        }
        
        .btn-outline-light {
            border: 2px solid rgba(255,255,255,0.8);
            padding: 0.75rem 1.75rem;
            font-weight: 600;
            border-radius: 50px;
        }
        
        .btn-outline-light:hover {
            background: white;
            color: var(--primary);
        }
        
        .hero {
            background: linear-gradient(135deg, var(--dark) 0%, #1a2942 50%, #243b55 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
            padding-top: 80px;
        }
        
        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 80%, rgba(0, 102, 255, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(108, 92, 231, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(0, 212, 170, 0.1) 0%, transparent 40%);
        }
        
        .hero-grid {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
            background-size: 50px 50px;
        }
        
        .hero-content {
            position: relative;
            z-index: 2;
        }
        
        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(0, 212, 170, 0.2);
            color: var(--accent);
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(0, 212, 170, 0.3);
        }
        
        .hero-title {
            font-size: 4rem;
            font-weight: 900;
            color: white;
            line-height: 1.1;
            margin-bottom: 1.5rem;
        }
        
        .hero-title .highlight {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .hero-subtitle {
            font-size: 1.25rem;
            color: var(--gray-400);
            line-height: 1.7;
            margin-bottom: 2rem;
            max-width: 500px;
        }
        
        .hero-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .hero-stats {
            display: flex;
            gap: 3rem;
            margin-top: 4rem;
        }
        
        .hero-stat {
            text-align: center;
        }
        
        .hero-stat-value {
            font-size: 2.5rem;
            font-weight: 800;
            color: white;
        }
        
        .hero-stat-label {
            color: var(--gray-400);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .hero-image {
            position: relative;
            z-index: 1;
        }
        
        .speed-card {
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 2rem;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .speed-indicator {
            width: 200px;
            height: 200px;
            border-radius: 50%;
            background: conic-gradient(var(--accent) 0deg, var(--primary) 120deg, var(--secondary) 240deg, var(--accent) 360deg);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            position: relative;
        }
        
        .speed-indicator::before {
            content: '';
            position: absolute;
            width: 160px;
            height: 160px;
            background: var(--dark);
            border-radius: 50%;
        }
        
        .speed-value {
            position: relative;
            z-index: 1;
            text-align: center;
            color: white;
        }
        
        .speed-value strong {
            display: block;
            font-size: 3rem;
            font-weight: 800;
        }
        
        .speed-value span {
            font-size: 0.875rem;
            color: var(--gray-400);
        }
        
        .section {
            padding: 6rem 0;
        }
        
        .section-header {
            text-align: center;
            margin-bottom: 4rem;
        }
        
        .section-badge {
            display: inline-block;
            background: var(--primary-light);
            color: var(--primary);
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        .section-title {
            font-size: 2.75rem;
            font-weight: 800;
            color: var(--gray-900);
            margin-bottom: 1rem;
        }
        
        .section-subtitle {
            font-size: 1.125rem;
            color: var(--gray-600);
            max-width: 600px;
            margin: 0 auto;
        }
        
        .feature-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            height: 100%;
            border: 1px solid var(--gray-200);
            transition: all 0.3s ease;
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            border-color: var(--primary);
        }
        
        .feature-icon {
            width: 64px;
            height: 64px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            margin-bottom: 1.5rem;
        }
        
        .feature-icon.blue { background: var(--primary-light); color: var(--primary); }
        .feature-icon.purple { background: #F3E8FF; color: var(--secondary); }
        .feature-icon.green { background: #D1FAE5; color: var(--accent); }
        .feature-icon.orange { background: #FEF3C7; color: #F59E0B; }
        
        .feature-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 0.75rem;
        }
        
        .feature-desc {
            color: var(--gray-600);
            line-height: 1.7;
        }
        
        .packages-section {
            background: linear-gradient(180deg, white 0%, var(--gray-100) 100%);
        }
        
        .package-card {
            background: white;
            border-radius: 24px;
            padding: 2.5rem;
            border: 2px solid var(--gray-200);
            height: 100%;
            position: relative;
            transition: all 0.3s ease;
        }
        
        .package-card:hover {
            border-color: var(--primary);
            box-shadow: 0 20px 60px rgba(0, 102, 255, 0.15);
        }
        
        .package-card.popular {
            border-color: var(--primary);
            background: linear-gradient(135deg, #fff 0%, var(--primary-light) 100%);
        }
        
        .package-badge {
            position: absolute;
            top: -12px;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 0.5rem 1.5rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .package-icon {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            background: var(--primary-light);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .package-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
        }
        
        .package-speed {
            display: flex;
            align-items: baseline;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .package-speed-value {
            font-size: 3rem;
            font-weight: 800;
            color: var(--primary);
        }
        
        .package-speed-unit {
            font-size: 1.25rem;
            color: var(--gray-600);
            font-weight: 600;
        }
        
        .package-price {
            display: flex;
            align-items: baseline;
            gap: 0.25rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .package-currency {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-800);
        }
        
        .package-amount {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--gray-900);
        }
        
        .package-period {
            color: var(--gray-600);
        }
        
        .package-features {
            list-style: none;
            padding: 0;
            margin: 0 0 2rem;
        }
        
        .package-features li {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 0;
            color: var(--gray-700);
            border-bottom: 1px solid var(--gray-100);
        }
        
        .package-features li:last-child {
            border-bottom: none;
        }
        
        .package-features li i {
            color: var(--accent);
            font-size: 1.25rem;
        }
        
        .coverage-section {
            background: var(--dark);
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .coverage-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 30% 70%, rgba(0, 102, 255, 0.2) 0%, transparent 50%);
        }
        
        .coverage-card {
            background: rgba(255,255,255,0.05);
            border-radius: 20px;
            padding: 2rem;
            text-align: center;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .coverage-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--accent);
        }
        
        .coverage-value {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }
        
        .coverage-label {
            color: var(--gray-400);
        }
        
        .contact-section {
            background: white;
        }
        
        .contact-card {
            background: var(--gray-100);
            border-radius: 20px;
            padding: 2rem;
            text-align: center;
            height: 100%;
            transition: all 0.3s ease;
            border: 1px solid transparent;
        }
        
        .contact-card:hover {
            background: white;
            border-color: var(--primary);
            box-shadow: 0 10px 40px rgba(0, 102, 255, 0.1);
        }
        
        .contact-icon {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: var(--primary-light);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin: 0 auto 1.5rem;
        }
        
        .contact-label {
            font-size: 0.875rem;
            color: var(--gray-600);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 0.5rem;
        }
        
        .contact-value {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--gray-900);
        }
        
        .contact-value a {
            color: var(--gray-900);
            text-decoration: none;
        }
        
        .contact-value a:hover {
            color: var(--primary);
        }
        
        .map-container {
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        
        .cta-section {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            padding: 5rem 0;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .cta-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.1'%3E%3Ccircle cx='30' cy='30' r='2'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }
        
        .cta-title {
            font-size: 2.5rem;
            font-weight: 800;
            color: white;
            margin-bottom: 1rem;
        }
        
        .cta-subtitle {
            font-size: 1.25rem;
            color: rgba(255,255,255,0.9);
            margin-bottom: 2rem;
        }
        
        .social-links {
            display: flex;
            gap: 1rem;
            justify-content: center;
        }
        
        .social-link {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: rgba(255,255,255,0.1);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            text-decoration: none;
            transition: all 0.3s ease;
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .social-link:hover {
            background: white;
            color: var(--primary);
            transform: translateY(-3px);
        }
        
        footer {
            background: var(--dark);
            color: white;
            padding: 5rem 0 2rem;
        }
        
        .footer-brand {
            font-size: 1.5rem;
            font-weight: 800;
            color: white;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .footer-desc {
            color: var(--gray-400);
            margin-bottom: 1.5rem;
            max-width: 300px;
        }
        
        .footer-title {
            font-size: 1rem;
            font-weight: 700;
            color: white;
            margin-bottom: 1.5rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .footer-links {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .footer-links li {
            margin-bottom: 0.75rem;
        }
        
        .footer-links a {
            color: var(--gray-400);
            text-decoration: none;
            transition: color 0.2s;
        }
        
        .footer-links a:hover {
            color: var(--primary);
        }
        
        .footer-bottom {
            border-top: 1px solid rgba(255,255,255,0.1);
            margin-top: 3rem;
            padding-top: 2rem;
            text-align: center;
            color: var(--gray-600);
        }
        
        .whatsapp-float {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 60px;
            height: 60px;
            background: #25D366;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            text-decoration: none;
            box-shadow: 0 4px 20px rgba(37, 211, 102, 0.4);
            z-index: 999;
            transition: all 0.3s ease;
        }
        
        .whatsapp-float:hover {
            transform: scale(1.1);
            color: white;
        }
        
        @media (max-width: 991px) {
            .hero-title {
                font-size: 2.5rem;
            }
            
            .hero-stats {
                gap: 2rem;
            }
            
            .hero-stat-value {
                font-size: 1.75rem;
            }
            
            .section-title {
                font-size: 2rem;
            }
        }
        
        @media (max-width: 767px) {
            .hero {
                padding-top: 100px;
                text-align: center;
            }
            
            .hero-title {
                font-size: 2rem;
            }
            
            .hero-subtitle {
                margin: 0 auto 2rem;
            }
            
            .hero-buttons {
                justify-content: center;
            }
            
            .hero-stats {
                justify-content: center;
            }
            
            .package-speed-value {
                font-size: 2.5rem;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="/">
                <i class="bi bi-broadcast-pin"></i>
                <?= htmlspecialchars($company['company_name'] ?? 'ISP Provider') ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#packages">Packages</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#features">Features</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#coverage">Coverage</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contact">Contact</a>
                    </li>
                </ul>
                <a href="?page=order" class="btn btn-primary">Get Started</a>
            </div>
        </div>
    </nav>

    <section class="hero">
        <div class="hero-grid"></div>
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 hero-content">
                    <div class="hero-badge">
                        <i class="bi bi-lightning-charge-fill"></i>
                        Fiber Optic Technology
                    </div>
                    <h1 class="hero-title">
                        <?= htmlspecialchars($landingSettings['hero_title'] ?? 'Lightning Fast') ?>
                        <span class="highlight">Internet</span>
                    </h1>
                    <p class="hero-subtitle">
                        <?= htmlspecialchars($landingSettings['hero_subtitle'] ?? 'Experience blazing fast fiber internet for your home and business. Unlimited data, no throttling, 24/7 support.') ?>
                    </p>
                    <div class="hero-buttons">
                        <a href="#packages" class="btn btn-primary btn-lg">
                            <i class="bi bi-box-seam me-2"></i>View Packages
                        </a>
                        <a href="#contact" class="btn btn-outline-light btn-lg">
                            <i class="bi bi-telephone me-2"></i>Contact Us
                        </a>
                    </div>
                    <div class="hero-stats">
                        <div class="hero-stat">
                            <div class="hero-stat-value">99.9%</div>
                            <div class="hero-stat-label">Uptime</div>
                        </div>
                        <div class="hero-stat">
                            <div class="hero-stat-value">24/7</div>
                            <div class="hero-stat-label">Support</div>
                        </div>
                        <div class="hero-stat">
                            <div class="hero-stat-value">1Gbps</div>
                            <div class="hero-stat-label">Max Speed</div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 hero-image d-none d-lg-block">
                    <div class="speed-card ms-auto" style="max-width: 350px;">
                        <div class="speed-indicator">
                            <div class="speed-value">
                                <strong>500</strong>
                                <span>Mbps</span>
                            </div>
                        </div>
                        <div class="text-center text-white">
                            <div class="mb-2">Current Speed</div>
                            <div class="d-flex justify-content-around">
                                <div>
                                    <small class="text-muted">Download</small>
                                    <div class="fw-bold">512 Mbps</div>
                                </div>
                                <div>
                                    <small class="text-muted">Upload</small>
                                    <div class="fw-bold">256 Mbps</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="features" class="section">
        <div class="container">
            <div class="section-header">
                <div class="section-badge">Why Choose Us</div>
                <h2 class="section-title"><?= htmlspecialchars($landingSettings['about_title'] ?? 'The Best Internet Experience') ?></h2>
                <p class="section-subtitle"><?= htmlspecialchars($landingSettings['about_description'] ?? 'We deliver reliable, high-speed internet with exceptional customer support and cutting-edge technology.') ?></p>
            </div>
            <div class="row g-4">
                <div class="col-lg-3 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon blue">
                            <i class="bi bi-lightning-charge"></i>
                        </div>
                        <h3 class="feature-title">Ultra-Fast Speed</h3>
                        <p class="feature-desc">Experience speeds up to 1Gbps with our fiber optic network. Perfect for streaming, gaming, and remote work.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon purple">
                            <i class="bi bi-infinity"></i>
                        </div>
                        <h3 class="feature-title">Unlimited Data</h3>
                        <p class="feature-desc">No data caps, no throttling. Stream, download, and browse as much as you want without worrying about limits.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon green">
                            <i class="bi bi-shield-check"></i>
                        </div>
                        <h3 class="feature-title">99.9% Uptime</h3>
                        <p class="feature-desc">Our robust infrastructure ensures you stay connected when it matters most. Enterprise-grade reliability.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon orange">
                            <i class="bi bi-headset"></i>
                        </div>
                        <h3 class="feature-title">24/7 Support</h3>
                        <p class="feature-desc">Our dedicated team is always ready to help. Get expert assistance anytime you need it.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="packages" class="section packages-section">
        <div class="container">
            <div class="section-header">
                <div class="section-badge">Our Packages</div>
                <h2 class="section-title">Choose Your Perfect Plan</h2>
                <p class="section-subtitle">Flexible packages designed for every need. From home users to businesses, we have you covered.</p>
            </div>
            <?php if (!empty($packages)): ?>
            <div class="row g-4 justify-content-center">
                <?php foreach ($packages as $package): ?>
                <div class="col-lg-4 col-md-6">
                    <div class="package-card <?= $package['is_popular'] ? 'popular' : '' ?>">
                        <?php if ($package['is_popular']): ?>
                        <div class="package-badge">Most Popular</div>
                        <?php endif; ?>
                        <div class="package-icon">
                            <i class="bi bi-<?= htmlspecialchars($package['icon'] ?? 'wifi') ?>"></i>
                        </div>
                        <h3 class="package-name"><?= htmlspecialchars($package['name']) ?></h3>
                        <div class="package-speed">
                            <span class="package-speed-value"><?= htmlspecialchars($package['speed']) ?></span>
                            <span class="package-speed-unit">Mbps</span>
                        </div>
                        <div class="package-price">
                            <span class="package-currency"><?= htmlspecialchars($company['currency_symbol'] ?? 'KES') ?></span>
                            <span class="package-amount"><?= number_format($package['price'], 0) ?></span>
                            <span class="package-period">/<?= htmlspecialchars($package['billing_cycle'] ?? 'month') ?></span>
                        </div>
                        <?php if (!empty($package['features'])): ?>
                        <ul class="package-features">
                            <?php foreach ($package['features'] as $feature): ?>
                            <li><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($feature) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>
                        <a href="?page=order&package=<?= $package['id'] ?>" class="btn btn-<?= $package['is_popular'] ? 'primary' : 'outline-primary' ?> w-100">
                            Get Started
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="text-center">
                <p class="text-muted">No packages available at the moment. Please check back later.</p>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <section id="coverage" class="section coverage-section">
        <div class="container position-relative">
            <div class="section-header">
                <div class="section-badge" style="background: rgba(0, 212, 170, 0.2); color: var(--accent);">Our Network</div>
                <h2 class="section-title" style="color: white;">Extensive Coverage Area</h2>
                <p class="section-subtitle" style="color: var(--gray-400);">We're continuously expanding to bring fast internet to more communities.</p>
            </div>
            <div class="row g-4">
                <div class="col-md-3 col-6">
                    <div class="coverage-card">
                        <div class="coverage-icon"><i class="bi bi-geo-alt"></i></div>
                        <div class="coverage-value">50+</div>
                        <div class="coverage-label">Areas Covered</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="coverage-card">
                        <div class="coverage-icon"><i class="bi bi-people"></i></div>
                        <div class="coverage-value">10K+</div>
                        <div class="coverage-label">Happy Customers</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="coverage-card">
                        <div class="coverage-icon"><i class="bi bi-building"></i></div>
                        <div class="coverage-value">500+</div>
                        <div class="coverage-label">Businesses Served</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="coverage-card">
                        <div class="coverage-icon"><i class="bi bi-router"></i></div>
                        <div class="coverage-value">1000+</div>
                        <div class="coverage-label">Active Connections</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="contact" class="section contact-section">
        <div class="container">
            <div class="section-header">
                <div class="section-badge">Get In Touch</div>
                <h2 class="section-title">Contact Us</h2>
                <p class="section-subtitle">Have questions? We're here to help. Reach out to us through any of these channels.</p>
            </div>
            <div class="row g-4 justify-content-center mb-5">
                <?php $phone = $contactSettings['contact_phone'] ?? $company['company_phone'] ?? ''; ?>
                <?php if (!empty($phone)): ?>
                <div class="col-lg-3 col-md-6">
                    <div class="contact-card">
                        <div class="contact-icon">
                            <i class="bi bi-telephone"></i>
                        </div>
                        <div class="contact-label">Phone</div>
                        <div class="contact-value">
                            <a href="tel:<?= htmlspecialchars($phone) ?>"><?= htmlspecialchars($phone) ?></a>
                        </div>
                        <?php if (!empty($contactSettings['contact_phone_2'])): ?>
                        <div class="contact-value mt-1">
                            <a href="tel:<?= htmlspecialchars($contactSettings['contact_phone_2']) ?>"><?= htmlspecialchars($contactSettings['contact_phone_2']) ?></a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php $email = $contactSettings['contact_email'] ?? $company['company_email'] ?? ''; ?>
                <?php if (!empty($email)): ?>
                <div class="col-lg-3 col-md-6">
                    <div class="contact-card">
                        <div class="contact-icon">
                            <i class="bi bi-envelope"></i>
                        </div>
                        <div class="contact-label">Email</div>
                        <div class="contact-value">
                            <a href="mailto:<?= htmlspecialchars($email) ?>"><?= htmlspecialchars($email) ?></a>
                        </div>
                        <?php if (!empty($contactSettings['contact_email_support'])): ?>
                        <div class="contact-value mt-1">
                            <a href="mailto:<?= htmlspecialchars($contactSettings['contact_email_support']) ?>">Support: <?= htmlspecialchars($contactSettings['contact_email_support']) ?></a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php $address = $contactSettings['contact_address'] ?? $company['company_address'] ?? ''; ?>
                <?php if (!empty($address)): ?>
                <div class="col-lg-3 col-md-6">
                    <div class="contact-card">
                        <div class="contact-icon">
                            <i class="bi bi-geo-alt"></i>
                        </div>
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
                    <div class="contact-card">
                        <div class="contact-icon">
                            <i class="bi bi-clock"></i>
                        </div>
                        <div class="contact-label">Working Hours</div>
                        <div class="contact-value">
                            <?= htmlspecialchars($contactSettings['working_days'] ?? 'Monday - Friday') ?>
                            <br><?= htmlspecialchars($contactSettings['working_hours'] ?? '8:00 AM - 5:00 PM') ?>
                            <br><small class="text-muted">Support: <?= htmlspecialchars($contactSettings['support_hours'] ?? '24/7') ?></small>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($contactSettings['map_embed_url'])): ?>
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="map-container">
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
            <h2 class="cta-title">Ready to Get Connected?</h2>
            <p class="cta-subtitle">Join thousands of satisfied customers enjoying high-speed internet</p>
            <a href="?page=order" class="btn btn-light btn-lg">
                <i class="bi bi-rocket-takeoff me-2"></i>Get Started Today
            </a>
            
            <?php 
            $hasSocial = !empty($contactSettings['social_facebook']) || !empty($contactSettings['social_twitter']) || 
                         !empty($contactSettings['social_instagram']) || !empty($contactSettings['social_linkedin']);
            if ($hasSocial): ?>
            <div class="social-links mt-4">
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
                <?php if (!empty($contactSettings['social_youtube'])): ?>
                <a href="<?= htmlspecialchars($contactSettings['social_youtube']) ?>" class="social-link" target="_blank"><i class="bi bi-youtube"></i></a>
                <?php endif; ?>
                <?php if (!empty($contactSettings['social_tiktok'])): ?>
                <a href="<?= htmlspecialchars($contactSettings['social_tiktok']) ?>" class="social-link" target="_blank"><i class="bi bi-tiktok"></i></a>
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
                        <i class="bi bi-broadcast-pin"></i>
                        <?= htmlspecialchars($company['company_name'] ?? 'ISP Provider') ?>
                    </div>
                    <p class="footer-desc">
                        <?= htmlspecialchars($landingSettings['footer_text'] ?? 'Your trusted partner for fast, reliable internet connectivity. Connecting homes and businesses since 2020.') ?>
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
                        <li><a href="#packages">Packages</a></li>
                        <li><a href="#features">Features</a></li>
                        <li><a href="#coverage">Coverage</a></li>
                        <li><a href="#contact">Contact</a></li>
                    </ul>
                </div>
                <div class="col-lg-2 col-md-4 col-6 mb-4">
                    <h6 class="footer-title">Services</h6>
                    <ul class="footer-links">
                        <li><a href="#">Home Internet</a></li>
                        <li><a href="#">Business Internet</a></li>
                        <li><a href="#">Fiber Optic</a></li>
                        <li><a href="#">Wireless</a></li>
                    </ul>
                </div>
                <div class="col-lg-2 col-md-4 col-6 mb-4">
                    <h6 class="footer-title">Support</h6>
                    <ul class="footer-links">
                        <li><a href="?page=login">Customer Portal</a></li>
                        <li><a href="#contact">Help Center</a></li>
                        <li><a href="#">Report Issue</a></li>
                        <li><a href="#">FAQs</a></li>
                    </ul>
                </div>
                <div class="col-lg-2 col-md-4 col-6 mb-4">
                    <h6 class="footer-title">Legal</h6>
                    <ul class="footer-links">
                        <li><a href="#">Terms of Service</a></li>
                        <li><a href="#">Privacy Policy</a></li>
                        <li><a href="#">Acceptable Use</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                &copy; <?= date('Y') ?> <?= htmlspecialchars($company['company_name'] ?? 'ISP Provider') ?>. All rights reserved.
            </div>
        </div>
    </footer>

    <?php $whatsapp = $contactSettings['contact_whatsapp'] ?? ''; ?>
    <?php if (!empty($whatsapp)): ?>
    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $whatsapp) ?>" class="whatsapp-float" target="_blank" title="Chat on WhatsApp">
        <i class="bi bi-whatsapp"></i>
    </a>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
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
    </script>
</body>
</html>
