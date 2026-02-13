<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Settings.php';
require_once __DIR__ . '/../src/SMSGateway.php';
require_once __DIR__ . '/../src/WhatsApp.php';
require_once __DIR__ . '/../src/SLA.php';
require_once __DIR__ . '/../src/ActivityLog.php';
require_once __DIR__ . '/../src/CustomerTicketLink.php';
require_once __DIR__ . '/../src/Ticket.php';

use App\Ticket;

$tokenParam = $_GET['t'] ?? $_POST['token'] ?? '';
$message = '';
$messageType = '';
$tokenRecord = null;
$timeline = [];

$db = Database::getConnection();
$customerLink = new CustomerTicketLink($db);

if (!empty($tokenParam)) {
    $tokenRecord = $customerLink->validateToken($tokenParam);
    if ($tokenRecord) {
        $customerLink->useToken($tokenRecord['id']);
        $timeline = $customerLink->getTicketTimeline($tokenRecord['ticket_id']);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['token']) && !empty($_POST['rating'])) {
    $postToken = $_POST['token'];
    $rating = (int)$_POST['rating'];
    $feedback = trim($_POST['feedback'] ?? '');
    
    $tokenRecord = $customerLink->validateToken($postToken);
    
    if (!$tokenRecord) {
        $message = 'This link has expired or is no longer valid.';
        $messageType = 'error';
    } elseif ($rating < 1 || $rating > 5) {
        $message = 'Please select a rating between 1 and 5 stars.';
        $messageType = 'error';
    } else {
        try {
            $ticket = new Ticket();
            $ticket->submitSatisfactionRating($tokenRecord['ticket_id'], [
                'rating' => $rating,
                'feedback' => $feedback,
                'customer_id' => $tokenRecord['customer_id'],
                'rated_by_name' => $tokenRecord['customer_name'] ?? 'Customer'
            ]);
            
            $message = "Thank you for your feedback! You rated this ticket {$rating}/5 stars.";
            $messageType = 'success';
            
            $tokenRecord = $customerLink->validateToken($postToken);
            if ($tokenRecord) {
                $timeline = $customerLink->getTicketTimeline($tokenRecord['ticket_id']);
            }
        } catch (Exception $e) {
            $message = 'Failed to submit rating. Please try again.';
            $messageType = 'error';
        }
    }
}

$statusColors = [
    'open' => '#3498db',
    'in_progress' => '#f39c12',
    'pending' => '#9b59b6',
    'resolved' => '#27ae60'
];

