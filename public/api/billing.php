<?php
error_reporting(0);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/OneISP.php';

header('Content-Type: application/json');
session_start();

if (!\App\Auth::check()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';
$db = \Database::getConnection();
$oneIsp = new \App\OneISP($db);

if (!$oneIsp->isConfigured()) {
    echo json_encode(['error' => 'Billing API not configured', 'configured' => false]);
    exit;
}

switch ($action) {
    case 'search':
        $search = trim($_GET['q'] ?? '');
        if (strlen($search) < 2) {
            echo json_encode(['customers' => [], 'message' => 'Enter at least 2 characters']);
            exit;
        }
        
        $result = $oneIsp->searchCustomers($search);
        if (!$result['success']) {
            echo json_encode(['error' => $result['error'] ?? 'Search failed']);
            exit;
        }
        
        $customers = [];
        $data = $result['data']['Customers'] ?? $result['data']['data'] ?? $result['data'] ?? [];
        if (!is_array($data)) {
            $data = [];
        }
        
        foreach ($data as $customer) {
            $mapped = $oneIsp->mapCustomerToLocal($customer);
            $customers[] = [
                'billing_id' => $mapped['billing_id'],
                'username' => $mapped['username'],
                'name' => $mapped['name'],
                'email' => $mapped['email'],
                'phone' => $mapped['phone'],
                'address' => $mapped['address'],
                'service_plan' => $mapped['service_plan'],
                'connection_status' => $mapped['connection_status'],
            ];
        }
        
        echo json_encode(['success' => true, 'customers' => $customers, 'total' => count($customers)]);
        break;
        
    case 'debug':
        $search = trim($_GET['q'] ?? 'test');
        $result = $oneIsp->searchCustomers($search);
        echo json_encode([
            'raw_response' => $result,
            'sample_customer' => isset($result['data']['data'][0]) ? $result['data']['data'][0] : (isset($result['data'][0]) ? $result['data'][0] : null)
        ], JSON_PRETTY_PRINT);
        break;
        
    case 'test':
        $result = $oneIsp->testConnection();
        echo json_encode($result);
        break;
        
    case 'import':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['error' => 'POST required']);
            exit;
        }
        
        $billingId = (int)($_POST['billing_id'] ?? 0);
        if (!$billingId) {
            echo json_encode(['error' => 'Billing ID required']);
            exit;
        }
        
        $customerResult = $oneIsp->getCustomer($billingId);
        if (!$customerResult['success']) {
            echo json_encode(['error' => $customerResult['error'] ?? 'Failed to fetch customer']);
            exit;
        }
        
        $customerId = $oneIsp->importCustomer($customerResult['data']);
        echo json_encode(['success' => true, 'customer_id' => $customerId]);
        break;
        
    default:
        echo json_encode(['error' => 'Invalid action']);
}
