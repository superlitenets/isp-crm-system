<?php

namespace App;

require_once __DIR__ . '/BiometricDevice.php';
require_once __DIR__ . '/ZKTecoDevice.php';
require_once __DIR__ . '/HikvisionDevice.php';

class BiometricSyncService {
    private \PDO $db;
    
    public function __construct(\PDO $db) {
        $this->db = $db;
    }
    
    public function getDevices(bool $activeOnly = true): array {
        $sql = "SELECT * FROM biometric_devices";
        if ($activeOnly) {
            $sql .= " WHERE is_active = TRUE";
        }
        $sql .= " ORDER BY name";
        
        return $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getDevice(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM biometric_devices WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    
    public function addDevice(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO biometric_devices (name, device_type, ip_address, port, username, password_encrypted, sync_interval_minutes, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $password = !empty($data['password']) ? BiometricDevice::encryptPassword($data['password']) : null;
        
        $stmt->execute([
            $data['name'],
            $data['device_type'],
            $data['ip_address'],
            $data['port'] ?? ($data['device_type'] === 'zkteco' ? 4370 : 80),
            $data['username'] ?? null,
            $password,
            $data['sync_interval_minutes'] ?? 15,
            isset($data['is_active']) ? (bool)$data['is_active'] : true
        ]);
        
        return (int)$this->db->lastInsertId();
    }
    
    public function updateDevice(int $id, array $data): bool {
        $fields = ['name', 'device_type', 'ip_address', 'port', 'username', 'sync_interval_minutes', 'is_active'];
        $updates = [];
        $params = [];
        
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $updates[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }
        
        if (!empty($data['password'])) {
            $updates[] = "password_encrypted = ?";
            $params[] = BiometricDevice::encryptPassword($data['password']);
        }
        
        $updates[] = "updated_at = CURRENT_TIMESTAMP";
        $params[] = $id;
        
        $sql = "UPDATE biometric_devices SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
    
    public function deleteDevice(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM biometric_devices WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    public function testDevice(int $id): array {
        $deviceConfig = $this->getDevice($id);
        if (!$deviceConfig) {
            return ['success' => false, 'message' => 'Device not found'];
        }
        
        $device = BiometricDevice::create($deviceConfig);
        if (!$device) {
            return ['success' => false, 'message' => 'Unsupported device type'];
        }
        
        return $device->testConnection();
    }
    
    public function syncDevice(int $id, ?string $since = null, bool $debug = false): array {
        $result = [
            'success' => false,
            'records_synced' => 0,
            'records_processed' => 0,
            'records_received' => 0,
            'message' => ''
        ];
        
        if ($debug) {
            $result['debug'] = [];
        }
        
        $deviceConfig = $this->getDevice($id);
        if (!$deviceConfig) {
            $result['message'] = 'Device not found';
            return $result;
        }
        
        if ($debug) {
            $result['debug']['device'] = [
                'type' => $deviceConfig['device_type'],
                'ip' => $deviceConfig['ip_address'],
                'port' => $deviceConfig['port']
            ];
        }
        
        $device = BiometricDevice::create($deviceConfig);
        if (!$device) {
            $result['message'] = 'Unsupported device type';
            return $result;
        }
        
        if (!$device->connect()) {
            $error = $device->getLastError();
            $result['message'] = $error['message'] ?? 'Connection failed';
            if ($debug) {
                $result['debug']['connection_error'] = $error;
            }
            $this->updateSyncStatus($id, 'failed', $result['message']);
            return $result;
        }
        
        if ($debug) {
            $result['debug']['connected'] = true;
        }
        
        try {
            if (!$since) {
                $since = $deviceConfig['last_sync_at'] ?? date('Y-m-d', strtotime('-30 days'));
            }
            
            if ($debug) {
                $result['debug']['sync_since'] = $since;
            }
            
            $attendance = $device->getAttendance($since);
            $result['records_received'] = count($attendance);
            
            if ($debug) {
                $result['debug']['attendance_count'] = count($attendance);
                if (count($attendance) > 0) {
                    $result['debug']['first_record'] = $attendance[0];
                    $result['debug']['last_record'] = $attendance[count($attendance) - 1];
                }
                $error = $device->getLastError();
                if ($error) {
                    $result['debug']['device_error'] = $error;
                }
            }
            
            $syncedCount = 0;
            foreach ($attendance as $log) {
                if ($this->saveAttendanceLog($id, $log)) {
                    $syncedCount++;
                }
            }
            
            $processedCount = $this->processAttendanceLogs($id);
            
            $result['success'] = true;
            $result['records_synced'] = $syncedCount;
            $result['records_processed'] = $processedCount;
            
            if ($syncedCount === 0 && count($attendance) === 0) {
                $result['message'] = "No attendance records found on device since {$since}. Try clearing logs on device and testing again.";
            } else {
                $result['message'] = "Synced {$syncedCount} records, processed {$processedCount} attendance entries";
            }
            
            $this->updateSyncStatus($id, 'success', $result['message']);
            
        } catch (\Exception $e) {
            $result['message'] = $e->getMessage();
            if ($debug) {
                $result['debug']['exception'] = $e->getMessage();
            }
            $this->updateSyncStatus($id, 'failed', $result['message']);
        }
        
        $device->disconnect();
        return $result;
    }
    
    public function syncAllDevices(): array {
        $devices = $this->getDevices(true);
        $results = [];
        
        foreach ($devices as $device) {
            $results[$device['id']] = $this->syncDevice($device['id']);
        }
        
        return $results;
    }
    
    private function saveAttendanceLog(int $deviceId, array $log): bool {
        $employeeId = $this->getEmployeeIdFromDeviceUser($deviceId, $log['device_user_id']);
        
        $stmt = $this->db->prepare("
            INSERT INTO biometric_attendance_logs 
            (device_id, employee_id, device_user_id, log_time, direction, verification_type, raw_data)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON CONFLICT (device_id, device_user_id, log_time) DO NOTHING
        ");
        
        return $stmt->execute([
            $deviceId,
            $employeeId,
            $log['device_user_id'],
            $log['log_time'],
            $log['direction'] ?? 'unknown',
            $log['verification_type'] ?? 'unknown',
            json_encode($log['raw_data'] ?? $log)
        ]);
    }
    
    private function getEmployeeIdFromDeviceUser(int $deviceId, string $deviceUserId): ?int {
        $stmt = $this->db->prepare("
            SELECT employee_id FROM device_user_mapping 
            WHERE device_id = ? AND device_user_id = ?
        ");
        $stmt->execute([$deviceId, $deviceUserId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ? (int)$result['employee_id'] : null;
    }
    
    private function processAttendanceLogs(int $deviceId): int {
        $stmt = $this->db->prepare("
            SELECT bal.*, dum.employee_id as mapped_employee_id
            FROM biometric_attendance_logs bal
            LEFT JOIN device_user_mapping dum ON bal.device_id = dum.device_id AND bal.device_user_id = dum.device_user_id
            WHERE bal.device_id = ? AND bal.processed = FALSE AND dum.employee_id IS NOT NULL
            ORDER BY bal.log_time
        ");
        $stmt->execute([$deviceId]);
        $logs = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $processed = 0;
        $groupedByDate = [];
        
        foreach ($logs as $log) {
            $employeeId = $log['mapped_employee_id'];
            $date = date('Y-m-d', strtotime($log['log_time']));
            $key = "{$employeeId}_{$date}";
            
            if (!isset($groupedByDate[$key])) {
                $groupedByDate[$key] = [
                    'employee_id' => $employeeId,
                    'date' => $date,
                    'logs' => []
                ];
            }
            
            $groupedByDate[$key]['logs'][] = $log;
        }
        
        foreach ($groupedByDate as $group) {
            if ($this->updateAttendanceFromLogs($group['employee_id'], $group['date'], $group['logs'])) {
                $processed++;
            }
        }
        
        $updateStmt = $this->db->prepare("
            UPDATE biometric_attendance_logs 
            SET processed = TRUE, employee_id = (
                SELECT employee_id FROM device_user_mapping dum 
                WHERE dum.device_id = biometric_attendance_logs.device_id 
                AND dum.device_user_id = biometric_attendance_logs.device_user_id
            )
            WHERE device_id = ? AND processed = FALSE
        ");
        $updateStmt->execute([$deviceId]);
        
        return $processed;
    }
    
    private function updateAttendanceFromLogs(int $employeeId, string $date, array $logs): bool {
        $inLogs = array_filter($logs, fn($l) => $l['direction'] === 'in');
        $outLogs = array_filter($logs, fn($l) => $l['direction'] === 'out');
        
        $clockIn = null;
        $clockOut = null;
        
        if (!empty($inLogs)) {
            usort($inLogs, fn($a, $b) => strtotime($a['log_time']) - strtotime($b['log_time']));
            $clockIn = date('H:i:s', strtotime($inLogs[0]['log_time']));
        }
        
        if (!empty($outLogs)) {
            usort($outLogs, fn($a, $b) => strtotime($b['log_time']) - strtotime($a['log_time']));
            $clockOut = date('H:i:s', strtotime($outLogs[0]['log_time']));
        }
        
        if (!$clockIn && !$clockOut && !empty($logs)) {
            usort($logs, fn($a, $b) => strtotime($a['log_time']) - strtotime($b['log_time']));
            $clockIn = date('H:i:s', strtotime($logs[0]['log_time']));
            if (count($logs) > 1) {
                $clockOut = date('H:i:s', strtotime($logs[count($logs) - 1]['log_time']));
            }
        }
        
        $lateCalculator = new LateDeductionCalculator($this->db);
        $lateMinutes = $clockIn ? $lateCalculator->calculateLateMinutes($employeeId, $clockIn) : 0;
        
        $hoursWorked = null;
        if ($clockIn && $clockOut) {
            $inTime = strtotime($clockIn);
            $outTime = strtotime($clockOut);
            if ($outTime > $inTime) {
                $hoursWorked = round(($outTime - $inTime) / 3600, 2);
            }
        }
        
        $stmt = $this->db->prepare("
            INSERT INTO attendance (employee_id, date, clock_in, clock_out, hours_worked, late_minutes, status, source)
            VALUES (?, ?, ?, ?, ?, ?, 'present', 'biometric')
            ON CONFLICT (employee_id, date) 
            DO UPDATE SET 
                clock_in = COALESCE(EXCLUDED.clock_in, attendance.clock_in),
                clock_out = COALESCE(EXCLUDED.clock_out, attendance.clock_out),
                hours_worked = COALESCE(EXCLUDED.hours_worked, attendance.hours_worked),
                late_minutes = EXCLUDED.late_minutes,
                source = 'biometric',
                updated_at = CURRENT_TIMESTAMP
        ");
        
        return $stmt->execute([$employeeId, $date, $clockIn, $clockOut, $hoursWorked, $lateMinutes]);
    }
    
    private function updateSyncStatus(int $deviceId, string $status, string $message): void {
        $stmt = $this->db->prepare("
            UPDATE biometric_devices 
            SET last_sync_at = CURRENT_TIMESTAMP, last_sync_status = ?, last_sync_message = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([$status, $message, $deviceId]);
    }
    
    public function getDeviceUsers(int $deviceId): array {
        $deviceConfig = $this->getDevice($deviceId);
        if (!$deviceConfig) {
            return [];
        }
        
        $device = BiometricDevice::create($deviceConfig);
        if (!$device || !$device->connect()) {
            return [];
        }
        
        $users = $device->getUsers();
        $device->disconnect();
        
        return $users;
    }
    
    public function getDeviceUsersWithDebug(int $deviceId): array {
        $result = [
            'users' => [],
            'debug' => ['error' => 'Device not found']
        ];
        
        $deviceConfig = $this->getDevice($deviceId);
        if (!$deviceConfig) {
            return $result;
        }
        
        $device = BiometricDevice::create($deviceConfig);
        if (!$device) {
            $result['debug'] = ['error' => 'Unsupported device type'];
            return $result;
        }
        
        if (method_exists($device, 'getUsersWithDebug')) {
            return $device->getUsersWithDebug();
        }
        
        $users = $device->getUsers();
        $device->disconnect();
        
        return [
            'users' => $users,
            'debug' => ['info' => 'Debug not available for this device type']
        ];
    }
    
    public function getUserMappings(int $deviceId): array {
        $stmt = $this->db->prepare("
            SELECT dum.*, e.name as employee_name, e.employee_id as employee_code
            FROM device_user_mapping dum
            LEFT JOIN employees e ON dum.employee_id = e.id
            WHERE dum.device_id = ?
            ORDER BY dum.device_user_id
        ");
        $stmt->execute([$deviceId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function saveUserMapping(int $deviceId, string $deviceUserId, int $employeeId, ?string $deviceUserName = null): bool {
        $stmt = $this->db->prepare("
            INSERT INTO device_user_mapping (device_id, device_user_id, employee_id, device_user_name)
            VALUES (?, ?, ?, ?)
            ON CONFLICT (device_id, device_user_id) 
            DO UPDATE SET employee_id = EXCLUDED.employee_id, device_user_name = EXCLUDED.device_user_name
        ");
        return $stmt->execute([$deviceId, $deviceUserId, $employeeId, $deviceUserName]);
    }
    
    public function deleteUserMapping(int $deviceId, string $deviceUserId): bool {
        $stmt = $this->db->prepare("DELETE FROM device_user_mapping WHERE device_id = ? AND device_user_id = ?");
        return $stmt->execute([$deviceId, $deviceUserId]);
    }
    
    public function getUnmappedLogs(): array {
        $stmt = $this->db->query("
            SELECT bal.device_user_id, bd.name as device_name, bd.id as device_id, COUNT(*) as log_count
            FROM biometric_attendance_logs bal
            JOIN biometric_devices bd ON bal.device_id = bd.id
            LEFT JOIN device_user_mapping dum ON bal.device_id = dum.device_id AND bal.device_user_id = dum.device_user_id
            WHERE dum.id IS NULL
            GROUP BY bal.device_user_id, bd.name, bd.id
            ORDER BY log_count DESC
        ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getAttendanceLogs(int $deviceId, ?string $date = null, ?int $employeeId = null): array {
        $sql = "
            SELECT bal.*, e.name as employee_name, e.employee_id as employee_code
            FROM biometric_attendance_logs bal
            LEFT JOIN employees e ON bal.employee_id = e.id
            WHERE bal.device_id = ?
        ";
        $params = [$deviceId];
        
        if ($date) {
            $sql .= " AND DATE(bal.log_time) = ?";
            $params[] = $date;
        }
        
        if ($employeeId) {
            $sql .= " AND bal.employee_id = ?";
            $params[] = $employeeId;
        }
        
        $sql .= " ORDER BY bal.log_time DESC LIMIT 500";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
