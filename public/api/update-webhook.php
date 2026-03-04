<?php
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET');
    header('Access-Control-Allow-Headers: Content-Type');
    exit;
}

require_once __DIR__ . '/../../src/UpdateManager.php';
$updateManager = new UpdateManager();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'push_update':
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            exit;
        }

        $token = $input['activation_token'] ?? '';
        if (empty($token) || !$updateManager->validateActivationToken($token)) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Invalid activation token']);
            exit;
        }

        if (!$updateManager->isRemoteUpdateAllowed()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Remote updates are disabled on this server']);
            exit;
        }

        if ($updateManager->isLocked()) {
            http_response_code(409);
            echo json_encode(['success' => false, 'error' => 'Update already in progress']);
            exit;
        }

        $update = $input['update'] ?? [];
        if (empty($update['version']) || empty($update['download_url'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing update version or download URL']);
            exit;
        }

        file_put_contents('/tmp/update_push_log.txt', date('Y-m-d H:i:s') . " Push update received: v{$update['version']}\n", FILE_APPEND);

        $result = $updateManager->applyUpdate($update);
        echo json_encode($result);
        break;

    case 'status':
        require_once __DIR__ . '/../../src/LicenseClient.php';
        $version = LicenseClient::APP_VERSION;

        echo json_encode([
            'success' => true,
            'version' => $version,
            'update_locked' => $updateManager->isLocked(),
            'remote_updates_allowed' => $updateManager->isRemoteUpdateAllowed(),
            'php_version' => PHP_VERSION,
            'hostname' => gethostname()
        ]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action. Use push_update or status']);
}
