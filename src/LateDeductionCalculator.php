<?php

namespace App;

class LateDeductionCalculator {
    private \PDO $db;
    
    public function __construct(\PDO $db) {
        $this->db = $db;
    }
    
    public function getLateRules(): array {
        $stmt = $this->db->query("
            SELECT lr.*, d.name as department_name
            FROM late_rules lr
            LEFT JOIN departments d ON lr.apply_to_department_id = d.id
            ORDER BY lr.is_default DESC, lr.name
        ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getActiveRules(): array {
        $stmt = $this->db->query("
            SELECT lr.*, d.name as department_name
            FROM late_rules lr
            LEFT JOIN departments d ON lr.apply_to_department_id = d.id
            WHERE lr.is_active = TRUE
            ORDER BY lr.is_default DESC, lr.name
        ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getRule(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM late_rules WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    
    public function getRuleForEmployee(int $employeeId): ?array {
        $stmt = $this->db->prepare("
            SELECT lr.* FROM late_rules lr
            LEFT JOIN employees e ON lr.apply_to_department_id = e.department_id
            WHERE lr.is_active = TRUE AND (e.id = ? OR lr.is_default = TRUE)
            ORDER BY (e.id IS NOT NULL) DESC, lr.is_default DESC
            LIMIT 1
        ");
        $stmt->execute([$employeeId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    
    public function addRule(array $data): int {
        $isDefault = !empty($data['is_default']) && $data['is_default'] !== '' && $data['is_default'] !== '0';
        $isActive = !isset($data['is_active']) || (!empty($data['is_active']) && $data['is_active'] !== '' && $data['is_active'] !== '0');
        
        if ($isDefault) {
            $this->db->exec("UPDATE late_rules SET is_default = FALSE WHERE is_default = TRUE");
        }
        
        $stmt = $this->db->prepare("
            INSERT INTO late_rules (name, work_start_time, grace_minutes, deduction_tiers, currency, apply_to_department_id, is_default, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $tiers = is_array($data['deduction_tiers']) ? json_encode($data['deduction_tiers']) : $data['deduction_tiers'];
        $deptId = !empty($data['apply_to_department_id']) ? (int)$data['apply_to_department_id'] : null;
        
        $stmt->execute([
            $data['name'],
            $data['work_start_time'] ?? '09:00',
            (int)($data['grace_minutes'] ?? 15),
            $tiers,
            $data['currency'] ?? 'KES',
            $deptId,
            $isDefault,
            $isActive
        ]);
        
        return (int)$this->db->lastInsertId();
    }
    
    public function updateRule(int $id, array $data): bool {
        $isDefault = !empty($data['is_default']) && $data['is_default'] !== '' && $data['is_default'] !== '0';
        
        if ($isDefault) {
            $this->db->exec("UPDATE late_rules SET is_default = FALSE WHERE is_default = TRUE AND id != " . intval($id));
        }
        
        $fields = [];
        $params = [];
        
        $simpleFields = ['name', 'work_start_time', 'grace_minutes', 'currency'];
        
        foreach ($simpleFields as $field) {
            if (isset($data[$field]) && $data[$field] !== '') {
                $fields[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }
        
        if (array_key_exists('apply_to_department_id', $data)) {
            $fields[] = "apply_to_department_id = ?";
            $params[] = !empty($data['apply_to_department_id']) ? (int)$data['apply_to_department_id'] : null;
        }
        
        if (array_key_exists('is_default', $data)) {
            $fields[] = "is_default = ?";
            $params[] = $isDefault;
        }
        
        if (array_key_exists('is_active', $data)) {
            $isActive = !empty($data['is_active']) && $data['is_active'] !== '' && $data['is_active'] !== '0';
            $fields[] = "is_active = ?";
            $params[] = $isActive;
        }
        
        if (isset($data['deduction_tiers'])) {
            $fields[] = "deduction_tiers = ?";
            $params[] = is_array($data['deduction_tiers']) ? json_encode($data['deduction_tiers']) : $data['deduction_tiers'];
        }
        
        if (empty($fields)) {
            return true;
        }
        
        $fields[] = "updated_at = CURRENT_TIMESTAMP";
        $params[] = $id;
        
        $sql = "UPDATE late_rules SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
    
    public function deleteRule(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM late_rules WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    public function calculateLateMinutes(int $employeeId, string $clockInTime): int {
        $rule = $this->getRuleForEmployee($employeeId);
        
        if (!$rule) {
            return 0;
        }
        
        $workStart = strtotime($rule['work_start_time']);
        $clockIn = strtotime($clockInTime);
        $graceEnd = $workStart + ($rule['grace_minutes'] * 60);
        
        if ($clockIn <= $graceEnd) {
            return 0;
        }
        
        return (int)round(($clockIn - $workStart) / 60);
    }
    
    public function calculateDeduction(int $lateMinutes, array $rule): float {
        if ($lateMinutes <= 0) {
            return 0;
        }
        
        $tiers = is_string($rule['deduction_tiers']) ? json_decode($rule['deduction_tiers'], true) : $rule['deduction_tiers'];
        
        if (empty($tiers)) {
            return 0;
        }
        
        usort($tiers, fn($a, $b) => ($a['min_minutes'] ?? 0) - ($b['min_minutes'] ?? 0));
        
        $deduction = 0;
        foreach ($tiers as $tier) {
            $minMin = $tier['min_minutes'] ?? 0;
            $maxMin = $tier['max_minutes'] ?? PHP_INT_MAX;
            $amount = $tier['amount'] ?? 0;
            
            if ($lateMinutes >= $minMin && $lateMinutes <= $maxMin) {
                $deduction = $amount;
                break;
            }
        }
        
        return (float)$deduction;
    }
    
    public function calculateMonthlyDeductions(int $employeeId, string $month): array {
        $startDate = date('Y-m-01', strtotime($month));
        $endDate = date('Y-m-t', strtotime($month));
        
        $stmt = $this->db->prepare("
            SELECT a.*, DATE(a.date) as attendance_date
            FROM attendance a
            WHERE a.employee_id = ? AND a.date BETWEEN ? AND ? AND a.late_minutes > 0
            ORDER BY a.date
        ");
        $stmt->execute([$employeeId, $startDate, $endDate]);
        $lateRecords = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $rule = $this->getRuleForEmployee($employeeId);
        
        $result = [
            'employee_id' => $employeeId,
            'month' => $month,
            'total_late_days' => count($lateRecords),
            'total_late_minutes' => 0,
            'total_deduction' => 0,
            'currency' => $rule['currency'] ?? 'KES',
            'breakdown' => []
        ];
        
        if (!$rule) {
            return $result;
        }
        
        foreach ($lateRecords as $record) {
            $lateMinutes = (int)$record['late_minutes'];
            $deduction = $this->calculateDeduction($lateMinutes, $rule);
            
            $result['total_late_minutes'] += $lateMinutes;
            $result['total_deduction'] += $deduction;
            $result['breakdown'][] = [
                'date' => $record['attendance_date'],
                'clock_in' => $record['clock_in'],
                'late_minutes' => $lateMinutes,
                'deduction' => $deduction
            ];
        }
        
        return $result;
    }
    
    public function getLateArrivalReport(string $month, ?int $departmentId = null): array {
        $startDate = date('Y-m-01', strtotime($month));
        $endDate = date('Y-m-t', strtotime($month));
        
        $sql = "
            SELECT 
                e.id as employee_id,
                e.name as employee_name,
                e.employee_id as employee_code,
                d.name as department_name,
                COUNT(CASE WHEN a.late_minutes > 0 THEN 1 END) as late_days,
                SUM(COALESCE(a.late_minutes, 0)) as total_late_minutes,
                AVG(CASE WHEN a.late_minutes > 0 THEN a.late_minutes END) as avg_late_minutes,
                COUNT(a.id) as total_attendance_days
            FROM employees e
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN attendance a ON e.id = a.employee_id AND a.date BETWEEN ? AND ?
            WHERE e.employment_status = 'active'
        ";
        $params = [$startDate, $endDate];
        
        if ($departmentId) {
            $sql .= " AND e.department_id = ?";
            $params[] = $departmentId;
        }
        
        $sql .= " GROUP BY e.id, e.name, e.employee_id, d.name ORDER BY total_late_minutes DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $employees = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        foreach ($employees as &$emp) {
            $monthlyData = $this->calculateMonthlyDeductions($emp['employee_id'], $month);
            $emp['total_deduction'] = $monthlyData['total_deduction'];
            $emp['currency'] = $monthlyData['currency'];
        }
        
        return $employees;
    }
    
    public function applyDeductionsToPayroll(int $payrollId, int $employeeId, string $month): bool {
        $deductions = $this->calculateMonthlyDeductions($employeeId, $month);
        
        if ($deductions['total_deduction'] <= 0) {
            return true;
        }
        
        $stmt = $this->db->prepare("
            DELETE FROM payroll_deductions 
            WHERE payroll_id = ? AND deduction_type = 'late_arrival'
        ");
        $stmt->execute([$payrollId]);
        
        $stmt = $this->db->prepare("
            INSERT INTO payroll_deductions (payroll_id, employee_id, deduction_type, description, amount, details)
            VALUES (?, ?, 'late_arrival', ?, ?, ?)
        ");
        
        $description = "Late arrival deduction ({$deductions['total_late_days']} days, {$deductions['total_late_minutes']} minutes)";
        
        $stmt->execute([
            $payrollId,
            $employeeId,
            $description,
            $deductions['total_deduction'],
            json_encode($deductions['breakdown'])
        ]);
        
        $updateStmt = $this->db->prepare("
            UPDATE payroll 
            SET deductions = deductions + ?,
                net_pay = net_pay - ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        
        return $updateStmt->execute([$deductions['total_deduction'], $deductions['total_deduction'], $payrollId]);
    }
    
    public function getPayrollDeductions(int $payrollId): array {
        $stmt = $this->db->prepare("
            SELECT * FROM payroll_deductions 
            WHERE payroll_id = ?
            ORDER BY deduction_type, created_at
        ");
        $stmt->execute([$payrollId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
