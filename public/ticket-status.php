<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/TicketStatusLink.php';

$pdo = Database::getConnection();

$tokenParam = $_GET['t'] ?? $_POST['token'] ?? '';
$message = '';
$messageType = '';
$tokenRecord = null;
$allowedStatuses = [];

$statusLink = new TicketStatusLink($pdo);

if (!empty($tokenParam)) {
    $tokenRecord = $statusLink->validateToken($tokenParam);
    if ($tokenRecord) {
        $allowedStatuses = $statusLink->getAllowedStatuses($tokenRecord);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['token']) && !empty($_POST['new_status'])) {
    $postToken = $_POST['token'];
    $newStatus = $_POST['new_status'];
    $comment = trim($_POST['comment'] ?? '');
    
    $tokenRecord = $statusLink->validateToken($postToken);
    
    if (!$tokenRecord) {
        $message = 'This link has expired or is no longer valid. Please request a new link.';
        $messageType = 'error';
    } else {
        $allowedStatuses = $statusLink->getAllowedStatuses($tokenRecord);
        
        if (!in_array($newStatus, $allowedStatuses)) {
            $message = 'Invalid status selected.';
            $messageType = 'error';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE tickets SET status = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$newStatus, $tokenRecord['ticket_id']]);
                
                $statusLink->useToken($tokenRecord['id']);
                
                if (in_array($newStatus, ['Resolved', 'Closed'])) {
                    $statusLink->invalidateToken($tokenRecord['id']);
                }
                
                $techName = $tokenRecord['assigned_to_name'] ?? 'Technician';
                $logStmt = $pdo->prepare("
                    INSERT INTO activity_logs (entity_type, entity_id, action, description, user_id, created_at)
                    VALUES ('ticket', ?, 'status_updated_via_link', ?, ?, NOW())
                ");
                $logDescription = "Status changed to '{$newStatus}' via quick link by {$techName}" . ($comment ? ". Note: {$comment}" : "");
                $logStmt->execute([$tokenRecord['ticket_id'], $logDescription, $tokenRecord['employee_id']]);
                
                if (!empty($comment)) {
                    $stmt = $pdo->prepare("
                        INSERT INTO ticket_comments (ticket_id, user_id, comment, created_at)
                        VALUES (?, ?, ?, NOW())
                    ");
                    $stmt->execute([$tokenRecord['ticket_id'], $tokenRecord['employee_id'], $comment]);
                }
                
                $message = "Ticket status updated to '{$newStatus}' successfully!";
                $messageType = 'success';
                
                $tokenRecord = $statusLink->validateToken($postToken);
                if ($tokenRecord) {
                    $allowedStatuses = $statusLink->getAllowedStatuses($tokenRecord);
                }
                
            } catch (Exception $e) {
                $message = 'Failed to update ticket status. Please try again.';
                $messageType = 'error';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Ticket Status</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card {
            max-width: 500px;
            width: 100%;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        .card-header {
            background: #2c3e50;
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 20px;
        }
        .ticket-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .status-btn {
            padding: 15px 20px;
            font-size: 1.1rem;
            margin-bottom: 10px;
            border-radius: 10px;
            transition: all 0.3s;
        }
        .status-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .btn-in-progress { background: #f39c12; border-color: #f39c12; color: white; }
        .btn-in-progress:hover { background: #e67e22; border-color: #e67e22; color: white; }
        .btn-resolved { background: #27ae60; border-color: #27ae60; color: white; }
        .btn-resolved:hover { background: #219a52; border-color: #219a52; color: white; }
        .btn-closed { background: #95a5a6; border-color: #95a5a6; color: white; }
        .btn-closed:hover { background: #7f8c8d; border-color: #7f8c8d; color: white; }
        .priority-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .priority-high { background: #e74c3c; color: white; }
        .priority-medium { background: #f39c12; color: white; }
        .priority-low { background: #3498db; color: white; }
        .current-status {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: 600;
        }
        .status-open { background: #3498db; color: white; }
        .status-in-progress { background: #f39c12; color: white; }
        .status-resolved { background: #27ae60; color: white; }
        .status-closed { background: #95a5a6; color: white; }
    </style>
</head>
<body>
    <div class="card">
        <?php if (empty($tokenParam)): ?>
            <div class="card-header text-center">
                <h4 class="mb-0">Invalid Request</h4>
            </div>
            <div class="card-body text-center py-5">
                <i class="bi bi-exclamation-triangle" style="font-size: 3rem; color: #e74c3c;"></i>
                <p class="mt-3 text-muted">No ticket token provided. Please use the link from your message.</p>
            </div>
        <?php elseif (!$tokenRecord && $messageType !== 'success'): ?>
            <div class="card-header text-center">
                <h4 class="mb-0">Link Expired</h4>
            </div>
            <div class="card-body text-center py-5">
                <i class="bi bi-clock-history" style="font-size: 3rem; color: #f39c12;"></i>
                <p class="mt-3 text-muted">This link has expired or has been used too many times.<br>Please request a new link from your supervisor.</p>
            </div>
        <?php elseif ($messageType === 'success'): ?>
            <div class="card-header text-center bg-success">
                <h4 class="mb-0">Success!</h4>
            </div>
            <div class="card-body text-center py-5">
                <div style="font-size: 4rem; color: #27ae60;">&#10004;</div>
                <p class="mt-3 fs-5"><?= htmlspecialchars($message) ?></p>
                <p class="text-muted">You can close this page now.</p>
            </div>
        <?php else: ?>
            <div class="card-header">
                <h4 class="mb-0 text-center">Update Ticket Status</h4>
            </div>
            <div class="card-body p-4">
                <?php if ($message && $messageType === 'error'): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($message) ?></div>
                <?php endif; ?>
                
                <div class="ticket-info">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <strong>Ticket #<?= $tokenRecord['ticket_id'] ?></strong>
                        <span class="priority-badge priority-<?= strtolower($tokenRecord['priority'] ?? 'medium') ?>">
                            <?= htmlspecialchars($tokenRecord['priority'] ?? 'Medium') ?>
                        </span>
                    </div>
                    <p class="mb-2"><strong>Subject:</strong> <?= htmlspecialchars($tokenRecord['subject'] ?? 'N/A') ?></p>
                    <p class="mb-2"><strong>Customer:</strong> <?= htmlspecialchars($tokenRecord['customer_name'] ?? 'N/A') ?></p>
                    <p class="mb-0">
                        <strong>Current Status:</strong> 
                        <span class="current-status status-<?= strtolower(str_replace(' ', '-', $tokenRecord['current_status'] ?? 'open')) ?>">
                            <?= htmlspecialchars($tokenRecord['current_status'] ?? 'Open') ?>
                        </span>
                    </p>
                </div>
                
                <form method="POST" id="statusForm">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($tokenParam) ?>">
                    <input type="hidden" name="new_status" id="newStatus" value="">
                    
                    <div class="mb-3">
                        <label class="form-label"><strong>Add a note (optional):</strong></label>
                        <textarea name="comment" class="form-control" rows="2" placeholder="e.g., Issue fixed by replacing router..."></textarea>
                    </div>
                    
                    <p class="text-center text-muted mb-3">Select new status:</p>
                    
                    <div class="d-grid gap-2">
                        <?php if (in_array('In Progress', $allowedStatuses)): ?>
                            <button type="button" class="btn status-btn btn-in-progress" onclick="submitStatus('In Progress')">
                                &#9881; Mark as In Progress
                            </button>
                        <?php endif; ?>
                        
                        <?php if (in_array('Resolved', $allowedStatuses)): ?>
                            <button type="button" class="btn status-btn btn-resolved" onclick="submitStatus('Resolved')">
                                &#10004; Mark as Resolved
                            </button>
                        <?php endif; ?>
                        
                        <?php if (in_array('Closed', $allowedStatuses)): ?>
                            <button type="button" class="btn status-btn btn-closed" onclick="submitStatus('Closed')">
                                &#10006; Mark as Closed
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        function submitStatus(status) {
            document.getElementById('newStatus').value = status;
            document.getElementById('statusForm').submit();
        }
    </script>
</body>
</html>
