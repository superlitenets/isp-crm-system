<?php
$pageTitle = 'Warehouse Management';
ob_start();

$warehouseManager = new \App\InventoryWarehouse($db);

$view = $_GET['view'] ?? 'warehouses';
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';
    
    if ($postAction === 'create_warehouse') {
        try {
            $warehouseManager->createWarehouse($_POST);
            $_SESSION['success'] = 'Warehouse created successfully';
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error creating warehouse: ' . $e->getMessage();
        }
        header('Location: ?page=inventory_warehouses');
        exit;
    }
    
    if ($postAction === 'update_warehouse') {
        try {
            $warehouseManager->updateWarehouse((int)$_POST['id'], $_POST);
            $_SESSION['success'] = 'Warehouse updated successfully';
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error updating warehouse: ' . $e->getMessage();
        }
        header('Location: ?page=inventory_warehouses');
        exit;
    }
    
    if ($postAction === 'delete_warehouse') {
        try {
            $warehouseManager->deleteWarehouse((int)$_POST['id']);
            $_SESSION['success'] = 'Warehouse deleted successfully';
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error deleting warehouse: ' . $e->getMessage();
        }
        header('Location: ?page=inventory_warehouses');
        exit;
    }
    
    if ($postAction === 'create_location') {
        try {
            $warehouseManager->createLocation($_POST);
            $_SESSION['success'] = 'Location created successfully';
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error creating location: ' . $e->getMessage();
        }
        header('Location: ?page=inventory_warehouses&view=locations');
        exit;
    }
    
    if ($postAction === 'update_location') {
        try {
            $warehouseManager->updateLocation((int)$_POST['id'], $_POST);
            $_SESSION['success'] = 'Location updated successfully';
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error updating location: ' . $e->getMessage();
        }
        header('Location: ?page=inventory_warehouses&view=locations');
        exit;
    }
    
    if ($postAction === 'delete_location') {
        try {
            $warehouseManager->deleteLocation((int)$_POST['id']);
            $_SESSION['success'] = 'Location deleted successfully';
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error deleting location: ' . $e->getMessage();
        }
        header('Location: ?page=inventory_warehouses&view=locations');
        exit;
    }
}

$warehouses = $warehouseManager->getWarehouses();
$warehouseTypes = $warehouseManager->getWarehouseTypes();
$locationTypes = $warehouseManager->getLocationTypes();

