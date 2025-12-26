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
        
        // Get expired subscriptions past grace period
        $stmt = $this->db->query("
            SELECT id, grace_period_days, expiry_date FROM radius_subscriptions 
            WHERE status = 'active' 
            AND expiry_date < CURRENT_DATE - INTERVAL '1 day' * grace_period_days
        ");
        
        while ($sub = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $this->db->prepare("UPDATE radius_subscriptions SET status = 'expired' WHERE id = ?")
                ->execute([$sub['id']]);
            $processed++;
        }
        
        return ['success' => true, 'processed' => $processed];
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
}
