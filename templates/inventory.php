<?php
$inventory = new \App\Inventory();
$stats = $inventory->getStats();
$categories = $inventory->getCategories();

$tab = $_GET['tab'] ?? 'overview';
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? null;

$successMessage = $_SESSION['success_message'] ?? null;
$errorMessage = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-box-seam"></i> Inventory Management</h2>
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

    <div class="row mb-4">
        <div class="col-12">
            <div class="btn-group flex-wrap mb-3" role="group">
                <a href="?page=inventory_warehouses" class="btn btn-outline-primary">
                    <i class="bi bi-building"></i> Warehouses
                </a>
                <a href="?page=stock_requests" class="btn btn-outline-success">
                    <i class="bi bi-box-arrow-up"></i> Stock Requests
                </a>
                <a href="?page=stock_returns" class="btn btn-outline-warning">
                    <i class="bi bi-arrow-return-left"></i> Returns & RMA
                </a>
            </div>
        </div>
    </div>

    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link <?= $tab === 'overview' ? 'active' : '' ?>" href="?page=inventory&tab=overview">
                <i class="bi bi-graph-up"></i> Overview
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $tab === 'equipment' ? 'active' : '' ?>" href="?page=inventory&tab=equipment">
                <i class="bi bi-router"></i> Equipment
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $tab === 'assignments' ? 'active' : '' ?>" href="?page=inventory&tab=assignments">
                <i class="bi bi-person-badge"></i> Employee Assignments
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $tab === 'loans' ? 'active' : '' ?>" href="?page=inventory&tab=loans">
                <i class="bi bi-arrow-left-right"></i> Customer Loans
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $tab === 'faults' ? 'active' : '' ?>" href="?page=inventory&tab=faults">
                <i class="bi bi-tools"></i> Faults & Repairs
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $tab === 'categories' ? 'active' : '' ?>" href="?page=inventory&tab=categories">
                <i class="bi bi-tags"></i> Categories
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $tab === 'kits' ? 'active' : '' ?>" href="?page=inventory&tab=kits">
                <i class="bi bi-briefcase"></i> Technician Kits
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $tab === 'thresholds' ? 'active' : '' ?>" href="?page=inventory&tab=thresholds">
                <i class="bi bi-sliders"></i> Thresholds
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $tab === 'reports' ? 'active' : '' ?>" href="?page=inventory&tab=reports">
                <i class="bi bi-file-earmark-bar-graph"></i> Reports
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $tab === 'import' ? 'active' : '' ?>" href="?page=inventory&tab=import">
                <i class="bi bi-upload"></i> Import/Export
            </a>
        </li>
    </ul>

    <?php if ($tab === 'overview'): ?>
    <!-- Overview Tab -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6 class="card-title"><i class="bi bi-box-seam"></i> Total Equipment</h6>
                    <h2 class="mb-0"><?= $stats['total_equipment'] ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="card-title"><i class="bi bi-check-circle"></i> Available</h6>
                    <h2 class="mb-0"><?= $stats['available'] ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6 class="card-title"><i class="bi bi-person-badge"></i> Assigned</h6>
                    <h2 class="mb-0"><?= $stats['assigned'] ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <h6 class="card-title"><i class="bi bi-arrow-left-right"></i> On Loan</h6>
                    <h2 class="mb-0"><?= $stats['on_loan'] ?></h2>
                </div>
            </div>
        </div>
    </div>
    <div class="row g-4">
        <div class="col-md-4">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h6 class="card-title"><i class="bi bi-exclamation-triangle"></i> Faulty Items</h6>
                    <h2 class="mb-0"><?= $stats['faulty'] ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-secondary text-white">
                <div class="card-body">
                    <h6 class="card-title"><i class="bi bi-wrench"></i> Pending Repairs</h6>
                    <h2 class="mb-0"><?= $stats['pending_repairs'] ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-dark text-white">
                <div class="card-body">
                    <h6 class="card-title"><i class="bi bi-currency-dollar"></i> Total Value</h6>
                    <h2 class="mb-0">KES <?= number_format($stats['total_value'], 2) ?></h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Low Stock Alerts -->
    <?php $lowStockItems = $inventory->getLowStockItems(); ?>
    <?php if (!empty($lowStockItems)): ?>
    <div class="card mt-4 border-warning">
        <div class="card-header bg-warning text-dark">
            <h5 class="mb-0"><i class="bi bi-exclamation-triangle-fill"></i> Low Stock Alerts (<?= count($lowStockItems) ?>)</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Current Stock</th>
                            <th>Min Quantity</th>
                            <th>Reorder Point</th>
                            <th>Suggested Order</th>
                            <th>Alert Level</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lowStockItems as $item): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($item['category_name']) ?></strong></td>
                            <td><?= $item['current_stock'] ?></td>
                            <td><?= $item['min_quantity'] ?></td>
                            <td><?= $item['reorder_point'] ?></td>
                            <td><?= $item['reorder_quantity'] ?></td>
                            <td>
                                <?php if ($item['alert_level'] === 'critical'): ?>
                                <span class="badge bg-danger">Critical</span>
                                <?php else: ?>
                                <span class="badge bg-warning text-dark">Low</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Stock Levels by Category -->
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-bar-chart"></i> Stock Levels by Category</h5>
        </div>
        <div class="card-body">
            <?php $stockLevels = $inventory->getStockLevelsByCategory(); ?>
            <?php if (empty($stockLevels)): ?>
            <p class="text-muted mb-0">No categories defined yet.</p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Total</th>
                            <th>Available</th>
                            <th>Assigned</th>
                            <th>On Loan</th>
                            <th>Faulty</th>
                            <th>Stock Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stockLevels as $level): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($level['category_name']) ?></strong></td>
                            <td><?= $level['total_count'] ?></td>
                            <td><span class="badge bg-success"><?= $level['available_count'] ?></span></td>
                            <td><span class="badge bg-info"><?= $level['assigned_count'] ?></span></td>
                            <td><span class="badge bg-warning text-dark"><?= $level['on_loan_count'] ?></span></td>
                            <td><span class="badge bg-danger"><?= $level['faulty_count'] ?></span></td>
                            <td>
                                <?php 
                                $available = $level['available_count'];
                                $minQty = $level['min_quantity'];
                                $reorderPt = $level['reorder_point'];
                                if ($minQty > 0 || $reorderPt > 0):
                                    if ($available <= $minQty): ?>
                                    <span class="badge bg-danger">Critical</span>
                                    <?php elseif ($available <= $reorderPt): ?>
                                    <span class="badge bg-warning text-dark">Low</span>
                                    <?php else: ?>
                                    <span class="badge bg-success">OK</span>
                                    <?php endif;
                                else: ?>
                                <span class="badge bg-secondary">No threshold</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php elseif ($tab === 'equipment'): ?>
    <!-- Equipment Tab -->
    <?php if ($action === 'add' || $action === 'edit'): ?>
        <?php 
        $equipment = $action === 'edit' && $id ? $inventory->getEquipmentById((int)$id) : null;
        ?>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><?= $action === 'edit' ? 'Edit Equipment' : 'Add New Equipment' ?></h5>
            </div>
            <div class="card-body">
                <form method="POST" action="?page=inventory&tab=equipment&action=save">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="id" value="<?= $equipment['id'] ?? '' ?>">
                    
                    <div class="row">
                        <div class="col-md-<?= $action === 'add' ? '4' : '6' ?> mb-3">
                            <label class="form-label">Name *</label>
                            <input type="text" class="form-control" name="name" required value="<?= htmlspecialchars($equipment['name'] ?? '') ?>">
                        </div>
                        <?php if ($action === 'add'): ?>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Quantity</label>
                            <input type="number" class="form-control" name="quantity" value="1" min="1" max="500">
                            <small class="text-muted">Add multiple units</small>
                        </div>
                        <?php endif; ?>
                        <div class="col-md-<?= $action === 'add' ? '6' : '6' ?> mb-3">
                            <label class="form-label">Category</label>
                            <select class="form-select" name="category_id">
                                <option value="">-- Select Category --</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= ($equipment['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Brand</label>
                            <input type="text" class="form-control" name="brand" value="<?= htmlspecialchars($equipment['brand'] ?? '') ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Model</label>
                            <input type="text" class="form-control" name="model" value="<?= htmlspecialchars($equipment['model'] ?? '') ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Serial Number</label>
                            <input type="text" class="form-control" name="serial_number" value="<?= htmlspecialchars($equipment['serial_number'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">MAC Address</label>
                            <input type="text" class="form-control" name="mac_address" value="<?= htmlspecialchars($equipment['mac_address'] ?? '') ?>" placeholder="AA:BB:CC:DD:EE:FF">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Location</label>
                            <input type="text" class="form-control" name="location" value="<?= htmlspecialchars($equipment['location'] ?? '') ?>" placeholder="e.g., Warehouse, Office">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Purchase Date</label>
                            <input type="date" class="form-control" name="purchase_date" value="<?= $equipment['purchase_date'] ?? '' ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Purchase Price (KES)</label>
                            <input type="number" step="0.01" class="form-control" name="purchase_price" value="<?= $equipment['purchase_price'] ?? '' ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Warranty Expiry</label>
                            <input type="date" class="form-control" name="warranty_expiry" value="<?= $equipment['warranty_expiry'] ?? '' ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Condition</label>
                            <select class="form-select" name="condition">
                                <option value="new" <?= ($equipment['condition'] ?? 'new') === 'new' ? 'selected' : '' ?>>New</option>
                                <option value="good" <?= ($equipment['condition'] ?? '') === 'good' ? 'selected' : '' ?>>Good</option>
                                <option value="fair" <?= ($equipment['condition'] ?? '') === 'fair' ? 'selected' : '' ?>>Fair</option>
                                <option value="poor" <?= ($equipment['condition'] ?? '') === 'poor' ? 'selected' : '' ?>>Poor</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="available" <?= ($equipment['status'] ?? 'available') === 'available' ? 'selected' : '' ?>>Available</option>
                                <option value="assigned" <?= ($equipment['status'] ?? '') === 'assigned' ? 'selected' : '' ?>>Assigned</option>
                                <option value="on_loan" <?= ($equipment['status'] ?? '') === 'on_loan' ? 'selected' : '' ?>>On Loan</option>
                                <option value="faulty" <?= ($equipment['status'] ?? '') === 'faulty' ? 'selected' : '' ?>>Faulty</option>
                                <option value="retired" <?= ($equipment['status'] ?? '') === 'retired' ? 'selected' : '' ?>>Retired</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="3"><?= htmlspecialchars($equipment['notes'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save Equipment</button>
                        <a href="?page=inventory&tab=equipment" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    <?php elseif ($action === 'view' && $id): ?>
        <?php 
        $equipment = $inventory->getEquipmentById((int)$id);
        $history = $inventory->getEquipmentHistory((int)$id);
        ?>
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Equipment Details</h5>
                <div>
                    <a href="?page=inventory&tab=equipment&action=edit&id=<?= $id ?>" class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i> Edit</a>
                    <a href="?page=inventory&tab=equipment" class="btn btn-sm btn-secondary">Back</a>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr><th width="150">Name:</th><td><?= htmlspecialchars($equipment['name']) ?></td></tr>
                            <tr><th>Category:</th><td><?= htmlspecialchars($equipment['category_name'] ?? 'N/A') ?></td></tr>
                            <tr><th>Brand:</th><td><?= htmlspecialchars($equipment['brand'] ?? 'N/A') ?></td></tr>
                            <tr><th>Model:</th><td><?= htmlspecialchars($equipment['model'] ?? 'N/A') ?></td></tr>
                            <tr><th>Serial Number:</th><td><code><?= htmlspecialchars($equipment['serial_number'] ?? 'N/A') ?></code></td></tr>
                            <tr><th>MAC Address:</th><td><code><?= htmlspecialchars($equipment['mac_address'] ?? 'N/A') ?></code></td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr><th width="150">Status:</th><td><span class="badge bg-<?= $equipment['status'] === 'available' ? 'success' : ($equipment['status'] === 'faulty' ? 'danger' : 'warning') ?>"><?= ucfirst($equipment['status']) ?></span></td></tr>
                            <tr><th>Condition:</th><td><?= ucfirst($equipment['condition']) ?></td></tr>
                            <tr><th>Location:</th><td><?= htmlspecialchars($equipment['location'] ?? 'N/A') ?></td></tr>
                            <tr><th>Purchase Date:</th><td><?= $equipment['purchase_date'] ?? 'N/A' ?></td></tr>
                            <tr><th>Purchase Price:</th><td><?= $equipment['purchase_price'] ? 'KES ' . number_format($equipment['purchase_price'], 2) : 'N/A' ?></td></tr>
                            <tr><th>Warranty Expiry:</th><td><?= $equipment['warranty_expiry'] ?? 'N/A' ?></td></tr>
                        </table>
                    </div>
                </div>
                <?php if (!empty($equipment['notes'])): ?>
                <div class="mt-3">
                    <strong>Notes:</strong>
                    <p class="mb-0"><?= nl2br(htmlspecialchars($equipment['notes'])) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-clock-history"></i> Equipment History</h5>
            </div>
            <div class="card-body">
                <?php if (empty($history)): ?>
                <p class="text-muted mb-0">No history available for this equipment.</p>
                <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Date</th>
                            <th>Details</th>
                            <th>Return Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $entry): ?>
                        <tr>
                            <td>
                                <?php if ($entry['type'] === 'assignment'): ?>
                                <span class="badge bg-info">Assignment</span>
                                <?php elseif ($entry['type'] === 'loan'): ?>
                                <span class="badge bg-warning">Loan</span>
                                <?php else: ?>
                                <span class="badge bg-danger">Fault</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $entry['date'] ?></td>
                            <td><?= htmlspecialchars($entry['to_name']) ?></td>
                            <td><?= $entry['return_date'] ?? '-' ?></td>
                            <td><?= ucfirst($entry['status']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Equipment List</h5>
                <div class="btn-group">
                    <a href="?page=inventory&tab=import&action=download_template" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-file-earmark-arrow-down"></i> Template
                    </a>
                    <button type="button" class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#importModal">
                        <i class="bi bi-upload"></i> Import
                    </button>
                    <a href="?page=inventory&tab=import&action=export" class="btn btn-outline-info btn-sm">
                        <i class="bi bi-download"></i> Export
                    </a>
                    <a href="?page=inventory&tab=equipment&action=add" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-lg"></i> Add
                    </a>
                </div>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3 mb-4">
                    <input type="hidden" name="page" value="inventory">
                    <input type="hidden" name="tab" value="equipment">
                    <div class="col-md-3">
                        <input type="text" class="form-control" name="search" placeholder="Search..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="category_id">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= ($_GET['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="status">
                            <option value="">All Status</option>
                            <option value="available" <?= ($_GET['status'] ?? '') === 'available' ? 'selected' : '' ?>>Available</option>
                            <option value="assigned" <?= ($_GET['status'] ?? '') === 'assigned' ? 'selected' : '' ?>>Assigned</option>
                            <option value="on_loan" <?= ($_GET['status'] ?? '') === 'on_loan' ? 'selected' : '' ?>>On Loan</option>
                            <option value="faulty" <?= ($_GET['status'] ?? '') === 'faulty' ? 'selected' : '' ?>>Faulty</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-secondary w-100"><i class="bi bi-search"></i> Filter</button>
                    </div>
                </form>
                
                <?php 
                $filters = [];
                if (!empty($_GET['search'])) $filters['search'] = $_GET['search'];
                if (!empty($_GET['category_id'])) $filters['category_id'] = $_GET['category_id'];
                if (!empty($_GET['status'])) $filters['status'] = $_GET['status'];
                $equipmentList = $inventory->getEquipment($filters);
                ?>
                
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Brand/Model</th>
                                <th>Serial Number</th>
                                <th>Condition</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($equipmentList)): ?>
                            <tr><td colspan="7" class="text-center text-muted">No equipment found</td></tr>
                            <?php else: ?>
                            <?php foreach ($equipmentList as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['name']) ?></td>
                                <td><?= htmlspecialchars($item['category_name'] ?? '-') ?></td>
                                <td><?= htmlspecialchars(trim(($item['brand'] ?? '') . ' ' . ($item['model'] ?? ''))) ?: '-' ?></td>
                                <td><code><?= htmlspecialchars($item['serial_number'] ?? '-') ?></code></td>
                                <td><?= ucfirst($item['condition']) ?></td>
                                <td>
                                    <span class="badge bg-<?= $item['status'] === 'available' ? 'success' : ($item['status'] === 'faulty' ? 'danger' : ($item['status'] === 'retired' ? 'secondary' : 'warning')) ?>">
                                        <?= ucfirst(str_replace('_', ' ', $item['status'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="?page=inventory&tab=equipment&action=view&id=<?= $item['id'] ?>" class="btn btn-outline-primary" title="View"><i class="bi bi-eye"></i></a>
                                        <a href="?page=inventory&tab=equipment&action=edit&id=<?= $item['id'] ?>" class="btn btn-outline-warning" title="Edit"><i class="bi bi-pencil"></i></a>
                                        <?php if ($item['status'] === 'available'): ?>
                                        <a href="?page=inventory&tab=assignments&action=add&equipment_id=<?= $item['id'] ?>" class="btn btn-outline-info" title="Assign to Employee"><i class="bi bi-person-plus"></i></a>
                                        <a href="?page=inventory&tab=loans&action=add&equipment_id=<?= $item['id'] ?>" class="btn btn-outline-success" title="Loan to Customer"><i class="bi bi-box-arrow-right"></i></a>
                                        <?php endif; ?>
                                        <a href="?page=inventory&tab=faults&action=add&equipment_id=<?= $item['id'] ?>" class="btn btn-outline-danger" title="Report Fault"><i class="bi bi-exclamation-triangle"></i></a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Import Modal -->
    <div class="modal fade" id="importModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="bi bi-upload"></i> Import Equipment</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="?page=inventory&tab=import&action=import" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        
                        <div class="alert alert-info py-2">
                            <small><i class="bi bi-info-circle"></i> Download the <a href="?page=inventory&tab=import&action=download_template">import template</a> first to see the correct format.</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Select Excel or CSV File</label>
                            <input type="file" class="form-control" name="import_file" accept=".xlsx,.xls,.csv" required>
                            <div class="form-text">Supported: .xlsx, .xls, .csv (max 10MB)</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success"><i class="bi bi-upload"></i> Import</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php elseif ($tab === 'assignments'): ?>
    <!-- Assignments Tab -->
    <?php 
    $employee = new \App\Employee($db);
    $employees = $employee->getEmployees();
    ?>
    <?php if ($action === 'add'): ?>
        <?php 
        $preselectedEquipment = $_GET['equipment_id'] ?? null;
        $availableEquipment = $inventory->getAvailableEquipment();
        ?>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Assign Equipment to Employee</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="?page=inventory&tab=assignments&action=assign">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Equipment *</label>
                            <select class="form-select" name="equipment_id" required>
                                <option value="">-- Select Equipment --</option>
                                <?php foreach ($availableEquipment as $eq): ?>
                                <option value="<?= $eq['id'] ?>" <?= $preselectedEquipment == $eq['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($eq['name']) ?> 
                                    <?= $eq['serial_number'] ? '(' . $eq['serial_number'] . ')' : '' ?>
                                    - <?= htmlspecialchars($eq['category_name'] ?? '') ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Employee *</label>
                            <select class="form-select" name="employee_id" required>
                                <option value="">-- Select Employee --</option>
                                <?php foreach ($employees as $emp): ?>
                                <option value="<?= $emp['id'] ?>">
                                    <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?>
                                    <?= $emp['department_name'] ? '(' . $emp['department_name'] . ')' : '' ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Assignment Date</label>
                            <input type="date" class="form-control" name="assignment_date" value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="3"></textarea>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Assign Equipment</button>
                        <a href="?page=inventory&tab=assignments" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Equipment Assignments</h5>
                <a href="?page=inventory&tab=assignments&action=add" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg"></i> New Assignment
                </a>
            </div>
            <div class="card-body">
                <?php 
                $showActive = !isset($_GET['show_all']);
                $assignments = $inventory->getAssignments(['active_only' => $showActive]);
                ?>
                <div class="mb-3">
                    <a href="?page=inventory&tab=assignments<?= $showActive ? '&show_all=1' : '' ?>" class="btn btn-sm btn-outline-secondary">
                        <?= $showActive ? 'Show All Assignments' : 'Show Active Only' ?>
                    </a>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Equipment</th>
                                <th>Serial Number</th>
                                <th>Employee</th>
                                <th>Assigned Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($assignments)): ?>
                            <tr><td colspan="6" class="text-center text-muted">No assignments found</td></tr>
                            <?php else: ?>
                            <?php foreach ($assignments as $assign): ?>
                            <tr>
                                <td><?= htmlspecialchars($assign['equipment_name']) ?></td>
                                <td><code><?= htmlspecialchars($assign['serial_number'] ?? '-') ?></code></td>
                                <td><?= htmlspecialchars($assign['first_name'] . ' ' . $assign['last_name']) ?></td>
                                <td><?= $assign['assignment_date'] ?></td>
                                <td>
                                    <span class="badge bg-<?= $assign['status'] === 'assigned' ? 'success' : 'secondary' ?>">
                                        <?= ucfirst($assign['status']) ?>
                                    </span>
                                    <?php if ($assign['return_date']): ?>
                                    <small class="text-muted d-block">Returned: <?= $assign['return_date'] ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($assign['status'] === 'assigned'): ?>
                                    <form method="POST" action="?page=inventory&tab=assignments&action=return" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                        <input type="hidden" name="assignment_id" value="<?= $assign['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Mark equipment as returned?')">
                                            <i class="bi bi-box-arrow-in-left"></i> Return
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php elseif ($tab === 'loans'): ?>
    <!-- Loans Tab -->
    <?php 
    $customer = new \App\Customer($db);
    $customers = $customer->getAll();
    ?>
    <?php if ($action === 'add'): ?>
        <?php 
        $preselectedEquipment = $_GET['equipment_id'] ?? null;
        $availableEquipment = $inventory->getAvailableEquipment();
        ?>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Loan Equipment to Customer</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="?page=inventory&tab=loans&action=loan">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Equipment *</label>
                            <select class="form-select" name="equipment_id" required>
                                <option value="">-- Select Equipment --</option>
                                <?php foreach ($availableEquipment as $eq): ?>
                                <option value="<?= $eq['id'] ?>" <?= $preselectedEquipment == $eq['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($eq['name']) ?> 
                                    <?= $eq['serial_number'] ? '(' . $eq['serial_number'] . ')' : '' ?>
                                    - <?= htmlspecialchars($eq['category_name'] ?? '') ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Customer *</label>
                            <select class="form-select" name="customer_id" required>
                                <option value="">-- Select Customer --</option>
                                <?php foreach ($customers as $cust): ?>
                                <option value="<?= $cust['id'] ?>">
                                    <?= htmlspecialchars($cust['name']) ?> - <?= htmlspecialchars($cust['phone']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Loan Date</label>
                            <input type="date" class="form-control" name="loan_date" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Expected Return Date</label>
                            <input type="date" class="form-control" name="expected_return_date">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Deposit Amount (KES)</label>
                            <input type="number" step="0.01" class="form-control" name="deposit_amount" value="0">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="deposit_paid" id="deposit_paid">
                            <label class="form-check-label" for="deposit_paid">Deposit Paid</label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="3"></textarea>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Create Loan</button>
                        <a href="?page=inventory&tab=loans" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Equipment Loans to Customers</h5>
                <a href="?page=inventory&tab=loans&action=add" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg"></i> New Loan
                </a>
            </div>
            <div class="card-body">
                <?php 
                $showActive = !isset($_GET['show_all']);
                $loans = $inventory->getLoans(['active_only' => $showActive]);
                ?>
                <div class="mb-3">
                    <a href="?page=inventory&tab=loans<?= $showActive ? '&show_all=1' : '' ?>" class="btn btn-sm btn-outline-secondary">
                        <?= $showActive ? 'Show All Loans' : 'Show Active Only' ?>
                    </a>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Equipment</th>
                                <th>Serial Number</th>
                                <th>Customer</th>
                                <th>Loan Date</th>
                                <th>Expected Return</th>
                                <th>Deposit</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($loans)): ?>
                            <tr><td colspan="8" class="text-center text-muted">No loans found</td></tr>
                            <?php else: ?>
                            <?php foreach ($loans as $loan): ?>
                            <tr>
                                <td><?= htmlspecialchars($loan['equipment_name']) ?></td>
                                <td><code><?= htmlspecialchars($loan['serial_number'] ?? '-') ?></code></td>
                                <td>
                                    <?= htmlspecialchars($loan['customer_name']) ?>
                                    <small class="text-muted d-block"><?= htmlspecialchars($loan['customer_phone']) ?></small>
                                </td>
                                <td><?= $loan['loan_date'] ?></td>
                                <td><?= $loan['expected_return_date'] ?? '-' ?></td>
                                <td>
                                    <?php if ($loan['deposit_amount'] > 0): ?>
                                    KES <?= number_format($loan['deposit_amount'], 2) ?>
                                    <span class="badge bg-<?= $loan['deposit_paid'] ? 'success' : 'warning' ?>"><?= $loan['deposit_paid'] ? 'Paid' : 'Pending' ?></span>
                                    <?php else: ?>
                                    -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $loan['status'] === 'on_loan' ? 'warning' : 'success' ?>">
                                        <?= $loan['status'] === 'on_loan' ? 'On Loan' : 'Returned' ?>
                                    </span>
                                    <?php if ($loan['actual_return_date']): ?>
                                    <small class="text-muted d-block">Returned: <?= $loan['actual_return_date'] ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($loan['status'] === 'on_loan'): ?>
                                    <form method="POST" action="?page=inventory&tab=loans&action=return" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                        <input type="hidden" name="loan_id" value="<?= $loan['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Mark equipment as returned?')">
                                            <i class="bi bi-box-arrow-in-left"></i> Return
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php elseif ($tab === 'faults'): ?>
    <!-- Faults Tab -->
    <?php if ($action === 'add'): ?>
        <?php 
        $preselectedEquipment = $_GET['equipment_id'] ?? null;
        $allEquipment = $inventory->getEquipment();
        ?>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Report Equipment Fault</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="?page=inventory&tab=faults&action=report">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Equipment *</label>
                            <select class="form-select" name="equipment_id" required>
                                <option value="">-- Select Equipment --</option>
                                <?php foreach ($allEquipment as $eq): ?>
                                <option value="<?= $eq['id'] ?>" <?= $preselectedEquipment == $eq['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($eq['name']) ?> 
                                    <?= $eq['serial_number'] ? '(' . $eq['serial_number'] . ')' : '' ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Reported Date</label>
                            <input type="date" class="form-control" name="reported_date" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Severity</label>
                            <select class="form-select" name="severity">
                                <option value="minor">Minor</option>
                                <option value="moderate">Moderate</option>
                                <option value="major">Major</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Fault Description *</label>
                        <textarea class="form-control" name="fault_description" rows="4" required placeholder="Describe the fault in detail..."></textarea>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-danger"><i class="bi bi-exclamation-triangle"></i> Report Fault</button>
                        <a href="?page=inventory&tab=faults" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    <?php elseif ($action === 'repair' && $id): ?>
        <?php $fault = $inventory->getFault((int)$id); ?>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Mark as Repaired</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <strong>Equipment:</strong> <?= htmlspecialchars($fault['equipment_name']) ?> 
                    (<?= htmlspecialchars($fault['serial_number'] ?? 'No S/N') ?>)<br>
                    <strong>Fault:</strong> <?= htmlspecialchars($fault['fault_description']) ?>
                </div>
                
                <form method="POST" action="?page=inventory&tab=faults&action=mark_repaired">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="fault_id" value="<?= $fault['id'] ?>">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Repair Date</label>
                            <input type="date" class="form-control" name="repair_date" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Repair Cost (KES)</label>
                            <input type="number" step="0.01" class="form-control" name="repair_cost">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Repair Notes</label>
                        <textarea class="form-control" name="repair_notes" rows="3" placeholder="What was done to fix the issue..."></textarea>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-success"><i class="bi bi-check-lg"></i> Mark as Repaired</button>
                        <a href="?page=inventory&tab=faults" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Equipment Faults & Repairs</h5>
                <a href="?page=inventory&tab=faults&action=add" class="btn btn-danger btn-sm">
                    <i class="bi bi-exclamation-triangle"></i> Report Fault
                </a>
            </div>
            <div class="card-body">
                <?php 
                $statusFilter = $_GET['repair_status'] ?? '';
                $faults = $inventory->getFaults($statusFilter ? ['repair_status' => $statusFilter] : []);
                ?>
                <div class="mb-3">
                    <div class="btn-group btn-group-sm">
                        <a href="?page=inventory&tab=faults" class="btn btn-outline-secondary <?= !$statusFilter ? 'active' : '' ?>">All</a>
                        <a href="?page=inventory&tab=faults&repair_status=pending" class="btn btn-outline-warning <?= $statusFilter === 'pending' ? 'active' : '' ?>">Pending</a>
                        <a href="?page=inventory&tab=faults&repair_status=in_progress" class="btn btn-outline-info <?= $statusFilter === 'in_progress' ? 'active' : '' ?>">In Progress</a>
                        <a href="?page=inventory&tab=faults&repair_status=repaired" class="btn btn-outline-success <?= $statusFilter === 'repaired' ? 'active' : '' ?>">Repaired</a>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Equipment</th>
                                <th>Serial Number</th>
                                <th>Reported</th>
                                <th>Severity</th>
                                <th>Fault Description</th>
                                <th>Status</th>
                                <th>Repair Cost</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($faults)): ?>
                            <tr><td colspan="8" class="text-center text-muted">No faults found</td></tr>
                            <?php else: ?>
                            <?php foreach ($faults as $fault): ?>
                            <tr>
                                <td><?= htmlspecialchars($fault['equipment_name']) ?></td>
                                <td><code><?= htmlspecialchars($fault['serial_number'] ?? '-') ?></code></td>
                                <td><?= $fault['reported_date'] ?></td>
                                <td>
                                    <span class="badge bg-<?= $fault['severity'] === 'critical' ? 'danger' : ($fault['severity'] === 'major' ? 'warning' : ($fault['severity'] === 'moderate' ? 'info' : 'secondary')) ?>">
                                        <?= ucfirst($fault['severity']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars(substr($fault['fault_description'], 0, 50)) ?>...</td>
                                <td>
                                    <span class="badge bg-<?= $fault['repair_status'] === 'repaired' ? 'success' : ($fault['repair_status'] === 'in_progress' ? 'info' : 'warning') ?>">
                                        <?= ucfirst(str_replace('_', ' ', $fault['repair_status'])) ?>
                                    </span>
                                    <?php if ($fault['repair_date']): ?>
                                    <small class="text-muted d-block">Repaired: <?= $fault['repair_date'] ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= $fault['repair_cost'] ? 'KES ' . number_format($fault['repair_cost'], 2) : '-' ?></td>
                                <td>
                                    <?php if ($fault['repair_status'] !== 'repaired'): ?>
                                    <a href="?page=inventory&tab=faults&action=repair&id=<?= $fault['id'] ?>" class="btn btn-sm btn-success">
                                        <i class="bi bi-check-lg"></i> Repaired
                                    </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php elseif ($tab === 'categories'): ?>
    <!-- Categories Tab -->
    <?php if ($action === 'add' || $action === 'edit'): ?>
        <?php $category = $action === 'edit' && $id ? $inventory->getCategory((int)$id) : null; ?>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><?= $action === 'edit' ? 'Edit Category' : 'Add New Category' ?></h5>
            </div>
            <div class="card-body">
                <form method="POST" action="?page=inventory&tab=categories&action=save">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="id" value="<?= $category['id'] ?? '' ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Category Name *</label>
                        <input type="text" class="form-control" name="name" required value="<?= htmlspecialchars($category['name'] ?? '') ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3"><?= htmlspecialchars($category['description'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save Category</button>
                        <a href="?page=inventory&tab=categories" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Equipment Categories</h5>
                <a href="?page=inventory&tab=categories&action=add" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg"></i> Add Category
                </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $cat): ?>
                            <tr>
                                <td><?= htmlspecialchars($cat['name']) ?></td>
                                <td><?= htmlspecialchars($cat['description'] ?? '-') ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="?page=inventory&tab=categories&action=edit&id=<?= $cat['id'] ?>" class="btn btn-outline-warning"><i class="bi bi-pencil"></i></a>
                                        <form method="POST" action="?page=inventory&tab=categories&action=delete" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                                            <button type="submit" class="btn btn-outline-danger" onclick="return confirm('Delete this category?')"><i class="bi bi-trash"></i></button>
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
    <?php endif; ?>
    
    <?php elseif ($tab === 'import'): ?>
    <!-- Import/Export Tab -->
    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-upload"></i> Import Equipment</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">Import multiple equipment items from an Excel or CSV file.</p>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> 
                        <strong>Before importing:</strong>
                        <ol class="mb-0 mt-2">
                            <li>Download the template file below</li>
                            <li>Fill in your equipment data (Name is required)</li>
                            <li>Make sure category names match existing categories</li>
                            <li>Save and upload the file</li>
                        </ol>
                    </div>
                    
                    <div class="mb-4">
                        <a href="?page=inventory&tab=import&action=download_template" class="btn btn-outline-primary">
                            <i class="bi bi-download"></i> Download Import Template
                        </a>
                    </div>
                    
                    <form method="POST" action="?page=inventory&tab=import&action=import" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Select File</label>
                            <input type="file" class="form-control" name="import_file" accept=".xlsx,.xls,.csv" required>
                            <div class="form-text">Supported formats: Excel (.xlsx, .xls) and CSV (.csv)</div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-upload"></i> Import Equipment
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-download"></i> Export Equipment</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">Export all equipment to an Excel file for backup or analysis.</p>
                    
                    <div class="mb-3">
                        <label class="form-label">Filter by Status</label>
                        <select class="form-select" id="exportStatus">
                            <option value="">All Equipment</option>
                            <option value="available">Available Only</option>
                            <option value="assigned">Assigned Only</option>
                            <option value="loaned">On Loan Only</option>
                            <option value="faulty">Faulty Only</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Filter by Category</label>
                        <select class="form-select" id="exportCategory">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <a href="?page=inventory&tab=import&action=export" class="btn btn-success" id="exportBtn">
                        <i class="bi bi-file-earmark-excel"></i> Export to Excel
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Bulk Add Equipment</h5>
        </div>
        <div class="card-body">
            <p class="text-muted">Quickly add multiple equipment items manually.</p>
            
            <form method="POST" action="?page=inventory&tab=import&action=bulk_add" id="bulkAddForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                
                <div class="table-responsive">
                    <table class="table table-bordered" id="bulkAddTable">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 15%">Name <span class="text-danger">*</span></th>
                                <th style="width: 12%">Category</th>
                                <th style="width: 10%">Brand</th>
                                <th style="width: 10%">Model</th>
                                <th style="width: 13%">Serial Number</th>
                                <th style="width: 13%">MAC Address</th>
                                <th style="width: 10%">Price</th>
                                <th style="width: 10%">Location</th>
                                <th style="width: 7%">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="bulk-row">
                                <td><input type="text" class="form-control form-control-sm" name="items[0][name]" required></td>
                                <td>
                                    <select class="form-select form-select-sm" name="items[0][category]">
                                        <option value="">-</option>
                                        <?php foreach ($categories as $cat): ?>
                                        <option value="<?= htmlspecialchars($cat['name']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td><input type="text" class="form-control form-control-sm" name="items[0][brand]"></td>
                                <td><input type="text" class="form-control form-control-sm" name="items[0][model]"></td>
                                <td><input type="text" class="form-control form-control-sm" name="items[0][serial_number]"></td>
                                <td><input type="text" class="form-control form-control-sm" name="items[0][mac_address]"></td>
                                <td><input type="number" step="0.01" class="form-control form-control-sm" name="items[0][purchase_price]"></td>
                                <td><input type="text" class="form-control form-control-sm" name="items[0][location]"></td>
                                <td><button type="button" class="btn btn-sm btn-outline-danger remove-row" disabled><i class="bi bi-x"></i></button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <div class="d-flex gap-2 mt-3">
                    <button type="button" class="btn btn-outline-secondary" id="addRowBtn">
                        <i class="bi bi-plus"></i> Add Row
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Save All Equipment
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        let rowCount = 1;
        const table = document.getElementById('bulkAddTable').getElementsByTagName('tbody')[0];
        const categories = <?= json_encode(array_map(fn($c) => $c['name'], $categories)) ?>;
        
        document.getElementById('addRowBtn').addEventListener('click', function() {
            const categoryOptions = categories.map(c => `<option value="${c}">${c}</option>`).join('');
            const newRow = document.createElement('tr');
            newRow.className = 'bulk-row';
            newRow.innerHTML = `
                <td><input type="text" class="form-control form-control-sm" name="items[${rowCount}][name]" required></td>
                <td>
                    <select class="form-select form-select-sm" name="items[${rowCount}][category]">
                        <option value="">-</option>
                        ${categoryOptions}
                    </select>
                </td>
                <td><input type="text" class="form-control form-control-sm" name="items[${rowCount}][brand]"></td>
                <td><input type="text" class="form-control form-control-sm" name="items[${rowCount}][model]"></td>
                <td><input type="text" class="form-control form-control-sm" name="items[${rowCount}][serial_number]"></td>
                <td><input type="text" class="form-control form-control-sm" name="items[${rowCount}][mac_address]"></td>
                <td><input type="number" step="0.01" class="form-control form-control-sm" name="items[${rowCount}][purchase_price]"></td>
                <td><input type="text" class="form-control form-control-sm" name="items[${rowCount}][location]"></td>
                <td><button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="bi bi-x"></i></button></td>
            `;
            table.appendChild(newRow);
            rowCount++;
            updateRemoveButtons();
        });
        
        table.addEventListener('click', function(e) {
            if (e.target.closest('.remove-row')) {
                e.target.closest('tr').remove();
                updateRemoveButtons();
            }
        });
        
        function updateRemoveButtons() {
            const rows = table.querySelectorAll('.bulk-row');
            rows.forEach((row, index) => {
                const btn = row.querySelector('.remove-row');
                btn.disabled = rows.length === 1;
            });
        }
        
        // Export link update
        const exportBtn = document.getElementById('exportBtn');
        const statusSelect = document.getElementById('exportStatus');
        const categorySelect = document.getElementById('exportCategory');
        
        function updateExportLink() {
            let url = '?page=inventory&tab=import&action=export';
            if (statusSelect.value) url += '&status=' + statusSelect.value;
            if (categorySelect.value) url += '&category_id=' + categorySelect.value;
            exportBtn.href = url;
        }
        
        statusSelect.addEventListener('change', updateExportLink);
        categorySelect.addEventListener('change', updateExportLink);
    });
    </script>

    <?php elseif ($tab === 'kits'): ?>
    <!-- Technician Kits Tab -->
    <?php if ($action === 'add' || $action === 'edit'): ?>
        <?php 
        $kit = $action === 'edit' && $id ? $inventory->getTechnicianKit((int)$id) : null;
        $db = \Database::getConnection();
        $employees = $db->query("SELECT id, name FROM employees WHERE employment_status = 'active' ORDER BY name")->fetchAll(\PDO::FETCH_ASSOC);
        ?>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><?= $action === 'edit' ? 'Edit Technician Kit' : 'Create New Kit' ?></h5>
            </div>
            <div class="card-body">
                <form method="POST" action="?page=inventory&tab=kits&action=save">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="id" value="<?= $kit['id'] ?? '' ?>">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Kit Name *</label>
                            <input type="text" class="form-control" name="kit_name" required value="<?= htmlspecialchars($kit['kit_name'] ?? '') ?>" placeholder="e.g., Field Kit #1">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Assign to Technician</label>
                            <select class="form-select" name="technician_id">
                                <option value="">-- Unassigned --</option>
                                <?php foreach ($employees as $emp): ?>
                                <option value="<?= $emp['id'] ?>" <?= ($kit['technician_id'] ?? '') == $emp['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($emp['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Issue Date</label>
                            <input type="date" class="form-control" name="issued_at" value="<?= $kit['issued_at'] ?? date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="active" <?= ($kit['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="returned" <?= ($kit['status'] ?? '') === 'returned' ? 'selected' : '' ?>>Returned</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="2"><?= htmlspecialchars($kit['notes'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save Kit</button>
                        <a href="?page=inventory&tab=kits" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    <?php elseif ($action === 'view' && $id): ?>
        <?php 
        $kit = $inventory->getTechnicianKit((int)$id);
        $kitItems = $inventory->getKitItems((int)$id);
        $availableEquipment = $inventory->getAvailableEquipment();
        ?>
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-briefcase"></i> <?= htmlspecialchars($kit['kit_name']) ?></h5>
                <div>
                    <a href="?page=inventory&tab=kits&action=edit&id=<?= $id ?>" class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i> Edit</a>
                    <a href="?page=inventory&tab=kits" class="btn btn-sm btn-secondary">Back</a>
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p><strong>Technician:</strong> <?= htmlspecialchars($kit['technician_name'] ?? 'Unassigned') ?></p>
                        <p><strong>Issue Date:</strong> <?= $kit['issued_at'] ?? 'N/A' ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Status:</strong> 
                            <span class="badge bg-<?= $kit['status'] === 'active' ? 'success' : 'secondary' ?>">
                                <?= ucfirst($kit['status']) ?>
                            </span>
                        </p>
                        <p><strong>Items:</strong> <?= count($kitItems) ?></p>
                    </div>
                </div>
                
                <?php if ($kit['notes']): ?>
                <p><strong>Notes:</strong> <?= nl2br(htmlspecialchars($kit['notes'])) ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Kit Items -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-box-seam"></i> Kit Contents</h5>
            </div>
            <div class="card-body">
                <?php if (empty($kitItems)): ?>
                <p class="text-muted">No items in this kit yet.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Equipment</th>
                                <th>Category</th>
                                <th>Serial Number</th>
                                <th>Quantity</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($kitItems as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['equipment_name']) ?></td>
                                <td><?= htmlspecialchars($item['category_name'] ?? '-') ?></td>
                                <td><code><?= htmlspecialchars($item['serial_number'] ?? '-') ?></code></td>
                                <td><?= $item['quantity'] ?></td>
                                <td>
                                    <form method="POST" action="?page=inventory&tab=kits&action=remove_item" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                        <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                        <input type="hidden" name="kit_id" value="<?= $id ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove this item from the kit?');">
                                            <i class="bi bi-x"></i> Remove
                                        </button>
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
        
        <!-- Add Item to Kit -->
        <?php if ($kit['status'] === 'active' && !empty($availableEquipment)): ?>
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Add Item to Kit</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="?page=inventory&tab=kits&action=add_item" class="row g-3">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="kit_id" value="<?= $id ?>">
                    
                    <div class="col-md-6">
                        <label class="form-label">Equipment</label>
                        <select class="form-select" name="equipment_id" required>
                            <option value="">-- Select Equipment --</option>
                            <?php foreach ($availableEquipment as $eq): ?>
                            <option value="<?= $eq['id'] ?>">
                                <?= htmlspecialchars($eq['name']) ?> 
                                <?php if ($eq['serial_number']): ?>(<?= $eq['serial_number'] ?>)<?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Quantity</label>
                        <input type="number" class="form-control" name="quantity" value="1" min="1">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-success w-100"><i class="bi bi-plus"></i> Add</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Technician Kits</h5>
                <a href="?page=inventory&tab=kits&action=add" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg"></i> Create Kit
                </a>
            </div>
            <div class="card-body">
                <?php $kits = $inventory->getTechnicianKits(); ?>
                <?php if (empty($kits)): ?>
                <p class="text-muted text-center">No technician kits created yet.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Kit Name</th>
                                <th>Technician</th>
                                <th>Items</th>
                                <th>Issued</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($kits as $kit): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($kit['kit_name']) ?></strong></td>
                                <td><?= htmlspecialchars($kit['technician_name'] ?? 'Unassigned') ?></td>
                                <td><span class="badge bg-secondary"><?= $kit['item_count'] ?></span></td>
                                <td><?= $kit['issued_at'] ?? '-' ?></td>
                                <td>
                                    <span class="badge bg-<?= $kit['status'] === 'active' ? 'success' : 'secondary' ?>">
                                        <?= ucfirst($kit['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="?page=inventory&tab=kits&action=view&id=<?= $kit['id'] ?>" class="btn btn-outline-primary" title="View"><i class="bi bi-eye"></i></a>
                                        <a href="?page=inventory&tab=kits&action=edit&id=<?= $kit['id'] ?>" class="btn btn-outline-warning" title="Edit"><i class="bi bi-pencil"></i></a>
                                        <?php if ($kit['status'] === 'active'): ?>
                                        <form method="POST" action="?page=inventory&tab=kits&action=return" style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="id" value="<?= $kit['id'] ?>">
                                            <button type="submit" class="btn btn-outline-success" title="Mark Returned" onclick="return confirm('Mark this kit as returned?');">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>
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
    <?php endif; ?>

    <?php elseif ($tab === 'thresholds'): ?>
    <!-- Stock Thresholds Tab -->
    <?php if ($action === 'add' || $action === 'edit'): ?>
        <?php 
        $threshold = $action === 'edit' && $id ? $inventory->getThreshold((int)$id) : null;
        $db = \Database::getConnection();
        $warehouses = [];
        try {
            $warehouses = $db->query("SELECT id, name FROM inventory_warehouses WHERE is_active = true ORDER BY name")->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {}
        ?>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><?= $action === 'edit' ? 'Edit Threshold' : 'Add Stock Threshold' ?></h5>
            </div>
            <div class="card-body">
                <form method="POST" action="?page=inventory&tab=thresholds&action=save">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="id" value="<?= $threshold['id'] ?? '' ?>">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Category *</label>
                            <select class="form-select" name="category_id" required>
                                <option value="">-- Select Category --</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= ($threshold['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Warehouse (Optional)</label>
                            <select class="form-select" name="warehouse_id">
                                <option value="">All Warehouses</option>
                                <?php foreach ($warehouses as $wh): ?>
                                <option value="<?= $wh['id'] ?>" <?= ($threshold['warehouse_id'] ?? '') == $wh['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($wh['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Minimum Quantity</label>
                            <input type="number" class="form-control" name="min_quantity" value="<?= $threshold['min_quantity'] ?? 0 ?>" min="0">
                            <small class="text-muted">Critical alert level</small>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Reorder Point</label>
                            <input type="number" class="form-control" name="reorder_point" value="<?= $threshold['reorder_point'] ?? 0 ?>" min="0">
                            <small class="text-muted">Low stock warning</small>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Reorder Quantity</label>
                            <input type="number" class="form-control" name="reorder_quantity" value="<?= $threshold['reorder_quantity'] ?? 0 ?>" min="0">
                            <small class="text-muted">Suggested order amount</small>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Maximum Quantity</label>
                            <input type="number" class="form-control" name="max_quantity" value="<?= $threshold['max_quantity'] ?? 0 ?>" min="0">
                            <small class="text-muted">Excess alert level</small>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="notify_on_low" value="1" <?= ($threshold['notify_on_low'] ?? true) ? 'checked' : '' ?>>
                                <label class="form-check-label">Notify when stock is low</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="notify_on_excess" value="1" <?= ($threshold['notify_on_excess'] ?? false) ? 'checked' : '' ?>>
                                <label class="form-check-label">Notify when stock exceeds maximum</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save Threshold</button>
                        <a href="?page=inventory&tab=thresholds" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Stock Thresholds</h5>
                <a href="?page=inventory&tab=thresholds&action=add" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg"></i> Add Threshold
                </a>
            </div>
            <div class="card-body">
                <p class="text-muted">Define minimum and maximum stock levels for each category. The system will alert you when stock falls below or exceeds these thresholds.</p>
                
                <?php $thresholds = $inventory->getThresholds(); ?>
                <?php if (empty($thresholds)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> No thresholds configured yet. Add thresholds to enable stock level alerts.
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Warehouse</th>
                                <th>Min Qty</th>
                                <th>Reorder Point</th>
                                <th>Reorder Qty</th>
                                <th>Max Qty</th>
                                <th>Alerts</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($thresholds as $th): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($th['category_name'] ?? 'All') ?></strong></td>
                                <td><?= htmlspecialchars($th['warehouse_name'] ?? 'All') ?></td>
                                <td><?= $th['min_quantity'] ?></td>
                                <td><?= $th['reorder_point'] ?></td>
                                <td><?= $th['reorder_quantity'] ?></td>
                                <td><?= $th['max_quantity'] ?></td>
                                <td>
                                    <?php if ($th['notify_on_low']): ?>
                                    <span class="badge bg-warning text-dark">Low</span>
                                    <?php endif; ?>
                                    <?php if ($th['notify_on_excess']): ?>
                                    <span class="badge bg-info">Excess</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="?page=inventory&tab=thresholds&action=edit&id=<?= $th['id'] ?>" class="btn btn-outline-warning" title="Edit"><i class="bi bi-pencil"></i></a>
                                        <form method="POST" action="?page=inventory&tab=thresholds&action=delete" style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="id" value="<?= $th['id'] ?>">
                                            <button type="submit" class="btn btn-outline-danger" title="Delete" onclick="return confirm('Delete this threshold?');">
                                                <i class="bi bi-trash"></i>
                                            </button>
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
    <?php endif; ?>

    <?php elseif ($tab === 'reports'): ?>
    <!-- Inventory Reports Tab -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="btn-group flex-wrap" role="group">
                <?php $reportType = $_GET['report'] ?? 'stock_levels'; ?>
                <a href="?page=inventory&tab=reports&report=stock_levels" class="btn btn-<?= $reportType === 'stock_levels' ? 'primary' : 'outline-primary' ?>">Stock Levels</a>
                <a href="?page=inventory&tab=reports&report=aging" class="btn btn-<?= $reportType === 'aging' ? 'primary' : 'outline-primary' ?>">Equipment Aging</a>
                <a href="?page=inventory&tab=reports&report=consumption" class="btn btn-<?= $reportType === 'consumption' ? 'primary' : 'outline-primary' ?>">Consumption</a>
                <a href="?page=inventory&tab=reports&report=rma" class="btn btn-<?= $reportType === 'rma' ? 'primary' : 'outline-primary' ?>">RMA Turnaround</a>
                <a href="?page=inventory&tab=reports&report=warranty" class="btn btn-<?= $reportType === 'warranty' ? 'primary' : 'outline-primary' ?>">Warranty Status</a>
                <a href="?page=inventory&tab=reports&report=value" class="btn btn-<?= $reportType === 'value' ? 'primary' : 'outline-primary' ?>">Asset Value</a>
            </div>
        </div>
    </div>

    <?php if ($reportType === 'stock_levels'): ?>
    <div class="card">
        <div class="card-header"><h5 class="mb-0"><i class="bi bi-box-seam"></i> Stock Levels Report</h5></div>
        <div class="card-body">
            <?php $stockReport = $inventory->getStockLevelsReport(); ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead><tr><th>Category</th><th>Total</th><th>Available</th><th>Assigned</th><th>On Loan</th><th>Faulty</th><th>Retired</th><th>Total Value</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($stockReport as $row): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($row['category_name'] ?? 'Uncategorized') ?></strong></td>
                            <td><?= $row['total_items'] ?></td>
                            <td><span class="badge bg-success"><?= $row['available'] ?></span></td>
                            <td><span class="badge bg-info"><?= $row['assigned'] ?></span></td>
                            <td><span class="badge bg-warning text-dark"><?= $row['on_loan'] ?></span></td>
                            <td><span class="badge bg-danger"><?= $row['faulty'] ?></span></td>
                            <td><span class="badge bg-secondary"><?= $row['retired'] ?></span></td>
                            <td>KES <?= number_format($row['total_value'], 2) ?></td>
                            <td>
                                <?php if ($row['min_qty'] > 0 && $row['available'] <= $row['min_qty']): ?>
                                <span class="badge bg-danger">Critical</span>
                                <?php elseif ($row['reorder_point'] > 0 && $row['available'] <= $row['reorder_point']): ?>
                                <span class="badge bg-warning text-dark">Low</span>
                                <?php elseif ($row['min_qty'] > 0 || $row['reorder_point'] > 0): ?>
                                <span class="badge bg-success">OK</span>
                                <?php else: ?>
                                <span class="badge bg-secondary">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php elseif ($reportType === 'aging'): ?>
    <div class="card">
        <div class="card-header"><h5 class="mb-0"><i class="bi bi-clock-history"></i> Equipment Aging Report</h5></div>
        <div class="card-body">
            <?php $agingReport = $inventory->getAgingReport(); ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead><tr><th>Age</th><th>Category</th><th>Items</th><th>Available</th><th>Needs Attention</th><th>Total Value</th></tr></thead>
                    <tbody>
                        <?php foreach ($agingReport as $row): ?>
                        <tr>
                            <td><span class="badge bg-<?= $row['age_bracket'] === 'Over 2 years' ? 'danger' : ($row['age_bracket'] === '1-2 years' ? 'warning' : 'secondary') ?>"><?= $row['age_bracket'] ?></span></td>
                            <td><?= htmlspecialchars($row['category_name'] ?? 'Uncategorized') ?></td>
                            <td><?= $row['item_count'] ?></td>
                            <td><?= $row['available_count'] ?></td>
                            <td><?= $row['needs_attention'] > 0 ? '<span class="badge bg-danger">' . $row['needs_attention'] . '</span>' : '-' ?></td>
                            <td>KES <?= number_format($row['total_value'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php elseif ($reportType === 'consumption'): ?>
    <div class="card">
        <div class="card-header"><h5 class="mb-0"><i class="bi bi-graph-down"></i> Consumption Report</h5></div>
        <div class="card-body">
            <form method="GET" class="row mb-3">
                <input type="hidden" name="page" value="inventory">
                <input type="hidden" name="tab" value="reports">
                <input type="hidden" name="report" value="consumption">
                <div class="col-md-4"><label class="form-label">Start Date</label><input type="date" class="form-control" name="start_date" value="<?= $_GET['start_date'] ?? date('Y-m-01') ?>"></div>
                <div class="col-md-4"><label class="form-label">End Date</label><input type="date" class="form-control" name="end_date" value="<?= $_GET['end_date'] ?? date('Y-m-d') ?>"></div>
                <div class="col-md-4"><label class="form-label">&nbsp;</label><button type="submit" class="btn btn-primary d-block">Generate Report</button></div>
            </form>
            <?php 
            $startDate = $_GET['start_date'] ?? date('Y-m-01');
            $endDate = $_GET['end_date'] ?? date('Y-m-d');
            $consumptionReport = $inventory->getConsumptionReport($startDate, $endDate); 
            ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead><tr><th>Category</th><th>Assigned</th><th>Loaned</th><th>Returned (Assignment)</th><th>Returned (Loan)</th></tr></thead>
                    <tbody>
                        <?php foreach ($consumptionReport as $row): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($row['category_name'] ?? 'Uncategorized') ?></strong></td>
                            <td><?= $row['assigned_count'] ?></td>
                            <td><?= $row['loaned_count'] ?></td>
                            <td><?= $row['returned_from_assignment'] ?></td>
                            <td><?= $row['returned_from_loan'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php elseif ($reportType === 'rma'): ?>
    <div class="card">
        <div class="card-header"><h5 class="mb-0"><i class="bi bi-arrow-repeat"></i> RMA Turnaround Report</h5></div>
        <div class="card-body">
            <?php $rmaReport = $inventory->getRMATurnaroundReport(); ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead><tr><th>Category</th><th>Total Faults</th><th>Pending</th><th>In Progress</th><th>Repaired</th><th>Avg Repair Days</th><th>Avg Cost</th><th>Total Cost</th></tr></thead>
                    <tbody>
                        <?php foreach ($rmaReport as $row): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($row['category_name'] ?? 'Uncategorized') ?></strong></td>
                            <td><?= $row['total_faults'] ?></td>
                            <td><span class="badge bg-warning text-dark"><?= $row['pending'] ?></span></td>
                            <td><span class="badge bg-info"><?= $row['in_progress'] ?></span></td>
                            <td><span class="badge bg-success"><?= $row['repaired'] ?></span></td>
                            <td><?= $row['avg_repair_days'] ?? '-' ?> days</td>
                            <td>KES <?= number_format($row['avg_repair_cost'] ?? 0, 2) ?></td>
                            <td>KES <?= number_format($row['total_repair_cost'] ?? 0, 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php elseif ($reportType === 'warranty'): ?>
    <div class="card">
        <div class="card-header"><h5 class="mb-0"><i class="bi bi-shield-check"></i> Warranty Status Report</h5></div>
        <div class="card-body">
            <?php $warrantyReport = $inventory->getWarrantyExpiryReport(); ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead><tr><th>Warranty Status</th><th>Category</th><th>Items</th><th>Total Value</th></tr></thead>
                    <tbody>
                        <?php foreach ($warrantyReport as $row): ?>
                        <tr>
                            <td><span class="badge bg-<?= $row['warranty_status'] === 'Expired' ? 'danger' : ($row['warranty_status'] === 'Expiring in 30 days' ? 'warning' : 'success') ?>"><?= $row['warranty_status'] ?></span></td>
                            <td><?= htmlspecialchars($row['category_name'] ?? 'Uncategorized') ?></td>
                            <td><?= $row['item_count'] ?></td>
                            <td>KES <?= number_format($row['total_value'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php elseif ($reportType === 'value'): ?>
    <div class="card">
        <div class="card-header"><h5 class="mb-0"><i class="bi bi-currency-dollar"></i> Asset Value Report</h5></div>
        <div class="card-body">
            <?php $valueReport = $inventory->getEquipmentValueReport(); ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead><tr><th>Category</th><th>Items</th><th>Total Value</th><th>Avg Value</th><th>Min Value</th><th>Max Value</th></tr></thead>
                    <tbody>
                        <?php $grandTotal = 0; foreach ($valueReport as $row): $grandTotal += $row['total_value']; ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($row['category_name'] ?? 'Uncategorized') ?></strong></td>
                            <td><?= $row['item_count'] ?></td>
                            <td>KES <?= number_format($row['total_value'], 2) ?></td>
                            <td>KES <?= number_format($row['avg_value'], 2) ?></td>
                            <td>KES <?= number_format($row['min_value'], 2) ?></td>
                            <td>KES <?= number_format($row['max_value'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-dark"><tr><th colspan="2">Grand Total</th><th colspan="4">KES <?= number_format($grandTotal, 2) ?></th></tr></tfoot>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>
