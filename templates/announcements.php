<?php
$announcementClass = new \App\Announcement($db);
$branchClass = new \App\Branch();
$allBranches = $branchClass->getAll();
$allTeams = $db->query("SELECT id, name FROM teams WHERE is_active = TRUE ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$allEmployees = $db->query("SELECT id, name FROM employees WHERE employment_status = 'active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

$action = $_GET['action'] ?? 'list';
$announcementId = isset($_GET['id']) ? (int)$_GET['id'] : null;

$announcement = null;
if ($announcementId && in_array($action, ['view', 'edit'])) {
    $announcement = $announcementClass->getById($announcementId);
}

$announcements = $announcementClass->getAll();
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-megaphone"></i> Employee Announcements</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAnnouncementModal">
            <i class="bi bi-plus-lg"></i> New Announcement
        </button>
    </div>

    <?php if ($action === 'view' && $announcement): ?>
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>
                <span class="badge bg-<?= $announcement['priority'] === 'urgent' ? 'danger' : ($announcement['priority'] === 'high' ? 'warning' : 'secondary') ?>">
                    <?= ucfirst($announcement['priority']) ?>
                </span>
                <?= htmlspecialchars($announcement['title']) ?>
            </span>
            <a href="?page=hr&subpage=announcements" class="btn btn-sm btn-secondary">Back to List</a>
        </div>
        <div class="card-body">
            <p class="lead"><?= nl2br(htmlspecialchars($announcement['message'])) ?></p>
            <hr>
            <div class="row">
                <div class="col-md-4">
                    <strong>Target:</strong> 
                    <?php if ($announcement['target_audience'] === 'branch'): ?>
                        Branch: <?= htmlspecialchars($announcement['branch_name'] ?? 'Unknown') ?>
                    <?php elseif ($announcement['target_audience'] === 'team'): ?>
                        Team: <?= htmlspecialchars($announcement['team_name'] ?? 'Unknown') ?>
                    <?php else: ?>
                        All Employees
                    <?php endif; ?>
                </div>
                <div class="col-md-4">
                    <strong>Status:</strong> 
                    <span class="badge bg-<?= $announcement['status'] === 'sent' ? 'success' : ($announcement['status'] === 'scheduled' ? 'info' : 'secondary') ?>">
                        <?= ucfirst($announcement['status']) ?>
                    </span>
                </div>
                <div class="col-md-4">
                    <strong>Created:</strong> <?= date('M j, Y H:i', strtotime($announcement['created_at'])) ?>
                    <?php if ($announcement['sent_at']): ?>
                        <br><strong>Sent:</strong> <?= date('M j, Y H:i', strtotime($announcement['sent_at'])) ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($announcement['status'] === 'sent'): ?>
            <hr>
            <h5>Recipients</h5>
            <?php $recipients = $announcementClass->getRecipients($announcementId); ?>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>SMS Sent</th>
                            <th>Notification Read</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recipients as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['employee_name']) ?></td>
                            <td>
                                <?php if ($r['sms_sent']): ?>
                                    <span class="text-success"><i class="bi bi-check-circle"></i> <?= $r['sms_sent_at'] ? date('M j H:i', strtotime($r['sms_sent_at'])) : '' ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($r['notification_read']): ?>
                                    <span class="text-success"><i class="bi bi-eye"></i> <?= $r['notification_read_at'] ? date('M j H:i', strtotime($r['notification_read_at'])) : '' ?></span>
                                <?php else: ?>
                                    <span class="text-muted">Unread</span>
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
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Priority</th>
                            <th>Target</th>
                            <th>SMS</th>
                            <th>Status</th>
                            <th>Recipients</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($announcements as $a): ?>
                        <tr>
                            <td><?= htmlspecialchars($a['title']) ?></td>
                            <td>
                                <span class="badge bg-<?= $a['priority'] === 'urgent' ? 'danger' : ($a['priority'] === 'high' ? 'warning' : 'secondary') ?>">
                                    <?= ucfirst($a['priority']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($a['target_audience'] === 'branch'): ?>
                                    <i class="bi bi-building"></i> <?= htmlspecialchars($a['branch_name'] ?? '') ?>
                                <?php elseif ($a['target_audience'] === 'team'): ?>
                                    <i class="bi bi-people"></i> <?= htmlspecialchars($a['team_name'] ?? '') ?>
                                <?php else: ?>
                                    <i class="bi bi-globe"></i> All
                                <?php endif; ?>
                            </td>
                            <td><?= $a['send_sms'] ? '<i class="bi bi-check-circle text-success"></i>' : '<i class="bi bi-x-circle text-muted"></i>' ?></td>
                            <td>
                                <span class="badge bg-<?= $a['status'] === 'sent' ? 'success' : ($a['status'] === 'scheduled' ? 'info' : 'secondary') ?>">
                                    <?= ucfirst($a['status']) ?>
                                </span>
                            </td>
                            <td>
                                <?= $a['read_count'] ?>/<?= $a['recipient_count'] ?>
                            </td>
                            <td><?= date('M j, Y', strtotime($a['created_at'])) ?></td>
                            <td>
                                <a href="?page=hr&subpage=announcements&action=view&id=<?= $a['id'] ?>" class="btn btn-sm btn-outline-primary" title="View">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <?php if ($a['status'] === 'draft'): ?>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Send this announcement to all recipients?');">
                                    <input type="hidden" name="csrf_token" value="<?= \App\Auth::generateToken() ?>">
                                    <input type="hidden" name="action" value="send_announcement">
                                    <input type="hidden" name="announcement_id" value="<?= $a['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-success" title="Send Now">
                                        <i class="bi bi-send"></i>
                                    </button>
                                </form>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this announcement?');">
                                    <input type="hidden" name="csrf_token" value="<?= \App\Auth::generateToken() ?>">
                                    <input type="hidden" name="action" value="delete_announcement">
                                    <input type="hidden" name="announcement_id" value="<?= $a['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($announcements)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted">No announcements yet</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="createAnnouncementModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= \App\Auth::generateToken() ?>">
                <input type="hidden" name="action" value="create_announcement">
                <div class="modal-header">
                    <h5 class="modal-title">New Announcement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Message <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="message" rows="4" required></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Priority</label>
                            <select class="form-select" name="priority">
                                <option value="low">Low</option>
                                <option value="normal" selected>Normal</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Target Audience</label>
                            <select class="form-select" name="target_audience" id="targetAudience" onchange="toggleTargetFields()">
                                <option value="all">All Employees</option>
                                <option value="branch">Specific Branch</option>
                                <option value="team">Specific Team</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3" id="branchField" style="display:none;">
                            <label class="form-label">Select Branch</label>
                            <select class="form-select" name="target_branch_id">
                                <option value="">-- Select Branch --</option>
                                <?php foreach ($allBranches as $b): ?>
                                <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3" id="teamField" style="display:none;">
                            <label class="form-label">Select Team</label>
                            <select class="form-select" name="target_team_id">
                                <option value="">-- Select Team --</option>
                                <?php foreach ($allTeams as $t): ?>
                                <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="send_sms" id="sendSms" value="1">
                                <label class="form-check-label" for="sendSms">
                                    Send SMS to employees
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="send_notification" id="sendNotification" value="1" checked>
                                <label class="form-check-label" for="sendNotification">
                                    Send in-app notification
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="save_as" value="draft" class="btn btn-outline-primary">Save as Draft</button>
                    <button type="submit" name="save_as" value="send" class="btn btn-primary">Save & Send Now</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleTargetFields() {
    const audience = document.getElementById('targetAudience').value;
    document.getElementById('branchField').style.display = audience === 'branch' ? 'block' : 'none';
    document.getElementById('teamField').style.display = audience === 'team' ? 'block' : 'none';
}
</script>
