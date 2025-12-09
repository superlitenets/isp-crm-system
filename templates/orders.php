<?php
$orderModel = new \App\Order();
try {
    $salespersonModel = new \App\Salesperson($db);
    $activeSalespersons = $salespersonModel->getActive();
} catch (\Throwable $e) {
    $activeSalespersons = [];
    error_log("Salesperson model error: " . $e->getMessage());
}
$action = $_GET['action'] ?? 'list';
$orderId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$order = $orderId ? $orderModel->getById($orderId) : null;

$currentUserId = $_SESSION['user_id'] ?? null;
$canViewAllOrders = \App\Auth::can('orders.view_all') || \App\Auth::isAdmin();
$orderUserFilter = $canViewAllOrders ? null : $currentUserId;
$stats = $orderModel->getStats($orderUserFilter);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-cart3"></i> Orders</h2>
    <div class="d-flex gap-2">
        <a href="?page=orders&action=create" class="btn btn-success">
            <i class="bi bi-plus-circle"></i> New Order
        </a>
        <form method="GET" class="d-flex gap-2">
            <input type="hidden" name="page" value="orders">
            <select name="status" class="form-select form-select-sm" style="width: 140px;" onchange="this.form.submit()">
                <option value="">All Status</option>
                <option value="new" <?= ($_GET['status'] ?? '') === 'new' ? 'selected' : '' ?>>New (Leads)</option>
                <option value="confirmed" <?= ($_GET['status'] ?? '') === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                <option value="converted" <?= ($_GET['status'] ?? '') === 'converted' ? 'selected' : '' ?>>Converted</option>
                <option value="cancelled" <?= ($_GET['status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
            <select name="salesperson" class="form-select form-select-sm" style="width: 150px;" onchange="this.form.submit()">
                <option value="">All Leads</option>
                <?php foreach ($activeSalespersons as $sp): ?>
                <option value="<?= $sp['id'] ?>" <?= ($_GET['salesperson'] ?? '') == $sp['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($sp['name']) ?>
                </option>
                <?php endforeach; ?>
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

<?php if ($action === 'create'): ?>
<?php
$packages = $db->query("SELECT id, name, speed, speed_unit, price FROM service_packages WHERE is_active = TRUE ORDER BY display_order, name")->fetchAll(PDO::FETCH_ASSOC);
$customers = $db->query("SELECT id, name, phone, email, account_number FROM customers ORDER BY name LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-cart-plus"></i> Create New Order</h5>
                <a href="?page=orders" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
            </div>
            <div class="card-body">
                <form method="POST" action="?page=orders">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="action" value="create_order">
                    
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="customer_type" id="new_customer" value="new" checked onchange="toggleCustomerFields()">
                                <label class="form-check-label" for="new_customer">New Customer</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="customer_type" id="existing_customer" value="existing" onchange="toggleCustomerFields()">
                                <label class="form-check-label" for="existing_customer">Existing Customer</label>
                            </div>
                        </div>
                    </div>

                    <div id="existing-customer-section" class="mb-3 d-none">
                        <label class="form-label">Select Customer</label>
                        <select name="customer_id" id="customer_id" class="form-select" onchange="fillCustomerDetails()">
                            <option value="">-- Select Customer --</option>
                            <?php foreach ($customers as $c): ?>
                            <option value="<?= $c['id'] ?>" data-name="<?= htmlspecialchars($c['name']) ?>" 
                                    data-phone="<?= htmlspecialchars($c['phone']) ?>" 
                                    data-email="<?= htmlspecialchars($c['email'] ?? '') ?>">
                                <?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['account_number']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="new-customer-section">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Customer Name <span class="text-danger">*</span></label>
                                <input type="text" name="customer_name" id="customer_name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                                <input type="tel" name="customer_phone" id="customer_phone" class="form-control" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" name="customer_email" id="customer_email" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Location/Address <span class="text-danger">*</span></label>
                                <input type="text" name="customer_address" class="form-control" required placeholder="e.g., Westlands, Nairobi">
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Service Package</label>
                            <select name="package_id" class="form-select">
                                <option value="">-- No Package Selected --</option>
                                <?php foreach ($packages as $pkg): ?>
                                <option value="<?= $pkg['id'] ?>">
                                    <?= htmlspecialchars($pkg['name']) ?> - <?= $pkg['speed'] ?> <?= $pkg['speed_unit'] ?? 'Mbps' ?> (KES <?= number_format($pkg['price'], 2) ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Assigned Salesperson</label>
                            <select name="salesperson_id" class="form-select">
                                <option value="">-- No Salesperson --</option>
                                <?php foreach ($activeSalespersons as $sp): ?>
                                <option value="<?= $sp['id'] ?>"><?= htmlspecialchars($sp['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Any additional notes about this order..."></textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check-circle"></i> Create Order
                        </button>
                        <a href="?page=orders" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function toggleCustomerFields() {
    const isExisting = document.getElementById('existing_customer').checked;
    document.getElementById('existing-customer-section').classList.toggle('d-none', !isExisting);
    document.getElementById('new-customer-section').querySelectorAll('input').forEach(input => {
        if (input.name === 'customer_name' || input.name === 'customer_phone' || input.name === 'customer_address') {
            input.required = !isExisting;
        }
    });
}

function fillCustomerDetails() {
    const select = document.getElementById('customer_id');
    const option = select.options[select.selectedIndex];
    if (option.value) {
        document.getElementById('customer_name').value = option.dataset.name || '';
        document.getElementById('customer_phone').value = option.dataset.phone || '';
        document.getElementById('customer_email').value = option.dataset.email || '';
    }
}
</script>

<?php elseif ($action === 'view' && $order): ?>
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
                    <?php if ($order['salesperson_id']): ?>
                    <tr>
                        <th>Lead By</th>
                        <td>
                            <i class="bi bi-person-badge text-primary"></i>
                            <strong><?= htmlspecialchars($order['salesperson_name']) ?></strong>
                            <?php if ($order['salesperson_phone']): ?>
                            <small class="text-muted">(<?= htmlspecialchars($order['salesperson_phone']) ?>)</small>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Commission</th>
                        <td>
                            <strong>KES <?= number_format($order['commission_amount'] ?? 0, 2) ?></strong>
                            <?php 
                            $commStatus = $order['commission_status'] ?? 'pending';
                            $commColor = $commStatus === 'paid' ? 'success' : 'warning';
                            ?>
                            <span class="badge bg-<?= $commColor ?>"><?= ucfirst($commStatus) ?></span>
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
        
        <?php 
        $waOrder = new \App\WhatsApp();
        if ($waOrder->isEnabled() && !empty($order['customer_phone'])): 
            $waSettings = new \App\Settings();
            $orderReplacements = [
                '{customer_name}' => $order['customer_name'],
                '{order_number}' => $order['order_number'],
                '{package_name}' => $order['package_name'] ?? 'N/A',
                '{amount}' => number_format($order['amount'] ?? 0, 2),
                '{status}' => ucfirst($order['order_status'])
            ];
            
            $orderConfirmMsg = str_replace(array_keys($orderReplacements), array_values($orderReplacements),
                $waSettings->get('wa_template_order_confirmation', "Hi {customer_name},\n\nThank you for your order #{order_number}!\n\nPackage: {package_name}\nAmount: KES {amount}\n\nWe will contact you shortly to schedule installation.\n\nThank you for choosing our services!"));
            $orderProcessingMsg = str_replace(array_keys($orderReplacements), array_values($orderReplacements),
                $waSettings->get('wa_template_order_processing', "Hi {customer_name},\n\nYour order #{order_number} is being processed.\n\nOur team will contact you to schedule the installation.\n\nThank you!"));
            $orderInstallMsg = str_replace(array_keys($orderReplacements), array_values($orderReplacements),
                $waSettings->get('wa_template_order_installation', "Hi {customer_name},\n\nWe're ready to install your service for order #{order_number}.\n\nPlease let us know a convenient time for installation.\n\nThank you!"));
        ?>
        <div class="card mt-4 border-success">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-whatsapp"></i> WhatsApp Notification</h5>
            </div>
            <div class="card-body">
                <p class="small text-muted mb-3">Send order updates via WhatsApp Web:</p>
                
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <a href="<?= htmlspecialchars($waOrder->generateWebLink($order['customer_phone'], $orderConfirmMsg)) ?>" 
                       target="_blank" class="btn btn-outline-success btn-sm"
                       onclick="logOrderWhatsApp(<?= $order['id'] ?>, 'confirmation')">
                        <i class="bi bi-bag-check"></i> Order Confirmation
                    </a>
                    <a href="<?= htmlspecialchars($waOrder->generateWebLink($order['customer_phone'], $orderProcessingMsg)) ?>" 
                       target="_blank" class="btn btn-outline-info btn-sm"
                       onclick="logOrderWhatsApp(<?= $order['id'] ?>, 'processing')">
                        <i class="bi bi-clock"></i> Processing Update
                    </a>
                    <a href="<?= htmlspecialchars($waOrder->generateWebLink($order['customer_phone'], $orderInstallMsg)) ?>" 
                       target="_blank" class="btn btn-outline-primary btn-sm"
                       onclick="logOrderWhatsApp(<?= $order['id'] ?>, 'installation')">
                        <i class="bi bi-tools"></i> Schedule Installation
                    </a>
                </div>
                
                <div class="input-group input-group-sm">
                    <textarea class="form-control" id="customOrderWaMessage" rows="2" placeholder="Type a custom message..."><?= "Hi {$order['customer_name']},\n\nRegarding your order #{$order['order_number']}:\n\n" ?></textarea>
                    <button type="button" class="btn btn-success" onclick="sendOrderWhatsApp()">
                        <i class="bi bi-whatsapp"></i> Send
                    </button>
                </div>
            </div>
        </div>
        
        <script>
        function sendOrderWhatsApp() {
            var message = document.getElementById('customOrderWaMessage').value;
            var phone = '<?= $waOrder->formatPhone($order['customer_phone']) ?>';
            var url = 'https://web.whatsapp.com/send?phone=' + phone + '&text=' + encodeURIComponent(message);
            window.open(url, '_blank');
            logOrderWhatsApp(<?= $order['id'] ?>, 'custom');
        }
        
        function logOrderWhatsApp(orderId, messageType) {
            fetch('?page=api&action=log_whatsapp', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({order_id: orderId, message_type: messageType, phone: '<?= $waOrder->formatPhone($order['customer_phone']) ?>'})
            }).catch(function(e) { console.log('WhatsApp log error:', e); });
        }
        </script>
        <?php endif; ?>
    </div>
</div>

<?php else: ?>
<div class="card">
    <div class="card-body p-0">
        <?php 
        $orderFilters = [
            'status' => $_GET['status'] ?? '',
            'search' => $_GET['search'] ?? '',
            'salesperson_id' => $_GET['salesperson'] ?? ''
        ];
        if (!\App\Auth::can('orders.view_all') && !\App\Auth::isAdmin()) {
            $orderFilters['user_id'] = $_SESSION['user_id'];
        }
        try {
            $orders = $orderModel->getAll($orderFilters);
        } catch (\Throwable $e) {
            $orders = [];
            error_log("Order list error: " . $e->getMessage());
        }
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
                        <th>Lead By</th>
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
                            <?php if ($o['order_status'] === 'new' && $o['salesperson_id']): ?>
                            <br><span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split"></i> Waiting Approval</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($o['customer_name']) ?></strong>
                            <br><small class="text-muted"><?= htmlspecialchars($o['customer_phone']) ?></small>
                        </td>
                        <td><?= htmlspecialchars($o['package_name'] ?? '-') ?></td>
                        <td>
                            <?php if (!empty($o['salesperson_name'])): ?>
                            <i class="bi bi-person-badge text-primary"></i>
                            <?= htmlspecialchars($o['salesperson_name']) ?>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
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
