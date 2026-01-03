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
$authResult = null;
$loginSuccess = false;
$stkPushSent = false;
$foundSubscription = null;
$activeTab = 'login';

// MikroTik hotspot variables
$linkLogin = $_GET['link-login'] ?? '';
$linkLoginOnly = $_GET['link-login-only'] ?? '';
$linkOrig = $_GET['link-orig'] ?? $_GET['dst'] ?? '';
$errorMsg = $_GET['error'] ?? '';

// Get ISP settings
$ispName = $radiusBilling->getSetting('isp_name') ?: 'WiFi Hotspot';
$ispLogo = $radiusBilling->getSetting('isp_logo') ?: '';
$hotspotWelcome = $radiusBilling->getSetting('hotspot_welcome') ?: 'Welcome! Please login to access the internet.';
$macAuthEnabled = $radiusBilling->getSetting('hotspot_mac_auth') === 'true';
$mpesaPaybill = $radiusBilling->getSetting('mpesa_paybill') ?: '';

// Check M-Pesa configuration
$mpesa = new \App\Mpesa();
$mpesaEnabled = $mpesa->isConfigured();

// Get available packages for purchase
$packages = [];
if ($mpesaEnabled) {
    $stmt = $db->query("SELECT * FROM radius_packages WHERE is_active = true ORDER BY price ASC");
    $packages = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Try MAC auto-login first
if ($macAuthEnabled && !empty($clientMAC) && empty($_POST['action'])) {
    $macResult = $radiusBilling->authenticateByMAC($clientMAC);
    if ($macResult['success'] && empty($macResult['expired'])) {
        $loginSuccess = true;
        $authResult = $macResult;
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'login':
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            
            if (empty($username) || empty($password)) {
                $message = 'Please enter username and password';
                $messageType = 'danger';
            } else {
                $authResult = $radiusBilling->authenticate($username, $password);
                if ($authResult['success']) {
                    $loginSuccess = true;
                    if ($macAuthEnabled && !empty($clientMAC) && !empty($authResult['subscription']['id'])) {
                        $radiusBilling->registerMACForHotspot($authResult['subscription']['id'], $clientMAC);
                    }
                } else {
                    $message = $authResult['reason'] ?? 'Login failed';
                    $messageType = 'danger';
                }
            }
            break;
            
        case 'voucher':
            $code = trim($_POST['voucher_code'] ?? '');
            if (empty($code)) {
                $message = 'Please enter a voucher code';
                $messageType = 'danger';
            } else {
                $authResult = $radiusBilling->authenticateVoucher($code);
                if ($authResult['success']) {
                    $loginSuccess = true;
                } else {
                    $message = $authResult['error'] ?? 'Invalid voucher';
                    $messageType = 'danger';
                }
            }
            $activeTab = 'voucher';
            break;
            
        case 'lookup':
            $lookupValue = trim($_POST['lookup_value'] ?? '');
            $activeTab = 'mpesa';
            if (!empty($lookupValue)) {
                $phone = preg_replace('/[^0-9]/', '', $lookupValue);
                if (substr($phone, 0, 1) === '0') {
                    $phone = '254' . substr($phone, 1);
                }
                
                $stmt = $db->prepare("
                    SELECT s.*, c.name as customer_name, c.phone as customer_phone,
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
                $foundSubscription = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$foundSubscription) {
                    $message = "No account found. You can purchase a new package below.";
                    $messageType = 'warning';
                }
            }
            break;
            
        case 'renew':
            $subId = (int)($_POST['subscription_id'] ?? 0);
            $phone = $_POST['phone'] ?? '';
            $activeTab = 'mpesa';
            
            if ($subId > 0 && !empty($phone)) {
                $stmt = $db->prepare("
                    SELECT s.*, p.name as package_name, p.price as package_price
                    FROM radius_subscriptions s
                    LEFT JOIN radius_packages p ON s.package_id = p.id
                    WHERE s.id = ?
                ");
                $stmt->execute([$subId]);
                $sub = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($sub && $sub['package_price'] > 0) {
                    try {
                        $result = $mpesa->stkPush($phone, (int)$sub['package_price'], $sub['username'], "Renew - {$sub['package_name']}");
                        if ($result && isset($result['ResponseCode']) && $result['ResponseCode'] == '0') {
                            $message = "Payment request sent! Enter your M-Pesa PIN on your phone.";
                            $messageType = 'success';
                            $stkPushSent = true;
                        } else {
                            $message = $result['errorMessage'] ?? 'Failed to send payment request. Try again.';
                            $messageType = 'danger';
                        }
                    } catch (Exception $e) {
                        $message = 'Payment service error. Please try again later.';
                        $messageType = 'danger';
                    }
                }
                $foundSubscription = $sub;
            }
            break;
            
        case 'buy_package':
            $packageId = (int)($_POST['package_id'] ?? 0);
            $phone = $_POST['phone'] ?? '';
            $activeTab = 'mpesa';
            
            if ($packageId > 0 && !empty($phone)) {
                $stmt = $db->prepare("SELECT * FROM radius_packages WHERE id = ? AND status = 'active'");
                $stmt->execute([$packageId]);
                $pkg = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($pkg && $pkg['price'] > 0) {
                    $normalizedPhone = preg_replace('/[^0-9]/', '', $phone);
                    if (substr($normalizedPhone, 0, 1) === '0') {
                        $normalizedPhone = '254' . substr($normalizedPhone, 1);
                    }
                    
                    $accountRef = 'NEW-' . substr($normalizedPhone, -4) . '-' . $packageId;
                    
                    try {
                        $result = $mpesa->stkPush($phone, (int)$pkg['price'], $accountRef, "Buy {$pkg['name']}");
                        if ($result && isset($result['ResponseCode']) && $result['ResponseCode'] == '0') {
                            $message = "Payment request sent! Enter your M-Pesa PIN. After payment, you'll receive login details via SMS.";
                            $messageType = 'success';
                            $stkPushSent = true;
                        } else {
                            $message = $result['errorMessage'] ?? 'Failed to send payment request.';
                            $messageType = 'danger';
                        }
                    } catch (Exception $e) {
                        $message = 'Payment service error. Please try again later.';
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
        'invalid username or password' => 'Invalid username or password',
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
    <title><?= htmlspecialchars($ispName) ?> - Hotspot Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            box-shadow: 0 25px 60px rgba(0,0,0,0.3);
            max-width: 440px;
            width: 100%;
            overflow: hidden;
            animation: slideUp 0.5s ease-out;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .hotspot-header {
            background: linear-gradient(135deg, #1e3a5f 0%, #0d1b2a 100%);
            color: white;
            padding: 30px 25px;
            text-align: center;
        }
        .hotspot-header .logo {
            width: 70px;
            height: 70px;
            background: rgba(255,255,255,0.15);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
            font-size: 35px;
        }
        .hotspot-header .logo img {
            max-width: 50px;
            max-height: 50px;
            border-radius: 50%;
        }
        .hotspot-body {
            padding: 25px;
        }
        .nav-tabs-custom {
            border: none;
            background: #f8f9fa;
            border-radius: 12px;
            padding: 4px;
            margin-bottom: 20px;
        }
        .nav-tabs-custom .nav-link {
            border: none;
            border-radius: 8px;
            color: #6c757d;
            font-weight: 500;
            padding: 8px 12px;
            font-size: 13px;
            transition: all 0.3s;
        }
        .nav-tabs-custom .nav-link.active {
            background: #667eea;
            color: white;
        }
        .form-control {
            border-radius: 12px;
            padding: 12px 16px;
            border: 2px solid #e9ecef;
            font-size: 15px;
            transition: all 0.3s;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.15);
        }
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 12px;
            padding: 12px;
            font-size: 15px;
            font-weight: 600;
            width: 100%;
            color: white;
            transition: all 0.3s;
        }
        .btn-login:hover {
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
        .input-group-text {
            background: transparent;
            border: 2px solid #e9ecef;
            border-right: none;
            border-radius: 12px 0 0 12px;
            color: #6c757d;
        }
        .input-group .form-control {
            border-left: none;
            border-radius: 0 12px 12px 0;
        }
        .input-group:focus-within .input-group-text {
            border-color: #667eea;
        }
        .success-card {
            text-align: center;
            padding: 35px 25px;
        }
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
            padding: 6px 12px;
            border-radius: 16px;
            font-size: 12px;
            display: inline-block;
            margin: 3px;
        }
        .footer-text {
            text-align: center;
            padding: 12px;
            color: #6c757d;
            font-size: 11px;
            border-top: 1px solid #eee;
        }
        .package-card {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 12px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .package-card:hover, .package-card.selected {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.05);
        }
        .package-card .price {
            font-size: 18px;
            font-weight: 700;
            color: #28a745;
        }
        .account-info {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .stkpush-waiting {
            animation: breathe 2s ease-in-out infinite;
        }
        @keyframes breathe {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        @media (max-width: 480px) {
            .hotspot-card { border-radius: 16px; }
            .hotspot-header { padding: 20px 15px; }
            .hotspot-body { padding: 18px; }
            .nav-tabs-custom .nav-link { padding: 6px 8px; font-size: 11px; }
        }
    </style>
</head>
<body>
    <div class="hotspot-card">
        <?php if ($loginSuccess): ?>
        <div class="success-card">
            <div class="success-icon"><i class="bi bi-check-lg"></i></div>
            <h4 class="mb-2">Connected!</h4>
            <p class="text-muted mb-3">You are now connected to the internet</p>
            
            <?php if (!empty($authResult['subscription'])): ?>
            <div class="mb-3">
                <div class="info-badge">
                    <i class="bi bi-person me-1"></i>
                    <?= htmlspecialchars($authResult['subscription']['customer_name'] ?? $authResult['subscription']['username']) ?>
                </div>
                <div class="info-badge">
                    <i class="bi bi-speedometer2 me-1"></i>
                    <?= htmlspecialchars($authResult['subscription']['download_speed'] ?? 'N/A') ?>
                </div>
            </div>
            <?php elseif (!empty($authResult['package'])): ?>
            <div class="mb-3">
                <div class="info-badge">
                    <i class="bi bi-ticket me-1"></i>
                    <?= htmlspecialchars($authResult['package']['package_name']) ?>
                </div>
                <div class="info-badge">
                    <i class="bi bi-clock me-1"></i>
                    <?= $authResult['package']['validity_days'] ?> days
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($linkOrig): ?>
            <a href="<?= htmlspecialchars($linkOrig) ?>" class="btn btn-login">
                <i class="bi bi-box-arrow-up-right me-2"></i>Continue Browsing
            </a>
            <?php else: ?>
            <a href="https://google.com" class="btn btn-login">
                <i class="bi bi-globe me-2"></i>Start Browsing
            </a>
            <?php endif; ?>
        </div>
        <?php elseif ($stkPushSent): ?>
        <div class="success-card stkpush-waiting">
            <div class="success-icon" style="background: linear-gradient(135deg, #4caf50 0%, #2e7d32 100%);">
                <i class="bi bi-phone"></i>
            </div>
            <h4 class="mb-2">Check Your Phone</h4>
            <p class="text-muted mb-3">Enter your M-Pesa PIN to complete payment</p>
            <div class="alert alert-success small">
                <i class="bi bi-info-circle me-1"></i>
                After payment, your account will be activated automatically. You can then login with your credentials.
            </div>
            <a href="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>" class="btn btn-outline-secondary mt-2">
                <i class="bi bi-arrow-clockwise me-1"></i>Try Again
            </a>
        </div>
        <?php else: ?>
        <div class="hotspot-header">
            <div class="logo">
                <?php if ($ispLogo): ?>
                <img src="<?= htmlspecialchars($ispLogo) ?>" alt="Logo">
                <?php else: ?>
                <i class="bi bi-wifi"></i>
                <?php endif; ?>
            </div>
            <h5 class="mb-1"><?= htmlspecialchars($ispName) ?></h5>
            <p class="mb-0 opacity-75 small"><?= htmlspecialchars($hotspotWelcome) ?></p>
        </div>
        
        <div class="hotspot-body">
            <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> py-2 small mb-3">
                <?= htmlspecialchars($message) ?>
            </div>
            <?php endif; ?>
            
            <ul class="nav nav-tabs nav-tabs-custom nav-fill" role="tablist">
                <li class="nav-item">
                    <a class="nav-link <?= $activeTab === 'login' ? 'active' : '' ?>" data-bs-toggle="tab" href="#loginTab">
                        <i class="bi bi-person-fill me-1"></i>Login
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $activeTab === 'voucher' ? 'active' : '' ?>" data-bs-toggle="tab" href="#voucherTab">
                        <i class="bi bi-ticket-fill me-1"></i>Voucher
                    </a>
                </li>
                <?php if ($mpesaEnabled): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $activeTab === 'mpesa' ? 'active' : '' ?>" data-bs-toggle="tab" href="#mpesaTab">
                        <i class="bi bi-phone-fill me-1"></i>M-Pesa
                    </a>
                </li>
                <?php endif; ?>
            </ul>
            
            <div class="tab-content">
                <div class="tab-pane fade <?= $activeTab === 'login' ? 'show active' : '' ?>" id="loginTab">
                    <form method="post">
                        <input type="hidden" name="action" value="login">
                        <input type="hidden" name="mac" value="<?= htmlspecialchars($clientMAC) ?>">
                        
                        <div class="mb-3">
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person"></i></span>
                                <input type="text" name="username" class="form-control" placeholder="Username" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" name="password" class="form-control" placeholder="Password" required>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-login">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Connect
                        </button>
                    </form>
                </div>
                
                <div class="tab-pane fade <?= $activeTab === 'voucher' ? 'show active' : '' ?>" id="voucherTab">
                    <form method="post">
                        <input type="hidden" name="action" value="voucher">
                        <input type="hidden" name="mac" value="<?= htmlspecialchars($clientMAC) ?>">
                        
                        <div class="mb-3">
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-ticket"></i></span>
                                <input type="text" name="voucher_code" class="form-control" 
                                       placeholder="Enter voucher code" required style="text-transform: uppercase;">
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-login">
                            <i class="bi bi-check-circle me-2"></i>Redeem
                        </button>
                    </form>
                </div>
                
                <?php if ($mpesaEnabled): ?>
                <div class="tab-pane fade <?= $activeTab === 'mpesa' ? 'show active' : '' ?>" id="mpesaTab">
                    <?php if ($foundSubscription): ?>
                    <div class="account-info">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <strong><?= htmlspecialchars($foundSubscription['customer_name'] ?? $foundSubscription['username']) ?></strong>
                            <span class="badge <?= $foundSubscription['status'] === 'active' ? 'bg-success' : 'bg-danger' ?>">
                                <?= ucfirst($foundSubscription['status']) ?>
                            </span>
                        </div>
                        <small class="text-muted d-block">Package: <?= htmlspecialchars($foundSubscription['package_name']) ?></small>
                        <?php if (!empty($foundSubscription['expiry_date'])): ?>
                        <small class="text-muted d-block">
                            <?php if (strtotime($foundSubscription['expiry_date']) < time()): ?>
                            <span class="text-danger">Expired: <?= date('M j, Y', strtotime($foundSubscription['expiry_date'])) ?></span>
                            <?php else: ?>
                            Expires: <?= date('M j, Y', strtotime($foundSubscription['expiry_date'])) ?>
                            <?php endif; ?>
                        </small>
                        <?php endif; ?>
                    </div>
                    
                    <form method="post">
                        <input type="hidden" name="action" value="renew">
                        <input type="hidden" name="subscription_id" value="<?= $foundSubscription['id'] ?>">
                        
                        <div class="mb-3">
                            <label class="form-label small text-muted">M-Pesa Phone Number</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-phone"></i></span>
                                <input type="tel" name="phone" class="form-control" 
                                       value="<?= htmlspecialchars($foundSubscription['customer_phone'] ?? '') ?>"
                                       placeholder="0712345678" required>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-login btn-mpesa">
                            <i class="bi bi-currency-exchange me-2"></i>Pay KES <?= number_format($foundSubscription['package_price']) ?>
                        </button>
                    </form>
                    
                    <hr class="my-3">
                    <a href="?<?= http_build_query(array_filter(['mac' => $clientMAC, 'dst' => $linkOrig])) ?>" class="btn btn-outline-secondary btn-sm w-100">
                        <i class="bi bi-arrow-left me-1"></i>Different Account
                    </a>
                    <?php else: ?>
                    <form method="post" class="mb-4">
                        <input type="hidden" name="action" value="lookup">
                        <p class="small text-muted mb-2">Enter your username or phone to renew:</p>
                        <div class="input-group mb-2">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" name="lookup_value" class="form-control" placeholder="Username or phone number">
                        </div>
                        <button type="submit" class="btn btn-outline-primary btn-sm w-100">Find Account</button>
                    </form>
                    
                    <?php if (!empty($packages)): ?>
                    <hr class="my-3">
                    <p class="small text-muted mb-2">Or buy a new package:</p>
                    <form method="post" id="buyForm">
                        <input type="hidden" name="action" value="buy_package">
                        <input type="hidden" name="package_id" id="selectedPackage" value="">
                        
                        <?php foreach ($packages as $pkg): ?>
                        <div class="package-card" onclick="selectPackage(<?= $pkg['id'] ?>, this)">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?= htmlspecialchars($pkg['name']) ?></strong>
                                    <small class="text-muted d-block"><?= htmlspecialchars($pkg['download_speed']) ?> / <?= $pkg['validity_days'] ?> days</small>
                                </div>
                                <div class="price">KES <?= number_format($pkg['price']) ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <div id="phoneSection" style="display: none;">
                            <div class="mb-3 mt-3">
                                <label class="form-label small text-muted">Your M-Pesa Phone Number</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-phone"></i></span>
                                    <input type="tel" name="phone" class="form-control" placeholder="0712345678" required>
                                </div>
                                <small class="text-muted">You'll receive login details via SMS</small>
                            </div>
                            <button type="submit" class="btn btn-login btn-mpesa">
                                <i class="bi bi-currency-exchange me-2"></i>Pay Now
                            </button>
                        </div>
                    </form>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="footer-text">
            <?php if ($mpesaPaybill): ?>
            <i class="bi bi-phone me-1"></i>Paybill: <?= htmlspecialchars($mpesaPaybill) ?>
            <?php else: ?>
            <i class="bi bi-shield-lock me-1"></i>Secure Connection
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function selectPackage(id, elem) {
        document.querySelectorAll('.package-card').forEach(c => c.classList.remove('selected'));
        elem.classList.add('selected');
        document.getElementById('selectedPackage').value = id;
        document.getElementById('phoneSection').style.display = 'block';
    }
    </script>
</body>
</html>
