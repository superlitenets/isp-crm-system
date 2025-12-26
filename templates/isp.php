<?php
require_once __DIR__ . '/../src/RadiusBilling.php';
$radiusBilling = new \App\RadiusBilling($db);

$view = $_GET['view'] ?? 'dashboard';
$message = '';
$messageType = 'info';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_nas':
            $result = $radiusBilling->createNAS($_POST);
            $message = $result['success'] ? 'NAS device added successfully' : 'Error: ' . ($result['error'] ?? 'Unknown error');
            $messageType = $result['success'] ? 'success' : 'danger';
            break;
            
        case 'update_nas':
            $result = $radiusBilling->updateNAS((int)$_POST['id'], $_POST);
            $message = $result['success'] ? 'NAS device updated' : 'Error: ' . ($result['error'] ?? 'Unknown error');
            $messageType = $result['success'] ? 'success' : 'danger';
            break;
            
        case 'delete_nas':
            $result = $radiusBilling->deleteNAS((int)$_POST['id']);
            $message = $result['success'] ? 'NAS device deleted' : 'Error: ' . ($result['error'] ?? 'Unknown error');
            $messageType = $result['success'] ? 'success' : 'danger';
            break;
            
        case 'create_package':
            $result = $radiusBilling->createPackage($_POST);
            $message = $result['success'] ? 'Package created successfully' : 'Error: ' . ($result['error'] ?? 'Unknown error');
            $messageType = $result['success'] ? 'success' : 'danger';
            break;
            
        case 'update_package':
            $result = $radiusBilling->updatePackage((int)$_POST['id'], $_POST);
            $message = $result['success'] ? 'Package updated' : 'Error: ' . ($result['error'] ?? 'Unknown error');
            $messageType = $result['success'] ? 'success' : 'danger';
            break;
            
        case 'create_subscription':
            $result = $radiusBilling->createSubscription($_POST);
            $message = $result['success'] ? 'Subscription created successfully' : 'Error: ' . ($result['error'] ?? 'Unknown error');
            $messageType = $result['success'] ? 'success' : 'danger';
            break;
            
        case 'renew_subscription':
            $result = $radiusBilling->renewSubscription((int)$_POST['id'], (int)$_POST['package_id'] ?: null);
            $message = $result['success'] ? 'Subscription renewed until ' . $result['expiry_date'] : 'Error: ' . ($result['error'] ?? 'Unknown error');
            $messageType = $result['success'] ? 'success' : 'danger';
            break;
            
        case 'suspend_subscription':
            $result = $radiusBilling->suspendSubscription((int)$_POST['id'], $_POST['reason'] ?? '');
            $message = $result['success'] ? 'Subscription suspended' : 'Error: ' . ($result['error'] ?? 'Unknown error');
            $messageType = $result['success'] ? 'success' : 'danger';
            break;
            
        case 'activate_subscription':
            $result = $radiusBilling->activateSubscription((int)$_POST['id']);
            $message = $result['success'] ? 'Subscription activated' : 'Error: ' . ($result['error'] ?? 'Unknown error');
            $messageType = $result['success'] ? 'success' : 'danger';
            break;
            
        case 'generate_vouchers':
            $result = $radiusBilling->generateVouchers((int)$_POST['package_id'], (int)$_POST['count'], $_SESSION['user_id']);
            $message = $result['success'] ? "Generated {$result['count']} vouchers (Batch: {$result['batch_id']})" : 'Error: ' . ($result['error'] ?? 'Unknown error');
            $messageType = $result['success'] ? 'success' : 'danger';
            break;
    }
}

$stats = $radiusBilling->getDashboardStats();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-router me-2"></i>ISP / RADIUS Billing</h4>
</div>

