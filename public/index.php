<?php

date_default_timezone_set('Africa/Nairobi');

error_reporting(E_ALL);
ini_set('display_errors', 0);

function applyTimezoneFromSettings(): void {
    try {
        $db = \Database::getConnection();
        $stmt = $db->prepare("SELECT setting_value FROM company_settings WHERE setting_key = 'timezone'");
        $stmt->execute();
        $tz = $stmt->fetchColumn();
        if ($tz && in_array($tz, timezone_identifiers_list())) {
            date_default_timezone_set($tz);
        }
    } catch (\Exception $e) {
    }
}

$isReplit = !empty(getenv('REPLIT_DEV_DOMAIN'));
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
           (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');

if ($isReplit) {
    ini_set('session.cookie_secure', '1');
    ini_set('session.cookie_samesite', 'None');
    session_set_cookie_params([
        'lifetime' => 86400,
        'path' => '/',
        'domain' => '',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'None'
    ]);
} else {
    ini_set('session.cookie_secure', $isHttps ? '1' : '0');
    ini_set('session.cookie_samesite', 'Lax');
    session_set_cookie_params([
        'lifetime' => 86400,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
    
    if ($isReplit) {
        $sessionId = session_id();
        $cookieName = session_name();
        $expires = gmdate('D, d M Y H:i:s T', time() + 86400);
        header("Set-Cookie: {$cookieName}={$sessionId}; Path=/; Expires={$expires}; Secure; HttpOnly; SameSite=None; Partitioned", false);
    }
}

ob_start();

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/init_db.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Customer.php';
require_once __DIR__ . '/../src/Ticket.php';
require_once __DIR__ . '/../src/SMS.php';
require_once __DIR__ . '/../src/SMSGateway.php';
require_once __DIR__ . '/../src/Employee.php';
require_once __DIR__ . '/../src/Settings.php';
require_once __DIR__ . '/../src/WhatsApp.php';
require_once __DIR__ . '/../src/TemplateEngine.php';
require_once __DIR__ . '/../src/BiometricDevice.php';
require_once __DIR__ . '/../src/ZKTecoDevice.php';
require_once __DIR__ . '/../src/HikvisionDevice.php';
require_once __DIR__ . '/../src/BiometricSyncService.php';
require_once __DIR__ . '/../src/LateDeductionCalculator.php';
require_once __DIR__ . '/../src/Salesperson.php';
require_once __DIR__ . '/../src/Role.php';
require_once __DIR__ . '/../src/SLA.php';
require_once __DIR__ . '/../src/Order.php';
require_once __DIR__ . '/../src/Mpesa.php';
require_once __DIR__ . '/../src/Complaint.php';
require_once __DIR__ . '/../src/ActivityLog.php';
require_once __DIR__ . '/../src/Reports.php';

// Skip schema initialization in production (run via deployment script instead)
// Set SKIP_DB_INIT=1 in production environment
if (!getenv('SKIP_DB_INIT')) {
    initializeDatabase();
}

$db = Database::getConnection();

applyTimezoneFromSettings();

\App\Auth::init();

if (getenv('REPLIT_DEV_DOMAIN') && !\App\Auth::isLoggedIn()) {
    $adminUser = $db->query("SELECT * FROM users WHERE role = 'admin' OR email = 'admin@isp.com' LIMIT 1")->fetch();
    if ($adminUser) {
        $_SESSION['user_id'] = $adminUser['id'];
        $_SESSION['user_name'] = $adminUser['name'];
        $_SESSION['user_role'] = $adminUser['role'] ?? 'admin';
        $_SESSION['user_role_id'] = $adminUser['role_id'] ?? null;
        $_SESSION['user_email'] = $adminUser['email'];
        $_SESSION['permissions'] = [];
    }
}

$page = $_GET['page'] ?? 'dashboard';
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

// Ticket Wallboard - renders standalone without main layout
if ($page === 'ticket-wallboard') {
    include __DIR__ . '/../templates/ticket_wallboard.php';
    exit;
}

if ($page === 'logout') {
    \App\Auth::logout();
    header('Location: ?page=login');
    exit;
}

if ($page === 'download' && $action === 'zip') {
    $file = __DIR__ . '/isp-crm-complete.zip';
    if (file_exists($file)) {
        ob_end_clean();
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="isp-crm-complete.zip"');
        header('Content-Length: ' . filesize($file));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: public');
        readfile($file);
        exit;
    }
    http_response_code(404);
    echo 'File not found';
    exit;
}

if ($page === 'download' && $action === 'docker') {
    $file = __DIR__ . '/isp-crm-docker.zip';
    if (file_exists($file)) {
        ob_end_clean();
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="isp-crm-docker.zip"');
        header('Content-Length: ' . filesize($file));
        header('Content-Transfer-Encoding: binary');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Accept-Ranges: bytes');
        flush();
        readfile($file);
        exit;
    }
    http_response_code(404);
    echo 'File not found';
    exit;
}

if ($page === 'settings' && isset($_GET['subpage']) && $_GET['subpage'] === 'backup' && isset($_GET['action']) && $_GET['action'] === 'download_backup') {
    if (!\App\Auth::isLoggedIn() || !\App\Auth::isAdmin()) {
        http_response_code(403);
        echo 'Access denied';
        exit;
    }
    
    $filename = basename($_GET['file'] ?? '');
    if (empty($filename) || !preg_match('/^backup_.*\.sql$/', $filename)) {
        http_response_code(400);
        echo 'Invalid filename';
        exit;
    }
    
    $filepath = __DIR__ . '/../backups/' . $filename;
    if (!file_exists($filepath)) {
        http_response_code(404);
        echo 'Backup file not found';
        exit;
    }
    
    ob_end_clean();
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($filepath));
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    readfile($filepath);
    exit;
}


// Recent Activity Feed API
if ($page === 'api' && $action === 'recent_activity') {
    ob_clean();
    header('Content-Type: application/json');
    
    if (!\App\Auth::isLoggedIn()) {
        echo json_encode(['success' => false, 'activities' => []]);
        exit;
    }
    
    $limit = min(50, max(10, (int)($_GET['limit'] ?? 20)));
    
    try {
        $stmt = $db->prepare("
            SELECT al.id, al.activity_type, al.description, al.created_at, u.name as user_name
            FROM activity_log al
            LEFT JOIN users u ON al.user_id = u.id
            ORDER BY al.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'activities' => $activities]);
    } catch (Exception $e) {
        // Try fallback if activity_log doesn't exist
        echo json_encode(['success' => true, 'activities' => []]);
    }
    exit;
}

if ($page === 'api' && $action === 'late_deductions') {
    header('Content-Type: application/json');
    
    if (!\App\Auth::isLoggedIn()) {
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    $employeeId = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;
    $month = $_GET['month'] ?? date('Y-m');
    
    if (!$employeeId) {
        echo json_encode(['error' => 'Employee ID required']);
        exit;
    }
    
    try {
        $apiDb = Database::getConnection();
        $lateCalculator = new \App\LateDeductionCalculator($apiDb);
        $deductions = $lateCalculator->calculateMonthlyDeductions($employeeId, $month);
        echo json_encode($deductions);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

if ($page === 'api' && $action === 'test_biometric_device') {
    ob_clean();
    header('Content-Type: application/json');
    
    if (!\App\Auth::isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        exit;
    }
    
    if (!\App\Auth::isAdmin()) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized - Admin access required']);
        exit;
    }
    
    $deviceId = isset($_GET['device_id']) ? (int)$_GET['device_id'] : 0;
    
    if (!$deviceId) {
        echo json_encode(['success' => false, 'message' => 'Device ID required']);
        exit;
    }
    
    try {
        if (!function_exists('socket_create')) {
            echo json_encode(['success' => false, 'message' => 'PHP sockets extension not enabled. Please enable php-sockets in php.ini or rebuild Docker with sockets extension.']);
            exit;
        }
        
        $biometricService = new \App\BiometricSyncService($db);
        $result = $biometricService->testDevice($deviceId);
        echo json_encode($result);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

if ($page === 'api' && $action === 'sync_biometric_device') {
    ob_clean();
    header('Content-Type: application/json');
    
    if (!\App\Auth::isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        exit;
    }
    
    if (!\App\Auth::isAdmin()) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized - Admin access required']);
        exit;
    }
    
    $deviceId = isset($_GET['device_id']) ? (int)$_GET['device_id'] : 0;
    $debug = isset($_GET['debug']) && $_GET['debug'] === '1';
    
    if (!$deviceId) {
        echo json_encode(['success' => false, 'message' => 'Device ID required']);
        exit;
    }
    
    try {
        if (!function_exists('socket_create')) {
            echo json_encode(['success' => false, 'message' => 'PHP sockets extension not enabled']);
            exit;
        }
        
        $biometricService = new \App\BiometricSyncService($db);
        $result = $biometricService->syncDevice($deviceId, null, $debug);
        echo json_encode($result);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

if ($page === 'api' && $action === 'fetch_biometric_users') {
    ob_clean();
    header('Content-Type: application/json');
    
    if (!\App\Auth::isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        exit;
    }
    
    if (!\App\Auth::isAdmin()) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized - Admin access required']);
        exit;
    }
    
    $deviceId = isset($_GET['device_id']) ? (int)$_GET['device_id'] : 0;
    $debug = isset($_GET['debug']) && $_GET['debug'] === '1';
    
    if (!$deviceId) {
        echo json_encode(['success' => false, 'message' => 'Device ID required']);
        exit;
    }
    
    try {
        if (!function_exists('socket_create')) {
            echo json_encode(['success' => false, 'message' => 'PHP sockets extension not enabled']);
            exit;
        }
        
        $biometricService = new \App\BiometricSyncService($db);
        
        if ($debug) {
            $result = $biometricService->getDeviceUsersWithDebug($deviceId);
            echo json_encode([
                'success' => true, 
                'users' => $result['users'], 
                'count' => count($result['users']),
                'debug' => $result['debug']
            ]);
        } else {
            $users = $biometricService->getDeviceUsers($deviceId);
            echo json_encode(['success' => true, 'users' => $users, 'count' => count($users)]);
        }
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

if ($page === 'api' && $action === 'repost_single_ticket') {
    ob_clean();
    header('Content-Type: application/json');
    
    if (!\App\Auth::isLoggedIn()) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    
    $ticketId = isset($_GET['ticket_id']) ? (int)$_GET['ticket_id'] : 0;
    
    if (!$ticketId) {
        echo json_encode(['success' => false, 'error' => 'Ticket ID required']);
        exit;
    }
    
    try {
        $ticketModel = new \App\Ticket();
        $ticketData = $ticketModel->find($ticketId);
        
        if (!$ticketData) {
            echo json_encode(['success' => false, 'error' => 'Ticket not found']);
            exit;
        }
        
        $settings = new \App\Settings();
        $whatsapp = new \App\WhatsApp($db);
        
        $customer = new \App\Customer();
        $customerData = $ticketData['customer_id'] ? $customer->find($ticketData['customer_id']) : null;
        
        $statusLink = '';
        try {
            require_once __DIR__ . '/../src/TicketStatusLink.php';
            $statusLinkService = new \TicketStatusLink($db);
            $statusLink = $statusLinkService->generateStatusUpdateUrl($ticketId, $ticketData['assigned_to'] ?? null);
        } catch (Throwable $e) {
            error_log("Failed to generate status link for repost: " . $e->getMessage());
        }
        
        if (!empty($ticketData['assigned_to'])) {
            $assignedUser = $ticketModel->getUser($ticketData['assigned_to']);
            $ticketData['assigned_to_name'] = $assignedUser['name'] ?? 'Unknown';
        }
        
        $serviceFee = new \App\ServiceFee($db);
        $ticketData['service_fees'] = $serviceFee->getTicketFees($ticketId);
        
        $message = $whatsapp->formatTicketAssignmentMessage($ticketData, $customerData, $settings, $statusLink);
        
        $groupsSent = 0;
        $errors = [];
        
        if (!empty($ticketData['branch_id'])) {
            $branchModel = new \App\Branch();
            $branch = $branchModel->find($ticketData['branch_id']);
            if ($branch && !empty($branch['whatsapp_group_id'])) {
                $result = $whatsapp->sendToGroup($branch['whatsapp_group_id'], $message);
                if ($result['success']) {
                    $groupsSent++;
                } else {
                    $errors[] = "Branch group: " . ($result['error'] ?? 'Unknown error');
                }
            }
        }
        
        $operationsGroupId = $settings->get('whatsapp_operations_group_id');
        if (!empty($operationsGroupId)) {
            $result = $whatsapp->sendToGroup($operationsGroupId, $message);
            if ($result['success']) {
                $groupsSent++;
            } else {
                $errors[] = "Operations group: " . ($result['error'] ?? 'Unknown error');
            }
        }
        
        echo json_encode([
            'success' => count($errors) === 0,
            'groups_sent' => $groupsSent,
            'ticket_number' => $ticketData['ticket_number'],
            'errors' => $errors
        ]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

if ($page === 'api' && $action === 'branch_employees') {
    ob_clean();
    header('Content-Type: application/json');
    
    if (!\App\Auth::isLoggedIn()) {
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    $branchId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if (!$branchId) {
        echo json_encode(['employees' => []]);
        exit;
    }
    
    try {
        $branchModel = new \App\Branch();
        $branchEmployees = $branchModel->getEmployees($branchId);
        $employeeIds = array_column($branchEmployees, 'id');
        echo json_encode(['employees' => $employeeIds]);
    } catch (Throwable $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

if ($page === 'api' && $action === 'smartolt_stats') {
    ob_clean();
    header('Content-Type: application/json');
    
    if (!\App\Auth::isLoggedIn()) {
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit;
    }
    
    try {
        $smartolt = new \App\SmartOLT($db);
        $forceRefresh = isset($_GET['refresh']);
        
        if ($forceRefresh) {
            \App\SmartOLT::clearCache();
        }
        
        $stats = $smartolt->getDashboardStats();
        echo json_encode(['success' => true, 'stats' => $stats]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

if ($page === 'api' && $action === 'smartolt_onu_action') {
    ob_clean();
    header('Content-Type: application/json');
    
    if (!\App\Auth::isLoggedIn()) {
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit;
    }
    
    if (!\App\Auth::isAdmin()) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized - Admin access required']);
        exit;
    }
    
    $onuAction = $_POST['onu_action'] ?? '';
    $externalId = $_POST['external_id'] ?? '';
    
    if (empty($onuAction) || empty($externalId)) {
        echo json_encode(['success' => false, 'error' => 'Action and external ID required']);
        exit;
    }
    
    try {
        $smartolt = new \App\SmartOLT($db);
        $result = [];
        
        switch ($onuAction) {
            case 'reboot':
                $result = $smartolt->rebootONU($externalId);
                break;
            case 'resync':
                $result = $smartolt->resyncONUConfig($externalId);
                break;
            case 'enable':
                $result = $smartolt->enableONU($externalId);
                break;
            case 'disable':
                $result = $smartolt->disableONU($externalId);
                break;
            default:
                echo json_encode(['success' => false, 'error' => 'Invalid action']);
                exit;
        }
        
        if ($result['status'] ?? false) {
            echo json_encode(['success' => true, 'message' => ucfirst($onuAction) . ' command sent successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => $result['error'] ?? 'Action failed']);
        }
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

if ($page === 'api' && $action === 'smartolt_authorize_onu') {
    ob_clean();
    header('Content-Type: application/json');
    
    if (!\App\Auth::isLoggedIn()) {
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit;
    }
    
    if (!\App\Auth::isAdmin()) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized - Admin access required']);
        exit;
    }
    
    if (!\App\Auth::validateToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'error' => 'Invalid security token']);
        exit;
    }
    
    $oltId = $_POST['olt_id'] ?? '';
    $ponType = $_POST['pon_type'] ?? 'gpon';
    $board = $_POST['board'] ?? '';
    $port = $_POST['port'] ?? '';
    $sn = trim($_POST['sn'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $onuType = $_POST['onu_type'] ?? '';
    $zone = $_POST['zone'] ?? '';
    $odb = $_POST['odb'] ?? '';
    $vlan = $_POST['vlan'] ?? '';
    $speedProfile = $_POST['speed_profile'] ?? '';
    $onuMode = $_POST['onu_mode'] ?? 'routing';
    $address = trim($_POST['address'] ?? '');
    
    if (empty($oltId) || empty($sn) || empty($name) || empty($onuType)) {
        echo json_encode(['success' => false, 'error' => 'OLT ID, serial number, name, and ONU type are required']);
        exit;
    }
    
    try {
        $smartolt = new \App\SmartOLT($db);
        
        $authData = [
            'olt_id' => $oltId,
            'pon_type' => $ponType,
            'sn' => $sn,
            'onu_type' => $onuType,
            'onu_mode' => $onuMode,
            'onu_external_id' => $name,
            'name' => $name
        ];
        
        if (!empty($board)) $authData['board'] = $board;
        if (!empty($port)) $authData['port'] = $port;
        if (!empty($zone)) $authData['zone'] = $zone;
        if (!empty($odb)) $authData['odb'] = $odb;
        if (!empty($vlan)) $authData['vlan'] = $vlan;
        if (!empty($speedProfile)) $authData['speed_profile'] = $speedProfile;
        if (!empty($address)) $authData['address'] = $address;
        
        $result = $smartolt->authorizeONU($authData);
        
        if ($result['status'] ?? false) {
            echo json_encode(['success' => true, 'message' => 'ONU provisioned successfully', 'data' => $result['response'] ?? null]);
        } else {
            echo json_encode(['success' => false, 'error' => $result['error'] ?? 'Failed to provision ONU']);
        }
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

if ($page === 'api' && $action === 'log_whatsapp') {
    ob_clean();
    header('Content-Type: application/json');
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        exit;
    }
    
    if (!\App\Auth::isLoggedIn()) {
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $ticketId = $input['ticket_id'] ?? null;
    $orderId = $input['order_id'] ?? null;
    $complaintId = $input['complaint_id'] ?? null;
    $messageType = $input['message_type'] ?? 'custom';
    $phone = $input['phone'] ?? '';
    
    try {
        $stmt = $db->prepare("
            INSERT INTO whatsapp_logs (ticket_id, order_id, complaint_id, recipient_phone, recipient_type, message_type, status)
            VALUES (?, ?, ?, ?, 'customer', ?, 'opened')
        ");
        $stmt->execute([$ticketId, $orderId, $complaintId, $phone, $messageType]);
        echo json_encode(['success' => true]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($page === 'api' && $action === 'send_whatsapp') {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        exit;
    }
    
    if (!\App\Auth::isLoggedIn()) {
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit;
    }
    
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['success' => false, 'error' => 'Invalid JSON input: ' . json_last_error_msg()]);
        exit;
    }
    
    $ticketId = $input['ticket_id'] ?? null;
    $orderId = $input['order_id'] ?? null;
    $complaintId = $input['complaint_id'] ?? null;
    $messageType = $input['message_type'] ?? 'custom';
    $phone = $input['phone'] ?? '';
    $message = $input['message'] ?? '';
    
    if (empty($phone) || empty($message)) {
        echo json_encode(['success' => false, 'error' => 'Phone and message are required']);
        exit;
    }
    
    try {
        $whatsapp = new \App\WhatsApp();
        $result = $whatsapp->send($phone, $message);
        
        if ($result['success']) {
            $whatsapp->logMessage($ticketId, $orderId, $complaintId, $phone, 'customer', $message, 'sent', $messageType);
            echo json_encode(['success' => true, 'message' => 'WhatsApp message sent']);
        } else {
            $whatsapp->logMessage($ticketId, $orderId, $complaintId, $phone, 'customer', $message, 'failed', $messageType, $result['error'] ?? 'Unknown error');
            echo json_encode(['success' => false, 'error' => $result['error'] ?? 'Failed to send']);
        }
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($page === 'api' && $action === 'whatsapp_session') {
    ob_clean();
    header('Content-Type: application/json');
    
    if (!\App\Auth::isLoggedIn()) {
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit;
    }
    
    $operation = $_GET['op'] ?? 'status';
    $whatsapp = new \App\WhatsApp();
    
    try {
        switch ($operation) {
            case 'status':
                $result = $whatsapp->getSessionStatus();
                break;
            case 'initialize':
                $result = $whatsapp->initializeSession();
                break;
            case 'qr':
                $result = $whatsapp->getSessionQR();
                break;
            case 'logout':
                $result = $whatsapp->logoutSession();
                break;
            case 'groups':
                $result = $whatsapp->getSessionGroups();
                break;
            case 'send':
                $input = json_decode(file_get_contents('php://input'), true);
                $phone = $input['phone'] ?? '';
                $message = $input['message'] ?? '';
                if (empty($phone) || empty($message)) {
                    $result = ['success' => false, 'error' => 'Phone and message required'];
                } else {
                    $result = $whatsapp->sendViaSession($phone, $message);
                }
                break;
            case 'send-group':
                $input = json_decode(file_get_contents('php://input'), true);
                $groupId = $input['groupId'] ?? '';
                $message = $input['message'] ?? '';
                if (empty($groupId) || empty($message)) {
                    $result = ['success' => false, 'error' => 'Group ID and message required'];
                } else {
                    $result = $whatsapp->sendToGroup($groupId, $message);
                }
                break;
            default:
                $result = ['success' => false, 'error' => 'Unknown operation'];
        }
        echo json_encode($result);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Huawei OLT Live ONU Monitor API
if ($page === 'api' && $action === 'huawei_live_onus') {
    ob_clean();
    header('Content-Type: application/json');
    
    if (!\App\Auth::isLoggedIn()) {
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit;
    }
    
    $oltId = isset($_GET['olt_id']) ? (int)$_GET['olt_id'] : 0;
    $slot = isset($_GET['slot']) ? (int)$_GET['slot'] : null;
    
    if (!$oltId) {
        echo json_encode(['success' => false, 'error' => 'OLT ID required']);
        exit;
    }
    
    try {
        $huaweiOLT = new \App\HuaweiOLT($db);
        $result = $huaweiOLT->getONUDetailedInfo($oltId, $slot);
        echo json_encode($result);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Huawei OLT Single ONU Live Data API
if ($page === 'api' && $action === 'huawei_live_onu') {
    ob_clean();
    header('Content-Type: application/json');
    
    if (!\App\Auth::isLoggedIn()) {
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit;
    }
    
    $oltId = isset($_GET['olt_id']) ? (int)$_GET['olt_id'] : 0;
    $frame = isset($_GET['frame']) ? (int)$_GET['frame'] : 0;
    $slot = isset($_GET['slot']) ? (int)$_GET['slot'] : null;
    $port = isset($_GET['port']) ? (int)$_GET['port'] : null;
    $onuId = isset($_GET['onu_id']) ? (int)$_GET['onu_id'] : null;
    $sn = isset($_GET['sn']) ? $_GET['sn'] : '';
    
    if (!$oltId) {
        echo json_encode(['success' => false, 'error' => 'OLT ID required']);
        exit;
    }
    
    try {
        $huaweiOLT = new \App\HuaweiOLT($db);
        $result = $huaweiOLT->getSingleONULiveData($oltId, $frame, $slot, $port, $onuId, $sn);
        echo json_encode($result);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Get ONU Details API
if ($page === 'api' && $action === 'get_onu_details') {
    ob_clean();
    header('Content-Type: application/json');
    
    if (!\App\Auth::isLoggedIn()) {
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit;
    }
    
    $onuId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if (!$onuId) {
        echo json_encode(['success' => false, 'error' => 'ONU ID required']);
        exit;
    }
    
    try {
        $stmt = $db->prepare("SELECT * FROM huawei_onus WHERE id = ?");
        $stmt->execute([$onuId]);
        $onu = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($onu) {
            echo json_encode(['success' => true, 'onu' => $onu]);
        } else {
            echo json_encode(['success' => false, 'error' => 'ONU not found']);
        }
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Check TR-069 Reachability API
if ($page === 'api' && $action === 'check_tr069_reachability') {
    ob_clean();
    header('Content-Type: application/json');
    
    if (!\App\Auth::isLoggedIn()) {
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit;
    }
    
    $serialNumber = isset($_GET['serial']) ? trim($_GET['serial']) : '';
    
    if (empty($serialNumber)) {
        echo json_encode(['reachable' => false, 'error' => 'Serial number required']);
        exit;
    }
    
    try {
        // First check GenieACS reachability - if device is there and online, allow config
        $genieAcs = new \App\GenieACS($db);
        
        error_log("[TR069 Reachability] Checking serial: {$serialNumber}");
        
        $device = null;
        $searchFormats = [
            $serialNumber,
            strtoupper($serialNumber),
        ];
        
        // Add converted format (HWTCF2D53A8B -> 48575443F2D53A8B)
        $upperSerial = strtoupper($serialNumber);
        if (preg_match('/^[A-Z]{4}[0-9A-F]{8}$/i', $upperSerial)) {
            $searchFormats[] = $genieAcs->convertOltSerialToGenieacs($upperSerial);
        }
        // Also try stripping 4-letter prefix as fallback
        $searchFormats[] = preg_replace('/^[A-Z]{4}/', '', $upperSerial);
        
        foreach ($searchFormats as $sn) {
            if (empty($sn)) continue;
            $result = $genieAcs->getDeviceBySerial($sn);
            if ($result['success'] && !empty($result['device'])) {
                $device = $result['device'];
                break;
            }
        }
        
        // Get ONU from database for reference (use minimal columns for compatibility)
        $stmt = $db->prepare("SELECT id, sn, tr069_status, status FROM huawei_onus WHERE sn = ? OR sn = ?");
        $stmt->execute([$serialNumber, strtoupper($serialNumber)]);
        $onu = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$device) {
            // Device not in GenieACS - check database state for guidance
            if (!$onu) {
                echo json_encode([
                    'reachable' => false,
                    'blocked' => true,
                    'block_reason' => 'ONU not found in database or GenieACS',
                    'last_inform' => 'N/A',
                    'device_id' => null
                ]);
            } else {
                $blockReason = 'Device not connected to GenieACS yet';
                echo json_encode([
                    'reachable' => false,
                    'blocked' => false,
                    'block_reason' => $blockReason,
                    'last_inform' => 'Never connected',
                    'device_id' => null
                ]);
            }
            exit;
        }
        
        // Device found in GenieACS - check if online
        $lastInformStr = $device['_lastInform'] ?? null;
        $isReachable = false;
        $lastInformFormatted = 'Unknown';
        
        if ($lastInformStr) {
            $lastInform = strtotime($lastInformStr);
            $now = time();
            $diff = $now - $lastInform;
            
            $isReachable = ($diff < 300); // Online if informed within 5 minutes
            
            if ($diff < 60) {
                $lastInformFormatted = $diff . ' seconds ago';
            } elseif ($diff < 3600) {
                $lastInformFormatted = round($diff / 60) . ' minutes ago';
            } elseif ($diff < 86400) {
                $lastInformFormatted = round($diff / 3600) . ' hours ago';
            } else {
                $lastInformFormatted = round($diff / 86400) . ' days ago';
            }
        }
        
        // Auto-update database if device is in GenieACS (sync status)
        if ($onu && $isReachable) {
            $stmt = $db->prepare("UPDATE huawei_onus SET tr069_status = 'online' WHERE id = ?");
            $stmt->execute([$onu['id']]);
        }
        
        echo json_encode([
            'reachable' => $isReachable,
            'blocked' => false,
            'last_inform' => $lastInformFormatted,
            'device_id' => $device['_id'] ?? null
        ]);
    } catch (Throwable $e) {
        echo json_encode(['reachable' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Summon and Check TR-069 Reachability - Combined API for faster status button
if ($page === 'api' && $action === 'summon_and_check') {
    ob_clean();
    header('Content-Type: application/json');
    
    if (!\App\Auth::isLoggedIn()) {
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit;
    }
    
    $serialNumber = isset($_GET['serial']) ? trim($_GET['serial']) : '';
    $onuId = isset($_GET['onu_id']) ? (int)$_GET['onu_id'] : 0;
    
    if (empty($serialNumber) && !$onuId) {
        echo json_encode(['success' => false, 'error' => 'Serial or ONU ID required']);
        exit;
    }
    
    try {
        $genieAcs = new \App\GenieACS($db);
        
        // Fast lookup: First check database for cached genieacs_id
        $onu = null;
        if ($onuId) {
            $stmt = $db->prepare("SELECT * FROM huawei_onus WHERE id = ?");
            $stmt->execute([$onuId]);
            $onu = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($onu) $serialNumber = $onu['sn'];
        } elseif ($serialNumber) {
            $stmt = $db->prepare("SELECT * FROM huawei_onus WHERE sn = ? OR sn = ?");
            $stmt->execute([$serialNumber, strtoupper($serialNumber)]);
            $onu = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        // Step 1: Fast device lookup using cached ID first (before summon)
        $device = null;
        $deviceId = null;
        
        // Try cached genieacs_id first (fastest - no HTTP call if we have it)
        if ($onu && !empty($onu['genieacs_id'])) {
            $deviceId = $onu['genieacs_id'];
            $deviceResult = $genieAcs->getDevice($deviceId);
            if ($deviceResult) {
                $device = $deviceResult;
            }
        }
        
        // Fallback to serial search if cached ID didn't work
        if (!$device && $serialNumber) {
            $searchFormats = [$serialNumber, strtoupper($serialNumber)];
            $upperSerial = strtoupper($serialNumber);
            if (preg_match('/^[A-Z]{4}[0-9A-F]{8}$/i', $upperSerial)) {
                $searchFormats[] = $genieAcs->convertOltSerialToGenieacs($upperSerial);
            }
            $searchFormats[] = preg_replace('/^[A-Z]{4}/', '', $upperSerial);
            
            foreach ($searchFormats as $sn) {
                if (empty($sn)) continue;
                $result = $genieAcs->getDeviceBySerial($sn);
                if ($result['success'] && !empty($result['device'])) {
                    $device = $result['device'];
                    $deviceId = $device['_id'] ?? null;
                    // Cache the genieacs_id for faster future lookups
                    if ($onu && $deviceId) {
                        $db->prepare("UPDATE huawei_onus SET genieacs_id = ? WHERE id = ?")->execute([$deviceId, $onu['id']]);
                    }
                    break;
                }
            }
        }
        
        // Step 2: Send instant connection request using device ID (fast, non-blocking)
        $summonResult = ['success' => false, 'queued' => false];
        if ($deviceId) {
            // Fast summon using device ID directly - 2 second timeout
            $summonResult = $genieAcs->sendFastConnectionRequest($deviceId);
        }
        
        // Determine reachability status
        $isReachable = false;
        $lastInformFormatted = 'Never';
        $tr069Status = 'offline';
        
        if ($device) {
            $lastInformStr = $device['_lastInform'] ?? null;
            if ($lastInformStr) {
                $lastInform = strtotime($lastInformStr);
                $diff = time() - $lastInform;
                $isReachable = ($diff < 300);
                $tr069Status = $isReachable ? 'online' : 'offline';
                
                if ($diff < 60) {
                    $lastInformFormatted = $diff . ' seconds ago';
                } elseif ($diff < 3600) {
                    $lastInformFormatted = round($diff / 60) . ' minutes ago';
                } elseif ($diff < 86400) {
                    $lastInformFormatted = round($diff / 3600) . ' hours ago';
                } else {
                    $lastInformFormatted = round($diff / 86400) . ' days ago';
                }
            }
            
            // Update database with current status
            if ($onu) {
                $db->prepare("UPDATE huawei_onus SET tr069_status = ?, genieacs_id = ? WHERE id = ?")
                   ->execute([$tr069Status, $deviceId, $onu['id']]);
            }
        }
        
        echo json_encode([
            'success' => true,
            'summoned' => $summonResult['success'] ?? false,
            'queued' => $summonResult['queued'] ?? false,
            'reachable' => $isReachable,
            'tr069_status' => $tr069Status,
            'last_inform' => $lastInformFormatted,
            'device_id' => $deviceId,
            'in_genieacs' => ($device !== null),
            'onu_status' => $onu['status'] ?? 'unknown',
            'distance' => $onu['distance'] ?? null,
            'rx_power' => $onu['rx_power'] ?? null,
            'tx_power' => $onu['tx_power'] ?? null
        ]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Get WAN Configuration API
if ($page === 'api' && $action === 'get_wan_config') {
    ob_clean();
    header('Content-Type: application/json');
    
    if (!\App\Auth::isLoggedIn()) {
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit;
    }
    
    $onuId = isset($_GET['onu_id']) ? (int)$_GET['onu_id'] : 0;
    
    if (!$onuId) {
        echo json_encode(['success' => false, 'error' => 'ONU ID required']);
        exit;
    }
    
    try {
        $stmt = $db->prepare("SELECT * FROM huawei_onus WHERE id = ?");
        $stmt->execute([$onuId]);
        $onu = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$onu) {
            echo json_encode(['success' => false, 'error' => 'ONU not found']);
            exit;
        }
        
        $wans = [];
        if (!empty($onu['genieacs_id'])) {
            $huaweiOLT = new \App\HuaweiOLT($db);
            $wanData = $huaweiOLT->getWANConfigFromGenieACS($onu['genieacs_id']);
            if ($wanData && isset($wanData['wans'])) {
                $wans = $wanData['wans'];
            }
        }
        
        echo json_encode([
            'success' => true, 
            'onu' => $onu,
            'wans' => $wans
        ]);
    } catch (Throwable $e) {
        echo json_encode(['success' => true, 'onu' => $onu ?? null, 'wans' => [], 'note' => 'Could not fetch live WAN data: ' . $e->getMessage()]);
    }
    exit;
}

// TR-069 Reboot ONU API
if ($page === 'api' && $action === 'tr069_reboot_onu') {
    ob_clean();
    header('Content-Type: application/json');
    
    if (!\App\Auth::isLoggedIn()) {
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit;
    }
    
    $onuId = isset($_GET['onu_id']) ? (int)$_GET['onu_id'] : 0;
    
    if (!$onuId) {
        echo json_encode(['success' => false, 'error' => 'ONU ID required']);
        exit;
    }
    
    try {
        $stmt = $db->prepare("SELECT id, sn, tr069_device_id, tr069_serial, genieacs_id FROM huawei_onus WHERE id = ?");
        $stmt->execute([$onuId]);
        $onu = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$onu) {
            echo json_encode(['success' => false, 'error' => 'ONU not found']);
            exit;
        }
        
        require_once __DIR__ . '/../src/GenieACS.php';
        $genieacs = new \App\GenieACS($db);
        
        // Try genieacs_id first, then tr069_device_id
        $deviceId = !empty($onu['genieacs_id']) ? $onu['genieacs_id'] : '';
        $lookupMethod = 'genieacs_id';
        if (empty($deviceId)) {
            $deviceId = !empty($onu['tr069_device_id']) ? $onu['tr069_device_id'] : '';
            $lookupMethod = 'tr069_device_id';
        }
        
        // If still empty, look up by serial
        if (empty($deviceId)) {
            $serial = !empty($onu['tr069_serial']) ? $onu['tr069_serial'] : 
                     (!empty($onu['sn']) ? $onu['sn'] : '');
            $lookupMethod = "serial lookup ({$serial})";
            if (!empty($serial)) {
                $deviceResult = $genieacs->getDeviceBySerial($serial);
                error_log("[TR069 Reboot] Serial lookup for {$serial}: " . json_encode($deviceResult));
                if ($deviceResult['success'] && !empty($deviceResult['device']['_id'])) {
                    $deviceId = $deviceResult['device']['_id'];
                    // Save for future lookups
                    $updateStmt = $db->prepare("UPDATE huawei_onus SET genieacs_id = ? WHERE id = ?");
                    $updateStmt->execute([$deviceId, $onuId]);
                }
            }
        }
        
        if (empty($deviceId)) {
            error_log("[TR069 Reboot] No device ID found for ONU {$onuId}, fields: " . json_encode($onu));
            echo json_encode(['success' => false, 'error' => 'Device not connected to TR-069/GenieACS', 'debug' => $onu]);
            exit;
        }
        
        error_log("[TR069 Reboot] Using deviceId={$deviceId} (via {$lookupMethod})");
        $result = $genieacs->rebootDevice($deviceId);
        error_log("[TR069 Reboot] Result: " . json_encode($result));
        echo json_encode($result);
    } catch (Throwable $e) {
        error_log("[TR069 Reboot] Exception: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Configure WAN via TR-069 API
if ($page === 'api' && $action === 'configure_wan_tr069') {
    ob_clean();
    header('Content-Type: application/json');
    
    if (!\App\Auth::isLoggedIn()) {
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit;
    }
    
    $onuId = isset($_POST['onu_id']) ? (int)$_POST['onu_id'] : 0;
    $wanMode = $_POST['wan_mode'] ?? '';
    $serviceVlan = isset($_POST['service_vlan']) ? (int)$_POST['service_vlan'] : 0;
    
    if (!$onuId || !$wanMode) {
        echo json_encode(['success' => false, 'error' => 'ONU ID and WAN mode required']);
        exit;
    }
    
    try {
        $stmt = $db->prepare("SELECT * FROM huawei_onus WHERE id = ?");
        $stmt->execute([$onuId]);
        $onu = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$onu) {
            echo json_encode(['success' => false, 'error' => 'ONU not found']);
            exit;
        }
        
        $huaweiOLT = new \App\HuaweiOLT($db);
        
        $config = [
            'wan_mode' => $wanMode,
            'service_vlan' => $serviceVlan,
            'pppoe_username' => $_POST['pppoe_username'] ?? '',
            'pppoe_password' => $_POST['pppoe_password'] ?? '',
            'static_ip' => $_POST['static_ip'] ?? '',
            'subnet_mask' => $_POST['static_mask'] ?? $_POST['subnet_mask'] ?? '255.255.255.0',
            'gateway' => $_POST['static_gateway'] ?? $_POST['gateway'] ?? '',
            'dns_servers' => $_POST['static_dns'] ?? $_POST['dns_servers'] ?? '8.8.8.8,8.8.4.4',
            'bind_lan_ports' => isset($_POST['bind_lan_ports']),
            'bind_wifi' => isset($_POST['bind_wifi']),
            'wan_profile_id' => isset($_POST['wan_profile_id']) ? (int)$_POST['wan_profile_id'] : 1
        ];
        
        $result = $huaweiOLT->configureWANViaTR069($onuId, $config);
        $json = json_encode($result);
        if ($json === false) {
            echo json_encode(['success' => false, 'error' => 'JSON encode error: ' . json_last_error_msg()]);
        } else {
            echo $json;
        }
    } catch (Throwable $e) {
        error_log("[configure_wan_tr069] Exception: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    if (ob_get_level()) ob_end_flush();
    exit;
}

// Get TR-069 Device Logs (SmartOLT-style)
if ($page === 'api' && $action === 'get_tr069_logs') {
    ob_clean();
    header('Content-Type: application/json');
    
    if (!\App\Auth::isLoggedIn()) {
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit;
    }
    
    $onuId = isset($_GET['onu_id']) ? (int)$_GET['onu_id'] : 0;
    $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 500) : 100;
    
    if (!$onuId) {
        echo json_encode(['success' => false, 'error' => 'ONU ID required']);
        exit;
    }
    
    try {
        $huaweiOLT = new \App\HuaweiOLT($db);
        $logs = $huaweiOLT->getTR069Logs($onuId, $limit);
        echo json_encode(['success' => true, 'logs' => $logs, 'count' => count($logs)]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Update ONU Mode (Bridge/Router)
if ($page === 'api' && $action === 'update_onu_mode') {
    ob_clean();
    header('Content-Type: application/json');
    
    if (!\App\Auth::isLoggedIn()) {
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit;
    }
    
    $onuId = isset($_POST['onu_id']) ? (int)$_POST['onu_id'] : 0;
    $ipMode = $_POST['ip_mode'] ?? '';
    
    if (!$onuId || !in_array($ipMode, ['Bridge', 'Router'])) {
        echo json_encode(['success' => false, 'error' => 'ONU ID and valid mode (Bridge/Router) required']);
        exit;
    }
    
    try {
        if ($ipMode === 'Bridge') {
            // Simple Bridge mode - just update the database
            $stmt = $db->prepare("UPDATE huawei_onus SET ip_mode = ?, wan_mode = NULL, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$ipMode, $onuId]);
            echo json_encode(['success' => true, 'message' => "ONU mode set to Bridge (OMCI)"]);
        } else {
            // Router mode - configure WAN via TR-069
            $wanMode = $_POST['wan_mode'] ?? 'dhcp';
            $serviceVlan = isset($_POST['service_vlan']) ? (int)$_POST['service_vlan'] : 0;
            
            $config = [
                'wan_mode' => $wanMode,
                'service_vlan' => $serviceVlan,
                'pppoe_username' => $_POST['pppoe_username'] ?? '',
                'pppoe_password' => $_POST['pppoe_password'] ?? '',
                'static_ip' => $_POST['static_ip'] ?? '',
                'gateway' => $_POST['gateway'] ?? '',
                'subnet_mask' => '255.255.255.0',
                'dns_servers' => '8.8.8.8,8.8.4.4'
            ];
            
            // Update mode in database
            $stmt = $db->prepare("UPDATE huawei_onus SET ip_mode = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$ipMode, $onuId]);
            
            // Configure WAN via TR-069
            $huaweiOLT = new \App\HuaweiOLT($db);
            $result = $huaweiOLT->configureWANViaTR069($onuId, $config);
            
            if ($result['success']) {
                echo json_encode(['success' => true, 'message' => "Router mode with " . strtoupper($wanMode) . " configured via TR-069"]);
            } else {
                echo json_encode(['success' => true, 'message' => "Mode set to Router. WAN config: " . ($result['error'] ?? 'pending TR-069 connection')]);
            }
        }
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Manage ONU VLAN (attach/detach) - creates service-port on OLT
if ($page === 'api' && $action === 'manage_onu_vlan') {
    ob_clean();
    header('Content-Type: application/json');
    
    if (!\App\Auth::isLoggedIn()) {
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit;
    }
    
    $onuId = isset($_POST['onu_id']) ? (int)$_POST['onu_id'] : 0;
    $vlanId = isset($_POST['vlan_id']) ? (int)$_POST['vlan_id'] : 0;
    $vlanAction = $_POST['action'] ?? '';
    
    if (!$onuId || !$vlanId || !in_array($vlanAction, ['attach', 'detach'])) {
        echo json_encode(['success' => false, 'error' => 'ONU ID, VLAN ID and action (attach/detach) required']);
        exit;
    }
    
    try {
        $huaweiOLT = new \App\HuaweiOLT($db);
        
        if ($vlanAction === 'attach') {
            $result = $huaweiOLT->attachVlanToONU($onuId, $vlanId);
        } else {
            $result = $huaweiOLT->detachVlanFromONU($onuId, $vlanId);
        }
        
        echo json_encode($result);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Web Dashboard Clock In/Out API
if ($page === 'api' && $action === 'clock_in') {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    header('Cache-Control: no-cache');
    
    try {
        if (!\App\Auth::isLoggedIn()) {
            echo json_encode(['success' => false, 'error' => 'Not logged in']);
            exit;
        }
        
        $userId = \App\Auth::user()['id'];
        $stmt = $db->prepare("SELECT id FROM employees WHERE user_id = ?");
        $stmt->execute([$userId]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$employee) {
            echo json_encode(['success' => false, 'error' => 'No employee profile linked to your account']);
            exit;
        }
        
        $today = date('Y-m-d');
        $now = date('H:i:s');
        
        // Check if already clocked in
        $stmt = $db->prepare("SELECT * FROM attendance WHERE employee_id = ? AND date = ?");
        $stmt->execute([$employee['id'], $today]);
        $attendance = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($attendance && $attendance['clock_in']) {
            echo json_encode(['success' => false, 'error' => 'Already clocked in today at ' . $attendance['clock_in']]);
            exit;
        }
        
        // Calculate late minutes
        $lateCalculator = new \App\LateDeductionCalculator($db);
        $lateMinutes = $lateCalculator->calculateLateMinutes($employee['id'], $now);
        $lateDeduction = 0;
        $rule = $lateCalculator->getRuleForEmployee($employee['id']);
        if ($rule && $lateMinutes > 0) {
            $lateDeduction = $lateCalculator->calculateDeduction($lateMinutes, $rule);
        }
        
        if ($attendance) {
            $stmt = $db->prepare("UPDATE attendance SET clock_in = ?, late_minutes = ?, status = 'present', source = 'web' WHERE id = ?");
            $stmt->execute([$now, $lateMinutes, $attendance['id']]);
        } else {
            $stmt = $db->prepare("INSERT INTO attendance (employee_id, date, clock_in, late_minutes, status, source) VALUES (?, ?, ?, ?, 'present', 'web')");
            $stmt->execute([$employee['id'], $today, $now, $lateMinutes]);
        }
        
        $response = [
            'success' => true,
            'message' => 'Clocked in at ' . date('h:i A'),
            'clock_in' => $now,
            'is_late' => $lateMinutes > 0,
            'late_minutes' => $lateMinutes,
            'late_deduction' => $lateDeduction
        ];
        
        echo json_encode($response);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
    }
    exit;
}

if ($page === 'api' && $action === 'clock_out') {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    header('Cache-Control: no-cache');
    
    try {
        if (!\App\Auth::isLoggedIn()) {
            echo json_encode(['success' => false, 'error' => 'Not logged in']);
            exit;
        }
        
        $userId = \App\Auth::user()['id'];
        $stmt = $db->prepare("SELECT id FROM employees WHERE user_id = ?");
        $stmt->execute([$userId]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$employee) {
            echo json_encode(['success' => false, 'error' => 'No employee profile linked to your account']);
            exit;
        }
        
        $today = date('Y-m-d');
        $now = date('H:i:s');
        
        $stmt = $db->prepare("SELECT * FROM attendance WHERE employee_id = ? AND date = ?");
        $stmt->execute([$employee['id'], $today]);
        $attendance = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$attendance || !$attendance['clock_in']) {
            echo json_encode(['success' => false, 'error' => 'You must clock in first']);
            exit;
        }
        
        if ($attendance['clock_out']) {
            echo json_encode(['success' => false, 'error' => 'Already clocked out at ' . $attendance['clock_out']]);
            exit;
        }
        
        // Check minimum clock out time (configurable, default 5:00 PM)
        $settingsObj = new \App\Settings();
        $minClockOutHour = (int)$settingsObj->get('min_clock_out_hour', '17');
        $currentHour = (int)date('H');
        if ($currentHour < $minClockOutHour) {
            $minTimeFormatted = date('g:i A', strtotime("$minClockOutHour:00"));
            echo json_encode(['success' => false, 'error' => "Clock out is only allowed after $minTimeFormatted"]);
            exit;
        }
        
        // Calculate hours worked
        $clockIn = strtotime($attendance['clock_in']);
        $clockOut = strtotime($now);
        $hoursWorked = round(($clockOut - $clockIn) / 3600, 2);
        
        $stmt = $db->prepare("UPDATE attendance SET clock_out = ?, hours_worked = ? WHERE id = ?");
        $stmt->execute([$now, $hoursWorked, $attendance['id']]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Clocked out at ' . date('h:i A'),
            'clock_out' => $now,
            'hours_worked' => $hoursWorked
        ]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
    }
    exit;
}

if ($page === 'api' && $action === 'attendance_status') {
    ob_clean();
    header('Content-Type: application/json');
    
    if (!\App\Auth::isLoggedIn()) {
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit;
    }
    
    $userId = \App\Auth::user()['id'];
    $stmt = $db->prepare("SELECT id FROM employees WHERE user_id = ?");
    $stmt->execute([$userId]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$employee) {
        echo json_encode(['success' => true, 'data' => null, 'has_employee' => false]);
        exit;
    }
    
    $today = date('Y-m-d');
    $stmt = $db->prepare("SELECT * FROM attendance WHERE employee_id = ? AND date = ?");
    $stmt->execute([$employee['id'], $today]);
    $attendance = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'has_employee' => true,
        'data' => $attendance ?: null
    ]);
    exit;
}

if ($page === 'api' && $action === 'get_vpn_server_config') {
    ob_clean();
    header('Content-Type: application/json');
    
    if (!\App\Auth::isLoggedIn() || !\App\Auth::isAdmin()) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    
    $serverId = (int)($_GET['id'] ?? 0);
    if ($serverId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid server ID']);
        exit;
    }
    
    try {
        $wgService = new \App\WireGuardService($db);
        $config = $wgService->getServerConfig($serverId);
        echo json_encode(['success' => true, 'config' => $config]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($page === 'api' && $action === 'get_vpn_peer') {
    ob_clean();
    header('Content-Type: application/json');
    
    if (!\App\Auth::isLoggedIn() || !\App\Auth::isAdmin()) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    
    $peerId = (int)($_GET['id'] ?? 0);
    if ($peerId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid peer ID']);
        exit;
    }
    
    try {
        $wgService = new \App\WireGuardService($db);
        $peer = $wgService->getPeer($peerId);
        if ($peer) {
            echo json_encode(['success' => true, 'peer' => $peer]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Peer not found']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($page === 'api' && $action === 'get_vpn_peer_config') {
    ob_clean();
    header('Content-Type: application/json');
    
    if (!\App\Auth::isLoggedIn() || !\App\Auth::isAdmin()) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    
    $peerId = (int)($_GET['id'] ?? 0);
    if ($peerId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid peer ID']);
        exit;
    }
    
    try {
        $wgService = new \App\WireGuardService($db);
        $config = $wgService->getPeerConfig($peerId);
        echo json_encode(['success' => true, 'config' => $config]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($page === 'api' && $action === 'get_vpn_peer_mikrotik') {
    ob_clean();
    header('Content-Type: application/json');
    
    if (!\App\Auth::isLoggedIn() || !\App\Auth::isAdmin()) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    
    $peerId = (int)($_GET['id'] ?? 0);
    if ($peerId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid peer ID']);
        exit;
    }
    
    try {
        $wgService = new \App\WireGuardService($db);
        $script = $wgService->getMikroTikScript($peerId);
        echo json_encode(['success' => true, 'script' => $script]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($page === 'api' && $action === 'vpn_peer_status') {
    ob_clean();
    header('Content-Type: application/json');
    
    if (!\App\Auth::isLoggedIn() || !\App\Auth::isAdmin()) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    
    try {
        $wgService = new \App\WireGuardService($db);
        $peers = $wgService->getAllPeers();
        $peerStatus = [];
        
        foreach ($peers as $peer) {
            $connected = false;
            $stale = false;
            $lastHandshakeFormatted = null;
            
            if ($peer['last_handshake']) {
                $handshakeTime = strtotime($peer['last_handshake']);
                $timeDiff = time() - $handshakeTime;
                $connected = $timeDiff < 180;
                $stale = $timeDiff >= 180 && $timeDiff < 600;
                $lastHandshakeFormatted = date('H:i:s', $handshakeTime);
            }
            
            $peerStatus[] = [
                'id' => $peer['id'],
                'is_active' => (bool)$peer['is_active'],
                'connected' => $connected,
                'stale' => $stale,
                'last_handshake' => $lastHandshakeFormatted,
                'rx_formatted' => $wgService->formatBytes($peer['rx_bytes']),
                'tx_formatted' => $wgService->formatBytes($peer['tx_bytes'])
            ];
        }
        
        echo json_encode(['success' => true, 'peers' => $peerStatus]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($page === 'api' && $action === 'create_nas_vpn_peer') {
    ob_clean();
    header('Content-Type: application/json');
    
    if (!\App\Auth::isLoggedIn() || !\App\Auth::isAdmin()) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $nasId = (int)($input['nas_id'] ?? 0);
        $nasName = $input['name'] ?? '';
        $nasIp = $input['ip'] ?? '';
        
        if (!$nasId || !$nasName) {
            throw new Exception('Missing required parameters');
        }
        
        $wgService = new \App\WireGuardService($db);
        $peerId = $wgService->createPeerForNAS($nasId, $nasName, $nasIp);
        
        if ($peerId) {
            $peer = $wgService->getPeer($peerId);
            echo json_encode([
                'success' => true,
                'peer_id' => $peerId,
                'allowed_ips' => $peer['allowed_ips'] ?? 'N/A'
            ]);
        } else {
            throw new Exception('Failed to create VPN peer');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($page === 'api' && $action === 'repost_single_ticket') {
    ob_clean();
    header('Content-Type: application/json');
    
    if (!\App\Auth::isLoggedIn()) {
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit;
    }
    
    if (!\App\Auth::can('tickets.view')) {
        echo json_encode(['success' => false, 'error' => 'Not authorized']);
        exit;
    }
    
    $ticketId = (int)($_GET['ticket_id'] ?? 0);
    if ($ticketId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid ticket ID']);
        exit;
    }
    
    try {
        $ticket = new \App\Ticket($db);
        $ticketData = $ticket->find($ticketId);
        if (!$ticketData) {
            throw new Exception('Ticket not found');
        }
        
        $whatsapp = new \App\WhatsApp();
        if (!$whatsapp->isEnabled() || $whatsapp->getProvider() !== 'session') {
            throw new Exception('WhatsApp session provider is required for group messaging');
        }
        
        $customer = new \App\Customer($db);
        $customerData = $customer->find($ticketData['customer_id']);
        $assignedName = $ticketData['assigned_to'] ? (\App\User::find($ticketData['assigned_to'])['name'] ?? 'Unassigned') : 'Unassigned';
        $age = floor((time() - strtotime($ticketData['created_at'])) / 86400);
        
        $serviceFeeModel = new \App\ServiceFee($db);
        $ticketFees = $serviceFeeModel->getTicketFees($ticketId);
        $feesTotal = $serviceFeeModel->getTicketFeesTotal($ticketId);
        
        $priorityEmoji = match($ticketData['priority']) {
            'critical' => '🔴',
            'high' => '🟠',
            'medium' => '🟡',
            default => '🟢'
        };
        
        $repostMessage = "*{$priorityEmoji} TICKET REPOST*\n\n";
        $repostMessage .= "*#{$ticketData['ticket_number']}*: {$ticketData['subject']}\n";
        $repostMessage .= "Priority: " . ucfirst($ticketData['priority']) . " | Status: " . ucwords(str_replace('_', ' ', $ticketData['status'])) . "\n";
        $repostMessage .= "Category: " . ucfirst($ticketData['category']) . "\n";
        $repostMessage .= "Customer: " . ($customerData['name'] ?? 'Unknown') . "\n";
        if (!empty($customerData['phone'])) {
            $repostMessage .= "Phone: {$customerData['phone']}\n";
        }
        $repostMessage .= "Assigned: {$assignedName}\n";
        $repostMessage .= "Age: {$age} days\n";
        
        if (count($ticketFees) > 0) {
            $repostMessage .= "\n*Service Fees:*\n";
            foreach ($ticketFees as $fee) {
                $paidStatus = $fee['is_paid'] ? '✅' : '⏳';
                $repostMessage .= "• {$fee['fee_name']}: " . number_format($fee['amount'], 2) . " {$fee['currency']} {$paidStatus}\n";
            }
            $repostMessage .= "Total: " . number_format($feesTotal['total'], 2) . " (Paid: " . number_format($feesTotal['paid'], 2) . ")\n";
        }
        
        $repostMessage .= "\n_Reposted at " . date('h:i A, M j') . "_";
        
        $results = [];
        $errors = [];
        $groupsSent = 0;
        
        if (!empty($ticketData['branch_id'])) {
            $branchClass = new \App\Branch();
            $branch = $branchClass->find($ticketData['branch_id']);
            if ($branch && !empty($branch['whatsapp_group_id'])) {
                $result = $whatsapp->sendToGroup($branch['whatsapp_group_id'], $repostMessage);
                $results['branch'] = $result;
                if ($result['success']) {
                    $groupsSent++;
                } else {
                    $errors[] = "Branch group: " . ($result['error'] ?? 'Unknown error');
                }
            }
        }
        
        $settings = new \App\Settings($db);
        $operationsGroupId = $settings->get('whatsapp_operations_group_id', '');
        if (!empty($operationsGroupId)) {
            $result = $whatsapp->sendToGroup($operationsGroupId, $repostMessage);
            $results['operations'] = $result;
            if ($result['success']) {
                $groupsSent++;
            } else {
                $errors[] = "Operations group: " . ($result['error'] ?? 'Unknown error');
            }
        }
        
        if (empty($results)) {
            throw new Exception('No WhatsApp groups configured for this ticket');
        }
        
        echo json_encode([
            'success' => count($errors) === 0,
            'groups_sent' => $groupsSent,
            'errors' => $errors
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($page === 'submit_complaint' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    if (!empty($_POST['honeypot'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid submission']);
        exit;
    }
    
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $category = trim($_POST['category'] ?? 'general');
    $subject = trim($_POST['subject'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $location = trim($_POST['location'] ?? '');
    
    if (empty($name) || empty($phone) || empty($subject) || empty($description)) {
        echo json_encode(['success' => false, 'error' => 'Please fill in all required fields']);
        exit;
    }
    
    try {
        $customerId = null;
        $stmt = $db->prepare("SELECT id FROM customers WHERE phone = ?");
        $stmt->execute([$phone]);
        $existingCustomer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existingCustomer) {
            $customerId = $existingCustomer['id'];
        }
        
        $fullDescription = $description;
        if ($location) {
            $fullDescription .= "\n\nLocation: " . $location;
        }
        $fullDescription .= "\n\nSubmitted via: Public Complaint Form";
        
        $complaintModel = new \App\Complaint();
        $complaintId = $complaintModel->create([
            'customer_id' => $customerId,
            'customer_name' => $name,
            'customer_phone' => $phone,
            'customer_email' => $email ?: null,
            'customer_location' => $location ?: null,
            'category' => $category,
            'subject' => $subject,
            'description' => $fullDescription,
            'source' => 'public'
        ]);
        
        $complaint = $complaintModel->getById($complaintId);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Your complaint has been submitted successfully. Our team will review it and contact you soon.',
            'complaint_number' => $complaint['complaint_number']
        ]);
    } catch (Exception $e) {
        error_log("Public complaint submission error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'An error occurred. Please try again later.']);
    }
    exit;
}

$requestUri = strtok($_SERVER['REQUEST_URI'], '?');
$isHomepage = ($requestUri === '/' || $requestUri === '/index.php') && !isset($_GET['page']);
if ($page === 'landing' || $isHomepage) {
    $settingsObj = new \App\Settings();
    $packages = $settingsObj->getActivePackagesForLanding();
    $company = $settingsObj->getCompanyInfo();
    $landingSettings = $settingsObj->getLandingPageSettings();
    $contactSettings = $settingsObj->getContactSettings();
    include __DIR__ . '/../templates/landing.php';
    exit;
}

if ($page === 'mpesa_callback') {
    header('Content-Type: application/json');
    
    $callbackType = $_GET['type'] ?? '';
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    
    file_put_contents(__DIR__ . '/../logs/mpesa_' . date('Y-m-d') . '.log', 
        date('Y-m-d H:i:s') . " [{$callbackType}]: " . $rawInput . "\n", 
        FILE_APPEND);
    
    try {
        $mpesa = new \App\Mpesa();
        
        switch ($callbackType) {
            case 'stkpush':
                if ($data) {
                    $mpesa->handleStkCallback($data);
                }
                echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Success']);
                break;
                
            case 'validation':
                $response = $mpesa->handleC2BValidation($data ?? []);
                echo json_encode($response);
                break;
                
            case 'confirmation':
                if ($data) {
                    $mpesa->handleC2BConfirmation($data);
                }
                echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Success']);
                break;
                
            default:
                echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Unknown callback type']);
        }
    } catch (Exception $e) {
        error_log("M-Pesa callback error: " . $e->getMessage());
        echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Error processing callback']);
    }
    exit;
}

if ($page === 'order') {
    $settingsObj = new \App\Settings();
    $company = $settingsObj->getCompanyInfo();
    $landingSettings = $settingsObj->getLandingPageSettings();
    
    $packageId = isset($_GET['package']) ? (int)$_GET['package'] : null;
    $package = null;
    if ($packageId) {
        $package = $settingsObj->getPackage($packageId);
    }
    
    $orderSuccess = false;
    $orderNumber = '';
    $paymentInitiated = false;
    $error = '';
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'submit') {
        $customerName = trim($_POST['customer_name'] ?? '');
        $customerPhone = trim($_POST['customer_phone'] ?? '');
        $customerEmail = trim($_POST['customer_email'] ?? '');
        $customerAddress = trim($_POST['customer_address'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $paymentMethod = $_POST['payment_method'] ?? 'later';
        $amount = floatval($_POST['amount'] ?? 0);
        $pkgId = !empty($_POST['package_id']) ? (int)$_POST['package_id'] : null;
        
        if (empty($customerName) || empty($customerPhone) || empty($customerAddress)) {
            $error = 'Please fill in all required fields.';
        } else {
            try {
                $orderModel = new \App\Order();
                $orderId = $orderModel->create([
                    'package_id' => $pkgId,
                    'customer_name' => $customerName,
                    'customer_email' => $customerEmail,
                    'customer_phone' => $customerPhone,
                    'customer_address' => $customerAddress,
                    'amount' => $amount,
                    'payment_method' => $paymentMethod,
                    'notes' => $notes
                ]);
                
                $order = $orderModel->getById($orderId);
                $orderNumber = $order['order_number'] ?? '';
                $orderSuccess = true;
                
                // M-Pesa payment - separate try-catch so order success shows even if M-Pesa fails
                if ($paymentMethod === 'mpesa' && $amount > 0) {
                    try {
                        $mpesa = new \App\Mpesa();
                        if ($mpesa->isConfigured()) {
                            $result = $mpesa->stkPush($customerPhone, $amount, $orderNumber, 'Order Payment');
                            if ($result['success']) {
                                $paymentInitiated = true;
                                if (!empty($result['transaction_id'])) {
                                    $orderModel->updatePaymentStatus($orderId, 'pending', $result['transaction_id']);
                                }
                            }
                        }
                    } catch (\Exception $mpesaError) {
                        error_log("M-Pesa STK push error: " . $mpesaError->getMessage());
                        // Order still successful, just payment initiation failed
                    }
                }
            } catch (\Exception $e) {
                $error = 'An error occurred. Please try again.';
                error_log("Order creation error: " . $e->getMessage());
            }
        }
    }
    
    include __DIR__ . '/../templates/order_form.php';
    exit;
}

if ($page === 'login') {
    $loginError = '';
    $csrfToken = \App\Auth::generateToken();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $submittedToken = $_POST['csrf_token'] ?? '';
        
        if (!\App\Auth::validateToken($submittedToken)) {
            $loginError = 'Invalid request. Please try again.';
        } else {
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            
            if (\App\Auth::login($email, $password)) {
                header('Location: ?page=dashboard');
                exit;
            } else {
                $loginError = 'Invalid email or password';
            }
        }
        $csrfToken = \App\Auth::generateToken();
    }
    include __DIR__ . '/../templates/login.php';
    exit;
}

// OMS (ONU Management System) - standalone page with its own layout
if ($page === 'huawei-olt') {
    \App\Auth::requireLogin();
    if (!\App\Auth::can('settings.view')) {
        echo '<div class="alert alert-danger m-4"><i class="bi bi-shield-exclamation me-2"></i><strong>Access Denied.</strong> You do not have permission to view this page.</div>';
        exit;
    }
    include __DIR__ . '/../templates/huawei_olt.php';
    exit;
}

// Finance Module - standalone page with its own layout
if ($page === 'finance') {
    \App\Auth::requireLogin();
    if (!\App\Auth::can('settings.view')) {
        echo '<div class="alert alert-danger m-4"><i class="bi bi-shield-exclamation me-2"></i><strong>Access Denied.</strong> You do not have permission to view this page.</div>';
        exit;
    }
    include __DIR__ . '/../templates/finance_dashboard.php';
    exit;
}

// ISP RADIUS Billing - standalone page with its own layout
if ($page === 'isp') {
    \App\Auth::requireLogin();
    if (!\App\Auth::can('settings.view')) {
        echo '<div class="alert alert-danger m-4"><i class="bi bi-shield-exclamation me-2"></i><strong>Access Denied.</strong> You do not have permission to view this page.</div>';
        exit;
    }
    
    // Handle AJAX actions
    $action = $_GET['action'] ?? '';
    if ($action === 'test_nas') {
        header('Content-Type: application/json');
        $radiusBilling = new \App\RadiusBilling($db);
        $nasId = (int)($_GET['id'] ?? 0);
        $nas = $radiusBilling->getNAS($nasId);
        
        if (!$nas) {
            echo json_encode(['success' => false, 'error' => 'NAS not found']);
            exit;
        }
        
        $ip = $nas['ip_address'];
        $apiPort = $nas['api_port'] ?? 8728;
        
        $result = [
            'success' => true,
            'online' => false,
            'api_online' => false,
            'latency_ms' => null,
            'api_latency_ms' => null,
            'reachable_port' => null
        ];
        
        $portsToCheck = [22, 23, 80, 443, 8291];
        
        foreach ($portsToCheck as $port) {
            $startTime = microtime(true);
            $socket = @fsockopen($ip, $port, $errno, $errstr, 1);
            $endTime = microtime(true);
            
            if ($socket) {
                fclose($socket);
                $result['online'] = true;
                $result['latency_ms'] = round(($endTime - $startTime) * 1000, 2);
                $result['reachable_port'] = $port;
                break;
            }
        }
        
        $startTime = microtime(true);
        $socket = @fsockopen($ip, $apiPort, $errno, $errstr, 2);
        $endTime = microtime(true);
        
        if ($socket) {
            fclose($socket);
            $result['api_online'] = true;
            $result['api_latency_ms'] = round(($endTime - $startTime) * 1000, 2);
            if (!$result['online']) {
                $result['online'] = true;
                $result['latency_ms'] = $result['api_latency_ms'];
                $result['reachable_port'] = $apiPort;
            }
        }
        
        echo json_encode($result);
        exit;
    }
    
    if ($action === 'ping_subscriber') {
        header('Content-Type: application/json');
        $radiusBilling = new \App\RadiusBilling($db);
        $subId = (int)($_GET['id'] ?? 0);
        $sub = $radiusBilling->getSubscription($subId);
        
        if (!$sub) {
            echo json_encode(['success' => false, 'error' => 'Subscriber not found']);
            exit;
        }
        
        $ipAddress = $sub['static_ip'] ?? null;
        if (!$ipAddress) {
            $stmt = $db->prepare("
                SELECT framed_ip_address FROM radius_sessions 
                WHERE subscription_id = ? AND session_end IS NULL 
                ORDER BY started_at DESC LIMIT 1
            ");
            $stmt->execute([$subId]);
            $ipAddress = $stmt->fetchColumn();
        }
        
        if (!$ipAddress) {
            echo json_encode(['success' => false, 'error' => 'No IP address found. Subscriber may be offline.']);
            exit;
        }
        
        $result = ['success' => true, 'online' => false, 'ip_address' => $ipAddress, 'latency_ms' => null];
        
        $startTime = microtime(true);
        $socket = @fsockopen($ipAddress, 80, $errno, $errstr, 2);
        $endTime = microtime(true);
        
        if ($socket) {
            fclose($socket);
            $result['online'] = true;
            $result['latency_ms'] = round(($endTime - $startTime) * 1000, 2);
        } else {
            $socket = @fsockopen($ipAddress, 443, $errno, $errstr, 2);
            $endTime = microtime(true);
            if ($socket) {
                fclose($socket);
                $result['online'] = true;
                $result['latency_ms'] = round(($endTime - $startTime) * 1000, 2);
            }
        }
        
        echo json_encode($result);
        exit;
    }
    
    if ($action === 'sync_mikrotik_blocked') {
        header('Content-Type: application/json');
        $radiusBilling = new \App\RadiusBilling($db);
        $nasId = isset($_GET['nas_id']) && $_GET['nas_id'] !== '' ? (int)$_GET['nas_id'] : null;
        $result = $radiusBilling->syncMikroTikBlockedList($nasId);
        echo json_encode($result);
        exit;
    }
    
    if ($action === 'block_ip' || $action === 'unblock_ip') {
        header('Content-Type: application/json');
        $radiusBilling = new \App\RadiusBilling($db);
        $subId = (int)($_GET['subscription_id'] ?? 0);
        $block = $action === 'block_ip';
        $reason = $block ? 'Manual block' : '';
        $result = $radiusBilling->updateMikroTikBlockedStatus($subId, $block, $reason);
        echo json_encode($result);
        exit;
    }
    
    if ($action === 'get_blocked_list') {
        header('Content-Type: application/json');
        $radiusBilling = new \App\RadiusBilling($db);
        $subs = $radiusBilling->getBlockedSubscriptions();
        echo json_encode(['success' => true, 'subscriptions' => $subs]);
        exit;
    }
    
    // VLAN Management API
    if ($action === 'get_vlans') {
        header('Content-Type: application/json');
        $radiusBilling = new \App\RadiusBilling($db);
        $nasId = isset($_GET['nas_id']) && $_GET['nas_id'] !== '' ? (int)$_GET['nas_id'] : null;
        $vlans = $radiusBilling->getVlans($nasId);
        echo json_encode(['success' => true, 'vlans' => $vlans]);
        exit;
    }
    
    if ($action === 'get_vlan') {
        header('Content-Type: application/json');
        $radiusBilling = new \App\RadiusBilling($db);
        $id = (int)($_GET['id'] ?? 0);
        $vlan = $radiusBilling->getVlan($id);
        echo json_encode(['success' => true, 'vlan' => $vlan]);
        exit;
    }
    
    if ($action === 'create_vlan' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        $radiusBilling = new \App\RadiusBilling($db);
        $data = json_decode(file_get_contents('php://input'), true);
        $result = $radiusBilling->createVlan($data);
        echo json_encode($result);
        exit;
    }
    
    if ($action === 'update_vlan' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        $radiusBilling = new \App\RadiusBilling($db);
        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)($data['id'] ?? 0);
        $result = $radiusBilling->updateVlan($id, $data);
        echo json_encode($result);
        exit;
    }
    
    if ($action === 'delete_vlan') {
        header('Content-Type: application/json');
        $radiusBilling = new \App\RadiusBilling($db);
        $id = (int)($_GET['id'] ?? 0);
        $result = $radiusBilling->deleteVlan($id);
        echo json_encode($result);
        exit;
    }
    
    if ($action === 'sync_vlan') {
        header('Content-Type: application/json');
        $radiusBilling = new \App\RadiusBilling($db);
        $id = (int)($_GET['id'] ?? 0);
        $result = $radiusBilling->syncVlanToMikroTik($id);
        echo json_encode($result);
        exit;
    }
    
    if ($action === 'import_vlans') {
        header('Content-Type: application/json');
        $radiusBilling = new \App\RadiusBilling($db);
        $nasId = isset($_GET['nas_id']) ? (int)$_GET['nas_id'] : null;
        $result = $radiusBilling->importVlansFromMikroTik($nasId);
        echo json_encode($result);
        exit;
    }
    
    if ($action === 'fetch_interfaces') {
        header('Content-Type: application/json');
        $radiusBilling = new \App\RadiusBilling($db);
        $nasId = (int)($_GET['nas_id'] ?? 0);
        $result = $radiusBilling->fetchMikroTikInterfaces($nasId);
        echo json_encode($result);
        exit;
    }
    
    if ($action === 'provision_static_ip' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        $radiusBilling = new \App\RadiusBilling($db);
        $data = json_decode(file_get_contents('php://input'), true);
        $result = $radiusBilling->provisionStaticIp(
            (int)$data['subscription_id'],
            (int)$data['vlan_id'],
            $data['ip_address'],
            $data['mac_address']
        );
        echo json_encode($result);
        exit;
    }
    
    if ($action === 'deprovision_static_ip') {
        header('Content-Type: application/json');
        $radiusBilling = new \App\RadiusBilling($db);
        $subId = (int)($_GET['subscription_id'] ?? 0);
        $result = $radiusBilling->deprovisionStaticIp($subId);
        echo json_encode($result);
        exit;
    }
    
    // VLAN Traffic Monitoring
    if ($action === 'vlan_traffic') {
        header('Content-Type: application/json');
        $radiusBilling = new \App\RadiusBilling($db);
        $vlanId = (int)($_GET['vlan_id'] ?? 0);
        
        if (!$vlanId) {
            echo json_encode(['success' => false, 'error' => 'VLAN ID required']);
            exit;
        }
        
        $vlan = $radiusBilling->getVlan($vlanId);
        if (!$vlan) {
            echo json_encode(['success' => false, 'error' => 'VLAN not found']);
            exit;
        }
        
        try {
            $nas = $radiusBilling->getNAS($vlan['nas_id']);
            if (!$nas || !$nas['api_enabled']) {
                echo json_encode(['success' => false, 'error' => 'NAS device not configured for API']);
                exit;
            }
            
            require_once __DIR__ . '/../src/MikroTikAPI.php';
            $api = new \App\MikroTikAPI($nas['ip_address'], $nas['api_port'] ?: 8728, $nas['api_username'], $radiusBilling->decryptPassword($nas['api_password_encrypted']));
            $api->connect();
            
            // Get interface traffic statistics for the VLAN interface
            // Try multiple interface naming patterns:
            // 1. VLAN interface by vlan-id on parent interface
            // 2. Interface named like "vlan{id}" 
            // 3. The stored name from database
            $vlanId = $vlan['vlan_id'];
            $parentInterface = $vlan['interface'] ?? '';
            
            // First try to find VLAN by vlan-id
            $stats = $api->command('/interface/vlan/print', ['?vlan-id' => (string)$vlanId]);
            
            // If not found, try interface named "vlan{id}"
            if (empty($stats)) {
                $stats = $api->command('/interface/print', ['?name' => 'vlan' . $vlanId]);
            }
            
            // If still not found, try the database name
            if (empty($stats)) {
                $stats = $api->command('/interface/print', ['?name' => $vlan['name']]);
            }
            
            $interfaceName = $vlan['name'];
            
            $traffic = [
                'name' => $interfaceName,
                'rx_byte' => 0,
                'tx_byte' => 0,
                'rx_packet' => 0,
                'tx_packet' => 0,
                'running' => false
            ];
            
            if (!empty($stats) && isset($stats[0])) {
                $traffic = [
                    'name' => $stats[0]['name'] ?? $interfaceName,
                    'rx_byte' => (int)($stats[0]['rx-byte'] ?? 0),
                    'tx_byte' => (int)($stats[0]['tx-byte'] ?? 0),
                    'rx_packet' => (int)($stats[0]['rx-packet'] ?? 0),
                    'tx_packet' => (int)($stats[0]['tx-packet'] ?? 0),
                    'running' => ($stats[0]['running'] ?? 'false') === 'true'
                ];
            }
            
            $api->disconnect();
            
            echo json_encode([
                'success' => true,
                'traffic' => $traffic,
                'timestamp' => time() * 1000
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    // VLAN Interface Status
    if ($action === 'vlan_status') {
        header('Content-Type: application/json');
        $radiusBilling = new \App\RadiusBilling($db);
        $vlanId = (int)($_GET['vlan_id'] ?? 0);
        
        $vlan = $radiusBilling->getVlan($vlanId);
        if (!$vlan) {
            echo json_encode(['success' => false, 'error' => 'VLAN not found']);
            exit;
        }
        
        try {
            $nas = $radiusBilling->getNAS($vlan['nas_id']);
            if (!$nas || !$nas['api_enabled']) {
                echo json_encode(['success' => false, 'error' => 'NAS not configured']);
                exit;
            }
            
            require_once __DIR__ . '/../src/MikroTikAPI.php';
            $api = new \App\MikroTikAPI($nas['ip_address'], $nas['api_port'] ?: 8728, $nas['api_username'], $radiusBilling->decryptPassword($nas['api_password_encrypted']));
            $api->connect();
            
            $stats = $api->command('/interface/vlan/print', ['?name' => $vlan['name']]);
            $api->disconnect();
            
            if (!empty($stats) && isset($stats[0])) {
                echo json_encode([
                    'success' => true,
                    'status' => [
                        'name' => $stats[0]['name'] ?? '',
                        'vlan_id' => $stats[0]['vlan-id'] ?? '',
                        'interface' => $stats[0]['interface'] ?? '',
                        'running' => ($stats[0]['running'] ?? 'false') === 'true',
                        'disabled' => ($stats[0]['disabled'] ?? 'false') === 'true',
                        'mtu' => $stats[0]['mtu'] ?? '',
                        'mac_address' => $stats[0]['mac-address'] ?? ''
                    ]
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => 'VLAN not found on device']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    // Get VLAN traffic history for graphs
    if ($action === 'vlan_traffic_history') {
        header('Content-Type: application/json');
        $vlanId = (int)($_GET['vlan_id'] ?? 0);
        $range = $_GET['range'] ?? '1h';
        
        if (!$vlanId) {
            echo json_encode(['success' => false, 'error' => 'VLAN ID required']);
            exit;
        }
        
        // Calculate time range
        $intervals = [
            '1h' => '1 hour',
            '12h' => '12 hours',
            '24h' => '24 hours',
            '1w' => '7 days',
            '1m' => '30 days'
        ];
        $interval = $intervals[$range] ?? '1 hour';
        
        try {
            $stmt = $db->prepare("
                SELECT rx_bytes, tx_bytes, rx_rate, tx_rate, is_running, recorded_at
                FROM vlan_traffic_history 
                WHERE vlan_id = ? AND recorded_at > NOW() - INTERVAL '$interval'
                ORDER BY recorded_at ASC
            ");
            $stmt->execute([$vlanId]);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'range' => $range,
                'data' => $history
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    // Record VLAN traffic snapshot (for cron job)
    if ($action === 'vlan_traffic_collect') {
        header('Content-Type: application/json');
        $radiusBilling = new \App\RadiusBilling($db);
        
        try {
            // Get all active VLANs
            $vlans = $radiusBilling->getVlans();
            $collected = 0;
            $errors = [];
            
            // Group VLANs by NAS to minimize connections
            $vlansByNas = [];
            foreach ($vlans as $vlan) {
                if ($vlan['is_active'] && $vlan['nas_id']) {
                    $vlansByNas[$vlan['nas_id']][] = $vlan;
                }
            }
            
            require_once __DIR__ . '/../src/MikroTikAPI.php';
            
            foreach ($vlansByNas as $nasId => $nasVlans) {
                $nas = $radiusBilling->getNAS($nasId);
                if (!$nas || !$nas['api_enabled'] || !$nas['api_password_encrypted']) {
                    continue;
                }
                
                try {
                    $api = new \App\MikroTikAPI($nas['ip_address'], $nas['api_port'] ?: 8728, $nas['api_username'], $radiusBilling->decryptPassword($nas['api_password_encrypted']));
                    $api->connect();
                    
                    // Get all interface stats in one call
                    $allStats = $api->command('/interface/print');
                    $statsMap = [];
                    foreach ($allStats as $stat) {
                        $statsMap[$stat['name'] ?? ''] = $stat;
                    }
                    
                    $api->disconnect();
                    
                    // Get previous readings for rate calculation
                    foreach ($nasVlans as $vlan) {
                        $ifName = $vlan['name'];
                        if (!isset($statsMap[$ifName])) continue;
                        
                        $stat = $statsMap[$ifName];
                        $rxBytes = (int)($stat['rx-byte'] ?? 0);
                        $txBytes = (int)($stat['tx-byte'] ?? 0);
                        
                        // Get last reading to calculate rate
                        $stmt = $db->prepare("SELECT rx_bytes, tx_bytes, recorded_at FROM vlan_traffic_history WHERE vlan_id = ? ORDER BY recorded_at DESC LIMIT 1");
                        $stmt->execute([$vlan['id']]);
                        $last = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        $rxRate = 0;
                        $txRate = 0;
                        if ($last) {
                            $timeDiff = time() - strtotime($last['recorded_at']);
                            if ($timeDiff > 0) {
                                $rxDiff = $rxBytes - (int)$last['rx_bytes'];
                                $txDiff = $txBytes - (int)$last['tx_bytes'];
                                if ($rxDiff >= 0 && $txDiff >= 0) {
                                    $rxRate = ($rxDiff * 8) / ($timeDiff * 1000000); // Mbps
                                    $txRate = ($txDiff * 8) / ($timeDiff * 1000000);
                                }
                            }
                        }
                        
                        // Insert new reading
                        $stmt = $db->prepare("
                            INSERT INTO vlan_traffic_history (vlan_id, rx_bytes, tx_bytes, rx_packets, tx_packets, rx_rate, tx_rate, is_running)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $vlan['id'],
                            $rxBytes,
                            $txBytes,
                            (int)($stat['rx-packet'] ?? 0),
                            (int)($stat['tx-packet'] ?? 0),
                            round($rxRate, 2),
                            round($txRate, 2),
                            ($stat['running'] ?? 'false') === 'true'
                        ]);
                        $collected++;
                    }
                } catch (Exception $e) {
                    $errors[] = $nas['name'] . ': ' . $e->getMessage();
                }
            }
            
            // Cleanup old data (keep 30 days)
            $db->exec("DELETE FROM vlan_traffic_history WHERE recorded_at < NOW() - INTERVAL '30 days'");
            
            echo json_encode([
                'success' => true,
                'collected' => $collected,
                'errors' => $errors
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    include __DIR__ . '/../templates/isp.php';
    exit;
}

\App\Auth::requireLogin();

$customer = new \App\Customer();
$ticket = new \App\Ticket();
$sms = new \App\SMS();
$smsGateway = null;
$employee = new \App\Employee();
$settings = new \App\Settings();
$currentUser = \App\Auth::user();

function getSMSGateway() {
    static $gateway = null;
    if ($gateway === null) {
        $gateway = new \App\SMSGateway();
    }
    return $gateway;
}

$message = '';
$messageType = '';

if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    $messageType = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!\App\Auth::validateToken($csrfToken)) {
        $message = 'Invalid request. Please try again.';
        $messageType = 'danger';
    } else {
        $postAction = $_POST['action'] ?? '';
        
        switch ($postAction) {
            case 'create_customer':
                if (!\App\Auth::can('customers.create')) {
                    $message = 'You do not have permission to create customers.';
                    $messageType = 'danger';
                    break;
                }
                $name = trim($_POST['name'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $address = trim($_POST['address'] ?? '');
                $servicePlan = trim($_POST['service_plan'] ?? '');
                
                if (empty($name) || empty($phone) || empty($address) || empty($servicePlan)) {
                    $message = 'Please fill in all required fields.';
                    $messageType = 'danger';
                } else {
                    try {
                        $customerId = $customer->create($_POST);
                        $message = 'Customer created successfully!';
                        $messageType = 'success';
                        \App\Auth::regenerateToken();
                    } catch (Exception $e) {
                        $message = 'Error creating customer: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'update_customer':
                if (!\App\Auth::can('customers.edit')) {
                    $message = 'You do not have permission to edit customers.';
                    $messageType = 'danger';
                    break;
                }
                $name = trim($_POST['name'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $address = trim($_POST['address'] ?? '');
                $servicePlan = trim($_POST['service_plan'] ?? '');
                
                if (empty($name) || empty($phone) || empty($address) || empty($servicePlan)) {
                    $message = 'Please fill in all required fields.';
                    $messageType = 'danger';
                } else {
                    try {
                        $customer->update((int)$_POST['id'], $_POST);
                        $message = 'Customer updated successfully!';
                        $messageType = 'success';
                        \App\Auth::regenerateToken();
                    } catch (Exception $e) {
                        $message = 'Error updating customer: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'delete_customer':
                if (!\App\Auth::can('customers.delete')) {
                    $message = 'You do not have permission to delete customers.';
                    $messageType = 'danger';
                } else {
                    try {
                        $customer->delete((int)$_POST['id']);
                        $message = 'Customer deleted successfully!';
                        $messageType = 'success';
                        \App\Auth::regenerateToken();
                        header('Location: ?page=customers');
                        exit;
                    } catch (Exception $e) {
                        $message = 'Error deleting customer: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'create_ticket':
                if (!\App\Auth::can('tickets.create')) {
                    $message = 'You do not have permission to create tickets.';
                    $messageType = 'danger';
                    break;
                }
                
                $customerType = $_POST['customer_type'] ?? 'existing';
                $customerId = (int)($_POST['customer_id'] ?? 0);
                $subject = trim($_POST['subject'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $category = trim($_POST['category'] ?? '');
                
                // Handle inline new customer creation
                if ($customerType === 'new') {
                    $newName = trim($_POST['new_customer_name'] ?? '');
                    $newPhone = trim($_POST['new_customer_phone'] ?? '');
                    $newEmail = trim($_POST['new_customer_email'] ?? '');
                    $newPlan = trim($_POST['new_customer_service_plan'] ?? 'basic');
                    $newAddress = trim($_POST['new_customer_address'] ?? '');
                    
                    if (empty($newName) || empty($newPhone) || empty($newAddress)) {
                        $message = 'Please fill in all required customer fields (Name, Phone, Address).';
                        $messageType = 'danger';
                        break;
                    }
                    
                    // Check if customer with same phone exists
                    $existingByPhone = $customer->findByPhone($newPhone);
                    if ($existingByPhone) {
                        $message = 'A customer with this phone number already exists. Please select from existing customers.';
                        $messageType = 'warning';
                        break;
                    }
                    
                    try {
                        $customerId = $customer->create([
                            'name' => $newName,
                            'phone' => $newPhone,
                            'email' => $newEmail,
                            'service_plan' => $newPlan,
                            'address' => $newAddress,
                            'connection_status' => 'pending'
                        ]);
                        $_POST['customer_id'] = $customerId;
                    } catch (Exception $e) {
                        $message = 'Error creating new customer: ' . $e->getMessage();
                        $messageType = 'danger';
                        break;
                    }
                }
                
                if ($customerType === 'billing') {
                    $billingData = $_POST['billing_customer'] ?? '';
                    if (empty($billingData)) {
                        $message = 'Please search and select a customer from the billing system.';
                        $messageType = 'danger';
                        break;
                    }
                    
                    $billingCustomer = json_decode($billingData, true);
                    if (!$billingCustomer) {
                        $message = 'Invalid billing customer data.';
                        $messageType = 'danger';
                        break;
                    }
                    
                    $billingName = !empty($billingCustomer['name']) ? $billingCustomer['name'] : 'Billing Customer';
                    $billingPhone = !empty($billingCustomer['phone']) ? $billingCustomer['phone'] : null;
                    $billingAddress = !empty($billingCustomer['address']) ? $billingCustomer['address'] : 'N/A';
                    $billingPlan = !empty($billingCustomer['service_plan']) ? $billingCustomer['service_plan'] : 'Standard';
                    
                    if (empty($billingPhone)) {
                        $message = 'Billing customer must have a phone number.';
                        $messageType = 'danger';
                        break;
                    }
                    
                    $existingByPhone = $customer->findByPhone($billingPhone);
                    if ($existingByPhone) {
                        $customerId = $existingByPhone['id'];
                        $customer->update($customerId, [
                            'name' => $billingName,
                            'email' => $billingCustomer['email'] ?? $existingByPhone['email'],
                            'address' => $billingAddress,
                            'service_plan' => $billingPlan,
                            'connection_status' => $billingCustomer['connection_status'] ?? $existingByPhone['connection_status'],
                            'username' => $billingCustomer['username'] ?? $existingByPhone['username'],
                            'billing_id' => $billingCustomer['billing_id'] ?? $existingByPhone['billing_id'],
                        ]);
                    } else {
                        try {
                            $customerId = $customer->create([
                                'name' => $billingName,
                                'phone' => $billingPhone,
                                'email' => $billingCustomer['email'] ?? null,
                                'service_plan' => $billingPlan,
                                'address' => $billingAddress,
                                'connection_status' => $billingCustomer['connection_status'] ?? 'active',
                                'username' => $billingCustomer['username'] ?? null,
                                'billing_id' => $billingCustomer['billing_id'] ?? null,
                            ]);
                        } catch (Exception $e) {
                            $message = 'Error importing billing customer: ' . $e->getMessage();
                            $messageType = 'danger';
                            break;
                        }
                    }
                    $_POST['customer_id'] = $customerId;
                }
                
                $branchId = (int)($_POST['branch_id'] ?? 0);
                
                if (empty($customerId) || empty($subject) || empty($description) || empty($category)) {
                    $message = 'Please fill in all required fields.';
                    $messageType = 'danger';
                } elseif (empty($branchId)) {
                    $message = 'Please select a branch for this ticket.';
                    $messageType = 'danger';
                } elseif (!$customer->find($customerId)) {
                    $message = 'Selected customer not found.';
                    $messageType = 'danger';
                } else {
                    try {
                        $ticketId = $ticket->create($_POST);
                        
                        if (!empty($_POST['service_fees']) && is_array($_POST['service_fees'])) {
                            $serviceFeeModel = new \App\ServiceFee($db);
                            $feeAmounts = $_POST['fee_amounts'] ?? [];
                            
                            foreach ($_POST['service_fees'] as $feeTypeId) {
                                $feeType = $serviceFeeModel->getFeeType((int)$feeTypeId);
                                if ($feeType) {
                                    $amount = isset($feeAmounts[$feeTypeId]) ? (float)$feeAmounts[$feeTypeId] : $feeType['default_amount'];
                                    $serviceFeeModel->addTicketFee($ticketId, [
                                        'fee_type_id' => $feeTypeId,
                                        'fee_name' => $feeType['name'],
                                        'amount' => $amount,
                                        'currency' => $feeType['currency'] ?? 'KES',
                                        'created_by' => $currentUser['id'] ?? null
                                    ]);
                                }
                            }
                        }
                        
                        $customerCreatedMsg = ($customerType === 'new') ? ' New customer created.' : (($customerType === 'billing') ? ' Customer imported from billing.' : '');
                        $message = 'Ticket created successfully!' . $customerCreatedMsg . ' SMS notifications sent.';
                        $messageType = 'success';
                        \App\Auth::regenerateToken();
                    } catch (Exception $e) {
                        $message = 'Error creating ticket: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'update_ticket':
                if (!\App\Auth::can('tickets.edit')) {
                    $message = 'You do not have permission to edit tickets.';
                    $messageType = 'danger';
                    break;
                }
                $subject = trim($_POST['subject'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $category = trim($_POST['category'] ?? '');
                $status = trim($_POST['status'] ?? '');
                
                if (empty($subject) || empty($description) || empty($category) || empty($status)) {
                    $message = 'Please fill in all required fields.';
                    $messageType = 'danger';
                } else {
                    try {
                        $ticketId = (int)$_POST['id'];
                        $ticket->update($ticketId, $_POST);
                        
                        $serviceFeeModel = new \App\ServiceFee($db);
                        $feeAmounts = $_POST['fee_amounts'] ?? [];
                        $feeData = [];
                        
                        if (!empty($_POST['service_fees']) && is_array($_POST['service_fees'])) {
                            foreach ($_POST['service_fees'] as $feeTypeId) {
                                $feeData[] = [
                                    'fee_type_id' => (int)$feeTypeId,
                                    'amount' => isset($feeAmounts[$feeTypeId]) ? (float)$feeAmounts[$feeTypeId] : 0
                                ];
                            }
                        }
                        $serviceFeeModel->syncTicketFees($ticketId, $feeData, $currentUser['id'] ?? null);
                        
                        $message = 'Ticket updated successfully! SMS notification sent to customer.';
                        $messageType = 'success';
                        \App\Auth::regenerateToken();
                    } catch (Exception $e) {
                        $message = 'Error updating ticket: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;
            
            case 'delete_ticket':
                if (!\App\Auth::can('tickets.delete')) {
                    $message = 'You do not have permission to delete tickets.';
                    $messageType = 'danger';
                    break;
                }
                $ticketId = (int)($_POST['id'] ?? 0);
                if ($ticketId) {
                    try {
                        if ($ticket->delete($ticketId)) {
                            $message = 'Ticket deleted successfully.';
                            $messageType = 'success';
                            header('Location: ?page=tickets&deleted=1');
                            exit;
                        } else {
                            $message = 'Failed to delete ticket.';
                            $messageType = 'danger';
                        }
                        \App\Auth::regenerateToken();
                    } catch (Exception $e) {
                        $message = 'Error deleting ticket: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'add_comment':
                $comment = trim($_POST['comment'] ?? '');
                if (empty($comment)) {
                    $message = 'Comment cannot be empty.';
                    $messageType = 'danger';
                } else {
                    try {
                        $ticket->addComment(
                            (int)$_POST['ticket_id'], 
                            $currentUser['id'], 
                            $comment, 
                            isset($_POST['is_internal'])
                        );
                        $message = 'Comment added successfully!';
                        $messageType = 'success';
                        \App\Auth::regenerateToken();
                    } catch (Exception $e) {
                        $message = 'Error adding comment: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;
            
            case 'repost_to_whatsapp':
                if (!\App\Auth::can('tickets.view')) {
                    $message = 'You do not have permission to repost tickets.';
                    $messageType = 'danger';
                    break;
                }
                $ticketId = (int)($_POST['ticket_id'] ?? 0);
                if ($ticketId <= 0) {
                    $message = 'Invalid ticket ID.';
                    $messageType = 'danger';
                    break;
                }
                try {
                    $ticketData = $ticket->find($ticketId);
                    if (!$ticketData) {
                        throw new Exception('Ticket not found.');
                    }
                    
                    $whatsapp = new \App\WhatsApp();
                    if (!$whatsapp->isEnabled() || $whatsapp->getProvider() !== 'session') {
                        throw new Exception('WhatsApp session provider is required for group messaging.');
                    }
                    
                    $customerData = $customer->find($ticketData['customer_id']);
                    $assignedName = $ticketData['assigned_to'] ? (\App\User::find($ticketData['assigned_to'])['name'] ?? 'Unassigned') : 'Unassigned';
                    $age = floor((time() - strtotime($ticketData['created_at'])) / 86400);
                    
                    $serviceFeeModel = new \App\ServiceFee($db);
                    $ticketFees = $serviceFeeModel->getTicketFees($ticketId);
                    $feesTotal = $serviceFeeModel->getTicketFeesTotal($ticketId);
                    
                    $priorityEmoji = match($ticketData['priority']) {
                        'critical' => '🔴',
                        'high' => '🟠',
                        'medium' => '🟡',
                        default => '🟢'
                    };
                    
                    $repostMessage = "*{$priorityEmoji} TICKET REPOST*\n\n";
                    $repostMessage .= "*#{$ticketData['ticket_number']}*: {$ticketData['subject']}\n";
                    $repostMessage .= "Priority: " . ucfirst($ticketData['priority']) . " | Status: " . ucwords(str_replace('_', ' ', $ticketData['status'])) . "\n";
                    $repostMessage .= "Category: " . ucfirst($ticketData['category']) . "\n";
                    $repostMessage .= "Customer: " . ($customerData['name'] ?? 'Unknown') . "\n";
                    if (!empty($customerData['phone'])) {
                        $repostMessage .= "Phone: {$customerData['phone']}\n";
                    }
                    $repostMessage .= "Assigned: {$assignedName}\n";
                    $repostMessage .= "Age: {$age} days\n";
                    
                    if (count($ticketFees) > 0) {
                        $repostMessage .= "\n*Service Fees:*\n";
                        foreach ($ticketFees as $fee) {
                            $paidStatus = $fee['is_paid'] ? '✅' : '⏳';
                            $repostMessage .= "• {$fee['fee_name']}: " . number_format($fee['amount'], 2) . " {$fee['currency']} {$paidStatus}\n";
                        }
                        $repostMessage .= "Total: " . number_format($feesTotal['total'], 2) . " (Paid: " . number_format($feesTotal['paid'], 2) . ")\n";
                    }
                    
                    $repostMessage .= "\n_Reposted at " . date('h:i A, M j') . "_";
                    
                    $results = [];
                    $errors = [];
                    
                    if (!empty($ticketData['branch_id'])) {
                        $branchClass = new \App\Branch();
                        $branch = $branchClass->find($ticketData['branch_id']);
                        if ($branch && !empty($branch['whatsapp_group_id'])) {
                            $result = $whatsapp->sendToGroup($branch['whatsapp_group_id'], $repostMessage);
                            $results['branch'] = $result;
                            if (!$result['success']) {
                                $errors[] = "Branch group: " . ($result['error'] ?? 'Unknown error');
                            }
                        }
                    }
                    
                    $settings = new \App\Settings($db);
                    $operationsGroupId = $settings->get('whatsapp_operations_group_id', '');
                    if (!empty($operationsGroupId)) {
                        $result = $whatsapp->sendToGroup($operationsGroupId, $repostMessage);
                        $results['operations'] = $result;
                        if (!$result['success']) {
                            $errors[] = "Operations group: " . ($result['error'] ?? 'Unknown error');
                        }
                    }
                    
                    if (empty($results)) {
                        throw new Exception('No WhatsApp groups configured for this ticket.');
                    }
                    
                    if (count($errors) > 0) {
                        $message = 'Ticket reposted with some errors: ' . implode('; ', $errors);
                        $messageType = 'warning';
                    } else {
                        $message = 'Ticket reposted to WhatsApp successfully!';
                        $messageType = 'success';
                    }
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error reposting ticket: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;
            
            case 'quick_status_change':
                $ticketId = (int)($_POST['ticket_id'] ?? 0);
                $newStatus = $_POST['new_status'] ?? '';
                if ($ticketId && $newStatus) {
                    try {
                        $result = $ticket->quickStatusChange($ticketId, $newStatus, $currentUser['id']);
                        \App\Auth::regenerateToken();
                        if ($result) {
                            $_SESSION['flash_message'] = 'Status changed to ' . ucwords(str_replace('_', ' ', $newStatus)) . ' successfully!';
                            $_SESSION['flash_type'] = 'success';
                        } else {
                            $_SESSION['flash_message'] = 'Failed to change status.';
                            $_SESSION['flash_type'] = 'danger';
                        }
                    } catch (Exception $e) {
                        $_SESSION['flash_message'] = 'Error: ' . $e->getMessage();
                        $_SESSION['flash_type'] = 'danger';
                    }
                    header('Location: ?page=tickets&action=view&id=' . $ticketId);
                    exit;
                }
                break;
            
            case 'resolve_ticket':
                $ticketId = (int)($_POST['ticket_id'] ?? 0);
                $resolutionNotes = trim($_POST['resolution_notes'] ?? '');
                
                if ($ticketId && $resolutionNotes) {
                    try {
                        $pdo = getDbConnection();
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
                            $currentUser['id'],
                            $resolutionNotes,
                            trim($_POST['router_serial'] ?? ''),
                            trim($_POST['power_levels'] ?? ''),
                            trim($_POST['cable_used'] ?? ''),
                            trim($_POST['equipment_installed'] ?? ''),
                            trim($_POST['additional_notes'] ?? '')
                        ]);
                        $resolutionId = $stmt->fetchColumn();
                        
                        $uploadDir = __DIR__ . '/uploads/ticket_resolutions/';
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0755, true);
                        }
                        
                        $photoTypes = ['photo_serial' => 'serial', 'photo_power' => 'power_levels', 'photo_cables' => 'cables', 'photo_additional' => 'additional'];
                        
                        $maxFileSize = 10 * 1024 * 1024; // 10MB limit
                        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                        $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                        
                        foreach ($photoTypes as $fieldName => $photoType) {
                            if (!empty($_FILES[$fieldName]['name']) && $_FILES[$fieldName]['error'] === UPLOAD_ERR_OK) {
                                $tmpFile = $_FILES[$fieldName]['tmp_name'];
                                $fileSize = $_FILES[$fieldName]['size'];
                                $ext = strtolower(pathinfo($_FILES[$fieldName]['name'], PATHINFO_EXTENSION));
                                
                                if ($fileSize > $maxFileSize) {
                                    continue; // Skip files exceeding size limit
                                }
                                
                                if (!in_array($ext, $allowedExts)) {
                                    continue; // Skip invalid extensions
                                }
                                
                                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                                $mimeType = finfo_file($finfo, $tmpFile);
                                finfo_close($finfo);
                                
                                if (!in_array($mimeType, $allowedMimes)) {
                                    continue; // Skip files with invalid MIME type
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
                                    $stmt->execute([$ticketId, $resolutionId, $photoType, $filePath, $_FILES[$fieldName]['name'], $currentUser['id']]);
                                }
                            }
                        }
                        
                        $ticket->quickStatusChange($ticketId, 'resolved', $currentUser['id']);
                        
                        $pdo->commit();
                        \App\Auth::regenerateToken();
                        
                        $_SESSION['flash_message'] = 'Ticket resolved successfully with documentation!';
                        $_SESSION['flash_type'] = 'success';
                    } catch (Exception $e) {
                        if (isset($pdo)) {
                            $pdo->rollBack();
                        }
                        $_SESSION['flash_message'] = 'Error resolving ticket: ' . $e->getMessage();
                        $_SESSION['flash_type'] = 'danger';
                    }
                    header('Location: ?page=tickets&action=view&id=' . $ticketId);
                    exit;
                } else {
                    $_SESSION['flash_message'] = 'Resolution notes are required.';
                    $_SESSION['flash_type'] = 'danger';
                    header('Location: ?page=tickets&action=view&id=' . $ticketId);
                    exit;
                }
                break;
            
            case 'quick_resolve_ticket':
                // Admin/Support quick resolve - no form required
                if (!\App\Auth::isAdmin() && !\App\Auth::hasRole('support')) {
                    $_SESSION['flash_message'] = 'Only administrators and support staff can use quick resolve.';
                    $_SESSION['flash_type'] = 'danger';
                    header('Location: ?page=tickets');
                    exit;
                }
                $ticketId = (int)($_POST['ticket_id'] ?? 0);
                if ($ticketId) {
                    try {
                        // Update ticket status to resolved
                        $stmt = $db->prepare("
                            UPDATE tickets 
                            SET status = 'resolved', 
                                resolved_by = ?, 
                                resolved_at = CURRENT_TIMESTAMP,
                                updated_at = CURRENT_TIMESTAMP 
                            WHERE id = ?
                        ");
                        $stmt->execute([$currentUser['id'], $ticketId]);
                        
                        // Log the activity
                        $stmt = $db->prepare("
                            INSERT INTO ticket_activity 
                            (ticket_id, user_id, action, details, created_at) 
                            VALUES (?, ?, 'status_change', ?, CURRENT_TIMESTAMP)
                        ");
                        $stmt->execute([
                            $ticketId, 
                            $currentUser['id'], 
                            json_encode(['from' => 'open', 'to' => 'resolved', 'type' => 'admin_quick_resolve'])
                        ]);
                        
                        $_SESSION['flash_message'] = 'Ticket resolved successfully.';
                        $_SESSION['flash_type'] = 'success';
                    } catch (Exception $e) {
                        $_SESSION['flash_message'] = 'Error resolving ticket: ' . $e->getMessage();
                        $_SESSION['flash_type'] = 'danger';
                    }
                    header('Location: ?page=tickets&action=view&id=' . $ticketId);
                    exit;
                }
                break;
            
            case 'escalate_ticket':
                $ticketId = (int)($_POST['ticket_id'] ?? 0);
                $reason = trim($_POST['reason'] ?? '');
                if ($ticketId && $reason) {
                    try {
                        $result = $ticket->escalate($ticketId, $currentUser['id'], [
                            'reason' => $reason,
                            'escalated_to' => !empty($_POST['escalated_to']) ? (int)$_POST['escalated_to'] : null,
                            'new_priority' => $_POST['new_priority'] ?? null
                        ]);
                        if ($result) {
                            $message = 'Ticket escalated successfully! Assigned technician has been notified.';
                            $messageType = 'success';
                        } else {
                            $message = 'Failed to escalate ticket.';
                            $messageType = 'danger';
                        }
                        \App\Auth::regenerateToken();
                    } catch (Exception $e) {
                        $message = 'Error: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                } else {
                    $message = 'Please provide a reason for escalation.';
                    $messageType = 'danger';
                }
                break;
            
            case 'submit_rating':
                $ticketId = (int)($_POST['ticket_id'] ?? 0);
                $rating = (int)($_POST['rating'] ?? 0);
                if ($ticketId && $rating >= 1 && $rating <= 5) {
                    try {
                        $result = $ticket->submitSatisfactionRating($ticketId, [
                            'rating' => $rating,
                            'feedback' => $_POST['feedback'] ?? null,
                            'rated_by_name' => $currentUser['name'] ?? null
                        ]);
                        if ($result) {
                            $message = 'Customer satisfaction rating submitted successfully!';
                            $messageType = 'success';
                        } else {
                            $message = 'Failed to submit rating.';
                            $messageType = 'danger';
                        }
                        \App\Auth::regenerateToken();
                    } catch (Exception $e) {
                        $message = 'Error: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                } else {
                    $message = 'Please select a valid rating (1-5 stars).';
                    $messageType = 'danger';
                }
                break;
                
            case 'create_employee':
                $name = trim($_POST['name'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $position = trim($_POST['position'] ?? '');
                
                if (empty($name) || empty($phone) || empty($position)) {
                    $message = 'Please fill in all required fields.';
                    $messageType = 'danger';
                } else {
                    try {
                        $data = $_POST;
                        if (!empty($_FILES['passport_photo']['name']) && $_FILES['passport_photo']['error'] === UPLOAD_ERR_OK) {
                            $uploadDir = __DIR__ . '/uploads/employees/';
                            if (!is_dir($uploadDir)) {
                                mkdir($uploadDir, 0755, true);
                            }
                            $ext = pathinfo($_FILES['passport_photo']['name'], PATHINFO_EXTENSION);
                            $filename = 'passport_' . uniqid() . '.' . $ext;
                            $targetPath = $uploadDir . $filename;
                            if (move_uploaded_file($_FILES['passport_photo']['tmp_name'], $targetPath)) {
                                $data['passport_photo'] = '/uploads/employees/' . $filename;
                            }
                        }
                        $employee->create($data);
                        $message = 'Employee added successfully!';
                        $messageType = 'success';
                        \App\Auth::regenerateToken();
                    } catch (Exception $e) {
                        $message = 'Error adding employee: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'update_employee':
                $name = trim($_POST['name'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $position = trim($_POST['position'] ?? '');
                
                if (empty($name) || empty($phone) || empty($position)) {
                    $message = 'Please fill in all required fields.';
                    $messageType = 'danger';
                } else {
                    try {
                        $data = $_POST;
                        if (!empty($_FILES['passport_photo']['name']) && $_FILES['passport_photo']['error'] === UPLOAD_ERR_OK) {
                            $uploadDir = __DIR__ . '/uploads/employees/';
                            if (!is_dir($uploadDir)) {
                                mkdir($uploadDir, 0755, true);
                            }
                            $ext = pathinfo($_FILES['passport_photo']['name'], PATHINFO_EXTENSION);
                            $filename = 'passport_' . uniqid() . '.' . $ext;
                            $targetPath = $uploadDir . $filename;
                            if (move_uploaded_file($_FILES['passport_photo']['tmp_name'], $targetPath)) {
                                $data['passport_photo'] = '/uploads/employees/' . $filename;
                            }
                        }
                        $employee->update((int)$_POST['id'], $data);
                        $message = 'Employee updated successfully!';
                        $messageType = 'success';
                        \App\Auth::regenerateToken();
                    } catch (Exception $e) {
                        $message = 'Error updating employee: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'delete_employee':
                if (!\App\Auth::isAdmin()) {
                    $message = 'Only administrators can delete employees.';
                    $messageType = 'danger';
                } else {
                    try {
                        $employee->delete((int)$_POST['id']);
                        $message = 'Employee deleted successfully!';
                        $messageType = 'success';
                        \App\Auth::regenerateToken();
                    } catch (Exception $e) {
                        $message = 'Error deleting employee: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'upload_kyc_document':
                $employeeId = (int)($_POST['employee_id'] ?? 0);
                $documentType = trim($_POST['document_type'] ?? '');
                
                if (!$employeeId || !$documentType || empty($_FILES['kyc_document']['name'])) {
                    $message = 'Please select a document type and file.';
                    $messageType = 'danger';
                } else {
                    try {
                        $uploadDir = __DIR__ . '/uploads/kyc/';
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0755, true);
                        }
                        
                        $ext = pathinfo($_FILES['kyc_document']['name'], PATHINFO_EXTENSION);
                        $filename = 'kyc_' . $employeeId . '_' . $documentType . '_' . uniqid() . '.' . $ext;
                        $targetPath = $uploadDir . $filename;
                        
                        if (move_uploaded_file($_FILES['kyc_document']['tmp_name'], $targetPath)) {
                            $employee->addKycDocument($employeeId, [
                                'document_type' => $documentType,
                                'document_name' => $_FILES['kyc_document']['name'],
                                'file_path' => '/uploads/kyc/' . $filename,
                                'notes' => $_POST['notes'] ?? null
                            ]);
                            $message = 'KYC document uploaded successfully!';
                            $messageType = 'success';
                        } else {
                            $message = 'Failed to upload file.';
                            $messageType = 'danger';
                        }
                        \App\Auth::regenerateToken();
                    } catch (Exception $e) {
                        $message = 'Error uploading document: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'delete_kyc_document':
                $documentId = (int)($_POST['document_id'] ?? 0);
                if ($documentId) {
                    try {
                        $employee->deleteKycDocument($documentId);
                        $message = 'KYC document deleted successfully!';
                        $messageType = 'success';
                        \App\Auth::regenerateToken();
                    } catch (Exception $e) {
                        $message = 'Error deleting document: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'verify_kyc_document':
                $documentId = (int)($_POST['document_id'] ?? 0);
                if ($documentId) {
                    try {
                        $employee->verifyKycDocument($documentId, $currentUser['id']);
                        $message = 'Document verified successfully!';
                        $messageType = 'success';
                        \App\Auth::regenerateToken();
                    } catch (Exception $e) {
                        $message = 'Error verifying document: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'create_department':
                $name = trim($_POST['name'] ?? '');
                if (empty($name)) {
                    $message = 'Department name is required.';
                    $messageType = 'danger';
                } else {
                    try {
                        $employee->createDepartment($_POST);
                        $message = 'Department created successfully!';
                        $messageType = 'success';
                        \App\Auth::regenerateToken();
                    } catch (Exception $e) {
                        $message = 'Error creating department: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'update_department':
                $name = trim($_POST['name'] ?? '');
                if (empty($name)) {
                    $message = 'Department name is required.';
                    $messageType = 'danger';
                } else {
                    try {
                        $employee->updateDepartment((int)$_POST['id'], $_POST);
                        $message = 'Department updated successfully!';
                        $messageType = 'success';
                        \App\Auth::regenerateToken();
                    } catch (Exception $e) {
                        $message = 'Error updating department: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'delete_department':
                if (!\App\Auth::isAdmin()) {
                    $message = 'Only administrators can delete departments.';
                    $messageType = 'danger';
                } else {
                    try {
                        $employee->deleteDepartment((int)$_POST['id']);
                        $message = 'Department deleted successfully!';
                        $messageType = 'success';
                        \App\Auth::regenerateToken();
                    } catch (Exception $e) {
                        $message = 'Error deleting department: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;

            case 'record_attendance':
                try {
                    $employee->recordAttendance($_POST);
                    $message = 'Attendance recorded successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error recording attendance: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'update_attendance':
                try {
                    $employee->updateAttendance((int)$_POST['id'], $_POST);
                    $message = 'Attendance updated successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error updating attendance: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'delete_attendance':
                if (!\App\Auth::isAdmin()) {
                    $message = 'Only administrators can delete attendance records.';
                    $messageType = 'danger';
                } else {
                    try {
                        $employee->deleteAttendance((int)$_POST['id']);
                        $message = 'Attendance record deleted successfully!';
                        $messageType = 'success';
                        \App\Auth::regenerateToken();
                    } catch (Exception $e) {
                        $message = 'Error deleting attendance: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;
            
            case 'toggle_late_penalties':
                try {
                    $enabled = $_POST['enabled'] === '1' ? '1' : '0';
                    $settings->set('late_penalties_enabled', $enabled);
                    \App\Settings::clearCache();
                    $message = $enabled === '1' ? 'Late arrival penalties enabled.' : 'Late arrival penalties disabled.';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;
            
            case 'update_absent_rule':
                try {
                    $cutoffTime = $_POST['cutoff_time'] ?? '10:00';
                    $deductionType = $_POST['deduction_type'] ?? 'daily_rate';
                    $deductionAmount = (float)($_POST['deduction_amount'] ?? 0);
                    $applyAutomatically = isset($_POST['apply_automatically']) ? true : false;
                    
                    $stmt = $db->prepare("
                        UPDATE absent_deduction_rules 
                        SET cutoff_time = ?, deduction_type = ?, deduction_amount = ?, apply_automatically = ?, updated_at = NOW()
                        WHERE is_active = TRUE
                    ");
                    $stmt->execute([$cutoffTime, $deductionType, $deductionAmount, $applyAutomatically]);
                    
                    $message = 'Absenteeism deduction settings updated successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;
            
            case 'remove_late_penalty':
                try {
                    $attendanceId = (int)$_POST['attendance_id'];
                    $stmt = $db->prepare("UPDATE attendance SET late_minutes = 0, deduction = 0 WHERE id = ?");
                    $stmt->execute([$attendanceId]);
                    $message = 'Late penalty removed successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;
            
            case 'update_late_penalty':
                try {
                    $attendanceId = (int)$_POST['attendance_id'];
                    $lateMinutes = max(0, (int)$_POST['late_minutes']);
                    $deduction = max(0, (float)$_POST['deduction']);
                    $reason = trim($_POST['adjustment_reason'] ?? '');
                    
                    $stmt = $db->prepare("UPDATE attendance SET late_minutes = ?, deduction = ? WHERE id = ?");
                    $stmt->execute([$lateMinutes, $deduction, $attendanceId]);
                    
                    if (!empty($reason)) {
                        \App\ActivityLog::log($db, 'attendance', $attendanceId, 'penalty_adjusted', 
                            "Late penalty adjusted: {$lateMinutes} min, KES {$deduction}. Reason: {$reason}");
                    }
                    
                    $message = 'Late penalty updated successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'create_payroll':
                try {
                    $payrollId = $employee->createPayroll($_POST);
                    $additions = [];
                    
                    if ($payrollId && !empty($_POST['employee_id']) && !empty($_POST['pay_period_start'])) {
                        $payrollDb = Database::getConnection();
                        $payPeriodMonth = date('Y-m', strtotime($_POST['pay_period_start']));
                        
                        if (!empty($_POST['include_late_deductions'])) {
                            $lateCalculator = new \App\LateDeductionCalculator($payrollDb);
                            $lateCalculator->applyDeductionsToPayroll($payrollId, (int)$_POST['employee_id'], $payPeriodMonth);
                            $additions[] = 'late deductions';
                        }
                        
                        if (!empty($_POST['include_ticket_commissions'])) {
                            $ticketCommission = new \App\TicketCommission($payrollDb);
                            $ticketCommission->applyToPayroll($payrollId, (int)$_POST['employee_id'], $payPeriodMonth);
                            $additions[] = 'ticket commissions';
                        }
                        
                        if (!empty($_POST['include_advance_deductions'])) {
                            $salaryAdvance = new \App\SalaryAdvance($payrollDb);
                            $activeAdvances = $salaryAdvance->getEmployeeActiveAdvances((int)$_POST['employee_id']);
                            foreach ($activeAdvances as $advance) {
                                if (in_array($advance['status'], ['disbursed', 'repaying']) && $advance['balance'] > 0) {
                                    $deductionAmount = min($advance['repayment_amount'], $advance['balance']);
                                    $salaryAdvance->recordPayment($advance['id'], [
                                        'amount' => $deductionAmount,
                                        'payment_type' => 'payroll_deduction',
                                        'payment_date' => date('Y-m-d'),
                                        'payroll_id' => $payrollId,
                                        'recorded_by' => $currentUser['id']
                                    ]);
                                }
                            }
                            $additions[] = 'advance deductions';
                        }
                    }
                    
                    if (!empty($additions)) {
                        $message = 'Payroll record created with ' . implode(', ', $additions) . ' applied!';
                    } else {
                        $message = 'Payroll record created successfully!';
                    }
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error creating payroll: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'update_payroll':
                try {
                    $employee->updatePayroll((int)$_POST['id'], $_POST);
                    $message = 'Payroll record updated successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error updating payroll: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'delete_payroll':
                if (!\App\Auth::isAdmin()) {
                    $message = 'Only administrators can delete payroll records.';
                    $messageType = 'danger';
                } else {
                    try {
                        $employee->deletePayroll((int)$_POST['id']);
                        $message = 'Payroll record deleted successfully!';
                        $messageType = 'success';
                        \App\Auth::regenerateToken();
                    } catch (Exception $e) {
                        $message = 'Error deleting payroll: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;

            case 'bulk_generate_payroll':
                if (!\App\Auth::isAdmin()) {
                    $message = 'Only administrators can generate bulk payroll.';
                    $messageType = 'danger';
                } else {
                    try {
                        $payPeriodStart = $_POST['pay_period_start'];
                        $payPeriodEnd = $_POST['pay_period_end'];
                        
                        $results = $employee->generateBulkPayroll($payPeriodStart, $payPeriodEnd);
                        
                        $payrollDb = Database::getConnection();
                        $payPeriodMonth = date('Y-m', strtotime($payPeriodStart));
                        $additionsApplied = [];
                        
                        foreach ($results['payroll_ids'] as $empId => $payrollId) {
                            if (!empty($_POST['include_late_deductions'])) {
                                $lateCalculator = new \App\LateDeductionCalculator($payrollDb);
                                $lateCalculator->applyDeductionsToPayroll($payrollId, $empId, $payPeriodMonth);
                            }
                            
                            if (!empty($_POST['include_ticket_commissions'])) {
                                $ticketCommission = new \App\TicketCommission($payrollDb);
                                $ticketCommission->applyToPayroll($payrollId, $empId, $payPeriodMonth);
                            }
                            
                            if (!empty($_POST['include_advance_deductions'])) {
                                $salaryAdvance = new \App\SalaryAdvance($payrollDb);
                                $activeAdvances = $salaryAdvance->getEmployeeActiveAdvances($empId);
                                foreach ($activeAdvances as $advance) {
                                    if (in_array($advance['status'], ['disbursed', 'repaying']) && $advance['balance'] > 0) {
                                        $deductionAmount = min($advance['repayment_amount'], $advance['balance']);
                                        $salaryAdvance->recordPayment($advance['id'], [
                                            'amount' => $deductionAmount,
                                            'payment_type' => 'payroll_deduction',
                                            'payment_date' => date('Y-m-d'),
                                            'payroll_id' => $payrollId,
                                            'recorded_by' => $currentUser['id']
                                        ]);
                                    }
                                }
                            }
                        }
                        
                        if (!empty($_POST['include_late_deductions'])) $additionsApplied[] = 'late deductions';
                        if (!empty($_POST['include_ticket_commissions'])) $additionsApplied[] = 'ticket commissions';
                        if (!empty($_POST['include_advance_deductions'])) $additionsApplied[] = 'advance deductions';
                        
                        $message = "Bulk payroll generated: {$results['success']} created, {$results['skipped']} skipped (already exist).";
                        if (!empty($additionsApplied)) {
                            $message .= ' Applied: ' . implode(', ', $additionsApplied) . '.';
                        }
                        if (!empty($results['errors'])) {
                            $message .= ' Errors: ' . count($results['errors']);
                        }
                        $messageType = 'success';
                        \App\Auth::regenerateToken();
                    } catch (Exception $e) {
                        $message = 'Error generating bulk payroll: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;

            case 'create_performance':
                try {
                    $employee->createPerformanceReview($_POST);
                    $message = 'Performance review created successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error creating performance review: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'update_performance':
                try {
                    $employee->updatePerformanceReview((int)$_POST['id'], $_POST);
                    $message = 'Performance review updated successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error updating performance review: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'delete_performance':
                if (!\App\Auth::isAdmin()) {
                    $message = 'Only administrators can delete performance reviews.';
                    $messageType = 'danger';
                } else {
                    try {
                        $employee->deletePerformanceReview((int)$_POST['id']);
                        $message = 'Performance review deleted successfully!';
                        $messageType = 'success';
                        \App\Auth::regenerateToken();
                    } catch (Exception $e) {
                        $message = 'Error deleting performance review: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;

            case 'create_advance':
                try {
                    $salaryAdvance = new \App\SalaryAdvance(Database::getConnection());
                    $advanceId = $salaryAdvance->create($_POST);
                    $advance = $salaryAdvance->getById($advanceId);
                    
                    $hrNotification = new \App\HRNotification(Database::getConnection());
                    $hrNotification->sendAdvanceNotification('advance_request_created', $advance);
                    $hrNotification->notifyAdminsOfAdvanceRequest($advance);
                    
                    $message = 'Salary advance request created successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error creating advance: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'approve_advance':
                try {
                    $salaryAdvance = new \App\SalaryAdvance(Database::getConnection());
                    $salaryAdvance->approve((int)$_POST['id'], $currentUser['id']);
                    $advance = $salaryAdvance->getById((int)$_POST['id']);
                    
                    $hrNotification = new \App\HRNotification(Database::getConnection());
                    $hrNotification->sendAdvanceNotification('advance_approved', $advance);
                    $hrNotification->notifyEmployeeOfAdvanceDecision($advance);
                    
                    $message = 'Salary advance approved successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error approving advance: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'reject_advance':
                try {
                    $salaryAdvance = new \App\SalaryAdvance(Database::getConnection());
                    $salaryAdvance->reject((int)$_POST['id'], $currentUser['id'], $_POST['notes'] ?? null);
                    $advance = $salaryAdvance->getById((int)$_POST['id']);
                    
                    $hrNotification = new \App\HRNotification(Database::getConnection());
                    $hrNotification->sendAdvanceNotification('advance_rejected', $advance);
                    $hrNotification->notifyEmployeeOfAdvanceDecision($advance);
                    
                    $message = 'Salary advance rejected.';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error rejecting advance: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'disburse_advance':
                try {
                    $salaryAdvance = new \App\SalaryAdvance(Database::getConnection());
                    $salaryAdvance->disburse((int)$_POST['id']);
                    $advance = $salaryAdvance->getById((int)$_POST['id']);
                    
                    $hrNotification = new \App\HRNotification(Database::getConnection());
                    $hrNotification->sendAdvanceNotification('advance_disbursed', $advance);
                    
                    $message = 'Salary advance disbursed successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error disbursing advance: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;
            
            case 'disburse_advance_mpesa':
                try {
                    require_once __DIR__ . '/../src/Mpesa.php';
                    $mpesa = new \App\Mpesa();
                    
                    if (!$mpesa->isB2CConfigured()) {
                        throw new Exception('M-Pesa B2C is not configured. Please configure it in M-Pesa settings first.');
                    }
                    
                    $salaryAdvance = new \App\SalaryAdvance(Database::getConnection());
                    $advance = $salaryAdvance->getById((int)$_POST['id']);
                    
                    if (!$advance) {
                        throw new Exception('Salary advance not found.');
                    }
                    
                    if ($advance['status'] !== 'approved') {
                        throw new Exception('Only approved advances can be disbursed.');
                    }
                    
                    $empStmt = Database::getConnection()->prepare("SELECT phone FROM employees WHERE id = ?");
                    $empStmt->execute([$advance['employee_id']]);
                    $employee = $empStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$employee || empty($employee['phone'])) {
                        throw new Exception('Employee phone number not found.');
                    }
                    
                    $result = $mpesa->b2cPayment(
                        $employee['phone'],
                        (float)$advance['amount'],
                        'SalaryPayment',
                        'Salary Advance #' . $advance['id'],
                        'Advance',
                        'advance',
                        (int)$advance['id'],
                        'salary_advance',
                        $currentUser['id'] ?? null
                    );
                    
                    if ($result['success']) {
                        $salaryAdvance->disburse((int)$_POST['id']);
                        
                        $updateStmt = Database::getConnection()->prepare("
                            UPDATE salary_advances 
                            SET mpesa_b2c_transaction_id = ?, disbursement_status = 'processing'
                            WHERE id = ?
                        ");
                        $updateStmt->execute([$result['transaction_id'], $advance['id']]);
                        
                        $hrNotification = new \App\HRNotification(Database::getConnection());
                        $hrNotification->sendAdvanceNotification('advance_disbursed', $advance);
                        
                        $message = 'M-Pesa disbursement initiated! ' . ($result['conversation_id'] ?? '');
                        $messageType = 'success';
                    } else {
                        throw new Exception($result['message']);
                    }
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'M-Pesa disbursement failed: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'record_advance_payment':
                try {
                    $salaryAdvance = new \App\SalaryAdvance(Database::getConnection());
                    $salaryAdvance->recordPayment((int)$_POST['advance_id'], [
                        'amount' => $_POST['amount'],
                        'payment_type' => $_POST['payment_type'] ?? 'payroll_deduction',
                        'payment_date' => $_POST['payment_date'] ?? date('Y-m-d'),
                        'reference_number' => $_POST['reference_number'] ?? null,
                        'recorded_by' => $currentUser['id']
                    ]);
                    $message = 'Payment recorded successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error recording payment: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'create_leave_request':
                try {
                    $leaveService = new \App\Leave(Database::getConnection());
                    $requestId = $leaveService->createRequest($_POST);
                    $request = $leaveService->getRequest($requestId);
                    
                    $hrNotification = new \App\HRNotification(Database::getConnection());
                    $hrNotification->sendLeaveNotification('leave_request_created', $request);
                    $hrNotification->notifyAdminsOfLeaveRequest($request);
                    
                    $message = 'Leave request submitted successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error submitting leave request: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'approve_leave':
                try {
                    $leaveService = new \App\Leave(Database::getConnection());
                    $leaveService->approve((int)$_POST['id'], $currentUser['id']);
                    $request = $leaveService->getRequest((int)$_POST['id']);
                    
                    $hrNotification = new \App\HRNotification(Database::getConnection());
                    $hrNotification->sendLeaveNotification('leave_approved', $request);
                    $hrNotification->notifyEmployeeOfLeaveDecision($request);
                    
                    $message = 'Leave request approved!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error approving leave: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'reject_leave':
                try {
                    $leaveService = new \App\Leave(Database::getConnection());
                    $leaveService->reject((int)$_POST['id'], $currentUser['id'], $_POST['rejection_reason'] ?? null);
                    $request = $leaveService->getRequest((int)$_POST['id']);
                    
                    $hrNotification = new \App\HRNotification(Database::getConnection());
                    $hrNotification->sendLeaveNotification('leave_rejected', $request);
                    $hrNotification->notifyEmployeeOfLeaveDecision($request);
                    
                    $message = 'Leave request rejected.';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error rejecting leave: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'create_leave_type':
                try {
                    $leaveService = new \App\Leave(Database::getConnection());
                    $leaveService->createLeaveType($_POST);
                    $message = 'Leave type created successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error creating leave type: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'add_holiday':
                try {
                    $leaveService = new \App\Leave(Database::getConnection());
                    $leaveService->addPublicHoliday($_POST['date'], $_POST['name'], $_POST['branch_id'] ?? null);
                    $message = 'Holiday added successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error adding holiday: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'create_announcement':
                try {
                    $announcementClass = new \App\Announcement($db);
                    $status = ($_POST['save_as'] ?? 'draft') === 'send' ? 'sent' : 'draft';
                    $announcementId = $announcementClass->create([
                        'title' => $_POST['title'],
                        'message' => $_POST['message'],
                        'priority' => $_POST['priority'] ?? 'normal',
                        'target_audience' => $_POST['target_audience'] ?? 'all',
                        'target_branch_id' => $_POST['target_branch_id'] ?: null,
                        'target_team_id' => $_POST['target_team_id'] ?: null,
                        'send_sms' => isset($_POST['send_sms']),
                        'send_notification' => isset($_POST['send_notification']),
                        'status' => 'draft',
                        'created_by' => $currentUser['id']
                    ]);
                    
                    if ($status === 'sent') {
                        $result = $announcementClass->send($announcementId);
                        $message = 'Announcement created and sent! SMS: ' . $result['sms_sent'] . ', Notifications: ' . $result['notifications_sent'];
                    } else {
                        $message = 'Announcement saved as draft.';
                    }
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error creating announcement: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'send_announcement':
                try {
                    $announcementClass = new \App\Announcement($db);
                    $result = $announcementClass->send((int)$_POST['announcement_id']);
                    if ($result['success']) {
                        $message = 'Announcement sent! SMS: ' . $result['sms_sent'] . ', Notifications: ' . $result['notifications_sent'];
                        $messageType = 'success';
                    } else {
                        $message = 'Error sending announcement: ' . implode(', ', $result['errors']);
                        $messageType = 'danger';
                    }
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error sending announcement: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'delete_announcement':
                try {
                    $announcementClass = new \App\Announcement($db);
                    $announcementClass->delete((int)$_POST['announcement_id']);
                    $message = 'Announcement deleted.';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error deleting announcement: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'add_ticket_service_fee':
                try {
                    $serviceFee = new \App\ServiceFee($db);
                    $serviceFee->addTicketFee((int)$_POST['ticket_id'], [
                        'fee_type_id' => $_POST['fee_type_id'] ?: null,
                        'fee_name' => $_POST['fee_name'],
                        'amount' => $_POST['amount'],
                        'notes' => $_POST['notes'] ?? null,
                        'created_by' => $currentUser['id']
                    ]);
                    $message = 'Service fee added to ticket.';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error adding service fee: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'mark_fee_paid':
                try {
                    $serviceFee = new \App\ServiceFee($db);
                    $serviceFee->markAsPaid((int)$_POST['fee_id'], $_POST['payment_reference'] ?? null);
                    $message = 'Service fee marked as paid.';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error updating fee: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'delete_ticket_fee':
                try {
                    $serviceFee = new \App\ServiceFee($db);
                    $serviceFee->deleteTicketFee((int)$_POST['fee_id']);
                    $message = 'Service fee removed.';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error removing fee: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'create_invoice':
            case 'update_invoice':
                if (!\App\Auth::can('settings.view')) {
                    $message = 'You do not have permission to manage invoices.';
                    $messageType = 'danger';
                    break;
                }
                try {
                    $accounting = new \App\Accounting(Database::getConnection());
                    $items = $_POST['items'] ?? [];
                    
                    if ($action === 'create_invoice') {
                        $invoiceId = $accounting->createInvoice([
                            'customer_id' => $_POST['customer_id'] ?? null,
                            'issue_date' => $_POST['issue_date'],
                            'due_date' => $_POST['due_date'],
                            'status' => $_POST['status'] ?? 'draft',
                            'subtotal' => $_POST['subtotal'],
                            'tax_amount' => $_POST['tax_amount'],
                            'total_amount' => $_POST['total_amount'],
                            'notes' => $_POST['notes'] ?? null,
                            'terms' => $_POST['terms'] ?? null,
                            'created_by' => $currentUser['id']
                        ]);
                    } else {
                        $invoiceId = (int)$_POST['invoice_id'];
                        $accounting->updateInvoice($invoiceId, [
                            'customer_id' => $_POST['customer_id'] ?? null,
                            'issue_date' => $_POST['issue_date'],
                            'due_date' => $_POST['due_date'],
                            'status' => $_POST['status'],
                            'subtotal' => $_POST['subtotal'],
                            'tax_amount' => $_POST['tax_amount'],
                            'total_amount' => $_POST['total_amount'],
                            'notes' => $_POST['notes'] ?? null,
                            'terms' => $_POST['terms'] ?? null
                        ]);
                        $accounting->deleteInvoiceItems($invoiceId);
                    }
                    
                    foreach ($items as $idx => $item) {
                        if (!empty($item['description'])) {
                            $qty = (float)($item['quantity'] ?? 1);
                            $price = (float)($item['unit_price'] ?? 0);
                            $taxRateId = !empty($item['tax_rate_id']) ? (int)$item['tax_rate_id'] : null;
                            
                            $taxAmount = 0;
                            if ($taxRateId) {
                                $taxStmt = Database::getConnection()->prepare("SELECT rate FROM tax_rates WHERE id = ?");
                                $taxStmt->execute([$taxRateId]);
                                $taxRate = (float)$taxStmt->fetchColumn();
                                $taxAmount = ($qty * $price) * ($taxRate / 100);
                            }
                            
                            $accounting->addInvoiceItem($invoiceId, [
                                'product_id' => !empty($item['product_id']) ? (int)$item['product_id'] : null,
                                'description' => $item['description'],
                                'quantity' => $qty,
                                'unit_price' => $price,
                                'tax_rate_id' => $taxRateId,
                                'tax_amount' => $taxAmount,
                                'line_total' => ($qty * $price) + $taxAmount,
                                'sort_order' => $idx
                            ]);
                        }
                    }
                    
                    $accounting->recalculateInvoice($invoiceId);
                    
                    $message = $action === 'create_invoice' ? 'Invoice created successfully!' : 'Invoice updated successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                    header('Location: ?page=accounting&subpage=invoices&action=view&id=' . $invoiceId);
                    exit;
                } catch (Exception $e) {
                    $message = 'Error: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'record_customer_payment':
                if (!\App\Auth::can('settings.view')) {
                    $message = 'You do not have permission to record payments.';
                    $messageType = 'danger';
                    break;
                }
                try {
                    $accounting = new \App\Accounting(Database::getConnection());
                    $paymentId = $accounting->recordCustomerPayment([
                        'customer_id' => $_POST['customer_id'] ?? null,
                        'invoice_id' => !empty($_POST['invoice_id']) ? (int)$_POST['invoice_id'] : null,
                        'payment_date' => $_POST['payment_date'] ?? date('Y-m-d'),
                        'amount' => (float)$_POST['amount'],
                        'payment_method' => $_POST['payment_method'],
                        'reference' => $_POST['reference'] ?? null,
                        'notes' => $_POST['notes'] ?? null,
                        'created_by' => $currentUser['id']
                    ]);
                    
                    $message = 'Payment recorded successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                    
                    if (!empty($_POST['invoice_id'])) {
                        header('Location: ?page=accounting&subpage=invoices&action=view&id=' . $_POST['invoice_id']);
                        exit;
                    }
                } catch (Exception $e) {
                    $message = 'Error: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'create_vendor':
            case 'update_vendor':
                if (!\App\Auth::can('settings.view')) {
                    $message = 'You do not have permission to manage vendors.';
                    $messageType = 'danger';
                    break;
                }
                try {
                    $accounting = new \App\Accounting(Database::getConnection());
                    
                    if ($action === 'create_vendor') {
                        $accounting->createVendor($_POST);
                        $message = 'Vendor created successfully!';
                    } else {
                        $accounting->updateVendor((int)$_POST['vendor_id'], $_POST);
                        $message = 'Vendor updated successfully!';
                    }
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                    header('Location: ?page=accounting&subpage=vendors');
                    exit;
                } catch (Exception $e) {
                    $message = 'Error: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'create_expense':
                if (!\App\Auth::can('settings.view')) {
                    $message = 'You do not have permission to record expenses.';
                    $messageType = 'danger';
                    break;
                }
                try {
                    $accounting = new \App\Accounting(Database::getConnection());
                    $amount = (float)$_POST['amount'];
                    $taxAmount = (float)($_POST['tax_amount'] ?? 0);
                    
                    $accounting->createExpense([
                        'category_id' => $_POST['category_id'] ?? null,
                        'vendor_id' => !empty($_POST['vendor_id']) ? (int)$_POST['vendor_id'] : null,
                        'expense_date' => $_POST['expense_date'] ?? date('Y-m-d'),
                        'amount' => $amount,
                        'tax_amount' => $taxAmount,
                        'total_amount' => $amount + $taxAmount,
                        'payment_method' => $_POST['payment_method'] ?? null,
                        'reference' => $_POST['reference'] ?? null,
                        'description' => $_POST['description'] ?? null,
                        'status' => 'approved',
                        'created_by' => $currentUser['id']
                    ]);
                    
                    $message = 'Expense recorded successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                    header('Location: ?page=accounting&subpage=expenses');
                    exit;
                } catch (Exception $e) {
                    $message = 'Error: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'create_product':
            case 'update_product':
                if (!\App\Auth::can('settings.view')) {
                    $message = 'You do not have permission to manage products.';
                    $messageType = 'danger';
                    break;
                }
                try {
                    $accounting = new \App\Accounting(Database::getConnection());
                    
                    if ($action === 'create_product') {
                        $accounting->createProduct($_POST);
                        $message = 'Product/service created successfully!';
                    } else {
                        $accounting->updateProduct((int)$_POST['product_id'], $_POST);
                        $message = 'Product/service updated successfully!';
                    }
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                    header('Location: ?page=accounting&subpage=products');
                    exit;
                } catch (Exception $e) {
                    $message = 'Error: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'create_quote':
            case 'update_quote':
                if (!\App\Auth::can('settings.view')) {
                    $message = 'You do not have permission to manage quotes.';
                    $messageType = 'danger';
                    break;
                }
                try {
                    $accounting = new \App\Accounting(Database::getConnection());
                    $items = $_POST['items'] ?? [];
                    
                    if ($action === 'create_quote') {
                        $quoteId = $accounting->createQuote([
                            'customer_id' => $_POST['customer_id'] ?? null,
                            'issue_date' => $_POST['issue_date'] ?? date('Y-m-d'),
                            'expiry_date' => $_POST['expiry_date'] ?? date('Y-m-d', strtotime('+30 days')),
                            'subtotal' => $_POST['subtotal'] ?? 0,
                            'tax_amount' => $_POST['tax_amount'] ?? 0,
                            'total_amount' => $_POST['total_amount'] ?? 0,
                            'notes' => $_POST['notes'] ?? null,
                            'terms' => $_POST['terms'] ?? null,
                            'status' => 'draft',
                            'created_by' => $_SESSION['user_id'] ?? null
                        ]);
                        
                        foreach ($items as $idx => $item) {
                            if (!empty($item['description'])) {
                                $item['sort_order'] = $idx;
                                $accounting->addQuoteItem($quoteId, $item);
                            }
                        }
                        $accounting->recalculateQuote($quoteId);
                        $message = 'Quote created successfully!';
                    } else {
                        $quoteId = (int)$_POST['quote_id'];
                        $accounting->updateQuote($quoteId, [
                            'customer_id' => $_POST['customer_id'] ?? null,
                            'issue_date' => $_POST['issue_date'] ?? date('Y-m-d'),
                            'expiry_date' => $_POST['expiry_date'] ?? date('Y-m-d', strtotime('+30 days')),
                            'subtotal' => $_POST['subtotal'] ?? 0,
                            'tax_amount' => $_POST['tax_amount'] ?? 0,
                            'total_amount' => $_POST['total_amount'] ?? 0,
                            'notes' => $_POST['notes'] ?? null,
                            'terms' => $_POST['terms'] ?? null,
                            'status' => $_POST['status'] ?? 'draft'
                        ]);
                        
                        $accounting->deleteQuoteItems($quoteId);
                        foreach ($items as $idx => $item) {
                            if (!empty($item['description'])) {
                                $item['sort_order'] = $idx;
                                $accounting->addQuoteItem($quoteId, $item);
                            }
                        }
                        $accounting->recalculateQuote($quoteId);
                        $message = 'Quote updated successfully!';
                    }
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                    header('Location: ?page=accounting&subpage=quotes&action=view&id=' . $quoteId);
                    exit;
                } catch (Exception $e) {
                    $message = 'Error: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;
                
            case 'convert_quote_to_invoice':
                if (!\App\Auth::can('settings.view')) {
                    $message = 'You do not have permission to convert quotes.';
                    $messageType = 'danger';
                    break;
                }
                try {
                    $accounting = new \App\Accounting(Database::getConnection());
                    $quoteId = (int)$_POST['quote_id'];
                    $invoiceId = $accounting->convertQuoteToInvoice($quoteId);
                    $message = 'Quote converted to invoice successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                    header('Location: ?page=accounting&subpage=invoices&action=view&id=' . $invoiceId);
                    exit;
                } catch (Exception $e) {
                    $message = 'Error: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;
                
            case 'mpesa_invoice_stkpush':
                if (!\App\Auth::can('settings.view')) {
                    $message = 'You do not have permission to process payments.';
                    $messageType = 'danger';
                    break;
                }
                try {
                    $mpesa = new \App\Mpesa();
                    $accounting = new \App\Accounting(Database::getConnection());
                    $invoiceId = (int)$_POST['invoice_id'];
                    $phone = trim($_POST['phone'] ?? '');
                    $requestedAmount = !empty($_POST['amount']) ? (int)$_POST['amount'] : null;
                    
                    $invoice = $accounting->getInvoice($invoiceId);
                    if (!$invoice) {
                        throw new Exception('Invoice not found');
                    }
                    
                    if ($invoice['balance_due'] <= 0) {
                        throw new Exception('This invoice is already paid in full');
                    }
                    
                    if (empty($phone)) {
                        throw new Exception('Phone number is required');
                    }
                    
                    $maxAmount = floor($invoice['balance_due']);
                    if ($maxAmount < 1 && $invoice['balance_due'] > 0) {
                        $maxAmount = 1;
                    }
                    $amount = $requestedAmount ? min($requestedAmount, $maxAmount) : $maxAmount;
                    if ($amount < 1) {
                        throw new Exception('Amount must be at least KES 1');
                    }
                    
                    if (!$mpesa->isConfigured()) {
                        throw new Exception('M-Pesa is not configured. Please configure M-Pesa settings first.');
                    }
                    
                    $result = $mpesa->stkPushForInvoice($invoiceId, $phone, $amount);
                    
                    if ($result['success']) {
                        $message = 'M-Pesa payment request sent! The customer will receive a prompt on their phone.';
                        $messageType = 'success';
                    } else {
                        $errorMsg = $result['message'] ?? 'Unknown error';
                        if (strpos($errorMsg, 'Invalid Access Token') !== false) {
                            $message = 'M-Pesa configuration error. Please check your API credentials.';
                        } elseif (strpos($errorMsg, 'Invalid PhoneNumber') !== false) {
                            $message = 'Invalid phone number. Please enter a valid Safaricom number.';
                        } else {
                            $message = 'M-Pesa request failed: ' . $errorMsg;
                        }
                        $messageType = 'danger';
                    }
                    \App\Auth::regenerateToken();
                    header('Location: ?page=accounting&subpage=invoices&action=view&id=' . $invoiceId);
                    exit;
                } catch (Exception $e) {
                    $message = $e->getMessage();
                    $messageType = 'danger';
                }
                break;
                
            case 'accounting_stkpush':
                if (!\App\Auth::can('settings.view')) {
                    $message = 'You do not have permission to process payments.';
                    $messageType = 'danger';
                    break;
                }
                try {
                    $mpesa = new \App\Mpesa();
                    $phone = trim($_POST['phone'] ?? '');
                    $amount = (int)($_POST['amount'] ?? 0);
                    $invoiceId = !empty($_POST['invoice_id']) ? (int)$_POST['invoice_id'] : null;
                    $customerId = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
                    $reference = trim($_POST['reference'] ?? '') ?: 'Payment';
                    
                    if (empty($phone)) {
                        throw new Exception('Phone number is required');
                    }
                    if ($amount < 1) {
                        throw new Exception('Amount must be at least KES 1');
                    }
                    if (!$mpesa->isConfigured()) {
                        throw new Exception('M-Pesa is not configured. Please configure M-Pesa settings first.');
                    }
                    
                    if ($invoiceId) {
                        $result = $mpesa->stkPushForInvoice($invoiceId, $phone, $amount);
                    } else {
                        $result = $mpesa->stkPush($phone, $amount, $reference);
                        if ($result['success'] && $customerId) {
                            // Link transaction to customer
                        }
                    }
                    
                    if ($result['success']) {
                        $message = 'M-Pesa payment request sent! The customer will receive a prompt on their phone.';
                        $messageType = 'success';
                    } else {
                        $errorMsg = $result['message'] ?? 'Unknown error';
                        if (strpos($errorMsg, 'Invalid Access Token') !== false) {
                            $message = 'M-Pesa configuration error. Please check your API credentials.';
                        } elseif (strpos($errorMsg, 'Invalid PhoneNumber') !== false) {
                            $message = 'Invalid phone number. Please enter a valid Safaricom number.';
                        } else {
                            $message = 'M-Pesa request failed: ' . $errorMsg;
                        }
                        $messageType = 'danger';
                    }
                    \App\Auth::regenerateToken();
                    header('Location: ?page=accounting&subpage=payments');
                    exit;
                } catch (Exception $e) {
                    $message = $e->getMessage();
                    $messageType = 'danger';
                }
                break;
                
            case 'create_bill':
            case 'update_bill':
                if (!\App\Auth::can('settings.view')) {
                    $message = 'You do not have permission to manage bills.';
                    $messageType = 'danger';
                    break;
                }
                try {
                    $accounting = new \App\Accounting(Database::getConnection());
                    $items = $_POST['items'] ?? [];
                    
                    if ($action === 'create_bill') {
                        $billNumber = $accounting->getNextNumber('bill');
                        $billId = $accounting->createBill([
                            'bill_number' => $billNumber,
                            'vendor_id' => $_POST['vendor_id'] ?? null,
                            'bill_date' => $_POST['bill_date'] ?? date('Y-m-d'),
                            'due_date' => $_POST['due_date'] ?? date('Y-m-d', strtotime('+30 days')),
                            'subtotal' => $_POST['subtotal'] ?? 0,
                            'tax_amount' => $_POST['tax_amount'] ?? 0,
                            'total_amount' => $_POST['total_amount'] ?? 0,
                            'reference' => $_POST['reference'] ?? null,
                            'notes' => $_POST['notes'] ?? null,
                            'status' => 'unpaid',
                            'created_by' => $_SESSION['user_id'] ?? null
                        ]);
                        
                        foreach ($items as $idx => $item) {
                            if (!empty($item['description'])) {
                                $item['sort_order'] = $idx;
                                $accounting->addBillItem($billId, $item);
                            }
                        }
                        $accounting->recalculateBill($billId);
                        $message = 'Bill created successfully!';
                    } else {
                        $billId = (int)$_POST['bill_id'];
                        $accounting->updateBill($billId, $_POST);
                        $message = 'Bill updated successfully!';
                    }
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                    header('Location: ?page=accounting&subpage=bills');
                    exit;
                } catch (Exception $e) {
                    $message = 'Error: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'save_company_settings':
                try {
                    $settings->saveCompanyInfo($_POST);
                    $message = 'Company settings saved successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error saving settings: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'save_sms_settings':
                try {
                    $settings->saveSMSSettings($_POST);
                    \App\Settings::clearCache();
                    $message = 'SMS settings saved successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error saving SMS settings: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'save_sms_templates':
                try {
                    $templateKeys = [
                        'sms_template_ticket_created',
                        'sms_template_ticket_updated',
                        'sms_template_ticket_resolved',
                        'sms_template_ticket_assigned',
                        'sms_template_technician_assigned',
                        'sms_template_complaint_received',
                        'sms_template_complaint_approved',
                        'sms_template_order_confirmation',
                        'sms_template_order_accepted',
                        'sms_template_hr_notice'
                    ];
                    foreach ($templateKeys as $key) {
                        if (isset($_POST[$key])) {
                            $settings->set($key, $_POST[$key]);
                        }
                    }
                    \App\Settings::clearCache();
                    $message = 'SMS templates saved successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error saving SMS templates: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;
            
            case 'save_primary_gateway':
                try {
                    $gateway = $_POST['primary_notification_gateway'] ?? 'both';
                    $settings->setPrimaryNotificationGateway($gateway);
                    \App\Settings::clearCache();
                    $message = 'Primary notification gateway saved successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error saving gateway setting: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'save_whatsapp_settings':
                try {
                    $whatsappData = $_POST;
                    // Always keep WhatsApp enabled - ignore checkbox state
                    $whatsappData['whatsapp_enabled'] = '1';
                    $settings->saveWhatsAppSettings($whatsappData);
                    \App\Settings::clearCache();
                    $message = 'WhatsApp settings saved successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error saving WhatsApp settings: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;
                
            case 'save_whatsapp_templates':
                try {
                    $templateKeys = [
                        'wa_template_status_update',
                        'wa_template_need_info',
                        'wa_template_resolved',
                        'wa_template_technician_coming',
                        'wa_template_scheduled',
                        'wa_template_order_confirmation',
                        'wa_template_order_processing',
                        'wa_template_order_installation',
                        'wa_template_complaint_received',
                        'wa_template_complaint_review',
                        'wa_template_complaint_approved',
                        'wa_template_complaint_rejected'
                    ];
                    foreach ($templateKeys as $key) {
                        if (isset($_POST[$key])) {
                            $settings->set($key, $_POST[$key]);
                        }
                    }
                    \App\Settings::clearCache();
                    $message = 'WhatsApp templates saved successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error saving WhatsApp templates: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'create_branch':
                try {
                    $branchClass = new \App\Branch();
                    $branchClass->create([
                        'name' => $_POST['name'],
                        'code' => $_POST['code'] ?? null,
                        'address' => $_POST['address'] ?? null,
                        'phone' => $_POST['phone'] ?? null,
                        'email' => $_POST['email'] ?? null,
                        'whatsapp_group' => $_POST['whatsapp_group'] ?? null,
                        'manager_id' => $_POST['manager_id'] ?: null,
                        'is_active' => isset($_POST['is_active'])
                    ]);
                    $message = 'Branch created successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error creating branch: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'update_branch':
                try {
                    $branchClass = new \App\Branch();
                    $branchClass->update((int)$_POST['branch_id'], [
                        'name' => $_POST['name'],
                        'code' => $_POST['code'] ?? null,
                        'address' => $_POST['address'] ?? null,
                        'phone' => $_POST['phone'] ?? null,
                        'email' => $_POST['email'] ?? null,
                        'whatsapp_group' => $_POST['whatsapp_group'] ?? null,
                        'manager_id' => $_POST['manager_id'] ?: null,
                        'is_active' => isset($_POST['is_active'])
                    ]);
                    $message = 'Branch updated successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error updating branch: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'delete_branch':
                try {
                    $branchClass = new \App\Branch();
                    $branchClass->delete((int)$_POST['branch_id']);
                    $message = 'Branch deleted successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error deleting branch: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'update_branch_employees':
                try {
                    $branchClass = new \App\Branch();
                    $branchId = (int)$_POST['branch_id'];
                    $employeeIds = $_POST['employee_ids'] ?? [];
                    
                    $db->beginTransaction();
                    $db->prepare("DELETE FROM employee_branches WHERE branch_id = ?")->execute([$branchId]);
                    foreach ($employeeIds as $empId) {
                        $branchClass->attachEmployee($branchId, (int)$empId, false, $_SESSION['user_id'] ?? null);
                    }
                    $db->commit();
                    
                    $message = 'Branch employees updated successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $db->rollBack();
                    $message = 'Error updating branch employees: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'update_branch_teams':
                try {
                    $branchId = (int)$_POST['branch_id'];
                    $teamIds = $_POST['team_ids'] ?? [];
                    
                    $db->beginTransaction();
                    $db->prepare("UPDATE teams SET branch_id = NULL WHERE branch_id = ?")->execute([$branchId]);
                    if (!empty($teamIds)) {
                        $placeholders = implode(',', array_fill(0, count($teamIds), '?'));
                        $params = array_merge([$branchId], $teamIds);
                        $db->prepare("UPDATE teams SET branch_id = ? WHERE id IN ($placeholders)")->execute($params);
                    }
                    $db->commit();
                    
                    $message = 'Branch teams updated successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $db->rollBack();
                    $message = 'Error updating branch teams: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'save_billing_api':
                try {
                    $authType = $_POST['auth_type'] ?? 'token';
                    
                    if ($authType === 'token') {
                        $token = trim($_POST['oneisp_api_token'] ?? '');
                        $settings->set('oneisp_api_token', $token);
                        if (!empty($token)) {
                            $settings->set('oneisp_username', '');
                            $settings->set('oneisp_password', '');
                        }
                        $message = 'API token saved successfully!';
                    } else {
                        $prefix = trim($_POST['oneisp_prefix'] ?? '');
                        $username = trim($_POST['oneisp_username'] ?? '');
                        $password = trim($_POST['oneisp_password'] ?? '');
                        $settings->set('oneisp_prefix', $prefix);
                        $settings->set('oneisp_username', $username);
                        $settings->set('oneisp_password', $password);
                        if (!empty($username) && !empty($password)) {
                            $settings->set('oneisp_api_token', '');
                        }
                        $message = 'Login credentials saved successfully!';
                    }
                    
                    \App\Settings::clearCache();
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error saving billing API settings: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'create_package':
                $name = trim($_POST['name'] ?? '');
                $speed = trim($_POST['speed'] ?? '');
                $price = floatval($_POST['price'] ?? 0);
                if (empty($name) || empty($speed) || $price <= 0) {
                    $message = 'Package name, speed, and price are required.';
                    $messageType = 'danger';
                } else {
                    try {
                        $featuresText = trim($_POST['features_text'] ?? '');
                        $features = array_filter(array_map('trim', explode("\n", $featuresText)));
                        $_POST['features'] = $features;
                        $settings->createPackage($_POST);
                        $message = 'Package created successfully!';
                        $messageType = 'success';
                        \App\Auth::regenerateToken();
                        header('Location: ?page=settings&subpage=packages');
                        exit;
                    } catch (Exception $e) {
                        $message = 'Error creating package: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;

            case 'update_package':
                $packageId = (int)($_POST['package_id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $speed = trim($_POST['speed'] ?? '');
                $price = floatval($_POST['price'] ?? 0);
                if (!$packageId || empty($name) || empty($speed) || $price <= 0) {
                    $message = 'Package name, speed, and price are required.';
                    $messageType = 'danger';
                } else {
                    try {
                        $featuresText = trim($_POST['features_text'] ?? '');
                        $features = array_filter(array_map('trim', explode("\n", $featuresText)));
                        $_POST['features'] = $features;
                        $settings->updatePackage($packageId, $_POST);
                        $message = 'Package updated successfully!';
                        $messageType = 'success';
                        \App\Auth::regenerateToken();
                        header('Location: ?page=settings&subpage=packages');
                        exit;
                    } catch (Exception $e) {
                        $message = 'Error updating package: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;

            case 'delete_package':
                if (!\App\Auth::isAdmin()) {
                    $message = 'Only administrators can delete packages.';
                    $messageType = 'danger';
                } else {
                    try {
                        $packageId = (int)($_POST['package_id'] ?? 0);
                        if ($packageId) {
                            $settings->deletePackage($packageId);
                            $message = 'Package deleted successfully!';
                            $messageType = 'success';
                            \App\Auth::regenerateToken();
                        }
                    } catch (Exception $e) {
                        $message = 'Error deleting package: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;

            case 'save_landing_settings':
                try {
                    $settings->saveLandingPageSettings($_POST);
                    \App\Settings::clearCache();
                    $message = 'Landing page settings saved successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error saving landing page settings: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'save_contact_settings':
                try {
                    $settings->saveContactSettings($_POST);
                    \App\Settings::clearCache();
                    $message = 'Contact settings saved successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error saving contact settings: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'create_sla_policy':
                try {
                    $sla = new \App\SLA();
                    $sla->createPolicy($_POST);
                    $message = 'SLA policy created successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error creating SLA policy: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'update_sla_policy':
                try {
                    $sla = new \App\SLA();
                    $sla->updatePolicy((int)$_POST['policy_id'], $_POST);
                    $message = 'SLA policy updated successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error updating SLA policy: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'delete_sla_policy':
                try {
                    $sla = new \App\SLA();
                    $sla->deletePolicy((int)$_POST['policy_id']);
                    $message = 'SLA policy deleted successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error deleting SLA policy: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'save_business_hours':
                try {
                    $sla = new \App\SLA();
                    $hours = [];
                    foreach ($_POST['hours'] as $dayHours) {
                        $hours[] = [
                            'day_of_week' => (int)$dayHours['day_of_week'],
                            'start_time' => $dayHours['start_time'],
                            'end_time' => $dayHours['end_time'],
                            'is_working_day' => isset($dayHours['is_working_day'])
                        ];
                    }
                    $sla->updateBusinessHours($hours);
                    $message = 'Business hours saved successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error saving business hours: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'add_holiday':
                try {
                    $sla = new \App\SLA();
                    $sla->addHoliday($_POST);
                    $message = 'Holiday added successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error adding holiday: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'delete_holiday':
                try {
                    $sla = new \App\SLA();
                    $sla->deleteHoliday((int)$_POST['holiday_id']);
                    $message = 'Holiday removed successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error removing holiday: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;
            
            case 'create_service_fee':
                if (!\App\Auth::can('settings.edit')) {
                    $message = 'You do not have permission to add fee types.';
                    $messageType = 'danger';
                    break;
                }
                try {
                    $serviceFee = new \App\ServiceFee($db);
                    $serviceFee->createFeeType([
                        'name' => trim($_POST['name'] ?? ''),
                        'description' => trim($_POST['description'] ?? ''),
                        'default_amount' => (float)($_POST['default_amount'] ?? 0),
                        'currency' => $_POST['currency'] ?? 'KES',
                        'is_active' => isset($_POST['is_active']),
                        'display_order' => (int)($_POST['display_order'] ?? 0)
                    ]);
                    $message = 'Service fee type added successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                    header('Location: ?page=settings&subpage=service_fees');
                    exit;
                } catch (Exception $e) {
                    $message = 'Error adding fee type: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;
            
            case 'update_service_fee':
                if (!\App\Auth::can('settings.edit')) {
                    $message = 'You do not have permission to update fee types.';
                    $messageType = 'danger';
                    break;
                }
                try {
                    $serviceFee = new \App\ServiceFee($db);
                    $feeTypeId = (int)($_POST['fee_type_id'] ?? 0);
                    if ($feeTypeId <= 0) throw new Exception('Invalid fee type ID');
                    $serviceFee->updateFeeType($feeTypeId, [
                        'name' => trim($_POST['name'] ?? ''),
                        'description' => trim($_POST['description'] ?? ''),
                        'default_amount' => (float)($_POST['default_amount'] ?? 0),
                        'currency' => $_POST['currency'] ?? 'KES',
                        'is_active' => isset($_POST['is_active']),
                        'display_order' => (int)($_POST['display_order'] ?? 0)
                    ]);
                    $message = 'Service fee type updated successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                    header('Location: ?page=settings&subpage=service_fees');
                    exit;
                } catch (Exception $e) {
                    $message = 'Error updating fee type: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;
            
            case 'delete_service_fee':
                if (!\App\Auth::can('settings.edit')) {
                    $message = 'You do not have permission to delete fee types.';
                    $messageType = 'danger';
                    break;
                }
                try {
                    $serviceFee = new \App\ServiceFee($db);
                    $feeTypeId = (int)($_POST['fee_type_id'] ?? 0);
                    if ($feeTypeId <= 0) throw new Exception('Invalid fee type ID');
                    $serviceFee->deleteFeeType($feeTypeId);
                    $message = 'Service fee type deleted successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error deleting fee type: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'create_hr_template':
                try {
                    require_once __DIR__ . '/../src/RealTimeAttendanceProcessor.php';
                    $rtProcessor = new \App\RealTimeAttendanceProcessor(\Database::getConnection());
                    $rtProcessor->createHRTemplate($_POST);
                    $message = 'HR notification template created successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                    header('Location: ?page=settings&subpage=hr_templates');
                    exit;
                } catch (Exception $e) {
                    $message = 'Error creating HR template: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'update_hr_template':
                try {
                    require_once __DIR__ . '/../src/RealTimeAttendanceProcessor.php';
                    $rtProcessor = new \App\RealTimeAttendanceProcessor(\Database::getConnection());
                    $rtProcessor->updateHRTemplate((int)$_POST['template_id'], $_POST);
                    $message = 'HR notification template updated successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                    header('Location: ?page=settings&subpage=hr_templates');
                    exit;
                } catch (Exception $e) {
                    $message = 'Error updating HR template: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'delete_hr_template':
                try {
                    require_once __DIR__ . '/../src/RealTimeAttendanceProcessor.php';
                    $rtProcessor = new \App\RealTimeAttendanceProcessor(\Database::getConnection());
                    $rtProcessor->deleteHRTemplate((int)$_POST['template_id']);
                    $message = 'HR notification template deleted successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error deleting HR template: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'create_backup':
                try {
                    if (!\App\Auth::isAdmin()) {
                        throw new Exception('Only administrators can create database backups.');
                    }
                    
                    $backupDir = __DIR__ . '/../backups';
                    if (!is_dir($backupDir)) {
                        mkdir($backupDir, 0755, true);
                    }
                    
                    $filename = 'backup_' . date('Y-m-d_His') . '.sql';
                    $filepath = $backupDir . '/' . $filename;
                    
                    $dbHost = getenv('PGHOST') ?: 'localhost';
                    $dbName = getenv('PGDATABASE') ?: 'isp_crm';
                    $dbUser = getenv('PGUSER') ?: 'crm';
                    $dbPass = getenv('PGPASSWORD') ?: '';
                    
                    $backupSuccess = false;
                    $output = [];
                    $returnVar = 1;
                    
                    // Method 1: Try direct pg_dump (works on native installs and Replit)
                    exec('which pg_dump 2>/dev/null', $pgdumpCheck, $pgdumpExists);
                    if ($pgdumpExists === 0) {
                        putenv("PGPASSWORD=$dbPass");
                        $command = sprintf(
                            'pg_dump -h %s -U %s -d %s -F p > %s 2>&1',
                            escapeshellarg($dbHost),
                            escapeshellarg($dbUser),
                            escapeshellarg($dbName),
                            escapeshellarg($filepath)
                        );
                        exec($command, $output, $returnVar);
                        if ($returnVar === 0 && file_exists($filepath) && filesize($filepath) > 100) {
                            $backupSuccess = true;
                        }
                    }
                    
                    // Method 2: Try docker exec (for Docker deployments)
                    if (!$backupSuccess) {
                        $dockerContainer = getenv('DB_DOCKER_CONTAINER') ?: 'isp_crm_db';
                        $command = sprintf(
                            'docker exec %s pg_dump -U %s -d %s > %s 2>&1',
                            escapeshellarg($dockerContainer),
                            escapeshellarg($dbUser),
                            escapeshellarg($dbName),
                            escapeshellarg($filepath)
                        );
                        exec($command, $output, $returnVar);
                        if ($returnVar === 0 && file_exists($filepath) && filesize($filepath) > 100) {
                            $backupSuccess = true;
                        }
                    }
                    
                    // Method 3: PHP-based backup using PDO (fallback)
                    if (!$backupSuccess) {
                        $db = \Database::getConnection();
                        $tables = $db->query("SELECT tablename FROM pg_tables WHERE schemaname = 'public'")->fetchAll(\PDO::FETCH_COLUMN);
                        
                        $backupContent = "-- ISP CRM Database Backup\n";
                        $backupContent .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
                        $backupContent .= "-- Tables: " . count($tables) . "\n\n";
                        
                        foreach ($tables as $table) {
                            $backupContent .= "\n-- Table: $table\n";
                            
                            // Get table structure
                            $cols = $db->query("SELECT column_name, data_type, is_nullable, column_default 
                                FROM information_schema.columns WHERE table_name = '$table' ORDER BY ordinal_position")->fetchAll(\PDO::FETCH_ASSOC);
                            
                            // Export data
                            $rows = $db->query("SELECT * FROM \"$table\"")->fetchAll(\PDO::FETCH_ASSOC);
                            foreach ($rows as $row) {
                                $columns = array_keys($row);
                                $values = array_map(function($v) use ($db) {
                                    if ($v === null) return 'NULL';
                                    return $db->quote($v);
                                }, array_values($row));
                                $backupContent .= "INSERT INTO \"$table\" (\"" . implode('", "', $columns) . "\") VALUES (" . implode(', ', $values) . ");\n";
                            }
                        }
                        
                        file_put_contents($filepath, $backupContent);
                        if (file_exists($filepath) && filesize($filepath) > 100) {
                            $backupSuccess = true;
                        }
                    }
                    
                    if (!$backupSuccess) {
                        if (file_exists($filepath)) {
                            unlink($filepath);
                        }
                        throw new Exception('Backup failed. Tried pg_dump, docker exec, and PHP export. Check server configuration.');
                    }
                    
                    $_SESSION['backup_success'] = 'Database backup created successfully: ' . $filename . ' (' . number_format(filesize($filepath) / 1024, 2) . ' KB)';
                    \App\Auth::regenerateToken();
                    header('Location: ?page=settings&subpage=backup');
                    exit;
                } catch (Exception $e) {
                    $_SESSION['backup_error'] = $e->getMessage();
                    header('Location: ?page=settings&subpage=backup');
                    exit;
                }
                break;

            case 'delete_backup':
                try {
                    if (!\App\Auth::isAdmin()) {
                        throw new Exception('Only administrators can delete backups.');
                    }
                    
                    $filename = basename($_POST['filename'] ?? '');
                    if (empty($filename) || !preg_match('/^backup_.*\.sql$/', $filename)) {
                        throw new Exception('Invalid backup filename.');
                    }
                    
                    $filepath = __DIR__ . '/../backups/' . $filename;
                    if (!file_exists($filepath)) {
                        throw new Exception('Backup file not found.');
                    }
                    
                    unlink($filepath);
                    $_SESSION['backup_success'] = 'Backup deleted successfully.';
                    \App\Auth::regenerateToken();
                    header('Location: ?page=settings&subpage=backup');
                    exit;
                } catch (Exception $e) {
                    $_SESSION['backup_error'] = $e->getMessage();
                    header('Location: ?page=settings&subpage=backup');
                    exit;
                }
                break;

            case 'upload_backup':
                try {
                    if (!\App\Auth::isAdmin()) {
                        throw new Exception('Only administrators can upload backups.');
                    }
                    
                    if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
                        $uploadErrors = [
                            UPLOAD_ERR_INI_SIZE => 'File exceeds server upload limit.',
                            UPLOAD_ERR_FORM_SIZE => 'File exceeds form upload limit.',
                            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
                            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
                            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
                            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                            UPLOAD_ERR_EXTENSION => 'Upload stopped by extension.',
                        ];
                        $errorCode = $_FILES['backup_file']['error'] ?? UPLOAD_ERR_NO_FILE;
                        throw new Exception($uploadErrors[$errorCode] ?? 'Unknown upload error.');
                    }
                    
                    $file = $_FILES['backup_file'];
                    $originalName = basename($file['name']);
                    
                    // Validate file extension
                    if (pathinfo($originalName, PATHINFO_EXTENSION) !== 'sql') {
                        throw new Exception('Only .sql files are allowed.');
                    }
                    
                    // Validate file size (50MB max)
                    if ($file['size'] > 50 * 1024 * 1024) {
                        throw new Exception('File size exceeds 50MB limit.');
                    }
                    
                    // Basic content validation - check if it looks like SQL
                    $content = file_get_contents($file['tmp_name'], false, null, 0, 1000);
                    if (stripos($content, 'INSERT') === false && stripos($content, 'CREATE') === false && stripos($content, '--') === false) {
                        throw new Exception('File does not appear to be a valid SQL backup.');
                    }
                    
                    $backupDir = __DIR__ . '/../backups';
                    if (!is_dir($backupDir)) {
                        mkdir($backupDir, 0755, true);
                    }
                    
                    // Create unique filename with upload prefix
                    $filename = 'backup_uploaded_' . date('Y-m-d_His') . '.sql';
                    $filepath = $backupDir . '/' . $filename;
                    
                    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                        throw new Exception('Failed to save uploaded file.');
                    }
                    
                    $_SESSION['backup_success'] = 'Backup uploaded successfully: ' . $filename . ' (' . number_format($file['size'] / 1024, 2) . ' KB)';
                    \App\Auth::regenerateToken();
                    header('Location: ?page=settings&subpage=backup');
                    exit;
                } catch (Exception $e) {
                    $_SESSION['backup_error'] = $e->getMessage();
                    header('Location: ?page=settings&subpage=backup');
                    exit;
                }
                break;

            case 'save_vpn_settings':
                try {
                    if (!\App\Auth::isAdmin()) {
                        throw new Exception('Only administrators can modify VPN settings.');
                    }
                    
                    $wgService = new \App\WireGuardService($db);
                    $wgService->updateSettings([
                        'vpn_enabled' => isset($_POST['vpn_enabled']) ? 'true' : 'false',
                        'server_public_ip' => $_POST['server_public_ip'] ?? '',
                        'vpn_gateway_ip' => $_POST['vpn_gateway_ip'] ?? '10.200.0.1',
                        'vpn_network' => $_POST['vpn_network'] ?? '10.200.0.0/24',
                        'tr069_use_vpn_gateway' => isset($_POST['tr069_use_vpn_gateway']) ? 'true' : 'false',
                        'tr069_acs_url' => $_POST['tr069_acs_url'] ?? 'http://localhost:7547'
                    ]);
                    
                    $message = 'VPN settings saved successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error saving VPN settings: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'add_vpn_server':
                try {
                    if (!\App\Auth::isAdmin()) {
                        throw new Exception('Only administrators can add VPN servers.');
                    }
                    
                    $wgService = new \App\WireGuardService($db);
                    $serverId = $wgService->createServer([
                        'name' => $_POST['name'] ?? 'VPN Server',
                        'interface_name' => $_POST['interface_name'] ?? 'wg0',
                        'interface_addr' => $_POST['interface_addr'] ?? '10.200.0.1/24',
                        'listen_port' => (int)($_POST['listen_port'] ?? 51820),
                        'mtu' => (int)($_POST['mtu'] ?? 1420),
                        'dns_servers' => $_POST['dns_servers'] ?? null,
                        'enabled' => true
                    ]);
                    
                    if ($serverId) {
                        $message = 'VPN server created successfully!';
                        $messageType = 'success';
                    } else {
                        throw new Exception('Failed to create VPN server.');
                    }
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error creating VPN server: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'delete_vpn_server':
                try {
                    if (!\App\Auth::isAdmin()) {
                        throw new Exception('Only administrators can delete VPN servers.');
                    }
                    
                    $serverId = (int)($_POST['server_id'] ?? 0);
                    if ($serverId <= 0) {
                        throw new Exception('Invalid server ID.');
                    }
                    
                    $wgService = new \App\WireGuardService($db);
                    $wgService->deleteServer($serverId);
                    
                    $message = 'VPN server deleted successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error deleting VPN server: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'add_vpn_peer':
                try {
                    if (!\App\Auth::isAdmin()) {
                        throw new Exception('Only administrators can add VPN peers.');
                    }
                    
                    $wgService = new \App\WireGuardService($db);
                    $peerId = $wgService->createPeer([
                        'server_id' => (int)($_POST['server_id'] ?? 0),
                        'name' => $_POST['name'] ?? 'VPN Peer',
                        'description' => $_POST['description'] ?? null,
                        'allowed_ips' => $_POST['allowed_ips'] ?? '10.200.0.2/32',
                        'endpoint' => $_POST['endpoint'] ?? null,
                        'persistent_keepalive' => (int)($_POST['persistent_keepalive'] ?? 25),
                        'is_active' => true,
                        'is_olt_site' => isset($_POST['is_olt_site']),
                        'olt_id' => !empty($_POST['olt_id']) ? (int)$_POST['olt_id'] : null
                    ]);
                    
                    if ($peerId) {
                        // Sync config and routes after adding peer
                        $syncResult = $wgService->syncConfig();
                        $syncMsg = $syncResult['success'] ? ' Routes synced.' : '';
                        $message = 'VPN peer created successfully!' . $syncMsg;
                        $messageType = 'success';
                    } else {
                        throw new Exception('Failed to create VPN peer.');
                    }
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error creating VPN peer: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;
            
            case 'sync_vpn_config':
                try {
                    if (!\App\Auth::isAdmin()) {
                        throw new Exception('Only administrators can sync VPN configuration.');
                    }
                    
                    $wgService = new \App\WireGuardService($db);
                    $result = $wgService->syncConfig();
                    
                    if ($result['success']) {
                        $message = $result['message'];
                        $messageType = 'success';
                    } else {
                        throw new Exception($result['message']);
                    }
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error syncing VPN config: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;
            
            case 'update_vpn_peer':
                try {
                    if (!\App\Auth::isAdmin()) {
                        throw new Exception('Only administrators can update VPN peers.');
                    }
                    
                    $peerId = (int)($_POST['peer_id'] ?? 0);
                    if ($peerId <= 0) {
                        throw new Exception('Invalid peer ID.');
                    }
                    
                    $wgService = new \App\WireGuardService($db);
                    $updated = $wgService->updatePeer($peerId, [
                        'name' => $_POST['name'] ?? '',
                        'description' => $_POST['description'] ?? null,
                        'allowed_ips' => $_POST['allowed_ips'] ?? '',
                        'endpoint' => $_POST['endpoint'] ?? null,
                        'persistent_keepalive' => (int)($_POST['persistent_keepalive'] ?? 25),
                        'is_active' => true,
                        'is_olt_site' => isset($_POST['is_olt_site']),
                        'olt_id' => null
                    ]);
                    
                    if ($updated) {
                        // Always sync config and routes after saving peer
                        $syncResult = $wgService->syncConfig();
                        $syncMsg = $syncResult['success'] ? ' Config & routes synced.' : ' Sync failed: ' . ($syncResult['error'] ?? 'Unknown');
                        $message = 'VPN peer updated successfully!' . $syncMsg;
                        $messageType = 'success';
                    } else {
                        throw new Exception('Failed to update VPN peer.');
                    }
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error updating VPN peer: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'delete_vpn_peer':
                try {
                    if (!\App\Auth::isAdmin()) {
                        throw new Exception('Only administrators can delete VPN peers.');
                    }
                    
                    $peerId = (int)($_POST['peer_id'] ?? 0);
                    if ($peerId <= 0) {
                        throw new Exception('Invalid peer ID.');
                    }
                    
                    $wgService = new \App\WireGuardService($db);
                    $wgService->deletePeer($peerId);
                    
                    $message = 'VPN peer deleted successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error deleting VPN peer: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'save_mpesa_settings':
                try {
                    error_log("M-Pesa settings save started");
                    $mpesa = new \App\Mpesa();
                    $savedCount = 0;
                    $savedCount += $mpesa->saveConfig('mpesa_environment', $_POST['mpesa_environment'] ?? 'sandbox') ? 1 : 0;
                    $savedCount += $mpesa->saveConfig('mpesa_shortcode', $_POST['mpesa_shortcode'] ?? '') ? 1 : 0;
                    $savedCount += $mpesa->saveConfig('mpesa_consumer_key', $_POST['mpesa_consumer_key'] ?? '') ? 1 : 0;
                    $savedCount += $mpesa->saveConfig('mpesa_consumer_secret', $_POST['mpesa_consumer_secret'] ?? '') ? 1 : 0;
                    $savedCount += $mpesa->saveConfig('mpesa_passkey', $_POST['mpesa_passkey'] ?? '') ? 1 : 0;
                    $savedCount += $mpesa->saveConfig('mpesa_callback_url', $_POST['mpesa_callback_url'] ?? '') ? 1 : 0;
                    $savedCount += $mpesa->saveConfig('mpesa_validation_url', $_POST['mpesa_validation_url'] ?? '') ? 1 : 0;
                    $savedCount += $mpesa->saveConfig('mpesa_confirmation_url', $_POST['mpesa_confirmation_url'] ?? '') ? 1 : 0;
                    error_log("M-Pesa settings saved count: $savedCount");
                    \App\Auth::regenerateToken();
                    $_SESSION['flash_message'] = "M-Pesa settings saved successfully! ($savedCount items)";
                    $_SESSION['flash_type'] = 'success';
                    header('Location: ?page=settings&subpage=mpesa');
                    exit;
                } catch (\Exception $e) {
                    error_log("M-Pesa settings save error: " . $e->getMessage());
                    $message = 'Error saving M-Pesa settings: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'save_smartolt_settings':
                if (!\App\Auth::isAdmin()) {
                    $message = 'Only administrators can modify SmartOLT settings.';
                    $messageType = 'danger';
                } else {
                    try {
                        \App\SmartOLT::saveSettings($db, [
                            'api_url' => $_POST['smartolt_api_url'] ?? '',
                            'api_key' => $_POST['smartolt_api_key'] ?? ''
                        ]);
                        \App\Settings::clearCache();
                        $message = 'SmartOLT settings saved successfully!';
                        $messageType = 'success';
                        \App\Auth::regenerateToken();
                    } catch (Exception $e) {
                        $message = 'Error saving SmartOLT settings: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;

            case 'test_smartolt_connection':
                if (!\App\Auth::isAdmin()) {
                    $message = 'Only administrators can test SmartOLT connection.';
                    $messageType = 'danger';
                } else {
                    try {
                        $smartolt = new \App\SmartOLT($db);
                        $result = $smartolt->testConnection();
                        if ($result['success']) {
                            $message = $result['message'];
                            $messageType = 'success';
                        } else {
                            $message = 'Connection failed: ' . $result['message'];
                            $messageType = 'danger';
                        }
                        \App\Auth::regenerateToken();
                    } catch (Exception $e) {
                        $message = 'Error testing connection: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;

            case 'register_c2b':
                try {
                    $mpesa = new \App\Mpesa();
                    $result = $mpesa->registerC2BUrls();
                    if ($result['success']) {
                        $mpesa->saveConfig('c2b_urls_registered', date('Y-m-d H:i:s'));
                        $message = 'C2B URLs registered successfully!';
                        $messageType = 'success';
                    } else {
                        $message = 'Failed to register C2B URLs: ' . ($result['error'] ?? 'Unknown error');
                        $messageType = 'danger';
                    }
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error registering C2B URLs: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'create_template':
                $name = trim($_POST['name'] ?? '');
                $content = trim($_POST['content'] ?? '');
                if (empty($name) || empty($content)) {
                    $message = 'Template name and content are required.';
                    $messageType = 'danger';
                } else {
                    try {
                        $_POST['created_by'] = $currentUser['id'];
                        $settings->createTemplate($_POST);
                        $message = 'Template created successfully!';
                        $messageType = 'success';
                        \App\Auth::regenerateToken();
                    } catch (Exception $e) {
                        $message = 'Error creating template: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;

            case 'update_template':
                $name = trim($_POST['name'] ?? '');
                $content = trim($_POST['content'] ?? '');
                if (empty($name) || empty($content)) {
                    $message = 'Template name and content are required.';
                    $messageType = 'danger';
                } else {
                    try {
                        $settings->updateTemplate((int)$_POST['id'], $_POST);
                        $message = 'Template updated successfully!';
                        $messageType = 'success';
                        \App\Auth::regenerateToken();
                    } catch (Exception $e) {
                        $message = 'Error updating template: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;

            case 'delete_template':
                if (!\App\Auth::isAdmin()) {
                    $message = 'Only administrators can delete templates.';
                    $messageType = 'danger';
                } else {
                    try {
                        $settings->deleteTemplate((int)$_POST['id']);
                        $message = 'Template deleted successfully!';
                        $messageType = 'success';
                        \App\Auth::regenerateToken();
                    } catch (Exception $e) {
                        $message = 'Error deleting template: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;

            case 'add_biometric_device':
                if (!\App\Auth::isAdmin()) {
                    $message = 'Only administrators can add biometric devices.';
                    $messageType = 'danger';
                } else {
                    try {
                        $biometricService = new \App\BiometricSyncService($db);
                        $deviceData = [
                            'name' => $_POST['name'],
                            'device_type' => $_POST['device_type'],
                            'ip_address' => $_POST['ip_address'],
                            'port' => (int)$_POST['port'],
                            'username' => $_POST['username'] ?? null,
                            'password' => $_POST['password'] ?? null,
                            'serial_number' => $_POST['serial_number'] ?? null,
                            'sync_interval_minutes' => (int)($_POST['sync_interval_minutes'] ?? 15),
                            'is_active' => isset($_POST['is_active']),
                            'api_base_url' => $_POST['api_base_url'] ?? null,
                            'company_name' => $_POST['company_name'] ?? null
                        ];
                        $biometricService->addDevice($deviceData);
                        $message = 'Biometric device added successfully!';
                        $messageType = 'success';
                        \App\Auth::regenerateToken();
                    } catch (Exception $e) {
                        $message = 'Error adding device: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;

            case 'update_biometric_device':
                if (!\App\Auth::isAdmin()) {
                    $message = 'Only administrators can update biometric devices.';
                    $messageType = 'danger';
                } else {
                    try {
                        $biometricService = new \App\BiometricSyncService($db);
                        $deviceData = [
                            'name' => $_POST['name'],
                            'device_type' => $_POST['device_type'],
                            'ip_address' => $_POST['ip_address'],
                            'port' => (int)$_POST['port'],
                            'username' => $_POST['username'] ?? null,
                            'serial_number' => $_POST['serial_number'] ?? null,
                            'sync_interval_minutes' => (int)($_POST['sync_interval_minutes'] ?? 15),
                            'is_active' => isset($_POST['is_active']),
                            'api_base_url' => $_POST['api_base_url'] ?? null,
                            'company_name' => $_POST['company_name'] ?? null
                        ];
                        if (!empty($_POST['password'])) {
                            $deviceData['password'] = $_POST['password'];
                        }
                        $biometricService->updateDevice((int)$_POST['device_id'], $deviceData);
                        $message = 'Biometric device updated successfully!';
                        $messageType = 'success';
                        \App\Auth::regenerateToken();
                    } catch (Exception $e) {
                        $message = 'Error updating device: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;

            case 'save_user_mapping':
                if (!\App\Auth::isAdmin()) {
                    $message = 'Only administrators can manage user mappings.';
                    $messageType = 'danger';
                } else {
                    try {
                        $biometricService = new \App\BiometricSyncService($db);
                        $biometricService->saveUserMapping(
                            (int)$_POST['device_id'],
                            $_POST['device_user_id'],
                            (int)$_POST['employee_id'],
                            $_POST['device_user_name'] ?? null
                        );
                        $message = 'User mapping saved successfully!';
                        $messageType = 'success';
                        \App\Auth::regenerateToken();
                    } catch (Exception $e) {
                        $message = 'Error saving mapping: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;

            case 'add_late_rule':
                if (!\App\Auth::isAdmin()) {
                    $message = 'Only administrators can add late rules.';
                    $messageType = 'danger';
                } else {
                    try {
                        $lateCalculator = new \App\LateDeductionCalculator($db);
                        $tiers = [];
                        if (!empty($_POST['tier_min']) && is_array($_POST['tier_min'])) {
                            foreach ($_POST['tier_min'] as $i => $min) {
                                if ($min !== '' && isset($_POST['tier_max'][$i]) && isset($_POST['tier_amount'][$i])) {
                                    $tiers[] = [
                                        'min_minutes' => (int)$min,
                                        'max_minutes' => (int)$_POST['tier_max'][$i],
                                        'amount' => (float)$_POST['tier_amount'][$i]
                                    ];
                                }
                            }
                        }
                        $ruleData = [
                            'name' => $_POST['name'],
                            'work_start_time' => $_POST['work_start_time'],
                            'grace_minutes' => (int)($_POST['grace_minutes'] ?? 15),
                            'deduction_tiers' => $tiers,
                            'currency' => $_POST['currency'] ?? 'KES',
                            'apply_to_department_id' => $_POST['apply_to_department_id'] ?: null,
                            'is_default' => isset($_POST['is_default']),
                            'is_active' => isset($_POST['is_active'])
                        ];
                        $lateCalculator->addRule($ruleData);
                        $message = 'Late deduction rule added successfully!';
                        $messageType = 'success';
                        \App\Auth::regenerateToken();
                    } catch (Exception $e) {
                        $message = 'Error adding rule: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;

            case 'update_late_rule':
                if (!\App\Auth::isAdmin()) {
                    $message = 'Only administrators can update late rules.';
                    $messageType = 'danger';
                } else {
                    try {
                        $lateCalculator = new \App\LateDeductionCalculator($db);
                        $tiers = [];
                        if (!empty($_POST['tier_min']) && is_array($_POST['tier_min'])) {
                            foreach ($_POST['tier_min'] as $i => $min) {
                                if ($min !== '' && isset($_POST['tier_max'][$i]) && isset($_POST['tier_amount'][$i])) {
                                    $tiers[] = [
                                        'min_minutes' => (int)$min,
                                        'max_minutes' => (int)$_POST['tier_max'][$i],
                                        'amount' => (float)$_POST['tier_amount'][$i]
                                    ];
                                }
                            }
                        }
                        $ruleData = [
                            'name' => $_POST['name'],
                            'work_start_time' => $_POST['work_start_time'],
                            'grace_minutes' => (int)($_POST['grace_minutes'] ?? 15),
                            'deduction_tiers' => $tiers,
                            'currency' => $_POST['currency'] ?? 'KES',
                            'apply_to_department_id' => $_POST['apply_to_department_id'] ?: null,
                            'is_default' => isset($_POST['is_default']),
                            'is_active' => isset($_POST['is_active'])
                        ];
                        $lateCalculator->updateRule((int)$_POST['rule_id'], $ruleData);
                        $message = 'Late deduction rule updated successfully!';
                        $messageType = 'success';
                        \App\Auth::regenerateToken();
                    } catch (Exception $e) {
                        $message = 'Error updating rule: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;

            case 'save_salesperson':
                if (!\App\Auth::isAdmin()) {
                    $message = 'Only administrators can add salespeople.';
                    $messageType = 'danger';
                } else {
                    $salesperson = new \App\Salesperson($db);
                    $name = trim($_POST['name'] ?? '');
                    $phone = trim($_POST['phone'] ?? '');
                    $employeeId = !empty($_POST['employee_id']) ? (int)$_POST['employee_id'] : null;
                    
                    if (empty($name) || empty($phone)) {
                        $message = 'Salesperson name and phone are required.';
                        $messageType = 'danger';
                    } elseif ($employeeId !== null) {
                        $empCheck = $db->prepare("SELECT id FROM employees WHERE id = ?");
                        $empCheck->execute([$employeeId]);
                        if (!$empCheck->fetch()) {
                            $message = 'Invalid employee selected.';
                            $messageType = 'danger';
                            break;
                        }
                    }
                    
                    if (empty($message)) {
                        try {
                            $spData = [
                                'name' => $name,
                                'email' => $_POST['email'] ?? null,
                                'phone' => $phone,
                                'employee_id' => $employeeId,
                                'commission_type' => $_POST['commission_type'] ?? 'percentage',
                                'commission_value' => floatval($_POST['commission_value'] ?? 10),
                                'is_active' => isset($_POST['is_active']) ? 1 : 0,
                                'notes' => $_POST['notes'] ?? null
                            ];
                            $salesperson->create($spData);
                            $message = 'Salesperson added successfully!';
                            $messageType = 'success';
                            \App\Auth::regenerateToken();
                            header('Location: ?page=hr&subpage=salespeople');
                            exit;
                        } catch (Exception $e) {
                            $message = 'Error adding salesperson: ' . $e->getMessage();
                            $messageType = 'danger';
                        }
                    }
                }
                break;

            case 'update_salesperson':
                if (!\App\Auth::isAdmin()) {
                    $message = 'Only administrators can update salespeople.';
                    $messageType = 'danger';
                } else {
                    $salesperson = new \App\Salesperson($db);
                    $spId = (int)($_POST['salesperson_id'] ?? 0);
                    $name = trim($_POST['name'] ?? '');
                    $phone = trim($_POST['phone'] ?? '');
                    $employeeId = !empty($_POST['employee_id']) ? (int)$_POST['employee_id'] : null;
                    
                    if (!$spId || empty($name) || empty($phone)) {
                        $message = 'Salesperson ID, name and phone are required.';
                        $messageType = 'danger';
                    } elseif ($employeeId !== null) {
                        $empCheck = $db->prepare("SELECT id FROM employees WHERE id = ?");
                        $empCheck->execute([$employeeId]);
                        if (!$empCheck->fetch()) {
                            $message = 'Invalid employee selected.';
                            $messageType = 'danger';
                            break;
                        }
                    }
                    
                    if (empty($message)) {
                        try {
                            $spData = [
                                'name' => $name,
                                'email' => $_POST['email'] ?? null,
                                'phone' => $phone,
                                'employee_id' => $employeeId,
                                'commission_type' => $_POST['commission_type'] ?? 'percentage',
                                'commission_value' => floatval($_POST['commission_value'] ?? 10),
                                'is_active' => isset($_POST['is_active']) ? 1 : 0,
                                'notes' => $_POST['notes'] ?? null
                            ];
                            $salesperson->update($spId, $spData);
                            $message = 'Salesperson updated successfully!';
                            $messageType = 'success';
                            \App\Auth::regenerateToken();
                            header('Location: ?page=hr&subpage=salespeople');
                            exit;
                        } catch (Exception $e) {
                            $message = 'Error updating salesperson: ' . $e->getMessage();
                            $messageType = 'danger';
                        }
                    }
                }
                break;

            case 'delete_salesperson':
                if (!\App\Auth::isAdmin()) {
                    $message = 'Only administrators can delete salespersons.';
                    $messageType = 'danger';
                } else {
                    try {
                        $salesperson = new \App\Salesperson($db);
                        $spId = (int)($_POST['salesperson_id'] ?? 0);
                        if ($spId) {
                            $salesperson->delete($spId);
                            $message = 'Salesperson deleted successfully!';
                            $messageType = 'success';
                            \App\Auth::regenerateToken();
                        }
                    } catch (Exception $e) {
                        $message = 'Error deleting salesperson: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;

            case 'pay_commission':
                try {
                    $salesperson = new \App\Salesperson($db);
                    $commissionId = (int)($_POST['commission_id'] ?? 0);
                    if ($commissionId) {
                        $salesperson->markCommissionPaid($commissionId);
                        $message = 'Commission marked as paid!';
                        $messageType = 'success';
                        \App\Auth::regenerateToken();
                    }
                } catch (Exception $e) {
                    $message = 'Error marking commission as paid: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'pay_all_commissions':
                try {
                    $salesperson = new \App\Salesperson($db);
                    $spId = (int)($_POST['salesperson_id'] ?? 0);
                    if ($spId) {
                        $salesperson->markAllCommissionsPaid($spId);
                        $message = 'All pending commissions marked as paid!';
                        $messageType = 'success';
                        \App\Auth::regenerateToken();
                    }
                } catch (Exception $e) {
                    $message = 'Error marking commissions as paid: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'save_commission_settings':
                try {
                    $settings->saveSetting('default_commission_type', $_POST['default_commission_type'] ?? 'percentage');
                    $settings->saveSetting('default_commission_value', $_POST['default_commission_value'] ?? '10');
                    $settings->saveSetting('min_commission_order_amount', $_POST['min_commission_order_amount'] ?? '0');
                    $settings->saveSetting('auto_mark_commission_paid', isset($_POST['auto_mark_commission_paid']) ? '1' : '0');
                    \App\Settings::clearCache();
                    $message = 'Commission settings saved successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error saving commission settings: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'seed_commission_rates':
                try {
                    $ticketCommission = new \App\TicketCommission($db);
                    $ticketCommission->seedDefaultRates();
                    $message = 'Default commission rates loaded successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error loading default rates: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'add_commission_rate':
                try {
                    $ticketCommission = new \App\TicketCommission($db);
                    $ticketCommission->addRate([
                        'category' => $_POST['category'],
                        'rate' => (float)$_POST['rate'],
                        'currency' => $_POST['currency'] ?? 'KES',
                        'description' => $_POST['description'] ?? null,
                        'is_active' => isset($_POST['is_active']),
                        'require_sla_compliance' => isset($_POST['require_sla_compliance'])
                    ]);
                    $message = 'Commission rate added successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error adding commission rate: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'update_commission_rate':
                try {
                    $ticketCommission = new \App\TicketCommission($db);
                    $ticketCommission->updateRate((int)$_POST['rate_id'], [
                        'category' => $_POST['category'],
                        'rate' => (float)$_POST['rate'],
                        'currency' => $_POST['currency'] ?? 'KES',
                        'description' => $_POST['description'] ?? null,
                        'is_active' => isset($_POST['is_active']),
                        'require_sla_compliance' => isset($_POST['require_sla_compliance'])
                    ]);
                    $message = 'Commission rate updated successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error updating commission rate: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'delete_commission_rate':
                try {
                    $ticketCommission = new \App\TicketCommission($db);
                    $ticketCommission->deleteRate((int)$_POST['rate_id']);
                    $message = 'Commission rate deleted successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error deleting commission rate: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'seed_ticket_categories':
                try {
                    $ticketModel = new \App\Ticket($db);
                    $ticketModel->seedDefaultCategories();
                    $message = 'Default ticket categories loaded successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error loading default categories: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'add_ticket_category':
                try {
                    $ticketModel = new \App\Ticket($db);
                    $ticketModel->addCategory([
                        'key' => $_POST['key'],
                        'label' => $_POST['label'],
                        'description' => $_POST['description'] ?? null,
                        'color' => $_POST['color'] ?? 'primary',
                        'display_order' => (int)($_POST['display_order'] ?? 0),
                        'is_active' => isset($_POST['is_active'])
                    ]);
                    $message = 'Ticket category added successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error adding ticket category: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'update_ticket_category':
                try {
                    $ticketModel = new \App\Ticket($db);
                    $ticketModel->updateCategory((int)$_POST['category_id'], [
                        'label' => $_POST['label'],
                        'description' => $_POST['description'] ?? null,
                        'color' => $_POST['color'] ?? 'primary',
                        'display_order' => (int)($_POST['display_order'] ?? 0),
                        'is_active' => isset($_POST['is_active'])
                    ]);
                    $message = 'Ticket category updated successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error updating ticket category: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'delete_ticket_category':
                try {
                    $ticketModel = new \App\Ticket($db);
                    $ticketModel->deleteCategory((int)$_POST['category_id']);
                    $message = 'Ticket category deleted successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error deleting ticket category: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'create_service_fee':
                try {
                    $serviceFee = new \App\ServiceFee($db);
                    $serviceFee->createFeeType([
                        'name' => $_POST['name'],
                        'description' => $_POST['description'] ?? null,
                        'default_amount' => $_POST['default_amount'] ?? 0,
                        'currency' => $_POST['currency'] ?? 'KES',
                        'is_active' => isset($_POST['is_active']),
                        'display_order' => $_POST['display_order'] ?? 0
                    ]);
                    $message = 'Service fee created successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error creating service fee: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;
                
            case 'update_service_fee':
                try {
                    $serviceFee = new \App\ServiceFee($db);
                    $serviceFee->updateFeeType((int)$_POST['fee_id'], [
                        'name' => $_POST['name'],
                        'description' => $_POST['description'] ?? null,
                        'default_amount' => $_POST['default_amount'] ?? 0,
                        'currency' => $_POST['currency'] ?? 'KES',
                        'is_active' => isset($_POST['is_active']),
                        'display_order' => $_POST['display_order'] ?? 0
                    ]);
                    $message = 'Service fee updated successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                    header('Location: ?page=settings&subpage=service_fees');
                    exit;
                } catch (Exception $e) {
                    $message = 'Error updating service fee: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;
                
            case 'delete_service_fee':
                try {
                    $serviceFee = new \App\ServiceFee($db);
                    $serviceFee->deleteFeeType((int)$_POST['fee_id']);
                    $message = 'Service fee deleted successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error deleting service fee: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'save_mobile_settings':
                try {
                    $mobileData = [
                        'mobile_enabled' => isset($_POST['mobile_enabled']) ? '1' : '0',
                        'mobile_salesperson_enabled' => isset($_POST['mobile_salesperson_enabled']) ? '1' : '0',
                        'mobile_technician_enabled' => isset($_POST['mobile_technician_enabled']) ? '1' : '0',
                        'mobile_token_expiry_days' => $_POST['mobile_token_expiry_days'] ?? '30',
                        'mobile_app_name' => $_POST['mobile_app_name'] ?? 'ISP Mobile',
                        'mobile_require_location' => isset($_POST['mobile_require_location']) ? '1' : '0',
                        'mobile_allow_offline' => isset($_POST['mobile_allow_offline']) ? '1' : '0',
                        'mobile_restrict_clockin_ip' => isset($_POST['mobile_restrict_clockin_ip']) ? '1' : '0',
                        'mobile_allowed_ips' => trim($_POST['mobile_allowed_ips'] ?? ''),
                    ];
                    $settings->saveMobileAppSettings($mobileData);
                    \App\Settings::clearCache();
                    $message = 'Mobile app settings saved successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error saving mobile settings: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'create_role':
                if (!\App\Auth::can('roles.manage')) {
                    $message = 'You do not have permission to create roles.';
                    $messageType = 'danger';
                } else {
                    try {
                        $roleManager = new \App\Role($db);
                        $roleId = $roleManager->createRole($_POST);
                        if (!empty($_POST['permissions'])) {
                            $roleManager->setRolePermissions($roleId, $_POST['permissions']);
                        }
                        $message = 'Role created successfully!';
                        $messageType = 'success';
                        \App\Auth::regenerateToken();
                    } catch (Exception $e) {
                        $message = 'Error creating role: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;

            case 'update_role':
                if (!\App\Auth::can('roles.manage')) {
                    $message = 'You do not have permission to update roles.';
                    $messageType = 'danger';
                } else {
                    try {
                        $roleManager = new \App\Role($db);
                        $roleId = (int)($_POST['role_id'] ?? 0);
                        $roleManager->updateRole($roleId, $_POST);
                        $roleManager->setRolePermissions($roleId, $_POST['permissions'] ?? []);
                        $message = 'Role updated successfully!';
                        $messageType = 'success';
                        \App\Auth::regenerateToken();
                    } catch (Exception $e) {
                        $message = 'Error updating role: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;

            case 'delete_role':
                if (!\App\Auth::can('roles.manage')) {
                    $message = 'You do not have permission to delete roles.';
                    $messageType = 'danger';
                } else {
                    try {
                        $roleManager = new \App\Role($db);
                        $roleId = (int)($_POST['role_id'] ?? 0);
                        $role = $roleManager->getRole($roleId);
                        if ($role && $role['is_system']) {
                            $message = 'Cannot delete system roles.';
                            $messageType = 'danger';
                        } else {
                            $roleManager->deleteRole($roleId);
                            $message = 'Role deleted successfully!';
                            $messageType = 'success';
                            \App\Auth::regenerateToken();
                        }
                    } catch (Exception $e) {
                        $message = 'Error deleting role: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;

            case 'create_user':
                if (!\App\Auth::can('users.manage')) {
                    $message = 'You do not have permission to create users.';
                    $messageType = 'danger';
                } else {
                    try {
                        $roleManager = new \App\Role($db);
                        if (strlen($_POST['password'] ?? '') < 6) {
                            throw new Exception('Password must be at least 6 characters.');
                        }
                        $roleManager->createUser($_POST);
                        $message = 'User created successfully!';
                        $messageType = 'success';
                        \App\Auth::regenerateToken();
                    } catch (Exception $e) {
                        $message = 'Error creating user: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;

            case 'update_user':
                if (!\App\Auth::can('users.manage')) {
                    $message = 'You do not have permission to update users.';
                    $messageType = 'danger';
                } else {
                    try {
                        $roleManager = new \App\Role($db);
                        $userId = (int)($_POST['user_id'] ?? 0);
                        if (!empty($_POST['password']) && strlen($_POST['password']) < 6) {
                            throw new Exception('Password must be at least 6 characters.');
                        }
                        $roleManager->updateUser($userId, $_POST);
                        $message = 'User updated successfully!';
                        $messageType = 'success';
                        \App\Auth::regenerateToken();
                    } catch (Exception $e) {
                        $message = 'Error updating user: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;

            case 'delete_user':
                if (!\App\Auth::can('users.manage')) {
                    $message = 'You do not have permission to delete users.';
                    $messageType = 'danger';
                } else {
                    try {
                        $roleManager = new \App\Role($db);
                        $userId = (int)($_POST['user_id'] ?? 0);
                        if ($userId === \App\Auth::userId()) {
                            throw new Exception('You cannot delete your own account.');
                        }
                        $roleManager->deleteUser($userId);
                        $message = 'User deleted successfully!';
                        $messageType = 'success';
                        \App\Auth::regenerateToken();
                    } catch (Exception $e) {
                        $message = 'Error deleting user: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;

            case 'add_device':
                if (!\App\Auth::can('settings.manage')) {
                    $message = 'You do not have permission to add devices.';
                    $messageType = 'danger';
                } else {
                    try {
                        $deviceMonitor = new \App\DeviceMonitor($db);
                        $deviceMonitor->initializeTables();
                        $deviceId = $deviceMonitor->addDevice($_POST);
                        if ($page === 'devices') {
                            header('Content-Type: application/json');
                            echo json_encode(['success' => true, 'id' => $deviceId]);
                            exit;
                        }
                        $message = 'Device added successfully!';
                        $messageType = 'success';
                        \App\Auth::regenerateToken();
                    } catch (Exception $e) {
                        if ($page === 'devices') {
                            header('Content-Type: application/json');
                            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                            exit;
                        }
                        $message = 'Error adding device: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;

        }
    }
}

if ($page === 'devices' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? $_POST['action'] ?? '';
    
    if (!\App\Auth::can('settings.view')) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit;
    }
    
    $deviceMonitor = new \App\DeviceMonitor($db);
    $deviceMonitor->initializeTables();
    
    switch ($action) {
        case 'add_device':
            if (!\App\Auth::can('settings.manage')) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Permission denied']);
                exit;
            }
            try {
                $deviceId = $deviceMonitor->addDevice($_POST);
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'id' => $deviceId]);
            } catch (Exception $e) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
            
        case 'test_device':
            $result = $deviceMonitor->testConnection((int)$input['id']);
            header('Content-Type: application/json');
            echo json_encode($result);
            exit;
            
        case 'poll_device':
            $result = $deviceMonitor->pollInterfaces((int)$input['id']);
            header('Content-Type: application/json');
            echo json_encode($result);
            exit;
            
        case 'delete_device':
            if (!\App\Auth::can('settings.manage')) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Permission denied']);
                exit;
            }
            $result = $deviceMonitor->deleteDevice((int)$input['id']);
            header('Content-Type: application/json');
            echo json_encode(['success' => $result]);
            exit;
            
        case 'get_interfaces':
            $stmt = $db->prepare("SELECT * FROM device_interfaces WHERE device_id = ? ORDER BY if_index");
            $stmt->execute([(int)$input['id']]);
            $interfaces = $stmt->fetchAll(PDO::FETCH_ASSOC);
            header('Content-Type: application/json');
            echo json_encode(['interfaces' => $interfaces]);
            exit;
            
        case 'telnet_command':
            if (!\App\Auth::can('settings.manage')) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Permission denied']);
                exit;
            }
            $device = $deviceMonitor->getDevice((int)$input['id']);
            if ($device && $device['vendor'] === 'Huawei') {
                $result = $deviceMonitor->huaweiCommand((int)$input['id'], $input['command']);
            } else {
                $result = $deviceMonitor->telnetCommand($device, $input['command']);
            }
            header('Content-Type: application/json');
            echo json_encode($result);
            exit;
            
        case 'get_device_info':
            $result = $deviceMonitor->getDeviceInfo((int)$input['id']);
            header('Content-Type: application/json');
            echo json_encode($result);
            exit;
            
        case 'get_interface_history':
            $interfaceId = (int)($input['interface_id'] ?? 0);
            $hours = (int)($input['hours'] ?? 24);
            $history = $deviceMonitor->getInterfaceHistory($interfaceId, $hours);
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'data' => $history]);
            exit;
            
        case 'get_traffic_summary':
            $deviceId = (int)($input['device_id'] ?? $input['id'] ?? 0);
            $hours = (int)($input['hours'] ?? 24);
            $summary = $deviceMonitor->getDeviceTrafficSummary($deviceId, $hours);
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'data' => $summary]);
            exit;
            
        case 'get_vlans':
            $deviceId = (int)($input['device_id'] ?? 0);
            $vlans = $deviceMonitor->getVlanTrafficSummary($deviceId, 24);
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'vlans' => $vlans]);
            exit;
            
        case 'poll_vlans':
            if (!\App\Auth::can('settings.manage')) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Permission denied']);
                exit;
            }
            $deviceId = (int)($input['device_id'] ?? 0);
            $result = $deviceMonitor->pollVlans($deviceId);
            header('Content-Type: application/json');
            echo json_encode($result);
            exit;
            
        case 'get_vlan_history':
            $vlanId = (int)($input['vlan_id'] ?? 0);
            $hours = (int)($input['hours'] ?? 24);
            $history = $deviceMonitor->getVlanHistory($vlanId, $hours);
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'data' => $history]);
            exit;
    }
}

if ($page === 'hr' && $_SERVER['REQUEST_METHOD'] === 'POST' && \App\Auth::validateToken($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_team':
            if (!\App\Auth::isAdmin()) {
                $message = 'Only administrators can create teams.';
                $messageType = 'danger';
            } else {
                try {
                    $ticketManager = new \App\Ticket();
                    $ticketManager->createTeam($_POST);
                    $message = 'Team created successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error creating team: ' . $e->getMessage();
                    $messageType = 'danger';
                }
            }
            break;

        case 'update_team':
            if (!\App\Auth::isAdmin()) {
                $message = 'Only administrators can update teams.';
                $messageType = 'danger';
            } else {
                try {
                    $ticketManager = new \App\Ticket();
                    $teamId = (int)($_POST['team_id'] ?? 0);
                    $ticketManager->updateTeam($teamId, [
                        'name' => $_POST['name'] ?? '',
                        'description' => $_POST['description'] ?? null,
                        'leader_id' => $_POST['leader_id'] ?: null,
                        'is_active' => isset($_POST['is_active'])
                    ]);
                    $message = 'Team updated successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error updating team: ' . $e->getMessage();
                    $messageType = 'danger';
                }
            }
            break;

        case 'delete_team':
            if (!\App\Auth::isAdmin()) {
                $message = 'Only administrators can delete teams.';
                $messageType = 'danger';
            } else {
                try {
                    $ticketManager = new \App\Ticket();
                    $teamId = (int)($_POST['team_id'] ?? 0);
                    $ticketManager->deleteTeam($teamId);
                    $message = 'Team deleted successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error deleting team: ' . $e->getMessage();
                    $messageType = 'danger';
                }
            }
            break;

        case 'change_employee_password':
            if (!\App\Auth::isAdmin()) {
                $message = 'Only administrators can change employee passwords.';
                $messageType = 'danger';
            } else {
                $employeeId = (int)($_POST['employee_id'] ?? 0);
                $newPassword = $_POST['new_password'] ?? '';
                $confirmPassword = $_POST['confirm_password'] ?? '';
                
                if ($newPassword !== $confirmPassword) {
                    $message = 'Passwords do not match.';
                    $messageType = 'danger';
                } elseif (strlen($newPassword) < 6) {
                    $message = 'Password must be at least 6 characters.';
                    $messageType = 'danger';
                } else {
                    $result = $employee->changeUserPassword($employeeId, $newPassword);
                    if ($result['success']) {
                        $message = $result['message'];
                        $messageType = 'success';
                        \App\Auth::regenerateToken();
                    } else {
                        $message = $result['error'];
                        $messageType = 'danger';
                    }
                }
            }
            break;

        case 'add_team_member':
            if (!\App\Auth::isAdmin()) {
                $message = 'Only administrators can add team members.';
                $messageType = 'danger';
            } else {
                try {
                    $ticketManager = new \App\Ticket();
                    $teamId = (int)($_POST['team_id'] ?? 0);
                    $employeeId = (int)($_POST['employee_id'] ?? 0);
                    $ticketManager->addTeamMember($teamId, $employeeId);
                    $message = 'Team member added successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error adding team member: ' . $e->getMessage();
                    $messageType = 'danger';
                }
            }
            break;

        case 'remove_team_member':
            if (!\App\Auth::isAdmin()) {
                $message = 'Only administrators can remove team members.';
                $messageType = 'danger';
            } else {
                try {
                    $ticketManager = new \App\Ticket();
                    $teamId = (int)($_POST['team_id'] ?? 0);
                    $employeeId = (int)($_POST['employee_id'] ?? 0);
                    $ticketManager->removeTeamMember($teamId, $employeeId);
                    $message = 'Team member removed successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error removing team member: ' . $e->getMessage();
                    $messageType = 'danger';
                }
            }
            break;

        case 'remove_late_penalty':
            try {
                $attendanceId = (int)$_POST['attendance_id'];
                $stmt = $db->prepare("UPDATE attendance SET late_minutes = 0, deduction = 0 WHERE id = ?");
                $stmt->execute([$attendanceId]);
                $message = 'Late penalty removed successfully!';
                $messageType = 'success';
                \App\Auth::regenerateToken();
            } catch (Exception $e) {
                $message = 'Error: ' . $e->getMessage();
                $messageType = 'danger';
            }
            break;

        case 'update_late_penalty':
            try {
                $attendanceId = (int)$_POST['attendance_id'];
                $lateMinutes = max(0, (int)$_POST['late_minutes']);
                $deduction = max(0, (float)$_POST['deduction']);
                $reason = trim($_POST['adjustment_reason'] ?? '');
                
                $stmt = $db->prepare("UPDATE attendance SET late_minutes = ?, deduction = ? WHERE id = ?");
                $stmt->execute([$lateMinutes, $deduction, $attendanceId]);
                
                if (!empty($reason)) {
                    \App\ActivityLog::log($db, 'attendance', $attendanceId, 'penalty_adjusted', 
                        "Late penalty adjusted: {$lateMinutes} min, KES {$deduction}. Reason: {$reason}");
                }
                
                $message = 'Late penalty updated successfully!';
                $messageType = 'success';
                \App\Auth::regenerateToken();
            } catch (Exception $e) {
                $message = 'Error: ' . $e->getMessage();
                $messageType = 'danger';
            }
            break;
    }
}

if ($page === 'settings' && $action === 'delete_device' && isset($_GET['id'])) {
    if (!\App\Auth::isAdmin()) {
        $message = 'Only administrators can delete devices.';
        $messageType = 'danger';
    } else {
        try {
            $biometricService = new \App\BiometricSyncService($db);
            $biometricService->deleteDevice((int)$_GET['id']);
            $message = 'Biometric device deleted successfully!';
            $messageType = 'success';
        } catch (Exception $e) {
            $message = 'Error deleting device: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

if ($page === 'settings' && $action === 'delete_mapping' && isset($_GET['device_id']) && isset($_GET['device_user_id'])) {
    if (!\App\Auth::isAdmin()) {
        $message = 'Only administrators can delete mappings.';
        $messageType = 'danger';
    } else {
        try {
            $biometricService = new \App\BiometricSyncService($db);
            $biometricService->deleteUserMapping((int)$_GET['device_id'], $_GET['device_user_id']);
            $message = 'User mapping deleted successfully!';
            $messageType = 'success';
        } catch (Exception $e) {
            $message = 'Error deleting mapping: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

if ($page === 'settings' && $action === 'delete_rule' && isset($_GET['id'])) {
    if (!\App\Auth::isAdmin()) {
        $message = 'Only administrators can delete rules.';
        $messageType = 'danger';
    } else {
        try {
            $lateCalculator = new \App\LateDeductionCalculator($db);
            $lateCalculator->deleteRule((int)$_GET['id']);
            $message = 'Late deduction rule deleted successfully!';
            $messageType = 'success';
        } catch (Exception $e) {
            $message = 'Error deleting rule: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

if ($page === 'hr' && $action === 'sync_biometric') {
    if (!\App\Auth::isAdmin()) {
        $message = 'Only administrators can sync biometric devices.';
        $messageType = 'danger';
    } else {
        try {
            $biometricService = new \App\BiometricSyncService($db);
            $syncResults = $biometricService->syncAllDevices();
            $successCount = count(array_filter($syncResults, fn($r) => $r['success']));
            $totalCount = count($syncResults);
            if ($successCount === $totalCount) {
                $message = "All {$totalCount} device(s) synced successfully!";
                $messageType = 'success';
            } elseif ($successCount > 0) {
                $message = "{$successCount} of {$totalCount} device(s) synced successfully. Some devices failed to sync.";
                $messageType = 'warning';
            } else {
                $message = "Failed to sync devices. Please check device configuration.";
                $messageType = 'danger';
            }
        } catch (Exception $e) {
            $message = 'Error syncing devices: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

if ($page === 'inventory' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $inventory = new \App\Inventory($db);
    $tab = $_GET['tab'] ?? 'equipment';
    $inventoryAction = $_GET['action'] ?? '';
    
    $csrfValid = \App\Auth::validateToken($_POST['csrf_token'] ?? '');
    if (!$csrfValid) {
        $_SESSION['error_message'] = 'Invalid request. Please try again.';
    } else {
        try {
            switch ($tab) {
                case 'equipment':
                    if ($inventoryAction === 'save') {
                        $data = [
                            'category_id' => $_POST['category_id'] ?: null,
                            'name' => $_POST['name'],
                            'brand' => $_POST['brand'] ?? null,
                            'model' => $_POST['model'] ?? null,
                            'serial_number' => $_POST['serial_number'] ?? null,
                            'mac_address' => $_POST['mac_address'] ?? null,
                            'purchase_date' => $_POST['purchase_date'] ?: null,
                            'purchase_price' => $_POST['purchase_price'] ?: null,
                            'warranty_expiry' => $_POST['warranty_expiry'] ?: null,
                            'condition' => $_POST['condition'] ?? 'new',
                            'status' => $_POST['status'] ?? 'available',
                            'location' => $_POST['location'] ?? null,
                            'notes' => $_POST['notes'] ?? null
                        ];
                        if (!empty($_POST['id'])) {
                            $inventory->updateEquipment((int)$_POST['id'], $data);
                            $_SESSION['success_message'] = 'Equipment updated successfully!';
                        } else {
                            $quantity = max(1, min(500, (int)($_POST['quantity'] ?? 1)));
                            if ($quantity === 1) {
                                $inventory->addEquipment($data);
                                $_SESSION['success_message'] = 'Equipment added successfully!';
                            } else {
                                $baseName = $data['name'];
                                $baseSerial = $data['serial_number'];
                                $baseMac = $data['mac_address'];
                                
                                for ($i = 1; $i <= $quantity; $i++) {
                                    $itemData = $data;
                                    $itemData['name'] = $baseName . ' #' . str_pad($i, 3, '0', STR_PAD_LEFT);
                                    if (!empty($baseSerial)) {
                                        $itemData['serial_number'] = $baseSerial . '-' . str_pad($i, 3, '0', STR_PAD_LEFT);
                                    }
                                    if (!empty($baseMac)) {
                                        $itemData['mac_address'] = null;
                                    }
                                    $inventory->addEquipment($itemData);
                                }
                                $_SESSION['success_message'] = "{$quantity} equipment items added successfully!";
                            }
                        }
                        \App\Auth::regenerateToken();
                        header('Location: ?page=inventory&tab=equipment');
                        exit;
                    } elseif ($inventoryAction === 'delete') {
                        if (!\App\Auth::isAdmin()) {
                            $_SESSION['error_message'] = 'Only administrators can delete equipment.';
                        } else {
                            $inventory->deleteEquipment((int)$_POST['id']);
                            $_SESSION['success_message'] = 'Equipment deleted successfully!';
                            \App\Auth::regenerateToken();
                        }
                        header('Location: ?page=inventory&tab=equipment');
                        exit;
                    }
                    break;
                    
                case 'categories':
                    if ($inventoryAction === 'save') {
                        $data = [
                            'name' => $_POST['name'],
                            'description' => $_POST['description'] ?? null
                        ];
                        if (!empty($_POST['id'])) {
                            $inventory->updateCategory((int)$_POST['id'], $data);
                            $_SESSION['success_message'] = 'Category updated successfully!';
                        } else {
                            $inventory->addCategory($data);
                            $_SESSION['success_message'] = 'Category added successfully!';
                        }
                        \App\Auth::regenerateToken();
                        header('Location: ?page=inventory&tab=categories');
                        exit;
                    } elseif ($inventoryAction === 'delete') {
                        if (!\App\Auth::isAdmin()) {
                            $_SESSION['error_message'] = 'Only administrators can delete categories.';
                        } else {
                            $inventory->deleteCategory((int)$_POST['id']);
                            $_SESSION['success_message'] = 'Category deleted successfully!';
                            \App\Auth::regenerateToken();
                        }
                        header('Location: ?page=inventory&tab=categories');
                        exit;
                    }
                    break;
                    
                case 'assignments':
                    if ($inventoryAction === 'assign') {
                        $data = [
                            'equipment_id' => (int)$_POST['equipment_id'],
                            'employee_id' => (int)$_POST['employee_id'],
                            'assignment_date' => $_POST['assignment_date'] ?? date('Y-m-d'),
                            'assigned_by' => $currentUser['id'],
                            'notes' => $_POST['notes'] ?? null
                        ];
                        $inventory->assignToEmployee($data);
                        $_SESSION['success_message'] = 'Equipment assigned successfully!';
                        \App\Auth::regenerateToken();
                        header('Location: ?page=inventory&tab=assignments');
                        exit;
                    } elseif ($inventoryAction === 'return') {
                        $inventory->returnFromEmployee((int)$_POST['assignment_id']);
                        $_SESSION['success_message'] = 'Equipment returned successfully!';
                        \App\Auth::regenerateToken();
                        header('Location: ?page=inventory&tab=assignments');
                        exit;
                    }
                    break;
                    
                case 'loans':
                    if ($inventoryAction === 'loan') {
                        $data = [
                            'equipment_id' => (int)$_POST['equipment_id'],
                            'customer_id' => (int)$_POST['customer_id'],
                            'loan_date' => $_POST['loan_date'] ?? date('Y-m-d'),
                            'expected_return_date' => $_POST['expected_return_date'] ?: null,
                            'loaned_by' => $currentUser['id'],
                            'deposit_amount' => $_POST['deposit_amount'] ?? 0,
                            'deposit_paid' => isset($_POST['deposit_paid']),
                            'notes' => $_POST['notes'] ?? null
                        ];
                        $inventory->loanToCustomer($data);
                        $_SESSION['success_message'] = 'Equipment loaned successfully!';
                        \App\Auth::regenerateToken();
                        header('Location: ?page=inventory&tab=loans');
                        exit;
                    } elseif ($inventoryAction === 'return') {
                        $inventory->returnFromCustomer((int)$_POST['loan_id']);
                        $_SESSION['success_message'] = 'Equipment returned successfully!';
                        \App\Auth::regenerateToken();
                        header('Location: ?page=inventory&tab=loans');
                        exit;
                    }
                    break;
                    
                case 'faults':
                    if ($inventoryAction === 'report') {
                        $data = [
                            'equipment_id' => (int)$_POST['equipment_id'],
                            'reported_date' => $_POST['reported_date'] ?? date('Y-m-d'),
                            'reported_by' => $currentUser['id'],
                            'fault_description' => $_POST['fault_description'],
                            'severity' => $_POST['severity'] ?? 'minor'
                        ];
                        $inventory->reportFault($data);
                        $_SESSION['success_message'] = 'Fault reported successfully!';
                        \App\Auth::regenerateToken();
                        header('Location: ?page=inventory&tab=faults');
                        exit;
                    } elseif ($inventoryAction === 'mark_repaired') {
                        $data = [
                            'repair_date' => $_POST['repair_date'] ?? date('Y-m-d'),
                            'repair_cost' => $_POST['repair_cost'] ?: null,
                            'repair_notes' => $_POST['repair_notes'] ?? null
                        ];
                        $inventory->markFaultRepaired((int)$_POST['fault_id'], $data);
                        $_SESSION['success_message'] = 'Equipment marked as repaired!';
                        \App\Auth::regenerateToken();
                        header('Location: ?page=inventory&tab=faults');
                        exit;
                    }
                    break;
                    
                case 'import':
                    if ($inventoryAction === 'import') {
                        if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
                            $uploadErrors = [
                                UPLOAD_ERR_INI_SIZE => 'File exceeds maximum upload size.',
                                UPLOAD_ERR_FORM_SIZE => 'File exceeds maximum form size.',
                                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
                                UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
                                UPLOAD_ERR_NO_TMP_DIR => 'Server configuration error (no temp folder).',
                                UPLOAD_ERR_CANT_WRITE => 'Server error (cannot write file).',
                            ];
                            $errorCode = $_FILES['import_file']['error'] ?? UPLOAD_ERR_NO_FILE;
                            throw new Exception($uploadErrors[$errorCode] ?? 'Please select a valid file to import.');
                        }
                        $file = $_FILES['import_file'];
                        $maxSize = 10 * 1024 * 1024; // 10MB max
                        if ($file['size'] > $maxSize) {
                            throw new Exception('File is too large. Maximum size is 10MB.');
                        }
                        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                        if (!in_array($ext, ['xlsx', 'xls', 'csv'])) {
                            throw new Exception('Invalid file format. Please use Excel (.xlsx, .xls) or CSV (.csv) files.');
                        }
                        $results = $inventory->importFromExcel($file['tmp_name']);
                        if ($results['success'] > 0) {
                            $msg = "Successfully imported {$results['success']} equipment item(s).";
                            if ($results['failed'] > 0) {
                                $msg .= " {$results['failed']} row(s) failed.";
                            }
                            $_SESSION['success_message'] = $msg;
                        } else {
                            $_SESSION['error_message'] = 'Import failed. ' . implode('; ', $results['errors']);
                        }
                        \App\Auth::regenerateToken();
                        header('Location: ?page=inventory&tab=import');
                        exit;
                    } elseif ($inventoryAction === 'bulk_add') {
                        $items = $_POST['items'] ?? [];
                        $items = array_filter($items, fn($item) => !empty($item['name']));
                        if (empty($items)) {
                            throw new Exception('Please add at least one equipment item with a name.');
                        }
                        $results = $inventory->bulkAddEquipment($items);
                        if ($results['success'] > 0) {
                            $msg = "Successfully added {$results['success']} equipment item(s).";
                            if ($results['failed'] > 0) {
                                $msg .= " {$results['failed']} row(s) failed.";
                            }
                            $_SESSION['success_message'] = $msg;
                        } else {
                            $_SESSION['error_message'] = 'Bulk add failed. ' . implode('; ', $results['errors']);
                        }
                        \App\Auth::regenerateToken();
                        header('Location: ?page=inventory&tab=import');
                        exit;
                    }
                    break;
                    
                case 'kits':
                    if ($inventoryAction === 'save') {
                        $data = [
                            'kit_name' => $_POST['kit_name'],
                            'technician_id' => $_POST['technician_id'] ?: null,
                            'status' => $_POST['status'] ?? 'active',
                            'issued_at' => $_POST['issued_at'] ?? date('Y-m-d'),
                            'created_by' => $currentUser['id'],
                            'notes' => $_POST['notes'] ?? null
                        ];
                        if (!empty($_POST['id'])) {
                            $inventory->updateTechnicianKit((int)$_POST['id'], $data);
                            $_SESSION['success_message'] = 'Technician kit updated successfully!';
                        } else {
                            $inventory->createTechnicianKit($data);
                            $_SESSION['success_message'] = 'Technician kit created successfully!';
                        }
                        \App\Auth::regenerateToken();
                        header('Location: ?page=inventory&tab=kits');
                        exit;
                    } elseif ($inventoryAction === 'add_item') {
                        $inventory->addKitItem(
                            (int)$_POST['kit_id'],
                            (int)$_POST['equipment_id'],
                            (int)($_POST['quantity'] ?? 1),
                            $_POST['notes'] ?? null
                        );
                        $_SESSION['success_message'] = 'Item added to kit successfully!';
                        \App\Auth::regenerateToken();
                        header('Location: ?page=inventory&tab=kits&action=view&id=' . $_POST['kit_id']);
                        exit;
                    } elseif ($inventoryAction === 'remove_item') {
                        $inventory->removeKitItem((int)$_POST['item_id']);
                        $_SESSION['success_message'] = 'Item removed from kit.';
                        \App\Auth::regenerateToken();
                        header('Location: ?page=inventory&tab=kits&action=view&id=' . $_POST['kit_id']);
                        exit;
                    } elseif ($inventoryAction === 'return') {
                        $inventory->returnTechnicianKit((int)$_POST['id']);
                        $_SESSION['success_message'] = 'Kit marked as returned.';
                        \App\Auth::regenerateToken();
                        header('Location: ?page=inventory&tab=kits');
                        exit;
                    }
                    break;
                    
                case 'thresholds':
                    if ($inventoryAction === 'save') {
                        $data = [
                            'id' => $_POST['id'] ?: null,
                            'category_id' => $_POST['category_id'] ?: null,
                            'warehouse_id' => $_POST['warehouse_id'] ?: null,
                            'min_quantity' => (int)($_POST['min_quantity'] ?? 0),
                            'max_quantity' => (int)($_POST['max_quantity'] ?? 0),
                            'reorder_point' => (int)($_POST['reorder_point'] ?? 0),
                            'reorder_quantity' => (int)($_POST['reorder_quantity'] ?? 0),
                            'notify_on_low' => isset($_POST['notify_on_low']),
                            'notify_on_excess' => isset($_POST['notify_on_excess'])
                        ];
                        $inventory->saveThreshold($data);
                        $_SESSION['success_message'] = 'Stock threshold saved successfully!';
                        \App\Auth::regenerateToken();
                        header('Location: ?page=inventory&tab=thresholds');
                        exit;
                    } elseif ($inventoryAction === 'delete') {
                        $inventory->deleteThreshold((int)$_POST['id']);
                        $_SESSION['success_message'] = 'Threshold deleted.';
                        \App\Auth::regenerateToken();
                        header('Location: ?page=inventory&tab=thresholds');
                        exit;
                    }
                    break;
            }
        } catch (Exception $e) {
            $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
        }
    }
}

// Handle inventory GET actions (template download and export)
if ($page === 'inventory' && isset($_GET['action'])) {
    $inventory = new \App\Inventory();
    $inventoryAction = $_GET['action'];
    
    if ($inventoryAction === 'download_template') {
        $spreadsheet = $inventory->generateImportTemplate();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="equipment_import_template.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    } elseif ($inventoryAction === 'export') {
        $filters = [];
        if (!empty($_GET['status'])) $filters['status'] = $_GET['status'];
        if (!empty($_GET['category_id'])) $filters['category_id'] = (int)$_GET['category_id'];
        $spreadsheet = $inventory->exportEquipment($filters);
        $filename = 'equipment_export_' . date('Y-m-d_His') . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
}

// Redirect old payments page to accounting payments
if ($page === 'payments') {
    header('Location: ?page=accounting&subpage=payments');
    exit;
}

// Handle M-Pesa payments (legacy - kept for backwards compatibility)
if ($page === 'payments_legacy' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $mpesa = new \App\Mpesa();
    $tab = $_GET['tab'] ?? 'stkpush';
    $paymentAction = $_GET['action'] ?? '';
    
    $csrfValid = \App\Auth::validateToken($_POST['csrf_token'] ?? '');
    if (!$csrfValid) {
        $_SESSION['error_message'] = 'Invalid request. Please try again.';
    } else {
        try {
            if ($tab === 'stkpush' && $paymentAction === 'send') {
                $phone = $_POST['phone'] ?? '';
                $amount = (float)($_POST['amount'] ?? 0);
                $accountRef = $_POST['account_ref'] ?? '';
                $description = $_POST['description'] ?? 'Payment';
                $customerId = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
                
                if (empty($phone) || $amount <= 0 || empty($accountRef)) {
                    $_SESSION['error_message'] = 'Phone number, amount, and account reference are required.';
                } else {
                    $result = $mpesa->stkPush($phone, $amount, $accountRef, $description, $customerId);
                    if ($result['success']) {
                        $_SESSION['success_message'] = $result['message'];
                    } else {
                        $_SESSION['error_message'] = $result['message'];
                    }
                }
                \App\Auth::regenerateToken();
            } elseif ($tab === 'c2b' && $paymentAction === 'register_urls') {
                $result = $mpesa->registerC2BUrls();
                if ($result['success']) {
                    $_SESSION['success_message'] = 'C2B URLs registered successfully!';
                } else {
                    $_SESSION['error_message'] = 'Failed to register C2B URLs: ' . $result['message'];
                }
                \App\Auth::regenerateToken();
            }
        } catch (Exception $e) {
            $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
        }
    }
    header('Location: ?page=payments&tab=' . $tab);
    exit;
}

// Handle M-Pesa GET actions (query status)
if ($page === 'payments_legacy' && isset($_GET['action'])) {
    $mpesa = new \App\Mpesa();
    $paymentAction = $_GET['action'];
    $tab = $_GET['tab'] ?? 'stkpush';
    
    if ($paymentAction === 'query' && isset($_GET['id'])) {
        $result = $mpesa->stkQuery($_GET['id']);
        if (isset($result['ResultCode']) && $result['ResultCode'] === '0') {
            $_SESSION['success_message'] = 'Transaction completed successfully!';
        } elseif (isset($result['ResultCode'])) {
            $_SESSION['error_message'] = 'Transaction status: ' . ($result['ResultDesc'] ?? 'Unknown');
        } else {
            $_SESSION['error_message'] = 'Could not query transaction status.';
        }
        header('Location: ?page=payments&tab=' . $tab);
        exit;
    } elseif ($paymentAction === 'export') {
        $transactions = $mpesa->getTransactions([
            'date_from' => $_GET['date_from'] ?? date('Y-m-01'),
            'date_to' => $_GET['date_to'] ?? date('Y-m-d'),
            'limit' => 10000
        ]);
        
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('M-Pesa Transactions');
        
        $headers = ['Date', 'Phone', 'Amount', 'Receipt', 'Account Ref', 'Customer', 'Status', 'Description'];
        foreach ($headers as $col => $header) {
            $sheet->setCellValue([$col + 1, 1], $header);
        }
        $sheet->getStyle('A1:H1')->getFont()->setBold(true);
        
        $row = 2;
        foreach ($transactions as $tx) {
            $sheet->setCellValue([1, $row], $tx['created_at']);
            $sheet->setCellValue([2, $row], $tx['phone_number'] ?? '');
            $sheet->setCellValue([3, $row], $tx['amount'] ?? 0);
            $sheet->setCellValue([4, $row], $tx['mpesa_receipt_number'] ?? '');
            $sheet->setCellValue([5, $row], $tx['account_reference'] ?? '');
            $sheet->setCellValue([6, $row], $tx['customer_name'] ?? '');
            $sheet->setCellValue([7, $row], $tx['status'] ?? '');
            $sheet->setCellValue([8, $row], $tx['result_desc'] ?? '');
            $row++;
        }
        
        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        $filename = 'mpesa_transactions_' . date('Y-m-d_His') . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
}

if ($page === 'complaints' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $complaintAction = $_GET['action'] ?? '';
    $complaintId = isset($_GET['id']) ? (int)$_GET['id'] : null;
    
    $csrfValid = \App\Auth::validateToken($_POST['csrf_token'] ?? '');
    if (!$csrfValid) {
        $_SESSION['error_message'] = 'Invalid request. Please try again.';
    } elseif ($complaintId) {
        try {
            $complaintModel = new \App\Complaint();
            
            switch ($complaintAction) {
                case 'approve':
                    $notes = trim($_POST['review_notes'] ?? '');
                    $complaintModel->approve($complaintId, $currentUser['id'], $notes);
                    $_SESSION['success_message'] = 'Complaint approved successfully! You can now convert it to a ticket.';
                    break;
                    
                case 'reject':
                    $notes = trim($_POST['review_notes'] ?? '');
                    if (empty($notes)) {
                        $_SESSION['error_message'] = 'Please provide a reason for rejection.';
                        header('Location: ?page=complaints&action=view&id=' . $complaintId);
                        exit;
                    } else {
                        $complaintModel->reject($complaintId, $currentUser['id'], $notes);
                        $_SESSION['success_message'] = 'Complaint rejected.';
                    }
                    break;
                    
                case 'convert':
                    $assignTo = !empty($_POST['assign_to']) ? (int)$_POST['assign_to'] : null;
                    $teamId = !empty($_POST['team_id']) ? (int)$_POST['team_id'] : null;
                    $ticketId = $complaintModel->convertToTicket($complaintId, $currentUser['id'], $assignTo, $teamId);
                    if ($ticketId) {
                        $_SESSION['success_message'] = 'Complaint converted to ticket! Please assign a technician.';
                        header('Location: ?page=tickets&action=edit&id=' . $ticketId);
                        exit;
                    } else {
                        $_SESSION['error_message'] = 'Failed to convert complaint to ticket. Make sure it is approved first.';
                    }
                    break;
                    
                case 'update_priority':
                    $priority = $_POST['priority'] ?? 'medium';
                    $complaintModel->updatePriority($complaintId, $priority);
                    $_SESSION['success_message'] = 'Priority updated.';
                    break;
                    
                case 'delete':
                    $complaintModel->delete($complaintId);
                    $_SESSION['success_message'] = 'Complaint deleted.';
                    break;
            }
            \App\Auth::regenerateToken();
        } catch (Exception $e) {
            $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
        }
    }
    header('Location: ?page=complaints');
    exit;
}

if ($page === 'orders' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderAction = $_GET['action'] ?? $_POST['action'] ?? '';
    $orderId = isset($_GET['id']) ? (int)$_GET['id'] : null;
    
    $csrfValid = \App\Auth::validateToken($_POST['csrf_token'] ?? '');
    if (!$csrfValid) {
        $_SESSION['error_message'] = 'Invalid request. Please try again.';
    } else {
        try {
            $orderModel = new \App\Order();
            
            if ($orderAction === 'create_order') {
                $customerName = trim($_POST['customer_name'] ?? '');
                $customerPhone = trim($_POST['customer_phone'] ?? '');
                $customerEmail = trim($_POST['customer_email'] ?? '');
                $customerAddress = trim($_POST['customer_address'] ?? '');
                $packageId = !empty($_POST['package_id']) ? (int)$_POST['package_id'] : null;
                $salespersonId = !empty($_POST['salesperson_id']) ? (int)$_POST['salesperson_id'] : null;
                $customerId = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
                $notes = trim($_POST['notes'] ?? '');
                
                if (empty($customerName) || empty($customerPhone)) {
                    $_SESSION['error_message'] = 'Customer name and phone are required.';
                } else {
                    $orderData = [
                        'customer_name' => $customerName,
                        'customer_phone' => $customerPhone,
                        'customer_email' => $customerEmail,
                        'customer_address' => $customerAddress,
                        'package_id' => $packageId,
                        'salesperson_id' => $salespersonId,
                        'customer_id' => $customerId,
                        'notes' => $notes,
                        'source' => 'crm',
                        'created_by' => $currentUser['id']
                    ];
                    
                    $newOrderId = $orderModel->create($orderData);
                    if ($newOrderId) {
                        $_SESSION['success_message'] = 'Order created successfully!';
                        header('Location: ?page=orders&action=view&id=' . $newOrderId);
                        exit;
                    } else {
                        $_SESSION['error_message'] = 'Failed to create order.';
                    }
                }
            } elseif ($orderId) {
                switch ($orderAction) {
                    case 'confirm':
                        $orderModel->updateStatus($orderId, 'confirmed');
                        $_SESSION['success_message'] = 'Order confirmed successfully!';
                        break;
                        
                    case 'convert':
                        $ticketId = $orderModel->convertToTicket($orderId, $currentUser['id']);
                        if ($ticketId) {
                            $_SESSION['success_message'] = 'Order converted to ticket! Please assign a technician.';
                            header('Location: ?page=tickets&action=edit&id=' . $ticketId);
                            exit;
                        } else {
                            $_SESSION['error_message'] = 'Failed to convert order to ticket.';
                        }
                        break;
                        
                    case 'cancel':
                        $orderModel->updateStatus($orderId, 'cancelled');
                        $_SESSION['success_message'] = 'Order cancelled.';
                        break;
                }
            }
            \App\Auth::regenerateToken();
        } catch (Exception $e) {
            $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
        }
    }
    header('Location: ?page=orders');
    exit;
}

$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$priorityFilter = $_GET['priority'] ?? '';

$stats = $ticket->getStats();
$users = $ticket->getAllUsers();
$servicePlans = $customer->getServicePlans();
$connectionStatuses = $customer->getConnectionStatuses();
$categories = $ticket->getCategories();
$priorities = $ticket->getPriorities();
$ticketStatuses = $ticket->getStatuses();
$csrfToken = \App\Auth::generateToken();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ISP CRM & Ticketing System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #0d6efd;
            --sidebar-width: 250px;
        }
        body {
            background-color: #f8f9fa;
        }
        /* Desktop sidebar - hidden on mobile */
        .sidebar-desktop {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: linear-gradient(135deg, #1a1c2c 0%, #2d3250 100%);
            padding-top: 1rem;
            z-index: 1000;
            overflow-y: auto;
        }
        .sidebar-desktop .nav-link,
        .offcanvas .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 0.75rem 1.5rem;
            border-radius: 0;
            transition: all 0.3s;
        }
        .sidebar-desktop .nav-link:hover, .sidebar-desktop .nav-link.active,
        .offcanvas .nav-link:hover, .offcanvas .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: #fff;
            border-left: 3px solid var(--primary-color);
        }
        .sidebar-desktop .nav-link i,
        .offcanvas .nav-link i {
            margin-right: 0.75rem;
            width: 20px;
        }
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
        }
        .brand {
            color: #fff;
            font-size: 1.5rem;
            font-weight: bold;
            padding: 0 1.5rem 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 1rem;
        }
        .stat-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-card-clickable {
            cursor: pointer;
        }
        .stat-card-clickable:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        }
        .stat-card .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        .badge-priority-critical { background-color: #dc3545; }
        .badge-priority-high { background-color: #fd7e14; }
        .badge-priority-medium { background-color: #ffc107; color: #000; }
        .badge-priority-low { background-color: #198754; }
        .badge-status-open { background-color: #0d6efd; }
        .badge-status-in_progress { background-color: #6f42c1; }
        .badge-status-pending { background-color: #ffc107; color: #000; }
        .badge-status-resolved { background-color: #198754; }
        .badge-status-closed { background-color: #6c757d; }
        .table-hover tbody tr:hover {
            background-color: rgba(13, 110, 253, 0.05);
        }
        .sms-status {
            font-size: 0.75rem;
        }
        .sms-enabled { color: #198754; }
        .sms-disabled { color: #dc3545; }
        .user-info {
            color: rgba(255,255,255,0.8);
            padding: 0.5rem 1.5rem;
            font-size: 0.875rem;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        /* Mobile header with hamburger */
        .mobile-header {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 56px;
            background: linear-gradient(135deg, #1a1c2c 0%, #2d3250 100%);
            z-index: 1050;
            padding: 0 1rem;
            align-items: center;
            justify-content: space-between;
        }
        .mobile-header .brand-mobile {
            color: #fff;
            font-size: 1.25rem;
            font-weight: bold;
        }
        .mobile-header .hamburger-btn {
            background: rgba(255,255,255,0.1);
            border: none;
            color: #fff;
            font-size: 1.5rem;
            padding: 0.5rem 0.75rem;
            border-radius: 8px;
        }
        /* Mobile offcanvas sidebar styling */
        .offcanvas-mobile {
            background: linear-gradient(135deg, #1a1c2c 0%, #2d3250 100%) !important;
            width: 280px !important;
        }
        .offcanvas-mobile .offcanvas-header {
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .offcanvas-mobile .btn-close {
            filter: invert(1);
        }
        /* Responsive adjustments */
        @media (max-width: 991.98px) {
            .sidebar-desktop {
                display: none !important;
            }
            .mobile-header {
                display: flex !important;
            }
            .main-content {
                margin-left: 0;
                padding: 1rem;
                padding-top: 70px;
            }
            /* Smaller cards on mobile */
            .stat-card .stat-icon {
                width: 45px;
                height: 45px;
                font-size: 1.2rem;
            }
            /* Full-width buttons on mobile */
            .btn-mobile-full {
                width: 100%;
                margin-bottom: 0.5rem;
            }
            /* Better table scrolling */
            .table-responsive {
                margin: 0 -1rem;
                padding: 0 1rem;
            }
        }
        @media (min-width: 992px) {
            .mobile-header {
                display: none !important;
            }
            .sidebar-desktop {
                display: flex !important;
            }
        }
        /* Touch-friendly form controls */
        @media (max-width: 767.98px) {
            .form-control, .form-select, .btn {
                min-height: 44px;
            }
            .modal-dialog {
                margin: 0.5rem;
            }
            .modal-body {
                padding: 1rem;
            }
            /* Stack form rows on mobile */
            .row-mobile-stack > * {
                margin-bottom: 0.75rem;
            }
        }
        
        /* Enhanced Mobile Responsiveness */
        @media (max-width: 991.98px) {
            /* Make all tables horizontally scrollable */
            .table-responsive-mobile {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            .table-responsive-mobile table {
                min-width: 600px;
            }
            /* Smaller text in tables on mobile */
            table.table-sm-mobile td, table.table-sm-mobile th {
                font-size: 0.8rem;
                padding: 0.4rem;
            }
            /* Cards should be full width */
            .card {
                margin-bottom: 1rem;
            }
            /* Fix nav tabs on mobile */
            .nav-tabs {
                flex-wrap: nowrap;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            .nav-tabs .nav-link {
                white-space: nowrap;
                font-size: 0.85rem;
                padding: 0.5rem 0.75rem;
            }
            /* Smaller headers on mobile */
            h1, .h1 { font-size: 1.5rem; }
            h2, .h2 { font-size: 1.3rem; }
            h3, .h3 { font-size: 1.15rem; }
            h4, .h4 { font-size: 1rem; }
            /* Better button groups on mobile */
            .btn-group-mobile {
                display: flex;
                flex-wrap: wrap;
                gap: 0.25rem;
            }
            .btn-group-mobile .btn {
                flex: 1 1 auto;
                min-width: 45%;
            }
            /* ONU detail cards - stack on mobile */
            .onu-detail-grid {
                display: block !important;
            }
            .onu-detail-grid > div {
                margin-bottom: 0.75rem;
            }
            /* Hide less important columns on mobile */
            .hide-mobile {
                display: none !important;
            }
            /* Full-width dropdown menus */
            .dropdown-menu {
                max-width: 100vw;
            }
            /* Compact badges */
            .badge {
                font-size: 0.7rem;
            }
        }
        
        @media (max-width: 575.98px) {
            /* Extra small screens */
            .main-content {
                padding: 0.75rem;
                padding-top: 65px;
            }
            /* Stack all action buttons */
            .action-buttons-mobile {
                display: flex;
                flex-direction: column;
                gap: 0.5rem;
            }
            .action-buttons-mobile .btn {
                width: 100%;
            }
            /* Smaller stat cards */
            .stat-card .stat-icon {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }
            .stat-card h3 {
                font-size: 1.25rem;
            }
            /* More compact tables */
            table.table-compact-mobile td, table.table-compact-mobile th {
                font-size: 0.75rem;
                padding: 0.3rem;
            }
            /* Modals full width */
            .modal-dialog {
                margin: 0;
                max-width: 100%;
            }
            .modal-content {
                border-radius: 0;
                min-height: 100vh;
            }
            .modal-lg, .modal-xl {
                max-width: 100%;
            }
        }
        
        /* Utility classes for mobile */
        .text-truncate-mobile {
            max-width: 120px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        @media (min-width: 768px) {
            .text-truncate-mobile {
                max-width: none;
            }
        }
    </style>
    
    <!-- Dark Mode Support -->
    <style id="darkModeStyles">
        body.dark-mode {
            --bs-body-bg: #1a1a2e;
            --bs-body-color: #e2e8f0;
            background-color: #1a1a2e !important;
            color: #e2e8f0 !important;
        }
        body.dark-mode .card { background: #16213e !important; border-color: #1f4068 !important; }
        body.dark-mode .card-header { background: #1f4068 !important; border-color: #1f4068 !important; }
        body.dark-mode .table { --bs-table-bg: #16213e; --bs-table-color: #e2e8f0; --bs-table-border-color: #1f4068; }
        body.dark-mode .modal-content { background: #16213e !important; border-color: #1f4068 !important; }
        body.dark-mode .form-control, body.dark-mode .form-select { background: #1a1a2e !important; border-color: #1f4068 !important; color: #e2e8f0 !important; }
        body.dark-mode .list-group-item { background: #16213e !important; border-color: #1f4068 !important; color: #e2e8f0 !important; }
        body.dark-mode .bg-light, body.dark-mode .bg-white { background: #16213e !important; }
        body.dark-mode .text-muted { color: #94a3b8 !important; }
        body.dark-mode .sidebar, body.dark-mode .isp-sidebar, body.dark-mode .oms-sidebar { background: #0f0f23 !important; }
        body.dark-mode .dropdown-menu { background: #16213e !important; border-color: #1f4068 !important; }
        body.dark-mode .dropdown-item { color: #e2e8f0 !important; }
        body.dark-mode .dropdown-item:hover { background: #1f4068 !important; }
        body.dark-mode .nav-link { color: #94a3b8 !important; }
        body.dark-mode .nav-link.active { color: #e2e8f0 !important; background: #1f4068 !important; }
        body.dark-mode code { background: #1f4068; color: #fbbf24; }
        body.dark-mode .alert-info { background: #1f4068 !important; border-color: #1f4068 !important; }
        
        /* Dark Mode Toggle Button */
        .dark-mode-toggle {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 9999;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
            transition: all 0.3s ease;
        }
        .dark-mode-toggle:hover { transform: scale(1.1); }
        body:not(.dark-mode) .dark-mode-toggle { background: #1a1a2e; color: #fff; }
        body.dark-mode .dark-mode-toggle { background: #f1f5f9; color: #1a1a2e; }
        
        /* Keyboard Shortcuts Help */
        .keyboard-shortcuts-help {
            position: fixed;
            bottom: 70px;
            right: 20px;
            z-index: 9998;
            display: none;
        }
        .keyboard-shortcuts-help.show { display: block; }
        .shortcut-key { 
            display: inline-block;
            background: #e2e8f0;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 2px 6px;
            font-family: monospace;
            font-size: 0.8rem;
        }
        body.dark-mode .shortcut-key { background: #1f4068; border-color: #1f4068; }
        
        /* Activity Feed */
        .activity-feed-btn {
            position: fixed;
            bottom: 20px;
            right: 75px;
            z-index: 9998;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            border: none;
            background: #6366f1;
            color: #fff;
            cursor: pointer;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }
    </style>
</head>
<body>
    <?php
    $sidebarSettings = new \App\Settings($db);
    $sidebarCompanyInfo = $sidebarSettings->getCompanyInfo();
    $sidebarLogo = $sidebarCompanyInfo['company_logo'] ?? '';
    $sidebarCompanyName = $sidebarCompanyInfo['company_name'] ?? 'ISP CRM';
    
    // Module visibility settings
    $moduleOmsEnabled = $sidebarSettings->get('module_oms_enabled', '1') === '1';
    $moduleIspEnabled = $sidebarSettings->get('module_isp_enabled', '1') === '1';
    $moduleAccountingEnabled = $sidebarSettings->get('module_accounting_enabled', '1') === '1';
    $moduleInventoryEnabled = $sidebarSettings->get('module_inventory_enabled', '1') === '1';
    ?>
    
    <!-- Mobile Header with Hamburger -->
    <div class="mobile-header">
        <div class="brand-mobile">
            <?php if (!empty($sidebarLogo)): ?>
                <img src="<?= htmlspecialchars($sidebarLogo) ?>" alt="<?= htmlspecialchars($sidebarCompanyName) ?>" style="max-height: 32px;">
            <?php else: ?>
                <i class="bi bi-router"></i> <?= htmlspecialchars($sidebarCompanyName) ?>
            <?php endif; ?>
        </div>
        <button class="hamburger-btn" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar" aria-controls="mobileSidebar">
            <i class="bi bi-list"></i>
        </button>
    </div>
    
    <!-- Mobile Offcanvas Sidebar -->
    <div class="offcanvas offcanvas-start offcanvas-mobile" tabindex="-1" id="mobileSidebar" aria-labelledby="mobileSidebarLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title text-white" id="mobileSidebarLabel">
                <?php if (!empty($sidebarLogo)): ?>
                    <img src="<?= htmlspecialchars($sidebarLogo) ?>" alt="<?= htmlspecialchars($sidebarCompanyName) ?>" style="max-height: 36px;">
                <?php else: ?>
                    <i class="bi bi-router"></i> <?= htmlspecialchars($sidebarCompanyName) ?>
                <?php endif; ?>
            </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body p-0">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link <?= $page === 'dashboard' ? 'active' : '' ?>" href="?page=dashboard">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="?page=ticket-wallboard" target="_blank">
                        <i class="bi bi-tv"></i> Wallboard
                    </a>
                </li>
                <?php if (\App\Auth::can('customers.view')): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $page === 'customers' ? 'active' : '' ?>" href="?page=customers">
                        <i class="bi bi-people"></i> Customers
                    </a>
                </li>
                <?php endif; ?>
                <?php if (\App\Auth::can('tickets.view')): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $page === 'tickets' ? 'active' : '' ?>" href="?page=tickets">
                        <i class="bi bi-ticket"></i> Tickets
                    </a>
                </li>
                <?php endif; ?>
                <?php if (\App\Auth::can('hr.view')): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $page === 'hr' ? 'active' : '' ?>" href="?page=hr">
                        <i class="bi bi-people-fill"></i> HR
                    </a>
                </li>
                <?php endif; ?>
                <?php if (\App\Auth::can('inventory.view') && $moduleInventoryEnabled): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $page === 'inventory' ? 'active' : '' ?>" href="?page=inventory">
                        <i class="bi bi-box-seam"></i> Inventory
                    </a>
                </li>
                <?php endif; ?>
                <?php if (\App\Auth::can('orders.view')): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $page === 'orders' ? 'active' : '' ?>" href="?page=orders">
                        <i class="bi bi-cart3"></i> Orders
                    </a>
                </li>
                <?php endif; ?>
                <?php if (\App\Auth::can('tickets.view')): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $page === 'complaints' ? 'active' : '' ?>" href="?page=complaints">
                        <i class="bi bi-exclamation-triangle"></i> Complaints
                    </a>
                </li>
                <?php endif; ?>
                <?php if (\App\Auth::can('settings.view') && $moduleOmsEnabled): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $page === 'huawei-olt' ? 'active' : '' ?>" href="?page=huawei-olt">
                        <i class="bi bi-router text-primary"></i> OMS
                    </a>
                </li>
                <?php endif; ?>
                <?php if (\App\Auth::can('settings.view') && $moduleIspEnabled): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $page === 'isp' ? 'active' : '' ?>" href="?page=isp">
                        <i class="bi bi-broadcast text-info"></i> ISP
                    </a>
                </li>
                <?php endif; ?>
                <?php if (\App\Auth::can('settings.view')): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $page === 'call_center' ? 'active' : '' ?>" href="?page=call_center">
                        <i class="bi bi-telephone-fill text-success"></i> Call Center
                    </a>
                </li>
                <?php endif; ?>
                <?php if (\App\Auth::can('reports.view')): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $page === 'reports' ? 'active' : '' ?>" href="?page=reports">
                        <i class="bi bi-graph-up"></i> Reports
                    </a>
                </li>
                <?php endif; ?>
                <?php if (\App\Auth::can('settings.view') && $moduleAccountingEnabled): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $page === 'accounting' ? 'active' : '' ?>" href="?page=accounting">
                        <i class="bi bi-calculator"></i> Accounting
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="?page=finance">
                        <i class="bi bi-bank text-success"></i> Finance
                    </a>
                </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a class="nav-link <?= $page === 'whatsapp-chat' ? 'active' : '' ?>" href="?page=whatsapp-chat">
                        <i class="bi bi-chat-dots"></i> Quick Chat
                    </a>
                </li>
                <?php if ($currentUser['role'] !== 'admin'): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $page === 'my-hr' ? 'active' : '' ?>" href="?page=my-hr">
                        <i class="bi bi-person-badge"></i> My HR
                    </a>
                </li>
                <?php endif; ?>
                <?php if (\App\Auth::can('settings.view')): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $page === 'settings' ? 'active' : '' ?>" href="?page=settings">
                        <i class="bi bi-gear"></i> Settings
                    </a>
                </li>
                <?php endif; ?>
                <?php if (\App\Auth::isAdmin()): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $page === 'branches' ? 'active' : '' ?>" href="?page=branches">
                        <i class="bi bi-building"></i> Branches
                    </a>
                </li>
                <?php endif; ?>
            </ul>
            <div class="user-info mt-auto">
                <i class="bi bi-person-circle"></i> <?= htmlspecialchars($currentUser['name']) ?>
                <br>
                <small class="text-muted"><?= ucfirst($currentUser['role']) ?></small>
                <br>
                <a href="?page=logout" class="text-danger small"><i class="bi bi-box-arrow-right"></i> Logout</a>
            </div>
        </div>
    </div>
    
    <!-- Desktop Sidebar -->
    <nav class="sidebar-desktop d-none d-lg-flex flex-column">
        <div class="brand">
            <?php if (!empty($sidebarLogo)): ?>
                <img src="<?= htmlspecialchars($sidebarLogo) ?>" alt="<?= htmlspecialchars($sidebarCompanyName) ?>" style="max-height: 40px; max-width: 180px;">
            <?php else: ?>
                <i class="bi bi-router"></i> <?= htmlspecialchars($sidebarCompanyName) ?>
            <?php endif; ?>
        </div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?= $page === 'dashboard' ? 'active' : '' ?>" href="?page=dashboard">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="?page=ticket-wallboard" target="_blank">
                    <i class="bi bi-tv"></i> Wallboard
                </a>
            </li>
            <?php if (\App\Auth::can('customers.view')): ?>
            <li class="nav-item">
                <a class="nav-link <?= $page === 'customers' ? 'active' : '' ?>" href="?page=customers">
                    <i class="bi bi-people"></i> Customers
                </a>
            </li>
            <?php endif; ?>
            <?php if (\App\Auth::can('tickets.view')): ?>
            <li class="nav-item">
                <a class="nav-link <?= $page === 'tickets' ? 'active' : '' ?>" href="?page=tickets">
                    <i class="bi bi-ticket"></i> Tickets
                </a>
            </li>
            <?php endif; ?>
            <?php if (\App\Auth::can('hr.view')): ?>
            <li class="nav-item">
                <?php 
                $pendingHrRequests = 0;
                if (\App\Auth::isAdmin() || \App\Auth::can('hr.approve_leave') || \App\Auth::can('hr.approve_advance')) {
                    $pendingLeave = $db->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'pending'")->fetchColumn();
                    $pendingAdvance = $db->query("SELECT COUNT(*) FROM salary_advances WHERE status = 'pending'")->fetchColumn();
                    $pendingHrRequests = (int)$pendingLeave + (int)$pendingAdvance;
                }
                ?>
                <a class="nav-link <?= $page === 'hr' ? 'active' : '' ?>" href="?page=hr">
                    <i class="bi bi-people-fill"></i> HR
                    <?php if ($pendingHrRequests > 0): ?>
                    <span class="badge bg-danger rounded-pill ms-1"><?= $pendingHrRequests ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <?php endif; ?>
            <?php if (\App\Auth::can('inventory.view') && $moduleInventoryEnabled): ?>
            <li class="nav-item">
                <a class="nav-link <?= $page === 'inventory' ? 'active' : '' ?>" href="?page=inventory">
                    <i class="bi bi-box-seam"></i> Inventory
                </a>
            </li>
            <?php endif; ?>
            <?php if (\App\Auth::can('orders.view')): ?>
            <li class="nav-item">
                <?php 
                $newOrdersCount = $db->query("SELECT COUNT(*) FROM orders WHERE order_status = 'new'")->fetchColumn();
                ?>
                <a class="nav-link <?= $page === 'orders' ? 'active' : '' ?>" href="?page=orders">
                    <i class="bi bi-cart3"></i> Orders
                    <?php if ($newOrdersCount > 0): ?>
                    <span class="badge bg-danger rounded-pill ms-1"><?= $newOrdersCount ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <?php endif; ?>
            <?php if (\App\Auth::can('tickets.view')): ?>
            <li class="nav-item">
                <?php 
                $pendingComplaintsCount = $db->query("SELECT COUNT(*) FROM complaints WHERE status = 'pending'")->fetchColumn();
                ?>
                <a class="nav-link <?= $page === 'complaints' ? 'active' : '' ?>" href="?page=complaints">
                    <i class="bi bi-exclamation-triangle"></i> Complaints
                    <?php if ($pendingComplaintsCount > 0): ?>
                    <span class="badge bg-warning text-dark rounded-pill ms-1"><?= $pendingComplaintsCount ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <?php endif; ?>
            <?php if (\App\Auth::can('settings.view') && $moduleOmsEnabled): ?>
            <li class="nav-item">
                <a class="nav-link <?= $page === 'huawei-olt' ? 'active' : '' ?>" href="?page=huawei-olt">
                    <i class="bi bi-router text-primary"></i> OMS
                </a>
            </li>
            <?php endif; ?>
            <?php if (\App\Auth::can('settings.view') && $moduleIspEnabled): ?>
            <li class="nav-item">
                <a class="nav-link <?= $page === 'isp' ? 'active' : '' ?>" href="?page=isp">
                    <i class="bi bi-broadcast text-info"></i> ISP
                </a>
            </li>
            <?php endif; ?>
            <?php if (\App\Auth::can('settings.view')): ?>
            <li class="nav-item">
                <a class="nav-link <?= $page === 'call_center' ? 'active' : '' ?>" href="?page=call_center">
                    <i class="bi bi-telephone-fill text-success"></i> Call Center
                </a>
            </li>
            <?php endif; ?>
            <?php if (\App\Auth::can('reports.view')): ?>
            <li class="nav-item">
                <a class="nav-link <?= $page === 'reports' ? 'active' : '' ?>" href="?page=reports">
                    <i class="bi bi-graph-up"></i> Reports
                </a>
            </li>
            <?php endif; ?>
            <?php if (\App\Auth::can('settings.view') && $moduleAccountingEnabled): ?>
            <li class="nav-item">
                <a class="nav-link <?= $page === 'accounting' ? 'active' : '' ?>" href="?page=accounting">
                    <i class="bi bi-calculator"></i> Accounting
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="?page=finance">
                    <i class="bi bi-bank text-success"></i> Finance
                </a>
            </li>
            <?php endif; ?>
            <li class="nav-item">
                <a class="nav-link <?= $page === 'whatsapp-chat' ? 'active' : '' ?>" href="?page=whatsapp-chat">
                    <i class="bi bi-chat-dots"></i> Quick Chat
                </a>
            </li>
            <?php if ($currentUser['role'] !== 'admin'): ?>
            <li class="nav-item">
                <a class="nav-link <?= $page === 'my-hr' ? 'active' : '' ?>" href="?page=my-hr">
                    <i class="bi bi-person-badge"></i> My HR
                </a>
            </li>
            <?php endif; ?>
            <?php if (\App\Auth::can('settings.view')): ?>
            <li class="nav-item">
                <a class="nav-link <?= $page === 'settings' ? 'active' : '' ?>" href="?page=settings">
                    <i class="bi bi-gear"></i> Settings
                </a>
            </li>
            <?php endif; ?>
            <?php if (\App\Auth::isAdmin()): ?>
            <li class="nav-item">
                <a class="nav-link <?= $page === 'branches' ? 'active' : '' ?>" href="?page=branches">
                    <i class="bi bi-building"></i> Branches
                </a>
            </li>
            <?php endif; ?>
        </ul>
        <div class="mt-auto">
            <div class="user-info">
                <i class="bi bi-person-circle"></i> <?= htmlspecialchars($currentUser['name']) ?>
                <br>
                <small class="text-muted"><?= ucfirst($currentUser['role']) ?></small>
                <br>
                <a href="?page=logout" class="text-danger small"><i class="bi bi-box-arrow-right"></i> Logout</a>
            </div>
        </div>
    </nav>

    <main class="main-content">
        <?php
        // Quick Dashboard Bar - Stats & Actions
        $quickStats = [];
        $alerts = [];
        
        try {
            // Today's open tickets
            $ticketStmt = $db->query("SELECT COUNT(*) FROM tickets WHERE status NOT IN ('closed', 'resolved')");
            $quickStats['open_tickets'] = $ticketStmt->fetchColumn() ?: 0;
            
            // Unassigned tickets (urgent)
            $unassignedStmt = $db->query("SELECT COUNT(*) FROM tickets WHERE assigned_to IS NULL AND status NOT IN ('closed', 'resolved')");
            $unassignedCount = $unassignedStmt->fetchColumn() ?: 0;
            if ($unassignedCount > 0) {
                $alerts[] = ['type' => 'warning', 'icon' => 'exclamation-triangle', 'text' => $unassignedCount . ' unassigned ticket(s)', 'link' => '?page=tickets&filter=unassigned'];
            }
            
            // Active RADIUS subscribers
            $subStmt = $db->query("SELECT COUNT(*) FROM radius_subscriptions WHERE status = 'active'");
            $quickStats['active_subs'] = $subStmt->fetchColumn() ?: 0;
            
            // Expiring subscriptions (next 3 days)
            $expiringStmt = $db->query("SELECT COUNT(*) FROM radius_subscriptions WHERE status = 'active' AND expiry_date BETWEEN CURRENT_DATE AND CURRENT_DATE + INTERVAL '3 days'");
            $expiringCount = $expiringStmt->fetchColumn() ?: 0;
            if ($expiringCount > 0) {
                $alerts[] = ['type' => 'info', 'icon' => 'clock', 'text' => $expiringCount . ' subscription(s) expiring soon', 'link' => '?page=isp&view=expiring'];
            }
            
            // Today's revenue
            $revenueStmt = $db->query("SELECT COALESCE(SUM(amount), 0) FROM mpesa_transactions WHERE status = 'completed' AND DATE(created_at) = CURRENT_DATE");
            $quickStats['today_revenue'] = $revenueStmt->fetchColumn() ?: 0;
            
            // Overdue tickets (high priority)
            $overdueStmt = $db->query("SELECT COUNT(*) FROM tickets WHERE status NOT IN ('closed', 'resolved') AND priority IN ('high', 'critical') AND created_at < NOW() - INTERVAL '24 hours'");
            $overdueCount = $overdueStmt->fetchColumn() ?: 0;
            if ($overdueCount > 0) {
                $alerts[] = ['type' => 'danger', 'icon' => 'alarm', 'text' => $overdueCount . ' overdue high-priority ticket(s)', 'link' => '?page=tickets&filter=overdue'];
            }
        } catch (Exception $e) {
            // Silently fail if tables don't exist
        }
        
        $userName = \App\Auth::user()['name'] ?? 'User';
        $greeting = (date('H') < 12) ? 'Good morning' : ((date('H') < 17) ? 'Good afternoon' : 'Good evening');
        ?>
        
        <div class="quick-dashboard-bar mb-3">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 py-2 px-3 bg-white border-bottom shadow-sm rounded">
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <span class="text-muted d-none d-md-inline"><i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($greeting . ', ' . explode(' ', $userName)[0]) ?></span>
                    
                    <div class="d-flex gap-2 flex-wrap">
                        <span class="badge bg-primary-subtle text-primary px-2 py-1" title="Open Tickets">
                            <i class="bi bi-ticket-detailed me-1"></i><?= $quickStats['open_tickets'] ?? 0 ?> Tickets
                        </span>
                        <span class="badge bg-success-subtle text-success px-2 py-1" title="Active Subscribers">
                            <i class="bi bi-wifi me-1"></i><?= $quickStats['active_subs'] ?? 0 ?> Active
                        </span>
                        <span class="badge bg-info-subtle text-info px-2 py-1 d-none d-sm-inline" title="Today's Revenue">
                            <i class="bi bi-cash me-1"></i>KES <?= number_format($quickStats['today_revenue'] ?? 0) ?>
                        </span>
                    </div>
                </div>
                
                <div class="d-flex align-items-center gap-2">
                    <?php if (!empty($alerts)): ?>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-warning position-relative" data-bs-toggle="dropdown" title="Alerts">
                                <i class="bi bi-bell"></i>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:0.6rem"><?= count($alerts) ?></span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow">
                                <li><h6 class="dropdown-header">Alerts</h6></li>
                                <?php foreach ($alerts as $alert): ?>
                                <li><a class="dropdown-item text-<?= $alert['type'] ?>" href="<?= $alert['link'] ?? '#' ?>"><i class="bi bi-<?= $alert['icon'] ?> me-2"></i><?= $alert['text'] ?></a></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <div class="btn-group">
                        <a href="?page=tickets&action=new" class="btn btn-sm btn-primary" title="New Ticket"><i class="bi bi-plus-lg"></i><span class="d-none d-md-inline ms-1">Ticket</span></a>
                        <a href="?page=customers&action=add" class="btn btn-sm btn-outline-primary" title="New Customer"><i class="bi bi-person-plus"></i></a>
                        <a href="?page=isp" class="btn btn-sm btn-outline-success" title="ISP Billing"><i class="bi bi-router"></i></a>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php
        $accessDenied = false;
        switch ($page) {
            case 'dashboard':
                include __DIR__ . '/../templates/dashboard.php';
                break;
            case 'customers':
                if (!\App\Auth::can('customers.view')) {
                    $accessDenied = true;
                } else {
                    include __DIR__ . '/../templates/customers.php';
                }
                break;
            case 'ticket-wallboard':
                include __DIR__ . '/../templates/ticket_wallboard.php';
                exit; // Render standalone without layout
                break;
            case 'tickets':
                if (!\App\Auth::can('tickets.view')) {
                    $accessDenied = true;
                } else {
                    include __DIR__ . '/../templates/tickets.php';
                }
                break;
            case 'hr':
                if (!\App\Auth::can('hr.view')) {
                    $accessDenied = true;
                } else {
                    include __DIR__ . '/../templates/hr.php';
                }
                break;
            case 'inventory':
                if (!\App\Auth::can('inventory.view')) {
                    $accessDenied = true;
                } else {
                    include __DIR__ . '/../templates/inventory.php';
                }
                break;
            case 'inventory_warehouses':
                if (!\App\Auth::can('inventory.view')) {
                    $accessDenied = true;
                } else {
                    include __DIR__ . '/../templates/inventory_warehouses.php';
                }
                break;
            case 'stock_requests':
                if (!\App\Auth::can('inventory.view')) {
                    $accessDenied = true;
                } else {
                    include __DIR__ . '/../templates/stock_requests.php';
                }
                break;
            case 'stock_returns':
                if (!\App\Auth::can('inventory.view')) {
                    $accessDenied = true;
                } else {
                    include __DIR__ . '/../templates/stock_returns.php';
                }
                break;
            case 'payments':
                if (!\App\Auth::can('payments.view')) {
                    $accessDenied = true;
                } else {
                    include __DIR__ . '/../templates/payments.php';
                }
                break;
            case 'orders':
                if (!\App\Auth::can('orders.view')) {
                    $accessDenied = true;
                } else {
                    include __DIR__ . '/../templates/orders.php';
                }
                break;
            case 'complaints':
                if (!\App\Auth::can('tickets.view')) {
                    $accessDenied = true;
                } else {
                    include __DIR__ . '/../templates/complaints.php';
                }
                break;
            case 'huawei-olt':
                if (!\App\Auth::can('settings.view')) {
                    $accessDenied = true;
                } else {
                    include __DIR__ . '/../templates/huawei_olt.php';
                    exit;
                }
                break;
            case 'isp':
                if (!\App\Auth::can('settings.view')) {
                    $accessDenied = true;
                } else {
                    include __DIR__ . '/../templates/isp.php';
                    exit;
                }
                break;
            case 'finance':
                if (!\App\Auth::can('settings.view')) {
                    $accessDenied = true;
                } else {
                    include __DIR__ . '/../templates/finance_dashboard.php';
                    exit;
                }
                break;
            case 'reports':
                if (!\App\Auth::can('reports.view')) {
                    $accessDenied = true;
                } else {
                    include __DIR__ . '/../templates/reports.php';
                }
                break;
            case 'settings':
                if (!\App\Auth::can('settings.view')) {
                    $accessDenied = true;
                } else {
                    $smsGateway = getSMSGateway();
                    include __DIR__ . '/../templates/settings.php';
                }
                break;
            case 'vpn':
                if (!\App\Auth::isAdmin()) {
                    $accessDenied = true;
                } else {
                    include __DIR__ . '/../templates/vpn.php';
                }
                break;
            case 'branches':
                if (!\App\Auth::can('settings.view')) {
                    $accessDenied = true;
                } else {
                    include __DIR__ . '/../templates/branches.php';
                }
                break;
            case 'accounting':
                if (!\App\Auth::can('settings.view')) {
                    $accessDenied = true;
                } else {
                    include __DIR__ . '/../templates/accounting.php';
                }
                break;
            case 'whatsapp-chat':
                include __DIR__ . '/../templates/whatsapp-chat.php';
                break;
            case 'my-hr':
                include __DIR__ . '/../templates/my-hr.php';
                break;
            case 'call_center':
                // Handle call center AJAX actions (check permission but return JSON for AJAX)
                if ($action === 'originate_call' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                    header('Content-Type: application/json');
                    if (!\App\Auth::check()) {
                        echo json_encode(['success' => false, 'error' => 'Not logged in']);
                        exit;
                    }
                    require_once __DIR__ . '/../src/CallCenter.php';
                    $callCenter = new CallCenter($db);
                    $phone = $_POST['phone'] ?? '';
                    $customerId = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
                    $ticketId = !empty($_POST['ticket_id']) ? (int)$_POST['ticket_id'] : null;
                    $userExt = $callCenter->getExtensionByUserId($_SESSION['user_id']);
                    if (!$userExt) {
                        echo json_encode(['success' => false, 'error' => 'No extension assigned to your account. Contact admin to assign an extension.']);
                        exit;
                    }
                    if (empty($phone)) {
                        echo json_encode(['success' => false, 'error' => 'No phone number provided']);
                        exit;
                    }
                    $result = $callCenter->originateCall($userExt['extension'], $phone, $customerId, $ticketId);
                    echo json_encode($result);
                    exit;
                }
                if (!\App\Auth::can('settings.view')) {
                    $accessDenied = true;
                } else {
                    // Handle call center AJAX actions
                    if ($action === 'originate' && isset($_GET['ajax'])) {
                        header('Content-Type: application/json');
                        require_once __DIR__ . '/../src/CallCenter.php';
                        $callCenter = new CallCenter($db);
                        $input = json_decode(file_get_contents('php://input'), true);
                        $userExt = $callCenter->getExtensionByUserId($_SESSION['user_id']);
                        if ($userExt) {
                            $result = $callCenter->originateCall($userExt['extension'], $input['destination']);
                            echo json_encode($result);
                        } else {
                            echo json_encode(['success' => false, 'error' => 'No extension assigned']);
                        }
                        exit;
                    }
                    if ($action === 'get_extension') {
                        header('Content-Type: application/json');
                        require_once __DIR__ . '/../src/CallCenter.php';
                        $callCenter = new CallCenter($db);
                        echo json_encode($callCenter->getExtension($_GET['id']));
                        exit;
                    }
                    if ($action === 'get_trunk') {
                        header('Content-Type: application/json');
                        require_once __DIR__ . '/../src/CallCenter.php';
                        $callCenter = new CallCenter($db);
                        echo json_encode($callCenter->getTrunk($_GET['id']));
                        exit;
                    }
                    if ($action === 'save_extension' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                        require_once __DIR__ . '/../src/CallCenter.php';
                        $callCenter = new CallCenter($db);
                        $callCenter->saveExtension($_POST);
                        header('Location: ?page=call_center&tab=extensions');
                        exit;
                    }
                    if ($action === 'save_trunk' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                        require_once __DIR__ . '/../src/CallCenter.php';
                        $callCenter = new CallCenter($db);
                        $callCenter->saveTrunk($_POST);
                        header('Location: ?page=call_center&tab=trunks');
                        exit;
                    }
                    if ($action === 'save_queue' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                        require_once __DIR__ . '/../src/CallCenter.php';
                        $callCenter = new CallCenter($db);
                        $callCenter->saveQueue($_POST);
                        header('Location: ?page=call_center&tab=queues');
                        exit;
                    }
                    if ($action === 'delete_extension') {
                        require_once __DIR__ . '/../src/CallCenter.php';
                        $callCenter = new CallCenter($db);
                        $callCenter->deleteExtension($_GET['id']);
                        header('Location: ?page=call_center&tab=extensions');
                        exit;
                    }
                    if ($action === 'delete_trunk') {
                        require_once __DIR__ . '/../src/CallCenter.php';
                        $callCenter = new CallCenter($db);
                        $callCenter->deleteTrunk($_GET['id']);
                        header('Location: ?page=call_center&tab=trunks');
                        exit;
                    }
                    include __DIR__ . '/../templates/call_center.php';
                }
                break;
            default:
                include __DIR__ . '/../templates/dashboard.php';
        }
        
        if ($accessDenied) {
            echo '<div class="alert alert-danger m-4"><i class="bi bi-shield-exclamation me-2"></i><strong>Access Denied.</strong> You do not have permission to view this page. Please contact your administrator if you need access.</div>';
        }
        ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelectorAll('.delete-confirm').forEach(btn => {
            btn.addEventListener('click', (e) => {
                if (!confirm('Are you sure you want to delete this item?')) {
                    e.preventDefault();
                }
            });
        });
        
    </script>
<script>
// Make all tables responsive on mobile
document.addEventListener('DOMContentLoaded', function() {
    if (window.innerWidth < 992) {
        document.querySelectorAll('table.table').forEach(function(table) {
            // Skip if already in a responsive wrapper
            if (table.parentElement.classList.contains('table-responsive')) return;
            if (table.closest('.table-responsive')) return;
            
            // Create responsive wrapper
            var wrapper = document.createElement('div');
            wrapper.className = 'table-responsive';
            table.parentNode.insertBefore(wrapper, table);
            wrapper.appendChild(table);
        });
    }
});
</script>

<!-- Dark Mode Toggle & Activity Feed Buttons -->
<button class="dark-mode-toggle" onclick="toggleDarkMode()" title="Toggle Dark Mode (Ctrl+D)">
    <i class="bi bi-moon-stars-fill"></i>
</button>

<button class="activity-feed-btn" onclick="toggleActivityFeed()" title="Recent Activity (Ctrl+A)">
    <i class="bi bi-activity"></i>
</button>

<!-- Keyboard Shortcuts Help Panel -->
<div class="keyboard-shortcuts-help card shadow" id="shortcutsPanel">
    <div class="card-body py-2 px-3">
        <h6 class="mb-2"><i class="bi bi-keyboard me-1"></i>Keyboard Shortcuts</h6>
        <div class="small">
            <div class="mb-1"><span class="shortcut-key">Ctrl</span>+<span class="shortcut-key">D</span> Dark Mode</div>
            <div class="mb-1"><span class="shortcut-key">Ctrl</span>+<span class="shortcut-key">/</span> Search</div>
            <div class="mb-1"><span class="shortcut-key">Ctrl</span>+<span class="shortcut-key">H</span> Dashboard</div>
            <div class="mb-1"><span class="shortcut-key">?</span> Show Shortcuts</div>
        </div>
    </div>
</div>

<!-- Activity Feed Offcanvas -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="activityFeedOffcanvas">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title"><i class="bi bi-activity me-2"></i>Recent Activity</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body" id="activityFeedContent">
        <div class="text-center text-muted py-4">
            <div class="spinner-border spinner-border-sm me-2"></div>Loading...
        </div>
    </div>
</div>

<script>
// Dark Mode Toggle
function toggleDarkMode() {
    document.body.classList.toggle('dark-mode');
    const isDark = document.body.classList.contains('dark-mode');
    localStorage.setItem('darkMode', isDark ? '1' : '0');
    
    // Update toggle button icon
    const btn = document.querySelector('.dark-mode-toggle i');
    if (btn) btn.className = isDark ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
}

// Initialize dark mode from localStorage
document.addEventListener('DOMContentLoaded', function() {
    if (localStorage.getItem('darkMode') === '1') {
        document.body.classList.add('dark-mode');
        const btn = document.querySelector('.dark-mode-toggle i');
        if (btn) btn.className = 'bi bi-sun-fill';
    }
});

// Keyboard Shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl+D: Toggle dark mode
    if (e.ctrlKey && e.key === 'd') {
        e.preventDefault();
        toggleDarkMode();
    }
    
    // Ctrl+/: Focus search
    if (e.ctrlKey && e.key === '/') {
        e.preventDefault();
        const search = document.querySelector('input[name="search"], #searchInput, .search-input');
        if (search) search.focus();
    }
    
    // Ctrl+H: Go to dashboard
    if (e.ctrlKey && e.key === 'h') {
        e.preventDefault();
        window.location.href = '?page=dashboard';
    }
    
    // ?: Show shortcuts help
    if (e.key === '?' && !e.ctrlKey && !e.altKey) {
        const panel = document.getElementById('shortcutsPanel');
        if (panel) {
            panel.classList.toggle('show');
            setTimeout(() => panel.classList.remove('show'), 5000);
        }
    }
    
    // Escape: Close shortcuts help
    if (e.key === 'Escape') {
        const panel = document.getElementById('shortcutsPanel');
        if (panel) panel.classList.remove('show');
    }
});

// Activity Feed Toggle
function toggleActivityFeed() {
    const offcanvas = new bootstrap.Offcanvas(document.getElementById('activityFeedOffcanvas'));
    offcanvas.toggle();
    loadActivityFeed();
}

function loadActivityFeed() {
    const content = document.getElementById('activityFeedContent');
    if (!content) return;
    
    // Fetch recent activity (from activity log)
    fetch('?page=api&action=recent_activity&limit=20')
        .then(r => r.json())
        .then(data => {
            if (data.activities && data.activities.length > 0) {
                let html = '<ul class="list-group list-group-flush">';
                data.activities.forEach(act => {
                    const time = new Date(act.created_at).toLocaleString();
                    const icon = getActivityIcon(act.activity_type);
                    // Escape HTML to prevent XSS
                    const escapeHtml = (str) => String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
                    const desc = escapeHtml(act.description || act.activity_type);
                    const user = escapeHtml(act.user_name || 'System');
                    html += '<li class="list-group-item px-0 py-2">';
                    html += '<div class="d-flex"><span class="me-2">' + icon + '</span>';
                    html += '<div class="flex-grow-1"><div class="small fw-bold">' + desc + '</div>';
                    html += '<div class="text-muted small">' + user + ' • ' + time + '</div></div></div>';
                    html += '</li>';
                });
                html += '</ul>';
                content.innerHTML = html;
            } else {
                content.innerHTML = '<div class="text-center text-muted py-4"><i class="bi bi-inbox fs-3 d-block mb-2"></i>No recent activity</div>';
            }
        })
        .catch(() => {
            content.innerHTML = '<div class="text-center text-muted py-4">Unable to load activity</div>';
        });
}

function getActivityIcon(type) {
    const icons = {
        'login': '<i class="bi bi-box-arrow-in-right text-success"></i>',
        'logout': '<i class="bi bi-box-arrow-left text-secondary"></i>',
        'ticket': '<i class="bi bi-ticket text-primary"></i>',
        'customer': '<i class="bi bi-person text-info"></i>',
        'payment': '<i class="bi bi-credit-card text-success"></i>',
        'onu': '<i class="bi bi-router text-warning"></i>',
        'subscriber': '<i class="bi bi-people text-info"></i>'
    };
    return icons[type] || '<i class="bi bi-circle text-muted"></i>';
}
</script>
</body>
</html>
