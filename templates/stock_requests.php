<?php
$pageTitle = 'Stock Requests';
ob_start();

$userId = $_SESSION['user_id'] ?? null;
$stockRequest = new \App\StockRequest($db);
$warehouseManager = new \App\InventoryWarehouse($db);

$view = $_GET['view'] ?? 'list';
$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';
    
    if ($postAction === 'create_request') {
        try {
            $items = [];
            if (!empty($_POST['items'])) {
                foreach ($_POST['items'] as $item) {
                    if (!empty($item['item_name']) && !empty($item['quantity_requested'])) {
                        $items[] = $item;
                    }
                }
            }
            $requestId = $stockRequest->createRequest($_POST, $items);
            $_SESSION['success'] = 'Stock request created successfully';
            header('Location: ?page=stock_requests&view=detail&id=' . $requestId);
            exit;
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error creating request: ' . $e->getMessage();
        }
    }
    
    if ($postAction === 'approve_request') {
        try {
            $approvedQty = $_POST['approved_qty'] ?? [];
            $stockRequest->approveRequest((int)$_POST['id'], $userId, $approvedQty);
            $_SESSION['success'] = 'Request approved successfully';
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error approving request: ' . $e->getMessage();
        }
        header('Location: ?page=stock_requests&view=detail&id=' . $_POST['id']);
        exit;
    }
    
    if ($postAction === 'reject_request') {
        try {
            $stockRequest->rejectRequest((int)$_POST['id'], $userId, $_POST['reason']);
            $_SESSION['success'] = 'Request rejected';
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error rejecting request: ' . $e->getMessage();
        }
        header('Location: ?page=stock_requests');
        exit;
    }
    
    if ($postAction === 'pick_items') {
        try {
            $pickedQty = $_POST['picked_qty'] ?? [];
            $equipmentIds = $_POST['equipment_ids'] ?? [];
            $stockRequest->pickItems((int)$_POST['id'], $userId, $pickedQty, $equipmentIds);
            $_SESSION['success'] = 'Items picked successfully';
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error picking items: ' . $e->getMessage();
        }
        header('Location: ?page=stock_requests&view=detail&id=' . $_POST['id']);
        exit;
    }
    
    if ($postAction === 'handover') {
        try {
            $stockRequest->handover((int)$_POST['id'], (int)$_POST['handed_to'], $_POST['signature'] ?? null);
            $_SESSION['success'] = 'Handover completed successfully';
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error completing handover: ' . $e->getMessage();
        }
        header('Location: ?page=stock_requests&view=detail&id=' . $_POST['id']);
        exit;
    }
    
    if ($postAction === 'complete_request') {
        try {
            $stockRequest->completeRequest((int)$_POST['id']);
            $_SESSION['success'] = 'Request marked as completed';
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error completing request: ' . $e->getMessage();
        }
        header('Location: ?page=stock_requests');
        exit;
    }
}

$warehouses = $warehouseManager->getWarehouses(['is_active' => true]);
$requestTypes = $stockRequest->getRequestTypes();
$statuses = $stockRequest->getStatuses();

$usersStmt = $db->query("SELECT id, name FROM users ORDER BY name");
$users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

$categoriesStmt = $db->query("SELECT id, name FROM equipment_categories ORDER BY name");
$categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);

