<?php

header('Content-Type: application/json');
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