$usersStmt = $db->query("SELECT id, name FROM users ORDER BY name");
$users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid">
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="bi bi-building me-2"></i>Warehouse Management
        </h1>
        <div>
            <a href="?page=inventory" class="btn btn-outline-secondary me-2">
                <i class="bi bi-arrow-left me-1"></i>Back to Inventory
            </a>
            <?php if ($view === 'warehouses'): ?>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#warehouseModal">
                    <i class="bi bi-plus-lg me-1"></i>Add Warehouse
                </button>
            <?php else: ?>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#locationModal">
                    <i class="bi bi-plus-lg me-1"></i>Add Location
                </button>
            <?php endif; ?>
        </div>
    </div>

    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link <?= $view === 'warehouses' ? 'active' : '' ?>" href="?page=inventory_warehouses&view=warehouses">
                <i class="bi bi-building me-1"></i>Warehouses
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $view === 'locations' ? 'active' : '' ?>" href="?page=inventory_warehouses&view=locations">
                <i class="bi bi-geo-alt me-1"></i>Locations
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $view === 'movements' ? 'active' : '' ?>" href="?page=inventory_warehouses&view=movements">
                <i class="bi bi-arrows-move me-1"></i>Stock Movements
            </a>
        </li>
    </ul>

    <?php if ($view === 'warehouses'): ?>
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Manager</th>
                                <th>Phone</th>
                                <th>Locations</th>
                                <th>Equipment</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($warehouses as $wh): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($wh['code']) ?></strong></td>
                                    <td><?= htmlspecialchars($wh['name']) ?></td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?= htmlspecialchars($warehouseTypes[$wh['type']] ?? $wh['type']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($wh['manager_name'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($wh['phone'] ?? '-') ?></td>
                                    <td><?= $wh['location_count'] ?></td>
                                    <td><?= $wh['equipment_count'] ?></td>
                                    <td>
                                        <?php if ($wh['is_active']): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary" onclick="editWarehouse(<?= htmlspecialchars(json_encode($wh)) ?>)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <a href="?page=inventory_warehouses&view=warehouse_detail&id=<?= $wh['id'] ?>" class="btn btn-outline-info">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <button class="btn btn-outline-danger" onclick="deleteWarehouse(<?= $wh['id'] ?>, '<?= htmlspecialchars($wh['name']) ?>')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($warehouses)): ?>
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">
                                        <i class="bi bi-building fs-1 d-block mb-2"></i>
                                        No warehouses found. Click "Add Warehouse" to create one.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <?php elseif ($view === 'locations'): ?>
        <?php $locations = $warehouseManager->getLocations(); ?>
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Warehouse</th>
                                <th>Location Code</th>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Capacity</th>
                                <th>Equipment</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($locations as $loc): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($loc['warehouse_code']) ?></strong>
                                        <small class="text-muted d-block"><?= htmlspecialchars($loc['warehouse_name']) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($loc['code'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($loc['name']) ?></td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?= htmlspecialchars($locationTypes[$loc['type']] ?? $loc['type']) ?>
                                        </span>
                                    </td>
                                    <td><?= $loc['capacity'] ?? '-' ?></td>
                                    <td><?= $loc['equipment_count'] ?></td>
                                    <td>
                                        <?php if ($loc['is_active']): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary" onclick="editLocation(<?= htmlspecialchars(json_encode($loc)) ?>)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-outline-danger" onclick="deleteLocation(<?= $loc['id'] ?>, '<?= htmlspecialchars($loc['name']) ?>')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($locations)): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        <i class="bi bi-geo-alt fs-1 d-block mb-2"></i>
                                        No locations found. Click "Add Location" to create one.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <?php elseif ($view === 'movements'): ?>
        <?php $movements = $warehouseManager->getMovementHistory(null, null, 100); ?>
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Equipment</th>
                                <th>Type</th>
                                <th>From</th>
                                <th>To</th>
                                <th>Qty</th>
                                <th>Performed By</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($movements as $mov): ?>
                                <tr>
                                    <td><?= date('M j, Y H:i', strtotime($mov['created_at'])) ?></td>
                                    <td>
                                        <?= htmlspecialchars($mov['equipment_name'] ?? 'N/A') ?>
                                        <?php if ($mov['serial_number']): ?>
                                            <small class="text-muted d-block"><?= htmlspecialchars($mov['serial_number']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?= ucfirst($mov['movement_type']) ?></span>
                                    </td>
                                    <td>
                                        <?php if ($mov['from_warehouse_name']): ?>
                                            <?= htmlspecialchars($mov['from_warehouse_name']) ?>
                                            <?php if ($mov['from_location_name']): ?>
                                                <small class="text-muted d-block"><?= htmlspecialchars($mov['from_location_name']) ?></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($mov['to_warehouse_name']): ?>
                                            <?= htmlspecialchars($mov['to_warehouse_name']) ?>
                                            <?php if ($mov['to_location_name']): ?>
                                                <small class="text-muted d-block"><?= htmlspecialchars($mov['to_location_name']) ?></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $mov['quantity'] ?></td>
                                    <td><?= htmlspecialchars($mov['performed_by_name'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($mov['notes'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($movements)): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        <i class="bi bi-arrows-move fs-1 d-block mb-2"></i>
                                        No stock movements recorded yet.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <?php elseif ($view === 'warehouse_detail' && $id): ?>
        <?php 
        $warehouse = $warehouseManager->getWarehouse($id);
        $warehouseLocations = $warehouseManager->getLocations($id);
        $warehouseStock = $warehouseManager->getStockByWarehouse($id);
        ?>
        <?php if ($warehouse): ?>
            <div class="row">
                <div class="col-md-4">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Warehouse Details</h5>
                        </div>
                        <div class="card-body">
                            <dl class="row mb-0">
                                <dt class="col-4">Code</dt>
                                <dd class="col-8"><strong><?= htmlspecialchars($warehouse['code']) ?></strong></dd>
                                
                                <dt class="col-4">Name</dt>
                                <dd class="col-8"><?= htmlspecialchars($warehouse['name']) ?></dd>
                                
                                <dt class="col-4">Type</dt>
                                <dd class="col-8"><?= htmlspecialchars($warehouseTypes[$warehouse['type']] ?? $warehouse['type']) ?></dd>
                                
                                <dt class="col-4">Manager</dt>
                                <dd class="col-8"><?= htmlspecialchars($warehouse['manager_name'] ?? 'Not assigned') ?></dd>
                                
                                <dt class="col-4">Phone</dt>
                                <dd class="col-8"><?= htmlspecialchars($warehouse['phone'] ?? '-') ?></dd>
                                
                                <dt class="col-4">Address</dt>
                                <dd class="col-8"><?= nl2br(htmlspecialchars($warehouse['address'] ?? '-')) ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Stock Summary</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($warehouseStock): ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($warehouseStock as $stock): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <?= htmlspecialchars($stock['category']) ?>
                                            <span class="badge bg-primary rounded-pill"><?= $stock['total_qty'] ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="text-muted mb-0">No stock in this warehouse</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Locations</h5>
                            <span class="badge bg-secondary"><?= count($warehouseLocations) ?></span>
                        </div>
                        <div class="card-body">
                            <?php if ($warehouseLocations): ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($warehouseLocations as $loc): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong><?= htmlspecialchars($loc['name']) ?></strong>
                                                <small class="text-muted d-block"><?= htmlspecialchars($loc['code'] ?? '') ?></small>
                                            </div>
                                            <span class="badge bg-info rounded-pill"><?= $loc['equipment_count'] ?> items</span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="text-muted mb-0">No locations defined</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<div class="modal fade" id="warehouseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="warehouseForm">
                <input type="hidden" name="action" value="create_warehouse" id="warehouseAction">
                <input type="hidden" name="id" id="warehouseId">
                <div class="modal-header">
                    <h5 class="modal-title" id="warehouseModalTitle">Add Warehouse</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="code" id="warehouseCode" required maxlength="20" style="text-transform: uppercase">
                        </div>
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" id="warehouseName" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Type</label>
                            <select class="form-select" name="type" id="warehouseType">
                                <?php foreach ($warehouseTypes as $key => $label): ?>
                                    <option value="<?= $key ?>"><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Manager</label>
                            <select class="form-select" name="manager_id" id="warehouseManager">
                                <option value="">Select Manager</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" class="form-control" name="phone" id="warehousePhone">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea class="form-control" name="address" id="warehouseAddress" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" id="warehouseNotes" rows="2"></textarea>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" id="warehouseActive" value="1" checked>
                        <label class="form-check-label" for="warehouseActive">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Warehouse</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="locationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="locationForm">
                <input type="hidden" name="action" value="create_location" id="locationAction">
                <input type="hidden" name="id" id="locationId">
                <div class="modal-header">
                    <h5 class="modal-title" id="locationModalTitle">Add Location</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Warehouse <span class="text-danger">*</span></label>
                        <select class="form-select" name="warehouse_id" id="locationWarehouse" required>
                            <option value="">Select Warehouse</option>
                            <?php foreach ($warehouses as $wh): ?>
                                <option value="<?= $wh['id'] ?>"><?= htmlspecialchars($wh['code'] . ' - ' . $wh['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Code</label>
                            <input type="text" class="form-control" name="code" id="locationCode" maxlength="50">
                        </div>
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" id="locationName" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Type</label>
                            <select class="form-select" name="type" id="locationType">
                                <?php foreach ($locationTypes as $key => $label): ?>
                                    <option value="<?= $key ?>"><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Capacity</label>
                            <input type="number" class="form-control" name="capacity" id="locationCapacity" min="0">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" id="locationNotes" rows="2"></textarea>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" id="locationActive" value="1" checked>
                        <label class="form-check-label" for="locationActive">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Location</button>
                </div>
            </form>
        </div>
    </div>
</div>

<form id="deleteWarehouseForm" method="POST" style="display:none">
    <input type="hidden" name="action" value="delete_warehouse">
    <input type="hidden" name="id" id="deleteWarehouseId">
</form>

<form id="deleteLocationForm" method="POST" style="display:none">
    <input type="hidden" name="action" value="delete_location">
    <input type="hidden" name="id" id="deleteLocationId">
</form>

<script>
function editWarehouse(wh) {
    document.getElementById('warehouseAction').value = 'update_warehouse';
    document.getElementById('warehouseModalTitle').textContent = 'Edit Warehouse';
    document.getElementById('warehouseId').value = wh.id;
    document.getElementById('warehouseCode').value = wh.code;
    document.getElementById('warehouseName').value = wh.name;
    document.getElementById('warehouseType').value = wh.type;
    document.getElementById('warehouseManager').value = wh.manager_id || '';
    document.getElementById('warehousePhone').value = wh.phone || '';
    document.getElementById('warehouseAddress').value = wh.address || '';
    document.getElementById('warehouseNotes').value = wh.notes || '';
    document.getElementById('warehouseActive').checked = wh.is_active;
    new bootstrap.Modal(document.getElementById('warehouseModal')).show();
}

function deleteWarehouse(id, name) {
    if (confirm('Are you sure you want to delete warehouse "' + name + '"? This will also delete all locations in this warehouse.')) {
        document.getElementById('deleteWarehouseId').value = id;
        document.getElementById('deleteWarehouseForm').submit();
    }
}

function editLocation(loc) {
    document.getElementById('locationAction').value = 'update_location';
    document.getElementById('locationModalTitle').textContent = 'Edit Location';
    document.getElementById('locationId').value = loc.id;
    document.getElementById('locationWarehouse').value = loc.warehouse_id;
    document.getElementById('locationCode').value = loc.code || '';
    document.getElementById('locationName').value = loc.name;
    document.getElementById('locationType').value = loc.type;
    document.getElementById('locationCapacity').value = loc.capacity || '';
    document.getElementById('locationNotes').value = loc.notes || '';
    document.getElementById('locationActive').checked = loc.is_active;
    new bootstrap.Modal(document.getElementById('locationModal')).show();
}

function deleteLocation(id, name) {
    if (confirm('Are you sure you want to delete location "' + name + '"?')) {
        document.getElementById('deleteLocationId').value = id;
        document.getElementById('deleteLocationForm').submit();
    }
}

document.getElementById('warehouseModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('warehouseForm').reset();
    document.getElementById('warehouseAction').value = 'create_warehouse';
    document.getElementById('warehouseModalTitle').textContent = 'Add Warehouse';
    document.getElementById('warehouseId').value = '';
    document.getElementById('warehouseActive').checked = true;
});

document.getElementById('locationModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('locationForm').reset();
    document.getElementById('locationAction').value = 'create_location';
    document.getElementById('locationModalTitle').textContent = 'Add Location';
    document.getElementById('locationId').value = '';
    document.getElementById('locationActive').checked = true;
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
