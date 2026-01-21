<?php
// Ticket Wallboard - Premium visual display of all tickets, LOS alerts, and new ONU discoveries
// Opens in fullscreen mode without header/sidebar

require_once __DIR__ . '/../config/database.php';
session_start();

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$db = Database::getConnection();

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
    WHERE t.status NOT IN ('closed')
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
    'resolved' => []
];

foreach ($allTickets as $t) {
    $status = $t['status'] ?? 'open';
    if (isset($ticketsByStatus[$status])) {
        $ticketsByStatus[$status][] = $t;
    } else {
        $ticketsByStatus['open'][] = $t;
    }
}

// Get LOS ONUs (Loss of Signal)
$losQuery = $db->query("
    SELECT o.*, olt.name as olt_name, c.name as customer_name, c.phone as customer_phone
    FROM huawei_onus o
    LEFT JOIN huawei_olts olt ON o.olt_id = olt.id
    LEFT JOIN customers c ON o.customer_id = c.id
    WHERE o.status = 'los'
    ORDER BY o.updated_at DESC
    LIMIT 50
");
$losOnus = $losQuery->fetchAll(PDO::FETCH_ASSOC);

// Get newly discovered ONUs (last 24 hours, not authorized)
$newOnuQuery = $db->query("
    SELECT dl.*, olt.name as olt_name
    FROM onu_discovery_log dl
    LEFT JOIN huawei_olts olt ON dl.olt_id = olt.id
    WHERE dl.last_seen_at > NOW() - INTERVAL '24 hours'
    ORDER BY dl.last_seen_at DESC
    LIMIT 30
");
$newOnus = $newOnuQuery->fetchAll(PDO::FETCH_ASSOC);

// Stats
$totalTickets = count($allTickets);
$openCount = count($ticketsByStatus['open']);
$inProgressCount = count($ticketsByStatus['in_progress']);
$pendingCount = count($ticketsByStatus['pending']);
$resolvedCount = count($ticketsByStatus['resolved']);
$losCount = count($losOnus);
$newOnuCount = count($newOnus);

$statusLabels = [
    'open' => 'Open',
    'in_progress' => 'In Progress',
    'pending' => 'Pending',
    'resolved' => 'Resolved'
];

function wallboardTimeAgo($datetime) {
    if (empty($datetime)) return '';
    $now = new DateTime();
    $then = new DateTime($datetime);
    $diff = $now->diff($then);
    
    if ($diff->days > 0) return $diff->days . 'd';
    if ($diff->h > 0) return $diff->h . 'h';
    if ($diff->i > 0) return $diff->i . 'm';
    return 'now';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Operations Wallboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --wb-dark: #0d1117;
            --wb-darker: #010409;
            --wb-card: #161b22;
            --wb-border: #30363d;
            --wb-text: #c9d1d9;
            --wb-muted: #8b949e;
            --wb-danger: #f85149;
            --wb-warning: #d29922;
            --wb-success: #3fb950;
            --wb-info: #58a6ff;
            --wb-primary: #1f6feb;
        }
        
        * { box-sizing: border-box; }
        
        html, body {
            margin: 0;
            padding: 0;
            height: 100vh;
            overflow: hidden;
            background: var(--wb-darker);
            color: var(--wb-text);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .wallboard-container {
            display: flex;
            flex-direction: column;
            height: 100vh;
            padding: 10px;
            gap: 10px;
        }
        
        .wb-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 20px;
            background: var(--wb-card);
            border-radius: 12px;
            border: 1px solid var(--wb-border);
        }
        
        .wb-title {
            font-size: 1.5rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .wb-stats {
            display: flex;
            gap: 20px;
        }
        
        .wb-stat {
            text-align: center;
            padding: 5px 15px;
            border-radius: 8px;
            background: var(--wb-dark);
        }
        
        .wb-stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            line-height: 1;
        }
        
        .wb-stat-label {
            font-size: 0.7rem;
            text-transform: uppercase;
            color: var(--wb-muted);
            margin-top: 2px;
        }
        
        .wb-main {
            flex: 1;
            display: flex;
            gap: 10px;
            overflow: hidden;
        }
        
        .wb-alerts-panel {
            width: 300px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .wb-alert-section {
            flex: 1;
            background: var(--wb-card);
            border-radius: 12px;
            border: 1px solid var(--wb-border);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .wb-alert-header {
            padding: 12px 15px;
            font-weight: 600;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--wb-border);
        }
        
        .wb-alert-header.los { background: rgba(248, 81, 73, 0.15); color: var(--wb-danger); }
        .wb-alert-header.new-onu { background: rgba(88, 166, 255, 0.15); color: var(--wb-info); }
        
        .wb-alert-list {
            flex: 1;
            overflow-y: auto;
            padding: 8px;
        }
        
        .wb-alert-item {
            padding: 10px 12px;
            margin-bottom: 6px;
            background: var(--wb-dark);
            border-radius: 8px;
            border-left: 3px solid;
            font-size: 0.85rem;
        }
        
        .wb-alert-item.los { border-color: var(--wb-danger); }
        .wb-alert-item.new-onu { border-color: var(--wb-info); }
        
        .wb-alert-title {
            font-weight: 600;
            margin-bottom: 3px;
        }
        
        .wb-alert-meta {
            color: var(--wb-muted);
            font-size: 0.75rem;
        }
        
        .wb-tickets-panel {
            flex: 1;
            display: flex;
            gap: 10px;
            overflow: hidden;
        }
        
        .wb-column {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: var(--wb-card);
            border-radius: 12px;
            border: 1px solid var(--wb-border);
            overflow: hidden;
            min-width: 0;
        }
        
        .wb-column-header {
            padding: 12px 15px;
            font-weight: 600;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--wb-border);
        }
        
        .wb-column-header.open { background: rgba(31, 111, 235, 0.15); color: var(--wb-info); }
        .wb-column-header.in_progress { background: rgba(88, 166, 255, 0.15); color: var(--wb-info); }
        .wb-column-header.pending { background: rgba(210, 153, 34, 0.15); color: var(--wb-warning); }
        .wb-column-header.resolved { background: rgba(63, 185, 80, 0.15); color: var(--wb-success); }
        
        .wb-column-count {
            background: var(--wb-dark);
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.85rem;
        }
        
        .wb-column-body {
            flex: 1;
            overflow-y: auto;
            padding: 8px;
        }
        
        .wb-ticket {
            padding: 12px;
            margin-bottom: 8px;
            background: var(--wb-dark);
            border-radius: 8px;
            border-left: 4px solid;
            transition: transform 0.15s, box-shadow 0.15s;
        }
        
        .wb-ticket:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }
        
        .wb-ticket.critical { border-color: var(--wb-danger); }
        .wb-ticket.high { border-color: var(--wb-warning); }
        .wb-ticket.medium { border-color: var(--wb-info); }
        .wb-ticket.low { border-color: var(--wb-muted); }
        
        .wb-ticket-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 6px;
        }
        
        .wb-ticket-id {
            font-size: 0.7rem;
            color: var(--wb-muted);
        }
        
        .wb-ticket-priority {
            font-size: 0.65rem;
            padding: 2px 6px;
            border-radius: 4px;
            text-transform: uppercase;
            font-weight: 600;
        }
        
        .wb-ticket-priority.critical { background: var(--wb-danger); color: white; }
        .wb-ticket-priority.high { background: var(--wb-warning); color: #000; }
        .wb-ticket-priority.medium { background: var(--wb-info); color: white; }
        .wb-ticket-priority.low { background: var(--wb-muted); color: white; }
        
        .wb-ticket-subject {
            font-weight: 600;
            font-size: 0.85rem;
            margin-bottom: 6px;
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .wb-ticket-customer {
            font-size: 0.8rem;
            color: var(--wb-text);
            margin-bottom: 4px;
        }
        
        .wb-ticket-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.75rem;
            color: var(--wb-muted);
            margin-top: 6px;
        }
        
        .wb-ticket-assignee {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .wb-ticket-age {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .wb-refresh-timer {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--wb-muted);
            font-size: 0.8rem;
        }
        
        .wb-countdown {
            font-weight: 600;
            color: var(--wb-text);
        }
        
        .pulse-dot {
            width: 8px;
            height: 8px;
            background: var(--wb-success);
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(1.2); }
        }
        
        .wb-empty {
            text-align: center;
            padding: 30px;
            color: var(--wb-muted);
        }
        
        .wb-empty i {
            font-size: 2rem;
            margin-bottom: 10px;
            opacity: 0.5;
        }
        
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: var(--wb-darker); }
        ::-webkit-scrollbar-thumb { background: var(--wb-border); border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--wb-muted); }
    </style>
