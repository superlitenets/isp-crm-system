<?php
$employeeData = null;
$departmentData = null;
$subpage = $_GET['subpage'] ?? 'employees';

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
                    <label class="form-label">Link to User Account</label>
                    <select class="form-select" name="user_id">
                        <option value="">Not Linked</option>
                        <?php foreach ($users as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= ($employeeData['user_id'] ?? '') == $u['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['email']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
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

<?php elseif ($subpage === 'departments'): ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-building"></i> Departments</h2>
    <a href="?page=hr&action=create_department" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> Add Department
    </a>
</div>

<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link" href="?page=hr&subpage=employees">Employees</a>
    </li>
    <li class="nav-item">
        <a class="nav-link active" href="?page=hr&subpage=departments">Departments</a>
    </li>
</ul>

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

<?php else: ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-people-fill"></i> Human Resources</h2>
    <a href="?page=hr&action=create_employee" class="btn btn-primary">
        <i class="bi bi-person-plus"></i> Add Employee
    </a>
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
        <a class="nav-link active" href="?page=hr&subpage=employees">Employees</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="?page=hr&subpage=departments">Departments</a>
    </li>
</ul>

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
