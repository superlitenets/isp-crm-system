<?php

date_default_timezone_set('Africa/Nairobi');

error_reporting(E_ALL);
ini_set('display_errors', 0);

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

initializeDatabase();

$db = Database::getConnection();

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
                $customerId = (int)($_POST['customer_id'] ?? 0);
                $subject = trim($_POST['subject'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $category = trim($_POST['category'] ?? '');
                
                if (empty($customerId) || empty($subject) || empty($description) || empty($category)) {
                    $message = 'Please fill in all required fields.';
                    $messageType = 'danger';
                } elseif (!$customer->find($customerId)) {
                    $message = 'Selected customer not found.';
                    $messageType = 'danger';
                } else {
                    try {
                        $ticketId = $ticket->create($_POST);
                        $message = 'Ticket created successfully! SMS notifications sent.';
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
                        $ticket->update((int)$_POST['id'], $_POST);
                        $message = 'Ticket updated successfully! SMS notification sent to customer.';
                        $messageType = 'success';
                        \App\Auth::regenerateToken();
                    } catch (Exception $e) {
                        $message = 'Error updating ticket: ' . $e->getMessage();
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
            
            case 'quick_status_change':
                $ticketId = (int)($_POST['ticket_id'] ?? 0);
                $newStatus = $_POST['new_status'] ?? '';
                if ($ticketId && $newStatus) {
                    try {
                        $result = $ticket->quickStatusChange($ticketId, $newStatus, $currentUser['id']);
                        if ($result) {
                            $message = 'Status changed to ' . ucwords(str_replace('_', ' ', $newStatus)) . ' successfully!';
                            $messageType = 'success';
                        } else {
                            $message = 'Failed to change status.';
                            $messageType = 'danger';
                        }
                        \App\Auth::regenerateToken();
                    } catch (Exception $e) {
                        $message = 'Error: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
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
                        $employee->create($_POST);
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
                        $employee->update((int)$_POST['id'], $_POST);
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
                    $salaryAdvance->create($_POST);
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
                    $message = 'Salary advance disbursed successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error disbursing advance: ' . $e->getMessage();
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
                    $leaveService->createRequest($_POST);
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
                            'is_active' => isset($_POST['is_active'])
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
                            'is_active' => isset($_POST['is_active'])
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
                        'is_active' => isset($_POST['is_active'])
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
                        'is_active' => isset($_POST['is_active'])
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
                            $inventory->addEquipment($data);
                            $_SESSION['success_message'] = 'Equipment added successfully!';
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
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: linear-gradient(135deg, #1a1c2c 0%, #2d3250 100%);
            padding-top: 1rem;
            z-index: 1000;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 0.75rem 1.5rem;
            border-radius: 0;
            transition: all 0.3s;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: #fff;
            border-left: 3px solid var(--primary-color);
        }
        .sidebar .nav-link i {
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
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <?php
    $sidebarSettings = new \App\Settings($db);
    $sidebarCompanyInfo = $sidebarSettings->getCompanyInfo();
    $sidebarLogo = $sidebarCompanyInfo['company_logo'] ?? '';
    $sidebarCompanyName = $sidebarCompanyInfo['company_name'] ?? 'ISP CRM';
    ?>
    <nav class="sidebar d-flex flex-column">
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
            <?php if (\App\Auth::can('inventory.view')): ?>
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
            <?php if (\App\Auth::can('settings.view')): ?>
            <li class="nav-item">
                <a class="nav-link <?= $page === 'devices' || $page === 'smartolt' ? 'active' : '' ?>" href="?page=devices">
                    <i class="bi bi-hdd-network"></i> Network Devices
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
            <?php if (\App\Auth::can('settings.view')): ?>
            <li class="nav-item">
                <a class="nav-link <?= $page === 'accounting' ? 'active' : '' ?>" href="?page=accounting">
                    <i class="bi bi-calculator"></i> Accounting
                </a>
            </li>
            <?php endif; ?>
            <li class="nav-item">
                <a class="nav-link <?= $page === 'whatsapp-chat' ? 'active' : '' ?>" href="?page=whatsapp-chat">
                    <i class="bi bi-whatsapp"></i> WhatsApp Chat
                </a>
            </li>
            <?php if (\App\Auth::can('settings.view')): ?>
            <li class="nav-item">
                <a class="nav-link <?= $page === 'settings' ? 'active' : '' ?>" href="?page=settings">
                    <i class="bi bi-gear"></i> Settings
                </a>
            </li>
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
        // Get current user's attendance status for clock-in widget
        $userAttendance = null;
        $hasEmployeeProfile = false;
        $currentUserId = \App\Auth::user()['id'] ?? null;
        if ($currentUserId) {
            $empStmt = $db->prepare("SELECT id FROM employees WHERE user_id = ?");
            $empStmt->execute([$currentUserId]);
            $empProfile = $empStmt->fetch(PDO::FETCH_ASSOC);
            if ($empProfile) {
                $hasEmployeeProfile = true;
                $attStmt = $db->prepare("SELECT * FROM attendance WHERE employee_id = ? AND date = ?");
                $attStmt->execute([$empProfile['id'], date('Y-m-d')]);
                $userAttendance = $attStmt->fetch(PDO::FETCH_ASSOC);
            }
        }
        ?>
        
        <?php if ($hasEmployeeProfile): ?>
        <!-- Clock In/Out Widget -->
        <div id="clock-widget" class="mb-3">
            <div class="card border-0 shadow-sm" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <div class="card-body py-2 px-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center text-white">
                            <i class="bi bi-clock-history fs-4 me-2"></i>
                            <div>
                                <span class="fw-bold" id="clock-display"><?= date('h:i:s A') ?></span>
                                <span class="ms-2 small opacity-75"><?= date('l, M j') ?></span>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span id="attendance-status" class="badge bg-light text-dark">
                                <?php if ($userAttendance && $userAttendance['clock_in'] && $userAttendance['clock_out']): ?>
                                    <i class="bi bi-check-circle text-success"></i> Worked <?= $userAttendance['hours_worked'] ?? 0 ?>h
                                <?php elseif ($userAttendance && $userAttendance['clock_in']): ?>
                                    <i class="bi bi-clock text-primary"></i> In: <?= date('h:i A', strtotime($userAttendance['clock_in'])) ?>
                                <?php else: ?>
                                    <i class="bi bi-x-circle text-muted"></i> Not clocked in
                                <?php endif; ?>
                            </span>
                            <?php if (!$userAttendance || !$userAttendance['clock_in']): ?>
                                <button id="btn-web-clock-in" class="btn btn-success btn-sm" onclick="webClockIn()">
                                    <i class="bi bi-box-arrow-in-right"></i> Clock In
                                </button>
                            <?php elseif (!$userAttendance['clock_out']): ?>
                                <button id="btn-web-clock-out" class="btn btn-danger btn-sm" onclick="webClockOut()">
                                    <i class="bi bi-box-arrow-right"></i> Clock Out
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

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
            case 'smartolt':
            case 'devices':
                if (!\App\Auth::can('settings.view')) {
                    $accessDenied = true;
                } else {
                    $deviceMonitor = new \App\DeviceMonitor($db);
                    $deviceMonitor->initializeTables();
                    $devices = $deviceMonitor->getDevices();
                    $stats = $deviceMonitor->getStatistics();
                    $onus = [];
                    if (!empty($_GET['device'])) {
                        $onus = $deviceMonitor->getOnus((int)$_GET['device'], ['status' => $_GET['status'] ?? '']);
                    }
                    include __DIR__ . '/../templates/devices.php';
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
        
        // Clock widget live time update
        function updateClockDisplay() {
            const clockEl = document.getElementById('clock-display');
            if (clockEl) {
                const now = new Date();
                clockEl.textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            }
        }
        setInterval(updateClockDisplay, 1000);
        
        // Web Clock In
        async function webClockIn() {
            const btn = document.getElementById('btn-web-clock-in');
            if (btn) btn.disabled = true;
            
            try {
                const response = await fetch('?page=api&action=clock_in', { 
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' }
                });
                const result = await response.json();
                
                if (result.success) {
                    const statusEl = document.getElementById('attendance-status');
                    if (statusEl) {
                        statusEl.innerHTML = '<i class="bi bi-clock text-primary"></i> In: ' + new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
                    }
                    
                    // Show late deduction alert if applicable
                    if (result.is_late && result.late_deduction > 0) {
                        alert('Late Clock-in!\n\nYou are ' + result.late_minutes + ' minutes late.\nDeduction: KES ' + result.late_deduction.toLocaleString());
                    }
                    
                    // Replace button with clock out
                    if (btn) {
                        btn.outerHTML = '<button id="btn-web-clock-out" class="btn btn-danger btn-sm" onclick="webClockOut()"><i class="bi bi-box-arrow-right"></i> Clock Out</button>';
                    }
                } else {
                    alert(result.error || 'Failed to clock in');
                    if (btn) btn.disabled = false;
                }
            } catch (error) {
                alert('Network error. Please try again.');
                if (btn) btn.disabled = false;
            }
        }
        
        // Web Clock Out
        async function webClockOut() {
            const btn = document.getElementById('btn-web-clock-out');
            if (btn) btn.disabled = true;
            
            try {
                const response = await fetch('?page=api&action=clock_out', { 
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' }
                });
                const result = await response.json();
                
                if (result.success) {
                    const statusEl = document.getElementById('attendance-status');
                    if (statusEl) {
                        statusEl.innerHTML = '<i class="bi bi-check-circle text-success"></i> Worked ' + result.hours_worked + 'h';
                    }
                    
                    // Remove clock out button
                    if (btn) btn.remove();
                } else {
                    alert(result.error || 'Failed to clock out');
                    if (btn) btn.disabled = false;
                }
            } catch (error) {
                alert('Network error. Please try again.');
                if (btn) btn.disabled = false;
            }
        }
    </script>
</body>
</html>
