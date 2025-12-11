<?php

namespace App;

class Leave {
    private \PDO $db;
    
    public function __construct(\PDO $db) {
        $this->db = $db;
    }
    
    public function getLeaveTypes(): array {
        $stmt = $this->db->query("SELECT * FROM leave_types WHERE is_active = TRUE ORDER BY name");
        return $stmt->fetchAll();
    }
    
    public function getAllLeaveTypes(): array {
        $stmt = $this->db->query("SELECT * FROM leave_types ORDER BY name");
        return $stmt->fetchAll();
    }
    
    public function getLeaveType(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM leave_types WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    public function createLeaveType(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO leave_types (name, code, days_per_year, is_paid, requires_approval, is_active)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['name'],
            strtoupper($data['code']),
            $data['days_per_year'] ?? 0,
            !empty($data['is_paid']),
            !empty($data['requires_approval']),
            !empty($data['is_active'])
        ]);
        return (int)$this->db->lastInsertId();
    }
    
    public function updateLeaveType(int $id, array $data): bool {
        $stmt = $this->db->prepare("
            UPDATE leave_types SET 
                name = ?, code = ?, days_per_year = ?, is_paid = ?, 
                requires_approval = ?, is_active = ?
            WHERE id = ?
        ");
        return $stmt->execute([
            $data['name'],
            strtoupper($data['code']),
            $data['days_per_year'] ?? 0,
            !empty($data['is_paid']),
            !empty($data['requires_approval']),
            !empty($data['is_active']),
            $id
        ]);
    }
    
    public function getRequests(array $filters = []): array {
        $sql = "SELECT lr.*, 
                    lr.days_requested as total_days,
                    e.name as employee_name, e.employee_id as employee_code,
                    lt.name as leave_type_name, lt.code as leave_type_code,
                    u.name as approved_by_name
                FROM leave_requests lr
                LEFT JOIN employees e ON lr.employee_id = e.id
                LEFT JOIN leave_types lt ON lr.leave_type_id = lt.id
                LEFT JOIN users u ON lr.approved_by = u.id
                WHERE 1=1";
        $params = [];
        
        if (!empty($filters['status'])) {
            $sql .= " AND lr.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['employee_id'])) {
            $sql .= " AND lr.employee_id = ?";
            $params[] = $filters['employee_id'];
        }
        
        
        if (!empty($filters['leave_type_id'])) {
            $sql .= " AND lr.leave_type_id = ?";
            $params[] = $filters['leave_type_id'];
        }
        
        if (!empty($filters['start_date'])) {
            $sql .= " AND lr.start_date >= ?";
            $params[] = $filters['start_date'];
        }
        
        if (!empty($filters['end_date'])) {
            $sql .= " AND lr.end_date <= ?";
            $params[] = $filters['end_date'];
        }
        
        $sql .= " ORDER BY lr.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function getRequest(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT lr.*, 
                lr.days_requested as total_days,
                e.name as employee_name, e.employee_id as employee_code,
                lt.name as leave_type_name, lt.code as leave_type_code,
                u.name as approved_by_name
            FROM leave_requests lr
            LEFT JOIN employees e ON lr.employee_id = e.id
            LEFT JOIN leave_types lt ON lr.leave_type_id = lt.id
            LEFT JOIN users u ON lr.approved_by = u.id
            WHERE lr.id = ?
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    public function validateLeaveRequest(int $employeeId, int $leaveTypeId, float $requestedDays): array {
        $year = (int)date('Y');
        $balance = $this->getOrCreateBalance($employeeId, $leaveTypeId, $year);
        $leaveType = $this->getLeaveType($leaveTypeId);
        
        $availableDays = ($balance['entitled_days'] ?? 0) + ($balance['accrued_days'] ?? 0) 
                        + ($balance['carried_over_days'] ?? 0) + ($balance['adjusted_days'] ?? 0)
                        - ($balance['used_days'] ?? 0) - ($balance['pending_days'] ?? 0);
        
        $configuredLimit = $leaveType['days_per_year'] ?? 0;
        $maxAnnualDays = $configuredLimit > 0 ? $configuredLimit : 21;
        
        $usedThisYear = $balance['used_days'] ?? 0;
        $pendingThisYear = $balance['pending_days'] ?? 0;
        $totalIfApproved = $usedThisYear + $pendingThisYear + $requestedDays;
        
        if ($totalIfApproved > $maxAnnualDays && !($leaveType['allow_negative_balance'] ?? false)) {
            return [
                'valid' => false,
                'error' => "This request would exceed your annual leave limit of {$maxAnnualDays} days. You have used {$usedThisYear} days with {$pendingThisYear} days pending."
            ];
        }
        
        if ($requestedDays > $availableDays && !($leaveType['allow_negative_balance'] ?? false)) {
            return [
                'valid' => false,
                'error' => "Insufficient leave balance. Available: {$availableDays} days, Requested: {$requestedDays} days."
            ];
        }
        
        return ['valid' => true, 'available_days' => $availableDays];
    }
    
    public function createRequest(array $data): int {
        $totalDays = $this->calculateLeaveDays($data['start_date'], $data['end_date'], !empty($data['is_half_day']));
        
        $validation = $this->validateLeaveRequest($data['employee_id'], $data['leave_type_id'], $totalDays);
        if (!$validation['valid']) {
            throw new \Exception($validation['error']);
        }
        
        $stmt = $this->db->prepare("
            INSERT INTO leave_requests (employee_id, leave_type_id, start_date, end_date, days_requested, reason)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['employee_id'],
            $data['leave_type_id'],
            $data['start_date'],
            $data['end_date'],
            $totalDays,
            $data['reason'] ?? null
        ]);
        
        $requestId = (int)$this->db->lastInsertId();
        
        $this->updatePendingDays($data['employee_id'], $data['leave_type_id'], $totalDays);
        
        return $requestId;
    }
    
    public function approve(int $id, int $approvedBy): bool {
        $this->db->beginTransaction();
        try {
            $request = $this->getRequest($id);
            if (!$request || $request['status'] !== 'pending') {
                throw new \Exception('Request not found or already processed');
            }
            
            $stmt = $this->db->prepare("
                UPDATE leave_requests 
                SET status = 'approved', approved_by = ?, approved_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([$approvedBy, $id]);
            
            $this->updatePendingDays($request['employee_id'], $request['leave_type_id'], -$request['total_days']);
            $this->updateUsedDays($request['employee_id'], $request['leave_type_id'], $request['total_days']);
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    public function reject(int $id, int $rejectedBy, ?string $reason = null): bool {
        $this->db->beginTransaction();
        try {
            $request = $this->getRequest($id);
            if (!$request || $request['status'] !== 'pending') {
                throw new \Exception('Request not found or already processed');
            }
            
            $stmt = $this->db->prepare("
                UPDATE leave_requests 
                SET status = 'rejected', approved_by = ?, approved_at = CURRENT_TIMESTAMP, rejection_reason = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([$rejectedBy, $reason, $id]);
            
            $this->updatePendingDays($request['employee_id'], $request['leave_type_id'], -$request['total_days']);
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    public function cancel(int $id): bool {
        $this->db->beginTransaction();
        try {
            $request = $this->getRequest($id);
            if (!$request) {
                throw new \Exception('Request not found');
            }
            
            if ($request['status'] === 'pending') {
                $this->updatePendingDays($request['employee_id'], $request['leave_type_id'], -$request['total_days']);
            } elseif ($request['status'] === 'approved') {
                $this->updateUsedDays($request['employee_id'], $request['leave_type_id'], -$request['total_days']);
            }
            
            $stmt = $this->db->prepare("
                UPDATE leave_requests SET status = 'cancelled', updated_at = CURRENT_TIMESTAMP WHERE id = ?
            ");
            $stmt->execute([$id]);
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    public function getEmployeeBalance(int $employeeId, ?int $year = null): array {
        $year = $year ?? (int)date('Y');
        
        $stmt = $this->db->prepare("
            SELECT lb.*, lt.name as leave_type_name, lt.code as leave_type_code
            FROM leave_balances lb
            JOIN leave_types lt ON lb.leave_type_id = lt.id
            WHERE lb.employee_id = ? AND lb.year = ?
            ORDER BY lt.name
        ");
        $stmt->execute([$employeeId, $year]);
        return $stmt->fetchAll();
    }
    
    public function getOrCreateBalance(int $employeeId, int $leaveTypeId, ?int $year = null): array {
        $year = $year ?? (int)date('Y');
        
        $stmt = $this->db->prepare("
            SELECT * FROM leave_balances WHERE employee_id = ? AND leave_type_id = ? AND year = ?
        ");
        $stmt->execute([$employeeId, $leaveTypeId, $year]);
        $balance = $stmt->fetch();
        
        if (!$balance) {
            $leaveType = $this->getLeaveType($leaveTypeId);
            $entitledDays = $leaveType['days_per_year'] ?? 0;
            
            $carryOver = 0;
            if ($leaveType['max_carryover_days'] > 0) {
                $carryOver = $this->getCarryOverDays($employeeId, $leaveTypeId, $year - 1, $leaveType['max_carryover_days']);
            }
            
            $stmt = $this->db->prepare("
                INSERT INTO leave_balances (employee_id, leave_type_id, year, entitled_days, carried_over_days)
                VALUES (?, ?, ?, ?, ?)
                RETURNING *
            ");
            $stmt->execute([$employeeId, $leaveTypeId, $year, $entitledDays, $carryOver]);
            $balance = $stmt->fetch();
        }
        
        return $balance;
    }
    
    private function getCarryOverDays(int $employeeId, int $leaveTypeId, int $year, float $maxCarryover): float {
        $stmt = $this->db->prepare("
            SELECT available_days FROM leave_balances 
            WHERE employee_id = ? AND leave_type_id = ? AND year = ?
        ");
        $stmt->execute([$employeeId, $leaveTypeId, $year]);
        $result = $stmt->fetch();
        
        if ($result && $result['available_days'] > 0) {
            return min($result['available_days'], $maxCarryover);
        }
        
        return 0;
    }
    
    public function runMonthlyAccrual(): array {
        $results = ['processed' => 0, 'errors' => []];
        $year = (int)date('Y');
        $currentMonth = (int)date('n');
        
        $stmt = $this->db->query("
            SELECT e.id as employee_id, lt.id as leave_type_id, lt.days_per_year
            FROM employees e
            CROSS JOIN leave_types lt
            WHERE e.is_active = TRUE AND lt.is_active = TRUE AND lt.accrual_type = 'monthly' AND lt.days_per_year > 0
        ");
        $employees = $stmt->fetchAll();
        
        foreach ($employees as $row) {
            try {
                $balance = $this->getOrCreateBalance($row['employee_id'], $row['leave_type_id'], $year);
                
                $lastAccrualMonth = $balance['last_accrual_date'] 
                    ? (int)date('n', strtotime($balance['last_accrual_date'])) 
                    : 0;
                
                if ($lastAccrualMonth < $currentMonth) {
                    $monthlyAccrual = $row['days_per_year'] / 12;
                    $monthsToAccrue = $currentMonth - $lastAccrualMonth;
                    $totalAccrual = $monthlyAccrual * $monthsToAccrue;
                    
                    $stmt = $this->db->prepare("
                        UPDATE leave_balances 
                        SET accrued_days = accrued_days + ?, last_accrual_date = CURRENT_DATE, updated_at = CURRENT_TIMESTAMP
                        WHERE id = ?
                    ");
                    $stmt->execute([$totalAccrual, $balance['id']]);
                    $results['processed']++;
                }
            } catch (Exception $e) {
                $results['errors'][] = "Employee {$row['employee_id']}: " . $e->getMessage();
            }
        }
        
        return $results;
    }
    
    public function adjustBalance(int $employeeId, int $leaveTypeId, float $days, string $reason, int $adjustedBy): bool {
        $year = (int)date('Y');
        $balance = $this->getOrCreateBalance($employeeId, $leaveTypeId, $year);
        
        $stmt = $this->db->prepare("
            UPDATE leave_balances 
            SET adjusted_days = adjusted_days + ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        return $stmt->execute([$days, $balance['id']]);
    }
    
    private function updatePendingDays(int $employeeId, int $leaveTypeId, float $days): void {
        $year = (int)date('Y');
        $balance = $this->getOrCreateBalance($employeeId, $leaveTypeId, $year);
        
        $stmt = $this->db->prepare("
            UPDATE leave_balances 
            SET pending_days = pending_days + ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([$days, $balance['id']]);
    }
    
    private function updateUsedDays(int $employeeId, int $leaveTypeId, float $days): void {
        $year = (int)date('Y');
        $balance = $this->getOrCreateBalance($employeeId, $leaveTypeId, $year);
        
        $stmt = $this->db->prepare("
            UPDATE leave_balances 
            SET used_days = used_days + ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([$days, $balance['id']]);
    }
    
    public function calculateLeaveDays(string $startDate, string $endDate, bool $isHalfDay = false): float {
        if ($isHalfDay) {
            return 0.5;
        }
        
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        $days = 0;
        
        while ($start <= $end) {
            $dayOfWeek = (int)$start->format('N');
            if ($dayOfWeek < 6) {
                if (!$this->isPublicHoliday($start->format('Y-m-d'))) {
                    $days++;
                }
            }
            $start->modify('+1 day');
        }
        
        return (float)$days;
    }
    
    private function isPublicHoliday(string $date): bool {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM leave_calendar 
            WHERE date = ? AND is_public_holiday = TRUE AND branch_id IS NULL
        ");
        $stmt->execute([$date]);
        return $stmt->fetchColumn() > 0;
    }
    
    public function getPublicHolidays(?int $year = null): array {
        $year = $year ?? (int)date('Y');
        $stmt = $this->db->prepare("
            SELECT * FROM leave_calendar 
            WHERE EXTRACT(YEAR FROM date) = ? AND is_public_holiday = TRUE
            ORDER BY date
        ");
        $stmt->execute([$year]);
        return $stmt->fetchAll();
    }
    
    public function addPublicHoliday(string $date, string $name, ?int $branchId = null): int {
        $stmt = $this->db->prepare("
            INSERT INTO leave_calendar (date, name, is_public_holiday, branch_id)
            VALUES (?, ?, TRUE, ?)
            ON CONFLICT (date, branch_id) DO UPDATE SET name = EXCLUDED.name
            RETURNING id
        ");
        $stmt->execute([$date, $name, $branchId]);
        return (int)$stmt->fetchColumn();
    }
    
    public function deleteHoliday(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM leave_calendar WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    public function getStatistics(): array {
        $year = (int)date('Y');
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) FILTER (WHERE status = 'pending') as pending_requests,
                COUNT(*) FILTER (WHERE status = 'approved') as approved_requests,
                COUNT(*) FILTER (WHERE status = 'rejected') as rejected_requests,
                COALESCE(SUM(days_requested) FILTER (WHERE status = 'approved'), 0) as total_days_taken
            FROM leave_requests
            WHERE EXTRACT(YEAR FROM start_date) = ?
        ");
        $stmt->execute([$year]);
        return $stmt->fetch();
    }
    
    public function getEmployeeRequests(int $employeeId): array {
        $stmt = $this->db->prepare("
            SELECT lr.*, lt.name as leave_type_name, lt.code as leave_type_code
            FROM leave_requests lr
            JOIN leave_types lt ON lr.leave_type_id = lt.id
            WHERE lr.employee_id = ?
            ORDER BY lr.created_at DESC
        ");
        $stmt->execute([$employeeId]);
        return $stmt->fetchAll();
    }
}
