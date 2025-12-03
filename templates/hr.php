<?php
$employeeData = null;
$departmentData = null;
$subpage = $_GET['subpage'] ?? 'employees';
$selectedDate = $_GET['date'] ?? date('Y-m-d');
$selectedMonth = $_GET['month'] ?? date('Y-m');

if ($action === 'edit_employee' && $id) {
    $employeeData = $employee->find($id);
}
if ($action === 'view_employee' && $id) {
    $employeeData = $employee->find($id);
}
if ($action === 'edit_department' && $id) {
    $departmentData = $employee->getDepartment($id);
}

$departments = $employee->getAllDepartments();
$employmentStatuses = $employee->getEmploymentStatuses();
$hrStats = $employee->getStats();
$allEmployees = $employee->getAll();
$attendanceStatuses = $employee->getAttendanceStatuses();
$payrollStatuses = $employee->getPayrollStatuses();
$paymentMethods = $employee->getPaymentMethods();
$performanceStatuses = $employee->getPerformanceStatuses();
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
        <form method="POST">
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
                    <label class="form-label">Phone Number *</label>
                    <input type="tel" class="form-control" name="phone" value="<?= htmlspecialchars($employeeData['phone'] ?? '') ?>" placeholder="+1234567890" required>
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
                <div class="col-md-4">
                    <label class="form-label">System Access</label>
                    <select class="form-select" name="user_id" id="userAccountSelect">
                        <option value="">No System Access</option>
                        <option value="create_new" <?= ($action === 'create_employee') ? '' : 'style="display:none"' ?>>+ Create New Login Account</option>
                        <?php foreach ($users as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= ($employeeData['user_id'] ?? '') == $u['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['email']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Required for ticket assignment</small>
                </div>
                
                <div class="col-12" id="newAccountFields" style="display: none;">
                    <div class="card bg-light">
                        <div class="card-body">
                            <h6 class="card-title"><i class="bi bi-person-plus"></i> New Login Account</h6>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Login Email *</label>
                                    <input type="email" class="form-control" name="new_user_email" placeholder="user@company.com">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Password *</label>
                                    <input type="password" class="form-control" name="new_user_password" placeholder="Min 6 characters">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Role</label>
                                    <select class="form-select" name="new_user_role">
                                        <option value="technician">Technician</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label">Address</label>
                    <textarea class="form-control" name="address" rows="2"><?= htmlspecialchars($employeeData['address'] ?? '') ?></textarea>
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
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-person-badge"></i> Employee Details</h2>
    <div>
        <a href="?page=hr&subpage=employees" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back
        </a>
        <a href="?page=hr&action=edit_employee&id=<?= $employeeData['id'] ?>" class="btn btn-primary">
            <i class="bi bi-pencil"></i> Edit
        </a>
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
                        <td><?= $employeeData['salary'] ? '$' . number_format($employeeData['salary'], 2) : 'N/A' ?></td>
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
    <a href="?page=hr&action=create_employee" class="btn btn-primary">
        <i class="bi bi-person-plus"></i> Add Employee
    </a>
    <?php elseif ($subpage === 'departments'): ?>
    <a href="?page=hr&action=create_department" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> Add Department
    </a>
    <?php endif; ?>
</div>

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
                <h4>$<?= number_format($payrollStats['total_paid'] ?? 0, 2) ?></h4>
                <small>Total Paid</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-warning text-dark">
            <div class="card-body">
                <h4>$<?= number_format($payrollStats['total_pending'] ?? 0, 2) ?></h4>
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
                        <td>$<?= number_format($pay['base_salary'], 2) ?></td>
                        <td class="text-success">+$<?= number_format($pay['bonuses'] + $pay['overtime_pay'], 2) ?></td>
                        <td class="text-danger">-$<?= number_format($pay['deductions'] + $pay['tax'], 2) ?></td>
                        <td><strong>$<?= number_format($pay['net_pay'], 2) ?></strong></td>
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
                                    <?= htmlspecialchars($emp['name']) ?> (<?= $emp['employee_id'] ?>) - $<?= number_format($emp['salary'] ?? 0, 2) ?>
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
                            <label class="form-label">Deductions</label>
                            <input type="number" step="0.01" class="form-control" name="deductions" value="0">
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

<?php elseif ($subpage === 'performance'): ?>

<?php $performanceReviews = $employee->getAllPerformanceReviews(); ?>
<?php $performanceStats = $employee->getPerformanceStats(); ?>

<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h4><?= $performanceStats['completed'] ?? 0 ?></h4>
                <small>Completed Reviews</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-warning text-dark">
            <div class="card-body">
                <h4><?= $performanceStats['pending'] ?? 0 ?></h4>
                <small>Pending Reviews</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-info text-white">
            <div class="card-body">
                <h4><?= number_format($performanceStats['avg_rating'] ?? 0, 1) ?>/5</h4>
                <small>Average Rating</small>
            </div>
        </div>
    </div>
</div>

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
                        <th>Reviewer</th>
                        <th>Overall Rating</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($performanceReviews as $review): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($review['employee_name']) ?></strong>
                            <br><small class="text-muted"><?= htmlspecialchars($review['department_name'] ?? 'No Department') ?></small>
                        </td>
                        <td>
                            <?= date('M j, Y', strtotime($review['review_period_start'])) ?> - <?= date('M j, Y', strtotime($review['review_period_end'])) ?>
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
                        <td colspan="6" class="text-center text-muted py-4">
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
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($lateArrivals)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
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
                        <td><?= date('g:i A', strtotime($late['expected_time'])) ?></td>
                        <td>
                            <span class="text-danger"><?= date('g:i A', strtotime($late['actual_time'])) ?></span>
                        </td>
                        <td>
                            <span class="badge bg-warning text-dark">
                                <?= $late['late_minutes'] ?> min
                            </span>
                        </td>
                        <td>
                            <?php if ($late['deduction_amount'] > 0): ?>
                            <span class="text-danger"><?= $late['currency'] ?? 'KES' ?> <?= number_format($late['deduction_amount'], 2) ?></span>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
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
                        $employeeSummary[$empId]['deductions'] += $late['deduction_amount'];
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

<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const userSelect = document.getElementById('userAccountSelect');
    const newAccountFields = document.getElementById('newAccountFields');
    
    if (userSelect && newAccountFields) {
        userSelect.addEventListener('change', function() {
            if (this.value === 'create_new') {
                newAccountFields.style.display = 'block';
            } else {
                newAccountFields.style.display = 'none';
            }
        });
    }
});
</script>
