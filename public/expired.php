<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Mpesa.php';
require_once __DIR__ . '/../src/RadiusBilling.php';

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');

$db = Database::getConnection();
$radiusBilling = new \App\RadiusBilling($db);

$ispName = $radiusBilling->getSetting('isp_name') ?: 'Your ISP';
$ispPhone = $radiusBilling->getSetting('isp_contact_phone') ?: '';
$ispPhoneFormatted = $ispPhone ? preg_replace('/[^0-9]/', '', $ispPhone) : '';
$ispWhatsApp = $ispPhoneFormatted ? '254' . substr($ispPhoneFormatted, -9) : '';
$ispLogo = $radiusBilling->getSetting('isp_logo') ?: '';
$ispEmail = $radiusBilling->getSetting('isp_contact_email') ?: '';
$mpesaPaybill = $radiusBilling->getSetting('mpesa_shortcode') ?: '';

function getClientIP(): string {
    $headers = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ips = explode(',', $_SERVER[$header]);
            $ip = trim($ips[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

$clientIP = getClientIP();
$macAddress = $_GET['mac'] ?? '';
$username = $_GET['username'] ?? '';
$message = '';
$messageType = 'info';
$subscription = null;
$pageType = 'unknown'; // 'expired', 'suspended', 'quota', 'unknown'
$stkPushSent = false;

// Parse NAS IP from URL path: /expired/10.200.0.5
$nasIP = null;
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
if (preg_match('#/expired/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})#', $requestUri, $matches)) {
    $nasIP = $matches[1];
}
// Also accept as query param: ?nas=10.200.0.5
if (!$nasIP && isset($_GET['nas'])) {
    $nasIP = $_GET['nas'];
}

// Try to find subscription by IP from active/recent session
// Include NAS IP filter if provided to avoid IP conflicts across NAS devices
if ($nasIP) {
    $stmt = $db->prepare("
        SELECT s.*, c.name as customer_name, c.phone as customer_phone, c.email as customer_email,
               p.name as package_name, p.price as package_price, p.validity_days,
               p.download_speed, p.upload_speed, rs.framed_ip_address, rs.nas_ip_address
        FROM radius_subscriptions s
        LEFT JOIN customers c ON s.customer_id = c.id
        LEFT JOIN radius_packages p ON s.package_id = p.id
        LEFT JOIN radius_sessions rs ON rs.subscription_id = s.id
        WHERE (rs.framed_ip_address = ? OR s.static_ip = ?)
          AND rs.nas_ip_address = ?
        ORDER BY rs.started_at DESC
        LIMIT 1
    ");
    $stmt->execute([$clientIP, $clientIP, $nasIP]);
} else {
    $stmt = $db->prepare("
        SELECT s.*, c.name as customer_name, c.phone as customer_phone, c.email as customer_email,
               p.name as package_name, p.price as package_price, p.validity_days,
               p.download_speed, p.upload_speed, rs.framed_ip_address
        FROM radius_subscriptions s
        LEFT JOIN customers c ON s.customer_id = c.id
        LEFT JOIN radius_packages p ON s.package_id = p.id
        LEFT JOIN radius_sessions rs ON rs.subscription_id = s.id
        WHERE rs.framed_ip_address = ? OR s.static_ip = ?
        ORDER BY rs.started_at DESC
        LIMIT 1
    ");
    $stmt->execute([$clientIP, $clientIP]);
}
$subscription = $stmt->fetch(PDO::FETCH_ASSOC);

// If not found by session IP, try by username from URL
if (!$subscription && $username) {
    $stmt = $db->prepare("
        SELECT s.*, c.name as customer_name, c.phone as customer_phone, c.email as customer_email,
               p.name as package_name, p.price as package_price, p.validity_days,
               p.download_speed, p.upload_speed
        FROM radius_subscriptions s
        LEFT JOIN customers c ON s.customer_id = c.id
        LEFT JOIN radius_packages p ON s.package_id = p.id
        WHERE s.username = ?
        LIMIT 1
    ");
    $stmt->execute([$username]);
    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Determine page type based on subscription status
if ($subscription) {
    if ($subscription['status'] === 'suspended') {
        $pageType = 'suspended';
    } elseif ($subscription['data_used_mb'] >= ($subscription['data_quota_mb'] ?? PHP_INT_MAX) && $subscription['data_quota_mb'] > 0) {
        $pageType = 'quota';
    } elseif ($subscription['expiry_date'] && strtotime($subscription['expiry_date']) < time()) {
        $pageType = 'expired';
    } else {
        $pageType = 'expired'; // Default to expired for any other issue
    }
} else {
    $pageType = 'unknown'; // No subscription found - account not registered
}

// Handle account lookup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'lookup') {
    $lookupValue = trim($_POST['lookup_value'] ?? '');
    if (!empty($lookupValue)) {
        $phone = preg_replace('/[^0-9]/', '', $lookupValue);
        if (substr($phone, 0, 1) === '0') $phone = '254' . substr($phone, 1);
        
        $stmt = $db->prepare("
            SELECT s.*, c.name as customer_name, c.phone as customer_phone,
                   p.name as package_name, p.price as package_price
            FROM radius_subscriptions s
            LEFT JOIN customers c ON s.customer_id = c.id
            LEFT JOIN radius_packages p ON s.package_id = p.id
            WHERE s.username = ? 
               OR REPLACE(REPLACE(c.phone, '+', ''), ' ', '') = ?
               OR REPLACE(REPLACE(c.phone, '+', ''), ' ', '') LIKE ?
            LIMIT 1
        ");
        $stmt->execute([$lookupValue, $phone, '%' . substr($phone, -9)]);
        $found = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($found) {
            $subscription = $found;
            $pageType = 'expired';
            $message = "Account found! Username: <strong>" . htmlspecialchars($found['username']) . "</strong>";
            $messageType = 'success';
        } else {
            $message = "No account found for '" . htmlspecialchars($lookupValue) . "'. Please contact support to register.";
            $messageType = 'warning';
        }
    }
}

// Handle M-Pesa payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'pay' && $subscription) {
    $payPhone = trim($_POST['phone'] ?? '');
    $payPhone = preg_replace('/[^0-9]/', '', $payPhone);
    if (substr($payPhone, 0, 1) === '0') $payPhone = '254' . substr($payPhone, 1);
    
    if (strlen($payPhone) >= 12) {
        try {
            $mpesa = new \App\Mpesa($db);
            $amount = (int)($subscription['package_price'] ?? 0);
            if ($amount > 0) {
                $result = $mpesa->stkPush($payPhone, $amount, "radius_{$subscription['id']}", "Internet renewal - {$subscription['package_name']}");
                if ($result['success'] ?? false) {
                    $message = "Payment request sent to {$payPhone}. Please enter your M-Pesa PIN to complete.";
                    $messageType = 'success';
                    $stkPushSent = true;
                } else {
                    $message = "Payment failed: " . ($result['error'] ?? 'Unknown error');
                    $messageType = 'danger';
                }
            }
        } catch (Exception $e) {
            $message = "Payment error: " . $e->getMessage();
            $messageType = 'danger';
        }
    } else {
        $message = "Please enter a valid phone number";
        $messageType = 'warning';
    }
}

// Page titles and messages based on type
$pageTitles = [
    'expired' => 'Subscription Expired',
    'suspended' => 'Account Suspended',
    'quota' => 'Data Quota Exhausted',
    'unknown' => 'Account Not Found'
];
$pageIcons = [
    'expired' => 'fa-clock',
    'suspended' => 'fa-ban',
    'quota' => 'fa-chart-pie',
    'unknown' => 'fa-user-slash'
];
$pageColors = [
    'expired' => '#f39c12',
    'suspended' => '#e74c3c',
    'quota' => '#9b59b6',
    'unknown' => '#95a5a6'
];
$pageMessages = [
    'expired' => 'Your internet subscription has expired. Please renew to continue browsing.',
    'suspended' => 'Your account has been suspended. Please contact support or clear any pending payments.',
    'quota' => 'You have used all your allocated data. Please purchase more data to continue.',
    'unknown' => 'Your account was not found in our system. You may need to register for service.'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitles[$pageType] ?> - <?= htmlspecialchars($ispName) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --theme-color: <?= $pageColors[$pageType] ?>;
            --gradient-start: #0f0c29;
            --gradient-mid: #302b63;
            --gradient-end: #24243e;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: linear-gradient(135deg, var(--gradient-start) 0%, var(--gradient-mid) 50%, var(--gradient-end) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            position: relative;
            overflow-x: hidden;
        }
        body::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.1) 0%, transparent 50%),
                        radial-gradient(circle at 80% 20%, rgba(139, 92, 246, 0.15) 0%, transparent 40%),
                        radial-gradient(circle at 20% 80%, rgba(59, 130, 246, 0.1) 0%, transparent 40%);
            animation: float 20s ease-in-out infinite;
            pointer-events: none;
        }
        @keyframes float {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            25% { transform: translate(2%, 2%) rotate(1deg); }
            50% { transform: translate(-1%, 3%) rotate(-1deg); }
            75% { transform: translate(3%, -2%) rotate(0.5deg); }
        }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.7; } }
        @keyframes slideUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        
        .main-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            box-shadow: 0 25px 80px rgba(0, 0, 0, 0.4), 0 0 0 1px rgba(255, 255, 255, 0.1) inset;
            max-width: 420px;
            width: 100%;
            overflow: hidden;
            animation: slideUp 0.6s ease-out;
            position: relative;
        }
        .card-header-section {
            background: linear-gradient(135deg, var(--theme-color) 0%, color-mix(in srgb, var(--theme-color) 70%, #1a1a2e) 100%);
            color: white;
            padding: 32px 28px 28px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .card-header-section::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 60%);
            pointer-events: none;
        }
        .logo-container {
            width: 72px;
            height: 72px;
            background: white;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
            position: relative;
        }
        .logo-container img { width: 48px; height: 48px; object-fit: contain; }
        .logo-container i { font-size: 28px; color: var(--theme-color); }
        .isp-name { font-size: 1.35rem; font-weight: 700; margin-bottom: 4px; letter-spacing: -0.3px; }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255,255,255,0.2);
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            backdrop-filter: blur(10px);
        }
        .status-badge i { font-size: 0.7rem; }
        
        .card-body-section { padding: 28px; }
        
        .status-card {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            text-align: center;
            border: 1px solid rgba(0,0,0,0.04);
            position: relative;
            overflow: hidden;
        }
        .status-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: var(--theme-color);
            border-radius: 4px 0 0 4px;
        }
        .status-icon {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, var(--theme-color), color-mix(in srgb, var(--theme-color) 80%, white));
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 14px;
            box-shadow: 0 8px 20px color-mix(in srgb, var(--theme-color) 40%, transparent);
        }
        .status-icon i { font-size: 24px; color: white; }
        .status-title { font-size: 1.1rem; font-weight: 700; color: #1e293b; margin-bottom: 6px; }
        .status-text { font-size: 0.9rem; color: #64748b; line-height: 1.5; }
        
        .alert-custom {
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-warning { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
        .alert-danger { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .alert-info { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }
        
        .info-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 4px;
            margin-bottom: 20px;
        }
        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 16px;
            border-bottom: 1px solid #f1f5f9;
        }
        .info-item:last-child { border-bottom: none; }
        .info-item:first-child { border-radius: 10px 10px 0 0; }
        .info-item:last-child { border-radius: 0 0 10px 10px; }
        .info-label { font-size: 0.85rem; color: #64748b; font-weight: 500; }
        .info-value { font-size: 0.95rem; font-weight: 600; color: #1e293b; }
        .info-value.success { color: #059669; }
        .info-value.danger { color: #dc2626; }
        
        .action-card {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 16px;
            padding: 22px;
            margin-top: 20px;
            border: 1px solid rgba(0,0,0,0.04);
        }
        .action-title {
            font-size: 0.95rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .action-title i {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, #10b981, #059669);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 14px;
        }
        
        .input-modern {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 1rem;
            font-family: inherit;
            transition: all 0.2s ease;
            background: white;
            color: #1e293b;
        }
        .input-modern:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }
        .input-modern::placeholder { color: #94a3b8; }
        
        .btn-pay {
            width: 100%;
            padding: 16px 24px;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            box-shadow: 0 8px 24px rgba(16, 185, 129, 0.3);
            margin-top: 14px;
        }
        .btn-pay:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 32px rgba(16, 185, 129, 0.4);
        }
        .btn-pay:active { transform: translateY(0); }
        
        .btn-search {
            padding: 14px 20px;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.3s ease;
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            color: white;
            margin-left: 10px;
        }
        .btn-search:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(99, 102, 241, 0.3);
        }
        
        .manual-pay {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border-radius: 10px;
            padding: 14px;
            margin-top: 16px;
            text-align: center;
            font-size: 0.82rem;
            color: #92400e;
            border: 1px solid #fcd34d;
        }
        .manual-pay strong { color: #78350f; }
        
        .contact-section {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .contact-btn {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 14px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        .btn-whatsapp {
            background: linear-gradient(135deg, #25d366 0%, #128c7e 100%);
            color: white;
            box-shadow: 0 6px 18px rgba(37, 211, 102, 0.3);
        }
        .btn-whatsapp:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 24px rgba(37, 211, 102, 0.4);
            color: white;
        }
        .btn-call {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
            box-shadow: 0 6px 18px rgba(59, 130, 246, 0.3);
        }
        .btn-call:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 24px rgba(59, 130, 246, 0.4);
            color: white;
        }
        
        .help-title {
            font-size: 0.85rem;
            color: #64748b;
            text-align: center;
            margin-bottom: 12px;
            font-weight: 500;
        }
        
        .ip-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #f1f5f9;
            padding: 8px 14px;
            border-radius: 20px;
            font-size: 0.75rem;
            color: #64748b;
            margin-top: 20px;
        }
        .ip-badge i { font-size: 0.7rem; }
        
        .search-row {
            display: flex;
            gap: 10px;
        }
        .search-row .input-modern { flex: 1; }
        
        .footer-text {
            text-align: center;
            padding-top: 16px;
        }
        
        @media (max-width: 480px) {
            .main-card { border-radius: 20px; margin: 10px; }
            .card-header-section { padding: 24px 20px; }
            .card-body-section { padding: 20px; }
            .contact-section { flex-direction: column; }
        }
    </style>
</head>
<body>
    <div class="main-card">
        <div class="card-header-section">
            <div class="logo-container">
                <?php if ($ispLogo): ?>
                    <img src="<?= htmlspecialchars($ispLogo) ?>" alt="Logo">
                <?php else: ?>
                    <i class="fas fa-wifi"></i>
                <?php endif; ?>
            </div>
            <div class="isp-name"><?= htmlspecialchars($ispName) ?></div>
            <div class="status-badge">
                <i class="fas <?= $pageIcons[$pageType] ?>"></i>
                <?= $pageTitles[$pageType] ?>
            </div>
        </div>
        
        <div class="card-body-section">
            <div class="status-card">
                <div class="status-icon">
                    <i class="fas <?= $pageIcons[$pageType] ?>"></i>
                </div>
                <div class="status-title"><?= $pageTitles[$pageType] ?></div>
                <div class="status-text"><?= $pageMessages[$pageType] ?></div>
            </div>

            <?php if ($message): ?>
                <div class="alert-custom alert-<?= $messageType ?>">
                    <i class="fas <?= $messageType === 'success' ? 'fa-check-circle' : ($messageType === 'warning' ? 'fa-exclamation-triangle' : ($messageType === 'danger' ? 'fa-times-circle' : 'fa-info-circle')) ?>"></i>
                    <?= $message ?>
                </div>
            <?php endif; ?>
            
            <?php if ($subscription && $pageType !== 'unknown'): ?>
                <div class="info-card">
                    <div class="info-item">
                        <span class="info-label">Account</span>
                        <span class="info-value"><?= htmlspecialchars($subscription['customer_name'] ?? $subscription['username']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Package</span>
                        <span class="info-value"><?= htmlspecialchars($subscription['package_name'] ?? 'N/A') ?></span>
                    </div>
                    <?php if ($pageType === 'expired' && $subscription['expiry_date']): ?>
                    <div class="info-item">
                        <span class="info-label">Expired On</span>
                        <span class="info-value danger"><?= date('M d, Y', strtotime($subscription['expiry_date'])) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($subscription['package_price']): ?>
                    <div class="info-item">
                        <span class="info-label">Renewal Cost</span>
                        <span class="info-value success">KES <?= number_format($subscription['package_price']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ($subscription['package_price'] && !$stkPushSent): ?>
                <div class="action-card">
                    <div class="action-title">
                        <i class="fas fa-mobile-alt"></i>
                        Pay with M-Pesa
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action" value="pay">
                        <input type="tel" class="input-modern" name="phone" placeholder="Phone number (0712...)" 
                               value="<?= htmlspecialchars($subscription['customer_phone'] ?? '') ?>" required>
                        <button type="submit" class="btn-pay">
                            <i class="fas fa-paper-plane"></i>
                            Pay KES <?= number_format($subscription['package_price']) ?>
                        </button>
                    </form>
                    <?php if ($mpesaPaybill): ?>
                    <div class="manual-pay">
                        <i class="fas fa-info-circle"></i> Manual: Paybill <strong><?= $mpesaPaybill ?></strong>, Account: <strong><?= htmlspecialchars($subscription['username']) ?></strong>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="action-card">
                    <div class="action-title">
                        <i class="fas fa-search" style="background: linear-gradient(135deg, #6366f1, #4f46e5);"></i>
                        Find Your Account
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action" value="lookup">
                        <div class="search-row">
                            <input type="text" class="input-modern" name="lookup_value" placeholder="Username or phone number" required>
                            <button type="submit" class="btn-search"><i class="fas fa-search"></i></button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <?php if ($ispWhatsApp || $ispPhone): ?>
            <div class="help-title">Contact <?= htmlspecialchars($ispName) ?></div>
            <div class="contact-section">
                <?php if ($ispPhone): ?>
                    <a href="tel:+<?= $ispPhoneFormatted ?>" class="contact-btn btn-call">
                        <i class="fas fa-phone"></i> Call <?= $ispPhone ?>
                    </a>
                <?php endif; ?>
                <?php if ($ispWhatsApp): ?>
                    <a href="https://wa.me/<?= $ispWhatsApp ?>" class="contact-btn btn-whatsapp" target="_blank">
                        <i class="fab fa-whatsapp"></i> WhatsApp
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div class="footer-text">
                <div class="ip-badge">
                    <i class="fas fa-network-wired"></i> Your IP: <?= htmlspecialchars($clientIP) ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
