<?php
session_start();
require_once __DIR__ . '/../src/License.php';

$config = require __DIR__ . '/../config/database.php';

try {
    $dsn = "pgsql:host={$config['host']};port={$config['port']};dbname={$config['database']}";
    $db = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

$license = new LicenseServer\License($db, $config);

try {
    $license->ensureSchema();
} catch (Exception $e) {
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if ($_POST['password'] === $config['admin_password']) {
        $_SESSION['admin_logged_in'] = true;
    } else {
        $loginError = 'Invalid password';
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

if (!isset($_SESSION['admin_logged_in'])) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>License Server - Admin Login</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
        <style>
            body { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); min-height: 100vh; }
        </style>
    </head>
    <body class="d-flex align-items-center justify-content-center">
        <div class="col-md-4 col-lg-3">
            <div class="card shadow-lg border-0">
                <div class="card-header bg-primary text-white text-center py-4">
                    <i class="bi bi-shield-lock-fill" style="font-size: 2.5rem;"></i>
                    <h4 class="mt-2 mb-0">License Server</h4>
                </div>
                <div class="card-body p-4">
                    <?php if (isset($loginError)): ?>
                    <div class="alert alert-danger py-2"><?= $loginError ?></div>
                    <?php endif; ?>
                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label">Admin Password</label>
                            <input type="password" name="password" class="form-control" required autofocus>
                        </div>
                        <button type="submit" name="login" class="btn btn-primary w-100">Login</button>
                    </form>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['ajax']) {
        case 'server_detail':
            $server = $license->getServerDetail((int)$_GET['id']);
            if (!$server) {
                echo json_encode(['error' => 'Not found']);
                exit;
            }
            $history = $license->getServerStatsHistory((int)$_GET['id']);
            echo json_encode(['server' => $server, 'history' => $history]);
            exit;

        case 'deactivate_server':
            $input = json_decode(file_get_contents('php://input'), true);
            $result = $license->deactivateServer((int)$input['id'], $input['reason'] ?? 'Admin deactivation');
            echo json_encode(['success' => $result]);
            exit;
            
        case 'update_logs':
            $logs = $license->getUpdateLogs((int)$_GET['update_id']);
            echo json_encode($logs);
            exit;
    }
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? null;
$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {
        case 'create_customer':
            $stmt = $db->prepare("INSERT INTO license_customers (name, email, company, phone, notes) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$_POST['name'], $_POST['email'], $_POST['company'], $_POST['phone'], $_POST['notes']]);
            $message = 'Customer created successfully';
            break;
            
        case 'edit_customer':
            $license->editCustomer((int)$_POST['customer_id'], $_POST);
            $message = 'Customer updated successfully';
            break;

        case 'delete_customer':
            if ($license->deleteCustomer((int)$_POST['customer_id'])) {
                $message = 'Customer deleted';
            } else {
                $message = 'Cannot delete customer with active licenses';
                $messageType = 'warning';
            }
            break;
            
        case 'create_license':
            $result = $license->createLicense($_POST);
            if ($result) {
                $message = 'License created: ' . $result['license_key'];
            }
            break;

        case 'edit_license':
            $license->editLicense((int)$_POST['license_id'], $_POST);
            $message = 'License updated successfully';
            break;
            
        case 'extend_license':
            $license->extendLicense((int)$_POST['license_id'], (int)$_POST['months']);
            $message = 'License extended by ' . $_POST['months'] . ' months';
            break;
            
        case 'suspend_license':
            $license->suspendLicense((int)$_POST['license_id'], $_POST['reason']);
            $message = 'License suspended';
            break;
            
        case 'unsuspend_license':
            $license->unsuspendLicense((int)$_POST['license_id']);
            $message = 'License unsuspended';
            break;
            
        case 'create_tier':
            $features = [];
            if (!empty($_POST['features'])) {
                foreach (explode(',', $_POST['features']) as $f) {
                    $f = trim($f);
                    if ($f) $features[$f] = true;
                }
            }
            $stmt = $db->prepare("
                INSERT INTO license_tiers (product_id, code, name, max_users, max_customers, max_onus, max_olts, max_subscribers, features, price_monthly, price_yearly)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $_POST['product_id'],
                strtolower(str_replace(' ', '_', $_POST['name'])),
                $_POST['name'],
                (int)$_POST['max_users'] ?: 0,
                (int)$_POST['max_customers'] ?: 0,
                (int)$_POST['max_onus'] ?: 0,
                (int)$_POST['max_olts'] ?: 0,
                (int)$_POST['max_subscribers'] ?: 0,
                json_encode($features),
                (float)$_POST['price_monthly'],
                (float)$_POST['price_yearly']
            ]);
            $message = 'Tier created successfully';
            break;
            
        case 'update_tier':
            $features = [];
            if (!empty($_POST['features'])) {
                foreach (explode(',', $_POST['features']) as $f) {
                    $f = trim($f);
                    if ($f) $features[$f] = true;
                }
            }
            $stmt = $db->prepare("
                UPDATE license_tiers SET 
                    name = ?, max_users = ?, max_customers = ?, max_onus = ?,
                    max_olts = ?, max_subscribers = ?,
                    features = ?, price_monthly = ?, price_yearly = ?, is_active = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $_POST['name'],
                (int)$_POST['max_users'] ?: 0,
                (int)$_POST['max_customers'] ?: 0,
                (int)$_POST['max_onus'] ?: 0,
                (int)$_POST['max_olts'] ?: 0,
                (int)$_POST['max_subscribers'] ?: 0,
                json_encode($features),
                (float)$_POST['price_monthly'],
                (float)$_POST['price_yearly'],
                isset($_POST['is_active']) ? 1 : 0,
                $_POST['tier_id']
            ]);
            $message = 'Tier updated successfully';
            break;
            
        case 'delete_tier':
            $checkStmt = $db->prepare("SELECT COUNT(*) FROM licenses WHERE tier_id = ?");
            $checkStmt->execute([$_POST['tier_id']]);
            if ($checkStmt->fetchColumn() > 0) {
                $message = 'Cannot delete tier: it has active licenses. Deactivate instead.';
                $messageType = 'warning';
            } else {
                $stmt = $db->prepare("DELETE FROM license_tiers WHERE id = ?");
                $stmt->execute([$_POST['tier_id']]);
                $message = 'Tier deleted successfully';
            }
            break;

        case 'create_update':
            $result = $license->createUpdate($_POST);
            if ($result) {
                $message = 'Update v' . $_POST['version'] . ' created';
                if (!empty($_POST['is_published'])) {
                    $message .= ' and published';
                }
            }
            break;

        case 'publish_update':
            $license->publishUpdate((int)$_POST['update_id']);
            $message = 'Update published';
            break;

        case 'unpublish_update':
            $license->unpublishUpdate((int)$_POST['update_id']);
            $message = 'Update unpublished';
            break;

        case 'delete_update':
            $license->deleteUpdate((int)$_POST['update_id']);
            $message = 'Update deleted';
            break;
    }
}

