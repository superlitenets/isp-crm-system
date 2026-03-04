<?php
namespace LicenseServer;

class License {
    private $db;
    private $config;
    
    public function __construct(\PDO $db, array $config) {
        $this->db = $db;
        $this->config = $config;
    }
    
    public function generateLicenseKey(): string {
        $segments = [];
        for ($i = 0; $i < 4; $i++) {
            $segments[] = strtoupper(bin2hex(random_bytes(4)));
        }
        return implode('-', $segments);
    }
    
    public function generateActivationToken(): string {
        return bin2hex(random_bytes(32));
    }
    
    public function createLicense(array $data): ?array {
        $licenseKey = $this->generateLicenseKey();
        
        $stmt = $this->db->prepare("
            INSERT INTO licenses (license_key, customer_id, product_id, tier_id, domain_restriction, max_activations, expires_at, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            RETURNING *
        ");
        
        $expiresAt = null;
        if (!empty($data['duration_months'])) {
            $expiresAt = date('Y-m-d H:i:s', strtotime("+{$data['duration_months']} months"));
        } elseif (!empty($data['expires_at'])) {
            $expiresAt = $data['expires_at'];
        }
        
        $stmt->execute([
            $licenseKey,
            $data['customer_id'],
            $data['product_id'],
            $data['tier_id'],
            $data['domain_restriction'] ?? null,
            $data['max_activations'] ?? 1,
            $expiresAt,
            $data['notes'] ?? null
        ]);
        
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
    
    public function validateLicense(string $licenseKey, array $clientInfo = []): array {
        $stmt = $this->db->prepare("
            SELECT l.*, 
                   c.name as customer_name, c.email as customer_email, c.company,
                   p.code as product_code, p.name as product_name, p.features as product_features, p.current_version,
                   t.code as tier_code, t.name as tier_name, t.max_users, t.max_customers, t.max_onus, t.max_olts, t.max_subscribers, t.features as tier_features
            FROM licenses l
            LEFT JOIN license_customers c ON l.customer_id = c.id
            LEFT JOIN license_products p ON l.product_id = p.id
            LEFT JOIN license_tiers t ON l.tier_id = t.id
            WHERE l.license_key = ?
        ");
        $stmt->execute([$licenseKey]);
        $license = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$license) {
            return ['valid' => false, 'error' => 'invalid_license', 'message' => 'License key not found'];
        }
        
        if (!$license['is_active']) {
            return ['valid' => false, 'error' => 'inactive', 'message' => 'License is inactive'];
        }
        
        if ($license['is_suspended']) {
            return ['valid' => false, 'error' => 'suspended', 'message' => 'License is suspended: ' . ($license['suspension_reason'] ?? 'Contact support')];
        }
        
        if ($license['expires_at'] && strtotime($license['expires_at']) < time()) {
            return ['valid' => false, 'error' => 'expired', 'message' => 'License has expired', 'expired_at' => $license['expires_at']];
        }
        
        if (!empty($license['domain_restriction']) && !empty($clientInfo['domain'])) {
            $allowedDomains = array_map('trim', explode(',', $license['domain_restriction']));
            $clientDomain = strtolower($clientInfo['domain']);
            $domainMatch = false;
            foreach ($allowedDomains as $allowed) {
                if (fnmatch(strtolower($allowed), $clientDomain)) {
                    $domainMatch = true;
                    break;
                }
            }
            if (!$domainMatch) {
                return ['valid' => false, 'error' => 'domain_mismatch', 'message' => 'License not valid for this domain'];
            }
        }
        
        $activationCount = $this->getActivationCount($license['id']);
        if ($license['max_activations'] > 0 && $activationCount >= $license['max_activations']) {
            $existingActivation = $this->findExistingActivation($license['id'], $clientInfo);
            if (!$existingActivation) {
                return ['valid' => false, 'error' => 'max_activations', 'message' => 'Maximum activations reached'];
            }
        }
        
        return [
            'valid' => true,
            'license' => [
                'key' => $license['license_key'],
                'customer' => $license['customer_name'],
                'company' => $license['company'],
                'product' => $license['product_name'],
                'tier' => $license['tier_name'],
                'tier_code' => $license['tier_code'],
                'expires_at' => $license['expires_at'],
                'max_users' => (int)$license['max_users'],
                'max_customers' => (int)$license['max_customers'],
                'max_onus' => (int)$license['max_onus'],
                'max_olts' => (int)$license['max_olts'],
                'max_subscribers' => (int)$license['max_subscribers'],
                'features' => json_decode($license['tier_features'] ?: '{}', true),
                'current_version' => $license['current_version']
            ]
        ];
    }
    
    public function activate(string $licenseKey, array $clientInfo): array {
        $validation = $this->validateLicense($licenseKey, $clientInfo);
        if (!$validation['valid']) {
            $this->logValidation(null, null, 'activation_failed', $clientInfo, 'error', $validation['message']);
            return $validation;
        }
        
        $stmt = $this->db->prepare("SELECT id FROM licenses WHERE license_key = ?");
        $stmt->execute([$licenseKey]);
        $licenseId = $stmt->fetchColumn();
        
        $existing = $this->findExistingActivation($licenseId, $clientInfo);
        
        if ($existing) {
            $stmt = $this->db->prepare("
                UPDATE license_activations 
                SET last_seen_at = NOW(), last_validated_at = NOW(),
                    php_version = COALESCE(?, php_version),
                    os_info = COALESCE(?, os_info),
                    app_version = COALESCE(?, app_version),
                    server_ip = COALESCE(?, server_ip)
                WHERE id = ?
            ");
            $stmt->execute([
                $clientInfo['php_version'] ?? null,
                $clientInfo['os_info'] ?? null,
                $clientInfo['app_version'] ?? null,
                $clientInfo['server_ip'] ?? null,
                $existing['id']
            ]);
            $activationToken = $existing['activation_token'];
        } else {
            $activationToken = $this->generateActivationToken();
            $stmt = $this->db->prepare("
                INSERT INTO license_activations (license_id, activation_token, domain, server_ip, server_hostname, hardware_id, php_version, os_info, app_version)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $licenseId,
                $activationToken,
                $clientInfo['domain'] ?? null,
                $clientInfo['server_ip'] ?? null,
                $clientInfo['hostname'] ?? null,
                $clientInfo['hardware_id'] ?? null,
                $clientInfo['php_version'] ?? null,
                $clientInfo['os_info'] ?? null,
                $clientInfo['app_version'] ?? null
            ]);
        }
        
        $this->logValidation($licenseId, null, 'activation', $clientInfo, 'success', 'License activated');
        
        $updateInfo = $this->getAvailableUpdate($clientInfo['app_version'] ?? '0.0.0', $licenseId);
        
        $result = [
            'valid' => true,
            'activated' => true,
            'activation_token' => $activationToken,
            'license' => $validation['license']
        ];
        
        if ($updateInfo) {
            $result['update_available'] = $updateInfo;
        }
        
        return $result;
    }
    
    public function heartbeat(string $activationToken, array $clientStats = []): array {
        $stmt = $this->db->prepare("
            SELECT a.*, l.license_key, l.is_active, l.is_suspended, l.expires_at, l.product_id,
                   t.code as tier_code, t.name as tier_name, t.max_users, t.max_customers, t.max_onus, t.max_olts, t.max_subscribers, t.features
            FROM license_activations a
            JOIN licenses l ON a.license_id = l.id
            LEFT JOIN license_tiers t ON l.tier_id = t.id
            WHERE a.activation_token = ? AND a.is_active = TRUE
        ");
        $stmt->execute([$activationToken]);
        $activation = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$activation) {
            return ['valid' => false, 'error' => 'invalid_token', 'message' => 'Activation not found'];
        }
        
        if (!$activation['is_active']) {
            return ['valid' => false, 'error' => 'inactive', 'message' => 'License is inactive'];
        }
        
        if ($activation['is_suspended']) {
            return ['valid' => false, 'error' => 'suspended', 'message' => 'License is suspended'];
        }
        
        if ($activation['expires_at'] && strtotime($activation['expires_at']) < time()) {
            return ['valid' => false, 'error' => 'expired', 'message' => 'License has expired'];
        }
        
        $updateFields = ['last_seen_at = NOW()', 'last_validated_at = NOW()'];
        $updateParams = [];
        
        $statsMap = [
            'app_version' => 'app_version',
            'php_version' => 'php_version',
            'os_info' => 'os_info',
            'user_count' => 'user_count',
            'customer_count' => 'customer_count',
            'onu_count' => 'onu_count',
            'ticket_count' => 'ticket_count',
            'disk_usage' => 'disk_usage',
            'db_size' => 'db_size',
            'server_ip' => 'server_ip'
        ];
        
        foreach ($statsMap as $inputKey => $dbColumn) {
            if (isset($clientStats[$inputKey])) {
                $updateFields[] = "$dbColumn = ?";
                $updateParams[] = $clientStats[$inputKey];
            }
        }
        
        $updateParams[] = $activation['id'];
        $sql = "UPDATE license_activations SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($updateParams);
        
        if (!empty($clientStats) && $this->shouldRecordStats($activation['id'])) {
            $this->recordServerStats($activation['id'], $clientStats);
        }
        
        $updateInfo = $this->getAvailableUpdate(
            $clientStats['app_version'] ?? $activation['app_version'] ?? '0.0.0',
            $activation['license_id']
        );
        
        $result = [
            'valid' => true,
            'license' => [
                'tier_code' => $activation['tier_code'],
                'tier_name' => $activation['tier_name'],
                'expires_at' => $activation['expires_at'],
                'max_users' => (int)$activation['max_users'],
                'max_customers' => (int)$activation['max_customers'],
                'max_onus' => (int)$activation['max_onus'],
                'max_olts' => (int)$activation['max_olts'],
                'max_subscribers' => (int)$activation['max_subscribers'],
                'features' => json_decode($activation['features'] ?: '{}', true)
            ]
        ];
        
        if ($updateInfo) {
            $result['update_available'] = $updateInfo;
        }
        
        return $result;
    }
    
    public function deactivate(string $activationToken, string $reason = 'Manual deactivation'): bool {
        $stmt = $this->db->prepare("
            UPDATE license_activations 
            SET is_active = FALSE, deactivated_at = NOW(), deactivation_reason = ?
            WHERE activation_token = ?
        ");
        return $stmt->execute([$reason, $activationToken]);
    }
    
    private function getActivationCount(int $licenseId): int {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM license_activations WHERE license_id = ? AND is_active = TRUE");
        $stmt->execute([$licenseId]);
        return (int)$stmt->fetchColumn();
    }
    
    private function findExistingActivation(int $licenseId, array $clientInfo): ?array {
        $stmt = $this->db->prepare("
            SELECT * FROM license_activations 
            WHERE license_id = ? AND is_active = TRUE
            AND (domain = ? OR hardware_id = ? OR server_ip = ?)
        ");
        $stmt->execute([
            $licenseId,
            $clientInfo['domain'] ?? '',
            $clientInfo['hardware_id'] ?? '',
            $clientInfo['server_ip'] ?? ''
        ]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    
    private function logValidation(?int $licenseId, ?int $activationId, string $action, array $requestData, string $status, string $message): void {
        $stmt = $this->db->prepare("
            INSERT INTO license_validation_logs (license_id, activation_id, action, ip_address, user_agent, request_data, response_status, response_message)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $licenseId,
            $activationId,
            $action,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            json_encode($requestData),
            $status,
            $message
        ]);
    }
    
    public function getLicense(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT l.*, c.name as customer_name, c.email as customer_email,
                   p.name as product_name, t.name as tier_name,
                   (SELECT COUNT(*) FROM license_activations WHERE license_id = l.id AND is_active = TRUE) as active_activations
            FROM licenses l
            LEFT JOIN license_customers c ON l.customer_id = c.id
            LEFT JOIN license_products p ON l.product_id = p.id
            LEFT JOIN license_tiers t ON l.tier_id = t.id
            WHERE l.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    
    public function getAllLicenses(): array {
        $stmt = $this->db->query("
            SELECT l.*, c.name as customer_name, c.email as customer_email, c.company,
                   p.name as product_name, t.name as tier_name,
                   (SELECT COUNT(*) FROM license_activations WHERE license_id = l.id AND is_active = TRUE) as active_activations
            FROM licenses l
            LEFT JOIN license_customers c ON l.customer_id = c.id
            LEFT JOIN license_products p ON l.product_id = p.id
            LEFT JOIN license_tiers t ON l.tier_id = t.id
            ORDER BY l.created_at DESC
        ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function suspendLicense(int $id, string $reason): bool {
        $stmt = $this->db->prepare("UPDATE licenses SET is_suspended = TRUE, suspension_reason = ?, updated_at = NOW() WHERE id = ?");
        return $stmt->execute([$reason, $id]);
    }
    
    public function unsuspendLicense(int $id): bool {
        $stmt = $this->db->prepare("UPDATE licenses SET is_suspended = FALSE, suspension_reason = NULL, updated_at = NOW() WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    public function extendLicense(int $id, int $months): bool {
        $stmt = $this->db->prepare("SELECT expires_at FROM licenses WHERE id = ?");
        $stmt->execute([$id]);
        $license = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$license) return false;

        $baseDate = $license['expires_at'] && strtotime($license['expires_at']) > time()
            ? $license['expires_at']
            : date('Y-m-d H:i:s');
        $newExpiry = date('Y-m-d H:i:s', strtotime($baseDate . " +{$months} months"));

        $stmt = $this->db->prepare("UPDATE licenses SET expires_at = ?, is_active = TRUE, updated_at = NOW() WHERE id = ?");
        return $stmt->execute([$newExpiry, $id]);
    }
    
    public function getStats(): array {
        $stats = [];
        
        $stmt = $this->db->query("SELECT COUNT(*) FROM licenses WHERE is_active = TRUE");
        $stats['total_licenses'] = (int)$stmt->fetchColumn();
        
        $stmt = $this->db->query("SELECT COUNT(*) FROM licenses WHERE is_active = TRUE AND is_suspended = FALSE AND (expires_at IS NULL OR expires_at > NOW())");
        $stats['active_licenses'] = (int)$stmt->fetchColumn();
        
        $stmt = $this->db->query("SELECT COUNT(*) FROM licenses WHERE expires_at IS NOT NULL AND expires_at < NOW()");
        $stats['expired_licenses'] = (int)$stmt->fetchColumn();
        
        $stmt = $this->db->query("SELECT COUNT(*) FROM license_activations WHERE is_active = TRUE");
        $stats['total_activations'] = (int)$stmt->fetchColumn();
        
        $stmt = $this->db->query("SELECT COUNT(*) FROM license_activations WHERE is_active = TRUE AND last_seen_at > NOW() - INTERVAL '24 hours'");
        $stats['active_today'] = (int)$stmt->fetchColumn();
        
        $stmt = $this->db->query("SELECT COUNT(*) FROM license_activations WHERE is_active = TRUE AND last_seen_at > NOW() - INTERVAL '5 minutes'");
        $stats['online_now'] = (int)$stmt->fetchColumn();
        
        $stmt = $this->db->query("SELECT COUNT(*) FROM license_customers");
        $stats['total_customers'] = (int)$stmt->fetchColumn();
        
        return $stats;
    }

    public function getAllServers(): array {
        $stmt = $this->db->query("
            SELECT a.*, l.license_key, l.is_active as license_active, l.is_suspended, l.expires_at,
                   c.name as customer_name, c.company, c.email as customer_email, c.phone as customer_phone,
                   t.name as tier_name, t.code as tier_code, t.max_users, t.max_customers, t.max_onus, t.max_olts, t.max_subscribers
            FROM license_activations a
            JOIN licenses l ON a.license_id = l.id
            LEFT JOIN license_customers c ON l.customer_id = c.id
            LEFT JOIN license_tiers t ON l.tier_id = t.id
            WHERE a.is_active = TRUE
            ORDER BY a.last_seen_at DESC
        ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getServerDetail(int $activationId): ?array {
        $stmt = $this->db->prepare("
            SELECT a.*, l.license_key, l.is_active as license_active, l.is_suspended, l.expires_at, l.notes as license_notes,
                   c.name as customer_name, c.company, c.email as customer_email, c.phone as customer_phone,
                   t.name as tier_name, t.code as tier_code, t.max_users, t.max_customers, t.max_onus, t.max_olts, t.max_subscribers, t.features as tier_features,
                   p.name as product_name, p.current_version as latest_version
            FROM license_activations a
            JOIN licenses l ON a.license_id = l.id
            LEFT JOIN license_customers c ON l.customer_id = c.id
            LEFT JOIN license_tiers t ON l.tier_id = t.id
            LEFT JOIN license_products p ON l.product_id = p.id
            WHERE a.id = ?
        ");
        $stmt->execute([$activationId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function getServerStatsHistory(int $activationId, int $days = 30): array {
        $stmt = $this->db->prepare("
            SELECT * FROM license_server_stats_history 
            WHERE activation_id = ? AND recorded_at > NOW() - INTERVAL '{$days} days'
            ORDER BY recorded_at ASC
        ");
        $stmt->execute([$activationId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getServerValidationLogs(int $activationId, int $limit = 50): array {
        $stmt = $this->db->prepare("
            SELECT vl.* FROM license_validation_logs vl
            JOIN license_activations a ON vl.license_id = a.license_id
            WHERE a.id = ?
            ORDER BY vl.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$activationId, $limit]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function shouldRecordStats(int $activationId): bool {
        $stmt = $this->db->prepare("
            SELECT recorded_at FROM license_server_stats_history 
            WHERE activation_id = ? 
            ORDER BY recorded_at DESC LIMIT 1
        ");
        $stmt->execute([$activationId]);
        $last = $stmt->fetchColumn();
        if (!$last) return true;
        return (time() - strtotime($last)) > 3600;
    }

    private function recordServerStats(int $activationId, array $stats): void {
        $stmt = $this->db->prepare("
            INSERT INTO license_server_stats_history (activation_id, user_count, customer_count, onu_count, ticket_count, disk_usage, db_size, app_version)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $activationId,
            $stats['user_count'] ?? 0,
            $stats['customer_count'] ?? 0,
            $stats['onu_count'] ?? 0,
            $stats['ticket_count'] ?? 0,
            $stats['disk_usage'] ?? null,
            $stats['db_size'] ?? null,
            $stats['app_version'] ?? null
        ]);
    }

    public function createUpdate(array $data): ?array {
        $stmt = $this->db->prepare("
            INSERT INTO license_updates (product_id, version, title, changelog, release_type, min_php_version, min_node_version, download_url, download_hash, file_size, is_critical, is_published, published_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            RETURNING *
        ");
        $isPublished = !empty($data['is_published']);
        $stmt->execute([
            $data['product_id'],
            $data['version'],
            $data['title'],
            $data['changelog'] ?? null,
            $data['release_type'] ?? 'patch',
            $data['min_php_version'] ?? null,
            $data['min_node_version'] ?? null,
            $data['download_url'] ?? null,
            $data['download_hash'] ?? null,
            $data['file_size'] ?? null,
            !empty($data['is_critical']) ? 1 : 0,
            $isPublished ? 1 : 0,
            $isPublished ? date('Y-m-d H:i:s') : null
        ]);
        
        if ($isPublished) {
            $this->db->prepare("UPDATE license_products SET current_version = ? WHERE id = ?")->execute([$data['version'], $data['product_id']]);
        }
        
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function publishUpdate(int $id): bool {
        $stmt = $this->db->prepare("SELECT * FROM license_updates WHERE id = ?");
        $stmt->execute([$id]);
        $update = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$update) return false;

        $this->db->prepare("UPDATE license_updates SET is_published = TRUE, published_at = NOW(), updated_at = NOW() WHERE id = ?")->execute([$id]);
        $this->db->prepare("UPDATE license_products SET current_version = ? WHERE id = ?")->execute([$update['version'], $update['product_id']]);
        return true;
    }

    public function unpublishUpdate(int $id): bool {
        $this->db->prepare("UPDATE license_updates SET is_published = FALSE, published_at = NULL, updated_at = NOW() WHERE id = ?")->execute([$id]);
        return true;
    }

    public function deleteUpdate(int $id): bool {
        $this->db->prepare("DELETE FROM license_update_logs WHERE update_id = ?")->execute([$id]);
        $this->db->prepare("DELETE FROM license_updates WHERE id = ?")->execute([$id]);
        return true;
    }

    public function getAllUpdates(): array {
        $stmt = $this->db->query("
            SELECT u.*, p.name as product_name,
                   (SELECT COUNT(*) FROM license_update_logs WHERE update_id = u.id AND status = 'completed') as install_count
            FROM license_updates u
            LEFT JOIN license_products p ON u.product_id = p.id
            ORDER BY u.created_at DESC
        ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getAvailableUpdate(string $currentVersion, int $licenseId): ?array {
        $stmt = $this->db->prepare("SELECT product_id FROM licenses WHERE id = ?");
        $stmt->execute([$licenseId]);
        $productId = $stmt->fetchColumn();
        if (!$productId) return null;

        $stmt = $this->db->prepare("
            SELECT id, version, title, changelog, release_type, min_php_version, min_node_version,
                   download_url, is_critical, published_at, file_size
            FROM license_updates
            WHERE product_id = ? AND is_published = TRUE
            ORDER BY published_at DESC
            LIMIT 1
        ");
        $stmt->execute([$productId]);
        $latest = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$latest) return null;
        
        if (version_compare($latest['version'], $currentVersion, '>')) {
            return [
                'version' => $latest['version'],
                'title' => $latest['title'],
                'changelog' => $latest['changelog'],
                'release_type' => $latest['release_type'],
                'is_critical' => (bool)$latest['is_critical'],
                'min_php_version' => $latest['min_php_version'],
                'min_node_version' => $latest['min_node_version'],
                'download_url' => $latest['download_url'],
                'published_at' => $latest['published_at'],
                'file_size' => $latest['file_size']
            ];
        }
        
        return null;
    }

    public function logUpdateInstall(int $activationId, int $updateId, string $fromVersion, string $toVersion, string $status, ?string $error = null): void {
        $stmt = $this->db->prepare("
            INSERT INTO license_update_logs (activation_id, update_id, from_version, to_version, status, started_at, completed_at, error_message)
            VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)
        ");
        $stmt->execute([
            $activationId,
            $updateId,
            $fromVersion,
            $toVersion,
            $status,
            $status !== 'pending' ? date('Y-m-d H:i:s') : null,
            $error
        ]);
    }

    public function getUpdateLogs(int $updateId): array {
        $stmt = $this->db->prepare("
            SELECT ul.*, a.domain, a.server_hostname, a.server_ip,
                   c.name as customer_name
            FROM license_update_logs ul
            JOIN license_activations a ON ul.activation_id = a.id
            JOIN licenses l ON a.license_id = l.id
            LEFT JOIN license_customers c ON l.customer_id = c.id
            WHERE ul.update_id = ?
            ORDER BY ul.created_at DESC
        ");
        $stmt->execute([$updateId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function editLicense(int $id, array $data): bool {
        $fields = [];
        $params = [];

        if (isset($data['tier_id'])) {
            $fields[] = 'tier_id = ?';
            $params[] = $data['tier_id'];
        }
        if (isset($data['max_activations'])) {
            $fields[] = 'max_activations = ?';
            $params[] = (int)$data['max_activations'];
        }
        if (isset($data['domain_restriction'])) {
            $fields[] = 'domain_restriction = ?';
            $params[] = $data['domain_restriction'] ?: null;
        }
        if (isset($data['notes'])) {
            $fields[] = 'notes = ?';
            $params[] = $data['notes'];
        }
        if (isset($data['expires_at'])) {
            $fields[] = 'expires_at = ?';
            $params[] = $data['expires_at'] ?: null;
        }
        if (isset($data['is_active'])) {
            $fields[] = 'is_active = ?';
            $params[] = $data['is_active'] ? 1 : 0;
        }

        if (empty($fields)) return false;

        $fields[] = 'updated_at = NOW()';
        $params[] = $id;
        $sql = "UPDATE licenses SET " . implode(', ', $fields) . " WHERE id = ?";
        return $this->db->prepare($sql)->execute($params);
    }

    public function editCustomer(int $id, array $data): bool {
        $stmt = $this->db->prepare("
            UPDATE license_customers SET name = ?, email = ?, company = ?, phone = ?, notes = ?, updated_at = NOW()
            WHERE id = ?
        ");
        return $stmt->execute([
            $data['name'],
            $data['email'],
            $data['company'] ?? null,
            $data['phone'] ?? null,
            $data['notes'] ?? null,
            $id
        ]);
    }

    public function deleteCustomer(int $id): bool {
        $check = $this->db->prepare("SELECT COUNT(*) FROM licenses WHERE customer_id = ?");
        $check->execute([$id]);
        if ($check->fetchColumn() > 0) return false;
        
        $stmt = $this->db->prepare("DELETE FROM license_customers WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function deactivateServer(int $activationId, string $reason): bool {
        $stmt = $this->db->prepare("
            UPDATE license_activations 
            SET is_active = FALSE, deactivated_at = NOW(), deactivation_reason = ?
            WHERE id = ?
        ");
        return $stmt->execute([$reason, $activationId]);
    }

    public function ensureSchema(): void {
        $migrations = [
            "ALTER TABLE license_tiers ADD COLUMN IF NOT EXISTS max_olts INTEGER DEFAULT 0",
            "ALTER TABLE license_tiers ADD COLUMN IF NOT EXISTS max_subscribers INTEGER DEFAULT 0",
            "ALTER TABLE license_activations ADD COLUMN IF NOT EXISTS app_version VARCHAR(20)",
            "ALTER TABLE license_activations ADD COLUMN IF NOT EXISTS user_count INTEGER DEFAULT 0",
            "ALTER TABLE license_activations ADD COLUMN IF NOT EXISTS customer_count INTEGER DEFAULT 0",
            "ALTER TABLE license_activations ADD COLUMN IF NOT EXISTS onu_count INTEGER DEFAULT 0",
            "ALTER TABLE license_activations ADD COLUMN IF NOT EXISTS ticket_count INTEGER DEFAULT 0",
            "ALTER TABLE license_activations ADD COLUMN IF NOT EXISTS disk_usage VARCHAR(50)",
            "ALTER TABLE license_activations ADD COLUMN IF NOT EXISTS db_size VARCHAR(50)",
            "ALTER TABLE license_products ADD COLUMN IF NOT EXISTS current_version VARCHAR(20) DEFAULT '1.0.0'",
            "CREATE TABLE IF NOT EXISTS license_updates (
                id SERIAL PRIMARY KEY,
                product_id INTEGER REFERENCES license_products(id),
                version VARCHAR(20) NOT NULL,
                title VARCHAR(255) NOT NULL,
                changelog TEXT,
                release_type VARCHAR(20) DEFAULT 'patch',
                min_php_version VARCHAR(10),
                min_node_version VARCHAR(10),
                download_url TEXT,
                download_hash VARCHAR(64),
                file_size BIGINT,
                is_critical BOOLEAN DEFAULT FALSE,
                is_published BOOLEAN DEFAULT FALSE,
                published_at TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(product_id, version)
            )",
            "CREATE TABLE IF NOT EXISTS license_update_logs (
                id SERIAL PRIMARY KEY,
                activation_id INTEGER REFERENCES license_activations(id),
                update_id INTEGER REFERENCES license_updates(id),
                from_version VARCHAR(20),
                to_version VARCHAR(20),
                status VARCHAR(20) DEFAULT 'pending',
                started_at TIMESTAMP,
                completed_at TIMESTAMP,
                error_message TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS license_server_stats_history (
                id SERIAL PRIMARY KEY,
                activation_id INTEGER REFERENCES license_activations(id),
                user_count INTEGER DEFAULT 0,
                customer_count INTEGER DEFAULT 0,
                onu_count INTEGER DEFAULT 0,
                ticket_count INTEGER DEFAULT 0,
                disk_usage VARCHAR(50),
                db_size VARCHAR(50),
                app_version VARCHAR(20),
                recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )"
        ];

        foreach ($migrations as $sql) {
            try {
                $this->db->exec($sql);
            } catch (\PDOException $e) {
            }
        }
    }
}
