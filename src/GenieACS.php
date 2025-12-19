<?php
namespace App;

class GenieACS {
    private \PDO $db;
    private string $baseUrl;
    private string $username;
    private string $password;
    private int $timeout;
    
    public function __construct(\PDO $db) {
        $this->db = $db;
        $this->loadSettings();
    }
    
    private function loadSettings(): void {
        $stmt = $this->db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'genieacs_%'");
        $settings = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        
        $this->baseUrl = rtrim($settings['genieacs_url'] ?? 'http://localhost:7557', '/');
        $this->username = $settings['genieacs_username'] ?? '';
        $this->password = $settings['genieacs_password'] ?? '';
        $this->timeout = (int)($settings['genieacs_timeout'] ?? 30);
    }
    
    public function isConfigured(): bool {
        return !empty($this->baseUrl) && $this->baseUrl !== 'http://localhost:7557';
    }
    
    private function request(string $method, string $endpoint, ?array $data = null, array $query = []): array {
        $url = $this->baseUrl . $endpoint;
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json'
            ]
        ]);
        
        if (!empty($this->username)) {
            curl_setopt($ch, CURLOPT_USERPWD, "{$this->username}:{$this->password}");
        }
        
        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return ['success' => false, 'error' => $error];
        }
        
        $decoded = json_decode($response, true);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true, 'data' => $decoded, 'http_code' => $httpCode];
        }
        
        return [
            'success' => false, 
            'error' => $decoded['message'] ?? "HTTP {$httpCode}", 
            'http_code' => $httpCode
        ];
    }
    
    public function testConnection(): array {
        $result = $this->request('GET', '/devices', null, ['limit' => 1]);
        if ($result['success']) {
            return ['success' => true, 'message' => 'GenieACS connection successful'];
        }
        return ['success' => false, 'message' => $result['error'] ?? 'Connection failed'];
    }
    
    public function getDevices(array $filters = [], int $limit = 100, int $skip = 0): array {
        $query = ['limit' => $limit, 'skip' => $skip];
        
        if (!empty($filters['serial'])) {
            $query['query'] = json_encode(['_deviceId._SerialNumber' => $filters['serial']]);
        } elseif (!empty($filters['query'])) {
            $query['query'] = $filters['query'];
        }
        
        if (!empty($filters['projection'])) {
            $query['projection'] = $filters['projection'];
        }
        
        return $this->request('GET', '/devices', null, $query);
    }
    
    public function getDevice(string $deviceId): array {
        $encodedId = urlencode($deviceId);
        return $this->request('GET', "/devices/{$encodedId}");
    }
    
    public function getDeviceBySerial(string $serial): array {
        $query = json_encode(['_deviceId._SerialNumber' => $serial]);
        $result = $this->request('GET', '/devices', null, ['query' => $query, 'limit' => 1]);
        
        if ($result['success'] && !empty($result['data'])) {
            return ['success' => true, 'device' => $result['data'][0]];
        }
        
        return ['success' => false, 'error' => 'Device not found'];
    }
    
    public function deleteDevice(string $deviceId): array {
        $encodedId = urlencode($deviceId);
        return $this->request('DELETE', "/devices/{$encodedId}");
    }
    
    public function rebootDevice(string $deviceId): array {
        $encodedId = urlencode($deviceId);
        return $this->request('POST', "/devices/{$encodedId}/tasks", [
            'name' => 'reboot'
        ]);
    }
    
    public function factoryReset(string $deviceId): array {
        $encodedId = urlencode($deviceId);
        return $this->request('POST', "/devices/{$encodedId}/tasks", [
            'name' => 'factoryReset'
        ]);
    }
    
    public function refreshDevice(string $deviceId): array {
        $encodedId = urlencode($deviceId);
        return $this->request('POST', "/devices/{$encodedId}/tasks", [
            'name' => 'refreshObject',
            'objectName' => ''
        ]);
    }
    
    public function getParameterValues(string $deviceId, array $parameterNames): array {
        $encodedId = urlencode($deviceId);
        return $this->request('POST', "/devices/{$encodedId}/tasks", [
            'name' => 'getParameterValues',
            'parameterNames' => $parameterNames
        ]);
    }
    
    public function setParameterValues(string $deviceId, array $parameterValues): array {
        $encodedId = urlencode($deviceId);
        return $this->request('POST', "/devices/{$encodedId}/tasks", [
            'name' => 'setParameterValues',
            'parameterValues' => $parameterValues
        ]);
    }
    
    public function downloadFirmware(string $deviceId, string $fileType, string $url, string $filename = ''): array {
        $encodedId = urlencode($deviceId);
        return $this->request('POST', "/devices/{$encodedId}/tasks", [
            'name' => 'download',
            'file' => $url,
            'fileType' => $fileType,
            'fileName' => $filename
        ]);
    }
    
    public function getTasks(string $deviceId): array {
        $query = json_encode(['device' => $deviceId]);
        return $this->request('GET', '/tasks', null, ['query' => $query]);
    }
    
    public function deleteTask(string $taskId): array {
        return $this->request('DELETE', "/tasks/{$taskId}");
    }
    
    public function getFaults(string $deviceId = ''): array {
        $query = [];
        if ($deviceId) {
            $query['query'] = json_encode(['device' => $deviceId]);
        }
        return $this->request('GET', '/faults', null, $query);
    }
    
    public function deleteFault(string $faultId): array {
        return $this->request('DELETE', "/faults/{$faultId}");
    }
    
    public function getPresets(): array {
        return $this->request('GET', '/presets');
    }
    
    public function createPreset(array $preset): array {
        return $this->request('PUT', "/presets/{$preset['_id']}", $preset);
    }
    
    public function deletePreset(string $presetId): array {
        return $this->request('DELETE', "/presets/{$presetId}");
    }
    
    public function getProvisions(): array {
        return $this->request('GET', '/provisions');
    }
    
    public function createProvision(string $name, string $script): array {
        return $this->request('PUT', "/provisions/{$name}", null);
    }
    
    public function getFiles(): array {
        return $this->request('GET', '/files');
    }
    
    public function setWiFiSettings(string $deviceId, string $ssid, string $password, bool $enabled = true, int $channel = 0): array {
        $params = [
            ['InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID', $ssid, 'xsd:string'],
            ['InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.KeyPassphrase', $password, 'xsd:string'],
            ['InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.Enable', $enabled, 'xsd:boolean']
        ];
        
        if ($channel > 0) {
            $params[] = ['InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.Channel', $channel, 'xsd:unsignedInt'];
        }
        
        return $this->setParameterValues($deviceId, $params);
    }
    
    public function setWiFi5GSettings(string $deviceId, string $ssid, string $password, bool $enabled = true): array {
        $params = [
            ['InternetGatewayDevice.LANDevice.1.WLANConfiguration.5.SSID', $ssid, 'xsd:string'],
            ['InternetGatewayDevice.LANDevice.1.WLANConfiguration.5.PreSharedKey.1.KeyPassphrase', $password, 'xsd:string'],
            ['InternetGatewayDevice.LANDevice.1.WLANConfiguration.5.Enable', $enabled, 'xsd:boolean']
        ];
        
        return $this->setParameterValues($deviceId, $params);
    }
    
    public function getWiFiSettings(string $deviceId): array {
        $params = [
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.Enable',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.Channel',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.Standard',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.5.SSID',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.5.Enable'
        ];
        
        return $this->getParameterValues($deviceId, $params);
    }
    
    public function setPPPoECredentials(string $deviceId, string $username, string $password): array {
        $params = [
            ['InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.Username', $username, 'xsd:string'],
            ['InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.Password', $password, 'xsd:string']
        ];
        
        return $this->setParameterValues($deviceId, $params);
    }
    
    public function getDeviceInfo(string $deviceId): array {
        $result = $this->getDevice($deviceId);
        if (!$result['success']) {
            return $result;
        }
        
        $device = $result['data'];
        $info = [
            'device_id' => $device['_id'] ?? '',
            'serial' => $device['_deviceId']['_SerialNumber'] ?? '',
            'manufacturer' => $device['_deviceId']['_Manufacturer'] ?? '',
            'oui' => $device['_deviceId']['_OUI'] ?? '',
            'product_class' => $device['_deviceId']['_ProductClass'] ?? '',
            'software_version' => $this->extractValue($device, 'InternetGatewayDevice.DeviceInfo.SoftwareVersion'),
            'hardware_version' => $this->extractValue($device, 'InternetGatewayDevice.DeviceInfo.HardwareVersion'),
            'uptime' => $this->extractValue($device, 'InternetGatewayDevice.DeviceInfo.UpTime'),
            'last_inform' => $device['_lastInform'] ?? null,
            'last_boot' => $device['_lastBoot'] ?? null,
            'ip_address' => $this->extractValue($device, 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.ExternalIPAddress'),
        ];
        
        return ['success' => true, 'info' => $info];
    }
    
    private function extractValue(array $device, string $path): ?string {
        $parts = explode('.', $path);
        $current = $device;
        
        foreach ($parts as $part) {
            if (isset($current[$part])) {
                $current = $current[$part];
            } else {
                return null;
            }
        }
        
        if (is_array($current) && isset($current['_value'])) {
            return (string)$current['_value'];
        }
        
        return is_scalar($current) ? (string)$current : null;
    }
    
    public function syncDevicesToDB(): array {
        $result = $this->getDevices([], 1000);
        if (!$result['success']) {
            return $result;
        }
        
        $synced = 0;
        $devices = $result['data'] ?? [];
        
        foreach ($devices as $device) {
            $serial = $device['_deviceId']['_SerialNumber'] ?? '';
            if (empty($serial)) continue;
            
            $stmt = $this->db->prepare("SELECT id FROM huawei_onus WHERE sn = ?");
            $stmt->execute([$serial]);
            $onu = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($onu) {
                $deviceId = $device['_id'] ?? '';
                $lastInform = $device['_lastInform'] ?? null;
                
                $stmt = $this->db->prepare("
                    INSERT INTO tr069_devices (onu_id, device_id, serial_number, last_inform, manufacturer, model)
                    VALUES (?, ?, ?, ?, ?, ?)
                    ON CONFLICT (serial_number) DO UPDATE SET 
                        device_id = EXCLUDED.device_id,
                        last_inform = EXCLUDED.last_inform,
                        updated_at = CURRENT_TIMESTAMP
                ");
                $stmt->execute([
                    $onu['id'],
                    $deviceId,
                    $serial,
                    $lastInform,
                    $device['_deviceId']['_Manufacturer'] ?? '',
                    $device['_deviceId']['_ProductClass'] ?? ''
                ]);
                $synced++;
            }
        }
        
        return ['success' => true, 'synced' => $synced, 'total' => count($devices)];
    }
    
    public function getDeviceCount(): int {
        $result = $this->getDevices([], 1);
        return $result['success'] ? count($result['data'] ?? []) : 0;
    }
    
    public function getOnlineDevices(): array {
        $fiveMinutesAgo = date('c', strtotime('-5 minutes'));
        $query = json_encode(['_lastInform' => ['$gte' => $fiveMinutesAgo]]);
        return $this->request('GET', '/devices', null, ['query' => $query]);
    }
}
