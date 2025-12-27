<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/RadiusBilling.php';
require_once __DIR__ . '/../../src/Mpesa.php';
require_once __DIR__ . '/../../src/GenieACS.php';

try {
    $db = Database::getConnection();
    $radiusBilling = new \App\RadiusBilling($db);
    $genieACS = new \App\GenieACS($db);
} catch (Exception $e) {
    die('Database connection error: ' . $e->getMessage());
}

$message = '';
$messageType = 'info';
$subscription = null;
$sessions = [];
$invoices = [];
$usageHistory = [];
$customerOnu = null;
$tr069DeviceId = null;
$wifiSettings = null;

function getClientIp() {
    $headers = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ips = explode(',', $_SERVER[$header]);
            $ip = trim($ips[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) || 
                filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

function normalizePhone($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone) === 9) {
        $phone = '254' . $phone;
    } elseif (strlen($phone) === 10 && substr($phone, 0, 1) === '0') {
        $phone = '254' . substr($phone, 1);
    } elseif (strlen($phone) === 12 && substr($phone, 0, 3) === '254') {
    } elseif (strlen($phone) === 13 && substr($phone, 0, 4) === '+254') {
        $phone = substr($phone, 1);
    }
    return $phone;
}

if (!isset($_SESSION['portal_subscription_id'])) {
    $clientIp = getClientIp();
    
    $stmt = $db->prepare("
        SELECT rs.id as subscription_id 
        FROM radius_sessions rsess
        JOIN radius_subscriptions rs ON rs.id = rsess.subscription_id
        WHERE rsess.framed_ip_address = ? AND rsess.session_end IS NULL
        ORDER BY rsess.started_at DESC LIMIT 1
    ");
    $stmt->execute([$clientIp]);
    $autoLogin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($autoLogin) {
        $_SESSION['portal_subscription_id'] = $autoLogin['subscription_id'];
        $_SESSION['portal_auto_login'] = true;
        $_SESSION['portal_client_ip'] = $clientIp;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'login':
                $phone = trim($_POST['phone'] ?? '');
                $password = trim($_POST['password'] ?? '');
                $normalizedPhone = normalizePhone($phone);
                
                $stmt = $db->prepare("
                    SELECT rs.*, c.phone as customer_phone FROM radius_subscriptions rs
                    JOIN customers c ON c.id = rs.customer_id
                    WHERE REPLACE(REPLACE(REPLACE(c.phone, '+', ''), ' ', ''), '-', '') = ?
                       OR REPLACE(REPLACE(REPLACE(c.phone, '+', ''), ' ', ''), '-', '') LIKE ?
                    ORDER BY rs.created_at DESC LIMIT 1
                ");
                $phonePattern = '%' . substr($normalizedPhone, -9);
                $stmt->execute([$normalizedPhone, $phonePattern]);
                $sub = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($sub) {
                    if ($sub['password'] === $password) {
                        $_SESSION['portal_subscription_id'] = $sub['id'];
                        $_SESSION['portal_username'] = $sub['username'];
                        header('Location: ?');
                        exit;
                    } else {
                        $message = 'Invalid password. Please try again.';
                        $messageType = 'danger';
                    }
                } else {
                    $message = 'No account found with this phone number. Please contact support.';
                    $messageType = 'danger';
                }
                break;
                
            case 'logout':
                session_destroy();
                header('Location: ?');
                exit;
                
            case 'pay':
                $phone = $_POST['phone'] ?? '';
                $subscription = $radiusBilling->getSubscription($_SESSION['portal_subscription_id']);
                $package = $radiusBilling->getPackage($subscription['package_id']);
                
                try {
                    $mpesa = new \App\Mpesa();
                    if ($mpesa->isConfigured()) {
                        $result = $mpesa->stkPush($phone, $package['price'], $subscription['username'], "Internet Renewal");
                        if ($result && ($result['ResponseCode'] ?? '') == '0') {
                            $message = 'Payment request sent to your phone. Enter your M-Pesa PIN.';
                            $messageType = 'success';
                        } else {
                            $message = 'Payment request failed: ' . ($result['errorMessage'] ?? 'Unknown error');
                            $messageType = 'danger';
                        }
                    }
                } catch (Exception $e) {
                    $message = 'Payment error: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;
                
            case 'update_wifi':
                if (!isset($_SESSION['portal_subscription_id'])) {
                    $message = 'Please login first';
                    $messageType = 'danger';
                    break;
                }
                
                $subscription = $radiusBilling->getSubscription($_SESSION['portal_subscription_id']);
                if (!$subscription) {
                    $message = 'Subscription not found';
                    $messageType = 'danger';
                    break;
                }
                
                $stmt = $db->prepare("SELECT o.*, o.tr069_device_id FROM huawei_onus o WHERE o.customer_id = ? AND o.tr069_device_id IS NOT NULL LIMIT 1");
                $stmt->execute([$subscription['customer_id']]);
                $onu = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$onu || empty($onu['tr069_device_id'])) {
                    $message = 'No WiFi device found for your account. Contact support.';
                    $messageType = 'warning';
                    break;
                }
                
                $wifiBand = $_POST['wifi_band'] ?? '2.4';
                $newSsid = trim($_POST['wifi_ssid'] ?? '');
                $newPassword = trim($_POST['wifi_password'] ?? '');
                
                if (empty($newSsid) && empty($newPassword)) {
                    $message = 'Please enter a WiFi name or password to update';
                    $messageType = 'warning';
                    break;
                }
                
                if (!empty($newPassword) && strlen($newPassword) < 8) {
                    $message = 'WiFi password must be at least 8 characters';
                    $messageType = 'danger';
                    break;
                }
                
                if (!empty($newSsid) && strlen($newSsid) < 1) {
                    $message = 'WiFi name cannot be empty';
                    $messageType = 'danger';
                    break;
                }
                
                try {
                    if ($wifiBand === '5') {
                        $result = $genieACS->setWiFi5GSettings(
                            $onu['tr069_device_id'],
                            $newSsid ?: '',
                            $newPassword ?: ''
                        );
                    } else {
                        $result = $genieACS->setWiFiSettings(
                            $onu['tr069_device_id'],
                            $newSsid ?: '',
                            $newPassword ?: ''
                        );
                    }
                    
                    if ($result['success']) {
                        $message = 'WiFi settings updated successfully! Changes may take a few minutes to apply.';
                        $messageType = 'success';
                    } else {
                        $message = 'Failed to update WiFi: ' . ($result['error'] ?? 'Unknown error');
                        $messageType = 'danger';
                    }
                } catch (Exception $e) {
                    $message = 'Error updating WiFi: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;
                
            case 'create_ticket':
                if (!isset($_SESSION['portal_subscription_id'])) {
                    $message = 'Please login first';
                    $messageType = 'danger';
                    break;
                }
                
                $subscription = $radiusBilling->getSubscription($_SESSION['portal_subscription_id']);
                if (!$subscription) {
                    $message = 'Subscription not found';
                    $messageType = 'danger';
                    break;
                }
                
                $subject = trim($_POST['subject'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $category = $_POST['category'] ?? 'complaint';
                
                if (empty($subject) || empty($description)) {
                    $message = 'Please fill in both subject and description';
                    $messageType = 'danger';
                    break;
                }
                
                try {
                    $ticketNumber = 'TKT-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    $stmt = $db->prepare("
                        INSERT INTO tickets (ticket_number, customer_id, subject, description, category, priority, status, source, created_at)
                        VALUES (?, ?, ?, ?, ?, 'medium', 'open', 'portal', NOW())
                    ");
                    $stmt->execute([
                        $ticketNumber,
                        $subscription['customer_id'],
                        $subject,
                        $description,
                        $category
                    ]);
                    
                    $message = "Ticket #{$ticketNumber} created successfully. We'll respond as soon as possible.";
                    $messageType = 'success';
                } catch (Exception $e) {
                    $message = 'Error creating ticket: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;
                
            case 'add_ticket_comment':
                if (!isset($_SESSION['portal_subscription_id'])) {
                    $message = 'Please login first';
                    $messageType = 'danger';
                    break;
                }
                
                $subscription = $radiusBilling->getSubscription($_SESSION['portal_subscription_id']);
                $ticketId = (int)($_POST['ticket_id'] ?? 0);
                $comment = trim($_POST['comment'] ?? '');
                
                if (empty($comment)) {
                    $message = 'Please enter a comment';
                    $messageType = 'danger';
                    break;
                }
                
                $stmt = $db->prepare("SELECT * FROM tickets WHERE id = ? AND customer_id = ?");
                $stmt->execute([$ticketId, $subscription['customer_id']]);
                $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$ticket) {
                    $message = 'Ticket not found';
                    $messageType = 'danger';
                    break;
                }
                
                try {
                    $stmt = $db->prepare("
                        INSERT INTO ticket_comments (ticket_id, comment, is_internal, created_at)
                        VALUES (?, ?, FALSE, NOW())
                    ");
                    $stmt->execute([$ticketId, $comment]);
                    
                    $db->prepare("UPDATE tickets SET updated_at = NOW() WHERE id = ?")->execute([$ticketId]);
                    
                    $message = 'Reply added successfully';
                    $messageType = 'success';
                } catch (Exception $e) {
                    $message = 'Error adding reply: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;
        }
    }
}

$tickets = [];

if (isset($_SESSION['portal_subscription_id'])) {
    $subscription = $radiusBilling->getSubscription($_SESSION['portal_subscription_id']);
    
    if ($subscription) {
        $stmt = $db->prepare("SELECT * FROM radius_sessions WHERE subscription_id = ? ORDER BY started_at DESC LIMIT 20");
        $stmt->execute([$subscription['id']]);
        $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt = $db->prepare("SELECT * FROM radius_invoices WHERE subscription_id = ? ORDER BY created_at DESC LIMIT 10");
        $stmt->execute([$subscription['id']]);
        $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt = $db->prepare("SELECT * FROM radius_usage_logs WHERE subscription_id = ? ORDER BY log_date DESC LIMIT 30");
        $stmt->execute([$subscription['id']]);
        $usageHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt = $db->prepare("SELECT * FROM radius_packages WHERE id = ?");
        $stmt->execute([$subscription['package_id']]);
        $package = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $db->prepare("SELECT * FROM customers WHERE id = ?");
        $stmt->execute([$subscription['customer_id']]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $db->prepare("SELECT o.*, o.tr069_device_id FROM huawei_onus o WHERE o.customer_id = ? ORDER BY o.id DESC LIMIT 1");
        $stmt->execute([$subscription['customer_id']]);
        $customerOnu = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($customerOnu && !empty($customerOnu['tr069_device_id'])) {
            $tr069DeviceId = $customerOnu['tr069_device_id'];
        }
        
        $routerIp = null;
        if ($customerOnu && !empty($customerOnu['ip_address'])) {
            $routerIp = $customerOnu['ip_address'];
        }
        
        $stmt = $db->prepare("SELECT t.*, 
            (SELECT COUNT(*) FROM ticket_comments tc WHERE tc.ticket_id = t.id) as comment_count
            FROM tickets t WHERE t.customer_id = ? ORDER BY t.created_at DESC LIMIT 50");
        $stmt->execute([$subscription['customer_id']]);
        $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Portal - ISP Billing</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background: #f5f7fa; font-family: 'Segoe UI', system-ui, sans-serif; }
        .portal-card { background: white; border-radius: 15px; box-shadow: 0 2px 15px rgba(0,0,0,0.08); }
        .stat-card { background: linear-gradient(135deg, #667eea, #764ba2); color: white; border-radius: 15px; }
        .stat-card.success { background: linear-gradient(135deg, #11998e, #38ef7d); }
        .stat-card.warning { background: linear-gradient(135deg, #f2994a, #f2c94c); }
        .stat-card.danger { background: linear-gradient(135deg, #eb3349, #f45c43); }
        .login-box { max-width: 400px; margin: 100px auto; }
        .usage-bar { height: 10px; border-radius: 5px; background: #e9ecef; overflow: hidden; }
        .usage-bar-fill { height: 100%; border-radius: 5px; transition: width 0.5s; }
        .nav-pills .nav-link.active { background: linear-gradient(135deg, #667eea, #764ba2); }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(135deg, #1e3a5f, #0d1b2a);">
        <div class="container">
            <a class="navbar-brand" href="?"><i class="bi bi-wifi me-2"></i>Customer Portal</a>
            <?php if ($subscription): ?>
            <div class="d-flex align-items-center">
                <span class="text-white-50 me-3">Welcome, <?= htmlspecialchars($customer['name'] ?? $subscription['username']) ?></span>
                <form method="post" class="d-inline">
                    <input type="hidden" name="action" value="logout">
                    <button type="submit" class="btn btn-outline-light btn-sm">Logout</button>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </nav>
    
    <div class="container py-4">
        <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if (!$subscription): ?>
        <div class="login-box">
            <div class="portal-card p-5">
                <div class="text-center mb-4">
                    <i class="bi bi-person-circle" style="font-size: 64px; color: #667eea;"></i>
                    <h4 class="mt-3">Customer Portal</h4>
                    <p class="text-muted">Login with your phone number and PPPoE password</p>
                </div>
                <form method="post">
                    <input type="hidden" name="action" value="login">
                    <div class="mb-3">
                        <label class="form-label">Phone Number</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                            <input type="tel" name="phone" class="form-control" 
                                   placeholder="0712 345 678" required
                                   pattern="[0-9+\s\-]{9,15}">
                        </div>
                        <small class="text-muted">Use the phone number registered with your account</small>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-key"></i></span>
                            <input type="password" name="password" id="loginPassword" class="form-control" 
                                   placeholder="Your PPPoE password" required>
                            <button type="button" class="btn btn-outline-secondary" onclick="toggleLoginPassword()">
                                <i class="bi bi-eye" id="loginPasswordIcon"></i>
                            </button>
                        </div>
                        <small class="text-muted">Your internet connection password</small>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg w-100">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Login
                    </button>
                </form>
                <hr class="my-4">
                <div class="text-center">
                    <small class="text-muted">
                        <i class="bi bi-info-circle me-1"></i>
                        Connected to our network? You'll be logged in automatically.
                    </small>
                </div>
            </div>
        </div>
        <script>
        function toggleLoginPassword() {
            const input = document.getElementById('loginPassword');
            const icon = document.getElementById('loginPasswordIcon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'bi bi-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'bi bi-eye';
            }
        }
        </script>
        <?php else: ?>
        
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="stat-card p-4 h-100">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="text-white-50 small">Package</div>
                            <div class="fs-5 fw-bold"><?= htmlspecialchars($package['name'] ?? 'N/A') ?></div>
                            <div class="small opacity-75"><?= $package['download_speed'] ?? '' ?>/<?= $package['upload_speed'] ?? '' ?></div>
                        </div>
                        <i class="bi bi-box fs-2 opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card <?= $subscription['status'] === 'active' ? 'success' : 'danger' ?> p-4 h-100">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="text-white-50 small">Status</div>
                            <div class="fs-5 fw-bold"><?= ucfirst($subscription['status']) ?></div>
                            <div class="small opacity-75">Expires: <?= $subscription['expiry_date'] ? date('M j, Y', strtotime($subscription['expiry_date'])) : 'N/A' ?></div>
                        </div>
                        <i class="bi bi-<?= $subscription['status'] === 'active' ? 'check-circle' : 'x-circle' ?> fs-2 opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card warning p-4 h-100">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="text-white-50 small">Data Used</div>
                            <div class="fs-5 fw-bold"><?= number_format($subscription['data_used_mb'] / 1024, 2) ?> GB</div>
                            <?php if ($package['data_quota_mb']): ?>
                            <div class="small opacity-75">of <?= number_format($package['data_quota_mb'] / 1024, 0) ?> GB</div>
                            <?php else: ?>
                            <div class="small opacity-75">Unlimited</div>
                            <?php endif; ?>
                        </div>
                        <i class="bi bi-bar-chart fs-2 opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card p-4 h-100">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="text-white-50 small">Balance</div>
                            <div class="fs-5 fw-bold">KES <?= number_format($subscription['credit_balance'] ?? 0) ?></div>
                            <div class="small opacity-75">Credit available</div>
                        </div>
                        <i class="bi bi-wallet2 fs-2 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if ($package['data_quota_mb']): ?>
        <div class="portal-card p-4 mb-4">
            <h6 class="mb-3">Data Usage</h6>
            <?php 
            $usagePercent = min(100, ($subscription['data_used_mb'] / $package['data_quota_mb']) * 100);
            $barColor = $usagePercent >= 100 ? '#dc3545' : ($usagePercent >= 80 ? '#ffc107' : '#28a745');
            ?>
            <div class="usage-bar">
                <div class="usage-bar-fill" style="width: <?= $usagePercent ?>%; background: <?= $barColor ?>;"></div>
            </div>
            <div class="d-flex justify-content-between mt-2 small text-muted">
                <span><?= number_format($subscription['data_used_mb'] / 1024, 2) ?> GB used</span>
                <span><?= number_format(max(0, $package['data_quota_mb'] - $subscription['data_used_mb']) / 1024, 2) ?> GB remaining</span>
            </div>
        </div>
        <?php endif; ?>
        
        <ul class="nav nav-pills mb-4 flex-wrap">
            <li class="nav-item"><a class="nav-link active" data-bs-toggle="pill" href="#overview">Overview</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="pill" href="#sessions">Sessions</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="pill" href="#invoices">Invoices</a></li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="pill" href="#tickets">
                    <i class="bi bi-ticket me-1"></i>Tickets
                    <?php $openTickets = count(array_filter($tickets, fn($t) => in_array($t['status'], ['open', 'in_progress']))); ?>
                    <?php if ($openTickets > 0): ?>
                    <span class="badge bg-danger"><?= $openTickets ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <?php if ($tr069DeviceId): ?>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="pill" href="#wifi"><i class="bi bi-wifi me-1"></i>WiFi Settings</a></li>
            <?php elseif ($routerIp): ?>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="pill" href="#router"><i class="bi bi-router me-1"></i>Router Settings</a></li>
            <?php endif; ?>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="pill" href="#payment">Make Payment</a></li>
        </ul>
        
        <div class="tab-content">
            <div class="tab-pane fade show active" id="overview">
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="portal-card p-4">
                            <h6 class="mb-3"><i class="bi bi-person me-2"></i>Account Details</h6>
                            <table class="table table-sm">
                                <tr><td class="text-muted">Username</td><td><code><?= htmlspecialchars($subscription['username']) ?></code></td></tr>
                                <tr><td class="text-muted">Customer</td><td><?= htmlspecialchars($customer['name'] ?? 'N/A') ?></td></tr>
                                <tr><td class="text-muted">Phone</td><td><?= htmlspecialchars($customer['phone'] ?? 'N/A') ?></td></tr>
                                <tr><td class="text-muted">Access Type</td><td><?= strtoupper($subscription['access_type']) ?></td></tr>
                                <tr><td class="text-muted">Static IP</td><td><?= $subscription['static_ip'] ?: 'Dynamic' ?></td></tr>
                                <tr><td class="text-muted">MAC Address</td><td><?= $subscription['mac_address'] ?: 'Not bound' ?></td></tr>
                            </table>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="portal-card p-4">
                            <h6 class="mb-3"><i class="bi bi-box me-2"></i>Package Details</h6>
                            <table class="table table-sm">
                                <tr><td class="text-muted">Package</td><td><?= htmlspecialchars($package['name'] ?? 'N/A') ?></td></tr>
                                <tr><td class="text-muted">Download Speed</td><td><?= $package['download_speed'] ?? 'N/A' ?></td></tr>
                                <tr><td class="text-muted">Upload Speed</td><td><?= $package['upload_speed'] ?? 'N/A' ?></td></tr>
                                <tr><td class="text-muted">Monthly Price</td><td>KES <?= number_format($package['price'] ?? 0) ?></td></tr>
                                <tr><td class="text-muted">Data Quota</td><td><?= $package['data_quota_mb'] ? number_format($package['data_quota_mb'] / 1024) . ' GB' : 'Unlimited' ?></td></tr>
                                <tr><td class="text-muted">Validity</td><td><?= $package['validity_days'] ?? 30 ?> days</td></tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="tab-pane fade" id="sessions">
                <div class="portal-card p-4">
                    <h6 class="mb-3"><i class="bi bi-clock-history me-2"></i>Recent Sessions</h6>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Started</th>
                                    <th>Ended</th>
                                    <th>Duration</th>
                                    <th>Download</th>
                                    <th>Upload</th>
                                    <th>IP Address</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sessions as $sess): ?>
                                <tr>
                                    <td><?= date('M j, H:i', strtotime($sess['started_at'] ?? $sess['session_start'])) ?></td>
                                    <td><?= $sess['stopped_at'] ? date('M j, H:i', strtotime($sess['stopped_at'])) : '<span class="badge bg-success">Active</span>' ?></td>
                                    <td>
                                        <?php 
                                        $start = strtotime($sess['started_at'] ?? $sess['session_start']);
                                        $end = $sess['stopped_at'] ? strtotime($sess['stopped_at']) : time();
                                        $dur = $end - $start;
                                        echo floor($dur/3600) . 'h ' . floor(($dur%3600)/60) . 'm';
                                        ?>
                                    </td>
                                    <td><?= number_format(($sess['input_octets'] ?? 0) / 1048576, 2) ?> MB</td>
                                    <td><?= number_format(($sess['output_octets'] ?? 0) / 1048576, 2) ?> MB</td>
                                    <td><code><?= $sess['framed_ip_address'] ?? '-' ?></code></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($sessions)): ?>
                                <tr><td colspan="6" class="text-center text-muted py-4">No sessions found</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="tab-pane fade" id="invoices">
                <div class="portal-card p-4">
                    <h6 class="mb-3"><i class="bi bi-receipt me-2"></i>Invoices & Payments</h6>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Invoice #</th>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Paid On</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($invoices as $inv): ?>
                                <tr>
                                    <td><?= htmlspecialchars($inv['invoice_number']) ?></td>
                                    <td><?= date('M j, Y', strtotime($inv['created_at'])) ?></td>
                                    <td>KES <?= number_format($inv['total_amount']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $inv['status'] === 'paid' ? 'success' : 'warning' ?>">
                                            <?= ucfirst($inv['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= $inv['paid_at'] ? date('M j, Y', strtotime($inv['paid_at'])) : '-' ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($invoices)): ?>
                                <tr><td colspan="5" class="text-center text-muted py-4">No invoices yet</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="tab-pane fade" id="tickets">
                <div class="row g-4">
                    <div class="col-lg-5">
                        <div class="portal-card p-4">
                            <h6 class="mb-3"><i class="bi bi-plus-circle me-2"></i>Create New Ticket</h6>
                            <form method="post">
                                <input type="hidden" name="action" value="create_ticket">
                                <div class="mb-3">
                                    <label class="form-label">Category</label>
                                    <select name="category" class="form-select" required>
                                        <option value="complaint">Complaint</option>
                                        <option value="technical">Technical Issue</option>
                                        <option value="billing">Billing Inquiry</option>
                                        <option value="request">Service Request</option>
                                        <option value="feedback">Feedback</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Subject</label>
                                    <input type="text" name="subject" class="form-control" placeholder="Brief description of your issue" required maxlength="200">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea name="description" class="form-control" rows="4" placeholder="Please describe your issue in detail..." required></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-send me-2"></i>Submit Ticket
                                </button>
                            </form>
                        </div>
                    </div>
                    <div class="col-lg-7">
                        <div class="portal-card p-4">
                            <h6 class="mb-3"><i class="bi bi-ticket me-2"></i>My Tickets</h6>
                            <?php if (empty($tickets)): ?>
                            <div class="text-center text-muted py-5">
                                <i class="bi bi-ticket" style="font-size: 48px;"></i>
                                <p class="mt-3">No tickets yet. Create one if you need help!</p>
                            </div>
                            <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($tickets as $t): ?>
                                <?php 
                                $statusColors = [
                                    'open' => 'primary',
                                    'in_progress' => 'info',
                                    'pending' => 'warning',
                                    'resolved' => 'success',
                                    'closed' => 'secondary'
                                ];
                                $statusColor = $statusColors[$t['status']] ?? 'secondary';
                                ?>
                                <div class="list-group-item list-group-item-action" data-bs-toggle="modal" data-bs-target="#ticketModal<?= $t['id'] ?>" style="cursor: pointer;">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?= htmlspecialchars($t['subject']) ?></h6>
                                        <small class="text-muted"><?= date('M j', strtotime($t['created_at'])) ?></small>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <span class="badge bg-<?= $statusColor ?>"><?= ucfirst(str_replace('_', ' ', $t['status'])) ?></span>
                                            <span class="badge bg-light text-dark"><?= ucfirst($t['category'] ?? 'general') ?></span>
                                        </div>
                                        <small class="text-muted">
                                            <i class="bi bi-chat-dots me-1"></i><?= $t['comment_count'] ?? 0 ?> replies
                                        </small>
                                    </div>
                                    <small class="text-muted">#<?= htmlspecialchars($t['ticket_number']) ?></small>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php 
            foreach ($tickets as $t): 
                $stmt = $db->prepare("SELECT * FROM ticket_comments WHERE ticket_id = ? ORDER BY created_at ASC");
                $stmt->execute([$t['id']]);
                $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>
            <div class="modal fade" id="ticketModal<?= $t['id'] ?>" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <div>
                                <h5 class="modal-title mb-1"><?= htmlspecialchars($t['subject']) ?></h5>
                                <small class="text-muted">#<?= htmlspecialchars($t['ticket_number']) ?> | <?= ucfirst($t['category'] ?? 'general') ?></small>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <span class="badge bg-<?= $statusColors[$t['status']] ?? 'secondary' ?> me-2"><?= ucfirst(str_replace('_', ' ', $t['status'])) ?></span>
                                <small class="text-muted">Created: <?= date('M j, Y g:i A', strtotime($t['created_at'])) ?></small>
                            </div>
                            
                            <div class="bg-light p-3 rounded mb-4">
                                <strong>Your Message:</strong>
                                <p class="mb-0 mt-2"><?= nl2br(htmlspecialchars($t['description'])) ?></p>
                            </div>
                            
                            <?php if (!empty($comments)): ?>
                            <h6 class="mb-3"><i class="bi bi-chat-dots me-2"></i>Conversation</h6>
                            <div class="ticket-comments" style="max-height: 300px; overflow-y: auto;">
                                <?php foreach ($comments as $c): ?>
                                <?php if (!$c['is_internal']): ?>
                                <div class="mb-3 p-3 rounded <?= $c['user_id'] ? 'bg-primary bg-opacity-10 ms-4' : 'bg-light me-4' ?>">
                                    <div class="d-flex justify-content-between mb-2">
                                        <strong><?= $c['user_id'] ? '<i class="bi bi-headset me-1"></i>Support' : '<i class="bi bi-person me-1"></i>You' ?></strong>
                                        <small class="text-muted"><?= date('M j, g:i A', strtotime($c['created_at'])) ?></small>
                                    </div>
                                    <p class="mb-0"><?= nl2br(htmlspecialchars($c['comment'])) ?></p>
                                </div>
                                <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!in_array($t['status'], ['closed', 'resolved'])): ?>
                            <form method="post" class="mt-4">
                                <input type="hidden" name="action" value="add_ticket_comment">
                                <input type="hidden" name="ticket_id" value="<?= $t['id'] ?>">
                                <div class="mb-3">
                                    <label class="form-label">Add a Reply</label>
                                    <textarea name="comment" class="form-control" rows="3" placeholder="Type your message..." required></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-send me-2"></i>Send Reply
                                </button>
                            </form>
                            <?php else: ?>
                            <div class="alert alert-success mt-3">
                                <i class="bi bi-check-circle me-2"></i>This ticket has been <?= $t['status'] ?>. Thank you for contacting us!
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php if ($routerIp && !$tr069DeviceId): ?>
            <div class="tab-pane fade" id="router">
                <div class="portal-card p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-router me-2 text-primary" style="font-size: 24px;"></i>
                            <div>
                                <h5 class="mb-0">Router Settings</h5>
                                <small class="text-muted"><?= htmlspecialchars($customerOnu['name'] ?? 'Router') ?> - <?= htmlspecialchars($routerIp) ?></small>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-outline-secondary btn-sm" onclick="refreshRouterFrame()">
                                <i class="bi bi-arrow-clockwise"></i> Refresh
                            </button>
                            <a href="http://<?= htmlspecialchars($routerIp) ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-box-arrow-up-right"></i> Open in New Tab
                            </a>
                        </div>
                    </div>
                    
                    <div id="router-frame-container" style="position: relative; min-height: 600px; background: #f8f9fa; border-radius: 8px; overflow: hidden;">
                        <div id="router-loading" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; z-index: 10;">
                            <div class="spinner-border text-primary mb-3" role="status"></div>
                            <p class="text-muted">Loading router interface...</p>
                        </div>
                        
                        <div id="router-error" style="display: none; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; max-width: 500px; z-index: 10;">
                            <i class="bi bi-exclamation-triangle text-warning" style="font-size: 48px;"></i>
                            <h5 class="mt-3">Unable to Load Router Interface</h5>
                            <p class="text-muted">This can happen if:</p>
                            <ul class="text-start text-muted small">
                                <li>Your router is powered off or disconnected</li>
                                <li>Network connectivity issue between server and router</li>
                                <li>The router is taking too long to respond</li>
                            </ul>
                            <div class="mt-3">
                                <button class="btn btn-primary" onclick="refreshRouterFrame()">
                                    <i class="bi bi-arrow-clockwise me-2"></i>Try Again
                                </button>
                            </div>
                            <div class="alert alert-info mt-3 text-start small">
                                <strong>Router IP:</strong> <code><?= htmlspecialchars($routerIp) ?></code>
                                <br><strong>Common logins:</strong> admin/admin or user/user
                            </div>
                        </div>
                        
                        <iframe id="router-iframe" 
                                src="about:blank"
                                style="width: 100%; height: 600px; border: none; display: none;"
                                loading="lazy"></iframe>
                    </div>
                    
                    <div class="row mt-3 g-3">
                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-body py-2">
                                    <small class="text-muted d-block">Device Info</small>
                                    <strong><?= htmlspecialchars($customerOnu['onu_type'] ?? 'Router') ?></strong>
                                    <span class="badge bg-<?= ($customerOnu['status'] ?? '') === 'online' ? 'success' : 'secondary' ?> ms-2">
                                        <?= ucfirst($customerOnu['status'] ?? 'Unknown') ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-body py-2">
                                    <small class="text-muted d-block">Default Login</small>
                                    <strong>Username:</strong> admin &nbsp; <strong>Password:</strong> admin
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($tr069DeviceId): ?>
            <div class="tab-pane fade" id="wifi">
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="portal-card p-4">
                            <div class="text-center mb-4">
                                <i class="bi bi-wifi" style="font-size: 48px; color: #667eea;"></i>
                                <h5 class="mt-3">WiFi Settings</h5>
                                <p class="text-muted">Change your WiFi name and password</p>
                            </div>
                            <form method="post">
                                <input type="hidden" name="action" value="update_wifi">
                                <div class="mb-3">
                                    <label class="form-label">WiFi Band</label>
                                    <select name="wifi_band" class="form-select">
                                        <option value="2.4">2.4 GHz</option>
                                        <option value="5">5 GHz</option>
                                    </select>
                                    <small class="text-muted">Select which WiFi band to configure</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">New WiFi Name (SSID)</label>
                                    <input type="text" name="wifi_ssid" class="form-control" 
                                           placeholder="Enter new WiFi name" maxlength="32">
                                    <small class="text-muted">Leave empty to keep current name</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">New WiFi Password</label>
                                    <div class="input-group">
                                        <input type="password" name="wifi_password" id="wifi_password" 
                                               class="form-control" placeholder="Enter new password" 
                                               minlength="8" maxlength="63">
                                        <button type="button" class="btn btn-outline-secondary" 
                                                onclick="toggleWifiPassword()">
                                            <i class="bi bi-eye" id="wifi_eye_icon"></i>
                                        </button>
                                    </div>
                                    <small class="text-muted">Minimum 8 characters. Leave empty to keep current password.</small>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-check-circle me-2"></i>Update WiFi Settings
                                </button>
                            </form>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="portal-card p-4">
                            <h6 class="mb-3"><i class="bi bi-info-circle me-2"></i>Your Device Info</h6>
                            <table class="table table-sm">
                                <tr><td class="text-muted">Device</td><td><?= htmlspecialchars($customerOnu['name'] ?? 'ONU') ?></td></tr>
                                <tr><td class="text-muted">Serial Number</td><td><code><?= htmlspecialchars($customerOnu['sn'] ?? 'N/A') ?></code></td></tr>
                                <tr><td class="text-muted">Model</td><td><?= htmlspecialchars($customerOnu['onu_type'] ?? 'N/A') ?></td></tr>
                                <tr><td class="text-muted">Status</td><td>
                                    <span class="badge bg-<?= ($customerOnu['status'] ?? '') === 'online' ? 'success' : 'secondary' ?>">
                                        <?= ucfirst($customerOnu['status'] ?? 'Unknown') ?>
                                    </span>
                                </td></tr>
                            </table>
                            
                            <div class="alert alert-info mt-4">
                                <i class="bi bi-lightbulb me-2"></i>
                                <strong>Tips:</strong>
                                <ul class="mb-0 mt-2 ps-3">
                                    <li>Changes take 1-2 minutes to apply</li>
                                    <li>Use a strong password with letters, numbers &amp; symbols</li>
                                    <li>Reconnect your devices after changing WiFi settings</li>
                                    <li>5 GHz is faster but has shorter range</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="tab-pane fade" id="payment">
                <div class="row justify-content-center">
                    <div class="col-md-6">
                        <div class="portal-card p-5">
                            <div class="text-center mb-4">
                                <i class="bi bi-phone" style="font-size: 48px; color: #28a745;"></i>
                                <h5 class="mt-3">Pay via M-Pesa</h5>
                                <p class="text-muted">Renew your subscription instantly</p>
                            </div>
                            <div class="alert alert-info">
                                <strong>Amount:</strong> KES <?= number_format($package['price'] ?? 0) ?><br>
                                <strong>Package:</strong> <?= htmlspecialchars($package['name'] ?? '') ?> (<?= $package['validity_days'] ?? 30 ?> days)
                            </div>
                            <form method="post">
                                <input type="hidden" name="action" value="pay">
                                <div class="mb-3">
                                    <label class="form-label">M-Pesa Phone Number</label>
                                    <input type="tel" name="phone" class="form-control form-control-lg" 
                                           value="<?= htmlspecialchars($customer['phone'] ?? '') ?>" 
                                           placeholder="0712345678" required>
                                </div>
                                <button type="submit" class="btn btn-success btn-lg w-100">
                                    <i class="bi bi-phone me-2"></i>Pay KES <?= number_format($package['price'] ?? 0) ?>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function toggleWifiPassword() {
        const input = document.getElementById('wifi_password');
        const icon = document.getElementById('wifi_eye_icon');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
        }
    }
    
    const routerIp = '<?= htmlspecialchars($routerIp ?? '') ?>';
    let routerFrameLoaded = false;
    
    function loadRouterFrame() {
        if (!routerIp) return;
        
        const iframe = document.getElementById('router-iframe');
        const loading = document.getElementById('router-loading');
        const error = document.getElementById('router-error');
        
        if (!iframe) return;
        
        loading.style.display = 'block';
        error.style.display = 'none';
        iframe.style.display = 'none';
        
        iframe.onload = function() {
            loading.style.display = 'none';
            iframe.style.display = 'block';
            routerFrameLoaded = true;
        };
        
        iframe.onerror = function() {
            showRouterError();
        };
        
        setTimeout(function() {
            if (!routerFrameLoaded) {
                showRouterError();
            }
        }, 15000);
        
        iframe.src = '/portal/router-proxy.php?path=/';
    }
    
    function showRouterError() {
        const loading = document.getElementById('router-loading');
        const error = document.getElementById('router-error');
        const iframe = document.getElementById('router-iframe');
        
        if (loading) loading.style.display = 'none';
        if (error) error.style.display = 'block';
        if (iframe) iframe.style.display = 'none';
    }
    
    function refreshRouterFrame() {
        routerFrameLoaded = false;
        loadRouterFrame();
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        const routerTab = document.querySelector('a[href="#router"]');
        if (routerTab) {
            routerTab.addEventListener('shown.bs.tab', function() {
                if (!routerFrameLoaded) {
                    loadRouterFrame();
                }
            });
        }
    });
    </script>
</body>
</html>
