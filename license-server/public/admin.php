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
    </head>
    <body class="bg-light">
        <div class="container mt-5">
            <div class="row justify-content-center">
                <div class="col-md-4">
                    <div class="card shadow">
                        <div class="card-header bg-primary text-white text-center">
                            <h4>License Server Admin</h4>
                        </div>
                        <div class="card-body">
                            <?php if (isset($loginError)): ?>
                            <div class="alert alert-danger"><?= $loginError ?></div>
                            <?php endif; ?>
                            <form method="post">
                                <div class="mb-3">
                                    <label class="form-label">Admin Password</label>
                                    <input type="password" name="password" class="form-control" required>
                                </div>
                                <button type="submit" name="login" class="btn btn-primary w-100">Login</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
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
            
        case 'create_license':
            $result = $license->createLicense($_POST);
            if ($result) {
                $message = 'License created: ' . $result['license_key'];
            }
            break;
            
        case 'suspend_license':
            $license->suspendLicense($_POST['license_id'], $_POST['reason']);
            $message = 'License suspended';
            break;
            
        case 'unsuspend_license':
            $license->unsuspendLicense($_POST['license_id']);
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
                INSERT INTO license_tiers (product_id, code, name, max_users, max_customers, max_onus, features, price_monthly, price_yearly)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $_POST['product_id'],
                strtolower(str_replace(' ', '_', $_POST['name'])),
                $_POST['name'],
                (int)$_POST['max_users'] ?: 0,
                (int)$_POST['max_customers'] ?: 0,
                (int)$_POST['max_onus'] ?: 0,
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
                    features = ?, price_monthly = ?, price_yearly = ?, is_active = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $_POST['name'],
                (int)$_POST['max_users'] ?: 0,
                (int)$_POST['max_customers'] ?: 0,
                (int)$_POST['max_onus'] ?: 0,
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
    }
}

