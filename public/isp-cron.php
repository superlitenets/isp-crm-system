<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/RadiusBilling.php';
require_once __DIR__ . '/../src/SMSGateway.php';
require_once __DIR__ . '/../src/Settings.php';

date_default_timezone_set('Africa/Nairobi');
header('Content-Type: application/json');

$isCli = php_sapi_name() === 'cli';
$action = $isCli ? ($argv[1] ?? '') : ($_GET['action'] ?? '');
$secret = $isCli ? 'cli-bypass' : ($_GET['secret'] ?? '');

$db = getDbConnection();
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
        
    case 'all':
        $results = [];
        $results['expired'] = processExpiredSubscriptions($radiusBilling, $db, $settings);
        $results['expiry_alerts'] = sendExpiryAlerts($radiusBilling, $db, $settings);
        $results['quota_alerts'] = sendQuotaAlerts($radiusBilling, $db, $settings);
        $results['auto_renew'] = $radiusBilling->processAutoRenewals();
        $results['disconnects'] = processScheduledDisconnects($radiusBilling, $db);
        echo json_encode(['success' => true, 'results' => $results]);
        break;
        
    default:
        echo json_encode([
            'error' => 'Unknown action',
            'available' => ['process_expired', 'send_expiry_alerts', 'send_quota_alerts', 'auto_renew', 'scheduled_disconnect', 'sync_sessions', 'all']
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
    $stmt = $db->query("
        UPDATE radius_sessions SET 
            status = 'closed',
            session_end = NOW()
        WHERE status = 'active' 
        AND session_start < NOW() - INTERVAL '24 hours'
        AND session_end IS NULL
    ");
    
    return ['success' => true, 'stale_sessions_closed' => $stmt->rowCount()];
}
