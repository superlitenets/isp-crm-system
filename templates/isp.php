<?php
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

if ($action === 'get_wifi_config') {
    header('Content-Type: application/json');
    
    if (empty($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    
    $subscriptionId = (int)($_GET['subscription_id'] ?? 0);
    
    $stmt = $db->prepare("SELECT s.*, c.phone as customer_phone FROM radius_subscriptions s LEFT JOIN customers c ON s.customer_id = c.id WHERE s.id = ?");
    $stmt->execute([$subscriptionId]);
    $sub = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$sub) {
        echo json_encode(['success' => false, 'error' => 'Subscription not found']);
        exit;
    }
    
    $deviceId = null;
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
        echo json_encode(['success' => false, 'error' => 'No TR-069 device found for this subscriber. Device may not be registered in GenieACS.']);
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
    
    $stmt = $db->prepare("SELECT s.*, c.phone as customer_phone FROM radius_subscriptions s LEFT JOIN customers c ON s.customer_id = c.id WHERE s.id = ?");
    $stmt->execute([$subscriptionId]);
    $sub = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$sub) {
        echo json_encode(['success' => false, 'error' => 'Subscription not found']);
        exit;
    }
    
    $deviceId = null;
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

// Real-time CoA AJAX endpoints
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
                // Get current package to detect changes
                $currentSub = $radiusBilling->getSubscription($id);
                $oldPackageId = $currentSub['package_id'] ?? null;
                $newPackageId = (int)$_POST['package_id'];
                
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
                    $_POST['password'],
                    $radiusBilling->encryptPassword($_POST['password']),
                    $_POST['expiry_date'] ?: null,
                    !empty($_POST['static_ip']) ? $_POST['static_ip'] : null,
                    !empty($_POST['mac_address']) ? $_POST['mac_address'] : null,
                    isset($_POST['auto_renew']) ? 'true' : 'false',
                    $id
                ]);
                
                // Auto-send CoA if package changed (speed update)
                $coaMessage = '';
                if ($oldPackageId != $newPackageId) {
                    $coaResult = $radiusBilling->sendSpeedUpdateCoA($id);
                    if ($coaResult['success']) {
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
                $message = 'Credit of KES ' . number_format($amount) . ' added successfully';
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
                if (!empty($result['sessions_disconnected'])) {
                    $msg .= ' (' . $result['sessions_disconnected'] . ' session(s) disconnected)';
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
                    $stmt = $db->prepare("UPDATE radius_subscriptions SET expiry_date = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$newExpiry, $subId]);
                    
                    if ($reason) {
                        $stmt = $db->prepare("INSERT INTO radius_subscription_notes (subscription_id, note, created_by, created_at) VALUES (?, ?, ?, NOW())");
                        $stmt->execute([$subId, "Expiry changed to " . date('M j, Y', strtotime($newExpiry)) . ". Reason: " . $reason, $_SESSION['user_id']]);
                    }
                    
                    $message = 'Expiry date updated to ' . date('M j, Y', strtotime($newExpiry));
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
            $sendCoA = isset($_POST['send_coa']);
            
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
                
                if ($sendCoA) {
                    $coaResult = $radiusBilling->sendCoAForSubscription($subId);
                    if ($coaResult && !empty($coaResult['success'])) {
                        $msg .= ' (speed updated via CoA)';
                    }
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
            }
            .sidebar {
                display: none !important;
            }
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
                <a class="nav-link <?= $view === 'nas' ? 'active' : '' ?>" href="?page=isp&view=nas">
                    <i class="bi bi-hdd-network me-2"></i> NAS Devices
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
                <a class="nav-link <?= $view === 'nas' ? 'active' : '' ?>" href="?page=isp&view=nas">
                    <i class="bi bi-hdd-network me-2"></i> NAS Devices
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
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="page-title mb-1"><i class="bi bi-speedometer2"></i> ISP Dashboard</h4>
                    <span class="text-muted small">Last updated: <?= date('M j, Y H:i:s') ?></span>
                </div>
                <div class="d-flex gap-2">
                    <a href="?page=isp&view=subscriptions&filter=expiring" class="btn btn-warning">
                        <i class="bi bi-exclamation-triangle me-1"></i> Expiring (<?= $stats['expiring_soon'] ?>)
                    </a>
                    <button class="btn btn-outline-primary" onclick="location.reload()">
                        <i class="bi bi-arrow-clockwise me-1"></i> Refresh
                    </button>
                </div>
            </div>
            
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card stat-card shadow-sm h-100 stat-success">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="stat-icon bg-success bg-opacity-10 text-success">
                                    <i class="bi bi-people"></i>
                                </div>
                            </div>
                            <div class="stat-value"><?= number_format($stats['active_subscriptions']) ?></div>
                            <div class="stat-label">Active Subscribers</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card shadow-sm h-100 stat-info">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="stat-icon bg-info bg-opacity-10 text-info">
                                    <i class="bi bi-wifi"></i>
                                </div>
                            </div>
                            <div class="stat-value"><?= number_format($stats['online_now'] ?? 0) ?></div>
                            <div class="stat-label">Online Now</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card shadow-sm h-100 stat-warning">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                                    <i class="bi bi-exclamation-triangle"></i>
                                </div>
                            </div>
                            <div class="stat-value"><?= number_format($stats['expiring_soon']) ?></div>
                            <div class="stat-label">Expiring Soon</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                                    <i class="bi bi-currency-exchange"></i>
                                </div>
                            </div>
                            <div class="stat-value">KES <?= number_format($stats['monthly_revenue']) ?></div>
                            <div class="stat-label">Monthly Revenue</div>
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
                                    <th>Subscriber</th>
                                    <th>Package</th>
                                    <th class="text-center">Status</th>
                                    <th>Expiry</th>
                                    <th class="text-end">Usage</th>
                                    <th class="text-center" style="width: 200px;">Actions</th>
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
                                        <div class="d-flex align-items-center">
                                            <div class="me-2 position-relative">
                                                <?php if ($isOnline): ?>
                                                <div class="rounded-circle bg-success d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                    <i class="bi bi-wifi text-white"></i>
                                                </div>
                                                <span class="position-absolute bottom-0 end-0 bg-success border border-white rounded-circle" style="width: 12px; height: 12px;"></span>
                                                <?php else: ?>
                                                <div class="rounded-circle bg-secondary-subtle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                    <i class="bi bi-wifi-off text-secondary"></i>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <div class="d-flex align-items-center gap-1">
                                                    <a href="?page=isp&view=subscriber&id=<?= $sub['id'] ?>" class="fw-bold text-decoration-none"><?= htmlspecialchars($sub['username']) ?></a>
                                                    <button class="btn btn-link btn-sm p-0 text-muted" onclick="copyToClipboard('<?= htmlspecialchars($sub['username']) ?>')" title="Copy username"><i class="bi bi-clipboard"></i></button>
                                                </div>
                                                <?php if ($isOnline && !empty($onlineInfo['ip'])): ?>
                                                <a href="javascript:void(0)" onclick="openRouterPage('<?= htmlspecialchars($onlineInfo['ip']) ?>')" class="badge bg-success-subtle text-success border border-success-subtle text-decoration-none" title="Click to open router page"><i class="bi bi-hdd-network"></i> <?= htmlspecialchars($onlineInfo['ip']) ?></a>
                                                <?php endif; ?>
                                                <div class="small text-muted"><?= htmlspecialchars($sub['customer_name'] ?? 'No customer') ?></div>
                                                <?php if (!empty($sub['customer_phone'])): ?>
                                                <div class="small">
                                                    <a href="tel:<?= htmlspecialchars($sub['customer_phone']) ?>" class="text-decoration-none text-muted"><i class="bi bi-telephone"></i> <?= htmlspecialchars($sub['customer_phone']) ?></a>
                                                    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $sub['customer_phone']) ?>" target="_blank" class="text-success ms-1" title="WhatsApp"><i class="bi bi-whatsapp"></i></a>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle"><?= htmlspecialchars($sub['package_name']) ?></span>
                                        <div class="small text-muted mt-1">
                                            <i class="bi bi-arrow-down text-success"></i> <?= $sub['download_speed'] ?>
                                            <i class="bi bi-arrow-up text-danger ms-1"></i> <?= $sub['upload_speed'] ?>
                                        </div>
                                        <div class="small text-muted">KES <?= number_format($sub['package_price'] ?? 0) ?></div>
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
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <a href="?page=isp&view=subscriber&id=<?= $sub['id'] ?>" class="btn btn-outline-primary" title="View Details">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <?php if ($sub['status'] === 'active'): ?>
                                            <button type="button" class="btn btn-outline-warning" title="Suspend" onclick="quickAction('suspend', <?= $sub['id'] ?>, '<?= htmlspecialchars($sub['username']) ?>')">
                                                <i class="bi bi-pause-fill"></i>
                                            </button>
                                            <?php else: ?>
                                            <button type="button" class="btn btn-outline-success" title="Activate" onclick="quickAction('activate', <?= $sub['id'] ?>, '<?= htmlspecialchars($sub['username']) ?>')">
                                                <i class="bi bi-play-fill"></i>
                                            </button>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-outline-info" title="Renew" onclick="quickAction('renew', <?= $sub['id'] ?>, '<?= htmlspecialchars($sub['username']) ?>')">
                                                <i class="bi bi-arrow-clockwise"></i>
                                            </button>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-secondary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" title="More actions"></button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li><a class="dropdown-item" href="#" onclick="copyCredentials(<?= $sub['id'] ?>, '<?= htmlspecialchars($sub['username']) ?>', '<?= htmlspecialchars($sub['password'] ?? '') ?>')"><i class="bi bi-key me-2"></i>Copy Credentials</a></li>
                                                    <?php if (!empty($sub['customer_phone'])): ?>
                                                    <li><a class="dropdown-item" href="#" onclick="sendQuickSMS('<?= htmlspecialchars($sub['customer_phone']) ?>', '<?= htmlspecialchars($sub['customer_name'] ?? '') ?>')"><i class="bi bi-chat-dots me-2"></i>Send SMS</a></li>
                                                    <li><a class="dropdown-item" href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $sub['customer_phone']) ?>" target="_blank"><i class="bi bi-whatsapp me-2"></i>WhatsApp</a></li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <?php endif; ?>
                                                    <li><a class="dropdown-item" href="#" onclick="initiateMpesa(<?= $sub['id'] ?>, '<?= htmlspecialchars($sub['customer_phone'] ?? '') ?>', <?= (int)($sub['package_price'] ?? 0) ?>)"><i class="bi bi-phone me-2"></i>M-Pesa Payment</a></li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li class="dropdown-header text-muted small">Real-time CoA</li>
                                                    <li><a class="dropdown-item" href="#" onclick="quickAction('disconnect', <?= $sub['id'] ?>, '<?= htmlspecialchars($sub['username']) ?>')"><i class="bi bi-x-circle me-2 text-warning"></i>Disconnect Sessions</a></li>
                                                    <li><a class="dropdown-item" href="#" onclick="quickAction('speed_update', <?= $sub['id'] ?>, '<?= htmlspecialchars($sub['username']) ?>')"><i class="bi bi-speedometer2 me-2 text-info"></i>Push Speed Update</a></li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><a class="dropdown-item text-danger" href="#" onclick="quickAction('delete', <?= $sub['id'] ?>, '<?= htmlspecialchars($sub['username']) ?>')"><i class="bi bi-trash me-2"></i>Delete</a></li>
                                                </ul>
                                            </div>
                                        </div>
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
                
                // Get tickets for this customer
                $tickets = [];
                if ($customer) {
                    $stmt = $db->prepare("SELECT t.*, (SELECT COUNT(*) FROM ticket_comments WHERE ticket_id = t.id) as comment_count FROM tickets t WHERE t.customer_id = ? ORDER BY t.created_at DESC LIMIT 10");
                    $stmt->execute([$customer['id']]);
                    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
                
                $isOnline = !empty($activeSession);
                
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
                                    <?php if ($isOnline): ?>
                                    <span class="badge bg-white text-success"><i class="bi bi-circle-fill me-1" style="font-size: 8px;"></i>Online</span>
                                    <?php else: ?>
                                    <span class="badge bg-white bg-opacity-25 text-white"><i class="bi bi-circle me-1" style="font-size: 8px;"></i>Offline</span>
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
            
            <div class="row g-4">
                <!-- Left Column -->
                <div class="col-lg-4">
                    <!-- Premium Customer Info Card -->
                    <div class="card border-0 shadow-lg mb-4 overflow-hidden">
                        <div class="card-header border-0 py-3" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="mb-0 text-white fw-semibold"><i class="bi bi-person-circle me-2"></i>Customer</h6>
                                <button class="btn btn-sm btn-light btn-outline-light rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#editCustomerModal">
                                    <i class="bi bi-pencil-fill me-1"></i> Edit
                                </button>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <?php if ($customer): ?>
                            <div class="text-center py-4 px-3" style="background: linear-gradient(180deg, rgba(102,126,234,0.1) 0%, transparent 100%);">
                                <div class="rounded-circle bg-gradient d-inline-flex align-items-center justify-content-center mb-3" style="width: 70px; height: 70px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                    <span class="text-white fw-bold fs-3"><?= strtoupper(substr($customer['name'], 0, 1)) ?></span>
                                </div>
                                <h5 class="fw-bold mb-1"><?= htmlspecialchars($customer['name']) ?></h5>
                                <p class="text-muted mb-0 small">Account #<?= htmlspecialchars($customer['phone']) ?></p>
                            </div>
                            <div class="px-3 pb-3">
                                <div class="list-group list-group-flush">
                                    <div class="list-group-item d-flex align-items-center px-0 py-2 border-0 bg-transparent">
                                        <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center me-3" style="width: 36px; height: 36px;">
                                            <i class="bi bi-telephone text-primary"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <small class="text-muted d-block">Phone</small>
                                            <span class="fw-medium"><?= htmlspecialchars($customer['phone']) ?></span>
                                        </div>
                                        <a href="tel:<?= htmlspecialchars($customer['phone']) ?>" class="btn btn-sm btn-outline-primary rounded-pill"><i class="bi bi-telephone-outbound"></i></a>
                                    </div>
                                    <div class="list-group-item d-flex align-items-center px-0 py-2 border-0 bg-transparent">
                                        <div class="rounded-circle bg-info bg-opacity-10 d-flex align-items-center justify-content-center me-3" style="width: 36px; height: 36px;">
                                            <i class="bi bi-envelope text-info"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <small class="text-muted d-block">Email</small>
                                            <span class="fw-medium"><?= htmlspecialchars($customer['email'] ?? '-') ?></span>
                                        </div>
                                    </div>
                                    <div class="list-group-item d-flex align-items-center px-0 py-2 border-0 bg-transparent">
                                        <div class="rounded-circle bg-warning bg-opacity-10 d-flex align-items-center justify-content-center me-3" style="width: 36px; height: 36px;">
                                            <i class="bi bi-geo-alt text-warning"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <small class="text-muted d-block">Address</small>
                                            <span class="fw-medium"><?= htmlspecialchars($customer['address'] ?? '-') ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="text-center py-5">
                                <i class="bi bi-person-x text-muted" style="font-size: 3rem;"></i>
                                <p class="text-muted mt-2 mb-0">No customer linked</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Premium Subscription Details Card -->
                    <div class="card border-0 shadow-lg mb-4 overflow-hidden">
                        <div class="card-header border-0 py-3" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="mb-0 text-white fw-semibold"><i class="bi bi-router me-2"></i>Subscription</h6>
                                <button class="btn btn-sm btn-light btn-outline-light rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#editSubscriptionModal">
                                    <i class="bi bi-pencil-fill me-1"></i> Edit
                                </button>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <!-- Credentials Section -->
                            <div class="p-3 border-bottom" style="background: linear-gradient(180deg, rgba(17,153,142,0.08) 0%, transparent 100%);">
                                <div class="row g-2">
                                    <div class="col-12">
                                        <label class="form-label text-muted small mb-1">Username</label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text bg-dark text-success border-dark"><i class="bi bi-person-badge"></i></span>
                                            <input type="text" class="form-control bg-dark text-success border-dark font-monospace" value="<?= htmlspecialchars($subscriber['username']) ?>" readonly>
                                            <button class="btn btn-dark border-dark" onclick="copyToClipboard('<?= htmlspecialchars($subscriber['username']) ?>')" title="Copy"><i class="bi bi-clipboard"></i></button>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label text-muted small mb-1">Password</label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text bg-dark text-warning border-dark"><i class="bi bi-key"></i></span>
                                            <input type="password" class="form-control bg-dark text-warning border-dark font-monospace" id="pwdInput" value="<?= htmlspecialchars($subscriber['password'] ?? '') ?>" readonly>
                                            <button class="btn btn-dark border-dark" id="pwdToggle" onclick="togglePassword()"><i class="bi bi-eye"></i></button>
                                            <button class="btn btn-dark border-dark" onclick="copyToClipboard('<?= htmlspecialchars($subscriber['password'] ?? '') ?>')" title="Copy"><i class="bi bi-clipboard"></i></button>
                                        </div>
                                        <script>
                                            function togglePassword() {
                                                const input = document.getElementById('pwdInput');
                                                const toggle = document.getElementById('pwdToggle').querySelector('i');
                                                if (input.type === 'password') {
                                                    input.type = 'text';
                                                    toggle.className = 'bi bi-eye-slash';
                                                } else {
                                                    input.type = 'password';
                                                    toggle.className = 'bi bi-eye';
                                                }
                                            }
                                        </script>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Package Info with Change Button -->
                            <div class="p-3 border-bottom">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <span class="text-muted small">Current Package</span>
                                    <button class="btn btn-sm btn-outline-primary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#changePackageModal">
                                        <i class="bi bi-arrow-left-right me-1"></i> Change
                                    </button>
                                </div>
                                <div class="d-flex align-items-center">
                                    <div class="rounded-3 p-3 me-3 text-center" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-width: 70px;">
                                        <i class="bi bi-box-seam text-white fs-4"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h5 class="fw-bold mb-1"><?= htmlspecialchars($package['name'] ?? 'N/A') ?></h5>
                                        <div class="d-flex gap-3 text-muted small">
                                            <span><i class="bi bi-arrow-down-circle text-success me-1"></i><?= $package['download_speed'] ?? '-' ?></span>
                                            <span><i class="bi bi-arrow-up-circle text-danger me-1"></i><?= $package['upload_speed'] ?? '-' ?></span>
                                        </div>
                                        <div class="mt-1">
                                            <span class="badge bg-success">KES <?= number_format($package['price'] ?? 0) ?>/<?= ucfirst($package['billing_cycle'] ?? 'month') ?></span>
                                            <span class="badge bg-secondary ms-1"><?= strtoupper($subscriber['access_type']) ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Expiry Section with Change Button -->
                            <div class="p-3 border-bottom">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <span class="text-muted small">Subscription Period</span>
                                    <button class="btn btn-sm btn-outline-warning rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#changeExpiryModal">
                                        <i class="bi bi-calendar-event me-1"></i> Change Expiry
                                    </button>
                                </div>
                                <?php 
                                $daysLeft = $subscriber['expiry_date'] ? (strtotime($subscriber['expiry_date']) - time()) / 86400 : null;
                                $isExpired = $daysLeft !== null && $daysLeft < 0;
                                $expiryColor = $isExpired ? '#dc3545' : ($daysLeft < 7 ? '#ffc107' : '#198754');
                                ?>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <div class="rounded-3 p-3 text-center bg-light">
                                            <small class="text-muted d-block">Start Date</small>
                                            <strong><?= $subscriber['start_date'] ? date('M j, Y', strtotime($subscriber['start_date'])) : '-' ?></strong>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="rounded-3 p-3 text-center" style="background: <?= $expiryColor ?>15; border: 2px solid <?= $expiryColor ?>;">
                                            <small class="text-muted d-block">Expiry Date</small>
                                            <strong style="color: <?= $expiryColor ?>;"><?= $subscriber['expiry_date'] ? date('M j, Y', strtotime($subscriber['expiry_date'])) : 'Never' ?></strong>
                                            <?php if ($isExpired): ?>
                                            <span class="badge bg-danger d-block mt-1">Expired</span>
                                            <?php elseif ($daysLeft !== null && $daysLeft < 7): ?>
                                            <span class="badge bg-warning text-dark d-block mt-1"><?= ceil($daysLeft) ?> days left</span>
                                            <?php elseif ($daysLeft !== null): ?>
                                            <span class="badge bg-success d-block mt-1"><?= ceil($daysLeft) ?> days left</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-2 text-center">
                                    <span class="badge bg-<?= $subscriber['auto_renew'] ? 'success' : 'secondary' ?>">
                                        <i class="bi bi-<?= $subscriber['auto_renew'] ? 'check-circle' : 'x-circle' ?> me-1"></i>
                                        Auto-renew <?= $subscriber['auto_renew'] ? 'ON' : 'OFF' ?>
                                    </span>
                                </div>
                            </div>
                            
                            <!-- Connection Details -->
                            <div class="p-3">
                                <h6 class="text-muted small mb-3 text-uppercase">Connection Details</h6>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <div class="rounded-3 p-2 bg-light text-center">
                                            <small class="text-muted d-block">Static IP</small>
                                            <span class="fw-medium"><?= $subscriber['static_ip'] ?: '<span class="text-muted">Dynamic</span>' ?></span>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="rounded-3 p-2 bg-light text-center">
                                            <small class="text-muted d-block">MAC Binding</small>
                                            <?php if ($subscriber['mac_address']): ?>
                                            <span class="badge bg-success"><i class="bi bi-lock-fill me-1"></i>Bound</span>
                                            <?php else: ?>
                                            <span class="badge bg-secondary"><i class="bi bi-unlock me-1"></i>Not Set</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php if ($subscriber['mac_address']): ?>
                                    <div class="col-12">
                                        <div class="rounded-3 p-2 bg-dark text-center">
                                            <code class="text-info small"><?= htmlspecialchars($subscriber['mac_address']) ?></code>
                                            <form method="post" class="d-inline ms-2">
                                                <input type="hidden" name="action" value="clear_mac">
                                                <input type="hidden" name="id" value="<?= $subscriber['id'] ?>">
                                                <input type="hidden" name="return_to" value="subscriber">
                                                <button type="submit" class="btn btn-sm btn-outline-warning py-0 px-2" title="Clear MAC binding" onclick="return confirm('Clear MAC binding? User will be able to connect from any device.')">
                                                    <i class="bi bi-unlock"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    
                    <!-- Data Usage Card -->
                    <?php if ($package && $package['data_quota_mb']): ?>
                    <div class="card shadow-sm mb-4">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Data Usage</h6>
                        </div>
                        <div class="card-body">
                            <?php 
                            $usagePercent = min(100, ($subscriber['data_used_mb'] / $package['data_quota_mb']) * 100);
                            $barColor = $usagePercent >= 100 ? '#dc3545' : ($usagePercent >= 80 ? '#ffc107' : '#28a745');
                            ?>
                            <div class="progress mb-2" style="height: 20px;">
                                <div class="progress-bar" style="width: <?= $usagePercent ?>%; background: <?= $barColor ?>;">
                                    <?= round($usagePercent) ?>%
                                </div>
                            </div>
                            <div class="d-flex justify-content-between small text-muted">
                                <span><?= number_format($subscriber['data_used_mb'] / 1024, 2) ?> GB used</span>
                                <span><?= number_format($package['data_quota_mb'] / 1024, 2) ?> GB total</span>
                            </div>
                            <button class="btn btn-outline-warning btn-sm w-100 mt-3" onclick="if(confirm('Reset data usage to 0?')) { document.getElementById('resetDataForm').submit(); }">
                                <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Usage
                            </button>
                            <form id="resetDataForm" method="post" style="display:none;">
                                <input type="hidden" name="action" value="reset_data_usage">
                                <input type="hidden" name="id" value="<?= $subId ?>">
                                <input type="hidden" name="return_to" value="subscriber">
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    
                    <div class="card shadow-sm mb-4">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-share me-2"></i>Quick Actions</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="copyCredentials(<?= $subId ?>, '<?= htmlspecialchars($subscriber['username']) ?>', '<?= htmlspecialchars($subscriber['password'] ?? '') ?>')">
                                    <i class="bi bi-key me-1"></i> Copy Credentials
                                </button>
                                <?php if ($customer && !empty($customer['phone'])): ?>
                                <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $customer['phone']) ?>?text=<?= urlencode('Hello ' . ($customer['name'] ?? '') . ', your WiFi credentials are:\nUsername: ' . $subscriber['username'] . '\nPassword: ' . ($subscriber['password'] ?? '')) ?>" target="_blank" class="btn btn-outline-success btn-sm">
                                    <i class="bi bi-whatsapp me-1"></i> Send Credentials via WhatsApp
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column -->
                <div class="col-lg-8">
                    <!-- Tabs -->
                    <ul class="nav nav-tabs mb-3">
                        <li class="nav-item">
                            <a class="nav-link active" data-bs-toggle="tab" href="#sessionsTab">
                                <i class="bi bi-broadcast me-1"></i> Sessions
                                <?php if ($isOnline): ?><span class="badge bg-success">1</span><?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#billingTab">
                                <i class="bi bi-receipt me-1"></i> Billing
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#invoicesTab">
                                <i class="bi bi-file-text me-1"></i> Invoices
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#ticketsTab">
                                <i class="bi bi-ticket me-1"></i> Tickets
                                <?php $openTickets = count(array_filter($tickets, fn($t) => in_array($t['status'], ['open', 'in_progress']))); ?>
                                <?php if ($openTickets): ?><span class="badge bg-danger"><?= $openTickets ?></span><?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#notesTab">
                                <i class="bi bi-sticky me-1"></i> Notes
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#speedOverridesTab">
                                <i class="bi bi-speedometer2 me-1"></i> Speed Schedules
                            </a>
                        </li>
                    </ul>
                    
                    <div class="tab-content">
                        <!-- Sessions Tab -->
                        <div class="tab-pane fade show active" id="sessionsTab">
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
                            
                            <div class="card shadow-sm">
                                <div class="card-header">
                                    <h6 class="mb-0">Session History</h6>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover mb-0">
                                            <thead>
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
                                                        $start = strtotime($sess['session_start']);
                                                        $end = $sess['session_end'] ? strtotime($sess['session_end']) : time();
                                                        $dur = $end - $start;
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
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="action" value="send_speed_coa">
                                        <input type="hidden" name="subscription_id" value="<?= $subId ?>">
                                        <input type="hidden" name="return_to" value="subscriber">
                                        <button type="submit" class="btn btn-warning">
                                            <i class="bi bi-arrow-repeat me-1"></i> Apply Current Speed (Send CoA)
                                        </button>
                                    </form>
                                    <small class="text-muted d-block mt-2">
                                        Immediately sends a Change of Authorization to update the user's speed based on current package and active schedules.
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
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
                                        $dur = time() - strtotime($session['session_start']);
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
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($packages as $pkg): ?>
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
                                    </td>
                                    <td><?= $pkg['data_quota_mb'] ? number_format($pkg['data_quota_mb'] / 1024) . ' GB' : 'Unlimited' ?></td>
                                    <td><?= $pkg['simultaneous_sessions'] ?></td>
                                    <td>
                                        <?php if ($pkg['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
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
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Simultaneous Sessions</label>
                                        <input type="number" name="simultaneous_sessions" class="form-control" value="1" min="1">
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
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addNASModal">
                    <i class="bi bi-plus-lg me-1"></i> Add NAS
                </button>
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
                                        <span class="badge bg-secondary">None</span>
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
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="page-title mb-0"><i class="bi bi-clock-history"></i> Expiring Subscribers</h4>
                <form method="post" class="d-inline">
                    <input type="hidden" name="action" value="send_expiry_alerts">
                    <button type="submit" class="btn btn-warning"><i class="bi bi-send me-1"></i> Send Expiry Alerts</button>
                </form>
            </div>
            
            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <?php if (empty($expiringList)): ?>
                    <div class="p-5 text-center text-muted">
                        <i class="bi bi-check-circle fs-1 mb-3 d-block text-success"></i>
                        <h5>All Clear!</h5>
                        <p>No subscribers expiring in the next 14 days.</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Customer</th>
                                    <th>Username</th>
                                    <th>Package</th>
                                    <th>Expiry Date</th>
                                    <th>Days Left</th>
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
                                    <td><code><?= htmlspecialchars($sub['username']) ?></code></td>
                                    <td><?= htmlspecialchars($sub['package_name'] ?? 'N/A') ?></td>
                                    <td><?= date('M j, Y', strtotime($sub['expiry_date'])) ?></td>
                                    <td>
                                        <?php $days = (int)$sub['days_remaining']; ?>
                                        <span class="badge bg-<?= $days <= 1 ? 'danger' : ($days <= 3 ? 'warning' : 'info') ?>">
                                            <?= $days ?> day<?= $days != 1 ? 's' : '' ?>
                                        </span>
                                    </td>
                                    <td>KES <?= number_format($sub['package_price'] ?? 0) ?></td>
                                    <td>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="action" value="renew_subscription">
                                            <input type="hidden" name="subscription_id" value="<?= $sub['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-success"><i class="bi bi-arrow-repeat"></i> Renew</button>
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
            'activate': `Activate subscriber ${username}?`,
            'renew': `Renew subscription for ${username}?`,
            'disconnect': `Disconnect active sessions for ${username}?`,
            'speed_update': `Push speed update (CoA) to ${username}?`,
            'delete': `DELETE subscriber ${username}? This cannot be undone!`
        };
        
        if (!confirm(messages[action] || `Perform ${action} on ${username}?`)) return;
        
        // Use AJAX for real-time actions
        const ajaxActions = ['activate', 'suspend', 'renew', 'disconnect', 'speed_update'];
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
    
    // Connect on page load
    document.addEventListener('DOMContentLoaded', function() {
        connectSSE();
    });
    
    // Cleanup on page unload
    window.addEventListener('beforeunload', function() {
        if (eventSource) eventSource.close();
        if (reconnectTimeout) clearTimeout(reconnectTimeout);
    });
    </script>
</body>
</html>
