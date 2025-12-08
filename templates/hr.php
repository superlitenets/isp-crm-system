<?php
$employeeData = null;
$departmentData = null;
$linkedUserData = null;
$subpage = $_GET['subpage'] ?? 'employees';
$selectedDate = $_GET['date'] ?? date('Y-m-d');
$selectedMonth = $_GET['month'] ?? date('Y-m');

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
    
    <div class="col-md-6">
        <div class="card border-primary">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-fingerprint"></i> Biometric Registration</h5>
            </div>
            <div class="card-body">
                <div id="biometricSyncStatus" class="alert d-none mb-3"></div>
                <p class="mb-2"><strong>Biometric ID:</strong> <?= htmlspecialchars($employeeData['biometric_id'] ?? $employeeData['id']) ?></p>
                <p class="mb-3"><strong>Card Number:</strong> <?= htmlspecialchars($employeeData['card_number'] ?? 'Not set') ?></p>
                
                <?php
                $bioDevices = [];
                try {
                    $bioDb = Database::getConnection();
                    $bioDevices = $bioDb->query("SELECT id, name, device_type FROM biometric_devices WHERE is_active = true AND device_type = 'hikvision'")->fetchAll();
                } catch (\Exception $e) {
                    // Table may not exist
                }
                ?>
                
                <?php if (!empty($bioDevices)): ?>
                <div class="mb-3">
                    <label class="form-label">Select Device:</label>
                    <select id="biometricDeviceSelect" class="form-select">
                        <?php foreach ($bioDevices as $dev): ?>
                        <option value="<?= $dev['id'] ?>"><?= htmlspecialchars($dev['name']) ?> (<?= ucfirst($dev['device_type']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <button type="button" class="btn btn-primary" onclick="syncEmployeeToBiometric(<?= $employeeData['id'] ?>)">
                        <i class="bi bi-upload"></i> Register to Device
                    </button>
                    <button type="button" class="btn btn-outline-info" onclick="viewDeviceUsers()">
                        <i class="bi bi-people"></i> View Device Users
                    </button>
                    <button type="button" class="btn btn-outline-danger" onclick="removeFromBiometric(<?= $employeeData['id'] ?>)">
                        <i class="bi bi-trash"></i> Remove from Device
                    </button>
                </div>
                <?php else: ?>
                <p class="text-muted mb-0">No Hikvision biometric devices configured. Add devices in Settings.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function syncEmployeeToBiometric(employeeId) {
    var deviceId = document.getElementById('biometricDeviceSelect').value;
    var statusDiv = document.getElementById('biometricSyncStatus');
    statusDiv.className = 'alert alert-info mb-3';
    statusDiv.textContent = 'Registering employee to biometric device...';
    statusDiv.classList.remove('d-none');
    
    fetch('/biometric-api.php?action=sync-employees-to-device', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            device_id: parseInt(deviceId),
            employee_ids: [employeeId]
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.success_count > 0) {
            statusDiv.className = 'alert alert-success mb-3';
            statusDiv.textContent = 'Employee registered to biometric device successfully!';
        } else if (data.results && data.results[0]) {
            statusDiv.className = 'alert alert-warning mb-3';
            statusDiv.textContent = data.results[0].message || 'Registration completed with notes';
        } else {
            statusDiv.className = 'alert alert-danger mb-3';
            statusDiv.textContent = 'Failed: ' + (data.error || 'Unknown error');
        }
    })
    .catch(e => {
        statusDiv.className = 'alert alert-danger mb-3';
        statusDiv.textContent = 'Error: ' + e.message;
    });
}

function removeFromBiometric(employeeId) {
    if (!confirm('Remove this employee from the biometric device?')) return;
    
    var deviceId = document.getElementById('biometricDeviceSelect').value;
    var statusDiv = document.getElementById('biometricSyncStatus');
    var bioId = '<?= $employeeData['biometric_id'] ?? $employeeData['id'] ?>';
    
    statusDiv.className = 'alert alert-info mb-3';
    statusDiv.textContent = 'Removing employee from device...';
    statusDiv.classList.remove('d-none');
    
    fetch('/biometric-api.php?action=delete-device-user', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            device_id: parseInt(deviceId),
            employee_no: bioId
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            statusDiv.className = 'alert alert-success mb-3';
            statusDiv.textContent = 'Employee removed from biometric device.';
        } else {
            statusDiv.className = 'alert alert-danger mb-3';
            statusDiv.textContent = 'Failed: ' + (data.error || 'Unknown error');
        }
    })
    .catch(e => {
        statusDiv.className = 'alert alert-danger mb-3';
        statusDiv.textContent = 'Error: ' + e.message;
    });
}

