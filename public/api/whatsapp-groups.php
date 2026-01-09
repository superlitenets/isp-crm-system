<?php
header('Content-Type: application/json');

$waServiceUrl = 'http://127.0.0.1:3001/groups';

try {
    $ch = curl_init($waServiceUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        echo json_encode(['error' => 'WhatsApp service unavailable', 'groups' => []]);
        exit;
    }
    
    if ($httpCode === 503) {
        echo json_encode(['error' => 'WhatsApp not connected - scan QR code first', 'groups' => []]);
        exit;
    }
    
    if ($httpCode !== 200) {
        echo json_encode(['error' => 'Failed to fetch groups', 'groups' => []]);
        exit;
    }
    
    $data = json_decode($response, true);
    echo json_encode($data);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage(), 'groups' => []]);
}
