<?php
$db = Database::getConnection();

$db->exec("CREATE TABLE IF NOT EXISTS maintenance_windows (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    olt_id INTEGER,
    slot INTEGER,
    port INTEGER,
    start_time TIMESTAMP NOT NULL,
    end_time TIMESTAMP NOT NULL,
    status VARCHAR(20) DEFAULT 'scheduled',
    notify_customers BOOLEAN DEFAULT FALSE,
    notifications_sent BOOLEAN DEFAULT FALSE,
    created_by INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$message = '';
$messageType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && \App\Auth::can('settings.view')) {
    $postAction = $_POST['maintenance_action'] ?? '';

    if ($postAction === 'create' || $postAction === 'update') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $oltId = !empty($_POST['olt_id']) ? (int)$_POST['olt_id'] : null;
        $slot = !empty($_POST['slot']) ? (int)$_POST['slot'] : null;
        $port = !empty($_POST['port']) ? (int)$_POST['port'] : null;
        $startTime = $_POST['start_time'] ?? '';
        $endTime = $_POST['end_time'] ?? '';
        $status = $_POST['status'] ?? 'scheduled';
        $notifyCustomers = isset($_POST['notify_customers']) ? true : false;

        if (empty($title) || empty($startTime) || empty($endTime)) {
            $message = 'Title, start time, and end time are required.';
            $messageType = 'danger';
        } elseif (strtotime($endTime) <= strtotime($startTime)) {
            $message = 'End time must be after start time.';
            $messageType = 'danger';
        } else {
            try {
                if ($postAction === 'create') {
                    $stmt = $db->prepare("INSERT INTO maintenance_windows (title, description, olt_id, slot, port, start_time, end_time, status, notify_customers, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$title, $description, $oltId, $slot, $port, $startTime, $endTime, $status, $notifyCustomers ? 't' : 'f', $_SESSION['user_id'] ?? null]);
                    $message = 'Maintenance window created successfully.';
                    $messageType = 'success';

                    if ($notifyCustomers && $status === 'active') {
                        $newId = $db->lastInsertId();
                        sendMaintenanceNotifications($db, $newId);
                    }
                } else {
                    $editId = (int)($_POST['id'] ?? 0);
                    $stmt = $db->prepare("UPDATE maintenance_windows SET title=?, description=?, olt_id=?, slot=?, port=?, start_time=?, end_time=?, status=?, notify_customers=?, updated_at=NOW() WHERE id=?");
                    $stmt->execute([$title, $description, $oltId, $slot, $port, $startTime, $endTime, $status, $notifyCustomers ? 't' : 'f', $editId]);
                    $message = 'Maintenance window updated successfully.';
                    $messageType = 'success';

                    if ($notifyCustomers && $status === 'active') {
                        $chk = $db->prepare("SELECT notifications_sent FROM maintenance_windows WHERE id = ?");
                        $chk->execute([$editId]);
                        $sent = $chk->fetchColumn();
                        if (!$sent) {
                            sendMaintenanceNotifications($db, $editId);
                        }
                    }
                }
            } catch (Exception $e) {
                $message = 'Error: ' . $e->getMessage();
                $messageType = 'danger';
            }
        }
    }

    if ($postAction === 'delete') {
        $delId = (int)($_POST['id'] ?? 0);
        try {
            $stmt = $db->prepare("DELETE FROM maintenance_windows WHERE id = ?");
            $stmt->execute([$delId]);
            $message = 'Maintenance window deleted.';
            $messageType = 'success';
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }

    if ($postAction === 'complete') {
        $compId = (int)($_POST['id'] ?? 0);
        try {
            $stmt = $db->prepare("UPDATE maintenance_windows SET status = 'completed', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$compId]);
            $message = 'Maintenance window marked as completed.';
            $messageType = 'success';
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }

    if ($postAction === 'notify') {
        $notifyId = (int)($_POST['id'] ?? 0);
        try {
            sendMaintenanceNotifications($db, $notifyId);
            $message = 'Notifications sent to affected customers.';
            $messageType = 'success';
        } catch (Exception $e) {
            $message = 'Error sending notifications: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

function sendMaintenanceNotifications($db, $maintenanceId) {
    $stmt = $db->prepare("SELECT * FROM maintenance_windows WHERE id = ?");
    $stmt->execute([$maintenanceId]);
    $mw = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$mw) return;

    $whatsapp = new \App\WhatsApp();
    $settings = new \App\Settings();

    $query = "SELECT DISTINCT c.phone, c.name FROM customers c";
    $params = [];

    if (!empty($mw['olt_id'])) {
        $query .= " INNER JOIN huawei_onus o ON o.customer_id = c.id WHERE o.olt_id = ?";
        $params[] = $mw['olt_id'];
        if ($mw['slot'] !== null) {
            $query .= " AND o.slot = ?";
            $params[] = $mw['slot'];
        }
        if ($mw['port'] !== null) {
            $query .= " AND o.port = ?";
            $params[] = $mw['port'];
        }
    } else {
        $query .= " WHERE c.status = 'active'";
    }

    try {
        $custStmt = $db->prepare($query);
        $custStmt->execute($params);
        $customers = $custStmt->fetchAll(PDO::FETCH_ASSOC);

        $startFormatted = date('M j, Y g:i A', strtotime($mw['start_time']));
        $endFormatted = date('M j, Y g:i A', strtotime($mw['end_time']));

        $template = $settings->get('wa_template_maintenance', "🔧 *Scheduled Maintenance Notice*\n\nDear {customer_name},\n\nWe will be performing maintenance on our network.\n\n📌 *{title}*\n📝 {description}\n🕐 From: {start_time}\n🕐 To: {end_time}\n\nYou may experience brief service interruptions during this period. We apologize for any inconvenience.\n\nThank you for your patience.");

        foreach ($customers as $cust) {
            if (empty($cust['phone'])) continue;
            $msg = str_replace(
                ['{customer_name}', '{title}', '{description}', '{start_time}', '{end_time}'],
                [$cust['name'] ?? 'Customer', $mw['title'], $mw['description'] ?? '', $startFormatted, $endFormatted],
                $template
            );
            $whatsapp->send($cust['phone'], $msg);
            usleep(100000);
        }

        $upd = $db->prepare("UPDATE maintenance_windows SET notifications_sent = TRUE WHERE id = ?");
        $upd->execute([$maintenanceId]);
    } catch (Exception $e) {
        error_log("Maintenance notification error: " . $e->getMessage());
        throw $e;
    }
}

$db->exec("UPDATE maintenance_windows SET status = 'active' WHERE status = 'scheduled' AND start_time <= NOW() AND end_time >= NOW()");
$db->exec("UPDATE maintenance_windows SET status = 'completed' WHERE status IN ('scheduled','active') AND end_time < NOW()");

$statusFilter = $_GET['status'] ?? '';
$filterSql = '';
$filterParams = [];
if ($statusFilter && in_array($statusFilter, ['scheduled', 'active', 'completed'])) {
    $filterSql = ' WHERE status = ?';
    $filterParams[] = $statusFilter;
}

$windows = $db->prepare("SELECT mw.*, u.name as creator_name FROM maintenance_windows mw LEFT JOIN users u ON mw.created_by = u.id $filterSql ORDER BY mw.start_time DESC");
$windows->execute($filterParams);
$allWindows = $windows->fetchAll(PDO::FETCH_ASSOC);

$olts = [];
try {
    $olts = $db->query("SELECT id, name FROM huawei_olts ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$editWindow = null;
if (($action ?? '') === 'edit' && $id) {
    $es = $db->prepare("SELECT * FROM maintenance_windows WHERE id = ?");
    $es->execute([$id]);
    $editWindow = $es->fetch(PDO::FETCH_ASSOC);
}

$statusCounts = ['all' => 0, 'scheduled' => 0, 'active' => 0, 'completed' => 0];
try {
    $cntStmt = $db->query("SELECT status, COUNT(*) as cnt FROM maintenance_windows GROUP BY status");
    while ($row = $cntStmt->fetch(PDO::FETCH_ASSOC)) {
        $statusCounts[$row['status']] = (int)$row['cnt'];
        $statusCounts['all'] += (int)$row['cnt'];
    }
} catch (Exception $e) {}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-tools"></i> Maintenance Windows</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#maintenanceModal" onclick="resetForm()">
        <i class="bi bi-plus-circle"></i> Schedule Maintenance
    </button>
</div>

<?php if ($message): ?>
<div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <a href="?page=maintenance" class="text-decoration-none">
            <div class="card stat-card <?= !$statusFilter ? 'border-primary' : '' ?>">
                <div class="card-body text-center py-3">
                    <h4 class="mb-0"><?= $statusCounts['all'] ?></h4>
                    <small class="text-muted">All</small>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="?page=maintenance&status=scheduled" class="text-decoration-none">
            <div class="card stat-card <?= $statusFilter === 'scheduled' ? 'border-info' : '' ?>">
                <div class="card-body text-center py-3">
                    <h4 class="mb-0 text-info"><?= $statusCounts['scheduled'] ?></h4>
                    <small class="text-muted">Scheduled</small>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="?page=maintenance&status=active" class="text-decoration-none">
            <div class="card stat-card <?= $statusFilter === 'active' ? 'border-warning' : '' ?>">
                <div class="card-body text-center py-3">
                    <h4 class="mb-0 text-warning"><?= $statusCounts['active'] ?></h4>
                    <small class="text-muted">Active</small>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="?page=maintenance&status=completed" class="text-decoration-none">
            <div class="card stat-card <?= $statusFilter === 'completed' ? 'border-success' : '' ?>">
                <div class="card-body text-center py-3">
                    <h4 class="mb-0 text-success"><?= $statusCounts['completed'] ?></h4>
                    <small class="text-muted">Completed</small>
                </div>
            </div>
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header bg-white">
        <h5 class="mb-0">Maintenance Schedule</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Title</th>
                        <th>Scope</th>
                        <th>Start</th>
                        <th>End</th>
                        <th>Status</th>
                        <th>Notify</th>
                        <th>Created By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($allWindows)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No maintenance windows found</td>
                    </tr>
                    <?php endif; ?>
                    <?php foreach ($allWindows as $w): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($w['title']) ?></strong>
                            <?php if ($w['description']): ?>
                            <br><small class="text-muted"><?= htmlspecialchars(substr($w['description'], 0, 60)) ?><?= strlen($w['description']) > 60 ? '...' : '' ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($w['olt_id']): ?>
                                <?php
                                $oltName = 'OLT #' . $w['olt_id'];
                                foreach ($olts as $o) { if ($o['id'] == $w['olt_id']) { $oltName = $o['name']; break; } }
                                ?>
                                <span class="badge bg-secondary"><?= htmlspecialchars($oltName) ?></span>
                                <?php if ($w['slot'] !== null): ?><br><small>Slot <?= $w['slot'] ?><?= $w['port'] !== null ? ' / Port ' . $w['port'] : '' ?></small><?php endif; ?>
                            <?php else: ?>
                                <span class="badge bg-dark">All Network</span>
                            <?php endif; ?>
                        </td>
                        <td><?= date('M j, Y g:i A', strtotime($w['start_time'])) ?></td>
                        <td><?= date('M j, Y g:i A', strtotime($w['end_time'])) ?></td>
                        <td>
                            <?php
                            $statusBadge = match($w['status']) {
                                'scheduled' => 'bg-info',
                                'active' => 'bg-warning text-dark',
                                'completed' => 'bg-success',
                                default => 'bg-secondary'
                            };
                            ?>
                            <span class="badge <?= $statusBadge ?>"><?= ucfirst($w['status']) ?></span>
                        </td>
                        <td>
                            <?php if ($w['notify_customers']): ?>
                                <i class="bi bi-check-circle text-success"></i>
                                <?php if ($w['notifications_sent']): ?><small class="text-success">Sent</small><?php else: ?><small class="text-muted">Pending</small><?php endif; ?>
                            <?php else: ?>
                                <i class="bi bi-x-circle text-muted"></i>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($w['creator_name'] ?? 'System') ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <?php if ($w['status'] !== 'completed'): ?>
                                <button class="btn btn-outline-primary" onclick='editMaintenance(<?= json_encode($w) ?>)' title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <?php endif; ?>
                                <?php if ($w['status'] === 'active' || $w['status'] === 'scheduled'): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="maintenance_action" value="complete">
                                    <input type="hidden" name="id" value="<?= $w['id'] ?>">
                                    <button type="submit" class="btn btn-outline-success" title="Mark Completed" onclick="return confirm('Mark this maintenance as completed?')">
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                                <?php if ($w['notify_customers'] && !$w['notifications_sent'] && $w['status'] !== 'completed'): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="maintenance_action" value="notify">
                                    <input type="hidden" name="id" value="<?= $w['id'] ?>">
                                    <button type="submit" class="btn btn-outline-info" title="Send Notifications" onclick="return confirm('Send WhatsApp notifications to affected customers?')">
                                        <i class="bi bi-whatsapp"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="maintenance_action" value="delete">
                                    <input type="hidden" name="id" value="<?= $w['id'] ?>">
                                    <button type="submit" class="btn btn-outline-danger" title="Delete" onclick="return confirm('Delete this maintenance window?')">
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
    </div>
</div>

<div class="modal fade" id="maintenanceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="maintenanceModalTitle"><i class="bi bi-tools"></i> Schedule Maintenance</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="maintenance_action" id="formAction" value="create">
                    <input type="hidden" name="id" id="formId" value="">

                    <div class="mb-3">
                        <label class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="title" id="formTitle" required placeholder="e.g., OLT Firmware Upgrade">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="formDescription" rows="3" placeholder="Details about the maintenance work..."></textarea>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">OLT (Optional)</label>
                            <select class="form-select" name="olt_id" id="formOltId">
                                <option value="">All Network</option>
                                <?php foreach ($olts as $olt): ?>
                                <option value="<?= $olt['id'] ?>"><?= htmlspecialchars($olt['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Slot (Optional)</label>
                            <input type="number" class="form-control" name="slot" id="formSlot" min="0" placeholder="e.g., 0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Port (Optional)</label>
                            <input type="number" class="form-control" name="port" id="formPort" min="0" placeholder="e.g., 3">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Start Time <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control" name="start_time" id="formStartTime" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">End Time <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control" name="end_time" id="formEndTime" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" id="formStatus">
                                <option value="scheduled">Scheduled</option>
                                <option value="active">Active</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="notify_customers" id="formNotify" value="1">
                                <label class="form-check-label" for="formNotify">
                                    <i class="bi bi-whatsapp text-success"></i> Notify affected customers via WhatsApp
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="formSubmitBtn"><i class="bi bi-save"></i> Create</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function resetForm() {
    document.getElementById('formAction').value = 'create';
    document.getElementById('formId').value = '';
    document.getElementById('formTitle').value = '';
    document.getElementById('formDescription').value = '';
    document.getElementById('formOltId').value = '';
    document.getElementById('formSlot').value = '';
    document.getElementById('formPort').value = '';
    document.getElementById('formStartTime').value = '';
    document.getElementById('formEndTime').value = '';
    document.getElementById('formStatus').value = 'scheduled';
    document.getElementById('formNotify').checked = false;
    document.getElementById('maintenanceModalTitle').innerHTML = '<i class="bi bi-tools"></i> Schedule Maintenance';
    document.getElementById('formSubmitBtn').innerHTML = '<i class="bi bi-save"></i> Create';
}

function editMaintenance(w) {
    document.getElementById('formAction').value = 'update';
    document.getElementById('formId').value = w.id;
    document.getElementById('formTitle').value = w.title;
    document.getElementById('formDescription').value = w.description || '';
    document.getElementById('formOltId').value = w.olt_id || '';
    document.getElementById('formSlot').value = w.slot || '';
    document.getElementById('formPort').value = w.port || '';
    document.getElementById('formStartTime').value = w.start_time ? w.start_time.replace(' ', 'T').substring(0, 16) : '';
    document.getElementById('formEndTime').value = w.end_time ? w.end_time.replace(' ', 'T').substring(0, 16) : '';
    document.getElementById('formStatus').value = w.status;
    document.getElementById('formNotify').checked = w.notify_customers === true || w.notify_customers === 't' || w.notify_customers === '1';
    document.getElementById('maintenanceModalTitle').innerHTML = '<i class="bi bi-tools"></i> Edit Maintenance';
    document.getElementById('formSubmitBtn').innerHTML = '<i class="bi bi-save"></i> Update';
    new bootstrap.Modal(document.getElementById('maintenanceModal')).show();
}
</script>
