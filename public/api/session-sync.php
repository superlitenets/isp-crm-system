<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/RadiusBilling.php';

try {
    $db = getDbConnection();
    $radiusBilling = new \App\RadiusBilling($db);
    
    $syncResult = $radiusBilling->syncSessionsWithRouter();
    
    $staleResult = $radiusBilling->cleanStaleSessions(24);
    
    echo json_encode([
        'success' => true,
        'sync' => $syncResult,
        'stale_cleaned' => $staleResult,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
