<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/RadiusBilling.php';
require_once __DIR__ . '/../src/MikroTikAPI.php';
require_once __DIR__ . '/../src/SMSGateway.php';
require_once __DIR__ . '/../src/Settings.php';
require_once __DIR__ . '/../src/WhatsApp.php';

date_default_timezone_set('Africa/Nairobi');
header('Content-Type: application/json');

$isCli = php_sapi_name() === 'cli';
$action = $isCli ? ($argv[1] ?? '') : ($_GET['action'] ?? '');
$secret = $isCli ? 'cli-bypass' : ($_GET['secret'] ?? '');

$db = Database::getConnection();
$settings = new \App\Settings();
$cronSecret = $settings->get('cron_secret', 'isp-crm-cron-2024');

if (!$isCli && $secret !== $cronSecret) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$radiusBilling = new \App\RadiusBilling($db);

switch ($action) {
    case 'process_expired':
        $result = processExpiredSubscriptions($radiusBilling, $db, $settings);
        echo json_encode($result);
        break;
        
    case 'send_expiry_alerts':
        $result = sendExpiryAlerts($radiusBilling, $db, $settings);
        echo json_encode($result);
        break;
        
    case 'send_quota_alerts':
        $result = sendQuotaAlerts($radiusBilling, $db, $settings);
        echo json_encode($result);
        break;
        
    case 'auto_renew':
        $result = $radiusBilling->processAutoRenewals();
        echo json_encode($result);
        break;
        
    case 'scheduled_disconnect':
        $result = processScheduledDisconnects($radiusBilling, $db);
        echo json_encode($result);
        break;
        
    case 'sync_sessions':
        $result = syncRadiusSessions($radiusBilling, $db);
        echo json_encode($result);
        break;
        
    case 'apply_speed_overrides':
        $result = applySpeedOverrides($radiusBilling, $db);
        echo json_encode($result);
        break;
        
    case 'sync_blocked_list':
        $nasId = $isCli ? ($argv[2] ?? null) : ($_GET['nas_id'] ?? null);
        $result = $radiusBilling->syncMikroTikBlockedList($nasId ? (int)$nasId : null);
        echo json_encode($result);
        break;
        
    case 'poll_static_bandwidth':
        $result = $radiusBilling->pollStaticBandwidth();
        echo json_encode($result);
        break;
        
    case 'check_inventory_alerts':
        $result = checkInventoryAlerts($db, $settings);
        echo json_encode($result);
        break;

    case 'check_onu_events':
        $result = checkOnuNetworkEvents($db, $settings);
        echo json_encode($result);
        break;

    case 'all':
        $results = [];
        $results['expired'] = processExpiredSubscriptions($radiusBilling, $db, $settings);
        $results['expiry_alerts'] = sendExpiryAlerts($radiusBilling, $db, $settings);
        $results['quota_alerts'] = sendQuotaAlerts($radiusBilling, $db, $settings);
        $results['auto_renew'] = $radiusBilling->processAutoRenewals();
        $results['disconnects'] = processScheduledDisconnects($radiusBilling, $db);
        $results['session_sync'] = syncRadiusSessions($radiusBilling, $db);
        $results['speed_overrides'] = applySpeedOverrides($radiusBilling, $db);
        $results['blocked_list_sync'] = $radiusBilling->syncMikroTikBlockedList();
        $results['static_bandwidth'] = $radiusBilling->pollStaticBandwidth();
        $results['inventory_alerts'] = checkInventoryAlerts($db, $settings);
        $results['onu_events'] = checkOnuNetworkEvents($db, $settings);
        echo json_encode(['success' => true, 'results' => $results]);
        break;
        
    default:
        echo json_encode([
            'error' => 'Unknown action',
            'available' => ['process_expired', 'send_expiry_alerts', 'send_quota_alerts', 'auto_renew', 'scheduled_disconnect', 'sync_sessions', 'apply_speed_overrides', 'sync_blocked_list', 'check_inventory_alerts', 'check_onu_events', 'all']
        ]);
}

