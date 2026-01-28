<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../../src/License.php';
require_once __DIR__ . '/../../src/Mpesa.php';

$config = require __DIR__ . '/../../config/database.php';

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

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$action = $input['action'] ?? $_GET['action'] ?? null;

switch ($action) {
    case 'initiate':
        if (empty($input['license_key']) || empty($input['phone'])) {
            http_response_code(400);
            echo json_encode(['error' => 'License key and phone number required']);
            exit;
        }
        
        $stmt = $db->prepare("
            SELECT l.*, t.price_monthly, t.price_yearly, t.name as tier_name, c.name as customer_name
            FROM licenses l
            JOIN license_tiers t ON l.tier_id = t.id
            LEFT JOIN license_customers c ON l.customer_id = c.id
            WHERE l.license_key = ?
        ");
        $stmt->execute([$input['license_key']]);
        $license = $stmt->fetch();
        
        if (!$license) {
            http_response_code(404);
            echo json_encode(['error' => 'License not found']);
            exit;
        }
        
        $billingCycle = $input['billing_cycle'] ?? 'monthly';
        $amount = $billingCycle === 'yearly' ? $license['price_yearly'] : $license['price_monthly'];
        
        if ($amount <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'This tier has no payment configured']);
            exit;
        }
        
        $mpesaConfig = [
            'consumer_key' => getenv('MPESA_CONSUMER_KEY'),
            'consumer_secret' => getenv('MPESA_CONSUMER_SECRET'),
            'shortcode' => getenv('MPESA_SHORTCODE'),
            'passkey' => getenv('MPESA_PASSKEY'),
            'env' => getenv('MPESA_ENV') ?: 'sandbox',
            'callback_url' => rtrim(getenv('LICENSE_SERVER_URL') ?: '', '/') . '/api/pay.php?action=callback'
        ];
        
        if (empty($mpesaConfig['consumer_key'])) {
            http_response_code(500);
            echo json_encode(['error' => 'M-Pesa not configured']);
            exit;
        }
        
        try {
            $mpesa = new LicenseServer\Mpesa($mpesaConfig);
            $reference = 'LIC-' . substr($license['license_key'], 0, 8);
            $result = $mpesa->stkPush($input['phone'], $amount, $reference, 'License Payment - ' . $license['tier_name']);
            
            $stmt = $db->prepare("
                INSERT INTO license_payments (license_id, amount, currency, payment_method, transaction_id, phone_number, status, metadata)
                VALUES (?, ?, 'KES', 'mpesa', ?, ?, 'pending', ?)
            ");
            $stmt->execute([
                $license['id'],
                $amount,
                $result['CheckoutRequestID'] ?? null,
                $input['phone'],
                json_encode([
                    'billing_cycle' => $billingCycle,
                    'merchant_request_id' => $result['MerchantRequestID'] ?? null
                ])
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Payment request sent to your phone',
                'checkout_request_id' => $result['CheckoutRequestID'] ?? null
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;
        
    case 'callback':
        $data = json_decode(file_get_contents('php://input'), true);
        file_put_contents('/tmp/mpesa_license_callback.log', date('Y-m-d H:i:s') . ' ' . json_encode($data) . "\n", FILE_APPEND);
        
        if (!$data) {
            echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
            exit;
        }
        
        $mpesaConfig = ['env' => getenv('MPESA_ENV') ?: 'sandbox'];
        $mpesa = new LicenseServer\Mpesa($mpesaConfig);
        $result = $mpesa->processCallback($data);
        
        if (!$result['checkout_request_id']) {
            echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
            exit;
        }
        
        $stmt = $db->prepare("SELECT * FROM license_payments WHERE transaction_id = ?");
        $stmt->execute([$result['checkout_request_id']]);
        $payment = $stmt->fetch();
        
        if (!$payment) {
            echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Payment not found']);
            exit;
        }
        
        if ($result['success']) {
            $metadata = json_decode($payment['metadata'] ?: '{}', true);
            $billingCycle = $metadata['billing_cycle'] ?? 'monthly';
            $months = $billingCycle === 'yearly' ? 12 : 1;
            
            $db->beginTransaction();
            try {
                $stmt = $db->prepare("
                    UPDATE license_payments 
                    SET status = 'completed', mpesa_receipt = ?, paid_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$result['mpesa_receipt'], $payment['id']]);
                
                $stmt = $db->prepare("SELECT expires_at FROM licenses WHERE id = ?");
                $stmt->execute([$payment['license_id']]);
                $license = $stmt->fetch();
                
                $baseDate = $license['expires_at'] && strtotime($license['expires_at']) > time() 
                    ? $license['expires_at'] 
                    : date('Y-m-d H:i:s');
                $newExpiry = date('Y-m-d H:i:s', strtotime($baseDate . " +{$months} months"));
                
                $stmt = $db->prepare("UPDATE licenses SET expires_at = ?, is_active = TRUE, is_suspended = FALSE WHERE id = ?");
                $stmt->execute([$newExpiry, $payment['license_id']]);
                
                $db->commit();
            } catch (Exception $e) {
                $db->rollBack();
            }
        } else {
            $stmt = $db->prepare("UPDATE license_payments SET status = 'failed' WHERE id = ?");
            $stmt->execute([$payment['id']]);
        }
        
        echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        break;
        
    case 'check':
        if (empty($input['license_key'])) {
            http_response_code(400);
            echo json_encode(['error' => 'License key required']);
            exit;
        }
        
        $stmt = $db->prepare("
            SELECT l.*, t.price_monthly, t.price_yearly, t.name as tier_name, c.name as customer_name, c.email
            FROM licenses l
            JOIN license_tiers t ON l.tier_id = t.id
            LEFT JOIN license_customers c ON l.customer_id = c.id
            WHERE l.license_key = ?
        ");
        $stmt->execute([$input['license_key']]);
        $license = $stmt->fetch();
        
        if (!$license) {
            http_response_code(404);
            echo json_encode(['error' => 'License not found']);
            exit;
        }
        
        echo json_encode([
            'license_key' => $license['license_key'],
            'customer' => $license['customer_name'],
            'tier' => $license['tier_name'],
            'expires_at' => $license['expires_at'],
            'is_active' => $license['is_active'] && !$license['is_suspended'],
            'is_expired' => $license['expires_at'] && strtotime($license['expires_at']) < time(),
            'price_monthly' => (float)$license['price_monthly'],
            'price_yearly' => (float)$license['price_yearly']
        ]);
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}
