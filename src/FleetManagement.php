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
        $stmt = $this->db->prepare("INSERT INTO fleet_vehicles (name, plate_number, imei, vehicle_type, make, model, year, color, assigned_employee_id, status, notes, fuel_rate) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
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
            $data['notes'] ?? null,
            isset($data['fuel_rate']) ? (float)$data['fuel_rate'] : 0
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
        
        $stmt = $this->db->prepare("UPDATE fleet_vehicles SET name = ?, plate_number = ?, imei = ?, vehicle_type = ?, make = ?, model = ?, year = ?, color = ?, assigned_employee_id = ?, status = ?, notes = ?, fuel_rate = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
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
            isset($data['fuel_rate']) ? (float)$data['fuel_rate'] : 0,
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
        try {
            $stmt = $this->db->prepare("UPDATE fleet_vehicles SET 
                last_latitude = ?, last_longitude = ?, last_speed = ?, 
                last_acc_status = ?, last_battery = ?, last_mileage = ?,
                last_data_status = ?, last_update = TO_TIMESTAMP(?) 
                WHERE imei = ?");
            $stmt->execute([
                $track['latitude'] ?? null,
                $track['longitude'] ?? null,
                $track['speed'] ?? 0,
                $track['accstatus'] ?? -1,
                $track['battery'] ?? -1,
                $track['mileage'] ?? 0,
                $track['datastatus'] ?? 0,
                $track['hearttime'] ?? time(),
                $track['imei']
            ]);
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), 'last_data_status')) {
                $this->db->exec("ALTER TABLE fleet_vehicles ADD COLUMN IF NOT EXISTS last_data_status INTEGER DEFAULT 0");
                $this->updateVehicleLocation($track);
            }
        }
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
        if (!$this->protrack->isConfigured()) {
            return ['success' => false, 'error' => 'Protrack not configured. Please set account and password in Settings.'];
        }
        
        $result = $this->protrack->getDeviceList();
        
        if (!$result) {
            $lastError = $this->protrack->getLastError();
            return ['success' => false, 'error' => 'Failed to connect to Protrack: ' . ($lastError ?? 'No response from API')];
        }
        if (($result['code'] ?? -1) !== 0) {
            $apiError = $result['message'] ?? 'Unknown API error';
            $apiCode = $result['code'] ?? 'null';
            return ['success' => false, 'error' => "Protrack API error (code $apiCode): $apiError"];
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
                $this->db->prepare("UPDATE fleet_vehicles SET name = COALESCE(NULLIF(?, ''), name), plate_number = COALESCE(NULLIF(?, ''), plate_number) WHERE imei = ? AND (name = imei OR name IS NULL OR name = '')")->execute([
                    $device['devicename'] ?? $device['name'] ?? '',
                    $device['platenumber'] ?? '',
                    $imei
                ]);
                $synced++;
            } else {
                $name = $device['devicename'] ?? $device['name'] ?? $device['imei'] ?? 'Vehicle';
                $plateNumber = $device['platenumber'] ?? null;
                $this->db->prepare("INSERT INTO fleet_vehicles (name, plate_number, imei, vehicle_type, status) VALUES (?, ?, ?, 'car', 'active')")->execute([$name, $plateNumber, $imei]);
                $added++;
                $synced++;
            }
        }
        
        return ['success' => true, 'synced' => $synced, 'added' => $added, 'total' => count($devices)];
    }
    
    public function getEmployees(): array {
        $stmt = $this->db->query("SELECT id, name FROM employees WHERE employment_status = 'active' ORDER BY name");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getDailyReport(string $date, ?int $vehicleId = null): array {
        $startOfDay = strtotime($date . ' 00:00:00');
        $endOfDay = strtotime($date . ' 23:59:59');

        $sql = "SELECT v.id, v.name, v.plate_number, v.imei, v.vehicle_type, v.make, v.model, 
                       v.fuel_rate, v.last_speed, v.last_acc_status, v.last_mileage, v.status,
                       e.name as assigned_to
                FROM fleet_vehicles v
                LEFT JOIN employees e ON v.assigned_employee_id = e.id
                WHERE v.imei IS NOT NULL AND v.imei != ''";
        $params = [];
        if ($vehicleId) {
            $sql .= " AND v.id = ?";
            $params[] = $vehicleId;
        }
        $sql .= " ORDER BY v.name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $vehicles = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($vehicles)) return [];

        $imeis = array_column($vehicles, 'imei');
        $imeiToVehicle = [];
        foreach ($vehicles as &$v) {
            $imeiToVehicle[$v['imei']] = &$v;
            $v['daily_mileage'] = 0;
            $v['fuel_consumed'] = 0;
            $v['alarm_count'] = 0;
            $v['command_count'] = 0;
        }
        unset($v);

        try {
            $mileageResult = $this->protrack->getBatchMileage($imeis, $startOfDay, $endOfDay);
            if ($mileageResult && ($mileageResult['code'] ?? -1) === 0 && !empty($mileageResult['record'])) {
                foreach ($mileageResult['record'] as $rec) {
                    if (isset($imeiToVehicle[$rec['imei']])) {
                        $rawMileage = (float)($rec['mileage'] ?? 0);
                        $km = $rawMileage > 1000 ? round($rawMileage / 1000, 2) : round($rawMileage, 2);
                        $imeiToVehicle[$rec['imei']]['daily_mileage'] = $km;
                        $fuelRate = (float)($imeiToVehicle[$rec['imei']]['fuel_rate'] ?? 0);
                        if ($fuelRate > 0 && $km > 0) {
                            $imeiToVehicle[$rec['imei']]['fuel_consumed'] = round($km * $fuelRate / 100, 2);
                        }
                    }
                }
            }
        } catch (\Exception $e) {}

        $placeholders = implode(',', array_fill(0, count($vehicles), '?'));
        $vids = array_column($vehicles, 'id');

        $alarmStmt = $this->db->prepare("SELECT vehicle_id, COUNT(*) as cnt FROM fleet_alarms 
            WHERE vehicle_id IN ($placeholders) AND alarm_time >= ? AND alarm_time <= ?
            GROUP BY vehicle_id");
        $alarmStmt->execute(array_merge($vids, [$date . ' 00:00:00', $date . ' 23:59:59']));
        foreach ($alarmStmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            foreach ($vehicles as &$v) {
                if ($v['id'] == $row['vehicle_id']) {
                    $v['alarm_count'] = (int)$row['cnt'];
                    break;
                }
            }
            unset($v);
        }

        $cmdStmt = $this->db->prepare("SELECT vehicle_id, COUNT(*) as cnt FROM fleet_command_log 
            WHERE vehicle_id IN ($placeholders) AND sent_at::date = ?
            GROUP BY vehicle_id");
        $cmdStmt->execute(array_merge($vids, [$date]));
        foreach ($cmdStmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            foreach ($vehicles as &$v) {
                if ($v['id'] == $row['vehicle_id']) {
                    $v['command_count'] = (int)$row['cnt'];
                    break;
                }
            }
            unset($v);
        }

        $this->storeDailyMileage($vehicles, $date);

        return $vehicles;
    }

    private function storeDailyMileage(array $vehicles, string $date): void {
        $stmt = $this->db->prepare("INSERT INTO fleet_mileage_reports (vehicle_id, imei, report_date, mileage)
            VALUES (?, ?, ?, ?)
            ON CONFLICT (vehicle_id, report_date) DO UPDATE SET mileage = EXCLUDED.mileage");
        foreach ($vehicles as $v) {
            if ($v['daily_mileage'] > 0) {
                $stmt->execute([$v['id'], $v['imei'], $date, $v['daily_mileage']]);
            }
        }
    }

    public function getFuelReport(string $startDate, string $endDate, ?int $vehicleId = null): array {
        $sql = "SELECT v.id, v.name, v.plate_number, v.vehicle_type, v.make, v.model, v.fuel_rate,
                       e.name as assigned_to,
                       COALESCE(SUM(mr.mileage), 0) as total_mileage,
                       COUNT(mr.id) as days_reported
                FROM fleet_vehicles v
                LEFT JOIN employees e ON v.assigned_employee_id = e.id
                LEFT JOIN fleet_mileage_reports mr ON v.id = mr.vehicle_id AND mr.report_date BETWEEN ? AND ?
                WHERE v.imei IS NOT NULL AND v.imei != ''";
        $params = [$startDate, $endDate];
        if ($vehicleId) {
            $sql .= " AND v.id = ?";
            $params[] = $vehicleId;
        }
        $sql .= " GROUP BY v.id, v.name, v.plate_number, v.vehicle_type, v.make, v.model, v.fuel_rate, e.name
                  ORDER BY v.name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($results as &$r) {
            $fuelRate = (float)($r['fuel_rate'] ?? 0);
            $totalKm = (float)$r['total_mileage'];
            $r['fuel_consumed'] = ($fuelRate > 0 && $totalKm > 0) ? round($totalKm * $fuelRate / 100, 2) : 0;
        }
        unset($r);

        return $results;
    }

    public function getSwapHistory(string $startDate, string $endDate, ?int $vehicleId = null): array {
        $sql = "SELECT va.id, va.vehicle_id, va.employee_id, va.assigned_at, va.returned_at, va.notes,
                       v.name as vehicle_name, v.plate_number, v.vehicle_type,
                       e.name as employee_name
                FROM fleet_vehicle_assignments va
                JOIN fleet_vehicles v ON va.vehicle_id = v.id
                JOIN employees e ON va.employee_id = e.id
                WHERE va.assigned_at::date <= ? AND (va.returned_at IS NULL OR va.returned_at::date >= ?)";
        $params = [$endDate, $startDate];
        if ($vehicleId) {
            $sql .= " AND va.vehicle_id = ?";
            $params[] = $vehicleId;
        }
        $sql .= " ORDER BY va.assigned_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getMileageTrend(int $vehicleId, int $days = 30): array {
        $stmt = $this->db->prepare("SELECT report_date, mileage FROM fleet_mileage_reports 
            WHERE vehicle_id = ? AND report_date >= CURRENT_DATE - INTERVAL '1 day' * ?
            ORDER BY report_date ASC");
        $stmt->execute([$vehicleId, $days]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getVehicleDailyLog(int $vehicleId, string $startDate, string $endDate): array {
        $vstmt = $this->db->prepare("SELECT v.*, e.name as assigned_to FROM fleet_vehicles v 
            LEFT JOIN employees e ON v.assigned_employee_id = e.id WHERE v.id = ?");
        $vstmt->execute([$vehicleId]);
        $vehicle = $vstmt->fetch(\PDO::FETCH_ASSOC);
        if (!$vehicle || empty($vehicle['imei'])) return [];

        $imei = $vehicle['imei'];
        $days = [];
        $current = new \DateTime($startDate);
        $end = new \DateTime($endDate);

        $maxDays = 31;
        $diff = $current->diff($end)->days;
        if ($diff > $maxDays) {
            $current = (clone $end)->modify("-{$maxDays} days");
        }

        while ($current <= $end) {
            $dateStr = $current->format('Y-m-d');
            $dayStart = strtotime($dateStr . ' 00:00:00');
            $dayEnd = strtotime($dateStr . ' 23:59:59');

            $dayData = [
                'date' => $dateStr,
                'day_name' => $current->format('D'),
                'mileage_km' => 0,
                'fuel_consumed' => 0,
                'max_speed' => 0,
                'alarm_count' => 0,
                'first_move' => null,
                'last_move' => null,
                'driving_points' => 0,
                'total_points' => 0,
            ];

            try {
                $mileageResult = $this->protrack->getBatchMileage([$imei], $dayStart, $dayEnd);
                if ($mileageResult && ($mileageResult['code'] ?? -1) === 0 && !empty($mileageResult['record'])) {
                    foreach ($mileageResult['record'] as $rec) {
                        if ($rec['imei'] === $imei) {
                            $raw = (float)($rec['mileage'] ?? 0);
                            $dayData['mileage_km'] = $raw > 1000 ? round($raw / 1000, 2) : round($raw, 2);
                        }
                    }
                }
            } catch (\Exception $e) {}

            if ($dayData['mileage_km'] > 0) {
                try {
                    $playback = $this->protrack->getPlayback($imei, $dayStart, $dayEnd);
                    $points = [];
                    if ($playback) {
                        if (!empty($playback['data']) && is_string($playback['data'])) {
                            $parts = explode(';', trim($playback['data'], ';'));
                            foreach ($parts as $part) {
                                $fields = explode(',', $part);
                                if (count($fields) >= 7) {
                                    $points[] = [
                                        'speed' => (float)$fields[4],
                                        'time' => (int)$fields[6],
                                    ];
                                }
                            }
                        } elseif (!empty($playback['data']['points'])) {
                            foreach ($playback['data']['points'] as $pt) {
                                $points[] = [
                                    'speed' => (float)($pt['speed'] ?? 0),
                                    'time' => (int)($pt['time'] ?? $pt['systemtime'] ?? 0),
                                ];
                            }
                        }
                    }

                    if (!empty($points)) {
                        $dayData['total_points'] = count($points);
                        $maxSpeed = 0;
                        $firstMove = null;
                        $lastMove = null;
                        $drivingPoints = 0;
                        foreach ($points as $pt) {
                            if ($pt['speed'] > $maxSpeed) $maxSpeed = $pt['speed'];
                            if ($pt['speed'] > 0) {
                                $drivingPoints++;
                                if ($firstMove === null) $firstMove = $pt['time'];
                                $lastMove = $pt['time'];
                            }
                        }
                        $dayData['max_speed'] = round($maxSpeed, 1);
                        $dayData['driving_points'] = $drivingPoints;
                        if ($firstMove) $dayData['first_move'] = date('H:i', $firstMove);
                        if ($lastMove) $dayData['last_move'] = date('H:i', $lastMove);
                    }
                } catch (\Exception $e) {}
            }

            $fuelRate = (float)($vehicle['fuel_rate'] ?? 0);
            if ($fuelRate > 0 && $dayData['mileage_km'] > 0) {
                $dayData['fuel_consumed'] = round($dayData['mileage_km'] * $fuelRate / 100, 2);
            }

            $alarmStmt = $this->db->prepare("SELECT COUNT(*) FROM fleet_alarms 
                WHERE vehicle_id = ? AND alarm_time >= ? AND alarm_time <= ?");
            $alarmStmt->execute([$vehicleId, $dateStr . ' 00:00:00', $dateStr . ' 23:59:59']);
            $dayData['alarm_count'] = (int)$alarmStmt->fetchColumn();

            $this->storeSingleDayMileage($vehicleId, $imei, $dateStr, $dayData['mileage_km']);

            $days[] = $dayData;
            $current->modify('+1 day');
        }

        return ['vehicle' => $vehicle, 'days' => $days];
    }

    private function storeSingleDayMileage(int $vehicleId, string $imei, string $date, float $mileage): void {
        if ($mileage <= 0) return;
        $stmt = $this->db->prepare("INSERT INTO fleet_mileage_reports (vehicle_id, imei, report_date, mileage)
            VALUES (?, ?, ?, ?)
            ON CONFLICT (vehicle_id, report_date) DO UPDATE SET mileage = EXCLUDED.mileage");
        $stmt->execute([$vehicleId, $imei, $date, $mileage]);
    }

    public function updateVehicleFuelRate(int $vehicleId, float $fuelRate): bool {
        $stmt = $this->db->prepare("UPDATE fleet_vehicles SET fuel_rate = ? WHERE id = ?");
        return $stmt->execute([$fuelRate, $vehicleId]);
    }
}