function processExpiredSubscriptions($radiusBilling, $db, $settings): array {
    $result = $radiusBilling->processExpiredSubscriptions();
    
    if ($result['processed'] > 0 && $settings->get('isp_notify_on_expiry', '1') === '1') {
        $stmt = $db->query("
            SELECT s.username, c.phone, c.name FROM radius_subscriptions s
            LEFT JOIN customers c ON s.customer_id = c.id
            WHERE s.status = 'expired' AND s.updated_at > NOW() - INTERVAL '5 minutes'
        ");
        
        while ($sub = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!empty($sub['phone'])) {
                try {
                    $sms = new \App\SMSGateway();
                    $sms->send($sub['phone'], "Dear {$sub['name']}, your internet subscription has expired. Please renew to restore service.");
                } catch (Exception $e) {}
            }
        }
    }
    
    return $result;
}

function sendExpiryAlerts($radiusBilling, $db, $settings): array {
    $sent = 0;
    $errors = [];
    
    $alertDays = [3, 1, 0];
    
    foreach ($alertDays as $days) {
        $stmt = $db->prepare("
            SELECT s.*, c.name as customer_name, c.phone, p.name as package_name, p.price
            FROM radius_subscriptions s
            LEFT JOIN customers c ON s.customer_id = c.id
            LEFT JOIN radius_packages p ON s.package_id = p.id
            WHERE s.status = 'active' 
            AND s.expiry_date = CURRENT_DATE + INTERVAL '1 day' * ?
            AND NOT EXISTS (
                SELECT 1 FROM radius_alert_log WHERE subscription_id = s.id 
                AND alert_type = 'expiry' AND days_before = ? AND DATE(sent_at) = CURRENT_DATE
            )
        ");
        $stmt->execute([$days, $days]);
        
        while ($sub = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (empty($sub['phone'])) continue;
            
            $dayText = $days == 0 ? 'TODAY' : "in {$days} day(s)";
            $message = "Dear {$sub['customer_name']}, your internet ({$sub['package_name']}) expires {$dayText}. ";
            $message .= "Renew now for KES " . number_format($sub['price']) . " to avoid disconnection.";
            
            try {
                $sms = new \App\SMSGateway();
                $sms->send($sub['phone'], $message);
                
                $logStmt = $db->prepare("
                    INSERT INTO radius_alert_log (subscription_id, alert_type, days_before, message, sent_at)
                    VALUES (?, 'expiry', ?, ?, NOW())
                ");
                $logStmt->execute([$sub['id'], $days, $message]);
                $sent++;
            } catch (Exception $e) {
                $errors[] = "Failed to send to {$sub['phone']}: " . $e->getMessage();
            }
        }
    }
    
    return ['success' => true, 'sent' => $sent, 'errors' => $errors];
}

function sendQuotaAlerts($radiusBilling, $db, $settings): array {
    $sent = 0;
    $thresholds = [100, 80];
    
    foreach ($thresholds as $threshold) {
        $stmt = $db->prepare("
            SELECT s.*, c.name as customer_name, c.phone, p.name as package_name, p.data_quota_mb,
                   ROUND((s.data_used_mb::numeric / NULLIF(p.data_quota_mb, 0)) * 100, 0) as usage_percent
            FROM radius_subscriptions s
            LEFT JOIN customers c ON s.customer_id = c.id
            LEFT JOIN radius_packages p ON s.package_id = p.id
            WHERE s.status = 'active' AND p.data_quota_mb > 0
            AND (s.data_used_mb::numeric / p.data_quota_mb) * 100 >= ?
            AND (s.data_used_mb::numeric / p.data_quota_mb) * 100 < ? + 20
            AND NOT EXISTS (
                SELECT 1 FROM radius_alert_log WHERE subscription_id = s.id 
                AND alert_type = 'quota' AND threshold_percent = ?
                AND sent_at > s.start_date
            )
        ");
        $stmt->execute([$threshold, $threshold, $threshold]);
        
        while ($sub = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (empty($sub['phone'])) continue;
            
            $remaining = max(0, $sub['data_quota_mb'] - $sub['data_used_mb']);
            $remainingGB = number_format($remaining / 1024, 2);
            
            if ($threshold >= 100) {
                $message = "Dear {$sub['customer_name']}, you have exhausted your data quota. ";
                $message .= "Top up to continue browsing at full speed.";
            } else {
                $message = "Dear {$sub['customer_name']}, you have used {$threshold}% of your data. ";
                $message .= "Remaining: {$remainingGB} GB.";
            }
            
            try {
                $sms = new \App\SMSGateway();
                $sms->send($sub['phone'], $message);
                
                $logStmt = $db->prepare("
                    INSERT INTO radius_alert_log (subscription_id, alert_type, threshold_percent, message, sent_at)
                    VALUES (?, 'quota', ?, ?, NOW())
                ");
                $logStmt->execute([$sub['id'], $threshold, $message]);
                $sent++;
            } catch (Exception $e) {}
        }
    }
    
    return ['success' => true, 'sent' => $sent];
}

function processScheduledDisconnects($radiusBilling, $db): array {
    $disconnected = 0;
    
    $stmt = $db->query("
        SELECT s.id FROM radius_subscriptions s
        WHERE s.status = 'active'
        AND s.expiry_date < CURRENT_DATE - INTERVAL '1 day' * s.grace_period_days
    ");
    
    while ($sub = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $result = $radiusBilling->disconnectUser($sub['id']);
        if ($result['success']) {
            $disconnected += $result['disconnected'];
        }
    }
    
    return ['success' => true, 'disconnected' => $disconnected];
}

function syncRadiusSessions($radiusBilling, $db): array {
    $routerSync = $radiusBilling->syncSessionsWithRouter();
    
    $stale = $radiusBilling->cleanStaleSessions(24);
    
    return [
        'success' => true, 
        'router_sync' => $routerSync,
        'stale_sessions_closed' => $stale
    ];
}

function applySpeedOverrides($radiusBilling, $db): array {
    $updated = 0;
    $errors = [];
    
    $currentTime = date('H:i:s');
    $currentDay = strtolower(date('l'));
    
    $stmt = $db->prepare("
        SELECT DISTINCT s.id as subscription_id, s.package_id, s.username,
               so.name as override_name, so.download_speed, so.upload_speed
        FROM radius_subscriptions s
        INNER JOIN radius_speed_overrides so ON so.package_id = s.package_id
        INNER JOIN radius_sessions rs ON rs.subscription_id = s.id AND rs.session_end IS NULL
        WHERE s.status = 'active'
        AND so.is_active = TRUE
        AND so.start_time <= ?
        AND so.end_time >= ?
        AND (
            so.days_of_week IS NULL 
            OR so.days_of_week = '' 
            OR so.days_of_week LIKE ?
        )
    ");
    $stmt->execute([$currentTime, $currentTime, '%' . $currentDay . '%']);
    
    while ($sub = $stmt->fetch(PDO::FETCH_ASSOC)) {
        try {
            $result = $radiusBilling->sendSpeedUpdateCoA($sub['subscription_id']);
            if ($result['success']) {
                $updated++;
            } else {
                $errors[] = "{$sub['username']}: {$result['error']}";
            }
        } catch (Exception $e) {
            $errors[] = "{$sub['username']}: " . $e->getMessage();
        }
    }
    
    return ['success' => true, 'updated' => $updated, 'errors' => $errors];
}

function checkOnuNetworkEvents($db, $settings): array {
    $sent = 0;
    $errors = [];
    
    if ($settings->get('wa_network_notifications_enabled', '1') !== '1') {
        return ['success' => true, 'sent' => 0, 'message' => 'Network notifications disabled'];
    }
    
    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS network_notifications (
                id SERIAL PRIMARY KEY,
                onu_id INTEGER NOT NULL,
                customer_id INTEGER,
                event_type VARCHAR(50) NOT NULL,
                notification_sent BOOLEAN DEFAULT FALSE,
                sent_at TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_network_notifications_onu_event ON network_notifications (onu_id, event_type, created_at)");
    } catch (Exception $e) {}
    
    $losStmt = $db->query("
        SELECT o.id AS onu_id, o.name AS onu_name, o.sn, o.status, o.last_down_time,
               o.customer_id AS onu_customer_id,
               c.id AS customer_id, c.name AS customer_name, c.phone
        FROM huawei_onus o
        LEFT JOIN radius_subscriptions rs ON rs.huawei_onu_id = o.id
        LEFT JOIN customers c ON (c.id = rs.customer_id OR c.id = o.customer_id)
        WHERE o.status IN ('LOS', 'los', 'offline', 'DyingGasp')
        AND c.phone IS NOT NULL AND c.phone != ''
        AND NOT EXISTS (
            SELECT 1 FROM network_notifications nn 
            WHERE nn.onu_id = o.id 
            AND nn.event_type = 'los' 
            AND nn.notification_sent = TRUE
            AND nn.created_at > NOW() - INTERVAL '6 hours'
        )
    ");
    
    $whatsapp = new \App\WhatsApp();
    
    while ($row = $losStmt->fetch(PDO::FETCH_ASSOC)) {
        try {
            $eventTime = !empty($row['last_down_time']) 
                ? date('M j, Y g:i A', strtotime($row['last_down_time'])) 
                : date('M j, Y g:i A');
            $onuName = $row['onu_name'] ?: $row['sn'];
            
            $result = $whatsapp->notifyNetworkEvent(
                $row['phone'],
                $row['customer_name'],
                $onuName,
                'los',
                $eventTime
            );
            
            $logStmt = $db->prepare("
                INSERT INTO network_notifications (onu_id, customer_id, event_type, notification_sent, sent_at)
                VALUES (?, ?, 'los', TRUE, NOW())
            ");
            $logStmt->execute([$row['onu_id'], $row['customer_id']]);
            
            if ($result['success'] ?? false) {
                $sent++;
            }
        } catch (Exception $e) {
            $errors[] = "LOS notification for ONU {$row['onu_id']}: " . $e->getMessage();
        }
    }
    
    $restoredStmt = $db->query("
        SELECT o.id AS onu_id, o.name AS onu_name, o.sn, o.status, o.last_up_time,
               c.id AS customer_id, c.name AS customer_name, c.phone
        FROM huawei_onus o
        INNER JOIN network_notifications nn ON nn.onu_id = o.id 
            AND nn.event_type = 'los' 
            AND nn.notification_sent = TRUE
            AND nn.created_at > NOW() - INTERVAL '48 hours'
        LEFT JOIN radius_subscriptions rs ON rs.huawei_onu_id = o.id
        LEFT JOIN customers c ON (c.id = rs.customer_id OR c.id = o.customer_id)
        WHERE o.status IN ('online', 'Online', 'active')
        AND c.phone IS NOT NULL AND c.phone != ''
        AND NOT EXISTS (
            SELECT 1 FROM network_notifications nn2 
            WHERE nn2.onu_id = o.id 
            AND nn2.event_type = 'restored' 
            AND nn2.notification_sent = TRUE
            AND nn2.created_at > nn.created_at
        )
    ");
    
    while ($row = $restoredStmt->fetch(PDO::FETCH_ASSOC)) {
        try {
            $eventTime = !empty($row['last_up_time']) 
                ? date('M j, Y g:i A', strtotime($row['last_up_time'])) 
                : date('M j, Y g:i A');
            $onuName = $row['onu_name'] ?: $row['sn'];
            
            $result = $whatsapp->notifyNetworkEvent(
                $row['phone'],
                $row['customer_name'],
                $onuName,
                'restored',
                $eventTime
            );
            
            $logStmt = $db->prepare("
                INSERT INTO network_notifications (onu_id, customer_id, event_type, notification_sent, sent_at)
                VALUES (?, ?, 'restored', TRUE, NOW())
            ");
            $logStmt->execute([$row['onu_id'], $row['customer_id']]);
            
            if ($result['success'] ?? false) {
                $sent++;
            }
        } catch (Exception $e) {
            $errors[] = "Restored notification for ONU {$row['onu_id']}: " . $e->getMessage();
        }
    }
    
    return ['success' => true, 'sent' => $sent, 'errors' => $errors];
}

function checkInventoryAlerts($db, $settings): array {
    $sent = 0;
    $errors = [];

    try {
        $stmt = $db->query("
            SELECT e.id, e.name, e.quantity, e.reorder_point, c.name as category_name
            FROM equipment e
            LEFT JOIN equipment_categories c ON e.category_id = c.id
            WHERE e.reorder_point > 0 AND e.quantity <= e.reorder_point
            ORDER BY e.quantity ASC, e.name
        ");
        $lowStockItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Failed to query inventory: ' . $e->getMessage()];
    }

    if (empty($lowStockItems)) {
        return ['success' => true, 'sent' => 0, 'low_stock_count' => 0, 'message' => 'No low stock items'];
    }

    $alreadyNotified = [];
    try {
        $stmt = $db->query("
            SELECT DISTINCT equipment_id FROM inventory_depletion_alerts
            WHERE DATE(alerted_at) = CURRENT_DATE
        ");
        $alreadyNotified = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'equipment_id');
    } catch (Exception $e) {
        try {
            $db->exec("
                CREATE TABLE IF NOT EXISTS inventory_depletion_alerts (
                    id SERIAL PRIMARY KEY,
                    equipment_id INTEGER NOT NULL,
                    item_name VARCHAR(200),
                    quantity INTEGER,
                    reorder_point INTEGER,
                    alerted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
        } catch (Exception $e2) {
            error_log("Failed to create inventory_depletion_alerts table: " . $e2->getMessage());
        }
    }

    $newAlerts = [];
    foreach ($lowStockItems as $item) {
        if (!in_array($item['id'], $alreadyNotified)) {
            $newAlerts[] = $item;
        }
    }

    if (empty($newAlerts)) {
        return ['success' => true, 'sent' => 0, 'low_stock_count' => count($lowStockItems), 'message' => 'All alerts already sent today'];
    }

    $adminPhone = $settings->get('admin_phone', '');
    if (empty($adminPhone)) {
        $adminPhone = $settings->get('company_phone', '');
    }

    if (!empty($adminPhone)) {
        $lines = ["⚠️ *INVENTORY LOW STOCK ALERT*\n"];
        foreach ($newAlerts as $item) {
            $category = $item['category_name'] ? " ({$item['category_name']})" : '';
            $status = $item['quantity'] <= 0 ? '🔴 OUT OF STOCK' : '🟡 LOW STOCK';
            $lines[] = "{$status}: {$item['name']}{$category} — Qty: {$item['quantity']}, Reorder at: {$item['reorder_point']}";
        }
        $lines[] = "\nTotal low stock items: " . count($lowStockItems);
        $message = implode("\n", $lines);

        try {
            require_once __DIR__ . '/../src/WhatsApp.php';
            $wa = new \App\WhatsApp();
            $result = $wa->send($adminPhone, $message);
            if ($result['success'] ?? false) {
                $sent++;
            } else {
                $errors[] = 'WhatsApp send failed: ' . ($result['error'] ?? 'unknown');
            }
        } catch (Exception $e) {
            $errors[] = 'WhatsApp error: ' . $e->getMessage();
        }
    }

    foreach ($newAlerts as $item) {
        try {
            $stmt = $db->prepare("
                INSERT INTO inventory_depletion_alerts (equipment_id, item_name, quantity, reorder_point)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$item['id'], $item['name'], $item['quantity'], $item['reorder_point']]);
        } catch (Exception $e) {
            error_log("Failed to log inventory alert for {$item['name']}: " . $e->getMessage());
        }
    }

    return [
        'success' => true,
        'sent' => $sent,
        'low_stock_count' => count($lowStockItems),
        'new_alerts' => count($newAlerts),
        'errors' => $errors
    ];
}
