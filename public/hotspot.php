<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/RadiusBilling.php';
require_once __DIR__ . '/../src/Mpesa.php';

$db = Database::getConnection();
$radiusBilling = new \App\RadiusBilling($db);

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

function getClientMAC(): string {
    return $_GET['mac'] ?? $_POST['mac'] ?? $_SERVER['HTTP_X_CLIENT_MAC'] ?? '';
}

$clientIP = getClientIP();
$clientMAC = getClientMAC();
$message = '';
$messageType = 'info';
$loginSuccess = false;
$stkPushSent = false;
$subscription = null;
$deviceStatus = 'unknown'; // 'active', 'expired', 'new'

// MikroTik hotspot variables
$linkLogin = $_GET['link-login'] ?? '';
$linkLoginOnly = $_GET['link-login-only'] ?? $_GET['loginLink'] ?? '';
$linkOrig = $_GET['link-orig'] ?? $_GET['dst'] ?? '';
$errorMsg = $_GET['error'] ?? '';

// CHAP authentication variables from MikroTik
$chapId = $_GET['chapID'] ?? $_GET['chap-id'] ?? '';
$chapChallenge = $_GET['chapChallenge'] ?? $_GET['chap-challenge'] ?? '';

// Store CHAP vars in session for form submissions
session_start();
if ($chapId) $_SESSION['chapId'] = $chapId;
if ($chapChallenge) $_SESSION['chapChallenge'] = $chapChallenge;
if ($linkLoginOnly) $_SESSION['linkLoginOnly'] = $linkLoginOnly;
if ($clientMAC) $_SESSION['clientMAC'] = $clientMAC;

// Restore from session if not in URL
$chapId = $chapId ?: ($_SESSION['chapId'] ?? '');
$chapChallenge = $chapChallenge ?: ($_SESSION['chapChallenge'] ?? '');
$linkLoginOnly = $linkLoginOnly ?: ($_SESSION['linkLoginOnly'] ?? '');
$clientMAC = $clientMAC ?: ($_SESSION['clientMAC'] ?? '');

// Get ISP settings
$ispName = $radiusBilling->getSetting('isp_name') ?: 'WiFi Hotspot';
$ispLogo = $radiusBilling->getSetting('isp_logo') ?: '';
$hotspotWelcome = $radiusBilling->getSetting('hotspot_welcome') ?: 'Connect to the internet';
$mpesaPaybill = $radiusBilling->getSetting('mpesa_paybill') ?: '';

// Check M-Pesa configuration
$mpesa = new \App\Mpesa();
$mpesaEnabled = $mpesa->isConfigured();

// Detect NAS device and MAC from URL path (/hotspot/{nas_ip}/{mac}), query params, or session
$nasIP = $_GET['nas'] ?? $_GET['server'] ?? $_GET['nasip'] ?? '';
$pathMAC = '';
if (empty($nasIP)) {
    $requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if (preg_match('#/hotspot/([0-9.:]+)(?:/([0-9a-fA-F:.-]+))?#', $requestUri, $matches)) {
        $nasIP = $matches[1];
        if (!empty($matches[2])) {
            $pathMAC = $matches[2];
        }
    }
}
if ($nasIP) {
    $nasIP = preg_replace('/:\d+$/', '', $nasIP);
    $_SESSION['nasIP'] = $nasIP;
}
$nasIP = $nasIP ?: ($_SESSION['nasIP'] ?? '');

if (!empty($pathMAC) && empty($clientMAC)) {
    $clientMAC = $pathMAC;
}

$currentNAS = null;
if (!empty($nasIP)) {
    $currentNAS = $radiusBilling->getNASByIP($nasIP);
}