<?php if ($message): ?>
<div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
    <?= htmlspecialchars($message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link <?= $view === 'dashboard' ? 'active' : '' ?>" href="?page=isp&view=dashboard">
            <i class="bi bi-speedometer2 me-1"></i> Dashboard
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $view === 'subscriptions' ? 'active' : '' ?>" href="?page=isp&view=subscriptions">
            <i class="bi bi-people me-1"></i> Subscriptions
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $view === 'sessions' ? 'active' : '' ?>" href="?page=isp&view=sessions">
            <i class="bi bi-broadcast me-1"></i> Active Sessions
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $view === 'packages' ? 'active' : '' ?>" href="?page=isp&view=packages">
            <i class="bi bi-box me-1"></i> Packages
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $view === 'nas' ? 'active' : '' ?>" href="?page=isp&view=nas">
            <i class="bi bi-hdd-network me-1"></i> NAS Devices
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $view === 'vouchers' ? 'active' : '' ?>" href="?page=isp&view=vouchers">
            <i class="bi bi-ticket me-1"></i> Vouchers
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $view === 'billing' ? 'active' : '' ?>" href="?page=isp&view=billing">
            <i class="bi bi-receipt me-1"></i> Billing
        </a>
    </li>
</ul>

<?php if ($view === 'dashboard'): ?>
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-white-50">Active Subscriptions</h6>
                        <h2 class="mb-0"><?= number_format($stats['active_subscriptions']) ?></h2>
                    </div>
                    <i class="bi bi-people fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-white-50">Active Sessions</h6>
                        <h2 class="mb-0"><?= number_format($stats['active_sessions']) ?></h2>
                    </div>
                    <i class="bi bi-broadcast fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-warning text-dark">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-muted">Expiring Soon</h6>
                        <h2 class="mb-0"><?= number_format($stats['expiring_soon']) ?></h2>
                    </div>
                    <i class="bi bi-exclamation-triangle fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-info text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-white-50">Monthly Revenue</h6>
                        <h2 class="mb-0">KES <?= number_format($stats['monthly_revenue']) ?></h2>
                    </div>
                    <i class="bi bi-currency-exchange fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-2">
        <div class="card shadow-sm">
            <div class="card-body text-center">
                <i class="bi bi-hdd-network fs-3 text-primary"></i>
                <h4 class="mb-0 mt-2"><?= $stats['nas_devices'] ?></h4>
                <small class="text-muted">NAS Devices</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card shadow-sm">
            <div class="card-body text-center">
                <i class="bi bi-box fs-3 text-success"></i>
                <h4 class="mb-0 mt-2"><?= $stats['packages'] ?></h4>
                <small class="text-muted">Packages</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card shadow-sm">
            <div class="card-body text-center">
                <i class="bi bi-pause-circle fs-3 text-warning"></i>
                <h4 class="mb-0 mt-2"><?= $stats['suspended_subscriptions'] ?></h4>
                <small class="text-muted">Suspended</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card shadow-sm">
            <div class="card-body text-center">
                <i class="bi bi-x-circle fs-3 text-danger"></i>
                <h4 class="mb-0 mt-2"><?= $stats['expired_subscriptions'] ?></h4>
                <small class="text-muted">Expired</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card shadow-sm">
            <div class="card-body text-center">
                <i class="bi bi-ticket fs-3 text-info"></i>
                <h4 class="mb-0 mt-2"><?= $stats['unused_vouchers'] ?></h4>
                <small class="text-muted">Vouchers</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card shadow-sm">
            <div class="card-body text-center">
                <i class="bi bi-download fs-3 text-secondary"></i>
                <h4 class="mb-0 mt-2"><?= $stats['today_data_gb'] ?> GB</h4>
                <small class="text-muted">Today's Usage</small>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-exclamation-triangle me-2 text-warning"></i>Expiring Subscriptions</h5>
                <a href="?page=isp&view=subscriptions&filter=expiring" class="btn btn-sm btn-outline-warning">View All</a>
            </div>
            <div class="card-body p-0">
                <?php $expiring = $radiusBilling->getSubscriptions(['expiring_soon' => true, 'limit' => 5]); ?>
                <?php if (empty($expiring)): ?>
                <div class="p-4 text-center text-muted">No subscriptions expiring soon</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Customer</th>
                                <th>Package</th>
                                <th>Expires</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expiring as $sub): ?>
                            <tr>
                                <td><?= htmlspecialchars($sub['customer_name'] ?? $sub['username']) ?></td>
                                <td><?= htmlspecialchars($sub['package_name']) ?></td>
                                <td><?= date('M j', strtotime($sub['expiry_date'])) ?></td>
                                <td>
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="action" value="renew_subscription">
                                        <input type="hidden" name="id" value="<?= $sub['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-success">Renew</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-broadcast me-2 text-success"></i>Active Sessions</h5>
                <a href="?page=isp&view=sessions" class="btn btn-sm btn-outline-success">View All</a>
            </div>
            <div class="card-body p-0">
                <?php $sessions = $radiusBilling->getActiveSessions(); ?>
                <?php if (empty($sessions)): ?>
                <div class="p-4 text-center text-muted">No active sessions</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Username</th>
                                <th>IP Address</th>
                                <th>NAS</th>
                                <th>Duration</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($sessions, 0, 5) as $session): ?>
                            <tr>
                                <td><?= htmlspecialchars($session['username']) ?></td>
                                <td><code><?= htmlspecialchars($session['framed_ip_address']) ?></code></td>
                                <td><?= htmlspecialchars($session['nas_name'] ?? '-') ?></td>
                                <td><?php 
                                    $dur = time() - strtotime($session['session_start']);
                                    echo floor($dur/3600) . 'h ' . floor(($dur%3600)/60) . 'm';
                                ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php elseif ($view === 'subscriptions'): ?>
