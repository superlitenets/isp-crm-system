<?php
require_once __DIR__ . '/../config/database.php';

$db = Database::getConnection();

// Ticket Stats
$ticketStats = $db->query("
    SELECT 
        COUNT(*) FILTER (WHERE status = 'open') as open_count,
        COUNT(*) FILTER (WHERE status = 'in_progress') as in_progress,
        COUNT(*) FILTER (WHERE priority = 'critical' AND status NOT IN ('closed', 'resolved')) as critical_count,
        COUNT(*) FILTER (WHERE status = 'closed' AND DATE(updated_at) = CURRENT_DATE) as closed_today
    FROM tickets
")->fetch(PDO::FETCH_ASSOC);

// ONU Stats
$onuStats = $db->query("
    SELECT 
        COUNT(*) FILTER (WHERE status = 'online') as online_count,
        COUNT(*) FILTER (WHERE status = 'los') as los_count,
        COUNT(*) FILTER (WHERE status = 'offline' OR status IS NULL) as offline_count
    FROM huawei_onus
")->fetch(PDO::FETCH_ASSOC);

// New/Unconfigured ONUs - check if table exists
$newOnuCount = 0;
try {
    $tableCheck = $db->query("SELECT to_regclass('public.huawei_unconfigured_onus')")->fetchColumn();
    if ($tableCheck) {
        $newOnuCount = $db->query("SELECT COUNT(*) as cnt FROM huawei_unconfigured_onus WHERE status = 'new'")->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0;
    }
} catch (Exception $e) {
    $newOnuCount = 0;
}

// Customer Stats
$customerStats = $db->query("
    SELECT 
        COUNT(*) as total,
        COUNT(*) FILTER (WHERE connection_status = 'active') as active_count,
        COUNT(*) FILTER (WHERE connection_status = 'suspended') as suspended_count
    FROM customers
")->fetch(PDO::FETCH_ASSOC);

// Today's Attendance
$todayAttendance = $db->query("
    SELECT 
        e.id, e.name, e.position,
        a.clock_in, a.clock_out, a.status,
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
$absentCount = 0;
foreach ($todayAttendance as $att) {
    if ($att['attendance_status'] === 'present' || $att['attendance_status'] === 'left') {
        $presentCount++;
    } else {
        $absentCount++;
    }
}

// Recent Critical/High Tickets
$urgentTickets = $db->query("
    SELECT t.id, t.subject, t.priority, t.status, t.created_at,
           c.name as customer_name, u.name as assigned_name
    FROM tickets t
    LEFT JOIN customers c ON t.customer_id = c.id
    LEFT JOIN users u ON t.assigned_to = u.id
    WHERE t.priority IN ('critical', 'high') 
    AND t.status NOT IN ('closed', 'resolved')
    ORDER BY 
        CASE t.priority WHEN 'critical' THEN 1 ELSE 2 END,
        t.created_at DESC
    LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC);

// LOS ONUs
$losOnus = $db->query("
    SELECT o.name, o.sn, olt.name as olt_name, c.name as customer_name
    FROM huawei_onus o
    LEFT JOIN huawei_olts olt ON o.olt_id = olt.id
    LEFT JOIN customers c ON o.customer_id = c.id
    WHERE o.status = 'los'
    ORDER BY o.updated_at DESC
    LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC);
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
            background: linear-gradient(135deg, #0f0f23 0%, #1a1a3e 50%, #0d1b2a 100%);
            color: #fff;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }
        
        .wallboard {
            display: flex;
            flex-direction: column;
            height: 100vh;
            padding: 15px;
            gap: 15px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 20px;
            background: rgba(255,255,255,0.05);
            border-radius: 10px;
            flex-shrink: 0;
        }
        
        .header h1 {
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        .header h1 i { color: #00d4ff; }
        
        .live-badge {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            color: #8b949e;
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
        
        .stats-row {
            display: flex;
            gap: 15px;
            flex-shrink: 0;
        }
        
        .stat-card {
            flex: 1;
            background: rgba(255,255,255,0.05);
            border-radius: 10px;
            padding: 15px 20px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .stat-card h3 {
            font-size: 0.75rem;
            color: #8b949e;
            text-transform: uppercase;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .stat-row {
            display: flex;
            justify-content: space-between;
            gap: 10px;
        }
        
        .stat-item {
            text-align: center;
            flex: 1;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            line-height: 1;
        }
        
        .stat-label {
            font-size: 0.65rem;
            color: #8b949e;
            margin-top: 4px;
            text-transform: uppercase;
        }
        
        .color-danger { color: #f85149; }
        .color-warning { color: #d29922; }
        .color-success { color: #3fb950; }
        .color-info { color: #58a6ff; }
        .color-muted { color: #8b949e; }
        
        .panels-row {
            display: flex;
            gap: 15px;
            flex: 1;
            min-height: 0;
        }
        
        .panel {
            flex: 1;
            background: rgba(255,255,255,0.05);
            border-radius: 10px;
            padding: 12px 15px;
            border: 1px solid rgba(255,255,255,0.1);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .panel.wide {
            flex: 1.5;
        }
        
        .panel h3 {
            font-size: 0.75rem;
            color: #8b949e;
            text-transform: uppercase;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
        }
        
        .panel h3 .count {
            background: rgba(255,255,255,0.1);
            padding: 2px 8px;
            border-radius: 8px;
            font-size: 0.7rem;
        }
        
        .panel-scroll {
            flex: 1;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        
        .list-item {
            padding: 8px 10px;
            background: rgba(0,0,0,0.2);
            border-radius: 6px;
            border-left: 3px solid;
            font-size: 0.8rem;
        }
        
        .list-item.critical { border-color: #f85149; }
        .list-item.high { border-color: #d29922; }
        .list-item.los { border-color: #f85149; }
        
        .list-item-title {
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .list-item-meta {
            font-size: 0.7rem;
            color: #8b949e;
            margin-top: 2px;
        }
        
        .attendance-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            flex: 1;
            overflow-y: auto;
            align-content: flex-start;
        }
        
        .att-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 10px;
            background: rgba(0,0,0,0.2);
            border-radius: 6px;
            border-left: 3px solid;
            width: calc(50% - 3px);
        }
        
        .att-item.present { border-color: #3fb950; }
        .att-item.left { border-color: #58a6ff; }
        .att-item.absent { border-color: #6e7681; opacity: 0.6; }
        
        .att-avatar {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.7rem;
            flex-shrink: 0;
        }
        
        .att-avatar.present { background: rgba(63, 185, 80, 0.3); color: #3fb950; }
        .att-avatar.left { background: rgba(88, 166, 255, 0.3); color: #58a6ff; }
        .att-avatar.absent { background: rgba(110, 118, 129, 0.3); color: #6e7681; }
        
        .att-info {
            flex: 1;
            min-width: 0;
        }
        
        .att-name {
            font-weight: 600;
            font-size: 0.75rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .att-role {
            font-size: 0.65rem;
            color: #8b949e;
        }
        
        .empty-state {
            color: #6e7681;
            text-align: center;
            padding: 20px;
            font-size: 0.8rem;
        }
    </style>
</head>
<body>
    <div class="wallboard">
        <div class="header">
            <h1><i class="bi bi-grid-3x3-gap-fill"></i> Operations Wallboard</h1>
            <div class="live-badge">
                <div class="live-dot"></div>
                Live
            </div>
        </div>
        
        <div class="stats-row">
            <div class="stat-card">
                <h3><i class="bi bi-ticket"></i> Tickets</h3>
                <div class="stat-row">
                    <div class="stat-item">
                        <div class="stat-value color-info"><?= $ticketStats['open_count'] ?? 0 ?></div>
                        <div class="stat-label">Open</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value color-warning"><?= $ticketStats['in_progress'] ?? 0 ?></div>
                        <div class="stat-label">In Progress</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value color-danger"><?= $ticketStats['critical_count'] ?? 0 ?></div>
                        <div class="stat-label">Critical</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value color-success"><?= $ticketStats['closed_today'] ?? 0 ?></div>
                        <div class="stat-label">Closed</div>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <h3><i class="bi bi-router"></i> Network</h3>
                <div class="stat-row">
                    <div class="stat-item">
                        <div class="stat-value color-success"><?= $onuStats['online_count'] ?? 0 ?></div>
                        <div class="stat-label">Online</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value color-danger"><?= $onuStats['los_count'] ?? 0 ?></div>
                        <div class="stat-label">LOS</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value color-muted"><?= $onuStats['offline_count'] ?? 0 ?></div>
                        <div class="stat-label">Offline</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value color-info"><?= $newOnuCount ?></div>
                        <div class="stat-label">New</div>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <h3><i class="bi bi-people"></i> Customers</h3>
                <div class="stat-row">
                    <div class="stat-item">
                        <div class="stat-value color-info"><?= $customerStats['total'] ?? 0 ?></div>
                        <div class="stat-label">Total</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value color-success"><?= $customerStats['active_count'] ?? 0 ?></div>
                        <div class="stat-label">Active</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value color-warning"><?= $customerStats['suspended_count'] ?? 0 ?></div>
                        <div class="stat-label">Suspended</div>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <h3><i class="bi bi-person-check"></i> Attendance</h3>
                <div class="stat-row">
                    <div class="stat-item">
                        <div class="stat-value color-success"><?= $presentCount ?></div>
                        <div class="stat-label">Present</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value color-muted"><?= $absentCount ?></div>
                        <div class="stat-label">Absent</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value color-info"><?= count($todayAttendance) ?></div>
                        <div class="stat-label">Total</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="panels-row">
            <div class="panel">
                <h3>
                    <span><i class="bi bi-exclamation-triangle"></i> Urgent Tickets</span>
                    <span class="count"><?= count($urgentTickets) ?></span>
                </h3>
                <div class="panel-scroll">
                    <?php if (empty($urgentTickets)): ?>
                        <div class="empty-state"><i class="bi bi-check-circle"></i> No urgent tickets</div>
                    <?php else: ?>
                        <?php foreach ($urgentTickets as $ticket): ?>
                        <div class="list-item <?= $ticket['priority'] ?>">
                            <div class="list-item-title">#<?= $ticket['id'] ?> <?= htmlspecialchars($ticket['subject']) ?></div>
                            <div class="list-item-meta">
                                <?= htmlspecialchars($ticket['customer_name'] ?? 'Unknown') ?>
                                <?php if ($ticket['assigned_name']): ?> &bull; <?= htmlspecialchars($ticket['assigned_name']) ?><?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="panel">
                <h3>
                    <span><i class="bi bi-wifi-off"></i> LOS Alerts</span>
                    <span class="count"><?= count($losOnus) ?></span>
                </h3>
                <div class="panel-scroll">
                    <?php if (empty($losOnus)): ?>
                        <div class="empty-state"><i class="bi bi-check-circle"></i> No LOS alerts</div>
                    <?php else: ?>
                        <?php foreach ($losOnus as $onu): ?>
                        <div class="list-item los">
                            <div class="list-item-title"><?= htmlspecialchars($onu['name'] ?: $onu['sn']) ?></div>
                            <div class="list-item-meta">
                                <?= htmlspecialchars($onu['customer_name'] ?? 'Unassigned') ?>
                                &bull; <?= htmlspecialchars($onu['olt_name'] ?? '') ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="panel wide">
                <h3>
                    <span><i class="bi bi-people"></i> Team</span>
                    <span class="count"><?= $presentCount ?>/<?= count($todayAttendance) ?></span>
                </h3>
                <div class="attendance-grid">
                    <?php foreach ($todayAttendance as $att): ?>
                    <div class="att-item <?= $att['attendance_status'] ?>">
                        <div class="att-avatar <?= $att['attendance_status'] ?>">
                            <?= strtoupper(substr($att['name'], 0, 2)) ?>
                        </div>
                        <div class="att-info">
                            <div class="att-name"><?= htmlspecialchars($att['name']) ?></div>
                            <div class="att-role"><?= htmlspecialchars($att['position'] ?? '') ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        setInterval(() => location.reload(), 30000);
    </script>
</body>
</html>
