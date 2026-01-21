<?php
// Ticket Wallboard - Premium visual display of all tickets and their progress

// Get all tickets with related data
$ticketsQuery = $db->query("
    SELECT t.*, 
           c.name as customer_name, c.phone as customer_phone, c.account_number,
           u.name as assigned_name,
           cr.name as creator_name,
           (SELECT COUNT(*) FROM ticket_comments tc WHERE tc.ticket_id = t.id) as comment_count,
           b.name as branch_name
    FROM tickets t
    LEFT JOIN customers c ON t.customer_id = c.id
    LEFT JOIN users u ON t.assigned_to = u.id
    LEFT JOIN users cr ON t.created_by = cr.id
    LEFT JOIN branches b ON t.branch_id = b.id
    ORDER BY 
        CASE t.priority 
            WHEN 'critical' THEN 1 
            WHEN 'high' THEN 2 
            WHEN 'medium' THEN 3 
            WHEN 'low' THEN 4 
            ELSE 5 
        END,
        t.created_at DESC
");
$allTickets = $ticketsQuery->fetchAll(PDO::FETCH_ASSOC);

// Group by status
$ticketsByStatus = [
    'open' => [],
    'in_progress' => [],
    'pending' => [],
    'resolved' => [],
    'closed' => []
];

foreach ($allTickets as $t) {
    $status = $t['status'] ?? 'open';
    if (isset($ticketsByStatus[$status])) {
        $ticketsByStatus[$status][] = $t;
    } else {
        $ticketsByStatus['open'][] = $t;
    }
}

$totalTickets = count($allTickets);
$openCount = count($ticketsByStatus['open']);
$inProgressCount = count($ticketsByStatus['in_progress']);
$pendingCount = count($ticketsByStatus['pending']);
$resolvedCount = count($ticketsByStatus['resolved']);
$closedCount = count($ticketsByStatus['closed']);

// Priority colors
$priorityColors = [
    'critical' => 'danger',
    'high' => 'warning',
    'medium' => 'info',
    'low' => 'secondary'
];

$priorityIcons = [
    'critical' => 'exclamation-triangle-fill',
    'high' => 'exclamation-circle-fill',
    'medium' => 'info-circle-fill',
    'low' => 'dash-circle'
];

$statusColors = [
    'open' => 'primary',
    'in_progress' => 'info',
    'pending' => 'warning',
    'resolved' => 'success',
    'closed' => 'secondary'
];

$statusIcons = [
    'open' => 'folder2-open',
    'in_progress' => 'gear-wide-connected',
    'pending' => 'hourglass-split',
    'resolved' => 'check-circle-fill',
    'closed' => 'lock-fill'
];
?>

<style>
    .wallboard-container {
        background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
        min-height: 100vh;
        padding: 20px;
        color: #fff;
    }
    
    .wallboard-header {
        text-align: center;
        margin-bottom: 30px;
        padding: 20px;
        background: rgba(255,255,255,0.05);
        border-radius: 15px;
        backdrop-filter: blur(10px);
    }
    
    .wallboard-title {
        font-size: 2.5rem;
        font-weight: 700;
        background: linear-gradient(90deg, #00d4ff, #7c3aed, #f472b6);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        margin-bottom: 10px;
    }
    
    .stats-row {
        display: flex;
        justify-content: center;
        gap: 30px;
        flex-wrap: wrap;
        margin-bottom: 30px;
    }
    
    .stat-card {
        background: rgba(255,255,255,0.1);
        border-radius: 15px;
        padding: 20px 30px;
        text-align: center;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255,255,255,0.1);
        min-width: 140px;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    }
    
    .stat-number {
        font-size: 2.5rem;
        font-weight: 700;
        line-height: 1;
    }
    
    .stat-label {
        font-size: 0.9rem;
        opacity: 0.8;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-top: 5px;
    }
    
    .column-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
        max-width: 1800px;
        margin: 0 auto;
    }
    
    .ticket-column {
        background: rgba(255,255,255,0.05);
        border-radius: 15px;
        padding: 15px;
        max-height: calc(100vh - 280px);
        overflow-y: auto;
    }
    
    .column-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 15px;
        border-radius: 10px;
        margin-bottom: 15px;
        font-weight: 600;
        font-size: 1.1rem;
    }
    
    .column-header.open { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
    .column-header.in_progress { background: linear-gradient(135deg, #06b6d4, #0891b2); }
    .column-header.pending { background: linear-gradient(135deg, #f59e0b, #d97706); }
    .column-header.resolved { background: linear-gradient(135deg, #10b981, #059669); }
    .column-header.closed { background: linear-gradient(135deg, #6b7280, #4b5563); }
    
    .ticket-card {
        background: rgba(255,255,255,0.08);
        border-radius: 12px;
        padding: 15px;
        margin-bottom: 12px;
        border-left: 4px solid;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        cursor: pointer;
    }
    
    .ticket-card:hover {
        transform: translateX(5px);
        box-shadow: 0 5px 20px rgba(0,0,0,0.2);
    }
    
    .ticket-card.priority-critical { border-left-color: #ef4444; background: rgba(239,68,68,0.1); }
    .ticket-card.priority-high { border-left-color: #f59e0b; background: rgba(245,158,11,0.1); }
    .ticket-card.priority-medium { border-left-color: #3b82f6; background: rgba(59,130,246,0.1); }
    .ticket-card.priority-low { border-left-color: #6b7280; }
    
    .ticket-title {
        font-weight: 600;
        font-size: 0.95rem;
        margin-bottom: 8px;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    
    .ticket-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        font-size: 0.75rem;
        opacity: 0.8;
        margin-bottom: 8px;
    }
    
    .ticket-meta-item {
        display: flex;
        align-items: center;
        gap: 4px;
    }
    
    .ticket-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 10px;
        padding-top: 10px;
        border-top: 1px solid rgba(255,255,255,0.1);
    }
    
    .priority-badge {
        padding: 3px 8px;
        border-radius: 4px;
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
    }
    
    .priority-badge.critical { background: #ef4444; color: white; }
    .priority-badge.high { background: #f59e0b; color: #1a1a2e; }
    .priority-badge.medium { background: #3b82f6; color: white; }
    .priority-badge.low { background: #6b7280; color: white; }
    
    .ticket-number {
        font-family: monospace;
        font-size: 0.8rem;
        opacity: 0.7;
    }
    
    .time-ago {
        font-size: 0.7rem;
        opacity: 0.6;
    }
    
    .assigned-avatar {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        background: linear-gradient(135deg, #7c3aed, #a855f7);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.65rem;
        font-weight: 600;
    }
    
    .no-tickets {
        text-align: center;
        padding: 30px;
        opacity: 0.5;
    }
    
    .refresh-btn {
        position: fixed;
        bottom: 30px;
        right: 30px;
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: linear-gradient(135deg, #7c3aed, #a855f7);
        border: none;
        color: white;
        font-size: 1.5rem;
        cursor: pointer;
        box-shadow: 0 5px 20px rgba(124,58,237,0.5);
        transition: transform 0.3s ease;
    }
    
    .refresh-btn:hover {
        transform: scale(1.1);
    }
    
    .live-indicator {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 5px 15px;
        background: rgba(16,185,129,0.2);
        border-radius: 20px;
        font-size: 0.85rem;
    }
    
    .live-dot {
        width: 8px;
        height: 8px;
        background: #10b981;
        border-radius: 50%;
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.5; transform: scale(1.2); }
    }
    
    .back-link {
        position: fixed;
        top: 20px;
        left: 20px;
        color: rgba(255,255,255,0.7);
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.9rem;
        transition: color 0.2s;
    }
    
    .back-link:hover {
        color: white;
    }
    
    @media (max-width: 768px) {
        .wallboard-title { font-size: 1.8rem; }
        .stats-row { gap: 15px; }
        .stat-card { padding: 15px 20px; min-width: 100px; }
        .stat-number { font-size: 1.8rem; }
        .column-container { grid-template-columns: 1fr; }
    }
</style>

<div class="wallboard-container">
    <a href="?page=tickets" class="back-link">
        <i class="bi bi-arrow-left"></i> Back to Tickets
    </a>
    
    <div class="wallboard-header">
        <h1 class="wallboard-title">Ticket Wallboard</h1>
        <div class="live-indicator">
            <span class="live-dot"></span>
            <span>Live Updates</span>
        </div>
    </div>
    
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-number" style="color: #3b82f6;"><?= $openCount ?></div>
            <div class="stat-label">Open</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" style="color: #06b6d4;"><?= $inProgressCount ?></div>
            <div class="stat-label">In Progress</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" style="color: #f59e0b;"><?= $pendingCount ?></div>
            <div class="stat-label">Pending</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" style="color: #10b981;"><?= $resolvedCount ?></div>
            <div class="stat-label">Resolved</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" style="color: #6b7280;"><?= $closedCount ?></div>
            <div class="stat-label">Closed</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" style="color: #a855f7;"><?= $totalTickets ?></div>
            <div class="stat-label">Total</div>
        </div>
    </div>
    
    <div class="column-container">
        <?php foreach (['open', 'in_progress', 'pending', 'resolved', 'closed'] as $status): ?>
        <div class="ticket-column">
            <div class="column-header <?= $status ?>">
                <span><i class="bi bi-<?= $statusIcons[$status] ?> me-2"></i><?= ucwords(str_replace('_', ' ', $status)) ?></span>
                <span class="badge bg-light text-dark"><?= count($ticketsByStatus[$status]) ?></span>
            </div>
            
            <?php if (empty($ticketsByStatus[$status])): ?>
            <div class="no-tickets">
                <i class="bi bi-inbox fs-2"></i>
                <div class="mt-2">No tickets</div>
            </div>
            <?php else: ?>
            <?php foreach ($ticketsByStatus[$status] as $t): 
                $priority = $t['priority'] ?? 'medium';
                $createdAt = new DateTime($t['created_at']);
                $now = new DateTime();
                $diff = $now->diff($createdAt);
                if ($diff->days > 0) {
                    $timeAgo = $diff->days . 'd ago';
                } elseif ($diff->h > 0) {
                    $timeAgo = $diff->h . 'h ago';
                } else {
                    $timeAgo = $diff->i . 'm ago';
                }
            ?>
            <div class="ticket-card priority-<?= $priority ?>" onclick="window.location.href='?page=tickets&action=view&id=<?= $t['id'] ?>'">
                <div class="ticket-title"><?= htmlspecialchars($t['subject'] ?? $t['title'] ?? 'No Subject') ?></div>
                <div class="ticket-meta">
                    <?php if (!empty($t['customer_name'])): ?>
                    <span class="ticket-meta-item">
                        <i class="bi bi-person"></i> <?= htmlspecialchars($t['customer_name']) ?>
                    </span>
                    <?php endif; ?>
                    <?php if (!empty($t['category'])): ?>
                    <span class="ticket-meta-item">
                        <i class="bi bi-tag"></i> <?= htmlspecialchars($t['category']) ?>
                    </span>
                    <?php endif; ?>
                    <?php if (!empty($t['comment_count']) && $t['comment_count'] > 0): ?>
                    <span class="ticket-meta-item">
                        <i class="bi bi-chat-dots"></i> <?= $t['comment_count'] ?>
                    </span>
                    <?php endif; ?>
                </div>
                <div class="ticket-footer">
                    <div class="d-flex align-items-center gap-2">
                        <span class="priority-badge <?= $priority ?>"><?= ucfirst($priority) ?></span>
                        <span class="ticket-number">#<?= $t['id'] ?></span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <?php if (!empty($t['assigned_name'])): ?>
                        <div class="assigned-avatar" title="<?= htmlspecialchars($t['assigned_name']) ?>">
                            <?= strtoupper(substr($t['assigned_name'], 0, 2)) ?>
                        </div>
                        <?php endif; ?>
                        <span class="time-ago"><?= $timeAgo ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    
    <button class="refresh-btn" onclick="location.reload()" title="Refresh">
        <i class="bi bi-arrow-clockwise"></i>
    </button>
</div>

<script>
// Auto-refresh every 30 seconds
setInterval(() => {
    location.reload();
}, 30000);
</script>