$priorityColors = [
    'low' => '#3498db',
    'medium' => '#f39c12',
    'high' => '#e74c3c',
    'critical' => '#8e44ad'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Ticket Status</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .card {
            max-width: 600px;
            margin: 0 auto;
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
            margin-bottom: 15px;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: 600;
            color: white;
        }
        .priority-badge {
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: 600;
            color: white;
        }
        .timeline-item {
            border-left: 3px solid #ddd;
            padding-left: 15px;
            margin-left: 10px;
            margin-bottom: 15px;
        }
        .timeline-item.activity {
            border-left-color: #3498db;
        }
        .timeline-item.comment {
            border-left-color: #27ae60;
        }
        .rating-stars {
            display: flex;
            gap: 5px;
            justify-content: center;
        }
        .rating-stars input {
            display: none;
        }
        .rating-stars label {
            font-size: 2.5rem;
            color: #ddd;
            cursor: pointer;
            transition: color 0.2s;
        }
        .rating-stars label:hover,
        .rating-stars label:hover ~ label,
        .rating-stars input:checked ~ label {
            color: #f39c12;
        }
        .rating-stars {
            flex-direction: row-reverse;
            justify-content: flex-end;
        }
        .rating-stars input:checked + label,
        .rating-stars input:checked + label ~ label {
            color: #f39c12;
        }
        .existing-rating {
            font-size: 1.5rem;
            color: #f39c12;
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
                <p class="text-muted">No ticket token provided. Please use the link from your message.</p>
            </div>
        <?php elseif (!$tokenRecord): ?>
            <div class="card-header text-center">
                <h4 class="mb-0">Link Expired</h4>
            </div>
            <div class="card-body text-center py-5">
                <p class="text-muted">This link has expired or is no longer valid.<br>Please contact support for assistance.</p>
            </div>
        <?php else: ?>
            <div class="card-header">
                <h4 class="mb-0 text-center">Ticket #<?= htmlspecialchars($tokenRecord['ticket_number']) ?></h4>
            </div>
            <div class="card-body p-4">
                <?php if ($message): ?>
                    <div class="alert alert-<?= $messageType === 'success' ? 'success' : 'danger' ?>"><?= htmlspecialchars($message) ?></div>
                <?php endif; ?>
                
                <div class="ticket-info">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="status-badge" style="background: <?= $statusColors[strtolower($tokenRecord['status'])] ?? '#95a5a6' ?>">
                            <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $tokenRecord['status']))) ?>
                        </span>
                        <span class="priority-badge" style="background: <?= $priorityColors[strtolower($tokenRecord['priority'])] ?? '#f39c12' ?>">
                            <?= htmlspecialchars(ucfirst($tokenRecord['priority'])) ?> Priority
                        </span>
                    </div>
                    
                    <h5 class="mb-3"><?= htmlspecialchars($tokenRecord['subject']) ?></h5>
                    
                    <p class="text-muted mb-2">
                        <strong>Category:</strong> <?= htmlspecialchars(ucfirst($tokenRecord['category'] ?? 'General')) ?>
                    </p>
                    
                    <?php if (!empty($tokenRecord['assigned_to_name'])): ?>
                    <p class="text-muted mb-2">
                        <strong>Assigned To:</strong> <?= htmlspecialchars($tokenRecord['assigned_to_name']) ?>
                        <?php if (!empty($tokenRecord['technician_phone'])): ?>
                            (<?= htmlspecialchars($tokenRecord['technician_phone']) ?>)
                        <?php endif; ?>
                    </p>
                    <?php endif; ?>
                    
                    <p class="text-muted mb-2">
                        <strong>Created:</strong> <?= date('M j, Y g:i A', strtotime($tokenRecord['ticket_created'])) ?>
                    </p>
                    
                    <?php if (!empty($tokenRecord['resolved_at'])): ?>
                    <p class="text-muted mb-0">
                        <strong>Resolved:</strong> <?= date('M j, Y g:i A', strtotime($tokenRecord['resolved_at'])) ?>
                    </p>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($tokenRecord['description'])): ?>
                <div class="mb-4">
                    <h6>Description</h6>
                    <p class="text-muted"><?= nl2br(htmlspecialchars($tokenRecord['description'])) ?></p>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($timeline)): ?>
                <div class="mb-4">
                    <h6>Recent Updates</h6>
                    <?php foreach ($timeline as $item): ?>
                    <div class="timeline-item <?= $item['type'] ?>">
                        <small class="text-muted"><?= date('M j, g:i A', strtotime($item['created_at'])) ?></small>
                        <?php if ($item['author']): ?>
                            <small class="text-primary"> - <?= htmlspecialchars($item['author']) ?></small>
                        <?php endif; ?>
                        <p class="mb-0 mt-1"><?= htmlspecialchars($item['content']) ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <?php if (strtolower($tokenRecord['status']) === 'resolved'): ?>
                <div class="border-top pt-4">
                    <h6 class="text-center mb-3">Rate Your Experience</h6>
                    
                    <?php if (!empty($tokenRecord['existing_rating'])): ?>
                    <div class="text-center">
                        <p class="mb-2">You rated this ticket:</p>
                        <div class="existing-rating">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <?= $i <= $tokenRecord['existing_rating'] ? '&#9733;' : '&#9734;' ?>
                            <?php endfor; ?>
                        </div>
                        <p class="text-muted mt-2">Thank you for your feedback!</p>
                    </div>
                    <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="token" value="<?= htmlspecialchars($tokenParam) ?>">
                        
                        <div class="text-center mb-3">
                            <div class="rating-stars">
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                <input type="radio" name="rating" id="star<?= $i ?>" value="<?= $i ?>">
                                <label for="star<?= $i ?>">&#9733;</label>
                                <?php endfor; ?>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <textarea name="feedback" class="form-control" rows="3" placeholder="Tell us about your experience (optional)..."></textarea>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Submit Rating</button>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
