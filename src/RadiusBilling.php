<?php
namespace App;

class RadiusBilling {
    private \PDO $db;
    private string $encryptionKey;
    
    public function __construct(\PDO $db) {
        $this->db = $db;
        $this->encryptionKey = $_ENV['ENCRYPTION_KEY'] ?? 'default-radius-key-change-me';
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
    
    // ==================== NAS Management ====================
    
    public function getNASDevices(): array {
        $stmt = $this->db->query("SELECT * FROM radius_nas ORDER BY name");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getNAS(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM radius_nas WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    
    public function createNAS(array $data): array {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO radius_nas (name, ip_address, secret, nas_type, ports, description, 
                                        api_enabled, api_port, api_username, api_password_encrypted, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['name'],
                $data['ip_address'],
                $data['secret'],
                $data['nas_type'] ?? 'mikrotik',
                $data['ports'] ?? 1812,
                $data['description'] ?? '',
                $data['api_enabled'] ?? false,
                $data['api_port'] ?? 8728,
                $data['api_username'] ?? '',
                !empty($data['api_password']) ? $this->encrypt($data['api_password']) : '',
                $data['is_active'] ?? true
            ]);
            return ['success' => true, 'id' => $this->db->lastInsertId()];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function updateNAS(int $id, array $data): array {
        try {
            $fields = ['name', 'ip_address', 'secret', 'nas_type', 'ports', 'description', 
                       'api_enabled', 'api_port', 'api_username', 'is_active'];
            $updates = [];
            $params = [];
            
            foreach ($fields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }
            
            if (!empty($data['api_password'])) {
                $updates[] = "api_password_encrypted = ?";
                $params[] = $this->encrypt($data['api_password']);
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
    
    // ==================== Package Management ====================
    
    public function getPackages(string $type = null): array {
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
                    simultaneous_sessions, fup_enabled, fup_quota_mb, fup_download_speed, fup_upload_speed, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
                $data['ip_binding'] ?? false,
                $data['simultaneous_sessions'] ?? 1,
                $data['fup_enabled'] ?? false,
                $data['fup_quota_mb'] ?: null,
                $data['fup_download_speed'] ?? '',
                $data['fup_upload_speed'] ?? '',
                $data['is_active'] ?? true
            ]);
            return ['success' => true, 'id' => $this->db->lastInsertId()];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function updatePackage(int $id, array $data): array {
        try {
            $stmt = $this->db->prepare("
                UPDATE radius_packages SET
                    name = ?, description = ?, package_type = ?, billing_type = ?, price = ?,
                    validity_days = ?, data_quota_mb = ?, download_speed = ?, upload_speed = ?,
                    burst_download = ?, burst_upload = ?, burst_threshold = ?, burst_time = ?,
                    priority = ?, address_pool = ?, ip_binding = ?, simultaneous_sessions = ?,
                    fup_enabled = ?, fup_quota_mb = ?, fup_download_speed = ?, fup_upload_speed = ?,
                    is_active = ?, updated_at = CURRENT_TIMESTAMP
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
                $data['ip_binding'] ?? false,
                $data['simultaneous_sessions'] ?? 1,
                $data['fup_enabled'] ?? false,
                $data['fup_quota_mb'] ?: null,
                $data['fup_download_speed'] ?? '',
                $data['fup_upload_speed'] ?? '',
                $data['is_active'] ?? true,
                $id
            ]);
            return ['success' => true];
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
        
        $sql .= " ORDER BY s.created_at DESC";
        
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT " . (int)$filters['limit'];
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getSubscription(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT s.*, c.name as customer_name, c.phone as customer_phone,
                   p.name as package_name, p.download_speed, p.upload_speed
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
            
            $startDate = date('Y-m-d');
            $expiryDate = date('Y-m-d', strtotime("+{$package['validity_days']} days"));
            
            $stmt = $this->db->prepare("
                INSERT INTO radius_subscriptions (customer_id, package_id, username, password, password_encrypted,
                    access_type, static_ip, mac_address, status, start_date, expiry_date, nas_id, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['customer_id'],
                $data['package_id'],
                $data['username'],
                $data['password'], // Cleartext for RADIUS CHAP/MS-CHAP
                $this->encrypt($data['password']),
                $data['access_type'] ?? $package['package_type'],
                $data['static_ip'] ?? null,
                $data['mac_address'] ?? null,
                'active',
                $startDate,
                $expiryDate,
                $data['nas_id'] ?? null,
                $data['notes'] ?? ''
            ]);
            
            $subscriptionId = $this->db->lastInsertId();
            
            // Create billing record
            $this->createBillingRecord($subscriptionId, $data['package_id'], $package['price'], 'renewal', $startDate, $expiryDate);
            
            return ['success' => true, 'id' => $subscriptionId];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function renewSubscription(int $id, int $packageId = null): array {
        try {
            $sub = $this->getSubscription($id);
            if (!$sub) {
                return ['success' => false, 'error' => 'Subscription not found'];
            }
            
            $packageId = $packageId ?? $sub['package_id'];
            $package = $this->getPackage($packageId);
            if (!$package) {
                return ['success' => false, 'error' => 'Package not found'];
            }
            
            $startDate = max(date('Y-m-d'), $sub['expiry_date'] ?? date('Y-m-d'));
            $expiryDate = date('Y-m-d', strtotime($startDate . " +{$package['validity_days']} days"));
            
            $stmt = $this->db->prepare("
                UPDATE radius_subscriptions SET
                    package_id = ?, status = 'active', start_date = ?, expiry_date = ?,
                    data_used_mb = 0, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([$packageId, $startDate, $expiryDate, $id]);
            
            $this->createBillingRecord($id, $packageId, $package['price'], 'renewal', $startDate, $expiryDate);
            
            return ['success' => true, 'expiry_date' => $expiryDate];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function suspendSubscription(int $id, string $reason = ''): array {
        try {
            $stmt = $this->db->prepare("
                UPDATE radius_subscriptions SET status = 'suspended', notes = CONCAT(notes, ?), updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute(["\nSuspended: $reason (" . date('Y-m-d H:i') . ")", $id]);
            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function activateSubscription(int $id): array {
        try {
            $stmt = $this->db->prepare("
                UPDATE radius_subscriptions SET status = 'active', updated_at = CURRENT_TIMESTAMP WHERE id = ?
            ");
            $stmt->execute([$id]);
            return ['success' => true];
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
                $data['mac_address'] ?? ''
            ]);
            
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
    
    public function getBillingHistory(int $subscriptionId = null, int $limit = 50): array {
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
    
    public function authenticate(string $username, string $password, string $nasIp = ''): array {
        $sub = $this->getSubscriptionByUsername($username);
        
        if (!$sub) {
            return ['success' => false, 'reply' => 'Access-Reject', 'reason' => 'User not found'];
        }
        
        // Check password
        if ($this->decrypt($sub['password_encrypted']) !== $password) {
            return ['success' => false, 'reply' => 'Access-Reject', 'reason' => 'Invalid password'];
        }
        
        // Check status
        if ($sub['status'] !== 'active') {
            return ['success' => false, 'reply' => 'Access-Reject', 'reason' => 'Account ' . $sub['status']];
        }
        
        // Check expiry
        if ($sub['expiry_date'] && strtotime($sub['expiry_date']) < time()) {
            // Check grace period
            $graceEnd = strtotime($sub['expiry_date'] . " +{$sub['grace_period_days']} days");
            if (time() > $graceEnd) {
                return ['success' => false, 'reply' => 'Access-Reject', 'reason' => 'Subscription expired'];
            }
        }
        
        // Check quota
        if ($sub['data_quota_mb'] && $sub['data_used_mb'] >= $sub['data_quota_mb']) {
            if (!$sub['fup_enabled']) {
                return ['success' => false, 'reply' => 'Access-Reject', 'reason' => 'Data quota exhausted'];
            }
        }
        
        // Build RADIUS attributes
        $attributes = [
            'Mikrotik-Rate-Limit' => $this->buildRateLimit($sub),
        ];
        
        if ($sub['static_ip']) {
            $attributes['Framed-IP-Address'] = $sub['static_ip'];
        }
        
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
        
        // Check if FUP applies
        if ($sub['fup_enabled'] && $sub['data_quota_mb'] && $sub['data_used_mb'] >= $sub['fup_quota_mb']) {
            $download = $sub['fup_download_speed'] ?? '1M';
            $upload = $sub['fup_upload_speed'] ?? '512k';
        }
        
        return "{$upload}/{$download}";
    }
    
    // ==================== Automation ====================
    
    public function processExpiredSubscriptions(): array {
        $processed = 0;
        $ipsReleased = 0;
        
        // Get expired subscriptions past grace period
        $stmt = $this->db->query("
            SELECT id, grace_period_days, expiry_date, static_ip FROM radius_subscriptions 
            WHERE status = 'active' 
            AND expiry_date < CURRENT_DATE - INTERVAL '1 day' * grace_period_days
        ");
        
        while ($sub = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            // Disconnect active sessions
            $this->disconnectUser($sub['id']);
            
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
            
            if ($releaseIp) {
                $ipsReleased++;
            }
            $processed++;
        }
        
        return ['success' => true, 'processed' => $processed, 'ips_released' => $ipsReleased];
    }
    
    public function getSubscriberOnlineStatus(int $subscriptionId): bool {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM radius_sessions 
            WHERE subscription_id = ? AND stopped_at IS NULL
        ");
        $stmt->execute([$subscriptionId]);
        return (int)$stmt->fetchColumn() > 0;
    }
    
    public function getOnlineSubscribers(): array {
        $stmt = $this->db->query("
            SELECT DISTINCT subscription_id FROM radius_sessions 
            WHERE stopped_at IS NULL
        ");
        return array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'subscription_id');
    }
    
    public function processAutoRenewals(): array {
        $renewed = 0;
        
        // Get subscriptions with auto_renew that expired today
        $stmt = $this->db->query("
            SELECT s.id, s.package_id, b.status as billing_status FROM radius_subscriptions s
            LEFT JOIN radius_billing b ON b.subscription_id = s.id AND b.period_end = s.expiry_date
            WHERE s.auto_renew = TRUE AND s.expiry_date = CURRENT_DATE AND s.status = 'active'
            AND (b.status IS NULL OR b.status = 'paid')
        ");
        
        while ($sub = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $result = $this->renewSubscription($sub['id']);
            if ($result['success']) $renewed++;
        }
        
        return ['success' => true, 'renewed' => $renewed];
    }
    
    // ==================== Expiry & Alerts ====================
    
    public function getExpiringSubscriptions(int $days = 7): array {
        $stmt = $this->db->prepare("
            SELECT s.*, c.name as customer_name, c.phone as customer_phone, c.email as customer_email,
                   p.name as package_name, p.price as package_price,
                   EXTRACT(DAY FROM s.expiry_date - CURRENT_DATE) as days_remaining
            FROM radius_subscriptions s
            LEFT JOIN customers c ON s.customer_id = c.id
            LEFT JOIN radius_packages p ON s.package_id = p.id
            WHERE s.status = 'active' AND s.expiry_date BETWEEN CURRENT_DATE AND CURRENT_DATE + INTERVAL '1 day' * ?
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
    
    public function processPayment(string $transactionId, string $phone, float $amount, string $accountRef = ''): array {
        // Find subscription by phone or account reference
        $stmt = $this->db->prepare("
            SELECT s.* FROM radius_subscriptions s
            LEFT JOIN customers c ON s.customer_id = c.id
            WHERE c.phone LIKE ? OR s.username = ?
            LIMIT 1
        ");
        $phoneClean = preg_replace('/^254/', '0', $phone);
        $stmt->execute(['%' . $phoneClean, $accountRef]);
        $sub = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$sub) {
            return ['success' => false, 'error' => 'Subscription not found for phone: ' . $phone];
        }
        
        $package = $this->getPackage($sub['package_id']);
        if (!$package) {
            return ['success' => false, 'error' => 'Package not found'];
        }
        
        // Check if payment covers package price
        if ($amount < $package['price']) {
            return ['success' => false, 'error' => 'Insufficient amount. Package costs KES ' . $package['price']];
        }
        
        // Renew subscription
        $result = $this->renewSubscription($sub['id']);
        
        if ($result['success']) {
            // Record payment
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
    
    // ==================== CoA (Change of Authorization) ====================
    
    public function disconnectUser(int $subscriptionId): array {
        $sub = $this->getSubscription($subscriptionId);
        if (!$sub) {
            return ['success' => false, 'error' => 'Subscription not found'];
        }
        
        // Get active sessions
        $stmt = $this->db->prepare("
            SELECT rs.*, rn.ip_address as nas_ip, rn.secret as nas_secret
            FROM radius_sessions rs
            LEFT JOIN radius_nas rn ON rs.nas_id = rn.id
            WHERE rs.subscription_id = ? AND rs.stopped_at IS NULL
        ");
        $stmt->execute([$subscriptionId]);
        $sessions = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $disconnected = 0;
        foreach ($sessions as $session) {
            if ($this->sendCoADisconnect($session)) {
                $disconnected++;
            }
        }
        
        return ['success' => true, 'disconnected' => $disconnected];
    }
    
    private function sendCoADisconnect(array $session): bool {
        if (empty($session['nas_ip']) || empty($session['nas_secret'])) {
            return false;
        }
        
        // Build CoA Disconnect-Request packet
        // This requires radclient or custom RADIUS implementation
        $command = sprintf(
            'echo "Acct-Session-Id=%s,User-Name=%s" | radclient -x %s:3799 disconnect %s 2>&1',
            escapeshellarg($session['session_id']),
            escapeshellarg($session['username'] ?? ''),
            escapeshellarg($session['nas_ip']),
            escapeshellarg($session['nas_secret'])
        );
        
        exec($command, $output, $returnCode);
        return $returnCode === 0;
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
    
    // ==================== IP Pool Management ====================
    
    public function getIPPools(): array {
        $stmt = $this->db->query("SELECT * FROM radius_ip_pools ORDER BY name");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function createIPPool(array $data): array {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO radius_ip_pools (name, start_ip, end_ip, gateway, netmask, dns1, dns2, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['name'],
                $data['start_ip'],
                $data['end_ip'],
                $data['gateway'] ?? null,
                $data['netmask'] ?? '255.255.255.0',
                $data['dns1'] ?? '8.8.8.8',
                $data['dns2'] ?? '8.8.4.4',
                $data['is_active'] ?? true
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
            exec("ping -c 1 -W 2 " . escapeshellarg($nas['ip_address']) . " 2>&1", $output, $returnCode);
            if ($returnCode === 0) {
                $status['online'] = true;
                if (preg_match('/time=(\d+\.?\d*)/', implode("\n", $output), $matches)) {
                    $status['latency_ms'] = (float)$matches[1];
                }
            }
            
            // Active sessions count
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM radius_sessions 
                WHERE nas_id = ? AND stopped_at IS NULL
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
            
            // Disconnect user to apply new speed
            $this->disconnectUser($subscriptionId);
            
            return [
                'success' => true,
                'old_package' => $oldPackage['name'],
                'new_package' => $newPackage['name'],
                'credit_amount' => $creditAmount,
                'prorated_amount' => $proratedAmount,
                'difference' => $proratedAmount - $creditAmount
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
