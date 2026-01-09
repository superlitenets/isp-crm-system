<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/SmartOLT.php';

header('Content-Type: application/json');

try {
    $smartolt = new SmartOLT($pdo);
    
    if (!$smartolt->isConfigured()) {
        echo json_encode(['success' => true, 'serials' => [], 'message' => 'SmartOLT not configured']);
        exit;
    }
    
    $result = $smartolt->getAllONUsDetails();
    
    if (!isset($result['ONUs']) || !is_array($result['ONUs'])) {
        echo json_encode(['success' => true, 'serials' => []]);
        exit;
    }
    
    $serials = [];
    foreach ($result['ONUs'] as $onu) {
        if (!empty($onu['sn'])) {
            $serials[] = $onu['sn'];
        }
    }
    
    echo json_encode(['success' => true, 'serials' => $serials, 'count' => count($serials)]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage(), 'serials' => []]);
}
