<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Settings.php';
require_once __DIR__ . '/../src/WhatsApp.php';

date_default_timezone_set('Africa/Nairobi');

header('Content-Type: application/json');

// Global error handler to ensure JSON output
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

try {
    // Support both CLI arguments and GET parameters
    $isCli = php_sapi_name() === 'cli';
    
    if ($isCli) {
        $action = $argv[1] ?? '';
        $secret = $argv[2] ?? 'cli-bypass';
    } else {
        $action = $_GET['action'] ?? '';
        $secret = $_GET['secret'] ?? '';
    }

    $settings = new \App\Settings();
    $cronSecret = $settings->get('cron_secret', 'isp-crm-cron-2024');

    // Allow CLI calls without secret (they're running from the container)
    if (!$isCli && $secret !== $cronSecret) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    $db = Database::getConnection();

    switch ($action) {
        case 'daily_summary':
            // Force send immediately (manual trigger only)
            sendDailySummaryToGroups($db, $settings);
            break;
            
        case 'scheduled_summaries':
        case 'check_schedule':
            // Scheduled - checks time and prevents duplicates
            checkAndSendScheduledSummaries($db, $settings);
            break;
            
        case 'sync_attendance':
        case 'biometric_sync':
            syncAttendanceFromDevices($db);
            break;
            
        case 'leave_accrual':
            runLeaveAccrual($db);
            break;
            
        default:
            echo json_encode(['error' => 'Unknown action', 'available' => ['daily_summary', 'scheduled_summaries', 'check_schedule', 'sync_attendance', 'biometric_sync', 'leave_accrual']]);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
}

function sendDailySummaryToGroups(\PDO $db, \App\Settings $settings): void {
    $whatsapp = new \App\WhatsApp();
    
    // Debug: Check what the settings return
    $whatsappEnabledSetting = $settings->get('whatsapp_enabled', 'NOT_SET');
    
    if (!$whatsapp->isEnabled()) {
        echo json_encode([
            'success' => false, 
            'error' => 'WhatsApp disabled',
            'debug' => [
                'whatsapp_enabled_setting' => $whatsappEnabledSetting,
                'expected' => '1',
                'check' => $whatsappEnabledSetting === '1' ? 'pass' : 'fail'
            ]
        ]);
        return;
    }
    
    $today = date('Y-m-d');
    $summaryType = date('H') < 12 ? 'morning' : 'evening';
    
    $stmt = $db->query("
        SELECT 
            e.id, e.name, e.department_id,
            a.clock_in, a.clock_out, a.hours_worked, a.late_minutes,
            d.name as department_name
        FROM employees e
        LEFT JOIN attendance a ON e.id = a.employee_id AND a.date = '$today'
        LEFT JOIN departments d ON e.department_id = d.id
        WHERE e.employment_status = 'active'
        ORDER BY d.name, e.name
    ");
    $employees = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
    $stmt = $db->query("
        SELECT 
            t.id, t.ticket_number, t.subject, t.status, t.priority, t.assigned_to,
            u.name as assigned_name, c.name as customer_name
        FROM tickets t
        LEFT JOIN users u ON t.assigned_to = u.id
        LEFT JOIN customers c ON t.customer_id = c.id
        WHERE DATE(t.created_at) = '$today' OR DATE(t.updated_at) = '$today'
        ORDER BY t.priority DESC, t.updated_at DESC
    ");
    $tickets = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
    $message = "*📊 DAILY TEAM SUMMARY*\n";
    $message .= "Date: " . date('l, M j, Y') . "\n";
    $message .= "Time: " . date('h:i A') . " (" . ($summaryType === 'morning' ? 'Morning Report' : 'Evening Report') . ")\n\n";
    
    $message .= "*👥 ATTENDANCE*\n";
    $present = array_filter($employees, fn($e) => !empty($e['clock_in']));
    $absent = array_filter($employees, fn($e) => empty($e['clock_in']));
    $lateCount = array_filter($employees, fn($e) => ($e['late_minutes'] ?? 0) > 0);
    
    $message .= "Present: " . count($present) . " | Absent: " . count($absent) . " | Late: " . count($lateCount) . "\n";
    
    if ($summaryType === 'evening') {
        $totalHours = array_sum(array_column($employees, 'hours_worked'));
        $message .= "Total Hours Worked: " . round($totalHours, 1) . " hrs\n";
    }
    
    $message .= "\n*🎫 TICKETS TODAY*\n";
    $newTickets = array_filter($tickets, fn($t) => $t['status'] === 'open');
    $inProgress = array_filter($tickets, fn($t) => $t['status'] === 'in_progress');
    $resolved = array_filter($tickets, fn($t) => in_array($t['status'], ['resolved', 'closed']));
    
    $message .= "New: " . count($newTickets) . " | In Progress: " . count($inProgress) . " | Resolved: " . count($resolved) . "\n";
    
    $criticalTickets = array_filter($tickets, fn($t) => $t['priority'] === 'critical' && $t['status'] !== 'closed');
    if (count($criticalTickets) > 0) {
        $message .= "\n*🔴 CRITICAL TICKETS*\n";
        foreach (array_slice($criticalTickets, 0, 5) as $t) {
            $message .= "• #{$t['ticket_number']}: {$t['subject']}\n";
            $message .= "  Assigned: " . ($t['assigned_name'] ?? 'Unassigned') . "\n";
        }
    }
    
    if ($summaryType === 'evening' && count($resolved) > 0) {
        $message .= "\n*✅ RESOLVED TODAY*\n";
        foreach (array_slice($resolved, 0, 10) as $t) {
            $message .= "• #{$t['ticket_number']}: {$t['subject']}\n";
        }
    }
    
    $message .= "\n_ISP CRM - " . ($settings->get('company_name', 'Your ISP')) . "_";
    
    $groupsJson = $settings->get('whatsapp_daily_summary_groups', '[]');
    $groups = json_decode($groupsJson, true) ?: [];
    $provider = $whatsapp->getProvider();
    
    $results = [];
    $errors = [];
    
    // Send to selected WhatsApp groups (requires session provider)
    if (count($groups) > 0) {
        if ($provider === 'session') {
            foreach ($groups as $group) {
                $groupId = $group['id'] ?? $group;
                if (!empty($groupId)) {
                    $result = $whatsapp->sendToGroup($groupId, $message);
                    $results[$groupId] = $result;
                    if (!$result['success']) {
                        $errors[] = "Group {$groupId}: " . ($result['error'] ?? 'Unknown error');
                    }
                }
            }
        } else {
            $errors[] = "Group messaging requires WhatsApp Session provider. Current: {$provider}";
        }
    }
    
    // Fallback: Send to phone numbers (works with any provider)
    $globalGroups = $settings->get('whatsapp_summary_groups', '');
    if (!empty($globalGroups)) {
        $phoneGroups = array_filter(array_map('trim', explode(',', $globalGroups)));
        foreach ($phoneGroups as $phone) {
            $result = $whatsapp->send($phone, $message);
            $results[$phone] = $result;
            if (!$result['success'] && $result['method'] !== 'web') {
                $errors[] = "Phone {$phone}: " . ($result['error'] ?? 'Unknown error');
            }
        }
    }
    
    // Department groups
    $deptStmt = $db->query("SELECT id FROM departments");
    $departments = $deptStmt->fetchAll(\PDO::FETCH_COLUMN);
    
    foreach ($departments as $deptId) {
        $deptGroup = $settings->get('whatsapp_group_dept_' . $deptId, '');
        if (!empty($deptGroup)) {
            if (str_contains($deptGroup, '@g.us')) {
                if ($provider === 'session') {
                    $result = $whatsapp->sendToGroup($deptGroup, $message);
                } else {
                    $result = ['success' => false, 'error' => 'Session provider required for groups'];
                    $errors[] = "Dept {$deptId}: Session provider required for group messaging";
                }
            } else {
                $result = $whatsapp->send($deptGroup, $message);
            }
            $results['dept_' . $deptId] = $result;
        }
    }
    
    // Branch-specific summaries
    $branchClass = new \App\Branch();
    $branchesWithGroups = $branchClass->getBranchesWithWhatsAppGroups();
    $allBranches = $branchClass->getAll();
    
    $branchSummaries = [];
    
    foreach ($branchesWithGroups as $branch) {
        $branchData = $branchClass->getBranchSummaryData($branch['id'], $today);
        $branchEmployees = $branchData['employees'];
        $branchTickets = $branchData['tickets'];
        
        $branchMessage = "*📊 " . strtoupper($branch['name']) . " DAILY SUMMARY*\n";
        $branchMessage .= "Date: " . date('l, M j, Y') . "\n";
        $branchMessage .= "Time: " . date('h:i A') . " (" . ($summaryType === 'morning' ? 'Morning Report' : 'Evening Report') . ")\n\n";
        
        $branchMessage .= "*👥 ATTENDANCE*\n";
        $branchPresent = array_filter($branchEmployees, fn($e) => !empty($e['clock_in']));
        $branchAbsent = array_filter($branchEmployees, fn($e) => empty($e['clock_in']));
        $branchLate = array_filter($branchEmployees, fn($e) => ($e['late_minutes'] ?? 0) > 0);
        
        $branchMessage .= "Present: " . count($branchPresent) . " | Absent: " . count($branchAbsent) . " | Late: " . count($branchLate) . "\n";
        
        if ($summaryType === 'evening') {
            $branchTotalHours = array_sum(array_column($branchEmployees, 'hours_worked'));
            $branchMessage .= "Total Hours: " . round($branchTotalHours, 1) . " hrs\n";
        }
        
        $branchMessage .= "\n*🎫 TICKETS*\n";
        $branchNew = array_filter($branchTickets, fn($t) => $t['status'] === 'open');
        $branchInProgress = array_filter($branchTickets, fn($t) => $t['status'] === 'in_progress');
        $branchResolved = array_filter($branchTickets, fn($t) => in_array($t['status'], ['resolved', 'closed']));
        
        $branchMessage .= "New: " . count($branchNew) . " | In Progress: " . count($branchInProgress) . " | Resolved: " . count($branchResolved) . "\n";
        
        $branchMessage .= "\n_" . ($branch['code'] ?? $branch['name']) . " - " . ($settings->get('company_name', 'Your ISP')) . "_";
        
        $branchSummaries[$branch['id']] = [
            'name' => $branch['name'],
            'code' => $branch['code'] ?? '',
            'employees' => count($branchEmployees),
            'present' => count($branchPresent),
            'absent' => count($branchAbsent),
            'late' => count($branchLate),
            'new_tickets' => count($branchNew),
            'in_progress' => count($branchInProgress),
            'resolved' => count($branchResolved)
        ];
        
        if ($provider === 'session') {
            $result = $whatsapp->sendToGroup($branch['whatsapp_group'], $branchMessage);
            $results['branch_' . $branch['id']] = $result;
            if (!$result['success']) {
                $errors[] = "Branch {$branch['name']}: " . ($result['error'] ?? 'Unknown error');
            }
        } else {
            $results['branch_' . $branch['id']] = ['success' => false, 'error' => 'Session provider required for branch groups'];
            $errors[] = "Branch {$branch['name']}: WhatsApp Session provider required for group messaging";
        }
    }
    
    // Daily Operations Group consolidated summary
    $operationsGroupId = $settings->get('whatsapp_operations_group_id', '');
    if (!empty($operationsGroupId) && $provider === 'session') {
        $totalTickets = count($tickets);
        $totalResolved = count($resolved);
        $totalInProgress = count($inProgress);
        $totalOpen = count($newTickets);
        $totalHours = round(array_sum(array_column($employees, 'hours_worked')), 1);
        
        $slaBreachedStmt = $db->query("
            SELECT COUNT(*) FROM tickets 
            WHERE DATE(created_at) = '$today' 
            AND (sla_response_breached = true OR sla_resolution_breached = true)
        ");
        $totalSlaBreached = $slaBreachedStmt->fetchColumn() ?: 0;
        
        $branchBreakdownText = "";
        foreach ($branchSummaries as $bs) {
            $branchBreakdownText .= "\n🏢 *{$bs['name']}*\n";
            $branchBreakdownText .= "  👥 Present: {$bs['present']}/{$bs['employees']} | Late: {$bs['late']}\n";
            $branchBreakdownText .= "  🎫 New: {$bs['new_tickets']} | Progress: {$bs['in_progress']} | Done: {$bs['resolved']}";
        }
        
        if (empty($branchBreakdownText)) {
            $branchBreakdownText = "No branch data available";
        }
        
        $topPerformersStmt = $db->query("
            SELECT u.name, COUNT(t.id) as resolved_count
            FROM tickets t
            JOIN users u ON t.assigned_to = u.id
            WHERE DATE(t.resolved_at) = '$today'
            GROUP BY u.id, u.name
            ORDER BY resolved_count DESC
            LIMIT 5
        ");
        $topPerformers = $topPerformersStmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $topPerformersText = "";
        $rank = 1;
        foreach ($topPerformers as $tp) {
            $medal = $rank === 1 ? '🥇' : ($rank === 2 ? '🥈' : ($rank === 3 ? '🥉' : '•'));
            $topPerformersText .= "{$medal} {$tp['name']}: {$tp['resolved_count']} tickets\n";
            $rank++;
        }
        
        if (empty($topPerformersText)) {
            $topPerformersText = "No resolved tickets today";
        }
        
        $opsTemplate = $settings->get('wa_template_operations_daily_summary', '');
        if (!empty($opsTemplate)) {
            $opsMessage = str_replace([
                '{date}', '{time}', '{company_name}',
                '{total_tickets}', '{total_resolved}', '{total_in_progress}', '{total_open}', '{total_sla_breached}',
                '{total_employees}', '{total_present}', '{total_absent}', '{total_late}', '{total_hours}',
                '{branch_summaries}', '{top_performers}', '{branch_count}'
            ], [
                date('l, M j, Y'), date('h:i A'), $settings->get('company_name', 'Your ISP'),
                $totalTickets, $totalResolved, $totalInProgress, $totalOpen, $totalSlaBreached,
                count($employees), count($present), count($absent), count($lateCount), $totalHours,
                $branchBreakdownText, $topPerformersText, count($allBranches)
            ], $opsTemplate);
        } else {
            $opsMessage = "📊 *DAILY OPERATIONS SUMMARY*\n";
            $opsMessage .= "📅 Date: " . date('l, M j, Y') . "\n";
            $opsMessage .= "🏢 Company: " . $settings->get('company_name', 'Your ISP') . "\n\n";
            
            $opsMessage .= "👥 *ATTENDANCE OVERVIEW*\n";
            $opsMessage .= "• Total Employees: " . count($employees) . "\n";
            $opsMessage .= "• Present: " . count($present) . "\n";
            $opsMessage .= "• Absent: " . count($absent) . "\n";
            $opsMessage .= "• Late: " . count($lateCount) . "\n";
            $opsMessage .= "• Hours Worked: {$totalHours} hrs\n\n";
            
            $opsMessage .= "📈 *TICKET STATISTICS*\n";
            $opsMessage .= "• Total Tickets Today: {$totalTickets}\n";
            $opsMessage .= "• Resolved: {$totalResolved}\n";
            $opsMessage .= "• In Progress: {$totalInProgress}\n";
            $opsMessage .= "• Open: {$totalOpen}\n";
            $opsMessage .= "• SLA Breached: {$totalSlaBreached}\n\n";
            
            $opsMessage .= "🏢 *BRANCH BREAKDOWN*" . $branchBreakdownText . "\n\n";
            
            $opsMessage .= "🏆 *TOP PERFORMERS*\n" . $topPerformersText . "\n";
            
            $opsMessage .= "⏰ Generated at " . date('h:i A');
        }
        
        $opsResult = $whatsapp->sendToGroup($operationsGroupId, $opsMessage);
        $results['operations_group'] = $opsResult;
        if (!$opsResult['success']) {
            $errors[] = "Daily Operations Group: " . ($opsResult['error'] ?? 'Unknown error');
        }
    }
    
    $successCount = count(array_filter($results, fn($r) => $r['success'] ?? false));
    
    echo json_encode([
        'success' => count($errors) === 0,
        'summary_type' => $summaryType,
        'employees_count' => count($employees),
        'tickets_count' => count($tickets),
        'groups_sent' => count($results),
        'success_count' => $successCount,
        'provider' => $provider,
        'branches_with_groups' => count($branchesWithGroups),
        'errors' => $errors,
        'results' => $results
    ]);
}

function checkAndSendScheduledSummaries(\PDO $db, \App\Settings $settings): void {
    $currentHour = (int)date('H');
    $currentMinute = (int)date('i');
    
    $morningHour = (int)$settings->get('daily_summary_morning_hour', '7');
    $eveningHour = (int)$settings->get('daily_summary_evening_hour', '18');
    
    $shouldSend = false;
    $summaryType = '';
    
    if ($currentHour === $morningHour && $currentMinute < 5) {
        $shouldSend = true;
        $summaryType = 'morning';
    } elseif ($currentHour === $eveningHour && $currentMinute < 5) {
        $shouldSend = true;
        $summaryType = 'evening';
    }
    
    if ($shouldSend) {
        $lastSent = $settings->get('last_daily_summary_' . $summaryType, '');
        $today = date('Y-m-d');
        
        if ($lastSent !== $today) {
            sendDailySummaryToGroups($db, $settings);
            $settings->set('last_daily_summary_' . $summaryType, $today);
            echo json_encode(['sent' => true, 'type' => $summaryType]);
        } else {
            echo json_encode(['sent' => false, 'reason' => 'Already sent today', 'type' => $summaryType]);
        }
    } else {
        echo json_encode([
            'sent' => false, 
            'reason' => 'Not scheduled time',
            'current_hour' => $currentHour,
            'morning_hour' => $morningHour,
            'evening_hour' => $eveningHour
        ]);
    }
}

function syncAttendanceFromDevices(\PDO $db): void {
    require_once __DIR__ . '/../src/BiometricDevice.php';
    require_once __DIR__ . '/../src/HikvisionDevice.php';
    require_once __DIR__ . '/../src/ZKTecoDevice.php';
    require_once __DIR__ . '/../src/RealTimeAttendanceProcessor.php';
    
    $stmt = $db->query("SELECT * FROM biometric_devices WHERE is_active = true");
    $devices = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
    $results = [];
    $processor = new \App\RealTimeAttendanceProcessor($db);
    
    $since = date('Y-m-d H:i:s', strtotime('-1 hour'));
    
    foreach ($devices as $deviceRow) {
        $deviceResult = ['device' => $deviceRow['name'], 'synced' => 0, 'errors' => []];
        
        try {
            $device = null;
            $password = null;
            if (!empty($deviceRow['password_encrypted'])) {
                $password = \App\BiometricDevice::decryptPassword($deviceRow['password_encrypted']);
            }
            
            if (strtolower($deviceRow['device_type']) === 'hikvision') {
                $device = new \App\HikvisionDevice(
                    (int)$deviceRow['id'],
                    $deviceRow['ip_address'],
                    (int)($deviceRow['port'] ?: 80),
                    $deviceRow['username'],
                    $password
                );
            } elseif (strtolower($deviceRow['device_type']) === 'zkteco') {
                $device = new \App\ZKTecoDevice(
                    (int)$deviceRow['id'],
                    $deviceRow['ip_address'],
                    (int)($deviceRow['port'] ?: 4370),
                    $deviceRow['username'],
                    $password
                );
            }
            
            if (!$device) {
                $deviceResult['errors'][] = 'Unknown device type: ' . $deviceRow['device_type'];
                $results[] = $deviceResult;
                continue;
            }
            
            $attendance = $device->getAttendance($since);
            
            foreach ($attendance as $record) {
                $processResult = $processor->processBiometricEvent(
                    (int)$deviceRow['id'],
                    (string)$record['device_user_id'],
                    $record['log_time'],
                    $record['direction'] ?? 'unknown',
                    $record['verification_type'] ?? 'unknown'
                );
                
                if ($processResult['success'] ?? false) {
                    $deviceResult['synced']++;
                }
            }
            
            $deviceResult['records_found'] = count($attendance);
            $deviceResult['verification_types'] = array_unique(array_column($attendance, 'verification_type'));
            
        } catch (\Throwable $e) {
            $deviceResult['errors'][] = $e->getMessage();
        }
        
        $results[] = $deviceResult;
    }
    
    $totalSynced = array_sum(array_column($results, 'synced'));
    
    echo json_encode([
        'success' => true,
        'total_synced' => $totalSynced,
        'devices' => $results
    ]);
}

function runLeaveAccrual(\PDO $db): void {
    try {
        $leaveService = new \App\Leave($db);
        $result = $leaveService->runMonthlyAccrual();
        
        echo json_encode([
            'success' => true,
            'processed' => $result['processed'],
            'errors' => $result['errors'],
            'month' => date('F Y')
        ]);
    } catch (\Throwable $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}
