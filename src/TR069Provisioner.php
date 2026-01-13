<?php
namespace App;

class TR069Provisioner {
    private \PDO $db;
    private GenieACS $genieacs;
    
    const STATE_INIT = 'init';
    const STATE_DISCOVER = 'discover';
    const STATE_CREATE_WAN_DEVICE = 'create_wan_device';
    const STATE_CREATE_WAN_CONN_DEVICE = 'create_wan_conn_device';
    const STATE_CREATE_WAN_PPP_CONN = 'create_wan_ppp_conn';
    const STATE_SET_PPP_PARAMS = 'set_ppp_params';
    const STATE_SET_L3_HW = 'set_l3_hw';
    const STATE_SET_POLICY_ROUTES = 'set_policy_routes';
    const STATE_VERIFY = 'verify';
    const STATE_COMPLETE = 'complete';
    const STATE_FAILED = 'failed';
    const STATE_WAITING_FOR_INFORM = 'waiting_for_inform';
    
    public function __construct(\PDO $db) {
        $this->db = $db;
        $this->genieacs = new GenieACS($db);
        $this->ensureTable();
    }
    
    private function ensureTable(): void {
        $this->db->exec("CREATE TABLE IF NOT EXISTS tr069_provision_state (
            id SERIAL PRIMARY KEY,
            onu_id INTEGER NOT NULL,
            device_id VARCHAR(255),
            serial_number VARCHAR(64),
            state VARCHAR(32) DEFAULT 'init',
            config_data TEXT,
            discovered_objects TEXT,
            wan_device_instance INTEGER,
            wan_conn_device_instance INTEGER,
            wan_ppp_conn_instance INTEGER,
            retry_count INTEGER DEFAULT 0,
            last_error TEXT,
            last_task_id VARCHAR(64),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            completed_at TIMESTAMP,
            UNIQUE(onu_id)
        )");
        
        try {
            $this->db->exec("ALTER TABLE tr069_provision_state ADD COLUMN IF NOT EXISTS last_inform_at TIMESTAMP");
            $this->db->exec("ALTER TABLE tr069_provision_state ADD COLUMN IF NOT EXISTS next_step_at TIMESTAMP");
        } catch (\Exception $e) {}
    }
    
