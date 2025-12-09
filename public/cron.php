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
    $action = $_GET['action'] ?? '';
    $secret = $_GET['secret'] ?? '';

    $settings = new \App\Settings();
    $cronSecret = $settings->get('cron_secret', 'isp-crm-cron-2024');

    if ($secret !== $cronSecret) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    $db = Database::getConnection();

    switch ($action) {
        case 'daily_summary':
            sendDailySummaryToGroups($db, $settings);
            break;
            
        case 'check_schedule':
            checkAndSendScheduledSummaries($db, $settings);
            break;
            
        case 'sync_attendance':
            syncAttendanceFromDevices($db);
            break;
            
        default:
            echo json_encode(['error' => 'Unknown action', 'available' => ['daily_summary', 'check_schedule', 'sync_attendance']]);
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
