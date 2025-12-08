<?php

header('Cache-Control: no-cache, no-store, must-revalidate');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/init_db.php';
require_once __DIR__ . '/../src/RealTimeAttendanceProcessor.php';
require_once __DIR__ . '/../src/BiometricSyncService.php';
require_once __DIR__ . '/../src/Settings.php';

initializeDatabase();

$db = Database::getConnection();
$processor = new \App\RealTimeAttendanceProcessor($db);
$biometricService = new \App\BiometricSyncService($db);
$settings = new \App\Settings();

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Handle ZKTeco native push protocol (iclock format)
// This handles requests like: /biometric-api.php?SN=XXXX&table=ATTLOG&Stamp=...
if (isset($_GET['SN']) || isset($_REQUEST['SN'])) {
    handleZKTecoPush($db, $processor, $settings);
    exit;
}

// Handle raw POST body for ZKTeco ATTLOG format
$rawInput = file_get_contents('php://input');
if ($method === 'POST' && !empty($rawInput) && strpos($rawInput, "\t") !== false) {
    handleZKTecoRawPush($db, $processor, $settings, $rawInput);
    exit;
}

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

$input = [];
if ($method === 'POST') {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    
    if (strpos($contentType, 'application/json') !== false) {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
    } else {
        $input = $_POST;
    }
}

function validateApiKey(): array {
    global $settings;
    
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? '';
    $configuredKey = $settings->get('biometric_api_key', '');
    
    if (empty($configuredKey)) {
        $configuredKey = getenv('BIOMETRIC_API_KEY') ?: '';
    }
    
    if (empty($configuredKey)) {
        return [
            'valid' => false,
            'error' => 'API key not configured. Set BIOMETRIC_API_KEY in environment or biometric_api_key in settings.'
        ];
    }
    
    if (strlen($configuredKey) < 16) {
        error_log('Biometric API: Configured API key is too short (minimum 16 characters required)');
        return [
            'valid' => false,
            'error' => 'Server configuration error. Contact administrator.'
        ];
    }
    
    if (empty($apiKey)) {
        return [
            'valid' => false,
            'error' => 'API key required. Provide via X-API-Key header or api_key parameter.'
        ];
    }
    
    if (!hash_equals($configuredKey, $apiKey)) {
        return [
            'valid' => false,
            'error' => 'Invalid API key.'
        ];
    }
    
    return ['valid' => true, 'error' => null];
}

function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function logApiAccess(string $action, bool $authenticated): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    error_log("Biometric API Access: action={$action}, auth=" . ($authenticated ? 'yes' : 'no') . ", ip={$ip}");
}

$publicActions = ['health'];
$requiresAuth = !in_array($action, $publicActions);

if ($requiresAuth) {
    $authResult = validateApiKey();
    if (!$authResult['valid']) {
        logApiAccess($action, false);
        jsonResponse(['success' => false, 'error' => $authResult['error']], 401);
    }
}

logApiAccess($action, true);

