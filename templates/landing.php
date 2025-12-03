<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($company['company_name'] ?? 'ISP Provider') ?> - High-Speed Internet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: <?= htmlspecialchars($landingSettings['primary_color'] ?? '#2563eb') ?>;
            --primary-dark: color-mix(in srgb, var(--primary-color) 80%, black);
            --primary-light: color-mix(in srgb, var(--primary-color) 20%, white);
            --gradient-start: var(--primary-color);
            --gradient-end: #7c3aed;
        }
        
        * {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }
        
        body {
            background: #f8fafc;
            color: #1e293b;
        }
        
        .navbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 1rem 0;
        }
        
        .navbar-brand {
            font-weight: 800;
            font-size: 1.5rem;
            color: var(--primary-color) !important;
        }
        
        .nav-link {
            font-weight: 500;
            color: #475569 !important;
            transition: color 0.2s;
        }
        
        .nav-link:hover {
            color: var(--primary-color) !important;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            border: none;
            padding: 0.75rem 2rem;
            font-weight: 600;
            border-radius: 50px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 40px rgba(37, 99, 235, 0.3);
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
        }
        
        .btn-outline-primary {
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            padding: 0.75rem 2rem;
            font-weight: 600;
            border-radius: 50px;
        }
        
        .btn-outline-primary:hover {
            background: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .hero-section {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%);
            min-height: 90vh;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Ccircle cx='30' cy='30' r='2'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }
        
        .hero-section::after {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 70%;
            height: 200%;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            opacity: 0.1;
            border-radius: 50%;
            filter: blur(100px);
        }
        
        .hero-content {
            position: relative;
            z-index: 1;
        }
        
        .hero-title {
            font-size: 4rem;
            font-weight: 800;
            color: #fff;
            line-height: 1.1;
            margin-bottom: 1.5rem;
        }
        
        .hero-title span {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .hero-subtitle {
            font-size: 1.25rem;
            color: #94a3b8;
            max-width: 500px;
            margin-bottom: 2rem;
        }
        
        .hero-stats {
            display: flex;
            gap: 3rem;
            margin-top: 3rem;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: #fff;
        }
        
        .stat-label {
            color: #64748b;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .hero-image {
            position: relative;
            z-index: 1;
        }
        
        .speed-badge {
            position: absolute;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            color: #fff;
            padding: 1rem 1.5rem;
            border-radius: 1rem;
            font-weight: 700;
            box-shadow: 0 20px 60px rgba(37, 99, 235, 0.4);
            animation: float 3s ease-in-out infinite;
        }
        
        .speed-badge.top {
            top: 20%;
            right: 0;
        }
        
        .speed-badge.bottom {
            bottom: 30%;
            left: -10%;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        
        .section-title {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 1rem;
        }
        
        .section-subtitle {
            color: #64748b;
            font-size: 1.125rem;
            max-width: 600px;
            margin: 0 auto 3rem;
        }
        
        .packages-section {
            padding: 6rem 0;
            background: #fff;
        }
        
        .package-card {
            background: #fff;
            border-radius: 1.5rem;
            padding: 2rem;
            position: relative;
            border: 2px solid #e2e8f0;
            transition: all 0.3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .package-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
            border-color: var(--primary-color);
        }
        
        .package-card.popular {
            border-color: var(--primary-color);
            background: linear-gradient(180deg, rgba(37, 99, 235, 0.05) 0%, #fff 100%);
        }
        
        .package-badge {
            position: absolute;
            top: -12px;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            color: #fff;
            padding: 0.5rem 1.5rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .package-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary-light), #fff);
            border-radius: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            color: var(--primary-color);
            margin-bottom: 1.5rem;
        }
        
        .package-name {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .package-speed {
            font-size: 3rem;
            font-weight: 800;
            color: var(--primary-color);
            line-height: 1;
        }
        
        .package-speed-unit {
            font-size: 1.25rem;
            color: #64748b;
            font-weight: 500;
        }
        
        .package-price {
            margin: 1.5rem 0;
            padding: 1rem 0;
            border-top: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .price-amount {
            font-size: 2.5rem;
            font-weight: 800;
            color: #1e293b;
        }
        
        .price-currency {
            font-size: 1rem;
            color: #64748b;
            vertical-align: top;
        }
        
        .price-period {
            font-size: 0.875rem;
            color: #64748b;
        }
        
        .package-features {
            list-style: none;
            padding: 0;
            margin: 0 0 1.5rem;
            flex-grow: 1;
        }
        
        .package-features li {
            padding: 0.5rem 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: #475569;
        }
        
        .package-features li i {
            color: #10b981;
            font-size: 1.125rem;
        }
        
        .features-section {
            padding: 6rem 0;
            background: linear-gradient(180deg, #f8fafc 0%, #fff 100%);
        }
        
        .feature-card {
            background: #fff;
            border-radius: 1rem;
            padding: 2rem;
            text-align: center;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            height: 100%;
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }
        
        .feature-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary-light), #fff);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2rem;
            color: var(--primary-color);
        }
        
        .feature-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
        }
        
        .feature-desc {
            color: #64748b;
            font-size: 0.9375rem;
        }
        
        .cta-section {
            padding: 6rem 0;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            position: relative;
            overflow: hidden;
        }
        
        .cta-section::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -20%;
            width: 60%;
            height: 200%;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            opacity: 0.1;
            border-radius: 50%;
            filter: blur(80px);
        }
        
        .cta-title {
            font-size: 2.5rem;
            font-weight: 800;
            color: #fff;
            margin-bottom: 1rem;
        }
        
        .cta-subtitle {
            color: #94a3b8;
            font-size: 1.125rem;
            margin-bottom: 2rem;
        }
        
        .contact-section {
            padding: 5rem 0;
            background: #fff;
        }
        
        .contact-card {
            background: #f8fafc;
            border-radius: 1rem;
            padding: 2rem;
            text-align: center;
            height: 100%;
        }
        
        .contact-icon {
            width: 60px;
            height: 60px;
            background: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: #fff;
            font-size: 1.5rem;
        }
        
        .contact-label {
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 0.25rem;
        }
        
        .contact-value {
            color: #64748b;
        }
        
        .contact-value a {
            color: var(--primary-color);
            text-decoration: none;
        }
        
        footer {
            background: #0f172a;
            color: #94a3b8;
            padding: 3rem 0 2rem;
        }
        
        .footer-brand {
            font-size: 1.5rem;
            font-weight: 800;
            color: #fff;
            margin-bottom: 1rem;
        }
        
        .footer-links h6 {
            color: #fff;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        
        .footer-links a {
            color: #94a3b8;
            text-decoration: none;
            display: block;
            padding: 0.25rem 0;
            transition: color 0.2s;
        }
        
        .footer-links a:hover {
            color: var(--primary-color);
        }
        
        .footer-bottom {
            border-top: 1px solid #1e293b;
            margin-top: 2rem;
            padding-top: 1.5rem;
            text-align: center;
            font-size: 0.875rem;
        }
        
        .social-links a {
            color: #64748b;
            font-size: 1.25rem;
            margin: 0 0.5rem;
            transition: color 0.2s;
        }
        
        .social-links a:hover {
            color: var(--primary-color);
        }
        
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }
            
            .hero-stats {
                gap: 1.5rem;
            }
            
            .stat-number {
                font-size: 1.75rem;
            }
            
            .package-speed {
                font-size: 2.25rem;
            }
            
            .price-amount {
                font-size: 2rem;
            }
        }
        
        .no-packages-message {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border-radius: 1rem;
            padding: 3rem;
            text-align: center;
        }
        
        .no-packages-icon {
            font-size: 4rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand" href="/">
                <i class="bi bi-wifi me-2"></i><?= htmlspecialchars($company['company_name'] ?? 'ISP Provider') ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto me-3">
                    <li class="nav-item">
                        <a class="nav-link" href="#packages">Packages</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#features">Features</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contact">Contact</a>
                    </li>
                </ul>
                <a href="/login" class="btn btn-primary">Customer Portal</a>
            </div>
        </div>
    </nav>
    
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 hero-content">
                    <h1 class="hero-title">
                        <?= htmlspecialchars($landingSettings['hero_title'] ?? 'Lightning Fast') ?>
                        <br><span>Internet</span>
                    </h1>
                    <p class="hero-subtitle">
                        <?= htmlspecialchars($landingSettings['hero_subtitle'] ?? 'Experience blazing fast fiber internet for your home and business. Reliable, affordable, and always connected.') ?>
                    </p>
                    <div class="d-flex gap-3 flex-wrap">
                        <a href="<?= htmlspecialchars($landingSettings['hero_cta_link'] ?? '#packages') ?>" class="btn btn-primary btn-lg">
                            <?= htmlspecialchars($landingSettings['hero_cta_text'] ?? 'View Packages') ?>
                        </a>
                        <a href="#contact" class="btn btn-outline-light btn-lg">Contact Us</a>
                    </div>
                    <div class="hero-stats">
                        <div class="stat-item">
                            <div class="stat-number">99.9%</div>
                            <div class="stat-label">Uptime</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number">24/7</div>
                            <div class="stat-label">Support</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number">1Gbps+</div>
                            <div class="stat-label">Speed</div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 d-none d-lg-block hero-image">
                    <div class="position-relative">
                        <svg viewBox="0 0 400 400" style="width: 100%; max-width: 500px;">
                            <defs>
                                <linearGradient id="grad1" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" style="stop-color:var(--gradient-start);stop-opacity:0.2" />
                                    <stop offset="100%" style="stop-color:var(--gradient-end);stop-opacity:0.2" />
                                </linearGradient>
                            </defs>
                            <circle cx="200" cy="200" r="180" fill="url(#grad1)" />
                            <circle cx="200" cy="200" r="140" fill="none" stroke="var(--primary-color)" stroke-width="2" stroke-dasharray="10,5" opacity="0.3" />
                            <circle cx="200" cy="200" r="100" fill="none" stroke="var(--primary-color)" stroke-width="1" opacity="0.2" />
                            <g transform="translate(150, 150)">
                                <path d="M50 0 L90 40 L70 40 L70 80 L30 80 L30 40 L10 40 Z" fill="var(--primary-color)" opacity="0.8"/>
                                <circle cx="50" cy="50" r="60" fill="none" stroke="var(--primary-color)" stroke-width="3" stroke-dasharray="5,5" opacity="0.5">
                                    <animateTransform attributeName="transform" type="rotate" from="0 50 50" to="360 50 50" dur="20s" repeatCount="indefinite"/>
                                </circle>
                            </g>
                        </svg>
                        <div class="speed-badge top">
                            <i class="bi bi-lightning-charge me-2"></i>Up to 1Gbps
                        </div>
                        <div class="speed-badge bottom">
                            <i class="bi bi-wifi me-2"></i>Fiber Optic
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <section id="packages" class="packages-section">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">Choose Your Perfect Plan</h2>
                <p class="section-subtitle">Select from our range of high-speed internet packages designed for every need and budget</p>
            </div>
            
            <?php if (empty($packages)): ?>
            <div class="no-packages-message">
                <div class="no-packages-icon">
                    <i class="bi bi-box-seam"></i>
                </div>
                <h4>Packages Coming Soon</h4>
                <p class="text-muted mb-0">We're preparing amazing internet packages for you. Check back soon!</p>
            </div>
            <?php else: ?>
            <div class="row g-4 justify-content-center">
                <?php foreach ($packages as $package): ?>
                <div class="col-lg-4 col-md-6">
                    <div class="package-card <?= $package['is_popular'] ? 'popular' : '' ?>">
                        <?php if (!empty($package['badge_text'])): ?>
                        <div class="package-badge" <?= !empty($package['badge_color']) ? 'style="background:' . htmlspecialchars($package['badge_color']) . '"' : '' ?>>
                            <?= htmlspecialchars($package['badge_text']) ?>
                        </div>
                        <?php elseif ($package['is_popular']): ?>
                        <div class="package-badge">Most Popular</div>
                        <?php endif; ?>
                        
                        <div class="package-icon">
                            <?php
                            $iconMap = [
                                'wifi' => 'bi-wifi',
                                'rocket' => 'bi-rocket-takeoff',
                                'lightning' => 'bi-lightning-charge',
                                'globe' => 'bi-globe',
                                'building' => 'bi-building',
                                'house' => 'bi-house',
                                'speedometer' => 'bi-speedometer2',
                                'star' => 'bi-star'
                            ];
                            $icon = $iconMap[$package['icon'] ?? 'wifi'] ?? 'bi-wifi';
                            ?>
                            <i class="bi <?= $icon ?>"></i>
                        </div>
                        
                        <h3 class="package-name"><?= htmlspecialchars($package['name']) ?></h3>
                        
                        <div class="package-speed">
                            <?= htmlspecialchars($package['speed']) ?>
                            <span class="package-speed-unit"><?= htmlspecialchars($package['speed_unit'] ?? 'Mbps') ?></span>
                        </div>
                        
                        <?php if (!empty($package['description'])): ?>
                        <p class="text-muted mt-2 mb-0"><?= htmlspecialchars($package['description']) ?></p>
                        <?php endif; ?>
                        
                        <div class="package-price">
                            <span class="price-currency"><?= htmlspecialchars($package['currency'] ?? 'KES') ?></span>
                            <span class="price-amount"><?= number_format($package['price'], 0) ?></span>
                            <span class="price-period">/<?= htmlspecialchars($package['billing_cycle'] ?? 'month') ?></span>
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
            <?php endif; ?>
        </div>
    </section>
    
    <section id="features" class="features-section">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title"><?= htmlspecialchars($landingSettings['about_title'] ?? 'Why Choose Us?') ?></h2>
                <p class="section-subtitle"><?= htmlspecialchars($landingSettings['about_description'] ?? 'We deliver reliable, high-speed internet with exceptional customer support.') ?></p>
            </div>
            
            <div class="row g-4">
                <div class="col-lg-3 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="bi bi-lightning-charge"></i>
                        </div>
                        <h5 class="feature-title">Blazing Fast Speed</h5>
                        <p class="feature-desc">Experience internet speeds up to 1Gbps for seamless streaming, gaming, and work</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="bi bi-shield-check"></i>
                        </div>
                        <h5 class="feature-title">99.9% Uptime</h5>
                        <p class="feature-desc">Our robust network infrastructure ensures you stay connected when it matters most</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="bi bi-headset"></i>
                        </div>
                        <h5 class="feature-title">24/7 Support</h5>
                        <p class="feature-desc">Our dedicated support team is always ready to help you with any issues</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="bi bi-tools"></i>
                        </div>
                        <h5 class="feature-title">Free Installation</h5>
                        <p class="feature-desc">Professional installation included with all packages at no extra cost</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <section class="cta-section">
        <div class="container text-center position-relative" style="z-index: 1;">
            <h2 class="cta-title">Ready to Get Connected?</h2>
            <p class="cta-subtitle">Join thousands of satisfied customers enjoying high-speed internet</p>
            <a href="#contact" class="btn btn-primary btn-lg">Contact Us Now</a>
        </div>
    </section>
    
    <section id="contact" class="contact-section">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">Get In Touch</h2>
                <p class="section-subtitle">Have questions? We're here to help. Reach out to us through any of these channels.</p>
            </div>
            
            <div class="row g-4 justify-content-center">
                <?php if (!empty($landingSettings['contact_phone'] ?? $company['company_phone'])): ?>
                <div class="col-lg-3 col-md-6">
                    <div class="contact-card">
                        <div class="contact-icon">
                            <i class="bi bi-telephone"></i>
                        </div>
                        <div class="contact-label">Phone</div>
                        <div class="contact-value">
                            <a href="tel:<?= htmlspecialchars($landingSettings['contact_phone'] ?? $company['company_phone']) ?>">
                                <?= htmlspecialchars($landingSettings['contact_phone'] ?? $company['company_phone']) ?>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($landingSettings['contact_email'] ?? $company['company_email'])): ?>
                <div class="col-lg-3 col-md-6">
                    <div class="contact-card">
                        <div class="contact-icon">
                            <i class="bi bi-envelope"></i>
                        </div>
                        <div class="contact-label">Email</div>
                        <div class="contact-value">
                            <a href="mailto:<?= htmlspecialchars($landingSettings['contact_email'] ?? $company['company_email']) ?>">
                                <?= htmlspecialchars($landingSettings['contact_email'] ?? $company['company_email']) ?>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($landingSettings['contact_address'] ?? $company['company_address'])): ?>
                <div class="col-lg-3 col-md-6">
                    <div class="contact-card">
                        <div class="contact-icon">
                            <i class="bi bi-geo-alt"></i>
                        </div>
                        <div class="contact-label">Address</div>
                        <div class="contact-value"><?= htmlspecialchars($landingSettings['contact_address'] ?? $company['company_address']) ?></div>
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
                            <?= htmlspecialchars($company['working_hours_start'] ?? '09:00') ?> - <?= htmlspecialchars($company['working_hours_end'] ?? '17:00') ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <footer>
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <div class="footer-brand">
                        <i class="bi bi-wifi me-2"></i><?= htmlspecialchars($company['company_name'] ?? 'ISP Provider') ?>
                    </div>
                    <p><?= htmlspecialchars($landingSettings['footer_text'] ?? 'Your trusted partner for fast, reliable internet connectivity.') ?></p>
                    <div class="social-links">
                        <a href="#"><i class="bi bi-facebook"></i></a>
                        <a href="#"><i class="bi bi-twitter-x"></i></a>
                        <a href="#"><i class="bi bi-instagram"></i></a>
                        <a href="#"><i class="bi bi-linkedin"></i></a>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 mb-4 footer-links">
                    <h6>Quick Links</h6>
                    <a href="#packages">Packages</a>
                    <a href="#features">Features</a>
                    <a href="#contact">Contact</a>
                    <a href="/login">Customer Portal</a>
                </div>
                <div class="col-lg-2 col-md-4 mb-4 footer-links">
                    <h6>Services</h6>
                    <a href="#">Home Internet</a>
                    <a href="#">Business Internet</a>
                    <a href="#">Fiber Optic</a>
                    <a href="#">Wireless</a>
                </div>
                <div class="col-lg-2 col-md-4 mb-4 footer-links">
                    <h6>Support</h6>
                    <a href="#">Help Center</a>
                    <a href="#">FAQs</a>
                    <a href="#">Report Issue</a>
                    <a href="#">Speed Test</a>
                </div>
                <div class="col-lg-2 col-md-4 mb-4 footer-links">
                    <h6>Legal</h6>
                    <a href="#">Terms of Service</a>
                    <a href="#">Privacy Policy</a>
                    <a href="#">Acceptable Use</a>
                </div>
            </div>
            <div class="footer-bottom">
                &copy; <?= date('Y') ?> <?= htmlspecialchars($company['company_name'] ?? 'ISP Provider') ?>. All rights reserved.
            </div>
        </div>
    </footer>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });
        
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.style.boxShadow = '0 4px 6px -1px rgba(0, 0, 0, 0.1)';
            } else {
                navbar.style.boxShadow = '0 1px 3px rgba(0,0,0,0.1)';
            }
        });
    </script>
</body>
</html>
