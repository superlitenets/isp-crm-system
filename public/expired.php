<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Mpesa.php';
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
$customer = null;
$package = null;
$message = '';
$messageType = 'info';
$stkPushSent = false;
$lookupMode = false;

$radiusBilling = new \App\RadiusBilling($db);
$ispName = $radiusBilling->getSetting('isp_name') ?: 'Your ISP';
$ispPhone = $radiusBilling->getSetting('isp_contact_phone') ?: '';
$ispPhoneFormatted = $ispPhone ? preg_replace('/[^0-9]/', '', $ispPhone) : '';
$ispWhatsApp = $ispPhoneFormatted ? '254' . substr($ispPhoneFormatted, -9) : '';
$ispLogo = $radiusBilling->getSetting('isp_logo') ?: '';
$mpesaPaybill = $radiusBilling->getSetting('mpesa_shortcode') ?: '';

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

    if (!$subscription) {
        $stmt = $db->prepare("
            SELECT s.*, c.name as customer_name, c.phone as customer_phone, c.email as customer_email,
                   p.name as package_name, p.price as package_price, p.validity_days,
                   p.download_speed, p.upload_speed, rs.framed_ip_address
            FROM radius_sessions rs
            LEFT JOIN radius_subscriptions s ON rs.subscription_id = s.id
            LEFT JOIN customers c ON s.customer_id = c.id
            LEFT JOIN radius_packages p ON s.package_id = p.id
            WHERE rs.framed_ip_address = ?
            ORDER BY rs.started_at DESC
            LIMIT 1
        ");
        $stmt->execute([$clientIP]);
        $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'stk_push' && $subscription) {
        $phone = $_POST['phone'] ?? $subscription['customer_phone'] ?? '';
        $amount = (int)($subscription['package_price'] ?? 0);
        $walletBalance = (float)($subscription['credit_balance'] ?? 0);
        
        if ($walletBalance > 0 && $walletBalance < $amount) {
            $amount = $amount - floor($walletBalance);
        }
        
        $accountRef = $subscription['username'];
        
        if (empty($phone)) {
            $message = "Please enter your M-Pesa phone number.";
            $messageType = 'danger';
        } elseif ($amount < 1) {
            $message = "Invalid package price.";
            $messageType = 'danger';
        } else {
            try {
                $mpesa = new \App\Mpesa();
                if ($mpesa->isConfigured()) {
                    $result = $mpesa->stkPush($phone, $amount, $accountRef, "Internet Renewal - {$subscription['package_name']}");
                    if ($result && isset($result['ResponseCode']) && $result['ResponseCode'] == '0') {
                        $message = "Payment request sent! Check your phone for the M-Pesa prompt.";
                        $messageType = 'success';
                        $stkPushSent = true;
                    } else {
                        $message = "Failed to send payment request: " . ($result['errorMessage'] ?? $result['ResponseDescription'] ?? 'Unknown error');
                        $messageType = 'danger';
                    }
                } else {
                    $message = "M-Pesa STK Push not configured. Please pay manually via Paybill.";
                    $messageType = 'info';
                }
            } catch (Exception $e) {
                $message = "Payment error: " . $e->getMessage();
                $messageType = 'danger';
            }
        }
    }
}

$mpesaConfigured = false;
try {
    $mpesa = new \App\Mpesa();
    $mpesaConfigured = $mpesa->isConfigured();
} catch (Exception $e) {
    $mpesaConfigured = false;
}

$subscriptionStatus = $subscription ? $subscription['status'] : null;
$isSuspended = $subscriptionStatus === 'suspended';
$isDisabled = $subscriptionStatus === 'disabled';
$isExpired = $subscriptionStatus === 'expired' || ($subscription && $subscription['expiry_date'] && strtotime($subscription['expiry_date']) < time());