try {
    switch ($action) {
        case 'health':
            jsonResponse([
                'success' => true,
                'status' => 'ok',
                'timestamp' => date('Y-m-d H:i:s'),
                'version' => '1.0'
            ]);
            break;
            
        case 'push':
        case 'attendance':
            if ($method !== 'POST') {
                jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
            }
            
            $deviceId = $input['device_id'] ?? null;
            $deviceUserId = $input['user_id'] ?? $input['device_user_id'] ?? null;
            $logTime = $input['timestamp'] ?? $input['log_time'] ?? date('Y-m-d H:i:s');
            $direction = $input['direction'] ?? $input['type'] ?? 'unknown';
            
            if ($direction === 'check_in' || $direction === 'checkin' || $direction === '0') {
                $direction = 'in';
            } elseif ($direction === 'check_out' || $direction === 'checkout' || $direction === '1') {
                $direction = 'out';
            }
            
            if (!$deviceId || !$deviceUserId) {
                jsonResponse(['success' => false, 'error' => 'device_id and user_id are required'], 400);
            }
            
            $result = $processor->processBiometricEvent(
                (int)$deviceId,
                (string)$deviceUserId,
                $logTime,
                $direction
            );
            
            jsonResponse($result, $result['success'] ? 200 : 400);
            break;
            
        case 'clock-in':
            if ($method !== 'POST') {
                jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
            }
            
            $employeeId = $input['employee_id'] ?? null;
            $clockInTime = $input['time'] ?? $input['clock_in'] ?? date('H:i:s');
            $date = $input['date'] ?? date('Y-m-d');
            $source = $input['source'] ?? 'api';
            
            if (!$employeeId) {
                jsonResponse(['success' => false, 'error' => 'employee_id is required'], 400);
            }
            
            $result = $processor->processClockIn(
                (int)$employeeId,
                $clockInTime,
                $date,
                $source
            );
            
            jsonResponse($result, $result['success'] ? 200 : 400);
            break;
            
        case 'clock-out':
            if ($method !== 'POST') {
                jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
            }
            
            $employeeId = $input['employee_id'] ?? null;
            $clockOutTime = $input['time'] ?? $input['clock_out'] ?? date('H:i:s');
            $date = $input['date'] ?? date('Y-m-d');
            $source = $input['source'] ?? 'api';
            
            if (!$employeeId) {
                jsonResponse(['success' => false, 'error' => 'employee_id is required'], 400);
            }
            
            $result = $processor->processClockOut(
                (int)$employeeId,
                $clockOutTime,
                $date,
                $source
            );
            
            jsonResponse($result, $result['success'] ? 200 : 400);
            break;
            
        case 'bulk-push':
            if ($method !== 'POST') {
                jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
            }
            
            $records = $input['records'] ?? $input['data'] ?? [];
            
            if (empty($records)) {
                jsonResponse(['success' => false, 'error' => 'No records provided'], 400);
            }
            
            $results = [];
            $successCount = 0;
            $failCount = 0;
            
            foreach ($records as $record) {
                $deviceId = $record['device_id'] ?? null;
                $deviceUserId = $record['user_id'] ?? $record['device_user_id'] ?? null;
                $logTime = $record['timestamp'] ?? $record['log_time'] ?? date('Y-m-d H:i:s');
                $direction = $record['direction'] ?? $record['type'] ?? 'unknown';
                
                if ($direction === 'check_in' || $direction === 'checkin' || $direction === '0') {
                    $direction = 'in';
                } elseif ($direction === 'check_out' || $direction === 'checkout' || $direction === '1') {
                    $direction = 'out';
                }
                
                if ($deviceId && $deviceUserId) {
                    $result = $processor->processBiometricEvent(
                        (int)$deviceId,
                        (string)$deviceUserId,
                        $logTime,
                        $direction
                    );
                    
                    if ($result['success']) {
                        $successCount++;
                    } else {
                        $failCount++;
                    }
                    
                    $results[] = $result;
                }
            }
            
            jsonResponse([
                'success' => true,
                'processed' => count($results),
                'success_count' => $successCount,
                'fail_count' => $failCount,
                'results' => $results
            ]);
            break;
            
        case 'stats':
            $stats = $processor->getRealtimeStats();
            jsonResponse(['success' => true, 'data' => $stats]);
            break;
            
        case 'late-arrivals':
            $lateArrivals = $processor->getTodayLateArrivals();
            jsonResponse(['success' => true, 'data' => $lateArrivals]);
            break;
            
        case 'notification-logs':
            $employeeId = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : null;
            $dateFrom = $_GET['date_from'] ?? null;
            $dateTo = $_GET['date_to'] ?? null;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
            
            $logs = $processor->getNotificationLogs($employeeId, $dateFrom, $dateTo, $limit);
            jsonResponse(['success' => true, 'data' => $logs]);
            break;
            
        case 'sync-device':
            if ($method !== 'POST') {
                jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
            }
            
            $deviceId = $input['device_id'] ?? null;
            if (!$deviceId) {
                jsonResponse(['success' => false, 'error' => 'device_id is required'], 400);
            }
            
            $result = $biometricService->syncDevice((int)$deviceId);
            jsonResponse($result);
            break;
            
        case 'sync-all':
            if ($method !== 'POST') {
                jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
            }
            
            $results = $biometricService->syncAllDevices();
            jsonResponse(['success' => true, 'results' => $results]);
            break;
            
        case 'devices':
            $devices = $biometricService->getDevices(false);
            jsonResponse(['success' => true, 'data' => $devices]);
            break;
            
        case 'zkteco-push':
            if ($method !== 'POST') {
                jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
            }
            
            $sn = $input['SN'] ?? $input['sn'] ?? '';
            $table = $input['TABLE'] ?? $input['table'] ?? 'ATTLOG';
            $stamp = $input['Stamp'] ?? $input['stamp'] ?? '';
            
            if ($table === 'ATTLOG') {
                $pin = $input['PIN'] ?? $input['pin'] ?? '';
                $time = $input['TIME'] ?? $input['time'] ?? '';
                $status = $input['STATUS'] ?? $input['status'] ?? '0';
                $verify = $input['VERIFY'] ?? $input['verify'] ?? '';
                
                if (!$sn || !$pin || !$time) {
                    jsonResponse(['success' => false, 'error' => 'Missing required fields'], 400);
                }
                
                $deviceStmt = $db->prepare("SELECT id FROM biometric_devices WHERE serial_number = ? OR name LIKE ?");
                $deviceStmt->execute([$sn, "%$sn%"]);
                $device = $deviceStmt->fetch(\PDO::FETCH_ASSOC);
                
                if (!$device) {
                    jsonResponse(['success' => false, 'error' => 'Unknown device'], 404);
                }
                
                $direction = $status === '0' ? 'in' : ($status === '1' ? 'out' : 'unknown');
                
                $result = $processor->processBiometricEvent(
                    (int)$device['id'],
                    (string)$pin,
                    $time,
                    $direction
                );
                
                jsonResponse($result, $result['success'] ? 200 : 400);
            }
            
            jsonResponse(['success' => true, 'message' => 'Acknowledged']);
            break;
            
        case 'register-user':
            if ($method !== 'POST') {
                jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
            }
            
            $deviceId = $input['device_id'] ?? null;
            $employeeNo = $input['employee_no'] ?? $input['employee_id'] ?? null;
            $name = $input['name'] ?? null;
            $cardNo = $input['card_no'] ?? null;
            
            if (!$deviceId || !$employeeNo || !$name) {
                jsonResponse(['success' => false, 'error' => 'device_id, employee_no, and name are required'], 400);
            }
            
            $deviceStmt = $db->prepare("SELECT * FROM biometric_devices WHERE id = ?");
            $deviceStmt->execute([$deviceId]);
            $device = $deviceStmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$device) {
                jsonResponse(['success' => false, 'error' => 'Device not found'], 404);
            }
            
            if ($device['device_type'] === 'hikvision') {
                require_once __DIR__ . '/../src/HikvisionDevice.php';
                $hikDevice = new \App\HikvisionDevice(
                    $device['id'],
                    $device['ip_address'],
                    $device['port'] ?: 80,
                    $device['username'],
                    $device['password_encrypted']
                );
                $startEnrollment = $input['start_enrollment'] ?? true;
                $result = $hikDevice->addUserWithEnrollment((string)$employeeNo, $name, $cardNo, $startEnrollment);
            } else {
                $result = ['success' => false, 'error' => 'User registration not supported for this device type'];
            }
            
            jsonResponse($result, $result['success'] ? 200 : 400);
            break;
            
        case 'start-enrollment':
            if ($method !== 'POST') {
                jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
            }
            
            $deviceId = $input['device_id'] ?? null;
            $employeeNo = $input['employee_no'] ?? null;
            $enrollType = $input['type'] ?? 'face';
            
            if (!$deviceId || !$employeeNo) {
                jsonResponse(['success' => false, 'error' => 'device_id and employee_no are required'], 400);
            }
            
            $deviceStmt = $db->prepare("SELECT * FROM biometric_devices WHERE id = ?");
            $deviceStmt->execute([$deviceId]);
            $device = $deviceStmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$device || $device['device_type'] !== 'hikvision') {
                jsonResponse(['success' => false, 'error' => 'Hikvision device not found'], 404);
            }
            
            require_once __DIR__ . '/../src/HikvisionDevice.php';
            $hikDevice = new \App\HikvisionDevice(
                $device['id'],
                $device['ip_address'],
                $device['port'] ?: 80,
                $device['username'],
                $device['password_encrypted']
            );
            
            if ($enrollType === 'fingerprint') {
                $result = $hikDevice->startFingerprintEnrollment((string)$employeeNo);
            } else {
                $result = $hikDevice->startFaceEnrollment((string)$employeeNo);
            }
            
            jsonResponse($result, $result['success'] ? 200 : 400);
            break;
            
        case 'sync-employees-to-device':
            if ($method !== 'POST') {
                jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
            }
            
            $deviceId = $input['device_id'] ?? null;
            $employeeIds = $input['employee_ids'] ?? [];
            
            if (!$deviceId) {
                jsonResponse(['success' => false, 'error' => 'device_id is required'], 400);
            }
            
            $deviceStmt = $db->prepare("SELECT * FROM biometric_devices WHERE id = ?");
            $deviceStmt->execute([$deviceId]);
            $device = $deviceStmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$device) {
                jsonResponse(['success' => false, 'error' => 'Device not found'], 404);
            }
            
            if ($device['device_type'] !== 'hikvision') {
                jsonResponse(['success' => false, 'error' => 'Employee sync only supported for Hikvision devices'], 400);
            }
            
            require_once __DIR__ . '/../src/HikvisionDevice.php';
            $hikDevice = new \App\HikvisionDevice(
                $device['id'],
                $device['ip_address'],
                $device['port'] ?: 80,
                $device['username'],
                $device['password_encrypted']
            );
            
            if (empty($employeeIds)) {
                $empStmt = $db->query("SELECT id, name, biometric_id, card_number FROM employees WHERE status = 'active'");
            } else {
                $placeholders = implode(',', array_fill(0, count($employeeIds), '?'));
                $empStmt = $db->prepare("SELECT id, name, biometric_id, card_number FROM employees WHERE id IN ($placeholders)");
                $empStmt->execute($employeeIds);
            }
            
            $employees = $empStmt->fetchAll(\PDO::FETCH_ASSOC);
            $results = [];
            $successCount = 0;
            $failCount = 0;
            
            $startEnrollment = $input['start_enrollment'] ?? true;
            
            foreach ($employees as $emp) {
                $bioId = $emp['biometric_id'] ?: (string)$emp['id'];
                $result = $hikDevice->addUserWithEnrollment($bioId, $emp['name'], $emp['card_number'], $startEnrollment);
                $results[] = [
                    'employee_id' => $emp['id'],
                    'name' => $emp['name'],
                    'biometric_id' => $bioId,
                    'success' => $result['success'],
                    'message' => $result['message'] ?? $result['error'] ?? ''
                ];
                if ($result['success']) {
                    $successCount++;
                    if (empty($emp['biometric_id'])) {
                        $updateStmt = $db->prepare("UPDATE employees SET biometric_id = ? WHERE id = ?");
                        $updateStmt->execute([$bioId, $emp['id']]);
                    }
                } else {
                    $failCount++;
                }
            }
            
            jsonResponse([
                'success' => true,
                'total' => count($employees),
                'success_count' => $successCount,
                'fail_count' => $failCount,
                'results' => $results
            ]);
            break;
            
        case 'delete-device-user':
            if ($method !== 'POST') {
                jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
            }
            
            $deviceId = $input['device_id'] ?? null;
            $employeeNo = $input['employee_no'] ?? null;
            
            if (!$deviceId || !$employeeNo) {
                jsonResponse(['success' => false, 'error' => 'device_id and employee_no are required'], 400);
            }
            
            $deviceStmt = $db->prepare("SELECT * FROM biometric_devices WHERE id = ?");
            $deviceStmt->execute([$deviceId]);
            $device = $deviceStmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$device || $device['device_type'] !== 'hikvision') {
                jsonResponse(['success' => false, 'error' => 'Hikvision device not found'], 404);
            }
            
            require_once __DIR__ . '/../src/HikvisionDevice.php';
            $hikDevice = new \App\HikvisionDevice(
                $device['id'],
                $device['ip_address'],
                $device['port'] ?: 80,
                $device['username'],
                $device['password_encrypted']
            );
            $result = $hikDevice->deleteUser((string)$employeeNo);
            jsonResponse($result, $result['success'] ? 200 : 400);
            break;
            
        case 'hikvision-push':
            if ($method !== 'POST') {
                jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
            }
            
            $events = $input['AccessControllerEvent'] ?? $input['events'] ?? [$input];
            
            if (!is_array($events)) {
                $events = [$events];
            }
            
            $results = [];
            
            foreach ($events as $event) {
                $deviceId = $event['deviceID'] ?? $event['device_id'] ?? null;
                $employeeNo = $event['employeeNoString'] ?? $event['employeeNo'] ?? $event['user_id'] ?? null;
                $time = $event['time'] ?? $event['dateTime'] ?? date('Y-m-d H:i:s');
                $eventType = $event['eventType'] ?? $event['type'] ?? '';
                
                $direction = 'unknown';
                if (stripos($eventType, 'entry') !== false || stripos($eventType, 'in') !== false) {
                    $direction = 'in';
                } elseif (stripos($eventType, 'exit') !== false || stripos($eventType, 'out') !== false) {
                    $direction = 'out';
                }
                
                if ($deviceId && $employeeNo) {
                    $deviceStmt = $db->prepare("SELECT id FROM biometric_devices WHERE ip_address = ? OR name LIKE ?");
                    $deviceStmt->execute([$deviceId, "%$deviceId%"]);
                    $device = $deviceStmt->fetch(\PDO::FETCH_ASSOC);
                    
                    if ($device) {
                        $result = $processor->processBiometricEvent(
                            (int)$device['id'],
                            (string)$employeeNo,
                            $time,
                            $direction
                        );
                        $results[] = $result;
                    }
                }
            }
            
            jsonResponse([
                'success' => true,
                'processed' => count($results),
                'results' => $results
            ]);
            break;
            
        default:
            jsonResponse(['success' => false, 'error' => 'Unknown action'], 400);
    }
    
} catch (\Exception $e) {
    error_log('Biometric API Error: ' . $e->getMessage());
    jsonResponse([
        'success' => false,
        'error' => 'Internal server error',
        'message' => $e->getMessage()
    ], 500);
}

