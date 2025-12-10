<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache');

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/Mpesa.php';

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

$phone = $input['phone'] ?? '';
$amount = floatval($input['amount'] ?? 0);

if (empty($phone) || $amount <= 0) {
    echo json_encode(['success' => false, 'error' => 'Phone and amount required']);
    exit;
}

if (!preg_match('/^254\d{9}$/', $phone)) {
    echo json_encode(['success' => false, 'error' => 'Invalid phone format. Use 254XXXXXXXXX']);
    exit;
}

try {
    $db = Database::getConnection();
    $mpesa = new \App\Mpesa();
    
    if (!$mpesa->isConfigured()) {
        echo json_encode(['success' => false, 'error' => 'M-Pesa not configured. Please save your API credentials first.']);
        exit;
    }
    
    $result = $mpesa->stkPush($phone, $amount, 'TEST' . time(), 'CRM Test Payment');
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'STK Push sent successfully',
            'checkoutRequestId' => $result['checkoutRequestId'] ?? null,
            'merchantRequestId' => $result['merchantRequestId'] ?? null
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => $result['error'] ?? 'STK Push failed'
        ]);
    }
} catch (Exception $e) {
    error_log("M-Pesa test error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