    public function startProvisioning(int $onuId, array $config): array {
        $onu = $this->getOnu($onuId);
        if (!$onu) {
            return ['success' => false, 'error' => 'ONU not found'];
        }
        
        $serial = $onu['serial_number'] ?? $onu['sn'] ?? '';
        if (empty($serial)) {
            return ['success' => false, 'error' => 'ONU serial number not found'];
        }
        
        $device = $this->genieacs->findDeviceBySerial($serial);
        if (!$device) {
            return ['success' => false, 'error' => 'Device not found in GenieACS'];
        }
        
        $deviceId = $device['_id'] ?? null;
        
        $stmt = $this->db->prepare("INSERT INTO tr069_provision_state 
            (onu_id, device_id, serial_number, state, config_data, created_at, updated_at)
            VALUES (?, ?, ?, 'init', ?, NOW(), NOW())
            ON CONFLICT (onu_id) DO UPDATE SET
                device_id = EXCLUDED.device_id,
                serial_number = EXCLUDED.serial_number,
                state = 'init',
                config_data = EXCLUDED.config_data,
                discovered_objects = NULL,
                wan_device_instance = NULL,
                wan_conn_device_instance = NULL,
                wan_ppp_conn_instance = NULL,
                retry_count = 0,
                last_error = NULL,
                last_task_id = NULL,
                updated_at = NOW(),
                completed_at = NULL
            RETURNING id");
        $stmt->execute([$onuId, $deviceId, $serial, json_encode($config)]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        $this->log($onuId, 'provision_started', 'Provisioning started', $config);
        
        return $this->processNextStep($onuId);
    }
    
    public function processNextStep(int $onuId): array {
        $state = $this->getState($onuId);
        if (!$state) {
            return ['success' => false, 'error' => 'No provisioning state found'];
        }
        
        $currentState = $state['state'];
        $config = json_decode($state['config_data'] ?? '{}', true);
        $deviceId = $state['device_id'];
        
        switch ($currentState) {
            case self::STATE_INIT:
                return $this->stepDiscover($onuId, $state);
            
            case self::STATE_DISCOVER:
                return $this->stepAnalyzeAndDecide($onuId, $state);
            
            case self::STATE_CREATE_WAN_DEVICE:
                return $this->stepCreateWanDevice($onuId, $state);
            
            case self::STATE_CREATE_WAN_CONN_DEVICE:
                return $this->stepCreateWanConnDevice($onuId, $state);
            
            case self::STATE_CREATE_WAN_PPP_CONN:
                return $this->stepCreateWanPppConn($onuId, $state);
            
            case self::STATE_SET_PPP_PARAMS:
                return $this->stepSetPppParams($onuId, $state);
            
            case self::STATE_SET_L3_HW:
                return $this->stepSetL3Hw($onuId, $state);
            
            case self::STATE_SET_POLICY_ROUTES:
                return $this->stepSetPolicyRoutes($onuId, $state);
            
            case self::STATE_VERIFY:
                return $this->stepVerify($onuId, $state);
            
            case self::STATE_COMPLETE:
                return ['success' => true, 'state' => 'complete', 'message' => 'Provisioning complete'];
            
            case self::STATE_FAILED:
                return ['success' => false, 'state' => 'failed', 'error' => $state['last_error']];
            
            case self::STATE_WAITING_FOR_INFORM:
                return $this->stepWaitingForInform($onuId, $state);
            
            default:
                return ['success' => false, 'error' => 'Unknown state: ' . $currentState];
        }
    }
    
    private function stepDiscover(int $onuId, array $state): array {
        $deviceId = $state['device_id'];
        
        $task = [
            'name' => 'getParameterValues',
            'parameterNames' => [
                'InternetGatewayDevice.WANDevice.',
                'InternetGatewayDevice.Layer3Forwarding.',
                'InternetGatewayDevice.DeviceInfo.SoftwareVersion',
                'InternetGatewayDevice.ManagementServer.ConnectionRequestURL'
            ]
        ];
        
        $result = $this->sendTask($deviceId, $task, true);
        
        if ($result['success']) {
            $this->updateState($onuId, self::STATE_DISCOVER, [
                'last_task_id' => $result['task_id'] ?? null
            ]);
            $this->log($onuId, 'discover_started', 'Object discovery started');
            
            return [
                'success' => true,
                'state' => 'discover',
                'message' => 'Discovery task queued. Waiting for device response.',
                'next_action' => 'wait_for_inform'
            ];
        }
        
        return $this->handleError($onuId, $state, 'Discovery failed: ' . ($result['error'] ?? 'Unknown'));
    }
    
    private function stepAnalyzeAndDecide(int $onuId, array $state): array {
        $deviceId = $state['device_id'];
        
        $deviceResult = $this->genieacs->getDevice($deviceId);
        if (!$deviceResult['success']) {
            return ['success' => false, 'error' => 'Could not fetch device data'];
        }
        
        $device = $deviceResult['data'];
        $config = json_decode($state['config_data'] ?? '{}', true);
        
        $discovered = $this->parseDiscoveredObjects($device);
        
        $this->updateState($onuId, null, [
            'discovered_objects' => json_encode($discovered)
        ]);
        $this->log($onuId, 'discovery_complete', 'Discovery complete', $discovered);
        
        if (!empty($discovered['existing_ppp_conn'])) {
            $this->updateState($onuId, self::STATE_SET_PPP_PARAMS, [
                'wan_device_instance' => $discovered['wan_device_instance'],
                'wan_conn_device_instance' => $discovered['wan_conn_device_instance'],
                'wan_ppp_conn_instance' => $discovered['ppp_conn_instance']
            ]);
            return $this->processNextStep($onuId);
        }
        
        if (!empty($discovered['wan_device_instance'])) {
            if (!empty($discovered['wan_conn_device_instance'])) {
                $this->updateState($onuId, self::STATE_CREATE_WAN_PPP_CONN, [
                    'wan_device_instance' => $discovered['wan_device_instance'],
                    'wan_conn_device_instance' => $discovered['wan_conn_device_instance']
                ]);
            } else {
                $this->updateState($onuId, self::STATE_CREATE_WAN_CONN_DEVICE, [
                    'wan_device_instance' => $discovered['wan_device_instance']
                ]);
            }
        } else {
            $this->updateState($onuId, self::STATE_CREATE_WAN_DEVICE);
        }
        
        return $this->processNextStep($onuId);
    }
    
    private function stepCreateWanDevice(int $onuId, array $state): array {
        $task = [
            'name' => 'addObject',
            'objectName' => 'InternetGatewayDevice.WANDevice.'
        ];
        
        $result = $this->sendTask($state['device_id'], $task, true);
        
        if ($result['success']) {
            $this->updateState($onuId, self::STATE_CREATE_WAN_CONN_DEVICE, [
                'last_task_id' => $result['task_id'] ?? null,
                'wan_device_instance' => 1
            ]);
            $this->log($onuId, 'wan_device_created', 'WANDevice.1 created (pending confirm)');
            
            return [
                'success' => true,
                'state' => 'create_wan_device',
                'message' => 'WANDevice creation queued. Wait for next inform.',
                'next_action' => 'wait_for_inform'
            ];
        }
        
        return $this->handleError($onuId, $state, 'WANDevice creation failed: ' . ($result['error'] ?? 'Unknown'));
    }
    
    private function stepCreateWanConnDevice(int $onuId, array $state): array {
        $wanDeviceInstance = $state['wan_device_instance'] ?? 1;
        
        $task = [
            'name' => 'addObject',
            'objectName' => "InternetGatewayDevice.WANDevice.{$wanDeviceInstance}.WANConnectionDevice."
        ];
        
        $result = $this->sendTask($state['device_id'], $task, true);
        
        if ($result['success']) {
            $this->updateState($onuId, self::STATE_CREATE_WAN_PPP_CONN, [
                'last_task_id' => $result['task_id'] ?? null,
                'wan_conn_device_instance' => 1
            ]);
            $this->log($onuId, 'wan_conn_device_created', 'WANConnectionDevice.1 created (pending confirm)');
            
            return [
                'success' => true,
                'state' => 'create_wan_conn_device',
                'message' => 'WANConnectionDevice creation queued. Wait for next inform.',
                'next_action' => 'wait_for_inform'
            ];
        }
        
        return $this->handleError($onuId, $state, 'WANConnectionDevice creation failed: ' . ($result['error'] ?? 'Unknown'));
    }
    
    private function stepCreateWanPppConn(int $onuId, array $state): array {
        $wanDeviceInstance = $state['wan_device_instance'] ?? 1;
        $wanConnDeviceInstance = $state['wan_conn_device_instance'] ?? 1;
        
        $task = [
            'name' => 'addObject',
            'objectName' => "InternetGatewayDevice.WANDevice.{$wanDeviceInstance}.WANConnectionDevice.{$wanConnDeviceInstance}.WANPPPConnection."
        ];
        
        $result = $this->sendTask($state['device_id'], $task, true);
        
        if ($result['success']) {
            $this->updateState($onuId, self::STATE_SET_PPP_PARAMS, [
                'last_task_id' => $result['task_id'] ?? null,
                'wan_ppp_conn_instance' => 1
            ]);
            $this->log($onuId, 'wan_ppp_conn_created', 'WANPPPConnection.1 created (pending confirm)');
            
            return [
                'success' => true,
                'state' => 'create_wan_ppp_conn',
                'message' => 'WANPPPConnection creation queued. Wait for next inform.',
                'next_action' => 'wait_for_inform'
            ];
        }
        
        return $this->handleError($onuId, $state, 'WANPPPConnection creation failed: ' . ($result['error'] ?? 'Unknown'));
    }
    
    private function stepSetPppParams(int $onuId, array $state): array {
        $config = json_decode($state['config_data'] ?? '{}', true);
        $wanDeviceInstance = $state['wan_device_instance'] ?? 1;
        $wanConnDeviceInstance = $state['wan_conn_device_instance'] ?? 1;
        $wanPppConnInstance = $state['wan_ppp_conn_instance'] ?? 1;
        
        $basePath = "InternetGatewayDevice.WANDevice.{$wanDeviceInstance}.WANConnectionDevice.{$wanConnDeviceInstance}.WANPPPConnection.{$wanPppConnInstance}";
        
        $params = [
            ["{$basePath}.Enable", true, 'xsd:boolean'],
            ["{$basePath}.ConnectionType", 'IP_Routed', 'xsd:string'],
            ["{$basePath}.Name", 'wan_ppp_1', 'xsd:string'],
            ["{$basePath}.NATEnabled", true, 'xsd:boolean']
        ];
        
        if (!empty($config['pppoe_username'])) {
            $params[] = ["{$basePath}.Username", $config['pppoe_username'], 'xsd:string'];
        }
        if (!empty($config['pppoe_password'])) {
            $params[] = ["{$basePath}.Password", $config['pppoe_password'], 'xsd:string'];
        }
        
        $serviceVlan = $config['service_vlan'] ?? $config['wan_vlan'] ?? null;
        if ($serviceVlan) {
            $params[] = ["{$basePath}.X_HW_VLAN", (int)$serviceVlan, 'xsd:unsignedInt'];
        }
        
        $task = [
            'name' => 'setParameterValues',
            'parameterValues' => $params
        ];
        
        $result = $this->sendTask($state['device_id'], $task, true);
        
        if ($result['success']) {
            $this->updateState($onuId, self::STATE_SET_L3_HW, [
                'last_task_id' => $result['task_id'] ?? null
            ]);
            $this->log($onuId, 'ppp_params_set', 'PPPoE parameters set');
            
            return [
                'success' => true,
                'state' => 'set_ppp_params',
                'message' => 'PPPoE parameters queued. Wait for next inform.',
                'next_action' => 'wait_for_inform'
            ];
        }
        
        return $this->handleError($onuId, $state, 'Set PPP params failed: ' . ($result['error'] ?? 'Unknown'));
    }
    
    private function stepSetL3Hw(int $onuId, array $state): array {
        $wanDeviceInstance = $state['wan_device_instance'] ?? 1;
        $wanConnDeviceInstance = $state['wan_conn_device_instance'] ?? 1;
        $wanPppConnInstance = $state['wan_ppp_conn_instance'] ?? 1;
        
        $params = [
            ['InternetGatewayDevice.Layer3Forwarding.X_HW_DefaultConnectionService', 
             "InternetGatewayDevice.WANDevice.{$wanDeviceInstance}.WANConnectionDevice.{$wanConnDeviceInstance}.WANPPPConnection.{$wanPppConnInstance}", 
             'xsd:string']
        ];
        
        $task = [
            'name' => 'setParameterValues',
            'parameterValues' => $params
        ];
        
        $result = $this->sendTask($state['device_id'], $task, true);
        
        if ($result['success']) {
            $this->updateState($onuId, self::STATE_SET_POLICY_ROUTES, [
                'last_task_id' => $result['task_id'] ?? null
            ]);
            $this->log($onuId, 'l3_hw_set', 'L3 forwarding default WAN set');
            
            return [
                'success' => true,
                'state' => 'set_l3_hw',
                'message' => 'L3 HW config queued. Wait for next inform.',
                'next_action' => 'wait_for_inform'
            ];
        }
        
        return $this->handleError($onuId, $state, 'L3 HW config failed: ' . ($result['error'] ?? 'Unknown'));
    }
    
    private function stepSetPolicyRoutes(int $onuId, array $state): array {
        $this->updateState($onuId, self::STATE_VERIFY);
        $this->log($onuId, 'policy_routes_skipped', 'Policy routes skipped (will be set after verify)');
        
        return $this->processNextStep($onuId);
    }
    
    private function stepVerify(int $onuId, array $state): array {
        $deviceResult = $this->genieacs->getDevice($state['device_id']);
        if (!$deviceResult['success']) {
            return $this->handleError($onuId, $state, 'Verification failed: Could not fetch device');
        }
        
        $device = $deviceResult['data'];
        $wanDeviceInstance = $state['wan_device_instance'] ?? 1;
        $wanConnDeviceInstance = $state['wan_conn_device_instance'] ?? 1;
        $wanPppConnInstance = $state['wan_ppp_conn_instance'] ?? 1;
        
        $basePath = "InternetGatewayDevice.WANDevice.{$wanDeviceInstance}.WANConnectionDevice.{$wanConnDeviceInstance}.WANPPPConnection.{$wanPppConnInstance}";
        
        $status = $this->getNestedValue($device, "{$basePath}.ConnectionStatus._value");
        $externalIp = $this->getNestedValue($device, "{$basePath}.ExternalIPAddress._value");
        
        if ($status === 'Connected' && !empty($externalIp) && $externalIp !== '0.0.0.0') {
            $this->updateState($onuId, self::STATE_COMPLETE, [
                'completed_at' => date('Y-m-d H:i:s')
            ]);
            $this->log($onuId, 'provision_complete', 'Provisioning complete', [
                'status' => $status,
                'external_ip' => $externalIp
            ]);
            
            $stmt = $this->db->prepare("UPDATE huawei_onus SET pppoe_username = ?, wan_mode = 'pppoe' WHERE id = ?");
            $config = json_decode($state['config_data'] ?? '{}', true);
            $stmt->execute([$config['pppoe_username'] ?? null, $onuId]);
            
            return [
                'success' => true,
                'state' => 'complete',
                'message' => "PPPoE connected! IP: {$externalIp}",
                'external_ip' => $externalIp
            ];
        }
        
        $retryCount = ($state['retry_count'] ?? 0) + 1;
        if ($retryCount >= 10) {
            return $this->handleError($onuId, $state, "Verification timeout: Status={$status}, IP={$externalIp}");
        }
        
        $this->updateState($onuId, self::STATE_VERIFY, [
            'retry_count' => $retryCount
        ]);
        
        return [
            'success' => true,
            'state' => 'verify',
            'message' => "Waiting for PPPoE connection... Status: {$status}, Attempt: {$retryCount}/10",
            'next_action' => 'wait_for_inform'
        ];
    }
    
    private function sendTask(string $deviceId, array $task, bool $connectionRequest = false): array {
        $encodedId = urlencode($deviceId);
        $endpoint = "/devices/{$encodedId}/tasks" . ($connectionRequest ? '?connection_request' : '');
        
        $ch = curl_init($this->genieacs->getBaseUrl() . $endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($task),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return ['success' => false, 'error' => $error];
        }
        
        $decoded = json_decode($response, true);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'task_id' => $decoded['_id'] ?? null,
                'http_code' => $httpCode
            ];
        }
        
        return [
            'success' => false,
            'error' => $decoded['message'] ?? "HTTP {$httpCode}",
            'http_code' => $httpCode
        ];
    }
    