<?php
$filter = $_GET['filter'] ?? '';
$filters = ['search' => $_GET['search'] ?? ''];
if ($filter === 'expiring') $filters['expiring_soon'] = true;
if ($filter === 'expired') $filters['expired'] = true;
if (!empty($_GET['status'])) $filters['status'] = $_GET['status'];
$subscriptions = $radiusBilling->getSubscriptions($filters);
$packages = $radiusBilling->getPackages();
$nasDevices = $radiusBilling->getNASDevices();

// Get customers for dropdown
$customersStmt = $db->query("SELECT id, name, phone FROM customers ORDER BY name LIMIT 500");
$customers = $customersStmt->fetchAll(\PDO::FETCH_ASSOC);
?>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Subscriptions</h5>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSubscriptionModal">
            <i class="bi bi-plus-lg me-1"></i> New Subscription
        </button>
    </div>
    <div class="card-body">
        <form method="get" class="row g-2 mb-3">
            <input type="hidden" name="page" value="isp">
            <input type="hidden" name="view" value="subscriptions">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control" placeholder="Search username, customer, phone..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="active" <?= ($_GET['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="suspended" <?= ($_GET['status'] ?? '') === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                    <option value="expired" <?= ($_GET['status'] ?? '') === 'expired' ? 'selected' : '' ?>>Expired</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-secondary w-100">Filter</button>
            </div>
        </form>
        
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Username</th>
                        <th>Customer</th>
                        <th>Package</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Expiry</th>
                        <th>Data Used</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subscriptions as $sub): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($sub['username']) ?></strong></td>
                        <td><?= htmlspecialchars($sub['customer_name'] ?? '-') ?></td>
                        <td>
                            <?= htmlspecialchars($sub['package_name']) ?>
                            <br><small class="text-muted"><?= $sub['download_speed'] ?>/<?= $sub['upload_speed'] ?></small>
                        </td>
                        <td><span class="badge bg-secondary"><?= strtoupper($sub['access_type']) ?></span></td>
                        <td>
                            <?php
                            $statusClass = match($sub['status']) {
                                'active' => 'success',
                                'suspended' => 'warning',
                                'expired' => 'danger',
                                default => 'secondary'
                            };
                            ?>
                            <span class="badge bg-<?= $statusClass ?>"><?= ucfirst($sub['status']) ?></span>
                        </td>
                        <td>
                            <?= $sub['expiry_date'] ? date('M j, Y', strtotime($sub['expiry_date'])) : '-' ?>
                            <?php if ($sub['expiry_date'] && strtotime($sub['expiry_date']) < time()): ?>
                            <span class="badge bg-danger">Expired</span>
                            <?php elseif ($sub['expiry_date'] && strtotime($sub['expiry_date']) < strtotime('+7 days')): ?>
                            <span class="badge bg-warning">Soon</span>
                            <?php endif; ?>
                        </td>
                        <td><?= number_format($sub['data_used_mb'] / 1024, 2) ?> GB</td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <?php if ($sub['status'] === 'active'): ?>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="action" value="suspend_subscription">
                                    <input type="hidden" name="id" value="<?= $sub['id'] ?>">
                                    <button type="submit" class="btn btn-warning" title="Suspend"><i class="bi bi-pause"></i></button>
                                </form>
                                <?php else: ?>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="action" value="activate_subscription">
                                    <input type="hidden" name="id" value="<?= $sub['id'] ?>">
                                    <button type="submit" class="btn btn-success" title="Activate"><i class="bi bi-play"></i></button>
                                </form>
                                <?php endif; ?>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="action" value="renew_subscription">
                                    <input type="hidden" name="id" value="<?= $sub['id'] ?>">
                                    <button type="submit" class="btn btn-primary" title="Renew"><i class="bi bi-arrow-clockwise"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Subscription Modal -->
<div class="modal fade" id="addSubscriptionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="action" value="create_subscription">
                <div class="modal-header">
                    <h5 class="modal-title">New Subscription</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Customer</label>
                            <select name="customer_id" class="form-select" required>
                                <option value="">Select Customer</option>
                                <?php foreach ($customers as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?> (<?= $c['phone'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Package</label>
                            <select name="package_id" class="form-select" required>
                                <option value="">Select Package</option>
                                <?php foreach ($packages as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?> - KES <?= number_format($p['price']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Username (PPPoE)</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Access Type</label>
                            <select name="access_type" class="form-select">
                                <option value="pppoe">PPPoE</option>
                                <option value="hotspot">Hotspot</option>
                                <option value="static">Static IP</option>
                                <option value="dhcp">DHCP</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Static IP (Optional)</label>
                            <input type="text" name="static_ip" class="form-control" placeholder="e.g., 192.168.1.100">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">NAS Device</label>
                            <select name="nas_id" class="form-select">
                                <option value="">Any NAS</option>
                                <?php foreach ($nasDevices as $nas): ?>
                                <option value="<?= $nas['id'] ?>"><?= htmlspecialchars($nas['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Subscription</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php elseif ($view === 'sessions'): ?>
<?php $sessions = $radiusBilling->getActiveSessions(); ?>
<div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-broadcast me-2"></i>Active Sessions (<?= count($sessions) ?>)</h5>
        <button class="btn btn-outline-secondary" onclick="location.reload()"><i class="bi bi-arrow-clockwise me-1"></i> Refresh</button>
    </div>
    <div class="card-body p-0">
        <?php if (empty($sessions)): ?>
        <div class="p-5 text-center text-muted">
            <i class="bi bi-broadcast fs-1 mb-3 d-block"></i>
            <h5>No Active Sessions</h5>
            <p>There are no users currently connected.</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Username</th>
                        <th>Customer</th>
                        <th>IP Address</th>
                        <th>MAC Address</th>
                        <th>NAS</th>
                        <th>Started</th>
                        <th>Duration</th>
                        <th>Download</th>
                        <th>Upload</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sessions as $session): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($session['username']) ?></strong></td>
                        <td><?= htmlspecialchars($session['customer_name'] ?? '-') ?></td>
                        <td><code><?= htmlspecialchars($session['framed_ip_address']) ?></code></td>
                        <td><code class="text-muted"><?= htmlspecialchars($session['mac_address'] ?? '-') ?></code></td>
                        <td><?= htmlspecialchars($session['nas_name'] ?? '-') ?></td>
                        <td><?= date('M j, H:i', strtotime($session['session_start'])) ?></td>
                        <td>
                            <?php 
                            $dur = time() - strtotime($session['session_start']);
                            $hours = floor($dur / 3600);
                            $mins = floor(($dur % 3600) / 60);
                            echo "{$hours}h {$mins}m";
                            ?>
                        </td>
                        <td><?= number_format($session['input_octets'] / 1024 / 1024, 2) ?> MB</td>
                        <td><?= number_format($session['output_octets'] / 1024 / 1024, 2) ?> MB</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php elseif ($view === 'packages'): ?>
<?php $packages = $radiusBilling->getPackages(); ?>
<div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Service Packages</h5>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPackageModal">
            <i class="bi bi-plus-lg me-1"></i> New Package
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Billing</th>
                        <th>Price</th>
                        <th>Speed</th>
                        <th>Quota</th>
                        <th>Sessions</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($packages as $pkg): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($pkg['name']) ?></strong>
                            <?php if ($pkg['description']): ?>
                            <br><small class="text-muted"><?= htmlspecialchars($pkg['description']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge bg-secondary"><?= strtoupper($pkg['package_type']) ?></span></td>
                        <td><?= ucfirst($pkg['billing_type']) ?></td>
                        <td>KES <?= number_format($pkg['price']) ?></td>
                        <td>
                            <i class="bi bi-arrow-down text-success"></i> <?= $pkg['download_speed'] ?>
                            <i class="bi bi-arrow-up text-primary ms-2"></i> <?= $pkg['upload_speed'] ?>
                        </td>
                        <td><?= $pkg['data_quota_mb'] ? number_format($pkg['data_quota_mb'] / 1024) . ' GB' : 'Unlimited' ?></td>
                        <td><?= $pkg['simultaneous_sessions'] ?></td>
                        <td>
                            <?php if ($pkg['is_active']): ?>
                            <span class="badge bg-success">Active</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">Inactive</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Package Modal -->
<div class="modal fade" id="addPackageModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="action" value="create_package">
                <div class="modal-header">
                    <h5 class="modal-title">New Package</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Package Name</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Type</label>
                            <select name="package_type" class="form-select">
                                <option value="pppoe">PPPoE</option>
                                <option value="hotspot">Hotspot</option>
                                <option value="static">Static IP</option>
                                <option value="dhcp">DHCP</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Billing Cycle</label>
                            <select name="billing_type" class="form-select">
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly" selected>Monthly</option>
                                <option value="quarterly">Quarterly</option>
                                <option value="yearly">Yearly</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Price (KES)</label>
                            <input type="number" name="price" class="form-control" step="0.01" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Validity (Days)</label>
                            <input type="number" name="validity_days" class="form-control" value="30">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Data Quota (MB)</label>
                            <input type="number" name="data_quota_mb" class="form-control" placeholder="Leave empty for unlimited">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Download Speed</label>
                            <input type="text" name="download_speed" class="form-control" placeholder="e.g., 10M">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Upload Speed</label>
                            <input type="text" name="upload_speed" class="form-control" placeholder="e.g., 5M">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Priority (1-8)</label>
                            <input type="number" name="priority" class="form-control" value="8" min="1" max="8">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Simultaneous Sessions</label>
                            <input type="number" name="simultaneous_sessions" class="form-control" value="1" min="1">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Package</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php elseif ($view === 'nas'): ?>
<?php $nasDevices = $radiusBilling->getNASDevices(); ?>
<div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">NAS Devices (MikroTik Routers)</h5>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addNASModal">
            <i class="bi bi-plus-lg me-1"></i> Add NAS
        </button>
    </div>
    <div class="card-body p-0">
        <?php if (empty($nasDevices)): ?>
        <div class="p-5 text-center text-muted">
            <i class="bi bi-hdd-network fs-1 mb-3 d-block"></i>
            <h5>No NAS Devices</h5>
            <p>Add your MikroTik routers to enable RADIUS authentication.</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>IP Address</th>
                        <th>Type</th>
                        <th>RADIUS Port</th>
                        <th>API</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($nasDevices as $nas): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($nas['name']) ?></strong>
                            <?php if ($nas['description']): ?>
                            <br><small class="text-muted"><?= htmlspecialchars($nas['description']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><code><?= htmlspecialchars($nas['ip_address']) ?></code></td>
                        <td><?= htmlspecialchars($nas['nas_type']) ?></td>
                        <td><?= $nas['ports'] ?></td>
                        <td>
                            <?php if ($nas['api_enabled']): ?>
                            <span class="badge bg-success">Enabled (<?= $nas['api_port'] ?>)</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">Disabled</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($nas['is_active']): ?>
                            <span class="badge bg-success">Active</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="post" class="d-inline" onsubmit="return confirm('Delete this NAS device?')">
                                <input type="hidden" name="action" value="delete_nas">
                                <input type="hidden" name="id" value="<?= $nas['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add NAS Modal -->
<div class="modal fade" id="addNASModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="action" value="create_nas">
                <div class="modal-header">
                    <h5 class="modal-title">Add NAS Device</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g., Main Router">
                    </div>
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label">IP Address</label>
                            <input type="text" name="ip_address" class="form-control" required placeholder="e.g., 192.168.1.1">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">RADIUS Port</label>
                            <input type="number" name="ports" class="form-control" value="1812">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">RADIUS Secret</label>
                        <input type="password" name="secret" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2"></textarea>
                    </div>
                    <hr>
                    <h6>MikroTik API (Optional)</h6>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="api_enabled" id="apiEnabled" value="1">
                        <label class="form-check-label" for="apiEnabled">Enable API Access</label>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">API Port</label>
                            <input type="number" name="api_port" class="form-control" value="8728">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">API Username</label>
                            <input type="text" name="api_username" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">API Password</label>
                            <input type="password" name="api_password" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add NAS</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php elseif ($view === 'vouchers'): ?>
<?php 
$vouchers = $radiusBilling->getVouchers(['limit' => 100]);
$packages = $radiusBilling->getPackages('hotspot');
?>
<div class="row">
    <div class="col-lg-4">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Generate Vouchers</h5>
            </div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="action" value="generate_vouchers">
                    <div class="mb-3">
                        <label class="form-label">Package</label>
                        <select name="package_id" class="form-select" required>
                            <option value="">Select Package</option>
                            <?php foreach ($packages as $pkg): ?>
                            <option value="<?= $pkg['id'] ?>"><?= htmlspecialchars($pkg['name']) ?> - KES <?= number_format($pkg['price']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Number of Vouchers</label>
                        <input type="number" name="count" class="form-control" value="10" min="1" max="100" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-ticket me-1"></i> Generate Vouchers
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Vouchers</h5>
                <span class="badge bg-primary"><?= count($vouchers) ?></span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($vouchers)): ?>
                <div class="p-4 text-center text-muted">No vouchers generated yet</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Code</th>
                                <th>Package</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Used At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($vouchers as $v): ?>
                            <tr>
                                <td><code class="fs-6"><?= htmlspecialchars($v['code']) ?></code></td>
                                <td><?= htmlspecialchars($v['package_name']) ?></td>
                                <td>
                                    <?php
                                    $statusClass = match($v['status']) {
                                        'unused' => 'success',
                                        'used' => 'secondary',
                                        'expired' => 'danger',
                                        default => 'warning'
                                    };
                                    ?>
                                    <span class="badge bg-<?= $statusClass ?>"><?= ucfirst($v['status']) ?></span>
                                </td>
                                <td><?= date('M j', strtotime($v['created_at'])) ?></td>
                                <td><?= $v['used_at'] ? date('M j, H:i', strtotime($v['used_at'])) : '-' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php elseif ($view === 'billing'): ?>
<?php $billing = $radiusBilling->getBillingHistory(null, 50); ?>
<div class="card shadow-sm">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="bi bi-receipt me-2"></i>Billing History</h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($billing)): ?>
        <div class="p-4 text-center text-muted">No billing records yet</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Invoice #</th>
                        <th>Customer</th>
                        <th>Package</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Period</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($billing as $b): ?>
                    <tr>
                        <td><code><?= htmlspecialchars($b['invoice_number']) ?></code></td>
                        <td><?= htmlspecialchars($b['customer_name'] ?? $b['username']) ?></td>
                        <td><?= htmlspecialchars($b['package_name']) ?></td>
                        <td><span class="badge bg-info"><?= ucfirst($b['billing_type']) ?></span></td>
                        <td>KES <?= number_format($b['amount']) ?></td>
                        <td><?= date('M j', strtotime($b['period_start'])) ?> - <?= date('M j', strtotime($b['period_end'])) ?></td>
                        <td>
                            <?php
                            $statusClass = match($b['status']) {
                                'paid' => 'success',
                                'pending' => 'warning',
                                'failed' => 'danger',
                                default => 'secondary'
                            };
                            ?>
                            <span class="badge bg-<?= $statusClass ?>"><?= ucfirst($b['status']) ?></span>
                        </td>
                        <td><?= date('M j, Y', strtotime($b['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
