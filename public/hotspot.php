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
    // Ignore MikroTik variable placeholders that weren't replaced
    if (preg_match('/^[0-9a-fA-F]{2}([:\-.])[0-9a-fA-F]{2}/', $pathMAC)) {
        $clientMAC = $pathMAC;
    }
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
                            $_SESSION['pending_subscription_id'] = $result['subscription_id'];
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
                            $_SESSION['pending_subscription_id'] = $subscription['id'];
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

// Helper: format validity
function formatValidity($days, $pkg = null) {
    if (isset($pkg['validity_hours']) && $pkg['validity_hours'] > 0 && $pkg['validity_hours'] < 24) {
        return $pkg['validity_hours'] . ' hour' . ($pkg['validity_hours'] > 1 ? 's' : '');
    }
    if ($days == 1) return '24 Hours';
    if ($days == 7) return '7 Days';
    if ($days == 30) return '30 Days';
    return $days . ' day' . ($days > 1 ? 's' : '');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($ispName) ?> - WiFi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background: #0f172a;
            min-height: 100vh;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            color: #1e293b;
            overflow-x: hidden;
        }
        .page-bg {
            min-height: 100vh;
            background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%);
            padding-bottom: 30px;
        }
        .hero {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #a855f7 100%);
            padding: 40px 20px 60px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .hero::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 60%);
            animation: shimmer 8s ease-in-out infinite;
        }
        @keyframes shimmer {
            0%, 100% { transform: translate(0, 0); }
            50% { transform: translate(10%, 10%); }
        }
        .hero-content { position: relative; z-index: 1; }
        .wifi-icon {
            width: 80px;
            height: 80px;
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-size: 36px;
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
        }
        .wifi-icon img {
            max-width: 56px;
            max-height: 56px;
            border-radius: 16px;
        }
        .hero h1 {
            color: white;
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 4px;
            letter-spacing: -0.02em;
        }
        .hero p {
            color: rgba(255,255,255,0.8);
            font-size: 14px;
            font-weight: 400;
        }
        .main-content {
            max-width: 440px;
            margin: -30px auto 0;
            padding: 0 16px;
            position: relative;
            z-index: 2;
        }
        .card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.12);
            margin-bottom: 16px;
            overflow: hidden;
        }
        .card-body { padding: 20px; }
        .alert-banner {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 14px 16px;
            border-radius: 14px;
            font-size: 13px;
            line-height: 1.5;
        }
        .alert-warning-custom {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
        }
        .alert-danger-custom {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .alert-success-custom {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        .alert-info-custom {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }
        .alert-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 18px;
        }
        .section-title {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .section-title i {
            color: #6366f1;
        }

        /* Package Cards */
        .pkg-card {
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 12px;
            transition: all 0.25s ease;
            cursor: pointer;
            position: relative;
        }
        .pkg-card:hover {
            border-color: #6366f1;
            box-shadow: 0 4px 16px rgba(99, 102, 241, 0.15);
        }
        .pkg-card.selected {
            border-color: #6366f1;
            background: linear-gradient(135deg, #eef2ff 0%, #f5f3ff 100%);
            box-shadow: 0 4px 16px rgba(99, 102, 241, 0.2);
        }
        .pkg-card input[type="radio"] { display: none; }
        .pkg-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        .pkg-name {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
        }
        .pkg-price {
            font-size: 22px;
            font-weight: 800;
            color: #059669;
            white-space: nowrap;
        }
        .pkg-price small {
            font-size: 12px;
            font-weight: 500;
            color: #6b7280;
        }
        .pkg-details {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        .pkg-tag {
            background: #f1f5f9;
            color: #475569;
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .pkg-tag i { font-size: 11px; color: #6366f1; }
        .pkg-card.selected .pkg-tag {
            background: rgba(99, 102, 241, 0.12);
            color: #4338ca;
        }
        .pkg-card.selected .pkg-tag i { color: #4338ca; }
        .pkg-multi-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: white;
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        .pkg-multi-badge i { font-size: 12px; }
        .pkg-buy-btn {
            display: block;
            width: 100%;
            margin-top: 12px;
            padding: 10px;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
            text-decoration: none;
        }
        .pkg-buy-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.35);
        }
        .pkg-buy-btn.mpesa {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
        }
        .pkg-buy-btn.mpesa:hover {
            box-shadow: 0 6px 20px rgba(5, 150, 105, 0.35);
        }
        .pkg-card-faded { opacity: 0.6; pointer-events: none; }

        /* Forms */
        .form-input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 14px;
            font-size: 16px;
            font-family: inherit;
            color: #0f172a;
            background: #f8fafc;
            transition: all 0.2s;
            outline: none;
        }
        .form-input:focus {
            border-color: #6366f1;
            background: white;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }
        .form-input::placeholder { color: #94a3b8; }
        .btn-main {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 14px;
            font-size: 16px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            color: white;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-main:hover { transform: translateY(-1px); }
        .btn-mpesa {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
        }
        .btn-mpesa:hover { box-shadow: 0 8px 24px rgba(5, 150, 105, 0.3); }
        .btn-voucher {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }
        .btn-voucher:hover { box-shadow: 0 8px 24px rgba(245, 158, 11, 0.3); }
        .btn-primary {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
        }
        .btn-primary:hover { box-shadow: 0 8px 24px rgba(99, 102, 241, 0.3); }
        .btn-outline {
            background: transparent;
            border: 2px solid #e2e8f0;
            color: #475569;
            padding: 13px;
        }
        .btn-outline:hover { border-color: #6366f1; color: #6366f1; }

        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 20px 0;
            color: #94a3b8;
            font-size: 13px;
            font-weight: 500;
        }
        .divider::before, .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e2e8f0;
        }

        /* Success State */
        .success-state { text-align: center; padding: 30px 20px; }
        .success-ring {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #059669 0%, #10b981 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 48px;
            color: white;
            animation: popIn 0.5s ease-out;
        }
        @keyframes popIn {
            0% { transform: scale(0); }
            70% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        .info-pill {
            background: #f1f5f9;
            padding: 8px 16px;
            border-radius: 100px;
            font-size: 13px;
            color: #475569;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin: 4px;
        }
        .info-pill i { color: #6366f1; }

        /* Expired State */
        .expired-banner {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            padding: 20px;
            border-radius: 16px;
            text-align: center;
        }
        .expired-banner h3 {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .expired-banner p {
            font-size: 13px;
            opacity: 0.9;
            margin: 0;
        }

        /* STK Push Waiting */
        .stk-waiting {
            text-align: center;
            padding: 20px;
        }
        .phone-anim {
            font-size: 64px;
            color: #059669;
            animation: vibrate 1.5s ease-in-out infinite;
        }
        @keyframes vibrate {
            0%, 100% { transform: rotate(0deg); }
            10% { transform: rotate(-5deg); }
            20% { transform: rotate(5deg); }
            30% { transform: rotate(-3deg); }
            40% { transform: rotate(3deg); }
            50% { transform: rotate(0deg); }
        }
        @keyframes progressPulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        /* Payment Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
            z-index: 100;
            align-items: flex-end;
            justify-content: center;
        }
        .modal-overlay.active {
            display: flex;
        }
        .modal-sheet {
            background: white;
            border-radius: 24px 24px 0 0;
            width: 100%;
            max-width: 440px;
            padding: 24px;
            animation: slideUp 0.3s ease-out;
            max-height: 90vh;
            overflow-y: auto;
        }
        @keyframes slideUp {
            from { transform: translateY(100%); }
            to { transform: translateY(0); }
        }
        .modal-handle {
            width: 40px;
            height: 4px;
            background: #d1d5db;
            border-radius: 2px;
            margin: 0 auto 20px;
        }
        .modal-pkg-summary {
            background: #f8fafc;
            border-radius: 14px;
            padding: 16px;
            margin-bottom: 20px;
            border: 1px solid #e2e8f0;
        }

        /* Footer */
        .footer {
            text-align: center;
            padding: 20px;
            color: rgba(255,255,255,0.4);
            font-size: 12px;
        }
        .footer strong { color: rgba(255,255,255,0.6); }

        /* Add device section */
        .add-device-section {
            background: #f8fafc;
            border-radius: 14px;
            padding: 16px;
            border: 1px solid #e2e8f0;
        }

        @media (max-width: 380px) {
            .hero { padding: 30px 16px 50px; }
            .hero h1 { font-size: 20px; }
            .pkg-price { font-size: 20px; }
        }
    </style>
</head>
<body>
    <div class="page-bg">
        <?php if ($loginSuccess): ?>
        <!-- SUCCESS STATE -->
        <?php 
        $mikrotikLoginUrl = '';
        $mikrotikLoginUser = '';
        $mikrotikLoginPass = '';
        $mikrotikDst = $linkOrig ?: '';
        $useChap = false;
        if (!empty($linkLoginOnly) && $subscription) {
            $mikrotikLoginUser = $subscription['username'] ?? $clientMAC;
            $plainPass = $subscription['password'] ?? $clientMAC;
            $mikrotikLoginUrl = $linkLoginOnly;
            
            if (!empty($chapId) && !empty($chapChallenge)) {
                $chapIdBin = stripcslashes($chapId);
                $chapChallengeBin = stripcslashes($chapChallenge);
                $chapHash = md5($chapIdBin . $plainPass . $chapChallengeBin);
                $mikrotikLoginPass = $chapHash;
                $useChap = true;
            } else {
                $mikrotikLoginPass = $plainPass;
            }
        }
        ?>
        <?php
        $subMaxDevices = (int)($subscription['max_devices'] ?? 1);
        $subDevices = [];
        $subDeviceCount = 1;
        if ($subscription && $subMaxDevices > 1) {
            $subDevices = $radiusBilling->getSubscriptionDevices($subscription['id']);
            $subDeviceCount = count($subDevices);
        }
        $canAddMore = $subMaxDevices > 1 && $subDeviceCount < $subMaxDevices;
        ?>
        <?php if (!empty($mikrotikLoginUrl)): ?>
        <form id="mikrotikLoginForm" method="POST" action="<?= htmlspecialchars($mikrotikLoginUrl) ?>" style="display:none;">
            <input type="hidden" name="username" value="<?= htmlspecialchars($mikrotikLoginUser) ?>">
            <input type="hidden" name="password" value="<?= htmlspecialchars($mikrotikLoginPass) ?>">
            <?php if (!empty($mikrotikDst)): ?>
            <input type="hidden" name="dst" value="<?= htmlspecialchars($mikrotikDst) ?>">
            <?php endif; ?>
        </form>
        <script>
            setTimeout(function() {
                document.getElementById('mikrotikLoginForm').submit();
            }, <?= $canAddMore ? '4000' : '2000' ?>);
        </script>
        <?php endif; ?>
        
        <div class="hero" style="padding-bottom: 40px;">
            <div class="hero-content">
                <div class="wifi-icon"><?php if ($ispLogo): ?><img src="<?= htmlspecialchars($ispLogo) ?>" alt=""><?php else: ?><i class="bi bi-wifi"></i><?php endif; ?></div>
                <h1><?= htmlspecialchars($ispName) ?></h1>
            </div>
        </div>
        <div class="main-content">
            <div class="card">
                <div class="success-state">
                    <div class="success-ring"><i class="bi bi-wifi"></i></div>
                    <h2 style="font-size: 24px; font-weight: 800; margin-bottom: 6px;">You're Connected!</h2>
                    <p style="color: #64748b; margin-bottom: 16px;">Enjoy browsing the internet</p>
                    
                    <?php if ($subscription): ?>
                    <div style="margin-bottom: 20px;">
                        <div class="info-pill"><i class="bi bi-speedometer2"></i> <?= htmlspecialchars($subscription['download_speed'] ?? 'Unlimited') ?></div>
                        <?php if (!empty($subscription['expiry_date'])): ?>
                        <div class="info-pill"><i class="bi bi-clock"></i> <?= date('M j, g:i A', strtotime($subscription['expiry_date'])) ?></div>
                        <?php endif; ?>
                        <?php if ($subMaxDevices > 1): ?>
                        <div class="info-pill"><i class="bi bi-people"></i> <?= $subDeviceCount ?>/<?= $subMaxDevices ?> devices</div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($mikrotikLoginUrl)): ?>
                    <p style="color: #94a3b8; font-size: 13px; margin-bottom: 12px;">Redirecting to network...</p>
                    <button type="button" onclick="document.getElementById('mikrotikLoginForm').submit();" class="btn-main btn-primary">
                        <i class="bi bi-arrow-right-circle"></i> Click Here if Not Redirected
                    </button>
                    <?php elseif (!empty($linkOrig)): ?>
                    <a href="<?= htmlspecialchars($linkOrig) ?>" class="btn-main btn-primary" style="text-decoration: none;">
                        <i class="bi bi-globe"></i> Continue Browsing
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($canAddMore): ?>
            <div class="card">
                <div class="card-body">
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 14px;">
                        <div style="width: 44px; height: 44px; background: linear-gradient(135deg, #6366f1, #8b5cf6); border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <i class="bi bi-people-fill" style="color: white; font-size: 20px;"></i>
                        </div>
                        <div>
                            <div style="font-size: 15px; font-weight: 700; color: #0f172a;">Add More Devices</div>
                            <div style="font-size: 12px; color: #64748b;">Your plan supports <?= $subMaxDevices ?> devices (<?= $subMaxDevices - $subDeviceCount ?> slots left)</div>
                        </div>
                    </div>
                    
                    <div style="background: linear-gradient(135deg, #eef2ff, #f5f3ff); border-radius: 12px; padding: 12px 14px; margin-bottom: 14px; border: 1px solid #c7d2fe;">
                        <p style="font-size: 13px; color: #4338ca; margin: 0;">
                            <i class="bi bi-info-circle"></i>
                            Share your phone number <strong><?= htmlspecialchars($subscription['customer_phone'] ?? '') ?></strong> with others so they can connect their devices to your plan.
                        </p>
                    </div>
                    
                    <?php if (!empty($subDevices)): ?>
                    <div style="margin-bottom: 14px;">
                        <div style="font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 8px;">Connected Devices:</div>
                        <?php foreach ($subDevices as $dev): ?>
                        <div style="display: flex; align-items: center; gap: 10px; padding: 8px 12px; background: #f8fafc; border-radius: 10px; margin-bottom: 6px; border: 1px solid #e2e8f0;">
                            <i class="bi bi-phone" style="color: #6366f1;"></i>
                            <div style="flex: 1;">
                                <div style="font-size: 13px; font-weight: 500; color: #0f172a;"><?= htmlspecialchars($dev['device_name'] ?: 'Device') ?></div>
                                <div style="font-size: 11px; color: #94a3b8;"><?= htmlspecialchars($dev['mac_address']) ?></div>
                            </div>
                            <?php if ($dev['mac_address'] === strtoupper(preg_replace('/[^A-Fa-f0-9:]/', '', $clientMAC))): ?>
                            <span style="font-size: 11px; background: #d1fae5; color: #065f46; padding: 2px 8px; border-radius: 6px;">This device</span>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                    <p style="font-size: 12px; color: #64748b; margin-bottom: 0;">
                        <i class="bi bi-lightbulb" style="color: #f59e0b;"></i>
                        Other devices can connect by visiting this WiFi page and tapping <strong>"Already have a plan?"</strong>
                    </p>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <?php elseif ($stkPushSent): ?>
        <!-- STK PUSH WAITING - Auto-polls for payment confirmation -->
        <?php $pendingSubId = $_SESSION['pending_subscription_id'] ?? 0; ?>
        <div class="hero" style="padding-bottom: 40px;">
            <div class="hero-content">
                <div class="wifi-icon"><?php if ($ispLogo): ?><img src="<?= htmlspecialchars($ispLogo) ?>" alt=""><?php else: ?><i class="bi bi-wifi"></i><?php endif; ?></div>
                <h1><?= htmlspecialchars($ispName) ?></h1>
            </div>
        </div>
        <div class="main-content">
            <div class="card" id="stkWaitingCard">
                <div class="stk-waiting">
                    <div class="phone-anim"><i class="bi bi-phone-vibrate"></i></div>
                    <h2 style="font-size: 22px; font-weight: 700; margin: 16px 0 8px;">Check Your Phone</h2>
                    <p style="color: #64748b; font-size: 14px; margin-bottom: 20px;">Enter your M-Pesa PIN to complete payment</p>
                    
                    <div class="alert-banner alert-info-custom" style="margin-bottom: 20px;" id="stkStatusBanner">
                        <div class="alert-icon" style="background: #bfdbfe;"><i class="bi bi-hourglass-split" id="stkStatusIcon"></i></div>
                        <div id="stkStatusText">Waiting for payment confirmation... <span id="pollTimer"></span></div>
                    </div>
                    
                    <div id="stkProgressBar" style="background: #e2e8f0; border-radius: 100px; height: 6px; margin-bottom: 20px; overflow: hidden;">
                        <div id="stkProgressFill" style="background: linear-gradient(90deg, #6366f1, #8b5cf6); height: 100%; width: 0%; border-radius: 100px; transition: width 3s linear;"></div>
                    </div>
                    
                    <a href="<?= $_SERVER['REQUEST_URI'] ?>" class="btn-main btn-primary" style="text-decoration: none; display: none;" id="stkRefreshBtn">
                        <i class="bi bi-arrow-clockwise"></i> Refresh Page
                    </a>
                    
                    <div class="divider">or use voucher</div>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="voucher">
                        <input type="hidden" name="mac" value="<?= htmlspecialchars($clientMAC) ?>">
                        <div style="display: flex; gap: 8px;">
                            <input type="text" name="voucher_code" class="form-input" placeholder="Voucher code" style="text-transform: uppercase; flex: 1;">
                            <button type="submit" class="btn-main btn-voucher" style="width: auto; padding: 14px 20px;">Apply</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card" id="stkSuccessCard" style="display: none;">
                <div class="success-state">
                    <div class="success-ring"><i class="bi bi-check-lg"></i></div>
                    <h2 style="font-size: 24px; font-weight: 800; margin-bottom: 6px;">Payment Received!</h2>
                    <p style="color: #64748b; margin-bottom: 16px;" id="stkSuccessPkg"></p>
                    <div style="margin-bottom: 20px;" id="stkSuccessInfo"></div>
                    <p style="color: #94a3b8; font-size: 13px; margin-bottom: 12px;">Connecting you to the internet...</p>
                    <div style="background: #e2e8f0; border-radius: 100px; height: 4px; overflow: hidden;">
                        <div style="background: linear-gradient(90deg, #059669, #10b981); height: 100%; width: 100%; animation: progressPulse 1.5s ease-in-out infinite;"></div>
                    </div>
                </div>
            </div>
        </div>
        <script>
        (function() {
            var subId = <?= (int)$pendingSubId ?>;
            var mac = '<?= htmlspecialchars($clientMAC) ?>';
            var pollInterval = 3000;
            var maxPolls = 40; // 2 minutes max
            var pollCount = 0;
            var confirmed = false;
            
            function updateProgress() {
                var pct = Math.min((pollCount / maxPolls) * 100, 100);
                var fill = document.getElementById('stkProgressFill');
                if (fill) fill.style.width = pct + '%';
            }
            
            function showSuccess(data) {
                confirmed = true;
                document.getElementById('stkWaitingCard').style.display = 'none';
                var successCard = document.getElementById('stkSuccessCard');
                successCard.style.display = 'block';
                
                if (data.package_name) {
                    document.getElementById('stkSuccessPkg').textContent = data.package_name + ' activated!';
                }
                
                var infoHtml = '';
                if (data.download_speed) {
                    infoHtml += '<span class="info-pill"><i class="bi bi-speedometer2"></i> ' + data.download_speed + '</span>';
                }
                if (data.expiry_date) {
                    infoHtml += '<span class="info-pill"><i class="bi bi-clock"></i> ' + new Date(data.expiry_date).toLocaleDateString('en-GB', {day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit'}) + '</span>';
                }
                if (data.max_devices > 1) {
                    infoHtml += '<span class="info-pill"><i class="bi bi-people"></i> ' + data.max_devices + ' devices</span>';
                }
                document.getElementById('stkSuccessInfo').innerHTML = infoHtml;
                
                setTimeout(function() {
                    // Redirect to HTTP URL so MikroTik intercepts and generates fresh CHAP params
                    // MikroTik will redirect back to captive portal with new chapId/chapChallenge
                    var dst = '<?= htmlspecialchars($linkOrig ?: "http://detectportal.firefox.com/") ?>';
                    if (dst.indexOf('http://') === 0) {
                        window.location.href = dst;
                    } else {
                        // Fallback: reload page (CHAP may be stale but will still show success)
                        window.location.href = '<?= $_SERVER['REQUEST_URI'] ?>';
                    }
                }, 3000);
            }
            
            function showTimeout() {
                var banner = document.getElementById('stkStatusBanner');
                banner.className = 'alert-banner alert-warning-custom';
                banner.querySelector('.alert-icon').style.background = '#fde68a';
                document.getElementById('stkStatusIcon').className = 'bi bi-exclamation-triangle';
                document.getElementById('stkStatusText').innerHTML = 'Payment not yet confirmed. Tap <strong>Refresh</strong> to check again.';
                document.getElementById('stkRefreshBtn').style.display = 'flex';
                document.getElementById('stkProgressBar').style.display = 'none';
            }
            
            function pollStatus() {
                if (confirmed) return;
                pollCount++;
                updateProgress();
                
                var url = '/api/hotspot-payment-status.php?';
                if (subId) url += 'sid=' + subId + '&';
                if (mac) url += 'mac=' + encodeURIComponent(mac);
                
                fetch(url)
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.activated) {
                            showSuccess(data);
                        } else if (data.status === 'error' && data.message === 'Unauthorized') {
                            showTimeout();
                        } else if (data.status === 'not_found' && pollCount > 10) {
                            showTimeout();
                        } else if (pollCount >= maxPolls) {
                            showTimeout();
                        } else {
                            setTimeout(pollStatus, pollInterval);
                        }
                    })
                    .catch(function() {
                        if (pollCount >= maxPolls) {
                            showTimeout();
                        } else {
                            setTimeout(pollStatus, pollInterval);
                        }
                    });
            }
            
            setTimeout(pollStatus, pollInterval);
        })();
        </script>

        <?php elseif ($deviceStatus === 'expired'): ?>
        <!-- EXPIRED STATE -->
        <div class="hero" style="padding-bottom: 40px;">
            <div class="hero-content">
                <div class="wifi-icon"><?php if ($ispLogo): ?><img src="<?= htmlspecialchars($ispLogo) ?>" alt=""><?php else: ?><i class="bi bi-wifi"></i><?php endif; ?></div>
                <h1><?= htmlspecialchars($ispName) ?></h1>
            </div>
        </div>
        <div class="main-content">
            <?php if ($message): ?>
            <div class="card"><div class="card-body">
                <div class="alert-banner alert-<?= $messageType === 'danger' ? 'danger' : ($messageType === 'success' ? 'success' : 'info') ?>-custom">
                    <div class="alert-icon" style="background: <?= $messageType === 'danger' ? '#fecaca' : ($messageType === 'success' ? '#a7f3d0' : '#bfdbfe') ?>;">
                        <i class="bi bi-<?= $messageType === 'danger' ? 'x-circle' : ($messageType === 'success' ? 'check-circle' : 'info-circle') ?>"></i>
                    </div>
                    <div><?= htmlspecialchars($message) ?></div>
                </div>
            </div></div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-body">
                    <div class="expired-banner" style="margin-bottom: 20px;">
                        <i class="bi bi-clock-history" style="font-size: 32px; margin-bottom: 8px; display: block;"></i>
                        <h3>Subscription Expired</h3>
                        <p>Renew your plan to get back online</p>
                    </div>
                    
                    <?php if ($subscription): ?>
                    <div style="background: #f8fafc; border-radius: 14px; padding: 14px; margin-bottom: 16px; border: 1px solid #e2e8f0;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <div style="font-weight: 600; color: #0f172a;"><?= htmlspecialchars($subscription['package_name']) ?></div>
                                <div style="font-size: 12px; color: #64748b;"><?= htmlspecialchars($subscription['download_speed']) ?> / <?= $subscription['validity_days'] ?> days</div>
                            </div>
                            <div class="pkg-price">KES <?= number_format($subscription['package_price'] ?? 0) ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($mpesaEnabled): ?>
                    <form method="POST" style="margin-bottom: 16px;">
                        <input type="hidden" name="action" value="renew">
                        <input type="hidden" name="mac" value="<?= htmlspecialchars($clientMAC) ?>">
                        <input type="tel" name="phone" class="form-input" placeholder="M-Pesa Phone (e.g., 0712345678)" value="<?= htmlspecialchars($subscription['customer_phone'] ?? '') ?>" required style="margin-bottom: 12px;">
                        <button type="submit" class="btn-main btn-mpesa">
                            <i class="bi bi-phone"></i> Renew with M-Pesa
                        </button>
                    </form>
                    <?php endif; ?>
                    
                    <div class="divider">or use voucher</div>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="voucher">
                        <input type="hidden" name="mac" value="<?= htmlspecialchars($clientMAC) ?>">
                        <input type="text" name="voucher_code" class="form-input" placeholder="Enter voucher code" style="text-transform: uppercase; margin-bottom: 12px;" required>
                        <button type="submit" class="btn-main btn-voucher">
                            <i class="bi bi-ticket-perforated"></i> Redeem Voucher
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <?php else: ?>
        <!-- NEW DEVICE / REGISTRATION -->
        <div class="hero">
            <div class="hero-content">
                <div class="wifi-icon"><?php if ($ispLogo): ?><img src="<?= htmlspecialchars($ispLogo) ?>" alt=""><?php else: ?><i class="bi bi-wifi"></i><?php endif; ?></div>
                <h1><?= htmlspecialchars($ispName) ?></h1>
                <p><?= htmlspecialchars($hotspotWelcome) ?></p>
            </div>
        </div>
        <div class="main-content">
            <?php if ($message): ?>
            <div class="card"><div class="card-body">
                <div class="alert-banner alert-<?= $messageType === 'danger' ? 'danger' : ($messageType === 'success' ? 'success' : ($messageType === 'warning' ? 'warning' : 'info')) ?>-custom">
                    <div class="alert-icon" style="background: <?= $messageType === 'danger' ? '#fecaca' : ($messageType === 'success' ? '#a7f3d0' : ($messageType === 'warning' ? '#fde68a' : '#bfdbfe')) ?>;">
                        <i class="bi bi-<?= $messageType === 'danger' ? 'x-circle' : ($messageType === 'success' ? 'check-circle' : 'info-circle') ?>"></i>
                    </div>
                    <div><?= htmlspecialchars($message) ?></div>
                </div>
            </div></div>
            <?php endif; ?>
            
            <?php if (empty($clientMAC)): ?>
            <div class="card"><div class="card-body">
                <div class="alert-banner alert-warning-custom">
                    <div class="alert-icon" style="background: #fde68a;"><i class="bi bi-wifi-off"></i></div>
                    <div>
                        <strong>Device not detected</strong><br>
                        <span style="font-size: 12px;">Connect to <strong><?= htmlspecialchars($ispName) ?></strong> WiFi first, then this page loads automatically.</span>
                    </div>
                </div>
            </div></div>
            <?php endif; ?>
            
            <?php if (!empty($packages)): ?>
            <div class="card">
                <div class="card-body">
                    <div class="section-title">
                        <i class="bi bi-box-seam"></i>
                        <?= !empty($clientMAC) ? 'Choose a Plan' : 'Available Plans' ?>
                    </div>
                    
                    <?php foreach ($packages as $i => $pkg): ?>
                    <?php
                        $validityText = formatValidity($pkg['validity_days'], $pkg);
                        $hasData = !empty($pkg['data_quota_mb']);
                        $dataText = $hasData ? number_format($pkg['data_quota_mb'] / 1024, 1) . ' GB' : 'Unlimited';
                        $maxDevices = (int)($pkg['max_devices'] ?? 1);
                    ?>
                    <div class="pkg-card <?= empty($clientMAC) ? 'pkg-card-faded' : '' ?>" <?= !empty($clientMAC) ? 'onclick="openPayment(' . $pkg['id'] . ', \'' . htmlspecialchars(addslashes($pkg['name'])) . '\', ' . $pkg['price'] . ', \'' . htmlspecialchars(addslashes($pkg['download_speed'] ?? '')) . '\', \'' . htmlspecialchars($validityText) . '\', \'' . htmlspecialchars($dataText) . '\', ' . $maxDevices . ')"' : '' ?>>
                        <?php if ($maxDevices > 1): ?>
                        <div class="pkg-multi-badge"><i class="bi bi-people-fill"></i> <?= $maxDevices ?> Devices</div>
                        <?php endif; ?>
                        <div class="pkg-top">
                            <div class="pkg-name"><?= htmlspecialchars($pkg['name']) ?></div>
                            <div class="pkg-price">KES <?= number_format($pkg['price']) ?></div>
                        </div>
                        <div class="pkg-details">
                            <span class="pkg-tag"><i class="bi bi-speedometer2"></i> <?= htmlspecialchars($pkg['download_speed'] ?? 'N/A') ?></span>
                            <span class="pkg-tag"><i class="bi bi-clock"></i> <?= $validityText ?></span>
                            <?php if ($hasData): ?>
                            <span class="pkg-tag"><i class="bi bi-database"></i> <?= $dataText ?></span>
                            <?php endif; ?>
                            <?php if ($maxDevices > 1): ?>
                            <span class="pkg-tag"><i class="bi bi-phone"></i> <?= $maxDevices ?> devices</span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($clientMAC)): ?>
                        <button type="button" class="pkg-buy-btn">
                            <i class="bi bi-cart-check"></i> Buy Now - KES <?= number_format($pkg['price']) ?>
                        </button>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php else: ?>
            <div class="card"><div class="card-body">
                <div class="alert-banner alert-info-custom">
                    <div class="alert-icon" style="background: #bfdbfe;"><i class="bi bi-info-circle"></i></div>
                    <div>No packages available for this hotspot.</div>
                </div>
            </div></div>
            <?php endif; ?>
            
            <?php if (!empty($clientMAC)): ?>
            <div class="card">
                <div class="card-body">
                    <div class="section-title"><i class="bi bi-ticket-perforated"></i> Have a Voucher?</div>
                    <form method="POST">
                        <input type="hidden" name="action" value="voucher">
                        <input type="hidden" name="mac" value="<?= htmlspecialchars($clientMAC) ?>">
                        <input type="text" name="voucher_code" class="form-input" placeholder="Enter voucher code" style="text-transform: uppercase; margin-bottom: 12px;" required>
                        <button type="submit" class="btn-main btn-voucher">
                            <i class="bi bi-ticket-perforated"></i> Redeem Voucher
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <div class="add-device-section">
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                            <div style="width: 44px; height: 44px; background: linear-gradient(135deg, #6366f1, #8b5cf6); border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                <i class="bi bi-phone-fill" style="color: white; font-size: 20px;"></i>
                            </div>
                            <div>
                                <div style="font-size: 15px; font-weight: 700; color: #0f172a;">Already have a plan?</div>
                                <div style="font-size: 12px; color: #64748b;">Add this device to your existing subscription</div>
                            </div>
                        </div>
                        <p style="font-size: 12px; color: #64748b; margin-bottom: 12px; background: #f1f5f9; padding: 10px 12px; border-radius: 10px;">
                            <i class="bi bi-info-circle" style="color: #6366f1;"></i>
                            If someone shared their plan with you, enter their registered phone number below to connect this device.
                        </p>
                        <form method="POST">
                            <input type="hidden" name="action" value="add_device">
                            <input type="hidden" name="new_mac" value="<?= htmlspecialchars($clientMAC) ?>">
                            <input type="tel" name="phone" class="form-input" placeholder="Registered phone number" required style="margin-bottom: 8px; padding: 12px 14px; font-size: 14px;">
                            <input type="text" name="device_name" class="form-input" placeholder="Device name (e.g., My Phone)" style="margin-bottom: 10px; padding: 12px 14px; font-size: 14px;">
                            <button type="submit" class="btn-main btn-primary" style="font-size: 14px; padding: 12px;">
                                <i class="bi bi-plus-circle"></i> Add This Device
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <div class="footer">
            <i class="bi bi-shield-check"></i>
            Powered by <strong><?= htmlspecialchars($ispName) ?></strong>
            <?php if (!empty($mpesaPaybill)): ?>
            <br>Paybill: <?= htmlspecialchars($mpesaPaybill) ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($clientMAC)): ?>
    <!-- Payment Bottom Sheet Modal -->
    <div class="modal-overlay" id="paymentModal">
        <div class="modal-sheet">
            <div class="modal-handle"></div>
            <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 16px;">Complete Purchase</h3>
            
            <div class="modal-pkg-summary">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <div id="modalPkgName" style="font-weight: 600; color: #0f172a;"></div>
                        <div id="modalPkgDetails" style="font-size: 12px; color: #64748b; margin-top: 4px;"></div>
                    </div>
                    <div id="modalPkgPrice" class="pkg-price"></div>
                </div>
                <div id="modalMultiDevice" style="display: none; margin-top: 12px; background: linear-gradient(135deg, #eef2ff, #f5f3ff); border-radius: 10px; padding: 10px 14px; border: 1px solid #c7d2fe;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <i class="bi bi-people-fill" style="color: #6366f1; font-size: 16px;"></i>
                        <div>
                            <div style="font-size: 13px; font-weight: 600; color: #4338ca;" id="modalDeviceText"></div>
                            <div style="font-size: 11px; color: #6366f1;">You can add more devices after purchase</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <form method="POST" id="paymentForm">
                <input type="hidden" name="action" value="register">
                <input type="hidden" name="mac" value="<?= htmlspecialchars($clientMAC) ?>">
                <input type="hidden" name="package_id" id="modalPackageId">
                
                <label style="font-size: 14px; font-weight: 600; color: #374151; display: block; margin-bottom: 8px;">M-Pesa Phone Number</label>
                <input type="tel" name="phone" class="form-input" id="phoneInput" placeholder="e.g., 0712345678" required style="margin-bottom: 16px;">
                
                <button type="submit" class="btn-main btn-mpesa" style="margin-bottom: 10px;">
                    <i class="bi bi-phone"></i> Pay with M-Pesa
                </button>
                <button type="button" class="btn-main btn-outline" onclick="closePayment()">Cancel</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
    function openPayment(pkgId, name, price, speed, validity, data, maxDevices) {
        document.getElementById('modalPackageId').value = pkgId;
        document.getElementById('modalPkgName').textContent = name;
        document.getElementById('modalPkgPrice').textContent = 'KES ' + price.toLocaleString();
        document.getElementById('modalPkgDetails').textContent = speed + ' \u00b7 ' + validity + (data !== 'Unlimited' ? ' \u00b7 ' + data : '');
        var multiEl = document.getElementById('modalMultiDevice');
        if (maxDevices > 1) {
            multiEl.style.display = 'block';
            document.getElementById('modalDeviceText').textContent = 'Supports up to ' + maxDevices + ' devices';
        } else {
            multiEl.style.display = 'none';
        }
        document.getElementById('paymentModal').classList.add('active');
        document.body.style.overflow = 'hidden';
        setTimeout(() => document.getElementById('phoneInput').focus(), 300);
    }
    
    function closePayment() {
        document.getElementById('paymentModal').classList.remove('active');
        document.body.style.overflow = '';
    }
    
    document.getElementById('paymentModal')?.addEventListener('click', function(e) {
        if (e.target === this) closePayment();
    });
    </script>
</body>
</html>
