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

// Try to find subscription by IP from active/recent session
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --theme-color: <?= $pageColors[$pageType] ?>; }
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .main-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 500px;
            width: 100%;
            overflow: hidden;
        }
        .card-header {
            background: linear-gradient(135deg, var(--theme-color) 0%, color-mix(in srgb, var(--theme-color) 80%, black) 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .card-header .logo { width: 80px; height: 80px; object-fit: contain; margin-bottom: 15px; background: white; border-radius: 50%; padding: 10px; }
        .card-header h1 { font-size: 1.5rem; margin: 0; font-weight: 600; }
        .card-body { padding: 30px; }
        .status-box { background: #f8f9fa; border-left: 4px solid var(--theme-color); padding: 20px; margin-bottom: 25px; border-radius: 8px; }
        .status-box i { color: var(--theme-color); font-size: 2rem; margin-bottom: 10px; }
        .info-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #eee; }
        .info-row:last-child { border-bottom: none; }
        .info-label { color: #6c757d; font-size: 0.9rem; }
        .info-value { font-weight: 600; color: #333; }
        .action-section { background: #f8f9fa; padding: 20px; border-radius: 12px; margin-top: 20px; }
        .btn-pay { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); border: none; padding: 12px 30px; font-weight: 600; border-radius: 8px; color: white; }
        .btn-pay:hover { background: linear-gradient(135deg, #218838 0%, #1aa179 100%); color: white; }
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; }
        .contact-btn { margin: 5px; padding: 10px 20px; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-weight: 500; }
        .btn-whatsapp { background: #25D366; color: white; }
        .btn-whatsapp:hover { background: #128C7E; color: white; }
        .btn-call { background: #007bff; color: white; }
        .btn-call:hover { background: #0056b3; color: white; }
        .ip-info { font-size: 0.8rem; color: #adb5bd; text-align: center; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="main-card">
        <div class="card-header">
            <?php if ($ispLogo): ?>
                <img src="<?= htmlspecialchars($ispLogo) ?>" alt="Logo" class="logo">
            <?php else: ?>
                <i class="fas fa-wifi fa-3x mb-3"></i>
            <?php endif; ?>
            <h1><?= htmlspecialchars($ispName) ?></h1>
            <p class="mb-0 opacity-75"><?= $pageTitles[$pageType] ?></p>
        </div>
        
        <div class="card-body">
            <div class="status-box text-center">
                <i class="fas <?= $pageIcons[$pageType] ?>"></i>
                <h5 class="mt-2 mb-2"><?= $pageTitles[$pageType] ?></h5>
                <p class="mb-0 text-muted"><?= $pageMessages[$pageType] ?></p>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?> mb-3"><?= $message ?></div>
            <?php endif; ?>
            
            <?php if ($subscription && $pageType !== 'unknown'): ?>
                <!-- Account Info -->
                <div class="account-info mb-4">
                    <div class="info-row">
                        <span class="info-label">Account</span>
                        <span class="info-value"><?= htmlspecialchars($subscription['customer_name'] ?? $subscription['username']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Package</span>
                        <span class="info-value"><?= htmlspecialchars($subscription['package_name'] ?? 'N/A') ?></span>
                    </div>
                    <?php if ($pageType === 'expired' && $subscription['expiry_date']): ?>
                    <div class="info-row">
                        <span class="info-label">Expired On</span>
                        <span class="info-value text-danger"><?= date('M d, Y', strtotime($subscription['expiry_date'])) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($subscription['package_price']): ?>
                    <div class="info-row">
                        <span class="info-label">Renewal Cost</span>
                        <span class="info-value text-success">KES <?= number_format($subscription['package_price']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Payment Section -->
                <?php if ($subscription['package_price'] && !$stkPushSent): ?>
                <div class="action-section">
                    <h5 class="mb-3"><i class="fas fa-mobile-alt me-2"></i>Pay with M-Pesa</h5>
                    <form method="POST">
                        <input type="hidden" name="action" value="pay">
                        <div class="input-group mb-3">
                            <span class="input-group-text"><i class="fas fa-phone"></i></span>
                            <input type="tel" class="form-control" name="phone" placeholder="0712345678" 
                                   value="<?= htmlspecialchars($subscription['customer_phone'] ?? '') ?>" required>
                        </div>
                        <button type="submit" class="btn btn-pay w-100">
                            <i class="fas fa-paper-plane me-2"></i>Pay KES <?= number_format($subscription['package_price']) ?>
                        </button>
                    </form>
                    <?php if ($mpesaPaybill): ?>
                    <p class="text-muted text-center mt-3 mb-0" style="font-size: 0.85rem;">
                        Or pay manually: Paybill <strong><?= $mpesaPaybill ?></strong>, Account: <strong><?= htmlspecialchars($subscription['username']) ?></strong>
                    </p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            <?php else: ?>
                <!-- Account Lookup for Unknown Users -->
                <div class="action-section">
                    <h5><i class="fas fa-search me-2"></i>Find Your Account</h5>
                    <p class="text-muted mb-3">Enter your phone number or username:</p>
                    <form method="POST">
                        <input type="hidden" name="action" value="lookup">
                        <div class="input-group mb-3">
                            <input type="text" class="form-control" name="lookup_value" placeholder="Phone (07XX...) or Username" required>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Contact Section -->
            <div class="action-section text-center mt-3">
                <h6 class="mb-3"><i class="fas fa-headset me-2"></i>Need Help?</h6>
                <?php if ($ispWhatsApp): ?>
                    <a href="https://wa.me/<?= $ispWhatsApp ?>" class="contact-btn btn-whatsapp" target="_blank">
                        <i class="fab fa-whatsapp"></i> WhatsApp
                    </a>
                <?php endif; ?>
                <?php if ($ispPhone): ?>
                    <a href="tel:<?= $ispPhoneFormatted ?>" class="contact-btn btn-call">
                        <i class="fas fa-phone"></i> Call
                    </a>
                <?php endif; ?>
            </div>
            
            <div class="ip-info">
                <i class="fas fa-network-wired me-1"></i> IP: <?= htmlspecialchars($clientIP) ?>
                <?php if ($macAddress): ?> | MAC: <?= htmlspecialchars($macAddress) ?><?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
