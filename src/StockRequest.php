<?php

namespace App;

use PDO;
use Exception;

class StockRequest {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function generateNumber(string $prefix = 'SR'): string {
        $stmt = $this->db->query("SELECT COUNT(*) + 1 FROM inventory_stock_requests WHERE EXTRACT(YEAR FROM created_at) = EXTRACT(YEAR FROM CURRENT_DATE)");
        $count = $stmt->fetchColumn();
        return $prefix . date('Y') . '-' . str_pad($count, 5, '0', STR_PAD_LEFT);
    }

    public function getRequests(array $filters = []): array {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'sr.status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['requested_by'])) {
            $where[] = 'sr.requested_by = :requested_by';
            $params['requested_by'] = $filters['requested_by'];
        }

        if (!empty($filters['warehouse_id'])) {
            $where[] = 'sr.warehouse_id = :warehouse_id';
            $params['warehouse_id'] = $filters['warehouse_id'];
        }

        if (!empty($filters['request_type'])) {
            $where[] = 'sr.request_type = :request_type';
            $params['request_type'] = $filters['request_type'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'sr.created_at >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'sr.created_at <= :date_to';
            $params['date_to'] = $filters['date_to'] . ' 23:59:59';
        }

        $sql = "SELECT sr.*, w.name as warehouse_name,
                u1.name as requested_by_name,
                u2.name as approved_by_name,
                u3.name as picked_by_name,
                u4.name as handed_to_name,
                t.subject as ticket_title,
                c.name as customer_name,
                (SELECT COUNT(*) FROM inventory_stock_request_items WHERE request_id = sr.id) as item_count
                FROM inventory_stock_requests sr
                LEFT JOIN inventory_warehouses w ON sr.warehouse_id = w.id
                LEFT JOIN users u1 ON sr.requested_by = u1.id
                LEFT JOIN users u2 ON sr.approved_by = u2.id
                LEFT JOIN users u3 ON sr.picked_by = u3.id
                LEFT JOIN users u4 ON sr.handed_to = u4.id
                LEFT JOIN tickets t ON sr.ticket_id = t.id
                LEFT JOIN customers c ON sr.customer_id = c.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY sr.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRequest(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT sr.*, w.name as warehouse_name,
                u1.name as requested_by_name,
                u2.name as approved_by_name,
                u3.name as picked_by_name,
                u4.name as handed_to_name,
                t.subject as ticket_title,
                c.name as customer_name
            FROM inventory_stock_requests sr
            LEFT JOIN inventory_warehouses w ON sr.warehouse_id = w.id
            LEFT JOIN users u1 ON sr.requested_by = u1.id
            LEFT JOIN users u2 ON sr.approved_by = u2.id
            LEFT JOIN users u3 ON sr.picked_by = u3.id
            LEFT JOIN users u4 ON sr.handed_to = u4.id
            LEFT JOIN tickets t ON sr.ticket_id = t.id
            LEFT JOIN customers c ON sr.customer_id = c.id
            WHERE sr.id = :id
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getRequestItems(int $requestId): array {
        $stmt = $this->db->prepare("
            SELECT sri.*, e.name as equipment_name, e.serial_number, e.mac_address,
                   ec.name as category_name
            FROM inventory_stock_request_items sri
            LEFT JOIN equipment e ON sri.equipment_id = e.id
            LEFT JOIN equipment_categories ec ON sri.category_id = ec.id
            WHERE sri.request_id = :request_id
            ORDER BY sri.id
        ");
        $stmt->execute(['request_id' => $requestId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createRequest(array $data, array $items): int {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                INSERT INTO inventory_stock_requests 
                (request_number, requested_by, warehouse_id, request_type, ticket_id, customer_id, priority, required_date, notes)
                VALUES (:request_number, :requested_by, :warehouse_id, :request_type, :ticket_id, :customer_id, :priority, :required_date, :notes)
                RETURNING id
            ");
            $stmt->execute([
                'request_number' => $this->generateNumber(),
                'requested_by' => $data['requested_by'],
                'warehouse_id' => $data['warehouse_id'] ?: null,
                'request_type' => $data['request_type'] ?? 'technician',
                'ticket_id' => $data['ticket_id'] ?: null,
                'customer_id' => $data['customer_id'] ?: null,
                'priority' => $data['priority'] ?? 'normal',
                'required_date' => $data['required_date'] ?: null,
                'notes' => $data['notes'] ?? null
            ]);
            $requestId = (int) $stmt->fetchColumn();

            $itemStmt = $this->db->prepare("
                INSERT INTO inventory_stock_request_items 
                (request_id, equipment_id, category_id, item_name, quantity_requested, notes)
                VALUES (:request_id, :equipment_id, :category_id, :item_name, :quantity_requested, :notes)
            ");
            foreach ($items as $item) {
                $itemStmt->execute([
                    'request_id' => $requestId,
                    'equipment_id' => $item['equipment_id'] ?: null,
                    'category_id' => $item['category_id'] ?: null,
                    'item_name' => $item['item_name'],
                    'quantity_requested' => $item['quantity_requested'] ?? 1,
                    'notes' => $item['notes'] ?? null
                ]);
            }

            $this->db->commit();
            return $requestId;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function approveRequest(int $id, int $approvedBy, array $approvedQuantities = []): bool {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                UPDATE inventory_stock_requests 
                SET status = 'approved', approved_by = :approved_by, approved_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            $stmt->execute(['id' => $id, 'approved_by' => $approvedBy]);

            foreach ($approvedQuantities as $itemId => $qty) {
                $stmt = $this->db->prepare("
                    UPDATE inventory_stock_request_items 
                    SET quantity_approved = :qty WHERE id = :id
                ");
                $stmt->execute(['id' => $itemId, 'qty' => $qty]);
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function rejectRequest(int $id, int $rejectedBy, ?string $reason = null): bool {
        $stmt = $this->db->prepare("
            UPDATE inventory_stock_requests 
            SET status = 'rejected', approved_by = :rejected_by, approved_at = CURRENT_TIMESTAMP, 
                notes = CONCAT(COALESCE(notes, ''), E'\n\nRejection Reason: ', :reason),
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");
        return $stmt->execute(['id' => $id, 'rejected_by' => $rejectedBy, 'reason' => $reason ?? 'No reason provided']);
    }

    public function pickItems(int $id, int $pickedBy, array $pickedQuantities, array $equipmentIds = []): bool {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                UPDATE inventory_stock_requests 
                SET status = 'picked', picked_by = :picked_by, picked_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            $stmt->execute(['id' => $id, 'picked_by' => $pickedBy]);

            foreach ($pickedQuantities as $itemId => $qty) {
                $equipmentId = $equipmentIds[$itemId] ?? null;
                $stmt = $this->db->prepare("
                    UPDATE inventory_stock_request_items 
                    SET quantity_picked = :qty, equipment_id = COALESCE(:equipment_id, equipment_id)
                    WHERE id = :id
                ");
                $stmt->execute(['id' => $itemId, 'qty' => $qty, 'equipment_id' => $equipmentId]);
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function handover(int $id, int $handedTo, ?string $signature = null): bool {
        $stmt = $this->db->prepare("
            UPDATE inventory_stock_requests 
            SET status = 'handed_over', handed_to = :handed_to, handover_at = CURRENT_TIMESTAMP, 
                handover_signature = :signature, updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");
        return $stmt->execute(['id' => $id, 'handed_to' => $handedTo, 'signature' => $signature]);
    }

    public function recordUsage(int $requestItemId, int $ticketId, int $customerId, int $employeeId, int $quantity, string $jobType, int $recordedBy, ?string $notes = null): int {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("SELECT equipment_id FROM inventory_stock_request_items WHERE id = :id");
            $stmt->execute(['id' => $requestItemId]);
            $equipmentId = $stmt->fetchColumn();

            $stmt = $this->db->prepare("
                INSERT INTO inventory_usage 
                (equipment_id, request_item_id, ticket_id, customer_id, employee_id, job_type, quantity, usage_date, notes, recorded_by)
                VALUES (:equipment_id, :request_item_id, :ticket_id, :customer_id, :employee_id, :job_type, :quantity, CURRENT_DATE, :notes, :recorded_by)
                RETURNING id
            ");
            $stmt->execute([
                'equipment_id' => $equipmentId,
                'request_item_id' => $requestItemId,
                'ticket_id' => $ticketId,
                'customer_id' => $customerId,
                'employee_id' => $employeeId,
                'job_type' => $jobType,
                'quantity' => $quantity,
                'notes' => $notes,
                'recorded_by' => $recordedBy
            ]);
            $usageId = (int) $stmt->fetchColumn();

            $stmt = $this->db->prepare("
                UPDATE inventory_stock_request_items 
                SET quantity_used = quantity_used + :qty WHERE id = :id
            ");
            $stmt->execute(['id' => $requestItemId, 'qty' => $quantity]);

            $this->db->commit();
            return $usageId;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function getUsageHistory(array $filters = []): array {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['employee_id'])) {
            $where[] = 'u.employee_id = :employee_id';
            $params['employee_id'] = $filters['employee_id'];
        }

        if (!empty($filters['ticket_id'])) {
            $where[] = 'u.ticket_id = :ticket_id';
            $params['ticket_id'] = $filters['ticket_id'];
        }

        if (!empty($filters['customer_id'])) {
            $where[] = 'u.customer_id = :customer_id';
            $params['customer_id'] = $filters['customer_id'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'u.usage_date >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'u.usage_date <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }

        $sql = "SELECT u.*, e.name as equipment_name, e.serial_number,
                emp.first_name || ' ' || emp.last_name as employee_name,
                t.subject as ticket_title,
                c.name as customer_name,
                usr.name as recorded_by_name
                FROM inventory_usage u
                LEFT JOIN equipment e ON u.equipment_id = e.id
                LEFT JOIN employees emp ON u.employee_id = emp.id
                LEFT JOIN tickets t ON u.ticket_id = t.id
                LEFT JOIN customers c ON u.customer_id = c.id
                LEFT JOIN users usr ON u.recorded_by = usr.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY u.usage_date DESC, u.id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRequestTypes(): array {
        return [
            'technician' => 'Technician Request',
            'installation' => 'Installation Job',
            'maintenance' => 'Maintenance Work',
            'replacement' => 'Equipment Replacement',
            'loan' => 'Customer Loan'
        ];
    }

    public function getStatuses(): array {
        return [
            'pending' => 'Pending Approval',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'picked' => 'Picked',
            'handed_over' => 'Handed Over',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled'
        ];
    }

    public function completeRequest(int $id): bool {
        $stmt = $this->db->prepare("
            UPDATE inventory_stock_requests 
            SET status = 'completed', updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");
        return $stmt->execute(['id' => $id]);
    }

    public function cancelRequest(int $id, ?string $reason = null): bool {
        $stmt = $this->db->prepare("
            UPDATE inventory_stock_requests 
            SET status = 'cancelled', 
                notes = CONCAT(COALESCE(notes, ''), E'\n\nCancellation Reason: ', :reason),
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");
        return $stmt->execute(['id' => $id, 'reason' => $reason ?? 'No reason provided']);
    }
}
