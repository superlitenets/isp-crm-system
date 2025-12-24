<?php
error_reporting(E_ERROR | E_PARSE);
header('Content-Type: application/json');
header('Cache-Control: no-cache');

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/HuaweiOLT.php';

try {
    $db = Database::getConnection();
    $huaweiOLT = new \App\HuaweiOLT($db);
    $stats = $huaweiOLT->getDashboardStats();
    
    echo json_encode([
        'success' => true,
        'unconfigured_onus' => $stats['unconfigured_onus'] ?? 0,
        'discovered_onus' => $stats['discovered_onus'] ?? 0,
        'total_pending' => ($stats['unconfigured_onus'] ?? 0) + ($stats['discovered_onus'] ?? 0)
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
