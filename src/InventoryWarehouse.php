<?php

class InventoryWarehouse {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function getWarehouses(array $filters = []): array {
        $where = ['1=1'];
        $params = [];

        if (isset($filters['is_active'])) {
            $where[] = 'w.is_active = :is_active';
            $params['is_active'] = $filters['is_active'];
        }

        if (!empty($filters['type'])) {
            $where[] = 'w.type = :type';
            $params['type'] = $filters['type'];
        }

        if (!empty($filters['search'])) {
            $where[] = '(w.name ILIKE :search OR w.code ILIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }

        $sql = "SELECT w.*, u.name as manager_name,
                (SELECT COUNT(*) FROM inventory_locations WHERE warehouse_id = w.id) as location_count,
                (SELECT COUNT(*) FROM equipment WHERE warehouse_id = w.id) as equipment_count
                FROM inventory_warehouses w
                LEFT JOIN users u ON w.manager_id = u.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY w.name";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getWarehouse(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT w.*, u.name as manager_name
            FROM inventory_warehouses w
            LEFT JOIN users u ON w.manager_id = u.id
            WHERE w.id = :id
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function createWarehouse(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO inventory_warehouses (name, code, type, address, phone, manager_id, is_active, notes)
            VALUES (:name, :code, :type, :address, :phone, :manager_id, :is_active, :notes)
            RETURNING id
        ");
        $stmt->execute([
            'name' => $data['name'],
            'code' => strtoupper($data['code']),
            'type' => $data['type'] ?? 'depot',
            'address' => $data['address'] ?? null,
            'phone' => $data['phone'] ?? null,
            'manager_id' => $data['manager_id'] ?: null,
            'is_active' => $data['is_active'] ?? true,
            'notes' => $data['notes'] ?? null
        ]);
        return (int) $stmt->fetchColumn();
    }

    public function updateWarehouse(int $id, array $data): bool {
        $stmt = $this->db->prepare("
            UPDATE inventory_warehouses SET
                name = :name,
                code = :code,
                type = :type,
                address = :address,
                phone = :phone,
                manager_id = :manager_id,
                is_active = :is_active,
                notes = :notes,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");
        return $stmt->execute([
            'id' => $id,
            'name' => $data['name'],
            'code' => strtoupper($data['code']),
            'type' => $data['type'] ?? 'depot',
            'address' => $data['address'] ?? null,
            'phone' => $data['phone'] ?? null,
            'manager_id' => $data['manager_id'] ?: null,
            'is_active' => $data['is_active'] ?? true,
            'notes' => $data['notes'] ?? null
        ]);
    }

    public function deleteWarehouse(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM inventory_warehouses WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    public function getLocations(int $warehouseId = null): array {
        $where = ['1=1'];
        $params = [];

        if ($warehouseId) {
            $where[] = 'l.warehouse_id = :warehouse_id';
            $params['warehouse_id'] = $warehouseId;
        }

        $sql = "SELECT l.*, w.name as warehouse_name, w.code as warehouse_code,
                (SELECT COUNT(*) FROM equipment WHERE location_id = l.id) as equipment_count
                FROM inventory_locations l
                LEFT JOIN inventory_warehouses w ON l.warehouse_id = w.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY w.name, l.name";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLocation(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT l.*, w.name as warehouse_name
            FROM inventory_locations l
            LEFT JOIN inventory_warehouses w ON l.warehouse_id = w.id
            WHERE l.id = :id
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function createLocation(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO inventory_locations (warehouse_id, name, code, type, capacity, notes, is_active)
            VALUES (:warehouse_id, :name, :code, :type, :capacity, :notes, :is_active)
            RETURNING id
        ");
        $stmt->execute([
            'warehouse_id' => $data['warehouse_id'],
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
            'type' => $data['type'] ?? 'shelf',
            'capacity' => $data['capacity'] ?: null,
            'notes' => $data['notes'] ?? null,
            'is_active' => $data['is_active'] ?? true
        ]);
        return (int) $stmt->fetchColumn();
    }

    public function updateLocation(int $id, array $data): bool {
        $stmt = $this->db->prepare("
            UPDATE inventory_locations SET
                warehouse_id = :warehouse_id,
                name = :name,
                code = :code,
                type = :type,
                capacity = :capacity,
                notes = :notes,
                is_active = :is_active
            WHERE id = :id
        ");
        return $stmt->execute([
            'id' => $id,
            'warehouse_id' => $data['warehouse_id'],
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
            'type' => $data['type'] ?? 'shelf',
            'capacity' => $data['capacity'] ?: null,
            'notes' => $data['notes'] ?? null,
            'is_active' => $data['is_active'] ?? true
        ]);
    }

    public function deleteLocation(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM inventory_locations WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    public function getWarehouseTypes(): array {
        return [
            'main' => 'Main Warehouse',
            'depot' => 'Regional Depot',
            'vehicle' => 'Vehicle/Van Stock',
            'technician' => 'Technician Kit',
            'customer' => 'Customer Premises'
        ];
    }

    public function getLocationTypes(): array {
        return [
            'shelf' => 'Shelf',
            'bin' => 'Bin',
            'rack' => 'Rack',
            'zone' => 'Zone',
            'room' => 'Room',
            'cabinet' => 'Cabinet'
        ];
    }

    public function getStockByWarehouse(int $warehouseId): array {
        $stmt = $this->db->prepare("
            SELECT ec.name as category, COUNT(*) as count, 
                   SUM(COALESCE(e.quantity, 1)) as total_qty
            FROM equipment e
            JOIN equipment_categories ec ON e.category_id = ec.id
            WHERE e.warehouse_id = :warehouse_id
            GROUP BY ec.id, ec.name
            ORDER BY ec.name
        ");
        $stmt->execute(['warehouse_id' => $warehouseId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getStockByLocation(int $locationId): array {
        $stmt = $this->db->prepare("
            SELECT e.*, ec.name as category_name
            FROM equipment e
            LEFT JOIN equipment_categories ec ON e.category_id = ec.id
            WHERE e.location_id = :location_id
            ORDER BY ec.name, e.name
        ");
        $stmt->execute(['location_id' => $locationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function moveEquipment(int $equipmentId, int $toWarehouseId, int $toLocationId = null, int $performedBy = null, string $notes = null): bool {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("SELECT warehouse_id, location_id FROM equipment WHERE id = :id");
            $stmt->execute(['id' => $equipmentId]);
            $current = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $this->db->prepare("
                INSERT INTO inventory_stock_movements 
                (equipment_id, movement_type, from_warehouse_id, to_warehouse_id, from_location_id, to_location_id, performed_by, notes)
                VALUES (:equipment_id, 'transfer', :from_warehouse, :to_warehouse, :from_location, :to_location, :performed_by, :notes)
            ");
            $stmt->execute([
                'equipment_id' => $equipmentId,
                'from_warehouse' => $current['warehouse_id'],
                'to_warehouse' => $toWarehouseId,
                'from_location' => $current['location_id'],
                'to_location' => $toLocationId,
                'performed_by' => $performedBy,
                'notes' => $notes
            ]);

            $stmt = $this->db->prepare("
                UPDATE equipment SET warehouse_id = :warehouse_id, location_id = :location_id WHERE id = :id
            ");
            $stmt->execute([
                'id' => $equipmentId,
                'warehouse_id' => $toWarehouseId,
                'location_id' => $toLocationId
            ]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function getMovementHistory(int $equipmentId = null, int $warehouseId = null, int $limit = 50): array {
        $where = ['1=1'];
        $params = ['limit' => $limit];

        if ($equipmentId) {
            $where[] = 'm.equipment_id = :equipment_id';
            $params['equipment_id'] = $equipmentId;
        }

        if ($warehouseId) {
            $where[] = '(m.from_warehouse_id = :warehouse_id OR m.to_warehouse_id = :warehouse_id2)';
            $params['warehouse_id'] = $warehouseId;
            $params['warehouse_id2'] = $warehouseId;
        }

        $sql = "SELECT m.*, e.name as equipment_name, e.serial_number,
                fw.name as from_warehouse_name, tw.name as to_warehouse_name,
                fl.name as from_location_name, tl.name as to_location_name,
                u.name as performed_by_name
                FROM inventory_stock_movements m
                LEFT JOIN equipment e ON m.equipment_id = e.id
                LEFT JOIN inventory_warehouses fw ON m.from_warehouse_id = fw.id
                LEFT JOIN inventory_warehouses tw ON m.to_warehouse_id = tw.id
                LEFT JOIN inventory_locations fl ON m.from_location_id = fl.id
                LEFT JOIN inventory_locations tl ON m.to_location_id = tl.id
                LEFT JOIN users u ON m.performed_by = u.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY m.created_at DESC
                LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
