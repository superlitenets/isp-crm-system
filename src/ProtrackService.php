<?php

namespace App;

class ProtrackService {
    private \PDO $db;
    private string $apiBase = 'https://api.protrack365.com';
    private ?string $account = null;
    private ?string $password = null;
    private ?string $accessToken = null;
    private ?int $tokenExpiry = null;
    private ?string $lastError = null;
    
    public function __construct(?\PDO $db = null) {
        $this->db = $db ?? \Database::getConnection();
        $this->loadCredentials();
    }
    
    private function loadCredentials(): void {
        $stmt = $this->db->prepare("SELECT setting_key, setting_value FROM company_settings WHERE setting_key IN ('protrack_account', 'protrack_password', 'protrack_api_base', 'protrack_access_token', 'protrack_token_expiry')");
        $stmt->execute();
        $settings = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
        
        $this->account = $settings['protrack_account'] ?? null;
        $this->password = $settings['protrack_password'] ?? null;
        if (!empty($settings['protrack_api_base'])) {
            $this->apiBase = rtrim($settings['protrack_api_base'], '/');
        }
        $this->accessToken = $settings['protrack_access_token'] ?? null;
        $this->tokenExpiry = isset($settings['protrack_token_expiry']) ? (int)$settings['protrack_token_expiry'] : null;
    }
    
    public function isConfigured(): bool {
        return !empty($this->account) && !empty($this->password);
    }
    
    public function getLastError(): ?string {
        return $this->lastError;
    }
    
    private function getAccessToken(): ?string {
        if ($this->accessToken && $this->tokenExpiry && time() < ($this->tokenExpiry - 300)) {
            return $this->accessToken;
        }
        
        if (!$this->isConfigured()) {
            $this->lastError = 'Not configured - missing account or password';
            error_log("[ProtrackService] " . $this->lastError);
            return null;
        }
        
        $time = time();
        $signature = md5(md5($this->password) . $time);
        
        $url = $this->apiBase . '/api/authorization?' . http_build_query([
            'time' => $time,
            'account' => $this->account,
            'signature' => $signature
        ]);
        
        $response = $this->httpGet($url);
        if (!$response) {
            $this->lastError = 'No response from Protrack API during authentication (URL: ' . $this->apiBase . ')';
            error_log("[ProtrackService] " . $this->lastError);
            return null;
        }
        if (($response['code'] ?? -1) !== 0) {
            $this->lastError = 'Auth failed: ' . ($response['message'] ?? 'Unknown') . ' (code: ' . ($response['code'] ?? 'null') . ')';
            error_log("[ProtrackService] " . $this->lastError);
            return null;
        }
        
        $this->accessToken = $response['record']['access_token'] ?? null;
        $expiresIn = $response['record']['expires_in'] ?? 7200;
        $this->tokenExpiry = time() + $expiresIn;
        
        $this->saveSetting('protrack_access_token', $this->accessToken);
        $this->saveSetting('protrack_token_expiry', (string)$this->tokenExpiry);
        
        return $this->accessToken;
    }
    
    private function saveSetting(string $key, string $value): void {
        $stmt = $this->db->prepare("INSERT INTO company_settings (setting_key, setting_value) VALUES (?, ?) ON CONFLICT (setting_key) DO UPDATE SET setting_value = EXCLUDED.setting_value");
        $stmt->execute([$key, $value]);
    }
    
    private function httpGet(string $url): ?array {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => ['Accept: application/json; charset=UTF-8'],
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            error_log("[ProtrackService] HTTP GET error: $error");
            return null;
        }
        
        return json_decode($result, true);
    }
    
    private function httpPost(string $url, array $params = []): ?array {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_HTTPHEADER => ['Accept: application/json; charset=UTF-8'],
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        $result = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            error_log("[ProtrackService] HTTP POST error: $error");
            return null;
        }
        
        return json_decode($result, true);
    }
    
    private function apiGet(string $endpoint, array $params = []): ?array {
        $token = $this->getAccessToken();
        if (!$token) return null;
        
        $params['access_token'] = $token;
        $url = $this->apiBase . $endpoint . '?' . http_build_query($params);
        
        return $this->httpGet($url);
    }
    
    private function apiPost(string $endpoint, array $params = []): ?array {
        $token = $this->getAccessToken();
        if (!$token) return null;
        
        $params['access_token'] = $token;
        $url = $this->apiBase . $endpoint . '?' . http_build_query($params);
        
        return $this->httpPost($url);
    }
    
    public function getTrack(array $imeis): ?array {
        if (empty($imeis)) return null;
        return $this->apiGet('/api/track', [
            'imeis' => implode(',', array_slice($imeis, 0, 100))
        ]);
    }
    
    public function getPlayback(string $imei, int $beginTime, int $endTime): ?array {
        return $this->apiGet('/api/playback', [
            'imei' => $imei,
            'begintime' => $beginTime,
            'endtime' => $endTime
        ]);
    }
    
    public function getDeviceList(): ?array {
        return $this->apiGet('/api/device/list');
    }
    
    public function sendCommand(string $imei, string $command, ?string $paramData = null): ?array {
        $params = [
            'imei' => $imei,
            'command' => $command
        ];
        if ($paramData) {
            $params['paramData'] = $paramData;
        }
        return $this->apiPost('/api/command/send', $params);
    }
    
    public function queryCommand(string $commandId): ?array {
        return $this->apiPost('/api/command/query', [
            'commandid' => $commandId
        ]);
    }
    
    public function getAlarms(string $imei, int $beginTime, int $endTime): ?array {
        return $this->apiGet('/api/alarm', [
            'imei' => $imei,
            'begintime' => $beginTime,
            'endtime' => $endTime
        ]);
    }
    
    public function getMileageReport(string $imei, int $beginTime, int $endTime): ?array {
        return $this->apiGet('/api/report/mileage', [
            'imei' => $imei,
            'begintime' => $beginTime,
            'endtime' => $endTime
        ]);
    }

    public function getBatchMileage(array $imeis, int $beginTime, int $endTime): ?array {
        return $this->apiGet('/api/device/mileage', [
            'imeis' => implode(',', $imeis),
            'begintime' => $beginTime,
            'endtime' => $endTime
        ]);
    }
    
    public function createGeofence(string $imei, string $name, int $alarmType, float $latitude, float $longitude, int $radius): ?array {
        return $this->apiPost('/api/geofence/create', [
            'imei' => $imei,
            'efencename' => $name,
            'alarmtype' => $alarmType,
            'longitude' => $longitude,
            'latitude' => $latitude,
            'radius' => $radius
        ]);
    }
    
    public function getGeofenceList(): ?array {
        return $this->apiGet('/api/geofence/list');
    }
    
    public function deleteGeofence(string $geofenceId): ?array {
        return $this->apiPost('/api/geofence/delete', [
            'efenceid' => $geofenceId
        ]);
    }
    
    public function getAccountInfo(): ?array {
        $result = $this->apiGet('/api/device/list');
        if ($result && ($result['code'] ?? -1) === 0) {
            $deviceCount = is_array($result['record'] ?? null) ? count($result['record']) : 0;
            return [
                'code' => 0,
                'message' => 'OK',
                'record' => [
                    'account' => $this->account,
                    'device_count' => $deviceCount
                ]
            ];
        }
        return $result;
    }
    
    public function getIMEIsInfo(array $imeis): ?array {
        return $this->apiGet('/api/device/imeis', [
            'imeis' => implode(',', $imeis)
        ]);
    }
}
