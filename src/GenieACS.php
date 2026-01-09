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
        // Check if GenieACS is enabled in settings
        try {
            $stmt = $this->db->query("SELECT setting_value FROM settings WHERE setting_key = 'genieacs_enabled'");
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            $enabled = ($row['setting_value'] ?? '0') === '1';
            
            if (!$enabled) return false;
        } catch (\Exception $e) {
            // If settings check fails, fall back to URL check
        }
        
        // Just check that URL is not empty - allow localhost/127.0.0.1 URLs
        return !empty($this->baseUrl);
    }
    
    public function getBaseUrl(): string {
        return $this->baseUrl;
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
        // Try exact match first
        $query = json_encode(['_deviceId._SerialNumber' => $serial]);
        $result = $this->request('GET', '/devices', null, ['query' => $query, 'limit' => 1]);
        
        if ($result['success'] && !empty($result['data'])) {
            return ['success' => true, 'device' => $result['data'][0]];
        }
        
        // If not found and serial looks like OLT format (4 letter prefix + hex), convert to GenieACS format
        if (preg_match('/^[A-Z]{4}[0-9A-F]{8}$/i', $serial)) {
            $genieSerial = $this->convertOltSerialToGenieacs($serial);
            if ($genieSerial !== $serial) {
                $query = json_encode(['_deviceId._SerialNumber' => $genieSerial]);
                $result = $this->request('GET', '/devices', null, ['query' => $query, 'limit' => 1]);
                
                if ($result['success'] && !empty($result['data'])) {
                    return ['success' => true, 'device' => $result['data'][0]];
                }
            }
        }
        
        return ['success' => false, 'error' => 'Device not found'];
    }
    
    /**
     * Convert OLT serial format (HWTCF2D53A8B) to GenieACS hex format (48575443F2D53A8B)
     * OLT stores vendor prefix as ASCII (HWTC), GenieACS stores as hex (48575443)
     */
    public function convertOltSerialToGenieacs(string $oltSerial): string {
        if (strlen($oltSerial) < 12) {
            return $oltSerial;
        }
        
        // First 4 chars are vendor prefix (ASCII like "HWTC")
        $vendorPrefix = substr($oltSerial, 0, 4);
        $hexSuffix = substr($oltSerial, 4); // Remaining 8 chars are already hex
        
        // Convert vendor prefix to uppercase hex
        $vendorHex = strtoupper(bin2hex($vendorPrefix));
        
        return $vendorHex . strtoupper($hexSuffix);
    }
    
    /**
     * Convert GenieACS hex serial (48575443F2D53A8B) to OLT format (HWTCF2D53A8B)
     */
    public function convertGeniSerialToOlt(string $genieSerial): string {
        if (strlen($genieSerial) !== 16) {
            return $genieSerial;
        }
        
        // First 8 chars are vendor prefix in hex
        $vendorHex = substr($genieSerial, 0, 8);
        $hexSuffix = substr($genieSerial, 8);
        
        // Convert hex back to ASCII
        $vendorPrefix = @hex2bin($vendorHex);
        if ($vendorPrefix === false) {
            return $genieSerial;
        }
        
        return $vendorPrefix . strtoupper($hexSuffix);
    }
    
    public function deleteDevice(string $deviceId): array {
        $encodedId = urlencode($deviceId);
        return $this->request('DELETE', "/devices/{$encodedId}");
    }
    
    public function rebootDevice(string $deviceId): array {
        $encodedId = urlencode($deviceId);
        // Use connection_request to execute immediately instead of waiting for periodic inform
        return $this->request('POST', "/devices/{$encodedId}/tasks?connection_request", [
            'name' => 'reboot'
        ]);
    }
    
    public function factoryReset(string $deviceId): array {
        $encodedId = urlencode($deviceId);
        return $this->request('POST', "/devices/{$encodedId}/tasks?connection_request", [
            'name' => 'factoryReset'
        ]);
    }
    
    public function refreshDevice(string $deviceId): array {
        $encodedId = urlencode($deviceId);
        return $this->request('POST', "/devices/{$encodedId}/tasks?connection_request", [
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
        // Use connection_request to execute immediately
        return $this->request('POST', "/devices/{$encodedId}/tasks?connection_request", [
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
    
    public function upgradeFirmware(string $deviceId, string $firmwareUrl): array {
        return $this->downloadFirmware($deviceId, '1 Firmware Upgrade Image', $firmwareUrl);
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
        $result = $this->getDevice($deviceId);
        
        if (!$result['success'] || empty($result['data'])) {
            return ['success' => false, 'error' => 'Device not found or no data'];
        }
        
        $device = $result['data'];
        $wifiData = [];
        
        $extractValue = function($device, $path) {
            $parts = explode('.', $path);
            $current = $device;
            foreach ($parts as $part) {
                if (isset($current[$part])) {
                    $current = $current[$part];
                } else {
                    return null;
                }
            }
            return $current['_value'] ?? $current;
        };
        
        for ($i = 1; $i <= 5; $i++) {
            $basePath = "InternetGatewayDevice.LANDevice.1.WLANConfiguration.{$i}";
            $ssid = $extractValue($device, "{$basePath}.SSID");
            $enable = $extractValue($device, "{$basePath}.Enable");
            $channel = $extractValue($device, "{$basePath}.Channel");
            
            if ($ssid !== null || $enable !== null) {
                $wifiData[] = [
                    "{$basePath}.SSID", 
                    $ssid
                ];
                $wifiData[] = [
                    "{$basePath}.Enable", 
                    $enable
                ];
                $wifiData[] = [
                    "{$basePath}.Channel", 
                    $channel
                ];
            }
        }
        
        return ['success' => true, 'data' => $wifiData];
    }
    
    public function setPPPoECredentials(string $deviceId, string $username, string $password): array {
        $params = [
            ['InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.Username', $username, 'xsd:string'],
            ['InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.Password', $password, 'xsd:string']
        ];
        
        return $this->setParameterValues($deviceId, $params);
    }
    
    /**
     * Change ONU administrator password via TR-069
     */
    public function setAdminPassword(string $deviceId, string $newPassword, string $username = 'admin'): array {
        // Try multiple common TR-069 paths for admin password
        $params = [
            ['InternetGatewayDevice.UserInterface.CurrentPassword', $newPassword, 'xsd:string']
        ];
        
        $result = $this->setParameterValues($deviceId, $params);
        
        // If first method fails, try alternative paths
        if (!$result['success']) {
            $altParams = [
                ['InternetGatewayDevice.DeviceInfo.X_HW_WebUserInfo.1.UserName', $username, 'xsd:string'],
                ['InternetGatewayDevice.DeviceInfo.X_HW_WebUserInfo.1.Password', $newPassword, 'xsd:string']
            ];
            $result = $this->setParameterValues($deviceId, $altParams);
        }
        
        // Try another common path for Huawei devices
        if (!$result['success']) {
            $hwParams = [
                ['InternetGatewayDevice.X_HW_WebUserInfo.WebUserInfoInstance.1.UserName', $username, 'xsd:string'],
                ['InternetGatewayDevice.X_HW_WebUserInfo.WebUserInfoInstance.1.Password', $newPassword, 'xsd:string']
            ];
            $result = $this->setParameterValues($deviceId, $hwParams);
        }
        
        return $result;
    }
    
    /**
     * Get Ethernet port settings via TR-069
     */
    public function getEthernetSettings(string $deviceId, int $portNumber = 1): array {
        $params = [
            "InternetGatewayDevice.LANDevice.1.LANEthernetInterfaceConfig.{$portNumber}.Enable",
            "InternetGatewayDevice.LANDevice.1.LANEthernetInterfaceConfig.{$portNumber}.MaxBitRate",
            "InternetGatewayDevice.LANDevice.1.LANEthernetInterfaceConfig.{$portNumber}.DuplexMode",
            "InternetGatewayDevice.LANDevice.1.LANEthernetInterfaceConfig.{$portNumber}.Status"
        ];
        
        return $this->getParameterValues($deviceId, $params);
    }
    
    /**
     * Configure Ethernet port settings via TR-069
     */
    public function setEthernetSettings(string $deviceId, int $portNumber, array $settings): array {
        $params = [];
        
        if (isset($settings['enabled'])) {
            $params[] = ["InternetGatewayDevice.LANDevice.1.LANEthernetInterfaceConfig.{$portNumber}.Enable", $settings['enabled'], 'xsd:boolean'];
        }
        
        if (!empty($settings['max_bit_rate'])) {
            // Values: Auto, 10, 100, 1000
            $params[] = ["InternetGatewayDevice.LANDevice.1.LANEthernetInterfaceConfig.{$portNumber}.MaxBitRate", $settings['max_bit_rate'], 'xsd:string'];
        }
        
        if (!empty($settings['duplex_mode'])) {
            // Values: Auto, Half, Full
            $params[] = ["InternetGatewayDevice.LANDevice.1.LANEthernetInterfaceConfig.{$portNumber}.DuplexMode", $settings['duplex_mode'], 'xsd:string'];
        }
        
        if (empty($params)) {
            return ['success' => false, 'error' => 'No settings provided'];
        }
        
        return $this->setParameterValues($deviceId, $params);
    }
    
    /**
     * Get LAN host information (connected devices)
     */
    public function getLANHosts(string $deviceId): array {
        $params = [
            'InternetGatewayDevice.LANDevice.1.Hosts.HostNumberOfEntries'
        ];
        
        return $this->getParameterValues($deviceId, $params);
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
        $unlinked = [];
        $devices = $result['data'] ?? [];
        
        foreach ($devices as $device) {
            $serial = $device['_deviceId']['_SerialNumber'] ?? '';
            if (empty($serial)) continue;
            
            $deviceId = $device['_id'] ?? '';
            
            // Match by: sn, tr069_serial, or tr069_device_id (already linked)
            $stmt = $this->db->prepare("SELECT id FROM huawei_onus WHERE sn = ? OR tr069_serial = ? OR tr069_device_id = ?");
            $stmt->execute([$serial, $serial, $deviceId]);
            $onu = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$onu) {
                $unlinked[] = ['serial' => $serial, 'device_id' => $deviceId];
            }
            
            if ($onu) {
                $lastInform = $device['_lastInform'] ?? null;
                $manufacturer = $device['_deviceId']['_Manufacturer'] ?? '';
                $model = $device['_deviceId']['_ProductClass'] ?? '';
                
                // Extract IP address from device data
                $ipAddress = $this->extractValue($device, 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.ExternalIPAddress')
                    ?? $this->extractValue($device, 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.ExternalIPAddress')
                    ?? null;
                
                // Sync to tr069_devices table
                $stmt = $this->db->prepare("
                    INSERT INTO tr069_devices (onu_id, device_id, serial_number, last_inform, manufacturer, model, ip_address)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                    ON CONFLICT (serial_number) DO UPDATE SET 
                        onu_id = COALESCE(EXCLUDED.onu_id, tr069_devices.onu_id),
                        device_id = EXCLUDED.device_id,
                        last_inform = EXCLUDED.last_inform,
                        ip_address = COALESCE(EXCLUDED.ip_address, tr069_devices.ip_address),
                        updated_at = CURRENT_TIMESTAMP
                ");
                $stmt->execute([
                    $onu['id'],
                    $deviceId,
                    $serial,
                    $lastInform,
                    $manufacturer,
                    $model,
                    $ipAddress
                ]);
                
                // Also update huawei_onus with TR-069 info
                try {
                    $updateStmt = $this->db->prepare("
                        UPDATE huawei_onus SET 
                            tr069_device_id = ?,
                            tr069_serial = ?,
                            tr069_ip = COALESCE(?, tr069_ip),
                            tr069_status = 'connected',
                            tr069_last_inform = ?
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$deviceId, $serial, $ipAddress, $lastInform, $onu['id']]);
                } catch (\Exception $e) {
                    // Ignore if columns don't exist
                }
                
                $synced++;
            }
        }
        
        return ['success' => true, 'synced' => $synced, 'total' => count($devices), 'unlinked' => $unlinked];
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
    
    public function setWirelessConfig(string $deviceId, array $config): array {
        $params = [];
        $base24 = 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1';
        $base5 = 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.5';
        
        // 2.4GHz WiFi settings
        if (isset($config['wifi_24_enable'])) {
            $params[] = ["{$base24}.Enable", (bool)$config['wifi_24_enable'], 'xsd:boolean'];
        }
        if (!empty($config['ssid_24'])) {
            $params[] = ["{$base24}.SSID", $config['ssid_24'], 'xsd:string'];
        }
        if (!empty($config['wifi_pass_24'])) {
            $params[] = ["{$base24}.PreSharedKey.1.KeyPassphrase", $config['wifi_pass_24'], 'xsd:string'];
        }
        if (isset($config['channel_24']) && $config['channel_24'] !== 'auto') {
            $params[] = ["{$base24}.Channel", (int)$config['channel_24'], 'xsd:unsignedInt'];
        }
        if (isset($config['hide_ssid_24'])) {
            $params[] = ["{$base24}.SSIDAdvertisementEnabled", !$config['hide_ssid_24'], 'xsd:boolean'];
        }
        if (isset($config['bandwidth_24'])) {
            $params[] = ["{$base24}.X_HW_ChannelWidth", (int)$config['bandwidth_24'], 'xsd:unsignedInt'];
        }
        
        // 5GHz WiFi settings
        if (isset($config['wifi_5_enable'])) {
            $params[] = ["{$base5}.Enable", (bool)$config['wifi_5_enable'], 'xsd:boolean'];
        }
        if (!empty($config['ssid_5'])) {
            $params[] = ["{$base5}.SSID", $config['ssid_5'], 'xsd:string'];
        }
        if (!empty($config['wifi_pass_5'])) {
            $params[] = ["{$base5}.PreSharedKey.1.KeyPassphrase", $config['wifi_pass_5'], 'xsd:string'];
        }
        if (isset($config['channel_5']) && $config['channel_5'] !== 'auto') {
            $params[] = ["{$base5}.Channel", (int)$config['channel_5'], 'xsd:unsignedInt'];
        }
        if (isset($config['hide_ssid_5'])) {
            $params[] = ["{$base5}.SSIDAdvertisementEnabled", !$config['hide_ssid_5'], 'xsd:boolean'];
        }
        if (isset($config['bandwidth_5'])) {
            $params[] = ["{$base5}.X_HW_ChannelWidth", (int)$config['bandwidth_5'], 'xsd:unsignedInt'];
        }
        
        // Max clients
        if (isset($config['max_clients'])) {
            $params[] = ["{$base24}.MaxAssociatedDevices", (int)$config['max_clients'], 'xsd:unsignedInt'];
            $params[] = ["{$base5}.MaxAssociatedDevices", (int)$config['max_clients'], 'xsd:unsignedInt'];
        }
        
        if (empty($params)) {
            return ['success' => false, 'error' => 'No parameters to configure'];
        }
        
        return $this->setParameterValues($deviceId, $params);
    }
    
    public function setAdvancedWiFiConfig(string $deviceId, array $config): array {
        $params = [];
        $base24 = 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1';
        $base5 = 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.5';
        
        // 2.4GHz WiFi settings
        $wifi24 = $config['wifi_24'] ?? [];
        if (!empty($wifi24)) {
            $params[] = ["{$base24}.Enable", (bool)($wifi24['enabled'] ?? true), 'xsd:boolean'];
            
            if (!empty($wifi24['ssid'])) {
                $params[] = ["{$base24}.SSID", $wifi24['ssid'], 'xsd:string'];
            }
            if (!empty($wifi24['password'])) {
                $params[] = ["{$base24}.PreSharedKey.1.KeyPassphrase", $wifi24['password'], 'xsd:string'];
                $params[] = ["{$base24}.PreSharedKey.1.PreSharedKey", $wifi24['password'], 'xsd:string'];
            }
            if (isset($wifi24['channel']) && $wifi24['channel'] > 0) {
                $params[] = ["{$base24}.Channel", (int)$wifi24['channel'], 'xsd:unsignedInt'];
            } else {
                $params[] = ["{$base24}.AutoChannelEnable", true, 'xsd:boolean'];
            }
            
            // VLAN settings for 2.4GHz (Huawei specific parameters)
            $mode24 = $wifi24['mode'] ?? 'access';
            if ($mode24 === 'access' && !empty($wifi24['access_vlan'])) {
                $vlan = (int)$wifi24['access_vlan'];
                if ($vlan > 1) {
                    // Set VLAN tag for access mode
                    $params[] = ["{$base24}.X_HW_WlanAccessType", 1, 'xsd:unsignedInt']; // 1 = Access
                    $params[] = ["{$base24}.X_HW_VLANID", $vlan, 'xsd:unsignedInt'];
                }
            } elseif ($mode24 === 'trunk') {
                $params[] = ["{$base24}.X_HW_WlanAccessType", 2, 'xsd:unsignedInt']; // 2 = Trunk
                if (!empty($wifi24['native_vlan'])) {
                    $params[] = ["{$base24}.X_HW_PVID", (int)$wifi24['native_vlan'], 'xsd:unsignedInt'];
                }
            }
        }
        
        // 5GHz WiFi settings
        $wifi5 = $config['wifi_5'] ?? [];
        if (!empty($wifi5)) {
            $params[] = ["{$base5}.Enable", (bool)($wifi5['enabled'] ?? true), 'xsd:boolean'];
            
            if (!empty($wifi5['ssid'])) {
                $params[] = ["{$base5}.SSID", $wifi5['ssid'], 'xsd:string'];
            }
            if (!empty($wifi5['password'])) {
                $params[] = ["{$base5}.PreSharedKey.1.KeyPassphrase", $wifi5['password'], 'xsd:string'];
                $params[] = ["{$base5}.PreSharedKey.1.PreSharedKey", $wifi5['password'], 'xsd:string'];
            }
            if (isset($wifi5['channel']) && $wifi5['channel'] > 0) {
                $params[] = ["{$base5}.Channel", (int)$wifi5['channel'], 'xsd:unsignedInt'];
            } else {
                $params[] = ["{$base5}.AutoChannelEnable", true, 'xsd:boolean'];
            }
            
            // VLAN settings for 5GHz (Huawei specific parameters)
            $mode5 = $wifi5['mode'] ?? 'access';
            if ($mode5 === 'access' && !empty($wifi5['access_vlan'])) {
                $vlan = (int)$wifi5['access_vlan'];
                if ($vlan > 1) {
                    $params[] = ["{$base5}.X_HW_WlanAccessType", 1, 'xsd:unsignedInt'];
                    $params[] = ["{$base5}.X_HW_VLANID", $vlan, 'xsd:unsignedInt'];
                }
            } elseif ($mode5 === 'trunk') {
                $params[] = ["{$base5}.X_HW_WlanAccessType", 2, 'xsd:unsignedInt'];
                if (!empty($wifi5['native_vlan'])) {
                    $params[] = ["{$base5}.X_HW_PVID", (int)$wifi5['native_vlan'], 'xsd:unsignedInt'];
                }
            }
        }
        
        if (empty($params)) {
            return ['success' => false, 'error' => 'No WiFi parameters to configure'];
        }
        
        return $this->setParameterValues($deviceId, $params);
    }
    
    public function setLANConfig(string $deviceId, array $config): array {
        $params = [];
        $lanBase = 'InternetGatewayDevice.LANDevice.1.LANHostConfigManagement';
        
        // LAN IP settings
        if (!empty($config['lan_ip'])) {
            $params[] = ["{$lanBase}.IPInterface.1.IPInterfaceIPAddress", $config['lan_ip'], 'xsd:string'];
        }
        if (!empty($config['lan_mask'])) {
            $params[] = ["{$lanBase}.IPInterface.1.IPInterfaceSubnetMask", $config['lan_mask'], 'xsd:string'];
        }
        
        // DHCP Server settings
        if (isset($config['dhcp_enable'])) {
            $params[] = ["{$lanBase}.DHCPServerEnable", (bool)$config['dhcp_enable'], 'xsd:boolean'];
        }
        if (!empty($config['dhcp_start'])) {
            $params[] = ["{$lanBase}.MinAddress", $config['dhcp_start'], 'xsd:string'];
        }
        if (!empty($config['dhcp_end'])) {
            $params[] = ["{$lanBase}.MaxAddress", $config['dhcp_end'], 'xsd:string'];
        }
        if (isset($config['dhcp_lease'])) {
            $leaseSeconds = (int)$config['dhcp_lease'] * 3600;
            $params[] = ["{$lanBase}.DHCPLeaseTime", $leaseSeconds, 'xsd:unsignedInt'];
        }
        
        if (empty($params)) {
            return ['success' => false, 'error' => 'No parameters to configure'];
        }
        
        return $this->setParameterValues($deviceId, $params);
    }
    
    public function setWANConfig(string $deviceId, array $config): array {
        $params = [];
        $wanBase = 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1';
        
        // PPPoE or DHCP connection
        $connType = $config['connection_type'] ?? 'pppoe';
        
        if ($connType === 'pppoe') {
            $pppBase = "{$wanBase}.WANPPPConnection.1";
            if (!empty($config['pppoe_username'])) {
                $params[] = ["{$pppBase}.Username", $config['pppoe_username'], 'xsd:string'];
            }
            if (!empty($config['pppoe_password'])) {
                $params[] = ["{$pppBase}.Password", $config['pppoe_password'], 'xsd:string'];
            }
            if (isset($config['wan_vlan']) && $config['wan_vlan'] > 0) {
                $params[] = ["{$pppBase}.X_HW_VLAN", (int)$config['wan_vlan'], 'xsd:unsignedInt'];
            }
            if (isset($config['wan_priority'])) {
                $params[] = ["{$pppBase}.X_HW_PRI", (int)$config['wan_priority'], 'xsd:unsignedInt'];
            }
            if (isset($config['nat_enable'])) {
                $params[] = ["{$pppBase}.NATEnabled", (bool)$config['nat_enable'], 'xsd:boolean'];
            }
            if (isset($config['mtu'])) {
                $params[] = ["{$pppBase}.MaxMRUSize", (int)$config['mtu'], 'xsd:unsignedInt'];
            }
        } else {
            $ipBase = "{$wanBase}.WANIPConnection.1";
            if (isset($config['wan_vlan']) && $config['wan_vlan'] > 0) {
                $params[] = ["{$ipBase}.X_HW_VLAN", (int)$config['wan_vlan'], 'xsd:unsignedInt'];
            }
            if (isset($config['nat_enable'])) {
                $params[] = ["{$ipBase}.NATEnabled", (bool)$config['nat_enable'], 'xsd:boolean'];
            }
        }
        
        if (empty($params)) {
            return ['success' => false, 'error' => 'No parameters to configure'];
        }
        
        return $this->setParameterValues($deviceId, $params);
    }
    
    /**
     * SmartOLT-style Internet WAN configuration via TR-069
     * Creates and configures Internet WAN connection (PPPoE or IPoE/DHCP)
     */
    public function configureInternetWAN(string $deviceId, array $config): array {
        $results = [];
        $errors = [];
        
        $connectionType = $config['connection_type'] ?? 'pppoe'; // pppoe, dhcp, static
        $serviceVlan = (int)($config['service_vlan'] ?? 0);
        $wanIndex = (int)($config['wan_index'] ?? 2); // WANConnectionDevice index (1 is usually management)
        $connIndex = 1; // Connection index within the device
        
        $wanDeviceBase = "InternetGatewayDevice.WANDevice.1";
        $wanConnDeviceBase = "{$wanDeviceBase}.WANConnectionDevice.{$wanIndex}";
        
        // Step 1: Add WANConnectionDevice if needed (only for new connections)
        if (!empty($config['create_wan_device'])) {
            $addResult = $this->addObject($deviceId, "{$wanDeviceBase}.WANConnectionDevice");
            $results['add_wan_device'] = $addResult;
            if (!$addResult['success']) {
                // Device may already exist, continue anyway
            }
        }
        
        // Step 2: Configure based on connection type
        if ($connectionType === 'pppoe') {
            $pppBase = "{$wanConnDeviceBase}.WANPPPConnection.{$connIndex}";
            
            // Add PPP connection first
            if (!empty($config['create_ppp_connection'])) {
                $addPppResult = $this->addObject($deviceId, "{$wanConnDeviceBase}.WANPPPConnection");
                $results['add_ppp_connection'] = $addPppResult;
            }
            
            // Configure PPPoE settings
            $pppParams = [];
            
            // Enable the connection
            $pppParams[] = ["{$pppBase}.Enable", true, 'xsd:boolean'];
            
            // Connection name
            $connectionName = $config['connection_name'] ?? 'Internet_PPPoE';
            $pppParams[] = ["{$pppBase}.Name", $connectionName, 'xsd:string'];
            
            // PPPoE credentials
            if (!empty($config['pppoe_username'])) {
                $pppParams[] = ["{$pppBase}.Username", $config['pppoe_username'], 'xsd:string'];
            }
            if (!empty($config['pppoe_password'])) {
                $pppParams[] = ["{$pppBase}.Password", $config['pppoe_password'], 'xsd:string'];
            }
            
            // VLAN configuration (Huawei-specific)
            if ($serviceVlan > 0) {
                $pppParams[] = ["{$pppBase}.X_HW_VLAN", $serviceVlan, 'xsd:unsignedInt'];
            }
            
            // NAT enabled
            $pppParams[] = ["{$pppBase}.NATEnabled", true, 'xsd:boolean'];
            
            // LCP Echo settings (keep-alive)
            $pppParams[] = ["{$pppBase}.X_HW_LcpEchoReqCheck", true, 'xsd:boolean'];
            $pppParams[] = ["{$pppBase}.PPPLCPEcho", 10, 'xsd:unsignedInt'];
            
            $pppResult = $this->setParameterValues($deviceId, $pppParams);
            $results['ppp_config'] = $pppResult;
            if (!$pppResult['success']) {
                $errors[] = 'Failed to configure PPPoE: ' . ($pppResult['error'] ?? 'Unknown');
            }
            
            // Set WAN name for policy routing
            $wanName = "wan{$wanIndex}.{$connIndex}.ppp{$connIndex}";
            
        } else {
            // DHCP or Static IP (IPoE)
            $ipBase = "{$wanConnDeviceBase}.WANIPConnection.{$connIndex}";
            
            // Add IP connection first
            if (!empty($config['create_ip_connection'])) {
                $addIpResult = $this->addObject($deviceId, "{$wanConnDeviceBase}.WANIPConnection");
                $results['add_ip_connection'] = $addIpResult;
            }
            
            $ipParams = [];
            $ipParams[] = ["{$ipBase}.Enable", true, 'xsd:boolean'];
            
            $connectionName = $config['connection_name'] ?? 'Internet_DHCP';
            $ipParams[] = ["{$ipBase}.Name", $connectionName, 'xsd:string'];
            
            if ($connectionType === 'dhcp') {
                $ipParams[] = ["{$ipBase}.AddressingType", 'DHCP', 'xsd:string'];
            } else {
                $ipParams[] = ["{$ipBase}.AddressingType", 'Static', 'xsd:string'];
                if (!empty($config['static_ip'])) {
                    $ipParams[] = ["{$ipBase}.ExternalIPAddress", $config['static_ip'], 'xsd:string'];
                }
                if (!empty($config['static_mask'])) {
                    $ipParams[] = ["{$ipBase}.SubnetMask", $config['static_mask'], 'xsd:string'];
                }
                if (!empty($config['static_gateway'])) {
                    $ipParams[] = ["{$ipBase}.DefaultGateway", $config['static_gateway'], 'xsd:string'];
                }
            }
            
            // VLAN
            if ($serviceVlan > 0) {
                $ipParams[] = ["{$ipBase}.X_HW_VLAN", $serviceVlan, 'xsd:unsignedInt'];
            }
            
            // NAT
            $ipParams[] = ["{$ipBase}.NATEnabled", true, 'xsd:boolean'];
            
            $ipResult = $this->setParameterValues($deviceId, $ipParams);
            $results['ip_config'] = $ipResult;
            if (!$ipResult['success']) {
                $errors[] = 'Failed to configure IP connection: ' . ($ipResult['error'] ?? 'Unknown');
            }
            
            $wanName = "wan{$wanIndex}.{$connIndex}.ip{$connIndex}";
        }
        
        // Step 3: Set provisioning code (SmartOLT uses this to identify configuration source)
        $provCode = $config['provisioning_code'] ?? 'CRM.r' . strtoupper(substr($connectionType, 0, 3));
        $provResult = $this->setParameterValues($deviceId, [
            ['InternetGatewayDevice.DeviceInfo.ProvisioningCode', $provCode, 'xsd:string']
        ]);
        $results['provisioning_code'] = $provResult;
        
        // Step 4: Set default WAN connection
        $wanName = $wanName ?? "wan{$wanIndex}.{$connIndex}.ppp{$connIndex}";
        $defaultWanResult = $this->setParameterValues($deviceId, [
            ['InternetGatewayDevice.Layer3Forwarding.X_HW_WanDefaultWanName', $wanName, 'xsd:string']
        ]);
        $results['default_wan'] = $defaultWanResult;
        
        // Step 5: Configure policy routes to bind LAN ports and WiFi to WAN
        if (!empty($config['bind_ports']) || !empty($config['auto_bind_ports'])) {
            // Add policy route entry
            $addRouteResult = $this->addObject($deviceId, 'InternetGatewayDevice.Layer3Forwarding.X_HW_policy_route');
            $results['add_policy_route'] = $addRouteResult;
            
            // Get the new route index
            $routeIndex = 1;
            if ($addRouteResult['success'] && isset($addRouteResult['data']['InstanceNumber'])) {
                $routeIndex = $addRouteResult['data']['InstanceNumber'];
            }
            
            // Configure policy route
            $portNames = $config['bind_ports'] ?? 'LAN1,LAN2,LAN3,LAN4,SSID1';
            $routeParams = [
                ["InternetGatewayDevice.Layer3Forwarding.X_HW_policy_route.{$routeIndex}.PhyPortName", $portNames, 'xsd:string'],
                ["InternetGatewayDevice.Layer3Forwarding.X_HW_policy_route.{$routeIndex}.PolicyRouteType", 'SourcePhyPort', 'xsd:string'],
                ["InternetGatewayDevice.Layer3Forwarding.X_HW_policy_route.{$routeIndex}.WanName", $wanName, 'xsd:string']
            ];
            
            $routeConfigResult = $this->setParameterValues($deviceId, $routeParams);
            $results['policy_route_config'] = $routeConfigResult;
        }
        
        // Step 6: Authorization notification (like SmartOLT)
        $this->logTR069Action($deviceId, 'internet_wan_config', [
            'connection_type' => $connectionType,
            'service_vlan' => $serviceVlan,
            'wan_name' => $wanName,
            'success' => empty($errors)
        ]);
        
        return [
            'success' => empty($errors),
            'message' => empty($errors) ? 'Internet WAN configured successfully' : 'Some errors occurred',
            'errors' => $errors,
            'results' => $results,
            'wan_name' => $wanName
        ];
    }
    
    /**
     * Add an object instance via TR-069 (e.g., new WANConnectionDevice)
     */
    public function addObject(string $deviceId, string $objectPath): array {
        $encodedId = urlencode($deviceId);
        
        // GenieACS uses tasks to add objects
        $task = [
            'name' => 'addObject',
            'objectName' => $objectPath
        ];
        
        $result = $this->request('POST', "/devices/{$encodedId}/tasks", $task, ['timeout' => $this->timeout * 1000]);
        
        return $result;
    }
    
    /**
     * Delete an object instance via TR-069
     */
    public function deleteObject(string $deviceId, string $objectPath): array {
        $encodedId = urlencode($deviceId);
        
        $task = [
            'name' => 'deleteObject',
            'objectName' => $objectPath
        ];
        
        return $this->request('POST', "/devices/{$encodedId}/tasks", $task, ['timeout' => $this->timeout * 1000]);
    }
    
    /**
     * Log TR-069 action to database
     */
    private function logTR069Action(string $deviceId, string $action, array $data): void {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO huawei_logs (olt_id, onu_id, action, status, message, command_sent, command_response, created_at)
                VALUES (NULL, NULL, :action, :status, :message, :command, :response, CURRENT_TIMESTAMP)
            ");
            $stmt->execute([
                ':action' => 'tr069_' . $action,
                ':status' => ($data['success'] ?? true) ? 'success' : 'failed',
                ':message' => "TR-069 {$action} for device {$deviceId}",
                ':command' => json_encode($data),
                ':response' => json_encode($data)
            ]);
        } catch (\Exception $e) {
            // Silently fail logging
        }
    }
    
    /**
     * Get current WAN connection status
     */
    public function getWANStatus(string $deviceId): array {
        $result = $this->getDevice($deviceId);
        
        if (!$result['success'] || empty($result['data'])) {
            return ['success' => false, 'error' => 'Device not found'];
        }
        
        $device = $result['data'];
        $wanInfo = [];
        
        // Check WANConnectionDevice instances
        for ($i = 1; $i <= 4; $i++) {
            $wanBase = "InternetGatewayDevice.WANDevice.1.WANConnectionDevice.{$i}";
            
            // Check PPPoE connections
            for ($j = 1; $j <= 4; $j++) {
                $pppPath = "{$wanBase}.WANPPPConnection.{$j}";
                $enable = $this->extractValue($device, "{$pppPath}.Enable");
                $status = $this->extractValue($device, "{$pppPath}.ConnectionStatus");
                $name = $this->extractValue($device, "{$pppPath}.Name");
                $vlan = $this->extractValue($device, "{$pppPath}.X_HW_VLAN");
                $ip = $this->extractValue($device, "{$pppPath}.ExternalIPAddress");
                $username = $this->extractValue($device, "{$pppPath}.Username");
                
                if ($enable !== null || $name !== null) {
                    $wanInfo[] = [
                        'type' => 'PPPoE',
                        'path' => $pppPath,
                        'index' => "{$i}.{$j}",
                        'name' => $name,
                        'enabled' => $enable,
                        'status' => $status,
                        'vlan' => $vlan,
                        'ip' => $ip,
                        'username' => $username
                    ];
                }
            }
            
            // Check IP connections
            for ($j = 1; $j <= 4; $j++) {
                $ipPath = "{$wanBase}.WANIPConnection.{$j}";
                $enable = $this->extractValue($device, "{$ipPath}.Enable");
                $status = $this->extractValue($device, "{$ipPath}.ConnectionStatus");
                $name = $this->extractValue($device, "{$ipPath}.Name");
                $vlan = $this->extractValue($device, "{$ipPath}.X_HW_VLAN");
                $ip = $this->extractValue($device, "{$ipPath}.ExternalIPAddress");
                
                if ($enable !== null || $name !== null) {
                    $wanInfo[] = [
                        'type' => 'IPoE',
                        'path' => $ipPath,
                        'index' => "{$i}.{$j}",
                        'name' => $name,
                        'enabled' => $enable,
                        'status' => $status,
                        'vlan' => $vlan,
                        'ip' => $ip
                    ];
                }
            }
        }
        
        // Get default WAN
        $defaultWan = $this->extractValue($device, 'InternetGatewayDevice.Layer3Forwarding.X_HW_WanDefaultWanName');
        
        return [
            'success' => true,
            'wan_connections' => $wanInfo,
            'default_wan' => $defaultWan
        ];
    }
}