$statusConfig = [
    'suspended' => [
        'icon' => 'bi-pause-circle-fill',
        'title' => 'Account Suspended',
        'subtitle' => 'Your account has been temporarily suspended',
        'color' => '#fd7e14',
        'colorDark' => '#e55a00',
        'animation' => 'pulse 2s infinite',
        'canPay' => false,
        'notice' => 'Your account has been suspended. Please contact support to resolve this issue and reactivate your service.',
    ],
    'disabled' => [
        'icon' => 'bi-slash-circle-fill',
        'title' => 'Account Disabled',
        'subtitle' => 'Your account has been disabled',
        'color' => '#6c757d',
        'colorDark' => '#495057',
        'animation' => 'none',
        'canPay' => false,
        'notice' => 'Your account has been disabled by the administrator. Please contact support for assistance.',
    ],
    'wrong_mac' => [
        'icon' => 'bi-hdd-network-fill',
        'title' => 'Device Not Authorized',
        'subtitle' => 'This device is not registered to your account',
        'color' => '#6f42c1',
        'colorDark' => '#5a32a3',
        'animation' => 'shake 0.5s ease-in-out',
        'canPay' => false,
        'notice' => 'Your account is bound to a different device (MAC address). Please connect from your registered device or contact support to update your device registration.',
    ],
    'wrong_credentials' => [
        'icon' => 'bi-key-fill',
        'title' => 'Authentication Failed',
        'subtitle' => 'Invalid username or password',
        'color' => '#dc3545',
        'colorDark' => '#c82333',
        'animation' => 'shake 0.5s ease-in-out',
        'canPay' => false,
        'notice' => 'Your login credentials are incorrect. Please check your username and password, or contact support if you need help resetting your credentials.',
    ],
    'expired' => [
        'icon' => 'bi-wifi-off',
        'title' => 'Subscription Expired',
        'subtitle' => 'Renew now to restore your internet',
        'color' => '#dc3545',
        'colorDark' => '#c82333',
        'animation' => 'shake 0.5s ease-in-out',
        'canPay' => true,
        'notice' => null,
    ],
];

$reason = $_GET['reason'] ?? null;
if ($reason === 'mac') {
    $currentStatus = 'wrong_mac';
} elseif ($reason === 'auth' || $reason === 'credentials') {
    $currentStatus = 'wrong_credentials';
} elseif ($isDisabled) {
    $currentStatus = 'disabled';
} elseif ($isSuspended) {
    $currentStatus = 'suspended';
} else {
    $currentStatus = 'expired';
}

$statusInfo = $statusConfig[$currentStatus];
$canPay = $statusInfo['canPay'];

$walletBalance = $subscription ? (float)($subscription['credit_balance'] ?? 0) : 0;
$packagePrice = $subscription ? (float)($subscription['package_price'] ?? 0) : 0;
$amountNeeded = max(0, $packagePrice - $walletBalance);

$daysExpired = 0;
if ($subscription && $subscription['expiry_date']) {
    $expiryTime = strtotime($subscription['expiry_date']);
    $daysExpired = max(0, floor((time() - $expiryTime) / 86400));
}

