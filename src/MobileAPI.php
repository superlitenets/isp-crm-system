<?php

namespace App;

class MobileAPI {
    private \PDO $db;
    
    public function __construct(?\PDO $db = null) {
        $this->db = $db ?? \Database::getConnection();
    }
    
    public function authenticate(string $email, string $password): ?array {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password_hash'])) {
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+30 days'));
            
            $stmt = $this->db->prepare("
                INSERT INTO mobile_tokens (user_id, token, expires_at) 
                VALUES (?, ?, ?)
                ON CONFLICT (user_id) DO UPDATE SET token = ?, expires_at = ?
            ");
            $stmt->execute([$user['id'], $token, $expiry, $token, $expiry]);
            
            unset($user['password_hash']);
            return [
                'user' => $user,
                'token' => $token,
                'expires_at' => $expiry
            ];
        }
        return null;
    }
    
    public function validateToken(string $token): ?array {
        $stmt = $this->db->prepare("
            SELECT u.* FROM users u
            JOIN mobile_tokens t ON u.id = t.user_id
            WHERE t.token = ? AND t.expires_at > NOW()
        ");
        $stmt->execute([$token]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($user) {
            unset($user['password_hash']);
            return $user;
        }
        return null;
    }
    
    public function logout(string $token): bool {
        $stmt = $this->db->prepare("DELETE FROM mobile_tokens WHERE token = ?");
        return $stmt->execute([$token]);
    }
    
    public function getSalespersonByUserId(int $userId): ?array {
        $stmt = $this->db->prepare("SELECT * FROM salespersons WHERE user_id = ? AND is_active = TRUE");
        $stmt->execute([$userId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    
    public function getSalespersonOrders(int $salespersonId, string $status = ''): array {
        $sql = "SELECT o.*, sp.name as package_name, sp.speed, sp.price as package_price
                FROM orders o
                LEFT JOIN service_packages sp ON o.package_id = sp.id
                WHERE o.salesperson_id = ?";
        $params = [$salespersonId];
        
        if ($status) {
            $sql .= " AND o.order_status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY o.created_at DESC LIMIT 50";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getSalespersonStats(int $salespersonId): array {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_orders,
                COUNT(CASE WHEN order_status = 'completed' THEN 1 END) as completed_orders,
                COUNT(CASE WHEN order_status = 'new' THEN 1 END) as pending_orders,
                COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN amount ELSE 0 END), 0) as total_sales
            FROM orders WHERE salesperson_id = ?
        ");
        $stmt->execute([$salespersonId]);
        $stats = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(commission_amount), 0) as total_commission,
                   COALESCE(SUM(CASE WHEN status = 'pending' THEN commission_amount ELSE 0 END), 0) as pending_commission
            FROM sales_commissions WHERE salesperson_id = ?
        ");
        $stmt->execute([$salespersonId]);
        $commissions = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return array_merge($stats, $commissions);
    }
    
    public function createOrder(int $salespersonId, array $data): ?int {
        $orderNumber = 'ORD-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        $stmt = $this->db->prepare("
            INSERT INTO orders (order_number, package_id, customer_name, customer_email, customer_phone, 
                customer_address, amount, salesperson_id, order_status, payment_status, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'new', 'pending', ?)
        ");
        
        $stmt->execute([
            $orderNumber,
            $data['package_id'] ?: null,
            $data['customer_name'],
            $data['customer_email'] ?? null,
            $data['customer_phone'],
            $data['customer_address'] ?? null,
            $data['amount'] ?? 0,
            $salespersonId,
            $data['notes'] ?? null
        ]);
        
        return (int) $this->db->lastInsertId();
    }
    
    public function getServicePackages(): array {
        $stmt = $this->db->query("SELECT * FROM service_packages WHERE is_active = TRUE ORDER BY display_order, price");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getTechnicianTickets(int $userId, string $status = ''): array {
        $sql = "SELECT t.*, c.name as customer_name, c.phone as customer_phone, c.address as customer_address
                FROM tickets t
                LEFT JOIN customers c ON t.customer_id = c.id
                WHERE t.assigned_to = ?";
        $params = [$userId];
        
        if ($status) {
            $sql .= " AND t.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY 
            CASE t.priority 
                WHEN 'critical' THEN 1 
                WHEN 'high' THEN 2 
                WHEN 'medium' THEN 3 
                ELSE 4 
            END,
            t.created_at DESC
            LIMIT 50";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getTicketDetails(int $ticketId, int $userId): ?array {
        $stmt = $this->db->prepare("
            SELECT t.*, c.name as customer_name, c.phone as customer_phone, 
                   c.address as customer_address, c.email as customer_email
            FROM tickets t
            LEFT JOIN customers c ON t.customer_id = c.id
            WHERE t.id = ? AND t.assigned_to = ?
        ");
        $stmt->execute([$ticketId, $userId]);
        $ticket = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($ticket) {
            $stmt = $this->db->prepare("
                SELECT tc.*, u.name as user_name 
                FROM ticket_comments tc
                LEFT JOIN users u ON tc.user_id = u.id
                WHERE tc.ticket_id = ?
                ORDER BY tc.created_at DESC
            ");
            $stmt->execute([$ticketId]);
            $ticket['comments'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }
        
        return $ticket ?: null;
    }
    
    public function updateTicketStatus(int $ticketId, int $userId, string $status, ?string $comment = null): bool {
        $stmt = $this->db->prepare("UPDATE tickets SET status = ?, updated_at = NOW() WHERE id = ? AND assigned_to = ?");
        $result = $stmt->execute([$status, $ticketId, $userId]);
        
        if ($result && $comment) {
            $stmt = $this->db->prepare("INSERT INTO ticket_comments (ticket_id, user_id, comment) VALUES (?, ?, ?)");
            $stmt->execute([$ticketId, $userId, $comment]);
        }
        
        if ($status === 'resolved') {
            $stmt = $this->db->prepare("UPDATE tickets SET resolved_at = NOW() WHERE id = ?");
            $stmt->execute([$ticketId]);
        }
        
        return $result;
    }
    
    public function addTicketComment(int $ticketId, int $userId, string $comment): bool {
        $stmt = $this->db->prepare("
            SELECT id FROM tickets WHERE id = ? AND assigned_to = ?
        ");
        $stmt->execute([$ticketId, $userId]);
        
        if (!$stmt->fetch()) {
            return false;
        }
        
        $stmt = $this->db->prepare("INSERT INTO ticket_comments (ticket_id, user_id, comment) VALUES (?, ?, ?)");
        return $stmt->execute([$ticketId, $userId, $comment]);
    }
    
    public function getTechnicianStats(int $userId): array {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_tickets,
                COUNT(CASE WHEN status = 'open' THEN 1 END) as open_tickets,
                COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress_tickets,
                COUNT(CASE WHEN status = 'resolved' THEN 1 END) as resolved_tickets
            FROM tickets WHERE assigned_to = ?
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
    
    public function getEmployeeByUserId(int $userId): ?array {
        $stmt = $this->db->prepare("SELECT * FROM employees WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    
    public function clockIn(int $employeeId): array {
        $today = date('Y-m-d');
        $now = date('H:i:s');
        
        $stmt = $this->db->prepare("SELECT * FROM attendance WHERE employee_id = ? AND date = ?");
        $stmt->execute([$employeeId, $today]);
        $existing = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($existing) {
            if ($existing['clock_in']) {
                return ['success' => false, 'message' => 'Already clocked in today at ' . $existing['clock_in']];
            }
        }
        
        $stmt = $this->db->prepare("
            INSERT INTO attendance (employee_id, date, clock_in, status, source)
            VALUES (?, ?, ?, 'present', 'mobile')
            ON CONFLICT (employee_id, date) DO UPDATE SET clock_in = ?, source = 'mobile'
        ");
        $stmt->execute([$employeeId, $today, $now, $now]);
        
        return ['success' => true, 'message' => 'Clocked in at ' . $now, 'time' => $now];
    }
    
    public function clockOut(int $employeeId): array {
        $today = date('Y-m-d');
        $now = date('H:i:s');
        
        $stmt = $this->db->prepare("SELECT * FROM attendance WHERE employee_id = ? AND date = ?");
        $stmt->execute([$employeeId, $today]);
        $existing = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$existing || !$existing['clock_in']) {
            return ['success' => false, 'message' => 'You must clock in first'];
        }
        
        if ($existing['clock_out']) {
            return ['success' => false, 'message' => 'Already clocked out today at ' . $existing['clock_out']];
        }
        
        $clockIn = strtotime($existing['clock_in']);
        $clockOut = strtotime($now);
        $hoursWorked = round(($clockOut - $clockIn) / 3600, 2);
        
        $stmt = $this->db->prepare("
            UPDATE attendance SET clock_out = ?, hours_worked = ?, updated_at = NOW()
            WHERE employee_id = ? AND date = ?
        ");
        $stmt->execute([$now, $hoursWorked, $employeeId, $today]);
        
        return ['success' => true, 'message' => 'Clocked out at ' . $now, 'time' => $now, 'hours_worked' => $hoursWorked];
    }
    
    public function getTodayAttendance(int $employeeId): ?array {
        $stmt = $this->db->prepare("SELECT * FROM attendance WHERE employee_id = ? AND date = ?");
        $stmt->execute([$employeeId, date('Y-m-d')]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    
    public function getAssignedEquipment(int $userId): array {
        $stmt = $this->db->prepare("
            SELECT ea.*, e.name as equipment_name, e.serial_number, e.brand, e.model,
                   c.name as customer_name, c.address as customer_address
            FROM equipment_assignments ea
            JOIN equipment e ON ea.equipment_id = e.id
            LEFT JOIN customers c ON ea.customer_id = c.id
            WHERE ea.assigned_by = ? AND ea.status = 'assigned'
            ORDER BY ea.assignment_date DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getRecentAttendance(int $employeeId, int $days = 7): array {
        $stmt = $this->db->prepare("
            SELECT * FROM attendance 
            WHERE employee_id = ? AND date >= CURRENT_DATE - INTERVAL '$days days'
            ORDER BY date DESC
        ");
        $stmt->execute([$employeeId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