$stats = $license->getStats();
$licenses = $license->getAllLicenses();
$customers = $db->query("SELECT * FROM license_customers ORDER BY name")->fetchAll();
$products = $db->query("SELECT * FROM license_products WHERE is_active = TRUE")->fetchAll();
$tiers = $db->query("SELECT t.*, p.name as product_name FROM license_tiers t LEFT JOIN license_products p ON t.product_id = p.id ORDER BY t.price_monthly")->fetchAll();
$activeTiers = array_filter($tiers, fn($t) => $t['is_active']);
$servers = $license->getAllServers();
$updates = $license->getAllUpdates();

$payments = $db->query("
    SELECT p.*, l.license_key, c.name as customer_name 
    FROM license_payments p 
    LEFT JOIN licenses l ON p.license_id = l.id
    LEFT JOIN license_customers c ON l.customer_id = c.id
    ORDER BY p.created_at DESC LIMIT 50
")->fetchAll();

$paymentStats = $db->query("
    SELECT 
        COUNT(*) as total_payments,
        SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as total_revenue,
        SUM(CASE WHEN status = 'completed' AND created_at > NOW() - INTERVAL '30 days' THEN amount ELSE 0 END) as monthly_revenue
    FROM license_payments
")->fetch();

$activeTab = $_GET['tab'] ?? 'dashboard';
?>
<!DOCTYPE html>
<html>
<head>
    <title>License Server - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root { --sidebar-width: 240px; }
        body { background: #f0f2f5; }
        .sidebar { position: fixed; top: 0; left: 0; width: var(--sidebar-width); height: 100vh; background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%); z-index: 1000; overflow-y: auto; }
        .sidebar .brand { padding: 1.5rem 1rem; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar .brand h5 { color: #fff; margin: 0; font-size: 1.1rem; }
        .sidebar .nav-link { color: rgba(255,255,255,0.7); padding: 0.6rem 1rem; border-radius: 6px; margin: 2px 8px; font-size: 0.9rem; transition: all 0.2s; }
        .sidebar .nav-link:hover { color: #fff; background: rgba(255,255,255,0.1); }
        .sidebar .nav-link.active { color: #fff; background: rgba(102,126,234,0.3); }
        .sidebar .nav-link i { width: 24px; }
        .main-content { margin-left: var(--sidebar-width); padding: 1.5rem; min-height: 100vh; }
        .stat-card { border: none; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .stat-card .stat-icon { width: 48px; height: 48px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; }
        .server-status { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
        .server-status.online { background: #28a745; box-shadow: 0 0 6px rgba(40,167,69,0.5); }
        .server-status.offline { background: #dc3545; }
        .server-status.stale { background: #ffc107; }
        .version-badge { font-size: 0.75rem; padding: 2px 8px; }
        .feature-badge { font-size: 0.72rem; margin: 1px; }
        .update-card { border-left: 4px solid; }
        .update-card.critical { border-left-color: #dc3545; }
        .update-card.major { border-left-color: #fd7e14; }
        .update-card.minor { border-left-color: #0d6efd; }
        .update-card.patch { border-left-color: #198754; }
        .table th { font-size: 0.85rem; font-weight: 600; text-transform: uppercase; color: #6c757d; border-bottom-width: 1px; }
        .table td { vertical-align: middle; }
        .empty-state { padding: 3rem; text-align: center; color: #adb5bd; }
        .empty-state i { font-size: 3rem; margin-bottom: 1rem; }
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main-content { margin-left: 0; }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="brand d-flex align-items-center gap-2">
            <i class="bi bi-shield-lock-fill text-primary" style="font-size: 1.5rem;"></i>
            <div>
                <h5>License Server</h5>
                <small class="text-muted">v2.0</small>
            </div>
        </div>
        <nav class="nav flex-column py-3">
            <a class="nav-link <?= $activeTab === 'dashboard' ? 'active' : '' ?>" href="?tab=dashboard">
                <i class="bi bi-grid-1x2 me-2"></i>Dashboard
            </a>
            <a class="nav-link <?= $activeTab === 'servers' ? 'active' : '' ?>" href="?tab=servers">
                <i class="bi bi-hdd-rack me-2"></i>Servers
                <span class="badge bg-info float-end"><?= count($servers) ?></span>
            </a>
            <a class="nav-link <?= $activeTab === 'licenses' ? 'active' : '' ?>" href="?tab=licenses">
                <i class="bi bi-key me-2"></i>Licenses
            </a>
            <a class="nav-link <?= $activeTab === 'customers' ? 'active' : '' ?>" href="?tab=customers">
                <i class="bi bi-people me-2"></i>Customers
            </a>
            <a class="nav-link <?= $activeTab === 'tiers' ? 'active' : '' ?>" href="?tab=tiers">
                <i class="bi bi-layers me-2"></i>Tiers
            </a>
            <a class="nav-link <?= $activeTab === 'updates' ? 'active' : '' ?>" href="?tab=updates">
                <i class="bi bi-cloud-arrow-up me-2"></i>Updates
                <?php $unpublished = count(array_filter($updates, fn($u) => !$u['is_published'])); if ($unpublished): ?>
                <span class="badge bg-warning float-end"><?= $unpublished ?></span>
                <?php endif; ?>
            </a>
            <a class="nav-link <?= $activeTab === 'payments' ? 'active' : '' ?>" href="?tab=payments">
                <i class="bi bi-credit-card me-2"></i>Payments
            </a>
            <hr class="mx-3 border-secondary">
            <a class="nav-link" href="?logout=1">
                <i class="bi bi-box-arrow-left me-2"></i>Logout
            </a>
        </nav>
    </div>
    
    <div class="main-content">
        <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if ($activeTab === 'dashboard'): ?>
        <h4 class="mb-4">Dashboard</h4>
        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6">
                <div class="card stat-card">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-key"></i></div>
                        <div>
                            <div class="text-muted small">Active Licenses</div>
                            <h4 class="mb-0"><?= $stats['active_licenses'] ?></h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card stat-card">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-hdd-rack"></i></div>
                        <div>
                            <div class="text-muted small">Online Servers</div>
                            <h4 class="mb-0"><?= $stats['online_now'] ?? 0 ?> <small class="text-muted fs-6">/ <?= $stats['total_activations'] ?></small></h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card stat-card">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-exclamation-triangle"></i></div>
                        <div>
                            <div class="text-muted small">Expired</div>
                            <h4 class="mb-0"><?= $stats['expired_licenses'] ?></h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card stat-card">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="stat-icon bg-info bg-opacity-10 text-info"><i class="bi bi-currency-exchange"></i></div>
                        <div>
                            <div class="text-muted small">Monthly Revenue</div>
                            <h4 class="mb-0">KES <?= number_format($paymentStats['monthly_revenue'] ?? 0) ?></h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">Active Servers</h6>
                        <a href="?tab=servers" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr><th>Server</th><th>Customer</th><th>Version</th><th>Status</th><th>Last Seen</th></tr>
                            </thead>
                            <tbody>
                                <?php if (empty($servers)): ?>
                                <tr><td colspan="5" class="text-center text-muted py-4">No active servers</td></tr>
                                <?php endif; ?>
                                <?php foreach (array_slice($servers, 0, 8) as $srv): 
                                    $lastSeen = strtotime($srv['last_seen_at']);
                                    $isOnline = (time() - $lastSeen) < 300;
                                    $isStale = !$isOnline && (time() - $lastSeen) < 86400;
                                ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($srv['domain'] ?: $srv['server_hostname'] ?: $srv['server_ip']) ?></strong>
                                    </td>
                                    <td><?= htmlspecialchars($srv['customer_name'] ?? '-') ?></td>
                                    <td><span class="badge bg-secondary version-badge"><?= htmlspecialchars($srv['app_version'] ?: 'N/A') ?></span></td>
                                    <td><span class="server-status <?= $isOnline ? 'online' : ($isStale ? 'stale' : 'offline') ?>"></span> <?= $isOnline ? 'Online' : ($isStale ? 'Stale' : 'Offline') ?></td>
                                    <td><small class="text-muted"><?= timeAgo($lastSeen) ?></small></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card mb-3">
                    <div class="card-header bg-white"><h6 class="mb-0">Quick Stats</h6></div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2"><span class="text-muted">Total Customers</span><strong><?= $stats['total_customers'] ?></strong></div>
                        <div class="d-flex justify-content-between mb-2"><span class="text-muted">Active Today</span><strong><?= $stats['active_today'] ?></strong></div>
                        <div class="d-flex justify-content-between mb-2"><span class="text-muted">Total Revenue</span><strong>KES <?= number_format($paymentStats['total_revenue'] ?? 0) ?></strong></div>
                        <div class="d-flex justify-content-between"><span class="text-muted">Published Updates</span><strong><?= count(array_filter($updates, fn($u) => $u['is_published'])) ?></strong></div>
                    </div>
                </div>
                <?php $latestUpdate = !empty($updates) ? $updates[0] : null; if ($latestUpdate): ?>
                <div class="card">
                    <div class="card-header bg-white"><h6 class="mb-0">Latest Release</h6></div>
                    <div class="card-body">
                        <h5>v<?= htmlspecialchars($latestUpdate['version']) ?></h5>
                        <p class="text-muted mb-1"><?= htmlspecialchars($latestUpdate['title']) ?></p>
                        <span class="badge bg-<?= $latestUpdate['is_published'] ? 'success' : 'warning' ?>"><?= $latestUpdate['is_published'] ? 'Published' : 'Draft' ?></span>
                        <?php if ($latestUpdate['is_critical']): ?><span class="badge bg-danger">Critical</span><?php endif; ?>
                        <div class="mt-2"><small class="text-muted"><?= $latestUpdate['install_count'] ?> installations</small></div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($activeTab === 'servers'): ?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="mb-0">Server Management</h4>
            <div>
                <span class="badge bg-success me-2"><span class="server-status online me-1"></span> <?= count(array_filter($servers, fn($s) => (time() - strtotime($s['last_seen_at'])) < 300)) ?> Online</span>
                <span class="badge bg-warning me-2"><?= count(array_filter($servers, fn($s) => (time() - strtotime($s['last_seen_at'])) >= 300 && (time() - strtotime($s['last_seen_at'])) < 86400)) ?> Stale</span>
                <span class="badge bg-danger"><?= count(array_filter($servers, fn($s) => (time() - strtotime($s['last_seen_at'])) >= 86400)) ?> Offline</span>
            </div>
        </div>
        
        <?php if (empty($servers)): ?>
        <div class="card">
            <div class="card-body empty-state">
                <i class="bi bi-hdd-rack d-block"></i>
                <h5>No Active Servers</h5>
                <p>Servers will appear here when clients activate their licenses.</p>
            </div>
        </div>
        <?php else: ?>
        <div class="card">
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>Server</th>
                            <th>Customer</th>
                            <th>Tier</th>
                            <th>Version</th>
                            <th>Usage</th>
                            <th>System</th>
                            <th>Last Seen</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($servers as $srv): 
                            $lastSeen = strtotime($srv['last_seen_at']);
                            $isOnline = (time() - $lastSeen) < 300;
                            $isStale = !$isOnline && (time() - $lastSeen) < 86400;
                            $statusClass = $isOnline ? 'online' : ($isStale ? 'stale' : 'offline');
                            $statusText = $isOnline ? 'Online' : ($isStale ? 'Stale' : 'Offline');
                        ?>
                        <tr>
                            <td><span class="server-status <?= $statusClass ?>"></span> <small><?= $statusText ?></small></td>
                            <td>
                                <strong><?= htmlspecialchars($srv['domain'] ?: 'N/A') ?></strong><br>
                                <small class="text-muted"><?= htmlspecialchars($srv['server_ip'] ?? '') ?></small>
                            </td>
                            <td>
                                <?= htmlspecialchars($srv['customer_name'] ?? '-') ?><br>
                                <small class="text-muted"><?= htmlspecialchars($srv['company'] ?? '') ?></small>
                            </td>
                            <td><span class="badge bg-info"><?= htmlspecialchars($srv['tier_name'] ?? 'N/A') ?></span></td>
                            <td>
                                <span class="badge bg-<?= $srv['app_version'] ? 'secondary' : 'warning' ?> version-badge">
                                    <?= htmlspecialchars($srv['app_version'] ?: 'Unknown') ?>
                                </span>
                            </td>
                            <td>
                                <small>
                                    <i class="bi bi-people" title="Users"></i> <?= $srv['user_count'] ?: 0 ?>
                                    <i class="bi bi-person-badge ms-1" title="Customers"></i> <?= $srv['customer_count'] ?: 0 ?>
                                    <i class="bi bi-router ms-1" title="ONUs"></i> <?= $srv['onu_count'] ?: 0 ?>
                                </small>
                            </td>
                            <td>
                                <small class="text-muted">
                                    PHP <?= htmlspecialchars($srv['php_version'] ?: '?') ?><br>
                                    <?= htmlspecialchars(substr($srv['os_info'] ?? '', 0, 30)) ?>
                                </small>
                            </td>
                            <td><small class="text-muted" title="<?= $srv['last_seen_at'] ?>"><?= timeAgo($lastSeen) ?></small></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" onclick="showServerDetail(<?= $srv['id'] ?>)" title="View Details">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" onclick="deactivateServer(<?= $srv['id'] ?>, '<?= htmlspecialchars($srv['domain'] ?? $srv['server_ip'] ?? '') ?>')" title="Deactivate">
                                    <i class="bi bi-x-circle"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <div class="modal fade" id="serverDetailModal" tabindex="-1">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Server Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" id="serverDetailContent">
                        <div class="text-center py-4"><div class="spinner-border text-primary"></div></div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($activeTab === 'licenses'): ?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="mb-0">Licenses</h4>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newLicenseModal">
                <i class="bi bi-plus-lg me-1"></i>New License
            </button>
        </div>
        <div class="card">
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr><th>License Key</th><th>Customer</th><th>Tier</th><th>Activations</th><th>Expires</th><th>Status</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($licenses)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No licenses yet</td></tr>
                        <?php endif; ?>
                        <?php foreach ($licenses as $lic): ?>
                        <tr>
                            <td><code class="user-select-all"><?= htmlspecialchars($lic['license_key']) ?></code></td>
                            <td><?= htmlspecialchars($lic['customer_name'] ?? 'N/A') ?><br><small class="text-muted"><?= htmlspecialchars($lic['company'] ?? '') ?></small></td>
                            <td><span class="badge bg-info"><?= htmlspecialchars($lic['tier_name'] ?? 'N/A') ?></span></td>
                            <td><?= $lic['active_activations'] ?>/<?= $lic['max_activations'] ?></td>
                            <td><?= $lic['expires_at'] ? date('M j, Y', strtotime($lic['expires_at'])) : '<span class="badge bg-success">Lifetime</span>' ?></td>
                            <td>
                                <?php if ($lic['is_suspended']): ?>
                                <span class="badge bg-danger">Suspended</span>
                                <?php elseif (!$lic['is_active']): ?>
                                <span class="badge bg-secondary">Inactive</span>
                                <?php elseif ($lic['expires_at'] && strtotime($lic['expires_at']) < time()): ?>
                                <span class="badge bg-warning">Expired</span>
                                <?php else: ?>
                                <span class="badge bg-success">Active</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <?php if ($lic['is_suspended']): ?>
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="action" value="unsuspend_license">
                                        <input type="hidden" name="license_id" value="<?= $lic['id'] ?>">
                                        <button type="submit" class="btn btn-outline-success btn-sm" title="Unsuspend"><i class="bi bi-play-circle"></i></button>
                                    </form>
                                    <?php else: ?>
                                    <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#suspendModal" data-license-id="<?= $lic['id'] ?>" title="Suspend"><i class="bi bi-pause-circle"></i></button>
                                    <?php endif; ?>
                                    <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#extendModal" data-license-id="<?= $lic['id'] ?>" data-key="<?= htmlspecialchars($lic['license_key']) ?>" title="Extend"><i class="bi bi-calendar-plus"></i></button>
                                    <button class="btn btn-outline-secondary btn-sm" onclick="editLicense(<?= htmlspecialchars(json_encode($lic)) ?>)" title="Edit"><i class="bi bi-pencil"></i></button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($activeTab === 'customers'): ?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="mb-0">Customers</h4>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newCustomerModal">
                <i class="bi bi-plus-lg me-1"></i>New Customer
            </button>
        </div>
        <div class="card">
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Name</th><th>Company</th><th>Email</th><th>Phone</th><th>Created</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php if (empty($customers)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No customers yet</td></tr>
                        <?php endif; ?>
                        <?php foreach ($customers as $cust): ?>
                        <tr>
                            <td><?= htmlspecialchars($cust['name']) ?></td>
                            <td><?= htmlspecialchars($cust['company'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($cust['email']) ?></td>
                            <td><?= htmlspecialchars($cust['phone'] ?? '-') ?></td>
                            <td><?= date('M j, Y', strtotime($cust['created_at'])) ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" onclick='editCustomer(<?= htmlspecialchars(json_encode($cust)) ?>)' title="Edit"><i class="bi bi-pencil"></i></button>
                                <form method="post" class="d-inline" onsubmit="return confirm('Delete this customer?')">
                                    <input type="hidden" name="action" value="delete_customer">
                                    <input type="hidden" name="customer_id" value="<?= $cust['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($activeTab === 'tiers'): ?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="mb-0">License Tiers</h4>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newTierModal">
                <i class="bi bi-plus-lg me-1"></i>Add Tier
            </button>
        </div>
        <div class="row">
            <?php foreach ($tiers as $tier): ?>
            <div class="col-md-4 mb-3">
                <div class="card h-100 <?= $tier['is_active'] ? '' : 'border-secondary opacity-50' ?>">
                    <div class="card-header d-flex justify-content-between">
                        <strong><?= htmlspecialchars($tier['name']) ?></strong>
                        <?php if (!$tier['is_active']): ?><span class="badge bg-secondary">Inactive</span><?php endif; ?>
                    </div>
                    <div class="card-body">
                        <h4 class="text-primary mb-1">KES <?= number_format($tier['price_monthly']) ?><small class="text-muted">/mo</small></h4>
                        <small class="text-muted">KES <?= number_format($tier['price_yearly']) ?>/year</small>
                        <hr>
                        <ul class="list-unstyled mb-3">
                            <li><i class="bi bi-people me-2"></i><?= $tier['max_users'] ?: 'Unlimited' ?> Users</li>
                            <li><i class="bi bi-person-badge me-2"></i><?= $tier['max_customers'] ?: 'Unlimited' ?> Customers</li>
                            <li><i class="bi bi-wifi me-2"></i><?= $tier['max_subscribers'] ?: 'Unlimited' ?> Subscribers</li>
                            <li><i class="bi bi-router me-2"></i><?= $tier['max_onus'] ?: 'Unlimited' ?> ONUs</li>
                            <li><i class="bi bi-hdd-rack me-2"></i><?= $tier['max_olts'] ?: 'Unlimited' ?> OLTs</li>
                        </ul>
                        <div class="mb-2">
                            <?php 
                            $features = json_decode($tier['features'] ?: '{}', true);
                            foreach ($features as $f => $enabled):
                                if ($enabled):
                            ?>
                            <span class="badge bg-light text-dark feature-badge"><?= htmlspecialchars($f) ?></span>
                            <?php endif; endforeach; ?>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent">
                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editTierModal"
                            data-tier='<?= htmlspecialchars(json_encode($tier)) ?>'><i class="bi bi-pencil me-1"></i>Edit</button>
                        <form method="post" class="d-inline" onsubmit="return confirm('Delete this tier?')">
                            <input type="hidden" name="action" value="delete_tier">
                            <input type="hidden" name="tier_id" value="<?= $tier['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($activeTab === 'updates'): ?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="mb-0">Software Updates</h4>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newUpdateModal">
                <i class="bi bi-plus-lg me-1"></i>New Release
            </button>
        </div>

        <?php if (empty($updates)): ?>
        <div class="card">
            <div class="card-body empty-state">
                <i class="bi bi-cloud-arrow-up d-block"></i>
                <h5>No Releases Yet</h5>
                <p>Create your first software release to start managing updates for your client servers.</p>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newUpdateModal">Create Release</button>
            </div>
        </div>
        <?php else: ?>
        <?php foreach ($updates as $upd): ?>
        <div class="card mb-3 update-card <?= htmlspecialchars($upd['release_type']) ?>">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h5 class="mb-1">
                            v<?= htmlspecialchars($upd['version']) ?> — <?= htmlspecialchars($upd['title']) ?>
                            <?php if ($upd['is_critical']): ?><span class="badge bg-danger ms-2">Critical</span><?php endif; ?>
                        </h5>
                        <div class="mb-2">
                            <span class="badge bg-<?= $upd['is_published'] ? 'success' : 'warning' ?>"><?= $upd['is_published'] ? 'Published' : 'Draft' ?></span>
                            <span class="badge bg-secondary"><?= htmlspecialchars($upd['release_type']) ?></span>
                            <span class="badge bg-light text-dark"><?= htmlspecialchars($upd['product_name'] ?? '') ?></span>
                            <?php if ($upd['published_at']): ?>
                            <small class="text-muted ms-2">Published <?= date('M j, Y', strtotime($upd['published_at'])) ?></small>
                            <?php endif; ?>
                        </div>
                        <?php if ($upd['changelog']): ?>
                        <div class="mb-2" style="white-space: pre-line; font-size: 0.9rem; color: #555; max-height: 150px; overflow-y: auto;"><?= htmlspecialchars($upd['changelog']) ?></div>
                        <?php endif; ?>
                        <small class="text-muted">
                            <i class="bi bi-download me-1"></i><?= $upd['install_count'] ?> installations
                            <?php if ($upd['min_php_version']): ?> | PHP >= <?= htmlspecialchars($upd['min_php_version']) ?><?php endif; ?>
                            <?php if ($upd['min_node_version']): ?> | Node >= <?= htmlspecialchars($upd['min_node_version']) ?><?php endif; ?>
                            <?php if ($upd['file_size']): ?> | <?= formatFileSize($upd['file_size']) ?><?php endif; ?>
                        </small>
                    </div>
                    <div class="btn-group btn-group-sm">
                        <?php if (!$upd['is_published']): ?>
                        <form method="post" class="d-inline">
                            <input type="hidden" name="action" value="publish_update">
                            <input type="hidden" name="update_id" value="<?= $upd['id'] ?>">
                            <button type="submit" class="btn btn-outline-success" title="Publish"><i class="bi bi-cloud-upload"></i></button>
                        </form>
                        <?php else: ?>
                        <form method="post" class="d-inline">
                            <input type="hidden" name="action" value="unpublish_update">
                            <input type="hidden" name="update_id" value="<?= $upd['id'] ?>">
                            <button type="submit" class="btn btn-outline-warning" title="Unpublish"><i class="bi bi-cloud-slash"></i></button>
                        </form>
                        <?php endif; ?>
                        <button class="btn btn-outline-info" onclick="showUpdateLogs(<?= $upd['id'] ?>, '<?= htmlspecialchars($upd['version']) ?>')" title="Install Logs"><i class="bi bi-list-check"></i></button>
                        <form method="post" class="d-inline" onsubmit="return confirm('Delete this release?')">
                            <input type="hidden" name="action" value="delete_update">
                            <input type="hidden" name="update_id" value="<?= $upd['id'] ?>">
                            <button type="submit" class="btn btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
        <?php endif; ?>

        <?php if ($activeTab === 'payments'): ?>
        <h4 class="mb-4">Payments</h4>
        <div class="card">
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Date</th><th>Customer</th><th>License</th><th>Amount</th><th>M-Pesa Receipt</th><th>Phone</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php if (empty($payments)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No payments yet</td></tr>
                        <?php endif; ?>
                        <?php foreach ($payments as $pay): ?>
                        <tr>
                            <td><?= date('M j, Y H:i', strtotime($pay['created_at'])) ?></td>
                            <td><?= htmlspecialchars($pay['customer_name'] ?? 'N/A') ?></td>
                            <td><code><?= htmlspecialchars(substr($pay['license_key'] ?? '', 0, 12)) ?>...</code></td>
                            <td><strong>KES <?= number_format($pay['amount']) ?></strong></td>
                            <td><code><?= htmlspecialchars($pay['mpesa_receipt'] ?? '-') ?></code></td>
                            <td><?= htmlspecialchars($pay['phone_number'] ?? '-') ?></td>
                            <td>
                                <?php if ($pay['status'] === 'completed'): ?>
                                <span class="badge bg-success">Completed</span>
                                <?php elseif ($pay['status'] === 'pending'): ?>
                                <span class="badge bg-warning">Pending</span>
                                <?php else: ?>
                                <span class="badge bg-danger"><?= htmlspecialchars($pay['status']) ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- New License Modal -->
    <div class="modal fade" id="newLicenseModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="action" value="create_license">
                    <div class="modal-header"><h5 class="modal-title">Create License</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Customer</label>
                            <select name="customer_id" class="form-select" required>
                                <option value="">Select Customer</option>
                                <?php foreach ($customers as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['company'] ?? '') ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Product</label>
                            <select name="product_id" class="form-select" required>
                                <?php foreach ($products as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tier</label>
                            <select name="tier_id" class="form-select" required>
                                <?php foreach ($activeTiers as $t): ?>
                                <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?> (KES <?= number_format($t['price_monthly']) ?>/mo)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Duration (months, 0 = lifetime)</label>
                            <input type="number" name="duration_months" class="form-control" value="12" min="0">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Max Activations</label>
                            <input type="number" name="max_activations" class="form-control" value="1" min="1">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Domain Restriction (optional)</label>
                            <input type="text" name="domain_restriction" class="form-control" placeholder="e.g. crm.example.com">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create License</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit License Modal -->
    <div class="modal fade" id="editLicenseModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="action" value="edit_license">
                    <input type="hidden" name="license_id" id="editLicenseId">
                    <div class="modal-header"><h5 class="modal-title">Edit License</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Key</label>
                            <input type="text" id="editLicenseKey" class="form-control" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tier</label>
                            <select name="tier_id" id="editLicenseTier" class="form-select">
                                <?php foreach ($activeTiers as $t): ?>
                                <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Max Activations</label>
                            <input type="number" name="max_activations" id="editLicenseMaxAct" class="form-control" min="1">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Domain Restriction</label>
                            <input type="text" name="domain_restriction" id="editLicenseDomain" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Expires At</label>
                            <input type="datetime-local" name="expires_at" id="editLicenseExpiry" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" id="editLicenseNotes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Suspend Modal -->
    <div class="modal fade" id="suspendModal" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="action" value="suspend_license">
                    <input type="hidden" name="license_id" id="suspendLicenseId">
                    <div class="modal-header"><h5 class="modal-title">Suspend License</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Reason</label>
                            <textarea name="reason" class="form-control" rows="2" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Suspend</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Extend Modal -->
    <div class="modal fade" id="extendModal" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="action" value="extend_license">
                    <input type="hidden" name="license_id" id="extendLicenseId">
                    <div class="modal-header"><h5 class="modal-title">Extend License</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <p class="text-muted mb-2" id="extendLicenseInfo"></p>
                        <div class="mb-3">
                            <label class="form-label">Extend by (months)</label>
                            <select name="months" class="form-select">
                                <option value="1">1 Month</option>
                                <option value="3">3 Months</option>
                                <option value="6">6 Months</option>
                                <option value="12" selected>12 Months</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Extend</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- New Customer Modal -->
    <div class="modal fade" id="newCustomerModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="action" value="create_customer">
                    <div class="modal-header"><h5 class="modal-title">New Customer</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3"><label class="form-label">Name</label><input type="text" name="name" class="form-control" required></div>
                            <div class="col-md-6 mb-3"><label class="form-label">Company</label><input type="text" name="company" class="form-control"></div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
                            <div class="col-md-6 mb-3"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control"></div>
                        </div>
                        <div class="mb-3"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Customer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Customer Modal -->
    <div class="modal fade" id="editCustomerModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="action" value="edit_customer">
                    <input type="hidden" name="customer_id" id="editCustomerId">
                    <div class="modal-header"><h5 class="modal-title">Edit Customer</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3"><label class="form-label">Name</label><input type="text" name="name" id="editCustName" class="form-control" required></div>
                            <div class="col-md-6 mb-3"><label class="form-label">Company</label><input type="text" name="company" id="editCustCompany" class="form-control"></div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3"><label class="form-label">Email</label><input type="email" name="email" id="editCustEmail" class="form-control" required></div>
                            <div class="col-md-6 mb-3"><label class="form-label">Phone</label><input type="text" name="phone" id="editCustPhone" class="form-control"></div>
                        </div>
                        <div class="mb-3"><label class="form-label">Notes</label><textarea name="notes" id="editCustNotes" class="form-control" rows="2"></textarea></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- New Tier Modal -->
    <div class="modal fade" id="newTierModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="action" value="create_tier">
                    <div class="modal-header"><h5 class="modal-title">New Tier</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Product</label>
                            <select name="product_id" class="form-select" required>
                                <?php foreach ($products as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3"><label class="form-label">Name</label><input type="text" name="name" class="form-control" required></div>
                        <div class="row">
                            <div class="col-4 mb-3"><label class="form-label">Max Users (0=unlimited)</label><input type="number" name="max_users" class="form-control" value="0"></div>
                            <div class="col-4 mb-3"><label class="form-label">Max Customers</label><input type="number" name="max_customers" class="form-control" value="0"></div>
                            <div class="col-4 mb-3"><label class="form-label">Max Subscribers</label><input type="number" name="max_subscribers" class="form-control" value="0"></div>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3"><label class="form-label">Max ONUs</label><input type="number" name="max_onus" class="form-control" value="0"></div>
                            <div class="col-6 mb-3"><label class="form-label">Max OLTs</label><input type="number" name="max_olts" class="form-control" value="0"></div>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3"><label class="form-label">Price Monthly (KES)</label><input type="number" name="price_monthly" class="form-control" step="0.01" value="0"></div>
                            <div class="col-6 mb-3"><label class="form-label">Price Yearly (KES)</label><input type="number" name="price_yearly" class="form-control" step="0.01" value="0"></div>
                        </div>
                        <div class="mb-3"><label class="form-label">Features (comma-separated)</label><input type="text" name="features" class="form-control" placeholder="crm, tickets, oms, hr, inventory, accounting"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Tier</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Tier Modal -->
    <div class="modal fade" id="editTierModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="action" value="update_tier">
                    <input type="hidden" name="tier_id" id="editTierId">
                    <div class="modal-header"><h5 class="modal-title">Edit Tier</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <div class="mb-3"><label class="form-label">Name</label><input type="text" name="name" id="editTierName" class="form-control" required></div>
                        <div class="row">
                            <div class="col-4 mb-3"><label class="form-label">Max Users</label><input type="number" name="max_users" id="editTierMaxUsers" class="form-control"></div>
                            <div class="col-4 mb-3"><label class="form-label">Max Customers</label><input type="number" name="max_customers" id="editTierMaxCust" class="form-control"></div>
                            <div class="col-4 mb-3"><label class="form-label">Max Subscribers</label><input type="number" name="max_subscribers" id="editTierMaxSubs" class="form-control"></div>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3"><label class="form-label">Max ONUs</label><input type="number" name="max_onus" id="editTierMaxOnus" class="form-control"></div>
                            <div class="col-6 mb-3"><label class="form-label">Max OLTs</label><input type="number" name="max_olts" id="editTierMaxOlts" class="form-control"></div>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3"><label class="form-label">Price Monthly</label><input type="number" name="price_monthly" id="editTierPriceM" class="form-control" step="0.01"></div>
                            <div class="col-6 mb-3"><label class="form-label">Price Yearly</label><input type="number" name="price_yearly" id="editTierPriceY" class="form-control" step="0.01"></div>
                        </div>
                        <div class="mb-3"><label class="form-label">Features (comma-separated)</label><input type="text" name="features" id="editTierFeatures" class="form-control"></div>
                        <div class="form-check"><input type="checkbox" name="is_active" id="editTierActive" class="form-check-input" value="1"><label class="form-check-label" for="editTierActive">Active</label></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- New Update Modal -->
    <div class="modal fade" id="newUpdateModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="action" value="create_update">
                    <div class="modal-header"><h5 class="modal-title"><i class="bi bi-cloud-arrow-up me-2"></i>New Software Release</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Product</label>
                                <select name="product_id" class="form-select" required>
                                    <?php foreach ($products as $p): ?>
                                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?> (v<?= htmlspecialchars($p['current_version'] ?? '1.0.0') ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Version</label>
                                <input type="text" name="version" class="form-control" placeholder="1.2.3" required pattern="[0-9]+\.[0-9]+\.[0-9]+">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Release Type</label>
                                <select name="release_type" class="form-select">
                                    <option value="patch">Patch (bug fixes)</option>
                                    <option value="minor">Minor (new features)</option>
                                    <option value="major">Major (breaking changes)</option>
                                    <option value="critical">Critical (security fix)</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Title</label>
                            <input type="text" name="title" class="form-control" placeholder="e.g. Bug fixes and performance improvements" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Changelog</label>
                            <textarea name="changelog" class="form-control" rows="6" placeholder="- Fixed: Description&#10;- Added: Description&#10;- Changed: Description"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Download URL (optional)</label>
                                <input type="url" name="download_url" class="form-control" placeholder="https://...">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Min PHP Version</label>
                                <input type="text" name="min_php_version" class="form-control" value="8.1" placeholder="8.1">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Min Node Version</label>
                                <input type="text" name="min_node_version" class="form-control" value="18" placeholder="18">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">File Size (bytes)</label>
                                <input type="number" name="file_size" class="form-control" placeholder="Optional">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">SHA-256 Hash</label>
                                <input type="text" name="download_hash" class="form-control" placeholder="Optional">
                            </div>
                            <div class="col-md-4 mb-3 d-flex align-items-end gap-3">
                                <div class="form-check">
                                    <input type="checkbox" name="is_critical" class="form-check-input" id="newUpdateCritical" value="1">
                                    <label class="form-check-label" for="newUpdateCritical">Critical</label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" name="is_published" class="form-check-input" id="newUpdatePublish" value="1">
                                    <label class="form-check-label" for="newUpdatePublish">Publish Now</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Release</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Update Logs Modal -->
    <div class="modal fade" id="updateLogsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title" id="updateLogsTitle">Install Logs</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body" id="updateLogsContent">
                    <div class="text-center py-4"><div class="spinner-border text-primary"></div></div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.getElementById('suspendModal')?.addEventListener('show.bs.modal', function(e) {
        document.getElementById('suspendLicenseId').value = e.relatedTarget.dataset.licenseId;
    });

    document.getElementById('extendModal')?.addEventListener('show.bs.modal', function(e) {
        document.getElementById('extendLicenseId').value = e.relatedTarget.dataset.licenseId;
        document.getElementById('extendLicenseInfo').textContent = e.relatedTarget.dataset.key || '';
    });

    document.getElementById('editTierModal')?.addEventListener('show.bs.modal', function(e) {
        const tier = JSON.parse(e.relatedTarget.dataset.tier);
        document.getElementById('editTierId').value = tier.id;
        document.getElementById('editTierName').value = tier.name;
        document.getElementById('editTierMaxUsers').value = tier.max_users;
        document.getElementById('editTierMaxCust').value = tier.max_customers;
        document.getElementById('editTierMaxSubs').value = tier.max_subscribers || 0;
        document.getElementById('editTierMaxOnus').value = tier.max_onus;
        document.getElementById('editTierMaxOlts').value = tier.max_olts || 0;
        document.getElementById('editTierPriceM').value = tier.price_monthly;
        document.getElementById('editTierPriceY').value = tier.price_yearly;
        const features = JSON.parse(tier.features || '{}');
        document.getElementById('editTierFeatures').value = Object.keys(features).filter(k => features[k]).join(', ');
        document.getElementById('editTierActive').checked = tier.is_active;
    });

    function editLicense(lic) {
        document.getElementById('editLicenseId').value = lic.id;
        document.getElementById('editLicenseKey').value = lic.license_key;
        document.getElementById('editLicenseTier').value = lic.tier_id || '';
        document.getElementById('editLicenseMaxAct').value = lic.max_activations;
        document.getElementById('editLicenseDomain').value = lic.domain_restriction || '';
        document.getElementById('editLicenseNotes').value = lic.notes || '';
        if (lic.expires_at) {
            document.getElementById('editLicenseExpiry').value = lic.expires_at.replace(' ', 'T').substring(0, 16);
        } else {
            document.getElementById('editLicenseExpiry').value = '';
        }
        new bootstrap.Modal(document.getElementById('editLicenseModal')).show();
    }

    function editCustomer(cust) {
        document.getElementById('editCustomerId').value = cust.id;
        document.getElementById('editCustName').value = cust.name;
        document.getElementById('editCustCompany').value = cust.company || '';
        document.getElementById('editCustEmail').value = cust.email;
        document.getElementById('editCustPhone').value = cust.phone || '';
        document.getElementById('editCustNotes').value = cust.notes || '';
        new bootstrap.Modal(document.getElementById('editCustomerModal')).show();
    }

    async function showServerDetail(id) {
        const modal = new bootstrap.Modal(document.getElementById('serverDetailModal'));
        document.getElementById('serverDetailContent').innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
        modal.show();

        try {
            const resp = await fetch('?ajax=server_detail&id=' + id);
            const data = await resp.json();
            if (data.error) throw new Error(data.error);
            const s = data.server;
            const lastSeen = new Date(s.last_seen_at);
            const isOnline = (Date.now() - lastSeen.getTime()) < 300000;
            const statusClass = isOnline ? 'online' : ((Date.now() - lastSeen.getTime()) < 86400000 ? 'stale' : 'offline');
            const statusText = isOnline ? 'Online' : ((Date.now() - lastSeen.getTime()) < 86400000 ? 'Stale' : 'Offline');

            let html = `
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h5><span class="server-status ${statusClass} me-2"></span>${s.domain || s.server_hostname || s.server_ip}</h5>
                        <table class="table table-sm">
                            <tr><td class="text-muted">Status</td><td><span class="badge bg-${isOnline ? 'success' : 'danger'}">${statusText}</span></td></tr>
                            <tr><td class="text-muted">Customer</td><td>${s.customer_name || '-'} ${s.company ? '(' + s.company + ')' : ''}</td></tr>
                            <tr><td class="text-muted">Email</td><td>${s.customer_email || '-'}</td></tr>
                            <tr><td class="text-muted">Phone</td><td>${s.customer_phone || '-'}</td></tr>
                            <tr><td class="text-muted">Tier</td><td><span class="badge bg-info">${s.tier_name || 'N/A'}</span></td></tr>
                            <tr><td class="text-muted">License Key</td><td><code class="user-select-all">${s.license_key}</code></td></tr>
                            <tr><td class="text-muted">License Expires</td><td>${s.expires_at ? new Date(s.expires_at).toLocaleDateString() : 'Lifetime'}</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>System Information</h6>
                        <table class="table table-sm">
                            <tr><td class="text-muted">IP Address</td><td>${s.server_ip || '-'}</td></tr>
                            <tr><td class="text-muted">Hostname</td><td>${s.server_hostname || '-'}</td></tr>
                            <tr><td class="text-muted">App Version</td><td><span class="badge bg-secondary">${s.app_version || 'N/A'}</span>
                                ${s.latest_version && s.app_version && s.latest_version !== s.app_version ? '<span class="badge bg-warning ms-1">Update Available: v' + s.latest_version + '</span>' : ''}
                            </td></tr>
                            <tr><td class="text-muted">PHP Version</td><td>${s.php_version || '-'}</td></tr>
                            <tr><td class="text-muted">OS</td><td>${s.os_info || '-'}</td></tr>
                            <tr><td class="text-muted">Disk Usage</td><td>${s.disk_usage || '-'}</td></tr>
                            <tr><td class="text-muted">DB Size</td><td>${s.db_size || '-'}</td></tr>
                            <tr><td class="text-muted">Activated</td><td>${new Date(s.first_activated_at).toLocaleDateString()}</td></tr>
                            <tr><td class="text-muted">Last Seen</td><td>${lastSeen.toLocaleString()}</td></tr>
                        </table>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-3 text-center"><div class="card card-body py-2"><h4 class="mb-0">${s.user_count || 0}</h4><small class="text-muted">Users</small></div></div>
                    <div class="col-md-3 text-center"><div class="card card-body py-2"><h4 class="mb-0">${s.customer_count || 0}</h4><small class="text-muted">Customers</small></div></div>
                    <div class="col-md-3 text-center"><div class="card card-body py-2"><h4 class="mb-0">${s.onu_count || 0}</h4><small class="text-muted">ONUs</small></div></div>
                    <div class="col-md-3 text-center"><div class="card card-body py-2"><h4 class="mb-0">${s.ticket_count || 0}</h4><small class="text-muted">Tickets</small></div></div>
                </div>`;

            if (data.history && data.history.length > 0) {
                html += `<h6>Usage History (last 30 days)</h6>
                <div class="table-responsive" style="max-height: 200px; overflow-y: auto;">
                    <table class="table table-sm table-striped">
                        <thead><tr><th>Date</th><th>Users</th><th>Customers</th><th>ONUs</th><th>Tickets</th><th>Version</th></tr></thead>
                        <tbody>`;
                data.history.forEach(h => {
                    html += `<tr>
                        <td>${new Date(h.recorded_at).toLocaleDateString()}</td>
                        <td>${h.user_count}</td><td>${h.customer_count}</td>
                        <td>${h.onu_count}</td><td>${h.ticket_count}</td>
                        <td>${h.app_version || '-'}</td>
                    </tr>`;
                });
                html += '</tbody></table></div>';
            }

            document.getElementById('serverDetailContent').innerHTML = html;
        } catch (e) {
            document.getElementById('serverDetailContent').innerHTML = '<div class="alert alert-danger">' + e.message + '</div>';
        }
    }

    async function deactivateServer(id, name) {
        const reason = prompt('Deactivate server ' + name + '?\nEnter reason:');
        if (!reason) return;
        
        try {
            const resp = await fetch('?ajax=deactivate_server', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id, reason })
            });
            const data = await resp.json();
            if (data.success) {
                location.reload();
            }
        } catch (e) {
            alert('Error: ' + e.message);
        }
    }

    async function showUpdateLogs(updateId, version) {
        document.getElementById('updateLogsTitle').textContent = 'Install Logs - v' + version;
        document.getElementById('updateLogsContent').innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
        new bootstrap.Modal(document.getElementById('updateLogsModal')).show();

        try {
            const resp = await fetch('?ajax=update_logs&update_id=' + updateId);
            const logs = await resp.json();
            if (logs.length === 0) {
                document.getElementById('updateLogsContent').innerHTML = '<p class="text-center text-muted py-4">No install logs for this release</p>';
                return;
            }
            let html = '<table class="table table-sm"><thead><tr><th>Server</th><th>Customer</th><th>From</th><th>To</th><th>Status</th><th>Date</th></tr></thead><tbody>';
            logs.forEach(l => {
                const statusBadge = l.status === 'completed' ? 'success' : (l.status === 'failed' ? 'danger' : 'warning');
                html += `<tr>
                    <td>${l.domain || l.server_hostname || l.server_ip || '-'}</td>
                    <td>${l.customer_name || '-'}</td>
                    <td>${l.from_version}</td>
                    <td>${l.to_version}</td>
                    <td><span class="badge bg-${statusBadge}">${l.status}</span>${l.error_message ? '<br><small class="text-danger">' + l.error_message + '</small>' : ''}</td>
                    <td>${new Date(l.created_at).toLocaleString()}</td>
                </tr>`;
            });
            html += '</tbody></table>';
            document.getElementById('updateLogsContent').innerHTML = html;
        } catch (e) {
            document.getElementById('updateLogsContent').innerHTML = '<div class="alert alert-danger">' + e.message + '</div>';
        }
    }
    </script>
</body>
</html>
<?php
function timeAgo($timestamp) {
    $diff = time() - $timestamp;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j', $timestamp);
}

function formatFileSize($bytes) {
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 1) . ' GB';
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024) return round($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
}
?>
