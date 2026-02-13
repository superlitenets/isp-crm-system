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
    
    $tz = $settings->get('timezone');
    if ($tz && in_array($tz, timezone_identifiers_list())) {
        date_default_timezone_set($tz);
    }
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
            
        case 'repost_incomplete':
            // Manually repost incomplete tickets to WhatsApp groups
            repostIncompleteTickets($db, $settings);
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
            
        case 'sla_notifications':
            checkSLAApproachingAndBreached($db, $settings);
            break;
            
        case 'attendance_reminder':
            sendDailyAttendanceReminder($db, $settings);
            break;
            
        default:
            echo json_encode(['error' => 'Unknown action', 'available' => ['daily_summary', 'repost_incomplete', 'scheduled_summaries', 'check_schedule', 'sync_attendance', 'biometric_sync', 'leave_accrual', 'sla_notifications', 'attendance_reminder']]);
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
            t.id, t.ticket_number, t.subject, t.status, t.priority, t.assigned_to,
            t.category, t.created_at,
            u.name as assigned_name, c.name as customer_name, c.phone as customer_phone
        FROM tickets t
        LEFT JOIN users u ON t.assigned_to = u.id
        LEFT JOIN customers c ON t.customer_id = c.id
        WHERE t.status NOT IN ('resolved', 'closed')
        ORDER BY 
            CASE t.priority 
                WHEN 'critical' THEN 1 
                WHEN 'high' THEN 2 
                WHEN 'medium' THEN 3 
                ELSE 4 
            END,
            t.created_at ASC
    ");
    $incompleteTickets = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
    $resolvedStmt = $db->prepare("SELECT COUNT(*) FROM tickets WHERE status = 'resolved' AND DATE(resolved_at) = ?");
    $resolvedStmt->execute([$today]);
    $resolvedToday = $resolvedStmt->fetchColumn();
    
    $newStmt = $db->prepare("SELECT COUNT(*) FROM tickets WHERE DATE(created_at) = ?");
    $newStmt->execute([$today]);
    $newToday = $newStmt->fetchColumn();
    
    $openTickets = array_filter($incompleteTickets, fn($t) => $t['status'] === 'open');
    $inProgressTickets = array_filter($incompleteTickets, fn($t) => $t['status'] === 'in_progress');
    $pendingTickets = array_filter($incompleteTickets, fn($t) => $t['status'] === 'pending');
    $criticalTickets = array_filter($incompleteTickets, fn($t) => $t['priority'] === 'critical');
    $highTickets = array_filter($incompleteTickets, fn($t) => $t['priority'] === 'high');
    $otherTickets = array_filter($incompleteTickets, fn($t) => !in_array($t['priority'], ['critical', 'high']));
    
    $companyName = $settings->get('company_name', 'Your ISP');
    
    $headerTpl = $settings->get('wa_template_daily_header', "*📋 {report_title}*\n\n📅 Date: {report_date}\n🕐 Time: {report_time}\n🏢 Branch: {branch_name}\n");
    $statsTpl = $settings->get('wa_template_daily_stats', "*📊 TICKET SUMMARY*\n🔢 Total Incomplete: {total_incomplete}\n🆕 Open: {open_count}\n🔄 In Progress: {in_progress_count}\n⏳ Pending: {pending_count}\n✅ Resolved Today: {resolved_today}\n");
    $criticalHeaderTpl = $settings->get('wa_template_daily_critical_header', "\n*🔴 CRITICAL ({count})*\n");
    $highHeaderTpl = $settings->get('wa_template_daily_high_header', "\n*🟠 HIGH PRIORITY ({count})*\n");
    $otherHeaderTpl = $settings->get('wa_template_daily_other_header', "\n*🟡 OTHER TICKETS ({count})*\n");
    $ticketLineTpl = $settings->get('wa_template_daily_ticket_line', "• #{ticket_number}: {subject}\n  👤 {assigned_name} | ⏱️ {age}d | 📍 {category}\n");
    $ticketSimpleTpl = $settings->get('wa_template_daily_ticket_simple', "• #{ticket_number}: {subject} ({priority})\n");
    $techSectionTpl = $settings->get('wa_template_daily_technician_section', "\n*👥 TECHNICIAN WORKLOAD*\n{technician_list}");
    $techLineTpl = $settings->get('wa_template_daily_tech_line', "• {name}: {open} open, {in_progress} in progress\n");
    $footerTpl = $settings->get('wa_template_daily_footer', "\n_ISP CRM - {company_name}_");
    $moreTpl = $settings->get('wa_template_daily_more', "  _...and {count} more_\n");
    
    $message = str_replace(
        ['{report_title}', '{report_date}', '{report_time}', '{branch_name}', '{company_name}'],
        ['INCOMPLETE TICKETS REPORT', date('l, M j, Y'), date('h:i A'), 'All Branches', $companyName],
        $headerTpl
    );
    
    $message .= str_replace(
        ['{total_incomplete}', '{open_count}', '{in_progress_count}', '{pending_count}', '{resolved_today}', '{new_today}'],
        [count($incompleteTickets), count($openTickets), count($inProgressTickets), count($pendingTickets), $resolvedToday, $newToday],
        $statsTpl
    );
    
    if (count($criticalTickets) > 0) {
        $message .= str_replace('{count}', count($criticalTickets), $criticalHeaderTpl);
        foreach ($criticalTickets as $t) {
            $age = floor((time() - strtotime($t['created_at'])) / 86400);
            $message .= str_replace(
                ['{ticket_number}', '{subject}', '{assigned_name}', '{age}', '{category}', '{status}', '{customer_name}', '{customer_phone}', '{priority}'],
                [$t['ticket_number'], $t['subject'], $t['assigned_name'] ?? 'Unassigned', $age, $t['category'] ?? '-', $t['status'], $t['customer_name'] ?? '-', $t['customer_phone'] ?? '-', $t['priority']],
                $ticketLineTpl
            );
        }
    }
    
    if (count($highTickets) > 0) {
        $message .= str_replace('{count}', count($highTickets), $highHeaderTpl);
        foreach (array_slice($highTickets, 0, 10) as $t) {
            $age = floor((time() - strtotime($t['created_at'])) / 86400);
            $message .= str_replace(
                ['{ticket_number}', '{subject}', '{assigned_name}', '{age}', '{category}', '{status}', '{customer_name}', '{customer_phone}', '{priority}'],
                [$t['ticket_number'], $t['subject'], $t['assigned_name'] ?? 'Unassigned', $age, $t['category'] ?? '-', $t['status'], $t['customer_name'] ?? '-', $t['customer_phone'] ?? '-', $t['priority']],
                $ticketLineTpl
            );
        }
        if (count($highTickets) > 10) {
            $message .= str_replace('{count}', count($highTickets) - 10, $moreTpl);
        }
    }
    
    if (count($otherTickets) > 0) {
        $message .= str_replace('{count}', count($otherTickets), $otherHeaderTpl);
        foreach (array_slice($otherTickets, 0, 10) as $t) {
            $message .= str_replace(
                ['{ticket_number}', '{subject}', '{priority}'],
                [$t['ticket_number'], $t['subject'], $t['priority']],
                $ticketSimpleTpl
            );
        }
        if (count($otherTickets) > 10) {
            $message .= str_replace('{count}', count($otherTickets) - 10, $moreTpl);
        }
    }
    
    $techStmt = $db->query("
        SELECT u.id, u.name, 
            COUNT(CASE WHEN t.status = 'open' THEN 1 END) as open_count,
            COUNT(CASE WHEN t.status = 'in_progress' THEN 1 END) as in_progress_count,
            COUNT(CASE WHEN t.status = 'resolved' AND DATE(t.resolved_at) = CURRENT_DATE THEN 1 END) as resolved_today
        FROM users u
        LEFT JOIN tickets t ON t.assigned_to = u.id AND t.status NOT IN ('closed')
        WHERE u.role IN ('technician', 'admin', 'manager')
        GROUP BY u.id, u.name
        HAVING COUNT(t.id) > 0
        ORDER BY open_count DESC, in_progress_count DESC
        LIMIT 10
    ");
    $techStats = $techStmt->fetchAll(\PDO::FETCH_ASSOC);
    
    if (!empty($techStats)) {
        $techList = '';
        foreach ($techStats as $tech) {
            $techList .= str_replace(
                ['{name}', '{open}', '{in_progress}', '{resolved_today}'],
                [$tech['name'], $tech['open_count'], $tech['in_progress_count'], $tech['resolved_today']],
                $techLineTpl
            );
        }
        $message .= str_replace('{technician_list}', $techList, $techSectionTpl);
    }
    
    $message .= str_replace('{company_name}', $companyName, $footerTpl);
    
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
    
    // Branch-specific summaries (incomplete tickets only)
    $branchClass = new \App\Branch();
    $branchesWithGroups = $branchClass->getBranchesWithWhatsAppGroups();
    $allBranches = $branchClass->getAll();
    
    $branchSummaries = [];
    
    foreach ($branchesWithGroups as $branch) {
        $branchIncompleteStmt = $db->prepare("
            SELECT t.id, t.ticket_number, t.subject, t.status, t.priority, t.created_at,
                   u.name as assigned_name
            FROM tickets t
            LEFT JOIN users u ON t.assigned_to = u.id
            WHERE t.branch_id = ? AND t.status NOT IN ('resolved', 'closed')
            ORDER BY 
                CASE t.priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END,
                t.created_at ASC
        ");
        $branchIncompleteStmt->execute([$branch['id']]);
        $branchIncomplete = $branchIncompleteStmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $branchMessage = "*📋 " . strtoupper($branch['name']) . " INCOMPLETE TICKETS*\n";
        $branchMessage .= "Date: " . date('l, M j, Y') . "\n";
        $branchMessage .= "Time: " . date('h:i A') . "\n\n";
        
        $branchOpen = array_filter($branchIncomplete, fn($t) => $t['status'] === 'open');
        $branchInProgress = array_filter($branchIncomplete, fn($t) => $t['status'] === 'in_progress');
        
        $branchMessage .= "*📊 Total: " . count($branchIncomplete) . "*\n";
        $branchMessage .= "Open: " . count($branchOpen) . " | In Progress: " . count($branchInProgress) . "\n\n";
        
        $branchCritical = array_filter($branchIncomplete, fn($t) => $t['priority'] === 'critical');
        if (count($branchCritical) > 0) {
            $branchMessage .= "*🔴 CRITICAL*\n";
            foreach ($branchCritical as $t) {
                $age = floor((time() - strtotime($t['created_at'])) / 86400);
                $branchMessage .= "• #{$t['ticket_number']}: {$t['subject']} ({$age}d)\n";
            }
            $branchMessage .= "\n";
        }
        
        foreach (array_slice($branchIncomplete, 0, 10) as $t) {
            if ($t['priority'] !== 'critical') {
                $age = floor((time() - strtotime($t['created_at'])) / 86400);
                $branchMessage .= "• #{$t['ticket_number']}: {$t['subject']} ({$t['priority']}, {$age}d)\n";
            }
        }
        if (count($branchIncomplete) > 10) {
            $branchMessage .= "_...and " . (count($branchIncomplete) - 10) . " more_\n";
        }
        
        $branchMessage .= "\n_" . ($branch['code'] ?? $branch['name']) . " - " . ($settings->get('company_name', 'Your ISP')) . "_";
        
        $branchSummaries[$branch['id']] = [
            'name' => $branch['name'],
            'code' => $branch['code'] ?? '',
            'total_incomplete' => count($branchIncomplete),
            'open' => count($branchOpen),
            'in_progress' => count($branchInProgress)
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
    
    // Daily Operations Group consolidated summary (incomplete tickets focus)
    $operationsGroupId = $settings->get('whatsapp_operations_group_id', '');
    if (!empty($operationsGroupId) && $provider === 'session') {
        $totalIncomplete = count($incompleteTickets);
        $totalOpen = count($openTickets);
        $totalInProgress = count($inProgressTickets);
        
        $slaBreachedStmt = $db->query("
            SELECT COUNT(*) FROM tickets 
            WHERE status NOT IN ('resolved', 'closed')
            AND (sla_response_breached = true OR sla_resolution_breached = true)
        ");
        $totalSlaBreached = $slaBreachedStmt->fetchColumn() ?: 0;
        
        $branchBreakdownText = "";
        foreach ($branchSummaries as $bs) {
            $branchBreakdownText .= "\n🏢 *{$bs['name']}*: {$bs['total_incomplete']} incomplete (Open: {$bs['open']}, In Progress: {$bs['in_progress']})";
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
                '{total_incomplete}', '{total_in_progress}', '{total_open}', '{total_sla_breached}',
                '{branch_summaries}', '{top_performers}', '{branch_count}'
            ], [
                date('l, M j, Y'), date('h:i A'), $settings->get('company_name', 'Your ISP'),
                $totalIncomplete, $totalInProgress, $totalOpen, $totalSlaBreached,
                $branchBreakdownText, $topPerformersText, count($allBranches)
            ], $opsTemplate);
        } else {
            $opsMessage = "📋 *INCOMPLETE TICKETS REPORT*\n";
            $opsMessage .= "📅 Date: " . date('l, M j, Y') . "\n";
            $opsMessage .= "🏢 Company: " . $settings->get('company_name', 'Your ISP') . "\n\n";
            
            $opsMessage .= "📈 *TICKET SUMMARY*\n";
            $opsMessage .= "• Total Incomplete: {$totalIncomplete}\n";
            $opsMessage .= "• Open: {$totalOpen}\n";
            $opsMessage .= "• In Progress: {$totalInProgress}\n";
            $opsMessage .= "• SLA Breached: {$totalSlaBreached}\n\n";
            
            $opsMessage .= "🏢 *BY BRANCH*" . $branchBreakdownText . "\n\n";
            
            $opsMessage .= "🏆 *TOP PERFORMERS TODAY*\n" . $topPerformersText . "\n";
            
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
        'incomplete_tickets' => count($incompleteTickets),
        'open_tickets' => count($openTickets),
        'in_progress_tickets' => count($inProgressTickets),
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
    // Use PostgreSQL advisory lock to prevent overlapping cron executions
    $lockId = 12345; // Unique lock ID for biometric sync
    $lockResult = $db->query("SELECT pg_try_advisory_lock($lockId)")->fetchColumn();
    
    if (!$lockResult) {
        echo json_encode(['success' => false, 'error' => 'Another sync is already running']);
        return;
    }
    
    try {
        require_once __DIR__ . '/../src/BiometricDevice.php';
        require_once __DIR__ . '/../src/HikvisionDevice.php';
        require_once __DIR__ . '/../src/ZKTecoDevice.php';
        require_once __DIR__ . '/../src/BioTimeCloud.php';
        require_once __DIR__ . '/../src/RealTimeAttendanceProcessor.php';
        
        $stmt = $db->query("SELECT * FROM biometric_devices WHERE is_active = true");
        $devices = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
    $results = [];
    $processor = new \App\RealTimeAttendanceProcessor($db);
    
    foreach ($devices as $deviceRow) {
        $deviceResult = ['device' => $deviceRow['name'], 'synced' => 0, 'errors' => []];
        
        // Calculate since time: use last_sync_at if available, otherwise start of today
        if (!empty($deviceRow['last_sync_at'])) {
            // Go back 5 minutes from last sync to handle any clock skew
            $since = date('Y-m-d H:i:s', strtotime($deviceRow['last_sync_at'] . ' -5 minutes'));
        } else {
            // First sync: get all records from start of today
            $since = date('Y-m-d 00:00:00');
        }
        
        $deviceResult['sync_since'] = $since;
        
        try {
            // Use the factory method to create appropriate device instance
            $device = \App\BiometricDevice::create($deviceRow);
            
            if (!$device) {
                $deviceResult['errors'][] = 'Unknown device type: ' . $deviceRow['device_type'];
                $results[] = $deviceResult;
                continue;
            }
            
            $attendance = $device->getAttendance($since);
            $latestTime = null;
            
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
                
                // Track the latest record time
                if (!$latestTime || $record['log_time'] > $latestTime) {
                    $latestTime = $record['log_time'];
                }
            }
            
            $deviceResult['records_found'] = count($attendance);
            $deviceResult['verification_types'] = array_unique(array_column($attendance, 'verification_type'));
            
            // Update last_sync_at for this device
            $newSyncTime = $latestTime ?: date('Y-m-d H:i:s');
            $updateStmt = $db->prepare("UPDATE biometric_devices SET last_sync_at = ? WHERE id = ?");
            $updateStmt->execute([$newSyncTime, $deviceRow['id']]);
            $deviceResult['last_sync_updated'] = $newSyncTime;
            
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
    } finally {
        // Release the advisory lock
        $db->query("SELECT pg_advisory_unlock(12345)");
    }
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

function repostIncompleteTickets(\PDO $db, \App\Settings $settings): void {
    $whatsapp = new \App\WhatsApp();
    
    if (!$whatsapp->isEnabled()) {
        echo json_encode(['success' => false, 'error' => 'WhatsApp disabled']);
        return;
    }
    
    $provider = $whatsapp->getProvider();
    if ($provider !== 'session') {
        echo json_encode(['success' => false, 'error' => 'Repost requires WhatsApp Session provider']);
        return;
    }
    
    $stmt = $db->query("
        SELECT 
            t.id, t.ticket_number, t.subject, t.status, t.priority, t.category,
            t.created_at, t.branch_id,
            u.name as assigned_name, c.name as customer_name, c.phone as customer_phone
        FROM tickets t
        LEFT JOIN users u ON t.assigned_to = u.id
        LEFT JOIN customers c ON t.customer_id = c.id
        WHERE t.status NOT IN ('resolved', 'closed')
        ORDER BY 
            CASE t.priority 
                WHEN 'critical' THEN 1 
                WHEN 'high' THEN 2 
                WHEN 'medium' THEN 3 
                ELSE 4 
            END,
            t.created_at ASC
    ");
    $incompleteTickets = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
    if (count($incompleteTickets) === 0) {
        echo json_encode(['success' => true, 'message' => 'No incomplete tickets to repost']);
        return;
    }
    
    $message = "*🔄 INCOMPLETE TICKETS REPOST*\n";
    $message .= "Time: " . date('h:i A, M j, Y') . "\n";
    $message .= "Total: " . count($incompleteTickets) . " tickets\n\n";
    
    $criticalTickets = array_filter($incompleteTickets, fn($t) => $t['priority'] === 'critical');
    if (count($criticalTickets) > 0) {
        $message .= "*🔴 CRITICAL (" . count($criticalTickets) . ")*\n";
        foreach ($criticalTickets as $t) {
            $age = floor((time() - strtotime($t['created_at'])) / 86400);
            $message .= "• #{$t['ticket_number']}: {$t['subject']}\n";
            $message .= "  👤 " . ($t['assigned_name'] ?? 'Unassigned') . " | ⏱️ {$age}d\n";
        }
        $message .= "\n";
    }
    
    $highTickets = array_filter($incompleteTickets, fn($t) => $t['priority'] === 'high');
    if (count($highTickets) > 0) {
        $message .= "*🟠 HIGH (" . count($highTickets) . ")*\n";
        foreach (array_slice($highTickets, 0, 10) as $t) {
            $age = floor((time() - strtotime($t['created_at'])) / 86400);
            $message .= "• #{$t['ticket_number']}: {$t['subject']} ({$age}d)\n";
        }
        if (count($highTickets) > 10) {
            $message .= "  _+" . (count($highTickets) - 10) . " more_\n";
        }
        $message .= "\n";
    }
    
    $otherTickets = array_filter($incompleteTickets, fn($t) => !in_array($t['priority'], ['critical', 'high']));
    if (count($otherTickets) > 0) {
        $message .= "*🟡 MEDIUM/LOW (" . count($otherTickets) . ")*\n";
        foreach (array_slice($otherTickets, 0, 10) as $t) {
            $age = floor((time() - strtotime($t['created_at'])) / 86400);
            $message .= "• #{$t['ticket_number']}: {$t['subject']} ({$age}d)\n";
        }
        if (count($otherTickets) > 10) {
            $message .= "  _+" . (count($otherTickets) - 10) . " more_\n";
        }
    }
    
    $message .= "\n_" . ($settings->get('company_name', 'Your ISP')) . " - Repost_";
    
    $results = [];
    $errors = [];
    
    $groupsJson = $settings->get('whatsapp_daily_summary_groups', '[]');
    $groups = json_decode($groupsJson, true) ?: [];
    
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
    
    $operationsGroupId = $settings->get('whatsapp_operations_group_id', '');
    if (!empty($operationsGroupId)) {
        $result = $whatsapp->sendToGroup($operationsGroupId, $message);
        $results['operations_group'] = $result;
        if (!$result['success']) {
            $errors[] = "Operations Group: " . ($result['error'] ?? 'Unknown error');
        }
    }
    
    $successCount = count(array_filter($results, fn($r) => $r['success'] ?? false));
    
    echo json_encode([
        'success' => count($errors) === 0,
        'incomplete_tickets' => count($incompleteTickets),
        'groups_sent' => count($results),
        'success_count' => $successCount,
        'errors' => $errors
    ]);
}

function checkSLAApproachingAndBreached(\PDO $db, \App\Settings $settings): void {
    $whatsapp = new \App\WhatsApp();
    $sla = new \App\SLA();
    $companyName = $settings->get('company_name', 'Your ISP');
    $now = new \DateTime();
    $warnings = [];
    $breaches = [];
    $errors = [];

    $stmt = $db->query("
        SELECT t.*, 
               c.full_name AS customer_name, c.phone AS customer_phone,
               u.full_name AS technician_name, u.phone AS technician_phone,
               u.email AS technician_email
        FROM tickets t
        LEFT JOIN customers c ON t.customer_id = c.id
        LEFT JOIN users u ON t.assigned_to = u.id
        WHERE t.status NOT IN ('resolved', 'closed', 'cancelled')
          AND t.sla_resolution_due IS NOT NULL
          AND t.assigned_to IS NOT NULL
    ");
    $tickets = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    foreach ($tickets as $ticket) {
        $resolutionDue = new \DateTime($ticket['sla_resolution_due']);
        $slaStart = !empty($ticket['sla_started_at']) ? strtotime($ticket['sla_started_at']) : strtotime($ticket['created_at']);
        $totalDuration = $resolutionDue->getTimestamp() - $slaStart;
        $timeLeft = $resolutionDue->getTimestamp() - $now->getTimestamp();

        if ($timeLeft <= 0 && empty($ticket['sla_breach_notified_at'])) {
            $phone = $ticket['technician_phone'] ?? null;
            if ($phone) {
                $message = "*SLA BREACHED - Urgent Action Required*\n\n";
                $message .= "Ticket: #{$ticket['ticket_number']}\n";
                $message .= "Subject: {$ticket['subject']}\n";
                $message .= "Customer: {$ticket['customer_name']}\n";
                $message .= "Priority: " . ucfirst($ticket['priority'] ?? 'medium') . "\n";
                $message .= "Due: " . $resolutionDue->format('d M Y H:i') . "\n";
                $message .= "Overdue by: " . formatTimeDiff(abs($timeLeft)) . "\n\n";
                $message .= "Please resolve this ticket immediately or escalate.\n";
                $message .= "_$companyName - SLA Alert_";

                $sendResult = $whatsapp->send($phone, $message);
                if ($sendResult['success'] ?? false) {
                    $db->prepare("UPDATE tickets SET sla_breach_notified_at = NOW() WHERE id = ?")->execute([$ticket['id']]);
                    $sla->logSLAEvent($ticket['id'], 'breach_notified', "WhatsApp breach notification sent to {$ticket['technician_name']}");
                    $breaches[] = $ticket['ticket_number'];
                } else {
                    $errors[] = "Breach notification failed for #{$ticket['ticket_number']}: " . ($sendResult['error'] ?? 'unknown');
                }
            }
        } elseif ($timeLeft > 0 && $totalDuration > 0 && empty($ticket['sla_warning_notified_at'])) {
            $warningThreshold = $totalDuration * 0.2;
            if ($timeLeft < $warningThreshold) {
                $phone = $ticket['technician_phone'] ?? null;
                if ($phone) {
                    $message = "*SLA Warning - Approaching Deadline*\n\n";
                    $message .= "Ticket: #{$ticket['ticket_number']}\n";
                    $message .= "Subject: {$ticket['subject']}\n";
                    $message .= "Customer: {$ticket['customer_name']}\n";
                    $message .= "Priority: " . ucfirst($ticket['priority'] ?? 'medium') . "\n";
                    $message .= "Due: " . $resolutionDue->format('d M Y H:i') . "\n";
                    $message .= "Time remaining: " . formatTimeDiff($timeLeft) . "\n\n";
                    $message .= "Please prioritize this ticket to avoid SLA breach.\n";
                    $message .= "_$companyName - SLA Alert_";

                    $sendResult = $whatsapp->send($phone, $message);
                    if ($sendResult['success'] ?? false) {
                        $db->prepare("UPDATE tickets SET sla_warning_notified_at = NOW() WHERE id = ?")->execute([$ticket['id']]);
                        $sla->logSLAEvent($ticket['id'], 'warning_notified', "WhatsApp warning notification sent to {$ticket['technician_name']}");
                        $warnings[] = $ticket['ticket_number'];
                    } else {
                        $errors[] = "Warning notification failed for #{$ticket['ticket_number']}: " . ($sendResult['error'] ?? 'unknown');
                    }
                }
            }
        }
    }

    $supervisorPhone = $settings->get('sla_supervisor_phone', '');
    if (!empty($supervisorPhone) && (count($breaches) > 0)) {
        $supervisorMsg = "*SLA Breach Summary*\n\n";
        $supervisorMsg .= "Breached tickets: " . count($breaches) . "\n";
        foreach ($breaches as $tn) {
            $supervisorMsg .= "  - #{$tn}\n";
        }
        $supervisorMsg .= "\nPlease review and take action.\n";
        $supervisorMsg .= "_$companyName - SLA Alert_";
        $whatsapp->send($supervisorPhone, $supervisorMsg);
    }

    echo json_encode([
        'success' => count($errors) === 0,
        'warnings_sent' => count($warnings),
        'breaches_sent' => count($breaches),
        'total_tickets_checked' => count($tickets),
        'errors' => $errors
    ]);
}

function formatTimeDiff(int $seconds): string {
    if ($seconds < 60) return "{$seconds}s";
    $minutes = floor($seconds / 60);
    if ($minutes < 60) return "{$minutes}m";
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;
    if ($hours < 24) return "{$hours}h {$mins}m";
    $days = floor($hours / 24);
    $hrs = $hours % 24;
    return "{$days}d {$hrs}h";
}

function sendDailyAttendanceReminder(\PDO $db, \App\Settings $settings): void {
    $dayOfWeek = (int)date('N');
    if ($dayOfWeek === 7) {
        echo json_encode(['success' => true, 'message' => 'Sunday - no reminder sent', 'sent' => 0]);
        return;
    }

    $whatsapp = new \App\WhatsApp();
    $companyName = $settings->get('company_name', 'Your ISP');
    $workStartTime = $settings->get('work_start_time', '08:00');
    $workStartFormatted = date('g:i A', strtotime($workStartTime));

    $stmt = $db->query("
        SELECT id, full_name, phone 
        FROM users 
        WHERE status = 'active' 
          AND role != 'customer'
          AND phone IS NOT NULL 
          AND phone != ''
    ");
    $employees = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    $today = date('Y-m-d');
    $sent = 0;
    $errors = [];

    foreach ($employees as $emp) {
        $alreadyClocked = $db->prepare("
            SELECT id FROM attendance WHERE employee_id = ? AND date = ? AND clock_in IS NOT NULL LIMIT 1
        ");
        $alreadyClocked->execute([$emp['id'], $today]);
        if ($alreadyClocked->fetch()) {
            continue;
        }

        $message = "Good morning {$emp['full_name']}!\n\n";
        $message .= "This is your daily attendance reminder.\n";
        $message .= "Please clock in before {$workStartFormatted}.\n\n";
        $message .= "Have a productive day!\n";
        $message .= "_$companyName - HR_";

        $result = $whatsapp->send($emp['phone'], $message);
        if ($result['success'] ?? false) {
            $sent++;
        } else {
            $errors[] = "Failed for {$emp['full_name']}: " . ($result['error'] ?? 'unknown');
        }
    }

    echo json_encode([
        'success' => count($errors) === 0,
        'employees_checked' => count($employees),
        'reminders_sent' => $sent,
        'errors' => $errors
    ]);
}
