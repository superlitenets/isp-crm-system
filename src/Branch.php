<?php

namespace App;

class Branch {
    private \PDO $db;

    public function __construct() {
        $this->db = \Database::getConnection();
    }

    public function create(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO branches (name, code, address, phone, email, whatsapp_group, manager_id, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['name'],
            $data['code'] ?? null,
            $data['address'] ?? null,
            $data['phone'] ?? null,
            $data['email'] ?? null,
            $data['whatsapp_group'] ?? null,
            $data['manager_id'] ?? null,
            isset($data['is_active']) ? ($data['is_active'] ? 1 : 0) : 1
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool {
        $stmt = $this->db->prepare("
            UPDATE branches SET
                name = ?, code = ?, address = ?, phone = ?, email = ?,
                whatsapp_group = ?, manager_id = ?, is_active = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        return $stmt->execute([
            $data['name'],
            $data['code'] ?? null,
            $data['address'] ?? null,
            $data['phone'] ?? null,
            $data['email'] ?? null,
            $data['whatsapp_group'] ?? null,
            $data['manager_id'] ?? null,
            isset($data['is_active']) ? ($data['is_active'] ? 1 : 0) : 1,
            $id
        ]);
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM branches WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function get(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT b.*, u.name as manager_name
            FROM branches b
            LEFT JOIN users u ON b.manager_id = u.id
            WHERE b.id = ?
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function getAll(bool $activeOnly = false): array {
        $sql = "
            SELECT b.*, u.name as manager_name,
                (SELECT COUNT(*) FROM employee_branches eb WHERE eb.branch_id = b.id) as employee_count,
                (SELECT COUNT(*) FROM teams t WHERE t.branch_id = b.id) as team_count
            FROM branches b
            LEFT JOIN users u ON b.manager_id = u.id
        ";
        if ($activeOnly) {
            $sql .= " WHERE b.is_active = true";
        }
        $sql .= " ORDER BY b.name";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getActive(): array {
        return $this->getAll(true);
    }

    public function attachEmployee(int $branchId, int $employeeId, bool $isPrimary = false, ?int $assignedBy = null): bool {
        $stmt = $this->db->prepare("
            INSERT INTO employee_branches (branch_id, employee_id, is_primary, assigned_by)
            VALUES (?, ?, ?, ?)
            ON CONFLICT (employee_id, branch_id) DO UPDATE SET
                is_primary = EXCLUDED.is_primary,
                assigned_at = CURRENT_TIMESTAMP
        ");
        return $stmt->execute([$branchId, $employeeId, $isPrimary ? 1 : 0, $assignedBy]);
    }

    public function detachEmployee(int $branchId, int $employeeId): bool {
        $stmt = $this->db->prepare("
            DELETE FROM employee_branches WHERE branch_id = ? AND employee_id = ?
        ");
        return $stmt->execute([$branchId, $employeeId]);
    }

    public function getEmployees(int $branchId): array {
        $stmt = $this->db->prepare("
            SELECT e.*, eb.is_primary, eb.assigned_at, d.name as department_name
            FROM employees e
            JOIN employee_branches eb ON e.id = eb.employee_id
            LEFT JOIN departments d ON e.department_id = d.id
            WHERE eb.branch_id = ?
            ORDER BY eb.is_primary DESC, e.name
        ");
        $stmt->execute([$branchId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getEmployeeBranches(int $employeeId): array {
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

    public function setPrimaryBranch(int $employeeId, int $branchId): bool {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                UPDATE employee_branches SET is_primary = false WHERE employee_id = ?
            ");
            $stmt->execute([$employeeId]);
            
            $stmt = $this->db->prepare("
                UPDATE employee_branches SET is_primary = true 
                WHERE employee_id = ? AND branch_id = ?
            ");
            $stmt->execute([$employeeId, $branchId]);
            
            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    public function getTeams(int $branchId): array {
        $stmt = $this->db->prepare("
            SELECT t.*, u.name as leader_name
            FROM teams t
            LEFT JOIN users u ON t.leader_id = u.id
            WHERE t.branch_id = ?
            ORDER BY t.name
        ");
        $stmt->execute([$branchId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function assignTeamToBranch(int $teamId, int $branchId): bool {
        $stmt = $this->db->prepare("UPDATE teams SET branch_id = ? WHERE id = ?");
        return $stmt->execute([$branchId, $teamId]);
    }

    public function getBranchesWithWhatsAppGroups(): array {
        $stmt = $this->db->query("
            SELECT id, name, code, whatsapp_group
            FROM branches
            WHERE is_active = true AND whatsapp_group IS NOT NULL AND whatsapp_group != ''
            ORDER BY name
        ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getBranchSummaryData(int $branchId, string $date): array {
        $stmt = $this->db->prepare("
            SELECT 
                e.id, e.name, e.department_id,
                a.clock_in, a.clock_out, a.hours_worked, a.late_minutes,
                d.name as department_name
            FROM employees e
            JOIN employee_branches eb ON e.id = eb.employee_id AND eb.branch_id = ?
            LEFT JOIN attendance a ON e.id = a.employee_id AND a.date = ?
            LEFT JOIN departments d ON e.department_id = d.id
            WHERE e.employment_status = 'active'
            ORDER BY d.name, e.name
        ");
        $stmt->execute([$branchId, $date]);
        $employees = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $stmt = $this->db->prepare("
            SELECT 
                t.id, t.ticket_number, t.subject, t.status, t.priority, t.assigned_to,
                u.name as assigned_name, c.name as customer_name
            FROM tickets t
            LEFT JOIN users u ON t.assigned_to = u.id
            LEFT JOIN customers c ON t.customer_id = c.id
            WHERE t.branch_id = ? AND (DATE(t.created_at) = ? OR DATE(t.updated_at) = ?)
            ORDER BY t.priority DESC, t.updated_at DESC
        ");
        $stmt->execute([$branchId, $date, $date]);
        $tickets = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        return [
            'employees' => $employees,
            'tickets' => $tickets
        ];
    }
}
