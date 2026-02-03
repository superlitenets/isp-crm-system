<?php
date_default_timezone_set('Africa/Nairobi');
require_once __DIR__ . '/../src/RadiusBilling.php';
$radiusBilling = new \App\RadiusBilling($db);

// Handle AJAX API actions
$action = $_GET['action'] ?? '';

if ($action === 'get_mikrotik_script' && isset($_GET['peer_id'])) {
    header('Content-Type: application/json');
    require_once __DIR__ . '/../src/WireGuardService.php';
    $wgService = new \App\WireGuardService($db);
    $script = $wgService->getMikroTikScript((int)$_GET['peer_id']);
    echo json_encode(['success' => !empty($script), 'script' => $script]);
    exit;
}

if ($action === 'get_nas_config' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $nasData = $radiusBilling->getNASWithVPN((int)$_GET['id']);
    
    if (!$nasData) {
        echo json_encode(['success' => false, 'error' => 'NAS not found']);
        exit;
    }
    
    $response = [
        'success' => true,
        'secret' => $nasData['secret'] ?? '',
        'radius_server' => $_ENV['RADIUS_SERVER_IP'] ?? '',
        'vpn_script' => null
    ];
    
    // If VPN peer is linked, get VPN server IP as RADIUS server and VPN script
    if (!empty($nasData['wireguard_peer_id'])) {
        require_once __DIR__ . '/../src/WireGuardService.php';
        $wgService = new \App\WireGuardService($db);
        $peer = $wgService->getPeer((int)$nasData['wireguard_peer_id']);
        
        if ($peer) {
            $server = $wgService->getServer((int)$peer['server_id']);
            if ($server) {
                // Use the VPN server's interface IP (without CIDR) as RADIUS server
                $vpnServerIp = preg_replace('/\/\d+$/', '', $server['interface_addr'] ?? '');
                if ($vpnServerIp) {
                    $response['radius_server'] = $vpnServerIp;
                }
            }
            // Get MikroTik VPN script
            $response['vpn_script'] = $wgService->getMikroTikScript((int)$nasData['wireguard_peer_id']);
        }
    }
    
    echo json_encode($response);
    exit;
}

if ($action === 'ping_nas' && isset($_GET['ip'])) {
    header('Content-Type: application/json');
    $ip = filter_var($_GET['ip'], FILTER_VALIDATE_IP);
    if (!$ip) {
        echo json_encode(['online' => false, 'error' => 'Invalid IP']);
        exit;
    }
    $online = false;
    $portsToCheck = [22, 23, 80, 443, 8291, 8728];
    foreach ($portsToCheck as $port) {
        $socket = @fsockopen($ip, $port, $errno, $errstr, 1);
        if ($socket) {
            fclose($socket);
            $online = true;
            break;
        }
    }
    echo json_encode(['online' => $online, 'ip' => $ip]);
    exit;
}

if ($action === 'preview_bulk_sms') {
    header('Content-Type: application/json');
    $filters = [
        'status' => $_GET['status'] ?? '',
        'location_id' => $_GET['location'] ?? '',
        'package_id' => $_GET['package'] ?? ''
    ];
    $subscribers = $radiusBilling->getSubscribersByFilter($filters);
    echo json_encode([
        'success' => true,
        'count' => count($subscribers),
        'subscribers' => array_slice($subscribers, 0, 50)
    ]);
    exit;
}

if ($action === 'search_subscribers') {
    header('Content-Type: application/json');
    
    $search = trim($_GET['q'] ?? '');
    $activeOnly = isset($_GET['active_only']);
    
    $query = "
        SELECT s.id, s.username, s.status, c.name as customer_name, c.phone as customer_phone
        FROM radius_subscriptions s
        LEFT JOIN customers c ON s.customer_id = c.id
        WHERE 1=1
    ";
    $params = [];
    
    if ($activeOnly) {
        $query .= " AND s.status IN ('active', 'grace')";
    }
    
    if ($search) {
        $query .= " AND (s.username ILIKE ? OR c.name ILIKE ? OR c.phone ILIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $query .= " ORDER BY c.name, s.username LIMIT 100";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $subscribers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'subscribers' => $subscribers]);
    exit;
}

if ($action === 'stk_push') {
    header('Content-Type: application/json');
    
    if (empty($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $phone = preg_replace('/[^0-9]/', '', $input['phone'] ?? '');
    $amount = (int)($input['amount'] ?? 0);
    $subscriptionId = (int)($input['subscription_id'] ?? 0);
    
    if (!$phone || strlen($phone) < 9) {
        echo json_encode(['success' => false, 'error' => 'Invalid phone number']);
        exit;
    }
    if ($amount < 1 || $amount > 150000) {
        echo json_encode(['success' => false, 'error' => 'Amount must be between 1 and 150,000']);
        exit;
    }
    
    $stmt = $db->prepare("SELECT id FROM radius_subscriptions WHERE id = ?");
    $stmt->execute([$subscriptionId]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Invalid subscription']);
        exit;
    }
    
    try {
        require_once __DIR__ . '/../src/Mpesa.php';
        $mpesa = new \App\Mpesa();
        $result = $mpesa->stkPush($phone, $amount, 'radius_' . $subscriptionId, 'Internet subscription');
        echo json_encode(['success' => true, 'result' => $result]);
    } catch (\Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'get_live_traffic') {
    header('Content-Type: application/json');
    
    if (empty($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    
    $subscriptionId = (int)($_GET['subscription_id'] ?? 0);
    
    $stmt = $db->prepare("
        SELECT s.*, n.ip_address as nas_ip, n.api_port, n.api_username, n.api_password_encrypted
        FROM radius_subscriptions s 
        LEFT JOIN radius_nas n ON s.nas_id = n.id
        WHERE s.id = ?
    ");
    $stmt->execute([$subscriptionId]);
    $sub = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$sub) {
        echo json_encode(['success' => false, 'error' => 'Subscription not found']);
        exit;
    }
    
    // Auto-detect NAS if not explicitly assigned
    if (!$sub['nas_ip']) {
        $sessionNas = null;
        
        // Try to find NAS from active session (wrapped in try-catch for schema compatibility)
        try {
            $sessionStmt = $db->prepare("
                SELECT rn.id as nas_id, rn.ip_address as nas_ip, rn.api_port, rn.api_username, rn.api_password_encrypted
                FROM radius_sessions rs
                JOIN radius_nas rn ON rs.nas_id = rn.id
                WHERE rs.subscription_id = ? AND rn.api_enabled = TRUE
                ORDER BY rs.id DESC
                LIMIT 1
            ");
            $sessionStmt->execute([$subscriptionId]);
            $sessionNas = $sessionStmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Schema mismatch - skip session lookup
            $sessionNas = null;
        }
        
        if ($sessionNas) {
            // Found NAS from session - use it and update subscription
            $sub['nas_ip'] = $sessionNas['nas_ip'];
            $sub['api_port'] = $sessionNas['api_port'];
            $sub['api_username'] = $sessionNas['api_username'];
            $sub['api_password_encrypted'] = $sessionNas['api_password_encrypted'];
            
            // Auto-assign NAS to subscription for future
            $updateStmt = $db->prepare("UPDATE radius_subscriptions SET nas_id = ? WHERE id = ? AND (nas_id IS NULL OR nas_id != ?)");
            $updateStmt->execute([$sessionNas['nas_id'], $subscriptionId, $sessionNas['nas_id']]);
        } else {
            // No active session - try auto-detect from NAS count
            $countStmt = $db->query("SELECT COUNT(*) FROM radius_nas WHERE is_active = TRUE AND api_enabled = TRUE");
            $nasCount = (int)$countStmt->fetchColumn();
            
            if ($nasCount === 0) {
                echo json_encode(['success' => false, 'error' => 'No NAS device with API enabled. Please add a NAS device and enable API access.']);
                exit;
            } elseif ($nasCount === 1) {
                // Only one NAS - safe to use automatically
                $nasStmt = $db->query("
                    SELECT id as nas_id, ip_address as nas_ip, api_port, api_username, api_password_encrypted 
                    FROM radius_nas 
                    WHERE is_active = TRUE AND api_enabled = TRUE 
                    LIMIT 1
                ");
                $autoNas = $nasStmt->fetch(PDO::FETCH_ASSOC);
                $sub['nas_ip'] = $autoNas['nas_ip'];
                $sub['api_port'] = $autoNas['api_port'];
                $sub['api_username'] = $autoNas['api_username'];
                $sub['api_password_encrypted'] = $autoNas['api_password_encrypted'];
                
                // Auto-assign NAS to subscription
                $updateStmt = $db->prepare("UPDATE radius_subscriptions SET nas_id = ? WHERE id = ? AND nas_id IS NULL");
                $updateStmt->execute([$autoNas['nas_id'], $subscriptionId]);
            } else {
                // Multiple NAS devices and no active session - require explicit assignment
                echo json_encode(['success' => false, 'error' => 'Multiple NAS devices found and no active session. Please assign a specific NAS to this subscription.']);
                exit;
            }
        }
    }
    
    try {
        require_once __DIR__ . '/../src/MikroTikAPI.php';
        
        $apiPassword = '';
        if (!empty($sub['api_password_encrypted'])) {
            $encKey = getenv('ENCRYPTION_KEY');
            if (empty($encKey)) {
                $encKey = $_ENV['ENCRYPTION_KEY'] ?? 'default-radius-key-change-me';
            }
            $decoded = base64_decode($sub['api_password_encrypted']);
            if ($decoded !== false && strlen($decoded) > 16) {
                $iv = substr($decoded, 0, 16);
                $encrypted = substr($decoded, 16);
                $apiPassword = openssl_decrypt($encrypted, 'AES-256-CBC', $encKey, 0, $iv) ?: '';
            }
        }
        
        $api = new \App\MikroTikAPI(
            $sub['nas_ip'],
            (int)($sub['api_port'] ?? 8728),
            $sub['api_username'] ?? 'admin',
            $apiPassword
        );
        $api->connect();
        
        $accessType = strtolower($sub['access_type'] ?? 'pppoe');
        
        if ($accessType === 'pppoe') {
            $trafficData = $api->getPPPoESessionTraffic($sub['username']);
        } elseif ($accessType === 'dhcp' && !empty($sub['mac_address'])) {
            $trafficData = $api->getDHCPLeaseTraffic($sub['mac_address']);
        } elseif (!empty($sub['static_ip'])) {
            $queues = $api->command('/queue/simple/print', ['?target' => $sub['static_ip'] . '/32']);
            if (!empty($queues)) {
                $queue = $queues[0];
                $bytesStr = $queue['bytes'] ?? '0/0';
                $parts = explode('/', $bytesStr);
                $trafficData = [
                    'online' => true,
                    'ip' => $sub['static_ip'],
                    'rx_bytes' => (int)($parts[0] ?? 0),
                    'tx_bytes' => (int)($parts[1] ?? 0),
                    'timestamp' => time() * 1000
                ];
            } else {
                $trafficData = ['online' => false, 'error' => 'No queue found for static IP'];
            }
        } else {
            $trafficData = ['online' => false, 'error' => 'Unknown access type or missing configuration'];
        }
        
        $api->disconnect();
        
        echo json_encode(['success' => true, 'data' => $trafficData]);
    } catch (\Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'link_onu') {
    header('Content-Type: application/json');
    
    if (empty($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $subscriptionId = (int)($input['subscription_id'] ?? 0);
    $onuId = $input['onu_id'] !== null ? (int)$input['onu_id'] : null;
    
    if (!$subscriptionId) {
        echo json_encode(['success' => false, 'error' => 'Subscription ID required']);
        exit;
    }
    
    // Verify ONU exists if provided
    if ($onuId !== null && $onuId > 0) {
        $stmt = $db->prepare("SELECT id, name, serial_number, genieacs_id FROM huawei_onus WHERE id = ?");
        $stmt->execute([$onuId]);
        $onu = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$onu) {
            echo json_encode(['success' => false, 'error' => 'ONU not found']);
            exit;
        }
        if (empty($onu['genieacs_id'])) {
            echo json_encode(['success' => false, 'error' => 'This ONU does not have a GenieACS device ID. Please configure TR-069 for this ONU first.']);
            exit;
        }
    }
    
    $stmt = $db->prepare("UPDATE radius_subscriptions SET huawei_onu_id = ? WHERE id = ?");
    $stmt->execute([$onuId > 0 ? $onuId : null, $subscriptionId]);
    
    echo json_encode(['success' => true, 'message' => $onuId ? 'ONU linked successfully' : 'ONU unlinked']);
    exit;
}

if ($action === 'search_onus') {
    header('Content-Type: application/json');
    
    if (empty($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    
    $search = trim($_GET['q'] ?? '');
    
    $query = "SELECT id, name, serial_number, genieacs_id, status FROM huawei_onus WHERE genieacs_id IS NOT NULL AND genieacs_id != ''";
    $params = [];
    
    if ($search) {
        $query .= " AND (name ILIKE ? OR serial_number ILIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $query .= " ORDER BY name ASC LIMIT 50";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $onus = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'onus' => $onus]);
    exit;
}

if ($action === 'get_wifi_config') {
    header('Content-Type: application/json');
    
    if (empty($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    
    $subscriptionId = (int)($_GET['subscription_id'] ?? 0);
    
    $stmt = $db->prepare("
        SELECT s.*, c.phone as customer_phone, ho.genieacs_id as onu_genieacs_id, ho.name as onu_name
        FROM radius_subscriptions s 
        LEFT JOIN customers c ON s.customer_id = c.id 
        LEFT JOIN huawei_onus ho ON s.huawei_onu_id = ho.id
        WHERE s.id = ?
    ");
    $stmt->execute([$subscriptionId]);
    $sub = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$sub) {
        echo json_encode(['success' => false, 'error' => 'Subscription not found']);
        exit;
    }
    
    $deviceId = null;
    
    // First priority: linked ONU's GenieACS ID
    if (!empty($sub['onu_genieacs_id'])) {
        $deviceId = $sub['onu_genieacs_id'];
    }
    
    // Second: try to find by customer phone
    if (!$deviceId) {
        $phone = preg_replace('/[^0-9]/', '', $sub['customer_phone'] ?? '');
        if ($phone && strlen($phone) >= 9) {
            $phoneSearch = '%' . substr($phone, -9) . '%';
            $stmt = $db->prepare("SELECT _id FROM genieacs_devices WHERE CAST(_deviceid AS TEXT) ILIKE ? OR CAST(serial_number AS TEXT) ILIKE ? ORDER BY last_inform DESC LIMIT 1");
            $stmt->execute([$phoneSearch, $phoneSearch]);
            $device = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($device) {
                $deviceId = $device['_id'];
            }
        }
    }
    
    // Third: try to find by MAC address
    if (!$deviceId && $sub['mac_address']) {
        $macSearch = '%' . strtoupper(str_replace([':', '-'], '', $sub['mac_address'])) . '%';
        $stmt = $db->prepare("SELECT _id FROM genieacs_devices WHERE CAST(_deviceid AS TEXT) ILIKE ? ORDER BY last_inform DESC LIMIT 1");
        $stmt->execute([$macSearch]);
        $device = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($device) {
            $deviceId = $device['_id'];
        }
    }
    
    if (!$deviceId) {
        echo json_encode(['success' => false, 'error' => 'No TR-069 device found. Please link an ONU to this subscriber in the Device section below.', 'no_onu' => true]);
        exit;
    }
    
    $genieUrl = getenv('GENIEACS_URL') ?: 'http://localhost:7557';
    $encoded = rawurlencode($deviceId);
    
    $wifiParams = [
        '2.4' => [
            'ssid' => 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID',
            'password' => 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.PreSharedKey'
        ],
        '5' => [
            'ssid' => 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.5.SSID',
            'password' => 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.5.PreSharedKey.1.PreSharedKey'
        ]
    ];
    
    $result = ['success' => true, 'device_id' => $deviceId];
    
    foreach (['2.4', '5'] as $band) {
        $ssidPath = $wifiParams[$band]['ssid'];
        $ch = curl_init("$genieUrl/devices/$encoded/tasks?timeout=5000");
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['name' => 'getParameterValues', 'parameterNames' => [$ssidPath]]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10
        ]);
        $resp = curl_exec($ch);
        curl_close($ch);
        
        if ($resp) {
            $ch = curl_init("$genieUrl/devices/$encoded?projection=$ssidPath");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $deviceData = curl_exec($ch);
            curl_close($ch);
            
            if ($deviceData) {
                $data = json_decode($deviceData, true);
                $ssidValue = $data[$ssidPath]['_value'] ?? null;
                if ($ssidValue) {
                    $result['wifi_' . str_replace('.', '', $band)] = [
                        'ssid' => $ssidValue,
                        'password' => ''
                    ];
                }
            }
        }
    }
    
    if (empty($result['wifi_24']) && empty($result['wifi_5'])) {
        $result['wifi_24'] = ['ssid' => '', 'password' => ''];
    }
    
    echo json_encode($result);
    exit;
}

if ($action === 'set_wifi_config') {
    header('Content-Type: application/json');
    
    if (empty($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $subscriptionId = (int)($input['subscription_id'] ?? 0);
    $band = $input['band'] ?? '2.4';
    $ssid = trim($input['ssid'] ?? '');
    $password = $input['password'] ?? '';
    
    if (!$ssid || strlen($ssid) > 32) {
        echo json_encode(['success' => false, 'error' => 'WiFi name is required (max 32 characters)']);
        exit;
    }
    if ($password && (strlen($password) < 8 || strlen($password) > 63)) {
        echo json_encode(['success' => false, 'error' => 'Password must be 8-63 characters']);
        exit;
    }
    
    $stmt = $db->prepare("
        SELECT s.*, c.phone as customer_phone, ho.genieacs_id as onu_genieacs_id
        FROM radius_subscriptions s 
        LEFT JOIN customers c ON s.customer_id = c.id 
        LEFT JOIN huawei_onus ho ON s.huawei_onu_id = ho.id
        WHERE s.id = ?
    ");
    $stmt->execute([$subscriptionId]);
    $sub = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$sub) {
        echo json_encode(['success' => false, 'error' => 'Subscription not found']);
        exit;
    }
    
    $deviceId = null;
    
    // First priority: linked ONU's GenieACS ID
    if (!empty($sub['onu_genieacs_id'])) {
        $deviceId = $sub['onu_genieacs_id'];
    }
    
    // Second: try to find by customer phone
    if (!$deviceId) {
        $phone = preg_replace('/[^0-9]/', '', $sub['customer_phone'] ?? '');
        if ($phone && strlen($phone) >= 9) {
            $phoneSearch = '%' . substr($phone, -9) . '%';
            $stmt = $db->prepare("SELECT _id FROM genieacs_devices WHERE CAST(_deviceid AS TEXT) ILIKE ? OR CAST(serial_number AS TEXT) ILIKE ? ORDER BY last_inform DESC LIMIT 1");
            $stmt->execute([$phoneSearch, $phoneSearch]);
            $device = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($device) {
                $deviceId = $device['_id'];
            }
        }
    }
    
    // Third: try to find by MAC address
    if (!$deviceId && $sub['mac_address']) {
        $macSearch = '%' . strtoupper(str_replace([':', '-'], '', $sub['mac_address'])) . '%';
        $stmt = $db->prepare("SELECT _id FROM genieacs_devices WHERE CAST(_deviceid AS TEXT) ILIKE ? ORDER BY last_inform DESC LIMIT 1");
        $stmt->execute([$macSearch]);
        $device = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($device) {
            $deviceId = $device['_id'];
        }
    }
    
    if (!$deviceId) {
        echo json_encode(['success' => false, 'error' => 'No TR-069 device found']);
        exit;
    }
    
    $genieUrl = getenv('GENIEACS_URL') ?: 'http://localhost:7557';
    $encoded = rawurlencode($deviceId);
    
    $wlanIndex = $band === '5' ? '5' : '1';
    $ssidPath = "InternetGatewayDevice.LANDevice.1.WLANConfiguration.$wlanIndex.SSID";
    $pskPath = "InternetGatewayDevice.LANDevice.1.WLANConfiguration.$wlanIndex.PreSharedKey.1.PreSharedKey";
    
    $params = [[$ssidPath, $ssid, 'xsd:string']];
    if ($password) {
        $params[] = [$pskPath, $password, 'xsd:string'];
    }
    
    $ch = curl_init("$genieUrl/devices/$encoded/tasks?timeout=30000");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['name' => 'setParameterValues', 'parameterValues' => $params]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 35
    ]);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode >= 200 && $httpCode < 300) {
        echo json_encode(['success' => true, 'message' => "WiFi settings applied. Device will update shortly."]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to apply settings. Device may be offline.']);
    }
    exit;
}

if ($action === 'get_nas_vpn' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $nasData = $radiusBilling->getNASWithVPN((int)$_GET['id']);
    if ($nasData && $nasData['wireguard_peer_id']) {
        require_once __DIR__ . '/../src/WireGuardService.php';
        $wgService = new \App\WireGuardService($db);
        $peer = $wgService->getPeer((int)$nasData['wireguard_peer_id']);
        $server = $peer ? $wgService->getServer((int)$peer['server_id']) : null;
        
        echo json_encode([
            'success' => true,
            'vpn' => [
                'peer_addr' => $peer['allowed_ips'] ?? '',
                'peer_private_key' => $peer['private_key'] ?? '',
                'server_pubkey' => $server['public_key'] ?? '',
                'server_endpoint' => $server['endpoint'] ?? gethostbyname(gethostname()),
                'server_port' => $server['listen_port'] ?? 51820,
                'server_addr' => $server['interface_addr'] ?? '',
                'server_network' => $server['interface_addr'] ? preg_replace('/\.\d+\//', '.0/', $server['interface_addr']) : '10.200.0.0/24',
                'psk' => $peer['preshared_key'] ?? ''
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'No VPN configured']);
    }
    exit;
}

if ($action === 'get_online_subscribers') {
    header('Content-Type: application/json');
    try {
        $onlineSubs = $radiusBilling->getOnlineSubscribers();
        $onlineIds = array_keys($onlineSubs);
        echo json_encode([
            'success' => true,
            'count' => count($onlineIds),
            'online_ids' => $onlineIds
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'test_nas') {
    header('Content-Type: application/json');
    $id = (int)($_GET['id'] ?? 0);
    if ($id) {
        $nas = $radiusBilling->getNAS($id);
        if ($nas) {
            $result = ['success' => true, 'online' => false];
            $ports = [$nas['api_port'] ?: 8728, 22, 80, 443];
            foreach ($ports as $port) {
                $socket = @fsockopen($nas['ip_address'], $port, $errno, $errstr, 2);
                if ($socket) {
                    fclose($socket);
                    $result['online'] = true;
                    $result['reachable_port'] = $port;
                    $result['latency_ms'] = round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 1);
                    break;
                }
            }
            if ($result['online'] && $nas['api_enabled']) {
                $api = new MikroTikAPI();
                $start = microtime(true);
                if ($api->connect($nas['ip_address'], $nas['api_username'], $nas['api_password'], $nas['api_port'] ?: 8728)) {
                    $result['api_online'] = true;
                    $result['api_latency_ms'] = round((microtime(true) - $start) * 1000, 1);
                    $api->disconnect();
                } else {
                    $result['api_online'] = false;
                }
            }
            echo json_encode($result);
        } else {
            echo json_encode(['success' => false, 'error' => 'NAS not found']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid NAS ID']);
    }
    exit;
}

if ($action === 'reboot_nas') {
    header('Content-Type: application/json');
    $id = (int)($_GET['id'] ?? 0);
    if ($id) {
        $nas = $radiusBilling->getNAS($id);
        if ($nas && $nas['api_enabled']) {
            $api = new MikroTikAPI();
            if ($api->connect($nas['ip_address'], $nas['api_username'], $nas['api_password'], $nas['api_port'] ?: 8728)) {
                $result = $api->sendCommand('/system/reboot');
                $api->disconnect();
                echo json_encode(['success' => true, 'message' => 'Reboot command sent']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to connect to MikroTik API']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'NAS not found or API not enabled']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid NAS ID']);
    }
    exit;
}

// Real-time CoA AJAX endpoints
if ($action === 'ajax_reset_mac') {
    header('Content-Type: application/json');
    $id = (int)($_GET['id'] ?? 0);
    if ($id) {
        try {
            $stmt = $db->prepare("DELETE FROM radius_subscription_macs WHERE subscription_id = ?");
            $stmt->execute([$id]);
            $deleted = $stmt->rowCount();
            // Also clear primary mac_address field
            $db->prepare("UPDATE radius_subscriptions SET mac_address = NULL WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true, 'message' => "Cleared {$deleted} registered device(s)"]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid subscription ID']);
    }
    exit;
}

if ($action === 'ajax_disconnect') {
    header('Content-Type: application/json');
    $id = (int)($_GET['id'] ?? 0);
    if ($id) {
        $result = $radiusBilling->disconnectUser($id);
        echo json_encode($result);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid subscription ID']);
    }
    exit;
}

if ($action === 'ajax_activate') {
    header('Content-Type: application/json');
    $id = (int)($_GET['id'] ?? 0);
    if ($id) {
        $result = $radiusBilling->activateSubscription($id);
        echo json_encode($result);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid subscription ID']);
    }
    exit;
}

if ($action === 'ajax_suspend') {
    header('Content-Type: application/json');
    $id = (int)($_GET['id'] ?? 0);
    if ($id) {
        $result = $radiusBilling->suspendSubscription($id);
        echo json_encode($result);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid subscription ID']);
    }
    exit;
}

if ($action === 'ajax_unsuspend') {
    header('Content-Type: application/json');
    $id = (int)($_GET['id'] ?? 0);
    if ($id) {
        $result = $radiusBilling->unsuspendSubscription($id);
        echo json_encode($result);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid subscription ID']);
    }
    exit;
}

if ($action === 'ajax_renew') {
    header('Content-Type: application/json');
    $id = (int)($_GET['id'] ?? 0);
    if ($id) {
        $result = $radiusBilling->renewSubscription($id);
        echo json_encode($result);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid subscription ID']);
    }
    exit;
}

if ($action === 'ajax_speed_update') {
    header('Content-Type: application/json');
    $id = (int)($_GET['id'] ?? 0);
    if ($id) {
        $result = $radiusBilling->sendSpeedUpdateCoA($id);
        echo json_encode($result);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid subscription ID']);
    }
    exit;
}

$view = $_GET['view'] ?? 'dashboard';
$message = '';
$messageType = 'info';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_nas':
            $result = $radiusBilling->createNAS($_POST);
            $message = $result['success'] ? 'NAS device added successfully' : 'Error: ' . ($result['error'] ?? 'Unknown error');
            $messageType = $result['success'] ? 'success' : 'danger';
            break;
            
        case 'update_nas':
            $result = $radiusBilling->updateNAS((int)$_POST['id'], $_POST);
            $message = $result['success'] ? 'NAS device updated' : 'Error: ' . ($result['error'] ?? 'Unknown error');
            $messageType = $result['success'] ? 'success' : 'danger';
            break;
            
        case 'delete_nas':
            $result = $radiusBilling->deleteNAS((int)$_POST['id']);
            $message = $result['success'] ? 'NAS device deleted' : 'Error: ' . ($result['error'] ?? 'Unknown error');
            $messageType = $result['success'] ? 'success' : 'danger';
            break;
        
        case 'create_location':
            $result = $radiusBilling->createLocation($_POST);
            $message = $result['success'] ? 'Zone created successfully' : 'Error: ' . ($result['error'] ?? 'Unknown error');
            $messageType = $result['success'] ? 'success' : 'danger';
            break;
            
        case 'update_location':
            $result = $radiusBilling->updateLocation((int)$_POST['id'], $_POST);
            $message = $result['success'] ? 'Zone updated successfully' : 'Error: ' . ($result['error'] ?? 'Unknown error');
            $messageType = $result['success'] ? 'success' : 'danger';
            break;
            
        case 'delete_location':
            $result = $radiusBilling->deleteLocation((int)$_POST['id']);
            $message = $result['success'] ? 'Zone deleted' : 'Error: ' . ($result['error'] ?? 'Unknown error');
            $messageType = $result['success'] ? 'success' : 'danger';
            break;
            
        case 'create_sub_location':
            $result = $radiusBilling->createSubLocation($_POST);
            $message = $result['success'] ? 'Sub-zone created successfully' : 'Error: ' . ($result['error'] ?? 'Unknown error');
            $messageType = $result['success'] ? 'success' : 'danger';
            break;
            
        case 'update_sub_location':
            $result = $radiusBilling->updateSubLocation((int)$_POST['id'], $_POST);
            $message = $result['success'] ? 'Sub-zone updated successfully' : 'Error: ' . ($result['error'] ?? 'Unknown error');
            $messageType = $result['success'] ? 'success' : 'danger';
            break;
            
        case 'delete_sub_location':
            $result = $radiusBilling->deleteSubLocation((int)$_POST['id']);
            $message = $result['success'] ? 'Sub-zone deleted' : 'Error: ' . ($result['error'] ?? 'Unknown error');
            $messageType = $result['success'] ? 'success' : 'danger';
            break;
            
        case 'send_bulk_sms':
            $filters = [
                'status' => $_POST['filter_status'] ?? '',
                'location_id' => $_POST['filter_location'] ?? '',
                'package_id' => $_POST['filter_package'] ?? ''
            ];
            $subscribers = $radiusBilling->getSubscribersByFilter($filters);
            $sms = new \App\SMS();
            $sendVia = $_POST['send_via'] ?? 'sms';
            $messageTemplate = $_POST['message'] ?? '';
            
            $sentCount = 0;
            $failCount = 0;
            
            foreach ($subscribers as $sub) {
                $phone = $sub['customer_phone'] ?? '';
                if (empty($phone)) continue;
                
                $msg = str_replace(
                    ['{customer_name}', '{username}', '{package_name}', '{expiry_date}', '{balance}'],
                    [$sub['customer_name'] ?? '', $sub['username'] ?? '', $sub['package_name'] ?? '', $sub['expiry_date'] ?? '', '0'],
                    $messageTemplate
                );
                
                try {
                    if ($sendVia === 'sms' || $sendVia === 'both') {
                        $sms->send($phone, $msg);
                    }
                    if ($sendVia === 'whatsapp' || $sendVia === 'both') {
                        $wa = new \App\WhatsApp();
                        $wa->sendMessage($phone, $msg);
                    }
                    $sentCount++;
                } catch (\Exception $e) {
                    $failCount++;
                }
            }
            
            $message = "Bulk message sent to $sentCount subscribers" . ($failCount > 0 ? " ($failCount failed)" : '');
            $messageType = $failCount > 0 ? 'warning' : 'success';
            break;
            
        case 'create_package':
            $result = $radiusBilling->createPackage($_POST);
            $message = $result['success'] ? 'Package created successfully' : 'Error: ' . ($result['error'] ?? 'Unknown error');
            $messageType = $result['success'] ? 'success' : 'danger';
            break;
            
        case 'update_package':
            $result = $radiusBilling->updatePackage((int)$_POST['id'], $_POST);
            if ($result['success']) {
                $coaMsg = ($result['coa_updated'] ?? 0) > 0 ? " Speed updated for {$result['coa_updated']} active subscriber(s)." : '';
                $message = 'Package updated.' . $coaMsg;
            } else {
                $message = 'Error: ' . ($result['error'] ?? 'Unknown error');
            }
            $messageType = $result['success'] ? 'success' : 'danger';
            break;
            
        case 'create_package_schedule':
            $packageId = (int)$_POST['package_id'];
            $downloadSpeed = (int)$_POST['download_speed'] . ($_POST['download_unit'] === 'k' ? 'k' : 'M');
            $uploadSpeed = (int)$_POST['upload_speed'] . ($_POST['upload_unit'] === 'k' ? 'k' : 'M');
            $daysOfWeek = implode('', $_POST['days'] ?? []);
            
            try {
                $radiusBilling->createPackageSchedule([
                    'package_id' => $packageId,
                    'name' => $_POST['name'],
                    'start_time' => $_POST['start_time'],
                    'end_time' => $_POST['end_time'],
                    'days_of_week' => $daysOfWeek,
                    'download_speed' => $downloadSpeed,
                    'upload_speed' => $uploadSpeed,
                    'priority' => (int)($_POST['priority'] ?? 0),
                    'is_active' => (bool)($_POST['is_active'] ?? true)
                ]);
                $message = 'Speed schedule created successfully';
                $messageType = 'success';
            } catch (\Exception $e) {
                $message = 'Error creating schedule: ' . $e->getMessage();
                $messageType = 'danger';
            }
            header("Location: ?page=isp&view=package_schedules&package_id={$packageId}&msg=" . urlencode($message) . "&type={$messageType}");
            exit;
            
        case 'delete_package_schedule':
            $scheduleId = (int)$_POST['schedule_id'];
            $packageId = (int)$_POST['package_id'];
            try {
                $radiusBilling->deletePackageSchedule($scheduleId);
                $message = 'Speed schedule deleted';
                $messageType = 'success';
            } catch (\Exception $e) {
                $message = 'Error deleting schedule: ' . $e->getMessage();
                $messageType = 'danger';
            }
            header("Location: ?page=isp&view=package_schedules&package_id={$packageId}&msg=" . urlencode($message) . "&type={$messageType}");
            exit;
            
        case 'add_addon':
            try {
                $category = $_POST['category'] ?? 'Other';
                $isInternet = str_starts_with($category, 'Internet');
                
                $stmt = $db->prepare("INSERT INTO radius_addon_services (name, description, category, billing_type, price, setup_fee, is_active, download_speed, upload_speed, speed_unit) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $_POST['name'],
                    $_POST['description'] ?? '',
                    $category,
                    $_POST['billing_type'] ?? 'monthly',
                    (float)$_POST['price'],
                    (float)($_POST['setup_fee'] ?? 0),
                    isset($_POST['is_active']) ? 1 : 0,
                    $isInternet && !empty($_POST['download_speed']) ? (int)$_POST['download_speed'] : null,
                    $isInternet && !empty($_POST['upload_speed']) ? (int)$_POST['upload_speed'] : null,
                    $isInternet ? ($_POST['speed_unit'] ?? 'Mbps') : null
                ]);
                $message = 'Addon service created successfully';
                $messageType = 'success';
            } catch (\Exception $e) {
                $message = 'Error creating addon: ' . $e->getMessage();
                $messageType = 'danger';
            }
            break;
            
        case 'edit_addon':
            try {
                $category = $_POST['category'] ?? 'Other';
                $isInternet = str_starts_with($category, 'Internet');
                
                $stmt = $db->prepare("UPDATE radius_addon_services SET name = ?, description = ?, category = ?, billing_type = ?, price = ?, setup_fee = ?, is_active = ?, download_speed = ?, upload_speed = ?, speed_unit = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([
                    $_POST['name'],
                    $_POST['description'] ?? '',
                    $category,
                    $_POST['billing_type'] ?? 'monthly',
                    (float)$_POST['price'],
                    (float)($_POST['setup_fee'] ?? 0),
                    isset($_POST['is_active']) ? 1 : 0,
                    $isInternet && !empty($_POST['download_speed']) ? (int)$_POST['download_speed'] : null,
                    $isInternet && !empty($_POST['upload_speed']) ? (int)$_POST['upload_speed'] : null,
                    $isInternet ? ($_POST['speed_unit'] ?? 'Mbps') : null,
                    (int)$_POST['addon_id']
                ]);
                $message = 'Addon service updated successfully';
                $messageType = 'success';
            } catch (\Exception $e) {
                $message = 'Error updating addon: ' . $e->getMessage();
                $messageType = 'danger';
            }
            break;
            
        case 'delete_addon':
            try {
                $addonId = (int)$_POST['addon_id'];
                $activeCheck = $db->prepare("SELECT COUNT(*) FROM radius_subscription_addons WHERE addon_id = ? AND status = 'active'");
                $activeCheck->execute([$addonId]);
                if ($activeCheck->fetchColumn() > 0) {
                    throw new \Exception('Cannot delete addon with active subscribers. Deactivate it instead.');
                }
                $stmt = $db->prepare("DELETE FROM radius_addon_services WHERE id = ?");
                $stmt->execute([$addonId]);
                $message = 'Addon service deleted';
                $messageType = 'success';
            } catch (\Exception $e) {
                $message = 'Error: ' . $e->getMessage();
                $messageType = 'danger';
            }
            break;
            
        case 'assign_addon':
            try {
                $subId = (int)$_POST['subscription_id'];
                $addonId = (int)$_POST['addon_id'];
                $quantity = max(1, (int)($_POST['quantity'] ?? 1));
                
                // Build config data based on addon category
                $configData = [];
                if (!empty($_POST['pppoe_username'])) {
                    $configData['type'] = 'pppoe';
                    $configData['username'] = trim($_POST['pppoe_username']);
                    $configData['password'] = $_POST['pppoe_password'];
                } elseif (!empty($_POST['static_ip'])) {
                    $configData['type'] = 'static';
                    $configData['ip'] = trim($_POST['static_ip']);
                    $configData['netmask'] = trim($_POST['static_netmask'] ?? '255.255.255.0');
                    $configData['gateway'] = trim($_POST['static_gateway'] ?? '');
                } elseif (!empty($_POST['dhcp_mac'])) {
                    $configData['type'] = 'dhcp';
                    $configData['mac'] = strtoupper(trim($_POST['dhcp_mac']));
                    $configData['reserved_ip'] = trim($_POST['dhcp_reserved_ip'] ?? '');
                    $configData['description'] = trim($_POST['dhcp_description'] ?? '');
                }
                
                $stmt = $db->prepare("INSERT INTO radius_subscription_addons (subscription_id, addon_id, quantity, config_data, status, activated_at) VALUES (?, ?, ?, ?, 'active', NOW()) ON CONFLICT ON CONSTRAINT radius_subscription_addons_subscription_id_addon_id_key DO UPDATE SET quantity = EXCLUDED.quantity, config_data = EXCLUDED.config_data, status = 'active', updated_at = NOW()");
                $stmt->execute([$subId, $addonId, $quantity, json_encode($configData)]);
                $message = 'Addon assigned successfully';
                $messageType = 'success';
            } catch (\Exception $e) {
                $message = 'Error assigning addon: ' . $e->getMessage();
                $messageType = 'danger';
            }
            break;
            
        case 'remove_addon':
            try {
                $subId = (int)$_POST['subscription_id'];
                $addonId = (int)$_POST['addon_id'];
                $stmt = $db->prepare("UPDATE radius_subscription_addons SET status = 'cancelled', cancelled_at = NOW(), updated_at = NOW() WHERE subscription_id = ? AND addon_id = ?");
                $stmt->execute([$subId, $addonId]);
                $message = 'Addon removed from subscription';
                $messageType = 'success';
            } catch (\Exception $e) {
                $message = 'Error: ' . $e->getMessage();
                $messageType = 'danger';
            }
            break;
            
        case 'create_subscription':
            $postData = $_POST;
            $customerId = null;
            
            if (($_POST['customer_mode'] ?? 'existing') === 'new') {
                $newName = trim($_POST['new_customer_name'] ?? '');
                $newPhone = trim($_POST['new_customer_phone'] ?? '');
                
                if (empty($newName) || empty($newPhone)) {
                    $message = 'Error: Customer name and phone are required';
                    $messageType = 'danger';
                    break;
                }
                
                $phone = preg_replace('/[^0-9]/', '', $newPhone);
                if (substr($phone, 0, 1) === '0') {
                    $phone = '254' . substr($phone, 1);
                } elseif (substr($phone, 0, 3) !== '254') {
                    $phone = '254' . $phone;
                }
                
                // Check if phone already has a subscriber
                $existingCheck = $db->prepare("
                    SELECT rs.id, c.name FROM radius_subscriptions rs 
                    JOIN customers c ON c.id = rs.customer_id 
                    WHERE REPLACE(REPLACE(c.phone, '+', ''), ' ', '') = ? 
                       OR REPLACE(REPLACE(c.phone, '+', ''), ' ', '') LIKE ?
                ");
                $existingCheck->execute([$phone, '%' . substr($phone, -9)]);
                $existing = $existingCheck->fetch(PDO::FETCH_ASSOC);
                
                if ($existing) {
                    $message = "Error: Phone number already has a subscriber ({$existing['name']}). One subscriber per phone allowed.";
                    $messageType = 'danger';
                    break;
                }
                
                try {
                    $stmt = $db->prepare("INSERT INTO customers (name, phone, email, address) VALUES (?, ?, ?, ?) RETURNING id");
                    $stmt->execute([
                        $newName,
                        $phone,
                        trim($_POST['new_customer_email'] ?? '') ?: null,
                        trim($_POST['new_customer_address'] ?? '') ?: null
                    ]);
                    $newCustomerId = $stmt->fetchColumn();
                    $postData['customer_id'] = $newCustomerId;
                    $postData['package_id'] = $_POST['package_id_new'] ?? $_POST['package_id'];
                } catch (Exception $e) {
                    $message = 'Error creating customer: ' . $e->getMessage();
                    $messageType = 'danger';
                    break;
                }
            } else {
                // Check if existing customer already has a subscriber
                $customerId = (int)($_POST['customer_id'] ?? 0);
                if ($customerId) {
                    $existingCheck = $db->prepare("SELECT id FROM radius_subscriptions WHERE customer_id = ?");
                    $existingCheck->execute([$customerId]);
                    if ($existingCheck->fetch()) {
                        $message = "Error: This customer already has a subscriber. One subscriber per phone allowed.";
                        $messageType = 'danger';
                        break;
                    }
                }
            }
            
            $result = $radiusBilling->createSubscription($postData);
            $message = $result['success'] ? 'Subscriber created successfully' : 'Error: ' . ($result['error'] ?? 'Unknown error');
            $messageType = $result['success'] ? 'success' : 'danger';
            break;
            
        case 'update_subscription':
            $id = (int)$_POST['id'];
            try {
                // Get current subscription to detect changes
                $currentSub = $radiusBilling->getSubscription($id);
                $oldPackageId = $currentSub['package_id'] ?? null;
                $oldPassword = $currentSub['password'] ?? '';
                $oldExpiryRaw = $currentSub['expiry_date'] ?? null;
                $newPackageId = (int)$_POST['package_id'];
                $newPassword = $_POST['password'];
                $newExpiry = $_POST['expiry_date'] ?: null;
                
                // Normalize expiry dates to Y-m-d for comparison (DB may return datetime)
                $oldExpiry = $oldExpiryRaw ? date('Y-m-d', strtotime($oldExpiryRaw)) : null;
                $normalizedNewExpiry = $newExpiry ? date('Y-m-d', strtotime($newExpiry)) : null;
                
                // Check if old package and new package have different address pools
                $oldPackage = $oldPackageId ? $radiusBilling->getPackage($oldPackageId) : null;
                $newPackage = $radiusBilling->getPackage($newPackageId);
                $poolChanged = ($oldPackage['address_pool'] ?? null) !== ($newPackage['address_pool'] ?? null);
                
                // Detect if expiry date changed at all (past or future)
                $expiryChanged = $oldExpiry !== $normalizedNewExpiry;
                
                $stmt = $db->prepare("
                    UPDATE radius_subscriptions SET 
                        package_id = ?,
                        password = ?,
                        password_encrypted = ?,
                        expiry_date = ?,
                        static_ip = ?,
                        mac_address = ?,
                        auto_renew = ?::boolean,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $stmt->execute([
                    $newPackageId,
                    $newPassword,
                    $radiusBilling->encryptPassword($newPassword),
                    $newExpiry,
                    !empty($_POST['static_ip']) ? $_POST['static_ip'] : null,
                    !empty($_POST['mac_address']) ? $_POST['mac_address'] : null,
                    isset($_POST['auto_renew']) ? 'true' : 'false',
                    $id
                ]);
                
                $coaMessage = '';
                $needsDisconnect = false;
                $disconnectReason = '';
                
                // Determine if disconnect is needed
                if ($oldPassword !== $newPassword) {
                    $needsDisconnect = true;
                    $disconnectReason = 'password changed';
                } elseif ($expiryChanged) {
                    $needsDisconnect = true;
                    $disconnectReason = 'expiry date changed';
                } elseif ($poolChanged) {
                    $needsDisconnect = true;
                    $disconnectReason = 'IP pool changed';
                }
                
                if ($needsDisconnect) {
                    // Force full disconnect async so router reconnects with new session
                    $disconnectResult = $radiusBilling->disconnectUserAsync($id);
                    if (!empty($disconnectResult['sent']) && $disconnectResult['sent'] > 0) {
                        $coaMessage = " Disconnect sent ({$disconnectReason}).";
                    } else {
                        $coaMessage = " (no active session - {$disconnectReason})";
                    }
                } elseif ($oldPackageId != $newPackageId) {
                    // Package changed but same pool - just update speed
                    $speedResult = $radiusBilling->sendSpeedUpdateCoA($id);
                    if (!empty($speedResult['success'])) {
                        $coaMessage = ' Speed updated via CoA.';
                    }
                }
                
                $message = 'Subscription updated successfully.' . $coaMessage;
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error updating subscription: ' . $e->getMessage();
                $messageType = 'danger';
            }
            break;
            
        case 'update_subscriber_customer':
            $customerId = (int)$_POST['customer_id'];
            try {
                $stmt = $db->prepare("UPDATE customers SET name = ?, phone = ?, email = ?, address = ? WHERE id = ?");
                $stmt->execute([
                    $_POST['name'],
                    $_POST['phone'],
                    !empty($_POST['email']) ? $_POST['email'] : null,
                    !empty($_POST['address']) ? $_POST['address'] : null,
                    $customerId
                ]);
                $message = 'Customer information updated';
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error updating customer: ' . $e->getMessage();
                $messageType = 'danger';
            }
            break;
            
        case 'update_subscription_notes':
            $id = (int)$_POST['id'];
            try {
                $stmt = $db->prepare("UPDATE radius_subscriptions SET notes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$_POST['notes'] ?? '', $id]);
                $message = 'Notes saved';
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error saving notes: ' . $e->getMessage();
                $messageType = 'danger';
            }
            break;
            
        case 'add_speed_override':
            try {
                $stmt = $db->prepare("
                    INSERT INTO radius_speed_overrides 
                    (package_id, name, download_speed, upload_speed, start_time, end_time, days_of_week, priority, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, TRUE)
                ");
                $stmt->execute([
                    (int)$_POST['package_id'],
                    $_POST['name'],
                    $_POST['download_speed'],
                    $_POST['upload_speed'],
                    $_POST['start_time'],
                    $_POST['end_time'],
                    $_POST['days_of_week'] ?? null,
                    (int)($_POST['priority'] ?? 0)
                ]);
                $message = 'Speed schedule added';
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error adding speed schedule: ' . $e->getMessage();
                $messageType = 'danger';
            }
            break;
            
        case 'delete_speed_override':
            try {
                $stmt = $db->prepare("DELETE FROM radius_speed_overrides WHERE id = ?");
                $stmt->execute([(int)$_POST['override_id']]);
                $message = 'Speed schedule deleted';
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error deleting speed schedule: ' . $e->getMessage();
                $messageType = 'danger';
            }
            break;
            
        case 'send_speed_coa':
            $subId = (int)$_POST['subscription_id'];
            $result = $radiusBilling->sendSpeedUpdateCoA($subId);
            if ($result['success']) {
                $message = 'CoA sent successfully. New speed: ' . ($result['rate_limit'] ?? 'applied');
                $messageType = 'success';
            } else {
                $errorMsg = $result['error'] ?? 'Unknown error';
                if (!empty($result['diagnostic'])) {
                    $diag = $result['diagnostic'];
                    if ($diag['ping_reachable']) {
                        $errorMsg .= ' | NAS is reachable but CoA port not responding. Run on MikroTik: /radius incoming set accept=yes port=3799';
                    } else {
                        $errorMsg .= ' | NAS (' . $diag['nas_ip'] . ') is not reachable. Check VPN tunnel or firewall.';
                    }
                }
                $message = 'CoA failed: ' . $errorMsg;
                $messageType = 'danger';
            }
            break;
            
        case 'override_speed_coa':
            $subId = (int)$_POST['subscription_id'];
            $downloadSpeed = (int)$_POST['download_speed'];
            $downloadUnit = $_POST['download_unit'] === 'k' ? 'k' : 'M';
            $uploadSpeed = (int)$_POST['upload_speed'];
            $uploadUnit = $_POST['upload_unit'] === 'k' ? 'k' : 'M';
            $durationHours = !empty($_POST['duration_hours']) ? (int)$_POST['duration_hours'] : null;
            
            $rateLimit = "{$downloadSpeed}{$downloadUnit}/{$uploadSpeed}{$uploadUnit}";
            
            // Save override to database
            $radiusBilling->setSpeedOverride($subId, $rateLimit, $durationHours);
            
            // Send CoA to apply immediately
            $result = $radiusBilling->sendSpeedUpdateCoA($subId, $rateLimit);
            if ($result['success']) {
                $expiryText = $durationHours ? "for {$durationHours} hours" : "(permanent)";
                $message = "Speed override applied: {$rateLimit} {$expiryText}";
                $messageType = 'success';
            } else {
                $errorMsg = $result['error'] ?? 'Unknown error';
                if (!empty($result['diagnostic'])) {
                    $diag = $result['diagnostic'];
                    if ($diag['ping_reachable']) {
                        $errorMsg .= ' | NAS is reachable but CoA port not responding. Run on MikroTik: /radius incoming set accept=yes port=3799';
                    } else {
                        $errorMsg .= ' | NAS (' . $diag['nas_ip'] . ') is not reachable. Check VPN tunnel or firewall.';
                    }
                }
                $message = "Override saved but CoA failed: {$errorMsg} - Override will apply on next reconnect.";
                $messageType = 'warning';
            }
            break;
            
        case 'clear_speed_override':
            $subId = (int)$_POST['subscription_id'];
            $radiusBilling->clearSpeedOverride($subId);
            
            // Send CoA to reset to package speed
            $result = $radiusBilling->sendSpeedUpdateCoA($subId);
            if ($result['success']) {
                $message = 'Speed override cleared, package speed restored';
                $messageType = 'success';
            } else {
                $message = 'Override cleared. Speed will reset on next reconnect.';
                $messageType = 'warning';
            }
            break;
            
        case 'reset_data_usage':
            $id = (int)$_POST['id'];
            try {
                $stmt = $db->prepare("UPDATE radius_subscriptions SET data_used_mb = 0, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$id]);
                $message = 'Data usage reset to 0';
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error resetting data: ' . $e->getMessage();
                $messageType = 'danger';
            }
            break;
            
        case 'capture_mac':
            $id = (int)$_POST['id'];
            $mac = trim($_POST['mac'] ?? '');
            try {
                if (empty($mac)) {
                    throw new Exception('No MAC address provided');
                }
                $stmt = $db->prepare("UPDATE radius_subscriptions SET mac_address = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$mac, $id]);
                $message = 'MAC address captured: ' . $mac;
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error capturing MAC: ' . $e->getMessage();
                $messageType = 'danger';
            }
            break;
            
        case 'clear_mac':
            $id = (int)$_POST['id'];
            try {
                $stmt = $db->prepare("UPDATE radius_subscriptions SET mac_address = NULL, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$id]);
                $message = 'MAC binding cleared. MAC will be auto-captured on next connection.';
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error clearing MAC: ' . $e->getMessage();
                $messageType = 'danger';
            }
            break;
            
        case 'add_credit':
            $subId = (int)$_POST['subscription_id'];
            $amount = (float)$_POST['amount'];
            try {
                $stmt = $db->prepare("UPDATE radius_subscriptions SET credit_balance = COALESCE(credit_balance, 0) + ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$amount, $subId]);
                
                // Disconnect user after manual topup so they can reconnect with updated balance
                $disconnectResult = $radiusBilling->disconnectSubscription($subId);
                $disconnectMsg = '';
                if (!empty($disconnectResult['success']) && $disconnectResult['disconnected'] > 0) {
                    $disconnectMsg = ' User disconnected to apply changes.';
                }
                
                $message = 'Credit of KES ' . number_format($amount) . ' added successfully.' . $disconnectMsg;
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error adding credit: ' . $e->getMessage();
                $messageType = 'danger';
            }
            break;
            
        case 'disconnect_session':
            $sessionId = (int)$_POST['session_id'];
            try {
                // Get session details for CoA
                $stmt = $db->prepare("
                    SELECT rs.acct_session_id, rs.framed_ip_address, rs.mac_address,
                           sub.username, rn.ip_address as nas_ip, rn.secret as nas_secret
                    FROM radius_sessions rs
                    LEFT JOIN radius_nas rn ON rs.nas_id = rn.id
                    LEFT JOIN radius_subscriptions sub ON rs.subscription_id = sub.id
                    WHERE rs.id = ?
                ");
                $stmt->execute([$sessionId]);
                $session = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($session && !empty($session['nas_ip'])) {
                    // Send CoA Disconnect to NAS
                    $coaResult = $radiusBilling->sendCoADisconnect($session);
                    if ($coaResult['success']) {
                        $message = 'Session disconnected via CoA';
                    } else {
                        $message = 'CoA sent (NAS response: ' . ($coaResult['error'] ?? 'unknown') . ')';
                    }
                } else {
                    $message = 'Session marked as disconnected (no NAS available for CoA)';
                }
                
                // Update database regardless
                $stmt = $db->prepare("UPDATE radius_sessions SET session_end = CURRENT_TIMESTAMP, terminate_cause = 'Admin-Reset' WHERE id = ?");
                $stmt->execute([$sessionId]);
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error disconnecting session: ' . $e->getMessage();
                $messageType = 'danger';
            }
            break;
            
        case 'generate_invoice':
            $subId = (int)$_POST['subscription_id'];
            try {
                $sub = $radiusBilling->getSubscription($subId);
                $package = $radiusBilling->getPackage($sub['package_id']);
                $invoiceNumber = 'INV-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                
                $stmt = $db->prepare("
                    INSERT INTO radius_invoices (subscription_id, invoice_number, total_amount, status, created_at)
                    VALUES (?, ?, ?, 'pending', CURRENT_TIMESTAMP)
                ");
                $stmt->execute([$subId, $invoiceNumber, $package['price'] ?? 0]);
                $message = "Invoice {$invoiceNumber} generated";
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error generating invoice: ' . $e->getMessage();
                $messageType = 'danger';
            }
            break;
            
        case 'renew_subscription':
            $result = $radiusBilling->renewSubscription((int)$_POST['id'], (int)$_POST['package_id'] ?: null);
            if ($result['success']) {
                $msg = 'Subscription renewed until ' . $result['expiry_date'];
                if (!empty($result['coa_sent'])) {
                    $msg .= ' (speed updated via CoA)';
                }
                $message = $msg;
                $messageType = 'success';
            } else {
                $message = 'Error: ' . ($result['error'] ?? 'Unknown error');
                $messageType = 'danger';
            }
            break;
            
        case 'suspend_subscription':
            $result = $radiusBilling->suspendSubscription((int)$_POST['id'], $_POST['reason'] ?? '');
            if ($result['success']) {
                $msg = 'Subscription suspended';
                if (!empty($result['days_remaining'])) {
                    $msg .= ' (' . $result['days_remaining'] . ' days saved for restoration)';
                }
                if (!empty($result['coa_errors'])) {
                    $msg .= ' - CoA warnings: ' . implode('; ', array_slice($result['coa_errors'], 0, 2));
                }
                $message = $msg;
                $messageType = 'success';
            } else {
                $message = 'Error: ' . ($result['error'] ?? 'Unknown error');
                $messageType = 'danger';
            }
            break;
            
        case 'unsuspend_subscription':
            $result = $radiusBilling->unsuspendSubscription((int)$_POST['id']);
            if ($result['success']) {
                $msg = 'Subscription reactivated';
                if (!empty($result['days_restored'])) {
                    $msg .= ' (' . $result['days_restored'] . ' days restored, new expiry: ' . date('M j, Y', strtotime($result['new_expiry'])) . ')';
                }
                $message = $msg;
                $messageType = 'success';
            } else {
                $message = 'Error: ' . ($result['error'] ?? 'Unknown error');
                $messageType = 'danger';
            }
            break;
            
        case 'activate_subscription':
            $result = $radiusBilling->activateSubscription((int)$_POST['id']);
            if ($result['success']) {
                $msg = 'Subscription activated';
                if (!empty($result['coa_sent'])) {
                    $msg .= ' (speed updated: ' . ($result['new_speed'] ?? 'applied') . ')';
                }
                $message = $msg;
                $messageType = 'success';
            } else {
                $message = 'Error: ' . ($result['error'] ?? 'Unknown error');
                $messageType = 'danger';
            }
            break;
            
        case 'change_expiry':
            $subId = (int)$_POST['id'];
            $newExpiry = $_POST['new_expiry_date'] ?? '';
            $reason = $_POST['expiry_change_reason'] ?? '';
            
            if ($newExpiry) {
                try {
                    // Get current subscription to check if extending from expired
                    $stmt = $db->prepare("SELECT status, expiry_date FROM radius_subscriptions WHERE id = ?");
                    $stmt->execute([$subId]);
                    $oldSub = $stmt->fetch(\PDO::FETCH_ASSOC);
                    $wasExpired = ($oldSub && ($oldSub['status'] === 'expired' || strtotime($oldSub['expiry_date']) < time()));
                    $isExtending = strtotime($newExpiry) > time();
                    $isExpiring = strtotime($newExpiry) < time(); // New expiry is in the past
                    
                    // Update expiry and status based on new date
                    if ($wasExpired && $isExtending) {
                        // Extending from expired - reactivate
                        $stmt = $db->prepare("UPDATE radius_subscriptions SET expiry_date = ?, status = 'active', updated_at = NOW() WHERE id = ?");
                    } elseif ($isExpiring) {
                        // Setting to past date - expire the subscription
                        $stmt = $db->prepare("UPDATE radius_subscriptions SET expiry_date = ?, status = 'expired', updated_at = NOW() WHERE id = ?");
                    } else {
                        $stmt = $db->prepare("UPDATE radius_subscriptions SET expiry_date = ?, updated_at = NOW() WHERE id = ?");
                    }
                    $stmt->execute([$newExpiry, $subId]);
                    
                    if ($reason) {
                        $stmt = $db->prepare("INSERT INTO radius_subscription_notes (subscription_id, note, created_by, created_at) VALUES (?, ?, ?, NOW())");
                        $stmt->execute([$subId, "Expiry changed to " . date('M j, Y', strtotime($newExpiry)) . ". Reason: " . $reason, $_SESSION['user_id']]);
                    }
                    
                    $msg = 'Expiry date updated to ' . date('M j, Y', strtotime($newExpiry));
                    
                    // Send disconnect request async (fire and forget) so it doesn't block
                    $disconnectResult = $radiusBilling->disconnectUserAsync($subId);
                    if (!empty($disconnectResult['sent']) && $disconnectResult['sent'] > 0) {
                        $msg .= ' (disconnect sent - will reconnect with new expiry)';
                    } else {
                        $msg .= ' (no active session)';
                    }
                    
                    $message = $msg;
                    $messageType = 'success';
                } catch (Exception $e) {
                    $message = 'Error updating expiry: ' . $e->getMessage();
                    $messageType = 'danger';
                }
            } else {
                $message = 'Please select a valid expiry date';
                $messageType = 'warning';
            }
            break;
            
        case 'change_package':
            $subId = (int)$_POST['id'];
            $newPackageId = (int)$_POST['new_package_id'];
            $prorateAmount = (float)($_POST['prorate_amount'] ?? 0);
            $applyProrate = isset($_POST['apply_prorate']);
            
            if (!$newPackageId) {
                $message = 'Please select a new package';
                $messageType = 'warning';
                break;
            }
            
            try {
                $stmt = $db->prepare("SELECT * FROM radius_subscriptions WHERE id = ?");
                $stmt->execute([$subId]);
                $oldSub = $stmt->fetch(\PDO::FETCH_ASSOC);
                
                $stmt = $db->prepare("SELECT * FROM radius_packages WHERE id = ?");
                $stmt->execute([$newPackageId]);
                $newPkg = $stmt->fetch(\PDO::FETCH_ASSOC);
                
                if (!$oldSub || !$newPkg) {
                    throw new Exception('Subscription or package not found');
                }
                
                $db->beginTransaction();
                
                $newExpiry = date('Y-m-d', strtotime('+' . $newPkg['validity_days'] . ' days'));
                $stmt = $db->prepare("UPDATE radius_subscriptions SET package_id = ?, expiry_date = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$newPackageId, $newExpiry, $subId]);
                
                if ($applyProrate && $prorateAmount != 0) {
                    if ($prorateAmount < 0) {
                        $creditAmount = abs($prorateAmount);
                        $stmt = $db->prepare("UPDATE radius_subscriptions SET credit_balance = credit_balance + ? WHERE id = ?");
                        $stmt->execute([$creditAmount, $subId]);
                        
                        $stmt = $db->prepare("INSERT INTO radius_billing_history (subscription_id, transaction_type, amount, description, created_at) VALUES (?, 'credit', ?, ?, NOW())");
                        $stmt->execute([$subId, $creditAmount, 'Package change credit (prorated)']);
                    } else {
                        $stmt = $db->prepare("INSERT INTO radius_billing_history (subscription_id, transaction_type, amount, description, created_at) VALUES (?, 'invoice', ?, ?, NOW())");
                        $stmt->execute([$subId, $prorateAmount, 'Package upgrade balance due']);
                    }
                }
                
                $db->commit();
                
                $msg = 'Package changed to ' . $newPkg['name'];
                
                // Send disconnect async so user reconnects with new package settings
                $disconnectResult = $radiusBilling->disconnectUserAsync($subId);
                if (!empty($disconnectResult['sent']) && $disconnectResult['sent'] > 0) {
                    $msg .= ' (disconnect sent - will reconnect with new speeds)';
                } else {
                    $msg .= ' (no active session)';
                }
                
                if ($applyProrate && $prorateAmount != 0) {
                    if ($prorateAmount < 0) {
                        $msg .= '. KES ' . number_format(abs($prorateAmount)) . ' credited to wallet.';
                    } else {
                        $msg .= '. KES ' . number_format($prorateAmount) . ' due from customer.';
                    }
                }
                
                $message = $msg;
                $messageType = 'success';
            } catch (Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                $message = 'Error changing package: ' . $e->getMessage();
                $messageType = 'danger';
            }
            break;
            
        case 'generate_vouchers':
            $result = $radiusBilling->generateVouchers((int)$_POST['package_id'], (int)$_POST['count'], $_SESSION['user_id']);
            $message = $result['success'] ? "Generated {$result['count']} vouchers (Batch: {$result['batch_id']})" : 'Error: ' . ($result['error'] ?? 'Unknown error');
            $messageType = $result['success'] ? 'success' : 'danger';
            break;
            
        case 'bulk_activate':
            $ids = array_filter(array_map('intval', explode(',', $_POST['bulk_ids'] ?? '')));
            $success = 0;
            foreach ($ids as $id) {
                $result = $radiusBilling->activateSubscription($id);
                if ($result['success']) $success++;
            }
            $message = "Activated {$success} of " . count($ids) . " subscriber(s)";
            $messageType = $success > 0 ? 'success' : 'warning';
            break;
            
        case 'bulk_suspend':
            $ids = array_filter(array_map('intval', explode(',', $_POST['bulk_ids'] ?? '')));
            $success = 0;
            foreach ($ids as $id) {
                $result = $radiusBilling->suspendSubscription($id, 'Bulk suspension');
                if ($result['success']) $success++;
            }
            $message = "Suspended {$success} of " . count($ids) . " subscriber(s)";
            $messageType = $success > 0 ? 'success' : 'warning';
            break;
            
        case 'bulk_renew':
            $ids = array_filter(array_map('intval', explode(',', $_POST['bulk_ids'] ?? '')));
            $success = 0;
            foreach ($ids as $id) {
                $result = $radiusBilling->renewSubscription($id);
                if ($result['success']) $success++;
            }
            $message = "Renewed {$success} of " . count($ids) . " subscriber(s)";
            $messageType = $success > 0 ? 'success' : 'warning';
            break;
            
        case 'bulk_send_sms':
            $ids = array_filter(array_map('intval', explode(',', $_POST['bulk_ids'] ?? '')));
            $message = "SMS feature: Selected " . count($ids) . " subscriber(s). Please use the SMS module for bulk messaging.";
            $messageType = 'info';
            break;
            
        case 'save_isp_settings':
            $settingsToSave = [];
            $category = $_POST['category'] ?? 'general';
            
            // Collect settings based on category
            foreach ($_POST as $key => $value) {
                if (in_array($key, ['action', 'category'])) continue;
                
                // Handle checkboxes (unchecked checkboxes don't appear in POST)
                if (strpos($key, 'enabled') !== false || $key === 'auto_suspend_expired' || $key === 'postpaid_enabled' || $key === 'use_expired_pool' || $key === 'allow_unknown_expired_pool') {
                    $settingsToSave[$key] = $value === 'true' ? 'true' : 'false';
                } else {
                    $settingsToSave[$key] = $value;
                }
            }
            
            // Handle unchecked checkboxes explicitly
            $checkboxFields = ['expiry_reminder_enabled', 'payment_confirmation_enabled', 'renewal_confirmation_enabled', 'postpaid_enabled', 'auto_suspend_expired', 'use_expired_pool'];
            foreach ($checkboxFields as $field) {
                if (!isset($_POST[$field]) && $category === 'notifications' && in_array($field, ['expiry_reminder_enabled', 'payment_confirmation_enabled', 'renewal_confirmation_enabled'])) {
                    $settingsToSave[$field] = 'false';
                }
                if (!isset($_POST[$field]) && $category === 'billing' && in_array($field, ['postpaid_enabled', 'auto_suspend_expired'])) {
                    $settingsToSave[$field] = 'false';
                }
                if (!isset($_POST[$field]) && $category === 'radius' && in_array($field, ['use_expired_pool', 'allow_unknown_expired_pool'])) {
                    $settingsToSave[$field] = 'false';
                }
            }
            
            if ($radiusBilling->saveSettings($settingsToSave)) {
                $message = 'Settings saved successfully';
                $messageType = 'success';
            } else {
                $message = 'Error saving settings';
                $messageType = 'danger';
            }
            break;
            
        case 'test_expiry_reminders':
            $result = $radiusBilling->sendExpiryReminders();
            $message = "Expiry reminders: Sent {$result['sent']}, Failed {$result['failed']}";
            $messageType = $result['failed'] > 0 ? 'warning' : 'success';
            break;
    }
    
    // Handle redirect after action
    if (!empty($_POST['return_to']) && $_POST['return_to'] === 'subscriber' && !empty($_POST['id'])) {
        $_SESSION['isp_message'] = $message;
        $_SESSION['isp_message_type'] = $messageType;
        header('Location: ?page=isp&view=subscriber&id=' . (int)$_POST['id']);
        exit;
    } elseif (!empty($_POST['return_to']) && $_POST['return_to'] === 'subscriber' && !empty($_POST['subscription_id'])) {
        $_SESSION['isp_message'] = $message;
        $_SESSION['isp_message_type'] = $messageType;
        header('Location: ?page=isp&view=subscriber&id=' . (int)$_POST['subscription_id']);
        exit;
    }
}

// Retrieve flash message
if (isset($_SESSION['isp_message'])) {
    $message = $_SESSION['isp_message'];
    $messageType = $_SESSION['isp_message_type'] ?? 'info';
    unset($_SESSION['isp_message'], $_SESSION['isp_message_type']);
}

$stats = $radiusBilling->getDashboardStats();

// Get customers for dropdown
$customers = [];
try {
    $customersStmt = $db->query("SELECT id, name, phone FROM customers ORDER BY name LIMIT 500");
    $customers = $customersStmt->fetchAll(\PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ISP - RADIUS Billing System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --isp-primary: #1a1c2c;
            --isp-primary-light: #2d3250;
            --isp-primary-dark: #0f1019;
            --isp-accent: #6366f1;
            --isp-accent-light: #818cf8;
            --isp-accent-dark: #4f46e5;
            --isp-success: #10b981;
            --isp-success-light: #34d399;
            --isp-warning: #f59e0b;
            --isp-warning-light: #fbbf24;
            --isp-danger: #ef4444;
            --isp-danger-light: #f87171;
            --isp-info: #0ea5e9;
            --isp-info-light: #38bdf8;
            --isp-bg: #f8fafc;
            --isp-card-bg: #ffffff;
            --isp-text: #1e293b;
            --isp-text-muted: #64748b;
            --isp-text-light: #94a3b8;
            --isp-border: #e2e8f0;
            --isp-border-light: #f1f5f9;
            --isp-shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --isp-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
            --isp-shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1);
            --isp-shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            --isp-radius: 0.5rem;
            --isp-radius-lg: 0.75rem;
            --isp-radius-xl: 1rem;
        }
        
        body { 
            background-color: var(--isp-bg); 
            color: var(--isp-text);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .sidebar { 
            background: linear-gradient(180deg, var(--isp-primary) 0%, var(--isp-primary-light) 100%); 
            min-height: 100vh;
            box-shadow: 4px 0 10px rgba(0,0,0,0.1);
        }
        .sidebar .nav-link { 
            color: rgba(255,255,255,0.7); 
            padding: 0.875rem 1rem; 
            border-radius: var(--isp-radius); 
            margin: 0.25rem 0.5rem;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
        }
        .sidebar .nav-link:hover { 
            background: rgba(255,255,255,0.1); 
            color: #fff;
            transform: translateX(4px);
        }
        .sidebar .nav-link.active { 
            background: linear-gradient(90deg, var(--isp-accent) 0%, var(--isp-accent-light) 100%);
            color: #fff;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
        }
        .sidebar .nav-link i { width: 24px; font-size: 1.1rem; }
        .brand-title { 
            font-size: 1.5rem; 
            font-weight: 800; 
            color: #fff;
            letter-spacing: -0.5px;
        }
        .brand-subtitle {
            font-size: 0.7rem;
            color: rgba(255,255,255,0.5);
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .card {
            border: 1px solid var(--isp-border);
            border-radius: var(--isp-radius-lg);
            box-shadow: var(--isp-shadow);
            transition: all 0.3s ease;
        }
        .card:hover {
            box-shadow: var(--isp-shadow-lg);
        }
        .card-header {
            background: transparent;
            border-bottom: 1px solid var(--isp-border);
            font-weight: 600;
            padding: 1rem 1.25rem;
        }
        
        .stat-card { 
            border-radius: var(--isp-radius-lg); 
            border: none; 
            transition: all 0.3s ease;
            overflow: hidden;
            position: relative;
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--isp-accent), var(--isp-accent-light));
        }
        .stat-card:hover { 
            transform: translateY(-4px); 
            box-shadow: var(--isp-shadow-lg);
        }
        .stat-card.stat-success::before { background: linear-gradient(90deg, var(--isp-success), var(--isp-success-light)); }
        .stat-card.stat-warning::before { background: linear-gradient(90deg, var(--isp-warning), var(--isp-warning-light)); }
        .stat-card.stat-danger::before { background: linear-gradient(90deg, var(--isp-danger), var(--isp-danger-light)); }
        .stat-card.stat-info::before { background: linear-gradient(90deg, var(--isp-info), var(--isp-info-light)); }
        .stat-card.stat-accent::before { background: linear-gradient(90deg, var(--isp-accent), var(--isp-accent-light)); }
        
        .stat-icon { 
            width: 56px; 
            height: 56px; 
            border-radius: var(--isp-radius); 
            display: flex; 
            align-items: center; 
            justify-content: center;
            font-size: 1.5rem;
        }
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            line-height: 1;
            color: var(--isp-primary);
        }
        .stat-label {
            font-size: 0.85rem;
            color: var(--isp-text-muted);
            font-weight: 500;
        }
        
        .table {
            margin-bottom: 0;
        }
        .table thead th {
            background: var(--isp-bg);
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--isp-text-muted);
            border-bottom: 2px solid var(--isp-border);
            padding: 1rem;
        }
        .table tbody td {
            padding: 1rem;
            vertical-align: middle;
            border-color: var(--isp-border);
        }
        .table-hover tbody tr {
            transition: background-color 0.15s ease;
        }
        .table-hover tbody tr:hover { 
            background-color: rgba(14, 165, 233, 0.04); 
        }
        
        .badge {
            font-weight: 600;
            padding: 0.4em 0.8em;
            border-radius: 0.5rem;
            font-size: 0.75rem;
            letter-spacing: 0.3px;
        }
        
        .btn {
            border-radius: var(--isp-radius);
            font-weight: 500;
            padding: 0.5rem 1rem;
            transition: all 0.2s ease;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--isp-accent), var(--isp-accent-light));
            border: none;
            box-shadow: 0 2px 8px rgba(99, 102, 241, 0.3);
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, var(--isp-accent-dark), var(--isp-accent));
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
        }
        .btn-success {
            background: linear-gradient(135deg, var(--isp-success), var(--isp-success-light));
            border: none;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
        }
        .btn-success:hover {
            background: linear-gradient(135deg, #059669, var(--isp-success));
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
        }
        .btn-warning {
            background: linear-gradient(135deg, var(--isp-warning), var(--isp-warning-light));
            border: none;
            box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
            color: #fff;
        }
        .btn-warning:hover {
            background: linear-gradient(135deg, #d97706, var(--isp-warning));
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4);
            color: #fff;
        }
        .btn-danger {
            background: linear-gradient(135deg, var(--isp-danger), var(--isp-danger-light));
            border: none;
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
        }
        .btn-danger:hover {
            background: linear-gradient(135deg, #dc2626, var(--isp-danger));
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
        }
        .btn-info {
            background: linear-gradient(135deg, var(--isp-info), var(--isp-info-light));
            border: none;
            box-shadow: 0 2px 8px rgba(14, 165, 233, 0.3);
            color: #fff;
        }
        .btn-info:hover {
            background: linear-gradient(135deg, #0284c7, var(--isp-info));
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(14, 165, 233, 0.4);
            color: #fff;
        }
        
        .main-content {
            min-height: 100vh;
            padding-bottom: 2rem;
        }
        
        .page-title {
            font-weight: 700;
            color: var(--isp-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .isp-mobile-header {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 60px;
            background: linear-gradient(180deg, var(--isp-primary) 0%, var(--isp-primary-light) 100%);
            z-index: 1030;
            padding: 0 1rem;
            align-items: center;
            justify-content: space-between;
        }
        .brand-mobile {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .hamburger-btn {
            background: rgba(255,255,255,0.1);
            border: none;
            border-radius: 8px;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
        }
        .isp-offcanvas {
            background: linear-gradient(180deg, var(--isp-primary) 0%, var(--isp-primary-light) 100%);
            width: 280px !important;
        }
        .isp-offcanvas .btn-close {
            filter: invert(1);
        }
        
        @media (max-width: 991.98px) {
            .isp-mobile-header {
                display: flex;
            }
            .main-content {
                padding-top: 70px !important;
                padding-left: 1rem !important;
                padding-right: 1rem !important;
            }
            .sidebar {
                display: none !important;
            }
            .stat-icon {
                width: 36px !important;
                height: 36px !important;
                font-size: 0.9rem !important;
            }
            .stat-value {
                font-size: 1.25rem !important;
            }
            .table th, .table td {
                padding: 0.5rem 0.4rem;
                font-size: 0.85rem;
            }
            .btn-group-sm .btn {
                padding: 0.25rem 0.4rem;
            }
            .modal-dialog {
                margin: 0.5rem;
            }
            .card-body {
                padding: 0.75rem;
            }
        }
        @media (max-width: 575.98px) {
            .main-content {
                padding: 0.5rem !important;
                padding-top: 65px !important;
            }
            .page-title {
                font-size: 1.1rem;
            }
            h4.page-title {
                font-size: 1rem;
            }
            .btn {
                padding: 0.4rem 0.6rem;
                font-size: 0.85rem;
            }
            .form-control, .form-select {
                font-size: 0.9rem;
            }
        }
        
        /* Enhanced ISP Mobile Responsiveness */
        @media (max-width: 991.98px) {
            .isp-sidebar { display: none !important; }
            .isp-main { margin-left: 0 !important; }
            /* Make subscriber table scrollable */
            .card-body { overflow-x: auto; -webkit-overflow-scrolling: touch; }
            /* Subscriber list compact view */
            #subscribersTable td, #subscribersTable th { font-size: 0.8rem; white-space: nowrap; }
            /* Stack subscriber details */
            .subscriber-detail-row { display: block !important; }
            .subscriber-detail-row > div { margin-bottom: 0.5rem; }
            /* Hide less critical columns */
            .hide-tablet { display: none !important; }
        }
        @media (max-width: 575.98px) {
            .isp-main { padding: 0.5rem !important; }
            /* Very compact for small phones */
            table td, table th { font-size: 0.7rem; padding: 0.3rem; }
            .badge { font-size: 0.65rem; }
            /* Stack action buttons */
            .subscriber-actions { flex-direction: column; }
            .subscriber-actions .btn { width: 100%; margin-bottom: 0.25rem; }
            /* Hide extra columns */
            .hide-mobile { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="isp-mobile-header">
        <div class="brand-mobile">
            <div style="width: 36px; height: 36px; background: linear-gradient(135deg, var(--isp-accent), var(--isp-accent-light)); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                <i class="bi bi-broadcast text-white"></i>
            </div>
            <span class="brand-title text-white">ISP</span>
        </div>
        <button class="hamburger-btn" type="button" data-bs-toggle="offcanvas" data-bs-target="#ispMobileSidebar">
            <i class="bi bi-list"></i>
        </button>
    </div>
    
    <div class="offcanvas offcanvas-start isp-offcanvas" tabindex="-1" id="ispMobileSidebar">
        <div class="offcanvas-header">
            <div class="d-flex align-items-center">
                <div class="me-2" style="width: 36px; height: 36px; background: linear-gradient(135deg, var(--isp-accent), var(--isp-accent-light)); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                    <i class="bi bi-broadcast text-white"></i>
                </div>
                <div>
                    <span class="brand-title text-white">ISP</span>
                    <div class="brand-subtitle">RADIUS Billing</div>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body p-2">
            <a href="?page=dashboard" class="nav-link text-white-50 small mb-2">
                <i class="bi bi-arrow-left me-2"></i> Back to CRM
            </a>
            <nav class="nav flex-column">
                <a class="nav-link <?= $view === 'dashboard' ? 'active' : '' ?>" href="?page=isp&view=dashboard">
                    <i class="bi bi-speedometer2 me-2"></i> Dashboard
                </a>
                <a class="nav-link <?= $view === 'subscriptions' ? 'active' : '' ?>" href="?page=isp&view=subscriptions">
                    <i class="bi bi-people me-2"></i> Subscribers
                </a>
                <a class="nav-link <?= $view === 'packages' ? 'active' : '' ?>" href="?page=isp&view=packages">
                    <i class="bi bi-box me-2"></i> Packages
                </a>
                <a class="nav-link <?= $view === 'addons' ? 'active' : '' ?>" href="?page=isp&view=addons">
                    <i class="bi bi-plus-circle me-2"></i> Addon Services
                </a>
                <a class="nav-link <?= $view === 'nas' ? 'active' : '' ?>" href="?page=isp&view=nas">
                    <i class="bi bi-hdd-network me-2"></i> NAS Devices
                </a>
                <a class="nav-link <?= $view === 'vlans' ? 'active' : '' ?>" href="?page=isp&view=vlans">
                    <i class="bi bi-diagram-3 me-2"></i> VLANs
                </a>
                <hr class="my-2 border-light opacity-25">
                <a class="nav-link <?= $view === 'vouchers' ? 'active' : '' ?>" href="?page=isp&view=vouchers">
                    <i class="bi bi-ticket me-2"></i> Vouchers
                </a>
                <a class="nav-link <?= $view === 'billing' ? 'active' : '' ?>" href="?page=isp&view=billing">
                    <i class="bi bi-receipt me-2"></i> Billing History
                </a>
                <a class="nav-link <?= $view === 'ip_pools' ? 'active' : '' ?>" href="?page=isp&view=ip_pools">
                    <i class="bi bi-diagram-2 me-2"></i> IP Pools
                </a>
                <hr class="my-2 border-light opacity-25">
                <a class="nav-link <?= $view === 'expiring' ? 'active' : '' ?>" href="?page=isp&view=expiring">
                    <i class="bi bi-clock-history me-2"></i> Expiring Soon
                    <?php $expiringCount = count($radiusBilling->getExpiringSubscriptions(7)); if ($expiringCount > 0): ?>
                    <span class="badge bg-warning ms-auto"><?= $expiringCount ?></span>
                    <?php endif; ?>
                </a>
                <a class="nav-link <?= $view === 'reports' ? 'active' : '' ?>" href="?page=isp&view=reports">
                    <i class="bi bi-graph-up me-2"></i> Reports
                </a>
                <a class="nav-link <?= $view === 'analytics' ? 'active' : '' ?>" href="?page=isp&view=analytics">
                    <i class="bi bi-bar-chart me-2"></i> Analytics
                </a>
                <a class="nav-link <?= $view === 'import' ? 'active' : '' ?>" href="?page=isp&view=import">
                    <i class="bi bi-upload me-2"></i> Import CSV
                </a>
                <hr class="my-2 border-light opacity-25">
                <a class="nav-link" href="?page=huawei-olt">
                    <i class="bi bi-router me-2"></i> OLT / Fiber <i class="bi bi-box-arrow-up-right small ms-1 opacity-50"></i>
                </a>
                <a class="nav-link <?= $view === 'settings' ? 'active' : '' ?>" href="?page=isp&view=settings">
                    <i class="bi bi-gear me-2"></i> Settings
                </a>
            </nav>
        </div>
    </div>
    
    <div class="d-flex">
        <div class="sidebar d-none d-lg-flex flex-column p-3" style="width: 260px;">
            <a href="?page=dashboard" class="text-decoration-none small mb-3 px-2 d-flex align-items-center" style="color: rgba(255,255,255,0.5);">
                <i class="bi bi-arrow-left me-1"></i> Back to CRM
            </a>
            <div class="d-flex align-items-center mb-4 px-2">
                <div class="me-3" style="width: 44px; height: 44px; background: linear-gradient(135deg, var(--isp-accent), var(--isp-accent-light)); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                    <i class="bi bi-broadcast fs-5 text-white"></i>
                </div>
                <div>
                    <span class="brand-title">ISP</span>
                    <div class="brand-subtitle">RADIUS Billing</div>
                </div>
            </div>
            <nav class="nav flex-column">
                <a class="nav-link <?= $view === 'dashboard' ? 'active' : '' ?>" href="?page=isp&view=dashboard">
                    <i class="bi bi-speedometer2 me-2"></i> Dashboard
                </a>
                <a class="nav-link <?= $view === 'subscriptions' ? 'active' : '' ?>" href="?page=isp&view=subscriptions">
                    <i class="bi bi-people me-2"></i> Subscribers
                </a>
                <a class="nav-link <?= $view === 'packages' ? 'active' : '' ?>" href="?page=isp&view=packages">
                    <i class="bi bi-box me-2"></i> Packages
                </a>
                <a class="nav-link <?= $view === 'addons' ? 'active' : '' ?>" href="?page=isp&view=addons">
                    <i class="bi bi-plus-circle me-2"></i> Addon Services
                </a>
                <a class="nav-link <?= $view === 'nas' ? 'active' : '' ?>" href="?page=isp&view=nas">
                    <i class="bi bi-hdd-network me-2"></i> NAS Devices
                </a>
                <a class="nav-link <?= $view === 'vlans' ? 'active' : '' ?>" href="?page=isp&view=vlans">
                    <i class="bi bi-diagram-3 me-2"></i> VLANs
                </a>
                <hr class="my-2 border-light opacity-25">
                <a class="nav-link <?= $view === 'vouchers' ? 'active' : '' ?>" href="?page=isp&view=vouchers">
                    <i class="bi bi-ticket me-2"></i> Vouchers
                </a>
                <a class="nav-link <?= $view === 'billing' ? 'active' : '' ?>" href="?page=isp&view=billing">
                    <i class="bi bi-receipt me-2"></i> Billing History
                </a>
                <a class="nav-link <?= $view === 'ip_pools' ? 'active' : '' ?>" href="?page=isp&view=ip_pools">
                    <i class="bi bi-diagram-2 me-2"></i> IP Pools
                </a>
                <hr class="my-2 border-light opacity-25">
                <a class="nav-link <?= $view === 'expiring' ? 'active' : '' ?>" href="?page=isp&view=expiring">
                    <i class="bi bi-clock-history me-2"></i> Expiring Soon
                </a>
                <a class="nav-link <?= $view === 'reports' ? 'active' : '' ?>" href="?page=isp&view=reports">
                    <i class="bi bi-graph-up me-2"></i> Reports
                </a>
                <a class="nav-link <?= $view === 'analytics' ? 'active' : '' ?>" href="?page=isp&view=analytics">
                    <i class="bi bi-bar-chart me-2"></i> Analytics
                </a>
                <a class="nav-link <?= $view === 'import' ? 'active' : '' ?>" href="?page=isp&view=import">
                    <i class="bi bi-upload me-2"></i> Import CSV
                </a>
                <hr class="my-2 border-light opacity-25">
                <a class="nav-link" href="?page=huawei-olt">
                    <i class="bi bi-router me-2"></i> OLT / Fiber <i class="bi bi-box-arrow-up-right small ms-1 opacity-50"></i>
                </a>
                <a class="nav-link <?= $view === 'settings' ? 'active' : '' ?>" href="?page=isp&view=settings">
                    <i class="bi bi-gear me-2"></i> Settings
                </a>
            </nav>
        </div>
        
        <div class="main-content flex-grow-1 p-4">
            <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <?php if ($view === 'dashboard'): ?>
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2 mb-4">
                <div>
                    <h4 class="page-title mb-1"><i class="bi bi-speedometer2"></i> ISP Dashboard</h4>
                    <span class="text-muted small d-none d-sm-inline">Last updated: <?= date('M j, Y H:i:s') ?></span>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="?page=isp&view=subscriptions&filter=expiring" class="btn btn-warning btn-sm">
                        <i class="bi bi-exclamation-triangle me-1"></i> Expiring (<?= $stats['expiring_soon'] ?>)
                    </a>
                    <button class="btn btn-outline-primary btn-sm" onclick="location.reload()">
                        <i class="bi bi-arrow-clockwise"></i><span class="d-none d-sm-inline ms-1">Refresh</span>
                    </button>
                </div>
            </div>
            
            <div class="row g-2 g-md-4 mb-4">
                <div class="col-6 col-md-3">
                    <div class="card stat-card shadow-sm h-100 stat-success">
                        <div class="card-body py-2 py-md-3">
                            <div class="d-flex justify-content-between align-items-start mb-2 mb-md-3">
                                <div class="stat-icon bg-success bg-opacity-10 text-success" style="width: 40px; height: 40px; font-size: 1rem;">
                                    <i class="bi bi-people"></i>
                                </div>
                            </div>
                            <div class="stat-value fs-4 fs-md-3"><?= number_format($stats['active_subscriptions']) ?></div>
                            <div class="stat-label small">Active</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card stat-card shadow-sm h-100 stat-info">
                        <div class="card-body py-2 py-md-3">
                            <div class="d-flex justify-content-between align-items-start mb-2 mb-md-3">
                                <div class="stat-icon bg-info bg-opacity-10 text-info" style="width: 40px; height: 40px; font-size: 1rem;">
                                    <i class="bi bi-wifi"></i>
                                </div>
                            </div>
                            <div class="stat-value fs-4 fs-md-3"><?= number_format($stats['online_now'] ?? 0) ?></div>
                            <div class="stat-label small">Online</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card stat-card shadow-sm h-100 stat-warning">
                        <div class="card-body py-2 py-md-3">
                            <div class="d-flex justify-content-between align-items-start mb-2 mb-md-3">
                                <div class="stat-icon bg-warning bg-opacity-10 text-warning" style="width: 40px; height: 40px; font-size: 1rem;">
                                    <i class="bi bi-exclamation-triangle"></i>
                                </div>
                            </div>
                            <div class="stat-value fs-4 fs-md-3"><?= number_format($stats['expiring_soon']) ?></div>
                            <div class="stat-label small">Expiring</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card stat-card shadow-sm h-100">
                        <div class="card-body py-2 py-md-3">
                            <div class="d-flex justify-content-between align-items-start mb-2 mb-md-3">
                                <div class="stat-icon bg-primary bg-opacity-10 text-primary" style="width: 40px; height: 40px; font-size: 1rem;">
                                    <i class="bi bi-currency-exchange"></i>
                                </div>
                            </div>
                            <div class="stat-value fs-5"><?= number_format($stats['monthly_revenue']) ?></div>
                            <div class="stat-label small">Revenue</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row g-3 mb-4">
                <div class="col-6 col-lg-2">
                    <div class="card shadow-sm h-100">
                        <div class="card-body text-center py-3">
                            <i class="bi bi-person-plus fs-4 text-primary"></i>
                            <h4 class="mb-0 mt-1"><?= number_format($stats['new_this_month'] ?? 0) ?></h4>
                            <small class="text-muted">New This Month</small>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-2">
                    <div class="card shadow-sm h-100">
                        <div class="card-body text-center py-3">
                            <i class="bi bi-graph-up-arrow fs-4 text-info"></i>
                            <h4 class="mb-0 mt-1">KES <?= number_format($stats['arpu'] ?? 0) ?></h4>
                            <small class="text-muted">ARPU</small>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-2">
                    <div class="card shadow-sm h-100">
                        <div class="card-body text-center py-3">
                            <i class="bi bi-arrow-<?= ($stats['revenue_growth'] ?? 0) >= 0 ? 'up' : 'down' ?>-circle fs-4 <?= ($stats['revenue_growth'] ?? 0) >= 0 ? 'text-success' : 'text-danger' ?>"></i>
                            <h4 class="mb-0 mt-1 <?= ($stats['revenue_growth'] ?? 0) >= 0 ? 'text-success' : 'text-danger' ?>"><?= ($stats['revenue_growth'] ?? 0) >= 0 ? '+' : '' ?><?= $stats['revenue_growth'] ?? 0 ?>%</h4>
                            <small class="text-muted">Revenue Growth</small>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-2">
                    <div class="card shadow-sm h-100">
                        <div class="card-body text-center py-3">
                            <i class="bi bi-pause-circle fs-4 text-warning"></i>
                            <h4 class="mb-0 mt-1"><?= $stats['suspended_subscriptions'] ?></h4>
                            <small class="text-muted">Suspended</small>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-2">
                    <div class="card shadow-sm h-100">
                        <div class="card-body text-center py-3">
                            <i class="bi bi-x-circle fs-4 text-danger"></i>
                            <h4 class="mb-0 mt-1"><?= $stats['expired_subscriptions'] ?></h4>
                            <small class="text-muted">Expired</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="card shadow-sm">
                        <div class="card-body text-center py-3">
                            <i class="bi bi-hdd-network fs-4 text-primary"></i>
                            <h5 class="mb-0 mt-1"><?= $stats['nas_devices'] ?></h5>
                            <small class="text-muted">NAS Devices</small>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card shadow-sm">
                        <div class="card-body text-center py-3">
                            <i class="bi bi-box fs-4 text-success"></i>
                            <h5 class="mb-0 mt-1"><?= $stats['packages'] ?></h5>
                            <small class="text-muted">Packages</small>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card shadow-sm">
                        <div class="card-body text-center py-3">
                            <i class="bi bi-ticket fs-4 text-info"></i>
                            <h5 class="mb-0 mt-1"><?= $stats['unused_vouchers'] ?></h5>
                            <small class="text-muted">Vouchers</small>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card shadow-sm">
                        <div class="card-body text-center py-3">
                            <i class="bi bi-download fs-4 text-secondary"></i>
                            <h5 class="mb-0 mt-1"><?= $stats['today_data_gb'] ?> GB</h5>
                            <small class="text-muted">Today's Usage</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row g-3 mb-4">
                <div class="col-12">
                    <div class="card shadow-sm bg-gradient" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <div class="card-body py-3">
                            <div class="row align-items-center">
                                <div class="col-md-6 mb-3 mb-md-0">
                                    <h5 class="text-white mb-1"><i class="bi bi-lightning-fill me-2"></i>Quick Actions</h5>
                                    <div class="d-flex flex-wrap gap-2">
                                        <a href="?page=isp&view=subscriptions" class="btn btn-light btn-sm">
                                            <i class="bi bi-person-plus me-1"></i>Add Subscriber
                                        </a>
                                        <a href="?page=isp&view=vouchers" class="btn btn-light btn-sm">
                                            <i class="bi bi-ticket me-1"></i>Generate Vouchers
                                        </a>
                                        <a href="?page=isp&view=expiring" class="btn btn-warning btn-sm">
                                            <i class="bi bi-clock-history me-1"></i>Expiring (<?= $stats['expiring_soon'] ?>)
                                        </a>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="text-white-50 mb-1"><i class="bi bi-globe me-2"></i>Public Portals</h6>
                                    <div class="d-flex flex-wrap gap-2">
                                        <a href="/hotspot.php" target="_blank" class="btn btn-outline-light btn-sm">
                                            <i class="bi bi-wifi me-1"></i>Hotspot Login
                                        </a>
                                        <a href="/expired.php" target="_blank" class="btn btn-outline-light btn-sm">
                                            <i class="bi bi-arrow-clockwise me-1"></i>Renewal Page
                                        </a>
                                        <a href="/portal" target="_blank" class="btn btn-outline-light btn-sm">
                                            <i class="bi bi-person-circle me-1"></i>Customer Portal
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row g-3 mb-4">
                <div class="col-lg-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-white"><h6 class="mb-0"><i class="bi bi-hdd-network me-2 text-primary"></i>NAS Status</h6></div>
                        <div class="card-body p-0">
                            <?php $nasDevices = $radiusBilling->getNASDevices(); ?>
                            <?php if (empty($nasDevices)): ?>
                            <div class="p-3 text-center text-muted">
                                <i class="bi bi-hdd-network fs-3 d-block mb-2"></i>
                                No NAS devices configured
                                <br><a href="?page=isp&view=nas" class="btn btn-sm btn-primary mt-2">Add NAS</a>
                            </div>
                            <?php else: ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach (array_slice($nasDevices, 0, 4) as $nas): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                                    <div>
                                        <strong class="small"><?= htmlspecialchars($nas['name']) ?></strong>
                                        <br><code class="small"><?= htmlspecialchars($nas['ip_address']) ?></code>
                                    </div>
                                    <span class="badge <?= ($nas['enabled'] ?? true) ? 'bg-success' : 'bg-secondary' ?>"><?= ($nas['enabled'] ?? true) ? 'Active' : 'Off' ?></span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-white"><h6 class="mb-0"><i class="bi bi-box me-2 text-success"></i>Popular Packages</h6></div>
                        <div class="card-body p-0">
                            <?php
                            $pkgStats = [];
                            try {
                                $stmt = $db->query("
                                    SELECT p.name, p.price, COUNT(s.id) as sub_count 
                                    FROM radius_packages p 
                                    LEFT JOIN radius_subscriptions s ON s.package_id = p.id AND s.status = 'active'
                                    WHERE p.status = 'active'
                                    GROUP BY p.id, p.name, p.price 
                                    ORDER BY sub_count DESC 
                                    LIMIT 4
                                ");
                                if ($stmt) {
                                    $pkgStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                }
                            } catch (Exception $e) {
                                $pkgStats = [];
                            }
                            ?>
                            <?php if (empty($pkgStats)): ?>
                            <div class="p-3 text-center text-muted">No packages</div>
                            <?php else: ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($pkgStats as $pkg): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                                    <span class="small"><?= htmlspecialchars($pkg['name']) ?></span>
                                    <div>
                                        <span class="badge bg-primary"><?= $pkg['sub_count'] ?> subs</span>
                                        <span class="badge bg-success ms-1">KES <?= number_format($pkg['price']) ?></span>
                                    </div>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-white"><h6 class="mb-0"><i class="bi bi-cash-coin me-2 text-info"></i>Recent Payments</h6></div>
                        <div class="card-body p-0">
                            <?php
                            $recentPayments = [];
                            try {
                                $stmt = $db->query("
                                    SELECT b.amount, b.payment_date, s.username, c.name as customer_name
                                    FROM radius_billing b
                                    LEFT JOIN radius_subscriptions s ON b.subscription_id = s.id
                                    LEFT JOIN customers c ON s.customer_id = c.id
                                    WHERE b.status = 'paid'
                                    ORDER BY b.payment_date DESC
                                    LIMIT 4
                                ");
                                if ($stmt) {
                                    $recentPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                }
                            } catch (Exception $e) {
                                $recentPayments = [];
                            }
                            ?>
                            <?php if (empty($recentPayments)): ?>
                            <div class="p-3 text-center text-muted">No recent payments</div>
                            <?php else: ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($recentPayments as $pmt): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                                    <div>
                                        <span class="small"><?= htmlspecialchars($pmt['customer_name'] ?? $pmt['username']) ?></span>
                                        <br><small class="text-muted"><?= date('M j, H:i', strtotime($pmt['payment_date'])) ?></small>
                                    </div>
                                    <span class="badge bg-success">KES <?= number_format($pmt['amount']) ?></span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-lg-6">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-exclamation-triangle me-2 text-warning"></i>Expiring Subscribers</h5>
                            <a href="?page=isp&view=subscriptions&filter=expiring" class="btn btn-sm btn-outline-warning">View All</a>
                        </div>
                        <div class="card-body p-0">
                            <?php $expiring = $radiusBilling->getSubscriptions(['expiring_soon' => true, 'limit' => 5]); ?>
                            <?php if (empty($expiring)): ?>
                            <div class="p-4 text-center text-muted">No subscribers expiring soon</div>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Customer</th>
                                            <th>Package</th>
                                            <th>Expires</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($expiring as $sub): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($sub['customer_name'] ?? $sub['username']) ?></td>
                                            <td><?= htmlspecialchars($sub['package_name']) ?></td>
                                            <td><?= date('M j', strtotime($sub['expiry_date'])) ?></td>
                                            <td>
                                                <form method="post" class="d-inline">
                                                    <input type="hidden" name="action" value="renew_subscription">
                                                    <input type="hidden" name="id" value="<?= $sub['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-success">Renew</button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
            </div>

            <?php elseif ($view === 'subscriptions'): ?>
            <?php
            $filter = $_GET['filter'] ?? '';
            $filters = ['search' => $_GET['search'] ?? ''];
            if ($filter === 'expiring') $filters['expiring_soon'] = true;
            if ($filter === 'expired') $filters['expired'] = true;
            if (!empty($_GET['status'])) $filters['status'] = $_GET['status'];
            if (!empty($_GET['package_id'])) $filters['package_id'] = (int)$_GET['package_id'];
            
            $packages = $radiusBilling->getPackages();
            $nasDevices = $radiusBilling->getNASDevices();
            $ispLocations = $radiusBilling->getLocations();
            $ispSubLocations = $radiusBilling->getAllSubLocations();
            $onlineSubscribers = $radiusBilling->getOnlineSubscribers();
            $onlineFilter = $_GET['online'] ?? '';
            
            // Add online/offline filter to database query if applicable
            $onlineSubIds = array_keys($onlineSubscribers);
            if ($onlineFilter === 'online' && !empty($onlineSubIds)) {
                $filters['subscription_ids'] = $onlineSubIds;
            } elseif ($onlineFilter === 'offline') {
                $filters['exclude_subscription_ids'] = $onlineSubIds;
            }
            
            // Pagination setup
            $perPage = 25;
            $currentPage = max(1, (int)($_GET['pg'] ?? 1));
            $totalCount = $radiusBilling->countSubscriptions($filters);
            $totalPages = max(1, ceil($totalCount / $perPage));
            $currentPage = min($currentPage, $totalPages);
            $offset = ($currentPage - 1) * $perPage;
            
            $filters['limit'] = $perPage;
            $filters['offset'] = $offset;
            $subscriptions = $radiusBilling->getSubscriptions($filters);
            
            // Calculate quick stats for this view
            $totalSubs = $totalCount;
            $onlineCount = count(array_filter($subscriptions, fn($s) => isset($onlineSubscribers[$s['id']])));
            $activeCount = count(array_filter($subscriptions, fn($s) => $s['status'] === 'active'));
            $inactiveCount = count(array_filter($subscriptions, fn($s) => $s['status'] === 'inactive'));
            $expiringSoonCount = count(array_filter($subscriptions, fn($s) => $s['expiry_date'] && strtotime($s['expiry_date']) < strtotime('+7 days') && strtotime($s['expiry_date']) > time()));
            ?>
            
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                <div>
                    <h4 class="page-title mb-1"><i class="bi bi-people"></i> Subscribers</h4>
                    <div class="d-flex gap-3 flex-wrap align-items-center">
                        <span class="badge bg-light text-dark border px-3 py-2"><i class="bi bi-people-fill me-1"></i><?= $totalSubs ?> total</span>
                        <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2" id="onlineCountBadge"><i class="bi bi-wifi me-1"></i><span id="onlineCountNum"><?= count($onlineSubscribers) ?></span> online</span>
                        <span class="badge bg-secondary-subtle text-secondary border px-2 py-2 ms-2" id="realTimeIndicator" title="Connecting to real-time updates..."><i class="bi bi-broadcast me-1"></i>Live</span>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2"><i class="bi bi-check-circle me-1"></i><?= $activeCount ?> active</span>
                        <?php if ($inactiveCount > 0): ?>
                        <a href="?page=isp&view=subscriptions&status=inactive" class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-3 py-2 text-decoration-none"><i class="bi bi-hourglass me-1"></i><?= $inactiveCount ?> inactive</a>
                        <?php endif; ?>
                        <?php if ($expiringSoonCount > 0): ?>
                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-2"><i class="bi bi-exclamation-triangle me-1"></i><?= $expiringSoonCount ?> expiring soon</span>
                        <?php endif; ?>
                        <button type="button" class="btn btn-sm btn-outline-success" onclick="refreshOnlineStatus()" title="Refresh online status">
                            <i class="bi bi-arrow-clockwise" id="refreshIcon"></i>
                        </button>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="bi bi-download me-1"></i> Export
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="?page=isp&view=export_subscribers&format=csv"><i class="bi bi-filetype-csv me-2"></i>Export CSV</a></li>
                            <li><a class="dropdown-item" href="?page=isp&view=export_subscribers&format=excel"><i class="bi bi-file-earmark-excel me-2"></i>Export Excel</a></li>
                        </ul>
                    </div>
                    <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#bulkActionsPanel">
                        <i class="bi bi-check2-square me-1"></i> Bulk Actions
                    </button>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSubscriptionModal">
                        <i class="bi bi-plus-lg me-1"></i> New Subscriber
                    </button>
                </div>
            </div>
            
            <div class="collapse mb-3" id="bulkActionsPanel">
                <div class="card bg-light border-0">
                    <div class="card-body py-2">
                        <form method="post" id="bulkActionForm" class="d-flex align-items-center gap-2 flex-wrap">
                            <input type="hidden" name="action" id="bulkActionType" value="">
                            <input type="hidden" name="bulk_ids" id="bulkIds" value="">
                            <span class="text-muted me-2"><span id="selectedCount">0</span> selected</span>
                            <button type="button" class="btn btn-sm btn-outline-success" onclick="bulkAction('activate')">
                                <i class="bi bi-play-fill"></i> Activate
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-warning" onclick="bulkAction('suspend')">
                                <i class="bi bi-pause-fill"></i> Suspend
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-info" onclick="bulkAction('send_sms')">
                                <i class="bi bi-chat-dots"></i> Send SMS
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="bulkAction('renew')">
                                <i class="bi bi-arrow-clockwise"></i> Renew All
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="card shadow-sm">
                <div class="card-header bg-white py-3">
                    <form method="get" class="row g-2 align-items-end">
                        <input type="hidden" name="page" value="isp">
                        <input type="hidden" name="view" value="subscriptions">
                        <div class="col-lg-3 col-md-6">
                            <label class="form-label small text-muted mb-1">Search</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                                <input type="text" name="search" class="form-control" placeholder="Username, customer, phone..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-lg-2 col-md-3">
                            <label class="form-label small text-muted mb-1">Status</label>
                            <select name="status" class="form-select">
                                <option value="">All Status</option>
                                <option value="active" <?= ($_GET['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="suspended" <?= ($_GET['status'] ?? '') === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                                <option value="expired" <?= ($_GET['status'] ?? '') === 'expired' ? 'selected' : '' ?>>Expired</option>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-3">
                            <label class="form-label small text-muted mb-1">Package</label>
                            <select name="package_id" class="form-select">
                                <option value="">All Packages</option>
                                <?php foreach ($packages as $pkg): ?>
                                <option value="<?= $pkg['id'] ?>" <?= ((int)($_GET['package_id'] ?? 0)) === $pkg['id'] ? 'selected' : '' ?>><?= htmlspecialchars($pkg['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-3">
                            <label class="form-label small text-muted mb-1">Connection</label>
                            <select name="online" class="form-select">
                                <option value="">All</option>
                                <option value="online" <?= ($_GET['online'] ?? '') === 'online' ? 'selected' : '' ?>>Online</option>
                                <option value="offline" <?= ($_GET['online'] ?? '') === 'offline' ? 'selected' : '' ?>>Offline</option>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-3">
                            <label class="form-label small text-muted mb-1">Filter</label>
                            <select name="filter" class="form-select">
                                <option value="">All Subscribers</option>
                                <option value="expiring" <?= ($filter) === 'expiring' ? 'selected' : '' ?>>Expiring Soon</option>
                                <option value="expired" <?= ($filter) === 'expired' ? 'selected' : '' ?>>Expired Only</option>
                            </select>
                        </div>
                        <div class="col-lg-1 col-md-3">
                            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel"></i></button>
                        </div>
                    </form>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="subscribersTable">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 40px;">
                                        <input type="checkbox" class="form-check-input" id="selectAllSubs" onchange="toggleSelectAll(this)">
                                    </th>
                                    <th>Username</th>
                                    <th>Name</th>
                                    <th>Phone</th>
                                    <th>Zone</th>
                                    <th>Package</th>
                                    <th>IP Address</th>
                                    <th class="text-center">Status</th>
                                    <th>Expiry</th>
                                    <th class="text-end">Usage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($subscriptions as $sub): ?>
                                <?php 
                                $isOnline = isset($onlineSubscribers[$sub['id']]);
                                $onlineInfo = $isOnline ? $onlineSubscribers[$sub['id']] : null;
                                // Check if actually expired based on date (regardless of DB status)
                                $isExpiringSoon = $sub['expiry_date'] && strtotime($sub['expiry_date']) < strtotime('+7 days') && strtotime($sub['expiry_date']) > time();
                                $isExpired = $sub['expiry_date'] && strtotime($sub['expiry_date']) < time();
                                $daysLeft = $sub['expiry_date'] ? ceil((strtotime($sub['expiry_date']) - time()) / 86400) : null;
                                
                                // Determine display status - override with Expired if date has passed
                                $displayStatus = $sub['status'];
                                if ($isExpired && $sub['status'] === 'active') {
                                    $displayStatus = 'expired';
                                }
                                
                                $statusClass = match($displayStatus) {
                                    'active' => 'success',
                                    'suspended' => 'warning',
                                    'expired' => 'danger',
                                    'inactive' => 'secondary',
                                    default => 'secondary'
                                };
                                $statusLabel = match($displayStatus) {
                                    'active' => 'Active',
                                    'suspended' => 'Suspended',
                                    'expired' => 'Expired',
                                    'inactive' => 'Inactive',
                                    default => ucfirst($displayStatus)
                                };
                                $needsActivation = ($sub['status'] === 'inactive');
                                ?>
                                <tr class="sub-row" data-sub-id="<?= $sub['id'] ?>">
                                    <td>
                                        <input type="checkbox" class="form-check-input sub-checkbox" value="<?= $sub['id'] ?>" onchange="updateBulkCount()">
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <?php if ($isOnline): ?>
                                            <span class="badge bg-success rounded-circle p-1" title="Online"><i class="bi bi-wifi"></i></span>
                                            <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-secondary rounded-circle p-1" title="Offline"><i class="bi bi-wifi-off"></i></span>
                                            <?php endif; ?>
                                            <div>
                                                <a href="?page=isp&view=subscriber&id=<?= $sub['id'] ?>" class="fw-bold text-decoration-none"><?= htmlspecialchars($sub['username']) ?></a>
                                                <button class="btn btn-link btn-sm p-0 text-muted ms-1" onclick="copyToClipboard('<?= htmlspecialchars($sub['username']) ?>')" title="Copy"><i class="bi bi-clipboard"></i></button>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="text-truncate" style="max-width: 150px; display: inline-block;"><?= htmlspecialchars($sub['customer_name'] ?? '-') ?></span>
                                    </td>
                                    <td>
                                        <?php if (!empty($sub['customer_phone'])): ?>
                                        <a href="tel:<?= htmlspecialchars($sub['customer_phone']) ?>" class="text-decoration-none"><?= htmlspecialchars($sub['customer_phone']) ?></a>
                                        <a href="?page=whatsapp&phone=<?= urlencode(preg_replace('/[^0-9]/', '', $sub['customer_phone'])) ?>" class="text-success ms-1" title="Quick Chat"><i class="bi bi-whatsapp"></i></a>
                                        <?php else: ?>
                                        <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($sub['zone_name']) || !empty($sub['subzone_name'])): ?>
                                        <div class="small">
                                            <?php if (!empty($sub['zone_name'])): ?>
                                            <span class="badge bg-secondary-subtle text-secondary"><?= htmlspecialchars($sub['zone_name']) ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($sub['subzone_name'])): ?>
                                            <div class="text-muted mt-1"><?= htmlspecialchars($sub['subzone_name']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <?php else: ?>
                                        <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle"><?= htmlspecialchars($sub['package_name']) ?></span>
                                        <div class="small text-muted mt-1">
                                            <i class="bi bi-arrow-down text-success"></i> <?= $sub['download_speed'] ?>
                                            <i class="bi bi-arrow-up text-danger ms-1"></i> <?= $sub['upload_speed'] ?>
                                        </div>
                                        <div class="small text-muted">KES <?= number_format($sub['package_price'] ?? 0) ?></div>
                                    </td>
                                    <td>
                                        <?php 
                                        $displayIp = null;
                                        if ($isOnline && !empty($onlineInfo['ip'])) {
                                            $displayIp = $onlineInfo['ip'];
                                        } elseif (!empty($sub['static_ip'])) {
                                            $displayIp = $sub['static_ip'];
                                        }
                                        ?>
                                        <?php if ($displayIp): ?>
                                        <a href="http://<?= htmlspecialchars($displayIp) ?>" target="_blank" class="badge <?= $isOnline ? 'bg-success' : 'bg-secondary-subtle text-secondary' ?> text-decoration-none" title="<?= $isOnline ? 'Online - Click to open router' : 'Static IP (offline)' ?>">
                                            <i class="bi bi-<?= $isOnline ? 'wifi' : 'hdd-network' ?>"></i> <?= htmlspecialchars($displayIp) ?>
                                        </a>
                                        <?php else: ?>
                                        <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-<?= $statusClass ?> px-3 status-badge"><?= $statusLabel ?></span>
                                        <?php if ($needsActivation): ?>
                                        <div class="mt-1">
                                            <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle"></i> Awaiting Payment</span>
                                        </div>
                                        <?php endif; ?>
                                        <div class="mt-1">
                                            <span class="badge bg-light text-dark border"><?= strtoupper($sub['access_type']) ?></span>
                                        </div>
                                        <?php if ($sub['mac_address']): ?>
                                        <div class="small text-success mt-1" title="MAC Bound"><i class="bi bi-lock-fill"></i> MAC</div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($needsActivation): ?>
                                            <span class="text-muted">Not activated</span>
                                        <?php elseif ($sub['expiry_date']): ?>
                                            <div class="<?= $isExpired ? 'text-danger' : ($isExpiringSoon ? 'text-warning' : '') ?> expiry-date">
                                                <?= date('M j, Y', strtotime($sub['expiry_date'])) ?>
                                            </div>
                                            <?php if ($isExpired): ?>
                                            <span class="badge bg-danger"><i class="bi bi-x-circle"></i> <?= abs($daysLeft) ?>d ago</span>
                                            <?php elseif ($isExpiringSoon): ?>
                                            <span class="badge bg-warning text-dark"><i class="bi bi-clock"></i> <?= $daysLeft ?>d left</span>
                                            <?php else: ?>
                                            <span class="badge bg-success-subtle text-success"><?= $daysLeft ?>d left</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">No expiry</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <?php 
                                        $usageGB = $sub['data_used_mb'] / 1024;
                                        $quotaGB = ($sub['data_quota_mb'] ?? 0) / 1024;
                                        $usagePercent = $quotaGB > 0 ? min(100, ($usageGB / $quotaGB) * 100) : 0;
                                        ?>
                                        <div class="fw-bold"><?= number_format($usageGB, 2) ?> GB</div>
                                        <?php if ($quotaGB > 0): ?>
                                        <div class="progress mt-1" style="height: 4px; width: 60px; margin-left: auto;">
                                            <div class="progress-bar <?= $usagePercent >= 100 ? 'bg-danger' : ($usagePercent >= 80 ? 'bg-warning' : 'bg-success') ?>" style="width: <?= $usagePercent ?>%;"></div>
                                        </div>
                                        <div class="small text-muted"><?= round($usagePercent) ?>% of <?= number_format($quotaGB, 0) ?>GB</div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($totalPages > 1): ?>
                    <div class="card-footer bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="text-muted small">
                            Showing <?= $offset + 1 ?>-<?= min($offset + $perPage, $totalCount) ?> of <?= number_format($totalCount) ?> subscribers
                        </div>
                        <nav>
                            <ul class="pagination pagination-sm mb-0">
                                <?php
                                $queryParams = $_GET;
                                unset($queryParams['pg']);
                                $baseUrl = '?' . http_build_query($queryParams);
                                ?>
                                <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                                    <a class="page-link" href="<?= $baseUrl ?>&pg=1"><i class="bi bi-chevron-double-left"></i></a>
                                </li>
                                <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                                    <a class="page-link" href="<?= $baseUrl ?>&pg=<?= $currentPage - 1 ?>"><i class="bi bi-chevron-left"></i></a>
                                </li>
                                <?php
                                $startPage = max(1, $currentPage - 2);
                                $endPage = min($totalPages, $currentPage + 2);
                                for ($p = $startPage; $p <= $endPage; $p++):
                                ?>
                                <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= $baseUrl ?>&pg=<?= $p ?>"><?= $p ?></a>
                                </li>
                                <?php endfor; ?>
                                <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                                    <a class="page-link" href="<?= $baseUrl ?>&pg=<?= $currentPage + 1 ?>"><i class="bi bi-chevron-right"></i></a>
                                </li>
                                <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                                    <a class="page-link" href="<?= $baseUrl ?>&pg=<?= $totalPages ?>"><i class="bi bi-chevron-double-right"></i></a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="modal fade" id="addSubscriptionModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <form method="post">
                            <input type="hidden" name="action" value="create_subscription">
                            <div class="modal-header">
                                <h5 class="modal-title">New Subscriber</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <div class="btn-group w-100" role="group">
                                        <input type="radio" class="btn-check" name="customer_mode" id="existingCustomer" value="existing" checked onchange="toggleCustomerMode()">
                                        <label class="btn btn-outline-primary" for="existingCustomer">
                                            <i class="bi bi-person-check me-1"></i> Select Existing Customer
                                        </label>
                                        <input type="radio" class="btn-check" name="customer_mode" id="newCustomer" value="new" onchange="toggleCustomerMode()">
                                        <label class="btn btn-outline-success" for="newCustomer">
                                            <i class="bi bi-person-plus me-1"></i> Create New Customer
                                        </label>
                                    </div>
                                </div>
                                
                                <div id="existingCustomerSection">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Customer</label>
                                            <select name="customer_id" id="customerSelect" class="form-select">
                                                <option value="">Select Customer</option>
                                                <?php foreach ($customers as $c): ?>
                                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?> (<?= $c['phone'] ?>)</option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Package</label>
                                            <select name="package_id" class="form-select" required>
                                                <option value="">Select Package</option>
                                                <?php foreach ($packages as $p): ?>
                                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?> - KES <?= number_format($p['price']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div id="newCustomerSection" style="display: none;">
                                    <div class="card bg-light mb-3">
                                        <div class="card-header"><i class="bi bi-person-plus me-1"></i> New Customer Details</div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Customer Name <span class="text-danger">*</span></label>
                                                    <input type="text" name="new_customer_name" id="newCustomerName" class="form-control" placeholder="Full name">
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                                                    <input type="text" name="new_customer_phone" id="newCustomerPhone" class="form-control" placeholder="07XXXXXXXX">
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Email</label>
                                                    <input type="email" name="new_customer_email" class="form-control" placeholder="email@example.com">
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Address</label>
                                                    <input type="text" name="new_customer_address" class="form-control" placeholder="Physical address">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Package</label>
                                            <select name="package_id_new" class="form-select">
                                                <option value="">Select Package</option>
                                                <?php foreach ($packages as $p): ?>
                                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?> - KES <?= number_format($p['price']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Username (PPPoE)</label>
                                        <input type="text" name="username" id="pppoe_username" class="form-control" value="<?= htmlspecialchars($radiusBilling->getNextUsername()) ?>" readonly style="background-color: #e9ecef;">
                                        <small class="text-muted">Auto-generated, cannot be changed</small>
                                    </div>
                                    <div class="col-md-5 mb-3">
                                        <label class="form-label">Password</label>
                                        <div class="input-group">
                                            <input type="text" name="password" id="pppoe_password" class="form-control" value="<?= htmlspecialchars($radiusBilling->generatePassword()) ?>" required>
                                            <button type="button" class="btn btn-outline-secondary" onclick="togglePasswordVisibility()" title="Toggle visibility">
                                                <i class="bi bi-eye" id="passwordToggleIcon"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-md-3 mb-3 d-flex align-items-end">
                                        <button type="button" class="btn btn-info w-100" onclick="regeneratePassword()">
                                            <i class="bi bi-magic me-1"></i> New Password
                                        </button>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Access Type</label>
                                        <select name="access_type" id="access_type" class="form-select" onchange="toggleStaticFields()">
                                            <option value="pppoe">PPPoE</option>
                                            <option value="hotspot">Hotspot</option>
                                            <option value="static">Static IP</option>
                                            <option value="dhcp">DHCP</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3" id="static_ip_field" style="display: none;">
                                        <label class="form-label">Static IP <span class="text-danger">*</span></label>
                                        <input type="text" name="static_ip" id="static_ip_input" class="form-control" placeholder="e.g., 192.168.1.100">
                                    </div>
                                    <div class="col-md-4 mb-3" id="nas_field" style="display: none;">
                                        <label class="form-label">NAS Device <span class="text-danger">*</span></label>
                                        <select name="nas_id" id="nas_id_select" class="form-select">
                                            <option value="">Select NAS</option>
                                            <?php foreach ($nasDevices as $nas): ?>
                                            <option value="<?= $nas['id'] ?>"><?= htmlspecialchars($nas['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Zone <span class="text-danger">*</span></label>
                                        <select name="location_id" id="sub_location" class="form-select" required onchange="filterSubLocations(this, 'sub_sub_location')">
                                            <option value="">-- Select Zone --</option>
                                            <?php foreach ($ispLocations as $loc): ?>
                                            <option value="<?= $loc['id'] ?>"><?= htmlspecialchars($loc['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Sub-Zone</label>
                                        <select name="sub_location_id" id="sub_sub_location" class="form-select">
                                            <option value="">-- Select Sub-Zone --</option>
                                            <?php foreach ($ispSubLocations as $sub): ?>
                                            <option value="<?= $sub['id'] ?>" data-location="<?= $sub['location_id'] ?>"><?= htmlspecialchars($sub['location_name']) ?> - <?= htmlspecialchars($sub['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Notes</label>
                                    <textarea name="notes" class="form-control" rows="2"></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Create Subscriber</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <?php elseif ($view === 'subscriber'): ?>
            <?php
            $subId = (int)($_GET['id'] ?? 0);
            $subscriber = $radiusBilling->getSubscription($subId);
            if (!$subscriber) {
                echo '<div class="alert alert-danger">Subscriber not found.</div>';
            } else {
                $customer = null;
                if ($subscriber['customer_id']) {
                    $stmt = $db->prepare("SELECT * FROM customers WHERE id = ?");
                    $stmt->execute([$subscriber['customer_id']]);
                    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
                }
                $package = $radiusBilling->getPackage($subscriber['package_id']);
                $packages = $radiusBilling->getPackages();
                $nasDevices = $radiusBilling->getNASDevices();
                
                // Get billing history
                $stmt = $db->prepare("SELECT * FROM radius_billing WHERE subscription_id = ? ORDER BY created_at DESC LIMIT 20");
                $stmt->execute([$subId]);
                $billingHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Get M-Pesa payment records for this subscription
                $mpesaPayments = [];
                try {
                    $stmt = $db->prepare("
                        SELECT t.*, 'stk' as source FROM mpesa_transactions t 
                        WHERE t.account_reference LIKE ? AND t.status = 'completed'
                        UNION ALL
                        SELECT c.id, 'c2b' as transaction_type, c.trans_id as merchant_request_id, 
                               c.trans_id as checkout_request_id, c.msisdn as phone_number,
                               c.trans_amount as amount, c.bill_ref_number as account_reference,
                               'C2B Payment' as transaction_desc, c.customer_id, 'completed' as status,
                               c.trans_id as mpesa_receipt_number, NULL as result_code, NULL as result_desc,
                               c.trans_time as created_at, c.trans_time as updated_at, 'c2b' as source
                        FROM mpesa_c2b_transactions c
                        WHERE c.bill_ref_number = ? OR c.bill_ref_number LIKE ?
                        ORDER BY created_at DESC LIMIT 30
                    ");
                    $stmt->execute(['radius_' . $subId, $subscriber['username'], '%' . substr(preg_replace('/[^0-9]/', '', $customer['phone'] ?? ''), -9)]);
                    $mpesaPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (Exception $e) {
                    // Tables might not exist
                }
                
                // Get session history (limit to 3 for display, but get more for stats)
                $stmt = $db->prepare("SELECT * FROM radius_sessions WHERE subscription_id = ? ORDER BY session_start DESC LIMIT 20");
                $stmt->execute([$subId]);
                $allSessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $sessionHistory = array_slice($allSessions, 0, 3); // Only show 3 in table
                
                // Get active session
                $stmt = $db->prepare("SELECT * FROM radius_sessions WHERE subscription_id = ? AND session_end IS NULL ORDER BY session_start DESC LIMIT 1");
                $stmt->execute([$subId]);
                $activeSession = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Get invoices
                $stmt = $db->prepare("SELECT * FROM radius_invoices WHERE subscription_id = ? ORDER BY created_at DESC LIMIT 20");
                $stmt->execute([$subId]);
                $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Get auth logs (login attempts with accept/reject status)
                $authLogs = $radiusBilling->getAuthLogs($subId, 20);
                
                // Get linked ONU information
                $linkedOnu = null;
                if (!empty($subscriber['huawei_onu_id'])) {
                    $stmt = $db->prepare("SELECT id, name, serial_number, genieacs_id, status FROM huawei_onus WHERE id = ?");
                    $stmt->execute([$subscriber['huawei_onu_id']]);
                    $linkedOnu = $stmt->fetch(PDO::FETCH_ASSOC);
                }
                
                // Get tickets for this customer
                $tickets = [];
                if ($customer) {
                    $stmt = $db->prepare("SELECT t.*, (SELECT COUNT(*) FROM ticket_comments WHERE ticket_id = t.id) as comment_count FROM tickets t WHERE t.customer_id = ? ORDER BY t.created_at DESC LIMIT 10");
                    $stmt->execute([$customer['id']]);
                    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
                
                $isOnline = !empty($activeSession);
                
                // Calculate uptime or offline duration
                // Database stores timestamps in UTC, convert to local timezone for comparison
                $uptimeStr = '';
                $offlineStr = '';
                if ($isOnline && $activeSession) {
                    // Parse session_start as UTC, then convert to local for proper comparison
                    $sessionStart = new DateTime($activeSession['session_start'], new DateTimeZone('UTC'));
                    $now = new DateTime('now', new DateTimeZone('UTC'));
                    $uptime = $now->getTimestamp() - $sessionStart->getTimestamp();
                    if ($uptime < 0) $uptime = 0;
                    $days = floor($uptime / 86400);
                    $hours = floor(($uptime % 86400) / 3600);
                    $mins = floor(($uptime % 3600) / 60);
                    $secs = $uptime % 60;
                    if ($days > 0) {
                        $uptimeStr = $days . 'd ' . sprintf('%02d:%02d:%02d', $hours, $mins, $secs);
                    } else {
                        $uptimeStr = sprintf('%02d:%02d:%02d', $hours, $mins, $secs);
                    }
                } else {
                    // Find last session end time
                    $lastSession = null;
                    foreach ($allSessions as $s) {
                        if (!empty($s['session_end'])) {
                            $lastSession = $s;
                            break;
                        }
                    }
                    if ($lastSession) {
                        $sessionEnd = new DateTime($lastSession['session_end'], new DateTimeZone('UTC'));
                        $now = new DateTime('now', new DateTimeZone('UTC'));
                        $offline = $now->getTimestamp() - $sessionEnd->getTimestamp();
                        if ($offline < 0) $offline = 0;
                        $days = floor($offline / 86400);
                        $hours = floor(($offline % 86400) / 3600);
                        $mins = floor(($offline % 3600) / 60);
                        $secs = $offline % 60;
                        if ($days > 0) {
                            $offlineStr = $days . 'd ' . sprintf('%02d:%02d:%02d', $hours, $mins, $secs) . ' ago';
                        } else {
                            $offlineStr = sprintf('%02d:%02d:%02d', $hours, $mins, $secs) . ' ago';
                        }
                    } else {
                        $offlineStr = 'Never connected';
                    }
                }
                
                // Check if actually expired based on date
                $isSubExpired = $subscriber['expiry_date'] && strtotime($subscriber['expiry_date']) < time();
                $displayStatus = $subscriber['status'];
                if ($isSubExpired && $subscriber['status'] === 'active') {
                    $displayStatus = 'expired';
                }
                
                $statusClass = match($displayStatus) {
                    'active' => 'success',
                    'suspended' => 'warning',
                    'expired' => 'danger',
                    default => 'secondary'
                };
                $statusLabel = ucfirst($displayStatus);
            ?>
            
            <?php
            $totalSessions = count($allSessions);
            $totalDownload = array_sum(array_column($allSessions, 'input_octets')) / 1073741824;
            $totalUpload = array_sum(array_column($allSessions, 'output_octets')) / 1073741824;
            $lastSession = !empty($allSessions) ? $allSessions[0] : null;
            $avgSessionDuration = $totalSessions > 0 ? array_sum(array_map(fn($s) => 
                (($s['session_end'] ? strtotime($s['session_end']) : time()) - strtotime($s['session_start'])), 
                $allSessions)) / $totalSessions / 3600 : 0;
            ?>
            
            <!-- Premium Subscriber Header Card -->
            <div class="card border-0 shadow-lg mb-4 overflow-hidden">
                <div class="card-body p-0">
                    <div class="row g-0">
                        <!-- Left: Profile Section with Gradient -->
                        <div class="col-lg-4" style="background: linear-gradient(135deg, <?= $isOnline ? '#198754' : '#6c757d' ?> 0%, <?= $isOnline ? '#0d6efd' : '#495057' ?> 100%);">
                            <div class="p-4 text-white text-center">
                                <a href="?page=isp&view=subscriptions" class="btn btn-sm btn-light btn-outline-light mb-3 opacity-75">
                                    <i class="bi bi-arrow-left me-1"></i> Back
                                </a>
                                <div class="position-relative d-inline-block mb-3">
                                    <?php if ($isOnline): ?>
                                    <div class="rounded-circle bg-white bg-opacity-25 d-flex align-items-center justify-content-center mx-auto" style="width: 80px; height: 80px;">
                                        <i class="bi bi-wifi text-white" style="font-size: 2.5rem;"></i>
                                    </div>
                                    <span class="position-absolute bottom-0 end-0 bg-success border border-3 border-white rounded-circle" style="width: 24px; height: 24px;"></span>
                                    <?php else: ?>
                                    <div class="rounded-circle bg-white bg-opacity-10 d-flex align-items-center justify-content-center mx-auto" style="width: 80px; height: 80px;">
                                        <i class="bi bi-wifi-off text-white opacity-50" style="font-size: 2.5rem;"></i>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <h4 class="fw-bold mb-1">
                                    <?= htmlspecialchars($subscriber['username']) ?>
                                    <button class="btn btn-link btn-sm p-0 text-white opacity-75" onclick="copyToClipboard('<?= htmlspecialchars($subscriber['username']) ?>')" title="Copy"><i class="bi bi-clipboard"></i></button>
                                </h4>
                                <?php if ($customer): ?>
                                <p class="mb-2 opacity-75"><?= htmlspecialchars($customer['name']) ?></p>
                                <?php endif; ?>
                                <div class="d-flex justify-content-center gap-2 flex-wrap">
                                    <?php if ($isOnline && $activeSession): ?>
                                    <span class="badge bg-white text-success live-timer" data-start="<?= strtotime($activeSession['session_start']) ?>" data-type="uptime"><i class="bi bi-circle-fill me-1" style="font-size: 8px;"></i>Online (<span class="timer-value"><?= $uptimeStr ?></span>)</span>
                                    <?php elseif (!$isOnline): ?>
                                    <?php 
                                    $lastSessionForTimer = null;
                                    foreach ($allSessions as $s) {
                                        if (!empty($s['session_end'])) {
                                            $lastSessionForTimer = $s;
                                            break;
                                        }
                                    }
                                    ?>
                                    <span class="badge bg-white bg-opacity-25 text-white live-timer" data-start="<?= $lastSessionForTimer ? strtotime($lastSessionForTimer['session_end']) : '' ?>" data-type="offline"><i class="bi bi-circle me-1" style="font-size: 8px;"></i>Offline (<span class="timer-value"><?= $offlineStr ?></span>)</span>
                                    <?php else: ?>
                                    <span class="badge bg-white text-success"><i class="bi bi-circle-fill me-1" style="font-size: 8px;"></i>Online</span>
                                    <?php endif; ?>
                                    <span class="badge bg-<?= $statusClass === 'success' ? 'white text-success' : ($statusClass === 'danger' ? 'danger' : 'warning text-dark') ?>"><?= $statusLabel ?></span>
                                    <span class="badge bg-white bg-opacity-25"><?= strtoupper($subscriber['access_type']) ?></span>
                                </div>
                                <?php if ($subscriber['mac_address']): ?>
                                <div class="mt-2"><span class="badge bg-white bg-opacity-10"><i class="bi bi-lock-fill me-1"></i>MAC Bound</span></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <!-- Right: Quick Stats -->
                        <div class="col-lg-8">
                            <div class="p-4">
                                <div class="row g-3 mb-3">
                                    <div class="col-6 col-md-3">
                                        <div class="text-center p-3 rounded-3 bg-primary bg-opacity-10">
                                            <div class="fs-4 fw-bold text-primary"><?= $package['name'] ?? 'N/A' ?></div>
                                            <small class="text-muted">Package</small>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="text-center p-3 rounded-3 bg-success bg-opacity-10">
                                            <div class="fs-4 fw-bold text-success"><?= number_format($totalDownload, 1) ?> GB</div>
                                            <small class="text-muted">Downloaded</small>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="text-center p-3 rounded-3 bg-info bg-opacity-10">
                                            <div class="fs-4 fw-bold text-info"><?= $totalSessions ?></div>
                                            <small class="text-muted">Sessions</small>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="text-center p-3 rounded-3 <?= $isSubExpired ? 'bg-danger bg-opacity-10' : 'bg-warning bg-opacity-10' ?>">
                                            <?php 
                                            $daysRemaining = $subscriber['expiry_date'] ? ceil((strtotime($subscriber['expiry_date']) - time()) / 86400) : null;
                                            ?>
                                            <div class="fs-4 fw-bold <?= $isSubExpired ? 'text-danger' : 'text-warning' ?>">
                                                <?= $daysRemaining !== null ? ($daysRemaining < 0 ? 'Expired' : $daysRemaining . 'd') : '∞' ?>
                                            </div>
                                            <small class="text-muted"><?= $isSubExpired ? 'Days Ago' : 'Days Left' ?></small>
                                        </div>
                                    </div>
                                </div>
                                <!-- Wallet Card at Top -->
                                <div class="d-flex align-items-center gap-3 mb-3 p-3 rounded-3 bg-gradient" style="background: linear-gradient(90deg, rgba(25,135,84,0.15) 0%, rgba(13,110,253,0.15) 100%); border: 1px solid rgba(25,135,84,0.2);">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                            <i class="bi bi-wallet2 fs-5"></i>
                                        </div>
                                        <div>
                                            <div class="text-muted small">Wallet Balance</div>
                                            <div class="fw-bold fs-5 text-success">KES <?= number_format($subscriber['credit_balance'] ?? 0) ?></div>
                                        </div>
                                    </div>
                                    <div class="vr mx-2"></div>
                                    <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addCreditModal">
                                        <i class="bi bi-plus-lg me-1"></i> Top Up
                                    </button>
                                    <?php if ($customer && !empty($customer['phone'])): ?>
                                    <button type="button" class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#stkPushModal">
                                        <i class="bi bi-phone me-1"></i> M-Pesa STK
                                    </button>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Action Buttons -->
                                <div class="d-flex flex-wrap gap-2">
                <div class="btn-group">
                    <?php if ($subscriber['status'] === 'active'): ?>
                    <form method="post" class="d-inline">
                        <input type="hidden" name="action" value="suspend_subscription">
                        <input type="hidden" name="id" value="<?= $subId ?>">
                        <input type="hidden" name="return_to" value="subscriber">
                        <button type="submit" class="btn btn-warning"><i class="bi bi-pause-fill me-1"></i> Suspend</button>
                    </form>
                    <?php elseif ($subscriber['status'] === 'suspended'): ?>
                    <form method="post" class="d-inline">
                        <input type="hidden" name="action" value="unsuspend_subscription">
                        <input type="hidden" name="id" value="<?= $subId ?>">
                        <input type="hidden" name="return_to" value="subscriber">
                        <button type="submit" class="btn btn-success"><i class="bi bi-play-fill me-1"></i> Unsuspend</button>
                    </form>
                    <?php else: ?>
                    <form method="post" class="d-inline">
                        <input type="hidden" name="action" value="activate_subscription">
                        <input type="hidden" name="id" value="<?= $subId ?>">
                        <input type="hidden" name="return_to" value="subscriber">
                        <button type="submit" class="btn btn-success"><i class="bi bi-play-fill me-1"></i> Activate</button>
                    </form>
                    <?php endif; ?>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#renewModal">
                        <i class="bi bi-arrow-clockwise me-1"></i> Renew
                    </button>
                    <button type="button" class="btn btn-info" onclick="pingSubscriber(<?= $subId ?>, '<?= htmlspecialchars($subscriber['username']) ?>')">
                        <i class="bi bi-lightning me-1"></i> Ping
                    </button>
                    <button type="button" class="btn btn-danger" onclick="resetSubscriberMAC(<?= $subId ?>, '<?= htmlspecialchars($subscriber['username']) ?>')">
                        <i class="bi bi-phone me-1"></i> Reset MAC
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#wifiConfigModal">
                        <i class="bi bi-wifi me-1"></i> WiFi Config
                    </button>
                    <div class="btn-group">
                        <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="bi bi-three-dots-vertical"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <?php if ($customer): ?>
                            <li><a class="dropdown-item" href="?page=tickets&action=create&customer_id=<?= $customer['id'] ?>"><i class="bi bi-ticket-perforated me-2"></i>Create Ticket</a></li>
                            <li><a class="dropdown-item" href="?page=customers&action=view&id=<?= $customer['id'] ?>"><i class="bi bi-person me-2"></i>View Customer</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <?php endif; ?>
                            <?php if (!empty($customer['phone'])): ?>
                            <li><a class="dropdown-item" href="#" onclick="sendQuickSMS('<?= htmlspecialchars($customer['phone']) ?>', '<?= htmlspecialchars($customer['name']) ?>')"><i class="bi bi-chat-dots me-2"></i>Send SMS</a></li>
                            <li><a class="dropdown-item" href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $customer['phone']) ?>" target="_blank"><i class="bi bi-whatsapp me-2"></i>WhatsApp</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <?php endif; ?>
                            <li><a class="dropdown-item text-danger" href="#" onclick="if(confirm('Reset data usage to 0?')) document.getElementById('resetDataForm').submit()"><i class="bi bi-arrow-counterclockwise me-2"></i>Reset Data Usage</a></li>
                        </ul>
                    </div>
                    <form id="resetDataForm" method="post" style="display:none;">
                        <input type="hidden" name="action" value="reset_data_usage">
                        <input type="hidden" name="id" value="<?= $subId ?>">
                        <input type="hidden" name="return_to" value="subscriber">
                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Full Width Tabs Layout -->
            <div class="card border-0 shadow-lg">
                <div class="card-header bg-white border-bottom p-0">
                    <ul class="nav nav-pills nav-fill" id="subscriberTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active rounded-0 py-3 border-end" id="sessions-tab" data-bs-toggle="tab" data-bs-target="#sessionsTab" type="button">
                                <i class="bi bi-broadcast me-2"></i>Sessions
                                <?php if ($isOnline): ?><span class="badge bg-success ms-1">1</span><?php endif; ?>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link rounded-0 py-3 border-end" id="customer-tab" data-bs-toggle="tab" data-bs-target="#customerTab" type="button">
                                <i class="bi bi-person-circle me-2"></i>Customer
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link rounded-0 py-3 border-end" id="subscription-tab" data-bs-toggle="tab" data-bs-target="#subscriptionTab" type="button">
                                <i class="bi bi-router me-2"></i>Subscription
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link rounded-0 py-3 border-end" id="billing-tab" data-bs-toggle="tab" data-bs-target="#billingTab" type="button">
                                <i class="bi bi-receipt me-2"></i>Billing
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link rounded-0 py-3 border-end" id="invoices-tab" data-bs-toggle="tab" data-bs-target="#invoicesTab" type="button">
                                <i class="bi bi-file-text me-2"></i>Invoices
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link rounded-0 py-3 border-end" id="tickets-tab" data-bs-toggle="tab" data-bs-target="#ticketsTab" type="button">
                                <i class="bi bi-ticket me-2"></i>Tickets
                                <?php $openTickets = count(array_filter($tickets, fn($t) => in_array($t['status'], ['open', 'in_progress']))); ?>
                                <?php if ($openTickets): ?><span class="badge bg-danger ms-1"><?= $openTickets ?></span><?php endif; ?>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link rounded-0 py-3 border-end" id="notes-tab" data-bs-toggle="tab" data-bs-target="#notesTab" type="button">
                                <i class="bi bi-sticky me-2"></i>Notes
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link rounded-0 py-3 border-end" id="speed-tab" data-bs-toggle="tab" data-bs-target="#speedOverridesTab" type="button">
                                <i class="bi bi-speedometer2 me-2"></i>Speed
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link rounded-0 py-3" id="traffic-tab" data-bs-toggle="tab" data-bs-target="#liveTrafficTab" type="button">
                                <i class="bi bi-graph-up me-2"></i>Live Traffic
                            </button>
                        </li>
                    </ul>
                </div>
                
                <div class="card-body p-4">
                    <div class="tab-content">
                        <!-- Customer Tab -->
                        <div class="tab-pane fade" id="customerTab" role="tabpanel">
                            <?php if ($customer): ?>
                            <div class="row g-4">
                                <div class="col-lg-6">
                                    <table class="table table-borderless mb-0">
                                        <tbody>
                                            <tr>
                                                <td class="text-muted" style="width: 140px;">Name</td>
                                                <td class="fw-medium"><?= htmlspecialchars($customer['name']) ?></td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">Phone</td>
                                                <td>
                                                    <span class="fw-medium"><?= htmlspecialchars($customer['phone']) ?></span>
                                                    <a href="tel:<?= htmlspecialchars($customer['phone']) ?>" class="btn btn-sm btn-link p-0 ms-2" title="Call"><i class="bi bi-telephone"></i></a>
                                                    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $customer['phone']) ?>" target="_blank" class="btn btn-sm btn-link text-success p-0 ms-1" title="WhatsApp"><i class="bi bi-whatsapp"></i></a>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">Email</td>
                                                <td class="fw-medium"><?= htmlspecialchars($customer['email'] ?? '-') ?></td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">Address</td>
                                                <td class="fw-medium"><?= htmlspecialchars($customer['address'] ?? '-') ?></td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">Wallet Balance</td>
                                                <td><span class="fw-bold text-success">KES <?= number_format($subscriber['credit_balance'] ?? 0) ?></span></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="col-lg-6">
                                    <div class="border-start ps-4 h-100">
                                        <h6 class="text-muted mb-3">Quick Actions</h6>
                                        <div class="d-flex flex-wrap gap-2">
                                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="copyCredentials(<?= $subId ?>, '<?= htmlspecialchars($subscriber['username']) ?>', '<?= htmlspecialchars($subscriber['password'] ?? '') ?>')">
                                                <i class="bi bi-key me-1"></i> Copy Credentials
                                            </button>
                                            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $customer['phone']) ?>?text=<?= urlencode('Hello ' . ($customer['name'] ?? '') . ', your WiFi credentials are:\nUsername: ' . $subscriber['username'] . '\nPassword: ' . ($subscriber['password'] ?? '')) ?>" target="_blank" class="btn btn-sm btn-outline-success">
                                                <i class="bi bi-whatsapp me-1"></i> Send Credentials
                                            </a>
                                            <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#addCreditModal">
                                                <i class="bi bi-plus-lg me-1"></i> Top Up
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#stkPushModal">
                                                <i class="bi bi-phone me-1"></i> M-Pesa
                                            </button>
                                            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editCustomerModal">
                                                <i class="bi bi-pencil me-1"></i> Edit Customer
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="text-center py-4 text-muted">
                                <i class="bi bi-person-x fs-1"></i>
                                <p class="mt-2 mb-0">No customer linked to this subscription</p>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Subscription Tab -->
                        <div class="tab-pane fade" id="subscriptionTab" role="tabpanel">
                            <div class="row g-4">
                                <!-- Left Column: Details -->
                                <div class="col-lg-6">
                                    <?php 
                                    $daysLeft = $subscriber['expiry_date'] ? (strtotime($subscriber['expiry_date']) - time()) / 86400 : null;
                                    $isExpired = $daysLeft !== null && $daysLeft < 0;
                                    ?>
                                    <table class="table table-borderless mb-0">
                                        <tbody>
                                            <tr>
                                                <td class="text-muted" style="width: 130px;">Username</td>
                                                <td>
                                                    <code class="bg-light px-2 py-1 rounded"><?= htmlspecialchars($subscriber['username']) ?></code>
                                                    <button class="btn btn-sm btn-link p-0 ms-1" onclick="copyToClipboard('<?= htmlspecialchars($subscriber['username']) ?>')" title="Copy"><i class="bi bi-clipboard"></i></button>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">Password</td>
                                                <td>
                                                    <code class="bg-light px-2 py-1 rounded" id="pwdDisplay">********</code>
                                                    <button class="btn btn-sm btn-link p-0 ms-1" id="pwdToggle" onclick="togglePassword()" title="Show"><i class="bi bi-eye"></i></button>
                                                    <button class="btn btn-sm btn-link p-0 ms-1" onclick="copyToClipboard('<?= htmlspecialchars($subscriber['password'] ?? '') ?>')" title="Copy"><i class="bi bi-clipboard"></i></button>
                                                    <script>
                                                        function togglePassword() {
                                                            const display = document.getElementById('pwdDisplay');
                                                            const toggle = document.getElementById('pwdToggle').querySelector('i');
                                                            if (display.textContent === '********') {
                                                                display.textContent = '<?= htmlspecialchars($subscriber['password'] ?? '') ?>';
                                                                toggle.className = 'bi bi-eye-slash';
                                                            } else {
                                                                display.textContent = '********';
                                                                toggle.className = 'bi bi-eye';
                                                            }
                                                        }
                                                    </script>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">Package</td>
                                                <td>
                                                    <strong><?= htmlspecialchars($package['name'] ?? 'N/A') ?></strong>
                                                    <span class="text-muted small ms-2">(<?= $package['download_speed'] ?? '-' ?> / <?= $package['upload_speed'] ?? '-' ?>)</span>
                                                    <button class="btn btn-sm btn-link p-0 ms-2" data-bs-toggle="modal" data-bs-target="#changePackageModal" title="Change Package"><i class="bi bi-arrow-left-right"></i></button>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">Price</td>
                                                <td><strong class="text-success">KES <?= number_format($package['price'] ?? 0) ?></strong> / <?= ucfirst($package['billing_cycle'] ?? 'month') ?></td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">Start Date</td>
                                                <td><?= $subscriber['start_date'] ? date('M j, Y', strtotime($subscriber['start_date'])) : '-' ?></td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">Expiry Date</td>
                                                <td>
                                                    <?php if ($isExpired): ?>
                                                    <span class="text-danger fw-bold"><?= date('M j, Y', strtotime($subscriber['expiry_date'])) ?></span>
                                                    <span class="badge bg-danger ms-1">Expired</span>
                                                    <?php elseif ($daysLeft !== null && $daysLeft < 7): ?>
                                                    <span class="text-warning fw-bold"><?= date('M j, Y', strtotime($subscriber['expiry_date'])) ?></span>
                                                    <span class="badge bg-warning text-dark ms-1"><?= ceil($daysLeft) ?> days</span>
                                                    <?php elseif ($subscriber['expiry_date']): ?>
                                                    <span class="fw-medium"><?= date('M j, Y', strtotime($subscriber['expiry_date'])) ?></span>
                                                    <span class="badge bg-success ms-1"><?= ceil($daysLeft) ?> days</span>
                                                    <?php else: ?>
                                                    <span class="text-muted">Never expires</span>
                                                    <?php endif; ?>
                                                    <button class="btn btn-sm btn-link p-0 ms-2" data-bs-toggle="modal" data-bs-target="#changeExpiryModal" title="Change Expiry"><i class="bi bi-calendar-event"></i></button>
                                                </td>
                                            </tr>
                                            <?php if ($subscriber['status'] === 'suspended' && !empty($subscriber['suspended_at'] ?? null)): ?>
                                            <tr class="table-warning">
                                                <td class="text-muted">Suspended On</td>
                                                <td>
                                                    <span class="text-warning fw-bold"><?= date('M j, Y', strtotime($subscriber['suspended_at'])) ?></span>
                                                    <?php 
                                                    $daysSuspended = ceil((time() - strtotime($subscriber['suspended_at'])) / 86400);
                                                    $savedDays = $subscriber['days_remaining_at_suspension'] ?? 0;
                                                    ?>
                                                    <span class="badge bg-warning text-dark ms-1"><?= $daysSuspended ?> day(s) ago</span>
                                                </td>
                                            </tr>
                                            <tr class="table-success">
                                                <td class="text-muted">Days Saved</td>
                                                <td>
                                                    <span class="text-success fw-bold"><?= $savedDays ?> days</span>
                                                    <small class="text-muted ms-2">(will be restored on unsuspend)</small>
                                                </td>
                                            </tr>
                                            <?php endif; ?>
                                            <tr>
                                                <td class="text-muted">Auto-Renew</td>
                                                <td>
                                                    <?php if ($subscriber['auto_renew']): ?>
                                                    <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>ON</span>
                                                    <?php else: ?>
                                                    <span class="badge bg-secondary"><i class="bi bi-x-circle me-1"></i>OFF</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">Access Type</td>
                                                <td><span class="badge bg-primary"><?= strtoupper($subscriber['access_type']) ?></span></td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">Static IP</td>
                                                <td><?= $subscriber['static_ip'] ?: '<span class="text-muted">Dynamic</span>' ?></td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">MAC Address</td>
                                                <td>
                                                    <?php if ($subscriber['mac_address']): ?>
                                                    <code><?= htmlspecialchars($subscriber['mac_address']) ?></code>
                                                    <span class="badge bg-success ms-1"><i class="bi bi-lock-fill"></i></span>
                                                    <form method="post" class="d-inline">
                                                        <input type="hidden" name="action" value="clear_mac">
                                                        <input type="hidden" name="id" value="<?= $subscriber['id'] ?>">
                                                        <input type="hidden" name="return_to" value="subscriber">
                                                        <button type="submit" class="btn btn-sm btn-link text-warning p-0 ms-1" onclick="return confirm('Clear MAC binding?')" title="Unbind"><i class="bi bi-unlock"></i></button>
                                                    </form>
                                                    <?php else: ?>
                                                    <span class="text-muted">Not bound</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Right Column: Data Usage & Actions -->
                                <div class="col-lg-6">
                                    <div class="border-start ps-4 h-100">
                                        <?php if ($package && $package['data_quota_mb']): ?>
                                        <h6 class="text-muted mb-3">Data Usage</h6>
                                        <?php 
                                        $usagePercent = min(100, ($subscriber['data_used_mb'] / $package['data_quota_mb']) * 100);
                                        ?>
                                        <div class="progress mb-2" style="height: 10px;">
                                            <div class="progress-bar bg-<?= $usagePercent >= 100 ? 'danger' : ($usagePercent >= 80 ? 'warning' : 'success') ?>" style="width: <?= $usagePercent ?>%;"></div>
                                        </div>
                                        <div class="d-flex justify-content-between small text-muted mb-4">
                                            <span><?= number_format($subscriber['data_used_mb'] / 1024, 2) ?> GB used</span>
                                            <span><?= number_format($package['data_quota_mb'] / 1024, 2) ?> GB total</span>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <h6 class="text-muted mb-3">Actions</h6>
                                        <div class="d-flex flex-wrap gap-2">
                                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editSubscriptionModal">
                                                <i class="bi bi-pencil me-1"></i> Edit
                                            </button>
                                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#changePackageModal">
                                                <i class="bi bi-arrow-left-right me-1"></i> Change Package
                                            </button>
                                            <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#changeExpiryModal">
                                                <i class="bi bi-calendar-event me-1"></i> Change Expiry
                                            </button>
                                            <form id="resetDataForm" method="post" class="d-inline">
                                                <input type="hidden" name="action" value="reset_data_usage">
                                                <input type="hidden" name="id" value="<?= $subId ?>">
                                                <input type="hidden" name="return_to" value="subscriber">
                                                <button type="submit" class="btn btn-sm btn-outline-secondary" onclick="return confirm('Reset data usage to 0?')">
                                                    <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Data
                                                </button>
                                            </form>
                                        </div>
                                        
                                        <!-- Static IP Provisioning -->
                                        <h6 class="text-muted mb-3 mt-4">Static IP Provisioning</h6>
                                        <?php 
                                        $provisionedIps = $radiusBilling->getProvisionedIps($subscriber['id']);
                                        $vlans = $radiusBilling->getVlans();
                                        ?>
                                        <?php if (!empty($provisionedIps)): ?>
                                        <div class="alert alert-info p-2 mb-2">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong>Provisioned IP:</strong> <code><?= htmlspecialchars($provisionedIps[0]['ip_address']) ?></code>
                                                    <br><small class="text-muted">VLAN: <?= htmlspecialchars($provisionedIps[0]['vlan_name'] ?? '-') ?> | MAC: <code><?= htmlspecialchars($provisionedIps[0]['mac_address']) ?></code></small>
                                                </div>
                                                <button class="btn btn-sm btn-outline-danger" onclick="deprovisionStaticIp(<?= $subscriber['id'] ?>)">
                                                    <i class="bi bi-trash"></i> Remove
                                                </button>
                                            </div>
                                        </div>
                                        <?php else: ?>
                                        <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#provisionIpModal">
                                            <i class="bi bi-plus-circle me-1"></i> Provision Static IP
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Provision Static IP Modal -->
                        <div class="modal fade" id="provisionIpModal" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title"><i class="bi bi-hdd-network me-2"></i>Provision Static IP</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <form id="provisionIpForm">
                                            <input type="hidden" name="subscription_id" value="<?= $subscriber['id'] ?>">
                                            <div class="mb-3">
                                                <label class="form-label">VLAN / Network *</label>
                                                <select class="form-select" name="vlan_id" id="provisionVlanSelect" required>
                                                    <option value="">Select VLAN...</option>
                                                    <?php foreach ($vlans as $vlan): ?>
                                                    <?php if ($vlan['is_active'] && $vlan['network_cidr']): ?>
                                                    <option value="<?= $vlan['id'] ?>" data-network="<?= htmlspecialchars($vlan['network_cidr']) ?>" data-pool-start="<?= htmlspecialchars($vlan['dhcp_pool_start'] ?? '') ?>" data-pool-end="<?= htmlspecialchars($vlan['dhcp_pool_end'] ?? '') ?>">
                                                        <?= htmlspecialchars($vlan['name']) ?> (<?= $vlan['network_cidr'] ?>) - <?= htmlspecialchars($vlan['nas_name'] ?? '') ?>
                                                    </option>
                                                    <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">IP Address *</label>
                                                <input type="text" class="form-control" name="ip_address" id="provisionIpAddress" required placeholder="e.g., 10.40.0.100">
                                                <div class="form-text" id="ipHint"></div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">MAC Address *</label>
                                                <input type="text" class="form-control" name="mac_address" value="<?= htmlspecialchars($subscriber['mac_address'] ?? '') ?>" required placeholder="AA:BB:CC:DD:EE:FF">
                                            </div>
                                        </form>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="button" class="btn btn-primary" onclick="provisionStaticIp()">
                                            <i class="bi bi-check-lg me-1"></i> Provision
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <script>
                        document.getElementById('provisionVlanSelect')?.addEventListener('change', function() {
                            const opt = this.options[this.selectedIndex];
                            const hint = document.getElementById('ipHint');
                            const start = opt.dataset.poolStart;
                            const end = opt.dataset.poolEnd;
                            if (start && end) {
                                hint.textContent = `Pool range: ${start} - ${end}`;
                            } else {
                                hint.textContent = '';
                            }
                        });
                        
                        function provisionStaticIp() {
                            const form = document.getElementById('provisionIpForm');
                            const formData = new FormData(form);
                            const data = Object.fromEntries(formData.entries());
                            
                            if (!data.vlan_id || !data.ip_address || !data.mac_address) {
                                alert('All fields are required');
                                return;
                            }
                            
                            fetch('/index.php?page=isp&action=provision_static_ip', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify(data)
                            })
                            .then(r => r.json())
                            .then(result => {
                                if (result.success) {
                                    alert(`Static IP provisioned!\nIP: ${result.ip}\nMAC: ${result.mac}`);
                                    window.location.reload();
                                } else {
                                    alert('Failed: ' + (result.error || 'Unknown error'));
                                }
                            })
                            .catch(err => alert('Error: ' + err.message));
                        }
                        
                        function deprovisionStaticIp(subscriptionId) {
                            if (!confirm('Remove static IP provisioning? This will delete the DHCP lease from MikroTik.')) return;
                            
                            fetch(`/index.php?page=isp&action=deprovision_static_ip&subscription_id=${subscriptionId}`)
                                .then(r => r.json())
                                .then(result => {
                                    if (result.success) {
                                        alert('Static IP removed.');
                                        window.location.reload();
                                    } else {
                                        alert('Failed: ' + (result.error || 'Unknown error'));
                                    }
                                });
                        }
                        </script>
                        
                        <!-- Sessions Tab -->
                        <div class="tab-pane fade show active" id="sessionsTab" role="tabpanel">
                            <?php if ($activeSession): ?>
                            <div class="alert alert-success mb-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><i class="bi bi-wifi me-2"></i>Active Session</strong>
                                        <br><small>IP: <code><?= htmlspecialchars($activeSession['framed_ip_address'] ?? '-') ?></code> | 
                                        Started: <?= date('M j, g:i A', strtotime($activeSession['session_start'])) ?></small>
                                    </div>
                                    <form method="post">
                                        <input type="hidden" name="action" value="disconnect_session">
                                        <input type="hidden" name="session_id" value="<?= $activeSession['id'] ?>">
                                        <input type="hidden" name="subscription_id" value="<?= $subId ?>">
                                        <input type="hidden" name="return_to" value="subscriber">
                                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Disconnect this session?')">
                                            <i class="bi bi-x-circle me-1"></i> Disconnect
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="card shadow-sm border-0">
                                <div class="card-header bg-transparent">
                                    <h6 class="mb-0">Session History</h6>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Started</th>
                                                    <th>Ended</th>
                                                    <th>Duration</th>
                                                    <th>IP Address</th>
                                                    <th>Download</th>
                                                    <th>Upload</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($sessionHistory as $sess): ?>
                                                <tr>
                                                    <td><?= date('M j, H:i', strtotime($sess['session_start'])) ?></td>
                                                    <td><?= $sess['session_end'] ? date('M j, H:i', strtotime($sess['session_end'])) : '<span class="badge bg-success">Active</span>' ?></td>
                                                    <td>
                                                        <?php 
                                                        $utc = new DateTimeZone('UTC');
                                                        $startDt = new DateTime($sess['session_start'], $utc);
                                                        $endDt = $sess['session_end'] ? new DateTime($sess['session_end'], $utc) : new DateTime('now', $utc);
                                                        $dur = $endDt->getTimestamp() - $startDt->getTimestamp();
                                                        if ($dur < 0) $dur = 0;
                                                        echo floor($dur/3600) . 'h ' . floor(($dur%3600)/60) . 'm';
                                                        ?>
                                                    </td>
                                                    <td><code><?= $sess['framed_ip_address'] ?? '-' ?></code></td>
                                                    <td><?= number_format(($sess['input_octets'] ?? 0) / 1048576, 2) ?> MB</td>
                                                    <td><?= number_format(($sess['output_octets'] ?? 0) / 1048576, 2) ?> MB</td>
                                                </tr>
                                                <?php endforeach; ?>
                                                <?php if (empty($sessionHistory)): ?>
                                                <tr><td colspan="6" class="text-center text-muted py-4">No session history</td></tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Addon Services -->
                            <?php 
                            $subscriberAddons = $db->prepare("
                                SELECT sa.*, ads.name, ads.price, ads.billing_type, ads.category, ads.description
                                FROM radius_subscription_addons sa
                                JOIN radius_addon_services ads ON sa.addon_id = ads.id
                                WHERE sa.subscription_id = ? AND sa.status = 'active'
                            ");
                            $subscriberAddons->execute([$subId]);
                            $activeAddons = $subscriberAddons->fetchAll(PDO::FETCH_ASSOC);
                            
                            $availableAddons = $db->query("SELECT * FROM radius_addon_services WHERE is_active = true ORDER BY category, name")->fetchAll(PDO::FETCH_ASSOC);
                            $assignedAddonIds = array_column($activeAddons, 'addon_id');
                            
                            $addonsTotal = array_reduce($activeAddons, function($sum, $a) {
                                return $sum + ($a['billing_type'] === 'monthly' ? $a['price'] * $a['quantity'] : 0);
                            }, 0);
                            $packagePrice = $package['price'] ?? 0;
                            $totalMonthly = $packagePrice + $addonsTotal;
                            ?>
                            <div class="card shadow-sm border-0 mt-3">
                                <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Addon Services</h6>
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#assignAddonModal">
                                        <i class="bi bi-plus me-1"></i>Add Service
                                    </button>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($activeAddons)): ?>
                                    <p class="text-muted mb-0 text-center py-2">No addon services assigned</p>
                                    <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Service</th>
                                                    <th>Category</th>
                                                    <th>Qty</th>
                                                    <th>Price</th>
                                                    <th>Billing</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($activeAddons as $addon): 
                                                $config = json_decode($addon['config_data'] ?? '{}', true) ?: [];
                                                ?>
                                                <tr>
                                                    <td>
                                                        <strong><?= htmlspecialchars($addon['name']) ?></strong>
                                                        <?php if ($addon['description']): ?>
                                                        <br><small class="text-muted"><?= htmlspecialchars($addon['description']) ?></small>
                                                        <?php endif; ?>
                                                        <?php if (!empty($config)): ?>
                                                        <div class="mt-1">
                                                            <?php if (($config['type'] ?? '') === 'pppoe'): ?>
                                                            <small class="text-info"><i class="bi bi-person-fill me-1"></i><?= htmlspecialchars($config['username'] ?? '') ?></small>
                                                            <?php elseif (($config['type'] ?? '') === 'static'): ?>
                                                            <small class="text-success"><i class="bi bi-hdd-network me-1"></i><?= htmlspecialchars($config['ip'] ?? '') ?></small>
                                                            <?php elseif (($config['type'] ?? '') === 'dhcp'): ?>
                                                            <small class="text-warning"><i class="bi bi-ethernet me-1"></i><?= htmlspecialchars($config['mac'] ?? '') ?></small>
                                                            <?php if (!empty($config['description'])): ?>
                                                            <small class="text-muted ms-2">(<?= htmlspecialchars($config['description']) ?>)</small>
                                                            <?php endif; ?>
                                                            <?php endif; ?>
                                                        </div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($addon['category'] ?? 'Other') ?></span></td>
                                                    <td><?= $addon['quantity'] ?></td>
                                                    <td>KES <?= number_format($addon['price'] * $addon['quantity'], 2) ?></td>
                                                    <td>
                                                        <?php if ($addon['billing_type'] === 'monthly'): ?>
                                                        <span class="badge bg-info">Monthly</span>
                                                        <?php elseif ($addon['billing_type'] === 'one_time'): ?>
                                                        <span class="badge bg-warning">One-time</span>
                                                        <?php else: ?>
                                                        <span class="badge bg-primary">Per Use</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <form method="post" class="d-inline" onsubmit="return confirm('Remove this addon?')">
                                                            <input type="hidden" name="action" value="remove_addon">
                                                            <input type="hidden" name="subscription_id" value="<?= $subId ?>">
                                                            <input type="hidden" name="addon_id" value="<?= $addon['addon_id'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-x"></i></button>
                                                        </form>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="border-top mt-3 pt-3">
                                        <div class="row text-center">
                                            <div class="col-4">
                                                <small class="text-muted">Package</small>
                                                <div class="fw-bold">KES <?= number_format($packagePrice) ?></div>
                                            </div>
                                            <div class="col-4">
                                                <small class="text-muted">Addons</small>
                                                <div class="fw-bold text-info">+ KES <?= number_format($addonsTotal) ?></div>
                                            </div>
                                            <div class="col-4">
                                                <small class="text-muted">Total Monthly</small>
                                                <div class="fw-bold text-primary fs-5">KES <?= number_format($totalMonthly) ?></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Assign Addon Modal -->
                            <div class="modal fade" id="assignAddonModal" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form method="post" id="assignAddonForm">
                                            <input type="hidden" name="action" value="assign_addon">
                                            <input type="hidden" name="subscription_id" value="<?= $subId ?>">
                                            <div class="modal-header">
                                                <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Assign Addon Service</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <?php 
                                                $unassignedAddons = array_filter($availableAddons, fn($a) => !in_array($a['id'], $assignedAddonIds));
                                                if (empty($unassignedAddons)): ?>
                                                <div class="alert alert-info mb-0">
                                                    All available addon services are already assigned to this subscriber.
                                                    <a href="?page=isp&view=addons">Manage addon services</a>
                                                </div>
                                                <?php else: ?>
                                                <div class="mb-3">
                                                    <label class="form-label">Select Addon Service</label>
                                                    <select name="addon_id" id="addonSelect" class="form-select" required onchange="showAddonFields()">
                                                        <option value="" data-category="">-- Choose addon --</option>
                                                        <?php foreach ($unassignedAddons as $addon): ?>
                                                        <option value="<?= $addon['id'] ?>" data-category="<?= htmlspecialchars($addon['category']) ?>">
                                                            <?= htmlspecialchars($addon['name']) ?> 
                                                            (<?= $addon['category'] ?>) - 
                                                            KES <?= number_format($addon['price'], 2) ?>/<?= $addon['billing_type'] ?>
                                                        </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                
                                                <!-- PPPoE Fields -->
                                                <div id="pppoeFields" class="addon-config-fields" style="display:none;">
                                                    <div class="alert alert-info py-2 small mb-3">
                                                        <i class="bi bi-info-circle me-1"></i> PPPoE requires username and password for authentication
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">PPPoE Username <span class="text-danger">*</span></label>
                                                        <input type="text" name="pppoe_username" class="form-control" placeholder="e.g., addon_user123">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">PPPoE Password <span class="text-danger">*</span></label>
                                                        <input type="text" name="pppoe_password" class="form-control" placeholder="Enter password">
                                                    </div>
                                                </div>
                                                
                                                <!-- Static IP Fields -->
                                                <div id="staticFields" class="addon-config-fields" style="display:none;">
                                                    <div class="alert alert-info py-2 small mb-3">
                                                        <i class="bi bi-info-circle me-1"></i> Static IP requires an IP address assignment
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Static IP Address <span class="text-danger">*</span></label>
                                                        <input type="text" name="static_ip" class="form-control" placeholder="e.g., 192.168.1.100" pattern="^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Subnet Mask</label>
                                                        <input type="text" name="static_netmask" class="form-control" value="255.255.255.0" placeholder="e.g., 255.255.255.0">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Gateway</label>
                                                        <input type="text" name="static_gateway" class="form-control" placeholder="e.g., 192.168.1.1">
                                                    </div>
                                                </div>
                                                
                                                <!-- DHCP Fields -->
                                                <div id="dhcpFields" class="addon-config-fields" style="display:none;">
                                                    <div class="alert alert-info py-2 small mb-3">
                                                        <i class="bi bi-info-circle me-1"></i> DHCP requires MAC address for device binding
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">MAC Address <span class="text-danger">*</span></label>
                                                        <input type="text" name="dhcp_mac" class="form-control" placeholder="e.g., AA:BB:CC:DD:EE:FF" style="text-transform:uppercase;">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Reserved IP (Optional)</label>
                                                        <input type="text" name="dhcp_reserved_ip" class="form-control" placeholder="Leave blank for dynamic">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Device Description</label>
                                                        <input type="text" name="dhcp_description" class="form-control" placeholder="e.g., Living room TV">
                                                    </div>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">Quantity</label>
                                                    <input type="number" name="quantity" class="form-control" value="1" min="1" max="100">
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <?php if (!empty($unassignedAddons)): ?>
                                                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Assign Addon</button>
                                                <?php endif; ?>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <script>
                            function showAddonFields() {
                                const select = document.getElementById('addonSelect');
                                const category = select.options[select.selectedIndex].dataset.category || '';
                                
                                document.querySelectorAll('.addon-config-fields').forEach(el => {
                                    el.style.display = 'none';
                                    el.querySelectorAll('input').forEach(inp => inp.required = false);
                                });
                                
                                if (category.includes('PPPoE')) {
                                    document.getElementById('pppoeFields').style.display = 'block';
                                    document.querySelector('[name="pppoe_username"]').required = true;
                                    document.querySelector('[name="pppoe_password"]').required = true;
                                } else if (category.includes('Static IP')) {
                                    document.getElementById('staticFields').style.display = 'block';
                                    document.querySelector('[name="static_ip"]').required = true;
                                } else if (category.includes('DHCP')) {
                                    document.getElementById('dhcpFields').style.display = 'block';
                                    document.querySelector('[name="dhcp_mac"]').required = true;
                                }
                            }
                            </script>
                            
                            <!-- Authentication Logs -->
                            <div class="card shadow-sm border-0 mt-3">
                                <div class="card-header bg-transparent">
                                    <h6 class="mb-0"><i class="bi bi-shield-lock me-2"></i>Authentication History</h6>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover table-sm mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Time</th>
                                                    <th>Result</th>
                                                    <th>Status/Reason</th>
                                                    <th>MAC Address</th>
                                                    <th>NAS IP</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($authLogs ?? [] as $auth): ?>
                                                <tr>
                                                    <td><?= date('M j, H:i:s', strtotime($auth['created_at'])) ?></td>
                                                    <td>
                                                        <?php if ($auth['auth_result'] === 'Accept'): ?>
                                                            <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Accept</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Reject</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                        $reason = $auth['reject_reason'] ?? '';
                                                        $reasonClass = match(true) {
                                                            str_contains($reason, 'password') => 'text-danger',
                                                            str_contains($reason, 'MAC') => 'text-warning',
                                                            str_contains($reason, 'expired') || str_contains($reason, 'Expired') => 'text-info',
                                                            str_contains($reason, 'suspended') || str_contains($reason, 'Suspended') => 'text-secondary',
                                                            str_contains($reason, 'quota') || str_contains($reason, 'Quota') => 'text-primary',
                                                            str_contains($reason, 'not found') => 'text-danger',
                                                            default => 'text-muted'
                                                        };
                                                        ?>
                                                        <small class="<?= $reasonClass ?>"><?= htmlspecialchars($reason ?: 'Active user') ?></small>
                                                    </td>
                                                    <td><code class="small"><?= htmlspecialchars($auth['mac_address'] ?? '-') ?></code></td>
                                                    <td><small><?= htmlspecialchars($auth['nas_ip_address'] ?? '-') ?></small></td>
                                                </tr>
                                                <?php endforeach; ?>
                                                <?php if (empty($authLogs)): ?>
                                                <tr><td colspan="5" class="text-center text-muted py-4">No authentication logs</td></tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Billing Tab -->
                        <div class="tab-pane fade" id="billingTab">
                            <div class="card shadow-sm">
                                <div class="card-header">
                                    <h6 class="mb-0">Billing History</h6>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Type</th>
                                                    <th>Amount</th>
                                                    <th>Period</th>
                                                    <th>Status</th>
                                                    <th>Reference</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($billingHistory as $bill): ?>
                                                <tr>
                                                    <td><?= date('M j, Y', strtotime($bill['created_at'])) ?></td>
                                                    <td><span class="badge bg-secondary"><?= ucfirst($bill['billing_type'] ?? '-') ?></span></td>
                                                    <td>KES <?= number_format($bill['amount'] ?? 0) ?></td>
                                                    <td><?= $bill['period_start'] ? date('M j', strtotime($bill['period_start'])) . ' - ' . date('M j', strtotime($bill['period_end'])) : '-' ?></td>
                                                    <td>
                                                        <span class="badge bg-<?= ($bill['status'] ?? '') === 'paid' ? 'success' : 'warning' ?>">
                                                            <?= ucfirst($bill['status'] ?? 'pending') ?>
                                                        </span>
                                                    </td>
                                                    <td><small><?= htmlspecialchars($bill['mpesa_reference'] ?? '-') ?></small></td>
                                                </tr>
                                                <?php endforeach; ?>
                                                <?php if (empty($billingHistory)): ?>
                                                <tr><td colspan="6" class="text-center text-muted py-4">No billing history</td></tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- M-Pesa Payment Records -->
                            <div class="card shadow-sm mt-3">
                                <div class="card-header">
                                    <h6 class="mb-0"><i class="bi bi-phone text-success me-2"></i>M-Pesa Payment Records</h6>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Type</th>
                                                    <th>Amount</th>
                                                    <th>Phone</th>
                                                    <th>Receipt</th>
                                                    <th>Account Ref</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($mpesaPayments as $payment): ?>
                                                <tr>
                                                    <td><?= $payment['created_at'] ? date('M j, Y H:i', strtotime($payment['created_at'])) : '-' ?></td>
                                                    <td>
                                                        <span class="badge bg-<?= ($payment['source'] ?? '') === 'c2b' ? 'info' : 'success' ?>">
                                                            <?= strtoupper($payment['source'] ?? 'STK') ?>
                                                        </span>
                                                    </td>
                                                    <td><strong>KES <?= number_format($payment['amount'] ?? 0) ?></strong></td>
                                                    <td><small><?= htmlspecialchars($payment['phone_number'] ?? '-') ?></small></td>
                                                    <td><small class="text-success"><?= htmlspecialchars($payment['mpesa_receipt_number'] ?? '-') ?></small></td>
                                                    <td><small><?= htmlspecialchars($payment['account_reference'] ?? '-') ?></small></td>
                                                </tr>
                                                <?php endforeach; ?>
                                                <?php if (empty($mpesaPayments)): ?>
                                                <tr><td colspan="6" class="text-center text-muted py-4">No M-Pesa payments found</td></tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Invoices Tab -->
                        <div class="tab-pane fade" id="invoicesTab">
                            <div class="card shadow-sm">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0">Invoices</h6>
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="action" value="generate_invoice">
                                        <input type="hidden" name="subscription_id" value="<?= $subId ?>">
                                        <input type="hidden" name="return_to" value="subscriber">
                                        <button type="submit" class="btn btn-sm btn-primary">
                                            <i class="bi bi-plus me-1"></i> Generate Invoice
                                        </button>
                                    </form>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Invoice #</th>
                                                    <th>Date</th>
                                                    <th>Amount</th>
                                                    <th>Status</th>
                                                    <th>Paid On</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($invoices as $inv): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($inv['invoice_number'] ?? '-') ?></td>
                                                    <td><?= date('M j, Y', strtotime($inv['created_at'])) ?></td>
                                                    <td>KES <?= number_format($inv['total_amount'] ?? 0) ?></td>
                                                    <td>
                                                        <span class="badge bg-<?= ($inv['status'] ?? '') === 'paid' ? 'success' : 'warning' ?>">
                                                            <?= ucfirst($inv['status'] ?? 'pending') ?>
                                                        </span>
                                                    </td>
                                                    <td><?= !empty($inv['paid_at']) ? date('M j, Y', strtotime($inv['paid_at'])) : '-' ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                                <?php if (empty($invoices)): ?>
                                                <tr><td colspan="5" class="text-center text-muted py-4">No invoices</td></tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Tickets Tab -->
                        <div class="tab-pane fade" id="ticketsTab">
                            <div class="card shadow-sm">
                                <div class="card-header">
                                    <h6 class="mb-0">Support Tickets</h6>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Ticket #</th>
                                                    <th>Subject</th>
                                                    <th>Category</th>
                                                    <th>Status</th>
                                                    <th>Created</th>
                                                    <th>Replies</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($tickets as $t): ?>
                                                <tr>
                                                    <td><a href="?page=tickets&action=view&id=<?= $t['id'] ?>"><?= htmlspecialchars($t['ticket_number']) ?></a></td>
                                                    <td><?= htmlspecialchars($t['subject']) ?></td>
                                                    <td><span class="badge bg-light text-dark"><?= ucfirst($t['category'] ?? '-') ?></span></td>
                                                    <td>
                                                        <?php
                                                        $tStatusClass = match($t['status']) {
                                                            'open' => 'primary',
                                                            'in_progress' => 'info',
                                                            'resolved' => 'success',
                                                            'closed' => 'secondary',
                                                            default => 'warning'
                                                        };
                                                        ?>
                                                        <span class="badge bg-<?= $tStatusClass ?>"><?= ucfirst(str_replace('_', ' ', $t['status'])) ?></span>
                                                    </td>
                                                    <td><?= date('M j', strtotime($t['created_at'])) ?></td>
                                                    <td><?= $t['comment_count'] ?? 0 ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                                <?php if (empty($tickets)): ?>
                                                <tr><td colspan="6" class="text-center text-muted py-4">No tickets</td></tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Notes Tab -->
                        <div class="tab-pane fade" id="notesTab">
                            <div class="card shadow-sm">
                                <div class="card-header">
                                    <h6 class="mb-0">Notes</h6>
                                </div>
                                <div class="card-body">
                                    <form method="post">
                                        <input type="hidden" name="action" value="update_subscription_notes">
                                        <input type="hidden" name="id" value="<?= $subId ?>">
                                        <input type="hidden" name="return_to" value="subscriber">
                                        <textarea name="notes" class="form-control mb-3" rows="5"><?= htmlspecialchars($subscriber['notes'] ?? '') ?></textarea>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-save me-1"></i> Save Notes
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Speed Overrides Tab -->
                        <div class="tab-pane fade" id="speedOverridesTab">
                            <?php
                            $speedOverrides = [];
                            try {
                                $soStmt = $db->prepare("SELECT * FROM radius_speed_overrides WHERE package_id = ? ORDER BY priority DESC, start_time");
                                $soStmt->execute([$subscriber['package_id']]);
                                $speedOverrides = $soStmt->fetchAll(PDO::FETCH_ASSOC);
                            } catch (Exception $e) {}
                            ?>
                            <div class="card shadow-sm mb-3">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>Speed Schedules for <?= htmlspecialchars($package['name'] ?? 'Package') ?></h6>
                                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addSpeedOverrideModal">
                                        <i class="bi bi-plus-circle me-1"></i> Add Schedule
                                    </button>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($speedOverrides)): ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="bi bi-speedometer2 fs-1 d-block mb-2"></i>
                                        No speed schedules configured for this package.<br>
                                        <small>Speed schedules allow different speeds at different times (e.g., Night Boost)</small>
                                    </div>
                                    <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Time Range</th>
                                                    <th>Days</th>
                                                    <th>Download</th>
                                                    <th>Upload</th>
                                                    <th>Priority</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($speedOverrides as $so): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($so['name']) ?></td>
                                                    <td><?= date('H:i', strtotime($so['start_time'])) ?> - <?= date('H:i', strtotime($so['end_time'])) ?></td>
                                                    <td>
                                                        <?php 
                                                        $days = $so['days_of_week'] ? explode(',', $so['days_of_week']) : ['All'];
                                                        echo implode(', ', array_map(fn($d) => ucfirst(substr(trim($d), 0, 3)), $days));
                                                        ?>
                                                    </td>
                                                    <td><span class="badge bg-success"><?= htmlspecialchars($so['download_speed']) ?></span></td>
                                                    <td><span class="badge bg-info"><?= htmlspecialchars($so['upload_speed']) ?></span></td>
                                                    <td><?= $so['priority'] ?></td>
                                                    <td>
                                                        <?php if ($so['is_active']): ?>
                                                        <span class="badge bg-success">Active</span>
                                                        <?php else: ?>
                                                        <span class="badge bg-secondary">Inactive</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <form method="post" class="d-inline">
                                                            <input type="hidden" name="action" value="delete_speed_override">
                                                            <input type="hidden" name="override_id" value="<?= $so['id'] ?>">
                                                            <input type="hidden" name="subscription_id" value="<?= $subId ?>">
                                                            <input type="hidden" name="return_to" value="subscriber">
                                                            <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('Delete this schedule?')">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="card shadow-sm">
                                <div class="card-header">
                                    <h6 class="mb-0"><i class="bi bi-lightning me-2"></i>Quick Actions</h6>
                                </div>
                                <div class="card-body">
                                    <div class="d-flex gap-2 flex-wrap mb-3">
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="action" value="send_speed_coa">
                                            <input type="hidden" name="subscription_id" value="<?= $subId ?>">
                                            <input type="hidden" name="return_to" value="subscriber">
                                            <button type="submit" class="btn btn-warning">
                                                <i class="bi bi-arrow-repeat me-1"></i> Apply Package Speed
                                            </button>
                                        </form>
                                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#overrideSpeedModal">
                                            <i class="bi bi-speedometer2 me-1"></i> Override Speed
                                        </button>
                                    </div>
                                    <small class="text-muted">
                                        <strong>Apply Package Speed:</strong> Sends CoA with current package speeds.<br>
                                        <strong>Override Speed:</strong> Temporarily set custom speeds (resets on reconnect).
                                    </small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Live Traffic Tab -->
                        <div class="tab-pane fade" id="liveTrafficTab">
                            <div class="card shadow-sm">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0"><i class="bi bi-activity me-2"></i>Live Traffic Monitor</h6>
                                    <div class="d-flex align-items-center gap-2">
                                        <span id="trafficStatus" class="badge bg-secondary">Stopped</span>
                                        <button type="button" class="btn btn-success btn-sm" id="startTrafficBtn" onclick="startTrafficMonitor()">
                                            <i class="bi bi-play-fill me-1"></i> Start
                                        </button>
                                        <button type="button" class="btn btn-danger btn-sm d-none" id="stopTrafficBtn" onclick="stopTrafficMonitor()">
                                            <i class="bi bi-stop-fill me-1"></i> Stop
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-md-3">
                                            <div class="border rounded p-3 text-center">
                                                <div class="text-muted small">Status</div>
                                                <div id="sessionStatus" class="fw-bold text-secondary">--</div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="border rounded p-3 text-center">
                                                <div class="text-muted small">Download</div>
                                                <div id="currentDownload" class="fw-bold text-success fs-5">0 Kbps</div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="border rounded p-3 text-center">
                                                <div class="text-muted small">Upload</div>
                                                <div id="currentUpload" class="fw-bold text-primary fs-5">0 Kbps</div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="border rounded p-3 text-center">
                                                <div class="text-muted small">Total Data</div>
                                                <div id="totalData" class="fw-bold text-dark">0 MB</div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="position-relative" style="height: 300px;">
                                        <canvas id="trafficChart"></canvas>
                                    </div>
                                    
                                    <div class="mt-3 text-muted small text-center">
                                        <i class="bi bi-info-circle me-1"></i>
                                        Traffic data is polled every 2 seconds from MikroTik router. Click "Start" to begin monitoring.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
            <script>
            let trafficChart = null;
            let trafficInterval = null;
            let previousRx = 0;
            let previousTx = 0;
            let previousTime = 0;
            const maxDataPoints = 60;
            const subscriptionId = <?= $subId ?>;
            
            function initTrafficChart() {
                const ctx = document.getElementById('trafficChart').getContext('2d');
                trafficChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: [],
                        datasets: [
                            {
                                label: 'Download (Kbps)',
                                data: [],
                                borderColor: 'rgb(25, 135, 84)',
                                backgroundColor: 'rgba(25, 135, 84, 0.1)',
                                fill: true,
                                tension: 0.3,
                                pointRadius: 0
                            },
                            {
                                label: 'Upload (Kbps)',
                                data: [],
                                borderColor: 'rgb(13, 110, 253)',
                                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                                fill: true,
                                tension: 0.3,
                                pointRadius: 0
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            intersect: false,
                            mode: 'index'
                        },
                        scales: {
                            x: {
                                display: true,
                                grid: { display: false }
                            },
                            y: {
                                display: true,
                                beginAtZero: true,
                                title: { display: true, text: 'Kbps' }
                            }
                        },
                        plugins: {
                            legend: { position: 'top' }
                        },
                        animation: { duration: 0 }
                    }
                });
            }
            
            function formatSpeed(bytesPerSec) {
                const kbps = (bytesPerSec * 8) / 1000;
                if (kbps >= 1000) {
                    return (kbps / 1000).toFixed(2) + ' Mbps';
                }
                return kbps.toFixed(1) + ' Kbps';
            }
            
            function formatBytes(bytes) {
                if (bytes >= 1073741824) return (bytes / 1073741824).toFixed(2) + ' GB';
                if (bytes >= 1048576) return (bytes / 1048576).toFixed(2) + ' MB';
                if (bytes >= 1024) return (bytes / 1024).toFixed(2) + ' KB';
                return bytes + ' B';
            }
            
            async function fetchTrafficData() {
                try {
                    const response = await fetch(`/index.php?page=isp&action=get_live_traffic&subscription_id=${subscriptionId}`);
                    const result = await response.json();
                    
                    if (!result.success) {
                        document.getElementById('sessionStatus').textContent = result.error || 'Error';
                        document.getElementById('sessionStatus').className = 'fw-bold text-danger';
                        return;
                    }
                    
                    const data = result.data;
                    const now = Date.now();
                    
                    if (!data.online) {
                        document.getElementById('sessionStatus').textContent = 'Offline';
                        document.getElementById('sessionStatus').className = 'fw-bold text-danger';
                        return;
                    }
                    
                    document.getElementById('sessionStatus').textContent = 'Online';
                    document.getElementById('sessionStatus').className = 'fw-bold text-success';
                    
                    if (previousTime > 0) {
                        const timeDiff = (now - previousTime) / 1000;
                        const rxDiff = Math.max(0, data.rx_bytes - previousRx);
                        const txDiff = Math.max(0, data.tx_bytes - previousTx);
                        
                        const rxSpeed = rxDiff / timeDiff;
                        const txSpeed = txDiff / timeDiff;
                        
                        const rxKbps = (rxSpeed * 8) / 1000;
                        const txKbps = (txSpeed * 8) / 1000;
                        
                        // From router perspective: rx = customer upload, tx = customer download
                        document.getElementById('currentDownload').textContent = formatSpeed(txSpeed);
                        document.getElementById('currentUpload').textContent = formatSpeed(rxSpeed);
                        
                        const timeLabel = new Date().toLocaleTimeString('en-US', { hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit' });
                        
                        trafficChart.data.labels.push(timeLabel);
                        trafficChart.data.datasets[0].data.push(txKbps.toFixed(1));
                        trafficChart.data.datasets[1].data.push(rxKbps.toFixed(1));
                        
                        if (trafficChart.data.labels.length > maxDataPoints) {
                            trafficChart.data.labels.shift();
                            trafficChart.data.datasets[0].data.shift();
                            trafficChart.data.datasets[1].data.shift();
                        }
                        
                        trafficChart.update('none');
                    }
                    
                    previousRx = data.rx_bytes;
                    previousTx = data.tx_bytes;
                    previousTime = now;
                    
                    document.getElementById('totalData').textContent = formatBytes(data.rx_bytes + data.tx_bytes);
                    
                } catch (error) {
                    console.error('Traffic fetch error:', error);
                    document.getElementById('sessionStatus').textContent = 'Error';
                    document.getElementById('sessionStatus').className = 'fw-bold text-danger';
                }
            }
            
            function startTrafficMonitor() {
                if (!trafficChart) {
                    initTrafficChart();
                }
                
                previousRx = 0;
                previousTx = 0;
                previousTime = 0;
                trafficChart.data.labels = [];
                trafficChart.data.datasets[0].data = [];
                trafficChart.data.datasets[1].data = [];
                trafficChart.update();
                
                document.getElementById('trafficStatus').textContent = 'Running';
                document.getElementById('trafficStatus').className = 'badge bg-success';
                document.getElementById('startTrafficBtn').classList.add('d-none');
                document.getElementById('stopTrafficBtn').classList.remove('d-none');
                
                fetchTrafficData();
                trafficInterval = setInterval(fetchTrafficData, 2000);
            }
            
            function stopTrafficMonitor() {
                if (trafficInterval) {
                    clearInterval(trafficInterval);
                    trafficInterval = null;
                }
                
                document.getElementById('trafficStatus').textContent = 'Stopped';
                document.getElementById('trafficStatus').className = 'badge bg-secondary';
                document.getElementById('startTrafficBtn').classList.remove('d-none');
                document.getElementById('stopTrafficBtn').classList.add('d-none');
            }
            
            document.getElementById('traffic-tab')?.addEventListener('hidden.bs.tab', function() {
                stopTrafficMonitor();
            });
            </script>
            
            <!-- Renew Modal -->
            <div class="modal fade" id="renewModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="post">
                            <input type="hidden" name="action" value="renew_subscription">
                            <input type="hidden" name="id" value="<?= $subId ?>">
                            <input type="hidden" name="return_to" value="subscriber">
                            <div class="modal-header">
                                <h5 class="modal-title">Renew Subscription</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">Package</label>
                                    <select name="package_id" class="form-select">
                                        <?php foreach ($packages as $p): ?>
                                        <option value="<?= $p['id'] ?>" <?= $p['id'] == $subscriber['package_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($p['name']) ?> - KES <?= number_format($p['price']) ?> (<?= $p['validity_days'] ?> days)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle me-2"></i>
                                    Current expiry: <?= $subscriber['expiry_date'] ? date('M j, Y', strtotime($subscriber['expiry_date'])) : 'Not set' ?>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Renew Now</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Override Speed Modal -->
            <div class="modal fade" id="overrideSpeedModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content border-0 shadow-lg">
                        <form method="post">
                            <input type="hidden" name="action" value="override_speed_coa">
                            <input type="hidden" name="subscription_id" value="<?= $subId ?>">
                            <input type="hidden" name="return_to" value="subscriber">
                            <div class="modal-header border-0 bg-primary text-white">
                                <h5 class="modal-title"><i class="bi bi-speedometer2 me-2"></i>Override Speed</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <?php if (!empty($subscription['speed_override'])): ?>
                                <div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                    <strong>Active Override:</strong> <?= htmlspecialchars($subscription['speed_override']) ?>
                                    <?php if (!empty($subscription['override_expires_at'])): ?>
                                        <br><small>Expires: <?= date('M j, Y g:i A', strtotime($subscription['override_expires_at'])) ?></small>
                                    <?php else: ?>
                                        <br><small>No expiry (permanent until cleared)</small>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle me-2"></i>
                                    Override speeds are saved and will persist across reconnects until they expire or are cleared.
                                </div>
                                <div class="row g-3">
                                    <div class="col-6">
                                        <label class="form-label">Download Speed</label>
                                        <div class="input-group">
                                            <input type="number" name="download_speed" class="form-control" placeholder="e.g. 50" value="<?= intval($package['download_speed'] ?? 10) ?>" required min="1">
                                            <select name="download_unit" class="form-select" style="max-width: 80px;">
                                                <option value="M" selected>Mbps</option>
                                                <option value="k">Kbps</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">Upload Speed</label>
                                        <div class="input-group">
                                            <input type="number" name="upload_speed" class="form-control" placeholder="e.g. 25" value="<?= intval($package['upload_speed'] ?? 5) ?>" required min="1">
                                            <select name="upload_unit" class="form-select" style="max-width: 80px;">
                                                <option value="M" selected>Mbps</option>
                                                <option value="k">Kbps</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <label class="form-label">Duration</label>
                                    <select name="duration_hours" class="form-select">
                                        <option value="1">1 hour</option>
                                        <option value="2">2 hours</option>
                                        <option value="6">6 hours</option>
                                        <option value="12">12 hours</option>
                                        <option value="24" selected>24 hours</option>
                                        <option value="48">2 days</option>
                                        <option value="72">3 days</option>
                                        <option value="168">1 week</option>
                                        <option value="">Permanent (no expiry)</option>
                                    </select>
                                </div>
                                <div class="mt-3">
                                    <label class="form-label">Quick Presets</label>
                                    <div class="d-flex flex-wrap gap-2">
                                        <button type="button" class="btn btn-sm btn-outline-secondary speed-preset" data-down="5" data-up="2">5/2 Mbps</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary speed-preset" data-down="10" data-up="5">10/5 Mbps</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary speed-preset" data-down="20" data-up="10">20/10 Mbps</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary speed-preset" data-down="50" data-up="25">50/25 Mbps</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary speed-preset" data-down="100" data-up="50">100/50 Mbps</button>
                                        <button type="button" class="btn btn-sm btn-outline-warning speed-preset" data-down="1" data-up="1">1/1 Mbps (Throttle)</button>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer border-0">
                                <?php if (!empty($subscription['speed_override'])): ?>
                                <button type="submit" name="action" value="clear_speed_override" class="btn btn-outline-danger me-auto">
                                    <i class="bi bi-x-circle me-1"></i> Clear Override
                                </button>
                                <?php endif; ?>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-send me-1"></i> Apply Speed Override
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <script>
            document.querySelectorAll('.speed-preset').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.querySelector('input[name="download_speed"]').value = this.dataset.down;
                    document.querySelector('input[name="upload_speed"]').value = this.dataset.up;
                    document.querySelectorAll('select[name$="_unit"]').forEach(s => s.value = 'M');
                });
            });
            </script>
            
            <!-- Change Expiry Modal -->
            <div class="modal fade" id="changeExpiryModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content border-0 shadow-lg">
                        <form method="post">
                            <input type="hidden" name="action" value="change_expiry">
                            <input type="hidden" name="id" value="<?= $subId ?>">
                            <input type="hidden" name="return_to" value="subscriber">
                            <div class="modal-header border-0" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                                <h5 class="modal-title text-white"><i class="bi bi-calendar-event me-2"></i>Change Expiry Date</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="text-center mb-4">
                                    <div class="rounded-circle mx-auto d-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                                        <i class="bi bi-calendar3 text-white fs-2"></i>
                                    </div>
                                    <p class="text-muted">Adjust the subscription expiry date manually</p>
                                </div>
                                
                                <div class="row g-3 mb-3">
                                    <div class="col-6">
                                        <div class="p-3 rounded-3 bg-light text-center">
                                            <small class="text-muted d-block">Current Expiry</small>
                                            <strong class="text-primary"><?= $subscriber['expiry_date'] ? date('M j, Y', strtotime($subscriber['expiry_date'])) : 'Not set' ?></strong>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="p-3 rounded-3 bg-light text-center">
                                            <small class="text-muted d-block">Days Remaining</small>
                                            <strong class="<?= ($daysLeft ?? 0) < 0 ? 'text-danger' : 'text-success' ?>"><?= $daysLeft !== null ? ($daysLeft < 0 ? 'Expired (' . abs(floor($daysLeft)) . 'd ago)' : floor($daysLeft) . ' days') : 'N/A' ?></strong>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">New Expiry Date</label>
                                    <input type="date" name="new_expiry_date" class="form-control form-control-lg" value="<?= $subscriber['expiry_date'] ?? date('Y-m-d') ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Quick Extend</label>
                                    <div class="btn-group w-100" role="group">
                                        <button type="button" class="btn btn-outline-primary" onclick="extendExpiry(7)">+7 Days</button>
                                        <button type="button" class="btn btn-outline-primary" onclick="extendExpiry(14)">+14 Days</button>
                                        <button type="button" class="btn btn-outline-primary" onclick="extendExpiry(30)">+30 Days</button>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Reason (Optional)</label>
                                    <textarea name="expiry_change_reason" class="form-control" rows="2" placeholder="e.g., Customer requested extension, billing adjustment..."></textarea>
                                </div>
                                
                                <div class="alert alert-warning small mb-0">
                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                    Changing expiry date will not charge the customer. Use for manual adjustments only.
                                </div>
                            </div>
                            <div class="modal-footer border-0">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); border: none;">
                                    <i class="bi bi-check-lg me-1"></i> Update Expiry
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <script>
            function extendExpiry(days) {
                const input = document.querySelector('#changeExpiryModal input[name="new_expiry_date"]');
                const currentDate = input.value ? new Date(input.value) : new Date();
                currentDate.setDate(currentDate.getDate() + days);
                input.value = currentDate.toISOString().split('T')[0];
            }
            </script>
            
            <!-- Change Package Modal -->
            <?php
            $currentPackagePrice = $package['price'] ?? 0;
            $currentValidityDays = $package['validity_days'] ?? 30;
            $daysRemaining = max(0, ceil($daysLeft ?? 0));
            $dailyRate = $currentValidityDays > 0 ? $currentPackagePrice / $currentValidityDays : 0;
            $remainingCredit = round($dailyRate * $daysRemaining);
            ?>
            <div class="modal fade" id="changePackageModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content border-0 shadow-lg">
                        <form method="post" id="changePackageForm">
                            <input type="hidden" name="action" value="change_package">
                            <input type="hidden" name="id" value="<?= $subId ?>">
                            <input type="hidden" name="return_to" value="subscriber">
                            <input type="hidden" name="prorate_amount" id="prorateAmountInput" value="0">
                            <div class="modal-header border-0" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                <h5 class="modal-title text-white"><i class="bi bi-arrow-left-right me-2"></i>Change Package</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row g-4">
                                    <!-- Current Package -->
                                    <div class="col-md-5">
                                        <div class="card h-100 border-2 border-primary">
                                            <div class="card-header bg-primary text-white text-center">
                                                <small>CURRENT PACKAGE</small>
                                            </div>
                                            <div class="card-body text-center">
                                                <h4 class="fw-bold"><?= htmlspecialchars($package['name'] ?? 'N/A') ?></h4>
                                                <div class="my-3">
                                                    <span class="fs-3 fw-bold text-primary">KES <?= number_format($currentPackagePrice) ?></span>
                                                    <small class="text-muted">/ <?= $currentValidityDays ?> days</small>
                                                </div>
                                                <div class="small text-muted">
                                                    <i class="bi bi-arrow-down text-success"></i> <?= $package['download_speed'] ?? '-' ?>
                                                    <i class="bi bi-arrow-up text-danger ms-2"></i> <?= $package['upload_speed'] ?? '-' ?>
                                                </div>
                                                <hr>
                                                <div class="bg-light rounded-3 p-2">
                                                    <small class="text-muted d-block">Remaining Value</small>
                                                    <span class="fw-bold text-success fs-5">KES <?= number_format($remainingCredit) ?></span>
                                                    <small class="text-muted d-block">(<?= $daysRemaining ?> days x KES <?= number_format($dailyRate, 1) ?>/day)</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Arrow -->
                                    <div class="col-md-2 d-flex align-items-center justify-content-center">
                                        <div class="text-center">
                                            <i class="bi bi-arrow-right fs-1 text-muted d-none d-md-block"></i>
                                            <i class="bi bi-arrow-down fs-1 text-muted d-md-none"></i>
                                        </div>
                                    </div>
                                    
                                    <!-- New Package Selection -->
                                    <div class="col-md-5">
                                        <div class="card h-100 border-2 border-success">
                                            <div class="card-header bg-success text-white text-center">
                                                <small>NEW PACKAGE</small>
                                            </div>
                                            <div class="card-body text-center">
                                                <select name="new_package_id" id="newPackageSelect" class="form-select form-select-lg mb-3" onchange="calculateProrate()">
                                                    <option value="">Select Package...</option>
                                                    <?php foreach ($packages as $p): ?>
                                                    <?php if ($p['id'] != $subscriber['package_id']): ?>
                                                    <option value="<?= $p['id'] ?>" 
                                                            data-price="<?= $p['price'] ?>" 
                                                            data-days="<?= $p['validity_days'] ?>"
                                                            data-name="<?= htmlspecialchars($p['name']) ?>"
                                                            data-download="<?= htmlspecialchars($p['download_speed']) ?>"
                                                            data-upload="<?= htmlspecialchars($p['upload_speed']) ?>">
                                                        <?= htmlspecialchars($p['name']) ?> - KES <?= number_format($p['price']) ?>
                                                    </option>
                                                    <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </select>
                                                
                                                <div id="newPackageInfo" style="display:none;">
                                                    <h4 class="fw-bold" id="newPackageName">-</h4>
                                                    <div class="my-3">
                                                        <span class="fs-3 fw-bold text-success" id="newPackagePrice">KES 0</span>
                                                        <small class="text-muted" id="newPackageDays">/ 0 days</small>
                                                    </div>
                                                    <div class="small text-muted" id="newPackageSpeeds">
                                                        <i class="bi bi-arrow-down text-success"></i> -
                                                        <i class="bi bi-arrow-up text-danger ms-2"></i> -
                                                    </div>
                                                </div>
                                                <div id="newPackagePlaceholder" class="py-4">
                                                    <i class="bi bi-box-seam text-muted" style="font-size: 3rem;"></i>
                                                    <p class="text-muted mt-2">Select a package above</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Proration Calculation -->
                                <div id="prorationSection" class="mt-4" style="display:none;">
                                    <div class="card bg-light border-0">
                                        <div class="card-body">
                                            <h6 class="fw-bold mb-3"><i class="bi bi-calculator me-2"></i>Payment Calculation</h6>
                                            <div class="row g-2 small">
                                                <div class="col-8">Credit from current package (<?= $daysRemaining ?> days remaining)</div>
                                                <div class="col-4 text-end fw-bold text-success">- KES <?= number_format($remainingCredit) ?></div>
                                                
                                                <div class="col-8">New package cost</div>
                                                <div class="col-4 text-end fw-bold" id="newPackageCost">+ KES 0</div>
                                                
                                                <div class="col-12"><hr class="my-2"></div>
                                                
                                                <div class="col-8 fw-bold fs-5" id="balanceLabel">Amount Due</div>
                                                <div class="col-4 text-end fw-bold fs-5" id="balanceAmount">KES 0</div>
                                            </div>
                                            
                                            <div id="refundNote" class="alert alert-info mt-3 small" style="display:none;">
                                                <i class="bi bi-info-circle me-2"></i>
                                                Credit will be added to customer's wallet balance.
                                            </div>
                                            <div id="paymentNote" class="alert alert-warning mt-3 small" style="display:none;">
                                                <i class="bi bi-exclamation-triangle me-2"></i>
                                                Customer needs to pay the difference before upgrade.
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="form-check mt-3">
                                        <input type="checkbox" class="form-check-input" id="applyProrate" name="apply_prorate" value="1" checked>
                                        <label class="form-check-label" for="applyProrate">
                                            Apply prorated calculation (use credit from remaining days)
                                        </label>
                                    </div>
                                    
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="sendCoA" name="send_coa" value="1" checked>
                                        <label class="form-check-label" for="sendCoA">
                                            Send CoA to update speed immediately
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer border-0">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary" id="changePackageBtn" disabled style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                                    <i class="bi bi-check-lg me-1"></i> Change Package
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <script>
            const currentRemainingCredit = <?= $remainingCredit ?>;
            const currentDaysRemaining = <?= $daysRemaining ?>;
            
            function calculateProrate() {
                const select = document.getElementById('newPackageSelect');
                const option = select.options[select.selectedIndex];
                const prorationSection = document.getElementById('prorationSection');
                const newPackageInfo = document.getElementById('newPackageInfo');
                const newPackagePlaceholder = document.getElementById('newPackagePlaceholder');
                const changePackageBtn = document.getElementById('changePackageBtn');
                
                if (!option.value) {
                    prorationSection.style.display = 'none';
                    newPackageInfo.style.display = 'none';
                    newPackagePlaceholder.style.display = 'block';
                    changePackageBtn.disabled = true;
                    return;
                }
                
                const newPrice = parseFloat(option.dataset.price);
                const newDays = parseInt(option.dataset.days);
                const newName = option.dataset.name;
                const newDownload = option.dataset.download;
                const newUpload = option.dataset.upload;
                
                document.getElementById('newPackageName').textContent = newName;
                document.getElementById('newPackagePrice').textContent = 'KES ' + newPrice.toLocaleString();
                document.getElementById('newPackageDays').textContent = '/ ' + newDays + ' days';
                document.getElementById('newPackageSpeeds').innerHTML = '<i class="bi bi-arrow-down text-success"></i> ' + newDownload + ' <i class="bi bi-arrow-up text-danger ms-2"></i> ' + newUpload;
                
                newPackageInfo.style.display = 'block';
                newPackagePlaceholder.style.display = 'none';
                prorationSection.style.display = 'block';
                changePackageBtn.disabled = false;
                
                document.getElementById('newPackageCost').textContent = '+ KES ' + newPrice.toLocaleString();
                
                const balance = newPrice - currentRemainingCredit;
                const balanceLabel = document.getElementById('balanceLabel');
                const balanceAmount = document.getElementById('balanceAmount');
                const refundNote = document.getElementById('refundNote');
                const paymentNote = document.getElementById('paymentNote');
                
                document.getElementById('prorateAmountInput').value = balance;
                
                if (balance < 0) {
                    balanceLabel.textContent = 'Credit to Wallet';
                    balanceAmount.textContent = 'KES ' + Math.abs(balance).toLocaleString();
                    balanceAmount.className = 'col-4 text-end fw-bold fs-5 text-success';
                    refundNote.style.display = 'block';
                    paymentNote.style.display = 'none';
                } else if (balance > 0) {
                    balanceLabel.textContent = 'Amount Due';
                    balanceAmount.textContent = 'KES ' + balance.toLocaleString();
                    balanceAmount.className = 'col-4 text-end fw-bold fs-5 text-danger';
                    refundNote.style.display = 'none';
                    paymentNote.style.display = 'block';
                } else {
                    balanceLabel.textContent = 'Balance';
                    balanceAmount.textContent = 'KES 0';
                    balanceAmount.className = 'col-4 text-end fw-bold fs-5';
                    refundNote.style.display = 'none';
                    paymentNote.style.display = 'none';
                }
            }
            </script>
            
            <!-- Edit Subscription Modal -->
            <div class="modal fade" id="editSubscriptionModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="post">
                            <input type="hidden" name="action" value="update_subscription">
                            <input type="hidden" name="id" value="<?= $subId ?>">
                            <input type="hidden" name="return_to" value="subscriber">
                            <div class="modal-header">
                                <h5 class="modal-title">Edit Subscription</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">Package</label>
                                    <select name="package_id" class="form-select">
                                        <?php foreach ($packages as $p): ?>
                                        <option value="<?= $p['id'] ?>" <?= $p['id'] == $subscriber['package_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($p['name']) ?> - KES <?= number_format($p['price']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Password</label>
                                    <input type="text" name="password" class="form-control" value="<?= htmlspecialchars($subscriber['password'] ?? '') ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Expiry Date</label>
                                    <input type="date" name="expiry_date" class="form-control" value="<?= $subscriber['expiry_date'] ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Static IP</label>
                                    <input type="text" name="static_ip" class="form-control" value="<?= htmlspecialchars($subscriber['static_ip'] ?? '') ?>" placeholder="Leave empty for dynamic">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">MAC Address</label>
                                    <input type="text" name="mac_address" class="form-control" value="<?= htmlspecialchars($subscriber['mac_address'] ?? '') ?>">
                                </div>
                                <div class="form-check mb-3">
                                    <input type="checkbox" name="auto_renew" class="form-check-input" id="autoRenewCheck" <?= $subscriber['auto_renew'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="autoRenewCheck">Auto-renew subscription</label>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Edit Customer Modal -->
            <div class="modal fade" id="editCustomerModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="post">
                            <input type="hidden" name="action" value="update_subscriber_customer">
                            <input type="hidden" name="subscription_id" value="<?= $subId ?>">
                            <input type="hidden" name="customer_id" value="<?= $customer['id'] ?? '' ?>">
                            <input type="hidden" name="return_to" value="subscriber">
                            <div class="modal-header">
                                <h5 class="modal-title">Edit Customer Information</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">Name</label>
                                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($customer['name'] ?? '') ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Phone</label>
                                    <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($customer['phone'] ?? '') ?>" required>
                                    <small class="text-muted">Used as account number for M-Pesa payments</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($customer['email'] ?? '') ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Address</label>
                                    <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($customer['address'] ?? '') ?></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Add Credit Modal -->
            <div class="modal fade" id="addCreditModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="post">
                            <input type="hidden" name="action" value="add_credit">
                            <input type="hidden" name="subscription_id" value="<?= $subId ?>">
                            <input type="hidden" name="return_to" value="subscriber">
                            <div class="modal-header">
                                <h5 class="modal-title">Add Credit</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">Amount (KES)</label>
                                    <input type="number" name="amount" class="form-control" min="1" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Reference/Notes</label>
                                    <input type="text" name="reference" class="form-control" placeholder="e.g., Manual credit, Refund, etc.">
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-success">Add Credit</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- STK Push Modal -->
            <?php if ($customer && !empty($customer['phone'])): ?>
            <div class="modal fade" id="stkPushModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-success-subtle">
                            <h5 class="modal-title"><i class="bi bi-phone me-2"></i>M-Pesa STK Push</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <form id="stkPushForm" onsubmit="return submitStkPush(event)">
                                <div class="mb-3">
                                    <label class="form-label">Phone Number</label>
                                    <input type="text" class="form-control" id="stkPhone" value="<?= htmlspecialchars(preg_replace('/[^0-9]/', '', $customer['phone'])) ?>" readonly>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Amount (KES)</label>
                                    <input type="number" class="form-control" id="stkAmount" value="<?= (int)($package['price'] ?? 0) ?>" min="1" required>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-outline-secondary flex-fill" onclick="document.getElementById('stkAmount').value=<?= (int)($package['price'] ?? 0) ?>">Package Price</button>
                                    <button type="button" class="btn btn-outline-secondary flex-fill" onclick="document.getElementById('stkAmount').value=500">500</button>
                                    <button type="button" class="btn btn-outline-secondary flex-fill" onclick="document.getElementById('stkAmount').value=1000">1000</button>
                                </div>
                            </form>
                            <div id="stkResult" class="mt-3 text-center"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-success" onclick="submitStkPush()" id="stkSubmitBtn">
                                <i class="bi bi-lightning-charge me-1"></i> Send STK Push
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- WiFi Config Modal -->
            <div class="modal fade" id="wifiConfigModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="bi bi-wifi me-2"></i>WiFi Configuration (TR-069)</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>Configure WiFi settings directly on the customer's device via TR-069/GenieACS.
                            </div>
                            
                            <div id="wifiConfigLoading" class="text-center py-4">
                                <div class="spinner-border text-primary" role="status"></div>
                                <p class="mt-2 text-muted">Loading device info...</p>
                            </div>
                            
                            <div id="wifiConfigContent" style="display: none;">
                                <ul class="nav nav-tabs" role="tablist">
                                    <li class="nav-item">
                                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#wifi24ghz">2.4 GHz</button>
                                    </li>
                                    <li class="nav-item" id="wifi5ghzTab" style="display: none;">
                                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#wifi5ghz">5 GHz</button>
                                    </li>
                                </ul>
                                
                                <div class="tab-content pt-3">
                                    <div class="tab-pane fade show active" id="wifi24ghz">
                                        <form id="wifi24Form">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">WiFi Name (SSID)</label>
                                                        <input type="text" class="form-control" id="wifi24Ssid" placeholder="Enter WiFi name">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">WiFi Password</label>
                                                        <div class="input-group">
                                                            <input type="password" class="form-control" id="wifi24Password" placeholder="Enter password" minlength="8">
                                                            <button type="button" class="btn btn-outline-secondary" onclick="toggleWifiPwd('wifi24Password')"><i class="bi bi-eye"></i></button>
                                                        </div>
                                                        <small class="text-muted">Minimum 8 characters</small>
                                                    </div>
                                                </div>
                                            </div>
                                            <button type="button" class="btn btn-primary" onclick="saveWifiConfig('2.4')">
                                                <i class="bi bi-check-lg me-1"></i> Save 2.4 GHz Settings
                                            </button>
                                        </form>
                                    </div>
                                    <div class="tab-pane fade" id="wifi5ghz">
                                        <form id="wifi5Form">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">WiFi Name (SSID)</label>
                                                        <input type="text" class="form-control" id="wifi5Ssid" placeholder="Enter WiFi name">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">WiFi Password</label>
                                                        <div class="input-group">
                                                            <input type="password" class="form-control" id="wifi5Password" placeholder="Enter password" minlength="8">
                                                            <button type="button" class="btn btn-outline-secondary" onclick="toggleWifiPwd('wifi5Password')"><i class="bi bi-eye"></i></button>
                                                        </div>
                                                        <small class="text-muted">Minimum 8 characters</small>
                                                    </div>
                                                </div>
                                            </div>
                                            <button type="button" class="btn btn-primary" onclick="saveWifiConfig('5')">
                                                <i class="bi bi-check-lg me-1"></i> Save 5 GHz Settings
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <div id="wifiConfigError" class="alert alert-warning" style="display: none;">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                <span id="wifiErrorMsg">No TR-069 device found for this subscriber.</span>
                            </div>
                            
                            <div id="wifiConfigResult" class="mt-3" style="display: none;"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
            
            <script>
            // WiFi Config Modal initialization
            document.getElementById('wifiConfigModal').addEventListener('show.bs.modal', function() {
                loadWifiConfig();
            });
            
            function loadWifiConfig() {
                document.getElementById('wifiConfigLoading').style.display = 'block';
                document.getElementById('wifiConfigContent').style.display = 'none';
                document.getElementById('wifiConfigError').style.display = 'none';
                
                fetch('/index.php?page=isp&action=get_wifi_config&subscription_id=<?= $subId ?>')
                    .then(r => r.json())
                    .then(data => {
                        document.getElementById('wifiConfigLoading').style.display = 'none';
                        if (data.success) {
                            document.getElementById('wifiConfigContent').style.display = 'block';
                            if (data.wifi_24) {
                                document.getElementById('wifi24Ssid').value = data.wifi_24.ssid || '';
                                document.getElementById('wifi24Password').value = data.wifi_24.password || '';
                            }
                            if (data.wifi_5) {
                                document.getElementById('wifi5ghzTab').style.display = 'block';
                                document.getElementById('wifi5Ssid').value = data.wifi_5.ssid || '';
                                document.getElementById('wifi5Password').value = data.wifi_5.password || '';
                            }
                        } else {
                            document.getElementById('wifiConfigError').style.display = 'block';
                            document.getElementById('wifiErrorMsg').textContent = data.error || 'No TR-069 device found';
                        }
                    })
                    .catch(err => {
                        document.getElementById('wifiConfigLoading').style.display = 'none';
                        document.getElementById('wifiConfigError').style.display = 'block';
                        document.getElementById('wifiErrorMsg').textContent = 'Failed to load WiFi configuration';
                    });
            }
            
            function toggleWifiPwd(id) {
                const input = document.getElementById(id);
                input.type = input.type === 'password' ? 'text' : 'password';
            }
            
            function saveWifiConfig(band) {
                const ssidId = band === '2.4' ? 'wifi24Ssid' : 'wifi5Ssid';
                const pwdId = band === '2.4' ? 'wifi24Password' : 'wifi5Password';
                const ssid = document.getElementById(ssidId).value;
                const password = document.getElementById(pwdId).value;
                
                if (!ssid) {
                    alert('Please enter a WiFi name');
                    return;
                }
                if (password && password.length < 8) {
                    alert('Password must be at least 8 characters');
                    return;
                }
                
                const resultDiv = document.getElementById('wifiConfigResult');
                resultDiv.style.display = 'block';
                resultDiv.innerHTML = '<div class="alert alert-info"><i class="bi bi-hourglass-split me-2"></i>Applying WiFi settings...</div>';
                
                fetch('/index.php?page=isp&action=set_wifi_config', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        subscription_id: <?= $subId ?>,
                        band: band,
                        ssid: ssid,
                        password: password
                    })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        resultDiv.innerHTML = '<div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>' + (data.message || 'WiFi settings applied successfully!') + '</div>';
                    } else {
                        resultDiv.innerHTML = '<div class="alert alert-danger"><i class="bi bi-x-circle me-2"></i>' + (data.error || 'Failed to apply settings') + '</div>';
                    }
                })
                .catch(err => {
                    resultDiv.innerHTML = '<div class="alert alert-danger"><i class="bi bi-x-circle me-2"></i>Request failed</div>';
                });
            }
            
            function submitStkPush() {
                const phone = document.getElementById('stkPhone').value;
                const amount = document.getElementById('stkAmount').value;
                const btn = document.getElementById('stkSubmitBtn');
                const result = document.getElementById('stkResult');
                
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Sending...';
                result.innerHTML = '';
                
                fetch('/index.php?page=isp&action=stk_push', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        phone: phone,
                        amount: amount,
                        subscription_id: <?= $subId ?>
                    })
                })
                .then(r => r.json())
                .then(data => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-lightning-charge me-1"></i> Send STK Push';
                    if (data.success) {
                        result.innerHTML = '<div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>STK Push sent! Check your phone.</div>';
                    } else {
                        result.innerHTML = '<div class="alert alert-danger"><i class="bi bi-x-circle me-2"></i>' + (data.error || 'Failed to send STK Push') + '</div>';
                    }
                })
                .catch(err => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-lightning-charge me-1"></i> Send STK Push';
                    result.innerHTML = '<div class="alert alert-danger"><i class="bi bi-x-circle me-2"></i>Request failed</div>';
                });
                
                return false;
            }
            </script>
            
            <!-- Add Speed Override Modal -->
            <div class="modal fade" id="addSpeedOverrideModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="post">
                            <input type="hidden" name="action" value="add_speed_override">
                            <input type="hidden" name="package_id" value="<?= $subscriber['package_id'] ?? '' ?>">
                            <input type="hidden" name="subscription_id" value="<?= $subId ?>">
                            <input type="hidden" name="return_to" value="subscriber">
                            <div class="modal-header">
                                <h5 class="modal-title"><i class="bi bi-speedometer2 me-2"></i>Add Speed Schedule</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">Schedule Name</label>
                                    <input type="text" name="name" class="form-control" placeholder="e.g., Night Boost, Weekend Special" required>
                                </div>
                                <div class="row">
                                    <div class="col-6">
                                        <div class="mb-3">
                                            <label class="form-label">Download Speed</label>
                                            <input type="text" name="download_speed" class="form-control" placeholder="e.g., 50M, 100M" required>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="mb-3">
                                            <label class="form-label">Upload Speed</label>
                                            <input type="text" name="upload_speed" class="form-control" placeholder="e.g., 20M, 50M" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-6">
                                        <div class="mb-3">
                                            <label class="form-label">Start Time</label>
                                            <input type="time" name="start_time" class="form-control" value="22:00" required>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="mb-3">
                                            <label class="form-label">End Time</label>
                                            <input type="time" name="end_time" class="form-control" value="06:00" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Days of Week (leave empty for all days)</label>
                                    <input type="text" name="days_of_week" class="form-control" placeholder="e.g., monday,tuesday,wednesday">
                                    <small class="text-muted">Comma-separated: monday,tuesday,wednesday,thursday,friday,saturday,sunday</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Priority</label>
                                    <input type="number" name="priority" class="form-control" value="10" min="0" max="100">
                                    <small class="text-muted">Higher priority overrides take precedence (0-100)</small>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Add Schedule</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <?php } ?>

            <?php elseif ($view === 'sessions'): ?>
            <?php $sessions = $radiusBilling->getActiveSessions(); ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="page-title mb-0"><i class="bi bi-broadcast"></i> Active Sessions (<?= count($sessions) ?>)</h4>
                <button class="btn btn-outline-primary" onclick="location.reload()"><i class="bi bi-arrow-clockwise me-1"></i> Refresh</button>
            </div>
            
            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <?php if (empty($sessions)): ?>
                    <div class="p-5 text-center text-muted">
                        <i class="bi bi-broadcast fs-1 mb-3 d-block"></i>
                        <h5>No Active Sessions</h5>
                        <p>There are no users currently connected.</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Username</th>
                                    <th>Customer</th>
                                    <th>IP Address</th>
                                    <th>MAC Address</th>
                                    <th>NAS</th>
                                    <th>Started</th>
                                    <th>Duration</th>
                                    <th>Download</th>
                                    <th>Upload</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sessions as $session): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($session['username']) ?></strong></td>
                                    <td><?= htmlspecialchars($session['customer_name'] ?? '-') ?></td>
                                    <td><code><?= htmlspecialchars($session['framed_ip_address']) ?></code></td>
                                    <td><code class="text-muted"><?= htmlspecialchars($session['mac_address'] ?? '-') ?></code></td>
                                    <td><?= htmlspecialchars($session['nas_name'] ?? '-') ?></td>
                                    <td><?= date('M j, H:i', strtotime($session['session_start'])) ?></td>
                                    <td>
                                        <?php 
                                        $utc = new DateTimeZone('UTC');
                                        $startDt = new DateTime($session['session_start'], $utc);
                                        $nowDt = new DateTime('now', $utc);
                                        $dur = $nowDt->getTimestamp() - $startDt->getTimestamp();
                                        if ($dur < 0) $dur = 0;
                                        $hours = floor($dur / 3600);
                                        $mins = floor(($dur % 3600) / 60);
                                        echo "{$hours}h {$mins}m";
                                        ?>
                                    </td>
                                    <td><?= number_format($session['input_octets'] / 1024 / 1024, 2) ?> MB</td>
                                    <td><?= number_format($session['output_octets'] / 1024 / 1024, 2) ?> MB</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php elseif ($view === 'packages'): ?>
            <?php $packages = $radiusBilling->getPackages(); ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="page-title mb-0"><i class="bi bi-box"></i> Service Packages</h4>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPackageModal">
                    <i class="bi bi-plus-lg me-1"></i> New Package
                </button>
            </div>
            
            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>Billing</th>
                                    <th>Price</th>
                                    <th>Speed</th>
                                    <th>Quota</th>
                                    <th>Sessions</th>
                                    <th>Devices</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($packages as $pkg): 
                                    $scheduleCount = count($radiusBilling->getPackageSchedules($pkg['id']));
                                ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($pkg['name']) ?></strong>
                                        <?php if ($pkg['description']): ?>
                                        <br><small class="text-muted"><?= htmlspecialchars($pkg['description']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge bg-secondary"><?= strtoupper($pkg['package_type']) ?></span></td>
                                    <td><?= ucfirst($pkg['billing_type']) ?></td>
                                    <td>KES <?= number_format($pkg['price']) ?></td>
                                    <td>
                                        <i class="bi bi-arrow-down text-success"></i> <?= $pkg['download_speed'] ?>
                                        <i class="bi bi-arrow-up text-primary ms-2"></i> <?= $pkg['upload_speed'] ?>
                                        <?php if (!empty($pkg['burst_download']) || !empty($pkg['burst_upload'])): ?>
                                        <br><small class="text-info"><i class="bi bi-lightning"></i> Burst: <?= $pkg['burst_download'] ?? '-' ?>/<?= $pkg['burst_upload'] ?? '-' ?></small>
                                        <?php endif; ?>
                                        <?php if ($pkg['fup_enabled']): ?>
                                        <br><small class="text-warning"><i class="bi bi-exclamation-triangle"></i> FUP: <?= $pkg['fup_download_speed'] ?>/<?= $pkg['fup_upload_speed'] ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $pkg['data_quota_mb'] ? number_format($pkg['data_quota_mb'] / 1024) . ' GB' : 'Unlimited' ?></td>
                                    <td><?= $pkg['simultaneous_sessions'] ?></td>
                                    <td>
                                        <?php $maxDevices = $pkg['max_devices'] ?? 1; ?>
                                        <?= $maxDevices ?><?php if ($maxDevices > 1): ?> <i class="bi bi-people-fill text-info" title="Multi-device"></i><?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($pkg['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-secondary" title="Edit Package" onclick="editPackage(<?= htmlspecialchars(json_encode($pkg)) ?>)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <a href="?page=isp&view=package_schedules&package_id=<?= $pkg['id'] ?>" class="btn btn-outline-primary" title="Speed Schedules">
                                                <i class="bi bi-clock-history"></i>
                                                <?php if ($scheduleCount > 0): ?>
                                                <span class="badge bg-primary"><?= $scheduleCount ?></span>
                                                <?php endif; ?>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="addPackageModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <form method="post">
                            <input type="hidden" name="action" value="create_package">
                            <div class="modal-header">
                                <h5 class="modal-title">New Package</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Package Name</label>
                                        <input type="text" name="name" class="form-control" required>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Type</label>
                                        <select name="package_type" class="form-select">
                                            <option value="pppoe">PPPoE</option>
                                            <option value="hotspot">Hotspot</option>
                                            <option value="static">Static IP</option>
                                            <option value="dhcp">DHCP</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Billing Cycle</label>
                                        <select name="billing_type" class="form-select">
                                            <option value="daily">Daily</option>
                                            <option value="weekly">Weekly</option>
                                            <option value="monthly" selected>Monthly</option>
                                            <option value="quarterly">Quarterly</option>
                                            <option value="yearly">Yearly</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Price (KES)</label>
                                        <input type="number" name="price" class="form-control" step="0.01" required>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Validity (Days)</label>
                                        <input type="number" name="validity_days" class="form-control" value="30">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Data Quota (MB)</label>
                                        <input type="number" name="data_quota_mb" class="form-control" placeholder="Leave empty for unlimited">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Download Speed</label>
                                        <input type="text" name="download_speed" class="form-control" placeholder="e.g., 10M">
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Upload Speed</label>
                                        <input type="text" name="upload_speed" class="form-control" placeholder="e.g., 5M">
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Priority (1-8)</label>
                                        <input type="number" name="priority" class="form-control" value="8" min="1" max="8">
                                    </div>
                                    <div class="col-md-3 mb-3 hotspot-only-field" style="display:none;">
                                        <label class="form-label">Simultaneous Sessions</label>
                                        <input type="number" name="simultaneous_sessions" class="form-control" value="1" min="1">
                                        <small class="text-muted">Hotspot only</small>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-3 mb-3 hotspot-only-field" style="display:none;">
                                        <label class="form-label">Max Devices (MACs)</label>
                                        <input type="number" name="max_devices" class="form-control" value="1" min="1" max="10">
                                        <small class="text-muted">Hotspot only</small>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Burst Download</label>
                                        <input type="text" name="burst_download" class="form-control" placeholder="e.g., 20M">
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Burst Upload</label>
                                        <input type="text" name="burst_upload" class="form-control" placeholder="e.g., 10M">
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Burst Threshold</label>
                                        <input type="text" name="burst_threshold" class="form-control" placeholder="e.g., 5M/2M">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Burst Time (seconds)</label>
                                        <input type="number" name="burst_time" class="form-control" value="10" min="1">
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Address Pool</label>
                                        <input type="text" name="address_pool" class="form-control" placeholder="e.g., pool1">
                                    </div>
                                    <div class="col-md-3 mb-3 d-flex align-items-end">
                                        <div class="form-check">
                                            <input type="checkbox" name="ip_binding" class="form-check-input" id="ipBindingNew">
                                            <label class="form-check-label" for="ipBindingNew">IP Binding</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3 mb-3 d-flex align-items-end">
                                        <div class="form-check">
                                            <input type="checkbox" name="is_active" class="form-check-input" id="isActiveNew" checked>
                                            <label class="form-check-label" for="isActiveNew">Active</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea name="description" class="form-control" rows="2"></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Create Package</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <script>
            // Toggle hotspot-only fields based on package type
            document.querySelector('#addPackageModal select[name="package_type"]').addEventListener('change', function() {
                const isHotspot = this.value === 'hotspot';
                document.querySelectorAll('#addPackageModal .hotspot-only-field').forEach(el => {
                    el.style.display = isHotspot ? 'block' : 'none';
                });
            });
            </script>
            
            <!-- Edit Package Modal -->
            <div class="modal fade" id="editPackageModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <form method="post">
                            <input type="hidden" name="action" value="update_package">
                            <input type="hidden" name="id" id="editPkgId">
                            <div class="modal-header">
                                <h5 class="modal-title">Edit Package</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Package Name</label>
                                        <input type="text" name="name" id="editPkgName" class="form-control" required>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Type</label>
                                        <select name="package_type" id="editPkgType" class="form-select">
                                            <option value="pppoe">PPPoE</option>
                                            <option value="hotspot">Hotspot</option>
                                            <option value="static">Static IP</option>
                                            <option value="dhcp">DHCP</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Billing Cycle</label>
                                        <select name="billing_type" id="editPkgBilling" class="form-select">
                                            <option value="daily">Daily</option>
                                            <option value="weekly">Weekly</option>
                                            <option value="monthly">Monthly</option>
                                            <option value="quarterly">Quarterly</option>
                                            <option value="yearly">Yearly</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Price (KES)</label>
                                        <input type="number" name="price" id="editPkgPrice" class="form-control" step="0.01" required>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Validity (Days)</label>
                                        <input type="number" name="validity_days" id="editPkgValidity" class="form-control">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Data Quota (MB)</label>
                                        <input type="number" name="data_quota_mb" id="editPkgQuota" class="form-control" placeholder="Leave empty for unlimited">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Download Speed</label>
                                        <input type="text" name="download_speed" id="editPkgDownload" class="form-control" placeholder="e.g., 10M">
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Upload Speed</label>
                                        <input type="text" name="upload_speed" id="editPkgUpload" class="form-control" placeholder="e.g., 5M">
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Priority (1-8)</label>
                                        <input type="number" name="priority" id="editPkgPriority" class="form-control" min="1" max="8">
                                    </div>
                                    <div class="col-md-3 mb-3 edit-hotspot-only-field" style="display:none;">
                                        <label class="form-label">Simultaneous Sessions</label>
                                        <input type="number" name="simultaneous_sessions" id="editPkgSessions" class="form-control" min="1">
                                        <small class="text-muted">Hotspot only</small>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-3 mb-3 edit-hotspot-only-field" style="display:none;">
                                        <label class="form-label">Max Devices</label>
                                        <input type="number" name="max_devices" id="editPkgDevices" class="form-control" min="1" max="10">
                                        <small class="text-muted">Hotspot only</small>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Burst Download</label>
                                        <input type="text" name="burst_download" id="editPkgBurstDown" class="form-control" placeholder="e.g., 20M">
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Burst Upload</label>
                                        <input type="text" name="burst_upload" id="editPkgBurstUp" class="form-control" placeholder="e.g., 10M">
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Burst Threshold</label>
                                        <input type="text" name="burst_threshold" id="editPkgBurstThreshold" class="form-control" placeholder="e.g., 5M/2M">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Burst Time (s)</label>
                                        <input type="number" name="burst_time" id="editPkgBurstTime" class="form-control" min="1">
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Address Pool</label>
                                        <input type="text" name="address_pool" id="editPkgPool" class="form-control">
                                    </div>
                                    <div class="col-md-3 mb-3 d-flex align-items-end">
                                        <div class="form-check">
                                            <input type="checkbox" name="ip_binding" class="form-check-input" id="editPkgIpBinding">
                                            <label class="form-check-label" for="editPkgIpBinding">IP Binding</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3 mb-3 d-flex align-items-end">
                                        <div class="form-check">
                                            <input type="checkbox" name="is_active" class="form-check-input" id="editPkgActive">
                                            <label class="form-check-label" for="editPkgActive">Active</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea name="description" id="editPkgDesc" class="form-control" rows="2"></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Update Package</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <script>
            function editPackage(pkg) {
                document.getElementById('editPkgId').value = pkg.id;
                document.getElementById('editPkgName').value = pkg.name || '';
                document.getElementById('editPkgType').value = pkg.package_type || 'pppoe';
                document.getElementById('editPkgBilling').value = pkg.billing_type || 'monthly';
                document.getElementById('editPkgPrice').value = pkg.price || '';
                document.getElementById('editPkgValidity').value = pkg.validity_days || 30;
                document.getElementById('editPkgQuota').value = pkg.data_quota_mb || '';
                document.getElementById('editPkgDownload').value = pkg.download_speed || '';
                document.getElementById('editPkgUpload').value = pkg.upload_speed || '';
                document.getElementById('editPkgPriority').value = pkg.priority || 8;
                document.getElementById('editPkgSessions').value = pkg.simultaneous_sessions || 1;
                document.getElementById('editPkgDevices').value = pkg.max_devices || 1;
                document.getElementById('editPkgBurstDown').value = pkg.burst_download || '';
                document.getElementById('editPkgBurstUp').value = pkg.burst_upload || '';
                document.getElementById('editPkgBurstThreshold').value = pkg.burst_threshold || '';
                document.getElementById('editPkgBurstTime').value = pkg.burst_time || 10;
                document.getElementById('editPkgPool').value = pkg.address_pool || '';
                document.getElementById('editPkgIpBinding').checked = pkg.ip_binding == true || pkg.ip_binding == 't';
                document.getElementById('editPkgActive').checked = pkg.is_active == true || pkg.is_active == 't';
                document.getElementById('editPkgDesc').value = pkg.description || '';
                
                // Toggle hotspot-only fields
                const isHotspot = pkg.package_type === 'hotspot';
                document.querySelectorAll('.edit-hotspot-only-field').forEach(el => {
                    el.style.display = isHotspot ? 'block' : 'none';
                });
                
                new bootstrap.Modal(document.getElementById('editPackageModal')).show();
            }
            
            // Toggle hotspot-only fields on type change in edit modal
            document.getElementById('editPkgType').addEventListener('change', function() {
                const isHotspot = this.value === 'hotspot';
                document.querySelectorAll('.edit-hotspot-only-field').forEach(el => {
                    el.style.display = isHotspot ? 'block' : 'none';
                });
            });
            </script>

            <?php elseif ($view === 'package_schedules'): ?>
            <?php 
            $packageId = (int)($_GET['package_id'] ?? 0);
            $pkg = $radiusBilling->getPackage($packageId);
            $schedules = $radiusBilling->getPackageSchedules($packageId);
            $dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            ?>
            <?php if (!$pkg): ?>
            <div class="alert alert-danger">Package not found. <a href="?page=isp&view=packages">Back to Packages</a></div>
            <?php else: ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <a href="?page=isp&view=packages" class="btn btn-sm btn-outline-secondary me-2">
                        <i class="bi bi-arrow-left"></i> Back
                    </a>
                    <h4 class="page-title mb-0 d-inline"><i class="bi bi-clock-history"></i> Speed Schedules: <?= htmlspecialchars($pkg['name']) ?></h4>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addScheduleModal">
                    <i class="bi bi-plus-lg me-1"></i> Add Schedule
                </button>
            </div>
            
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card bg-light">
                        <div class="card-body">
                            <h6 class="card-subtitle mb-2 text-muted">Package Base Speed</h6>
                            <p class="mb-0">
                                <i class="bi bi-arrow-down text-success"></i> Download: <strong><?= $pkg['download_speed'] ?></strong>
                                <i class="bi bi-arrow-up text-primary ms-3"></i> Upload: <strong><?= $pkg['upload_speed'] ?></strong>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card <?= $pkg['fup_enabled'] ? 'bg-warning-subtle' : 'bg-light' ?>">
                        <div class="card-body">
                            <h6 class="card-subtitle mb-2 text-muted">FUP (Fair Usage Policy)</h6>
                            <?php if ($pkg['fup_enabled']): ?>
                            <p class="mb-0">
                                After <?= number_format(($pkg['fup_quota_mb'] ?? 0) / 1024, 1) ?> GB:
                                <i class="bi bi-arrow-down text-warning"></i> <?= $pkg['fup_download_speed'] ?>
                                <i class="bi bi-arrow-up text-warning ms-2"></i> <?= $pkg['fup_upload_speed'] ?>
                            </p>
                            <?php else: ?>
                            <p class="mb-0 text-muted">FUP not enabled for this package</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i>
                <strong>How Speed Schedules Work:</strong> During scheduled times, users on this package will get the scheduled speeds instead of the base package speeds. 
                Higher priority schedules take precedence if times overlap. FUP throttling is applied first if the user has exceeded their quota.
            </div>
            
            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <?php if (empty($schedules)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-clock-history display-4"></i>
                        <p class="mt-2">No speed schedules configured for this package.</p>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addScheduleModal">
                            <i class="bi bi-plus-lg me-1"></i> Add First Schedule
                        </button>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Time Range</th>
                                    <th>Days</th>
                                    <th>Speed</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($schedules as $sch): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($sch['name']) ?></strong></td>
                                    <td><?= date('g:i A', strtotime($sch['start_time'])) ?> - <?= date('g:i A', strtotime($sch['end_time'])) ?></td>
                                    <td>
                                        <?php 
                                        $days = str_split($sch['days_of_week'] ?? '0123456');
                                        foreach ($days as $d) {
                                            echo '<span class="badge bg-secondary me-1">' . $dayNames[(int)$d] . '</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <i class="bi bi-arrow-down text-success"></i> <?= $sch['download_speed'] ?>
                                        <i class="bi bi-arrow-up text-primary ms-2"></i> <?= $sch['upload_speed'] ?>
                                    </td>
                                    <td><span class="badge bg-info"><?= $sch['priority'] ?></span></td>
                                    <td>
                                        <?php if ($sch['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="action" value="delete_package_schedule">
                                            <input type="hidden" name="schedule_id" value="<?= $sch['id'] ?>">
                                            <input type="hidden" name="package_id" value="<?= $packageId ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this schedule?')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Add Schedule Modal -->
            <div class="modal fade" id="addScheduleModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="post">
                            <input type="hidden" name="action" value="create_package_schedule">
                            <input type="hidden" name="package_id" value="<?= $packageId ?>">
                            <div class="modal-header">
                                <h5 class="modal-title"><i class="bi bi-clock-history me-2"></i>Add Speed Schedule</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">Schedule Name</label>
                                    <input type="text" name="name" class="form-control" placeholder="e.g., Peak Hours, Night Boost" required>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-6">
                                        <label class="form-label">Start Time</label>
                                        <input type="time" name="start_time" class="form-control" required>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">End Time</label>
                                        <input type="time" name="end_time" class="form-control" required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Days of Week</label>
                                    <div class="d-flex flex-wrap gap-2">
                                        <?php foreach ($dayNames as $i => $day): ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="days[]" value="<?= $i ?>" id="day<?= $i ?>" checked>
                                            <label class="form-check-label" for="day<?= $i ?>"><?= $day ?></label>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-6">
                                        <label class="form-label">Download Speed</label>
                                        <div class="input-group">
                                            <input type="number" name="download_speed" class="form-control" placeholder="e.g., 5" required min="1">
                                            <select name="download_unit" class="form-select" style="max-width: 80px;">
                                                <option value="M" selected>Mbps</option>
                                                <option value="k">Kbps</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">Upload Speed</label>
                                        <div class="input-group">
                                            <input type="number" name="upload_speed" class="form-control" placeholder="e.g., 2" required min="1">
                                            <select name="upload_unit" class="form-select" style="max-width: 80px;">
                                                <option value="M" selected>Mbps</option>
                                                <option value="k">Kbps</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-6">
                                        <label class="form-label">Priority</label>
                                        <input type="number" name="priority" class="form-control" value="0" min="0" max="100">
                                        <small class="text-muted">Higher priority wins if schedules overlap</small>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">Status</label>
                                        <select name="is_active" class="form-select">
                                            <option value="1" selected>Active</option>
                                            <option value="0">Inactive</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-lg me-1"></i> Create Schedule
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php elseif ($view === 'addons'): ?>
            <?php 
            $addonServices = $db->query("SELECT * FROM radius_addon_services ORDER BY category, name")->fetchAll(PDO::FETCH_ASSOC);
            $categories = ['Internet - PPPoE', 'Internet - Static IP', 'Internet - DHCP', 'IPTV', 'VoIP', 'CDN', 'Security', 'Cloud', 'Other'];
            ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="page-title mb-0"><i class="bi bi-plus-circle"></i> Addon Services</h4>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAddonModal">
                    <i class="bi bi-plus-lg me-1"></i> New Addon Service
                </button>
            </div>
            
            <div class="card shadow-sm">
                <div class="card-body">
                    <?php if (empty($addonServices)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-plus-circle display-4 mb-3"></i>
                        <p class="mb-0">No addon services configured yet.</p>
                        <p class="small">Create addon services like IPTV, VoIP, CDN, etc. to charge alongside packages.</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Name</th>
                                    <th>Category</th>
                                    <th>Unit Price</th>
                                    <th>Bandwidth</th>
                                    <th>Billing</th>
                                    <th>Status</th>
                                    <th>Subscribers</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($addonServices as $addon): ?>
                                <?php 
                                $subCount = $db->prepare("SELECT COUNT(*) FROM radius_subscription_addons WHERE addon_id = ? AND status = 'active'");
                                $subCount->execute([$addon['id']]);
                                $activeCount = $subCount->fetchColumn();
                                $isInternet = str_starts_with($addon['category'] ?? '', 'Internet');
                                ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($addon['name']) ?></strong>
                                        <?php if ($addon['description']): ?>
                                        <br><small class="text-muted"><?= htmlspecialchars($addon['description']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($addon['category'] ?? 'Other') ?></span></td>
                                    <td><strong>KES <?= number_format($addon['price'], 2) ?></strong></td>
                                    <td>
                                        <?php if ($isInternet && ($addon['download_speed'] || $addon['upload_speed'])): ?>
                                        <span class="badge bg-info">
                                            <i class="bi bi-arrow-down"></i> <?= $addon['download_speed'] ?? '-' ?>/
                                            <i class="bi bi-arrow-up"></i> <?= $addon['upload_speed'] ?? '-' ?> <?= $addon['speed_unit'] ?? 'Mbps' ?>
                                        </span>
                                        <?php elseif ($isInternet): ?>
                                        <span class="text-muted">Not set</span>
                                        <?php else: ?>
                                        <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($addon['billing_type'] === 'monthly'): ?>
                                        <span class="badge bg-info">Monthly</span>
                                        <?php elseif ($addon['billing_type'] === 'one_time'): ?>
                                        <span class="badge bg-warning">One-time</span>
                                        <?php else: ?>
                                        <span class="badge bg-primary">Per Use</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($addon['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge bg-primary"><?= $activeCount ?></span></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" onclick="editAddon(<?= htmlspecialchars(json_encode($addon)) ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Delete this addon service?')">
                                            <input type="hidden" name="action" value="delete_addon">
                                            <input type="hidden" name="addon_id" value="<?= $addon['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Add Addon Modal -->
            <div class="modal fade" id="addAddonModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="post">
                            <input type="hidden" name="action" value="add_addon">
                            <div class="modal-header">
                                <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>New Addon Service</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">Service Name *</label>
                                    <input type="text" name="name" class="form-control" required placeholder="e.g., IPTV Premium">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea name="description" class="form-control" rows="2" placeholder="Brief description of the service"></textarea>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Category</label>
                                        <select name="category" class="form-select">
                                            <?php foreach ($categories as $cat): ?>
                                            <option value="<?= $cat ?>"><?= $cat ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Billing Type</label>
                                        <select name="billing_type" class="form-select">
                                            <option value="monthly">Monthly (Recurring)</option>
                                            <option value="one_time">One-time</option>
                                            <option value="per_use">Per Use</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Unit Price (KES) *</label>
                                        <input type="number" name="price" class="form-control" step="0.01" required placeholder="0.00">
                                        <small class="text-muted">Price per unit/quantity</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Setup Fee (KES)</label>
                                        <input type="number" name="setup_fee" class="form-control" step="0.01" value="0" placeholder="0.00">
                                    </div>
                                </div>
                                <div id="addBandwidthFields" class="border rounded p-3 mb-3" style="display:none; background:#f8f9fa;">
                                    <h6 class="mb-3"><i class="bi bi-speedometer2 me-1"></i> Bandwidth Settings</h6>
                                    <div class="row">
                                        <div class="col-md-4 mb-2">
                                            <label class="form-label">Download Speed</label>
                                            <input type="number" name="download_speed" class="form-control" min="1" placeholder="e.g., 10">
                                        </div>
                                        <div class="col-md-4 mb-2">
                                            <label class="form-label">Upload Speed</label>
                                            <input type="number" name="upload_speed" class="form-control" min="1" placeholder="e.g., 5">
                                        </div>
                                        <div class="col-md-4 mb-2">
                                            <label class="form-label">Unit</label>
                                            <select name="speed_unit" class="form-select">
                                                <option value="Mbps">Mbps</option>
                                                <option value="Kbps">Kbps</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" name="is_active" class="form-check-input" id="addonActive" checked>
                                    <label class="form-check-label" for="addonActive">Active (available for assignment)</label>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Create Addon</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Edit Addon Modal -->
            <div class="modal fade" id="editAddonModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="post">
                            <input type="hidden" name="action" value="edit_addon">
                            <input type="hidden" name="addon_id" id="editAddonId">
                            <div class="modal-header">
                                <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Addon Service</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">Service Name *</label>
                                    <input type="text" name="name" id="editAddonName" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea name="description" id="editAddonDesc" class="form-control" rows="2"></textarea>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Category</label>
                                        <select name="category" id="editAddonCategory" class="form-select">
                                            <?php foreach ($categories as $cat): ?>
                                            <option value="<?= $cat ?>"><?= $cat ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Billing Type</label>
                                        <select name="billing_type" id="editAddonBilling" class="form-select">
                                            <option value="monthly">Monthly (Recurring)</option>
                                            <option value="one_time">One-time</option>
                                            <option value="per_use">Per Use</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Unit Price (KES) *</label>
                                        <input type="number" name="price" id="editAddonPrice" class="form-control" step="0.01" required>
                                        <small class="text-muted">Price per unit/quantity</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Setup Fee (KES)</label>
                                        <input type="number" name="setup_fee" id="editAddonSetup" class="form-control" step="0.01">
                                    </div>
                                </div>
                                <div id="editBandwidthFields" class="border rounded p-3 mb-3" style="display:none; background:#f8f9fa;">
                                    <h6 class="mb-3"><i class="bi bi-speedometer2 me-1"></i> Bandwidth Settings</h6>
                                    <div class="row">
                                        <div class="col-md-4 mb-2">
                                            <label class="form-label">Download Speed</label>
                                            <input type="number" name="download_speed" id="editDownloadSpeed" class="form-control" min="1">
                                        </div>
                                        <div class="col-md-4 mb-2">
                                            <label class="form-label">Upload Speed</label>
                                            <input type="number" name="upload_speed" id="editUploadSpeed" class="form-control" min="1">
                                        </div>
                                        <div class="col-md-4 mb-2">
                                            <label class="form-label">Unit</label>
                                            <select name="speed_unit" id="editSpeedUnit" class="form-select">
                                                <option value="Mbps">Mbps</option>
                                                <option value="Kbps">Kbps</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" name="is_active" class="form-check-input" id="editAddonActive">
                                    <label class="form-check-label" for="editAddonActive">Active (available for assignment)</label>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <script>
            function toggleBandwidthFields(categorySelect, fieldsId) {
                const cat = categorySelect.value;
                const isInternet = cat.startsWith('Internet');
                document.getElementById(fieldsId).style.display = isInternet ? 'block' : 'none';
            }
            
            // Add modal category change
            document.querySelector('#addAddonModal select[name="category"]').addEventListener('change', function() {
                toggleBandwidthFields(this, 'addBandwidthFields');
            });
            
            // Edit modal category change
            document.getElementById('editAddonCategory').addEventListener('change', function() {
                toggleBandwidthFields(this, 'editBandwidthFields');
            });
            
            function editAddon(addon) {
                document.getElementById('editAddonId').value = addon.id;
                document.getElementById('editAddonName').value = addon.name;
                document.getElementById('editAddonDesc').value = addon.description || '';
                document.getElementById('editAddonCategory').value = addon.category || 'Other';
                document.getElementById('editAddonBilling').value = addon.billing_type || 'monthly';
                document.getElementById('editAddonPrice').value = addon.price;
                document.getElementById('editAddonSetup').value = addon.setup_fee || 0;
                document.getElementById('editAddonActive').checked = addon.is_active == 1;
                document.getElementById('editDownloadSpeed').value = addon.download_speed || '';
                document.getElementById('editUploadSpeed').value = addon.upload_speed || '';
                document.getElementById('editSpeedUnit').value = addon.speed_unit || 'Mbps';
                
                // Show/hide bandwidth fields based on category
                const isInternet = (addon.category || '').startsWith('Internet');
                document.getElementById('editBandwidthFields').style.display = isInternet ? 'block' : 'none';
                
                new bootstrap.Modal(document.getElementById('editAddonModal')).show();
            }
            </script>

            <?php elseif ($view === 'nas'): ?>
            <?php 
            $nasDevices = $radiusBilling->getNASDevices();
            $wireguardService = new \App\WireGuardService($db);
            $vpnPeers = $wireguardService->getAllPeers();
            $ispLocations = $radiusBilling->getLocations();
            $ispSubLocations = $radiusBilling->getAllSubLocations();
            ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="page-title mb-0"><i class="bi bi-hdd-network"></i> NAS Devices (MikroTik Routers)</h4>
                <div class="btn-group">
                    <button class="btn btn-outline-secondary" onclick="syncMikroTikBlocked()" id="syncBlockedBtn">
                        <i class="bi bi-arrow-repeat me-1"></i> Sync Blocked List
                    </button>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addNASModal">
                        <i class="bi bi-plus-lg me-1"></i> Add NAS
                    </button>
                </div>
            </div>
            
            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <?php if (empty($nasDevices)): ?>
                    <div class="p-5 text-center text-muted">
                        <i class="bi bi-hdd-network fs-1 mb-3 d-block"></i>
                        <h5>No NAS Devices</h5>
                        <p>Add your MikroTik routers to enable RADIUS authentication.</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>IP Address</th>
                                    <th>Type</th>
                                    <th>API</th>
                                    <th>VPN</th>
                                    <th>Online</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($nasDevices as $nas): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($nas['name']) ?></strong>
                                        <?php if ($nas['description']): ?>
                                        <br><small class="text-muted"><?= htmlspecialchars($nas['description']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><code><?= htmlspecialchars($nas['ip_address']) ?></code></td>
                                    <td><?= htmlspecialchars($nas['nas_type']) ?></td>
                                    <td>
                                        <?php if ($nas['api_enabled']): ?>
                                        <span class="badge bg-success">Enabled (<?= $nas['api_port'] ?>)</span>
                                        <?php else: ?>
                                        <span class="badge bg-secondary">Disabled</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($nas['vpn_peer_name']): ?>
                                        <span class="badge bg-info" title="<?= htmlspecialchars($nas['vpn_allowed_ips'] ?? '') ?>">
                                            <i class="bi bi-shield-lock me-1"></i><?= htmlspecialchars($nas['vpn_peer_name']) ?>
                                        </span>
                                        <?php else: ?>
                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="createVPNPeer(<?= $nas['id'] ?>, '<?= htmlspecialchars($nas['name']) ?>', '<?= htmlspecialchars($nas['ip_address']) ?>')" title="Create VPN Peer">
                                            <i class="bi bi-plus-circle me-1"></i>Create
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="nas-online-status" data-ip="<?= htmlspecialchars($nas['ip_address']) ?>">
                                            <span class="spinner-border spinner-border-sm text-secondary" role="status"></span>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($nas['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-info" onclick="showMikroTikScript(<?= htmlspecialchars(json_encode($nas)) ?>)" title="MikroTik Script">
                                                <i class="bi bi-terminal"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-success" onclick="testNAS(<?= $nas['id'] ?>, '<?= htmlspecialchars($nas['ip_address']) ?>')" title="Test Connectivity">
                                                <i class="bi bi-lightning"></i>
                                            </button>
                                            <?php if ($nas['api_enabled']): ?>
                                            <button type="button" class="btn btn-outline-warning" onclick="rebootNAS(<?= $nas['id'] ?>, '<?= htmlspecialchars($nas['name']) ?>')" title="Reboot Router">
                                                <i class="bi bi-arrow-clockwise"></i>
                                            </button>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-outline-primary" onclick="editNAS(<?= htmlspecialchars(json_encode($nas)) ?>)" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <form method="post" class="d-inline" onsubmit="return confirm('Delete this NAS device?')">
                                                <input type="hidden" name="action" value="delete_nas">
                                                <input type="hidden" name="id" value="<?= $nas['id'] ?>">
                                                <button type="submit" class="btn btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="modal fade" id="addNASModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="post">
                            <input type="hidden" name="action" value="create_nas">
                            <div class="modal-header">
                                <h5 class="modal-title">Add NAS Device</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">Name</label>
                                    <input type="text" name="name" class="form-control" required placeholder="e.g., Main Router">
                                </div>
                                <div class="row">
                                    <div class="col-md-8 mb-3">
                                        <label class="form-label">IP Address</label>
                                        <input type="text" name="ip_address" class="form-control" required placeholder="e.g., 192.168.1.1">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">RADIUS Port</label>
                                        <input type="number" name="ports" class="form-control" value="1812">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">RADIUS Secret</label>
                                    <div class="input-group">
                                        <input type="password" name="secret" id="add_nas_secret" class="form-control" required>
                                        <button type="button" class="btn btn-outline-secondary" onclick="toggleSecretVisibility('add_nas_secret', this)" title="Show/Hide">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-primary" onclick="generateSecret('add_nas_secret')" title="Generate Secret">
                                            <i class="bi bi-shuffle"></i> Generate
                                        </button>
                                    </div>
                                    <small class="text-muted">Use the same secret on your MikroTik router</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea name="description" class="form-control" rows="2"></textarea>
                                </div>
                                <hr>
                                <h6><i class="bi bi-shield-lock me-1"></i> WireGuard VPN (Optional)</h6>
                                <div class="mb-3">
                                    <label class="form-label">VPN Peer</label>
                                    <select name="wireguard_peer_id" class="form-select">
                                        <option value="">-- No VPN --</option>
                                        <?php foreach ($vpnPeers as $peer): ?>
                                        <option value="<?= $peer['id'] ?>"><?= htmlspecialchars($peer['name']) ?> (<?= htmlspecialchars($peer['allowed_ips']) ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">Link to a VPN peer for remote site access</small>
                                </div>
                                <hr>
                                <h6>MikroTik API (Optional)</h6>
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" name="api_enabled" id="apiEnabled" value="1">
                                    <label class="form-check-label" for="apiEnabled">Enable API Access</label>
                                </div>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">API Port</label>
                                        <input type="number" name="api_port" class="form-control" value="8728">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">API Username</label>
                                        <input type="text" name="api_username" class="form-control">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">API Password</label>
                                        <input type="password" name="api_password" class="form-control">
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Add NAS</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="editNASModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="post">
                            <input type="hidden" name="action" value="update_nas">
                            <input type="hidden" name="id" id="edit_nas_id">
                            <div class="modal-header">
                                <h5 class="modal-title">Edit NAS Device</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">Name</label>
                                    <input type="text" name="name" id="edit_nas_name" class="form-control" required>
                                </div>
                                <div class="row">
                                    <div class="col-md-8 mb-3">
                                        <label class="form-label">IP Address</label>
                                        <input type="text" name="ip_address" id="edit_nas_ip" class="form-control" required>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">RADIUS Port</label>
                                        <input type="number" name="ports" id="edit_nas_ports" class="form-control">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">RADIUS Secret</label>
                                    <div class="input-group">
                                        <input type="password" name="secret" id="edit_nas_secret" class="form-control" placeholder="Leave blank to keep current">
                                        <button type="button" class="btn btn-outline-secondary" onclick="toggleSecretVisibility('edit_nas_secret', this)" title="Show/Hide">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-primary" onclick="generateSecret('edit_nas_secret')" title="Generate Secret">
                                            <i class="bi bi-shuffle"></i> Generate
                                        </button>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea name="description" id="edit_nas_description" class="form-control" rows="2"></textarea>
                                </div>
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="edit_nas_active" value="1">
                                    <label class="form-check-label" for="edit_nas_active">Active</label>
                                </div>
                                <hr>
                                <h6><i class="bi bi-shield-lock me-1"></i> WireGuard VPN</h6>
                                <div class="mb-3">
                                    <label class="form-label">VPN Peer</label>
                                    <select name="wireguard_peer_id" id="edit_nas_vpn_peer" class="form-select">
                                        <option value="">-- No VPN --</option>
                                        <?php foreach ($vpnPeers as $peer): ?>
                                        <option value="<?= $peer['id'] ?>"><?= htmlspecialchars($peer['name']) ?> (<?= htmlspecialchars($peer['allowed_ips']) ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <hr>
                                <h6>MikroTik API (Optional)</h6>
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" name="api_enabled" id="edit_api_enabled" value="1">
                                    <label class="form-check-label" for="edit_api_enabled">Enable API Access</label>
                                </div>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">API Port</label>
                                        <input type="number" name="api_port" id="edit_api_port" class="form-control">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">API Username</label>
                                        <input type="text" name="api_username" id="edit_api_username" class="form-control">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">API Password</label>
                                        <input type="password" name="api_password" class="form-control" placeholder="Leave blank to keep">
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="testNASModal" tabindex="-1">
                <div class="modal-dialog modal-sm">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">NAS Connectivity Test</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body text-center" id="testNASResult">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Testing...</span>
                            </div>
                            <p class="mt-2">Testing connectivity...</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal fade" id="mikrotikScriptModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="bi bi-terminal me-2"></i>MikroTik Configuration Script</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <ul class="nav nav-tabs mb-3" id="scriptTabs">
                                <li class="nav-item">
                                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#radiusTab">RADIUS Config</button>
                                </li>
                                <li class="nav-item">
                                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#vpnTab">WireGuard VPN</button>
                                </li>
                                <li class="nav-item">
                                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#fullTab">Full Script</button>
                                </li>
                            </ul>
                            <div class="tab-content">
                                <div class="tab-pane fade show active" id="radiusTab">
                                    <p class="text-muted small">RADIUS configuration for PPPoE/Hotspot authentication:</p>
                                    <div class="position-relative">
                                        <pre class="bg-dark text-light p-3 rounded" id="radiusScript" style="max-height: 300px; overflow-y: auto; font-size: 12px;"></pre>
                                        <button class="btn btn-sm btn-outline-light position-absolute top-0 end-0 m-2" onclick="copyScript('radiusScript')">
                                            <i class="bi bi-clipboard"></i> Copy
                                        </button>
                                    </div>
                                </div>
                                <div class="tab-pane fade" id="vpnTab">
                                    <p class="text-muted small">WireGuard VPN configuration for secure site-to-site connectivity:</p>
                                    <div class="position-relative">
                                        <pre class="bg-dark text-light p-3 rounded" id="vpnScript" style="max-height: 300px; overflow-y: auto; font-size: 12px;"></pre>
                                        <button class="btn btn-sm btn-outline-light position-absolute top-0 end-0 m-2" onclick="copyScript('vpnScript')">
                                            <i class="bi bi-clipboard"></i> Copy
                                        </button>
                                    </div>
                                </div>
                                <div class="tab-pane fade" id="fullTab">
                                    <p class="text-muted small">Complete configuration script (RADIUS + VPN):</p>
                                    <div class="position-relative">
                                        <pre class="bg-dark text-light p-3 rounded" id="fullScript" style="max-height: 400px; overflow-y: auto; font-size: 12px;"></pre>
                                        <button class="btn btn-sm btn-outline-light position-absolute top-0 end-0 m-2" onclick="copyScript('fullScript')">
                                            <i class="bi bi-clipboard"></i> Copy
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <small class="text-muted me-auto">Paste this script into MikroTik terminal (Winbox > New Terminal)</small>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>

            <?php elseif ($view === 'vouchers'): ?>
            <?php 
            $vouchers = $radiusBilling->getVouchers(['limit' => 100]);
            $packages = $radiusBilling->getPackages('hotspot');
            ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="page-title mb-0"><i class="bi bi-ticket"></i> Hotspot Vouchers</h4>
            </div>
            
            <div class="row">
                <div class="col-lg-4">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Generate Vouchers</h5>
                        </div>
                        <div class="card-body">
                            <form method="post">
                                <input type="hidden" name="action" value="generate_vouchers">
                                <div class="mb-3">
                                    <label class="form-label">Package</label>
                                    <select name="package_id" class="form-select" required>
                                        <option value="">Select Package</option>
                                        <?php foreach ($packages as $pkg): ?>
                                        <option value="<?= $pkg['id'] ?>"><?= htmlspecialchars($pkg['name']) ?> - KES <?= number_format($pkg['price']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Number of Vouchers</label>
                                    <input type="number" name="count" class="form-control" value="10" min="1" max="100" required>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-ticket me-1"></i> Generate Vouchers
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-8">
                    <div class="card shadow-sm">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Vouchers</h5>
                            <span class="badge bg-primary"><?= count($vouchers) ?></span>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($vouchers)): ?>
                            <div class="p-4 text-center text-muted">No vouchers generated yet</div>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Code</th>
                                            <th>Package</th>
                                            <th>Status</th>
                                            <th>Created</th>
                                            <th>Used At</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($vouchers as $v): ?>
                                        <tr>
                                            <td><code class="fs-6"><?= htmlspecialchars($v['code']) ?></code></td>
                                            <td><?= htmlspecialchars($v['package_name']) ?></td>
                                            <td>
                                                <?php
                                                $statusClass = match($v['status']) {
                                                    'unused' => 'success',
                                                    'used' => 'secondary',
                                                    'expired' => 'danger',
                                                    default => 'warning'
                                                };
                                                ?>
                                                <span class="badge bg-<?= $statusClass ?>"><?= ucfirst($v['status']) ?></span>
                                            </td>
                                            <td><?= date('M j', strtotime($v['created_at'])) ?></td>
                                            <td><?= $v['used_at'] ? date('M j, H:i', strtotime($v['used_at'])) : '-' ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php elseif ($view === 'billing'): ?>
            <?php $billing = $radiusBilling->getBillingHistory(null, 50); ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="page-title mb-0"><i class="bi bi-receipt"></i> Billing History</h4>
            </div>
            
            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <?php if (empty($billing)): ?>
                    <div class="p-4 text-center text-muted">No billing records yet</div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Invoice #</th>
                                    <th>Customer</th>
                                    <th>Package</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th>Period</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($billing as $b): ?>
                                <tr>
                                    <td><code><?= htmlspecialchars($b['invoice_number']) ?></code></td>
                                    <td><?= htmlspecialchars($b['customer_name'] ?? $b['username']) ?></td>
                                    <td><?= htmlspecialchars($b['package_name']) ?></td>
                                    <td><span class="badge bg-info"><?= ucfirst($b['billing_type']) ?></span></td>
                                    <td>KES <?= number_format($b['amount']) ?></td>
                                    <td><?= date('M j', strtotime($b['period_start'])) ?> - <?= date('M j', strtotime($b['period_end'])) ?></td>
                                    <td>
                                        <?php
                                        $statusClass = match($b['status']) {
                                            'paid' => 'success',
                                            'pending' => 'warning',
                                            'failed' => 'danger',
                                            default => 'secondary'
                                        };
                                        ?>
                                        <span class="badge bg-<?= $statusClass ?>"><?= ucfirst($b['status']) ?></span>
                                    </td>
                                    <td><?= date('M j, Y', strtotime($b['created_at'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php elseif ($view === 'ip_pools'): ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="page-title mb-0"><i class="bi bi-diagram-2"></i> IP Address Pools</h4>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPoolModal">
                    <i class="bi bi-plus-lg me-1"></i> Add Pool
                </button>
            </div>
            
            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <div class="p-5 text-center text-muted">
                        <i class="bi bi-diagram-2 fs-1 mb-3 d-block"></i>
                        <h5>IP Pool Management</h5>
                        <p>Configure IP address pools for dynamic allocation to subscribers.</p>
                    </div>
                </div>
            </div>

            <?php elseif ($view === 'expiring'): ?>
            <?php $expiringList = $radiusBilling->getExpiringSubscriptions(14); ?>
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-4">
                <h4 class="page-title mb-0"><i class="bi bi-clock-history"></i> Expiring Subscribers</h4>
                <form method="post" class="d-inline">
                    <input type="hidden" name="action" value="send_expiry_alerts">
                    <button type="submit" class="btn btn-warning btn-sm"><i class="bi bi-send me-1"></i> Send Alerts</button>
                </form>
            </div>
            
            <!-- Mobile Summary Cards -->
            <div class="row g-2 mb-3 d-md-none">
                <div class="col-4">
                    <div class="card bg-danger bg-opacity-10 border-0">
                        <div class="card-body text-center py-2">
                            <div class="fw-bold text-danger"><?= count(array_filter($expiringList, fn($s) => (int)$s['days_remaining'] <= 1)) ?></div>
                            <small class="text-muted">Critical</small>
                        </div>
                    </div>
                </div>
                <div class="col-4">
                    <div class="card bg-warning bg-opacity-10 border-0">
                        <div class="card-body text-center py-2">
                            <div class="fw-bold text-warning"><?= count(array_filter($expiringList, fn($s) => (int)$s['days_remaining'] > 1 && (int)$s['days_remaining'] <= 3)) ?></div>
                            <small class="text-muted">Soon</small>
                        </div>
                    </div>
                </div>
                <div class="col-4">
                    <div class="card bg-info bg-opacity-10 border-0">
                        <div class="card-body text-center py-2">
                            <div class="fw-bold text-info"><?= count(array_filter($expiringList, fn($s) => (int)$s['days_remaining'] > 3)) ?></div>
                            <small class="text-muted">Later</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <?php if (empty($expiringList)): ?>
                    <div class="p-4 p-md-5 text-center text-muted">
                        <i class="bi bi-check-circle fs-1 mb-3 d-block text-success"></i>
                        <h5>All Clear!</h5>
                        <p class="mb-0">No subscribers expiring in the next 14 days.</p>
                    </div>
                    <?php else: ?>
                    <!-- Desktop Table -->
                    <div class="table-responsive d-none d-md-block">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Customer</th>
                                    <th>Username</th>
                                    <th>Package</th>
                                    <th>Expiry</th>
                                    <th>Days</th>
                                    <th>Amount</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($expiringList as $sub): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($sub['customer_name'] ?? 'N/A') ?></strong>
                                        <br><small class="text-muted"><?= htmlspecialchars($sub['customer_phone'] ?? '') ?></small>
                                    </td>
                                    <td><code class="small"><?= htmlspecialchars($sub['username']) ?></code></td>
                                    <td><?= htmlspecialchars($sub['package_name'] ?? 'N/A') ?></td>
                                    <td><?= date('M j', strtotime($sub['expiry_date'])) ?></td>
                                    <td>
                                        <?php $days = (int)$sub['days_remaining']; ?>
                                        <span class="badge bg-<?= $days <= 1 ? 'danger' : ($days <= 3 ? 'warning' : 'info') ?>">
                                            <?= $days ?>d
                                        </span>
                                    </td>
                                    <td>KES <?= number_format($sub['package_price'] ?? 0) ?></td>
                                    <td>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="action" value="renew_subscription">
                                            <input type="hidden" name="subscription_id" value="<?= $sub['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-success"><i class="bi bi-arrow-repeat"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <!-- Mobile Card List -->
                    <div class="d-md-none">
                        <?php foreach ($expiringList as $sub): ?>
                        <?php $days = (int)$sub['days_remaining']; ?>
                        <div class="p-3 border-bottom">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <strong><?= htmlspecialchars($sub['customer_name'] ?? 'N/A') ?></strong>
                                    <br><small class="text-muted"><?= htmlspecialchars($sub['customer_phone'] ?? '') ?></small>
                                </div>
                                <span class="badge bg-<?= $days <= 1 ? 'danger' : ($days <= 3 ? 'warning' : 'info') ?>">
                                    <?= $days ?> day<?= $days != 1 ? 's' : '' ?>
                                </span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="text-muted"><?= htmlspecialchars($sub['package_name'] ?? 'N/A') ?></small>
                                    <span class="mx-1">&bull;</span>
                                    <strong class="text-success">KES <?= number_format($sub['package_price'] ?? 0) ?></strong>
                                </div>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="action" value="renew_subscription">
                                    <input type="hidden" name="subscription_id" value="<?= $sub['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-success"><i class="bi bi-arrow-repeat me-1"></i>Renew</button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php elseif ($view === 'reports'): ?>
            <?php 
            $revenueReport = $radiusBilling->getRevenueReport('monthly');
            $packageStats = $radiusBilling->getPackagePopularity();
            $subStats = $radiusBilling->getSubscriptionStats();
            ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="page-title mb-0"><i class="bi bi-graph-up"></i> Revenue Reports</h4>
            </div>
            
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="fs-2 fw-bold text-success"><?= number_format($subStats['active'] ?? 0) ?></div>
                            <div class="text-muted">Active Subscribers</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="fs-2 fw-bold text-warning"><?= number_format($subStats['expiring_week'] ?? 0) ?></div>
                            <div class="text-muted">Expiring This Week</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="fs-2 fw-bold text-danger"><?= number_format($subStats['suspended'] ?? 0) ?></div>
                            <div class="text-muted">Suspended</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="fs-2 fw-bold text-info"><?= number_format($subStats['total'] ?? 0) ?></div>
                            <div class="text-muted">Total Subscribers</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row g-4">
                <div class="col-md-8">
                    <div class="card shadow-sm">
                        <div class="card-header"><i class="bi bi-cash me-2"></i>Monthly Revenue</div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Period</th>
                                            <th>Transactions</th>
                                            <th>Total Revenue</th>
                                            <th>Paid</th>
                                            <th>Pending</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($revenueReport as $row): ?>
                                        <tr>
                                            <td><?= date('F Y', strtotime($row['period'])) ?></td>
                                            <td><?= number_format($row['transactions']) ?></td>
                                            <td><strong>KES <?= number_format($row['total_revenue'] ?? 0) ?></strong></td>
                                            <td class="text-success">KES <?= number_format($row['paid_revenue'] ?? 0) ?></td>
                                            <td class="text-warning">KES <?= number_format($row['pending_revenue'] ?? 0) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($revenueReport)): ?>
                                        <tr><td colspan="5" class="text-center text-muted py-4">No billing data yet</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow-sm">
                        <div class="card-header"><i class="bi bi-box me-2"></i>Package Popularity</div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <?php foreach ($packageStats as $pkg): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?= htmlspecialchars($pkg['name']) ?></strong>
                                        <br><small class="text-muted">KES <?= number_format($pkg['price']) ?>/mo</small>
                                    </div>
                                    <span class="badge bg-primary rounded-pill"><?= $pkg['active_count'] ?> active</span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php elseif ($view === 'analytics'): ?>
            <?php 
            $topUsers = $radiusBilling->getTopUsers(10, 'month');
            $peakHours = $radiusBilling->getPeakHours();
            ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="page-title mb-0"><i class="bi bi-bar-chart"></i> Usage Analytics</h4>
            </div>
            
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="card-header"><i class="bi bi-trophy me-2"></i>Top 10 Users (This Month)</div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>User</th>
                                            <th>Download</th>
                                            <th>Upload</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($topUsers as $i => $user): ?>
                                        <tr>
                                            <td><?= $i + 1 ?></td>
                                            <td>
                                                <strong><?= htmlspecialchars($user['customer_name'] ?? $user['username']) ?></strong>
                                                <br><small class="text-muted"><?= htmlspecialchars($user['username']) ?></small>
                                            </td>
                                            <td><?= number_format($user['download_gb'] ?? 0, 2) ?> GB</td>
                                            <td><?= number_format($user['upload_gb'] ?? 0, 2) ?> GB</td>
                                            <td><strong><?= number_format($user['total_gb'] ?? 0, 2) ?> GB</strong></td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($topUsers)): ?>
                                        <tr><td colspan="5" class="text-center text-muted py-4">No usage data yet</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="card-header"><i class="bi bi-clock me-2"></i>Peak Usage Hours</div>
                        <div class="card-body">
                            <?php if (!empty($peakHours)): ?>
                            <div class="row">
                                <?php foreach ($peakHours as $hour): ?>
                                <div class="col-3 mb-2">
                                    <div class="text-center p-2 rounded" style="background: rgba(14,165,233,<?= min(1, ($hour['session_count'] / max(1, max(array_column($peakHours, 'session_count')))) * 0.5 + 0.1) ?>);">
                                        <div class="fw-bold"><?= str_pad($hour['hour'], 2, '0', STR_PAD_LEFT) ?>:00</div>
                                        <small><?= $hour['session_count'] ?> sessions</small>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                            <p class="text-center text-muted py-4">No session data yet</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php elseif ($view === 'import'): ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="page-title mb-0"><i class="bi bi-upload"></i> Bulk Import Subscribers</h4>
            </div>
            
            <div class="row">
                <div class="col-md-8">
                    <div class="card shadow-sm">
                        <div class="card-header">Upload CSV File</div>
                        <div class="card-body">
                            <form method="post" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="import_csv">
                                <div class="mb-3">
                                    <label class="form-label">CSV File</label>
                                    <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Or paste CSV content</label>
                                    <textarea name="csv_content" class="form-control font-monospace" rows="10" placeholder="customer_id,package_id,username,password,access_type,static_ip,mac_address,notes"></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary"><i class="bi bi-upload me-1"></i> Import</button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow-sm">
                        <div class="card-header">CSV Format</div>
                        <div class="card-body">
                            <p class="small">Required columns:</p>
                            <ul class="small">
                                <li><code>username</code> - PPPoE username</li>
                                <li><code>password</code> - PPPoE password</li>
                            </ul>
                            <p class="small">Optional columns:</p>
                            <ul class="small">
                                <li><code>customer_id</code> - Link to customer</li>
                                <li><code>package_id</code> - Package ID</li>
                                <li><code>access_type</code> - pppoe/hotspot/static/dhcp</li>
                                <li><code>static_ip</code> - Static IP address</li>
                                <li><code>mac_address</code> - MAC binding</li>
                                <li><code>notes</code> - Notes</li>
                            </ul>
                            <hr>
                            <p class="small mb-1"><strong>Example:</strong></p>
                            <code class="small">username,password,package_id<br>user1,pass123,1<br>user2,pass456,2</code>
                        </div>
                    </div>
                </div>
            </div>

            <?php elseif ($view === 'vlans'): ?>
            <?php 
            $vlans = $radiusBilling->getVlans();
            $nasDevices = $radiusBilling->getNASDevices();
            ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="page-title mb-0"><i class="bi bi-diagram-3"></i> VLAN Management</h4>
                <div class="d-flex gap-2 align-items-center">
                    <select class="form-select form-select-sm" id="nasFilter" onchange="filterVlansByNas()" style="width: auto;">
                        <option value="">All NAS Devices</option>
                        <?php foreach ($nasDevices as $nas): ?>
                        <option value="<?= $nas['id'] ?>"><?= htmlspecialchars($nas['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="btn-group">
                        <button class="btn btn-outline-secondary" onclick="importVlans()">
                            <i class="bi bi-download me-1"></i> Import from MikroTik
                        </button>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addVlanModal">
                            <i class="bi bi-plus-lg me-1"></i> Add VLAN
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <?php if (empty($vlans)): ?>
                    <div class="p-5 text-center text-muted">
                        <i class="bi bi-diagram-3 fs-1 mb-3 d-block"></i>
                        <h5>No VLANs Configured</h5>
                        <p>Add VLANs to provision static IPs for subscribers.</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>VLAN</th>
                                    <th>NAS Device</th>
                                    <th>Interface</th>
                                    <th>Network</th>
                                    <th>DHCP Pool</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($vlans as $vlan): ?>
                                <tr data-nas-id="<?= $vlan['nas_id'] ?>">
                                    <td>
                                        <a href="?page=isp&view=vlan_detail&id=<?= $vlan['id'] ?>" class="text-decoration-none">
                                            <strong><?= htmlspecialchars($vlan['name']) ?></strong>
                                        </a>
                                        <br><small class="text-muted">ID: <?= $vlan['vlan_id'] ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($vlan['nas_name'] ?? 'N/A') ?></td>
                                    <td><code><?= htmlspecialchars($vlan['interface']) ?></code></td>
                                    <td>
                                        <?php if ($vlan['network_cidr']): ?>
                                        <code><?= htmlspecialchars($vlan['network_cidr']) ?></code>
                                        <br><small class="text-muted">GW: <?= htmlspecialchars($vlan['gateway_ip']) ?></small>
                                        <?php else: ?>
                                        <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($vlan['dhcp_pool_start'] && $vlan['dhcp_pool_end']): ?>
                                        <small><?= htmlspecialchars($vlan['dhcp_pool_start']) ?> - <?= htmlspecialchars($vlan['dhcp_pool_end']) ?></small>
                                        <?php else: ?>
                                        <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($vlan['is_synced']): ?>
                                        <span class="badge bg-success">Synced</span>
                                        <?php else: ?>
                                        <span class="badge bg-warning">Not Synced</span>
                                        <?php endif; ?>
                                        <?php if (!$vlan['is_active']): ?>
                                        <span class="badge bg-secondary">Disabled</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="?page=isp&view=vlan_detail&id=<?= $vlan['id'] ?>" class="btn btn-outline-info" title="View Details">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <button class="btn btn-outline-primary" onclick="syncVlan(<?= $vlan['id'] ?>)" title="Sync to MikroTik">
                                                <i class="bi bi-arrow-repeat"></i>
                                            </button>
                                            <button class="btn btn-outline-secondary" onclick="editVlan(<?= $vlan['id'] ?>)" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-outline-danger" onclick="deleteVlan(<?= $vlan['id'] ?>, '<?= htmlspecialchars($vlan['name']) ?>')" title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Add VLAN Modal -->
            <div class="modal fade" id="addVlanModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="bi bi-diagram-3 me-2"></i>Add VLAN</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <form id="addVlanForm">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">NAS Device *</label>
                                        <select class="form-select" name="nas_id" required onchange="fetchInterfaces(this.value)">
                                            <option value="">Select NAS...</option>
                                            <?php foreach ($nasDevices as $nas): ?>
                                            <?php if ($nas['api_enabled']): ?>
                                            <option value="<?= $nas['id'] ?>"><?= htmlspecialchars($nas['name']) ?> (<?= $nas['ip_address'] ?>)</option>
                                            <?php endif; ?>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">VLAN Name *</label>
                                        <input type="text" class="form-control" name="name" placeholder="e.g., vlan40-residential" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">VLAN ID *</label>
                                        <input type="number" class="form-control" name="vlan_id" min="1" max="4094" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Parent Interface *</label>
                                        <select class="form-select" name="interface" id="vlanInterface" required>
                                            <option value="">Select interface...</option>
                                            <option value="ether1">ether1</option>
                                            <option value="ether2">ether2</option>
                                            <option value="ether3">ether3</option>
                                            <option value="ether4">ether4</option>
                                            <option value="ether5">ether5</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Lease Time</label>
                                        <input type="text" class="form-control" name="lease_time" value="1d" placeholder="1d">
                                    </div>
                                    <div class="col-12">
                                        <hr>
                                        <h6>Network Configuration</h6>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Gateway IP</label>
                                        <input type="text" class="form-control" name="gateway_ip" placeholder="e.g., 10.40.0.1">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Network CIDR</label>
                                        <input type="text" class="form-control" name="network_cidr" placeholder="e.g., 10.40.0.0/24">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">DHCP Pool Start</label>
                                        <input type="text" class="form-control" name="dhcp_pool_start" placeholder="e.g., 10.40.0.10">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">DHCP Pool End</label>
                                        <input type="text" class="form-control" name="dhcp_pool_end" placeholder="e.g., 10.40.0.254">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">DNS Servers</label>
                                        <input type="text" class="form-control" name="dns_servers" placeholder="e.g., 8.8.8.8,8.8.4.4">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">DHCP Server Name</label>
                                        <input type="text" class="form-control" name="dhcp_server_name" placeholder="Auto-generated if empty">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Description</label>
                                        <textarea class="form-control" name="description" rows="2"></textarea>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-success" onclick="saveVlan(true)">
                                <i class="bi bi-check-lg me-1"></i> Save & Sync to MikroTik
                            </button>
                            <button type="button" class="btn btn-primary" onclick="saveVlan(false)">
                                <i class="bi bi-save me-1"></i> Save Only
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Edit VLAN Modal -->
            <div class="modal fade" id="editVlanModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit VLAN</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <form id="editVlanForm">
                                <input type="hidden" name="id">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">NAS Device</label>
                                        <input type="text" class="form-control" id="editNasName" disabled>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">VLAN Name *</label>
                                        <input type="text" class="form-control" name="name" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">VLAN ID *</label>
                                        <input type="number" class="form-control" name="vlan_id" min="1" max="4094" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Parent Interface *</label>
                                        <input type="text" class="form-control" name="interface" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Lease Time</label>
                                        <input type="text" class="form-control" name="lease_time">
                                    </div>
                                    <div class="col-12"><hr><h6>Network Configuration</h6></div>
                                    <div class="col-md-6">
                                        <label class="form-label">Gateway IP</label>
                                        <input type="text" class="form-control" name="gateway_ip">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Network CIDR</label>
                                        <input type="text" class="form-control" name="network_cidr">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">DHCP Pool Start</label>
                                        <input type="text" class="form-control" name="dhcp_pool_start">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">DHCP Pool End</label>
                                        <input type="text" class="form-control" name="dhcp_pool_end">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">DNS Servers</label>
                                        <input type="text" class="form-control" name="dns_servers">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">DHCP Server Name</label>
                                        <input type="text" class="form-control" name="dhcp_server_name">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Description</label>
                                        <textarea class="form-control" name="description" rows="2"></textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Status</label>
                                        <select class="form-select" name="is_active">
                                            <option value="1">Active</option>
                                            <option value="0">Disabled</option>
                                        </select>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary" onclick="updateVlan()">
                                <i class="bi bi-save me-1"></i> Save Changes
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <script>
            function fetchInterfaces(nasId) {
                if (!nasId) return;
                const select = document.getElementById('vlanInterface');
                select.innerHTML = '<option value="">Loading...</option>';
                
                fetch(`/index.php?page=isp&action=fetch_interfaces&nas_id=${nasId}`)
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            select.innerHTML = '<option value="">Select interface...</option>';
                            data.interfaces.forEach(iface => {
                                if (iface.name && iface.type !== 'vlan') {
                                    const opt = document.createElement('option');
                                    opt.value = iface.name;
                                    opt.textContent = `${iface.name} (${iface.type || 'unknown'})`;
                                    select.appendChild(opt);
                                }
                            });
                        } else {
                            console.log('MikroTik API Debug:', data.debug || 'No debug data');
                            alert('Failed to fetch interfaces: ' + (data.error || 'Unknown error') + '\n\nCheck browser console (F12) for debug data.');
                        }
                    })
                    .catch(err => alert('Error: ' + err.message));
            }
            
            function filterVlansByNas() {
                const nasId = document.getElementById('nasFilter').value;
                const rows = document.querySelectorAll('tbody tr[data-nas-id]');
                rows.forEach(row => {
                    if (!nasId || row.dataset.nasId === nasId) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            }
            
            function importVlans() {
                const nasId = document.getElementById('nasFilter').value;
                const msg = nasId ? 'Import VLANs from selected NAS device?' : 'Import VLANs from all active NAS devices?';
                if (!confirm(msg)) return;
                
                const btn = event.target.closest('button');
                const originalHtml = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Importing...';
                
                let url = '/index.php?page=isp&action=import_vlans';
                if (nasId) url += '&nas_id=' + nasId;
                
                fetch(url)
                    .then(r => r.json())
                    .then(data => {
                        btn.disabled = false;
                        btn.innerHTML = originalHtml;
                        console.log('Import VLANs response:', data);
                        
                        if (data.success) {
                            let msg = `Import completed!\n\nImported: ${data.imported}\nSkipped (existing): ${data.skipped}`;
                            if (data.errors && data.errors.length > 0) {
                                msg += '\n\nWarnings:\n' + data.errors.join('\n');
                            }
                            alert(msg);
                            if (data.imported > 0) {
                                window.location.reload();
                            }
                        } else {
                            alert('Import failed: ' + (data.error || 'Unknown error'));
                        }
                    })
                    .catch(err => {
                        btn.disabled = false;
                        btn.innerHTML = originalHtml;
                        alert('Error: ' + err.message);
                    });
            }
            
            function saveVlan(syncAfter = false) {
                const form = document.getElementById('addVlanForm');
                const formData = new FormData(form);
                const data = Object.fromEntries(formData.entries());
                
                fetch('/index.php?page=isp&action=create_vlan', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                })
                .then(r => r.json())
                .then(result => {
                    console.log('Create VLAN response:', result);
                    if (result.success) {
                        if (syncAfter) {
                            syncVlan(result.id, true);
                        } else {
                            alert('VLAN created successfully!');
                            window.location.reload();
                        }
                    } else {
                        let errorMsg = result.error || '';
                        if (result.errors && result.errors.length > 0) {
                            errorMsg = result.errors.join('\n');
                        }
                        alert('Failed: ' + (errorMsg || 'Unknown error'));
                    }
                })
                .catch(err => alert('Error: ' + err.message));
            }
            
            function syncVlan(id, isNew = false) {
                const btn = event?.target?.closest('button');
                if (btn) {
                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
                }
                
                fetch(`/index.php?page=isp&action=sync_vlan&id=${id}`)
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            let msg = 'Sync completed!\n\n';
                            if (data.results) {
                                msg += `VLAN: ${data.results.vlan ? 'OK' : 'Failed'}\n`;
                                msg += `IP Address: ${data.results.ip ? 'OK' : 'Skipped/Failed'}\n`;
                                msg += `IP Pool: ${data.results.pool ? 'OK' : 'Skipped/Failed'}\n`;
                                msg += `DHCP Network: ${data.results.network ? 'OK' : 'Skipped/Failed'}\n`;
                                msg += `DHCP Server: ${data.results.dhcp ? 'OK' : 'Skipped/Failed'}\n`;
                            }
                            if (data.errors && data.errors.length > 0) {
                                msg += '\nWarnings:\n' + data.errors.join('\n');
                            }
                            alert(msg);
                            window.location.reload();
                        } else {
                            let errorMsg = data.error || '';
                            if (data.errors && data.errors.length > 0) {
                                errorMsg = data.errors.join('\n');
                            }
                            alert('Sync failed: ' + (errorMsg || 'Unknown error'));
                            if (btn) {
                                btn.disabled = false;
                                btn.innerHTML = '<i class="bi bi-arrow-repeat"></i>';
                            }
                        }
                    })
                    .catch(err => {
                        alert('Error: ' + err.message);
                        if (btn) {
                            btn.disabled = false;
                            btn.innerHTML = '<i class="bi bi-arrow-repeat"></i>';
                        }
                    });
            }
            
            function editVlan(id) {
                fetch(`/index.php?page=isp&action=get_vlan&id=${id}`)
                    .then(r => r.json())
                    .then(data => {
                        if (data.success && data.vlan) {
                            const v = data.vlan;
                            const form = document.getElementById('editVlanForm');
                            form.querySelector('[name="id"]').value = v.id;
                            form.querySelector('[name="name"]').value = v.name;
                            form.querySelector('[name="vlan_id"]').value = v.vlan_id;
                            form.querySelector('[name="interface"]').value = v.interface;
                            form.querySelector('[name="gateway_ip"]').value = v.gateway_ip || '';
                            form.querySelector('[name="network_cidr"]').value = v.network_cidr || '';
                            form.querySelector('[name="dhcp_pool_start"]').value = v.dhcp_pool_start || '';
                            form.querySelector('[name="dhcp_pool_end"]').value = v.dhcp_pool_end || '';
                            form.querySelector('[name="dns_servers"]').value = v.dns_servers || '';
                            form.querySelector('[name="dhcp_server_name"]').value = v.dhcp_server_name || '';
                            form.querySelector('[name="lease_time"]').value = v.lease_time || '1d';
                            form.querySelector('[name="description"]').value = v.description || '';
                            form.querySelector('[name="is_active"]').value = v.is_active ? '1' : '0';
                            document.getElementById('editNasName').value = v.nas_name || 'N/A';
                            new bootstrap.Modal(document.getElementById('editVlanModal')).show();
                        } else {
                            alert('VLAN not found');
                        }
                    });
            }
            
            function updateVlan() {
                const form = document.getElementById('editVlanForm');
                const formData = new FormData(form);
                const data = Object.fromEntries(formData.entries());
                data.is_active = data.is_active === '1';
                
                fetch('/index.php?page=isp&action=update_vlan', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                })
                .then(r => r.json())
                .then(result => {
                    if (result.success) {
                        alert('VLAN updated!');
                        window.location.reload();
                    } else {
                        alert('Failed: ' + (result.error || 'Unknown error'));
                    }
                });
            }
            
            function deleteVlan(id, name) {
                if (!confirm(`Delete VLAN "${name}"?\n\nNote: This only removes from CRM database, not from MikroTik.`)) return;
                
                fetch(`/index.php?page=isp&action=delete_vlan&id=${id}`)
                    .then(r => r.json())
                    .then(result => {
                        if (result.success) {
                            window.location.reload();
                        } else {
                            alert('Failed: ' + (result.error || 'Unknown error'));
                        }
                    });
            }
            
            // Live Traffic Monitoring
            let trafficChart = null;
            let trafficInterval = null;
            let lastTrafficData = { rx: 0, tx: 0, time: 0 };
            let trafficHistory = { labels: [], rx: [], tx: [] };
            
            function viewVlanTraffic(vlanId, vlanName) {
                document.getElementById('trafficVlanName').textContent = vlanName;
                document.getElementById('trafficVlanId').value = vlanId;
                
                // Reset data
                lastTrafficData = { rx: 0, tx: 0, time: 0 };
                trafficHistory = { labels: [], rx: [], tx: [] };
                
                // Show modal
                const modal = new bootstrap.Modal(document.getElementById('trafficModal'));
                modal.show();
                
                // Initialize chart
                initTrafficChart();
                
                // Start polling
                fetchVlanTraffic(vlanId);
                trafficInterval = setInterval(() => fetchVlanTraffic(vlanId), 2000);
            }
            
            function initTrafficChart() {
                const ctx = document.getElementById('trafficChart').getContext('2d');
                
                if (trafficChart) {
                    trafficChart.destroy();
                }
                
                trafficChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: [],
                        datasets: [
                            {
                                label: 'Download (Mbps)',
                                data: [],
                                borderColor: 'rgb(75, 192, 192)',
                                backgroundColor: 'rgba(75, 192, 192, 0.1)',
                                fill: true,
                                tension: 0.3
                            },
                            {
                                label: 'Upload (Mbps)',
                                data: [],
                                borderColor: 'rgb(255, 99, 132)',
                                backgroundColor: 'rgba(255, 99, 132, 0.1)',
                                fill: true,
                                tension: 0.3
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: { duration: 0 },
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: { display: true, text: 'Mbps' }
                            },
                            x: {
                                title: { display: true, text: 'Time' }
                            }
                        },
                        plugins: {
                            legend: { position: 'top' }
                        }
                    }
                });
            }
            
            function fetchVlanTraffic(vlanId) {
                fetch(`/index.php?page=isp&action=vlan_traffic&vlan_id=${vlanId}`)
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            updateTrafficDisplay(data.traffic, data.timestamp);
                        } else {
                            document.getElementById('trafficStatus').innerHTML = 
                                `<span class="text-danger"><i class="bi bi-exclamation-triangle"></i> ${data.error}</span>`;
                        }
                    })
                    .catch(err => {
                        console.error('Traffic fetch error:', err);
                    });
            }
            
            function updateTrafficDisplay(traffic, timestamp) {
                const statusEl = document.getElementById('trafficStatus');
                statusEl.innerHTML = traffic.running 
                    ? '<span class="badge bg-success">Interface Running</span>' 
                    : '<span class="badge bg-warning">Interface Down</span>';
                
                // Calculate speeds (bytes delta / time delta * 8 / 1000000 = Mbps)
                let rxMbps = 0, txMbps = 0;
                
                if (lastTrafficData.time > 0) {
                    const timeDelta = (timestamp - lastTrafficData.time) / 1000; // seconds
                    if (timeDelta > 0) {
                        const rxDelta = traffic.rx_byte - lastTrafficData.rx;
                        const txDelta = traffic.tx_byte - lastTrafficData.tx;
                        
                        if (rxDelta >= 0 && txDelta >= 0) {
                            rxMbps = (rxDelta * 8) / (timeDelta * 1000000);
                            txMbps = (txDelta * 8) / (timeDelta * 1000000);
                        }
                    }
                }
                
                lastTrafficData = { rx: traffic.rx_byte, tx: traffic.tx_byte, time: timestamp };
                
                // Update display
                document.getElementById('downloadSpeed').textContent = rxMbps.toFixed(2) + ' Mbps';
                document.getElementById('uploadSpeed').textContent = txMbps.toFixed(2) + ' Mbps';
                document.getElementById('totalDownload').textContent = formatBytes(traffic.rx_byte);
                document.getElementById('totalUpload').textContent = formatBytes(traffic.tx_byte);
                document.getElementById('rxPackets').textContent = traffic.rx_packet.toLocaleString();
                document.getElementById('txPackets').textContent = traffic.tx_packet.toLocaleString();
                
                // Update chart
                const timeLabel = new Date().toLocaleTimeString();
                trafficHistory.labels.push(timeLabel);
                trafficHistory.rx.push(rxMbps);
                trafficHistory.tx.push(txMbps);
                
                // Keep last 30 points
                if (trafficHistory.labels.length > 30) {
                    trafficHistory.labels.shift();
                    trafficHistory.rx.shift();
                    trafficHistory.tx.shift();
                }
                
                if (trafficChart) {
                    trafficChart.data.labels = trafficHistory.labels;
                    trafficChart.data.datasets[0].data = trafficHistory.rx;
                    trafficChart.data.datasets[1].data = trafficHistory.tx;
                    trafficChart.update('none');
                }
            }
            
            function formatBytes(bytes) {
                if (bytes === 0) return '0 B';
                const k = 1024;
                const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
            }
            
            function stopTrafficMonitoring() {
                if (trafficInterval) {
                    clearInterval(trafficInterval);
                    trafficInterval = null;
                }
            }
            
            // Stop monitoring when modal closes
            document.addEventListener('DOMContentLoaded', function() {
                const trafficModal = document.getElementById('trafficModal');
                if (trafficModal) {
                    trafficModal.addEventListener('hidden.bs.modal', stopTrafficMonitoring);
                }
            });
            </script>
            
            <!-- Traffic Monitoring Modal -->
            <div class="modal fade" id="trafficModal" tabindex="-1">
                <div class="modal-dialog modal-xl">
                    <div class="modal-content">
                        <div class="modal-header bg-info text-white">
                            <h5 class="modal-title"><i class="bi bi-graph-up me-2"></i>Live Traffic: <span id="trafficVlanName"></span></h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" id="trafficVlanId">
                            
                            <div class="row g-3 mb-4">
                                <div class="col-md-3">
                                    <div class="card bg-light">
                                        <div class="card-body text-center">
                                            <h6 class="text-muted mb-1"><i class="bi bi-download text-success"></i> Download</h6>
                                            <h3 class="mb-0" id="downloadSpeed">0.00 Mbps</h3>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-light">
                                        <div class="card-body text-center">
                                            <h6 class="text-muted mb-1"><i class="bi bi-upload text-danger"></i> Upload</h6>
                                            <h3 class="mb-0" id="uploadSpeed">0.00 Mbps</h3>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-light">
                                        <div class="card-body text-center">
                                            <h6 class="text-muted mb-1">Total Downloaded</h6>
                                            <h5 class="mb-0" id="totalDownload">0 B</h5>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-light">
                                        <div class="card-body text-center">
                                            <h6 class="text-muted mb-1">Total Uploaded</h6>
                                            <h5 class="mb-0" id="totalUpload">0 B</h5>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row g-3 mb-4">
                                <div class="col-md-4">
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted">RX Packets:</span>
                                        <strong id="rxPackets">0</strong>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted">TX Packets:</span>
                                        <strong id="txPackets">0</strong>
                                    </div>
                                </div>
                                <div class="col-md-4 text-end">
                                    <span id="trafficStatus"><span class="badge bg-secondary">Connecting...</span></span>
                                </div>
                            </div>
                            
                            <div style="height: 300px;">
                                <canvas id="trafficChart"></canvas>
                            </div>
                            
                            <div class="mt-3 text-muted small text-center">
                                <i class="bi bi-info-circle me-1"></i> Traffic data updates every 2 seconds
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>

            <?php elseif ($view === 'vlan_detail'): ?>
            <?php
            $vlanId = (int)($_GET['id'] ?? 0);
            $vlan = $radiusBilling->getVlan($vlanId);
            if (!$vlan) {
                echo '<div class="alert alert-danger">VLAN not found. <a href="?page=isp&view=vlans">Back to VLANs</a></div>';
            } else {
                $nas = $radiusBilling->getNAS($vlan['nas_id']);
                
                // Get subscriptions using this VLAN (via provisioned IPs)
                $stmt = $db->prepare("SELECT DISTINCT s.*, p.name as package_name, mpi.ip_address as provisioned_ip 
                    FROM radius_subscriptions s 
                    LEFT JOIN radius_packages p ON s.package_id = p.id 
                    LEFT JOIN mikrotik_provisioned_ips mpi ON mpi.subscription_id = s.id
                    WHERE mpi.vlan_id = ? ORDER BY s.username");
                $stmt->execute([$vlanId]);
                $vlanSubscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>
            
            <!-- VLAN Detail Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <a href="?page=isp&view=vlans" class="btn btn-outline-secondary btn-sm mb-2">
                        <i class="bi bi-arrow-left me-1"></i> Back to VLANs
                    </a>
                    <h4 class="page-title mb-0">
                        <i class="bi bi-diagram-3"></i> <?= htmlspecialchars($vlan['name']) ?>
                        <span class="badge bg-<?= $vlan['is_synced'] ? 'success' : 'warning' ?> ms-2">
                            <?= $vlan['is_synced'] ? 'Synced' : 'Not Synced' ?>
                        </span>
                        <?php if (!$vlan['is_active']): ?>
                        <span class="badge bg-secondary ms-1">Disabled</span>
                        <?php endif; ?>
                    </h4>
                </div>
                <div class="btn-group">
                    <button class="btn btn-primary" onclick="syncVlanDetail(<?= $vlan['id'] ?>)">
                        <i class="bi bi-arrow-repeat me-1"></i> Sync to MikroTik
                    </button>
                    <button class="btn btn-outline-secondary" onclick="editVlanDetail(<?= $vlan['id'] ?>)">
                        <i class="bi bi-pencil me-1"></i> Edit
                    </button>
                </div>
            </div>
            
            <div class="row g-4">
                <!-- VLAN Info Card -->
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-header bg-primary text-white">
                            <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>VLAN Information</h6>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm table-borderless mb-0">
                                <tr>
                                    <th class="text-muted" style="width:40%">VLAN ID</th>
                                    <td><strong><?= $vlan['vlan_id'] ?></strong></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">Name</th>
                                    <td><code><?= htmlspecialchars($vlan['name']) ?></code></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">Interface</th>
                                    <td><code><?= htmlspecialchars($vlan['interface']) ?></code></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">NAS Device</th>
                                    <td><?= htmlspecialchars($nas['name'] ?? 'N/A') ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">NAS IP</th>
                                    <td><code><?= htmlspecialchars($nas['ip_address'] ?? 'N/A') ?></code></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Network Config Card -->
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-header bg-success text-white">
                            <h6 class="mb-0"><i class="bi bi-hdd-network me-2"></i>Network Configuration</h6>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm table-borderless mb-0">
                                <tr>
                                    <th class="text-muted" style="width:40%">Network</th>
                                    <td><code><?= htmlspecialchars($vlan['network_cidr'] ?: 'Not Set') ?></code></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">Gateway</th>
                                    <td><code><?= htmlspecialchars($vlan['gateway_ip'] ?: 'Not Set') ?></code></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">DHCP Pool</th>
                                    <td>
                                        <?php if ($vlan['dhcp_pool_start'] && $vlan['dhcp_pool_end']): ?>
                                        <code><?= htmlspecialchars($vlan['dhcp_pool_start']) ?></code> -<br>
                                        <code><?= htmlspecialchars($vlan['dhcp_pool_end']) ?></code>
                                        <?php else: ?>
                                        <span class="text-muted">Not Configured</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th class="text-muted">Subscribers</th>
                                    <td><span class="badge bg-info"><?= count($vlanSubscriptions) ?></span></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Live Status Card -->
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0"><i class="bi bi-activity me-2"></i>Live Status</h6>
                        </div>
                        <div class="card-body" id="vlanStatusCard">
                            <div class="text-center py-3">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p class="text-muted mt-2 mb-0">Fetching status...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Live Traffic Monitoring -->
            <div class="card mt-4">
                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-graph-up me-2"></i>Live Traffic Monitor</h6>
                    <div>
                        <span id="trafficStatus" class="badge bg-secondary me-2">Connecting...</span>
                        <button class="btn btn-sm btn-outline-light" id="toggleTrafficBtn" onclick="toggleTrafficMonitor()">
                            <i class="bi bi-pause-fill"></i> Pause
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <div class="card bg-light border-0">
                                <div class="card-body text-center py-3">
                                    <h6 class="text-muted mb-1"><i class="bi bi-download text-success"></i> Download Speed</h6>
                                    <h2 class="mb-0" id="downloadSpeed">0.00 <small class="fs-6">Mbps</small></h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-light border-0">
                                <div class="card-body text-center py-3">
                                    <h6 class="text-muted mb-1"><i class="bi bi-upload text-danger"></i> Upload Speed</h6>
                                    <h2 class="mb-0" id="uploadSpeed">0.00 <small class="fs-6">Mbps</small></h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-light border-0">
                                <div class="card-body text-center py-3">
                                    <h6 class="text-muted mb-1">Total Downloaded</h6>
                                    <h4 class="mb-0" id="totalDownload">0 B</h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-light border-0">
                                <div class="card-body text-center py-3">
                                    <h6 class="text-muted mb-1">Total Uploaded</h6>
                                    <h4 class="mb-0" id="totalUpload">0 B</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <div class="d-flex justify-content-between">
                                <span class="text-muted">RX Packets:</span>
                                <strong id="rxPackets">0</strong>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex justify-content-between">
                                <span class="text-muted">TX Packets:</span>
                                <strong id="txPackets">0</strong>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex justify-content-between">
                                <span class="text-muted">Last Update:</span>
                                <strong id="lastUpdate">-</strong>
                            </div>
                        </div>
                    </div>
                    
                    <div style="height: 300px;">
                        <canvas id="trafficChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Historical Traffic Graph -->
            <div class="card mt-4">
                <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>Historical Traffic</h6>
                    <div class="btn-group btn-group-sm" role="group" id="historyRangeGroup">
                        <button type="button" class="btn btn-outline-light active" data-range="1h">1H</button>
                        <button type="button" class="btn btn-outline-light" data-range="12h">12H</button>
                        <button type="button" class="btn btn-outline-light" data-range="24h">24H</button>
                        <button type="button" class="btn btn-outline-light" data-range="1w">1W</button>
                        <button type="button" class="btn btn-outline-light" data-range="1m">1M</button>
                    </div>
                </div>
                <div class="card-body">
                    <div id="historyLoading" class="text-center py-4">
                        <div class="spinner-border text-secondary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="text-muted mt-2">Loading historical data...</p>
                    </div>
                    <div id="historyNoData" class="text-center py-4 d-none">
                        <i class="bi bi-database-x text-muted" style="font-size: 3rem;"></i>
                        <p class="text-muted mt-2">No historical data available yet.<br>
                        <small>Data is collected every 5 minutes. Check back later.</small></p>
                    </div>
                    <div id="historyChartContainer" style="height: 300px; display: none;">
                        <canvas id="historyChart"></canvas>
                    </div>
                    <div class="mt-3 row g-3" id="historyStats" style="display: none;">
                        <div class="col-md-2">
                            <div class="d-flex justify-content-between">
                                <span class="text-muted">Avg DL:</span>
                                <strong id="avgDownload">-</strong>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="d-flex justify-content-between">
                                <span class="text-muted">Avg UL:</span>
                                <strong id="avgUpload">-</strong>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="d-flex justify-content-between">
                                <span class="text-muted">Peak DL:</span>
                                <strong id="peakDownload">-</strong>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="d-flex justify-content-between">
                                <span class="text-muted">Peak UL:</span>
                                <strong id="peakUpload">-</strong>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="d-flex justify-content-between">
                                <span class="text-muted text-success">95th DL:</span>
                                <strong id="p95Download" class="text-success">-</strong>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="d-flex justify-content-between">
                                <span class="text-muted text-danger">95th UL:</span>
                                <strong id="p95Upload" class="text-danger">-</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Subscribers using this VLAN -->
            <?php if (!empty($vlanSubscriptions)): ?>
            <div class="card mt-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-people me-2"></i>Subscribers on this VLAN (<?= count($vlanSubscriptions) ?>)</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Username</th>
                                    <th>Package</th>
                                    <th>Status</th>
                                    <th>IP Address</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($vlanSubscriptions as $sub): ?>
                                <tr>
                                    <td>
                                        <a href="?page=isp&view=subscriber&id=<?= $sub['id'] ?>" class="text-decoration-none">
                                            <strong><?= htmlspecialchars($sub['username']) ?></strong>
                                        </a>
                                    </td>
                                    <td><?= htmlspecialchars($sub['package_name'] ?? 'N/A') ?></td>
                                    <td>
                                        <span class="badge bg-<?= $sub['status'] === 'active' ? 'success' : ($sub['status'] === 'suspended' ? 'warning' : 'secondary') ?>">
                                            <?= ucfirst($sub['status']) ?>
                                        </span>
                                    </td>
                                    <td><code><?= htmlspecialchars($sub['provisioned_ip'] ?: $sub['static_ip'] ?: '-') ?></code></td>
                                    <td>
                                        <a href="?page=isp&view=subscriber&id=<?= $sub['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <script>
            const currentVlanId = <?= $vlanId ?>;
            let trafficChart = null;
            let trafficInterval = null;
            let trafficPaused = false;
            let lastTrafficData = { rx: 0, tx: 0, time: 0 };
            let trafficHistory = { labels: [], rx: [], tx: [] };
            
            document.addEventListener('DOMContentLoaded', function() {
                initTrafficChart();
                fetchVlanStatus();
                fetchVlanTraffic();
                trafficInterval = setInterval(fetchVlanTraffic, 2000);
            });
            
            function initTrafficChart() {
                const ctx = document.getElementById('trafficChart').getContext('2d');
                trafficChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: [],
                        datasets: [
                            {
                                label: 'Download (Mbps)',
                                data: [],
                                borderColor: 'rgb(75, 192, 192)',
                                backgroundColor: 'rgba(75, 192, 192, 0.1)',
                                fill: true,
                                tension: 0.3,
                                pointRadius: 2
                            },
                            {
                                label: 'Upload (Mbps)',
                                data: [],
                                borderColor: 'rgb(255, 99, 132)',
                                backgroundColor: 'rgba(255, 99, 132, 0.1)',
                                fill: true,
                                tension: 0.3,
                                pointRadius: 2
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: { duration: 0 },
                        interaction: { intersect: false, mode: 'index' },
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: { display: true, text: 'Speed (Mbps)' }
                            },
                            x: {
                                title: { display: true, text: 'Time' }
                            }
                        },
                        plugins: {
                            legend: { position: 'top' }
                        }
                    }
                });
            }
            
            function fetchVlanStatus() {
                fetch(`/index.php?page=isp&action=vlan_status&vlan_id=${currentVlanId}`)
                    .then(r => r.json())
                    .then(data => {
                        const card = document.getElementById('vlanStatusCard');
                        if (data.success) {
                            const s = data.status;
                            card.innerHTML = `
                                <table class="table table-sm table-borderless mb-0">
                                    <tr>
                                        <th class="text-muted" style="width:40%">Interface</th>
                                        <td><code>${s.name}</code></td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted">Status</th>
                                        <td>
                                            <span class="badge bg-${s.running ? 'success' : 'danger'}">
                                                ${s.running ? 'Running' : 'Down'}
                                            </span>
                                            ${s.disabled ? '<span class="badge bg-secondary ms-1">Disabled</span>' : ''}
                                        </td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted">VLAN Tag</th>
                                        <td>${s.vlan_id}</td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted">Parent</th>
                                        <td><code>${s.interface}</code></td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted">MTU</th>
                                        <td>${s.mtu}</td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted">MAC</th>
                                        <td><code class="small">${s.mac_address}</code></td>
                                    </tr>
                                </table>
                            `;
                        } else {
                            card.innerHTML = `<div class="alert alert-warning mb-0">
                                <i class="bi bi-exclamation-triangle me-2"></i>${data.error}
                            </div>`;
                        }
                    })
                    .catch(err => {
                        document.getElementById('vlanStatusCard').innerHTML = 
                            `<div class="alert alert-danger mb-0">Failed to fetch status</div>`;
                    });
            }
            
            function fetchVlanTraffic() {
                if (trafficPaused) return;
                
                fetch(`/index.php?page=isp&action=vlan_traffic&vlan_id=${currentVlanId}`)
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            updateTrafficDisplay(data.traffic, data.timestamp);
                            document.getElementById('trafficStatus').className = 'badge bg-success me-2';
                            document.getElementById('trafficStatus').textContent = 'Live';
                        } else {
                            document.getElementById('trafficStatus').className = 'badge bg-danger me-2';
                            document.getElementById('trafficStatus').textContent = 'Error: ' + data.error;
                        }
                    })
                    .catch(err => {
                        document.getElementById('trafficStatus').className = 'badge bg-danger me-2';
                        document.getElementById('trafficStatus').textContent = 'Connection Error';
                    });
            }
            
            function updateTrafficDisplay(traffic, timestamp) {
                let rxMbps = 0, txMbps = 0;
                
                if (lastTrafficData.time > 0) {
                    const timeDelta = (timestamp - lastTrafficData.time) / 1000;
                    if (timeDelta > 0) {
                        const rxDelta = traffic.rx_byte - lastTrafficData.rx;
                        const txDelta = traffic.tx_byte - lastTrafficData.tx;
                        
                        if (rxDelta >= 0 && txDelta >= 0) {
                            rxMbps = (rxDelta * 8) / (timeDelta * 1000000);
                            txMbps = (txDelta * 8) / (timeDelta * 1000000);
                        }
                    }
                }
                
                lastTrafficData = { rx: traffic.rx_byte, tx: traffic.tx_byte, time: timestamp };
                
                document.getElementById('downloadSpeed').innerHTML = rxMbps.toFixed(2) + ' <small class="fs-6">Mbps</small>';
                document.getElementById('uploadSpeed').innerHTML = txMbps.toFixed(2) + ' <small class="fs-6">Mbps</small>';
                document.getElementById('totalDownload').textContent = formatBytes(traffic.rx_byte);
                document.getElementById('totalUpload').textContent = formatBytes(traffic.tx_byte);
                document.getElementById('rxPackets').textContent = traffic.rx_packet.toLocaleString();
                document.getElementById('txPackets').textContent = traffic.tx_packet.toLocaleString();
                document.getElementById('lastUpdate').textContent = new Date().toLocaleTimeString();
                
                // Update chart
                const timeLabel = new Date().toLocaleTimeString();
                trafficHistory.labels.push(timeLabel);
                trafficHistory.rx.push(rxMbps);
                trafficHistory.tx.push(txMbps);
                
                if (trafficHistory.labels.length > 30) {
                    trafficHistory.labels.shift();
                    trafficHistory.rx.shift();
                    trafficHistory.tx.shift();
                }
                
                trafficChart.data.labels = trafficHistory.labels;
                trafficChart.data.datasets[0].data = trafficHistory.rx;
                trafficChart.data.datasets[1].data = trafficHistory.tx;
                trafficChart.update('none');
            }
            
            function formatBytes(bytes) {
                if (bytes === 0) return '0 B';
                const k = 1024;
                const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
            }
            
            function toggleTrafficMonitor() {
                trafficPaused = !trafficPaused;
                const btn = document.getElementById('toggleTrafficBtn');
                const status = document.getElementById('trafficStatus');
                
                if (trafficPaused) {
                    btn.innerHTML = '<i class="bi bi-play-fill"></i> Resume';
                    status.className = 'badge bg-warning me-2';
                    status.textContent = 'Paused';
                } else {
                    btn.innerHTML = '<i class="bi bi-pause-fill"></i> Pause';
                    fetchVlanTraffic();
                }
            }
            
            function syncVlanDetail(id) {
                if (!confirm('Sync this VLAN configuration to MikroTik?')) return;
                
                fetch(`/index.php?page=isp&action=sync_vlan&id=${id}`)
                    .then(r => r.json())
                    .then(result => {
                        if (result.success) {
                            alert('VLAN synced successfully!');
                            location.reload();
                        } else {
                            alert('Sync failed: ' + (result.error || 'Unknown error'));
                        }
                    });
            }
            
            function editVlanDetail(id) {
                window.location.href = `?page=isp&view=vlans&edit=${id}`;
            }
            
            // Historical Traffic Chart
            let historyChart = null;
            let currentHistoryRange = '1h';
            
            // Calculate percentile (for 95th percentile billing)
            function percentile(arr, p) {
                if (arr.length === 0) return 0;
                const sorted = [...arr].sort((a, b) => a - b);
                const idx = Math.ceil((p / 100) * sorted.length) - 1;
                return sorted[Math.max(0, idx)];
            }
            
            function loadHistoricalData(range) {
                currentHistoryRange = range;
                
                // Update button states
                document.querySelectorAll('#historyRangeGroup button').forEach(btn => {
                    btn.classList.remove('active');
                    if (btn.dataset.range === range) btn.classList.add('active');
                });
                
                document.getElementById('historyLoading').classList.remove('d-none');
                document.getElementById('historyNoData').classList.add('d-none');
                document.getElementById('historyChartContainer').style.display = 'none';
                document.getElementById('historyStats').style.display = 'none';
                
                fetch(`/index.php?page=isp&action=vlan_traffic_history&vlan_id=${currentVlanId}&range=${range}`)
                    .then(r => r.json())
                    .then(data => {
                        document.getElementById('historyLoading').classList.add('d-none');
                        
                        if (data.success && data.data && data.data.length > 0) {
                            renderHistoryChart(data.data);
                            document.getElementById('historyChartContainer').style.display = 'block';
                            document.getElementById('historyStats').style.display = 'flex';
                        } else {
                            document.getElementById('historyNoData').classList.remove('d-none');
                        }
                    })
                    .catch(err => {
                        document.getElementById('historyLoading').classList.add('d-none');
                        document.getElementById('historyNoData').classList.remove('d-none');
                    });
            }
            
            function renderHistoryChart(data) {
                const ctx = document.getElementById('historyChart').getContext('2d');
                
                if (historyChart) historyChart.destroy();
                
                const labels = data.map(d => {
                    const date = new Date(d.recorded_at);
                    if (currentHistoryRange === '1h' || currentHistoryRange === '12h') {
                        return date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                    } else if (currentHistoryRange === '24h') {
                        return date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                    } else {
                        return date.toLocaleDateString([], {month:'short', day:'numeric', hour:'2-digit'});
                    }
                });
                const rxRates = data.map(d => parseFloat(d.rx_rate) || 0);
                const txRates = data.map(d => parseFloat(d.tx_rate) || 0);
                
                historyChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: 'Download (Mbps)',
                                data: rxRates,
                                borderColor: 'rgb(75, 192, 192)',
                                backgroundColor: 'rgba(75, 192, 192, 0.1)',
                                fill: true,
                                tension: 0.3,
                                pointRadius: currentHistoryRange === '1m' ? 0 : 2
                            },
                            {
                                label: 'Upload (Mbps)',
                                data: txRates,
                                borderColor: 'rgb(255, 99, 132)',
                                backgroundColor: 'rgba(255, 99, 132, 0.1)',
                                fill: true,
                                tension: 0.3,
                                pointRadius: currentHistoryRange === '1m' ? 0 : 2
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { intersect: false, mode: 'index' },
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: { display: true, text: 'Speed (Mbps)' }
                            },
                            x: {
                                ticks: { maxTicksLimit: 12 }
                            }
                        },
                        plugins: {
                            legend: { position: 'top' }
                        }
                    }
                });
                
                // Calculate stats
                const avgRx = rxRates.reduce((a, b) => a + b, 0) / rxRates.length;
                const avgTx = txRates.reduce((a, b) => a + b, 0) / txRates.length;
                const peakRx = Math.max(...rxRates);
                const peakTx = Math.max(...txRates);
                
                // Calculate 95th percentile
                const p95Rx = percentile(rxRates, 95);
                const p95Tx = percentile(txRates, 95);
                
                document.getElementById('avgDownload').textContent = avgRx.toFixed(2) + ' Mbps';
                document.getElementById('avgUpload').textContent = avgTx.toFixed(2) + ' Mbps';
                document.getElementById('peakDownload').textContent = peakRx.toFixed(2) + ' Mbps';
                document.getElementById('peakUpload').textContent = peakTx.toFixed(2) + ' Mbps';
                document.getElementById('p95Download').textContent = p95Rx.toFixed(2) + ' Mbps';
                document.getElementById('p95Upload').textContent = p95Tx.toFixed(2) + ' Mbps';
            }
            
            // Setup history range buttons
            document.addEventListener('DOMContentLoaded', function() {
                document.querySelectorAll('#historyRangeGroup button').forEach(btn => {
                    btn.addEventListener('click', () => loadHistoricalData(btn.dataset.range));
                });
                // Load initial history
                loadHistoricalData('1h');
            });
            </script>
            <?php } ?>

            <?php elseif ($view === 'settings'): ?>
            <?php
            $settingsTab = $_GET['tab'] ?? 'notifications';
            ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="page-title mb-0"><i class="bi bi-gear"></i> ISP Settings</h4>
                <button type="button" class="btn btn-outline-primary" onclick="testExpiryReminders()">
                    <i class="bi bi-envelope me-1"></i> Test Reminders
                </button>
            </div>
            
            <ul class="nav nav-tabs mb-4">
                <li class="nav-item">
                    <a class="nav-link <?= $settingsTab === 'notifications' ? 'active' : '' ?>" href="?page=isp&view=settings&tab=notifications">
                        <i class="bi bi-bell me-1"></i> Notifications
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $settingsTab === 'templates' ? 'active' : '' ?>" href="?page=isp&view=settings&tab=templates">
                        <i class="bi bi-file-text me-1"></i> Message Templates
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $settingsTab === 'billing' ? 'active' : '' ?>" href="?page=isp&view=settings&tab=billing">
                        <i class="bi bi-credit-card me-1"></i> Billing
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $settingsTab === 'radius' ? 'active' : '' ?>" href="?page=isp&view=settings&tab=radius">
                        <i class="bi bi-hdd-network me-1"></i> RADIUS
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $settingsTab === 'locations' ? 'active' : '' ?>" href="?page=isp&view=settings&tab=locations">
                        <i class="bi bi-geo-alt me-1"></i> Zones
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $settingsTab === 'bulk_sms' ? 'active' : '' ?>" href="?page=isp&view=settings&tab=bulk_sms">
                        <i class="bi bi-envelope-paper me-1"></i> Bulk SMS
                    </a>
                </li>
            </ul>
            
            <?php if ($settingsTab === 'notifications'): ?>
            <form method="post">
                <input type="hidden" name="action" value="save_isp_settings">
                <input type="hidden" name="category" value="notifications">
                
                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="card shadow-sm">
                            <div class="card-header"><i class="bi bi-clock-history me-2"></i>Expiry Reminders</div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input type="checkbox" class="form-check-input" name="expiry_reminder_enabled" id="expiry_reminder_enabled" value="true" <?= $radiusBilling->getSetting('expiry_reminder_enabled') ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="expiry_reminder_enabled"><strong>Enable Expiry Reminders</strong></label>
                                    </div>
                                    <small class="text-muted">Automatically send reminders before subscriptions expire</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Reminder Days</label>
                                    <input type="text" class="form-control" name="expiry_reminder_days" value="<?= htmlspecialchars($radiusBilling->getSetting('expiry_reminder_days', '3,1,0')) ?>" placeholder="3,1,0">
                                    <small class="text-muted">Days before expiry to send reminders (comma-separated). E.g., "3,1,0" sends at 3 days, 1 day, and on expiry day.</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Reminder Channel</label>
                                    <select name="expiry_reminder_channel" class="form-select">
                                        <option value="sms" <?= $radiusBilling->getSetting('expiry_reminder_channel') === 'sms' ? 'selected' : '' ?>>SMS Only</option>
                                        <option value="whatsapp" <?= $radiusBilling->getSetting('expiry_reminder_channel') === 'whatsapp' ? 'selected' : '' ?>>WhatsApp Only</option>
                                        <option value="both" <?= $radiusBilling->getSetting('expiry_reminder_channel') === 'both' ? 'selected' : '' ?>>Both SMS & WhatsApp</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6">
                        <div class="card shadow-sm">
                            <div class="card-header"><i class="bi bi-check-circle me-2"></i>Confirmation Messages</div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input type="checkbox" class="form-check-input" name="payment_confirmation_enabled" id="payment_confirmation_enabled" value="true" <?= $radiusBilling->getSetting('payment_confirmation_enabled') ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="payment_confirmation_enabled"><strong>Payment Confirmations</strong></label>
                                    </div>
                                    <small class="text-muted">Send confirmation when payment is received</small>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input type="checkbox" class="form-check-input" name="renewal_confirmation_enabled" id="renewal_confirmation_enabled" value="true" <?= $radiusBilling->getSetting('renewal_confirmation_enabled') ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="renewal_confirmation_enabled"><strong>Renewal Confirmations</strong></label>
                                    </div>
                                    <small class="text-muted">Send confirmation when subscription is renewed</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Save Notification Settings</button>
                </div>
            </form>
            
            <?php elseif ($settingsTab === 'templates'): ?>
            <form method="post">
                <input type="hidden" name="action" value="save_isp_settings">
                <input type="hidden" name="category" value="templates">
                
                <div class="alert alert-info mb-4">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Available Variables:</strong> {customer_name}, {package_name}, {days_remaining}, {package_price}, {expiry_date}, {amount}, {transaction_id}, {paybill}, {balance}
                </div>
                
                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="card shadow-sm">
                            <div class="card-header bg-warning bg-opacity-10"><i class="bi bi-exclamation-triangle me-2"></i>Expiry Warning (Days Before)</div>
                            <div class="card-body">
                                <textarea name="template_expiry_warning" class="form-control" rows="4"><?= htmlspecialchars($radiusBilling->getSetting('template_expiry_warning', '')) ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6">
                        <div class="card shadow-sm">
                            <div class="card-header bg-danger bg-opacity-10"><i class="bi bi-clock me-2"></i>Expiry Today</div>
                            <div class="card-body">
                                <textarea name="template_expiry_today" class="form-control" rows="4"><?= htmlspecialchars($radiusBilling->getSetting('template_expiry_today', '')) ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6">
                        <div class="card shadow-sm">
                            <div class="card-header bg-danger bg-opacity-25"><i class="bi bi-x-circle me-2"></i>Expired Notification</div>
                            <div class="card-body">
                                <textarea name="template_expired" class="form-control" rows="4"><?= htmlspecialchars($radiusBilling->getSetting('template_expired', '')) ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6">
                        <div class="card shadow-sm">
                            <div class="card-header bg-success bg-opacity-10"><i class="bi bi-cash-coin me-2"></i>Payment Received</div>
                            <div class="card-body">
                                <textarea name="template_payment_received" class="form-control" rows="4"><?= htmlspecialchars($radiusBilling->getSetting('template_payment_received', '')) ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6">
                        <div class="card shadow-sm">
                            <div class="card-header bg-primary bg-opacity-10"><i class="bi bi-arrow-clockwise me-2"></i>Renewal Success</div>
                            <div class="card-body">
                                <textarea name="template_renewal_success" class="form-control" rows="4"><?= htmlspecialchars($radiusBilling->getSetting('template_renewal_success', '')) ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6">
                        <div class="card shadow-sm">
                            <div class="card-header bg-info bg-opacity-10"><i class="bi bi-wallet2 me-2"></i>Low Balance (Postpaid)</div>
                            <div class="card-body">
                                <textarea name="template_low_balance" class="form-control" rows="4"><?= htmlspecialchars($radiusBilling->getSetting('template_low_balance', '')) ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Save Message Templates</button>
                </div>
            </form>
            
            <?php elseif ($settingsTab === 'billing'): ?>
            <form method="post">
                <input type="hidden" name="action" value="save_isp_settings">
                <input type="hidden" name="category" value="billing">
                
                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="card shadow-sm">
                            <div class="card-header"><i class="bi bi-wallet me-2"></i>Postpaid Settings</div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input type="checkbox" class="form-check-input" name="postpaid_enabled" id="postpaid_enabled" value="true" <?= $radiusBilling->getSetting('postpaid_enabled') ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="postpaid_enabled"><strong>Enable Postpaid Accounts</strong></label>
                                    </div>
                                    <small class="text-muted">Allow customers to use service first and pay later</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Grace Period (Days)</label>
                                    <input type="number" class="form-control" name="postpaid_grace_days" value="<?= $radiusBilling->getSetting('postpaid_grace_days', 7) ?>" min="0" max="30">
                                    <small class="text-muted">Days to continue service after expiry for postpaid accounts</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Default Credit Limit (KES)</label>
                                    <input type="number" class="form-control" name="postpaid_credit_limit" value="<?= $radiusBilling->getSetting('postpaid_credit_limit', 0) ?>" min="0">
                                    <small class="text-muted">Maximum outstanding balance allowed (0 = unlimited)</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6">
                        <div class="card shadow-sm">
                            <div class="card-header"><i class="bi bi-gear me-2"></i>Subscription Settings</div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input type="checkbox" class="form-check-input" name="auto_suspend_expired" id="auto_suspend_expired" value="true" <?= $radiusBilling->getSetting('auto_suspend_expired', true) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="auto_suspend_expired"><strong>Auto-Suspend Expired</strong></label>
                                    </div>
                                    <small class="text-muted">Automatically suspend prepaid subscriptions when they expire</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">M-Pesa Paybill/Till Number</label>
                                    <input type="text" class="form-control" name="mpesa_paybill" value="<?= htmlspecialchars($radiusBilling->getSetting('mpesa_paybill', '')) ?>" placeholder="e.g., 123456">
                                    <small class="text-muted">Displayed in payment reminder messages</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Save Billing Settings</button>
                </div>
            </form>
            
            <?php elseif ($settingsTab === 'radius'): ?>
            <form method="post">
                <input type="hidden" name="action" value="save_isp_settings">
                <input type="hidden" name="category" value="radius">
                
                <div class="row g-4 mb-4">
                    <div class="col-lg-6">
                        <div class="card shadow-sm">
                            <div class="card-header"><i class="bi bi-hdd-network me-2"></i>Expired User IP Pool</div>
                            <div class="card-body">
                                <p class="text-muted small">Configure how expired/quota-exhausted users are handled. When enabled, they get assigned to a restricted IP pool for captive portal access.</p>
                                
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input type="checkbox" class="form-check-input" name="use_expired_pool" id="use_expired_pool" value="true" <?= $radiusBilling->getSetting('use_expired_pool') === 'true' ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="use_expired_pool"><strong>Enable Expired Pool</strong></label>
                                    </div>
                                    <small class="text-muted">Accept expired users but assign to restricted pool (instead of rejecting)</small>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input type="checkbox" class="form-check-input" name="allow_unknown_expired_pool" id="allow_unknown_expired_pool" value="true" <?= $radiusBilling->getSetting('allow_unknown_expired_pool') === 'true' ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="allow_unknown_expired_pool"><strong>Allow Unknown Users</strong></label>
                                    </div>
                                    <small class="text-muted">Accept non-registered accounts and assign to expired pool (redirects to payment portal)</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">ISP Contact Phone</label>
                                    <input type="text" class="form-control" name="isp_contact_phone" value="<?= htmlspecialchars($radiusBilling->getSetting('isp_contact_phone') ?: '') ?>" placeholder="+254712345678">
                                    <small class="text-muted">Displayed on expired page for unknown users to contact support</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Framed-Pool Name</label>
                                    <input type="text" class="form-control" name="expired_ip_pool" value="<?= htmlspecialchars($radiusBilling->getSetting('expired_ip_pool') ?: 'expired-pool') ?>" placeholder="expired-pool">
                                    <small class="text-muted">MikroTik IP pool name for expired users (create this pool in your router)</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Rate Limit for Expired Users</label>
                                    <input type="text" class="form-control" name="expired_rate_limit" value="<?= htmlspecialchars($radiusBilling->getSetting('expired_rate_limit') ?: '256k/256k') ?>" placeholder="256k/256k">
                                    <small class="text-muted">Format: upload/download (e.g., 256k/256k)</small>
                                </div>
                                
                                <div class="alert alert-warning small mb-0">
                                    <i class="bi bi-exclamation-triangle me-1"></i>
                                    <strong>MikroTik Setup:</strong> Create an IP pool named "<code><?= htmlspecialchars($radiusBilling->getSetting('expired_ip_pool') ?: 'expired-pool') ?></code>" and configure web proxy to redirect to your renewal page. Add the renewal page URL to the walled garden.
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6">
                        <div class="card shadow-sm">
                            <div class="card-header"><i class="bi bi-wifi me-2"></i>Hotspot Settings</div>
                            <div class="card-body">
                                <p class="text-muted small">Configure hotspot captive portal and MAC authentication.</p>
                                
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input type="checkbox" class="form-check-input" name="hotspot_mac_auth" id="hotspot_mac_auth" value="true" <?= $radiusBilling->getSetting('hotspot_mac_auth') === 'true' ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="hotspot_mac_auth"><strong>Enable MAC Authentication</strong></label>
                                    </div>
                                    <small class="text-muted">Auto-login returning users by their device MAC address</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">ISP/Hotspot Name</label>
                                    <input type="text" class="form-control" name="isp_name" value="<?= htmlspecialchars($radiusBilling->getSetting('isp_name') ?: '') ?>" placeholder="My WiFi Hotspot">
                                    <small class="text-muted">Displayed on the hotspot login page</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Welcome Message</label>
                                    <input type="text" class="form-control" name="hotspot_welcome" value="<?= htmlspecialchars($radiusBilling->getSetting('hotspot_welcome') ?: '') ?>" placeholder="Welcome! Please login to access the internet.">
                                </div>
                                
                                <div class="alert alert-info small mb-0">
                                    <i class="bi bi-link-45deg me-1"></i>
                                    <strong>Hotspot Login URL:</strong><br>
                                    <code>/hotspot.php</code>
                                    <br><small>Point your MikroTik hotspot login page to this URL</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card shadow-sm mt-4">
                            <div class="card-header"><i class="bi bi-info-circle me-2"></i>RADIUS Server Info</div>
                            <div class="card-body">
                                <div class="alert alert-info mb-3">
                                    <i class="bi bi-info-circle me-2"></i>
                                    Point your NAS devices to this server's IP address.
                                </div>
                                <ul class="list-unstyled mb-0">
                                    <li><strong>Auth Port:</strong> 1812/UDP</li>
                                    <li><strong>Acct Port:</strong> 1813/UDP</li>
                                    <li><strong>CoA Port:</strong> 3799/UDP</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mb-4">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Save RADIUS Settings</button>
                </div>
            </form>
            
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="card-header">NAS Status</div>
                        <div class="card-body p-0">
                            <?php $nasStatus = $radiusBilling->getNASStatus(); ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($nasStatus as $nas): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?= htmlspecialchars($nas['name']) ?></strong>
                                        <br><small class="text-muted"><?= $nas['ip_address'] ?></small>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-<?= $nas['online'] ? 'success' : 'danger' ?>">
                                            <?= $nas['online'] ? 'Online' : 'Offline' ?>
                                        </span>
                                        <?php if ($nas['online'] && $nas['latency_ms']): ?>
                                        <br><small class="text-muted"><?= $nas['latency_ms'] ?>ms</small>
                                        <?php endif; ?>
                                        <br><small><?= $nas['active_sessions'] ?> sessions</small>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <?php if (empty($nasStatus)): ?>
                                <div class="list-group-item text-center text-muted">No NAS devices configured</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php elseif ($settingsTab === 'locations'): ?>
            <?php
            $ispLocations = $radiusBilling->getAllLocations();
            $ispSubLocations = $radiusBilling->getAllSubLocations();
            ?>
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="card shadow-sm">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-geo-alt me-2"></i>Zones</span>
                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addLocationModal">
                                <i class="bi bi-plus-lg"></i> Add Zone
                            </button>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($ispLocations)): ?>
                            <div class="p-4 text-center text-muted">
                                <i class="bi bi-geo-alt fs-3 d-block mb-2"></i>
                                No zones defined. Add your first zone.
                            </div>
                            <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($ispLocations as $loc): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?= htmlspecialchars($loc['name']) ?></strong>
                                        <?php if ($loc['code']): ?>
                                        <span class="badge bg-secondary ms-2"><?= htmlspecialchars($loc['code']) ?></span>
                                        <?php endif; ?>
                                        <?php if ($loc['description']): ?>
                                        <br><small class="text-muted"><?= htmlspecialchars($loc['description']) ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary" onclick="editLocation(<?= htmlspecialchars(json_encode($loc)) ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Delete this zone?')">
                                            <input type="hidden" name="action" value="delete_location">
                                            <input type="hidden" name="id" value="<?= $loc['id'] ?>">
                                            <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6">
                    <div class="card shadow-sm">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-geo me-2"></i>Sub-Zones</span>
                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addSubLocationModal">
                                <i class="bi bi-plus-lg"></i> Add Sub-Zone
                            </button>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($ispSubLocations)): ?>
                            <div class="p-4 text-center text-muted">
                                <i class="bi bi-geo fs-3 d-block mb-2"></i>
                                No sub-zones defined. Create zones first.
                            </div>
                            <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($ispSubLocations as $sub): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="badge bg-primary me-2"><?= htmlspecialchars($sub['location_name']) ?></span>
                                        <strong><?= htmlspecialchars($sub['name']) ?></strong>
                                        <?php if ($sub['code']): ?>
                                        <span class="badge bg-secondary ms-2"><?= htmlspecialchars($sub['code']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary" onclick="editSubLocation(<?= htmlspecialchars(json_encode($sub)) ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Delete this sub-location?')">
                                            <input type="hidden" name="action" value="delete_sub_location">
                                            <input type="hidden" name="id" value="<?= $sub['id'] ?>">
                                            <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal fade" id="addLocationModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="post">
                            <input type="hidden" name="action" value="create_location">
                            <div class="modal-header">
                                <h5 class="modal-title">Add Zone</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">Zone Name <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control" required placeholder="e.g., Zone A - Westlands">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Code</label>
                                    <input type="text" name="code" class="form-control" placeholder="e.g., ZA">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea name="description" class="form-control" rows="2"></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Add Zone</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="modal fade" id="editLocationModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="post">
                            <input type="hidden" name="action" value="update_location">
                            <input type="hidden" name="id" id="edit_location_id">
                            <div class="modal-header">
                                <h5 class="modal-title">Edit Zone</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">Zone Name <span class="text-danger">*</span></label>
                                    <input type="text" name="name" id="edit_location_name" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Code</label>
                                    <input type="text" name="code" id="edit_location_code" class="form-control">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea name="description" id="edit_location_desc" class="form-control" rows="2"></textarea>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" name="is_active" id="edit_location_active" value="1">
                                    <label class="form-check-label" for="edit_location_active">Active</label>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="modal fade" id="addSubLocationModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="post">
                            <input type="hidden" name="action" value="create_sub_location">
                            <div class="modal-header">
                                <h5 class="modal-title">Add Sub-Zone</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">Parent Zone <span class="text-danger">*</span></label>
                                    <select name="location_id" class="form-select" required>
                                        <option value="">-- Select Zone --</option>
                                        <?php foreach ($ispLocations as $loc): ?>
                                        <option value="<?= $loc['id'] ?>"><?= htmlspecialchars($loc['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Sub-Zone Name <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control" required placeholder="e.g., Block A">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Code</label>
                                    <input type="text" name="code" class="form-control" placeholder="e.g., BA">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea name="description" class="form-control" rows="2"></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Add Sub-Zone</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="modal fade" id="editSubLocationModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="post">
                            <input type="hidden" name="action" value="update_sub_location">
                            <input type="hidden" name="id" id="edit_sub_location_id">
                            <div class="modal-header">
                                <h5 class="modal-title">Edit Sub-Zone</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">Parent Zone <span class="text-danger">*</span></label>
                                    <select name="location_id" id="edit_sub_location_parent" class="form-select" required>
                                        <option value="">-- Select Zone --</option>
                                        <?php foreach ($ispLocations as $loc): ?>
                                        <option value="<?= $loc['id'] ?>"><?= htmlspecialchars($loc['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Sub-Zone Name <span class="text-danger">*</span></label>
                                    <input type="text" name="name" id="edit_sub_location_name" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Code</label>
                                    <input type="text" name="code" id="edit_sub_location_code" class="form-control">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea name="description" id="edit_sub_location_desc" class="form-control" rows="2"></textarea>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" name="is_active" id="edit_sub_location_active" value="1">
                                    <label class="form-check-label" for="edit_sub_location_active">Active</label>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <?php elseif ($settingsTab === 'bulk_sms'): ?>
            <?php
            $ispLocations = $radiusBilling->getLocations();
            $packages = $radiusBilling->getPackages();
            ?>
            <div class="card shadow-sm">
                <div class="card-header">
                    <i class="bi bi-envelope-paper me-2"></i>Bulk SMS to Subscribers
                </div>
                <div class="card-body">
                    <form method="post" id="bulkSmsForm">
                        <input type="hidden" name="action" value="send_bulk_sms">
                        
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label class="form-label">Filter by Status</label>
                                <select name="filter_status" class="form-select" id="bulkFilterStatus">
                                    <option value="">All Statuses</option>
                                    <option value="active">Active</option>
                                    <option value="expired">Expired</option>
                                    <option value="suspended">Suspended</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Filter by Zone</label>
                                <select name="filter_location" class="form-select" id="bulkFilterLocation">
                                    <option value="">All Zones</option>
                                    <?php foreach ($ispLocations as $loc): ?>
                                    <option value="<?= $loc['id'] ?>"><?= htmlspecialchars($loc['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Filter by Package</label>
                                <select name="filter_package" class="form-select" id="bulkFilterPackage">
                                    <option value="">All Packages</option>
                                    <?php foreach ($packages as $p): ?>
                                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <button type="button" class="btn btn-outline-primary" onclick="previewRecipients()">
                                <i class="bi bi-eye me-1"></i> Preview Recipients
                            </button>
                            <span id="recipientCount" class="ms-3 text-muted"></span>
                        </div>
                        
                        <div id="recipientPreview" class="mb-3" style="display: none;">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6>Selected Recipients:</h6>
                                    <div id="recipientList" style="max-height: 200px; overflow-y: auto;"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Message <span class="text-danger">*</span></label>
                            <textarea name="message" class="form-control" rows="4" required placeholder="Type your message here..."></textarea>
                            <div class="d-flex justify-content-between mt-2">
                                <small class="text-muted">Variables: {customer_name}, {username}, {package_name}, {expiry_date}, {balance}</small>
                                <small class="text-muted"><span id="charCount">0</span>/160 characters</small>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Send Via</label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="send_via" id="sendSms" value="sms" checked>
                                <label class="btn btn-outline-primary" for="sendSms"><i class="bi bi-chat-dots me-1"></i> SMS</label>
                                <input type="radio" class="btn-check" name="send_via" id="sendWhatsapp" value="whatsapp">
                                <label class="btn btn-outline-success" for="sendWhatsapp"><i class="bi bi-whatsapp me-1"></i> WhatsApp</label>
                                <input type="radio" class="btn-check" name="send_via" id="sendBoth" value="both">
                                <label class="btn btn-outline-info" for="sendBoth"><i class="bi bi-send me-1"></i> Both</label>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-lg" onclick="return confirm('Send bulk message to selected subscribers?')">
                            <i class="bi bi-send-fill me-1"></i> Send Bulk Message
                        </button>
                    </form>
                </div>
            </div>
            
            <script>
            document.querySelector('textarea[name="message"]').addEventListener('input', function() {
                document.getElementById('charCount').textContent = this.value.length;
            });
            
            function previewRecipients() {
                const status = document.getElementById('bulkFilterStatus').value;
                const location = document.getElementById('bulkFilterLocation').value;
                const pkg = document.getElementById('bulkFilterPackage').value;
                
                fetch(`/index.php?page=isp&action=preview_bulk_sms&status=${status}&location=${location}&package=${pkg}`)
                    .then(r => r.json())
                    .then(data => {
                        document.getElementById('recipientCount').textContent = `${data.count} subscribers selected`;
                        
                        if (data.subscribers && data.subscribers.length > 0) {
                            let html = '<table class="table table-sm table-striped mb-0"><thead><tr><th>Customer</th><th>Phone</th><th>Package</th><th>Status</th></tr></thead><tbody>';
                            data.subscribers.slice(0, 50).forEach(s => {
                                html += `<tr><td>${s.customer_name || 'N/A'}</td><td>${s.customer_phone || 'N/A'}</td><td>${s.package_name || 'N/A'}</td><td>${s.status}</td></tr>`;
                            });
                            if (data.count > 50) {
                                html += `<tr><td colspan="4" class="text-center text-muted">...and ${data.count - 50} more</td></tr>`;
                            }
                            html += '</tbody></table>';
                            document.getElementById('recipientList').innerHTML = html;
                            document.getElementById('recipientPreview').style.display = 'block';
                        }
                    })
                    .catch(err => {
                        console.error('Preview error:', err);
                        document.getElementById('recipientCount').textContent = 'Error loading preview';
                    });
            }
            </script>
            <?php endif; ?>
            
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function regeneratePassword() {
        const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
        let password = '';
        for (let i = 0; i < 8; i++) {
            password += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        document.getElementById('pppoe_password').value = password;
    }
    
    function togglePasswordVisibility() {
        const passwordInput = document.getElementById('pppoe_password');
        const icon = document.getElementById('passwordToggleIcon');
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
        } else {
            passwordInput.type = 'password';
            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
        }
    }
    
    function toggleStaticFields() {
        const accessType = document.getElementById('access_type').value;
        const staticIpField = document.getElementById('static_ip_field');
        const nasField = document.getElementById('nas_field');
        const staticIpInput = document.getElementById('static_ip_input');
        const nasSelect = document.getElementById('nas_id_select');
        
        if (accessType === 'static') {
            staticIpField.style.display = 'block';
            nasField.style.display = 'block';
            staticIpInput.required = true;
            nasSelect.required = true;
        } else {
            staticIpField.style.display = 'none';
            nasField.style.display = 'none';
            staticIpInput.required = false;
            nasSelect.required = false;
            staticIpInput.value = '';
            nasSelect.value = '';
        }
    }
    
    function editNAS(nas) {
        document.getElementById('edit_nas_id').value = nas.id;
        document.getElementById('edit_nas_name').value = nas.name;
        document.getElementById('edit_nas_ip').value = nas.ip_address;
        document.getElementById('edit_nas_ports').value = nas.ports;
        document.getElementById('edit_nas_description').value = nas.description || '';
        document.getElementById('edit_nas_active').checked = nas.is_active == 1;
        document.getElementById('edit_api_enabled').checked = nas.api_enabled == 1;
        document.getElementById('edit_api_port').value = nas.api_port || 8728;
        document.getElementById('edit_api_username').value = nas.api_username || '';
        document.getElementById('edit_nas_vpn_peer').value = nas.wireguard_peer_id || '';
        new bootstrap.Modal(document.getElementById('editNASModal')).show();
    }
    
    function filterSubLocations(locationSelect, subLocationSelectId, selectedValue = '') {
        const locationId = locationSelect.value;
        const subSelect = document.getElementById(subLocationSelectId);
        const options = subSelect.querySelectorAll('option');
        
        options.forEach(opt => {
            if (opt.value === '') {
                opt.style.display = '';
            } else if (!locationId || opt.dataset.location === locationId) {
                opt.style.display = '';
            } else {
                opt.style.display = 'none';
            }
        });
        
        if (selectedValue) {
            subSelect.value = selectedValue;
        } else if (locationId) {
            subSelect.value = '';
        }
    }
    
    function editLocation(loc) {
        document.getElementById('edit_location_id').value = loc.id;
        document.getElementById('edit_location_name').value = loc.name;
        document.getElementById('edit_location_code').value = loc.code || '';
        document.getElementById('edit_location_desc').value = loc.description || '';
        document.getElementById('edit_location_active').checked = loc.is_active == true;
        new bootstrap.Modal(document.getElementById('editLocationModal')).show();
    }
    
    function editSubLocation(sub) {
        document.getElementById('edit_sub_location_id').value = sub.id;
        document.getElementById('edit_sub_location_parent').value = sub.location_id;
        document.getElementById('edit_sub_location_name').value = sub.name;
        document.getElementById('edit_sub_location_code').value = sub.code || '';
        document.getElementById('edit_sub_location_desc').value = sub.description || '';
        document.getElementById('edit_sub_location_active').checked = sub.is_active == true;
        new bootstrap.Modal(document.getElementById('editSubLocationModal')).show();
    }
    
    function generateSecret(inputId) {
        const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789!@#$%';
        let secret = '';
        for (let i = 0; i < 16; i++) {
            secret += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        const input = document.getElementById(inputId);
        input.value = secret;
        input.type = 'text';
        const btn = input.nextElementSibling;
        if (btn) {
            const icon = btn.querySelector('i');
            if (icon) {
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            }
        }
    }
    
    function toggleSecretVisibility(inputId, btn) {
        const input = document.getElementById(inputId);
        const icon = btn.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
        }
    }
    
    function showMikroTikScript(nas) {
        const radiusSecret = nas.secret || 'YOUR_SECRET_HERE';
        // Use VPN server address if linked, otherwise use env or fallback
        let radiusServer = '<?= $_ENV['RADIUS_SERVER_IP'] ?? '' ?>';
        
        // Default script while loading
        let radiusScript = '# Loading RADIUS configuration...';
        let vpnScript = '# No VPN configured for this NAS device\n# Link a VPN peer to this NAS to generate WireGuard configuration';
        
        document.getElementById('radiusScript').textContent = radiusScript;
        document.getElementById('vpnScript').textContent = vpnScript;
        document.getElementById('fullScript').textContent = radiusScript + '\n\n' + vpnScript;
        
        // Fetch NAS data with VPN info to get proper RADIUS server IP
        fetch('/index.php?page=isp&action=get_nas_config&id=' + nas.id)
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    radiusServer = data.radius_server || radiusServer || '<?= gethostbyname(gethostname()) ?>';
                    const secret = data.secret || radiusSecret;
                    
                    const expiredPoolName = '<?= $radiusBilling->getSetting('expired_ip_pool') ?: 'expired-pool' ?>';
                    const expiredPageUrl = '<?= rtrim($_ENV['APP_URL'] ?? 'https://your-crm-domain.com', '/') ?>/expired.php';
                    
                    radiusScript = `# ============================================
# RADIUS Configuration for ${nas.name}
# Generated: ${new Date().toLocaleString()}
# ============================================

# ---- RADIUS SERVER SETUP ----
# Add RADIUS server for authentication
/radius add service=ppp address=${radiusServer} secret="${secret}" timeout=3000ms

# Add RADIUS server for hotspot (if needed)
/radius add service=hotspot address=${radiusServer} secret="${secret}" timeout=3000ms

# Enable RADIUS for PPPoE
/ppp aaa set use-radius=yes accounting=yes interim-update=5m

# IMPORTANT: Enable RADIUS Incoming for CoA/Disconnect
# This allows the server to disconnect users or change their speed
/radius incoming set accept=yes port=3799

# Optional: Set NAS identifier
/system identity set name="${nas.name}"

# ============================================
# EXPIRED POOL REDIRECT CONFIGURATION
# ============================================
# Create IP pool for expired/unknown users
/ip pool add name=${expiredPoolName} ranges=10.255.255.2-10.255.255.254

# Create address list for expired users
/ip firewall address-list remove [find list=expired-users]

# NAT rule to redirect HTTP traffic from expired pool to payment page
/ip firewall nat add chain=dstnat src-address=10.255.255.0/24 dst-port=80 protocol=tcp action=dst-nat to-addresses=${radiusServer.split(':')[0]} to-ports=5000 comment="Redirect expired users to payment page"

# NAT rule to redirect HTTPS (falls back to HTTP redirect page)
/ip firewall nat add chain=dstnat src-address=10.255.255.0/24 dst-port=443 protocol=tcp action=dst-nat to-addresses=${radiusServer.split(':')[0]} to-ports=5000 comment="Redirect expired users HTTPS"

# Allow expired pool to access DNS (important!)
/ip firewall filter add chain=forward src-address=10.255.255.0/24 dst-port=53 protocol=udp action=accept comment="Allow expired users DNS"
/ip firewall filter add chain=forward src-address=10.255.255.0/24 dst-port=53 protocol=tcp action=accept comment="Allow expired users DNS"

# Allow expired pool to access CRM server only
/ip firewall filter add chain=forward src-address=10.255.255.0/24 dst-address=${radiusServer} action=accept comment="Allow expired users to CRM"

# Block all other traffic from expired pool
/ip firewall filter add chain=forward src-address=10.255.255.0/24 action=drop comment="Block expired users internet"

# ============================================
# PPP PROFILE (Create or update)
# ============================================
# Note: RADIUS will return Framed-Pool attribute to assign users to expired-pool
# Your default PPP profile should NOT have a fixed remote-address if using RADIUS pools
# /ppp profile set [find name=default] remote-address=""

# ============================================
# WALLED GARDEN (for Hotspot only)
# ============================================
# If using Hotspot, add walled garden entries:
# /ip hotspot walled-garden add dst-host=*your-crm-domain.com* action=allow
# /ip hotspot walled-garden ip add dst-address=${radiusServer} action=accept
`;
                    document.getElementById('radiusScript').textContent = radiusScript;
                    document.getElementById('fullScript').textContent = radiusScript + '\n\n' + vpnScript;
                    
                    // If VPN script is available, update full script
                    if (data.vpn_script) {
                        vpnScript = data.vpn_script;
                        document.getElementById('vpnScript').textContent = vpnScript;
                        document.getElementById('fullScript').textContent = radiusScript + '\n\n' + vpnScript;
                    }
                }
            })
            .catch(err => {
                console.error('Failed to fetch NAS config:', err);
                // Fallback to basic script
                radiusScript = `# RADIUS Configuration for ${nas.name}
# Generated: ${new Date().toLocaleString()}
# Note: Could not fetch full config, using defaults

/radius add service=ppp address=${radiusServer || 'RADIUS_SERVER_IP'} secret="${radiusSecret}" timeout=3000ms
/ppp aaa set use-radius=yes accounting=yes interim-update=5m

# IMPORTANT: Enable RADIUS Incoming for CoA/Disconnect
/radius incoming set accept=yes port=3799

# ============================================
# EXPIRED POOL REDIRECT (Update IPs as needed)
# ============================================
/ip pool add name=expired-pool ranges=10.255.255.2-10.255.255.254
/ip firewall nat add chain=dstnat src-address=10.255.255.0/24 dst-port=80 protocol=tcp action=dst-nat to-addresses=YOUR_CRM_IP to-ports=5000 comment="Redirect expired to payment"
/ip firewall nat add chain=dstnat src-address=10.255.255.0/24 dst-port=443 protocol=tcp action=dst-nat to-addresses=YOUR_CRM_IP to-ports=5000 comment="Redirect expired HTTPS"
/ip firewall filter add chain=forward src-address=10.255.255.0/24 dst-port=53 protocol=udp action=accept comment="Allow DNS"
/ip firewall filter add chain=forward src-address=10.255.255.0/24 dst-address=YOUR_CRM_IP action=accept comment="Allow CRM"
/ip firewall filter add chain=forward src-address=10.255.255.0/24 action=drop comment="Block expired internet"
`;
                document.getElementById('radiusScript').textContent = radiusScript;
                document.getElementById('fullScript').textContent = radiusScript + '\n\n' + vpnScript;
            });
        
        new bootstrap.Modal(document.getElementById('mikrotikScriptModal')).show();
    }
    
    function copyScript(elementId) {
        const text = document.getElementById(elementId).textContent;
        navigator.clipboard.writeText(text).then(() => {
            const btn = event.target.closest('button');
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-check"></i> Copied!';
            setTimeout(() => { btn.innerHTML = originalHtml; }, 2000);
        });
    }
    
    function checkNASOnlineStatus() {
        document.querySelectorAll('.nas-online-status').forEach(el => {
            const ip = el.dataset.ip;
            if (!ip) return;
            
            fetch('/index.php?page=isp&action=ping_nas&ip=' + encodeURIComponent(ip))
                .then(r => r.json())
                .then(data => {
                    if (data.online) {
                        el.innerHTML = '<span class="badge bg-success"><i class="bi bi-circle-fill me-1"></i>Online</span>';
                    } else {
                        el.innerHTML = '<span class="badge bg-danger"><i class="bi bi-circle-fill me-1"></i>Offline</span>';
                    }
                })
                .catch(() => {
                    el.innerHTML = '<span class="badge bg-warning"><i class="bi bi-question-circle me-1"></i>Unknown</span>';
                });
        });
    }
    
    // Check NAS status on page load for NAS view
    if (document.querySelector('.nas-online-status')) {
        checkNASOnlineStatus();
        // Refresh every 30 seconds
        setInterval(checkNASOnlineStatus, 30000);
    }
    
    function testExpiryReminders() {
        if (!confirm('Send expiry reminders to all eligible subscribers now?')) return;
        
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '?page=isp&view=settings&tab=notifications';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'test_expiry_reminders';
        form.appendChild(actionInput);
        
        document.body.appendChild(form);
        form.submit();
    }
    
    function createVPNPeer(nasId, nasName, nasIp) {
        if (!confirm(`Create VPN peer for ${nasName} (${nasIp})?\n\nThis will auto-assign the next available VPN IP.`)) {
            return;
        }
        
        const btn = event.target.closest('button');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        
        fetch('/index.php?page=api&action=create_nas_vpn_peer', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ nas_id: nasId, name: nasName, ip: nasIp })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert(`VPN peer created!\n\nAssigned IP: ${data.allowed_ips}\n\nReloading page...`);
                window.location.reload();
            } else {
                alert('Failed to create VPN peer: ' + (data.error || 'Unknown error'));
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-plus-circle me-1"></i>Create';
            }
        })
        .catch(err => {
            alert('Error: ' + err.message);
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-plus-circle me-1"></i>Create';
        });
    }
    
    function syncMikroTikBlocked(nasId = null) {
        const btn = document.getElementById('syncBlockedBtn');
        const originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Syncing...';
        
        let url = '/index.php?page=isp&action=sync_mikrotik_blocked';
        if (nasId) url += '&nas_id=' + nasId;
        
        fetch(url)
            .then(r => r.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
                
                if (data.success) {
                    let msg = `Sync completed!\n\nBlocked IPs: ${data.blocked_count}\n`;
                    if (data.results) {
                        for (const [nasName, result] of Object.entries(data.results)) {
                            if (result.success) {
                                msg += `\n${nasName}: Added ${result.added}, Removed ${result.removed}`;
                                if (result.errors > 0) msg += ` (${result.errors} errors)`;
                            } else {
                                msg += `\n${nasName}: Failed - ${result.error}`;
                            }
                        }
                    }
                    alert(msg);
                } else {
                    alert('Sync failed: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
                alert('Error: ' + err.message);
            });
    }
    
    function testNAS(nasId, ipAddress) {
        const modal = new bootstrap.Modal(document.getElementById('testNASModal'));
        const resultDiv = document.getElementById('testNASResult');
        
        resultDiv.innerHTML = `
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Testing...</span>
            </div>
            <p class="mt-2">Testing connectivity to ${ipAddress}...</p>
        `;
        modal.show();
        
        fetch('/index.php?page=isp&action=test_nas&id=' + nasId)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.online) {
                    let apiStatus = data.api_online 
                        ? `<span class="text-success"><i class="bi bi-check-circle me-1"></i>Online (${data.api_latency_ms} ms)</span>`
                        : `<span class="text-danger"><i class="bi bi-x-circle me-1"></i>Offline</span>`;
                    
                    resultDiv.innerHTML = `
                        <i class="bi bi-check-circle-fill text-success fs-1"></i>
                        <h5 class="mt-3 text-success">Device Reachable</h5>
                        <table class="table table-sm mt-3 mb-0 text-start">
                            <tr><td><strong>Network:</strong></td><td><span class="text-success"><i class="bi bi-check-circle me-1"></i>Reachable</span></td></tr>
                            <tr><td><strong>Latency:</strong></td><td>${data.latency_ms} ms (port ${data.reachable_port})</td></tr>
                            <tr><td><strong>API Status:</strong></td><td>${apiStatus}</td></tr>
                        </table>
                    `;
                } else {
                    resultDiv.innerHTML = `
                        <i class="bi bi-x-circle-fill text-danger fs-1"></i>
                        <h5 class="mt-3 text-danger">Unreachable</h5>
                        <p class="mb-0">${data.error || 'Could not reach the device on any port'}</p>
                    `;
                }
            })
            .catch(error => {
                resultDiv.innerHTML = `
                    <i class="bi bi-exclamation-triangle-fill text-warning fs-1"></i>
                    <h5 class="mt-3 text-warning">Error</h5>
                    <p class="mb-0">Failed to test connectivity</p>
                `;
            });
    }
    
    function rebootNAS(nasId, nasName) {
        if (!confirm('Are you sure you want to reboot ' + nasName + '?\n\nThis will disconnect all active users temporarily.')) {
            return;
        }
        
        const modal = new bootstrap.Modal(document.getElementById('testNASModal'));
        const resultDiv = document.getElementById('testNASResult');
        
        resultDiv.innerHTML = `
            <div class="spinner-border text-warning" role="status">
                <span class="visually-hidden">Rebooting...</span>
            </div>
            <p class="mt-2">Sending reboot command to ${nasName}...</p>
        `;
        modal.show();
        
        fetch('/index.php?page=isp&action=reboot_nas&id=' + nasId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resultDiv.innerHTML = `
                        <i class="bi bi-check-circle-fill text-success fs-1"></i>
                        <h5 class="mt-3 text-success">Reboot Command Sent</h5>
                        <p class="mb-0">${nasName} is rebooting. It may take 1-2 minutes to come back online.</p>
                    `;
                } else {
                    resultDiv.innerHTML = `
                        <i class="bi bi-x-circle-fill text-danger fs-1"></i>
                        <h5 class="mt-3 text-danger">Reboot Failed</h5>
                        <p class="mb-0">${data.error || 'Failed to send reboot command'}</p>
                    `;
                }
            })
            .catch(error => {
                resultDiv.innerHTML = `
                    <i class="bi bi-exclamation-triangle-fill text-warning fs-1"></i>
                    <h5 class="mt-3 text-warning">Error</h5>
                    <p class="mb-0">Failed to communicate with the server</p>
                `;
            });
    }
    
    function toggleCustomerMode() {
        const isNew = document.getElementById('newCustomer').checked;
        const existingSection = document.getElementById('existingCustomerSection');
        const newSection = document.getElementById('newCustomerSection');
        const customerSelect = document.getElementById('customerSelect');
        const newCustomerName = document.getElementById('newCustomerName');
        const newCustomerPhone = document.getElementById('newCustomerPhone');
        
        if (isNew) {
            existingSection.style.display = 'none';
            newSection.style.display = 'block';
            customerSelect.removeAttribute('required');
            newCustomerName.setAttribute('required', 'required');
            newCustomerPhone.setAttribute('required', 'required');
        } else {
            existingSection.style.display = 'block';
            newSection.style.display = 'none';
            customerSelect.setAttribute('required', 'required');
            newCustomerName.removeAttribute('required');
            newCustomerPhone.removeAttribute('required');
        }
    }
    
    function toggleSelectAll(checkbox) {
        const checkboxes = document.querySelectorAll('.sub-checkbox');
        checkboxes.forEach(cb => cb.checked = checkbox.checked);
        updateBulkCount();
    }
    
    function updateBulkCount() {
        const checked = document.querySelectorAll('.sub-checkbox:checked');
        document.getElementById('selectedCount').textContent = checked.length;
    }
    
    function sendQuickSMS(phone, name) {
        const message = prompt(`Send SMS to ${name} (${phone}):\n\nEnter message:`);
        if (message && message.trim()) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '?page=sms&action=quick_send';
            
            const phoneInput = document.createElement('input');
            phoneInput.type = 'hidden';
            phoneInput.name = 'phone';
            phoneInput.value = phone;
            form.appendChild(phoneInput);
            
            const messageInput = document.createElement('input');
            messageInput.type = 'hidden';
            messageInput.name = 'message';
            messageInput.value = message;
            form.appendChild(messageInput);
            
            const returnInput = document.createElement('input');
            returnInput.type = 'hidden';
            returnInput.name = 'return_url';
            returnInput.value = window.location.href;
            form.appendChild(returnInput);
            
            document.body.appendChild(form);
            form.submit();
        }
    }
    
    function bulkAction(action) {
        const checked = document.querySelectorAll('.sub-checkbox:checked');
        if (checked.length === 0) {
            alert('Please select at least one subscriber');
            return;
        }
        
        const ids = Array.from(checked).map(cb => cb.value);
        const confirmMessages = {
            'activate': `Activate ${ids.length} subscriber(s)?`,
            'suspend': `Suspend ${ids.length} subscriber(s)?`,
            'renew': `Renew ${ids.length} subscriber(s) for another period?`,
            'send_sms': `Send SMS to ${ids.length} subscriber(s)?`
        };
        
        if (!confirm(confirmMessages[action] || `Perform ${action} on ${ids.length} subscriber(s)?`)) {
            return;
        }
        
        document.getElementById('bulkActionType').value = 'bulk_' + action;
        document.getElementById('bulkIds').value = ids.join(',');
        document.getElementById('bulkActionForm').submit();
    }
    
    function pingSubscriber(subId, username) {
        const modal = new bootstrap.Modal(document.getElementById('testNASModal'));
        const resultDiv = document.getElementById('testNASResult');
        
        resultDiv.innerHTML = `
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Pinging...</span>
            </div>
            <p class="mt-2">Pinging subscriber ${username}...</p>
        `;
        modal.show();
        
        fetch('/index.php?page=isp&action=ping_subscriber&id=' + subId)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.online) {
                    resultDiv.innerHTML = `
                        <i class="bi bi-check-circle-fill text-success fs-1"></i>
                        <h5 class="mt-3 text-success">Reachable</h5>
                        <p class="mb-1"><strong>IP:</strong> ${data.ip_address}</p>
                        <p class="mb-0"><strong>Latency:</strong> ${data.latency_ms ? data.latency_ms + ' ms' : 'N/A'}</p>
                    `;
                } else if (data.error) {
                    resultDiv.innerHTML = `
                        <i class="bi bi-exclamation-triangle-fill text-warning fs-1"></i>
                        <h5 class="mt-3 text-warning">Cannot Ping</h5>
                        <p class="mb-0">${data.error}</p>
                    `;
                } else {
                    resultDiv.innerHTML = `
                        <i class="bi bi-x-circle-fill text-danger fs-1"></i>
                        <h5 class="mt-3 text-danger">Unreachable</h5>
                        <p class="mb-1"><strong>IP:</strong> ${data.ip_address || 'Unknown'}</p>
                        <p class="mb-0">Could not reach the subscriber</p>
                    `;
                }
            })
            .catch(error => {
                resultDiv.innerHTML = `
                    <i class="bi bi-exclamation-triangle-fill text-warning fs-1"></i>
                    <h5 class="mt-3 text-warning">Error</h5>
                    <p class="mb-0">Failed to ping subscriber</p>
                `;
            });
    }
    
    function disconnectSubscriber(subId, username) {
        if (!confirm(`Disconnect all active sessions for ${username}?`)) return;
        
        const modal = new bootstrap.Modal(document.getElementById('testNASModal'));
        const resultDiv = document.getElementById('testNASResult');
        
        resultDiv.innerHTML = `
            <div class="spinner-border text-warning" role="status">
                <span class="visually-hidden">Disconnecting...</span>
            </div>
            <p class="mt-2">Disconnecting ${username}...</p>
        `;
        modal.show();
        
        fetch('/index.php?page=isp&action=ajax_disconnect&id=' + subId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resultDiv.innerHTML = `
                        <i class="bi bi-check-circle-fill text-success fs-1"></i>
                        <h5 class="mt-3 text-success">Disconnected</h5>
                        <p class="mb-0">Disconnected ${data.disconnected || 0} session(s)</p>
                    `;
                    setTimeout(() => location.reload(), 1500);
                } else {
                    resultDiv.innerHTML = `
                        <i class="bi bi-exclamation-triangle-fill text-warning fs-1"></i>
                        <h5 class="mt-3 text-warning">Warning</h5>
                        <p class="mb-0">${data.message || 'Could not disconnect sessions'}</p>
                    `;
                }
            })
            .catch(error => {
                resultDiv.innerHTML = `
                    <i class="bi bi-x-circle-fill text-danger fs-1"></i>
                    <h5 class="mt-3 text-danger">Error</h5>
                    <p class="mb-0">Failed to disconnect subscriber</p>
                `;
            });
    }
    
    function resetSubscriberMAC(subId, username) {
        if (!confirm(`Reset all registered devices for ${username}? They will need to reconnect to be registered again.`)) return;
        
        const modal = new bootstrap.Modal(document.getElementById('testNASModal'));
        const resultDiv = document.getElementById('testNASResult');
        
        resultDiv.innerHTML = `
            <div class="spinner-border text-warning" role="status">
                <span class="visually-hidden">Resetting...</span>
            </div>
            <p class="mt-2">Clearing registered devices for ${username}...</p>
        `;
        modal.show();
        
        fetch('/index.php?page=isp&action=ajax_reset_mac&id=' + subId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resultDiv.innerHTML = `
                        <i class="bi bi-check-circle-fill text-success fs-1"></i>
                        <h5 class="mt-3 text-success">Devices Reset</h5>
                        <p class="mb-0">${data.message}</p>
                    `;
                    setTimeout(() => location.reload(), 1500);
                } else {
                    resultDiv.innerHTML = `
                        <i class="bi bi-exclamation-triangle-fill text-warning fs-1"></i>
                        <h5 class="mt-3 text-warning">Warning</h5>
                        <p class="mb-0">${data.error || 'Could not reset devices'}</p>
                    `;
                }
            })
            .catch(error => {
                resultDiv.innerHTML = `
                    <i class="bi bi-x-circle-fill text-danger fs-1"></i>
                    <h5 class="mt-3 text-danger">Error</h5>
                    <p class="mb-0">Failed to reset devices</p>
                `;
            });
    }
    
    function openRouterPage(ip) {
        const modal = new bootstrap.Modal(document.getElementById('routerBrowserModal'));
        document.getElementById('routerBrowserTitle').textContent = 'Router: ' + ip;
        const proxyUrl = '/router-proxy.php?url=' + encodeURIComponent('http://' + ip);
        document.getElementById('routerBrowserFrame').src = proxyUrl;
        document.getElementById('routerBrowserAddress').value = 'http://' + ip;
        document.getElementById('routerBrowserAddress').dataset.ip = ip;
        modal.show();
    }
    
    function navigateRouterBrowser() {
        const addressBar = document.getElementById('routerBrowserAddress');
        const url = addressBar.value;
        const proxyUrl = '/router-proxy.php?url=' + encodeURIComponent(url);
        document.getElementById('routerBrowserFrame').src = proxyUrl;
    }
    
    function refreshRouterBrowser() {
        const frame = document.getElementById('routerBrowserFrame');
        frame.src = frame.src;
    }
    
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(() => {
            showToast('Copied to clipboard!', 'success');
        }).catch(() => {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            showToast('Copied to clipboard!', 'success');
        });
    }
    
    function copyCredentials(subId, username, password) {
        const text = `Username: ${username}\nPassword: ${password}`;
        copyToClipboard(text);
    }
    
    function showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `alert alert-${type} position-fixed bottom-0 end-0 m-3 shadow-lg`;
        toast.style.zIndex = '9999';
        toast.innerHTML = `<i class="bi bi-${type === 'success' ? 'check-circle' : 'info-circle'} me-2"></i>${message}`;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }
    
    function quickAction(action, subId, username) {
        const messages = {
            'suspend': `Suspend subscriber ${username}?`,
            'unsuspend': `Reactivate subscriber ${username}? Remaining days will be restored.`,
            'activate': `Activate subscriber ${username}?`,
            'renew': `Renew subscription for ${username}?`,
            'disconnect': `Disconnect active sessions for ${username}?`,
            'speed_update': `Push speed update (CoA) to ${username}?`,
            'delete': `DELETE subscriber ${username}? This cannot be undone!`
        };
        
        if (!confirm(messages[action] || `Perform ${action} on ${username}?`)) return;
        
        // Use AJAX for real-time actions
        const ajaxActions = ['activate', 'suspend', 'unsuspend', 'renew', 'disconnect', 'speed_update'];
        if (ajaxActions.includes(action)) {
            performCoAAction(action, subId, username);
            return;
        }
        
        const actionMap = {
            'delete': 'delete_subscription'
        };
        
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `<input type="hidden" name="action" value="${actionMap[action]}"><input type="hidden" name="id" value="${subId}">`;
        document.body.appendChild(form);
        form.submit();
    }
    
    async function performCoAAction(action, subId, username) {
        const btn = event?.target;
        const originalHtml = btn?.innerHTML;
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        }
        
        try {
            const actionMap = {
                'activate': 'ajax_activate',
                'suspend': 'ajax_suspend',
                'unsuspend': 'ajax_unsuspend',
                'renew': 'ajax_renew',
                'disconnect': 'ajax_disconnect',
                'speed_update': 'ajax_speed_update'
            };
            
            const response = await fetch(`?page=isp&action=${actionMap[action]}&id=${subId}`);
            const result = await response.json();
            
            if (result.success) {
                showToast(`${action.charAt(0).toUpperCase() + action.slice(1)} successful for ${username}`, 'success');
                
                // Update UI based on action
                const row = document.querySelector(`tr[data-sub-id="${subId}"]`);
                if (row) {
                    const statusBadge = row.querySelector('.status-badge');
                    if (statusBadge) {
                        if (action === 'activate' || action === 'renew') {
                            statusBadge.className = 'badge bg-success status-badge';
                            statusBadge.textContent = 'Active';
                        } else if (action === 'suspend') {
                            statusBadge.className = 'badge bg-warning status-badge';
                            statusBadge.textContent = 'Suspended';
                        }
                    }
                    if (result.expiry_date) {
                        const expiryCell = row.querySelector('.expiry-date');
                        if (expiryCell) expiryCell.textContent = result.expiry_date;
                    }
                }
                
                // Show CoA result if available
                if (result.coa_result) {
                    const coaStatus = result.coa_result.success ? 'CoA sent' : 'CoA failed';
                    showToast(`${coaStatus}: ${result.coa_result.output || result.coa_result.error || ''}`, result.coa_result.success ? 'info' : 'warning');
                }
                if (result.disconnected !== undefined) {
                    showToast(`Disconnected ${result.disconnected} session(s)`, 'info');
                }
            } else {
                showToast(`Error: ${result.error || 'Unknown error'}`, 'danger');
            }
        } catch (err) {
            showToast(`Network error: ${err.message}`, 'danger');
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        }
    }
    
    function initiateMpesa(subId, phone, amount) {
        const formattedPhone = phone.replace(/[^0-9]/g, '');
        const modal = document.getElementById('mpesaPaymentModal');
        if (modal) {
            document.getElementById('mpesaSubId').value = subId;
            document.getElementById('mpesaPhone').value = formattedPhone;
            document.getElementById('mpesaAmount').value = amount;
            new bootstrap.Modal(modal).show();
        } else {
            if (confirm(`Send M-Pesa STK Push for KES ${amount} to ${formattedPhone}?`)) {
                fetch(`/index.php?page=api&action=mpesa_stk_push&subscription_id=${subId}&phone=${formattedPhone}&amount=${amount}`)
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            showToast('STK Push sent! User should enter PIN.', 'success');
                        } else {
                            showToast('Failed: ' + (data.error || 'Unknown error'), 'danger');
                        }
                    })
                    .catch(() => showToast('Failed to initiate payment', 'danger'));
            }
        }
    }
    
    function refreshOnlineStatus() {
        const icon = document.getElementById('refreshIcon');
        icon.classList.add('spin');
        
        fetch('/index.php?page=isp&action=get_online_subscribers')
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('onlineCountNum').textContent = data.count;
                    
                    document.querySelectorAll('.sub-row').forEach(row => {
                        const subId = row.dataset.subId;
                        const isOnline = data.online_ids.includes(parseInt(subId));
                        const statusDiv = row.querySelector('.me-2.position-relative');
                        if (statusDiv) {
                            if (isOnline) {
                                statusDiv.innerHTML = `
                                    <div class="rounded-circle bg-success d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                        <i class="bi bi-wifi text-white"></i>
                                    </div>
                                    <span class="position-absolute bottom-0 end-0 bg-success border border-white rounded-circle" style="width: 12px; height: 12px;"></span>
                                `;
                            } else {
                                statusDiv.innerHTML = `
                                    <div class="rounded-circle bg-secondary-subtle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                        <i class="bi bi-wifi-off text-secondary"></i>
                                    </div>
                                `;
                            }
                        }
                    });
                    
                    showToast(`Updated: ${data.count} subscribers online`, 'success');
                }
            })
            .catch(() => showToast('Failed to refresh status', 'danger'))
            .finally(() => icon.classList.remove('spin'));
    }
    
    function submitMpesaQuickPay(e) {
        e.preventDefault();
        const btn = document.getElementById('mpesaStkBtn');
        const resultDiv = document.getElementById('mpesaPayResult');
        const phone = document.getElementById('mpesaPayPhone').value;
        const amount = document.getElementById('mpesaPayAmount').value;
        
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Sending...';
        resultDiv.innerHTML = '';
        
        const urlParams = new URLSearchParams(window.location.search);
        const subId = urlParams.get('id');
        
        fetch(`/index.php?page=api&action=mpesa_stk_push&subscription_id=${subId}&phone=${phone}&amount=${amount}`)
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    resultDiv.innerHTML = '<span class="text-success"><i class="bi bi-check-circle me-1"></i>STK Push sent! Ask customer to enter PIN.</span>';
                    showToast('M-Pesa STK Push sent successfully!', 'success');
                } else {
                    resultDiv.innerHTML = `<span class="text-danger"><i class="bi bi-x-circle me-1"></i>${data.error || 'Failed to send STK Push'}</span>`;
                }
            })
            .catch(err => {
                resultDiv.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle me-1"></i>Network error</span>';
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-lightning-charge me-1"></i> Send STK Push';
            });
        
        return false;
    }
    </script>
    
    <style>
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    .spin {
        animation: spin 1s linear infinite;
    }
    </style>
    
    <div class="modal fade" id="routerBrowserModal" tabindex="-1" aria-labelledby="routerBrowserTitle" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="routerBrowserTitle"><i class="bi bi-globe me-2"></i>Router</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="input-group border-bottom">
                        <span class="input-group-text bg-light border-0 rounded-0"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control border-0 rounded-0" id="routerBrowserAddress" placeholder="http://..." onkeypress="if(event.key==='Enter')navigateRouterBrowser()">
                        <button class="btn btn-outline-secondary border-0 rounded-0" type="button" onclick="navigateRouterBrowser()"><i class="bi bi-arrow-right"></i></button>
                        <button class="btn btn-outline-secondary border-0 rounded-0" type="button" onclick="refreshRouterBrowser()"><i class="bi bi-arrow-clockwise"></i></button>
                    </div>
                    <iframe id="routerBrowserFrame" src="about:blank" style="width: 100%; height: 70vh; border: none;"></iframe>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    // Real-time session updates via Server-Sent Events
    let eventSource = null;
    let reconnectTimeout = null;
    
    function connectSSE() {
        if (eventSource) {
            eventSource.close();
        }
        
        eventSource = new EventSource('http://localhost:3002/radius/events');
        
        eventSource.onopen = function() {
            console.log('[SSE] Connected to real-time session updates');
            const indicator = document.getElementById('realTimeIndicator');
            if (indicator) {
                indicator.classList.remove('bg-danger');
                indicator.classList.add('bg-success');
                indicator.title = 'Real-time updates active';
            }
        };
        
        eventSource.onerror = function(e) {
            console.log('[SSE] Connection error, will retry...');
            const indicator = document.getElementById('realTimeIndicator');
            if (indicator) {
                indicator.classList.remove('bg-success');
                indicator.classList.add('bg-danger');
                indicator.title = 'Real-time updates disconnected';
            }
            eventSource.close();
            // Retry connection after 5 seconds
            if (reconnectTimeout) clearTimeout(reconnectTimeout);
            reconnectTimeout = setTimeout(connectSSE, 5000);
        };
        
        eventSource.addEventListener('session_disconnect', function(e) {
            const data = JSON.parse(e.data);
            console.log('[SSE] Session disconnected:', data);
            
            // Show toast notification
            showToast(`Session disconnected: ${data.username || data.sessionId}`, data.success ? 'success' : 'warning');
            
            // Update UI - remove session from table or mark as disconnected
            const sessionRow = document.querySelector(`tr[data-session-id="${data.sessionId}"]`);
            if (sessionRow) {
                sessionRow.classList.add('table-secondary');
                sessionRow.style.opacity = '0.5';
                const statusCell = sessionRow.querySelector('.session-status');
                if (statusCell) statusCell.innerHTML = '<span class="badge bg-secondary">Disconnected</span>';
            }
            
            // Update online count
            const onlineCount = document.getElementById('onlineCountNum');
            if (onlineCount) {
                const current = parseInt(onlineCount.textContent) || 0;
                if (current > 0) onlineCount.textContent = current - 1;
            }
            
            // Update subscriber row status indicator
            if (data.subscriptionId) {
                const subRow = document.querySelector(`.sub-row[data-sub-id="${data.subscriptionId}"]`);
                if (subRow) {
                    const statusDiv = subRow.querySelector('.me-2.position-relative');
                    if (statusDiv) {
                        statusDiv.innerHTML = `
                            <div class="rounded-circle bg-secondary-subtle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                <i class="bi bi-wifi-off text-secondary"></i>
                            </div>
                        `;
                    }
                    
                    // Update live timer from Online to Offline
                    const liveTimer = subRow.querySelector('.live-timer');
                    if (liveTimer) {
                        const nowTimestamp = Math.floor(Date.now() / 1000);
                        liveTimer.dataset.start = nowTimestamp;
                        liveTimer.dataset.type = 'offline';
                        liveTimer.className = 'badge bg-white bg-opacity-25 text-white live-timer';
                        liveTimer.innerHTML = '<i class="bi bi-circle me-1" style="font-size: 8px;"></i>Offline (<span class="timer-value">00:00:00 ago</span>)';
                    }
                }
            }
        });
        
        eventSource.addEventListener('speed_update', function(e) {
            const data = JSON.parse(e.data);
            console.log('[SSE] Speed updated:', data);
            
            // Show toast notification
            showToast(`Speed updated for ${data.username}: ${data.rateLimit}`, data.success ? 'success' : 'warning');
            
            // Highlight the affected row briefly
            if (data.subscriptionId) {
                const subRow = document.querySelector(`.sub-row[data-sub-id="${data.subscriptionId}"]`);
                if (subRow) {
                    subRow.classList.add('table-info');
                    setTimeout(() => subRow.classList.remove('table-info'), 3000);
                }
            }
        });
    }
    
    // Live timer update function - updates uptime/offline every second like a watch
    // Server time offset to sync with server timezone (Africa/Nairobi)
    const serverTimeAtLoad = <?= time() ?>;
    const clientTimeAtLoad = Math.floor(Date.now() / 1000);
    const serverOffset = serverTimeAtLoad - clientTimeAtLoad;
    
    function formatDuration(seconds) {
        if (seconds < 0) seconds = 0;
        const days = Math.floor(seconds / 86400);
        const hours = Math.floor((seconds % 86400) / 3600);
        const mins = Math.floor((seconds % 3600) / 60);
        const secs = seconds % 60;
        const timeStr = String(hours).padStart(2, '0') + ':' + String(mins).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
        return days > 0 ? days + 'd ' + timeStr : timeStr;
    }
    
    function updateLiveTimers() {
        // Use server time by applying offset
        const now = Math.floor(Date.now() / 1000) + serverOffset;
        document.querySelectorAll('.live-timer').forEach(function(timer) {
            const start = parseInt(timer.dataset.start);
            const type = timer.dataset.type;
            const valueSpan = timer.querySelector('.timer-value');
            if (!start || !valueSpan) return;
            
            const elapsed = now - start;
            let text = formatDuration(elapsed);
            if (type === 'offline') text += ' ago';
            valueSpan.textContent = text;
        });
    }
    
    // Update timers every second
    setInterval(updateLiveTimers, 1000);
    
    // Connect on page load
    document.addEventListener('DOMContentLoaded', function() {
        connectSSE();
        updateLiveTimers(); // Initial update
    });
    
    // Cleanup on page unload
    window.addEventListener('beforeunload', function() {
        if (eventSource) eventSource.close();
        if (reconnectTimeout) clearTimeout(reconnectTimeout);
    });
    </script>
</body>
</html>
