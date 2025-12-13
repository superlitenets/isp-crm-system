<?php
$pageTitle = 'Returns & RMA';
ob_start();

$userId = $_SESSION['user_id'] ?? null;
$stockReturn = new \App\StockReturn($db);
$warehouseManager = new \App\InventoryWarehouse($db);

$view = $_GET['view'] ?? 'returns';
$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';
    
    if ($postAction === 'create_return') {
        try {
            $items = [];
            if (!empty($_POST['items'])) {
                foreach ($_POST['items'] as $item) {
                    if (!empty($item['equipment_id']) || !empty($item['quantity'])) {
                        $items[] = $item;
                    }
                }
            }
            $returnId = $stockReturn->createReturn($_POST, $items);
            $_SESSION['success'] = 'Return created successfully';
            header('Location: ?page=stock_returns&view=detail&id=' . $returnId);
            exit;
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error creating return: ' . $e->getMessage();
        }
    }
    
    if ($postAction === 'receive_return') {
        try {
            $stockReturn->receiveReturn((int)$_POST['id'], $userId);
            $_SESSION['success'] = 'Return received and stock updated';
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error receiving return: ' . $e->getMessage();
        }
        header('Location: ?page=stock_returns&view=detail&id=' . $_POST['id']);
        exit;
    }
    
    if ($postAction === 'create_rma') {
        try {
            $_POST['created_by'] = $userId;
            $rmaId = $stockReturn->createRMA($_POST);
            $_SESSION['success'] = 'RMA created successfully';
            header('Location: ?page=stock_returns&view=rma');
            exit;
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error creating RMA: ' . $e->getMessage();
        }
    }
    
    if ($postAction === 'update_rma') {
        try {
            $stockReturn->updateRMA((int)$_POST['id'], $_POST);
            $_SESSION['success'] = 'RMA updated successfully';
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error updating RMA: ' . $e->getMessage();
        }
        header('Location: ?page=stock_returns&view=rma');
        exit;
    }
    
    if ($postAction === 'create_loss_report') {
        try {
            $_POST['reported_by'] = $userId;
            $stockReturn->createLossReport($_POST);
            $_SESSION['success'] = 'Loss report created successfully';
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error creating loss report: ' . $e->getMessage();
        }
        header('Location: ?page=stock_returns&view=losses');
        exit;
    }
}

$warehouses = $warehouseManager->getWarehouses(['is_active' => true]);
$returnTypes = $stockReturn->getReturnTypes();
$rmaStatuses = $stockReturn->getRmaStatuses();
$rmaResolutions = $stockReturn->getRmaResolutions();
$lossTypes = $stockReturn->getLossTypes();

$usersStmt = $db->query("SELECT id, name FROM users ORDER BY name");
$users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

$equipmentStmt = $db->query("SELECT id, name, serial_number, mac_address FROM equipment ORDER BY name");
$equipment = $equipmentStmt->fetchAll(PDO::FETCH_ASSOC);

