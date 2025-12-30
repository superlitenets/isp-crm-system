<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/RadiusBilling.php';

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
                    // Register MAC if provided and MAC auth is enabled
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
            break;
    }
}

// Handle MikroTik error messages
if ($errorMsg) {
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
            max-width: 420px;
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
            padding: 35px 30px;
            text-align: center;
        }
        .hotspot-header .logo {
            width: 80px;
            height: 80px;
            background: rgba(255,255,255,0.15);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 40px;
        }
        .hotspot-header .logo img {
            max-width: 60px;
            max-height: 60px;
            border-radius: 50%;
        }
        .hotspot-body {
            padding: 30px;
        }
        .nav-tabs-custom {
            border: none;
            background: #f8f9fa;
            border-radius: 12px;
            padding: 5px;
            margin-bottom: 25px;
        }
        .nav-tabs-custom .nav-link {
            border: none;
            border-radius: 8px;
            color: #6c757d;
            font-weight: 500;
            padding: 10px 20px;
            transition: all 0.3s;
        }
        .nav-tabs-custom .nav-link.active {
            background: #667eea;
            color: white;
        }
        .form-control {
            border-radius: 12px;
            padding: 14px 18px;
            border: 2px solid #e9ecef;
            font-size: 16px;
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
            padding: 14px;
            font-size: 16px;
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
        .input-group .form-control:focus {
            border-left: none;
        }
        .input-group:focus-within .input-group-text {
            border-color: #667eea;
        }
        .success-card {
            text-align: center;
            padding: 40px 30px;
        }
        .success-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 50px;
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
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            display: inline-block;
            margin: 5px;
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
            .hotspot-header { padding: 25px 20px; }
            .hotspot-body { padding: 20px; }
            .nav-tabs-custom .nav-link { padding: 8px 12px; font-size: 14px; }
        }
    </style>
</head>
<body>
    <div class="hotspot-card">
        <?php if ($loginSuccess): ?>
        <div class="success-card">
            <div class="success-icon"><i class="bi bi-check-lg"></i></div>
            <h3 class="mb-2">Connected!</h3>
            <p class="text-muted mb-4">You are now connected to the internet</p>
            
            <?php if (!empty($authResult['subscription'])): ?>
            <div class="mb-4">
                <div class="info-badge">
                    <i class="bi bi-person me-1"></i>
                    <?= htmlspecialchars($authResult['subscription']['customer_name'] ?? $authResult['subscription']['username']) ?>
                </div>
                <div class="info-badge">
                    <i class="bi bi-speedometer2 me-1"></i>
                    <?= htmlspecialchars($authResult['subscription']['download_speed'] ?? 'N/A') ?>
                </div>
                <?php if (!empty($authResult['subscription']['expiry_date'])): ?>
                <div class="info-badge">
                    <i class="bi bi-calendar me-1"></i>
                    Expires: <?= date('M j, Y', strtotime($authResult['subscription']['expiry_date'])) ?>
                </div>
                <?php endif; ?>
            </div>
            <?php elseif (!empty($authResult['package'])): ?>
            <div class="mb-4">
                <div class="info-badge">
                    <i class="bi bi-ticket me-1"></i>
                    <?= htmlspecialchars($authResult['package']['package_name']) ?>
                </div>
                <div class="info-badge">
                    <i class="bi bi-speedometer2 me-1"></i>
                    <?= htmlspecialchars($authResult['package']['download_speed']) ?>
                </div>
                <div class="info-badge">
                    <i class="bi bi-calendar me-1"></i>
                    Valid: <?= $authResult['package']['validity_days'] ?> days
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
            
            <?php if (!empty($authResult['mac_auth'])): ?>
            <p class="text-muted small mt-3">
                <i class="bi bi-shield-check me-1"></i>
                Your device will auto-connect next time
            </p>
            <?php endif; ?>
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
            <h4 class="mb-1"><?= htmlspecialchars($ispName) ?></h4>
            <p class="mb-0 opacity-75 small"><?= htmlspecialchars($hotspotWelcome) ?></p>
        </div>
        
        <div class="hotspot-body">
            <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> py-2 small">
                <?= htmlspecialchars($message) ?>
            </div>
            <?php endif; ?>
            
            <ul class="nav nav-tabs nav-tabs-custom nav-fill" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" data-bs-toggle="tab" href="#loginTab">
                        <i class="bi bi-person-fill me-1"></i>Login
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#voucherTab">
                        <i class="bi bi-ticket-fill me-1"></i>Voucher
                    </a>
                </li>
            </ul>
            
            <div class="tab-content">
                <div class="tab-pane fade show active" id="loginTab">
                    <form method="post" action="<?= $linkLoginOnly ?: '' ?>">
                        <input type="hidden" name="action" value="login">
                        <input type="hidden" name="mac" value="<?= htmlspecialchars($clientMAC) ?>">
                        <input type="hidden" name="dst" value="<?= htmlspecialchars($linkOrig) ?>">
                        
                        <div class="mb-3">
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person"></i></span>
                                <input type="text" name="username" class="form-control" 
                                       placeholder="Username" required autocomplete="username">
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" name="password" class="form-control" 
                                       placeholder="Password" required autocomplete="current-password">
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-login">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Connect
                        </button>
                    </form>
                </div>
                
                <div class="tab-pane fade" id="voucherTab">
                    <form method="post" action="<?= $linkLoginOnly ?: '' ?>">
                        <input type="hidden" name="action" value="voucher">
                        <input type="hidden" name="mac" value="<?= htmlspecialchars($clientMAC) ?>">
                        <input type="hidden" name="dst" value="<?= htmlspecialchars($linkOrig) ?>">
                        
                        <div class="mb-4">
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-ticket"></i></span>
                                <input type="text" name="voucher_code" class="form-control" 
                                       placeholder="Enter voucher code" required
                                       style="text-transform: uppercase;" autocomplete="off">
                            </div>
                            <div class="form-text text-center mt-2">
                                Enter the code printed on your voucher card
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-login">
                            <i class="bi bi-check-circle me-2"></i>Redeem & Connect
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="footer-text">
            <?php if ($macAuthEnabled && $clientMAC): ?>
            <i class="bi bi-info-circle me-1"></i>
            After first login, your device will connect automatically
            <?php else: ?>
            <i class="bi bi-shield-lock me-1"></i>
            Secure Connection
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
