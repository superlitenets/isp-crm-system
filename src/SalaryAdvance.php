<?php

namespace App;

class SalaryAdvance {
    private \PDO $db;
    
    public function __construct(\PDO $db) {
        $this->db = $db;
    }
    
    public function getAll(array $filters = []): array {
        $sql = "SELECT sa.*, 
                    COALESCE(sa.approved_amount, sa.requested_amount) as amount,
                    sa.outstanding_balance as balance,
                    sa.repayment_schedule as repayment_type,
                    sa.installments as repayment_installments,
                    CASE WHEN sa.installments > 0 THEN COALESCE(sa.approved_amount, sa.requested_amount) / sa.installments ELSE 0 END as repayment_amount,
                    e.name as employee_name, e.employee_id as employee_code,
                    u.name as approved_by_name
                FROM salary_advances sa
                LEFT JOIN employees e ON sa.employee_id = e.id
                LEFT JOIN users u ON sa.approved_by = u.id
                WHERE 1=1";
        $params = [];
        
        if (!empty($filters['status'])) {
            $sql .= " AND sa.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['employee_id'])) {
            $sql .= " AND sa.employee_id = ?";
            $params[] = $filters['employee_id'];
        }
        
        
        $sql .= " ORDER BY sa.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function getById(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT sa.*, 
                COALESCE(sa.approved_amount, sa.requested_amount) as amount,
                sa.outstanding_balance as balance,
                sa.repayment_schedule as repayment_type,
                sa.installments as repayment_installments,
                CASE WHEN sa.installments > 0 THEN COALESCE(sa.approved_amount, sa.requested_amount) / sa.installments ELSE 0 END as repayment_amount,
                e.name as employee_name, e.employee_id as employee_code,
                u.name as approved_by_name
            FROM salary_advances sa
            LEFT JOIN employees e ON sa.employee_id = e.id
            LEFT JOIN users u ON sa.approved_by = u.id
            WHERE sa.id = ?
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    public function getByEmployee(int $employeeId): array {
        $stmt = $this->db->prepare("
            SELECT sa.*
            FROM salary_advances sa
            WHERE sa.employee_id = ?
            ORDER BY sa.created_at DESC
        ");
        $stmt->execute([$employeeId]);
        return $stmt->fetchAll();
    }
    
    public function create(array $data): int {
        $installments = $data['repayment_installments'] ?? $data['installments'] ?? 1;
        $amount = $data['amount'] ?? $data['requested_amount'];
        
        $stmt = $this->db->prepare("
            INSERT INTO salary_advances (employee_id, requested_amount, repayment_schedule, installments, outstanding_balance, notes, status)
            VALUES (?, ?, ?, ?, ?, ?, 'pending')
        ");
        
        $stmt->execute([
            $data['employee_id'],
            $amount,
            $data['repayment_type'] ?? $data['repayment_schedule'] ?? 'monthly',
            $installments,
            $amount,
            $data['reason'] ?? $data['notes'] ?? null
        ]);
        
        return (int)$this->db->lastInsertId();
    }
    
    public function approve(int $id, int $approvedBy): bool {
        $stmt = $this->db->prepare("
            UPDATE salary_advances 
            SET status = 'approved', approved_by = ?, approved_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
            WHERE id = ? AND status = 'pending'
        ");
        return $stmt->execute([$approvedBy, $id]);
    }
    
    public function reject(int $id, int $rejectedBy, ?string $notes = null): bool {
        $stmt = $this->db->prepare("
            UPDATE salary_advances 
            SET status = 'rejected', approved_by = ?, approved_at = CURRENT_TIMESTAMP, notes = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ? AND status = 'pending'
        ");
        return $stmt->execute([$rejectedBy, $notes, $id]);
    }
    
    public function disburse(int $id): bool {
        $advance = $this->getById($id);
        if (!$advance || $advance['status'] !== 'approved') {
            throw new \Exception('Advance must be approved before disbursement');
        }
        
        $stmt = $this->db->prepare("
            UPDATE salary_advances 
            SET status = 'disbursed', disbursed_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        return $stmt->execute([$id]);
    }
    
    public function recordPayment(int $advanceId, array $data): int {
        $this->db->beginTransaction();
        try {
            $advance = $this->getById($advanceId);
            if (!$advance) {
                throw new \Exception('Advance not found');
            }
            
            $stmt = $this->db->prepare("
                INSERT INTO salary_advance_payments (advance_id, amount, payment_type, payment_date, reference_number, notes, recorded_by, payroll_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $advanceId,
                $data['amount'],
                $data['payment_type'] ?? 'payroll_deduction',
                $data['payment_date'] ?? date('Y-m-d'),
                $data['reference_number'] ?? null,
                $data['notes'] ?? null,
                $data['recorded_by'] ?? null,
                $data['payroll_id'] ?? null
            ]);
            $paymentId = (int)$this->db->lastInsertId();
            
            $currentBalance = $advance['balance'] ?? $advance['outstanding_balance'] ?? $advance['amount'];
            $newBalance = $currentBalance - $data['amount'];
            $newStatus = $newBalance <= 0 ? 'completed' : 'repaying';
            
            $stmt = $this->db->prepare("
                UPDATE salary_advances 
                SET outstanding_balance = ?, status = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([max(0, $newBalance), $newStatus, $advanceId]);
            
            $this->db->commit();
            return $paymentId;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    public function getPayments(int $advanceId): array {
        $stmt = $this->db->prepare("
            SELECT sap.*, u.name as recorded_by_name
            FROM salary_advance_payments sap
            LEFT JOIN users u ON sap.recorded_by = u.id
            WHERE sap.advance_id = ?
            ORDER BY sap.payment_date DESC
        ");
        $stmt->execute([$advanceId]);
        return $stmt->fetchAll();
    }
    
    public function getPendingDeductions(): array {
        $stmt = $this->db->prepare("
            SELECT sa.*, e.name as employee_name, e.employee_id as employee_code
            FROM salary_advances sa
            JOIN employees e ON sa.employee_id = e.id
            WHERE sa.status IN ('disbursed', 'repaying')
            AND sa.outstanding_balance > 0
            AND (sa.next_deduction_date IS NULL OR sa.next_deduction_date <= CURRENT_DATE)
            ORDER BY sa.employee_id
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    public function getEmployeeActiveAdvances(int $employeeId): array {
        $stmt = $this->db->prepare("
            SELECT * FROM salary_advances 
            WHERE employee_id = ? AND status IN ('approved', 'disbursed', 'repaying')
            ORDER BY created_at DESC
        ");
        $stmt->execute([$employeeId]);
        return $stmt->fetchAll();
    }
    
    public function getEmployeeTotalOutstanding(int $employeeId): float {
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(outstanding_balance), 0) as total
            FROM salary_advances 
            WHERE employee_id = ? AND status IN ('disbursed', 'repaying')
        ");
        $stmt->execute([$employeeId]);
        return (float)$stmt->fetchColumn();
    }
    
    public function cancel(int $id): bool {
        $stmt = $this->db->prepare("
            UPDATE salary_advances 
            SET status = 'cancelled', updated_at = CURRENT_TIMESTAMP
            WHERE id = ? AND status = 'pending'
        ");
        return $stmt->execute([$id]);
    }
    
    private function calculateNextDeductionDate(string $repaymentType): string {
        $date = new \DateTime();
        
        switch ($repaymentType) {
            case 'weekly':
                $date->modify('+1 week');
                break;
            case 'bi-weekly':
                $date->modify('+2 weeks');
                break;
            case 'monthly':
            default:
                $date->modify('first day of next month');
                break;
        }
        
        return $date->format('Y-m-d');
    }
    
    public function getStatistics(): array {
        $stmt = $this->db->query("
            SELECT 
                COUNT(*) FILTER (WHERE status = 'pending') as pending_count,
                COUNT(*) FILTER (WHERE status IN ('disbursed', 'repaying')) as active_count,
                COUNT(*) FILTER (WHERE status = 'completed') as completed_count,
                COALESCE(SUM(COALESCE(approved_amount, requested_amount)) FILTER (WHERE status IN ('disbursed', 'repaying')), 0) as total_outstanding,
                COALESCE(SUM(outstanding_balance) FILTER (WHERE status IN ('disbursed', 'repaying')), 0) as total_balance
            FROM salary_advances
        ");
        return $stmt->fetch();
    }
}
