<?php
$subpage = $_GET['subpage'] ?? 'overview';
$currentUserId = \App\Auth::user()['id'] ?? null;

$mobileApi = new \App\MobileAPI($db);
$employeeRecord = $mobileApi->getEmployeeByUserId($currentUserId, true);

$leaveService = new \App\Leave($db);
$advanceService = new \App\SalaryAdvance($db);
$settings = new \App\Settings($db);
$currencySymbol = $settings->get('currency_symbol', 'KES');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $employeeRecord) {
    if (!\App\Auth::validateToken($_POST['csrf_token'] ?? '')) {
        $errorMsg = 'Invalid request. Please try again.';
    } else {
        $postAction = $_POST['action'] ?? '';
    
    if ($postAction === 'request_leave') {
        try {
            $leaveService->createRequest([
                'employee_id' => $employeeRecord['id'],
                'leave_type_id' => (int)$_POST['leave_type_id'],
                'start_date' => $_POST['start_date'],
                'end_date' => $_POST['end_date'],
                'is_half_day' => !empty($_POST['is_half_day']),
                'half_day_type' => $_POST['half_day_type'] ?? null,
                'reason' => $_POST['reason'] ?? null
            ]);
            $successMsg = 'Leave request submitted successfully!';
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
        }
    }
    
    if ($postAction === 'request_advance') {
        try {
            $advanceService->create([
                'employee_id' => $employeeRecord['id'],
                'amount' => (float)$_POST['amount'],
                'reason' => $_POST['reason'] ?? null,
                'repayment_type' => $_POST['repayment_type'] ?? 'monthly',
                'repayment_installments' => (int)($_POST['installments'] ?? 1)
            ]);
            $successMsg = 'Salary advance request submitted for approval!';
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
        }
    }
    
    if ($postAction === 'cancel_leave' && !empty($_POST['request_id'])) {
        try {
            $request = $leaveService->getRequest((int)$_POST['request_id']);
            if ($request && $request['employee_id'] == $employeeRecord['id'] && $request['status'] === 'pending') {
                $leaveService->cancel((int)$_POST['request_id']);
                $successMsg = 'Leave request cancelled.';
            }
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
        }
    }

    if ($postAction === 'sign_contract' && !empty($_POST['contract_id'])) {
        try {
            $contractId = (int)$_POST['contract_id'];
            $chk = $db->prepare("SELECT * FROM employee_contracts WHERE id = ? AND employee_id = ? AND status = 'pending'");
            $chk->execute([$contractId, $employeeRecord['id']]);
            $contract = $chk->fetch(PDO::FETCH_ASSOC);
            if (!$contract) {
                $errorMsg = 'Contract not found or already signed.';
            } elseif (empty($_POST['signature_data'])) {
                $errorMsg = 'Please provide your signature.';
            } elseif (empty($_POST['agree_terms'])) {
                $errorMsg = 'You must agree to the contract terms.';
            } else {
                $signerIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                $signerName = trim($_POST['signer_name'] ?? $employeeRecord['name']);
                $upd = $db->prepare("
                    UPDATE employee_contracts 
                    SET status = 'signed', signed_at = CURRENT_TIMESTAMP, signature_data = ?, signer_ip = ?, signer_name = ?, viewed_at = COALESCE(viewed_at, CURRENT_TIMESTAMP)
                    WHERE id = ? AND employee_id = ?
                ");
                $upd->execute([$_POST['signature_data'], $signerIp, $signerName, $contractId, $employeeRecord['id']]);
                $successMsg = 'Contract signed successfully!';
            }
        } catch (\Exception $e) {
            $errorMsg = 'Error signing contract: ' . $e->getMessage();
        }
    }
    }
}

$leaveBalance = $employeeRecord ? $leaveService->getEmployeeBalance($employeeRecord['id']) : [];
$leaveRequests = $employeeRecord ? $leaveService->getEmployeeRequests($employeeRecord['id']) : [];
$leaveTypes = $leaveService->getLeaveTypes();
$advances = $employeeRecord ? $advanceService->getByEmployee($employeeRecord['id']) : [];
$totalOutstanding = $employeeRecord ? $advanceService->getEmployeeTotalOutstanding($employeeRecord['id']) : 0;

$employeeContracts = [];
if ($employeeRecord) {
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS employee_contracts (
            id SERIAL PRIMARY KEY, employee_id INTEGER NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
            title VARCHAR(255) NOT NULL, description TEXT, contract_type VARCHAR(50) DEFAULT 'employment',
            content TEXT, file_path VARCHAR(500), status VARCHAR(30) DEFAULT 'pending',
            created_by INTEGER REFERENCES users(id), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            sent_at TIMESTAMP, viewed_at TIMESTAMP, signed_at TIMESTAMP, signature_data TEXT,
            signer_ip VARCHAR(45), signer_name VARCHAR(255), expires_at TIMESTAMP, notes TEXT
        )");
        $cStmt = $db->prepare("SELECT ec.*, u.username as created_by_name FROM employee_contracts ec LEFT JOIN users u ON ec.created_by = u.id WHERE ec.employee_id = ? ORDER BY ec.created_at DESC");
        $cStmt->execute([$employeeRecord['id']]);
        $employeeContracts = $cStmt->fetchAll(PDO::FETCH_ASSOC);
        if (($subpage ?? '') === 'contracts' && !empty($_GET['view'])) {
            $viewId = (int)$_GET['view'];
            $markViewed = $db->prepare("UPDATE employee_contracts SET viewed_at = COALESCE(viewed_at, CURRENT_TIMESTAMP) WHERE id = ? AND employee_id = ?");
            $markViewed->execute([$viewId, $employeeRecord['id']]);
        }
    } catch (\Exception $e) { $employeeContracts = []; }
}
$pendingContracts = array_filter($employeeContracts, fn($c) => $c['status'] === 'pending');

