<?php
$complaintModel = new \App\Complaint();
$csrfToken = \App\Auth::generateToken();

$statusFilter = $_GET['status'] ?? '';
$categoryFilter = $_GET['category'] ?? '';
$searchQuery = $_GET['search'] ?? '';

$filters = [];
if ($statusFilter) $filters['status'] = $statusFilter;
if ($categoryFilter) $filters['category'] = $categoryFilter;
if ($searchQuery) $filters['search'] = $searchQuery;
if (!\App\Auth::can('complaints.view_all') && !\App\Auth::isAdmin()) {
    $filters['user_id'] = $_SESSION['user_id'];
}

$complaints = $complaintModel->getAll($filters);
$stats = $complaintModel->getStats();

$viewAction = $_GET['action'] ?? 'list';
$viewId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$viewComplaint = null;

if ($viewAction === 'view' && $viewId) {
    $viewComplaint = $complaintModel->getById($viewId);
}

$users = $db->query("SELECT id, name FROM users WHERE role IN ('admin', 'technician', 'administrator') ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$teams = $db->query("SELECT id, name FROM teams ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

$categoryLabels = [
    'connectivity' => 'Connectivity Issue',
    'speed' => 'Speed Issue',
    'billing' => 'Billing Problem',
    'equipment' => 'Equipment Issue',
    'service' => 'Service Quality',
    'other' => 'Other'
];

$statusColors = [
    'pending' => 'warning',
    'approved' => 'success',
    'rejected' => 'danger',
    'converted' => 'info'
];

$priorityColors = [
    'low' => 'success',
    'medium' => 'warning',
    'high' => 'orange',
    'critical' => 'danger'
];
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-exclamation-triangle"></i> Complaints Management</h2>
    </div>

    <div class="row mb-4">
        <div class="col-md-2">
            <div class="card bg-light">
                <div class="card-body text-center">
                    <h3 class="mb-0"><?= $stats['total'] ?></h3>
                    <small class="text-muted">Total</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-warning text-dark">
                <div class="card-body text-center">
                    <h3 class="mb-0"><?= $stats['pending'] ?></h3>
                    <small>Pending</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h3 class="mb-0"><?= $stats['approved'] ?></h3>
                    <small>Approved</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-danger text-white">
                <div class="card-body text-center">
                    <h3 class="mb-0"><?= $stats['rejected'] ?></h3>
                    <small>Rejected</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h3 class="mb-0"><?= $stats['converted'] ?></h3>
                    <small>Converted</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h3 class="mb-0"><?= $stats['today'] ?></h3>
                    <small>Today</small>
                </div>
            </div>
        </div>
    </div>

    <?php if ($viewComplaint): ?>
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="bi bi-file-text"></i> Complaint Details: <?= htmlspecialchars($viewComplaint['complaint_number']) ?>
            </h5>
            <a href="?page=complaints" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Back to List
            </a>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <table class="table table-borderless">
                        <tr>
                            <th width="150">Status:</th>
                            <td>
                                <span class="badge bg-<?= $statusColors[$viewComplaint['status']] ?? 'secondary' ?>">
                                    <?= ucfirst($viewComplaint['status']) ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th>Priority:</th>
                            <td>
                                <span class="badge bg-<?= $priorityColors[$viewComplaint['priority']] ?? 'secondary' ?>">
                                    <?= ucfirst($viewComplaint['priority']) ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th>Category:</th>
                            <td><?= $categoryLabels[$viewComplaint['category']] ?? ucfirst($viewComplaint['category']) ?></td>
                        </tr>
                        <tr>
                            <th>Submitted:</th>
                            <td><?= date('M d, Y H:i', strtotime($viewComplaint['created_at'])) ?></td>
                        </tr>
                        <?php if ($viewComplaint['reviewed_at']): ?>
                        <tr>
                            <th>Reviewed:</th>
                            <td><?= date('M d, Y H:i', strtotime($viewComplaint['reviewed_at'])) ?> by <?= htmlspecialchars($viewComplaint['reviewer_name'] ?? 'Unknown') ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($viewComplaint['converted_ticket_number']): ?>
                        <tr>
                            <th>Ticket:</th>
                            <td>
                                <a href="?page=tickets&action=view&id=<?= $viewComplaint['converted_ticket_id'] ?>">
                                    <?= htmlspecialchars($viewComplaint['converted_ticket_number']) ?>
                                </a>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-borderless">
                        <tr>
                            <th width="150">Customer:</th>
                            <td><?= htmlspecialchars($viewComplaint['customer_name']) ?></td>
                        </tr>
                        <tr>
                            <th>Phone:</th>
                            <td>
                                <a href="tel:<?= htmlspecialchars($viewComplaint['customer_phone']) ?>">
                                    <?= htmlspecialchars($viewComplaint['customer_phone']) ?>
                                </a>
                            </td>
                        </tr>
                        <?php if ($viewComplaint['customer_email']): ?>
                        <tr>
                            <th>Email:</th>
                            <td>
                                <a href="mailto:<?= htmlspecialchars($viewComplaint['customer_email']) ?>">
                                    <?= htmlspecialchars($viewComplaint['customer_email']) ?>
                                </a>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($viewComplaint['customer_location']): ?>
                        <tr>
                            <th>Location:</th>
                            <td><?= htmlspecialchars($viewComplaint['customer_location']) ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>

            <hr>
            
            <h6><i class="bi bi-chat-left-text"></i> Subject</h6>
            <p class="lead"><?= htmlspecialchars($viewComplaint['subject']) ?></p>
            
            <h6><i class="bi bi-card-text"></i> Description</h6>
            <div class="bg-light p-3 rounded">
                <?= nl2br(htmlspecialchars($viewComplaint['description'])) ?>
            </div>

            <?php if ($viewComplaint['review_notes']): ?>
            <hr>
            <h6><i class="bi bi-sticky"></i> Review Notes</h6>
            <div class="bg-light p-3 rounded border-start border-4 border-<?= $statusColors[$viewComplaint['status']] ?? 'secondary' ?>">
                <?= nl2br(htmlspecialchars($viewComplaint['review_notes'])) ?>
            </div>
            <?php endif; ?>

            <hr>
            
            <div class="d-flex gap-2 flex-wrap">
                <?php if ($viewComplaint['status'] === 'pending'): ?>
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#approveModal">
                    <i class="bi bi-check-circle"></i> Approve
                </button>
                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">
                    <i class="bi bi-x-circle"></i> Reject
                </button>
                <button type="button" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#priorityModal">
                    <i class="bi bi-arrow-up-circle"></i> Change Priority
                </button>
                <?php endif; ?>
                
                <?php if ($viewComplaint['status'] === 'approved'): ?>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#convertModal">
                    <i class="bi bi-ticket"></i> Convert to Ticket
                </button>
                <?php endif; ?>
                
                <?php if ($viewComplaint['status'] !== 'converted'): ?>
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                    <i class="bi bi-trash"></i> Delete
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php 
    $waComplaint = new \App\WhatsApp();
    if ($waComplaint->isEnabled() && !empty($viewComplaint['customer_phone'])): 
        $waSettings = new \App\Settings();
        $complaintReplacements = [
            '{customer_name}' => $viewComplaint['customer_name'],
            '{complaint_number}' => $viewComplaint['complaint_number'],
            '{category}' => $categoryLabels[$viewComplaint['category']] ?? ucfirst($viewComplaint['category']),
            '{status}' => ucfirst($viewComplaint['status'])
        ];
        
        $complaintReceivedMsg = str_replace(array_keys($complaintReplacements), array_values($complaintReplacements),
            $waSettings->get('wa_template_complaint_received', "Hi {customer_name},\n\nWe have received your complaint (Ref: {complaint_number}).\n\nCategory: {category}\n\nOur team will review and respond within 24 hours.\n\nThank you for your feedback."));
        $complaintReviewMsg = str_replace(array_keys($complaintReplacements), array_values($complaintReplacements),
            $waSettings->get('wa_template_complaint_review', "Hi {customer_name},\n\nRegarding your complaint {complaint_number}:\n\nWe are currently reviewing your issue and will update you soon.\n\nThank you for your patience."));
        $complaintApprovedMsg = str_replace(array_keys($complaintReplacements), array_values($complaintReplacements),
            $waSettings->get('wa_template_complaint_approved', "Hi {customer_name},\n\nYour complaint {complaint_number} has been approved and a support ticket will be created.\n\nOur team will contact you shortly to resolve the issue.\n\nThank you!"));
        $complaintRejectedMsg = str_replace(array_keys($complaintReplacements), array_values($complaintReplacements),
            $waSettings->get('wa_template_complaint_rejected', "Hi {customer_name},\n\nRegarding your complaint {complaint_number}:\n\nAfter careful review, we were unable to proceed with this complaint.\n\nIf you have any questions, please contact our support team.\n\nThank you."));
    ?>
    <div class="card mt-4 border-success">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0"><i class="bi bi-whatsapp"></i> WhatsApp Notification</h5>
        </div>
        <div class="card-body">
            <p class="small text-muted mb-3">Send complaint updates via WhatsApp Web:</p>
            
            <div class="d-flex flex-wrap gap-2 mb-3">
                <a href="<?= htmlspecialchars($waComplaint->generateWebLink($viewComplaint['customer_phone'], $complaintReceivedMsg)) ?>" 
                   target="_blank" class="btn btn-outline-success btn-sm"
                   onclick="logComplaintWhatsApp(<?= $viewComplaint['id'] ?>, 'received')">
                    <i class="bi bi-envelope-check"></i> Complaint Received
                </a>
                <a href="<?= htmlspecialchars($waComplaint->generateWebLink($viewComplaint['customer_phone'], $complaintReviewMsg)) ?>" 
                   target="_blank" class="btn btn-outline-info btn-sm"
                   onclick="logComplaintWhatsApp(<?= $viewComplaint['id'] ?>, 'review')">
                    <i class="bi bi-hourglass-split"></i> Under Review
                </a>
                <a href="<?= htmlspecialchars($waComplaint->generateWebLink($viewComplaint['customer_phone'], $complaintApprovedMsg)) ?>" 
                   target="_blank" class="btn btn-outline-primary btn-sm"
                   onclick="logComplaintWhatsApp(<?= $viewComplaint['id'] ?>, 'approved')">
                    <i class="bi bi-check-circle"></i> Approved
                </a>
                <a href="<?= htmlspecialchars($waComplaint->generateWebLink($viewComplaint['customer_phone'], $complaintRejectedMsg)) ?>" 
                   target="_blank" class="btn btn-outline-secondary btn-sm"
                   onclick="logComplaintWhatsApp(<?= $viewComplaint['id'] ?>, 'rejected')">
                    <i class="bi bi-x-circle"></i> Rejected
                </a>
            </div>
            
            <div class="input-group input-group-sm">
                <textarea class="form-control" id="customComplaintWaMessage" rows="2" placeholder="Type a custom message..."><?= "Hi {$viewComplaint['customer_name']},\n\nRegarding your complaint {$viewComplaint['complaint_number']}:\n\n" ?></textarea>
                <button type="button" class="btn btn-success" onclick="sendComplaintWhatsApp()">
                    <i class="bi bi-whatsapp"></i> Send
                </button>
            </div>
        </div>
    </div>
    
    <script>
    function sendComplaintWhatsApp() {
        var message = document.getElementById('customComplaintWaMessage').value;
        var phone = '<?= $waComplaint->formatPhone($viewComplaint['customer_phone']) ?>';
        var url = 'https://web.whatsapp.com/send?phone=' + phone + '&text=' + encodeURIComponent(message);
        window.open(url, '_blank');
        logComplaintWhatsApp(<?= $viewComplaint['id'] ?>, 'custom');
    }
    
    function logComplaintWhatsApp(complaintId, messageType) {
        fetch('?page=api&action=log_whatsapp', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({complaint_id: complaintId, message_type: messageType, phone: '<?= $waComplaint->formatPhone($viewComplaint['customer_phone']) ?>'})
        }).catch(function(e) { console.log('WhatsApp log error:', e); });
    }
    </script>
    <?php endif; ?>

    <div class="modal fade" id="approveModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="?page=complaints&action=approve&id=<?= $viewComplaint['id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title"><i class="bi bi-check-circle"></i> Approve Complaint</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>You are about to approve this complaint. After approval, it can be converted to a ticket.</p>
                        <div class="mb-3">
                            <label class="form-label">Notes (optional)</label>
                            <textarea name="review_notes" class="form-control" rows="3" placeholder="Add any notes about the approval..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Approve</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="?page=complaints&action=reject&id=<?= $viewComplaint['id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title"><i class="bi bi-x-circle"></i> Reject Complaint</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>You are about to reject this complaint. Please provide a reason.</p>
                        <div class="mb-3">
                            <label class="form-label">Rejection Reason <span class="text-danger">*</span></label>
                            <textarea name="review_notes" class="form-control" rows="3" required placeholder="Explain why this complaint is being rejected..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Reject</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="convertModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="?page=complaints&action=convert&id=<?= $viewComplaint['id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title"><i class="bi bi-ticket"></i> Convert to Ticket</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Convert this complaint to a support ticket. The customer will be created if not exists.</p>
                        <div class="mb-3">
                            <label class="form-label">Assign to User (optional)</label>
                            <select name="assign_to" class="form-select">
                                <option value="">-- Unassigned --</option>
                                <?php foreach ($users as $user): ?>
                                <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if (!empty($teams)): ?>
                        <div class="mb-3">
                            <label class="form-label">Assign to Team (optional)</label>
                            <select name="team_id" class="form-select">
                                <option value="">-- No Team --</option>
                                <?php foreach ($teams as $team): ?>
                                <option value="<?= $team['id'] ?>"><?= htmlspecialchars($team['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Convert to Ticket</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="priorityModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="?page=complaints&action=update_priority&id=<?= $viewComplaint['id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-arrow-up-circle"></i> Change Priority</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Priority</label>
                            <select name="priority" class="form-select">
                                <option value="low" <?= $viewComplaint['priority'] === 'low' ? 'selected' : '' ?>>Low</option>
                                <option value="medium" <?= $viewComplaint['priority'] === 'medium' ? 'selected' : '' ?>>Medium</option>
                                <option value="high" <?= $viewComplaint['priority'] === 'high' ? 'selected' : '' ?>>High</option>
                                <option value="critical" <?= $viewComplaint['priority'] === 'critical' ? 'selected' : '' ?>>Critical</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="?page=complaints&action=delete&id=<?= $viewComplaint['id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title"><i class="bi bi-trash"></i> Delete Complaint</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete this complaint? This action cannot be undone.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php else: ?>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <input type="hidden" name="page" value="complaints">
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control" placeholder="Search..." value="<?= htmlspecialchars($searchQuery) ?>">
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                        <option value="converted" <?= $statusFilter === 'converted' ? 'selected' : '' ?>>Converted</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="category" class="form-select">
                        <option value="">All Categories</option>
                        <?php foreach ($categoryLabels as $key => $label): ?>
                        <option value="<?= $key ?>" <?= $categoryFilter === $key ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i> Filter
                    </button>
                </div>
                <div class="col-md-2">
                    <a href="?page=complaints" class="btn btn-outline-secondary w-100">
                        <i class="bi bi-x-circle"></i> Clear
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <?php if (empty($complaints)): ?>
            <div class="text-center py-5">
                <i class="bi bi-inbox display-1 text-muted"></i>
                <p class="text-muted mt-3">No complaints found</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Complaint #</th>
                            <th>Customer</th>
                            <th>Subject</th>
                            <th>Category</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($complaints as $complaint): ?>
                        <tr class="<?= $complaint['status'] === 'pending' ? 'table-warning' : '' ?>">
                            <td>
                                <a href="?page=complaints&action=view&id=<?= $complaint['id'] ?>">
                                    <strong><?= htmlspecialchars($complaint['complaint_number']) ?></strong>
                                </a>
                            </td>
                            <td>
                                <div><?= htmlspecialchars($complaint['customer_name']) ?></div>
                                <small class="text-muted"><?= htmlspecialchars($complaint['customer_phone']) ?></small>
                            </td>
                            <td><?= htmlspecialchars(substr($complaint['subject'], 0, 40)) ?><?= strlen($complaint['subject']) > 40 ? '...' : '' ?></td>
                            <td><?= $categoryLabels[$complaint['category']] ?? ucfirst($complaint['category']) ?></td>
                            <td>
                                <span class="badge bg-<?= $priorityColors[$complaint['priority']] ?? 'secondary' ?>">
                                    <?= ucfirst($complaint['priority']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-<?= $statusColors[$complaint['status']] ?? 'secondary' ?>">
                                    <?= ucfirst($complaint['status']) ?>
                                </span>
                                <?php if ($complaint['converted_ticket_number']): ?>
                                <br><small><a href="?page=tickets&action=view&id=<?= $complaint['converted_ticket_id'] ?>"><?= $complaint['converted_ticket_number'] ?></a></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small><?= date('M d, Y', strtotime($complaint['created_at'])) ?></small>
                                <br>
                                <small class="text-muted"><?= date('H:i', strtotime($complaint['created_at'])) ?></small>
                            </td>
                            <td>
                                <a href="?page=complaints&action=view&id=<?= $complaint['id'] ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye"></i> View
                                </a>
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
</div>

<style>
.bg-orange {
    background-color: #fd7e14 !important;
    color: white;
}
</style>
