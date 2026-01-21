<?php
// Operations Wallboard - Statistics display optimized for TV screens
// Opens in fullscreen mode without header/sidebar

require_once __DIR__ . '/../config/database.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$db = Database::getConnection();

// Ticket Statistics
$ticketStats = $db->query("
    SELECT 
        COUNT(*) FILTER (WHERE status = 'open') as open_tickets,
        COUNT(*) FILTER (WHERE status = 'in_progress') as in_progress,
        COUNT(*) FILTER (WHERE status = 'pending') as pending,
        COUNT(*) FILTER (WHERE status = 'resolved') as resolved,
        COUNT(*) FILTER (WHERE status = 'closed' AND updated_at > NOW() - INTERVAL '24 hours') as closed_today,
        COUNT(*) FILTER (WHERE priority = 'critical' AND status NOT IN ('closed', 'resolved')) as critical,
        COUNT(*) FILTER (WHERE priority = 'high' AND status NOT IN ('closed', 'resolved')) as high_priority
    FROM tickets
")->fetch(PDO::FETCH_ASSOC);

// ONU Statistics
$onuStats = $db->query("
    SELECT 
        COUNT(*) as total_onus,
        COUNT(*) FILTER (WHERE status = 'online') as online,
        COUNT(*) FILTER (WHERE status = 'offline') as offline,
        COUNT(*) FILTER (WHERE status = 'los') as los
    FROM huawei_onus
    WHERE is_authorized = true
")->fetch(PDO::FETCH_ASSOC);

// New ONU discoveries (last 24h)
$newOnuCount = $db->query("
    SELECT COUNT(*) as cnt FROM onu_discovery_log 
    WHERE last_seen_at > NOW() - INTERVAL '24 hours'
")->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0;

// Customer Statistics
$customerStats = $db->query("
    SELECT 
        COUNT(*) as total,
        COUNT(*) FILTER (WHERE status = 'active') as active,
        COUNT(*) FILTER (WHERE status = 'suspended') as suspended
    FROM customers
")->fetch(PDO::FETCH_ASSOC);

// Today's Attendance
$todayAttendance = $db->query("
    SELECT 
        u.id, u.name, u.role,
        a.check_in, a.check_out, a.status,
        CASE 
            WHEN a.check_in IS NOT NULL AND a.check_out IS NULL THEN 'present'
            WHEN a.check_in IS NOT NULL AND a.check_out IS NOT NULL THEN 'left'
            ELSE 'absent'
        END as attendance_status
    FROM users u
    LEFT JOIN attendance a ON u.id = a.user_id AND DATE(a.check_in) = CURRENT_DATE
    WHERE u.is_active = true AND u.role != 'customer'
    ORDER BY 
        CASE 
            WHEN a.check_in IS NOT NULL AND a.check_out IS NULL THEN 1
            WHEN a.check_in IS NOT NULL AND a.check_out IS NOT NULL THEN 2
            ELSE 3
        END,
        u.name
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
    LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);

// LOS ONUs
$losOnus = $db->query("
    SELECT o.name, o.sn, olt.name as olt_name, c.name as customer_name
    FROM huawei_onus o
    LEFT JOIN huawei_olts olt ON o.olt_id = olt.id
    LEFT JOIN customers c ON o.customer_id = c.id
    WHERE o.status = 'los'
    ORDER BY o.updated_at DESC
    LIMIT 10
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
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr;
            grid-template-rows: auto 1fr 1fr;
            gap: 15px;
            height: 100vh;
            padding: 15px;
        }
        
        .header {
            grid-column: 1 / -1;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 20px;
            background: rgba(255,255,255,0.05);
            border-radius: 12px;
        }
        
        .header h1 {
            font-size: 1.8rem;
            font-weight: 600;
        }
        
        .header h1 i { color: #00d4ff; }
        
        .live-badge {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            color: #8b949e;
        }
        
        .live-dot {
            width: 10px;
            height: 10px;
            background: #3fb950;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }
        
        .stat-card {
            background: rgba(255,255,255,0.05);
            border-radius: 12px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .stat-card h3 {
            font-size: 0.85rem;
            color: #8b949e;
            text-transform: uppercase;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            flex: 1;
        }
        
        .stat-item {
            text-align: center;
            padding: 15px;
            background: rgba(0,0,0,0.2);
            border-radius: 10px;
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            line-height: 1;
        }
        
        .stat-label {
            font-size: 0.75rem;
            color: #8b949e;
            margin-top: 5px;
            text-transform: uppercase;
        }
        
        .color-danger { color: #f85149; }
        .color-warning { color: #d29922; }
        .color-success { color: #3fb950; }
        .color-info { color: #58a6ff; }
        .color-muted { color: #8b949e; }
        
        .list-card {
            background: rgba(255,255,255,0.05);
            border-radius: 12px;
            padding: 15px;
            display: flex;
            flex-direction: column;
            border: 1px solid rgba(255,255,255,0.1);
            overflow: hidden;
        }
        
        .list-card h3 {
            font-size: 0.85rem;
            color: #8b949e;
            text-transform: uppercase;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .list-card h3 .count {
            background: rgba(255,255,255,0.1);
            padding: 2px 10px;
            border-radius: 10px;
            font-size: 0.8rem;
        }
        
        .list-scroll {
            flex: 1;
            overflow-y: auto;
        }
        
        .list-item {
            padding: 10px 12px;
            margin-bottom: 6px;
            background: rgba(0,0,0,0.2);
            border-radius: 8px;
            border-left: 3px solid;
            font-size: 0.85rem;
        }
        
        .list-item.critical { border-color: #f85149; }
        .list-item.high { border-color: #d29922; }
        .list-item.los { border-color: #f85149; }
        .list-item.present { border-color: #3fb950; }
        .list-item.left { border-color: #58a6ff; }
        .list-item.absent { border-color: #8b949e; }
        
        .list-item-title {
            font-weight: 600;
            margin-bottom: 3px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .list-item-meta {
            font-size: 0.75rem;
            color: #8b949e;
        }
        
        .attendance-panel {
            grid-column: span 2;
        }
        
        .attendance-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 8px;
            flex: 1;
            overflow-y: auto;
        }
        
        .att-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            background: rgba(0,0,0,0.2);
            border-radius: 8px;
            border-left: 3px solid;
        }
        
        .att-item.present { border-color: #3fb950; }
        .att-item.left { border-color: #58a6ff; }
        .att-item.absent { border-color: #6e7681; opacity: 0.7; }
        
        .att-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.9rem;
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
            font-size: 0.85rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .att-time {
            font-size: 0.7rem;
            color: #8b949e;
        }
        
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 3px; }
    </style>
</head>
<body>
    <div class="wallboard">
        <div class="header">
            <h1><i class="bi bi-grid-3x3-gap-fill"></i> Operations Wallboard</h1>
            <div class="live-badge">
                <div class="live-dot"></div>
                <span>Live</span>
            </div>
        </div>
        
        <div class="stat-card">
            <h3><i class="bi bi-ticket"></i> Tickets</h3>
            <div class="stat-grid">
                <div class="stat-item">
                    <div class="stat-value color-info"><?= $ticketStats['open_tickets'] ?? 0 ?></div>
                    <div class="stat-label">Open</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value color-warning"><?= $ticketStats['in_progress'] ?? 0 ?></div>
                    <div class="stat-label">In Progress</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value color-danger"><?= $ticketStats['critical'] ?? 0 ?></div>
                    <div class="stat-label">Critical</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value color-success"><?= $ticketStats['closed_today'] ?? 0 ?></div>
                    <div class="stat-label">Closed Today</div>
                </div>
            </div>
        </div>
        
        <div class="stat-card">
            <h3><i class="bi bi-router"></i> Network</h3>
            <div class="stat-grid">
                <div class="stat-item">
                    <div class="stat-value color-success"><?= $onuStats['online'] ?? 0 ?></div>
                    <div class="stat-label">Online</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value color-danger"><?= $onuStats['los'] ?? 0 ?></div>
                    <div class="stat-label">LOS</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value color-muted"><?= $onuStats['offline'] ?? 0 ?></div>
                    <div class="stat-label">Offline</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value color-info"><?= $newOnuCount ?></div>
                    <div class="stat-label">New (24h)</div>
                </div>
            </div>
        </div>
        
        <div class="stat-card">
            <h3><i class="bi bi-people"></i> Customers</h3>
            <div class="stat-grid">
                <div class="stat-item">
                    <div class="stat-value color-info"><?= $customerStats['total'] ?? 0 ?></div>
                    <div class="stat-label">Total</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value color-success"><?= $customerStats['active'] ?? 0 ?></div>
                    <div class="stat-label">Active</div>
                </div>
                <div class="stat-item" style="grid-column: span 2;">
                    <div class="stat-value color-warning"><?= $customerStats['suspended'] ?? 0 ?></div>
                    <div class="stat-label">Suspended</div>
                </div>
            </div>
        </div>
        
        <div class="stat-card">
            <h3><i class="bi bi-person-check"></i> Attendance</h3>
            <div class="stat-grid">
                <div class="stat-item">
                    <div class="stat-value color-success"><?= $presentCount ?></div>
                    <div class="stat-label">Present</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value color-muted"><?= $absentCount ?></div>
                    <div class="stat-label">Absent</div>
                </div>
                <div class="stat-item" style="grid-column: span 2;">
                    <div class="stat-value color-info"><?= count($todayAttendance) ?></div>
                    <div class="stat-label">Total Staff</div>
                </div>
            </div>
        </div>
        
        <div class="list-card">
            <h3>
                <span><i class="bi bi-exclamation-triangle"></i> Urgent Tickets</span>
                <span class="count"><?= count($urgentTickets) ?></span>
            </h3>
            <div class="list-scroll">
                <?php if (empty($urgentTickets)): ?>
                <div style="text-align: center; padding: 30px; color: #8b949e;">
                    <i class="bi bi-check-circle" style="font-size: 2rem;"></i>
                    <div>No urgent tickets</div>
                </div>
                <?php else: ?>
                <?php foreach ($urgentTickets as $t): ?>
                <div class="list-item <?= $t['priority'] ?>">
                    <div class="list-item-title">#<?= $t['id'] ?> <?= htmlspecialchars($t['subject']) ?></div>
                    <div class="list-item-meta">
                        <?= htmlspecialchars($t['customer_name'] ?? 'No customer') ?>
                        <?php if ($t['assigned_name']): ?> - <?= htmlspecialchars($t['assigned_name']) ?><?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="list-card">
            <h3>
                <span><i class="bi bi-exclamation-octagon"></i> LOS Alerts</span>
                <span class="count"><?= count($losOnus) ?></span>
            </h3>
            <div class="list-scroll">
                <?php if (empty($losOnus)): ?>
                <div style="text-align: center; padding: 30px; color: #8b949e;">
                    <i class="bi bi-check-circle" style="font-size: 2rem;"></i>
                    <div>No LOS alerts</div>
                </div>
                <?php else: ?>
                <?php foreach ($losOnus as $o): ?>
                <div class="list-item los">
                    <div class="list-item-title"><?= htmlspecialchars($o['name'] ?: $o['sn']) ?></div>
                    <div class="list-item-meta">
                        <?= htmlspecialchars($o['olt_name'] ?? '') ?>
                        <?php if ($o['customer_name']): ?> - <?= htmlspecialchars($o['customer_name']) ?><?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="list-card attendance-panel">
            <h3>
                <span><i class="bi bi-calendar-check"></i> Today's Attendance</span>
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
                        <div class="att-time">
                            <?php if ($att['attendance_status'] === 'present'): ?>
                                In: <?= date('H:i', strtotime($att['check_in'])) ?>
                            <?php elseif ($att['attendance_status'] === 'left'): ?>
                                <?= date('H:i', strtotime($att['check_in'])) ?> - <?= date('H:i', strtotime($att['check_out'])) ?>
                            <?php else: ?>
                                Not checked in
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <script>
        setInterval(() => location.reload(), 30000);
    </script>
</body>
</html>
