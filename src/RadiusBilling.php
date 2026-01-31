<?php
namespace App;

class RadiusBilling {
    private \PDO $db;
    private string $encryptionKey;
    
    public function __construct(\PDO $db) {
        $this->db = $db;
        $encKey = getenv('ENCRYPTION_KEY');
        if (empty($encKey)) {
            $encKey = $_ENV['ENCRYPTION_KEY'] ?? 'default-radius-key-change-me';
        }
        $this->encryptionKey = $encKey;
        $this->ensureTablesExist();
    }
    
    private function ensureTablesExist(): void {
        try {
            $this->db->query("SELECT 1 FROM radius_nas LIMIT 1");
        } catch (\Exception $e) {
            $sql = file_get_contents(__DIR__ . '/../migrations/radius_billing.sql');
            if ($sql) {
                $this->db->exec($sql);
            }
        }
    }
    
    private function encrypt(string $data): string {
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $this->encryptionKey, 0, $iv);
        return base64_encode($iv . $encrypted);
    }
    
    private function decrypt(string $data): string {
        $data = base64_decode($data);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $this->encryptionKey, 0, $iv);
    }
    
    private function castBoolean($value, bool $default = false): string {
        if ($value === '' || $value === null) {
            return $default ? 'true' : 'false';
        }
        return !empty($value) ? 'true' : 'false';
    }
    
    public function encryptPassword(string $password): string {
        return $this->encrypt($password);
    }
    
    public function decryptPassword(string $encryptedPassword): string {
        return $this->decrypt($encryptedPassword);
    }
    
    // ==================== Username Generation ====================
    
    public function getISPPrefix(): string {
        $stmt = $this->db->prepare("SELECT setting_value FROM company_settings WHERE setting_key = 'isp_username_prefix'");
        $stmt->execute();
        $result = $stmt->fetchColumn();
        return $result ?: 'SFL';
    }
    
    public function setISPPrefix(string $prefix): bool {
        $stmt = $this->db->prepare("
            INSERT INTO company_settings (setting_key, setting_value, setting_type)
            VALUES ('isp_username_prefix', ?, 'string')
            ON CONFLICT (setting_key) DO UPDATE SET setting_value = EXCLUDED.setting_value
        ");
        return $stmt->execute([$prefix]);
    }
    
    public function getNextUsername(): string {
        $prefix = $this->getISPPrefix();
        
        $stmt = $this->db->prepare("
            SELECT username FROM radius_subscriptions 
            WHERE username LIKE ? 
            ORDER BY LENGTH(username) DESC, username DESC 
            LIMIT 1
        ");
        $stmt->execute([$prefix . '%']);
        $lastUsername = $stmt->fetchColumn();
        
        if ($lastUsername) {
            $numericPart = preg_replace('/^' . preg_quote($prefix, '/') . '/', '', $lastUsername);
            $nextNum = (int)$numericPart + 1;
        } else {
            $nextNum = 1;
        }
        
        $digits = max(3, strlen((string)$nextNum));
        return $prefix . str_pad($nextNum, $digits, '0', STR_PAD_LEFT);
    }
    
    public function generatePassword(int $length = 8): string {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $password;
    }
    
    // ==================== NAS Management ====================
    
    public function getNASDevices(): array {
        $stmt = $this->db->query("
            SELECT n.*, 
                   wp.name as vpn_peer_name, wp.allowed_ips as vpn_allowed_ips,
                   ws.name as vpn_server_name, ws.interface_addr as vpn_server_addr
            FROM radius_nas n
            LEFT JOIN wireguard_peers wp ON n.wireguard_peer_id = wp.id
            LEFT JOIN wireguard_servers ws ON wp.server_id = ws.id
            ORDER BY n.name
        ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getNASWithVPN(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT n.*, n.secret,
                   wp.id as wireguard_peer_id, wp.name as vpn_peer_name, wp.allowed_ips as vpn_allowed_ips,
                   wp.public_key as vpn_peer_pubkey,
                   ws.id as vpn_server_id, ws.name as vpn_server_name, ws.interface_addr as vpn_server_addr,
                   ws.public_key as vpn_server_pubkey, ws.listen_port as vpn_server_port
            FROM radius_nas n
            LEFT JOIN wireguard_peers wp ON n.wireguard_peer_id = wp.id
            LEFT JOIN wireguard_servers ws ON wp.server_id = ws.id
            WHERE n.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    
    public function getNAS(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM radius_nas WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    
    public function createNAS(array $data): array {
        try {
            $apiEnabled = !empty($data['api_enabled']) ? 'true' : 'false';
            $isActive = isset($data['is_active']) ? (!empty($data['is_active']) ? 'true' : 'false') : 'true';
            
            $stmt = $this->db->prepare("
                INSERT INTO radius_nas (name, ip_address, secret, nas_type, ports, description, 
                                        api_enabled, api_port, api_username, api_password_encrypted, is_active, wireguard_peer_id,
                                        location_id, sub_location_id)
                VALUES (?, ?, ?, ?, ?, ?, ?::boolean, ?, ?, ?, ?::boolean, ?, ?, ?)
            ");
            $stmt->execute([
                $data['name'],
                $data['ip_address'],
                $data['secret'],
                $data['nas_type'] ?? 'mikrotik',
                $data['ports'] ?? 1812,
                $data['description'] ?? '',
                $apiEnabled,
                $data['api_port'] ?? 8728,
                $data['api_username'] ?? '',
                !empty($data['api_password']) ? $this->encrypt($data['api_password']) : '',
                $isActive,
                !empty($data['wireguard_peer_id']) ? (int)$data['wireguard_peer_id'] : null,
                !empty($data['location_id']) ? (int)$data['location_id'] : null,
                !empty($data['sub_location_id']) ? (int)$data['sub_location_id'] : null
            ]);
            return ['success' => true, 'id' => $this->db->lastInsertId()];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function updateNAS(int $id, array $data): array {
        try {
            $fields = ['name', 'ip_address', 'secret', 'nas_type', 'ports', 'description', 
                       'api_port', 'api_username'];
            $boolFields = ['api_enabled', 'is_active'];
            $intFields = ['location_id', 'sub_location_id'];
            $updates = [];
            $params = [];
            
            foreach ($fields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }
            
            foreach ($boolFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?::boolean";
                    $params[] = !empty($data[$field]) ? 'true' : 'false';
                }
            }
            
            foreach ($intFields as $field) {
                if (array_key_exists($field, $data)) {
                    $updates[] = "$field = ?";
                    $params[] = !empty($data[$field]) ? (int)$data[$field] : null;
                }
            }
            
            if (!empty($data['api_password'])) {
                $updates[] = "api_password_encrypted = ?";
                $params[] = $this->encrypt($data['api_password']);
            }
            
            if (array_key_exists('wireguard_peer_id', $data)) {
                $updates[] = "wireguard_peer_id = ?";
                $params[] = !empty($data['wireguard_peer_id']) ? (int)$data['wireguard_peer_id'] : null;
            }
            
            $updates[] = "updated_at = CURRENT_TIMESTAMP";
            $params[] = $id;
            
            $stmt = $this->db->prepare("UPDATE radius_nas SET " . implode(', ', $updates) . " WHERE id = ?");
            $stmt->execute($params);
            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function deleteNAS(int $id): array {
        try {
            $stmt = $this->db->prepare("DELETE FROM radius_nas WHERE id = ?");
            $stmt->execute([$id]);
            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    // ==================== Location Management ====================
    
    public function getLocations(): array {
        $stmt = $this->db->query("SELECT * FROM huawei_zones WHERE is_active = true ORDER BY name");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getAllLocations(): array {
        $stmt = $this->db->query("SELECT * FROM huawei_zones ORDER BY name");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getLocation(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM huawei_zones WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    
    public function createLocation(array $data): array {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO huawei_zones (name, description, is_active)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([
                $data['name'],
                $data['description'] ?? null,
                isset($data['is_active']) ? ($data['is_active'] ? true : false) : true
            ]);
            return ['success' => true, 'id' => $this->db->lastInsertId()];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function updateLocation(int $id, array $data): array {
        try {
            $stmt = $this->db->prepare("
                UPDATE huawei_zones 
                SET name = ?, description = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([
                $data['name'],
                $data['description'] ?? null,
                isset($data['is_active']) ? ($data['is_active'] ? true : false) : true,
                $id
            ]);
            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function deleteLocation(int $id): array {
        try {
            $stmt = $this->db->prepare("DELETE FROM huawei_zones WHERE id = ?");
            $stmt->execute([$id]);
            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function getSubLocations(?int $locationId = null): array {
        $sql = "SELECT sl.*, l.name as location_name, sl.zone_id as location_id
                FROM huawei_subzones sl 
                JOIN huawei_zones l ON sl.zone_id = l.id 
                WHERE sl.is_active = true";
        $params = [];
        
        if ($locationId) {
            $sql .= " AND sl.zone_id = ?";
            $params[] = $locationId;
        }
        
        $sql .= " ORDER BY l.name, sl.name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getAllSubLocations(): array {
        $stmt = $this->db->query("
            SELECT sl.*, l.name as location_name, sl.zone_id as location_id
            FROM huawei_subzones sl 
            JOIN huawei_zones l ON sl.zone_id = l.id 
            ORDER BY l.name, sl.name
        ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getSubLocation(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT sl.*, l.name as location_name, sl.zone_id as location_id
            FROM huawei_subzones sl 
            JOIN huawei_zones l ON sl.zone_id = l.id 
            WHERE sl.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    
    public function createSubLocation(array $data): array {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO huawei_subzones (zone_id, name, description, is_active)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['location_id'],
                $data['name'],
                $data['description'] ?? null,
                isset($data['is_active']) ? ($data['is_active'] ? true : false) : true
            ]);
            return ['success' => true, 'id' => $this->db->lastInsertId()];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function updateSubLocation(int $id, array $data): array {
        try {
            $stmt = $this->db->prepare("
                UPDATE huawei_subzones 
                SET zone_id = ?, name = ?, description = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([
                $data['location_id'],
                $data['name'],
                $data['description'] ?? null,
                isset($data['is_active']) ? ($data['is_active'] ? true : false) : true,
                $id
            ]);
            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function deleteSubLocation(int $id): array {
        try {
            $stmt = $this->db->prepare("DELETE FROM huawei_subzones WHERE id = ?");
            $stmt->execute([$id]);
            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function getSubscribersByFilter(array $filters): array {
        $sql = "SELECT s.*, c.name as customer_name, c.phone as customer_phone, c.email as customer_email,
                       p.name as package_name, p.price as package_price,
                       n.name as nas_name, n.ip_address as nas_ip,
                       l.name as location_name, sl.name as sub_location_name
                FROM radius_subscriptions s
                LEFT JOIN customers c ON s.customer_id = c.id
                LEFT JOIN radius_packages p ON s.package_id = p.id
                LEFT JOIN radius_nas n ON s.nas_id = n.id
                LEFT JOIN huawei_zones l ON s.location_id = l.id
                LEFT JOIN huawei_subzones sl ON s.sub_location_id = sl.id
                WHERE 1=1";
        $params = [];
        
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'expired') {
                $sql .= " AND s.expiry_date < CURRENT_DATE";
            } elseif ($filters['status'] === 'active') {
                $sql .= " AND s.status = 'active' AND s.expiry_date >= CURRENT_DATE";
            } else {
                $sql .= " AND s.status = ?";
                $params[] = $filters['status'];
            }
        }
        
        if (!empty($filters['location_id'])) {
            $sql .= " AND s.location_id = ?";
            $params[] = $filters['location_id'];
        }
        
        if (!empty($filters['sub_location_id'])) {
            $sql .= " AND s.sub_location_id = ?";
            $params[] = $filters['sub_location_id'];
        }
        
        if (!empty($filters['package_id'])) {
            $sql .= " AND s.package_id = ?";
            $params[] = $filters['package_id'];
        }
        
        if (!empty($filters['nas_id'])) {
            $sql .= " AND s.nas_id = ?";
            $params[] = $filters['nas_id'];
        }
        
        $sql .= " ORDER BY c.name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    // ==================== Package Management ====================
    
    public function getPackages(?string $type = null): array {
        $sql = "SELECT * FROM radius_packages WHERE 1=1";
        $params = [];
        
        if ($type) {
            $sql .= " AND package_type = ?";
            $params[] = $type;
        }
        
        $sql .= " ORDER BY package_type, price";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getPackage(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM radius_packages WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    
    public function createPackage(array $data): array {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO radius_packages (name, description, package_type, billing_type, price, validity_days,
                    data_quota_mb, download_speed, upload_speed, burst_download, burst_upload,
                    burst_threshold, burst_time, priority, address_pool, ip_binding,
                    simultaneous_sessions, max_devices, fup_enabled, fup_quota_mb, fup_download_speed, fup_upload_speed, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?::boolean, ?, ?, ?::boolean, ?, ?, ?, ?::boolean)
            ");
            $stmt->execute([
                $data['name'],
                $data['description'] ?? '',
                $data['package_type'] ?? 'pppoe',
                $data['billing_type'] ?? 'monthly',
                $data['price'] ?? 0,
                $data['validity_days'] ?? 30,
                $data['data_quota_mb'] ?: null,
                $data['download_speed'] ?? '',
                $data['upload_speed'] ?? '',
                $data['burst_download'] ?? '',
                $data['burst_upload'] ?? '',
                $data['burst_threshold'] ?? '',
                $data['burst_time'] ?? '',
                $data['priority'] ?? 8,
                $data['address_pool'] ?? '',
                $this->castBoolean($data['ip_binding'] ?? false),
                $data['simultaneous_sessions'] ?? 1,
                $data['max_devices'] ?? 1,
                $this->castBoolean($data['fup_enabled'] ?? false),
                $data['fup_quota_mb'] ?: null,
                $data['fup_download_speed'] ?? '',
                $data['fup_upload_speed'] ?? '',
                $this->castBoolean($data['is_active'] ?? true, true)
            ]);
            return ['success' => true, 'id' => $this->db->lastInsertId()];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function updatePackage(int $id, array $data): array {
        try {
            // Get old package speeds to detect changes
            $oldPackage = $this->getPackage($id);
            $oldDownload = $oldPackage['download_speed'] ?? '';
            $oldUpload = $oldPackage['upload_speed'] ?? '';
            
            $stmt = $this->db->prepare("
                UPDATE radius_packages SET
                    name = ?, description = ?, package_type = ?, billing_type = ?, price = ?,
                    validity_days = ?, data_quota_mb = ?, download_speed = ?, upload_speed = ?,
                    burst_download = ?, burst_upload = ?, burst_threshold = ?, burst_time = ?,
                    priority = ?, address_pool = ?, ip_binding = ?::boolean, simultaneous_sessions = ?,
                    max_devices = ?, fup_enabled = ?::boolean, fup_quota_mb = ?, fup_download_speed = ?, fup_upload_speed = ?,
                    is_active = ?::boolean, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([
                $data['name'],
                $data['description'] ?? '',
                $data['package_type'] ?? 'pppoe',
                $data['billing_type'] ?? 'monthly',
                $data['price'] ?? 0,
                $data['validity_days'] ?? 30,
                $data['data_quota_mb'] ?: null,
                $data['download_speed'] ?? '',
                $data['upload_speed'] ?? '',
                $data['burst_download'] ?? '',
                $data['burst_upload'] ?? '',
                $data['burst_threshold'] ?? '',
                $data['burst_time'] ?? '',
                $data['priority'] ?? 8,
                $data['address_pool'] ?? '',
                $this->castBoolean($data['ip_binding'] ?? false),
                $data['simultaneous_sessions'] ?? 1,
                $data['max_devices'] ?? 1,
                $this->castBoolean($data['fup_enabled'] ?? false),
                $data['fup_quota_mb'] ?: null,
                $data['fup_download_speed'] ?? '',
                $data['fup_upload_speed'] ?? '',
                $this->castBoolean($data['is_active'] ?? true, true),
                $id
            ]);
            
            // If speeds changed, send CoA to all active subscribers using this package
            $coaCount = 0;
            $newDownload = $data['download_speed'] ?? '';
            $newUpload = $data['upload_speed'] ?? '';
            
            if ($oldDownload !== $newDownload || $oldUpload !== $newUpload) {
                $affectedSubs = $this->db->prepare("
                    SELECT id FROM radius_subscriptions 
                    WHERE package_id = ? AND status = 'active'
                ");
                $affectedSubs->execute([$id]);
                
                while ($sub = $affectedSubs->fetch(\PDO::FETCH_ASSOC)) {
                    $coaResult = $this->sendSpeedUpdateCoA($sub['id']);
                    if ($coaResult['success']) {
                        $coaCount++;
                    }
                }
            }
            
            return ['success' => true, 'coa_updated' => $coaCount];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    // ==================== Subscription Management ====================
    
    public function getSubscriptions(array $filters = []): array {
        $sql = "SELECT s.*, c.name as customer_name, c.phone as customer_phone, c.email as customer_email,
                       p.name as package_name, p.download_speed, p.upload_speed, p.price as package_price,
                       n.name as nas_name
                FROM radius_subscriptions s
                LEFT JOIN customers c ON s.customer_id = c.id
                LEFT JOIN radius_packages p ON s.package_id = p.id
                LEFT JOIN radius_nas n ON s.nas_id = n.id
                WHERE 1=1";
        $params = [];
        
        if (!empty($filters['status'])) {
            $sql .= " AND s.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['access_type'])) {
            $sql .= " AND s.access_type = ?";
            $params[] = $filters['access_type'];
        }
        if (!empty($filters['search'])) {
            $sql .= " AND (s.username ILIKE ? OR c.name ILIKE ? OR c.phone ILIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        if (!empty($filters['expiring_soon'])) {
            $sql .= " AND s.expiry_date BETWEEN CURRENT_DATE AND CURRENT_DATE + INTERVAL '7 days'";
        }
        if (!empty($filters['expired'])) {
            $sql .= " AND s.expiry_date < CURRENT_DATE";
        }
        if (!empty($filters['package_id'])) {
            $sql .= " AND s.package_id = ?";
            $params[] = (int)$filters['package_id'];
        }
        if (!empty($filters['subscription_ids']) && is_array($filters['subscription_ids'])) {
            $placeholders = implode(',', array_fill(0, count($filters['subscription_ids']), '?'));
            $sql .= " AND s.id IN ($placeholders)";
            $params = array_merge($params, array_map('intval', $filters['subscription_ids']));
        }
        if (isset($filters['exclude_subscription_ids']) && is_array($filters['exclude_subscription_ids'])) {
            if (!empty($filters['exclude_subscription_ids'])) {
                $placeholders = implode(',', array_fill(0, count($filters['exclude_subscription_ids']), '?'));
                $sql .= " AND s.id NOT IN ($placeholders)";
                $params = array_merge($params, array_map('intval', $filters['exclude_subscription_ids']));
            }
        }
        
        $sql .= " ORDER BY s.created_at DESC";
        
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT " . (int)$filters['limit'];
        }
        if (!empty($filters['offset'])) {
            $sql .= " OFFSET " . (int)$filters['offset'];
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function countSubscriptions(array $filters = []): int {
        $sql = "SELECT COUNT(*) FROM radius_subscriptions s
                LEFT JOIN customers c ON s.customer_id = c.id
                WHERE 1=1";
        $params = [];
        
        if (!empty($filters['status'])) {
            $sql .= " AND s.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['access_type'])) {
            $sql .= " AND s.access_type = ?";
            $params[] = $filters['access_type'];
        }
        if (!empty($filters['search'])) {
            $sql .= " AND (s.username ILIKE ? OR c.name ILIKE ? OR c.phone ILIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        if (!empty($filters['expiring_soon'])) {
            $sql .= " AND s.expiry_date BETWEEN CURRENT_DATE AND CURRENT_DATE + INTERVAL '7 days'";
        }
        if (!empty($filters['expired'])) {
            $sql .= " AND s.expiry_date < CURRENT_DATE";
        }
        if (!empty($filters['package_id'])) {
            $sql .= " AND s.package_id = ?";
            $params[] = (int)$filters['package_id'];
        }
        if (!empty($filters['subscription_ids']) && is_array($filters['subscription_ids'])) {
            $placeholders = implode(',', array_fill(0, count($filters['subscription_ids']), '?'));
            $sql .= " AND s.id IN ($placeholders)";
            $params = array_merge($params, array_map('intval', $filters['subscription_ids']));
        }
        if (isset($filters['exclude_subscription_ids']) && is_array($filters['exclude_subscription_ids'])) {
            if (!empty($filters['exclude_subscription_ids'])) {
                $placeholders = implode(',', array_fill(0, count($filters['exclude_subscription_ids']), '?'));
                $sql .= " AND s.id NOT IN ($placeholders)";
                $params = array_merge($params, array_map('intval', $filters['exclude_subscription_ids']));
            }
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }
    
    public function getSubscription(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT s.*, c.name as customer_name, c.phone as customer_phone,
                   p.name as package_name, p.download_speed, p.upload_speed,
                   p.fup_enabled, p.fup_quota_mb, p.fup_download_speed, p.fup_upload_speed
            FROM radius_subscriptions s
            LEFT JOIN customers c ON s.customer_id = c.id
            LEFT JOIN radius_packages p ON s.package_id = p.id
            WHERE s.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    
    public function getSubscriptionByUsername(string $username): ?array {
        $stmt = $this->db->prepare("
            SELECT s.*, c.name as customer_name, p.name as package_name,
                   p.download_speed, p.upload_speed, p.data_quota_mb,
                   p.simultaneous_sessions, p.fup_enabled, p.fup_quota_mb,
                   p.fup_download_speed, p.fup_upload_speed
            FROM radius_subscriptions s
            LEFT JOIN customers c ON s.customer_id = c.id
            LEFT JOIN radius_packages p ON s.package_id = p.id
            WHERE s.username = ?
        ");
        $stmt->execute([$username]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    
    public function createSubscription(array $data): array {
        try {
            $package = $this->getPackage($data['package_id']);
            if (!$package) {
                return ['success' => false, 'error' => 'Package not found'];
            }
            
            // New subscribers start as 'inactive' - activated upon payment or manual activation
            $initialStatus = $data['status'] ?? 'inactive';
            $startDate = null;
            $expiryDate = null;
            
            // Only set dates if activating immediately
            if ($initialStatus === 'active') {
                $startDate = date('Y-m-d');
                $expiryDate = date('Y-m-d', strtotime("+{$package['validity_days']} days"));
            }
            
            $stmt = $this->db->prepare("
                INSERT INTO radius_subscriptions (customer_id, package_id, username, password, password_encrypted,
                    access_type, static_ip, mac_address, status, start_date, expiry_date, nas_id, notes, location_id, sub_location_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['customer_id'],
                $data['package_id'],
                $data['username'],
                $data['password'], // Cleartext for RADIUS CHAP/MS-CHAP
                $this->encrypt($data['password']),
                $data['access_type'] ?? $package['package_type'],
                !empty($data['static_ip']) ? $data['static_ip'] : null,
                !empty($data['mac_address']) ? $data['mac_address'] : null,
                $initialStatus,
                $startDate,
                $expiryDate,
                !empty($data['nas_id']) ? (int)$data['nas_id'] : null,
                $data['notes'] ?? '',
                !empty($data['location_id']) ? (int)$data['location_id'] : null,
                !empty($data['sub_location_id']) ? (int)$data['sub_location_id'] : null
            ]);
            
            $subscriptionId = $this->db->lastInsertId();
            
            // Only create billing record if activated
            if ($initialStatus === 'active') {
                $this->createBillingRecord($subscriptionId, $data['package_id'], $package['price'], 'renewal', $startDate, $expiryDate);
            }
            
            return ['success' => true, 'id' => $subscriptionId, 'status' => $initialStatus];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function activateSubscription(int $id): array {
        try {
            $sub = $this->getSubscription($id);
            if (!$sub) {
                return ['success' => false, 'error' => 'Subscription not found'];
            }
            
            if ($sub['status'] === 'active') {
                return ['success' => false, 'error' => 'Subscription is already active'];
            }
            
            $package = $this->getPackage($sub['package_id']);
            if (!$package) {
                return ['success' => false, 'error' => 'Package not found'];
            }
            
            $startDate = date('Y-m-d');
            $expiryDate = date('Y-m-d', strtotime("+{$package['validity_days']} days"));
            
            $stmt = $this->db->prepare("
                UPDATE radius_subscriptions SET
                    status = 'active', start_date = ?, expiry_date = ?,
                    data_used_mb = 0, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([$startDate, $expiryDate, $id]);
            
            // Create billing record for activation
            $this->createBillingRecord($id, $sub['package_id'], $package['price'], 'activation', $startDate, $expiryDate);
            
            // Send CoA to update speed (in case device tried connecting before)
            $coaResult = $this->sendSpeedUpdateCoA($id);
            
            return [
                'success' => true, 
                'message' => 'Subscription activated successfully',
                'start_date' => $startDate,
                'expiry_date' => $expiryDate,
                'coa_result' => $coaResult
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function renewSubscription(int $id, ?int $packageId = null): array {
        try {
            $sub = $this->getSubscription($id);
            if (!$sub) {
                return ['success' => false, 'error' => 'Subscription not found'];
            }
            
            // Check if was inactive (never activated) or expired
            $wasInactive = ($sub['status'] === 'inactive' || empty($sub['start_date']));
            $wasExpired = ($sub['status'] !== 'active' || (isset($sub['expiry_date']) && $sub['expiry_date'] < date('Y-m-d')));
            
            $packageId = $packageId ?? $sub['package_id'];
            $package = $this->getPackage($packageId);
            if (!$package) {
                return ['success' => false, 'error' => 'Package not found'];
            }
            
            // For inactive accounts, start fresh from today
            // For active/expired accounts, extend from expiry date
            if ($wasInactive || empty($sub['expiry_date'])) {
                $startDate = date('Y-m-d');
            } else {
                $startDate = max(date('Y-m-d'), $sub['expiry_date']);
            }
            $expiryDate = date('Y-m-d', strtotime($startDate . " +{$package['validity_days']} days"));
            
            $stmt = $this->db->prepare("
                UPDATE radius_subscriptions SET
                    package_id = ?, status = 'active', start_date = ?, expiry_date = ?,
                    data_used_mb = 0, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([$packageId, $startDate, $expiryDate, $id]);
            
            $this->createBillingRecord($id, $packageId, $package['price'], 'renewal', $startDate, $expiryDate);
            
            // If user was expired/suspended, disconnect them so they reconnect with new settings
            $coaResult = null;
            if ($wasExpired) {
                // Disconnect first to force reconnection with new expiry/pool
                $coaResult = $this->disconnectUser($id);
                if (!$coaResult['success'] && ($coaResult['disconnected'] ?? 0) == 0) {
                    // No active session - user will get correct settings on next connect
                    $coaResult['note'] = 'No active session - settings will apply on next login';
                }
            }
            
            // Unblock IP on MikroTik for static/DHCP accounts
            $mikrotikResult = $this->updateMikroTikBlockedStatus($id, false);
            
            return [
                'success' => true, 
                'expiry_date' => $expiryDate,
                'disconnected' => $coaResult ? ($coaResult['disconnected'] ?? 0) : 0,
                'coa_result' => $coaResult,
                'mikrotik_result' => $mikrotikResult
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function suspendSubscription(int $id, string $reason = ''): array {
        try {
            // Get current subscription to calculate days remaining
            $stmt = $this->db->prepare("SELECT expiry_date, status FROM radius_subscriptions WHERE id = ?");
            $stmt->execute([$id]);
            $sub = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$sub) {
                return ['success' => false, 'error' => 'Subscription not found'];
            }
            
            // Calculate days remaining at suspension
            $daysRemaining = 0;
            if ($sub['expiry_date']) {
                $expiryTime = strtotime($sub['expiry_date']);
                $daysRemaining = max(0, ceil(($expiryTime - time()) / 86400));
            }
            
            $stmt = $this->db->prepare("
                UPDATE radius_subscriptions 
                SET status = 'suspended', 
                    suspended_at = CURRENT_TIMESTAMP,
                    days_remaining_at_suspension = ?,
                    notes = COALESCE(notes, '') || ?::text, 
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([$daysRemaining, "\nSuspended: $reason (" . date('Y-m-d H:i') . ")", $id]);
            
            // Disconnect active sessions via CoA
            $disconnectResult = $this->sendCoAForSubscription($id);
            
            // Block IP on MikroTik for static/DHCP accounts
            $mikrotikResult = $this->updateMikroTikBlockedStatus($id, true, "Suspended: $reason");
            
            return [
                'success' => true, 
                'days_remaining' => $daysRemaining,
                'coa_result' => $disconnectResult,
                'mikrotik_result' => $mikrotikResult
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function unsuspendSubscription(int $id): array {
        try {
            // Get subscription to restore days
            $stmt = $this->db->prepare("
                SELECT suspended_at, days_remaining_at_suspension, expiry_date 
                FROM radius_subscriptions WHERE id = ?
            ");
            $stmt->execute([$id]);
            $sub = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$sub) {
                return ['success' => false, 'error' => 'Subscription not found'];
            }
            
            // Calculate new expiry: today + days remaining at suspension
            $daysRemaining = (int)($sub['days_remaining_at_suspension'] ?? 0);
            $newExpiry = date('Y-m-d H:i:s', strtotime("+{$daysRemaining} days"));
            
            // Calculate suspended duration for logging
            $suspendedDays = 0;
            if ($sub['suspended_at']) {
                $suspendedDays = ceil((time() - strtotime($sub['suspended_at'])) / 86400);
            }
            
            $stmt = $this->db->prepare("
                UPDATE radius_subscriptions 
                SET status = 'active', 
                    expiry_date = ?,
                    suspended_at = NULL,
                    days_remaining_at_suspension = NULL,
                    notes = COALESCE(notes, '') || ?::text, 
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([
                $newExpiry, 
                "\nReactivated after {$suspendedDays} days suspension (" . date('Y-m-d H:i') . ")", 
                $id
            ]);
            
            // Unblock IP on MikroTik for static/DHCP accounts
            $mikrotikResult = $this->updateMikroTikBlockedStatus($id, false);
            
            return [
                'success' => true, 
                'new_expiry' => $newExpiry,
                'days_restored' => $daysRemaining,
                'suspended_days' => $suspendedDays,
                'mikrotik_result' => $mikrotikResult
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    // ==================== Session Management ====================
    
    public function getActiveSessions(): array {
        $stmt = $this->db->query("
            SELECT s.*, sub.username, c.name as customer_name, n.name as nas_name
            FROM radius_sessions s
            LEFT JOIN radius_subscriptions sub ON s.subscription_id = sub.id
            LEFT JOIN customers c ON sub.customer_id = c.id
            LEFT JOIN radius_nas n ON s.nas_id = n.id
            WHERE s.status = 'active'
            ORDER BY s.session_start DESC
        ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getSessionHistory(int $subscriptionId, int $limit = 50): array {
        $stmt = $this->db->prepare("
            SELECT * FROM radius_sessions
            WHERE subscription_id = ?
            ORDER BY session_start DESC
            LIMIT ?
        ");
        $stmt->execute([$subscriptionId, $limit]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function recordSessionStart(array $data): array {
        try {
            $sub = $this->getSubscriptionByUsername($data['username']);
            if (!$sub) {
                return ['success' => false, 'error' => 'Subscription not found'];
            }
            
            $sessionMac = $data['mac_address'] ?? '';
            
            $stmt = $this->db->prepare("
                INSERT INTO radius_sessions (subscription_id, acct_session_id, nas_id, nas_ip_address,
                    nas_port_id, framed_ip_address, mac_address, session_start, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, 'active')
            ");
            $stmt->execute([
                $sub['id'],
                $data['acct_session_id'],
                $data['nas_id'] ?? null,
                $data['nas_ip_address'] ?? '',
                $data['nas_port_id'] ?? '',
                $data['framed_ip_address'] ?? '',
                $sessionMac
            ]);
            
            // Auto-capture MAC if subscription doesn't have one yet
            if (empty($sub['mac_address']) && !empty($sessionMac)) {
                $this->db->prepare("UPDATE radius_subscriptions SET mac_address = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
                    ->execute([$sessionMac, $sub['id']]);
            }
            
            // Update subscription last session
            $this->db->prepare("UPDATE radius_subscriptions SET last_session_start = CURRENT_TIMESTAMP WHERE id = ?")->execute([$sub['id']]);
            
            return ['success' => true, 'session_id' => $this->db->lastInsertId()];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function recordSessionEnd(string $acctSessionId, array $data): array {
        try {
            $stmt = $this->db->prepare("
                UPDATE radius_sessions SET
                    session_end = CURRENT_TIMESTAMP,
                    session_duration = ?,
                    input_octets = ?,
                    output_octets = ?,
                    input_packets = ?,
                    output_packets = ?,
                    terminate_cause = ?,
                    status = 'closed'
                WHERE acct_session_id = ? AND status = 'active'
            ");
            $stmt->execute([
                $data['session_duration'] ?? 0,
                $data['input_octets'] ?? 0,
                $data['output_octets'] ?? 0,
                $data['input_packets'] ?? 0,
                $data['output_packets'] ?? 0,
                $data['terminate_cause'] ?? '',
                $acctSessionId
            ]);
            
            // Update subscription data usage
            $session = $this->db->prepare("SELECT subscription_id, input_octets, output_octets FROM radius_sessions WHERE acct_session_id = ?")->fetch(\PDO::FETCH_ASSOC);
            if ($session) {
                $usageMb = (($session['input_octets'] + $session['output_octets']) / 1024 / 1024);
                $this->db->prepare("UPDATE radius_subscriptions SET data_used_mb = data_used_mb + ?, last_session_end = CURRENT_TIMESTAMP WHERE id = ?")
                    ->execute([$usageMb, $session['subscription_id']]);
            }
            
            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    // ==================== Voucher Management ====================
    
    public function generateVouchers(int $packageId, int $count, int $createdBy): array {
        try {
            $package = $this->getPackage($packageId);
            if (!$package) {
                return ['success' => false, 'error' => 'Package not found'];
            }
            
            $batchId = 'BATCH-' . date('Ymd-His');
            $vouchers = [];
            
            for ($i = 0; $i < $count; $i++) {
                $code = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
                
                $stmt = $this->db->prepare("
                    INSERT INTO radius_vouchers (batch_id, code, package_id, validity_minutes, data_limit_mb, created_by)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $batchId,
                    $code,
                    $packageId,
                    $package['validity_days'] * 24 * 60,
                    $package['data_quota_mb'],
                    $createdBy
                ]);
                
                $vouchers[] = $code;
            }
            
            return ['success' => true, 'batch_id' => $batchId, 'vouchers' => $vouchers, 'count' => count($vouchers)];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function getVouchers(array $filters = []): array {
        $sql = "SELECT v.*, p.name as package_name, u.name as created_by_name
                FROM radius_vouchers v
                LEFT JOIN radius_packages p ON v.package_id = p.id
                LEFT JOIN users u ON v.created_by = u.id
                WHERE 1=1";
        $params = [];
        
        if (!empty($filters['status'])) {
            $sql .= " AND v.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['batch_id'])) {
            $sql .= " AND v.batch_id = ?";
            $params[] = $filters['batch_id'];
        }
        
        $sql .= " ORDER BY v.created_at DESC";
        
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT " . (int)$filters['limit'];
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function redeemVoucher(string $code, int $subscriptionId): array {
        try {
            $stmt = $this->db->prepare("SELECT * FROM radius_vouchers WHERE code = ? AND status = 'unused'");
            $stmt->execute([$code]);
            $voucher = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$voucher) {
                return ['success' => false, 'error' => 'Invalid or already used voucher'];
            }
            
            $expiresAt = date('Y-m-d H:i:s', strtotime("+{$voucher['validity_minutes']} minutes"));
            
            $stmt = $this->db->prepare("
                UPDATE radius_vouchers SET
                    status = 'used', used_by_subscription_id = ?, used_at = CURRENT_TIMESTAMP, expires_at = ?
                WHERE id = ?
            ");
            $stmt->execute([$subscriptionId, $expiresAt, $voucher['id']]);
            
            return ['success' => true, 'expires_at' => $expiresAt];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    // ==================== Dashboard Stats ====================
    
    public function getDashboardStats(): array {
        $stats = [];
        
        // Active subscriptions
        $stmt = $this->db->query("SELECT COUNT(*) FROM radius_subscriptions WHERE status = 'active'");
        $stats['active_subscriptions'] = $stmt->fetchColumn();
        
        // Suspended subscriptions
        $stmt = $this->db->query("SELECT COUNT(*) FROM radius_subscriptions WHERE status = 'suspended'");
        $stats['suspended_subscriptions'] = $stmt->fetchColumn();
        
        // Expired subscriptions
        $stmt = $this->db->query("SELECT COUNT(*) FROM radius_subscriptions WHERE status = 'active' AND expiry_date < CURRENT_DATE");
        $stats['expired_subscriptions'] = $stmt->fetchColumn();
        
        // Expiring in 7 days
        $stmt = $this->db->query("SELECT COUNT(*) FROM radius_subscriptions WHERE status = 'active' AND expiry_date BETWEEN CURRENT_DATE AND CURRENT_DATE + INTERVAL '7 days'");
        $stats['expiring_soon'] = $stmt->fetchColumn();
        
        // Active sessions
        $stmt = $this->db->query("SELECT COUNT(*) FROM radius_sessions WHERE status = 'active'");
        $stats['active_sessions'] = $stmt->fetchColumn();
        
        // NAS devices
        $stmt = $this->db->query("SELECT COUNT(*) FROM radius_nas WHERE is_active = TRUE");
        $stats['nas_devices'] = $stmt->fetchColumn();
        
        // Packages
        $stmt = $this->db->query("SELECT COUNT(*) FROM radius_packages WHERE is_active = TRUE");
        $stats['packages'] = $stmt->fetchColumn();
        
        // Unused vouchers
        $stmt = $this->db->query("SELECT COUNT(*) FROM radius_vouchers WHERE status = 'unused'");
        $stats['unused_vouchers'] = $stmt->fetchColumn();
        
        // Monthly revenue
        $stmt = $this->db->query("SELECT COALESCE(SUM(amount), 0) FROM radius_billing WHERE status = 'paid' AND created_at >= DATE_TRUNC('month', CURRENT_DATE)");
        $stats['monthly_revenue'] = $stmt->fetchColumn();
        
        // ARPU (Average Revenue Per User) - monthly revenue / active subscribers
        $stats['arpu'] = $stats['active_subscriptions'] > 0 
            ? round($stats['monthly_revenue'] / $stats['active_subscriptions'], 2) 
            : 0;
        
        // Last month revenue for comparison
        $stmt = $this->db->query("SELECT COALESCE(SUM(amount), 0) FROM radius_billing WHERE status = 'paid' AND created_at >= DATE_TRUNC('month', CURRENT_DATE - INTERVAL '1 month') AND created_at < DATE_TRUNC('month', CURRENT_DATE)");
        $stats['last_month_revenue'] = $stmt->fetchColumn();
        
        // Revenue growth percentage
        $stats['revenue_growth'] = $stats['last_month_revenue'] > 0 
            ? round((($stats['monthly_revenue'] - $stats['last_month_revenue']) / $stats['last_month_revenue']) * 100, 1)
            : 0;
        
        // Total subscribers
        $stmt = $this->db->query("SELECT COUNT(*) FROM radius_subscriptions");
        $stats['total_subscriptions'] = $stmt->fetchColumn();
        
        // New subscribers this month
        $stmt = $this->db->query("SELECT COUNT(*) FROM radius_subscriptions WHERE created_at >= DATE_TRUNC('month', CURRENT_DATE)");
        $stats['new_this_month'] = $stmt->fetchColumn();
        
        // Online subscribers (active sessions count)
        $stmt = $this->db->query("SELECT COUNT(DISTINCT subscription_id) FROM radius_sessions WHERE session_end IS NULL");
        $stats['online_now'] = $stmt->fetchColumn();
        
        // Today's data usage (GB)
        $stmt = $this->db->query("SELECT COALESCE(SUM(download_mb + upload_mb), 0) / 1024 FROM radius_usage_logs WHERE log_date = CURRENT_DATE");
        $stats['today_data_gb'] = round($stmt->fetchColumn(), 2);
        
        return $stats;
    }
    
    public function getRecentActivity(int $limit = 10): array {
        $stmt = $this->db->prepare("
            SELECT 'session' as type, s.session_start as timestamp, sub.username, 
                   CASE WHEN s.status = 'active' THEN 'Session started' ELSE 'Session ended' END as action,
                   n.name as nas_name
            FROM radius_sessions s
            LEFT JOIN radius_subscriptions sub ON s.subscription_id = sub.id
            LEFT JOIN radius_nas n ON s.nas_id = n.id
            ORDER BY s.session_start DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    // ==================== Billing ====================
    
    private function createBillingRecord(int $subscriptionId, int $packageId, float $amount, string $type, string $start, string $end): void {
        $invoiceNumber = 'RAD-' . date('Ymd') . '-' . str_pad($subscriptionId, 5, '0', STR_PAD_LEFT);
        
        $stmt = $this->db->prepare("
            INSERT INTO radius_billing (subscription_id, package_id, amount, billing_type, period_start, period_end, invoice_number, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([$subscriptionId, $packageId, $amount, $type, $start, $end, $invoiceNumber]);
    }
    
    public function getBillingHistory(?int $subscriptionId = null, int $limit = 50): array {
        $sql = "SELECT b.*, s.username, c.name as customer_name, p.name as package_name
                FROM radius_billing b
                LEFT JOIN radius_subscriptions s ON b.subscription_id = s.id
                LEFT JOIN customers c ON s.customer_id = c.id
                LEFT JOIN radius_packages p ON b.package_id = p.id
                WHERE 1=1";
        $params = [];
        
        if ($subscriptionId) {
            $sql .= " AND b.subscription_id = ?";
            $params[] = $subscriptionId;
        }
        
        $sql .= " ORDER BY b.created_at DESC LIMIT ?";
        $params[] = $limit;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    // ==================== RADIUS Authentication (for integration) ====================
    
    private function logAuthAttempt(?int $subscriptionId, string $username, string $nasIp, string $mac, string $result, ?string $reason = null, ?string $replyMessage = null, ?array $attributes = null): void {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO radius_auth_logs (subscription_id, username, nas_ip_address, mac_address, auth_result, reject_reason, reply_message, attributes, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $subscriptionId,
                $username,
                $nasIp ?: null,
                $mac ?: null,
                $result,
                $reason,
                $replyMessage,
                $attributes ? json_encode($attributes) : null
            ]);
        } catch (\Exception $e) {
            // Don't fail auth if logging fails
        }
    }
    
    public function getAuthLogs(int $subscriptionId, int $limit = 20): array {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM radius_auth_logs 
                WHERE subscription_id = ? 
                ORDER BY created_at DESC 
                LIMIT ?
            ");
            $stmt->execute([$subscriptionId, $limit]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            // Table may not exist yet
            return [];
        }
    }
    
    public function authenticate(string $username, string $password, string $nasIp = '', string $callingStationId = ''): array {
        $sub = $this->getSubscriptionByUsername($username);
        
        // Get expired pool settings upfront
        $useExpiredPool = $this->getSetting('use_expired_pool') === 'true';
        $allowUnknownUsers = $this->getSetting('allow_unknown_expired_pool') === 'true';
        $expiredPoolName = $this->getSetting('expired_ip_pool') ?: 'expired-pool';
        $expiredRateLimit = $this->getSetting('expired_rate_limit') ?: '256k/256k';
        
        if (!$sub) {
            // If unknown users should be allowed with expired pool
            if ($useExpiredPool && $allowUnknownUsers) {
                $attrs = [
                    'Framed-Pool' => $expiredPoolName,
                    'Mikrotik-Rate-Limit' => $expiredRateLimit,
                    'Session-Timeout' => 300,
                    'Acct-Interim-Interval' => 60
                ];
                $this->logAuthAttempt(null, $username, $nasIp, $callingStationId, 'Accept', 'Unknown user - expired pool', null, $attrs);
                return [
                    'success' => true,
                    'reply' => 'Access-Accept',
                    'unknown_user' => true,
                    'attributes' => $attrs,
                    'subscription' => null
                ];
            }
            $this->logAuthAttempt(null, $username, $nasIp, $callingStationId, 'Reject', 'User not found');
            return ['success' => false, 'reply' => 'Access-Reject', 'reason' => 'User not found'];
        }
        
        // Get expired/suspended pool settings
        $expiredPoolName = $this->getSetting('expired_ip_pool') ?: 'expired-pool';
        $expiredRateLimit = $this->getSetting('expired_rate_limit') ?: '256k/256k';
        $useExpiredPool = $this->getSetting('use_expired_pool') === 'true';
        
        // Suspended accounts - bypass password check, just return expired pool
        if ($sub['status'] === 'suspended') {
            $attrs = [
                'Framed-Pool' => $expiredPoolName,
                'Mikrotik-Rate-Limit' => $expiredRateLimit,
                'Session-Timeout' => 300,
                'Acct-Interim-Interval' => 60
            ];
            $this->logAuthAttempt($sub['id'], $username, $nasIp, $callingStationId, 'Accept', 'Suspended - expired pool', null, $attrs);
            return [
                'success' => true,
                'reply' => 'Access-Accept',
                'suspended' => true,
                'attributes' => $attrs,
                'subscription' => $sub
            ];
        }
        
        // Check password (only for non-suspended accounts)
        if ($this->decrypt($sub['password_encrypted']) !== $password) {
            $this->logAuthAttempt($sub['id'], $username, $nasIp, $callingStationId, 'Reject', 'Invalid password');
            return ['success' => false, 'reply' => 'Access-Reject', 'reason' => 'Invalid password'];
        }
        
        // Check MAC binding - only enforce for PPPoE if mac_binding is enabled
        $enforceMacBinding = $this->getSetting('enforce_mac_binding') === 'true';
        $isHotspot = ($sub['access_type'] ?? '') === 'hotspot';
        
        if ($enforceMacBinding && !$isHotspot && !empty($sub['mac_address']) && !empty($callingStationId)) {
            $normalizedSubMac = strtoupper(str_replace(['-', '.'], ':', $sub['mac_address']));
            $normalizedCallingMac = strtoupper(str_replace(['-', '.'], ':', $callingStationId));
            if ($normalizedSubMac !== $normalizedCallingMac) {
                $this->logAuthAttempt($sub['id'], $username, $nasIp, $callingStationId, 'Reject', 'MAC address mismatch');
                return ['success' => false, 'reply' => 'Access-Reject', 'reason' => 'MAC address mismatch'];
            }
        }
        
        // Check if expired
        $isExpired = false;
        if ($sub['status'] === 'expired' || ($sub['expiry_date'] && strtotime($sub['expiry_date']) < time())) {
            // Check grace period
            $graceDays = $sub['grace_period_days'] ?? 0;
            $graceEnd = strtotime($sub['expiry_date'] . " +{$graceDays} days");
            if (time() > $graceEnd) {
                $isExpired = true;
            }
        }
        
        // Handle expired users
        if ($isExpired) {
            if ($useExpiredPool) {
                // Accept with restricted pool for captive portal
                $attrs = [
                    'Framed-Pool' => $expiredPoolName,
                    'Mikrotik-Rate-Limit' => $expiredRateLimit,
                    'Session-Timeout' => 300, // 5 min sessions to force re-auth
                    'Acct-Interim-Interval' => 60
                ];
                $this->logAuthAttempt($sub['id'], $username, $nasIp, $callingStationId, 'Accept', 'Expired - expired pool', null, $attrs);
                return [
                    'success' => true,
                    'reply' => 'Access-Accept',
                    'expired' => true,
                    'attributes' => $attrs,
                    'subscription' => $sub
                ];
            } else {
                $this->logAuthAttempt($sub['id'], $username, $nasIp, $callingStationId, 'Reject', 'Subscription expired');
                return ['success' => false, 'reply' => 'Access-Reject', 'reason' => 'Subscription expired'];
            }
        }
        
        // Check quota
        if ($sub['data_quota_mb'] && $sub['data_used_mb'] >= $sub['data_quota_mb']) {
            if (!$sub['fup_enabled']) {
                if ($useExpiredPool) {
                    // Put quota-exhausted users in expired pool too
                    $attrs = [
                        'Framed-Pool' => $expiredPoolName,
                        'Mikrotik-Rate-Limit' => $expiredRateLimit,
                        'Session-Timeout' => 300
                    ];
                    $this->logAuthAttempt($sub['id'], $username, $nasIp, $callingStationId, 'Accept', 'Quota exhausted - expired pool', null, $attrs);
                    return [
                        'success' => true,
                        'reply' => 'Access-Accept',
                        'quota_exhausted' => true,
                        'attributes' => $attrs,
                        'subscription' => $sub
                    ];
                } else {
                    $this->logAuthAttempt($sub['id'], $username, $nasIp, $callingStationId, 'Reject', 'Data quota exhausted');
                    return ['success' => false, 'reply' => 'Access-Reject', 'reason' => 'Data quota exhausted'];
                }
            }
        }
        
        // Build RADIUS attributes for active users
        $attributes = [
            'Mikrotik-Rate-Limit' => $this->buildRateLimit($sub),
        ];
        
        // Add address pool from package if set
        if (!empty($sub['address_pool'])) {
            $attributes['Framed-Pool'] = $sub['address_pool'];
        }
        
        if ($sub['static_ip']) {
            $attributes['Framed-IP-Address'] = $sub['static_ip'];
        }
        
        // Auto-assign NAS ID based on the NAS IP the user connected through
        if (!empty($nasIp)) {
            $nasStmt = $this->db->prepare("SELECT id FROM radius_nas WHERE ip_address = ? LIMIT 1");
            $nasStmt->execute([$nasIp]);
            $nasRecord = $nasStmt->fetch(\PDO::FETCH_ASSOC);
            if ($nasRecord && (empty($sub['nas_id']) || $sub['nas_id'] != $nasRecord['id'])) {
                $updateStmt = $this->db->prepare("UPDATE radius_subscriptions SET nas_id = ? WHERE id = ?");
                $updateStmt->execute([$nasRecord['id'], $sub['id']]);
            }
        }
        
        // Auto-save MAC address for hotspot users (no pre-registration needed)
        if (!empty($callingStationId)) {
            $normalizedMac = strtoupper(str_replace(['-', '.'], ':', $callingStationId));
            if (empty($sub['mac_address']) || $sub['mac_address'] !== $normalizedMac) {
                $updateStmt = $this->db->prepare("UPDATE radius_subscriptions SET mac_address = ? WHERE id = ?");
                $updateStmt->execute([$normalizedMac, $sub['id']]);
            }
        }
        
        // Log successful authentication
        $this->logAuthAttempt($sub['id'], $username, $nasIp, $callingStationId, 'Accept', null, null, $attributes);
        
        return [
            'success' => true,
            'reply' => 'Access-Accept',
            'attributes' => $attributes,
            'subscription' => $sub
        ];
    }
    
    private function buildRateLimit(array $sub): string {
        $download = $sub['download_speed'] ?? '10M';
        $upload = $sub['upload_speed'] ?? '5M';
        
        // Priority 1: Check for active subscription-level speed override (timed)
        if (!empty($sub['speed_override'])) {
            $expiresAt = $sub['override_expires_at'] ?? null;
            if (!$expiresAt || strtotime($expiresAt) > time()) {
                // Override is still active - parse it (format: download/upload e.g., "10M/5M")
                $parts = explode('/', $sub['speed_override']);
                if (count($parts) === 2) {
                    return $sub['speed_override']; // Already in correct format
                }
            }
        }
        
        // Priority 2: Check if FUP applies (quota exceeded)
        if (!empty($sub['fup_enabled']) && !empty($sub['fup_quota_mb']) && ($sub['data_used_mb'] ?? 0) >= ($sub['fup_quota_mb'] ?? 0)) {
            $download = $sub['fup_download_speed'] ?? '1M';
            $upload = $sub['fup_upload_speed'] ?? '512k';
            return "{$upload}/{$download}";
        }
        
        // Priority 3: Check for time-based package speed schedule
        $schedule = $this->getActivePackageSchedule($sub['package_id'] ?? null);
        if ($schedule) {
            $download = $schedule['download_speed'];
            $upload = $schedule['upload_speed'];
        }
        
        return "{$upload}/{$download}";
    }
    
    public function getActivePackageSchedule(?int $packageId): ?array {
        if (!$packageId) return null;
        
        $currentTime = date('H:i:s');
        $currentDay = (string)date('w'); // 0=Sun, 1=Mon, ..., 6=Sat
        
        // Handle both normal schedules (09:00-17:00) and overnight schedules (22:00-06:00)
        $stmt = $this->db->prepare("
            SELECT download_speed, upload_speed, name
            FROM radius_package_schedules
            WHERE package_id = ? 
            AND is_active = TRUE
            AND (
                -- Normal schedule (start < end): e.g., 09:00-17:00
                (start_time <= end_time AND ?::time >= start_time AND ?::time <= end_time)
                OR
                -- Overnight schedule (start > end): e.g., 22:00-06:00
                (start_time > end_time AND (?::time >= start_time OR ?::time <= end_time))
            )
            AND (
                days_of_week IS NULL 
                OR days_of_week = '' 
                OR days_of_week LIKE ?
            )
            ORDER BY priority DESC
            LIMIT 1
        ");
        $stmt->execute([$packageId, $currentTime, $currentTime, $currentTime, $currentTime, '%' . $currentDay . '%']);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    
    public function sendSpeedUpdateCoA(int $subscriptionId, ?string $customRateLimit = null): array {
        $sub = $this->getSubscription($subscriptionId);
        if (!$sub) {
            return ['success' => false, 'error' => 'Subscription not found'];
        }
        
        // Get NAS info - try subscription's nas_id first
        $nas = null;
        if (!empty($sub['nas_id'])) {
            $stmt = $this->db->prepare("SELECT ip_address, secret FROM radius_nas WHERE id = ?");
            $stmt->execute([$sub['nas_id']]);
            $nas = $stmt->fetch(\PDO::FETCH_ASSOC);
        }
        
        // If not found, try to get NAS from active session
        if (!$nas) {
            $stmt = $this->db->prepare("
                SELECT rn.ip_address, rn.secret 
                FROM radius_sessions rs
                JOIN radius_nas rn ON rs.nas_id = rn.id OR rs.nas_ip_address = rn.ip_address
                WHERE rs.subscription_id = ? AND rs.session_end IS NULL
                ORDER BY rs.session_start DESC LIMIT 1
            ");
            $stmt->execute([$subscriptionId]);
            $nas = $stmt->fetch(\PDO::FETCH_ASSOC);
        }
        
        // If still not found, try default active NAS
        if (!$nas) {
            $stmt = $this->db->query("SELECT ip_address, secret FROM radius_nas WHERE is_active = true ORDER BY id LIMIT 1");
            $nas = $stmt->fetch(\PDO::FETCH_ASSOC);
        }
        
        if (!$nas) {
            return ['success' => false, 'error' => 'NAS not found - please assign a NAS to this subscription or add an active NAS device'];
        }
        
        // Build CoA with new rate limit (use custom if provided, otherwise build from package)
        $rateLimit = $customRateLimit ?: $this->buildRateLimit($sub);
        
        // Send CoA via OLT service (routes through WireGuard VPN)
        $oltServiceUrl = (getenv('OLT_SERVICE_URL') ?: 'http://localhost:3002') . '/radius/coa';
        
        $payload = [
            'nasIp' => $nas['ip_address'],
            'nasPort' => 3799,
            'secret' => $nas['secret'],
            'username' => $sub['username'],
            'rateLimit' => $rateLimit
        ];
        
        try {
            $ch = curl_init($oltServiceUrl);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10
            ]);
            
            $response = curl_exec($ch);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($curlError) {
                return ['success' => false, 'error' => 'OLT service unavailable: ' . $curlError, 'target_ip' => $nas['ip_address']];
            }
            
            $result = json_decode($response, true);
            if ($result && $result['success']) {
                return ['success' => true, 'rate_limit' => $rateLimit, 'output' => $result['response'] ?? 'CoA-ACK', 'target_ip' => $nas['ip_address'], 'via' => 'vpn'];
            }
            
            return ['success' => false, 'error' => $result['error'] ?? 'CoA failed', 'target_ip' => $nas['ip_address']];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Exception: ' . $e->getMessage(), 'target_ip' => $nas['ip_address']];
        }
    }
    
    private function sendSpeedUpdateCoADirect(array $sub, array $nas, string $rateLimit): array {
        $attrs = [
            'User-Name' => $sub['username'],
            'Mikrotik-Rate-Limit' => $rateLimit
        ];
        
        try {
            $client = new RadiusClient($nas['ip_address'], $nas['secret'], 3799, 5);
            $result = $client->coa($attrs);
            
            if ($result['success']) {
                return ['success' => true, 'rate_limit' => $rateLimit, 'output' => $result['response'] ?? 'CoA-ACK', 'target_ip' => $nas['ip_address'], 'via' => 'direct'];
            }
            
            $errorResponse = ['success' => false, 'error' => $result['error'] ?? 'CoA failed', 'target_ip' => $nas['ip_address']];
            if (!empty($result['diagnostic'])) {
                $errorResponse['diagnostic'] = $result['diagnostic'];
            }
            return $errorResponse;
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Exception: ' . $e->getMessage(), 'target_ip' => $nas['ip_address']];
        }
    }
    
    // ==================== Timed Speed Override ====================
    
    public function setSpeedOverride(int $subscriptionId, string $rateLimit, ?int $durationHours = null): bool {
        $expiresAt = $durationHours ? date('Y-m-d H:i:s', strtotime("+{$durationHours} hours")) : null;
        
        $stmt = $this->db->prepare("
            UPDATE radius_subscriptions 
            SET speed_override = ?, override_expires_at = ?, updated_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        return $stmt->execute([$rateLimit, $expiresAt, $subscriptionId]);
    }
    
    public function clearSpeedOverride(int $subscriptionId): bool {
        $stmt = $this->db->prepare("
            UPDATE radius_subscriptions 
            SET speed_override = NULL, override_expires_at = NULL, updated_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        return $stmt->execute([$subscriptionId]);
    }
    
    public function clearExpiredOverrides(): int {
        $stmt = $this->db->prepare("
            UPDATE radius_subscriptions 
            SET speed_override = NULL, override_expires_at = NULL, updated_at = CURRENT_TIMESTAMP 
            WHERE override_expires_at IS NOT NULL AND override_expires_at < CURRENT_TIMESTAMP
        ");
        $stmt->execute();
        return $stmt->rowCount();
    }
    
    // ==================== Package Speed Schedules ====================
    
    public function getPackageSchedules(int $packageId): array {
        $stmt = $this->db->prepare("
            SELECT * FROM radius_package_schedules 
            WHERE package_id = ? 
            ORDER BY priority DESC, start_time
        ");
        $stmt->execute([$packageId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function createPackageSchedule(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO radius_package_schedules 
            (package_id, name, start_time, end_time, days_of_week, download_speed, upload_speed, priority, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['package_id'],
            $data['name'],
            $data['start_time'],
            $data['end_time'],
            $data['days_of_week'] ?? '0123456',
            $data['download_speed'],
            $data['upload_speed'],
            $data['priority'] ?? 0,
            $data['is_active'] ?? true
        ]);
        return (int)$this->db->lastInsertId();
    }
    
    public function updatePackageSchedule(int $id, array $data): bool {
        $stmt = $this->db->prepare("
            UPDATE radius_package_schedules SET
                name = ?, start_time = ?, end_time = ?, days_of_week = ?,
                download_speed = ?, upload_speed = ?, priority = ?, is_active = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        return $stmt->execute([
            $data['name'],
            $data['start_time'],
            $data['end_time'],
            $data['days_of_week'] ?? '0123456',
            $data['download_speed'],
            $data['upload_speed'],
            $data['priority'] ?? 0,
            $data['is_active'] ?? true,
            $id
        ]);
    }
    
    public function deletePackageSchedule(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM radius_package_schedules WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    // ==================== MAC Authentication (Hotspot) ====================
    
    public function authenticateByMAC(string $mac, string $nasIp = ''): array {
        // Normalize MAC address
        $mac = strtoupper(preg_replace('/[^A-Fa-f0-9]/', '', $mac));
        if (strlen($mac) !== 12) {
            return ['success' => false, 'reply' => 'Access-Reject', 'reason' => 'Invalid MAC format'];
        }
        $macFormatted = implode(':', str_split($mac, 2));
        
        // Check if MAC auth is enabled
        if ($this->getSetting('hotspot_mac_auth') !== 'true') {
            return ['success' => false, 'reply' => 'Access-Reject', 'reason' => 'MAC auth disabled'];
        }
        
        // Find subscription by MAC
        $stmt = $this->db->prepare("
            SELECT s.*, c.name as customer_name, c.phone as customer_phone,
                   p.name as package_name, p.download_speed, p.upload_speed, p.address_pool,
                   p.data_quota_mb, p.fup_enabled, p.fup_quota_mb, p.fup_download_speed, p.fup_upload_speed
            FROM radius_subscriptions s
            LEFT JOIN customers c ON s.customer_id = c.id
            LEFT JOIN radius_packages p ON s.package_id = p.id
            WHERE s.mac_address = ? AND s.status = 'active'
            LIMIT 1
        ");
        $stmt->execute([$macFormatted]);
        $sub = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$sub) {
            return ['success' => false, 'reply' => 'Access-Reject', 'reason' => 'MAC not registered'];
        }
        
        // Check expiry
        if ($sub['expiry_date'] && strtotime($sub['expiry_date']) < time()) {
            $useExpiredPool = $this->getSetting('use_expired_pool') === 'true';
            if ($useExpiredPool) {
                return [
                    'success' => true,
                    'reply' => 'Access-Accept',
                    'expired' => true,
                    'mac_auth' => true,
                    'attributes' => [
                        'Framed-Pool' => $this->getSetting('expired_ip_pool') ?: 'expired-pool',
                        'Mikrotik-Rate-Limit' => $this->getSetting('expired_rate_limit') ?: '256k/256k',
                        'Session-Timeout' => 300
                    ],
                    'subscription' => $sub
                ];
            }
            return ['success' => false, 'reply' => 'Access-Reject', 'reason' => 'Subscription expired'];
        }
        
        // Build attributes
        $attributes = [
            'Mikrotik-Rate-Limit' => $this->buildRateLimit($sub),
        ];
        
        if (!empty($sub['address_pool'])) {
            $attributes['Framed-Pool'] = $sub['address_pool'];
        }
        
        if ($sub['static_ip']) {
            $attributes['Framed-IP-Address'] = $sub['static_ip'];
        }
        
        // Auto-assign NAS ID based on the NAS IP the user connected through
        if (!empty($nasIp)) {
            $nasStmt = $this->db->prepare("SELECT id FROM radius_nas WHERE ip_address = ? LIMIT 1");
            $nasStmt->execute([$nasIp]);
            $nasRecord = $nasStmt->fetch(\PDO::FETCH_ASSOC);
            if ($nasRecord && (empty($sub['nas_id']) || $sub['nas_id'] != $nasRecord['id'])) {
                $updateStmt = $this->db->prepare("UPDATE radius_subscriptions SET nas_id = ? WHERE id = ?");
                $updateStmt->execute([$nasRecord['id'], $sub['id']]);
            }
        }
        
        return [
            'success' => true,
            'reply' => 'Access-Accept',
            'mac_auth' => true,
            'attributes' => $attributes,
            'subscription' => $sub
        ];
    }
    
    public function authenticateVoucher(string $code): array {
        $code = strtoupper(trim($code));
        
        $stmt = $this->db->prepare("
            SELECT v.*, p.name as package_name, p.download_speed, p.upload_speed, 
                   p.validity_days, p.data_quota_mb, p.address_pool
            FROM radius_vouchers v
            LEFT JOIN radius_packages p ON v.package_id = p.id
            WHERE v.code = ? AND v.status = 'unused'
            LIMIT 1
        ");
        $stmt->execute([$code]);
        $voucher = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$voucher) {
            return ['success' => false, 'error' => 'Invalid or already used voucher'];
        }
        
        // Mark voucher as used
        $this->db->prepare("
            UPDATE radius_vouchers SET status = 'used', used_at = CURRENT_TIMESTAMP WHERE id = ?
        ")->execute([$voucher['id']]);
        
        // Calculate expiry
        $expiryDate = date('Y-m-d H:i:s', strtotime("+{$voucher['validity_days']} days"));
        
        // Build attributes
        $attributes = [
            'Mikrotik-Rate-Limit' => "{$voucher['upload_speed']}/{$voucher['download_speed']}",
            'Session-Timeout' => $voucher['validity_days'] * 86400,
        ];
        
        if (!empty($voucher['address_pool'])) {
            $attributes['Framed-Pool'] = $voucher['address_pool'];
        }
        
        return [
            'success' => true,
            'reply' => 'Access-Accept',
            'voucher' => true,
            'voucher_id' => $voucher['id'],
            'expiry' => $expiryDate,
            'attributes' => $attributes,
            'package' => $voucher
        ];
    }
    
    public function getSubscriptionByPhone(string $phone): ?array {
        // Normalize phone
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (substr($phone, 0, 1) === '0') {
            $phone = '254' . substr($phone, 1);
        }
        
        $stmt = $this->db->prepare("
            SELECT s.*, c.name as customer_name, c.phone as customer_phone,
                   p.name as package_name, p.download_speed, p.upload_speed, p.address_pool,
                   p.data_quota_mb
            FROM radius_subscriptions s
            LEFT JOIN customers c ON s.customer_id = c.id
            LEFT JOIN radius_packages p ON s.package_id = p.id
            WHERE REPLACE(REPLACE(c.phone, '+', ''), ' ', '') = ?
               OR REPLACE(REPLACE(c.phone, '+', ''), ' ', '') LIKE ?
            LIMIT 1
        ");
        $stmt->execute([$phone, '%' . substr($phone, -9)]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    
    public function registerMACForHotspot(int $subscriptionId, string $mac): array {
        $mac = strtoupper(preg_replace('/[^A-Fa-f0-9]/', '', $mac));
        if (strlen($mac) !== 12) {
            return ['success' => false, 'error' => 'Invalid MAC address'];
        }
        $macFormatted = implode(':', str_split($mac, 2));
        
        // Check if MAC already registered to another subscription
        $existing = $this->db->prepare("SELECT id, username FROM radius_subscriptions WHERE mac_address = ? AND id != ?");
        $existing->execute([$macFormatted, $subscriptionId]);
        if ($existing->fetch()) {
            return ['success' => false, 'error' => 'MAC already registered to another account'];
        }
        
        $stmt = $this->db->prepare("UPDATE radius_subscriptions SET mac_address = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$macFormatted, $subscriptionId]);
        
        return ['success' => true, 'mac' => $macFormatted];
    }
    
    // ==================== Automation ====================
    
    public function processExpiredSubscriptions(): array {
        $processed = 0;
        $ipsReleased = 0;
        $coaSent = 0;
        
        $useExpiredPool = $this->getSetting('use_expired_pool') === 'true';
        $expiredPoolName = $this->getSetting('expired_ip_pool') ?: 'expired-pool';
        $expiredRateLimit = $this->getSetting('expired_rate_limit') ?: '64k/64k';
        
        // Get expired subscriptions past grace period
        $stmt = $this->db->query("
            SELECT id, grace_period_days, expiry_date, static_ip FROM radius_subscriptions 
            WHERE status = 'active' 
            AND expiry_date < CURRENT_DATE - INTERVAL '1 day' * grace_period_days
        ");
        
        while ($sub = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            if ($useExpiredPool) {
                // Send CoA to move user to expired pool (don't disconnect, redirect to captive portal)
                $coaResult = $this->sendExpiredPoolCoA($sub['id'], $expiredPoolName, $expiredRateLimit);
                if ($coaResult['success']) {
                    $coaSent++;
                }
            } else {
                // Disconnect active sessions
                $this->disconnectUser($sub['id']);
            }
            
            // Release static IP back to pool if assigned
            $releaseIp = !empty($sub['static_ip']);
            
            // Update subscription: set expired and clear static IP
            $this->db->prepare("
                UPDATE radius_subscriptions 
                SET status = 'expired', 
                    static_ip = NULL,
                    updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ")->execute([$sub['id']]);
            
            // Block IP on MikroTik for static/DHCP accounts
            $this->updateMikroTikBlockedStatus($sub['id'], true, "Expired");
            
            if ($releaseIp) {
                $ipsReleased++;
            }
            $processed++;
        }
        
        return ['success' => true, 'processed' => $processed, 'ips_released' => $ipsReleased, 'coa_sent' => $coaSent];
    }
    
    public function sendExpiredPoolCoA(int $subscriptionId, ?string $poolName = null, ?string $rateLimit = null): array {
        $sub = $this->getSubscription($subscriptionId);
        if (!$sub) {
            return ['success' => false, 'error' => 'Subscription not found'];
        }
        
        $poolName = $poolName ?: $this->getSetting('expired_ip_pool') ?: 'expired-pool';
        $rateLimit = $rateLimit ?: $this->getSetting('expired_rate_limit') ?: '64k/64k';
        
        // Get NAS info
        $nas = null;
        if (!empty($sub['nas_id'])) {
            $stmt = $this->db->prepare("SELECT ip_address, secret FROM radius_nas WHERE id = ?");
            $stmt->execute([$sub['nas_id']]);
            $nas = $stmt->fetch(\PDO::FETCH_ASSOC);
        }
        
        if (!$nas) {
            $stmt = $this->db->prepare("
                SELECT rn.ip_address, rn.secret 
                FROM radius_sessions rs
                JOIN radius_nas rn ON rs.nas_id = rn.id OR rs.nas_ip_address = rn.ip_address
                WHERE rs.subscription_id = ? AND rs.session_end IS NULL
                ORDER BY rs.session_start DESC LIMIT 1
            ");
            $stmt->execute([$subscriptionId]);
            $nas = $stmt->fetch(\PDO::FETCH_ASSOC);
        }
        
        if (!$nas) {
            return ['success' => false, 'error' => 'NAS not found'];
        }
        
        // Send CoA via OLT service with Framed-Pool
        $oltServiceUrl = (getenv('OLT_SERVICE_URL') ?: 'http://localhost:3002') . '/radius/coa';
        
        $payload = [
            'nasIp' => $nas['ip_address'],
            'nasPort' => 3799,
            'secret' => $nas['secret'],
            'username' => $sub['username'],
            'rateLimit' => $rateLimit,
            'framedPool' => $poolName
        ];
        
        try {
            $ch = curl_init($oltServiceUrl);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10
            ]);
            
            $response = curl_exec($ch);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($curlError) {
                return ['success' => false, 'error' => "cURL error: $curlError"];
            }
            
            $result = json_decode($response, true);
            if ($result && $result['success']) {
                return ['success' => true, 'pool' => $poolName, 'rate_limit' => $rateLimit, 'output' => $result['response'] ?? 'CoA-ACK'];
            }
            
            return ['success' => false, 'error' => $result['error'] ?? 'CoA failed'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function sendReactivationCoA(int $subscriptionId): array {
        $sub = $this->getSubscription($subscriptionId);
        if (!$sub) {
            return ['success' => false, 'error' => 'Subscription not found'];
        }
        
        $package = $this->getPackage($sub['package_id']);
        if (!$package) {
            return ['success' => false, 'error' => 'Package not found'];
        }
        
        // Get NAS info
        $nas = null;
        if (!empty($sub['nas_id'])) {
            $stmt = $this->db->prepare("SELECT ip_address, secret FROM radius_nas WHERE id = ?");
            $stmt->execute([$sub['nas_id']]);
            $nas = $stmt->fetch(\PDO::FETCH_ASSOC);
        }
        
        if (!$nas) {
            $stmt = $this->db->prepare("
                SELECT rn.ip_address, rn.secret 
                FROM radius_sessions rs
                JOIN radius_nas rn ON rs.nas_id = rn.id OR rs.nas_ip_address = rn.ip_address
                WHERE rs.subscription_id = ? AND rs.session_end IS NULL
                ORDER BY rs.session_start DESC LIMIT 1
            ");
            $stmt->execute([$subscriptionId]);
            $nas = $stmt->fetch(\PDO::FETCH_ASSOC);
        }
        
        if (!$nas) {
            return ['success' => false, 'error' => 'NAS not found'];
        }
        
        // Build rate limit and get the package's IP pool
        $rateLimit = $this->buildRateLimit($sub);
        $poolName = $package['address_pool'] ?? null;
        
        // Send CoA via OLT service with new pool and speed
        $oltServiceUrl = (getenv('OLT_SERVICE_URL') ?: 'http://localhost:3002') . '/radius/coa';
        
        $payload = [
            'nasIp' => $nas['ip_address'],
            'nasPort' => 3799,
            'secret' => $nas['secret'],
            'username' => $sub['username'],
            'rateLimit' => $rateLimit
        ];
        
        if ($poolName) {
            $payload['framedPool'] = $poolName;
        }
        
        try {
            $ch = curl_init($oltServiceUrl);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10
            ]);
            
            $response = curl_exec($ch);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($curlError) {
                return ['success' => false, 'error' => "cURL error: $curlError"];
            }
            
            $result = json_decode($response, true);
            if ($result && $result['success']) {
                return ['success' => true, 'pool' => $poolName, 'rate_limit' => $rateLimit, 'output' => $result['response'] ?? 'CoA-ACK'];
            }
            
            return ['success' => false, 'error' => $result['error'] ?? 'CoA failed'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function getSubscriberOnlineStatus(int $subscriptionId): bool {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM radius_sessions 
            WHERE subscription_id = ? AND session_end IS NULL
        ");
        $stmt->execute([$subscriptionId]);
        return (int)$stmt->fetchColumn() > 0;
    }
    
    public function getOnlineSubscribers(): array {
        $stmt = $this->db->query("
            SELECT subscription_id, framed_ip_address, mac_address, session_start
            FROM radius_sessions 
            WHERE session_end IS NULL
            ORDER BY session_start DESC
        ");
        $sessions = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $result = [];
        foreach ($sessions as $s) {
            $result[$s['subscription_id']] = [
                'ip' => $s['framed_ip_address'],
                'mac' => $s['mac_address'],
                'start' => $s['session_start']
            ];
        }
        return $result;
    }
    
    public function processAutoRenewals(): array {
        $renewed = 0;
        $disconnected = 0;
        
        // Get subscriptions with auto_renew that expired today
        $stmt = $this->db->query("
            SELECT s.id, s.package_id, s.credit_balance, p.price as package_price, 
                   b.status as billing_status 
            FROM radius_subscriptions s
            LEFT JOIN radius_packages p ON s.package_id = p.id
            LEFT JOIN radius_billing b ON b.subscription_id = s.id AND b.period_end = s.expiry_date
            WHERE s.auto_renew = TRUE AND s.expiry_date = CURRENT_DATE AND s.status = 'active'
            AND (b.status IS NULL OR b.status = 'paid')
        ");
        
        while ($sub = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $walletBalance = (float)($sub['credit_balance'] ?? 0);
            $packagePrice = (float)($sub['package_price'] ?? 0);
            
            // Check if wallet has enough for renewal
            if ($walletBalance >= $packagePrice && $packagePrice > 0) {
                // Deduct from wallet
                $newBalance = $walletBalance - $packagePrice;
                $updateStmt = $this->db->prepare("UPDATE radius_subscriptions SET credit_balance = ? WHERE id = ?");
                $updateStmt->execute([$newBalance, $sub['id']]);
                
                $result = $this->renewSubscription($sub['id']);
                if ($result['success']) {
                    $renewed++;
                    
                    // Disconnect user after wallet-based auto-renewal
                    $disconnectResult = $this->disconnectSubscription($sub['id']);
                    if ($disconnectResult['disconnected'] > 0) {
                        $disconnected++;
                    }
                }
            }
        }
        
        return ['success' => true, 'renewed' => $renewed, 'disconnected' => $disconnected];
    }
    
    // ==================== Expiry & Alerts ====================
    
    public function getExpiringSubscriptions(int $days = 7): array {
        $stmt = $this->db->prepare("
            SELECT s.*, c.name as customer_name, c.phone as customer_phone, c.email as customer_email,
                   p.name as package_name, p.price as package_price,
                   (s.expiry_date::date - CURRENT_DATE) as days_remaining
            FROM radius_subscriptions s
            LEFT JOIN customers c ON s.customer_id = c.id
            LEFT JOIN radius_packages p ON s.package_id = p.id
            WHERE s.status = 'active' AND s.expiry_date::date BETWEEN CURRENT_DATE AND (CURRENT_DATE + (? || ' days')::interval)
            ORDER BY s.expiry_date ASC
        ");
        $stmt->execute([$days]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getSubscriptionsNearQuota(int $percentThreshold = 80): array {
        $stmt = $this->db->prepare("
            SELECT s.*, c.name as customer_name, c.phone as customer_phone,
                   p.name as package_name, p.data_quota_mb,
                   ROUND((s.data_used_mb::numeric / NULLIF(p.data_quota_mb, 0)) * 100, 1) as usage_percent
            FROM radius_subscriptions s
            LEFT JOIN customers c ON s.customer_id = c.id
            LEFT JOIN radius_packages p ON s.package_id = p.id
            WHERE s.status = 'active' AND p.data_quota_mb > 0
            AND (s.data_used_mb::numeric / p.data_quota_mb) * 100 >= ?
            ORDER BY usage_percent DESC
        ");
        $stmt->execute([$percentThreshold]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function sendExpiryAlerts(): array {
        $sent = 0;
        $sms = new \App\SMS();
        
        // Get subscriptions expiring in 3 days
        $expiring = $this->getExpiringSubscriptions(3);
        
        foreach ($expiring as $sub) {
            if (empty($sub['customer_phone'])) continue;
            
            $days = (int)$sub['days_remaining'];
            $message = "Dear {$sub['customer_name']}, your internet subscription ({$sub['package_name']}) ";
            $message .= $days == 0 ? "expires TODAY." : "expires in {$days} day(s).";
            $message .= " Please renew to avoid disconnection. Amount: KES " . number_format($sub['package_price']);
            
            try {
                $gateway = new \App\SMSGateway();
                $gateway->send($sub['customer_phone'], $message);
                $sent++;
            } catch (\Exception $e) {
                // Log error but continue
            }
        }
        
        return ['success' => true, 'sent' => $sent];
    }
    
    // ==================== M-Pesa Integration ====================
    
    public function processPayment(string $transactionId, string $phone, float $amount, string $accountRef = '', ?int $subscriptionId = null): array {
        // Idempotency check - prevent duplicate processing of same transaction
        $checkStmt = $this->db->prepare("SELECT id FROM radius_billing_history WHERE transaction_ref = ? LIMIT 1");
        $checkStmt->execute([$transactionId]);
        if ($checkStmt->fetch()) {
            error_log("RADIUS: Duplicate payment ignored - transaction {$transactionId} already processed");
            return ['success' => true, 'duplicate' => true, 'message' => 'Payment already processed'];
        }
        
        $sub = null;
        
        // First: Try to find by subscription ID if provided
        if ($subscriptionId) {
            $stmt = $this->db->prepare("SELECT * FROM radius_subscriptions WHERE id = ?");
            $stmt->execute([$subscriptionId]);
            $sub = $stmt->fetch(\PDO::FETCH_ASSOC);
        }
        
        // Second: Try to extract subscription ID from account reference (format: radius_X)
        if (!$sub && preg_match('/^radius_(\d+)$/i', $accountRef, $matches)) {
            $extractedId = (int)$matches[1];
            $stmt = $this->db->prepare("SELECT * FROM radius_subscriptions WHERE id = ?");
            $stmt->execute([$extractedId]);
            $sub = $stmt->fetch(\PDO::FETCH_ASSOC);
        }
        
        // Third: Fallback to phone or username matching
        if (!$sub) {
            $stmt = $this->db->prepare("
                SELECT s.* FROM radius_subscriptions s
                LEFT JOIN customers c ON s.customer_id = c.id
                WHERE c.phone LIKE ? OR s.username = ?
                LIMIT 1
            ");
            $phoneClean = preg_replace('/^254/', '0', $phone);
            $stmt->execute(['%' . $phoneClean, $accountRef]);
            $sub = $stmt->fetch(\PDO::FETCH_ASSOC);
        }
        
        if (!$sub) {
            return ['success' => false, 'error' => 'Subscription not found for phone: ' . $phone];
        }
        
        $package = $this->getPackage($sub['package_id']);
        if (!$package) {
            return ['success' => false, 'error' => 'Package not found'];
        }
        
        $packagePrice = (float)($package['price'] ?? 0);
        $currentBalance = (float)($sub['credit_balance'] ?? 0);
        
        // Safety check: package must have a valid price > 0
        if ($packagePrice <= 0) {
            error_log("RADIUS ERROR: Package ID {$sub['package_id']} has invalid price: {$packagePrice}");
            return ['success' => false, 'error' => 'Package has no price configured'];
        }
        
        error_log("RADIUS Payment: amount={$amount}, packagePrice={$packagePrice}, currentBalance={$currentBalance}, subscriptionId={$sub['id']}");
        
        // Record the payment transaction first (if table exists)
        try {
            $stmt = $this->db->prepare("
                INSERT INTO radius_billing_history (subscription_id, transaction_type, amount, description, transaction_ref, created_at)
                VALUES (?, 'payment', ?, 'M-Pesa payment', ?, NOW())
            ");
            $stmt->execute([$sub['id'], $amount, $transactionId]);
        } catch (\Exception $e) {
            // Table might not exist - continue without recording
            error_log("RADIUS: billing_history insert failed: " . $e->getMessage());
        }
        
        // If amount exactly equals package price, renew immediately
        if (abs($amount - $packagePrice) < 0.01) {
            $result = $this->renewSubscription($sub['id']);
            
            if ($result['success']) {
                // Record billing record
                $stmt = $this->db->prepare("
                    INSERT INTO radius_billing (subscription_id, package_id, amount, billing_type, 
                        period_start, period_end, status, payment_method, transaction_ref)
                    VALUES (?, ?, ?, 'renewal', CURRENT_DATE, ?, 'paid', 'mpesa', ?)
                ");
                $stmt->execute([
                    $sub['id'], 
                    $sub['package_id'], 
                    $amount, 
                    $result['expiry_date'],
                    $transactionId
                ]);
            }
            
            return $result;
        }
        
        // Partial payment - add to wallet
        $newBalance = $currentBalance + $amount;
        $stmt = $this->db->prepare("UPDATE radius_subscriptions SET credit_balance = ? WHERE id = ?");
        $stmt->execute([$newBalance, $sub['id']]);
        
        // Check if subscription is expired or inactive (needs renewal)
        $isExpired = ($sub['status'] !== 'active') || 
                     (isset($sub['expiry_date']) && !empty($sub['expiry_date']) && strtotime($sub['expiry_date']) < time());
        
        // Only auto-renew from wallet if subscription is EXPIRED and wallet has enough
        if ($isExpired && $newBalance >= $packagePrice) {
            // Deduct package price from wallet and renew
            $remainingBalance = $newBalance - $packagePrice;
            $stmt = $this->db->prepare("UPDATE radius_subscriptions SET credit_balance = ? WHERE id = ?");
            $stmt->execute([$remainingBalance, $sub['id']]);
            
            error_log("RADIUS: Auto-renewing expired subscription {$sub['id']} from wallet. Balance: {$newBalance} -> {$remainingBalance}");
            
            $result = $this->renewSubscription($sub['id']);
            
            if ($result['success']) {
                // Record billing record (paid from wallet)
                $stmt = $this->db->prepare("
                    INSERT INTO radius_billing (subscription_id, package_id, amount, billing_type, 
                        period_start, period_end, status, payment_method, transaction_ref)
                    VALUES (?, ?, ?, 'renewal', CURRENT_DATE, ?, 'paid', 'wallet', ?)
                ");
                $stmt->execute([
                    $sub['id'], 
                    $sub['package_id'], 
                    $packagePrice, 
                    $result['expiry_date'],
                    $transactionId
                ]);
                
                // Disconnect user after wallet-based renewal so they reconnect with new settings
                $disconnectResult = $this->disconnectSubscription($sub['id']);
                
                $result['wallet_used'] = true;
                $result['wallet_remaining'] = $remainingBalance;
                $result['disconnected'] = $disconnectResult['disconnected'] ?? 0;
            }
            
            return $result;
        }
        
        // Account is still active - just credit the wallet, don't renew yet
        if (!$isExpired) {
            error_log("RADIUS: Account {$sub['id']} still active (expires: {$sub['expiry_date']}). Wallet topped up to {$newBalance}");
        }
        
        // Not enough for renewal, just topped up wallet
        return [
            'success' => true,
            'wallet_topup' => true,
            'amount_added' => $amount,
            'new_balance' => $newBalance,
            'needed_for_renewal' => $packagePrice - $newBalance,
            'message' => "KES " . number_format($amount) . " added to wallet. Balance: KES " . number_format($newBalance, 2) . ". Need KES " . number_format($packagePrice - $newBalance, 2) . " more to renew."
        ];
    }
    
    // ==================== CoA (Change of Authorization) ====================
    
    /**
     * Alias for disconnectUser - for compatibility
     */
    public function disconnectSubscription(int $subscriptionId): array {
        return $this->disconnectUser($subscriptionId);
    }
    
    public function disconnectUser(int $subscriptionId): array {
        $sub = $this->getSubscription($subscriptionId);
        if (!$sub) {
            return ['success' => false, 'error' => 'Subscription not found'];
        }
        
        // Get the subscription's NAS for fallback
        $fallbackNas = null;
        if (!empty($sub['nas_id'])) {
            $fallbackNas = $this->getNAS($sub['nas_id']);
        }
        
        // If no NAS assigned to subscription, try to find one
        if (!$fallbackNas) {
            $stmt = $this->db->query("SELECT * FROM radius_nas WHERE is_active = TRUE LIMIT 1");
            $fallbackNas = $stmt->fetch(\PDO::FETCH_ASSOC);
        }
        
        // Get active sessions with subscription username and NAS ID for VPN lookup
        $stmt = $this->db->prepare("
            SELECT rs.id, rs.acct_session_id, rs.framed_ip_address, rs.mac_address, rs.nas_id,
                   sub.username, rn.ip_address as nas_ip, rn.secret as nas_secret
            FROM radius_sessions rs
            LEFT JOIN radius_nas rn ON rs.nas_id = rn.id
            LEFT JOIN radius_subscriptions sub ON rs.subscription_id = sub.id
            WHERE rs.subscription_id = ? AND rs.session_end IS NULL
        ");
        $stmt->execute([$subscriptionId]);
        $sessions = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // If no active sessions, try to disconnect by username using fallback NAS
        if (empty($sessions) && $fallbackNas) {
            $session = [
                'username' => $sub['username'],
                'nas_ip' => $fallbackNas['ip_address'],
                'nas_secret' => $fallbackNas['secret'],
                'acct_session_id' => null
            ];
            $result = $this->sendCoADisconnect($session);
            return [
                'success' => $result['success'],
                'disconnected' => $result['success'] ? 1 : 0,
                'total_sessions' => 0,
                'message' => $result['success'] ? 'Disconnected by username' : $result['error'],
                'errors' => $result['success'] ? [] : [$result['error']]
            ];
        }
        
        $disconnected = 0;
        $errors = [];
        foreach ($sessions as $session) {
            // Use fallback NAS if session doesn't have one
            if (empty($session['nas_ip']) && $fallbackNas) {
                $session['nas_ip'] = $fallbackNas['ip_address'];
                $session['nas_secret'] = $fallbackNas['secret'];
            }
            
            $result = $this->sendCoADisconnect($session);
            if ($result['success']) {
                $disconnected++;
                // Mark session as ended in database so dashboard updates immediately
                if (!empty($session['id'])) {
                    $this->markSessionEnded($session['id'], 'coa_disconnect');
                }
            } else {
                $errors[] = $result['error'];
            }
        }
        
        return [
            'success' => true, 
            'disconnected' => $disconnected,
            'total_sessions' => count($sessions),
            'errors' => $errors
        ];
    }
    
    private function markSessionEnded(int $sessionId, string $terminateCause = 'Admin-Reset'): void {
        $stmt = $this->db->prepare("
            UPDATE radius_sessions SET 
                session_end = CURRENT_TIMESTAMP,
                status = 'closed',
                terminate_cause = ?
            WHERE id = ? AND session_end IS NULL
        ");
        $stmt->execute([$terminateCause, $sessionId]);
    }
    
    public function sendCoADisconnect(array $session): array {
        if (empty($session['nas_ip']) || empty($session['nas_secret'])) {
            return ['success' => false, 'error' => 'Missing NAS IP or secret'];
        }
        
        $sessionId = $session['acct_session_id'] ?? '';
        $username = $session['username'] ?? '';
        $nasIp = $session['nas_ip'];
        $nasSecret = $session['nas_secret'];
        
        if (empty($sessionId) && empty($username)) {
            return ['success' => false, 'error' => 'Missing session ID and username'];
        }
        
        // Send Disconnect-Request via OLT service (routes through WireGuard VPN)
        // In Docker, use container name; locally use localhost
        $oltServiceUrl = getenv('OLT_SERVICE_URL') ?: 'http://localhost:3002';
        $oltServiceUrl .= '/radius/disconnect';
        
        $payload = [
            'nasIp' => $nasIp,
            'nasPort' => 3799,
            'secret' => $nasSecret,
            'username' => $username ?: null,
            'sessionId' => $sessionId ?: null
        ];
        
        try {
            $ch = curl_init($oltServiceUrl);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($curlError) {
                return ['success' => false, 'error' => 'OLT service unavailable: ' . $curlError, 'target_ip' => $nasIp];
            }
            
            $result = json_decode($response, true);
            if ($result && $result['success']) {
                return ['success' => true, 'output' => $result['response'] ?? 'Disconnect-ACK', 'target_ip' => $nasIp, 'via' => 'vpn'];
            }
            
            return ['success' => false, 'error' => $result['error'] ?? 'Disconnect failed', 'target_ip' => $nasIp];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Exception: ' . $e->getMessage(), 'target_ip' => $nasIp];
        }
    }
    
    private function sendCoADisconnectDirect(array $session): array {
        $sessionId = $session['acct_session_id'] ?? '';
        $username = $session['username'] ?? '';
        $nasIp = $session['nas_ip'];
        $nasSecret = $session['nas_secret'];
        
        $attrs = [];
        if (!empty($sessionId)) {
            $attrs['Acct-Session-Id'] = $sessionId;
        }
        if (!empty($username)) {
            $attrs['User-Name'] = $username;
        }
        
        try {
            $client = new RadiusClient($nasIp, $nasSecret, 3799, 5);
            $result = $client->disconnect($attrs);
            
            if ($result['success']) {
                return ['success' => true, 'output' => $result['response'] ?? 'Disconnect-ACK', 'target_ip' => $nasIp, 'via' => 'direct'];
            }
            
            $errorResponse = ['success' => false, 'error' => $result['error'] ?? 'Disconnect failed', 'target_ip' => $nasIp];
            if (!empty($result['diagnostic'])) {
                $errorResponse['diagnostic'] = $result['diagnostic'];
            }
            return $errorResponse;
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Exception: ' . $e->getMessage(), 'target_ip' => $nasIp];
        }
    }
    
    /**
     * Send CoA disconnect for a subscription (forces reconnect with new attributes)
     * Used when expiry date changes or package changes to apply new limits
     */
    public function sendCoAForSubscription(int $subscriptionId): array {
        $sub = $this->getSubscription($subscriptionId);
        if (!$sub) {
            return ['success' => false, 'error' => 'Subscription not found'];
        }
        
        // Find active session for this subscription
        $stmt = $this->db->prepare("
            SELECT rs.*, rn.ip_address as nas_ip, rn.secret as nas_secret
            FROM radius_sessions rs
            LEFT JOIN radius_nas rn ON rs.nas_id = rn.id OR rs.nas_ip_address = rn.ip_address
            WHERE rs.subscription_id = ? AND rs.session_end IS NULL
            ORDER BY rs.session_start DESC LIMIT 1
        ");
        $stmt->execute([$subscriptionId]);
        $session = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($session && !empty($session['nas_ip'])) {
            // Disconnect the active session
            return $this->sendCoADisconnect($session);
        }
        
        // No active session, try sending a general CoA with just the username
        // This helps trigger any session that might exist but isn't tracked
        return $this->sendCoA($subscriptionId, []);
    }
    
    public function sendCoA(int $subscriptionId, array $attributes): array {
        $sub = $this->getSubscription($subscriptionId);
        if (!$sub) {
            return ['success' => false, 'error' => 'Subscription not found'];
        }
        
        // Get NAS info - try subscription's nas_id first
        $nas = null;
        if (!empty($sub['nas_id'])) {
            $stmt = $this->db->prepare("SELECT ip_address, secret FROM radius_nas WHERE id = ?");
            $stmt->execute([$sub['nas_id']]);
            $nas = $stmt->fetch(\PDO::FETCH_ASSOC);
        }
        
        // If not found, try to get NAS from active session
        if (!$nas) {
            $stmt = $this->db->prepare("
                SELECT rn.ip_address, rn.secret 
                FROM radius_sessions rs
                JOIN radius_nas rn ON rs.nas_id = rn.id OR rs.nas_ip_address = rn.ip_address
                WHERE rs.subscription_id = ? AND rs.session_end IS NULL
                ORDER BY rs.session_start DESC LIMIT 1
            ");
            $stmt->execute([$subscriptionId]);
            $nas = $stmt->fetch(\PDO::FETCH_ASSOC);
        }
        
        // If still not found, try default active NAS
        if (!$nas) {
            $stmt = $this->db->query("SELECT ip_address, secret FROM radius_nas WHERE is_active = true ORDER BY id LIMIT 1");
            $nas = $stmt->fetch(\PDO::FETCH_ASSOC);
        }
        
        if (!$nas) {
            return ['success' => false, 'error' => 'NAS not found - please assign a NAS to this subscription or add an active NAS device'];
        }
        
        // Send CoA via OLT service (routes through WireGuard VPN)
        $oltServiceUrl = (getenv('OLT_SERVICE_URL') ?: 'http://localhost:3002') . '/radius/coa';
        
        $payload = [
            'nasIp' => $nas['ip_address'],
            'nasPort' => 3799,
            'secret' => $nas['secret'],
            'username' => $sub['username']
        ];
        
        // Add any additional attributes
        foreach ($attributes as $key => $value) {
            $payload[$key] = $value;
        }
        
        try {
            $ch = curl_init($oltServiceUrl);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($curlError) {
                return ['success' => false, 'error' => 'OLT service unavailable: ' . $curlError, 'target_ip' => $nas['ip_address']];
            }
            
            $result = json_decode($response, true);
            if ($result && $result['success']) {
                return ['success' => true, 'output' => $result['response'] ?? 'CoA-ACK', 'target_ip' => $nas['ip_address'], 'via' => 'vpn'];
            }
            
            return ['success' => false, 'error' => $result['error'] ?? 'CoA failed', 'target_ip' => $nas['ip_address']];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Exception: ' . $e->getMessage(), 'target_ip' => $nas['ip_address']];
        }
    }
    
    public function checkNasReachability(string $nasIp): array {
        $output = [];
        $returnVar = 0;
        \exec("ping -c 1 -W 2 " . \escapeshellarg($nasIp) . " 2>&1", $output, $returnVar);
        
        $reachable = ($returnVar === 0);
        $latency = null;
        
        if ($reachable) {
            foreach ($output as $line) {
                if (preg_match('/time[=<](\d+(?:\.\d+)?)\s*ms/i', $line, $matches)) {
                    $latency = round((float)$matches[1], 1);
                    break;
                }
            }
        }
        
        return [
            'reachable' => $reachable,
            'latency' => $latency,
            'output' => implode("\n", $output)
        ];
    }
    
    public function testNasConnectivity(int $nasId): array {
        $stmt = $this->db->prepare("SELECT * FROM radius_nas WHERE id = ?");
        $stmt->execute([$nasId]);
        $nas = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$nas) {
            return ['success' => false, 'error' => 'NAS not found'];
        }
        
        $results = [
            'nas_id' => $nasId,
            'nas_name' => $nas['name'],
            'nas_ip' => $nas['ip_address'],
            'tests' => []
        ];
        
        // Test 1: Ping
        $pingResult = $this->checkNasReachability($nas['ip_address']);
        $results['tests']['ping'] = [
            'status' => $pingResult['reachable'] ? 'pass' : 'fail',
            'message' => $pingResult['reachable'] ? "Reachable ({$pingResult['latency']}ms)" : 'Not reachable - check VPN tunnel',
            'details' => $pingResult['output']
        ];
        
        // Test 2: UDP port 3799 (CoA)
        $socket = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        $coaPortOpen = false;
        if ($socket) {
            socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 2, 'usec' => 0]);
            socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => 2, 'usec' => 0]);
            $testPacket = pack('CCn', 1, rand(0, 255), 20) . random_bytes(16);
            $sent = @socket_sendto($socket, $testPacket, strlen($testPacket), 0, $nas['ip_address'], 3799);
            $coaPortOpen = ($sent !== false);
            socket_close($socket);
        }
        $results['tests']['coa_port'] = [
            'status' => $coaPortOpen ? 'pass' : 'fail',
            'message' => $coaPortOpen ? 'UDP 3799 accessible' : 'Cannot send to UDP 3799 - check firewall'
        ];
        
        $results['success'] = $pingResult['reachable'];
        $results['summary'] = $pingResult['reachable'] 
            ? 'NAS is reachable. If CoA still fails, verify: 1) CoA secret matches, 2) MikroTik has RADIUS CoA enabled on port 3799'
            : 'NAS is NOT reachable. Check: 1) VPN tunnel is up, 2) NAS IP is correct, 3) Firewall allows traffic';
        
        return $results;
    }
    
    // ==================== Reports ====================
    
    public function getRevenueReport(string $period = 'monthly', ?string $startDate = null, ?string $endDate = null): array {
        $startDate = $startDate ?? date('Y-m-01');
        $endDate = $endDate ?? date('Y-m-t');
        
        $groupBy = match($period) {
            'daily' => "DATE(created_at)",
            'weekly' => "DATE_TRUNC('week', created_at)",
            'monthly' => "DATE_TRUNC('month', created_at)",
            default => "DATE_TRUNC('month', created_at)"
        };
        
        $stmt = $this->db->prepare("
            SELECT 
                {$groupBy} as period,
                COUNT(*) as transactions,
                SUM(amount) as total_revenue,
                SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as paid_revenue,
                SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_revenue
            FROM radius_billing
            WHERE created_at BETWEEN ? AND ?
            GROUP BY {$groupBy}
            ORDER BY period DESC
        ");
        $stmt->execute([$startDate, $endDate . ' 23:59:59']);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getPackagePopularity(): array {
        $stmt = $this->db->query("
            SELECT p.name, p.price, COUNT(s.id) as subscriber_count,
                   SUM(CASE WHEN s.status = 'active' THEN 1 ELSE 0 END) as active_count,
                   p.price * COUNT(CASE WHEN s.status = 'active' THEN 1 END) as potential_revenue
            FROM radius_packages p
            LEFT JOIN radius_subscriptions s ON s.package_id = p.id
            GROUP BY p.id, p.name, p.price
            ORDER BY subscriber_count DESC
        ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getSubscriptionStats(): array {
        $stmt = $this->db->query("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended,
                SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired,
                SUM(CASE WHEN expiry_date = CURRENT_DATE THEN 1 ELSE 0 END) as expiring_today,
                SUM(CASE WHEN expiry_date BETWEEN CURRENT_DATE AND CURRENT_DATE + 7 THEN 1 ELSE 0 END) as expiring_week
            FROM radius_subscriptions
        ");
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
    
    // ==================== Usage Analytics ====================
    
    public function getTopUsers(int $limit = 20, string $period = 'today'): array {
        $dateFilter = match($period) {
            'today' => "DATE(started_at) = CURRENT_DATE",
            'week' => "started_at >= CURRENT_DATE - INTERVAL '7 days'",
            'month' => "started_at >= CURRENT_DATE - INTERVAL '30 days'",
            default => "DATE(started_at) = CURRENT_DATE"
        };
        
        $stmt = $this->db->prepare("
            SELECT s.username, c.name as customer_name,
                   SUM(rs.input_octets + rs.output_octets) / 1073741824.0 as total_gb,
                   SUM(rs.input_octets) / 1073741824.0 as download_gb,
                   SUM(rs.output_octets) / 1073741824.0 as upload_gb,
                   COUNT(rs.id) as session_count
            FROM radius_sessions rs
            LEFT JOIN radius_subscriptions s ON rs.subscription_id = s.id
            LEFT JOIN customers c ON s.customer_id = c.id
            WHERE {$dateFilter}
            GROUP BY s.username, c.name
            ORDER BY total_gb DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getPeakHours(): array {
        $stmt = $this->db->query("
            SELECT EXTRACT(HOUR FROM started_at) as hour,
                   COUNT(*) as session_count,
                   SUM(input_octets + output_octets) / 1073741824.0 as total_gb
            FROM radius_sessions
            WHERE started_at >= CURRENT_DATE - INTERVAL '30 days'
            GROUP BY EXTRACT(HOUR FROM started_at)
            ORDER BY hour
        ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getBandwidthTrends(int $days = 30): array {
        $stmt = $this->db->prepare("
            SELECT DATE(started_at) as date,
                   SUM(input_octets) / 1073741824.0 as download_gb,
                   SUM(output_octets) / 1073741824.0 as upload_gb,
                   COUNT(DISTINCT subscription_id) as unique_users
            FROM radius_sessions
            WHERE started_at >= CURRENT_DATE - INTERVAL '1 day' * ?
            GROUP BY DATE(started_at)
            ORDER BY date
        ");
        $stmt->execute([$days]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    // ==================== MAC Binding ====================
    
    public function bindMAC(int $subscriptionId, string $mac): array {
        $mac = strtoupper(preg_replace('/[^A-Fa-f0-9]/', '', $mac));
        if (strlen($mac) !== 12) {
            return ['success' => false, 'error' => 'Invalid MAC address format'];
        }
        
        // Format as XX:XX:XX:XX:XX:XX
        $mac = implode(':', str_split($mac, 2));
        
        $stmt = $this->db->prepare("UPDATE radius_subscriptions SET mac_address = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$mac, $subscriptionId]);
        
        return ['success' => true, 'mac' => $mac];
    }
    
    public function unbindMAC(int $subscriptionId): array {
        $stmt = $this->db->prepare("UPDATE radius_subscriptions SET mac_address = NULL, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$subscriptionId]);
        return ['success' => true];
    }
    
    public function verifyMAC(int $subscriptionId, string $mac): bool {
        $sub = $this->getSubscription($subscriptionId);
        if (!$sub || empty($sub['mac_address'])) {
            return true; // No MAC binding, allow
        }
        
        $mac = strtoupper(preg_replace('/[^A-Fa-f0-9:]/', '', $mac));
        return $sub['mac_address'] === $mac;
    }
    
    // ==================== Multi-Device MAC Management ====================
    
    public function normalizeMAC(string $mac): string {
        $mac = strtoupper(preg_replace('/[^A-Fa-f0-9]/', '', $mac));
        if (strlen($mac) !== 12) {
            return '';
        }
        return implode(':', str_split($mac, 2));
    }
    
    public function getSubscriptionMACs(int $subscriptionId): array {
        $stmt = $this->db->prepare("
            SELECT * FROM radius_subscription_macs 
            WHERE subscription_id = ? 
            ORDER BY is_primary DESC, created_at ASC
        ");
        $stmt->execute([$subscriptionId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getSubscriptionMACCount(int $subscriptionId): int {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM radius_subscription_macs WHERE subscription_id = ?");
        $stmt->execute([$subscriptionId]);
        return (int) $stmt->fetchColumn();
    }
    
    public function getMaxDevicesForSubscription(int $subscriptionId): int {
        $stmt = $this->db->prepare("
            SELECT COALESCE(p.max_devices, 1) as max_devices
            FROM radius_subscriptions s
            LEFT JOIN radius_packages p ON s.package_id = p.id
            WHERE s.id = ?
        ");
        $stmt->execute([$subscriptionId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? (int)$row['max_devices'] : 1;
    }
    
    public function addSubscriptionMAC(int $subscriptionId, string $mac, ?string $deviceName = null, bool $autoCaptured = false): array {
        $mac = $this->normalizeMAC($mac);
        if (empty($mac)) {
            return ['success' => false, 'error' => 'Invalid MAC address format'];
        }
        
        $maxDevices = $this->getMaxDevicesForSubscription($subscriptionId);
        $currentCount = $this->getSubscriptionMACCount($subscriptionId);
        
        if ($currentCount >= $maxDevices) {
            return ['success' => false, 'error' => "Device limit reached ({$maxDevices} devices allowed)"];
        }
        
        $stmt = $this->db->prepare("SELECT id FROM radius_subscription_macs WHERE mac_address = ?");
        $stmt->execute([$mac]);
        if ($stmt->fetch()) {
            return ['success' => false, 'error' => 'This MAC address is already registered'];
        }
        
        try {
            $this->db->beginTransaction();
            
            $isPrimary = ($currentCount === 0);
            
            if ($isPrimary) {
                $this->db->prepare("UPDATE radius_subscription_macs SET is_primary = FALSE WHERE subscription_id = ?")
                    ->execute([$subscriptionId]);
            }
            
            $stmt = $this->db->prepare("
                INSERT INTO radius_subscription_macs (subscription_id, mac_address, device_name, is_primary, auto_captured)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$subscriptionId, $mac, $deviceName, $isPrimary, $autoCaptured]);
            
            if ($isPrimary) {
                $this->db->prepare("UPDATE radius_subscriptions SET mac_address = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
                    ->execute([$mac, $subscriptionId]);
            }
            
            $this->db->commit();
            return ['success' => true, 'mac' => $mac, 'is_primary' => $isPrimary];
        } catch (\Exception $e) {
            $this->db->rollBack();
            if (strpos($e->getMessage(), 'duplicate key') !== false || strpos($e->getMessage(), 'unique constraint') !== false) {
                return ['success' => false, 'error' => 'This MAC address is already registered to another account'];
            }
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function removeSubscriptionMAC(int $subscriptionId, int $macId): array {
        $stmt = $this->db->prepare("SELECT * FROM radius_subscription_macs WHERE id = ? AND subscription_id = ?");
        $stmt->execute([$macId, $subscriptionId]);
        $macRecord = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$macRecord) {
            return ['success' => false, 'error' => 'MAC address not found'];
        }
        
        $stmt = $this->db->prepare("DELETE FROM radius_subscription_macs WHERE id = ?");
        $stmt->execute([$macId]);
        
        if ($macRecord['is_primary']) {
            $stmt = $this->db->prepare("
                SELECT mac_address FROM radius_subscription_macs 
                WHERE subscription_id = ? 
                ORDER BY created_at ASC 
                LIMIT 1
            ");
            $stmt->execute([$subscriptionId]);
            $nextMac = $stmt->fetchColumn();
            
            if ($nextMac) {
                $this->db->prepare("UPDATE radius_subscription_macs SET is_primary = TRUE WHERE subscription_id = ? AND mac_address = ?")
                    ->execute([$subscriptionId, $nextMac]);
                $this->db->prepare("UPDATE radius_subscriptions SET mac_address = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
                    ->execute([$nextMac, $subscriptionId]);
            } else {
                $this->db->prepare("UPDATE radius_subscriptions SET mac_address = NULL, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
                    ->execute([$subscriptionId]);
            }
        }
        
        return ['success' => true];
    }
    
    public function findSubscriptionByMAC(string $mac): ?array {
        $mac = $this->normalizeMAC($mac);
        if (empty($mac)) {
            return null;
        }
        
        $stmt = $this->db->prepare("
            SELECT s.*, c.name as customer_name, c.phone as customer_phone,
                   p.name as package_name, p.download_speed, p.upload_speed, p.max_devices
            FROM radius_subscription_macs m
            JOIN radius_subscriptions s ON m.subscription_id = s.id
            LEFT JOIN customers c ON s.customer_id = c.id
            LEFT JOIN radius_packages p ON s.package_id = p.id
            WHERE m.mac_address = ? AND s.status = 'active'
            LIMIT 1
        ");
        $stmt->execute([$mac]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    
    public function isMACAUthenticated(int $subscriptionId, string $mac): bool {
        $mac = $this->normalizeMAC($mac);
        if (empty($mac)) {
            return false;
        }
        
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM radius_subscription_macs 
            WHERE subscription_id = ? AND mac_address = ?
        ");
        $stmt->execute([$subscriptionId, $mac]);
        return (int)$stmt->fetchColumn() > 0;
    }
    
    public function autoCaptureMACIfAllowed(int $subscriptionId, string $mac): array {
        $mac = $this->normalizeMAC($mac);
        if (empty($mac)) {
            return ['success' => false, 'error' => 'Invalid MAC'];
        }
        
        $sub = $this->getSubscription($subscriptionId);
        if (!$sub) {
            return ['success' => false, 'error' => 'Subscription not found'];
        }
        
        $existingMACs = $this->getSubscriptionMACs($subscriptionId);
        foreach ($existingMACs as $existing) {
            if ($existing['mac_address'] === $mac) {
                return ['success' => true, 'already_exists' => true];
            }
        }
        
        $maxDevices = $this->getMaxDevicesForSubscription($subscriptionId);
        if (count($existingMACs) >= $maxDevices) {
            return ['success' => false, 'error' => 'Device limit reached'];
        }
        
        return $this->addSubscriptionMAC($subscriptionId, $mac, 'Auto-detected device', true);
    }
    
    // ==================== IP Pool Management ====================
    
    public function getIPPools(): array {
        $stmt = $this->db->query("SELECT * FROM radius_ip_pools ORDER BY name");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function createIPPool(array $data): array {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO radius_ip_pools (name, start_ip, end_ip, gateway, netmask, dns1, dns2, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?::boolean)
            ");
            $stmt->execute([
                $data['name'],
                $data['start_ip'],
                $data['end_ip'],
                $data['gateway'] ?? null,
                $data['netmask'] ?? '255.255.255.0',
                $data['dns1'] ?? '8.8.8.8',
                $data['dns2'] ?? '8.8.4.4',
                $this->castBoolean($data['is_active'] ?? true, true)
            ]);
            return ['success' => true, 'id' => $this->db->lastInsertId()];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function getIPPoolAllocation(int $poolId): array {
        $stmt = $this->db->prepare("
            SELECT s.static_ip, s.username, c.name as customer_name, s.status
            FROM radius_subscriptions s
            LEFT JOIN customers c ON s.customer_id = c.id
            LEFT JOIN radius_ip_pools p ON s.static_ip >= p.start_ip AND s.static_ip <= p.end_ip
            WHERE p.id = ? AND s.static_ip IS NOT NULL
            ORDER BY INET(s.static_ip)
        ");
        $stmt->execute([$poolId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    // ==================== NAS Monitoring ====================
    
    public function getNASStatus(): array {
        $devices = $this->getNASDevices();
        $result = [];
        
        foreach ($devices as $nas) {
            $status = [
                'id' => $nas['id'],
                'name' => $nas['name'],
                'ip_address' => $nas['ip_address'],
                'online' => false,
                'latency_ms' => null,
                'active_sessions' => 0,
                'last_check' => date('Y-m-d H:i:s')
            ];
            
            // Ping check
            \exec("ping -c 1 -W 2 " . \escapeshellarg($nas['ip_address']) . " 2>&1", $output, $returnCode);
            if ($returnCode === 0) {
                $status['online'] = true;
                if (preg_match('/time=(\d+\.?\d*)/', implode("\n", $output), $matches)) {
                    $status['latency_ms'] = (float)$matches[1];
                }
            }
            
            // Active sessions count
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM radius_sessions 
                WHERE nas_id = ? AND session_end IS NULL
            ");
            $stmt->execute([$nas['id']]);
            $status['active_sessions'] = (int)$stmt->fetchColumn();
            
            $result[] = $status;
        }
        
        return $result;
    }
    
    // ==================== Bulk Import ====================
    
    public function importSubscriptionsCSV(string $csvContent): array {
        $lines = explode("\n", trim($csvContent));
        $header = str_getcsv(array_shift($lines));
        
        $imported = 0;
        $errors = [];
        
        foreach ($lines as $lineNum => $line) {
            if (empty(trim($line))) continue;
            
            $row = array_combine($header, str_getcsv($line));
            
            try {
                // Required fields: customer_id, package_id, username, password
                if (empty($row['username']) || empty($row['password'])) {
                    $errors[] = "Line " . ($lineNum + 2) . ": Missing username or password";
                    continue;
                }
                
                $result = $this->createSubscription([
                    'customer_id' => $row['customer_id'] ?? null,
                    'package_id' => $row['package_id'] ?? 1,
                    'username' => $row['username'],
                    'password' => $row['password'],
                    'access_type' => $row['access_type'] ?? 'pppoe',
                    'static_ip' => $row['static_ip'] ?? null,
                    'mac_address' => $row['mac_address'] ?? null,
                    'notes' => $row['notes'] ?? 'Imported via CSV'
                ]);
                
                if ($result['success']) {
                    $imported++;
                } else {
                    $errors[] = "Line " . ($lineNum + 2) . ": " . $result['error'];
                }
            } catch (\Exception $e) {
                $errors[] = "Line " . ($lineNum + 2) . ": " . $e->getMessage();
            }
        }
        
        return ['success' => true, 'imported' => $imported, 'errors' => $errors];
    }
    
    // ==================== Package Upgrade/Downgrade ====================
    
    public function changePackage(int $subscriptionId, int $newPackageId, bool $prorated = true): array {
        try {
            $sub = $this->getSubscription($subscriptionId);
            if (!$sub) {
                return ['success' => false, 'error' => 'Subscription not found'];
            }
            
            $oldPackage = $this->getPackage($sub['package_id']);
            $newPackage = $this->getPackage($newPackageId);
            
            if (!$newPackage) {
                return ['success' => false, 'error' => 'New package not found'];
            }
            
            $proratedAmount = 0;
            $creditAmount = 0;
            
            if ($prorated && $sub['expiry_date']) {
                $daysRemaining = max(0, (strtotime($sub['expiry_date']) - time()) / 86400);
                $totalDays = $oldPackage['validity_days'] ?? 30;
                
                // Calculate credit for unused days
                $dailyRate = $oldPackage['price'] / $totalDays;
                $creditAmount = round($dailyRate * $daysRemaining, 2);
                
                // Calculate prorated amount for new package
                $newDailyRate = $newPackage['price'] / ($newPackage['validity_days'] ?? 30);
                $proratedAmount = round($newDailyRate * $daysRemaining, 2);
            }
            
            // Update subscription
            $stmt = $this->db->prepare("
                UPDATE radius_subscriptions SET package_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?
            ");
            $stmt->execute([$newPackageId, $subscriptionId]);
            
            // Send CoA to update speed without disconnecting user
            $coaResult = $this->sendSpeedUpdateCoA($subscriptionId);
            
            return [
                'success' => true,
                'old_package' => $oldPackage['name'],
                'new_package' => $newPackage['name'],
                'credit_amount' => $creditAmount,
                'prorated_amount' => $proratedAmount,
                'difference' => $proratedAmount - $creditAmount,
                'coa_result' => $coaResult
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    // ==================== Referral System ====================
    
    public function generateReferralCode(int $subscriptionId): string {
        $code = strtoupper(substr(md5($subscriptionId . time()), 0, 8));
        $this->db->prepare("UPDATE radius_subscriptions SET referral_code = ? WHERE id = ?")
            ->execute([$code, $subscriptionId]);
        return $code;
    }
    
    public function applyReferral(int $newSubscriptionId, string $referralCode): array {
        $stmt = $this->db->prepare("SELECT id FROM radius_subscriptions WHERE referral_code = ?");
        $stmt->execute([$referralCode]);
        $referrer = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$referrer) {
            return ['success' => false, 'error' => 'Invalid referral code'];
        }
        
        $stmt = $this->db->prepare("
            INSERT INTO radius_referrals (referrer_subscription_id, referred_subscription_id, referral_code, reward_type, reward_value, status)
            VALUES (?, ?, ?, 'days', 7, 'pending')
        ");
        $stmt->execute([$referrer['id'], $newSubscriptionId, $referralCode]);
        
        return ['success' => true, 'referrer_id' => $referrer['id']];
    }
    
    public function processReferralReward(int $referralId): array {
        $stmt = $this->db->prepare("SELECT * FROM radius_referrals WHERE id = ? AND status = 'pending'");
        $stmt->execute([$referralId]);
        $referral = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$referral) {
            return ['success' => false, 'error' => 'Referral not found or already processed'];
        }
        
        if ($referral['reward_type'] === 'days') {
            $this->db->prepare("
                UPDATE radius_subscriptions SET 
                    expiry_date = expiry_date + INTERVAL '1 day' * ?
                WHERE id = ?
            ")->execute([$referral['reward_value'], $referral['referrer_subscription_id']]);
        } elseif ($referral['reward_type'] === 'credit') {
            $this->db->prepare("
                UPDATE radius_subscriptions SET 
                    credit_balance = credit_balance + ?
                WHERE id = ?
            ")->execute([$referral['reward_value'], $referral['referrer_subscription_id']]);
        }
        
        $this->db->prepare("UPDATE radius_referrals SET status = 'rewarded', rewarded_at = NOW() WHERE id = ?")
            ->execute([$referralId]);
        
        return ['success' => true];
    }
    
    public function getReferralStats(int $subscriptionId): array {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as total_referrals,
                   SUM(CASE WHEN status = 'rewarded' THEN reward_value ELSE 0 END) as total_rewards
            FROM radius_referrals WHERE referrer_subscription_id = ?
        ");
        $stmt->execute([$subscriptionId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
    
    // ==================== Promotional Packages ====================
    
    public function getActivePromotions(): array {
        $stmt = $this->db->query("
            SELECT p.*, pkg.name as package_name FROM radius_promotions p
            LEFT JOIN radius_packages pkg ON p.package_id = pkg.id
            WHERE p.is_active = TRUE 
            AND (p.start_date IS NULL OR p.start_date <= CURRENT_DATE)
            AND (p.end_date IS NULL OR p.end_date >= CURRENT_DATE)
            AND (p.max_uses IS NULL OR p.current_uses < p.max_uses)
        ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function applyPromoCode(string $code, int $packageId): array {
        $stmt = $this->db->prepare("
            SELECT * FROM radius_promotions 
            WHERE promo_code = ? AND is_active = TRUE
            AND (package_id IS NULL OR package_id = ?)
            AND (start_date IS NULL OR start_date <= CURRENT_DATE)
            AND (end_date IS NULL OR end_date >= CURRENT_DATE)
            AND (max_uses IS NULL OR current_uses < max_uses)
        ");
        $stmt->execute([$code, $packageId]);
        $promo = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$promo) {
            return ['success' => false, 'error' => 'Invalid or expired promo code'];
        }
        
        $package = $this->getPackage($packageId);
        $discount = 0;
        
        if ($promo['discount_type'] === 'percent') {
            $discount = $package['price'] * ($promo['discount_value'] / 100);
        } else {
            $discount = $promo['discount_value'];
        }
        
        $this->db->prepare("UPDATE radius_promotions SET current_uses = current_uses + 1 WHERE id = ?")
            ->execute([$promo['id']]);
        
        return [
            'success' => true,
            'promo' => $promo,
            'original_price' => $package['price'],
            'discount' => $discount,
            'final_price' => max(0, $package['price'] - $discount)
        ];
    }
    
    public function createPromotion(array $data): array {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO radius_promotions (name, description, package_id, discount_type, discount_value, promo_code, start_date, end_date, max_uses, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?::boolean)
            ");
            $stmt->execute([
                $data['name'],
                $data['description'] ?? '',
                $data['package_id'] ?? null,
                $data['discount_type'] ?? 'percent',
                $data['discount_value'],
                $data['promo_code'] ?? strtoupper(substr(md5(time()), 0, 8)),
                $data['start_date'] ?? null,
                $data['end_date'] ?? null,
                $data['max_uses'] ?? null,
                $this->castBoolean($data['is_active'] ?? true, true)
            ]);
            return ['success' => true, 'id' => $this->db->lastInsertId()];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    // ==================== Invoice Generation ====================
    
    public function generateInvoice(int $subscriptionId, float $amount, string $description = ''): array {
        try {
            $invoiceNumber = 'INV-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            $taxRate = 0.16;
            $taxAmount = round($amount * $taxRate, 2);
            $totalAmount = $amount + $taxAmount;
            
            $stmt = $this->db->prepare("
                INSERT INTO radius_invoices (subscription_id, invoice_number, amount, tax_amount, total_amount, description, due_date, status)
                VALUES (?, ?, ?, ?, ?, ?, CURRENT_DATE + INTERVAL '7 days', 'unpaid')
            ");
            $stmt->execute([$subscriptionId, $invoiceNumber, $amount, $taxAmount, $totalAmount, $description]);
            
            return [
                'success' => true,
                'invoice_number' => $invoiceNumber,
                'amount' => $amount,
                'tax' => $taxAmount,
                'total' => $totalAmount
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function getInvoice(string $invoiceNumber): ?array {
        $stmt = $this->db->prepare("
            SELECT i.*, s.username, c.name as customer_name, c.phone, c.email,
                   p.name as package_name
            FROM radius_invoices i
            LEFT JOIN radius_subscriptions s ON i.subscription_id = s.id
            LEFT JOIN customers c ON s.customer_id = c.id
            LEFT JOIN radius_packages p ON s.package_id = p.id
            WHERE i.invoice_number = ?
        ");
        $stmt->execute([$invoiceNumber]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    
    public function markInvoicePaid(string $invoiceNumber, string $paymentMethod = 'mpesa', string $transactionRef = ''): array {
        $stmt = $this->db->prepare("
            UPDATE radius_invoices SET 
                status = 'paid', 
                paid_at = NOW(), 
                payment_method = ?, 
                transaction_ref = ?
            WHERE invoice_number = ? AND status = 'unpaid'
        ");
        $stmt->execute([$paymentMethod, $transactionRef, $invoiceNumber]);
        
        return ['success' => $stmt->rowCount() > 0];
    }
    
    public function getUnpaidInvoices(int $subscriptionId): array {
        $stmt = $this->db->prepare("
            SELECT * FROM radius_invoices 
            WHERE subscription_id = ? AND status = 'unpaid'
            ORDER BY due_date ASC
        ");
        $stmt->execute([$subscriptionId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    // ==================== Credit/Debt Management ====================
    
    public function addCredit(int $subscriptionId, float $amount, string $reason = '', bool $autoRenew = true): array {
        $sub = $this->getSubscription($subscriptionId);
        if (!$sub) {
            return ['success' => false, 'error' => 'Subscription not found'];
        }
        
        $package = $this->getPackage($sub['package_id']);
        $packagePrice = (float)($package['price'] ?? 0);
        $currentBalance = (float)($sub['credit_balance'] ?? 0);
        $newBalance = $currentBalance + $amount;
        
        // Add credit to wallet
        $stmt = $this->db->prepare("
            UPDATE radius_subscriptions SET credit_balance = credit_balance + ? WHERE id = ?
        ");
        $stmt->execute([$amount, $subscriptionId]);
        
        // Check if subscription is expired or inactive
        $isExpired = ($sub['status'] !== 'active') || 
                     (isset($sub['expiry_date']) && !empty($sub['expiry_date']) && strtotime($sub['expiry_date']) < time());
        
        // Auto-renew if expired and wallet has enough
        if ($autoRenew && $isExpired && $packagePrice > 0 && $newBalance >= $packagePrice) {
            // Deduct package price from wallet
            $remainingBalance = $newBalance - $packagePrice;
            $stmt = $this->db->prepare("UPDATE radius_subscriptions SET credit_balance = ? WHERE id = ?");
            $stmt->execute([$remainingBalance, $subscriptionId]);
            
            $result = $this->renewSubscription($subscriptionId);
            
            if ($result['success']) {
                // Record billing record
                $stmt = $this->db->prepare("
                    INSERT INTO radius_billing (subscription_id, package_id, amount, billing_type, 
                        period_start, period_end, status, payment_method, transaction_ref)
                    VALUES (?, ?, ?, 'renewal', CURRENT_DATE, ?, 'paid', 'wallet', ?)
                ");
                $stmt->execute([
                    $subscriptionId, 
                    $sub['package_id'], 
                    $packagePrice, 
                    $result['expiry_date'],
                    'manual_credit_' . time()
                ]);
                
                // Disconnect user so they reconnect with new settings
                $disconnectResult = $this->disconnectSubscription($subscriptionId);
                
                return [
                    'success' => true, 
                    'added' => $amount,
                    'renewed' => true,
                    'expiry_date' => $result['expiry_date'],
                    'wallet_remaining' => $remainingBalance,
                    'disconnected' => $disconnectResult['disconnected'] ?? 0
                ];
            }
        }
        
        return ['success' => true, 'added' => $amount, 'new_balance' => $newBalance];
    }
    
    public function useCredit(int $subscriptionId, float $amount): array {
        $sub = $this->getSubscription($subscriptionId);
        if (!$sub || $sub['credit_balance'] < $amount) {
            return ['success' => false, 'error' => 'Insufficient credit'];
        }
        
        $stmt = $this->db->prepare("
            UPDATE radius_subscriptions SET credit_balance = credit_balance - ? WHERE id = ?
        ");
        $stmt->execute([$amount, $subscriptionId]);
        
        return ['success' => true, 'used' => $amount, 'remaining' => $sub['credit_balance'] - $amount];
    }
    
    public function getDebtors(): array {
        $stmt = $this->db->query("
            SELECT s.*, c.name as customer_name, c.phone,
                   COALESCE(SUM(i.total_amount), 0) as total_unpaid
            FROM radius_subscriptions s
            LEFT JOIN customers c ON s.customer_id = c.id
            LEFT JOIN radius_invoices i ON i.subscription_id = s.id AND i.status = 'unpaid'
            GROUP BY s.id, c.name, c.phone
            HAVING COALESCE(SUM(i.total_amount), 0) > 0
            ORDER BY total_unpaid DESC
        ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    // ==================== Technician Assignment ====================
    
    public function linkToTicket(int $subscriptionId, int $ticketId): array {
        try {
            $this->db->prepare("
                UPDATE radius_subscriptions SET notes = CONCAT(COALESCE(notes, ''), '\nLinked to Ticket #', ?) WHERE id = ?
            ")->execute([$ticketId, $subscriptionId]);
            
            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function getSubscriptionByCustomer(int $customerId): ?array {
        $stmt = $this->db->prepare("SELECT * FROM radius_subscriptions WHERE customer_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$customerId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    
    // ==================== Bandwidth Graphs Data ====================
    
    public function getDailyUsage(int $subscriptionId, int $days = 30): array {
        $stmt = $this->db->prepare("
            SELECT log_date, download_mb, upload_mb, session_count, session_time_seconds
            FROM radius_usage_logs
            WHERE subscription_id = ? AND log_date >= CURRENT_DATE - INTERVAL '1 day' * ?
            ORDER BY log_date ASC
        ");
        $stmt->execute([$subscriptionId, $days]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getHourlyUsage(int $subscriptionId): array {
        $stmt = $this->db->prepare("
            SELECT EXTRACT(HOUR FROM started_at) as hour,
                   SUM(input_octets) / 1048576.0 as download_mb,
                   SUM(output_octets) / 1048576.0 as upload_mb
            FROM radius_sessions
            WHERE subscription_id = ? AND started_at >= CURRENT_DATE - INTERVAL '7 days'
            GROUP BY EXTRACT(HOUR FROM started_at)
            ORDER BY hour
        ");
        $stmt->execute([$subscriptionId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    // ==================== Multi-Router Support ====================
    
    public function syncToMikroTik(int $nasId, int $subscriptionId): array {
        $nas = $this->getNAS($nasId);
        $sub = $this->getSubscription($subscriptionId);
        $package = $this->getPackage($sub['package_id']);
        
        if (!$nas || !$nas['api_enabled']) {
            return ['success' => false, 'error' => 'NAS not configured for API access'];
        }
        
        try {
            $api = new MikroTikAPI(
                $nas['ip_address'],
                $nas['api_port'] ?? 8728,
                $nas['api_username'],
                $this->decrypt($nas['api_password_encrypted'])
            );
            
            $rateLimit = ($package['upload_speed'] ?? '5M') . '/' . ($package['download_speed'] ?? '10M');
            
            $result = $api->addPPPoESecret(
                $sub['username'],
                $sub['password'],
                $package['name'] ?? 'default',
                $sub['static_ip']
            );
            
            return ['success' => true, 'result' => $result];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function removeFromMikroTik(int $nasId, string $username): array {
        $nas = $this->getNAS($nasId);
        
        if (!$nas || !$nas['api_enabled']) {
            return ['success' => false, 'error' => 'NAS not configured for API access'];
        }
        
        try {
            $api = new MikroTikAPI(
                $nas['ip_address'],
                $nas['api_port'] ?? 8728,
                $nas['api_username'],
                $this->decrypt($nas['api_password_encrypted'])
            );
            
            $api->disconnectPPPoE($username);
            $result = $api->removePPPoESecret($username);
            
            return ['success' => true, 'result' => $result];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    // ==================== ISP Settings Management ====================
    
    public function getSettings(?string $category = null): array {
        $sql = "SELECT * FROM isp_settings";
        $params = [];
        
        if ($category) {
            $sql .= " WHERE category = ?";
            $params[] = $category;
        }
        
        $sql .= " ORDER BY category, setting_key";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getSetting(string $key, $default = null) {
        $stmt = $this->db->prepare("SELECT setting_value, setting_type FROM isp_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$row) return $default;
        
        $value = $row['setting_value'];
        
        if ($row['setting_type'] === 'boolean') {
            return $value === 'true' || $value === '1';
        }
        if ($row['setting_type'] === 'number') {
            return (int)$value;
        }
        
        return $value;
    }
    
    public function saveSetting(string $key, $value): bool {
        $stmt = $this->db->prepare("
            INSERT INTO isp_settings (setting_key, setting_value, updated_at)
            VALUES (?, ?, NOW())
            ON CONFLICT (setting_key) DO UPDATE SET setting_value = EXCLUDED.setting_value, updated_at = NOW()
        ");
        return $stmt->execute([$key, is_bool($value) ? ($value ? 'true' : 'false') : (string)$value]);
    }
    
    public function saveSettings(array $settings): bool {
        try {
            $this->db->beginTransaction();
            foreach ($settings as $key => $value) {
                $this->saveSetting($key, $value);
            }
            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }
    
    public function getMessageTemplates(): array {
        return $this->getSettings('templates');
    }
    
    public function processTemplate(string $templateKey, array $variables): string {
        $template = $this->getSetting($templateKey, '');
        
        foreach ($variables as $key => $value) {
            $template = str_replace('{' . $key . '}', $value, $template);
        }
        
        return $template;
    }
    
    public function sendExpiryReminders(): array {
        $results = ['sent' => 0, 'failed' => 0, 'errors' => []];
        
        if (!$this->getSetting('expiry_reminder_enabled', false)) {
            return $results;
        }
        
        $reminderDays = array_map('intval', explode(',', $this->getSetting('expiry_reminder_days', '3,1,0')));
        $channel = $this->getSetting('expiry_reminder_channel', 'sms');
        $sms = new \App\SMS();
        
        foreach ($reminderDays as $days) {
            $expiring = $this->getExpiringSubscriptions($days);
            
            foreach ($expiring as $sub) {
                if (empty($sub['customer_phone'])) continue;
                
                $templateKey = $days === 0 ? 'template_expiry_today' : 'template_expiry_warning';
                $message = $this->processTemplate($templateKey, [
                    'customer_name' => $sub['customer_name'],
                    'package_name' => $sub['package_name'],
                    'days_remaining' => $days,
                    'package_price' => number_format($sub['package_price']),
                    'expiry_date' => date('M j, Y', strtotime($sub['expiry_date'])),
                    'paybill' => $this->getSetting('mpesa_paybill', '')
                ]);
                
                if ($channel === 'sms' || $channel === 'both') {
                    $result = $sms->send($sub['customer_phone'], $message);
                    if ($result['success']) {
                        $results['sent']++;
                    } else {
                        $results['failed']++;
                        $results['errors'][] = $sub['username'] . ': ' . ($result['error'] ?? 'Unknown error');
                    }
                }
            }
        }
        
        return $results;
    }
    
    public function sendPaymentConfirmation(int $subscriptionId, float $amount, string $transactionId): bool {
        if (!$this->getSetting('payment_confirmation_enabled', false)) {
            return false;
        }
        
        $sub = $this->getSubscription($subscriptionId);
        if (!$sub || empty($sub['customer_phone'])) return false;
        
        $message = $this->processTemplate('template_payment_received', [
            'customer_name' => $sub['customer_name'],
            'amount' => number_format($amount),
            'transaction_id' => $transactionId,
            'expiry_date' => date('M j, Y', strtotime($sub['expiry_date'])),
            'package_name' => $sub['package_name']
        ]);
        
        $sms = new \App\SMS();
        $result = $sms->send($sub['customer_phone'], $message);
        return $result['success'] ?? false;
    }
    
    public function sendRenewalConfirmation(int $subscriptionId): bool {
        if (!$this->getSetting('renewal_confirmation_enabled', false)) {
            return false;
        }
        
        $sub = $this->getSubscription($subscriptionId);
        if (!$sub || empty($sub['customer_phone'])) return false;
        
        $message = $this->processTemplate('template_renewal_success', [
            'customer_name' => $sub['customer_name'],
            'package_name' => $sub['package_name'],
            'expiry_date' => date('M j, Y', strtotime($sub['expiry_date']))
        ]);
        
        $sms = new \App\SMS();
        $result = $sms->send($sub['customer_phone'], $message);
        return $result['success'] ?? false;
    }
    
    // ==================== MikroTik Address List Management ====================
    
    public function updateMikroTikBlockedStatus(int $subscriptionId, bool $block, string $reason = ''): array {
        try {
            $sub = $this->getSubscription($subscriptionId);
            if (!$sub) {
                return ['success' => false, 'error' => 'Subscription not found'];
            }
            
            // Only process static IP and DHCP access types
            if (!in_array($sub['access_type'], ['static', 'dhcp'])) {
                return ['success' => true, 'skipped' => true, 'reason' => 'Not a static/DHCP account'];
            }
            
            // Get the IP address to block/unblock
            $ipAddress = $sub['static_ip'] ?? null;
            if (!$ipAddress) {
                return ['success' => false, 'error' => 'No IP address assigned'];
            }
            
            // Get NAS device with API enabled
            $nas = null;
            if (!empty($sub['nas_id'])) {
                $stmt = $this->db->prepare("
                    SELECT * FROM radius_nas 
                    WHERE id = ? AND api_enabled = true AND is_active = true
                ");
                $stmt->execute([$sub['nas_id']]);
                $nas = $stmt->fetch(\PDO::FETCH_ASSOC);
            }
            
            if (!$nas) {
                // Try to find any active NAS with API enabled
                $stmt = $this->db->query("
                    SELECT * FROM radius_nas 
                    WHERE api_enabled = true AND is_active = true 
                    ORDER BY id LIMIT 1
                ");
                $nas = $stmt->fetch(\PDO::FETCH_ASSOC);
            }
            
            if (!$nas) {
                return ['success' => false, 'error' => 'No NAS device with API enabled found'];
            }
            
            // Decrypt API password
            $apiPassword = $this->decryptApiPassword($nas['api_password_encrypted']);
            if (!$apiPassword) {
                return ['success' => false, 'error' => 'Failed to decrypt API password'];
            }
            
            // Connect to MikroTik
            $mikrotik = new \App\MikroTikAPI(
                $nas['ip_address'],
                (int)($nas['api_port'] ?? 8728),
                $nas['api_username'],
                $apiPassword
            );
            
            $mikrotik->connect();
            
            $listName = $this->getSetting('mikrotik_blocked_list') ?: 'DISABLED_USERS';
            $comment = $sub['username'] . ' - ' . $reason . ' (' . date('Y-m-d H:i') . ')';
            
            if ($block) {
                $result = $mikrotik->addToBlockedList($ipAddress, $comment, $listName);
            } else {
                $result = $mikrotik->removeFromBlockedList($ipAddress, $listName);
            }
            
            $mikrotik->disconnect();
            
            return ['success' => $result, 'ip' => $ipAddress, 'action' => $block ? 'blocked' : 'unblocked'];
            
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function syncMikroTikBlockedList(?int $nasId = null): array {
        try {
            // Get NAS device(s) with API enabled
            if ($nasId) {
                $stmt = $this->db->prepare("
                    SELECT * FROM radius_nas 
                    WHERE id = ? AND api_enabled = true AND is_active = true
                ");
                $stmt->execute([$nasId]);
                $nasDevices = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            } else {
                $stmt = $this->db->query("
                    SELECT * FROM radius_nas 
                    WHERE api_enabled = true AND is_active = true
                ");
                $nasDevices = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            }
            
            if (empty($nasDevices)) {
                return ['success' => false, 'error' => 'No NAS devices with API enabled found'];
            }
            
            // Get all blocked subscriptions (expired, suspended, disabled) with static IPs
            $stmt = $this->db->query("
                SELECT s.id, s.username, s.static_ip, s.status, c.name as customer_name
                FROM radius_subscriptions s
                LEFT JOIN customers c ON s.customer_id = c.id
                WHERE s.access_type IN ('static', 'dhcp')
                AND s.static_ip IS NOT NULL
                AND (
                    s.status IN ('expired', 'suspended', 'disabled')
                    OR (s.status = 'active' AND s.expiry_date < CURRENT_DATE)
                )
            ");
            $blockedSubs = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $blockedAddresses = [];
            foreach ($blockedSubs as $sub) {
                $blockedAddresses[] = [
                    'address' => $sub['static_ip'],
                    'comment' => $sub['username'] . ' - ' . ucfirst($sub['status']) . ' (' . date('Y-m-d') . ')'
                ];
            }
            
            $listName = $this->getSetting('mikrotik_blocked_list') ?: 'DISABLED_USERS';
            $results = [];
            
            foreach ($nasDevices as $nas) {
                $apiPassword = $this->decryptApiPassword($nas['api_password_encrypted']);
                if (!$apiPassword) {
                    $results[$nas['name']] = ['success' => false, 'error' => 'Failed to decrypt API password'];
                    continue;
                }
                
                try {
                    $mikrotik = new \App\MikroTikAPI(
                        $nas['ip_address'],
                        (int)($nas['api_port'] ?? 8728),
                        $nas['api_username'],
                        $apiPassword
                    );
                    
                    $mikrotik->connect();
                    $syncResult = $mikrotik->syncBlockedList($blockedAddresses, $listName);
                    $mikrotik->disconnect();
                    
                    $results[$nas['name']] = [
                        'success' => true,
                        'added' => $syncResult['added'],
                        'removed' => $syncResult['removed'],
                        'errors' => $syncResult['errors']
                    ];
                } catch (\Exception $e) {
                    $results[$nas['name']] = ['success' => false, 'error' => $e->getMessage()];
                }
            }
            
            return ['success' => true, 'results' => $results, 'blocked_count' => count($blockedAddresses)];
            
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function getBlockedSubscriptions(): array {
        $stmt = $this->db->query("
            SELECT s.id, s.username, s.static_ip, s.status, s.access_type,
                   s.expiry_date, s.suspended_at, c.name as customer_name, c.phone as customer_phone,
                   p.name as package_name, n.name as nas_name
            FROM radius_subscriptions s
            LEFT JOIN customers c ON s.customer_id = c.id
            LEFT JOIN radius_packages p ON s.package_id = p.id
            LEFT JOIN radius_nas n ON s.nas_id = n.id
            WHERE s.access_type IN ('static', 'dhcp')
            AND s.static_ip IS NOT NULL
            AND (
                s.status IN ('expired', 'suspended', 'disabled')
                OR (s.status = 'active' AND s.expiry_date < CURRENT_DATE)
            )
            ORDER BY s.status, s.expiry_date DESC
        ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    private function decryptApiPassword(?string $encrypted): ?string {
        if (!$encrypted) return null;
        
        try {
            return $this->decrypt($encrypted);
        } catch (\Exception $e) {
            return null;
        }
    }
    
    // ==================== VLAN Management ====================
    
    public function getVlans(?int $nasId = null): array {
        if ($nasId) {
            $stmt = $this->db->prepare("
                SELECT v.*, n.name as nas_name, n.ip_address as nas_ip
                FROM mikrotik_vlans v
                LEFT JOIN radius_nas n ON v.nas_id = n.id
                WHERE v.nas_id = ?
                ORDER BY v.vlan_id
            ");
            $stmt->execute([$nasId]);
        } else {
            $stmt = $this->db->query("
                SELECT v.*, n.name as nas_name, n.ip_address as nas_ip
                FROM mikrotik_vlans v
                LEFT JOIN radius_nas n ON v.nas_id = n.id
                ORDER BY n.name, v.vlan_id
            ");
        }
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getVlan(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT v.*, n.name as nas_name, n.ip_address as nas_ip
            FROM mikrotik_vlans v
            LEFT JOIN radius_nas n ON v.nas_id = n.id
            WHERE v.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    
    public function createVlan(array $data): array {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO mikrotik_vlans 
                (nas_id, name, vlan_id, interface, gateway_ip, network_cidr, 
                 dhcp_pool_start, dhcp_pool_end, dhcp_server_name, dns_servers, lease_time, description)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                RETURNING id
            ");
            $stmt->execute([
                $data['nas_id'],
                $data['name'],
                $data['vlan_id'],
                $data['interface'],
                $data['gateway_ip'] ?? null,
                $data['network_cidr'] ?? null,
                $data['dhcp_pool_start'] ?? null,
                $data['dhcp_pool_end'] ?? null,
                $data['dhcp_server_name'] ?? null,
                $data['dns_servers'] ?? null,
                $data['lease_time'] ?? '1d',
                $data['description'] ?? null
            ]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            return ['success' => true, 'id' => $result['id']];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function updateVlan(int $id, array $data): array {
        try {
            $stmt = $this->db->prepare("
                UPDATE mikrotik_vlans SET
                    name = ?, vlan_id = ?, interface = ?, gateway_ip = ?, network_cidr = ?,
                    dhcp_pool_start = ?, dhcp_pool_end = ?, dhcp_server_name = ?, 
                    dns_servers = ?, lease_time = ?, description = ?, is_active = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([
                $data['name'],
                $data['vlan_id'],
                $data['interface'],
                $data['gateway_ip'] ?? null,
                $data['network_cidr'] ?? null,
                $data['dhcp_pool_start'] ?? null,
                $data['dhcp_pool_end'] ?? null,
                $data['dhcp_server_name'] ?? null,
                $data['dns_servers'] ?? null,
                $data['lease_time'] ?? '1d',
                $data['description'] ?? null,
                $data['is_active'] ?? true,
                $id
            ]);
            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function deleteVlan(int $id): array {
        try {
            $stmt = $this->db->prepare("DELETE FROM mikrotik_vlans WHERE id = ?");
            $stmt->execute([$id]);
            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function syncVlanToMikroTik(int $vlanId): array {
        try {
            $vlan = $this->getVlan($vlanId);
            if (!$vlan) {
                return ['success' => false, 'error' => 'VLAN not found'];
            }
            
            $nas = $this->getNAS($vlan['nas_id']);
            if (!$nas || !$nas['api_enabled']) {
                return ['success' => false, 'error' => 'NAS device not found or API not enabled'];
            }
            
            $apiPassword = $this->decryptApiPassword($nas['api_password_encrypted']);
            if (!$apiPassword) {
                return ['success' => false, 'error' => 'Failed to decrypt API password'];
            }
            
            $mikrotik = new \App\MikroTikAPI(
                $nas['ip_address'],
                (int)($nas['api_port'] ?? 8728),
                $nas['api_username'],
                $apiPassword
            );
            $mikrotik->connect();
            
            $results = ['vlan' => false, 'ip' => false, 'pool' => false, 'network' => false, 'dhcp' => false];
            $errors = [];
            
            // 1. Create VLAN interface
            $existingVlan = $mikrotik->getVlan($vlan['vlan_id']);
            if (!$existingVlan) {
                $vlanResult = $mikrotik->createVlan(
                    $vlan['name'],
                    $vlan['vlan_id'],
                    $vlan['interface'],
                    $vlan['description']
                );
                $results['vlan'] = !isset($vlanResult['error']);
                if (isset($vlanResult['error'])) $errors[] = 'VLAN: ' . $vlanResult['error'];
            } else {
                $results['vlan'] = true;
            }
            
            // 2. Add IP address to VLAN interface
            if ($vlan['gateway_ip'] && $vlan['network_cidr']) {
                $ipResult = $mikrotik->addIpAddress(
                    $vlan['gateway_ip'] . '/' . explode('/', $vlan['network_cidr'])[1],
                    $vlan['name'],
                    'CRM VLAN ' . $vlan['vlan_id']
                );
                $results['ip'] = !isset($ipResult['error']);
                if (isset($ipResult['error'])) $errors[] = 'IP: ' . $ipResult['error'];
            }
            
            // 3. Create IP Pool for DHCP
            if ($vlan['dhcp_pool_start'] && $vlan['dhcp_pool_end']) {
                $poolName = 'pool-vlan' . $vlan['vlan_id'];
                $poolResult = $mikrotik->createIpPool(
                    $poolName,
                    $vlan['dhcp_pool_start'] . '-' . $vlan['dhcp_pool_end'],
                    'CRM VLAN ' . $vlan['vlan_id']
                );
                $results['pool'] = !isset($poolResult['error']);
                if (isset($poolResult['error'])) $errors[] = 'Pool: ' . $poolResult['error'];
                
                // 4. Add DHCP Network
                if ($vlan['network_cidr'] && $vlan['gateway_ip']) {
                    $networkResult = $mikrotik->addDhcpNetwork(
                        $vlan['network_cidr'],
                        $vlan['gateway_ip'],
                        $vlan['dns_servers'],
                        'CRM VLAN ' . $vlan['vlan_id']
                    );
                    $results['network'] = !isset($networkResult['error']);
                    if (isset($networkResult['error'])) $errors[] = 'Network: ' . $networkResult['error'];
                }
                
                // 5. Create DHCP Server
                $dhcpName = $vlan['dhcp_server_name'] ?: 'dhcp-vlan' . $vlan['vlan_id'];
                $dhcpResult = $mikrotik->createDhcpServer(
                    $dhcpName,
                    $vlan['name'],
                    $poolName,
                    $vlan['lease_time'] ?: '1d'
                );
                $results['dhcp'] = !isset($dhcpResult['error']);
                if (isset($dhcpResult['error'])) $errors[] = 'DHCP: ' . $dhcpResult['error'];
                
                // Update DHCP server name in DB if we created it
                if ($results['dhcp'] && !$vlan['dhcp_server_name']) {
                    $this->db->prepare("UPDATE mikrotik_vlans SET dhcp_server_name = ? WHERE id = ?")
                        ->execute([$dhcpName, $vlanId]);
                }
            }
            
            $mikrotik->disconnect();
            
            // Mark as synced
            $this->db->prepare("UPDATE mikrotik_vlans SET is_synced = true, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
                ->execute([$vlanId]);
            
            $allSuccess = !in_array(false, $results, true);
            return [
                'success' => $allSuccess,
                'results' => $results,
                'errors' => $errors
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function importVlansFromMikroTik(?int $nasId = null): array {
        try {
            $imported = 0;
            $skipped = 0;
            $errors = [];
            $nasList = [];
            
            if ($nasId) {
                $nas = $this->getNAS($nasId);
                if ($nas && $nas['api_enabled']) {
                    $nasList[] = $nas;
                }
            } else {
                $stmt = $this->db->query("SELECT * FROM radius_nas WHERE is_active = TRUE AND api_enabled = TRUE");
                $nasList = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            }
            
            if (empty($nasList)) {
                return ['success' => false, 'error' => 'No NAS devices with API enabled'];
            }
            
            foreach ($nasList as $nas) {
                $apiPassword = $this->decryptApiPassword($nas['api_password_encrypted']);
                if (!$apiPassword) {
                    $errors[] = $nas['name'] . ': Failed to decrypt API password';
                    continue;
                }
                
                try {
                    $mikrotik = new \App\MikroTikAPI(
                        $nas['ip_address'],
                        (int)($nas['api_port'] ?? 8728),
                        $nas['api_username'],
                        $apiPassword
                    );
                    $mikrotik->connect();
                    
                    $vlans = $mikrotik->getVlans();
                    $mikrotik->disconnect();
                    
                    if (empty($vlans)) {
                        $errors[] = $nas['name'] . ': No VLANs found or API returned empty';
                        continue;
                    }
                    
                    foreach ($vlans as $vlan) {
                        $vlanId = (int)($vlan['vlan-id'] ?? 0);
                        $name = $vlan['name'] ?? '';
                        $interface = $vlan['interface'] ?? '';
                        
                        if (!$vlanId || !$name) continue;
                        
                        // Check if already exists
                        $existingStmt = $this->db->prepare("
                            SELECT id FROM mikrotik_vlans 
                            WHERE nas_id = ? AND (vlan_id = ? OR name = ?)
                        ");
                        $existingStmt->execute([$nas['id'], $vlanId, $name]);
                        
                        if ($existingStmt->fetch()) {
                            $skipped++;
                            continue;
                        }
                        
                        // Import the VLAN
                        $insertStmt = $this->db->prepare("
                            INSERT INTO mikrotik_vlans (nas_id, name, vlan_id, interface, description, is_synced, created_at)
                            VALUES (?, ?, ?, ?, ?, TRUE, CURRENT_TIMESTAMP)
                        ");
                        $insertStmt->execute([
                            $nas['id'],
                            $name,
                            $vlanId,
                            $interface,
                            'Imported from ' . $nas['name']
                        ]);
                        $imported++;
                    }
                } catch (\Exception $e) {
                    $errors[] = $nas['name'] . ': ' . $e->getMessage();
                }
            }
            
            return [
                'success' => true,
                'imported' => $imported,
                'skipped' => $skipped,
                'errors' => $errors
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    // ==================== Static IP Provisioning ====================
    
    public function getProvisionedIps(?int $subscriptionId = null): array {
        if ($subscriptionId) {
            $stmt = $this->db->prepare("
                SELECT p.*, v.name as vlan_name, v.vlan_id, n.name as nas_name,
                       s.username, c.name as customer_name
                FROM mikrotik_provisioned_ips p
                LEFT JOIN mikrotik_vlans v ON p.vlan_id = v.id
                LEFT JOIN radius_nas n ON p.nas_id = n.id
                LEFT JOIN radius_subscriptions s ON p.subscription_id = s.id
                LEFT JOIN customers c ON s.customer_id = c.id
                WHERE p.subscription_id = ?
                ORDER BY p.created_at DESC
            ");
            $stmt->execute([$subscriptionId]);
        } else {
            $stmt = $this->db->query("
                SELECT p.*, v.name as vlan_name, v.vlan_id, n.name as nas_name,
                       s.username, c.name as customer_name
                FROM mikrotik_provisioned_ips p
                LEFT JOIN mikrotik_vlans v ON p.vlan_id = v.id
                LEFT JOIN radius_nas n ON p.nas_id = n.id
                LEFT JOIN radius_subscriptions s ON p.subscription_id = s.id
                LEFT JOIN customers c ON s.customer_id = c.id
                ORDER BY p.created_at DESC
            ");
        }
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function provisionStaticIp(int $subscriptionId, int $vlanId, string $ipAddress, string $macAddress): array {
        try {
            $sub = $this->getSubscription($subscriptionId);
            if (!$sub) {
                return ['success' => false, 'error' => 'Subscription not found'];
            }
            
            $vlan = $this->getVlan($vlanId);
            if (!$vlan) {
                return ['success' => false, 'error' => 'VLAN not found'];
            }
            
            $nas = $this->getNAS($vlan['nas_id']);
            if (!$nas || !$nas['api_enabled']) {
                return ['success' => false, 'error' => 'NAS device not found or API not enabled'];
            }
            
            $apiPassword = $this->decryptApiPassword($nas['api_password_encrypted']);
            if (!$apiPassword) {
                return ['success' => false, 'error' => 'Failed to decrypt API password'];
            }
            
            // Format MAC address
            $macFormatted = strtoupper(preg_replace('/[^A-Fa-f0-9]/', '', $macAddress));
            $macFormatted = implode(':', str_split($macFormatted, 2));
            
            $mikrotik = new \App\MikroTikAPI(
                $nas['ip_address'],
                (int)($nas['api_port'] ?? 8728),
                $nas['api_username'],
                $apiPassword
            );
            $mikrotik->connect();
            
            $comment = $sub['username'] . ' - ' . ($sub['customer_name'] ?? 'Customer');
            
            // Create DHCP lease
            $dhcpServer = $vlan['dhcp_server_name'] ?: 'dhcp-vlan' . $vlan['vlan_id'];
            $leaseResult = $mikrotik->createDhcpLease($ipAddress, $macFormatted, $dhcpServer, $comment);
            
            $mikrotik->disconnect();
            
            if (isset($leaseResult['error'])) {
                return ['success' => false, 'error' => 'Failed to create DHCP lease: ' . $leaseResult['error']];
            }
            
            // Save to database
            $stmt = $this->db->prepare("
                INSERT INTO mikrotik_provisioned_ips 
                (subscription_id, nas_id, vlan_id, ip_address, mac_address, provision_type, comment, is_synced, synced_at)
                VALUES (?, ?, ?, ?, ?, 'dhcp_lease', ?, true, CURRENT_TIMESTAMP)
                RETURNING id
            ");
            $stmt->execute([
                $subscriptionId,
                $nas['id'],
                $vlanId,
                $ipAddress,
                $macFormatted,
                $comment
            ]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            // Update subscription with static IP
            $this->db->prepare("
                UPDATE radius_subscriptions SET 
                    static_ip = ?, mac_address = ?, nas_id = ?, 
                    access_type = 'static', updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ")->execute([$ipAddress, $macFormatted, $nas['id'], $subscriptionId]);
            
            return ['success' => true, 'id' => $result['id'], 'ip' => $ipAddress, 'mac' => $macFormatted];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function deprovisionStaticIp(int $subscriptionId): array {
        try {
            $sub = $this->getSubscription($subscriptionId);
            if (!$sub) {
                return ['success' => false, 'error' => 'Subscription not found'];
            }
            
            // Get provisioned IP record
            $stmt = $this->db->prepare("SELECT * FROM mikrotik_provisioned_ips WHERE subscription_id = ?");
            $stmt->execute([$subscriptionId]);
            $provision = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($provision && $provision['nas_id']) {
                $nas = $this->getNAS($provision['nas_id']);
                if ($nas && $nas['api_enabled']) {
                    $apiPassword = $this->decryptApiPassword($nas['api_password_encrypted']);
                    if ($apiPassword) {
                        try {
                            $mikrotik = new \App\MikroTikAPI(
                                $nas['ip_address'],
                                (int)($nas['api_port'] ?? 8728),
                                $nas['api_username'],
                                $apiPassword
                            );
                            $mikrotik->connect();
                            
                            // Remove DHCP lease
                            if ($provision['ip_address']) {
                                $mikrotik->removeDhcpLease($provision['ip_address']);
                            }
                            
                            $mikrotik->disconnect();
                        } catch (\Exception $e) {
                            // Log but continue
                        }
                    }
                }
            }
            
            // Delete provision record
            $this->db->prepare("DELETE FROM mikrotik_provisioned_ips WHERE subscription_id = ?")
                ->execute([$subscriptionId]);
            
            // Clear static IP from subscription
            $this->db->prepare("
                UPDATE radius_subscriptions SET 
                    static_ip = NULL, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ")->execute([$subscriptionId]);
            
            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function fetchMikroTikInterfaces(int $nasId): array {
        try {
            $nas = $this->getNAS($nasId);
            if (!$nas || !$nas['api_enabled']) {
                return ['success' => false, 'error' => 'NAS not found or API not enabled'];
            }
            
            $apiPassword = $this->decryptApiPassword($nas['api_password_encrypted']);
            if (!$apiPassword) {
                return ['success' => false, 'error' => 'Failed to decrypt API password'];
            }
            
            $mikrotik = new \App\MikroTikAPI(
                $nas['ip_address'],
                (int)($nas['api_port'] ?? 8728),
                $nas['api_username'],
                $apiPassword
            );
            
            if (!$mikrotik->connect()) {
                return ['success' => false, 'error' => 'Failed to connect to MikroTik'];
            }
            
            // Test with a single command first - get interfaces directly
            $interfacesRaw = $mikrotik->commandRaw('/interface/print');
            
            // Reconnect to get parsed data cleanly
            $mikrotik->disconnect();
            $mikrotik->connect();
            
            $interfaces = $mikrotik->getInterfaces();
            $vlans = $mikrotik->getVlans();
            $bridges = $mikrotik->getBridges();
            $ethernet = $mikrotik->getEthernetInterfaces();
            
            // Get identity last
            $identity = $mikrotik->command('/system/identity/print');
            
            $mikrotik->disconnect();
            
            // If all are empty, show debug info
            if (empty($interfaces) && empty($vlans) && empty($bridges) && empty($ethernet)) {
                $identityName = $identity[0]['name'] ?? 'unknown';
                return [
                    'success' => false, 
                    'error' => "Connected to '{$identityName}' but no interfaces found. API user: " . $nas['api_username'],
                    'debug' => [
                        'interfaces_raw' => $interfacesRaw,
                        'interfaces_count' => count($interfacesRaw),
                        'identity' => $identity
                    ]
                ];
            }
            
            return [
                'success' => true,
                'interfaces' => $interfaces,
                'vlans' => $vlans,
                'bridges' => $bridges,
                'ethernet' => $ethernet
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
