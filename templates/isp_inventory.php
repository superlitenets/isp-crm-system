<?php
$ispInv = new \App\ISPInventory();
$tab = $_GET['tab'] ?? 'overview';
$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$search = $_GET['search'] ?? '';

$successMessage = $_SESSION['success_message'] ?? null;
$errorMessage = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);

$sites = $ispInv->getSites();
$olts = $ispInv->getOLTs();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ISP Inventory Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --inv-sidebar-width: 260px;
            --inv-sidebar-bg: #1a1c2c;
            --inv-sidebar-hover: #252840;
            --inv-sidebar-active: #2d3154;
            --inv-accent: #e83e8c;
            --inv-accent-light: rgba(232, 62, 140, 0.15);
            --inv-text: #c8cad0;
            --inv-text-active: #ffffff;
            --inv-top-bar-height: 40px;
            --inv-border: rgba(255,255,255,0.08);
        }

        body {
            padding-top: var(--inv-top-bar-height);
            background: #f0f2f5;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }

        .module-top-bar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1100;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            padding: 0;
            height: var(--inv-top-bar-height);
        }

        .module-top-bar .nav-link {
            font-size: 0.82rem;
            letter-spacing: 0.3px;
            transition: all 0.2s;
            opacity: 0.85;
        }

        .module-top-bar .nav-link:hover {
            opacity: 1;
            background: rgba(255,255,255,0.1) !important;
        }

        .module-top-bar .nav-link.active {
            opacity: 1;
        }

        .inv-sidebar {
            position: fixed;
            top: var(--inv-top-bar-height);
            left: 0;
            bottom: 0;
            width: var(--inv-sidebar-width);
            background: var(--inv-sidebar-bg);
            z-index: 1050;
            overflow-y: auto;
            overflow-x: hidden;
            border-right: 1px solid var(--inv-border);
            transition: transform 0.3s ease;
        }

        .inv-sidebar::-webkit-scrollbar {
            width: 4px;
        }

        .inv-sidebar::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.15);
            border-radius: 4px;
        }

        .inv-sidebar .sidebar-header {
            padding: 20px 20px 12px;
            border-bottom: 1px solid var(--inv-border);
        }

        .inv-sidebar .sidebar-header h5 {
            color: var(--inv-text-active);
            font-size: 1.05rem;
            font-weight: 700;
            margin: 0;
        }

        .inv-sidebar .sidebar-header small {
            color: var(--inv-text);
            font-size: 0.72rem;
            opacity: 0.7;
        }

        .inv-sidebar .quick-nav {
            padding: 12px 12px 8px;
            border-bottom: 1px solid var(--inv-border);
        }

        .inv-sidebar .quick-nav a {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            font-size: 0.72rem;
            color: var(--inv-text);
            text-decoration: none;
            background: rgba(255,255,255,0.05);
            border-radius: 4px;
            margin-right: 6px;
            transition: all 0.2s;
        }

        .inv-sidebar .quick-nav a:hover {
            background: rgba(255,255,255,0.12);
            color: var(--inv-text-active);
        }

        .inv-sidebar .nav-section-label {
            padding: 16px 20px 6px;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: rgba(200,202,208,0.45);
            font-weight: 600;
        }

        .inv-sidebar .sidebar-nav {
            padding: 8px 12px;
            list-style: none;
            margin: 0;
        }

        .inv-sidebar .sidebar-nav li {
            margin-bottom: 2px;
        }

        .inv-sidebar .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 14px;
            color: var(--inv-text);
            text-decoration: none;
            border-radius: 8px;
            font-size: 0.85rem;
            transition: all 0.2s;
        }

        .inv-sidebar .sidebar-nav a:hover {
            background: var(--inv-sidebar-hover);
            color: var(--inv-text-active);
        }

        .inv-sidebar .sidebar-nav a.active {
            background: var(--inv-accent-light);
            color: var(--inv-accent);
            font-weight: 600;
        }

        .inv-sidebar .sidebar-nav a.active i {
            color: var(--inv-accent);
        }

        .inv-sidebar .sidebar-nav a i {
            font-size: 1.05rem;
            width: 22px;
            text-align: center;
            color: rgba(200,202,208,0.6);
        }

        .inv-main-content {
            margin-left: var(--inv-sidebar-width);
            padding: 24px 28px;
            min-height: calc(100vh - var(--inv-top-bar-height));
        }

        .inv-mobile-toggle {
            display: none;
            position: fixed;
            bottom: 24px;
            left: 24px;
            z-index: 1200;
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: var(--inv-accent);
            color: #fff;
            border: none;
            box-shadow: 0 4px 15px rgba(232,62,140,0.4);
            font-size: 1.3rem;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .inv-mobile-toggle:hover {
            transform: scale(1.1);
        }

        .inv-mobile-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1040;
        }

        @media (max-width: 991.98px) {
            .inv-sidebar {
                transform: translateX(-100%);
            }

            .inv-sidebar.show {
                transform: translateX(0);
            }

            .inv-main-content {
                margin-left: 0;
                padding: 16px;
            }

            .inv-mobile-toggle {
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .inv-mobile-overlay.show {
                display: block;
            }
        }

        @media (max-width: 575.98px) {
            .inv-main-content {
                padding: 12px 8px;
            }

            .module-top-bar .nav-link {
                font-size: 0.72rem;
                padding: 6px 10px !important;
            }
        }

        .card {
            border: none;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            border-radius: 10px;
        }

        .card-header {
            background: #fff;
            border-bottom: 1px solid #eee;
            font-weight: 600;
        }

        .table th {
            font-size: 0.82rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6c757d;
            border-bottom-width: 1px;
        }
    </style>
</head>
<body>

<div class="module-top-bar">
    <div class="container-fluid px-0">
        <div class="d-flex align-items-center ps-3">
            <ul class="nav nav-pills mb-0" style="gap: 2px;">
                <li class="nav-item"><a class="nav-link py-2 px-4 text-white" href="?page=dashboard" style="border-radius: 0; background: transparent;"><i class="bi bi-grid-3x3-gap me-1"></i>CRM</a></li>
                <li class="nav-item"><a class="nav-link py-2 px-4 text-white" href="?page=isp" style="border-radius: 0; background: transparent;"><i class="bi bi-broadcast me-1"></i>ISP</a></li>
                <li class="nav-item"><a class="nav-link py-2 px-4 text-white" href="?page=huawei-olt" style="border-radius: 0; background: transparent;"><i class="bi bi-router me-1"></i>OMS</a></li>
                <li class="nav-item"><a class="nav-link py-2 px-4 text-white active" href="?page=isp_inventory" style="border-radius: 0; background: #e83e8c; font-weight: 600;"><i class="bi bi-hdd-network me-1"></i>Inventory</a></li>
                <li class="nav-item"><a class="nav-link py-2 px-4 text-white" href="?page=call_center" style="border-radius: 0; background: transparent;"><i class="bi bi-telephone me-1"></i>Call Centre</a></li>
                <li class="nav-item"><a class="nav-link py-2 px-4 text-white" href="?page=finance" style="border-radius: 0; background: transparent;"><i class="bi bi-bank me-1"></i>Finance</a></li>
            </ul>
        </div>
    </div>
</div>

<div class="inv-mobile-overlay" id="invMobileOverlay" onclick="toggleInvSidebar()"></div>

<aside class="inv-sidebar" id="invSidebar">
    <div class="sidebar-header">
        <h5><i class="bi bi-hdd-network me-2"></i>Inventory</h5>
        <small>Network Asset Management</small>
    </div>

    <div class="quick-nav">
        <a href="?page=dashboard"><i class="bi bi-grid-3x3-gap"></i> CRM</a>
        <a href="?page=huawei-olt"><i class="bi bi-router"></i> OMS</a>
        <a href="?page=isp"><i class="bi bi-broadcast"></i> ISP</a>
    </div>

    <div class="nav-section-label">Inventory Modules</div>
    <ul class="sidebar-nav">
        <li><a href="?page=isp_inventory&tab=overview" class="<?= $tab === 'overview' ? 'active' : '' ?>"><i class="bi bi-speedometer2"></i> Overview</a></li>
        <li><a href="?page=isp_inventory&tab=ont" class="<?= $tab === 'ont' ? 'active' : '' ?>"><i class="bi bi-router"></i> ONT Inventory</a></li>
        <li><a href="?page=isp_inventory&tab=sites" class="<?= $tab === 'sites' ? 'active' : '' ?>"><i class="bi bi-geo-alt"></i> Sites</a></li>
        <li><a href="?page=isp_inventory&tab=core" class="<?= $tab === 'core' ? 'active' : '' ?>"><i class="bi bi-hdd-rack"></i> Core Network</a></li>
        <li><a href="?page=isp_inventory&tab=ipam" class="<?= $tab === 'ipam' ? 'active' : '' ?>"><i class="bi bi-globe"></i> IPAM</a></li>
        <li><a href="?page=isp_inventory&tab=warehouse" class="<?= $tab === 'warehouse' ? 'active' : '' ?>"><i class="bi bi-box-seam"></i> Warehouse</a></li>
        <li><a href="?page=isp_inventory&tab=assets" class="<?= $tab === 'assets' ? 'active' : '' ?>"><i class="bi bi-tools"></i> Field Assets</a></li>
        <li><a href="?page=isp_inventory&tab=maintenance" class="<?= $tab === 'maintenance' ? 'active' : '' ?>"><i class="bi bi-wrench"></i> Maintenance</a></li>
    </ul>
</aside>

<button class="inv-mobile-toggle" id="invMobileToggle" onclick="toggleInvSidebar()">
    <i class="bi bi-list"></i>
</button>

<div class="inv-main-content">

    <?php if ($successMessage): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?= htmlspecialchars($successMessage) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    <?php if ($errorMessage): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?= htmlspecialchars($errorMessage) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php
    // ==================== OVERVIEW TAB ====================
    if ($tab === 'overview'):
        $stats = $ispInv->getDashboardStats();
        $ontStats = $ispInv->getOntStats();
        $lowStock = $ispInv->getLowStockAlerts();
    ?>
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3 col-lg-2">
            <div class="card text-center border-primary h-100">
                <div class="card-body py-3">
                    <h3 class="text-primary mb-1"><?= $stats['total_onts'] ?></h3>
                    <small class="text-muted">Total ONTs</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <div class="card text-center border-success h-100">
                <div class="card-body py-3">
                    <h3 class="text-success mb-1"><?= $stats['onts_online'] ?></h3>
                    <small class="text-muted">ONTs Online</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <div class="card text-center border-danger h-100">
                <div class="card-body py-3">
                    <h3 class="text-danger mb-1"><?= $stats['onts_offline'] ?></h3>
                    <small class="text-muted">ONTs Offline</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <div class="card text-center border-warning h-100">
                <div class="card-body py-3">
                    <h3 class="text-warning mb-1"><?= $stats['onts_low_signal'] ?></h3>
                    <small class="text-muted">Low Signal</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <div class="card text-center border-info h-100">
                <div class="card-body py-3">
                    <h3 class="text-info mb-1"><?= $stats['total_sites'] ?></h3>
                    <small class="text-muted">Sites</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <div class="card text-center <?= $stats['low_stock_count'] > 0 ? 'border-danger' : 'border-success' ?> h-100">
                <div class="card-body py-3">
                    <h3 class="<?= $stats['low_stock_count'] > 0 ? 'text-danger' : 'text-success' ?> mb-1"><?= $stats['low_stock_count'] ?></h3>
                    <small class="text-muted">Low Stock Alerts</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><i class="bi bi-router"></i> ONTs by Zone</div>
                <div class="card-body" style="max-height:300px;overflow-y:auto;">
                    <?php if (empty($ontStats['zones'])): ?>
                        <p class="text-muted mb-0">No zone data available.</p>
                    <?php else: ?>
                    <table class="table table-sm table-striped mb-0">
                        <thead><tr><th>Zone</th><th>Total</th><th>Online</th><th>Rate</th></tr></thead>
                        <tbody>
                        <?php foreach ($ontStats['zones'] as $z): ?>
                            <tr>
                                <td><?= htmlspecialchars($z['name'] ?? 'Unassigned') ?></td>
                                <td><strong><?= $z['ont_count'] ?></strong></td>
                                <td><span class="text-success"><?= $z['online_count'] ?></span></td>
                                <td>
                                    <?php $rate = $z['ont_count'] > 0 ? round($z['online_count']/$z['ont_count']*100) : 0; ?>
                                    <div class="progress" style="height:16px;min-width:80px;">
                                        <div class="progress-bar bg-<?= $rate >= 80 ? 'success' : ($rate >= 50 ? 'warning' : 'danger') ?>" style="width:<?= $rate ?>%"><?= $rate ?>%</div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><i class="bi bi-hdd-rack"></i> ONTs by OLT</div>
                <div class="card-body" style="max-height:300px;overflow-y:auto;">
                    <?php if (empty($ontStats['olts'])): ?>
                        <p class="text-muted mb-0">No OLT data available.</p>
                    <?php else: ?>
                    <table class="table table-sm table-striped mb-0">
                        <thead><tr><th>OLT</th><th>Total</th><th>Online</th><th>Rate</th></tr></thead>
                        <tbody>
                        <?php foreach ($ontStats['olts'] as $ol): ?>
                            <tr>
                                <td><?= htmlspecialchars($ol['name'] ?? 'Unknown') ?></td>
                                <td><strong><?= $ol['ont_count'] ?></strong></td>
                                <td><span class="text-success"><?= $ol['online_count'] ?></span></td>
                                <td>
                                    <?php $rate = $ol['ont_count'] > 0 ? round($ol['online_count']/$ol['ont_count']*100) : 0; ?>
                                    <div class="progress" style="height:16px;min-width:80px;">
                                        <div class="progress-bar bg-<?= $rate >= 80 ? 'success' : ($rate >= 50 ? 'warning' : 'danger') ?>" style="width:<?= $rate ?>%"><?= $rate ?>%</div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header"><i class="bi bi-hdd-rack"></i> Infrastructure</div>
                <div class="card-body">
                    <p class="mb-1">Core Equipment: <strong><?= $stats['total_core_equipment'] ?></strong></p>
                    <p class="mb-1">Field Assets: <strong><?= $stats['field_assets_total'] ?></strong></p>
                    <p class="mb-0">Pending Maintenance: <strong class="<?= $stats['pending_maintenance'] > 0 ? 'text-warning' : '' ?>"><?= $stats['pending_maintenance'] ?></strong></p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header"><i class="bi bi-globe"></i> IP Allocation</div>
                <div class="card-body">
                    <p class="mb-1">Total IPs: <strong><?= $stats['total_ips'] ?></strong></p>
                    <p class="mb-1">Assigned: <strong><?= $stats['ips_assigned'] ?></strong></p>
                    <?php if ($stats['total_ips'] > 0): ?>
                    <div class="progress mt-2" style="height:20px;">
                        <div class="progress-bar bg-info" style="width:<?= round($stats['ips_assigned']/$stats['total_ips']*100) ?>%"><?= round($stats['ips_assigned']/$stats['total_ips']*100) ?>% Used</div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header"><i class="bi bi-exclamation-triangle"></i> Low Stock Alerts</div>
                <div class="card-body" style="max-height:250px;overflow-y:auto;">
                    <?php if (empty($lowStock)): ?>
                        <p class="text-success mb-0">All stock levels are healthy.</p>
                    <?php else: ?>
                        <table class="table table-sm table-striped mb-0">
                            <thead><tr><th>Item</th><th>Qty</th><th>Min</th></tr></thead>
                            <tbody>
                            <?php foreach ($lowStock as $ls): ?>
                                <tr class="table-warning">
                                    <td><?= htmlspecialchars($ls['item_name']) ?></td>
                                    <td><strong class="text-danger"><?= $ls['quantity'] ?></strong></td>
                                    <td><?= $ls['min_threshold'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php
    // ==================== SITES TAB ====================
    elseif ($tab === 'sites'):
        if ($action === 'form'):
            $item = $id ? $ispInv->getSite($id) : null;
    ?>
    <div class="card">
        <div class="card-header"><?= $item ? 'Edit' : 'Add' ?> Network Site</div>
        <div class="card-body">
            <form method="POST" action="?page=isp_inventory&tab=sites&action=save<?= $item ? '&id='.$item['id'] : '' ?>">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Site Name *</label>
                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($item['name'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Site Type</label>
                        <select name="site_type" class="form-select">
                            <?php foreach (['pop'=>'POP','data_center'=>'Data Center','tower'=>'Tower','cabinet'=>'Cabinet','manhole'=>'Manhole','other'=>'Other'] as $k=>$v): ?>
                            <option value="<?= $k ?>" <?= ($item['site_type'] ?? 'pop') === $k ? 'selected' : '' ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="active" <?= ($item['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="maintenance" <?= ($item['status'] ?? '') === 'maintenance' ? 'selected' : '' ?>>Maintenance</option>
                            <option value="inactive" <?= ($item['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Address</label>
                        <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($item['address'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">GPS Latitude</label>
                        <input type="text" name="gps_lat" class="form-control" value="<?= $item['gps_lat'] ?? '' ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">GPS Longitude</label>
                        <input type="text" name="gps_lng" class="form-control" value="<?= $item['gps_lng'] ?? '' ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Contact Person</label>
                        <input type="text" name="contact_person" class="form-control" value="<?= htmlspecialchars($item['contact_person'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Contact Phone</label>
                        <input type="text" name="contact_phone" class="form-control" value="<?= htmlspecialchars($item['contact_phone'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Power Source</label>
                        <input type="text" name="power_source" class="form-control" value="<?= htmlspecialchars($item['power_source'] ?? '') ?>" placeholder="e.g., KPLC + Generator">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">UPS Capacity</label>
                        <input type="text" name="ups_capacity" class="form-control" value="<?= htmlspecialchars($item['ups_capacity'] ?? '') ?>" placeholder="e.g., 3KVA">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">UPS Battery Health</label>
                        <select name="ups_battery_health" class="form-select">
                            <option value="">-- Select --</option>
                            <?php foreach (['good'=>'Good','fair'=>'Fair','replace_soon'=>'Replace Soon','bad'=>'Bad'] as $k=>$v): ?>
                            <option value="<?= $k ?>" <?= ($item['ups_battery_health'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"><?= htmlspecialchars($item['notes'] ?? '') ?></textarea>
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save</button>
                    <a href="?page=isp_inventory&tab=sites" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    <?php else:
        $siteList = $ispInv->getSites(['search' => $search]);
    ?>
    <div class="d-flex justify-content-between mb-3">
        <form class="d-flex gap-2" method="GET">
            <input type="hidden" name="page" value="isp_inventory"><input type="hidden" name="tab" value="sites">
            <input type="text" name="search" class="form-control" placeholder="Search sites..." value="<?= htmlspecialchars($search) ?>" style="width:250px;">
            <button class="btn btn-outline-primary btn-sm"><i class="bi bi-search"></i></button>
        </form>
        <a href="?page=isp_inventory&tab=sites&action=form" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add Site</a>
    </div>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead><tr><th>Name</th><th>Type</th><th>Address</th><th>Power</th><th>UPS</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if (empty($siteList)): ?>
                <tr><td colspan="7" class="text-center text-muted">No sites found.</td></tr>
            <?php else: foreach ($siteList as $s): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>
                    <td><span class="badge bg-secondary"><?= htmlspecialchars($s['site_type']) ?></span></td>
                    <td><?= htmlspecialchars($s['address'] ?? '') ?></td>
                    <td><?= htmlspecialchars($s['power_source'] ?? '') ?></td>
                    <td><?= htmlspecialchars($s['ups_capacity'] ?? '') ?> <?php if ($s['ups_battery_health']): ?><span class="badge bg-<?= $s['ups_battery_health']==='good'?'success':($s['ups_battery_health']==='bad'?'danger':'warning') ?>"><?= $s['ups_battery_health'] ?></span><?php endif; ?></td>
                    <td><span class="badge bg-<?= $s['status']==='active'?'success':($s['status']==='maintenance'?'warning':'secondary') ?>"><?= $s['status'] ?></span></td>
                    <td>
                        <a href="?page=isp_inventory&tab=sites&action=form&id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                        <form method="POST" action="?page=isp_inventory&tab=sites&action=delete" class="d-inline" onsubmit="return confirm('Delete this site?')">
                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php
    // ==================== CORE NETWORK TAB ====================
    elseif ($tab === 'core'):
        if ($action === 'form'):
            $item = $id ? $ispInv->getCoreEquipmentItem($id) : null;
            $racks = $ispInv->getRacks();
    ?>
    <div class="card">
        <div class="card-header"><?= $item ? 'Edit' : 'Add' ?> Core Equipment</div>
        <div class="card-body">
            <form method="POST" action="?page=isp_inventory&tab=core&action=save<?= $item ? '&id='.$item['id'] : '' ?>">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Equipment Type *</label>
                        <select name="equipment_type" class="form-select" required>
                            <?php foreach (['router'=>'Router','switch'=>'Switch','olt'=>'OLT','odf'=>'ODF','ups'=>'UPS','power_supply'=>'Power Supply','media_converter'=>'Media Converter','firewall'=>'Firewall','server'=>'Server','other'=>'Other'] as $k=>$v): ?>
                            <option value="<?= $k ?>" <?= ($item['equipment_type'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Name *</label>
                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($item['name'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Link to OLT</label>
                        <select name="olt_id" class="form-select">
                            <option value="">-- None --</option>
                            <?php foreach ($olts as $o): ?>
                            <option value="<?= $o['id'] ?>" <?= ($item['olt_id'] ?? '') == $o['id'] ? 'selected' : '' ?>><?= htmlspecialchars($o['name']) ?> (<?= $o['ip_address'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Site</label>
                        <select name="site_id" class="form-select">
                            <option value="">-- Select --</option>
                            <?php foreach ($sites as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= ($item['site_id'] ?? '') == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Rack</label>
                        <select name="rack_id" class="form-select">
                            <option value="">-- Select --</option>
                            <?php foreach ($racks as $r): ?>
                            <option value="<?= $r['id'] ?>" <?= ($item['rack_id'] ?? '') == $r['id'] ? 'selected' : '' ?>><?= htmlspecialchars($r['name']) ?> (<?= htmlspecialchars($r['site_name'] ?? '') ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Rack Position</label>
                        <input type="text" name="rack_position" class="form-control" value="<?= htmlspecialchars($item['rack_position'] ?? '') ?>" placeholder="e.g., U1-U4">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Manufacturer</label>
                        <input type="text" name="manufacturer" class="form-control" value="<?= htmlspecialchars($item['manufacturer'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Model</label>
                        <input type="text" name="model" class="form-control" value="<?= htmlspecialchars($item['model'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Serial Number</label>
                        <input type="text" name="serial_number" class="form-control" value="<?= htmlspecialchars($item['serial_number'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">MAC Address</label>
                        <input type="text" name="mac_address" class="form-control" value="<?= htmlspecialchars($item['mac_address'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Management IP</label>
                        <input type="text" name="management_ip" class="form-control" value="<?= htmlspecialchars($item['management_ip'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">OS / Firmware</label>
                        <input type="text" name="firmware_version" class="form-control" value="<?= htmlspecialchars($item['firmware_version'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Purchase Date</label>
                        <input type="date" name="purchase_date" class="form-control" value="<?= $item['purchase_date'] ?? '' ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Warranty Expiry</label>
                        <input type="date" name="warranty_expiry" class="form-control" value="<?= $item['warranty_expiry'] ?? '' ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Supplier</label>
                        <input type="text" name="supplier" class="form-control" value="<?= htmlspecialchars($item['supplier'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Purchase Price</label>
                        <input type="number" step="0.01" name="purchase_price" class="form-control" value="<?= $item['purchase_price'] ?? '' ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <?php foreach (['active'=>'Active','maintenance'=>'Maintenance','faulty'=>'Faulty','retired'=>'Retired','spare'=>'Spare'] as $k=>$v): ?>
                            <option value="<?= $k ?>" <?= ($item['status'] ?? 'active') === $k ? 'selected' : '' ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"><?= htmlspecialchars($item['notes'] ?? '') ?></textarea>
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save</button>
                    <a href="?page=isp_inventory&tab=core" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    <?php else:
        $eqType = $_GET['equipment_type'] ?? '';
        $eqStatus = $_GET['eq_status'] ?? '';
        $coreList = $ispInv->getCoreEquipment(['equipment_type'=>$eqType, 'status'=>$eqStatus, 'search'=>$search]);
    ?>
    <div class="d-flex justify-content-between mb-3 flex-wrap gap-2">
        <form class="d-flex gap-2 flex-wrap" method="GET">
            <input type="hidden" name="page" value="isp_inventory"><input type="hidden" name="tab" value="core">
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Search..." value="<?= htmlspecialchars($search) ?>" style="width:200px;">
            <select name="equipment_type" class="form-select form-select-sm" style="width:150px;">
                <option value="">All Types</option>
                <?php foreach (['router'=>'Router','switch'=>'Switch','olt'=>'OLT','odf'=>'ODF','ups'=>'UPS','firewall'=>'Firewall','server'=>'Server'] as $k=>$v): ?>
                <option value="<?= $k ?>" <?= $eqType === $k ? 'selected' : '' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
            <select name="eq_status" class="form-select form-select-sm" style="width:130px;">
                <option value="">All Status</option>
                <?php foreach (['active','maintenance','faulty','retired','spare'] as $st): ?>
                <option value="<?= $st ?>" <?= $eqStatus === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-outline-primary btn-sm"><i class="bi bi-search"></i></button>
        </form>
        <a href="?page=isp_inventory&tab=core&action=form" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Add Equipment</a>
    </div>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead><tr><th>Name</th><th>Type</th><th>Site</th><th>Model</th><th>Serial</th><th>IP</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if (empty($coreList)): ?>
                <tr><td colspan="8" class="text-center text-muted">No equipment found.</td></tr>
            <?php else: foreach ($coreList as $eq): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($eq['name']) ?></strong><?php if ($eq['olt_name']): ?><br><small class="text-muted">OLT: <?= htmlspecialchars($eq['olt_name']) ?></small><?php endif; ?></td>
                    <td><span class="badge bg-info"><?= htmlspecialchars($eq['equipment_type']) ?></span></td>
                    <td><?= htmlspecialchars($eq['site_name'] ?? '') ?></td>
                    <td><?= htmlspecialchars(($eq['manufacturer'] ?? '') . ' ' . ($eq['model'] ?? '')) ?></td>
                    <td><small><?= htmlspecialchars($eq['serial_number'] ?? '') ?></small></td>
                    <td><code><?= htmlspecialchars($eq['management_ip'] ?? '') ?></code></td>
                    <td><span class="badge bg-<?= $eq['status']==='active'?'success':($eq['status']==='faulty'?'danger':($eq['status']==='maintenance'?'warning':'secondary')) ?>"><?= $eq['status'] ?></span></td>
                    <td>
                        <a href="?page=isp_inventory&tab=core&action=form&id=<?= $eq['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                        <form method="POST" action="?page=isp_inventory&tab=core&action=delete" class="d-inline" onsubmit="return confirm('Delete?')">
                            <input type="hidden" name="id" value="<?= $eq['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php
    // ==================== ONT INVENTORY TAB ====================
    elseif ($tab === 'ont'):
        $ontStatus = $_GET['ont_status'] ?? '';
        $ontOlt = $_GET['ont_olt'] ?? '';
        $ontZone = $_GET['ont_zone'] ?? '';
        $ontFilters = ['status'=>$ontStatus, 'olt_id'=>$ontOlt, 'zone_id'=>$ontZone, 'search'=>$search];
        $ontList = $ispInv->getOntInventory($ontFilters);
        $olts = $ispInv->getOLTs();
        $zones = $ispInv->getZones();
        $ontOverview = $ispInv->getOntStats();
    ?>

    <div class="row g-3 mb-3">
        <div class="col-auto"><span class="badge bg-primary fs-6"><?= $ontOverview['total_onts'] ?> Total</span></div>
        <div class="col-auto"><span class="badge bg-success fs-6"><?= $ontOverview['online_onts'] ?> Online</span></div>
        <div class="col-auto"><span class="badge bg-danger fs-6"><?= $ontOverview['offline_onts'] ?> Offline</span></div>
        <div class="col-auto"><span class="badge bg-warning fs-6"><?= $ontOverview['low_signal'] ?> Low Signal</span></div>
    </div>

    <div class="card mb-3">
        <div class="card-body py-2">
            <form class="row g-2 align-items-end" method="GET">
                <input type="hidden" name="page" value="isp_inventory">
                <input type="hidden" name="tab" value="ont">
                <div class="col-md-3">
                    <label class="form-label form-label-sm mb-1">Search</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Serial, name, customer, phone, PPPoE..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label form-label-sm mb-1">Status</label>
                    <select name="ont_status" class="form-select form-select-sm">
                        <option value="">All Status</option>
                        <option value="online" <?= $ontStatus === 'online' ? 'selected' : '' ?>>Online</option>
                        <option value="offline" <?= $ontStatus === 'offline' ? 'selected' : '' ?>>Offline</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label form-label-sm mb-1">OLT</label>
                    <select name="ont_olt" class="form-select form-select-sm">
                        <option value="">All OLTs</option>
                        <?php foreach ($olts as $o): ?>
                        <option value="<?= $o['id'] ?>" <?= $ontOlt == $o['id'] ? 'selected' : '' ?>><?= htmlspecialchars($o['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label form-label-sm mb-1">Zone</label>
                    <select name="ont_zone" class="form-select form-select-sm">
                        <option value="">All Zones</option>
                        <?php foreach ($zones as $z): ?>
                        <option value="<?= $z['id'] ?>" <?= $ontZone == $z['id'] ? 'selected' : '' ?>><?= htmlspecialchars($z['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-1">
                    <button class="btn btn-primary btn-sm w-100"><i class="bi bi-search"></i></button>
                </div>
                <?php if ($search || $ontStatus || $ontOlt || $ontZone): ?>
                <div class="col-md-1">
                    <a href="?page=isp_inventory&tab=ont" class="btn btn-outline-secondary btn-sm w-100">Clear</a>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="mb-2 text-muted small">
        <i class="bi bi-info-circle"></i> Showing <?= count($ontList) ?> provisioned ONTs from OMS.
        ONTs are automatically tracked here once provisioned on the OLT.
    </div>

    <div class="table-responsive">
        <table class="table table-striped table-hover table-sm">
            <thead class="table-dark">
                <tr>
                    <th>Serial Number</th>
                    <th>Name</th>
                    <th>Customer</th>
                    <th>Phone</th>
                    <th>OLT / Port</th>
                    <th>Zone</th>
                    <th>Status</th>
                    <th>Rx Power</th>
                    <th>PPPoE</th>
                    <th>Uptime</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($ontList)): ?>
                <tr><td colspan="10" class="text-center text-muted py-4">No provisioned ONTs found matching your filters.</td></tr>
            <?php else: foreach ($ontList as $ont): ?>
                <tr>
                    <td><strong><code><?= htmlspecialchars($ont['sn']) ?></code></strong>
                        <?php if ($ont['mac_address']): ?><br><small class="text-muted"><?= htmlspecialchars($ont['mac_address']) ?></small><?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($ont['name'] ?? '') ?>
                        <?php if ($ont['discovered_eqid']): ?><br><small class="text-muted"><?= htmlspecialchars($ont['discovered_eqid']) ?></small><?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($ont['customer_name'] ?? '') ?>
                        <?php if ($ont['address']): ?><br><small class="text-muted"><?= htmlspecialchars(mb_strimwidth($ont['address'], 0, 40, '...')) ?></small><?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($ont['phone'] ?? '') ?></td>
                    <td>
                        <small><?= htmlspecialchars($ont['olt_name'] ?? '') ?></small>
                        <br><code><?= $ont['frame'] ?>/<?= $ont['slot'] ?>/<?= $ont['port'] ?>/<?= $ont['onu_id'] ?></code>
                    </td>
                    <td>
                        <?= htmlspecialchars($ont['zone_name'] ?? $ont['zone'] ?? '') ?>
                        <?php if ($ont['subzone_name'] ?? $ont['area'] ?? ''): ?>
                        <br><small class="text-muted"><?= htmlspecialchars($ont['subzone_name'] ?? $ont['area'] ?? '') ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($ont['status'] === 'online'): ?>
                            <span class="badge bg-success">Online</span>
                        <?php elseif ($ont['status'] === 'offline'): ?>
                            <span class="badge bg-danger">Offline</span>
                        <?php else: ?>
                            <span class="badge bg-secondary"><?= htmlspecialchars($ont['status'] ?? 'unknown') ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($ont['rx_power'] !== null): ?>
                            <span class="<?= $ont['rx_power'] < -27 ? 'text-danger fw-bold' : ($ont['rx_power'] < -25 ? 'text-warning' : 'text-success') ?>">
                                <?= number_format($ont['rx_power'], 2) ?> dBm
                            </span>
                        <?php else: ?>
                            <span class="text-muted">--</span>
                        <?php endif; ?>
                    </td>
                    <td><small><?= htmlspecialchars($ont['pppoe_username'] ?? '') ?></small></td>
                    <td><small><?= htmlspecialchars($ont['uptime'] ?? '') ?></small></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?php
    // ==================== IPAM TAB ====================
    elseif ($tab === 'ipam'):
        $ipamSub = $_GET['sub'] ?? 'ips';
    ?>
    <ul class="nav nav-pills mb-3">
        <li class="nav-item"><a class="nav-link <?= $ipamSub==='ips'?'active':'' ?>" href="?page=isp_inventory&tab=ipam&sub=ips">IP Addresses</a></li>
        <li class="nav-item"><a class="nav-link <?= $ipamSub==='vlans'?'active':'' ?>" href="?page=isp_inventory&tab=ipam&sub=vlans">VLANs</a></li>
    </ul>

    <?php if ($ipamSub === 'ips'):
        if ($action === 'form'):
            $item = null;
            if ($id) { foreach ($ispInv->getIPAddresses() as $ip) { if ($ip['id'] == $id) { $item = $ip; break; } } }
    ?>
    <div class="card">
        <div class="card-header"><?= $item ? 'Edit' : 'Add' ?> IP Address</div>
        <div class="card-body">
            <form method="POST" action="?page=isp_inventory&tab=ipam&sub=ips&action=save<?= $item ? '&id='.$item['id'] : '' ?>">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">IP Type</label>
                        <select name="ip_type" class="form-select">
                            <?php foreach (['public'=>'Public','private'=>'Private','cgnat'=>'CGNAT','loopback'=>'Loopback','management'=>'Management'] as $k=>$v): ?>
                            <option value="<?= $k ?>" <?= ($item['ip_type'] ?? 'public') === $k ? 'selected' : '' ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3"><label class="form-label">IP Address *</label><input type="text" name="ip_address" class="form-control" value="<?= htmlspecialchars($item['ip_address'] ?? '') ?>" required></div>
                    <div class="col-md-3"><label class="form-label">Subnet Mask</label><input type="text" name="subnet_mask" class="form-control" value="<?= htmlspecialchars($item['subnet_mask'] ?? '') ?>"></div>
                    <div class="col-md-3"><label class="form-label">CIDR</label><input type="number" name="cidr" class="form-control" value="<?= $item['cidr'] ?? '' ?>" min="0" max="128"></div>
                    <div class="col-md-3"><label class="form-label">Gateway</label><input type="text" name="gateway" class="form-control" value="<?= htmlspecialchars($item['gateway'] ?? '') ?>"></div>
                    <div class="col-md-3"><label class="form-label">Block Name</label><input type="text" name="block_name" class="form-control" value="<?= htmlspecialchars($item['block_name'] ?? '') ?>"></div>
                    <div class="col-md-3"><label class="form-label">Assigned To</label><input type="text" name="assigned_to" class="form-control" value="<?= htmlspecialchars($item['assigned_to'] ?? '') ?>"></div>
                    <div class="col-md-3">
                        <label class="form-label">Assignment Type</label>
                        <select name="assignment_type" class="form-select">
                            <option value="">-- Select --</option>
                            <?php foreach (['customer'=>'Customer','infrastructure'=>'Infrastructure','server'=>'Server','management'=>'Management'] as $k=>$v): ?>
                            <option value="<?= $k ?>" <?= ($item['assignment_type'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3"><label class="form-label">Customer ID</label><input type="number" name="customer_id" class="form-control" value="<?= $item['customer_id'] ?? '' ?>"></div>
                    <div class="col-md-3"><label class="form-label">Reverse DNS</label><input type="text" name="reverse_dns" class="form-control" value="<?= htmlspecialchars($item['reverse_dns'] ?? '') ?>"></div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <?php foreach (['available'=>'Available','assigned'=>'Assigned','reserved'=>'Reserved','blocked'=>'Blocked'] as $k=>$v): ?>
                            <option value="<?= $k ?>" <?= ($item['status'] ?? 'available') === $k ? 'selected' : '' ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"><?= htmlspecialchars($item['notes'] ?? '') ?></textarea></div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save</button>
                    <a href="?page=isp_inventory&tab=ipam&sub=ips" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    <?php else:
        $ipType = $_GET['ip_type'] ?? '';
        $ipStatus = $_GET['ip_status'] ?? '';
        $ipList = $ispInv->getIPAddresses(['ip_type'=>$ipType, 'status'=>$ipStatus, 'search'=>$search]);
    ?>
    <div class="d-flex justify-content-between mb-3 flex-wrap gap-2">
        <form class="d-flex gap-2 flex-wrap" method="GET">
            <input type="hidden" name="page" value="isp_inventory"><input type="hidden" name="tab" value="ipam"><input type="hidden" name="sub" value="ips">
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Search IP/assignment..." value="<?= htmlspecialchars($search) ?>" style="width:200px;">
            <select name="ip_type" class="form-select form-select-sm" style="width:130px;">
                <option value="">All Types</option>
                <?php foreach (['public','private','cgnat','management'] as $t): ?>
                <option value="<?= $t ?>" <?= $ipType === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="ip_status" class="form-select form-select-sm" style="width:130px;">
                <option value="">All Status</option>
                <?php foreach (['available','assigned','reserved','blocked'] as $st): ?>
                <option value="<?= $st ?>" <?= $ipStatus === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-outline-primary btn-sm"><i class="bi bi-search"></i></button>
        </form>
        <a href="?page=isp_inventory&tab=ipam&sub=ips&action=form" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Add IP</a>
    </div>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead><tr><th>IP Address</th><th>Type</th><th>CIDR</th><th>Block</th><th>Assigned To</th><th>Use</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if (empty($ipList)): ?>
                <tr><td colspan="8" class="text-center text-muted">No IPs found.</td></tr>
            <?php else: foreach ($ipList as $ip): ?>
                <tr>
                    <td><code><?= htmlspecialchars($ip['ip_address']) ?></code></td>
                    <td><span class="badge bg-secondary"><?= $ip['ip_type'] ?></span></td>
                    <td>/<?= $ip['cidr'] ?? '' ?></td>
                    <td><?= htmlspecialchars($ip['block_name'] ?? '') ?></td>
                    <td><?= htmlspecialchars($ip['assigned_to'] ?? '') ?></td>
                    <td><?= htmlspecialchars($ip['assignment_type'] ?? '') ?></td>
                    <td><span class="badge bg-<?= $ip['status']==='available'?'success':($ip['status']==='assigned'?'primary':($ip['status']==='blocked'?'danger':'warning')) ?>"><?= $ip['status'] ?></span></td>
                    <td>
                        <a href="?page=isp_inventory&tab=ipam&sub=ips&action=form&id=<?= $ip['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                        <form method="POST" action="?page=isp_inventory&tab=ipam&sub=ips&action=delete" class="d-inline" onsubmit="return confirm('Delete?')">
                            <input type="hidden" name="id" value="<?= $ip['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php elseif ($ipamSub === 'vlans'):
        if ($action === 'form'):
            $item = null;
            if ($id) { foreach ($ispInv->getVLANs() as $v) { if ($v['id'] == $id) { $item = $v; break; } } }
            $coreEquip = $ispInv->getCoreEquipment();
    ?>
    <div class="card">
        <div class="card-header"><?= $item ? 'Edit' : 'Add' ?> VLAN</div>
        <div class="card-body">
            <form method="POST" action="?page=isp_inventory&tab=ipam&sub=vlans&action=save<?= $item ? '&id='.$item['id'] : '' ?>">
                <div class="row g-3">
                    <div class="col-md-2"><label class="form-label">VLAN ID *</label><input type="number" name="vlan_id" class="form-control" value="<?= $item['vlan_id'] ?? '' ?>" required></div>
                    <div class="col-md-4"><label class="form-label">Name *</label><input type="text" name="name" class="form-control" value="<?= htmlspecialchars($item['name'] ?? '') ?>" required></div>
                    <div class="col-md-3"><label class="form-label">Purpose</label><input type="text" name="purpose" class="form-control" value="<?= htmlspecialchars($item['purpose'] ?? '') ?>"></div>
                    <div class="col-md-3"><label class="form-label">Subnet</label><input type="text" name="subnet" class="form-control" value="<?= htmlspecialchars($item['subnet'] ?? '') ?>" placeholder="10.0.0.0/24"></div>
                    <div class="col-md-3"><label class="form-label">Gateway</label><input type="text" name="gateway" class="form-control" value="<?= htmlspecialchars($item['gateway'] ?? '') ?>"></div>
                    <div class="col-md-3">
                        <label class="form-label">Site</label>
                        <select name="site_id" class="form-select"><option value="">-- Select --</option>
                            <?php foreach ($sites as $s): ?><option value="<?= $s['id'] ?>" <?= ($item['site_id'] ?? '') == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Equipment</label>
                        <select name="equipment_id" class="form-select"><option value="">-- Select --</option>
                            <?php foreach ($coreEquip as $ce): ?><option value="<?= $ce['id'] ?>" <?= ($item['equipment_id'] ?? '') == $ce['id'] ? 'selected' : '' ?>><?= htmlspecialchars($ce['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="active" <?= ($item['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= ($item['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"><?= htmlspecialchars($item['notes'] ?? '') ?></textarea></div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save</button>
                    <a href="?page=isp_inventory&tab=ipam&sub=vlans" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    <?php else:
        $vlanList = $ispInv->getVLANs(['search'=>$search]);
    ?>
    <div class="d-flex justify-content-between mb-3">
        <form class="d-flex gap-2" method="GET">
            <input type="hidden" name="page" value="isp_inventory"><input type="hidden" name="tab" value="ipam"><input type="hidden" name="sub" value="vlans">
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Search..." value="<?= htmlspecialchars($search) ?>" style="width:250px;">
            <button class="btn btn-outline-primary btn-sm"><i class="bi bi-search"></i></button>
        </form>
        <a href="?page=isp_inventory&tab=ipam&sub=vlans&action=form" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Add VLAN</a>
    </div>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead><tr><th>VLAN ID</th><th>Name</th><th>Purpose</th><th>Subnet</th><th>Gateway</th><th>Site</th><th>Equipment</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if (empty($vlanList)): ?>
                <tr><td colspan="9" class="text-center text-muted">No VLANs found.</td></tr>
            <?php else: foreach ($vlanList as $vl): ?>
                <tr>
                    <td><strong><?= $vl['vlan_id'] ?></strong></td>
                    <td><?= htmlspecialchars($vl['name']) ?></td>
                    <td><?= htmlspecialchars($vl['purpose'] ?? '') ?></td>
                    <td><code><?= htmlspecialchars($vl['subnet'] ?? '') ?></code></td>
                    <td><code><?= htmlspecialchars($vl['gateway'] ?? '') ?></code></td>
                    <td><?= htmlspecialchars($vl['site_name'] ?? '') ?></td>
                    <td><?= htmlspecialchars($vl['equipment_name'] ?? '') ?></td>
                    <td><span class="badge bg-<?= $vl['status']==='active'?'success':'secondary' ?>"><?= $vl['status'] ?></span></td>
                    <td>
                        <a href="?page=isp_inventory&tab=ipam&sub=vlans&action=form&id=<?= $vl['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                        <form method="POST" action="?page=isp_inventory&tab=ipam&sub=vlans&action=delete" class="d-inline" onsubmit="return confirm('Delete?')">
                            <input type="hidden" name="id" value="<?= $vl['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <?php
    // ==================== WAREHOUSE TAB ====================
    elseif ($tab === 'warehouse'):
        if ($action === 'form'):
            $item = $id ? $ispInv->getWarehouseStockItem($id) : null;
    ?>
    <div class="card">
        <div class="card-header"><?= $item ? 'Edit' : 'Add' ?> Warehouse Stock Item</div>
        <div class="card-body">
            <form method="POST" action="?page=isp_inventory&tab=warehouse&action=save<?= $item ? '&id='.$item['id'] : '' ?>">
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label">Item Name *</label><input type="text" name="item_name" class="form-control" value="<?= htmlspecialchars($item['item_name'] ?? '') ?>" required></div>
                    <div class="col-md-4">
                        <label class="form-label">Category *</label>
                        <select name="category" class="form-select" required>
                            <?php foreach (['ONU/ONT'=>'ONU/ONT','SFP Module'=>'SFP Module','Fiber Cable'=>'Fiber Cable','Patch Cord'=>'Patch Cord','Splitter'=>'Splitter','Distribution Box'=>'Distribution Box','Splice Closure'=>'Splice Closure','Drop Cable'=>'Drop Cable','Connector'=>'Connector','Tools'=>'Tools','UPS/Power'=>'UPS/Power','Switch/Router'=>'Switch/Router','Accessories'=>'Accessories','Other'=>'Other'] as $k=>$v): ?>
                            <option value="<?= $k ?>" <?= ($item['category'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Warehouse / Site</label>
                        <select name="site_id" class="form-select"><option value="">-- Main Warehouse --</option>
                            <?php foreach ($sites as $s): ?><option value="<?= $s['id'] ?>" <?= ($item['site_id'] ?? '') == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2"><label class="form-label">Unit</label><input type="text" name="unit" class="form-control" value="<?= htmlspecialchars($item['unit'] ?? 'pcs') ?>"></div>
                    <div class="col-md-2"><label class="form-label">Quantity</label><input type="number" step="0.01" name="quantity" class="form-control" value="<?= $item['quantity'] ?? 0 ?>"></div>
                    <div class="col-md-2"><label class="form-label">Min Threshold</label><input type="number" step="0.01" name="min_threshold" class="form-control" value="<?= $item['min_threshold'] ?? 0 ?>"></div>
                    <div class="col-md-3"><label class="form-label">Unit Cost</label><input type="number" step="0.01" name="unit_cost" class="form-control" value="<?= $item['unit_cost'] ?? '' ?>"></div>
                    <div class="col-md-3"><label class="form-label">Supplier</label><input type="text" name="supplier" class="form-control" value="<?= htmlspecialchars($item['supplier'] ?? '') ?>"></div>
                    <div class="col-md-3"><label class="form-label">Supplier Contact</label><input type="text" name="supplier_contact" class="form-control" value="<?= htmlspecialchars($item['supplier_contact'] ?? '') ?>"></div>
                    <div class="col-md-3"><label class="form-label">Storage Location</label><input type="text" name="storage_location" class="form-control" value="<?= htmlspecialchars($item['storage_location'] ?? '') ?>"></div>
                    <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"><?= htmlspecialchars($item['notes'] ?? '') ?></textarea></div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save</button>
                    <a href="?page=isp_inventory&tab=warehouse" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    <?php elseif ($action === 'movement' && $id):
        $stockItem = $ispInv->getWarehouseStockItem($id);
        $movements = $ispInv->getStockMovements($id);
    ?>
    <div class="card mb-4">
        <div class="card-header">
            <i class="bi bi-arrow-left-right"></i> Stock Movement: <strong><?= htmlspecialchars($stockItem['item_name'] ?? '') ?></strong>
            <span class="badge bg-info float-end">Current Qty: <?= $stockItem['quantity'] ?? 0 ?> <?= htmlspecialchars($stockItem['unit'] ?? '') ?></span>
        </div>
        <div class="card-body">
            <form method="POST" action="?page=isp_inventory&tab=warehouse&action=record_movement&id=<?= $id ?>">
                <input type="hidden" name="stock_id" value="<?= $id ?>">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Movement Type *</label>
                        <select name="movement_type" class="form-select" required>
                            <option value="intake">Intake (Add)</option>
                            <option value="dispatch">Dispatch (Remove)</option>
                            <option value="usage">Field Usage</option>
                            <option value="return">Return</option>
                            <option value="loss">Loss/Damage</option>
                            <option value="adjustment_add">Adjustment (+)</option>
                            <option value="adjustment_remove">Adjustment (-)</option>
                        </select>
                    </div>
                    <div class="col-md-2"><label class="form-label">Quantity *</label><input type="number" step="0.01" name="quantity" class="form-control" required min="0.01"></div>
                    <div class="col-md-3"><label class="form-label">Reference #</label><input type="text" name="reference_number" class="form-control" placeholder="PO#, Ticket#, etc."></div>
                    <div class="col-md-4"><label class="form-label">Reason</label><input type="text" name="reason" class="form-control" placeholder="Reason for movement"></div>
                    <div class="col-md-4"><label class="form-label">From Location</label><input type="text" name="from_location" class="form-control"></div>
                    <div class="col-md-4"><label class="form-label">To Location</label><input type="text" name="to_location" class="form-control"></div>
                    <div class="col-md-4"><label class="form-label">Notes</label><input type="text" name="notes" class="form-control"></div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Record Movement</button>
                    <a href="?page=isp_inventory&tab=warehouse" class="btn btn-secondary">Back</a>
                </div>
            </form>
        </div>
    </div>
    <?php if (!empty($movements)): ?>
    <div class="card">
        <div class="card-header">Movement History</div>
        <div class="card-body p-0">
            <table class="table table-sm mb-0">
                <thead><tr><th>Date</th><th>Type</th><th>Qty</th><th>Reference</th><th>Reason</th><th>From</th><th>To</th></tr></thead>
                <tbody>
                <?php foreach ($movements as $mv): ?>
                    <tr>
                        <td><small><?= date('Y-m-d H:i', strtotime($mv['created_at'])) ?></small></td>
                        <td><span class="badge bg-<?= in_array($mv['movement_type'],['intake','return','adjustment_add'])?'success':'warning' ?>"><?= $mv['movement_type'] ?></span></td>
                        <td><?= $mv['quantity'] ?></td>
                        <td><?= htmlspecialchars($mv['reference_number'] ?? '') ?></td>
                        <td><?= htmlspecialchars($mv['reason'] ?? '') ?></td>
                        <td><?= htmlspecialchars($mv['from_location'] ?? '') ?></td>
                        <td><?= htmlspecialchars($mv['to_location'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php else:
        $stockCat = $_GET['stock_cat'] ?? '';
        $lowOnly = !empty($_GET['low_stock']);
        $stockList = $ispInv->getWarehouseStock(['category'=>$stockCat, 'low_stock'=>$lowOnly, 'search'=>$search]);
    ?>
    <div class="d-flex justify-content-between mb-3 flex-wrap gap-2">
        <form class="d-flex gap-2 flex-wrap" method="GET">
            <input type="hidden" name="page" value="isp_inventory"><input type="hidden" name="tab" value="warehouse">
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Search..." value="<?= htmlspecialchars($search) ?>" style="width:200px;">
            <select name="stock_cat" class="form-select form-select-sm" style="width:150px;">
                <option value="">All Categories</option>
                <?php foreach (['ONU/ONT','SFP Module','Fiber Cable','Patch Cord','Splitter','Distribution Box','Splice Closure','Drop Cable','Connector','Tools','UPS/Power','Switch/Router','Accessories','Other'] as $c): ?>
                <option value="<?= $c ?>" <?= $stockCat === $c ? 'selected' : '' ?>><?= $c ?></option>
                <?php endforeach; ?>
            </select>
            <div class="form-check form-check-inline align-self-center">
                <input type="checkbox" name="low_stock" value="1" class="form-check-input" id="lowStockChk" <?= $lowOnly ? 'checked' : '' ?>>
                <label class="form-check-label" for="lowStockChk">Low Stock Only</label>
            </div>
            <button class="btn btn-outline-primary btn-sm"><i class="bi bi-search"></i></button>
        </form>
        <a href="?page=isp_inventory&tab=warehouse&action=form" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Add Stock Item</a>
    </div>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead><tr><th>Item</th><th>Category</th><th>Site</th><th>Qty</th><th>Min</th><th>Unit Cost</th><th>Total Value</th><th>Supplier</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if (empty($stockList)): ?>
                <tr><td colspan="9" class="text-center text-muted">No stock items found.</td></tr>
            <?php else: foreach ($stockList as $st): ?>
                <tr class="<?= ($st['min_threshold'] > 0 && $st['quantity'] <= $st['min_threshold']) ? 'table-warning' : '' ?>">
                    <td><strong><?= htmlspecialchars($st['item_name']) ?></strong></td>
                    <td><span class="badge bg-secondary"><?= htmlspecialchars($st['category']) ?></span></td>
                    <td><?= htmlspecialchars($st['site_name'] ?? 'Main') ?></td>
                    <td>
                        <strong class="<?= ($st['min_threshold'] > 0 && $st['quantity'] <= $st['min_threshold']) ? 'text-danger' : '' ?>"><?= $st['quantity'] ?></strong>
                        <small><?= htmlspecialchars($st['unit']) ?></small>
                    </td>
                    <td><?= $st['min_threshold'] ?></td>
                    <td><?= $st['unit_cost'] ? number_format($st['unit_cost'],2) : '' ?></td>
                    <td><?= $st['unit_cost'] ? number_format($st['quantity'] * $st['unit_cost'],2) : '' ?></td>
                    <td><small><?= htmlspecialchars($st['supplier'] ?? '') ?></small></td>
                    <td>
                        <a href="?page=isp_inventory&tab=warehouse&action=movement&id=<?= $st['id'] ?>" class="btn btn-sm btn-outline-success" title="Stock Movement"><i class="bi bi-arrow-left-right"></i></a>
                        <a href="?page=isp_inventory&tab=warehouse&action=form&id=<?= $st['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                        <form method="POST" action="?page=isp_inventory&tab=warehouse&action=delete" class="d-inline" onsubmit="return confirm('Delete?')">
                            <input type="hidden" name="id" value="<?= $st['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php
    // ==================== FIELD ASSETS TAB ====================
    elseif ($tab === 'assets'):
        if ($action === 'form'):
            $item = null;
            if ($id) { foreach ($ispInv->getFieldAssets() as $fa) { if ($fa['id'] == $id) { $item = $fa; break; } } }
            $employees = $ispInv->getEmployees();
    ?>
    <div class="card">
        <div class="card-header"><?= $item ? 'Edit' : 'Add' ?> Field Asset</div>
        <div class="card-body">
            <form method="POST" action="?page=isp_inventory&tab=assets&action=save<?= $item ? '&id='.$item['id'] : '' ?>">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Asset Type *</label>
                        <select name="asset_type" class="form-select" required>
                            <?php foreach (['splicing_machine'=>'Splicing Machine','otdr'=>'OTDR','power_meter'=>'Power Meter','cleaver'=>'Fiber Cleaver','vehicle'=>'Vehicle','toolkit'=>'Toolkit','laptop'=>'Laptop','phone'=>'Phone','ladder'=>'Ladder','safety_gear'=>'Safety Gear','other'=>'Other'] as $k=>$v): ?>
                            <option value="<?= $k ?>" <?= ($item['asset_type'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4"><label class="form-label">Name *</label><input type="text" name="name" class="form-control" value="<?= htmlspecialchars($item['name'] ?? '') ?>" required></div>
                    <div class="col-md-4"><label class="form-label">Serial Number</label><input type="text" name="serial_number" class="form-control" value="<?= htmlspecialchars($item['serial_number'] ?? '') ?>"></div>
                    <div class="col-md-3"><label class="form-label">Manufacturer</label><input type="text" name="manufacturer" class="form-control" value="<?= htmlspecialchars($item['manufacturer'] ?? '') ?>"></div>
                    <div class="col-md-3"><label class="form-label">Model</label><input type="text" name="model" class="form-control" value="<?= htmlspecialchars($item['model'] ?? '') ?>"></div>
                    <div class="col-md-3"><label class="form-label">Purchase Date</label><input type="date" name="purchase_date" class="form-control" value="<?= $item['purchase_date'] ?? '' ?>"></div>
                    <div class="col-md-3"><label class="form-label">Purchase Price</label><input type="number" step="0.01" name="purchase_price" class="form-control" value="<?= $item['purchase_price'] ?? '' ?>"></div>
                    <div class="col-md-3"><label class="form-label">Warranty Expiry</label><input type="date" name="warranty_expiry" class="form-control" value="<?= $item['warranty_expiry'] ?? '' ?>"></div>
                    <div class="col-md-3">
                        <label class="form-label">Condition</label>
                        <select name="condition" class="form-select">
                            <?php foreach (['excellent'=>'Excellent','good'=>'Good','fair'=>'Fair','poor'=>'Poor'] as $k=>$v): ?>
                            <option value="<?= $k ?>" <?= ($item['condition'] ?? 'good') === $k ? 'selected' : '' ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Assigned To</label>
                        <select name="assigned_to" class="form-select">
                            <option value="">-- Unassigned --</option>
                            <?php foreach ($employees as $emp): ?>
                            <option value="<?= $emp['id'] ?>" <?= ($item['assigned_to'] ?? '') == $emp['id'] ? 'selected' : '' ?>><?= htmlspecialchars($emp['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <?php foreach (['available'=>'Available','in_use'=>'In Use','maintenance'=>'Maintenance','faulty'=>'Faulty','retired'=>'Retired'] as $k=>$v): ?>
                            <option value="<?= $k ?>" <?= ($item['status'] ?? 'available') === $k ? 'selected' : '' ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Site</label>
                        <select name="site_id" class="form-select"><option value="">-- None --</option>
                            <?php foreach ($sites as $s): ?><option value="<?= $s['id'] ?>" <?= ($item['site_id'] ?? '') == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4"><label class="form-label">Next Maintenance</label><input type="date" name="next_maintenance" class="form-control" value="<?= $item['next_maintenance'] ?? '' ?>"></div>
                    <div class="col-md-4"><label class="form-label">Last Maintenance</label><input type="date" name="last_maintenance" class="form-control" value="<?= $item['last_maintenance'] ?? '' ?>"></div>
                    <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"><?= htmlspecialchars($item['notes'] ?? '') ?></textarea></div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save</button>
                    <a href="?page=isp_inventory&tab=assets" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    <?php else:
        $assetType = $_GET['asset_type'] ?? '';
        $assetStatus = $_GET['asset_status'] ?? '';
        $assetList = $ispInv->getFieldAssets(['asset_type'=>$assetType, 'status'=>$assetStatus, 'search'=>$search]);
    ?>
    <div class="d-flex justify-content-between mb-3 flex-wrap gap-2">
        <form class="d-flex gap-2 flex-wrap" method="GET">
            <input type="hidden" name="page" value="isp_inventory"><input type="hidden" name="tab" value="assets">
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Search..." value="<?= htmlspecialchars($search) ?>" style="width:200px;">
            <select name="asset_type" class="form-select form-select-sm" style="width:160px;">
                <option value="">All Types</option>
                <?php foreach (['splicing_machine'=>'Splicing Machine','otdr'=>'OTDR','power_meter'=>'Power Meter','vehicle'=>'Vehicle','toolkit'=>'Toolkit','laptop'=>'Laptop'] as $k=>$v): ?>
                <option value="<?= $k ?>" <?= $assetType === $k ? 'selected' : '' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
            <select name="asset_status" class="form-select form-select-sm" style="width:130px;">
                <option value="">All Status</option>
                <?php foreach (['available','in_use','maintenance','faulty','retired'] as $st): ?>
                <option value="<?= $st ?>" <?= $assetStatus === $st ? 'selected' : '' ?>><?= ucfirst(str_replace('_',' ',$st)) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-outline-primary btn-sm"><i class="bi bi-search"></i></button>
        </form>
        <a href="?page=isp_inventory&tab=assets&action=form" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Add Asset</a>
    </div>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead><tr><th>Name</th><th>Type</th><th>Serial</th><th>Model</th><th>Assigned To</th><th>Condition</th><th>Next Maint.</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if (empty($assetList)): ?>
                <tr><td colspan="9" class="text-center text-muted">No assets found.</td></tr>
            <?php else: foreach ($assetList as $fa): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($fa['name']) ?></strong></td>
                    <td><span class="badge bg-info"><?= str_replace('_',' ',$fa['asset_type']) ?></span></td>
                    <td><small><?= htmlspecialchars($fa['serial_number'] ?? '') ?></small></td>
                    <td><?= htmlspecialchars(($fa['manufacturer'] ?? '') . ' ' . ($fa['model'] ?? '')) ?></td>
                    <td><?= htmlspecialchars($fa['assigned_to_name'] ?? 'Unassigned') ?></td>
                    <td><span class="badge bg-<?= $fa['condition']==='good'||$fa['condition']==='excellent'?'success':($fa['condition']==='poor'?'danger':'warning') ?>"><?= $fa['condition'] ?></span></td>
                    <td><?= $fa['next_maintenance'] ?? '' ?></td>
                    <td><span class="badge bg-<?= $fa['status']==='available'?'success':($fa['status']==='in_use'?'primary':($fa['status']==='faulty'?'danger':'warning')) ?>"><?= str_replace('_',' ',$fa['status']) ?></span></td>
                    <td>
                        <a href="?page=isp_inventory&tab=assets&action=form&id=<?= $fa['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                        <form method="POST" action="?page=isp_inventory&tab=assets&action=delete" class="d-inline" onsubmit="return confirm('Delete?')">
                            <input type="hidden" name="id" value="<?= $fa['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php
    // ==================== MAINTENANCE TAB ====================
    elseif ($tab === 'maintenance'):
        if ($action === 'form'):
            $employees = $ispInv->getEmployees();
    ?>
    <div class="card">
        <div class="card-header">Log Maintenance Activity</div>
        <div class="card-body">
            <form method="POST" action="?page=isp_inventory&tab=maintenance&action=save">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Asset Type *</label>
                        <select name="asset_type" class="form-select" required>
                            <?php foreach (['core_equipment'=>'Core Equipment','splitter'=>'Splitter','distribution_box'=>'Distribution Box','cpe_device'=>'CPE Device','field_asset'=>'Field Asset','fiber_core'=>'Fiber Core','site'=>'Network Site'] as $k=>$v): ?>
                            <option value="<?= $k ?>"><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2"><label class="form-label">Asset ID *</label><input type="number" name="asset_id" class="form-control" required></div>
                    <div class="col-md-4"><label class="form-label">Asset Name</label><input type="text" name="asset_name" class="form-control"></div>
                    <div class="col-md-3">
                        <label class="form-label">Maintenance Type *</label>
                        <select name="maintenance_type" class="form-select" required>
                            <?php foreach (['preventive'=>'Preventive','corrective'=>'Corrective','inspection'=>'Inspection','cleaning'=>'Cleaning','calibration'=>'Calibration','replacement'=>'Replacement','upgrade'=>'Upgrade'] as $k=>$v): ?>
                            <option value="<?= $k ?>"><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
                    <div class="col-md-3">
                        <label class="form-label">Performed By</label>
                        <select name="performed_by" class="form-select"><option value="">-- Select --</option>
                            <?php foreach ($employees as $emp): ?><option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3"><label class="form-label">Cost</label><input type="number" step="0.01" name="cost" class="form-control" value="0"></div>
                    <div class="col-md-3"><label class="form-label">Next Due Date</label><input type="date" name="next_due" class="form-control"></div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="completed">Completed</option>
                            <option value="pending">Pending</option>
                            <option value="in_progress">In Progress</option>
                        </select>
                    </div>
                    <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save</button>
                    <a href="?page=isp_inventory&tab=maintenance" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    <?php else:
        $maintList = $ispInv->getMaintenanceLogs(['search'=>$search]);
    ?>
    <div class="d-flex justify-content-between mb-3">
        <form class="d-flex gap-2" method="GET">
            <input type="hidden" name="page" value="isp_inventory"><input type="hidden" name="tab" value="maintenance">
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Search..." value="<?= htmlspecialchars($search) ?>" style="width:250px;">
            <button class="btn btn-outline-primary btn-sm"><i class="bi bi-search"></i></button>
        </form>
        <a href="?page=isp_inventory&tab=maintenance&action=form" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Log Maintenance</a>
    </div>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead><tr><th>Date</th><th>Asset</th><th>Type</th><th>Maint. Type</th><th>Description</th><th>By</th><th>Cost</th><th>Next Due</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if (empty($maintList)): ?>
                <tr><td colspan="10" class="text-center text-muted">No maintenance logs found.</td></tr>
            <?php else: foreach ($maintList as $ml): ?>
                <tr>
                    <td><small><?= date('Y-m-d', strtotime($ml['created_at'])) ?></small></td>
                    <td><?= htmlspecialchars($ml['asset_name'] ?? 'ID:'.$ml['asset_id']) ?></td>
                    <td><span class="badge bg-secondary"><?= str_replace('_',' ',$ml['asset_type']) ?></span></td>
                    <td><span class="badge bg-info"><?= $ml['maintenance_type'] ?></span></td>
                    <td><small><?= htmlspecialchars(substr($ml['description'] ?? '',0,80)) ?></small></td>
                    <td><?= htmlspecialchars($ml['performed_by_name'] ?? '') ?></td>
                    <td><?= $ml['cost'] > 0 ? number_format($ml['cost'],2) : '' ?></td>
                    <td><?= $ml['next_due'] ?? '' ?></td>
                    <td><span class="badge bg-<?= $ml['status']==='completed'?'success':($ml['status']==='pending'?'warning':'primary') ?>"><?= $ml['status'] ?></span></td>
                    <td>
                        <form method="POST" action="?page=isp_inventory&tab=maintenance&action=delete" class="d-inline" onsubmit="return confirm('Delete?')">
                            <input type="hidden" name="id" value="<?= $ml['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>

<div class="offcanvas offcanvas-start" tabindex="-1" id="invMobileNav" style="background: var(--inv-sidebar-bg); width: 280px;">
    <div class="offcanvas-header border-bottom" style="border-color: var(--inv-border) !important;">
        <h5 class="offcanvas-title text-white"><i class="bi bi-hdd-network me-2"></i>Inventory</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body p-0">
        <div class="quick-nav p-3 border-bottom" style="border-color: var(--inv-border) !important;">
            <a href="?page=dashboard" class="btn btn-sm btn-outline-light me-1"><i class="bi bi-grid-3x3-gap"></i> CRM</a>
            <a href="?page=huawei-olt" class="btn btn-sm btn-outline-light me-1"><i class="bi bi-router"></i> OMS</a>
            <a href="?page=isp" class="btn btn-sm btn-outline-light"><i class="bi bi-broadcast"></i> ISP</a>
        </div>
        <ul class="sidebar-nav list-unstyled p-3">
            <li class="mb-1"><a href="?page=isp_inventory&tab=overview" class="d-flex align-items-center gap-2 p-2 rounded text-decoration-none <?= $tab === 'overview' ? 'text-white' : '' ?>" style="color: var(--inv-text); <?= $tab === 'overview' ? 'background: var(--inv-accent-light); color: var(--inv-accent) !important;' : '' ?>"><i class="bi bi-speedometer2"></i> Overview</a></li>
            <li class="mb-1"><a href="?page=isp_inventory&tab=ont" class="d-flex align-items-center gap-2 p-2 rounded text-decoration-none" style="color: var(--inv-text); <?= $tab === 'ont' ? 'background: var(--inv-accent-light); color: var(--inv-accent) !important;' : '' ?>"><i class="bi bi-router"></i> ONT Inventory</a></li>
            <li class="mb-1"><a href="?page=isp_inventory&tab=sites" class="d-flex align-items-center gap-2 p-2 rounded text-decoration-none" style="color: var(--inv-text); <?= $tab === 'sites' ? 'background: var(--inv-accent-light); color: var(--inv-accent) !important;' : '' ?>"><i class="bi bi-geo-alt"></i> Sites</a></li>
            <li class="mb-1"><a href="?page=isp_inventory&tab=core" class="d-flex align-items-center gap-2 p-2 rounded text-decoration-none" style="color: var(--inv-text); <?= $tab === 'core' ? 'background: var(--inv-accent-light); color: var(--inv-accent) !important;' : '' ?>"><i class="bi bi-hdd-rack"></i> Core Network</a></li>
            <li class="mb-1"><a href="?page=isp_inventory&tab=ipam" class="d-flex align-items-center gap-2 p-2 rounded text-decoration-none" style="color: var(--inv-text); <?= $tab === 'ipam' ? 'background: var(--inv-accent-light); color: var(--inv-accent) !important;' : '' ?>"><i class="bi bi-globe"></i> IPAM</a></li>
            <li class="mb-1"><a href="?page=isp_inventory&tab=warehouse" class="d-flex align-items-center gap-2 p-2 rounded text-decoration-none" style="color: var(--inv-text); <?= $tab === 'warehouse' ? 'background: var(--inv-accent-light); color: var(--inv-accent) !important;' : '' ?>"><i class="bi bi-box-seam"></i> Warehouse</a></li>
            <li class="mb-1"><a href="?page=isp_inventory&tab=assets" class="d-flex align-items-center gap-2 p-2 rounded text-decoration-none" style="color: var(--inv-text); <?= $tab === 'assets' ? 'background: var(--inv-accent-light); color: var(--inv-accent) !important;' : '' ?>"><i class="bi bi-tools"></i> Field Assets</a></li>
            <li class="mb-1"><a href="?page=isp_inventory&tab=maintenance" class="d-flex align-items-center gap-2 p-2 rounded text-decoration-none" style="color: var(--inv-text); <?= $tab === 'maintenance' ? 'background: var(--inv-accent-light); color: var(--inv-accent) !important;' : '' ?>"><i class="bi bi-wrench"></i> Maintenance</a></li>
        </ul>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleInvSidebar() {
    const sidebar = document.getElementById('invSidebar');
    const overlay = document.getElementById('invMobileOverlay');
    sidebar.classList.toggle('show');
    overlay.classList.toggle('show');
}

document.querySelectorAll('#invSidebar .sidebar-nav a').forEach(link => {
    link.addEventListener('click', () => {
        if (window.innerWidth < 992) {
            toggleInvSidebar();
        }
    });
});
</script>
</body>
</html>
<?php exit; ?>
