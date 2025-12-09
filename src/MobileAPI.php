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
    
    public function getSalespersonByUserId(int $userId, bool $autoCreate = false): ?array {
        $stmt = $this->db->prepare("SELECT * FROM salespersons WHERE user_id = ? AND is_active = TRUE");
        $stmt->execute([$userId]);
        $salesperson = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($salesperson) {
            return $salesperson;
        }
        
        if ($autoCreate) {
            $stmt = $this->db->prepare("SELECT * FROM salespersons WHERE user_id = ?");
            $stmt->execute([$userId]);
            $existingInactive = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($existingInactive) {
                $stmt = $this->db->prepare("UPDATE salespersons SET is_active = TRUE WHERE id = ? RETURNING *");
                $stmt->execute([$existingInactive['id']]);
                return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
            }
            
            $stmt = $this->db->prepare("SELECT id, name, email, role FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($user && in_array($user['role'], ['salesperson', 'sales', 'admin', 'manager'])) {
                $stmt = $this->db->prepare("
                    INSERT INTO salespersons (user_id, name, email, commission_type, commission_value, is_active)
                    VALUES (?, ?, ?, 'percentage', 5, TRUE)
                    RETURNING *
                ");
                $stmt->execute([$userId, $user['name'], $user['email']]);
                return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
            }
        }
        
        return null;
    }
    
    public function getSalespersonOrders(int $salespersonId, string $status = '', int $limit = 50): array {
        $sql = "SELECT o.*, sp.name as package_name, sp.speed, sp.price as package_price
                FROM orders o
                LEFT JOIN service_packages sp ON o.package_id = sp.id
                WHERE o.salesperson_id = ?";
        $params = [$salespersonId];
        
        if ($status) {
            $sql .= " AND o.order_status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY o.created_at DESC LIMIT " . (int)$limit;
        
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
                customer_address, amount, salesperson_id, order_status, payment_status, notes, lead_source)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'new', 'pending', ?, 'mobile')
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
    
    public function createLead(int $salespersonId, array $data): ?int {
        $orderNumber = 'LEAD-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        $notes = '';
        if (!empty($data['location'])) {
            $notes .= "Location: " . $data['location'];
        }
        if (!empty($data['description'])) {
            $notes .= ($notes ? "\n" : '') . "Description: " . $data['description'];
        }
        
        $stmt = $this->db->prepare("
            INSERT INTO orders (order_number, customer_name, customer_phone, customer_address, 
                salesperson_id, order_status, payment_status, notes, lead_source, amount)
            VALUES (?, ?, ?, ?, ?, 'new', 'pending', ?, 'mobile_lead', 0)
        ");
        
        $stmt->execute([
            $orderNumber,
            $data['customer_name'],
            $data['customer_phone'],
            $data['location'] ?? null,
            $salespersonId,
            $notes
        ]);
        
        return (int) $this->db->lastInsertId();
    }
    
    public function getNewOrdersCount(): int {
        $stmt = $this->db->query("SELECT COUNT(*) FROM orders WHERE order_status = 'new'");
        return (int) $stmt->fetchColumn();
    }
    
    public function getServicePackages(): array {
        $stmt = $this->db->query("SELECT * FROM service_packages WHERE is_active = TRUE ORDER BY display_order, price");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getTechnicianTickets(int $userId, string $status = '', int $limit = 50): array {
        $sql = "SELECT t.*, c.name as customer_name, c.phone as customer_phone, c.address as customer_address,
                       COALESCE((SELECT SUM(te.earned_amount) FROM ticket_earnings te WHERE te.ticket_id = t.id), 0) as earnings
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
            LIMIT " . (int)$limit;
        
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
        $stmt = $this->db->prepare("
            SELECT t.*, c.phone as customer_phone, c.name as customer_name, t.ticket_number
            FROM tickets t
            LEFT JOIN customers c ON t.customer_id = c.id
            WHERE t.id = ? AND t.assigned_to = ?
        ");
        $stmt->execute([$ticketId, $userId]);
        $ticket = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$ticket) {
            return false;
        }
        
        $stmt = $this->db->prepare("UPDATE tickets SET status = ?, updated_at = NOW() WHERE id = ?");
        $result = $stmt->execute([$status, $ticketId]);
        
        if ($result && $comment) {
            $stmt = $this->db->prepare("INSERT INTO ticket_comments (ticket_id, user_id, comment) VALUES (?, ?, ?)");
            $stmt->execute([$ticketId, $userId, $comment]);
        }
        
        if ($status === 'resolved') {
            $stmt = $this->db->prepare("UPDATE tickets SET resolved_at = NOW() WHERE id = ?");
            $stmt->execute([$ticketId]);
        }
        
        if ($result && !empty($ticket['customer_phone'])) {
            $this->sendStatusNotification($ticket, $status);
        }
        
        return $result;
    }
    
    private function sendStatusNotification(array $ticket, string $status): void {
        try {
            $settings = new Settings();
            $smsEnabled = $settings->get('sms_enabled', false);
            $waEnabled = $settings->get('whatsapp_enabled', false);
            
            if (!$smsEnabled && !$waEnabled) {
                return;
            }
            
            $statusLabels = [
                'open' => 'Open',
                'in_progress' => 'In Progress',
                'pending' => 'Pending',
                'resolved' => 'Resolved',
                'closed' => 'Closed'
            ];
            
            $statusLabel = $statusLabels[$status] ?? ucfirst($status);
            $ticketNumber = $ticket['ticket_number'] ?? $ticket['id'];
            
            $template = $settings->get('sms_template_status_update', 
                'Your ticket #{ticket_number} status has been updated to: {status}. Thank you for your patience.');
            
            $message = str_replace(
                ['{ticket_number}', '{status}', '{customer_name}'],
                [$ticketNumber, $statusLabel, $ticket['customer_name'] ?? 'Customer'],
                $template
            );
            
            if ($smsEnabled) {
                $sms = new SMSGateway();
                if ($sms->isEnabled()) {
                    $sms->send($ticket['customer_phone'], $message);
                }
            }
            
            if ($waEnabled) {
                $wa = new WhatsApp();
                if ($wa->isEnabled()) {
                    $wa->send($ticket['customer_phone'], $message);
                }
            }
        } catch (\Exception $e) {
            error_log("MobileAPI notification error: " . $e->getMessage());
        }
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
    
    public function closeTicketWithDetails(int $ticketId, int $userId, array $closureDetails, string $userRole = 'technician'): bool {
        if ($userRole === 'admin' || $userRole === 'manager') {
            $stmt = $this->db->prepare("SELECT id FROM tickets WHERE id = ?");
            $stmt->execute([$ticketId]);
        } else {
            $stmt = $this->db->prepare("SELECT id FROM tickets WHERE id = ? AND assigned_to = ?");
            $stmt->execute([$ticketId, $userId]);
        }
        
        if (!$stmt->fetch()) {
            return false;
        }
        
        $stmt = $this->db->prepare("
            UPDATE tickets SET 
                status = 'resolved',
                resolved_at = NOW(),
                updated_at = NOW()
            WHERE id = ?
        ");
        $result = $stmt->execute([$ticketId]);
        
        if ($result) {
            $comment = "Ticket resolved. ";
            if (!empty($closureDetails['cable_meters'])) {
                $comment .= "Cable used: " . $closureDetails['cable_meters'] . "m. ";
            }
            if (!empty($closureDetails['router_model'])) {
                $comment .= "Router: " . $closureDetails['router_model'] . ". ";
            }
            if (!empty($closureDetails['router_serial'])) {
                $comment .= "S/N: " . $closureDetails['router_serial'] . ". ";
            }
            if (!empty($closureDetails['comment'])) {
                $comment .= $closureDetails['comment'];
            }
            
            $stmt = $this->db->prepare("INSERT INTO ticket_comments (ticket_id, user_id, comment) VALUES (?, ?, ?)");
            $stmt->execute([$ticketId, $userId, $comment]);
        }
        
        return $result;
    }
    
    public function getAvailableEquipmentForTicket(int $ticketId): array {
        $stmt = $this->db->prepare("
            SELECT e.id, e.name, e.serial_number, e.brand, e.model, e.mac_address,
                   CASE WHEN ea.id IS NOT NULL THEN 'assigned' ELSE 'available' END as status
            FROM equipment e
            LEFT JOIN equipment_assignments ea ON e.id = ea.equipment_id AND ea.status = 'assigned'
            WHERE e.status = 'in_stock' OR e.status = 'available' OR ea.id IS NOT NULL
            ORDER BY e.name
            LIMIT 100
        ");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
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
    
    public function clockIn(int $employeeId, ?float $latitude = null, ?float $longitude = null): array {
        $today = date('Y-m-d');
        $now = date('H:i:s');
        $cutoffTime = '08:30:00';
        
        if ($now > $cutoffTime) {
            return ['success' => false, 'message' => 'Clock in is only allowed until 8:30 AM. Current time: ' . date('h:i A')];
        }
        
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
            ON CONFLICT (employee_id, date) DO UPDATE SET 
                clock_in = EXCLUDED.clock_in, 
                source = 'mobile'
        ");
        $stmt->execute([$employeeId, $today, $now]);
        
        return ['success' => true, 'message' => 'Clocked in at ' . date('h:i A'), 'time' => $now];
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
            UPDATE attendance SET 
                clock_out = ?, 
                hours_worked = ?, 
                updated_at = NOW()
            WHERE employee_id = ? AND date = ?
        ");
        $stmt->execute([$now, $hoursWorked, $employeeId, $today]);
        
        return ['success' => true, 'message' => 'Clocked out at ' . date('h:i A'), 'time' => $now, 'hours_worked' => $hoursWorked];
    }
    
    public function getTodayAttendance(int $employeeId): ?array {
        $stmt = $this->db->prepare("SELECT * FROM attendance WHERE employee_id = ? AND date = ?");
        $stmt->execute([$employeeId, date('Y-m-d')]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    
    public function getAssignedEquipment(int $userId): array {
        $employee = $this->getEmployeeByUserId($userId);
        if (!$employee) {
            return [];
        }
        
        $stmt = $this->db->prepare("
            SELECT ea.*, e.name as equipment_name, e.serial_number, e.brand, e.model,
                   c.name as customer_name, c.address as customer_address
            FROM equipment_assignments ea
            JOIN equipment e ON ea.equipment_id = e.id
            LEFT JOIN customers c ON ea.customer_id = c.id
            WHERE ea.employee_id = ? AND ea.status = 'assigned'
            ORDER BY ea.assignment_date DESC
        ");
        $stmt->execute([$employee['id']]);
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
    
    public function getSalespersonDashboard(int $userId): array {
        $salesperson = $this->getSalespersonByUserId($userId);
        if (!$salesperson) {
            return ['error' => 'Not a salesperson'];
        }
        
        return [
            'stats' => $this->getSalespersonStats($salesperson['id']),
            'orders' => $this->getSalespersonOrders($salesperson['id'], '', 20),
            'salesperson' => $salesperson
        ];
    }
    
    public function getTechnicianDashboard(int $userId): array {
        $employee = $this->getEmployeeByUserId($userId);
        
        return [
            'stats' => $this->getTechnicianStats($userId),
            'tickets' => $this->getTechnicianTickets($userId, '', 20),
            'attendance' => $employee ? $this->getTodayAttendance($employee['id']) : null,
            'employee' => $employee
        ];
    }
    
    public function getSalespersonPerformance(int $salespersonId): array {
        $thisMonth = date('Y-m-01');
        $lastMonth = date('Y-m-01', strtotime('-1 month'));
        
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_orders,
                COUNT(CASE WHEN order_status = 'completed' THEN 1 END) as completed_orders,
                COUNT(CASE WHEN order_status = 'cancelled' THEN 1 END) as cancelled_orders,
                COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN amount ELSE 0 END), 0) as total_sales
            FROM orders 
            WHERE salesperson_id = ? AND created_at >= ?
        ");
        $stmt->execute([$salespersonId, $thisMonth]);
        $thisMonthStats = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        $stmt->execute([$salespersonId, $lastMonth]);
        $lastMonthStats = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        $conversionRate = $thisMonthStats['total_orders'] > 0 
            ? round(($thisMonthStats['completed_orders'] / $thisMonthStats['total_orders']) * 100, 1) 
            : 0;
        
        $stmt = $this->db->prepare("
            SELECT s.id, s.name, 
                   COALESCE(SUM(CASE WHEN o.payment_status = 'paid' THEN o.amount ELSE 0 END), 0) as sales
            FROM salespersons s
            LEFT JOIN orders o ON s.id = o.salesperson_id AND o.created_at >= ?
            WHERE s.is_active = TRUE
            GROUP BY s.id, s.name
            ORDER BY sales DESC
        ");
        $stmt->execute([$thisMonth]);
        $allSalespersons = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $rank = 1;
        $totalSalespersons = count($allSalespersons);
        foreach ($allSalespersons as $index => $sp) {
            if ($sp['id'] == $salespersonId) {
                $rank = $index + 1;
                break;
            }
        }
        
        $achievements = [];
        if ($thisMonthStats['total_sales'] >= 100000) {
            $achievements[] = ['icon' => 'trophy', 'title' => 'Top Performer', 'color' => 'gold'];
        }
        if ($thisMonthStats['completed_orders'] >= 10) {
            $achievements[] = ['icon' => 'award', 'title' => '10+ Orders', 'color' => 'silver'];
        }
        if ($conversionRate >= 80) {
            $achievements[] = ['icon' => 'star', 'title' => 'High Converter', 'color' => 'bronze'];
        }
        if ($rank == 1) {
            $achievements[] = ['icon' => 'crown', 'title' => '#1 Salesperson', 'color' => 'gold'];
        }
        
        $salesGrowth = 0;
        if ($lastMonthStats['total_sales'] > 0) {
            $salesGrowth = round((($thisMonthStats['total_sales'] - $lastMonthStats['total_sales']) / $lastMonthStats['total_sales']) * 100, 1);
        }
        
        return [
            'this_month' => $thisMonthStats,
            'conversion_rate' => $conversionRate,
            'rank' => $rank,
            'total_salespersons' => $totalSalespersons,
            'sales_growth' => $salesGrowth,
            'achievements' => $achievements
        ];
    }
    
    public function getTechnicianPerformance(int $userId): array {
        $thisMonth = date('Y-m-01');
        $employee = $this->getEmployeeByUserId($userId);
        
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_tickets,
                COUNT(CASE WHEN status = 'resolved' THEN 1 END) as resolved_tickets,
                COUNT(CASE WHEN status = 'closed' THEN 1 END) as closed_tickets,
                COUNT(CASE WHEN sla_resolution_breached = TRUE THEN 1 END) as sla_breached,
                AVG(CASE WHEN resolved_at IS NOT NULL THEN 
                    EXTRACT(EPOCH FROM (resolved_at - created_at))/3600 
                END) as avg_resolution_hours
            FROM tickets 
            WHERE assigned_to = ? AND created_at >= ?
        ");
        $stmt->execute([$userId, $thisMonth]);
        $thisMonthStats = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        $resolutionRate = $thisMonthStats['total_tickets'] > 0 
            ? round((($thisMonthStats['resolved_tickets'] + $thisMonthStats['closed_tickets']) / $thisMonthStats['total_tickets']) * 100, 1) 
            : 0;
        
        $slaCompliance = $thisMonthStats['total_tickets'] > 0 
            ? round(100 - (($thisMonthStats['sla_breached'] / $thisMonthStats['total_tickets']) * 100), 1) 
            : 100;
        
        $avgResolutionTime = $thisMonthStats['avg_resolution_hours'] 
            ? round($thisMonthStats['avg_resolution_hours'], 1) 
            : null;
        
        $stmt = $this->db->prepare("
            SELECT u.id, u.name, 
                   COUNT(CASE WHEN t.status IN ('resolved', 'closed') THEN 1 END) as resolved
            FROM users u
            LEFT JOIN tickets t ON u.id = t.assigned_to AND t.created_at >= ?
            WHERE u.role IN ('technician', 'admin')
            GROUP BY u.id, u.name
            ORDER BY resolved DESC
        ");
        $stmt->execute([$thisMonth]);
        $allTechnicians = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $rank = 1;
        $totalTechnicians = count($allTechnicians);
        foreach ($allTechnicians as $index => $tech) {
            if ($tech['id'] == $userId) {
                $rank = $index + 1;
                break;
            }
        }
        
        $attendanceRate = 0;
        $attendanceStats = [
            'days_present' => 0,
            'days_late' => 0,
            'days_on_time' => 0,
            'total_hours' => 0,
            'avg_clock_in' => null
        ];
        
        if ($employee) {
            $workingDays = (int)date('t') - 8;
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as present_days,
                    COUNT(CASE WHEN late_minutes > 0 THEN 1 END) as late_days,
                    COUNT(CASE WHEN late_minutes = 0 OR late_minutes IS NULL THEN 1 END) as on_time_days,
                    COALESCE(SUM(hours_worked), 0) as total_hours,
                    AVG(EXTRACT(HOUR FROM clock_in::time) + EXTRACT(MINUTE FROM clock_in::time)/60.0) as avg_clock_in_hour
                FROM attendance 
                WHERE employee_id = ? AND date >= ? AND status = 'present'
            ");
            $stmt->execute([$employee['id'], $thisMonth]);
            $attData = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            $attendanceStats['days_present'] = (int)$attData['present_days'];
            $attendanceStats['days_late'] = (int)$attData['late_days'];
            $attendanceStats['days_on_time'] = (int)$attData['on_time_days'];
            $attendanceStats['total_hours'] = round((float)$attData['total_hours'], 1);
            
            if ($attData['avg_clock_in_hour']) {
                $hours = floor($attData['avg_clock_in_hour']);
                $minutes = round(($attData['avg_clock_in_hour'] - $hours) * 60);
                $attendanceStats['avg_clock_in'] = sprintf('%02d:%02d', $hours, $minutes);
            }
            
            $attendanceRate = $workingDays > 0 ? round(($attData['present_days'] / $workingDays) * 100, 1) : 0;
            $attendanceRate = min(100, $attendanceRate);
        }
        
        $achievements = [];
        if ($resolutionRate >= 90) {
            $achievements[] = ['icon' => 'trophy', 'title' => 'Problem Solver', 'color' => 'gold'];
        }
        if ($slaCompliance >= 95) {
            $achievements[] = ['icon' => 'clock', 'title' => 'SLA Champion', 'color' => 'silver'];
        }
        if ($thisMonthStats['resolved_tickets'] >= 20) {
            $achievements[] = ['icon' => 'star', 'title' => '20+ Resolved', 'color' => 'bronze'];
        }
        if ($rank == 1) {
            $achievements[] = ['icon' => 'award', 'title' => '#1 Technician', 'color' => 'gold'];
        }
        
        $commissionStats = [
            'total_tickets' => 0,
            'total_earnings' => 0,
            'currency' => 'KES'
        ];
        
        if ($employee) {
            $ticketCommission = new \App\TicketCommission($this->db);
            $commissionStats = $ticketCommission->getEmployeeCommissionStats($employee['id']);
            
            if ($commissionStats['total_earnings'] >= 5000) {
                $achievements[] = ['icon' => 'cash', 'title' => 'Top Earner', 'color' => 'gold'];
            }
        }
        
        return [
            'this_month' => $thisMonthStats,
            'resolution_rate' => $resolutionRate,
            'sla_compliance' => $slaCompliance,
            'avg_resolution_hours' => $avgResolutionTime,
            'rank' => $rank,
            'total_technicians' => $totalTechnicians,
            'attendance_rate' => $attendanceRate,
            'attendance_stats' => $attendanceStats,
            'commission' => $commissionStats,
            'achievements' => $achievements
        ];
    }
    
    public function createTicket(int $userId, array $data): ?int {
        $ticketNumber = 'TKT-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        $customerId = null;
        if (!empty($data['customer_id'])) {
            $customerId = (int)$data['customer_id'];
        } elseif (!empty($data['customer_phone'])) {
            $stmt = $this->db->prepare("SELECT id FROM customers WHERE phone = ?");
            $stmt->execute([$data['customer_phone']]);
            $customer = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($customer) {
                $customerId = $customer['id'];
            }
        }
        
        $stmt = $this->db->prepare("
            INSERT INTO tickets (ticket_number, subject, description, category, priority, 
                                customer_id, created_by, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'open')
        ");
        
        $stmt->execute([
            $ticketNumber,
            $data['subject'],
            $data['description'] ?? '',
            $data['category'] ?? 'general',
            $data['priority'] ?? 'medium',
            $customerId,
            $userId
        ]);
        
        return (int) $this->db->lastInsertId();
    }
    
    public function getTicketCategories(): array {
        return [
            ['value' => 'installation', 'label' => 'New Installation'],
            ['value' => 'fault', 'label' => 'Fault/Repair'],
            ['value' => 'relocation', 'label' => 'Relocation'],
            ['value' => 'upgrade', 'label' => 'Package Upgrade'],
            ['value' => 'billing', 'label' => 'Billing Issue'],
            ['value' => 'general', 'label' => 'General Inquiry']
        ];
    }
    
    public function searchCustomers(string $query, int $limit = 30): array {
        $searchTerm = '%' . $query . '%';
        $stmt = $this->db->prepare("
            SELECT id, name, phone, address, email, status
            FROM customers 
            WHERE name ILIKE ? OR phone ILIKE ? OR address ILIKE ? OR email ILIKE ?
            ORDER BY name
            LIMIT ?
        ");
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm, $limit]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getAvailableTickets(int $limit = 50): array {
        $stmt = $this->db->prepare("
            SELECT t.*, c.name as customer_name, c.phone as customer_phone, c.address as customer_address,
                   COALESCE(tcr.rate, 0) as commission_rate
            FROM tickets t
            LEFT JOIN customers c ON t.customer_id = c.id
            LEFT JOIN ticket_commission_rates tcr ON t.category = tcr.category AND tcr.is_active = true
            WHERE t.assigned_to IS NULL AND t.status NOT IN ('resolved', 'closed')
            ORDER BY 
                CASE t.priority 
                    WHEN 'critical' THEN 1 
                    WHEN 'high' THEN 2 
                    WHEN 'medium' THEN 3 
                    ELSE 4 
                END,
                t.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function claimTicket(int $ticketId, int $userId): bool {
        $stmt = $this->db->prepare("
            UPDATE tickets SET assigned_to = ?, updated_at = NOW() 
            WHERE id = ? AND assigned_to IS NULL AND status NOT IN ('resolved', 'closed')
        ");
        $stmt->execute([$userId, $ticketId]);
        return $stmt->rowCount() > 0;
    }
    
    public function getTicketEquipment(int $ticketId): array {
        $stmt = $this->db->prepare("
            SELECT e.*, ea.status as assignment_status, ea.assignment_date,
                   c.name as customer_name
            FROM equipment e
            LEFT JOIN equipment_assignments ea ON e.id = ea.equipment_id
            LEFT JOIN tickets t ON t.customer_id = ea.customer_id
            LEFT JOIN customers c ON ea.customer_id = c.id
            WHERE t.id = ?
            ORDER BY ea.assignment_date DESC
        ");
        $stmt->execute([$ticketId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getTechnicianEquipment(int $userId): array {
        $stmt = $this->db->prepare("
            SELECT e.*, ea.status as assignment_status, ea.assignment_date,
                   c.name as customer_name, c.address as customer_address
            FROM equipment e
            JOIN equipment_assignments ea ON e.id = ea.equipment_id
            LEFT JOIN customers c ON ea.customer_id = c.id
            WHERE ea.assigned_by = ? OR ea.technician_id = ?
            ORDER BY ea.assignment_date DESC
            LIMIT 50
        ");
        $stmt->execute([$userId, $userId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function canAccessTicket(int $ticketId, int $userId, string $userRole): bool {
        if ($userRole === 'admin') {
            return true;
        }
        
        $stmt = $this->db->prepare("
            SELECT id FROM tickets 
            WHERE id = ? AND (assigned_to = ? OR assigned_to IS NULL)
        ");
        $stmt->execute([$ticketId, $userId]);
        return $stmt->fetch() !== false;
    }
    
    public function canModifyTicket(int $ticketId, int $userId, string $userRole): bool {
        if ($userRole === 'admin') {
            return true;
        }
        
        $stmt = $this->db->prepare("
            SELECT id FROM tickets 
            WHERE id = ? AND assigned_to = ?
        ");
        $stmt->execute([$ticketId, $userId]);
        return $stmt->fetch() !== false;
    }
    
    public function getTicketDetailsAny(int $ticketId, int $userId, string $userRole): ?array {
        if (!$this->canAccessTicket($ticketId, $userId, $userRole)) {
            return null;
        }
        
        $stmt = $this->db->prepare("
            SELECT t.*, c.name as customer_name, c.phone as customer_phone, 
                   c.address as customer_address, c.email as customer_email,
                   u.name as assigned_name
            FROM tickets t
            LEFT JOIN customers c ON t.customer_id = c.id
            LEFT JOIN users u ON t.assigned_to = u.id
            WHERE t.id = ?
        ");
        $stmt->execute([$ticketId]);
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
            
            $ticket['equipment'] = $this->getTicketEquipment($ticketId);
        }
        
        return $ticket ?: null;
    }
    
    public function updateTicketStatusAny(int $ticketId, int $userId, string $userRole, string $status, ?string $comment = null): bool {
        if (!$this->canModifyTicket($ticketId, $userId, $userRole)) {
            return false;
        }
        
        $stmt = $this->db->prepare("
            SELECT t.*, c.phone as customer_phone, c.name as customer_name, t.ticket_number
            FROM tickets t
            LEFT JOIN customers c ON t.customer_id = c.id
            WHERE t.id = ?
        ");
        $stmt->execute([$ticketId]);
        $ticket = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        $stmt = $this->db->prepare("UPDATE tickets SET status = ?, updated_at = NOW() WHERE id = ?");
        $result = $stmt->execute([$status, $ticketId]);
        
        if ($result && $comment) {
            $stmt = $this->db->prepare("INSERT INTO ticket_comments (ticket_id, user_id, comment) VALUES (?, ?, ?)");
            $stmt->execute([$ticketId, $userId, $comment]);
        }
        
        if ($status === 'resolved') {
            $stmt = $this->db->prepare("UPDATE tickets SET resolved_at = NOW() WHERE id = ?");
            $stmt->execute([$ticketId]);
        }
        
        if ($result && $ticket && !empty($ticket['customer_phone'])) {
            $this->sendStatusNotification($ticket, $status);
        }
        
        return $result;
    }
    
    public function addTicketCommentAny(int $ticketId, int $userId, string $userRole, string $comment): bool {
        if (!$this->canAccessTicket($ticketId, $userId, $userRole)) {
            return false;
        }
        
        $stmt = $this->db->prepare("INSERT INTO ticket_comments (ticket_id, user_id, comment) VALUES (?, ?, ?)");
        return $stmt->execute([$ticketId, $userId, $comment]);
    }
    
    public function getEmployeeTeams(int $userId): array {
        $employee = $this->getEmployeeByUserId($userId);
        if (!$employee) {
            return [];
        }
        
        $stmt = $this->db->prepare("
            SELECT t.*, 
                   (SELECT COUNT(*) FROM team_members WHERE team_id = t.id) as member_count
            FROM teams t
            JOIN team_members tm ON t.id = tm.team_id
            WHERE tm.employee_id = ? AND t.is_active = true
            ORDER BY t.name
        ");
        $stmt->execute([$employee['id']]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getTeamDetails(int $teamId, int $userId): ?array {
        $employee = $this->getEmployeeByUserId($userId);
        if (!$employee) {
            return null;
        }
        
        $memberCheck = $this->db->prepare("SELECT 1 FROM team_members WHERE team_id = ? AND employee_id = ?");
        $memberCheck->execute([$teamId, $employee['id']]);
        if (!$memberCheck->fetch()) {
            return null;
        }
        
        $stmt = $this->db->prepare("SELECT * FROM teams WHERE id = ?");
        $stmt->execute([$teamId]);
        $team = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$team) {
            return null;
        }
        
        $stmt = $this->db->prepare("
            SELECT e.id, e.name, e.phone, e.email, u.id as user_id, tm.joined_at
            FROM team_members tm
            JOIN employees e ON tm.employee_id = e.id
            LEFT JOIN users u ON e.user_id = u.id
            WHERE tm.team_id = ?
            ORDER BY e.name
        ");
        $stmt->execute([$teamId]);
        $team['members'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $thisMonth = date('Y-m-01');
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_tickets,
                COUNT(CASE WHEN status IN ('resolved', 'closed') THEN 1 END) as completed_tickets
            FROM tickets
            WHERE team_id = ? AND created_at >= ?
        ");
        $stmt->execute([$teamId, $thisMonth]);
        $team['stats'] = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return $team;
    }
    
    public function getTeamTickets(int $teamId, int $userId, string $status = '', int $limit = 50): array {
        $employee = $this->getEmployeeByUserId($userId);
        if (!$employee) {
            return [];
        }
        
        $memberCheck = $this->db->prepare("SELECT 1 FROM team_members WHERE team_id = ? AND employee_id = ?");
        $memberCheck->execute([$teamId, $employee['id']]);
        if (!$memberCheck->fetch()) {
            return [];
        }
        
        $sql = "
            SELECT t.*, c.name as customer_name, c.phone as customer_phone,
                   u.name as assigned_to_name,
                   COALESCE((SELECT SUM(te.earned_amount) FROM ticket_earnings te WHERE te.ticket_id = t.id), 0) as earnings
            FROM tickets t
            LEFT JOIN customers c ON t.customer_id = c.id
            LEFT JOIN users u ON t.assigned_to = u.id
            WHERE t.team_id = ?
        ";
        $params = [$teamId];
        
        if ($status) {
            $sql .= " AND t.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY t.created_at DESC LIMIT ?";
        $params[] = $limit;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getEmployeeEarnings(int $userId, ?string $month = null): array {
        $employee = $this->getEmployeeByUserId($userId);
        if (!$employee) {
            return ['error' => 'Employee not found'];
        }
        
        $month = $month ?? date('Y-m');
        
        $ticketCommission = new TicketCommission($this->db);
        $earnings = $ticketCommission->getEmployeeEarnings($employee['id'], $month);
        $summary = $ticketCommission->getEmployeeEarningsSummary($employee['id'], $month);
        
        return [
            'month' => $month,
            'employee_id' => $employee['id'],
            'employee_name' => $employee['name'],
            'summary' => $summary,
            'earnings' => $earnings
        ];
    }
    
    public function getTeamEarnings(int $teamId, int $userId, ?string $month = null): array {
        $employee = $this->getEmployeeByUserId($userId);
        if (!$employee) {
            return ['error' => 'Employee not found'];
        }
        
        $memberCheck = $this->db->prepare("SELECT 1 FROM team_members WHERE team_id = ? AND employee_id = ?");
        $memberCheck->execute([$teamId, $employee['id']]);
        if (!$memberCheck->fetch()) {
            return ['error' => 'Not a team member'];
        }
        
        $month = $month ?? date('Y-m');
        
        $ticketCommission = new TicketCommission($this->db);
        $earnings = $ticketCommission->getTeamEarnings($teamId, $month);
        
        $startDate = date('Y-m-01', strtotime($month));
        $endDate = date('Y-m-t', strtotime($month));
        
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_tickets,
                COALESCE(SUM(earned_amount), 0) as total_earnings,
                currency
            FROM ticket_earnings
            WHERE team_id = ? 
              AND created_at BETWEEN ? AND ?
              AND status != 'cancelled'
            GROUP BY currency
        ");
        $stmt->execute([$teamId, $startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        $summary = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [
            'total_tickets' => 0,
            'total_earnings' => 0,
            'currency' => 'KES'
        ];
        
        $stmt = $this->db->prepare("SELECT name FROM teams WHERE id = ?");
        $stmt->execute([$teamId]);
        $team = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return [
            'month' => $month,
            'team_id' => $teamId,
            'team_name' => $team['name'] ?? 'Team',
            'summary' => $summary,
            'earnings' => $earnings
        ];
    }
    
    public function getCustomerDetail(int $customerId): ?array {
        $stmt = $this->db->prepare("
            SELECT c.*, sp.name as package_name, sp.speed as package_speed
            FROM customers c
            LEFT JOIN service_packages sp ON c.package_id = sp.id
            WHERE c.id = ?
        ");
        $stmt->execute([$customerId]);
        $customer = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$customer) {
            return null;
        }
        
        $stmt = $this->db->prepare("
            SELECT id, subject, status, priority, created_at
            FROM tickets
            WHERE customer_id = ?
            ORDER BY created_at DESC
            LIMIT 5
        ");
        $stmt->execute([$customerId]);
        $customer['recent_tickets'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $stmt = $this->db->prepare("
            SELECT e.id, e.name, e.serial_number, e.brand, e.model, e.mac_address
            FROM equipment e
            JOIN equipment_assignments ea ON e.id = ea.equipment_id
            WHERE ea.customer_id = ? AND ea.status = 'assigned'
        ");
        $stmt->execute([$customerId]);
        $customer['equipment'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        return $customer;
    }
    
    public function getUserNotifications(int $userId, int $limit = 50): array {
        try {
            $stmt = $this->db->prepare("
                SELECT id, user_id, type, title, message, reference_id, is_read, created_at
                FROM user_notifications
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$userId, $limit]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }
    
    public function markNotificationRead(int $notificationId, int $userId): bool {
        try {
            $stmt = $this->db->prepare("UPDATE user_notifications SET is_read = TRUE WHERE id = ? AND user_id = ?");
            return $stmt->execute([$notificationId, $userId]);
        } catch (\Exception $e) {
            return false;
        }
    }
    
    public function markAllNotificationsRead(int $userId): bool {
        try {
            $stmt = $this->db->prepare("UPDATE user_notifications SET is_read = TRUE WHERE user_id = ?");
            return $stmt->execute([$userId]);
        } catch (\Exception $e) {
            return false;
        }
    }
}