/**
 * Handle ZKTeco native push protocol (iclock format)
 * The device sends: GET /biometric-api.php?SN=XXXX to register
 * Then POST /biometric-api.php?SN=XXXX&table=ATTLOG&Stamp=... with attendance data
 */
function handleZKTecoPush($db, $processor, $settings) {
    $sn = $_REQUEST['SN'] ?? '';
    $table = $_REQUEST['table'] ?? '';
    $stamp = $_REQUEST['Stamp'] ?? '0';
    $method = $_SERVER['REQUEST_METHOD'];
    
    // Log the request for debugging
    error_log("ZKTeco Push: SN=$sn, table=$table, stamp=$stamp, method=$method");
    
    // Validate device token if configured
    $deviceToken = $settings->get('zkteco_push_token', '');
    if (!empty($deviceToken)) {
        $providedToken = $_GET['token'] ?? $_REQUEST['token'] ?? '';
        if ($providedToken !== $deviceToken) {
            error_log("ZKTeco Push: Invalid token for device $sn");
            header('HTTP/1.1 401 Unauthorized');
            echo "ERROR";
            return;
        }
    }
    
    // Find device by serial number
    $deviceStmt = $db->prepare("SELECT id, name FROM biometric_devices WHERE serial_number = ? OR name LIKE ? LIMIT 1");
    $deviceStmt->execute([$sn, "%$sn%"]);
    $device = $deviceStmt->fetch(\PDO::FETCH_ASSOC);
    
    // If device not found, try to auto-register
    if (!$device && !empty($sn)) {
        $autoRegister = $settings->get('zkteco_auto_register', '0');
        if ($autoRegister === '1') {
            $insertStmt = $db->prepare("INSERT INTO biometric_devices (name, type, ip_address, serial_number, enabled) VALUES (?, 'zkteco', '', ?, true) RETURNING id");
            $insertStmt->execute(["ZKTeco $sn", $sn]);
            $device = $insertStmt->fetch(\PDO::FETCH_ASSOC);
            $device['name'] = "ZKTeco $sn";
            error_log("ZKTeco Push: Auto-registered device $sn with ID " . $device['id']);
        }
    }
    
    // Handle GET request (device checking for commands)
    if ($method === 'GET') {
        // Device is asking if server has any commands
        // We respond with OK to acknowledge and optionally send commands
        header('Content-Type: text/plain');
        
        if ($device) {
            // Update last seen timestamp
            $updateStmt = $db->prepare("UPDATE biometric_devices SET last_sync = NOW() WHERE id = ?");
            $updateStmt->execute([$device['id']]);
        }
        
        // Return OK with stamp to acknowledge
        echo "OK";
        return;
    }
    
    // Handle POST request (device pushing data)
    if ($method === 'POST') {
        if (!$device) {
            error_log("ZKTeco Push: Unknown device $sn");
            header('Content-Type: text/plain');
            echo "OK"; // Still acknowledge to prevent device from retrying
            return;
        }
        
        $rawData = file_get_contents('php://input');
        error_log("ZKTeco Push Raw Data: " . substr($rawData, 0, 500));
        
        $processed = 0;
        $errors = 0;
        
        if ($table === 'ATTLOG' && !empty($rawData)) {
            // Parse ATTLOG format: PIN\tTime\tStatus\tVerify\tWorkcode\tReserved
            // Example: 1\t2024-12-05 09:00:00\t0\t1\t0\t0
            $lines = explode("\n", trim($rawData));
            
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                
                $parts = explode("\t", $line);
                if (count($parts) >= 2) {
                    $pin = trim($parts[0]);
                    $time = trim($parts[1]);
                    $status = isset($parts[2]) ? trim($parts[2]) : '0';
                    
                    // Status: 0=Check-In, 1=Check-Out, 2=Break-Out, 3=Break-In, 4=OT-In, 5=OT-Out
                    $direction = 'unknown';
                    if ($status === '0' || $status === '3' || $status === '4') {
                        $direction = 'in';
                    } elseif ($status === '1' || $status === '2' || $status === '5') {
                        $direction = 'out';
                    }
                    
                    error_log("ZKTeco Push: Processing PIN=$pin, Time=$time, Status=$status, Direction=$direction");
                    
                    try {
                        $result = $processor->processBiometricEvent(
                            (int)$device['id'],
                            (string)$pin,
                            $time,
                            $direction
                        );
                        
                        if ($result['success']) {
                            $processed++;
                        } else {
                            $errors++;
                            error_log("ZKTeco Push: Failed to process - " . ($result['message'] ?? 'Unknown error'));
                        }
                    } catch (\Exception $e) {
                        $errors++;
                        error_log("ZKTeco Push Error: " . $e->getMessage());
                    }
                }
            }
        } elseif ($table === 'OPERLOG') {
            // Operation log - just acknowledge
            error_log("ZKTeco Push: Received OPERLOG data (ignored)");
        } elseif ($table === 'USERINFO') {
            // User info - could be used to sync users
            error_log("ZKTeco Push: Received USERINFO data");
            // TODO: Could parse and store user info if needed
        }
        
        error_log("ZKTeco Push: Processed $processed records, $errors errors");
        
        // Update device last sync
        $updateStmt = $db->prepare("UPDATE biometric_devices SET last_sync = NOW() WHERE id = ?");
        $updateStmt->execute([$device['id']]);
        
        header('Content-Type: text/plain');
        echo "OK";
        return;
    }
    
    header('Content-Type: text/plain');
    echo "OK";
}

