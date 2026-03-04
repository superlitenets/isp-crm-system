<?php
require_once __DIR__ . '/../src/License.php';
require_once __DIR__ . '/../src/Mpesa.php';

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
        $autoUpdate = $license->getAutoApplyUpdate($currentVersion, $licenseId);
        $response = [
            'update_available' => $update !== null,
            'update' => $update
        ];
        if ($autoUpdate) {
            $response['auto_update'] = $autoUpdate;
        }
        echo json_encode($response);
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
        
    case '/api/subscription-info':
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
        }
        $licenseKey = $input['license_key'] ?? '';
        $stmt = $db->prepare("
            SELECT l.id, l.expires_at, l.is_active, l.is_suspended,
                   t.code as tier_code, t.name as tier_name, t.price_monthly, t.price_yearly,
                   t.max_users, t.max_customers, t.max_onus, t.max_olts, t.max_subscribers,
                   c.name as customer_name, c.phone as customer_phone
            FROM licenses l
            LEFT JOIN license_tiers t ON l.tier_id = t.id
            LEFT JOIN license_customers c ON l.customer_id = c.id
            WHERE l.license_key = ?
        ");
        $stmt->execute([$licenseKey]);
        $info = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$info) {
            http_response_code(404);
            echo json_encode(['error' => 'License not found']);
            break;
        }

        $paymentsStmt = $db->prepare("
            SELECT id, amount, status, mpesa_receipt, phone_number, paid_at, created_at
            FROM license_payments WHERE license_id = ? ORDER BY created_at DESC LIMIT 10
        ");
        $paymentsStmt->execute([$info['id']]);
        $payments = $paymentsStmt->fetchAll(\PDO::FETCH_ASSOC);

        $tiersStmt = $db->query("
            SELECT code, name, price_monthly, price_yearly, max_users, max_customers, max_onus, max_olts, max_subscribers
            FROM license_tiers WHERE is_active = TRUE ORDER BY price_monthly ASC
        ");
        $tiers = $tiersStmt->fetchAll(\PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'license' => [
                'expires_at' => $info['expires_at'],
                'is_active' => (bool)$info['is_active'],
                'is_suspended' => (bool)$info['is_suspended'],
                'tier_code' => $info['tier_code'],
                'tier_name' => $info['tier_name'],
                'price_monthly' => (float)$info['price_monthly'],
                'price_yearly' => (float)$info['price_yearly'],
                'customer_name' => $info['customer_name'],
                'customer_phone' => $info['customer_phone']
            ],
            'tiers' => $tiers,
            'payments' => $payments
        ]);
        break;

    case '/api/pay/initiate':
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
        }

        $licenseKey = $input['license_key'] ?? '';
        $phone = $input['phone'] ?? '';
        $billingCycle = $input['billing_cycle'] ?? 'monthly';

        if (empty($licenseKey) || empty($phone)) {
            http_response_code(400);
            echo json_encode(['error' => 'License key and phone number are required']);
            break;
        }

        $stmt = $db->prepare("
            SELECT l.id as license_id, t.price_monthly, t.price_yearly, t.name as tier_name
            FROM licenses l
            LEFT JOIN license_tiers t ON l.tier_id = t.id
            WHERE l.license_key = ?
        ");
        $stmt->execute([$licenseKey]);
        $licData = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$licData) {
            http_response_code(404);
            echo json_encode(['error' => 'License not found']);
            break;
        }

        $amount = $billingCycle === 'yearly' ? (float)$licData['price_yearly'] : (float)$licData['price_monthly'];
        if ($amount <= 0) {
            echo json_encode(['error' => 'No payment required for this tier']);
            break;
        }

        $mpesaConfig = [
            'consumer_key' => getenv('MPESA_CONSUMER_KEY') ?: '',
            'consumer_secret' => getenv('MPESA_CONSUMER_SECRET') ?: '',
            'shortcode' => getenv('MPESA_SHORTCODE') ?: '174379',
            'passkey' => getenv('MPESA_PASSKEY') ?: 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919',
            'account_type' => getenv('MPESA_ACCOUNT_TYPE') ?: 'paybill',
            'env' => getenv('MPESA_ENV') ?: 'sandbox',
            'callback_url' => rtrim(getenv('LICENSE_SERVER_PUBLIC_URL') ?: "https://{$_SERVER['HTTP_HOST']}", '/') . '/api/pay/callback'
        ];

        try {
            $mpesa = new \LicenseServer\Mpesa($mpesaConfig, $db);
            $ref = 'LIC-' . substr($licenseKey, 0, 8);
            $result = $mpesa->stkPush($phone, $amount, $ref, 'License');

            if (!isset($result['CheckoutRequestID'])) {
                echo json_encode(['success' => false, 'error' => 'STK Push failed', 'details' => $result]);
                break;
            }

            $mpesa->createPaymentRecord(
                $licData['license_id'],
                $amount,
                $phone,
                $result['CheckoutRequestID'],
                $billingCycle
            );

            echo json_encode([
                'success' => true,
                'checkout_request_id' => $result['CheckoutRequestID'],
                'message' => $result['CustomerMessage'] ?? 'Check your phone for the M-Pesa prompt',
                'amount' => $amount,
                'billing_cycle' => $billingCycle
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case '/api/pay/callback':
        $callbackData = json_decode(file_get_contents('php://input'), true);

        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
        file_put_contents($logDir . '/mpesa_callback_' . date('Y-m-d') . '.log',
            date('Y-m-d H:i:s') . ' ' . json_encode($callbackData) . "\n", FILE_APPEND);

        $mpesaConfig = [
            'consumer_key' => getenv('MPESA_CONSUMER_KEY') ?: '',
            'consumer_secret' => getenv('MPESA_CONSUMER_SECRET') ?: '',
            'shortcode' => getenv('MPESA_SHORTCODE') ?: '174379',
            'passkey' => getenv('MPESA_PASSKEY') ?: '',
            'env' => getenv('MPESA_ENV') ?: 'sandbox',
            'callback_url' => ''
        ];

        $mpesa = new \LicenseServer\Mpesa($mpesaConfig, $db);
        $result = $mpesa->processCallback($callbackData);
        echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Success']);
        break;

    case '/api/pay/status':
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
        }

        $checkoutRequestId = $input['checkout_request_id'] ?? '';
        if (empty($checkoutRequestId)) {
            http_response_code(400);
            echo json_encode(['error' => 'checkout_request_id required']);
            break;
        }

        $stmt = $db->prepare("SELECT id, status, mpesa_receipt, amount, paid_at FROM license_payments WHERE transaction_id = ?");
        $stmt->execute([$checkoutRequestId]);
        $payment = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$payment) {
            echo json_encode(['status' => 'not_found']);
        } else {
            echo json_encode([
                'status' => $payment['status'],
                'mpesa_receipt' => $payment['mpesa_receipt'],
                'amount' => $payment['amount'],
                'paid_at' => $payment['paid_at']
            ]);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found']);
}
