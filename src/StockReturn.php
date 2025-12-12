<?php

class StockReturn {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function generateReturnNumber(): string {
        $stmt = $this->db->query("SELECT COUNT(*) + 1 FROM inventory_returns WHERE EXTRACT(YEAR FROM created_at) = EXTRACT(YEAR FROM CURRENT_DATE)");
        $count = $stmt->fetchColumn();
        return 'RET' . date('Y') . '-' . str_pad($count, 5, '0', STR_PAD_LEFT);
    }

    public function generateRmaNumber(): string {
        $stmt = $this->db->query("SELECT COUNT(*) + 1 FROM inventory_rma WHERE EXTRACT(YEAR FROM created_at) = EXTRACT(YEAR FROM CURRENT_DATE)");
        $count = $stmt->fetchColumn();
        return 'RMA' . date('Y') . '-' . str_pad($count, 5, '0', STR_PAD_LEFT);
    }

    public function getReturns(array $filters = []): array {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'r.status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['warehouse_id'])) {
            $where[] = 'r.warehouse_id = :warehouse_id';
            $params['warehouse_id'] = $filters['warehouse_id'];
        }

        if (!empty($filters['return_type'])) {
            $where[] = 'r.return_type = :return_type';
            $params['return_type'] = $filters['return_type'];
        }