function viewDeviceUsers() {
    var deviceId = document.getElementById('biometricDeviceSelect').value;
    var statusDiv = document.getElementById('biometricSyncStatus');
    
    statusDiv.className = 'alert alert-info mb-3';
    statusDiv.textContent = 'Fetching users from device...';
    statusDiv.classList.remove('d-none');
    
    fetch('/biometric-api.php?action=fetch-device-users&device_id=' + deviceId)
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            statusDiv.classList.add('d-none');
            showDeviceUsersModal(data);
        } else {
            statusDiv.className = 'alert alert-danger mb-3';
            statusDiv.textContent = 'Failed: ' + (data.error || 'Unknown error');
        }
    })
    .catch(e => {
        statusDiv.className = 'alert alert-danger mb-3';
        statusDiv.textContent = 'Error: ' + e.message;
    });
}

function showDeviceUsersModal(data) {
    var existingModal = document.getElementById('deviceUsersModal');
    if (existingModal) existingModal.remove();
    
    var modalHtml = '<div class="modal fade" id="deviceUsersModal" tabindex="-1">' +
        '<div class="modal-dialog modal-lg">' +
        '<div class="modal-content">' +
        '<div class="modal-header">' +
        '<h5 class="modal-title"><i class="bi bi-people"></i> Device Users - ' + data.device_name + '</h5>' +
        '<button type="button" class="btn-close" data-bs-dismiss="modal"></button>' +
        '</div>' +
        '<div class="modal-body">' +
        '<p class="text-muted">Total users on device: <strong>' + data.user_count + '</strong></p>' +
        '<div class="table-responsive"><table class="table table-sm table-striped">' +
        '<thead><tr><th>Device User ID</th><th>Name</th><th>Linked Employee</th><th>Actions</th></tr></thead>' +
        '<tbody>';
    
    if (data.users && data.users.length > 0) {
        data.users.forEach(function(user) {
            var linkedText = user.linked_employee ? 
                '<span class="text-success"><i class="bi bi-check-circle"></i> ' + user.linked_employee.name + ' (ID: ' + user.linked_employee.id + ')</span>' : 
                '<span class="text-warning"><i class="bi bi-exclamation-triangle"></i> Not linked</span>';
            modalHtml += '<tr>' +
                '<td><strong>' + user.device_user_id + '</strong></td>' +
                '<td>' + (user.name || '-') + '</td>' +
                '<td>' + linkedText + '</td>' +
                '<td><button class="btn btn-sm btn-outline-primary" onclick="linkDeviceUser(\'' + user.device_user_id + '\')"><i class="bi bi-link"></i> Link</button></td>' +
                '</tr>';
        });
    } else {
        modalHtml += '<tr><td colspan="4" class="text-center text-muted">No users found on device</td></tr>';
    }
    
    modalHtml += '</tbody></table></div></div>' +
        '<div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>' +
        '</div></div></div>';
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    var modal = new bootstrap.Modal(document.getElementById('deviceUsersModal'));
    modal.show();
}

function linkDeviceUser(deviceUserId) {
    var empId = prompt('Enter Employee ID to link with device user ' + deviceUserId + ':');
    if (!empId) return;
    
    var db = window.Database || null;
    fetch('/biometric-api.php?action=link-device-user', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            device_user_id: deviceUserId,
            employee_id: parseInt(empId)
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('Device user ' + deviceUserId + ' linked to employee ID ' + empId);
            bootstrap.Modal.getInstance(document.getElementById('deviceUsersModal')).hide();
        } else {
            alert('Failed: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(e => alert('Error: ' + e.message));
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
$allEmployees = (new \App\Employee($dbConn))->getAll();

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
</script>