// Get available hotspot packages (filtered by NAS if detected)
$packages = [];
if ($currentNAS) {
    // Get packages assigned to this NAS
    $packages = $radiusBilling->getNASPackages($currentNAS['id']);
}
if (empty($packages)) {
    // Fallback to all active hotspot packages if no NAS-specific packages
    $stmt = $db->query("SELECT * FROM radius_packages WHERE is_active = true AND package_type = 'hotspot' ORDER BY price ASC");
    $packages = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Check if MAC authentication is enabled
$macAuthEnabled = $radiusBilling->getSetting('hotspot_mac_auth') === 'true';

// Check device status by MAC (using multi-device lookup)
if (!empty($clientMAC) && $macAuthEnabled) {
    $subscription = $radiusBilling->getSubscriptionByDeviceMAC($clientMAC);
    
    if ($subscription) {
        if (!$subscription['is_expired'] && $subscription['status'] === 'active') {
            // Auto-login for active subscription - redirect to MikroTik login
            $deviceStatus = 'active';
            $loginSuccess = true;
        } else {
            $deviceStatus = 'expired';
        }
    } else {
        $deviceStatus = 'new';
    }
} elseif (!$macAuthEnabled) {
    // MAC auth disabled - treat all as new
    $deviceStatus = 'new';
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'voucher':
            $code = trim($_POST['voucher_code'] ?? '');
            if (empty($code)) {
                $message = 'Please enter a voucher code';
                $messageType = 'danger';
            } elseif (empty($clientMAC)) {
                $message = 'Unable to detect your device. Please reconnect to the WiFi.';
                $messageType = 'danger';
            } else {
                $result = $radiusBilling->redeemVoucherForMAC($code, $clientMAC);
                if ($result['success']) {
                    $loginSuccess = true;
                    $message = $result['message'];
                    $messageType = 'success';
                    $subscription = $radiusBilling->getSubscriptionByMAC($clientMAC);
                } else {
                    $message = $result['error'] ?? 'Invalid voucher';
                    $messageType = 'danger';
                }
            }
            break;
            
        case 'register':
            $phone = $_POST['phone'] ?? '';
            $packageId = (int)($_POST['package_id'] ?? 0);
            
            if (empty($phone)) {
                $message = 'Please enter your phone number';
                $messageType = 'danger';
            } elseif ($packageId <= 0) {
                $message = 'Please select a package';
                $messageType = 'danger';
            } elseif (empty($clientMAC)) {
                $message = 'Unable to detect your device. Please reconnect to the WiFi.';
                $messageType = 'danger';
            } else {
                $result = $radiusBilling->registerHotspotDeviceByPhone($phone, $clientMAC, $packageId);
                if ($result['success']) {
                    // Send M-Pesa STK Push
                    try {
                        $stkResult = $mpesa->stkPush($phone, (int)$result['amount'], 
                            'HS-' . $result['subscription_id'], 
                            "WiFi - {$result['package']['name']}");
                        if ($stkResult && !empty($stkResult['success'])) {
                            $message = "Payment request sent! Enter your M-Pesa PIN on your phone.";
                            $messageType = 'success';
                            $stkPushSent = true;
                            $subscription = $radiusBilling->getSubscriptionByMAC($clientMAC);
                            $deviceStatus = 'pending';
                        } else {
                            $message = $stkResult['message'] ?? 'Failed to send payment request. Use voucher instead.';
                            $messageType = 'warning';
                        }
                    } catch (Exception $e) {
                        $message = 'Payment service error. Please use a voucher instead.';
                        $messageType = 'warning';
                    }
                } else {
                    $message = $result['error'];
                    $messageType = 'danger';
                }
            }
            break;
            
        case 'renew':
            $phone = $_POST['phone'] ?? '';
            
            if (empty($phone)) {
                $message = 'Please enter your phone number';
                $messageType = 'danger';
            } elseif (!$subscription) {
                $message = 'No subscription found for this device';
                $messageType = 'danger';
            } else {
                try {
                    $amount = (int)($subscription['package_price'] ?? 0);
                    if ($amount <= 0) {
                        $message = 'Package price not configured. Please use a voucher.';
                        $messageType = 'warning';
                    } else {
                        $stkResult = $mpesa->stkPush($phone, $amount, 
                            'HS-' . $subscription['id'], 
                            "Renew - {$subscription['package_name']}");
                        if ($stkResult && !empty($stkResult['success'])) {
                            $message = "Payment request sent! Enter your M-Pesa PIN on your phone.";
                            $messageType = 'success';
                            $stkPushSent = true;
                        } else {
                            $message = $stkResult['message'] ?? 'Failed to send payment request. Use voucher instead.';
                            $messageType = 'warning';
                        }
                    }
                } catch (Exception $e) {
                    $message = 'Payment service error. Please use a voucher instead.';
                    $messageType = 'warning';
                }
            }
            break;
            
        case 'add_device':
            // Customer adding another device to their subscription
            $phone = $_POST['phone'] ?? '';
            $newMAC = trim($_POST['new_mac'] ?? '');
            $deviceName = trim($_POST['device_name'] ?? '');
            
            if (empty($phone) || empty($newMAC)) {
                $message = 'Please provide phone number and device MAC address';
                $messageType = 'danger';
            } else {
                // Find subscription by phone number
                $stmt = $db->prepare("
                    SELECT rs.*, rp.max_devices 
                    FROM radius_subscriptions rs
                    JOIN radius_packages rp ON rs.package_id = rp.id
                    WHERE rs.phone = ? AND rs.access_type = 'hotspot' AND rs.status = 'active'
                    ORDER BY rs.created_at DESC LIMIT 1
                ");
                $stmt->execute([$phone]);
                $subByPhone = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$subByPhone) {
                    $message = 'No active subscription found for this phone number';
                    $messageType = 'danger';
                } else {
                    $result = $radiusBilling->addSubscriptionDevice(
                        $subByPhone['id'], 
                        strtoupper(preg_replace('/[^a-fA-F0-9:]/', '', $newMAC)),
                        $deviceName
                    );
                    if ($result['success']) {
                        $message = 'Device added successfully! You can now connect.';
                        $messageType = 'success';
                        $subscription = $radiusBilling->getSubscriptionByDeviceMAC($newMAC);
                        $deviceStatus = 'active';
                    } else {
                        $message = $result['error'];
                        $messageType = 'danger';
                    }
                }
            }
            break;
    }
}

