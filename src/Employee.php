<?php

namespace App;

class Employee {
    private \PDO $db;

    public function __construct(?\PDO $db = null) {
        $this->db = $db ?? \Database::getConnection();
    }
    
    public function getEmployees(): array {
        $stmt = $this->db->query("
            SELECT e.*, d.name as department_name,
                   SPLIT_PART(e.name, ' ', 1) as first_name,
                   CASE 
                       WHEN POSITION(' ' IN e.name) > 0 
                       THEN SUBSTRING(e.name FROM POSITION(' ' IN e.name) + 1)
                       ELSE ''
                   END as last_name
            FROM employees e
            LEFT JOIN departments d ON e.department_id = d.id
            WHERE e.employment_status = 'active'
            ORDER BY e.name ASC
        ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function generateEmployeeId(): string {
        return 'EMP-' . date('Y') . '-' . str_pad(random_int(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    public function createUserAccount(array $data): int {
        $roleId = $data['role_id'] ?? null;
        $roleName = $data['role'] ?? 'technician';
        
        if ($roleId) {
            $roleStmt = $this->db->prepare("SELECT name FROM roles WHERE id = ?");
            $roleStmt->execute([$roleId]);
            $roleName = $roleStmt->fetchColumn() ?: 'technician';
        }
        
        $stmt = $this->db->prepare("
            INSERT INTO users (name, email, phone, password_hash, role, role_id)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['name'],
            $data['email'],
            $data['phone'],
            password_hash($data['password'], PASSWORD_DEFAULT),
            $roleName,
            $roleId
        ]);
        $userId = (int) $this->db->lastInsertId();
        
        // Auto-create salesperson record if role is salesperson
        if (in_array($roleName, ['salesperson', 'sales'])) {
            $this->createSalespersonRecord($userId, $data);
        }
        
        return $userId;
    }
    
    private function createSalespersonRecord(int $userId, array $data): void {
        // Check if salesperson record already exists
        $checkStmt = $this->db->prepare("SELECT id FROM salespersons WHERE user_id = ?");
        $checkStmt->execute([$userId]);
        if ($checkStmt->fetch()) {
            return; // Already exists
        }
        
        $stmt = $this->db->prepare("
            INSERT INTO salespersons (user_id, name, email, phone, commission_type, commission_value, is_active, created_at, updated_at)
            VALUES (?, ?, ?, ?, 'percentage', 10.00, true, NOW(), NOW())
        ");
        $stmt->execute([
            $userId,
            $data['name'],
            $data['email'],
            $data['phone'] ?? null
        ]);
    }
    
    public function updateUserRole(int $userId, int $roleId): bool {
        $roleStmt = $this->db->prepare("SELECT name FROM roles WHERE id = ?");
        $roleStmt->execute([$roleId]);
        $roleName = $roleStmt->fetchColumn();
        
        if (!$roleName) return false;
        
        $stmt = $this->db->prepare("UPDATE users SET role = ?, role_id = ? WHERE id = ?");
        $result = $stmt->execute([$roleName, $roleId, $userId]);
        
        // Auto-create salesperson record if role changed to salesperson
        if ($result && in_array($roleName, ['salesperson', 'sales'])) {
            $userStmt = $this->db->prepare("SELECT name, email, phone FROM users WHERE id = ?");
            $userStmt->execute([$userId]);
            $user = $userStmt->fetch(\PDO::FETCH_ASSOC);
            if ($user) {
                $this->createSalespersonRecord($userId, $user);
            }
        }
        
        return $result;
    }
    
    public function changeUserPassword(int $employeeId, string $newPassword): array {
        $stmt = $this->db->prepare("SELECT user_id, name FROM employees WHERE id = ?");
        $stmt->execute([$employeeId]);
        $employee = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$employee) {
            return ['success' => false, 'error' => 'Employee not found'];
        }
        
        if (!$employee['user_id']) {
            return ['success' => false, 'error' => 'Employee does not have a linked user account'];
        }
        
        if (strlen($newPassword) < 6) {
            return ['success' => false, 'error' => 'Password must be at least 6 characters'];
        }
        
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $updateStmt = $this->db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $result = $updateStmt->execute([$passwordHash, $employee['user_id']]);
        
        if ($result) {
            return ['success' => true, 'message' => 'Password changed successfully for ' . $employee['name']];
        }
        
        return ['success' => false, 'error' => 'Failed to update password'];
    }
    
    public function changeUserPasswordByUserId(int $userId, string $newPassword): array {
        if (strlen($newPassword) < 6) {
            return ['success' => false, 'error' => 'Password must be at least 6 characters'];
        }
        
        $stmt = $this->db->prepare("SELECT id, name FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$user) {
            return ['success' => false, 'error' => 'User not found'];
        }
        
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $updateStmt = $this->db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $result = $updateStmt->execute([$passwordHash, $userId]);
        
        if ($result) {
            return ['success' => true, 'message' => 'Password changed successfully for ' . $user['name']];
        }
        
        return ['success' => false, 'error' => 'Failed to update password'];
    }
    
    public function getUserByEmployeeId(int $employeeId): ?array {
        $stmt = $this->db->prepare("
            SELECT u.*, r.display_name as role_display_name 
            FROM users u 
            LEFT JOIN roles r ON u.role_id = r.id
            JOIN employees e ON e.user_id = u.id 
            WHERE e.id = ?
        ");
        $stmt->execute([$employeeId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function create(array $data): int {
        $userId = null;
        
        if (isset($data['user_id']) && $data['user_id'] === 'create_new') {
            if (!empty($data['new_user_email']) && !empty($data['new_user_password'])) {
                $userId = $this->createUserAccount([
                    'name' => $data['name'],
                    'email' => $data['new_user_email'],
                    'phone' => $data['phone'],
                    'password' => $data['new_user_password'],
                    'role_id' => $data['new_user_role_id'] ?? null
                ]);
            }
        } elseif (!empty($data['user_id'])) {
            $userId = (int)$data['user_id'];
            if (!empty($data['new_user_role_id'])) {
                $this->updateUserRole($userId, (int)$data['new_user_role_id']);
            }
        }
        
        $stmt = $this->db->prepare("
            INSERT INTO employees (employee_id, user_id, name, email, phone, office_phone, department_id, position, salary, hire_date, employment_status, emergency_contact, emergency_phone, address, notes, passport_photo, id_number, passport_number, next_of_kin_name, next_of_kin_phone, next_of_kin_relationship, date_of_birth, gender, nationality, marital_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['employee_id'] ?? $this->generateEmployeeId(),
            $userId,
            $data['name'],
            $data['email'],
            $data['phone'],
            $data['office_phone'] ?? null,
            $data['department_id'] ?: null,
            $data['position'],
            $data['salary'] ?: null,
            $data['hire_date'] ?: null,
            $data['employment_status'] ?? 'active',
            $data['emergency_contact'] ?? null,
            $data['emergency_phone'] ?? null,
            $data['address'] ?? null,
            $data['notes'] ?? null,
            $data['passport_photo'] ?? null,
            $data['id_number'] ?? null,
            $data['passport_number'] ?? null,
            $data['next_of_kin_name'] ?? null,
            $data['next_of_kin_phone'] ?? null,
            $data['next_of_kin_relationship'] ?? null,
            $data['date_of_birth'] ?: null,
            $data['gender'] ?? null,
            $data['nationality'] ?? null,
            $data['marital_status'] ?? null
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool {
        $fields = [];
        $values = [];
        
        if (isset($data['user_id']) && $data['user_id'] === 'create_new') {
            if (!empty($data['new_user_email']) && !empty($data['new_user_password'])) {
                $userId = $this->createUserAccount([
                    'name' => $data['name'],
                    'email' => $data['new_user_email'],
                    'phone' => $data['phone'],
                    'password' => $data['new_user_password'],
                    'role_id' => $data['new_user_role_id'] ?? null
                ]);
                $data['user_id'] = $userId;
            }
        } elseif (!empty($data['user_id']) && !empty($data['new_user_role_id'])) {
            $this->updateUserRole((int)$data['user_id'], (int)$data['new_user_role_id']);
        }
        
        $allowedFields = ['name', 'email', 'phone', 'office_phone', 'department_id', 'position', 'salary', 'hire_date', 'employment_status', 'emergency_contact', 'emergency_phone', 'address', 'notes', 'user_id', 'passport_photo', 'id_number', 'passport_number', 'next_of_kin_name', 'next_of_kin_phone', 'next_of_kin_relationship', 'date_of_birth', 'gender', 'nationality', 'marital_status'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                if ($field === 'user_id' && $data[$field] === 'create_new') {
                    continue;
                }
                $fields[] = "$field = ?";
                $value = $data[$field] === '' ? null : $data[$field];
                if ($field === 'user_id' && $value !== null) {
                    $value = (int)$value;
                }
                $values[] = $value;
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $fields[] = "updated_at = CURRENT_TIMESTAMP";
        $values[] = $id;
        
        $stmt = $this->db->prepare("UPDATE employees SET " . implode(', ', $fields) . " WHERE id = ?");
        return $stmt->execute($values);
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM employees WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function find(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT e.*, d.name as department_name
            FROM employees e
            LEFT JOIN departments d ON e.department_id = d.id
            WHERE e.id = ?
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function getAll(string $search = '', ?int $departmentId = null, int $limit = 50, int $offset = 0): array {
        $sql = "
            SELECT e.*, d.name as department_name
            FROM employees e
            LEFT JOIN departments d ON e.department_id = d.id
            WHERE 1=1
        ";
        $params = [];
        
        if ($search) {
            $sql .= " AND (e.name ILIKE ? OR e.employee_id ILIKE ? OR e.email ILIKE ? OR e.phone ILIKE ? OR e.position ILIKE ?)";
            $searchTerm = "%$search%";
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        }
        
        if ($departmentId) {
            $sql .= " AND e.department_id = ?";
            $params[] = $departmentId;
        }
        
        $sql .= " ORDER BY e.name ASC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function count(string $search = '', ?int $departmentId = null): int {
        $sql = "SELECT COUNT(*) FROM employees e WHERE 1=1";
        $params = [];
        
        if ($search) {
            $sql .= " AND (e.name ILIKE ? OR e.employee_id ILIKE ? OR e.email ILIKE ? OR e.phone ILIKE ?)";
            $searchTerm = "%$search%";
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        }
        
        if ($departmentId) {
            $sql .= " AND e.department_id = ?";
            $params[] = $departmentId;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function getStats(): array {
        $stmt = $this->db->query("
            SELECT 
                COUNT(*) as total,
                COUNT(*) FILTER (WHERE employment_status = 'active') as active,
                COUNT(*) FILTER (WHERE employment_status = 'on_leave') as on_leave,
                COUNT(*) FILTER (WHERE employment_status = 'terminated') as terminated,
                COUNT(DISTINCT department_id) as departments
            FROM employees
        ");
        return $stmt->fetch();
    }

    public function getEmploymentStatuses(): array {
        return [
            'active' => 'Active',
            'probation' => 'Probation',
            'on_leave' => 'On Leave',
            'suspended' => 'Suspended',
            'terminated' => 'Terminated'
        ];
    }

    public function createDepartment(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO departments (name, description, manager_id)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([
            $data['name'],
            $data['description'] ?? null,
            $data['manager_id'] ?: null
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function updateDepartment(int $id, array $data): bool {
        $stmt = $this->db->prepare("
            UPDATE departments SET name = ?, description = ?, manager_id = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        return $stmt->execute([
            $data['name'],
            $data['description'] ?? null,
            $data['manager_id'] ?: null,
            $id
        ]);
    }

    public function deleteDepartment(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM departments WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function getDepartment(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT d.*, e.name as manager_name
            FROM departments d
            LEFT JOIN employees e ON d.manager_id = e.id
            WHERE d.id = ?
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function getAllDepartments(): array {
        $stmt = $this->db->query("
            SELECT d.*, e.name as manager_name,
                   (SELECT COUNT(*) FROM employees WHERE department_id = d.id) as employee_count
            FROM departments d
            LEFT JOIN employees e ON d.manager_id = e.id
            ORDER BY d.name ASC
        ");
        return $stmt->fetchAll();
    }

    public function linkToUser(int $employeeId, int $userId): bool {
        $stmt = $this->db->prepare("UPDATE employees SET user_id = ? WHERE id = ?");
        return $stmt->execute([$userId, $employeeId]);
    }

    public function getByUserId(int $userId): ?array {
        $stmt = $this->db->prepare("SELECT * FROM employees WHERE user_id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function getTechnicians(): array {
        $stmt = $this->db->query("
            SELECT e.*, u.id as user_id, u.email as user_email
            FROM employees e
            LEFT JOIN users u ON e.user_id = u.id
            WHERE e.employment_status = 'active'
            ORDER BY e.name ASC
        ");
        return $stmt->fetchAll();
    }

    public function recordAttendance(array $data): int {
        $clockIn = $data['clock_in'] ?? null;
        $clockOut = $data['clock_out'] ?? null;
        $hoursWorked = null;
        $overtimeHours = 0;

        if ($clockIn && $clockOut) {
            $start = new \DateTime($clockIn);
            $end = new \DateTime($clockOut);
            $diff = $start->diff($end);
            $hoursWorked = $diff->h + ($diff->i / 60);
            if ($hoursWorked > 8) {
                $overtimeHours = $hoursWorked - 8;
            }
        }

        $stmt = $this->db->prepare("
            INSERT INTO attendance (employee_id, date, clock_in, clock_out, status, hours_worked, overtime_hours, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON CONFLICT (employee_id, date) DO UPDATE SET
                clock_in = EXCLUDED.clock_in,
                clock_out = EXCLUDED.clock_out,
                status = EXCLUDED.status,
                hours_worked = EXCLUDED.hours_worked,
                overtime_hours = EXCLUDED.overtime_hours,
                notes = EXCLUDED.notes,
                updated_at = CURRENT_TIMESTAMP
        ");
        
        $stmt->execute([
            $data['employee_id'],
            $data['date'],
            $clockIn,
            $clockOut,
            $data['status'] ?? 'present',
            $hoursWorked,
            $overtimeHours,
            $data['notes'] ?? null
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function updateAttendance(int $id, array $data): bool {
        $clockIn = $data['clock_in'] ?? null;
        $clockOut = $data['clock_out'] ?? null;
        $hoursWorked = null;
        $overtimeHours = 0;

        if ($clockIn && $clockOut) {
            $start = new \DateTime($clockIn);
            $end = new \DateTime($clockOut);
            $diff = $start->diff($end);
            $hoursWorked = $diff->h + ($diff->i / 60);
            if ($hoursWorked > 8) {
                $overtimeHours = $hoursWorked - 8;
            }
        }

        $stmt = $this->db->prepare("
            UPDATE attendance SET 
                clock_in = ?, clock_out = ?, status = ?, hours_worked = ?, overtime_hours = ?, notes = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        return $stmt->execute([$clockIn, $clockOut, $data['status'], $hoursWorked, $overtimeHours, $data['notes'] ?? null, $id]);
    }

    public function deleteAttendance(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM attendance WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function getAttendance(int $employeeId, ?string $startDate = null, ?string $endDate = null): array {
        $sql = "SELECT a.*, e.name as employee_name FROM attendance a 
                JOIN employees e ON a.employee_id = e.id 
                WHERE a.employee_id = ?";
        $params = [$employeeId];

        if ($startDate) {
            $sql .= " AND a.date >= ?";
            $params[] = $startDate;
        }
        if ($endDate) {
            $sql .= " AND a.date <= ?";
            $params[] = $endDate;
        }

        $sql .= " ORDER BY a.date DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getAllAttendance(string $date): array {
        $stmt = $this->db->prepare("
            SELECT a.*, e.name as employee_name, e.employee_id as emp_code, d.name as department_name
            FROM attendance a
            JOIN employees e ON a.employee_id = e.id
            LEFT JOIN departments d ON e.department_id = d.id
            WHERE a.date = ?
            ORDER BY e.name ASC
        ");
        $stmt->execute([$date]);
        return $stmt->fetchAll();
    }

    public function getTodayAttendance(): array {
        return $this->getAllAttendance(date('Y-m-d'));
    }

    public function getAttendanceStatuses(): array {
        return [
            'present' => 'Present',
            'absent' => 'Absent',
            'late' => 'Late',
            'half_day' => 'Half Day',
            'leave' => 'On Leave',
            'holiday' => 'Holiday',
            'work_from_home' => 'Work From Home'
        ];
    }

    public function getAttendanceStats(string $month): array {
        $startDate = $month . '-01';
        $endDate = date('Y-m-t', strtotime($startDate));

        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) FILTER (WHERE status = 'present') as present,
                COUNT(*) FILTER (WHERE status = 'absent') as absent,
                COUNT(*) FILTER (WHERE status = 'late') as late,
                COUNT(*) FILTER (WHERE status = 'leave') as on_leave,
                COUNT(*) FILTER (WHERE status = 'work_from_home') as wfh,
                SUM(COALESCE(hours_worked, 0)) as total_hours,
                SUM(COALESCE(overtime_hours, 0)) as total_overtime
            FROM attendance
            WHERE date >= ? AND date <= ?
        ");
        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetch();
    }

    public function createPayroll(array $data): int {
        $baseSalary = (float)($data['base_salary'] ?? 0);
        $overtimePay = (float)($data['overtime_pay'] ?? 0);
        $bonuses = (float)($data['bonuses'] ?? 0);
        $deductions = (float)($data['deductions'] ?? 0);
        $tax = (float)($data['tax'] ?? 0);
        
        $advanceDeduction = $this->calculateAdvanceDeduction($data['employee_id']);
        $deductions += $advanceDeduction;
        
        $netPay = $baseSalary + $overtimePay + $bonuses - $deductions - $tax;

        $stmt = $this->db->prepare("
            INSERT INTO payroll (employee_id, pay_period_start, pay_period_end, base_salary, overtime_pay, bonuses, deductions, tax, net_pay, status, payment_date, payment_method, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['employee_id'],
            $data['pay_period_start'],
            $data['pay_period_end'],
            $baseSalary,
            $overtimePay,
            $bonuses,
            $deductions,
            $tax,
            $netPay,
            $data['status'] ?? 'pending',
            $data['payment_date'] ?: null,
            $data['payment_method'] ?? null,
            $data['notes'] ?? null
        ]);

        $payrollId = (int) $this->db->lastInsertId();
        
        if ($advanceDeduction > 0) {
            $this->applyAdvanceDeductions($data['employee_id'], $payrollId, $advanceDeduction);
        }

        return $payrollId;
    }
    
    private function calculateAdvanceDeduction(int $employeeId): float {
        $stmt = $this->db->prepare("
            SELECT sa.id, sa.outstanding_balance, sa.installments,
                   COALESCE(sa.approved_amount, sa.requested_amount) as total_amount
            FROM salary_advances sa
            WHERE sa.employee_id = ? 
              AND sa.status IN ('disbursed', 'repaying')
              AND sa.outstanding_balance > 0
            ORDER BY sa.created_at ASC
        ");
        $stmt->execute([$employeeId]);
        $advances = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $totalDeduction = 0;
        foreach ($advances as $advance) {
            $installmentAmount = $advance['installments'] > 0 
                ? $advance['total_amount'] / $advance['installments'] 
                : $advance['outstanding_balance'];
            $deductAmount = min($installmentAmount, $advance['outstanding_balance']);
            $totalDeduction += $deductAmount;
        }
        
        return $totalDeduction;
    }
    
    private function applyAdvanceDeductions(int $employeeId, int $payrollId, float $totalDeduction): void {
        $stmt = $this->db->prepare("
            SELECT sa.id, sa.outstanding_balance, sa.installments,
                   COALESCE(sa.approved_amount, sa.requested_amount) as total_amount
            FROM salary_advances sa
            WHERE sa.employee_id = ? 
              AND sa.status IN ('disbursed', 'repaying')
              AND sa.outstanding_balance > 0
            ORDER BY sa.created_at ASC
        ");
        $stmt->execute([$employeeId]);
        $advances = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $remainingDeduction = $totalDeduction;
        
        foreach ($advances as $advance) {
            if ($remainingDeduction <= 0) break;
            
            $installmentAmount = $advance['installments'] > 0 
                ? $advance['total_amount'] / $advance['installments'] 
                : $advance['outstanding_balance'];
            $deductAmount = min($installmentAmount, $advance['outstanding_balance'], $remainingDeduction);
            
            $repaymentStmt = $this->db->prepare("
                INSERT INTO salary_advance_repayments (advance_id, payroll_id, amount, repayment_date, notes)
                VALUES (?, ?, ?, CURRENT_DATE, 'Automatic payroll deduction')
            ");
            $repaymentStmt->execute([$advance['id'], $payrollId, $deductAmount]);
            
            $newBalance = $advance['outstanding_balance'] - $deductAmount;
            $newStatus = $newBalance <= 0 ? 'completed' : 'repaying';
            
            $updateStmt = $this->db->prepare("
                UPDATE salary_advances 
                SET outstanding_balance = ?, status = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $updateStmt->execute([max(0, $newBalance), $newStatus, $advance['id']]);
            
            $remainingDeduction -= $deductAmount;
        }
    }

    public function updatePayroll(int $id, array $data): bool {
        $baseSalary = (float)($data['base_salary'] ?? 0);
        $overtimePay = (float)($data['overtime_pay'] ?? 0);
        $bonuses = (float)($data['bonuses'] ?? 0);
        $deductions = (float)($data['deductions'] ?? 0);
        $tax = (float)($data['tax'] ?? 0);
        $netPay = $baseSalary + $overtimePay + $bonuses - $deductions - $tax;

        $stmt = $this->db->prepare("
            UPDATE payroll SET 
                base_salary = ?, overtime_pay = ?, bonuses = ?, deductions = ?, tax = ?, net_pay = ?,
                status = ?, payment_date = ?, payment_method = ?, notes = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        return $stmt->execute([
            $baseSalary, $overtimePay, $bonuses, $deductions, $tax, $netPay,
            $data['status'], $data['payment_date'] ?: null, $data['payment_method'] ?? null, $data['notes'] ?? null, $id
        ]);
    }

    public function deletePayroll(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM payroll WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function getPayroll(int $employeeId): array {
        $stmt = $this->db->prepare("
            SELECT p.*, e.name as employee_name
            FROM payroll p
            JOIN employees e ON p.employee_id = e.id
            WHERE p.employee_id = ?
            ORDER BY p.pay_period_end DESC
        ");
        $stmt->execute([$employeeId]);
        return $stmt->fetchAll();
    }

    public function getAllPayroll(?string $status = null, ?string $month = null): array {
        $sql = "SELECT p.*, e.name as employee_name, e.employee_id as emp_code, d.name as department_name
                FROM payroll p
                JOIN employees e ON p.employee_id = e.id
                LEFT JOIN departments d ON e.department_id = d.id
                WHERE 1=1";
        $params = [];

        if ($status) {
            $sql .= " AND p.status = ?";
            $params[] = $status;
        }

        if ($month) {
            $sql .= " AND p.pay_period_start <= ? AND p.pay_period_end >= ?";
            $monthStart = $month . '-01';
            $monthEnd = date('Y-m-t', strtotime($monthStart));
            $params[] = $monthEnd;
            $params[] = $monthStart;
        }

        $sql .= " ORDER BY p.pay_period_end DESC, e.name ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getPayrollStatuses(): array {
        return [
            'pending' => 'Pending',
            'processing' => 'Processing',
            'paid' => 'Paid',
            'cancelled' => 'Cancelled'
        ];
    }

    public function getPaymentMethods(): array {
        return [
            'bank_transfer' => 'Bank Transfer',
            'check' => 'Check',
            'cash' => 'Cash',
            'mobile_money' => 'Mobile Money'
        ];
    }

    public function getPayrollStats(?string $month = null): array {
        $sql = "SELECT 
                    COUNT(*) as total_records,
                    COUNT(*) FILTER (WHERE status = 'pending') as pending,
                    COUNT(*) FILTER (WHERE status = 'paid') as paid,
                    SUM(CASE WHEN status = 'paid' THEN net_pay ELSE 0 END) as total_paid,
                    SUM(CASE WHEN status = 'pending' THEN net_pay ELSE 0 END) as total_pending
                FROM payroll";
        $params = [];

        if ($month) {
            $sql .= " WHERE pay_period_start >= ? AND pay_period_end <= ?";
            $monthStart = $month . '-01';
            $monthEnd = date('Y-m-t', strtotime($monthStart));
            $params = [$monthStart, $monthEnd];
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }

    public function generateBulkPayroll(string $payPeriodStart, string $payPeriodEnd, array $options = []): array {
        $stmt = $this->db->prepare("
            SELECT e.id, e.name, e.salary, e.employee_id as emp_code
            FROM employees e
            WHERE e.employment_status = 'active' AND e.salary > 0
            ORDER BY e.name
        ");
        $stmt->execute();
        $employees = $stmt->fetchAll();

        $results = [
            'success' => 0,
            'skipped' => 0,
            'errors' => [],
            'payroll_ids' => []
        ];

        $payPeriodMonth = date('Y-m', strtotime($payPeriodStart));

        foreach ($employees as $emp) {
            $existingStmt = $this->db->prepare("
                SELECT id FROM payroll 
                WHERE employee_id = ? 
                AND pay_period_start = ? 
                AND pay_period_end = ?
            ");
            $existingStmt->execute([$emp['id'], $payPeriodStart, $payPeriodEnd]);
            if ($existingStmt->fetch()) {
                $results['skipped']++;
                continue;
            }

            try {
                $baseSalary = (float)$emp['salary'];
                $overtimePay = 0;
                $bonuses = 0;
                $deductions = 0;
                $tax = 0;
                $netPay = $baseSalary + $overtimePay + $bonuses - $deductions - $tax;

                $stmt = $this->db->prepare("
                    INSERT INTO payroll (employee_id, pay_period_start, pay_period_end, base_salary, overtime_pay, bonuses, deductions, tax, net_pay, status, payment_date, payment_method, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $emp['id'],
                    $payPeriodStart,
                    $payPeriodEnd,
                    $baseSalary,
                    $overtimePay,
                    $bonuses,
                    $deductions,
                    $tax,
                    $netPay,
                    'pending',
                    null,
                    null,
                    'Bulk generated'
                ]);

                $payrollId = (int)$this->db->lastInsertId();
                $results['payroll_ids'][$emp['id']] = $payrollId;
                $results['success']++;

            } catch (\Exception $e) {
                $results['errors'][] = $emp['name'] . ': ' . $e->getMessage();
            }
        }

        return $results;
    }

    public function createPerformanceReview(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO performance_reviews (
                employee_id, reviewer_id, review_period_start, review_period_end,
                overall_rating, productivity_rating, quality_rating, teamwork_rating, communication_rating,
                goals_achieved, strengths, areas_for_improvement, goals_next_period, comments, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['employee_id'],
            $data['reviewer_id'] ?: null,
            $data['review_period_start'],
            $data['review_period_end'],
            $data['overall_rating'] ?: null,
            $data['productivity_rating'] ?: null,
            $data['quality_rating'] ?: null,
            $data['teamwork_rating'] ?: null,
            $data['communication_rating'] ?: null,
            $data['goals_achieved'] ?? null,
            $data['strengths'] ?? null,
            $data['areas_for_improvement'] ?? null,
            $data['goals_next_period'] ?? null,
            $data['comments'] ?? null,
            $data['status'] ?? 'draft'
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function updatePerformanceReview(int $id, array $data): bool {
        $stmt = $this->db->prepare("
            UPDATE performance_reviews SET 
                reviewer_id = ?, overall_rating = ?, productivity_rating = ?, quality_rating = ?,
                teamwork_rating = ?, communication_rating = ?, goals_achieved = ?, strengths = ?,
                areas_for_improvement = ?, goals_next_period = ?, comments = ?, status = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        return $stmt->execute([
            $data['reviewer_id'] ?: null,
            $data['overall_rating'] ?: null,
            $data['productivity_rating'] ?: null,
            $data['quality_rating'] ?: null,
            $data['teamwork_rating'] ?: null,
            $data['communication_rating'] ?: null,
            $data['goals_achieved'] ?? null,
            $data['strengths'] ?? null,
            $data['areas_for_improvement'] ?? null,
            $data['goals_next_period'] ?? null,
            $data['comments'] ?? null,
            $data['status'],
            $id
        ]);
    }

    public function deletePerformanceReview(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM performance_reviews WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function getPerformanceReview(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT pr.*, e.name as employee_name, r.name as reviewer_name
            FROM performance_reviews pr
            JOIN employees e ON pr.employee_id = e.id
            LEFT JOIN employees r ON pr.reviewer_id = r.id
            WHERE pr.id = ?
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function getEmployeePerformanceReviews(int $employeeId): array {
        $stmt = $this->db->prepare("
            SELECT pr.*, e.name as employee_name, r.name as reviewer_name
            FROM performance_reviews pr
            JOIN employees e ON pr.employee_id = e.id
            LEFT JOIN employees r ON pr.reviewer_id = r.id
            WHERE pr.employee_id = ?
            ORDER BY pr.review_period_end DESC
        ");
        $stmt->execute([$employeeId]);
        return $stmt->fetchAll();
    }

    public function getAllPerformanceReviews(?string $status = null): array {
        $sql = "SELECT pr.*, e.name as employee_name, e.employee_id as emp_code, r.name as reviewer_name, d.name as department_name
                FROM performance_reviews pr
                JOIN employees e ON pr.employee_id = e.id
                LEFT JOIN employees r ON pr.reviewer_id = r.id
                LEFT JOIN departments d ON e.department_id = d.id
                WHERE 1=1";
        $params = [];

        if ($status) {
            $sql .= " AND pr.status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY pr.review_period_end DESC, e.name ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getPerformanceStatuses(): array {
        return [
            'draft' => 'Draft',
            'pending_review' => 'Pending Review',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
            'acknowledged' => 'Acknowledged'
        ];
    }

    public function getPerformanceStats(): array {
        $stmt = $this->db->query("
            SELECT 
                COUNT(*) as total_reviews,
                COUNT(*) FILTER (WHERE status = 'completed' OR status = 'acknowledged') as completed,
                COUNT(*) FILTER (WHERE status = 'draft' OR status = 'pending_review' OR status = 'in_progress') as pending,
                AVG(overall_rating) FILTER (WHERE overall_rating IS NOT NULL) as avg_rating
            FROM performance_reviews
        ");
        return $stmt->fetch();
    }

    public function getBranches(int $employeeId): array {
        $stmt = $this->db->prepare("
            SELECT b.*, eb.is_primary, eb.assigned_at
            FROM branches b
            JOIN employee_branches eb ON b.id = eb.branch_id
            WHERE eb.employee_id = ?
            ORDER BY eb.is_primary DESC, b.name
        ");
        $stmt->execute([$employeeId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function attachToBranch(int $employeeId, int $branchId, bool $isPrimary = false, ?int $assignedBy = null): bool {
        $stmt = $this->db->prepare("
            INSERT INTO employee_branches (employee_id, branch_id, is_primary, assigned_by)
            VALUES (?, ?, ?, ?)
            ON CONFLICT (employee_id, branch_id) DO UPDATE SET
                is_primary = EXCLUDED.is_primary,
                assigned_at = CURRENT_TIMESTAMP
        ");
        return $stmt->execute([$employeeId, $branchId, $isPrimary ? 1 : 0, $assignedBy]);
    }

    public function detachFromBranch(int $employeeId, int $branchId): bool {
        $stmt = $this->db->prepare("DELETE FROM employee_branches WHERE employee_id = ? AND branch_id = ?");
        return $stmt->execute([$employeeId, $branchId]);
    }

    public function setPrimaryBranch(int $employeeId, int $branchId): bool {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("UPDATE employee_branches SET is_primary = false WHERE employee_id = ?");
            $stmt->execute([$employeeId]);
            
            $stmt = $this->db->prepare("UPDATE employee_branches SET is_primary = true WHERE employee_id = ? AND branch_id = ?");
            $stmt->execute([$employeeId, $branchId]);
            
            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    public function updateBranches(int $employeeId, array $branchIds, ?int $primaryBranchId = null, ?int $assignedBy = null): bool {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("DELETE FROM employee_branches WHERE employee_id = ?");
            $stmt->execute([$employeeId]);
            
            foreach ($branchIds as $branchId) {
                $isPrimary = ($primaryBranchId && $branchId == $primaryBranchId);
                $this->attachToBranch($employeeId, $branchId, $isPrimary, $assignedBy);
            }
            
            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }
}