/**
 * Handle raw ZKTeco ATTLOG format in POST body
 * Some devices send raw tab-separated data without query parameters
 */
function handleZKTecoRawPush($db, $processor, $settings, $rawInput) {
    error_log("ZKTeco Raw Push: Received " . strlen($rawInput) . " bytes");
    
    // Try to extract device ID from User-Agent or other headers
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $deviceId = null;
    
    // Check if device ID is in URL
    if (isset($_GET['device_id'])) {
        $deviceId = (int)$_GET['device_id'];
    }
    
    // If no device ID, try to find the first active ZKTeco device
    if (!$deviceId) {
        $stmt = $db->query("SELECT id FROM biometric_devices WHERE type = 'zkteco' AND enabled = true ORDER BY id LIMIT 1");
        $device = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($device) {
            $deviceId = $device['id'];
        }
    }
    
    if (!$deviceId) {
        error_log("ZKTeco Raw Push: No device found");
        header('Content-Type: text/plain');
        echo "OK";
        return;
    }
    
    $processed = 0;
    $lines = explode("\n", trim($rawInput));
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        $parts = explode("\t", $line);
        if (count($parts) >= 2) {
            $pin = trim($parts[0]);
            $time = trim($parts[1]);
            $status = isset($parts[2]) ? trim($parts[2]) : '0';
            
            $direction = ($status === '0' || $status === '3' || $status === '4') ? 'in' : 
                        (($status === '1' || $status === '2' || $status === '5') ? 'out' : 'unknown');
            
            try {
                $result = $processor->processBiometricEvent($deviceId, (string)$pin, $time, $direction);
                if ($result['success']) $processed++;
            } catch (\Exception $e) {
                error_log("ZKTeco Raw Push Error: " . $e->getMessage());
            }
        }
    }
    
    error_log("ZKTeco Raw Push: Processed $processed records");
    
    header('Content-Type: text/plain');
    echo "OK";
}