        $sql = "SELECT r.*, w.name as warehouse_name,
                u1.name as returned_by_name,
                u2.name as received_by_name,
                (SELECT COUNT(*) FROM inventory_return_items WHERE return_id = r.id) as item_count
                FROM inventory_returns r
                LEFT JOIN inventory_warehouses w ON r.warehouse_id = w.id
                LEFT JOIN users u1 ON r.returned_by = u1.id
                LEFT JOIN users u2 ON r.received_by = u2.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY r.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getReturn(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT r.*, w.name as warehouse_name,
                u1.name as returned_by_name,
                u2.name as received_by_name
            FROM inventory_returns r
            LEFT JOIN inventory_warehouses w ON r.warehouse_id = w.id
            LEFT JOIN users u1 ON r.returned_by = u1.id
            LEFT JOIN users u2 ON r.received_by = u2.id
            WHERE r.id = :id
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getReturnItems(int $returnId): array {
        $stmt = $this->db->prepare("
            SELECT ri.*, e.name as equipment_name, e.serial_number, e.mac_address,
                   l.name as location_name
            FROM inventory_return_items ri
            LEFT JOIN equipment e ON ri.equipment_id = e.id
            LEFT JOIN inventory_locations l ON ri.location_id = l.id
            WHERE ri.return_id = :return_id
            ORDER BY ri.id
        ");
        $stmt->execute(['return_id' => $returnId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createReturn(array $data, array $items): int {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                INSERT INTO inventory_returns 
                (return_number, request_id, returned_by, warehouse_id, return_date, return_type, notes)
                VALUES (:return_number, :request_id, :returned_by, :warehouse_id, :return_date, :return_type, :notes)
                RETURNING id
            ");
            $stmt->execute([
                'return_number' => $this->generateReturnNumber(),
                'request_id' => $data['request_id'] ?: null,
                'returned_by' => $data['returned_by'],
                'warehouse_id' => $data['warehouse_id'],
                'return_date' => $data['return_date'] ?? date('Y-m-d'),
                'return_type' => $data['return_type'] ?? 'unused',
                'notes' => $data['notes'] ?? null
            ]);
            $returnId = (int) $stmt->fetchColumn();

            $itemStmt = $this->db->prepare("
                INSERT INTO inventory_return_items 
                (return_id, equipment_id, request_item_id, quantity, condition, location_id, notes)
                VALUES (:return_id, :equipment_id, :request_item_id, :quantity, :condition, :location_id, :notes)
            ");
            foreach ($items as $item) {
                $itemStmt->execute([
                    'return_id' => $returnId,
                    'equipment_id' => $item['equipment_id'] ?: null,
                    'request_item_id' => $item['request_item_id'] ?: null,
                    'quantity' => $item['quantity'] ?? 1,
                    'condition' => $item['condition'] ?? 'good',
                    'location_id' => $item['location_id'] ?: null,
                    'notes' => $item['notes'] ?? null
                ]);
            }

            $this->db->commit();
            return $returnId;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function receiveReturn(int $id, int $receivedBy): bool {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                UPDATE inventory_returns 
                SET status = 'received', received_by = :received_by, received_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            $stmt->execute(['id' => $id, 'received_by' => $receivedBy]);

            $items = $this->getReturnItems($id);
            $return = $this->getReturn($id);

            foreach ($items as $item) {
                if ($item['equipment_id']) {
                    $stmt = $this->db->prepare("
                        UPDATE equipment 
                        SET warehouse_id = :warehouse_id, location_id = :location_id, status = :status
                        WHERE id = :id
                    ");
                    $stmt->execute([
                        'id' => $item['equipment_id'],
                        'warehouse_id' => $return['warehouse_id'],
                        'location_id' => $item['location_id'],
                        'status' => $item['condition'] === 'good' ? 'available' : 'faulty'
                    ]);

                    $stmt = $this->db->prepare("
                        INSERT INTO inventory_stock_movements 
                        (equipment_id, movement_type, to_warehouse_id, to_location_id, quantity, reference_type, reference_id, performed_by)
                        VALUES (:equipment_id, 'return', :warehouse_id, :location_id, :quantity, 'return', :return_id, :performed_by)
                    ");
                    $stmt->execute([
                        'equipment_id' => $item['equipment_id'],
                        'warehouse_id' => $return['warehouse_id'],
                        'location_id' => $item['location_id'],
                        'quantity' => $item['quantity'],
                        'return_id' => $id,
                        'performed_by' => $receivedBy
                    ]);
                }

                if ($item['request_item_id']) {
                    $stmt = $this->db->prepare("
                        UPDATE inventory_stock_request_items 
                        SET quantity_returned = quantity_returned + :qty WHERE id = :id
                    ");
                    $stmt->execute(['id' => $item['request_item_id'], 'qty' => $item['quantity']]);
                }
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function getRMAs(array $filters = []): array {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'rma.status = :status';
            $params['status'] = $filters['status'];
        }

        $sql = "SELECT rma.*, e.name as equipment_name, e.serial_number, e.mac_address,
                u.name as created_by_name
                FROM inventory_rma rma
                LEFT JOIN equipment e ON rma.equipment_id = e.id
                LEFT JOIN users u ON rma.created_by = u.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY rma.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRMA(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT rma.*, e.name as equipment_name, e.serial_number, e.mac_address, e.brand,
                   ef.description as fault_description,
                   u.name as created_by_name,
                   re.name as replacement_name, re.serial_number as replacement_serial
            FROM inventory_rma rma
            LEFT JOIN equipment e ON rma.equipment_id = e.id
            LEFT JOIN equipment_faults ef ON rma.fault_id = ef.id
            LEFT JOIN users u ON rma.created_by = u.id
            LEFT JOIN equipment re ON rma.replacement_equipment_id = re.id
            WHERE rma.id = :id
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function createRMA(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO inventory_rma 
            (rma_number, equipment_id, fault_id, vendor_name, vendor_contact, created_by)
            VALUES (:rma_number, :equipment_id, :fault_id, :vendor_name, :vendor_contact, :created_by)
            RETURNING id
        ");
        $stmt->execute([
            'rma_number' => $this->generateRmaNumber(),
            'equipment_id' => $data['equipment_id'],
            'fault_id' => $data['fault_id'] ?: null,
            'vendor_name' => $data['vendor_name'] ?? null,
            'vendor_contact' => $data['vendor_contact'] ?? null,
            'created_by' => $data['created_by']
        ]);

        $stmt = $this->db->prepare("UPDATE equipment SET status = 'rma' WHERE id = :id");
        $stmt->execute(['id' => $data['equipment_id']]);

        return (int) $stmt->fetchColumn();
    }

    public function updateRMA(int $id, array $data): bool {
        $stmt = $this->db->prepare("
            UPDATE inventory_rma SET
                vendor_name = :vendor_name,
                vendor_contact = :vendor_contact,
                status = :status,
                shipped_date = :shipped_date,
                received_date = :received_date,
                resolution = :resolution,
                resolution_notes = :resolution_notes,
                replacement_equipment_id = :replacement_equipment_id,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");
        $result = $stmt->execute([
            'id' => $id,
            'vendor_name' => $data['vendor_name'] ?? null,
            'vendor_contact' => $data['vendor_contact'] ?? null,
            'status' => $data['status'] ?? 'pending',
            'shipped_date' => $data['shipped_date'] ?: null,
            'received_date' => $data['received_date'] ?: null,
            'resolution' => $data['resolution'] ?? null,
            'resolution_notes' => $data['resolution_notes'] ?? null,
            'replacement_equipment_id' => $data['replacement_equipment_id'] ?: null
        ]);

        if ($data['status'] === 'resolved' && $data['resolution'] === 'repaired') {
            $rma = $this->getRMA($id);
            $stmt = $this->db->prepare("UPDATE equipment SET status = 'available' WHERE id = :id");
            $stmt->execute(['id' => $rma['equipment_id']]);
        }

        return $result;
    }

    public function getLossReports(array $filters = []): array {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['investigation_status'])) {
            $where[] = 'lr.investigation_status = :status';
            $params['status'] = $filters['investigation_status'];
        }

        if (!empty($filters['loss_type'])) {
            $where[] = 'lr.loss_type = :loss_type';
            $params['loss_type'] = $filters['loss_type'];
        }

        $sql = "SELECT lr.*, e.name as equipment_name, e.serial_number,
                emp.first_name || ' ' || emp.last_name as employee_name,
                u1.name as reported_by_name,
                u2.name as resolved_by_name
                FROM inventory_loss_reports lr
                LEFT JOIN equipment e ON lr.equipment_id = e.id
                LEFT JOIN employees emp ON lr.employee_id = emp.id
                LEFT JOIN users u1 ON lr.reported_by = u1.id
                LEFT JOIN users u2 ON lr.resolved_by = u2.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY lr.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function generateLossReportNumber(): string {
        $stmt = $this->db->query("SELECT COUNT(*) + 1 FROM inventory_loss_reports WHERE EXTRACT(YEAR FROM created_at) = EXTRACT(YEAR FROM CURRENT_DATE)");
        $count = $stmt->fetchColumn();
        return 'LOSS' . date('Y') . '-' . str_pad($count, 5, '0', STR_PAD_LEFT);
    }

    public function createLossReport(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO inventory_loss_reports 
            (report_number, equipment_id, reported_by, employee_id, loss_type, loss_date, description, estimated_value)
            VALUES (:report_number, :equipment_id, :reported_by, :employee_id, :loss_type, :loss_date, :description, :estimated_value)
            RETURNING id
        ");
        $stmt->execute([
            'report_number' => $this->generateLossReportNumber(),
            'equipment_id' => $data['equipment_id'] ?: null,
            'reported_by' => $data['reported_by'],
            'employee_id' => $data['employee_id'] ?: null,
            'loss_type' => $data['loss_type'] ?? 'lost',
            'loss_date' => $data['loss_date'] ?? date('Y-m-d'),
            'description' => $data['description'],
            'estimated_value' => $data['estimated_value'] ?: null
        ]);

        if ($data['equipment_id']) {
            $stmt = $this->db->prepare("UPDATE equipment SET status = 'lost' WHERE id = :id");
            $stmt->execute(['id' => $data['equipment_id']]);
        }

        return (int) $stmt->fetchColumn();
    }

    public function getReturnTypes(): array {
        return [
            'unused' => 'Unused Stock',
            'defective' => 'Defective/Faulty',
            'customer' => 'From Customer',
            'job_complete' => 'Job Complete - Excess',
            'recalled' => 'Recalled'
        ];
    }

    public function getRmaStatuses(): array {
        return [
            'pending' => 'Pending',
            'shipped' => 'Shipped to Vendor',
            'in_repair' => 'In Repair',
            'resolved' => 'Resolved',
            'cancelled' => 'Cancelled'
        ];
    }

    public function getRmaResolutions(): array {
        return [
            'repaired' => 'Repaired',
            'replaced' => 'Replaced',
            'refunded' => 'Refunded',
            'no_fault' => 'No Fault Found',
            'unrepairable' => 'Unrepairable'
        ];
    }

    public function getLossTypes(): array {
        return [
            'lost' => 'Lost',
            'stolen' => 'Stolen',
            'damaged' => 'Damaged Beyond Repair',
            'missing' => 'Missing/Unaccounted'
        ];
    }
}
