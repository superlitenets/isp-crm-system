<?php

namespace App;

class Order {
    private \PDO $db;
    private SMSGateway $sms;
    private Settings $settings;
    
    public function __construct() {
        $this->db = \Database::getConnection();
        $this->sms = new SMSGateway();
        $this->settings = new Settings();
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
                               customer_phone, customer_address, amount, payment_method, notes, salesperson_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
            $data['notes'] ?? null,
            $data['salesperson_id'] ?? null
        ]);
        
        $orderId = (int) $stmt->fetchColumn();
        
        if (!empty($data['salesperson_id']) && $orderId) {
            $this->createCommission($orderId, (int)$data['salesperson_id'], (float)($data['amount'] ?? 0));
        }
        
        // Send SMS confirmation to customer
        if (!empty($data['customer_phone'])) {
            $this->sendOrderConfirmationSMS($orderNumber, $data);
        }
        
        return $orderId;
    }
    
    private function sendOrderConfirmationSMS(string $orderNumber, array $data): void {
        $template = $this->settings->get('sms_template_order_confirmation', 
            'Dear {customer_name}, your order #{order_number} has been received. Amount: KES {amount}. We will contact you shortly. Thank you!');
        
        $placeholders = [
            '{customer_name}' => $data['customer_name'] ?? 'Customer',
            '{order_number}' => $orderNumber,
            '{amount}' => number_format((float)($data['amount'] ?? 0)),
            '{customer_phone}' => $data['customer_phone'] ?? '',
            '{customer_address}' => $data['customer_address'] ?? ''
        ];
        
        $message = str_replace(array_keys($placeholders), array_values($placeholders), $template);
        $this->sms->send($data['customer_phone'], $message);
    }
    
    private function createCommission(int $orderId, int $salespersonId, float $amount): void {
        if ($amount <= 0) return;
        
        $stmt = $this->db->prepare("SELECT commission_type, commission_value FROM salespersons WHERE id = ?");
        $stmt->execute([$salespersonId]);
        $sp = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$sp) return;
        
        $commissionType = $sp['commission_type'];
        $commissionRate = (float) $sp['commission_value'];
        
        if ($commissionType === 'percentage') {
            $commissionAmount = ($amount * $commissionRate) / 100;
        } else {
            $commissionAmount = $commissionRate;
        }
        
        $stmt = $this->db->prepare("
            INSERT INTO sales_commissions (salesperson_id, order_id, order_amount, commission_type, commission_rate, commission_amount, status)
            VALUES (?, ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([$salespersonId, $orderId, $amount, $commissionType, $commissionRate, $commissionAmount]);
        
        $stmt = $this->db->prepare("
            UPDATE salespersons 
            SET total_sales = total_sales + ?,
                total_commission = total_commission + ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([$amount, $commissionAmount, $salespersonId]);
    }
    
    public function getById(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT o.*, 
                   p.name as package_name, p.speed, p.speed_unit, p.price as package_price,
                   c.name as linked_customer_name, c.account_number,
                   t.ticket_number,
                   s.name as salesperson_name, s.phone as salesperson_phone,
                   sc.commission_amount, sc.status as commission_status
            FROM orders o
            LEFT JOIN service_packages p ON o.package_id = p.id
            LEFT JOIN customers c ON o.customer_id = c.id
            LEFT JOIN tickets t ON o.ticket_id = t.id
            LEFT JOIN salespersons s ON o.salesperson_id = s.id
            LEFT JOIN sales_commissions sc ON sc.order_id = o.id
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
        
        if (!empty($filters['salesperson_id'])) {
            $where[] = "o.salesperson_id = ?";
            $params[] = (int)$filters['salesperson_id'];
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $limit = isset($filters['limit']) ? (int)$filters['limit'] : 100;
        
        $stmt = $this->db->prepare("
            SELECT o.*, 
                   p.name as package_name, p.speed, p.price as package_price,
                   c.name as linked_customer_name,
                   t.ticket_number,
                   s.name as salesperson_name
            FROM orders o
            LEFT JOIN service_packages p ON o.package_id = p.id
            LEFT JOIN customers c ON o.customer_id = c.id
            LEFT JOIN tickets t ON o.ticket_id = t.id
            LEFT JOIN salespersons s ON o.salesperson_id = s.id
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
                          'package_id', 'amount', 'order_status', 'payment_status', 'notes', 'salesperson_id'];
        
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
