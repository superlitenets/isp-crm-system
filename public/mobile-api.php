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

$db = Database::getConnection();

try {
    $stmt = $db->prepare("SELECT setting_value FROM company_settings WHERE setting_key = 'timezone'");
    $stmt->execute();
    $tz = $stmt->fetchColumn();
    if ($tz && in_array($tz, timezone_identifiers_list())) {
        date_default_timezone_set($tz);
    }
} catch (\Exception $e) {}

$api = new \App\MobileAPI();

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

$input = [];
if ($method === 'POST') {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'multipart/form-data') !== false) {
        $input = $_POST;
    } else {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
    }
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
            try {
                $employee = $api->getEmployeeByUserId($user['id'], true);
                if (!$employee) {
                    echo json_encode(['success' => false, 'error' => 'Could not create employee record']);
                    break;
                }
                
                $leaveService = new \App\Leave($db);
                $balance = $leaveService->getEmployeeBalance($employee['id']);
                echo json_encode(['success' => true, 'data' => $balance]);
            } catch (\Exception $e) {
                echo json_encode(['success' => false, 'error' => 'Failed to load leave balance: ' . $e->getMessage()]);
            }
            break;
            
        case 'leave-types':
            requireAuth();
            try {
                $leaveService = new \App\Leave($db);
                $types = $leaveService->getLeaveTypes();
                echo json_encode(['success' => true, 'data' => $types]);
            } catch (\Exception $e) {
                echo json_encode(['success' => false, 'error' => 'Failed to load leave types: ' . $e->getMessage()]);
            }
            break;
            
        case 'leave-requests':
            requireAuth();
            try {
                $employee = $api->getEmployeeByUserId($user['id'], true);
                if (!$employee) {
                    echo json_encode(['success' => false, 'error' => 'Could not create employee record']);
                    break;
                }
                
                $leaveService = new \App\Leave($db);
                $requests = $leaveService->getEmployeeRequests($employee['id']);
                echo json_encode(['success' => true, 'data' => $requests]);
            } catch (\Exception $e) {
                echo json_encode(['success' => false, 'error' => 'Failed to load leave requests: ' . $e->getMessage()]);
            }
            break;
            
        case 'submit-leave-request':
            requireAuth();
            $employee = $api->getEmployeeByUserId($user['id'], true);
            if (!$employee) {
                echo json_encode(['success' => false, 'error' => 'Could not create employee record']);
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
            $employee = $api->getEmployeeByUserId($user['id'], true);
            if (!$employee) {
                echo json_encode(['success' => false, 'error' => 'Could not create employee record']);
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
            try {
                $employee = $api->getEmployeeByUserId($user['id'], true);
                if (!$employee) {
                    echo json_encode(['success' => false, 'error' => 'Could not create employee record']);
                    break;
                }
                
                $advanceService = new \App\SalaryAdvance($db);
                $advances = $advanceService->getByEmployee($employee['id']);
                $outstanding = $advanceService->getEmployeeTotalOutstanding($employee['id']);
                echo json_encode(['success' => true, 'data' => $advances, 'total_outstanding' => $outstanding]);
            } catch (\Exception $e) {
                echo json_encode(['success' => false, 'error' => 'Failed to load salary advances: ' . $e->getMessage()]);
            }
            break;
            
        case 'request-advance':
            requireAuth();
            $employee = $api->getEmployeeByUserId($user['id'], true);
            if (!$employee) {
                echo json_encode(['success' => false, 'error' => 'Could not create employee record']);
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
            $allowedRoles = ['admin', 'technician', 'manager', 'support_staff'];
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
                if (!empty($input['service_fees']) && is_array($input['service_fees'])) {
                    $serviceFeeModel = new \App\ServiceFee($db);
                    foreach ($input['service_fees'] as $feeData) {
                        $feeTypeId = (int)($feeData['fee_type_id'] ?? 0);
                        $amount = (float)($feeData['amount'] ?? 0);
                        
                        if ($feeTypeId > 0) {
                            $feeType = $serviceFeeModel->getFeeType($feeTypeId);
                            if ($feeType) {
                                $serviceFeeModel->addTicketFee($ticketId, [
                                    'fee_type_id' => $feeTypeId,
                                    'fee_name' => $feeType['name'],
                                    'amount' => $amount > 0 ? $amount : $feeType['default_amount'],
                                    'currency' => $feeType['currency'] ?? 'KES',
                                    'created_by' => $user['id']
                                ]);
                            }
                        }
                    }
                }
                echo json_encode(['success' => true, 'ticket_id' => $ticketId]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to create ticket']);
            }
            break;
        
        case 'service-fees':
            requireAuth();
            $serviceFeeModel = new \App\ServiceFee($db);
            $fees = $serviceFeeModel->getFeeTypes(true);
            echo json_encode(['success' => true, 'data' => $fees]);
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
            $allowedRoles = ['admin', 'technician', 'manager', 'support_staff', 'salesperson'];
            if (!in_array($user['role'] ?? '', $allowedRoles)) {
                echo json_encode(['success' => false, 'error' => 'Not authorized to view available tickets']);
                break;
            }
            $tickets = $api->getAvailableTickets($user['id']);
            echo json_encode(['success' => true, 'data' => $tickets]);
            break;
            
        case 'claim-ticket':
            requireAuth();
            $allowedRoles = ['admin', 'technician', 'manager', 'support_staff', 'salesperson'];
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
            $allowedRoles = ['admin', 'technician', 'manager', 'support_staff', 'salesperson'];
            if (!in_array($user['role'] ?? '', $allowedRoles)) {
                echo json_encode(['success' => false, 'error' => 'Not authorized to view equipment']);
                break;
            }
            $equipment = $api->getTechnicianEquipment($user['id']);
            echo json_encode(['success' => true, 'data' => $equipment]);
            break;
            
        case 'ticket-detail-any':
            requireAuth();
            $allowedRoles = ['admin', 'technician', 'manager', 'support_staff', 'salesperson'];
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
            $allowedRoles = ['admin', 'technician', 'manager', 'support_staff', 'salesperson'];
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
            
        case 'resolve-ticket':
            requireAuth();
            $allowedRoles = ['admin', 'technician', 'manager', 'support_staff', 'salesperson'];
            $userRole = $user['role'] ?? '';
            if (!in_array($userRole, $allowedRoles)) {
                echo json_encode(['success' => false, 'error' => 'Not authorized to resolve tickets']);
                break;
            }
            
            $ticketId = (int) ($input['ticket_id'] ?? 0);
            $resolutionNotes = trim($input['resolution_notes'] ?? '');
            
            if (!$ticketId || !$resolutionNotes) {
                echo json_encode(['success' => false, 'error' => 'Ticket ID and resolution notes are required']);
                break;
            }
            
            if (!$api->isClockedIn($user['id'])) {
                echo json_encode(['success' => false, 'error' => 'You must clock in before resolving tickets']);
                break;
            }
            
            if (!$api->canModifyTicket($ticketId, $user['id'], $userRole)) {
                echo json_encode(['success' => false, 'error' => 'You do not have permission to resolve this ticket']);
                break;
            }
            
            try {
                $pdo = $db;
                $pdo->beginTransaction();
                
                $stmt = $pdo->prepare("
                    INSERT INTO ticket_resolutions 
                    (ticket_id, resolved_by, resolution_notes, router_serial, power_levels, cable_used, equipment_installed, additional_notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ON CONFLICT (ticket_id) DO UPDATE SET
                        resolution_notes = EXCLUDED.resolution_notes,
                        router_serial = EXCLUDED.router_serial,
                        power_levels = EXCLUDED.power_levels,
                        cable_used = EXCLUDED.cable_used,
                        equipment_installed = EXCLUDED.equipment_installed,
                        additional_notes = EXCLUDED.additional_notes,
                        updated_at = CURRENT_TIMESTAMP
                    RETURNING id
                ");
                $stmt->execute([
                    $ticketId,
                    $user['id'],
                    $resolutionNotes,
                    trim($input['router_serial'] ?? ''),
                    trim($input['power_levels'] ?? ''),
                    trim($input['cable_used'] ?? ''),
                    trim($input['equipment_installed'] ?? ''),
                    trim($input['additional_notes'] ?? '')
                ]);
                $resolutionId = $stmt->fetchColumn();
                
                $uploadDir = __DIR__ . '/uploads/ticket_resolutions/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $photoTypes = ['photo_serial' => 'serial', 'photo_power' => 'power_levels', 'photo_cables' => 'cables', 'photo_additional' => 'additional'];
                $maxFileSize = 10 * 1024 * 1024;
                $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                
                foreach ($photoTypes as $fieldName => $photoType) {
                    if (!empty($_FILES[$fieldName]['name']) && $_FILES[$fieldName]['error'] === UPLOAD_ERR_OK) {
                        $tmpFile = $_FILES[$fieldName]['tmp_name'];
                        $fileSize = $_FILES[$fieldName]['size'];
                        $ext = strtolower(pathinfo($_FILES[$fieldName]['name'], PATHINFO_EXTENSION));
                        
                        if ($fileSize > $maxFileSize || !in_array($ext, $allowedExts)) {
                            continue;
                        }
                        
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $mimeType = finfo_file($finfo, $tmpFile);
                        finfo_close($finfo);
                        
                        if (!in_array($mimeType, $allowedMimes)) {
                            continue;
                        }
                        
                        $safeExt = match($mimeType) {
                            'image/jpeg' => 'jpg',
                            'image/png' => 'png',
                            'image/gif' => 'gif',
                            'image/webp' => 'webp',
                            default => 'jpg'
                        };
                        
                        $fileName = 'ticket_' . $ticketId . '_' . $photoType . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $safeExt;
                        $filePath = 'uploads/ticket_resolutions/' . $fileName;
                        
                        if (move_uploaded_file($tmpFile, $uploadDir . $fileName)) {
                            $stmt = $pdo->prepare("
                                INSERT INTO ticket_resolution_photos 
                                (ticket_id, resolution_id, photo_type, file_path, file_name, uploaded_by)
                                VALUES (?, ?, ?, ?, ?, ?)
                            ");
                            $stmt->execute([$ticketId, $resolutionId, $photoType, $filePath, $_FILES[$fieldName]['name'], $user['id']]);
                        }
                    }
                }
                
                $ticketModel = new \App\Ticket();
                $ticketModel->quickStatusChange($ticketId, 'resolved', $user['id']);
                
                $pdo->commit();
                
                $ticketData = $ticketModel->find($ticketId);
                if ($ticketData && !empty($ticketData['customer_id'])) {
                    try {
                        $api->sendStatusNotification($ticketData, 'resolved');
                    } catch (\Exception $e) {}
                }
                
                echo json_encode(['success' => true, 'message' => 'Ticket resolved successfully']);
            } catch (Exception $e) {
                if (isset($pdo) && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                echo json_encode(['success' => false, 'error' => 'Error resolving ticket: ' . $e->getMessage()]);
            }
            break;
            
        case 'add-comment-any':
            requireAuth();
            $allowedRoles = ['admin', 'technician', 'manager', 'support_staff', 'salesperson'];
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
            $allowedRoles = ['admin', 'technician', 'manager', 'support_staff', 'salesperson'];
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
            $allowedRoles = ['admin', 'technician', 'manager', 'support_staff', 'salesperson'];
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
