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
    
    $totalOnus = $stats['total_authorized_onus'] ?? $stats['total_onus'] ?? 0;
    $uptimePercent = $totalOnus > 0 ? round(($stats['online_onus'] / $totalOnus) * 100, 1) : 0;
    $offlineTotal = ($totalOnus - ($stats['online_onus'] ?? 0));
    
    echo json_encode([
        'success' => true,
        'total_olts' => $stats['total_olts'] ?? 0,
        'active_olts' => $stats['active_olts'] ?? 0,
        'total_onus' => $totalOnus,
        'online_onus' => $stats['online_onus'] ?? 0,
        'offline_onus' => $stats['offline_onus'] ?? 0,
        'los_onus' => $stats['los_onus'] ?? 0,
        'dying_gasp_onus' => $stats['dying_gasp_onus'] ?? 0,
        'unconfigured_onus' => $stats['unconfigured_onus'] ?? 0,
        'discovered_onus' => $stats['discovered_onus'] ?? 0,
        'total_pending' => ($stats['unconfigured_onus'] ?? 0) + ($stats['discovered_onus'] ?? 0),
        'uptime_percent' => $uptimePercent,
        'recent_alerts' => $stats['recent_alerts'] ?? 0
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
