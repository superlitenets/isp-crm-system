<?php
$portalAction = $_GET['portal_action'] ?? 'login';
$portalError = '';
$portalSuccess = '';
$customerData = null;
$ticketCategories = ['connectivity' => 'Connectivity Issue', 'billing' => 'Billing', 'speed' => 'Speed Issue', 'installation' => 'Installation', 'relocation' => 'Relocation', 'other' => 'Other'];

if (isset($_SESSION['portal_customer_id'])) {
    $customer = new \App\Customer();
    $customerData = $customer->find($_SESSION['portal_customer_id']);
    if (!$customerData) {
        unset($_SESSION['portal_customer_id']);
    } else {
        $portalAction = $_GET['portal_action'] ?? 'dashboard';
    }
}

if ($portalAction === 'logout') {
    unset($_SESSION['portal_customer_id']);
    header('Location: ?page=customer-portal');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($portalAction === 'login' || !$customerData)) {
    $accountNumber = trim($_POST['account_number'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if (empty($accountNumber) || empty($phone)) {
        $portalError = 'Please enter both account number and phone number.';
    } else {
        $customer = new \App\Customer();
        $found = $customer->findByAccountNumber($accountNumber);
        if ($found) {
            $normalizedInput = preg_replace('/[^0-9]/', '', $phone);
            $normalizedStored = preg_replace('/[^0-9]/', '', $found['phone'] ?? '');
            if (substr($normalizedInput, -9) === substr($normalizedStored, -9) && strlen($normalizedInput) >= 9) {
                $_SESSION['portal_customer_id'] = $found['id'];
                $customerData = $found;
                $portalAction = 'dashboard';
            } else {
                $portalError = 'Account number and phone number do not match.';
            }
        } else {
            $portalError = 'Account not found. Please check your account number.';
        }
    }
}

if ($customerData && $_SERVER['REQUEST_METHOD'] === 'POST' && ($portalAction === 'submit_ticket' || ($_POST['portal_form'] ?? '') === 'ticket')) {
    $subject = trim($_POST['subject'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = $_POST['category'] ?? 'other';

    if (empty($subject) || empty($description)) {
        $portalError = 'Please fill in all required fields.';
        $portalAction = 'new_ticket';
    } else {
        try {
            $ticket = new \App\Ticket();
            $ticketId = $ticket->create([
                'customer_id' => $customerData['id'],
                'subject' => $subject,
                'description' => $description,
                'category' => $category,
                'priority' => 'medium',
                'created_by' => null
            ]);
            $portalSuccess = 'Your ticket has been submitted successfully!';
            $portalAction = 'tickets';
        } catch (\Exception $e) {
            $portalError = 'Failed to submit ticket. Please try again.';
            $portalAction = 'new_ticket';
        }
    }
}

if ($customerData && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['portal_form'] ?? '') === 'mpesa_pay') {
    $payPhone = trim($_POST['pay_phone'] ?? $customerData['phone']);
    $payAmount = (float)($_POST['pay_amount'] ?? 0);

    if ($payAmount < 1) {
        $portalError = 'Please enter a valid amount.';
    } else {
        try {
            $mpesa = new \App\Mpesa();
            $result = $mpesa->stkPush($payPhone, $payAmount, $customerData['account_number'], 'Payment', $customerData['id']);
            if ($result['success']) {
                $portalSuccess = 'Payment request sent to your phone. Please enter your M-Pesa PIN to complete.';
            } else {
                $portalError = $result['message'] ?? 'Payment request failed. Please try again.';
            }
        } catch (\Exception $e) {
            $portalError = 'Payment service unavailable. Please try again later.';
        }
    }
    $portalAction = 'payments';
}

$lastPayment = null;
$outstandingBalance = 0;
$tickets = [];
$connectionStatus = 'unknown';

if ($customerData) {
    $connectionStatus = $customerData['connection_status'] ?? 'unknown';

    try {
        $stmt = $db->prepare("SELECT * FROM mpesa_c2b_transactions WHERE customer_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$customerData['id']]);
        $lastPayment = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (\Exception $e) {}

    if (!$lastPayment) {
        try {
            $stmt = $db->prepare("SELECT * FROM mpesa_transactions WHERE customer_id = ? AND status = 'completed' ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$customerData['id']]);
            $lastPayment = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {}
    }

    try {
        $stmt = $db->prepare("SELECT COALESCE(SUM(balance_due), 0) as total_due FROM invoices WHERE customer_id = ? AND status != 'paid'");
        $stmt->execute([$customerData['id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $outstandingBalance = (float)($row['total_due'] ?? 0);
    } catch (\Exception $e) {}

    try {
        $stmt = $db->prepare("SELECT id, ticket_number, subject, category, priority, status, created_at, resolved_at FROM tickets WHERE customer_id = ? ORDER BY created_at DESC LIMIT 20");
        $stmt->execute([$customerData['id']]);
        $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Portal - ISP CRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root { --primary: #0d6efd; --success: #198754; --warning: #ffc107; --danger: #dc3545; }
        body { background: #f0f2f5; min-height: 100vh; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        .portal-nav { background: linear-gradient(135deg, #1a1c2c 0%, #2d3250 100%); padding: 1rem 0; }
        .portal-nav .brand { color: #fff; font-size: 1.25rem; font-weight: 600; text-decoration: none; }
        .portal-nav .nav-link { color: rgba(255,255,255,0.8); }
        .portal-nav .nav-link:hover, .portal-nav .nav-link.active { color: #fff; }
        .login-card { background: #fff; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.08); padding: 2.5rem; max-width: 420px; margin: 3rem auto; }
        .login-card .login-icon { font-size: 3rem; color: var(--primary); }
        .stat-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); padding: 1.5rem; text-align: center; transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-2px); }
        .stat-card .stat-icon { font-size: 2rem; margin-bottom: 0.5rem; }
        .stat-card .stat-value { font-size: 1.5rem; font-weight: 700; }
        .stat-card .stat-label { color: #6c757d; font-size: 0.85rem; }
        .status-badge { padding: 0.35rem 0.75rem; border-radius: 50px; font-size: 0.8rem; font-weight: 600; }
        .status-active { background: #d1e7dd; color: #0f5132; }
        .status-suspended { background: #fff3cd; color: #664d03; }
        .status-disconnected { background: #f8d7da; color: #842029; }
        .status-pending { background: #cff4fc; color: #055160; }
        .status-open { background: #cff4fc; color: #055160; }
        .status-in_progress { background: #fff3cd; color: #664d03; }
        .status-resolved { background: #d1e7dd; color: #0f5132; }
        .status-closed { background: #e2e3e5; color: #41464b; }
        .content-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); padding: 1.5rem; margin-bottom: 1.5rem; }
        .portal-footer { text-align: center; color: #6c757d; padding: 2rem 0; font-size: 0.85rem; }
        @media (max-width: 576px) { .login-card { margin: 1rem; padding: 1.5rem; } .stat-card { margin-bottom: 1rem; } }
    </style>
</head>
<body>

<?php if ($customerData): ?>
<nav class="portal-nav">
    <div class="container d-flex justify-content-between align-items-center">
        <a href="?page=customer-portal" class="brand"><i class="bi bi-router me-2"></i>Customer Portal</a>
        <div class="d-flex align-items-center gap-3">
            <span class="text-white-50 d-none d-sm-inline"><?= htmlspecialchars($customerData['name']) ?></span>
            <a href="?page=customer-portal&portal_action=logout" class="nav-link"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </div>
    </div>
</nav>

<div class="container py-4">

    <?php if ($portalError): ?>
        <div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($portalError) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if ($portalSuccess): ?>
        <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($portalSuccess) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="d-flex flex-wrap gap-2 mb-4">
        <a href="?page=customer-portal&portal_action=dashboard" class="btn btn-<?= $portalAction === 'dashboard' ? 'primary' : 'outline-primary' ?> btn-sm"><i class="bi bi-speedometer2 me-1"></i>Overview</a>
        <a href="?page=customer-portal&portal_action=tickets" class="btn btn-<?= in_array($portalAction, ['tickets', 'new_ticket']) ? 'primary' : 'outline-primary' ?> btn-sm"><i class="bi bi-ticket me-1"></i>Tickets</a>
        <a href="?page=customer-portal&portal_action=payments" class="btn btn-<?= $portalAction === 'payments' ? 'primary' : 'outline-primary' ?> btn-sm"><i class="bi bi-credit-card me-1"></i>Payments</a>
    </div>

    <?php if ($portalAction === 'dashboard'): ?>
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-icon <?= $connectionStatus === 'active' ? 'text-success' : 'text-danger' ?>">
                        <i class="bi bi-<?= $connectionStatus === 'active' ? 'wifi' : 'wifi-off' ?>"></i>
                    </div>
                    <div class="stat-value">
                        <span class="status-badge status-<?= htmlspecialchars($connectionStatus) ?>"><?= ucfirst(htmlspecialchars($connectionStatus)) ?></span>
                    </div>
                    <div class="stat-label">Connection Status</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-icon text-primary"><i class="bi bi-speedometer"></i></div>
                    <div class="stat-value" style="font-size:1rem;"><?= htmlspecialchars(ucfirst($customerData['service_plan'] ?? 'N/A')) ?></div>
                    <div class="stat-label">Current Plan</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-icon text-success"><i class="bi bi-cash-stack"></i></div>
                    <div class="stat-value"><?php
                        if ($lastPayment) {
                            echo 'KES ' . number_format((float)($lastPayment['trans_amount'] ?? $lastPayment['amount'] ?? 0));
                        } else {
                            echo 'None';
                        }
                    ?></div>
                    <div class="stat-label">Last Payment<?php if ($lastPayment) { echo '<br><small>' . date('M j, Y', strtotime($lastPayment['created_at'] ?? $lastPayment['trans_time'] ?? 'now')) . '</small>'; } ?></div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-icon <?= $outstandingBalance > 0 ? 'text-danger' : 'text-success' ?>"><i class="bi bi-receipt"></i></div>
                    <div class="stat-value">KES <?= number_format($outstandingBalance) ?></div>
                    <div class="stat-label">Outstanding Balance</div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <div class="content-card">
                    <h6><i class="bi bi-person me-2"></i>Account Details</h6>
                    <hr>
                    <table class="table table-sm table-borderless mb-0">
                        <tr><td class="text-muted" style="width:40%">Account No.</td><td class="fw-semibold"><?= htmlspecialchars($customerData['account_number'] ?? '') ?></td></tr>
                        <tr><td class="text-muted">Name</td><td><?= htmlspecialchars($customerData['name'] ?? '') ?></td></tr>
                        <tr><td class="text-muted">Phone</td><td><?= htmlspecialchars($customerData['phone'] ?? '') ?></td></tr>
                        <tr><td class="text-muted">Email</td><td><?= htmlspecialchars($customerData['email'] ?? 'N/A') ?></td></tr>
                        <tr><td class="text-muted">Address</td><td><?= htmlspecialchars($customerData['address'] ?? 'N/A') ?></td></tr>
                    </table>
                </div>
            </div>
            <div class="col-md-6">
                <div class="content-card">
                    <h6><i class="bi bi-lightning me-2"></i>Quick Actions</h6>
                    <hr>
                    <div class="d-grid gap-2">
                        <a href="?page=customer-portal&portal_action=new_ticket" class="btn btn-outline-primary"><i class="bi bi-plus-circle me-2"></i>Raise Support Ticket</a>
                        <a href="?page=customer-portal&portal_action=payments" class="btn btn-outline-success"><i class="bi bi-phone me-2"></i>Make M-Pesa Payment</a>
                        <a href="?page=customer-portal&portal_action=tickets" class="btn btn-outline-secondary"><i class="bi bi-list-check me-2"></i>View Ticket History</a>
                    </div>
                </div>
            </div>
        </div>

    <?php elseif ($portalAction === 'new_ticket'): ?>
        <div class="content-card" style="max-width:600px;">
            <h5><i class="bi bi-plus-circle me-2"></i>Raise a Support Ticket</h5>
            <hr>
            <form method="POST" action="?page=customer-portal&portal_action=submit_ticket">
                <input type="hidden" name="portal_form" value="ticket">
                <div class="mb-3">
                    <label class="form-label">Category <span class="text-danger">*</span></label>
                    <select name="category" class="form-select" required>
                        <?php foreach ($ticketCategories as $val => $label): ?>
                            <option value="<?= $val ?>"><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Subject <span class="text-danger">*</span></label>
                    <input type="text" name="subject" class="form-control" required maxlength="200" placeholder="Brief description of your issue">
                </div>
                <div class="mb-3">
                    <label class="form-label">Description <span class="text-danger">*</span></label>
                    <textarea name="description" class="form-control" rows="4" required placeholder="Please describe your issue in detail..."></textarea>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i>Submit Ticket</button>
                    <a href="?page=customer-portal&portal_action=tickets" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>

    <?php elseif ($portalAction === 'tickets'): ?>
        <div class="content-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0"><i class="bi bi-ticket me-2"></i>My Tickets</h5>
                <a href="?page=customer-portal&portal_action=new_ticket" class="btn btn-primary btn-sm"><i class="bi bi-plus me-1"></i>New Ticket</a>
            </div>
            <?php if (empty($tickets)): ?>
                <div class="text-center text-muted py-4">
                    <i class="bi bi-inbox" style="font-size:2.5rem;"></i>
                    <p class="mt-2">No tickets found.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Ticket #</th>
                                <th>Subject</th>
                                <th class="d-none d-md-table-cell">Category</th>
                                <th>Status</th>
                                <th class="d-none d-sm-table-cell">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tickets as $t): ?>
                                <tr>
                                    <td class="fw-semibold"><?= htmlspecialchars($t['ticket_number']) ?></td>
                                    <td><?= htmlspecialchars($t['subject']) ?></td>
                                    <td class="d-none d-md-table-cell"><?= ucfirst(htmlspecialchars($t['category'])) ?></td>
                                    <td><span class="status-badge status-<?= htmlspecialchars($t['status']) ?>"><?= ucfirst(str_replace('_', ' ', $t['status'])) ?></span></td>
                                    <td class="d-none d-sm-table-cell"><?= date('M j, Y', strtotime($t['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    <?php elseif ($portalAction === 'payments'): ?>
        <div class="row g-3">
            <div class="col-md-6">
                <div class="content-card">
                    <h5><i class="bi bi-phone me-2"></i>Make M-Pesa Payment</h5>
                    <hr>
                    <form method="POST" action="?page=customer-portal&portal_action=payments">
                        <input type="hidden" name="portal_form" value="mpesa_pay">
                        <div class="mb-3">
                            <label class="form-label">Phone Number</label>
                            <input type="text" name="pay_phone" class="form-control" value="<?= htmlspecialchars($customerData['phone'] ?? '') ?>" required placeholder="e.g. 0712345678">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Amount (KES)</label>
                            <input type="number" name="pay_amount" class="form-control" min="1" step="1" required placeholder="Enter amount" <?= $outstandingBalance > 0 ? 'value="' . (int)$outstandingBalance . '"' : '' ?>>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Account Reference</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($customerData['account_number'] ?? '') ?>" readonly>
                        </div>
                        <button type="submit" class="btn btn-success w-100"><i class="bi bi-send me-1"></i>Send Payment Request</button>
                    </form>
                </div>
            </div>
            <div class="col-md-6">
                <div class="content-card">
                    <h5><i class="bi bi-clock-history me-2"></i>Payment Summary</h5>
                    <hr>
                    <div class="mb-3">
                        <div class="text-muted small">Outstanding Balance</div>
                        <div class="fs-4 fw-bold <?= $outstandingBalance > 0 ? 'text-danger' : 'text-success' ?>">KES <?= number_format($outstandingBalance, 2) ?></div>
                    </div>
                    <?php if ($lastPayment): ?>
                        <div class="mb-3">
                            <div class="text-muted small">Last Payment</div>
                            <div class="fw-semibold">KES <?= number_format((float)($lastPayment['trans_amount'] ?? $lastPayment['amount'] ?? 0), 2) ?></div>
                            <div class="text-muted small"><?= date('M j, Y g:i A', strtotime($lastPayment['created_at'] ?? $lastPayment['trans_time'] ?? 'now')) ?></div>
                            <?php if (!empty($lastPayment['trans_id'] ?? $lastPayment['mpesa_receipt_number'] ?? '')): ?>
                                <div class="text-muted small">Ref: <?= htmlspecialchars($lastPayment['trans_id'] ?? $lastPayment['mpesa_receipt_number'] ?? '') ?></div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No payment records found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

</div>

<?php else: ?>

<div style="background: linear-gradient(135deg, #1a1c2c 0%, #2d3250 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center;">
    <div class="login-card">
        <div class="text-center mb-4">
            <i class="bi bi-router login-icon"></i>
            <h4 class="mt-2">Customer Portal</h4>
            <p class="text-muted">Login with your account details</p>
        </div>

        <?php if ($portalError): ?>
            <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($portalError) ?></div>
        <?php endif; ?>

        <form method="POST" action="?page=customer-portal">
            <div class="mb-3">
                <label class="form-label">Account Number</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person-badge"></i></span>
                    <input type="text" name="account_number" class="form-control" placeholder="e.g. ISP-2025-00001" required value="<?= htmlspecialchars($_POST['account_number'] ?? '') ?>">
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label">Phone Number</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-phone"></i></span>
                    <input type="text" name="phone" class="form-control" placeholder="e.g. 0712345678" required value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-box-arrow-in-right me-2"></i>Login</button>
        </form>
        <div class="text-center mt-3">
            <a href="?page=login" class="text-muted small">Staff Login</a>
        </div>
    </div>
</div>

<?php endif; ?>

<div class="portal-footer">
    <p>&copy; <?= date('Y') ?> ISP CRM. All rights reserved.</p>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
