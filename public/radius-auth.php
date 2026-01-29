<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/RadiusBilling.php';

header('Content-Type: application/json');

$db = getDbConnection();
$radiusBilling = new \App\RadiusBilling($db);

$input = file_get_contents('php://input');
$data = json_decode($input, true) ?? $_POST;

$username = $data['User-Name'] ?? $data['username'] ?? '';
$password = $data['User-Password'] ?? $data['password'] ?? '';
$nasIP = $data['NAS-IP-Address'] ?? $data['nas_ip'] ?? '';
$callingStationId = $data['Calling-Station-Id'] ?? $data['mac'] ?? '';

if (empty($username)) {
    echo json_encode([
        'Reply-Message' => 'Missing username',
        'control:Auth-Type' => 'Reject'
    ]);
    exit;
}

$result = $radiusBilling->authenticate($username, $password, $nasIP, $callingStationId);

if ($result['success']) {
    $response = [
        'control:Auth-Type' => 'Accept',
        'control:Cleartext-Password' => $password
    ];
    
    if (!empty($result['attributes'])) {
        foreach ($result['attributes'] as $attr => $value) {
            $response["reply:$attr"] = $value;
        }
    }
    
    if (!empty($result['expired'])) {
        $response['reply:Reply-Message'] = 'Account expired - limited access';
    }
    
    if (!empty($result['quota_exhausted'])) {
        $response['reply:Reply-Message'] = 'Data quota exhausted - limited access';
    }
    
    echo json_encode($response);
} else {
    echo json_encode([
        'Reply-Message' => $result['reason'] ?? 'Authentication failed',
        'control:Auth-Type' => 'Reject'
    ]);
}
