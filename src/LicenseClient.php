<?php

class LicenseClient {
    private $config;
    private $cacheFile;
    private $activationToken = null;
    
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
            return ['valid' => true, 'mode' => 'unlicensed', 'features' => $this->config['features']];
        }
        
        $cached = $this->getCachedLicense();
        if ($cached && $this->isCacheValid($cached)) {
            return $cached;
        }
        
        try {
            $token = $this->getActivationToken();
            
            if ($token) {
                $result = $this->callServer('heartbeat', [
                    'activation_token' => $token
                ]);
                
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
            'os_info' => $clientInfo['os_info']
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
            return ['max_users' => 0, 'max_customers' => 0, 'max_onus' => 0];
        }
        return [
            'max_users' => $license['license']['max_users'] ?? 0,
            'max_customers' => $license['license']['max_customers'] ?? 0,
            'max_onus' => $license['license']['max_onus'] ?? 0
        ];
    }
    
    public function getLicenseInfo(): ?array {
        $license = $this->validate();
        return $license['valid'] ? $license['license'] ?? null : null;
    }
    
    private function callServer(string $endpoint, array $data): array {
        $url = rtrim($this->config['server_url'], '/') . '/api/' . $endpoint;
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true
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
