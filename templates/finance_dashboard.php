<?php
try {
    require_once __DIR__ . '/../src/Mpesa.php';
    $mpesa = new \App\Mpesa();
    $db = \Database::getConnection();
    
    $view = $_GET['view'] ?? 'dashboard';
    $stats = $mpesa->getDashboardStats();
    $config = $mpesa->getConfig();
} catch (\Exception $e) {
    error_log("Finance dashboard init error: " . $e->getMessage());
    $mpesa = null;
    $db = \Database::getConnection();
    $view = $_GET['view'] ?? 'dashboard';
    $stats = ['stk' => ['total' => 0, 'success' => 0, 'failed' => 0, 'pending' => 0, 'total_amount' => 0], 'c2b' => ['total' => 0, 'success' => 0, 'total_amount' => 0], 'b2c' => ['total' => 0, 'success' => 0, 'failed' => 0, 'pending' => 0, 'total_amount' => 0], 'b2b' => ['total' => 0, 'success' => 0, 'failed' => 0, 'total_amount' => 0]];
    $config = [];
    $initError = $e->getMessage();
}

$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $mpesa) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_b2c_config') {
        $mpesa->saveConfig('mpesa_b2c_shortcode', $_POST['b2c_shortcode'] ?? '');
        $mpesa->saveConfig('mpesa_b2c_initiator_name', $_POST['b2c_initiator_name'] ?? '');
        $mpesa->saveConfig('mpesa_b2c_initiator_password', $_POST['b2c_initiator_password'] ?? '', true);
        $mpesa->saveConfig('mpesa_b2c_callback_url', $_POST['b2c_callback_url'] ?? '');
        $mpesa->saveConfig('mpesa_b2c_timeout_url', $_POST['b2c_timeout_url'] ?? '');
        $_SESSION['success'] = 'B2C configuration saved successfully';
        header('Location: ?page=finance&view=settings');
        exit;
    }
    
    if ($action === 'save_b2b_config') {
        $mpesa->saveConfig('mpesa_b2b_shortcode', $_POST['b2b_shortcode'] ?? '');
        $mpesa->saveConfig('mpesa_b2b_initiator_name', $_POST['b2b_initiator_name'] ?? '');
        $mpesa->saveConfig('mpesa_b2b_callback_url', $_POST['b2b_callback_url'] ?? '');
        $mpesa->saveConfig('mpesa_b2b_timeout_url', $_POST['b2b_timeout_url'] ?? '');
        $_SESSION['success'] = 'B2B configuration saved successfully';
        header('Location: ?page=finance&view=settings');
        exit;
    }
    
    if ($action === 'manual_b2c') {
        $result = $mpesa->b2cPayment(
            $_POST['phone'],
            (float)$_POST['amount'],
            $_POST['command_id'] ?? 'BusinessPayment',
            $_POST['remarks'] ?? 'Manual Payment',
            $_POST['occasion'] ?? '',
            'manual',
            null,
            null,
            $_SESSION['user_id'] ?? null
        );
        if ($result['success']) {
            $_SESSION['success'] = 'B2C payment initiated: ' . ($result['conversation_id'] ?? '');
        } else {
            $_SESSION['error'] = 'B2C payment failed: ' . $result['message'];
        }
        header('Location: ?page=finance&view=b2c');
        exit;
    }
    
    if ($action === 'manual_b2b') {
        $result = $mpesa->b2bPayment(
            $_POST['receiver_shortcode'],
            (float)$_POST['amount'],
            $_POST['account_ref'] ?? '',
            $_POST['command_id'] ?? 'BusinessPayBill',
            $_POST['remarks'] ?? 'Business Payment',
            $_POST['receiver_type'] ?? '4',
            null,
            null,
            $_SESSION['user_id'] ?? null
        );
        if ($result['success']) {
            $_SESSION['success'] = 'B2B payment initiated: ' . ($result['conversation_id'] ?? '');
        } else {
            $_SESSION['error'] = 'B2B payment failed: ' . $result['message'];
        }
        header('Location: ?page=finance&view=b2b');
        exit;
    }
}

$b2cTransactions = [];
$b2bTransactions = [];
$stkTransactions = [];
$c2bTransactions = [];

