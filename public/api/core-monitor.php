<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

$db = Database::getConnection();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'ping_all':
        $stmt = $db->query("
            SELECT id, name, management_ip, ping_status, equipment_type, site_id
            FROM isp_core_equipment 
            WHERE status = 'active' AND monitor_enabled = TRUE AND management_ip IS NOT NULL AND management_ip != ''
            ORDER BY name
        ");
        $equipment = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $results = [];
        $statusChanges = [];
        
        foreach ($equipment as $eq) {
            $ip = trim($eq['management_ip']);
            if (empty($ip)) continue;
            
            $reachable = pingHost($ip);
            $prevStatus = $eq['ping_status'] ?? 'unknown';
            $newStatus = $reachable ? 'online' : 'offline';
            
            if ($reachable) {
                $db->prepare("
                    UPDATE isp_core_equipment SET 
                        ping_status = 'online',
                        last_ping_at = CURRENT_TIMESTAMP,
                        last_seen_online = CURRENT_TIMESTAMP,
                        downtime_started = NULL,
                        downtime_notified = FALSE
                    WHERE id = ?
                ")->execute([$eq['id']]);
            } else {
                if ($prevStatus !== 'offline') {
                    $db->prepare("
                        UPDATE isp_core_equipment SET 
                            ping_status = 'offline',
                            last_ping_at = CURRENT_TIMESTAMP,
                            downtime_started = CURRENT_TIMESTAMP,
                            downtime_notified = FALSE
                        WHERE id = ?
                    ")->execute([$eq['id']]);
                } else {
                    $db->prepare("
                        UPDATE isp_core_equipment SET 
                            last_ping_at = CURRENT_TIMESTAMP
                        WHERE id = ?
                    ")->execute([$eq['id']]);
                }
            }
            
            if ($prevStatus !== $newStatus && $prevStatus !== 'unknown') {
                $statusChanges[] = [
                    'id' => $eq['id'],
                    'name' => $eq['name'],
                    'ip' => $ip,
                    'type' => $eq['equipment_type'],
                    'prev' => $prevStatus,
                    'new' => $newStatus
                ];
            }
            
            $results[] = [
                'id' => $eq['id'],
                'name' => $eq['name'],
                'ip' => $ip,
                'status' => $newStatus,
                'changed' => ($prevStatus !== $newStatus && $prevStatus !== 'unknown')
            ];
        }
        
        if (!empty($statusChanges)) {
            sendEquipmentNotifications($db, $statusChanges);
        }
        
        echo json_encode([
            'success' => true,
            'results' => $results,
            'total' => count($results),
            'online' => count(array_filter($results, fn($r) => $r['status'] === 'online')),
            'offline' => count(array_filter($results, fn($r) => $r['status'] === 'offline')),
            'changes' => count($statusChanges)
        ]);
        break;
        
    case 'get_status':
        $stmt = $db->query("
            SELECT id, name, management_ip, ping_status, last_ping_at, last_seen_online, 
                   downtime_started, equipment_type, monitor_enabled
            FROM isp_core_equipment 
            WHERE status = 'active' AND management_ip IS NOT NULL AND management_ip != ''
            ORDER BY name
        ");
        echo json_encode(['success' => true, 'equipment' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        break;
        
    case 'toggle_monitor':
        $eqId = (int)($_POST['id'] ?? 0);
        $enabled = ($_POST['enabled'] ?? '1') === '1';
        $db->prepare("UPDATE isp_core_equipment SET monitor_enabled = ? WHERE id = ?")->execute([$enabled, $eqId]);
        echo json_encode(['success' => true]);
        break;
        
    case 'ping_one':
        $eqId = (int)($_GET['id'] ?? 0);
        $stmt = $db->prepare("SELECT id, name, management_ip, ping_status FROM isp_core_equipment WHERE id = ?");
        $stmt->execute([$eqId]);
        $eq = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$eq || empty($eq['management_ip'])) {
            echo json_encode(['success' => false, 'error' => 'Equipment not found or no IP']);
            break;
        }
        $reachable = pingHost(trim($eq['management_ip']));
        $newStatus = $reachable ? 'online' : 'offline';
        
        if ($reachable) {
            $db->prepare("UPDATE isp_core_equipment SET ping_status = 'online', last_ping_at = CURRENT_TIMESTAMP, last_seen_online = CURRENT_TIMESTAMP, downtime_started = NULL, downtime_notified = FALSE WHERE id = ?")->execute([$eqId]);
        } else {
            $db->prepare("UPDATE isp_core_equipment SET ping_status = 'offline', last_ping_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$eqId]);
        }
        
        echo json_encode(['success' => true, 'id' => $eqId, 'status' => $newStatus, 'name' => $eq['name']]);
        break;
        
    case 'uptime_report':
        $days = (int)($_GET['days'] ?? 7);
        $stmt = $db->prepare("
            SELECT e.id, e.name, e.management_ip, e.equipment_type, e.ping_status,
                   e.last_seen_online, e.downtime_started, e.last_ping_at,
                   s.name as site_name,
                   COALESCE(el.total_events, 0) as total_events,
                   COALESCE(el.down_events, 0) as down_events,
                   COALESCE(el.up_events, 0) as up_events
            FROM isp_core_equipment e
            LEFT JOIN isp_network_sites s ON e.site_id = s.id
            LEFT JOIN (
                SELECT equipment_id,
                    COUNT(*) as total_events,
                    COUNT(*) FILTER (WHERE new_status = 'offline') as down_events,
                    COUNT(*) FILTER (WHERE new_status = 'online') as up_events
                FROM isp_equipment_uptime_log
                WHERE created_at >= CURRENT_TIMESTAMP - MAKE_INTERVAL(days => ?)
                GROUP BY equipment_id
            ) el ON el.equipment_id = e.id
            WHERE e.status = 'active' AND e.management_ip IS NOT NULL AND e.management_ip != ''
            ORDER BY e.name
        ");
        $stmt->execute([$days]);
        echo json_encode(['success' => true, 'report' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'days' => $days]);
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
}

function pingHost(string $ip): bool {
    $ip = escapeshellarg($ip);
    $output = [];
    $retval = 0;
    exec("ping -c 2 -W 2 $ip 2>/dev/null", $output, $retval);
    return $retval === 0;
}

function sendEquipmentNotifications(PDO $db, array $changes): void {
    try {
        $dyingGaspGroup = null;
        $provGroup = null;
        
        $stmt = $db->query("SELECT setting_key, setting_value FROM company_settings WHERE setting_key IN ('wa_dying_gasp_group', 'wa_provisioning_group')");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($row['setting_key'] === 'wa_dying_gasp_group') $dyingGaspGroup = $row['setting_value'];
            if ($row['setting_key'] === 'wa_provisioning_group') $provGroup = $row['setting_value'];
        }
        
        $targetGroup = $dyingGaspGroup ?: $provGroup;
        if (!$targetGroup) return;
        
        foreach ($changes as $change) {
            $insertStmt = $db->prepare("
                INSERT INTO isp_equipment_uptime_log (equipment_id, prev_status, new_status, created_at)
                VALUES (?, ?, ?, CURRENT_TIMESTAMP)
            ");
            $insertStmt->execute([$change['id'], $change['prev'], $change['new']]);
        }
        
        $downChanges = array_filter($changes, fn($c) => $c['new'] === 'offline');
        $upChanges = array_filter($changes, fn($c) => $c['new'] === 'online');
        
        $messages = [];
        
        if (!empty($downChanges)) {
            $lines = [];
            foreach ($downChanges as $c) {
                $siteStmt = $db->prepare("SELECT s.name FROM isp_core_equipment e LEFT JOIN isp_network_sites s ON e.site_id = s.id WHERE e.id = ?");
                $siteStmt->execute([$c['id']]);
                $siteName = $siteStmt->fetchColumn() ?: 'Unknown';
                $lines[] = "  - {$c['name']} ({$c['type']}) — {$c['ip']}\n    Site: {$siteName}";
            }
            $messages[] = "🔴 *EQUIPMENT DOWN*\n\n" . implode("\n\n", $lines) . "\n\n⏰ " . date('Y-m-d H:i:s');
        }
        
        if (!empty($upChanges)) {
            $lines = [];
            foreach ($upChanges as $c) {
                $downtime = '';
                $dtStmt = $db->prepare("SELECT downtime_started FROM isp_core_equipment WHERE id = ?");
                $dtStmt->execute([$c['id']]);
                $downStart = $dtStmt->fetchColumn();
                if ($downStart) {
                    $start = new DateTime($downStart);
                    $now = new DateTime();
                    $diff = $now->diff($start);
                    if ($diff->days > 0) $downtime = " (was down {$diff->days}d {$diff->h}h)";
                    elseif ($diff->h > 0) $downtime = " (was down {$diff->h}h {$diff->i}m)";
                    else $downtime = " (was down {$diff->i}m)";
                }
                $lines[] = "  - {$c['name']} ({$c['type']}) — {$c['ip']}{$downtime}";
            }
            $messages[] = "🟢 *EQUIPMENT RECOVERED*\n\n" . implode("\n\n", $lines) . "\n\n⏰ " . date('Y-m-d H:i:s');
        }
        
        if (!empty($messages)) {
            require_once __DIR__ . '/../../src/WhatsApp.php';
            $whatsapp = new \App\WhatsApp();
            foreach ($messages as $msg) {
                $whatsapp->sendToGroup($targetGroup, $msg);
            }
        }
    } catch (\Exception $e) {
        error_log("[CoreMonitor] Notification error: " . $e->getMessage());
    }
}
