<?php

namespace App;

class FleetManagement {
    private \PDO $db;
    private ProtrackService $protrack;
    
    public function __construct(?\PDO $db = null) {
        $this->db = $db ?? \Database::getConnection();
        $this->protrack = new ProtrackService($this->db);
    }
    
    public function getProtrack(): ProtrackService {
        return $this->protrack;
    }
    
    public function isConfigured(): bool {
        return $this->protrack->isConfigured();
    }
    
    public function getStats(): array {
        $stats = [];
        $stats['total_vehicles'] = (int)$this->db->query("SELECT COUNT(*) FROM fleet_vehicles")->fetchColumn();
        $stats['active_vehicles'] = (int)$this->db->query("SELECT COUNT(*) FROM fleet_vehicles WHERE status = 'active'")->fetchColumn();
        $stats['assigned_vehicles'] = (int)$this->db->query("SELECT COUNT(*) FROM fleet_vehicles WHERE assigned_employee_id IS NOT NULL")->fetchColumn();
        $stats['total_geofences'] = (int)$this->db->query("SELECT COUNT(*) FROM fleet_geofences")->fetchColumn();
        $stats['unacknowledged_alarms'] = (int)$this->db->query("SELECT COUNT(*) FROM fleet_alarms WHERE acknowledged = FALSE")->fetchColumn();
        return $stats;
    }
    