$filters = [
    'status' => $_GET['status'] ?? '',
    'warehouse_id' => $_GET['warehouse_id'] ?? '',
    'request_type' => $_GET['request_type'] ?? ''
];
$requests = $stockRequest->getRequests(array_filter($filters));
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

    <?php if ($view === 'list'): ?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">
                <i class="bi bi-box-arrow-up me-2"></i>Stock Requests
            </h1>
            <div>
                <a href="?page=inventory" class="btn btn-outline-secondary me-2">
                    <i class="bi bi-arrow-left me-1"></i>Back to Inventory
                </a>
                <a href="?page=stock_requests&view=create" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i>New Request
                </a>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <input type="hidden" name="page" value="stock_requests">
                    <div class="col-md-3">
                        <select class="form-select" name="status">
                            <option value="">All Statuses</option>
                            <?php foreach ($statuses as $key => $label): ?>
                                <option value="<?= $key ?>" <?= $filters['status'] === $key ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="warehouse_id">
                            <option value="">All Warehouses</option>
                            <?php foreach ($warehouses as $wh): ?>
                                <option value="<?= $wh['id'] ?>" <?= $filters['warehouse_id'] == $wh['id'] ? 'selected' : '' ?>><?= htmlspecialchars($wh['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="request_type">
                            <option value="">All Types</option>
                            <?php foreach ($requestTypes as $key => $label): ?>
                                <option value="<?= $key ?>" <?= $filters['request_type'] === $key ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="?page=stock_requests" class="btn btn-outline-secondary">Clear</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Request #</th>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Requested By</th>
                                <th>Warehouse</th>
                                <th>Items</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $req): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($req['request_number']) ?></strong></td>
                                    <td><?= date('M j, Y', strtotime($req['created_at'])) ?></td>
                                    <td><?= htmlspecialchars($requestTypes[$req['request_type']] ?? $req['request_type']) ?></td>
                                    <td><?= htmlspecialchars($req['requested_by_name'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($req['warehouse_name'] ?? '-') ?></td>
                                    <td><?= $req['item_count'] ?> items</td>
                                    <td>
                                        <?php
                                        $priorityClass = match($req['priority']) {
                                            'high' => 'bg-danger',
                                            'urgent' => 'bg-warning',
                                            default => 'bg-secondary'
                                        };
                                        ?>
                                        <span class="badge <?= $priorityClass ?>"><?= ucfirst($req['priority']) ?></span>
                                    </td>
                                    <td>
                                        <?php
                                        $statusClass = match($req['status']) {
                                            'pending' => 'bg-warning',
                                            'approved' => 'bg-info',
                                            'picked' => 'bg-primary',
                                            'handed_over' => 'bg-success',
                                            'completed' => 'bg-success',
                                            'rejected', 'cancelled' => 'bg-danger',
                                            default => 'bg-secondary'
                                        };
                                        ?>
                                        <span class="badge <?= $statusClass ?>"><?= $statuses[$req['status']] ?? $req['status'] ?></span>
                                    </td>
                                    <td>
                                        <a href="?page=stock_requests&view=detail&id=<?= $req['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($requests)): ?>
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">
                                        <i class="bi bi-box-arrow-up fs-1 d-block mb-2"></i>
                                        No stock requests found.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <?php elseif ($view === 'create'): ?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">
                <i class="bi bi-plus-lg me-2"></i>New Stock Request
            </h1>
            <a href="?page=stock_requests" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Back
            </a>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="create_request">
            <input type="hidden" name="requested_by" value="<?= $userId ?>">
            
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Request Details</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Request Type <span class="text-danger">*</span></label>
                            <select class="form-select" name="request_type" required>
                                <?php foreach ($requestTypes as $key => $label): ?>
                                    <option value="<?= $key ?>"><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Warehouse</label>
                            <select class="form-select" name="warehouse_id">
                                <option value="">Select Warehouse</option>
                                <?php foreach ($warehouses as $wh): ?>
                                    <option value="<?= $wh['id'] ?>"><?= htmlspecialchars($wh['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Priority</label>
                            <select class="form-select" name="priority">
                                <option value="normal">Normal</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Required Date</label>
                            <input type="date" class="form-control" name="required_date">
                        </div>
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Notes</label>
                            <input type="text" class="form-control" name="notes" placeholder="Purpose or additional details">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Request Items</h5>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="addItem()">
                        <i class="bi bi-plus-lg me-1"></i>Add Item
                    </button>
                </div>
                <div class="card-body">
                    <div id="itemsContainer">
                        <div class="row mb-3 item-row">
                            <div class="col-md-4">
                                <label class="form-label">Category</label>
                                <select class="form-select" name="items[0][category_id]">
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Item Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="items[0][item_name]" required placeholder="e.g. ONU Router">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Quantity <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="items[0][quantity_requested]" value="1" min="1" required>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="button" class="btn btn-outline-danger" onclick="removeItem(this)" disabled>
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="?page=stock_requests" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Submit Request</button>
            </div>
        </form>

        <script>
        let itemIndex = 1;
        
        function addItem() {
            const container = document.getElementById('itemsContainer');
            const template = `
                <div class="row mb-3 item-row">
                    <div class="col-md-4">
                        <select class="form-select" name="items[${itemIndex}][category_id]">
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <input type="text" class="form-control" name="items[${itemIndex}][item_name]" required placeholder="e.g. ONU Router">
                    </div>
                    <div class="col-md-2">
                        <input type="number" class="form-control" name="items[${itemIndex}][quantity_requested]" value="1" min="1" required>
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-outline-danger" onclick="removeItem(this)">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', template);
            itemIndex++;
        }
        
        function removeItem(btn) {
            btn.closest('.item-row').remove();
        }
        </script>

    <?php elseif ($view === 'detail' && $id): ?>
        <?php 
        $request = $stockRequest->getRequest($id);
        $items = $stockRequest->getRequestItems($id);
        ?>
        <?php if ($request): ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">
                    <i class="bi bi-box-arrow-up me-2"></i>Request <?= htmlspecialchars($request['request_number']) ?>
                </h1>
                <a href="?page=stock_requests" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Back
                </a>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Request Details</h5>
                        </div>
                        <div class="card-body">
                            <dl class="row mb-0">
                                <dt class="col-5">Status</dt>
                                <dd class="col-7">
                                    <?php
                                    $statusClass = match($request['status']) {
                                        'pending' => 'bg-warning',
                                        'approved' => 'bg-info',
                                        'picked' => 'bg-primary',
                                        'handed_over' => 'bg-success',
                                        'completed' => 'bg-success',
                                        'rejected', 'cancelled' => 'bg-danger',
                                        default => 'bg-secondary'
                                    };
                                    ?>
                                    <span class="badge <?= $statusClass ?>"><?= $statuses[$request['status']] ?? $request['status'] ?></span>
                                </dd>
                                
                                <dt class="col-5">Type</dt>
                                <dd class="col-7"><?= htmlspecialchars($requestTypes[$request['request_type']] ?? $request['request_type']) ?></dd>
                                
                                <dt class="col-5">Requested By</dt>
                                <dd class="col-7"><?= htmlspecialchars($request['requested_by_name'] ?? '-') ?></dd>
                                
                                <dt class="col-5">Warehouse</dt>
                                <dd class="col-7"><?= htmlspecialchars($request['warehouse_name'] ?? '-') ?></dd>
                                
                                <dt class="col-5">Priority</dt>
                                <dd class="col-7">
                                    <?php
                                    $priorityClass = match($request['priority']) {
                                        'high' => 'bg-danger',
                                        'urgent' => 'bg-warning',
                                        default => 'bg-secondary'
                                    };
                                    ?>
                                    <span class="badge <?= $priorityClass ?>"><?= ucfirst($request['priority']) ?></span>
                                </dd>
                                
                                <dt class="col-5">Required Date</dt>
                                <dd class="col-7"><?= $request['required_date'] ? date('M j, Y', strtotime($request['required_date'])) : '-' ?></dd>
                                
                                <dt class="col-5">Created</dt>
                                <dd class="col-7"><?= date('M j, Y H:i', strtotime($request['created_at'])) ?></dd>
                                
                                <?php if ($request['approved_by_name']): ?>
                                    <dt class="col-5">Approved By</dt>
                                    <dd class="col-7"><?= htmlspecialchars($request['approved_by_name']) ?></dd>
                                <?php endif; ?>
                                
                                <?php if ($request['picked_by_name']): ?>
                                    <dt class="col-5">Picked By</dt>
                                    <dd class="col-7"><?= htmlspecialchars($request['picked_by_name']) ?></dd>
                                <?php endif; ?>
                                
                                <?php if ($request['handed_to_name']): ?>
                                    <dt class="col-5">Handed To</dt>
                                    <dd class="col-7"><?= htmlspecialchars($request['handed_to_name']) ?></dd>
                                <?php endif; ?>
                            </dl>
                            
                            <?php if ($request['notes']): ?>
                                <hr>
                                <p class="mb-0"><strong>Notes:</strong> <?= nl2br(htmlspecialchars($request['notes'])) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Actions</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($request['status'] === 'pending'): ?>
                                <form method="POST" class="mb-2">
                                    <input type="hidden" name="action" value="approve_request">
                                    <input type="hidden" name="id" value="<?= $id ?>">
                                    <button type="submit" class="btn btn-success w-100">
                                        <i class="bi bi-check-lg me-1"></i>Approve Request
                                    </button>
                                </form>
                                <button class="btn btn-danger w-100" data-bs-toggle="modal" data-bs-target="#rejectModal">
                                    <i class="bi bi-x-lg me-1"></i>Reject Request
                                </button>
                            <?php elseif ($request['status'] === 'approved'): ?>
                                <button class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#pickModal">
                                    <i class="bi bi-box-seam me-1"></i>Pick Items
                                </button>
                            <?php elseif ($request['status'] === 'picked'): ?>
                                <button class="btn btn-success w-100" data-bs-toggle="modal" data-bs-target="#handoverModal">
                                    <i class="bi bi-hand-index-thumb me-1"></i>Complete Handover
                                </button>
                            <?php elseif ($request['status'] === 'handed_over'): ?>
                                <form method="POST">
                                    <input type="hidden" name="action" value="complete_request">
                                    <input type="hidden" name="id" value="<?= $id ?>">
                                    <button type="submit" class="btn btn-success w-100">
                                        <i class="bi bi-check-circle me-1"></i>Mark Completed
                                    </button>
                                </form>
                            <?php else: ?>
                                <p class="text-muted mb-0">No actions available</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Request Items</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Category</th>
                                            <th>Item</th>
                                            <th>Requested</th>
                                            <th>Approved</th>
                                            <th>Picked</th>
                                            <th>Used</th>
                                            <th>Returned</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($items as $item): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($item['category_name'] ?? '-') ?></td>
                                                <td>
                                                    <?= htmlspecialchars($item['item_name'] ?? $item['equipment_name'] ?? '-') ?>
                                                    <?php if ($item['serial_number']): ?>
                                                        <small class="text-muted d-block"><?= htmlspecialchars($item['serial_number']) ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= $item['quantity_requested'] ?></td>
                                                <td><?= $item['quantity_approved'] ?></td>
                                                <td><?= $item['quantity_picked'] ?></td>
                                                <td><?= $item['quantity_used'] ?></td>
                                                <td><?= $item['quantity_returned'] ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="rejectModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST">
                            <input type="hidden" name="action" value="reject_request">
                            <input type="hidden" name="id" value="<?= $id ?>">
                            <div class="modal-header">
                                <h5 class="modal-title">Reject Request</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">Rejection Reason</label>
                                    <textarea class="form-control" name="reason" rows="3" required></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-danger">Reject Request</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="pickModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <form method="POST">
                            <input type="hidden" name="action" value="pick_items">
                            <input type="hidden" name="id" value="<?= $id ?>">
                            <div class="modal-header">
                                <h5 class="modal-title">Pick Items</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Item</th>
                                            <th>Approved Qty</th>
                                            <th>Pick Qty</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($items as $item): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($item['item_name'] ?? $item['equipment_name'] ?? '-') ?></td>
                                                <td><?= $item['quantity_approved'] ?></td>
                                                <td>
                                                    <input type="number" class="form-control" name="picked_qty[<?= $item['id'] ?>]" 
                                                           value="<?= $item['quantity_approved'] ?>" min="0" max="<?= $item['quantity_approved'] ?>">
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Confirm Pick</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="handoverModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST">
                            <input type="hidden" name="action" value="handover">
                            <input type="hidden" name="id" value="<?= $id ?>">
                            <div class="modal-header">
                                <h5 class="modal-title">Complete Handover</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">Handed To <span class="text-danger">*</span></label>
                                    <select class="form-select" name="handed_to" required>
                                        <option value="">Select Person</option>
                                        <?php foreach ($users as $user): ?>
                                            <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-success">Complete Handover</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-warning">Request not found.</div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
