<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/RadiusBilling.php';

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');

$db = Database::getConnection();

function getClientIP(): string {
    $headers = [
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'HTTP_CLIENT_IP',
        'REMOTE_ADDR'
    ];
    
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ips = explode(',', $_SERVER[$header]);
            $ip = trim($ips[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

$clientIP = getClientIP();
$subscription = null;
$lookupMode = false;
$message = '';
$messageType = 'info';

$radiusBilling = new \App\RadiusBilling($db);
$ispName = $radiusBilling->getSetting('isp_name') ?: 'Your ISP';
$ispPhone = $radiusBilling->getSetting('isp_contact_phone') ?: '';
$ispPhoneFormatted = $ispPhone ? preg_replace('/[^0-9]/', '', $ispPhone) : '';
$ispWhatsApp = $ispPhoneFormatted ? '254' . substr($ispPhoneFormatted, -9) : '';
$ispLogo = $radiusBilling->getSetting('isp_logo') ?: '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'lookup') {
    $lookupValue = trim($_POST['lookup_value'] ?? '');
    if (!empty($lookupValue)) {
        $phone = preg_replace('/[^0-9]/', '', $lookupValue);
        if (substr($phone, 0, 1) === '0') {
            $phone = '254' . substr($phone, 1);
        }
        
        $stmt = $db->prepare("
            SELECT s.*, c.name as customer_name, c.phone as customer_phone, c.email as customer_email,
                   p.name as package_name, p.price as package_price, p.validity_days,
                   p.download_speed, p.upload_speed
            FROM radius_subscriptions s
            LEFT JOIN customers c ON s.customer_id = c.id
            LEFT JOIN radius_packages p ON s.package_id = p.id
            WHERE s.username = ? 
               OR REPLACE(REPLACE(c.phone, '+', ''), ' ', '') = ?
               OR REPLACE(REPLACE(c.phone, '+', ''), ' ', '') LIKE ?
            LIMIT 1
        ");
        $stmt->execute([$lookupValue, $phone, '%' . substr($phone, -9)]);
        $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$subscription) {
            $message = "No subscription found for '{$lookupValue}'. Please check your username or phone number.";
            $messageType = 'warning';
            $lookupMode = true;
        }
    }
}

if (!$subscription && !$lookupMode) {
    $stmt = $db->prepare("
        SELECT s.*, c.name as customer_name, c.phone as customer_phone, c.email as customer_email,
               p.name as package_name, p.price as package_price, p.validity_days,
               p.download_speed, p.upload_speed
        FROM radius_subscriptions s
        LEFT JOIN customers c ON s.customer_id = c.id
        LEFT JOIN radius_packages p ON s.package_id = p.id
        LEFT JOIN radius_sessions rs ON rs.subscription_id = s.id
        WHERE rs.framed_ip_address = ? OR s.static_ip = ?
        ORDER BY rs.started_at DESC
        LIMIT 1
    ");
    $stmt->execute([$clientIP, $clientIP]);
    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
}

$daysRemaining = 0;
$daysSuspended = 0;
if ($subscription) {
    if ($subscription['days_remaining_at_suspension']) {
        $daysRemaining = (int)$subscription['days_remaining_at_suspension'];
    }
    if ($subscription['suspended_at']) {
        $daysSuspended = ceil((time() - strtotime($subscription['suspended_at'])) / 86400);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Account Suspended - <?= htmlspecialchars($ispName) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #fd7e14;
            --primary-dark: #e55a00;
        }
        
        * { box-sizing: border-box; }
        
        body {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            padding: 20px;
            margin: 0;
        }
        
        .page-container {
            width: 100%;
            max-width: 440px;
            animation: slideUp 0.5s ease-out;
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .suspended-card {
            background: #fff;
            border-radius: 24px;
            box-shadow: 0 25px 80px rgba(0,0,0,0.4);
            overflow: hidden;
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 35px 25px;
            text-align: center;
            position: relative;
        }
        
        .card-header::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            opacity: 0.5;
        }
        
        .header-content {
            position: relative;
            z-index: 1;
        }
        
        .status-icon {
            width: 80px;
            height: 80px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 40px;
            animation: pulse 2s infinite;
        }
        
        .header-title {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .header-subtitle {
            opacity: 0.9;
            font-size: 0.95rem;
            margin-bottom: 15px;
        }
        
        .package-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.2);
            padding: 8px 18px;
            border-radius: 25px;
            font-size: 0.9rem;
            backdrop-filter: blur(10px);
        }
        
        .card-body {
            padding: 25px;
        }
        
        .info-grid {
            display: grid;
            gap: 0;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            color: #6c757d;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .info-value {
            font-weight: 600;
            color: #1a1a2e;
            text-align: right;
        }
        
        .days-card {
            border-radius: 16px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
        }
        
        .days-remaining-card {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
        }
        
        .days-suspended-card {
            background: linear-gradient(135deg, #ffc107, #fd7e14);
            color: white;
        }
        
        .days-value {
            font-size: 2.5rem;
            font-weight: 700;
        }
        
        .days-label {
            font-size: 0.85rem;
            opacity: 0.9;
        }
        
        .suspended-notice {
            background: linear-gradient(135deg, #fff3cd, #ffeeba);
            border-left: 4px solid #fd7e14;
            padding: 20px;
            border-radius: 0 14px 14px 0;
            margin: 20px 0;
        }
        
        .suspended-notice h5 {
            color: #856404;
            margin-bottom: 10px;
        }
        
        .suspended-notice p {
            color: #664d03;
            margin: 0;
            font-size: 0.9rem;
        }
        
        .contact-section {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #f0f0f0;
            margin-top: 20px;
        }
        
        .contact-btns {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-top: 15px;
        }
        
        .contact-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .contact-btn-call {
            background: #e9ecef;
            color: #495057;
        }
        
        .contact-btn-call:hover {
            background: #dee2e6;
            color: #212529;
        }
        
        .contact-btn-whatsapp {
            background: #25d366;
            color: white;
        }
        
        .contact-btn-whatsapp:hover {
            background: #1da851;
            color: white;
        }
        
        .not-found-card {
            text-align: center;
            padding: 50px 30px;
        }
        
        .not-found-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #ffc107, #ff9800);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            font-size: 50px;
            color: white;
        }
        
        .search-form {
            margin-top: 30px;
        }
        
        .search-input-group {
            display: flex;
            gap: 10px;
        }
        
        .search-input {
            flex: 1;
            padding: 14px 18px;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            font-size: 1rem;
        }
        
        .search-btn {
            padding: 14px 24px;
            background: #1a1a2e;
            color: white;
            border: none;
            border-radius: 12px;
            cursor: pointer;
        }
        
        .isp-footer {
            text-align: center;
            margin-top: 25px;
            color: rgba(255,255,255,0.6);
            font-size: 0.85rem;
        }
        
        .alert {
            border-radius: 12px;
            border: none;
            padding: 15px 20px;
        }
        
        @media (max-width: 480px) {
            body { padding: 15px; }
            .card-header { padding: 30px 20px; }
            .card-body { padding: 20px; }
            .header-title { font-size: 1.5rem; }
            .days-value { font-size: 2rem; }
            .contact-btns { flex-direction: column; }
            .contact-btn { justify-content: center; }
        }
    </style>
</head>
<body>
    <div class="page-container">
        <div class="suspended-card">
            <?php if ($subscription): ?>
            <div class="card-header">
                <div class="header-content">
                    <div class="status-icon">
                        <i class="bi bi-pause-circle-fill"></i>
                    </div>
                    
                    <h1 class="header-title">Account Suspended</h1>
                    <p class="header-subtitle">Your internet service has been temporarily suspended</p>
                    
                    <div class="package-pill">
                        <i class="bi bi-speedometer2"></i>
                        <?= htmlspecialchars($subscription['package_name'] ?? 'Package') ?>
                        <?php if ($subscription['download_speed']): ?>
                        <span style="opacity:0.7">|</span>
                        <?= htmlspecialchars($subscription['download_speed']) ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="card-body">
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label"><i class="bi bi-person"></i> Account</span>
                        <span class="info-value"><?= htmlspecialchars($subscription['customer_name'] ?? 'N/A') ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label"><i class="bi bi-key"></i> Username</span>
                        <span class="info-value"><?= htmlspecialchars($subscription['username']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label"><i class="bi bi-shield-x"></i> Status</span>
                        <span class="info-value" style="color: var(--primary-color);">
                            <i class="bi bi-pause-circle-fill me-1"></i> Suspended
                        </span>
                    </div>
                    <?php if ($subscription['suspended_at']): ?>
                    <div class="info-item">
                        <span class="info-label"><i class="bi bi-calendar-event"></i> Suspended On</span>
                        <span class="info-value"><?= date('M j, Y', strtotime($subscription['suspended_at'])) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="row">
                    <?php if ($daysRemaining > 0): ?>
                    <div class="col-6">
                        <div class="days-card days-remaining-card">
                            <div class="days-value"><?= $daysRemaining ?></div>
                            <div class="days-label">Days Remaining</div>
                            <small style="opacity:0.8">Will be restored on reactivation</small>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($daysSuspended > 0): ?>
                    <div class="col-<?= $daysRemaining > 0 ? '6' : '12' ?>">
                        <div class="days-card days-suspended-card">
                            <div class="days-value"><?= $daysSuspended ?></div>
                            <div class="days-label">Days Suspended</div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="suspended-notice">
                    <h5><i class="bi bi-exclamation-triangle me-2"></i>Account Suspended</h5>
                    <p>Your account has been temporarily suspended. Please contact <?= htmlspecialchars($ispName) ?> support to resolve this issue and reactivate your service.</p>
                    <?php if ($daysRemaining > 0): ?>
                    <p class="mt-2"><strong>Good news:</strong> You still have <?= $daysRemaining ?> days of service remaining. These will be restored when your account is reactivated.</p>
                    <?php endif; ?>
                </div>
                
                <?php if ($ispPhone): ?>
                <div class="contact-section">
                    <p class="text-muted mb-0">Contact support to reactivate</p>
                    <div class="contact-btns">
                        <a href="tel:<?= htmlspecialchars($ispPhoneFormatted) ?>" class="contact-btn contact-btn-call">
                            <i class="bi bi-telephone"></i>
                            <?= htmlspecialchars($ispPhone) ?>
                        </a>
                        <?php if ($ispWhatsApp): ?>
                        <a href="https://wa.me/<?= htmlspecialchars($ispWhatsApp) ?>?text=Hi%2C%20my%20account%20(<?= urlencode($subscription['username']) ?>)%20has%20been%20suspended.%20Please%20help%20me%20reactivate%20it." 
                           class="contact-btn contact-btn-whatsapp">
                            <i class="bi bi-whatsapp"></i>
                            WhatsApp
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <?php else: ?>
            <div class="not-found-card">
                <div class="not-found-icon">
                    <i class="bi bi-question-lg"></i>
                </div>
                <h3>Account Not Found</h3>
                <p class="text-muted">We couldn't identify your account.<br>Please search using your details below.</p>
                
                <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?> text-start mb-4">
                    <?= htmlspecialchars($message) ?>
                </div>
                <?php endif; ?>
                
                <form method="post" class="search-form">
                    <input type="hidden" name="action" value="lookup">
                    <div class="search-input-group">
                        <input type="text" name="lookup_value" class="search-input" 
                               placeholder="Username or phone number" required>
                        <button type="submit" class="search-btn">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="isp-footer">
            <p>&copy; <?= date('Y') ?> <?= htmlspecialchars($ispName) ?></p>
        </div>
    </div>
</body>
</html>
