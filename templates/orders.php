<?php
$orderModel = new \App\Order();
$action = $_GET['action'] ?? 'list';
$orderId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$order = $orderId ? $orderModel->getById($orderId) : null;
$stats = $orderModel->getStats();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-cart3"></i> Orders</h2>
    <div class="d-flex gap-2">
        <form method="GET" class="d-flex gap-2">
            <input type="hidden" name="page" value="orders">
            <select name="status" class="form-select form-select-sm" style="width: 140px;" onchange="this.form.submit()">
                <option value="">All Status</option>
                <option value="new" <?= ($_GET['status'] ?? '') === 'new' ? 'selected' : '' ?>>New</option>
                <option value="confirmed" <?= ($_GET['status'] ?? '') === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                <option value="converted" <?= ($_GET['status'] ?? '') === 'converted' ? 'selected' : '' ?>>Converted</option>
                <option value="cancelled" <?= ($_GET['status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
            <input type="text" class="form-control form-control-sm" name="search" 
                   placeholder="Search orders..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" style="width: 200px;">
            <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button>
        </form>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-0 bg-primary bg-opacity-10">
            <div class="card-body text-center">
                <h3 class="mb-0 text-primary"><?= number_format($stats['total'] ?? 0) ?></h3>
                <small class="text-muted">Total Orders</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 bg-warning bg-opacity-10">
            <div class="card-body text-center">
                <h3 class="mb-0 text-warning"><?= number_format($stats['new_orders'] ?? 0) ?></h3>
                <small class="text-muted">New Orders</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 bg-success bg-opacity-10">
            <div class="card-body text-center">
                <h3 class="mb-0 text-success"><?= number_format($stats['confirmed'] ?? 0) ?></h3>
                <small class="text-muted">Confirmed</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 bg-info bg-opacity-10">
            <div class="card-body text-center">
                <h3 class="mb-0 text-info">KES <?= number_format($stats['total_paid'] ?? 0) ?></h3>
                <small class="text-muted">Revenue</small>
            </div>
        </div>
    </div>
</div>

<?php if ($action === 'view' && $order): ?>
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="bi bi-cart"></i> Order <?= htmlspecialchars($order['order_number']) ?>
        </h5>
        <div class="d-flex gap-2">
            <?php if ($order['order_status'] === 'new'): ?>
            <form method="POST" action="?page=orders&action=confirm&id=<?= $order['id'] ?>" class="d-inline">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <button type="submit" class="btn btn-success btn-sm">
                    <i class="bi bi-check-lg"></i> Confirm Order
                </button>
            </form>
            <?php endif; ?>
            <?php if ($order['order_status'] === 'confirmed' && !$order['ticket_id']): ?>
            <form method="POST" action="?page=orders&action=convert&id=<?= $order['id'] ?>" class="d-inline">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-ticket"></i> Convert to Ticket
                </button>
            </form>
            <?php endif; ?>
            <?php if ($order['order_status'] !== 'cancelled' && $order['order_status'] !== 'converted'): ?>
            <form method="POST" action="?page=orders&action=cancel&id=<?= $order['id'] ?>" class="d-inline" 
                  onsubmit="return confirm('Are you sure you want to cancel this order?')">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <button type="submit" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-x-lg"></i> Cancel
                </button>
            </form>
            <?php endif; ?>
            <a href="?page=orders" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h6 class="text-muted mb-3">Customer Details</h6>
                <table class="table table-sm">
                    <tr>
                        <th style="width: 140px;">Name</th>
                        <td><?= htmlspecialchars($order['customer_name']) ?></td>
                    </tr>
                    <tr>
                        <th>Phone</th>
                        <td>
                            <a href="tel:<?= htmlspecialchars($order['customer_phone']) ?>">
                                <?= htmlspecialchars($order['customer_phone']) ?>
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <th>Email</th>
                        <td>
                            <?php if ($order['customer_email']): ?>
                            <a href="mailto:<?= htmlspecialchars($order['customer_email']) ?>">
                                <?= htmlspecialchars($order['customer_email']) ?>
                            </a>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Address</th>
                        <td><?= nl2br(htmlspecialchars($order['customer_address'] ?? '-')) ?></td>
                    </tr>
                    <?php if ($order['customer_id']): ?>
                    <tr>
                        <th>Linked Customer</th>
                        <td>
                            <a href="?page=customers&action=view&id=<?= $order['customer_id'] ?>">
                                <?= htmlspecialchars($order['linked_customer_name']) ?>
                                (<?= htmlspecialchars($order['account_number']) ?>)
                            </a>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
            <div class="col-md-6">
                <h6 class="text-muted mb-3">Order Details</h6>
                <table class="table table-sm">
                    <tr>
                        <th style="width: 140px;">Order Number</th>
                        <td><code><?= htmlspecialchars($order['order_number']) ?></code></td>
                    </tr>
                    <tr>
                        <th>Package</th>
                        <td>
                            <?php if ($order['package_name']): ?>
                            <?= htmlspecialchars($order['package_name']) ?>
                            (<?= htmlspecialchars($order['speed']) ?> <?= htmlspecialchars($order['speed_unit'] ?? 'Mbps') ?>)
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Amount</th>
                        <td><strong>KES <?= number_format($order['amount'] ?? 0, 2) ?></strong></td>
                    </tr>
                    <tr>
                        <th>Order Status</th>
                        <td>
                            <?php
                            $statusColors = ['new' => 'warning', 'confirmed' => 'success', 'converted' => 'info', 'cancelled' => 'secondary'];
                            $color = $statusColors[$order['order_status']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?= $color ?>"><?= ucfirst($order['order_status']) ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th>Payment</th>
                        <td>
                            <?php
                            $payColors = ['pending' => 'warning', 'paid' => 'success', 'failed' => 'danger'];
                            $pColor = $payColors[$order['payment_status']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?= $pColor ?>"><?= ucfirst($order['payment_status']) ?></span>
                            <?php if ($order['payment_method']): ?>
                            <small class="text-muted">(<?= ucfirst($order['payment_method']) ?>)</small>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Created</th>
                        <td><?= date('M j, Y H:i', strtotime($order['created_at'])) ?></td>
                    </tr>
                    <?php if ($order['ticket_id']): ?>
                    <tr>
                        <th>Ticket</th>
                        <td>
                            <a href="?page=tickets&action=view&id=<?= $order['ticket_id'] ?>">
                                <i class="bi bi-ticket"></i> <?= htmlspecialchars($order['ticket_number']) ?>
                            </a>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
                
                <?php if ($order['notes']): ?>
                <h6 class="text-muted mb-2 mt-4">Notes</h6>
                <div class="bg-light p-3 rounded">
                    <?= nl2br(htmlspecialchars($order['notes'])) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<div class="card">
    <div class="card-body p-0">
        <?php 
        $orders = $orderModel->getAll([
            'status' => $_GET['status'] ?? '',
            'search' => $_GET['search'] ?? ''
        ]);
        ?>
        <?php if (empty($orders)): ?>
        <div class="text-center py-5 text-muted">
            <i class="bi bi-cart" style="font-size: 3rem;"></i>
            <p class="mt-2">No orders found</p>
            <small>Orders from your website will appear here</small>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Package</th>
                        <th>Amount</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $o): ?>
                    <tr>
                        <td>
                            <a href="?page=orders&action=view&id=<?= $o['id'] ?>">
                                <code><?= htmlspecialchars($o['order_number']) ?></code>
                            </a>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($o['customer_name']) ?></strong>
                            <br><small class="text-muted"><?= htmlspecialchars($o['customer_phone']) ?></small>
                        </td>
                        <td><?= htmlspecialchars($o['package_name'] ?? '-') ?></td>
                        <td>KES <?= number_format($o['amount'] ?? 0) ?></td>
                        <td>
                            <?php
                            $payColors = ['pending' => 'warning', 'paid' => 'success', 'failed' => 'danger'];
                            $pColor = $payColors[$o['payment_status']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?= $pColor ?>"><?= ucfirst($o['payment_status']) ?></span>
                        </td>
                        <td>
                            <?php
                            $statusColors = ['new' => 'warning', 'confirmed' => 'success', 'converted' => 'info', 'cancelled' => 'secondary'];
                            $color = $statusColors[$o['order_status']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?= $color ?>"><?= ucfirst($o['order_status']) ?></span>
                            <?php if ($o['ticket_number']): ?>
                            <br><small><a href="?page=tickets&action=view&id=<?= $o['ticket_id'] ?>"><i class="bi bi-ticket"></i> <?= htmlspecialchars($o['ticket_number']) ?></a></small>
                            <?php endif; ?>
                        </td>
                        <td><?= date('M j, H:i', strtotime($o['created_at'])) ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="?page=orders&action=view&id=<?= $o['id'] ?>" class="btn btn-outline-primary" title="View">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <?php if ($o['order_status'] === 'new'): ?>
                                <form method="POST" action="?page=orders&action=confirm&id=<?= $o['id'] ?>" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                    <button type="submit" class="btn btn-outline-success" title="Confirm">
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                                <?php if ($o['order_status'] === 'confirmed' && !$o['ticket_id']): ?>
                                <form method="POST" action="?page=orders&action=convert&id=<?= $o['id'] ?>" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                    <button type="submit" class="btn btn-outline-info" title="Convert to Ticket">
                                        <i class="bi bi-ticket"></i>
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
