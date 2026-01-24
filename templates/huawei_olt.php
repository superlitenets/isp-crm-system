<?php
require_once __DIR__ . '/../src/HuaweiOLT.php';
require_once __DIR__ . '/../src/Branch.php';
$huaweiOLT = new \App\HuaweiOLT($db);
$branchService = new \App\Branch($db);
$allBranches = $branchService->getAll();

$view = $_GET['view'] ?? 'dashboard';
$oltId = isset($_GET['olt_id']) ? (int)$_GET['olt_id'] : null;
$action = $_POST['action'] ?? null;
$message = '';
$messageType = '';

// Handle AJAX GET requests for VPN configs
if (isset($_GET['action']) && $view === 'vpn') {
    require_once __DIR__ . '/../src/WireGuardService.php';
    $wgService = new \App\WireGuardService($db);
    
    header('Content-Type: application/json');
    
    switch ($_GET['action']) {
        case 'get_server_config':
            $serverId = (int)($_GET['server_id'] ?? 0);
            $config = $wgService->getServerConfig($serverId);
            echo json_encode(['success' => true, 'config' => $config, 'name' => 'wg0.conf']);
            exit;
        case 'get_peer_config':
            $peerId = (int)($_GET['peer_id'] ?? 0);
            $config = $wgService->getPeerConfig($peerId);
            $peer = $wgService->getPeer($peerId);
            $name = ($peer['name'] ?? 'peer') . '.conf';
            echo json_encode(['success' => true, 'config' => $config, 'name' => $name]);
            exit;
        case 'get_mikrotik_script':
            $peerId = (int)($_GET['peer_id'] ?? 0);
            $script = $wgService->getMikroTikScript($peerId);
            $peer = $wgService->getPeer($peerId);
            $name = ($peer['name'] ?? 'peer') . '_mikrotik.rsc';
            echo json_encode(['success' => true, 'config' => $script, 'name' => $name]);
            exit;
        case 'get_peer_data':
            $peerId = (int)($_GET['peer_id'] ?? 0);
            $peer = $wgService->getPeer($peerId);
            if (!$peer) {
                echo json_encode(['success' => false, 'error' => 'Peer not found']);
                exit;
            }
            // Get routed subnets for this peer
            $subnets = '';
            try {
                $stmt = $db->prepare("SELECT network_cidr FROM wireguard_subnets WHERE vpn_peer_id = ? AND is_active = TRUE");
                $stmt->execute([$peerId]);
                $subnetRows = $stmt->fetchAll(\PDO::FETCH_COLUMN);
                $subnets = implode("\n", $subnetRows);
            } catch (Exception $e) {}
            echo json_encode(['success' => true, 'peer' => $peer, 'subnets' => $subnets]);
            exit;
        case 'test_peer_connectivity':
            try {
                $peerId = (int)($_GET['peer_id'] ?? 0);
                $results = $wgService->testPeerConnectivity($peerId);
                echo json_encode(['success' => true, 'results' => $results]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
        case 'test_ip':
            $ip = $_GET['ip'] ?? '';
            $results = $wgService->testConnectivity($ip, 3, 2);
            echo json_encode($results);
            exit;
    }
}

// Handle AJAX requests for ONU discovery (async background discovery)
if (isset($_GET['ajax']) && $_GET['ajax'] === 'discover_onus') {
    header('Content-Type: application/json');
    
    $oltId = isset($_GET['olt_id']) ? (int)$_GET['olt_id'] : null;
    
    try {
        if ($oltId) {
            // Discover from specific OLT
            $result = $huaweiOLT->discoverUnconfiguredONUs($oltId);
            if ($result['success']) {
                $method = $result['method'] ?? 'unknown';
                $methodLabel = $method === 'snmp' ? 'SNMP' : ($method === 'cli' ? 'CLI' : $method);
                echo json_encode([
                    'success' => true, 
                    'count' => $result['count'], 
                    'method' => $method,
                    'message' => "Found {$result['count']} unconfigured ONUs via {$methodLabel}"
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => $result['error'] ?? 'Discovery failed']);
            }
        } else {
            // Discover from all OLTs
            $totalFound = 0;
            $results = [];
            $methods = [];
            $allOlts = $huaweiOLT->getOLTs(false);
            foreach ($allOlts as $olt) {
                if ($olt['is_active']) {
                    try {
                        $result = $huaweiOLT->discoverUnconfiguredONUs($olt['id']);
                        if ($result['success']) {
                            $totalFound += $result['count'];
                            $method = $result['method'] ?? 'unknown';
                            $methods[] = $method;
                            $results[] = ['olt' => $olt['name'], 'count' => $result['count'], 'method' => $method, 'success' => true];
                        } else {
                            $results[] = ['olt' => $olt['name'], 'error' => $result['error'] ?? 'failed', 'success' => false];
                        }
                    } catch (Exception $e) {
                        $results[] = ['olt' => $olt['name'], 'error' => $e->getMessage(), 'success' => false];
                    }
                }
            }
            $primaryMethod = in_array('snmp', $methods) ? 'SNMP' : (in_array('cli', $methods) ? 'CLI' : 'unknown');
            echo json_encode([
                'success' => true, 
                'count' => $totalFound, 
                'details' => $results, 
                'method' => $primaryMethod,
                'message' => "Found {$totalFound} unconfigured ONUs via {$primaryMethod}"
            ]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// AJAX endpoint to get current unconfigured ONU count (fast, no discovery)
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_unconfigured_count') {
    header('Content-Type: application/json');
    $oltId = isset($_GET['olt_id']) ? (int)$_GET['olt_id'] : null;
    
    try {
        if ($oltId) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM huawei_onus WHERE olt_id = ? AND is_authorized = false");
            $stmt->execute([$oltId]);
        } else {
            $stmt = $db->query("SELECT COUNT(*) FROM huawei_onus WHERE is_authorized = false");
        }
        $count = $stmt->fetchColumn();
        echo json_encode(['success' => true, 'count' => (int)$count]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// AJAX endpoint to get customers for authorization modal
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_customers_for_auth') {
    header('Content-Type: application/json');
    try {
        $stmt = $db->query("SELECT id, name, phone, account_number FROM customers ORDER BY name LIMIT 5000");
        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'customers' => $customers]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// AJAX endpoint to search billing customers
if (isset($_GET['ajax']) && $_GET['ajax'] === 'search_billing_customer') {
    header('Content-Type: application/json');
    $query = trim($_GET['q'] ?? '');
    if (empty($query)) {
        echo json_encode(['success' => false, 'error' => 'Search query required']);
        exit;
    }
    try {
        // Search in customers table
        $stmt = $db->prepare("
            SELECT id, name, phone, email, account_number 
            FROM customers 
            WHERE name ILIKE ? OR phone ILIKE ? OR account_number ILIKE ?
            ORDER BY name 
            LIMIT 20
        ");
        $search = '%' . $query . '%';
        $stmt->execute([$search, $search, $search]);
        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'customers' => $customers]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// AJAX endpoint for staged authorization (with progress)
if (isset($_GET['ajax']) && $_GET['ajax'] === 'authorize_staged') {
    header('Content-Type: application/json');
    
    // Debug logging function
    $debugLog = function($stage, $message, $data = []) {
        $logFile = '/tmp/auth_debug.log';
        $entry = date('Y-m-d H:i:s') . " [Stage $stage] $message";
        if (!empty($data)) {
            $entry .= " | " . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        }
        @file_put_contents($logFile, $entry . "\n", FILE_APPEND);
    };
    
    $stage = isset($_POST['stage']) ? (int)$_POST['stage'] : 1;
    $response = ['success' => false, 'stage' => $stage, 'next_stage' => null];
    
    $debugLog($stage, 'Starting stage', ['POST' => $_POST]);
    
    try {
        switch ($stage) {
            case 1: // Save ONU details
                $onuId = (int)$_POST['onu_id'];
                $sn = trim($_POST['sn'] ?? '');
                $name = trim($_POST['name'] ?? '');
                $zoneId = !empty($_POST['zone_id']) ? (int)$_POST['zone_id'] : null;
                $zone = $_POST['zone'] ?? '';
                $vlanId = !empty($_POST['vlan_id']) ? (int)$_POST['vlan_id'] : null;
                $address = trim($_POST['address'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $customerId = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
                $latitude = !empty($_POST['latitude']) ? (float)$_POST['latitude'] : null;
                $longitude = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;
                $onuTypeId = !empty($_POST['onu_type_id']) ? (int)$_POST['onu_type_id'] : null;
                $pppoeUsername = trim($_POST['pppoe_username'] ?? '');
                $pppoePassword = trim($_POST['pppoe_password'] ?? '');
                $onuMode = $_POST['onu_mode'] ?? 'router'; // 'router' or 'bridge'
                $oltIdInput = !empty($_POST['olt_id']) ? (int)$_POST['olt_id'] : null;
                $frameSlotPort = trim($_POST['frame_slot_port'] ?? '');
                
                // Ensure ONU exists in database
                $onu = $onuId ? $huaweiOLT->getONU($onuId) : null;
                
                if (!$onu && !empty($sn)) {
                    $existingStmt = $db->prepare("SELECT id FROM huawei_onus WHERE sn = ?");
                    $existingStmt->execute([$sn]);
                    $existingOnuId = $existingStmt->fetchColumn();
                    
                    if ($existingOnuId) {
                        $onuId = (int)$existingOnuId;
                        $onu = $huaweiOLT->getONU($onuId);
                    } else {
                        $discStmt = $db->prepare("SELECT * FROM onu_discovery_log WHERE serial_number = ? ORDER BY last_seen_at DESC LIMIT 1");
                        $discStmt->execute([$sn]);
                        $discoveredOnu = $discStmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($discoveredOnu || (!empty($oltIdInput) && !empty($frameSlotPort))) {
                            $frame = 0; $slot = 0; $port = 0;
                            $fsp = $discoveredOnu['frame_slot_port'] ?? $frameSlotPort;
                            if (preg_match('/(\d+)\/(\d+)\/(\d+)/', $fsp, $fspMatch)) {
                                $frame = (int)$fspMatch[1];
                                $slot = (int)$fspMatch[2];
                                $port = (int)$fspMatch[3];
                            }
                            
                            $oltIdForOnu = $discoveredOnu['olt_id'] ?? $oltIdInput;
                            $onuTypeIdForOnu = $onuTypeId ?: ($discoveredOnu['onu_type_id'] ?? null);
                            
                            $insertStmt = $db->prepare("
                                INSERT INTO huawei_onus (olt_id, sn, name, frame, slot, port, onu_type_id, discovered_eqid, is_authorized, status, created_at)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, false, 'offline', NOW())
                                RETURNING id
                            ");
                            $insertStmt->execute([
                                $oltIdForOnu, $sn, $name ?: $sn, $frame, $slot, $port,
                                $onuTypeIdForOnu, $discoveredOnu['equipment_id'] ?? null
                            ]);
                            $onuId = (int)$insertStmt->fetchColumn();
                            $onu = $huaweiOLT->getONU($onuId);
                        }
                    }
                }
                
                if (!$onu) {
                    throw new Exception('ONU record not found. Please try again.');
                }
                
                // Update ONU record with all fields
                $updateFields = ['name' => $name ?: $sn];
                if (!empty($zone)) $updateFields['zone'] = $zone;
                if ($zoneId) $updateFields['zone_id'] = $zoneId;
                if (!empty($address)) $updateFields['address'] = $address;
                if (!empty($phone)) $updateFields['phone'] = $phone;
                if ($customerId) $updateFields['customer_id'] = $customerId;
                if ($latitude !== null) $updateFields['latitude'] = $latitude;
                if ($longitude !== null) $updateFields['longitude'] = $longitude;
                if ($onuTypeId) $updateFields['onu_type_id'] = $onuTypeId;
                if (!empty($pppoeUsername)) $updateFields['pppoe_username'] = $pppoeUsername;
                if (!empty($pppoePassword)) $updateFields['pppoe_password'] = $pppoePassword;
                $updateFields['installation_date'] = date('Y-m-d');
                $huaweiOLT->updateONU($onuId, $updateFields);
                
                $response['success'] = true;
                $response['message'] = 'ONU details saved';
                $response['next_stage'] = 2;
                $response['onu_id'] = $onuId;
                $response['vlan_id'] = $vlanId;
                break;
                
            case 2: // Authorize on OLT
                $onuId = (int)$_POST['onu_id'];
                $vlanId = !empty($_POST['vlan_id']) ? (int)$_POST['vlan_id'] : null;
                $name = trim($_POST['name'] ?? '');
                $sn = trim($_POST['sn'] ?? '');
                
                $defaultProfile = $huaweiOLT->getDefaultServiceProfile();
                if (!$defaultProfile) {
                    $defaultProfileId = $huaweiOLT->addServiceProfile([
                        'name' => 'Default Internet', 'line_profile' => 1, 'srv_profile' => 1,
                        'download_speed' => 100, 'upload_speed' => 50, 'is_default' => true, 'is_active' => true
                    ]);
                    $defaultProfile = $huaweiOLT->getServiceProfile($defaultProfileId);
                }
                
                $result = $huaweiOLT->authorizeONUStage1($onuId, $defaultProfile['id'], [
                    'description' => $name ?: $sn,
                    'vlan_id' => $vlanId,
                    'skip_service_port' => true // We'll do this in stage 3
                ]);
                
                $debugLog(2, 'Authorization result', [
                    'success' => $result['success'] ?? false,
                    'message' => $result['message'] ?? null,
                    'onu_id' => $result['onu_id'] ?? null
                ]);
                
                if (!$result['success']) {
                    throw new Exception($result['message'] ?? 'Failed to authorize ONU on OLT');
                }
                
                // Verify DB was updated
                $onuAfter = $huaweiOLT->getONU($onuId);
                $debugLog(2, 'ONU after authorization', [
                    'is_authorized' => $onuAfter['is_authorized'] ?? null,
                    'olt_onu_id' => $onuAfter['onu_id'] ?? null
                ]);
                $response['success'] = true;
                $response['message'] = 'ONU authorized as ID ' . ($result['onu_id'] ?? 'N/A');
                $response['next_stage'] = 3;
                $response['olt_onu_id'] = $result['onu_id'] ?? null;
                break;
                
            case 3: // Configure service VLAN
                $onuId = (int)$_POST['onu_id'];
                $vlanId = !empty($_POST['vlan_id']) ? (int)$_POST['vlan_id'] : null;
                
                if ($vlanId) {
                    $onu = $huaweiOLT->getONU($onuId);
                    if ($onu) {
                        $result = $huaweiOLT->attachVlanToONU($onuId, $vlanId);
                        if (!$result['success']) {
                            $response['warning'] = 'Service port config: ' . ($result['message'] ?? 'Check manually');
                        }
                    }
                }
                
                $response['success'] = true;
                $response['message'] = $vlanId ? "Service VLAN $vlanId configured" : 'No VLAN specified';
                $response['next_stage'] = 4;
                break;
                
            case 4: // Configure TR-069
                $onuId = (int)$_POST['onu_id'];
                
                // Get ONU data before calling TR-069 config
                $onuDataBefore = $huaweiOLT->getONU($onuId);
                $debugLog(4, 'Before TR-069 config', [
                    'onu_id' => $onuId,
                    'is_authorized' => $onuDataBefore['is_authorized'] ?? null,
                    'olt_onu_id' => $onuDataBefore['onu_id'] ?? null,
                    'olt_id' => $onuDataBefore['olt_id'] ?? null,
                    'frame_slot_port' => ($onuDataBefore['frame'] ?? '?') . '/'. ($onuDataBefore['slot'] ?? '?') . '/'. ($onuDataBefore['port'] ?? '?')
                ]);
                
                $tr069Result = $huaweiOLT->configureONUStage2TR069($onuId, [
                    'tr069_vlan' => 69,
                    'tr069_gem_port' => 2,
                    'tr069_profile_id' => 3
                ]);
                
                $debugLog(4, 'TR-069 result', $tr069Result);
                $response['success'] = $tr069Result['success'];
                if ($tr069Result['success']) {
                    $response['message'] = 'TR-069 management configured on VLAN 69';
                } else {
                    $response['message'] = 'TR-069 config failed: ' . ($tr069Result['message'] ?? 'Unknown error');
                    $response['error'] = $tr069Result['message'] ?? 'TR-069 configuration failed';
                    $response['debug'] = $tr069Result['output'] ?? '';
                }
                $response['next_stage'] = null; // Done
                $response['redirect'] = '?page=huawei-olt&view=onu_detail&onu_id=' . $onuId;
                break;
        }
    } catch (Exception $e) {
        $response['success'] = false;
        $response['error'] = $e->getMessage();
    }
    
    echo json_encode($response);
    exit;
}

// AJAX endpoint for realtime OMS stats (dashboard refresh)
if (isset($_GET['ajax']) && $_GET['ajax'] === 'realtime_stats') {
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    
    try {
        $stats = $huaweiOLT->getStats();
        
        // Get OLT status counts
        $oltStats = $db->query("
            SELECT 
                COUNT(*) FILTER (WHERE is_active = TRUE) as active_olts,
                COUNT(*) as total_olts
            FROM huawei_olts
        ")->fetch(\PDO::FETCH_ASSOC);
        
        // Get signal health issues
        $signalIssues = $db->query("
            SELECT COUNT(*) FROM huawei_onus 
            WHERE is_authorized = TRUE 
            AND (rx_power < -28 OR rx_power > -8 OR status = 'los')
        ")->fetchColumn();
        
        // Get recent activity (last 5 minutes)
        $recentActivity = $db->query("
            SELECT COUNT(*) FROM huawei_onus 
            WHERE updated_at > NOW() - INTERVAL '5 minutes'
        ")->fetchColumn();
        
        echo json_encode([
            'success' => true,
            'stats' => $stats,
            'olt_stats' => $oltStats,
            'signal_issues' => (int)$signalIssues,
            'recent_activity' => (int)$recentActivity,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// AJAX endpoint for realtime ONU list (with pagination)
if (isset($_GET['ajax']) && $_GET['ajax'] === 'realtime_onus') {
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    
    $oltId = isset($_GET['olt_id']) ? (int)$_GET['olt_id'] : null;
    $unconfigured = isset($_GET['unconfigured']) && $_GET['unconfigured'] == '1';
    $search = $_GET['search'] ?? '';
    $status = $_GET['status'] ?? '';
    
    try {
        $params = [];
        $where = [];
        
        if ($oltId) {
            $where[] = "o.olt_id = ?";
            $params[] = $oltId;
        }
        
        if ($unconfigured) {
            $where[] = "o.is_authorized = FALSE";
        } else {
            $where[] = "o.is_authorized = TRUE";
        }
        
        if ($search) {
            $where[] = "(o.sn ILIKE ? OR o.name ILIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        if ($status) {
            $where[] = "o.status = ?";
            $params[] = $status;
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $sql = "SELECT o.id, o.sn, o.name, o.status, o.rx_power, o.tx_power, 
                       o.frame, o.slot, o.port, o.onu_id, o.olt_id, 
                       o.tr069_ip, o.distance, o.vlan_id,
                       ol.name as olt_name, o.updated_at,
                       c.name as customer_name, z.name as zone_name
                FROM huawei_onus o
                LEFT JOIN huawei_zones z ON o.zone_id = z.id
                LEFT JOIN huawei_olts ol ON o.olt_id = ol.id
                LEFT JOIN customers c ON o.customer_id = c.id
                $whereClause
                ORDER BY o.updated_at DESC
                LIMIT 100";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $onus = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Get discovered ONUs if viewing unconfigured
        $discoveredOnus = [];
        if ($unconfigured) {
            $discSql = "SELECT dl.*, ol.name as olt_name 
                        FROM onu_discovery_log dl
                        LEFT JOIN huawei_olts ol ON dl.olt_id = ol.id
                        WHERE dl.authorized = FALSE 
                        AND dl.last_seen_at > NOW() - INTERVAL '2 hours'
                        ORDER BY dl.last_seen_at DESC";
            if ($oltId) {
                $discSql = "SELECT dl.*, ol.name as olt_name 
                            FROM onu_discovery_log dl
                            LEFT JOIN huawei_olts ol ON dl.olt_id = ol.id
                            WHERE dl.authorized = FALSE 
                            AND dl.olt_id = ?
                            AND dl.last_seen_at > NOW() - INTERVAL '2 hours'
                            ORDER BY dl.last_seen_at DESC";
                $stmt = $db->prepare($discSql);
                $stmt->execute([$oltId]);
            } else {
                $stmt = $db->query($discSql);
            }
            $discoveredOnus = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }
        
        echo json_encode([
            'success' => true,
            'onus' => $onus,
            'discovered_onus' => $discoveredOnus,
            'total_count' => count($onus),
            'discovered_count' => count($discoveredOnus),
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// AJAX endpoint for signal history
if (isset($_GET['ajax']) && $_GET['ajax'] === 'signal_history') {
    header('Content-Type: application/json');
    $onuId = (int)($_GET['onu_id'] ?? 0);
    $days = (int)($_GET['days'] ?? 7);
    $hours = $days * 24;
    
    if (!$onuId) {
        echo json_encode(['success' => false, 'error' => 'ONU ID required']);
        exit;
    }
    
    try {
        $history = $huaweiOLT->getSignalHistory($onuId, $hours);
        echo json_encode(['success' => true, 'history' => $history]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage(), 'history' => []]);
    }
    exit;
}

// AJAX endpoint for WiFi status via TR-069
if (isset($_GET['ajax']) && $_GET['ajax'] === 'wifi_status') {
    header('Content-Type: application/json');
    $deviceId = $_GET['device_id'] ?? '';
    
    if (empty($deviceId)) {
        echo json_encode(['success' => false, 'error' => 'Device ID required']);
        exit;
    }
    
    try {
        require_once __DIR__ . '/../src/GenieACS.php';
        $genieacs = new \App\GenieACS($db);
        
        if (!$genieacs->isConfigured()) {
            echo json_encode(['success' => false, 'error' => 'GenieACS not configured']);
            exit;
        }
        
        $result = $genieacs->getWiFiSettings($deviceId);
        
        if (!$result['success']) {
            echo json_encode(['success' => false, 'error' => $result['error'] ?? 'Failed to fetch WiFi settings']);
            exit;
        }
        
        // Parse the response data - detect available interfaces dynamically
        $data = $result['data'] ?? [];
        $interfaces = [];
        $detectedConfigs = [];
        
        // Scan for all WLANConfiguration instances
        if (is_array($data)) {
            foreach ($data as $param) {
                $name = $param[0] ?? '';
                $value = $param[1] ?? null;
                
                // Match WLANConfiguration.X patterns
                if (preg_match('/WLANConfiguration\.(\d+)\.(\w+)/', $name, $matches)) {
                    $configIndex = $matches[1];
                    $property = $matches[2];
                    
                    if (!isset($detectedConfigs[$configIndex])) {
                        $detectedConfigs[$configIndex] = [
                            'index' => $configIndex,
                            'ssid' => null,
                            'enabled' => false,
                            'channel' => null,
                            'band' => null,
                            'available' => true
                        ];
                    }
                    
                    switch ($property) {
                        case 'SSID':
                            $detectedConfigs[$configIndex]['ssid'] = $value;
                            break;
                        case 'Enable':
                            $detectedConfigs[$configIndex]['enabled'] = ($value === true || $value === 'true' || $value === '1' || $value === 1);
                            break;
                        case 'Channel':
                            $detectedConfigs[$configIndex]['channel'] = $value;
                            // Detect band from channel
                            if ($value !== null && $value > 0) {
                                $detectedConfigs[$configIndex]['band'] = ($value <= 14) ? '2.4GHz' : '5GHz';
                            }
                            break;
                        case 'OperatingFrequencyBand':
                        case 'X_HW_FrequencyBand':
                            // Explicit frequency band indicator
                            if (stripos($value, '5') !== false) {
                                $detectedConfigs[$configIndex]['band'] = '5GHz';
                            } else {
                                $detectedConfigs[$configIndex]['band'] = '2.4GHz';
                            }
                            break;
                    }
                }
            }
        }
        
        // Infer bands from index if not detected
        // HG8546M: WLAN 1-4 = 2.4GHz (1=main, 2-4=guest SSIDs)
        // HG8145V5: WLAN 1-4 = 2.4GHz, WLAN 5 = 5GHz
        foreach ($detectedConfigs as $idx => &$config) {
            if ($config['band'] === null) {
                if ($idx >= 1 && $idx <= 4) {
                    $config['band'] = '2.4GHz';
                    $config['role'] = ($idx == 1) ? 'main' : 'guest';
                } elseif ($idx == 5) {
                    $config['band'] = '5GHz';
                    $config['role'] = 'main';
                } else {
                    $config['band'] = "Radio {$idx}";
                    $config['role'] = 'unknown';
                }
            }
        }
        
        // Build interface list
        foreach ($detectedConfigs as $config) {
            $interfaces[] = $config;
        }
        
        // Sort by index
        usort($interfaces, fn($a, $b) => $a['index'] <=> $b['index']);
        
        // Legacy format for backward compatibility
        $wifi24 = null;
        $wifi5 = null;
        foreach ($interfaces as $iface) {
            if ($iface['band'] === '2.4GHz' && !$wifi24) {
                $wifi24 = $iface;
            } elseif ($iface['band'] === '5GHz' && !$wifi5) {
                $wifi5 = $iface;
            }
        }
        
        echo json_encode([
            'success' => true,
            'interfaces' => $interfaces,
            'wifi_24' => $wifi24,
            'wifi_5' => $wifi5,
            'is_dual_band' => ($wifi24 !== null && $wifi5 !== null)
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// AJAX endpoint for ONU service VLANs
if (isset($_GET['action']) && $_GET['action'] === 'get_onu_service_vlans') {
    header('Content-Type: application/json');
    $onuId = (int)($_GET['onu_id'] ?? 0);
    
    if (!$onuId) {
        echo json_encode(['success' => false, 'error' => 'ONU ID required']);
        exit;
    }
    
    try {
        $stmt = $db->prepare("SELECT * FROM huawei_onu_service_vlans WHERE onu_id = ? ORDER BY priority, vlan_id");
        $stmt->execute([$onuId]);
        $vlans = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'vlans' => $vlans]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// AJAX endpoint for getting OLT VLANs (for dropdown)
if (isset($_GET['action']) && $_GET['action'] === 'get_olt_vlans') {
    header('Content-Type: application/json');
    $oltId = (int)($_GET['olt_id'] ?? 0);
    
    if (!$oltId) {
        echo json_encode(['success' => false, 'error' => 'OLT ID required']);
        exit;
    }
    
    try {
        $stmt = $db->prepare("SELECT vlan_id, description, vlan_type, is_tr069 FROM huawei_vlans WHERE olt_id = ? AND is_active = TRUE ORDER BY vlan_id");
        $stmt->execute([$oltId]);
        $vlans = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'vlans' => $vlans]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// AJAX endpoint for getting VLANs by OLT with PON default
if (isset($_GET['action']) && $_GET['action'] === 'get_auth_vlans') {
    header('Content-Type: application/json');
    $oltId = (int)($_GET['olt_id'] ?? 0);
    $fsp = trim($_GET['fsp'] ?? ''); // frame/slot/port e.g. "0/1/0"
    
    if (!$oltId) {
        echo json_encode(['success' => false, 'error' => 'OLT ID required']);
        exit;
    }
    
    try {
        // Get VLANs for this OLT (exclude TR-069 management VLANs from service VLAN selection)
        $stmt = $db->prepare("SELECT vlan_id, description, vlan_type, is_tr069 FROM huawei_vlans WHERE olt_id = ? AND is_active = TRUE AND (is_tr069 = FALSE OR is_tr069 IS NULL) ORDER BY vlan_id");
        $stmt->execute([$oltId]);
        $vlans = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Get default VLAN for this PON port if fsp provided
        $defaultVlan = null;
        if ($fsp && preg_match('/^(\d+)\/(\d+)\/(\d+)$/', $fsp, $m)) {
            // Try both formats: "0/1/0" and "gpon 0/1/0"
            $stmt = $db->prepare("SELECT default_vlan FROM huawei_olt_pon_ports WHERE olt_id = ? AND (port_name = ? OR port_name = ?)");
            $stmt->execute([$oltId, $fsp, "gpon $fsp"]);
            $ponPort = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($ponPort && $ponPort['default_vlan']) {
                $defaultVlan = (int)$ponPort['default_vlan'];
            }
        }
        
        echo json_encode(['success' => true, 'vlans' => $vlans, 'default_vlan' => $defaultVlan]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// AJAX endpoint for setting PON port default VLAN
if (isset($_GET['action']) && $_GET['action'] === 'set_pon_default_vlan') {
    header('Content-Type: application/json');
    $oltId = (int)($_POST['olt_id'] ?? 0);
    $portName = trim($_POST['port_name'] ?? '');
    $vlanId = !empty($_POST['vlan_id']) ? (int)$_POST['vlan_id'] : null;
    
    if (!$oltId || !$portName) {
        echo json_encode(['success' => false, 'error' => 'OLT ID and port name required']);
        exit;
    }
    
    try {
        $stmt = $db->prepare("UPDATE huawei_olt_pon_ports SET default_vlan = ? WHERE olt_id = ? AND port_name = ?");
        $stmt->execute([$vlanId, $oltId, $portName]);
        
        echo json_encode(['success' => true, 'updated' => $stmt->rowCount()]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// AJAX endpoint for updating PON port description
if (isset($_GET['action']) && $_GET['action'] === 'update_port_description') {
    header('Content-Type: application/json');
    $oltId = (int)($_POST['olt_id'] ?? 0);
    $portName = trim($_POST['port_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    if (!$oltId || !$portName) {
        echo json_encode(['success' => false, 'error' => 'OLT ID and port name required']);
        exit;
    }
    
    try {
        $stmt = $db->prepare("UPDATE huawei_olt_pon_ports SET description = ? WHERE olt_id = ? AND port_name = ?");
        $stmt->execute([$description ?: null, $oltId, $portName]);
        
        echo json_encode(['success' => true, 'updated' => $stmt->rowCount()]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// AJAX endpoint to add service VLAN to ONU
if (isset($_GET['action']) && $_GET['action'] === 'add_onu_service_vlan') {
    header('Content-Type: application/json');
    
    $onuId = (int)($_POST['onu_id'] ?? 0);
    $vlanId = (int)($_POST['vlan_id'] ?? 0);
    $vlanName = trim($_POST['vlan_name'] ?? '');
    $interfaceType = $_POST['interface_type'] ?? 'wifi';
    $portMode = $_POST['port_mode'] ?? 'access';
    $isNative = (int)($_POST['is_native'] ?? 0);
    
    if (!$onuId || !$vlanId) {
        echo json_encode(['success' => false, 'error' => 'ONU ID and VLAN ID required']);
        exit;
    }
    
    try {
        // If setting as native, unset other natives for same interface type
        if ($isNative) {
            $db->prepare("UPDATE huawei_onu_service_vlans SET is_native = FALSE WHERE onu_id = ? AND interface_type = ?")->execute([$onuId, $interfaceType]);
        }
        
        $stmt = $db->prepare("INSERT INTO huawei_onu_service_vlans (onu_id, vlan_id, vlan_name, interface_type, port_mode, is_native) 
                              VALUES (?, ?, ?, ?, ?, ?) 
                              ON CONFLICT (onu_id, vlan_id, interface_type) DO UPDATE 
                              SET vlan_name = EXCLUDED.vlan_name, port_mode = EXCLUDED.port_mode, is_native = EXCLUDED.is_native");
        $stmt->execute([$onuId, $vlanId, $vlanName ?: null, $interfaceType, $portMode, $isNative]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// AJAX endpoint to remove service VLAN from ONU
if (isset($_GET['action']) && $_GET['action'] === 'remove_onu_service_vlan') {
    header('Content-Type: application/json');
    
    $id = (int)($_POST['id'] ?? 0);
    
    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'Record ID required']);
        exit;
    }
    
    try {
        $stmt = $db->prepare("DELETE FROM huawei_onu_service_vlans WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Verify WAN provisioning status
if (isset($_GET['action']) && $_GET['action'] === 'verify_wan_provisioning') {
    header('Content-Type: application/json');
    
    $onuId = (int)($_GET['onu_id'] ?? 0);
    
    if (!$onuId) {
        echo json_encode(['success' => false, 'error' => 'ONU ID required']);
        exit;
    }
    
    try {
        $result = $huaweiOLT->verifyWANProvisioning($onuId);
        echo json_encode($result);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// AJAX endpoint for port capacity
if (isset($_GET['ajax']) && $_GET['ajax'] === 'port_capacity') {
    header('Content-Type: application/json');
    $oltId = (int)($_GET['olt_id'] ?? 0);
    
    if (!$oltId) {
        echo json_encode(['success' => false, 'error' => 'OLT ID required']);
        exit;
    }
    
    $capacity = $huaweiOLT->getPortCapacity($oltId);
    echo json_encode(['success' => true, 'capacity' => $capacity]);
    exit;
}

// AJAX endpoint for uptime stats
if (isset($_GET['ajax']) && $_GET['ajax'] === 'uptime_stats') {
    header('Content-Type: application/json');
    $onuId = (int)($_GET['onu_id'] ?? 0);
    $days = (int)($_GET['days'] ?? 7);
    
    if (!$onuId) {
        echo json_encode(['success' => false, 'error' => 'ONU ID required']);
        exit;
    }
    
    $stats = $huaweiOLT->getUptimeStats($onuId, $days);
    echo json_encode(['success' => true, 'stats' => $stats]);
    exit;
}

// AJAX endpoint for CSV export
if (isset($_GET['ajax']) && $_GET['ajax'] === 'export_csv') {
    $oltId = isset($_GET['olt_id']) ? (int)$_GET['olt_id'] : null;
    $data = $huaweiOLT->exportONUsToCSV($oltId);
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="onus_export_' . date('Y-m-d_His') . '.csv"');
    
    $output = fopen('php://output', 'w');
    if (!empty($data)) {
        fputcsv($output, array_keys($data[0]));
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }
    fclose($output);
    exit;
}

// AJAX endpoint for bulk reboot
if (isset($_GET['ajax']) && $_GET['ajax'] === 'bulk_reboot' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $onuIds = $input['onu_ids'] ?? [];
    
    if (empty($onuIds)) {
        echo json_encode(['success' => false, 'error' => 'No ONUs selected']);
        exit;
    }
    
    $result = $huaweiOLT->bulkReboot($onuIds);
    echo json_encode(['success' => true, 'result' => $result]);
    exit;
}

// AJAX endpoint for bulk delete
if (isset($_GET['ajax']) && $_GET['ajax'] === 'bulk_delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $onuIds = $input['onu_ids'] ?? [];
    
    if (empty($onuIds)) {
        echo json_encode(['success' => false, 'error' => 'No ONUs selected']);
        exit;
    }
    
    $result = $huaweiOLT->bulkDelete($onuIds);
    echo json_encode(['success' => true, 'result' => $result]);
    exit;
}

// AJAX endpoint for customer search (for ONU linking)
if (isset($_GET['ajax']) && $_GET['ajax'] === 'search_customers') {
    header('Content-Type: application/json');
    $phone = $_GET['phone'] ?? '';
    
    if (strlen($phone) < 3) {
        echo json_encode(['success' => true, 'customers' => []]);
        exit;
    }
    
    $customers = $huaweiOLT->findCustomersByPhone($phone);
    echo json_encode(['success' => true, 'customers' => $customers]);
    exit;
}

// AJAX endpoint for linking ONU to customer
if (isset($_GET['ajax']) && $_GET['ajax'] === 'link_customer' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $onuId = (int)($input['onu_id'] ?? 0);
    $customerId = (int)($input['customer_id'] ?? 0);
    
    if (!$onuId || !$customerId) {
        echo json_encode(['success' => false, 'error' => 'ONU and customer ID required']);
        exit;
    }
    
    $result = $huaweiOLT->matchONUToCustomer($onuId, $customerId);
    echo json_encode(['success' => $result]);
    exit;
}

// AJAX endpoint for creating LOS ticket
if (isset($_GET['ajax']) && $_GET['ajax'] === 'create_los_ticket' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $onuId = (int)($input['onu_id'] ?? 0);
    
    if (!$onuId) {
        echo json_encode(['success' => false, 'error' => 'ONU ID required']);
        exit;
    }
    
    $ticketId = $huaweiOLT->createLOSTicket($onuId);
    echo json_encode(['success' => $ticketId !== null, 'ticket_id' => $ticketId]);
    exit;
}

// TR-069 Device Info (GET)
if (isset($_GET['action']) && $_GET['action'] === 'get_tr069_device_info') {
    header('Content-Type: application/json');
    try {
        $onuId = (int)($_GET['onu_id'] ?? 0);
        if (!$onuId) {
            echo json_encode(['success' => false, 'error' => 'ONU ID required']);
            exit;
        }
        
        $stmt = $db->prepare("SELECT sn FROM huawei_onus WHERE id = ?");
        $stmt->execute([$onuId]);
        $onu = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$onu) {
            echo json_encode(['success' => false, 'error' => 'ONU not found']);
            exit;
        }
        
        $genieACS = new \App\GenieACS($db);
        $device = $genieACS->findDeviceBySerial($onu['sn']);
        
        if ($device) {
            echo json_encode([
                'success' => true, 
                'device' => [
                    '_id' => $device['_id'] ?? null,
                    '_lastInform' => $device['_lastInform'] ?? null,
                    '_registered' => $device['_registered'] ?? null,
                    '_deviceId' => $device['_deviceId'] ?? null
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Device not found in GenieACS']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// TR-069 Connection Request (POST)
if (isset($_GET['action']) && $_GET['action'] === 'tr069_connection_request' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    try {
        $onuId = (int)($_POST['onu_id'] ?? $_GET['onu_id'] ?? 0);
        if (!$onuId) {
            echo json_encode(['success' => false, 'error' => 'ONU ID required']);
            exit;
        }
        
        $stmt = $db->prepare("SELECT sn FROM huawei_onus WHERE id = ?");
        $stmt->execute([$onuId]);
        $onu = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$onu) {
            echo json_encode(['success' => false, 'error' => 'ONU not found']);
            exit;
        }
        
        $genieACS = new \App\GenieACS($db);
        $result = $genieACS->sendConnectionRequest($onu['sn']);
        echo json_encode($result);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action) {
    try {
        switch ($action) {
            case 'add_olt':
                $id = $huaweiOLT->addOLT($_POST);
                $message = 'OLT added successfully';
                $messageType = 'success';
                break;
            case 'update_olt':
                $huaweiOLT->updateOLT((int)$_POST['id'], $_POST);
                $message = 'OLT updated successfully';
                $messageType = 'success';
                break;
            case 'delete_olt':
                $huaweiOLT->deleteOLT((int)$_POST['id']);
                $message = 'OLT deleted successfully';
                $messageType = 'success';
                break;
            case 'test_connection':
                $result = $huaweiOLT->testFullConnection((int)$_POST['id']);
                if ($result['overall_success'] ?? false) {
                    $message = "Connected! SNMP: ✓, CLI ({$result['cli']['type']}): ✓. " . ($result['recommendation'] ?? '');
                    $messageType = 'success';
                } else {
                    $parts = [];
                    $parts[] = "SNMP: " . (($result['snmp']['success'] ?? false) ? '✓' : '✗');
                    $parts[] = "CLI: " . (($result['cli']['success'] ?? false) ? '✓' : '✗');
                    $message = implode(', ', $parts) . ". " . ($result['recommendation'] ?? 'Check connection settings.');
                    $messageType = (($result['snmp']['success'] ?? false) || ($result['cli']['success'] ?? false)) ? 'warning' : 'danger';
                }
                break;
            case 'test_ssh_connection':
                $oltId = (int)$_POST['id'];
                $huaweiOLT->disconnectOLTSession($oltId);
                $result = $huaweiOLT->connectToOLTSession($oltId, 'ssh');
                if ($result['success'] ?? false) {
                    $testResult = $huaweiOLT->executeViaService($oltId, 'display version', 15000);
                    if ($testResult['success'] ?? false) {
                        $message = "SSH connection successful! Spaces should now be preserved.";
                        $messageType = 'success';
                    } else {
                        $message = "SSH connected but command failed: " . ($testResult['message'] ?? 'Unknown error');
                        $messageType = 'warning';
                    }
                } else {
                    $message = "SSH connection failed: " . ($result['error'] ?? 'Check if SSH is enabled on OLT');
                    $messageType = 'danger';
                }
                break;
            case 'set_cli_protocol':
                $oltId = (int)$_POST['id'];
                $protocol = in_array($_POST['protocol'] ?? '', ['telnet', 'ssh']) ? $_POST['protocol'] : 'telnet';
                $db->prepare("UPDATE huawei_olts SET cli_protocol = ? WHERE id = ?")->execute([$protocol, $oltId]);
                $huaweiOLT->disconnectOLTSession($oltId);
                $message = "CLI protocol set to " . strtoupper($protocol);
                $messageType = 'success';
                break;
            case 'add_profile':
                $huaweiOLT->addServiceProfile($_POST);
                $message = 'Service profile added successfully';
                $messageType = 'success';
                break;
            case 'update_profile':
                $huaweiOLT->updateServiceProfile((int)$_POST['id'], $_POST);
                $message = 'Service profile updated successfully';
                $messageType = 'success';
                break;
            case 'delete_profile':
                $huaweiOLT->deleteServiceProfile((int)$_POST['id']);
                $message = 'Service profile deleted successfully';
                $messageType = 'success';
                break;
            case 'save_onu_type':
                $typeId = !empty($_POST['id']) ? (int)$_POST['id'] : null;
                $typeData = [
                    'name' => trim($_POST['name'] ?? ''),
                    'model' => trim($_POST['model'] ?? '') ?: trim($_POST['name'] ?? ''),
                    'eth_ports' => (int)($_POST['eth_ports'] ?? 4),
                    'pots_ports' => (int)($_POST['pots_ports'] ?? 0),
                    'wifi_capable' => isset($_POST['wifi_capable']),
                    'default_mode' => $_POST['default_mode'] ?? 'bridge'
                ];
                if ($typeId) {
                    $stmt = $db->prepare("UPDATE huawei_onu_types SET name = ?, model = ?, eth_ports = ?, pots_ports = ?, wifi_capable = ?, default_mode = ? WHERE id = ?");
                    $stmt->execute([$typeData['name'], $typeData['model'], $typeData['eth_ports'], $typeData['pots_ports'], $typeData['wifi_capable'], $typeData['default_mode'], $typeId]);
                    $message = 'ONU type updated successfully';
                } else {
                    $stmt = $db->prepare("INSERT INTO huawei_onu_types (name, model, eth_ports, pots_ports, wifi_capable, default_mode) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$typeData['name'], $typeData['model'], $typeData['eth_ports'], $typeData['pots_ports'], $typeData['wifi_capable'], $typeData['default_mode']]);
                    $message = 'ONU type added successfully';
                }
                $messageType = 'success';
                break;
            case 'delete_onu_type':
                $typeId = (int)$_POST['id'];
                $checkStmt = $db->prepare("SELECT COUNT(*) FROM huawei_onus WHERE onu_type_id = ?");
                $checkStmt->execute([$typeId]);
                if ($checkStmt->fetchColumn() > 0) {
                    $message = 'Cannot delete ONU type - it is being used by existing ONUs';
                    $messageType = 'danger';
                } else {
                    $db->prepare("DELETE FROM huawei_onu_types WHERE id = ?")->execute([$typeId]);
                    $message = 'ONU type deleted successfully';
                    $messageType = 'success';
                }
                break;
            // Location Management
            case 'add_zone':
                $huaweiOLT->addZone($_POST);
                $message = 'Zone added successfully';
                $messageType = 'success';
                break;
            case 'update_zone':
                $huaweiOLT->updateZone((int)$_POST['id'], $_POST);
                $message = 'Zone updated successfully';
                $messageType = 'success';
                break;
            case 'delete_zone':
                $huaweiOLT->deleteZone((int)$_POST['id']);
                $message = 'Zone deleted successfully';
                $messageType = 'success';
                break;
            case 'add_subzone':
                $huaweiOLT->addSubzone($_POST);
                $message = 'Subzone added successfully';
                $messageType = 'success';
                break;
            case 'update_subzone':
                $huaweiOLT->updateSubzone((int)$_POST['id'], $_POST);
                $message = 'Subzone updated successfully';
                $messageType = 'success';
                break;
            case 'delete_subzone':
                $huaweiOLT->deleteSubzone((int)$_POST['id']);
                $message = 'Subzone deleted successfully';
                $messageType = 'success';
                break;
            case 'add_apartment':
                $huaweiOLT->addApartment($_POST);
                $message = 'Apartment/Building added successfully';
                $messageType = 'success';
                break;
            case 'update_apartment':
                $huaweiOLT->updateApartment((int)$_POST['id'], $_POST);
                $message = 'Apartment/Building updated successfully';
                $messageType = 'success';
                break;
            case 'delete_apartment':
                $huaweiOLT->deleteApartment((int)$_POST['id']);
                $message = 'Apartment/Building deleted successfully';
                $messageType = 'success';
                break;
            case 'add_odb':
                $huaweiOLT->addODB($_POST);
                $message = 'ODB added successfully';
                $messageType = 'success';
                break;
            case 'update_odb':
                $huaweiOLT->updateODB((int)$_POST['id'], $_POST);
                $message = 'ODB updated successfully';
                $messageType = 'success';
                break;
            case 'delete_odb':
                $huaweiOLT->deleteODB((int)$_POST['id']);
                $message = 'ODB deleted successfully';
                $messageType = 'success';
                break;
            case 'add_onu':
                $onuData = [
                    'olt_id' => (int)$_POST['olt_id'],
                    'sn' => $_POST['sn'] ?? '',
                    'name' => $_POST['name'] ?? '',
                    'frame' => (int)($_POST['frame'] ?? 0),
                    'slot' => !empty($_POST['slot']) ? (int)$_POST['slot'] : null,
                    'port' => !empty($_POST['port']) ? (int)$_POST['port'] : null,
                    'onu_id' => !empty($_POST['onu_id']) ? (int)$_POST['onu_id'] : null,
                    'customer_id' => !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null,
                    'service_profile_id' => !empty($_POST['service_profile_id']) ? (int)$_POST['service_profile_id'] : null,
                    'is_authorized' => !empty($_POST['is_authorized']),
                ];
                $huaweiOLT->addONU($onuData);
                $message = 'ONU added successfully';
                $messageType = 'success';
                break;
            case 'update_onu':
                $onuData = [
                    'name' => $_POST['name'] ?? '',
                    'frame' => (int)($_POST['frame'] ?? 0),
                    'slot' => !empty($_POST['slot']) ? (int)$_POST['slot'] : null,
                    'port' => !empty($_POST['port']) ? (int)$_POST['port'] : null,
                    'onu_id' => !empty($_POST['onu_id']) ? (int)$_POST['onu_id'] : null,
                    'customer_id' => !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null,
                    'service_profile_id' => !empty($_POST['service_profile_id']) ? (int)$_POST['service_profile_id'] : null,
                ];
                if (isset($_POST['is_authorized'])) {
                    $onuData['is_authorized'] = !empty($_POST['is_authorized']);
                }
                $huaweiOLT->updateONU((int)$_POST['id'], $onuData);
                $message = 'ONU updated successfully';
                $messageType = 'success';
                break;
            case 'delete_onu':
                $result = $huaweiOLT->deleteONU((int)$_POST['id']);
                if ($result['success']) {
                    $message = 'ONU deleted from database';
                    if ($result['deauthorized']) {
                        $message .= ' and deauthorized from OLT';
                    }
                    $messageType = 'success';
                } else {
                    $message = $result['error'] ?? 'Failed to delete ONU';
                    $messageType = 'danger';
                }
                break;
            case 'cleanup_stale_pending':
                $hoursOld = (int)($_POST['hours_old'] ?? 2);
                $cleaned = $huaweiOLT->cleanupStalePendingONUs($hoursOld);
                $total = $cleaned['discovery_log'] + $cleaned['unauthorized_onus'];
                if ($total > 0) {
                    $message = "Cleaned up {$total} stale entries ({$cleaned['discovery_log']} discovery, {$cleaned['unauthorized_onus']} ONUs)";
                    $messageType = 'success';
                } else {
                    $message = 'No stale entries found to clean up';
                    $messageType = 'info';
                }
                break;
            
            case 'configure_tr069':
                $onuId = (int)($_POST['onu_id'] ?? 0);
                $tr069Vlan = !empty($_POST['tr069_vlan']) ? (int)$_POST['tr069_vlan'] : null;
                $acsUrl = !empty($_POST['acs_url']) ? trim($_POST['acs_url']) : null;
                $gemPort = !empty($_POST['gem_port']) ? (int)$_POST['gem_port'] : 2;
                
                if (!$onuId) {
                    $message = 'ONU ID is required';
                    $messageType = 'danger';
                    break;
                }
                
                try {
                    $result = $huaweiOLT->configureTR069Manual($onuId);
                    
                    if ($result['success']) {
                        $message = $result['message'];
                        $messageType = 'success';
                        
                        // Update ONU TR-069 status
                        $huaweiOLT->updateONU($onuId, ['tr069_status' => 'configured']);
                    } else {
                        $message = $result['message'];
                        $messageType = 'warning';
                        
                        // Partial success - update status
                        if (!empty($result['errors'])) {
                            $huaweiOLT->updateONU($onuId, ['tr069_status' => 'partial']);
                        }
                    }
                } catch (Exception $e) {
                    $message = 'TR-069 configuration failed: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;
            case 'configure_wan_pppoe':
                // SmartOLT-style: Configure Internet WAN via TR-069
                require_once __DIR__ . '/../src/GenieACS.php';
                $genieacs = new \App\GenieACS($db);
                $onuId = (int)($_POST['onu_id'] ?? 0);
                
                if (!$onuId) {
                    $message = 'ONU ID is required';
                    $messageType = 'danger';
                    break;
                }
                
                $onu = $huaweiOLT->getONU($onuId);
                if (!$onu) {
                    $message = 'ONU not found';
                    $messageType = 'danger';
                    break;
                }
                
                $deviceResult = $genieacs->getDeviceBySerial($onu['sn']);
                if (!$deviceResult['success']) {
                    $message = 'ONU not found in GenieACS. Please configure TR-069 first.';
                    $messageType = 'warning';
                    break;
                }
                
                $deviceId = $deviceResult['device']['_id'];
                
                $config = [
                    'connection_type' => 'pppoe',
                    'service_vlan' => !empty($_POST['pppoe_vlan']) ? (int)$_POST['pppoe_vlan'] : 902,
                    'wan_index' => 1, // WANConnectionDevice.1 is default (created by OMCI)
                    'connection_name' => 'Internet_PPPoE',
                    'pppoe_username' => trim($_POST['pppoe_username'] ?? ''),
                    'pppoe_password' => trim($_POST['pppoe_password'] ?? ''),
                ];
                
                $result = $genieacs->configureInternetWAN($deviceId, $config);
                
                if ($result['success']) {
                    $message = 'Internet PPPoE configured via TR-069. WAN: ' . ($result['wan_name'] ?? 'wan2.1.ppp1');
                    $messageType = 'success';
                } else {
                    $message = 'Failed to configure PPPoE: ' . implode(', ', $result['errors'] ?? ['Unknown error']);
                    $messageType = 'danger';
                }
                break;
            case 'clear_discovery_entry':
                $huaweiOLT->clearDiscoveryEntry((int)$_POST['id']);
                $message = 'Discovery entry cleared';
                $messageType = 'success';
                break;
            
            // ==================== STAGED PROVISIONING ====================
            
            case 'authorize_stage1':
                // STAGE 1: Authorization + Service Ports ONLY (SmartOLT-style)
                $message = '';
                $messageType = 'danger';
                
                $onuId = (int)$_POST['onu_id'];
                $zoneId = !empty($_POST['zone_id']) ? (int)$_POST['zone_id'] : null;
                $zone = $_POST['zone'] ?? '';
                $vlanId = !empty($_POST['vlan_id']) ? (int)$_POST['vlan_id'] : null;
                $description = trim($_POST['description'] ?? '');
                $sn = trim($_POST['sn'] ?? '');
                $frameSlotPort = trim($_POST['frame_slot_port'] ?? '');
                $oltIdInput = !empty($_POST['olt_id']) ? (int)$_POST['olt_id'] : null;
                $onuTypeId = !empty($_POST['onu_type_id']) ? (int)$_POST['onu_type_id'] : null;
                
                // Auto-generate description from zone if not provided
                if (empty($description) && !empty($zone)) {
                    $description = $zone . '_' . date('Ymd_His');
                }
                
                // Ensure ONU exists in database
                $onu = $onuId ? $huaweiOLT->getONU($onuId) : null;
                
                // If no ONU ID but we have SN, check discovery log or create new ONU
                if (!$onu && !empty($sn)) {
                    $existingStmt = $db->prepare("SELECT id FROM huawei_onus WHERE sn = ?");
                    $existingStmt->execute([$sn]);
                    $existingOnuId = $existingStmt->fetchColumn();
                    
                    if ($existingOnuId) {
                        $onuId = (int)$existingOnuId;
                        $onu = $huaweiOLT->getONU($onuId);
                    } else {
                        // Look up in discovery log
                        $discStmt = $db->prepare("SELECT * FROM onu_discovery_log WHERE serial_number = ? ORDER BY last_seen_at DESC LIMIT 1");
                        $discStmt->execute([$sn]);
                        $discoveredOnu = $discStmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($discoveredOnu || (!empty($oltIdInput) && !empty($frameSlotPort))) {
                            $frame = 0; $slot = 0; $port = 0;
                            $fsp = $discoveredOnu['frame_slot_port'] ?? $frameSlotPort;
                            if (preg_match('/(\d+)\/(\d+)\/(\d+)/', $fsp, $fspMatch)) {
                                $frame = (int)$fspMatch[1];
                                $slot = (int)$fspMatch[2];
                                $port = (int)$fspMatch[3];
                            }
                            
                            $oltIdForOnu = $discoveredOnu['olt_id'] ?? $oltIdInput;
                            $onuTypeIdForOnu = $onuTypeId ?: ($discoveredOnu['onu_type_id'] ?? null);
                            
                            $insertStmt = $db->prepare("
                                INSERT INTO huawei_onus (olt_id, sn, name, frame, slot, port, onu_type_id, discovered_eqid, is_authorized, status, created_at)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, false, 'offline', NOW())
                                RETURNING id
                            ");
                            $insertStmt->execute([
                                $oltIdForOnu, $sn, $description ?: $sn, $frame, $slot, $port,
                                $onuTypeIdForOnu, $discoveredOnu['equipment_id'] ?? null
                            ]);
                            $onuId = (int)$insertStmt->fetchColumn();
                            $onu = $huaweiOLT->getONU($onuId);
                        }
                    }
                }
                
                if (!$onu) {
                    header('Location: ?page=huawei-olt&view=onus&unconfigured=1&msg=' . urlencode('ONU record not found.') . '&msg_type=warning');
                    exit;
                }
                
                // Update ONU record with zone info
                $updateFields = [];
                if (!empty($zone)) $updateFields['zone'] = $zone;
                if ($zoneId) $updateFields['zone_id'] = $zoneId;
                if (!empty($updateFields)) {
                    $huaweiOLT->updateONU($onuId, $updateFields);
                }
                
                // Get default profile
                $defaultProfile = $huaweiOLT->getDefaultServiceProfile();
                if (!$defaultProfile) {
                    $defaultProfileId = $huaweiOLT->addServiceProfile([
                        'name' => 'Default Internet', 'line_profile' => 1, 'srv_profile' => 1,
                        'download_speed' => 100, 'upload_speed' => 50, 'is_default' => true, 'is_active' => true
                    ]);
                    $defaultProfile = $huaweiOLT->getServiceProfile($defaultProfileId);
                }
                
                // Execute Stage 1: Authorization + Service Ports
                try {
                    $result = $huaweiOLT->authorizeONUStage1($onuId, $defaultProfile['id'], [
                        'description' => $description,
                        'vlan_id' => $vlanId
                    ]);
                    
                    if ($result['success']) {
                        $message = "Stage 1 Complete: ONU authorized as ID {$result['onu_id']}";
                        if ($result['service_port_success']) {
                            $message .= ", VLAN {$vlanId} configured";
                        }
                        $message .= ". Proceed to Stage 2 (TR-069) when ready.";
                        $messageType = 'success';
                    } else {
                        $message = "Stage 1 Failed: " . ($result['message'] ?? 'Unknown error');
                        $messageType = 'danger';
                    }
                } catch (Exception $e) {
                    $message = 'Stage 1 Failed: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                
                // Redirect to ONU detail page to continue with next stages
                header('Location: ?page=huawei-olt&view=onu_detail&onu_id=' . $onuId . '&msg=' . urlencode($message) . '&msg_type=' . $messageType);
                exit;
                break;
            
            case 'authorize_onu_simple':
                // ENHANCED AUTHORIZATION: Name, Phone, Customer, Zone, Address, GPS, VLAN, PPPoE
                // Auto-configures TR-069 WAN in background and redirects to ONU config page
                $message = '';
                $messageType = 'danger';
                
                $onuId = (int)$_POST['onu_id'];
                $zoneId = !empty($_POST['zone_id']) ? (int)$_POST['zone_id'] : null;
                $zone = $_POST['zone'] ?? '';
                $vlanId = !empty($_POST['vlan_id']) ? (int)$_POST['vlan_id'] : null;
                $name = trim($_POST['name'] ?? '');
                $address = trim($_POST['address'] ?? '');
                $sn = trim($_POST['sn'] ?? '');
                $frameSlotPort = trim($_POST['frame_slot_port'] ?? '');
                $oltIdInput = !empty($_POST['olt_id']) ? (int)$_POST['olt_id'] : null;
                $onuTypeId = !empty($_POST['onu_type_id']) ? (int)$_POST['onu_type_id'] : null;
                
                // New enhanced fields
                $phone = trim($_POST['phone'] ?? '');
                $customerId = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
                $latitude = !empty($_POST['latitude']) ? (float)$_POST['latitude'] : null;
                $longitude = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;
                $onuMode = $_POST['onu_mode'] ?? 'router'; // 'router' or 'bridge'
                
                // Handle customer creation/selection modes
                $customerMode = $_POST['customer_mode'] ?? 'existing';
                $createCustomer = ($_POST['create_customer'] ?? '0') === '1';
                $billingCustomerId = !empty($_POST['billing_customer_id']) ? (int)$_POST['billing_customer_id'] : null;
                
                // Create new customer if requested
                if ($createCustomer && !$customerId) {
                    $newCustomerName = trim($_POST['new_customer_name'] ?? '') ?: $name;
                    $newCustomerPhone = trim($_POST['new_customer_phone'] ?? '') ?: $phone;
                    $newCustomerEmail = trim($_POST['new_customer_email'] ?? '');
                    
                    if (!empty($newCustomerName)) {
                        try {
                            $insertCust = $db->prepare("
                                INSERT INTO customers (name, phone, email, created_at)
                                VALUES (?, ?, ?, NOW())
                                RETURNING id
                            ");
                            $insertCust->execute([$newCustomerName, $newCustomerPhone, $newCustomerEmail ?: null]);
                            $customerId = (int)$insertCust->fetchColumn();
                            $name = $newCustomerName;
                            $phone = $newCustomerPhone;
                        } catch (Exception $e) {
                            // Log but don't fail - customer creation is optional
                            error_log("Failed to create customer: " . $e->getMessage());
                        }
                    }
                } elseif ($billingCustomerId && !$customerId) {
                    $customerId = $billingCustomerId;
                }
                
                // Ensure ONU exists in database
                $onu = $onuId ? $huaweiOLT->getONU($onuId) : null;
                
                // If no ONU ID but we have SN, check discovery log or create new ONU
                if (!$onu && !empty($sn)) {
                    $existingStmt = $db->prepare("SELECT id FROM huawei_onus WHERE sn = ?");
                    $existingStmt->execute([$sn]);
                    $existingOnuId = $existingStmt->fetchColumn();
                    
                    if ($existingOnuId) {
                        $onuId = (int)$existingOnuId;
                        $onu = $huaweiOLT->getONU($onuId);
                    } else {
                        $discStmt = $db->prepare("SELECT * FROM onu_discovery_log WHERE serial_number = ? ORDER BY last_seen_at DESC LIMIT 1");
                        $discStmt->execute([$sn]);
                        $discoveredOnu = $discStmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($discoveredOnu || (!empty($oltIdInput) && !empty($frameSlotPort))) {
                            $frame = 0; $slot = 0; $port = 0;
                            $fsp = $discoveredOnu['frame_slot_port'] ?? $frameSlotPort;
                            if (preg_match('/(\d+)\/(\d+)\/(\d+)/', $fsp, $fspMatch)) {
                                $frame = (int)$fspMatch[1];
                                $slot = (int)$fspMatch[2];
                                $port = (int)$fspMatch[3];
                            }
                            
                            $oltIdForOnu = $discoveredOnu['olt_id'] ?? $oltIdInput;
                            $onuTypeIdForOnu = $onuTypeId ?: ($discoveredOnu['onu_type_id'] ?? null);
                            
                            $insertStmt = $db->prepare("
                                INSERT INTO huawei_onus (olt_id, sn, name, frame, slot, port, onu_type_id, discovered_eqid, is_authorized, status, created_at)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, false, 'offline', NOW())
                                RETURNING id
                            ");
                            $insertStmt->execute([
                                $oltIdForOnu, $sn, $name ?: $sn, $frame, $slot, $port,
                                $onuTypeIdForOnu, $discoveredOnu['equipment_id'] ?? null
                            ]);
                            $onuId = (int)$insertStmt->fetchColumn();
                            $onu = $huaweiOLT->getONU($onuId);
                        }
                    }
                }
                
                if (!$onu) {
                    header('Location: ?page=huawei-olt&view=onus&unconfigured=1&msg=' . urlencode('ONU record not found.') . '&msg_type=warning');
                    exit;
                }
                
                // Update ONU record with all new fields
                $updateFields = ['name' => $name ?: $sn];
                if (!empty($zone)) $updateFields['zone'] = $zone;
                if ($zoneId) $updateFields['zone_id'] = $zoneId;
                if (!empty($address)) $updateFields['address'] = $address;
                if (!empty($phone)) $updateFields['phone'] = $phone;
                if ($customerId) $updateFields['customer_id'] = $customerId;
                if ($latitude !== null) $updateFields['latitude'] = $latitude;
                if ($longitude !== null) $updateFields['longitude'] = $longitude;
                if ($onuTypeId) $updateFields['onu_type_id'] = $onuTypeId;
                if (!empty($pppoeUsername)) $updateFields['pppoe_username'] = $pppoeUsername;
                if (!empty($pppoePassword)) $updateFields['pppoe_password'] = $pppoePassword;
                $updateFields['installation_date'] = date('Y-m-d'); // Auto-set installation date
                $huaweiOLT->updateONU($onuId, $updateFields);
                
                // Get default profile
                $defaultProfile = $huaweiOLT->getDefaultServiceProfile();
                if (!$defaultProfile) {
                    $defaultProfileId = $huaweiOLT->addServiceProfile([
                        'name' => 'Default Internet', 'line_profile' => 1, 'srv_profile' => 1,
                        'download_speed' => 100, 'upload_speed' => 50, 'is_default' => true, 'is_active' => true
                    ]);
                    $defaultProfile = $huaweiOLT->getServiceProfile($defaultProfileId);
                }
                
                $authMessages = [];
                $hasError = false;
                
                // Step 1: Authorize ONU on OLT with service VLAN
                try {
                    $result = $huaweiOLT->authorizeONUStage1($onuId, $defaultProfile['id'], [
                        'description' => $name ?: $sn,
                        'vlan_id' => $vlanId
                    ]);
                    
                    if ($result['success']) {
                        $authMessages[] = "ONU authorized as ID {$result['onu_id']}";
                        if ($result['service_port_success']) {
                            $authMessages[] = "Service VLAN {$vlanId} configured";
                        }
                    } else {
                        $authMessages[] = "Authorization warning: " . ($result['message'] ?? 'See OLT');
                    }
                } catch (Exception $e) {
                    $authMessages[] = "Authorization failed: " . $e->getMessage();
                    $hasError = true;
                }
                
                // Step 2: Auto-configure TR-069 WAN (IPHOST on VLAN 69 with DHCP)
                if (!$hasError) {
                    try {
                        $tr069Result = $huaweiOLT->configureONUStage2TR069($onuId, [
                            'tr069_vlan' => 69,
                            'tr069_gem_port' => 2,
                            'tr069_profile_id' => 3
                        ]);
                        
                        if ($tr069Result['success']) {
                            $authMessages[] = "TR-069 WAN configured on VLAN 69";
                        } else {
                            $authMessages[] = "TR-069 config info: " . ($tr069Result['message'] ?? 'Pending');
                        }
                    } catch (Exception $e) {
                        $authMessages[] = "TR-069 setup: " . $e->getMessage();
                    }
                }
                
                // Build final message
                $message = implode('. ', $authMessages) . '.';
                $messageType = $hasError ? 'warning' : 'success';
                
                // Redirect to ONU configuration page
                header('Location: ?page=huawei-olt&view=onu_detail&onu_id=' . $onuId . '&msg=' . urlencode($message) . '&msg_type=' . $messageType);
                exit;
                break;
                
            case 'configure_stage2_tr069':
                // STAGE 2: TR-069 Configuration (after authorization)
                $onuId = (int)$_POST['onu_id'];
                
                try {
                    $result = $huaweiOLT->configureONUStage2TR069($onuId, [
                        'tr069_vlan' => !empty($_POST['tr069_vlan']) ? (int)$_POST['tr069_vlan'] : null,
                        'tr069_gem_port' => !empty($_POST['tr069_gem_port']) ? (int)$_POST['tr069_gem_port'] : 2,
                        'tr069_profile_id' => 3
                    ]);
                    
                    if ($result['success']) {
                        $message = "Stage 2 Complete: TR-069 configured on VLAN {$result['tr069_vlan']}. Device should connect to GenieACS shortly.";
                        $messageType = 'success';
                    } else {
                        $message = "Stage 2 Failed: " . ($result['message'] ?? 'Unknown error');
                        $messageType = 'danger';
                    }
                } catch (Exception $e) {
                    $message = 'Stage 2 Failed: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                
                header('Location: ?page=huawei-olt&view=onu_detail&onu_id=' . $onuId . '&msg=' . urlencode($message) . '&msg_type=' . $messageType);
                exit;
                break;
                
            case 'verify_onu_online':
                // Verify ONU is online (AJAX endpoint)
                $onuId = (int)$_POST['onu_id'];
                $result = $huaweiOLT->verifyONUOnline($onuId);
                header('Content-Type: application/json');
                echo json_encode($result);
                exit;
                break;
                
            case 'get_provisioning_stage':
                // Get current provisioning stage (AJAX endpoint)
                $onuId = (int)$_POST['onu_id'];
                $result = $huaweiOLT->getONUProvisioningStage($onuId);
                header('Content-Type: application/json');
                echo json_encode($result);
                exit;
                break;
            
            case 'quick_authorize':
                $onuId = (int)$_POST['id'];
                $huaweiOLT->updateONU($onuId, ['is_authorized' => true]);
                $message = 'ONU authorized successfully. You can now configure it.';
                $messageType = 'success';
                header('Location: ?page=huawei-olt&view=onu_detail&onu_id=' . $onuId);
                exit;
                break;
            case 'authorize_onu':
                // Initialize message variables
                $message = '';
                $messageType = 'danger';
                
                $onuId = (int)$_POST['onu_id'];
                $zoneId = !empty($_POST['zone_id']) ? (int)$_POST['zone_id'] : null;
                $zone = $_POST['zone'] ?? '';
                $vlanId = !empty($_POST['vlan_id']) ? (int)$_POST['vlan_id'] : null;
                $description = trim($_POST['description'] ?? '');
                $sn = trim($_POST['sn'] ?? '');
                $frameSlotPort = trim($_POST['frame_slot_port'] ?? '');
                $oltIdInput = !empty($_POST['olt_id']) ? (int)$_POST['olt_id'] : null;
                $onuTypeId = !empty($_POST['onu_type_id']) ? (int)$_POST['onu_type_id'] : null;
                
                // Auto-generate description from zone if not provided
                if (empty($description) && !empty($zone)) {
                    $description = $zone . '_' . date('Ymd_His');
                }
                
                // Ensure ONU exists in database
                $onu = $onuId ? $huaweiOLT->getONU($onuId) : null;
                
                // If no ONU ID but we have SN, check discovery log or create new ONU
                if (!$onu && !empty($sn)) {
                    // Check if ONU exists by serial number first
                    $existingStmt = $db->prepare("SELECT id FROM huawei_onus WHERE sn = ?");
                    $existingStmt->execute([$sn]);
                    $existingOnuId = $existingStmt->fetchColumn();
                    
                    if ($existingOnuId) {
                        $onuId = (int)$existingOnuId;
                        $onu = $huaweiOLT->getONU($onuId);
                    } else {
                        // Look up in discovery log
                        $discStmt = $db->prepare("SELECT * FROM onu_discovery_log WHERE serial_number = ? ORDER BY last_seen_at DESC LIMIT 1");
                        $discStmt->execute([$sn]);
                        $discoveredOnu = $discStmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($discoveredOnu || (!empty($oltIdInput) && !empty($frameSlotPort))) {
                            // Parse frame/slot/port
                            $frame = 0; $slot = 0; $port = 0;
                            $fsp = $discoveredOnu['frame_slot_port'] ?? $frameSlotPort;
                            if (preg_match('/(\d+)\/(\d+)\/(\d+)/', $fsp, $fspMatch)) {
                                $frame = (int)$fspMatch[1];
                                $slot = (int)$fspMatch[2];
                                $port = (int)$fspMatch[3];
                            }
                            
                            // Create new ONU record from discovery
                            $oltIdForOnu = $discoveredOnu['olt_id'] ?? $oltIdInput;
                            $onuTypeIdForOnu = $onuTypeId ?: ($discoveredOnu['onu_type_id'] ?? null);
                            
                            $insertStmt = $db->prepare("
                                INSERT INTO huawei_onus (olt_id, sn, name, frame, slot, port, onu_type_id, discovered_eqid, is_authorized, status, created_at)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, false, 'offline', NOW())
                                RETURNING id
                            ");
                            $insertStmt->execute([
                                $oltIdForOnu,
                                $sn,
                                $description ?: $sn,
                                $frame,
                                $slot,
                                $port,
                                $onuTypeIdForOnu,
                                $discoveredOnu['equipment_id'] ?? null
                            ]);
                            $onuId = (int)$insertStmt->fetchColumn();
                            $onu = $huaweiOLT->getONU($onuId);
                            
                            // Mark as authorized in discovery log
                            $db->prepare("UPDATE onu_discovery_log SET authorized = true, authorized_at = NOW() WHERE serial_number = ?")->execute([$sn]);
                        }
                    }
                }
                
                if (!$onu) {
                    // ONU not found - redirect with error
                    header('Location: ?page=huawei-olt&view=onus&unconfigured=1&msg=' . urlencode('ONU record not found. Please provide SN and OLT info.') . '&msg_type=warning');
                    exit;
                }
                
                // Ensure a default service profile exists
                $defaultProfile = $huaweiOLT->getDefaultServiceProfile();
                if (!$defaultProfile) {
                    // Create a default profile if none exists
                    $defaultProfileId = $huaweiOLT->addServiceProfile([
                        'name' => 'Default Internet',
                        'line_profile' => 1,
                        'srv_profile' => 1,
                        'download_speed' => 100,
                        'upload_speed' => 50,
                        'is_default' => true,
                        'is_active' => true
                    ]);
                    $defaultProfile = $huaweiOLT->getServiceProfile($defaultProfileId);
                }
                $profileId = $defaultProfile['id'];
                
                // Update ONU record with zone info before authorization
                $updateFields = [];
                if (!empty($zone)) $updateFields['zone'] = $zone;
                if ($zoneId) $updateFields['zone_id'] = $zoneId;
                if ($vlanId) $updateFields['vlan_id'] = $vlanId;
                if (!empty($updateFields)) {
                    $huaweiOLT->updateONU($onuId, $updateFields);
                }
                
                // Build options with VLAN for service-port command
                $options = [
                    'description' => $description,
                    'vlan_id' => $vlanId
                ];
                
                // Get TR-069 ACS URL for display
                require_once __DIR__ . '/../src/WireGuardService.php';
                $wgService = new \App\WireGuardService($db);
                $acsUrl = $wgService->getTR069AcsUrl();
                
                // Try to execute actual OLT authorization
                try {
                    $result = $huaweiOLT->authorizeONU($onuId, $profileId, 'sn', '', '', $options);
                    
                    if ($result['success']) {
                        $assignedOnuId = $result['onu_id'] ?? '';
                        $tr069Status = $result['tr069_status'] ?? ['attempted' => false];
                        
                        // Fetch optical power and distance immediately after authorization
                        $opticalInfo = '';
                        try {
                            $opticalResult = $huaweiOLT->refreshONUOptical($onuId);
                            if ($opticalResult['success']) {
                                $rxPower = $opticalResult['rx_power'] ?? 'N/A';
                                $txPower = $opticalResult['tx_power'] ?? 'N/A';
                                $distance = $opticalResult['distance'] ?? 'N/A';
                                $opticalInfo = " | RX: {$rxPower}dBm, TX: {$txPower}dBm, Distance: {$distance}m";
                            }
                        } catch (Exception $e) {
                            // Optical fetch failed, continue without it
                        }
                        
                        $message = "ONU authorized successfully! ";
                        $message .= $assignedOnuId ? "ONU ID: {$assignedOnuId}" : "";
                        $message .= $opticalInfo;
                        $message .= " | VLAN: " . ($vlanId ?: 'default') . ". ";
                        
                        // Detailed TR-069 status notification
                        if (isset($tr069Status['attempted']) && $tr069Status['attempted']) {
                            if ($tr069Status['success']) {
                                $message .= "TR-069 configured: VLAN {$tr069Status['vlan']}";
                                $messageType = 'success';
                                $huaweiOLT->updateONU($onuId, [
                                    'tr069_status' => 'configured',
                                    'onu_id' => $assignedOnuId ?: $onu['onu_id']
                                ]);
                            } else {
                                $message .= "TR-069 FAILED - use manual configuration button below.";
                                $messageType = 'warning';
                                $huaweiOLT->updateONU($onuId, [
                                    'tr069_status' => 'failed',
                                    'onu_id' => $assignedOnuId ?: $onu['onu_id']
                                ]);
                            }
                        } else {
                            $reason = $tr069Status['reason'] ?? 'not configured';
                            $message .= "TR-069 skipped: {$reason}";
                            $messageType = 'success';
                            $huaweiOLT->updateONU($onuId, [
                                'tr069_status' => 'skipped',
                                'onu_id' => $assignedOnuId ?: $onu['onu_id']
                            ]);
                        }
                    } else {
                        // CLI command failed - do NOT mark as authorized
                        $message = "Authorization failed: " . ($result['message'] ?? 'OLT command failed');
                        $messageType = 'danger';
                    }
                } catch (Exception $e) {
                    // Connection failed - do NOT mark as authorized
                    $message = 'Authorization failed: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                
                // Only mark discovery log and queue TR-069 if authorization succeeded
                if ($messageType === 'success') {
                    // Mark discovery log entry as authorized (remove from pending list)
                    $onuSn = $onu['sn'] ?? $sn;
                    if (!empty($onuSn)) {
                        $db->prepare("UPDATE onu_discovery_log SET authorized = true, authorized_at = NOW() WHERE serial_number = ?")->execute([$onuSn]);
                    }
                }
                
                // Configure PPPoE WAN via OMCI if credentials provided and auth succeeded
                // Use vlan_id (service VLAN) as the PPPoE VLAN - no separate wan_vlan needed
                $serviceVlan = !empty($_POST['vlan_id']) ? (int)$_POST['vlan_id'] : 902;
                $pppoeConfigured = false;
                if ($messageType === 'success' && !empty($_POST['pppoe_username']) && !empty($_POST['pppoe_password'])) {
                    try {
                        $pppoeConfig = [
                            'pppoe_vlan' => $serviceVlan,
                            'pppoe_username' => $_POST['pppoe_username'],
                            'pppoe_password' => $_POST['pppoe_password'],
                            'gemport' => 1,
                            'nat_enabled' => true,
                            'priority' => 0
                        ];
                        $pppoeResult = $huaweiOLT->configureWANPPPoE($onuId, $pppoeConfig);
                        if ($pppoeResult['success']) {
                            $pppoeConfigured = true;
                            $message .= ' PPPoE WAN configured via OMCI.';
                        } else {
                            $message .= ' PPPoE OMCI config: ' . ($pppoeResult['message'] ?? 'partial');
                        }
                    } catch (Exception $e) {
                        error_log("PPPoE OMCI config failed: " . $e->getMessage());
                    }
                }
                
                // Configure bridge mode if selected
                if ($messageType === 'success' && isset($onuMode) && $onuMode === 'bridge') {
                    try {
                        // Store bridge mode in database
                        $db->prepare("UPDATE huawei_onus SET onu_mode = ? WHERE id = ?")->execute(['bridge', $onuId]);
                        
                        // Configure all LAN ports as bridged via OMCI (native VLAN on all ports)
                        $bridgeVlan = null; // Will use attached service VLAN from ONU record
                        $bridgeResult = $huaweiOLT->configureBridgeMode($onuId, $bridgeVlan);
                        if ($bridgeResult['success']) {
                            $message .= ' Bridge mode configured.';
                        } else {
                            $message .= ' Bridge mode partial: ' . ($bridgeResult['message'] ?? 'check manually');
                        }
                    } catch (Exception $e) {
                        error_log("Bridge mode config failed: " . $e->getMessage());
                    }
                } else if ($messageType === 'success') {
                    // Default router mode
                    $db->prepare("UPDATE huawei_onus SET onu_mode = ? WHERE id = ?")->execute(['router', $onuId]);
                }

                // Queue TR-069 configuration if PPPoE settings provided and auth succeeded
                $tr069Queued = false;
                if ($messageType === 'success' && !empty($_POST['pppoe_username'])) {
                    // Store TR-069 config to be applied when device connects to ACS
                    $tr069Config = [
                        'onu_id' => $onuId,
                        'wan_vlan' => $serviceVlan,
                        'connection_type' => $_POST['connection_type'] ?? 'pppoe',
                        'pppoe_username' => $_POST['pppoe_username'] ?? '',
                        'pppoe_password' => $_POST['pppoe_password'] ?? '',
                        'nat_enable' => true,
                        'wifi_ssid_24' => '',
                        'wifi_pass_24' => '',
                        'wifi_ssid_5' => '',
                        'wifi_pass_5' => '',
                        'pppoe_omci_configured' => $pppoeConfigured
                    ];
                    
                    // Ensure TR-069 config table exists with all required columns
                    $db->exec("CREATE TABLE IF NOT EXISTS huawei_onu_tr069_config (
                        onu_id INTEGER PRIMARY KEY,
                        config_data TEXT,
                        status VARCHAR(20) DEFAULT 'pending',
                        error_message TEXT,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP,
                        applied_at TIMESTAMP
                    )");
                    // Add columns for existing installations (silently ignore if exists)
                    try { $db->exec("ALTER TABLE huawei_onu_tr069_config ADD COLUMN error_message TEXT"); } catch (Exception $e) {}
                    try { $db->exec("ALTER TABLE huawei_onu_tr069_config ADD COLUMN applied_at TIMESTAMP"); } catch (Exception $e) {}
                    
                    // Store pending TR-069 config in database (clear error_message on re-queue)
                    $stmt = $db->prepare("
                        INSERT INTO huawei_onu_tr069_config (onu_id, config_data, status, error_message, created_at)
                        VALUES (?, ?, 'pending', NULL, CURRENT_TIMESTAMP)
                        ON CONFLICT (onu_id) DO UPDATE SET
                            config_data = EXCLUDED.config_data,
                            status = 'pending',
                            error_message = NULL,
                            updated_at = CURRENT_TIMESTAMP
                    ");
                    try {
                        $stmt->execute([$onuId, json_encode($tr069Config)]);
                        $tr069Queued = true;
                        $message .= ' TR-069 config queued for push.';
                    } catch (Exception $e) {
                        // Log error but don't fail the ONU authorization
                        error_log("TR-069 config queue failed: " . $e->getMessage());
                    }
                }
                
                // Redirect back to authorized ONUs list
                header('Location: ?page=huawei-olt&view=onus&msg=' . urlencode($message) . '&msg_type=' . $messageType);
                exit;
                break;
            case 'reboot_onu':
                $result = $huaweiOLT->rebootONU((int)$_POST['onu_id']);
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
            case 'force_tr069_reconnect':
                $onuId = (int)$_POST['onu_id'];
                // First ensure periodic inform is enabled
                $onu = $huaweiOLT->getONU($onuId);
                if ($onu) {
                    $olt = $huaweiOLT->getOLT($onu['olt_id']);
                    $frame = $onu['frame'] ?? 0;
                    $slot = $onu['slot'];
                    $port = (int)$onu['port'];
                    $onuIdNum = (int)$onu['onu_id'];
                    
                    // Enable periodic inform (300 seconds = 5 minutes)
                    $cmd = "interface gpon {$frame}/{$slot}\r\n";
                    $cmd .= "ont tr069-server-config {$port} {$onuIdNum} periodic-inform enable interval 300\r\n";
                    $cmd .= "quit";
                    $huaweiOLT->executeCommand($onu['olt_id'], $cmd);
                    
                    // Reboot the ONU to force immediate reconnection
                    $result = $huaweiOLT->rebootONU($onuId);
                    if ($result['success']) {
                        $message = 'ONU rebooting to re-establish TR-069 connection. Wait 2-3 minutes for it to come online.';
                        $messageType = 'success';
                    } else {
                        $message = 'Failed to reboot ONU: ' . ($result['message'] ?? 'Unknown error');
                        $messageType = 'danger';
                    }
                } else {
                    $message = 'ONU not found';
                    $messageType = 'danger';
                }
                break;
            case 'delete_onu_olt':
                $result = $huaweiOLT->deleteONUFromOLT((int)$_POST['onu_id']);
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
            case 'apply_port_template':
                $onuId = (int)$_POST['onu_id'];
                $template = $_POST['template'] ?? '';
                $result = $huaweiOLT->applyPortTemplate($onuId, $template);
                if ($result['success']) {
                    $message = "Applied '{$template}' template to {$result['eth_ports']} ports. Configuration pushed via OMCI.";
                    $messageType = 'success';
                } else {
                    $message = 'Failed to apply template: ' . ($result['error'] ?? 'Unknown error');
                    $messageType = 'danger';
                }
                header('Location: ?page=huawei-olt&view=onu_detail&onu_id=' . $onuId . '&msg=' . urlencode($message) . '&msg_type=' . $messageType);
                exit;
                break;
            case 'configure_onu_ports':
                $onuId = (int)$_POST['onu_id'];
                $portData = $_POST['port'] ?? [];
                $portConfigs = [];
                foreach ($portData as $ethPort => $config) {
                    $portConfigs[(int)$ethPort] = [
                        'mode' => $config['mode'] ?? 'transparent',
                        'vlan_id' => !empty($config['vlan_id']) ? (int)$config['vlan_id'] : null,
                        'allowed_vlans' => $config['allowed_vlans'] ?? '',
                        'priority' => (int)($config['priority'] ?? 0),
                        'desc' => $config['desc'] ?? ''
                    ];
                }
                $result = $huaweiOLT->configureOnuPorts($onuId, $portConfigs);
                if ($result['success']) {
                    $message = "Configured {$result['ports_configured']} ports successfully. OMCI commands sent to OLT.";
                    $messageType = 'success';
                } else {
                    $message = 'Port configuration failed: ' . ($result['error'] ?? 'Unknown error');
                    $messageType = 'danger';
                }
                header('Location: ?page=huawei-olt&view=onu_detail&onu_id=' . $onuId . '&msg=' . urlencode($message) . '&msg_type=' . $messageType);
                exit;
                break;
            case 'configure_eth_port_omci':
                $onuId = (int)$_POST['onu_id'];
                $portNum = (int)$_POST['port_num'];
                $portConfig = [
                    $portNum => [
                        'mode' => $_POST['port_mode'] ?? 'transparent',
                        'vlan_id' => !empty($_POST['vlan_id']) ? (int)$_POST['vlan_id'] : null,
                        'allowed_vlans' => $_POST['allowed_vlans'] ?? '',
                        'priority' => (int)($_POST['priority'] ?? 0)
                    ]
                ];
                $result = $huaweiOLT->configureOnuPorts($onuId, $portConfig);
                if ($result['success']) {
                    $message = "ETH port {$portNum} configured successfully via OMCI.";
                    $messageType = 'success';
                } else {
                    $message = 'Port configuration failed: ' . ($result['error'] ?? 'Unknown error');
                    $messageType = 'danger';
                }
                header('Location: ?page=huawei-olt&view=onu_detail&onu_id=' . $onuId . '&msg=' . urlencode($message) . '&msg_type=' . $messageType);
                exit;
                break;
            case 'move_onu':
                $onuId = (int)$_POST['onu_id'];
                $newSlot = (int)$_POST['new_slot'];
                $newPort = (int)$_POST['new_port'];
                $newOnuId = !empty($_POST['new_onu_id']) ? (int)$_POST['new_onu_id'] : null;
                $result = $huaweiOLT->moveONU($onuId, $newSlot, $newPort, $newOnuId);
                $message = $result['message'] ?? ($result['error'] ?? 'Unknown error');
                $messageType = $result['success'] ? 'success' : 'danger';
                $redirectView = isset($_POST['redirect_view']) ? $_POST['redirect_view'] : 'onu_detail&onu_id=' . $onuId;
                header('Location: ?page=huawei-olt&view=' . $redirectView . '&msg=' . urlencode($message) . '&msg_type=' . $messageType);
                exit;
                break;
            case 'bulk_move_onus':
                $oltId = (int)$_POST['olt_id'];
                $migrations = [];
                if (!empty($_POST['migrations']) && is_array($_POST['migrations'])) {
                    foreach ($_POST['migrations'] as $m) {
                        if (!empty($m['onu_id']) && !empty($m['new_slot']) && isset($m['new_port'])) {
                            $migrations[] = [
                                'onu_id' => (int)$m['onu_id'],
                                'new_slot' => (int)$m['new_slot'],
                                'new_port' => (int)$m['new_port'],
                                'new_onu_id' => !empty($m['new_onu_id']) ? (int)$m['new_onu_id'] : null
                            ];
                        }
                    }
                }
                if (empty($migrations)) {
                    $message = 'No valid migrations specified';
                    $messageType = 'warning';
                } else {
                    $result = $huaweiOLT->bulkMoveONUs($migrations);
                    $message = "Bulk migration complete: {$result['successful']}/{$result['total']} successful";
                    $messageType = $result['success'] ? 'success' : ($result['successful'] > 0 ? 'warning' : 'danger');
                }
                header('Location: ?page=huawei-olt&view=migrations&olt_id=' . $oltId . '&msg=' . urlencode($message) . '&msg_type=' . $messageType);
                exit;
                break;
            case 'bulk_import_smartolt':
                require_once __DIR__ . '/../src/SmartOLT.php';
                $smartolt = new \App\SmartOLT($db);
                if (!$smartolt->isConfigured()) {
                    $message = 'SmartOLT is not configured. Please set API URL and key in OMS Settings.';
                    $messageType = 'danger';
                } else {
                    // Get OLT mappings from POST
                    $oltMappings = [];
                    foreach ($_POST as $key => $value) {
                        if (strpos($key, 'olt_map_') === 0 && !empty($value)) {
                            $smartoltOltId = str_replace('olt_map_', '', $key);
                            $oltMappings[$smartoltOltId] = (int)$value;
                            // Store mapping for future imports
                            $db->prepare("UPDATE huawei_olts SET smartolt_id = ? WHERE id = ?")->execute([$smartoltOltId, (int)$value]);
                        }
                    }
                    
                    $result = $smartolt->getAllONUsDetails();
                    if ($result['status']) {
                        $onus = $result['response'] ?? [];
                        $imported = 0;
                        $updated = 0;
                        $skipped = 0;
                        
                        foreach ($onus as $smartOnu) {
                            $sn = strtoupper($smartOnu['sn'] ?? '');
                            if (empty($sn)) continue;
                            
                            // Get OLT from mapping or existing smartolt_id
                            $oltId = null;
                            $smartOltId = $smartOnu['olt_id'] ?? null;
                            if ($smartOltId && isset($oltMappings[$smartOltId])) {
                                $oltId = $oltMappings[$smartOltId];
                            } else if ($smartOltId) {
                                $stmt = $db->prepare("SELECT id FROM huawei_olts WHERE smartolt_id = ?");
                                $stmt->execute([$smartOltId]);
                                $olt = $stmt->fetch(\PDO::FETCH_ASSOC);
                                $oltId = $olt['id'] ?? null;
                            }
                            
                            if (!$oltId) {
                                $skipped++;
                                continue;
                            }
                            
                            // Check if ONU exists
                            $stmt = $db->prepare("SELECT id FROM huawei_onus WHERE sn = ?");
                            $stmt->execute([$sn]);
                            $existing = $stmt->fetch(\PDO::FETCH_ASSOC);
                            
                            $onuData = [
                                'sn' => $sn,
                                'olt_id' => $oltId,
                                'name' => $smartOnu['name'] ?? $smartOnu['description'] ?? '',
                                'description' => $smartOnu['description'] ?? '',
                                'frame' => (int)($smartOnu['board'] ?? 0),
                                'slot' => (int)($smartOnu['slot'] ?? 0),
                                'port' => (int)($smartOnu['port'] ?? 0),
                                'onu_id' => (int)($smartOnu['onu_number'] ?? $smartOnu['onu_id'] ?? 0),
                                'status' => strtolower($smartOnu['status'] ?? 'offline'),
                                'is_authorized' => true,
                                'rx_power' => isset($smartOnu['rx_power']) ? (float)str_replace(' dBm', '', $smartOnu['rx_power']) : null,
                                'tx_power' => isset($smartOnu['tx_power']) ? (float)str_replace(' dBm', '', $smartOnu['tx_power']) : null,
                                'smartolt_external_id' => $smartOnu['external_id'] ?? $smartOnu['id'] ?? null
                            ];
                            
                            if ($existing) {
                                $huaweiOLT->updateONU($existing['id'], $onuData);
                                $updated++;
                            } else {
                                $huaweiOLT->addONU($onuData);
                                $imported++;
                            }
                        }
                        
                        $message = "Bulk import complete: {$imported} added, {$updated} updated" . ($skipped > 0 ? ", {$skipped} skipped (no OLT mapping)" : '');
                        $messageType = 'success';
                        
                        // Auto-sync optical levels if requested
                        if (!empty($_POST['sync_optical'])) {
                            $syncedCount = 0;
                            $syncedOlts = array_unique(array_values($oltMappings));
                            foreach ($syncedOlts as $syncOltId) {
                                $syncResult = $huaweiOLT->syncOpticalPowerSNMP($syncOltId);
                                if ($syncResult['success']) {
                                    $syncedCount += $syncResult['updated'] ?? 0;
                                }
                            }
                            $message .= ". Optical sync: {$syncedCount} ONUs updated";
                        }
                    } else {
                        $message = 'Failed to fetch ONUs from SmartOLT: ' . ($result['error'] ?? 'Unknown error');
                        $messageType = 'danger';
                    }
                }
                break;
            case 'import_smartolt_csv':
                $oltId = isset($_POST['olt_id']) ? (int)$_POST['olt_id'] : null;
                if (!$oltId) {
                    $message = 'Please select an OLT';
                    $messageType = 'danger';
                } elseif (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
                    $message = 'Please upload a valid CSV file';
                    $messageType = 'danger';
                } else {
                    $csvFile = $_FILES['csv_file']['tmp_name'];
                    $handle = fopen($csvFile, 'r');
                    if (!$handle) {
                        $message = 'Failed to read CSV file';
                        $messageType = 'danger';
                    } else {
                        fgetcsv($handle, 0, ',', '"', '\\');
                        
                        $imported = 0;
                        $updated = 0;
                        $skipped = 0;
                        $zonesCreated = 0;
                        $errors = [];
                        $zoneCache = [];
                        
                        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
                            $sn = strtoupper(trim($row[2] ?? ''));
                            if (empty($sn) || strlen($sn) < 12) {
                                $skipped++;
                                continue;
                            }
                            
                            $onuType = trim($row[3] ?? '');
                            $name = trim($row[4] ?? '');
                            $board = (int)($row[6] ?? 0);
                            $port = (int)($row[7] ?? 0);
                            $allocatedOnu = (int)($row[8] ?? 0);
                            $zone = trim($row[9] ?? '');
                            $address = trim($row[10] ?? '');
                            $status = strtolower(trim($row[27] ?? 'offline'));
                            $rxPower = $row[30] ?? null;
                            $txPower = $row[29] ?? null;
                            $distance = $row[31] ?? null;
                            $serviceVlan = (int)($row[33] ?? 0);
                            $externalId = trim($row[0] ?? '');
                            
                            $rxPowerFloat = is_numeric($rxPower) ? (float)$rxPower : null;
                            $txPowerFloat = is_numeric($txPower) ? (float)$txPower : null;
                            $distanceFloat = is_numeric($distance) ? (float)$distance : null;
                            
                            $onuTypeId = null;
                            if ($onuType) {
                                $stmt = $db->prepare("SELECT id FROM huawei_onu_types WHERE LOWER(name) = LOWER(?) OR LOWER(model) = LOWER(?) LIMIT 1");
                                $stmt->execute([$onuType, $onuType]);
                                $typeRow = $stmt->fetch(\PDO::FETCH_ASSOC);
                                $onuTypeId = $typeRow['id'] ?? null;
                            }
                            
                            // Auto-create zone if it doesn't exist
                            $zoneId = null;
                            if (!empty($zone)) {
                                if (isset($zoneCache[$zone])) {
                                    $zoneId = $zoneCache[$zone];
                                } else {
                                    $stmt = $db->prepare("SELECT id FROM huawei_zones WHERE LOWER(name) = LOWER(?) LIMIT 1");
                                    $stmt->execute([$zone]);
                                    $zoneRow = $stmt->fetch(\PDO::FETCH_ASSOC);
                                    if ($zoneRow) {
                                        $zoneId = $zoneRow['id'];
                                    } else {
                                        // Create the zone
                                        $stmt = $db->prepare("INSERT INTO huawei_zones (name, is_active, created_at) VALUES (?, true, NOW()) RETURNING id");
                                        $stmt->execute([$zone]);
                                        $newZone = $stmt->fetch(\PDO::FETCH_ASSOC);
                                        $zoneId = $newZone['id'] ?? null;
                                        $zonesCreated++;
                                    }
                                    $zoneCache[$zone] = $zoneId;
                                }
                            }
                            
                            // Extended status mapping for SmartOLT
                            $statusMap = [
                                'online' => 'online', 
                                'offline' => 'offline', 
                                'power fail' => 'offline', 
                                'los' => 'los', 
                                'dying-gasp' => 'offline',
                                'working' => 'online',
                                'dyinggasp' => 'offline',
                                'losi' => 'los',
                                'lofi' => 'los',
                                'power-off' => 'offline',
                                'initial' => 'offline'
                            ];
                            $mappedStatus = $statusMap[$status] ?? 'online';
                            
                            $stmt = $db->prepare("SELECT id FROM huawei_onus WHERE sn = ?");
                            $stmt->execute([$sn]);
                            $existing = $stmt->fetch(\PDO::FETCH_ASSOC);
                            
                            try {
                                if ($existing) {
                                    $stmt = $db->prepare("UPDATE huawei_onus SET name = ?, description = ?, slot = ?, port = ?, onu_id = ?, status = ?, zone = ?, zone_id = ?, onu_type_id = ?, vlan_id = ?, smartolt_external_id = ?, rx_power = ?, tx_power = ?, distance = ?, is_authorized = true, updated_at = NOW() WHERE id = ?");
                                    $stmt->execute([$name ?: $sn, $address, $board, $port, $allocatedOnu, $mappedStatus, $zone, $zoneId, $onuTypeId, $serviceVlan > 0 ? $serviceVlan : null, $externalId ?: null, $rxPowerFloat, $txPowerFloat, $distanceFloat, $existing['id']]);
                                    $updated++;
                                } else {
                                    $stmt = $db->prepare("INSERT INTO huawei_onus (sn, olt_id, name, description, frame, slot, port, onu_id, status, is_authorized, zone, zone_id, onu_type_id, vlan_id, smartolt_external_id, rx_power, tx_power, distance, created_at, updated_at) VALUES (?, ?, ?, ?, 0, ?, ?, ?, ?, true, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                                    $stmt->execute([$sn, $oltId, $name ?: $sn, $address, $board, $port, $allocatedOnu, $mappedStatus, $zone, $zoneId, $onuTypeId, $serviceVlan > 0 ? $serviceVlan : null, $externalId ?: null, $rxPowerFloat, $txPowerFloat, $distanceFloat]);
                                    $imported++;
                                }
                            } catch (\Exception $e) {
                                $errors[] = "SN {$sn}: " . $e->getMessage();
                            }
                        }
                        fclose($handle);
                        
                        $message = "CSV Import complete: {$imported} added, {$updated} updated";
                        if ($zonesCreated > 0) $message .= ", {$zonesCreated} zones created";
                        if ($skipped > 0) $message .= ", {$skipped} skipped (continuation rows)";
                        if (!empty($errors)) $message .= ". Errors: " . count($errors);
                        $messageType = empty($errors) ? 'success' : 'warning';
                    }
                }
                break;
            case 'bulk_tr069_config':
                $oltId = isset($_POST['olt_id']) ? (int)$_POST['olt_id'] : null;
                $acsUrl = trim($_POST['acs_url'] ?? '');
                $tr069Vlan = !empty($_POST['tr069_vlan']) ? (int)$_POST['tr069_vlan'] : null;
                $periodicInterval = !empty($_POST['periodic_interval']) ? (int)$_POST['periodic_interval'] : 300;
                
                if (!$oltId) {
                    $message = 'Please select an OLT';
                    $messageType = 'danger';
                } elseif (empty($acsUrl)) {
                    $message = 'Please enter the ACS URL';
                    $messageType = 'danger';
                } else {
                    $result = $huaweiOLT->bulkConfigureTR069($oltId, $acsUrl, [
                        'tr069_vlan' => $tr069Vlan,
                        'periodic_interval' => $periodicInterval
                    ]);
                    
                    if ($result['success'] && $result['configured'] > 0) {
                        $message = "TR-069 configured on {$result['configured']} ONUs" . ($result['failed'] > 0 ? ", {$result['failed']} failed" : '');
                        $messageType = $result['failed'] > 0 ? 'warning' : 'success';
                    } else {
                        $message = 'Failed to configure TR-069: ' . ($result['error'] ?? 'No ONUs found or all commands failed');
                        $messageType = 'danger';
                    }
                }
                break;
            case 'import_smartolt':
            case 'import_from_smartolt':
                require_once __DIR__ . '/../src/SmartOLT.php';
                $smartolt = new \App\SmartOLT($db);
                if (!$smartolt->isConfigured()) {
                    $message = 'SmartOLT is not configured. Please set API URL and key in settings.';
                    $messageType = 'danger';
                } else {
                    $targetOltId = isset($_POST['olt_id']) ? (int)$_POST['olt_id'] : null;
                    $result = $smartolt->getAllONUsDetails();
                    if ($result['status']) {
                        $onus = $result['response'] ?? [];
                        $imported = 0;
                        $updated = 0;
                        foreach ($onus as $smartOnu) {
                            $sn = strtoupper($smartOnu['sn'] ?? '');
                            if (empty($sn)) continue;
                            
                            // Check if ONU exists
                            $stmt = $db->prepare("SELECT id FROM huawei_onus WHERE sn = ?");
                            $stmt->execute([$sn]);
                            $existing = $stmt->fetch(\PDO::FETCH_ASSOC);
                            
                            // Get OLT by SmartOLT olt_id mapping or use target OLT
                            $oltId = $targetOltId;
                            if (!$oltId && !empty($smartOnu['olt_id'])) {
                                $stmt = $db->prepare("SELECT id FROM huawei_olts WHERE smartolt_id = ? OR name LIKE ?");
                                $stmt->execute([$smartOnu['olt_id'], '%' . ($smartOnu['olt_name'] ?? '') . '%']);
                                $olt = $stmt->fetch(\PDO::FETCH_ASSOC);
                                $oltId = $olt['id'] ?? null;
                            }
                            
                            if (!$oltId) continue;
                            
                            $onuData = [
                                'sn' => $sn,
                                'olt_id' => $oltId,
                                'name' => $smartOnu['name'] ?? $smartOnu['description'] ?? '',
                                'description' => $smartOnu['description'] ?? '',
                                'frame' => (int)($smartOnu['board'] ?? 0),
                                'slot' => (int)($smartOnu['slot'] ?? 0),
                                'port' => (int)($smartOnu['port'] ?? 0),
                                'onu_id' => (int)($smartOnu['onu_number'] ?? $smartOnu['onu_id'] ?? 0),
                                'status' => strtolower($smartOnu['status'] ?? 'offline'),
                                'is_authorized' => true,
                                'rx_power' => isset($smartOnu['rx_power']) ? (float)str_replace(' dBm', '', $smartOnu['rx_power']) : null,
                                'tx_power' => isset($smartOnu['tx_power']) ? (float)str_replace(' dBm', '', $smartOnu['tx_power']) : null,
                                'smartolt_external_id' => $smartOnu['external_id'] ?? $smartOnu['id'] ?? null
                            ];
                            
                            if ($existing) {
                                $huaweiOLT->updateONU($existing['id'], $onuData);
                                $updated++;
                            } else {
                                // Insert new
                                $cols = array_keys($onuData);
                                $placeholders = array_fill(0, count($cols), '?');
                                $stmt = $db->prepare("INSERT INTO huawei_onus (" . implode(',', $cols) . ") VALUES (" . implode(',', $placeholders) . ")");
                                $stmt->execute(array_values($onuData));
                                $imported++;
                            }
                        }
                        $message = "SmartOLT sync complete: {$imported} imported, {$updated} updated from " . count($onus) . " total ONUs.";
                        $messageType = 'success';
                    } else {
                        $message = 'SmartOLT API error: ' . ($result['error'] ?? 'Unknown');
                        $messageType = 'danger';
                    }
                }
                break;
            case 'execute_command':
                $result = $huaweiOLT->executeCommand((int)$_POST['olt_id'], $_POST['command']);
                $message = $result['success'] ? 'Command executed' : $result['message'];
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
            case 'mark_alerts_read':
                $huaweiOLT->markAllAlertsRead();
                $message = 'All alerts marked as read';
                $messageType = 'success';
                break;
            case 'sync_onus_snmp':
                $result = $huaweiOLT->syncONUsFromSNMP((int)$_POST['olt_id']);
                if ($result['success']) {
                    $message = "Synced {$result['synced']} ONUs ({$result['added']} new, {$result['updated']} updated)";
                    $messageType = 'success';
                } else {
                    $message = $result['error'] ?? 'Sync failed';
                    $messageType = 'danger';
                }
                break;
            case 'sync_onu_locations':
                $result = $huaweiOLT->syncONULocationsFromSNMP((int)$_POST['olt_id']);
                if ($result['success']) {
                    $oidShort = str_replace('1.3.6.1.4.1.2011.6.128.1.1.2.', '.', $result['used_oid'] ?? '');
                    $message = "Location sync: Updated {$result['updated']}/{$result['db_total']} DB ONUs. SNMP found {$result['snmp_total']} ONUs (OID: {$oidShort}).";
                    if ($result['updated'] == 0 && !empty($result['sample_snmp']) && !empty($result['sample_db'])) {
                        $snmpSamples = implode(', ', $result['sample_snmp']);
                        $dbSamples = implode(', ', $result['sample_db']);
                        $message .= " DEBUG: SNMP=[{$snmpSamples}] vs DB=[{$dbSamples}]";
                    }
                    $messageType = $result['updated'] > 0 ? 'success' : 'warning';
                } else {
                    $message = $result['error'] ?? 'Sync failed';
                    $messageType = 'danger';
                }
                break;
            case 'get_olt_info_snmp':
                $result = $huaweiOLT->getOLTSystemInfoViaSNMP((int)$_POST['olt_id']);
                if ($result['success']) {
                    $info = $result['info'];
                    $message = "OLT Info: {$info['sysName']} - {$info['sysDescr']}";
                    $messageType = 'success';
                } else {
                    $message = $result['error'] ?? 'Failed to get OLT info';
                    $messageType = 'danger';
                }
                break;
            case 'refresh_snmp_info':
                $oltId = (int)$_POST['olt_id'];
                $result = $huaweiOLT->getOLTSystemInfoViaSNMP($oltId);
                if ($result['success'] && !empty($result['info'])) {
                    $info = $result['info'];
                    $db->prepare("UPDATE huawei_olts SET 
                        snmp_last_poll = CURRENT_TIMESTAMP,
                        snmp_sys_name = ?,
                        snmp_sys_descr = ?,
                        snmp_sys_uptime = ?,
                        snmp_sys_location = ?,
                        snmp_status = 'online'
                        WHERE id = ?")->execute([
                        $info['sysName'] ?? null,
                        $info['sysDescr'] ?? null,
                        $info['sysUpTime'] ?? null,
                        $info['sysLocation'] ?? null,
                        $oltId
                    ]);
                    $message = 'SNMP data refreshed from OLT';
                    $messageType = 'success';
                } else {
                    // SNMP failed - keep existing data but update timestamp and mark offline
                    $db->prepare("UPDATE huawei_olts SET 
                        snmp_last_poll = CURRENT_TIMESTAMP,
                        snmp_status = CASE WHEN snmp_status = 'simulated' THEN 'simulated' ELSE 'offline' END
                        WHERE id = ?")->execute([$oltId]);
                    $message = 'SNMP poll failed: ' . ($result['error'] ?? 'OLT unreachable') . '. Using cached data.';
                    $messageType = 'warning';
                }
                header('Location: ?page=huawei-olt&view=olt_detail&olt_id=' . $oltId . '&tab=overview&msg=' . urlencode($message) . '&msg_type=' . $messageType);
                exit;
                break;
            case 'discover_unconfigured':
                $result = $huaweiOLT->discoverUnconfiguredONUs((int)$_POST['olt_id']);
                if ($result['success']) {
                    $message = "Found {$result['count']} unsynced ONUs";
                    $messageType = 'success';
                } else {
                    $message = $result['error'] ?? 'Discovery failed';
                    $messageType = 'danger';
                }
                break;
            case 'discover_all_unconfigured':
                $totalFound = 0;
                $successOlts = [];
                $failedOlts = [];
                $allOlts = $huaweiOLT->getOLTs(false);
                foreach ($allOlts as $olt) {
                    if ($olt['is_active']) {
                        try {
                            $result = $huaweiOLT->discoverUnconfiguredONUs($olt['id']);
                            if ($result['success']) {
                                $totalFound += $result['count'];
                                $successOlts[] = "{$olt['name']}: {$result['count']}";
                            } else {
                                $failedOlts[] = "{$olt['name']}: " . ($result['error'] ?? 'failed');
                            }
                        } catch (Exception $e) {
                            $failedOlts[] = "{$olt['name']}: " . $e->getMessage();
                        }
                    }
                }
                
                if (empty($failedOlts) && !empty($successOlts)) {
                    $message = "Discovery complete. Found {$totalFound} unsynced ONUs (" . implode(', ', $successOlts) . ")";
                    $messageType = 'success';
                } elseif (!empty($failedOlts) && !empty($successOlts)) {
                    $message = "Partial discovery. Found {$totalFound} ONUs from: " . implode(', ', $successOlts) . ". Failed: " . implode(', ', $failedOlts);
                    $messageType = 'warning';
                } elseif (!empty($failedOlts)) {
                    $message = "Discovery failed for all OLTs: " . implode(', ', $failedOlts);
                    $messageType = 'danger';
                } else {
                    $message = "No active OLTs found to discover from.";
                    $messageType = 'warning';
                }
                break;
            case 'import_smartolt':
                $result = $huaweiOLT->importFromSmartOLT((int)$_POST['olt_id']);
                if ($result['success']) {
                    $message = "Imported from SmartOLT: {$result['added']} added, {$result['updated']} updated (total: {$result['total']})";
                    $messageType = 'success';
                } else {
                    $message = $result['error'] ?? 'Import failed';
                    $messageType = 'danger';
                }
                break;
            case 'mark_all_authorized':
                $result = $huaweiOLT->markAllONUsAuthorized((int)$_POST['olt_id']);
                if ($result['success']) {
                    $message = "Marked {$result['count']} ONUs as authorized";
                    $messageType = 'success';
                } else {
                    $message = $result['error'] ?? 'Failed';
                    $messageType = 'danger';
                }
                break;
            case 'delete_all_onus':
                $deleteOltId = !empty($_POST['olt_id']) ? (int)$_POST['olt_id'] : null;
                $result = $huaweiOLT->deleteAllONUs($deleteOltId);
                if ($result['success']) {
                    $message = "Deleted all ONUs" . ($deleteOltId ? " for this OLT" : "") . ". You can now start fresh.";
                    $messageType = 'success';
                } else {
                    $message = $result['message'] ?? 'Failed to delete ONUs';
                    $messageType = 'danger';
                }
                break;
            case 'sync_cli':
                $result = $huaweiOLT->syncONUsFromCLI((int)$_POST['olt_id']);
                if ($result['success']) {
                    $opticalInfo = isset($result['optical_sync']) ? ", optical updated: {$result['optical_sync']['updated']}" : '';
                    $message = "CLI Sync: {$result['added']} added, {$result['updated']} updated (total: {$result['total']}){$opticalInfo}";
                    if (!empty($result['errors'])) {
                        $errorCount = count($result['errors']);
                        $sampleErrors = array_slice($result['errors'], 0, 3);
                        $message .= ". ERRORS ({$errorCount}): " . implode(' | ', $sampleErrors);
                        $messageType = ($result['added'] + $result['updated']) > 0 ? 'warning' : 'danger';
                    } else {
                        $messageType = 'success';
                    }
                } else {
                    $message = $result['error'] ?? 'CLI sync failed';
                    if (!empty($result['errors'])) {
                        $message .= ': ' . implode(' | ', array_slice($result['errors'], 0, 3));
                    }
                    $messageType = 'danger';
                }
                break;
            case 'save_tr069_omci_settings':
                $tr069Settings = [
                    'tr069_acs_url' => $_POST['tr069_acs_url'] ?? '',
                    'tr069_periodic_interval' => $_POST['tr069_periodic_interval'] ?? '300',
                    'tr069_default_gem_port' => $_POST['tr069_default_gem_port'] ?? '2',
                    'tr069_acs_username' => $_POST['tr069_acs_username'] ?? '',
                    'tr069_cpe_username' => $_POST['tr069_cpe_username'] ?? ''
                ];
                if (!empty($_POST['tr069_acs_password'])) {
                    $tr069Settings['tr069_acs_password'] = $_POST['tr069_acs_password'];
                }
                if (!empty($_POST['tr069_cpe_password'])) {
                    $tr069Settings['tr069_cpe_password'] = $_POST['tr069_cpe_password'];
                }
                foreach ($tr069Settings as $key => $value) {
                    $stmt = $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
                    $stmt->execute([$value, $key]);
                    if ($stmt->rowCount() === 0) {
                        $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value, setting_group) VALUES (?, ?, 'TR-069')");
                        $stmt->execute([$key, $value]);
                    }
                }
                $message = 'TR-069 OMCI settings saved successfully';
                $messageType = 'success';
                break;
                
            case 'save_genieacs_settings':
                $settings = [
                    'genieacs_url' => $_POST['genieacs_url'] ?? '',
                    'genieacs_username' => $_POST['genieacs_username'] ?? '',
                    'genieacs_timeout' => $_POST['genieacs_timeout'] ?? '30',
                    'genieacs_enabled' => isset($_POST['genieacs_enabled']) ? '1' : '0'
                ];
                if (!empty($_POST['genieacs_password'])) {
                    $settings['genieacs_password'] = $_POST['genieacs_password'];
                }
                foreach ($settings as $key => $value) {
                    $stmt = $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
                    $stmt->execute([$value, $key]);
                    if ($stmt->rowCount() === 0) {
                        $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value, setting_group) VALUES (?, ?, 'TR-069')");
                        $stmt->execute([$key, $value]);
                    }
                }
                $message = 'GenieACS settings saved successfully';
                $messageType = 'success';
                break;
            case 'save_oms_notifications':
                $notifSettings = [
                    'wa_provisioning_group' => trim($_POST['wa_provisioning_group'] ?? ''),
                    'onu_discovery_notify' => isset($_POST['onu_discovery_notify']) ? '1' : '0',
                    'onu_authorized_notify' => isset($_POST['onu_authorized_notify']) ? '1' : '0'
                ];
                foreach ($notifSettings as $key => $value) {
                    $stmt = $db->prepare("UPDATE company_settings SET setting_value = ? WHERE setting_key = ?");
                    $stmt->execute([$value, $key]);
                    if ($stmt->rowCount() === 0) {
                        $stmt = $db->prepare("INSERT INTO company_settings (setting_key, setting_value) VALUES (?, ?)");
                        $stmt->execute([$key, $value]);
                    }
                }
                $message = 'Notification settings saved successfully';
                $messageType = 'success';
                header('Location: ?page=huawei-olt&view=settings&tab=notifications&msg=' . urlencode($message) . '&msg_type=' . $messageType);
                exit;
            case 'save_oms_templates':
                $templates = [
                    'wa_template_oms_new_onu' => trim($_POST['wa_template_oms_new_onu'] ?? ''),
                    'wa_template_oms_fault' => trim($_POST['wa_template_oms_fault'] ?? '')
                ];
                foreach ($templates as $key => $value) {
                    if (empty($value)) continue;
                    $stmt = $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
                    $stmt->execute([$value, $key]);
                    if ($stmt->rowCount() === 0) {
                        $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value, setting_group) VALUES (?, ?, 'WhatsApp')");
                        $stmt->execute([$key, $value]);
                    }
                }
                $message = 'Message templates saved successfully';
                $messageType = 'success';
                header('Location: ?page=huawei-olt&view=settings&tab=notifications&msg=' . urlencode($message) . '&msg_type=' . $messageType);
                exit;
            case 'test_oms_notification':
                $provGroup = null;
                $stmt = $db->prepare("SELECT setting_value FROM company_settings WHERE setting_key = 'wa_provisioning_group'");
                $stmt->execute();
                $provGroup = $stmt->fetchColumn();
                if (!$provGroup) {
                    $message = 'No provisioning group configured';
                    $messageType = 'warning';
                } else {
                    try {
                        $waMessage = "OMS Test Notification\n\nThis is a test message from your OLT Management System.\n\nTime: " . date('Y-m-d H:i:s');
                        $waUrl = 'http://127.0.0.1:3001/send-group';
                        $ch = curl_init($waUrl);
                        curl_setopt($ch, CURLOPT_POST, true);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                            'groupId' => $provGroup,
                            'message' => $waMessage
                        ]));
                        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                        $response = curl_exec($ch);
                        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);
                        if ($httpCode === 200) {
                            $message = 'Test notification sent successfully';
                            $messageType = 'success';
                        } else {
                            $message = 'Failed to send test notification: ' . $response;
                            $messageType = 'danger';
                        }
                    } catch (Exception $e) {
                        $message = 'Error: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                header('Location: ?page=huawei-olt&view=settings&tab=notifications&msg=' . urlencode($message) . '&msg_type=' . $messageType);
                exit;
            case 'save_smartolt_settings':
                require_once __DIR__ . '/../src/SmartOLT.php';
                \App\SmartOLT::saveSettings($db, [
                    'api_url' => $_POST['api_url'] ?? '',
                    'api_key' => $_POST['api_key'] ?? ''
                ]);
                $message = 'SmartOLT settings saved successfully';
                $messageType = 'success';
                header('Location: ?page=huawei-olt&view=settings&tab=smartolt&msg=' . urlencode($message) . '&msg_type=' . $messageType);
                exit;
                break;
            case 'test_smartolt':
                require_once __DIR__ . '/../src/SmartOLT.php';
                $smartolt = new \App\SmartOLT($db);
                $result = $smartolt->testConnection();
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'danger';
                header('Location: ?page=huawei-olt&view=settings&tab=smartolt&msg=' . urlencode($message) . '&msg_type=' . $messageType);
                exit;
                break;
            case 'save_onu_type':
                $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
                $data = [
                    'name' => $_POST['name'] ?? '',
                    'model' => $_POST['model'] ?? '',
                    'eth_ports' => (int)($_POST['eth_ports'] ?? 1),
                    'pots_ports' => (int)($_POST['pots_ports'] ?? 0),
                    'default_mode' => $_POST['default_mode'] ?? 'bridge',
                    'tcont_count' => (int)($_POST['tcont_count'] ?? 1),
                    'gemport_count' => (int)($_POST['gemport_count'] ?? 1),
                    'wifi_capable' => isset($_POST['wifi_capable']),
                    'omci_capable' => isset($_POST['omci_capable']),
                    'tr069_capable' => isset($_POST['tr069_capable']),
                    'description' => $_POST['description'] ?? ''
                ];
                if ($id) {
                    $stmt = $db->prepare("UPDATE huawei_onu_types SET name=?, model=?, eth_ports=?, pots_ports=?, default_mode=?, tcont_count=?, gemport_count=?, wifi_capable=?, omci_capable=?, tr069_capable=?, description=?, updated_at=CURRENT_TIMESTAMP WHERE id=?");
                    $stmt->execute([$data['name'], $data['model'], $data['eth_ports'], $data['pots_ports'], $data['default_mode'], $data['tcont_count'], $data['gemport_count'], $data['wifi_capable'], $data['omci_capable'], $data['tr069_capable'], $data['description'], $id]);
                    $message = 'ONU type updated successfully';
                } else {
                    $stmt = $db->prepare("INSERT INTO huawei_onu_types (name, model, eth_ports, pots_ports, default_mode, tcont_count, gemport_count, wifi_capable, omci_capable, tr069_capable, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$data['name'], $data['model'], $data['eth_ports'], $data['pots_ports'], $data['default_mode'], $data['tcont_count'], $data['gemport_count'], $data['wifi_capable'], $data['omci_capable'], $data['tr069_capable'], $data['description']]);
                    $message = 'ONU type added successfully';
                }
                $messageType = 'success';
                break;
            case 'delete_onu_type':
                $stmt = $db->prepare("UPDATE huawei_onu_types SET is_active = FALSE WHERE id = ?");
                $stmt->execute([(int)$_POST['id']]);
                $message = 'ONU type deleted successfully';
                $messageType = 'success';
                break;
            case 'save_vpn_settings':
                require_once __DIR__ . '/../src/WireGuardService.php';
                $wgService = new \App\WireGuardService($db);
                $vpnSettings = [
                    'vpn_enabled' => isset($_POST['vpn_enabled']) ? 'true' : 'false',
                    'vpn_gateway_ip' => $_POST['vpn_gateway_ip'] ?? '10.200.0.1',
                    'vpn_network' => $_POST['vpn_network'] ?? '10.200.0.0/24'
                ];
                $wgService->updateSettings($vpnSettings);
                $message = 'VPN settings saved successfully';
                $messageType = 'success';
                break;
            case 'add_vpn_server':
                require_once __DIR__ . '/../src/WireGuardService.php';
                $wgService = new \App\WireGuardService($db);
                $serverData = [
                    'name' => $_POST['name'] ?? '',
                    'interface_name' => $_POST['interface_name'] ?? 'wg0',
                    'interface_addr' => $_POST['address'] ?? '10.200.0.1/24',
                    'listen_port' => (int)($_POST['listen_port'] ?? 51820),
                    'mtu' => (int)($_POST['mtu'] ?? 1420),
                    'dns_servers' => $_POST['dns_servers'] ?? null,
                    'enabled' => true
                ];
                $serverId = $wgService->createServer($serverData);
                if ($serverId) {
                    $message = 'VPN server added successfully';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to add VPN server';
                    $messageType = 'danger';
                }
                break;
            case 'delete_vpn_server':
                require_once __DIR__ . '/../src/WireGuardService.php';
                $wgService = new \App\WireGuardService($db);
                $wgService->deleteServer((int)$_POST['server_id']);
                $message = 'VPN server deleted successfully';
                $messageType = 'success';
                break;
            case 'add_vpn_peer':
                require_once __DIR__ . '/../src/WireGuardService.php';
                $wgService = new \App\WireGuardService($db);
                
                // Auto-create default server if none exists and no server selected
                $serverId = (int)($_POST['server_id'] ?? 0);
                if ($serverId === 0) {
                    $existingServers = $wgService->getServers();
                    if (empty($existingServers)) {
                        // Create default server using VPN settings
                        $vpnSettings = $wgService->getSettings();
                        $gatewayIp = $vpnSettings['vpn_gateway_ip'] ?? '10.200.0.1';
                        $defaultServer = [
                            'name' => 'Main VPN Server',
                            'interface_name' => 'wg0',
                            'interface_addr' => $gatewayIp . '/24',
                            'listen_port' => 51820,
                            'mtu' => 1420,
                            'dns_servers' => '1.1.1.1',
                            'enabled' => true
                        ];
                        $serverId = $wgService->createServer($defaultServer);
                        if (!$serverId) {
                            $message = 'Failed to create default VPN server';
                            $messageType = 'danger';
                            break;
                        }
                    } else {
                        $serverId = $existingServers[0]['id'];
                    }
                }
                
                $peerData = [
                    'server_id' => $serverId,
                    'name' => $_POST['name'] ?? '',
                    'description' => $_POST['description'] ?? null,
                    'allowed_ips' => $_POST['allowed_ips'] ?? '',
                    'endpoint' => $_POST['endpoint'] ?? null,
                    'persistent_keepalive' => (int)($_POST['persistent_keepalive'] ?? 25),
                    'is_active' => true,
                    'is_olt_site' => isset($_POST['is_olt_site']),
                    'olt_id' => !empty($_POST['olt_id']) ? (int)$_POST['olt_id'] : null
                ];
                $peerId = $wgService->createPeer($peerData);
                if ($peerId) {
                    // Process routed networks if provided
                    $routedNetworks = trim($_POST['routed_networks'] ?? '');
                    if (!empty($routedNetworks)) {
                        $networks = array_filter(array_map('trim', explode("\n", $routedNetworks)));
                        $subnetStmt = $db->prepare("INSERT INTO wireguard_subnets (vpn_peer_id, network_cidr, subnet_type, is_olt_management) VALUES (?, ?, 'management', true)");
                        foreach ($networks as $network) {
                            if (preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\/\d{1,2}$/', $network)) {
                                $subnetStmt->execute([$peerId, $network]);
                            }
                        }
                    }
                    // Auto-sync WireGuard config
                    $syncResult = $wgService->syncConfig();
                    $message = 'VPN peer added successfully' . ($syncResult['success'] ? ' - Config synced' : '');
                    $messageType = 'success';
                } else {
                    $message = 'Failed to add VPN peer';
                    $messageType = 'danger';
                }
                break;
            case 'delete_vpn_peer':
                require_once __DIR__ . '/../src/WireGuardService.php';
                $wgService = new \App\WireGuardService($db);
                $wgService->deletePeer((int)$_POST['peer_id']);
                // Auto-sync WireGuard config
                $syncResult = $wgService->syncConfig();
                $message = 'VPN peer deleted' . ($syncResult['success'] ? ' - Config synced' : '');
                $messageType = 'success';
                break;
            case 'edit_vpn_peer':
                require_once __DIR__ . '/../src/WireGuardService.php';
                $wgService = new \App\WireGuardService($db);
                $peerId = (int)$_POST['peer_id'];
                
                // Update peer data
                $peerData = [
                    'name' => $_POST['name'] ?? '',
                    'description' => $_POST['description'] ?? null,
                    'allowed_ips' => $_POST['allowed_ips'] ?? '',
                    'endpoint' => $_POST['endpoint'] ?? null,
                    'persistent_keepalive' => (int)($_POST['persistent_keepalive'] ?? 25),
                    'is_olt_site' => isset($_POST['is_olt_site']),
                    'olt_id' => !empty($_POST['olt_id']) ? (int)$_POST['olt_id'] : null
                ];
                $wgService->updatePeer($peerId, $peerData);
                
                // Update routed networks - delete existing and re-add
                try {
                    $db->prepare("DELETE FROM wireguard_subnets WHERE vpn_peer_id = ?")->execute([$peerId]);
                    
                    $routedNetworks = trim($_POST['routed_networks'] ?? '');
                    if (!empty($routedNetworks)) {
                        $networks = array_filter(array_map('trim', explode("\n", $routedNetworks)));
                        $subnetStmt = $db->prepare("INSERT INTO wireguard_subnets (vpn_peer_id, network_cidr, subnet_type, is_olt_management) VALUES (?, ?, 'management', true)");
                        foreach ($networks as $network) {
                            if (preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\/\d{1,2}$/', $network)) {
                                $subnetStmt->execute([$peerId, $network]);
                            }
                        }
                    }
                    
                    // Auto-sync WireGuard config (replaces restartContainer)
                    $syncResult = $wgService->syncConfig();
                    $message = 'VPN peer updated' . ($syncResult['success'] ? ' - Config synced and applied' : ' - Please sync manually');
                } catch (Exception $e) {
                    $message = 'Peer updated but subnet changes may have failed: ' . $e->getMessage();
                }
                $messageType = 'success';
                break;
            case 'add_vpn_subnet':
                require_once __DIR__ . '/../src/WireGuardService.php';
                $wgService = new \App\WireGuardService($db);
                $peerId = !empty($_POST['vpn_peer_id']) ? (int)$_POST['vpn_peer_id'] : null;
                $stmt = $db->prepare("INSERT INTO wireguard_subnets (vpn_peer_id, network_cidr, description, subnet_type, is_olt_management, is_tr069_range) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $peerId,
                    $_POST['network_cidr'] ?? '',
                    $_POST['description'] ?? '',
                    $_POST['subnet_type'] ?? 'management',
                    isset($_POST['is_olt_management']) && $_POST['is_olt_management'] === '1',
                    isset($_POST['is_tr069_range']) && $_POST['is_tr069_range'] === '1'
                ]);
                // Auto-sync WireGuard config
                $syncResult = $wgService->syncConfig();
                $message = 'Network subnet added' . ($syncResult['success'] ? ' - Config synced' : '');
                $messageType = 'success';
                break;
            case 'delete_vpn_subnet':
                require_once __DIR__ . '/../src/WireGuardService.php';
                $wgService = new \App\WireGuardService($db);
                $stmt = $db->prepare("UPDATE wireguard_subnets SET is_active = FALSE WHERE id = ?");
                $stmt->execute([(int)$_POST['id']]);
                // Auto-sync WireGuard config
                $syncResult = $wgService->syncConfig();
                $message = 'Network subnet deleted' . ($syncResult['success'] ? ' - Config synced' : '');
                $messageType = 'success';
                break;
            case 'sync_wireguard':
                require_once __DIR__ . '/../src/WireGuardService.php';
                $wgService = new \App\WireGuardService($db);
                $syncResult = $wgService->syncConfig();
                $message = $syncResult['success'] 
                    ? 'WireGuard config synced and applied successfully' 
                    : 'Sync failed: ' . ($syncResult['error'] ?? 'Unknown error');
                $messageType = $syncResult['success'] ? 'success' : 'danger';
                break;
            case 'test_genieacs':
                require_once __DIR__ . '/../src/GenieACS.php';
                $genieacs = new \App\GenieACS($db);
                $result = $genieacs->testConnection();
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
            case 'sync_tr069_devices':
                require_once __DIR__ . '/../src/GenieACS.php';
                $genieacs = new \App\GenieACS($db);
                $result = $genieacs->syncDevicesToDB();
                if ($result['success']) {
                    $unlinkedCount = count($result['unlinked'] ?? []);
                    $message = "Synced {$result['synced']} devices from GenieACS (total: {$result['total']}, unlinked: {$unlinkedCount})";
                    if ($unlinkedCount > 0 && $unlinkedCount <= 5) {
                        $unlinkedInfo = array_map(fn($u) => $u['serial'], $result['unlinked']);
                        $message .= " - Unlinked serials: " . implode(', ', $unlinkedInfo);
                    }
                    $messageType = $result['synced'] > 0 ? 'success' : 'warning';
                } else {
                    $message = $result['error'] ?? 'Sync failed';
                    $messageType = 'danger';
                }
                break;
            case 'tr069_reboot':
                require_once __DIR__ . '/../src/GenieACS.php';
                $genieacs = new \App\GenieACS($db);
                $deviceId = $_POST['device_id'] ?? '';
                $onuId = $_POST['onu_id'] ?? '';
                
                // Look up ONU if onu_id provided
                if (empty($deviceId) && !empty($onuId)) {
                    $onuStmt = $db->prepare("SELECT sn, tr069_device_id, tr069_serial, genieacs_id FROM huawei_onus WHERE id = ?");
                    $onuStmt->execute([$onuId]);
                    $onuData = $onuStmt->fetch(PDO::FETCH_ASSOC);
                    if ($onuData) {
                        // Try genieacs_id first, then tr069_device_id
                        $deviceId = !empty($onuData['genieacs_id']) ? $onuData['genieacs_id'] : '';
                        if (empty($deviceId)) {
                            $deviceId = !empty($onuData['tr069_device_id']) ? $onuData['tr069_device_id'] : '';
                        }
                        
                        // If still empty, look up by serial
                        if (empty($deviceId)) {
                            $serial = !empty($onuData['tr069_serial']) ? $onuData['tr069_serial'] : 
                                     (!empty($onuData['sn']) ? $onuData['sn'] : '');
                            if (!empty($serial)) {
                                $deviceResult = $genieacs->getDeviceBySerial($serial);
                                if ($deviceResult['success']) {
                                    $deviceId = $deviceResult['device']['_id'] ?? '';
                                    if (!empty($deviceId)) {
                                        $updateStmt = $db->prepare("UPDATE huawei_onus SET genieacs_id = ? WHERE id = ?");
                                        $updateStmt->execute([$deviceId, $onuId]);
                                    }
                                }
                            }
                        }
                    }
                }
                
                if (empty($deviceId)) {
                    $message = 'Device not connected to TR-069/GenieACS';
                    $messageType = 'warning';
                    break;
                }
                
                error_log("[TR069] Reboot: deviceId={$deviceId}");
                $result = $genieacs->rebootDevice($deviceId);
                error_log("[TR069] Reboot result: " . json_encode($result));
                
                if ($result['success']) {
                    $message = 'Reboot command sent to device';
                    $messageType = 'success';
                } else {
                    $errorDetail = $result['error'] ?? 'Unknown error';
                    $httpCode = $result['http_code'] ?? '';
                    $message = "Reboot failed: {$errorDetail}" . ($httpCode ? " (HTTP {$httpCode})" : '');
                    $messageType = 'danger';
                }
                break;
            case 'get_device_status':
                // Get all device parameters organized by category (like SmartOLT)
                require_once __DIR__ . '/../src/GenieACS.php';
                header('Content-Type: application/json');
                
                $serial = $_POST['serial'] ?? '';
                if (empty($serial)) {
                    echo json_encode(['success' => false, 'error' => 'Serial number required']);
                    exit;
                }
                
                $genieacs = new \App\GenieACS($db);
                
                // Find device by serial
                $deviceResult = $genieacs->getDeviceBySerial($serial);
                if (!($deviceResult['success'] ?? false) || empty($deviceResult['device'])) {
                    echo json_encode(['success' => false, 'error' => 'Device not found in TR-069']);
                    exit;
                }
                
                $deviceId = $deviceResult['device']['_id'] ?? '';
                $refresh = filter_var($_POST["refresh"] ?? false, FILTER_VALIDATE_BOOLEAN);
                $result = $genieacs->getDeviceStatus($deviceId, $refresh);
                echo json_encode($result);
                exit;
                
            case 'save_device_params':
                // Save device parameters (batch update)
                require_once __DIR__ . '/../src/GenieACS.php';
                header('Content-Type: application/json');
                
                $serial = $_POST['serial'] ?? '';
                $params = json_decode($_POST['params'] ?? '{}', true);
                
                error_log("[save_device_params] Serial received: " . $serial);
                
                if (empty($serial)) {
                    echo json_encode(['success' => false, 'error' => 'Serial number required']);
                    exit;
                }
                
                if (empty($params)) {
                    echo json_encode(['success' => false, 'error' => 'No parameters to save']);
                    exit;
                }
                
                $genieacs = new \App\GenieACS($db);
                
                // Normalize parameter paths - fix common issues
                $normalizedParams = [];
                foreach ($params as $path => $value) {
                    // Fix KeyPassphrase path for Huawei devices
                    if (strpos($path, '.KeyPassphrase') !== false && strpos($path, 'PreSharedKey') === false) {
                        // Convert WLANConfiguration.X.KeyPassphrase to WLANConfiguration.X.PreSharedKey.1.KeyPassphrase
                        $path = preg_replace('/\.KeyPassphrase$/', '.PreSharedKey.1.KeyPassphrase', $path);
                    }
                    
                    // Skip problematic parameters that cause cwmp.9007 errors
                    $skipParams = ['WPAEncryptionModes', 'X_HW_WlanAccessType', 'X_HW_VlanMappingEnable', 'BeaconType', 'WEPEncryptionLevel'];
                    $skip = false;
                    foreach ($skipParams as $skipParam) {
                        if (strpos($path, $skipParam) !== false) {
                            $skip = true;
                            break;
                        }
                    }
                    if (!$skip) {
                        $normalizedParams[$path] = $value;
                    }
                }
                $params = $normalizedParams;
                
                if (empty($params)) {
                    echo json_encode(['success' => true, 'message' => 'No valid parameters to save (some may have been filtered)']);
                    exit;
                }
                
                // Find device by serial
                $deviceResult = $genieacs->getDeviceBySerial($serial);
                error_log("[save_device_params] Device lookup result: " . json_encode($deviceResult));
                if (!($deviceResult['success'] ?? false) || empty($deviceResult['device'])) {
                    echo json_encode(['success' => false, 'error' => 'Device not found in TR-069', 'serial_searched' => $serial]);
                    exit;
                }
                
                $deviceId = $deviceResult['device']['_id'] ?? '';
                $result = $genieacs->saveDeviceParams($deviceId, $params);
                
                // Check if task was queued (202) vs completed (200)
                if ($result['success'] && isset($result['http_code'])) {
                    if ($result['http_code'] == 202) {
                        // Task queued but device didn't respond in time - send explicit connection request
                        $genieacs->sendConnectionRequest($serial);
                        $result['warning'] = 'Changes queued. Device may apply them on next connection.';
                        $result['queued'] = true;
                    } elseif ($result['http_code'] == 200) {
                        $result['applied'] = true;
                    }
                }
                
                echo json_encode($result);
                exit;
                
            case 'configure_pppoe':
                // Internet WAN configuration - supports both TR-069 and OMCI methods
                require_once __DIR__ . '/../src/GenieACS.php';
                require_once __DIR__ . '/../src/HuaweiOLT.php';
                
                $serial = $_POST['serial'] ?? '';
                $connType = $_POST['connection_type'] ?? 'pppoe';
                $provisionMethod = $_POST['provision_method'] ?? 'tr069';
                $vlanId = (int)($_POST['vlan_id'] ?? 900);
                
                if ($provisionMethod === 'omci') {
                    // OMCI method - configure via OLT CLI
                    $huaweiOLT = new \ISP\HuaweiOLT($pdo);
                    
                    // Find ONU by serial
                    $stmt = $pdo->prepare("SELECT id, olt_id, slot, port, onu_id FROM huawei_onus WHERE sn = ?");
                    $stmt->execute([$serial]);
                    $onu = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$onu) {
                        $message = 'ONU not found in database: ' . $serial;
                        $messageType = 'danger';
                        break;
                    }
                    
                    if ($connType === 'pppoe') {
                        $config = [
                            'pppoe_vlan' => $vlanId ?: 900,
                            'pppoe_username' => $_POST['pppoe_username'] ?? '',
                            'pppoe_password' => $_POST['pppoe_password'] ?? '',
                            'ip_index' => 1,
                            'wan_profile_id' => 1
                        ];
                        $result = $huaweiOLT->configureWANPPPoE($onu['id'], $config);
                    } else {
                        $config = [
                            'dhcp_vlan' => $vlanId ?: 900,
                            'ip_index' => 1
                        ];
                        $result = $huaweiOLT->configureWANDHCP($onu['id'], $config);
                    }
                    
                    if ($result['success']) {
                        $message = 'WAN configured via OMCI (OLT CLI). VLAN: ' . ($result['pppoe_vlan'] ?? $result['dhcp_vlan'] ?? $vlanId);
                        $messageType = 'success';
                    } else {
                        $message = 'Failed to configure WAN via OMCI: ' . ($result['message'] ?? 'Unknown error');
                        $messageType = 'danger';
                    }
                } else {
                    // TR-069 method - configure via GenieACS using 4-step approach
                    // Step 1: Summon (getParameterNames) - Force discovery
                    // Step 2: AddObject - Create WANPPPConnection
                    // Step 3: Refresh (getParameterValues) - Read parameters
                    // Step 4: SetParameterValues - Set PPPoE credentials
                    $genieacs = new \App\GenieACS($pdo);
                    
                    $deviceResult = $genieacs->getDeviceBySerial($serial);
                    if (!$deviceResult['success']) {
                        $message = 'Device not found in GenieACS: ' . $serial;
                        $messageType = 'danger';
                        break;
                    }
                    
                    $deviceId = $deviceResult['device']['_id'];
                    
                    if ($connType === 'pppoe') {
                        $pppoeUsername = $_POST['pppoe_username'] ?? '';
                        $pppoePassword = $_POST['pppoe_password'] ?? '';
                        
                        if (empty($pppoeUsername) || empty($pppoePassword)) {
                            $message = 'PPPoE username and password are required';
                            $messageType = 'danger';
                            break;
                        }
                        
                        // Use direct setParameterValues (instant execution like WiFi config)
                        $result = $genieacs->configurePPPoEDirect(
                            $deviceId,
                            $pppoeUsername,
                            $pppoePassword,
                            $vlanId,    // Service VLAN
                            true        // Enable connection
                        );
                        
                        if ($result['success']) {
                            $message = 'PPPoE configured instantly via TR-069. ' . ($result['message'] ?? '');
                            $messageType = 'success';
                        } else {
                            $message = 'TR-069 PPPoE failed: ' . ($result['error'] ?? 'Unknown error');
                            $messageType = 'danger';
                        }
                    } else {
                        // DHCP/Static - use simpler approach
                        $config = [
                            'connection_type' => $connType,
                            'service_vlan' => $vlanId,
                            'skip_safety_checks' => true
                        ];
                        if ($connType === 'static') {
                            $config['static_ip'] = $_POST['static_ip'] ?? '';
                            $config['static_mask'] = $_POST['subnet_mask'] ?? '255.255.255.0';
                            $config['static_gateway'] = $_POST['gateway'] ?? '';
                        }
                        $result = $genieacs->configureInternetWAN($deviceId, $config);
                        
                        if ($result['success']) {
                            $message = strtoupper($connType) . ' configured via TR-069';
                            $messageType = 'success';
                        } else {
                            $message = 'TR-069 ' . strtoupper($connType) . ' failed: ' . implode(', ', $result['errors'] ?? ['Unknown error']);
                            $messageType = 'danger';
                        }
                    }
                }
                break;
            case 'configure_pppoe_wan':
                // SmartOLT-style: Configure Internet WAN via TR-069, not OMCI
                require_once __DIR__ . '/../src/GenieACS.php';
                require_once __DIR__ . '/../src/HuaweiOLT.php';
                $genieacs = new \App\GenieACS($db);
                $huaweiOLT = new \App\HuaweiOLT($db);
                $onuDbId = (int)($_POST['onu_db_id'] ?? 0);
                
                if ($onuDbId <= 0) {
                    $message = 'Invalid ONU ID';
                    $messageType = 'danger';
                    break;
                }
                
                // Get ONU info to find its serial number
                $onu = $huaweiOLT->getONU($onuDbId);
                if (!$onu) {
                    $message = 'ONU not found';
                    $messageType = 'danger';
                    break;
                }
                
                // Find device in GenieACS by serial
                $deviceResult = $genieacs->getDeviceBySerial($onu['sn']);
                if (!$deviceResult['success']) {
                    $message = 'ONU not found in GenieACS. Please ensure TR-069 is configured on OLT first.';
                    $messageType = 'warning';
                    break;
                }
                
                $deviceId = $deviceResult['device']['_id'];
                
                // Use SmartOLT-style configureInternetWAN function
                // NOTE: Huawei ONUs require WANPPPConnection.1 to already exist from OMCI
                $config = [
                    'connection_type' => 'pppoe',
                    'service_vlan' => (int)($_POST['pppoe_vlan'] ?? 902),
                    'wan_index' => 1, // WANConnectionDevice.1 (created by OMCI)
                    'connection_name' => 'Internet_PPPoE',
                    'pppoe_username' => $_POST['pppoe_username'] ?? '',
                    'pppoe_password' => $_POST['pppoe_password'] ?? '',
                ];
                
                error_log("[TR069-PPPoE] Config: " . json_encode($config));
                $result = $genieacs->configureInternetWAN($deviceId, $config);
                error_log("[TR069-PPPoE] Result: " . json_encode($result));
                
                if ($result['success']) {
                    // Save PPPoE credentials to database
                    $updateStmt = $db->prepare("UPDATE huawei_onus SET pppoe_username = ?, wan_mode = 'pppoe' WHERE id = ?");
                    $updateStmt->execute([$config['pppoe_username'], $onuDbId]);
                    
                    $message = 'Internet PPPoE configured via TR-069. WAN: ' . ($result['wan_name'] ?? 'wan2.1.ppp1');
                    $messageType = 'success';
                } else {
                    $detailedErrors = [];
                    if (!empty($result['errors'])) {
                        $detailedErrors = $result['errors'];
                    }
                    if (!empty($result['results'])) {
                        foreach ($result['results'] as $step => $stepResult) {
                            if (!($stepResult['success'] ?? true)) {
                                $detailedErrors[] = "{$step}: " . ($stepResult['error'] ?? 'failed');
                            }
                        }
                    }
                    $message = 'Failed to configure PPPoE: ' . (implode(', ', $detailedErrors) ?: 'Unknown error');
                    $messageType = 'danger';
                }
                break;
            case 'tr069_refresh':
                require_once __DIR__ . '/../src/GenieACS.php';
                $genieacs = new \App\GenieACS($db);
                $deviceId = $_POST['device_id'] ?? '';
                $onuId = $_POST['onu_id'] ?? '';
                
                // Look up ONU if onu_id provided
                if (empty($deviceId) && !empty($onuId)) {
                    $onuStmt = $db->prepare("SELECT sn, tr069_device_id, tr069_serial, genieacs_id FROM huawei_onus WHERE id = ?");
                    $onuStmt->execute([$onuId]);
                    $onuData = $onuStmt->fetch(PDO::FETCH_ASSOC);
                    if ($onuData) {
                        // Try genieacs_id first, then tr069_device_id
                        $deviceId = !empty($onuData['genieacs_id']) ? $onuData['genieacs_id'] : '';
                        if (empty($deviceId)) {
                            $deviceId = !empty($onuData['tr069_device_id']) ? $onuData['tr069_device_id'] : '';
                        }
                        
                        // If still empty, look up by serial
                        if (empty($deviceId)) {
                            // Try tr069_serial first (GenieACS format), then sn (OLT format)
                            $serial = !empty($onuData['tr069_serial']) ? $onuData['tr069_serial'] : 
                                     (!empty($onuData['sn']) ? $onuData['sn'] : '');
                            if (!empty($serial)) {
                                $deviceResult = $genieacs->getDeviceBySerial($serial);
                                if ($deviceResult['success']) {
                                    $deviceId = $deviceResult['device']['_id'] ?? '';
                                    // Update ONU record with found device ID
                                    if (!empty($deviceId)) {
                                        $updateStmt = $db->prepare("UPDATE huawei_onus SET genieacs_id = ? WHERE id = ?");
                                        $updateStmt->execute([$deviceId, $onuId]);
                                    }
                                }
                            }
                        }
                    }
                }
                
                if (empty($deviceId)) {
                    $message = 'Device not connected to TR-069/GenieACS';
                    $messageType = 'warning';
                    break;
                }
                
                $result = $genieacs->refreshDevice($deviceId);
                $message = $result['success'] ? 'Device info refresh requested' : ($result['error'] ?? 'Refresh failed');
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
            case 'tr069_wifi':
                require_once __DIR__ . '/../src/GenieACS.php';
                $genieacs = new \App\GenieACS($db);
                $result = $genieacs->setWiFiSettings(
                    $_POST['device_id'],
                    $_POST['ssid'],
                    $_POST['password'],
                    isset($_POST['enabled']),
                    (int)($_POST['channel'] ?? 0)
                );
                $message = $result['success'] ? 'WiFi configuration sent' : ($result['error'] ?? 'Configuration failed');
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
            case 'tr069_factory_reset':
                require_once __DIR__ . '/../src/GenieACS.php';
                $genieacs = new \App\GenieACS($db);
                $deviceId = $_POST['device_id'] ?? '';
                $onuId = $_POST['onu_id'] ?? '';
                
                // Look up ONU if onu_id provided
                if (empty($deviceId) && !empty($onuId)) {
                    $onuStmt = $db->prepare("SELECT sn, tr069_device_id, tr069_serial, genieacs_id FROM huawei_onus WHERE id = ?");
                    $onuStmt->execute([$onuId]);
                    $onuData = $onuStmt->fetch(PDO::FETCH_ASSOC);
                    if ($onuData) {
                        $deviceId = !empty($onuData['genieacs_id']) ? $onuData['genieacs_id'] : '';
                        if (empty($deviceId)) {
                            $deviceId = !empty($onuData['tr069_device_id']) ? $onuData['tr069_device_id'] : '';
                        }
                        if (empty($deviceId)) {
                            $serial = !empty($onuData['tr069_serial']) ? $onuData['tr069_serial'] : 
                                     (!empty($onuData['sn']) ? $onuData['sn'] : '');
                            if (!empty($serial)) {
                                $deviceResult = $genieacs->getDeviceBySerial($serial);
                                if ($deviceResult['success']) {
                                    $deviceId = $deviceResult['device']['_id'] ?? '';
                                    if (!empty($deviceId)) {
                                        $updateStmt = $db->prepare("UPDATE huawei_onus SET genieacs_id = ? WHERE id = ?");
                                        $updateStmt->execute([$deviceId, $onuId]);
                                    }
                                }
                            }
                        }
                    }
                }
                
                if (empty($deviceId)) {
                    $message = 'Device not connected to TR-069/GenieACS';
                    $messageType = 'warning';
                    break;
                }
                
                $result = $genieacs->factoryReset($deviceId);
                $message = $result['success'] ? 'Factory reset command sent - device will reboot' : ($result['error'] ?? 'Factory reset failed');
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
            
            case 'tr069_firmware':
                require_once __DIR__ . '/../src/GenieACS.php';
                $genieacs = new \App\GenieACS($db);
                $deviceId = $_POST['device_id'] ?? '';
                $onuId = $_POST['onu_id'] ?? '';
                $firmwareUrl = $_POST['firmware_url'] ?? '';
                
                if (empty($firmwareUrl)) {
                    $message = 'Firmware URL is required';
                    $messageType = 'warning';
                    break;
                }
                
                // Look up ONU if onu_id provided
                if (empty($deviceId) && !empty($onuId)) {
                    $onuStmt = $db->prepare("SELECT sn, tr069_device_id, tr069_serial, genieacs_id FROM huawei_onus WHERE id = ?");
                    $onuStmt->execute([$onuId]);
                    $onuData = $onuStmt->fetch(PDO::FETCH_ASSOC);
                    if ($onuData) {
                        $deviceId = !empty($onuData['genieacs_id']) ? $onuData['genieacs_id'] : '';
                        if (empty($deviceId)) {
                            $deviceId = !empty($onuData['tr069_device_id']) ? $onuData['tr069_device_id'] : '';
                        }
                        if (empty($deviceId)) {
                            $serial = !empty($onuData['tr069_serial']) ? $onuData['tr069_serial'] : 
                                     (!empty($onuData['sn']) ? $onuData['sn'] : '');
                            if (!empty($serial)) {
                                $deviceResult = $genieacs->getDeviceBySerial($serial);
                                if ($deviceResult['success']) {
                                    $deviceId = $deviceResult['device']['_id'] ?? '';
                                    if (!empty($deviceId)) {
                                        $updateStmt = $db->prepare("UPDATE huawei_onus SET genieacs_id = ? WHERE id = ?");
                                        $updateStmt->execute([$deviceId, $onuId]);
                                    }
                                }
                            }
                        }
                    }
                }
                
                if (empty($deviceId)) {
                    $message = 'Device not connected to TR-069/GenieACS';
                    $messageType = 'warning';
                    break;
                }
                
                $result = $genieacs->upgradeFirmware($deviceId, $firmwareUrl);
                $message = $result['success'] ? 'Firmware upgrade initiated - device will download and reboot' : ($result['error'] ?? 'Firmware upgrade failed');
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
            
            case 'tr069_admin_password':
                require_once __DIR__ . '/../src/GenieACS.php';
                $genieacs = new \App\GenieACS($db);
                $deviceId = $_POST['device_id'] ?? '';
                $newPassword = $_POST['new_password'] ?? '';
                $username = $_POST['admin_username'] ?? 'admin';
                
                if (empty($newPassword)) {
                    $message = 'Password is required';
                    $messageType = 'warning';
                } elseif (strlen($newPassword) < 6) {
                    $message = 'Password must be at least 6 characters';
                    $messageType = 'warning';
                } else {
                    $result = $genieacs->setAdminPassword($deviceId, $newPassword, $username);
                    if ($result['success']) {
                        $message = 'Admin password change command sent successfully. The device may need to reboot.';
                        $messageType = 'success';
                    } else {
                        $message = 'Failed to change admin password: ' . ($result['error'] ?? 'Unknown error');
                        $messageType = 'danger';
                    }
                }
                break;
            
            case 'tr069_wifi_advanced':
                require_once __DIR__ . '/../src/GenieACS.php';
                $genieacs = new \App\GenieACS($db);
                $deviceId = $_POST['device_id'] ?? '';
                
                // Build WiFi configuration for both bands
                $wifiConfig = [
                    'wifi_24' => [
                        'enabled' => isset($_POST['wifi24_enabled']),
                        'ssid' => $_POST['wifi24_ssid'] ?? '',
                        'password' => $_POST['wifi24_password'] ?? '',
                        'channel' => (int)($_POST['wifi24_channel'] ?? 0),
                        'mode' => $_POST['wifi24_mode'] ?? 'access',
                        'access_vlan' => (int)($_POST['wifi24_access_vlan'] ?? 1),
                        'native_vlan' => (int)($_POST['wifi24_native_vlan'] ?? 1),
                        'allowed_vlans' => $_POST['wifi24_allowed_vlans'] ?? ''
                    ],
                    'wifi_5' => [
                        'enabled' => isset($_POST['wifi5_enabled']),
                        'ssid' => $_POST['wifi5_ssid'] ?? '',
                        'password' => $_POST['wifi5_password'] ?? '',
                        'channel' => (int)($_POST['wifi5_channel'] ?? 0),
                        'mode' => $_POST['wifi5_mode'] ?? 'access',
                        'access_vlan' => (int)($_POST['wifi5_access_vlan'] ?? 1),
                        'native_vlan' => (int)($_POST['wifi5_native_vlan'] ?? 1),
                        'allowed_vlans' => $_POST['wifi5_allowed_vlans'] ?? ''
                    ],
                    'sync_both' => isset($_POST['sync_both'])
                ];
                
                // Sync SSIDs/passwords if requested
                if ($wifiConfig['sync_both'] && !empty($wifiConfig['wifi_24']['ssid'])) {
                    $wifiConfig['wifi_5']['ssid'] = $wifiConfig['wifi_24']['ssid'];
                    $wifiConfig['wifi_5']['password'] = $wifiConfig['wifi_24']['password'];
                }
                
                $result = $genieacs->setAdvancedWiFiConfig($deviceId, $wifiConfig);
                
                if ($result['success']) {
                    $message = 'WiFi configuration sent successfully. Changes may take a few moments to apply.';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to configure WiFi: ' . ($result['error'] ?? 'Unknown error');
                    $messageType = 'danger';
                }
                break;
            
            case 'apply_pending_tr069':
                require_once __DIR__ . '/../src/GenieACS.php';
                $genieacs = new \App\GenieACS($db);
                $onuId = (int)$_POST['onu_id'];
                
                // Check if GenieACS is configured
                if (!$genieacs->isConfigured()) {
                    $message = 'GenieACS is not configured. Please set the ACS URL in Settings.';
                    $messageType = 'danger';
                    break;
                }
                
                // Get pending config
                $stmt = $db->prepare("SELECT onu_id, config_data FROM huawei_onu_tr069_config WHERE onu_id = ? AND status = 'pending'");
                $stmt->execute([$onuId]);
                $pendingConfig = $stmt->fetch(\PDO::FETCH_ASSOC);
                
                if ($pendingConfig) {
                    $config = json_decode($pendingConfig['config_data'], true);
                    
                    // Get TR-069 device ID - first try local table
                    $stmt = $db->prepare("SELECT t.device_id FROM tr069_devices t JOIN huawei_onus o ON t.serial_number = o.sn WHERE o.id = ?");
                    $stmt->execute([$onuId]);
                    $tr069Device = $stmt->fetch(\PDO::FETCH_ASSOC);
                    
                    // Fallback: Query GenieACS directly by ONU serial
                    if (!$tr069Device || !$tr069Device['device_id']) {
                        $stmt = $db->prepare("SELECT sn FROM huawei_onus WHERE id = ?");
                        $stmt->execute([$onuId]);
                        $onu = $stmt->fetch(\PDO::FETCH_ASSOC);
                        if ($onu && !empty($onu['sn'])) {
                            $deviceResult = $genieacs->getDeviceBySerial($onu['sn']);
                            if ($deviceResult['success'] && isset($deviceResult['device'])) {
                                $tr069Device = ['device_id' => $deviceResult['device']['_id']];
                            }
                        }
                    }
                    
                    if ($tr069Device && $tr069Device['device_id']) {
                        $allSuccess = true;
                        $errors = [];
                        
                        // Apply WAN config using SmartOLT-style function
                        if (!empty($config['pppoe_username'])) {
                            $wanResult = $genieacs->configureInternetWAN($tr069Device['device_id'], [
                                'connection_type' => $config['connection_type'] ?? 'pppoe',
                                'pppoe_username' => $config['pppoe_username'],
                                'pppoe_password' => $config['pppoe_password'],
                                'service_vlan' => $config['wan_vlan'] ?? $config['service_vlan'] ?? 0,
                                'nat_enabled' => $config['nat_enable'] ?? true,
                                'enable_connection' => true
                            ]);
                            if (!$wanResult['success']) {
                                $allSuccess = false;
                                $errors[] = 'WAN: ' . implode(', ', $wanResult['errors'] ?? ['failed']);
                            }
                        }
                        
                        // Apply WiFi config
                        if (!empty($config['wifi_ssid_24'])) {
                            $wifiResult = $genieacs->setWirelessConfig($tr069Device['device_id'], [
                                'wifi_24_enable' => true,
                                'ssid_24' => $config['wifi_ssid_24'],
                                'wifi_pass_24' => $config['wifi_pass_24'],
                                'wifi_5_enable' => !empty($config['wifi_ssid_5']),
                                'ssid_5' => $config['wifi_ssid_5'] ?: $config['wifi_ssid_24'],
                                'wifi_pass_5' => $config['wifi_pass_5'] ?: $config['wifi_pass_24']
                            ]);
                            if (!$wifiResult['success']) {
                                $allSuccess = false;
                                $errors[] = 'WiFi: ' . ($wifiResult['error'] ?? 'failed');
                            }
                        }
                        
                        // Only mark as applied if ALL calls succeeded
                        if ($allSuccess) {
                            $stmt = $db->prepare("UPDATE huawei_onu_tr069_config SET status = 'applied', applied_at = CURRENT_TIMESTAMP, error_message = NULL WHERE onu_id = ?");
                            $stmt->execute([$pendingConfig['onu_id']]);
                            $message = 'TR-069 configuration applied successfully';
                            $messageType = 'success';
                        } else {
                            // Keep status as pending and store error
                            $errorMsg = implode('; ', $errors);
                            $stmt = $db->prepare("UPDATE huawei_onu_tr069_config SET error_message = ?, updated_at = CURRENT_TIMESTAMP WHERE onu_id = ?");
                            $stmt->execute([$errorMsg, $pendingConfig['onu_id']]);
                            $message = 'Failed to apply TR-069 configuration: ' . $errorMsg;
                            $messageType = 'danger';
                        }
                    } else {
                        $message = 'Device not found in GenieACS. The device must connect to ACS first.';
                        $messageType = 'warning';
                    }
                } else {
                    $message = 'No pending TR-069 configuration found';
                    $messageType = 'info';
                }
                break;
            case 'tr069_wireless_config':
                require_once __DIR__ . '/../src/GenieACS.php';
                $genieacs = new \App\GenieACS($db);
                $onuId = (int)$_POST['onu_id'];
                $stmt = $db->prepare("SELECT t.device_id FROM tr069_devices t JOIN huawei_onus o ON t.onu_id = o.id WHERE o.id = ?");
                $stmt->execute([$onuId]);
                $tr069Device = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($tr069Device && $tr069Device['device_id']) {
                    $config = [
                        'wifi_24_enable' => isset($_POST['wifi_24_enable']),
                        'ssid_24' => $_POST['ssid_24'] ?? '',
                        'wifi_pass_24' => $_POST['wifi_pass_24'] ?? '',
                        'channel_24' => $_POST['channel_24'] ?? 'auto',
                        'bandwidth_24' => $_POST['bandwidth_24'] ?? 40,
                        'hide_ssid_24' => isset($_POST['hide_ssid_24']),
                        'wifi_5_enable' => isset($_POST['wifi_5_enable']),
                        'ssid_5' => $_POST['ssid_5'] ?? '',
                        'wifi_pass_5' => $_POST['wifi_pass_5'] ?? '',
                        'channel_5' => $_POST['channel_5'] ?? 'auto',
                        'bandwidth_5' => $_POST['bandwidth_5'] ?? 80,
                        'hide_ssid_5' => isset($_POST['hide_ssid_5']),
                        'max_clients' => (int)($_POST['max_clients'] ?? 32)
                    ];
                    $result = $genieacs->setWirelessConfig($tr069Device['device_id'], $config);
                    $message = $result['success'] ? 'WiFi configuration sent to device' : ($result['error'] ?? 'WiFi config failed');
                } else {
                    $message = 'Device not found in TR-069. Please sync devices first.';
                    $result = ['success' => false];
                }
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
            case 'tr069_lan_config':
                require_once __DIR__ . '/../src/GenieACS.php';
                $genieacs = new \App\GenieACS($db);
                $onuId = (int)$_POST['onu_id'];
                $stmt = $db->prepare("SELECT t.device_id FROM tr069_devices t JOIN huawei_onus o ON t.onu_id = o.id WHERE o.id = ?");
                $stmt->execute([$onuId]);
                $tr069Device = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($tr069Device && $tr069Device['device_id']) {
                    $config = [
                        'lan_ip' => $_POST['lan_ip'] ?? '192.168.1.1',
                        'lan_mask' => $_POST['lan_mask'] ?? '255.255.255.0',
                        'dhcp_enable' => isset($_POST['dhcp_enable']),
                        'dhcp_start' => $_POST['dhcp_start'] ?? '192.168.1.100',
                        'dhcp_end' => $_POST['dhcp_end'] ?? '192.168.1.200',
                        'dhcp_lease' => (int)($_POST['dhcp_lease'] ?? 24)
                    ];
                    $result = $genieacs->setLANConfig($tr069Device['device_id'], $config);
                    $message = $result['success'] ? 'LAN configuration sent to device' : ($result['error'] ?? 'LAN config failed');
                } else {
                    $message = 'Device not found in TR-069. Please sync devices first.';
                    $result = ['success' => false];
                }
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
            case 'tr069_wan_config':
                // SmartOLT-style Internet WAN configuration via TR-069
                require_once __DIR__ . '/../src/GenieACS.php';
                require_once __DIR__ . '/../src/HuaweiOLT.php';
                $genieacs = new \App\GenieACS($db);
                $huaweiOLT = new \App\HuaweiOLT($db);
                $onuId = (int)$_POST['onu_id'];
                
                $onu = $huaweiOLT->getONU($onuId);
                if (!$onu) {
                    $message = 'ONU not found';
                    $messageType = 'danger';
                    break;
                }
                
                $deviceResult = $genieacs->getDeviceBySerial($onu['sn']);
                if (!$deviceResult['success']) {
                    $message = 'Device not found in GenieACS. Please ensure TR-069 is configured.';
                    $messageType = 'warning';
                    break;
                }
                
                $deviceId = $deviceResult['device']['_id'];
                $connType = $_POST['wan_type'] ?? $_POST['connection_type'] ?? 'pppoe';
                
                // NOTE: Huawei ONUs require WANPPPConnection.1 to already exist from OMCI
                $config = [
                    'connection_type' => $connType,
                    'service_vlan' => (int)($_POST['wan_vlan'] ?? $_POST['vlan_id'] ?? 0),
                    'wan_index' => 1, // WANConnectionDevice.1 (created by OMCI)
                    'connection_name' => $connType === 'pppoe' ? 'Internet_PPPoE' : 'Internet_' . strtoupper($connType),
                ];
                
                if ($connType === 'pppoe') {
                    $config['pppoe_username'] = $_POST['pppoe_user'] ?? $_POST['pppoe_username'] ?? '';
                    $config['pppoe_password'] = $_POST['pppoe_pass'] ?? $_POST['pppoe_password'] ?? '';
                } elseif ($connType === 'static') {
                    $config['static_ip'] = $_POST['static_ip'] ?? '';
                    $config['static_mask'] = $_POST['static_mask'] ?? '255.255.255.0';
                    $config['static_gateway'] = $_POST['static_gw'] ?? $_POST['gateway'] ?? '';
                }
                
                $result = $genieacs->configureInternetWAN($deviceId, $config);
                
                if ($result['success']) {
                    $message = 'Internet WAN configured via TR-069. WAN: ' . ($result['wan_name'] ?? 'wan2.1.ppp1');
                    $messageType = 'success';
                } else {
                    $message = 'Failed to configure WAN: ' . implode(', ', $result['errors'] ?? ['Unknown error']);
                    $messageType = 'danger';
                }
                break;
            case 'create_vlan':
                $vlanOptions = [
                    'is_multicast' => !empty($_POST['is_multicast']),
                    'is_voip' => !empty($_POST['is_voip']),
                    'is_tr069' => !empty($_POST['is_tr069']),
                    'dhcp_snooping' => !empty($_POST['dhcp_snooping']),
                    'lan_to_lan' => !empty($_POST['lan_to_lan'])
                ];
                $result = $huaweiOLT->createVLAN(
                    (int)$_POST['olt_id'],
                    (int)$_POST['vlan_id'],
                    $_POST['description'] ?? '',
                    $_POST['vlan_type'] ?? 'smart',
                    $vlanOptions
                );
                $message = $result['success'] ? 'VLAN created successfully' : ($result['message'] ?? 'Failed to create VLAN');
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
            case 'delete_vlan':
                $result = $huaweiOLT->deleteVLAN((int)$_POST['olt_id'], (int)$_POST['vlan_id']);
                $message = $result['success'] ? 'VLAN deleted successfully' : ($result['message'] ?? 'Failed to delete VLAN');
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
            case 'update_vlan_desc':
                $result = $huaweiOLT->updateVLANDescription((int)$_POST['olt_id'], (int)$_POST['vlan_id'], $_POST['description'] ?? '');
                $message = $result['success'] ? 'VLAN description updated' : ($result['message'] ?? 'Failed to update description');
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
            case 'update_vlan_features':
                $options = [
                    'is_multicast' => !empty($_POST['is_multicast']),
                    'is_voip' => !empty($_POST['is_voip']),
                    'is_tr069' => !empty($_POST['is_tr069']),
                    'dhcp_snooping' => !empty($_POST['dhcp_snooping']),
                    'lan_to_lan' => !empty($_POST['lan_to_lan'])
                ];
                $result = $huaweiOLT->updateVLANFeatures((int)$_POST['olt_id'], (int)$_POST['vlan_id'], $_POST['description'] ?? '', $options);
                $message = $result['success'] ? 'VLAN features updated' : ($result['message'] ?? 'Failed to update features');
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
            case 'add_vlan_uplink':
                $result = $huaweiOLT->addVLANToUplink((int)$_POST['olt_id'], $_POST['port_name'], (int)$_POST['vlan_id']);
                $message = $result['success'] ? "VLAN {$_POST['vlan_id']} added to uplink {$_POST['port_name']}" : ($result['message'] ?? 'Failed to add VLAN');
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
            case 'refresh_onu_optical':
                $result = $huaweiOLT->refreshONUOptical((int)$_POST['onu_id']);
                if ($result['success']) {
                    $message = "Optical: RX={$result['rx_power']}dBm, TX={$result['tx_power']}dBm";
                    $messageType = 'success';
                } else {
                    $message = $result['error'] ?? 'Failed to refresh optical data';
                    $messageType = 'danger';
                }
                break;
            case 'get_onu_full_status':
                header('Content-Type: application/json');
                $result = $huaweiOLT->getONUFullStatus((int)$_POST['onu_id']);
                echo json_encode($result);
                exit;
            case 'get_onu_config':
                header('Content-Type: application/json');
                $result = $huaweiOLT->getONUConfig((int)$_POST['onu_id']);
                echo json_encode($result);
                exit;
            case 'get_onu_sw_info':
                header('Content-Type: application/json');
                $onu = $huaweiOLT->getONU((int)$_POST['onu_id']);
                if (!$onu) {
                    echo json_encode(['success' => false, 'error' => 'ONU not found']);
                    exit;
                }
                $portNum = (int)$onu['port'];
                $onuNum = (int)$onu['onu_id'];
                $cmd = "display ont version {$onu['frame']}/{$onu['slot']} " . $portNum . " " . $onuNum;
                $cmdResult = $huaweiOLT->executeCommand($onu['olt_id'], $cmd);
                $output = $cmdResult['output'] ?? '';
                $swVersion = '-'; $hwVersion = '-'; $onuType = '-'; $uptime = '-';
                if (preg_match('/Software\s*Version\s*[:\s]+([^\r\n]+)/i', $output, $m)) $swVersion = trim($m[1]);
                if (preg_match('/Hardware\s*Version\s*[:\s]+([^\r\n]+)/i', $output, $m)) $hwVersion = trim($m[1]);
                if (preg_match('/Equipment-ID\s*[:\s]+([^\r\n]+)/i', $output, $m)) $onuType = trim($m[1]);
                if (preg_match('/Online\s*duration\s*[:\s]+([^\r\n]+)/i', $output, $m)) $uptime = trim($m[1]);
                echo json_encode([
                    'success' => true,
                    'sw_version' => $swVersion,
                    'hw_version' => $hwVersion,
                    'onu_type' => $onuType,
                    'uptime' => $uptime,
                    'raw_output' => $output
                ]);
                exit;
            case 'get_tr069_stat':
                header('Content-Type: application/json');
                $onu = $huaweiOLT->getONU((int)$_POST['onu_id']);
                if (!$onu) {
                    echo json_encode(['success' => false, 'error' => 'ONU not found']);
                    exit;
                }
                
                $serialForLookup = $onu['tr069_serial'] ?? $onu['sn'] ?? '';
                
                $result = [
                    'success' => true,
                    'status' => 'pending',
                    'ip' => '-',
                    'acs_url' => '-',
                    'last_inform' => '-',
                    'inform_interval' => '-',
                    'manufacturer' => '-',
                    'oui' => '-',
                    'model' => '-',
                    'sw_version' => '-',
                    'hw_version' => '-',
                    'provisioning_code' => '-',
                    'serial' => $onu['sn'] ?? '-',
                    'cpu_usage' => '-',
                    'ram_usage' => '-',
                    'uptime' => '-',
                    'uptime_seconds' => 0,
                    'found_in_acs' => false,
                    'wifi' => [],
                    'wan' => [],
                    'lan' => [],
                    'admin_password' => '-'
                ];
                
                if (!empty($serialForLookup)) {
                    try {
                        require_once __DIR__ . '/../src/GenieACS.php';
                        $genieacs = new \App\GenieACS($db);
                        $deviceResult = $genieacs->getDeviceBySerial($serialForLookup);
                        if ($deviceResult['success'] && !empty($deviceResult['device'])) {
                            $device = $deviceResult['device'];
                            $result['found_in_acs'] = true;
                            $result['device_id'] = $device['_id'] ?? '';
                            
                            $lastInformTime = isset($device['_lastInform']) ? strtotime($device['_lastInform']) : 0;
                            $fiveMinutesAgo = time() - 300;
                            $result['status'] = ($lastInformTime >= $fiveMinutesAgo) ? 'online' : 'offline';
                            
                            $igd = $device['InternetGatewayDevice'] ?? [];
                            $devInfo = $igd['DeviceInfo'] ?? [];
                            $mgmtServer = $igd['ManagementServer'] ?? [];
                            $lanDevice = $igd['LANDevice'] ?? [];
                            $wanDevice = $igd['WANDevice'] ?? [];
                            
                            // Basic device info
                            if (isset($devInfo['Manufacturer']['_value'])) $result['manufacturer'] = $devInfo['Manufacturer']['_value'];
                            if (isset($device['_deviceId']['_OUI'])) $result['oui'] = $device['_deviceId']['_OUI'];
                            if (isset($devInfo['ModelName']['_value'])) $result['model'] = $devInfo['ModelName']['_value'];
                            elseif (isset($device['_deviceId']['_ProductClass'])) $result['model'] = $device['_deviceId']['_ProductClass'];
                            if (isset($devInfo['SoftwareVersion']['_value'])) $result['sw_version'] = $devInfo['SoftwareVersion']['_value'];
                            if (isset($devInfo['HardwareVersion']['_value'])) $result['hw_version'] = $devInfo['HardwareVersion']['_value'];
                            if (isset($devInfo['ProvisioningCode']['_value'])) $result['provisioning_code'] = $devInfo['ProvisioningCode']['_value'];
                            if (isset($devInfo['SerialNumber']['_value'])) $result['serial'] = $devInfo['SerialNumber']['_value'];
                            elseif (isset($device['_deviceId']['_SerialNumber'])) $result['serial'] = $device['_deviceId']['_SerialNumber'];
                            
                            // CPU/RAM
                            if (isset($igd['X_HW_DeviceMgr']['CpuUsage']['_value'])) $result['cpu_usage'] = $igd['X_HW_DeviceMgr']['CpuUsage']['_value'] . '%';
                            elseif (isset($devInfo['ProcessStatus']['CPUUsage']['_value'])) $result['cpu_usage'] = $devInfo['ProcessStatus']['CPUUsage']['_value'] . '%';
                            if (isset($igd['X_HW_DeviceMgr']['MemUsage']['_value'])) $result['ram_usage'] = $igd['X_HW_DeviceMgr']['MemUsage']['_value'] . '%';
                            
                            // Uptime
                            if (isset($devInfo['UpTime']['_value'])) {
                                $uptimeSec = (int)$devInfo['UpTime']['_value'];
                                $result['uptime_seconds'] = $uptimeSec;
                                $d = floor($uptimeSec / 86400); $h = floor(($uptimeSec % 86400) / 3600); $m = floor(($uptimeSec % 3600) / 60);
                                $result['uptime'] = ($d > 0 ? "{$d}d " : '') . ($h > 0 ? "{$h}h " : '') . "{$m}m";
                            }
                            
                            // Management Server
                            if (isset($mgmtServer['ConnectionRequestURL']['_value'])) {
                                if (preg_match('/https?:\/\/([^:\/]+)/', $mgmtServer['ConnectionRequestURL']['_value'], $m)) $result['ip'] = $m[1];
                            }
                            if (isset($mgmtServer['URL']['_value'])) $result['acs_url'] = $mgmtServer['URL']['_value'];
                            if (isset($mgmtServer['PeriodicInformInterval']['_value'])) $result['inform_interval'] = $mgmtServer['PeriodicInformInterval']['_value'];
                            if (isset($device['_lastInform'])) $result['last_inform'] = date('Y-m-d H:i:s', strtotime($device['_lastInform']));
                            
                            // Admin Password
                            if (isset($igd['X_HW_WebUserInfo']['UserName']['_value'])) {
                                $result['admin_user'] = $igd['X_HW_WebUserInfo']['UserName']['_value'];
                            }
                            if (isset($igd['X_HW_WebUserInfo']['Password']['_value'])) {
                                $result['admin_password'] = $igd['X_HW_WebUserInfo']['Password']['_value'];
                            }
                            
                            // WiFi Configuration
                            foreach ($lanDevice as $lanIdx => $lan) {
                                if (!is_array($lan) || !isset($lan['WLANConfiguration'])) continue;
                                foreach ($lan['WLANConfiguration'] as $wlanIdx => $wlan) {
                                    if (!is_array($wlan)) continue;
                                    $wifiEntry = [
                                        'index' => $wlanIdx,
                                        'enabled' => $wlan['Enable']['_value'] ?? false,
                                        'ssid' => $wlan['SSID']['_value'] ?? '-',
                                        'password' => $wlan['PreSharedKey']['1']['PreSharedKey']['_value'] ?? $wlan['KeyPassphrase']['_value'] ?? '-',
                                        'channel' => $wlan['Channel']['_value'] ?? 'Auto',
                                        'security' => $wlan['BeaconType']['_value'] ?? '-',
                                        'standard' => $wlan['Standard']['_value'] ?? '-',
                                        'mac' => $wlan['BSSID']['_value'] ?? '-'
                                    ];
                                    // Determine band from standard or frequency
                                    if (isset($wlan['X_HW_WlanRfPara']['FreqBand']['_value'])) {
                                        $wifiEntry['band'] = $wlan['X_HW_WlanRfPara']['FreqBand']['_value'] == '5' ? '5GHz' : '2.4GHz';
                                    } elseif (strpos($wifiEntry['standard'], 'a') !== false || strpos($wifiEntry['standard'], 'ac') !== false) {
                                        $wifiEntry['band'] = '5GHz';
                                    } else {
                                        $wifiEntry['band'] = '2.4GHz';
                                    }
                                    $result['wifi'][] = $wifiEntry;
                                }
                            }
                            
                            // WAN Configuration
                            foreach ($wanDevice as $wanIdx => $wan) {
                                if (!is_array($wan) || !isset($wan['WANConnectionDevice'])) continue;
                                foreach ($wan['WANConnectionDevice'] as $connIdx => $connDev) {
                                    if (!is_array($connDev)) continue;
                                    // PPP Connections
                                    if (isset($connDev['WANPPPConnection'])) {
                                        foreach ($connDev['WANPPPConnection'] as $pppIdx => $ppp) {
                                            if (!is_array($ppp)) continue;
                                            $result['wan'][] = [
                                                'type' => 'PPPoE',
                                                'name' => $ppp['Name']['_value'] ?? "WAN $pppIdx",
                                                'enabled' => $ppp['Enable']['_value'] ?? false,
                                                'username' => $ppp['Username']['_value'] ?? '-',
                                                'status' => $ppp['ConnectionStatus']['_value'] ?? '-',
                                                'ip' => $ppp['ExternalIPAddress']['_value'] ?? '-',
                                                'vlan' => $ppp['X_HW_VLAN']['_value'] ?? '-',
                                                'nat' => $ppp['NATEnabled']['_value'] ?? false
                                            ];
                                        }
                                    }
                                    // IP Connections (DHCP/Static)
                                    if (isset($connDev['WANIPConnection'])) {
                                        foreach ($connDev['WANIPConnection'] as $ipIdx => $ipConn) {
                                            if (!is_array($ipConn)) continue;
                                            $addrType = $ipConn['AddressingType']['_value'] ?? 'DHCP';
                                            $result['wan'][] = [
                                                'type' => $addrType === 'Static' ? 'Static' : 'DHCP',
                                                'name' => $ipConn['Name']['_value'] ?? "WAN $ipIdx",
                                                'enabled' => $ipConn['Enable']['_value'] ?? false,
                                                'status' => $ipConn['ConnectionStatus']['_value'] ?? '-',
                                                'ip' => $ipConn['ExternalIPAddress']['_value'] ?? '-',
                                                'vlan' => $ipConn['X_HW_VLAN']['_value'] ?? '-',
                                                'nat' => $ipConn['NATEnabled']['_value'] ?? false
                                            ];
                                        }
                                    }
                                }
                            }
                            
                            // LAN Configuration
                            foreach ($lanDevice as $lanIdx => $lan) {
                                if (!is_array($lan) || !isset($lan['LANHostConfigManagement'])) continue;
                                $lanCfg = $lan['LANHostConfigManagement'];
                                $result['lan'] = [
                                    'dhcp_enabled' => $lanCfg['DHCPServerEnable']['_value'] ?? false,
                                    'ip' => $lanCfg['IPInterface']['1']['IPInterfaceIPAddress']['_value'] ?? '-',
                                    'subnet' => $lanCfg['SubnetMask']['_value'] ?? '-',
                                    'dhcp_start' => $lanCfg['MinAddress']['_value'] ?? '-',
                                    'dhcp_end' => $lanCfg['MaxAddress']['_value'] ?? '-',
                                    'lease_time' => $lanCfg['DHCPLeaseTime']['_value'] ?? '-'
                                ];
                                break;
                            }
                        }
                    } catch (Exception $e) {
                        $result['error_detail'] = $e->getMessage();
                    }
                }
                
                // Fallback to DB values
                if ($result['ip'] === '-' && !empty($onu['tr069_ip'])) $result['ip'] = $onu['tr069_ip'];
                if ($result['last_inform'] === '-' && !empty($onu['tr069_last_inform'])) {
                    $result['last_inform'] = date('Y-m-d H:i:s', strtotime($onu['tr069_last_inform']));
                    $lastInformTime = strtotime($onu['tr069_last_inform']);
                    if ($result['status'] === 'pending' && $lastInformTime >= time() - 300) $result['status'] = 'online';
                }
                
                // Add DB-stored config
                if (!empty($onu['pppoe_username'])) $result['db_pppoe_user'] = $onu['pppoe_username'];
                if (!empty($onu['wan_mode'])) $result['db_wan_mode'] = $onu['wan_mode'];
                if (!empty($onu['vlan_id'])) $result['db_vlan'] = $onu['vlan_id'];
                
                echo json_encode($result);
                exit;
            
                        case 'get_tr069_device_info':
                header('Content-Type: application/json');
                try {
                    $onuId = (int)($_GET['onu_id'] ?? 0);
                    if (!$onuId) {
                        echo json_encode(['success' => false, 'error' => 'ONU ID required']);
                        exit;
                    }
                    
                    // Get ONU details to find serial number
                    $stmt = $db->prepare("SELECT sn, tr069_serial FROM huawei_onus WHERE id = ?");
                    $stmt->execute([$onuId]);
                    $onu = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$onu) {
                        echo json_encode(['success' => false, 'error' => 'ONU not found']);
                        exit;
                    }
                    
                    // Query GenieACS for device - try multiple serial formats
                    $genieACS = new GenieACS($db);
                    $serial = !empty($onu['tr069_serial']) ? $onu['tr069_serial'] : $onu['sn'];
                    
                    $searchFormats = [$serial, strtoupper($serial)];
                    $upperSerial = strtoupper($serial);
                    if (preg_match('/^[A-Z]{4}[0-9A-F]{8}$/i', $upperSerial)) {
                        $searchFormats[] = $genieACS->convertOltSerialToGenieacs($upperSerial);
                    }
                    
                    $device = null;
                    foreach ($searchFormats as $sn) {
                        if (empty($sn)) continue;
                        $result = $genieACS->getDeviceBySerial($sn);
                        if ($result['success'] && !empty($result['device'])) {
                            $device = $result['device'];
                            break;
                        }
                    }
                    
                    if ($device) {
                        // Return full device object for status display
                        echo json_encode([
                            'success' => true, 
                            'device' => $device
                        ]);
                    } else {
                        echo json_encode(['success' => false, 'error' => 'Device not found in GenieACS', 'tried' => $searchFormats]);
                    }
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
                exit;

            
            case 'get_tr069_device_by_serial':
                header('Content-Type: application/json');
                try {
                    $serial = $_GET['serial'] ?? '';
                    if (empty($serial)) {
                        echo json_encode(['success' => false, 'error' => 'Serial number required']);
                        exit;
                    }
                    
                    error_log("[TR069 Lookup] Serial: {$serial}");
                    
                    // Query GenieACS for device by serial - try multiple formats
                    $genieACS = new GenieACS($db);
                    
                    $searchFormats = [$serial, strtoupper($serial)];
                    $upperSerial = strtoupper($serial);
                    if (preg_match('/^[A-Z]{4}[0-9A-F]{8}$/i', $upperSerial)) {
                        $searchFormats[] = $genieACS->convertOltSerialToGenieacs($upperSerial);
                    }
                    
                    $device = null;
                    foreach ($searchFormats as $sn) {
                        error_log("[TR069 Lookup] Trying format: {$sn}");
                        $result = $genieACS->getDeviceBySerial($sn);
                        if ($result['success'] && !empty($result['device'])) {
                            $device = $result['device'];
                            error_log("[TR069 Lookup] Found with format: {$sn}");
                            break;
                        }
                    }
                    
                    if ($device) {
                        echo json_encode([
                            'success' => true, 
                            'device' => [
                                '_id' => $device['_id'] ?? null,
                                '_lastInform' => $device['_lastInform'] ?? null,
                                '_registered' => $device['_registered'] ?? null,
                                '_deviceId' => $device['_deviceId'] ?? null
                            ]
                        ]);
                    } else {
                        echo json_encode(['success' => false, 'error' => 'Device not found in GenieACS', 'tried' => $searchFormats]);
                    }
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
                exit;

            
            case 'tr069_connection_request_by_serial':
                header('Content-Type: application/json');
                try {
                    $serial = $_POST['serial'] ?? $_GET['serial'] ?? '';
                    if (empty($serial)) {
                        echo json_encode(['success' => false, 'error' => 'Serial number required']);
                        exit;
                    }
                    
                    // Send connection request via GenieACS
                    $genieACS = new GenieACS($db);
                    $result = $genieACS->sendConnectionRequest($serial);
                    
                    echo json_encode($result);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
                exit;
            
            case 'tr069_connection_request':
                header('Content-Type: application/json');
                try {
                    $onuId = (int)($_POST['onu_id'] ?? $_GET['onu_id'] ?? 0);
                    if (!$onuId) {
                        echo json_encode(['success' => false, 'error' => 'ONU ID required']);
                        exit;
                    }
                    
                    // Get ONU details
                    $stmt = $db->prepare("SELECT serial_number FROM huawei_onus WHERE id = ?");
                    $stmt->execute([$onuId]);
                    $onu = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$onu) {
                        echo json_encode(['success' => false, 'error' => 'ONU not found']);
                        exit;
                    }
                    
                    // Send connection request via GenieACS
                    $genieACS = new GenieACS($db);
                    $result = $genieACS->sendConnectionRequest($onu['serial_number']);
                    
                    echo json_encode($result);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
                exit;
            
            case 'enable_instant_provisioning':
                header('Content-Type: application/json');
                try {
                    $serial = $_POST['serial'] ?? '';
                    if (empty($serial)) {
                        echo json_encode(['success' => false, 'error' => 'Serial number required']);
                        exit;
                    }
                    
                    $genieACS = new GenieACS($db);
                    $result = $genieACS->enableInstantProvisioning($serial);
                    
                    echo json_encode($result);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
                exit;
            

            case 'queue_clear_auth':
                header('Content-Type: application/json');
                try {
                    $serial = $_POST['serial'] ?? '';
                    if (empty($serial)) {
                        echo json_encode(['success' => false, 'error' => 'Serial number required']);
                        exit;
                    }
                    
                    $genieACS = new GenieACS($db);
                    $result = $genieACS->queueClearAuth($serial);
                    
                    echo json_encode($result);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
                exit;

            case 'save_tr069_wifi':
                header('Content-Type: application/json');
                try {
                    $onuId = (int)($_POST['onu_id'] ?? 0);
                    $wlanIndex = (int)($_POST['wlan_index'] ?? 1);
                    $enabled = ($_POST['enabled'] ?? '1') === '1';
                    $ssid = $_POST['ssid'] ?? '';
                    $password = $_POST['password'] ?? '';
                    $channel = (int)($_POST['channel'] ?? 0);
                    $security = $_POST['security'] ?? 'WPA2-PSK';
                    
                    // Bridge/VLAN parameters
                    $connMode = $_POST['conn_mode'] ?? 'route';
                    $vlanMode = $_POST['vlan_mode'] ?? 'access';
                    $accessVlan = (int)($_POST['access_vlan'] ?? 0);
                    $nativeVlan = (int)($_POST['native_vlan'] ?? 0);
                    $allowedVlans = $_POST['allowed_vlans'] ?? '';
                    
                    if (!$onuId || !$ssid) {
                        echo json_encode(['success' => false, 'error' => 'ONU ID and SSID are required']);
                        exit;
                    }
                    
                    // Use the same method as configureWANViaTR069 for consistency
                    $result = $huaweiOLT->configureWiFiViaTR069($onuId, [
                        'wlan_index' => $wlanIndex,
                        'enabled' => $enabled,
                        'ssid' => $ssid,
                        'password' => $password,
                        'channel' => $channel,
                        'security' => $security,
                        'conn_mode' => $connMode,
                        'vlan_mode' => $vlanMode,
                        'access_vlan' => $accessVlan,
                        'native_vlan' => $nativeVlan,
                        'allowed_vlans' => $allowedVlans
                    ]);
                    
                    echo json_encode($result);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'error' => 'Exception: ' . $e->getMessage()]);
                }
                exit;
            
            case 'start_pppoe_provisioning':
                // Direct parameter setting - NO provisions/scripts (same as WiFi config)
                header('Content-Type: application/json');
                try {
                    require_once __DIR__ . '/../src/GenieACS.php';
                    require_once __DIR__ . '/../src/HuaweiOLT.php';
                    
                    $onuId = (int)($_POST['onu_id'] ?? 0);
                    if (!$onuId) {
                        echo json_encode(['success' => false, 'error' => 'ONU ID required']);
                        exit;
                    }
                    
                    $huaweiOLT = new \App\HuaweiOLT($db);
                    $onu = $huaweiOLT->getONU($onuId);
                    if (!$onu) {
                        echo json_encode(['success' => false, 'error' => 'ONU not found']);
                        exit;
                    }
                    
                    $genieacs = new \App\GenieACS($db);
                    $deviceResult = $genieacs->getDeviceBySerial($onu['sn']);
                    if (!$deviceResult['success']) {
                        echo json_encode(['success' => false, 'error' => 'ONU not found in GenieACS']);
                        exit;
                    }
                    
                    $deviceId = $deviceResult['device']['_id'];
                    
                    // Use direct parameter setting - same approach as WiFi config
                    $config = [
                        'connection_type' => 'pppoe',
                        'service_vlan' => (int)($_POST['vlan'] ?? $_POST['service_vlan'] ?? 0),
                        'pppoe_username' => $_POST['pppoe_username'] ?? '',
                        'pppoe_password' => $_POST['pppoe_password'] ?? '',
                        'skip_safety_checks' => true
                    ];
                    
                    $result = $genieacs->configureInternetWAN($deviceId, $config);
                    
                    if ($result['success']) {
                        $updateStmt = $db->prepare("UPDATE huawei_onus SET pppoe_username = ?, wan_mode = 'pppoe' WHERE id = ?");
                        $updateStmt->execute([$config['pppoe_username'], $onuId]);
                    }
                    
                    echo json_encode($result);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
                exit;
            
            case 'get_provisioning_status':
                header('Content-Type: application/json');
                try {
                    require_once __DIR__ . '/../src/TR069Provisioner.php';
                    $onuId = (int)($_GET['onu_id'] ?? $_POST['onu_id'] ?? 0);
                    if (!$onuId) {
                        echo json_encode(['success' => false, 'error' => 'ONU ID required']);
                        exit;
                    }
                    
                    $provisioner = new \App\TR069Provisioner($db);
                    $result = $provisioner->getProvisioningStatus($onuId);
                    
                    echo json_encode($result);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
                exit;
            
            case 'continue_provisioning':
                header('Content-Type: application/json');
                try {
                    require_once __DIR__ . '/../src/TR069Provisioner.php';
                    $onuId = (int)($_POST['onu_id'] ?? 0);
                    if (!$onuId) {
                        echo json_encode(['success' => false, 'error' => 'ONU ID required']);
                        exit;
                    }
                    
                    $provisioner = new \App\TR069Provisioner($db);
                    $result = $provisioner->processNextStep($onuId);
                    
                    echo json_encode($result);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
                exit;
            
            case 'cancel_provisioning':
                header('Content-Type: application/json');
                try {
                    require_once __DIR__ . '/../src/TR069Provisioner.php';
                    $onuId = (int)($_POST['onu_id'] ?? 0);
                    if (!$onuId) {
                        echo json_encode(['success' => false, 'error' => 'ONU ID required']);
                        exit;
                    }
                    
                    $provisioner = new \App\TR069Provisioner($db);
                    $result = $provisioner->cancelProvisioning($onuId);
                    
                    echo json_encode($result);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
                exit;
            
            case 'save_tr069_wan':
                header('Content-Type: application/json');
                try {
                    $onuId = (int)($_POST['onu_id'] ?? 0);
                    $wanMode = $_POST['wan_mode'] ?? 'bridge';
                    $vlan = $_POST['vlan'] ?? '';
                    $pppoeUser = $_POST['pppoe_user'] ?? '';
                    $pppoePass = $_POST['pppoe_pass'] ?? '';
                    
                    if (!$onuId) {
                        echo json_encode(['success' => false, 'error' => 'ONU ID required']);
                        exit;
                    }
                    
                    if ($wanMode === 'bridge') {
                        // Bridge mode - just update DB, no TR-069
                        $huaweiOLT->updateONU($onuId, ['wan_mode' => $wanMode, 'vlan_id' => (int)$vlan ?: null]);
                        echo json_encode(['success' => true, 'message' => 'Bridge mode saved (no TR-069 config needed)']);
                        exit;
                    }
                    
                    // Use the same method as the working API endpoint
                    $result = $huaweiOLT->configureWANViaTR069($onuId, [
                        'wan_mode' => $wanMode,
                        'service_vlan' => (int)$vlan,
                        'pppoe_username' => $pppoeUser,
                        'pppoe_password' => $pppoePass
                    ]);
                    
                    echo json_encode($result);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'error' => 'Exception: ' . $e->getMessage()]);
                }
                exit;
            
            case 'update_pppoe_credentials':
                // Update only PPPoE credentials for existing connection
                header('Content-Type: application/json');
                try {
                    $onuId = (int)($_POST['onu_id'] ?? 0);
                    $pppoeUser = $_POST['pppoe_user'] ?? '';
                    $pppoePass = $_POST['pppoe_pass'] ?? '';
                    
                    if (!$onuId) {
                        echo json_encode(['success' => false, 'error' => 'ONU ID required']);
                        exit;
                    }
                    
                    if (empty($pppoeUser)) {
                        echo json_encode(['success' => false, 'error' => 'PPPoE username required']);
                        exit;
                    }
                    
                    // Get ONU and device info
                    $onu = $huaweiOLT->getONU($onuId);
                    if (!$onu) {
                        echo json_encode(['success' => false, 'error' => 'ONU not found']);
                        exit;
                    }
                    
                    $genieacsId = $onu['genieacs_id'] ?? null;
                    if (empty($genieacsId)) {
                        echo json_encode(['success' => false, 'error' => 'ONU not registered in GenieACS']);
                        exit;
                    }
                    
                    // Get GenieACS URL
                    $genieacsUrl = '';
                    $stmt = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'genieacs_url'");
                    if ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                        $genieacsUrl = rtrim($row['setting_value'], '/');
                        $genieacsUrl = preg_replace('/:\d+$/', ':7557', parse_url($genieacsUrl, PHP_URL_SCHEME) . '://' . parse_url($genieacsUrl, PHP_URL_HOST));
                    }
                    
                    if (empty($genieacsUrl)) {
                        echo json_encode(['success' => false, 'error' => 'GenieACS not configured']);
                        exit;
                    }
                    
                    // Build task to update only PPPoE credentials
                    $params = [
                        ['InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.Username', $pppoeUser, 'xsd:string']
                    ];
                    
                    // Only add password if provided
                    if (!empty($pppoePass)) {
                        $params[] = ['InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.Password', $pppoePass, 'xsd:string'];
                    }
                    
                    $task = ['name' => 'setParameterValues', 'parameterValues' => $params];
                    
                    $ch = curl_init("{$genieacsUrl}/devices/" . urlencode($genieacsId) . "/tasks?connection_request");
                    curl_setopt_array($ch, [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_POST => true,
                        CURLOPT_POSTFIELDS => json_encode($task),
                        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                        CURLOPT_TIMEOUT => 30
                    ]);
                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    
                    if ($httpCode >= 200 && $httpCode < 300) {
                        // Update DB
                        $stmt = $db->prepare("UPDATE huawei_onus SET pppoe_username = ? WHERE id = ?");
                        $stmt->execute([$pppoeUser, $onuId]);
                        
                        echo json_encode(['success' => true, 'message' => 'PPPoE credentials updated']);
                    } else {
                        echo json_encode(['success' => false, 'error' => 'GenieACS returned ' . $httpCode]);
                    }
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'error' => 'Exception: ' . $e->getMessage()]);
                }
                exit;
            
            case 'tr069_sync_device':
                header('Content-Type: application/json');
                $onuId = (int)($_POST['onu_id'] ?? 0);
                if (!$onuId) { echo json_encode(['success' => false, 'error' => 'ONU ID required']); exit; }
                
                $onu = $huaweiOLT->getONU($onuId);
                if (!$onu) { echo json_encode(['success' => false, 'error' => 'ONU not found']); exit; }
                
                require_once __DIR__ . '/../src/GenieACS.php';
                $genieacs = new \App\GenieACS($db);
                $serialForLookup = $onu['tr069_serial'] ?? $onu['sn'] ?? '';
                
                $deviceResult = $genieacs->getDeviceBySerial($serialForLookup);
                if (!$deviceResult['success'] || empty($deviceResult['device'])) {
                    echo json_encode(['success' => false, 'error' => 'Device not found in GenieACS']);
                    exit;
                }
                
                $deviceId = $deviceResult['device']['_id'];
                
                $refreshTask = [
                    'name' => 'refreshObject',
                    'objectName' => 'InternetGatewayDevice'
                ];
                
                $genieacsUrl = '';
                $stmt = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'genieacs_url'");
                if ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $genieacsUrl = rtrim($row['setting_value'], '/');
                    $genieacsUrl = preg_replace('/:\d+$/', ':7557', parse_url($genieacsUrl, PHP_URL_SCHEME) . '://' . parse_url($genieacsUrl, PHP_URL_HOST));
                }
                if (!$genieacsUrl) { $genieacsUrl = 'http://localhost:7557'; }
                
                $ch = curl_init("{$genieacsUrl}/devices/" . urlencode($deviceId) . "/tasks?connection_request");
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($refreshTask));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                sleep(2);
                
                echo json_encode(['success' => $httpCode >= 200 && $httpCode < 300, 'message' => 'Sync triggered']);
                exit;
                
            case 'get_tr069_full_config':
                // Enhanced TR-069 full config with all data
                header('Content-Type: application/json');
                $onuId = (int)($_POST['onu_id'] ?? 0);
                if (!$onuId) { echo json_encode(['success' => false, 'error' => 'ONU ID required']); exit; }
                
                $onu = $huaweiOLT->getONU($onuId);
                if (!$onu) { echo json_encode(['success' => false, 'error' => 'ONU not found']); exit; }
                
                $result = ['success' => true, 'found_in_acs' => false, 'serial' => $onu['sn'] ?? ''];
                $result['db_wan_mode'] = $onu['wan_mode'] ?? '';
                $result['db_pppoe_user'] = $onu['pppoe_username'] ?? '';
                $result['db_vlan'] = $onu['vlan_id'] ?? '';
                
                require_once __DIR__ . '/../src/GenieACS.php';
                $genieacs = new \App\GenieACS($db);
                $serialForLookup = $onu['tr069_serial'] ?? $onu['sn'] ?? '';
                $deviceResult = $genieacs->getDeviceBySerial($serialForLookup);
                
                if ($deviceResult['success'] && !empty($deviceResult['device'])) {
                    $device = $deviceResult['device'];
                    $deviceId = $device['_id'];
                    $result['found_in_acs'] = true;
                    
                    // Basic info
                    $result['model'] = $device['_deviceId']['_ProductClass'] ?? '';
                    $result['manufacturer'] = $device['_deviceId']['_Manufacturer'] ?? '';
                    $result['serial'] = $device['_deviceId']['_SerialNumber'] ?? $serialForLookup;
                    $lastInform = $device['_lastInform'] ?? null;
                    $result['last_inform'] = $lastInform ? date('Y-m-d H:i:s', strtotime($lastInform)) : null;
                    $result['status'] = ($lastInform && strtotime($lastInform) >= time() - 300) ? 'online' : 'offline';
                    
                    // Device details from TR-069 data
                    $swVer = $device['InternetGatewayDevice']['DeviceInfo']['SoftwareVersion']['_value'] ?? null;
                    $hwVer = $device['InternetGatewayDevice']['DeviceInfo']['HardwareVersion']['_value'] ?? null;
                    $uptime = $device['InternetGatewayDevice']['DeviceInfo']['UpTime']['_value'] ?? null;
                    $result['sw_version'] = $swVer;
                    $result['hw_version'] = $hwVer;
                    $result['uptime'] = $uptime ? gmdate('d\d H:i:s', (int)$uptime) : null;
                    
                    // CPU/RAM
                    $cpuUsage = $device['InternetGatewayDevice']['DeviceInfo']['ProcessStatus']['CPUUsage']['_value'] ?? null;
                    $memFree = $device['InternetGatewayDevice']['DeviceInfo']['MemoryStatus']['Free']['_value'] ?? null;
                    $memTotal = $device['InternetGatewayDevice']['DeviceInfo']['MemoryStatus']['Total']['_value'] ?? null;
                    $result['cpu_usage'] = $cpuUsage !== null ? $cpuUsage . '%' : null;
                    $result['ram_usage'] = ($memFree !== null && $memTotal !== null) ? round((1 - $memFree/$memTotal) * 100) . '%' : null;
                    
                    // Admin credentials
                    $result['admin_user'] = $device['InternetGatewayDevice']['X_HW_WebUserInfo']['AdminUserName']['_value'] ?? 'admin';
                    $result['admin_password'] = $device['InternetGatewayDevice']['X_HW_WebUserInfo']['AdminPassword']['_value'] ?? '';
                    
                    // Management IP
                    $result['ip'] = $device['InternetGatewayDevice']['WANDevice']['1']['WANConnectionDevice']['1']['WANIPConnection']['1']['ExternalIPAddress']['_value'] ?? null;
                    $result['inform_interval'] = $device['InternetGatewayDevice']['ManagementServer']['PeriodicInformInterval']['_value'] ?? 300;
                    
                    // WiFi settings
                    $wifiResult = $genieacs->getWiFiSettings($deviceId);
                    if ($wifiResult['success']) {
                        $wifiData = $wifiResult['data'] ?? [];
                        $grouped = [];
                        foreach ($wifiData as $item) {
                            if (preg_match('/WLANConfiguration\.(\d+)\.(.+)$/', $item[0], $m)) {
                                $grouped[$m[1]][$m[2]] = $item[1];
                            }
                        }
                        $wifiInterfaces = [];
                        foreach ($grouped as $idx => $data) {
                            $channel = (int)($data['Channel'] ?? 0);
                            $standard = $data['Standard'] ?? '';
                            $band = ($channel > 14 || stripos($standard, '5') !== false || stripos($standard, 'ac') !== false) ? '5GHz' : '2.4GHz';
                            $wifiInterfaces[] = [
                                'index' => (int)$idx,
                                'band' => $band,
                                'ssid' => $data['SSID'] ?? '',
                                'password' => $data['PreSharedKey.1.PreSharedKey'] ?? $data['KeyPassphrase'] ?? '',
                                'channel' => $channel ?: 'Auto',
                                'enabled' => ($data['Enable'] ?? false) == true,
                                'security' => $data['BeaconType'] ?? 'WPA2-PSK'
                            ];
                        }
                        $result['wifi'] = $wifiInterfaces;
                    }
                    
                    // WAN settings
                    $wanResult = $genieacs->getWANStatus($deviceId);
                    if ($wanResult['success']) { $result['wan'] = $wanResult['connections'] ?? []; }
                    
                    // LAN settings
                    $lanIp = $device['InternetGatewayDevice']['LANDevice']['1']['LANHostConfigManagement']['IPInterface']['1']['IPInterfaceIPAddress']['_value'] ?? 
                             $device['InternetGatewayDevice']['LANDevice']['1']['LANHostConfigManagement']['DHCPServerConfigurable']['_value'] ?? null;
                    $dhcpEnabled = $device['InternetGatewayDevice']['LANDevice']['1']['LANHostConfigManagement']['DHCPServerEnable']['_value'] ?? false;
                    $result['lan'] = [
                        'ip' => $lanIp ?: '192.168.1.1',
                        'subnet' => '255.255.255.0',
                        'dhcp_enabled' => $dhcpEnabled,
                        'dhcp_start' => $device['InternetGatewayDevice']['LANDevice']['1']['LANHostConfigManagement']['MinAddress']['_value'] ?? '192.168.1.100',
                        'dhcp_end' => $device['InternetGatewayDevice']['LANDevice']['1']['LANHostConfigManagement']['MaxAddress']['_value'] ?? '192.168.1.200',
                        'dns1' => '8.8.8.8', 'dns2' => '8.8.4.4'
                    ];
                    
                    // Ethernet ports
                    $ethPorts = [];
                    for ($i = 1; $i <= 4; $i++) {
                        $enabled = $device['InternetGatewayDevice']['LANDevice']['1']['LANEthernetInterfaceConfig'][$i]['Enable']['_value'] ?? true;
                        $status = $device['InternetGatewayDevice']['LANDevice']['1']['LANEthernetInterfaceConfig'][$i]['Status']['_value'] ?? 'Up';
                        $ethPorts[] = ['name' => 'LAN' . $i, 'enabled' => $enabled, 'status' => $status];
                    }
                    $result['eth_ports'] = $ethPorts;
                    
                    // Connected clients (hosts)
                    $clients = [];
                    $hostsNum = $device['InternetGatewayDevice']['LANDevice']['1']['Hosts']['HostNumberOfEntries']['_value'] ?? 0;
                    for ($i = 1; $i <= min($hostsNum, 32); $i++) {
                        $host = $device['InternetGatewayDevice']['LANDevice']['1']['Hosts']['Host'][$i] ?? null;
                        if ($host) {
                            $clients[] = [
                                'hostname' => $host['HostName']['_value'] ?? 'Unknown',
                                'ip' => $host['IPAddress']['_value'] ?? '',
                                'mac' => $host['MACAddress']['_value'] ?? '',
                                'connection' => ($host['InterfaceType']['_value'] ?? '') === '802.11' ? 'WiFi' : 'LAN'
                            ];
                        }
                    }
                    $result['clients'] = $clients;
                    
                    // Optical power (from OLT if available)
                    if (!empty($onu['rx_power']) || !empty($onu['tx_power'])) {
                        $result['optical_power'] = [
                            'rx' => $onu['rx_power'] ?? null,
                            'tx' => $onu['tx_power'] ?? null
                        ];
                    }
                }
                
                echo json_encode($result);
                exit;
            case 'save_tr069_eth_ports':
                header('Content-Type: application/json');
                try {
                    $onuId = (int)($_POST['onu_id'] ?? 0);
                    $ports = json_decode($_POST['ports'] ?? '[]', true);
                    if (!$onuId) { echo json_encode(['success' => false, 'error' => 'ONU ID required']); exit; }
                    
                    $result = $huaweiOLT->configureEthPortsViaTR069($onuId, $ports);
                    echo json_encode($result);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
                exit;
            case 'save_tr069_lan':
                header('Content-Type: application/json');
                try {
                    $onuId = (int)($_POST['onu_id'] ?? 0);
                    if (!$onuId) { echo json_encode(['success' => false, 'error' => 'ONU ID required']); exit; }
                    
                    $result = $huaweiOLT->configureLANViaTR069($onuId, [
                        'dhcp_enabled' => ($_POST['dhcp_enabled'] ?? '0') === '1',
                        'ip_start' => $_POST['dhcp_start'] ?? '192.168.1.100',
                        'ip_end' => $_POST['dhcp_end'] ?? '192.168.1.200'
                    ]);
                    echo json_encode($result);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
                exit;
            case 'add_port_forward':
                header('Content-Type: application/json');
                try {
                    $onuId = (int)($_POST['onu_id'] ?? 0);
                    if (!$onuId) { echo json_encode(['success' => false, 'error' => 'ONU ID required']); exit; }
                    
                    $result = $huaweiOLT->addPortForwardViaTR069($onuId, [
                        'external_port' => $_POST['ext_port'] ?? '',
                        'internal_ip' => $_POST['int_ip'] ?? '',
                        'internal_port' => $_POST['int_port'] ?? '',
                        'protocol' => $_POST['protocol'] ?? 'TCP',
                        'description' => $_POST['description'] ?? 'Port Forward'
                    ]);
                    echo json_encode($result);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
                exit;
            case 'delete_port_forward':
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Port forward deleted']);
                exit;
            case 'change_admin_password':
                header('Content-Type: application/json');
                try {
                    $onuId = (int)($_POST['onu_id'] ?? 0);
                    $newPassword = $_POST['password'] ?? '';
                    if (!$onuId || !$newPassword) { echo json_encode(['success' => false, 'error' => 'ONU ID and password required']); exit; }
                    
                    $result = $huaweiOLT->changeAdminPasswordViaTR069($onuId, $newPassword);
                    echo json_encode($result);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
                exit;
            case 'factory_reset_onu':
                header('Content-Type: application/json');
                try {
                    $onuId = (int)($_POST['onu_id'] ?? 0);
                    if (!$onuId) { echo json_encode(['success' => false, 'error' => 'ONU ID required']); exit; }
                    
                    $result = $huaweiOLT->factoryResetViaTR069($onuId);
                    echo json_encode($result);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
                exit;
            case 'restore_onu_config':
                header('Content-Type: application/json');
                $onuId = (int)($_POST['onu_id'] ?? 0);
                $config = json_decode($_POST['config'] ?? '{}', true);
                if (!$onuId || empty($config)) { echo json_encode(['success' => false, 'error' => 'ONU ID and config required']); exit; }
                
                $onu = $huaweiOLT->getONU($onuId);
                if (!$onu) { echo json_encode(['success' => false, 'error' => 'ONU not found']); exit; }
                
                // Update DB with restored config
                $updateData = [];
                if (!empty($config['db_wan_mode'])) $updateData['wan_mode'] = $config['db_wan_mode'];
                if (!empty($config['db_pppoe_user'])) $updateData['pppoe_username'] = $config['db_pppoe_user'];
                if (!empty($config['db_vlan'])) $updateData['vlan_id'] = $config['db_vlan'];
                if (!empty($updateData)) $huaweiOLT->updateONU($onuId, $updateData);
                
                // Push WiFi and WAN config via TR-069
                require_once __DIR__ . '/../src/GenieACS.php';
                $genieacs = new \App\GenieACS($db);
                $deviceResult = $genieacs->getDeviceBySerial($onu['tr069_serial'] ?? $onu['sn']);
                
                if ($deviceResult['success'] && !empty($config['wifi'])) {
                    $deviceId = $deviceResult['device']['_id'];
                    foreach ($config['wifi'] as $wifi) {
                        if (isset($wifi['index'])) {
                            $params = [
                                "InternetGatewayDevice.LANDevice.1.WLANConfiguration.{$wifi['index']}.Enable" => $wifi['enabled'] ?? true,
                                "InternetGatewayDevice.LANDevice.1.WLANConfiguration.{$wifi['index']}.SSID" => $wifi['ssid'] ?? ''
                            ];
                            if (!empty($wifi['password'])) {
                                $params["InternetGatewayDevice.LANDevice.1.WLANConfiguration.{$wifi['index']}.PreSharedKey.1.PreSharedKey"] = $wifi['password'];
                            }
                            $genieacs->setParameterValues($deviceId, $params);
                        }
                    }
                }
                echo json_encode(['success' => true, 'message' => 'Configuration restored']);
                exit;
            case 'get_tr069_clients':
                header('Content-Type: application/json');
                $onuId = (int)($_POST['onu_id'] ?? 0);
                if (!$onuId) { echo json_encode(['success' => false, 'error' => 'ONU ID required']); exit; }
                
                $onu = $huaweiOLT->getONU($onuId);
                if (!$onu) { echo json_encode(['success' => false, 'error' => 'ONU not found']); exit; }
                
                require_once __DIR__ . '/../src/GenieACS.php';
                $genieacs = new \App\GenieACS($db);
                $deviceResult = $genieacs->getDeviceBySerial($onu['tr069_serial'] ?? $onu['sn']);
                
                $clients = [];
                if ($deviceResult['success']) {
                    $device = $deviceResult['device'];
                    $hostsNum = $device['InternetGatewayDevice']['LANDevice']['1']['Hosts']['HostNumberOfEntries']['_value'] ?? 0;
                    for ($i = 1; $i <= min($hostsNum, 32); $i++) {
                        $host = $device['InternetGatewayDevice']['LANDevice']['1']['Hosts']['Host'][$i] ?? null;
                        if ($host) {
                            $clients[] = [
                                'hostname' => $host['HostName']['_value'] ?? 'Unknown',
                                'ip' => $host['IPAddress']['_value'] ?? '',
                                'mac' => $host['MACAddress']['_value'] ?? '',
                                'connection' => ($host['InterfaceType']['_value'] ?? '') === '802.11' ? 'WiFi' : 'LAN'
                            ];
                        }
                    }
                }
                echo json_encode(['success' => true, 'clients' => $clients]);
                exit;
            case 'get_tr069_wan_status':
                // Get WAN status via TR-069 (SmartOLT-style)
                header('Content-Type: application/json');
                $serial = $_POST['serial'] ?? '';
                if (empty($serial)) {
                    echo json_encode(['success' => false, 'error' => 'Serial number required']);
                    exit;
                }
                require_once __DIR__ . '/../src/GenieACS.php';
                $genieacs = new \App\GenieACS($db);
                $deviceResult = $genieacs->getDeviceBySerial($serial);
                if (!$deviceResult['success']) {
                    echo json_encode(['success' => false, 'error' => 'Device not found in GenieACS']);
                    exit;
                }
                $deviceId = $deviceResult['device']['_id'];
                $result = $genieacs->getWANStatus($deviceId);
                echo json_encode($result);
                exit;
            case 'get_tr069_wifi':
                header('Content-Type: application/json');
                $serial = $_POST['serial'] ?? '';
                if (empty($serial)) {
                    echo json_encode(['success' => false, 'error' => 'Serial number required']);
                    exit;
                }
                try {
                    require_once __DIR__ . '/../src/GenieACS.php';
                    $genieacs = new \App\GenieACS($db);
                    $deviceResult = $genieacs->getDeviceBySerial($serial);
                    if (!$deviceResult['success']) {
                        echo json_encode(['success' => false, 'error' => 'Device not found in ACS. Serial: ' . $serial]);
                        exit;
                    }
                    $deviceId = $deviceResult['device']['_id'];
                    $device = $deviceResult['device'];
                    
                    // Get model info
                    $model = $device['_deviceId']['_ProductClass'] ?? '';
                    
                    // Detect if dual-band based on model
                    $isDualBand = preg_match('/HG8546|EG8145|HG8245|HS8145/i', $model);
                    
                    $wifiResult = $genieacs->getWiFiSettings($deviceId);
                    if (!$wifiResult['success']) {
                        echo json_encode(['success' => false, 'error' => $wifiResult['error'] ?? 'Failed to get WiFi settings', 'model' => $model]);
                        exit;
                    }
                    $wifiInterfaces = [];
                    $wifiData = $wifiResult['data'] ?? [];
                    $grouped = [];
                    
                    // Parse all WiFi data
                    foreach ($wifiData as $item) {
                        if (preg_match('/WLANConfiguration\.(\d+)\.(.+)$/', $item[0], $m)) {
                            $idx = $m[1];
                            $key = $m[2];
                            $grouped[$idx][$key] = $item[1];
                        }
                    }
                    
                    // Detect band based on channel or standard
                    foreach ($grouped as $idx => $data) {
                        $channel = (int)($data['Channel'] ?? 0);
                        $standard = $data['Standard'] ?? '';
                        
                        // Determine band from channel or standard
                        $band = '2.4GHz';
                        if ($channel > 14 || stripos($standard, '5') !== false || stripos($standard, 'ac') !== false || stripos($standard, 'ax') !== false) {
                            $band = '5GHz';
                        }
                        
                        // Add index suffix for guest networks (typically idx > 2)
                        if ($idx == 1) $bandLabel = '2.4GHz';
                        elseif ($idx == 2) $bandLabel = $isDualBand ? '5GHz' : '2.4GHz #2';
                        elseif ($idx == 3) $bandLabel = '2.4GHz Guest';
                        elseif ($idx == 4) $bandLabel = '5GHz Guest';
                        elseif ($idx == 5) $bandLabel = '5GHz #2';
                        else $bandLabel = "WiFi {$idx}";
                        
                        $wifiInterfaces[] = [
                            'index' => $idx,
                            'band' => $bandLabel,
                            'ssid' => $data['SSID'] ?? '',
                            'password' => $data['PreSharedKey.1.KeyPassphrase'] ?? '',
                            'enabled' => ($data['Enable'] ?? false) === true || ($data['Enable'] ?? '') === 'true' || ($data['Enable'] ?? '') === '1' || $data['Enable'] === 1,
                            'channel' => $channel,
                            'standard' => $standard,
                            'vlan_id' => $data['X_HW_VLANID'] ?? $data['VLANID'] ?? '',
                            'vlan_mode' => $data['X_HW_VLANMode'] ?? '',
                            'broadcast' => ($data['SSIDAdvertisementEnabled'] ?? true) === true || ($data['SSIDAdvertisementEnabled'] ?? '') === 'true',
                            'security' => $data['BeaconType'] ?? '',
                            'path' => "InternetGatewayDevice.LANDevice.1.WLANConfiguration.{$idx}"
                        ];
                    }
                    
                    echo json_encode([
                        'success' => true, 
                        'interfaces' => $wifiInterfaces,
                        'model' => $model,
                        'dual_band' => $isDualBand,
                        'device_id' => $deviceId
                    ]);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
                exit;
            case 'set_tr069_param':
                header('Content-Type: application/json');
                $serial = $_POST['serial'] ?? '';
                $path = $_POST['path'] ?? '';
                $value = $_POST['value'] ?? '';
                $type = $_POST['type'] ?? 'xsd:string';
                
                if (empty($serial) || empty($path)) {
                    echo json_encode(['success' => false, 'error' => 'Serial and path required']);
                    exit;
                }
                
                try {
                    require_once __DIR__ . '/../src/GenieACS.php';
                    $genieacs = new \App\GenieACS($db);
                    $deviceResult = $genieacs->getDeviceBySerial($serial);
                    if (!$deviceResult['success']) {
                        echo json_encode(['success' => false, 'error' => 'Device not found']);
                        exit;
                    }
                    $deviceId = $deviceResult['device']['_id'];
                    
                    // Convert value to correct type
                    if ($type === 'xsd:boolean') {
                        $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                    } elseif ($type === 'xsd:unsignedInt' || $type === 'xsd:int') {
                        $value = (int)$value;
                    }
                    
                    $result = $genieacs->setParameterValues($deviceId, [
                        [$path, $value, $type]
                    ]);
                    
                    echo json_encode($result);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
                exit;
                
            case 'summon_onu':
                header('Content-Type: application/json');
                $serial = $_POST['serial'] ?? '';
                if (empty($serial)) {
                    echo json_encode(['success' => false, 'error' => 'Serial required']);
                    exit;
                }
                try {
                    require_once __DIR__ . '/../src/GenieACS.php';
                    $genieacs = new \App\GenieACS($db);
                    $result = $genieacs->sendConnectionRequest($serial);
                    echo json_encode($result);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
                exit;
                
            case 'refresh_tr069_ip':
                header('Content-Type: application/json');
                $onuId = (int)$_POST['onu_id'];
                
                // Get ONU data
                $onuStmt = $db->prepare("SELECT sn, genieacs_id, tr069_status FROM huawei_onus WHERE id = ?");
                $onuStmt->execute([$onuId]);
                $onuData = $onuStmt->fetch(PDO::FETCH_ASSOC);
                
                $tr069Status = 'offline';
                $ip = '-';
                $found = false;
                
                if ($onuData) {
                    // Try GenieACS first
                    try {
                        $genieAcs = new \App\GenieACS($db);
                        $device = null;
                        $searchFormats = [
                            $onuData['sn'],
                            strtoupper($onuData['sn']),
                            preg_replace('/^[A-Z]{4}/', '', strtoupper($onuData['sn']))
                        ];
                        
                        foreach ($searchFormats as $sn) {
                            $device = $genieAcs->getDevice($sn);
                            if ($device) break;
                        }
                        
                        if ($device) {
                            $found = true;
                            $deviceId = $device['_id'] ?? null;
                            
                            // Check last inform time
                            $lastInformStr = $device['_lastInform'] ?? null;
                            if ($lastInformStr) {
                                $lastInform = strtotime($lastInformStr);
                                $diff = time() - $lastInform;
                                $tr069Status = ($diff < 300) ? 'online' : 'offline';
                            }
                            
                            // Try to get IP from GenieACS
                            $wanPath = 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.ExternalIPAddress';
                            if (isset($device[$wanPath]['_value'])) {
                                $ip = $device[$wanPath]['_value'];
                            }
                            
                            // Update database with GenieACS info
                            $updateStmt = $db->prepare("UPDATE huawei_onus SET genieacs_id = ?, tr069_status = ?, updated_at = NOW() WHERE id = ?");
                            $updateStmt->execute([$deviceId, $tr069Status, $onuId]);
                        }
                    } catch (Exception $e) {
                        // GenieACS check failed, continue to OLT method
                    }
                    
                    // Fall back to OLT CLI if no IP from GenieACS
                    if ($ip === '-') {
                        $result = $huaweiOLT->refreshONUTR069IP($onuId);
                        if ($result['success'] && !empty($result['tr069_ip'])) {
                            $ip = $result['tr069_ip'];
                        }
                    }
                }
                
                echo json_encode([
                    'success' => $found || $ip !== '-',
                    'ip' => $ip,
                    'tr069_status' => $tr069Status,
                    'message' => $found ? "Device found in GenieACS (Status: {$tr069Status})" : ($ip !== '-' ? "IP from OLT CLI" : 'Device not found')
                ]);
                exit;
            case 'refresh_ont_ip':
                header('Content-Type: application/json');
                $result = $huaweiOLT->refreshONUOntIP((int)$_POST['onu_id']);
                echo json_encode($result);
                exit;
            case 'update_dba_profile':
                $onuId = (int)$_POST['onu_id'];
                $dbaProfileId = (int)$_POST['dba_profile_id'];
                $stmt = $db->prepare("UPDATE huawei_onus SET dba_profile_id = ? WHERE id = ?");
                if ($stmt->execute([$dbaProfileId, $onuId])) {
                    $message = 'DBA/Speed profile updated successfully';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to update DBA profile';
                    $messageType = 'danger';
                }
                break;
            case 'refresh_all_optical':
            case 'refresh_all_optical_cli':
                $result = $huaweiOLT->refreshAllONUOpticalViaCLI((int)$_POST['olt_id']);
                if ($result['success']) {
                    $message = "CLI Sync: Refreshed optical data for {$result['refreshed']}/{$result['total']} ONUs";
                    $messageType = 'success';
                } else {
                    $message = $result['error'] ?? 'Failed to refresh optical data via CLI';
                    $messageType = 'danger';
                }
                break;
            case 'refresh_all_optical_snmp':
                $result = $huaweiOLT->refreshAllONUOpticalViaSNMP((int)$_POST['olt_id']);
                if ($result['success']) {
                    $message = "SNMP Sync: Updated {$result['updated']}/{$result['total']} ONUs (RX/TX power + distance)";
                    $messageType = 'success';
                } else {
                    $message = $result['error'] ?? 'Failed to refresh optical data via SNMP';
                    $messageType = 'danger';
                }
                break;
            case 'sync_boards':
                $result = $huaweiOLT->syncBoardsFromOLT((int)$_POST['olt_id']);
                $count = $result['synced'] ?? $result['count'] ?? 0;
                $message = $result['success'] ? "Synced {$count} boards from OLT" : ($result['message'] ?? 'Sync failed');
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
            case 'sync_vlans':
                $result = $huaweiOLT->syncVLANsFromOLT((int)$_POST['olt_id']);
                $count = $result['synced'] ?? $result['count'] ?? 0;
                $message = $result['success'] ? "Synced {$count} VLANs from OLT" : ($result['message'] ?? 'Sync failed');
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
            case 'sync_ports':
                $result = $huaweiOLT->syncPONPortsFromOLT((int)$_POST['olt_id']);
                $count = $result['synced'] ?? $result['count'] ?? 0;
                $message = $result['success'] ? "Synced {$count} PON ports from OLT" : ($result['message'] ?? 'Sync failed');
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
            case 'sync_uplinks':
                $result = $huaweiOLT->syncUplinksFromOLT((int)$_POST['olt_id']);
                $count = $result['synced'] ?? $result['count'] ?? 0;
                $message = $result['success'] ? "Synced {$count} uplink ports from OLT" : ($result['message'] ?? 'Sync failed');
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
            case 'sync_all_olt':
                $result = $huaweiOLT->syncAllFromOLT((int)$_POST['olt_id']);
                $message = $result['success'] ? "Full sync complete: {$result['message']}" : "Sync partially failed: {$result['message']}";
                $messageType = $result['success'] ? 'success' : 'warning';
                break;
            case 'toggle_port':
                $enable = (bool)$_POST['enable'];
                $result = $huaweiOLT->enablePort((int)$_POST['olt_id'], $_POST['port_name'], $enable);
                $action = $enable ? 'enabled' : 'disabled';
                $message = $result['success'] ? "Port {$_POST['port_name']} has been {$action}" : ($result['message'] ?? 'Failed to toggle port');
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
            case 'assign_port_vlan':
                $result = $huaweiOLT->assignPortVLAN((int)$_POST['olt_id'], $_POST['port_name'], (int)$_POST['vlan_id'], $_POST['vlan_mode'] ?? 'tag');
                $message = $result['success'] ? "VLAN {$_POST['vlan_id']} assigned to port {$_POST['port_name']}" : ($result['message'] ?? 'Failed to assign VLAN');
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
            case 'bulk_port_vlan':
                $ports = $huaweiOLT->getCachedPONPorts((int)$_POST['olt_id']);
                $success = 0;
                foreach ($ports as $port) {
                    $r = $huaweiOLT->assignPortVLAN((int)$_POST['olt_id'], $port['port_name'], (int)$_POST['vlan_id'], 'tag');
                    if ($r['success']) $success++;
                }
                $message = "VLAN {$_POST['vlan_id']} assigned to {$success}/" . count($ports) . " ports";
                $messageType = $success > 0 ? 'success' : 'danger';
                break;
            case 'configure_uplink':
                $config = [
                    'vlan_mode' => $_POST['vlan_mode'] ?? null,
                    'pvid' => !empty($_POST['pvid']) ? (int)$_POST['pvid'] : null,
                    'allowed_vlans' => $_POST['allowed_vlans'] ?? null,
                    'description' => $_POST['description'] ?? null
                ];
                $result = $huaweiOLT->configureUplink((int)$_POST['olt_id'], $_POST['port_name'], $config);
                $message = $result['success'] ? "Uplink {$_POST['port_name']} configured successfully" : ($result['message'] ?? 'Failed to configure uplink');
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
            case 'create_template':
                $templateData = [
                    'name' => $_POST['name'],
                    'description' => $_POST['description'] ?? '',
                    'downstream_bandwidth' => (int)$_POST['downstream_bandwidth'],
                    'upstream_bandwidth' => (int)$_POST['upstream_bandwidth'],
                    'bandwidth_unit' => $_POST['bandwidth_unit'] ?? 'mbps',
                    'vlan_id' => !empty($_POST['vlan_id']) ? (int)$_POST['vlan_id'] : null,
                    'vlan_mode' => $_POST['vlan_mode'] ?? 'tag',
                    'qos_profile' => $_POST['qos_profile'] ?? '',
                    'iptv_enabled' => isset($_POST['iptv_enabled']),
                    'voip_enabled' => isset($_POST['voip_enabled']),
                    'tr069_enabled' => isset($_POST['tr069_enabled']),
                    'is_default' => isset($_POST['is_default'])
                ];
                $result = $huaweiOLT->createServiceTemplate($templateData);
                $message = $result['success'] ? "Service template '{$_POST['name']}' created successfully" : ($result['message'] ?? 'Failed to create template');
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
            case 'update_template':
                $templateData = [
                    'name' => $_POST['name'],
                    'description' => $_POST['description'] ?? '',
                    'downstream_bandwidth' => (int)$_POST['downstream_bandwidth'],
                    'upstream_bandwidth' => (int)$_POST['upstream_bandwidth'],
                    'bandwidth_unit' => $_POST['bandwidth_unit'] ?? 'mbps',
                    'vlan_id' => !empty($_POST['vlan_id']) ? (int)$_POST['vlan_id'] : null,
                    'vlan_mode' => $_POST['vlan_mode'] ?? 'tag',
                    'qos_profile' => $_POST['qos_profile'] ?? '',
                    'iptv_enabled' => isset($_POST['iptv_enabled']),
                    'voip_enabled' => isset($_POST['voip_enabled']),
                    'tr069_enabled' => isset($_POST['tr069_enabled']),
                    'is_default' => isset($_POST['is_default'])
                ];
                $result = $huaweiOLT->updateServiceTemplate((int)$_POST['template_id'], $templateData);
                $message = $result['success'] ? "Service template updated successfully" : ($result['message'] ?? 'Failed to update template');
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
            case 'delete_template':
                $result = $huaweiOLT->deleteServiceTemplate((int)$_POST['template_id']);
                $message = $result['success'] ? "Service template deleted" : ($result['message'] ?? 'Failed to delete template');
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
            case 'reset_onu_config':
                $result = $huaweiOLT->resetONUConfig((int)$_POST['onu_id']);
                $message = $result['success'] ? "ONU configuration reset successfully" : ($result['message'] ?? 'Reset failed');
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
            case 'update_onu_description':
                $result = $huaweiOLT->updateONUDescription((int)$_POST['onu_id'], $_POST['description'] ?? '');
                $message = $result['success'] ? "ONU description updated" : ($result['message'] ?? 'Update failed');
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
            case 'update_onu_info':
                $onuId = (int)$_POST['onu_id'];
                $updateData = [];
                if (isset($_POST['name'])) $updateData['name'] = $_POST['name'];
                if (isset($_POST['description'])) $updateData['description'] = $_POST['description'];
                if (isset($_POST['zone_id'])) $updateData['zone_id'] = $_POST['zone_id'] ?: null;
                
                $result = $huaweiOLT->updateONU($onuId, $updateData);
                $message = $result['success'] ? "ONU info updated" : ($result['message'] ?? 'Update failed');
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
            case 'delete_service_port':
                $result = $huaweiOLT->deleteServicePort((int)$_POST['olt_id'], (int)$_POST['service_port_index']);
                $message = $result['success'] ? "Service port deleted" : ($result['message'] ?? 'Delete failed');
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
            case 'change_onu_profile':
                $result = $huaweiOLT->changeONUServiceProfile((int)$_POST['onu_id'], (int)$_POST['new_profile_id']);
                $message = $result['success'] ? "ONU service profile changed successfully" : ($result['message'] ?? 'Profile change failed');
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
            case 'check_signal_health':
                $oltIdCheck = !empty($_POST['olt_id']) ? (int)$_POST['olt_id'] : null;
                $result = $huaweiOLT->checkONUSignalHealth($oltIdCheck);
                if ($result['success']) {
                    $s = $result['summary'];
                    $message = "Checked {$s['total_checked']} ONUs. Critical: {$s['critical_signal']}, Warning: {$s['warning_signal']}, LOS: {$s['los']}, Offline: {$s['offline']}";
                    $messageType = ($s['critical_signal'] > 0 || $s['los'] > 0) ? 'warning' : 'success';
                } else {
                    $message = 'Signal health check failed';
                    $messageType = 'danger';
                }
                break;
            // Location Management
            case 'add_zone':
                $result = $huaweiOLT->createZone($_POST['name'], $_POST['description'] ?? null, isset($_POST['is_active']));
                $message = $result['success'] ? 'Zone created successfully' : ($result['message'] ?? 'Failed to create zone');
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
            case 'update_zone':
                $result = $huaweiOLT->updateZone((int)$_POST['id'], $_POST['name'], $_POST['description'] ?? null, isset($_POST['is_active']));
                $message = $result['success'] ? 'Zone updated successfully' : ($result['message'] ?? 'Failed to update zone');
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
            case 'delete_zone':
                $result = $huaweiOLT->deleteZone((int)$_POST['id']);
                $message = $result['success'] ? 'Zone deleted successfully' : ($result['message'] ?? 'Failed to delete zone');
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
            case 'add_subzone':
                $result = $huaweiOLT->createSubzone((int)$_POST['zone_id'], $_POST['name'], $_POST['description'] ?? null, isset($_POST['is_active']));
                $message = $result['success'] ? 'Subzone created successfully' : ($result['message'] ?? 'Failed to create subzone');
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
            case 'update_subzone':
                $result = $huaweiOLT->updateSubzone((int)$_POST['id'], (int)$_POST['zone_id'], $_POST['name'], $_POST['description'] ?? null, isset($_POST['is_active']));
                $message = $result['success'] ? 'Subzone updated successfully' : ($result['message'] ?? 'Failed to update subzone');
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
            case 'delete_subzone':
                $result = $huaweiOLT->deleteSubzone((int)$_POST['id']);
                $message = $result['success'] ? 'Subzone deleted successfully' : ($result['message'] ?? 'Failed to delete subzone');
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
            case 'add_apartment':
                $data = [
                    'zone_id' => (int)$_POST['zone_id'],
                    'subzone_id' => !empty($_POST['subzone_id']) ? (int)$_POST['subzone_id'] : null,
                    'name' => $_POST['name'],
                    'address' => $_POST['address'] ?? null,
                    'floors' => !empty($_POST['floors']) ? (int)$_POST['floors'] : null,
                    'units_per_floor' => !empty($_POST['units_per_floor']) ? (int)$_POST['units_per_floor'] : null
                ];
                $result = $huaweiOLT->createApartment($data);
                $message = $result['success'] ? 'Apartment created successfully' : ($result['message'] ?? 'Failed to create apartment');
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
            case 'update_apartment':
                $data = [
                    'zone_id' => (int)$_POST['zone_id'],
                    'subzone_id' => !empty($_POST['subzone_id']) ? (int)$_POST['subzone_id'] : null,
                    'name' => $_POST['name'],
                    'address' => $_POST['address'] ?? null,
                    'floors' => !empty($_POST['floors']) ? (int)$_POST['floors'] : null,
                    'units_per_floor' => !empty($_POST['units_per_floor']) ? (int)$_POST['units_per_floor'] : null
                ];
                $result = $huaweiOLT->updateApartment((int)$_POST['id'], $data);
                $message = $result['success'] ? 'Apartment updated successfully' : ($result['message'] ?? 'Failed to update apartment');
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
            case 'delete_apartment':
                $result = $huaweiOLT->deleteApartment((int)$_POST['id']);
                $message = $result['success'] ? 'Apartment deleted successfully' : ($result['message'] ?? 'Failed to delete apartment');
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
            case 'add_odb':
                $data = [
                    'zone_id' => (int)$_POST['zone_id'],
                    'apartment_id' => !empty($_POST['apartment_id']) ? (int)$_POST['apartment_id'] : null,
                    'code' => $_POST['code'],
                    'capacity' => (int)$_POST['capacity'],
                    'location_description' => $_POST['location_description'] ?? null,
                    'is_active' => isset($_POST['is_active'])
                ];
                $result = $huaweiOLT->createODB($data);
                $message = $result['success'] ? 'ODB created successfully' : ($result['message'] ?? 'Failed to create ODB');
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
            case 'update_odb':
                $data = [
                    'zone_id' => (int)$_POST['zone_id'],
                    'apartment_id' => !empty($_POST['apartment_id']) ? (int)$_POST['apartment_id'] : null,
                    'code' => $_POST['code'],
                    'capacity' => (int)$_POST['capacity'],
                    'location_description' => $_POST['location_description'] ?? null,
                    'is_active' => isset($_POST['is_active'])
                ];
                $result = $huaweiOLT->updateODB((int)$_POST['id'], $data);
                $message = $result['success'] ? 'ODB updated successfully' : ($result['message'] ?? 'Failed to update ODB');
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
            case 'delete_odb':
                $result = $huaweiOLT->deleteODB((int)$_POST['id']);
                $message = $result['success'] ? 'ODB deleted successfully' : ($result['message'] ?? 'Failed to delete ODB');
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
            case 'get_tr069_profiles':
                $oltId = (int)$_POST['olt_id'];
                $result = $huaweiOLT->getTR069Profiles($oltId);
                header('Content-Type: application/json');
                echo json_encode($result);
                exit;
            case 'get_tr069_profile':
                $oltId = (int)$_POST['olt_id'];
                $profileId = (int)$_POST['profile_id'];
                $result = $huaweiOLT->getTR069Profile($oltId, $profileId);
                header('Content-Type: application/json');
                echo json_encode($result);
                exit;
            case 'clear_tr069_profile_credentials':
                $oltId = (int)$_POST['olt_id'];
                $profileId = (int)$_POST['profile_id'];
                $result = $huaweiOLT->clearTR069ProfileCredentials($oltId, $profileId);
                header('Content-Type: application/json');
                echo json_encode($result);
                exit;
            default:
                break;
        }
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

$stats = $huaweiOLT->getDashboardStats();
$olts = $huaweiOLT->getOLTs(false);
$onus = [];
$profiles = $huaweiOLT->getServiceProfiles(false);
$logs = [];
$alerts = [];

$discoveredOnus = [];
if ($view === 'onus' || $view === 'dashboard') {
    $onuFilters = [];
    if ($oltId) $onuFilters['olt_id'] = $oltId;
    if (!empty($_GET['status'])) $onuFilters['status'] = $_GET['status'];
    if (!empty($_GET['search'])) $onuFilters['search'] = $_GET['search'];
    if (isset($_GET['unconfigured'])) {
        $onuFilters['is_authorized'] = false;
    } else {
        // Default view shows only authorized ONUs
        $onuFilters['is_authorized'] = true;
    }
    $onus = $huaweiOLT->getONUs($onuFilters);
    
    // Always fetch discovered ONUs (auto-populated by OLT Session Manager)
    // These are pending ONUs waiting to be authorized
    $discoveredOnus = $huaweiOLT->getDiscoveredONUs($oltId, true);
}

if ($view === 'logs') {
    $logFilters = [];
    if ($oltId) $logFilters['olt_id'] = $oltId;
    if (!empty($_GET['log_action'])) $logFilters['action'] = $_GET['log_action'];
    $logs = $huaweiOLT->getLogs($logFilters, 200);
}

if ($view === 'alerts' || $view === 'dashboard') {
    $alerts = $huaweiOLT->getAlerts(false, 100);
}

// Load location data only when needed (lazy load for performance)
$zones = [];
$subzones = [];
$apartments = [];
$odbs = [];
if (in_array($view, ["locations", "onus"])) {
$zones = $huaweiOLT->getZones(false);
$subzones = $huaweiOLT->getSubzones();
$apartments = $huaweiOLT->getApartments();
$odbs = $huaweiOLT->getODBs();
}

// Load ONU types for authorization modal
$onuTypes = [];
try {
    $stmt = $db->query("SELECT * FROM huawei_onu_types WHERE is_active = true ORDER BY model");
    $onuTypes = $stmt->fetchAll(\PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$currentOnu = null;
$onuRefreshResult = null;
if ($view === 'onu_detail' && isset($_GET['onu_id'])) {
    $onuId = (int)$_GET['onu_id'];
    $currentOnu = $huaweiOLT->getONU($onuId);
    if (!$currentOnu) {
        header('Location: ?page=huawei-olt&view=onus');
        exit;
    }
    
    // Status detection: Trust SNMP polling status from OLT Session Manager
    // SNMP fault statuses (los, dying-gasp, offline) are authoritative - never override
    $snmpFaultStatuses = ['los', 'dying-gasp', 'offline'];
    if (!in_array($currentOnu['status'], $snmpFaultStatuses) && $currentOnu['status'] !== 'online') {
        // Only use fallbacks for unknown/empty status - not for faults
        $isOnline = false;
        // 1. SNMP status from SmartOLT sync
        if (!empty($currentOnu['snmp_status']) && $currentOnu['snmp_status'] === 'online') {
            $isOnline = true;
        }
        // 2. Valid optical power means ONU is definitely online
        elseif (!empty($currentOnu['rx_power']) && $currentOnu['rx_power'] > -40) {
            $isOnline = true;
        }
        // 3. TR-069 last inform as fallback (device talking to ACS)
        elseif (!empty($currentOnu['tr069_last_inform'])) {
            $lastInformTime = strtotime($currentOnu['tr069_last_inform']);
            if ($lastInformTime >= time() - 300) $isOnline = true;
        }
        if ($isOnline) $currentOnu['status'] = 'online';
    }
    
    // Show cached optical data - refresh is now manual via AJAX button
    // This makes the config page load instantly instead of waiting for OLT query
    $onuRefreshResult = [
        'success' => true,
        'cached' => true,
        'rx_power' => $currentOnu['rx_power'],
        'tx_power' => $currentOnu['tx_power'],
        'distance' => $currentOnu['distance'] ?? null,
        'status' => $currentOnu['status']
    ];
    
    // Fetch TR-069 device info from local database only (no network calls)
    // Live GenieACS data is fetched via AJAX to prevent page hanging
    $tr069Device = null;
    $tr069Info = null;
    $pendingTr069Config = null;
    $genieacsConfigured = false;
    try {
        require_once __DIR__ . '/../src/GenieACS.php';
        $genieacs = new \App\GenieACS($db);
        $genieacsConfigured = $genieacs->isConfigured();
        
        // Check for TR-069 device by serial number in local table first
        $stmt = $db->prepare("SELECT * FROM tr069_devices WHERE serial_number = ?");
        $stmt->execute([$currentOnu['sn']]);
        $tr069Device = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        // Get pending TR-069 config
        $stmt = $db->prepare("SELECT * FROM huawei_onu_tr069_config WHERE onu_id = ?");
        $stmt->execute([$onuId]);
        $pendingTr069Config = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($pendingTr069Config && !empty($pendingTr069Config['config_data'])) {
            $pendingTr069Config['config'] = json_decode($pendingTr069Config['config_data'], true);
        }
        
        // Skip live GenieACS calls on page load - they are slow and can timeout
        // TR-069 info can be refreshed via AJAX when user clicks the refresh button
    } catch (Exception $e) {
        // TR-069 tables may not exist yet
    }
}

$customers = [];
try {
    $stmt = $db->query("SELECT id, name, phone FROM customers ORDER BY name LIMIT 1000");
    $customers = $stmt->fetchAll(\PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OMS - ONU Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --oms-primary: #1a1c2c;
            --oms-primary-light: #2d3250;
            --oms-primary-dark: #0f1019;
            --oms-accent: #6366f1;
            --oms-accent-light: #818cf8;
            --oms-accent-dark: #4f46e5;
            --oms-success: #10b981;
            --oms-success-light: #34d399;
            --oms-warning: #f59e0b;
            --oms-warning-light: #fbbf24;
            --oms-danger: #ef4444;
            --oms-danger-light: #f87171;
            --oms-info: #0ea5e9;
            --oms-info-light: #38bdf8;
            --oms-bg: #f8fafc;
            --oms-card-bg: #ffffff;
            --oms-text: #1e293b;
            --oms-text-muted: #64748b;
            --oms-text-light: #94a3b8;
            --oms-border: #e2e8f0;
            --oms-border-light: #f1f5f9;
            --oms-shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --oms-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
            --oms-shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1);
            --oms-shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            --oms-radius: 0.5rem;
            --oms-radius-lg: 0.75rem;
            --oms-radius-xl: 1rem;
            --sidebar-width: 280px;
        }
        
        * { box-sizing: border-box; }
        
        body { 
            background-color: var(--oms-bg); 
            color: var(--oms-text);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
            font-size: 0.9375rem;
            line-height: 1.6;
            margin: 0;
            padding: 0;
        }
        
        /* Premium Sidebar */
        .sidebar { 
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: linear-gradient(180deg, var(--oms-primary) 0%, var(--oms-primary-light) 50%, var(--oms-primary) 100%);
            overflow-y: auto;
            overflow-x: hidden;
            z-index: 1000;
            display: flex;
            flex-direction: column;
        }
        
        .sidebar::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.02'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            pointer-events: none;
        }
        
        .sidebar-brand {
            padding: 1.5rem 1.25rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            margin-bottom: 0.5rem;
            position: relative;
        }
        
        .sidebar .nav-link { 
            color: rgba(255, 255, 255, 0.7);
            padding: 0.75rem 1.25rem;
            margin: 0.125rem 0.75rem;
            border-radius: var(--oms-radius);
            font-weight: 500;
            font-size: 0.875rem;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            position: relative;
            border-left: 3px solid transparent;
        }
        
        .sidebar .nav-link:hover { 
            background: rgba(255, 255, 255, 0.08);
            color: #fff;
            border-left-color: rgba(99, 102, 241, 0.5);
        }
        
        .sidebar .nav-link.active { 
            background: rgba(99, 102, 241, 0.15);
            color: #fff;
            border-left-color: var(--oms-accent);
            font-weight: 600;
        }
        
        .sidebar .nav-link i { 
            width: 22px; 
            font-size: 1.1rem; 
            margin-right: 0.75rem;
            opacity: 0.85;
        }
        
        .sidebar .nav-link.active i {
            color: var(--oms-accent-light);
            opacity: 1;
        }
        
        .sidebar .badge {
            font-size: 0.7rem;
            padding: 0.25rem 0.5rem;
            font-weight: 600;
        }
        
        .brand-title { 
            font-size: 1.375rem; 
            font-weight: 700; 
            color: #fff;
            letter-spacing: -0.025em;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .brand-title i {
            color: var(--oms-accent-light);
        }
        
        .brand-subtitle {
            font-size: 0.6875rem;
            color: rgba(255, 255, 255, 0.45);
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin-top: 0.25rem;
        }
        
        .sidebar-section-title {
            font-size: 0.6875rem;
            color: rgba(255, 255, 255, 0.4);
            text-transform: uppercase;
            letter-spacing: 0.1em;
            padding: 1rem 1.25rem 0.5rem;
            font-weight: 600;
        }
        
        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            padding: 1.5rem 2rem;
        }
        
        /* Premium Cards */
        .card {
            background: var(--oms-card-bg);
            border: 1px solid var(--oms-border);
            border-radius: var(--oms-radius-lg);
            box-shadow: var(--oms-shadow-sm);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .card:hover {
            box-shadow: var(--oms-shadow);
        }
        
        .card-header {
            background: transparent;
            border-bottom: 1px solid var(--oms-border);
            font-weight: 600;
            padding: 1rem 1.25rem;
            color: var(--oms-text);
        }
        
        .card-body {
            padding: 1.25rem;
        }
        
        /* Premium Stat Cards */
        .stat-card { 
            background: var(--oms-card-bg);
            border-radius: var(--oms-radius-xl); 
            border: 1px solid var(--oms-border);
            padding: 1.5rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--oms-accent), var(--oms-accent-light));
        }
        
        .stat-card:hover { 
            transform: translateY(-4px); 
            box-shadow: var(--oms-shadow-lg);
            border-color: transparent;
        }
        
        .stat-card.stat-success::before { 
            background: linear-gradient(90deg, var(--oms-success), var(--oms-success-light)); 
        }
        .stat-card.stat-warning::before { 
            background: linear-gradient(90deg, var(--oms-warning), var(--oms-warning-light)); 
        }
        .stat-card.stat-danger::before { 
            background: linear-gradient(90deg, var(--oms-danger), var(--oms-danger-light)); 
        }
        .stat-card.stat-info::before { 
            background: linear-gradient(90deg, var(--oms-info), var(--oms-info-light)); 
        }
        
        .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: var(--oms-radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(99, 102, 241, 0.05));
            color: var(--oms-accent);
        }
        
        .stat-icon.icon-success {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(16, 185, 129, 0.05));
            color: var(--oms-success);
        }
        .stat-icon.icon-warning {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.15), rgba(245, 158, 11, 0.05));
            color: var(--oms-warning);
        }
        .stat-icon.icon-danger {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.15), rgba(239, 68, 68, 0.05));
            color: var(--oms-danger);
        }
        .stat-icon.icon-info {
            background: linear-gradient(135deg, rgba(14, 165, 233, 0.15), rgba(14, 165, 233, 0.05));
            color: var(--oms-info);
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--oms-text);
            line-height: 1.2;
        }
        
        .stat-label {
            font-size: 0.8125rem;
            color: var(--oms-text-muted);
            font-weight: 500;
            margin-top: 0.25rem;
        }
        
        /* Premium Tables */
        .table {
            margin-bottom: 0;
        }
        
        .table thead th {
            background: var(--oms-bg);
            color: var(--oms-text-muted);
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 2px solid var(--oms-border);
            padding: 0.875rem 1rem;
            white-space: nowrap;
        }
        
        .table tbody td {
            padding: 1rem;
            vertical-align: middle;
            border-bottom: 1px solid var(--oms-border-light);
            color: var(--oms-text);
        }
        
        .table tbody tr {
            transition: background-color 0.15s ease;
        }
        
        .table tbody tr:hover {
            background-color: rgba(99, 102, 241, 0.03);
        }
        
        .table tbody tr:last-child td {
            border-bottom: none;
        }
        
        /* Premium Badges */
        .badge {
            font-weight: 600;
            font-size: 0.75rem;
            padding: 0.375rem 0.625rem;
            border-radius: 0.375rem;
            letter-spacing: 0.01em;
        }
        
        .badge-online, .badge-success { 
            background: linear-gradient(135deg, var(--oms-success), var(--oms-success-light));
            color: white;
        }
        .badge-offline, .badge-secondary { 
            background: linear-gradient(135deg, #64748b, #94a3b8);
            color: white;
        }
        .badge-los, .badge-danger { 
            background: linear-gradient(135deg, var(--oms-danger), var(--oms-danger-light));
            color: white;
        }
        .badge-warning { 
            background: linear-gradient(135deg, var(--oms-warning), var(--oms-warning-light));
            color: #1e293b;
        }
        .badge-info { 
            background: linear-gradient(135deg, var(--oms-info), var(--oms-info-light));
            color: white;
        }
        .badge-pending, .bg-warning { 
            background: linear-gradient(135deg, var(--oms-warning), var(--oms-warning-light)) !important;
            color: #1e293b !important;
        }
        
        /* Premium Buttons */
        .btn {
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: var(--oms-radius);
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            font-size: 0.875rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--oms-accent), var(--oms-accent-dark));
            border: none;
            box-shadow: 0 2px 4px rgba(99, 102, 241, 0.25);
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, var(--oms-accent-light), var(--oms-accent));
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.35);
        }
        
        .btn-success {
            background: linear-gradient(135deg, var(--oms-success), #059669);
            border: none;
            box-shadow: 0 2px 4px rgba(16, 185, 129, 0.25);
        }
        .btn-success:hover {
            background: linear-gradient(135deg, var(--oms-success-light), var(--oms-success));
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.35);
        }
        
        .btn-warning {
            background: linear-gradient(135deg, var(--oms-warning), #d97706);
            border: none;
            color: #1e293b;
            box-shadow: 0 2px 4px rgba(245, 158, 11, 0.25);
        }
        .btn-warning:hover {
            background: linear-gradient(135deg, var(--oms-warning-light), var(--oms-warning));
            transform: translateY(-1px);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, var(--oms-danger), #dc2626);
            border: none;
            box-shadow: 0 2px 4px rgba(239, 68, 68, 0.25);
        }
        .btn-danger:hover {
            background: linear-gradient(135deg, var(--oms-danger-light), var(--oms-danger));
            transform: translateY(-1px);
        }
        
        .btn-outline-primary {
            border: 2px solid var(--oms-accent);
            color: var(--oms-accent);
            background: transparent;
        }
        .btn-outline-primary:hover {
            background: var(--oms-accent);
            border-color: var(--oms-accent);
            color: white;
        }
        
        .btn-outline-secondary {
            border: 2px solid var(--oms-border);
            color: var(--oms-text-muted);
            background: transparent;
        }
        .btn-outline-secondary:hover {
            background: var(--oms-bg);
            border-color: var(--oms-text-muted);
            color: var(--oms-text);
        }
        
        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.8125rem;
        }
        
        .btn-lg {
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
        }
        
        /* Premium Forms */
        .form-control, .form-select {
            border-radius: var(--oms-radius);
            border: 2px solid var(--oms-border);
            padding: 0.625rem 1rem;
            transition: all 0.2s ease;
            font-size: 0.9375rem;
            background-color: var(--oms-card-bg);
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--oms-accent);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
            outline: none;
        }
        
        .form-label {
            font-weight: 500;
            font-size: 0.875rem;
            color: var(--oms-text);
            margin-bottom: 0.5rem;
        }
        
        .input-group-text {
            background: var(--oms-bg);
            border: 2px solid var(--oms-border);
            border-right: none;
            color: var(--oms-text-muted);
        }
        
        .input-group .form-control {
            border-left: none;
        }
        
        .input-group .form-control:focus {
            border-color: var(--oms-accent);
        }
        
        .input-group:focus-within .input-group-text {
            border-color: var(--oms-accent);
        }
        
        /* Premium Modals */
        .modal-content {
            border: none;
            border-radius: var(--oms-radius-xl);
            box-shadow: var(--oms-shadow-xl);
            overflow: hidden;
        }
        
        .modal-header {
            background: linear-gradient(135deg, var(--oms-primary), var(--oms-primary-light));
            color: white;
            border-bottom: none;
            padding: 1.25rem 1.5rem;
        }
        
        .modal-header .modal-title {
            font-weight: 600;
            font-size: 1.125rem;
        }
        
        .modal-header .btn-close {
            filter: brightness(0) invert(1);
            opacity: 0.7;
        }
        
        .modal-header .btn-close:hover {
            opacity: 1;
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .modal-footer {
            border-top: 1px solid var(--oms-border);
            padding: 1rem 1.5rem;
            background: var(--oms-bg);
        }
        
        /* Page Header */
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .page-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--oms-text);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin: 0;
        }
        
        .page-title i {
            color: var(--oms-accent);
            font-size: 1.25rem;
        }
        
        /* Live Indicator */
        .live-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.375rem 0.875rem;
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.05));
            border: 1px solid rgba(16, 185, 129, 0.2);
            border-radius: 2rem;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--oms-success);
        }
        
        .live-dot {
            width: 8px;
            height: 8px;
            background: var(--oms-success);
            border-radius: 50%;
            animation: live-pulse 1.5s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        
        @keyframes live-pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(0.85); }
        }
        
        /* Loading Overlay */
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        
        .loading-overlay.active { 
            display: flex; 
        }
        
        .loading-spinner-container {
            background: white;
            padding: 2.5rem 3.5rem;
            border-radius: var(--oms-radius-xl);
            text-align: center;
            box-shadow: var(--oms-shadow-xl);
        }
        
        .loading-spinner {
            width: 48px;
            height: 48px;
            border: 4px solid var(--oms-border);
            border-top-color: var(--oms-accent);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin: 0 auto 1rem;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .loading-text {
            color: var(--oms-text);
            font-weight: 600;
            font-size: 1rem;
        }
        
        /* Pulsing Badge */
        .badge-pulse {
            animation: pulse-animation 2s infinite;
        }
        
        @keyframes pulse-animation {
            0% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.7); }
            70% { box-shadow: 0 0 0 8px rgba(245, 158, 11, 0); }
            100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0); }
        }
        
        .pending-auth-highlight {
            background: linear-gradient(90deg, rgba(245, 158, 11, 0.08) 0%, transparent 100%);
        }
        
        /* Nav Tabs */
        .nav-tabs {
            border-bottom: 2px solid var(--oms-border);
        }
        
        .nav-tabs .nav-link {
            border: none;
            color: var(--oms-text-muted);
            font-weight: 500;
            padding: 0.75rem 1.25rem;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            transition: all 0.2s ease;
        }
        
        .nav-tabs .nav-link:hover {
            color: var(--oms-accent);
            border-color: transparent;
            background: transparent;
        }
        
        .nav-tabs .nav-link.active {
            color: var(--oms-accent);
            background: transparent;
            border-bottom-color: var(--oms-accent);
        }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: var(--oms-bg);
        }
        
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
        
        /* Sync Button */
        .btn-sync { position: relative; }
        .btn-sync .spinner-border { display: none; }
        .btn-sync.syncing .spinner-border { display: inline-block; }
        .btn-sync.syncing .btn-text { display: none; }
        
        /* ONU Signal Bars */
        .signal-bars {
            display: inline-flex;
            align-items: flex-end;
            gap: 2px;
            height: 16px;
        }
        
        .signal-bar {
            width: 4px;
            background: var(--oms-border);
            border-radius: 2px;
        }
        
        .signal-bar.active {
            background: var(--oms-success);
        }
        
        .signal-bar.warning {
            background: var(--oms-warning);
        }
        
        .signal-bar.danger {
            background: var(--oms-danger);
        }
        
        /* Mobile Header */
        .oms-mobile-header {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 60px;
            background: linear-gradient(135deg, var(--oms-primary), var(--oms-primary-light));
            z-index: 1050;
            padding: 0 1rem;
            align-items: center;
            justify-content: space-between;
            box-shadow: var(--oms-shadow);
        }
        
        .oms-mobile-header .brand-mobile {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .oms-mobile-header .brand-mobile .brand-title {
            color: white;
            font-size: 1.125rem;
            font-weight: 700;
        }
        
        .oms-mobile-header .hamburger-btn {
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: white;
            font-size: 1.5rem;
            padding: 0.5rem 0.75rem;
            border-radius: var(--oms-radius);
            transition: background 0.2s ease;
        }
        
        .oms-mobile-header .hamburger-btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        /* Offcanvas for Mobile */
        .offcanvas.oms-offcanvas {
            background: linear-gradient(180deg, var(--oms-primary), var(--oms-primary-light));
            width: 280px !important;
        }
        
        .offcanvas.oms-offcanvas .offcanvas-header {
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            padding: 1.25rem;
        }
        
        .offcanvas.oms-offcanvas .btn-close {
            filter: brightness(0) invert(1);
            opacity: 0.7;
        }
        
        /* Responsive */
        @media (max-width: 991.98px) {
            .sidebar {
                display: none !important;
            }
            
            .oms-mobile-header {
                display: flex !important;
            }
            
            .main-content {
                margin-left: 0;
                padding: 1rem;
                padding-top: 76px;
            }
            
            .page-title {
                font-size: 1.25rem;
            }
            
            .stat-card {
                padding: 1rem;
            }
            
            .stat-value {
                font-size: 1.5rem;
            }
            
            .stat-icon {
                width: 44px;
                height: 44px;
                font-size: 1.25rem;
            }
            
            .table-responsive {
                margin: 0 -1rem;
                padding: 0 1rem;
            }
            
            .btn-mobile-full {
                width: 100%;
                margin-bottom: 0.5rem;
            }
        }
        
        @media (min-width: 992px) {
            .oms-mobile-header {
                display: none !important;
            }
            
            .sidebar {
                display: flex !important;
            }
        }
        
        @media (max-width: 575.98px) {
            .main-content {
                padding: 0.75rem;
                padding-top: 72px;
            }
            
            .card-body {
                padding: 1rem;
            }
            
            .modal-body {
                padding: 1rem;
            }
            
            .modal-dialog {
                margin: 0.5rem;
            }
        }
        
        /* Alert Cards */
        .alert {
            border: none;
            border-radius: var(--oms-radius-lg);
            border-left: 4px solid;
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border-left-color: var(--oms-success);
            color: #065f46;
        }
        
        .alert-warning {
            background: rgba(245, 158, 11, 0.1);
            border-left-color: var(--oms-warning);
            color: #92400e;
        }
        
        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            border-left-color: var(--oms-danger);
            color: #991b1b;
        }
        
        .alert-info {
            background: rgba(14, 165, 233, 0.1);
            border-left-color: var(--oms-info);
            color: #075985;
        }
        
        /* Dropdown */
        .dropdown-menu {
            border: 1px solid var(--oms-border);
            border-radius: var(--oms-radius-lg);
            box-shadow: var(--oms-shadow-lg);
            padding: 0.5rem;
        }
        
        .dropdown-item {
            border-radius: var(--oms-radius);
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            transition: all 0.15s ease;
        }
        
        .dropdown-item:hover {
            background: var(--oms-bg);
        }
        
        .dropdown-item.active, .dropdown-item:active {
            background: var(--oms-accent);
            color: white;
        }
        
        /* Back to CRM link */
        .back-to-crm {
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.8125rem;
            padding: 0.5rem 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            transition: color 0.2s ease;
        }
        
        .back-to-crm:hover {
            color: rgba(255, 255, 255, 0.8);
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1.5rem;
            color: var(--oms-text-muted);
        }
        
        .empty-state i {
            font-size: 3rem;
            color: var(--oms-border);
            margin-bottom: 1rem;
        }
        
        .empty-state h5 {
            color: var(--oms-text);
            font-weight: 600;
            margin-bottom: 0.5rem;
        
        /* Premium ONU Status Button */
        .btn-status-refresh {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--oms-accent), var(--oms-accent-dark));
            color: white;
            border: none;
            box-shadow: 0 2px 8px rgba(99, 102, 241, 0.3);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-size: 0.875rem;
        }
        
        .btn-status-refresh:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 16px rgba(99, 102, 241, 0.4);
            color: white;
        }
        
        .btn-status-refresh:active {
            transform: scale(0.95);
        }
        
        .btn-status-refresh.spinning i {
            animation: spin 0.8s linear infinite;
        }
        
        /* ONU Status Card */
        .onu-status-card {
            background: var(--oms-card-bg);
            border-radius: var(--oms-radius-xl);
            border: 1px solid var(--oms-border);
            overflow: hidden;
            box-shadow: var(--oms-shadow-sm);
        }
        
        .onu-status-header {
            padding: 1.25rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .onu-status-icon {
            width: 64px;
            height: 64px;
            border-radius: var(--oms-radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
        }
        
        .onu-status-icon.online {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(16, 185, 129, 0.05));
            color: var(--oms-success);
        }
        
        .onu-status-icon.offline {
            background: linear-gradient(135deg, rgba(100, 116, 139, 0.15), rgba(100, 116, 139, 0.05));
            color: var(--oms-text-muted);
        }
        
        .onu-status-icon.los {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.15), rgba(239, 68, 68, 0.05));
            color: var(--oms-danger);
        }
        
        .onu-status-info h5 {
            margin: 0;
            font-weight: 600;
            color: var(--oms-text);
            font-size: 1.125rem;
        }
        
        .onu-status-info .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.375rem 0.75rem;
            border-radius: 2rem;
            font-size: 0.8125rem;
            font-weight: 600;
            margin-top: 0.5rem;
        }
        
        .onu-status-info .status-badge.online {
            background: linear-gradient(135deg, var(--oms-success), var(--oms-success-light));
            color: white;
        }
        
        .onu-status-info .status-badge.offline {
            background: linear-gradient(135deg, #64748b, #94a3b8);
            color: white;
        }
        
        .onu-status-info .status-badge.los {
            background: linear-gradient(135deg, var(--oms-danger), var(--oms-danger-light));
            color: white;
            animation: pulse-danger 2s infinite;
        }
        
        @keyframes pulse-danger {
            0%, 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4); }
            50% { box-shadow: 0 0 0 8px rgba(239, 68, 68, 0); }
        }
        
        /* Optical Power Display */
        .optical-power-display {
            display: flex;
            gap: 1rem;
            padding: 1rem 1.25rem;
            background: var(--oms-bg);
            border-top: 1px solid var(--oms-border);
        }
        
        .optical-power-item {
            flex: 1;
            text-align: center;
            padding: 0.75rem;
            background: var(--oms-card-bg);
            border-radius: var(--oms-radius);
            border: 1px solid var(--oms-border);
        }
        
        .optical-power-item .power-label {
            font-size: 0.6875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--oms-text-muted);
            margin-bottom: 0.25rem;
        }
        
        .optical-power-item .power-value {
            font-size: 1.125rem;
            font-weight: 700;
            color: var(--oms-text);
        }
        
        .optical-power-item .power-value.good {
            color: var(--oms-success);
        }
        
        .optical-power-item .power-value.warning {
            color: var(--oms-warning);
        }
        
        .optical-power-item .power-value.danger {
            color: var(--oms-danger);
        }
        
        /* Quick Action Buttons */
        .onu-quick-actions {
            display: flex;
            gap: 0.5rem;
            padding: 1rem 1.25rem;
            border-top: 1px solid var(--oms-border);
            flex-wrap: wrap;
        }
        
        .btn-quick-action {
            flex: 1;
            min-width: 100px;
            padding: 0.625rem 1rem;
            border-radius: var(--oms-radius);
            font-size: 0.8125rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
        }
        
        .btn-quick-action.btn-check-status {
            background: linear-gradient(135deg, var(--oms-accent), var(--oms-accent-dark));
            color: white;
            border: none;
        }
        
        .btn-quick-action.btn-check-status:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.35);
        }
        
        .btn-quick-action.btn-reboot {
            background: linear-gradient(135deg, var(--oms-warning), #d97706);
            color: #1e293b;
            border: none;
        }
        
        .btn-quick-action.btn-reboot:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.35);
        }
        
        .btn-quick-action.btn-delete {
            background: linear-gradient(135deg, var(--oms-danger), #dc2626);
            color: white;
            border: none;
        }
        
        .btn-quick-action.btn-delete:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.35);
        }
        
        /* Info Label/Value pairs */
        .info-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--oms-text-muted);
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .info-value {
            font-size: 0.9375rem;
            color: var(--oms-text);
            font-weight: 500;
        }
        }
    </style>
    <!-- Load Bootstrap JS early for mobile offcanvas and all modals -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
    <!-- Loading Overlay for OLT Operations -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="loading-spinner-container">
            <div class="loading-spinner"></div>
            <div class="loading-text" id="loadingText">Connecting to OLT...</div>
            <div class="text-muted small mt-2" id="loadingSubtext">This may take a few seconds</div>
            <div id="loadingStages" class="mt-3 text-start" style="display:none; max-width: 350px;">
                <div class="stage-item" id="stage1" data-stage="1">
                    <i class="bi bi-circle stage-icon me-2"></i>
                    <span>Saving ONU details...</span>
                </div>
                <div class="stage-item" id="stage2" data-stage="2">
                    <i class="bi bi-circle stage-icon me-2"></i>
                    <span>Authorizing on OLT...</span>
                </div>
                <div class="stage-item" id="stage3" data-stage="3">
                    <i class="bi bi-circle stage-icon me-2"></i>
                    <span>Configuring service VLAN...</span>
                </div>
                <div class="stage-item" id="stage4" data-stage="4">
                    <i class="bi bi-circle stage-icon me-2"></i>
                    <span>Setting up TR-069 management...</span>
                </div>
            </div>
            <div id="loadingError" class="alert alert-danger mt-3" style="display:none; max-width: 350px;"></div>
        </div>
    </div>
    <style>
        .stage-item { padding: 6px 0; color: #6c757d; font-size: 0.9rem; }
        .stage-item.active { color: #0d6efd; font-weight: 500; }
        .stage-item.active .stage-icon { animation: pulse 1s infinite; }
        .stage-item.success { color: #198754; }
        .stage-item.success .stage-icon:before { content: "\F26B"; }
        .stage-item.error { color: #dc3545; }
        .stage-item.error .stage-icon:before { content: "\F62A"; }
        @keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.4; } }
    </style>
    
    <!-- OMS Mobile Header -->
    <div class="oms-mobile-header">
        <div class="brand-mobile">
            <div style="width: 36px; height: 36px; background: linear-gradient(135deg, var(--oms-accent), var(--oms-accent-light)); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                <i class="bi bi-router text-white"></i>
            </div>
            <span class="brand-title text-white">OMS</span>
        </div>
        <button class="hamburger-btn" type="button" data-bs-toggle="offcanvas" data-bs-target="#omsMobileSidebar">
            <i class="bi bi-list"></i>
        </button>
    </div>
    
    <!-- OMS Mobile Offcanvas Sidebar -->
    <div class="offcanvas offcanvas-start oms-offcanvas" tabindex="-1" id="omsMobileSidebar">
        <div class="offcanvas-header">
            <div class="d-flex align-items-center">
                <div class="me-2" style="width: 36px; height: 36px; background: linear-gradient(135deg, var(--oms-accent), var(--oms-accent-light)); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                    <i class="bi bi-router text-white"></i>
                </div>
                <div>
                    <span class="brand-title text-white">OMS</span>
                    <div class="brand-subtitle">Network Manager</div>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body p-2">
            <a href="?page=dashboard" class="nav-link text-white-50 small mb-2">
                <i class="bi bi-arrow-left me-2"></i> Back to CRM
            </a>
            <nav class="nav flex-column">
                <a class="nav-link <?= $view === 'dashboard' ? 'active' : '' ?>" href="?page=huawei-olt&view=dashboard">
                    <i class="bi bi-speedometer2 me-2"></i> Dashboard
                </a>
                <a class="nav-link <?= $view === 'olts' ? 'active' : '' ?>" href="?page=huawei-olt&view=olts">
                    <i class="bi bi-hdd-rack me-2"></i> OLT Devices
                </a>
                <a class="nav-link <?= ($view === 'onus' && !isset($_GET['unconfigured'])) ? 'active' : '' ?>" href="?page=huawei-olt&view=onus">
                    <i class="bi bi-check-circle me-2"></i> Authorized ONUs
                </a>
                <a class="nav-link <?= isset($_GET['unconfigured']) ? 'active' : '' ?>" href="?page=huawei-olt&view=onus&unconfigured=1">
                    <i class="bi bi-hourglass-split me-2"></i> Non Auth
                    <?php $mobileTotalPending = $stats['unconfigured_onus'] + ($stats['discovered_onus'] ?? 0); ?>
                    <span id="nonAuthBadgeMobile" class="badge <?= $mobileTotalPending > 0 ? 'bg-warning' : 'bg-secondary' ?> ms-auto"><?= $mobileTotalPending ?> pending</span>
                </a>
                <a class="nav-link <?= $view === 'locations' ? 'active' : '' ?>" href="?page=huawei-olt&view=locations">
                    <i class="bi bi-geo-alt me-2"></i> Locations
                </a>
                <a class="nav-link <?= $view === 'topology' ? 'active' : '' ?>" href="?page=huawei-olt&view=topology">
                    <i class="bi bi-diagram-3 me-2"></i> Network Map
                </a>
                <a class="nav-link <?= $view === 'logs' ? 'active' : '' ?>" href="?page=huawei-olt&view=logs">
                    <i class="bi bi-journal-text me-2"></i> Logs
                </a>
                <a class="nav-link <?= $view === 'alerts' ? 'active' : '' ?>" href="?page=huawei-olt&view=alerts">
                    <i class="bi bi-bell me-2"></i> Alerts
                </a>
                <a class="nav-link <?= $view === 'terminal' ? 'active' : '' ?>" href="?page=huawei-olt&view=terminal">
                    <i class="bi bi-terminal me-2"></i> CLI Terminal
                </a>
                <a class="nav-link <?= $view === 'migrations' ? 'active' : '' ?>" href="?page=huawei-olt&view=migrations">
                    <i class="bi bi-arrow-left-right me-2"></i> ONU Migrations
                </a>
                <hr class="my-2 border-light opacity-25">
                <a class="nav-link <?= $view === 'tr069' ? 'active' : '' ?>" href="?page=huawei-olt&view=tr069">
                    <i class="bi bi-gear-wide-connected me-2"></i> TR-069
                </a>
                <a class="nav-link <?= $view === 'vpn' ? 'active' : '' ?>" href="?page=huawei-olt&view=vpn">
                    <i class="bi bi-shield-lock-fill me-2"></i> VPN
                </a>
                <a class="nav-link <?= $view === 'settings' ? 'active' : '' ?>" href="?page=huawei-olt&view=settings">
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
                <div class="me-3" style="width: 44px; height: 44px; background: linear-gradient(135deg, var(--oms-accent), var(--oms-accent-light)); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                    <i class="bi bi-router fs-5 text-white"></i>
                </div>
                <div>
                    <span class="brand-title">OMS</span>
                    <div class="brand-subtitle">Network Manager</div>
                </div>
            </div>
            <nav class="nav flex-column">
                <a class="nav-link <?= $view === 'dashboard' ? 'active' : '' ?>" href="?page=huawei-olt&view=dashboard">
                    <i class="bi bi-speedometer2 me-2"></i> Dashboard
                </a>
                <a class="nav-link <?= $view === 'olts' ? 'active' : '' ?>" href="?page=huawei-olt&view=olts">
                    <i class="bi bi-hdd-rack me-2"></i> OLT Devices
                </a>
                <a class="nav-link <?= ($view === 'onus' && !isset($_GET['unconfigured'])) ? 'active' : '' ?>" href="?page=huawei-olt&view=onus">
                    <i class="bi bi-check-circle me-2"></i> Authorized ONUs
                </a>
                <a class="nav-link <?= isset($_GET['unconfigured']) ? 'active' : '' ?> <?= ($stats['unconfigured_onus'] > 0 || $stats['discovered_onus'] > 0) ? 'pending-auth-highlight' : '' ?>" href="?page=huawei-olt&view=onus&unconfigured=1">
                    <i class="bi bi-hourglass-split me-2"></i> Non Auth
                    <?php $totalPending = $stats['unconfigured_onus'] + ($stats['discovered_onus'] ?? 0); ?>
                    <span id="nonAuthBadgeDesktop" class="badge <?= $totalPending > 0 ? 'bg-warning badge-pulse' : 'bg-secondary' ?> ms-auto"><?= $totalPending ?> pending</span>
                </a>
                <a class="nav-link <?= $view === 'locations' ? 'active' : '' ?>" href="?page=huawei-olt&view=locations">
                    <i class="bi bi-geo-alt me-2"></i> Locations
                </a>
                <a class="nav-link <?= $view === 'topology' ? 'active' : '' ?>" href="?page=huawei-olt&view=topology">
                    <i class="bi bi-diagram-3 me-2"></i> Network Map
                </a>
                <a class="nav-link <?= $view === 'logs' ? 'active' : '' ?>" href="?page=huawei-olt&view=logs">
                    <i class="bi bi-journal-text me-2"></i> Provisioning Logs
                </a>
                <a class="nav-link <?= $view === 'alerts' ? 'active' : '' ?>" href="?page=huawei-olt&view=alerts">
                    <i class="bi bi-bell me-2"></i> Alerts
                    <?php if ($stats['recent_alerts'] > 0): ?>
                    <span class="badge bg-danger ms-auto"><?= $stats['recent_alerts'] ?></span>
                    <?php endif; ?>
                </a>
                <a class="nav-link <?= $view === 'terminal' ? 'active' : '' ?>" href="?page=huawei-olt&view=terminal">
                    <i class="bi bi-terminal me-2"></i> CLI Terminal
                </a>
                <a class="nav-link <?= $view === 'migrations' ? 'active' : '' ?>" href="?page=huawei-olt&view=migrations">
                    <i class="bi bi-arrow-left-right me-2"></i> ONU Migrations
                </a>
                <hr class="my-2 border-light opacity-25">
                <a class="nav-link <?= $view === 'tr069' ? 'active' : '' ?>" href="?page=huawei-olt&view=tr069">
                    <i class="bi bi-gear-wide-connected me-2"></i> TR-069 / ACS
                </a>
                <a class="nav-link <?= $view === 'vpn' ? 'active' : '' ?>" href="?page=huawei-olt&view=vpn">
                    <i class="bi bi-shield-lock-fill me-2"></i> VPN
                </a>
                <a class="nav-link <?= $view === 'settings' ? 'active' : '' ?>" href="?page=huawei-olt&view=settings">
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
            <!-- Modern Network Dashboard -->
            <style>
                .dashboard-header {
                    background: linear-gradient(135deg, #1e3a5f 0%, #0d2137 100%);
                    border-radius: 16px;
                    padding: 24px 32px;
                    margin-bottom: 24px;
                    color: white;
                    position: relative;
                    overflow: hidden;
                }
                .dashboard-header::before {
                    content: '';
                    position: absolute;
                    top: -50%;
                    right: -20%;
                    width: 400px;
                    height: 400px;
                    background: radial-gradient(circle, rgba(59, 130, 246, 0.15) 0%, transparent 70%);
                    border-radius: 50%;
                }
                .dashboard-header .live-pulse {
                    width: 10px;
                    height: 10px;
                    background: #10b981;
                    border-radius: 50%;
                    animation: pulse 2s infinite;
                    display: inline-block;
                    margin-right: 6px;
                }
                @keyframes pulse {
                    0%, 100% { opacity: 1; transform: scale(1); }
                    50% { opacity: 0.6; transform: scale(1.2); }
                }
                .mega-stat {
                    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
                    border-radius: 16px;
                    padding: 24px;
                    border: 1px solid rgba(0,0,0,0.05);
                    transition: all 0.3s ease;
                    position: relative;
                    overflow: hidden;
                }
                .mega-stat:hover {
                    transform: translateY(-4px);
                    box-shadow: 0 12px 40px rgba(0,0,0,0.12);
                }
                .mega-stat .stat-icon-lg {
                    width: 56px;
                    height: 56px;
                    border-radius: 14px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 1.5rem;
                }
                .mega-stat .stat-number {
                    font-size: 2.5rem;
                    font-weight: 700;
                    line-height: 1;
                    margin: 12px 0 4px;
                }
                .mega-stat .stat-trend {
                    font-size: 0.75rem;
                    padding: 2px 8px;
                    border-radius: 20px;
                    display: inline-flex;
                    align-items: center;
                    gap: 4px;
                }
                .olt-card {
                    background: white;
                    border-radius: 12px;
                    padding: 16px;
                    border: 1px solid #e2e8f0;
                    transition: all 0.2s ease;
                }
                .olt-card:hover {
                    border-color: #3b82f6;
                    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.1);
                }
                .olt-card .health-ring {
                    width: 48px;
                    height: 48px;
                    position: relative;
                }
                .olt-card .health-ring svg {
                    transform: rotate(-90deg);
                }
                .olt-card .health-value {
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    font-size: 0.7rem;
                    font-weight: 700;
                }
                .quick-action-btn {
                    background: white;
                    border: 1px solid #e2e8f0;
                    border-radius: 12px;
                    padding: 16px;
                    text-align: center;
                    transition: all 0.2s ease;
                    text-decoration: none;
                    color: #334155;
                    display: block;
                }
                .quick-action-btn:hover {
                    border-color: #3b82f6;
                    color: #3b82f6;
                    transform: translateY(-2px);
                    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15);
                }
                .quick-action-btn i {
                    font-size: 1.5rem;
                    margin-bottom: 8px;
                    display: block;
                }
                .alert-item {
                    padding: 12px 16px;
                    border-left: 3px solid;
                    background: #f8fafc;
                    border-radius: 0 8px 8px 0;
                    margin-bottom: 8px;
                }
                .alert-item.critical { border-left-color: #ef4444; background: #fef2f2; }
                .alert-item.warning { border-left-color: #f59e0b; background: #fffbeb; }
                .alert-item.info { border-left-color: #3b82f6; background: #eff6ff; }
                .uptime-gauge {
                    width: 180px;
                    height: 180px;
                    position: relative;
                    margin: 0 auto;
                }
                .uptime-gauge .gauge-bg {
                    stroke: #e2e8f0;
                }
                .uptime-gauge .gauge-fill {
                    stroke: url(#gaugeGradient);
                    stroke-linecap: round;
                    transition: stroke-dasharray 1s ease;
                }
                .uptime-gauge .gauge-center {
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    text-align: center;
                }
                .uptime-gauge .gauge-value {
                    font-size: 2.5rem;
                    font-weight: 700;
                    color: #10b981;
                }
                .issue-badge {
                    font-size: 0.7rem;
                    padding: 4px 8px;
                    border-radius: 6px;
                    font-weight: 600;
                }
            </style>
            
            <!-- Dashboard Header -->
            <div class="dashboard-header">
                <div class="d-flex justify-content-between align-items-center position-relative" style="z-index: 1;">
                    <div>
                        <h3 class="mb-1 fw-bold"><i class="bi bi-speedometer2 me-2"></i>Network Dashboard</h3>
                        <div class="d-flex align-items-center gap-3 opacity-75">
                            <span><span class="live-pulse"></span> Live Monitoring</span>
                            <span><i class="bi bi-clock me-1"></i><?= date('M j, Y H:i:s') ?></span>
                            <span id="autoRefreshTimer" class="small"></span>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="?page=huawei-olt&view=onus&unconfigured=1" class="btn btn-warning">
                            <i class="bi bi-hourglass-split me-1"></i> <?= $stats['unconfigured_onus'] ?> Pending
                        </a>
                        <button class="btn btn-light" onclick="location.reload()" title="Refresh">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Primary Stats Row -->
            <div class="row g-4 mb-4">
                <div class="col-lg-3 col-md-6">
                    <div class="mega-stat h-100">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="stat-icon-lg bg-primary bg-opacity-10 text-primary">
                                <i class="bi bi-hdd-rack"></i>
                            </div>
                            <span class="stat-trend bg-success bg-opacity-10 text-success">
                                <i class="bi bi-circle-fill" style="font-size: 6px;"></i> <?= $stats['active_olts'] ?> Active
                            </span>
                        </div>
                        <div class="stat-number text-primary"><?= $stats['total_olts'] ?></div>
                        <div class="text-muted mb-2">OLT Devices</div>
                        <div class="progress" style="height: 6px; border-radius: 3px;">
                            <div class="progress-bar bg-primary" style="width: <?= $stats['total_olts'] > 0 ? ($stats['active_olts'] / $stats['total_olts'] * 100) : 0 ?>%; border-radius: 3px;"></div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="mega-stat h-100">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="stat-icon-lg bg-success bg-opacity-10 text-success">
                                <i class="bi bi-wifi"></i>
                            </div>
                            <span class="stat-trend bg-success bg-opacity-10 text-success">
                                <i class="bi bi-arrow-up"></i> <?= $uptimePercent ?>%
                            </span>
                        </div>
                        <div class="stat-number text-success"><?= number_format($stats['online_onus']) ?></div>
                        <div class="text-muted mb-2">Online ONUs</div>
                        <div class="progress" style="height: 6px; border-radius: 3px;">
                            <div class="progress-bar bg-success" style="width: <?= $uptimePercent ?>%; border-radius: 3px;"></div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="mega-stat h-100">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="stat-icon-lg bg-danger bg-opacity-10 text-danger">
                                <i class="bi bi-exclamation-triangle"></i>
                            </div>
                            <?php $offlineTotal = $stats['offline_onus'] + $stats['los_onus']; ?>
                            <span class="stat-trend <?= $offlineTotal > 0 ? 'bg-danger bg-opacity-10 text-danger' : 'bg-success bg-opacity-10 text-success' ?>">
                                <?= $offlineTotal > 0 ? '<i class="bi bi-exclamation-circle"></i> Issues' : '<i class="bi bi-check-circle"></i> OK' ?>
                            </span>
                        </div>
                        <div class="stat-number text-danger"><?= $offlineTotal ?></div>
                        <div class="text-muted mb-2">Offline / LOS</div>
                        <div class="d-flex gap-2 small">
                            <span class="text-muted"><i class="bi bi-x-circle me-1"></i><?= $stats['offline_onus'] ?> Offline</span>
                            <span class="text-muted"><i class="bi bi-exclamation-diamond me-1"></i><?= $stats['los_onus'] ?> LOS</span>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="mega-stat h-100">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="stat-icon-lg bg-warning bg-opacity-10 text-warning">
                                <i class="bi bi-hourglass-split"></i>
                            </div>
                            <a href="?page=huawei-olt&view=onus&unconfigured=1" class="stat-trend bg-warning bg-opacity-10 text-warning text-decoration-none">
                                View All <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                        <div class="stat-number text-warning"><?= $stats['unconfigured_onus'] ?></div>
                        <div class="text-muted mb-2">Pending Authorization</div>
                        <div class="small text-muted">
                            <i class="bi bi-router me-1"></i><?= number_format($totalOnus) ?> Total Authorized
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Main Content Row -->
            <div class="row g-4 mb-4">
                <!-- OLT Status Cards -->
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0" style="border-radius: 16px;">
                        <div class="card-header bg-transparent border-0 pt-4 pb-2 px-4">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0 fw-bold"><i class="bi bi-hdd-rack me-2 text-primary"></i>OLT Status</h5>
                                <a href="?page=huawei-olt&view=olts" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                    <i class="bi bi-gear me-1"></i> Manage
                                </a>
                            </div>
                        </div>
                        <div class="card-body px-4 pb-4">
                            <?php if (empty($olts)): ?>
                            <div class="text-center py-5">
                                <div class="mb-3"><i class="bi bi-inbox display-4 text-muted"></i></div>
                                <p class="text-muted mb-3">No OLTs configured yet</p>
                                <a href="?page=huawei-olt&view=olts" class="btn btn-primary rounded-pill px-4">
                                    <i class="bi bi-plus-circle me-1"></i> Add First OLT
                                </a>
                            </div>
                            <?php else: ?>
                            <div class="row g-3">
                                <?php 
                                $onusByOlt = $huaweiOLT->getONUsByOLT();
                                $onuCountMap = array_column($onusByOlt, null, 'id');
                                foreach ($olts as $olt): 
                                    $oltId = $olt['id'];
                                    $oltOnline = $onuCountMap[$oltId]['online_count'] ?? 0;
                                    $oltTotal = $onuCountMap[$oltId]['total_count'] ?? 0;
                                    $oltOffline = $oltTotal - $oltOnline;
                                    $oltHealth = $oltTotal > 0 ? round($oltOnline / $oltTotal * 100) : 100;
                                    $healthColor = $oltHealth >= 95 ? '#10b981' : ($oltHealth >= 80 ? '#f59e0b' : '#ef4444');
                                ?>
                                <div class="col-md-6">
                                    <div class="olt-card">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="health-ring">
                                                <svg viewBox="0 0 36 36" width="48" height="48">
                                                    <circle cx="18" cy="18" r="15" fill="none" stroke="#e2e8f0" stroke-width="3"></circle>
                                                    <circle cx="18" cy="18" r="15" fill="none" stroke="<?= $healthColor ?>" stroke-width="3" 
                                                            stroke-dasharray="<?= $oltHealth * 0.94 ?> 100" stroke-linecap="round" 
                                                            style="transform: rotate(-90deg); transform-origin: center;"></circle>
                                                </svg>
                                                <span class="health-value" style="color: <?= $healthColor ?>"><?= $oltHealth ?>%</span>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="fw-bold"><?= htmlspecialchars($olt['name']) ?></div>
                                                <div class="small text-muted"><?= htmlspecialchars($olt['ip_address']) ?></div>
                                                <?php $oltUptime = $olt['uptime'] ?: ($olt['snmp_sys_uptime'] ?? null); if ($oltUptime): ?>
                                                <div class="small text-success"><i class="bi bi-clock-history me-1"></i><?= htmlspecialchars($oltUptime) ?></div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-end">
                                                <div class="d-flex gap-2 justify-content-end mb-1">
                                                    <span class="badge bg-success bg-opacity-10 text-success"><?= $oltOnline ?> <i class="bi bi-wifi"></i></span>
                                                    <?php if ($oltOffline > 0): ?>
                                                    <span class="badge bg-danger bg-opacity-10 text-danger"><?= $oltOffline ?> <i class="bi bi-wifi-off"></i></span>
                                                    <?php endif; ?>
                                                </div>
                                                <small class="text-muted"><?= $oltTotal ?> total</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Network Uptime Gauge -->
                <div class="col-lg-4">
                    <div class="card shadow-sm border-0 h-100" style="border-radius: 16px;">
                        <div class="card-header bg-transparent border-0 pt-4 pb-2 px-4">
                            <h5 class="mb-0 fw-bold"><i class="bi bi-pie-chart me-2 text-success"></i>Network Health</h5>
                        </div>
                        <div class="card-body d-flex flex-column align-items-center justify-content-center">
                            <div class="uptime-gauge">
                                <svg viewBox="0 0 36 36" width="180" height="180">
                                    <defs>
                                        <linearGradient id="gaugeGradient" x1="0%" y1="0%" x2="100%" y2="0%">
                                            <stop offset="0%" stop-color="#10b981"/>
                                            <stop offset="100%" stop-color="#059669"/>
                                        </linearGradient>
                                    </defs>
                                    <circle class="gauge-bg" cx="18" cy="18" r="15" fill="none" stroke-width="3"></circle>
                                    <circle class="gauge-fill" cx="18" cy="18" r="15" fill="none" stroke-width="3" 
                                            stroke-dasharray="<?= $uptimePercent * 0.94 ?> 100"
                                            style="transform: rotate(-90deg); transform-origin: center;"></circle>
                                </svg>
                                <div class="gauge-center">
                                    <div class="gauge-value"><?= $uptimePercent ?>%</div>
                                    <div class="text-muted small">Uptime</div>
                                </div>
                            </div>
                            <div class="row w-100 text-center mt-4 g-2">
                                <div class="col-4">
                                    <div class="p-2 rounded-3" style="background: rgba(16, 185, 129, 0.1);">
                                        <div class="fw-bold text-success"><?= number_format($stats['online_onus']) ?></div>
                                        <small class="text-muted">Online</small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="p-2 rounded-3" style="background: rgba(239, 68, 68, 0.1);">
                                        <div class="fw-bold text-danger"><?= $offlineTotal ?></div>
                                        <small class="text-muted">Down</small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="p-2 rounded-3" style="background: rgba(59, 130, 246, 0.1);">
                                        <div class="fw-bold text-primary"><?= number_format($totalOnus) ?></div>
                                        <small class="text-muted">Total</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions & Alerts Row -->
            <div class="row g-4 mb-4">
                <!-- Quick Actions -->
                <div class="col-lg-4">
                    <div class="card shadow-sm border-0 h-100" style="border-radius: 16px;">
                        <div class="card-header bg-transparent border-0 pt-4 pb-2 px-4">
                            <h5 class="mb-0 fw-bold"><i class="bi bi-lightning me-2 text-warning"></i>Quick Actions</h5>
                        </div>
                        <div class="card-body px-4 pb-4">
                            <div class="row g-3">
                                <div class="col-6">
                                    <a href="?page=huawei-olt&view=onus&unconfigured=1" class="quick-action-btn">
                                        <i class="bi bi-plus-circle text-success"></i>
                                        <div class="fw-medium">Authorize ONUs</div>
                                    </a>
                                </div>
                                <div class="col-6">
                                    <a href="?page=huawei-olt&view=live_monitor" class="quick-action-btn">
                                        <i class="bi bi-broadcast text-primary"></i>
                                        <div class="fw-medium">Live Monitor</div>
                                    </a>
                                </div>
                                <div class="col-6">
                                    <a href="?page=huawei-olt&view=tr069" class="quick-action-btn">
                                        <i class="bi bi-router text-info"></i>
                                        <div class="fw-medium">TR-069</div>
                                    </a>
                                </div>
                                <div class="col-6">
                                    <a href="?page=huawei-olt&view=terminal" class="quick-action-btn">
                                        <i class="bi bi-terminal text-dark"></i>
                                        <div class="fw-medium">CLI Terminal</div>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Alerts -->
                <div class="col-lg-4">
                    <div class="card shadow-sm border-0 h-100" style="border-radius: 16px;">
                        <div class="card-header bg-transparent border-0 pt-4 pb-2 px-4 d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 fw-bold"><i class="bi bi-bell me-2 text-danger"></i>Recent Alerts</h5>
                            <a href="?page=huawei-olt&view=alerts" class="btn btn-sm btn-link text-primary p-0">View All</a>
                        </div>
                        <div class="card-body px-4 pb-4" style="max-height: 280px; overflow-y: auto;">
                            <?php if (empty($alerts)): ?>
                            <div class="text-center py-4">
                                <i class="bi bi-check-circle display-4 text-success mb-2"></i>
                                <p class="text-muted mb-0">All systems operational</p>
                            </div>
                            <?php else: ?>
                            <?php foreach (array_slice($alerts, 0, 5) as $alert): ?>
                            <div class="alert-item <?= $alert['severity'] ?>">
                                <div class="d-flex align-items-center gap-2">
                                    <?php
                                    $severityIcon = ['info' => 'info-circle', 'warning' => 'exclamation-triangle', 'critical' => 'exclamation-circle'];
                                    ?>
                                    <i class="bi bi-<?= $severityIcon[$alert['severity']] ?? 'info-circle' ?>"></i>
                                    <div class="flex-grow-1">
                                        <div class="fw-medium small"><?= htmlspecialchars($alert['title']) ?></div>
                                        <div class="text-muted" style="font-size: 0.7rem;"><?= date('M j, H:i', strtotime($alert['created_at'])) ?></div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Problem ONUs -->
                <div class="col-lg-4">
                    <div class="card shadow-sm border-0 h-100" style="border-radius: 16px;">
                        <div class="card-header bg-transparent border-0 pt-4 pb-2 px-4 d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 fw-bold"><i class="bi bi-exclamation-diamond me-2 text-warning"></i>Problem ONUs</h5>
                            <a href="?page=huawei-olt&view=onus&status=offline" class="btn btn-sm btn-link text-primary p-0">View All</a>
                        </div>
                        <div class="card-body px-4 pb-4" style="max-height: 280px; overflow-y: auto;">
                            <?php
                            $issueONUs = $db->query("
                                SELECT o.id, o.sn, o.name as description, o.status, o.rx_power 
                                FROM huawei_onus o 
                                WHERE o.is_authorized = true 
                                  AND (LOWER(o.status) IN ('offline', 'los') OR CAST(o.rx_power AS DECIMAL) <= -28)
                                ORDER BY 
                                    CASE WHEN LOWER(o.status) = 'los' THEN 0 ELSE 1 END,
                                    o.rx_power ASC NULLS LAST
                                LIMIT 8
                            ")->fetchAll(PDO::FETCH_ASSOC);
                            ?>
                            <?php if (empty($issueONUs)): ?>
                            <div class="text-center py-4">
                                <i class="bi bi-check-circle display-4 text-success mb-2"></i>
                                <p class="text-muted mb-0">No issues detected</p>
                            </div>
                            <?php else: ?>
                            <?php foreach ($issueONUs as $issue): ?>
                            <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                <div>
                                    <div class="fw-medium small text-truncate" style="max-width: 150px;" title="<?= htmlspecialchars($issue['sn']) ?>">
                                        <?= htmlspecialchars($issue['description'] ?: $issue['sn']) ?>
                                    </div>
                                    <div class="text-muted" style="font-size: 0.7rem;"><?= htmlspecialchars($issue['sn']) ?></div>
                                </div>
                                <?php 
                                $isLos = strtolower($issue['status']) === 'los';
                                $rxPower = floatval($issue['rx_power'] ?? 0);
                                $badgeClass = $isLos ? 'bg-danger' : ($rxPower <= -28 ? 'bg-warning' : 'bg-secondary');
                                $badgeText = $isLos ? 'LOS' : (isset($issue['rx_power']) ? number_format($rxPower, 1) . ' dBm' : 'Offline');
                                ?>
                                <span class="issue-badge <?= $badgeClass ?> text-white"><?= $badgeText ?></span>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Auto-refresh script -->
            <script>
            (function() {
                let countdown = 60;
                const timerEl = document.getElementById('autoRefreshTimer');
                function updateTimer() {
                    if (timerEl) {
                        timerEl.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i>Auto-refresh in ' + countdown + 's';
                    }
                    countdown--;
                    if (countdown < 0) {
                        location.reload();
                    }
                }
                updateTimer();
                setInterval(updateTimer, 1000);
            })();
            </script>


            <?php elseif ($view === 'live_monitor'): ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0"><i class="bi bi-activity me-2"></i>Live ONU Monitor</h4>
                <div class="d-flex gap-2 align-items-center">
                    <select id="liveOltSelect" class="form-select form-select-sm" style="width: auto;">
                        <option value="">Select OLT</option>
                        <?php foreach ($olts as $olt): ?>
                        <option value="<?= $olt['id'] ?>" <?= $oltId == $olt['id'] ? 'selected' : '' ?>><?= htmlspecialchars($olt['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select id="liveSlotSelect" class="form-select form-select-sm" style="width: auto;">
                        <option value="">All Slots</option>
                        <?php for ($i = 0; $i <= 7; $i++): ?>
                        <option value="<?= $i ?>">Slot <?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                    <button class="btn btn-success btn-sm" id="btnStartMonitor">
                        <i class="bi bi-play-fill me-1"></i> Start Monitor
                    </button>
                    <button class="btn btn-danger btn-sm d-none" id="btnStopMonitor">
                        <i class="bi bi-stop-fill me-1"></i> Stop
                    </button>
                    <span id="monitorStatus" class="badge bg-secondary">Stopped</span>
                </div>
            </div>
            
            <div class="alert alert-info small">
                <i class="bi bi-info-circle me-1"></i>
                Live Monitor fetches real-time ONU data directly from the OLT including optical power levels.
                Initial load may take 30-60 seconds depending on the number of ONUs.
            </div>
            
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-router me-2"></i>Live ONU Status</span>
                    <span id="lastRefresh" class="text-muted small">Never refreshed</span>
                </div>
                <div class="card-body p-0">
                    <div id="liveOnuLoading" class="text-center p-5 d-none">
                        <div class="spinner-border text-primary mb-3" role="status"></div>
                        <div class="text-muted">Fetching live ONU data from OLT...</div>
                        <div class="text-muted small mt-1">This may take up to 60 seconds</div>
                    </div>
                    <div id="liveOnuEmpty" class="text-center text-muted p-5">
                        <i class="bi bi-router fs-1 mb-2 d-block"></i>
                        Select an OLT and click "Start Monitor" to view live ONU data
                    </div>
                    <div id="liveOnuTable" class="table-responsive d-none">
                        <table class="table table-hover table-sm mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>Serial Number</th>
                                    <th>Name</th>
                                    <th>Location</th>
                                    <th>Status</th>
                                    <th>RX Power</th>
                                    <th>TX Power</th>
                                    <th>Signal Quality</th>
                                </tr>
                            </thead>
                            <tbody id="liveOnuBody"></tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center">
                    <span id="onuCount" class="text-muted small">0 ONUs</span>
                    <span id="onlineCount" class="text-muted small">0 online</span>
                </div>
            </div>
            
            <script>
            (function() {
                let monitorInterval = null;
                const startBtn = document.getElementById('btnStartMonitor');
                const stopBtn = document.getElementById('btnStopMonitor');
                const oltSelect = document.getElementById('liveOltSelect');
                const slotSelect = document.getElementById('liveSlotSelect');
                const status = document.getElementById('monitorStatus');
                const loading = document.getElementById('liveOnuLoading');
                const empty = document.getElementById('liveOnuEmpty');
                const table = document.getElementById('liveOnuTable');
                const tbody = document.getElementById('liveOnuBody');
                const lastRefresh = document.getElementById('lastRefresh');
                const onuCount = document.getElementById('onuCount');
                const onlineCount = document.getElementById('onlineCount');
                
                function getSignalQuality(rxPower) {
                    if (rxPower === null) return { class: 'secondary', text: 'N/A', bars: 0 };
                    if (rxPower >= -20) return { class: 'success', text: 'Excellent', bars: 4 };
                    if (rxPower >= -24) return { class: 'success', text: 'Good', bars: 3 };
                    if (rxPower >= -27) return { class: 'warning', text: 'Fair', bars: 2 };
                    if (rxPower >= -30) return { class: 'danger', text: 'Weak', bars: 1 };
                    return { class: 'danger', text: 'Critical', bars: 0 };
                }
                
                function formatPower(power) {
                    if (power === null) return '<span class="text-muted">-</span>';
                    const cls = power <= -28 ? 'danger' : (power <= -25 ? 'warning' : 'success');
                    return `<span class="text-${cls}">${power.toFixed(2)} dBm</span>`;
                }
                
                function renderSignalBars(bars, colorClass) {
                    let html = '<div class="d-flex gap-1 align-items-end" style="height: 20px;">';
                    for (let i = 1; i <= 4; i++) {
                        const h = i * 4 + 2;
                        const active = i <= bars ? `bg-${colorClass}` : 'bg-secondary opacity-25';
                        html += `<div class="${active}" style="width: 4px; height: ${h}px; border-radius: 1px;"></div>`;
                    }
                    html += '</div>';
                    return html;
                }
                
                async function fetchLiveData() {
                    const oltId = oltSelect.value;
                    const slot = slotSelect.value;
                    
                    if (!oltId) {
                        alert('Please select an OLT');
                        stopMonitor();
                        return;
                    }
                    
                    loading.classList.remove('d-none');
                    empty.classList.add('d-none');
                    status.textContent = 'Fetching...';
                    status.className = 'badge bg-warning';
                    
                    try {
                        let url = `?page=api&action=huawei_live_onus&olt_id=${oltId}`;
                        if (slot !== '') url += `&slot=${slot}`;
                        
                        const resp = await fetch(url);
                        const data = await resp.json();
                        
                        if (data.success) {
                            renderOnus(data.onus);
                            lastRefresh.textContent = 'Updated: ' + new Date().toLocaleTimeString();
                            status.textContent = 'Live';
                            status.className = 'badge bg-success';
                        } else {
                            status.textContent = 'Error';
                            status.className = 'badge bg-danger';
                            console.error(data.error);
                        }
                    } catch (e) {
                        status.textContent = 'Error';
                        status.className = 'badge bg-danger';
                        console.error(e);
                    } finally {
                        loading.classList.add('d-none');
                    }
                }
                
                function renderOnus(onus) {
                    if (!onus || onus.length === 0) {
                        table.classList.add('d-none');
                        empty.classList.remove('d-none');
                        empty.innerHTML = '<i class="bi bi-inbox fs-1 mb-2 d-block"></i>No ONUs found';
                        onuCount.textContent = '0 ONUs';
                        onlineCount.textContent = '0 online';
                        return;
                    }
                    
                    table.classList.remove('d-none');
                    empty.classList.add('d-none');
                    
                    const online = onus.filter(o => o.status === 'online').length;
                    onuCount.textContent = onus.length + ' ONUs';
                    onlineCount.textContent = online + ' online';
                    
                    tbody.innerHTML = onus.map(onu => {
                        const statusCfg = {
                            online: { class: 'success', icon: 'check-circle-fill' },
                            offline: { class: 'secondary', icon: 'circle' },
                            los: { class: 'danger', icon: 'exclamation-triangle-fill' }
                        };
                        const st = statusCfg[onu.status] || statusCfg.offline;
                        const sig = getSignalQuality(onu.rx_power);
                        const loc = `${onu.frame}/${onu.slot}/${onu.port}:${onu.onu_id}`;
                        
                        return `<tr>
                            <td><code>${onu.sn}</code></td>
                            <td>${onu.name || '<span class="text-muted">-</span>'}</td>
                            <td><small>${loc}</small></td>
                            <td><span class="badge bg-${st.class}"><i class="bi bi-${st.icon} me-1"></i>${onu.status}</span></td>
                            <td>${formatPower(onu.rx_power)}</td>
                            <td>${formatPower(onu.tx_power)}</td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    ${renderSignalBars(sig.bars, sig.class)}
                                    <small class="text-${sig.class}">${sig.text}</small>
                                </div>
                            </td>
                        </tr>`;
                    }).join('');
                }
                
                function startMonitor() {
                    if (!oltSelect.value) {
                        alert('Please select an OLT first');
                        return;
                    }
                    startBtn.classList.add('d-none');
                    stopBtn.classList.remove('d-none');
                    fetchLiveData();
                    // Auto-refresh every 30 seconds for real-time monitoring
                    monitorInterval = setInterval(fetchLiveData, 30000);
                }
                
                function stopMonitor() {
                    if (monitorInterval) {
                        clearInterval(monitorInterval);
                        monitorInterval = null;
                    }
                    startBtn.classList.remove('d-none');
                    stopBtn.classList.add('d-none');
                    status.textContent = 'Stopped';
                    status.className = 'badge bg-secondary';
                }
                
                startBtn.addEventListener('click', startMonitor);
                stopBtn.addEventListener('click', stopMonitor);
            })();
            </script>
            
            <?php elseif ($view === 'olts'): ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0"><i class="bi bi-hdd-rack me-2"></i>OLT Devices</h4>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#oltModal" onclick="resetOltForm()">
                    <i class="bi bi-plus-circle me-1"></i> Add OLT
                </button>
            </div>
            
            <?php if (empty($olts)): ?>
            <div class="card shadow-sm">
                <div class="card-body text-center py-5">
                    <i class="bi bi-hdd-rack fs-1 text-muted mb-3 d-block"></i>
                    <h5>No OLTs Configured</h5>
                    <p class="text-muted">Add your first OLT device to start managing your fiber network.</p>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#oltModal">
                        <i class="bi bi-plus-circle me-1"></i> Add OLT
                    </button>
                </div>
            </div>
            <?php else: ?>
            <div class="row g-4">
                <?php foreach ($olts as $olt): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card shadow-sm olt-card <?= $olt['is_active'] ? '' : 'offline' ?>">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h5 class="mb-1"><?= htmlspecialchars($olt['name']) ?></h5>
                                    <code class="small"><?= htmlspecialchars($olt['ip_address']) ?>:<?= $olt['port'] ?></code>
                                </div>
                                <?php if ($olt['is_active']): ?>
                                <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                <span class="badge bg-secondary">Inactive</span>
                                <?php endif; ?>
                            </div>
                            <div class="row text-center mb-3">
                                <div class="col-4">
                                    <div class="small text-muted">Type</div>
                                    <div class="fw-bold"><?= ucfirst($olt['connection_type']) ?></div>
                                </div>
                                <div class="col-4">
                                    <div class="small text-muted">Vendor</div>
                                    <div class="fw-bold"><?= htmlspecialchars($olt['vendor'] ?: 'Huawei') ?></div>
                                </div>
                                <div class="col-4">
                                    <div class="small text-muted">Model</div>
                                    <div class="fw-bold"><?= htmlspecialchars($olt['model'] ?: '-') ?></div>
                                </div>
                            </div>
                            <div class="d-flex gap-2">
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="action" value="test_connection">
                                    <input type="hidden" name="id" value="<?= $olt['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-primary" title="Test Connection">
                                        <i class="bi bi-plug"></i>
                                    </button>
                                </form>
                                <button type="button" class="btn btn-sm btn-outline-warning" onclick="editOlt(<?= htmlspecialchars(json_encode($olt)) ?>)" title="Edit OLT">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <a href="?page=huawei-olt&view=onus&olt_id=<?= $olt['id'] ?>" class="btn btn-sm btn-primary">
                                    <i class="bi bi-diagram-3 me-1"></i> ONUs
                                </a>
                                <a href="?page=huawei-olt&view=olt_detail&olt_id=<?= $olt['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Settings">
                                    <i class="bi bi-gear"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <?php elseif ($view === 'onus'): ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-0">
                        <i class="bi bi-<?= isset($_GET['unconfigured']) ? 'hourglass-split' : 'check-circle' ?> me-2"></i>
                        <?= isset($_GET['unconfigured']) ? 'Pending Authorization' : 'Authorized ONUs' ?>
                    </h4>
                    <div class="d-flex align-items-center gap-2 mt-1">
                        <span class="live-indicator"><span class="live-dot"></span> Live</span>
                        <small id="realtime-indicator" class="text-muted">Auto-refreshing...</small>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <form class="d-flex gap-2" method="get">
                        <input type="hidden" name="page" value="huawei-olt">
                        <input type="hidden" name="view" value="onus">
                        <?php if (isset($_GET['unconfigured'])): ?>
                        <input type="hidden" name="unconfigured" value="1">
                        <?php endif; ?>
                        <select name="olt_id" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">All OLTs</option>
                            <?php foreach ($olts as $olt): ?>
                            <option value="<?= $olt['id'] ?>" <?= $oltId == $olt['id'] ? 'selected' : '' ?>><?= htmlspecialchars($olt['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">All Status</option>
                            <option value="online" <?= ($_GET['status'] ?? '') === 'online' ? 'selected' : '' ?>>Online</option>
                            <option value="offline" <?= ($_GET['status'] ?? '') === 'offline' ? 'selected' : '' ?>>Offline</option>
                            <option value="los" <?= ($_GET['status'] ?? '') === 'los' ? 'selected' : '' ?>>LOS</option>
                        </select>
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="Search SN/Name..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                        <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-search"></i></button>
                    </form>
                    <?php if (isset($_GET['unconfigured'])): ?>
                        <?php if ($oltId): ?>
                        <form method="post" class="d-inline">
                            <input type="hidden" name="action" value="discover_unconfigured">
                            <input type="hidden" name="olt_id" value="<?= $oltId ?>">
                            <button type="submit" class="btn btn-warning btn-sm" onclick="showLoading('Discovering unconfigured ONUs...')">
                                <i class="bi bi-search me-1"></i> Discover ONUs
                            </button>
                        </form>
                        <?php else: ?>
                        <form method="post" class="d-inline">
                            <input type="hidden" name="action" value="discover_all_unconfigured">
                            <button type="submit" class="btn btn-warning btn-sm" onclick="showLoading('Discovering from all OLTs... This may take a while.')">
                                <i class="bi bi-broadcast me-1"></i> Discover All ONUs
                            </button>
                        </form>
                        <?php endif; ?>
                        <form method="post" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="action" value="cleanup_stale_pending">
                            <input type="hidden" name="hours_old" value="2">
                            <button type="submit" class="btn btn-outline-secondary btn-sm" onclick="return confirm('Clear stale pending entries older than 2 hours?')" title="Remove stale entries that no longer exist on OLT">
                                <i class="bi bi-trash me-1"></i> Clear Stale
                            </button>
                        </form>
                    <?php endif; ?>
                    <a href="?page=huawei-olt&ajax=export_csv<?= $oltId ? '&olt_id=' . $oltId : '' ?>" class="btn btn-outline-success btn-sm" title="Export to CSV">
                        <i class="bi bi-download me-1"></i> Export CSV
                    </a>
                    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#onuModal" onclick="resetOnuForm()">
                        <i class="bi bi-plus-circle me-1"></i> Add ONU
                    </button>
                    <?php if ($oltId): ?>
                    <div class="btn-group">
                        <form method="post" class="d-inline">
                            <input type="hidden" name="action" value="sync_cli">
                            <input type="hidden" name="olt_id" value="<?= $oltId ?>">
                            <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('Sync ONUs from OLT? This reads configuration and optical power levels.')">
                                <i class="bi bi-arrow-repeat me-1"></i> Sync from OLT
                            </button>
                        </form>
                        <div class="btn-group">
                            <button type="button" class="btn btn-outline-primary btn-sm dropdown-toggle" data-bs-toggle="dropdown" title="Refresh optical power">
                                <i class="bi bi-reception-4"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <form method="post">
                                        <input type="hidden" name="action" value="refresh_all_optical_cli">
                                        <input type="hidden" name="olt_id" value="<?= $oltId ?>">
                                        <button type="submit" class="dropdown-item" onclick="return confirm('Sync optical power via CLI? This is slower but uses Telnet.')">
                                            <i class="bi bi-terminal me-2"></i> CLI Sync (RX/TX)
                                        </button>
                                    </form>
                                </li>
                                <li>
                                    <form method="post">
                                        <input type="hidden" name="action" value="refresh_all_optical_snmp">
                                        <input type="hidden" name="olt_id" value="<?= $oltId ?>">
                                        <button type="submit" class="dropdown-item" onclick="return confirm('Sync optical power via SNMP? This is faster and includes distance data.')">
                                            <i class="bi bi-hdd-network me-2"></i> SNMP Sync (RX/TX/Distance)
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                        <div class="btn-group">
                            <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                                <i class="bi bi-three-dots"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <form method="post">
                                        <input type="hidden" name="action" value="import_smartolt">
                                        <input type="hidden" name="olt_id" value="<?= $oltId ?>">
                                        <button type="submit" class="dropdown-item" onclick="return confirm('Import ONUs from SmartOLT?')">
                                            <i class="bi bi-cloud-download me-2"></i> Import from SmartOLT
                                        </button>
                                    </form>
                                </li>
                                <li>
                                    <form method="post">
                                        <input type="hidden" name="action" value="mark_all_authorized">
                                        <input type="hidden" name="olt_id" value="<?= $oltId ?>">
                                        <button type="submit" class="dropdown-item" onclick="return confirm('Mark all ONUs as authorized?')">
                                            <i class="bi bi-check-all me-2"></i> Mark All Authorized
                                        </button>
                                    </form>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form method="post">
                                        <input type="hidden" name="action" value="sync_onus_snmp">
                                        <input type="hidden" name="olt_id" value="<?= $oltId ?>">
                                        <button type="submit" class="dropdown-item" onclick="return confirm('Sync via SNMP only?')">
                                            <i class="bi bi-broadcast me-2"></i> Sync via SNMP
                                        </button>
                                    </form>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form method="post">
                                        <input type="hidden" name="action" value="delete_all_onus">
                                        <input type="hidden" name="olt_id" value="<?= $oltId ?>">
                                        <button type="submit" class="dropdown-item text-danger" onclick="return confirm('DELETE ALL ONUs for this OLT? This cannot be undone!')">
                                            <i class="bi bi-trash me-2"></i> Delete All ONUs
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <?php if (empty($onus) && empty($discoveredOnus)): ?>
                    <div class="p-4 text-center text-muted">
                        <i class="bi bi-inbox fs-1 mb-2 d-block"></i>
                        No ONUs found
                    </div>
                    <?php elseif (empty($onus) && !empty($discoveredOnus)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Serial Number</th>
                                    <th>ONU Type</th>
                                    <th>OLT / Port</th>
                                    <th>First Seen</th>
                                    <th>Last Seen</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($discoveredOnus as $disc): ?>
                                <tr>
                                    <td>
                                        <code><?= htmlspecialchars($disc['serial_number']) ?></code>
                                        <?php if (!empty($disc['equipment_id'])): ?>
                                        <br><small class="text-muted"><?= htmlspecialchars($disc['equipment_id']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($disc['onu_model'])): ?>
                                        <span class="badge bg-info">
                                            <i class="bi bi-router me-1"></i><?= htmlspecialchars($disc['onu_model']) ?>
                                        </span>
                                        <?php elseif (!empty($disc['equipment_id'])): ?>
                                        <span class="badge bg-secondary"><?= htmlspecialchars($disc['equipment_id']) ?></span>
                                        <?php else: ?>
                                        <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="text-muted"><?= htmlspecialchars($disc['olt_name'] ?? '-') ?></span>
                                        <br><small><?= htmlspecialchars($disc['frame_slot_port'] ?? '-') ?></small>
                                    </td>
                                    <td>
                                        <small><?= date('Y-m-d H:i', strtotime($disc['first_seen_at'])) ?></small>
                                    </td>
                                    <td>
                                        <small><?= date('Y-m-d H:i', strtotime($disc['last_seen_at'])) ?></small>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-success" 
                                            onclick="openAuthModal('<?= htmlspecialchars($disc['serial_number']) ?>', '<?= $disc['olt_id'] ?>', '<?= htmlspecialchars($disc['frame_slot_port'] ?? '') ?>', '<?= $disc['onu_type_id'] ?? '' ?>')">
                                            <i class="bi bi-check-lg"></i> Authorize
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="onuTable">
                            <thead>
                                <tr>
                                    <th>Serial Number</th>
                                    <th>ONU Type</th>
                                    <th>Name</th>
                                    <th>Zone</th>
                                    <th>VLAN</th>
                                    <th>Management IP</th>
                                    <th>Status</th>
                                    <th>Signal (RX/TX)</th>
                                    <th>Distance</th>
                                    <th style="width: 80px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($onus as $onu): ?>
                                <tr data-onu-id="<?= $onu['id'] ?>">
                                    <td>
                                        <a href="?page=huawei-olt&view=onu_detail&onu_id=<?= $onu['id'] ?>" class="text-decoration-none">
                                            <code><?= htmlspecialchars($onu['sn']) ?></code>
                                        </a>
                                    </td>
                                    <td>
                                        <?php 
                                        $typeName = $onu['onu_type_model'] ?? null;
                                        $rawType = $onu['onu_type'] ?? $onu['discovered_eqid'] ?? null;
                                        if ($typeName): ?>
                                            <span class="badge bg-info"><?= htmlspecialchars($typeName) ?></span>
                                        <?php elseif ($rawType): ?>
                                            <span class="badge bg-secondary"><?= htmlspecialchars($rawType) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($onu['name'] ?: '-') ?></td>
                                    <td><?= htmlspecialchars($onu['zone_name'] ?? '-') ?></td>
                                    <td>
                                        <?php if (!empty($onu['vlan_id'])): ?>
                                            <span class="badge bg-primary"><?= $onu['vlan_id'] ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($onu['tr069_ip'])): ?>
                                            <code class="text-success"><?= htmlspecialchars($onu['tr069_ip']) ?></code>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $statusConfig = [
                                            'online' => ['class' => 'success', 'icon' => 'check-circle-fill', 'label' => 'Online'],
                                            'offline' => ['class' => 'secondary', 'icon' => 'circle', 'label' => 'Offline'],
                                            'los' => ['class' => 'danger', 'icon' => 'exclamation-triangle-fill', 'label' => 'LOS'],
                                            'power_fail' => ['class' => 'warning', 'icon' => 'lightning-fill', 'label' => 'Power Fail'],
                                            'dyinggasp' => ['class' => 'warning', 'icon' => 'lightning-fill', 'label' => 'Dying Gasp'],
                                        ];
                                        $status = strtolower($onu['status'] ?? 'offline');
                                        // Apply same status override logic as config page
                                        $snmpFaultStatuses = ['los', 'dying-gasp', 'dyinggasp', 'offline', 'power_fail'];
                                        if (!in_array($status, $snmpFaultStatuses) && $status !== 'online') {
                                            $isOnline = false;
                                            if (!empty($onu['snmp_status']) && $onu['snmp_status'] === 'online') $isOnline = true;
                                            elseif (!empty($onu['rx_power']) && $onu['rx_power'] > -40) $isOnline = true;
                                            elseif (!empty($onu['tr069_last_inform']) && strtotime($onu['tr069_last_inform']) >= time() - 300) $isOnline = true;
                                            if ($isOnline) $status = 'online';
                                        }
                                        $cfg = $statusConfig[$status] ?? ['class' => 'secondary', 'icon' => 'question-circle', 'label' => ucfirst($status)];
                                        ?>
                                        <span class="badge bg-<?= $cfg['class'] ?>">
                                            <i class="bi bi-<?= $cfg['icon'] ?> me-1"></i><?= $cfg['label'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $rx = $onu['rx_power'] !== null ? (float)$onu['rx_power'] : null;
                                        $tx = $onu['tx_power'] !== null ? (float)$onu['tx_power'] : null;
                                        $rxClass = 'success';
                                        if ($rx !== null) {
                                            if ($rx <= -28) $rxClass = 'danger';
                                            elseif ($rx <= -25) $rxClass = 'warning';
                                        }
                                        ?>
                                        <span class="signal-<?= $rxClass ?>"><?= $rx !== null ? number_format($rx, 1) : '-' ?></span>
                                        / <?= $tx !== null ? number_format($tx, 1) : '-' ?> dBm
                                    </td>
                                    <td>
                                        <?php 
                                        $distance = isset($onu['distance']) && $onu['distance'] !== null ? (float)$onu['distance'] : null;
                                        if ($distance !== null): 
                                            if ($distance >= 1000): ?>
                                                <?= number_format($distance / 1000, 2) ?> km
                                            <?php else: ?>
                                                <?= $distance ?> m
                                            <?php endif; ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-dark" onclick="showTR069LogsModal(<?= $onu['id'] ?>, '<?= htmlspecialchars($onu['sn'] ?? '') ?>')" title="TR-069 Logs">
                                                <i class="bi bi-journal-text"></i>
                                            </button>
                                            <button class="btn btn-outline-primary" onclick="rebootOnu(<?= $onu['id'] ?>)" title="Reboot ONU">
                                                <i class="bi bi-arrow-clockwise"></i>
                                            </button>
                                            <button class="btn btn-outline-danger" onclick="deleteOnu(<?= $onu['id'] ?>, '<?= htmlspecialchars($onu['sn'] ?? '') ?>')" title="Delete ONU">
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
            
            <!-- Realtime ONU list updates -->
            
            <script>
            (function() {
                const isUnconfigured = <?= isset($_GET['unconfigured']) ? 'true' : 'false' ?>;
                const oltId = <?= $oltId ? $oltId : 'null' ?>;
                const searchParam = '<?= htmlspecialchars($_GET['search'] ?? '') ?>';
                const statusParam = '<?= htmlspecialchars($_GET['status'] ?? '') ?>';
                let isDiscovering = false;
                let realtimeInterval = null;
                const REFRESH_INTERVAL = 45000; // 45 seconds for ONU list (reduced to prevent hangs)
                
                function showToast(message, type) {
                    const toast = document.createElement('div');
                    toast.className = 'position-fixed bottom-0 end-0 p-3';
                    toast.style.zIndex = '9999';
                    toast.innerHTML = `
                        <div class="toast show align-items-center text-white bg-${type} border-0" role="alert">
                            <div class="d-flex">
                                <div class="toast-body">${message}</div>
                                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                            </div>
                        </div>`;
                    document.body.appendChild(toast);
                    setTimeout(() => toast.remove(), 5000);
                }
                
                // Realtime ONU status updates
                async function fetchRealtimeONUs() {
                    if (document.hidden || window._fetchingONUs) return;
                    window._fetchingONUs = true;
                    try {
                        let url = '?page=huawei-olt&ajax=realtime_onus';
                        if (oltId) url += '&olt_id=' + oltId;
                        if (isUnconfigured) url += '&unconfigured=1';
                        if (searchParam) url += '&search=' + encodeURIComponent(searchParam);
                        if (statusParam) url += '&status=' + encodeURIComponent(statusParam);
                        
                        const resp = await fetch(url);
                        const data = await resp.json();
                        
                        if (data.success) {
                            updateONUStatuses(data.onus);
                            updateRealtimeIndicator(data.timestamp);
                        }
                    } catch (e) {
                        console.error('Realtime ONU error:', e);
                    } finally {
                        window._fetchingONUs = false;
                    }
                }
                
                function updateONUStatuses(onus) {
                    const statusMap = {};
                    onus.forEach(onu => {
                        statusMap[onu.id] = onu;
                    });
                    
                    // Update status badges in the table
                    document.querySelectorAll('tr[data-onu-id]').forEach(row => {
                        const onuId = row.getAttribute('data-onu-id');
                        const onu = statusMap[onuId];
                        if (!onu) return;
                        
                        const statusBadge = row.querySelector('.badge');
                        if (statusBadge) {
                            const newStatus = onu.status || 'offline';
                            const statusCfg = {
                                online: { class: 'bg-success', icon: 'check-circle-fill' },
                                offline: { class: 'bg-secondary', icon: 'circle' },
                                los: { class: 'bg-danger', icon: 'exclamation-triangle-fill' }
                            };
                            const cfg = statusCfg[newStatus] || statusCfg.offline;
                            
                            // Check if status changed
                            if (!statusBadge.classList.contains(cfg.class)) {
                                statusBadge.className = 'badge ' + cfg.class;
                                statusBadge.innerHTML = `<i class="bi bi-${cfg.icon} me-1"></i>${newStatus}`;
                                row.classList.add('row-updated');
                                setTimeout(() => row.classList.remove('row-updated'), 1000);
                            }
                        }
                        
                        // Update power levels
                        const rxCell = row.querySelector('[data-rx-power]');
                        const txCell = row.querySelector('[data-tx-power]');
                        if (rxCell && onu.rx_power !== null) {
                            const newRx = parseFloat(onu.rx_power).toFixed(1) + ' dBm';
                            if (rxCell.textContent !== newRx) {
                                rxCell.textContent = newRx;
                            }
                        }
                        if (txCell && onu.tx_power !== null) {
                            const newTx = parseFloat(onu.tx_power).toFixed(1) + ' dBm';
                            if (txCell.textContent !== newTx) {
                                txCell.textContent = newTx;
                            }
                        }
                    });
                }
                
                function updateRealtimeIndicator(timestamp) {
                    const indicator = document.getElementById('realtime-indicator');
                    if (indicator) {
                        indicator.textContent = 'Updated: ' + new Date(timestamp).toLocaleTimeString();
                        indicator.classList.add('pulse-once');
                        setTimeout(() => indicator.classList.remove('pulse-once'), 500);
                    }
                }
                
                // Manual discovery function (called by Discover button)
                window.runManualDiscovery = function(btn) {
                    if (isDiscovering) return;
                    isDiscovering = true;
                    
                    const origHtml = btn.innerHTML;
                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Discovering...';
                    
                    const url = '?page=huawei-olt&ajax=discover_onus' + (oltId ? '&olt_id=' + oltId : '');
                    fetch(url)
                        .then(r => r.json())
                        .then(data => {
                            isDiscovering = false;
                            btn.disabled = false;
                            btn.innerHTML = origHtml;
                            
                            if (data.success) {
                                showToast('Discovery complete: ' + data.message, 'success');
                                if (data.count > 0) {
                                    setTimeout(() => window.location.reload(), 1000);
                                }
                            } else {
                                showToast('Discovery failed: ' + (data.error || 'Unknown error'), 'danger');
                            }
                        })
        .catch(err => {
                            isDiscovering = false;
                            btn.disabled = false;
                            btn.innerHTML = origHtml;
                            showToast('Discovery error: ' + err.message, 'danger');
                        });
                };
                
                // Manual refresh function
                window.refreshPage = function() {
                    window.location.reload();
                };
                
                // Start realtime polling
                realtimeInterval = setInterval(fetchRealtimeONUs, REFRESH_INTERVAL);
                
                // Cleanup on page unload
                window.addEventListener('beforeunload', () => {
                    if (realtimeInterval) clearInterval(realtimeInterval);
                });
                
                // ==================== Bulk Operations ====================
                const selectAllCheckbox = document.getElementById('selectAllOnus');
                const bulkActionBar = document.getElementById('bulkActionBar');
                const selectedCountEl = document.getElementById('selectedCount');
                
                function getSelectedONUs() {
                    return Array.from(document.querySelectorAll('.onu-checkbox:checked')).map(cb => parseInt(cb.value));
                }
                
                function updateBulkActionBar() {
                    const selected = getSelectedONUs();
                    if (bulkActionBar && selectedCountEl) {
                        selectedCountEl.textContent = selected.length;
                        bulkActionBar.classList.toggle('d-none', selected.length === 0);
                    }
                }
                
                if (selectAllCheckbox) {
                    selectAllCheckbox.addEventListener('change', function() {
                        document.querySelectorAll('.onu-checkbox').forEach(cb => cb.checked = this.checked);
                        updateBulkActionBar();
                    });
                }
                
                document.querySelectorAll('.onu-checkbox').forEach(cb => {
                    cb.addEventListener('change', updateBulkActionBar);
                });
                
                window.clearSelection = function() {
                    document.querySelectorAll('.onu-checkbox').forEach(cb => cb.checked = false);
                    if (selectAllCheckbox) selectAllCheckbox.checked = false;
                    updateBulkActionBar();
                };
                
                window.bulkReboot = async function() {
                    const onuIds = getSelectedONUs();
                    if (!onuIds.length) return;
                    if (!confirm(`Reboot ${onuIds.length} ONU(s)?`)) return;
                    
                    showToast(`Rebooting ${onuIds.length} ONU(s)...`, 'info');
                    try {
                        const resp = await fetch('?page=huawei-olt&ajax=bulk_reboot', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({onu_ids: onuIds})
                        });
                        const data = await resp.json();
                        if (data.success) {
                            showToast(`Rebooted: ${data.result.success} success, ${data.result.failed} failed`, 
                                data.result.failed > 0 ? 'warning' : 'success');
                        }
                    } catch (e) {
                        showToast('Bulk reboot failed: ' + e.message, 'danger');
                    }
                    clearSelection();
                };
                
                window.bulkDelete = async function() {
                    const onuIds = getSelectedONUs();
                    if (!onuIds.length) return;
                    if (!confirm(`DELETE ${onuIds.length} ONU(s)? This cannot be undone!`)) return;
                    
                    showToast(`Deleting ${onuIds.length} ONU(s)...`, 'warning');
                    try {
                        const resp = await fetch('?page=huawei-olt&ajax=bulk_delete', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({onu_ids: onuIds})
                        });
                        const data = await resp.json();
                        if (data.success) {
                            showToast(`Deleted: ${data.result.success} success, ${data.result.failed} failed`, 'success');
                            setTimeout(() => location.reload(), 1500);
                        }
                    } catch (e) {
                        showToast('Bulk delete failed: ' + e.message, 'danger');
                    }
                };
                
                window.bulkRefreshSignal = function() {
                    const onuIds = getSelectedONUs();
                    if (!onuIds.length) return;
                    showToast(`Use "Sync from OLT" to refresh signals for all ONUs`, 'info');
                };
                
                // ==================== Browser Notifications ====================
                let lastPendingCount = <?= ($stats['unconfigured_onus'] ?? 0) + ($stats['discovered_onus'] ?? 0) ?>;
                let notificationsEnabled = false;
                
                async function requestNotificationPermission() {
                    if ('Notification' in window && Notification.permission === 'default') {
                        const permission = await Notification.requestPermission();
                        notificationsEnabled = permission === 'granted';
                    } else {
                        notificationsEnabled = Notification.permission === 'granted';
                    }
                }
                
                function sendBrowserNotification(title, body, icon = 'bi-bell') {
                    if (!notificationsEnabled) return;
                    try {
                        new Notification(title, { body, icon: '/favicon.ico' });
                    } catch (e) { console.warn('Notification failed:', e); }
                }
                
                // Check for new pending ONUs
                async function checkForNewPending() {
                    try {
                        const resp = await fetch('?page=huawei-olt&ajax=realtime_stats');
                        const data = await resp.json();
                        if (data.success) {
                            const newPending = (data.stats.unconfigured_onus || 0) + (data.stats.discovered_onus || 0);
                            if (newPending > lastPendingCount) {
                                const diff = newPending - lastPendingCount;
                                sendBrowserNotification('New ONU Discovered!', 
                                    `${diff} new ONU(s) waiting for authorization`);
                                showToast(`${diff} new ONU(s) discovered!`, 'warning');
                            }
                            lastPendingCount = newPending;
                        }
                    } catch (e) {}
                }
                
                requestNotificationPermission();
                setInterval(checkForNewPending, 30000);
                
                // ==================== Keyboard Shortcuts ====================
                document.addEventListener('keydown', function(e) {
                    // Ctrl+/ or ? for help
                    if ((e.ctrlKey && e.key === '/') || (e.shiftKey && e.key === '?')) {
                        e.preventDefault();
                        showKeyboardShortcuts();
                    }
                    // Escape to clear selection
                    if (e.key === 'Escape') {
                        clearSelection();
                    }
                    // Ctrl+A to select all (when not in input)
                    if (e.ctrlKey && e.key === 'a' && !['INPUT', 'TEXTAREA'].includes(e.target.tagName)) {
                        e.preventDefault();
                        if (selectAllCheckbox) {
                            selectAllCheckbox.checked = true;
                            selectAllCheckbox.dispatchEvent(new Event('change'));
                        }
                    }
                    // R to refresh (when not in input)
                    if (e.key === 'r' && !e.ctrlKey && !['INPUT', 'TEXTAREA'].includes(e.target.tagName)) {
                        e.preventDefault();
                        location.reload();
                    }
                });
                
                function showKeyboardShortcuts() {
                    const modal = document.createElement('div');
                    modal.className = 'modal fade show';
                    modal.style.display = 'block';
                    modal.style.backgroundColor = 'rgba(0,0,0,0.5)';
                    modal.innerHTML = `
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title"><i class="bi bi-keyboard me-2"></i>Keyboard Shortcuts</h5>
                                    <button type="button" class="btn-close" onclick="this.closest('.modal').remove()"></button>
                                </div>
                                <div class="modal-body">
                                    <table class="table table-sm mb-0">
                                        <tr><td><kbd>Ctrl</kbd> + <kbd>A</kbd></td><td>Select all ONUs</td></tr>
                                        <tr><td><kbd>Esc</kbd></td><td>Clear selection</td></tr>
                                        <tr><td><kbd>R</kbd></td><td>Refresh page</td></tr>
                                        <tr><td><kbd>?</kbd></td><td>Show this help</td></tr>
                                    </table>
                                </div>
                            </div>
                        </div>`;
                    document.body.appendChild(modal);
                    modal.addEventListener('click', function(e) {
                        if (e.target === modal) modal.remove();
                    });
                }
            })();
            </script>
            <style>
            .row-updated {
                animation: rowFlash 0.5s ease-out;
            }
            @keyframes rowFlash {
                0% { background-color: rgba(25, 135, 84, 0.2); }
                100% { background-color: transparent; }
            }
            #bulkActionBar {
                position: sticky;
                top: 0;
                z-index: 100;
                border-radius: 0.5rem 0.5rem 0 0;
            }
            </style>
            
            <?php elseif ($view === 'onu_detail' && $currentOnu): ?>
            <?php
            // Get provisioning stage info
            $provisioningStage = (int)($currentOnu['provisioning_stage'] ?? 0);
            $rx = isset($currentOnu['rx_power']) && $currentOnu['rx_power'] !== null && $currentOnu['rx_power'] !== '' ? (float)$currentOnu['rx_power'] : null;
            $tx = isset($currentOnu['tx_power']) && $currentOnu['tx_power'] !== null && $currentOnu['tx_power'] !== '' ? (float)$currentOnu['tx_power'] : null;
            $distance = isset($currentOnu['distance']) && $currentOnu['distance'] !== null && $currentOnu['distance'] !== '' ? (float)$currentOnu['distance'] : null;
            $distanceDisplay = $distance !== null ? ($distance >= 1000 ? number_format($distance/1000, 2).'km' : $distance.'m') : '-';
            $rxClass = 'success';
            $rxLabel = 'Excellent';
            if ($rx !== null) {
                if ($rx <= -28) { $rxClass = 'danger'; $rxLabel = 'Critical'; }
                elseif ($rx <= -25) { $rxClass = 'warning'; $rxLabel = 'Fair'; }
                elseif ($rx <= -20) { $rxClass = 'success'; $rxLabel = 'Good'; }
            }
            $statusColors = ['online' => 'success', 'offline' => 'secondary', 'los' => 'danger'];
            $statusIcons = ['online' => 'check-circle-fill', 'offline' => 'x-circle', 'los' => 'exclamation-triangle-fill'];
            ?>
            
            <style>
            .onu-status-bar { 
                border-radius: 0.75rem; 
                overflow: hidden;
                box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            }
            .onu-status-bar.online { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
            .onu-status-bar.offline { background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%); }
            .onu-status-bar.los { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); }
            .onu-status-bar .status-icon { font-size: 2.5rem; opacity: 0.9; }
            .onu-status-bar .status-label { font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.9; }
            .onu-status-bar .status-value { font-size: 0.9rem; font-weight: 600; word-break: break-all; }
            .onu-info-card { background: #fff; border-radius: 0.75rem; border: 1px solid #e2e8f0; }
            .onu-info-card .info-label { font-size: 0.75rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
            .onu-info-card .info-value { font-size: 0.95rem; font-weight: 500; color: #1e293b; word-break: break-word; }
            .action-toolbar { background: #f8fafc; border-radius: 0.75rem; border: 1px solid #e2e8f0; }
            .action-toolbar .btn { font-size: 0.75rem; padding: 0.4rem 0.6rem; }
            .signal-meter { height: 6px; border-radius: 3px; background: #fff;
    border: 1px solid #3b82f6 !important;
    color: #3b82f6; overflow: hidden; }
            .signal-meter-fill { height: 100%; border-radius: 3px; }
            .data-table { font-size: 0.85rem; }
            .data-table th { font-weight: 600; color: #64748b; font-size: 0.75rem; text-transform: uppercase; }
            .section-header { font-size: 0.9rem; font-weight: 600; color: #374151; border-bottom: 2px solid #3b82f6; padding-bottom: 0.5rem; }
            .pulse-online { animation: pulse-green 2s infinite; }
            @keyframes pulse-green { 0%, 100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4); } 50% { box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); } }
            .danger-zone { border: 1px solid #fee2e2; background: #fef2f2; border-radius: 0.75rem; }
            
            /* Mobile responsive styles */
            @media (max-width: 768px) {
                .onu-status-bar { padding: 0.75rem !important; }
                .onu-status-bar .status-icon { font-size: 1.5rem; }
                .onu-status-bar .status-label { font-size: 0.6rem; }
                .onu-status-bar .status-value { font-size: 0.75rem; }
                .onu-status-bar .border-end { border: none !important; }
                .onu-status-bar .py-2 { padding-top: 0.25rem !important; padding-bottom: 0.25rem !important; }
                .action-toolbar .btn { font-size: 0.7rem; padding: 0.3rem 0.5rem; }
                .action-toolbar .btn .me-1 { margin-right: 0 !important; }
                .action-toolbar .btn-text { display: none; }
                .onu-info-card { margin-bottom: 0.5rem; }
                .onu-info-card .section-header { font-size: 0.8rem; }
                .signal-card .display-6 { font-size: 1.5rem !important; }
                .signal-card .border-start { border: none !important; padding-top: 0.5rem; }
                .hide-mobile { display: none !important; }
            }
            @media (max-width: 576px) {
                .onu-status-bar .col-auto.text-center { display: none; }
                .action-toolbar { overflow-x: auto; white-space: nowrap; -webkit-overflow-scrolling: touch; }
                .action-toolbar .d-flex { flex-wrap: nowrap !important; }
            }
            </style>
            
            <?php 
            // Calculate uptime for display
            $uptimeDisplay = '';
            if (!empty($currentOnu['online_since']) && $currentOnu['status'] === 'online') {
                $onlineSince = new DateTime($currentOnu['online_since']);
                $now = new DateTime();
                $diff = $now->diff($onlineSince);
                if ($diff->d > 0) {
                    $uptimeDisplay = $diff->d . 'd ' . $diff->h . 'h ' . $diff->i . 'm';
                } elseif ($diff->h > 0) {
                    $uptimeDisplay = $diff->h . 'h ' . $diff->i . 'm';
                } else {
                    $uptimeDisplay = $diff->i . 'm';
                }
            }
            ?>
            
            <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $messageType ?? 'success' ?> alert-dismissible fade show mb-3" role="alert">
                <i class="bi bi-<?= $messageType === 'success' ? 'check-circle' : ($messageType === 'danger' ? 'x-circle' : 'info-circle') ?> me-2"></i>
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <!-- Simple Back Button -->
            <div class="mb-3">
                <a href="?page=huawei-olt&view=onus<?= $currentOnu['olt_id'] ? '&olt_id='.$currentOnu['olt_id'] : '' ?>" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Back to ONU List
                </a>
            </div>
            
            <?php 
            $attachedVlans = [];
            if (!empty($currentOnu['attached_vlans'])) {
                $attachedVlans = json_decode($currentOnu['attached_vlans'], true) ?: [];
            } elseif (!empty($currentOnu['vlan_id'])) {
                $attachedVlans = [(int)$currentOnu['vlan_id']];
            }
            ?>
            
            <!-- Action Toolbar - Separated into OLT and TR-069 sections -->
            <div class="action-toolbar p-2 mb-3">
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <!-- OLT Operations -->
                    <div class="btn-group btn-group-sm" role="group">
                        <span class="btn btn-secondary disabled" style="opacity:0.7"><i class="bi bi-hdd-network"></i> OLT</span>
                        <button type="button" class="btn btn-outline-primary" onclick="getOnuConfig(<?= $currentOnu['id'] ?>)" title="Show OLT Config">
                            <i class="bi bi-code-slash"></i><span class="d-none d-lg-inline ms-1">Config</span>
                        </button>
                        <form method="post" class="d-inline" onsubmit="return confirm('Reboot this ONU?')">
                            <input type="hidden" name="action" value="reboot_onu">
                            <input type="hidden" name="onu_id" value="<?= $currentOnu['id'] ?>">
                            <button type="submit" class="btn btn-outline-warning" title="Reboot ONU">
                                <i class="bi bi-arrow-clockwise"></i><span class="d-none d-lg-inline ms-1">Reboot</span>
                            </button>
                        </form>
                        <form method="post" class="d-inline" onsubmit="return confirm('Push TR-069 OMCI config to OLT?')">
                            <input type="hidden" name="action" value="configure_tr069">
                            <input type="hidden" name="onu_id" value="<?= $currentOnu['id'] ?>">
                            <button type="submit" class="btn btn-outline-info" title="Push TR-069 OMCI Config (enables device to connect to GenieACS)">
                                <i class="bi bi-broadcast"></i><span class="d-none d-lg-inline ms-1">Push TR-069</span>
                            </button>
                        </form>
                    </div>
                    
                    <!-- TR-069/GenieACS Operations -->
                    <div class="btn-group btn-group-sm" role="group">
                        <span class="btn btn-info disabled text-white" style="opacity:0.8"><i class="bi bi-cloud"></i> TR-069</span>
                        <button type="button" class="btn btn-outline-info" onclick="toggleInlineStatus('<?= $currentOnu['sn'] ?>')" title="View & Edit All Device Parameters" id="inlineStatusBtn">
                            <i class="bi bi-sliders"></i><span class="d-none d-lg-inline ms-1">Status</span>
                        </button>
                        <button type="button" class="btn btn-outline-primary" onclick="openTR069WANConfig(<?= $currentOnu['id'] ?>, '<?= $currentOnu['sn'] ?>')" title="Configure PPPoE/IPoE WAN via TR-069">
                            <i class="bi bi-ethernet"></i><span class="d-none d-lg-inline ms-1">WAN</span>
                        </button>
                        <button type="button" class="btn btn-outline-success" onclick="openTR069WiFiConfig('<?= $currentOnu['sn'] ?>')" title="Configure WiFi via TR-069">
                            <i class="bi bi-wifi"></i><span class="d-none d-lg-inline ms-1">WiFi</span>
                        </button>
                    </div>
                    
                    <!-- Delete -->
                    <div class="ms-auto">
                        <form method="post" class="d-inline" onsubmit="return confirm('DELETE this ONU from OLT?')">
                            <input type="hidden" name="action" value="delete_onu_olt">
                            <input type="hidden" name="onu_id" value="<?= $currentOnu['id'] ?>">
                            <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete from OLT">
                                <i class="bi bi-trash"></i><span class="d-none d-md-inline ms-1">Delete</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Compact ONU Info Bar -->
            <div class="card shadow-sm mb-3">
                <div class="card-body py-2 px-3">
                    <div class="row align-items-center g-2">
                        <!-- Status & Model -->
                        <div class="col-auto">
                            <?php
                            $onuStatus = strtolower($currentOnu['status'] ?? 'offline');
                            $statusClass = ['online' => 'success', 'offline' => 'secondary', 'los' => 'danger'][$onuStatus] ?? 'secondary';
                            $statusIcon = ['online' => 'check-circle-fill', 'offline' => 'x-circle', 'los' => 'exclamation-triangle-fill'][$onuStatus] ?? 'circle';
                            ?>
                            <span id="onuStatusBadge" class="badge bg-<?= $statusClass ?> fs-6" data-live-status>
                                <i class="bi bi-<?= $statusIcon ?> me-1"></i><?= ucfirst($onuStatus) ?>
                            </span>
                            <button type="button" class="btn btn-link btn-sm p-0 ms-1" onclick="refreshLiveStatus()" title="Refresh">
                                <i class="bi bi-arrow-clockwise" id="liveStatusRefreshIcon"></i>
                            </button>
                        </div>
                        <div class="col-auto border-start ps-2">
                            <small class="text-muted">Model</small>
                            <div class="fw-medium"><?= htmlspecialchars($currentOnu['onu_type_model'] ?? $currentOnu['discovered_eqid'] ?? 'Unknown') ?></div>
                        </div>
                        <!-- Position -->
                        <div class="col-auto border-start ps-2">
                            <small class="text-muted">Position</small>
                            <div class="fw-medium"><code><?= $currentOnu['frame'] ?? 0 ?>/<?= $currentOnu['slot'] ?? 0 ?>/<?= $currentOnu['port'] ?? 0 ?></code> ONU <code><?= $currentOnu['onu_id'] ?? '-' ?></code></div>
                        </div>
                        <!-- VLANs -->
                        <div class="col-auto border-start ps-2">
                            <small class="text-muted">VLANs <button type="button" class="btn btn-link btn-sm p-0" data-bs-toggle="modal" data-bs-target="#attachedVlansModal"><i class="bi bi-pencil-square"></i></button></small>
                            <div class="fw-medium">
                                <?php if (!empty($attachedVlans)): foreach ($attachedVlans as $vid): ?><span class="badge bg-info me-1"><?= $vid ?></span><?php endforeach; else: ?><span class="text-muted">None</span><?php endif; ?>
                            </div>
                        </div>
                        <!-- TR-069 -->
                        <div class="col-auto border-start ps-2">
                            <small class="text-muted">TR-069</small>
                            <div>
                                <?php $tr069Status = $currentOnu['tr069_status'] ?? 'pending'; ?>
                                <span id="tr069StatusBadge" class="badge bg-<?= $tr069Status === 'online' ? 'success' : ($tr069Status === 'configured' ? 'info' : ($tr069Status === 'offline' ? 'secondary' : 'warning')) ?>"><?= ucfirst($tr069Status) ?></span>
                            </div>
                        </div>
                        <!-- ONU Mode (Bridge/Router) -->
                        <div class="col-auto border-start ps-2">
                            <small class="text-muted">Mode</small>
                            <div class="fw-medium">
                                <?php $ipMode = $currentOnu['ip_mode'] ?? 'Router'; ?>
                                <span id="onuModeDisplay" class="badge bg-<?= strtolower($ipMode) === 'bridge' ? 'secondary' : 'info' ?>"><?= htmlspecialchars($ipMode ?: 'Router') ?></span>
                                <button type="button" class="btn btn-link btn-sm p-0 ms-1" data-bs-toggle="modal" data-bs-target="#changeModeModal" title="Change Mode">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                            </div>
