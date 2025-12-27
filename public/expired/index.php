<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Mpesa.php';
require_once __DIR__ . '/../src/RadiusBilling.php';

$db = getDbConnection();

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'stk_push' && $subscription) {
        $phone = $_POST['phone'] ?? $subscription['customer_phone'] ?? '';
        $amount = (int)($subscription['package_price'] ?? 0);
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
                        $message = "Payment request sent to your phone. Please enter your M-Pesa PIN to complete the payment.";
                        $messageType = 'success';
                        $stkPushSent = true;
                    } else {
                        $message = "Failed to send payment request. Error: " . ($result['errorMessage'] ?? $result['ResponseDescription'] ?? 'Unknown error');
                        $messageType = 'danger';
                    }
                } else {
                    $message = "To renew your subscription, please send KES " . number_format($amount) . " to our M-Pesa Paybill. Use your username '{$accountRef}' as the account number.";
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription Expired</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #1e3a5f 0%, #0d1b2a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }
        .expired-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.3);
            max-width: 500px;
            width: 100%;
            overflow: hidden;
        }
        .expired-header {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        .expired-header .icon {
            font-size: 64px;
            margin-bottom: 15px;
        }
        .expired-body {
            padding: 30px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            color: #6c757d;
            font-size: 14px;
        }
        .info-value {
            font-weight: 600;
            color: #1e3a5f;
        }
        .renew-btn {
            background: linear-gradient(135deg, #28a745 0%, #218838 100%);
            border: none;
            padding: 15px 30px;
            font-size: 18px;
            font-weight: 600;
            border-radius: 10px;
            width: 100%;
            margin-top: 20px;
        }
        .renew-btn:hover {
            background: linear-gradient(135deg, #218838 0%, #1e7e34 100%);
        }
        .package-badge {
            background: rgba(255,255,255,0.2);
            padding: 8px 20px;
            border-radius: 20px;
            display: inline-block;
            margin-top: 10px;
        }
        .not-found {
            text-align: center;
            padding: 60px 30px;
        }
        .not-found .icon {
            font-size: 80px;
            color: #6c757d;
            margin-bottom: 20px;
        }
        .ip-badge {
            background: rgba(0,0,0,0.1);
            padding: 5px 15px;
            border-radius: 15px;
            font-size: 12px;
            margin-top: 15px;
            display: inline-block;
        }
    </style>
</head>
<body>
    <div class="expired-card">
        <?php if ($subscription): ?>
        <div class="expired-header">
            <div class="icon"><i class="bi bi-exclamation-triangle"></i></div>
            <h2 class="mb-2">Subscription Expired</h2>
            <p class="mb-0 opacity-75">Your internet subscription has expired</p>
            <div class="package-badge">
                <i class="bi bi-box me-1"></i> <?= htmlspecialchars($subscription['package_name'] ?? 'Unknown Package') ?>
            </div>
        </div>
        <div class="expired-body">
            <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> mb-4">
                <?= htmlspecialchars($message) ?>
            </div>
            <?php endif; ?>
            
            <div class="info-row">
                <span class="info-label">Customer Name</span>
                <span class="info-value"><?= htmlspecialchars($subscription['customer_name'] ?? 'N/A') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Username</span>
                <span class="info-value"><?= htmlspecialchars($subscription['username']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Package</span>
                <span class="info-value">
                    <?= htmlspecialchars($subscription['package_name'] ?? 'N/A') ?>
                    <br><small class="text-muted"><?= $subscription['download_speed'] ?? '' ?>/<?= $subscription['upload_speed'] ?? '' ?></small>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Expired On</span>
                <span class="info-value text-danger">
                    <?= $subscription['expiry_date'] ? date('M j, Y', strtotime($subscription['expiry_date'])) : 'N/A' ?>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Renewal Amount</span>
                <span class="info-value text-success fs-5">KES <?= number_format($subscription['package_price'] ?? 0) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Your IP Address</span>
                <span class="info-value"><code><?= htmlspecialchars($clientIP) ?></code></span>
            </div>
            
            <?php if ($stkPushSent): ?>
            <div class="text-center py-4">
                <div class="spinner-border text-success mb-3" role="status"></div>
                <h5>Waiting for Payment...</h5>
                <p class="text-muted">Check your phone for the M-Pesa prompt</p>
                <button onclick="location.reload()" class="btn btn-outline-secondary mt-3">
                    <i class="bi bi-arrow-clockwise me-1"></i> Refresh Status
                </button>
            </div>
            <?php else: ?>
            <form method="post">
                <input type="hidden" name="action" value="stk_push">
                <div class="mb-3">
                    <label class="form-label text-muted small">M-Pesa Phone Number</label>
                    <input type="tel" name="phone" class="form-control form-control-lg" 
                           value="<?= htmlspecialchars($subscription['customer_phone'] ?? '') ?>" 
                           placeholder="0712345678" required
                           pattern="^(07|01|2547|2541)[0-9]{8}$">
                    <div class="form-text">Enter the phone number to receive M-Pesa payment request</div>
                </div>
                <button type="submit" class="btn btn-success renew-btn">
                    <i class="bi bi-phone me-2"></i> Pay KES <?= number_format($subscription['package_price'] ?? 0) ?> via M-Pesa
                </button>
            </form>
            <?php endif; ?>
            
            <div class="text-center mt-4">
                <p class="text-muted small mb-2">Need help?</p>
                <div class="d-flex justify-content-center gap-3">
                    <a href="tel:0700000000" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-telephone me-1"></i> Call Support
                    </a>
                    <a href="https://wa.me/254700000000" class="btn btn-outline-success btn-sm">
                        <i class="bi bi-whatsapp me-1"></i> WhatsApp
                    </a>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="not-found">
            <div class="icon"><i class="bi bi-wifi-off"></i></div>
            <h3 class="mb-3">Connection Issue</h3>
            <p class="text-muted mb-4">We couldn't identify your subscription from your IP address.</p>
            <div class="ip-badge mb-4">
                <i class="bi bi-geo-alt me-1"></i> Your IP: <?= htmlspecialchars($clientIP) ?>
            </div>
            <p class="text-muted small">If you believe this is an error, please contact support with your username.</p>
            <a href="tel:0700000000" class="btn btn-primary mt-3">
                <i class="bi bi-telephone me-1"></i> Contact Support
            </a>
        </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
