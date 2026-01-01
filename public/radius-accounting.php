<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$db = getDbConnection();

$input = file_get_contents('php://input');
$data = json_decode($input, true) ?? $_POST;

$acctStatusType = $data['Acct-Status-Type'] ?? $data['acct_status_type'] ?? '';
$username = $data['User-Name'] ?? $data['username'] ?? '';
$sessionId = $data['Acct-Session-Id'] ?? $data['acct_session_id'] ?? '';
$nasIP = $data['NAS-IP-Address'] ?? $data['nas_ip'] ?? '';
$nasPort = $data['NAS-Port-Id'] ?? $data['nas_port'] ?? '';
$framedIP = $data['Framed-IP-Address'] ?? $data['framed_ip'] ?? '';
$macAddress = $data['Calling-Station-Id'] ?? $data['mac'] ?? '';
$inputOctets = (int)($data['Acct-Input-Octets'] ?? $data['input_octets'] ?? 0);
$outputOctets = (int)($data['Acct-Output-Octets'] ?? $data['output_octets'] ?? 0);
$sessionTime = (int)($data['Acct-Session-Time'] ?? $data['session_time'] ?? 0);
$terminateCause = $data['Acct-Terminate-Cause'] ?? $data['terminate_cause'] ?? '';
$inputGigawords = (int)($data['Acct-Input-Gigawords'] ?? 0);
$outputGigawords = (int)($data['Acct-Output-Gigawords'] ?? 0);

$inputOctets += $inputGigawords * 4294967296;
$outputOctets += $outputGigawords * 4294967296;

if (empty($username) || empty($sessionId)) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

$stmt = $db->prepare("SELECT id FROM radius_subscriptions WHERE username = ?");
$stmt->execute([$username]);
$subscription = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$subscription) {
    echo json_encode(['success' => false, 'error' => 'Subscription not found']);
    exit;
}

$subscriptionId = $subscription['id'];

$stmt = $db->prepare("SELECT id FROM radius_nas WHERE ip_address = ?");
$stmt->execute([$nasIP]);
$nas = $stmt->fetch(PDO::FETCH_ASSOC);
$nasId = $nas['id'] ?? null;

switch (strtolower($acctStatusType)) {
    case 'start':
        $stmt = $db->prepare("
            SELECT id FROM radius_sessions WHERE acct_session_id = ? AND subscription_id = ?
        ");
        $stmt->execute([$sessionId, $subscriptionId]);
        $existing = $stmt->fetch();
        
        if (!$existing) {
            $stmt = $db->prepare("
                INSERT INTO radius_sessions 
                (subscription_id, acct_session_id, nas_id, nas_ip_address, nas_port_id, 
                 framed_ip_address, mac_address, session_start, started_at, username, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, 'active')
            ");
            $stmt->execute([
                $subscriptionId, $sessionId, $nasId, $nasIP, $nasPort,
                $framedIP, $macAddress, $username
            ]);
            
            // Auto-capture MAC if subscription doesn't have one yet
            if (!empty($macAddress)) {
                $stmt = $db->prepare("SELECT mac_address FROM radius_subscriptions WHERE id = ?");
                $stmt->execute([$subscriptionId]);
                $currentMac = $stmt->fetchColumn();
                if (empty($currentMac)) {
                    $db->prepare("UPDATE radius_subscriptions SET mac_address = ?, updated_at = NOW() WHERE id = ?")
                       ->execute([$macAddress, $subscriptionId]);
                }
            }
        }
        
        $db->prepare("UPDATE radius_subscriptions SET last_session_start = NOW() WHERE id = ?")
           ->execute([$subscriptionId]);
        
        echo json_encode(['success' => true, 'action' => 'session_started']);
        break;
        
    case 'stop':
        $stmt = $db->prepare("
            UPDATE radius_sessions SET
                session_end = NOW(),
                stopped_at = NOW(),
                session_duration = ?,
                input_octets = ?,
                output_octets = ?,
                terminate_cause = ?,
                status = 'closed'
            WHERE acct_session_id = ? AND subscription_id = ?
        ");
        $stmt->execute([
            $sessionTime, $inputOctets, $outputOctets, $terminateCause,
            $sessionId, $subscriptionId
        ]);
        
        $totalMB = ($inputOctets + $outputOctets) / 1048576;
        $db->prepare("
            UPDATE radius_subscriptions SET 
                data_used_mb = data_used_mb + ?,
                last_session_end = NOW()
            WHERE id = ?
        ")->execute([$totalMB, $subscriptionId]);
        
        $stmt = $db->prepare("
            INSERT INTO radius_usage_logs (subscription_id, log_date, download_mb, upload_mb, session_count, session_time_seconds)
            VALUES (?, CURRENT_DATE, ?, ?, 1, ?)
            ON CONFLICT (subscription_id, log_date) DO UPDATE SET
                download_mb = radius_usage_logs.download_mb + EXCLUDED.download_mb,
                upload_mb = radius_usage_logs.upload_mb + EXCLUDED.upload_mb,
                session_count = radius_usage_logs.session_count + 1,
                session_time_seconds = radius_usage_logs.session_time_seconds + EXCLUDED.session_time_seconds
        ");
        $stmt->execute([
            $subscriptionId,
            $inputOctets / 1048576,
            $outputOctets / 1048576,
            $sessionTime
        ]);
        
        echo json_encode(['success' => true, 'action' => 'session_stopped', 'data_mb' => round($totalMB, 2)]);
        break;
        
    case 'interim-update':
    case 'alive':
        $stmt = $db->prepare("
            UPDATE radius_sessions SET
                session_duration = ?,
                input_octets = ?,
                output_octets = ?
            WHERE acct_session_id = ? AND subscription_id = ?
        ");
        $stmt->execute([
            $sessionTime, $inputOctets, $outputOctets,
            $sessionId, $subscriptionId
        ]);
        
        echo json_encode(['success' => true, 'action' => 'session_updated']);
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Unknown Acct-Status-Type: ' . $acctStatusType]);
}