$walletData = [];
if ($employeeRecord) {
    $empId = $employeeRecord['id'];
    $walletData['basic_salary'] = (float)($employeeRecord['salary'] ?? 0);

    try {
        $tcStmt = $db->prepare("SELECT COALESCE(SUM(earned_amount), 0) FROM ticket_earnings WHERE employee_id = ?");
        $tcStmt->execute([$empId]);
        $walletData['ticket_commissions_total'] = (float)$tcStmt->fetchColumn();

        $tcStmt = $db->prepare("SELECT COALESCE(SUM(earned_amount), 0) FROM ticket_earnings WHERE employee_id = ? AND created_at >= DATE_TRUNC('month', CURRENT_DATE)");
        $tcStmt->execute([$empId]);
        $walletData['ticket_commissions_month'] = (float)$tcStmt->fetchColumn();
    } catch (\Exception $e) { $walletData['ticket_commissions_total'] = 0; $walletData['ticket_commissions_month'] = 0; }

    try {
        $spStmt = $db->prepare("SELECT id FROM salespersons WHERE employee_id = ? LIMIT 1");
        $spStmt->execute([$empId]);
        $spId = $spStmt->fetchColumn();
        if ($spId) {
            $scStmt = $db->prepare("SELECT COALESCE(SUM(commission_amount), 0) FROM sales_commissions WHERE salesperson_id = ?");
            $scStmt->execute([$spId]);
            $walletData['sales_commissions_total'] = (float)$scStmt->fetchColumn();
            $scStmt = $db->prepare("SELECT COALESCE(SUM(commission_amount), 0) FROM sales_commissions WHERE salesperson_id = ? AND created_at >= DATE_TRUNC('month', CURRENT_DATE)");
            $scStmt->execute([$spId]);
            $walletData['sales_commissions_month'] = (float)$scStmt->fetchColumn();
        } else {
            $walletData['sales_commissions_total'] = 0;
            $walletData['sales_commissions_month'] = 0;
        }
    } catch (\Exception $e) { $walletData['sales_commissions_total'] = 0; $walletData['sales_commissions_month'] = 0; }

    $walletData['advances_outstanding'] = (float)$totalOutstanding;

    try {
        $payStmt = $db->prepare("
            SELECT pay_period_start, pay_period_end, base_salary, COALESCE(overtime_pay,0) as overtime_pay,
                   COALESCE(bonuses,0) as bonuses, COALESCE(allowances,0) as allowances,
                   COALESCE(deductions,0) as deductions, COALESCE(tax,0) as tax, net_pay, status
            FROM payroll WHERE employee_id = ? ORDER BY pay_period_start DESC LIMIT 6
        ");
        $payStmt->execute([$empId]);
        $walletData['payroll_history'] = $payStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Exception $e) { $walletData['payroll_history'] = []; }

    try {
        $teStmt = $db->prepare("
            SELECT te.earned_amount, te.category, te.status, te.created_at, t.ticket_number, t.subject
            FROM ticket_earnings te LEFT JOIN tickets t ON te.ticket_id = t.id
            WHERE te.employee_id = ? ORDER BY te.created_at DESC LIMIT 10
        ");
        $teStmt->execute([$empId]);
        $walletData['ticket_earnings_list'] = $teStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Exception $e) { $walletData['ticket_earnings_list'] = []; }

    try {
        if (!empty($spId)) {
            $scListStmt = $db->prepare("
                SELECT commission_amount, order_amount, commission_rate, commission_type, status, created_at
                FROM sales_commissions WHERE salesperson_id = ? ORDER BY created_at DESC LIMIT 10
            ");
            $scListStmt->execute([$spId]);
            $walletData['sales_commissions_list'] = $scListStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $walletData['sales_commissions_list'] = [];
        }
    } catch (\Exception $e) { $walletData['sales_commissions_list'] = []; }

    // Late deductions (current month)
    try {
        $lateCalc = new \App\LateDeductionCalculator($db);
        $lateData = $lateCalc->calculateMonthlyDeductions($empId, date('Y-m'));
        $walletData['late_deduction_total'] = (float)($lateData['total_deduction'] ?? 0);
        $walletData['late_days'] = (int)($lateData['total_late_days'] ?? 0);
        $walletData['late_minutes'] = (int)($lateData['total_late_minutes'] ?? 0);
        $walletData['late_breakdown'] = $lateData['breakdown'] ?? [];
    } catch (\Exception $e) { $walletData['late_deduction_total'] = 0; $walletData['late_days'] = 0; $walletData['late_minutes'] = 0; $walletData['late_breakdown'] = []; }

    $walletData['gross_month'] = $walletData['basic_salary'] + ($walletData['ticket_commissions_month'] ?? 0) + ($walletData['sales_commissions_month'] ?? 0);
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-person-badge"></i> My HR</h2>
</div>

<?php if (!empty($successMsg)): ?>
<div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle"></i> <?= htmlspecialchars($successMsg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (!empty($errorMsg)): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($errorMsg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (!$employeeRecord): ?>
<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle"></i> Your account is not linked to an employee record. Please contact HR.
</div>
<?php else: ?>

<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link <?= $subpage === 'overview' ? 'active' : '' ?>" href="?page=my-hr&subpage=overview">
            <i class="bi bi-house"></i> Overview
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $subpage === 'leave' ? 'active' : '' ?>" href="?page=my-hr&subpage=leave">
            <i class="bi bi-calendar-event"></i> Leave
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $subpage === 'advance' ? 'active' : '' ?>" href="?page=my-hr&subpage=advance">
            <i class="bi bi-cash"></i> Salary Advance
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $subpage === 'wallet' ? 'active' : '' ?>" href="?page=my-hr&subpage=wallet">
            <i class="bi bi-wallet2"></i> My Wallet
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $subpage === 'contracts' ? 'active' : '' ?>" href="?page=my-hr&subpage=contracts">
            <i class="bi bi-file-earmark-text"></i> Contracts
            <?php if (count($pendingContracts) > 0): ?>
                <span class="badge bg-danger"><?= count($pendingContracts) ?></span>
            <?php endif; ?>
        </a>
    </li>
</ul>

<?php if ($subpage === 'overview'): ?>
<div class="row g-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-person"></i> My Information
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <tr><th>Employee ID</th><td><?= htmlspecialchars($employeeRecord['employee_id'] ?? 'N/A') ?></td></tr>
                    <tr><th>Name</th><td><?= htmlspecialchars($employeeRecord['name']) ?></td></tr>
                    <tr><th>Position</th><td><?= htmlspecialchars($employeeRecord['position'] ?? 'N/A') ?></td></tr>
                    <tr><th>Phone</th><td><?= htmlspecialchars($employeeRecord['phone'] ?? 'N/A') ?></td></tr>
                    <tr><th>Email</th><td><?= htmlspecialchars($employeeRecord['email'] ?? 'N/A') ?></td></tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card mb-3">
            <div class="card-header bg-success text-white">
                <i class="bi bi-calendar-check"></i> Leave Balance
            </div>
            <div class="card-body">
                <?php if (!empty($leaveBalance)): ?>
                <div class="row text-center">
                    <?php foreach (array_slice($leaveBalance, 0, 3) as $balance): ?>
                    <div class="col-4">
                        <h4 class="mb-0"><?= number_format($balance['remaining'] ?? 0, 1) ?></h4>
                        <small class="text-muted"><?= htmlspecialchars($balance['leave_type_name'] ?? 'Leave') ?></small>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-muted mb-0">No leave balance records.</p>
                <?php endif; ?>
            </div>
        </div>
        <div class="card">
            <div class="card-header bg-warning">
                <i class="bi bi-cash-stack"></i> Outstanding Advance
            </div>
            <div class="card-body text-center">
                <h3 class="mb-0"><?= $currencySymbol ?> <?= number_format($totalOutstanding, 2) ?></h3>
                <small class="text-muted">To be deducted from salary</small>
            </div>
        </div>
    </div>
</div>

<?php elseif ($subpage === 'leave'): ?>
<div class="row g-4">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-plus-circle"></i> Request Leave
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="request_leave">
                    
                    <div class="mb-3">
                        <label class="form-label">Leave Type *</label>
                        <select class="form-select" name="leave_type_id" required>
                            <option value="">Select type...</option>
                            <?php foreach ($leaveTypes as $type): ?>
                            <option value="<?= $type['id'] ?>"><?= htmlspecialchars($type['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Start Date *</label>
                        <input type="date" class="form-control" name="start_date" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">End Date *</label>
                        <input type="date" class="form-control" name="end_date" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" name="is_half_day" id="is_half_day">
                        <label class="form-check-label" for="is_half_day">Half Day</label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reason</label>
                        <textarea class="form-control" name="reason" rows="2"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-send"></i> Submit Request
                    </button>
                </form>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header bg-success text-white">
                <i class="bi bi-calendar-check"></i> Leave Balance
            </div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Type</th><th>Used</th><th>Remaining</th></tr></thead>
                    <tbody>
                    <?php foreach ($leaveBalance as $balance): ?>
                    <tr>
                        <td><?= htmlspecialchars($balance['leave_type_name'] ?? 'Leave') ?></td>
                        <td><?= number_format($balance['used'] ?? 0, 1) ?></td>
                        <td><strong><?= number_format($balance['remaining'] ?? 0, 1) ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-list-ul"></i> My Leave Requests
            </div>
            <div class="card-body">
                <?php if (empty($leaveRequests)): ?>
                <p class="text-muted">No leave requests yet.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Dates</th>
                                <th>Days</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($leaveRequests as $req): ?>
                        <tr>
                            <td><?= htmlspecialchars($req['leave_type_name'] ?? 'Leave') ?></td>
                            <td>
                                <?= date('M d', strtotime($req['start_date'])) ?> - 
                                <?= date('M d, Y', strtotime($req['end_date'])) ?>
                            </td>
                            <td><?= $req['total_days'] ?? $req['days_requested'] ?? 1 ?></td>
                            <td>
                                <?php
                                $statusClass = match($req['status']) {
                                    'approved' => 'bg-success',
                                    'rejected' => 'bg-danger',
                                    'cancelled' => 'bg-secondary',
                                    default => 'bg-warning text-dark'
                                };
                                ?>
                                <span class="badge <?= $statusClass ?>"><?= ucfirst($req['status']) ?></span>
                            </td>
                            <td>
                                <?php if ($req['status'] === 'pending'): ?>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Cancel this request?')">
                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                    <input type="hidden" name="action" value="cancel_leave">
                                    <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Cancel</button>
                                </form>
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
    </div>
</div>

<?php elseif ($subpage === 'advance'): ?>
<div class="row g-4">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-plus-circle"></i> Request Salary Advance
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="request_advance">
                    
                    <div class="mb-3">
                        <label class="form-label">Amount (<?= $currencySymbol ?>) *</label>
                        <input type="number" class="form-control" name="amount" min="100" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Repayment Schedule</label>
                        <select class="form-select" name="repayment_type">
                            <option value="monthly">Monthly</option>
                            <option value="bi-weekly">Bi-Weekly</option>
                            <option value="weekly">Weekly</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Installments</label>
                        <input type="number" class="form-control" name="installments" min="1" max="12" value="1">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reason</label>
                        <textarea class="form-control" name="reason" rows="2"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-send"></i> Submit Request
                    </button>
                </form>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header bg-warning">
                <i class="bi bi-cash-stack"></i> Outstanding Balance
            </div>
            <div class="card-body text-center">
                <h3><?= $currencySymbol ?> <?= number_format($totalOutstanding, 2) ?></h3>
                <small class="text-muted">Total to be deducted</small>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-list-ul"></i> My Salary Advances
            </div>
            <div class="card-body">
                <?php if (empty($advances)): ?>
                <p class="text-muted">No salary advance requests.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Outstanding</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($advances as $adv): ?>
                        <tr>
                            <td><?= date('M d, Y', strtotime($adv['created_at'])) ?></td>
                            <td><?= $currencySymbol ?> <?= number_format($adv['amount'] ?? $adv['requested_amount'], 2) ?></td>
                            <td><?= $currencySymbol ?> <?= number_format($adv['balance'] ?? $adv['outstanding_balance'] ?? 0, 2) ?></td>
                            <td>
                                <?php
                                $statusClass = match($adv['status']) {
                                    'approved', 'disbursed' => 'bg-success',
                                    'rejected' => 'bg-danger',
                                    'repaying' => 'bg-info',
                                    'paid' => 'bg-secondary',
                                    default => 'bg-warning text-dark'
                                };
                                ?>
                                <span class="badge <?= $statusClass ?>"><?= ucfirst($adv['status']) ?></span>
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
<?php endif; ?>

<?php if ($subpage === 'contracts'): ?>
<?php
    $viewContractId = (int)($_GET['view'] ?? 0);
    $viewContract = null;
    if ($viewContractId) {
        foreach ($employeeContracts as $c) {
            if ($c['id'] == $viewContractId) { $viewContract = $c; break; }
        }
    }
?>

<?php if ($viewContract): ?>
<div class="mb-3">
    <a href="?page=my-hr&subpage=contracts" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Back to Contracts
    </a>
</div>

<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-1"><?= htmlspecialchars($viewContract['title']) ?></h5>
            <small class="text-muted">
                <?= ucfirst($viewContract['contract_type']) ?> Contract
                &middot; Sent <?= date('M j, Y', strtotime($viewContract['created_at'])) ?>
                <?php if ($viewContract['expires_at']): ?>
                    &middot; Expires <?= date('M j, Y', strtotime($viewContract['expires_at'])) ?>
                <?php endif; ?>
            </small>
        </div>
        <span class="badge bg-<?= $viewContract['status'] === 'signed' ? 'success' : ($viewContract['status'] === 'pending' ? 'warning text-dark' : 'secondary') ?> fs-6">
            <?= ucfirst($viewContract['status']) ?>
        </span>
    </div>
    <div class="card-body">
        <?php if ($viewContract['description']): ?>
            <p class="text-muted"><?= nl2br(htmlspecialchars($viewContract['description'])) ?></p>
            <hr>
        <?php endif; ?>

        <?php if ($viewContract['file_path']): ?>
            <div class="mb-3">
                <a href="<?= htmlspecialchars($viewContract['file_path']) ?>" target="_blank" class="btn btn-outline-primary">
                    <i class="bi bi-file-earmark-pdf"></i> View Contract Document
                </a>
            </div>
        <?php endif; ?>

        <?php if ($viewContract['content']): ?>
            <div class="border rounded p-4 bg-light mb-4" style="max-height: 500px; overflow-y: auto; font-family: 'Georgia', serif; line-height: 1.8;">
                <?= nl2br(htmlspecialchars($viewContract['content'])) ?>
            </div>
        <?php endif; ?>

        <?php if ($viewContract['status'] === 'signed'): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle-fill"></i>
                <strong>Signed</strong> by <?= htmlspecialchars($viewContract['signer_name']) ?>
                on <?= date('M j, Y \a\t H:i', strtotime($viewContract['signed_at'])) ?>
                (IP: <?= htmlspecialchars($viewContract['signer_ip']) ?>)
            </div>
            <?php if ($viewContract['signature_data']): ?>
                <div class="text-center">
                    <p class="text-muted small mb-1">Digital Signature</p>
                    <img src="<?= $viewContract['signature_data'] ?>" alt="Signature" class="border rounded" style="max-width: 400px; max-height: 150px; background: #fff;">
                </div>
            <?php endif; ?>
        <?php elseif ($viewContract['status'] === 'pending'): ?>
            <hr>
            <h6><i class="bi bi-pen"></i> Sign This Contract</h6>
            <form method="POST" id="signContractForm">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="sign_contract">
                <input type="hidden" name="contract_id" value="<?= $viewContract['id'] ?>">
                <input type="hidden" name="signature_data" id="signatureData">

                <div class="mb-3">
                    <label class="form-label">Your Full Name (as signature confirmation)</label>
                    <input type="text" name="signer_name" class="form-control" value="<?= htmlspecialchars($employeeRecord['name']) ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Draw Your Signature</label>
                    <div class="border rounded bg-white position-relative" style="touch-action: none;">
                        <canvas id="signatureCanvas" width="500" height="180" style="width: 100%; cursor: crosshair;"></canvas>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary mt-1" onclick="clearSignature()">
                        <i class="bi bi-eraser"></i> Clear
                    </button>
                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="agree_terms" id="agreeTerms" required>
                    <label class="form-check-label" for="agreeTerms">
                        I have read and agree to the terms of this contract. I understand that this digital signature is legally binding.
                    </label>
                </div>

                <button type="submit" class="btn btn-success btn-lg" id="signBtn" disabled>
                    <i class="bi bi-pen"></i> Sign Contract
                </button>
            </form>

            <script>
            (function() {
                const canvas = document.getElementById('signatureCanvas');
                const ctx = canvas.getContext('2d');
                let drawing = false;
                let hasDrawn = false;

                function getPos(e) {
                    const rect = canvas.getBoundingClientRect();
                    const scaleX = canvas.width / rect.width;
                    const scaleY = canvas.height / rect.height;
                    if (e.touches) {
                        return { x: (e.touches[0].clientX - rect.left) * scaleX, y: (e.touches[0].clientY - rect.top) * scaleY };
                    }
                    return { x: (e.clientX - rect.left) * scaleX, y: (e.clientY - rect.top) * scaleY };
                }

                canvas.addEventListener('mousedown', function(e) { drawing = true; ctx.beginPath(); const p = getPos(e); ctx.moveTo(p.x, p.y); });
                canvas.addEventListener('mousemove', function(e) { if (!drawing) return; const p = getPos(e); ctx.lineWidth = 2; ctx.lineCap = 'round'; ctx.strokeStyle = '#000'; ctx.lineTo(p.x, p.y); ctx.stroke(); hasDrawn = true; });
                canvas.addEventListener('mouseup', function() { drawing = false; updateSignBtn(); });
                canvas.addEventListener('mouseleave', function() { drawing = false; });

                canvas.addEventListener('touchstart', function(e) { e.preventDefault(); drawing = true; ctx.beginPath(); const p = getPos(e); ctx.moveTo(p.x, p.y); });
                canvas.addEventListener('touchmove', function(e) { e.preventDefault(); if (!drawing) return; const p = getPos(e); ctx.lineWidth = 2; ctx.lineCap = 'round'; ctx.strokeStyle = '#000'; ctx.lineTo(p.x, p.y); ctx.stroke(); hasDrawn = true; });
                canvas.addEventListener('touchend', function() { drawing = false; updateSignBtn(); });

                window.clearSignature = function() {
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    hasDrawn = false;
                    updateSignBtn();
                };

                function updateSignBtn() {
                    document.getElementById('signBtn').disabled = !(hasDrawn && document.getElementById('agreeTerms').checked);
                }
                document.getElementById('agreeTerms').addEventListener('change', updateSignBtn);

                document.getElementById('signContractForm').addEventListener('submit', function(e) {
                    if (!hasDrawn) { e.preventDefault(); alert('Please draw your signature.'); return; }
                    document.getElementById('signatureData').value = canvas.toDataURL('image/png');
                });
            })();
            </script>
        <?php endif; ?>
    </div>
</div>

<?php else: ?>

<?php if (count($pendingContracts) > 0): ?>
<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle"></i>
    You have <strong><?= count($pendingContracts) ?></strong> contract<?= count($pendingContracts) > 1 ? 's' : '' ?> awaiting your signature.
</div>
<?php endif; ?>

<?php if (empty($employeeContracts)): ?>
<div class="text-center text-muted py-5">
    <i class="bi bi-file-earmark-text" style="font-size: 3rem;"></i>
    <p class="mt-2">No contracts yet.</p>
</div>
<?php else: ?>
<div class="row g-3">
    <?php foreach ($employeeContracts as $c): ?>
    <div class="col-md-6">
        <div class="card <?= $c['status'] === 'pending' ? 'border-warning' : '' ?>">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h6 class="card-title mb-0"><?= htmlspecialchars($c['title']) ?></h6>
                    <span class="badge bg-<?= $c['status'] === 'signed' ? 'success' : ($c['status'] === 'pending' ? 'warning text-dark' : 'secondary') ?>">
                        <?= ucfirst($c['status']) ?>
                    </span>
                </div>
                <p class="text-muted small mb-2">
                    <i class="bi bi-tag"></i> <?= ucfirst($c['contract_type']) ?>
                    &middot; Sent <?= date('M j, Y', strtotime($c['created_at'])) ?>
                    <?php if ($c['expires_at']): ?>
                        &middot; Expires <?= date('M j, Y', strtotime($c['expires_at'])) ?>
                    <?php endif; ?>
                </p>
                <?php if ($c['description']): ?>
                    <p class="small mb-2"><?= htmlspecialchars(mb_strimwidth($c['description'], 0, 120, '...')) ?></p>
                <?php endif; ?>
                <div class="d-flex gap-2">
                    <a href="?page=my-hr&subpage=contracts&view=<?= $c['id'] ?>" class="btn btn-sm <?= $c['status'] === 'pending' ? 'btn-warning' : 'btn-outline-primary' ?>">
                        <?php if ($c['status'] === 'pending'): ?>
                            <i class="bi bi-pen"></i> Review & Sign
                        <?php else: ?>
                            <i class="bi bi-eye"></i> View
                        <?php endif; ?>
                    </a>
                    <?php if ($c['file_path']): ?>
                        <a href="<?= htmlspecialchars($c['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-download"></i> Download
                        </a>
                    <?php endif; ?>
                </div>
                <?php if ($c['status'] === 'signed'): ?>
                    <small class="text-success d-block mt-2">
                        <i class="bi bi-check-circle"></i> Signed <?= date('M j, Y', strtotime($c['signed_at'])) ?>
                    </small>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php endif; ?>
<?php endif; ?>

<?php if ($subpage === 'wallet'): ?>
<?php $w = $walletData; ?>
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card text-center">
            <div class="card-body py-3">
                <small class="text-muted d-block mb-1">Basic Salary</small>
                <span class="fs-5 fw-bold text-primary" style="filter: blur(5px); cursor: pointer;" onclick="this.style.filter=this.style.filter?'':'blur(5px)'">
                    <?= $currencySymbol ?> <?= number_format($w['basic_salary'] ?? 0, 2) ?>
                </span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center">
            <div class="card-body py-3">
                <small class="text-muted d-block mb-1">Ticket Earnings (Month)</small>
                <span class="fs-5 fw-bold text-success">
                    <?= $currencySymbol ?> <?= number_format($w['ticket_commissions_month'] ?? 0, 2) ?>
                </span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center">
            <div class="card-body py-3">
                <small class="text-muted d-block mb-1">Sales Commissions (Month)</small>
                <span class="fs-5 fw-bold text-info">
                    <?= $currencySymbol ?> <?= number_format($w['sales_commissions_month'] ?? 0, 2) ?>
                </span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center">
            <div class="card-body py-3">
                <small class="text-muted d-block mb-1">Advances Outstanding</small>
                <span class="fs-5 fw-bold text-danger">
                    <?= $currencySymbol ?> <?= number_format($w['advances_outstanding'] ?? 0, 2) ?>
                </span>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="bi bi-receipt"></i> Current Month Statement</h6>
        <span class="badge bg-dark"><?= date('F Y') ?></span>
    </div>
    <div class="card-body p-0">
        <table class="table table-bordered mb-0">
            <tbody>
                <tr>
                    <td class="ps-3">Basic Salary</td>
                    <td class="text-end pe-3 fw-bold"><?= $currencySymbol ?> <?= number_format($w['basic_salary'] ?? 0, 2) ?></td>
                </tr>
                <tr>
                    <td class="ps-3"><span class="text-success">+</span> Ticket Earnings</td>
                    <td class="text-end pe-3 text-success">+ <?= $currencySymbol ?> <?= number_format($w['ticket_commissions_month'] ?? 0, 2) ?></td>
                </tr>
                <tr>
                    <td class="ps-3"><span class="text-success">+</span> Sales Commissions</td>
                    <td class="text-end pe-3 text-success">+ <?= $currencySymbol ?> <?= number_format($w['sales_commissions_month'] ?? 0, 2) ?></td>
                </tr>
                <tr class="table-light">
                    <td class="ps-3 fw-bold">Gross Total</td>
                    <td class="text-end pe-3 fw-bold"><?= $currencySymbol ?> <?= number_format($w['gross_month'] ?? 0, 2) ?></td>
                </tr>
                <tr>
                    <td class="ps-3"><span class="text-danger">-</span> Late Deductions (<?= $w['late_days'] ?? 0 ?> day<?= ($w['late_days'] ?? 0) != 1 ? 's' : '' ?>, <?= $w['late_minutes'] ?? 0 ?> mins)</td>
                    <td class="text-end pe-3 text-danger">- <?= $currencySymbol ?> <?= number_format($w['late_deduction_total'] ?? 0, 2) ?></td>
                </tr>
                <tr>
                    <td class="ps-3"><span class="text-danger">-</span> Salary Advances Outstanding</td>
                    <td class="text-end pe-3 text-danger">- <?= $currencySymbol ?> <?= number_format($w['advances_outstanding'] ?? 0, 2) ?></td>
                </tr>
                <tr class="table-dark">
                    <td class="ps-3 fw-bold">Estimated Net</td>
                    <td class="text-end pe-3 fw-bold fs-5"><?= $currencySymbol ?> <?= number_format(($w['gross_month'] ?? 0) - ($w['late_deduction_total'] ?? 0) - ($w['advances_outstanding'] ?? 0), 2) ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<?php if (!empty($w['late_breakdown'])): ?>
<div class="card mb-4">
    <div class="card-header bg-white">
        <h6 class="mb-0"><i class="bi bi-alarm"></i> Late Arrival Deductions — <?= date('F Y') ?></h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead>
                    <tr><th class="ps-3">Date</th><th>Clock In</th><th>Late By</th><th>Deduction</th></tr>
                </thead>
                <tbody>
                <?php foreach ($w['late_breakdown'] as $lb): ?>
                    <tr>
                        <td class="ps-3"><small><?= date('D, M j', strtotime($lb['date'])) ?></small></td>
                        <td><small><?= $lb['clock_in'] ? date('H:i', strtotime($lb['clock_in'])) : '-' ?></small></td>
                        <td><small class="text-warning"><?= $lb['late_minutes'] ?> mins</small></td>
                        <td class="text-danger fw-bold"><small>- <?= $currencySymbol ?> <?= number_format($lb['deduction'], 2) ?></small></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="table-light">
                        <td colspan="2" class="ps-3 fw-bold">Total</td>
                        <td class="fw-bold"><?= $w['late_minutes'] ?? 0 ?> mins</td>
                        <td class="text-danger fw-bold">- <?= $currencySymbol ?> <?= number_format($w['late_deduction_total'] ?? 0, 2) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="bi bi-ticket-detailed"></i> Recent Ticket Earnings</h6>
            </div>
            <div class="card-body">
                <?php if (empty($w['ticket_earnings_list'])): ?>
                    <p class="text-muted text-center mb-0">No ticket earnings yet.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr><th>Ticket</th><th>Category</th><th>Amount</th><th>Status</th><th>Date</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($w['ticket_earnings_list'] as $te): ?>
                            <tr>
                                <td><small><?= htmlspecialchars($te['ticket_number'] ?? '-') ?></small></td>
                                <td><small><?= htmlspecialchars(ucfirst($te['category'] ?? '-')) ?></small></td>
                                <td class="text-success fw-bold"><small><?= $currencySymbol ?> <?= number_format($te['earned_amount'], 2) ?></small></td>
                                <td>
                                    <span class="badge bg-<?= ($te['status'] ?? '') === 'paid' ? 'success' : 'warning' ?> badge-sm">
                                        <?= ucfirst($te['status'] ?? 'pending') ?>
                                    </span>
                                </td>
                                <td><small><?= date('M j', strtotime($te['created_at'])) ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-end small text-muted mt-2">
                    All-time total: <strong><?= $currencySymbol ?> <?= number_format($w['ticket_commissions_total'] ?? 0, 2) ?></strong>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="bi bi-graph-up-arrow"></i> Recent Sales Commissions</h6>
            </div>
            <div class="card-body">
                <?php if (empty($w['sales_commissions_list'])): ?>
                    <p class="text-muted text-center mb-0">No sales commissions yet.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr><th>Order Amt</th><th>Rate</th><th>Commission</th><th>Status</th><th>Date</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($w['sales_commissions_list'] as $sc): ?>
                            <tr>
                                <td><small><?= $currencySymbol ?> <?= number_format($sc['order_amount'], 2) ?></small></td>
                                <td><small><?= ($sc['commission_type'] ?? '') === 'percentage' ? number_format($sc['commission_rate'], 1) . '%' : $currencySymbol . ' ' . number_format($sc['commission_rate'], 2) ?></small></td>
                                <td class="text-info fw-bold"><small><?= $currencySymbol ?> <?= number_format($sc['commission_amount'], 2) ?></small></td>
                                <td>
                                    <span class="badge bg-<?= ($sc['status'] ?? '') === 'paid' ? 'success' : 'warning' ?> badge-sm">
                                        <?= ucfirst($sc['status'] ?? 'pending') ?>
                                    </span>
                                </td>
                                <td><small><?= date('M j', strtotime($sc['created_at'])) ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-end small text-muted mt-2">
                    All-time total: <strong><?= $currencySymbol ?> <?= number_format($w['sales_commissions_total'] ?? 0, 2) ?></strong>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($w['payroll_history'])): ?>
<div class="card mt-4">
    <div class="card-header bg-white">
        <h6 class="mb-0"><i class="bi bi-clock-history"></i> Payroll History</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-striped mb-0">
                <thead>
                    <tr>
                        <th>Period</th>
                        <th>Base</th>
                        <th>Overtime</th>
                        <th>Bonuses</th>
                        <th>Allowances</th>
                        <th>Deductions</th>
                        <th>Tax</th>
                        <th>Net Pay</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($w['payroll_history'] as $pay): ?>
                    <tr>
                        <td><small><?= date('M Y', strtotime($pay['pay_period_start'])) ?></small></td>
                        <td><small><?= $currencySymbol ?> <?= number_format($pay['base_salary'], 2) ?></small></td>
                        <td class="text-success"><small>+<?= number_format($pay['overtime_pay'], 2) ?></small></td>
                        <td class="text-success"><small>+<?= number_format($pay['bonuses'], 2) ?></small></td>
                        <td class="text-success"><small>+<?= number_format($pay['allowances'], 2) ?></small></td>
                        <td class="text-danger"><small>-<?= number_format($pay['deductions'], 2) ?></small></td>
                        <td class="text-danger"><small>-<?= number_format($pay['tax'], 2) ?></small></td>
                        <td class="fw-bold"><small><?= $currencySymbol ?> <?= number_format($pay['net_pay'], 2) ?></small></td>
                        <td>
                            <span class="badge bg-<?= $pay['status'] === 'paid' ? 'success' : ($pay['status'] === 'pending' ? 'warning' : 'secondary') ?>">
                                <?= ucfirst($pay['status']) ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>

<?php endif; ?>
