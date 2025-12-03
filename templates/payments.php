<?php
$tab = $_GET['tab'] ?? 'stkpush';
$action = $_GET['action'] ?? 'list';
$mpesa = new \App\Mpesa();
$isConfigured = $mpesa->isConfigured();

$successMessage = $_SESSION['success_message'] ?? null;
$errorMessage = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-phone"></i> M-Pesa Payments</h2>
</div>

<?php if ($successMessage): ?>
<div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle"></i> <?= htmlspecialchars($successMessage) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($errorMessage): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($errorMessage) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (!$isConfigured): ?>
<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle"></i> <strong>M-Pesa not configured!</strong> 
    Go to <a href="?page=settings&subpage=mpesa">Settings > M-Pesa</a> to configure your API credentials.
</div>
<?php endif; ?>

<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'stkpush' ? 'active' : '' ?>" href="?page=payments&tab=stkpush">
            <i class="bi bi-send"></i> STK Push
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'c2b' ? 'active' : '' ?>" href="?page=payments&tab=c2b">
            <i class="bi bi-arrow-down-circle"></i> C2B Payments
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'reports' ? 'active' : '' ?>" href="?page=payments&tab=reports">
            <i class="bi bi-graph-up"></i> Reports
        </a>
    </li>
</ul>

<?php if ($tab === 'stkpush'): ?>
<div class="row">
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-send"></i> Send STK Push</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="?page=payments&tab=stkpush&action=send">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Customer (Optional)</label>
                        <select class="form-select" name="customer_id" id="customerSelect">
                            <option value="">-- Select Customer --</option>
                            <?php 
                            $customerModel = new \App\Customer($db);
                            $customers = $customerModel->getCustomers();
                            foreach ($customers as $cust): 
                            ?>
                            <option value="<?= $cust['id'] ?>" 
                                    data-phone="<?= htmlspecialchars($cust['phone']) ?>"
                                    data-account="<?= htmlspecialchars($cust['account_number']) ?>">
                                <?= htmlspecialchars($cust['name']) ?> (<?= htmlspecialchars($cust['account_number']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Phone Number *</label>
                        <input type="tel" class="form-control" name="phone" id="phoneInput" 
                               placeholder="0712345678" required>
                        <div class="form-text">Format: 07XXXXXXXX or 254XXXXXXXX</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Amount (KES) *</label>
                        <input type="number" class="form-control" name="amount" min="1" max="150000" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Account Reference *</label>
                        <input type="text" class="form-control" name="account_ref" id="accountRefInput" 
                               maxlength="12" placeholder="Invoice/Account No" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <input type="text" class="form-control" name="description" 
                               maxlength="13" placeholder="Payment for..." value="Payment">
                    </div>
                    
                    <button type="submit" class="btn btn-success w-100" <?= !$isConfigured ? 'disabled' : '' ?>>
                        <i class="bi bi-send"></i> Send Payment Request
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-list-ul"></i> STK Push Transactions</h5>
                <form method="GET" class="d-flex gap-2">
                    <input type="hidden" name="page" value="payments">
                    <input type="hidden" name="tab" value="stkpush">
                    <select class="form-select form-select-sm" name="status" style="width: auto;">
                        <option value="">All Status</option>
                        <option value="completed" <?= ($_GET['status'] ?? '') === 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="pending" <?= ($_GET['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="failed" <?= ($_GET['status'] ?? '') === 'failed' ? 'selected' : '' ?>>Failed</option>
                    </select>
                    <input type="text" class="form-control form-control-sm" name="search" 
                           placeholder="Search..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" style="width: 150px;">
                    <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button>
                </form>
            </div>
            <div class="card-body">
                <?php 
                $transactions = $mpesa->getTransactions([
                    'status' => $_GET['status'] ?? '',
                    'search' => $_GET['search'] ?? '',
                    'limit' => 50
                ]);
                ?>
                <?php if (empty($transactions)): ?>
                <div class="text-center text-muted py-5">
                    <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                    <p class="mt-2">No transactions found</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Phone</th>
                                <th>Amount</th>
                                <th>Receipt</th>
                                <th>Account Ref</th>
                                <th>Customer</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $tx): ?>
                            <tr>
                                <td><?= date('M j, H:i', strtotime($tx['created_at'])) ?></td>
                                <td><?= htmlspecialchars($tx['phone_number'] ?? '-') ?></td>
                                <td><strong>KES <?= number_format($tx['amount'] ?? 0, 2) ?></strong></td>
                                <td>
                                    <?php if ($tx['mpesa_receipt_number']): ?>
                                    <code><?= htmlspecialchars($tx['mpesa_receipt_number']) ?></code>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($tx['account_reference'] ?? '-') ?></td>
                                <td>
                                    <?php if ($tx['customer_name']): ?>
                                    <a href="?page=customers&action=view&id=<?= $tx['customer_id'] ?>">
                                        <?= htmlspecialchars($tx['customer_name']) ?>
                                    </a>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $statusClass = match($tx['status']) {
                                        'completed' => 'success',
                                        'pending' => 'warning',
                                        'failed' => 'danger',
                                        default => 'secondary'
                                    };
                                    ?>
                                    <span class="badge bg-<?= $statusClass ?>"><?= ucfirst($tx['status']) ?></span>
                                </td>
                                <td>
                                    <?php if ($tx['status'] === 'pending' && $tx['checkout_request_id']): ?>
                                    <a href="?page=payments&tab=stkpush&action=query&id=<?= $tx['checkout_request_id'] ?>" 
                                       class="btn btn-sm btn-outline-info" title="Check Status">
                                        <i class="bi bi-arrow-repeat"></i>
                                    </a>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" 
                                            onclick="showDetails(<?= htmlspecialchars(json_encode($tx)) ?>)" title="Details">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php elseif ($tab === 'c2b'): ?>
<div class="row">
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-gear"></i> C2B Configuration</h5>
            </div>
            <div class="card-body">
                <p class="text-muted small">
                    C2B (Customer to Business) allows customers to pay to your Paybill/Till number directly. 
                    Register your callback URLs with Safaricom to receive payment notifications.
                </p>
                
                <div class="mb-3">
                    <label class="form-label">Validation URL</label>
                    <input type="text" class="form-control form-control-sm" readonly 
                           value="<?= htmlspecialchars($mpesa->getValidationUrl()) ?>">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Confirmation URL</label>
                    <input type="text" class="form-control form-control-sm" readonly 
                           value="<?= htmlspecialchars($mpesa->getConfirmationUrl()) ?>">
                </div>
                
                <?php 
                $config = $mpesa->getConfig();
                $lastRegistered = $config['c2b_urls_registered'] ?? null;
                ?>
                
                <?php if ($lastRegistered): ?>
                <div class="alert alert-success py-2 small">
                    <i class="bi bi-check-circle"></i> URLs registered on <?= htmlspecialchars($lastRegistered) ?>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="?page=payments&tab=c2b&action=register_urls">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <button type="submit" class="btn btn-primary w-100" <?= !$isConfigured ? 'disabled' : '' ?>>
                        <i class="bi bi-link-45deg"></i> Register C2B URLs
                    </button>
                </form>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-info-circle"></i> How C2B Works</h6>
            </div>
            <div class="card-body small">
                <ol class="mb-0">
                    <li>Customer dials USSD or uses M-Pesa app</li>
                    <li>Enters your Paybill and account number</li>
                    <li>M-Pesa sends validation request</li>
                    <li>On success, confirmation is sent</li>
                    <li>Transaction appears here automatically</li>
                </ol>
            </div>
        </div>
    </div>
    
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-arrow-down-circle"></i> C2B Transactions</h5>
                <form method="GET" class="d-flex gap-2">
                    <input type="hidden" name="page" value="payments">
                    <input type="hidden" name="tab" value="c2b">
                    <input type="text" class="form-control form-control-sm" name="search" 
                           placeholder="Search..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" style="width: 150px;">
                    <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button>
                </form>
            </div>
            <div class="card-body">
                <?php 
                $c2bTransactions = $mpesa->getC2BTransactions([
                    'search' => $_GET['search'] ?? '',
                    'limit' => 50
                ]);
                ?>
                <?php if (empty($c2bTransactions)): ?>
                <div class="text-center text-muted py-5">
                    <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                    <p class="mt-2">No C2B transactions yet</p>
                    <small>Transactions will appear here when customers pay to your Paybill</small>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Transaction ID</th>
                                <th>Phone</th>
                                <th>Amount</th>
                                <th>Bill Ref</th>
                                <th>Name</th>
                                <th>Customer</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($c2bTransactions as $tx): ?>
                            <tr>
                                <td><?= date('M j, H:i', strtotime($tx['created_at'])) ?></td>
                                <td><code><?= htmlspecialchars($tx['trans_id'] ?? '-') ?></code></td>
                                <td><?= htmlspecialchars($tx['msisdn'] ?? '-') ?></td>
                                <td><strong>KES <?= number_format($tx['trans_amount'] ?? 0, 2) ?></strong></td>
                                <td><?= htmlspecialchars($tx['bill_ref_number'] ?? '-') ?></td>
                                <td>
                                    <?= htmlspecialchars(trim(($tx['first_name'] ?? '') . ' ' . ($tx['last_name'] ?? ''))) ?: '-' ?>
                                </td>
                                <td>
                                    <?php if ($tx['customer_name']): ?>
                                    <a href="?page=customers&action=view&id=<?= $tx['customer_id'] ?>">
                                        <?= htmlspecialchars($tx['customer_name']) ?>
                                    </a>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-success"><?= ucfirst($tx['status'] ?? 'received') ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php elseif ($tab === 'reports'): ?>
<?php
$period = $_GET['period'] ?? 'month';
$stats = $mpesa->getPaymentStats($period);

$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
?>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-white-50">Completed</h6>
                        <h3 class="mb-0"><?= number_format($stats['stk']['completed_count'] ?? 0) ?></h3>
                    </div>
                    <i class="bi bi-check-circle" style="font-size: 2rem; opacity: 0.7;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-dark">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-dark-50">Pending</h6>
                        <h3 class="mb-0"><?= number_format($stats['stk']['pending_count'] ?? 0) ?></h3>
                    </div>
                    <i class="bi bi-hourglass-split" style="font-size: 2rem; opacity: 0.7;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-danger text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-white-50">Failed</h6>
                        <h3 class="mb-0"><?= number_format($stats['stk']['failed_count'] ?? 0) ?></h3>
                    </div>
                    <i class="bi bi-x-circle" style="font-size: 2rem; opacity: 0.7;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-white-50">Total Revenue</h6>
                        <h3 class="mb-0">KES <?= number_format($stats['stk']['total_amount'] ?? 0) ?></h3>
                    </div>
                    <i class="bi bi-cash-stack" style="font-size: 2rem; opacity: 0.7;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-pie-chart"></i> STK Push Summary</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-4">
                        <h4 class="text-success"><?= number_format($stats['stk']['completed_count'] ?? 0) ?></h4>
                        <small class="text-muted">Completed</small>
                    </div>
                    <div class="col-4">
                        <h4 class="text-warning"><?= number_format($stats['stk']['pending_count'] ?? 0) ?></h4>
                        <small class="text-muted">Pending</small>
                    </div>
                    <div class="col-4">
                        <h4 class="text-danger"><?= number_format($stats['stk']['failed_count'] ?? 0) ?></h4>
                        <small class="text-muted">Failed</small>
                    </div>
                </div>
                <hr>
                <div class="text-center">
                    <h5>Total: <?= number_format($stats['stk']['total_transactions'] ?? 0) ?> transactions</h5>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-arrow-down-circle"></i> C2B Summary</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6">
                        <h4 class="text-primary"><?= number_format($stats['c2b']['c2b_count'] ?? 0) ?></h4>
                        <small class="text-muted">Transactions</small>
                    </div>
                    <div class="col-6">
                        <h4 class="text-success">KES <?= number_format($stats['c2b']['c2b_amount'] ?? 0) ?></h4>
                        <small class="text-muted">Total Amount</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-table"></i> Payment Report</h5>
        <form method="GET" class="d-flex gap-2">
            <input type="hidden" name="page" value="payments">
            <input type="hidden" name="tab" value="reports">
            <select class="form-select form-select-sm" name="period" onchange="this.form.submit()" style="width: auto;">
                <option value="today" <?= $period === 'today' ? 'selected' : '' ?>>Today</option>
                <option value="week" <?= $period === 'week' ? 'selected' : '' ?>>Last 7 Days</option>
                <option value="month" <?= $period === 'month' ? 'selected' : '' ?>>Last 30 Days</option>
                <option value="year" <?= $period === 'year' ? 'selected' : '' ?>>Last Year</option>
            </select>
            <a href="?page=payments&tab=reports&action=export&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>" 
               class="btn btn-sm btn-outline-success">
                <i class="bi bi-download"></i> Export
            </a>
        </form>
    </div>
    <div class="card-body">
        <?php 
        $allTransactions = $mpesa->getTransactions([
            'date_from' => match($period) {
                'today' => date('Y-m-d'),
                'week' => date('Y-m-d', strtotime('-7 days')),
                'month' => date('Y-m-d', strtotime('-30 days')),
                'year' => date('Y-m-d', strtotime('-365 days')),
                default => date('Y-m-01')
            },
            'date_to' => date('Y-m-d'),
            'limit' => 100
        ]);
        ?>
        
        <?php if (empty($allTransactions)): ?>
        <div class="text-center text-muted py-5">
            <i class="bi bi-calendar-x" style="font-size: 3rem;"></i>
            <p class="mt-2">No transactions in this period</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Phone</th>
                        <th>Amount</th>
                        <th>Receipt</th>
                        <th>Reference</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allTransactions as $tx): ?>
                    <tr>
                        <td><?= date('M j, Y H:i', strtotime($tx['created_at'])) ?></td>
                        <td><span class="badge bg-info">STK</span></td>
                        <td><?= htmlspecialchars($tx['phone_number'] ?? '-') ?></td>
                        <td>KES <?= number_format($tx['amount'] ?? 0, 2) ?></td>
                        <td><code><?= htmlspecialchars($tx['mpesa_receipt_number'] ?? '-') ?></code></td>
                        <td><?= htmlspecialchars($tx['account_reference'] ?? '-') ?></td>
                        <td>
                            <?php
                            $statusClass = match($tx['status']) {
                                'completed' => 'success',
                                'pending' => 'warning',
                                'failed' => 'danger',
                                default => 'secondary'
                            };
                            ?>
                            <span class="badge bg-<?= $statusClass ?>"><?= ucfirst($tx['status']) ?></span>
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

<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-info-circle"></i> Transaction Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailsContent">
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('customerSelect')?.addEventListener('change', function() {
    const selected = this.options[this.selectedIndex];
    if (selected.value) {
        document.getElementById('phoneInput').value = selected.dataset.phone || '';
        document.getElementById('accountRefInput').value = selected.dataset.account || '';
    }
});

function showDetails(tx) {
    let html = '<dl class="row mb-0">';
    html += '<dt class="col-sm-5">Transaction Type</dt><dd class="col-sm-7">' + (tx.transaction_type || '-') + '</dd>';
    html += '<dt class="col-sm-5">Phone Number</dt><dd class="col-sm-7">' + (tx.phone_number || '-') + '</dd>';
    html += '<dt class="col-sm-5">Amount</dt><dd class="col-sm-7">KES ' + parseFloat(tx.amount || 0).toLocaleString() + '</dd>';
    html += '<dt class="col-sm-5">Receipt Number</dt><dd class="col-sm-7"><code>' + (tx.mpesa_receipt_number || '-') + '</code></dd>';
    html += '<dt class="col-sm-5">Account Reference</dt><dd class="col-sm-7">' + (tx.account_reference || '-') + '</dd>';
    html += '<dt class="col-sm-5">Status</dt><dd class="col-sm-7">' + (tx.status || '-') + '</dd>';
    html += '<dt class="col-sm-5">Result Description</dt><dd class="col-sm-7">' + (tx.result_desc || '-') + '</dd>';
    html += '<dt class="col-sm-5">Merchant Request ID</dt><dd class="col-sm-7"><code class="small">' + (tx.merchant_request_id || '-') + '</code></dd>';
    html += '<dt class="col-sm-5">Checkout Request ID</dt><dd class="col-sm-7"><code class="small">' + (tx.checkout_request_id || '-') + '</code></dd>';
    html += '<dt class="col-sm-5">Created</dt><dd class="col-sm-7">' + (tx.created_at || '-') + '</dd>';
    html += '<dt class="col-sm-5">Updated</dt><dd class="col-sm-7">' + (tx.updated_at || '-') + '</dd>';
    html += '</dl>';
    
    document.getElementById('detailsContent').innerHTML = html;
    new bootstrap.Modal(document.getElementById('detailsModal')).show();
}
</script>
