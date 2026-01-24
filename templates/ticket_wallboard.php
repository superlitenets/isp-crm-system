<?php
require_once __DIR__ . '/../config/database.php';

$db = Database::getConnection();

// Ticket Stats - "open" is now shown as "Pending", pending means waiting on customer
$ticketStats = $db->query("
    SELECT 
        COUNT(*) FILTER (WHERE status IN ('open', 'pending')) as pending_count,
        COUNT(*) FILTER (WHERE status = 'in_progress') as in_progress,
        COUNT(*) FILTER (WHERE status = 'waiting_customer') as waiting_customer,
        COUNT(*) FILTER (WHERE priority = 'critical' AND status NOT IN ('closed', 'resolved')) as critical_count,
        COUNT(*) FILTER (WHERE assigned_to IS NULL AND status NOT IN ('closed', 'resolved')) as unassigned,
        COUNT(*) FILTER (WHERE DATE(created_at) = CURRENT_DATE) as created_today,
        COUNT(*) FILTER (WHERE status = 'closed' AND DATE(updated_at) = CURRENT_DATE) as closed_today,
        COUNT(*) FILTER (WHERE status NOT IN ('closed', 'resolved')) as total_open
    FROM tickets
")->fetch(PDO::FETCH_ASSOC);

// Average Response Time (time from created to first response/update)
$avgResponse = $db->query("
    SELECT AVG(EXTRACT(EPOCH FROM (first_response_at - created_at))/60) as avg_minutes
    FROM tickets 
    WHERE first_response_at IS NOT NULL AND created_at >= NOW() - INTERVAL '30 days'
")->fetchColumn();

// Average Resolution Time
$avgResolution = $db->query("
    SELECT AVG(EXTRACT(EPOCH FROM (updated_at - created_at))/60) as avg_minutes
    FROM tickets 
    WHERE status IN ('closed', 'resolved') AND created_at >= NOW() - INTERVAL '30 days'
")->fetchColumn();

// SLA Compliance (tickets resolved within SLA)
$slaStats = $db->query("
    SELECT 
        COUNT(*) as total,
        COUNT(*) FILTER (WHERE sla_resolution_breached = false) as met
    FROM tickets 
    WHERE status IN ('closed', 'resolved') AND created_at >= NOW() - INTERVAL '30 days'
")->fetch(PDO::FETCH_ASSOC);
$slaCompliance = ($slaStats['total'] > 0) ? round(($slaStats['met'] / $slaStats['total']) * 100) : 100;

// LOS ONUs from OMS
$losOnus = $db->query("
    SELECT o.name, o.sn, CONCAT(o.frame, '/', o.slot, '/', o.port) as fsp, 
           olt.name as olt_name, c.name as customer_name, o.updated_at
    FROM huawei_onus o
    LEFT JOIN huawei_olts olt ON o.olt_id = olt.id
    LEFT JOIN customers c ON o.customer_id = c.id
    WHERE o.status = 'los'
    ORDER BY o.updated_at DESC
    LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);

// Tickets by Category
$categoryStats = $db->query("
    SELECT 
        COALESCE(category, 'Other') as category,
        COUNT(*) as count
    FROM tickets 
    WHERE status NOT IN ('closed', 'resolved')
    GROUP BY COALESCE(category, 'Other')
    ORDER BY count DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

$totalCategoryTickets = array_sum(array_column($categoryStats, 'count'));

// Top 5 Pending Tickets (longest pending)
$topOpenTickets = $db->query("
    SELECT t.id, t.subject, t.priority, t.created_at,
           c.name as customer_name
    FROM tickets t
    LEFT JOIN customers c ON t.customer_id = c.id
    WHERE t.status NOT IN ('closed', 'resolved')
    ORDER BY t.created_at ASC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Critical Alert (most urgent issue)
$criticalAlert = $db->query("
    SELECT t.id, t.subject, t.priority, t.created_at,
           c.name as customer_name, u.name as assigned_name
    FROM tickets t
    LEFT JOIN customers c ON t.customer_id = c.id
    LEFT JOIN users u ON t.assigned_to = u.id
    WHERE t.priority = 'critical' AND t.status NOT IN ('closed', 'resolved')
    ORDER BY t.created_at ASC
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

// Technicians in the field (employees who checked in today - for avatars)
$technicians = $db->query("
    SELECT e.id, e.name,
           (SELECT COUNT(*) FROM tickets t LEFT JOIN users u2 ON t.assigned_to = u2.id WHERE LOWER(u2.name) = LOWER(e.name) AND t.status NOT IN ('closed', 'resolved')) as ticket_count,
           TO_CHAR(a.clock_in, 'HH24:MI') as clock_in
    FROM employees e
    INNER JOIN attendance a ON e.id = a.employee_id AND a.date = CURRENT_DATE
    WHERE e.employment_status = 'active' 
    AND a.clock_in IS NOT NULL
    AND a.clock_out IS NULL
    ORDER BY a.clock_in ASC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Today's Attendance - full list with clock in/out times
$todayAttendance = $db->query("
    SELECT 
        e.id, e.name, e.position,
        TO_CHAR(a.clock_in, 'HH24:MI') as clock_in,
        TO_CHAR(a.clock_out, 'HH24:MI') as clock_out,
        CASE 
            WHEN a.clock_out IS NOT NULL THEN 'left'
            ELSE 'present'
        END as attendance_status
    FROM employees e
    INNER JOIN attendance a ON e.id = a.employee_id AND a.date = CURRENT_DATE
    WHERE e.employment_status = 'active' AND a.clock_in IS NOT NULL
    ORDER BY 
        CASE WHEN a.clock_out IS NULL THEN 1 ELSE 2 END,
        a.clock_in DESC
")->fetchAll(PDO::FETCH_ASSOC);
$presentCount = count(array_filter($todayAttendance, fn($a) => $a['attendance_status'] === 'present'));

// Tickets assigned to checked-in technicians (for the table)
$technicianTickets = $db->query("
    SELECT t.id, t.priority, t.status, u.name as assigned_name
    FROM tickets t
    INNER JOIN users u ON t.assigned_to = u.id
    INNER JOIN employees e ON LOWER(e.name) = LOWER(u.name)
    INNER JOIN attendance a ON e.id = a.employee_id AND a.date = CURRENT_DATE AND a.clock_out IS NULL
    WHERE t.status NOT IN ('closed', 'resolved')
    ORDER BY 
        CASE t.priority 
            WHEN 'critical' THEN 1 
            WHEN 'high' THEN 2 
            WHEN 'medium' THEN 3 
            ELSE 4 
        END,
        t.created_at ASC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

function formatDuration($minutes) {
    if ($minutes === null) return '-';
    $mins = round($minutes);
    if ($mins < 60) return $mins . 'm';
    $hours = floor($mins / 60);
    $mins = $mins % 60;
    return $hours . 'h ' . $mins . 'm';
}

function timeOpen($created) {
    $now = new DateTime();
    $past = new DateTime($created);
    $diff = $now->diff($past);
    
    if ($diff->d > 0) return $diff->d . 'd ' . $diff->h . 'h';
    if ($diff->h > 0) return $diff->h . 'h ' . $diff->i . 'm';
    return $diff->i . 'm';
}

function getInitials($name) {
    $words = explode(' ', trim($name));
    $initials = '';
    foreach ($words as $word) {
        if (!empty($word)) $initials .= strtoupper($word[0]);
    }
    return substr($initials, 0, 2);
}

$categoryColors = ['#dc3545', '#17a2b8', '#28a745', '#ffc107', '#6c757d'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Ticket Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        html, body {
            height: 100vh;
            overflow: hidden;
            background: linear-gradient(135deg, #1a1f2e 0%, #0d1117 100%);
            color: #fff;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }
        
        .dashboard {
            display: flex;
            flex-direction: column;
            height: 100vh;
            padding: 20px;
            gap: 16px;
        }
        
        .header {
            text-align: center;
            padding: 10px 0;
        }
        
        .header h1 {
            font-size: 2rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 3px;
            color: #fff;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
        }
        
        .status-cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr) 120px;
            gap: 12px;
        }
        
        .status-card {
            border-radius: 8px;
            padding: 16px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }
        
        .status-card .label {
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
            opacity: 0.9;
        }
        
        .status-card .value {
            font-size: 3rem;
            font-weight: 800;
            line-height: 1;
        }
        
        .card-pending { background: linear-gradient(135deg, #c0392b 0%, #e74c3c 100%); }
        .card-progress { background: linear-gradient(135deg, #d68910 0%, #f39c12 100%); }
        .card-waiting { background: linear-gradient(135deg, #1a5276 0%, #2980b9 100%); }
        .card-critical { background: linear-gradient(135deg, #1c2833 0%, #2c3e50 100%); border: 2px solid #e74c3c; }
        
        .gauge-container {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .gauge {
            width: 100px;
            height: 50px;
            position: relative;
            overflow: hidden;
        }
        
        .gauge-bg {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: conic-gradient(
                #27ae60 0deg <?= $slaCompliance * 1.8 ?>deg,
                #f39c12 <?= $slaCompliance * 1.8 ?>deg <?= min($slaCompliance * 1.8 + 36, 180) ?>deg,
                #e74c3c <?= min($slaCompliance * 1.8 + 36, 180) ?>deg 180deg
            );
            position: absolute;
            top: 0;
        }
        
        .gauge-inner {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #1a1f2e;
            position: absolute;
            top: 20px;
            left: 20px;
        }
        
        .metrics-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }
        
        .metric-card {
            background: rgba(255,255,255,0.05);
            border-radius: 8px;
            padding: 16px 24px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        
        .metric-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .metric-icon.clock { background: rgba(52, 152, 219, 0.2); color: #3498db; }
        .metric-icon.wrench { background: rgba(155, 89, 182, 0.2); color: #9b59b6; }
        .metric-icon.check { background: rgba(39, 174, 96, 0.2); color: #27ae60; }
        
        .metric-info .label {
            font-size: 0.75rem;
            color: #95a5a6;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .metric-info .value {
            font-size: 1.8rem;
            font-weight: 700;
        }
        
        .bottom-section {
            flex: 1;
            display: grid;
            grid-template-columns: 1fr 1.5fr 1fr;
            gap: 16px;
            min-height: 0;
        }
        
        .panel {
            background: rgba(255,255,255,0.03);
            border-radius: 8px;
            padding: 16px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        
        .panel-title {
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 12px;
            color: #ecf0f1;
            border-bottom: 2px solid rgba(255,255,255,0.1);
            padding-bottom: 8px;
        }
        
        .category-chart {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px 0;
        }
        
        .donut-chart {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            position: relative;
        }
        
        .donut-hole {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #1a1f2e;
            position: absolute;
            top: 30px;
            left: 30px;
        }
        
        .category-legend {
            margin-top: 16px;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 4px 0;
            font-size: 0.8rem;
        }
        
        .legend-color {
            width: 12px;
            height: 12px;
            border-radius: 2px;
        }
        
        .legend-text { flex: 1; }
        .legend-percent { font-weight: 600; }
        
        .ticket-list {
            flex: 1;
            overflow-y: auto;
        }
        
        .ticket-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            background: rgba(0,0,0,0.2);
            border-radius: 6px;
            margin-bottom: 8px;
        }
        
        .ticket-rank {
            width: 24px;
            height: 24px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.75rem;
        }
        
        .rank-1 { background: #c0392b; }
        .rank-2 { background: #d35400; }
        .rank-3 { background: #f39c12; }
        .rank-4 { background: #27ae60; }
        .rank-5 { background: #2980b9; }
        
        .ticket-info {
            flex: 1;
            min-width: 0;
        }
        
        .ticket-subject {
            font-size: 0.85rem;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .ticket-time {
            font-size: 0.75rem;
            color: #95a5a6;
            font-weight: 500;
        }
        
        .alert-box {
            margin-top: auto;
            background: rgba(231, 76, 60, 0.15);
            border-left: 4px solid #e74c3c;
            border-radius: 6px;
            padding: 12px;
        }
        
        .alert-label {
            font-size: 0.7rem;
            font-weight: 700;
            color: #e74c3c;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        
        .alert-text {
            font-size: 0.8rem;
            line-height: 1.4;
        }
        
        .alert-meta {
            font-size: 0.7rem;
            color: #95a5a6;
            margin-top: 4px;
        }
        
        .technician-avatars {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }
        
        .tech-avatar {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
        }
        
        .avatar-circle {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3498db, #2c3e50);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.9rem;
            border: 2px solid #fff;
            overflow: hidden;
        }
        
        .avatar-circle img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .tech-name {
            font-size: 0.65rem;
            color: #bdc3c7;
            max-width: 50px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            text-align: center;
        }
        
        .tech-table {
            flex: 1;
            overflow-y: auto;
        }
        
        .att-list {
            flex: 1;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        
        .att-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 10px;
            background: rgba(0,0,0,0.2);
            border-radius: 6px;
            border-left: 3px solid;
        }
        
        .att-row.present { border-color: #27ae60; }
        .att-row.left { border-color: #3498db; }
        
        .att-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.7rem;
            flex-shrink: 0;
        }
        
        .att-avatar.present { background: rgba(39,174,96,0.3); color: #27ae60; }
        .att-avatar.left { background: rgba(52,152,219,0.3); color: #3498db; }
        
        .att-info {
            flex: 1;
            min-width: 0;
        }
        
        .att-name {
            font-size: 0.8rem;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .att-position {
            font-size: 0.65rem;
            color: #95a5a6;
        }
        
        .att-time {
            font-size: 0.7rem;
            color: #95a5a6;
            display: flex;
            gap: 6px;
            align-items: center;
        }
        
        .att-time i {
            font-size: 0.75rem;
        }
        
        .los-section {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding-top: 8px;
            margin-top: 8px;
        }
        
        .los-list {
            display: flex;
            flex-direction: column;
            gap: 6px;
            max-height: 150px;
            overflow-y: auto;
        }
        
        .los-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 10px;
            background: rgba(231,76,60,0.1);
            border-left: 3px solid #e74c3c;
            border-radius: 6px;
        }
        
        .los-info {
            flex: 1;
            min-width: 0;
        }
        
        .los-name {
            font-size: 0.8rem;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .los-meta {
            font-size: 0.65rem;
            color: #95a5a6;
            font-family: monospace;
        }
        
        .data-table {
            width: 100%;
            font-size: 0.75rem;
            border-collapse: collapse;
        }
        
        .data-table th {
            text-align: left;
            color: #95a5a6;
            font-weight: 600;
            padding: 8px;
            border-bottom: 2px solid rgba(255,255,255,0.1);
            font-size: 0.7rem;
            text-transform: uppercase;
        }
        
        .data-table td {
            padding: 8px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        
        .priority-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        
        .priority-critical { background: rgba(231,76,60,0.3); color: #e74c3c; }
        .priority-high { background: rgba(241,196,15,0.3); color: #f1c40f; }
        .priority-medium { background: rgba(52,152,219,0.3); color: #3498db; }
        .priority-low { background: rgba(149,165,166,0.3); color: #95a5a6; }
        
        .status-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.65rem;
            font-weight: 500;
        }
        
        .status-open { background: rgba(231,76,60,0.2); color: #e74c3c; }
        .status-in_progress { background: rgba(241,196,15,0.2); color: #f1c40f; }
        .status-pending { background: rgba(52,152,219,0.2); color: #3498db; }
        
        .live-indicator {
            position: fixed;
            top: 20px;
            right: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(0,0,0,0.5);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.75rem;
            color: #95a5a6;
        }
        
        .live-dot {
            width: 8px;
            height: 8px;
            background: #27ae60;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(0.8); }
        }
        
        .empty-state {
            text-align: center;
            color: #7f8c8d;
            padding: 20px;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <div class="live-indicator">
        <div class="live-dot"></div>
        <span>Live - Auto-refresh 30s</span>
    </div>
    
    <div class="dashboard">
        <div class="header">
            <h1>Support Ticket Dashboard</h1>
        </div>
        
        <div class="status-cards">
            <div class="status-card card-pending">
                <div class="label">Pending Tickets</div>
                <div class="value"><?= $ticketStats['pending_count'] ?? 0 ?></div>
            </div>
            <div class="status-card card-progress">
                <div class="label">In Progress</div>
                <div class="value"><?= $ticketStats['in_progress'] ?? 0 ?></div>
            </div>
            <div class="status-card card-waiting">
                <div class="label">Waiting on Customer</div>
                <div class="value"><?= $ticketStats['waiting_customer'] ?? 0 ?></div>
            </div>
            <div class="status-card card-critical">
                <div class="label">Critical Tickets</div>
                <div class="value"><?= $ticketStats['critical_count'] ?? 0 ?></div>
            </div>
            <div class="gauge-container">
                <div class="gauge">
                    <div class="gauge-bg"></div>
                    <div class="gauge-inner"></div>
                </div>
            </div>
        </div>
        
        <div class="metrics-row">
            <div class="metric-card">
                <div class="metric-icon clock">
                    <i class="bi bi-clock"></i>
                </div>
                <div class="metric-info">
                    <div class="label">Average Response Time</div>
                    <div class="value"><?= formatDuration($avgResponse) ?></div>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-icon wrench">
                    <i class="bi bi-tools"></i>
                </div>
                <div class="metric-info">
                    <div class="label">Average Resolution Time</div>
                    <div class="value"><?= formatDuration($avgResolution) ?></div>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-icon check">
                    <i class="bi bi-check-circle"></i>
                </div>
                <div class="metric-info">
                    <div class="label">SLA Compliance</div>
                    <div class="value"><?= $slaCompliance ?>%</div>
                </div>
            </div>
        </div>
        
        <div class="bottom-section">
            <div class="panel">
                <div class="panel-title">Tickets by Category</div>
                <div class="category-chart">
                    <?php 
                    $gradientParts = [];
                    $currentDeg = 0;
                    foreach ($categoryStats as $i => $cat) {
                        $percent = ($totalCategoryTickets > 0) ? ($cat['count'] / $totalCategoryTickets) * 360 : 0;
                        $color = $categoryColors[$i] ?? '#6c757d';
                        $gradientParts[] = "$color {$currentDeg}deg " . ($currentDeg + $percent) . "deg";
                        $currentDeg += $percent;
                    }
                    $gradient = implode(', ', $gradientParts);
                    ?>
                    <div class="donut-chart" style="background: conic-gradient(<?= $gradient ?: '#333 0deg 360deg' ?>);">
                        <div class="donut-hole"></div>
                    </div>
                </div>
                <div class="category-legend">
                    <?php foreach ($categoryStats as $i => $cat): 
                        $percent = ($totalCategoryTickets > 0) ? round(($cat['count'] / $totalCategoryTickets) * 100) : 0;
                    ?>
                    <div class="legend-item">
                        <div class="legend-color" style="background: <?= $categoryColors[$i] ?? '#6c757d' ?>"></div>
                        <div class="legend-text"><?= htmlspecialchars($cat['category']) ?></div>
                        <div class="legend-percent"><?= $percent ?>%</div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($categoryStats)): ?>
                    <div class="empty-state">No categories</div>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($losOnus)): ?>
                <div class="los-section">
                    <div class="panel-title" style="margin-top:12px;"><i class="bi bi-wifi-off"></i> LOS Alerts <span style="color:#e74c3c;">(<?= count($losOnus) ?>)</span></div>
                    <div class="los-list">
                        <?php foreach ($losOnus as $onu): ?>
                        <div class="los-item">
                            <i class="bi bi-exclamation-triangle-fill" style="color:#e74c3c;"></i>
                            <div class="los-info">
                                <div class="los-name"><?= htmlspecialchars($onu['customer_name'] ?: $onu['name'] ?: 'Unknown') ?></div>
                                <div class="los-meta"><?= htmlspecialchars($onu['sn']) ?> &bull; <?= htmlspecialchars($onu['olt_name'] ?? '') ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="panel">
                <div class="panel-title">Top 5 Pending Tickets</div>
                <div class="ticket-list">
                    <?php foreach ($topOpenTickets as $i => $ticket): ?>
                    <div class="ticket-item">
                        <div class="ticket-rank rank-<?= $i + 1 ?>"><?= $i + 1 ?></div>
                        <div class="ticket-info">
                            <div class="ticket-subject"><?= htmlspecialchars($ticket['subject']) ?><?= $ticket['customer_name'] ? ' - ' . htmlspecialchars($ticket['customer_name']) : '' ?></div>
                        </div>
                        <div class="ticket-time"><?= timeOpen($ticket['created_at']) ?></div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($topOpenTickets)): ?>
                    <div class="empty-state">No open tickets</div>
                    <?php endif; ?>
                </div>
                
                <?php if ($criticalAlert): ?>
                <div class="alert-box">
                    <div class="alert-label">Alert</div>
                    <div class="alert-text"><?= htmlspecialchars($criticalAlert['subject']) ?><?= $criticalAlert['customer_name'] ? ' - ' . htmlspecialchars($criticalAlert['customer_name']) : '' ?></div>
                    <div class="alert-meta">
                        <?php if ($criticalAlert['assigned_name']): ?>
                        Assigned to <?= htmlspecialchars($criticalAlert['assigned_name']) ?>. 
                        <?php endif; ?>
                        Open for <?= timeOpen($criticalAlert['created_at']) ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="panel">
                <div class="panel-title">Technicians in the Field <span style="color:#27ae60;">(<?= $presentCount ?> present)</span></div>
                <div class="technician-avatars">
                    <?php foreach ($technicians as $tech): ?>
                    <div class="tech-avatar">
                        <div class="avatar-circle">
                            <?= getInitials($tech['name']) ?>
                        </div>
                        <div class="tech-name"><?= htmlspecialchars(explode(' ', $tech['name'])[0]) ?></div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($technicians)): ?>
                    <div class="empty-state">No one checked in</div>
                    <?php endif; ?>
                </div>
                
                <div class="att-list">
                    <?php foreach ($todayAttendance as $att): ?>
                    <div class="att-row <?= $att['attendance_status'] ?>">
                        <div class="att-avatar <?= $att['attendance_status'] ?>">
                            <?= strtoupper(substr($att['name'], 0, 2)) ?>
                        </div>
                        <div class="att-info">
                            <div class="att-name"><?= htmlspecialchars($att['name']) ?></div>
                            <div class="att-position"><?= htmlspecialchars($att['position'] ?? '') ?></div>
                        </div>
                        <div class="att-time">
                            <i class="bi bi-box-arrow-in-right"></i> <?= $att['clock_in'] ?>
                            <?php if ($att['clock_out']): ?>
                                <i class="bi bi-box-arrow-right"></i> <?= $att['clock_out'] ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($todayAttendance)): ?>
                    <div class="empty-state">No attendance records</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        setInterval(() => location.reload(), 30000);
    </script>
</body>
</html>