$stats = $license->getStats();
$licenses = $license->getAllLicenses();
$customers = $db->query("SELECT * FROM license_customers ORDER BY name")->fetchAll();
$products = $db->query("SELECT * FROM license_products WHERE is_active = TRUE")->fetchAll();
$tiers = $db->query("SELECT t.*, p.name as product_name FROM license_tiers t LEFT JOIN license_products p ON t.product_id = p.id ORDER BY t.price_monthly")->fetchAll();
$activeTiers = array_filter($tiers, fn($t) => $t['is_active']);

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
?>
<!DOCTYPE html>
<html>
<head>
    <title>License Server - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .feature-badge { font-size: 0.75rem; margin: 2px; }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-dark bg-primary">
        <div class="container">
            <span class="navbar-brand"><i class="bi bi-shield-lock me-2"></i>License Server Admin</span>
            <a href="?logout=1" class="btn btn-outline-light btn-sm">Logout</a>
        </div>
    </nav>
    
    <div class="container mt-4">
        <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-primary"><?= $stats['total_licenses'] ?></h3>
                        <small class="text-muted">Total Licenses</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-success"><?= $stats['active_licenses'] ?></h3>
                        <small class="text-muted">Active</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-danger"><?= $stats['expired_licenses'] ?></h3>
                        <small class="text-muted">Expired</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-info"><?= $stats['total_activations'] ?></h3>
                        <small class="text-muted">Activations</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-success">KES <?= number_format($paymentStats['monthly_revenue'] ?? 0) ?></h3>
                        <small class="text-muted">Monthly Revenue</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center">
                    <div class="card-body">
                        <h3><?= $stats['total_customers'] ?></h3>
                        <small class="text-muted">Customers</small>
                    </div>
                </div>
            </div>
        </div>
        
        <ul class="nav nav-tabs mb-4" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="tab" href="#licenses">Licenses</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#customers">Customers</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#tiers">Tiers</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#payments">Payments</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#new-license">New License</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#new-customer">New Customer</a>
            </li>
        </ul>
        
        <div class="tab-content">
            <div class="tab-pane fade show active" id="licenses">
                <div class="card">
                    <div class="card-body">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>License Key</th>
                                    <th>Customer</th>
                                    <th>Tier</th>
                                    <th>Activations</th>
                                    <th>Expires</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($licenses as $lic): ?>
                                <tr>
                                    <td><code><?= htmlspecialchars($lic['license_key']) ?></code></td>
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
                                        <?php if ($lic['is_suspended']): ?>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="action" value="unsuspend_license">
                                            <input type="hidden" name="license_id" value="<?= $lic['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-success">Unsuspend</button>
                                        </form>
                                        <?php else: ?>
                                        <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#suspendModal" data-license-id="<?= $lic['id'] ?>">Suspend</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="tab-pane fade" id="customers">
                <div class="card">
                    <div class="card-body">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Company</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($customers as $cust): ?>
                                <tr>
                                    <td><?= htmlspecialchars($cust['name']) ?></td>
                                    <td><?= htmlspecialchars($cust['company'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($cust['email']) ?></td>
                                    <td><?= htmlspecialchars($cust['phone'] ?? '-') ?></td>
                                    <td><?= date('M j, Y', strtotime($cust['created_at'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="tab-pane fade" id="tiers">
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">License Tiers</h5>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newTierModal">
                            <i class="bi bi-plus-lg me-1"></i>Add Tier
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($tiers as $tier): ?>
                            <div class="col-md-4 mb-3">
                                <div class="card h-100 <?= $tier['is_active'] ? '' : 'border-secondary opacity-50' ?>">
                                    <div class="card-header d-flex justify-content-between">
                                        <strong><?= htmlspecialchars($tier['name']) ?></strong>
                                        <?php if (!$tier['is_active']): ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-body">
                                        <h4 class="text-primary mb-1">KES <?= number_format($tier['price_monthly']) ?><small class="text-muted">/mo</small></h4>
                                        <small class="text-muted">KES <?= number_format($tier['price_yearly']) ?>/year</small>
                                        <hr>
                                        <ul class="list-unstyled mb-3">
                                            <li><i class="bi bi-people me-2"></i><?= $tier['max_users'] ?: 'Unlimited' ?> Users</li>
                                            <li><i class="bi bi-person-badge me-2"></i><?= $tier['max_customers'] ?: 'Unlimited' ?> Customers</li>
                                            <li><i class="bi bi-router me-2"></i><?= $tier['max_onus'] ?: 'Unlimited' ?> ONUs</li>
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
                                    <div class="card-footer">
                                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editTierModal"
                                            data-tier='<?= htmlspecialchars(json_encode($tier)) ?>'>
                                            <i class="bi bi-pencil"></i> Edit
                                        </button>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Delete this tier?')">
                                            <input type="hidden" name="action" value="delete_tier">
                                            <input type="hidden" name="tier_id" value="<?= $tier['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="tab-pane fade" id="payments">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Recent Payments</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Customer</th>
                                    <th>License</th>
                                    <th>Amount</th>
                                    <th>M-Pesa Receipt</th>
                                    <th>Phone</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($payments)): ?>
                                <tr><td colspan="7" class="text-center text-muted">No payments yet</td></tr>
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
            </div>
            
            <div class="tab-pane fade" id="new-license">
                <div class="card">
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="action" value="create_license">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Customer</label>
                                    <select name="customer_id" class="form-select" required>
                                        <option value="">Select Customer</option>
                                        <?php foreach ($customers as $cust): ?>
                                        <option value="<?= $cust['id'] ?>"><?= htmlspecialchars($cust['name']) ?> (<?= htmlspecialchars($cust['company'] ?? $cust['email']) ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Product</label>
                                    <select name="product_id" class="form-select" required>
                                        <?php foreach ($products as $prod): ?>
                                        <option value="<?= $prod['id'] ?>"><?= htmlspecialchars($prod['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Tier</label>
                                    <select name="tier_id" class="form-select" required>
                                        <?php foreach ($activeTiers as $tier): ?>
                                        <option value="<?= $tier['id'] ?>"><?= htmlspecialchars($tier['name']) ?> - KES <?= number_format($tier['price_monthly']) ?>/mo</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Duration (months)</label>
                                    <select name="duration_months" class="form-select">
                                        <option value="">Lifetime</option>
                                        <option value="1">1 Month</option>
                                        <option value="3">3 Months</option>
                                        <option value="6">6 Months</option>
                                        <option value="12" selected>12 Months</option>
                                        <option value="24">24 Months</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Max Activations</label>
                                    <input type="number" name="max_activations" class="form-control" value="1" min="1">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Domain Restriction (optional)</label>
                                <input type="text" name="domain_restriction" class="form-control" placeholder="e.g., *.example.com, client.isp.co.ke">
                                <small class="text-muted">Comma-separated. Use * for wildcard. Leave empty for no restriction.</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Notes</label>
                                <textarea name="notes" class="form-control" rows="2"></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Generate License</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="tab-pane fade" id="new-customer">
                <div class="card">
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="action" value="create_customer">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Name *</label>
                                    <input type="text" name="name" class="form-control" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Company</label>
                                    <input type="text" name="company" class="form-control">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email *</label>
                                    <input type="email" name="email" class="form-control" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Phone</label>
                                    <input type="text" name="phone" class="form-control">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Notes</label>
                                <textarea name="notes" class="form-control" rows="2"></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Create Customer</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="suspendModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="action" value="suspend_license">
                    <input type="hidden" name="license_id" id="suspendLicenseId">
                    <div class="modal-header">
                        <h5 class="modal-title">Suspend License</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Reason</label>
                            <textarea name="reason" class="form-control" rows="3" required placeholder="Enter suspension reason..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Suspend License</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="newTierModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="action" value="create_tier">
                    <div class="modal-header">
                        <h5 class="modal-title">Create New Tier</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Product</label>
                            <select name="product_id" class="form-select" required>
                                <?php foreach ($products as $prod): ?>
                                <option value="<?= $prod['id'] ?>"><?= htmlspecialchars($prod['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tier Name</label>
                            <input type="text" name="name" class="form-control" required placeholder="e.g., Premium">
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Max Users</label>
                                <input type="number" name="max_users" class="form-control" value="0" min="0">
                                <small class="text-muted">0 = Unlimited</small>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Max Customers</label>
                                <input type="number" name="max_customers" class="form-control" value="0" min="0">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Max ONUs</label>
                                <input type="number" name="max_onus" class="form-control" value="0" min="0">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Monthly Price (KES)</label>
                                <input type="number" name="price_monthly" class="form-control" step="0.01" value="0" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Yearly Price (KES)</label>
                                <input type="number" name="price_yearly" class="form-control" step="0.01" value="0" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Features (comma-separated)</label>
                            <input type="text" name="features" class="form-control" placeholder="crm, tickets, oms, hr, inventory">
                            <small class="text-muted">e.g., crm, tickets, oms, hr, radius, huawei_olt</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Tier</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="editTierModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="action" value="update_tier">
                    <input type="hidden" name="tier_id" id="editTierId">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Tier</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Tier Name</label>
                            <input type="text" name="name" id="editTierName" class="form-control" required>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Max Users</label>
                                <input type="number" name="max_users" id="editTierMaxUsers" class="form-control" min="0">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Max Customers</label>
                                <input type="number" name="max_customers" id="editTierMaxCustomers" class="form-control" min="0">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Max ONUs</label>
                                <input type="number" name="max_onus" id="editTierMaxOnus" class="form-control" min="0">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Monthly Price (KES)</label>
                                <input type="number" name="price_monthly" id="editTierPriceMonthly" class="form-control" step="0.01" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Yearly Price (KES)</label>
                                <input type="number" name="price_yearly" id="editTierPriceYearly" class="form-control" step="0.01" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Features (comma-separated)</label>
                            <input type="text" name="features" id="editTierFeatures" class="form-control">
                        </div>
                        <div class="form-check mb-3">
                            <input type="checkbox" name="is_active" id="editTierActive" class="form-check-input" value="1">
                            <label class="form-check-label" for="editTierActive">Active</label>
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
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.getElementById('suspendModal').addEventListener('show.bs.modal', function(e) {
        document.getElementById('suspendLicenseId').value = e.relatedTarget.dataset.licenseId;
    });
    
    document.getElementById('editTierModal').addEventListener('show.bs.modal', function(e) {
        const tier = JSON.parse(e.relatedTarget.dataset.tier);
        document.getElementById('editTierId').value = tier.id;
        document.getElementById('editTierName').value = tier.name;
        document.getElementById('editTierMaxUsers').value = tier.max_users || 0;
        document.getElementById('editTierMaxCustomers').value = tier.max_customers || 0;
        document.getElementById('editTierMaxOnus').value = tier.max_onus || 0;
        document.getElementById('editTierPriceMonthly').value = tier.price_monthly || 0;
        document.getElementById('editTierPriceYearly').value = tier.price_yearly || 0;
        document.getElementById('editTierActive').checked = tier.is_active;
        
        const features = tier.features ? JSON.parse(tier.features) : {};
        document.getElementById('editTierFeatures').value = Object.keys(features).filter(k => features[k]).join(', ');
    });
    </script>
</body>
</html>
