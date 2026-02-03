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
    
        /* Dark Mode Support for ISP */
        body.dark-mode { background-color: #1a1a2e !important; color: #e2e8f0 !important; }
        body.dark-mode .isp-sidebar { background: #0f0f23 !important; }
        body.dark-mode .card { background: #16213e !important; border-color: #1f4068 !important; }
        body.dark-mode .card-header { background: #1f4068 !important; border-color: #1f4068 !important; color: #e2e8f0 !important; }
        body.dark-mode .table { --bs-table-bg: #16213e; --bs-table-color: #e2e8f0; --bs-table-border-color: #1f4068; }
        body.dark-mode .modal-content { background: #16213e !important; }
        body.dark-mode .form-control, body.dark-mode .form-select { background: #1a1a2e !important; border-color: #1f4068 !important; color: #e2e8f0 !important; }
        body.dark-mode .list-group-item { background: #16213e !important; border-color: #1f4068 !important; color: #e2e8f0 !important; }
        body.dark-mode .dropdown-menu { background: #16213e !important; }
        body.dark-mode .dropdown-item { color: #e2e8f0 !important; }
        body.dark-mode .dropdown-item:hover { background: #1f4068 !important; }
        body.dark-mode .bg-light, body.dark-mode .bg-white { background: #16213e !important; }
        body.dark-mode code { background: #1f4068; color: #fbbf24; }
    </style>