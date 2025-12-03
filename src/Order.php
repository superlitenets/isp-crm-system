<?php

namespace App;

class Order {
    private \PDO $db;
    
    public function __construct() {
        $this->db = \Database::getConnection();
    }
    
    public function generateOrderNumber(): string {
        $prefix = 'ORD';
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 4));
        return "{$prefix}{$date}{$random}";
    }
    
    public function create(array $data): int {
        $orderNumber = $this->generateOrderNumber();
        
        $stmt = $this->db->prepare("
            INSERT INTO orders (order_number, package_id, customer_name, customer_email, 
                               customer_phone, customer_address, amount, payment_method, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            RETURNING id
        ");
        
        $stmt->execute([
            $orderNumber,
            $data['package_id'] ?? null,
            $data['customer_name'],
            $data['customer_email'] ?? null,
            $data['customer_phone'],
            $data['customer_address'] ?? null,
            $data['amount'] ?? null,
            $data['payment_method'] ?? null,
            $data['notes'] ?? null
        ]);
        
        return (int) $stmt->fetchColumn();
    }
    
    public function getById(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT o.*, 
                   p.name as package_name, p.speed, p.speed_unit, p.price as package_price,
                   c.name as linked_customer_name, c.account_number,
                   t.ticket_number
            FROM orders o
            LEFT JOIN service_packages p ON o.package_id = p.id
            LEFT JOIN customers c ON o.customer_id = c.id
            LEFT JOIN tickets t ON o.ticket_id = t.id
            WHERE o.id = ?
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    
    public function getByOrderNumber(string $orderNumber): ?array {
        $stmt = $this->db->prepare("
            SELECT o.*, 
                   p.name as package_name, p.speed, p.speed_unit, p.price as package_price
            FROM orders o
            LEFT JOIN service_packages p ON o.package_id = p.id
            WHERE o.order_number = ?
        ");
        $stmt->execute([$orderNumber]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    
    public function getAll(array $filters = []): array {
        $where = [];
        $params = [];
        
        if (!empty($filters['status'])) {
            $where[] = "o.order_status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['payment_status'])) {
            $where[] = "o.payment_status = ?";
            $params[] = $filters['payment_status'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = "(o.order_number ILIKE ? OR o.customer_name ILIKE ? OR o.customer_phone ILIKE ? OR o.customer_email ILIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params = array_merge($params, [$search, $search, $search, $search]);
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $limit = isset($filters['limit']) ? (int)$filters['limit'] : 100;
        
        $stmt = $this->db->prepare("
            SELECT o.*, 
                   p.name as package_name, p.speed, p.price as package_price,
                   c.name as linked_customer_name,
                   t.ticket_number
            FROM orders o
            LEFT JOIN service_packages p ON o.package_id = p.id
            LEFT JOIN customers c ON o.customer_id = c.id
            LEFT JOIN tickets t ON o.ticket_id = t.id
            $whereClause
            ORDER BY o.created_at DESC
            LIMIT $limit
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function updateStatus(int $id, string $status): bool {
        $stmt = $this->db->prepare("
            UPDATE orders SET order_status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?
        ");
        return $stmt->execute([$status, $id]);
    }
    
    public function updatePaymentStatus(int $id, string $status, ?int $transactionId = null): bool {
        $sql = "UPDATE orders SET payment_status = ?, updated_at = CURRENT_TIMESTAMP";
        $params = [$status];
        
        if ($transactionId) {
            $sql .= ", mpesa_transaction_id = ?";
            $params[] = $transactionId;
        }
        
        $sql .= " WHERE id = ?";
        $params[] = $id;
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
    
    public function linkCustomer(int $orderId, int $customerId): bool {
        $stmt = $this->db->prepare("
            UPDATE orders SET customer_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?
        ");
        return $stmt->execute([$customerId, $orderId]);
    }
    
    public function linkTicket(int $orderId, int $ticketId): bool {
        $stmt = $this->db->prepare("
            UPDATE orders SET ticket_id = ?, order_status = 'converted', updated_at = CURRENT_TIMESTAMP WHERE id = ?
        ");
        return $stmt->execute([$ticketId, $orderId]);
    }
    
    public function convertToTicket(int $orderId, int $createdBy): ?int {
        $order = $this->getById($orderId);
        if (!$order) {
            return null;
        }
        
        $customer = new Customer();
        $customerId = $order['customer_id'];
        
        if (!$customerId) {
            $accountNumber = $customer->generateAccountNumber();
            $stmt = $this->db->prepare("
                INSERT INTO customers (account_number, name, email, phone, address, service_plan, connection_status)
                VALUES (?, ?, ?, ?, ?, ?, 'pending')
                RETURNING id
            ");
            $stmt->execute([
                $accountNumber,
                $order['customer_name'],
                $order['customer_email'],
                $order['customer_phone'],
                $order['customer_address'] ?? '',
                $order['package_name'] ?? 'Standard'
            ]);
            $customerId = (int) $stmt->fetchColumn();
            $this->linkCustomer($orderId, $customerId);
        }
        
        $ticket = new Ticket();
        $ticketNumber = $ticket->generateTicketNumber();
        
        $subject = "New Installation - " . ($order['package_name'] ?? 'Service Package');
        $description = "New service order from website.\n\n";
        $description .= "Order Number: {$order['order_number']}\n";
        $description .= "Package: " . ($order['package_name'] ?? 'N/A') . "\n";
        $description .= "Customer: {$order['customer_name']}\n";
        $description .= "Phone: {$order['customer_phone']}\n";
        $description .= "Address: " . ($order['customer_address'] ?? 'N/A') . "\n";
        $description .= "Payment Status: " . ucfirst($order['payment_status']) . "\n";
        if ($order['notes']) {
            $description .= "\nNotes: {$order['notes']}\n";
        }
        
        $stmt = $this->db->prepare("
            INSERT INTO tickets (ticket_number, customer_id, subject, description, category, priority, status)
            VALUES (?, ?, ?, ?, 'installation', 'high', 'open')
            RETURNING id
        ");
        $stmt->execute([
            $ticketNumber,
            $customerId,
            $subject,
            $description
        ]);
        $ticketId = (int) $stmt->fetchColumn();
        
        $this->linkTicket($orderId, $ticketId);
        
        return $ticketId;
    }
    
    public function getStats(): array {
        $stmt = $this->db->query("
            SELECT 
                COUNT(*) as total,
                COUNT(*) FILTER (WHERE order_status = 'new') as new_orders,
                COUNT(*) FILTER (WHERE order_status = 'confirmed') as confirmed,
                COUNT(*) FILTER (WHERE order_status = 'converted') as converted,
                COUNT(*) FILTER (WHERE payment_status = 'paid') as paid,
                SUM(amount) FILTER (WHERE payment_status = 'paid') as total_paid
            FROM orders
        ");
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
    
    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM orders WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    public function update(int $id, array $data): bool {
        $fields = [];
        $params = [];
        
        $allowedFields = ['customer_name', 'customer_email', 'customer_phone', 'customer_address', 
                          'package_id', 'amount', 'order_status', 'payment_status', 'notes'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $fields[] = "updated_at = CURRENT_TIMESTAMP";
        $params[] = $id;
        
        $sql = "UPDATE orders SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
}
