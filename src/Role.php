<?php

namespace App;

class Role {
    private \PDO $db;
    
    public function __construct(?\PDO $db = null) {
        $this->db = $db ?? \Database::getConnection();
    }
    
    public function getAllRoles(): array {
        $stmt = $this->db->query("
            SELECT r.*, 
                   (SELECT COUNT(*) FROM users WHERE role_id = r.id) as user_count,
                   (SELECT COUNT(*) FROM role_permissions WHERE role_id = r.id) as permission_count
            FROM roles r
            ORDER BY r.is_system DESC, r.name
        ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getRole(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM roles WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    
    public function getRoleByName(string $name): ?array {
        $stmt = $this->db->prepare("SELECT * FROM roles WHERE name = ?");
        $stmt->execute([$name]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    
    public function createRole(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO roles (name, display_name, description, is_system)
            VALUES (?, ?, ?, FALSE)
        ");
        $stmt->execute([
            $this->slugify($data['display_name']),
            $data['display_name'],
            $data['description'] ?? null
        ]);
        return (int)$this->db->lastInsertId();
    }
    
    public function updateRole(int $id, array $data): bool {
        $role = $this->getRole($id);
        if (!$role) return false;
        
        $fields = [];
        $params = [];
        
        if (isset($data['display_name'])) {
            $fields[] = "display_name = ?";
            $params[] = $data['display_name'];
            
            if (!$role['is_system']) {
                $fields[] = "name = ?";
                $params[] = $this->slugify($data['display_name']);
            }
        }
        
        if (isset($data['description'])) {
            $fields[] = "description = ?";
            $params[] = $data['description'];
        }
        
        if (empty($fields)) return true;
        
        $fields[] = "updated_at = CURRENT_TIMESTAMP";
        $params[] = $id;
        
        $sql = "UPDATE roles SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
    
    public function deleteRole(int $id): bool {
        $role = $this->getRole($id);
        if (!$role || $role['is_system']) {
            return false;
        }
        
        $stmt = $this->db->prepare("DELETE FROM roles WHERE id = ? AND is_system = FALSE");
        return $stmt->execute([$id]);
    }
    
    public function getAllPermissions(): array {
        $stmt = $this->db->query("
            SELECT * FROM permissions 
            ORDER BY category, name
        ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getPermissionsByCategory(): array {
        $permissions = $this->getAllPermissions();
        $grouped = [];
        
        foreach ($permissions as $perm) {
            $category = $perm['category'];
            if (!isset($grouped[$category])) {
                $grouped[$category] = [];
            }
            $grouped[$category][] = $perm;
        }
        
        return $grouped;
    }
    
    public function getRolePermissions(int $roleId): array {
        $stmt = $this->db->prepare("
            SELECT p.* 
            FROM permissions p
            JOIN role_permissions rp ON p.id = rp.permission_id
            WHERE rp.role_id = ?
            ORDER BY p.category, p.name
        ");
        $stmt->execute([$roleId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getRolePermissionIds(int $roleId): array {
        $stmt = $this->db->prepare("
            SELECT permission_id FROM role_permissions WHERE role_id = ?
        ");
        $stmt->execute([$roleId]);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }
    
    public function setRolePermissions(int $roleId, array $permissionIds): bool {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("DELETE FROM role_permissions WHERE role_id = ?");
            $stmt->execute([$roleId]);
            
            if (!empty($permissionIds)) {
                $insertStmt = $this->db->prepare("
                    INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)
                ");
                foreach ($permissionIds as $permId) {
                    $insertStmt->execute([$roleId, (int)$permId]);
                }
            }
            
            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    public function getUsersWithRole(int $roleId): array {
        $stmt = $this->db->prepare("
            SELECT id, name, email, role 
            FROM users 
            WHERE role_id = ?
            ORDER BY name
        ");
        $stmt->execute([$roleId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function assignRoleToUser(int $userId, int $roleId): bool {
        $role = $this->getRole($roleId);
        if (!$role) return false;
        
        $stmt = $this->db->prepare("
            UPDATE users SET role_id = ?, role = ? WHERE id = ?
        ");
        return $stmt->execute([$roleId, $role['name'], $userId]);
    }
    
    public function getAllUsers(): array {
        $stmt = $this->db->query("
            SELECT u.id, u.name, u.email, u.role, u.role_id, r.display_name as role_display_name
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            ORDER BY u.name
        ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getUser(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT u.*, r.display_name as role_display_name
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE u.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    
    public function createUser(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO users (name, email, phone, password_hash, role, role_id)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $role = $this->getRole((int)$data['role_id']);
        
        $stmt->execute([
            $data['name'],
            $data['email'],
            $data['phone'] ?? '',
            password_hash($data['password'], PASSWORD_DEFAULT),
            $role ? $role['name'] : 'technician',
            $data['role_id'] ?: null
        ]);
        
        return (int)$this->db->lastInsertId();
    }
    
    public function updateUser(int $id, array $data): bool {
        $fields = [];
        $params = [];
        
        if (isset($data['name'])) {
            $fields[] = "name = ?";
            $params[] = $data['name'];
        }
        
        if (isset($data['email'])) {
            $fields[] = "email = ?";
            $params[] = $data['email'];
        }
        
        if (isset($data['phone'])) {
            $fields[] = "phone = ?";
            $params[] = $data['phone'];
        }
        
        if (!empty($data['password'])) {
            $fields[] = "password_hash = ?";
            $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        
        if (isset($data['role_id'])) {
            $role = $this->getRole((int)$data['role_id']);
            $fields[] = "role_id = ?";
            $params[] = $data['role_id'] ?: null;
            if ($role) {
                $fields[] = "role = ?";
                $params[] = $role['name'];
            }
        }
        
        if (empty($fields)) return true;
        
        $params[] = $id;
        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
    
    public function deleteUser(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM users WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    private function slugify(string $text): string {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9-]/', '_', $text);
        $text = preg_replace('/_+/', '_', $text);
        return trim($text, '_');
    }
}