    private function parseDiscoveredObjects(array $device): array {
        $result = [
            'wan_device_instance' => null,
            'wan_conn_device_instance' => null,
            'ppp_conn_instance' => null,
            'existing_ppp_conn' => false
        ];
        
        if (isset($device['InternetGatewayDevice']['WANDevice'])) {
            foreach ($device['InternetGatewayDevice']['WANDevice'] as $key => $value) {
                if (is_numeric($key)) {
                    $result['wan_device_instance'] = (int)$key;
                    
                    if (isset($value['WANConnectionDevice'])) {
                        foreach ($value['WANConnectionDevice'] as $connKey => $connValue) {
                            if (is_numeric($connKey)) {
                                $result['wan_conn_device_instance'] = (int)$connKey;
                                
                                if (isset($connValue['WANPPPConnection'])) {
                                    foreach ($connValue['WANPPPConnection'] as $pppKey => $pppValue) {
                                        if (is_numeric($pppKey)) {
                                            $result['ppp_conn_instance'] = (int)$pppKey;
                                            $result['existing_ppp_conn'] = true;
                                            break 3;
                                        }
                                    }
                                }
                            }
                        }
                    }
                    break;
                }
            }
        }
        
        return $result;
    }
    
    private function getNestedValue(array $data, string $path) {
        $parts = explode('.', $path);
        $value = $data;
        
        foreach ($parts as $part) {
            if (isset($value[$part])) {
                $value = $value[$part];
            } else {
                return null;
            }
        }
        
        return $value;
    }
    
