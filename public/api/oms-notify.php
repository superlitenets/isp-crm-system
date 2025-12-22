<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/WhatsApp.php';
require_once __DIR__ . '/../../src/Settings.php';

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

if (empty($type) || empty($groupId) || empty($discoveries)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

try {
    $settings = new Settings();
    $whatsapp = new WhatsApp();
    
    if ($type === 'new_onu_discovery') {
        $template = $settings->get('wa_template_oms_new_onu', 
            "*🆕 New ONU Discovery*\n\nOLT: {olt_name}\nBranch: {branch_name}\n\nFound {onu_count} unconfigured ONU(s):\n{onu_list}\n\nDiscovered at: {discovery_time}"
        );
        
        $oltName = $discoveries[0]['olt_name'] ?? 'Unknown';
        $branchName = $discoveries[0]['branch_name'] ?? 'Unknown';
        $branchCode = $discoveries[0]['branch_code'] ?? '';
        $oltIp = $discoveries[0]['olt_ip'] ?? '';
        
        $onuList = [];
        $onuSerials = [];
        foreach ($discoveries as $d) {
            $onuList[] = "• SN: {$d['serial_number']} @ {$d['frame_slot_port']}";
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
        
        $result = $whatsapp->sendGroupMessage($groupId, $message);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Notification sent',
            'group_id' => $groupId,
            'onu_count' => count($discoveries)
        ]);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Unknown notification type']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
