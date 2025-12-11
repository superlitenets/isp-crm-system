<?php

date_default_timezone_set('Africa/Nairobi');

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
            
            $identifier = $input['email'] ?? $input['identifier'] ?? '';
            $password = $input['password'] ?? '';
            
            if (!$identifier || !$password) {
                echo json_encode(['success' => false, 'error' => 'Email/phone and password required']);
                break;
            }
            
            $result = $api->authenticate($identifier, $password);
            
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
                echo json_encode(['success' => false, 'error' => 'Invalid email/phone or password']);
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
            if ($result['success']) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => $result['error'] ?? 'Failed to update ticket']);
            }
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
            
        case 'leave-balance':
            requireAuth();
            $employee = $api->getEmployeeByUserId($user['id']);
            if (!$employee) {
                echo json_encode(['success' => false, 'error' => 'Employee not found']);
                break;
            }
            
            $leaveService = new \App\Leave($db);
            $balance = $leaveService->getEmployeeBalance($employee['id']);
            echo json_encode(['success' => true, 'data' => $balance]);
            break;
            
        case 'leave-types':
            requireAuth();
            $leaveService = new \App\Leave($db);
            $types = $leaveService->getLeaveTypes();
            echo json_encode(['success' => true, 'data' => $types]);
            break;
            
        case 'leave-requests':
            requireAuth();
            $employee = $api->getEmployeeByUserId($user['id']);
            if (!$employee) {
                echo json_encode(['success' => false, 'error' => 'Employee not found']);
                break;
            }
            
            $leaveService = new \App\Leave($db);
            $requests = $leaveService->getEmployeeRequests($employee['id']);
            echo json_encode(['success' => true, 'data' => $requests]);
            break;
            
        case 'submit-leave-request':
            requireAuth();
            $employee = $api->getEmployeeByUserId($user['id']);
            if (!$employee) {
                echo json_encode(['success' => false, 'error' => 'Employee not found']);
                break;
            }
            
            if (empty($input['leave_type_id']) || empty($input['start_date']) || empty($input['end_date'])) {
                echo json_encode(['success' => false, 'error' => 'Leave type, start date, and end date are required']);
                break;
            }
            
            $leaveService = new \App\Leave($db);
            try {
                $requestId = $leaveService->createRequest([
                    'employee_id' => $employee['id'],
                    'leave_type_id' => (int)$input['leave_type_id'],
                    'start_date' => $input['start_date'],
                    'end_date' => $input['end_date'],
                    'is_half_day' => !empty($input['is_half_day']),
                    'half_day_type' => $input['half_day_type'] ?? null,
                    'reason' => $input['reason'] ?? null
                ]);
                echo json_encode(['success' => true, 'request_id' => $requestId, 'message' => 'Leave request submitted successfully']);
            } catch (\Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;
            
        case 'cancel-leave-request':
            requireAuth();
            $employee = $api->getEmployeeByUserId($user['id']);
            if (!$employee) {
                echo json_encode(['success' => false, 'error' => 'Employee not found']);
                break;
            }
            
            if (empty($input['request_id'])) {
                echo json_encode(['success' => false, 'error' => 'Request ID is required']);
                break;
            }
            
            $leaveService = new \App\Leave($db);
            try {
                $request = $leaveService->getRequest((int)$input['request_id']);
                if (!$request || $request['employee_id'] != $employee['id']) {
                    echo json_encode(['success' => false, 'error' => 'Request not found or not authorized']);
                    break;
                }
                $leaveService->cancel((int)$input['request_id']);
                echo json_encode(['success' => true, 'message' => 'Leave request cancelled']);
            } catch (\Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;
            
        case 'salary-advances':
            requireAuth();
            $employee = $api->getEmployeeByUserId($user['id']);
            if (!$employee) {
                echo json_encode(['success' => false, 'error' => 'Employee not found']);
                break;
            }
            
            $advanceService = new \App\SalaryAdvance($db);
            $advances = $advanceService->getByEmployee($employee['id']);
            $outstanding = $advanceService->getEmployeeTotalOutstanding($employee['id']);
            echo json_encode(['success' => true, 'data' => $advances, 'total_outstanding' => $outstanding]);
            break;
            
        case 'request-advance':
            requireAuth();
            $employee = $api->getEmployeeByUserId($user['id']);
            if (!$employee) {
                echo json_encode(['success' => false, 'error' => 'Employee not found']);
                break;
            }
            
            if (empty($input['amount']) || $input['amount'] <= 0) {
                echo json_encode(['success' => false, 'error' => 'Valid amount is required']);
                break;
            }
            
            $advanceService = new \App\SalaryAdvance($db);
            try {
                $advanceId = $advanceService->create([
                    'employee_id' => $employee['id'],
                    'amount' => (float)$input['amount'],
                    'reason' => $input['reason'] ?? null,
                    'repayment_type' => $input['repayment_type'] ?? 'monthly',
                    'repayment_installments' => $input['repayment_installments'] ?? 1
                ]);
                echo json_encode(['success' => true, 'advance_id' => $advanceId, 'message' => 'Advance request submitted for approval']);
            } catch (\Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
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
            
        case 'available-tickets':
            requireAuth();
            $allowedRoles = ['admin', 'technician', 'manager'];
            if (!in_array($user['role'] ?? '', $allowedRoles)) {
                echo json_encode(['success' => false, 'error' => 'Not authorized to view available tickets']);
                break;
            }
            $tickets = $api->getAvailableTickets();
            echo json_encode(['success' => true, 'data' => $tickets]);
            break;
            
        case 'claim-ticket':
            requireAuth();
            $allowedRoles = ['admin', 'technician', 'manager'];
            if (!in_array($user['role'] ?? '', $allowedRoles)) {
                echo json_encode(['success' => false, 'error' => 'Not authorized to claim tickets']);
                break;
            }
            
            $ticketId = (int) ($input['ticket_id'] ?? 0);
            if (!$ticketId) {
                echo json_encode(['success' => false, 'error' => 'Ticket ID required']);
                break;
            }
            
            $result = $api->claimTicket($ticketId, $user['id']);
            if ($result['success']) {
                echo json_encode(['success' => true, 'message' => 'Ticket claimed successfully']);
            } else {
                echo json_encode(['success' => false, 'error' => $result['error'] ?? 'Failed to claim ticket']);
            }
            break;
            
        case 'technician-equipment':
            requireAuth();
            $allowedRoles = ['admin', 'technician', 'manager'];
            if (!in_array($user['role'] ?? '', $allowedRoles)) {
                echo json_encode(['success' => false, 'error' => 'Not authorized to view equipment']);
                break;
            }
            $equipment = $api->getTechnicianEquipment($user['id']);
            echo json_encode(['success' => true, 'data' => $equipment]);
            break;
            
        case 'ticket-detail-any':
            requireAuth();
            $allowedRoles = ['admin', 'technician', 'manager'];
            $userRole = $user['role'] ?? '';
            if (!in_array($userRole, $allowedRoles)) {
                echo json_encode(['success' => false, 'error' => 'Not authorized to view this ticket']);
                break;
            }
            
            $ticketId = (int) ($_GET['id'] ?? 0);
            if (!$ticketId) {
                echo json_encode(['success' => false, 'error' => 'Ticket ID required']);
                break;
            }
            
            $ticket = $api->getTicketDetailsAny($ticketId, $user['id'], $userRole);
            if ($ticket) {
                echo json_encode(['success' => true, 'data' => $ticket]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Ticket not found or not authorized']);
            }
            break;
            
        case 'update-ticket-any':
            requireAuth();
            $allowedRoles = ['admin', 'technician', 'manager'];
            $userRole = $user['role'] ?? '';
            if (!in_array($userRole, $allowedRoles)) {
                echo json_encode(['success' => false, 'error' => 'Not authorized to update tickets']);
                break;
            }
            
            $ticketId = (int) ($input['ticket_id'] ?? 0);
            $status = $input['status'] ?? '';
            $comment = $input['comment'] ?? null;
            
            if (!$ticketId || !$status) {
                echo json_encode(['success' => false, 'error' => 'Ticket ID and status required']);
                break;
            }
            
            $result = $api->updateTicketStatusAny($ticketId, $user['id'], $userRole, $status, $comment);
            if ($result['success']) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => $result['error'] ?? 'Failed to update ticket']);
            }
            break;
            
        case 'add-comment-any':
            requireAuth();
            $allowedRoles = ['admin', 'technician', 'manager'];
            $userRole = $user['role'] ?? '';
            if (!in_array($userRole, $allowedRoles)) {
                echo json_encode(['success' => false, 'error' => 'Not authorized to add comments']);
                break;
            }
            
            $ticketId = (int) ($input['ticket_id'] ?? 0);
            $comment = $input['comment'] ?? '';
            
            if (!$ticketId || !$comment) {
                echo json_encode(['success' => false, 'error' => 'Ticket ID and comment required']);
                break;
            }
            
            $result = $api->addTicketCommentAny($ticketId, $user['id'], $userRole, $comment);
            if ($result['success']) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => $result['error'] ?? 'Failed to add comment']);
            }
            break;
            
        case 'close-ticket':
            requireAuth();
            $allowedRoles = ['admin', 'technician', 'manager'];
            $userRole = $user['role'] ?? '';
            if (!in_array($userRole, $allowedRoles)) {
                echo json_encode(['success' => false, 'error' => 'Not authorized']);
                break;
            }
            
            $ticketId = (int) ($input['ticket_id'] ?? 0);
            if (!$ticketId) {
                echo json_encode(['success' => false, 'error' => 'Ticket ID required']);
                break;
            }
            
            $closureDetails = [
                'cable_meters' => $input['cable_meters'] ?? null,
                'router_model' => $input['router_model'] ?? null,
                'router_serial' => $input['router_serial'] ?? null,
                'equipment_id' => $input['equipment_id'] ?? null,
                'comment' => $input['comment'] ?? ''
            ];
            
            $result = $api->closeTicketWithDetails($ticketId, $user['id'], $closureDetails, $userRole);
            if ($result['success']) {
                echo json_encode(['success' => true, 'message' => 'Ticket closed successfully']);
            } else {
                echo json_encode(['success' => false, 'error' => $result['error'] ?? 'Failed to close ticket']);
            }
            break;
            
        case 'available-equipment':
            requireAuth();
            $allowedRoles = ['admin', 'technician', 'manager'];
            if (!in_array($user['role'] ?? '', $allowedRoles)) {
                echo json_encode(['success' => false, 'error' => 'Not authorized']);
                break;
            }
            
            $ticketId = (int) ($_GET['ticket_id'] ?? 0);
            $equipment = $api->getAvailableEquipmentForTicket($ticketId);
            echo json_encode(['success' => true, 'data' => $equipment]);
            break;
            
        case 'my-teams':
            requireAuth();
            $teams = $api->getEmployeeTeams($user['id']);
            echo json_encode(['success' => true, 'data' => $teams]);
            break;
            
        case 'team-details':
            requireAuth();
            $teamId = (int) ($_GET['team_id'] ?? 0);
            if (!$teamId) {
                echo json_encode(['success' => false, 'error' => 'Team ID required']);
                break;
            }
            $team = $api->getTeamDetails($teamId, $user['id']);
            if ($team) {
                echo json_encode(['success' => true, 'data' => $team]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Team not found or not a member']);
            }
            break;
            
        case 'team-tickets':
            requireAuth();
            $teamId = (int) ($_GET['team_id'] ?? 0);
            if (!$teamId) {
                echo json_encode(['success' => false, 'error' => 'Team ID required']);
                break;
            }
            $status = $_GET['status'] ?? '';
            $limit = (int) ($_GET['limit'] ?? 50);
            $tickets = $api->getTeamTickets($teamId, $user['id'], $status, $limit);
            echo json_encode(['success' => true, 'data' => $tickets]);
            break;
            
        case 'my-earnings':
            requireAuth();
            $month = $_GET['month'] ?? date('Y-m');
            $data = $api->getEmployeeEarnings($user['id'], $month);
            if (isset($data['error'])) {
                echo json_encode(['success' => false, 'error' => $data['error']]);
            } else {
                echo json_encode(['success' => true, 'data' => $data]);
            }
            break;
            
        case 'team-earnings':
            requireAuth();
            $teamId = (int) ($_GET['team_id'] ?? 0);
            if (!$teamId) {
                echo json_encode(['success' => false, 'error' => 'Team ID required']);
                break;
            }
            $month = $_GET['month'] ?? date('Y-m');
            $data = $api->getTeamEarnings($teamId, $user['id'], $month);
            if (isset($data['error'])) {
                echo json_encode(['success' => false, 'error' => $data['error']]);
            } else {
                echo json_encode(['success' => true, 'data' => $data]);
            }
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
            
        case 'customer-detail':
            requireAuth();
            $customerId = (int) ($_GET['id'] ?? 0);
            if (!$customerId) {
                echo json_encode(['success' => false, 'error' => 'Customer ID required']);
                break;
            }
            $customer = $api->getCustomerDetail($customerId);
            if ($customer) {
                echo json_encode(['success' => true, 'data' => $customer]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Customer not found']);
            }
            break;
            
        case 'notifications':
            requireAuth();
            $notifications = $api->getUserNotifications($user['id']);
            echo json_encode(['success' => true, 'data' => $notifications]);
            break;
            
        case 'mark-notification-read':
            requireAuth();
            $notificationId = (int) ($input['notification_id'] ?? 0);
            $api->markNotificationRead($notificationId, $user['id']);
            echo json_encode(['success' => true]);
            break;
            
        case 'mark-all-notifications-read':
            requireAuth();
            $api->markAllNotificationsRead($user['id']);
            echo json_encode(['success' => true]);
            break;
            
        case 'attendance-history':
            requireAuth();
            $employee = $api->getEmployeeByUserId($user['id']);
            if ($employee) {
                $history = $api->getRecentAttendance($employee['id'], 30);
                echo json_encode(['success' => true, 'data' => $history]);
            } else {
                echo json_encode(['success' => true, 'data' => []]);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
    }
} catch (Exception $e) {
    error_log('Mobile API Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
