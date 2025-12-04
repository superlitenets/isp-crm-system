<?php
$ticketData = null;
$comments = [];
if (($action === 'edit' || $action === 'view') && $id) {
    $ticketData = $ticket->find($id);
    $comments = $ticket->getComments($id);
}

$preselectedCustomer = null;
if (isset($_GET['customer_id'])) {
    $preselectedCustomer = $customer->find((int)$_GET['customer_id']);
}
?>

<?php if ($action === 'create' || $action === 'edit'): ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-ticket-<?= $action === 'create' ? 'perforated' : 'detailed' ?>"></i> <?= $action === 'create' ? 'Create Ticket' : 'Edit Ticket' ?></h2>
    <a href="?page=tickets" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="<?= $action === 'create' ? 'create_ticket' : 'update_ticket' ?>">
            <?php if ($action === 'edit'): ?>
            <input type="hidden" name="id" value="<?= $ticketData['id'] ?>">
            <?php endif; ?>
            
            <div class="row g-3">
                <?php if ($action === 'create'): ?>
                <div class="col-md-6">
                    <label class="form-label">Customer *</label>
                    <select class="form-select" name="customer_id" required>
                        <option value="">Select Customer</option>
                        <?php
                        $allCustomers = $customer->getAll();
                        foreach ($allCustomers as $c):
                        ?>
                        <option value="<?= $c['id'] ?>" <?= ($preselectedCustomer && $preselectedCustomer['id'] == $c['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['account_number']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <div class="col-md-6">
                    <label class="form-label">Assign To Team</label>
                    <?php $teams = $ticket->getAllTeams(); ?>
                    <select class="form-select" name="team_id">
                        <option value="">No Team</option>
                        <?php foreach ($teams as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= ($ticketData['team_id'] ?? '') == $t['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">All team members will be notified</small>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Assign To Individual</label>
                    <select class="form-select" name="assigned_to">
                        <option value="">Unassigned</option>
                        <?php foreach ($users as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= ($ticketData['assigned_to'] ?? '') == $u['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['name']) ?> (<?= ucfirst($u['role']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Individual will receive SMS notification</small>
                </div>
                
                <div class="col-12">
                    <label class="form-label">Subject *</label>
                    <input type="text" class="form-control" name="subject" value="<?= htmlspecialchars($ticketData['subject'] ?? '') ?>" required>
                </div>
                
                <div class="col-12">
                    <label class="form-label">Description *</label>
                    <textarea class="form-control" name="description" rows="4" required><?= htmlspecialchars($ticketData['description'] ?? '') ?></textarea>
                </div>
                
                <div class="col-md-4">
                    <label class="form-label">Category *</label>
                    <select class="form-select" name="category" required>
                        <?php foreach ($categories as $key => $label): ?>
                        <option value="<?= $key ?>" <?= ($ticketData['category'] ?? '') === $key ? 'selected' : '' ?>>
                            <?= $label ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-4">
                    <label class="form-label">Priority *</label>
                    <select class="form-select" name="priority" required>
                        <?php foreach ($priorities as $key => $label): ?>
                        <option value="<?= $key ?>" <?= ($ticketData['priority'] ?? 'medium') === $key ? 'selected' : '' ?>>
                            <?= $label ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <?php if ($action === 'edit'): ?>
                <div class="col-md-4">
                    <label class="form-label">Status *</label>
                    <select class="form-select" name="status" required>
                        <?php foreach ($ticketStatuses as $key => $label): ?>
                        <option value="<?= $key ?>" <?= ($ticketData['status'] ?? 'open') === $key ? 'selected' : '' ?>>
                            <?= $label ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Customer will receive SMS on status change</small>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="mt-4">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg"></i> <?= $action === 'create' ? 'Create Ticket' : 'Update Ticket' ?>
                </button>
                <a href="?page=tickets" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php elseif ($action === 'view' && $ticketData): ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-ticket"></i> Ticket <?= htmlspecialchars($ticketData['ticket_number']) ?></h2>
    <div>
        <a href="?page=tickets" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back
        </a>
        <a href="?page=tickets&action=edit&id=<?= $ticketData['id'] ?>" class="btn btn-primary">
            <i class="bi bi-pencil"></i> Edit
        </a>
    </div>
</div>

<div class="row g-4">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><?= htmlspecialchars($ticketData['subject']) ?></h5>
            </div>
            <div class="card-body">
                <p><?= nl2br(htmlspecialchars($ticketData['description'])) ?></p>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">Comments & Activity</h5>
            </div>
            <div class="card-body">
                <?php if (empty($comments)): ?>
                <p class="text-muted">No comments yet</p>
                <?php endif; ?>
                
                <?php foreach ($comments as $c): ?>
                <div class="border-start border-3 border-<?= $c['is_internal'] ? 'warning' : 'primary' ?> ps-3 mb-3">
                    <div class="d-flex justify-content-between">
                        <strong><?= htmlspecialchars($c['user_name'] ?? 'System') ?></strong>
                        <small class="text-muted"><?= date('M j, Y g:i A', strtotime($c['created_at'])) ?></small>
                    </div>
                    <?php if ($c['is_internal']): ?>
                    <span class="badge bg-warning text-dark mb-1">Internal Note</span>
                    <?php endif; ?>
                    <p class="mb-0"><?= nl2br(htmlspecialchars($c['comment'])) ?></p>
                </div>
                <?php endforeach; ?>
                
                <hr>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="add_comment">
                    <input type="hidden" name="ticket_id" value="<?= $ticketData['id'] ?>">
                    <div class="mb-3">
                        <textarea class="form-control" name="comment" rows="3" placeholder="Add a comment..." required></textarea>
                    </div>
                    <div class="d-flex justify-content-between">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_internal" id="isInternal">
                            <label class="form-check-label" for="isInternal">
                                Internal note (not visible to customer)
                            </label>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-send"></i> Add Comment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0">Ticket Details</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless table-sm">
                    <tr>
                        <th>Status</th>
                        <td><span class="badge badge-status-<?= $ticketData['status'] ?>"><?= ucfirst(str_replace('_', ' ', $ticketData['status'])) ?></span></td>
                    </tr>
                    <tr>
                        <th>Priority</th>
                        <td><span class="badge badge-priority-<?= $ticketData['priority'] ?>"><?= ucfirst($ticketData['priority']) ?></span></td>
                    </tr>
                    <tr>
                        <th>Category</th>
                        <td><?= htmlspecialchars($categories[$ticketData['category']] ?? $ticketData['category']) ?></td>
                    </tr>
                    <tr>
                        <th>Assigned Team</th>
                        <td><?= htmlspecialchars($ticketData['team_name'] ?? 'No Team') ?></td>
                    </tr>
                    <tr>
                        <th>Assigned To</th>
                        <td><?= htmlspecialchars($ticketData['assigned_name'] ?? 'Unassigned') ?></td>
                    </tr>
                    <tr>
                        <th>Created</th>
                        <td><?= date('M j, Y g:i A', strtotime($ticketData['created_at'])) ?></td>
                    </tr>
                    <?php if ($ticketData['resolved_at']): ?>
                    <tr>
                        <th>Resolved</th>
                        <td><?= date('M j, Y g:i A', strtotime($ticketData['resolved_at'])) ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0">Customer</h5>
            </div>
            <div class="card-body">
                <p class="mb-1"><strong><?= htmlspecialchars($ticketData['customer_name']) ?></strong></p>
                <p class="mb-1"><small class="text-muted"><?= htmlspecialchars($ticketData['account_number']) ?></small></p>
                <p class="mb-2"><i class="bi bi-telephone"></i> <?= htmlspecialchars($ticketData['customer_phone']) ?></p>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="?page=customers&action=view&id=<?= $ticketData['customer_id'] ?>" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-person"></i> View
                    </a>
                    <?php if (!empty($ticketData['customer_phone'])): ?>
                    <a href="tel:<?= htmlspecialchars($ticketData['customer_phone']) ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-telephone"></i> Call
                    </a>
                    <?php 
                    $waCustomer = new \App\WhatsApp();
                    if ($waCustomer->isEnabled()):
                        $customerMsg = "Hi " . $ticketData['customer_name'] . ",\n\nRegarding ticket #" . $ticketData['ticket_number'] . ":\n";
                    ?>
                    <a href="<?= htmlspecialchars($waCustomer->generateWebLink($ticketData['customer_phone'], $customerMsg)) ?>" 
                       target="_blank" class="btn btn-sm btn-success">
                        <i class="bi bi-whatsapp"></i> WhatsApp
                    </a>
                    <?php endif; ?>
                    <?php else: ?>
                    <span class="text-muted small">No phone number</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <?php if ($ticketData['assigned_to']): ?>
        <div class="card mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0">Assigned Technician</h5>
            </div>
            <div class="card-body">
                <p class="mb-1"><strong><?= htmlspecialchars($ticketData['assigned_name']) ?></strong></p>
                <?php if (!empty($ticketData['assigned_phone'])): ?>
                <p class="mb-2"><i class="bi bi-telephone"></i> <?= htmlspecialchars($ticketData['assigned_phone']) ?></p>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="tel:<?= htmlspecialchars($ticketData['assigned_phone']) ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-telephone"></i> Call
                    </a>
                    <?php 
                    if (!isset($waCustomer)) $waCustomer = new \App\WhatsApp();
                    if ($waCustomer->isEnabled()):
                        $techMsg = "Hi " . $ticketData['assigned_name'] . ",\n\nRegarding ticket #" . $ticketData['ticket_number'] . " for " . $ticketData['customer_name'] . ":\n";
                    ?>
                    <a href="<?= htmlspecialchars($waCustomer->generateWebLink($ticketData['assigned_phone'], $techMsg)) ?>" 
                       target="_blank" class="btn btn-sm btn-success">
                        <i class="bi bi-whatsapp"></i> WhatsApp
                    </a>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <p class="text-muted small mb-0">No phone number on file</p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">SMS Log</h5>
            </div>
            <div class="card-body">
                <?php
                $db = Database::getConnection();
                $stmt = $db->prepare("SELECT * FROM sms_logs WHERE ticket_id = ? ORDER BY sent_at DESC LIMIT 5");
                $stmt->execute([$ticketData['id']]);
                $smsLogs = $stmt->fetchAll();
                ?>
                <?php if (empty($smsLogs)): ?>
                <p class="text-muted mb-0">No SMS sent for this ticket</p>
                <?php endif; ?>
                <?php foreach ($smsLogs as $log): ?>
                <div class="mb-2 pb-2 border-bottom">
                    <small class="text-muted"><?= date('M j, g:i A', strtotime($log['sent_at'])) ?></small><br>
                    <span class="badge bg-<?= $log['status'] === 'sent' ? 'success' : 'danger' ?>"><?= ucfirst($log['status']) ?></span>
                    <small><?= ucfirst($log['recipient_type']) ?>: <?= htmlspecialchars($log['recipient_phone']) ?></small>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-ticket"></i> Tickets</h2>
    <a href="?page=tickets&action=create" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> Create Ticket
    </a>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <input type="hidden" name="page" value="tickets">
            <div class="col-md-4">
                <input type="text" class="form-control" name="search" placeholder="Search tickets..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-2">
                <select class="form-select" name="status">
                    <option value="">All Status</option>
                    <?php foreach ($ticketStatuses as $key => $label): ?>
                    <option value="<?= $key ?>" <?= $statusFilter === $key ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" name="priority">
                    <option value="">All Priority</option>
                    <?php foreach ($priorities as $key => $label): ?>
                    <option value="<?= $key ?>" <?= $priorityFilter === $key ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-search"></i> Filter
                </button>
                <a href="?page=tickets" class="btn btn-outline-secondary">Clear</a>
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
                        <th>Ticket #</th>
                        <th>Customer</th>
                        <th>Subject</th>
                        <th>Category</th>
                        <th>Team</th>
                        <th>Assigned To</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $filters = [];
                    if ($statusFilter) $filters['status'] = $statusFilter;
                    if ($priorityFilter) $filters['priority'] = $priorityFilter;
                    if ($search) $filters['search'] = $search;
                    $tickets = $ticket->getAll($filters);
                    foreach ($tickets as $t):
                    ?>
                    <tr>
                        <td><a href="?page=tickets&action=view&id=<?= $t['id'] ?>"><?= htmlspecialchars($t['ticket_number']) ?></a></td>
                        <td><?= htmlspecialchars($t['customer_name'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars(substr($t['subject'], 0, 30)) ?><?= strlen($t['subject']) > 30 ? '...' : '' ?></td>
                        <td><?= htmlspecialchars($categories[$t['category']] ?? $t['category']) ?></td>
                        <td><span class="badge bg-info text-dark"><?= htmlspecialchars($t['team_name'] ?? '-') ?></span></td>
                        <td><?= htmlspecialchars($t['assigned_name'] ?? '-') ?></td>
                        <td><span class="badge badge-priority-<?= $t['priority'] ?>"><?= ucfirst($t['priority']) ?></span></td>
                        <td><span class="badge badge-status-<?= $t['status'] ?>"><?= ucfirst(str_replace('_', ' ', $t['status'])) ?></span></td>
                        <td><?= date('M j', strtotime($t['created_at'])) ?></td>
                        <td>
                            <a href="?page=tickets&action=view&id=<?= $t['id'] ?>" class="btn btn-sm btn-outline-primary" title="View">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="?page=tickets&action=edit&id=<?= $t['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($tickets)): ?>
                    <tr>
                        <td colspan="10" class="text-center text-muted py-4">
                            No tickets found. <a href="?page=tickets&action=create">Create your first ticket</a>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>
