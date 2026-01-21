<?php
$employeeData = null;
$departmentData = null;
$linkedUserData = null;
$subpage = $_GET['subpage'] ?? 'employees';
$selectedDate = $_GET['date'] ?? date('Y-m-d');
$selectedMonth = $_GET['month'] ?? date('Y-m');

$hrSettings = new \App\Settings();
$currencySymbol = $hrSettings->get('currency_symbol', 'KES');

if ($action === 'edit_employee' && $id) {
    $employeeData = $employee->find($id);
    if ($employeeData && $employeeData['user_id']) {
        $linkedUserData = $employee->getUserByEmployeeId($id);
    }
}
if ($action === 'view_employee' && $id) {
    $employeeData = $employee->find($id);
    if ($employeeData && $employeeData['user_id']) {
        $linkedUserData = $employee->getUserByEmployeeId($id);
    }
}
if ($action === 'edit_department' && $id) {
    $departmentData = $employee->getDepartment($id);
}

$departments = $employee->getAllDepartments();
$employmentStatuses = $employee->getEmploymentStatuses();
$hrStats = $employee->getStats();
$allEmployees = $employee->getAll();

$pendingLeaveCount = $db->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'pending'")->fetchColumn();
$pendingAdvanceCount = $db->query("SELECT COUNT(*) FROM salary_advances WHERE status = 'pending'")->fetchColumn();
try {
    $todayLateCount = $db->query("SELECT COUNT(*) FROM attendance WHERE DATE(clock_in) = CURRENT_DATE AND is_late = true")->fetchColumn();
} catch (Exception $e) {
    $todayLateCount = 0;
}
$attendanceStatuses = $employee->getAttendanceStatuses();
$payrollStatuses = $employee->getPayrollStatuses();
$paymentMethods = $employee->getPaymentMethods();
$performanceStatuses = $employee->getPerformanceStatuses();
$roleManager = new \App\Role();
$allRoles = $roleManager->getAllRoles();
?>

