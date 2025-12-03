<?php

namespace App;

class Customer {
    private \PDO $db;

    public function __construct() {
        $this->db = \Database::getConnection();
    }

    public function generateAccountNumber(): string {
        return 'ISP-' . date('Y') . '-' . str_pad(random_int(1, 99999), 5, '0', STR_PAD_LEFT);
    }

    public function create(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO customers (account_number, name, email, phone, address, service_plan, connection_status, installation_date, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['account_number'] ?? $this->generateAccountNumber(),
            $data['name'],
            $data['email'] ?? null,
            $data['phone'],
            $data['address'],
            $data['service_plan'],
            $data['connection_status'] ?? 'active',
            $data['installation_date'] ?? null,
            $data['notes'] ?? null
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool {
        $fields = [];
        $values = [];
        
        foreach (['name', 'email', 'phone', 'address', 'service_plan', 'connection_status', 'installation_date', 'notes'] as $field) {
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
        
        $stmt = $this->db->prepare("UPDATE customers SET " . implode(', ', $fields) . " WHERE id = ?");
        return $stmt->execute($values);
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM customers WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function find(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM customers WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function findByAccountNumber(string $accountNumber): ?array {
        $stmt = $this->db->prepare("SELECT * FROM customers WHERE account_number = ?");
        $stmt->execute([$accountNumber]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function getAll(string $search = '', int $limit = 50, int $offset = 0): array {
        $sql = "SELECT * FROM customers";
        $params = [];
        
        if ($search) {
            $sql .= " WHERE name ILIKE ? OR account_number ILIKE ? OR phone ILIKE ? OR email ILIKE ?";
            $searchTerm = "%$search%";
            $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function count(string $search = ''): int {
        $sql = "SELECT COUNT(*) FROM customers";
        $params = [];
        
        if ($search) {
            $sql .= " WHERE name ILIKE ? OR account_number ILIKE ? OR phone ILIKE ? OR email ILIKE ?";
            $searchTerm = "%$search%";
            $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function getServicePlans(): array {
        return [
            'basic' => 'Basic (10 Mbps)',
            'standard' => 'Standard (50 Mbps)',
            'premium' => 'Premium (100 Mbps)',
            'business' => 'Business (200 Mbps)',
            'enterprise' => 'Enterprise (500 Mbps)'
        ];
    }

    public function getConnectionStatuses(): array {
        return [
            'active' => 'Active',
            'suspended' => 'Suspended',
            'disconnected' => 'Disconnected',
            'pending' => 'Pending Installation'
        ];
    }
}
