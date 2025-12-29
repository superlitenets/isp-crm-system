<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/HuaweiOLT.php';

$oltId = (int)($_GET['olt_id'] ?? 0);

if ($oltId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Missing olt_id']);
    exit;
}

try {
    $huaweiOLT = new \App\HuaweiOLT($db);
    
    $result = $huaweiOLT->getONUListViaSNMP($oltId);
    
    if (!$result['success']) {
        echo json_encode(['success' => false, 'error' => $result['error'] ?? 'SNMP failed']);
        exit;
    }
    
    $onus = $result['onus'] ?? [];
    $enrichedOnus = [];
    
    foreach ($onus as $onu) {
        $frame = $onu['frame'] ?? 0;
        $slot = $onu['slot'] ?? 0;
        $port = $onu['port'] ?? 0;
        $onuId = $onu['onu_id'] ?? 0;
        
        if ($onuId > 0) {
            $optical = $huaweiOLT->getONUOpticalInfoViaSNMP($oltId, $frame, $slot, $port, $onuId);
            if ($optical['success'] && isset($optical['optical'])) {
                $onu['rx_power'] = $optical['optical']['rx_power'] ?? null;
                $onu['tx_power'] = $optical['optical']['tx_power'] ?? null;
                $onu['distance'] = $optical['optical']['distance'] ?? null;
            }
        }
        
        $enrichedOnus[] = $onu;
    }
    
    echo json_encode([
        'success' => true,
        'count' => count($enrichedOnus),
        'onus' => $enrichedOnus
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
