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
                $pdo->beginTransaction();
                
                if ($newStatus === 'Resolved') {
                    $resolutionNotes = trim($_POST['resolution_notes'] ?? '');
                    $routerSerial = trim($_POST['router_serial'] ?? '');
                    $powerLevels = trim($_POST['power_levels'] ?? '');
                    $cableUsed = trim($_POST['cable_used'] ?? '');
                    $equipmentInstalled = trim($_POST['equipment_installed'] ?? '');
                    $additionalNotes = trim($_POST['additional_notes'] ?? '');
                    
                    if (empty($resolutionNotes)) {
                        throw new Exception('Resolution notes are required.');
                    }
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO ticket_resolutions 
                        (ticket_id, resolved_by, resolution_notes, router_serial, power_levels, cable_used, equipment_installed, additional_notes)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                        ON CONFLICT (ticket_id) DO UPDATE SET
                            resolution_notes = EXCLUDED.resolution_notes,
                            router_serial = EXCLUDED.router_serial,
                            power_levels = EXCLUDED.power_levels,
                            cable_used = EXCLUDED.cable_used,
                            equipment_installed = EXCLUDED.equipment_installed,
                            additional_notes = EXCLUDED.additional_notes,
                            updated_at = CURRENT_TIMESTAMP
                        RETURNING id
                    ");
                    $stmt->execute([
                        $tokenRecord['ticket_id'],
                        $tokenRecord['employee_id'],
                        $resolutionNotes,
                        $routerSerial,
                        $powerLevels,
                        $cableUsed,
                        $equipmentInstalled,
                        $additionalNotes
                    ]);
                    $resolutionId = $stmt->fetchColumn();
                    
                    $uploadDir = __DIR__ . '/uploads/ticket_resolutions/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    $maxFileSize = 10 * 1024 * 1024;
                    $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    $photoTypes = ['photo_serial' => 'serial', 'photo_power' => 'power_levels', 'photo_cables' => 'cables', 'photo_additional' => 'additional'];
                    
                    foreach ($photoTypes as $fieldName => $photoType) {
                        if (!empty($_FILES[$fieldName]['name']) && $_FILES[$fieldName]['error'] === UPLOAD_ERR_OK) {
                            $tmpFile = $_FILES[$fieldName]['tmp_name'];
                            $fileSize = $_FILES[$fieldName]['size'];
                            $ext = strtolower(pathinfo($_FILES[$fieldName]['name'], PATHINFO_EXTENSION));
                            
                            if ($fileSize > $maxFileSize) continue;
                            if (!in_array($ext, $allowedExts)) continue;
                            
                            $finfo = finfo_open(FILEINFO_MIME_TYPE);
                            $mimeType = finfo_file($finfo, $tmpFile);
                            finfo_close($finfo);
                            
                            if (!in_array($mimeType, $allowedMimes)) continue;
                            
                            $safeExt = match($mimeType) {
                                'image/jpeg' => 'jpg',
                                'image/png' => 'png',
                                'image/gif' => 'gif',
                                'image/webp' => 'webp',
                                default => 'jpg'
                            };
                            
                            $fileName = 'ticket_' . $tokenRecord['ticket_id'] . '_' . $photoType . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $safeExt;
                            $filePath = 'uploads/ticket_resolutions/' . $fileName;
                            
                            if (move_uploaded_file($tmpFile, $uploadDir . $fileName)) {
                                $stmt = $pdo->prepare("
                                    INSERT INTO ticket_resolution_photos 
                                    (ticket_id, resolution_id, photo_type, file_path, file_name, uploaded_by)
                                    VALUES (?, ?, ?, ?, ?, ?)
                                ");
                                $stmt->execute([$tokenRecord['ticket_id'], $resolutionId, $photoType, $filePath, $_FILES[$fieldName]['name'], $tokenRecord['employee_id']]);
                            }
                        }
                    }
                }
                
                $stmt = $pdo->prepare("UPDATE tickets SET status = ?, resolved_at = CASE WHEN ? = 'resolved' THEN NOW() ELSE resolved_at END, updated_at = NOW() WHERE id = ?");
                $stmt->execute([strtolower(str_replace(' ', '_', $newStatus)), strtolower(str_replace(' ', '_', $newStatus)), $tokenRecord['ticket_id']]);
                
                $statusLink->useToken($tokenRecord['id']);
                
                if (in_array($newStatus, ['Resolved', 'Closed'])) {
                    $statusLink->invalidateToken($tokenRecord['id']);
                }
                
                $techName = $tokenRecord['assigned_to_name'] ?? 'Technician';
                $logDescription = "Status changed to '{$newStatus}' via quick link by {$techName}" . ($comment ? ". Note: {$comment}" : "");
                try {
                    $logStmt = $pdo->prepare("
                        INSERT INTO activity_logs (user_id, action_type, entity_type, entity_id, details, created_at)
                        VALUES (?, 'status_updated_via_link', 'ticket', ?, ?, NOW())
                    ");
                    $logStmt->execute([$tokenRecord['employee_id'], $tokenRecord['ticket_id'], $logDescription]);
                } catch (Exception $logEx) {
                }
                
                if (!empty($comment)) {
                    $stmt = $pdo->prepare("
                        INSERT INTO ticket_comments (ticket_id, user_id, comment, created_at)
                        VALUES (?, ?, ?, NOW())
                    ");
                    $stmt->execute([$tokenRecord['ticket_id'], $tokenRecord['employee_id'], $comment]);
                }
                
                $pdo->commit();
                
                $message = "Ticket status updated to '{$newStatus}' successfully!";
                $messageType = 'success';
                
                $tokenRecord = $statusLink->validateToken($postToken);
                if ($tokenRecord) {
                    $allowedStatuses = $statusLink->getAllowedStatuses($tokenRecord);
                }
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = 'Failed to update ticket: ' . $e->getMessage();
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
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
            max-width: 600px;
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
        .priority-high, .priority-critical { background: #e74c3c; color: white; }
        .priority-medium { background: #f39c12; color: white; }
        .priority-low { background: #3498db; color: white; }
        .current-status {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: 600;
        }
        .status-open { background: #3498db; color: white; }
        .status-in-progress, .status-in_progress { background: #f39c12; color: white; }
        .status-resolved { background: #27ae60; color: white; }
        .status-closed { background: #95a5a6; color: white; }
        .resolution-form { display: none; }
        .resolution-form.active { display: block; }
        .photo-upload-card {
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            transition: all 0.3s;
        }
        .photo-upload-card:hover {
            border-color: #667eea;
            background: #f8f9fa;
        }
        .photo-preview {
            max-height: 80px;
            border-radius: 5px;
            margin-top: 10px;
        }
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
                <h4 class="mb-0"><i class="bi bi-check-circle"></i> Success!</h4>
            </div>
            <div class="card-body text-center py-5">
                <div style="font-size: 4rem; color: #27ae60;"><i class="bi bi-check-circle-fill"></i></div>
                <p class="mt-3 fs-5"><?= htmlspecialchars($message) ?></p>
                <p class="text-muted">You can close this page now.</p>
            </div>
        <?php else: ?>
            <div class="card-header">
                <h4 class="mb-0 text-center"><i class="bi bi-ticket-detailed"></i> Update Ticket Status</h4>
            </div>
            <div class="card-body p-4">
                <?php if ($message && $messageType === 'error'): ?>
                    <div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($message) ?></div>
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
                            <?= htmlspecialchars(ucwords(str_replace('_', ' ', $tokenRecord['current_status'] ?? 'Open'))) ?>
                        </span>
                    </p>
                </div>
                
                <div id="statusButtons">
                    <p class="text-center text-muted mb-3">Select new status:</p>
                    
                    <div class="d-grid gap-2">
                        <?php if (in_array('In Progress', $allowedStatuses)): ?>
                            <button type="button" class="btn status-btn btn-in-progress" onclick="submitSimpleStatus('In Progress')">
                                <i class="bi bi-play-circle"></i> Mark as In Progress
                            </button>
                        <?php endif; ?>
                        
                        <?php if (in_array('Resolved', $allowedStatuses)): ?>
                            <button type="button" class="btn status-btn btn-resolved" onclick="showResolutionForm()">
                                <i class="bi bi-check-circle"></i> Mark as Resolved
                            </button>
                        <?php endif; ?>
                        
                        <?php if (in_array('Closed', $allowedStatuses)): ?>
                            <button type="button" class="btn status-btn btn-closed" onclick="submitSimpleStatus('Closed')">
                                <i class="bi bi-x-circle"></i> Mark as Closed
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div id="resolutionForm" class="resolution-form">
                    <div class="d-flex align-items-center mb-3">
                        <button type="button" class="btn btn-link text-decoration-none p-0 me-2" onclick="hideResolutionForm()">
                            <i class="bi bi-arrow-left"></i>
                        </button>
                        <h5 class="mb-0 text-success"><i class="bi bi-check-circle"></i> Complete Resolution</h5>
                    </div>
                    
                    <div class="alert alert-info small">
                        <i class="bi bi-info-circle"></i> Please provide resolution details and photos before marking as resolved.
                    </div>
                    
                    <form method="POST" enctype="multipart/form-data" id="resolutionFormSubmit">
                        <input type="hidden" name="token" value="<?= htmlspecialchars($tokenParam) ?>">
                        <input type="hidden" name="new_status" value="Resolved">
                        
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label small">Router Serial</label>
                                <input type="text" class="form-control form-control-sm" name="router_serial" placeholder="SN123456">
                            </div>
                            <div class="col-6">
                                <label class="form-label small">Power Levels</label>
                                <input type="text" class="form-control form-control-sm" name="power_levels" placeholder="TX:-3.2/RX:-18.5">
                            </div>
                        </div>
                        
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label small">Cable Used</label>
                                <input type="text" class="form-control form-control-sm" name="cable_used" placeholder="50m CAT6">
                            </div>
                            <div class="col-6">
                                <label class="form-label small">Equipment</label>
                                <input type="text" class="form-control form-control-sm" name="equipment_installed" placeholder="ONU, Router">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label small">Resolution Notes *</label>
                            <textarea class="form-control form-control-sm" name="resolution_notes" rows="2" required placeholder="What was done to resolve the issue..."></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label small">Additional Notes</label>
                            <textarea class="form-control form-control-sm" name="additional_notes" rows="1" placeholder="Any other details about the resolution..."></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label small">Comment (visible in ticket)</label>
                            <textarea class="form-control form-control-sm" name="comment" rows="1" placeholder="Any follow-up needed..."></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label small"><i class="bi bi-camera"></i> Resolution Photos</label>
                            <div class="row g-2">
                                <div class="col-6">
                                    <div class="photo-upload-card">
                                        <i class="bi bi-upc-scan text-muted"></i>
                                        <div class="small text-muted">Serial Photo</div>
                                        <input type="file" class="form-control form-control-sm mt-2" name="photo_serial" accept="image/*" onchange="previewPhoto(this, 'preview_serial')">
                                        <img id="preview_serial" class="photo-preview d-none">
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="photo-upload-card">
                                        <i class="bi bi-graph-up text-muted"></i>
                                        <div class="small text-muted">Power Levels</div>
                                        <input type="file" class="form-control form-control-sm mt-2" name="photo_power" accept="image/*" onchange="previewPhoto(this, 'preview_power')">
                                        <img id="preview_power" class="photo-preview d-none">
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="photo-upload-card">
                                        <i class="bi bi-ethernet text-muted"></i>
                                        <div class="small text-muted">Cables/Install</div>
                                        <input type="file" class="form-control form-control-sm mt-2" name="photo_cables" accept="image/*" onchange="previewPhoto(this, 'preview_cables')">
                                        <img id="preview_cables" class="photo-preview d-none">
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="photo-upload-card">
                                        <i class="bi bi-image text-muted"></i>
                                        <div class="small text-muted">Additional</div>
                                        <input type="file" class="form-control form-control-sm mt-2" name="photo_additional" accept="image/*" onchange="previewPhoto(this, 'preview_additional')">
                                        <img id="preview_additional" class="photo-preview d-none">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-success btn-lg" id="submitResolutionBtn">
                                <i class="bi bi-check-circle"></i> Complete Resolution
                            </button>
                        </div>
                    </form>
                </div>
                
                <form method="POST" id="simpleStatusForm" style="display: none;">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($tokenParam) ?>">
                    <input type="hidden" name="new_status" id="simpleNewStatus" value="">
                </form>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        function showResolutionForm() {
            document.getElementById('statusButtons').style.display = 'none';
            document.getElementById('resolutionForm').classList.add('active');
        }
        
        function hideResolutionForm() {
            document.getElementById('statusButtons').style.display = 'block';
            document.getElementById('resolutionForm').classList.remove('active');
        }
        
        function submitSimpleStatus(status) {
            document.getElementById('simpleNewStatus').value = status;
            document.getElementById('simpleStatusForm').submit();
        }
        
        function previewPhoto(input, previewId) {
            const preview = document.getElementById(previewId);
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.classList.remove('d-none');
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        document.getElementById('resolutionFormSubmit')?.addEventListener('submit', function() {
            const btn = document.getElementById('submitResolutionBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Uploading...';
        });
    </script>
</body>
</html>
