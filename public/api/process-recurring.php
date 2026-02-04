<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/init_db.php';

header('Content-Type: application/json');

$secret = $_SERVER['HTTP_X_CRON_SECRET'] ?? $_GET['secret'] ?? '';
$expectedSecret = getenv('CRON_SECRET') ?: getenv('SESSION_SECRET');

if (empty($expectedSecret) || $secret !== $expectedSecret) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized - CRON_SECRET or SESSION_SECRET must be configured']);
    exit;
}

try {
    $db = Database::getConnection();
    $accounting = new \App\Accounting($db);
    
    $results = $accounting->processRecurringInvoices();
    
    $successCount = count(array_filter($results, fn($r) => !isset($r['error'])));
    $errorCount = count(array_filter($results, fn($r) => isset($r['error'])));
    
    echo json_encode([
        'success' => true,
        'message' => "Processed {$successCount} recurring invoice(s)",
        'processed' => $successCount,
        'errors' => $errorCount,
        'details' => $results
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