$daysSuspended = 0;
$savedDays = 0;
if ($isSuspended && $subscription) {
    if (!empty($subscription['suspended_at'])) {
        $daysSuspended = ceil((time() - strtotime($subscription['suspended_at'])) / 86400);
    }
    $savedDays = (int)($subscription['days_remaining_at_suspension'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($statusInfo['title']) ?> - <?= htmlspecialchars($ispName) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: <?= $statusInfo['color'] ?>;
            --primary-dark: <?= $statusInfo['colorDark'] ?>;
            --success-color: #198754;
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
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        .expired-card {
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
            animation: <?= $statusInfo['animation'] ?>;
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
        
        .days-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(0,0,0,0.3);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            backdrop-filter: blur(5px);
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
        
        .wallet-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 16px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
        }
        
        .wallet-balance {
            font-size: 2rem;
            font-weight: 700;
            color: var(--success-color);
        }
        
        .wallet-label {
            font-size: 0.8rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .amount-needed {
            background: linear-gradient(135deg, var(--success-color), #157347);
            color: white;
            padding: 20px;
            border-radius: 16px;
            text-align: center;
            margin: 20px 0;
        }
        
        .amount-value {
            font-size: 2.5rem;
            font-weight: 700;
        }
        
        .amount-label {
            font-size: 0.85rem;
            opacity: 0.9;
        }
        
        .payment-section {
            margin-top: 25px;
        }
        
        .phone-input-group {
            position: relative;
        }
        
        .phone-input {
            width: 100%;
            padding: 16px 20px;
            padding-left: 50px;
            font-size: 1.1rem;
            border: 2px solid #e9ecef;
            border-radius: 14px;
            transition: all 0.3s ease;
        }
        
        .phone-input:focus {
            outline: none;
            border-color: var(--success-color);
            box-shadow: 0 0 0 4px rgba(25, 135, 84, 0.1);
        }
        
        .phone-prefix {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        
        .pay-btn {
            width: 100%;
            padding: 18px;
            font-size: 1.15rem;
            font-weight: 600;
            border: none;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--success-color), #157347);
            color: white;
            margin-top: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .pay-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(25, 135, 84, 0.3);
        }
        
        .pay-btn:active {
            transform: translateY(0);
        }
        
        .mpesa-icon {
            width: 28px;
            height: 28px;
            background: white;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--success-color);
            font-weight: 700;
            font-size: 0.7rem;
        }
        
        .paybill-info {
            background: #f8f9fa;
            border-radius: 14px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .paybill-title {
            font-weight: 600;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .paybill-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px dashed #dee2e6;
        }
        
        .paybill-row:last-child {
            border-bottom: none;
        }
        
        .copy-btn {
            background: none;
            border: none;
            color: var(--success-color);
            cursor: pointer;
            padding: 2px 8px;
            border-radius: 4px;
            transition: background 0.2s;
        }
        
        .copy-btn:hover {
            background: rgba(25, 135, 84, 0.1);
        }
        
        .waiting-payment {
            text-align: center;
            padding: 30px 20px;
        }
        
        .waiting-spinner {
            width: 60px;
            height: 60px;
            border: 4px solid #e9ecef;
            border-top-color: var(--success-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .suspended-notice {
            background: linear-gradient(135deg, #fff3cd, #ffeeba);
            border-left: 4px solid #fd7e14;
            padding: 20px;
            border-radius: 0 14px 14px 0;
            margin: 20px 0;
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
        
        .alert-success {
            background: #d1e7dd;
            color: #0f5132;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #842029;
        }
        
        .alert-warning {
            background: #fff3cd;
            color: #664d03;
        }
        
        @media (max-width: 480px) {
            body { padding: 15px; }
            .card-header { padding: 30px 20px; }
            .card-body { padding: 20px; }
            .header-title { font-size: 1.5rem; }
            .amount-value { font-size: 2rem; }
            .contact-btns { flex-direction: column; }
            .contact-btn { justify-content: center; }
        }
    </style>
</head>
<body>
    <div class="page-container">
        <div class="expired-card">
            <?php if ($subscription): ?>
            <div class="card-header">
                <div class="header-content">
                    <?php if ($currentStatus === 'expired' && $daysExpired > 0): ?>
                    <div class="days-badge">
                        <i class="bi bi-calendar-x me-1"></i>
                        <?= $daysExpired ?> day<?= $daysExpired > 1 ? 's' : '' ?> ago
                    </div>
                    <?php elseif ($currentStatus === 'suspended' && $daysSuspended > 0): ?>
                    <div class="days-badge">
                        <i class="bi bi-pause-circle me-1"></i>
                        Suspended <?= $daysSuspended ?> day<?= $daysSuspended > 1 ? 's' : '' ?> ago
                    </div>
                    <?php endif; ?>
                    
                    <div class="status-icon">
                        <i class="bi <?= $statusInfo['icon'] ?>"></i>
                    </div>
                    
                    <h1 class="header-title">
                        <?= htmlspecialchars($statusInfo['title']) ?>
                    </h1>
                    <p class="header-subtitle">
                        <?= htmlspecialchars($statusInfo['subtitle']) ?>
                    </p>
                    
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
                <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?> mb-4">
                    <i class="bi bi-<?= $messageType === 'success' ? 'check-circle' : ($messageType === 'danger' ? 'exclamation-circle' : 'info-circle') ?> me-2"></i>
                    <?= htmlspecialchars($message) ?>
                </div>
                <?php endif; ?>
                
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label"><i class="bi bi-person"></i> Account</span>
                        <span class="info-value"><?= htmlspecialchars($subscription['customer_name'] ?? 'N/A') ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label"><i class="bi bi-key"></i> Username</span>
                        <span class="info-value"><?= htmlspecialchars($subscription['username']) ?></span>
                    </div>
                    <?php if ($currentStatus === 'expired'): ?>
                    <div class="info-item">
                        <span class="info-label"><i class="bi bi-calendar-x"></i> Expired</span>
                        <span class="info-value text-danger">
                            <?= $subscription['expiry_date'] ? date('M j, Y', strtotime($subscription['expiry_date'])) : 'N/A' ?>
                        </span>
                    </div>
                    <?php elseif ($currentStatus === 'suspended'): ?>
                    <div class="info-item">
                        <span class="info-label"><i class="bi bi-shield-x"></i> Status</span>
                        <span class="info-value" style="color: var(--primary-color);">
                            <i class="bi bi-pause-circle-fill me-1"></i> Suspended
                        </span>
                    </div>
                    <?php if ($savedDays > 0): ?>
                    <div class="info-item">
                        <span class="info-label"><i class="bi bi-hourglass-split"></i> Days Saved</span>
                        <span class="info-value text-success">
                            <i class="bi bi-check-circle me-1"></i> <?= $savedDays ?> days
                        </span>
                    </div>
                    <?php endif; ?>
                    <?php else: ?>
                    <div class="info-item">
                        <span class="info-label"><i class="bi bi-shield-x"></i> Status</span>
                        <span class="info-value" style="color: var(--primary-color);">
                            <i class="bi <?= $statusInfo['icon'] ?> me-1"></i> <?= htmlspecialchars($statusInfo['title']) ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if (!$canPay && $statusInfo['notice']): ?>
                <div class="suspended-notice">
                    <strong><i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($statusInfo['title']) ?></strong>
                    <p class="mb-0 mt-2" style="font-size: 0.9rem;">
                        <?= htmlspecialchars($statusInfo['notice']) ?>
                    </p>
                    <?php if ($currentStatus === 'suspended' && $savedDays > 0): ?>
                    <p class="mb-0 mt-2 text-success" style="font-size: 0.9rem;">
                        <i class="bi bi-check-circle me-1"></i>
                        <strong><?= $savedDays ?> days</strong> will be restored when your account is reactivated.
                    </p>
                    <?php endif; ?>
                </div>
                <?php elseif ($canPay): ?>
                
                <?php if ($walletBalance > 0): ?>
                <div class="wallet-card">
                    <div class="wallet-label">Wallet Balance</div>
                    <div class="wallet-balance">KES <?= number_format($walletBalance, 0) ?></div>
                    <?php if ($walletBalance >= $packagePrice): ?>
                    <div class="text-success mt-2"><i class="bi bi-check-circle me-1"></i> Sufficient for renewal!</div>
                    <?php else: ?>
                    <div class="text-muted mt-2">Need KES <?= number_format($amountNeeded, 0) ?> more</div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <div class="amount-needed">
                    <div class="amount-label">Amount to Pay</div>
                    <div class="amount-value">KES <?= number_format($amountNeeded, 0) ?></div>
                    <div class="amount-label" style="margin-top:5px;">
                        <?= $subscription['validity_days'] ? $subscription['validity_days'] . ' days validity' : '' ?>
                    </div>
                </div>
                
                <?php if ($stkPushSent): ?>
                <div class="waiting-payment">
                    <div class="waiting-spinner"></div>
                    <h5>Waiting for Payment</h5>
                    <p class="text-muted">Check your phone for the M-Pesa prompt<br>and enter your PIN to complete</p>
                    <button onclick="location.reload()" class="btn btn-outline-secondary mt-3">
                        <i class="bi bi-arrow-clockwise me-2"></i>Check Status
                    </button>
                </div>
                <script>
                    setTimeout(function() { location.reload(); }, 30000);
                </script>
                <?php else: ?>
                <div class="payment-section">
                    <form method="post">
                        <input type="hidden" name="action" value="stk_push">
                        <div class="phone-input-group">
                            <i class="bi bi-phone phone-prefix"></i>
                            <input type="tel" name="phone" class="phone-input" 
                                   value="<?= htmlspecialchars($subscription['customer_phone'] ?? '') ?>" 
                                   placeholder="0712 345 678" required
                                   pattern="^(07|01|2547|2541)[0-9]{8}$">
                        </div>
                        <button type="submit" class="pay-btn">
                            <span class="mpesa-icon">M</span>
                            Pay KES <?= number_format($amountNeeded, 0) ?> with M-Pesa
                        </button>
                    </form>
                    
                    <?php if ($mpesaPaybill): ?>
                    <div class="paybill-info">
                        <div class="paybill-title">
                            <i class="bi bi-info-circle"></i> Or Pay via Paybill
                        </div>
                        <div class="paybill-row">
                            <span class="text-muted">Paybill Number</span>
                            <span>
                                <strong><?= htmlspecialchars($mpesaPaybill) ?></strong>
                                <button class="copy-btn" onclick="copyText('<?= htmlspecialchars($mpesaPaybill) ?>')">
                                    <i class="bi bi-clipboard"></i>
                                </button>
                            </span>
                        </div>
                        <div class="paybill-row">
                            <span class="text-muted">Account Number</span>
                            <span>
                                <strong><?= htmlspecialchars($subscription['username']) ?></strong>
                                <button class="copy-btn" onclick="copyText('<?= htmlspecialchars($subscription['username']) ?>')">
                                    <i class="bi bi-clipboard"></i>
                                </button>
                            </span>
                        </div>
                        <div class="paybill-row">
                            <span class="text-muted">Amount</span>
                            <span><strong>KES <?= number_format($amountNeeded, 0) ?></strong></span>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php endif; ?>
                
                <?php if ($ispPhone): ?>
                <div class="contact-section">
                    <p class="text-muted mb-0">Need help?</p>
                    <div class="contact-btns">
                        <a href="tel:<?= htmlspecialchars($ispPhoneFormatted) ?>" class="contact-btn contact-btn-call">
                            <i class="bi bi-telephone"></i>
                            <?= htmlspecialchars($ispPhone) ?>
                        </a>
                        <?php if ($ispWhatsApp): ?>
                        <a href="https://wa.me/<?= htmlspecialchars($ispWhatsApp) ?>?text=Hi%2C%20I%20need%20help%20with%20my%20internet%20account%20(<?= urlencode($subscription['username']) ?>)" 
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
                               placeholder="Username or phone number" required
                               value="<?= htmlspecialchars($_POST['lookup_value'] ?? '') ?>">
                        <button type="submit" class="search-btn">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                </form>
                
                <?php if ($ispPhone): ?>
                <div class="contact-section" style="border-top: none; margin-top: 30px;">
                    <p class="text-muted mb-0">Contact <?= htmlspecialchars($ispName) ?></p>
                    <div class="contact-btns">
                        <a href="tel:<?= htmlspecialchars($ispPhoneFormatted) ?>" class="contact-btn contact-btn-call">
                            <i class="bi bi-telephone"></i> Call
                        </a>
                        <?php if ($ispWhatsApp): ?>
                        <a href="https://wa.me/<?= htmlspecialchars($ispWhatsApp) ?>?text=Hi%2C%20I%20need%20help%20with%20my%20internet%20account" 
                           class="contact-btn contact-btn-whatsapp">
                            <i class="bi bi-whatsapp"></i> WhatsApp
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="text-muted mt-4" style="font-size: 0.8rem;">
                    <i class="bi bi-geo-alt me-1"></i> Your IP: <?= htmlspecialchars($clientIP) ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="isp-footer">
            <strong><?= htmlspecialchars($ispName) ?></strong>
        </div>
    </div>
    
    <script>
        function copyText(text) {
            navigator.clipboard.writeText(text).then(function() {
                alert('Copied: ' + text);
            });
        }
    </script>
</body>
</html>
