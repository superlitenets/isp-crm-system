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

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-hdd-network"></i> ISP Inventory</h2>
    </div>

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

    <ul class="nav nav-tabs mb-4 flex-nowrap" style="overflow-x:auto;white-space:nowrap;">
        <li class="nav-item"><a class="nav-link <?= $tab==='overview'?'active':'' ?>" href="?page=isp_inventory&tab=overview"><i class="bi bi-speedometer2"></i> Overview</a></li>
        <li class="nav-item"><a class="nav-link <?= $tab==='sites'?'active':'' ?>" href="?page=isp_inventory&tab=sites"><i class="bi bi-geo-alt"></i> Sites</a></li>
        <li class="nav-item"><a class="nav-link <?= $tab==='core'?'active':'' ?>" href="?page=isp_inventory&tab=core"><i class="bi bi-hdd-rack"></i> Core Network</a></li>
        <li class="nav-item"><a class="nav-link <?= $tab==='ftth'?'active':'' ?>" href="?page=isp_inventory&tab=ftth"><i class="bi bi-diagram-3"></i> Access / FTTH</a></li>
        <li class="nav-item"><a class="nav-link <?= $tab==='cpe'?'active':'' ?>" href="?page=isp_inventory&tab=cpe"><i class="bi bi-router"></i> CPE / ONU</a></li>
        <li class="nav-item"><a class="nav-link <?= $tab==='ipam'?'active':'' ?>" href="?page=isp_inventory&tab=ipam"><i class="bi bi-globe"></i> IPAM</a></li>
        <li class="nav-item"><a class="nav-link <?= $tab==='warehouse'?'active':'' ?>" href="?page=isp_inventory&tab=warehouse"><i class="bi bi-box-seam"></i> Warehouse</a></li>
        <li class="nav-item"><a class="nav-link <?= $tab==='assets'?'active':'' ?>" href="?page=isp_inventory&tab=assets"><i class="bi bi-tools"></i> Field Assets</a></li>
        <li class="nav-item"><a class="nav-link <?= $tab==='maintenance'?'active':'' ?>" href="?page=isp_inventory&tab=maintenance"><i class="bi bi-wrench"></i> Maintenance</a></li>
    </ul>

    <?php
    // ==================== OVERVIEW TAB ====================
    if ($tab === 'overview'):
        $stats = $ispInv->getDashboardStats();
        $lowStock = $ispInv->getLowStockAlerts();
    ?>
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3 col-lg-2">
            <div class="card text-center border-primary h-100">
                <div class="card-body py-3">
                    <h3 class="text-primary mb-1"><?= $stats['total_sites'] ?></h3>
                    <small class="text-muted">Sites</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <div class="card text-center border-info h-100">
                <div class="card-body py-3">
                    <h3 class="text-info mb-1"><?= $stats['total_core_equipment'] ?></h3>
                    <small class="text-muted">Core Equipment</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <div class="card text-center border-success h-100">
                <div class="card-body py-3">
                    <h3 class="text-success mb-1"><?= $stats['total_splitters'] ?></h3>
                    <small class="text-muted">Splitters</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <div class="card text-center border-warning h-100">
                <div class="card-body py-3">
                    <h3 class="text-warning mb-1"><?= $stats['total_cpe_deployed'] ?> / <?= $stats['total_cpe_in_stock'] ?></h3>
                    <small class="text-muted">CPE Deployed / Stock</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <div class="card text-center border-secondary h-100">
                <div class="card-body py-3">
                    <h3 class="text-secondary mb-1"><?= $stats['fiber_cores_used'] ?> / <?= $stats['total_fiber_cores'] ?></h3>
                    <small class="text-muted">Fiber Cores Used</small>
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

    <div class="row g-3">
        <div class="col-md-6">
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
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><i class="bi bi-exclamation-triangle"></i> Low Stock Alerts</div>
                <div class="card-body" style="max-height:250px;overflow-y:auto;">
                    <?php if (empty($lowStock)): ?>
                        <p class="text-success mb-0">All stock levels are healthy.</p>
                    <?php else: ?>
                        <table class="table table-sm table-striped mb-0">
                            <thead><tr><th>Item</th><th>Qty</th><th>Min</th><th>Site</th></tr></thead>
                            <tbody>
                            <?php foreach ($lowStock as $ls): ?>
                                <tr class="table-warning">
                                    <td><?= htmlspecialchars($ls['item_name']) ?></td>
                                    <td><strong class="text-danger"><?= $ls['quantity'] ?></strong></td>
                                    <td><?= $ls['min_threshold'] ?></td>
                                    <td><?= htmlspecialchars($ls['site_name'] ?? 'N/A') ?></td>
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
    // ==================== FTTH / ACCESS NETWORK TAB ====================
    elseif ($tab === 'ftth'):
        $ftthSub = $_GET['sub'] ?? 'splitters';
    ?>
    <ul class="nav nav-pills mb-3">
        <li class="nav-item"><a class="nav-link <?= $ftthSub==='splitters'?'active':'' ?>" href="?page=isp_inventory&tab=ftth&sub=splitters">Splitters</a></li>
        <li class="nav-item"><a class="nav-link <?= $ftthSub==='fiber'?'active':'' ?>" href="?page=isp_inventory&tab=ftth&sub=fiber">Fiber Cores</a></li>
        <li class="nav-item"><a class="nav-link <?= $ftthSub==='fdb'?'active':'' ?>" href="?page=isp_inventory&tab=ftth&sub=fdb">Distribution Boxes</a></li>
        <li class="nav-item"><a class="nav-link <?= $ftthSub==='splice'?'active':'' ?>" href="?page=isp_inventory&tab=ftth&sub=splice">Splice Closures</a></li>
        <li class="nav-item"><a class="nav-link <?= $ftthSub==='drop'?'active':'' ?>" href="?page=isp_inventory&tab=ftth&sub=drop">Drop Cables</a></li>
    </ul>

    <?php if ($ftthSub === 'splitters'):
        if ($action === 'form'):
            $item = $id ? $ispInv->getSplitters(['search'=>''])[array_search($id, array_column($ispInv->getSplitters(), 'id'))] ?? null : null;
            $coreEquip = $ispInv->getCoreEquipment();
    ?>
    <div class="card">
        <div class="card-header"><?= $item ? 'Edit' : 'Add' ?> Splitter</div>
        <div class="card-body">
            <form method="POST" action="?page=isp_inventory&tab=ftth&sub=splitters&action=save<?= $item ? '&id='.$item['id'] : '' ?>">
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label">Name *</label><input type="text" name="name" class="form-control" value="<?= htmlspecialchars($item['name'] ?? '') ?>" required></div>
                    <div class="col-md-4">
                        <label class="form-label">Ratio</label>
                        <select name="ratio" class="form-select">
                            <?php foreach (['1:2','1:4','1:8','1:16','1:32','1:64'] as $r): ?>
                            <option value="<?= $r ?>" <?= ($item['ratio'] ?? '1:8') === $r ? 'selected' : '' ?>><?= $r ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4"><label class="form-label">Total Ports</label><input type="number" name="total_ports" class="form-control" value="<?= $item['total_ports'] ?? 8 ?>"></div>
                    <div class="col-md-4"><label class="form-label">Used Ports</label><input type="number" name="used_ports" class="form-control" value="<?= $item['used_ports'] ?? 0 ?>"></div>
                    <div class="col-md-4">
                        <label class="form-label">Site</label>
                        <select name="site_id" class="form-select"><option value="">-- Select --</option>
                            <?php foreach ($sites as $s): ?><option value="<?= $s['id'] ?>" <?= ($item['site_id'] ?? '') == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Upstream Equipment</label>
                        <select name="upstream_equipment_id" class="form-select"><option value="">-- Select --</option>
                            <?php foreach ($coreEquip as $ce): ?><option value="<?= $ce['id'] ?>" <?= ($item['upstream_equipment_id'] ?? '') == $ce['id'] ? 'selected' : '' ?>><?= htmlspecialchars($ce['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4"><label class="form-label">Upstream Port</label><input type="text" name="upstream_port" class="form-control" value="<?= htmlspecialchars($item['upstream_port'] ?? '') ?>"></div>
                    <div class="col-md-4"><label class="form-label">Pole Number</label><input type="text" name="pole_number" class="form-control" value="<?= htmlspecialchars($item['pole_number'] ?? '') ?>"></div>
                    <div class="col-md-4"><label class="form-label">GPS Lat</label><input type="text" name="gps_lat" class="form-control" value="<?= $item['gps_lat'] ?? '' ?>"></div>
                    <div class="col-md-4"><label class="form-label">GPS Lng</label><input type="text" name="gps_lng" class="form-control" value="<?= $item['gps_lng'] ?? '' ?>"></div>
                    <div class="col-md-8"><label class="form-label">Location Description</label><input type="text" name="location_description" class="form-control" value="<?= htmlspecialchars($item['location_description'] ?? '') ?>"></div>
                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <?php foreach (['active'=>'Active','maintenance'=>'Maintenance','faulty'=>'Faulty','retired'=>'Retired'] as $k=>$v): ?>
                            <option value="<?= $k ?>" <?= ($item['status'] ?? 'active') === $k ? 'selected' : '' ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"><?= htmlspecialchars($item['notes'] ?? '') ?></textarea></div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save</button>
                    <a href="?page=isp_inventory&tab=ftth&sub=splitters" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    <?php else:
        $splitterList = $ispInv->getSplitters(['search'=>$search]);
    ?>
    <div class="d-flex justify-content-between mb-3">
        <form class="d-flex gap-2" method="GET">
            <input type="hidden" name="page" value="isp_inventory"><input type="hidden" name="tab" value="ftth"><input type="hidden" name="sub" value="splitters">
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Search..." value="<?= htmlspecialchars($search) ?>" style="width:250px;">
            <button class="btn btn-outline-primary btn-sm"><i class="bi bi-search"></i></button>
        </form>
        <a href="?page=isp_inventory&tab=ftth&sub=splitters&action=form" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Add Splitter</a>
    </div>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead><tr><th>Name</th><th>Ratio</th><th>Ports</th><th>Site</th><th>Pole</th><th>Upstream</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if (empty($splitterList)): ?>
                <tr><td colspan="8" class="text-center text-muted">No splitters found.</td></tr>
            <?php else: foreach ($splitterList as $sp): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($sp['name']) ?></strong></td>
                    <td><?= htmlspecialchars($sp['ratio']) ?></td>
                    <td><?= $sp['used_ports'] ?> / <?= $sp['total_ports'] ?></td>
                    <td><?= htmlspecialchars($sp['site_name'] ?? '') ?></td>
                    <td><?= htmlspecialchars($sp['pole_number'] ?? '') ?></td>
                    <td><?= htmlspecialchars($sp['upstream_equipment_name'] ?? '') ?></td>
                    <td><span class="badge bg-<?= $sp['status']==='active'?'success':($sp['status']==='faulty'?'danger':'warning') ?>"><?= $sp['status'] ?></span></td>
                    <td>
                        <a href="?page=isp_inventory&tab=ftth&sub=splitters&action=form&id=<?= $sp['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                        <form method="POST" action="?page=isp_inventory&tab=ftth&sub=splitters&action=delete" class="d-inline" onsubmit="return confirm('Delete?')">
                            <input type="hidden" name="id" value="<?= $sp['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php elseif ($ftthSub === 'fiber'):
        if ($action === 'form'):
            $item = null;
            if ($id) { $stmt = $ispInv->getFiberCores(); foreach ($stmt as $fc) { if ($fc['id'] == $id) { $item = $fc; break; } } }
    ?>
    <div class="card">
        <div class="card-header"><?= $item ? 'Edit' : 'Add' ?> Fiber Core</div>
        <div class="card-body">
            <form method="POST" action="?page=isp_inventory&tab=ftth&sub=fiber&action=save<?= $item ? '&id='.$item['id'] : '' ?>">
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label">Cable Name *</label><input type="text" name="cable_name" class="form-control" value="<?= htmlspecialchars($item['cable_name'] ?? '') ?>" required placeholder="e.g., Backbone-A"></div>
                    <div class="col-md-2"><label class="form-label">Core # *</label><input type="number" name="core_number" class="form-control" value="<?= $item['core_number'] ?? '' ?>" required></div>
                    <div class="col-md-3"><label class="form-label">Core Color</label><input type="text" name="core_color" class="form-control" value="<?= htmlspecialchars($item['core_color'] ?? '') ?>"></div>
                    <div class="col-md-3"><label class="form-label">Tube Color</label><input type="text" name="tube_color" class="form-control" value="<?= htmlspecialchars($item['tube_color'] ?? '') ?>"></div>
                    <div class="col-md-6"><label class="form-label">Start Point</label><input type="text" name="start_point" class="form-control" value="<?= htmlspecialchars($item['start_point'] ?? '') ?>"></div>
                    <div class="col-md-6"><label class="form-label">End Point</label><input type="text" name="end_point" class="form-control" value="<?= htmlspecialchars($item['end_point'] ?? '') ?>"></div>
                    <div class="col-md-3"><label class="form-label">Distance (m)</label><input type="number" step="0.01" name="distance_meters" class="form-control" value="<?= $item['distance_meters'] ?? '' ?>"></div>
                    <div class="col-md-3"><label class="form-label">Attenuation (dB)</label><input type="number" step="0.001" name="attenuation_db" class="form-control" value="<?= $item['attenuation_db'] ?? '' ?>"></div>
                    <div class="col-md-3"><label class="form-label">Assigned To</label><input type="text" name="assigned_to" class="form-control" value="<?= htmlspecialchars($item['assigned_to'] ?? '') ?>"></div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <?php foreach (['available'=>'Available','in_use'=>'In Use','reserved'=>'Reserved','faulty'=>'Faulty','spliced'=>'Spliced'] as $k=>$v): ?>
                            <option value="<?= $k ?>" <?= ($item['status'] ?? 'available') === $k ? 'selected' : '' ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12"><label class="form-label">Route Path</label><textarea name="route_path" class="form-control" rows="2" placeholder="Describe fiber route..."><?= htmlspecialchars($item['route_path'] ?? '') ?></textarea></div>
                    <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"><?= htmlspecialchars($item['notes'] ?? '') ?></textarea></div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save</button>
                    <a href="?page=isp_inventory&tab=ftth&sub=fiber" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    <?php else:
        $fiberList = $ispInv->getFiberCores(['search'=>$search]);
    ?>
    <div class="d-flex justify-content-between mb-3">
        <form class="d-flex gap-2" method="GET">
            <input type="hidden" name="page" value="isp_inventory"><input type="hidden" name="tab" value="ftth"><input type="hidden" name="sub" value="fiber">
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Search..." value="<?= htmlspecialchars($search) ?>" style="width:250px;">
            <button class="btn btn-outline-primary btn-sm"><i class="bi bi-search"></i></button>
        </form>
        <a href="?page=isp_inventory&tab=ftth&sub=fiber&action=form" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Add Fiber Core</a>
    </div>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead><tr><th>Cable</th><th>Core #</th><th>Colors</th><th>Route</th><th>Distance</th><th>Loss</th><th>Assigned</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if (empty($fiberList)): ?>
                <tr><td colspan="9" class="text-center text-muted">No fiber cores found.</td></tr>
            <?php else: foreach ($fiberList as $fc): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($fc['cable_name']) ?></strong></td>
                    <td><?= $fc['core_number'] ?></td>
                    <td><small><?= htmlspecialchars(($fc['tube_color'] ?? '') . '/' . ($fc['core_color'] ?? '')) ?></small></td>
                    <td><small><?= htmlspecialchars($fc['start_point'] ?? '') ?> → <?= htmlspecialchars($fc['end_point'] ?? '') ?></small></td>
                    <td><?= $fc['distance_meters'] ? $fc['distance_meters'].'m' : '' ?></td>
                    <td><?= $fc['attenuation_db'] ? $fc['attenuation_db'].'dB' : '' ?></td>
                    <td><?= htmlspecialchars($fc['assigned_to'] ?? '') ?></td>
                    <td><span class="badge bg-<?= $fc['status']==='available'?'success':($fc['status']==='in_use'?'primary':($fc['status']==='faulty'?'danger':'secondary')) ?>"><?= $fc['status'] ?></span></td>
                    <td>
                        <a href="?page=isp_inventory&tab=ftth&sub=fiber&action=form&id=<?= $fc['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                        <form method="POST" action="?page=isp_inventory&tab=ftth&sub=fiber&action=delete" class="d-inline" onsubmit="return confirm('Delete?')">
                            <input type="hidden" name="id" value="<?= $fc['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php elseif ($ftthSub === 'fdb'):
        if ($action === 'form'):
            $item = null;
            if ($id) { foreach ($ispInv->getDistributionBoxes() as $db_item) { if ($db_item['id'] == $id) { $item = $db_item; break; } } }
            $splitters = $ispInv->getSplitters();
    ?>
    <div class="card">
        <div class="card-header"><?= $item ? 'Edit' : 'Add' ?> Distribution Box</div>
        <div class="card-body">
            <form method="POST" action="?page=isp_inventory&tab=ftth&sub=fdb&action=save<?= $item ? '&id='.$item['id'] : '' ?>">
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label">Name *</label><input type="text" name="name" class="form-control" value="<?= htmlspecialchars($item['name'] ?? '') ?>" required></div>
                    <div class="col-md-4">
                        <label class="form-label">Box Type</label>
                        <select name="box_type" class="form-select">
                            <?php foreach (['FDB'=>'FDB','FAT'=>'FAT','FDT'=>'FDT','FTTH_Box'=>'FTTH Box'] as $k=>$v): ?>
                            <option value="<?= $k ?>" <?= ($item['box_type'] ?? 'FDB') === $k ? 'selected' : '' ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2"><label class="form-label">Capacity</label><input type="number" name="capacity" class="form-control" value="<?= $item['capacity'] ?? 16 ?>"></div>
                    <div class="col-md-2"><label class="form-label">Used Ports</label><input type="number" name="used_ports" class="form-control" value="<?= $item['used_ports'] ?? 0 ?>"></div>
                    <div class="col-md-4">
                        <label class="form-label">Site</label>
                        <select name="site_id" class="form-select"><option value="">-- Select --</option>
                            <?php foreach ($sites as $s): ?><option value="<?= $s['id'] ?>" <?= ($item['site_id'] ?? '') == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Connected Splitter</label>
                        <select name="splitter_id" class="form-select"><option value="">-- Select --</option>
                            <?php foreach ($splitters as $sp): ?><option value="<?= $sp['id'] ?>" <?= ($item['splitter_id'] ?? '') == $sp['id'] ? 'selected' : '' ?>><?= htmlspecialchars($sp['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4"><label class="form-label">Pole Number</label><input type="text" name="pole_number" class="form-control" value="<?= htmlspecialchars($item['pole_number'] ?? '') ?>"></div>
                    <div class="col-md-3"><label class="form-label">GPS Lat</label><input type="text" name="gps_lat" class="form-control" value="<?= $item['gps_lat'] ?? '' ?>"></div>
                    <div class="col-md-3"><label class="form-label">GPS Lng</label><input type="text" name="gps_lng" class="form-control" value="<?= $item['gps_lng'] ?? '' ?>"></div>
                    <div class="col-md-6"><label class="form-label">Location</label><input type="text" name="location_description" class="form-control" value="<?= htmlspecialchars($item['location_description'] ?? '') ?>"></div>
                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <?php foreach (['active'=>'Active','maintenance'=>'Maintenance','faulty'=>'Faulty','retired'=>'Retired'] as $k=>$v): ?>
                            <option value="<?= $k ?>" <?= ($item['status'] ?? 'active') === $k ? 'selected' : '' ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"><?= htmlspecialchars($item['notes'] ?? '') ?></textarea></div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save</button>
                    <a href="?page=isp_inventory&tab=ftth&sub=fdb" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    <?php else:
        $fdbList = $ispInv->getDistributionBoxes(['search'=>$search]);
    ?>
    <div class="d-flex justify-content-between mb-3">
        <form class="d-flex gap-2" method="GET">
            <input type="hidden" name="page" value="isp_inventory"><input type="hidden" name="tab" value="ftth"><input type="hidden" name="sub" value="fdb">
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Search..." value="<?= htmlspecialchars($search) ?>" style="width:250px;">
            <button class="btn btn-outline-primary btn-sm"><i class="bi bi-search"></i></button>
        </form>
        <a href="?page=isp_inventory&tab=ftth&sub=fdb&action=form" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Add Distribution Box</a>
    </div>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead><tr><th>Name</th><th>Type</th><th>Ports</th><th>Site</th><th>Splitter</th><th>Pole</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if (empty($fdbList)): ?>
                <tr><td colspan="8" class="text-center text-muted">No distribution boxes found.</td></tr>
            <?php else: foreach ($fdbList as $db_row): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($db_row['name']) ?></strong></td>
                    <td><?= htmlspecialchars($db_row['box_type'] ?? '') ?></td>
                    <td><?= $db_row['used_ports'] ?> / <?= $db_row['capacity'] ?></td>
                    <td><?= htmlspecialchars($db_row['site_name'] ?? '') ?></td>
                    <td><?= htmlspecialchars($db_row['splitter_name'] ?? '') ?></td>
                    <td><?= htmlspecialchars($db_row['pole_number'] ?? '') ?></td>
                    <td><span class="badge bg-<?= $db_row['status']==='active'?'success':($db_row['status']==='faulty'?'danger':'warning') ?>"><?= $db_row['status'] ?></span></td>
                    <td>
                        <a href="?page=isp_inventory&tab=ftth&sub=fdb&action=form&id=<?= $db_row['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                        <form method="POST" action="?page=isp_inventory&tab=ftth&sub=fdb&action=delete" class="d-inline" onsubmit="return confirm('Delete?')">
                            <input type="hidden" name="id" value="<?= $db_row['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php elseif ($ftthSub === 'splice'):
        $spliceList = $ispInv->getSpliceClosures(['search'=>$search]);
    ?>
    <div class="d-flex justify-content-between mb-3">
        <form class="d-flex gap-2" method="GET">
            <input type="hidden" name="page" value="isp_inventory"><input type="hidden" name="tab" value="ftth"><input type="hidden" name="sub" value="splice">
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Search..." value="<?= htmlspecialchars($search) ?>" style="width:250px;">
            <button class="btn btn-outline-primary btn-sm"><i class="bi bi-search"></i></button>
        </form>
        <a href="?page=isp_inventory&tab=ftth&sub=splice&action=form" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Add Splice Closure</a>
    </div>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead><tr><th>Name</th><th>Type</th><th>Location</th><th>Pole</th><th>Fiber Cable</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if (empty($spliceList)): ?>
                <tr><td colspan="7" class="text-center text-muted">No splice closures found.</td></tr>
            <?php else: foreach ($spliceList as $sc): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($sc['name']) ?></strong></td>
                    <td><?= htmlspecialchars($sc['closure_type'] ?? '') ?></td>
                    <td><?= htmlspecialchars($sc['location_description'] ?? '') ?></td>
                    <td><?= htmlspecialchars($sc['pole_number'] ?? '') ?></td>
                    <td><?= htmlspecialchars($sc['fiber_cable_name'] ?? '') ?></td>
                    <td><span class="badge bg-<?= $sc['status']==='active'?'success':'warning' ?>"><?= $sc['status'] ?></span></td>
                    <td>
                        <a href="?page=isp_inventory&tab=ftth&sub=splice&action=form&id=<?= $sc['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                        <form method="POST" action="?page=isp_inventory&tab=ftth&sub=splice&action=delete" class="d-inline" onsubmit="return confirm('Delete?')">
                            <input type="hidden" name="id" value="<?= $sc['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?php elseif ($ftthSub === 'drop'):
        $dropList = $ispInv->getDropCables(['search'=>$search]);
    ?>
    <div class="d-flex justify-content-between mb-3">
        <form class="d-flex gap-2" method="GET">
            <input type="hidden" name="page" value="isp_inventory"><input type="hidden" name="tab" value="ftth"><input type="hidden" name="sub" value="drop">
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Search..." value="<?= htmlspecialchars($search) ?>" style="width:250px;">
            <button class="btn btn-outline-primary btn-sm"><i class="bi bi-search"></i></button>
        </form>
        <a href="?page=isp_inventory&tab=ftth&sub=drop&action=form" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Add Drop Cable</a>
    </div>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead><tr><th>ID</th><th>Distribution Box</th><th>Port</th><th>Customer</th><th>Type</th><th>Length</th><th>Installed</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if (empty($dropList)): ?>
                <tr><td colspan="9" class="text-center text-muted">No drop cables found.</td></tr>
            <?php else: foreach ($dropList as $dc): ?>
                <tr>
                    <td><?= $dc['id'] ?></td>
                    <td><?= htmlspecialchars($dc['box_name'] ?? '') ?></td>
                    <td><?= $dc['box_port'] ?? '' ?></td>
                    <td><?= htmlspecialchars($dc['customer_name'] ?? '') ?></td>
                    <td><?= htmlspecialchars($dc['cable_type'] ?? '') ?></td>
                    <td><?= $dc['length_meters'] ? $dc['length_meters'].'m' : '' ?></td>
                    <td><?= $dc['installation_date'] ?? '' ?></td>
                    <td><span class="badge bg-<?= $dc['status']==='active'?'success':'warning' ?>"><?= $dc['status'] ?></span></td>
                    <td>
                        <form method="POST" action="?page=isp_inventory&tab=ftth&sub=drop&action=delete" class="d-inline" onsubmit="return confirm('Delete?')">
                            <input type="hidden" name="id" value="<?= $dc['id'] ?>">
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
    // ==================== CPE / ONU TAB ====================
    elseif ($tab === 'cpe'):
        if ($action === 'form'):
            $item = $id ? $ispInv->getCPEDevice($id) : null;
            $splitters = $ispInv->getSplitters();
    ?>
    <div class="card">
        <div class="card-header"><?= $item ? 'Edit' : 'Add' ?> CPE / ONU Device</div>
        <div class="card-body">
            <form method="POST" action="?page=isp_inventory&tab=cpe&action=save<?= $item ? '&id='.$item['id'] : '' ?>">
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label">Serial Number *</label><input type="text" name="serial_number" class="form-control" value="<?= htmlspecialchars($item['serial_number'] ?? '') ?>" required></div>
                    <div class="col-md-4"><label class="form-label">MAC Address</label><input type="text" name="mac_address" class="form-control" value="<?= htmlspecialchars($item['mac_address'] ?? '') ?>"></div>
                    <div class="col-md-4"><label class="form-label">Model</label><input type="text" name="model" class="form-control" value="<?= htmlspecialchars($item['model'] ?? '') ?>"></div>
                    <div class="col-md-4"><label class="form-label">Manufacturer</label><input type="text" name="manufacturer" class="form-control" value="<?= htmlspecialchars($item['manufacturer'] ?? '') ?>" placeholder="e.g., Huawei, ZTE"></div>
                    <div class="col-md-4"><label class="form-label">Firmware</label><input type="text" name="firmware_version" class="form-control" value="<?= htmlspecialchars($item['firmware_version'] ?? '') ?>"></div>
                    <div class="col-md-4">
                        <label class="form-label">OLT</label>
                        <select name="olt_id" class="form-select"><option value="">-- Select --</option>
                            <?php foreach ($olts as $o): ?><option value="<?= $o['id'] ?>" <?= ($item['olt_id'] ?? '') == $o['id'] ? 'selected' : '' ?>><?= htmlspecialchars($o['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3"><label class="form-label">OLT Port</label><input type="text" name="olt_port" class="form-control" value="<?= htmlspecialchars($item['olt_port'] ?? '') ?>" placeholder="0/0/0"></div>
                    <div class="col-md-3">
                        <label class="form-label">Splitter</label>
                        <select name="splitter_id" class="form-select"><option value="">-- Select --</option>
                            <?php foreach ($splitters as $sp): ?><option value="<?= $sp['id'] ?>" <?= ($item['splitter_id'] ?? '') == $sp['id'] ? 'selected' : '' ?>><?= htmlspecialchars($sp['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3"><label class="form-label">Splitter Port</label><input type="number" name="splitter_port" class="form-control" value="<?= $item['splitter_port'] ?? '' ?>"></div>
                    <div class="col-md-3"><label class="form-label">PPPoE Account</label><input type="text" name="pppoe_account" class="form-control" value="<?= htmlspecialchars($item['pppoe_account'] ?? '') ?>"></div>
                    <div class="col-md-4"><label class="form-label">Customer ID</label><input type="number" name="customer_id" class="form-control" value="<?= $item['customer_id'] ?? '' ?>" placeholder="CRM Customer ID"></div>
                    <div class="col-md-4"><label class="form-label">Installation Date</label><input type="date" name="installation_date" class="form-control" value="<?= $item['installation_date'] ?? '' ?>"></div>
                    <div class="col-md-4"><label class="form-label">Warranty Expiry</label><input type="date" name="warranty_expiry" class="form-control" value="<?= $item['warranty_expiry'] ?? '' ?>"></div>
                    <div class="col-md-4"><label class="form-label">Supplier</label><input type="text" name="supplier" class="form-control" value="<?= htmlspecialchars($item['supplier'] ?? '') ?>"></div>
                    <div class="col-md-4"><label class="form-label">Purchase Price</label><input type="number" step="0.01" name="purchase_price" class="form-control" value="<?= $item['purchase_price'] ?? '' ?>"></div>
                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <?php foreach (['in_stock'=>'In Stock','deployed'=>'Deployed','faulty'=>'Faulty','returned'=>'Returned','lost'=>'Lost','retired'=>'Retired'] as $k=>$v): ?>
                            <option value="<?= $k ?>" <?= ($item['status'] ?? 'in_stock') === $k ? 'selected' : '' ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"><?= htmlspecialchars($item['notes'] ?? '') ?></textarea></div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save</button>
                    <a href="?page=isp_inventory&tab=cpe" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    <?php else:
        $cpeStatus = $_GET['cpe_status'] ?? '';
        $cpeList = $ispInv->getCPEDevices(['status'=>$cpeStatus, 'search'=>$search]);
    ?>
    <div class="d-flex justify-content-between mb-3 flex-wrap gap-2">
        <form class="d-flex gap-2 flex-wrap" method="GET">
            <input type="hidden" name="page" value="isp_inventory"><input type="hidden" name="tab" value="cpe">
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Search serial/MAC/model..." value="<?= htmlspecialchars($search) ?>" style="width:250px;">
            <select name="cpe_status" class="form-select form-select-sm" style="width:140px;">
                <option value="">All Status</option>
                <?php foreach (['in_stock','deployed','faulty','returned','lost','retired'] as $st): ?>
                <option value="<?= $st ?>" <?= $cpeStatus === $st ? 'selected' : '' ?>><?= ucfirst(str_replace('_',' ',$st)) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-outline-primary btn-sm"><i class="bi bi-search"></i></button>
        </form>
        <a href="?page=isp_inventory&tab=cpe&action=form" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Add CPE</a>
    </div>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead><tr><th>Serial</th><th>Model</th><th>OLT</th><th>Port</th><th>Customer</th><th>PPPoE</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if (empty($cpeList)): ?>
                <tr><td colspan="8" class="text-center text-muted">No CPE devices found.</td></tr>
            <?php else: foreach ($cpeList as $cpe): ?>
                <tr>
                    <td><strong><code><?= htmlspecialchars($cpe['serial_number']) ?></code></strong></td>
                    <td><?= htmlspecialchars(($cpe['manufacturer'] ?? '') . ' ' . ($cpe['model'] ?? '')) ?></td>
                    <td><?= htmlspecialchars($cpe['olt_name'] ?? '') ?></td>
                    <td><?= htmlspecialchars($cpe['olt_port'] ?? '') ?></td>
                    <td><?= htmlspecialchars($cpe['customer_name'] ?? '') ?></td>
                    <td><small><?= htmlspecialchars($cpe['pppoe_account'] ?? '') ?></small></td>
                    <td><span class="badge bg-<?= $cpe['status']==='deployed'?'success':($cpe['status']==='in_stock'?'primary':($cpe['status']==='faulty'?'danger':'secondary')) ?>"><?= str_replace('_',' ',$cpe['status']) ?></span></td>
                    <td>
                        <a href="?page=isp_inventory&tab=cpe&action=form&id=<?= $cpe['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                        <form method="POST" action="?page=isp_inventory&tab=cpe&action=delete" class="d-inline" onsubmit="return confirm('Delete?')">
                            <input type="hidden" name="id" value="<?= $cpe['id'] ?>">
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
