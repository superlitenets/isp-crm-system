<?php
// WhatsApp Webhook Handler
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true);

if (!$input) {
    http_response_code(400);
    exit('Invalid payload');
}

$from = $input['key']['remoteJid'] ?? 'unknown';
$text = $input['message']['conversation']
     ?? $input['message']['extendedTextMessage']['text']
     ?? $input['message']['imageMessage']['caption']
     ?? null;

if ($text) {
    // Log the message
    $logDir = __DIR__ . '/../../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }
    
    file_put_contents(
        $logDir . '/whatsapp_webhook.log',
        date('Y-m-d H:i:s') . " | From: {$from} | Msg: {$text}\n",
        FILE_APPEND
    );
    
    // Here you would typically trigger ticket creation or automated replies
}

header('Content-Type: application/json');
echo json_encode(['success' => true]);
