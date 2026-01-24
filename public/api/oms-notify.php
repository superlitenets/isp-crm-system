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
            "*🆕 New ONU Discovery*\n\nOLT: {olt_name} ({olt_ip})\nBranch: {branch_name}\n\nFound {onu_count} unconfigured ONU(s):\n{onu_list}\n\nDiscovered at: {discovery_time}"
        );
        
        $oltName = $discoveries[0]['olt_name'] ?? 'Unknown';
        $branchName = $discoveries[0]['branch_name'] ?? 'Unassigned';
        $branchCode = $discoveries[0]['branch_code'] ?? '';
        $oltIp = $discoveries[0]['olt_ip'] ?? '';
        
        $onuList = [];
        $onuSerials = [];
        foreach ($discoveries as $d) {
            $eqid = !empty($d['equipment_id']) ? " ({$d['equipment_id']})" : '';
            $onuList[] = "• SN: {$d['serial_number']}{$eqid} @ {$d['frame_slot_port']}";
            $onuSerials[] = $d['serial_number'];
        }
        
        $message = str_replace([
            '{olt_name}',
            '{olt_ip}',
            '{branch_name}',
            '{branch_code}',
            '{onu_count}',
            '{onu_list}',
            '{onu_serials}',
            '{discovery_time}'
        ], [
            $oltName,
            $oltIp,
            $branchName,
            $branchCode,
            count($discoveries),
            implode("\n", $onuList),
            implode(", ", $onuSerials),
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
        
        // Send WhatsApp notification
        $template = $settings->get('wa_template_oms_fault', 
            "*⚠️ ONU Fault Alert*\n\nOLT: {olt_name} ({olt_ip})\nBranch: {branch_name}\n\n{fault_count} ONU(s) went offline:\n{fault_list}\n\nDetected at: {detection_time}"
        );
        
        $faultList = [];
        foreach ($faults as $f) {
            $customerInfo = !empty($f['customer_name']) ? " - {$f['customer_name']}" : '';
            $statusIcon = $f['new_status'] === 'los' ? '🔴 LOS' : ($f['new_status'] === 'dying-gasp' ? '⚡ Power' : '❌ Offline');
            $faultList[] = "• {$statusIcon}: {$f['name']} (0/{$f['slot']}/{$f['port']}/{$f['onu_id']}){$customerInfo}";
        }
        
        $message = str_replace([
            '{olt_name}',
            '{olt_ip}',
            '{branch_name}',
            '{fault_count}',
            '{fault_list}',
            '{detection_time}'
        ], [
            $oltName,
            $oltIp,
            $branchName,
            count($faults),
            implode("\n", $faultList),
            date('Y-m-d H:i:s')
        ], $template);
        
        $result = $whatsapp->sendToGroup($groupId, $message);
        
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
