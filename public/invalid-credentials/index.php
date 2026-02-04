<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/RadiusBilling.php';

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
$lookupMode = false;

$radiusBilling = new \App\RadiusBilling($db);
$ispName = $radiusBilling->getSetting('isp_name') ?: 'Your ISP';
$ispPhone = $radiusBilling->getSetting('isp_contact_phone') ?: '';
$ispPhoneFormatted = $ispPhone ? preg_replace('/[^0-9]/', '', $ispPhone) : '';
$ispWhatsApp = $ispPhoneFormatted ? '254' . substr($ispPhoneFormatted, -9) : '';

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
               p.download_speed, p.upload_speed, al.username as last_auth_username
        FROM radius_auth_log al
        JOIN radius_subscriptions s ON al.subscription_id = s.id
        LEFT JOIN customers c ON s.customer_id = c.id
        LEFT JOIN radius_packages p ON s.package_id = p.id
        WHERE al.client_ip = ? 
          AND al.result = 'Accept'
          AND al.reason = 'Invalid password'
          AND al.auth_time > NOW() - INTERVAL '1 hour'
        ORDER BY al.auth_time DESC
        LIMIT 1
    ");
    $stmt->execute([$clientIP]);
    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$subscription) {
        $stmt = $db->prepare("
            SELECT s.*, c.name as customer_name, c.phone as customer_phone, c.email as customer_email,
                   p.name as package_name, p.price as package_price, p.validity_days,
                   p.download_speed, p.upload_speed
            FROM radius_subscriptions s
            LEFT JOIN customers c ON s.customer_id = c.id
            LEFT JOIN radius_packages p ON s.package_id = p.id
            LEFT JOIN radius_sessions rs ON s.id = rs.subscription_id
            WHERE rs.framed_ip = ? AND rs.stop_time IS NULL
            LIMIT 1
        ");
        $stmt->execute([$clientIP]);
        $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

$showLookupForm = !$subscription;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invalid Credentials - <?= htmlspecialchars($ispName) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #dc3545 0%, #6c757d 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            max-width: 500px;
            width: 100%;
        }
        .card-header {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            border-radius: 20px 20px 0 0 !important;
            padding: 30px;
            text-align: center;
        }
        .card-header i {
            font-size: 4rem;
            margin-bottom: 15px;
        }
        .card-body {
            padding: 30px;
        }
        .info-box {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .btn-contact {
            border-radius: 50px;
            padding: 12px 30px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="card-header text-white">
            <i class="bi bi-shield-exclamation"></i>
            <h3 class="mb-0">Invalid Credentials</h3>
        </div>
        <div class="card-body">
            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <?php if ($subscription): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <strong>Authentication Failed!</strong><br>
                    The password you entered is incorrect. Please check your credentials and reconnect.
                </div>

                <div class="info-box">
                    <h5 class="mb-3"><i class="bi bi-person-circle me-2"></i>Account Details</h5>
                    <table class="table table-sm mb-0">
                        <tr>
                            <td class="text-muted">Username:</td>
                            <td class="fw-bold"><?= htmlspecialchars($subscription['username']) ?></td>
                        </tr>
                        <?php if (!empty($subscription['customer_name'])): ?>
                        <tr>
                            <td class="text-muted">Name:</td>
                            <td><?= htmlspecialchars($subscription['customer_name']) ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if (!empty($subscription['package_name'])): ?>
                        <tr>
                            <td class="text-muted">Package:</td>
                            <td><?= htmlspecialchars($subscription['package_name']) ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td class="text-muted">Status:</td>
                            <td>
                                <span class="badge bg-<?= $subscription['status'] === 'active' ? 'success' : 'secondary' ?>">
                                    <?= ucfirst($subscription['status']) ?>
                                </span>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="info-box bg-warning bg-opacity-10 border border-warning">
                    <h6 class="text-warning mb-2"><i class="bi bi-lightbulb me-2"></i>What to do:</h6>
                    <ol class="mb-0 ps-3">
                        <li>Check that your PPPoE password is correct</li>
                        <li>Re-enter your credentials in your router settings</li>
                        <li>If you forgot your password, contact support</li>
                    </ol>
                </div>

            <?php else: ?>
                <div class="alert alert-warning">
                    <i class="bi bi-info-circle me-2"></i>
                    Unable to identify your account. Please enter your username or phone number below.
                </div>
                
                <form method="post" class="mb-4">
                    <input type="hidden" name="action" value="lookup">
                    <div class="mb-3">
                        <label class="form-label">Username or Phone Number</label>
                        <input type="text" name="lookup_value" class="form-control form-control-lg" 
                               placeholder="e.g., john_doe or 0712345678" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg w-100">
                        <i class="bi bi-search me-2"></i>Find My Account
                    </button>
                </form>
            <?php endif; ?>

            <hr>
            
            <div class="text-center">
                <p class="text-muted mb-3">Need help? Contact <?= htmlspecialchars($ispName) ?> Support:</p>
                <div class="d-flex flex-wrap justify-content-center gap-2">
                    <?php if ($ispPhone): ?>
                    <a href="tel:<?= $ispPhone ?>" class="btn btn-outline-primary btn-contact">
                        <i class="bi bi-telephone me-2"></i>Call
                    </a>
                    <?php endif; ?>
                    <?php if ($ispWhatsApp): ?>
                    <a href="https://wa.me/<?= $ispWhatsApp ?>?text=Hi, I'm having trouble with my PPPoE credentials. Username: <?= urlencode($subscription['username'] ?? 'Unknown') ?>" 
                       target="_blank" class="btn btn-success btn-contact">
                        <i class="bi bi-whatsapp me-2"></i>WhatsApp
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
