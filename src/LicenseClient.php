<?php

class LicenseClient {
    private $config;
    private $cacheFile;
    private $activationToken = null;
    
    const APP_VERSION = '1.0.0';
    
    public function __construct() {
        $this->config = require __DIR__ . '/../config/license.php';
        $this->cacheFile = $this->config['cache_file'];
        
        if (!is_dir(dirname($this->cacheFile))) {
            @mkdir(dirname($this->cacheFile), 0755, true);
        }
    }
    
    public function isEnabled(): bool {
        return $this->config['enabled'] && !empty($this->config['server_url']);
    }
    
    public function validate(): array {
        if (!$this->isEnabled()) {
            return ['valid' => false, 'mode' => 'unconfigured', 'error' => 'license_required', 'message' => 'License not configured. Please enter your license server URL and key in Settings.'];
        }
        
        $cached = $this->getCachedLicense();
        if ($cached && $this->isCacheValid($cached)) {
            return $cached;
        }
        
        try {
            $token = $this->getActivationToken();
            
            if ($token) {
                $stats = $this->collectServerStats();
                $result = $this->callServer('heartbeat', array_merge(
                    ['activation_token' => $token],
                    $stats
                ));
                
                if ($result['valid']) {
                    $this->cacheLicense($result);
                    return $result;
                }
            }
            
            if (!empty($this->config['license_key'])) {
                $result = $this->activate();
                if ($result['valid']) {
                    return $result;
                }
            }
            
            return ['valid' => false, 'error' => 'not_activated', 'message' => 'License not activated'];
        } catch (Exception $e) {
            if ($cached && $this->isInGracePeriod($cached)) {
                $cached['grace_mode'] = true;
                return $cached;
            }
            
            return ['valid' => false, 'error' => 'connection_failed', 'message' => 'Cannot connect to license server'];
        }
    }
    
    public function activate(): array {
        if (!$this->isEnabled()) {
            return ['valid' => true, 'mode' => 'unlicensed'];
        }
        
        $clientInfo = $this->getClientInfo();
        
        $result = $this->callServer('activate', [
            'license_key' => $this->config['license_key'],
            'domain' => $clientInfo['domain'],
            'server_ip' => $clientInfo['server_ip'],
            'hostname' => $clientInfo['hostname'],
            'hardware_id' => $clientInfo['hardware_id'],
            'php_version' => $clientInfo['php_version'],
            'os_info' => $clientInfo['os_info'],
            'app_version' => self::APP_VERSION
        ]);
        
        if ($result['valid'] && !empty($result['activation_token'])) {
            $this->saveActivationToken($result['activation_token']);
            $this->cacheLicense($result);
        }
        
        return $result;
    }
    
