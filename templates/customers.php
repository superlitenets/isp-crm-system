<?php
$customerData = null;
if ($action === 'edit' && $id) {
    $customerData = $customer->find($id);
}
if ($action === 'view' && $id) {
    $customerData = $customer->find($id);
}
?>

<?php if ($action === 'create' || $action === 'edit'): ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-person-<?= $action === 'create' ? 'plus' : 'gear' ?>"></i> <?= $action === 'create' ? 'Add Customer' : 'Edit Customer' ?></h2>
    <a href="?page=customers" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="<?= $action === 'create' ? 'create_customer' : 'update_customer' ?>">
            <?php if ($action === 'edit'): ?>
            <input type="hidden" name="id" value="<?= $customerData['id'] ?>">
            <?php endif; ?>
            
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Full Name *</label>
                    <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($customerData['name'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Phone Number *</label>
                    <input type="tel" class="form-control" name="phone" value="<?= htmlspecialchars($customerData['phone'] ?? '') ?>" placeholder="+1234567890" required>
                    <small class="text-muted">Include country code for SMS</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($customerData['email'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Service Plan *</label>
                    <select class="form-select" name="service_plan" required>
                        <?php foreach ($servicePlans as $key => $label): ?>
                        <option value="<?= $key ?>" <?= ($customerData['service_plan'] ?? '') === $key ? 'selected' : '' ?>>
                            <?= $label ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Installation Address *</label>
                    <textarea class="form-control" name="address" rows="2" required><?= htmlspecialchars($customerData['address'] ?? '') ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Connection Status</label>
                    <select class="form-select" name="connection_status">
                        <?php foreach ($connectionStatuses as $key => $label): ?>
                        <option value="<?= $key ?>" <?= ($customerData['connection_status'] ?? 'active') === $key ? 'selected' : '' ?>>
                            <?= $label ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Installation Date</label>
                    <input type="date" class="form-control" name="installation_date" value="<?= htmlspecialchars($customerData['installation_date'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Notes</label>
                    <textarea class="form-control" name="notes" rows="2"><?= htmlspecialchars($customerData['notes'] ?? '') ?></textarea>
                </div>
            </div>
            
            <div class="mt-4">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg"></i> <?= $action === 'create' ? 'Create Customer' : 'Update Customer' ?>
                </button>
                <a href="?page=customers" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php elseif ($action === 'view' && $customerData): ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-person"></i> Customer Details</h2>
    <div>
        <a href="?page=customers" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back
        </a>
        <a href="?page=customers&action=edit&id=<?= $customerData['id'] ?>" class="btn btn-primary">
            <i class="bi bi-pencil"></i> Edit
        </a>
    </div>
</div>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">Customer Information</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <th width="40%">Account Number</th>
                        <td><strong><?= htmlspecialchars($customerData['account_number']) ?></strong></td>
                    </tr>
                    <tr>
                        <th>Name</th>
                        <td><?= htmlspecialchars($customerData['name']) ?></td>
                    </tr>
                    <tr>
                        <th>Phone</th>
                        <td><?= htmlspecialchars($customerData['phone']) ?></td>
                    </tr>
                    <tr>
                        <th>Email</th>
                        <td><?= htmlspecialchars($customerData['email'] ?? 'N/A') ?></td>
                    </tr>
                    <tr>
                        <th>Address</th>
                        <td><?= nl2br(htmlspecialchars($customerData['address'])) ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">Service Information</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <th width="40%">Service Plan</th>
                        <td><?= htmlspecialchars($servicePlans[$customerData['service_plan']] ?? $customerData['service_plan']) ?></td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td>
                            <span class="badge bg-<?= $customerData['connection_status'] === 'active' ? 'success' : ($customerData['connection_status'] === 'suspended' ? 'warning' : 'danger') ?>">
                                <?= ucfirst($customerData['connection_status']) ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Installation Date</th>
                        <td><?= $customerData['installation_date'] ? date('M j, Y', strtotime($customerData['installation_date'])) : 'N/A' ?></td>
                    </tr>
                    <tr>
                        <th>Customer Since</th>
                        <td><?= date('M j, Y', strtotime($customerData['created_at'])) ?></td>
                    </tr>
                    <tr>
                        <th>Notes</th>
                        <td><?= nl2br(htmlspecialchars($customerData['notes'] ?? 'No notes')) ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Tickets</h5>
        <a href="?page=tickets&action=create&customer_id=<?= $customerData['id'] ?>" class="btn btn-sm btn-primary">
            <i class="bi bi-plus-circle"></i> New Ticket
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Ticket #</th>
                        <th>Subject</th>
                        <th>Category</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $db = Database::getConnection();
                    $stmt = $db->prepare("SELECT * FROM tickets WHERE customer_id = ? ORDER BY created_at DESC");
                    $stmt->execute([$customerData['id']]);
                    $customerTickets = $stmt->fetchAll();
                    foreach ($customerTickets as $t):
                    ?>
                    <tr>
                        <td><a href="?page=tickets&action=view&id=<?= $t['id'] ?>"><?= htmlspecialchars($t['ticket_number']) ?></a></td>
                        <td><?= htmlspecialchars($t['subject']) ?></td>
                        <td><?= htmlspecialchars($categories[$t['category']] ?? $t['category']) ?></td>
                        <td><span class="badge badge-priority-<?= $t['priority'] ?>"><?= ucfirst($t['priority']) ?></span></td>
                        <td><span class="badge badge-status-<?= $t['status'] ?>"><?= ucfirst(str_replace('_', ' ', $t['status'])) ?></span></td>
                        <td><?= date('M j, Y', strtotime($t['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($customerTickets)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No tickets for this customer</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php else: ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-people"></i> Customers</h2>
    <a href="?page=customers&action=create" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> Add Customer
    </a>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <input type="hidden" name="page" value="customers">
            <div class="col-md-8">
                <input type="text" class="form-control" name="search" placeholder="Search by name, account, phone, or email..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-search"></i> Search
                </button>
                <a href="?page=customers" class="btn btn-outline-secondary">Clear</a>
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
                        <th>Account #</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Service Plan</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $customerUserId = null;
                    if (!\App\Auth::can('customers.view_all') && !\App\Auth::isAdmin()) {
                        $customerUserId = $_SESSION['user_id'];
                    }
                    $customers = $customer->getAll($search, 50, 0, $customerUserId);
                    foreach ($customers as $c):
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($c['account_number']) ?></strong></td>
                        <td><?= htmlspecialchars($c['name']) ?></td>
                        <td><?= htmlspecialchars($c['phone']) ?></td>
                        <td><?= htmlspecialchars($servicePlans[$c['service_plan']] ?? $c['service_plan']) ?></td>
                        <td>
                            <span class="badge bg-<?= $c['connection_status'] === 'active' ? 'success' : ($c['connection_status'] === 'suspended' ? 'warning' : 'secondary') ?>">
                                <?= ucfirst($c['connection_status']) ?>
                            </span>
                        </td>
                        <td>
                            <a href="?page=customers&action=view&id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary" title="View">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="?page=customers&action=edit&id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <a href="?page=tickets&action=create&customer_id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-success" title="New Ticket">
                                <i class="bi bi-ticket"></i>
                            </a>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this customer? All associated tickets will also be deleted.')">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="action" value="delete_customer">
                                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($customers)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            No customers found. <a href="?page=customers&action=create">Add your first customer</a>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>
