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
        COUNT(*) FILTER (WHERE assigned_to IS NULL AND team_id IS NULL AND status NOT IN ('closed', 'resolved')) as unassigned,
        COUNT(*) FILTER (WHERE DATE(created_at) = CURRENT_DATE) as created_today,
        COUNT(*) FILTER (WHERE status = 'closed' AND DATE(updated_at) = CURRENT_DATE) as closed_today,
        COUNT(*) FILTER (WHERE status NOT IN ('closed', 'resolved')) as total_open,
        COUNT(*) FILTER (WHERE assigned_to IS NOT NULL AND status NOT IN ('closed', 'resolved')) as assigned_individual,
        COUNT(*) FILTER (WHERE team_id IS NOT NULL AND assigned_to IS NULL AND status NOT IN ('closed', 'resolved')) as assigned_group
    FROM tickets
")->fetch(PDO::FETCH_ASSOC);

// Assignment breakdown by team/group
$teamAssignments = $db->query("
    SELECT tm.name as team_name, COUNT(t.id) as ticket_count
    FROM tickets t
    INNER JOIN teams tm ON t.team_id = tm.id
    WHERE t.status NOT IN ('closed', 'resolved')
    GROUP BY tm.id, tm.name
    ORDER BY ticket_count DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Assignment breakdown by individual
$individualAssignments = $db->query("
    SELECT u.name as user_name, COUNT(t.id) as ticket_count
    FROM tickets t
    INNER JOIN users u ON t.assigned_to = u.id
    WHERE t.status NOT IN ('closed', 'resolved')
    GROUP BY u.id, u.name
    ORDER BY ticket_count DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Branch ticket statistics
$branchStats = $db->query("
    SELECT 
        b.name as branch_name,
        COUNT(t.id) as total_tickets,
        COUNT(*) FILTER (WHERE t.status = 'open') as open_count,
        COUNT(*) FILTER (WHERE t.status = 'pending') as pending_count,
        COUNT(*) FILTER (WHERE t.status = 'in_progress') as in_progress_count
    FROM branches b
    LEFT JOIN tickets t ON t.branch_id = b.id AND t.status NOT IN ('closed', 'resolved')
    GROUP BY b.id, b.name
    ORDER BY total_tickets DESC
")->fetchAll(PDO::FETCH_ASSOC);

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

// Recent Tickets (for live table display)
$topOpenTickets = $db->query("
    SELECT t.id, t.subject, t.priority, t.status, t.category, t.created_at,
           c.name as customer_name, tm.name as team_name,
           u.name as assigned_name, b.name as branch_name
    FROM tickets t
    LEFT JOIN customers c ON t.customer_id = c.id
    LEFT JOIN teams tm ON t.team_id = tm.id
    LEFT JOIN users u ON t.assigned_to = u.id
    LEFT JOIN branches b ON t.branch_id = b.id
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
            padding: 1vh 1.5vw;
            gap: 1vh;
        }
        
        .header {
            text-align: center;
            padding: 0.5vh 0;
            flex-shrink: 0;
        }
        
        .header h1 {
            font-size: clamp(1.5rem, 3vw, 2.7rem);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: #fff;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
        }
        
        .status-cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr) 100px;
            gap: 0.8vw;
            flex-shrink: 0;
        }
        
        .status-card {
            border-radius: 6px;
            padding: 1vh 0.5vw;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }
        
        .status-card .label {
            font-size: clamp(0.75rem, 1.05vw, 1.05rem);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 0.5vh;
            opacity: 0.9;
        }
        
        .status-card .value {
            font-size: clamp(2.25rem, 4.5vw, 3.75rem);
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
            gap: 1vw;
            flex-shrink: 0;
        }
        
        .metric-card {
            background: rgba(255,255,255,0.05);
            border-radius: 6px;
            padding: 1vh 1vw;
            display: flex;
            align-items: center;
            gap: 1vw;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        
        .metric-icon {
            width: clamp(30px, 3vw, 45px);
            height: clamp(30px, 3vw, 45px);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: clamp(1.35rem, 1.8vw, 1.95rem);
        }
        
        .metric-icon.clock { background: rgba(52, 152, 219, 0.2); color: #3498db; }
        .metric-icon.wrench { background: rgba(155, 89, 182, 0.2); color: #9b59b6; }
        .metric-icon.check { background: rgba(39, 174, 96, 0.2); color: #27ae60; }
        
        .metric-info .label {
            font-size: clamp(1.2rem, 1.5vw, 1.65rem);
            color: #95a5a6;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .metric-info .value {
            font-size: clamp(1.5rem, 2.25vw, 2.4rem);
            font-weight: 700;
        }
        
        .bottom-section {
            flex: 1;
            display: flex;
            gap: 1vw;
            min-height: 0;
        }
        
        .ticket-table-panel {
            flex: 1;
            min-width: 0;
            overflow: hidden;
        }
        
        .tech-panel {
            flex: 0 0 250px;
            min-width: 200px;
        }
        
        .panel {
            background: rgba(255,255,255,0.03);
            border-radius: 6px;
            padding: 1vh 1vw;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        
        .panel-title {
            font-size: clamp(0.9rem, 1.2vw, 1.2rem);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 0.8vh;
            color: #ecf0f1;
            border-bottom: 2px solid rgba(255,255,255,0.1);
            padding-bottom: 0.5vh;
        }
        
        .category-chart {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1vh 0;
        }
        
        .donut-chart {
            width: clamp(80px, 8vw, 110px);
            height: clamp(80px, 8vw, 110px);
            border-radius: 50%;
            position: relative;
        }
        
        .donut-hole {
            width: 50%;
            height: 50%;
            border-radius: 50%;
            background: #1a1f2e;
            position: absolute;
            top: 25%;
            left: 25%;
        }
        
        .category-legend {
            margin-top: 1vh;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 0.5vw;
            padding: 2px 0;
            font-size: clamp(1.2rem, 1.5vw, 1.65rem);
        }
        
        .legend-color {
            width: clamp(8px, 0.8vw, 12px);
            height: clamp(8px, 0.8vw, 12px);
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
            font-size: 1.125rem;
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
            font-size: clamp(1.35rem, 1.65vw, 1.8rem);
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .ticket-team {
            font-size: clamp(1.2rem, 1.5vw, 1.65rem);
            color: #3498db;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .ticket-team i {
            font-size: clamp(0.75rem, 0.825vw, 0.9rem);
        }
        
        .ticket-time {
            font-size: 1.125rem;
            color: #95a5a6;
            font-weight: 500;
        }
        
        .alert-box {
            margin-top: 8px;
            background: rgba(231, 76, 60, 0.15);
            border-left: 3px solid #e74c3c;
            border-radius: 4px;
            padding: 6px 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .alert-label {
            font-size: clamp(1.2rem, 1.5vw, 1.65rem);
            font-weight: 700;
            color: #e74c3c;
            text-transform: uppercase;
        }
        
        .alert-text {
            font-size: clamp(1.2rem, 1.5vw, 1.65rem);
        }
        
        .ticket-table {
            width: 100%;
            border-collapse: collapse;
            font-size: clamp(1.2rem, 1.5vw, 1.65rem);
            table-layout: fixed;
        }
        
        .ticket-table th {
            text-align: left;
            padding: 3px 4px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            color: #7f8c8d;
            font-weight: 600;
            font-size: clamp(1.125rem, 1.35vw, 1.5rem);
            white-space: nowrap;
        }
        
        .ticket-table td {
            padding: 3px 4px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .ticket-table th:nth-child(1) { width: 5%; }
        .ticket-table th:nth-child(2) { width: 8%; }
        .ticket-table th:nth-child(3) { width: 25%; }
        .ticket-table th:nth-child(4) { width: 10%; }
        .ticket-table th:nth-child(5) { width: 10%; }
        .ticket-table th:nth-child(6) { width: 12%; }
        .ticket-table th:nth-child(7) { width: 10%; }
        .ticket-table th:nth-child(8) { width: 10%; }
        .ticket-table th:nth-child(9) { width: 10%; }
        
        .ticket-subject-cell {
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .cat-badge {
            background: rgba(52,152,219,0.2);
            color: #3498db;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: clamp(1.125rem, 1.35vw, 1.5rem);
        }
        
        .status-badge {
            padding: 2px 6px;
            border-radius: 3px;
            font-size: clamp(1.125rem, 1.35vw, 1.5rem);
            font-weight: 500;
        }
        
        .status-badge.status-open { background: rgba(52,152,219,0.2); color: #3498db; }
        .status-badge.status-pending { background: rgba(241,196,15,0.2); color: #f1c40f; }
        .status-badge.status-in-progress { background: rgba(155,89,182,0.2); color: #9b59b6; }
        .status-badge.status-waiting { background: rgba(230,126,34,0.2); color: #e67e22; }
        .status-badge.status-resolved { background: rgba(46,204,113,0.2); color: #2ecc71; }
        
        .priority-badge {
            padding: 2px 6px;
            border-radius: 3px;
            font-size: clamp(1.125rem, 1.35vw, 1.5rem);
            font-weight: 600;
        }
        
        .priority-badge.priority-critical { background: rgba(231,76,60,0.3); color: #e74c3c; }
        .priority-badge.priority-high { background: rgba(230,126,34,0.3); color: #e67e22; }
        .priority-badge.priority-medium { background: rgba(241,196,15,0.2); color: #f1c40f; }
        .priority-badge.priority-low { background: rgba(46,204,113,0.2); color: #2ecc71; }
        
        .priority-row-critical { background: rgba(231,76,60,0.05); }
        .priority-row-high { background: rgba(230,126,34,0.03); }
        
        .ticket-id {
            font-weight: 600;
            color: #3498db;
            white-space: nowrap;
        }
        
        .subject-text {
            font-weight: 500;
        }
        
        .customer-text {
            font-size: clamp(1.125rem, 1.35vw, 1.5rem);
            color: #7f8c8d;
        }
        
        .assigned-cell .unassigned {
            color: #e74c3c;
            font-style: italic;
        }
        
        .ticket-age {
            color: #95a5a6;
            white-space: nowrap;
        }
        
        .los-alert {
            background: rgba(231,76,60,0.1);
            border-left-color: #e74c3c;
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
            font-size: 1.35rem;
            border: 2px solid #fff;
            overflow: hidden;
        }
        
        .avatar-circle img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .tech-name {
            font-size: clamp(1.2rem, 1.5vw, 1.65rem);
            color: #bdc3c7;
            max-width: clamp(40px, 4vw, 50px);
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
            gap: 4px;
        }
        
        .att-row {
            display: flex;
            align-items: center;
            gap: 0.5vw;
            padding: 0.4vh 0.5vw;
            background: rgba(0,0,0,0.2);
            border-radius: 4px;
            border-left: 3px solid;
        }
        
        .att-row.present { border-color: #27ae60; }
        .att-row.left { border-color: #3498db; }
        
        .att-avatar {
            width: clamp(24px, 2.5vw, 32px);
            height: clamp(24px, 2.5vw, 32px);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: clamp(0.75rem, 0.9vw, 1.05rem);
            flex-shrink: 0;
        }
        
        .att-avatar.present { background: rgba(39,174,96,0.3); color: #27ae60; }
        .att-avatar.left { background: rgba(52,152,219,0.3); color: #3498db; }
        
        .att-info {
            flex: 1;
            min-width: 0;
        }
        
        .att-name {
            font-size: clamp(1.2rem, 1.5vw, 1.65rem);
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .att-position {
            font-size: clamp(1.125rem, 1.35vw, 1.5rem);
            color: #95a5a6;
        }
        
        .att-time {
            font-size: clamp(1.2rem, 1.5vw, 1.65rem);
            color: #95a5a6;
            display: flex;
            gap: 4px;
            align-items: center;
        }
        
        .att-time i {
            font-size: clamp(0.75rem, 0.9vw, 1.05rem);
        }
        
        .los-section {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding-top: 0.5vh;
            margin-top: 0.5vh;
        }
        
        .los-list {
            display: flex;
            flex-direction: column;
            gap: 4px;
            max-height: 12vh;
            overflow-y: auto;
        }
        
        .los-item {
            display: flex;
            align-items: center;
            gap: 0.5vw;
            padding: 0.4vh 0.5vw;
            background: rgba(231,76,60,0.1);
            border-left: 3px solid #e74c3c;
            border-radius: 4px;
        }
        
        .los-info {
            flex: 1;
            min-width: 0;
        }
        
        .los-name {
            font-size: clamp(1.2rem, 1.5vw, 1.65rem);
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .los-meta {
            font-size: clamp(1.125rem, 1.35vw, 1.5rem);
            color: #95a5a6;
            font-family: monospace;
        }
        
        .assignment-row {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 1vw;
            flex-shrink: 0;
        }
        
        .assignment-card {
            background: rgba(255,255,255,0.03);
            border-radius: 6px;
            padding: 0.8vh 1vw;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        
        .assignment-card.unassigned-card {
            background: rgba(231,76,60,0.1);
            border: 1px solid rgba(231,76,60,0.3);
            min-width: 140px;
        }
        
        .assignment-header {
            display: flex;
            align-items: center;
            gap: 0.5vw;
            margin-bottom: 0.6vh;
            font-size: clamp(1.35rem, 1.65vw, 1.8rem);
            font-weight: 600;
            color: #ecf0f1;
        }
        
        .assignment-header i {
            font-size: clamp(0.75rem, 1.05vw, 1.05rem);
            color: #3498db;
        }
        
        .unassigned-card .assignment-header i {
            color: #e74c3c;
        }
        
        .assignment-total {
            margin-left: auto;
            background: rgba(52,152,219,0.2);
            color: #3498db;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: clamp(0.65rem, 0.8vw, 0.85rem);
            font-weight: 700;
        }
        
        .unassigned-card .assignment-total {
            background: rgba(231,76,60,0.2);
            color: #e74c3c;
        }
        
        .assignment-list {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
        }
        
        .assignment-item {
            display: flex;
            align-items: center;
            gap: 4px;
            background: rgba(0,0,0,0.2);
            padding: 2px 8px;
            border-radius: 3px;
            font-size: clamp(1.2rem, 1.5vw, 1.65rem);
        }
        
        .assign-name {
            color: #bdc3c7;
        }
        
        .assign-count {
            background: rgba(255,255,255,0.1);
            padding: 1px 5px;
            border-radius: 3px;
            font-weight: 600;
            color: #fff;
        }
        
        .unassigned-note {
            font-size: clamp(1.2rem, 1.5vw, 1.65rem);
            color: #95a5a6;
        }
        
        .empty-state-sm {
            font-size: clamp(1.2rem, 1.5vw, 1.65rem);
            color: #7f8c8d;
        }
        
        .branch-stats-row {
            flex-shrink: 0;
        }
        
        .branch-stats-row .panel {
            background: rgba(255,255,255,0.03);
            border-radius: 6px;
            padding: 0.5vh 1vw;
        }
        
        .branch-stats-row .panel-title {
            font-size: clamp(1.2rem, 1.5vw, 1.65rem);
            font-weight: 600;
            margin-bottom: 0.5vh;
            display: flex;
            align-items: center;
            gap: 0.5vw;
        }
        
        .branch-stats-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5vw;
        }
        
        .branch-stat-card {
            background: rgba(0,0,0,0.2);
            border-radius: 4px;
            padding: 0.3vh 0.6vw;
            display: flex;
            align-items: center;
            gap: 0.5vw;
        }
        
        .branch-name {
            font-size: clamp(1.2rem, 1.5vw, 1.65rem);
            font-weight: 600;
            color: #ecf0f1;
        }
        
        .branch-total {
            font-size: clamp(0.7rem, 0.85vw, 0.9rem);
            font-weight: 700;
            color: #3498db;
        }
        
        .branch-breakdown {
            display: flex;
            gap: 4px;
        }
        
        .branch-status {
            font-size: clamp(0.75rem, 0.825vw, 0.9rem);
            padding: 1px 5px;
            border-radius: 2px;
            font-weight: 500;
        }
        
        .branch-status.open {
            background: rgba(231,76,60,0.2);
            color: #e74c3c;
        }
        
        .branch-status.pending {
            background: rgba(241,196,15,0.2);
            color: #f1c40f;
        }
        
        .branch-status.progress {
            background: rgba(52,152,219,0.2);
            color: #3498db;
        }
        
        .data-table {
            width: 100%;
            font-size: clamp(1.2rem, 1.5vw, 1.65rem);
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
            font-size: 1.125rem;
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
        
        <div class="assignment-row">
            <div class="assignment-card">
                <div class="assignment-header">
                    <i class="bi bi-person-fill"></i>
                    <span>Assigned to Individuals</span>
                    <span class="assignment-total"><?= $ticketStats['assigned_individual'] ?? 0 ?></span>
                </div>
                <div class="assignment-list">
                    <?php foreach ($individualAssignments as $assign): ?>
                    <div class="assignment-item">
                        <span class="assign-name"><?= htmlspecialchars($assign['user_name']) ?></span>
                        <span class="assign-count"><?= $assign['ticket_count'] ?></span>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($individualAssignments)): ?>
                    <div class="empty-state-sm">No assignments</div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="assignment-card">
                <div class="assignment-header">
                    <i class="bi bi-people-fill"></i>
                    <span>Assigned to Teams</span>
                    <span class="assignment-total"><?= $ticketStats['assigned_group'] ?? 0 ?></span>
                </div>
                <div class="assignment-list">
                    <?php foreach ($teamAssignments as $assign): ?>
                    <div class="assignment-item">
                        <span class="assign-name"><?= htmlspecialchars($assign['team_name']) ?></span>
                        <span class="assign-count"><?= $assign['ticket_count'] ?></span>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($teamAssignments)): ?>
                    <div class="empty-state-sm">No team assignments</div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="assignment-card unassigned-card">
                <div class="assignment-header">
                    <i class="bi bi-exclamation-circle"></i>
                    <span>Unassigned</span>
                    <span class="assignment-total"><?= $ticketStats['unassigned'] ?? 0 ?></span>
                </div>
                <div class="unassigned-note">Tickets not assigned to any person or team</div>
            </div>
        </div>
        
        <div class="branch-stats-row">
            <div class="panel">
                <div class="panel-title"><i class="bi bi-building"></i> Tickets by Branch</div>
                <div class="branch-stats-grid">
                    <?php foreach ($branchStats as $branch): ?>
                    <div class="branch-stat-card">
                        <span class="branch-name"><?= htmlspecialchars($branch['branch_name']) ?>:</span>
                        <span class="branch-total"><?= $branch['total_tickets'] ?></span>
                        <span class="branch-breakdown">
                            <span class="branch-status open"><?= $branch['open_count'] ?>O</span>
                            <span class="branch-status pending"><?= $branch['pending_count'] ?>P</span>
                            <span class="branch-status progress"><?= $branch['in_progress_count'] ?>IP</span>
                        </span>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($branchStats)): ?>
                    <div class="empty-state-sm">No branches</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="bottom-section">
            <div class="panel ticket-table-panel">
                <div class="panel-title"><i class="bi bi-list-task"></i> Recent Tickets (Live)</div>
                <table class="ticket-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Priority</th>
                            <th>Subject / Customer</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Assigned To</th>
                            <th>Team</th>
                            <th>Branch</th>
                            <th>Age</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topOpenTickets as $ticket): ?>
                        <tr class="priority-row-<?= strtolower($ticket['priority'] ?? 'medium') ?>">
                            <td class="ticket-id">#<?= $ticket['id'] ?></td>
                            <td><span class="priority-badge priority-<?= strtolower($ticket['priority'] ?? 'medium') ?>"><?= htmlspecialchars($ticket['priority'] ?? 'Medium') ?></span></td>
                            <td class="ticket-subject-cell">
                                <div class="subject-text"><?= htmlspecialchars($ticket['subject']) ?></div>
                                <?php if ($ticket['customer_name']): ?><div class="customer-text"><?= htmlspecialchars($ticket['customer_name']) ?></div><?php endif; ?>
                            </td>
                            <td><span class="cat-badge"><?= htmlspecialchars($ticket['category'] ?? '-') ?></span></td>
                            <td><span class="status-badge status-<?= strtolower(str_replace(' ', '-', $ticket['status'])) ?>"><?= htmlspecialchars($ticket['status']) ?></span></td>
                            <td class="assigned-cell"><?= $ticket['assigned_name'] ? htmlspecialchars($ticket['assigned_name']) : '<span class="unassigned">Unassigned</span>' ?></td>
                            <td><?= $ticket['team_name'] ? htmlspecialchars($ticket['team_name']) : '-' ?></td>
                            <td><?= $ticket['branch_name'] ? htmlspecialchars($ticket['branch_name']) : '-' ?></td>
                            <td class="ticket-age"><?= timeOpen($ticket['created_at']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($topOpenTickets)): ?>
                        <tr><td colspan="9" class="empty-state">No open tickets</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <?php if ($criticalAlert): ?>
                <div class="alert-box">
                    <div class="alert-label"><i class="bi bi-exclamation-triangle-fill"></i> Critical</div>
                    <div class="alert-text"><?= htmlspecialchars($criticalAlert['subject']) ?> - Open <?= timeOpen($criticalAlert['created_at']) ?></div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($losOnus)): ?>
                <div class="alert-box los-alert">
                    <div class="alert-label"><i class="bi bi-wifi-off"></i> LOS (<?= count($losOnus) ?>)</div>
                    <div class="alert-text"><?= implode(', ', array_map(fn($o) => htmlspecialchars($o['customer_name'] ?: $o['name'] ?: $o['sn']), array_slice($losOnus, 0, 5))) ?><?= count($losOnus) > 5 ? '...' : '' ?></div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="panel tech-panel">
                <div class="panel-title">Technicians in the Field <span style="color:#27ae60;">(<?= $presentCount ?> present)</span></div>
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
