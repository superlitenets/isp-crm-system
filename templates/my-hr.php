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
    }
}

$leaveBalance = $employeeRecord ? $leaveService->getEmployeeBalance($employeeRecord['id']) : [];
$leaveRequests = $employeeRecord ? $leaveService->getEmployeeRequests($employeeRecord['id']) : [];
$leaveTypes = $leaveService->getLeaveTypes();
$advances = $employeeRecord ? $advanceService->getByEmployee($employeeRecord['id']) : [];
$totalOutstanding = $employeeRecord ? $advanceService->getEmployeeTotalOutstanding($employeeRecord['id']) : 0;
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

<?php endif; ?>
