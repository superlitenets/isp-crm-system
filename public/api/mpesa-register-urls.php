<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache');

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/Mpesa.php';
require_once __DIR__ . '/../../src/RadiusBilling.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'POST required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$accountId = isset($input['account_id']) ? (int)$input['account_id'] : null;

if (!$accountId) {
    echo json_encode(['success' => false, 'error' => 'account_id is required']);
    exit;
}

try {
    $db = Database::getConnection();
    $radiusBilling = new \App\RadiusBilling($db);
    $acctConfig = $radiusBilling->getMpesaAccountConfig($accountId);

    if (!$acctConfig) {
        echo json_encode(['success' => false, 'error' => 'M-Pesa account not found or inactive']);
        exit;
    }

    $mpesa = new \App\Mpesa($acctConfig);

    if (!$mpesa->isConfigured()) {
        echo json_encode(['success' => false, 'error' => 'M-Pesa account is not fully configured. Check credentials.']);
        exit;
    }

    $result = $mpesa->registerC2BUrls();

    echo json_encode($result);
} catch (Exception $e) {
    error_log("M-Pesa URL registration error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
