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
                   p.code as product_code, p.name as product_name, p.features as product_features,
                   t.code as tier_code, t.name as tier_name, t.max_users, t.max_customers, t.max_onus, t.features as tier_features
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
                'features' => json_decode($license['tier_features'] ?: '{}', true)
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
            $stmt = $this->db->prepare("UPDATE license_activations SET last_seen_at = NOW(), last_validated_at = NOW() WHERE id = ?");
            $stmt->execute([$existing['id']]);
            $activationToken = $existing['activation_token'];
        } else {
            $activationToken = $this->generateActivationToken();
            $stmt = $this->db->prepare("
                INSERT INTO license_activations (license_id, activation_token, domain, server_ip, server_hostname, hardware_id, php_version, os_info)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $licenseId,
                $activationToken,
                $clientInfo['domain'] ?? null,
                $clientInfo['server_ip'] ?? null,
                $clientInfo['hostname'] ?? null,
                $clientInfo['hardware_id'] ?? null,
                $clientInfo['php_version'] ?? null,
                $clientInfo['os_info'] ?? null
            ]);
        }
        
        $this->logValidation($licenseId, null, 'activation', $clientInfo, 'success', 'License activated');
        
        return [
            'valid' => true,
            'activated' => true,
            'activation_token' => $activationToken,
            'license' => $validation['license']
        ];
    }
    
    public function heartbeat(string $activationToken): array {
        $stmt = $this->db->prepare("
            SELECT a.*, l.license_key, l.is_active, l.is_suspended, l.expires_at,
                   t.code as tier_code, t.max_users, t.max_customers, t.max_onus, t.features
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
        
        $stmt = $this->db->prepare("UPDATE license_activations SET last_seen_at = NOW() WHERE id = ?");
        $stmt->execute([$activation['id']]);
        
        return [
            'valid' => true,
            'license' => [
                'tier_code' => $activation['tier_code'],
                'expires_at' => $activation['expires_at'],
                'max_users' => (int)$activation['max_users'],
                'max_customers' => (int)$activation['max_customers'],
                'max_onus' => (int)$activation['max_onus'],
                'features' => json_decode($activation['features'] ?: '{}', true)
            ]
        ];
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
        
        $stmt = $this->db->query("SELECT COUNT(*) FROM license_customers");
        $stats['total_customers'] = (int)$stmt->fetchColumn();
        
        return $stats;
    }
}