</head>
<body>
    <div class="wallboard-container">
        <div class="wb-header">
            <div class="wb-title">
                <i class="bi bi-grid-3x3-gap-fill"></i>
                Operations Wallboard
            </div>
            
            <div class="wb-stats">
                <div class="wb-stat">
                    <div class="wb-stat-value" style="color: var(--wb-danger)"><?= $losCount ?></div>
                    <div class="wb-stat-label">LOS Alerts</div>
                </div>
                <div class="wb-stat">
                    <div class="wb-stat-value" style="color: var(--wb-info)"><?= $newOnuCount ?></div>
                    <div class="wb-stat-label">New ONUs</div>
                </div>
                <div class="wb-stat">
                    <div class="wb-stat-value" style="color: var(--wb-primary)"><?= $openCount ?></div>
                    <div class="wb-stat-label">Open</div>
                </div>
                <div class="wb-stat">
                    <div class="wb-stat-value" style="color: var(--wb-warning)"><?= $pendingCount ?></div>
                    <div class="wb-stat-label">Pending</div>
                </div>
                <div class="wb-stat">
                    <div class="wb-stat-value" style="color: var(--wb-success)"><?= $resolvedCount ?></div>
                    <div class="wb-stat-label">Resolved</div>
                </div>
            </div>
            
            <div class="wb-refresh-timer">
                <div class="pulse-dot"></div>
                <span>Refresh in <span class="wb-countdown" id="countdown">30</span>s</span>
            </div>
        </div>
        
        <div class="wb-main">
            <div class="wb-alerts-panel">
                <div class="wb-alert-section">
                    <div class="wb-alert-header los">
                        <span><i class="bi bi-exclamation-triangle-fill me-2"></i>LOS Alerts</span>
                        <span class="wb-column-count"><?= $losCount ?></span>
                    </div>
                    <div class="wb-alert-list">
                        <?php if (empty($losOnus)): ?>
                        <div class="wb-empty">
                            <i class="bi bi-check-circle"></i>
                            <div>No LOS alerts</div>
                        </div>
                        <?php else: ?>
                        <?php foreach ($losOnus as $onu): ?>
                        <div class="wb-alert-item los">
                            <div class="wb-alert-title">
                                <?= htmlspecialchars($onu['name'] ?: $onu['sn']) ?>
                            </div>
                            <div class="wb-alert-meta">
                                <?= htmlspecialchars($onu['olt_name'] ?? 'Unknown OLT') ?>
                                <br>
                                <?php if (!empty($onu['customer_name'])): ?>
                                <i class="bi bi-person"></i> <?= htmlspecialchars($onu['customer_name']) ?>
                                <?php else: ?>
                                Port <?= $onu['slot'] ?>/<?= $onu['port'] ?>
                                <?php endif; ?>
                                <br>
                                <i class="bi bi-clock"></i> <?= wallboardTimeAgo($onu['updated_at']) ?> ago
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="wb-alert-section">
                    <div class="wb-alert-header new-onu">
                        <span><i class="bi bi-broadcast-pin me-2"></i>New ONUs</span>
                        <span class="wb-column-count"><?= $newOnuCount ?></span>
                    </div>
                    <div class="wb-alert-list">
                        <?php if (empty($newOnus)): ?>
                        <div class="wb-empty">
                            <i class="bi bi-router"></i>
                            <div>No new discoveries</div>
                        </div>
                        <?php else: ?>
                        <?php foreach ($newOnus as $onu): ?>
                        <div class="wb-alert-item new-onu">
                            <div class="wb-alert-title">
                                <?= htmlspecialchars($onu['serial_number']) ?>
                            </div>
                            <div class="wb-alert-meta">
                                <?= htmlspecialchars($onu['olt_name'] ?? 'Unknown OLT') ?>
                                <br>
                                Port <?= $onu['frame'] ?? 0 ?>/<?= $onu['slot'] ?>/<?= $onu['port'] ?>
                                <br>
                                <i class="bi bi-clock"></i> <?= wallboardTimeAgo($onu['last_seen_at']) ?> ago
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="wb-tickets-panel">
                <?php foreach ($ticketsByStatus as $status => $tickets): ?>
                <div class="wb-column">
                    <div class="wb-column-header <?= $status ?>">
                        <span><?= $statusLabels[$status] ?? ucfirst($status) ?></span>
                        <span class="wb-column-count"><?= count($tickets) ?></span>
                    </div>
                    <div class="wb-column-body">
                        <?php if (empty($tickets)): ?>
                        <div class="wb-empty">
                            <i class="bi bi-inbox"></i>
                            <div>No tickets</div>
                        </div>
                        <?php else: ?>
                        <?php foreach ($tickets as $ticket): ?>
                        <div class="wb-ticket <?= $ticket['priority'] ?? 'medium' ?>">
                            <div class="wb-ticket-header">
                                <span class="wb-ticket-id">#<?= $ticket['id'] ?></span>
                                <span class="wb-ticket-priority <?= $ticket['priority'] ?? 'medium' ?>">
                                    <?= $ticket['priority'] ?? 'medium' ?>
                                </span>
                            </div>
                            <div class="wb-ticket-subject"><?= htmlspecialchars($ticket['subject']) ?></div>
                            <?php if (!empty($ticket['customer_name'])): ?>
                            <div class="wb-ticket-customer">
                                <i class="bi bi-person"></i> <?= htmlspecialchars($ticket['customer_name']) ?>
                            </div>
                            <?php endif; ?>
                            <div class="wb-ticket-footer">
                                <div class="wb-ticket-assignee">
                                    <?php if (!empty($ticket['assigned_name'])): ?>
                                    <i class="bi bi-person-check"></i>
                                    <?= htmlspecialchars($ticket['assigned_name']) ?>
                                    <?php else: ?>
                                    <i class="bi bi-person-dash"></i>
                                    Unassigned
                                    <?php endif; ?>
                                </div>
                                <div class="wb-ticket-age">
                                    <i class="bi bi-clock"></i>
                                    <?= wallboardTimeAgo($ticket['created_at']) ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <script>
        let countdown = 30;
        const countdownEl = document.getElementById('countdown');
        
        setInterval(() => {
            countdown--;
            countdownEl.textContent = countdown;
            if (countdown <= 0) {
                location.reload();
            }
        }, 1000);
    </script>
</body>
</html>
