<?php
require_once __DIR__ . '/../src/License.php';

$config = require __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $dsn = "pgsql:host={$config['host']};port={$config['port']};dbname={$config['database']}";
    $db = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$license = new LicenseServer\License($db, $config);

try {
    $license->ensureSchema();
} catch (Exception $e) {
}

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = str_replace('/api', '', $uri);
$method = $_SERVER['REQUEST_METHOD'];

$input = json_decode(file_get_contents('php://input'), true) ?: [];

function getClientInfo(): array {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    return [
        'domain' => $input['domain'] ?? $_SERVER['HTTP_HOST'] ?? null,
        'server_ip' => $input['server_ip'] ?? $_SERVER['REMOTE_ADDR'] ?? null,
        'hostname' => $input['hostname'] ?? null,
        'hardware_id' => $input['hardware_id'] ?? null,
        'php_version' => $input['php_version'] ?? null,
        'os_info' => $input['os_info'] ?? null,
        'app_version' => $input['app_version'] ?? null
    ];
}

function getClientStats(): array {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    return [
        'app_version' => $input['app_version'] ?? null,
        'php_version' => $input['php_version'] ?? null,
        'os_info' => $input['os_info'] ?? null,
        'user_count' => $input['user_count'] ?? null,
        'customer_count' => $input['customer_count'] ?? null,
        'onu_count' => $input['onu_count'] ?? null,
        'ticket_count' => $input['ticket_count'] ?? null,
        'disk_usage' => $input['disk_usage'] ?? null,
        'db_size' => $input['db_size'] ?? null,
        'server_ip' => $input['server_ip'] ?? $_SERVER['REMOTE_ADDR'] ?? null
    ];
}

switch ($uri) {
    case '/':
    case '/health':
        echo json_encode(['status' => 'ok', 'service' => 'License Server', 'version' => '2.0.0']);
        break;
        
    case '/api/validate':
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
        }
        $result = $license->validateLicense($input['license_key'] ?? '', getClientInfo());
        echo json_encode($result);
        break;
        
    case '/api/activate':
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
        }
        $result = $license->activate($input['license_key'] ?? '', getClientInfo());
        echo json_encode($result);
        break;
        
    case '/api/heartbeat':
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
        }
        $result = $license->heartbeat($input['activation_token'] ?? '', getClientStats());
        echo json_encode($result);
        break;
        
    case '/api/deactivate':
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
        }
        $result = $license->deactivate($input['activation_token'] ?? '', $input['reason'] ?? 'Manual deactivation');
        echo json_encode(['success' => $result]);
        break;

    case '/api/check-update':
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
        }
        $activationToken = $input['activation_token'] ?? '';
        $currentVersion = $input['app_version'] ?? '0.0.0';
        
        $stmt = $db->prepare("
            SELECT a.license_id FROM license_activations a 
            WHERE a.activation_token = ? AND a.is_active = TRUE
        ");
        $stmt->execute([$activationToken]);
        $licenseId = $stmt->fetchColumn();
        
        if (!$licenseId) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid activation']);
            break;
        }
        
        $update = $license->getAvailableUpdate($currentVersion, $licenseId);
        echo json_encode([
            'update_available' => $update !== null,
            'update' => $update
        ]);
        break;

    case '/api/report-update':
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
        }
        
        $stmt = $db->prepare("SELECT id FROM license_activations WHERE activation_token = ? AND is_active = TRUE");
        $stmt->execute([$input['activation_token'] ?? '']);
        $activationId = $stmt->fetchColumn();
        
        if (!$activationId) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid activation']);
            break;
        }
        
        $license->logUpdateInstall(
            $activationId,
            (int)($input['update_id'] ?? 0),
            $input['from_version'] ?? '',
            $input['to_version'] ?? '',
            $input['status'] ?? 'completed',
            $input['error'] ?? null
        );
        echo json_encode(['success' => true]);
        break;
        
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found']);
}
