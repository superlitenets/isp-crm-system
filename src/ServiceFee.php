<?php

namespace App;

class ServiceFee {
    private \PDO $db;
    
    public function __construct(\PDO $db = null) {
        $this->db = $db ?? \Database::getConnection();
    }
    
    public function getFeeTypes(bool $activeOnly = true): array {
        $sql = "SELECT * FROM service_fee_types";
        if ($activeOnly) {
            $sql .= " WHERE is_active = TRUE";
        }
        $sql .= " ORDER BY display_order, name";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getFeeType(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM service_fee_types WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    
    public function createFeeType(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO service_fee_types (name, description, default_amount, currency, is_active, display_order)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['name'],
            $data['description'] ?? null,
            $data['default_amount'] ?? 0,
            $data['currency'] ?? 'KES',
            isset($data['is_active']) ? (bool)$data['is_active'] : true,
            $data['display_order'] ?? 0
        ]);
        return (int)$this->db->lastInsertId();
    }
    
    public function updateFeeType(int $id, array $data): bool {
        $fields = [];
        $params = [];
        
        $allowedFields = ['name', 'description', 'default_amount', 'currency', 'is_active', 'display_order'];
        
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($fields)) return false;
        
        $fields[] = "updated_at = CURRENT_TIMESTAMP";
        $params[] = $id;
        
        $stmt = $this->db->prepare("UPDATE service_fee_types SET " . implode(', ', $fields) . " WHERE id = ?");
        return $stmt->execute($params);
    }
    
    public function deleteFeeType(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM service_fee_types WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    public function addTicketFee(int $ticketId, array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO ticket_service_fees (ticket_id, fee_type_id, fee_name, amount, currency, notes, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $ticketId,
            $data['fee_type_id'] ?: null,
            $data['fee_name'],
            $data['amount'] ?? 0,
            $data['currency'] ?? 'KES',
            $data['notes'] ?? null,
            $data['created_by'] ?? null
        ]);
        return (int)$this->db->lastInsertId();
    }
    
    public function getTicketFees(int $ticketId): array {
        $stmt = $this->db->prepare("
            SELECT tsf.*, sft.name as type_name, u.name as created_by_name
            FROM ticket_service_fees tsf
            LEFT JOIN service_fee_types sft ON tsf.fee_type_id = sft.id
            LEFT JOIN users u ON tsf.created_by = u.id
            WHERE tsf.ticket_id = ?
            ORDER BY tsf.created_at
        ");
        $stmt->execute([$ticketId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getTicketFeesTotal(int $ticketId): array {
        $stmt = $this->db->prepare("
            SELECT 
                COALESCE(SUM(amount), 0) as total,
                COALESCE(SUM(CASE WHEN is_paid = TRUE THEN amount END), 0) as paid,
                COALESCE(SUM(CASE WHEN is_paid = FALSE THEN amount END), 0) as unpaid,
                COUNT(*) as fee_count
            FROM ticket_service_fees
            WHERE ticket_id = ?
        ");
        $stmt->execute([$ticketId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: ['total' => 0, 'paid' => 0, 'unpaid' => 0, 'fee_count' => 0];
    }
    
    public function updateTicketFee(int $feeId, array $data): bool {
        $fields = [];
        $params = [];
        
        $allowedFields = ['fee_name', 'amount', 'notes', 'is_paid', 'paid_at', 'payment_reference'];
        
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($fields)) return false;
        
        $params[] = $feeId;
        
        $stmt = $this->db->prepare("UPDATE ticket_service_fees SET " . implode(', ', $fields) . " WHERE id = ?");
        return $stmt->execute($params);
    }
    
    public function markAsPaid(int $feeId, ?string $reference = null): bool {
        $stmt = $this->db->prepare("
            UPDATE ticket_service_fees 
            SET is_paid = TRUE, paid_at = CURRENT_TIMESTAMP, payment_reference = ?
            WHERE id = ?
        ");
        return $stmt->execute([$reference, $feeId]);
    }
    
    public function deleteTicketFee(int $feeId): bool {
        $stmt = $this->db->prepare("DELETE FROM ticket_service_fees WHERE id = ?");
        return $stmt->execute([$feeId]);
    }
    
    public function clearTicketFees(int $ticketId): bool {
        $stmt = $this->db->prepare("DELETE FROM ticket_service_fees WHERE ticket_id = ?");
        return $stmt->execute([$ticketId]);
    }
    
    public function syncTicketFees(int $ticketId, array $feeData, ?int $createdBy = null): void {
        $this->clearTicketFees($ticketId);
        
        foreach ($feeData as $data) {
            $feeTypeId = (int)($data['fee_type_id'] ?? 0);
            if ($feeTypeId <= 0) continue;
            
            $feeType = $this->getFeeType($feeTypeId);
            if (!$feeType) continue;
            
            $amount = isset($data['amount']) && $data['amount'] > 0 
                ? (float)$data['amount'] 
                : $feeType['default_amount'];
            
            $this->addTicketFee($ticketId, [
                'fee_type_id' => $feeTypeId,
                'fee_name' => $feeType['name'],
                'amount' => $amount,
                'currency' => $feeType['currency'] ?? 'KES',
                'created_by' => $createdBy
            ]);
        }
    }
    
    public function getUnpaidFeesByCustomer(int $customerId): array {
        $stmt = $this->db->prepare("
            SELECT tsf.*, t.ticket_number, t.subject
            FROM ticket_service_fees tsf
            JOIN tickets t ON tsf.ticket_id = t.id
            WHERE t.customer_id = ? AND tsf.is_paid = FALSE
            ORDER BY tsf.created_at DESC
        ");
        $stmt->execute([$customerId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getFeesReport(array $filters = []): array {
        $sql = "SELECT tsf.*, t.ticket_number, t.subject, c.name as customer_name,
                       sft.name as type_name
                FROM ticket_service_fees tsf
                JOIN tickets t ON tsf.ticket_id = t.id
                LEFT JOIN customers c ON t.customer_id = c.id
                LEFT JOIN service_fee_types sft ON tsf.fee_type_id = sft.id
                WHERE 1=1";
        $params = [];
        
        if (!empty($filters['start_date'])) {
            $sql .= " AND tsf.created_at >= ?";
            $params[] = $filters['start_date'];
        }
        
        if (!empty($filters['end_date'])) {
            $sql .= " AND tsf.created_at <= ?";
            $params[] = $filters['end_date'] . ' 23:59:59';
        }
        
        if (isset($filters['is_paid'])) {
            $sql .= " AND tsf.is_paid = ?";
            $params[] = (bool)$filters['is_paid'];
        }
        
        $sql .= " ORDER BY tsf.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
