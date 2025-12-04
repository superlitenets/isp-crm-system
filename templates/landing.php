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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: <?= htmlspecialchars($landingSettings['primary_color'] ?? '#0066FF') ?>;
            --primary-dark: #0052CC;
            --primary-light: #E6F0FF;
            --secondary: #FF6B35;
            --accent: #00D9A5;
            --dark: #0B1426;
            --dark-light: #152238;
            --gray-900: #1A1F36;
            --gray-800: #2D3748;
            --gray-600: #718096;
            --gray-400: #A0AEC0;
            --gray-200: #E2E8F0;
            --gray-100: #F7FAFC;
            --gradient-primary: linear-gradient(135deg, var(--primary) 0%, #6366F1 100%);
            --gradient-secondary: linear-gradient(135deg, var(--secondary) 0%, #FF8F6B 100%);
        }
        
        * {
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, sans-serif;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html {
            scroll-behavior: smooth;
        }
        
        body {
            background: var(--gray-100);
            color: var(--gray-900);
            overflow-x: hidden;
        }
        
        .navbar {
            background: transparent;
            padding: 1rem 0;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            transition: all 0.4s ease;
        }
        
        .navbar.scrolled {
            background: rgba(11, 20, 38, 0.98);
            backdrop-filter: blur(20px);
            box-shadow: 0 4px 30px rgba(0,0,0,0.3);
            padding: 0.75rem 0;
        }
        
        .navbar-brand {
            font-weight: 800;
            font-size: 1.75rem;
            color: white !important;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
        }
        
        .navbar-brand i {
            font-size: 2rem;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .nav-link {
            font-weight: 500;
            color: rgba(255,255,255,0.85) !important;
            padding: 0.5rem 1.25rem !important;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .nav-link::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 0;
            height: 2px;
            background: var(--primary);
            transition: all 0.3s ease;
            transform: translateX(-50%);
        }
        
        .nav-link:hover {
            color: white !important;
        }
        
        .nav-link:hover::after {
            width: 70%;
        }
        
        .btn-primary {
            background: var(--gradient-primary);
            border: none;
            padding: 0.875rem 2rem;
            font-weight: 600;
            border-radius: 50px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 102, 255, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(0, 102, 255, 0.5);
        }
        
        .btn-outline-light {
            border: 2px solid rgba(255,255,255,0.5);
            padding: 0.875rem 2rem;
            font-weight: 600;
            border-radius: 50px;
            backdrop-filter: blur(10px);
        }
        
        .btn-outline-light:hover {
            background: white;
            border-color: white;
            color: var(--dark);
        }
        
        .hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
            background: var(--dark);
        }
        
        .hero-bg {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                linear-gradient(135deg, rgba(11, 20, 38, 0.95) 0%, rgba(21, 34, 56, 0.9) 50%, rgba(0, 102, 255, 0.3) 100%),
                url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.03)" stroke-width="0.5"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
            background-size: cover, 100px 100px;
        }
        
        .hero-shapes {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            overflow: hidden;
        }
        
        .hero-shape {
            position: absolute;
            border-radius: 50%;
            opacity: 0.1;
            animation: float 20s ease-in-out infinite;
        }
        
        .hero-shape-1 {
            width: 600px;
            height: 600px;
            background: var(--primary);
            top: -200px;
            right: -200px;
            animation-delay: 0s;
        }
        
        .hero-shape-2 {
            width: 400px;
            height: 400px;
            background: var(--secondary);
            bottom: -100px;
            left: -100px;
            animation-delay: -5s;
        }
        
        .hero-shape-3 {
            width: 300px;
            height: 300px;
            background: var(--accent);
            top: 50%;
            left: 50%;
            animation-delay: -10s;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            25% { transform: translateY(-30px) rotate(5deg); }
            50% { transform: translateY(-20px) rotate(-5deg); }
            75% { transform: translateY(-40px) rotate(3deg); }
        }
        
        .hero-content {
            position: relative;
            z-index: 2;
            padding-top: 100px;
        }
        
        .hero-subtitle-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(0, 217, 165, 0.15);
            color: var(--accent);
            padding: 0.5rem 1.25rem;
            border-radius: 50px;
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(0, 217, 165, 0.3);
            animation: pulse-glow 2s ease-in-out infinite;
        }
        
        @keyframes pulse-glow {
            0%, 100% { box-shadow: 0 0 5px rgba(0, 217, 165, 0.3); }
            50% { box-shadow: 0 0 20px rgba(0, 217, 165, 0.5); }
        }
        
        .hero-title {
            font-size: 4.5rem;
            font-weight: 800;
            color: white;
            line-height: 1.1;
            margin-bottom: 1.5rem;
        }
        
        .hero-title .gradient-text {
            background: linear-gradient(135deg, var(--primary), var(--accent), var(--secondary));
            background-size: 200% 200%;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: gradient-shift 5s ease infinite;
        }
        
        @keyframes gradient-shift {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }
        
        .hero-description {
            font-size: 1.25rem;
            color: var(--gray-400);
            line-height: 1.8;
            margin-bottom: 2.5rem;
            max-width: 540px;
        }
        
        .hero-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 3rem;
        }
        
        .hero-features {
            display: flex;
            gap: 2.5rem;
            flex-wrap: wrap;
        }
        
        .hero-feature {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: rgba(255,255,255,0.7);
        }
        
        .hero-feature i {
            color: var(--accent);
            font-size: 1.25rem;
        }
        
        .hero-image-wrapper {
            position: relative;
            z-index: 2;
        }
        
        .speed-meter {
            background: rgba(255,255,255,0.08);
            backdrop-filter: blur(30px);
            border-radius: 30px;
            padding: 3rem;
            border: 1px solid rgba(255,255,255,0.1);
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .speed-meter::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: conic-gradient(from 0deg, transparent, rgba(0, 102, 255, 0.1), transparent);
            animation: rotate 10s linear infinite;
        }
        
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .speed-circle {
            width: 220px;
            height: 220px;
            border-radius: 50%;
            background: conic-gradient(var(--primary) 0deg, var(--accent) 120deg, var(--secondary) 240deg, var(--primary) 360deg);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            position: relative;
            animation: spin-slow 20s linear infinite;
        }
        
        @keyframes spin-slow {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .speed-circle::before {
            content: '';
            position: absolute;
            width: 180px;
            height: 180px;
            background: var(--dark);
            border-radius: 50%;
        }
        
        .speed-value {
            position: relative;
            z-index: 1;
            color: white;
            animation: spin-reverse 20s linear infinite;
        }
        
        @keyframes spin-reverse {
            from { transform: rotate(0deg); }
            to { transform: rotate(-360deg); }
        }
        
        .speed-value strong {
            display: block;
            font-size: 3.5rem;
            font-weight: 800;
        }
        
        .speed-value span {
            font-size: 1rem;
            color: var(--gray-400);
        }
        
        .speed-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            position: relative;
            z-index: 1;
        }
        
        .speed-stat {
            text-align: center;
        }
        
        .hero-image-container {
            position: relative;
        }
        
        .hero-main-image {
            width: 100%;
            height: auto;
            border-radius: 30px;
            box-shadow: 0 30px 60px rgba(0,0,0,0.3);
        }
        
        .hero-float-card {
            position: absolute;
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            animation: float-bounce 3s ease-in-out infinite;
        }
        
        .hero-float-card i {
            font-size: 2rem;
            color: var(--primary);
        }
        
        .hero-float-card strong {
            display: block;
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--dark);
        }
        
        .hero-float-card span {
            font-size: 0.75rem;
            color: var(--gray-600);
        }
        
        .hero-float-1 {
            top: 20px;
            right: -20px;
            animation-delay: 0s;
        }
        
        .hero-float-2 {
            bottom: 100px;
            left: -40px;
            animation-delay: 1s;
        }
        
        .hero-float-3 {
            bottom: 20px;
            right: 40px;
            animation-delay: 2s;
        }
        
        @keyframes float-bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        
        .service-card-img {
            width: 100%;
            height: 180px;
            object-fit: cover;
            border-radius: 16px;
            margin-bottom: 1.5rem;
        }
        
        .testimonials-section {
            background: linear-gradient(180deg, var(--gray-100) 0%, white 100%);
        }
        
        .testimonial-card {
            background: white;
            border-radius: 24px;
            padding: 2.5rem;
            height: 100%;
            border: 1px solid var(--gray-200);
            transition: all 0.4s ease;
            position: relative;
        }
        
        .testimonial-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 30px 60px rgba(0,0,0,0.1);
        }
        
        .testimonial-quote {
            font-size: 3rem;
            color: var(--primary);
            opacity: 0.3;
            position: absolute;
            top: 20px;
            right: 30px;
        }
        
        .testimonial-text {
            color: var(--gray-700);
            line-height: 1.8;
            margin-bottom: 1.5rem;
            font-style: italic;
        }
        
        .testimonial-author {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .testimonial-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary-light);
        }
        
        .testimonial-info h5 {
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 0.25rem;
        }
        
        .testimonial-info span {
            color: var(--gray-600);
            font-size: 0.875rem;
        }
        
        .testimonial-rating {
            color: #FFB800;
            margin-top: 0.25rem;
        }
        
        .why-choose-section {
            background: white;
            overflow: hidden;
        }
        
        .why-choose-image {
            position: relative;
        }
        
        .why-choose-image img {
            width: 100%;
            border-radius: 24px;
            box-shadow: 0 30px 60px rgba(0,0,0,0.15);
        }
        
        .why-choose-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .why-choose-list li {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding: 1.5rem;
            background: var(--gray-100);
            border-radius: 16px;
            transition: all 0.3s ease;
        }
        
        .why-choose-list li:hover {
            background: var(--primary-light);
        }
        
        .why-choose-list .icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: var(--gradient-primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }
        
        .why-choose-list h5 {
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 0.25rem;
        }
        
        .why-choose-list p {
            color: var(--gray-600);
            margin: 0;
            font-size: 0.9rem;
        }
        
        .speed-stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
        }
        
        .speed-stat-label {
            font-size: 0.75rem;
            color: var(--gray-400);
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .section {
            padding: 7rem 0;
        }
        
        .section-header {
            text-align: center;
            margin-bottom: 4rem;
        }
        
        .section-tag {
            display: inline-block;
            background: var(--primary-light);
            color: var(--primary);
            padding: 0.5rem 1.5rem;
            border-radius: 50px;
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 1rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .section-title {
            font-size: 3rem;
            font-weight: 800;
            color: var(--gray-900);
            margin-bottom: 1rem;
        }
        
        .section-subtitle {
            font-size: 1.125rem;
            color: var(--gray-600);
            max-width: 650px;
            margin: 0 auto;
            line-height: 1.8;
        }
        
        .about-section {
            background: white;
            overflow: hidden;
        }
        
        .about-image {
            position: relative;
        }
        
        .about-image img {
            border-radius: 20px;
            box-shadow: 0 30px 60px rgba(0,0,0,0.15);
        }
        
        .about-badge {
            position: absolute;
            bottom: -20px;
            right: -20px;
            background: var(--gradient-primary);
            color: white;
            padding: 1.5rem;
            border-radius: 20px;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0, 102, 255, 0.4);
        }
        
        .about-badge strong {
            display: block;
            font-size: 2.5rem;
            font-weight: 800;
        }
        
        .about-content h2 {
            font-size: 2.75rem;
            font-weight: 800;
            color: var(--gray-900);
            margin-bottom: 1.5rem;
        }
        
        .about-content p {
            color: var(--gray-600);
            line-height: 1.8;
            margin-bottom: 2rem;
        }
        
        .about-features {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .about-feature {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .about-feature-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: var(--primary-light);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }
        
        .about-feature-text {
            font-weight: 600;
            color: var(--gray-800);
        }
        
        .services-section {
            background: var(--gray-100);
        }
        
        .service-card {
            background: white;
            border-radius: 24px;
            padding: 2.5rem;
            height: 100%;
            border: 1px solid var(--gray-200);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
        }
        
        .service-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-primary);
            transform: scaleX(0);
            transition: transform 0.4s ease;
        }
        
        .service-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 30px 60px rgba(0,0,0,0.1);
        }
        
        .service-card:hover::before {
            transform: scaleX(1);
        }
        
        .service-icon {
            width: 80px;
            height: 80px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
        }
        
        .service-card:hover .service-icon {
            transform: scale(1.1) rotate(5deg);
        }
        
        .service-icon.blue { background: linear-gradient(135deg, #E0EAFF, #C7D7FE); color: var(--primary); }
        .service-icon.green { background: linear-gradient(135deg, #D1FAE5, #A7F3D0); color: #059669; }
        .service-icon.orange { background: linear-gradient(135deg, #FEE2D5, #FED7C3); color: var(--secondary); }
        .service-icon.purple { background: linear-gradient(135deg, #EDE9FE, #DDD6FE); color: #7C3AED; }
        
        .service-title {
            font-size: 1.375rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 0.75rem;
        }
        
        .service-desc {
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
            transition: all 0.4s ease;
            overflow: hidden;
        }
        
        .package-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: var(--gradient-primary);
            opacity: 0;
            transition: opacity 0.4s ease;
            z-index: 0;
        }
        
        .package-card > * {
            position: relative;
            z-index: 1;
        }
        
        .package-card:hover {
            border-color: var(--primary);
            transform: translateY(-10px);
            box-shadow: 0 30px 60px rgba(0, 102, 255, 0.2);
        }
        
        .package-card.popular {
            border-color: var(--primary);
            background: linear-gradient(180deg, #fff 0%, var(--primary-light) 100%);
        }
        
        .package-card.popular::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: var(--gradient-primary);
        }
        
        .package-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            background: var(--gradient-secondary);
            color: white;
            padding: 0.375rem 1rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
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
            margin-bottom: 0.5rem;
        }
        
        .package-speed-value {
            font-size: 3.5rem;
            font-weight: 800;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
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
            border-bottom: 1px dashed var(--gray-200);
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
        }
        
        .package-features li i {
            color: var(--accent);
            font-size: 1.25rem;
        }
        
        .package-btn {
            width: 100%;
            padding: 1rem;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .package-btn.primary {
            background: var(--gradient-primary);
            border: none;
            color: white;
        }
        
        .package-btn.outline {
            background: transparent;
            border: 2px solid var(--primary);
            color: var(--primary);
        }
        
        .package-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(0, 102, 255, 0.3);
        }
        
        .stats-section {
            background: var(--dark);
            color: white;
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
            background: 
                radial-gradient(circle at 20% 80%, rgba(0, 102, 255, 0.2) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(0, 217, 165, 0.15) 0%, transparent 50%);
        }
        
        .stat-card {
            text-align: center;
            padding: 2rem;
            position: relative;
        }
        
        .stat-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .stat-value {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            color: white;
        }
        
        .stat-label {
            color: var(--gray-400);
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.875rem;
        }
        
        .contact-section {
            background: white;
        }
        
        .contact-card {
            background: var(--gray-100);
            border-radius: 20px;
            padding: 2.5rem;
            text-align: center;
            height: 100%;
            transition: all 0.4s ease;
            border: 2px solid transparent;
        }
        
        .contact-card:hover {
            background: white;
            border-color: var(--primary);
            box-shadow: 0 20px 50px rgba(0, 102, 255, 0.1);
            transform: translateY(-5px);
        }
        
        .contact-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--gradient-primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            margin: 0 auto 1.5rem;
            transition: transform 0.3s ease;
        }
        
        .contact-card:hover .contact-icon {
            transform: scale(1.1) rotate(10deg);
        }
        
        .contact-label {
            font-size: 0.875rem;
            color: var(--gray-600);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 0.75rem;
            font-weight: 600;
        }
        
        .contact-value {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--gray-900);
            line-height: 1.6;
        }
        
        .contact-value a {
            color: var(--gray-900);
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .contact-value a:hover {
            color: var(--primary);
        }
        
        .map-container {
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
            border: 4px solid white;
        }
        
        .cta-section {
            background: var(--gradient-primary);
            padding: 6rem 0;
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
            background: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Ccircle cx='50' cy='50' r='1' fill='rgba(255,255,255,0.1)'/%3E%3C/svg%3E");
        }
        
        .cta-title {
            font-size: 3rem;
            font-weight: 800;
            color: white;
            margin-bottom: 1rem;
        }
        
        .cta-subtitle {
            font-size: 1.25rem;
            color: rgba(255,255,255,0.9);
            margin-bottom: 2.5rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .cta-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .social-links {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2.5rem;
        }
        
        .social-link {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: rgba(255,255,255,0.15);
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
            transform: translateY(-5px);
        }
        
        footer {
            background: var(--dark);
            color: white;
            padding: 5rem 0 2rem;
        }
        
        .footer-brand {
            font-size: 1.75rem;
            font-weight: 800;
            color: white;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }
        
        .footer-brand i {
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .footer-desc {
            color: var(--gray-400);
            margin-bottom: 1.5rem;
            max-width: 350px;
            line-height: 1.8;
        }
        
        .footer-title {
            font-size: 1.125rem;
            font-weight: 700;
            color: white;
            margin-bottom: 1.5rem;
            position: relative;
            padding-bottom: 0.75rem;
        }
        
        .footer-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 40px;
            height: 3px;
            background: var(--gradient-primary);
            border-radius: 2px;
        }
        
        .footer-links {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .footer-links li {
            margin-bottom: 0.875rem;
        }
        
        .footer-links a {
            color: var(--gray-400);
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .footer-links a:hover {
            color: white;
            padding-left: 5px;
        }
        
        .footer-contact-item {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            color: var(--gray-400);
        }
        
        .footer-contact-item i {
            color: var(--primary);
            font-size: 1.25rem;
            margin-top: 0.25rem;
        }
        
        .footer-bottom {
            border-top: 1px solid rgba(255,255,255,0.1);
            margin-top: 4rem;
            padding-top: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .footer-bottom-text {
            color: var(--gray-600);
        }
        
        .footer-bottom-links {
            display: flex;
            gap: 2rem;
        }
        
        .footer-bottom-links a {
            color: var(--gray-400);
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .footer-bottom-links a:hover {
            color: white;
        }
        
        .whatsapp-float {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 65px;
            height: 65px;
            background: linear-gradient(135deg, #25D366, #128C7E);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            text-decoration: none;
            box-shadow: 0 6px 30px rgba(37, 211, 102, 0.5);
            z-index: 999;
            transition: all 0.3s ease;
            animation: whatsapp-pulse 2s ease-in-out infinite;
        }
        
        @keyframes whatsapp-pulse {
            0%, 100% { box-shadow: 0 6px 30px rgba(37, 211, 102, 0.5); }
            50% { box-shadow: 0 6px 50px rgba(37, 211, 102, 0.8); }
        }
        
        .whatsapp-float:hover {
            transform: scale(1.1);
            color: white;
        }
        
        .animate-on-scroll {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.6s ease;
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
        }
        
        @media (max-width: 767px) {
            .hero {
                text-align: center;
                padding: 120px 0 80px;
            }
            
            .hero-title { font-size: 2.25rem; }
            .hero-description { margin: 0 auto 2rem; }
            .hero-buttons { justify-content: center; }
            .hero-features { justify-content: center; }
            
            .about-features { grid-template-columns: 1fr; }
            .package-speed-value { font-size: 2.5rem; }
            .footer-bottom { text-align: center; justify-content: center; }
            .footer-bottom-links { justify-content: center; }
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
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item"><a class="nav-link" href="#home">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="#about">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="#services">Services</a></li>
                    <li class="nav-item"><a class="nav-link" href="#packages">Packages</a></li>
                    <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
                    <li class="nav-item ms-lg-3">
                        <a class="btn btn-primary" href="?page=order">Get Started</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <section id="home" class="hero">
        <div class="hero-bg"></div>
        <div class="hero-shapes">
            <div class="hero-shape hero-shape-1"></div>
            <div class="hero-shape hero-shape-2"></div>
            <div class="hero-shape hero-shape-3"></div>
        </div>
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="hero-content animate__animated animate__fadeInUp">
                        <div class="hero-subtitle-tag">
                            <i class="bi bi-lightning-charge-fill"></i>
                            <?= htmlspecialchars($landingSettings['hero_badge'] ?? 'Ultra-Fast Fiber Internet') ?>
                        </div>
                        <h1 class="hero-title">
                            <?= htmlspecialchars($landingSettings['hero_title'] ?? 'Experience Lightning-Fast') ?>
                            <span class="gradient-text">Internet Speed</span>
                        </h1>
                        <p class="hero-description">
                            <?= htmlspecialchars($landingSettings['hero_subtitle'] ?? 'Unlock the power of high-speed connectivity. Stream, game, work, and connect with the fastest fiber internet in your area.') ?>
                        </p>
                        <div class="hero-buttons">
                            <a href="#packages" class="btn btn-primary btn-lg">
                                <i class="bi bi-arrow-right-circle me-2"></i>View Packages
                            </a>
                            <a href="?page=order" class="btn btn-outline-light btn-lg">
                                <i class="bi bi-telephone me-2"></i>Order Now
                            </a>
                        </div>
                        <div class="hero-features">
                            <div class="hero-feature">
                                <i class="bi bi-check-circle-fill"></i>
                                <span>No Hidden Fees</span>
                            </div>
                            <div class="hero-feature">
                                <i class="bi bi-check-circle-fill"></i>
                                <span>Free Installation</span>
                            </div>
                            <div class="hero-feature">
                                <i class="bi bi-check-circle-fill"></i>
                                <span>24/7 Support</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 d-none d-lg-block">
                    <div class="hero-image-wrapper animate__animated animate__fadeInRight animate__delay-1s">
                        <div class="hero-image-container">
                            <img src="https://images.unsplash.com/photo-1558494949-ef010cbdcc31?w=600&h=500&fit=crop" alt="High Speed Internet" class="hero-main-image">
                            <div class="hero-float-card hero-float-1">
                                <i class="bi bi-lightning-charge-fill"></i>
                                <div>
                                    <strong>1 Gbps</strong>
                                    <span>Download Speed</span>
                                </div>
                            </div>
                            <div class="hero-float-card hero-float-2">
                                <i class="bi bi-shield-check"></i>
                                <div>
                                    <strong>99.9%</strong>
                                    <span>Uptime</span>
                                </div>
                            </div>
                            <div class="hero-float-card hero-float-3">
                                <i class="bi bi-headset"></i>
                                <div>
                                    <strong>24/7</strong>
                                    <span>Support</span>
                                </div>
                            </div>
                        </div>
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
                        <a href="#contact" class="btn btn-primary">
                            <i class="bi bi-arrow-right me-2"></i>Learn More
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
                <p class="section-subtitle">We provide comprehensive internet solutions tailored to meet your needs, whether you're streaming, gaming, or running a business.</p>
            </div>
            <div class="row g-4">
                <div class="col-lg-3 col-md-6">
                    <div class="service-card animate-on-scroll">
                        <img src="https://images.unsplash.com/photo-1544197150-b99a580bb7a8?w=400&h=250&fit=crop" alt="Fiber Internet" class="service-card-img">
                        <div class="service-icon blue"><i class="bi bi-router"></i></div>
                        <h4 class="service-title">Fiber Internet</h4>
                        <p class="service-desc">Experience blazing fast fiber optic internet with speeds up to 1Gbps for seamless streaming and gaming.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="service-card animate-on-scroll">
                        <img src="https://images.unsplash.com/photo-1497366216548-37526070297c?w=400&h=250&fit=crop" alt="Business Solutions" class="service-card-img">
                        <div class="service-icon green"><i class="bi bi-building"></i></div>
                        <h4 class="service-title">Business Solutions</h4>
                        <p class="service-desc">Dedicated business internet with guaranteed uptime, static IPs, and priority support for your company.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="service-card animate-on-scroll">
                        <img src="https://images.unsplash.com/photo-1516044734145-07ca8eef8731?w=400&h=250&fit=crop" alt="Wireless Networks" class="service-card-img">
                        <div class="service-icon orange"><i class="bi bi-wifi"></i></div>
                        <h4 class="service-title">Wireless Networks</h4>
                        <p class="service-desc">High-speed wireless solutions for areas where fiber isn't available, with reliable coverage.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="service-card animate-on-scroll">
                        <img src="https://images.unsplash.com/photo-1553775927-a071d5a6a39a?w=400&h=250&fit=crop" alt="24/7 Support" class="service-card-img">
                        <div class="service-icon purple"><i class="bi bi-headset"></i></div>
                        <h4 class="service-title">24/7 Support</h4>
                        <p class="service-desc">Round-the-clock customer support via phone, email, and WhatsApp to resolve any issues quickly.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <section class="section why-choose-section">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-6">
                    <div class="why-choose-image animate-on-scroll">
                        <img src="https://images.unsplash.com/photo-1551434678-e076c223a692?w=600&h=500&fit=crop" alt="Why Choose Us">
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="animate-on-scroll">
                        <div class="section-tag">Why Choose Us</div>
                        <h2 class="section-title mb-4">The Best ISP in Your Area</h2>
                        <ul class="why-choose-list">
                            <li>
                                <div class="icon"><i class="bi bi-lightning-charge"></i></div>
                                <div>
                                    <h5>Fastest Speeds</h5>
                                    <p>Get speeds up to 1Gbps with our cutting-edge fiber network infrastructure.</p>
                                </div>
                            </li>
                            <li>
                                <div class="icon"><i class="bi bi-graph-up-arrow"></i></div>
                                <div>
                                    <h5>99.9% Uptime</h5>
                                    <p>Our reliable network ensures you stay connected when it matters most.</p>
                                </div>
                            </li>
                            <li>
                                <div class="icon"><i class="bi bi-currency-dollar"></i></div>
                                <div>
                                    <h5>Affordable Plans</h5>
                                    <p>Competitive pricing with no hidden fees or surprise charges.</p>
                                </div>
                            </li>
                            <li>
                                <div class="icon"><i class="bi bi-tools"></i></div>
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
                                <li><i class="bi bi-check-circle-fill"></i> Unlimited Data</li>
                                <li><i class="bi bi-check-circle-fill"></i> Free Installation</li>
                                <li><i class="bi bi-check-circle-fill"></i> Free WiFi Router</li>
                                <li><i class="bi bi-check-circle-fill"></i> 24/7 Support</li>
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
                <p class="section-subtitle">Don't just take our word for it. Here's what our satisfied customers have to say about our services.</p>
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

    <section class="section stats-section">
        <div class="container position-relative">
            <div class="row g-4">
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card animate-on-scroll">
                        <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
                        <div class="stat-value" data-count="5000">5000+</div>
                        <div class="stat-label">Happy Customers</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card animate-on-scroll">
                        <div class="stat-icon"><i class="bi bi-building"></i></div>
                        <div class="stat-value" data-count="500">500+</div>
                        <div class="stat-label">Businesses Served</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card animate-on-scroll">
                        <div class="stat-icon"><i class="bi bi-router"></i></div>
                        <div class="stat-value" data-count="1000">1000+</div>
                        <div class="stat-label">Active Connections</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card animate-on-scroll">
                        <div class="stat-icon"><i class="bi bi-award"></i></div>
                        <div class="stat-value">99.9%</div>
                        <div class="stat-label">Network Uptime</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="contact" class="section contact-section">
        <div class="container">
            <div class="section-header animate-on-scroll">
                <div class="section-tag">Get In Touch</div>
                <h2 class="section-title">Contact Us</h2>
                <p class="section-subtitle">Have questions? We're here to help. Reach out to us through any of these channels.</p>
            </div>
            <div class="row g-4 justify-content-center mb-5">
                <?php $phone = $contactSettings['contact_phone'] ?? $company['company_phone'] ?? ''; ?>
                <?php if (!empty($phone)): ?>
                <div class="col-lg-3 col-md-6">
                    <div class="contact-card animate-on-scroll">
                        <div class="contact-icon">
                            <i class="bi bi-telephone"></i>
                        </div>
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
                        <div class="contact-icon">
                            <i class="bi bi-envelope"></i>
                        </div>
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
                    <div class="contact-card animate-on-scroll">
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
                <a href="?page=order" class="btn btn-light btn-lg">
                    <i class="bi bi-rocket-takeoff me-2"></i>Get Started Today
                </a>
                <?php if (!empty($whatsappNum)): ?>
                <a href="https://wa.me/<?= $whatsappNum ?>?text=Hi,%20I'm%20interested%20in%20your%20internet%20services" class="btn btn-outline-light btn-lg" target="_blank">
                    <i class="bi bi-whatsapp me-2"></i>Contact Sales
                </a>
                <?php else: ?>
                <a href="#contact" class="btn btn-outline-light btn-lg">
                    <i class="bi bi-telephone me-2"></i>Contact Sales
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
                        <i class="bi bi-broadcast-pin"></i>
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
                        <li><a href="?page=login">Customer Portal</a></li>
                        <li><a href="#contact">Help Center</a></li>
                        <li><a href="#">Report Issue</a></li>
                        <li><a href="#">FAQs</a></li>
                    </ul>
                </div>
                <div class="col-lg-2 col-md-4 col-6 mb-4">
                    <h6 class="footer-title">Contact</h6>
                    <?php if (!empty($phone)): ?>
                    <div class="footer-contact-item">
                        <i class="bi bi-telephone"></i>
                        <div>
                            <a href="tel:<?= htmlspecialchars($phone) ?>" style="color: var(--gray-400); text-decoration: none;"><?= htmlspecialchars($phone) ?></a>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($email)): ?>
                    <div class="footer-contact-item">
                        <i class="bi bi-envelope"></i>
                        <div>
                            <a href="mailto:<?= htmlspecialchars($email) ?>" style="color: var(--gray-400); text-decoration: none;"><?= htmlspecialchars($email) ?></a>
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
        
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                }
            });
        }, observerOptions);
        
        document.querySelectorAll('.animate-on-scroll').forEach(el => {
            observer.observe(el);
        });
    </script>
</body>
</html>
