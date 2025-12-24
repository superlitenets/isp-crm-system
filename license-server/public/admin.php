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
    if ($config['admin_password'] === 'change-this-in-production') {
        $loginError = 'Admin password not configured. Set LICENSE_ADMIN_PASSWORD environment variable.';
    } elseif ($_POST['password'] === $config['admin_password']) {
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
    }
}

$stats = $license->getStats();
$licenses = $license->getAllLicenses();
$customers = $db->query("SELECT * FROM license_customers ORDER BY name")->fetchAll();
$products = $db->query("SELECT * FROM license_products WHERE is_active = TRUE")->fetchAll();
$tiers = $db->query("SELECT * FROM license_tiers WHERE is_active = TRUE")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>License Server - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
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
        <div class="alert alert-success alert-dismissible fade show">
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
                        <h3 class="text-warning"><?= $stats['active_today'] ?></h3>
                        <small class="text-muted">Active Today</small>
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
                                        <?php foreach ($tiers as $tier): ?>
                                        <option value="<?= $tier['id'] ?>"><?= htmlspecialchars($tier['name']) ?> - $<?= $tier['price_monthly'] ?>/mo</option>
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
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.getElementById('suspendModal').addEventListener('show.bs.modal', function(e) {
        document.getElementById('suspendLicenseId').value = e.relatedTarget.dataset.licenseId;
    });
    </script>
</body>
</html>
