<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/Mpesa.php';

$input = file_get_contents('php://input');
$data = json_decode($input, true);

error_log("M-Pesa B2C Callback received: " . $input);

if (!$data) {
    http_response_code(400);
    echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Invalid payload']);
    exit;
}

try {
    $mpesa = new \App\Mpesa();
    $result = $mpesa->handleB2CCallback($data);
    
    echo json_encode([
        'ResultCode' => 0,
        'ResultDesc' => 'Callback processed successfully'
    ]);
} catch (\Exception $e) {
    error_log("M-Pesa B2C Callback error: " . $e->getMessage());
    echo json_encode([
        'ResultCode' => 1,
        'ResultDesc' => 'Callback processing failed'
    ]);
}
