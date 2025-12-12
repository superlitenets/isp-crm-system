<?php

namespace App;

use PDO;

class Salesperson {
    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? \Database::getConnection();
    }
    
    public function markAllCommissionsPaid(int $salespersonId): bool {
        $stmt = $this->db->prepare("
            UPDATE sales_commissions 
            SET status = 'paid', paid_at = CURRENT_TIMESTAMP 
            WHERE salesperson_id = ? AND status = 'pending'
        ");
        $result = $stmt->execute([$salespersonId]);
        $this->updateTotals($salespersonId);
        return $result;
    }

    public function getAll(): array {
        $stmt = $this->db->query("
            SELECT s.*, 
                   e.name as employee_name,
                   u.name as user_name,
                   (SELECT COUNT(*) FROM orders WHERE salesperson_id = s.id) as order_count
            FROM salespersons s
            LEFT JOIN employees e ON s.employee_id = e.id
            LEFT JOIN users u ON s.user_id = u.id
            ORDER BY s.name ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getActive(): array {
        $stmt = $this->db->query("
            SELECT s.*, 
                   e.name as employee_name,
                   u.name as user_name
            FROM salespersons s
            LEFT JOIN employees e ON s.employee_id = e.id
            LEFT JOIN users u ON s.user_id = u.id
            WHERE s.is_active = TRUE
            ORDER BY s.name ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT s.*, 
                   e.name as employee_name,
                   u.name as user_name
            FROM salespersons s
            LEFT JOIN employees e ON s.employee_id = e.id
            LEFT JOIN users u ON s.user_id = u.id
            WHERE s.id = ?
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function create(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO salespersons (employee_id, user_id, name, email, phone, commission_type, commission_value, is_active, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            RETURNING id
        ");
        $stmt->execute([
            $data['employee_id'] ?: null,
            $data['user_id'] ?: null,
            $data['name'],
            $data['email'] ?? null,
            $data['phone'],
            $data['commission_type'] ?? 'percentage',
            $data['commission_value'] ?? 0,
            isset($data['is_active']) ? ($data['is_active'] ? true : false) : true,
            $data['notes'] ?? null
        ]);
        return (int) $stmt->fetchColumn();
    }

    public function update(int $id, array $data): bool {
        $stmt = $this->db->prepare("
            UPDATE salespersons 
            SET name = ?, email = ?, phone = ?, commission_type = ?, commission_value = ?,
                employee_id = ?, user_id = ?, is_active = ?, notes = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        return $stmt->execute([
            $data['name'],
            $data['email'] ?? null,
            $data['phone'],
            $data['commission_type'] ?? 'percentage',
            $data['commission_value'] ?? 0,
            $data['employee_id'] ?: null,
            $data['user_id'] ?: null,
            isset($data['is_active']) ? ($data['is_active'] ? true : false) : true,
            $data['notes'] ?? null,
            $id
        ]);
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM salespersons WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function getOrdersBySalesperson(int $salespersonId, ?string $startDate = null, ?string $endDate = null): array {
        $sql = "
            SELECT o.*, 
                   sp.name as package_name,
                   sp.price as package_price,
                   sc.commission_amount,
                   sc.status as commission_status
            FROM orders o
            LEFT JOIN service_packages sp ON o.package_id = sp.id
            LEFT JOIN sales_commissions sc ON sc.order_id = o.id
            WHERE o.salesperson_id = ?
        ";
        $params = [$salespersonId];
        
        if ($startDate) {
            $sql .= " AND o.created_at >= ?";
            $params[] = $startDate;
        }
        if ($endDate) {
            $sql .= " AND o.created_at <= ?";
            $params[] = $endDate . ' 23:59:59';
        }
        
        $sql .= " ORDER BY o.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCommissions(int $salespersonId, ?string $status = null): array {
        $sql = "
            SELECT sc.*, 
                   o.order_number,
                   o.customer_name,
                   o.created_at as order_date
            FROM sales_commissions sc
            JOIN orders o ON sc.order_id = o.id
            WHERE sc.salesperson_id = ?
        ";
        $params = [$salespersonId];
        
        if ($status) {
            $sql .= " AND sc.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY sc.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createCommission(int $salespersonId, int $orderId, float $orderAmount): ?int {
        $salesperson = $this->getById($salespersonId);
        if (!$salesperson) {
            return null;
        }

        $commissionType = $salesperson['commission_type'];
        $commissionRate = (float) $salesperson['commission_value'];
        
        if ($commissionType === 'percentage') {
            $commissionAmount = ($orderAmount * $commissionRate) / 100;
        } else {
            $commissionAmount = $commissionRate;
        }

        $stmt = $this->db->prepare("
            INSERT INTO sales_commissions (salesperson_id, order_id, order_amount, commission_type, commission_rate, commission_amount, status)
            VALUES (?, ?, ?, ?, ?, ?, 'pending')
            RETURNING id
        ");
        $stmt->execute([
            $salespersonId,
            $orderId,
            $orderAmount,
            $commissionType,
            $commissionRate,
            $commissionAmount
        ]);

        $this->updateTotals($salespersonId);

        return (int) $stmt->fetchColumn();
    }

    public function markCommissionPaid(int $commissionId): bool {
        $stmt = $this->db->prepare("
            UPDATE sales_commissions 
            SET status = 'paid', paid_at = CURRENT_TIMESTAMP 
            WHERE id = ?
            RETURNING salesperson_id
        ");
        $stmt->execute([$commissionId]);
        $salespersonId = $stmt->fetchColumn();
        
        if ($salespersonId) {
            $this->updateTotals($salespersonId);
            return true;
        }
        return false;
    }

    public function updateTotals(int $salespersonId): void {
        $stmt = $this->db->prepare("
            UPDATE salespersons 
            SET total_sales = COALESCE((SELECT SUM(order_amount) FROM sales_commissions WHERE salesperson_id = ?), 0),
                total_commission = COALESCE((SELECT SUM(commission_amount) FROM sales_commissions WHERE salesperson_id = ?), 0),
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([$salespersonId, $salespersonId, $salespersonId]);
    }

    public function getSalesStats(int $salespersonId): array {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_orders,
                COALESCE(SUM(order_amount), 0) as total_sales,
                COALESCE(SUM(commission_amount), 0) as total_commission,
                COALESCE(SUM(CASE WHEN status = 'pending' THEN commission_amount ELSE 0 END), 0) as pending_commission,
                COALESCE(SUM(CASE WHEN status = 'paid' THEN commission_amount ELSE 0 END), 0) as paid_commission
            FROM sales_commissions
            WHERE salesperson_id = ?
        ");
        $stmt->execute([$salespersonId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getLeaderboard(?string $period = 'month'): array {
        $dateCondition = '';
        if ($period === 'week') {
            $dateCondition = "AND sc.created_at >= CURRENT_DATE - INTERVAL '7 days'";
        } elseif ($period === 'month') {
            $dateCondition = "AND sc.created_at >= CURRENT_DATE - INTERVAL '30 days'";
        } elseif ($period === 'year') {
            $dateCondition = "AND sc.created_at >= CURRENT_DATE - INTERVAL '365 days'";
        }

        $stmt = $this->db->query("
            SELECT s.id, s.name, s.phone,
                   COUNT(sc.id) as order_count,
                   COALESCE(SUM(sc.order_amount), 0) as total_sales,
                   COALESCE(SUM(sc.commission_amount), 0) as total_commission
            FROM salespersons s
            LEFT JOIN sales_commissions sc ON s.id = sc.salesperson_id $dateCondition
            WHERE s.is_active = TRUE
            GROUP BY s.id, s.name, s.phone
            ORDER BY total_sales DESC
            LIMIT 10
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDefaultCommission(): array {
        $stmt = $this->db->prepare("
            SELECT setting_value FROM company_settings WHERE setting_key = 'default_commission_type'
        ");
        $stmt->execute();
        $type = $stmt->fetchColumn() ?: 'percentage';

        $stmt = $this->db->prepare("
            SELECT setting_value FROM company_settings WHERE setting_key = 'default_commission_value'
        ");
        $stmt->execute();
        $value = $stmt->fetchColumn() ?: '10';

        return [
            'type' => $type,
            'value' => (float) $value
        ];
    }

    public function getByEmployeeId(int $employeeId): ?array {
        $stmt = $this->db->prepare("
            SELECT s.*, 
                   e.name as employee_name,
                   u.name as user_name
            FROM salespersons s
            LEFT JOIN employees e ON s.employee_id = e.id
            LEFT JOIN users u ON s.user_id = u.id
            WHERE s.employee_id = ?
        ");
        $stmt->execute([$employeeId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function getByUserId(int $userId): ?array {
        $stmt = $this->db->prepare("
            SELECT s.*, 
                   e.name as employee_name,
                   u.name as user_name
            FROM salespersons s
            LEFT JOIN employees e ON s.employee_id = e.id
            LEFT JOIN users u ON s.user_id = u.id
            WHERE s.user_id = ?
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function getEmployeeSalesMetrics(int $employeeId, ?string $periodStart = null, ?string $periodEnd = null): array {
        $salesperson = $this->getByEmployeeId($employeeId);
        
        if (!$salesperson) {
            return [
                'is_salesperson' => false,
                'total_orders' => 0,
                'total_sales' => 0,
                'total_commission' => 0,
                'avg_order_value' => 0,
                'rank' => null,
                'period_orders' => 0,
                'period_sales' => 0,
                'period_commission' => 0
            ];
        }
        
        $allTimeStats = $this->getSalesStats($salesperson['id']);
        
        $periodSql = "
            SELECT 
                COUNT(*) as period_orders,
                COALESCE(SUM(order_amount), 0) as period_sales,
                COALESCE(SUM(commission_amount), 0) as period_commission
            FROM sales_commissions
            WHERE salesperson_id = ?
        ";
        $params = [$salesperson['id']];
        
        if ($periodStart) {
            $periodSql .= " AND created_at >= ?";
            $params[] = $periodStart;
        }
        if ($periodEnd) {
            $periodSql .= " AND created_at <= ?";
            $params[] = $periodEnd . ' 23:59:59';
        }
        
        $stmt = $this->db->prepare($periodSql);
        $stmt->execute($params);
        $periodStats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $rankStmt = $this->db->query("
            SELECT id, RANK() OVER (ORDER BY total_sales DESC) as rank
            FROM salespersons
            WHERE is_active = TRUE
        ");
        $rankings = $rankStmt->fetchAll(PDO::FETCH_KEY_PAIR);
        $rank = $rankings[$salesperson['id']] ?? null;
        
        $avgOrderValue = $allTimeStats['total_orders'] > 0 
            ? $allTimeStats['total_sales'] / $allTimeStats['total_orders'] 
            : 0;
        
        return [
            'is_salesperson' => true,
            'salesperson_id' => $salesperson['id'],
            'salesperson_name' => $salesperson['name'],
            'total_orders' => (int) $allTimeStats['total_orders'],
            'total_sales' => (float) $allTimeStats['total_sales'],
            'total_commission' => (float) $allTimeStats['total_commission'],
            'pending_commission' => (float) $allTimeStats['pending_commission'],
            'paid_commission' => (float) $allTimeStats['paid_commission'],
            'avg_order_value' => $avgOrderValue,
            'rank' => $rank,
            'period_orders' => (int) ($periodStats['period_orders'] ?? 0),
            'period_sales' => (float) ($periodStats['period_sales'] ?? 0),
            'period_commission' => (float) ($periodStats['period_commission'] ?? 0)
        ];
    }
}
