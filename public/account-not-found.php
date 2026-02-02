<?php
require_once __DIR__ . '/../config/database.php';
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
$portalUrl = $radiusBilling->getSetting('self_service_url') ?: '';

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
$macAddress = $_GET['mac'] ?? '';
$username = $_GET['username'] ?? '';

$message = '';
$messageType = 'info';
$subscriptionFound = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'lookup') {
    $lookupValue = trim($_POST['lookup_value'] ?? '');
    if (!empty($lookupValue)) {
        $phone = preg_replace('/[^0-9]/', '', $lookupValue);
        if (substr($phone, 0, 1) === '0') {
            $phone = '254' . substr($phone, 1);
        }
        
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
        $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($subscription) {
            $subscriptionFound = true;
            $message = "Account found! Your username is: <strong>" . htmlspecialchars($subscription['username']) . "</strong>";
            if ($subscription['expiry_date'] && strtotime($subscription['expiry_date']) < time()) {
                $message .= "<br>Your subscription has expired. Please renew to continue using the internet.";
                header("Location: /expired.php?username=" . urlencode($subscription['username']));
                exit;
            }
            $messageType = 'success';
        } else {
            $message = "No account found for '" . htmlspecialchars($lookupValue) . "'. Please contact support to register.";
            $messageType = 'warning';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Not Found - <?= htmlspecialchars($ispName) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #dc3545;
            --secondary-color: #6c757d;
        }
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
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .card-header .logo {
            width: 80px;
            height: 80px;
            object-fit: contain;
            margin-bottom: 15px;
            background: white;
            border-radius: 50%;
            padding: 10px;
        }
        .card-header h1 {
            font-size: 1.5rem;
            margin: 0;
            font-weight: 600;
        }
        .card-body {
            padding: 30px;
        }
        .alert-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 20px;
            margin-bottom: 25px;
            border-radius: 8px;
        }
        .alert-box i {
            color: #ffc107;
            font-size: 2rem;
            margin-bottom: 10px;
        }
        .lookup-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        .lookup-form h5 {
            color: #333;
            margin-bottom: 15px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px 30px;
            font-weight: 600;
            border-radius: 8px;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #5a6fd6 0%, #6a4190 100%);
        }
        .contact-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
        }
        .contact-section h5 {
            color: #333;
            margin-bottom: 15px;
        }
        .contact-btn {
            margin: 5px;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
        }
        .btn-whatsapp {
            background: #25D366;
            color: white;
        }
        .btn-whatsapp:hover {
            background: #128C7E;
            color: white;
        }
        .btn-call {
            background: #007bff;
            color: white;
        }
        .btn-call:hover {
            background: #0056b3;
            color: white;
        }
        .info-text {
            color: #6c757d;
            font-size: 0.9rem;
        }
        .ip-info {
            font-size: 0.8rem;
            color: #adb5bd;
            text-align: center;
            margin-top: 20px;
        }
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
            <p class="mb-0 opacity-75">Account Not Found</p>
        </div>
        
        <div class="card-body">
            <div class="alert-box text-center">
                <i class="fas fa-exclamation-triangle"></i>
                <h5 class="mt-2 mb-2">Your account was not found</h5>
                <p class="mb-0 info-text">
                    The username or device you're using is not registered in our system.
                    <?php if ($username): ?>
                        <br><strong>Username attempted:</strong> <?= htmlspecialchars($username) ?>
                    <?php endif; ?>
                </p>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType === 'success' ? 'success' : ($messageType === 'warning' ? 'warning' : 'info') ?> mb-3">
                    <?= $message ?>
                </div>
            <?php endif; ?>
            
            <div class="lookup-form">
                <h5><i class="fas fa-search me-2"></i>Find Your Account</h5>
                <p class="info-text mb-3">Enter your phone number or username to find your account:</p>
                <form method="POST">
                    <input type="hidden" name="action" value="lookup">
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" name="lookup_value" 
                               placeholder="Phone (07XX...) or Username" required>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-1"></i> Search
                        </button>
                    </div>
                </form>
            </div>

            <div class="contact-section">
                <h5><i class="fas fa-headset me-2"></i>Need to Register?</h5>
                <p class="info-text mb-3">Contact us to create a new account:</p>
                
                <?php if ($ispWhatsApp): ?>
                    <a href="https://wa.me/<?= $ispWhatsApp ?>?text=Hello%2C%20I%20need%20to%20register%20for%20internet%20service" 
                       class="contact-btn btn-whatsapp" target="_blank">
                        <i class="fab fa-whatsapp"></i> WhatsApp
                    </a>
                <?php endif; ?>
                
                <?php if ($ispPhone): ?>
                    <a href="tel:<?= $ispPhoneFormatted ?>" class="contact-btn btn-call">
                        <i class="fas fa-phone"></i> Call Us
                    </a>
                <?php endif; ?>
                
                <?php if (!$ispWhatsApp && !$ispPhone): ?>
                    <p class="info-text">Please contact your ISP administrator for assistance.</p>
                <?php endif; ?>
            </div>
            
            <div class="ip-info">
                <i class="fas fa-network-wired me-1"></i>
                Your IP: <?= htmlspecialchars($clientIP) ?>
                <?php if ($macAddress): ?>
                    | MAC: <?= htmlspecialchars($macAddress) ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
