<?php

namespace App;

class ISPInventory {
    private \PDO $db;

    public function __construct(?\PDO $db = null) {
        $this->db = $db ?? \Database::getConnection();
    }

    // ==================== NETWORK SITES ====================

    public function getSites(array $filters = []): array {
        $sql = "SELECT * FROM isp_network_sites WHERE 1=1";
        $params = [];
        if (!empty($filters['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['site_type'])) {
            $sql .= " AND site_type = ?";
            $params[] = $filters['site_type'];
        }
        if (!empty($filters['search'])) {
            $sql .= " AND (name ILIKE ? OR address ILIKE ?)";
            $params[] = '%' . $filters['search'] . '%';
            $params[] = '%' . $filters['search'] . '%';
        }
        $sql .= " ORDER BY name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getSite(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM isp_network_sites WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function saveSite(array $data, ?int $id = null): int {
        $fields = ['name', 'site_type', 'address', 'gps_lat', 'gps_lng', 'contact_person', 'contact_phone', 'power_source', 'ups_capacity', 'ups_battery_health', 'notes', 'status'];
        if ($id) {
            $sets = implode(', ', array_map(fn($f) => "$f = ?", $fields));
            $sql = "UPDATE isp_network_sites SET $sets, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $params = array_map(fn($f) => $data[$f] ?? null, $fields);
            $params[] = $id;
            $this->db->prepare($sql)->execute($params);
            return $id;
        } else {
            $cols = implode(', ', $fields);
            $placeholders = implode(', ', array_fill(0, count($fields), '?'));
            $sql = "INSERT INTO isp_network_sites ($cols) VALUES ($placeholders) RETURNING id";
            $params = array_map(fn($f) => $data[$f] ?? null, $fields);
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return (int) $stmt->fetchColumn();
        }
    }

    public function deleteSite(int $id): bool {
        return $this->db->prepare("DELETE FROM isp_network_sites WHERE id = ?")->execute([$id]);
    }

    // ==================== RACKS ====================

    public function getRacks(?int $siteId = null): array {
        $sql = "SELECT r.*, s.name as site_name FROM isp_racks r LEFT JOIN isp_network_sites s ON r.site_id = s.id WHERE 1=1";
        $params = [];
        if ($siteId) {
            $sql .= " AND r.site_id = ?";
            $params[] = $siteId;
        }
        $sql .= " ORDER BY s.name, r.name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function saveRack(array $data, ?int $id = null): int {
        if ($id) {
            $this->db->prepare("UPDATE isp_racks SET site_id=?, name=?, rack_units=?, used_units=?, location_detail=?, status=? WHERE id=?")
                ->execute([$data['site_id'] ?: null, $data['name'], $data['rack_units'] ?? 42, $data['used_units'] ?? 0, $data['location_detail'] ?? null, $data['status'] ?? 'active', $id]);
            return $id;
        } else {
            $stmt = $this->db->prepare("INSERT INTO isp_racks (site_id, name, rack_units, used_units, location_detail, status) VALUES (?,?,?,?,?,?) RETURNING id");
            $stmt->execute([$data['site_id'] ?: null, $data['name'], $data['rack_units'] ?? 42, $data['used_units'] ?? 0, $data['location_detail'] ?? null, $data['status'] ?? 'active']);
            return (int) $stmt->fetchColumn();
        }
    }

    public function deleteRack(int $id): bool {
        return $this->db->prepare("DELETE FROM isp_racks WHERE id = ?")->execute([$id]);
    }

    // ==================== CORE EQUIPMENT ====================

    public function getCoreEquipment(array $filters = []): array {
        $sql = "SELECT e.*, s.name as site_name, r.name as rack_name, o.name as olt_name
                FROM isp_core_equipment e
                LEFT JOIN isp_network_sites s ON e.site_id = s.id
                LEFT JOIN isp_racks r ON e.rack_id = r.id
                LEFT JOIN huawei_olts o ON e.olt_id = o.id
                WHERE 1=1";
        $params = [];
        if (!empty($filters['equipment_type'])) {
            $sql .= " AND e.equipment_type = ?";
            $params[] = $filters['equipment_type'];
        }
        if (!empty($filters['site_id'])) {
            $sql .= " AND e.site_id = ?";
            $params[] = $filters['site_id'];
        }
        if (!empty($filters['status'])) {
            $sql .= " AND e.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $sql .= " AND (e.name ILIKE ? OR e.serial_number ILIKE ? OR e.model ILIKE ?)";
            $params[] = '%' . $filters['search'] . '%';
            $params[] = '%' . $filters['search'] . '%';
            $params[] = '%' . $filters['search'] . '%';
        }
        $sql .= " ORDER BY e.name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getCoreEquipmentItem(int $id): ?array {
        $stmt = $this->db->prepare("SELECT e.*, s.name as site_name, r.name as rack_name FROM isp_core_equipment e LEFT JOIN isp_network_sites s ON e.site_id = s.id LEFT JOIN isp_racks r ON e.rack_id = r.id WHERE e.id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function saveCoreEquipment(array $data, ?int $id = null): int {
        $fields = ['site_id', 'rack_id', 'olt_id', 'equipment_type', 'name', 'manufacturer', 'model', 'serial_number', 'mac_address', 'management_ip', 'os_version', 'firmware_version', 'rack_position', 'capacity', 'purchase_date', 'warranty_expiry', 'supplier', 'purchase_price', 'notes', 'status'];
        if ($id) {
            $sets = implode(', ', array_map(fn($f) => "$f = ?", $fields));
            $sql = "UPDATE isp_core_equipment SET $sets, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $params = array_map(fn($f) => (!empty($data[$f]) ? $data[$f] : null), $fields);
            $params[] = $id;
            $this->db->prepare($sql)->execute($params);
            return $id;
        } else {
            $cols = implode(', ', $fields);
            $placeholders = implode(', ', array_fill(0, count($fields), '?'));
            $stmt = $this->db->prepare("INSERT INTO isp_core_equipment ($cols) VALUES ($placeholders) RETURNING id");
            $params = array_map(fn($f) => (!empty($data[$f]) ? $data[$f] : null), $fields);
            $stmt->execute($params);
            return (int) $stmt->fetchColumn();
        }
    }

    public function deleteCoreEquipment(int $id): bool {
        return $this->db->prepare("DELETE FROM isp_core_equipment WHERE id = ?")->execute([$id]);
    }

    // ==================== SPLITTERS ====================

    public function getSplitters(array $filters = []): array {
        $sql = "SELECT sp.*, s.name as site_name, e.name as upstream_equipment_name
                FROM isp_splitters sp
                LEFT JOIN isp_network_sites s ON sp.site_id = s.id
                LEFT JOIN isp_core_equipment e ON sp.upstream_equipment_id = e.id
                WHERE 1=1";
        $params = [];
        if (!empty($filters['site_id'])) {
            $sql .= " AND sp.site_id = ?";
            $params[] = $filters['site_id'];
        }
        if (!empty($filters['status'])) {
            $sql .= " AND sp.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $sql .= " AND (sp.name ILIKE ? OR sp.pole_number ILIKE ?)";
            $params[] = '%' . $filters['search'] . '%';
            $params[] = '%' . $filters['search'] . '%';
        }
        $sql .= " ORDER BY sp.name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function saveSplitter(array $data, ?int $id = null): int {
        $fields = ['site_id', 'name', 'splitter_type', 'ratio', 'total_ports', 'used_ports', 'location_description', 'pole_number', 'gps_lat', 'gps_lng', 'upstream_equipment_id', 'upstream_port', 'upstream_fiber_core_id', 'notes', 'status'];
        if ($id) {
            $sets = implode(', ', array_map(fn($f) => "$f = ?", $fields));
            $sql = "UPDATE isp_splitters SET $sets, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $params = array_map(fn($f) => (!empty($data[$f]) ? $data[$f] : null), $fields);
            $params[] = $id;
            $this->db->prepare($sql)->execute($params);
            return $id;
        } else {
            $cols = implode(', ', $fields);
            $placeholders = implode(', ', array_fill(0, count($fields), '?'));
            $stmt = $this->db->prepare("INSERT INTO isp_splitters ($cols) VALUES ($placeholders) RETURNING id");
            $params = array_map(fn($f) => (!empty($data[$f]) ? $data[$f] : null), $fields);
            $stmt->execute($params);
            return (int) $stmt->fetchColumn();
        }
    }

    public function deleteSplitter(int $id): bool {
        return $this->db->prepare("DELETE FROM isp_splitters WHERE id = ?")->execute([$id]);
    }

    // ==================== FIBER CORES ====================

    public function getFiberCores(array $filters = []): array {
        $sql = "SELECT * FROM isp_fiber_cores WHERE 1=1";
        $params = [];
        if (!empty($filters['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['cable_name'])) {
            $sql .= " AND cable_name = ?";
            $params[] = $filters['cable_name'];
        }
        if (!empty($filters['search'])) {
            $sql .= " AND (cable_name ILIKE ? OR assigned_to ILIKE ?)";
            $params[] = '%' . $filters['search'] . '%';
            $params[] = '%' . $filters['search'] . '%';
        }
        $sql .= " ORDER BY cable_name, core_number";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function saveFiberCore(array $data, ?int $id = null): int {
        $fields = ['cable_name', 'core_number', 'core_color', 'tube_color', 'route_path', 'start_point', 'end_point', 'splice_points', 'distance_meters', 'attenuation_db', 'assigned_to', 'assignment_type', 'notes', 'status'];
        if ($id) {
            $sets = implode(', ', array_map(fn($f) => "$f = ?", $fields));
            $sql = "UPDATE isp_fiber_cores SET $sets, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $params = array_map(fn($f) => $data[$f] ?? null, $fields);
            $params[] = $id;
            $this->db->prepare($sql)->execute($params);
            return $id;
        } else {
            $cols = implode(', ', $fields);
            $placeholders = implode(', ', array_fill(0, count($fields), '?'));
            $stmt = $this->db->prepare("INSERT INTO isp_fiber_cores ($cols) VALUES ($placeholders) RETURNING id");
            $params = array_map(fn($f) => $data[$f] ?? null, $fields);
            $stmt->execute($params);
            return (int) $stmt->fetchColumn();
        }
    }

    public function deleteFiberCore(int $id): bool {
        return $this->db->prepare("DELETE FROM isp_fiber_cores WHERE id = ?")->execute([$id]);
    }

    // ==================== DISTRIBUTION BOXES ====================

    public function getDistributionBoxes(array $filters = []): array {
        $sql = "SELECT d.*, s.name as site_name, sp.name as splitter_name
                FROM isp_distribution_boxes d
                LEFT JOIN isp_network_sites s ON d.site_id = s.id
                LEFT JOIN isp_splitters sp ON d.splitter_id = sp.id
                WHERE 1=1";
        $params = [];
        if (!empty($filters['site_id'])) {
            $sql .= " AND d.site_id = ?";
            $params[] = $filters['site_id'];
        }
        if (!empty($filters['search'])) {
            $sql .= " AND (d.name ILIKE ? OR d.pole_number ILIKE ?)";
            $params[] = '%' . $filters['search'] . '%';
            $params[] = '%' . $filters['search'] . '%';
        }
        $sql .= " ORDER BY d.name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function saveDistributionBox(array $data, ?int $id = null): int {
        $fields = ['site_id', 'name', 'box_type', 'capacity', 'used_ports', 'pole_number', 'gps_lat', 'gps_lng', 'location_description', 'splitter_id', 'notes', 'status'];
        if ($id) {
            $sets = implode(', ', array_map(fn($f) => "$f = ?", $fields));
            $sql = "UPDATE isp_distribution_boxes SET $sets WHERE id = ?";
            $params = array_map(fn($f) => (!empty($data[$f]) ? $data[$f] : null), $fields);
            $params[] = $id;
            $this->db->prepare($sql)->execute($params);
            return $id;
        } else {
            $cols = implode(', ', $fields);
            $placeholders = implode(', ', array_fill(0, count($fields), '?'));
            $stmt = $this->db->prepare("INSERT INTO isp_distribution_boxes ($cols) VALUES ($placeholders) RETURNING id");
            $params = array_map(fn($f) => (!empty($data[$f]) ? $data[$f] : null), $fields);
            $stmt->execute($params);
            return (int) $stmt->fetchColumn();
        }
    }

    public function deleteDistributionBox(int $id): bool {
        return $this->db->prepare("DELETE FROM isp_distribution_boxes WHERE id = ?")->execute([$id]);
    }

    // ==================== SPLICE CLOSURES ====================

    public function getSpliceClosures(array $filters = []): array {
        $sql = "SELECT sc.*, fc.cable_name as fiber_cable_name
                FROM isp_splice_closures sc
                LEFT JOIN isp_fiber_cores fc ON sc.fiber_core_id = fc.id
                WHERE 1=1";
        $params = [];
        if (!empty($filters['search'])) {
            $sql .= " AND (sc.name ILIKE ? OR sc.pole_number ILIKE ?)";
            $params[] = '%' . $filters['search'] . '%';
            $params[] = '%' . $filters['search'] . '%';
        }
        $sql .= " ORDER BY sc.name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function saveSpliceClosure(array $data, ?int $id = null): int {
        $fields = ['name', 'closure_type', 'location_description', 'pole_number', 'gps_lat', 'gps_lng', 'splice_diagram', 'core_mapping', 'fiber_core_id', 'notes', 'status'];
        if ($id) {
            $sets = implode(', ', array_map(fn($f) => "$f = ?", $fields));
            $sql = "UPDATE isp_splice_closures SET $sets WHERE id = ?";
            $params = array_map(fn($f) => $data[$f] ?? null, $fields);
            $params[] = $id;
            $this->db->prepare($sql)->execute($params);
            return $id;
        } else {
            $cols = implode(', ', $fields);
            $placeholders = implode(', ', array_fill(0, count($fields), '?'));
            $stmt = $this->db->prepare("INSERT INTO isp_splice_closures ($cols) VALUES ($placeholders) RETURNING id");
            $params = array_map(fn($f) => $data[$f] ?? null, $fields);
            $stmt->execute($params);
            return (int) $stmt->fetchColumn();
        }
    }

    public function deleteSpliceClosure(int $id): bool {
        return $this->db->prepare("DELETE FROM isp_splice_closures WHERE id = ?")->execute([$id]);
    }

    // ==================== DROP CABLES ====================

    public function getDropCables(array $filters = []): array {
        $sql = "SELECT dc.*, db.name as box_name, c.name as customer_name
                FROM isp_drop_cables dc
                LEFT JOIN isp_distribution_boxes db ON dc.distribution_box_id = db.id
                LEFT JOIN customers c ON dc.customer_id = c.id
                WHERE 1=1";
        $params = [];
        if (!empty($filters['distribution_box_id'])) {
            $sql .= " AND dc.distribution_box_id = ?";
            $params[] = $filters['distribution_box_id'];
        }
        if (!empty($filters['status'])) {
            $sql .= " AND dc.status = ?";
            $params[] = $filters['status'];
        }
        $sql .= " ORDER BY dc.id DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function saveDropCable(array $data, ?int $id = null): int {
        $fields = ['distribution_box_id', 'box_port', 'customer_id', 'cable_type', 'length_meters', 'installation_date', 'notes', 'status'];
        if ($id) {
            $sets = implode(', ', array_map(fn($f) => "$f = ?", $fields));
            $sql = "UPDATE isp_drop_cables SET $sets WHERE id = ?";
            $params = array_map(fn($f) => (!empty($data[$f]) ? $data[$f] : null), $fields);
            $params[] = $id;
            $this->db->prepare($sql)->execute($params);
            return $id;
        } else {
            $cols = implode(', ', $fields);
            $placeholders = implode(', ', array_fill(0, count($fields), '?'));
            $stmt = $this->db->prepare("INSERT INTO isp_drop_cables ($cols) VALUES ($placeholders) RETURNING id");
            $params = array_map(fn($f) => (!empty($data[$f]) ? $data[$f] : null), $fields);
            $stmt->execute($params);
            return (int) $stmt->fetchColumn();
        }
    }

    public function deleteDropCable(int $id): bool {
        return $this->db->prepare("DELETE FROM isp_drop_cables WHERE id = ?")->execute([$id]);
    }

    // ==================== CPE DEVICES (Legacy) ====================

    public function getCPEDevices(array $filters = []): array {
        $sql = "SELECT cpe.*, o.name as olt_name, sp.name as splitter_name, c.name as customer_name
                FROM isp_cpe_devices cpe
                LEFT JOIN huawei_olts o ON cpe.olt_id = o.id
                LEFT JOIN isp_splitters sp ON cpe.splitter_id = sp.id
                LEFT JOIN customers c ON cpe.customer_id = c.id
                WHERE 1=1";
        $params = [];
        if (!empty($filters['status'])) {
            $sql .= " AND cpe.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['olt_id'])) {
            $sql .= " AND cpe.olt_id = ?";
            $params[] = $filters['olt_id'];
        }
        if (!empty($filters['search'])) {
            $sql .= " AND (cpe.serial_number ILIKE ? OR cpe.mac_address ILIKE ? OR cpe.model ILIKE ? OR c.name ILIKE ?)";
            $params[] = '%' . $filters['search'] . '%';
            $params[] = '%' . $filters['search'] . '%';
            $params[] = '%' . $filters['search'] . '%';
            $params[] = '%' . $filters['search'] . '%';
        }
        $sql .= " ORDER BY cpe.id DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getCPEDevice(int $id): ?array {
        $stmt = $this->db->prepare("SELECT cpe.*, o.name as olt_name, c.name as customer_name FROM isp_cpe_devices cpe LEFT JOIN huawei_olts o ON cpe.olt_id = o.id LEFT JOIN customers c ON cpe.customer_id = c.id WHERE cpe.id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function saveCPEDevice(array $data, ?int $id = null): int {
        $fields = ['serial_number', 'mac_address', 'model', 'manufacturer', 'firmware_version', 'olt_id', 'olt_port', 'splitter_id', 'splitter_port', 'customer_id', 'pppoe_account', 'installation_date', 'warranty_expiry', 'purchase_price', 'supplier', 'notes', 'status'];
        if ($id) {
            $sets = implode(', ', array_map(fn($f) => "$f = ?", $fields));
            $sql = "UPDATE isp_cpe_devices SET $sets, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $params = array_map(fn($f) => (!empty($data[$f]) ? $data[$f] : null), $fields);
            $params[] = $id;
            $this->db->prepare($sql)->execute($params);
            return $id;
        } else {
            $cols = implode(', ', $fields);
            $placeholders = implode(', ', array_fill(0, count($fields), '?'));
            $stmt = $this->db->prepare("INSERT INTO isp_cpe_devices ($cols) VALUES ($placeholders) RETURNING id");
            $params = array_map(fn($f) => (!empty($data[$f]) ? $data[$f] : null), $fields);
            $stmt->execute($params);
            return (int) $stmt->fetchColumn();
        }
    }

    public function deleteCPEDevice(int $id): bool {
        return $this->db->prepare("DELETE FROM isp_cpe_devices WHERE id = ?")->execute([$id]);
    }

    // ==================== ONT INVENTORY (from OMS) ====================

    public function getOntInventory(array $filters = []): array {
        $sql = "SELECT o.id, o.sn, o.name, o.customer_name, o.phone, o.status, o.onu_type,
                       o.mac_address, o.rx_power, o.tx_power, o.distance,
                       o.frame, o.slot, o.port, o.onu_id,
                       o.zone, o.area, o.zone_id, o.subzone_id,
                       o.is_authorized, o.auth_date, o.installation_date,
                       o.firmware_version, o.hardware_version, o.software_version,
                       o.ip_address, o.ont_ip, o.pppoe_username,
                       o.config_state, o.run_state, o.uptime, o.online_since,
                       o.olt_id, o.address, o.latitude, o.longitude,
                       o.discovered_eqid, o.onu_mode,
                       olt.name as olt_name, olt.ip_address as olt_ip,
                       z.name as zone_name, sz.name as subzone_name
                FROM huawei_onus o
                LEFT JOIN huawei_olts olt ON o.olt_id = olt.id
                LEFT JOIN huawei_zones z ON o.zone_id = z.id
                LEFT JOIN huawei_subzones sz ON o.subzone_id = sz.id
                WHERE o.is_authorized = true";
        $params = [];
        if (!empty($filters['status'])) {
            $sql .= " AND o.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['olt_id'])) {
            $sql .= " AND o.olt_id = ?";
            $params[] = $filters['olt_id'];
        }
        if (!empty($filters['zone_id'])) {
            $sql .= " AND o.zone_id = ?";
            $params[] = $filters['zone_id'];
        }
        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $sql .= " AND (o.sn ILIKE ? OR o.name ILIKE ? OR o.customer_name ILIKE ? OR o.phone ILIKE ? OR o.mac_address ILIKE ? OR o.pppoe_username ILIKE ?)";
            $params = array_merge($params, [$search, $search, $search, $search, $search, $search]);
        }
        $sql .= " ORDER BY o.name ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getOntDetail(int $id): ?array {
        $stmt = $this->db->prepare("SELECT o.*, olt.name as olt_name, olt.ip_address as olt_ip,
                       z.name as zone_name, sz.name as subzone_name
                FROM huawei_onus o
                LEFT JOIN huawei_olts olt ON o.olt_id = olt.id
                LEFT JOIN huawei_zones z ON o.zone_id = z.id
                LEFT JOIN huawei_subzones sz ON o.subzone_id = sz.id
                WHERE o.id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function getOntStats(): array {
        $stats = [];
        $stats['total_onts'] = (int) $this->db->query("SELECT COUNT(*) FROM huawei_onus WHERE is_authorized = true")->fetchColumn();
        $stats['online_onts'] = (int) $this->db->query("SELECT COUNT(*) FROM huawei_onus WHERE is_authorized = true AND status = 'online'")->fetchColumn();
        $stats['offline_onts'] = (int) $this->db->query("SELECT COUNT(*) FROM huawei_onus WHERE is_authorized = true AND status = 'offline'")->fetchColumn();
        $stats['low_signal'] = (int) $this->db->query("SELECT COUNT(*) FROM huawei_onus WHERE is_authorized = true AND rx_power < -27 AND rx_power IS NOT NULL")->fetchColumn();
        $stats['zones'] = $this->db->query("SELECT z.name, COUNT(o.id) as ont_count,
                    SUM(CASE WHEN o.status = 'online' THEN 1 ELSE 0 END) as online_count
                    FROM huawei_onus o
                    LEFT JOIN huawei_zones z ON o.zone_id = z.id
                    WHERE o.is_authorized = true
                    GROUP BY z.name ORDER BY ont_count DESC LIMIT 10")->fetchAll(\PDO::FETCH_ASSOC);
        $stats['olts'] = $this->db->query("SELECT olt.name, COUNT(o.id) as ont_count,
                    SUM(CASE WHEN o.status = 'online' THEN 1 ELSE 0 END) as online_count
                    FROM huawei_onus o
                    LEFT JOIN huawei_olts olt ON o.olt_id = olt.id
                    WHERE o.is_authorized = true
                    GROUP BY olt.name ORDER BY ont_count DESC")->fetchAll(\PDO::FETCH_ASSOC);
        return $stats;
    }

    public function getZones(): array {
        return $this->db->query("SELECT id, name FROM huawei_zones ORDER BY name")->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ==================== IP ADDRESSES ====================

    public function getIPAddresses(array $filters = []): array {
        $sql = "SELECT * FROM isp_ip_addresses WHERE 1=1";
        $params = [];
        if (!empty($filters['ip_type'])) {
            $sql .= " AND ip_type = ?";
            $params[] = $filters['ip_type'];
        }
        if (!empty($filters['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $sql .= " AND (ip_address ILIKE ? OR assigned_to ILIKE ? OR block_name ILIKE ?)";
            $params[] = '%' . $filters['search'] . '%';
            $params[] = '%' . $filters['search'] . '%';
            $params[] = '%' . $filters['search'] . '%';
        }
        $sql .= " ORDER BY ip_address";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function saveIPAddress(array $data, ?int $id = null): int {
        $fields = ['ip_type', 'ip_address', 'subnet_mask', 'cidr', 'gateway', 'block_name', 'vlan_id', 'assigned_to', 'assignment_type', 'customer_id', 'device_id', 'reverse_dns', 'notes', 'status'];
        if ($id) {
            $sets = implode(', ', array_map(fn($f) => "$f = ?", $fields));
            $sql = "UPDATE isp_ip_addresses SET $sets, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $params = array_map(fn($f) => $data[$f] ?? null, $fields);
            $params[] = $id;
            $this->db->prepare($sql)->execute($params);
            return $id;
        } else {
            $cols = implode(', ', $fields);
            $placeholders = implode(', ', array_fill(0, count($fields), '?'));
            $stmt = $this->db->prepare("INSERT INTO isp_ip_addresses ($cols) VALUES ($placeholders) RETURNING id");
            $params = array_map(fn($f) => $data[$f] ?? null, $fields);
            $stmt->execute($params);
            return (int) $stmt->fetchColumn();
        }
    }

    public function deleteIPAddress(int $id): bool {
        return $this->db->prepare("DELETE FROM isp_ip_addresses WHERE id = ?")->execute([$id]);
    }

    // ==================== VLANS ====================

    public function getVLANs(array $filters = []): array {
        $sql = "SELECT v.*, s.name as site_name, e.name as equipment_name
                FROM isp_vlans v
                LEFT JOIN isp_network_sites s ON v.site_id = s.id
                LEFT JOIN isp_core_equipment e ON v.equipment_id = e.id
                WHERE 1=1";
        $params = [];
        if (!empty($filters['site_id'])) {
            $sql .= " AND v.site_id = ?";
            $params[] = $filters['site_id'];
        }
        if (!empty($filters['search'])) {
            $sql .= " AND (v.name ILIKE ? OR CAST(v.vlan_id AS TEXT) LIKE ?)";
            $params[] = '%' . $filters['search'] . '%';
            $params[] = '%' . $filters['search'] . '%';
        }
        $sql .= " ORDER BY v.vlan_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function saveVLAN(array $data, ?int $id = null): int {
        $fields = ['vlan_id', 'name', 'purpose', 'subnet', 'gateway', 'site_id', 'equipment_id', 'notes', 'status'];
        if ($id) {
            $sets = implode(', ', array_map(fn($f) => "$f = ?", $fields));
            $sql = "UPDATE isp_vlans SET $sets WHERE id = ?";
            $params = array_map(fn($f) => $data[$f] ?? null, $fields);
            $params[] = $id;
            $this->db->prepare($sql)->execute($params);
            return $id;
        } else {
            $cols = implode(', ', $fields);
            $placeholders = implode(', ', array_fill(0, count($fields), '?'));
            $stmt = $this->db->prepare("INSERT INTO isp_vlans ($cols) VALUES ($placeholders) RETURNING id");
            $params = array_map(fn($f) => $data[$f] ?? null, $fields);
            $stmt->execute($params);
            return (int) $stmt->fetchColumn();
        }
    }

    public function deleteVLAN(int $id): bool {
        return $this->db->prepare("DELETE FROM isp_vlans WHERE id = ?")->execute([$id]);
    }

    // ==================== WAREHOUSE STOCK ====================

    public function getWarehouseStock(array $filters = []): array {
        $sql = "SELECT ws.*, s.name as site_name FROM isp_warehouse_stock ws LEFT JOIN isp_network_sites s ON ws.site_id = s.id WHERE 1=1";
        $params = [];
        if (!empty($filters['category'])) {
            $sql .= " AND ws.category = ?";
            $params[] = $filters['category'];
        }
        if (!empty($filters['site_id'])) {
            $sql .= " AND ws.site_id = ?";
            $params[] = $filters['site_id'];
        }
        if (!empty($filters['low_stock'])) {
            $sql .= " AND ws.quantity <= ws.min_threshold";
        }
        if (!empty($filters['search'])) {
            $sql .= " AND (ws.item_name ILIKE ? OR ws.category ILIKE ?)";
            $params[] = '%' . $filters['search'] . '%';
            $params[] = '%' . $filters['search'] . '%';
        }
        $sql .= " ORDER BY ws.category, ws.item_name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getWarehouseStockItem(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM isp_warehouse_stock WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function saveWarehouseStock(array $data, ?int $id = null): int {
        $fields = ['site_id', 'item_name', 'category', 'unit', 'quantity', 'min_threshold', 'unit_cost', 'supplier', 'supplier_contact', 'storage_location', 'last_restocked', 'notes'];
        if ($id) {
            $sets = implode(', ', array_map(fn($f) => "$f = ?", $fields));
            $sql = "UPDATE isp_warehouse_stock SET $sets, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $params = array_map(fn($f) => (isset($data[$f]) && $data[$f] !== '' ? $data[$f] : null), $fields);
            $params[] = $id;
            $this->db->prepare($sql)->execute($params);
            return $id;
        } else {
            $cols = implode(', ', $fields);
            $placeholders = implode(', ', array_fill(0, count($fields), '?'));
            $stmt = $this->db->prepare("INSERT INTO isp_warehouse_stock ($cols) VALUES ($placeholders) RETURNING id");
            $params = array_map(fn($f) => (isset($data[$f]) && $data[$f] !== '' ? $data[$f] : null), $fields);
            $stmt->execute($params);
            return (int) $stmt->fetchColumn();
        }
    }

    public function deleteWarehouseStock(int $id): bool {
        return $this->db->prepare("DELETE FROM isp_warehouse_stock WHERE id = ?")->execute([$id]);
    }

    public function recordStockMovement(array $data): int {
        $stmt = $this->db->prepare("INSERT INTO isp_stock_movements (stock_id, movement_type, quantity, reference_number, from_location, to_location, performed_by, reason, notes) VALUES (?,?,?,?,?,?,?,?,?) RETURNING id");
        $stmt->execute([
            $data['stock_id'], $data['movement_type'], $data['quantity'],
            $data['reference_number'] ?? null, $data['from_location'] ?? null,
            $data['to_location'] ?? null, $data['performed_by'] ?? null,
            $data['reason'] ?? null, $data['notes'] ?? null
        ]);
        $movementType = $data['movement_type'];
        $qty = (float) $data['quantity'];
        if (in_array($movementType, ['intake', 'return', 'adjustment_add'])) {
            $this->db->prepare("UPDATE isp_warehouse_stock SET quantity = quantity + ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$qty, $data['stock_id']]);
        } elseif (in_array($movementType, ['dispatch', 'usage', 'loss', 'adjustment_remove'])) {
            $this->db->prepare("UPDATE isp_warehouse_stock SET quantity = quantity - ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$qty, $data['stock_id']]);
        }
        return (int) $stmt->fetchColumn();
    }

    public function getStockMovements(int $stockId, int $limit = 50): array {
        $stmt = $this->db->prepare("SELECT * FROM isp_stock_movements WHERE stock_id = ? ORDER BY created_at DESC LIMIT ?");
        $stmt->execute([$stockId, $limit]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getLowStockAlerts(): array {
        $stmt = $this->db->query("SELECT ws.*, s.name as site_name FROM isp_warehouse_stock ws LEFT JOIN isp_network_sites s ON ws.site_id = s.id WHERE ws.quantity <= ws.min_threshold AND ws.min_threshold > 0 ORDER BY ws.quantity ASC");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getSerialStatusMap(array $serialNumbers): array {
        if (empty($serialNumbers)) return [];
        $placeholders = implode(',', array_fill(0, count($serialNumbers), '?'));
        $stmt = $this->db->prepare("SELECT ws.serial_number, ws.status, ws.assigned_to, wst.item_name 
            FROM isp_warehouse_serials ws 
            JOIN isp_warehouse_stock wst ON ws.stock_id = wst.id 
            WHERE ws.serial_number IN ($placeholders)");
        $stmt->execute(array_values($serialNumbers));
        $map = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $map[$row['serial_number']] = $row;
        }
        return $map;
    }

    // ==================== SERIALIZED ITEMS ====================

    public function getSerializedItems(int $stockId, array $filters = []): array {
        $sql = "SELECT si.*, s.name as site_name, ws.item_name, ws.category
                FROM isp_warehouse_serials si
                LEFT JOIN isp_network_sites s ON si.site_id = s.id
                LEFT JOIN isp_warehouse_stock ws ON si.stock_id = ws.id
                WHERE si.stock_id = ?";
        $params = [$stockId];
        if (!empty($filters['status'])) {
            $sql .= " AND si.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $sql .= " AND (si.serial_number ILIKE ? OR si.assigned_to ILIKE ? OR si.notes ILIKE ?)";
            $params = array_merge($params, [$search, $search, $search]);
        }
        $sql .= " ORDER BY si.created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getSerializedItemCounts(int $stockId): array {
        $stmt = $this->db->prepare("SELECT status, COUNT(*) as cnt FROM isp_warehouse_serials WHERE stock_id = ? GROUP BY status");
        $stmt->execute([$stockId]);
        $result = ['total' => 0, 'in_stock' => 0, 'deployed' => 0, 'faulty' => 0, 'returned' => 0, 'lost' => 0];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $result[$row['status']] = (int) $row['cnt'];
            $result['total'] += (int) $row['cnt'];
        }
        return $result;
    }

    public function addSerialsBulk(int $stockId, array $serials, array $meta = []): array {
        $added = 0;
        $duplicates = [];
        $siteId = !empty($meta['site_id']) ? $meta['site_id'] : null;
        $receivedDate = !empty($meta['received_date']) ? $meta['received_date'] : date('Y-m-d');
        $notes = $meta['notes'] ?? null;

        $checkStmt = $this->db->prepare("SELECT COUNT(*) FROM isp_warehouse_serials WHERE serial_number = ?");
        $insertStmt = $this->db->prepare("INSERT INTO isp_warehouse_serials (stock_id, serial_number, status, site_id, received_date, notes) VALUES (?, ?, 'in_stock', ?, ?, ?)");

        $this->db->beginTransaction();
        try {
            foreach ($serials as $sn) {
                $sn = trim($sn);
                if (empty($sn)) continue;
                $checkStmt->execute([$sn]);
                if ((int) $checkStmt->fetchColumn() > 0) {
                    $duplicates[] = $sn;
                    continue;
                }
                $insertStmt->execute([$stockId, $sn, $siteId, $receivedDate, $notes]);
                $added++;
            }
            if ($added > 0) {
                $this->db->prepare("UPDATE isp_warehouse_stock SET quantity = quantity + ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$added, $stockId]);
            }
            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
        return ['added' => $added, 'duplicates' => $duplicates];
    }

    public function updateSerialStatus(int $serialId, string $status, ?string $assignedTo = null): void {
        $oldStmt = $this->db->prepare("SELECT stock_id, status FROM isp_warehouse_serials WHERE id = ?");
        $oldStmt->execute([$serialId]);
        $old = $oldStmt->fetch(\PDO::FETCH_ASSOC);
        if (!$old) return;

        $this->db->prepare("UPDATE isp_warehouse_serials SET status = ?, assigned_to = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$status, $assignedTo, $serialId]);

        if ($old['status'] === 'in_stock' && $status !== 'in_stock') {
            $this->db->prepare("UPDATE isp_warehouse_stock SET quantity = GREATEST(quantity - 1, 0), updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$old['stock_id']]);
        } elseif ($old['status'] !== 'in_stock' && $status === 'in_stock') {
            $this->db->prepare("UPDATE isp_warehouse_stock SET quantity = quantity + 1, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$old['stock_id']]);
        }
    }

    public function deploySerialBySN(string $serialNumber, ?string $assignedTo = null): bool {
        $stmt = $this->db->prepare("SELECT id, stock_id, status FROM isp_warehouse_serials WHERE serial_number = ? LIMIT 1");
        $stmt->execute([$serialNumber]);
        $serial = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$serial) return false;
        if ($serial['status'] === 'deployed') return true;

        $this->db->prepare("UPDATE isp_warehouse_serials SET status = 'deployed', assigned_to = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$assignedTo, $serial['id']]);
        if ($serial['status'] === 'in_stock') {
            $this->db->prepare("UPDATE isp_warehouse_stock SET quantity = GREATEST(quantity - 1, 0), updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$serial['stock_id']]);
        }
        return true;
    }

    public function returnSerialBySN(string $serialNumber): bool {
        $stmt = $this->db->prepare("SELECT id, stock_id, status FROM isp_warehouse_serials WHERE serial_number = ? LIMIT 1");
        $stmt->execute([$serialNumber]);
        $serial = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$serial || $serial['status'] === 'in_stock') return false;

        $this->db->prepare("UPDATE isp_warehouse_serials SET status = 'in_stock', assigned_to = NULL, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$serial['id']]);
        $this->db->prepare("UPDATE isp_warehouse_stock SET quantity = quantity + 1, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$serial['stock_id']]);
        return true;
    }

    public function deleteSerial(int $serialId): void {
        $stmt = $this->db->prepare("SELECT stock_id, status FROM isp_warehouse_serials WHERE id = ?");
        $stmt->execute([$serialId]);
        $item = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$item) return;

        $this->db->prepare("DELETE FROM isp_warehouse_serials WHERE id = ?")->execute([$serialId]);
        if ($item['status'] === 'in_stock') {
            $this->db->prepare("UPDATE isp_warehouse_stock SET quantity = GREATEST(quantity - 1, 0), updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$item['stock_id']]);
        }
    }

    public function importSerialsFromFile(string $filePath, int $stockId, array $columnMap = []): array {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $rows = [];

        if ($ext === 'csv') {
            $handle = fopen($filePath, 'r');
            $header = fgetcsv($handle, 0, ',', '"', '');
            $header = array_map('trim', array_map('strtolower', $header));
            while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                $assoc = array_combine($header, array_pad($row, count($header), ''));
                $rows[] = $assoc;
            }
            fclose($handle);
        } else {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $data = $sheet->toArray(null, true, true, false);
            if (empty($data)) return ['added' => 0, 'duplicates' => [], 'errors' => ['Empty file']];
            $header = array_map('trim', array_map('strtolower', array_shift($data)));
            foreach ($data as $row) {
                if (empty(array_filter($row))) continue;
                $assoc = array_combine($header, array_pad($row, count($header), ''));
                $rows[] = $assoc;
            }
        }

        $snCol = $columnMap['serial_number'] ?? $this->detectColumn($header ?? [], ['serial_number', 'serial', 'sn', 'serial no', 'serialno', 'serial_no', 'imei', 'mac', 'mac_address']);
        if (!$snCol) return ['added' => 0, 'duplicates' => [], 'errors' => ['Could not find serial number column. Expected: serial_number, serial, sn, imei, or mac']];

        $notesCol = $columnMap['notes'] ?? $this->detectColumn($header ?? [], ['notes', 'note', 'description', 'remarks']);
        $dateCol = $columnMap['received_date'] ?? $this->detectColumn($header ?? [], ['received_date', 'date', 'purchase_date', 'date_received']);

        $serials = [];
        $meta = [];
        foreach ($rows as $row) {
            $sn = trim($row[$snCol] ?? '');
            if (empty($sn)) continue;
            $serials[] = $sn;
            $meta[$sn] = [
                'notes' => trim($row[$notesCol] ?? ''),
                'received_date' => !empty($row[$dateCol]) ? $row[$dateCol] : date('Y-m-d'),
            ];
        }

        $added = 0;
        $duplicates = [];
        $checkStmt = $this->db->prepare("SELECT COUNT(*) FROM isp_warehouse_serials WHERE serial_number = ?");
        $insertStmt = $this->db->prepare("INSERT INTO isp_warehouse_serials (stock_id, serial_number, status, received_date, notes) VALUES (?, ?, 'in_stock', ?, ?)");

        $this->db->beginTransaction();
        try {
            foreach ($serials as $sn) {
                $checkStmt->execute([$sn]);
                if ((int) $checkStmt->fetchColumn() > 0) {
                    $duplicates[] = $sn;
                    continue;
                }
                $insertStmt->execute([$stockId, $sn, $meta[$sn]['received_date'] ?? date('Y-m-d'), $meta[$sn]['notes'] ?? null]);
                $added++;
            }
            if ($added > 0) {
                $this->db->prepare("UPDATE isp_warehouse_stock SET quantity = quantity + ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$added, $stockId]);
            }
            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();
            return ['added' => 0, 'duplicates' => $duplicates, 'errors' => [$e->getMessage()]];
        }

        return ['added' => $added, 'duplicates' => $duplicates, 'errors' => []];
    }

    private function detectColumn(array $headers, array $candidates): ?string {
        foreach ($candidates as $c) {
            if (in_array($c, $headers)) return $c;
        }
        foreach ($headers as $h) {
            foreach ($candidates as $c) {
                if (strpos($h, $c) !== false) return $h;
            }
        }
        return null;
    }

    // ==================== FIELD ASSETS ====================

    public function getFieldAssets(array $filters = []): array {
        $sql = "SELECT fa.*, s.name as site_name FROM isp_field_assets fa LEFT JOIN isp_network_sites s ON fa.site_id = s.id WHERE 1=1";
        $params = [];
        if (!empty($filters['asset_type'])) {
            $sql .= " AND fa.asset_type = ?";
            $params[] = $filters['asset_type'];
        }
        if (!empty($filters['status'])) {
            $sql .= " AND fa.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $sql .= " AND (fa.name ILIKE ? OR fa.serial_number ILIKE ? OR fa.assigned_to_name ILIKE ?)";
            $params[] = '%' . $filters['search'] . '%';
            $params[] = '%' . $filters['search'] . '%';
            $params[] = '%' . $filters['search'] . '%';
        }
        $sql .= " ORDER BY fa.name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function saveFieldAsset(array $data, ?int $id = null): int {
        $fields = ['asset_type', 'name', 'serial_number', 'model', 'manufacturer', 'purchase_date', 'purchase_price', 'warranty_expiry', 'condition', 'assigned_to', 'assigned_to_name', 'assignment_date', 'site_id', 'next_maintenance', 'last_maintenance', 'notes', 'status'];
        if ($id) {
            $sets = implode(', ', array_map(fn($f) => "$f = ?", $fields));
            $sql = "UPDATE isp_field_assets SET $sets, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $params = array_map(fn($f) => (!empty($data[$f]) ? $data[$f] : null), $fields);
            $params[] = $id;
            $this->db->prepare($sql)->execute($params);
            return $id;
        } else {
            $cols = implode(', ', $fields);
            $placeholders = implode(', ', array_fill(0, count($fields), '?'));
            $stmt = $this->db->prepare("INSERT INTO isp_field_assets ($cols) VALUES ($placeholders) RETURNING id");
            $params = array_map(fn($f) => (!empty($data[$f]) ? $data[$f] : null), $fields);
            $stmt->execute($params);
            return (int) $stmt->fetchColumn();
        }
    }

    public function deleteFieldAsset(int $id): bool {
        return $this->db->prepare("DELETE FROM isp_field_assets WHERE id = ?")->execute([$id]);
    }

    // ==================== MAINTENANCE LOGS ====================

    public function getMaintenanceLogs(array $filters = []): array {
        $sql = "SELECT * FROM isp_maintenance_logs WHERE 1=1";
        $params = [];
        if (!empty($filters['asset_type'])) {
            $sql .= " AND asset_type = ?";
            $params[] = $filters['asset_type'];
        }
        if (!empty($filters['asset_id'])) {
            $sql .= " AND asset_id = ?";
            $params[] = $filters['asset_id'];
        }
        if (!empty($filters['search'])) {
            $sql .= " AND (asset_name ILIKE ? OR description ILIKE ?)";
            $params[] = '%' . $filters['search'] . '%';
            $params[] = '%' . $filters['search'] . '%';
        }
        $sql .= " ORDER BY created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function saveMaintenanceLog(array $data): int {
        $stmt = $this->db->prepare("INSERT INTO isp_maintenance_logs (asset_type, asset_id, asset_name, maintenance_type, description, performed_by, performed_by_name, cost, next_due, notes, status) VALUES (?,?,?,?,?,?,?,?,?,?,?) RETURNING id");
        $stmt->execute([
            $data['asset_type'], $data['asset_id'], $data['asset_name'] ?? null,
            $data['maintenance_type'], $data['description'] ?? null,
            $data['performed_by'] ?? null, $data['performed_by_name'] ?? null,
            $data['cost'] ?? 0, $data['next_due'] ?? null,
            $data['notes'] ?? null, $data['status'] ?? 'completed'
        ]);
        return (int) $stmt->fetchColumn();
    }

    public function deleteMaintenanceLog(int $id): bool {
        return $this->db->prepare("DELETE FROM isp_maintenance_logs WHERE id = ?")->execute([$id]);
    }

    // ==================== DASHBOARD STATS ====================

    public function getDashboardStats(): array {
        $stats = [];
        $stats['total_sites'] = (int) $this->db->query("SELECT COUNT(*) FROM isp_network_sites WHERE status = 'active'")->fetchColumn();
        $stats['total_core_equipment'] = (int) $this->db->query("SELECT COUNT(*) FROM isp_core_equipment WHERE status = 'active'")->fetchColumn();
        $stats['total_onts'] = (int) $this->db->query("SELECT COUNT(*) FROM huawei_onus WHERE is_authorized = true")->fetchColumn();
        $stats['onts_online'] = (int) $this->db->query("SELECT COUNT(*) FROM huawei_onus WHERE is_authorized = true AND status = 'online'")->fetchColumn();
        $stats['onts_offline'] = (int) $this->db->query("SELECT COUNT(*) FROM huawei_onus WHERE is_authorized = true AND status = 'offline'")->fetchColumn();
        $stats['onts_low_signal'] = (int) $this->db->query("SELECT COUNT(*) FROM huawei_onus WHERE is_authorized = true AND rx_power < -27 AND rx_power IS NOT NULL")->fetchColumn();
        $stats['total_ips'] = (int) $this->db->query("SELECT COUNT(*) FROM isp_ip_addresses")->fetchColumn();
        $stats['ips_assigned'] = (int) $this->db->query("SELECT COUNT(*) FROM isp_ip_addresses WHERE status = 'assigned'")->fetchColumn();
        $stats['low_stock_count'] = (int) $this->db->query("SELECT COUNT(*) FROM isp_warehouse_stock WHERE quantity <= min_threshold AND min_threshold > 0")->fetchColumn();
        $stats['field_assets_total'] = (int) $this->db->query("SELECT COUNT(*) FROM isp_field_assets")->fetchColumn();
        $stats['pending_maintenance'] = (int) $this->db->query("SELECT COUNT(*) FROM isp_maintenance_logs WHERE status = 'pending'")->fetchColumn();
        return $stats;
    }

    // ==================== NETWORK MAPPING ====================

    public function getNetworkMap(): array {
        $sites = $this->db->query("SELECT * FROM isp_network_sites WHERE status = 'active' ORDER BY name")->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($sites as &$site) {
            $stmt = $this->db->prepare("SELECT * FROM isp_core_equipment WHERE site_id = ? AND status = 'active' ORDER BY equipment_type, name");
            $stmt->execute([$site['id']]);
            $site['equipment'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $stmt = $this->db->prepare("SELECT * FROM isp_splitters WHERE site_id = ? AND status = 'active' ORDER BY name");
            $stmt->execute([$site['id']]);
            $site['splitters'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $stmt = $this->db->prepare("SELECT * FROM isp_distribution_boxes WHERE site_id = ? AND status = 'active' ORDER BY name");
            $stmt->execute([$site['id']]);
            $site['distribution_boxes'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }
        return $sites;
    }

    // ==================== HELPERS ====================

    public function getOLTs(): array {
        $stmt = $this->db->query("SELECT id, name, ip_address, location FROM huawei_olts WHERE is_active = true ORDER BY name");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getCustomers(string $search = ''): array {
        $sql = "SELECT id, name, phone, email FROM customers WHERE 1=1";
        $params = [];
        if ($search) {
            $sql .= " AND (name ILIKE ? OR phone ILIKE ?)";
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }
        $sql .= " ORDER BY name LIMIT 50";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getEmployees(): array {
        $stmt = $this->db->query("SELECT id, COALESCE(first_name || ' ' || last_name, username, name) as name FROM users ORDER BY name");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getTeams(): array {
        $stmt = $this->db->query("SELECT id, name FROM teams WHERE is_active = true ORDER BY name");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function bulkAssignSerials(array $serialIds, string $assignTo, string $status = 'deployed', int $stockId = 0): array {
        $updated = 0;
        $this->db->beginTransaction();
        try {
            foreach ($serialIds as $serialId) {
                $serialId = (int) $serialId;
                $sql = "SELECT stock_id, status FROM isp_warehouse_serials WHERE id = ?";
                $params = [$serialId];
                if ($stockId > 0) {
                    $sql .= " AND stock_id = ?";
                    $params[] = $stockId;
                }
                $oldStmt = $this->db->prepare($sql);
                $oldStmt->execute($params);
                $old = $oldStmt->fetch(\PDO::FETCH_ASSOC);
                if (!$old) continue;

                $this->db->prepare("UPDATE isp_warehouse_serials SET status = ?, assigned_to = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$status, $assignTo, $serialId]);

                if ($old['status'] === 'in_stock' && $status !== 'in_stock') {
                    $this->db->prepare("UPDATE isp_warehouse_stock SET quantity = GREATEST(quantity - 1, 0), updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$old['stock_id']]);
                } elseif ($old['status'] !== 'in_stock' && $status === 'in_stock') {
                    $this->db->prepare("UPDATE isp_warehouse_stock SET quantity = quantity + 1, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$old['stock_id']]);
                }
                $updated++;
            }
            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
        return ['updated' => $updated];
    }
}
