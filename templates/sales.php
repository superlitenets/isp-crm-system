<?php
$action = $_GET['action'] ?? 'list';
$salespersonModel = new \App\Salesperson($db);

$canViewAll = \App\Auth::can('sales.view_all') || \App\Auth::isAdmin();
$currentUserId = $_SESSION['user_id'] ?? null;
$mySalesperson = $currentUserId ? $salespersonModel->getByUserIdOrCreate($currentUserId) : null;
?>

<div class="container-fluid py-4">
    <?php if ($action === 'list'): ?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-people-fill me-2"></i><?= $canViewAll ? 'Sales Team' : 'My Sales' ?></h2>
            <div>
                <?php if ($canViewAll): ?>
                <a href="?page=sales&action=leaderboard" class="btn btn-outline-primary me-2">
                    <i class="bi bi-trophy me-1"></i>Leaderboard
                </a>
                <a href="?page=sales&action=commissions" class="btn btn-outline-success me-2">
                    <i class="bi bi-cash-stack me-1"></i>All Commissions
                </a>
                <form method="POST" action="?page=sales" class="d-inline me-2">
                    <input type="hidden" name="csrf_token" value="<?= \App\Auth::generateToken() ?>">
                    <input type="hidden" name="action" value="sync_employees">
                    <button type="submit" class="btn btn-outline-warning" onclick="return confirm('This will register all HR employees as salespersons with default commission settings. Continue?')">
                        <i class="bi bi-arrow-repeat me-1"></i>Sync Employees
                    </button>
                </form>
                <a href="?page=sales&action=add" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i>Add Salesperson
                </a>
                <?php elseif ($mySalesperson): ?>
                <a href="?page=sales&action=view&id=<?= $mySalesperson['id'] ?>" class="btn btn-outline-primary me-2">
                    <i class="bi bi-graph-up me-1"></i>My Performance
                </a>
                <a href="?page=sales&action=orders&id=<?= $mySalesperson['id'] ?>" class="btn btn-outline-success">
                    <i class="bi bi-cart me-1"></i>My Orders
                </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Phone</th>
                                <th>Email</th>
                                <th>Commission</th>
                                <th>Total Sales</th>
                                <th>Total Commission</th>
                                <th>Orders</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if ($canViewAll) {
                                $salespersons = $salespersonModel->getAll();
                            } elseif ($mySalesperson) {
                                $salespersons = [$mySalesperson];
                            } else {
                                $salespersons = [];
                            }
                            foreach ($salespersons as $sp): 
                            ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($sp['name']) ?></strong>
                                    <?php if ($sp['employee_name']): ?>
                                        <br><small class="text-muted">Employee: <?= htmlspecialchars($sp['employee_name']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($sp['phone']) ?></td>
                                <td><?= htmlspecialchars($sp['email'] ?? '-') ?></td>
                                <td>
                                    <?php if ($sp['commission_type'] === 'percentage'): ?>
                                        <span class="badge bg-info"><?= number_format($sp['commission_value'], 1) ?>%</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">KES <?= number_format($sp['commission_value'], 2) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>KES <?= number_format($sp['total_sales'], 2) ?></td>
                                <td>KES <?= number_format($sp['total_commission'], 2) ?></td>
                                <td>
                                    <a href="?page=sales&action=orders&id=<?= $sp['id'] ?>" class="text-decoration-none">
                                        <?= $sp['order_count'] ?? 0 ?> orders
                                    </a>
                                </td>
                                <td>
                                    <?php if ($sp['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="?page=sales&action=view&id=<?= $sp['id'] ?>" class="btn btn-outline-primary" title="View">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <?php if ($canViewAll): ?>
                                        <a href="?page=sales&action=edit&id=<?= $sp['id'] ?>" class="btn btn-outline-secondary" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-danger" title="Delete" 
                                                onclick="confirmDelete(<?= $sp['id'] ?>, '<?= htmlspecialchars($sp['name']) ?>')">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($salespersons)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-4 text-muted">
                                    <i class="bi bi-people display-4 d-block mb-2"></i>
                                    <?php if ($canViewAll): ?>
                                    No salespersons found. <a href="?page=sales&action=add">Add your first salesperson</a>
                                    <?php else: ?>
                                    You are not registered as a salesperson. Please contact your administrator.
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <?php elseif ($action === 'add' || $action === 'edit'): ?>
        <?php
        if (!$canViewAll) {
            echo '<div class="alert alert-danger">Access denied. Only administrators can add or edit salespersons.</div>';
            return;
        }
        $sp = null;
        if ($action === 'edit' && isset($_GET['id'])) {
            $sp = $salespersonModel->getById((int)$_GET['id']);
        }
        $employees = (new \App\Employee())->getAll();
        $users = $db->query("SELECT id, name, email FROM users ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        $defaultCommission = $salespersonModel->getDefaultCommission();
        ?>
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-person-plus me-2"></i>
                            <?= $action === 'edit' ? 'Edit Salesperson' : 'Add Salesperson' ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="?page=sales">
                            <input type="hidden" name="csrf_token" value="<?= \App\Auth::generateToken() ?>">
                            <input type="hidden" name="action" value="<?= $sp ? 'update_salesperson' : 'save_salesperson' ?>">
                            <?php if ($sp): ?>
                                <input type="hidden" name="salesperson_id" value="<?= $sp['id'] ?>">
                            <?php endif; ?>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Name <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control" required 
                                           value="<?= htmlspecialchars($sp['name'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Phone <span class="text-danger">*</span></label>
                                    <input type="text" name="phone" class="form-control" required 
                                           value="<?= htmlspecialchars($sp['phone'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control" 
                                           value="<?= htmlspecialchars($sp['email'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Status</label>
                                    <select name="is_active" class="form-select">
                                        <option value="1" <?= (!$sp || $sp['is_active']) ? 'selected' : '' ?>>Active</option>
                                        <option value="0" <?= ($sp && !$sp['is_active']) ? 'selected' : '' ?>>Inactive</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Link to Employee (Optional)</label>
                                    <select name="employee_id" class="form-select">
                                        <option value="">-- None --</option>
                                        <?php foreach ($employees as $emp): ?>
                                            <option value="<?= $emp['id'] ?>" <?= ($sp && $sp['employee_id'] == $emp['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($emp['name']) ?> (<?= htmlspecialchars($emp['employee_id']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Link to User Account (Optional)</label>
                                    <select name="user_id" class="form-select">
                                        <option value="">-- None --</option>
                                        <?php foreach ($users as $user): ?>
                                            <option value="<?= $user['id'] ?>" <?= ($sp && $sp['user_id'] == $user['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($user['name']) ?> (<?= htmlspecialchars($user['email']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <hr>
                            <h6 class="mb-3"><i class="bi bi-percent me-2"></i>Commission Settings</h6>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Commission Type</label>
                                    <select name="commission_type" class="form-select" id="commissionType">
                                        <option value="percentage" <?= (!$sp || $sp['commission_type'] === 'percentage') ? 'selected' : '' ?>>
                                            Percentage (%)
                                        </option>
                                        <option value="fixed" <?= ($sp && $sp['commission_type'] === 'fixed') ? 'selected' : '' ?>>
                                            Fixed Amount (KES)
                                        </option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Commission Value</label>
                                    <div class="input-group">
                                        <span class="input-group-text" id="commissionPrefix">%</span>
                                        <input type="number" step="0.01" name="commission_value" class="form-control" 
                                               value="<?= $sp ? $sp['commission_value'] : $defaultCommission['value'] ?>">
                                    </div>
                                    <small class="text-muted">Default: <?= $defaultCommission['type'] === 'percentage' ? $defaultCommission['value'] . '%' : 'KES ' . $defaultCommission['value'] ?></small>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Notes</label>
                                <textarea name="notes" class="form-control" rows="2"><?= htmlspecialchars($sp['notes'] ?? '') ?></textarea>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-lg me-1"></i>Save Salesperson
                                </button>
                                <a href="?page=sales" class="btn btn-outline-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <script>
        document.getElementById('commissionType').addEventListener('change', function() {
            document.getElementById('commissionPrefix').textContent = this.value === 'percentage' ? '%' : 'KES';
        });
        </script>

    <?php elseif ($action === 'view' && isset($_GET['id'])): ?>
        <?php
        $sp = $salespersonModel->getById((int)$_GET['id']);
        if (!$sp) {
            echo '<div class="alert alert-danger">Salesperson not found.</div>';
            return;
        }
        if (!$canViewAll && (!$mySalesperson || $mySalesperson['id'] != $sp['id'])) {
            echo '<div class="alert alert-danger">Access denied. You can only view your own sales data.</div>';
            return;
        }
        $stats = $salespersonModel->getSalesStats($sp['id']);
        $recentOrders = $salespersonModel->getOrdersBySalesperson($sp['id']);
        $commissions = $salespersonModel->getCommissions($sp['id']);
        ?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-person me-2"></i><?= htmlspecialchars($sp['name']) ?></h2>
            <div>
                <a href="?page=sales&action=edit&id=<?= $sp['id'] ?>" class="btn btn-outline-primary">
                    <i class="bi bi-pencil me-1"></i>Edit
                </a>
                <a href="?page=sales" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Back
                </a>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <h3 class="mb-0"><?= $stats['total_orders'] ?></h3>
                        <small>Total Orders</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <h3 class="mb-0">KES <?= number_format($stats['total_sales'], 0) ?></h3>
                        <small>Total Sales</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body text-center">
                        <h3 class="mb-0">KES <?= number_format($stats['total_commission'], 0) ?></h3>
                        <small>Total Commission</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-dark">
                    <div class="card-body text-center">
                        <h3 class="mb-0">KES <?= number_format($stats['pending_commission'], 0) ?></h3>
                        <small>Pending Commission</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Details</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr>
                                <td class="text-muted">Phone</td>
                                <td><?= htmlspecialchars($sp['phone']) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Email</td>
                                <td><?= htmlspecialchars($sp['email'] ?? '-') ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Commission</td>
                                <td>
                                    <?php if ($sp['commission_type'] === 'percentage'): ?>
                                        <?= number_format($sp['commission_value'], 1) ?>%
                                    <?php else: ?>
                                        KES <?= number_format($sp['commission_value'], 2) ?> per order
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted">Status</td>
                                <td>
                                    <?php if ($sp['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php if ($sp['employee_name']): ?>
                            <tr>
                                <td class="text-muted">Employee</td>
                                <td><?= htmlspecialchars($sp['employee_name']) ?></td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <ul class="nav nav-tabs card-header-tabs" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" data-bs-toggle="tab" href="#orders">Orders</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#commissionsTab">Commissions</a>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="orders">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Order #</th>
                                                <th>Customer</th>
                                                <th>Package</th>
                                                <th>Amount</th>
                                                <th>Status</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach (array_slice($recentOrders, 0, 10) as $order): ?>
                                            <tr>
                                                <td><a href="?page=orders&action=view&id=<?= $order['id'] ?>"><?= htmlspecialchars($order['order_number']) ?></a></td>
                                                <td><?= htmlspecialchars($order['customer_name']) ?></td>
                                                <td><?= htmlspecialchars($order['package_name'] ?? '-') ?></td>
                                                <td>KES <?= number_format($order['amount'], 2) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= $order['order_status'] === 'confirmed' ? 'success' : ($order['order_status'] === 'cancelled' ? 'danger' : 'warning') ?>">
                                                        <?= ucfirst($order['order_status']) ?>
                                                    </span>
                                                </td>
                                                <td><?= date('M j, Y', strtotime($order['created_at'])) ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                            <?php if (empty($recentOrders)): ?>
                                            <tr><td colspan="6" class="text-center text-muted">No orders yet</td></tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="commissionsTab">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Order</th>
                                                <th>Order Amount</th>
                                                <th>Rate</th>
                                                <th>Commission</th>
                                                <th>Status</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($commissions as $comm): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($comm['order_number']) ?></td>
                                                <td>KES <?= number_format($comm['order_amount'], 2) ?></td>
                                                <td>
                                                    <?= $comm['commission_type'] === 'percentage' ? $comm['commission_rate'] . '%' : 'KES ' . number_format($comm['commission_rate'], 2) ?>
                                                </td>
                                                <td><strong>KES <?= number_format($comm['commission_amount'], 2) ?></strong></td>
                                                <td>
                                                    <?php if ($comm['status'] === 'paid'): ?>
                                                        <span class="badge bg-success">Paid</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">Pending</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= date('M j, Y', strtotime($comm['created_at'])) ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                            <?php if (empty($commissions)): ?>
                                            <tr><td colspan="6" class="text-center text-muted">No commissions yet</td></tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <?php elseif ($action === 'leaderboard'): ?>
        <?php $period = $_GET['period'] ?? 'month'; ?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-trophy me-2"></i>Sales Leaderboard</h2>
            <div class="btn-group">
                <a href="?page=sales&action=leaderboard&period=week" class="btn btn-outline-primary <?= $period === 'week' ? 'active' : '' ?>">This Week</a>
                <a href="?page=sales&action=leaderboard&period=month" class="btn btn-outline-primary <?= $period === 'month' ? 'active' : '' ?>">This Month</a>
                <a href="?page=sales&action=leaderboard&period=year" class="btn btn-outline-primary <?= $period === 'year' ? 'active' : '' ?>">This Year</a>
                <a href="?page=sales&action=leaderboard&period=all" class="btn btn-outline-primary <?= $period === 'all' ? 'active' : '' ?>">All Time</a>
            </div>
        </div>

        <div class="row">
            <?php 
            $leaderboard = $salespersonModel->getLeaderboard($period);
            $rank = 1;
            foreach ($leaderboard as $leader): 
            ?>
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="card <?= $rank <= 3 ? 'border-warning' : '' ?>">
                    <div class="card-body d-flex align-items-center">
                        <div class="me-3">
                            <?php if ($rank === 1): ?>
                                <span class="display-5">🥇</span>
                            <?php elseif ($rank === 2): ?>
                                <span class="display-5">🥈</span>
                            <?php elseif ($rank === 3): ?>
                                <span class="display-5">🥉</span>
                            <?php else: ?>
                                <span class="display-6 text-muted">#<?= $rank ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-0"><?= htmlspecialchars($leader['name']) ?></h6>
                            <small class="text-muted"><?= $leader['order_count'] ?> orders</small>
                        </div>
                        <div class="text-end">
                            <div class="fw-bold text-success">KES <?= number_format($leader['total_sales'], 0) ?></div>
                            <small class="text-muted">Commission: KES <?= number_format($leader['total_commission'], 0) ?></small>
                        </div>
                    </div>
                </div>
            </div>
            <?php $rank++; endforeach; ?>
            <?php if (empty($leaderboard)): ?>
            <div class="col-12">
                <div class="alert alert-info">No sales data for this period.</div>
            </div>
            <?php endif; ?>
        </div>

        <div class="mt-3">
            <a href="?page=sales" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Back to Sales Team
            </a>
        </div>

    <?php elseif ($action === 'commissions'): ?>
        <?php
        $status = $_GET['status'] ?? '';
        $stmt = $db->query("
            SELECT sc.*, s.name as salesperson_name, o.order_number, o.customer_name
            FROM sales_commissions sc
            JOIN salespersons s ON sc.salesperson_id = s.id
            JOIN orders o ON sc.order_id = o.id
            " . ($status ? "WHERE sc.status = '$status'" : "") . "
            ORDER BY sc.created_at DESC
        ");
        $allCommissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        ?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-cash-stack me-2"></i>All Commissions</h2>
            <div class="btn-group">
                <a href="?page=sales&action=commissions" class="btn btn-outline-primary <?= !$status ? 'active' : '' ?>">All</a>
                <a href="?page=sales&action=commissions&status=pending" class="btn btn-outline-warning <?= $status === 'pending' ? 'active' : '' ?>">Pending</a>
                <a href="?page=sales&action=commissions&status=paid" class="btn btn-outline-success <?= $status === 'paid' ? 'active' : '' ?>">Paid</a>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Salesperson</th>
                                <th>Order</th>
                                <th>Customer</th>
                                <th>Order Amount</th>
                                <th>Rate</th>
                                <th>Commission</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allCommissions as $comm): ?>
                            <tr>
                                <td><?= htmlspecialchars($comm['salesperson_name']) ?></td>
                                <td><a href="?page=orders&action=view&id=<?= $comm['order_id'] ?>"><?= htmlspecialchars($comm['order_number']) ?></a></td>
                                <td><?= htmlspecialchars($comm['customer_name']) ?></td>
                                <td>KES <?= number_format($comm['order_amount'], 2) ?></td>
                                <td>
                                    <?= $comm['commission_type'] === 'percentage' ? $comm['commission_rate'] . '%' : 'KES ' . number_format($comm['commission_rate'], 2) ?>
                                </td>
                                <td><strong>KES <?= number_format($comm['commission_amount'], 2) ?></strong></td>
                                <td>
                                    <?php if ($comm['status'] === 'paid'): ?>
                                        <span class="badge bg-success">Paid</span>
                                        <br><small class="text-muted"><?= $comm['paid_at'] ? date('M j', strtotime($comm['paid_at'])) : '' ?></small>
                                    <?php else: ?>
                                        <span class="badge bg-warning">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('M j, Y', strtotime($comm['created_at'])) ?></td>
                                <td>
                                    <?php if ($comm['status'] === 'pending'): ?>
                                        <form method="POST" action="?page=sales" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= \App\Auth::generateToken() ?>">
                                            <input type="hidden" name="action" value="pay_commission">
                                            <input type="hidden" name="commission_id" value="<?= $comm['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Mark commission as paid?')">
                                                <i class="bi bi-check-lg"></i> Mark Paid
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($allCommissions)): ?>
                            <tr><td colspan="9" class="text-center text-muted py-4">No commissions found</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="mt-3">
            <a href="?page=sales" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Back to Sales Team
            </a>
        </div>

    <?php endif; ?>
</div>

<script>
function confirmDelete(id, name) {
    if (confirm('Are you sure you want to delete salesperson "' + name + '"? This will also remove their commission history.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '?page=sales';
        form.innerHTML = '<input type="hidden" name="csrf_token" value="<?= \App\Auth::generateToken() ?>">' +
                         '<input type="hidden" name="action" value="delete_salesperson">' +
                         '<input type="hidden" name="salesperson_id" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
    }
}
</script>