    private function getState(int $onuId): ?array {
        $stmt = $this->db->prepare("SELECT * FROM tr069_provision_state WHERE onu_id = ?");
        $stmt->execute([$onuId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    
    private function updateState(int $onuId, ?string $newState, array $data = []): void {
        $sets = ['updated_at = NOW()'];
        $params = [];
        
        if ($newState !== null) {
            $sets[] = 'state = ?';
            $params[] = $newState;
        }
        
        foreach ($data as $key => $value) {
            $sets[] = "{$key} = ?";
            $params[] = $value;
        }
        
        $params[] = $onuId;
        $sql = "UPDATE tr069_provision_state SET " . implode(', ', $sets) . " WHERE onu_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }
    
    private function handleError(int $onuId, array $state, string $error): array {
        $retryCount = ($state['retry_count'] ?? 0) + 1;
        
        // Check if this is a connection-request failure (401, timeout, etc.)
        // SmartOLT behavior: Don't retry aggressively, wait for next natural inform
        $isConnectionRequestFailure = preg_match('/401|timeout|connection.?request|Unexpected status/i', $error);
        
        if ($isConnectionRequestFailure) {
            // Transition to WAITING state - don't retry, wait for next inform
            $previousState = $state['state'];
            $this->updateState($onuId, self::STATE_WAITING_FOR_INFORM, [
                'last_error' => $error,
                'previous_state' => $previousState,
                'next_step_at' => null // Will be updated on next inform
            ]);
            $this->log($onuId, 'waiting_for_inform', "Connection-request failed, waiting for next inform. Previous state: {$previousState}");
            
            return [
                'success' => true,
                'state' => 'waiting_for_inform',
                'message' => 'Waiting for device to reconnect. Will resume on next inform.',
                'next_action' => 'wait',
                'previous_state' => $previousState
            ];
        }
        
        if ($retryCount >= 3) {
            $this->updateState($onuId, self::STATE_FAILED, [
                'last_error' => $error,
                'retry_count' => $retryCount
            ]);
            $this->log($onuId, 'provision_failed', $error);
            
            return [
                'success' => false,
                'state' => 'failed',
                'error' => $error
            ];
        }
        
        $this->updateState($onuId, null, [
            'last_error' => $error,
            'retry_count' => $retryCount
        ]);
        $this->log($onuId, 'provision_retry', "Retry {$retryCount}/3: {$error}");
        
        return [
            'success' => true,
            'state' => $state['state'],
            'message' => "Retrying... ({$retryCount}/3)",
            'next_action' => 'retry'
        ];
    }
    
    /**
     * Handle WAITING_FOR_INFORM state
     * SmartOLT behavior: Don't force connection-request, wait for natural inform
     * Resume provisioning when device reconnects
     */
    private function stepWaitingForInform(int $onuId, array $state): array {
        $deviceId = $state['device_id'];
        
        // Check if device has sent a new inform since we started waiting
        $deviceResult = $this->genieacs->getDevice($deviceId);
        if (!$deviceResult['success']) {
            return [
                'success' => true,
                'state' => 'waiting_for_inform',
                'message' => 'Still waiting for device to reconnect...',
                'next_action' => 'wait'
            ];
        }
        
        $device = $deviceResult['data'];
        $lastInform = $device['_lastInform'] ?? null;
        
        // Check if device has informed recently (within last 2 minutes)
        if ($lastInform) {
            $lastInformTime = is_array($lastInform) ? ($lastInform['_value'] ?? $lastInform) : $lastInform;
            $lastInformTs = strtotime($lastInformTime);
            $stateUpdatedTs = strtotime($state['updated_at'] ?? 'now');
            
            // Device informed AFTER we entered waiting state
            if ($lastInformTs > $stateUpdatedTs) {
                $this->log($onuId, 'inform_received', 'Device reconnected, resuming provisioning');
                
                // Get the previous state to resume from
                $discovered = json_decode($state['discovered_objects'] ?? '{}', true);
                $previousState = $discovered['previous_state'] ?? null;
                
                // Determine next state based on what we have
                $nextState = $this->determineNextState($state);
                $this->updateState($onuId, $nextState, [
                    'retry_count' => 0,
                    'last_error' => null
                ]);
                
                return $this->processNextStep($onuId);
            }
        }
        
        // Still waiting
        $waitingMinutes = round((time() - strtotime($state['updated_at'] ?? 'now')) / 60);
        
        return [
            'success' => true,
            'state' => 'waiting_for_inform',
            'message' => "Waiting for device to reconnect ({$waitingMinutes} min). Will resume automatically on next inform.",
            'next_action' => 'wait',
            'waiting_since' => $state['updated_at']
        ];
    }
    
    /**
     * Determine what state to resume from based on current progress
     */
    private function determineNextState(array $state): string {
        $wanDevice = $state['wan_device_instance'] ?? null;
        $wanConnDevice = $state['wan_conn_device_instance'] ?? null;
        $wanPppConn = $state['wan_ppp_conn_instance'] ?? null;
        
        if ($wanPppConn !== null) {
            return self::STATE_SET_PPP_PARAMS;
        }
        if ($wanConnDevice !== null) {
            return self::STATE_CREATE_WAN_PPP_CONN;
        }
        if ($wanDevice !== null) {
            return self::STATE_CREATE_WAN_CONN_DEVICE;
        }
        
        // Re-discover to get current state
        return self::STATE_DISCOVER;
    }
    
    private function getOnu(int $onuId): ?array {
        $stmt = $this->db->prepare("SELECT * FROM huawei_onus WHERE id = ?");
        $stmt->execute([$onuId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    
    private function log(int $onuId, string $type, string $message, ?array $details = null): void {
        try {
            $this->db->exec("CREATE TABLE IF NOT EXISTS tr069_provision_logs (
                id SERIAL PRIMARY KEY,
                onu_id INTEGER NOT NULL,
                log_type VARCHAR(32),
                message TEXT,
                details TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
            
            $stmt = $this->db->prepare("INSERT INTO tr069_provision_logs (onu_id, log_type, message, details) VALUES (?, ?, ?, ?)");
            $stmt->execute([$onuId, $type, $message, $details ? json_encode($details) : null]);
        } catch (\Exception $e) {}
    }
    
    public function getProvisioningStatus(int $onuId): array {
        $state = $this->getState($onuId);
        if (!$state) {
            return ['success' => false, 'error' => 'No provisioning in progress'];
        }
        
        $stmt = $this->db->prepare("SELECT * FROM tr069_provision_logs WHERE onu_id = ? ORDER BY created_at DESC LIMIT 10");
        $stmt->execute([$onuId]);
        $logs = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'state' => $state['state'],
            'config' => json_decode($state['config_data'] ?? '{}', true),
            'discovered' => json_decode($state['discovered_objects'] ?? '{}', true),
            'instances' => [
                'wan_device' => $state['wan_device_instance'],
                'wan_conn_device' => $state['wan_conn_device_instance'],
                'wan_ppp_conn' => $state['wan_ppp_conn_instance']
            ],
            'retry_count' => $state['retry_count'],
            'last_error' => $state['last_error'],
            'created_at' => $state['created_at'],
            'updated_at' => $state['updated_at'],
            'completed_at' => $state['completed_at'],
            'logs' => $logs
        ];
    }
    
    public function cancelProvisioning(int $onuId): array {
        $state = $this->getState($onuId);
        if (!$state) {
            return ['success' => false, 'error' => 'No provisioning in progress'];
        }
        
        $this->updateState($onuId, self::STATE_FAILED, [
            'last_error' => 'Cancelled by user'
        ]);
        $this->log($onuId, 'provision_cancelled', 'Provisioning cancelled by user');
        
        return ['success' => true, 'message' => 'Provisioning cancelled'];
    }
    
    public function onDeviceInform(string $deviceId): array {
        $stmt = $this->db->prepare("SELECT * FROM tr069_provision_state WHERE device_id = ? AND state NOT IN ('complete', 'failed')");
        $stmt->execute([$deviceId]);
        $state = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$state) {
            return ['success' => false, 'error' => 'No active provisioning for this device'];
        }
        
        $this->updateState($state['onu_id'], null, [
            'last_inform_at' => date('Y-m-d H:i:s')
        ]);
        
        return $this->processNextStep($state['onu_id']);
    }
}
