<?php
require_once __DIR__ . '/../config/database.php';

$db = Database::getConnection();

// Ticket Stats
$ticketStats = $db->query("
    SELECT 
        COUNT(*) FILTER (WHERE status = 'open') as open_count,
        COUNT(*) FILTER (WHERE status = 'in_progress') as in_progress,
        COUNT(*) FILTER (WHERE status = 'pending') as pending,
        COUNT(*) FILTER (WHERE priority = 'critical' AND status NOT IN ('closed', 'resolved')) as critical_count,
        COUNT(*) FILTER (WHERE assigned_to IS NULL AND status NOT IN ('closed', 'resolved')) as unassigned,
        COUNT(*) FILTER (WHERE DATE(created_at) = CURRENT_DATE) as created_today,
        COUNT(*) FILTER (WHERE status = 'closed' AND DATE(updated_at) = CURRENT_DATE) as closed_today
    FROM tickets
")->fetch(PDO::FETCH_ASSOC);

// LOS ONUs
$losOnus = $db->query("
    SELECT o.name, o.sn, CONCAT(o.frame, '/', o.slot, '/', o.port) as fsp, 
           olt.name as olt_name, c.name as customer_name, o.updated_at
    FROM huawei_onus o
    LEFT JOIN huawei_olts olt ON o.olt_id = olt.id
    LEFT JOIN customers c ON o.customer_id = c.id
    WHERE o.status = 'los'
    ORDER BY o.updated_at DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// New/Unconfigured ONUs
$newOnus = [];
try {
    $tableCheck = $db->query("SELECT to_regclass('public.huawei_unconfigured_onus')")->fetchColumn();
    if ($tableCheck) {
        $newOnus = $db->query("
            SELECT sn, equipment_id, olt_id, fsp, discovered_at,
                   (SELECT name FROM huawei_olts WHERE id = olt_id) as olt_name
            FROM huawei_unconfigured_onus 
            WHERE status = 'new'
            ORDER BY discovered_at DESC
            LIMIT 8
        ")->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {}

// Tickets needing attention (oldest open)
$oldestTickets = $db->query("
    SELECT t.id, t.subject, t.priority, t.status, t.created_at,
           c.name as customer_name, u.name as assigned_name
    FROM tickets t
    LEFT JOIN customers c ON t.customer_id = c.id
    LEFT JOIN users u ON t.assigned_to = u.id
    WHERE t.status NOT IN ('closed', 'resolved')
    ORDER BY t.created_at ASC
    LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);

// Top ticket solvers today
$topSolvers = $db->query("
    SELECT u.name, COUNT(*) as solved_count
    FROM tickets t
    JOIN users u ON t.assigned_to = u.id
    WHERE t.status IN ('closed', 'resolved') 
    AND DATE(t.updated_at) = CURRENT_DATE
    GROUP BY u.id, u.name
    ORDER BY solved_count DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Today's Attendance
$todayAttendance = $db->query("
    SELECT 
        e.id, e.name, e.position,
        a.clock_in, a.clock_out,
        CASE 
            WHEN a.clock_in IS NOT NULL AND a.clock_out IS NULL THEN 'present'
            WHEN a.clock_in IS NOT NULL AND a.clock_out IS NOT NULL THEN 'left'
            ELSE 'absent'
        END as attendance_status
    FROM employees e
    LEFT JOIN attendance a ON e.id = a.employee_id AND a.date = CURRENT_DATE
    WHERE e.employment_status = 'active'
    ORDER BY 
        CASE 
            WHEN a.clock_in IS NOT NULL AND a.clock_out IS NULL THEN 1
            WHEN a.clock_in IS NOT NULL AND a.clock_out IS NOT NULL THEN 2
            ELSE 3
        END,
        e.name
")->fetchAll(PDO::FETCH_ASSOC);

$presentCount = 0;
foreach ($todayAttendance as $att) {
    if ($att['attendance_status'] === 'present' || $att['attendance_status'] === 'left') {
        $presentCount++;
    }
}

function timeAgo($datetime) {
    $now = new DateTime();
    $past = new DateTime($datetime);
    $diff = $now->diff($past);
    
    if ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0) return $diff->h . ' hr' . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0) return $diff->i . ' min ago';
    return 'just now';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Operations Wallboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        html, body {
            height: 100vh;
            overflow: hidden;
            background: #0d1117;
            color: #fff;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }
        
        .wallboard {
            display: grid;
            grid-template-columns: 1fr 1.5fr 1fr;
            grid-template-rows: auto 1fr;
            gap: 12px;
            height: 100vh;
            padding: 12px;
        }
        
        .header {
            grid-column: 1 / -1;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 16px;
            background: rgba(255,255,255,0.03);
            border-radius: 8px;
        }
        
        .header h1 {
            font-size: 1.3rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .header h1 i { color: #58a6ff; }
        
        .live-badge {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.8rem;
            color: #7d8590;
        }
        
        .live-dot {
            width: 8px;
            height: 8px;
            background: #3fb950;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }
        
        .panel {
            background: rgba(255,255,255,0.03);
            border-radius: 8px;
            padding: 12px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.06);
        }
        
        .panel-title {
            font-size: 0.7rem;
            color: #7d8590;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .left-column {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .kpi-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }
        
        .kpi-box {
            background: rgba(0,0,0,0.3);
            border-radius: 8px;
            padding: 12px;
            text-align: center;
        }
        
        .kpi-value {
            font-size: 2.2rem;
            font-weight: 700;
            line-height: 1;
        }
        
        .kpi-label {
            font-size: 0.65rem;
            color: #7d8590;
            text-transform: uppercase;
            margin-top: 4px;
        }
        
        .kpi-box.highlight {
            border: 2px solid;
        }
        
        .kpi-box.danger { border-color: #f85149; }
        .kpi-box.warning { border-color: #d29922; }
        .kpi-box.success { border-color: #3fb950; }
        
        .color-danger { color: #f85149; }
        .color-warning { color: #d29922; }
        .color-success { color: #3fb950; }
        .color-info { color: #58a6ff; }
        .color-muted { color: #7d8590; }
        
        .table-scroll {
            flex: 1;
            overflow-y: auto;
        }
        
        .data-table {
            width: 100%;
            font-size: 0.75rem;
        }
        
        .data-table th {
            text-align: left;
            color: #7d8590;
            font-weight: 500;
            padding: 6px 8px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            font-size: 0.65rem;
            text-transform: uppercase;
        }
        
        .data-table td {
            padding: 8px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            vertical-align: middle;
        }
        
        .data-table tr:hover {
            background: rgba(255,255,255,0.03);
        }
        
        .priority-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.6rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .priority-critical { background: rgba(248,81,73,0.2); color: #f85149; }
        .priority-high { background: rgba(210,153,34,0.2); color: #d29922; }
        .priority-medium { background: rgba(88,166,255,0.2); color: #58a6ff; }
        .priority-low { background: rgba(125,133,144,0.2); color: #7d8590; }
        
        .status-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.6rem;
            background: rgba(88,166,255,0.2);
            color: #58a6ff;
        }
        
        .truncate {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 180px;
        }
        
        .alert-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 10px;
            background: rgba(248,81,73,0.1);
            border-left: 3px solid #f85149;
            border-radius: 6px;
            margin-bottom: 6px;
        }
        
        .alert-row.new-onu {
            background: rgba(88,166,255,0.1);
            border-left-color: #58a6ff;
        }
        
        .alert-icon {
            font-size: 1.2rem;
        }
        
        .alert-info {
            flex: 1;
            min-width: 0;
        }
        
        .alert-title {
            font-weight: 600;
            font-size: 0.8rem;
        }
        
        .alert-meta {
            font-size: 0.65rem;
            color: #7d8590;
        }
        
        .alert-sn {
            font-family: monospace;
            font-size: 0.7rem;
            color: #7d8590;
        }
        
        .att-list {
            display: flex;
            flex-direction: column;
            gap: 4px;
            flex: 1;
            overflow-y: auto;
        }
        
        .att-row {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 8px;
            background: rgba(0,0,0,0.2);
            border-radius: 6px;
            border-left: 3px solid;
        }
        
        .att-row.present { border-color: #3fb950; }
        .att-row.left { border-color: #58a6ff; }
        .att-row.absent { border-color: #484f58; opacity: 0.6; }
        
        .att-avatar {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.65rem;
            flex-shrink: 0;
        }
        
        .att-avatar.present { background: rgba(63,185,80,0.3); color: #3fb950; }
        .att-avatar.left { background: rgba(88,166,255,0.3); color: #58a6ff; }
        .att-avatar.absent { background: rgba(72,79,88,0.3); color: #484f58; }
        
        .att-name {
            flex: 1;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .att-role {
            font-size: 0.6rem;
            color: #7d8590;
        }
        
        .solver-row {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 0;
        }
        
        .solver-avatar {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: rgba(88,166,255,0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.6rem;
            font-weight: 600;
            color: #58a6ff;
        }
        
        .solver-name {
            flex: 1;
            font-size: 0.75rem;
        }
        
        .solver-count {
            font-weight: 700;
            font-size: 0.85rem;
            color: #3fb950;
        }
        
        .empty-state {
            text-align: center;
            color: #484f58;
            padding: 20px;
            font-size: 0.8rem;
        }
        
        .empty-state i {
            font-size: 1.5rem;
            margin-bottom: 8px;
            display: block;
        }

        .right-column {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
    </style>
</head>
<body>
    <div class="wallboard">
        <div class="header">
            <h1><i class="bi bi-display"></i> Live Operations</h1>
            <div class="live-badge">
                <div class="live-dot"></div>
                Auto-refresh 30s
            </div>
        </div>
        
        <div class="left-column">
            <div class="panel">
                <div class="panel-title"><i class="bi bi-ticket"></i> Tickets Today</div>
                <div class="kpi-grid">
                    <div class="kpi-box">
                        <div class="kpi-value color-info"><?= $ticketStats['created_today'] ?? 0 ?></div>
                        <div class="kpi-label">Created</div>
                    </div>
                    <div class="kpi-box">
                        <div class="kpi-value color-success"><?= $ticketStats['closed_today'] ?? 0 ?></div>
                        <div class="kpi-label">Solved</div>
                    </div>
                    <div class="kpi-box">
                        <div class="kpi-value"><?= $ticketStats['open_count'] ?? 0 ?></div>
                        <div class="kpi-label">Open</div>
                    </div>
                    <div class="kpi-box <?= ($ticketStats['unassigned'] ?? 0) > 0 ? 'highlight danger' : '' ?>">
                        <div class="kpi-value color-danger"><?= $ticketStats['unassigned'] ?? 0 ?></div>
                        <div class="kpi-label">Unassigned</div>
                    </div>
                </div>
            </div>
            
            <div class="panel" style="flex:1;">
                <div class="panel-title"><i class="bi bi-wifi-off"></i> LOS Alerts <span class="color-danger">(<?= count($losOnus) ?>)</span></div>
                <div class="table-scroll">
                    <?php if (empty($losOnus)): ?>
                        <div class="empty-state">
                            <i class="bi bi-check-circle color-success"></i>
                            No LOS alerts
                        </div>
                    <?php else: ?>
                        <?php foreach ($losOnus as $onu): ?>
                        <div class="alert-row">
                            <i class="bi bi-exclamation-triangle-fill alert-icon color-danger"></i>
                            <div class="alert-info">
                                <div class="alert-title"><?= htmlspecialchars($onu['customer_name'] ?: $onu['name'] ?: 'Unknown') ?></div>
                                <div class="alert-sn"><?= htmlspecialchars($onu['sn']) ?></div>
                                <div class="alert-meta"><?= htmlspecialchars($onu['olt_name'] ?? '') ?> &bull; <?= $onu['fsp'] ?? '' ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="panel">
            <div class="panel-title"><i class="bi bi-clock-history"></i> Tickets Needing Attention</div>
            <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Subject</th>
                            <th>Priority</th>
                            <th>Assignee</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($oldestTickets)): ?>
                        <tr><td colspan="5" class="empty-state">No open tickets</td></tr>
                        <?php else: ?>
                        <?php foreach ($oldestTickets as $ticket): ?>
                        <tr>
                            <td>#<?= $ticket['id'] ?></td>
                            <td class="truncate"><?= htmlspecialchars($ticket['subject']) ?></td>
                            <td><span class="priority-badge priority-<?= $ticket['priority'] ?>"><?= ucfirst($ticket['priority']) ?></span></td>
                            <td><?= htmlspecialchars($ticket['assigned_name'] ?? '-') ?></td>
                            <td class="color-muted"><?= timeAgo($ticket['created_at']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="right-column">
            <div class="panel">
                <div class="panel-title"><i class="bi bi-router"></i> New ONUs <span class="color-info">(<?= count($newOnus) ?>)</span></div>
                <div class="table-scroll">
                    <?php if (empty($newOnus)): ?>
                        <div class="empty-state">
                            <i class="bi bi-inbox color-muted"></i>
                            No new ONUs discovered
                        </div>
                    <?php else: ?>
                        <?php foreach ($newOnus as $onu): ?>
                        <div class="alert-row new-onu">
                            <i class="bi bi-plus-circle-fill alert-icon color-info"></i>
                            <div class="alert-info">
                                <div class="alert-title"><?= htmlspecialchars($onu['equipment_id'] ?? 'Unknown Model') ?></div>
                                <div class="alert-sn"><?= htmlspecialchars($onu['sn']) ?></div>
                                <div class="alert-meta"><?= htmlspecialchars($onu['olt_name'] ?? '') ?> &bull; <?= $onu['fsp'] ?? '' ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="panel" style="flex:1;">
                <div class="panel-title"><i class="bi bi-people"></i> Team Attendance <span class="color-success">(<?= $presentCount ?>/<?= count($todayAttendance) ?>)</span></div>
                <div class="att-list">
                    <?php foreach ($todayAttendance as $att): ?>
                    <div class="att-row <?= $att['attendance_status'] ?>">
                        <div class="att-avatar <?= $att['attendance_status'] ?>">
                            <?= strtoupper(substr($att['name'], 0, 2)) ?>
                        </div>
                        <div class="att-name"><?= htmlspecialchars($att['name']) ?></div>
                        <div class="att-role"><?= htmlspecialchars($att['position'] ?? '') ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <?php if (!empty($topSolvers)): ?>
            <div class="panel">
                <div class="panel-title"><i class="bi bi-trophy"></i> Top Solvers Today</div>
                <?php foreach ($topSolvers as $solver): ?>
                <div class="solver-row">
                    <div class="solver-avatar"><?= strtoupper(substr($solver['name'], 0, 2)) ?></div>
                    <div class="solver-name"><?= htmlspecialchars($solver['name']) ?></div>
                    <div class="solver-count"><?= $solver['solved_count'] ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        setInterval(() => location.reload(), 30000);
    </script>
</body>
</html>
