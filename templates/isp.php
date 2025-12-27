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

// Get customers for dropdown
$customers = [];
try {
    $customersStmt = $db->query("SELECT id, name, phone FROM customers ORDER BY name LIMIT 500");
    $customers = $customersStmt->fetchAll(\PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ISP - RADIUS Billing System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --isp-primary: #1e3a5f;
            --isp-primary-light: #2d4a6f;
            --isp-accent: #0ea5e9;
            --isp-accent-light: #38bdf8;
            --isp-success: #10b981;
            --isp-warning: #f59e0b;
            --isp-danger: #ef4444;
            --isp-info: #06b6d4;
            --isp-bg: #f1f5f9;
            --isp-card-bg: #ffffff;
            --isp-text: #334155;
            --isp-text-muted: #64748b;
            --isp-border: #e2e8f0;
            --isp-shadow: 0 1px 3px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.06);
            --isp-shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.08), 0 4px 6px -2px rgba(0,0,0,0.04);
            --isp-radius: 0.75rem;
            --isp-radius-lg: 1rem;
        }
        
        body { 
            background-color: var(--isp-bg); 
            color: var(--isp-text);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .sidebar { 
            background: linear-gradient(180deg, var(--isp-primary) 0%, var(--isp-primary-light) 100%); 
            min-height: 100vh;
            box-shadow: 4px 0 10px rgba(0,0,0,0.1);
        }
        .sidebar .nav-link { 
            color: rgba(255,255,255,0.7); 
            padding: 0.875rem 1rem; 
            border-radius: var(--isp-radius); 
            margin: 0.25rem 0.5rem;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
        }
        .sidebar .nav-link:hover { 
            background: rgba(255,255,255,0.1); 
            color: #fff;
            transform: translateX(4px);
        }
        .sidebar .nav-link.active { 
            background: linear-gradient(90deg, var(--isp-accent) 0%, var(--isp-accent-light) 100%);
            color: #fff;
            box-shadow: 0 4px 12px rgba(14, 165, 233, 0.4);
        }
        .sidebar .nav-link i { width: 24px; font-size: 1.1rem; }
        .brand-title { 
            font-size: 1.5rem; 
            font-weight: 800; 
            color: #fff;
            letter-spacing: -0.5px;
        }
        .brand-subtitle {
            font-size: 0.7rem;
            color: rgba(255,255,255,0.5);
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .card {
            border: 1px solid var(--isp-border);
            border-radius: var(--isp-radius-lg);
            box-shadow: var(--isp-shadow);
            transition: all 0.3s ease;
        }
        .card:hover {
            box-shadow: var(--isp-shadow-lg);
        }
        .card-header {
            background: transparent;
            border-bottom: 1px solid var(--isp-border);
            font-weight: 600;
            padding: 1rem 1.25rem;
        }
        
        .stat-card { 
            border-radius: var(--isp-radius-lg); 
            border: none; 
            transition: all 0.3s ease;
            overflow: hidden;
            position: relative;
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--isp-accent), var(--isp-accent-light));
        }
        .stat-card:hover { 
            transform: translateY(-4px); 
            box-shadow: var(--isp-shadow-lg);
        }
        .stat-card.stat-success::before { background: linear-gradient(90deg, var(--isp-success), #34d399); }
        .stat-card.stat-warning::before { background: linear-gradient(90deg, var(--isp-warning), #fbbf24); }
        .stat-card.stat-danger::before { background: linear-gradient(90deg, var(--isp-danger), #f87171); }
        .stat-card.stat-info::before { background: linear-gradient(90deg, var(--isp-info), #22d3ee); }
        
        .stat-icon { 
            width: 56px; 
            height: 56px; 
            border-radius: var(--isp-radius); 
            display: flex; 
            align-items: center; 
            justify-content: center;
            font-size: 1.5rem;
        }
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            line-height: 1;
            color: var(--isp-primary);
        }
        .stat-label {
            font-size: 0.85rem;
            color: var(--isp-text-muted);
            font-weight: 500;
        }
        
        .table {
            margin-bottom: 0;
        }
        .table thead th {
            background: var(--isp-bg);
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--isp-text-muted);
            border-bottom: 2px solid var(--isp-border);
            padding: 1rem;
        }
        .table tbody td {
            padding: 1rem;
            vertical-align: middle;
            border-color: var(--isp-border);
        }
        .table-hover tbody tr {
            transition: background-color 0.15s ease;
        }
        .table-hover tbody tr:hover { 
            background-color: rgba(14, 165, 233, 0.04); 
        }
        
        .badge {
            font-weight: 600;
            padding: 0.4em 0.8em;
            border-radius: 0.5rem;
            font-size: 0.75rem;
            letter-spacing: 0.3px;
        }
        
        .btn {
            border-radius: var(--isp-radius);
            font-weight: 500;
            padding: 0.5rem 1rem;
            transition: all 0.2s ease;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--isp-accent), var(--isp-accent-light));
            border: none;
            box-shadow: 0 2px 8px rgba(14, 165, 233, 0.3);
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, var(--isp-accent-light), var(--isp-accent));
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(14, 165, 233, 0.4);
        }
        
        .main-content {
            min-height: 100vh;
            padding-bottom: 2rem;
        }
        
        .page-title {
            font-weight: 700;
            color: var(--isp-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .isp-mobile-header {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 60px;
            background: linear-gradient(180deg, var(--isp-primary) 0%, var(--isp-primary-light) 100%);
            z-index: 1030;
            padding: 0 1rem;
            align-items: center;
            justify-content: space-between;
        }
        .brand-mobile {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .hamburger-btn {
            background: rgba(255,255,255,0.1);
            border: none;
            border-radius: 8px;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
        }
        .isp-offcanvas {
            background: linear-gradient(180deg, var(--isp-primary) 0%, var(--isp-primary-light) 100%);
            width: 280px !important;
        }
        .isp-offcanvas .btn-close {
            filter: invert(1);
        }
        
        @media (max-width: 991.98px) {
            .isp-mobile-header {
                display: flex;
            }
            .main-content {
                padding-top: 70px !important;
            }
            .sidebar {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="isp-mobile-header">
        <div class="brand-mobile">
            <div style="width: 36px; height: 36px; background: linear-gradient(135deg, var(--isp-accent), var(--isp-accent-light)); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                <i class="bi bi-broadcast text-white"></i>
            </div>
            <span class="brand-title text-white">ISP</span>
        </div>
        <button class="hamburger-btn" type="button" data-bs-toggle="offcanvas" data-bs-target="#ispMobileSidebar">
            <i class="bi bi-list"></i>
        </button>
    </div>
    
    <div class="offcanvas offcanvas-start isp-offcanvas" tabindex="-1" id="ispMobileSidebar">
        <div class="offcanvas-header">
            <div class="d-flex align-items-center">
                <div class="me-2" style="width: 36px; height: 36px; background: linear-gradient(135deg, var(--isp-accent), var(--isp-accent-light)); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                    <i class="bi bi-broadcast text-white"></i>
                </div>
                <div>
                    <span class="brand-title text-white">ISP</span>
                    <div class="brand-subtitle">RADIUS Billing</div>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body p-2">
            <a href="?page=dashboard" class="nav-link text-white-50 small mb-2">
                <i class="bi bi-arrow-left me-2"></i> Back to CRM
            </a>
            <nav class="nav flex-column">
                <a class="nav-link <?= $view === 'dashboard' ? 'active' : '' ?>" href="?page=isp&view=dashboard">
                    <i class="bi bi-speedometer2 me-2"></i> Dashboard
                </a>
                <a class="nav-link <?= $view === 'subscriptions' ? 'active' : '' ?>" href="?page=isp&view=subscriptions">
                    <i class="bi bi-people me-2"></i> Subscribers
                </a>
                <a class="nav-link <?= $view === 'sessions' ? 'active' : '' ?>" href="?page=isp&view=sessions">
                    <i class="bi bi-broadcast me-2"></i> Active Sessions
                    <?php if ($stats['active_sessions'] > 0): ?>
                    <span class="badge bg-success ms-auto"><?= $stats['active_sessions'] ?></span>
                    <?php endif; ?>
                </a>
                <a class="nav-link <?= $view === 'packages' ? 'active' : '' ?>" href="?page=isp&view=packages">
                    <i class="bi bi-box me-2"></i> Packages
                </a>
                <a class="nav-link <?= $view === 'nas' ? 'active' : '' ?>" href="?page=isp&view=nas">
                    <i class="bi bi-hdd-network me-2"></i> NAS Devices
                </a>
                <hr class="my-2 border-light opacity-25">
                <a class="nav-link <?= $view === 'vouchers' ? 'active' : '' ?>" href="?page=isp&view=vouchers">
                    <i class="bi bi-ticket me-2"></i> Vouchers
                </a>
                <a class="nav-link <?= $view === 'billing' ? 'active' : '' ?>" href="?page=isp&view=billing">
                    <i class="bi bi-receipt me-2"></i> Billing History
                </a>
                <a class="nav-link <?= $view === 'ip_pools' ? 'active' : '' ?>" href="?page=isp&view=ip_pools">
                    <i class="bi bi-diagram-2 me-2"></i> IP Pools
                </a>
                <hr class="my-2 border-light opacity-25">
                <a class="nav-link <?= $view === 'expiring' ? 'active' : '' ?>" href="?page=isp&view=expiring">
                    <i class="bi bi-clock-history me-2"></i> Expiring Soon
                    <?php $expiringCount = count($radiusBilling->getExpiringSubscriptions(7)); if ($expiringCount > 0): ?>
                    <span class="badge bg-warning ms-auto"><?= $expiringCount ?></span>
                    <?php endif; ?>
                </a>
                <a class="nav-link <?= $view === 'reports' ? 'active' : '' ?>" href="?page=isp&view=reports">
                    <i class="bi bi-graph-up me-2"></i> Reports
                </a>
                <a class="nav-link <?= $view === 'analytics' ? 'active' : '' ?>" href="?page=isp&view=analytics">
                    <i class="bi bi-bar-chart me-2"></i> Analytics
                </a>
                <a class="nav-link <?= $view === 'import' ? 'active' : '' ?>" href="?page=isp&view=import">
                    <i class="bi bi-upload me-2"></i> Import CSV
                </a>
                <hr class="my-2 border-light opacity-25">
                <a class="nav-link <?= $view === 'settings' ? 'active' : '' ?>" href="?page=isp&view=settings">
                    <i class="bi bi-gear me-2"></i> Settings
                </a>
            </nav>
        </div>
    </div>
    
    <div class="d-flex">
        <div class="sidebar d-none d-lg-flex flex-column p-3" style="width: 260px;">
            <a href="?page=dashboard" class="text-decoration-none small mb-3 px-2 d-flex align-items-center" style="color: rgba(255,255,255,0.5);">
                <i class="bi bi-arrow-left me-1"></i> Back to CRM
            </a>
            <div class="d-flex align-items-center mb-4 px-2">
                <div class="me-3" style="width: 44px; height: 44px; background: linear-gradient(135deg, var(--isp-accent), var(--isp-accent-light)); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                    <i class="bi bi-broadcast fs-5 text-white"></i>
                </div>
                <div>
                    <span class="brand-title">ISP</span>
                    <div class="brand-subtitle">RADIUS Billing</div>
                </div>
            </div>
            <nav class="nav flex-column">
                <a class="nav-link <?= $view === 'dashboard' ? 'active' : '' ?>" href="?page=isp&view=dashboard">
                    <i class="bi bi-speedometer2 me-2"></i> Dashboard
                </a>
                <a class="nav-link <?= $view === 'subscriptions' ? 'active' : '' ?>" href="?page=isp&view=subscriptions">
                    <i class="bi bi-people me-2"></i> Subscribers
                </a>
                <a class="nav-link <?= $view === 'sessions' ? 'active' : '' ?>" href="?page=isp&view=sessions">
                    <i class="bi bi-broadcast me-2"></i> Active Sessions
                    <?php if ($stats['active_sessions'] > 0): ?>
                    <span class="badge bg-success ms-auto"><?= $stats['active_sessions'] ?></span>
                    <?php endif; ?>
                </a>
                <a class="nav-link <?= $view === 'packages' ? 'active' : '' ?>" href="?page=isp&view=packages">
                    <i class="bi bi-box me-2"></i> Packages
                </a>
                <a class="nav-link <?= $view === 'nas' ? 'active' : '' ?>" href="?page=isp&view=nas">
                    <i class="bi bi-hdd-network me-2"></i> NAS Devices
                </a>
                <hr class="my-2 border-light opacity-25">
                <a class="nav-link <?= $view === 'vouchers' ? 'active' : '' ?>" href="?page=isp&view=vouchers">
                    <i class="bi bi-ticket me-2"></i> Vouchers
                </a>
                <a class="nav-link <?= $view === 'billing' ? 'active' : '' ?>" href="?page=isp&view=billing">
                    <i class="bi bi-receipt me-2"></i> Billing History
                </a>
                <a class="nav-link <?= $view === 'ip_pools' ? 'active' : '' ?>" href="?page=isp&view=ip_pools">
                    <i class="bi bi-diagram-2 me-2"></i> IP Pools
                </a>
                <hr class="my-2 border-light opacity-25">
                <a class="nav-link <?= $view === 'expiring' ? 'active' : '' ?>" href="?page=isp&view=expiring">
                    <i class="bi bi-clock-history me-2"></i> Expiring Soon
                </a>
                <a class="nav-link <?= $view === 'reports' ? 'active' : '' ?>" href="?page=isp&view=reports">
                    <i class="bi bi-graph-up me-2"></i> Reports
                </a>
                <a class="nav-link <?= $view === 'analytics' ? 'active' : '' ?>" href="?page=isp&view=analytics">
                    <i class="bi bi-bar-chart me-2"></i> Analytics
                </a>
                <a class="nav-link <?= $view === 'import' ? 'active' : '' ?>" href="?page=isp&view=import">
                    <i class="bi bi-upload me-2"></i> Import CSV
                </a>
                <hr class="my-2 border-light opacity-25">
                <a class="nav-link <?= $view === 'settings' ? 'active' : '' ?>" href="?page=isp&view=settings">
                    <i class="bi bi-gear me-2"></i> Settings
                </a>
            </nav>
        </div>
        
        <div class="main-content flex-grow-1 p-4">
            <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <?php if ($view === 'dashboard'): ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="page-title mb-1"><i class="bi bi-speedometer2"></i> ISP Dashboard</h4>
                    <span class="text-muted small">Last updated: <?= date('M j, Y H:i:s') ?></span>
                </div>
                <div class="d-flex gap-2">
                    <a href="?page=isp&view=subscriptions&filter=expiring" class="btn btn-warning">
                        <i class="bi bi-exclamation-triangle me-1"></i> Expiring (<?= $stats['expiring_soon'] ?>)
                    </a>
                    <button class="btn btn-outline-primary" onclick="location.reload()">
                        <i class="bi bi-arrow-clockwise me-1"></i> Refresh
                    </button>
                </div>
            </div>
            
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card stat-card shadow-sm h-100 stat-success">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="stat-icon bg-success bg-opacity-10 text-success">
                                    <i class="bi bi-people"></i>
                                </div>
                            </div>
                            <div class="stat-value"><?= number_format($stats['active_subscriptions']) ?></div>
                            <div class="stat-label">Active Subscribers</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card shadow-sm h-100 stat-info">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="stat-icon bg-info bg-opacity-10 text-info">
                                    <i class="bi bi-broadcast"></i>
                                </div>
                            </div>
                            <div class="stat-value"><?= number_format($stats['active_sessions']) ?></div>
                            <div class="stat-label">Active Sessions</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card shadow-sm h-100 stat-warning">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                                    <i class="bi bi-exclamation-triangle"></i>
                                </div>
                            </div>
                            <div class="stat-value"><?= number_format($stats['expiring_soon']) ?></div>
                            <div class="stat-label">Expiring Soon</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                                    <i class="bi bi-currency-exchange"></i>
                                </div>
                            </div>
                            <div class="stat-value">KES <?= number_format($stats['monthly_revenue']) ?></div>
                            <div class="stat-label">Monthly Revenue</div>
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
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-exclamation-triangle me-2 text-warning"></i>Expiring Subscribers</h5>
                            <a href="?page=isp&view=subscriptions&filter=expiring" class="btn btn-sm btn-outline-warning">View All</a>
                        </div>
                        <div class="card-body p-0">
                            <?php $expiring = $radiusBilling->getSubscriptions(['expiring_soon' => true, 'limit' => 5]); ?>
                            <?php if (empty($expiring)): ?>
                            <div class="p-4 text-center text-muted">No subscribers expiring soon</div>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0">
                                    <thead>
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
                        <div class="card-header d-flex justify-content-between align-items-center">
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
                                    <thead>
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
            $onlineSubscribers = $radiusBilling->getOnlineSubscribers();
            
            // Apply online/offline filter
            $onlineFilter = $_GET['online'] ?? '';
            if ($onlineFilter === 'online') {
                $subscriptions = array_filter($subscriptions, fn($s) => in_array($s['id'], $onlineSubscribers));
            } elseif ($onlineFilter === 'offline') {
                $subscriptions = array_filter($subscriptions, fn($s) => !in_array($s['id'], $onlineSubscribers));
            }
            ?>
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="page-title mb-0"><i class="bi bi-people"></i> Subscribers</h4>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSubscriptionModal">
                    <i class="bi bi-plus-lg me-1"></i> New Subscriber
                </button>
            </div>
            
            <div class="card shadow-sm">
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
                            <select name="online" class="form-select">
                                <option value="">All (Online/Offline)</option>
                                <option value="online" <?= ($_GET['online'] ?? '') === 'online' ? 'selected' : '' ?>>Online Only</option>
                                <option value="offline" <?= ($_GET['online'] ?? '') === 'offline' ? 'selected' : '' ?>>Offline Only</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-secondary w-100">Filter</button>
                        </div>
                    </form>
                    
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Username</th>
                                    <th>Customer</th>
                                    <th>Package</th>
                                    <th>Type</th>
                                    <th>Online</th>
                                    <th>Status</th>
                                    <th>Expiry</th>
                                    <th>Data Used</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($subscriptions as $sub): ?>
                                <?php $isOnline = in_array($sub['id'], $onlineSubscribers); ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($sub['username']) ?></strong></td>
                                    <td><?= htmlspecialchars($sub['customer_name'] ?? '-') ?></td>
                                    <td>
                                        <?= htmlspecialchars($sub['package_name']) ?>
                                        <br><small class="text-muted"><?= $sub['download_speed'] ?>/<?= $sub['upload_speed'] ?></small>
                                    </td>
                                    <td><span class="badge bg-secondary"><?= strtoupper($sub['access_type']) ?></span></td>
                                    <td>
                                        <?php if ($isOnline): ?>
                                        <span class="badge bg-success"><i class="bi bi-wifi me-1"></i>Online</span>
                                        <?php else: ?>
                                        <span class="badge bg-secondary"><i class="bi bi-wifi-off me-1"></i>Offline</span>
                                        <?php endif; ?>
                                    </td>
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
                                            <button type="button" class="btn btn-outline-info" onclick="pingSubscriber(<?= $sub['id'] ?>, '<?= htmlspecialchars($sub['username']) ?>')" title="Ping IP">
                                                <i class="bi bi-lightning"></i>
                                            </button>
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

            <div class="modal fade" id="addSubscriptionModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <form method="post">
                            <input type="hidden" name="action" value="create_subscription">
                            <div class="modal-header">
                                <h5 class="modal-title">New Subscriber</h5>
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
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Username (PPPoE)</label>
                                        <input type="text" name="username" id="pppoe_username" class="form-control" value="<?= htmlspecialchars($radiusBilling->getNextUsername()) ?>" readonly style="background-color: #e9ecef;">
                                        <small class="text-muted">Auto-generated, cannot be changed</small>
                                    </div>
                                    <div class="col-md-5 mb-3">
                                        <label class="form-label">Password</label>
                                        <div class="input-group">
                                            <input type="text" name="password" id="pppoe_password" class="form-control" value="<?= htmlspecialchars($radiusBilling->generatePassword()) ?>" required>
                                            <button type="button" class="btn btn-outline-secondary" onclick="togglePasswordVisibility()" title="Toggle visibility">
                                                <i class="bi bi-eye" id="passwordToggleIcon"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-md-3 mb-3 d-flex align-items-end">
                                        <button type="button" class="btn btn-info w-100" onclick="regeneratePassword()">
                                            <i class="bi bi-magic me-1"></i> New Password
                                        </button>
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
                                <button type="submit" class="btn btn-primary">Create Subscriber</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <?php elseif ($view === 'sessions'): ?>
            <?php $sessions = $radiusBilling->getActiveSessions(); ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="page-title mb-0"><i class="bi bi-broadcast"></i> Active Sessions (<?= count($sessions) ?>)</h4>
                <button class="btn btn-outline-primary" onclick="location.reload()"><i class="bi bi-arrow-clockwise me-1"></i> Refresh</button>
            </div>
            
            <div class="card shadow-sm">
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
                            <thead>
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
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="page-title mb-0"><i class="bi bi-box"></i> Service Packages</h4>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPackageModal">
                    <i class="bi bi-plus-lg me-1"></i> New Package
                </button>
            </div>
            
            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
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
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="page-title mb-0"><i class="bi bi-hdd-network"></i> NAS Devices (MikroTik Routers)</h4>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addNASModal">
                    <i class="bi bi-plus-lg me-1"></i> Add NAS
                </button>
            </div>
            
            <div class="card shadow-sm">
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
                            <thead>
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
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-success" onclick="testNAS(<?= $nas['id'] ?>, '<?= htmlspecialchars($nas['ip_address']) ?>')" title="Test Connectivity">
                                                <i class="bi bi-lightning"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-primary" onclick="editNAS(<?= htmlspecialchars(json_encode($nas)) ?>)" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <form method="post" class="d-inline" onsubmit="return confirm('Delete this NAS device?')">
                                                <input type="hidden" name="action" value="delete_nas">
                                                <input type="hidden" name="id" value="<?= $nas['id'] ?>">
                                                <button type="submit" class="btn btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

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

            <div class="modal fade" id="editNASModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="post">
                            <input type="hidden" name="action" value="update_nas">
                            <input type="hidden" name="id" id="edit_nas_id">
                            <div class="modal-header">
                                <h5 class="modal-title">Edit NAS Device</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">Name</label>
                                    <input type="text" name="name" id="edit_nas_name" class="form-control" required>
                                </div>
                                <div class="row">
                                    <div class="col-md-8 mb-3">
                                        <label class="form-label">IP Address</label>
                                        <input type="text" name="ip_address" id="edit_nas_ip" class="form-control" required>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">RADIUS Port</label>
                                        <input type="number" name="ports" id="edit_nas_ports" class="form-control">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">RADIUS Secret</label>
                                    <input type="password" name="secret" id="edit_nas_secret" class="form-control" placeholder="Leave blank to keep current">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea name="description" id="edit_nas_description" class="form-control" rows="2"></textarea>
                                </div>
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="edit_nas_active" value="1">
                                    <label class="form-check-label" for="edit_nas_active">Active</label>
                                </div>
                                <hr>
                                <h6>MikroTik API (Optional)</h6>
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" name="api_enabled" id="edit_api_enabled" value="1">
                                    <label class="form-check-label" for="edit_api_enabled">Enable API Access</label>
                                </div>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">API Port</label>
                                        <input type="number" name="api_port" id="edit_api_port" class="form-control">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">API Username</label>
                                        <input type="text" name="api_username" id="edit_api_username" class="form-control">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">API Password</label>
                                        <input type="password" name="api_password" class="form-control" placeholder="Leave blank to keep">
                                    </div>
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

            <div class="modal fade" id="testNASModal" tabindex="-1">
                <div class="modal-dialog modal-sm">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">NAS Connectivity Test</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body text-center" id="testNASResult">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Testing...</span>
                            </div>
                            <p class="mt-2">Testing connectivity...</p>
                        </div>
                    </div>
                </div>
            </div>

            <?php elseif ($view === 'vouchers'): ?>
            <?php 
            $vouchers = $radiusBilling->getVouchers(['limit' => 100]);
            $packages = $radiusBilling->getPackages('hotspot');
            ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="page-title mb-0"><i class="bi bi-ticket"></i> Hotspot Vouchers</h4>
            </div>
            
            <div class="row">
                <div class="col-lg-4">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header">
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
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Vouchers</h5>
                            <span class="badge bg-primary"><?= count($vouchers) ?></span>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($vouchers)): ?>
                            <div class="p-4 text-center text-muted">No vouchers generated yet</div>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0">
                                    <thead>
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
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="page-title mb-0"><i class="bi bi-receipt"></i> Billing History</h4>
            </div>
            
            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <?php if (empty($billing)): ?>
                    <div class="p-4 text-center text-muted">No billing records yet</div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
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

            <?php elseif ($view === 'ip_pools'): ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="page-title mb-0"><i class="bi bi-diagram-2"></i> IP Address Pools</h4>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPoolModal">
                    <i class="bi bi-plus-lg me-1"></i> Add Pool
                </button>
            </div>
            
            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <div class="p-5 text-center text-muted">
                        <i class="bi bi-diagram-2 fs-1 mb-3 d-block"></i>
                        <h5>IP Pool Management</h5>
                        <p>Configure IP address pools for dynamic allocation to subscribers.</p>
                    </div>
                </div>
            </div>

            <?php elseif ($view === 'expiring'): ?>
            <?php $expiringList = $radiusBilling->getExpiringSubscriptions(14); ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="page-title mb-0"><i class="bi bi-clock-history"></i> Expiring Subscribers</h4>
                <form method="post" class="d-inline">
                    <input type="hidden" name="action" value="send_expiry_alerts">
                    <button type="submit" class="btn btn-warning"><i class="bi bi-send me-1"></i> Send Expiry Alerts</button>
                </form>
            </div>
            
            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <?php if (empty($expiringList)): ?>
                    <div class="p-5 text-center text-muted">
                        <i class="bi bi-check-circle fs-1 mb-3 d-block text-success"></i>
                        <h5>All Clear!</h5>
                        <p>No subscribers expiring in the next 14 days.</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Customer</th>
                                    <th>Username</th>
                                    <th>Package</th>
                                    <th>Expiry Date</th>
                                    <th>Days Left</th>
                                    <th>Amount</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($expiringList as $sub): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($sub['customer_name'] ?? 'N/A') ?></strong>
                                        <br><small class="text-muted"><?= htmlspecialchars($sub['customer_phone'] ?? '') ?></small>
                                    </td>
                                    <td><code><?= htmlspecialchars($sub['username']) ?></code></td>
                                    <td><?= htmlspecialchars($sub['package_name'] ?? 'N/A') ?></td>
                                    <td><?= date('M j, Y', strtotime($sub['expiry_date'])) ?></td>
                                    <td>
                                        <?php $days = (int)$sub['days_remaining']; ?>
                                        <span class="badge bg-<?= $days <= 1 ? 'danger' : ($days <= 3 ? 'warning' : 'info') ?>">
                                            <?= $days ?> day<?= $days != 1 ? 's' : '' ?>
                                        </span>
                                    </td>
                                    <td>KES <?= number_format($sub['package_price'] ?? 0) ?></td>
                                    <td>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="action" value="renew_subscription">
                                            <input type="hidden" name="subscription_id" value="<?= $sub['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-success"><i class="bi bi-arrow-repeat"></i> Renew</button>
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

            <?php elseif ($view === 'reports'): ?>
            <?php 
            $revenueReport = $radiusBilling->getRevenueReport('monthly');
            $packageStats = $radiusBilling->getPackagePopularity();
            $subStats = $radiusBilling->getSubscriptionStats();
            ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="page-title mb-0"><i class="bi bi-graph-up"></i> Revenue Reports</h4>
            </div>
            
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="fs-2 fw-bold text-success"><?= number_format($subStats['active'] ?? 0) ?></div>
                            <div class="text-muted">Active Subscribers</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="fs-2 fw-bold text-warning"><?= number_format($subStats['expiring_week'] ?? 0) ?></div>
                            <div class="text-muted">Expiring This Week</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="fs-2 fw-bold text-danger"><?= number_format($subStats['suspended'] ?? 0) ?></div>
                            <div class="text-muted">Suspended</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="fs-2 fw-bold text-info"><?= number_format($subStats['total'] ?? 0) ?></div>
                            <div class="text-muted">Total Subscribers</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row g-4">
                <div class="col-md-8">
                    <div class="card shadow-sm">
                        <div class="card-header"><i class="bi bi-cash me-2"></i>Monthly Revenue</div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Period</th>
                                            <th>Transactions</th>
                                            <th>Total Revenue</th>
                                            <th>Paid</th>
                                            <th>Pending</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($revenueReport as $row): ?>
                                        <tr>
                                            <td><?= date('F Y', strtotime($row['period'])) ?></td>
                                            <td><?= number_format($row['transactions']) ?></td>
                                            <td><strong>KES <?= number_format($row['total_revenue'] ?? 0) ?></strong></td>
                                            <td class="text-success">KES <?= number_format($row['paid_revenue'] ?? 0) ?></td>
                                            <td class="text-warning">KES <?= number_format($row['pending_revenue'] ?? 0) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($revenueReport)): ?>
                                        <tr><td colspan="5" class="text-center text-muted py-4">No billing data yet</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow-sm">
                        <div class="card-header"><i class="bi bi-box me-2"></i>Package Popularity</div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <?php foreach ($packageStats as $pkg): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?= htmlspecialchars($pkg['name']) ?></strong>
                                        <br><small class="text-muted">KES <?= number_format($pkg['price']) ?>/mo</small>
                                    </div>
                                    <span class="badge bg-primary rounded-pill"><?= $pkg['active_count'] ?> active</span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php elseif ($view === 'analytics'): ?>
            <?php 
            $topUsers = $radiusBilling->getTopUsers(10, 'month');
            $peakHours = $radiusBilling->getPeakHours();
            ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="page-title mb-0"><i class="bi bi-bar-chart"></i> Usage Analytics</h4>
            </div>
            
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="card-header"><i class="bi bi-trophy me-2"></i>Top 10 Users (This Month)</div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>User</th>
                                            <th>Download</th>
                                            <th>Upload</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($topUsers as $i => $user): ?>
                                        <tr>
                                            <td><?= $i + 1 ?></td>
                                            <td>
                                                <strong><?= htmlspecialchars($user['customer_name'] ?? $user['username']) ?></strong>
                                                <br><small class="text-muted"><?= htmlspecialchars($user['username']) ?></small>
                                            </td>
                                            <td><?= number_format($user['download_gb'] ?? 0, 2) ?> GB</td>
                                            <td><?= number_format($user['upload_gb'] ?? 0, 2) ?> GB</td>
                                            <td><strong><?= number_format($user['total_gb'] ?? 0, 2) ?> GB</strong></td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($topUsers)): ?>
                                        <tr><td colspan="5" class="text-center text-muted py-4">No usage data yet</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="card-header"><i class="bi bi-clock me-2"></i>Peak Usage Hours</div>
                        <div class="card-body">
                            <?php if (!empty($peakHours)): ?>
                            <div class="row">
                                <?php foreach ($peakHours as $hour): ?>
                                <div class="col-3 mb-2">
                                    <div class="text-center p-2 rounded" style="background: rgba(14,165,233,<?= min(1, ($hour['session_count'] / max(1, max(array_column($peakHours, 'session_count')))) * 0.5 + 0.1) ?>);">
                                        <div class="fw-bold"><?= str_pad($hour['hour'], 2, '0', STR_PAD_LEFT) ?>:00</div>
                                        <small><?= $hour['session_count'] ?> sessions</small>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                            <p class="text-center text-muted py-4">No session data yet</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php elseif ($view === 'import'): ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="page-title mb-0"><i class="bi bi-upload"></i> Bulk Import Subscribers</h4>
            </div>
            
            <div class="row">
                <div class="col-md-8">
                    <div class="card shadow-sm">
                        <div class="card-header">Upload CSV File</div>
                        <div class="card-body">
                            <form method="post" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="import_csv">
                                <div class="mb-3">
                                    <label class="form-label">CSV File</label>
                                    <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Or paste CSV content</label>
                                    <textarea name="csv_content" class="form-control font-monospace" rows="10" placeholder="customer_id,package_id,username,password,access_type,static_ip,mac_address,notes"></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary"><i class="bi bi-upload me-1"></i> Import</button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow-sm">
                        <div class="card-header">CSV Format</div>
                        <div class="card-body">
                            <p class="small">Required columns:</p>
                            <ul class="small">
                                <li><code>username</code> - PPPoE username</li>
                                <li><code>password</code> - PPPoE password</li>
                            </ul>
                            <p class="small">Optional columns:</p>
                            <ul class="small">
                                <li><code>customer_id</code> - Link to customer</li>
                                <li><code>package_id</code> - Package ID</li>
                                <li><code>access_type</code> - pppoe/hotspot/static/dhcp</li>
                                <li><code>static_ip</code> - Static IP address</li>
                                <li><code>mac_address</code> - MAC binding</li>
                                <li><code>notes</code> - Notes</li>
                            </ul>
                            <hr>
                            <p class="small mb-1"><strong>Example:</strong></p>
                            <code class="small">username,password,package_id<br>user1,pass123,1<br>user2,pass456,2</code>
                        </div>
                    </div>
                </div>
            </div>

            <?php elseif ($view === 'settings'): ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="page-title mb-0"><i class="bi bi-gear"></i> ISP Settings</h4>
            </div>
            
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="card-header">RADIUS Server Configuration</div>
                        <div class="card-body">
                            <p class="text-muted">Configure your RADIUS server settings for MikroTik integration.</p>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                RADIUS server settings are configured in your MikroTik router. Point your NAS devices to this server's IP address.
                            </div>
                            <ul class="list-unstyled">
                                <li><strong>Auth Port:</strong> 1812/UDP</li>
                                <li><strong>Acct Port:</strong> 1813/UDP</li>
                                <li><strong>CoA Port:</strong> 3799/UDP</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="card-header">NAS Status</div>
                        <div class="card-body p-0">
                            <?php $nasStatus = $radiusBilling->getNASStatus(); ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($nasStatus as $nas): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?= htmlspecialchars($nas['name']) ?></strong>
                                        <br><small class="text-muted"><?= $nas['ip_address'] ?></small>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-<?= $nas['online'] ? 'success' : 'danger' ?>">
                                            <?= $nas['online'] ? 'Online' : 'Offline' ?>
                                        </span>
                                        <?php if ($nas['online'] && $nas['latency_ms']): ?>
                                        <br><small class="text-muted"><?= $nas['latency_ms'] ?>ms</small>
                                        <?php endif; ?>
                                        <br><small><?= $nas['active_sessions'] ?> sessions</small>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <?php if (empty($nasStatus)): ?>
                                <div class="list-group-item text-center text-muted">No NAS devices configured</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function regeneratePassword() {
        const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
        let password = '';
        for (let i = 0; i < 8; i++) {
            password += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        document.getElementById('pppoe_password').value = password;
    }
    
    function togglePasswordVisibility() {
        const passwordInput = document.getElementById('pppoe_password');
        const icon = document.getElementById('passwordToggleIcon');
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
        } else {
            passwordInput.type = 'password';
            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
        }
    }
    
    function editNAS(nas) {
        document.getElementById('edit_nas_id').value = nas.id;
        document.getElementById('edit_nas_name').value = nas.name;
        document.getElementById('edit_nas_ip').value = nas.ip_address;
        document.getElementById('edit_nas_ports').value = nas.ports;
        document.getElementById('edit_nas_description').value = nas.description || '';
        document.getElementById('edit_nas_active').checked = nas.is_active == 1;
        document.getElementById('edit_api_enabled').checked = nas.api_enabled == 1;
        document.getElementById('edit_api_port').value = nas.api_port || 8728;
        document.getElementById('edit_api_username').value = nas.api_username || '';
        new bootstrap.Modal(document.getElementById('editNASModal')).show();
    }
    
    function testNAS(nasId, ipAddress) {
        const modal = new bootstrap.Modal(document.getElementById('testNASModal'));
        const resultDiv = document.getElementById('testNASResult');
        
        resultDiv.innerHTML = `
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Testing...</span>
            </div>
            <p class="mt-2">Testing connectivity to ${ipAddress}...</p>
        `;
        modal.show();
        
        fetch('/index.php?page=isp&action=test_nas&id=' + nasId)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.online) {
                    resultDiv.innerHTML = `
                        <i class="bi bi-check-circle-fill text-success fs-1"></i>
                        <h5 class="mt-3 text-success">Reachable</h5>
                        <p class="mb-0">Latency: ${data.latency_ms ? data.latency_ms + ' ms' : 'N/A'}</p>
                    `;
                } else {
                    resultDiv.innerHTML = `
                        <i class="bi bi-x-circle-fill text-danger fs-1"></i>
                        <h5 class="mt-3 text-danger">Unreachable</h5>
                        <p class="mb-0">${data.error || 'Could not reach the device'}</p>
                    `;
                }
            })
            .catch(error => {
                resultDiv.innerHTML = `
                    <i class="bi bi-exclamation-triangle-fill text-warning fs-1"></i>
                    <h5 class="mt-3 text-warning">Error</h5>
                    <p class="mb-0">Failed to test connectivity</p>
                `;
            });
    }
    
    function pingSubscriber(subId, username) {
        const modal = new bootstrap.Modal(document.getElementById('testNASModal'));
        const resultDiv = document.getElementById('testNASResult');
        
        resultDiv.innerHTML = `
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Pinging...</span>
            </div>
            <p class="mt-2">Pinging subscriber ${username}...</p>
        `;
        modal.show();
        
        fetch('/index.php?page=isp&action=ping_subscriber&id=' + subId)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.online) {
                    resultDiv.innerHTML = `
                        <i class="bi bi-check-circle-fill text-success fs-1"></i>
                        <h5 class="mt-3 text-success">Reachable</h5>
                        <p class="mb-1"><strong>IP:</strong> ${data.ip_address}</p>
                        <p class="mb-0"><strong>Latency:</strong> ${data.latency_ms ? data.latency_ms + ' ms' : 'N/A'}</p>
                    `;
                } else if (data.error) {
                    resultDiv.innerHTML = `
                        <i class="bi bi-exclamation-triangle-fill text-warning fs-1"></i>
                        <h5 class="mt-3 text-warning">Cannot Ping</h5>
                        <p class="mb-0">${data.error}</p>
                    `;
                } else {
                    resultDiv.innerHTML = `
                        <i class="bi bi-x-circle-fill text-danger fs-1"></i>
                        <h5 class="mt-3 text-danger">Unreachable</h5>
                        <p class="mb-1"><strong>IP:</strong> ${data.ip_address || 'Unknown'}</p>
                        <p class="mb-0">Could not reach the subscriber</p>
                    `;
                }
            })
            .catch(error => {
                resultDiv.innerHTML = `
                    <i class="bi bi-exclamation-triangle-fill text-warning fs-1"></i>
                    <h5 class="mt-3 text-warning">Error</h5>
                    <p class="mb-0">Failed to ping subscriber</p>
                `;
            });
    }
    </script>
</body>
</html>
