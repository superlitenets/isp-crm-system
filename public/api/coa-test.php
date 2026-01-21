<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/RadiusClient.php';

header('Content-Type: application/json');

$db = \Database::getConnection();

$nasId = $_GET['nas_id'] ?? $_POST['nas_id'] ?? 1;
$username = $_GET['username'] ?? $_POST['username'] ?? 'test-user';

$stmt = $db->prepare("SELECT * FROM radius_nas WHERE id = ? AND is_active = true");
$stmt->execute([$nasId]);
$nas = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$nas) {
    echo json_encode(['success' => false, 'error' => 'NAS not found or inactive']);
    exit;
}

$client = new \App\RadiusClient($nas['ip_address'], $nas['secret'], (int)($nas['ports'] ?: 3799), 5);

$result = $client->disconnect([
    'User-Name' => $username,
    'NAS-IP-Address' => $nas['ip_address']
]);

echo json_encode([
    'success' => $result['success'],
    'nas' => [
        'name' => $nas['name'],
        'ip' => $nas['ip_address'],
        'port' => $nas['ports'] ?: 3799
    ],
    'test_user' => $username,
    'result' => $result
]);