<?php if ($action === 'create_employee' || $action === 'edit_employee'): ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-person-<?= $action === 'create_employee' ? 'plus' : 'gear' ?>"></i> <?= $action === 'create_employee' ? 'Add Employee' : 'Edit Employee' ?></h2>
    <a href="?page=hr&subpage=employees" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="<?= $action === 'create_employee' ? 'create_employee' : 'update_employee' ?>">
            <?php if ($action === 'edit_employee'): ?>
            <input type="hidden" name="id" value="<?= $employeeData['id'] ?>">
            <?php endif; ?>
            
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Full Name *</label>
                    <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($employeeData['name'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Personal Phone *</label>
                    <input type="tel" class="form-control" name="phone" value="<?= htmlspecialchars($employeeData['phone'] ?? '') ?>" placeholder="+1234567890" required>
                    <small class="text-muted">Used for internal notifications to employee</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Office Phone</label>
                    <input type="tel" class="form-control" name="office_phone" value="<?= htmlspecialchars($employeeData['office_phone'] ?? '') ?>" placeholder="+1234567890">
                    <small class="text-muted">Shown to customers (e.g. on tickets)</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($employeeData['email'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Position *</label>
                    <input type="text" class="form-control" name="position" value="<?= htmlspecialchars($employeeData['position'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Department</label>
                    <select class="form-select" name="department_id">
                        <option value="">No Department</option>
                        <?php foreach ($departments as $dept): ?>
                        <option value="<?= $dept['id'] ?>" <?= ($employeeData['department_id'] ?? '') == $dept['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($dept['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Employment Status</label>
                    <select class="form-select" name="employment_status">
                        <?php foreach ($employmentStatuses as $key => $label): ?>
                        <option value="<?= $key ?>" <?= ($employeeData['employment_status'] ?? 'active') === $key ? 'selected' : '' ?>>
                            <?= $label ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Salary</label>
                    <input type="number" step="0.01" class="form-control" name="salary" value="<?= htmlspecialchars($employeeData['salary'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Hire Date</label>
                    <input type="date" class="form-control" name="hire_date" value="<?= htmlspecialchars($employeeData['hire_date'] ?? '') ?>">
                </div>
                <?php if ($action === 'create_employee'): ?>
                <input type="hidden" name="user_id" value="create_new">
                
                <div class="col-12">
                    <div class="card bg-light">
                        <div class="card-body">
                            <h6 class="card-title"><i class="bi bi-shield-lock"></i> System Login Details</h6>
                            <p class="text-muted small mb-3">Every employee gets a login account to access the system.</p>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Login Email *</label>
                                    <input type="email" class="form-control" name="new_user_email" placeholder="user@company.com" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Password *</label>
                                    <input type="password" class="form-control" name="new_user_password" placeholder="Min 6 characters" required minlength="6">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">System Role *</label>
                                    <select class="form-select" name="new_user_role_id" required>
                                        <option value="">Select Role</option>
                                        <?php foreach ($allRoles as $role): ?>
                                        <option value="<?= $role['id'] ?>">
                                            <?= htmlspecialchars($role['display_name']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">Determines what the employee can access</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="col-md-4">
                    <label class="form-label">System Access</label>
                    <select class="form-select" name="user_id" id="userAccountSelect">
                        <option value="">No System Access</option>
                        <option value="create_new">+ Create New Login Account</option>
                        <?php foreach ($users as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= ($employeeData['user_id'] ?? '') == $u['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['email']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Required for ticket assignment</small>
                </div>
                
                <div class="col-12" id="roleAndAccessFields" style="display: <?= ($employeeData['user_id'] ?? '') ? 'block' : 'none' ?>;">
                    <div class="card bg-light">
                        <div class="card-body">
                            <h6 class="card-title"><i class="bi bi-shield-lock"></i> Role & Permissions</h6>
                            <div class="row g-3">
                                <div class="col-md-4" id="newAccountEmailField" style="display: none;">
                                    <label class="form-label">Login Email *</label>
                                    <input type="email" class="form-control" name="new_user_email" placeholder="user@company.com">
                                </div>
                                <div class="col-md-4" id="newAccountPasswordField" style="display: none;">
                                    <label class="form-label">Password *</label>
                                    <input type="password" class="form-control" name="new_user_password" placeholder="Min 6 characters">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">System Role *</label>
                                    <select class="form-select" name="new_user_role_id" id="roleSelect">
                                        <option value="">Select Role</option>
                                        <?php foreach ($allRoles as $role): ?>
                                        <option value="<?= $role['id'] ?>" <?= ($linkedUserData['role_id'] ?? '') == $role['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($role['display_name']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">Determines what the employee can access in the system</small>
                                </div>
                                <?php if ($linkedUserData): ?>
                                <div class="col-md-4">
                                    <label class="form-label">Current Login</label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($linkedUserData['email']) ?>" readonly>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="col-12 mt-3">
                    <h6 class="text-primary border-bottom pb-2"><i class="bi bi-person-vcard"></i> Personal Details</h6>
                </div>
                <div class="col-md-4">
                    <label class="form-label">ID/National ID Number</label>
                    <input type="text" class="form-control" name="id_number" value="<?= htmlspecialchars($employeeData['id_number'] ?? '') ?>" placeholder="e.g. 12345678">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Passport Number</label>
                    <input type="text" class="form-control" name="passport_number" value="<?= htmlspecialchars($employeeData['passport_number'] ?? '') ?>" placeholder="e.g. AB1234567">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Date of Birth</label>
                    <input type="date" class="form-control" name="date_of_birth" value="<?= htmlspecialchars($employeeData['date_of_birth'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Gender</label>
                    <select class="form-select" name="gender">
                        <option value="">Select...</option>
                        <option value="Male" <?= ($employeeData['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                        <option value="Female" <?= ($employeeData['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                        <option value="Other" <?= ($employeeData['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Nationality</label>
                    <input type="text" class="form-control" name="nationality" value="<?= htmlspecialchars($employeeData['nationality'] ?? '') ?>" placeholder="e.g. Kenyan">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Marital Status</label>
                    <select class="form-select" name="marital_status">
                        <option value="">Select...</option>
                        <option value="Single" <?= ($employeeData['marital_status'] ?? '') === 'Single' ? 'selected' : '' ?>>Single</option>
                        <option value="Married" <?= ($employeeData['marital_status'] ?? '') === 'Married' ? 'selected' : '' ?>>Married</option>
                        <option value="Divorced" <?= ($employeeData['marital_status'] ?? '') === 'Divorced' ? 'selected' : '' ?>>Divorced</option>
                        <option value="Widowed" <?= ($employeeData['marital_status'] ?? '') === 'Widowed' ? 'selected' : '' ?>>Widowed</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Address</label>
                    <textarea class="form-control" name="address" rows="2"><?= htmlspecialchars($employeeData['address'] ?? '') ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Passport Photo</label>
                    <?php if (!empty($employeeData['passport_photo'])): ?>
                    <div class="mb-2">
                        <img src="<?= htmlspecialchars($employeeData['passport_photo']) ?>" alt="Passport Photo" class="img-thumbnail" style="max-width: 100px; max-height: 100px;">
                    </div>
                    <?php endif; ?>
                    <input type="file" class="form-control" name="passport_photo" accept="image/*">
                    <small class="text-muted">Upload a passport-size photo for job card</small>
                </div>
                
                <div class="col-12 mt-3">
                    <h6 class="text-primary border-bottom pb-2"><i class="bi bi-people"></i> Next of Kin</h6>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Next of Kin Name</label>
                    <input type="text" class="form-control" name="next_of_kin_name" value="<?= htmlspecialchars($employeeData['next_of_kin_name'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Next of Kin Phone</label>
                    <input type="tel" class="form-control" name="next_of_kin_phone" value="<?= htmlspecialchars($employeeData['next_of_kin_phone'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Relationship</label>
                    <select class="form-select" name="next_of_kin_relationship">
                        <option value="">Select...</option>
                        <option value="Spouse" <?= ($employeeData['next_of_kin_relationship'] ?? '') === 'Spouse' ? 'selected' : '' ?>>Spouse</option>
                        <option value="Parent" <?= ($employeeData['next_of_kin_relationship'] ?? '') === 'Parent' ? 'selected' : '' ?>>Parent</option>
                        <option value="Sibling" <?= ($employeeData['next_of_kin_relationship'] ?? '') === 'Sibling' ? 'selected' : '' ?>>Sibling</option>
                        <option value="Child" <?= ($employeeData['next_of_kin_relationship'] ?? '') === 'Child' ? 'selected' : '' ?>>Child</option>
                        <option value="Other" <?= ($employeeData['next_of_kin_relationship'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                
                <div class="col-12 mt-3">
                    <h6 class="text-primary border-bottom pb-2"><i class="bi bi-telephone"></i> Emergency Contact</h6>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Emergency Contact Name</label>
                    <input type="text" class="form-control" name="emergency_contact" value="<?= htmlspecialchars($employeeData['emergency_contact'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Emergency Contact Phone</label>
                    <input type="tel" class="form-control" name="emergency_phone" value="<?= htmlspecialchars($employeeData['emergency_phone'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Notes</label>
                    <textarea class="form-control" name="notes" rows="2"><?= htmlspecialchars($employeeData['notes'] ?? '') ?></textarea>
                </div>
            </div>
            
            <div class="mt-4">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg"></i> <?= $action === 'create_employee' ? 'Add Employee' : 'Update Employee' ?>
                </button>
                <a href="?page=hr&subpage=employees" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php elseif ($action === 'view_employee' && $employeeData): ?>
<?php
// Fetch employee statistics
$empStats = [];
try {
    $statsDb = Database::getConnection();
    
    // Get tickets assigned to this employee (via user_id)
    $ticketStats = ['total' => 0, 'open' => 0, 'closed' => 0];
    if ($employeeData['user_id']) {
        $ticketStmt = $statsDb->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status NOT IN ('closed', 'resolved') THEN 1 ELSE 0 END) as open,
                SUM(CASE WHEN status IN ('closed', 'resolved') THEN 1 ELSE 0 END) as closed
            FROM tickets WHERE assigned_to = ?
        ");
        $ticketStmt->execute([$employeeData['user_id']]);
        $ticketStats = $ticketStmt->fetch(PDO::FETCH_ASSOC) ?: $ticketStats;
    }
    
    // Get attendance this month (only count records with clock_in)
    $attendanceStmt = $statsDb->prepare("
        SELECT 
            COUNT(*) FILTER (WHERE clock_in IS NOT NULL) as days_present,
            COALESCE(SUM(hours_worked), 0) as total_hours,
            COUNT(*) FILTER (WHERE status = 'late' OR is_late = true) as late_days
        FROM attendance 
        WHERE employee_id = ? 
        AND date >= DATE_TRUNC('month', CURRENT_DATE)
    ");
    $attendanceStmt->execute([$employeeData['id']]);
    $attendanceStats = $attendanceStmt->fetch(PDO::FETCH_ASSOC) ?: ['days_present' => 0, 'total_hours' => 0, 'late_days' => 0];
    
    // Get salary advances this month
    $advanceStmt = $statsDb->prepare("
        SELECT COALESCE(SUM(amount), 0) as total_advances, COUNT(*) as advance_count
        FROM salary_advances 
        WHERE employee_id = ? 
        AND status = 'approved'
        AND request_date >= DATE_TRUNC('month', CURRENT_DATE)
    ");
    $advanceStmt->execute([$employeeData['id']]);
    $advanceStats = $advanceStmt->fetch(PDO::FETCH_ASSOC) ?: ['total_advances' => 0, 'advance_count' => 0];
    
    // Get leave balance
    $leaveStmt = $statsDb->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN status = 'approved' THEN days_requested ELSE 0 END), 0) as days_taken,
            COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) as pending_requests
        FROM leave_requests 
        WHERE employee_id = ? 
        AND EXTRACT(YEAR FROM start_date) = EXTRACT(YEAR FROM CURRENT_DATE)
    ");
    $leaveStmt->execute([$employeeData['id']]);
    $leaveStats = $leaveStmt->fetch(PDO::FETCH_ASSOC) ?: ['days_taken' => 0, 'pending_requests' => 0];
    
    $empStats = [
        'tickets' => $ticketStats,
        'attendance' => $attendanceStats,
        'advances' => $advanceStats,
        'leave' => $leaveStats
    ];
} catch (\Exception $e) {
    // Stats tables may not exist
}
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-person-badge"></i> Employee Details</h2>
    <div>
        <a href="?page=hr&subpage=employees" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back
        </a>
        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#jobCardModal">
            <i class="bi bi-card-heading"></i> Job Card
        </button>
        <a href="?page=hr&action=edit_employee&id=<?= $employeeData['id'] ?>" class="btn btn-primary">
            <i class="bi bi-pencil"></i> Edit
        </a>
    </div>
</div>

<!-- Employee Statistics -->
<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="card bg-primary text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="mb-0"><?= (int)($empStats['tickets']['total'] ?? 0) ?></h3>
                        <small>Total Tickets</small>
                    </div>
                    <i class="bi bi-ticket-detailed fs-1 opacity-50"></i>
                </div>
                <div class="mt-2 small">
                    <span class="badge bg-light text-primary"><?= (int)($empStats['tickets']['open'] ?? 0) ?> Open</span>
                    <span class="badge bg-light text-success"><?= (int)($empStats['tickets']['closed'] ?? 0) ?> Closed</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card bg-success text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="mb-0"><?= (int)($empStats['attendance']['days_present'] ?? 0) ?></h3>
                        <small>Days Present (Month)</small>
                    </div>
                    <i class="bi bi-calendar-check fs-1 opacity-50"></i>
                </div>
                <div class="mt-2 small">
                    <span class="badge bg-light text-success"><?= number_format((float)($empStats['attendance']['total_hours'] ?? 0), 1) ?> hrs</span>
                    <?php if (($empStats['attendance']['late_days'] ?? 0) > 0): ?>
                    <span class="badge bg-warning text-dark"><?= (int)$empStats['attendance']['late_days'] ?> Late</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card bg-warning text-dark h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="mb-0"><?= $currencySymbol ?> <?= number_format((float)($empStats['advances']['total_advances'] ?? 0)) ?></h3>
                        <small>Advances (Month)</small>
                    </div>
                    <i class="bi bi-cash-stack fs-1 opacity-50"></i>
                </div>
                <div class="mt-2 small">
                    <span class="badge bg-dark"><?= (int)($empStats['advances']['advance_count'] ?? 0) ?> requests</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card bg-info text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="mb-0"><?= (int)($empStats['leave']['days_taken'] ?? 0) ?></h3>
                        <small>Leave Days (Year)</small>
                    </div>
                    <i class="bi bi-calendar-x fs-1 opacity-50"></i>
                </div>
                <div class="mt-2 small">
                    <?php if (($empStats['leave']['pending_requests'] ?? 0) > 0): ?>
                    <span class="badge bg-warning text-dark"><?= (int)$empStats['leave']['pending_requests'] ?> Pending</span>
                    <?php else: ?>
                    <span class="badge bg-light text-info">No pending</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">Personal Information</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <th width="40%">Employee ID</th>
                        <td><strong><?= htmlspecialchars($employeeData['employee_id']) ?></strong></td>
                    </tr>
                    <tr>
                        <th>Name</th>
                        <td><?= htmlspecialchars($employeeData['name']) ?></td>
                    </tr>
                    <tr>
                        <th>Phone</th>
                        <td><?= htmlspecialchars($employeeData['phone']) ?></td>
                    </tr>
                    <tr>
                        <th>Email</th>
                        <td><?= htmlspecialchars($employeeData['email'] ?? 'N/A') ?></td>
                    </tr>
                    <tr>
                        <th>Address</th>
                        <td><?= nl2br(htmlspecialchars($employeeData['address'] ?? 'N/A')) ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">Employment Information</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <th width="40%">Position</th>
                        <td><?= htmlspecialchars($employeeData['position']) ?></td>
                    </tr>
                    <tr>
                        <th>Department</th>
                        <td><?= htmlspecialchars($employeeData['department_name'] ?? 'No Department') ?></td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td>
                            <span class="badge bg-<?= $employeeData['employment_status'] === 'active' ? 'success' : ($employeeData['employment_status'] === 'on_leave' ? 'warning' : 'secondary') ?>">
                                <?= ucfirst(str_replace('_', ' ', $employeeData['employment_status'])) ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Hire Date</th>
                        <td><?= $employeeData['hire_date'] ? date('M j, Y', strtotime($employeeData['hire_date'])) : 'N/A' ?></td>
                    </tr>
                    <tr>
                        <th>Salary</th>
                        <td><?= $employeeData['salary'] ? $currencySymbol . ' ' . number_format($employeeData['salary'], 2) : 'N/A' ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">Emergency Contact</h5>
            </div>
            <div class="card-body">
                <p><strong>Name:</strong> <?= htmlspecialchars($employeeData['emergency_contact'] ?? 'Not provided') ?></p>
                <p class="mb-0"><strong>Phone:</strong> <?= htmlspecialchars($employeeData['emergency_phone'] ?? 'Not provided') ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">Notes</h5>
            </div>
            <div class="card-body">
                <p class="mb-0"><?= nl2br(htmlspecialchars($employeeData['notes'] ?? 'No notes')) ?></p>
            </div>
        </div>
    </div>
</div>

<!-- KYC Documents Section -->
<div class="card mt-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-file-earmark-check"></i> KYC Documents</h5>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#uploadKycModal">
            <i class="bi bi-upload"></i> Upload Document
        </button>
    </div>
    <div class="card-body">
        <?php 
        $kycDocuments = $employee->getKycDocuments($employeeData['id']);
        $documentTypes = $employee->getKycDocumentTypes();
        ?>
        <?php if (empty($kycDocuments)): ?>
        <p class="text-muted text-center mb-0">No KYC documents uploaded yet.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Document Type</th>
                        <th>File Name</th>
                        <th>Uploaded</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($kycDocuments as $doc): ?>
                    <tr>
                        <td>
                            <i class="bi bi-file-earmark-text text-primary me-1"></i>
                            <?= htmlspecialchars($documentTypes[$doc['document_type']] ?? ucfirst(str_replace('_', ' ', $doc['document_type']))) ?>
                        </td>
                        <td><?= htmlspecialchars($doc['document_name']) ?></td>
                        <td><?= date('M j, Y', strtotime($doc['uploaded_at'])) ?></td>
                        <td>
                            <?php if ($doc['verified_at']): ?>
                            <span class="badge bg-success"><i class="bi bi-check-circle"></i> Verified</span>
                            <small class="text-muted d-block">by <?= htmlspecialchars($doc['verified_by_name'] ?? 'System') ?></small>
                            <?php else: ?>
                            <span class="badge bg-warning text-dark"><i class="bi bi-clock"></i> Pending</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?= htmlspecialchars($doc['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="<?= htmlspecialchars($doc['file_path']) ?>" download class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-download"></i>
                            </a>
                            <?php if (!$doc['verified_at']): ?>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="action" value="verify_kyc_document">
                                <input type="hidden" name="document_id" value="<?= $doc['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-success" title="Verify">
                                    <i class="bi bi-check-lg"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this document?')">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="action" value="delete_kyc_document">
                                <input type="hidden" name="document_id" value="<?= $doc['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Upload KYC Modal -->
<div class="modal fade" id="uploadKycModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="upload_kyc_document">
                <input type="hidden" name="employee_id" value="<?= $employeeData['id'] ?>">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-upload"></i> Upload KYC Document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Document Type *</label>
                        <select class="form-select" name="document_type" required>
                            <option value="">Select document type...</option>
                            <?php foreach ($documentTypes as $key => $label): ?>
                            <option value="<?= $key ?>"><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Document File *</label>
                        <input type="file" class="form-control" name="kyc_document" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>
                        <small class="text-muted">Accepted: PDF, JPG, PNG, DOC, DOCX (Max 5MB)</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="2" placeholder="Optional notes about this document..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-upload"></i> Upload
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Job Card Modal -->
<div class="modal fade" id="jobCardModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-person-badge"></i> Employee ID Card</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 2rem;">
                <div id="jobCardPrintArea">
                    <style>
                        .id-card-bright {
                            width: 380px;
                            margin: 0 auto;
                            border-radius: 20px;
                            overflow: hidden;
                            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                            background: linear-gradient(180deg, #ffffff 0%, #f8f9fa 100%);
                            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                            position: relative;
                        }
                        .id-card-bright::before {
                            content: '';
                            position: absolute;
                            top: 0;
                            left: 0;
                            right: 0;
                            height: 8px;
                            background: linear-gradient(90deg, #00b4db 0%, #0083b0 50%, #00b4db 100%);
                        }
                        .id-card-bright-header {
                            background: linear-gradient(135deg, #0083b0 0%, #00b4db 100%);
                            padding: 20px;
                            text-align: center;
                            position: relative;
                            clip-path: polygon(0 0, 100% 0, 100% 85%, 50% 100%, 0 85%);
                            padding-bottom: 35px;
                        }
                        .id-card-bright-header h4 {
                            color: #fff;
                            margin: 0;
                            font-weight: 800;
                            text-transform: uppercase;
                            letter-spacing: 3px;
                            font-size: 1.1rem;
                            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
                        }
                        .id-card-bright-header small {
                            color: rgba(255,255,255,0.95);
                            font-size: 0.75rem;
                            letter-spacing: 2px;
                            text-transform: uppercase;
                        }
                        .id-card-bright-body {
                            padding: 15px 25px 20px;
                            color: #333;
                        }
                        .id-card-bright-photo {
                            width: 110px;
                            height: 130px;
                            border-radius: 12px;
                            object-fit: cover;
                            border: 4px solid #00b4db;
                            box-shadow: 0 8px 25px rgba(0,180,219,0.3);
                        }
                        .id-card-bright-photo-placeholder {
                            width: 110px;
                            height: 130px;
                            border-radius: 12px;
                            background: linear-gradient(135deg, #e0e0e0 0%, #f5f5f5 100%);
                            border: 4px solid #00b4db;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                        }
                        .id-card-bright-name {
                            font-size: 1.3rem;
                            font-weight: 800;
                            color: #1a1a2e;
                            margin-bottom: 4px;
                        }
                        .id-card-bright-position {
                            color: #0083b0;
                            font-weight: 700;
                            font-size: 0.9rem;
                            text-transform: uppercase;
                            letter-spacing: 1px;
                        }
                        .id-card-bright-emp-id {
                            background: linear-gradient(90deg, #00b4db 0%, #0083b0 100%);
                            border-radius: 25px;
                            padding: 5px 15px;
                            font-size: 0.8rem;
                            color: #fff;
                            display: inline-block;
                            margin-top: 10px;
                            font-weight: 600;
                            box-shadow: 0 3px 10px rgba(0,180,219,0.3);
                        }
                        .id-card-bright-details {
                            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
                            border-radius: 12px;
                            padding: 15px;
                            margin-top: 15px;
                            border: 1px solid #dee2e6;
                        }
                        .id-card-bright-detail-row {
                            display: flex;
                            justify-content: space-between;
                            align-items: center;
                            padding: 8px 0;
                            border-bottom: 1px dashed #dee2e6;
                        }
                        .id-card-bright-detail-row:last-child {
                            border-bottom: none;
                        }
                        .id-card-bright-detail-label {
                            color: #6c757d;
                            font-size: 0.75rem;
                            text-transform: uppercase;
                            letter-spacing: 0.5px;
                            font-weight: 600;
                        }
                        .id-card-bright-detail-label i {
                            color: #00b4db;
                            margin-right: 5px;
                        }
                        .id-card-bright-detail-value {
                            color: #1a1a2e;
                            font-weight: 700;
                            font-size: 0.85rem;
                            text-align: right;
                        }
                        .id-card-bright-barcode {
                            background: #fff;
                            padding: 10px 15px;
                            text-align: center;
                            border-top: 2px dashed #dee2e6;
                        }
                        .id-card-bright-barcode svg {
                            max-width: 100%;
                            height: 50px;
                        }
                        .id-card-bright-footer {
                            background: linear-gradient(135deg, #0083b0 0%, #00b4db 100%);
                            padding: 12px 20px;
                            display: flex;
                            justify-content: space-between;
                            font-size: 0.75rem;
                            color: #fff;
                            font-weight: 600;
                        }
                        .id-card-bright-footer i {
                            margin-right: 4px;
                        }
                        .id-card-bright-validity {
                            position: absolute;
                            top: 12px;
                            right: 12px;
                            background: rgba(255,255,255,0.2);
                            padding: 3px 10px;
                            border-radius: 15px;
                            font-size: 0.65rem;
                            color: #fff;
                            backdrop-filter: blur(5px);
                        }
                    </style>
                    
                    <div class="id-card-bright">
                        <div class="id-card-bright-header">
                            <div class="id-card-bright-validity">
                                <i class="bi bi-shield-check"></i> VALID
                            </div>
                            <h4><?= htmlspecialchars($settings->get('company_name', 'Company Name')) ?></h4>
                            <small>Employee Identification Card</small>
                        </div>
                        <div class="id-card-bright-body">
                            <div class="d-flex gap-3 align-items-start">
                                <div>
                                    <?php if (!empty($employeeData['passport_photo'])): ?>
                                    <img src="<?= htmlspecialchars($employeeData['passport_photo']) ?>" alt="Photo" class="id-card-bright-photo">
                                    <?php else: ?>
                                    <div class="id-card-bright-photo-placeholder">
                                        <i class="bi bi-person-fill" style="font-size: 3rem; color: #00b4db;"></i>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="id-card-bright-name"><?= htmlspecialchars($employeeData['name']) ?></div>
                                    <div class="id-card-bright-position"><?= htmlspecialchars($employeeData['position'] ?? 'Employee') ?></div>
                                    <div class="id-card-bright-emp-id">
                                        <i class="bi bi-person-badge me-1"></i><?= htmlspecialchars($employeeData['employee_id']) ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="id-card-bright-details">
                                <div class="id-card-bright-detail-row">
                                    <span class="id-card-bright-detail-label"><i class="bi bi-building"></i>Department</span>
                                    <span class="id-card-bright-detail-value"><?= htmlspecialchars($employeeData['department_name'] ?? 'N/A') ?></span>
                                </div>
                                <div class="id-card-bright-detail-row">
                                    <span class="id-card-bright-detail-label"><i class="bi bi-person-vcard"></i>ID Number</span>
                                    <span class="id-card-bright-detail-value"><?= htmlspecialchars($employeeData['id_number'] ?? 'N/A') ?></span>
                                </div>
                                <div class="id-card-bright-detail-row">
                                    <span class="id-card-bright-detail-label"><i class="bi bi-telephone"></i>Phone</span>
                                    <span class="id-card-bright-detail-value"><?= htmlspecialchars($employeeData['office_phone'] ?? $employeeData['phone'] ?? 'N/A') ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="id-card-bright-barcode">
                            <svg id="employeeBarcode"></svg>
                            <div style="font-size: 0.7rem; color: #6c757d; margin-top: 5px;">Scan to verify</div>
                        </div>
                        <div class="id-card-bright-footer">
                            <span><i class="bi bi-calendar-event"></i>Joined: <?= $employeeData['hire_date'] ? date('M Y', strtotime($employeeData['hire_date'])) : 'N/A' ?></span>
                            <span><i class="bi bi-calendar-check"></i>Valid: <?= date('M Y', strtotime('+1 year')) ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="printJobCard()">
                    <i class="bi bi-printer"></i> Print Job Card
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script>
document.getElementById('jobCardModal').addEventListener('shown.bs.modal', function() {
    var empId = '<?= htmlspecialchars($employeeData['employee_id'] ?? 'EMP000') ?>';
    if (document.getElementById('employeeBarcode')) {
        JsBarcode("#employeeBarcode", empId, {
            format: "CODE128",
            width: 2,
            height: 40,
            displayValue: true,
            fontSize: 12,
            margin: 5,
            background: "#ffffff",
            lineColor: "#1a1a2e"
        });
    }
});

function printJobCard() {
    var empId = '<?= htmlspecialchars($employeeData['employee_id'] ?? 'EMP000') ?>';
    var printContent = document.getElementById('jobCardPrintArea').innerHTML;
    var printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Job Card - <?= htmlspecialchars($employeeData['name']) ?></title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
            <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"><\/script>
            <style>
                body { margin: 0; padding: 20px; background: #fff !important; }
                @media print {
                    body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
                    .id-card-bright { box-shadow: none !important; }
                }
            </style>
        </head>
        <body>
            ${printContent}
            <script>
                JsBarcode("#employeeBarcode", "${empId}", {
                    format: "CODE128",
                    width: 2,
                    height: 40,
                    displayValue: true,
                    fontSize: 12,
                    margin: 5,
                    background: "#ffffff",
                    lineColor: "#1a1a2e"
                });
                setTimeout(function() { window.print(); window.close(); }, 300);
            <\/script>
        </body>
        </html>
    `);
    printWindow.document.close();
}
</script>

<?php elseif ($action === 'create_department' || $action === 'edit_department'): ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-building"></i> <?= $action === 'create_department' ? 'Add Department' : 'Edit Department' ?></h2>
    <a href="?page=hr&subpage=departments" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="<?= $action === 'create_department' ? 'create_department' : 'update_department' ?>">
            <?php if ($action === 'edit_department'): ?>
            <input type="hidden" name="id" value="<?= $departmentData['id'] ?>">
            <?php endif; ?>
            
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Department Name *</label>
                    <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($departmentData['name'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Manager</label>
                    <select class="form-select" name="manager_id">
                        <option value="">No Manager</option>
                        <?php foreach ($allEmployees as $emp): ?>
                        <option value="<?= $emp['id'] ?>" <?= ($departmentData['manager_id'] ?? '') == $emp['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($emp['name']) ?> - <?= htmlspecialchars($emp['position']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea class="form-control" name="description" rows="3"><?= htmlspecialchars($departmentData['description'] ?? '') ?></textarea>
                </div>
            </div>
            
            <div class="mt-4">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg"></i> <?= $action === 'create_department' ? 'Create Department' : 'Update Department' ?>
                </button>
                <a href="?page=hr&subpage=departments" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php else: ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-people-fill"></i> Human Resources</h2>
    <?php if ($subpage === 'employees'): ?>
    <div class="d-flex gap-2">
        <?php
        $hikDevices = [];
        try {
            $bioDb = Database::getConnection();
            $hikDevices = $bioDb->query("SELECT id, name FROM biometric_devices WHERE is_active = true AND device_type = 'hikvision'")->fetchAll();
        } catch (\Exception $e) {
            // Table may not exist
        }
        if (!empty($hikDevices)):
        ?>
        <div class="dropdown">
            <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                <i class="bi bi-fingerprint"></i> Sync to Device
            </button>
            <ul class="dropdown-menu">
                <?php foreach ($hikDevices as $dev): ?>
                <li><a class="dropdown-item" href="#" onclick="syncAllEmployeesToDevice(<?= $dev['id'] ?>); return false;"><?= htmlspecialchars($dev['name']) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        <a href="?page=hr&action=create_employee" class="btn btn-primary">
            <i class="bi bi-person-plus"></i> Add Employee
        </a>
    </div>
    <?php elseif ($subpage === 'departments'): ?>
    <a href="?page=hr&action=create_department" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> Add Department
    </a>
    <?php endif; ?>
</div>

<div id="bulkSyncStatus" class="alert d-none mb-3"></div>

<script>
function syncAllEmployeesToDevice(deviceId) {
    var statusDiv = document.getElementById('bulkSyncStatus');
    statusDiv.className = 'alert alert-info mb-3';
    statusDiv.innerHTML = '<i class="bi bi-hourglass-split"></i> Syncing all active employees to biometric device... This may take a moment.';
    statusDiv.classList.remove('d-none');
    
    fetch('/biometric-api.php?action=sync-employees-to-device', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ device_id: deviceId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            statusDiv.className = 'alert alert-success mb-3';
            statusDiv.innerHTML = '<i class="bi bi-check-circle"></i> Sync complete! ' + data.success_count + ' employees registered, ' + data.fail_count + ' failed.';
        } else {
            statusDiv.className = 'alert alert-danger mb-3';
            statusDiv.textContent = 'Failed: ' + (data.error || 'Unknown error');
        }
        setTimeout(function() { statusDiv.classList.add('d-none'); }, 10000);
    })
    .catch(e => {
        statusDiv.className = 'alert alert-danger mb-3';
        statusDiv.textContent = 'Error: ' + e.message;
    });
}
</script>

<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon bg-primary bg-opacity-10 text-primary me-3">
                    <i class="bi bi-people"></i>
                </div>
                <div>
                    <h3 class="mb-0"><?= $hrStats['total'] ?? 0 ?></h3>
                    <small class="text-muted">Total Employees</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon bg-success bg-opacity-10 text-success me-3">
                    <i class="bi bi-person-check"></i>
                </div>
                <div>
                    <h3 class="mb-0"><?= $hrStats['active'] ?? 0 ?></h3>
                    <small class="text-muted">Active</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon bg-warning bg-opacity-10 text-warning me-3">
                    <i class="bi bi-calendar-x"></i>
                </div>
                <div>
                    <h3 class="mb-0"><?= $hrStats['on_leave'] ?? 0 ?></h3>
                    <small class="text-muted">On Leave</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon bg-info bg-opacity-10 text-info me-3">
                    <i class="bi bi-building"></i>
                </div>
                <div>
                    <h3 class="mb-0"><?= $hrStats['departments'] ?? 0 ?></h3>
                    <small class="text-muted">Departments</small>
                </div>
            </div>
        </div>
    </div>
</div>

<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link <?= $subpage === 'employees' ? 'active' : '' ?>" href="?page=hr&subpage=employees">
            <i class="bi bi-people"></i> Employees
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $subpage === 'departments' ? 'active' : '' ?>" href="?page=hr&subpage=departments">
            <i class="bi bi-building"></i> Departments
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $subpage === 'attendance' ? 'active' : '' ?>" href="?page=hr&subpage=attendance">
            <i class="bi bi-clock-history"></i> Attendance
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $subpage === 'payroll' ? 'active' : '' ?>" href="?page=hr&subpage=payroll">
            <i class="bi bi-cash-stack"></i> Payroll
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $subpage === 'performance' ? 'active' : '' ?>" href="?page=hr&subpage=performance">
            <i class="bi bi-graph-up"></i> Performance
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $subpage === 'late_arrivals' ? 'active' : '' ?>" href="?page=hr&subpage=late_arrivals">
            <i class="bi bi-alarm"></i> Late Arrivals
            <?php if ($todayLateCount > 0): ?><span class="badge bg-warning rounded-pill ms-1"><?= $todayLateCount ?></span><?php endif; ?>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $subpage === 'salespeople' ? 'active' : '' ?>" href="?page=hr&subpage=salespeople">
            <i class="bi bi-person-badge"></i> Salespeople
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $subpage === 'teams' ? 'active' : '' ?>" href="?page=hr&subpage=teams">
            <i class="bi bi-people-fill"></i> Teams
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $subpage === 'commissions' ? 'active' : '' ?>" href="?page=hr&subpage=commissions">
            <i class="bi bi-ticket-perforated"></i> Commissions
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $subpage === 'advances' ? 'active' : '' ?>" href="?page=hr&subpage=advances">
            <i class="bi bi-cash-coin"></i> Advances
            <?php if ($pendingAdvanceCount > 0): ?><span class="badge bg-danger rounded-pill ms-1"><?= $pendingAdvanceCount ?></span><?php endif; ?>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $subpage === 'leave' ? 'active' : '' ?>" href="?page=hr&subpage=leave">
            <i class="bi bi-calendar-check"></i> Leave
            <?php if ($pendingLeaveCount > 0): ?><span class="badge bg-danger rounded-pill ms-1"><?= $pendingLeaveCount ?></span><?php endif; ?>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $subpage === 'announcements' ? 'active' : '' ?>" href="?page=hr&subpage=announcements">
            <i class="bi bi-megaphone"></i> Announcements
        </a>
    </li>
</ul>

<?php if ($subpage === 'departments'): ?>

<div class="row g-4">
    <?php foreach ($departments as $dept): ?>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title"><?= htmlspecialchars($dept['name']) ?></h5>
                <p class="text-muted small mb-2"><?= htmlspecialchars($dept['description'] ?? 'No description') ?></p>
                <p class="mb-1"><i class="bi bi-people"></i> <?= $dept['employee_count'] ?> Employees</p>
                <p class="mb-3"><i class="bi bi-person-badge"></i> Manager: <?= htmlspecialchars($dept['manager_name'] ?? 'Not assigned') ?></p>
                <a href="?page=hr&action=edit_department&id=<?= $dept['id'] ?>" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-pencil"></i> Edit
                </a>
                <?php if (\App\Auth::isAdmin()): ?>
                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this department?')">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="delete_department">
                    <input type="hidden" name="id" value="<?= $dept['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger">
                        <i class="bi bi-trash"></i> Delete
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($departments)): ?>
    <div class="col-12">
        <div class="text-center text-muted py-5">
            <i class="bi bi-building" style="font-size: 3rem;"></i>
            <p class="mt-3">No departments yet. <a href="?page=hr&action=create_department">Create your first department</a></p>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php elseif ($subpage === 'attendance'): ?>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <input type="hidden" name="page" value="hr">
            <input type="hidden" name="subpage" value="attendance">
            <div class="col-md-3">
                <label class="form-label">Date</label>
                <input type="date" class="form-control" name="date" value="<?= $selectedDate ?>">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-filter"></i> Filter
                </button>
            </div>
            <div class="col-md-6 text-end">
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#attendanceModal">
                    <i class="bi bi-plus-circle"></i> Record Attendance
                </button>
            </div>
        </form>
    </div>
</div>

<?php $attendanceRecords = $employee->getAllAttendance($selectedDate); ?>

<div class="card">
    <div class="card-header bg-white">
        <h5 class="mb-0">Attendance for <?= date('F j, Y', strtotime($selectedDate)) ?></h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Clock In</th>
                        <th>Clock Out</th>
                        <th>Hours</th>
                        <th>Overtime</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($attendanceRecords as $att): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($att['employee_name']) ?></strong>
                            <br><small class="text-muted"><?= htmlspecialchars($att['emp_code']) ?></small>
                        </td>
                        <td><?= htmlspecialchars($att['department_name'] ?? '-') ?></td>
                        <td><?= $att['clock_in'] ? date('h:i A', strtotime($att['clock_in'])) : '-' ?></td>
                        <td><?= $att['clock_out'] ? date('h:i A', strtotime($att['clock_out'])) : '-' ?></td>
                        <td><?= $att['hours_worked'] ? number_format($att['hours_worked'], 1) . 'h' : '-' ?></td>
                        <td><?= $att['overtime_hours'] > 0 ? number_format($att['overtime_hours'], 1) . 'h' : '-' ?></td>
                        <td>
                            <span class="badge bg-<?= $att['status'] === 'present' ? 'success' : ($att['status'] === 'absent' ? 'danger' : ($att['status'] === 'late' ? 'warning' : 'info')) ?>">
                                <?= ucfirst(str_replace('_', ' ', $att['status'])) ?>
                            </span>
                        </td>
                        <td>
                            <?php if (\App\Auth::isAdmin()): ?>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this record?')">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="action" value="delete_attendance">
                                <input type="hidden" name="id" value="<?= $att['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($attendanceRecords)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            No attendance records for this date.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="attendanceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="record_attendance">
                <div class="modal-header">
                    <h5 class="modal-title">Record Attendance</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Employee *</label>
                        <select class="form-select" name="employee_id" required>
                            <option value="">Select Employee</option>
                            <?php foreach ($allEmployees as $emp): ?>
                            <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['name']) ?> (<?= $emp['employee_id'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Date *</label>
                        <input type="date" class="form-control" name="date" value="<?= $selectedDate ?>" required>
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <div class="mb-3">
                                <label class="form-label">Clock In</label>
                                <input type="time" class="form-control" name="clock_in">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="mb-3">
                                <label class="form-label">Clock Out</label>
                                <input type="time" class="form-control" name="clock_out">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <?php foreach ($attendanceStatuses as $key => $label): ?>
                            <option value="<?= $key ?>"><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="2"></textarea>
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

<?php elseif ($subpage === 'payroll'): ?>

<?php $payrollRecords = $employee->getAllPayroll(null, $selectedMonth); ?>
<?php $payrollStats = $employee->getPayrollStats($selectedMonth); ?>

<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h4><?= $currencySymbol ?> <?= number_format($payrollStats['total_paid'] ?? 0, 2) ?></h4>
                <small>Total Paid</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-warning text-dark">
            <div class="card-body">
                <h4><?= $currencySymbol ?> <?= number_format($payrollStats['total_pending'] ?? 0, 2) ?></h4>
                <small>Pending Payment</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-info text-white">
            <div class="card-body">
                <h4><?= $payrollStats['total_records'] ?? 0 ?></h4>
                <small>Total Records</small>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <input type="hidden" name="page" value="hr">
            <input type="hidden" name="subpage" value="payroll">
            <div class="col-md-3">
                <label class="form-label">Month</label>
                <input type="month" class="form-control" name="month" value="<?= $selectedMonth ?>">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-filter"></i> Filter
                </button>
            </div>
            <div class="col-md-6 text-end">
                <button type="button" class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#bulkPayrollModal">
                    <i class="bi bi-people-fill"></i> Generate All
                </button>
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#payrollModal">
                    <i class="bi bi-plus-circle"></i> Create Payroll
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Employee</th>
                        <th>Pay Period</th>
                        <th>Base Salary</th>
                        <th>Bonuses</th>
                        <th>Deductions</th>
                        <th>Net Pay</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payrollRecords as $pay): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($pay['employee_name']) ?></strong>
                            <br><small class="text-muted"><?= htmlspecialchars($pay['emp_code']) ?></small>
                        </td>
                        <td>
                            <?= date('M j', strtotime($pay['pay_period_start'])) ?> - <?= date('M j, Y', strtotime($pay['pay_period_end'])) ?>
                        </td>
                        <td><?= $currencySymbol ?> <?= number_format($pay['base_salary'], 2) ?></td>
                        <td class="text-success">+<?= $currencySymbol ?> <?= number_format($pay['bonuses'] + $pay['overtime_pay'], 2) ?></td>
                        <td class="text-danger">-<?= $currencySymbol ?> <?= number_format($pay['deductions'] + $pay['tax'], 2) ?></td>
                        <td><strong><?= $currencySymbol ?> <?= number_format($pay['net_pay'], 2) ?></strong></td>
                        <td>
                            <span class="badge bg-<?= $pay['status'] === 'paid' ? 'success' : ($pay['status'] === 'pending' ? 'warning' : 'secondary') ?>">
                                <?= ucfirst($pay['status']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if (\App\Auth::isAdmin()): ?>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this payroll record?')">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="action" value="delete_payroll">
                                <input type="hidden" name="id" value="<?= $pay['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($payrollRecords)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            No payroll records found.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="payrollModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="create_payroll">
                <div class="modal-header">
                    <h5 class="modal-title">Create Payroll Record</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label">Employee *</label>
                            <select class="form-select" name="employee_id" required>
                                <option value="">Select Employee</option>
                                <?php foreach ($allEmployees as $emp): ?>
                                <option value="<?= $emp['id'] ?>" data-salary="<?= $emp['salary'] ?? 0 ?>">
                                    <?= htmlspecialchars($emp['name']) ?> (<?= $emp['employee_id'] ?>) - <?= $currencySymbol ?> <?= number_format($emp['salary'] ?? 0, 2) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Pay Period Start *</label>
                            <input type="date" class="form-control" name="pay_period_start" value="<?= $selectedMonth ?>-01" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Pay Period End *</label>
                            <input type="date" class="form-control" name="pay_period_end" value="<?= date('Y-m-t', strtotime($selectedMonth . '-01')) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Base Salary *</label>
                            <input type="number" step="0.01" class="form-control" name="base_salary" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Overtime Pay</label>
                            <input type="number" step="0.01" class="form-control" name="overtime_pay" value="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Bonuses</label>
                            <input type="number" step="0.01" class="form-control" name="bonuses" value="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Manual Deductions</label>
                            <input type="number" step="0.01" class="form-control" name="deductions" value="0" id="manualDeductions">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tax</label>
                            <input type="number" step="0.01" class="form-control" name="tax" value="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <?php foreach ($payrollStatuses as $key => $label): ?>
                                <option value="<?= $key ?>"><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" name="include_late_deductions" id="includeLateDeductions" value="1" checked>
                                <label class="form-check-label" for="includeLateDeductions">
                                    <i class="bi bi-alarm text-warning"></i> Include late arrival deductions automatically
                                </label>
                            </div>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" name="include_ticket_commissions" id="includeTicketCommissions" value="1" checked>
                                <label class="form-check-label" for="includeTicketCommissions">
                                    <i class="bi bi-ticket-perforated text-success"></i> Include ticket commissions automatically
                                </label>
                            </div>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" name="include_advance_deductions" id="includeAdvanceDeductions" value="1" checked>
                                <label class="form-check-label" for="includeAdvanceDeductions">
                                    <i class="bi bi-cash-coin text-danger"></i> Include salary advance deductions automatically
                                </label>
                            </div>
                            <div id="lateDeductionPreview" class="alert alert-info mt-2 d-none">
                                <small>
                                    <strong><i class="bi bi-info-circle"></i> Late Deduction Preview</strong>
                                    <div id="lateDeductionInfo">Select an employee and pay period to see estimated late deductions</div>
                                </small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Payment Date</label>
                            <input type="date" class="form-control" name="payment_date">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Payment Method</label>
                            <select class="form-select" name="payment_method">
                                <option value="">Select Method</option>
                                <?php foreach ($paymentMethods as $key => $label): ?>
                                <option value="<?= $key ?>"><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Payroll</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="bulkPayrollModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="bulk_generate_payroll">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-people-fill"></i> Generate Payroll for All Employees</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info mb-3">
                        <i class="bi bi-info-circle"></i> This will create payroll records for all active employees with a salary configured. Existing records for the same period will be skipped.
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Pay Period Start *</label>
                            <input type="date" class="form-control" name="pay_period_start" value="<?= $selectedMonth ?>-01" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Pay Period End *</label>
                            <input type="date" class="form-control" name="pay_period_end" value="<?= date('Y-m-t', strtotime($selectedMonth . '-01')) ?>" required>
                        </div>
                        <div class="col-12">
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" name="include_late_deductions" id="bulkIncludeLateDeductions" value="1" checked>
                                <label class="form-check-label" for="bulkIncludeLateDeductions">
                                    <i class="bi bi-alarm text-warning"></i> Include late arrival deductions
                                </label>
                            </div>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" name="include_ticket_commissions" id="bulkIncludeTicketCommissions" value="1" checked>
                                <label class="form-check-label" for="bulkIncludeTicketCommissions">
                                    <i class="bi bi-ticket-perforated text-success"></i> Include ticket commissions
                                </label>
                            </div>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" name="include_advance_deductions" id="bulkIncludeAdvanceDeductions" value="1" checked>
                                <label class="form-check-label" for="bulkIncludeAdvanceDeductions">
                                    <i class="bi bi-cash-coin text-danger"></i> Include salary advance deductions
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-play-fill"></i> Generate All Payroll
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php elseif ($subpage === 'performance'): ?>

<?php 
$performanceReviews = $employee->getAllPerformanceReviews(); 
$performanceStats = $employee->getPerformanceStats();
$salespersonModel = new \App\Salesperson($db);
$salesLeaderboard = $salespersonModel->getLeaderboard('month');
?>

<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h4><?= $performanceStats['completed'] ?? 0 ?></h4>
                <small>Completed Reviews</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-dark">
            <div class="card-body">
                <h4><?= $performanceStats['pending'] ?? 0 ?></h4>
                <small>Pending Reviews</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <h4><?= number_format($performanceStats['avg_rating'] ?? 0, 1) ?>/5</h4>
                <small>Average Rating</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h4><?= count($salesLeaderboard) ?></h4>
                <small>Active Salespeople</small>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($salesLeaderboard)): ?>
<div class="card mb-4">
    <div class="card-header bg-light">
        <h6 class="mb-0"><i class="bi bi-trophy"></i> Sales Leaderboard (Last 30 Days)</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 50px;">Rank</th>
                        <th>Salesperson</th>
                        <th class="text-end">Orders</th>
                        <th class="text-end">Total Sales</th>
                        <th class="text-end">Commission Earned</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($salesLeaderboard as $index => $seller): ?>
                    <tr>
                        <td>
                            <?php if ($index === 0): ?>
                            <span class="badge bg-warning text-dark"><i class="bi bi-trophy"></i> 1</span>
                            <?php elseif ($index === 1): ?>
                            <span class="badge bg-secondary">2</span>
                            <?php elseif ($index === 2): ?>
                            <span class="badge bg-dark">3</span>
                            <?php else: ?>
                            <span class="badge bg-light text-dark"><?= $index + 1 ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($seller['name']) ?></strong>
                            <br><small class="text-muted"><?= htmlspecialchars($seller['phone']) ?></small>
                        </td>
                        <td class="text-end"><?= $seller['order_count'] ?></td>
                        <td class="text-end">KES <?= number_format($seller['total_sales'], 0) ?></td>
                        <td class="text-end text-success">KES <?= number_format($seller['total_commission'], 0) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-body d-flex justify-content-end">
        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#performanceModal">
            <i class="bi bi-plus-circle"></i> Create Performance Review
        </button>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Employee</th>
                        <th>Review Period</th>
                        <th>Sales Metrics</th>
                        <th>Reviewer</th>
                        <th>Overall Rating</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($performanceReviews as $review): ?>
                    <?php 
                    $empSalesMetrics = $salespersonModel->getEmployeeSalesMetrics(
                        $review['employee_id'],
                        $review['review_period_start'],
                        $review['review_period_end']
                    );
                    ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($review['employee_name']) ?></strong>
                            <br><small class="text-muted"><?= htmlspecialchars($review['department_name'] ?? 'No Department') ?></small>
                            <?php if ($empSalesMetrics['is_salesperson']): ?>
                            <br><span class="badge bg-info"><i class="bi bi-person-badge"></i> Sales Team</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= date('M j, Y', strtotime($review['review_period_start'])) ?> - <?= date('M j, Y', strtotime($review['review_period_end'])) ?>
                        </td>
                        <td>
                            <?php if ($empSalesMetrics['is_salesperson']): ?>
                            <div class="small">
                                <strong><?= $empSalesMetrics['period_orders'] ?></strong> orders<br>
                                <span class="text-success">KES <?= number_format($empSalesMetrics['period_sales'], 0) ?></span>
                                <?php if ($empSalesMetrics['rank']): ?>
                                <br><span class="badge bg-<?= $empSalesMetrics['rank'] <= 3 ? 'warning' : 'light text-dark' ?>">Rank #<?= $empSalesMetrics['rank'] ?></span>
                                <?php endif; ?>
                            </div>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($review['reviewer_name'] ?? 'Not assigned') ?></td>
                        <td>
                            <?php if ($review['overall_rating']): ?>
                            <div class="d-flex align-items-center">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="bi bi-star<?= $i <= $review['overall_rating'] ? '-fill text-warning' : '' ?>"></i>
                                <?php endfor; ?>
                                <span class="ms-2"><?= $review['overall_rating'] ?>/5</span>
                            </div>
                            <?php else: ?>
                            <span class="text-muted">Not rated</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-<?= $review['status'] === 'completed' || $review['status'] === 'acknowledged' ? 'success' : ($review['status'] === 'draft' ? 'secondary' : 'warning') ?>">
                                <?= ucfirst(str_replace('_', ' ', $review['status'])) ?>
                            </span>
                        </td>
                        <td>
                            <?php if (\App\Auth::isAdmin()): ?>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this performance review?')">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="action" value="delete_performance">
                                <input type="hidden" name="id" value="<?= $review['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($performanceReviews)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            No performance reviews found.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="performanceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="create_performance">
                <div class="modal-header">
                    <h5 class="modal-title">Create Performance Review</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Employee *</label>
                            <select class="form-select" name="employee_id" required>
                                <option value="">Select Employee</option>
                                <?php foreach ($allEmployees as $emp): ?>
                                <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['name']) ?> (<?= $emp['employee_id'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Reviewer</label>
                            <select class="form-select" name="reviewer_id">
                                <option value="">Select Reviewer</option>
                                <?php foreach ($allEmployees as $emp): ?>
                                <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Review Period Start *</label>
                            <input type="date" class="form-control" name="review_period_start" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Review Period End *</label>
                            <input type="date" class="form-control" name="review_period_end" required>
                        </div>
                        <div class="col-12">
                            <h6 class="mt-3">Ratings (1-5)</h6>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Overall</label>
                            <select class="form-select" name="overall_rating">
                                <option value="">Not rated</option>
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                <option value="<?= $i ?>"><?= $i ?> - <?= ['Poor', 'Below Average', 'Average', 'Good', 'Excellent'][$i-1] ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Productivity</label>
                            <select class="form-select" name="productivity_rating">
                                <option value="">Not rated</option>
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                <option value="<?= $i ?>"><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Quality</label>
                            <select class="form-select" name="quality_rating">
                                <option value="">Not rated</option>
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                <option value="<?= $i ?>"><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Teamwork</label>
                            <select class="form-select" name="teamwork_rating">
                                <option value="">Not rated</option>
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                <option value="<?= $i ?>"><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Communication</label>
                            <select class="form-select" name="communication_rating">
                                <option value="">Not rated</option>
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                <option value="<?= $i ?>"><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Goals Achieved</label>
                            <textarea class="form-control" name="goals_achieved" rows="2"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Strengths</label>
                            <textarea class="form-control" name="strengths" rows="2"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Areas for Improvement</label>
                            <textarea class="form-control" name="areas_for_improvement" rows="2"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Goals for Next Period</label>
                            <textarea class="form-control" name="goals_next_period" rows="2"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Additional Comments</label>
                            <textarea class="form-control" name="comments" rows="2"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <?php foreach ($performanceStatuses as $key => $label): ?>
                                <option value="<?= $key ?>"><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Review</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php elseif ($subpage === 'late_arrivals'): ?>

<?php
$lateCalculator = new \App\LateDeductionCalculator($db);
$biometricService = new \App\BiometricSyncService($db);
$lateReportMonth = $_GET['late_month'] ?? date('Y-m');
$lateReportDept = $_GET['late_dept'] ?? '';
$lateArrivals = $lateCalculator->getMonthlyLateArrivals($lateReportMonth, $lateReportDept ?: null);
$lateStats = $lateCalculator->getMonthlyLateStats($lateReportMonth, $lateReportDept ?: null);
$lastSync = $biometricService->getLastSyncTime();
?>

<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card bg-warning bg-opacity-10">
            <div class="card-body">
                <h3 class="mb-0"><?= $lateStats['total_late_days'] ?? 0 ?></h3>
                <small class="text-muted">Late Arrivals</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-danger bg-opacity-10">
            <div class="card-body">
                <h3 class="mb-0"><?= number_format($lateStats['total_late_minutes'] ?? 0) ?></h3>
                <small class="text-muted">Total Late Minutes</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info bg-opacity-10">
            <div class="card-body">
                <h3 class="mb-0"><?= $lateStats['employees_affected'] ?? 0 ?></h3>
                <small class="text-muted">Employees Affected</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-primary bg-opacity-10">
            <div class="card-body">
                <h3 class="mb-0"><?= $lateStats['currency'] ?? 'KES' ?> <?= number_format($lateStats['total_deductions'] ?? 0, 2) ?></h3>
                <small class="text-muted">Total Deductions</small>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <input type="hidden" name="page" value="hr">
            <input type="hidden" name="subpage" value="late_arrivals">
            <div class="col-md-3">
                <label class="form-label">Month</label>
                <input type="month" class="form-control" name="late_month" value="<?= $lateReportMonth ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Department</label>
                <select class="form-select" name="late_dept">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $dept): ?>
                    <option value="<?= $dept['id'] ?>" <?= $lateReportDept == $dept['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($dept['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-filter"></i> Filter
                </button>
                <a href="?page=hr&subpage=late_arrivals&action=sync_biometric" class="btn btn-outline-success">
                    <i class="bi bi-arrow-repeat"></i> Sync Devices
                </a>
            </div>
            <div class="col-md-3 text-end">
                <small class="text-muted">
                    <?php if ($lastSync): ?>
                    Last sync: <?= date('M j, g:i A', strtotime($lastSync)) ?>
                    <?php else: ?>
                    Never synced
                    <?php endif; ?>
                </small>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-alarm text-warning"></i> Late Arrival Report - <?= date('F Y', strtotime($lateReportMonth . '-01')) ?></h5>
        <a href="?page=settings&subpage=late_rules" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-gear"></i> Configure Rules
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Expected</th>
                        <th>Actual</th>
                        <th>Late By</th>
                        <th>Deduction</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($lateArrivals)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            <i class="bi bi-check-circle text-success" style="font-size: 2rem;"></i>
                            <p class="mb-0 mt-2">No late arrivals for this period</p>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($lateArrivals as $late): ?>
                    <tr>
                        <td><?= date('M j, Y', strtotime($late['date'])) ?></td>
                        <td>
                            <strong><?= htmlspecialchars($late['employee_name']) ?></strong>
                            <small class="text-muted d-block"><?= htmlspecialchars($late['employee_code'] ?? '') ?></small>
                        </td>
                        <td><?= htmlspecialchars($late['department_name'] ?? '-') ?></td>
                        <td><?= $late['expected_time'] ? date('g:i A', strtotime($late['expected_time'])) : '-' ?></td>
                        <td>
                            <span class="text-danger"><?= $late['clock_in'] ? date('g:i A', strtotime($late['clock_in'])) : '-' ?></span>
                        </td>
                        <td>
                            <span class="badge bg-warning text-dark">
                                <?= $late['late_minutes'] ?> min
                            </span>
                        </td>
                        <td>
                            <?php if (($late['deduction'] ?? 0) > 0): ?>
                            <span class="text-danger"><?= $late['currency'] ?? 'KES' ?> <?= number_format($late['deduction'], 2) ?></span>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                    data-bs-toggle="modal" data-bs-target="#editPenaltyModal"
                                    data-attendance-id="<?= $late['id'] ?>"
                                    data-employee="<?= htmlspecialchars($late['employee_name']) ?>"
                                    data-date="<?= $late['date'] ?>"
                                    data-minutes="<?= $late['late_minutes'] ?>"
                                    data-deduction="<?= $late['deduction'] ?? 0 ?>">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Remove late penalty for this record?')">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="action" value="remove_late_penalty">
                                <input type="hidden" name="attendance_id" value="<?= $late['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove Penalty">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if (!empty($lateArrivals)): ?>
<div class="card mt-4">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="bi bi-bar-chart"></i> Summary by Employee</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Employee</th>
                        <th>Late Days</th>
                        <th>Total Late Minutes</th>
                        <th>Avg. Late (min)</th>
                        <th>Total Deduction</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $employeeSummary = [];
                    foreach ($lateArrivals as $late) {
                        $empId = $late['employee_id'];
                        if (!isset($employeeSummary[$empId])) {
                            $employeeSummary[$empId] = [
                                'name' => $late['employee_name'],
                                'days' => 0,
                                'minutes' => 0,
                                'deductions' => 0,
                                'currency' => $late['currency'] ?? 'KES'
                            ];
                        }
                        $employeeSummary[$empId]['days']++;
                        $employeeSummary[$empId]['minutes'] += $late['late_minutes'];
                        $employeeSummary[$empId]['deductions'] += ($late['deduction'] ?? 0);
                    }
                    usort($employeeSummary, fn($a, $b) => $b['deductions'] <=> $a['deductions']);
                    ?>
                    <?php foreach ($employeeSummary as $summary): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($summary['name']) ?></strong></td>
                        <td><?= $summary['days'] ?></td>
                        <td><?= $summary['minutes'] ?></td>
                        <td><?= round($summary['minutes'] / $summary['days']) ?></td>
                        <td class="text-danger"><?= $summary['currency'] ?> <?= number_format($summary['deductions'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Edit Penalty Modal -->
<div class="modal fade" id="editPenaltyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil"></i> Edit Late Penalty</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="update_late_penalty">
                    <input type="hidden" name="attendance_id" id="editPenaltyAttendanceId">
                    
                    <div class="mb-3">
                        <label class="form-label">Employee</label>
                        <input type="text" class="form-control" id="editPenaltyEmployee" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Date</label>
                        <input type="text" class="form-control" id="editPenaltyDate" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Late Minutes</label>
                        <input type="number" class="form-control" name="late_minutes" id="editPenaltyMinutes" min="0">
                        <small class="text-muted">Set to 0 to mark as on time</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Deduction Amount</label>
                        <div class="input-group">
                            <span class="input-group-text">KES</span>
                            <input type="number" class="form-control" name="deduction" id="editPenaltyDeduction" min="0" step="0.01">
                        </div>
                        <small class="text-muted">Set to 0 to remove penalty</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reason for Adjustment</label>
                        <textarea class="form-control" name="adjustment_reason" rows="2" placeholder="Optional: explain why the penalty is being adjusted"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const editModal = document.getElementById('editPenaltyModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            document.getElementById('editPenaltyAttendanceId').value = button.dataset.attendanceId;
            document.getElementById('editPenaltyEmployee').value = button.dataset.employee;
            document.getElementById('editPenaltyDate').value = button.dataset.date;
            document.getElementById('editPenaltyMinutes').value = button.dataset.minutes;
            document.getElementById('editPenaltyDeduction').value = button.dataset.deduction;
        });
    }
});
</script>

<?php elseif ($subpage === 'salespeople'): ?>
<?php
$salespersonModel = new \App\Salesperson($db);
$allSalespersons = $salespersonModel->getAll();
$spAction = $_GET['sp_action'] ?? 'list';
$spId = isset($_GET['sp_id']) ? (int)$_GET['sp_id'] : null;
$editSalesperson = $spId ? $salespersonModel->getById($spId) : null;
$defaultCommission = $salespersonModel->getDefaultCommission();
?>

<?php if ($spAction === 'add' || $spAction === 'edit'): ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4><i class="bi bi-person-plus"></i> <?= $spAction === 'edit' ? 'Edit Salesperson' : 'Add Salesperson' ?></h4>
    <a href="?page=hr&subpage=salespeople" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="<?= $spAction === 'edit' ? 'update_salesperson' : 'save_salesperson' ?>">
            <?php if ($editSalesperson): ?>
            <input type="hidden" name="salesperson_id" value="<?= $editSalesperson['id'] ?>">
            <?php endif; ?>
            
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Name *</label>
                    <input type="text" class="form-control" name="name" required 
                           value="<?= htmlspecialchars($editSalesperson['name'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Phone *</label>
                    <input type="text" class="form-control" name="phone" required 
                           value="<?= htmlspecialchars($editSalesperson['phone'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" name="email" 
                           value="<?= htmlspecialchars($editSalesperson['email'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Link to Employee</label>
                    <select name="employee_id" class="form-select">
                        <option value="">-- None --</option>
                        <?php foreach ($allEmployees as $emp): ?>
                        <option value="<?= $emp['id'] ?>" <?= ($editSalesperson['employee_id'] ?? '') == $emp['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($emp['name']) ?> (<?= htmlspecialchars($emp['employee_id']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Link to employee record for performance tracking</small>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Commission Type</label>
                    <select name="commission_type" class="form-select" id="spCommType">
                        <option value="percentage" <?= (!$editSalesperson || $editSalesperson['commission_type'] === 'percentage') ? 'selected' : '' ?>>Percentage (%)</option>
                        <option value="fixed" <?= ($editSalesperson && $editSalesperson['commission_type'] === 'fixed') ? 'selected' : '' ?>>Fixed (KES)</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Commission Value</label>
                    <div class="input-group">
                        <span class="input-group-text" id="spCommPrefix">%</span>
                        <input type="number" step="0.01" name="commission_value" class="form-control" 
                               value="<?= $editSalesperson ? $editSalesperson['commission_value'] : $defaultCommission['value'] ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select name="is_active" class="form-select">
                        <option value="1" <?= (!$editSalesperson || $editSalesperson['is_active']) ? 'selected' : '' ?>>Active</option>
                        <option value="0" <?= ($editSalesperson && !$editSalesperson['is_active']) ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="2"><?= htmlspecialchars($editSalesperson['notes'] ?? '') ?></textarea>
                </div>
            </div>
            
            <div class="mt-4">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg"></i> Save Salesperson
                </button>
                <a href="?page=hr&subpage=salespeople" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php else: ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4><i class="bi bi-person-badge"></i> Sales Team</h4>
    <?php if (\App\Auth::isAdmin()): ?>
    <a href="?page=hr&subpage=salespeople&sp_action=add" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> Add Salesperson
    </a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Contact</th>
                        <th>Employee</th>
                        <th>Commission</th>
                        <th>Total Sales</th>
                        <th>Total Commission</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allSalespersons as $sp): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($sp['name']) ?></strong></td>
                        <td>
                            <?= htmlspecialchars($sp['phone']) ?>
                            <?php if ($sp['email']): ?>
                            <br><small class="text-muted"><?= htmlspecialchars($sp['email']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($sp['employee_name']): ?>
                            <a href="?page=hr&action=view_employee&id=<?= $sp['employee_id'] ?>">
                                <?= htmlspecialchars($sp['employee_name']) ?>
                            </a>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($sp['commission_type'] === 'percentage'): ?>
                            <span class="badge bg-info"><?= number_format($sp['commission_value'], 1) ?>%</span>
                            <?php else: ?>
                            <span class="badge bg-success">KES <?= number_format($sp['commission_value'], 2) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>KES <?= number_format($sp['total_sales'], 0) ?></td>
                        <td>KES <?= number_format($sp['total_commission'], 0) ?></td>
                        <td>
                            <?php if ($sp['is_active']): ?>
                            <span class="badge bg-success">Active</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (\App\Auth::isAdmin()): ?>
                            <div class="btn-group btn-group-sm">
                                <a href="?page=hr&subpage=salespeople&sp_action=edit&sp_id=<?= $sp['id'] ?>" class="btn btn-outline-primary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this salesperson?')">
                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                    <input type="hidden" name="action" value="delete_salesperson">
                                    <input type="hidden" name="salesperson_id" value="<?= $sp['id'] ?>">
                                    <button type="submit" class="btn btn-outline-danger" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($allSalespersons)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            <i class="bi bi-person-badge" style="font-size: 2rem;"></i>
                            <p class="mt-2">No salespeople found. <a href="?page=hr&subpage=salespeople&sp_action=add">Add your first salesperson</a></p>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php elseif ($subpage === 'teams'): ?>

<?php
$ticketManager = new \App\Ticket();
$allTeams = $ticketManager->getAllTeams();
$allEmployees = (new \App\Employee($db))->getAll();

$editTeam = null;
$teamMembers = [];
if ($action === 'edit_team' && $id) {
    $editTeam = $ticketManager->getTeam($id);
    $teamMembers = $ticketManager->getTeamMembers($id);
}
?>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-people-fill"></i> Teams</h5>
                <?php if (\App\Auth::isAdmin()): ?>
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#teamModal" onclick="resetTeamForm()">
                    <i class="bi bi-plus-circle"></i> Add Team
                </button>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Team Name</th>
                                <th>Description</th>
                                <th>Members</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $db = \Database::getConnection();
                            $teamsStmt = $db->query("SELECT t.*, 
                                (SELECT COUNT(*) FROM team_members tm WHERE tm.team_id = t.id) as member_count,
                                e.name as leader_name
                                FROM teams t 
                                LEFT JOIN employees e ON t.leader_id = e.id
                                ORDER BY t.name");
                            $allTeamsData = $teamsStmt->fetchAll();
                            foreach ($allTeamsData as $t): 
                            ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($t['name']) ?></strong>
                                    <?php if ($t['leader_name']): ?>
                                    <br><small class="text-muted">Leader: <?= htmlspecialchars($t['leader_name']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($t['description'] ?? '-') ?></td>
                                <td><span class="badge bg-primary"><?= $t['member_count'] ?> members</span></td>
                                <td>
                                    <span class="badge bg-<?= $t['is_active'] ? 'success' : 'secondary' ?>">
                                        <?= $t['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (\App\Auth::isAdmin()): ?>
                                    <a href="?page=hr&subpage=teams&action=edit_team&id=<?= $t['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit & Manage Members">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this team?');">
                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                        <input type="hidden" name="action" value="delete_team">
                                        <input type="hidden" name="team_id" value="<?= $t['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($allTeamsData)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    No teams created yet. Click "Add Team" to create your first team.
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-info-circle"></i> About Teams</h5>
            </div>
            <div class="card-body">
                <p class="mb-2">Teams allow you to group employees together for ticket assignment.</p>
                <ul class="mb-0">
                    <li>Assign tickets to entire teams</li>
                    <li>All team members receive notifications</li>
                    <li>Track workload by team</li>
                    <li>Set team leaders for accountability</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php if ($action === 'edit_team' && $editTeam): ?>
<div class="row g-4 mt-2">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-pencil"></i> Edit Team: <?= htmlspecialchars($editTeam['name']) ?></h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="update_team">
                    <input type="hidden" name="team_id" value="<?= $editTeam['id'] ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Team Name *</label>
                        <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($editTeam['name']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="2"><?= htmlspecialchars($editTeam['description'] ?? '') ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Team Leader</label>
                        <select class="form-select" name="leader_id">
                            <option value="">No Leader</option>
                            <?php foreach ($allEmployees as $emp): ?>
                            <option value="<?= $emp['id'] ?>" <?= $editTeam['leader_id'] == $emp['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($emp['name']) ?> (<?= htmlspecialchars($emp['position']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_active" id="teamActive" <?= $editTeam['is_active'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="teamActive">Active</label>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> Save Changes
                    </button>
                    <a href="?page=hr&subpage=teams" class="btn btn-outline-secondary">Cancel</a>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-people-fill"></i> Team Members</h5>
            </div>
            <div class="card-body">
                <form method="POST" class="mb-3">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="add_team_member">
                    <input type="hidden" name="team_id" value="<?= $editTeam['id'] ?>">
                    <div class="input-group">
                        <select class="form-select" name="employee_id" required>
                            <option value="">Select Employee to Add</option>
                            <?php 
                            $memberIds = array_column($teamMembers, 'id');
                            foreach ($allEmployees as $emp): 
                                if (!in_array($emp['id'], $memberIds)):
                            ?>
                            <option value="<?= $emp['id'] ?>">
                                <?= htmlspecialchars($emp['name']) ?> (<?= htmlspecialchars($emp['position']) ?>)
                            </option>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                        </select>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-plus"></i> Add
                        </button>
                    </div>
                </form>
                
                <?php if (empty($teamMembers)): ?>
                <p class="text-muted text-center">No members in this team yet.</p>
                <?php else: ?>
                <ul class="list-group">
                    <?php foreach ($teamMembers as $member): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <strong><?= htmlspecialchars($member['name']) ?></strong>
                            <br><small class="text-muted"><?= htmlspecialchars($member['position']) ?></small>
                        </div>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="action" value="remove_team_member">
                            <input type="hidden" name="team_id" value="<?= $editTeam['id'] ?>">
                            <input type="hidden" name="employee_id" value="<?= $member['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </form>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="modal fade" id="teamModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="teamModalTitle"><i class="bi bi-plus-circle"></i> Add Team</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="create_team">
                    
                    <div class="mb-3">
                        <label class="form-label">Team Name *</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Team Leader</label>
                        <select class="form-select" name="leader_id">
                            <option value="">No Leader</option>
                            <?php foreach ($allEmployees as $emp): ?>
                            <option value="<?= $emp['id'] ?>">
                                <?= htmlspecialchars($emp['name']) ?> (<?= htmlspecialchars($emp['position']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> Create Team
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function resetTeamForm() {
    document.querySelector('#teamModal form').reset();
}
</script>

<?php elseif ($subpage === 'commissions'): ?>

<?php
$ticketCommission = new \App\TicketCommission($db);
$selectedMonth = $_GET['month'] ?? date('Y-m');
$selectedEmployeeId = isset($_GET['employee_id']) && $_GET['employee_id'] !== '' ? (int)$_GET['employee_id'] : null;

$currentUser = \App\Auth::getUser();
$currentEmployee = $employee->getByUserId($currentUser['id']);
$isAdminOrManager = in_array($currentUser['role'], ['admin', 'manager']);

if (!$isAdminOrManager && $currentEmployee) {
    $selectedEmployeeId = $currentEmployee['id'];
}

$allEmployeesList = $isAdminOrManager ? $employee->getAll() : [];
$earningsData = [];
$summaryData = null;
$allEarningsData = [];

if ($selectedEmployeeId) {
    $earningsData = $ticketCommission->getEmployeeEarnings($selectedEmployeeId, $selectedMonth);
    $summaryData = $ticketCommission->getEmployeeEarningsSummary($selectedEmployeeId, $selectedMonth);
} elseif ($isAdminOrManager) {
    $allEarningsData = $ticketCommission->getAllEarnings($selectedMonth);
    $summaryData = $ticketCommission->getAllEarningsSummary($selectedMonth);
}
?>

<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-ticket-perforated text-success"></i> Ticket Commissions</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3 mb-4">
                    <input type="hidden" name="page" value="hr">
                    <input type="hidden" name="subpage" value="commissions">
                    <?php if ($isAdminOrManager): ?>
                    <div class="col-md-4">
                        <label class="form-label">Employee</label>
                        <select class="form-select" name="employee_id">
                            <option value="">Select Employee</option>
                            <?php foreach ($allEmployeesList as $emp): ?>
                            <option value="<?= $emp['id'] ?>" <?= $selectedEmployeeId == $emp['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($emp['name']) ?> (<?= htmlspecialchars($emp['position']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="col-md-3">
                        <label class="form-label">Month</label>
                        <input type="month" class="form-control" name="month" value="<?= htmlspecialchars($selectedMonth) ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i> View
                        </button>
                    </div>
                </form>
                
                <?php if ($summaryData): ?>
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="card bg-success bg-opacity-10">
                            <div class="card-body text-center">
                                <h4 class="text-success mb-0"><?= $summaryData['currency'] ?? 'KES' ?> <?= number_format($summaryData['total_earnings'] ?? 0, 2) ?></h4>
                                <small class="text-muted">Total Earnings</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-primary bg-opacity-10">
                            <div class="card-body text-center">
                                <h4 class="text-primary mb-0"><?= $summaryData['total_tickets'] ?? 0 ?></h4>
                                <small class="text-muted">Tickets Completed</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info bg-opacity-10">
                            <div class="card-body text-center">
                                <h4 class="text-info mb-0"><?= $summaryData['pending'] ?? 0 ?></h4>
                                <small class="text-muted">Pending Payment</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning bg-opacity-10">
                            <div class="card-body text-center">
                                <h4 class="text-warning mb-0"><?= $summaryData['paid'] ?? 0 ?></h4>
                                <small class="text-muted">Paid</small>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($earningsData)): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Ticket #</th>
                                <th>Category</th>
                                <th>Customer</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($earningsData as $earning): ?>
                            <tr>
                                <td><?= date('M d, Y', strtotime($earning['created_at'])) ?></td>
                                <td>
                                    <a href="?page=tickets&action=view&id=<?= $earning['ticket_id'] ?>" class="text-decoration-none">
                                        #<?= $earning['ticket_id'] ?>
                                    </a>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?= htmlspecialchars($earning['category'] ?? '-') ?></span>
                                </td>
                                <td><?= htmlspecialchars($earning['customer_name'] ?? '-') ?></td>
                                <td>
                                    <?php if ($earning['team_id']): ?>
                                    <span class="badge bg-info"><i class="bi bi-people"></i> Team Split</span>
                                    <?php else: ?>
                                    <span class="badge bg-success"><i class="bi bi-person"></i> Individual</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-success fw-bold">
                                    <?= $earning['currency'] ?? 'KES' ?> <?= number_format($earning['earned_amount'], 2) ?>
                                </td>
                                <td>
                                    <?php
                                    $statusColors = ['pending' => 'warning', 'paid' => 'success', 'cancelled' => 'danger'];
                                    $statusColor = $statusColors[$earning['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?= $statusColor ?>"><?= ucfirst($earning['status']) ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php elseif ($selectedEmployeeId): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> No commission earnings found for the selected period.
                </div>
                <?php elseif (!empty($allEarningsData)): ?>
                <h6 class="mb-3"><i class="bi bi-people"></i> All Employees Earnings for <?= date('F Y', strtotime($selectedMonth)) ?></h6>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Employee</th>
                                <th>Ticket</th>
                                <th>Category</th>
                                <th>Customer</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allEarningsData as $earning): ?>
                            <tr>
                                <td><?= date('M d, Y', strtotime($earning['created_at'])) ?></td>
                                <td><strong><?= htmlspecialchars($earning['employee_name']) ?></strong></td>
                                <td>
                                    <a href="?page=tickets&view=<?= $earning['ticket_id'] ?>">
                                        #<?= htmlspecialchars($earning['ticket_number']) ?>
                                    </a>
                                    <br><small class="text-muted"><?= htmlspecialchars($earning['subject'] ?? '') ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?= htmlspecialchars($earning['category'] ?? $earning['ticket_category'] ?? '-') ?></span>
                                </td>
                                <td><?= htmlspecialchars($earning['customer_name'] ?? '-') ?></td>
                                <td>
                                    <?php if ($earning['team_id']): ?>
                                    <span class="badge bg-info"><i class="bi bi-people"></i> <?= htmlspecialchars($earning['team_name'] ?? 'Team') ?></span>
                                    <?php else: ?>
                                    <span class="badge bg-success"><i class="bi bi-person"></i> Individual</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-success fw-bold">
                                    <?= $earning['currency'] ?? 'KES' ?> <?= number_format($earning['earned_amount'], 2) ?>
                                </td>
                                <td>
                                    <?php
                                    $statusColors = ['pending' => 'warning', 'paid' => 'success', 'cancelled' => 'danger'];
                                    $statusColor = $statusColors[$earning['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?= $statusColor ?>"><?= ucfirst($earning['status']) ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php elseif ($isAdminOrManager): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> No commission earnings found for <?= date('F Y', strtotime($selectedMonth)) ?>. Select an employee to filter.
                </div>
                <?php else: ?>
                <div class="alert alert-secondary">
                    <i class="bi bi-info-circle"></i> Select an employee and month to view their commission earnings.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="bi bi-info-circle"></i> About Ticket Commissions</h6>
            </div>
            <div class="card-body">
                <p class="mb-2">Employees earn commissions based on ticket categories:</p>
                <ul class="mb-0">
                    <li>Commissions are calculated when tickets are resolved or closed</li>
                    <li>Team-assigned tickets split the commission equally among members</li>
                    <li>Commission rates are configured in Settings &gt; Ticket Commissions</li>
                    <li>Earnings can be included in payroll processing</li>
                </ul>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="bi bi-gear"></i> Quick Links</h6>
            </div>
            <div class="card-body">
                <?php if ($isAdminOrManager): ?>
                <a href="?page=settings&tab=commissions" class="btn btn-outline-primary me-2 mb-2">
                    <i class="bi bi-sliders"></i> Configure Rates
                </a>
                <a href="?page=hr&subpage=payroll" class="btn btn-outline-success me-2 mb-2">
                    <i class="bi bi-cash-stack"></i> Process Payroll
                </a>
                <?php endif; ?>
                <a href="?page=tickets" class="btn btn-outline-secondary mb-2">
                    <i class="bi bi-ticket"></i> View Tickets
                </a>
            </div>
        </div>
    </div>
</div>

<?php elseif ($subpage === 'advances'): ?>
<?php
$salaryAdvance = new \App\SalaryAdvance(Database::getConnection());
$advanceStats = $salaryAdvance->getStatistics();
$advanceFilter = $_GET['status'] ?? '';
$advances = $salaryAdvance->getAll(['status' => $advanceFilter]);
?>

<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card bg-warning bg-opacity-10 border-warning">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-warning mb-0">Pending</h6>
                        <h3 class="mb-0"><?= $advanceStats['pending_count'] ?? 0 ?></h3>
                    </div>
                    <div class="text-warning fs-1"><i class="bi bi-clock"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-primary bg-opacity-10 border-primary">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-primary mb-0">Active</h6>
                        <h3 class="mb-0"><?= $advanceStats['active_count'] ?? 0 ?></h3>
                    </div>
                    <div class="text-primary fs-1"><i class="bi bi-cash-coin"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success bg-opacity-10 border-success">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-success mb-0">Completed</h6>
                        <h3 class="mb-0"><?= $advanceStats['completed_count'] ?? 0 ?></h3>
                    </div>
                    <div class="text-success fs-1"><i class="bi bi-check-circle"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-danger bg-opacity-10 border-danger">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-danger mb-0">Outstanding</h6>
                        <h3 class="mb-0"><?= $currencySymbol ?> <?= number_format($advanceStats['total_balance'] ?? 0) ?></h3>
                    </div>
                    <div class="text-danger fs-1"><i class="bi bi-wallet2"></i></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-md-6">
                <form method="GET" class="d-flex gap-2">
                    <input type="hidden" name="page" value="hr">
                    <input type="hidden" name="subpage" value="advances">
                    <select class="form-select" name="status">
                        <option value="">All Status</option>
                        <option value="pending" <?= $advanceFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="approved" <?= $advanceFilter === 'approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="disbursed" <?= $advanceFilter === 'disbursed' ? 'selected' : '' ?>>Disbursed</option>
                        <option value="repaying" <?= $advanceFilter === 'repaying' ? 'selected' : '' ?>>Repaying</option>
                        <option value="completed" <?= $advanceFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
                    </select>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-filter"></i> Filter</button>
                </form>
            </div>
            <div class="col-md-6 text-end">
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#newAdvanceModal">
                    <i class="bi bi-plus-circle"></i> New Advance
                </button>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Employee</th>
                        <th>Amount</th>
                        <th>Repayment</th>
                        <th>Balance</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($advances as $adv): ?>
                    <tr>
                        <td><?= date('M d, Y', strtotime($adv['created_at'])) ?></td>
                        <td>
                            <strong><?= htmlspecialchars($adv['employee_name']) ?></strong>
                            <br><small class="text-muted"><?= htmlspecialchars($adv['employee_code']) ?></small>
                        </td>
                        <td class="fw-bold"><?= $currencySymbol ?> <?= number_format($adv['amount'], 2) ?></td>
                        <td>
                            <?= $currencySymbol ?> <?= number_format($adv['repayment_amount'] ?? 0, 2) ?>/<?= ucfirst($adv['repayment_type']) ?>
                            <br><small class="text-muted"><?= $adv['repayment_installments'] ?> installment(s)</small>
                        </td>
                        <td>
                            <span class="text-<?= ($adv['balance'] ?? 0) > 0 ? 'danger' : 'success' ?>">
                                <?= $currencySymbol ?> <?= number_format($adv['balance'] ?? 0, 2) ?>
                            </span>
                        </td>
                        <td>
                            <?php
                            $statusColors = ['pending' => 'warning', 'approved' => 'info', 'disbursed' => 'primary', 'repaying' => 'secondary', 'completed' => 'success', 'rejected' => 'danger', 'cancelled' => 'dark'];
                            ?>
                            <span class="badge bg-<?= $statusColors[$adv['status']] ?? 'secondary' ?>">
                                <?= ucfirst($adv['status']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($adv['status'] === 'pending'): ?>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="action" value="approve_advance">
                                <input type="hidden" name="id" value="<?= $adv['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-success" title="Approve"><i class="bi bi-check"></i></button>
                            </form>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="action" value="reject_advance">
                                <input type="hidden" name="id" value="<?= $adv['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger" title="Reject"><i class="bi bi-x"></i></button>
                            </form>
                            <?php elseif ($adv['status'] === 'approved'): ?>
                            <div class="btn-group">
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                    <input type="hidden" name="action" value="disburse_advance">
                                    <input type="hidden" name="id" value="<?= $adv['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-primary" title="Mark as Disbursed"><i class="bi bi-cash"></i> Manual</button>
                                </form>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                    <input type="hidden" name="action" value="disburse_advance_mpesa">
                                    <input type="hidden" name="id" value="<?= $adv['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-success" title="Send via M-Pesa"><i class="bi bi-phone"></i> M-Pesa</button>
                                </form>
                            </div>
                            <?php elseif (in_array($adv['status'], ['disbursed', 'repaying'])): ?>
                            <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#recordPaymentModal" 
                                data-advance-id="<?= $adv['id'] ?>" data-balance="<?= $adv['balance'] ?>" data-installment="<?= $adv['repayment_amount'] ?>">
                                <i class="bi bi-plus"></i> Payment
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($advances)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No salary advances found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="newAdvanceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="create_advance">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-cash-coin"></i> New Salary Advance</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Employee *</label>
                        <select class="form-select" name="employee_id" required>
                            <option value="">Select Employee</option>
                            <?php foreach ($allEmployees as $emp): ?>
                            <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['name']) ?> (<?= $emp['employee_id'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Amount *</label>
                        <input type="number" step="0.01" class="form-control" name="amount" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Repayment Type</label>
                            <select class="form-select" name="repayment_type">
                                <option value="monthly">Monthly</option>
                                <option value="bi-weekly">Bi-Weekly</option>
                                <option value="weekly">Weekly</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Installments</label>
                            <input type="number" class="form-control" name="repayment_installments" value="1" min="1">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reason</label>
                        <textarea class="form-control" name="reason" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Create Advance</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="recordPaymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="record_advance_payment">
                <input type="hidden" name="advance_id" id="paymentAdvanceId">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Record Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Amount *</label>
                        <input type="number" step="0.01" class="form-control" name="amount" id="paymentAmount" required>
                        <small class="text-muted">Balance: <span id="paymentBalance"></span></small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payment Type</label>
                        <select class="form-select" name="payment_type">
                            <option value="payroll_deduction">Payroll Deduction</option>
                            <option value="cash">Cash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payment Date</label>
                        <input type="date" class="form-control" name="payment_date" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reference Number</label>
                        <input type="text" class="form-control" name="reference_number">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Record Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('recordPaymentModal')?.addEventListener('show.bs.modal', function(event) {
    const btn = event.relatedTarget;
    document.getElementById('paymentAdvanceId').value = btn.dataset.advanceId;
    document.getElementById('paymentAmount').value = btn.dataset.installment;
    document.getElementById('paymentBalance').textContent = '<?= $currencySymbol ?> ' + parseFloat(btn.dataset.balance).toFixed(2);
});
</script>

<?php elseif ($subpage === 'leave'): ?>
<?php
$leaveService = new \App\Leave(Database::getConnection());
$leaveStats = $leaveService->getStatistics();
$leaveTypes = $leaveService->getAllLeaveTypes();
$leaveFilter = $_GET['status'] ?? '';
$leaveRequests = $leaveService->getRequests(['status' => $leaveFilter]);
$leaveTab = $_GET['tab'] ?? 'requests';
?>

<ul class="nav nav-pills mb-4">
    <li class="nav-item">
        <a class="nav-link <?= $leaveTab === 'requests' ? 'active' : '' ?>" href="?page=hr&subpage=leave&tab=requests">
            <i class="bi bi-list-check"></i> Requests
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $leaveTab === 'types' ? 'active' : '' ?>" href="?page=hr&subpage=leave&tab=types">
            <i class="bi bi-tags"></i> Leave Types
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $leaveTab === 'holidays' ? 'active' : '' ?>" href="?page=hr&subpage=leave&tab=holidays">
            <i class="bi bi-calendar-event"></i> Holidays
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $leaveTab === 'balances' ? 'active' : '' ?>" href="?page=hr&subpage=leave&tab=balances">
            <i class="bi bi-pie-chart"></i> Balances
        </a>
    </li>
</ul>

<?php if ($leaveTab === 'requests'): ?>

<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card bg-warning bg-opacity-10 border-warning">
            <div class="card-body text-center">
                <h3 class="mb-0"><?= $leaveStats['pending_requests'] ?? 0 ?></h3>
                <small class="text-warning">Pending</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success bg-opacity-10 border-success">
            <div class="card-body text-center">
                <h3 class="mb-0"><?= $leaveStats['approved_requests'] ?? 0 ?></h3>
                <small class="text-success">Approved</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-danger bg-opacity-10 border-danger">
            <div class="card-body text-center">
                <h3 class="mb-0"><?= $leaveStats['rejected_requests'] ?? 0 ?></h3>
                <small class="text-danger">Rejected</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-primary bg-opacity-10 border-primary">
            <div class="card-body text-center">
                <h3 class="mb-0"><?= number_format($leaveStats['total_days_taken'] ?? 0, 1) ?></h3>
                <small class="text-primary">Days Taken (<?= date('Y') ?>)</small>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-md-6">
                <form method="GET" class="d-flex gap-2">
                    <input type="hidden" name="page" value="hr">
                    <input type="hidden" name="subpage" value="leave">
                    <input type="hidden" name="tab" value="requests">
                    <select class="form-select" name="status">
                        <option value="">All Status</option>
                        <option value="pending" <?= $leaveFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="approved" <?= $leaveFilter === 'approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="rejected" <?= $leaveFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                    </select>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-filter"></i> Filter</button>
                </form>
            </div>
            <div class="col-md-6 text-end">
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#newLeaveRequestModal">
                    <i class="bi bi-plus-circle"></i> New Request
                </button>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Employee</th>
                        <th>Leave Type</th>
                        <th>Period</th>
                        <th>Days</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($leaveRequests as $req): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($req['employee_name']) ?></strong>
                            <br><small class="text-muted"><?= htmlspecialchars($req['employee_code']) ?></small>
                        </td>
                        <td><span class="badge bg-secondary"><?= htmlspecialchars($req['leave_type_name']) ?></span></td>
                        <td>
                            <?= date('M d', strtotime($req['start_date'])) ?> - <?= date('M d, Y', strtotime($req['end_date'])) ?>
                        </td>
                        <td><?= number_format($req['total_days'], 1) ?></td>
                        <td><small><?= htmlspecialchars(substr($req['reason'] ?? '', 0, 50)) ?><?= strlen($req['reason'] ?? '') > 50 ? '...' : '' ?></small></td>
                        <td>
                            <?php
                            $statusColors = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger', 'cancelled' => 'secondary'];
                            ?>
                            <span class="badge bg-<?= $statusColors[$req['status']] ?? 'secondary' ?>">
                                <?= ucfirst($req['status']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($req['status'] === 'pending'): ?>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="action" value="approve_leave">
                                <input type="hidden" name="id" value="<?= $req['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-success" title="Approve"><i class="bi bi-check"></i></button>
                            </form>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="action" value="reject_leave">
                                <input type="hidden" name="id" value="<?= $req['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger" title="Reject"><i class="bi bi-x"></i></button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($leaveRequests)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No leave requests found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="newLeaveRequestModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="create_leave_request">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-calendar-plus"></i> New Leave Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Employee *</label>
                        <select class="form-select" name="employee_id" required>
                            <option value="">Select Employee</option>
                            <?php foreach ($allEmployees as $emp): ?>
                            <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['name']) ?> (<?= $emp['employee_id'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Leave Type *</label>
                        <select class="form-select" name="leave_type_id" required>
                            <option value="">Select Type</option>
                            <?php foreach ($leaveTypes as $lt): ?>
                            <option value="<?= $lt['id'] ?>"><?= htmlspecialchars($lt['name']) ?> (<?= $lt['days_per_year'] ?> days/year)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Start Date *</label>
                            <input type="date" class="form-control" name="start_date" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">End Date *</label>
                            <input type="date" class="form-control" name="end_date" required>
                        </div>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" name="is_half_day" id="isHalfDay">
                        <label class="form-check-label" for="isHalfDay">Half Day</label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reason</label>
                        <textarea class="form-control" name="reason" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Submit Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php elseif ($leaveTab === 'types'): ?>

<div class="card mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="bi bi-tags"></i> Leave Types</h6>
        <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#newLeaveTypeModal">
            <i class="bi bi-plus"></i> Add Type
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Code</th>
                        <th>Days/Year</th>
                        <th>Accrual</th>
                        <th>Paid</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($leaveTypes as $lt): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($lt['name']) ?></strong></td>
                        <td><code><?= htmlspecialchars($lt['code']) ?></code></td>
                        <td><?= number_format($lt['days_per_year'], 1) ?></td>
                        <td><?= ucfirst($lt['accrual_type']) ?></td>
                        <td><?= $lt['is_paid'] ? '<i class="bi bi-check-circle text-success"></i>' : '<i class="bi bi-x-circle text-danger"></i>' ?></td>
                        <td><?= $lt['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="newLeaveTypeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="create_leave_type">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> New Leave Type</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Name *</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Code *</label>
                            <input type="text" class="form-control" name="code" required placeholder="e.g., ANNUAL">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="2"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Days Per Year</label>
                            <input type="number" step="0.5" class="form-control" name="days_per_year" value="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Accrual Type</label>
                            <select class="form-select" name="accrual_type">
                                <option value="monthly">Monthly (Trickle)</option>
                                <option value="annual">Annual (Upfront)</option>
                                <option value="none">None</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Max Carryover Days</label>
                            <input type="number" step="0.5" class="form-control" name="max_carryover_days" value="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="form-check mt-4">
                                <input type="checkbox" class="form-check-input" name="is_paid" id="isPaid" checked>
                                <label class="form-check-label" for="isPaid">Paid Leave</label>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="is_active" id="isActive" checked>
                                <label class="form-check-label" for="isActive">Active</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Create Type</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php elseif ($leaveTab === 'holidays'): ?>
<?php $holidays = $leaveService->getPublicHolidays(); ?>

<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="bi bi-calendar-event"></i> Public Holidays (<?= date('Y') ?>)</h6>
        <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addHolidayModal">
            <i class="bi bi-plus"></i> Add Holiday
        </button>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <?php foreach ($holidays as $h): ?>
            <div class="col-md-4">
                <div class="card bg-light">
                    <div class="card-body">
                        <h6 class="mb-1"><?= htmlspecialchars($h['name']) ?></h6>
                        <p class="mb-0 text-muted"><?= date('l, M d, Y', strtotime($h['date'])) ?></p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($holidays)): ?>
            <div class="col-12 text-center text-muted py-4">
                <i class="bi bi-calendar-x" style="font-size: 2rem;"></i>
                <p class="mt-2 mb-0">No holidays configured for <?= date('Y') ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="addHolidayModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="add_holiday">
                <div class="modal-header">
                    <h5 class="modal-title">Add Holiday</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Date *</label>
                        <input type="date" class="form-control" name="date" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Holiday Name *</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success">Add</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php elseif ($leaveTab === 'balances'): ?>

<div class="card">
    <div class="card-header bg-white">
        <h6 class="mb-0"><i class="bi bi-pie-chart"></i> Employee Leave Balances (<?= date('Y') ?>)</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Employee</th>
                        <?php foreach ($leaveTypes as $lt): ?>
                        <th class="text-center"><?= htmlspecialchars($lt['code']) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allEmployees as $emp): ?>
                    <?php $balances = $leaveService->getEmployeeBalance($emp['id']); ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($emp['name']) ?></strong></td>
                        <?php foreach ($leaveTypes as $lt): ?>
                        <?php 
                        $bal = array_filter($balances, fn($b) => $b['leave_type_id'] == $lt['id']);
                        $bal = reset($bal);
                        ?>
                        <td class="text-center">
                            <?php if ($bal): ?>
                            <span class="badge bg-<?= ($bal['available_days'] ?? 0) > 0 ? 'success' : 'secondary' ?>">
                                <?= number_format($bal['available_days'] ?? 0, 1) ?>
                            </span>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php endif; ?>

<?php elseif ($subpage === 'announcements'): ?>
<?php include __DIR__ . '/announcements.php'; ?>

<?php else: ?>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <input type="hidden" name="page" value="hr">
            <div class="col-md-6">
                <input type="text" class="form-control" name="search" placeholder="Search employees..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-3">
                <select class="form-select" name="department">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $dept): ?>
                    <option value="<?= $dept['id'] ?>" <?= ($_GET['department'] ?? '') == $dept['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($dept['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-search"></i> Search
                </button>
                <a href="?page=hr" class="btn btn-outline-secondary">Clear</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Employee ID</th>
                        <th>Name</th>
                        <th>Position</th>
                        <th>Department</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $deptFilter = isset($_GET['department']) ? (int)$_GET['department'] : null;
                    $employees = $employee->getAll($search, $deptFilter);
                    foreach ($employees as $emp):
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($emp['employee_id']) ?></strong></td>
                        <td><?= htmlspecialchars($emp['name']) ?></td>
                        <td><?= htmlspecialchars($emp['position']) ?></td>
                        <td><?= htmlspecialchars($emp['department_name'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($emp['phone']) ?></td>
                        <td>
                            <span class="badge bg-<?= $emp['employment_status'] === 'active' ? 'success' : ($emp['employment_status'] === 'on_leave' ? 'warning' : ($emp['employment_status'] === 'terminated' ? 'danger' : 'secondary')) ?>">
                                <?= ucfirst(str_replace('_', ' ', $emp['employment_status'])) ?>
                            </span>
                        </td>
                        <td>
                            <a href="?page=hr&action=view_employee&id=<?= $emp['id'] ?>" class="btn btn-sm btn-outline-primary" title="View">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="?page=hr&action=edit_employee&id=<?= $emp['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <?php if (\App\Auth::isAdmin() && $emp['user_id']): ?>
                            <button type="button" class="btn btn-sm btn-outline-warning" title="Change Password" 
                                    data-bs-toggle="modal" data-bs-target="#changePasswordModal"
                                    onclick="setPasswordChangeEmployee(<?= $emp['id'] ?>, '<?= htmlspecialchars(addslashes($emp['name'])) ?>')">
                                <i class="bi bi-key"></i>
                            </button>
                            <?php endif; ?>
                            <?php if (\App\Auth::isAdmin()): ?>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this employee?')">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="action" value="delete_employee">
                                <input type="hidden" name="id" value="<?= $emp['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($employees)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            No employees found. <a href="?page=hr&action=create_employee">Add your first employee</a>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const userSelect = document.getElementById('userAccountSelect');
    const roleAndAccessFields = document.getElementById('roleAndAccessFields');
    const newAccountEmailField = document.getElementById('newAccountEmailField');
    const newAccountPasswordField = document.getElementById('newAccountPasswordField');
    const roleSelect = document.getElementById('roleSelect');
    
    function updateAccessFields() {
        if (!userSelect || !roleAndAccessFields) return;
        
        const value = userSelect.value;
        
        if (value === '') {
            roleAndAccessFields.style.display = 'none';
            if (roleSelect) roleSelect.required = false;
        } else if (value === 'create_new') {
            roleAndAccessFields.style.display = 'block';
            if (newAccountEmailField) newAccountEmailField.style.display = 'block';
            if (newAccountPasswordField) newAccountPasswordField.style.display = 'block';
            if (roleSelect) roleSelect.required = true;
        } else {
            roleAndAccessFields.style.display = 'block';
            if (newAccountEmailField) newAccountEmailField.style.display = 'none';
            if (newAccountPasswordField) newAccountPasswordField.style.display = 'none';
            if (roleSelect) roleSelect.required = true;
        }
    }
    
    if (userSelect) {
        userSelect.addEventListener('change', updateAccessFields);
        updateAccessFields();
    }
    
    const payrollModal = document.getElementById('payrollModal');
    if (payrollModal) {
        const employeeSelect = payrollModal.querySelector('[name="employee_id"]');
        const payPeriodStart = payrollModal.querySelector('[name="pay_period_start"]');
        const baseSalaryInput = payrollModal.querySelector('[name="base_salary"]');
        const includeLateCheckbox = document.getElementById('includeLateDeductions');
        const previewBox = document.getElementById('lateDeductionPreview');
        const previewInfo = document.getElementById('lateDeductionInfo');
        
        function updateLateDeductionPreview() {
            if (!employeeSelect || !payPeriodStart || !previewBox || !previewInfo) return;
            
            const employeeId = employeeSelect.value;
            const periodDate = payPeriodStart.value;
            
            if (!employeeId || !periodDate) {
                previewBox.classList.add('d-none');
                return;
            }
            
            const month = periodDate.substring(0, 7);
            
            fetch(`?page=api&action=late_deductions&employee_id=${employeeId}&month=${month}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        previewBox.classList.add('d-none');
                        return;
                    }
                    
                    if (data.total_late_days > 0) {
                        previewBox.classList.remove('d-none');
                        previewInfo.innerHTML = `
                            <strong>${data.total_late_days}</strong> late arrival(s) totaling 
                            <strong>${data.total_late_minutes}</strong> minutes<br>
                            Estimated deduction: <strong class="text-danger">${data.currency} ${parseFloat(data.total_deduction).toFixed(2)}</strong>
                        `;
                    } else {
                        previewBox.classList.remove('d-none');
                        previewInfo.innerHTML = '<span class="text-success"><i class="bi bi-check-circle"></i> No late arrivals for this period</span>';
                    }
                })
                .catch(err => {
                    previewBox.classList.add('d-none');
                });
        }
        
        if (employeeSelect) {
            employeeSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                const salary = selectedOption.dataset.salary || 0;
                if (baseSalaryInput) baseSalaryInput.value = salary;
                updateLateDeductionPreview();
            });
        }
        
        if (payPeriodStart) {
            payPeriodStart.addEventListener('change', updateLateDeductionPreview);
        }
        
        if (includeLateCheckbox) {
            includeLateCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    updateLateDeductionPreview();
                    if (previewBox) previewBox.classList.remove('d-none');
                } else {
                    if (previewBox) previewBox.classList.add('d-none');
                }
            });
        }
    }
});

function setPasswordChangeEmployee(employeeId, employeeName) {
    document.getElementById('passwordChangeEmployeeId').value = employeeId;
    document.getElementById('passwordChangeEmployeeName').textContent = employeeName;
    document.getElementById('newPasswordInput').value = '';
    document.getElementById('confirmPasswordInput').value = '';
}
</script>

<!-- Change Password Modal -->
<div class="modal fade" id="changePasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-key"></i> Change Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="change_employee_password">
                    <input type="hidden" name="employee_id" id="passwordChangeEmployeeId">
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> 
                        Changing password for: <strong id="passwordChangeEmployeeName"></strong>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" id="newPasswordInput" class="form-control" required minlength="6">
                        <small class="text-muted">Minimum 6 characters</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Confirm Password</label>
                        <input type="password" name="confirm_password" id="confirmPasswordInput" class="form-control" required minlength="6">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning"><i class="bi bi-key"></i> Change Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php endif; ?>
