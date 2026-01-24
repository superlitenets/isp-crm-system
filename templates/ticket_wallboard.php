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
    WHERE LOWER(t.status) NOT IN ('closed', 'resolved')
    GROUP BY tm.id, tm.name
    ORDER BY ticket_count DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Assignment breakdown by individual
$individualAssignments = $db->query("
    SELECT u.name as user_name, COUNT(t.id) as ticket_count
    FROM tickets t
    INNER JOIN users u ON t.assigned_to = u.id
    WHERE LOWER(t.status) NOT IN ('closed', 'resolved')
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
    WHERE LOWER(t.status) NOT IN ('closed', 'resolved')
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
    WHERE LOWER(t.status) NOT IN ('closed', 'resolved')
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
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            color: #212529;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }
        
        .dashboard {
            display: flex;
            flex-direction: column;
            height: 100vh;
            padding: 1.2vh 1.5vw;
            gap: 1.2vh;
        }
        
        .header {
            text-align: center;
            padding: 0.5vh 0;
            flex-shrink: 0;
        }
        
        .header h1 {
            font-size: clamp(1.4rem, 2.5vw, 2rem);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: #2c3e50;
        }
        
        .status-cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr) 100px;
            gap: 1vw;
            flex-shrink: 0;
        }
        
        .status-card {
            border-radius: 10px;
            padding: 1.2vh 0.8vw;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            color: #fff;
        }
        
        .status-card .label {
            font-size: clamp(0.7rem, 0.9vw, 0.85rem);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 0.5vh;
            opacity: 0.95;
        }
        
        .status-card .value {
            font-size: clamp(2rem, 3.5vw, 2.8rem);
            font-weight: 800;
            line-height: 1;
        }
        
        .card-pending { background: linear-gradient(135deg, #dc3545 0%, #e74c3c 100%); }
        .card-progress { background: linear-gradient(135deg, #fd7e14 0%, #f39c12 100%); }
        .card-waiting { background: linear-gradient(135deg, #0d6efd 0%, #3498db 100%); }
        .card-critical { background: linear-gradient(135deg, #343a40 0%, #495057 100%); border: 2px solid #dc3545; }
        
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
            background: #f8f9fa;
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
            background: #fff;
            border-radius: 10px;
            padding: 1vh 1vw;
            display: flex;
            align-items: center;
            gap: 1vw;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
        }
        
        .metric-icon {
            width: clamp(36px, 3vw, 48px);
            height: clamp(36px, 3vw, 48px);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: clamp(1rem, 1.3vw, 1.3rem);
        }
        
        .metric-icon.clock { background: #e3f2fd; color: #1976d2; }
        .metric-icon.wrench { background: #f3e5f5; color: #7b1fa2; }
        .metric-icon.check { background: #e8f5e9; color: #388e3c; }
        
        .metric-info .label {
            font-size: clamp(0.65rem, 0.8vw, 0.75rem);
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .metric-info .value {
            font-size: clamp(1.2rem, 1.6vw, 1.5rem);
            font-weight: 700;
            color: #212529;
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
            flex: 0 0 280px;
            min-width: 240px;
        }
        
        .panel {
            background: #fff;
            border-radius: 10px;
            padding: 1vh 1vw;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
        }
        
        .panel-title {
            font-size: clamp(0.75rem, 0.95vw, 0.9rem);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 0.8vh;
            color: #495057;
            border-bottom: 2px solid #e9ecef;
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
            background: #fff;
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
            font-size: clamp(0.7rem, 0.85vw, 0.8rem);
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
            background: #f8f9fa;
            border-radius: 6px;
            margin-bottom: 8px;
            border: 1px solid #e9ecef;
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
            color: #fff;
        }
        
        .rank-1 { background: #dc3545; }
        .rank-2 { background: #fd7e14; }
        .rank-3 { background: #ffc107; color: #212529; }
        .rank-4 { background: #28a745; }
        .rank-5 { background: #0d6efd; }
        
        .ticket-info {
            flex: 1;
            min-width: 0;
        }
        
        .ticket-subject {
            font-size: clamp(0.8rem, 1vw, 0.95rem);
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: #212529;
        }
        
        .ticket-team {
            font-size: clamp(0.7rem, 0.85vw, 0.8rem);
            color: #0d6efd;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .ticket-team i {
            font-size: clamp(0.6rem, 0.7vw, 0.7rem);
        }
        
        .ticket-time {
            font-size: 0.75rem;
            color: #6c757d;
            font-weight: 500;
        }
        
        .alert-box {
            margin-top: 8px;
            background: #fff5f5;
            border-left: 4px solid #dc3545;
            border-radius: 4px;
            padding: 8px 12px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-label {
            font-size: clamp(0.7rem, 0.85vw, 0.8rem);
            font-weight: 700;
            color: #dc3545;
            text-transform: uppercase;
        }
        
        .alert-text {
            font-size: clamp(0.75rem, 0.9vw, 0.85rem);
            color: #495057;
        }
        
        .ticket-table {
            width: 100%;
            border-collapse: collapse;
            font-size: clamp(0.75rem, 0.9vw, 0.85rem);
            table-layout: fixed;
        }
        
        .ticket-table th {
            text-align: left;
            padding: 6px 8px;
            border-bottom: 2px solid #e9ecef;
            color: #6c757d;
            font-weight: 600;
            font-size: clamp(0.65rem, 0.8vw, 0.75rem);
            white-space: nowrap;
            text-transform: uppercase;
        }
        
        .ticket-table td {
            padding: 6px 8px;
            border-bottom: 1px solid #f1f3f4;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: #212529;
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
            background: #e3f2fd;
            color: #1976d2;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: clamp(0.6rem, 0.75vw, 0.7rem);
        }
        
        .status-badge {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: clamp(0.6rem, 0.75vw, 0.7rem);
            font-weight: 500;
        }
        
        .status-badge.status-open { background: #e3f2fd; color: #1976d2; }
        .status-badge.status-pending { background: #fff3e0; color: #f57c00; }
        .status-badge.status-in-progress { background: #f3e5f5; color: #7b1fa2; }
        .status-badge.status-waiting { background: #fff8e1; color: #ff8f00; }
        .status-badge.status-resolved { background: #e8f5e9; color: #388e3c; }
        
        .priority-badge {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: clamp(0.6rem, 0.75vw, 0.7rem);
            font-weight: 600;
        }
        
        .priority-badge.priority-critical { background: #ffebee; color: #c62828; }
        .priority-badge.priority-high { background: #fff3e0; color: #ef6c00; }
        .priority-badge.priority-medium { background: #fff8e1; color: #f9a825; }
        .priority-badge.priority-low { background: #e8f5e9; color: #388e3c; }
        
        .priority-row-critical { background: #fff5f5; }
        .priority-row-high { background: #fffbf0; }
        
        .ticket-id {
            font-weight: 600;
            color: #0d6efd;
            white-space: nowrap;
        }
        
        .subject-text {
            font-weight: 500;
            color: #212529;
        }
        
        .customer-text {
            font-size: clamp(0.6rem, 0.75vw, 0.7rem);
            color: #6c757d;
        }
        
        .assigned-cell .unassigned {
            color: #dc3545;
            font-style: italic;
        }
        
        .ticket-age {
            color: #6c757d;
            white-space: nowrap;
        }
        
        .los-alert {
            background: #fff5f5;
            border-left-color: #dc3545;
        }
        
        .technician-avatars {
            display: flex;
            justify-content: center;
            gap: 10px;
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
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #0d6efd, #6610f2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.85rem;
            color: #fff;
            border: 2px solid #fff;
            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
            overflow: hidden;
        }
        
        .avatar-circle img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .tech-name {
            font-size: clamp(0.65rem, 0.8vw, 0.75rem);
            color: #495057;
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
            gap: 4px;
        }
        
        .att-row {
            display: flex;
            align-items: center;
            gap: 0.6vw;
            padding: 0.5vh 0.6vw;
            background: #f8f9fa;
            border-radius: 6px;
            border-left: 4px solid;
        }
        
        .att-row.present { border-color: #28a745; background: #f0fff4; }
        .att-row.left { border-color: #6c757d; background: #f8f9fa; }
        
        .att-avatar {
            width: clamp(28px, 2.5vw, 36px);
            height: clamp(28px, 2.5vw, 36px);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: clamp(0.6rem, 0.75vw, 0.7rem);
            flex-shrink: 0;
            color: #fff;
        }
        
        .att-avatar.present { background: #28a745; }
        .att-avatar.left { background: #6c757d; }
        
        .att-info {
            flex: 1;
            min-width: 0;
        }
        
        .att-name {
            font-size: clamp(0.75rem, 0.9vw, 0.85rem);
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: #212529;
        }
        
        .att-position {
            font-size: clamp(0.6rem, 0.75vw, 0.7rem);
            color: #6c757d;
        }
        
        .att-time {
            font-size: clamp(0.65rem, 0.8vw, 0.75rem);
            color: #6c757d;
            display: flex;
            gap: 4px;
            align-items: center;
        }
        
        .att-time i {
            font-size: clamp(0.55rem, 0.7vw, 0.65rem);
        }
        
        .los-section {
            border-top: 1px solid #e9ecef;
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
            padding: 0.5vh 0.6vw;
            background: #fff5f5;
            border-left: 4px solid #dc3545;
            border-radius: 4px;
        }
        
        .los-info {
            flex: 1;
            min-width: 0;
        }
        
        .los-name {
            font-size: clamp(0.7rem, 0.85vw, 0.8rem);
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: #212529;
        }
        
        .los-meta {
            font-size: clamp(0.6rem, 0.75vw, 0.7rem);
            color: #6c757d;
            font-family: monospace;
        }
        
        .assignment-row {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 1vw;
            flex-shrink: 0;
        }
        
        .assignment-card {
            background: #fff;
            border-radius: 10px;
            padding: 1vh 1vw;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
        }
        
        .assignment-card.unassigned-card {
            background: #fff5f5;
            border: 1px solid #f8d7da;
            min-width: 140px;
        }
        
        .assignment-header {
            display: flex;
            align-items: center;
            gap: 0.5vw;
            margin-bottom: 0.6vh;
            font-size: clamp(0.7rem, 0.85vw, 0.8rem);
            font-weight: 600;
            color: #495057;
        }
        
        .assignment-header i {
            font-size: clamp(0.65rem, 0.8vw, 0.75rem);
            color: #0d6efd;
        }
        
        .unassigned-card .assignment-header i {
            color: #dc3545;
        }
        
        .assignment-total {
            margin-left: auto;
            background: #e3f2fd;
            color: #1976d2;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: clamp(0.6rem, 0.75vw, 0.7rem);
            font-weight: 700;
        }
        
        .unassigned-card .assignment-total {
            background: #ffebee;
            color: #c62828;
        }
        
        .assignment-list {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        
        .assignment-item {
            display: flex;
            align-items: center;
            gap: 4px;
            background: #f8f9fa;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: clamp(0.65rem, 0.8vw, 0.75rem);
            border: 1px solid #e9ecef;
        }
        
        .assign-name {
            color: #495057;
        }
        
        .assign-count {
            background: #0d6efd;
            padding: 1px 6px;
            border-radius: 4px;
            font-weight: 600;
            color: #fff;
            font-size: clamp(0.55rem, 0.7vw, 0.65rem);
        }
        
        .unassigned-note {
            font-size: clamp(0.65rem, 0.8vw, 0.75rem);
            color: #6c757d;
        }
        
        .empty-state-sm {
            font-size: clamp(0.65rem, 0.8vw, 0.75rem);
            color: #6c757d;
        }
        
        .branch-stats-row {
            flex-shrink: 0;
        }
        
        .branch-stats-row .panel {
            background: #fff;
            border-radius: 10px;
            padding: 0.8vh 1vw;
            border: 1px solid #e9ecef;
        }
        
        .branch-stats-row .panel-title {
            font-size: clamp(0.7rem, 0.85vw, 0.8rem);
            font-weight: 600;
            margin-bottom: 0.5vh;
            display: flex;
            align-items: center;
            gap: 0.5vw;
            color: #495057;
        }
        
        .branch-stats-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 0.6vw;
        }
        
        .branch-stat-card {
            background: #f8f9fa;
            border-radius: 6px;
            padding: 0.4vh 0.8vw;
            display: flex;
            align-items: center;
            gap: 0.5vw;
            border: 1px solid #e9ecef;
        }
        
        .branch-name {
            font-size: clamp(0.65rem, 0.8vw, 0.75rem);
            font-weight: 600;
            color: #212529;
        }
        
        .branch-total {
            font-size: clamp(0.6rem, 0.75vw, 0.7rem);
            font-weight: 700;
            color: #0d6efd;
        }
        
        .branch-breakdown {
            display: flex;
            gap: 4px;
        }
        
        .branch-status {
            font-size: clamp(0.55rem, 0.7vw, 0.65rem);
            padding: 2px 6px;
            border-radius: 3px;
            font-weight: 500;
        }
        
        .branch-status.open {
            background: #ffebee;
            color: #c62828;
        }
        
        .branch-status.pending {
            background: #fff8e1;
            color: #f9a825;
        }
        
        .branch-status.progress {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .data-table {
            width: 100%;
            font-size: clamp(0.7rem, 0.85vw, 0.8rem);
            border-collapse: collapse;
        }
        
        .data-table th {
            text-align: left;
            color: #6c757d;
            font-weight: 600;
            padding: 8px;
            border-bottom: 2px solid #e9ecef;
            font-size: 0.65rem;
            text-transform: uppercase;
        }
        
        .data-table td {
            padding: 8px;
            border-bottom: 1px solid #f1f3f4;
            color: #212529;
        }
        
        .live-indicator {
            position: fixed;
            top: 12px;
            right: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            background: #fff;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.75rem;
            color: #6c757d;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border: 1px solid #e9ecef;
        }
        
        .live-dot {
            width: 8px;
            height: 8px;
            background: #28a745;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(0.8); }
        }
        
        .empty-state {
            text-align: center;
            color: #6c757d;
            padding: 20px;
            font-size: 0.8rem;
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
