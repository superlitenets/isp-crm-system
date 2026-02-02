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
        $this->timeout = (int)($settings['genieacs_timeout'] ?? 60);
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
    
    /**
     * Flatten nested GenieACS device structure to dot-notation
     * Converts {InternetGatewayDevice: {DeviceInfo: {Manufacturer: {_value: 'X'}}}}
     * to {'InternetGatewayDevice.DeviceInfo.Manufacturer': {_value: 'X'}}
     */
    private function flattenDevice(array $data, string $prefix = ''): array {
        $result = [];
        foreach ($data as $key => $value) {
            // Skip special keys at root level
            if ($prefix === '' && in_array($key, ['_id', '_deviceId', '_lastInform', '_lastBoot', '_registered', '_lastBootstrap', '_timestamp'])) {
                $result[$key] = $value;
                continue;
            }
            
            $newKey = $prefix === '' ? $key : $prefix . '.' . $key;
            
            if (is_array($value) && !empty($value)) {
                // Check if this has nested parameters (non-underscore keys with array values)
                $hasNestedParams = false;
                foreach ($value as $subKey => $subVal) {
                    if (is_array($subVal) && !str_starts_with($subKey, '_')) {
                        $hasNestedParams = true;
                        break;
                    }
                }
                
                if ($hasNestedParams) {
                    // Container object - recursively flatten
                    $flattened = $this->flattenDevice($value, $newKey);
                    $result = array_merge($result, $flattened);
                } elseif (isset($value['_value']) || isset($value['_type'])) {
                    // Leaf parameter - add directly
                    $result[$newKey] = $value;
                } else {
                    // Other array value
                    $result[$newKey] = $value;
                }
            } else {
                $result[$newKey] = $value;
            }
        }
        return $result;
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
    
    public function getDevice(string $deviceId, bool $flatten = true): array {
        // Use query parameter approach to avoid URL encoding issues with device IDs containing special chars
        $query = json_encode(['_id' => $deviceId]);
        $result = $this->request('GET', '/devices', null, ['query' => $query, 'limit' => 1]);
        
        if ($result['success'] && !empty($result['data']) && is_array($result['data'])) {
            $device = $result['data'][0];
            // Flatten nested GenieACS structure to dot-notation for easier access
            if ($flatten) {
                $device = $this->flattenDevice($device);
            }
            return ['success' => true, 'data' => $device];
        }
        
        // Fallback to direct path approach
        $encodedId = urlencode($deviceId);
        $directResult = $this->request('GET', "/devices/{$encodedId}");
        if ($directResult['success'] && isset($directResult['data'])) {
            if ($flatten) {
                $directResult['data'] = $this->flattenDevice($directResult['data']);
            }
            return $directResult;
        }
        
        return ['success' => false, 'error' => $result['error'] ?? $directResult['error'] ?? 'Device not found'];
    }
    
    public function getDeviceBySerial(string $serial): array {
        // Try exact _id match first (full GenieACS device ID format like "00259E-HG8546M-48575443F2D53A8B")
        $query = json_encode(['_id' => $serial]);
        $result = $this->request('GET', '/devices', null, ['query' => $query, 'limit' => 1]);
        
        if ($result['success'] && !empty($result['data'])) {
            return ['success' => true, 'device' => $result['data'][0]];
        }
        
        // Try exact serial number match
        $query = json_encode(['_deviceId._SerialNumber' => $serial]);
        $result = $this->request('GET', '/devices', null, ['query' => $query, 'limit' => 1]);
        
        if ($result['success'] && !empty($result['data'])) {
            return ['success' => true, 'device' => $result['data'][0]];
        }
        
        // Try _id containing serial (regex match for partial serial in device ID)
        $query = json_encode(['_id' => ['$regex' => $serial, '$options' => 'i']]);
        $result = $this->request('GET', '/devices', null, ['query' => $query, 'limit' => 1]);
        
        if ($result['success'] && !empty($result['data'])) {
            return ['success' => true, 'device' => $result['data'][0]];
        }
        
        // If not found and serial looks like OLT format (4 letter prefix + hex), convert to GenieACS format
        if (preg_match('/^[A-Z]{4}[0-9A-F]{8,16}$/i', $serial)) {
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
     * Find device by serial number - returns device array or null
     */
    public function findDeviceBySerial(string $serial): ?array {
        $result = $this->getDeviceBySerial($serial);
        return ($result['success'] && isset($result['device'])) ? $result['device'] : null;
    }
    
    /**
     * Send connection request to device to force immediate inform
     */
    public function sendConnectionRequest(string $serial): array {
        $device = $this->findDeviceBySerial($serial);
        if (!$device) {
            return ['success' => false, 'error' => 'Device not found in GenieACS'];
        }
        
        $deviceId = $device['_id'] ?? null;
        if (!$deviceId) {
            return ['success' => false, 'error' => 'Device ID not found'];
        }
        
        // Check ConnectionRequestURL reachability
        $connReqUrl = $this->getConnectionRequestURL($device);
        $reachable = !empty($connReqUrl) && $this->isConnectionRequestReachable($connReqUrl);
        
        $encodedId = urlencode($deviceId);
        $task = [
            'name' => 'setParameterValues',
            'parameterValues' => [
                ['InternetGatewayDevice.ManagementServer.ConnectionRequestUsername', '', 'xsd:string'],
                ['InternetGatewayDevice.ManagementServer.ConnectionRequestPassword', '', 'xsd:string'],
            ]
        ];
        
        // Try with connection_request for instant push
        $result = $this->request('POST', "/devices/{$encodedId}/tasks?connection_request&timeout=10000", $task);
        
        // If 401, fallback to queue mode
        $httpCode = $result['http_code'] ?? 0;
        $queued = false;
        if ($httpCode == 401 || (isset($result['error']) && strpos($result['error'], '401') !== false)) {
            error_log("[GenieACS] Got 401 on sendConnectionRequest, falling back to queue mode for {$serial}");
            $result = $this->request('POST', "/devices/{$encodedId}/tasks", $task);
            $queued = true;
        }
        
        return [
            'success' => $result['success'] ?? true,
            'message' => $queued 
                ? 'Auth clear queued - will execute on next device inform, then instant push will work'
                : 'Connection request sent and auth cleared - instant push now enabled',
            'connection_request_url' => $connReqUrl,
            'reachable' => $reachable,
            'queued' => $queued,
            'task_result' => $result
        ];
    }
    
    /**
     * Send fast connection request using device ID directly (no serial search)
     * Uses a 2-second timeout for quick response
     */
    public function sendFastConnectionRequest(string $deviceId): array {
        if (empty($deviceId)) {
            return ['success' => false, 'error' => 'Device ID required'];
        }
        
        $encodedId = urlencode($deviceId);
        
        // Just send connection request - no task, just wake up the device
        // Use very short timeout (2 seconds) to be instant
        $ch = curl_init();
        $url = rtrim($this->baseUrl, '/') . "/devices/{$encodedId}/tasks?connection_request&timeout=2000";
        
        // Send a simple getParameterNames task to trigger connection
        $task = [
            'name' => 'getParameterNames',
            'parameterPath' => 'InternetGatewayDevice.',
            'nextLevel' => false
        ];
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($task),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 3, // 3 second total timeout
            CURLOPT_CONNECTTIMEOUT => 2
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        // Any response means the request was sent (even timeout is OK - device may be waking up)
        $success = ($httpCode >= 200 && $httpCode < 500) || !empty($response);
        $queued = ($httpCode == 202 || $httpCode == 0); // 202 = queued, 0 = timeout (still sent)
        
        return [
            'success' => $success,
            'queued' => $queued,
            'http_code' => $httpCode,
            'error' => $error ?: null
        ];
    }
    
    /**
     * Get ConnectionRequestURL from device data
     */
    public function getConnectionRequestURL(array $device): ?string {
        // Try different paths for ConnectionRequestURL
        $paths = [
            'InternetGatewayDevice.ManagementServer.ConnectionRequestURL._value',
            'InternetGatewayDevice.ManagementServer.ConnectionRequestURL'
        ];
        
        foreach ($paths as $path) {
            $parts = explode('.', $path);
            $value = $device;
            foreach ($parts as $part) {
                if (isset($value[$part])) {
                    $value = $value[$part];
                } else {
                    $value = null;
                    break;
                }
            }
            if (!empty($value) && is_string($value)) {
                return $value;
            }
        }
        
        return null;
    }
    
    /**
     * Check if ConnectionRequestURL is reachable
     */
    public function isConnectionRequestReachable(?string $url): bool {
        if (empty($url)) {
            return false;
        }
        
        // Parse the URL to get host and port
        $parsed = parse_url($url);
        if (!$parsed || empty($parsed['host'])) {
            return false;
        }
        
        // Reject localhost/loopback
        $host = $parsed['host'];
        if (in_array($host, ['127.0.0.1', 'localhost', '::1'])) {
            return false;
        }
        
        // Quick socket connect test (2 second timeout)
        $port = $parsed['port'] ?? ($parsed['scheme'] === 'https' ? 443 : 80);
        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($host, $port, $errno, $errstr, 2);
        if ($socket) {
            fclose($socket);
            return true;
        }
        
        return false;
    }
    
    /**
     * Check device connection request capability
     */
    public function checkConnectionRequestCapability(string $serial): array {
        $device = $this->findDeviceBySerial($serial);
        if (!$device) {
            return ['success' => false, 'error' => 'Device not found'];
        }
        
        $connReqUrl = $this->getConnectionRequestURL($device);
        $reachable = !empty($connReqUrl) && $this->isConnectionRequestReachable($connReqUrl);
        
        $lastInform = $device['_lastInform'] ?? null;
        $lastInformTime = $lastInform ? strtotime($lastInform) : null;
        $informAge = $lastInformTime ? (time() - $lastInformTime) : null;
        
        return [
            'success' => true,
            'connection_request_url' => $connReqUrl,
            'reachable' => $reachable,
            'last_inform' => $lastInform,
            'inform_age_seconds' => $informAge,
            'instant_push_possible' => $reachable,
            'recommendation' => $reachable 
                ? 'Instant push is available' 
                : ($connReqUrl 
                    ? 'ConnectionRequestURL exists but is not reachable - check firewall/NAT' 
                    : 'ConnectionRequestURL is empty - device may be behind NAT or in bridge mode')
        ];
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
        // Use connection_request with timeout for instant execution
        return $this->request('POST', "/devices/{$encodedId}/tasks?connection_request&timeout=10000", [
            'name' => 'refreshObject',
            'objectName' => 'InternetGatewayDevice'
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
        // Use rawurlencode for path safety with special characters
        $encodedId = rawurlencode($deviceId);
        
        // GenieACS expects parameterValues as array of [name, value, type] arrays
        $formattedParams = [];
        foreach ($parameterValues as $key => $value) {
            if (is_array($value) && isset($value[0], $value[1])) {
                $formattedParams[] = $value;
            } else {
                if (is_bool($value)) {
                    $formattedParams[] = [$key, $value, 'xsd:boolean'];
                } elseif (is_int($value)) {
                    $formattedParams[] = [$key, $value, 'xsd:unsignedInt'];
                } else {
                    $formattedParams[] = [$key, (string)$value, 'xsd:string'];
                }
            }
        }
        
        // Use connection_request with 60s timeout for instant push
        $result = $this->request('POST', "/devices/{$encodedId}/tasks?connection_request&timeout=60000", [
            'name' => 'setParameterValues',
            'parameterValues' => $formattedParams
        ]);
        
        error_log("[GenieACS] setParameterValues to {$deviceId}: " . json_encode([
            'params_count' => count($formattedParams), 
            'success' => $result['success'] ?? false, 
            'http_code' => $result['http_code'] ?? 0
        ]));
        
        return $result;
    }
    
    /**
     * Push multiple tasks to device in sequence (addObject, deleteObject, etc)
     * Used for creating WAN objects before configuring them
     */
    public function pushTasks(string $deviceId, array $tasks): array {
        $encodedId = rawurlencode($deviceId);
        
        $results = [];
        foreach ($tasks as $task) {
            $result = $this->request('POST', "/devices/{$encodedId}/tasks", $task);
            $results[] = $result;
            
            // Log the task
            error_log("[GenieACS] pushTask {$task['name']} to {$deviceId}: " . json_encode([
                'objectName' => $task['objectName'] ?? '',
                'success' => $result['success'] ?? false
            ]));
        }
        
        return [
            'success' => true,
            'results' => $results
        ];
    }
    
    /**
     * Configure PPPoE WAN connection via TR-069
     */
    public function configurePPPoE(string $deviceId, array $config): array {
        $username = $config['username'] ?? '';
        $password = $config['password'] ?? '';
        $vlan = (int)($config['vlan'] ?? 0);
        
        // Standard TR-069 paths for PPPoE configuration
        // WANConnectionDevice.2 is typically the internet WAN (WANConnectionDevice.1 is often management)
        $basePath = 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.2.WANPPPConnection.1';
        
        $params = [
            "{$basePath}.Enable" => true,
            "{$basePath}.Username" => $username,
            "{$basePath}.Password" => $password,
            "{$basePath}.ConnectionType" => 'IP_Routed'
        ];
        
        // Add VLAN if specified (Huawei specific path)
        if ($vlan > 0) {
            $params['InternetGatewayDevice.WANDevice.1.WANConnectionDevice.2.X_HW_VLAN'] = $vlan;
        }
        
        return $this->setParameterValues($deviceId, $params);
    }
    
    /**
     * Configure DHCP/IPoE WAN connection via TR-069
     */
    public function configureDHCP(string $deviceId, array $config = []): array {
        $vlan = (int)($config['vlan'] ?? 0);
        $basePath = 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.2.WANIPConnection.1';
        
        $params = [
            "{$basePath}.Enable" => true,
            "{$basePath}.AddressingType" => 'DHCP',
            "{$basePath}.ConnectionType" => 'IP_Routed'
        ];
        
        if ($vlan > 0) {
            $params['InternetGatewayDevice.WANDevice.1.WANConnectionDevice.2.X_HW_VLAN'] = $vlan;
        }
        
        return $this->setParameterValues($deviceId, $params);
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
    
    /**
     * Configure WiFi settings for Huawei ONUs via TR-069
     * Supports both 2.4GHz (index 1) and 5GHz (index 5) bands
     * 
     * @param string $deviceId GenieACS device ID
     * @param array $config WiFi configuration:
     *   - ssid: SSID name
     *   - password: Pre-shared key
     *   - enabled: Enable/disable WiFi (default true)
     *   - channel: Channel number (0 = auto)
     *   - broadcast_ssid: Broadcast SSID (default true)
     *   - security_mode: WPA2-PSK, WPA-WPA2-PSK, etc. (default WPA2-PSK)
     *   - access_vlan: VLAN for WiFi traffic (0 = untagged)
     *   - band: '2.4ghz', '5ghz', or 'both' (default '2.4ghz')
     */
    public function configureWiFi(string $deviceId, array $config): array {
        $results = [];
        $errors = [];
        
        $ssid = $config['ssid'] ?? '';
        $password = $config['password'] ?? '';
        $enabled = $config['enabled'] ?? true;
        $channel = (int)($config['channel'] ?? 0);
        $broadcastSsid = $config['broadcast_ssid'] ?? true;
        $securityMode = $config['security_mode'] ?? 'WPA2-PSK';
        $accessVlan = (int)($config['access_vlan'] ?? 0);
        $band = $config['band'] ?? '2.4ghz';
        
        // Determine which interfaces to configure
        $interfaces = [];
        if ($band === '2.4ghz' || $band === 'both') {
            $interfaces['2.4GHz'] = 1;
        }
        if ($band === '5ghz' || $band === 'both') {
            $interfaces['5GHz'] = 5;
        }
        
        foreach ($interfaces as $bandName => $wlanIndex) {
            // Try Huawei direct path first (InternetGatewayDevice.WLANConfiguration)
            // Fall back to LANDevice path if needed
            $basePath = "InternetGatewayDevice.WLANConfiguration.{$wlanIndex}";
            $altBasePath = "InternetGatewayDevice.LANDevice.1.WLANConfiguration.{$wlanIndex}";
            
            $params = [];
            
            // Enable WiFi
            $params[] = ["{$basePath}.Enable", $enabled, 'xsd:boolean'];
            
            // Set SSID
            if (!empty($ssid)) {
                $params[] = ["{$basePath}.SSID", $ssid, 'xsd:string'];
            }
            
            // Broadcast SSID
            $params[] = ["{$basePath}.SSIDAdvertisementEnabled", $broadcastSsid, 'xsd:boolean'];
            
            // Security Mode
            $params[] = ["{$basePath}.BeaconType", $securityMode === 'WPA2-PSK' ? 'WPA' : 'Basic', 'xsd:string'];
            $params[] = ["{$basePath}.WPAEncryptionModes", 'AESEncryption', 'xsd:string'];
            $params[] = ["{$basePath}.WPAAuthenticationMode", 'PSKAuthentication', 'xsd:string'];
            
            // Pre-shared key (password) - validate first
            if (!empty($password)) {
                // HG8546M and similar devices require 8-63 chars
                if (strlen($password) < 8) {
                    $errors[] = "WiFi password must be at least 8 characters";
                } elseif (strlen($password) > 63) {
                    $errors[] = "WiFi password must be 63 characters or less";
                } else {
                    // Use PreSharedKey only - KeyPassphrase path often doesn't exist on Huawei devices
                    $params[] = ["{$basePath}.PreSharedKey.1.PreSharedKey", $password, 'xsd:string'];
                }
            }
            
            // Channel (0 = auto)
            if ($channel > 0) {
                $params[] = ["{$basePath}.Channel", $channel, 'xsd:unsignedInt'];
            }
            
            // Huawei-specific: Access VLAN
            if ($accessVlan > 0) {
                $params[] = ["{$basePath}.X_HW_AccessVLAN", $accessVlan, 'xsd:unsignedInt'];
            }
            
            $result = $this->setParameterValues($deviceId, $params);
            $results[$bandName] = $result;
            
            // If direct path fails, try LANDevice path
            if (!$result['success']) {
                $altParams = [];
                $altParams[] = ["{$altBasePath}.Enable", $enabled, 'xsd:boolean'];
                if (!empty($ssid)) {
                    $altParams[] = ["{$altBasePath}.SSID", $ssid, 'xsd:string'];
                }
                if (!empty($password) && strlen($password) >= 8 && strlen($password) <= 63) {
                    $altParams[] = ["{$altBasePath}.PreSharedKey.1.PreSharedKey", $password, 'xsd:string'];
                }
                if ($channel > 0) {
                    $altParams[] = ["{$altBasePath}.Channel", $channel, 'xsd:unsignedInt'];
                }
                
                $altResult = $this->setParameterValues($deviceId, $altParams);
                $results["{$bandName}_alt"] = $altResult;
                
                if (!$altResult['success']) {
                    $errors[] = "Failed to configure {$bandName} WiFi";
                }
            }
        }
        
        return [
            'success' => empty($errors),
            'message' => empty($errors) ? 'WiFi configured successfully' : 'Some errors occurred',
            'errors' => $errors,
            'results' => $results
        ];
    }
    
    /**
     * Legacy function - use configureWiFi instead
     */
    public function setWiFiSettings(string $deviceId, string $ssid, string $password, bool $enabled = true, int $channel = 0): array {
        return $this->configureWiFi($deviceId, [
            'ssid' => $ssid,
            'password' => $password,
            'enabled' => $enabled,
            'channel' => $channel,
            'band' => '2.4ghz'
        ]);
    }
    
    /**
     * Legacy function - use configureWiFi instead
     */
    public function setWiFi5GSettings(string $deviceId, string $ssid, string $password, bool $enabled = true): array {
        return $this->configureWiFi($deviceId, [
            'ssid' => $ssid,
            'password' => $password,
            'enabled' => $enabled,
            'band' => '5ghz'
        ]);
    }
    
    /**
     * Configure Layer 2 VLAN bridge for Guest WiFi on Huawei ONUs
     * This creates a bridge with VLAN tag and attaches WiFi interfaces to it
     * 
     * HG8546M (2.4GHz only):
     *   - WLAN 1-4: All 2.4GHz (1=main, 2-4=guest SSIDs)
     * 
     * HG8145V5 (dual-band):
     *   - WLAN 1-4: 2.4GHz (1=main, 2-4=guest)
     *   - WLAN 5: 5GHz main
     * 
     * @param string $deviceId GenieACS device ID
     * @param array $config Bridge configuration:
     *   - bridge_index: Bridge index (1-4, avoid 1 if used for WAN)
     *   - vlan_id: VLAN ID for the bridge (e.g., 903)
     *   - wlan_interfaces: Array of WLAN indices to attach [2, 6] for guest
     *   - ssid: SSID for guest network
     *   - password: Password for guest network
     *   - enabled: Enable the WiFi interfaces (default true)
     */
    public function configureLayer2WiFiBridge(string $deviceId, array $config): array {
        $results = [];
        $errors = [];
        
        $bridgeIndex = (int)($config['bridge_index'] ?? 2);
        $vlanId = (int)($config['vlan_id'] ?? 0);
        $wlanInterfaces = $config['wlan_interfaces'] ?? [2, 6]; // Default: guest interfaces
        $ssid = $config['ssid'] ?? '';
        $password = $config['password'] ?? '';
        $enabled = $config['enabled'] ?? true;
        $securityMode = $config['security_mode'] ?? 'WPA2-PSK';
        $broadcastSsid = $config['broadcast_ssid'] ?? true;
        
        if ($vlanId <= 0) {
            return ['success' => false, 'error' => 'VLAN ID is required for Layer 2 bridge'];
        }
        
        // Step 1: Create and configure the bridge with VLAN
        $bridgeParams = [
            ["InternetGatewayDevice.X_HW_Bridge.{$bridgeIndex}.Enable", true, 'xsd:boolean'],
            ["InternetGatewayDevice.X_HW_Bridge.{$bridgeIndex}.X_HW_VLAN", $vlanId, 'xsd:unsignedInt']
        ];
        
        $bridgeResult = $this->setParameterValues($deviceId, $bridgeParams);
        $results['bridge'] = $bridgeResult;
        
        if (!$bridgeResult['success']) {
            $errors[] = 'Failed to create bridge: ' . ($bridgeResult['error'] ?? 'Unknown');
        }
        
        // Step 2: Attach WiFi interfaces to the bridge and configure them
        foreach ($wlanInterfaces as $wlanIndex) {
            $wlanIndex = (int)$wlanIndex;
            $basePath = "InternetGatewayDevice.WLANConfiguration.{$wlanIndex}";
            
            $wlanParams = [
                // Attach to bridge
                ["{$basePath}.X_HW_Bridge", $bridgeIndex, 'xsd:unsignedInt'],
                // Enable interface
                ["{$basePath}.Enable", $enabled, 'xsd:boolean'],
                // Broadcast SSID
                ["{$basePath}.SSIDAdvertisementEnabled", $broadcastSsid, 'xsd:boolean']
            ];
            
            // Set SSID
            if (!empty($ssid)) {
                $wlanParams[] = ["{$basePath}.SSID", $ssid, 'xsd:string'];
            }
            
            // Set password
            if (!empty($password)) {
                $wlanParams[] = ["{$basePath}.PreSharedKey.1.PreSharedKey", $password, 'xsd:string'];
            }
            
            // Security mode
            $wlanParams[] = ["{$basePath}.BeaconType", 'WPA', 'xsd:string'];
            $wlanParams[] = ["{$basePath}.WPAEncryptionModes", 'AESEncryption', 'xsd:string'];
            $wlanParams[] = ["{$basePath}.WPAAuthenticationMode", 'PSKAuthentication', 'xsd:string'];
            
            $wlanResult = $this->setParameterValues($deviceId, $wlanParams);
            $results["wlan_{$wlanIndex}"] = $wlanResult;
            
            if (!$wlanResult['success']) {
                $errors[] = "Failed to configure WLAN {$wlanIndex}: " . ($wlanResult['error'] ?? 'Unknown');
            }
        }
        
        return [
            'success' => empty($errors),
            'message' => empty($errors) 
                ? "Layer 2 bridge configured: VLAN {$vlanId} on WLAN " . implode(',', $wlanInterfaces)
                : 'Some errors occurred',
            'errors' => $errors,
            'results' => $results
        ];
    }
    
    /**
     * Configure individual WiFi interface with optional Layer 2 bridging
     * 
     * @param string $deviceId GenieACS device ID
     * @param int $wlanIndex WLAN interface index (1, 2, 5, or 6)
     * @param array $config Interface configuration
     */
    public function configureWiFiInterface(string $deviceId, int $wlanIndex, array $config): array {
        $basePath = "InternetGatewayDevice.WLANConfiguration.{$wlanIndex}";
        $params = [];
        
        // Enable/disable
        $params[] = ["{$basePath}.Enable", $config['enabled'] ?? true, 'xsd:boolean'];
        
        // SSID
        if (!empty($config['ssid'])) {
            $params[] = ["{$basePath}.SSID", $config['ssid'], 'xsd:string'];
        }
        
        // Password
        if (!empty($config['password'])) {
            $params[] = ["{$basePath}.PreSharedKey.1.PreSharedKey", $config['password'], 'xsd:string'];
        }
        
        // Broadcast SSID
        $params[] = ["{$basePath}.SSIDAdvertisementEnabled", $config['broadcast_ssid'] ?? true, 'xsd:boolean'];
        
        // Security
        $params[] = ["{$basePath}.BeaconType", 'WPA', 'xsd:string'];
        $params[] = ["{$basePath}.WPAEncryptionModes", 'AESEncryption', 'xsd:string'];
        $params[] = ["{$basePath}.WPAAuthenticationMode", 'PSKAuthentication', 'xsd:string'];
        
        // Layer 2 bridge attachment (0 = no bridge/NAT mode)
        if (isset($config['bridge_index'])) {
            $bridgeIndex = (int)$config['bridge_index'];
            if ($bridgeIndex > 0) {
                $params[] = ["{$basePath}.X_HW_Bridge", $bridgeIndex, 'xsd:unsignedInt'];
            }
        }
        
        // Access VLAN (direct VLAN tagging without bridge)
        if (isset($config['access_vlan']) && (int)$config['access_vlan'] > 0) {
            $params[] = ["{$basePath}.X_HW_AccessVLAN", (int)$config['access_vlan'], 'xsd:unsignedInt'];
        }
        
        // Channel
        if (isset($config['channel']) && (int)$config['channel'] > 0) {
            $params[] = ["{$basePath}.Channel", (int)$config['channel'], 'xsd:unsignedInt'];
        }
        
        return $this->setParameterValues($deviceId, $params);
    }
    
    public function getWiFiSettings(string $deviceId): array {
        $result = $this->getDevice($deviceId);
        
        if (!$result['success']) {
            return ['success' => false, 'error' => 'Failed to fetch device: ' . ($result['error'] ?? 'Unknown')];
        }
        
        if (empty($result['data'])) {
            return ['success' => false, 'error' => 'Device data is empty'];
        }
        
        $device = $result['data'];
        $wifiData = [];
        
        // Helper to extract value from TR-069 format
        $getValue = function($obj, $key) {
            if (!isset($obj[$key])) return null;
            $val = $obj[$key];
            if (is_array($val) && isset($val['_value'])) return $val['_value'];
            return $val;
        };
        
        // Try multiple paths for WiFi data
        $wlanConfig = null;
        $basePaths = [
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration',
            'Device.WiFi.SSID',
            'InternetGatewayDevice.X_HW_WLANConfiguration',
        ];
        
        // Check InternetGatewayDevice.LANDevice.1.WLANConfiguration
        $lanDevice = $device['InternetGatewayDevice']['LANDevice']['1'] ?? null;
        if ($lanDevice && isset($lanDevice['WLANConfiguration'])) {
            $wlanConfig = $lanDevice['WLANConfiguration'];
        }
        
        // Try Device.WiFi.SSID (Device:2 data model)
        if (!$wlanConfig) {
            $wlanConfig = $device['Device']['WiFi']['SSID'] ?? null;
        }
        
        // If still not found, return helpful debug info
        if (!$wlanConfig) {
            $availableKeys = [];
            if (isset($device['InternetGatewayDevice'])) {
                $availableKeys = array_keys($device['InternetGatewayDevice']);
            }
            return [
                'success' => false, 
                'error' => 'WiFi configuration not found. Device may need a refresh from GenieACS.',
                'debug' => ['available_keys' => $availableKeys]
            ];
        }
        
        // For HG8546M and similar: Interfaces are typically 1,2,5,6 (2.4G, 2.4G Guest, 5G, 5G Guest)
        $potentialIndices = array_merge(array_keys($wlanConfig), range(1, 8));
        $potentialIndices = array_unique(array_filter($potentialIndices, 'is_numeric'));
        sort($potentialIndices);
        
        foreach ($potentialIndices as $idx) {
            $config = $wlanConfig[$idx] ?? null;
            if (!$config) continue;
            
            $basePath = "InternetGatewayDevice.LANDevice.1.WLANConfiguration.{$idx}";
            
            $ssid = $getValue($config, 'SSID');
            $enable = $getValue($config, 'Enable');
            $channel = $getValue($config, 'Channel');
            $standard = $getValue($config, 'Standard');
            $beaconType = $getValue($config, 'BeaconType');
            $vlanId = $getValue($config, 'X_HW_VLANID') ?? $getValue($config, 'VLANID');
            $vlanMode = $getValue($config, 'X_HW_VLANMode');
            $broadcast = $getValue($config, 'SSIDAdvertisementEnabled');
            $accessMode = $getValue($config, 'X_HW_WlanAccessMode') ?? $getValue($config, 'X_HW_AccessMode');
            $bindWan = $getValue($config, 'X_HW_BindWan');
            
            // Get password from nested PreSharedKey structure
            $password = null;
            if (isset($config['PreSharedKey']['1'])) {
                $password = $getValue($config['PreSharedKey']['1'], 'KeyPassphrase');
            }
            
            // Include interface even if some values are null
            $wifiData[] = ["{$basePath}.SSID", $ssid];
            $wifiData[] = ["{$basePath}.Enable", $enable];
            $wifiData[] = ["{$basePath}.Channel", $channel];
            $wifiData[] = ["{$basePath}.Standard", $standard];
            $wifiData[] = ["{$basePath}.BeaconType", $beaconType];
            $wifiData[] = ["{$basePath}.X_HW_VLANID", $vlanId];
            $wifiData[] = ["{$basePath}.X_HW_VLANMode", $vlanMode];
            $wifiData[] = ["{$basePath}.SSIDAdvertisementEnabled", $broadcast];
            $wifiData[] = ["{$basePath}.X_HW_WlanAccessMode", $accessMode];
            $wifiData[] = ["{$basePath}.X_HW_BindWan", $bindWan];
            if ($password !== null) {
                $wifiData[] = ["{$basePath}.PreSharedKey.1.KeyPassphrase", $password];
            }
        }
        
        if (empty($wifiData)) {
            return ['success' => false, 'error' => 'No WiFi interfaces found in device data'];
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
    
    /**
     * Get all device parameters organized by category (like SmartOLT Status view)
     * Returns editable parameter groups: PPP, LAN, WiFi, DHCP, etc.
     */
    public function getDeviceStatus(string $deviceId, bool $refresh = false): array {
        // If refresh requested, trigger a refresh task first
        if ($refresh) {
            $refreshResult = $this->refreshDevice($deviceId);
            if ($refreshResult['success'] ?? false) {
                // Wait for values to populate (refresh has 10s timeout, device may take a moment)
                sleep(2);
            }
        }
        
        $result = $this->getDevice($deviceId);
        if (!($result['success'] ?? false) || empty($result['data'])) {
            return ['success' => false, 'error' => 'Device not found'];
        }
        
        $device = $result['data'];
        $categories = [];
        
        // Check if device has actual values (not just parameter structure)
        $hasValues = false;
        foreach ($device as $key => $val) {
            if (is_array($val) && isset($val['_value'])) {
                $hasValues = true;
                break;
            }
        }
        
        // If no values and not already refreshing, suggest refresh
        if (!$hasValues && !$refresh) {
            return [
                'success' => false,
                'error' => 'Device parameters not yet fetched. Click Refresh to load data from device.',
                'needs_refresh' => true
            ];
        }
        
        // Helper to extract parameter value - checks for _value in array or direct value
        $getValue = function($path) use ($device) {
            if (!isset($device[$path])) return null;
            $param = $device[$path];
            if (is_array($param)) {
                return $param['_value'] ?? null;
            }
            return $param;
        };
        
        // Helper to check if path exists with a value
        $hasPath = function($path) use ($device) {
            if (!isset($device[$path])) return false;
            $param = $device[$path];
            if (is_array($param)) {
                return isset($param['_value']) || isset($param['_type']);
            }
            return true;
        };
        
        // Helper to check if any path with prefix exists
        $hasPrefix = function($prefix) use ($device) {
            foreach ($device as $key => $val) {
                if (str_starts_with($key, $prefix)) {
                    if (is_array($val) && isset($val['_value'])) return true;
                }
            }
            return false;
        };
        
        // Device Info
        $categories['device_info'] = [
            'label' => 'Device Info',
            'icon' => 'bi-info-circle',
            'editable' => false,
            'params' => [
                ['path' => 'InternetGatewayDevice.DeviceInfo.Manufacturer', 'label' => 'Manufacturer', 'value' => $getValue('InternetGatewayDevice.DeviceInfo.Manufacturer')],
                ['path' => 'InternetGatewayDevice.DeviceInfo.ModelName', 'label' => 'Model', 'value' => $getValue('InternetGatewayDevice.DeviceInfo.ModelName')],
                ['path' => 'InternetGatewayDevice.DeviceInfo.ProductClass', 'label' => 'Product Class', 'value' => $getValue('InternetGatewayDevice.DeviceInfo.ProductClass')],
                ['path' => 'InternetGatewayDevice.DeviceInfo.SerialNumber', 'label' => 'Serial Number', 'value' => $getValue('InternetGatewayDevice.DeviceInfo.SerialNumber')],
                ['path' => 'InternetGatewayDevice.DeviceInfo.HardwareVersion', 'label' => 'Hardware Version', 'value' => $getValue('InternetGatewayDevice.DeviceInfo.HardwareVersion')],
                ['path' => 'InternetGatewayDevice.DeviceInfo.SoftwareVersion', 'label' => 'Firmware', 'value' => $getValue('InternetGatewayDevice.DeviceInfo.SoftwareVersion')],
                ['path' => 'InternetGatewayDevice.DeviceInfo.UpTime', 'label' => 'Uptime (sec)', 'value' => $getValue('InternetGatewayDevice.DeviceInfo.UpTime')],
                ['path' => 'InternetGatewayDevice.DeviceInfo.MemoryStatus.Total', 'label' => 'Total Memory', 'value' => $getValue('InternetGatewayDevice.DeviceInfo.MemoryStatus.Total')],
                ['path' => 'InternetGatewayDevice.DeviceInfo.MemoryStatus.Free', 'label' => 'Free Memory', 'value' => $getValue('InternetGatewayDevice.DeviceInfo.MemoryStatus.Free')],
            ]
        ];
        
        // Dynamically detect PPP interfaces (like SmartOLT "PPP Interface 2.1")
        for ($wanDev = 1; $wanDev <= 2; $wanDev++) {
            for ($connDev = 1; $connDev <= 4; $connDev++) {
                $pppBase = "InternetGatewayDevice.WANDevice.{$wanDev}.WANConnectionDevice.{$connDev}.WANPPPConnection.1";
                if ($hasPath("{$pppBase}.Enable") || $hasPath("{$pppBase}.Username")) {
                    $categories["ppp_interface_{$wanDev}_{$connDev}"] = [
                        'label' => "PPP Interface {$wanDev}.{$connDev}",
                        'icon' => 'bi-globe',
                        'editable' => true,
                        'params' => [
                            ['path' => "{$pppBase}.Enable", 'label' => 'Enable', 'value' => $getValue("{$pppBase}.Enable"), 'type' => 'boolean'],
                            ['path' => "{$pppBase}.Name", 'label' => 'Name', 'value' => $getValue("{$pppBase}.Name"), 'type' => 'string'],
                            ['path' => "{$pppBase}.Username", 'label' => 'Username', 'value' => $getValue("{$pppBase}.Username"), 'type' => 'string'],
                            ['path' => "{$pppBase}.Password", 'label' => 'Password', 'value' => $getValue("{$pppBase}.Password"), 'type' => 'password'],
                            ['path' => "{$pppBase}.NATEnabled", 'label' => 'NAT Enabled', 'value' => $getValue("{$pppBase}.NATEnabled"), 'type' => 'boolean'],
                            ['path' => "{$pppBase}.ConnectionType", 'label' => 'Connection Type', 'value' => $getValue("{$pppBase}.ConnectionType"), 'type' => 'string'],
                            ['path' => "{$pppBase}.ConnectionTrigger", 'label' => 'Connection Trigger', 'value' => $getValue("{$pppBase}.ConnectionTrigger"), 'type' => 'string'],
                            ['path' => "{$pppBase}.X_HW_VLAN", 'label' => 'VLAN', 'value' => $getValue("{$pppBase}.X_HW_VLAN"), 'type' => 'number'],
                            ['path' => "{$pppBase}.X_HW_SERVICELIST", 'label' => 'Service List', 'value' => $getValue("{$pppBase}.X_HW_SERVICELIST"), 'type' => 'string'],
                            ['path' => "{$pppBase}.ConnectionStatus", 'label' => 'Status', 'value' => $getValue("{$pppBase}.ConnectionStatus"), 'type' => 'readonly'],
                            ['path' => "{$pppBase}.ExternalIPAddress", 'label' => 'External IP', 'value' => $getValue("{$pppBase}.ExternalIPAddress"), 'type' => 'readonly'],
                            ['path' => "{$pppBase}.RemoteIPAddress", 'label' => 'Remote IP', 'value' => $getValue("{$pppBase}.RemoteIPAddress"), 'type' => 'readonly'],
                            ['path' => "{$pppBase}.DNSServers", 'label' => 'DNS Servers', 'value' => $getValue("{$pppBase}.DNSServers"), 'type' => 'readonly'],
                            ['path' => "{$pppBase}.Uptime", 'label' => 'Uptime', 'value' => $getValue("{$pppBase}.Uptime"), 'type' => 'readonly'],
                        ]
                    ];
                }
            }
        }
        
        // Port Forwarding
        $portFwdParams = [];
        for ($i = 1; $i <= 10; $i++) {
            $pfBase = "InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.PortMapping.{$i}";
            if ($hasPath("{$pfBase}.PortMappingEnabled")) {
                $portFwdParams[] = ['path' => "{$pfBase}.PortMappingEnabled", 'label' => "Rule {$i} Enable", 'value' => $getValue("{$pfBase}.PortMappingEnabled"), 'type' => 'boolean'];
                $portFwdParams[] = ['path' => "{$pfBase}.ExternalPort", 'label' => "Rule {$i} External Port", 'value' => $getValue("{$pfBase}.ExternalPort"), 'type' => 'number'];
                $portFwdParams[] = ['path' => "{$pfBase}.InternalPort", 'label' => "Rule {$i} Internal Port", 'value' => $getValue("{$pfBase}.InternalPort"), 'type' => 'number'];
                $portFwdParams[] = ['path' => "{$pfBase}.InternalClient", 'label' => "Rule {$i} Internal IP", 'value' => $getValue("{$pfBase}.InternalClient"), 'type' => 'string'];
                $portFwdParams[] = ['path' => "{$pfBase}.PortMappingProtocol", 'label' => "Rule {$i} Protocol", 'value' => $getValue("{$pfBase}.PortMappingProtocol"), 'type' => 'string'];
            }
        }
        if (!empty($portFwdParams)) {
            $categories['port_forward'] = [
                'label' => 'Port Forward',
                'icon' => 'bi-arrow-left-right',
                'editable' => true,
                'params' => $portFwdParams
            ];
        }
        
        // Dynamically detect IP interfaces
        for ($wanDev = 1; $wanDev <= 2; $wanDev++) {
            for ($connDev = 1; $connDev <= 4; $connDev++) {
                $ipBase = "InternetGatewayDevice.WANDevice.{$wanDev}.WANConnectionDevice.{$connDev}.WANIPConnection.1";
                if ($hasPrefix($ipBase) || $hasPath("{$ipBase}.Enable") || $hasPath("{$ipBase}.ConnectionType")) {
                    $categories["ip_interface_{$wanDev}_{$connDev}"] = [
                        'label' => "IP Interface {$wanDev}.{$connDev}",
                        'icon' => 'bi-ethernet',
                        'editable' => true,
                        'params' => [
                            ['path' => "{$ipBase}.Enable", 'label' => 'Enable', 'value' => $getValue("{$ipBase}.Enable"), 'type' => 'boolean'],
                            ['path' => "{$ipBase}.Name", 'label' => 'Name', 'value' => $getValue("{$ipBase}.Name"), 'type' => 'string'],
                            ['path' => "{$ipBase}.ConnectionType", 'label' => 'Connection Type', 'value' => $getValue("{$ipBase}.ConnectionType"), 'type' => 'string'],
                            ['path' => "{$ipBase}.AddressingType", 'label' => 'Addressing', 'value' => $getValue("{$ipBase}.AddressingType"), 'type' => 'string'],
                            ['path' => "{$ipBase}.NATEnabled", 'label' => 'NAT Enabled', 'value' => $getValue("{$ipBase}.NATEnabled"), 'type' => 'boolean'],
                            ['path' => "{$ipBase}.X_HW_VLAN", 'label' => 'VLAN', 'value' => $getValue("{$ipBase}.X_HW_VLAN"), 'type' => 'number'],
                            ['path' => "{$ipBase}.ExternalIPAddress", 'label' => 'External IP', 'value' => $getValue("{$ipBase}.ExternalIPAddress"), 'type' => 'readonly'],
                            ['path' => "{$ipBase}.SubnetMask", 'label' => 'Subnet Mask', 'value' => $getValue("{$ipBase}.SubnetMask"), 'type' => 'readonly'],
                            ['path' => "{$ipBase}.DefaultGateway", 'label' => 'Gateway', 'value' => $getValue("{$ipBase}.DefaultGateway"), 'type' => 'readonly'],
                            ['path' => "{$ipBase}.DNSServers", 'label' => 'DNS Servers', 'value' => $getValue("{$ipBase}.DNSServers"), 'type' => 'readonly'],
                        ]
                    ];
                }
            }
        }
        
        // LAN DHCP Server
        $dhcpBase = 'InternetGatewayDevice.LANDevice.1.LANHostConfigManagement';
        $categories['lan_dhcp'] = [
            'label' => 'LAN DHCP Server',
            'icon' => 'bi-hdd-network',
            'editable' => true,
            'params' => [
                ['path' => "{$dhcpBase}.DHCPServerEnable", 'label' => 'DHCP Enabled', 'value' => $getValue("{$dhcpBase}.DHCPServerEnable"), 'type' => 'boolean'],
                ['path' => "{$dhcpBase}.MinAddress", 'label' => 'Min Address', 'value' => $getValue("{$dhcpBase}.MinAddress"), 'type' => 'string'],
                ['path' => "{$dhcpBase}.MaxAddress", 'label' => 'Max Address', 'value' => $getValue("{$dhcpBase}.MaxAddress"), 'type' => 'string'],
                ['path' => "{$dhcpBase}.SubnetMask", 'label' => 'Subnet Mask', 'value' => $getValue("{$dhcpBase}.SubnetMask"), 'type' => 'string'],
                ['path' => "{$dhcpBase}.IPRouters", 'label' => 'Gateway', 'value' => $getValue("{$dhcpBase}.IPRouters"), 'type' => 'string'],
                ['path' => "{$dhcpBase}.DNSServers", 'label' => 'DNS Servers', 'value' => $getValue("{$dhcpBase}.DNSServers"), 'type' => 'string'],
                ['path' => "{$dhcpBase}.DHCPLeaseTime", 'label' => 'Lease Time', 'value' => $getValue("{$dhcpBase}.DHCPLeaseTime"), 'type' => 'number'],
                ['path' => "{$dhcpBase}.IPInterfaceNumberOfEntries", 'label' => 'IP Interfaces', 'value' => $getValue("{$dhcpBase}.IPInterfaceNumberOfEntries"), 'type' => 'readonly'],
            ]
        ];
        
        // LAN Ports (Ethernet interfaces)
        $lanParams = [];
        for ($i = 1; $i <= 4; $i++) {
            $lanBase = "InternetGatewayDevice.LANDevice.1.LANEthernetInterfaceConfig.{$i}";
            if ($hasPath("{$lanBase}.Enable") || $hasPath("{$lanBase}.Status")) {
                $lanParams[] = ['path' => "{$lanBase}.Enable", 'label' => "Port {$i} Enable", 'value' => $getValue("{$lanBase}.Enable"), 'type' => 'boolean'];
                $lanParams[] = ['path' => "{$lanBase}.X_HW_L3Enable", 'label' => "Port {$i} L3 Enable", 'value' => $getValue("{$lanBase}.X_HW_L3Enable"), 'type' => 'boolean'];
                $lanParams[] = ['path' => "{$lanBase}.MaxBitRate", 'label' => "Port {$i} Speed", 'value' => $getValue("{$lanBase}.MaxBitRate"), 'type' => 'string'];
                $lanParams[] = ['path' => "{$lanBase}.DuplexMode", 'label' => "Port {$i} Duplex", 'value' => $getValue("{$lanBase}.DuplexMode"), 'type' => 'string'];
                $lanParams[] = ['path' => "{$lanBase}.Status", 'label' => "Port {$i} Status", 'value' => $getValue("{$lanBase}.Status"), 'type' => 'readonly'];
                $lanParams[] = ['path' => "{$lanBase}.MACAddress", 'label' => "Port {$i} MAC", 'value' => $getValue("{$lanBase}.MACAddress"), 'type' => 'readonly'];
            }
        }
        if (!empty($lanParams)) {
            $categories['lan_ports'] = [
                'label' => 'LAN Ports',
                'icon' => 'bi-plug',
                'editable' => true,
                'params' => $lanParams
            ];
        }
        
        // LAN Counters (Statistics)
        $lanCounterParams = [];
        for ($i = 1; $i <= 4; $i++) {
            $statsBase = "InternetGatewayDevice.LANDevice.1.LANEthernetInterfaceConfig.{$i}.Stats";
            if ($hasPath("{$statsBase}.BytesSent")) {
                $lanCounterParams[] = ['path' => "{$statsBase}.BytesSent", 'label' => "Port {$i} Bytes Sent", 'value' => $getValue("{$statsBase}.BytesSent"), 'type' => 'readonly'];
                $lanCounterParams[] = ['path' => "{$statsBase}.BytesReceived", 'label' => "Port {$i} Bytes Received", 'value' => $getValue("{$statsBase}.BytesReceived"), 'type' => 'readonly'];
                $lanCounterParams[] = ['path' => "{$statsBase}.PacketsSent", 'label' => "Port {$i} Packets Sent", 'value' => $getValue("{$statsBase}.PacketsSent"), 'type' => 'readonly'];
                $lanCounterParams[] = ['path' => "{$statsBase}.PacketsReceived", 'label' => "Port {$i} Packets Received", 'value' => $getValue("{$statsBase}.PacketsReceived"), 'type' => 'readonly'];
                $lanCounterParams[] = ['path' => "{$statsBase}.ErrorsSent", 'label' => "Port {$i} Errors Sent", 'value' => $getValue("{$statsBase}.ErrorsSent"), 'type' => 'readonly'];
                $lanCounterParams[] = ['path' => "{$statsBase}.ErrorsReceived", 'label' => "Port {$i} Errors Received", 'value' => $getValue("{$statsBase}.ErrorsReceived"), 'type' => 'readonly'];
            }
        }
        if (!empty($lanCounterParams)) {
            $categories['lan_counters'] = [
                'label' => 'LAN Counters',
                'icon' => 'bi-bar-chart',
                'editable' => false,
                'params' => $lanCounterParams
            ];
        }
        
        // Wireless LAN - Dynamically detect all WLANConfiguration entries
        $wlanParams = [];
        $lanDevice = $device['InternetGatewayDevice']['LANDevice']['1'] ?? [];
        $wlanConfigs = $lanDevice['WLANConfiguration'] ?? [];
        
        // Also check direct path (some devices use InternetGatewayDevice.WLANConfiguration)
        if (empty($wlanConfigs)) {
            $wlanConfigs = $device['InternetGatewayDevice']['WLANConfiguration'] ?? [];
        }
        
        foreach ($wlanConfigs as $idx => $wlanData) {
            if (!is_numeric($idx)) continue;
            
            // Determine base path
            $wlanBase = isset($lanDevice['WLANConfiguration'][$idx]) 
                ? "InternetGatewayDevice.LANDevice.1.WLANConfiguration.{$idx}"
                : "InternetGatewayDevice.WLANConfiguration.{$idx}";
            
            // Check if this WLAN exists - show all SSIDs including disabled ones
            $ssid = $getValue("{$wlanBase}.SSID");
            $enable = $getValue("{$wlanBase}.Enable");
            
            // Skip only if no data exists at all for this index
            if ($ssid === null && $enable === null) continue;
            
            // Determine band based on index or standard
            $standard = $getValue("{$wlanBase}.Standard") ?? '';
            $band = '';
            if (strpos($standard, 'ac') !== false || strpos($standard, 'ax') !== false || $idx >= 5) {
                $band = '5GHz';
            } else {
                $band = '2.4GHz';
            }
            
            // Get status for display
            $isEnabled = $enable === true || $enable === 'true' || $enable === 1 || $enable === '1';
            $statusLabel = $isEnabled ? '' : ' (Disabled)';
            
            $wlanParams[] = [
                'idx' => $idx,
                'band' => $band,
                'ssid' => $ssid,
                'enabled' => $isEnabled,
                'params' => [
                    ['path' => "{$wlanBase}.Enable", 'label' => "SSID {$idx} Enable{$statusLabel}", 'value' => $enable, 'type' => 'boolean'],
                    ['path' => "{$wlanBase}.SSID", 'label' => "SSID {$idx} Name", 'value' => $ssid, 'type' => 'string'],
                    ['path' => "{$wlanBase}.PreSharedKey.1.KeyPassphrase", 'label' => "SSID {$idx} Password", 'value' => $getValue("{$wlanBase}.PreSharedKey.1.KeyPassphrase"), 'type' => 'password'],
                    ['path' => "{$wlanBase}.X_HW_VLANID", 'label' => "SSID {$idx} VLAN ID", 'value' => $getValue("{$wlanBase}.X_HW_VLANID"), 'type' => 'number'],
                    ['path' => "{$wlanBase}.X_HW_VLANMode", 'label' => "SSID {$idx} VLAN Mode", 'value' => $getValue("{$wlanBase}.X_HW_VLANMode"), 'type' => 'string'],
                    ['path' => "{$wlanBase}.Channel", 'label' => "SSID {$idx} Channel", 'value' => $getValue("{$wlanBase}.Channel"), 'type' => 'number'],
                    ['path' => "{$wlanBase}.X_HW_ChannelWidth", 'label' => "SSID {$idx} Channel Width", 'value' => $getValue("{$wlanBase}.X_HW_ChannelWidth"), 'type' => 'number'],
                    ['path' => "{$wlanBase}.SSIDAdvertisementEnabled", 'label' => "SSID {$idx} Broadcast", 'value' => $getValue("{$wlanBase}.SSIDAdvertisementEnabled"), 'type' => 'boolean'],
                    ['path' => "{$wlanBase}.BeaconType", 'label' => "SSID {$idx} Security", 'value' => $getValue("{$wlanBase}.BeaconType"), 'type' => 'string'],
                    ['path' => "{$wlanBase}.WPAEncryptionModes", 'label' => "SSID {$idx} Encryption", 'value' => $getValue("{$wlanBase}.WPAEncryptionModes"), 'type' => 'string'],
                    ['path' => "{$wlanBase}.Standard", 'label' => "SSID {$idx} Standard ({$band})", 'value' => $standard, 'type' => 'readonly'],
                    ['path' => "{$wlanBase}.TransmitPower", 'label' => "SSID {$idx} TX Power", 'value' => $getValue("{$wlanBase}.TransmitPower"), 'type' => 'number'],
                    ['path' => "{$wlanBase}.TotalAssociations", 'label' => "SSID {$idx} Clients", 'value' => $getValue("{$wlanBase}.TotalAssociations"), 'type' => 'readonly'],
                    ['path' => "{$wlanBase}.BSSID", 'label' => "SSID {$idx} BSSID", 'value' => $getValue("{$wlanBase}.BSSID"), 'type' => 'readonly'],
                    ['path' => "{$wlanBase}.X_HW_WMMEnable", 'label' => "SSID {$idx} WMM", 'value' => $getValue("{$wlanBase}.X_HW_WMMEnable"), 'type' => 'boolean'],
                    ['path' => "{$wlanBase}.X_HW_APModuleEnable", 'label' => "SSID {$idx} AP Isolation", 'value' => $getValue("{$wlanBase}.X_HW_APModuleEnable"), 'type' => 'boolean'],
                ]
            ];
        }
        
        // Group by band and create categories
        if (!empty($wlanParams)) {
            // Combine all wireless params into single category
            $allWlanParams = [];
            foreach ($wlanParams as $wlan) {
                // Add a separator/header for each SSID
                foreach ($wlan['params'] as $param) {
                    if ($param['value'] !== null) {
                        $allWlanParams[] = $param;
                    }
                }
            }
            
            if (!empty($allWlanParams)) {
                $categories['wireless_lan'] = [
                    'label' => 'Wireless LAN',
                    'icon' => 'bi-wifi',
                    'editable' => true,
                    'params' => $allWlanParams
                ];
            }
        }
        
        // WLAN Counters
        $wlanCounterParams = [];
        foreach ([1 => '2.4GHz', 5 => '5GHz'] as $idx => $band) {
            $statsBase = "InternetGatewayDevice.LANDevice.1.WLANConfiguration.{$idx}.Stats";
            if ($hasPath("{$statsBase}.BytesSent")) {
                $wlanCounterParams[] = ['path' => "{$statsBase}.BytesSent", 'label' => "{$band} Bytes Sent", 'value' => $getValue("{$statsBase}.BytesSent"), 'type' => 'readonly'];
                $wlanCounterParams[] = ['path' => "{$statsBase}.BytesReceived", 'label' => "{$band} Bytes Received", 'value' => $getValue("{$statsBase}.BytesReceived"), 'type' => 'readonly'];
                $wlanCounterParams[] = ['path' => "{$statsBase}.PacketsSent", 'label' => "{$band} Packets Sent", 'value' => $getValue("{$statsBase}.PacketsSent"), 'type' => 'readonly'];
                $wlanCounterParams[] = ['path' => "{$statsBase}.PacketsReceived", 'label' => "{$band} Packets Received", 'value' => $getValue("{$statsBase}.PacketsReceived"), 'type' => 'readonly'];
            }
        }
        if (!empty($wlanCounterParams)) {
            $categories['wlan_counters'] = [
                'label' => 'WLAN Counters',
                'icon' => 'bi-bar-chart',
                'editable' => false,
                'params' => $wlanCounterParams
            ];
        }
        
        // Connected Hosts (DHCP Leases / LAN Hosts)
        $hostParams = [];
        for ($i = 1; $i <= 32; $i++) {
            $hostBase = "InternetGatewayDevice.LANDevice.1.Hosts.Host.{$i}";
            if ($hasPath("{$hostBase}.IPAddress")) {
                $hostParams[] = ['path' => "{$hostBase}.HostName", 'label' => "Host {$i} Name", 'value' => $getValue("{$hostBase}.HostName"), 'type' => 'readonly'];
                $hostParams[] = ['path' => "{$hostBase}.IPAddress", 'label' => "Host {$i} IP", 'value' => $getValue("{$hostBase}.IPAddress"), 'type' => 'readonly'];
                $hostParams[] = ['path' => "{$hostBase}.MACAddress", 'label' => "Host {$i} MAC", 'value' => $getValue("{$hostBase}.MACAddress"), 'type' => 'readonly'];
                $hostParams[] = ['path' => "{$hostBase}.InterfaceType", 'label' => "Host {$i} Interface", 'value' => $getValue("{$hostBase}.InterfaceType"), 'type' => 'readonly'];
                $hostParams[] = ['path' => "{$hostBase}.Active", 'label' => "Host {$i} Active", 'value' => $getValue("{$hostBase}.Active"), 'type' => 'readonly'];
            }
        }
        if (!empty($hostParams)) {
            $categories['hosts'] = [
                'label' => 'Hosts',
                'icon' => 'bi-pc-display',
                'editable' => false,
                'params' => $hostParams
            ];
        }
        
        // Security/Firewall & User Management
        $fwBase = 'InternetGatewayDevice.X_HW_Security.Firewall';
        $userBase = 'InternetGatewayDevice.UserInterface';
        $secParams = [
            // Firewall Settings
            ['path' => "{$fwBase}.Enable", 'label' => 'Firewall Enable', 'value' => $getValue("{$fwBase}.Enable"), 'type' => 'boolean'],
            ['path' => "{$fwBase}.Level", 'label' => 'Firewall Level', 'value' => $getValue("{$fwBase}.Level"), 'type' => 'string'],
            ['path' => 'InternetGatewayDevice.X_HW_Security.AntiDDoS.Enable', 'label' => 'AntiDDoS Enable', 'value' => $getValue('InternetGatewayDevice.X_HW_Security.AntiDDoS.Enable'), 'type' => 'boolean'],
            ['path' => 'InternetGatewayDevice.X_HW_Security.ARP.Enable', 'label' => 'ARP Protection', 'value' => $getValue('InternetGatewayDevice.X_HW_Security.ARP.Enable'), 'type' => 'boolean'],
            // Remote Access
            ['path' => 'InternetGatewayDevice.X_HW_Security.AclServices.HTTPLanEnable', 'label' => 'HTTP LAN Access', 'value' => $getValue('InternetGatewayDevice.X_HW_Security.AclServices.HTTPLanEnable'), 'type' => 'boolean'],
            ['path' => 'InternetGatewayDevice.X_HW_Security.AclServices.HTTPWanEnable', 'label' => 'HTTP WAN Access', 'value' => $getValue('InternetGatewayDevice.X_HW_Security.AclServices.HTTPWanEnable'), 'type' => 'boolean'],
            ['path' => 'InternetGatewayDevice.X_HW_Security.AclServices.TelnetLanEnable', 'label' => 'Telnet LAN Access', 'value' => $getValue('InternetGatewayDevice.X_HW_Security.AclServices.TelnetLanEnable'), 'type' => 'boolean'],
            ['path' => 'InternetGatewayDevice.X_HW_Security.AclServices.SSHLanEnable', 'label' => 'SSH LAN Access', 'value' => $getValue('InternetGatewayDevice.X_HW_Security.AclServices.SSHLanEnable'), 'type' => 'boolean'],
        ];
        
        // User Management - Web Users (Admin, User, etc.)
        for ($u = 1; $u <= 4; $u++) {
            $userPath = "{$userBase}.X_HW_WebUserInfo.{$u}";
            if ($hasPath("{$userPath}.UserName") || $getValue("{$userPath}.UserName") !== null) {
                $userName = $getValue("{$userPath}.UserName");
                $userLabel = $userName ? ucfirst($userName) : "User {$u}";
                $secParams[] = ['path' => "{$userPath}.UserName", 'label' => "{$userLabel} Username", 'value' => $userName, 'type' => 'string'];
                $secParams[] = ['path' => "{$userPath}.Password", 'label' => "{$userLabel} Password", 'value' => $getValue("{$userPath}.Password"), 'type' => 'password'];
                $secParams[] = ['path' => "{$userPath}.UserLevel", 'label' => "{$userLabel} Level", 'value' => $getValue("{$userPath}.UserLevel"), 'type' => 'string'];
            }
        }
        
        $categories['security'] = [
            'label' => 'Security & Users',
            'icon' => 'bi-shield-check',
            'editable' => true,
            'params' => $secParams
        ];
        
        // Voice Lines (VoIP)
        $voiceParams = [];
        for ($i = 1; $i <= 2; $i++) {
            $voipBase = "InternetGatewayDevice.Services.VoiceService.1.VoiceProfile.1.Line.{$i}";
            if ($hasPath("{$voipBase}.Enable") || $hasPath("{$voipBase}.Status")) {
                $voiceParams[] = ['path' => "{$voipBase}.Enable", 'label' => "Line {$i} Enable", 'value' => $getValue("{$voipBase}.Enable"), 'type' => 'boolean'];
                $voiceParams[] = ['path' => "{$voipBase}.SIP.AuthUserName", 'label' => "Line {$i} SIP User", 'value' => $getValue("{$voipBase}.SIP.AuthUserName"), 'type' => 'string'];
                $voiceParams[] = ['path' => "{$voipBase}.SIP.AuthPassword", 'label' => "Line {$i} SIP Password", 'value' => $getValue("{$voipBase}.SIP.AuthPassword"), 'type' => 'password'];
                $voiceParams[] = ['path' => "{$voipBase}.Status", 'label' => "Line {$i} Status", 'value' => $getValue("{$voipBase}.Status"), 'type' => 'readonly'];
            }
        }
        if (!empty($voiceParams)) {
            $categories['voice_lines'] = [
                'label' => 'Voice Lines',
                'icon' => 'bi-telephone',
                'editable' => true,
                'params' => $voiceParams
            ];
        }
        
        // Miscellaneous
        $categories['miscellaneous_extra'] = [
            'label' => 'Miscellaneous',
            'icon' => 'bi-gear',
            'editable' => true,
            'params' => [
                ['path' => 'InternetGatewayDevice.ManagementServer.URL', 'label' => 'ACS URL', 'value' => $getValue('InternetGatewayDevice.ManagementServer.URL'), 'type' => 'string'],
                ['path' => 'InternetGatewayDevice.ManagementServer.PeriodicInformEnable', 'label' => 'Periodic Inform', 'value' => $getValue('InternetGatewayDevice.ManagementServer.PeriodicInformEnable'), 'type' => 'boolean'],
                ['path' => 'InternetGatewayDevice.ManagementServer.PeriodicInformInterval', 'label' => 'Inform Interval', 'value' => $getValue('InternetGatewayDevice.ManagementServer.PeriodicInformInterval'), 'type' => 'number'],
                ['path' => 'InternetGatewayDevice.ManagementServer.ConnectionRequestURL', 'label' => 'Connection Request URL', 'value' => $getValue('InternetGatewayDevice.ManagementServer.ConnectionRequestURL'), 'type' => 'readonly'],
                ['path' => 'InternetGatewayDevice.Time.NTPServer1', 'label' => 'NTP Server 1', 'value' => $getValue('InternetGatewayDevice.Time.NTPServer1'), 'type' => 'string'],
                ['path' => 'InternetGatewayDevice.Time.NTPServer2', 'label' => 'NTP Server 2', 'value' => $getValue('InternetGatewayDevice.Time.NTPServer2'), 'type' => 'string'],
                ['path' => 'InternetGatewayDevice.Time.CurrentLocalTime', 'label' => 'Device Time', 'value' => $getValue('InternetGatewayDevice.Time.CurrentLocalTime'), 'type' => 'readonly'],
                ['path' => 'InternetGatewayDevice.UserInterface.CurrentLanguage', 'label' => 'Language', 'value' => $getValue('InternetGatewayDevice.UserInterface.CurrentLanguage'), 'type' => 'string'],
            ]
        ];
        
        // WiFi 2.4GHz Site Survey
        $survey24Params = [];
        for ($i = 1; $i <= 20; $i++) {
            $surveyBase = "InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.X_HW_NeighboringWiFiDiagnostic.Result.{$i}";
            if ($hasPath("{$surveyBase}.SSID")) {
                $survey24Params[] = ['path' => "{$surveyBase}.SSID", 'label' => "AP {$i} SSID", 'value' => $getValue("{$surveyBase}.SSID"), 'type' => 'readonly'];
                $survey24Params[] = ['path' => "{$surveyBase}.BSSID", 'label' => "AP {$i} BSSID", 'value' => $getValue("{$surveyBase}.BSSID"), 'type' => 'readonly'];
                $survey24Params[] = ['path' => "{$surveyBase}.Channel", 'label' => "AP {$i} Channel", 'value' => $getValue("{$surveyBase}.Channel"), 'type' => 'readonly'];
                $survey24Params[] = ['path' => "{$surveyBase}.SignalStrength", 'label' => "AP {$i} Signal", 'value' => $getValue("{$surveyBase}.SignalStrength"), 'type' => 'readonly'];
            }
        }
        if (!empty($survey24Params)) {
            $categories['troubleshooting_wifi_24'] = [
                'label' => 'WiFi 2.4GHz Site Survey',
                'icon' => 'bi-broadcast',
                'editable' => false,
                'params' => $survey24Params
            ];
        }
        
        // WiFi 5GHz Site Survey
        $survey5Params = [];
        for ($i = 1; $i <= 20; $i++) {
            $surveyBase = "InternetGatewayDevice.LANDevice.1.WLANConfiguration.5.X_HW_NeighboringWiFiDiagnostic.Result.{$i}";
            if ($hasPath("{$surveyBase}.SSID")) {
                $survey5Params[] = ['path' => "{$surveyBase}.SSID", 'label' => "AP {$i} SSID", 'value' => $getValue("{$surveyBase}.SSID"), 'type' => 'readonly'];
                $survey5Params[] = ['path' => "{$surveyBase}.BSSID", 'label' => "AP {$i} BSSID", 'value' => $getValue("{$surveyBase}.BSSID"), 'type' => 'readonly'];
                $survey5Params[] = ['path' => "{$surveyBase}.Channel", 'label' => "AP {$i} Channel", 'value' => $getValue("{$surveyBase}.Channel"), 'type' => 'readonly'];
                $survey5Params[] = ['path' => "{$surveyBase}.SignalStrength", 'label' => "AP {$i} Signal", 'value' => $getValue("{$surveyBase}.SignalStrength"), 'type' => 'readonly'];
            }
        }
        if (!empty($survey5Params)) {
            $categories['troubleshooting_wifi_5'] = [
                'label' => 'WiFi 5GHz Site Survey',
                'icon' => 'bi-broadcast',
                'editable' => false,
                'params' => $survey5Params
            ];
        }
        
        // Troubleshooting / Diagnostics
        $diagBase = 'InternetGatewayDevice.IPPingDiagnostics';
        $diagParams = [
            ['path' => "{$diagBase}.DiagnosticsState", 'label' => 'Ping State', 'value' => $getValue("{$diagBase}.DiagnosticsState"), 'type' => 'readonly'],
            ['path' => "{$diagBase}.Host", 'label' => 'Ping Host', 'value' => $getValue("{$diagBase}.Host"), 'type' => 'string'],
            ['path' => "{$diagBase}.NumberOfRepetitions", 'label' => 'Repetitions', 'value' => $getValue("{$diagBase}.NumberOfRepetitions"), 'type' => 'number'],
            ['path' => "{$diagBase}.Timeout", 'label' => 'Timeout (ms)', 'value' => $getValue("{$diagBase}.Timeout"), 'type' => 'number'],
            ['path' => "{$diagBase}.SuccessCount", 'label' => 'Success Count', 'value' => $getValue("{$diagBase}.SuccessCount"), 'type' => 'readonly'],
            ['path' => "{$diagBase}.FailureCount", 'label' => 'Failure Count', 'value' => $getValue("{$diagBase}.FailureCount"), 'type' => 'readonly'],
            ['path' => "{$diagBase}.AverageResponseTime", 'label' => 'Avg Response (ms)', 'value' => $getValue("{$diagBase}.AverageResponseTime"), 'type' => 'readonly'],
            ['path' => "{$diagBase}.MinimumResponseTime", 'label' => 'Min Response (ms)', 'value' => $getValue("{$diagBase}.MinimumResponseTime"), 'type' => 'readonly'],
            ['path' => "{$diagBase}.MaximumResponseTime", 'label' => 'Max Response (ms)', 'value' => $getValue("{$diagBase}.MaximumResponseTime"), 'type' => 'readonly'],
        ];
        // Traceroute
        $traceBase = 'InternetGatewayDevice.TraceRouteDiagnostics';
        $diagParams[] = ['path' => "{$traceBase}.DiagnosticsState", 'label' => 'Traceroute State', 'value' => $getValue("{$traceBase}.DiagnosticsState"), 'type' => 'readonly'];
        $diagParams[] = ['path' => "{$traceBase}.Host", 'label' => 'Traceroute Host', 'value' => $getValue("{$traceBase}.Host"), 'type' => 'string'];
        $diagParams[] = ['path' => "{$traceBase}.NumberOfTries", 'label' => 'Tries', 'value' => $getValue("{$traceBase}.NumberOfTries"), 'type' => 'number'];
        $diagParams[] = ['path' => "{$traceBase}.ResponseTime", 'label' => 'Response Time', 'value' => $getValue("{$traceBase}.ResponseTime"), 'type' => 'readonly'];
        $categories['troubleshooting'] = [
            'label' => 'Troubleshooting',
            'icon' => 'bi-search',
            'editable' => true,
            'params' => $diagParams
        ];
        
        // Device Logs
        $logParams = [];
        for ($i = 1; $i <= 10; $i++) {
            $logBase = "InternetGatewayDevice.DeviceInfo.VendorLogFile.{$i}";
            if ($hasPath("{$logBase}.Name")) {
                $logParams[] = ['path' => "{$logBase}.Name", 'label' => "Log {$i} Name", 'value' => $getValue("{$logBase}.Name"), 'type' => 'readonly'];
            }
        }
        // Also check DeviceLog if available
        if ($hasPath('InternetGatewayDevice.DeviceInfo.DeviceLog')) {
            $logParams[] = ['path' => 'InternetGatewayDevice.DeviceInfo.DeviceLog', 'label' => 'Device Log', 'value' => $getValue('InternetGatewayDevice.DeviceInfo.DeviceLog'), 'type' => 'readonly'];
        }
        if (!empty($logParams)) {
            $categories['device_logs'] = [
                'label' => 'Device Logs',
                'icon' => 'bi-journal-text',
                'editable' => false,
                'params' => $logParams
            ];
        }
        
        // File & Firmware Management
        $fwParams = [
            ['path' => 'InternetGatewayDevice.DeviceInfo.SoftwareVersion', 'label' => 'Current Firmware', 'value' => $getValue('InternetGatewayDevice.DeviceInfo.SoftwareVersion'), 'type' => 'readonly'],
            ['path' => 'InternetGatewayDevice.DeviceInfo.SpecVersion', 'label' => 'Spec Version', 'value' => $getValue('InternetGatewayDevice.DeviceInfo.SpecVersion'), 'type' => 'readonly'],
            ['path' => 'InternetGatewayDevice.ManagementServer.UpgradesManaged', 'label' => 'Upgrades Managed', 'value' => $getValue('InternetGatewayDevice.ManagementServer.UpgradesManaged'), 'type' => 'boolean'],
        ];
        // Download Diagnostics
        $dlBase = 'InternetGatewayDevice.DownloadDiagnostics';
        if ($hasPath("{$dlBase}.DiagnosticsState")) {
            $fwParams[] = ['path' => "{$dlBase}.DiagnosticsState", 'label' => 'Download State', 'value' => $getValue("{$dlBase}.DiagnosticsState"), 'type' => 'readonly'];
            $fwParams[] = ['path' => "{$dlBase}.DownloadURL", 'label' => 'Download URL', 'value' => $getValue("{$dlBase}.DownloadURL"), 'type' => 'string'];
        }
        $categories['miscellaneous_fw'] = [
            'label' => 'File & Firmware',
            'icon' => 'bi-download',
            'editable' => true,
            'params' => $fwParams
        ];
        
        // AUTO-CATEGORIZE ALL REMAINING PARAMETERS by path prefix
        $autoCats = [
            'ppp_interface' => ['label' => 'PPP Interface (WAN)', 'icon' => 'bi-globe', 'prefix' => 'WANDevice', 'match' => ['PPPConnection', 'WANPPPConnection'], 'editable' => true],
            'port_forward' => ['label' => 'Port Forward', 'icon' => 'bi-arrow-left-right', 'prefix' => 'WANDevice', 'match' => ['PortMapping', 'NAT'], 'editable' => true],
            'ip_interface' => ['label' => 'IP Interface', 'icon' => 'bi-diagram-3', 'prefix' => 'WANDevice', 'match' => ['IPConnection', 'WANIPConnection', 'ExternalIP', 'DefaultGateway'], 'editable' => true],
            'lan_dhcp' => ['label' => 'LAN DHCP Server', 'icon' => 'bi-hdd-network', 'prefix' => 'LANDevice', 'match' => ['DHCPServer', 'DHCP'], 'editable' => true],
            'lan_ports' => ['label' => 'LAN Ports', 'icon' => 'bi-ethernet', 'prefix' => 'LANEthernetInterfaceConfig', 'match' => [], 'editable' => true],
            'lan_counters' => ['label' => 'LAN Counters', 'icon' => 'bi-speedometer2', 'prefix' => 'LANDevice', 'match' => ['Stats', 'Bytes', 'Packets', 'Errors'], 'editable' => false],
            'wireless_lan' => ['label' => 'Wireless LAN', 'icon' => 'bi-wifi', 'prefix' => 'WLANConfiguration', 'match' => ['SSID', 'KeyPassphrase', 'BeaconType', 'Channel', 'Enable'], 'editable' => true],
            'wlan_counters' => ['label' => 'WLAN Counters', 'icon' => 'bi-bar-chart', 'prefix' => 'WLANConfiguration', 'match' => ['Stats', 'TotalBytes', 'TotalPackets'], 'editable' => false],
            'hosts' => ['label' => 'Hosts', 'icon' => 'bi-pc-display', 'prefix' => 'LANDevice.1.Hosts', 'match' => [], 'editable' => false],
            'security' => ['label' => 'Security', 'icon' => 'bi-shield-lock', 'prefix' => 'UserInterface', 'match' => ['RemoteAccess', 'Password', 'Firewall'], 'editable' => true],
            'voice_lines' => ['label' => 'Voice Lines', 'icon' => 'bi-telephone', 'prefix' => 'VoiceService', 'match' => [], 'editable' => true],
            'miscellaneous' => ['label' => 'Miscellaneous', 'icon' => 'bi-three-dots', 'prefix' => '', 'match' => ['ManagementServer', 'Time', 'DeviceInfo'], 'editable' => true],
            'troubleshooting' => ['label' => 'Troubleshooting', 'icon' => 'bi-tools', 'prefix' => '', 'match' => ['Diagnostics', 'IPPing', 'TraceRoute', 'Download', 'Upload'], 'editable' => true],
            'device_logs' => ['label' => 'Device Logs', 'icon' => 'bi-journal-text', 'prefix' => 'DeviceInfo', 'match' => ['Log', 'DeviceLog', 'VendorLog'], 'editable' => false],
        ];
        
        $autoCatParams = [];
        foreach ($autoCats as $catKey => $catDef) {
            $autoCatParams[$catKey] = [];
        }
        $uncategorized = [];
        
        // Process all device parameters
        $sortedKeys = array_keys($device);
        sort($sortedKeys);
        foreach ($sortedKeys as $key) {
            if (str_starts_with($key, '_')) continue;
            
            $val = $device[$key];
            if (!is_array($val) || !isset($val['_value'])) continue;
            
            $paramValue = $val['_value'];
            $writable = $val['_writable'] ?? false;
            $pathParts = explode('.', $key);
            $shortLabel = end($pathParts);
            
            // Make labels more readable
            $friendlyLabel = preg_replace('/([a-z])([A-Z])/', '$1 $2', $shortLabel);
            $friendlyLabel = str_replace('X_HW_', '', $friendlyLabel);
            $friendlyLabel = str_replace('_', ' ', $friendlyLabel);
            
            // Determine type based on value and path
            $paramType = 'readonly';
            if ($writable) {
                if (is_bool($paramValue) || $paramValue === 'true' || $paramValue === 'false') {
                    $paramType = 'boolean';
                } elseif (stripos($shortLabel, 'Password') !== false || stripos($shortLabel, 'Passphrase') !== false) {
                    $paramType = 'password';
                } elseif (is_numeric($paramValue) && !preg_match('/Address|IP|MAC|URL/', $shortLabel)) {
                    $paramType = 'number';
                } else {
                    $paramType = 'string';
                }
            }
            
            $param = [
                'path' => $key,
                'label' => $friendlyLabel,
                'full_path' => $key,
                'value' => $paramValue,
                'type' => $paramType
            ];
            
            // Categorize by path
            $categorized = false;
            foreach ($autoCats as $catKey => $catDef) {
                if (strpos($key, $catDef['prefix']) !== false) {
                    // Check if matches specific sub-patterns
                    if (!empty($catDef['match'])) {
                        foreach ($catDef['match'] as $match) {
                            if (strpos($key, $match) !== false) {
                                $autoCatParams[$catKey][] = $param;
                                $categorized = true;
                                break 2;
                            }
                        }
                    } else {
                        $autoCatParams[$catKey][] = $param;
                        $categorized = true;
                        break;
                    }
                }
            }
            
            if (!$categorized) {
                $uncategorized[] = $param;
            }
        }
        
        // Add auto-categorized sections
        foreach ($autoCats as $catKey => $catDef) {
            if (!empty($autoCatParams[$catKey])) {
                $categories['auto_' . $catKey] = [
                    'label' => $catDef['label'] . ' (' . count($autoCatParams[$catKey]) . ')',
                    'icon' => $catDef['icon'],
                    'editable' => $catDef['editable'],
                    'params' => $autoCatParams[$catKey]
                ];
            }
        }
        
        // Add uncategorized as "Other Parameters"
        if (!empty($uncategorized)) {
            $categories['miscellaneous_other'] = [
                'label' => 'Other Parameters (' . count($uncategorized) . ')',
                'icon' => 'bi-list-ul',
                'editable' => true,
                'params' => $uncategorized
            ];
        }
        
        // Remove categories with no valid parameters (except all_parameters)
        foreach ($categories as $key => $category) {
            if ($key === 'all_parameters') continue;
            $hasValue = false;
            foreach ($category['params'] as $param) {
                if ($param['value'] !== null) {
                    $hasValue = true;
                    break;
                }
            }
            if (!$hasValue) {
                unset($categories[$key]);
            }
        }
        
        // Calculate total params from all categories
        $totalParams = 0;
        foreach ($categories as $cat) {
            $totalParams += count($cat['params'] ?? []);
        }
        
        return [
            'success' => true,
            'device_id' => $deviceId,
            'last_inform' => $device['_lastInform'] ?? null,
            'categories' => $categories,
            'total_params' => $totalParams
        ];
    }
    
    /**
     * Save device parameters (batch update)
     */
    public function saveDeviceParams(string $deviceId, array $params): array {
        if (empty($params)) {
            return ['success' => false, 'error' => 'No parameters to save'];
        }
        
        // Build parameter values array
        $paramValues = [];
        foreach ($params as $path => $value) {
            // Determine type based on value
            if (is_bool($value) || $value === 'true' || $value === 'false') {
                $paramValues[] = [$path, $value === 'true' || $value === true, 'xsd:boolean'];
            } elseif (is_numeric($value) && strpos($path, 'Address') === false && strpos($path, 'SSID') === false) {
                $paramValues[] = [$path, (int)$value, 'xsd:unsignedInt'];
            } else {
                $paramValues[] = [$path, (string)$value, 'xsd:string'];
            }
        }
        
        return $this->setParameterValues($deviceId, $paramValues);
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
     * Configure WiFi Access VLAN (Bridge mode for specific SSID)
     * 
     * This follows the exact sequence from Huawei ONU logs:
     * 1. Add WLANConfiguration (if needed)
     * 2. Add WANConnectionDevice
     * 3. Set WLAN settings
     * 4. Add WANIPConnection
     * 5. Set ConnectionType: IP_Bridged
     * 6. Add Layer3Forwarding.X_HW_policy_route
     * 7. Set policy_route with PhyPortName, WanName
     * 8. Set WANIPConnection Name and X_HW_VLAN
     * 
     * @param string $deviceId GenieACS device ID
     * @param int $wifiIndex WiFi index (1=2.4GHz primary, 2=2.4GHz secondary, 5=5GHz primary)
     * @param int $vlanId VLAN ID to assign
     * @param string $ssidName Optional SSID name
     * @return array Result with success status
     */
    public function configureWifiAccessVlan(string $deviceId, int $wifiIndex, int $vlanId, string $ssidName = ''): array {
        $results = [];
        $errors = [];
        
        // Map WiFi index to SSID port name (SSID1, SSID2, SSID3, SSID4 for 2.4GHz, SSID5-8 for 5GHz)
        $ssidPortName = 'SSID' . $wifiIndex;
        
        // Determine WAN connection device index (use wifiIndex + 2 to avoid conflict with main WAN)
        $wanDeviceIndex = $wifiIndex + 2;
        $wanConnectionName = "WIFI_Bridge_VLAN_{$vlanId}";
        $wanName = "wan1.{$wanDeviceIndex}.ip1";
        
        $deviceIdEncoded = rawurlencode($deviceId);
        
        try {
            // Step 1: Add WLANConfiguration (if needed for secondary SSIDs)
            // Primary SSIDs (1, 5) usually exist, secondaries (2, 6) may need creation
            if ($wifiIndex > 1 && $wifiIndex != 5) {
                $addWlanResult = $this->makeRequest(
                    "POST",
                    "devices/{$deviceIdEncoded}/tasks?connection_request",
                    [
                        'name' => 'addObject',
                        'objectName' => 'InternetGatewayDevice.LANDevice.WLANConfiguration'
                    ]
                );
                $results[] = ['step' => 'add_wlan_config', 'result' => $addWlanResult];
                usleep(500000); // 500ms delay
            }
            
            // Step 2: Add WANConnectionDevice
            $addWanDevResult = $this->makeRequest(
                "POST",
                "devices/{$deviceIdEncoded}/tasks?connection_request",
                [
                    'name' => 'addObject',
                    'objectName' => 'InternetGatewayDevice.WANDevice.WANConnectionDevice'
                ]
            );
            $results[] = ['step' => 'add_wan_device', 'result' => $addWanDevResult];
            usleep(500000);
            
            // Step 3: Set WLAN settings if SSID name provided
            if ($ssidName) {
                $wlanPath = "InternetGatewayDevice.LANDevice.1.WLANConfiguration.{$wifiIndex}";
                $setWlanResult = $this->makeRequest(
                    "POST",
                    "devices/{$deviceIdEncoded}/tasks?connection_request",
                    [
                        'name' => 'setParameterValues',
                        'parameterValues' => [
                            ["{$wlanPath}.SSID", $ssidName, 'xsd:string'],
                            ["{$wlanPath}.BeaconType", 'None', 'xsd:string']
                        ]
                    ]
                );
                $results[] = ['step' => 'set_wlan_settings', 'result' => $setWlanResult];
                usleep(500000);
            }
            
            // Step 4: Add WANIPConnection
            $addWanIpResult = $this->makeRequest(
                "POST",
                "devices/{$deviceIdEncoded}/tasks?connection_request",
                [
                    'name' => 'addObject',
                    'objectName' => "InternetGatewayDevice.WANDevice.1.WANConnectionDevice.{$wanDeviceIndex}.WANIPConnection"
                ]
            );
            $results[] = ['step' => 'add_wan_ip_connection', 'result' => $addWanIpResult];
            usleep(500000);
            
            // Step 5: Set ConnectionType to IP_Bridged
            $wanIpPath = "InternetGatewayDevice.WANDevice.1.WANConnectionDevice.{$wanDeviceIndex}.WANIPConnection.1";
            $setBridgeResult = $this->makeRequest(
                "POST",
                "devices/{$deviceIdEncoded}/tasks?connection_request",
                [
                    'name' => 'setParameterValues',
                    'parameterValues' => [
                        ["{$wanIpPath}.ConnectionType", 'IP_Bridged', 'xsd:string']
                    ]
                ]
            );
            $results[] = ['step' => 'set_bridge_mode', 'result' => $setBridgeResult];
            usleep(500000);
            
            // Step 6: Add Layer3Forwarding.X_HW_policy_route
            $addRouteResult = $this->makeRequest(
                "POST",
                "devices/{$deviceIdEncoded}/tasks?connection_request",
                [
                    'name' => 'addObject',
                    'objectName' => 'InternetGatewayDevice.Layer3Forwarding.X_HW_policy_route'
                ]
            );
            $results[] = ['step' => 'add_policy_route', 'result' => $addRouteResult];
            usleep(500000);
            
            // Step 7: Set policy_route with PhyPortName and WanName
            // The route index is typically wifiIndex or the next available
            $routeIndex = $wifiIndex;
            $setRouteResult = $this->makeRequest(
                "POST",
                "devices/{$deviceIdEncoded}/tasks?connection_request",
                [
                    'name' => 'setParameterValues',
                    'parameterValues' => [
                        ["InternetGatewayDevice.Layer3Forwarding.X_HW_policy_route.{$routeIndex}.PhyPortName", $ssidPortName, 'xsd:string'],
                        ["InternetGatewayDevice.Layer3Forwarding.X_HW_policy_route.{$routeIndex}.PolicyRouteType", 'SourcePhyPort', 'xsd:string'],
                        ["InternetGatewayDevice.Layer3Forwarding.X_HW_policy_route.{$routeIndex}.WanName", $wanName, 'xsd:string']
                    ]
                ]
            );
            $results[] = ['step' => 'set_policy_route', 'result' => $setRouteResult];
            usleep(500000);
            
            // Step 8: Set WANIPConnection Name and X_HW_VLAN
            $setVlanResult = $this->makeRequest(
                "POST",
                "devices/{$deviceIdEncoded}/tasks?connection_request",
                [
                    'name' => 'setParameterValues',
                    'parameterValues' => [
                        ["{$wanIpPath}.Name", $wanConnectionName, 'xsd:string'],
                        ["{$wanIpPath}.X_HW_VLAN", (string)$vlanId, 'xsd:int']
                    ]
                ]
            );
            $results[] = ['step' => 'set_vlan', 'result' => $setVlanResult];
            
            return [
                'success' => true,
                'message' => "WiFi {$wifiIndex} configured with VLAN {$vlanId} in Bridge mode",
                'results' => $results,
                'wan_name' => $wanName,
                'ssid_port' => $ssidPortName
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'results' => $results,
                'errors' => $errors
            ];
        }
    }

    /**
     * SmartOLT-style Internet WAN configuration via TR-069
     * Creates WAN objects using addObject then configures them
     * Matches Huawei ONU firmware behavior and SmartOLT provisioning
     * 
     * SAFEGUARDS IMPLEMENTED:
     * 1. Device state guard (NEW→PROVISIONING→ACTIVE)
     * 2. Idempotency check (don't create duplicate WAN objects)
     * 3. NTP gate (check device time before provisioning)
     * 4. Rate limiting (10 min cooldown between provisions)
     * 5. Final HTTP lock even on failure
     */
    public function configureInternetWAN(string $deviceId, array $config): array {
        $results = [];
        $errors = [];
        $wanName = 'wan1.1.ppp1';
        
        $connectionType = $config['connection_type'] ?? 'pppoe';
        $serviceVlan = (int)($config['service_vlan'] ?? 0);
        $skipChecks = $config['skip_safety_checks'] ?? false;
        
        try {
            // =====================================================
            // SAFEGUARD 1: Device State Guard
            // =====================================================
            if (!$skipChecks) {
                $stateCheck = $this->checkProvisioningState($deviceId);
                if (!$stateCheck['can_provision']) {
                    return [
                        'success' => false,
                        'error' => $stateCheck['reason'],
                        'state' => $stateCheck['state']
                    ];
                }
                
                // Mark as PROVISIONING
                $this->setProvisioningState($deviceId, 'PROVISIONING');
            }
            
            // =====================================================
            // SAFEGUARD 2: NTP Gate - Check device time
            // =====================================================
            if (!$skipChecks) {
                $ntpCheck = $this->checkDeviceTime($deviceId);
                if (!$ntpCheck['time_valid']) {
                    // Push NTP config and wait for next inform
                    $this->pushNTPConfig($deviceId);
                    $this->setProvisioningState($deviceId, 'NEW'); // Reset to NEW
                    return [
                        'success' => false,
                        'error' => 'Device time invalid (year < 2020). NTP pushed, waiting for next inform.',
                        'ntp_pushed' => true
                    ];
                }
            }
            
            // =====================================================
            // SAFEGUARD 3: Rate Limiting (10 min cooldown)
            // =====================================================
            if (!$skipChecks) {
                $rateCheck = $this->checkProvisioningCooldown($deviceId, 600); // 10 minutes
                if (!$rateCheck['allowed']) {
                    return [
                        'success' => false,
                        'error' => 'Rate limit: Last provision was ' . $rateCheck['seconds_ago'] . 's ago. Wait ' . (600 - $rateCheck['seconds_ago']) . 's.',
                        'rate_limited' => true
                    ];
                }
            }
            
            // =====================================================
            // SAFEGUARD 4: Idempotency - Check existing WAN objects
            // =====================================================
            $existingWAN = $this->checkExistingWANObjects($deviceId);
            $results['existing_wan_check'] = $existingWAN;
            
            // Step 1: Lock management access (disable WAN HTTP)
            $secureResult = $this->secureDevice($deviceId);
            $results['secure_device'] = $secureResult;
            
            // Step 2: Configure WAN - Proper TR-069 workflow:
            // 1. AddObject to create WANPPPConnection instance
            // 2. Wait for device to process
            // 3. SetParameterValues on the new instance
            if ($connectionType === 'pppoe') {
                $pppBasePath = 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection';
                $wanName = 'wan1.1.ppp1';
                
                // Step 2a: Check if WANPPPConnection.1 already exists
                $instanceExists = false;
                if (isset($existingWAN['ppp_connections']) && count($existingWAN['ppp_connections']) > 0) {
                    $instanceExists = true;
                    $pppBase = $existingWAN['ppp_connections'][0] ?? "{$pppBasePath}.1";
                    $results['create_wan'] = ['skipped' => true, 'reason' => 'WANPPPConnection instance already exists: ' . $pppBase];
                    error_log("[GenieACS] PPPoE instance exists: {$pppBase}");
                }
                
                if (!$instanceExists) {
                    // Step 2b: AddObject to create WANPPPConnection instance
                    error_log("[GenieACS] Creating WANPPPConnection via AddObject: {$pppBasePath}");
                    $addResult = $this->addObject($deviceId, $pppBasePath);
                    $results['add_ppp_object'] = $addResult;
                    
                    if (!$addResult['success']) {
                        $errors[] = 'AddObject failed: ' . ($addResult['error'] ?? 'Unknown');
                        error_log("[GenieACS] AddObject failed: " . json_encode($addResult));
                    } else {
                        // Wait for device to create the object (2 seconds)
                        sleep(2);
                        
                        // Step 2c: Refresh to get the new instance path
                        $refreshResult = $this->refreshObject($deviceId, $pppBasePath);
                        $results['refresh_ppp'] = $refreshResult;
                        error_log("[GenieACS] Refresh result: " . json_encode($refreshResult));
                        
                        // Wait for refresh to complete
                        sleep(2);
                    }
                    $pppBase = "{$pppBasePath}.1";
                }
                
                // Step 2d: Build all parameters in one setParameterValues call
                $params = [];
                $params[] = ["{$pppBase}.Enable", true, 'xsd:boolean'];
                if (!empty($config['pppoe_username'])) {
                    $params[] = ["{$pppBase}.Username", $config['pppoe_username'], 'xsd:string'];
                }
                if (!empty($config['pppoe_password'])) {
                    $params[] = ["{$pppBase}.Password", $config['pppoe_password'], 'xsd:string'];
                }
                $params[] = ["{$pppBase}.NATEnabled", true, 'xsd:boolean'];
                if ($serviceVlan > 0) {
                    $params[] = ["{$pppBase}.X_HW_VLAN", $serviceVlan, 'xsd:unsignedInt'];
                }
                
                // Enable LAN port routing (L3Enable) as you did manually
                for ($i = 1; $i <= 4; $i++) {
                    $params[] = ["InternetGatewayDevice.LANDevice.1.LANEthernetInterfaceConfig.{$i}.X_HW_L3Enable", true, 'xsd:boolean'];
                }
                
                error_log("[GenieACS] PPPoE setParameterValues: " . json_encode($params));
                
                $configResult = $this->setParameterValues($deviceId, $params);
                $results['pppoe_config'] = $configResult;
                
                error_log("[GenieACS] PPPoE result: " . json_encode($configResult));
                
                if (!$configResult['success']) {
                    $errors[] = 'PPPoE config failed: ' . ($configResult['error'] ?? 'Unknown');
                }
                
            } else {
                // Bridge Mode (DHCP/Static) - WANIPConnection with IP_Bridged
                $ipBasePath = 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection';
                $wanName = 'wan1.1.ip1';
                
                // Step 2a: Check if WANIPConnection.1 already exists
                $instanceExists = false;
                if (isset($existingWAN['ip_connections']) && count($existingWAN['ip_connections']) > 0) {
                    $instanceExists = true;
                    $ipBase = $existingWAN['ip_connections'][0] ?? "{$ipBasePath}.1";
                    $results['create_wan'] = ['skipped' => true, 'reason' => 'WANIPConnection instance already exists: ' . $ipBase];
                }
                
                if (!$instanceExists) {
                    // Step 2b: AddObject to create WANIPConnection instance
                    error_log("[GenieACS] Creating WANIPConnection via AddObject: {$ipBasePath}");
                    $addResult = $this->addObject($deviceId, $ipBasePath);
                    $results['add_ip_object'] = $addResult;
                    
                    if (!$addResult['success']) {
                        $errors[] = 'AddObject failed: ' . ($addResult['error'] ?? 'Unknown');
                    } else {
                        sleep(2);
                        $refreshResult = $this->refreshObject($deviceId, $ipBasePath);
                        $results['refresh_ip'] = $refreshResult;
                        sleep(2);
                    }
                    $ipBase = "{$ipBasePath}.1";
                }
                
                $params = [
                    ["{$ipBase}.Enable", true, 'xsd:boolean'],
                    ["{$ipBase}.ConnectionType", 'IP_Bridged', 'xsd:string']
                ];
                if ($serviceVlan > 0) {
                    $params[] = ["{$ipBase}.X_HW_VLAN", $serviceVlan, 'xsd:unsignedInt'];
                }
                
                error_log("[GenieACS] Bridge setParameterValues: " . json_encode($params));
                
                $configResult = $this->setParameterValues($deviceId, $params);
                $results['bridge_config'] = $configResult;
                
                error_log("[GenieACS] Bridge result: " . json_encode($configResult));
                
                if (!$configResult['success']) {
                    $errors[] = 'Bridge config failed: ' . ($configResult['error'] ?? 'Unknown');
                }
            }
            
            // Mark as ACTIVE on success
            if (!$skipChecks && empty($errors)) {
                $this->setProvisioningState($deviceId, 'ACTIVE');
                $this->recordProvisioningTime($deviceId);
            }
            
            // Step 3: Log the action
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
            
        } catch (\Exception $e) {
            // Mark as FAILED
            if (!$skipChecks) {
                $this->setProvisioningState($deviceId, 'FAILED');
            }
            $errors[] = 'Exception: ' . $e->getMessage();
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'errors' => $errors,
                'results' => $results
            ];
            
        } finally {
            // =====================================================
            // SAFEGUARD 5: Final HTTP lock even on failure
            // =====================================================
            $this->secureDevice($deviceId);
        }
    }
    
    /**
     * Secure device by disabling WAN HTTP access
     */
    public function secureDevice(string $deviceId): array {
        return $this->setParameterValues($deviceId, [
            ['InternetGatewayDevice.X_HW_Security.AclServices.HTTPWanEnable', false, 'xsd:boolean']
        ]);
    }
    
    // ========================================================================
    // SAFEGUARD HELPER FUNCTIONS
    // ========================================================================
    
    /**
     * Check provisioning state - prevents re-provisioning already active devices
     * States: NEW, PROVISIONING, ACTIVE, FAILED
     */
    public function checkProvisioningState(string $deviceId): array {
        // Get state from database
        $stmt = $this->db->prepare("
            SELECT provision_state, last_provision_at 
            FROM tr069_devices 
            WHERE device_id = ?
        ");
        $stmt->execute([$deviceId]);
        $device = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$device) {
            // Device not in DB yet - allow provisioning
            return ['can_provision' => true, 'state' => 'NEW'];
        }
        
        $state = $device['provision_state'] ?? 'NEW';
        
        // State machine logic
        switch ($state) {
            case 'NEW':
            case 'FAILED':
                return ['can_provision' => true, 'state' => $state];
            case 'PROVISIONING':
                return ['can_provision' => false, 'state' => $state, 'reason' => 'Device is currently being provisioned'];
            case 'ACTIVE':
                return ['can_provision' => false, 'state' => $state, 'reason' => 'Device already provisioned. Use force=true to re-provision.'];
            default:
                return ['can_provision' => true, 'state' => $state];
        }
    }
    
    /**
     * Set provisioning state in database
     */
    public function setProvisioningState(string $deviceId, string $state): bool {
        try {
            // Check if device exists
            $stmt = $this->db->prepare("SELECT device_id FROM tr069_devices WHERE device_id = ?");
            $stmt->execute([$deviceId]);
            
            if ($stmt->fetch()) {
                // Update existing
                $stmt = $this->db->prepare("UPDATE tr069_devices SET provision_state = ?, updated_at = CURRENT_TIMESTAMP WHERE device_id = ?");
                $stmt->execute([$state, $deviceId]);
            } else {
                // Insert new
                $stmt = $this->db->prepare("INSERT INTO tr069_devices (device_id, provision_state) VALUES (?, ?)");
                $stmt->execute([$deviceId, $state]);
            }
            return true;
        } catch (\Exception $e) {
            error_log("[GenieACS] Failed to set provisioning state: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check device time - NTP gate to prevent provisioning before time sync
     */
    public function checkDeviceTime(string $deviceId): array {
        $result = $this->getDevice($deviceId);
        if (!$result['success'] || empty($result['data'])) {
            return ['time_valid' => true]; // Allow if we can't check
        }
        
        $device = $result['data'];
        
        // Get CurrentTime from device
        $currentTime = $this->getNestedValue($device, 'InternetGatewayDevice.DeviceInfo.CurrentTime._value');
        $upTime = $this->getNestedValue($device, 'InternetGatewayDevice.DeviceInfo.UpTime._value');
        
        if ($currentTime) {
            $year = (int)date('Y', strtotime($currentTime));
            if ($year < 2020) {
                return [
                    'time_valid' => false,
                    'device_time' => $currentTime,
                    'year' => $year,
                    'uptime' => $upTime
                ];
            }
        }
        
        return ['time_valid' => true, 'device_time' => $currentTime, 'uptime' => $upTime];
    }
    
    /**
     * Push NTP configuration to device
     */
    public function pushNTPConfig(string $deviceId): array {
        return $this->setParameterValues($deviceId, [
            ['InternetGatewayDevice.Time.Enable', true, 'xsd:boolean'],
            ['InternetGatewayDevice.Time.NTPServer1', 'pool.ntp.org', 'xsd:string'],
            ['InternetGatewayDevice.Time.NTPServer2', 'time.google.com', 'xsd:string']
        ]);
    }
    
    /**
     * Check provisioning cooldown - rate limiting
     */
    public function checkProvisioningCooldown(string $deviceId, int $cooldownSeconds = 600): array {
        $stmt = $this->db->prepare("
            SELECT last_provision_at 
            FROM tr069_devices 
            WHERE device_id = ?
        ");
        $stmt->execute([$deviceId]);
        $device = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$device || empty($device['last_provision_at'])) {
            return ['allowed' => true, 'seconds_ago' => null];
        }
        
        $lastProvision = strtotime($device['last_provision_at']);
        $secondsAgo = time() - $lastProvision;
        
        if ($secondsAgo < $cooldownSeconds) {
            return ['allowed' => false, 'seconds_ago' => $secondsAgo];
        }
        
        return ['allowed' => true, 'seconds_ago' => $secondsAgo];
    }
    
    /**
     * Record provisioning time for rate limiting
     */
    public function recordProvisioningTime(string $deviceId): bool {
        try {
            $stmt = $this->db->prepare("
                UPDATE tr069_devices 
                SET last_provision_at = CURRENT_TIMESTAMP 
                WHERE device_id = ?
            ");
            $stmt->execute([$deviceId]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Check existing WAN objects - idempotency check
     */
    public function checkExistingWANObjects(string $deviceId): array {
        $result = $this->getDevice($deviceId);
        if (!$result['success'] || empty($result['data'])) {
            return ['has_pppoe' => false, 'has_ip' => false, 'has_policy_route' => false];
        }
        
        $device = $result['data'];
        
        // Check for existing PPPoE
        $pppPath = 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1';
        $hasPPPoE = $this->getNestedValue($device, $pppPath . '.Enable') !== null ||
                    $this->getNestedValue($device, $pppPath . '.Username') !== null;
        
        // Check for existing IP connection
        $ipPath = 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1';
        $hasIP = $this->getNestedValue($device, $ipPath . '.Enable') !== null;
        
        // Check for existing policy routes
        $routePath = 'InternetGatewayDevice.Layer3Forwarding.X_HW_policy_route.1';
        $hasRoute = $this->getNestedValue($device, $routePath . '.WanName') !== null;
        
        return [
            'has_pppoe' => $hasPPPoE,
            'has_ip' => $hasIP,
            'has_policy_route' => $hasRoute,
            'pppoe_username' => $hasPPPoE ? $this->getNestedValue($device, $pppPath . '.Username._value') : null
        ];
    }
    
    /**
     * Provision WiFi VLAN Bridge (for guest networks or separate VLAN)
     */
    public function provisionBridge(string $deviceId, int $vlan): array {
        $createTasks = [
            ['name' => 'addObject', 'objectName' => 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.'],
            ['name' => 'addObject', 'objectName' => 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.']
        ];
        
        $this->pushTasks($deviceId, $createTasks);
        
        return $this->setParameterValues($deviceId, [
            ['InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.ConnectionType', 'IP_Bridged', 'xsd:string'],
            ['InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.X_HW_VLAN', $vlan, 'xsd:unsignedInt'],
            ['InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.Enable', true, 'xsd:boolean']
        ]);
    }
    
    /**
     * Configure WiFi interface - simple version (SSID, password, enable)
     * Used by provisionCustomer for quick WiFi setup
     */
    public function configureWiFiSimple(string $deviceId, int $ssidIndex, string $ssid, string $password): array {
        $wlanBase = "InternetGatewayDevice.LANDevice.1.WLANConfiguration.{$ssidIndex}";
        
        return $this->setParameterValues($deviceId, [
            ["{$wlanBase}.SSID", $ssid, 'xsd:string'],
            ["{$wlanBase}.PreSharedKey.1.KeyPassphrase", $password, 'xsd:string'],
            ["{$wlanBase}.Enable", true, 'xsd:boolean'],
            ["{$wlanBase}.WPS.Enable", false, 'xsd:boolean']
        ]);
    }
    
    /**
     * Bind WiFi interface to WAN using policy routing
     */
    public function bindWiFiToWAN(string $deviceId, int $ssidIndex, string $wanName): array {
        $createTask = [
            ['name' => 'addObject', 'objectName' => 'InternetGatewayDevice.Layer3Forwarding.X_HW_policy_route.']
        ];
        
        $this->pushTasks($deviceId, $createTask);
        
        return $this->setParameterValues($deviceId, [
            ['InternetGatewayDevice.Layer3Forwarding.X_HW_policy_route.1.PhyPortName', "SSID{$ssidIndex}", 'xsd:string'],
            ['InternetGatewayDevice.Layer3Forwarding.X_HW_policy_route.1.PolicyRouteType', 'SourcePhyPort', 'xsd:string'],
            ['InternetGatewayDevice.Layer3Forwarding.X_HW_policy_route.1.WanName', $wanName, 'xsd:string']
        ]);
    }
    
    /**
     * Full SmartOLT-style customer provisioning flow
     */
    public function provisionCustomer(string $deviceId, array $config): array {
        $results = [];
        $errors = [];
        
        $mode = $config['mode'] ?? 'route'; // route or bridge
        $pppoeUser = $config['pppoe_username'] ?? '';
        $pppoePass = $config['pppoe_password'] ?? '';
        $internetVlan = (int)($config['internet_vlan'] ?? 0);
        $wifiVlan = (int)($config['wifi_vlan'] ?? 0);
        $ssid = $config['ssid'] ?? '';
        $wifiKey = $config['wifi_password'] ?? '';
        
        // 1. Lock management access
        $results['secure1'] = $this->secureDevice($deviceId);
        
        // 2. Internet service (PPPoE)
        if ($mode === 'route' && !empty($pppoeUser)) {
            $results['pppoe'] = $this->configureInternetWAN($deviceId, [
                'connection_type' => 'pppoe',
                'pppoe_username' => $pppoeUser,
                'pppoe_password' => $pppoePass,
                'service_vlan' => $internetVlan
            ]);
            if (!$results['pppoe']['success']) {
                $errors[] = 'PPPoE failed';
            }
        }
        
        // 3. WiFi bridge VLAN (if different from internet VLAN)
        if ($wifiVlan > 0 && $wifiVlan !== $internetVlan) {
            $results['bridge'] = $this->provisionBridge($deviceId, $wifiVlan);
        }
        
        // 4. WiFi setup
        if (!empty($ssid) && !empty($wifiKey)) {
            $results['wifi'] = $this->configureWiFiSimple($deviceId, 1, $ssid, $wifiKey);
            if (!$results['wifi']['success']) {
                $errors[] = 'WiFi failed';
            }
            
            // 5. Bind WiFi to bridge WAN
            if ($wifiVlan > 0) {
                $results['bind'] = $this->bindWiFiToWAN($deviceId, 1, 'wan1.3.ip1');
            }
        }
        
        // 6. Lock again
        $results['secure2'] = $this->secureDevice($deviceId);
        
        $this->logTR069Action($deviceId, 'provision_customer', [
            'mode' => $mode,
            'internet_vlan' => $internetVlan,
            'wifi_vlan' => $wifiVlan,
            'ssid' => $ssid,
            'success' => empty($errors)
        ]);
        
        return [
            'success' => empty($errors),
            'errors' => $errors,
            'results' => $results
        ];
    }
    
    // ========================================================================
    // TR-181 (Device:2) Data Model Support
    // For ONUs that use the newer TR-181 data model instead of TR-098 IGD
    // ========================================================================
    
    /**
     * Detect which data model the ONU supports
     * Returns 'tr098' for IGD model or 'tr181' for Device:2 model
     */
    public function detectDataModel(string $deviceId): string {
        $result = $this->getDevice($deviceId);
        if (!$result['success'] || empty($result['data'])) {
            return 'tr098'; // Default to TR-098
        }
        
        $device = $result['data'];
        
        // Check for TR-181 specific paths
        $hasTR181 = isset($device['Device']) || 
                    $this->getNestedValue($device, 'InternetGatewayDevice.PPP.Interface') !== null ||
                    $this->getNestedValue($device, 'InternetGatewayDevice.Ethernet.VLANTermination') !== null;
        
        // Check for TR-098 specific paths
        $hasTR098 = $this->getNestedValue($device, 'InternetGatewayDevice.WANDevice') !== null;
        
        if ($hasTR181 && !$hasTR098) {
            return 'tr181';
        }
        
        return 'tr098';
    }
    
    /**
     * TR-181: Create VLAN termination
     */
    public function createVlanTerminationTR181(string $deviceId, int $vlan): array {
        $tasks = [
            ['name' => 'addObject', 'objectName' => 'InternetGatewayDevice.Ethernet.VLANTermination.'],
            ['name' => 'setParameterValues', 'parameterValues' => [
                ['InternetGatewayDevice.Ethernet.VLANTermination.1.VLANID', $vlan, 'xsd:unsignedInt'],
                ['InternetGatewayDevice.Ethernet.VLANTermination.1.Enable', true, 'xsd:boolean']
            ]]
        ];
        
        return $this->pushTasks($deviceId, $tasks);
    }
    
    /**
     * TR-181: Configure PPPoE using PPP.Interface and IP.Interface
     */
    public function configurePPPoETR181(string $deviceId, string $username, string $password): array {
        $tasks = [
            // Create IP Interface linked to VLAN termination
            ['name' => 'addObject', 'objectName' => 'InternetGatewayDevice.IP.Interface.'],
            ['name' => 'setParameterValues', 'parameterValues' => [
                ['InternetGatewayDevice.IP.Interface.3.LowerLayers', 'InternetGatewayDevice.Ethernet.VLANTermination.1', 'xsd:string'],
                ['InternetGatewayDevice.IP.Interface.3.Enable', true, 'xsd:boolean']
            ]],
            
            // Create PPP Interface linked to IP Interface
            ['name' => 'addObject', 'objectName' => 'InternetGatewayDevice.PPP.Interface.'],
            ['name' => 'setParameterValues', 'parameterValues' => [
                ['InternetGatewayDevice.PPP.Interface.2.LowerLayers', 'InternetGatewayDevice.IP.Interface.3', 'xsd:string'],
                ['InternetGatewayDevice.PPP.Interface.2.Username', $username, 'xsd:string'],
                ['InternetGatewayDevice.PPP.Interface.2.Password', $password, 'xsd:string'],
                ['InternetGatewayDevice.PPP.Interface.2.Enable', true, 'xsd:boolean']
            ]]
        ];
        
        return $this->pushTasks($deviceId, $tasks);
    }
    
    /**
     * TR-181: Configure Bridge mode
     */
    public function configureBridgeTR181(string $deviceId): array {
        $tasks = [
            // Create Bridge
            ['name' => 'addObject', 'objectName' => 'InternetGatewayDevice.Bridging.Bridge.'],
            ['name' => 'setParameterValues', 'parameterValues' => [
                ['InternetGatewayDevice.Bridging.Bridge.1.Enable', true, 'xsd:boolean']
            ]],
            
            // Create Bridge Port linked to VLAN
            ['name' => 'addObject', 'objectName' => 'InternetGatewayDevice.Bridging.Bridge.1.Port.'],
            ['name' => 'setParameterValues', 'parameterValues' => [
                ['InternetGatewayDevice.Bridging.Bridge.1.Port.1.Interface', 'InternetGatewayDevice.Ethernet.VLANTermination.1', 'xsd:string']
            ]]
        ];
        
        return $this->pushTasks($deviceId, $tasks);
    }
    
    /**
     * TR-181: Configure WiFi
     */
    public function configureWiFiTR181(string $deviceId, string $ssid, string $password): array {
        return $this->setParameterValues($deviceId, [
            ['InternetGatewayDevice.WLANConfiguration.1.SSID', $ssid, 'xsd:string'],
            ['InternetGatewayDevice.WLANConfiguration.1.PreSharedKey.1.KeyPassphrase', $password, 'xsd:string'],
            ['InternetGatewayDevice.WLANConfiguration.1.Enable', true, 'xsd:boolean']
        ]);
    }
    
    /**
     * TR-181: Bind WiFi to WAN using LowerLayers
     */
    public function bindWiFiToWANTR181(string $deviceId, string $mode = 'route'): array {
        $lowerLayer = ($mode === 'bridge') 
            ? 'InternetGatewayDevice.Bridging.Bridge.1'
            : 'InternetGatewayDevice.PPP.Interface.2';
        
        return $this->setParameterValues($deviceId, [
            ['InternetGatewayDevice.WLANConfiguration.1.LowerLayers', $lowerLayer, 'xsd:string']
        ]);
    }
    
    /**
     * Unified provisioning - auto-detects data model and uses appropriate methods
     */
    public function provisionCustomerAuto(string $deviceId, array $config): array {
        $dataModel = $this->detectDataModel($deviceId);
        $results = [];
        $errors = [];
        
        $mode = $config['mode'] ?? 'route';
        $vlan = (int)($config['vlan'] ?? $config['internet_vlan'] ?? 0);
        $pppoeUser = $config['pppoe_username'] ?? '';
        $pppoePass = $config['pppoe_password'] ?? '';
        $ssid = $config['ssid'] ?? '';
        $wifiPass = $config['wifi_password'] ?? '';
        
        $this->logTR069Action($deviceId, 'provision_auto_start', ['data_model' => $dataModel]);
        
        if ($dataModel === 'tr181') {
            // TR-181 Data Model
            
            // 1. Create VLAN termination
            if ($vlan > 0) {
                $results['vlan'] = $this->createVlanTerminationTR181($deviceId, $vlan);
            }
            
            // 2. Configure PPPoE or Bridge
            if ($mode === 'route' && !empty($pppoeUser)) {
                $results['pppoe'] = $this->configurePPPoETR181($deviceId, $pppoeUser, $pppoePass);
                if (!($results['pppoe']['success'] ?? true)) {
                    $errors[] = 'TR181 PPPoE failed';
                }
            } elseif ($mode === 'bridge') {
                $results['bridge'] = $this->configureBridgeTR181($deviceId);
            }
            
            // 3. Configure WiFi
            if (!empty($ssid) && !empty($wifiPass)) {
                $results['wifi'] = $this->configureWiFiTR181($deviceId, $ssid, $wifiPass);
                $results['wifi_bind'] = $this->bindWiFiToWANTR181($deviceId, $mode);
            }
            
        } else {
            // TR-098 Data Model (default - Huawei ONUs)
            
            // 1. Secure device
            $results['secure'] = $this->secureDevice($deviceId);
            
            // 2. Configure Internet WAN
            if ($mode === 'route' && !empty($pppoeUser)) {
                $results['pppoe'] = $this->configureInternetWAN($deviceId, [
                    'connection_type' => 'pppoe',
                    'pppoe_username' => $pppoeUser,
                    'pppoe_password' => $pppoePass,
                    'service_vlan' => $vlan
                ]);
                if (!$results['pppoe']['success']) {
                    $errors[] = 'TR098 PPPoE failed';
                }
            } elseif ($mode === 'bridge') {
                $results['bridge'] = $this->provisionBridge($deviceId, $vlan);
            }
            
            // 3. Configure WiFi
            if (!empty($ssid) && !empty($wifiPass)) {
                $results['wifi'] = $this->configureWiFiSimple($deviceId, 1, $ssid, $wifiPass);
                if ($mode === 'bridge') {
                    $results['wifi_bind'] = $this->bindWiFiToWAN($deviceId, 1, 'wan1.1.ip1');
                }
            }
        }
        
        $this->logTR069Action($deviceId, 'provision_auto_complete', [
            'data_model' => $dataModel,
            'mode' => $mode,
            'success' => empty($errors)
        ]);
        
        return [
            'success' => empty($errors),
            'data_model' => $dataModel,
            'errors' => $errors,
            'results' => $results
        ];
    }
    
    /**
     * Check if WAN connection object exists on device
     * Returns the connection type (pppoe/ip) and index if found
     */
    public function checkWANConnectionExists(string $deviceId, int $wanIndex = 1): array {
        $wanBase = "InternetGatewayDevice.WANDevice.1.WANConnectionDevice.{$wanIndex}";
        
        // Get device data (skip refresh to avoid invalid path errors)
        $result = $this->getDevice($deviceId);
        if (!$result['success'] || empty($result['data'])) {
            return ['exists' => false, 'error' => 'Device not found'];
        }
        
        $device = $result['data'];
        
        // Check for PPPoE connection
        $pppPath = "InternetGatewayDevice.WANDevice.1.WANConnectionDevice.{$wanIndex}.WANPPPConnection.1";
        $pppExists = $this->getNestedValue($device, $pppPath . '.Enable') !== null ||
                     $this->getNestedValue($device, $pppPath . '.Username') !== null;
        
        // Check for IP connection
        $ipPath = "InternetGatewayDevice.WANDevice.1.WANConnectionDevice.{$wanIndex}.WANIPConnection.1";
        $ipExists = $this->getNestedValue($device, $ipPath . '.Enable') !== null ||
                    $this->getNestedValue($device, $ipPath . '.AddressingType') !== null;
        
        if ($pppExists) {
            return [
                'exists' => true,
                'type' => 'pppoe',
                'path' => $pppPath,
                'wan_index' => $wanIndex,
                'conn_index' => 1
            ];
        }
        
        if ($ipExists) {
            return [
                'exists' => true,
                'type' => 'ip',
                'path' => $ipPath,
                'wan_index' => $wanIndex,
                'conn_index' => 1
            ];
        }
        
        return ['exists' => false, 'wan_index' => $wanIndex];
    }
    
    /**
     * Get nested value from device data using dot-notation path
     */
    private function getNestedValue(array $data, string $path) {
        $parts = explode('.', $path);
        $current = $data;
        
        foreach ($parts as $part) {
            if (!isset($current[$part])) {
                return null;
            }
            $current = $current[$part];
        }
        
        // Return the _value if it exists
        if (is_array($current) && isset($current['_value'])) {
            return $current['_value'];
        }
        
        return $current;
    }
    
    /**
     * Add an object instance via TR-069 (e.g., new WANConnectionDevice)
     */
    public function addObject(string $deviceId, string $objectPath): array {
        $encodedId = rawurlencode($deviceId);
        
        // GenieACS uses tasks to add objects - use connection_request for immediate execution
        $task = [
            'name' => 'addObject',
            'objectName' => $objectPath
        ];
        
        error_log("[GenieACS] addObject: {$objectPath} on {$deviceId}");
        $result = $this->request('POST', "/devices/{$encodedId}/tasks", $task, ['timeout' => $this->timeout * 1000]);
        error_log("[GenieACS] addObject result: " . json_encode($result));
        
        return $result;
    }
    
    /**
     * Refresh/discover an object path via TR-069 getParameterNames
     */
    public function refreshObject(string $deviceId, string $objectPath): array {
        $encodedId = rawurlencode($deviceId);
        
        $task = [
            'name' => 'getParameterValues',
            'parameterNames' => [$objectPath]
        ];
        
        error_log("[GenieACS] refreshObject: {$objectPath} on {$deviceId}");
        $result = $this->request('POST', "/devices/{$encodedId}/tasks", $task, ['timeout' => $this->timeout * 1000]);
        error_log("[GenieACS] refreshObject result: " . json_encode($result));
        
        return $result;
    }
    
    /**
     * Delete an object instance via TR-069
     */
    public function deleteObject(string $deviceId, string $objectPath): array {
        $encodedId = rawurlencode($deviceId);
        
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
    
    /**
     * Run a GenieACS provision on a device
     * Provisions are pre-configured scripts stored in GenieACS that can be triggered via API
     * 
     * @param string $deviceId GenieACS device ID
     * @param string $provisionName Name of the provision to run (e.g., 'huawei-wan-pppoe')
     * @param array $args Arguments to pass to the provision
     * @param bool $connectionRequest Whether to send connection request for immediate execution
     * @return array Result with success status
     */
    public function runProvision(string $deviceId, string $provisionName, array $args = [], bool $connectionRequest = false): array {
        $encodedId = rawurlencode($deviceId);
        $endpoint = "/devices/{$encodedId}/tasks";
        
        $task = [
            'name' => 'provision',
            'provision' => $provisionName,
            'args' => $args
        ];
        
        $result = $this->request('POST', $endpoint, $task);
        
        error_log("[GenieACS] runProvision {$provisionName} on {$deviceId}: " . json_encode([
            'args' => $args,
            'success' => $result['success'] ?? false,
            'http_code' => $result['http_code'] ?? 0
        ]));
        
        return $result;
    }
    
    /**
     * Configure PPPoE WAN via GenieACS provision
     * Uses the huawei-wan-pppoe provision for reliable WAN object creation and configuration
     * 
     * @param string $deviceId GenieACS device ID
     * @param string $username PPPoE username
     * @param string $password PPPoE password
     * @param int $vlan Service VLAN (0 for untagged)
     * @return array Result with success status
     */
    public function configureWANViaProv(string $deviceId, string $username, string $password, int $vlan = 0): array {
        if (empty($username) || empty($password)) {
            return ['success' => false, 'error' => 'Username and password are required'];
        }
        
        return $this->runProvision($deviceId, 'huawei-wan-pppoe', [$username, $password, (string)$vlan]);
    }
    
    /**
     * Configure PPPoE using the correct 4-step workflow for Huawei ONUs
     * Step 1: Summon (getParameterNames) - Force discovery of WAN path
     * Step 2: AddObject - Create WANPPPConnection instance
     * Step 3: Refresh (getParameterValues) - Get all parameters
     * Step 4: SetParameterValues - Set PPPoE credentials
     * 
     * @param string $deviceId GenieACS device ID
     * @param string $username PPPoE username
     * @param string $password PPPoE password
     * @param bool $natEnabled Enable NAT (default true)
     * @param int $wanDeviceIndex WANDevice index (default 1)
     * @param int $wanConnDeviceIndex WANConnectionDevice index (default 1)
     * @return array Result with success status and step details
     */
    public function configurePPPoE4Step(string $deviceId, string $username, string $password, 
                                         bool $natEnabled = true, int $wanDeviceIndex = 1, int $wanConnDeviceIndex = 1,
                                         bool $useConnectionRequest = false): array {
        if (empty($username) || empty($password)) {
            return ['success' => false, 'error' => 'Username and password are required'];
        }
        
        $results = [];
        $basePath = "InternetGatewayDevice.WANDevice.{$wanDeviceIndex}.WANConnectionDevice.{$wanConnDeviceIndex}";
        $pppPath = "{$basePath}.WANPPPConnection";
        $encodedId = urlencode($deviceId);
        
        // Use connection_request for immediate push, or queue for next inform
        $endpoint = $useConnectionRequest 
            ? "/devices/{$encodedId}/tasks"
            : "/devices/{$encodedId}/tasks";
        
        // Step 1: Summon - Force discovery of WAN path
        error_log("[GenieACS] Step 1: Summon WAN path for {$deviceId}");
        $summonResult = $this->request('POST', $endpoint, [
            'name' => 'getParameterNames',
            'parameterPath' => $pppPath,
            'nextLevel' => true  // Get immediate children to find instances
        ]);
        $results['step1_summon'] = $summonResult;
        
        // Step 2: Read device from GenieACS to check for existing WANPPPConnection
        error_log("[GenieACS] Step 2: Read device to check existing PPP instances");
        usleep(500000); // Wait 500ms for summon to complete
        
        $device = $this->getDevice($deviceId);
        $results['step2_device_read'] = ['success' => !empty($device)];
        
        // Check for existing WANPPPConnection instances
        $existingInstance = null;
        $instanceIndex = 1;
        
        if ($device) {
            // Look for WANPPPConnection.1, .2, etc in device data
            for ($i = 1; $i <= 5; $i++) {
                $checkPath = "{$pppPath}.{$i}";
                $exists = $this->getNestedValue($device, "{$checkPath}.Enable") !== null ||
                          $this->getNestedValue($device, "{$checkPath}.Username") !== null ||
                          $this->getNestedValue($device, "{$checkPath}.ConnectionStatus") !== null;
                if ($exists) {
                    $existingInstance = $i;
                    error_log("[GenieACS] Found existing WANPPPConnection.{$i}");
                    break;
                }
            }
        }
        
        $pppInstancePath = null;
        
        if ($existingInstance) {
            // Use existing instance
            $pppInstancePath = "{$pppPath}.{$existingInstance}";
            $results['step3_instance'] = ['action' => 'use_existing', 'path' => $pppInstancePath];
            error_log("[GenieACS] Step 3: Using existing instance {$pppInstancePath}");
        } else {
            // Step 3: AddObject - Create WANPPPConnection instance
            error_log("[GenieACS] Step 3: AddObject - Creating new WANPPPConnection");
            $addObjectResult = $this->request('POST', $endpoint, [
                'name' => 'addObject',
                'objectName' => $pppPath
            ]);
            $results['step3_instance'] = ['action' => 'add_object', 'result' => $addObjectResult];
            $pppInstancePath = "{$pppPath}.1";
            
            if (!($addObjectResult['success'] ?? false)) {
                // AddObject failed, try using instance 1 anyway (might already exist)
                error_log("[GenieACS] AddObject failed, trying existing path anyway");
            }
        }
        
        // Step 4: Refresh - Get all parameters from the instance
        error_log("[GenieACS] Step 4: Refresh parameters for {$pppInstancePath}");
        $refreshResult = $this->request('POST', $endpoint, [
            'name' => 'getParameterValues',
            'parameterNames' => ["{$pppInstancePath}."]
        ]);
        $results['step4_refresh'] = $refreshResult;
        
        // Step 5: SetParameterValues - Configure PPPoE credentials
        error_log("[GenieACS] Step 5: Set PPPoE credentials for {$pppInstancePath}");
        $setParams = [
            ["{$pppInstancePath}.Enable", true, 'xsd:boolean'],
            ["{$pppInstancePath}.Username", $username, 'xsd:string'],
            ["{$pppInstancePath}.Password", $password, 'xsd:string'],
            ["{$pppInstancePath}.NATEnabled", $natEnabled, 'xsd:boolean'],
            ["{$pppInstancePath}.ConnectionType", 'IP_Routed', 'xsd:string'],
            ["{$pppInstancePath}.ConnectionTrigger", 'AlwaysOn', 'xsd:string']
        ];
        
        $setResult = $this->request('POST', $endpoint, [
            'name' => 'setParameterValues',
            'parameterValues' => $setParams
        ]);
        $results['step5_setParams'] = $setResult;
        
        // Step 6: Enable L3 routing on LAN Ethernet ports (required for PPPoE routing)
        error_log("[GenieACS] Step 6: Enable X_HW_L3Enable on LAN ports for {$deviceId}");
        $l3Params = [];
        for ($i = 1; $i <= 4; $i++) {
            $l3Params[] = ["InternetGatewayDevice.LANDevice.1.LANEthernetInterfaceConfig.{$i}.X_HW_L3Enable", true, 'xsd:boolean'];
        }
        
        $l3Result = $this->request('POST', $endpoint, [
            'name' => 'setParameterValues',
            'parameterValues' => $l3Params
        ]);
        $results['step6_l3Enable'] = $l3Result;
        
        $success = ($setResult['success'] ?? false);
        
        return [
            'success' => $success,
            'message' => $success 
                ? ($existingInstance 
                    ? "PPPoE configured on existing {$pppInstancePath}" 
                    : "PPPoE configured on new {$pppInstancePath}")
                : 'PPPoE configuration failed - check results for details',
            'instance_path' => $pppInstancePath,
            'used_existing' => (bool)$existingInstance,
            'queued' => !$useConnectionRequest,
            'results' => $results
        ];
    }
    
    /**
     * Configure PPPoE on existing WANPPPConnection instance (skip create step)
     * Use when WANPPPConnection.1 already exists
     * 
     * @param string $deviceId GenieACS device ID
     * @param string $username PPPoE username
     * @param string $password PPPoE password
     * @param bool $natEnabled Enable NAT
     * @param string $instancePath Full path to WANPPPConnection instance
     * @param bool $useConnectionRequest If true, push immediately; if false, queue for next inform
     * @return array Result with success status
     */
    public function configurePPPoEOnExisting(string $deviceId, string $username, string $password, 
                                              bool $natEnabled = true, 
                                              string $instancePath = 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1',
                                              bool $useConnectionRequest = false): array {
        if (empty($username) || empty($password)) {
            return ['success' => false, 'error' => 'Username and password are required'];
        }
        
        $encodedId = urlencode($deviceId);
        $endpoint = $useConnectionRequest 
            ? "/devices/{$encodedId}/tasks"
            : "/devices/{$encodedId}/tasks";
        
        // First refresh to ensure parameters exist in GenieACS
        $refreshResult = $this->request('POST', $endpoint, [
            'name' => 'getParameterValues',
            'parameterNames' => ["{$instancePath}."]
        ]);
        
        // Set PPPoE parameters
        $setParams = [
            ["{$instancePath}.Enable", true, 'xsd:boolean'],
            ["{$instancePath}.Username", $username, 'xsd:string'],
            ["{$instancePath}.Password", $password, 'xsd:string'],
            ["{$instancePath}.NATEnabled", $natEnabled, 'xsd:boolean']
        ];
        
        $setResult = $this->request('POST', $endpoint, [
            'name' => 'setParameterValues',
            'parameterValues' => $setParams
        ]);
        
        // Enable L3 routing on LAN Ethernet ports (required for PPPoE routing)
        $l3Params = [];
        for ($i = 1; $i <= 4; $i++) {
            $l3Params[] = ["InternetGatewayDevice.LANDevice.1.LANEthernetInterfaceConfig.{$i}.X_HW_L3Enable", true, 'xsd:boolean'];
        }
        
        $l3Result = $this->request('POST', $endpoint, [
            'name' => 'setParameterValues',
            'parameterValues' => $l3Params
        ]);
        
        $success = ($setResult['success'] ?? false) && ($l3Result['success'] ?? false);
        
        return [
            'success' => $success,
            'message' => $success 
                ? ($useConnectionRequest 
                    ? "PPPoE credentials set on {$instancePath}"
                    : "PPPoE tasks queued for {$instancePath} - will apply on next device inform")
                : 'Failed to set PPPoE credentials',
            'instance_path' => $instancePath,
            'queued' => !$useConnectionRequest,
            'set_result' => $setResult,
            'l3_result' => $l3Result
        ];
    }
    
    /**
     * Create WAN objects via provision
     * Uses wan-create provision to create WANDevice/WANConnectionDevice/WANPPPConnection structure
     * 
     * @param string $deviceId GenieACS device ID
     * @param int $wanDeviceIndex WANDevice index (default 1)
     * @param int $wanConnDeviceIndex WANConnectionDevice index (default 1)
     * @param string $connectionType 'pppoe' or 'ipoe'
     * @return array Result with success status
     */
    public function createWANObjects(string $deviceId, int $wanDeviceIndex = 1, int $wanConnDeviceIndex = 1, string $connectionType = 'pppoe'): array {
        return $this->runProvision($deviceId, 'wan-create', [
            (string)$wanDeviceIndex,
            (string)$wanConnDeviceIndex,
            $connectionType
        ]);
    }
    
    /**
     * Configure PPPoE credentials via provision (after WAN objects exist)
     * 
     * @param string $deviceId GenieACS device ID
     * @param string $username PPPoE username
     * @param string $password PPPoE password
     * @param int $vlan Service VLAN
     * @param int $wanDeviceIndex WANDevice index
     * @param int $wanConnDeviceIndex WANConnectionDevice index
     * @param int $pppConnIndex WANPPPConnection index
     * @return array Result with success status
     */
    public function configurePPPoEViaProv(string $deviceId, string $username, string $password, int $vlan = 0, 
                                           int $wanDeviceIndex = 1, int $wanConnDeviceIndex = 1, int $pppConnIndex = 1): array {
        return $this->runProvision($deviceId, 'wan-pppoe-config', [
            $username,
            $password,
            (string)$vlan,
            (string)$wanDeviceIndex,
            (string)$wanConnDeviceIndex,
            (string)$pppConnIndex
        ]);
    }
    
    /**
     * Configure IPoE/DHCP via provision
     * 
     * @param string $deviceId GenieACS device ID
     * @param int $vlan Service VLAN
     * @param string $addressingType 'DHCP' or 'Static'
     * @param int $wanDeviceIndex WANDevice index
     * @param int $wanConnDeviceIndex WANConnectionDevice index
     * @param int $ipConnIndex WANIPConnection index
     * @return array Result with success status
     */
    public function configureIPoEViaProv(string $deviceId, int $vlan = 0, string $addressingType = 'DHCP',
                                          int $wanDeviceIndex = 1, int $wanConnDeviceIndex = 1, int $ipConnIndex = 1): array {
        return $this->runProvision($deviceId, 'wan-ipoe-config', [
            (string)$vlan,
            $addressingType,
            (string)$wanDeviceIndex,
            (string)$wanConnDeviceIndex,
            (string)$ipConnIndex
        ]);
    }
    
    /**
     * Discover WAN objects via provision
     * Refreshes the WAN device tree in GenieACS
     * 
     * @param string $deviceId GenieACS device ID
     * @return array Result with success status
     */
    public function discoverWAN(string $deviceId): array {
        return $this->runProvision($deviceId, 'wan-discover', []);
    }
    
    /**
     * Full WAN provisioning workflow for Huawei ONUs
     * Step 1: Discover existing WAN structure
     * Step 2: Create WAN objects if needed
     * Step 3: Configure PPPoE/IPoE
     * 
     * @param string $deviceId GenieACS device ID
     * @param array $config WAN configuration
     * @return array Result with success status and steps
     */
    public function provisionWAN(string $deviceId, array $config): array {
        $results = [];
        $connectionType = $config['connection_type'] ?? 'pppoe';
        
        // Step 1: Discover WAN structure
        $discoverResult = $this->discoverWAN($deviceId);
        $results['discover'] = $discoverResult;
        
        // Step 2: Use the comprehensive provision for the connection type
        if ($connectionType === 'pppoe') {
            $username = $config['pppoe_username'] ?? '';
            $password = $config['pppoe_password'] ?? '';
            $vlan = (int)($config['service_vlan'] ?? $config['wan_vlan'] ?? 0);
            
            if (empty($username) || empty($password)) {
                return [
                    'success' => false,
                    'error' => 'PPPoE username and password are required',
                    'results' => $results
                ];
            }
            
            $configResult = $this->configureWANViaProv($deviceId, $username, $password, $vlan);
            $results['configure'] = $configResult;
        } else {
            $vlan = (int)($config['service_vlan'] ?? $config['wan_vlan'] ?? 0);
            $addressingType = $config['addressing_type'] ?? 'DHCP';
            
            $configResult = $this->configureIPoEViaProv($deviceId, $vlan, $addressingType);
            $results['configure'] = $configResult;
        }
        
        return [
            'success' => $configResult['success'] ?? false,
            'results' => $results,
            'message' => $configResult['success'] 
                ? 'WAN provisioning queued successfully' 
                : 'WAN provisioning failed: ' . ($configResult['error'] ?? 'Unknown error')
        ];
    }
    
    /**
     * SmartOLT-style PPPoE WAN Provisioning - EXACT REPLICATION
     * Based on chronological SmartOLT device logs analysis
     * 
     * Flow:
     * 1. Device initialization (ProvisioningCode:sOLTinit)
     * 2. LAN router mode enable (X_HW_L3Enable on all ports)
     * 3. Security baseline (HTTPWanEnable:0)
     * 4. WAN structure creation (Add WANConnectionDevice, WANPPPConnection)
     * 5. PPPoE credentials & VLAN
     * 6. Default WAN selection
     * 7. Policy routing
     * 8. Enable WAN HTTP access
     * 9. Optional: WiFi config
     */
    public function provisionPPPoESmartOLTStyle(string $deviceId, array $config): array {
        $results = [];
        $errors = [];
        
        $username = $config['pppoe_username'] ?? '';
        $password = $config['pppoe_password'] ?? '';
        $vlan = (int)($config['wan_vlan'] ?? $config['service_vlan'] ?? 900);
        $wanName = 'wan1.2.ppp1'; // SmartOLT uses WANConnectionDevice.2.WANPPPConnection.1
        $ssid = $config['ssid'] ?? '';
        $wifiPassword = $config['wifi_password'] ?? '';
        $webUsername = $config['web_username'] ?? 'superlite';
        $webPassword = $config['web_password'] ?? '';
        
        if (empty($username) || empty($password)) {
            return ['success' => false, 'error' => 'PPPoE username and password required'];
        }
        
        try {
            // =================================================================
            // STEP 1: Device Initialization
            // =================================================================
            error_log("[GenieACS SmartOLT] Step 1: Device initialization");
            $results['step1_init'] = $this->setParameterValues($deviceId, [
                ['InternetGatewayDevice.DeviceInfo.ProvisioningCode', 'sOLTinit', 'xsd:string']
            ]);
            
            // =================================================================
            // STEP 2: LAN Router Mode Enable (X_HW_L3Enable on all 4 ports)
            // =================================================================
            error_log("[GenieACS SmartOLT] Step 2: Enable LAN router mode");
            $results['step2_lan_router'] = $this->setParameterValues($deviceId, [
                ['InternetGatewayDevice.LANDevice.1.LANEthernetInterfaceConfig.1.X_HW_L3Enable', true, 'xsd:boolean'],
                ['InternetGatewayDevice.LANDevice.1.LANEthernetInterfaceConfig.2.X_HW_L3Enable', true, 'xsd:boolean'],
                ['InternetGatewayDevice.LANDevice.1.LANEthernetInterfaceConfig.3.X_HW_L3Enable', true, 'xsd:boolean'],
                ['InternetGatewayDevice.LANDevice.1.LANEthernetInterfaceConfig.4.X_HW_L3Enable', true, 'xsd:boolean']
            ]);
            
            // =================================================================
            // STEP 3: Security Baseline - Disable WAN HTTP Access
            // =================================================================
            error_log("[GenieACS SmartOLT] Step 3: Security baseline (disable WAN HTTP)");
            $results['step3_security'] = $this->setParameterValues($deviceId, [
                ['InternetGatewayDevice.X_HW_Security.AclServices.HTTPWanEnable', false, 'xsd:boolean']
            ]);
            
            // =================================================================
            // STEP 4: WAN Structure Creation
            // Add WANConnectionDevice, then WANPPPConnection
            // =================================================================
            error_log("[GenieACS SmartOLT] Step 4a: Add WANConnectionDevice");
            $results['step4a_add_wancd'] = $this->addObject($deviceId, 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.');
            
            error_log("[GenieACS SmartOLT] Step 4b: Add WANPPPConnection under WANConnectionDevice.2");
            $results['step4b_add_ppp'] = $this->addObject($deviceId, 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.2.WANPPPConnection.');
            
            // =================================================================
            // STEP 5: PPPoE Credentials & VLAN Assignment
            // =================================================================
            $pppBase = 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.2.WANPPPConnection.1';
            
            error_log("[GenieACS SmartOLT] Step 5a: Set PPPoE credentials");
            $results['step5a_credentials'] = $this->setParameterValues($deviceId, [
                ["{$pppBase}.Username", $username, 'xsd:string'],
                ["{$pppBase}.Password", $password, 'xsd:string'],
                ["{$pppBase}.NATEnabled", true, 'xsd:boolean'],
                ["{$pppBase}.X_HW_LcpEchoReqCheck", 1, 'xsd:unsignedInt'],
                ["{$pppBase}.PPPLCPEcho", 10, 'xsd:unsignedInt']
            ]);
            
            error_log("[GenieACS SmartOLT] Step 5b: Set VLAN and Name");
            $results['step5b_vlan'] = $this->setParameterValues($deviceId, [
                ["{$pppBase}.X_HW_VLAN", $vlan, 'xsd:unsignedInt'],
                ["{$pppBase}.Name", 'Internet_PPPoE', 'xsd:string']
            ]);
            
            // =================================================================
            // STEP 6: Default WAN Selection
            // =================================================================
            error_log("[GenieACS SmartOLT] Step 6: Set default WAN");
            $results['step6_default_wan'] = $this->setParameterValues($deviceId, [
                ['InternetGatewayDevice.DeviceInfo.ProvisioningCode', 'sOLT.rPPP', 'xsd:string'],
                ['InternetGatewayDevice.Layer3Forwarding.DefaultConnectionService', $pppBase, 'xsd:string'],
                ['InternetGatewayDevice.Layer3Forwarding.X_HW_WanDefaultWanName', $wanName, 'xsd:string']
            ]);
            
            // =================================================================
            // STEP 7: Security & Policy Routing
            // =================================================================
            error_log("[GenieACS SmartOLT] Step 7a: Add WAN Access ACL");
            $results['step7a_acl'] = $this->addObject($deviceId, 'InternetGatewayDevice.X_HW_Security.AclServices.WanAccess.');
            
            error_log("[GenieACS SmartOLT] Step 7b: Add policy route");
            $results['step7b_add_route'] = $this->addObject($deviceId, 'InternetGatewayDevice.Layer3Forwarding.X_HW_policy_route.');
            
            error_log("[GenieACS SmartOLT] Step 7c: Configure policy route");
            $results['step7c_route_config'] = $this->setParameterValues($deviceId, [
                ['InternetGatewayDevice.Layer3Forwarding.X_HW_policy_route.1.PhyPortName', 'LAN1,LAN2,LAN3,LAN4,SSID1', 'xsd:string'],
                ['InternetGatewayDevice.Layer3Forwarding.X_HW_policy_route.1.PolicyRouteType', 'SourcePhyPort', 'xsd:string'],
                ['InternetGatewayDevice.Layer3Forwarding.X_HW_policy_route.1.WanName', $wanName, 'xsd:string']
            ]);
            
            // =================================================================
            // STEP 8: Enable WAN HTTP Access (Post-Config)
            // =================================================================
            error_log("[GenieACS SmartOLT] Step 8: Enable WAN HTTP access");
            $results['step8_enable_http'] = $this->setParameterValues($deviceId, [
                ['InternetGatewayDevice.X_HW_Security.AclServices.HTTPWanEnable', true, 'xsd:boolean']
            ]);
            
            // =================================================================
            // STEP 9: WiFi Configuration (Optional)
            // =================================================================
            if (!empty($ssid)) {
                error_log("[GenieACS SmartOLT] Step 9: Configure WiFi");
                $wifiParams = [
                    ['InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID', $ssid, 'xsd:string']
                ];
                if (!empty($wifiPassword)) {
                    $wifiParams[] = ['InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.KeyPassphrase', $wifiPassword, 'xsd:string'];
                }
                $results['step9_wifi'] = $this->setParameterValues($deviceId, $wifiParams);
            }
            
            // =================================================================
            // STEP 10: UI & Access Credentials (Optional)
            // =================================================================
            if (!empty($webPassword)) {
                error_log("[GenieACS SmartOLT] Step 10: Set web UI credentials");
                $results['step10_web_ui'] = $this->setParameterValues($deviceId, [
                    ['InternetGatewayDevice.UserInterface.X_HW_WebUserInfo.2.UserName', $webUsername, 'xsd:string'],
                    ['InternetGatewayDevice.UserInterface.X_HW_WebUserInfo.2.Password', $webPassword, 'xsd:string']
                ]);
            }
            
            // Log the action
            $this->logTR069Action($deviceId, 'smartolt_pppoe_provision', [
                'username' => $username,
                'vlan' => $vlan,
                'wan_name' => $wanName,
                'ssid' => $ssid,
                'success' => true
            ]);
            
            return [
                'success' => true,
                'message' => 'SmartOLT-style PPPoE provisioning completed (10 steps)',
                'wan_name' => $wanName,
                'ppp_path' => $pppBase,
                'results' => $results
            ];
            
        } catch (\Exception $e) {
            error_log("[GenieACS SmartOLT] Exception: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'results' => $results
            ];
        }
    }
    
    /**
     * Simpler direct PPPoE configuration assuming WANConnectionDevice.1.WANPPPConnection.1 exists
     * Use this when the ONU has been factory-default-ed and has base WAN structure
     */
    public function configureExistingPPPoE(string $deviceId, string $username, string $password, int $vlan = 0): array {
        $pppBase = 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1';
        
        $params = [
            ["{$pppBase}.Enable", true, 'xsd:boolean'],
            ["{$pppBase}.Username", $username, 'xsd:string'],
            ["{$pppBase}.Password", $password, 'xsd:string'],
            ["{$pppBase}.NATEnabled", true, 'xsd:boolean'],
            ["{$pppBase}.X_HW_LcpEchoReqCheck", 1, 'xsd:unsignedInt']
        ];
        
        if ($vlan > 0) {
            $params[] = ["{$pppBase}.X_HW_VLAN", $vlan, 'xsd:unsignedInt'];
        }
        
        return $this->setParameterValues($deviceId, $params);
    }

    /**
     * Ensure a provision script exists in GenieACS
     * Creates or updates the provision with the given script content
     */
    public function ensureProvision(string $name, string $script): array {
        $encodedName = rawurlencode($name);
        
        // GenieACS expects provision script as raw text body with Content-Type: text/plain
        $url = $this->baseUrl . "/provisions/{$encodedName}";
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $script,
            CURLOPT_HTTPHEADER => ['Content-Type: text/plain'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $success = $httpCode >= 200 && $httpCode < 300;
        error_log("[GenieACS] ensureProvision {$name}: HTTP {$httpCode}");
        
        return ['success' => $success, 'http_code' => $httpCode];
    }
    
    /**
     * Configure PPPoE using GenieACS Provision (recommended approach)
     * Uses declare() statements for reliable object creation and configuration
     * 
     * This is the best practice method per GenieACS documentation:
     * 1. declare() with {path: 1} ensures the WANPPPConnection instance exists
     * 2. declare() with {value: x} sets the parameters
     * 
     * @param string $deviceId GenieACS device ID
     * @param string $username PPPoE username
     * @param string $password PPPoE password  
     * @param int $vlan Service VLAN (0 = untagged)
     * @param bool $natEnabled Enable NAT routing
     * @param bool $useConnectionRequest If false, just queue task (user can summon device)
     * @return array Result with success status
     */
    public function configurePPPoEViaProvision(string $deviceId, string $username, string $password, 
                                                int $vlan = 0, bool $natEnabled = true, 
                                                bool $useConnectionRequest = true): array {
        if (empty($username) || empty($password)) {
            return ['success' => false, 'error' => 'Username and password are required'];
        }
        
        $provisionName = 'pppoe-config';
        
        // Create the provision script using GenieACS declare() syntax
        // args[0] = username, args[1] = password, args[2] = vlan, args[3] = natEnabled
        $script = <<<'PROVISION'
const username = args[0];
const password = args[1];
const vlan = parseInt(args[2]) || 0;
const natEnabled = args[3] === "true" || args[3] === "1";

const pppBase = "InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection";

// Step 1: Ensure WANPPPConnection instance exists (creates if needed)
// The {path: 1} tells GenieACS to ensure exactly 1 instance exists
declare(pppBase + ".*", null, {path: Date.now()});

// Step 2: Refresh to discover what instances exist
declare(pppBase + ".*", {value: Date.now()});

// Step 3: Configure WANPPPConnection.1
const pppPath = pppBase + ".1";

declare(pppPath + ".Enable", null, {value: true});
declare(pppPath + ".Username", null, {value: username});
declare(pppPath + ".Password", null, {value: password});
declare(pppPath + ".NATEnabled", null, {value: natEnabled});
declare(pppPath + ".ConnectionType", null, {value: "IP_Routed"});
declare(pppPath + ".ConnectionTrigger", null, {value: "AlwaysOn"});

// Set VLAN if specified
if (vlan > 0) {
    declare(pppPath + ".X_HW_VLAN", null, {value: vlan});

// Step 4: Enable L3 routing on LAN ports (required for PPPoE to work)
for (let i = 1; i <= 4; i++) {
    declare("InternetGatewayDevice.LANDevice.1.LANEthernetInterfaceConfig." + i + ".X_HW_L3Enable", null, {value: true});

log("PPPoE configured: " + username + " VLAN:" + vlan);
PROVISION;

        // Step 1: Ensure provision exists in GenieACS
        $ensureResult = $this->ensureProvision($provisionName, $script);
        if (!($ensureResult['success'] ?? false)) {
            return [
                'success' => false,
                'error' => 'Failed to create provision script',
                'details' => $ensureResult
            ];
        }
        
        // Step 2: Run the provision with arguments
        $args = [$username, $password, (string)$vlan, $natEnabled ? 'true' : 'false'];
        $result = $this->runProvision($deviceId, $provisionName, $args, $useConnectionRequest);
        
        return [
            'success' => $result['success'] ?? false,
            'message' => ($result['success'] ?? false) 
                ? "PPPoE configured via provision (username: {$username}, vlan: {$vlan})"
                : 'Provision execution failed',
            'provision_name' => $provisionName,
            'http_code' => $result['http_code'] ?? 0,
            'details' => $result
        ];
    }

    /**
     * Configure PPPoE using direct setParameterValues (instant execution like WiFi)
     * This uses the same approach as editing WiFi parameters - direct and immediate
     * 
     * @param string $deviceId GenieACS device ID
     * @param string $username PPPoE username
     * @param string $password PPPoE password
     * @param int $vlan Service VLAN (for X_HW_VLAN parameter)
     * @param bool $enable Enable the connection (default true)
     * @return array Result with success status
     */
    public function configurePPPoEDirect(string $deviceId, string $username, string $password, 
                                          int $vlan = 0, bool $enable = true): array {
        if (empty($username) || empty($password)) {
            return ['success' => false, 'error' => 'Username and password are required'];
        }
        
        $pppBase = 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1';
        $lanBase = 'InternetGatewayDevice.LANDevice.1.LANEthernetInterfaceConfig';
        
        // Build parameter list (same format as WiFi config)
        $params = [
            // PPPoE connection settings
            ["{$pppBase}.Enable", $enable, 'xsd:boolean'],
            ["{$pppBase}.Username", $username, 'xsd:string'],
            ["{$pppBase}.Password", $password, 'xsd:string'],
            ["{$pppBase}.NATEnabled", true, 'xsd:boolean'],
            ["{$pppBase}.ConnectionType", 'IP_Routed', 'xsd:string'],
            ["{$pppBase}.ConnectionTrigger", 'AlwaysOn', 'xsd:string'],
            // L3 routing on LAN ports (required for PPPoE to work)
            ["{$lanBase}.1.X_HW_L3Enable", true, 'xsd:boolean'],
            ["{$lanBase}.2.X_HW_L3Enable", true, 'xsd:boolean'],
            ["{$lanBase}.3.X_HW_L3Enable", true, 'xsd:boolean'],
            ["{$lanBase}.4.X_HW_L3Enable", true, 'xsd:boolean'],
        ];
        
        // Add VLAN if specified
        if ($vlan > 0) {
            $params[] = ["{$pppBase}.X_HW_VLAN", $vlan, 'xsd:unsignedInt'];
        }
        
        error_log("[GenieACS] configurePPPoEDirect to {$deviceId}: username={$username}, vlan={$vlan}");
        
        // Use setParameterValues which already uses connection_request for instant push
        $result = $this->setParameterValues($deviceId, $params);
        
        return [
            'success' => $result['success'] ?? false,
            'message' => ($result['success'] ?? false)
                ? "PPPoE configured instantly (username: {$username}, vlan: {$vlan})"
                : 'Failed to set PPPoE parameters',
            'http_code' => $result['http_code'] ?? 0,
            'error' => $result['error'] ?? null,
            'details' => $result
        ];
    }


    /**
     * Clear Connection Request authentication credentials on the device
     * This allows GenieACS to make instant connection requests without 401 errors
     */
    public function clearConnectionRequestAuth(string $deviceId): array {
        $params = [
            ['InternetGatewayDevice.ManagementServer.ConnectionRequestUsername', '', 'xsd:string'],
            ['InternetGatewayDevice.ManagementServer.ConnectionRequestPassword', '', 'xsd:string'],
        ];
        
        error_log("[GenieACS] Clearing Connection Request auth for {$deviceId}");
        
        $result = $this->setParameterValues($deviceId, $params);
        
        return [
            'success' => $result['success'] ?? false,
            'message' => ($result['success'] ?? false)
                ? 'Connection Request authentication cleared - instant push now enabled'
                : 'Failed to clear Connection Request auth',
            'error' => $result['error'] ?? null
        ];
    }

    /**
     * Enable instant provisioning by clearing auth and configuring proper ACS URL
     * Call this after first TR-069 contact to enable instant push capability
     */
    public function enableInstantProvisioning(string $deviceId, string $acsUrl = ''): array {
        $params = [
            // Clear connection request auth
            ['InternetGatewayDevice.ManagementServer.ConnectionRequestUsername', '', 'xsd:string'],
            ['InternetGatewayDevice.ManagementServer.ConnectionRequestPassword', '', 'xsd:string'],
            // Enable periodic inform
            ['InternetGatewayDevice.ManagementServer.PeriodicInformEnable', true, 'xsd:boolean'],
            ['InternetGatewayDevice.ManagementServer.PeriodicInformInterval', 60, 'xsd:unsignedInt'],
        ];
        
        // Set ACS URL if provided
        if (!empty($acsUrl)) {
            $params[] = ['InternetGatewayDevice.ManagementServer.URL', $acsUrl, 'xsd:string'];
        }
        
        error_log("[GenieACS] Enabling instant provisioning for {$deviceId}");
        
        $result = $this->setParameterValues($deviceId, $params);
        
        return [
            'success' => $result['success'] ?? false,
            'message' => ($result['success'] ?? false)
                ? 'Instant provisioning enabled - device will respond to immediate push commands'
                : 'Failed to enable instant provisioning',
            'error' => $result['error'] ?? null
        ];
    }


    /**
     * Queue auth clear WITHOUT connection_request
     * This queues the task to execute on next device inform (avoids 401)
     */
    public function queueClearAuth(string $deviceId): array {
        $encodedId = rawurlencode($deviceId);
        
        // Queue WITHOUT connection_request - executes on next inform
        $result = $this->request('POST', "/devices/{$encodedId}/tasks", [
            'name' => 'setParameterValues',
            'parameterValues' => [
                ['InternetGatewayDevice.ManagementServer.ConnectionRequestUsername', '', 'xsd:string'],
                ['InternetGatewayDevice.ManagementServer.ConnectionRequestPassword', '', 'xsd:string'],
            ]
        ]);
        
        error_log("[GenieACS] Queued auth clear for {$deviceId} (will execute on next inform)");
        
        return [
            'success' => $result['success'] ?? false,
            'message' => 'Auth clear queued - will execute on next device inform (usually within 60 seconds)',
            'result' => $result
        ];
    }
}
