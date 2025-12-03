<?php

namespace App;

class Employee {
    private \PDO $db;

    public function __construct() {
        $this->db = \Database::getConnection();
    }

    public function generateEmployeeId(): string {
        return 'EMP-' . date('Y') . '-' . str_pad(random_int(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    public function create(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO employees (employee_id, name, email, phone, department_id, position, salary, hire_date, employment_status, emergency_contact, emergency_phone, address, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['employee_id'] ?? $this->generateEmployeeId(),
            $data['name'],
            $data['email'],
            $data['phone'],
            $data['department_id'] ?: null,
            $data['position'],
            $data['salary'] ?: null,
            $data['hire_date'] ?: null,
            $data['employment_status'] ?? 'active',
            $data['emergency_contact'] ?? null,
            $data['emergency_phone'] ?? null,
            $data['address'] ?? null,
            $data['notes'] ?? null
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool {
        $fields = [];
        $values = [];
        
        $allowedFields = ['name', 'email', 'phone', 'department_id', 'position', 'salary', 'hire_date', 'employment_status', 'emergency_contact', 'emergency_phone', 'address', 'notes'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                $values[] = $data[$field] === '' ? null : $data[$field];
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
}