    public function deactivate(): bool {
        $token = $this->getActivationToken();
        if (!$token) return true;
        
        try {
            $this->callServer('deactivate', ['activation_token' => $token]);
            $this->clearActivationToken();
            $this->clearCache();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    public function getFeatures(): array {
        $license = $this->validate();
        if (!$license['valid']) {
            return [];
        }
        return $license['license']['features'] ?? $this->config['features'];
    }
    
    public function hasFeature(string $feature): bool {
        $features = $this->getFeatures();
        return !empty($features[$feature]);
    }
    
    public function getLimits(): array {
        $license = $this->validate();
        if (!$license['valid']) {
            return ['max_users' => 0, 'max_customers' => 0, 'max_onus' => 0, 'max_olts' => 0, 'max_subscribers' => 0];
        }
        return [
            'max_users' => $license['license']['max_users'] ?? 0,
            'max_customers' => $license['license']['max_customers'] ?? 0,
            'max_onus' => $license['license']['max_onus'] ?? 0,
            'max_olts' => $license['license']['max_olts'] ?? 0,
            'max_subscribers' => $license['license']['max_subscribers'] ?? 0
        ];
    }
    
    public function getLicenseInfo(): ?array {
        $license = $this->validate();
        return $license['valid'] ? $license['license'] ?? null : null;
    }

    public function checkForUpdates(): ?array {
        if (!$this->isEnabled()) return null;

        $token = $this->getActivationToken();
        if (!$token) return null;

        try {
            $result = $this->callServer('check-update', [
                'activation_token' => $token,
                'app_version' => self::APP_VERSION
            ]);
            
            if (!empty($result['update_available']) && !empty($result['update'])) {
                return $result['update'];
            }
            return null;
        } catch (Exception $e) {
            return null;
        }
    }

    public function reportUpdateResult(int $updateId, string $fromVersion, string $toVersion, string $status, ?string $error = null): bool {
        $token = $this->getActivationToken();
        if (!$token) return false;

        try {
            $this->callServer('report-update', [
                'activation_token' => $token,
                'update_id' => $updateId,
                'from_version' => $fromVersion,
                'to_version' => $toVersion,
                'status' => $status,
                'error' => $error
            ]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function getSubscriptionInfo(): ?array {
        if (!$this->isEnabled()) return null;
        try {
            return $this->callServer('subscription-info', [
                'license_key' => $this->config['license_key']
            ]);
        } catch (Exception $e) {
            return null;
        }
    }

    public function initiatePayment(string $phone, string $billingCycle = 'monthly'): array {
        if (!$this->isEnabled()) {
            return ['success' => false, 'error' => 'License not configured'];
        }
        try {
            return $this->callServer('pay/initiate', [
                'license_key' => $this->config['license_key'],
                'phone' => $phone,
                'billing_cycle' => $billingCycle
            ]);
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function checkPaymentStatus(string $checkoutRequestId): array {
        if (!$this->isEnabled()) {
            return ['status' => 'error', 'error' => 'License not configured'];
        }
        try {
            return $this->callServer('pay/status', [
                'checkout_request_id' => $checkoutRequestId
            ]);
        } catch (Exception $e) {
            return ['status' => 'error', 'error' => $e->getMessage()];
        }
    }

    public function getUpdateFromCache(): ?array {
        $cached = $this->getCachedLicense();
        return $cached['update_available'] ?? null;
    }

    public function getAppVersion(): string {
        return self::APP_VERSION;
    }
    
    private function collectServerStats(): array {
        $stats = [
            'app_version' => self::APP_VERSION,
            'php_version' => PHP_VERSION,
            'os_info' => php_uname(),
            'server_ip' => $_SERVER['SERVER_ADDR'] ?? gethostbyname(gethostname())
        ];

        try {
            if (class_exists('Database', false)) {
                $db = \Database::getConnection();
                
                $stmt = $db->query("SELECT COUNT(*) FROM users");
                $stats['user_count'] = (int)$stmt->fetchColumn();
                
                $stmt = $db->query("SELECT COUNT(*) FROM customers");
                $stats['customer_count'] = (int)$stmt->fetchColumn();

                try {
                    $stmt = $db->query("SELECT COUNT(*) FROM discovered_onus");
                    $stats['onu_count'] = (int)$stmt->fetchColumn();
                } catch (\Throwable $e) {
                    $stats['onu_count'] = 0;
                }

                try {
                    $stmt = $db->query("SELECT COUNT(*) FROM tickets");
                    $stats['ticket_count'] = (int)$stmt->fetchColumn();
                } catch (\Throwable $e) {
                    $stats['ticket_count'] = 0;
                }

                try {
                    $stmt = $db->query("SELECT pg_size_pretty(pg_database_size(current_database()))");
                    $stats['db_size'] = $stmt->fetchColumn();
                } catch (\Throwable $e) {
                }
            }
        } catch (\Throwable $e) {
        }

        $diskTotal = @disk_total_space('/');
        $diskFree = @disk_free_space('/');
        if ($diskTotal && $diskFree) {
            $used = $diskTotal - $diskFree;
            $stats['disk_usage'] = round($used / 1073741824, 1) . 'GB / ' . round($diskTotal / 1073741824, 1) . 'GB';
        }

        return $stats;
    }
    
    private function callServer(string $endpoint, array $data): array {
        $url = rtrim($this->config['server_url'], '/') . '/api/' . $endpoint;
        
        $parsedUrl = parse_url($this->config['server_url']);
        $host = $parsedUrl['host'] ?? '';
        $isIpAddress = filter_var($host, FILTER_VALIDATE_IP) !== false;
        $isHttps = ($parsedUrl['scheme'] ?? '') === 'https';
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => $isHttps && !$isIpAddress,
            CURLOPT_SSL_VERIFYHOST => $isHttps && !$isIpAddress ? 2 : 0
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("Connection failed: $error");
        }
        
        $result = json_decode($response, true);
        if (!$result) {
            throw new Exception("Invalid response from license server");
        }
        
        return $result;
    }
    
    private function getClientInfo(): array {
        return [
            'domain' => $_SERVER['HTTP_HOST'] ?? gethostname(),
            'server_ip' => $_SERVER['SERVER_ADDR'] ?? gethostbyname(gethostname()),
            'hostname' => gethostname(),
            'hardware_id' => $this->generateHardwareId(),
            'php_version' => PHP_VERSION,
            'os_info' => php_uname()
        ];
    }
    
    private function generateHardwareId(): string {
        $factors = [
            gethostname(),
            php_uname('n'),
            __DIR__
        ];
        return hash('sha256', implode('|', $factors));
    }
    
    private function getActivationToken(): ?string {
        if ($this->activationToken) return $this->activationToken;
        
        $tokenFile = dirname($this->cacheFile) . '/activation_token';
        if (file_exists($tokenFile)) {
            $this->activationToken = trim(file_get_contents($tokenFile));
            return $this->activationToken;
        }
        return null;
    }
    
    private function saveActivationToken(string $token): void {
        $this->activationToken = $token;
        $tokenFile = dirname($this->cacheFile) . '/activation_token';
        file_put_contents($tokenFile, $token);
    }
    
    private function clearActivationToken(): void {
        $this->activationToken = null;
        $tokenFile = dirname($this->cacheFile) . '/activation_token';
        @unlink($tokenFile);
    }
    
    private function getCachedLicense(): ?array {
        if (!file_exists($this->cacheFile)) return null;
        $data = json_decode(file_get_contents($this->cacheFile), true);
        return $data ?: null;
    }
    
    private function cacheLicense(array $license): void {
        $license['cached_at'] = time();
        $license['last_validated'] = time();
        file_put_contents($this->cacheFile, json_encode($license));
    }
    
    private function clearCache(): void {
        @unlink($this->cacheFile);
    }
    
    private function isCacheValid(array $cached): bool {
        if (empty($cached['last_validated'])) return false;
        $interval = $this->config['check_interval_hours'] * 3600;
        return (time() - $cached['last_validated']) < $interval;
    }
    
    private function isInGracePeriod(array $cached): bool {
        if (empty($cached['last_validated'])) return false;
        $gracePeriod = $this->config['grace_period_days'] * 86400;
        return (time() - $cached['last_validated']) < $gracePeriod;
    }
}
