<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/WhatsApp.php';
require_once __DIR__ . '/../../src/Settings.php';

use App\Settings;
use App\WhatsApp;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

$type = $input['type'] ?? '';
$groupId = $input['group_id'] ?? '';
$discoveries = $input['discoveries'] ?? [];
$faults = $input['faults'] ?? [];

if (empty($type) || empty($groupId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields (type or group_id)']);
    exit;
}

// Validate payload based on notification type
if ($type === 'new_onu_discovery' && empty($discoveries)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing discoveries for new_onu_discovery']);
    exit;
}

if ($type === 'onu_fault' && empty($faults)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing faults for onu_fault notification']);
    exit;
}

try {
    $settings = new Settings();
    $whatsapp = new WhatsApp();
    
    if ($type === 'new_onu_discovery') {
        $template = $settings->get('wa_template_oms_new_onu', 
            "🔔 *NEW ONU DISCOVERED*\n\n🏢 *OLT:* {olt_name}\n📍 *Branch:* {branch_name}\n📊 *Count:* {onu_count} new ONU(s)\n⏰ *Time:* {discovery_time}\n\n📋 *Locations:*\n{onu_locations}\n\n🔢 *Serial Numbers:*\n{onu_serials}\n\n💡 Please authorize these ONUs in the OMS panel."
        );
        
        $oltName = $discoveries[0]['olt_name'] ?? 'Unknown';
        $branchName = $discoveries[0]['branch_name'] ?? 'Unassigned';
        $branchCode = $discoveries[0]['branch_code'] ?? '';
        $oltIp = $discoveries[0]['olt_ip'] ?? '';
        
        $onuLocations = [];
        $onuSerials = [];
        foreach ($discoveries as $d) {
            $eqid = !empty($d['equipment_id']) ? " ({$d['equipment_id']})" : '';
            $onuLocations[] = "• {$d['frame_slot_port']}{$eqid}";
            $onuSerials[] = "• {$d['serial_number']}";
        }
        
        $locationsStr = implode("\n", $onuLocations);
        $serialsStr = implode("\n", $onuSerials);

        $firstOnu = $discoveries[0];
        $onuPort = $firstOnu['frame_slot_port'] ?? '';
        $zoneName = $firstOnu['zone_name'] ?? 'Unassigned';
        $inventoryStatus = $firstOnu['inventory_status'] ?? 'Unknown';
        $onuType = $firstOnu['onu_type'] ?? $firstOnu['equipment_id'] ?? 'Unknown';
        
        $message = str_replace([
            '{olt_name}',
            '{olt_ip}',
            '{branch_name}',
            '{branch_code}',
            '{onu_count}',
            '{onu_locations}',
            '{onu_list}',
            '{onu_serials}',
            '{onu_port}',
            '{zone_name}',
            '{inventory_status}',
            '{onu_type}',
            '{discovery_time}'
        ], [
            $oltName,
            $oltIp,
            $branchName,
            $branchCode,
            count($discoveries),
            $locationsStr,
            $locationsStr,
            $serialsStr,
            $onuPort,
            $zoneName,
            $inventoryStatus,
            $onuType,
            date('Y-m-d H:i:s')
        ], $template);
        
        $result = $whatsapp->sendToGroup($groupId, $message);
        
        if ($result['success']) {
            echo json_encode([
                'success' => true, 
                'message' => 'Notification sent',
                'group_id' => $groupId,
                'onu_count' => count($discoveries),
                'messageId' => $result['messageId'] ?? null
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false, 
                'error' => $result['error'] ?? 'Failed to send WhatsApp message',
                'group_id' => $groupId
            ]);
        }
    } elseif ($type === 'onu_fault') {
        // Handle ONU fault notifications (using $faults extracted above)
        $oltName = $input['olt_name'] ?? 'Unknown OLT';
        $oltIp = $input['olt_ip'] ?? '';
        $branchName = $input['branch_name'] ?? 'Unassigned';
        
        if (empty($faults)) {
            echo json_encode(['success' => true, 'message' => 'No faults to notify']);
            exit;
        }
        
        // Create tickets for each fault with a linked customer
        $ticketsCreated = 0;
        require_once __DIR__ . '/../../src/Ticket.php';
        $ticketClass = new \App\Ticket();
        
        foreach ($faults as $f) {
            // Only create ticket if customer is linked
            if (!empty($f['customer_id'])) {
                $statusLabel = $f['new_status'] === 'los' ? 'LOS (Loss of Signal)' : 
                    ($f['new_status'] === 'dying-gasp' ? 'Dying Gasp (Power Failure)' : 'Offline');
                
                $ticketSubject = "ONU {$statusLabel}: " . ($f['name'] ?: $f['sn']);
                $ticketDescription = "Automatic fault detection:\n\n" .
                    "ONU: " . ($f['name'] ?: $f['sn']) . "\n" .
                    "Serial: {$f['sn']}\n" .
                    "Location: 0/{$f['slot']}/{$f['port']}/{$f['onu_id']}\n" .
                    "OLT: {$oltName} ({$oltIp})\n" .
                    "Branch: {$branchName}\n" .
                    "Previous Status: {$f['prev_status']}\n" .
                    "New Status: {$f['new_status']}\n" .
                    "Detected: " . date('Y-m-d H:i:s');
                
                try {
                    $ticketResult = $ticketClass->create([
                        'customer_id' => $f['customer_id'],
                        'subject' => $ticketSubject,
                        'description' => $ticketDescription,
                        'priority' => ($f['new_status'] === 'los' || $f['new_status'] === 'dying-gasp') ? 'high' : 'medium',
                        'category' => 'Fiber/ONU',
                        'source' => 'system'
                    ]);
                    if ($ticketResult['success'] ?? false) {
                        $ticketsCreated++;
                    }
                } catch (Exception $e) {
                    error_log("Failed to create fault ticket: " . $e->getMessage());
                }
            }
        }
        
        $losTemplate = $settings->get('wa_template_oms_los_alert', 
            "⚠️ *ONU LOS ALERT — {fault_count} ONU(s)*\n\n🏢 *OLT:* {olt_name}\n📍 *Branch:* {branch_name}\n⏰ *Time:* {alert_time}\n\n{onu_table}\n\n🔧 Please check fiber connection and customer site."
        );
        $dyingGaspTemplate = $settings->get('wa_template_oms_dying_gasp',
            "🔴 *DYING GASP — {fault_count} ONU(s) POWER FAILURE*\n\n🏢 *OLT:* {olt_name}\n📍 *Branch:* {branch_name}\n⏰ *Time:* {alert_time}\n\n{onu_table}\n\n💡 Customers may have a power outage. Check power supply."
        );
        $offlineTemplate = $settings->get('wa_template_oms_offline_alert',
            "📴 *ONU OFFLINE — {fault_count} ONU(s)*\n\n🏢 *OLT:* {olt_name}\n📍 *Branch:* {branch_name}\n⏰ *Time:* {alert_time}\n\n{onu_table}"
        );
        
        $grouped = ['los' => [], 'dying-gasp' => [], 'offline' => []];
        foreach ($faults as $f) {
            $status = $f['new_status'] ?? 'offline';
            $key = isset($grouped[$status]) ? $status : 'offline';
            $grouped[$key][] = $f;
        }
        
        $messages = [];
        foreach ($grouped as $statusType => $typeFaults) {
            if (empty($typeFaults)) continue;
            
            if ($statusType === 'los') {
                $template = $losTemplate;
            } elseif ($statusType === 'dying-gasp') {
                $template = $dyingGaspTemplate;
            } else {
                $template = $offlineTemplate;
            }
            
            $tableRows = [];
            $idx = 1;
            foreach ($typeFaults as $f) {
                $onuPort = "0/{$f['slot']}/{$f['port']}:{$f['onu_id']}";
                $onuName = $f['name'] ?: $f['sn'];
                $customerName = $f['customer_name'] ?? '';
                $customerPhone = $f['customer_phone'] ?? '';
                
                $row = "*{$idx}.* {$onuName}\n" .
                       "    📡 Port: {$onuPort} | SN: {$f['sn']}";
                if (!empty($customerName) && $customerName !== 'Unknown') {
                    $row .= "\n    👤 {$customerName}";
                    if (!empty($customerPhone)) {
                        $row .= " | 📞 {$customerPhone}";
                    }
                }
                $tableRows[] = $row;
                $idx++;
            }
            $onuTable = implode("\n\n", $tableRows);
            
            $message = str_replace([
                '{olt_name}',
                '{olt_ip}',
                '{branch_name}',
                '{branch_code}',
                '{alert_time}',
                '{fault_count}',
                '{onu_table}'
            ], [
                $oltName,
                $oltIp,
                $branchName,
                $input['branch_code'] ?? '',
                date('Y-m-d H:i:s'),
                count($typeFaults),
                $onuTable
            ], $template);
            $messages[] = $message;
        }
        
        $combinedMessage = implode("\n\n---\n\n", $messages);
        $result = $whatsapp->sendToGroup($groupId, $combinedMessage);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Fault notification processed',
            'group_id' => $groupId,
            'fault_count' => count($faults),
            'tickets_created' => $ticketsCreated,
            'whatsapp_sent' => $result['success'] ?? false,
            'messageId' => $result['messageId'] ?? null
        ]);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Unknown notification type']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