$employeesStmt = $db->query("SELECT id, first_name, last_name FROM employees ORDER BY first_name");
$employees = $employeesStmt->fetchAll(PDO::FETCH_ASSOC);
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
            <i class="bi bi-arrow-return-left me-2"></i>Returns & RMA
        </h1>
        <div>
            <a href="?page=inventory" class="btn btn-outline-secondary me-2">
                <i class="bi bi-arrow-left me-1"></i>Back to Inventory
            </a>
            <?php if ($view === 'returns'): ?>
                <a href="?page=stock_returns&view=create_return" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i>New Return
                </a>
            <?php elseif ($view === 'rma'): ?>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#rmaModal">
                    <i class="bi bi-plus-lg me-1"></i>New RMA
                </button>
            <?php elseif ($view === 'losses'): ?>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#lossModal">
                    <i class="bi bi-plus-lg me-1"></i>Report Loss
                </button>
            <?php endif; ?>
        </div>
    </div>

    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link <?= $view === 'returns' ? 'active' : '' ?>" href="?page=stock_returns&view=returns">
                <i class="bi bi-arrow-return-left me-1"></i>Returns
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $view === 'rma' ? 'active' : '' ?>" href="?page=stock_returns&view=rma">
                <i class="bi bi-tools me-1"></i>RMA
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $view === 'losses' ? 'active' : '' ?>" href="?page=stock_returns&view=losses">
                <i class="bi bi-exclamation-triangle me-1"></i>Loss Reports
            </a>
        </li>
    </ul>

    <?php if ($view === 'returns'): ?>
        <?php $returns = $stockReturn->getReturns(); ?>
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Return #</th>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Returned By</th>
                                <th>Warehouse</th>
                                <th>Items</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($returns as $ret): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($ret['return_number']) ?></strong></td>
                                    <td><?= date('M j, Y', strtotime($ret['return_date'])) ?></td>
                                    <td><?= htmlspecialchars($returnTypes[$ret['return_type']] ?? $ret['return_type']) ?></td>
                                    <td><?= htmlspecialchars($ret['returned_by_name'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($ret['warehouse_name'] ?? '-') ?></td>
                                    <td><?= $ret['item_count'] ?> items</td>
                                    <td>
                                        <?php
                                        $statusClass = match($ret['status']) {
                                            'pending' => 'bg-warning',
                                            'received' => 'bg-success',
                                            default => 'bg-secondary'
                                        };
                                        ?>
                                        <span class="badge <?= $statusClass ?>"><?= ucfirst($ret['status']) ?></span>
                                    </td>
                                    <td>
                                        <a href="?page=stock_returns&view=detail&id=<?= $ret['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($returns)): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        <i class="bi bi-arrow-return-left fs-1 d-block mb-2"></i>
                                        No returns found.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <?php elseif ($view === 'create_return'): ?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="h4 mb-0">Create Stock Return</h2>
            <a href="?page=stock_returns&view=returns" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Back
            </a>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="create_return">
            <input type="hidden" name="returned_by" value="<?= $userId ?>">
            
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Return Details</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Return Type <span class="text-danger">*</span></label>
                            <select class="form-select" name="return_type" required>
                                <?php foreach ($returnTypes as $key => $label): ?>
                                    <option value="<?= $key ?>"><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Warehouse <span class="text-danger">*</span></label>
                            <select class="form-select" name="warehouse_id" required>
                                <option value="">Select Warehouse</option>
                                <?php foreach ($warehouses as $wh): ?>
                                    <option value="<?= $wh['id'] ?>"><?= htmlspecialchars($wh['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Return Date</label>
                            <input type="date" class="form-control" name="return_date" value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="2"></textarea>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Return Items</h5>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="addReturnItem()">
                        <i class="bi bi-plus-lg me-1"></i>Add Item
                    </button>
                </div>
                <div class="card-body">
                    <div id="returnItemsContainer">
                        <div class="row mb-3 return-item-row">
                            <div class="col-md-5">
                                <label class="form-label">Equipment</label>
                                <select class="form-select" name="items[0][equipment_id]">
                                    <option value="">Select Equipment</option>
                                    <?php foreach ($equipment as $eq): ?>
                                        <option value="<?= $eq['id'] ?>">
                                            <?= htmlspecialchars($eq['name']) ?> 
                                            <?= $eq['serial_number'] ? '(' . $eq['serial_number'] . ')' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Quantity</label>
                                <input type="number" class="form-control" name="items[0][quantity]" value="1" min="1">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Condition</label>
                                <select class="form-select" name="items[0][condition]">
                                    <option value="good">Good</option>
                                    <option value="damaged">Damaged</option>
                                    <option value="faulty">Faulty</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="button" class="btn btn-outline-danger" onclick="removeReturnItem(this)" disabled>
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="?page=stock_returns&view=returns" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Create Return</button>
            </div>
        </form>

        <script>
        let returnItemIndex = 1;
        
        function addReturnItem() {
            const container = document.getElementById('returnItemsContainer');
            const template = `
                <div class="row mb-3 return-item-row">
                    <div class="col-md-5">
                        <select class="form-select" name="items[${returnItemIndex}][equipment_id]">
                            <option value="">Select Equipment</option>
                            <?php foreach ($equipment as $eq): ?>
                                <option value="<?= $eq['id'] ?>">
                                    <?= htmlspecialchars($eq['name']) ?> 
                                    <?= $eq['serial_number'] ? '(' . $eq['serial_number'] . ')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="number" class="form-control" name="items[${returnItemIndex}][quantity]" value="1" min="1">
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="items[${returnItemIndex}][condition]">
                            <option value="good">Good</option>
                            <option value="damaged">Damaged</option>
                            <option value="faulty">Faulty</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-outline-danger" onclick="removeReturnItem(this)">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', template);
            returnItemIndex++;
        }
        
        function removeReturnItem(btn) {
            btn.closest('.return-item-row').remove();
        }
        </script>

    <?php elseif ($view === 'detail' && $id): ?>
        <?php 
        $return = $stockReturn->getReturn($id);
        $items = $stockReturn->getReturnItems($id);
        ?>
        <?php if ($return): ?>
            <div class="row">
                <div class="col-md-4">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Return Details</h5>
                        </div>
                        <div class="card-body">
                            <dl class="row mb-0">
                                <dt class="col-5">Return #</dt>
                                <dd class="col-7"><strong><?= htmlspecialchars($return['return_number']) ?></strong></dd>
                                
                                <dt class="col-5">Status</dt>
                                <dd class="col-7">
                                    <span class="badge <?= $return['status'] === 'received' ? 'bg-success' : 'bg-warning' ?>">
                                        <?= ucfirst($return['status']) ?>
                                    </span>
                                </dd>
                                
                                <dt class="col-5">Type</dt>
                                <dd class="col-7"><?= htmlspecialchars($returnTypes[$return['return_type']] ?? $return['return_type']) ?></dd>
                                
                                <dt class="col-5">Warehouse</dt>
                                <dd class="col-7"><?= htmlspecialchars($return['warehouse_name'] ?? '-') ?></dd>
                                
                                <dt class="col-5">Returned By</dt>
                                <dd class="col-7"><?= htmlspecialchars($return['returned_by_name'] ?? '-') ?></dd>
                                
                                <dt class="col-5">Return Date</dt>
                                <dd class="col-7"><?= date('M j, Y', strtotime($return['return_date'])) ?></dd>
                                
                                <?php if ($return['received_by_name']): ?>
                                    <dt class="col-5">Received By</dt>
                                    <dd class="col-7"><?= htmlspecialchars($return['received_by_name']) ?></dd>
                                <?php endif; ?>
                            </dl>
                            
                            <?php if ($return['status'] === 'pending'): ?>
                                <hr>
                                <form method="POST">
                                    <input type="hidden" name="action" value="receive_return">
                                    <input type="hidden" name="id" value="<?= $id ?>">
                                    <button type="submit" class="btn btn-success w-100">
                                        <i class="bi bi-check-lg me-1"></i>Receive Return
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Return Items</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Equipment</th>
                                            <th>Serial #</th>
                                            <th>Quantity</th>
                                            <th>Condition</th>
                                            <th>Location</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($items as $item): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($item['equipment_name'] ?? '-') ?></td>
                                                <td><?= htmlspecialchars($item['serial_number'] ?? '-') ?></td>
                                                <td><?= $item['quantity'] ?></td>
                                                <td>
                                                    <?php
                                                    $condClass = match($item['condition']) {
                                                        'good' => 'bg-success',
                                                        'damaged' => 'bg-warning',
                                                        'faulty' => 'bg-danger',
                                                        default => 'bg-secondary'
                                                    };
                                                    ?>
                                                    <span class="badge <?= $condClass ?>"><?= ucfirst($item['condition']) ?></span>
                                                </td>
                                                <td><?= htmlspecialchars($item['location_name'] ?? '-') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-warning">Return not found.</div>
        <?php endif; ?>

    <?php elseif ($view === 'rma'): ?>
        <?php $rmas = $stockReturn->getRMAs(); ?>
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>RMA #</th>
                                <th>Equipment</th>
                                <th>Serial #</th>
                                <th>Vendor</th>
                                <th>Status</th>
                                <th>Shipped</th>
                                <th>Received</th>
                                <th>Resolution</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rmas as $rma): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($rma['rma_number']) ?></strong></td>
                                    <td><?= htmlspecialchars($rma['equipment_name'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($rma['serial_number'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($rma['vendor_name'] ?? '-') ?></td>
                                    <td>
                                        <?php
                                        $statusClass = match($rma['status']) {
                                            'pending' => 'bg-warning',
                                            'shipped' => 'bg-info',
                                            'in_repair' => 'bg-primary',
                                            'resolved' => 'bg-success',
                                            default => 'bg-secondary'
                                        };
                                        ?>
                                        <span class="badge <?= $statusClass ?>"><?= $rmaStatuses[$rma['status']] ?? $rma['status'] ?></span>
                                    </td>
                                    <td><?= $rma['shipped_date'] ? date('M j', strtotime($rma['shipped_date'])) : '-' ?></td>
                                    <td><?= $rma['received_date'] ? date('M j', strtotime($rma['received_date'])) : '-' ?></td>
                                    <td><?= htmlspecialchars($rmaResolutions[$rma['resolution']] ?? $rma['resolution'] ?? '-') ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" onclick='editRMA(<?= json_encode($rma) ?>)'>
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($rmas)): ?>
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">
                                        <i class="bi bi-tools fs-1 d-block mb-2"></i>
                                        No RMA records found.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <?php elseif ($view === 'losses'): ?>
        <?php $losses = $stockReturn->getLossReports(); ?>
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Report #</th>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Equipment</th>
                                <th>Employee</th>
                                <th>Est. Value</th>
                                <th>Status</th>
                                <th>Resolution</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($losses as $loss): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($loss['report_number']) ?></strong></td>
                                    <td><?= date('M j, Y', strtotime($loss['loss_date'])) ?></td>
                                    <td>
                                        <?php
                                        $typeClass = match($loss['loss_type']) {
                                            'stolen' => 'bg-danger',
                                            'damaged' => 'bg-warning',
                                            default => 'bg-secondary'
                                        };
                                        ?>
                                        <span class="badge <?= $typeClass ?>"><?= $lossTypes[$loss['loss_type']] ?? $loss['loss_type'] ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($loss['equipment_name'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($loss['employee_name'] ?? '-') ?></td>
                                    <td><?= $loss['estimated_value'] ? 'KES ' . number_format($loss['estimated_value'], 2) : '-' ?></td>
                                    <td>
                                        <?php
                                        $statusClass = match($loss['investigation_status']) {
                                            'pending' => 'bg-warning',
                                            'investigating' => 'bg-info',
                                            'resolved' => 'bg-success',
                                            default => 'bg-secondary'
                                        };
                                        ?>
                                        <span class="badge <?= $statusClass ?>"><?= ucfirst($loss['investigation_status']) ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($loss['resolution'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($losses)): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        <i class="bi bi-exclamation-triangle fs-1 d-block mb-2"></i>
                                        No loss reports found.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="modal fade" id="rmaModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="rmaForm">
                <input type="hidden" name="action" value="create_rma" id="rmaAction">
                <input type="hidden" name="id" id="rmaId">
                <div class="modal-header">
                    <h5 class="modal-title" id="rmaModalTitle">Create RMA</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Equipment <span class="text-danger">*</span></label>
                        <select class="form-select" name="equipment_id" id="rmaEquipment" required>
                            <option value="">Select Equipment</option>
                            <?php foreach ($equipment as $eq): ?>
                                <option value="<?= $eq['id'] ?>">
                                    <?= htmlspecialchars($eq['name']) ?> 
                                    <?= $eq['serial_number'] ? '(' . $eq['serial_number'] . ')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Vendor Name</label>
                        <input type="text" class="form-control" name="vendor_name" id="rmaVendor">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Vendor Contact</label>
                        <input type="text" class="form-control" name="vendor_contact" id="rmaContact">
                    </div>
                    <div class="row" id="rmaStatusFields" style="display:none">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" id="rmaStatus">
                                <?php foreach ($rmaStatuses as $key => $label): ?>
                                    <option value="<?= $key ?>"><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Resolution</label>
                            <select class="form-select" name="resolution" id="rmaResolution">
                                <option value="">Not Resolved</option>
                                <?php foreach ($rmaResolutions as $key => $label): ?>
                                    <option value="<?= $key ?>"><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Shipped Date</label>
                            <input type="date" class="form-control" name="shipped_date" id="rmaShipped">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Received Date</label>
                            <input type="date" class="form-control" name="received_date" id="rmaReceived">
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">Resolution Notes</label>
                            <textarea class="form-control" name="resolution_notes" id="rmaNotes" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="lossModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="create_loss_report">
                <div class="modal-header">
                    <h5 class="modal-title">Report Loss</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Loss Type <span class="text-danger">*</span></label>
                        <select class="form-select" name="loss_type" required>
                            <?php foreach ($lossTypes as $key => $label): ?>
                                <option value="<?= $key ?>"><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Equipment</label>
                        <select class="form-select" name="equipment_id">
                            <option value="">Select Equipment</option>
                            <?php foreach ($equipment as $eq): ?>
                                <option value="<?= $eq['id'] ?>">
                                    <?= htmlspecialchars($eq['name']) ?> 
                                    <?= $eq['serial_number'] ? '(' . $eq['serial_number'] . ')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Employee Responsible</label>
                        <select class="form-select" name="employee_id">
                            <option value="">Select Employee</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Loss Date</label>
                            <input type="date" class="form-control" name="loss_date" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Estimated Value (KES)</label>
                            <input type="number" class="form-control" name="estimated_value" step="0.01" min="0">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="description" rows="3" required placeholder="Describe what happened..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Report</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editRMA(rma) {
    document.getElementById('rmaAction').value = 'update_rma';
    document.getElementById('rmaModalTitle').textContent = 'Edit RMA';
    document.getElementById('rmaId').value = rma.id;
    document.getElementById('rmaEquipment').value = rma.equipment_id;
    document.getElementById('rmaEquipment').disabled = true;
    document.getElementById('rmaVendor').value = rma.vendor_name || '';
    document.getElementById('rmaContact').value = rma.vendor_contact || '';
    document.getElementById('rmaStatus').value = rma.status;
    document.getElementById('rmaResolution').value = rma.resolution || '';
    document.getElementById('rmaShipped').value = rma.shipped_date || '';
    document.getElementById('rmaReceived').value = rma.received_date || '';
    document.getElementById('rmaNotes').value = rma.resolution_notes || '';
    document.getElementById('rmaStatusFields').style.display = 'flex';
    new bootstrap.Modal(document.getElementById('rmaModal')).show();
}

document.getElementById('rmaModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('rmaForm').reset();
    document.getElementById('rmaAction').value = 'create_rma';
    document.getElementById('rmaModalTitle').textContent = 'Create RMA';
    document.getElementById('rmaId').value = '';
    document.getElementById('rmaEquipment').disabled = false;
    document.getElementById('rmaStatusFields').style.display = 'none';
});
</script>
