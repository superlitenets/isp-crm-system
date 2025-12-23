<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';

$input = file_get_contents('php://input');
$data = json_decode($input, true);

error_log("M-Pesa B2B Timeout received: " . $input);

try {
    $db = \Database::getConnection();
    
    $conversationId = $data['Result']['ConversationID'] ?? '';
    
    if ($conversationId) {
        $stmt = $db->prepare("
            UPDATE mpesa_b2b_transactions 
            SET status = 'failed', result_desc = 'Request timed out', updated_at = CURRENT_TIMESTAMP
            WHERE conversation_id = ?
        ");
        $stmt->execute([$conversationId]);
    }
    
    echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Timeout handled']);
} catch (\Exception $e) {
    error_log("M-Pesa B2B Timeout error: " . $e->getMessage());
    echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Error']);
}