// Handle MikroTik error messages
if ($errorMsg && empty($message)) {
    $errorMessages = [
        'chap-missing' => 'Authentication error. Please try again.',
        'invalid username or password' => 'Session expired. Please pay to reconnect.',
        'user already logged in' => 'You are already logged in on another device',
        'radius server is not responding' => 'Server temporarily unavailable. Please try again.',
    ];
    $message = $errorMessages[strtolower($errorMsg)] ?? $errorMsg;
    $messageType = 'danger';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($ispName) ?> - WiFi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', system-ui, sans-serif;
            padding: 15px;
            margin: 0;
        }
        .hotspot-card {
            background: white;
            border-radius: 24px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.4);
            max-width: 400px;
            width: 100%;
            overflow: hidden;
            animation: slideUp 0.5s ease-out;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .hotspot-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 25px;
            text-align: center;
        }
        .hotspot-header .logo {
            width: 70px;
            height: 70px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
            font-size: 32px;
        }
        .hotspot-header .logo img {
            max-width: 50px;
            max-height: 50px;
            border-radius: 50%;
        }
        .hotspot-body { padding: 25px; }
        .form-control {
            border-radius: 12px;
            padding: 14px 16px;
            border: 2px solid #e9ecef;
            font-size: 16px;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.15);
        }
        .btn-primary-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 12px;
            padding: 14px;
            font-size: 16px;
            font-weight: 600;
            width: 100%;
            color: white;
        }
        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
            color: white;
        }
        .btn-mpesa {
            background: linear-gradient(135deg, #4caf50 0%, #2e7d32 100%);
        }
        .btn-mpesa:hover {
            box-shadow: 0 8px 25px rgba(76, 175, 80, 0.4);
        }
        .btn-voucher {
            background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
        }
        .success-card { text-align: center; padding: 35px 25px; }
        .success-icon {
            width: 90px;
            height: 90px;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 45px;
            color: white;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        .info-badge {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
            padding: 8px 14px;
            border-radius: 20px;
            font-size: 13px;
            display: inline-block;
            margin: 4px;
        }
        .package-card {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 14px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .package-card:hover, .package-card.selected {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.05);
        }
        .package-card input[type="radio"] { display: none; }
        .package-card .price {
            font-size: 20px;
            font-weight: 700;
            color: #28a745;
        }
        .expired-notice {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        .stkpush-waiting { animation: breathe 2s ease-in-out infinite; }
        @keyframes breathe {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        .section-divider {
            text-align: center;
            margin: 20px 0;
            position: relative;
        }
        .section-divider::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            width: 100%;
            height: 1px;
            background: #e9ecef;
        }
        .section-divider span {
            background: white;
            padding: 0 15px;
            position: relative;
            color: #6c757d;
            font-size: 13px;
        }
        .footer-text {
            text-align: center;
            padding: 15px;
            color: #6c757d;
            font-size: 12px;
            border-top: 1px solid #eee;
        }
        @media (max-width: 480px) {
            .hotspot-card { border-radius: 16px; }
            .hotspot-header { padding: 20px 15px; }
            .hotspot-body { padding: 18px; }
        }
    </style>
</head>
<body>
    <div class="hotspot-card">
        <?php if ($loginSuccess): ?>
        <!-- SUCCESS: Connected - Auto-redirect to MikroTik for MAC login -->
        <?php 
        // Build MikroTik login URL with MAC as username (for MAC-auth)
        $mikrotikLoginUrl = '';
        if (!empty($linkLoginOnly) && !empty($clientMAC)) {
            // For MAC-based auth, use MAC as username with empty/MAC password
            $loginParams = [
                'username' => $clientMAC,
                'password' => $clientMAC
            ];
            // Add CHAP if available
            if (!empty($chapId) && !empty($chapChallenge)) {
                $loginParams['chap-id'] = $chapId;
                $loginParams['chap-challenge'] = $chapChallenge;
            }
            $mikrotikLoginUrl = $linkLoginOnly . (strpos($linkLoginOnly, '?') !== false ? '&' : '?') . http_build_query($loginParams);
        }
        ?>
        <?php if (!empty($mikrotikLoginUrl)): ?>
        <script>
            // Auto-submit to MikroTik for MAC-based login
            setTimeout(function() {
                window.location.href = '<?= htmlspecialchars($mikrotikLoginUrl) ?>';
            }, 1500);
        </script>
        <?php endif; ?>
        <div class="success-card">
            <div class="success-icon"><i class="bi bi-wifi"></i></div>
            <h4 class="mb-2">Connected!</h4>
            <p class="text-muted mb-3">You are now online</p>
            
            <?php if ($subscription): ?>
            <div class="mb-3">
                <div class="info-badge">
                    <i class="bi bi-speedometer2 me-1"></i>
                    <?= htmlspecialchars($subscription['download_speed'] ?? 'Unlimited') ?>
                </div>
                <?php if (!empty($subscription['expiry_date'])): ?>
                <div class="info-badge">
                    <i class="bi bi-clock me-1"></i>
                    Expires: <?= date('M j, g:i A', strtotime($subscription['expiry_date'])) ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($mikrotikLoginUrl)): ?>
            <p class="small text-muted">Redirecting to network...</p>
            <a href="<?= htmlspecialchars($mikrotikLoginUrl) ?>" class="btn btn-primary-custom">
                <i class="bi bi-arrow-right-circle me-2"></i>Click Here if Not Redirected
            </a>
            <?php elseif (!empty($linkOrig)): ?>
            <a href="<?= htmlspecialchars($linkOrig) ?>" class="btn btn-primary-custom">
                <i class="bi bi-arrow-right-circle me-2"></i>Continue Browsing
            </a>
            <?php else: ?>
            <p class="small text-muted">You can now browse the internet</p>
            <?php endif; ?>
        </div>
        
        <?php elseif ($stkPushSent): ?>
        <!-- WAITING: M-Pesa Payment -->
        <div class="hotspot-header">
            <div class="logo"><?php if ($ispLogo): ?><img src="<?= htmlspecialchars($ispLogo) ?>" alt=""><?php else: ?><i class="bi bi-wifi"></i><?php endif; ?></div>
            <h5 class="mb-1"><?= htmlspecialchars($ispName) ?></h5>
        </div>
        <div class="hotspot-body text-center stkpush-waiting">
            <div class="mb-4">
                <i class="bi bi-phone-vibrate text-success" style="font-size: 60px;"></i>
            </div>
            <h5>Check Your Phone</h5>
            <p class="text-muted">Enter your M-Pesa PIN to complete payment</p>
            <div class="alert alert-success">
                <i class="bi bi-info-circle me-2"></i>
                After payment, refresh this page to connect
            </div>
            <a href="<?= $_SERVER['REQUEST_URI'] ?>" class="btn btn-primary-custom mt-3">
                <i class="bi bi-arrow-clockwise me-2"></i>Refresh
            </a>
            
            <div class="section-divider"><span>or use voucher</span></div>
            
            <form method="POST" class="mt-3">
                <input type="hidden" name="action" value="voucher">
                <input type="hidden" name="mac" value="<?= htmlspecialchars($clientMAC) ?>">
                <div class="input-group">
                    <input type="text" name="voucher_code" class="form-control" placeholder="Enter voucher code" style="text-transform: uppercase;">
                    <button type="submit" class="btn btn-voucher text-white">Apply</button>
                </div>
            </form>
        </div>
        
        <?php elseif ($deviceStatus === 'expired'): ?>
        <!-- EXPIRED: Needs renewal -->
        <div class="hotspot-header">
            <div class="logo"><?php if ($ispLogo): ?><img src="<?= htmlspecialchars($ispLogo) ?>" alt=""><?php else: ?><i class="bi bi-wifi"></i><?php endif; ?></div>
            <h5 class="mb-1"><?= htmlspecialchars($ispName) ?></h5>
        </div>
        <div class="hotspot-body">
            <div class="expired-notice">
                <i class="bi bi-exclamation-circle me-2"></i>
                <strong>Subscription Expired</strong>
                <p class="mb-0 mt-1 small">Your internet access has expired. Renew to continue.</p>
            </div>
            
            <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> py-2 small"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            
            <?php if ($subscription): ?>
            <div class="bg-light rounded-3 p-3 mb-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <strong><?= htmlspecialchars($subscription['package_name']) ?></strong>
                        <div class="small text-muted"><?= htmlspecialchars($subscription['download_speed']) ?> / <?= $subscription['validity_days'] ?> days</div>
                    </div>
                    <div class="price">KES <?= number_format($subscription['package_price'] ?? 0) ?></div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($mpesaEnabled): ?>
            <form method="POST" class="mb-3">
                <input type="hidden" name="action" value="renew">
                <input type="hidden" name="mac" value="<?= htmlspecialchars($clientMAC) ?>">
                <div class="mb-3">
                    <input type="tel" name="phone" class="form-control" placeholder="M-Pesa Phone Number" value="<?= htmlspecialchars($subscription['customer_phone'] ?? '') ?>" required>
                </div>
                <button type="submit" class="btn btn-mpesa btn-primary-custom">
                    <i class="bi bi-phone me-2"></i>Pay with M-Pesa
                </button>
            </form>
            <?php endif; ?>
            
            <div class="section-divider"><span>or use voucher</span></div>
            
            <form method="POST">
                <input type="hidden" name="action" value="voucher">
                <input type="hidden" name="mac" value="<?= htmlspecialchars($clientMAC) ?>">
                <div class="mb-3">
                    <input type="text" name="voucher_code" class="form-control" placeholder="Enter voucher code" style="text-transform: uppercase;" required>
                </div>
                <button type="submit" class="btn btn-voucher btn-primary-custom">
                    <i class="bi bi-ticket me-2"></i>Redeem Voucher
                </button>
            </form>
        </div>
        
        <?php else: ?>
        <!-- NEW DEVICE: Registration -->
        <div class="hotspot-header">
            <div class="logo"><?php if ($ispLogo): ?><img src="<?= htmlspecialchars($ispLogo) ?>" alt=""><?php else: ?><i class="bi bi-wifi"></i><?php endif; ?></div>
            <h5 class="mb-1"><?= htmlspecialchars($ispName) ?></h5>
            <p class="mb-0 small opacity-75"><?= htmlspecialchars($hotspotWelcome) ?></p>
        </div>
        <div class="hotspot-body">
            <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> py-2 small"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            
            <?php if (empty($clientMAC)): ?>
            <div class="alert alert-warning d-flex align-items-start mb-3">
                <i class="bi bi-wifi-off me-2 mt-1" style="font-size: 1.2em;"></i>
                <div>
                    <strong>Device not detected</strong>
                    <p class="mb-0 small mt-1">Please connect to the <strong><?= htmlspecialchars($ispName) ?></strong> WiFi network first, then this page will load automatically.</p>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($packages)): ?>
            <h6 class="mb-3">Available Packages</h6>
            <?php foreach ($packages as $i => $pkg): ?>
            <div class="package-card <?= $i === 0 && !empty($clientMAC) ? 'selected' : '' ?>" <?= empty($clientMAC) ? 'style="opacity: 0.7;"' : '' ?>>
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <strong><?= htmlspecialchars($pkg['name']) ?></strong>
                        <div class="small text-muted">
                            <?= htmlspecialchars($pkg['download_speed'] ?? '') ?> • <?= $pkg['validity_days'] ?> day<?= $pkg['validity_days'] > 1 ? 's' : '' ?>
                            <?php if (!empty($pkg['data_quota_mb'])): ?> • <?= number_format($pkg['data_quota_mb'] / 1024, 1) ?>GB<?php endif; ?>
                        </div>
                    </div>
                    <div class="price">KES <?= number_format($pkg['price']) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php if (!empty($clientMAC)): ?>
            <form method="POST" id="registerForm" class="mt-3">
                <input type="hidden" name="action" value="register">
                <input type="hidden" name="mac" value="<?= htmlspecialchars($clientMAC) ?>">
                
                <?php foreach ($packages as $i => $pkg): ?>
                <input type="hidden" class="package-radio" name="package_id_<?= $pkg['id'] ?>" value="<?= $pkg['id'] ?>">
                <?php endforeach; ?>
                <select name="package_id" class="form-control mb-3" required>
                    <?php foreach ($packages as $pkg): ?>
                    <option value="<?= $pkg['id'] ?>">
                        <?= htmlspecialchars($pkg['name']) ?> - KES <?= number_format($pkg['price']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                
                <div class="mb-3">
                    <input type="tel" name="phone" class="form-control" placeholder="M-Pesa Phone Number (e.g., 0712345678)" required>
                </div>
                <button type="submit" class="btn btn-mpesa btn-primary-custom">
                    <i class="bi bi-phone me-2"></i>Pay with M-Pesa
                </button>
            </form>
            <?php endif; ?>
            <?php else: ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i>
                No packages available for this hotspot.
            </div>
            <?php endif; ?>
            
            <?php if (!empty($clientMAC)): ?>
            <div class="section-divider"><span>or use voucher</span></div>
            
            <form method="POST">
                <input type="hidden" name="action" value="voucher">
                <input type="hidden" name="mac" value="<?= htmlspecialchars($clientMAC) ?>">
                <div class="mb-3">
                    <input type="text" name="voucher_code" class="form-control" placeholder="Enter voucher code" style="text-transform: uppercase;" required>
                </div>
                <button type="submit" class="btn btn-voucher btn-primary-custom">
                    <i class="bi bi-ticket me-2"></i>Redeem Voucher
                </button>
            </form>
            
            <div class="section-divider"><span>already have a subscription?</span></div>
            
            <div class="bg-light rounded-3 p-3">
                <h6 class="mb-2"><i class="bi bi-phone-fill me-2"></i>Add This Device</h6>
                <p class="small text-muted mb-3">If you already have an active subscription and want to add this device, enter your phone number below.</p>
                <form method="POST">
                    <input type="hidden" name="action" value="add_device">
                    <input type="hidden" name="new_mac" value="<?= htmlspecialchars($clientMAC) ?>">
                    <div class="mb-2">
                        <input type="tel" name="phone" class="form-control form-control-sm" placeholder="Registered phone number" required>
                    </div>
                    <div class="mb-2">
                        <input type="text" name="device_name" class="form-control form-control-sm" placeholder="Device name (optional)">
                    </div>
                    <button type="submit" class="btn btn-outline-primary btn-sm w-100">
                        <i class="bi bi-plus-circle me-1"></i>Add This Device
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <div class="footer-text">
            <i class="bi bi-shield-check me-1"></i>
            Powered by <?= htmlspecialchars($ispName) ?>
            <?php if (!empty($mpesaPaybill)): ?>
            <br><small>Paybill: <?= htmlspecialchars($mpesaPaybill) ?></small>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
    document.querySelectorAll('.package-card').forEach(card => {
        card.addEventListener('click', function() {
            document.querySelectorAll('.package-card').forEach(c => c.classList.remove('selected'));
            this.classList.add('selected');
            this.querySelector('input[type="radio"]').checked = true;
        });
    });
    </script>
</body>
</html>
