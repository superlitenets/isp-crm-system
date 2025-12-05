<?php

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('X-Content-Type-Options: nosniff');

if (extension_loaded('zlib') && !headers_sent()) {
    ob_start('ob_gzhandler');
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/init_db.php';
require_once __DIR__ . '/../src/MobileAPI.php';

initializeDatabase();

$api = new \App\MobileAPI();

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

$input = [];
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
}

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$token = str_replace('Bearer ', '', $authHeader);

$user = null;
if ($token) {
    $user = $api->validateToken($token);
}

function requireAuth() {
    global $user;
    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
}

try {
    switch ($action) {
        case 'login':
            if ($method !== 'POST') {
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
                break;
            }
            
            $email = $input['email'] ?? '';
            $password = $input['password'] ?? '';
            
            if (!$email || !$password) {
                echo json_encode(['success' => false, 'error' => 'Email and password required']);
                break;
            }
            
            $result = $api->authenticate($email, $password);
            
            if ($result) {
                $salesperson = $api->getSalespersonByUserId($result['user']['id']);
                $employee = $api->getEmployeeByUserId($result['user']['id']);
                
                echo json_encode([
                    'success' => true,
                    'user' => $result['user'],
                    'token' => $result['token'],
                    'salesperson' => $salesperson,
                    'employee' => $employee
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Invalid email or password']);
            }
            break;
            
        case 'validate':
            requireAuth();
            $salesperson = $api->getSalespersonByUserId($user['id']);
            $employee = $api->getEmployeeByUserId($user['id']);
            
            echo json_encode([
                'success' => true,
                'user' => $user,
                'salesperson' => $salesperson,
                'employee' => $employee
            ]);
            break;
            
        case 'logout':
            if ($token) {
                $api->logout($token);
            }
            echo json_encode(['success' => true]);
            break;
            
        case 'salesperson-dashboard':
            requireAuth();
            $data = $api->getSalespersonDashboard($user['id']);
            if (isset($data['error'])) {
                echo json_encode(['success' => false, 'error' => $data['error']]);
            } else {
                echo json_encode(['success' => true, 'data' => $data]);
            }
            break;
            
        case 'salesperson-stats':
            requireAuth();
            $salesperson = $api->getSalespersonByUserId($user['id']);
            if (!$salesperson) {
                echo json_encode(['success' => false, 'error' => 'Not a salesperson']);
                break;
            }
            
            $stats = $api->getSalespersonStats($salesperson['id']);
            echo json_encode(['success' => true, 'data' => $stats]);
            break;
            
        case 'salesperson-orders':
            requireAuth();
            $salesperson = $api->getSalespersonByUserId($user['id']);
            if (!$salesperson) {
                echo json_encode(['success' => false, 'error' => 'Not a salesperson']);
                break;
            }
            
            $status = $_GET['status'] ?? '';
            $orders = $api->getSalespersonOrders($salesperson['id'], $status);
            echo json_encode(['success' => true, 'data' => $orders]);
            break;
            
        case 'packages':
            $packages = $api->getServicePackages();
            echo json_encode(['success' => true, 'data' => $packages]);
            break;
            
        case 'create-order':
            requireAuth();
            $salesperson = $api->getSalespersonByUserId($user['id'], true);
            if (!$salesperson) {
                echo json_encode(['success' => false, 'error' => 'Not authorized as a salesperson. Please contact admin.']);
                break;
            }
            
            if (empty($input['customer_name']) || empty($input['customer_phone'])) {
                echo json_encode(['success' => false, 'error' => 'Customer name and phone required']);
                break;
            }
            
            $orderId = $api->createOrder($salesperson['id'], $input);
            echo json_encode(['success' => true, 'order_id' => $orderId]);
            break;
            
        case 'create-lead':
            requireAuth();
            $salesperson = $api->getSalespersonByUserId($user['id'], true);
            if (!$salesperson) {
                echo json_encode(['success' => false, 'error' => 'Not authorized as a salesperson. Please contact admin.']);
                break;
            }
            
            if (empty($input['customer_name']) || empty($input['customer_phone']) || empty($input['location'])) {
                echo json_encode(['success' => false, 'error' => 'Customer name, phone, and location are required']);
                break;
            }
            
            $leadId = $api->createLead($salesperson['id'], $input);
            echo json_encode(['success' => true, 'lead_id' => $leadId, 'message' => 'Lead submitted successfully']);
            break;
            
        case 'new-orders-count':
            requireAuth();
            $count = $api->getNewOrdersCount();
            echo json_encode(['success' => true, 'count' => $count]);
            break;
            
        case 'technician-dashboard':
            requireAuth();
            $data = $api->getTechnicianDashboard($user['id']);
            echo json_encode(['success' => true, 'data' => $data]);
            break;
            
        case 'technician-stats':
            requireAuth();
            $stats = $api->getTechnicianStats($user['id']);
            echo json_encode(['success' => true, 'data' => $stats]);
            break;
            
        case 'technician-tickets':
            requireAuth();
            $status = $_GET['status'] ?? '';
            $tickets = $api->getTechnicianTickets($user['id'], $status);
            echo json_encode(['success' => true, 'data' => $tickets]);
            break;
            
        case 'ticket-detail':
            requireAuth();
            $ticketId = (int) ($_GET['id'] ?? 0);
            if (!$ticketId) {
                echo json_encode(['success' => false, 'error' => 'Ticket ID required']);
                break;
            }
            
            $ticket = $api->getTicketDetails($ticketId, $user['id']);
            if ($ticket) {
                echo json_encode(['success' => true, 'data' => $ticket]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Ticket not found']);
            }
            break;
            
        case 'update-ticket':
            requireAuth();
            $ticketId = (int) ($input['ticket_id'] ?? 0);
            $status = $input['status'] ?? '';
            $comment = $input['comment'] ?? null;
            
            if (!$ticketId || !$status) {
                echo json_encode(['success' => false, 'error' => 'Ticket ID and status required']);
                break;
            }
            
            $result = $api->updateTicketStatus($ticketId, $user['id'], $status, $comment);
            echo json_encode(['success' => $result]);
            break;
            
        case 'add-comment':
            requireAuth();
            $ticketId = (int) ($input['ticket_id'] ?? 0);
            $comment = $input['comment'] ?? '';
            
            if (!$ticketId || !$comment) {
                echo json_encode(['success' => false, 'error' => 'Ticket ID and comment required']);
                break;
            }
            
            $result = $api->addTicketComment($ticketId, $user['id'], $comment);
            echo json_encode(['success' => $result]);
            break;
            
        case 'today-attendance':
            requireAuth();
            $employee = $api->getEmployeeByUserId($user['id']);
            if (!$employee) {
                echo json_encode(['success' => false, 'error' => 'Employee not found']);
                break;
            }
            
            $attendance = $api->getTodayAttendance($employee['id']);
            echo json_encode(['success' => true, 'data' => $attendance]);
            break;
            
        case 'clock-in':
            requireAuth();
            $employee = $api->getEmployeeByUserId($user['id']);
            if (!$employee) {
                echo json_encode(['success' => false, 'error' => 'Employee not found']);
                break;
            }
            
            $result = $api->clockIn($employee['id']);
            echo json_encode($result);
            break;
            
        case 'clock-out':
            requireAuth();
            $employee = $api->getEmployeeByUserId($user['id']);
            if (!$employee) {
                echo json_encode(['success' => false, 'error' => 'Employee not found']);
                break;
            }
            
            $result = $api->clockOut($employee['id']);
            echo json_encode($result);
            break;
            
        case 'assigned-equipment':
            requireAuth();
            $equipment = $api->getAssignedEquipment($user['id']);
            echo json_encode(['success' => true, 'data' => $equipment]);
            break;
            
        case 'attendance-history':
            requireAuth();
            $employee = $api->getEmployeeByUserId($user['id']);
            if (!$employee) {
                echo json_encode(['success' => false, 'error' => 'Employee not found']);
                break;
            }
            
            $history = $api->getRecentAttendance($employee['id']);
            echo json_encode(['success' => true, 'data' => $history]);
            break;
            
        case 'salesperson-performance':
            requireAuth();
            $salesperson = $api->getSalespersonByUserId($user['id']);
            if (!$salesperson) {
                echo json_encode(['success' => false, 'error' => 'Not a salesperson']);
                break;
            }
            
            $performance = $api->getSalespersonPerformance($salesperson['id']);
            echo json_encode(['success' => true, 'data' => $performance]);
            break;
            
        case 'technician-performance':
            requireAuth();
            $performance = $api->getTechnicianPerformance($user['id']);
            echo json_encode(['success' => true, 'data' => $performance]);
            break;
            
        case 'create-ticket':
            requireAuth();
            
            $employee = $api->getEmployeeByUserId($user['id']);
            $allowedRoles = ['admin', 'technician', 'manager'];
            if (!in_array($user['role'] ?? '', $allowedRoles) && !$employee) {
                echo json_encode(['success' => false, 'error' => 'Not authorized to create tickets']);
                break;
            }
            
            if (empty($input['subject']) || strlen(trim($input['subject'])) < 3) {
                echo json_encode(['success' => false, 'error' => 'Subject is required (minimum 3 characters)']);
                break;
            }
            
            $validCategories = ['installation', 'fault', 'relocation', 'upgrade', 'billing', 'general'];
            if (!empty($input['category']) && !in_array($input['category'], $validCategories)) {
                $input['category'] = 'general';
            }
            
            $validPriorities = ['low', 'medium', 'high', 'critical'];
            if (!empty($input['priority']) && !in_array($input['priority'], $validPriorities)) {
                $input['priority'] = 'medium';
            }
            
            $ticketId = $api->createTicket($user['id'], $input);
            if ($ticketId) {
                echo json_encode(['success' => true, 'ticket_id' => $ticketId]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to create ticket']);
            }
            break;
            
        case 'ticket-categories':
            requireAuth();
            $categories = $api->getTicketCategories();
            echo json_encode(['success' => true, 'data' => $categories]);
            break;
            
        case 'search-customers':
            requireAuth();
            $query = $_GET['q'] ?? '';
            if (strlen($query) < 2) {
                echo json_encode(['success' => true, 'data' => []]);
                break;
            }
            
            $customers = $api->searchCustomers($query);
            echo json_encode(['success' => true, 'data' => $customers]);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
    }
} catch (Exception $e) {
    error_log('Mobile API Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