    public function getVehicles(array $filters = []): array {
        $sql = "SELECT v.*, e.name as employee_name 
                FROM fleet_vehicles v 
                LEFT JOIN employees e ON v.assigned_employee_id = e.id 
                WHERE 1=1";
        $params = [];
        
        if (!empty($filters['status'])) {
            $sql .= " AND v.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['employee_id'])) {
            $sql .= " AND v.assigned_employee_id = ?";
            $params[] = $filters['employee_id'];
        }
        if (!empty($filters['search'])) {
            $sql .= " AND (v.name ILIKE ? OR v.plate_number ILIKE ? OR v.imei ILIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        $sql .= " ORDER BY v.name ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getVehicle(int $id): ?array {
        $stmt = $this->db->prepare("SELECT v.*, e.name as employee_name FROM fleet_vehicles v LEFT JOIN employees e ON v.assigned_employee_id = e.id WHERE v.id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    
    public function addVehicle(array $data): int {
        $stmt = $this->db->prepare("INSERT INTO fleet_vehicles (name, plate_number, imei, vehicle_type, make, model, year, color, assigned_employee_id, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['name'],
            $data['plate_number'] ?? null,
            $data['imei'] ?? null,
            $data['vehicle_type'] ?? 'car',
            $data['make'] ?? null,
            $data['model'] ?? null,
            !empty($data['year']) ? (int)$data['year'] : null,
            $data['color'] ?? null,
            !empty($data['assigned_employee_id']) ? (int)$data['assigned_employee_id'] : null,
            $data['status'] ?? 'active',
            $data['notes'] ?? null
        ]);
        $vehicleId = (int)$this->db->lastInsertId();
        
        if (!empty($data['assigned_employee_id'])) {
            $this->logAssignment($vehicleId, (int)$data['assigned_employee_id'], 'Initial assignment');
        }
        
        return $vehicleId;
    }
    
    public function updateVehicle(int $id, array $data): bool {
        $vehicle = $this->getVehicle($id);
        if (!$vehicle) return false;
        
        $oldEmployeeId = $vehicle['assigned_employee_id'];
        $newEmployeeId = !empty($data['assigned_employee_id']) ? (int)$data['assigned_employee_id'] : null;
        
        $stmt = $this->db->prepare("UPDATE fleet_vehicles SET name = ?, plate_number = ?, imei = ?, vehicle_type = ?, make = ?, model = ?, year = ?, color = ?, assigned_employee_id = ?, status = ?, notes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $result = $stmt->execute([
            $data['name'],
            $data['plate_number'] ?? null,
            $data['imei'] ?? null,
            $data['vehicle_type'] ?? 'car',
            $data['make'] ?? null,
            $data['model'] ?? null,
            !empty($data['year']) ? (int)$data['year'] : null,
            $data['color'] ?? null,
            $newEmployeeId,
            $data['status'] ?? 'active',
            $data['notes'] ?? null,
            $id
        ]);
        
        if ($oldEmployeeId != $newEmployeeId) {
            if ($oldEmployeeId) {
                $this->db->prepare("UPDATE fleet_vehicle_assignments SET returned_at = CURRENT_TIMESTAMP WHERE vehicle_id = ? AND employee_id = ? AND returned_at IS NULL")->execute([$id, $oldEmployeeId]);
            }
            if ($newEmployeeId) {
                $this->logAssignment($id, $newEmployeeId, 'Reassignment');
            }
        }
        
        return $result;
    }
    
    public function deleteVehicle(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM fleet_vehicles WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    private function logAssignment(int $vehicleId, int $employeeId, string $notes = ''): void {
        $stmt = $this->db->prepare("INSERT INTO fleet_vehicle_assignments (vehicle_id, employee_id, notes) VALUES (?, ?, ?)");
        $stmt->execute([$vehicleId, $employeeId, $notes]);
    }
    
    public function getAssignmentHistory(int $vehicleId): array {
        $stmt = $this->db->prepare("SELECT va.*, e.name as employee_name FROM fleet_vehicle_assignments va JOIN employees e ON va.employee_id = e.id WHERE va.vehicle_id = ? ORDER BY va.assigned_at DESC");
        $stmt->execute([$vehicleId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function trackVehicles(?array $vehicleIds = null): ?array {
        if ($vehicleIds) {
            $placeholders = implode(',', array_fill(0, count($vehicleIds), '?'));
            $stmt = $this->db->prepare("SELECT imei FROM fleet_vehicles WHERE id IN ($placeholders) AND imei IS NOT NULL AND imei != ''");
            $stmt->execute($vehicleIds);
        } else {
            $stmt = $this->db->query("SELECT imei FROM fleet_vehicles WHERE imei IS NOT NULL AND imei != '' AND status = 'active'");
        }
        $imeis = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        
        if (empty($imeis)) return ['record' => [], 'code' => 0];
        
        $result = $this->protrack->getTrack($imeis);
        
        if ($result && ($result['code'] ?? -1) === 0 && !empty($result['record'])) {
            foreach ($result['record'] as $track) {
                $this->updateVehicleLocation($track);
            }
        }
        
        return $result;
    }
    
    private function updateVehicleLocation(array $track): void {
        $stmt = $this->db->prepare("UPDATE fleet_vehicles SET 
            last_latitude = ?, last_longitude = ?, last_speed = ?, 
            last_acc_status = ?, last_battery = ?, last_mileage = ?,
            last_update = TO_TIMESTAMP(?) 
            WHERE imei = ?");
        $stmt->execute([
            $track['latitude'] ?? null,
            $track['longitude'] ?? null,
            $track['speed'] ?? 0,
            $track['accstatus'] ?? -1,
            $track['battery'] ?? -1,
            $track['mileage'] ?? 0,
            $track['hearttime'] ?? time(),
            $track['imei']
        ]);
    }
    
    public function getPlayback(int $vehicleId, int $beginTime, int $endTime): ?array {
        $vehicle = $this->getVehicle($vehicleId);
        if (!$vehicle || empty($vehicle['imei'])) return null;
        return $this->protrack->getPlayback($vehicle['imei'], $beginTime, $endTime);
    }
    
    public function sendCommand(int $vehicleId, string $command, int $sentBy): ?array {
        $vehicle = $this->getVehicle($vehicleId);
        if (!$vehicle || empty($vehicle['imei'])) return null;
        
        $result = $this->protrack->sendCommand($vehicle['imei'], $command);
        
        $commandId = $result['record']['commandid'] ?? null;
        $stmt = $this->db->prepare("INSERT INTO fleet_command_log (vehicle_id, imei, command, command_id, status, sent_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $vehicleId, $vehicle['imei'], $command, $commandId,
            ($result && ($result['code'] ?? -1) === 0) ? 'sent' : 'failed',
            $sentBy
        ]);
        
        return $result;
    }
    
    public function queryCommandStatus(string $commandId): ?array {
        $result = $this->protrack->queryCommand($commandId);
        
        if ($result && ($result['code'] ?? -1) === 0) {
            $record = $result['record'] ?? [];
            if (($record['commandstatus'] ?? 0) === 1) {
                $stmt = $this->db->prepare("UPDATE fleet_command_log SET status = 'responded', response = ?, responded_at = CURRENT_TIMESTAMP WHERE command_id = ?");
                $stmt->execute([$record['response'] ?? '', $commandId]);
            }
        }
        
        return $result;
    }
    
    public function getCommandLog(int $vehicleId, int $limit = 20): array {
        $stmt = $this->db->prepare("SELECT cl.*, e.name as sent_by_name FROM fleet_command_log cl LEFT JOIN employees e ON cl.sent_by = e.id WHERE cl.vehicle_id = ? ORDER BY cl.sent_at DESC LIMIT ?");
        $stmt->execute([$vehicleId, $limit]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getGeofences(): array {
        $stmt = $this->db->query("SELECT * FROM fleet_geofences ORDER BY name");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function addGeofence(array $data): int {
        $stmt = $this->db->prepare("INSERT INTO fleet_geofences (name, geofence_type, latitude, longitude, radius, alarm_type) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['name'],
            $data['geofence_type'] ?? 'circle',
            $data['latitude'] ?? 0,
            $data['longitude'] ?? 0,
            $data['radius'] ?? 500,
            $data['alarm_type'] ?? 2
        ]);
        return (int)$this->db->lastInsertId();
    }
    
    public function deleteGeofence(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM fleet_geofences WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    public function getAlarms(array $filters = []): array {
        $sql = "SELECT a.*, v.name as vehicle_name, v.plate_number 
                FROM fleet_alarms a 
                LEFT JOIN fleet_vehicles v ON a.vehicle_id = v.id 
                WHERE 1=1";
        $params = [];
        
        if (!empty($filters['vehicle_id'])) {
            $sql .= " AND a.vehicle_id = ?";
            $params[] = $filters['vehicle_id'];
        }
        if (isset($filters['acknowledged'])) {
            $sql .= " AND a.acknowledged = ?";
            $params[] = $filters['acknowledged'];
        }
        
        $sql .= " ORDER BY a.alarm_time DESC LIMIT 100";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function acknowledgeAlarm(int $alarmId, int $employeeId): bool {
        $stmt = $this->db->prepare("UPDATE fleet_alarms SET acknowledged = TRUE, acknowledged_by = ?, acknowledged_at = CURRENT_TIMESTAMP WHERE id = ?");
        return $stmt->execute([$employeeId, $alarmId]);
    }
    
    public function fetchAndStoreAlarms(int $vehicleId): ?array {
        $vehicle = $this->getVehicle($vehicleId);
        if (!$vehicle || empty($vehicle['imei'])) return null;
        
        $endTime = time();
        $beginTime = $endTime - 86400;
        
        $result = $this->protrack->getAlarms($vehicle['imei'], $beginTime, $endTime);
        
        if ($result && ($result['code'] ?? -1) === 0 && !empty($result['record'])) {
            $alarmTypes = $this->getAlarmTypeNames();
            foreach ($result['record'] as $alarm) {
                $alarmTime = date('Y-m-d H:i:s', $alarm['alarmtime'] ?? time());
                $exists = $this->db->prepare("SELECT id FROM fleet_alarms WHERE imei = ? AND alarm_time = ? AND alarm_type = ?");
                $exists->execute([$vehicle['imei'], $alarmTime, $alarm['alarmtype'] ?? 0]);
                if (!$exists->fetch()) {
                    $stmt = $this->db->prepare("INSERT INTO fleet_alarms (vehicle_id, imei, alarm_type, alarm_name, latitude, longitude, speed, alarm_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $vehicleId,
                        $vehicle['imei'],
                        $alarm['alarmtype'] ?? 0,
                        $alarmTypes[$alarm['alarmtype'] ?? 0] ?? 'Unknown',
                        $alarm['longitude'] ?? null,
                        $alarm['latitude'] ?? null,
                        $alarm['speed'] ?? 0,
                        $alarmTime
                    ]);
                }
            }
        }
        
        return $result;
    }
    
    private function getAlarmTypeNames(): array {
        return [
            1 => 'SOS', 2 => 'Low Battery', 3 => 'Power Off',
            4 => 'Vibration', 5 => 'Geofence In', 6 => 'Geofence Out',
            7 => 'Overspeed', 8 => 'Illegal Movement', 9 => 'Illegal Ignition',
            10 => 'Power Disconnect', 11 => 'GPS Antenna Disconnect',
            12 => 'ACC ON', 13 => 'ACC OFF', 14 => 'Door Open',
            15 => 'Door Close', 18 => 'Temperature Alarm', 19 => 'Fuel Alarm',
            20 => 'Harsh Braking', 21 => 'Harsh Acceleration', 22 => 'Sharp Turn',
            23 => 'Idle Alarm', 24 => 'Towing', 25 => 'Fatigue Driving'
        ];
    }
    
    public function syncDevicesFromProtrack(): array {
        $result = $this->protrack->getDeviceList();
        
        if (!$result || ($result['code'] ?? -1) !== 0) {
            return ['success' => false, 'error' => 'Failed to fetch device list from Protrack'];
        }
        
        $devices = $result['record'] ?? [];
        $synced = 0;
        $added = 0;
        
        foreach ($devices as $device) {
            $imei = $device['imei'] ?? '';
            if (empty($imei)) continue;
            
            $existing = $this->db->prepare("SELECT id FROM fleet_vehicles WHERE imei = ?");
            $existing->execute([$imei]);
            
            if ($existing->fetch()) {
                $synced++;
            } else {
                $name = $device['name'] ?? $device['imei'] ?? 'Vehicle';
                $this->db->prepare("INSERT INTO fleet_vehicles (name, imei, vehicle_type, status) VALUES (?, ?, 'car', 'active')")->execute([$name, $imei]);
                $added++;
                $synced++;
            }
        }
        
        return ['success' => true, 'synced' => $synced, 'added' => $added, 'total' => count($devices)];
    }
    
    public function getEmployees(): array {
        $stmt = $this->db->query("SELECT id, name FROM employees WHERE status = 'active' ORDER BY name");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
