<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($company['company_name'] ?? 'ISP Provider') ?> - High-Speed Internet Services</title>
    <meta name="description" content="<?= htmlspecialchars($landingSettings['hero_subtitle'] ?? 'Fast, reliable internet for your home and business') ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --accent: <?= htmlspecialchars($landingSettings['primary_color'] ?? '#4F46E5') ?>;
            --text: #1a1a1a;
            --text-light: #6b7280;
            --text-lighter: #9ca3af;
            --border: #e5e7eb;
            --bg: #ffffff;
            --bg-alt: #fafafa;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            color: var(--text);
            background: var(--bg);
            -webkit-font-smoothing: antialiased;
        }

        .navbar {
            padding: 1.5rem 0;
            background: var(--bg);
            border-bottom: 1px solid var(--border);
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            transition: padding 0.3s ease;
        }

        .navbar.scrolled { padding: 0.75rem 0; }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.25rem;
            color: var(--text) !important;
            text-decoration: none;
            letter-spacing: -0.02em;
        }

        .nav-link {
            font-weight: 400;
            font-size: 0.9rem;
            color: var(--text-light) !important;
            padding: 0.5rem 1.25rem !important;
            transition: color 0.2s ease;
        }

        .nav-link:hover { color: var(--text) !important; }

        .btn-login {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text-light);
            text-decoration: none;
            padding: 0.5rem 1rem;
            transition: color 0.2s;
        }

        .btn-login:hover { color: var(--text); }

        .btn-accent {
            background: var(--accent);
            color: #fff !important;
            padding: 0.6rem 1.5rem;
            border-radius: 6px;
            font-weight: 500;
            font-size: 0.9rem;
            border: none;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            transition: opacity 0.2s ease;
        }

        .btn-accent:hover { opacity: 0.85; color: #fff; }

        .btn-outline-accent {
            background: transparent;
            color: var(--accent);
            padding: 0.6rem 1.5rem;
            border-radius: 6px;
            font-weight: 500;
            font-size: 0.9rem;
            border: 1px solid var(--accent);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            transition: all 0.2s ease;
        }

        .btn-outline-accent:hover {
            background: var(--accent);
            color: #fff;
        }

        .btn-ghost {
            background: transparent;
            color: var(--text-light);
            padding: 0.6rem 1.5rem;
            border-radius: 6px;
            font-weight: 500;
            font-size: 0.9rem;
            border: 1px solid var(--border);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-ghost:hover {
            border-color: var(--text-light);
            color: var(--text);
        }

        .navbar-toggler {
            border: 1px solid var(--border);
            padding: 0.4rem 0.6rem;
        }

        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%2826,26,26,0.6%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        }

        .hero {
            padding: 12rem 0 8rem;
            text-align: center;
        }

        .hero-title {
            font-size: 3.75rem;
            font-weight: 800;
            letter-spacing: -0.04em;
            line-height: 1.05;
            margin-bottom: 1.5rem;
            color: var(--text);
        }

        .hero-title .accent { color: var(--accent); }

        .hero-subtitle {
            font-size: 1.15rem;
            color: var(--text-light);
            max-width: 520px;
            margin: 0 auto 2.5rem;
            line-height: 1.7;
            font-weight: 400;
        }

        .hero-actions {
            display: flex;
            gap: 0.75rem;
            justify-content: center;
            flex-wrap: wrap;
            margin-bottom: 4rem;
        }

        .hero-trust {
            display: flex;
            gap: 2rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .hero-trust-item {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            color: var(--text-lighter);
            font-size: 0.85rem;
        }

        .hero-trust-item i { color: var(--accent); }

        .section { padding: 6rem 0; }
        .section-alt { background: var(--bg-alt); }

        .section-label {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--accent);
            margin-bottom: 0.75rem;
        }

        .section-title {
            font-size: 2.5rem;
            font-weight: 800;
            letter-spacing: -0.03em;
            margin-bottom: 1rem;
            color: var(--text);
        }

        .section-desc {
            font-size: 1rem;
            color: var(--text-light);
            max-width: 500px;
            line-height: 1.7;
        }

        .section-desc.centered { margin: 0 auto; }

        .section-header {
            text-align: center;
            margin-bottom: 4rem;
        }

        .feature-item {
            padding: 2rem 0;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: flex-start;
            gap: 1.5rem;
        }

        .feature-item:last-child { border-bottom: none; }

        .feature-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            color: var(--accent);
            font-size: 1.1rem;
        }

        .feature-content h4 {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.35rem;
            color: var(--text);
        }

        .feature-content p {
            font-size: 0.9rem;
            color: var(--text-light);
            margin: 0;
            line-height: 1.6;
        }

        .package-card {
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 2.5rem 2rem;
            height: 100%;
            background: var(--bg);
            transition: border-color 0.2s ease;
            position: relative;
        }

        .package-card:hover { border-color: var(--accent); }

        .package-card.featured {
            border-color: var(--accent);
            box-shadow: 0 0 0 1px var(--accent);
        }

        .package-badge {
            position: absolute;
            top: -10px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--accent);
            color: #fff;
            font-size: 0.7rem;
            font-weight: 600;
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .package-name {
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-light);
            margin-bottom: 1rem;
        }

        .package-speed {
            font-size: 3rem;
            font-weight: 800;
            letter-spacing: -0.04em;
            color: var(--text);
            line-height: 1;
            margin-bottom: 0.25rem;
        }

        .package-speed-unit {
            font-size: 1rem;
            font-weight: 400;
            color: var(--text-light);
        }

        .package-price {
            margin: 1.5rem 0;
            padding: 1rem 0;
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
        }

        .package-currency {
            font-size: 0.85rem;
            color: var(--text-light);
            font-weight: 500;
        }

        .package-amount {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text);
            letter-spacing: -0.02em;
        }

        .package-period {
            font-size: 0.85rem;
            color: var(--text-lighter);
        }

        .package-features {
            list-style: none;
            padding: 0;
            margin: 0 0 1.5rem;
        }

        .package-features li {
            padding: 0.4rem 0;
            font-size: 0.9rem;
            color: var(--text-light);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .package-features li i { color: var(--accent); font-size: 0.8rem; }

        .package-btn {
            width: 100%;
            text-align: center;
            justify-content: center;
        }

        .contact-item {
            padding: 2rem 0;
            border-bottom: 1px solid var(--border);
        }

        .contact-item:last-child { border-bottom: none; }

        .contact-label {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-lighter);
            margin-bottom: 0.35rem;
        }

        .contact-value {
            font-size: 1rem;
            color: var(--text);
        }

        .contact-value a {
            color: var(--text);
            text-decoration: none;
            transition: color 0.2s;
        }

        .contact-value a:hover { color: var(--accent); }

        .map-container {
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid var(--border);
        }

        .cta-section {
            padding: 8rem 0;
            text-align: center;
            border-top: 1px solid var(--border);
        }

        .cta-title {
            font-size: 2.75rem;
            font-weight: 800;
            letter-spacing: -0.03em;
            margin-bottom: 1rem;
            color: var(--text);
        }

        .cta-desc {
            font-size: 1.05rem;
            color: var(--text-light);
            margin-bottom: 2rem;
            max-width: 450px;
            margin-left: auto;
            margin-right: auto;
            line-height: 1.7;
        }

        .cta-actions {
            display: flex;
            gap: 0.75rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        footer {
            border-top: 1px solid var(--border);
            padding: 3rem 0 2rem;
            color: var(--text-lighter);
            font-size: 0.85rem;
        }

        .footer-brand {
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--text);
            margin-bottom: 0.5rem;
        }

        .footer-desc {
            color: var(--text-lighter);
            font-size: 0.85rem;
            max-width: 280px;
            line-height: 1.6;
        }

        .footer-title {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text);
            margin-bottom: 1rem;
        }

        .footer-links {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .footer-links li { margin-bottom: 0.5rem; }

        .footer-links a {
            color: var(--text-lighter);
            text-decoration: none;
            font-size: 0.85rem;
            transition: color 0.2s;
        }

        .footer-links a:hover { color: var(--text); }

        .footer-bottom {
            border-top: 1px solid var(--border);
            margin-top: 2rem;
            padding-top: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .whatsapp-float {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 52px;
            height: 52px;
            background: #25D366;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.5rem;
            text-decoration: none;
            z-index: 999;
            transition: transform 0.2s ease;
        }

        .whatsapp-float:hover { transform: scale(1.08); color: #fff; }

        .fade-in {
            opacity: 0;
            transform: translateY(16px);
            transition: opacity 0.5s ease, transform 0.5s ease;
        }

        .fade-in.visible {
            opacity: 1;
            transform: translateY(0);
        }

        @media (max-width: 991px) {
            .hero-title { font-size: 2.75rem; }
            .section-title { font-size: 2rem; }
            .cta-title { font-size: 2rem; }
        }

        @media (max-width: 767px) {
            .hero { padding: 9rem 0 5rem; }
            .hero-title { font-size: 2.25rem; }
            .section { padding: 4rem 0; }
            .package-speed { font-size: 2.25rem; }
            .footer-bottom { text-align: center; justify-content: center; }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <div class="d-flex w-100 align-items-center justify-content-between">
                <a class="navbar-brand" href="/"><?= htmlspecialchars($company['company_name'] ?? 'ISP Provider') ?></a>
                <button class="navbar-toggler d-lg-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                    <ul class="navbar-nav align-items-center">
                        <li class="nav-item"><a class="nav-link" href="#features">Features</a></li>
                        <li class="nav-item"><a class="nav-link" href="#packages">Packages</a></li>
                        <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
                        <li class="nav-item ms-lg-2">
                            <a class="btn-login" href="?page=login">Log in</a>
                        </li>
                        <li class="nav-item ms-lg-1">
                            <a class="btn-accent" href="?page=order">Get Started</a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <section class="hero">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <h1 class="hero-title fade-in">
                        <?= htmlspecialchars($landingSettings['hero_title'] ?? 'Internet that just') ?> <span class="accent">works.</span>
                    </h1>
                    <p class="hero-subtitle fade-in">
                        <?= htmlspecialchars($landingSettings['hero_subtitle'] ?? 'Fast, reliable fiber internet for your home and business. No complexity, no hidden fees — just connectivity.') ?>
                    </p>
                    <div class="hero-actions fade-in">
                        <a href="#packages" class="btn-accent">View Packages</a>
                        <a href="?page=order" class="btn-outline-accent">Order Now</a>
                        <button type="button" class="btn-ghost" data-bs-toggle="modal" data-bs-target="#complaintModal">
                            <i class="bi bi-exclamation-circle"></i> Report Issue
                        </button>
                    </div>
                    <div class="hero-trust fade-in">
                        <div class="hero-trust-item">
                            <i class="bi bi-check2"></i> No hidden fees
                        </div>
                        <div class="hero-trust-item">
                            <i class="bi bi-check2"></i> Free installation
                        </div>
                        <div class="hero-trust-item">
                            <i class="bi bi-check2"></i> 24/7 support
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="features" class="section section-alt">
        <div class="container">
            <div class="row">
                <div class="col-lg-5 mb-4 mb-lg-0">
                    <div class="section-label fade-in">Features</div>
                    <h2 class="section-title fade-in">Why choose us</h2>
                    <p class="section-desc fade-in">We keep things simple. High-speed fiber internet with transparent pricing and reliable support.</p>
                </div>
                <div class="col-lg-6 offset-lg-1">
                    <div class="feature-item fade-in">
                        <div class="feature-icon"><i class="bi bi-lightning"></i></div>
                        <div class="feature-content">
                            <h4>Blazing Fast Speeds</h4>
                            <p>Fiber optic connections delivering symmetrical upload and download speeds up to 10 Gbps.</p>
                        </div>
                    </div>
                    <div class="feature-item fade-in">
                        <div class="feature-icon"><i class="bi bi-arrow-repeat"></i></div>
                        <div class="feature-content">
                            <h4>99.9% Uptime</h4>
                            <p>Enterprise-grade reliability with redundant infrastructure and proactive monitoring.</p>
                        </div>
                    </div>
                    <div class="feature-item fade-in">
                        <div class="feature-icon"><i class="bi bi-shield-check"></i></div>
                        <div class="feature-content">
                            <h4>Secure Connection</h4>
                            <p>Built-in DDoS protection and secure network architecture to keep you safe online.</p>
                        </div>
                    </div>
                    <div class="feature-item fade-in">
                        <div class="feature-icon"><i class="bi bi-headset"></i></div>
                        <div class="feature-content">
                            <h4>Always-On Support</h4>
                            <p>Reach our technical team 24/7 via phone, WhatsApp, or email whenever you need help.</p>
                        </div>
                    </div>
                    <div class="feature-item fade-in">
                        <div class="feature-icon"><i class="bi bi-tools"></i></div>
                        <div class="feature-content">
                            <h4>Free Installation</h4>
                            <p>Professional setup at no extra cost including a free WiFi router to get you started.</p>
                        </div>
                    </div>
                    <div class="feature-item fade-in">
                        <div class="feature-icon"><i class="bi bi-tag"></i></div>
                        <div class="feature-content">
                            <h4>Transparent Pricing</h4>
                            <p>No hidden fees, no surprise charges. The price you see is the price you pay, every month.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="packages" class="section">
        <div class="container">
            <div class="section-header">
                <div class="section-label fade-in">Pricing</div>
                <h2 class="section-title fade-in">Choose your plan</h2>
                <p class="section-desc centered fade-in">Simple pricing. All plans include unlimited data, free installation, and a WiFi router.</p>
            </div>
            <div class="row g-4 justify-content-center">
                <?php if (empty($packages)): ?>
                <div class="col-lg-4 col-md-6">
                    <div class="package-card fade-in">
                        <div class="package-name">Basic Home</div>
                        <div class="package-speed">10 <span class="package-speed-unit">Mbps</span></div>
                        <div class="package-price">
                            <span class="package-currency">KES </span>
                            <span class="package-amount">1,500</span>
                            <span class="package-period"> /month</span>
                        </div>
                        <ul class="package-features">
                            <li><i class="bi bi-check2"></i> Unlimited Data</li>
                            <li><i class="bi bi-check2"></i> Free Installation</li>
                            <li><i class="bi bi-check2"></i> Free WiFi Router</li>
                            <li><i class="bi bi-check2"></i> 24/7 Support</li>
                        </ul>
                        <a href="?page=order" class="btn-outline-accent package-btn">Select Plan</a>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="package-card featured fade-in">
                        <span class="package-badge">Popular</span>
                        <div class="package-name">Family Plus</div>
                        <div class="package-speed">30 <span class="package-speed-unit">Mbps</span></div>
                        <div class="package-price">
                            <span class="package-currency">KES </span>
                            <span class="package-amount">2,500</span>
                            <span class="package-period"> /month</span>
                        </div>
                        <ul class="package-features">
                            <li><i class="bi bi-check2"></i> Unlimited Data</li>
                            <li><i class="bi bi-check2"></i> Free Installation</li>
                            <li><i class="bi bi-check2"></i> Free WiFi Router</li>
                            <li><i class="bi bi-check2"></i> Priority Support</li>
                        </ul>
                        <a href="?page=order" class="btn-accent package-btn">Select Plan</a>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="package-card fade-in">
                        <div class="package-name">Business Pro</div>
                        <div class="package-speed">100 <span class="package-speed-unit">Mbps</span></div>
                        <div class="package-price">
                            <span class="package-currency">KES </span>
                            <span class="package-amount">5,000</span>
                            <span class="package-period"> /month</span>
                        </div>
                        <ul class="package-features">
                            <li><i class="bi bi-check2"></i> Unlimited Data</li>
                            <li><i class="bi bi-check2"></i> Static IP Address</li>
                            <li><i class="bi bi-check2"></i> Business Router</li>
                            <li><i class="bi bi-check2"></i> Dedicated Support</li>
                        </ul>
                        <a href="?page=order" class="btn-outline-accent package-btn">Select Plan</a>
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
                        <div class="package-card <?= $isPopular ? 'featured' : '' ?> fade-in">
                            <?php if ($isPopular): ?>
                            <span class="package-badge">Popular</span>
                            <?php endif; ?>
                            <div class="package-name"><?= htmlspecialchars($pkg['name']) ?></div>
                            <div class="package-speed"><?= htmlspecialchars($speedNum) ?> <span class="package-speed-unit"><?= htmlspecialchars($speedUnit) ?></span></div>
                            <div class="package-price">
                                <span class="package-currency">KES </span>
                                <span class="package-amount"><?= number_format($pkg['price']) ?></span>
                                <span class="package-period"> /month</span>
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
                                <li><i class="bi bi-check2"></i> <?= htmlspecialchars($feature) ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <a href="?page=order&package=<?= $pkg['id'] ?>" class="<?= $isPopular ? 'btn-accent' : 'btn-outline-accent' ?> package-btn">
                                Select Plan
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <?php
    $contactSettings = $contactSettings ?? [];
    ?>
    <section id="contact" class="section section-alt">
        <div class="container">
            <div class="row">
                <div class="col-lg-5 mb-4 mb-lg-0">
                    <div class="section-label fade-in">Contact</div>
                    <h2 class="section-title fade-in">Get in touch</h2>
                    <p class="section-desc fade-in">Questions or need help? Reach out through any of the channels below.</p>
                </div>
                <div class="col-lg-6 offset-lg-1">
                    <?php $phone = $contactSettings['contact_phone'] ?? $company['company_phone'] ?? ''; ?>
                    <?php if (!empty($phone)): ?>
                    <div class="contact-item fade-in">
                        <div class="contact-label">Phone</div>
                        <div class="contact-value">
                            <a href="tel:<?= htmlspecialchars($phone) ?>"><?= htmlspecialchars($phone) ?></a>
                            <?php if (!empty($contactSettings['contact_phone_2'])): ?>
                                <br><a href="tel:<?= htmlspecialchars($contactSettings['contact_phone_2']) ?>"><?= htmlspecialchars($contactSettings['contact_phone_2']) ?></a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php $email = $contactSettings['contact_email'] ?? $company['company_email'] ?? ''; ?>
                    <?php if (!empty($email)): ?>
                    <div class="contact-item fade-in">
                        <div class="contact-label">Email</div>
                        <div class="contact-value">
                            <a href="mailto:<?= htmlspecialchars($email) ?>"><?= htmlspecialchars($email) ?></a>
                            <?php if (!empty($contactSettings['contact_email_support'])): ?>
                                <br><a href="mailto:<?= htmlspecialchars($contactSettings['contact_email_support']) ?>"><?= htmlspecialchars($contactSettings['contact_email_support']) ?></a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php $address = $contactSettings['contact_address'] ?? $company['company_address'] ?? ''; ?>
                    <?php if (!empty($address)): ?>
                    <div class="contact-item fade-in">
                        <div class="contact-label">Address</div>
                        <div class="contact-value">
                            <?= htmlspecialchars($address) ?>
                            <?php if (!empty($contactSettings['contact_city'])): ?>
                                <br><?= htmlspecialchars($contactSettings['contact_city']) ?>, <?= htmlspecialchars($contactSettings['contact_country'] ?? 'Kenya') ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="contact-item fade-in">
                        <div class="contact-label">Working Hours</div>
                        <div class="contact-value">
                            <?= htmlspecialchars($contactSettings['working_days'] ?? 'Monday - Friday') ?>
                            <br><?= htmlspecialchars($contactSettings['working_hours'] ?? '8:00 AM - 5:00 PM') ?>
                            <br><span style="color: var(--text-lighter); font-size: 0.9rem;">Support: <?= htmlspecialchars($contactSettings['support_hours'] ?? '24/7') ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!empty($contactSettings['map_embed_url'])): ?>
            <div class="row mt-5">
                <div class="col-12">
                    <div class="map-container fade-in">
                        <iframe src="<?= htmlspecialchars($contactSettings['map_embed_url']) ?>"
                                width="100%" height="350" style="border:0;" allowfullscreen="" loading="lazy"></iframe>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="cta-section">
        <div class="container">
            <h2 class="cta-title fade-in">Ready to connect?</h2>
            <p class="cta-desc fade-in">Join us today and experience internet the way it should be — fast, reliable, and simple.</p>
            <div class="cta-actions fade-in">
                <a href="?page=order" class="btn-accent"><i class="bi bi-arrow-right"></i> Get Started</a>
                <?php
                $whatsappNum = $contactSettings['whatsapp_number'] ?? $contactSettings['contact_phone'] ?? $company['company_phone'] ?? '';
                $whatsappNum = preg_replace('/[^0-9+]/', '', $whatsappNum);
                if (!empty($whatsappNum)):
                ?>
                <a href="https://wa.me/<?= htmlspecialchars(ltrim($whatsappNum, '+')) ?>" target="_blank" class="btn-ghost">
                    <i class="bi bi-whatsapp"></i> Chat with us
                </a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <footer>
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="footer-brand"><?= htmlspecialchars($company['company_name'] ?? 'ISP Provider') ?></div>
                    <p class="footer-desc"><?= htmlspecialchars($landingSettings['hero_subtitle'] ?? 'Fast, reliable internet for your home and business.') ?></p>
                </div>
                <div class="col-lg-2 col-md-4">
                    <div class="footer-title">Links</div>
                    <ul class="footer-links">
                        <li><a href="#features">Features</a></li>
                        <li><a href="#packages">Packages</a></li>
                        <li><a href="#contact">Contact</a></li>
                    </ul>
                </div>
                <div class="col-lg-2 col-md-4">
                    <div class="footer-title">Account</div>
                    <ul class="footer-links">
                        <li><a href="?page=login">Log in</a></li>
                        <li><a href="?page=order">Order Now</a></li>
                    </ul>
                </div>
                <div class="col-lg-4 col-md-4">
                    <div class="footer-title">Contact</div>
                    <ul class="footer-links">
                        <?php if (!empty($phone)): ?>
                        <li><a href="tel:<?= htmlspecialchars($phone) ?>"><?= htmlspecialchars($phone) ?></a></li>
                        <?php endif; ?>
                        <?php if (!empty($email)): ?>
                        <li><a href="mailto:<?= htmlspecialchars($email) ?>"><?= htmlspecialchars($email) ?></a></li>
                        <?php endif; ?>
                        <?php if (!empty($address)): ?>
                        <li><?= htmlspecialchars($address) ?></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <span>&copy; <?= date('Y') ?> <?= htmlspecialchars($company['company_name'] ?? 'ISP Provider') ?>. All rights reserved.</span>
                <span>Powered by SuperLite CRM</span>
            </div>
        </div>
    </footer>

    <?php if (!empty($whatsappNum)): ?>
    <a href="https://wa.me/<?= htmlspecialchars(ltrim($whatsappNum, '+')) ?>" target="_blank" class="whatsapp-float" title="Chat on WhatsApp">
        <i class="bi bi-whatsapp"></i>
    </a>
    <?php endif; ?>

    <div class="modal fade" id="complaintModal" tabindex="-1" aria-labelledby="complaintModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border: 1px solid var(--border); border-radius: 10px;">
                <div class="modal-header" style="border-bottom: 1px solid var(--border); padding: 1.5rem;">
                    <h5 class="modal-title" id="complaintModalLabel" style="font-weight: 700; font-size: 1.1rem;">Report an Issue</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="padding: 1.5rem;">
                    <div id="complaintSuccess" class="alert alert-success d-none" style="border-radius: 8px;">Your complaint has been submitted successfully. We will contact you shortly.</div>
                    <div id="complaintError" class="alert alert-danger d-none" style="border-radius: 8px;"></div>
                    <form id="complaintForm">
                        <div class="mb-3">
                            <label class="form-label" style="font-size: 0.85rem; font-weight: 500; color: var(--text-light);">Full Name</label>
                            <input type="text" class="form-control" name="name" required style="border: 1px solid var(--border); border-radius: 6px; padding: 0.6rem 0.75rem; font-size: 0.9rem;">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" style="font-size: 0.85rem; font-weight: 500; color: var(--text-light);">Phone / Account Number</label>
                            <input type="text" class="form-control" name="phone" required style="border: 1px solid var(--border); border-radius: 6px; padding: 0.6rem 0.75rem; font-size: 0.9rem;">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" style="font-size: 0.85rem; font-weight: 500; color: var(--text-light);">Issue Description</label>
                            <textarea class="form-control" name="description" rows="4" required style="border: 1px solid var(--border); border-radius: 6px; padding: 0.6rem 0.75rem; font-size: 0.9rem;"></textarea>
                        </div>
                        <button type="submit" class="btn-accent w-100 justify-content-center" style="padding: 0.7rem;">Submit Report</button>
                    </form>
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
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1 });

        document.querySelectorAll('.fade-in').forEach(function(el) {
            observer.observe(el);
        });

        document.getElementById('complaintForm').addEventListener('submit', function(e) {
            e.preventDefault();
            var form = e.target;
            var formData = new FormData(form);

            fetch('?page=complaints&action=public_submit', {
                method: 'POST',
                body: formData
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    document.getElementById('complaintSuccess').classList.remove('d-none');
                    document.getElementById('complaintError').classList.add('d-none');
                    form.reset();
                    setTimeout(function() {
                        bootstrap.Modal.getInstance(document.getElementById('complaintModal')).hide();
                        document.getElementById('complaintSuccess').classList.add('d-none');
                    }, 3000);
                } else {
                    document.getElementById('complaintError').textContent = data.message || 'Failed to submit complaint';
                    document.getElementById('complaintError').classList.remove('d-none');
                }
            })
            .catch(function() {
                document.getElementById('complaintError').textContent = 'An error occurred. Please try again.';
                document.getElementById('complaintError').classList.remove('d-none');
            });
        });
    </script>
</body>
</html>
