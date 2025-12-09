<?php
$branchModel = new \App\Branch();
$action = $_GET['action'] ?? 'list';
$branchId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$branch = $branchId ? $branchModel->get($branchId) : null;

try {
    $branches = $branchModel->getAll();
} catch (\Throwable $e) {
    $branches = [];
    error_log("Branch list error: " . $e->getMessage());
}

$managers = [];
try {
    $stmt = $db->query("SELECT id, name FROM users WHERE role IN ('admin', 'manager') ORDER BY name");
    $managers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {
    error_log("Manager list error: " . $e->getMessage());
}

$employees = [];
try {
    $stmt = $db->query("SELECT id, name, department_id FROM employees WHERE status = 'active' ORDER BY name");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {
    error_log("Employee list error: " . $e->getMessage());
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-building"></i> Branches</h2>
    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#branchModal">
        <i class="bi bi-plus-circle"></i> Add Branch
    </button>
</div>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success alert-dismissible fade show">
    <?= htmlspecialchars($_GET['success']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <?= htmlspecialchars($_GET['error']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h5 class="card-title">Total Branches</h5>
                <h2><?= count($branches) ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h5 class="card-title">Active</h5>
                <h2><?= count(array_filter($branches, fn($b) => $b['is_active'])) ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <h5 class="card-title">Total Employees</h5>
                <h2><?= array_sum(array_column($branches, 'employee_count')) ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-dark">
            <div class="card-body">
                <h5 class="card-title">Total Teams</h5>
                <h2><?= array_sum(array_column($branches, 'team_count')) ?></h2>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <?php if (empty($branches)): ?>
        <div class="text-center py-5 text-muted">
            <i class="bi bi-building" style="font-size: 3rem;"></i>
            <p class="mt-2">No branches found</p>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#branchModal">
                <i class="bi bi-plus-circle"></i> Create First Branch
            </button>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Code</th>
                        <th>Manager</th>
                        <th>Phone</th>
                        <th>Employees</th>
                        <th>Teams</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($branches as $b): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($b['name']) ?></strong>
                            <?php if ($b['address']): ?>
                            <br><small class="text-muted"><?= htmlspecialchars($b['address']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge bg-secondary"><?= htmlspecialchars($b['code'] ?? '-') ?></span></td>
                        <td><?= htmlspecialchars($b['manager_name'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($b['phone'] ?? '-') ?></td>
                        <td><span class="badge bg-info"><?= $b['employee_count'] ?? 0 ?></span></td>
                        <td><span class="badge bg-warning text-dark"><?= $b['team_count'] ?? 0 ?></span></td>
                        <td>
                            <?php if ($b['is_active']): ?>
                            <span class="badge bg-success">Active</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-primary" 
                                        onclick="editBranch(<?= htmlspecialchars(json_encode($b)) ?>)"
                                        title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button type="button" class="btn btn-outline-info"
                                        onclick="manageEmployees(<?= $b['id'] ?>, '<?= htmlspecialchars($b['name']) ?>')"
                                        title="Manage Employees">
                                    <i class="bi bi-people"></i>
                                </button>
                                <form method="POST" style="display: inline;" 
                                      onsubmit="return confirm('Are you sure you want to delete this branch?')">
                                    <input type="hidden" name="csrf_token" value="<?= \App\Auth::generateToken() ?>">
                                    <input type="hidden" name="action" value="delete_branch">
                                    <input type="hidden" name="branch_id" value="<?= $b['id'] ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
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

<div class="modal fade" id="branchModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= \App\Auth::generateToken() ?>">
                <input type="hidden" name="action" id="formAction" value="create_branch">
                <input type="hidden" name="branch_id" id="branchId" value="">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="branchModalLabel">Add Branch</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Branch Name *</label>
                        <input type="text" class="form-control" name="name" id="branchName" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Branch Code</label>
                            <input type="text" class="form-control" name="code" id="branchCode" placeholder="e.g., HQ, BR01">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" class="form-control" name="phone" id="branchPhone">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea class="form-control" name="address" id="branchAddress" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" id="branchEmail">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">WhatsApp Group ID</label>
                        <input type="text" class="form-control" name="whatsapp_group" id="branchWhatsapp" 
                               placeholder="For daily summary notifications">
                        <small class="text-muted">Enter the WhatsApp group ID for daily summaries</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Manager</label>
                        <select class="form-select" name="manager_id" id="branchManager">
                            <option value="">Select Manager</option>
                            <?php foreach ($managers as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" id="branchActive" value="1" checked>
                        <label class="form-check-label" for="branchActive">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Branch</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="employeesModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= \App\Auth::generateToken() ?>">
                <input type="hidden" name="action" value="update_branch_employees">
                <input type="hidden" name="branch_id" id="empBranchId" value="">
                
                <div class="modal-header">
                    <h5 class="modal-title">Manage Employees - <span id="empBranchName"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted">Select employees to assign to this branch:</p>
                    <div class="row" id="employeeCheckboxes">
                        <?php foreach ($employees as $emp): ?>
                        <div class="col-md-6 mb-2">
                            <div class="form-check">
                                <input class="form-check-input emp-checkbox" type="checkbox" 
                                       name="employee_ids[]" value="<?= $emp['id'] ?>" 
                                       id="emp<?= $emp['id'] ?>">
                                <label class="form-check-label" for="emp<?= $emp['id'] ?>">
                                    <?= htmlspecialchars($emp['name']) ?>
                                </label>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Assignments</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editBranch(branch) {
    document.getElementById('formAction').value = 'update_branch';
    document.getElementById('branchModalLabel').textContent = 'Edit Branch';
    document.getElementById('branchId').value = branch.id;
    document.getElementById('branchName').value = branch.name || '';
    document.getElementById('branchCode').value = branch.code || '';
    document.getElementById('branchPhone').value = branch.phone || '';
    document.getElementById('branchAddress').value = branch.address || '';
    document.getElementById('branchEmail').value = branch.email || '';
    document.getElementById('branchWhatsapp').value = branch.whatsapp_group || '';
    document.getElementById('branchManager').value = branch.manager_id || '';
    document.getElementById('branchActive').checked = branch.is_active;
    new bootstrap.Modal(document.getElementById('branchModal')).show();
}

function manageEmployees(branchId, branchName) {
    document.getElementById('empBranchId').value = branchId;
    document.getElementById('empBranchName').textContent = branchName;
    
    document.querySelectorAll('.emp-checkbox').forEach(cb => cb.checked = false);
    
    fetch('?page=api&action=branch_employees&id=' + branchId)
        .then(r => r.json())
        .then(data => {
            if (data.employees) {
                data.employees.forEach(empId => {
                    const cb = document.getElementById('emp' + empId);
                    if (cb) cb.checked = true;
                });
            }
        })
        .catch(() => {});
    
    new bootstrap.Modal(document.getElementById('employeesModal')).show();
}

document.getElementById('branchModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('formAction').value = 'create_branch';
    document.getElementById('branchModalLabel').textContent = 'Add Branch';
    document.getElementById('branchId').value = '';
    document.getElementById('branchName').value = '';
    document.getElementById('branchCode').value = '';
    document.getElementById('branchPhone').value = '';
    document.getElementById('branchAddress').value = '';
    document.getElementById('branchEmail').value = '';
    document.getElementById('branchWhatsapp').value = '';
    document.getElementById('branchManager').value = '';
    document.getElementById('branchActive').checked = true;
});
</script>