if ($view === 'b2c' && $mpesa) {
    $b2cTransactions = $mpesa->getB2CTransactions($_GET, 50);
}
if ($view === 'b2b' && $mpesa) {
    $b2bTransactions = $mpesa->getB2BTransactions($_GET, 50);
}
if ($view === 'c2b' || $view === 'dashboard') {
    try {
        $stmt = $db->query("SELECT * FROM mpesa_transactions ORDER BY created_at DESC LIMIT 50");
        $stkTransactions = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $stmt = $db->query("SELECT * FROM mpesa_c2b_transactions ORDER BY created_at DESC LIMIT 50");
        $c2bTransactions = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    } catch (\Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finance Dashboard - ISP CRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --mpesa-primary: #00a650;
            --mpesa-secondary: #f8f9fa;
            --mpesa-accent: #198754;
            --mpesa-bg: #f8f9fa;
            --mpesa-card: #ffffff;
            --mpesa-text: #212529;
            --mpesa-muted: #6c757d;
            --mpesa-border: #dee2e6;
            --mpesa-success: #198754;
            --mpesa-danger: #dc3545;
            --mpesa-warning: #ffc107;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--mpesa-bg);
            min-height: 100vh;
        }
        
        .mpesa-layout {
            display: flex;
            min-height: 100vh;
        }
        
        .mpesa-sidebar {
            width: 260px;
            background: linear-gradient(135deg, #1a1c2c 0%, #2d3250 100%);
            border-right: 1px solid rgba(255,255,255,0.1);
            padding: 1.5rem 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }
        
        .mpesa-sidebar .brand {
            padding: 0 1.5rem 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 1rem;
        }
        
        .mpesa-sidebar .brand h4 {
            color: #fff;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .mpesa-sidebar .brand small {
            color: rgba(255,255,255,0.6);
            font-size: 0.75rem;
        }
        
        .mpesa-sidebar .nav-link {
            color: rgba(255,255,255,0.7);
            padding: 0.75rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            transition: all 0.2s;
            border-left: 3px solid transparent;
        }
        
        .mpesa-sidebar .nav-link:hover {
            color: #fff;
            background: rgba(255,255,255,0.1);
        }
        
        .mpesa-sidebar .nav-link.active {
            color: #fff;
            background: rgba(0, 166, 80, 0.3);
            border-left-color: var(--mpesa-primary);
        }
        
        .mpesa-content {
            flex: 1;
            margin-left: 260px;
            padding: 2rem;
            background: var(--mpesa-bg);
        }
        
        .stat-card {
            background: var(--mpesa-card);
            border: 1px solid var(--mpesa-border);
            border-radius: 12px;
            padding: 1.5rem;
            color: var(--mpesa-text);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }
        
        .stat-card.incoming {
            border-top: 3px solid var(--mpesa-success);
        }
        
        .stat-card.outgoing {
            border-top: 3px solid var(--mpesa-warning);
        }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            line-height: 1.2;
        }
        
        .stat-label {
            color: var(--mpesa-muted);
            font-size: 0.875rem;
        }
        
        .card {
            background: var(--mpesa-card);
            border: 1px solid var(--mpesa-border);
            border-radius: 12px;
            color: var(--mpesa-text);
        }
        
        .card-header {
            background: rgba(0, 166, 80, 0.1);
            border-bottom: 1px solid var(--mpesa-border);
            padding: 1rem 1.25rem;
        }
        
        .table {
            color: var(--mpesa-text);
            margin: 0;
        }
        
        .table thead th {
            background: #f8f9fa;
            border-color: var(--mpesa-border);
            color: var(--mpesa-muted);
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .table tbody td {
            border-color: var(--mpesa-border);
            vertical-align: middle;
        }
        
        .table tbody tr:hover {
            background: rgba(0, 166, 80, 0.05);
        }
        
        .badge-success { background: var(--mpesa-success); }
        .badge-danger { background: var(--mpesa-danger); }
        .badge-warning { background: var(--mpesa-warning); color: #000; }
        .badge-pending { background: #6366f1; }
        
        .form-control, .form-select {
            background: #ffffff;
            border-color: var(--mpesa-border);
            color: var(--mpesa-text);
        }
        
        .form-control:focus, .form-select:focus {
            background: #ffffff;
            border-color: var(--mpesa-primary);
            color: var(--mpesa-text);
            box-shadow: 0 0 0 0.2rem rgba(0, 166, 80, 0.25);
        }
        
        .form-label {
            color: var(--mpesa-muted);
            font-size: 0.875rem;
        }
        
        .btn-mpesa {
            background: var(--mpesa-primary);
            border-color: var(--mpesa-primary);
            color: #fff;
        }
        
        .btn-mpesa:hover {
            background: #008c44;
            border-color: #008c44;
            color: #fff;
        }
        
        .btn-outline-mpesa {
            border-color: var(--mpesa-primary);
            color: var(--mpesa-primary);
        }
        
        .btn-outline-mpesa:hover {
            background: var(--mpesa-primary);
            color: #fff;
        }
        
        .page-title {
            color: var(--mpesa-text);
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .text-muted {
            color: var(--mpesa-muted) !important;
        }
        
        .mpesa-sidebar::-webkit-scrollbar {
            width: 6px;
        }
        
        .mpesa-sidebar::-webkit-scrollbar-track {
            background: transparent;
        }
        
        .mpesa-sidebar::-webkit-scrollbar-thumb {
            background: var(--mpesa-border);
            border-radius: 3px;
        }
        
        .live-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.75rem;
            color: var(--mpesa-success);
            font-weight: 500;
        }
        
        .live-dot {
            width: 8px;
            height: 8px;
            background: var(--mpesa-success);
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(0.8); }
        }
        
        .amount-display {
            font-family: 'SF Mono', 'Roboto Mono', monospace;
            font-weight: 600;
        }
        
        .quick-action-btn {
            background: #ffffff;
            border: 1px solid var(--mpesa-border);
            color: var(--mpesa-text);
            padding: 1rem 1.5rem;
            border-radius: 12px;
            text-align: center;
            transition: all 0.2s;
            text-decoration: none;
            display: block;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .quick-action-btn:hover {
            background: #f8f9fa;
            border-color: var(--mpesa-primary);
            color: var(--mpesa-text);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 166, 80, 0.15);
        }
        
        .quick-action-btn i {
            font-size: 2rem;
            color: var(--mpesa-primary);
            display: block;
            margin-bottom: 0.5rem;
        }
        
        .finance-mobile-header {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 56px;
            background: linear-gradient(135deg, #1a1c2c 0%, #2d3250 100%);
            z-index: 1050;
            padding: 0 1rem;
            align-items: center;
            justify-content: space-between;
        }
        .finance-mobile-header .brand-mobile {
            color: #fff;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .finance-mobile-header .hamburger-btn {
            background: rgba(255,255,255,0.1);
            border: none;
            color: #fff;
            font-size: 1.5rem;
            padding: 0.5rem 0.75rem;
            border-radius: 8px;
        }
        .finance-offcanvas {
            background: linear-gradient(135deg, #1a1c2c 0%, #2d3250 100%) !important;
            width: 280px !important;
        }
        .finance-offcanvas .offcanvas-header {
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .finance-offcanvas .btn-close {
            filter: invert(1);
        }
        .finance-offcanvas .nav-link {
            color: rgba(255,255,255,0.7);
            padding: 0.75rem 1rem;
        }
        .finance-offcanvas .nav-link:hover {
            color: #fff;
            background: rgba(255,255,255,0.1);
        }
        .finance-offcanvas .nav-link.active {
            color: #fff;
            background: rgba(0, 166, 80, 0.3);
        }
        
        @media (max-width: 991.98px) {
            .mpesa-sidebar {
                display: none !important;
            }
            .finance-mobile-header {
                display: flex !important;
            }
            .mpesa-content {
                margin-left: 0 !important;
                padding: 1rem !important;
                padding-top: 70px !important;
            }
            .stat-value {
                font-size: 1.5rem !important;
            }
        }
        
        @media (max-width: 767.98px) {
            .form-control, .form-select, .btn {
                min-height: 44px;
            }
        }
        
        @media (min-width: 992px) {
            .finance-mobile-header {
                display: none !important;
            }
            .mpesa-sidebar {
                display: block !important;
            }
        }
    </style>
</head>
<body>
    <!-- Finance Mobile Header -->
    <div class="finance-mobile-header">
        <div class="brand-mobile">
            <i class="bi bi-bank"></i> Finance
        </div>
        <button class="hamburger-btn" type="button" data-bs-toggle="offcanvas" data-bs-target="#financeMobileSidebar">
            <i class="bi bi-list"></i>
        </button>
    </div>
    
    <!-- Finance Mobile Offcanvas Sidebar -->
    <div class="offcanvas offcanvas-start finance-offcanvas" tabindex="-1" id="financeMobileSidebar">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title text-white"><i class="bi bi-bank me-2"></i>Finance</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body">
            <nav class="nav flex-column">
                <a class="nav-link <?= $view === 'dashboard' ? 'active' : '' ?>" href="?page=finance&view=dashboard">
                    <i class="bi bi-speedometer2 me-2"></i> Dashboard
                </a>
                <a class="nav-link <?= $view === 'c2b' ? 'active' : '' ?>" href="?page=finance&view=c2b">
                    <i class="bi bi-arrow-down-circle me-2"></i> C2B Collections
                </a>
                <a class="nav-link <?= $view === 'b2c' ? 'active' : '' ?>" href="?page=finance&view=b2c">
                    <i class="bi bi-arrow-up-circle me-2"></i> B2C Disbursements
                </a>
                <a class="nav-link <?= $view === 'b2b' ? 'active' : '' ?>" href="?page=finance&view=b2b">
                    <i class="bi bi-arrow-left-right me-2"></i> B2B Payments
                </a>
                <hr class="my-2 border-light opacity-25">
                <a class="nav-link <?= $view === 'settings' ? 'active' : '' ?>" href="?page=finance&view=settings">
                    <i class="bi bi-gear me-2"></i> M-Pesa Settings
                </a>
                <hr class="my-2 border-light opacity-25">
                <a class="nav-link" href="?page=dashboard">
                    <i class="bi bi-arrow-left me-2"></i> Back to CRM
                </a>
            </nav>
        </div>
    </div>
    
    <?php if (!empty($initError)): ?>
    <div class="alert alert-warning m-3">
        <strong>Setup Required:</strong> Some finance database tables are missing. Please run the database migration or create the tables manually. The dashboard will show zeros until tables are created.
    </div>
    <?php endif; ?>
    <div class="mpesa-layout">
        <aside class="mpesa-sidebar d-none d-lg-block">
            <div class="brand">
                <h4><i class="bi bi-bank"></i> Finance</h4>
                <small>Payment & Disbursement</small>
            </div>
            
            <nav class="nav flex-column">
                <a class="nav-link <?= $view === 'dashboard' ? 'active' : '' ?>" href="?page=finance&view=dashboard">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
                <a class="nav-link <?= $view === 'c2b' ? 'active' : '' ?>" href="?page=finance&view=c2b">
                    <i class="bi bi-arrow-down-circle"></i> C2B Collections
                </a>
                <a class="nav-link <?= $view === 'b2c' ? 'active' : '' ?>" href="?page=finance&view=b2c">
                    <i class="bi bi-arrow-up-circle"></i> B2C Disbursements
                </a>
                <a class="nav-link <?= $view === 'b2b' ? 'active' : '' ?>" href="?page=finance&view=b2b">
                    <i class="bi bi-arrow-left-right"></i> B2B Payments
                </a>
                <hr class="my-2 border-secondary opacity-25">
                <a class="nav-link <?= $view === 'settings' ? 'active' : '' ?>" href="?page=finance&view=settings">
                    <i class="bi bi-gear"></i> M-Pesa Settings
                </a>
                <hr class="my-2 border-secondary opacity-25">
                <a class="nav-link" href="?page=dashboard">
                    <i class="bi bi-arrow-left"></i> Back to CRM
                </a>
            </nav>
        </aside>
        
        <main class="mpesa-content">
            <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <?php if ($view === 'dashboard'): ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="page-title mb-1"><i class="bi bi-speedometer2"></i> Finance Dashboard</h4>
                    <div class="d-flex align-items-center gap-3">
                        <span class="text-muted small">Last 30 days overview</span>
                        <span class="live-indicator"><span class="live-dot"></span> Live</span>
                    </div>
                </div>
                <button class="btn btn-outline-mpesa" onclick="location.reload()">
                    <i class="bi bi-arrow-clockwise me-1"></i> Refresh
                </button>
            </div>
            
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="stat-card incoming">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="stat-icon bg-success bg-opacity-10 text-success">
                                <i class="bi bi-arrow-down-circle"></i>
                            </div>
                            <span class="badge bg-success"><?= $stats['stk']['success'] ?? 0 ?> successful</span>
                        </div>
                        <div class="stat-value text-success">KES <?= number_format($stats['stk']['total_amount'] ?? 0) ?></div>
                        <div class="stat-label">STK Push Collections</div>
                        <small class="text-muted"><?= $stats['stk']['total'] ?? 0 ?> transactions</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card incoming">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="stat-icon bg-info bg-opacity-10 text-info">
                                <i class="bi bi-cash-stack"></i>
                            </div>
                            <span class="badge bg-info"><?= $stats['c2b']['success'] ?? 0 ?> received</span>
                        </div>
                        <div class="stat-value text-info">KES <?= number_format($stats['c2b']['total_amount'] ?? 0) ?></div>
                        <div class="stat-label">C2B Payments</div>
                        <small class="text-muted"><?= $stats['c2b']['total'] ?? 0 ?> transactions</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card outgoing">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                                <i class="bi bi-arrow-up-circle"></i>
                            </div>
                            <span class="badge bg-warning text-dark"><?= $stats['b2c']['pending'] ?? 0 ?> pending</span>
                        </div>
                        <div class="stat-value text-warning">KES <?= number_format($stats['b2c']['total_amount'] ?? 0) ?></div>
                        <div class="stat-label">B2C Disbursements</div>
                        <small class="text-muted"><?= $stats['b2c']['success'] ?? 0 ?>/<?= $stats['b2c']['total'] ?? 0 ?> successful</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card outgoing">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="stat-icon bg-purple bg-opacity-10" style="color: #a855f7;">
                                <i class="bi bi-arrow-left-right"></i>
                            </div>
                        </div>
                        <div class="stat-value" style="color: #a855f7;">KES <?= number_format($stats['b2b']['total_amount'] ?? 0) ?></div>
                        <div class="stat-label">B2B Transfers</div>
                        <small class="text-muted"><?= $stats['b2b']['success'] ?? 0 ?>/<?= $stats['b2b']['total'] ?? 0 ?> successful</small>
                    </div>
                </div>
            </div>
            
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <a href="?page=finance&view=c2b" class="quick-action-btn">
                        <i class="bi bi-arrow-down-circle"></i>
                        <strong>View Collections</strong>
                        <small class="d-block text-muted">STK Push & C2B payments</small>
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="?page=finance&view=b2c" class="quick-action-btn">
                        <i class="bi bi-send"></i>
                        <strong>Send Money</strong>
                        <small class="d-block text-muted">B2C disbursements</small>
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="?page=finance&view=b2b" class="quick-action-btn">
                        <i class="bi bi-building"></i>
                        <strong>Business Payments</strong>
                        <small class="d-block text-muted">B2B transfers</small>
                    </a>
                </div>
            </div>
            
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="bi bi-arrow-down-circle me-2"></i>Recent STK Push</h6>
                            <a href="?page=finance&view=c2b" class="btn btn-sm btn-outline-mpesa">View All</a>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead>
                                        <tr>
                                            <th>Phone</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_slice($stkTransactions, 0, 5) as $tx): ?>
                                        <tr>
                                            <td><code><?= htmlspecialchars($tx['phone'] ?? '') ?></code></td>
                                            <td class="amount-display">KES <?= number_format($tx['amount'] ?? 0) ?></td>
                                            <td>
                                                <?php
                                                $statusClass = match($tx['status'] ?? '') {
                                                    'success' => 'success',
                                                    'failed' => 'danger',
                                                    default => 'warning'
                                                };
                                                ?>
                                                <span class="badge badge-<?= $statusClass ?>"><?= $tx['status'] ?? 'pending' ?></span>
                                            </td>
                                            <td class="text-muted small"><?= date('M j, H:i', strtotime($tx['created_at'] ?? 'now')) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($stkTransactions)): ?>
                                        <tr><td colspan="4" class="text-center text-muted py-3">No transactions yet</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="bi bi-arrow-up-circle me-2"></i>Recent B2C Disbursements</h6>
                            <a href="?page=finance&view=b2c" class="btn btn-sm btn-outline-mpesa">View All</a>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead>
                                        <tr>
                                            <th>Phone</th>
                                            <th>Amount</th>
                                            <th>Purpose</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $recentB2C = $mpesa->getB2CTransactions([], 5);
                                        foreach ($recentB2C as $tx): 
                                        ?>
                                        <tr>
                                            <td><code><?= htmlspecialchars($tx['phone'] ?? '') ?></code></td>
                                            <td class="amount-display">KES <?= number_format($tx['amount'] ?? 0) ?></td>
                                            <td><span class="badge bg-secondary"><?= $tx['purpose'] ?? 'manual' ?></span></td>
                                            <td>
                                                <?php
                                                $statusClass = match($tx['status'] ?? '') {
                                                    'success' => 'success',
                                                    'failed' => 'danger',
                                                    'queued', 'processing' => 'info',
                                                    default => 'warning'
                                                };
                                                ?>
                                                <span class="badge badge-<?= $statusClass ?>"><?= $tx['status'] ?? 'pending' ?></span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($recentB2C)): ?>
                                        <tr><td colspan="4" class="text-center text-muted py-3">No disbursements yet</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php elseif ($view === 'c2b'): ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="page-title mb-1"><i class="bi bi-arrow-down-circle"></i> C2B Collections</h4>
                    <small class="text-muted">STK Push and C2B payment transactions</small>
                </div>
            </div>
            
            <ul class="nav nav-tabs mb-4" style="border-color: var(--mpesa-border);">
                <li class="nav-item">
                    <a class="nav-link active" data-bs-toggle="tab" href="#stkTab" style="color: var(--mpesa-text);">STK Push</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#c2bTab" style="color: var(--mpesa-text);">C2B Payments</a>
                </li>
            </ul>
            
            <div class="tab-content">
                <div class="tab-pane fade show active" id="stkTab">
                    <div class="card">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table mb-0">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Phone</th>
                                            <th>Amount</th>
                                            <th>Reference</th>
                                            <th>Receipt</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($stkTransactions as $tx): ?>
                                        <tr>
                                            <td class="text-muted"><?= date('M j, Y H:i', strtotime($tx['created_at'] ?? 'now')) ?></td>
                                            <td><code><?= htmlspecialchars($tx['phone'] ?? '') ?></code></td>
                                            <td class="amount-display fw-bold">KES <?= number_format($tx['amount'] ?? 0) ?></td>
                                            <td><?= htmlspecialchars($tx['account_reference'] ?? '-') ?></td>
                                            <td><code><?= htmlspecialchars($tx['mpesa_receipt'] ?? '-') ?></code></td>
                                            <td>
                                                <?php
                                                $statusClass = match($tx['status'] ?? '') {
                                                    'success' => 'success',
                                                    'failed' => 'danger',
                                                    default => 'warning'
                                                };
                                                ?>
                                                <span class="badge badge-<?= $statusClass ?>"><?= $tx['status'] ?? 'pending' ?></span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($stkTransactions)): ?>
                                        <tr><td colspan="6" class="text-center text-muted py-4">No STK Push transactions found</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="tab-pane fade" id="c2bTab">
                    <div class="card">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table mb-0">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Phone</th>
                                            <th>Amount</th>
                                            <th>Account</th>
                                            <th>Receipt</th>
                                            <th>Type</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($c2bTransactions as $tx): ?>
                                        <tr>
                                            <td class="text-muted"><?= date('M j, Y H:i', strtotime($tx['created_at'] ?? $tx['trans_time'] ?? 'now')) ?></td>
                                            <td><code><?= htmlspecialchars($tx['msisdn'] ?? '') ?></code></td>
                                            <td class="amount-display fw-bold">KES <?= number_format($tx['trans_amount'] ?? 0) ?></td>
                                            <td><?= htmlspecialchars($tx['bill_ref_number'] ?? '-') ?></td>
                                            <td><code><?= htmlspecialchars($tx['trans_id'] ?? '-') ?></code></td>
                                            <td><span class="badge bg-info"><?= $tx['trans_type'] ?? 'C2B' ?></span></td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($c2bTransactions)): ?>
                                        <tr><td colspan="6" class="text-center text-muted py-4">No C2B transactions found</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php elseif ($view === 'b2c'): ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="page-title mb-1"><i class="bi bi-arrow-up-circle"></i> B2C Disbursements</h4>
                    <small class="text-muted">Send money to customers, employees, and vendors</small>
                </div>
                <button class="btn btn-mpesa" data-bs-toggle="modal" data-bs-target="#b2cModal">
                    <i class="bi bi-send me-1"></i> New Disbursement
                </button>
            </div>
            
            <?php if (!$mpesa->isB2CConfigured()): ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <strong>B2C not configured.</strong> Please configure B2C credentials in <a href="?page=finance&view=settings">Settings</a> first.
            </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Phone</th>
                                    <th>Amount</th>
                                    <th>Purpose</th>
                                    <th>Receipt</th>
                                    <th>Status</th>
                                    <th>Initiated By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($b2cTransactions as $tx): ?>
                                <tr>
                                    <td class="text-muted"><?= date('M j, Y H:i', strtotime($tx['created_at'])) ?></td>
                                    <td><code><?= htmlspecialchars($tx['phone']) ?></code></td>
                                    <td class="amount-display fw-bold">KES <?= number_format($tx['amount']) ?></td>
                                    <td><span class="badge bg-secondary"><?= $tx['purpose'] ?></span></td>
                                    <td><code><?= $tx['transaction_receipt'] ?: '-' ?></code></td>
                                    <td>
                                        <?php
                                        $statusClass = match($tx['status']) {
                                            'success' => 'success',
                                            'failed' => 'danger',
                                            'queued', 'processing' => 'info',
                                            default => 'warning'
                                        };
                                        ?>
                                        <span class="badge badge-<?= $statusClass ?>"><?= $tx['status'] ?></span>
                                        <?php if ($tx['result_desc']): ?>
                                        <small class="d-block text-muted" style="max-width: 200px; font-size: 0.7rem;"><?= htmlspecialchars(substr($tx['result_desc'], 0, 50)) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($tx['initiated_by_name'] ?? 'System') ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($b2cTransactions)): ?>
                                <tr><td colspan="7" class="text-center text-muted py-4">No B2C transactions found</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="modal fade" id="b2cModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content" style="background: var(--mpesa-card); color: var(--mpesa-text);">
                        <div class="modal-header border-secondary">
                            <h5 class="modal-title"><i class="bi bi-send me-2"></i>New B2C Disbursement</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="post">
                            <div class="modal-body">
                                <input type="hidden" name="action" value="manual_b2c">
                                <div class="mb-3">
                                    <label class="form-label">Phone Number</label>
                                    <input type="text" name="phone" class="form-control" placeholder="0712345678" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Amount (KES)</label>
                                    <input type="number" name="amount" class="form-control" min="10" step="1" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Command ID</label>
                                    <select name="command_id" class="form-select">
                                        <option value="BusinessPayment">Business Payment</option>
                                        <option value="SalaryPayment">Salary Payment</option>
                                        <option value="PromotionPayment">Promotion Payment</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Remarks</label>
                                    <input type="text" name="remarks" class="form-control" maxlength="100">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Occasion</label>
                                    <input type="text" name="occasion" class="form-control" maxlength="100">
                                </div>
                            </div>
                            <div class="modal-footer border-secondary">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-mpesa"><i class="bi bi-send me-1"></i> Send Money</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <?php elseif ($view === 'b2b'): ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="page-title mb-1"><i class="bi bi-arrow-left-right"></i> B2B Payments</h4>
                    <small class="text-muted">Business to business transfers</small>
                </div>
                <button class="btn btn-mpesa" data-bs-toggle="modal" data-bs-target="#b2bModal">
                    <i class="bi bi-building me-1"></i> New B2B Payment
                </button>
            </div>
            
            <?php if (!$mpesa->isB2BConfigured()): ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <strong>B2B not configured.</strong> Please configure B2B credentials in <a href="?page=finance&view=settings">Settings</a> first.
            </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Receiver</th>
                                    <th>Amount</th>
                                    <th>Account Ref</th>
                                    <th>Status</th>
                                    <th>Initiated By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($b2bTransactions as $tx): ?>
                                <tr>
                                    <td class="text-muted"><?= date('M j, Y H:i', strtotime($tx['created_at'])) ?></td>
                                    <td><code><?= htmlspecialchars($tx['receiver_shortcode']) ?></code></td>
                                    <td class="amount-display fw-bold">KES <?= number_format($tx['amount']) ?></td>
                                    <td><?= htmlspecialchars($tx['account_reference'] ?: '-') ?></td>
                                    <td>
                                        <?php
                                        $statusClass = match($tx['status']) {
                                            'success' => 'success',
                                            'failed' => 'danger',
                                            'queued', 'processing' => 'info',
                                            default => 'warning'
                                        };
                                        ?>
                                        <span class="badge badge-<?= $statusClass ?>"><?= $tx['status'] ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($tx['initiated_by_name'] ?? 'System') ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($b2bTransactions)): ?>
                                <tr><td colspan="6" class="text-center text-muted py-4">No B2B transactions found</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="modal fade" id="b2bModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content" style="background: var(--mpesa-card); color: var(--mpesa-text);">
                        <div class="modal-header border-secondary">
                            <h5 class="modal-title"><i class="bi bi-building me-2"></i>New B2B Payment</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="post">
                            <div class="modal-body">
                                <input type="hidden" name="action" value="manual_b2b">
                                <div class="mb-3">
                                    <label class="form-label">Receiver Shortcode (Till/Paybill)</label>
                                    <input type="text" name="receiver_shortcode" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Receiver Type</label>
                                    <select name="receiver_type" class="form-select">
                                        <option value="4">Paybill</option>
                                        <option value="2">Till Number</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Amount (KES)</label>
                                    <input type="number" name="amount" class="form-control" min="10" step="1" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Account Reference</label>
                                    <input type="text" name="account_ref" class="form-control" maxlength="20">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Command ID</label>
                                    <select name="command_id" class="form-select">
                                        <option value="BusinessPayBill">Business Pay Bill</option>
                                        <option value="BusinessBuyGoods">Business Buy Goods</option>
                                        <option value="MerchantToMerchantTransfer">Merchant Transfer</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Remarks</label>
                                    <input type="text" name="remarks" class="form-control" maxlength="100">
                                </div>
                            </div>
                            <div class="modal-footer border-secondary">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-mpesa"><i class="bi bi-send me-1"></i> Send Payment</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <?php elseif ($view === 'settings'): ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="page-title mb-1"><i class="bi bi-gear"></i> M-Pesa Settings</h4>
                    <small class="text-muted">Configure API credentials and callbacks</small>
                </div>
            </div>
            
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-arrow-up-circle me-2"></i>B2C Configuration</h6>
                        </div>
                        <div class="card-body">
                            <form method="post">
                                <input type="hidden" name="action" value="save_b2c_config">
                                <div class="mb-3">
                                    <label class="form-label">B2C Shortcode</label>
                                    <input type="text" name="b2c_shortcode" class="form-control" value="<?= htmlspecialchars($config['mpesa_b2c_shortcode'] ?? '') ?>">
                                    <small class="text-muted">Leave empty to use main shortcode</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Initiator Name</label>
                                    <input type="text" name="b2c_initiator_name" class="form-control" value="<?= htmlspecialchars($config['mpesa_b2c_initiator_name'] ?? '') ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Initiator Password</label>
                                    <input type="password" name="b2c_initiator_password" class="form-control" placeholder="<?= !empty($config['mpesa_b2c_initiator_password']) ? '••••••••' : '' ?>">
                                    <small class="text-muted">Leave empty to keep existing</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Result Callback URL</label>
                                    <input type="url" name="b2c_callback_url" class="form-control" value="<?= htmlspecialchars($config['mpesa_b2c_callback_url'] ?? '') ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Timeout Callback URL</label>
                                    <input type="url" name="b2c_timeout_url" class="form-control" value="<?= htmlspecialchars($config['mpesa_b2c_timeout_url'] ?? '') ?>">
                                </div>
                                <button type="submit" class="btn btn-mpesa"><i class="bi bi-save me-1"></i> Save B2C Config</button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-arrow-left-right me-2"></i>B2B Configuration</h6>
                        </div>
                        <div class="card-body">
                            <form method="post">
                                <input type="hidden" name="action" value="save_b2b_config">
                                <div class="mb-3">
                                    <label class="form-label">B2B Shortcode</label>
                                    <input type="text" name="b2b_shortcode" class="form-control" value="<?= htmlspecialchars($config['mpesa_b2b_shortcode'] ?? '') ?>">
                                    <small class="text-muted">Leave empty to use main shortcode</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Initiator Name</label>
                                    <input type="text" name="b2b_initiator_name" class="form-control" value="<?= htmlspecialchars($config['mpesa_b2b_initiator_name'] ?? '') ?>">
                                    <small class="text-muted">Leave empty to use B2C initiator</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Result Callback URL</label>
                                    <input type="url" name="b2b_callback_url" class="form-control" value="<?= htmlspecialchars($config['mpesa_b2b_callback_url'] ?? '') ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Timeout Callback URL</label>
                                    <input type="url" name="b2b_timeout_url" class="form-control" value="<?= htmlspecialchars($config['mpesa_b2b_timeout_url'] ?? '') ?>">
                                </div>
                                <button type="submit" class="btn btn-mpesa"><i class="bi bi-save me-1"></i> Save B2B Config</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Configuration Status</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <?php if ($mpesa->isConfigured()): ?>
                                <i class="bi bi-check-circle-fill text-success"></i>
                                <span>Main API Configured</span>
                                <?php else: ?>
                                <i class="bi bi-x-circle-fill text-danger"></i>
                                <span>Main API Not Configured</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <?php if ($mpesa->isB2CConfigured()): ?>
                                <i class="bi bi-check-circle-fill text-success"></i>
                                <span>B2C Ready</span>
                                <?php else: ?>
                                <i class="bi bi-x-circle-fill text-danger"></i>
                                <span>B2C Not Ready</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <?php if ($mpesa->isB2BConfigured()): ?>
                                <i class="bi bi-check-circle-fill text-success"></i>
                                <span>B2B Ready</span>
                                <?php else: ?>
                                <i class="bi bi-x-circle-fill text-danger"></i>
                                <span>B2B Not Ready</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php endif; ?>
        </main>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
