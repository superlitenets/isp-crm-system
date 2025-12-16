<?php

namespace App;

class TicketCommission {
    private \PDO $db;
    
    public function __construct(\PDO $db) {
        $this->db = $db;
    }
    
    public function getCommissionRates(): array {
        $stmt = $this->db->query("SELECT * FROM ticket_commission_rates ORDER BY category");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getActiveRates(): array {
        $stmt = $this->db->query("SELECT * FROM ticket_commission_rates WHERE is_active = true ORDER BY category");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getRateByCategory(string $category): ?array {
        $stmt = $this->db->prepare("SELECT * FROM ticket_commission_rates WHERE category = ? AND is_active = true");
        $stmt->execute([$category]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    
    public function addRate(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO ticket_commission_rates (category, rate, currency, description, is_active, require_sla_compliance)
            VALUES (?, ?, ?, ?, ?, ?)
            ON CONFLICT (category) DO UPDATE SET
                rate = EXCLUDED.rate,
                currency = EXCLUDED.currency,
                description = EXCLUDED.description,
                is_active = EXCLUDED.is_active,
                require_sla_compliance = EXCLUDED.require_sla_compliance,
                updated_at = CURRENT_TIMESTAMP
        ");
        
        $stmt->execute([
            $data['category'],
            $data['rate'] ?? 0,
            $data['currency'] ?? 'KES',
            $data['description'] ?? null,
            isset($data['is_active']) ? (bool)$data['is_active'] : true,
            isset($data['require_sla_compliance']) ? (bool)$data['require_sla_compliance'] : false
        ]);
        
        return (int)$this->db->lastInsertId();
    }
    
    public function updateRate(int $id, array $data): bool {
        $fields = [];
        $params = [];
        
        foreach (['category', 'rate', 'currency', 'description', 'is_active', 'require_sla_compliance'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $fields[] = "updated_at = CURRENT_TIMESTAMP";
        $params[] = $id;
        
        $stmt = $this->db->prepare("UPDATE ticket_commission_rates SET " . implode(', ', $fields) . " WHERE id = ?");
        return $stmt->execute($params);
    }
    
    public function deleteRate(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM ticket_commission_rates WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    public function processTicketClosure(int $ticketId): array {
        $result = [
            'success' => false,
            'message' => '',
            'earnings' => [],
            'sla_compliant' => true,
            'sla_note' => null
        ];
        
        $stmt = $this->db->prepare("
            SELECT t.*, 
                   t.assigned_to as user_id,
                   t.team_id,
                   t.sla_response_breached,
                   t.sla_resolution_breached
            FROM tickets t
            WHERE t.id = ?
        ");
        $stmt->execute([$ticketId]);
        $ticket = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$ticket) {
            $result['message'] = 'Ticket not found';
            return $result;
        }
        
        $existing = $this->db->prepare("SELECT id FROM ticket_earnings WHERE ticket_id = ?");
        $existing->execute([$ticketId]);
        if ($existing->fetch()) {
            $result['message'] = 'Commission already processed for this ticket';
            return $result;
        }
        
        $rate = $this->getRateByCategory($ticket['category']);
        if (!$rate || $rate['rate'] <= 0) {
            $result['message'] = 'No commission rate defined for category: ' . $ticket['category'];
            return $result;
        }
        
        $slaCompliant = true;
        $slaNote = null;
        if (!empty($rate['require_sla_compliance'])) {
            $responseBreached = $ticket['sla_response_breached'] ?? false;
            $resolutionBreached = $ticket['sla_resolution_breached'] ?? false;
            
            if ($responseBreached || $resolutionBreached) {
                $slaCompliant = false;
                $breachTypes = [];
                if ($responseBreached) $breachTypes[] = 'response';
                if ($resolutionBreached) $breachTypes[] = 'resolution';
                $slaNote = 'SLA breached: ' . implode(' and ', $breachTypes);
                
                $result['success'] = false;
                $result['sla_compliant'] = false;
                $result['sla_note'] = $slaNote;
                $result['message'] = 'Commission denied - SLA not met. ' . $slaNote;
                return $result;
            }
        }
        
        $result['sla_compliant'] = $slaCompliant;
        $result['sla_note'] = $slaNote;
        
        $fullRate = (float)$rate['rate'];
        $currency = $rate['currency'];
        
        if ($ticket['team_id']) {
            $members = $this->getTeamMembers($ticket['team_id']);
            
            if (empty($members)) {
                $result['message'] = 'No active team members found';
                return $result;
            }
            
            $shareCount = count($members);
            $earnedAmount = $fullRate / $shareCount;
            
            foreach ($members as $member) {
                $this->createEarning([
                    'ticket_id' => $ticketId,
                    'employee_id' => $member['employee_id'],
                    'team_id' => $ticket['team_id'],
                    'category' => $ticket['category'],
                    'full_rate' => $fullRate,
                    'earned_amount' => $earnedAmount,
                    'share_count' => $shareCount,
                    'currency' => $currency,
                    'sla_compliant' => $slaCompliant,
                    'sla_note' => $slaNote
                ]);
                
                $result['earnings'][] = [
                    'employee_id' => $member['employee_id'],
                    'employee_name' => $member['employee_name'],
                    'amount' => $earnedAmount
                ];
            }
            
            $result['success'] = true;
            $result['message'] = "Commission split among $shareCount team members";
        } else {
            $employeeId = $this->resolveEmployeeId($ticket['user_id']);
            
            if (!$employeeId) {
                $result['message'] = 'Assigned user does not have an employee record. Please link user to employee in HR.';
                return $result;
            }
            
            $this->createEarning([
                'ticket_id' => $ticketId,
                'employee_id' => $employeeId,
                'team_id' => null,
                'category' => $ticket['category'],
                'full_rate' => $fullRate,
                'earned_amount' => $fullRate,
                'share_count' => 1,
                'currency' => $currency,
                'sla_compliant' => $slaCompliant,
                'sla_note' => $slaNote
            ]);
            
            $result['success'] = true;
            $result['message'] = 'Commission assigned to individual';
            $result['earnings'][] = [
                'employee_id' => $employeeId,
                'amount' => $fullRate
            ];
        }
        
        return $result;
    }
    
    private function resolveEmployeeId(?int $userId): ?int {
        if (!$userId) {
            return null;
        }
        
        $stmt = $this->db->prepare("
            SELECT id FROM employees 
            WHERE user_id = ? AND employment_status = 'active'
        ");
        $stmt->execute([$userId]);
        $employee = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return $employee ? (int)$employee['id'] : null;
    }
    
    private function createEarning(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO ticket_earnings (ticket_id, employee_id, team_id, category, full_rate, earned_amount, share_count, currency, status, sla_compliant, sla_note)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?)
        ");
        
        $stmt->execute([
            $data['ticket_id'],
            $data['employee_id'],
            $data['team_id'],
            $data['category'],
            $data['full_rate'],
            $data['earned_amount'],
            $data['share_count'],
            $data['currency'],
            $data['sla_compliant'] ?? true,
            $data['sla_note'] ?? null
        ]);
        
        return (int)$this->db->lastInsertId();
    }
    
    private function getTeamMembers(int $teamId): array {
        $stmt = $this->db->prepare("
            SELECT DISTINCT tm.employee_id, e.name as employee_name
            FROM team_members tm
            JOIN employees e ON tm.employee_id = e.id
            WHERE tm.team_id = ? AND e.employment_status = 'active'
        ");
        $stmt->execute([$teamId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getEmployeeEarnings(int $employeeId, ?string $month = null): array {
        $sql = "
            SELECT te.*, t.ticket_number, t.subject, t.category as ticket_category,
                   tm.name as team_name
            FROM ticket_earnings te
            JOIN tickets t ON te.ticket_id = t.id
            LEFT JOIN teams tm ON te.team_id = tm.id
            WHERE te.employee_id = ?
        ";
        $params = [$employeeId];
        
        if ($month) {
            $startDate = date('Y-m-01', strtotime($month));
            $endDate = date('Y-m-t', strtotime($month));
            $sql .= " AND te.created_at BETWEEN ? AND ?";
            $params[] = $startDate . ' 00:00:00';
            $params[] = $endDate . ' 23:59:59';
        }
        
        $sql .= " ORDER BY te.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getEmployeeEarningsSummary(int $employeeId, string $month): array {
        $startDate = date('Y-m-01', strtotime($month));
        $endDate = date('Y-m-t', strtotime($month));
        
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_tickets,
                COALESCE(SUM(earned_amount), 0) as total_earnings,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
                COUNT(CASE WHEN status IN ('paid', 'processed') THEN 1 END) as paid,
                COALESCE(MAX(currency), 'KES') as currency
            FROM ticket_earnings
            WHERE employee_id = ? 
              AND created_at BETWEEN ? AND ?
              AND status != 'cancelled'
        ");
        $stmt->execute([$employeeId, $startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$result || !$result['total_tickets']) {
            return [
                'total_tickets' => 0,
                'total_earnings' => 0,
                'pending' => 0,
                'paid' => 0,
                'currency' => 'KES'
            ];
        }
        
        return $result;
    }
    
    public function getPendingEarnings(int $employeeId): array {
        $stmt = $this->db->prepare("
            SELECT te.*, t.ticket_number, t.subject
            FROM ticket_earnings te
            JOIN tickets t ON te.ticket_id = t.id
            WHERE te.employee_id = ? AND te.status = 'pending'
            ORDER BY te.created_at DESC
        ");
        $stmt->execute([$employeeId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function applyToPayroll(int $payrollId, int $employeeId, string $month): bool {
        $startDate = date('Y-m-01', strtotime($month));
        $endDate = date('Y-m-t', strtotime($month));
        
        $stmt = $this->db->prepare("
            SELECT id, earned_amount 
            FROM ticket_earnings 
            WHERE employee_id = ? 
              AND status = 'pending' 
              AND created_at BETWEEN ? AND ?
        ");
        $stmt->execute([$employeeId, $startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        $pendingEarnings = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        if (empty($pendingEarnings)) {
            return true;
        }
        
        $earningIds = array_column($pendingEarnings, 'id');
        $totalAmount = array_sum(array_column($pendingEarnings, 'earned_amount'));
        $ticketCount = count($pendingEarnings);
        
        try {
            $this->db->beginTransaction();
            
            $this->db->prepare("DELETE FROM payroll_commissions WHERE payroll_id = ? AND employee_id = ? AND commission_type = 'ticket'")->execute([$payrollId, $employeeId]);
            
            $stmt = $this->db->prepare("
                INSERT INTO payroll_commissions (payroll_id, employee_id, commission_type, description, amount, details)
                VALUES (?, ?, 'ticket', ?, ?, ?)
            ");
            
            $description = "Ticket commission ({$ticketCount} tickets)";
            
            $stmt->execute([
                $payrollId,
                $employeeId,
                $description,
                $totalAmount,
                json_encode(['earning_ids' => $earningIds, 'month' => $month])
            ]);
            
            $placeholders = implode(',', array_fill(0, count($earningIds), '?'));
            $updateStmt = $this->db->prepare("
                UPDATE ticket_earnings 
                SET status = 'paid', payroll_id = ?
                WHERE id IN ($placeholders) AND status = 'pending'
            ");
            $updateStmt->execute(array_merge([$payrollId], $earningIds));
            
            $updatePayroll = $this->db->prepare("
                UPDATE payroll 
                SET allowances = COALESCE(allowances, 0) + ?,
                    net_pay = COALESCE(net_pay, 0) + ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $updatePayroll->execute([$totalAmount, $totalAmount, $payrollId]);
            
            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    public function getTeamEarnings(int $teamId, ?string $month = null): array {
        $sql = "
            SELECT te.*, t.ticket_number, t.subject, e.name as employee_name
            FROM ticket_earnings te
            JOIN tickets t ON te.ticket_id = t.id
            JOIN employees e ON te.employee_id = e.id
            WHERE te.team_id = ?
        ";
        $params = [$teamId];
        
        if ($month) {
            $startDate = date('Y-m-01', strtotime($month));
            $endDate = date('Y-m-t', strtotime($month));
            $sql .= " AND te.created_at BETWEEN ? AND ?";
            $params[] = $startDate . ' 00:00:00';
            $params[] = $endDate . ' 23:59:59';
        }
        
        $sql .= " ORDER BY te.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getAllEarnings(string $month): array {
        $startDate = date('Y-m-01', strtotime($month));
        $endDate = date('Y-m-t', strtotime($month));
        
        $stmt = $this->db->prepare("
            SELECT te.*, t.ticket_number, t.subject, t.category as ticket_category,
                   e.name as employee_name, tm.name as team_name, c.name as customer_name
            FROM ticket_earnings te
            JOIN tickets t ON te.ticket_id = t.id
            JOIN employees e ON te.employee_id = e.id
            LEFT JOIN teams tm ON te.team_id = tm.id
            LEFT JOIN customers c ON t.customer_id = c.id
            WHERE te.created_at BETWEEN ? AND ?
            ORDER BY te.created_at DESC
        ");
        $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getAllEarningsSummary(string $month): array {
        $startDate = date('Y-m-01', strtotime($month));
        $endDate = date('Y-m-t', strtotime($month));
        
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_tickets,
                COALESCE(SUM(earned_amount), 0) as total_earnings,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
                COUNT(CASE WHEN status IN ('paid', 'processed') THEN 1 END) as paid,
                COUNT(DISTINCT employee_id) as employees_count,
                COALESCE(MAX(currency), 'KES') as currency
            FROM ticket_earnings
            WHERE created_at BETWEEN ? AND ?
              AND status != 'cancelled'
        ");
        $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$result || !$result['total_tickets']) {
            return [
                'total_tickets' => 0,
                'total_earnings' => 0,
                'pending' => 0,
                'paid' => 0,
                'employees_count' => 0,
                'currency' => 'KES'
            ];
        }
        
        return $result;
    }
    
    public function getEmployeeCommissionStats(int $employeeId): array {
        $thisMonth = date('Y-m-01');
        
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_tickets,
                COALESCE(SUM(earned_amount), 0) as total_earnings,
                COALESCE(MAX(currency), 'KES') as currency
            FROM ticket_earnings
            WHERE employee_id = ? 
              AND created_at >= ?
              AND status != 'cancelled'
        ");
        $stmt->execute([$employeeId, $thisMonth]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return $result ?: ['total_tickets' => 0, 'total_earnings' => 0, 'currency' => 'KES'];
    }
    
    public function seedDefaultRates(): void {
        $defaultRates = [
            ['category' => 'installation', 'rate' => 200, 'description' => 'New customer installation'],
            ['category' => 'los', 'rate' => 100, 'description' => 'Loss of Signal repair'],
            ['category' => 'relocation', 'rate' => 150, 'description' => 'Customer relocation'],
            ['category' => 'upgrade', 'rate' => 100, 'description' => 'Package upgrade'],
            ['category' => 'maintenance', 'rate' => 50, 'description' => 'General maintenance'],
            ['category' => 'complaint', 'rate' => 50, 'description' => 'Customer complaint resolution'],
            ['category' => 'support', 'rate' => 25, 'description' => 'Technical support']
        ];
        
        foreach ($defaultRates as $rate) {
            $this->addRate($rate);
        }
    }
}
