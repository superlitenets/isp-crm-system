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
                       ol.name as olt_name, o.updated_at,
                       c.name as customer_name
                FROM huawei_onus o
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
        
        // Infer bands from index if not detected (common convention: 1=2.4GHz, 5=5GHz)
        foreach ($detectedConfigs as $idx => &$config) {
            if ($config['band'] === null) {
                if ($idx == 1) $config['band'] = '2.4GHz';
                elseif ($idx == 5 || $idx == 2) $config['band'] = '5GHz';
                else $config['band'] = "Radio {$idx}";
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
                    $result = $huaweiOLT->configureTR069Manual($onuId, $tr069Vlan, $acsUrl, $gemPort);
                    
                    if ($result['success']) {
                        $message = "TR-069 configured successfully! VLAN: {$result['tr069_vlan']}, ACS: {$result['acs_url']}";
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
                    'wan_index' => 2,
                    'connection_name' => 'Internet_PPPoE',
                    'pppoe_username' => trim($_POST['pppoe_username'] ?? ''),
                    'pppoe_password' => trim($_POST['pppoe_password'] ?? ''),
                    'auto_bind_ports' => true,
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
                // SIMPLIFIED AUTHORIZATION: Name, Zone, Address, Service VLAN
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
                
                // Update ONU record with name, zone, address
                $updateFields = ['name' => $name ?: $sn];
                if (!empty($zone)) $updateFields['zone'] = $zone;
                if ($zoneId) $updateFields['zone_id'] = $zoneId;
                if (!empty($address)) $updateFields['description'] = $address;
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
                            'tr069_gem_port' => 2
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
                        'tr069_gem_port' => !empty($_POST['tr069_gem_port']) ? (int)$_POST['tr069_gem_port'] : 2
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
                
                // Queue TR-069 configuration if WAN/WiFi settings provided and auth succeeded
                $tr069Queued = false;
                if ($messageType === 'success' && (!empty($_POST['pppoe_username']) || !empty($_POST['wifi_ssid_24']))) {
                    // Store TR-069 config to be applied when device connects to ACS
                    $tr069Config = [
                        'onu_id' => $onuId,
                        'wan_vlan' => $serviceVlan,
                        'connection_type' => $_POST['connection_type'] ?? 'pppoe',
                        'pppoe_username' => $_POST['pppoe_username'] ?? '',
                        'pppoe_password' => $_POST['pppoe_password'] ?? '',
                        'nat_enable' => true,
                        'wifi_ssid_24' => $_POST['wifi_ssid_24'] ?? '',
                        'wifi_pass_24' => $_POST['wifi_pass_24'] ?? '',
                        'wifi_ssid_5' => $_POST['wifi_ssid_5'] ?? '',
                        'wifi_pass_5' => $_POST['wifi_pass_5'] ?? '',
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
                        $errors = [];
                        
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
                            
                            $statusMap = ['online' => 'online', 'offline' => 'offline', 'power fail' => 'offline', 'los' => 'los', 'dying-gasp' => 'offline'];
                            $mappedStatus = $statusMap[$status] ?? 'offline';
                            
                            $stmt = $db->prepare("SELECT id FROM huawei_onus WHERE sn = ?");
                            $stmt->execute([$sn]);
                            $existing = $stmt->fetch(\PDO::FETCH_ASSOC);
                            
                            try {
                                if ($existing) {
                                    $stmt = $db->prepare("UPDATE huawei_onus SET name = ?, description = ?, slot = ?, port = ?, onu_id = ?, status = ?, zone = ?, onu_type_id = ?, vlan_id = ?, smartolt_external_id = ?, rx_power = ?, tx_power = ?, distance = ?, is_authorized = true, updated_at = NOW() WHERE id = ?");
                                    $stmt->execute([$name ?: $sn, $address, $board, $port, $allocatedOnu, $mappedStatus, $zone, $onuTypeId, $serviceVlan > 0 ? $serviceVlan : null, $externalId ?: null, $rxPowerFloat, $txPowerFloat, $distanceFloat, $existing['id']]);
                                    $updated++;
                                } else {
                                    $stmt = $db->prepare("INSERT INTO huawei_onus (sn, olt_id, name, description, frame, slot, port, onu_id, status, is_authorized, zone, onu_type_id, vlan_id, smartolt_external_id, rx_power, tx_power, distance, created_at, updated_at) VALUES (?, ?, ?, ?, 0, ?, ?, ?, ?, true, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                                    $stmt->execute([$sn, $oltId, $name ?: $sn, $address, $board, $port, $allocatedOnu, $mappedStatus, $zone, $onuTypeId, $serviceVlan > 0 ? $serviceVlan : null, $externalId ?: null, $rxPowerFloat, $txPowerFloat, $distanceFloat]);
                                    $imported++;
                                }
                            } catch (\Exception $e) {
                                $errors[] = "SN {$sn}: " . $e->getMessage();
                            }
                        }
                        fclose($handle);
                        
                        $message = "CSV Import complete: {$imported} added, {$updated} updated";
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
                    $message = "Synced {$result['synced']} devices from GenieACS (total: {$result['total']})";
                    $messageType = 'success';
                } else {
                    $message = $result['error'] ?? 'Sync failed';
                    $messageType = 'danger';
                }
                break;
            case 'tr069_reboot':
                require_once __DIR__ . '/../src/GenieACS.php';
                $genieacs = new \App\GenieACS($db);
                $deviceId = $_POST['device_id'] ?? '';
                $serial = $_POST['serial'] ?? '';
                $onuId = $_POST['onu_id'] ?? '';
                
                // Look up ONU if onu_id provided
                if (empty($deviceId) && empty($serial) && !empty($onuId)) {
                    $onuStmt = $db->prepare("SELECT sn, tr069_device_id FROM huawei_onus WHERE id = ?");
                    $onuStmt->execute([$onuId]);
                    $onuData = $onuStmt->fetch(PDO::FETCH_ASSOC);
                    if ($onuData) {
                        $deviceId = $onuData['tr069_device_id'] ?? '';
                        $serial = $onuData['sn'] ?? '';
                    }
                }
                
                if (empty($deviceId) && !empty($serial)) {
                    $deviceResult = $genieacs->getDeviceBySerial($serial);
                    if ($deviceResult['success']) {
                        $deviceId = $deviceResult['device']['_id'] ?? '';
                    }
                }
                
                if (empty($deviceId)) {
                    $message = 'Device not connected to TR-069/GenieACS';
                    $messageType = 'warning';
                    break;
                }
                
                $result = $genieacs->rebootDevice($deviceId);
                $message = $result['success'] ? 'Reboot command sent to device' : ($result['error'] ?? 'Reboot failed');
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
            case 'configure_pppoe':
                // SmartOLT-style Internet WAN configuration via TR-069
                require_once __DIR__ . '/../src/GenieACS.php';
                $genieacs = new \App\GenieACS($db);
                $serial = $_POST['serial'] ?? '';
                $connType = $_POST['connection_type'] ?? 'pppoe';
                
                $deviceResult = $genieacs->getDeviceBySerial($serial);
                if (!$deviceResult['success']) {
                    $message = 'Device not found in GenieACS: ' . $serial;
                    $messageType = 'danger';
                    break;
                }
                
                $deviceId = $deviceResult['device']['_id'];
                
                // Use SmartOLT-style configureInternetWAN function
                $config = [
                    'connection_type' => $connType,
                    'service_vlan' => (int)($_POST['vlan_id'] ?? 0),
                    'wan_index' => 2, // WAN device 1 is management, 2 is internet
                    'connection_name' => $connType === 'pppoe' ? 'Internet_PPPoE' : 'Internet_' . strtoupper($connType),
                    'auto_bind_ports' => true, // Bind LAN1-4 and SSID1 to this WAN
                ];
                
                if ($connType === 'pppoe') {
                    $config['pppoe_username'] = $_POST['pppoe_username'] ?? '';
                    $config['pppoe_password'] = $_POST['pppoe_password'] ?? '';
                } elseif ($connType === 'static') {
                    $config['static_ip'] = $_POST['static_ip'] ?? '';
                    $config['static_mask'] = $_POST['subnet_mask'] ?? '255.255.255.0';
                    $config['static_gateway'] = $_POST['gateway'] ?? '';
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
                $config = [
                    'connection_type' => 'pppoe',
                    'service_vlan' => (int)($_POST['pppoe_vlan'] ?? 902),
                    'wan_index' => 2,
                    'connection_name' => 'Internet_PPPoE',
                    'pppoe_username' => $_POST['pppoe_username'] ?? '',
                    'pppoe_password' => $_POST['pppoe_password'] ?? '',
                    'auto_bind_ports' => true,
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
            case 'tr069_refresh':
                require_once __DIR__ . '/../src/GenieACS.php';
                $genieacs = new \App\GenieACS($db);
                $deviceId = $_POST['device_id'] ?? '';
                $onuId = $_POST['onu_id'] ?? '';
                
                // Look up ONU if onu_id provided
                if (empty($deviceId) && !empty($onuId)) {
                    $onuStmt = $db->prepare("SELECT sn, tr069_device_id FROM huawei_onus WHERE id = ?");
                    $onuStmt->execute([$onuId]);
                    $onuData = $onuStmt->fetch(PDO::FETCH_ASSOC);
                    if ($onuData) {
                        $deviceId = $onuData['tr069_device_id'] ?? '';
                        if (empty($deviceId) && !empty($onuData['sn'])) {
                            $deviceResult = $genieacs->getDeviceBySerial($onuData['sn']);
                            if ($deviceResult['success']) {
                                $deviceId = $deviceResult['device']['_id'] ?? '';
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
                    $onuStmt = $db->prepare("SELECT sn, tr069_device_id FROM huawei_onus WHERE id = ?");
                    $onuStmt->execute([$onuId]);
                    $onuData = $onuStmt->fetch(PDO::FETCH_ASSOC);
                    if ($onuData) {
                        $deviceId = $onuData['tr069_device_id'] ?? '';
                        if (empty($deviceId) && !empty($onuData['sn'])) {
                            $deviceResult = $genieacs->getDeviceBySerial($onuData['sn']);
                            if ($deviceResult['success']) {
                                $deviceId = $deviceResult['device']['_id'] ?? '';
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
                    $onuStmt = $db->prepare("SELECT sn, tr069_device_id FROM huawei_onus WHERE id = ?");
                    $onuStmt->execute([$onuId]);
                    $onuData = $onuStmt->fetch(PDO::FETCH_ASSOC);
                    if ($onuData) {
                        $deviceId = $onuData['tr069_device_id'] ?? '';
                        if (empty($deviceId) && !empty($onuData['sn'])) {
                            $deviceResult = $genieacs->getDeviceBySerial($onuData['sn']);
                            if ($deviceResult['success']) {
                                $deviceId = $deviceResult['device']['_id'] ?? '';
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
                        
                        // Apply WAN config
                        if (!empty($config['pppoe_username'])) {
                            $wanResult = $genieacs->setWANConfig($tr069Device['device_id'], [
                                'connection_type' => $config['connection_type'] ?? 'pppoe',
                                'pppoe_username' => $config['pppoe_username'],
                                'pppoe_password' => $config['pppoe_password'],
                                'wan_vlan' => $config['wan_vlan'] ?? 902,
                                'nat_enable' => $config['nat_enable'] ?? true
                            ]);
                            if (!$wanResult['success']) {
                                $allSuccess = false;
                                $errors[] = 'WAN: ' . ($wanResult['error'] ?? 'failed');
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
                
                $config = [
                    'connection_type' => $connType,
                    'service_vlan' => (int)($_POST['wan_vlan'] ?? $_POST['vlan_id'] ?? 0),
                    'wan_index' => 2,
                    'connection_name' => $connType === 'pppoe' ? 'Internet_PPPoE' : 'Internet_' . strtoupper($connType),
                    'auto_bind_ports' => true,
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
                $cmd = "display ont version {$onu['frame']}/{$onu['slot']} {$onu['port']} {$onu['onu_id']}";
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
                    'serial' => $onu['serial_number'] ?? '-',
                    'cpu_usage' => '-',
                    'ram_usage' => '-',
                    'uptime' => '-',
                    'uptime_seconds' => 0,
                    'found_in_acs' => false
                ];
                
                // Get TR-069 info from GenieACS if device is registered
                if (!empty($onu['serial_number'])) {
                    try {
                        require_once __DIR__ . '/../src/GenieACS.php';
                        $genieacs = new \App\GenieACS($db);
                        $deviceResult = $genieacs->getDeviceBySerial($onu['serial_number']);
                        if ($deviceResult['success'] && !empty($deviceResult['device'])) {
                            $device = $deviceResult['device'];
                            $result['found_in_acs'] = true;
                            $result['status'] = 'connected';
                            
                            // Device Info
                            $igd = $device['InternetGatewayDevice'] ?? [];
                            $devInfo = $igd['DeviceInfo'] ?? [];
                            $mgmtServer = $igd['ManagementServer'] ?? [];
                            
                            // Manufacturer & OUI
                            if (isset($devInfo['Manufacturer']['_value'])) {
                                $result['manufacturer'] = $devInfo['Manufacturer']['_value'];
                            }
                            if (isset($device['_deviceId']['_OUI'])) {
                                $result['oui'] = $device['_deviceId']['_OUI'];
                            }
                            
                            // Model
                            if (isset($devInfo['ModelName']['_value'])) {
                                $result['model'] = $devInfo['ModelName']['_value'];
                            } elseif (isset($device['_deviceId']['_ProductClass'])) {
                                $result['model'] = $device['_deviceId']['_ProductClass'];
                            }
                            
                            // Software/Hardware Version
                            if (isset($devInfo['SoftwareVersion']['_value'])) {
                                $result['sw_version'] = $devInfo['SoftwareVersion']['_value'];
                            }
                            if (isset($devInfo['HardwareVersion']['_value'])) {
                                $result['hw_version'] = $devInfo['HardwareVersion']['_value'];
                            }
                            
                            // Provisioning Code
                            if (isset($devInfo['ProvisioningCode']['_value'])) {
                                $result['provisioning_code'] = $devInfo['ProvisioningCode']['_value'];
                            }
                            
                            // Serial Number
                            if (isset($devInfo['SerialNumber']['_value'])) {
                                $result['serial'] = $devInfo['SerialNumber']['_value'];
                            } elseif (isset($device['_deviceId']['_SerialNumber'])) {
                                $result['serial'] = $device['_deviceId']['_SerialNumber'];
                            }
                            
                            // CPU Usage (X_HW_DeviceMgr.CpuUsage or similar)
                            if (isset($igd['X_HW_DeviceMgr']['CpuUsage']['_value'])) {
                                $result['cpu_usage'] = $igd['X_HW_DeviceMgr']['CpuUsage']['_value'] . ' %';
                            } elseif (isset($devInfo['ProcessStatus']['CPUUsage']['_value'])) {
                                $result['cpu_usage'] = $devInfo['ProcessStatus']['CPUUsage']['_value'] . ' %';
                            }
                            
                            // Memory/RAM Usage
                            if (isset($igd['X_HW_DeviceMgr']['MemUsage']['_value'])) {
                                $result['ram_usage'] = $igd['X_HW_DeviceMgr']['MemUsage']['_value'] . ' %';
                            } elseif (isset($devInfo['MemoryStatus']['Free']['_value']) && isset($devInfo['MemoryStatus']['Total']['_value'])) {
                                $free = $devInfo['MemoryStatus']['Free']['_value'];
                                $total = $devInfo['MemoryStatus']['Total']['_value'];
                                if ($total > 0) {
                                    $usedPercent = round((($total - $free) / $total) * 100);
                                    $result['ram_usage'] = $usedPercent . ' %';
                                }
                            }
                            
                            // Uptime
                            if (isset($devInfo['UpTime']['_value'])) {
                                $uptimeSec = (int)$devInfo['UpTime']['_value'];
                                $result['uptime_seconds'] = $uptimeSec;
                                $days = floor($uptimeSec / 86400);
                                $hours = floor(($uptimeSec % 86400) / 3600);
                                $mins = floor(($uptimeSec % 3600) / 60);
                                $secs = $uptimeSec % 60;
                                $result['uptime'] = '';
                                if ($days > 0) $result['uptime'] .= "{$days} day" . ($days > 1 ? 's' : '') . ", ";
                                if ($hours > 0) $result['uptime'] .= "{$hours} hour" . ($hours > 1 ? 's' : '') . ", ";
                                $result['uptime'] .= "{$mins} min" . ($mins > 1 ? 's' : '') . ", {$secs} sec";
                            }
                            
                            // Management IP from ConnectionRequestURL
                            if (isset($mgmtServer['ConnectionRequestURL']['_value'])) {
                                $connUrl = $mgmtServer['ConnectionRequestURL']['_value'];
                                if (preg_match('/https?:\/\/([^:\/]+)/', $connUrl, $m)) {
                                    $result['ip'] = $m[1];
                                }
                            }
                            
                            // ACS URL
                            if (isset($mgmtServer['URL']['_value'])) {
                                $result['acs_url'] = $mgmtServer['URL']['_value'];
                            }
                            
                            // Periodic inform interval
                            if (isset($mgmtServer['PeriodicInformInterval']['_value'])) {
                                $result['inform_interval'] = $mgmtServer['PeriodicInformInterval']['_value'];
                            }
                            
                            // Last inform time
                            if (isset($device['_lastInform'])) {
                                $result['last_inform'] = date('Y-m-d H:i:s', strtotime($device['_lastInform']));
                            }
                        }
                    } catch (Exception $e) {
                        $result['error_detail'] = $e->getMessage();
                    }
                }
                
                // Fall back to database values if GenieACS didn't provide them
                if ($result['ip'] === '-' && !empty($onu['tr069_ip'])) { 
                    $result['ip'] = $onu['tr069_ip']; 
                    $result['status'] = 'connected'; 
                }
                if ($result['last_inform'] === '-' && !empty($onu['tr069_last_inform'])) {
                    $result['last_inform'] = date('Y-m-d H:i:s', strtotime($onu['tr069_last_inform']));
                }
                
                echo json_encode($result);
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
                    $genieacs = new GenieACS();
                    $device = $genieacs->findDeviceBySerial($serial);
                    if (!$device) {
                        echo json_encode(['success' => false, 'error' => 'Device not found in ACS']);
                        exit;
                    }
                    $wifiInterfaces = [];
                    $wifiPaths = [
                        'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1' => '2.4GHz',
                        'InternetGatewayDevice.LANDevice.1.WLANConfiguration.2' => '5GHz',
                        'InternetGatewayDevice.LANDevice.1.WLANConfiguration.3' => '2.4GHz Guest',
                        'InternetGatewayDevice.LANDevice.1.WLANConfiguration.4' => '5GHz Guest'
                    ];
                    foreach ($wifiPaths as $path => $band) {
                        $ssid = $genieacs->getParameterValue($device, $path . '.SSID');
                        if ($ssid !== null) {
                            $enabled = $genieacs->getParameterValue($device, $path . '.Enable');
                            $wifiInterfaces[] = [
                                'band' => $band,
                                'ssid' => $ssid,
                                'enabled' => $enabled === true || $enabled === 'true' || $enabled === '1' || $enabled === 1,
                                'mode' => 'LAN',
                                'path' => $path
                            ];
                        }
                    }
                    echo json_encode(['success' => true, 'interfaces' => $wifiInterfaces]);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
                exit;
            case 'refresh_tr069_ip':
                $result = $huaweiOLT->refreshONUTR069IP((int)$_POST['onu_id']);
                if ($result['success']) {
                    $message = $result['message'];
                    $messageType = 'success';
                } else {
                    $message = $result['message'] ?? 'Failed to get TR-069 IP';
                    $messageType = 'warning';
                }
                break;
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

// Load location data for locations view and authorization modal
$zones = $huaweiOLT->getZones(false);
$subzones = $huaweiOLT->getSubzones();
$apartments = $huaweiOLT->getApartments();
$odbs = $huaweiOLT->getODBs();

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
    
    // Fetch TR-069 device info
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
        
        // If device found in local table and GenieACS is configured, get live info
        if ($genieacsConfigured && $tr069Device && $tr069Device['device_id']) {
            $deviceResult = $genieacs->getDeviceInfo($tr069Device['device_id']);
            if ($deviceResult['success']) {
                $tr069Info = $deviceResult['info'];
            }
        }
        // Fallback: If not in local table but GenieACS is configured, try direct lookup
        elseif ($genieacsConfigured && !$tr069Device && !empty($currentOnu['sn'])) {
            $deviceResult = $genieacs->getDeviceBySerial($currentOnu['sn']);
            if ($deviceResult['success'] && isset($deviceResult['device'])) {
                $device = $deviceResult['device'];
                $deviceId = $device['_id'] ?? '';
                // Create a pseudo tr069Device for display
                $tr069Device = [
                    'device_id' => $deviceId,
                    'serial_number' => $currentOnu['sn'],
                    'from_genieacs' => true
                ];
                // Get device info
                $infoResult = $genieacs->getDeviceInfo($deviceId);
                if ($infoResult['success']) {
                    $tr069Info = $infoResult['info'];
                }
            }
        }
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
            --oms-primary: #0f172a;
            --oms-primary-light: #1e293b;
            --oms-accent: #3b82f6;
            --oms-accent-light: #60a5fa;
            --oms-success: #10b981;
            --oms-warning: #f59e0b;
            --oms-danger: #ef4444;
            --oms-info: #06b6d4;
            --oms-bg: #f1f5f9;
            --oms-card-bg: #ffffff;
            --oms-text: #334155;
            --oms-text-muted: #64748b;
            --oms-border: #e2e8f0;
            --oms-shadow: 0 1px 3px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.06);
            --oms-shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.08), 0 4px 6px -2px rgba(0,0,0,0.04);
            --oms-radius: 0.75rem;
            --oms-radius-lg: 1rem;
        }
        
        body { 
            background-color: var(--oms-bg); 
            color: var(--oms-text);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        /* Sidebar */
        .sidebar { 
            background: linear-gradient(180deg, var(--oms-primary) 0%, var(--oms-primary-light) 100%); 
            min-height: 100vh;
            box-shadow: 4px 0 10px rgba(0,0,0,0.1);
        }
        .sidebar .nav-link { 
            color: rgba(255,255,255,0.7); 
            padding: 0.875rem 1rem; 
            border-radius: var(--oms-radius); 
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
            background: linear-gradient(90deg, var(--oms-accent) 0%, var(--oms-accent-light) 100%);
            color: #fff;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
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
        
        /* Cards */
        .card {
            border: 1px solid var(--oms-border);
            border-radius: var(--oms-radius-lg);
            box-shadow: var(--oms-shadow);
            transition: all 0.3s ease;
        }
        .card:hover {
            box-shadow: var(--oms-shadow-lg);
        }
        .card-header {
            background: transparent;
            border-bottom: 1px solid var(--oms-border);
            font-weight: 600;
            padding: 1rem 1.25rem;
        }
        
        /* Stat Cards */
        .stat-card { 
            border-radius: var(--oms-radius-lg); 
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
            background: linear-gradient(90deg, var(--oms-accent), var(--oms-accent-light));
        }
        .stat-card:hover { 
            transform: translateY(-4px); 
            box-shadow: var(--oms-shadow-lg);
        }
        .stat-card.stat-success::before { background: linear-gradient(90deg, var(--oms-success), #34d399); }
        .stat-card.stat-warning::before { background: linear-gradient(90deg, var(--oms-warning), #fbbf24); }
        .stat-card.stat-danger::before { background: linear-gradient(90deg, var(--oms-danger), #f87171); }
        .stat-card.stat-info::before { background: linear-gradient(90deg, var(--oms-info), #22d3ee); }
        
        .stat-icon { 
            width: 56px; 
            height: 56px; 
            border-radius: var(--oms-radius); 
            display: flex; 
            align-items: center; 
            justify-content: center;
            font-size: 1.5rem;
        }
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            line-height: 1;
            color: var(--oms-primary);
        }
        .stat-label {
            font-size: 0.85rem;
            color: var(--oms-text-muted);
            font-weight: 500;
        }
        
        /* Tables */
        .table {
            margin-bottom: 0;
        }
        .table thead th {
            background: var(--oms-bg);
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--oms-text-muted);
            border-bottom: 2px solid var(--oms-border);
            padding: 1rem;
        }
        .table tbody td {
            padding: 1rem;
            vertical-align: middle;
            border-color: var(--oms-border);
        }
        .table-hover tbody tr {
            transition: background-color 0.15s ease;
        }
        .table-hover tbody tr:hover { 
            background-color: rgba(59, 130, 246, 0.04); 
        }
        
        /* Badges */
        .badge {
            font-weight: 600;
            padding: 0.4em 0.8em;
            border-radius: 0.5rem;
            font-size: 0.75rem;
            letter-spacing: 0.3px;
        }
        .badge-online { background: linear-gradient(135deg, var(--oms-success), #34d399); }
        .badge-offline { background: linear-gradient(135deg, #64748b, #94a3b8); }
        .badge-los { background: linear-gradient(135deg, var(--oms-danger), #f87171); }
        .badge-power-fail { background: linear-gradient(135deg, var(--oms-warning), #fbbf24); color: #000; }
        
        /* OLT Cards */
        .olt-card { 
            border: none;
            border-radius: var(--oms-radius-lg);
            background: var(--oms-card-bg);
            box-shadow: var(--oms-shadow);
            transition: all 0.3s ease;
            overflow: hidden;
        }
        .olt-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            bottom: 0;
            width: 4px;
            background: linear-gradient(180deg, var(--oms-accent), var(--oms-accent-light));
        }
        .olt-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--oms-shadow-lg);
        }
        .olt-card.offline::before { 
            background: linear-gradient(180deg, var(--oms-danger), #f87171);
        }
        
        /* Signal Indicators */
        .signal-good { color: var(--oms-success); }
        .signal-warning { color: var(--oms-warning); }
        .signal-critical { color: var(--oms-danger); }
        
        /* Buttons */
        .btn {
            border-radius: var(--oms-radius);
            font-weight: 500;
            padding: 0.5rem 1rem;
            transition: all 0.2s ease;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--oms-accent), var(--oms-accent-light));
            border: none;
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #2563eb, var(--oms-accent));
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        }
        .btn-success {
            background: linear-gradient(135deg, var(--oms-success), #34d399);
            border: none;
        }
        .btn-warning {
            background: linear-gradient(135deg, var(--oms-warning), #fbbf24);
            border: none;
            color: #000;
        }
        .btn-danger {
            background: linear-gradient(135deg, var(--oms-danger), #f87171);
            border: none;
        }
        .btn-outline-primary {
            border: 2px solid var(--oms-accent);
            color: var(--oms-accent);
        }
        .btn-outline-primary:hover {
            background: var(--oms-accent);
            border-color: var(--oms-accent);
        }
        
        /* Pulsing badge for pending authorization */
        .badge-pulse {
            animation: pulse-animation 2s infinite;
        }
        @keyframes pulse-animation {
            0% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(245, 158, 11, 0); }
            100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0); }
        }
        .pending-auth-highlight {
            background: linear-gradient(90deg, rgba(245, 158, 11, 0.15) 0%, transparent 100%);
        }
        
        /* Loading overlay styles */
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(4px);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        .loading-overlay.active { display: flex; }
        .loading-spinner-container {
            background: white;
            padding: 2.5rem 3.5rem;
            border-radius: var(--oms-radius-lg);
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
        }
        .loading-spinner {
            width: 56px;
            height: 56px;
            border: 4px solid var(--oms-border);
            border-top-color: var(--oms-accent);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin: 0 auto 1.25rem;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .loading-text {
            color: var(--oms-primary);
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        /* Forms */
        .form-control, .form-select {
            border-radius: var(--oms-radius);
            border: 2px solid var(--oms-border);
            padding: 0.625rem 1rem;
            transition: all 0.2s ease;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--oms-accent);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        }
        
        /* Modals */
        .modal-content {
            border: none;
            border-radius: var(--oms-radius-lg);
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
        }
        .modal-header {
            border-bottom: 1px solid var(--oms-border);
            padding: 1.25rem 1.5rem;
        }
        .modal-footer {
            border-top: 1px solid var(--oms-border);
            padding: 1rem 1.5rem;
        }
        
        /* Scrollbar */
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
        
        /* Sync button */
        .btn-sync { position: relative; }
        .btn-sync .spinner-border { display: none; }
        .btn-sync.syncing .spinner-border { display: inline-block; }
        .btn-sync.syncing .btn-text { display: none; }
        
        /* Page Title */
        .page-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--oms-primary);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .page-title i {
            color: var(--oms-accent);
        }
        
        /* Live indicator */
        .live-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.25rem 0.75rem;
            background: rgba(16, 185, 129, 0.1);
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
            animation: live-pulse 1.5s infinite;
        }
        @keyframes live-pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(0.8); }
        }
        
        /* Mobile Responsive Styles */
        .oms-mobile-header {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 56px;
            background: linear-gradient(180deg, var(--oms-primary) 0%, var(--oms-primary-light) 100%);
            z-index: 1050;
            padding: 0 1rem;
            align-items: center;
            justify-content: space-between;
        }
        .oms-mobile-header .brand-mobile {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .oms-mobile-header .brand-mobile .brand-title {
            font-size: 1.25rem;
        }
        .oms-mobile-header .hamburger-btn {
            background: rgba(255,255,255,0.1);
            border: none;
            color: #fff;
            font-size: 1.5rem;
            padding: 0.5rem 0.75rem;
            border-radius: 8px;
        }
        .oms-offcanvas {
            background: linear-gradient(180deg, var(--oms-primary) 0%, var(--oms-primary-light) 100%) !important;
            width: 280px !important;
        }
        .oms-offcanvas .offcanvas-header {
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .oms-offcanvas .btn-close {
            filter: invert(1);
        }
        
        @media (max-width: 991.98px) {
            .sidebar {
                display: none !important;
            }
            .oms-mobile-header {
                display: flex !important;
            }
            .main-content {
                margin-left: 0 !important;
                padding: 1rem !important;
                padding-top: 70px !important;
                width: 100% !important;
            }
            .stat-card {
                margin-bottom: 0.75rem;
            }
            .stat-icon {
                width: 40px !important;
                height: 40px !important;
                font-size: 1.2rem !important;
            }
            .stat-value {
                font-size: 1.5rem !important;
            }
            .page-title {
                font-size: 1.25rem;
            }
            .btn-group-mobile {
                flex-wrap: wrap;
                gap: 0.5rem;
            }
            .btn-group-mobile .btn {
                flex: 1 1 auto;
                min-width: 120px;
            }
        }
        
        @media (max-width: 767.98px) {
            .form-control, .form-select, .btn {
                min-height: 44px;
                touch-action: manipulation;
            }
            .btn {
                -webkit-tap-highlight-color: rgba(0,0,0,0.1);
                cursor: pointer;
            }
            .modal-dialog {
                margin: 0.5rem;
            }
            .card-body {
                padding: 1rem;
            }
            .table td, .table th {
                padding: 0.5rem;
                font-size: 0.875rem;
            }
            .nav-link {
                min-height: 44px;
                display: flex;
                align-items: center;
                touch-action: manipulation;
                -webkit-tap-highlight-color: rgba(0,0,0,0.1);
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
            <div class="text-muted small mt-2">This may take a few seconds</div>
        </div>
    </div>
    
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
            <?php
            // Calculate additional statistics
            $totalOnus = $stats['online_onus'] + $stats['offline_onus'] + $stats['los_onus'];
            $uptimePercent = $totalOnus > 0 ? round(($stats['online_onus'] / $totalOnus) * 100, 1) : 0;
            $onusByOltDashboard = $huaweiOLT->getONUsByOLT();
            ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="page-title mb-1"><i class="bi bi-speedometer2"></i> Network Dashboard</h4>
                    <div class="d-flex align-items-center gap-3">
                        <span class="text-muted small">Last updated: <?= date('M j, Y H:i:s') ?></span>
                        <span class="live-indicator"><span class="live-dot"></span> Live</span>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <a href="?page=huawei-olt&view=onus&unconfigured=1" class="btn btn-warning">
                        <i class="bi bi-hourglass-split me-1"></i> Pending (<?= $stats['unconfigured_onus'] ?>)
                    </a>
                    <button class="btn btn-outline-primary" onclick="location.reload()">
                        <i class="bi bi-arrow-clockwise me-1"></i> Refresh
                    </button>
                </div>
            </div>
            
            <!-- Primary Stats Row -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card stat-card shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                                    <i class="bi bi-hdd-rack fs-4"></i>
                                </div>
                                <span class="badge bg-primary"><?= $stats['active_olts'] ?> Active</span>
                            </div>
                            <div class="stat-value"><?= $stats['total_olts'] ?></div>
                            <div class="stat-label">Total OLT Devices</div>
                            <div class="progress mt-3" style="height: 6px;">
                                <div class="progress-bar bg-primary" style="width: <?= $stats['total_olts'] > 0 ? ($stats['active_olts'] / $stats['total_olts'] * 100) : 0 ?>%"></div>
                            </div>
                            <small class="text-muted"><?= $stats['total_olts'] - $stats['active_olts'] ?> inactive</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card stat-success shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="stat-icon bg-success bg-opacity-10 text-success">
                                    <i class="bi bi-wifi fs-4"></i>
                                </div>
                                <span class="badge bg-success"><?= $uptimePercent ?>% Uptime</span>
                            </div>
                            <div class="stat-value text-success"><?= number_format($stats['online_onus']) ?></div>
                            <div class="stat-label">Online ONUs</div>
                            <div class="progress mt-3" style="height: 6px;">
                                <div class="progress-bar bg-success" style="width: <?= $uptimePercent ?>%"></div>
                            </div>
                            <small class="text-muted">of <?= number_format($totalOnus) ?> total authorized</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card stat-danger shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                                    <i class="bi bi-exclamation-triangle fs-4"></i>
                                </div>
                                <?php if ($stats['los_onus'] > 0): ?>
                                <span class="badge bg-danger badge-pulse"><?= $stats['los_onus'] ?> LOS</span>
                                <?php endif; ?>
                            </div>
                            <div class="stat-value text-danger"><?= $stats['offline_onus'] + $stats['los_onus'] ?></div>
                            <div class="stat-label">Problem ONUs</div>
                            <div class="d-flex gap-3 mt-3">
                                <div>
                                    <span class="text-danger fw-bold"><?= $stats['los_onus'] ?></span>
                                    <small class="text-muted d-block">LOS</small>
                                </div>
                                <div>
                                    <span class="text-secondary fw-bold"><?= $stats['offline_onus'] ?></span>
                                    <small class="text-muted d-block">Offline</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <?php $totalPendingDash = $stats['unconfigured_onus'] + ($stats['discovered_onus'] ?? 0); ?>
                    <div class="card stat-card stat-warning shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                                    <i class="bi bi-hourglass-split fs-4"></i>
                                </div>
                                <?php if ($totalPendingDash > 0): ?>
                                <span class="badge bg-warning text-dark badge-pulse">New!</span>
                                <?php endif; ?>
                            </div>
                            <div class="stat-value text-warning" id="dashPendingCount"><?= $totalPendingDash ?></div>
                            <div class="stat-label">Pending Authorization</div>
                            <a href="?page=huawei-olt&view=onus&unconfigured=1" class="btn btn-sm btn-warning w-100 mt-3">
                                <i class="bi bi-arrow-right me-1"></i> Authorize Now
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Secondary Stats Row -->
            <div class="row g-4 mb-4">
                <div class="col-md-8">
                    <div class="card shadow-sm h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="bi bi-bar-chart me-2"></i>ONU Distribution by OLT</h6>
                            <small class="text-muted">Real-time status</small>
                        </div>
                        <div class="card-body">
                            <?php if (empty($onusByOltDashboard)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                No OLT data available
                            </div>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead>
                                        <tr>
                                            <th>OLT</th>
                                            <th class="text-center">Total</th>
                                            <th class="text-center">Online</th>
                                            <th class="text-center">Offline</th>
                                            <th>Distribution</th>
                                            <th class="text-end">Health</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($onusByOltDashboard as $oltData): 
                                            $oltTotal = $oltData['onu_count'] ?? 0;
                                            $oltOnline = $oltData['online'] ?? 0;
                                            $oltOffline = $oltData['offline'] ?? 0;
                                            $oltHealth = $oltTotal > 0 ? round(($oltOnline / $oltTotal) * 100) : 0;
                                            $healthClass = $oltHealth >= 90 ? 'success' : ($oltHealth >= 70 ? 'warning' : 'danger');
                                        ?>
                                        <tr>
                                            <td>
                                                <i class="bi bi-hdd-rack text-primary me-2"></i>
                                                <strong><?= htmlspecialchars($oltData['name'] ?? 'Unknown') ?></strong>
                                            </td>
                                            <td class="text-center fw-bold"><?= $oltTotal ?></td>
                                            <td class="text-center"><span class="text-success fw-bold"><?= $oltOnline ?></span></td>
                                            <td class="text-center"><span class="text-danger fw-bold"><?= $oltOffline ?></span></td>
                                            <td style="min-width: 150px;">
                                                <div class="progress" style="height: 8px;">
                                                    <div class="progress-bar bg-success" style="width: <?= $oltTotal > 0 ? ($oltOnline / $oltTotal * 100) : 0 ?>%"></div>
                                                    <div class="progress-bar bg-danger" style="width: <?= $oltTotal > 0 ? ($oltOffline / $oltTotal * 100) : 0 ?>%"></div>
                                                </div>
                                            </td>
                                            <td class="text-end">
                                                <span class="badge bg-<?= $healthClass ?>"><?= $oltHealth ?>%</span>
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
                <div class="col-md-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-pie-chart me-2"></i>Network Overview</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-center mb-4">
                                <div class="position-relative" style="width: 140px; height: 140px;">
                                    <svg viewBox="0 0 36 36" class="w-100 h-100" style="transform: rotate(-90deg);">
                                        <circle cx="18" cy="18" r="16" fill="none" stroke="#e2e8f0" stroke-width="3"></circle>
                                        <circle cx="18" cy="18" r="16" fill="none" stroke="var(--oms-success)" stroke-width="3" 
                                                stroke-dasharray="<?= $uptimePercent ?> <?= 100 - $uptimePercent ?>" stroke-linecap="round"></circle>
                                    </svg>
                                    <div class="position-absolute top-50 start-50 translate-middle text-center">
                                        <div class="fs-4 fw-bold text-success"><?= $uptimePercent ?>%</div>
                                        <small class="text-muted">Uptime</small>
                                    </div>
                                </div>
                            </div>
                            <div class="row text-center g-2">
                                <div class="col-6">
                                    <div class="p-2 rounded" style="background: rgba(16, 185, 129, 0.1);">
                                        <div class="fs-5 fw-bold text-success"><?= number_format($stats['online_onus']) ?></div>
                                        <small class="text-muted">Online</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-2 rounded" style="background: rgba(239, 68, 68, 0.1);">
                                        <div class="fs-5 fw-bold text-danger"><?= $stats['offline_onus'] + $stats['los_onus'] ?></div>
                                        <small class="text-muted">Offline/LOS</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-2 rounded" style="background: rgba(59, 130, 246, 0.1);">
                                        <div class="fs-5 fw-bold text-primary"><?= number_format($totalOnus) ?></div>
                                        <small class="text-muted">Authorized</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-2 rounded" style="background: rgba(245, 158, 11, 0.1);">
                                        <div class="fs-5 fw-bold text-warning"><?= $stats['unconfigured_onus'] ?></div>
                                        <small class="text-muted">Pending</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row g-4">
                <div class="col-md-8">
                    <div class="card shadow-sm">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="bi bi-hdd-rack me-2"></i>OLT Status</h6>
                            <a href="?page=huawei-olt&view=olts" class="btn btn-sm btn-outline-primary">Manage OLTs</a>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($olts)): ?>
                            <div class="p-4 text-center text-muted">
                                <i class="bi bi-inbox fs-1 mb-2 d-block"></i>
                                No OLTs configured. <a href="?page=huawei-olt&view=olts">Add your first OLT</a>
                            </div>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>OLT Name</th>
                                            <th>IP Address</th>
                                            <th>ONUs</th>
                                            <th>Status</th>
                                            <th>Last Sync</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $onusByOlt = $huaweiOLT->getONUsByOLT();
                                        $onuCountMap = array_column($onusByOlt, null, 'id');
                                        foreach ($olts as $olt): 
                                            $oltStats = $onuCountMap[$olt['id']] ?? ['onu_count' => 0, 'online' => 0, 'offline' => 0];
                                        ?>
                                        <tr>
                                            <td>
                                                <i class="bi bi-hdd-rack text-primary me-2"></i>
                                                <strong><?= htmlspecialchars($olt['name']) ?></strong>
                                            </td>
                                            <td><code><?= htmlspecialchars($olt['ip_address']) ?></code></td>
                                            <td>
                                                <span class="badge bg-success"><?= $oltStats['online'] ?></span>
                                                <span class="badge bg-secondary"><?= $oltStats['offline'] ?></span>
                                            </td>
                                            <td>
                                                <?php if ($olt['is_active']): ?>
                                                <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Active</span>
                                                <?php else: ?>
                                                <span class="badge bg-secondary"><i class="bi bi-pause-circle me-1"></i>Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-muted small">
                                                <?= $olt['last_sync_at'] ? date('M j, H:i', strtotime($olt['last_sync_at'])) : 'Never' ?>
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
                <?php 
                // Get selected OLT from query param for signal health filtering
                $signalHealthOltId = isset($_GET['signal_olt']) ? (int)$_GET['signal_olt'] : null;
                $signalStats = $huaweiOLT->getONUSignalStats($signalHealthOltId);
                $issueONUs = $huaweiOLT->getONUsWithIssues($signalHealthOltId, 5);
                ?>
                <div class="col-md-4">
                    <div class="card shadow-sm mb-3">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-reception-4 me-2"></i>Signal Health</h6>
                        </div>
                        <div class="card-body pb-2">
                            <form method="get" class="mb-2" id="signalOltForm">
                                <input type="hidden" name="page" value="huawei-olt">
                                <div class="input-group input-group-sm">
                                    <select name="signal_olt" id="signalOltSelect" class="form-select form-select-sm" onchange="this.form.submit()">
                                        <option value="">All OLTs</option>
                                        <?php foreach ($olts as $olt): ?>
                                        <option value="<?= $olt['id'] ?>" <?= $signalHealthOltId == $olt['id'] ? 'selected' : '' ?>><?= htmlspecialchars($olt['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </form>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="action" value="check_signal_health">
                                <input type="hidden" name="olt_id" value="<?= $signalHealthOltId ?? '' ?>">
                                <button type="submit" class="btn btn-sm btn-outline-primary w-100" title="Run signal health check on selected OLT">
                                    <i class="bi bi-arrow-repeat me-1"></i>Run Health Check<?= $signalHealthOltId ? ' (' . htmlspecialchars($olts[array_search($signalHealthOltId, array_column($olts, 'id'))]['name'] ?? 'Selected') . ')' : ' (All)' ?>
                                </button>
                            </form>
                        </div>
                        <div class="card-body pt-0">
                            <div class="row text-center">
                                <div class="col-4">
                                    <div class="text-muted small">Good</div>
                                    <div class="fs-5 fw-bold text-success"><?= $signalStats['good_signal'] ?? 0 ?></div>
                                </div>
                                <div class="col-4">
                                    <div class="text-muted small">Warning</div>
                                    <div class="fs-5 fw-bold text-warning"><?= $signalStats['warning_signal'] ?? 0 ?></div>
                                </div>
                                <div class="col-4">
                                    <div class="text-muted small">Critical</div>
                                    <div class="fs-5 fw-bold text-danger"><?= $signalStats['critical_signal'] ?? 0 ?></div>
                                </div>
                            </div>
                            <div class="row text-center mt-2">
                                <div class="col-4">
                                    <div class="text-muted small">LOS</div>
                                    <div class="fs-6 fw-bold text-danger"><?= $signalStats['los'] ?? 0 ?></div>
                                </div>
                                <div class="col-4">
                                    <div class="text-muted small">Offline</div>
                                    <div class="fs-6 fw-bold text-secondary"><?= $signalStats['offline'] ?? 0 ?></div>
                                </div>
                                <div class="col-4">
                                    <div class="text-muted small">Total</div>
                                    <div class="fs-6 fw-bold"><?= $signalStats['total'] ?? 0 ?></div>
                                </div>
                            </div>
                            <?php if (!empty($signalStats['avg_rx_power'])): ?>
                            <div class="text-center mt-2">
                                <small class="text-muted">Avg RX: <?= number_format((float)$signalStats['avg_rx_power'], 1) ?> dBm</small>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($issueONUs)): ?>
                            <hr class="my-2">
                            <div class="small">
                                <strong>Issues:</strong>
                                <?php foreach ($issueONUs as $issue): ?>
                                <div class="d-flex justify-content-between align-items-center py-1 border-bottom">
                                    <span class="text-truncate" style="max-width: 140px;" title="<?= htmlspecialchars($issue['sn']) ?>">
                                        <?= htmlspecialchars($issue['description'] ?: $issue['sn']) ?>
                                    </span>
                                    <span class="badge bg-<?= strtolower($issue['status']) === 'los' ? 'danger' : (($issue['rx_power'] ?? 0) <= -28 ? 'danger' : 'warning') ?>">
                                        <?= strtolower($issue['status']) === 'los' ? 'LOS' : (isset($issue['rx_power']) ? number_format($issue['rx_power'], 1) . ' dBm' : 'N/A') ?>
                                    </span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="card shadow-sm">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="bi bi-bell me-2"></i>Recent Alerts</h6>
                            <a href="?page=huawei-olt&view=alerts" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($alerts)): ?>
                            <div class="p-4 text-center text-muted">
                                <i class="bi bi-check-circle fs-1 mb-2 d-block text-success"></i>
                                No alerts
                            </div>
                            <?php else: ?>
                            <div class="list-group list-group-flush" style="max-height: 300px; overflow-y: auto;">
                                <?php foreach (array_slice($alerts, 0, 10) as $alert): ?>
                                <div class="list-group-item <?= !$alert['is_read'] ? 'bg-light' : '' ?>">
                                    <div class="d-flex align-items-center">
                                        <?php
                                        $severityIcon = ['info' => 'info-circle text-info', 'warning' => 'exclamation-triangle text-warning', 'critical' => 'exclamation-circle text-danger'];
                                        ?>
                                        <i class="bi bi-<?= $severityIcon[$alert['severity']] ?? 'info-circle text-info' ?> me-2"></i>
                                        <div class="flex-grow-1">
                                            <div class="small fw-bold"><?= htmlspecialchars($alert['title']) ?></div>
                                            <div class="small text-muted"><?= date('M j, H:i', strtotime($alert['created_at'])) ?></div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Realtime Dashboard Updates -->
            <script>
            (function() {
                let realtimeInterval = null;
                const REFRESH_INTERVAL = 10000; // 10 seconds
                
                function updateStats(data) {
                    if (!data.success) return;
                    
                    const stats = data.stats;
                    const totalOnus = stats.online_onus + stats.offline_onus + stats.los_onus;
                    const uptimePercent = totalOnus > 0 ? ((stats.online_onus / totalOnus) * 100).toFixed(1) : 0;
                    
                    // Update stat values with animation
                    const totalPending = (stats.unconfigured_onus || 0) + (stats.discovered_onus || 0);
                    updateElement('.stat-card.stat-success .stat-value', stats.online_onus.toLocaleString());
                    updateElement('.stat-card.stat-danger .stat-value', (stats.offline_onus + stats.los_onus).toString());
                    updateElement('.stat-card.stat-warning .stat-value', totalPending.toString());
                    
                    // Update pending button
                    const pendingBtn = document.querySelector('a[href*="unconfigured=1"].btn-warning');
                    if (pendingBtn) {
                        pendingBtn.innerHTML = `<i class="bi bi-hourglass-split me-1"></i> Pending (${totalPending})`;
                    }
                    
                    // Update timestamp
                    const timestampEl = document.querySelector('.text-muted.small');
                    if (timestampEl && timestampEl.textContent.includes('Last updated')) {
                        timestampEl.textContent = 'Last updated: ' + new Date().toLocaleString('en-US', {
                            month: 'short', day: 'numeric', year: 'numeric', 
                            hour: '2-digit', minute: '2-digit', second: '2-digit'
                        });
                    }
                    
                    // Pulse the live indicator
                    const liveIndicator = document.querySelector('.live-indicator');
                    if (liveIndicator) {
                        liveIndicator.classList.add('pulse-once');
                        setTimeout(() => liveIndicator.classList.remove('pulse-once'), 500);
                    }
                }
                
                function updateElement(selector, value) {
                    const el = document.querySelector(selector);
                    if (el && el.textContent !== value) {
                        el.classList.add('value-updated');
                        el.textContent = value;
                        setTimeout(() => el.classList.remove('value-updated'), 1000);
                    }
                }
                
                async function fetchRealtimeStats() {
                    try {
                        const resp = await fetch('?page=huawei-olt&ajax=realtime_stats');
                        const data = await resp.json();
                        updateStats(data);
                    } catch (e) {
                        console.error('Realtime stats error:', e);
                    }
                }
                
                // Start polling
                realtimeInterval = setInterval(fetchRealtimeStats, REFRESH_INTERVAL);
                
                // Cleanup on page unload
                window.addEventListener('beforeunload', () => {
                    if (realtimeInterval) clearInterval(realtimeInterval);
                });
            })();
            </script>
            <style>
            .value-updated {
                animation: valueFlash 0.5s ease-out;
            }
            @keyframes valueFlash {
                0% { background-color: rgba(25, 135, 84, 0.3); }
                100% { background-color: transparent; }
            }
            .pulse-once {
                animation: pulseLive 0.5s ease-out;
            }
            @keyframes pulseLive {
                0%, 100% { opacity: 1; }
                50% { opacity: 0.5; }
            }
            </style>
            
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
                            <thead class="table-light">
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
                            <thead class="table-light">
                                <tr>
                                    <th>Serial Number</th>
                                    <th>ONU Type</th>
                                    <th>Name</th>
                                    <th>Zone</th>
                                    <th>VLAN</th>
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
                                        <?php
                                        $statusConfig = [
                                            'online' => ['class' => 'success', 'icon' => 'check-circle-fill', 'label' => 'Online'],
                                            'offline' => ['class' => 'secondary', 'icon' => 'circle', 'label' => 'Offline'],
                                            'los' => ['class' => 'danger', 'icon' => 'exclamation-triangle-fill', 'label' => 'LOS'],
                                            'power_fail' => ['class' => 'warning', 'icon' => 'lightning-fill', 'label' => 'Power Fail'],
                                            'dyinggasp' => ['class' => 'warning', 'icon' => 'lightning-fill', 'label' => 'Dying Gasp'],
                                        ];
                                        $status = strtolower($onu['status'] ?? 'offline');
                                        $cfg = $statusConfig[$status] ?? ['class' => 'secondary', 'icon' => 'question-circle', 'label' => ucfirst($status)];
                                        ?>
                                        <span class="badge bg-<?= $cfg['class'] ?>">
                                            <i class="bi bi-<?= $cfg['icon'] ?> me-1"></i><?= $cfg['label'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $rx = $onu['rx_power'];
                                        $tx = $onu['tx_power'];
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
                                        $distance = $onu['distance'] ?? null;
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
                                        <button class="btn btn-sm btn-outline-primary" onclick="rebootOnu(<?= $onu['id'] ?>)" title="Reboot ONU">
                                            <i class="bi bi-arrow-clockwise"></i>
                                        </button>
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
                const REFRESH_INTERVAL = 15000; // 15 seconds for ONU list
                
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
            $rx = $currentOnu['rx_power'];
            $tx = $currentOnu['tx_power'];
            $distance = $currentOnu['distance'] ?? null;
            $distanceDisplay = $distance ? ($distance >= 1000 ? number_format($distance/1000, 2).'km' : $distance.'m') : '-';
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
            .onu-hero { background: #fff; border-radius: 1rem; color: #1e293b; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; }
            .onu-hero .stat-card { background: #f8fafc; border-radius: 0.75rem; padding: 1rem; border: 1px solid #e2e8f0; }
            .onu-hero .stat-value { font-size: 1.5rem; font-weight: 700; color: #1e293b; }
            .onu-hero .stat-label { font-size: 0.75rem; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px; }
            .quick-action-card { transition: all 0.2s ease; border: 2px solid transparent; }
            .quick-action-card:hover { transform: translateY(-2px); border-color: var(--oms-accent); box-shadow: 0 8px 25px rgba(59,130,246,0.15); }
            .quick-action-card .icon-wrapper { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; }
            .config-section { border-left: 3px solid var(--oms-accent); }
            .signal-gauge { height: 8px; border-radius: 4px; background: #e2e8f0; overflow: hidden; }
            .signal-gauge-fill { height: 100%; border-radius: 4px; transition: width 0.3s ease; }
            .pulse-online { animation: pulse-green 2s infinite; }
            @keyframes pulse-green { 0%, 100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4); } 50% { box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); } }
            </style>
            
            <!-- Breadcrumb Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <a href="?page=huawei-olt&view=onus<?= $currentOnu['olt_id'] ? '&olt_id='.$currentOnu['olt_id'] : '' ?>" class="text-decoration-none text-muted">
                        <i class="bi bi-arrow-left me-1"></i> Back to ONUs
                    </a>
                    <h4 class="mb-0 mt-2">
                        <i class="bi bi-router text-primary me-2"></i>
                        <?= htmlspecialchars($currentOnu['name'] ?: $currentOnu['sn']) ?>
                    </h4>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-primary" onclick="fetchLiveOnuData()">
                        <i class="bi bi-arrow-clockwise me-1"></i> Refresh
                    </button>
                    <button type="button" class="btn btn-success" id="liveBtn" onclick="toggleLiveMode()">
                        <i class="bi bi-broadcast me-1"></i> Live Mode
                    </button>
                </div>
            </div>
            
            <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $messageType ?? 'success' ?> alert-dismissible fade show" role="alert">
                <i class="bi bi-<?= $messageType === 'success' ? 'check-circle' : ($messageType === 'danger' ? 'x-circle' : 'info-circle') ?> me-2"></i>
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <!-- Hero Status Card -->
            <div class="onu-hero p-4 mb-4">
                <div class="row align-items-center">
                    <div class="col-md-3 text-center border-end mb-3 mb-md-0">
                        <div class="rounded-circle d-inline-flex align-items-center justify-content-center <?= $currentOnu['status'] === 'online' ? 'pulse-online' : '' ?>" 
                             style="width: 80px; height: 80px; background: <?= $currentOnu['status'] === 'online' ? 'rgba(16, 185, 129, 0.2)' : 'rgba(148, 163, 184, 0.2)' ?>">
                            <i class="bi bi-<?= $statusIcons[$currentOnu['status']] ?? 'question-circle' ?> text-<?= $statusColors[$currentOnu['status']] ?? 'secondary' ?>" style="font-size: 2.5rem;"></i>
                        </div>
                        <h5 data-live-status class="mt-2 mb-0 text-<?= $statusColors[$currentOnu['status']] ?? 'secondary' ?>"><?= ucfirst($currentOnu['status'] ?? 'Unknown') ?></h5>
                        <small class="text-muted">Device Status</small>
                    </div>
                    <div class="col-md-9">
                        <div class="row g-3">
                            <div class="col-6 col-md-3">
                                <div class="stat-card text-center">
                                    <div class="stat-value text-<?= $rxClass ?>" data-live-rx><?= $rx !== null ? number_format($rx, 1) : '-' ?></div>
                                    <div class="stat-label">RX Power (dBm)</div>
                                    <div class="signal-gauge mt-2">
                                        <div class="signal-gauge-fill bg-<?= $rxClass ?>" style="width: <?= $rx !== null ? min(100, max(0, (($rx + 30) / 20) * 100)) : 0 ?>%"></div>
                                    </div>
                                    <small class="text-<?= $rxClass ?>"><?= $rxLabel ?></small>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="stat-card text-center">
                                    <div class="stat-value" data-live-tx><?= $tx !== null ? number_format($tx, 1) : '-' ?></div>
                                    <div class="stat-label">TX Power (dBm)</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="stat-card text-center">
                                    <div class="stat-value"><?= $distanceDisplay ?></div>
                                    <div class="stat-label">Distance</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="stat-card text-center">
                                    <div class="stat-value"><?= !empty($currentOnu['vlan_id']) ? $currentOnu['vlan_id'] : '-' ?></div>
                                    <div class="stat-label">Service VLAN</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- SmartOLT-style ONU Info Header -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <div class="row">
                        <!-- Left: Basic Info (Editable) -->
                        <div class="col-md-4 border-end">
                            <form method="post" id="onuInfoForm">
                                <input type="hidden" name="action" value="update_onu_info">
                                <input type="hidden" name="onu_id" value="<?= $currentOnu['id'] ?>">
                                
                                <div class="mb-2">
                                    <span class="text-primary">Zone</span><br>
                                    <select name="zone_id" class="form-select form-select-sm" style="max-width: 200px;">
                                        <option value="">None</option>
                                        <?php foreach ($zones as $z): ?>
                                        <option value="<?= $z['id'] ?>" <?= ($currentOnu['zone_id'] == $z['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($z['name']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-2">
                                    <span class="text-primary">Name</span><br>
                                    <input type="text" name="name" class="form-control form-control-sm" style="max-width: 200px;" 
                                           value="<?= htmlspecialchars($currentOnu['name'] ?? '') ?>" placeholder="Customer name">
                                </div>
                                <div class="mb-2">
                                    <span class="text-primary">Address or comment</span><br>
                                    <input type="text" name="description" class="form-control form-control-sm" style="max-width: 200px;"
                                           value="<?= htmlspecialchars($currentOnu['description'] ?? '') ?>" placeholder="Description">
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm mt-1">
                                    <i class="bi bi-check me-1"></i> Save
                                </button>
                            </form>
                            <div class="mb-2">
                                <span class="text-primary">Customer</span><br>
                                <?php if ($currentOnu['customer_id']): ?>
                                <a href="?page=customers&action=view&id=<?= $currentOnu['customer_id'] ?>"><?= htmlspecialchars($currentOnu['customer_name'] ?? 'Linked') ?></a>
                                <?php else: ?>
                                None
                                <?php endif; ?>
                            </div>
                            <div class="mb-2">
                                <span class="text-primary">Authorization date</span><br>
                                <?= $currentOnu['auth_date'] ? date('d-M-Y H:i:s', strtotime($currentOnu['auth_date'])) : ($currentOnu['created_at'] ? date('d-M-Y H:i:s', strtotime($currentOnu['created_at'])) : '-') ?>
                            </div>
                            <div class="mb-2">
                                <span class="text-primary">ONU external ID</span><br>
                                <code class="text-primary"><?= htmlspecialchars($currentOnu['sn']) ?></code>
                            </div>
                        </div>
                        
                        <!-- Center: ONU Image & Service Profile -->
                        <div class="col-md-2 text-center d-flex align-items-center justify-content-center">
                            <div>
                                <i class="bi bi-router text-secondary" style="font-size: 4rem;"></i>
                                <div class="small mt-2">
                                    <span class="text-primary">Service Profile</span><br>
                                    <strong><?= htmlspecialchars($currentOnu['onu_type_model'] ?? $currentOnu['discovered_eqid'] ?? 'ONU') ?></strong>
                                </div>
                                <?php if (!empty($currentOnu['line_profile_name'])): ?>
                                <div class="small text-muted mt-1">
                                    Line: <?= htmlspecialchars($currentOnu['line_profile_name']) ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Right: Status Info -->
                        <div class="col-md-6">
                            <div class="mb-2">
                                <span class="text-primary">Status</span><br>
                                <?php
                                $statusColors = ['online' => 'success', 'offline' => 'secondary', 'los' => 'danger'];
                                ?>
                                <span data-live-status class="text-<?= $statusColors[$currentOnu['status']] ?? 'secondary' ?> fw-bold">
                                    <?= ucfirst($currentOnu['status'] ?? 'Unknown') ?>
                                </span>
                            </div>
                            <div class="mb-2">
                                <span class="text-primary">ONU/OLT Rx signal</span><br>
                                <span data-live-rx class="text-<?= $rxClass ?> fw-bold"><?= $rx !== null ? number_format($rx, 2) : '-' ?> dBm</span>
                                / <span data-live-tx><?= $tx !== null ? number_format($tx, 2) : '-' ?> dBm</span>
                                (<?= $distanceDisplay ?>)
                            </div>
                            <div class="mb-2">
                                <span class="text-primary">Attached VLANs</span>
                                <button type="button" class="btn btn-sm btn-outline-primary py-0 px-1 ms-2" data-bs-toggle="modal" data-bs-target="#attachedVlansModal" title="Edit Attached VLANs">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <br>
                                <?php 
                                $attachedVlans = [];
                                if (!empty($currentOnu['attached_vlans'])) {
                                    $attachedVlans = json_decode($currentOnu['attached_vlans'], true) ?: [];
                                } elseif (!empty($currentOnu['vlan_id'])) {
                                    $attachedVlans = [(int)$currentOnu['vlan_id']];
                                }
                                if (!empty($attachedVlans)): 
                                    foreach ($attachedVlans as $vid): ?>
                                <span class="badge bg-primary me-1"><?= htmlspecialchars($vid) ?></span>
                                <?php endforeach; 
                                else: ?>
                                <span class="text-muted">None</span>
                                <?php endif; ?>
                            </div>
                            <div class="mb-2">
                                <span class="text-primary">ONU mode</span>
                                <button type="button" class="btn btn-sm btn-outline-primary py-0 px-1 ms-2" onclick="toggleOnuModeEdit()" title="Edit ONU Mode">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <br>
                                <span id="onuModeDisplay">
                                    <span class="badge bg-<?= ($currentOnu['ip_mode'] ?? 'Bridge') === 'Bridge' ? 'secondary' : 'info' ?>"><?= $currentOnu['ip_mode'] ?? 'Bridge' ?></span>
                                    <?php if (($currentOnu['ip_mode'] ?? 'Bridge') !== 'Bridge' && !empty($currentOnu['wan_mode'])): ?>
                                    - <?= strtoupper($currentOnu['wan_mode']) ?>
                                    <?php endif; ?>
                                </span>
                                <div id="onuModeEdit" style="display: none;" class="mt-2 p-2 border rounded bg-light" style="max-width: 320px;">
                                    <div class="mb-2">
                                        <label class="form-label small mb-1">Mode</label>
                                        <select id="onuModeSelect" class="form-select form-select-sm" onchange="onModeChange()">
                                            <option value="Bridge" <?= ($currentOnu['ip_mode'] ?? 'Bridge') === 'Bridge' ? 'selected' : '' ?>>Bridge (OMCI)</option>
                                            <option value="Router" <?= ($currentOnu['ip_mode'] ?? '') === 'Router' ? 'selected' : '' ?>>Router (TR-069)</option>
                                        </select>
                                    </div>
                                    <div id="routerWanOptions" style="display: <?= ($currentOnu['ip_mode'] ?? 'Bridge') === 'Router' ? 'block' : 'none' ?>;">
                                        <div class="mb-2">
                                            <label class="form-label small mb-1">WAN Type</label>
                                            <select id="routerWanType" class="form-select form-select-sm" onchange="onWanTypeChange()">
                                                <option value="dhcp" <?= ($currentOnu['wan_mode'] ?? '') === 'dhcp' ? 'selected' : '' ?>>DHCP</option>
                                                <option value="static" <?= ($currentOnu['wan_mode'] ?? '') === 'static' ? 'selected' : '' ?>>Static IP</option>
                                                <option value="pppoe" <?= ($currentOnu['wan_mode'] ?? '') === 'pppoe' ? 'selected' : '' ?>>PPPoE</option>
                                            </select>
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label small mb-1">Service VLAN</label>
                                            <select id="routerServiceVlan" class="form-select form-select-sm">
                                                <?php if (empty($attachedVlans)): ?>
                                                <option value="">-- Attach VLANs first --</option>
                                                <?php else: foreach ($attachedVlans as $vid): ?>
                                                <option value="<?= $vid ?>" <?= ($currentOnu['vlan_id'] ?? '') == $vid ? 'selected' : '' ?>><?= $vid ?></option>
                                                <?php endforeach; endif; ?>
                                            </select>
                                        </div>
                                        <div id="pppoeFields2" style="display: <?= ($currentOnu['wan_mode'] ?? '') === 'pppoe' ? 'block' : 'none' ?>;">
                                            <div class="mb-2">
                                                <label class="form-label small mb-1">PPPoE Username</label>
                                                <input type="text" id="pppoeUser" class="form-control form-control-sm" value="<?= htmlspecialchars($currentOnu['pppoe_username'] ?? '') ?>" placeholder="username">
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label small mb-1">PPPoE Password</label>
                                                <input type="text" id="pppoePass" class="form-control form-control-sm" value="<?= htmlspecialchars($currentOnu['pppoe_password'] ?? '') ?>" placeholder="password">
                                            </div>
                                        </div>
                                        <div id="staticFields2" style="display: <?= ($currentOnu['wan_mode'] ?? '') === 'static' ? 'block' : 'none' ?>;">
                                            <div class="mb-2">
                                                <label class="form-label small mb-1">Static IP</label>
                                                <input type="text" id="staticIp" class="form-control form-control-sm" placeholder="192.168.1.100">
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label small mb-1">Gateway</label>
                                                <input type="text" id="staticGw" class="form-control form-control-sm" placeholder="192.168.1.1">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-flex gap-2 mt-2">
                                        <button type="button" class="btn btn-success btn-sm" onclick="saveOnuMode()">
                                            <i class="bi bi-check me-1"></i> Save
                                        </button>
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="toggleOnuModeEdit()">
                                            <i class="bi bi-x me-1"></i> Cancel
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-2">
                                <span class="text-primary">TR069</span><br>
                                <?php 
                                $tr069Status = $currentOnu['tr069_status'] ?? 'pending';
                                ?>
                                <span class="text-<?= $tr069Status === 'configured' ? 'success' : 'warning' ?>">
                                    <?= $tr069Status === 'configured' ? 'Configured' : 'Pending' ?>
                                </span>
                            </div>
                            <div class="mb-2">
                                <span class="text-primary">Mgmt IP</span><br>
                                <?php if (!empty($currentOnu['tr069_ip'])): ?>
                                <span class="text-success">DHCP - vlan 69 - <?= htmlspecialchars($currentOnu['tr069_ip']) ?></span>
                                <a href="#" onclick="refreshTR069IP(); return false;" title="Refresh"><i class="bi bi-arrow-clockwise"></i></a>
                                <?php else: ?>
                                <span class="text-muted">Waiting for TR-069 connection...</span>
                                <?php endif; ?>
                            </div>
                            <div class="mb-2">
                                <span class="text-primary">WAN setup mode</span>
                                <button type="button" class="btn btn-sm btn-outline-primary py-0 px-1 ms-2" data-bs-toggle="modal" data-bs-target="#wanConfigModal" title="Configure WAN">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <br>
                                <?php if (!empty($currentOnu['pppoe_username'])): ?>
                                <span class="badge bg-info">PPPoE (TR069)</span> - <?= htmlspecialchars($currentOnu['pppoe_username']) ?>
                                <?php elseif (!empty($currentOnu['wan_mode'])): ?>
                                <span class="badge bg-success"><?= strtoupper($currentOnu['wan_mode']) ?></span>
                                <?php else: ?>
                                <span class="badge bg-secondary">Not configured</span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($currentOnu['pppoe_username'])): ?>
                            <div class="mb-2">
                                <span class="text-primary">PPPoE Username</span><br>
                                <span class="badge bg-dark"><?= htmlspecialchars($currentOnu['pppoe_username']) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Define JS functions BEFORE buttons use them -->
            <script>
            const onuOltId = <?= $currentOnu['olt_id'] ?? 'null' ?>;
            const onuFrame = <?= $currentOnu['frame'] ?? 0 ?>;
            const onuSlot = <?= $currentOnu['slot'] ?? 'null' ?>;
            const onuPort = <?= $currentOnu['port'] ?? 'null' ?>;
            const onuIdVal = <?= $currentOnu['onu_id'] ?? 'null' ?>;
            const onuSn = '<?= htmlspecialchars($currentOnu['sn'] ?? '') ?>';
            const onuDbId = <?= $currentOnu['id'] ?? 'null' ?>;
            let liveIntervalId = null;
            
            async function fetchLiveOnuData() {
                if (!onuOltId || onuSlot === null) {
                    alert('Missing OLT information');
                    return;
                }
                
                try {
                    const resp = await fetch(`?page=api&action=huawei_live_onu&olt_id=${onuOltId}&frame=${onuFrame}&slot=${onuSlot}&port=${onuPort}&onu_id=${onuIdVal}&sn=${encodeURIComponent(onuSn)}`);
                    const data = await resp.json();
                    
                    if (data.success && data.onu) {
                        const onu = data.onu;
                        const statusEl = document.querySelector('[data-live-status]');
                        const rxEl = document.querySelector('[data-live-rx]');
                        const txEl = document.querySelector('[data-live-tx]');
                        
                        if (statusEl) {
                            const statusClass = {'online': 'success', 'offline': 'secondary', 'los': 'danger'}[onu.status] || 'secondary';
                            statusEl.className = 'text-' + statusClass + ' fw-bold';
                            statusEl.textContent = onu.status ? onu.status.charAt(0).toUpperCase() + onu.status.slice(1) : 'Unknown';
                        }
                        if (rxEl && onu.rx_power !== null) {
                            rxEl.textContent = onu.rx_power.toFixed(2) + ' dBm';
                        }
                        if (txEl && onu.tx_power !== null) {
                            txEl.textContent = onu.tx_power.toFixed(2) + ' dBm';
                        }
                        
                        const toast = document.createElement('div');
                        toast.className = 'position-fixed bottom-0 end-0 p-3';
                        toast.style.zIndex = '9999';
                        toast.innerHTML = '<div class="toast show bg-success text-white"><div class="toast-body"><i class="bi bi-check-circle me-2"></i>Status: ' + (onu.status || 'Unknown') + ', RX: ' + (onu.rx_power !== null ? onu.rx_power.toFixed(1) + ' dBm' : 'N/A') + '</div></div>';
                        document.body.appendChild(toast);
                        setTimeout(() => toast.remove(), 3000);
                    } else {
                        alert('Could not fetch live data: ' + (data.error || 'ONU not found'));
                    }
                } catch (e) {
                    console.error(e);
                    alert('Error fetching live data');
                }
            }
            
            function toggleLiveMode() {
                const btn = document.getElementById('liveBtn');
                if (liveIntervalId) {
                    clearInterval(liveIntervalId);
                    liveIntervalId = null;
                    btn.classList.remove('btn-success');
                    btn.classList.add('btn-danger');
                    btn.innerHTML = '<i class="bi bi-record-circle me-1"></i> LIVE!';
                } else {
                    fetchLiveOnuData();
                    liveIntervalId = setInterval(fetchLiveOnuData, 5000);
                    btn.classList.remove('btn-danger');
                    btn.classList.add('btn-success');
                    btn.innerHTML = '<i class="bi bi-stop-circle me-1"></i> STOP';
                }
            }
            
            function toggleOnuModeEdit() {
                const display = document.getElementById('onuModeDisplay');
                const edit = document.getElementById('onuModeEdit');
                if (edit.style.display === 'none') {
                    display.style.display = 'none';
                    edit.style.display = 'block';
                } else {
                    display.style.display = 'inline';
                    edit.style.display = 'none';
                }
            }
            
            function onModeChange() {
                const mode = document.getElementById('onuModeSelect').value;
                document.getElementById('routerWanOptions').style.display = mode === 'Router' ? 'block' : 'none';
            }
            
            function onWanTypeChange() {
                const wanType = document.getElementById('routerWanType').value;
                document.getElementById('pppoeFields2').style.display = wanType === 'pppoe' ? 'block' : 'none';
                document.getElementById('staticFields2').style.display = wanType === 'static' ? 'block' : 'none';
            }
            
            async function saveOnuMode() {
                const mode = document.getElementById('onuModeSelect').value;
                const formData = new FormData();
                formData.append('onu_id', onuDbId);
                formData.append('ip_mode', mode);
                
                if (mode === 'Router') {
                    const wanType = document.getElementById('routerWanType').value;
                    const serviceVlan = document.getElementById('routerServiceVlan').value;
                    formData.append('wan_mode', wanType);
                    formData.append('service_vlan', serviceVlan);
                    
                    if (wanType === 'pppoe') {
                        formData.append('pppoe_username', document.getElementById('pppoeUser').value);
                        formData.append('pppoe_password', document.getElementById('pppoePass').value);
                    } else if (wanType === 'static') {
                        formData.append('static_ip', document.getElementById('staticIp').value);
                        formData.append('gateway', document.getElementById('staticGw').value);
                    }
                }
                
                try {
                    const resp = await fetch('?page=api&action=update_onu_mode', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await resp.json();
                    
                    if (data.success) {
                        const display = document.getElementById('onuModeDisplay');
                        const badgeClass = mode === 'Bridge' ? 'secondary' : 'info';
                        let displayText = `<span class="badge bg-${badgeClass}">${mode}</span>`;
                        if (mode === 'Router') {
                            const wanType = document.getElementById('routerWanType').value;
                            displayText += ` - ${wanType.toUpperCase()}`;
                        }
                        display.innerHTML = displayText;
                        toggleOnuModeEdit();
                        
                        const toast = document.createElement('div');
                        toast.className = 'position-fixed bottom-0 end-0 p-3';
                        toast.style.zIndex = '9999';
                        toast.innerHTML = '<div class="toast show bg-success text-white"><div class="toast-body"><i class="bi bi-check-circle me-2"></i>' + data.message + '</div></div>';
                        document.body.appendChild(toast);
                        setTimeout(() => toast.remove(), 3000);
                    } else {
                        alert('Error: ' + (data.error || 'Unknown error'));
                    }
                } catch (err) {
                    alert('Network error: ' + err.message);
                }
            }
            </script>
            
            <!-- Quick Actions Grid -->
            <div class="row g-3 mb-4">
                <!-- OLT Actions -->
                <div class="col-md-6 col-lg-3">
                    <div class="card quick-action-card h-100 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="icon-wrapper bg-primary bg-opacity-10 text-primary me-3">
                                    <i class="bi bi-terminal"></i>
                                </div>
                                <h6 class="mb-0">OLT Commands</h6>
                            </div>
                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="getOnuConfig(<?= $currentOnu['id'] ?>)">
                                    <i class="bi bi-code-slash me-1"></i> Running Config
                                </button>
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="getOnuSWInfo(<?= $currentOnu['id'] ?>)">
                                    <i class="bi bi-cpu me-1"></i> Software Info
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- TR-069 Actions -->
                <div class="col-md-6 col-lg-3">
                    <div class="card quick-action-card h-100 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="icon-wrapper bg-info bg-opacity-10 text-info me-3">
                                    <i class="bi bi-broadcast"></i>
                                </div>
                                <h6 class="mb-0">TR-069 Remote</h6>
                            </div>
                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-outline-info btn-sm" onclick="getTR069Stat(<?= $currentOnu['id'] ?>)">
                                    <i class="bi bi-info-circle me-1"></i> Device Info
                                </button>
                                <form method="post" class="d-grid">
                                    <input type="hidden" name="action" value="tr069_refresh">
                                    <input type="hidden" name="onu_id" value="<?= $currentOnu['id'] ?>">
                                    <button type="submit" class="btn btn-outline-info btn-sm">
                                        <i class="bi bi-arrow-repeat me-1"></i> Refresh Data
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Device Control -->
                <div class="col-md-6 col-lg-3">
                    <div class="card quick-action-card h-100 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="icon-wrapper bg-warning bg-opacity-10 text-warning me-3">
                                    <i class="bi bi-power"></i>
                                </div>
                                <h6 class="mb-0">Device Control</h6>
                            </div>
                            <div class="d-grid gap-2">
                                <form method="post" class="d-grid" onsubmit="return confirm('Reboot this ONU via OLT?')">
                                    <input type="hidden" name="action" value="reboot_onu">
                                    <input type="hidden" name="onu_id" value="<?= $currentOnu['id'] ?>">
                                    <button type="submit" class="btn btn-warning btn-sm">
                                        <i class="bi bi-arrow-clockwise me-1"></i> Reboot (OLT)
                                    </button>
                                </form>
                                <form method="post" class="d-grid" onsubmit="return confirm('Reboot via TR-069?')">
                                    <input type="hidden" name="action" value="tr069_reboot">
                                    <input type="hidden" name="onu_id" value="<?= $currentOnu['id'] ?>">
                                    <button type="submit" class="btn btn-outline-warning btn-sm">
                                        <i class="bi bi-arrow-clockwise me-1"></i> Reboot (TR-069)
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Danger Zone -->
                <div class="col-md-6 col-lg-3">
                    <div class="card quick-action-card h-100 shadow-sm border-danger border-opacity-25">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="icon-wrapper bg-danger bg-opacity-10 text-danger me-3">
                                    <i class="bi bi-exclamation-triangle"></i>
                                </div>
                                <h6 class="mb-0 text-danger">Danger Zone</h6>
                            </div>
                            <div class="d-grid gap-2">
                                <form method="post" class="d-grid" onsubmit="return confirm('FACTORY RESET? All settings will be erased!')">
                                    <input type="hidden" name="action" value="tr069_factory_reset">
                                    <input type="hidden" name="onu_id" value="<?= $currentOnu['id'] ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-sm">
                                        <i class="bi bi-arrow-counterclockwise me-1"></i> Factory Reset
                                    </button>
                                </form>
                                <form method="post" class="d-grid" onsubmit="return confirm('DELETE this ONU from OLT?')">
                                    <input type="hidden" name="action" value="delete_onu_olt">
                                    <input type="hidden" name="onu_id" value="<?= $currentOnu['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">
                                        <i class="bi bi-trash me-1"></i> Delete ONU
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Traffic/Signal Charts Row -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-dark text-white py-2">
                            <small><i class="bi bi-graph-up me-1"></i> Traffic</small>
                        </div>
                        <div class="card-body p-2">
                            <canvas id="trafficChart" height="100"></canvas>
                            <div class="d-flex justify-content-between small text-muted mt-1">
                                <span><span class="text-warning">Upload:</span> <span id="uploadRate">0.00 Mbps</span></span>
                                <span><span class="text-info">Download:</span> <span id="downloadRate">0.00 Mbps</span></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-dark text-white py-2">
                            <small><i class="bi bi-reception-4 me-1"></i> Signal</small>
                        </div>
                        <div class="card-body p-2">
                            <canvas id="signalHistoryChart" height="100"></canvas>
                            <div class="d-flex justify-content-between small text-muted mt-1">
                                <span><span class="text-danger">1310nm OLT Rx for ONU:</span> Current: <?= $rx !== null ? number_format($rx, 1) : '-' ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Speed Profiles (DBA) / Service-ports Table -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                    <strong>Speed profiles (DBA)</strong>
                    <small class="text-muted">Dynamic Bandwidth Allocation</small>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Service-port ID</th>
                                <th>User-VLAN</th>
                                <th>DBA Profile</th>
                                <th>Download</th>
                                <th>Upload</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="servicePortsTable">
                            <?php if (!empty($currentOnu['vlan_id'])): ?>
                            <?php
                            // Get DBA profile info if available
                            $dbaProfile = null;
                            if (!empty($currentOnu['dba_profile_id'])) {
                                $dbaStmt = $db->prepare("SELECT * FROM huawei_dba_profiles WHERE id = ?");
                                $dbaStmt->execute([$currentOnu['dba_profile_id']]);
                                $dbaProfile = $dbaStmt->fetch(PDO::FETCH_ASSOC);
                            }
                            $downloadSpeed = $dbaProfile['assured_bandwidth'] ?? ($currentOnu['download_speed'] ?? '1G');
                            $uploadSpeed = $dbaProfile['max_bandwidth'] ?? ($currentOnu['upload_speed'] ?? '1G');
                            $dbaName = $dbaProfile['name'] ?? 'Default';
                            ?>
                            <tr>
                                <td><?= $currentOnu['onu_id'] ?? '-' ?></td>
                                <td><span class="badge bg-primary"><?= $currentOnu['vlan_id'] ?></span></td>
                                <td><span class="badge bg-info"><?= htmlspecialchars($dbaName) ?></span></td>
                                <td><?= is_numeric($downloadSpeed) ? number_format($downloadSpeed/1000).' Mbps' : $downloadSpeed ?></td>
                                <td><?= is_numeric($uploadSpeed) ? number_format($uploadSpeed/1000).' Mbps' : $uploadSpeed ?></td>
                                <td><a href="#" class="text-primary" onclick="configureSpeedProfile(<?= $currentOnu['id'] ?>, <?= $currentOnu['vlan_id'] ?>)">Configure</a></td>
                            </tr>
                            <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-muted text-center">No service ports configured</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Ethernet Ports Table -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light py-2">
                    <strong>Ethernet ports</strong>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Port</th>
                                <th>Admin state</th>
                                <th>Mode</th>
                                <th>DHCP</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="ethPortsTable">
                            <?php 
                            $ethPorts = $currentOnu['type_eth_ports'] ?? 4;
                            for ($i = 1; $i <= $ethPorts; $i++): 
                            ?>
                            <tr>
                                <td>eth_0/<?= $i ?></td>
                                <td><span class="text-success">Enabled</span></td>
                                <td>LAN</td>
                                <td>No control</td>
                                <td><a href="#" class="text-primary" onclick="configureEthPort(<?= $currentOnu['id'] ?>, <?= $i ?>)">Configure</a></td>
                            </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- WiFi Section - Dynamically loads from TR-069 -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                    <strong>WiFi</strong>
                    <div>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="loadWiFiFromTR069()" id="wifiRefreshBtn">
                            <i class="bi bi-arrow-clockwise me-1"></i> Refresh from TR-069
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div id="wifiLoadingIndicator" class="text-center py-3 d-none">
                        <div class="spinner-border spinner-border-sm text-primary me-2"></div>
                        <span class="text-muted">Loading WiFi data from TR-069...</span>
                    </div>
                    <div id="wifiNotConnected" class="text-center py-3 <?= empty($currentOnu['tr069_ip']) ? '' : 'd-none' ?>">
                        <i class="bi bi-wifi-off text-muted fs-4 d-block mb-2"></i>
                        <span class="text-muted">Device not connected to TR-069. WiFi data will be available when the device connects to the ACS.</span>
                    </div>
                    <table class="table table-sm table-hover mb-0 <?= empty($currentOnu['tr069_ip']) ? 'd-none' : '' ?>" id="wifiTable">
                        <thead class="table-light">
                            <tr>
                                <th>Port</th>
                                <th>Admin state</th>
                                <th>Mode</th>
                                <th>SSID</th>
                                <th>DHCP</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="wifiPortsTable">
                            <tr>
                                <td colspan="6" class="text-muted text-center">
                                    <span class="spinner-border spinner-border-sm me-1"></span> Loading...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <script>
            const onuSerial = '<?= htmlspecialchars($currentOnu['sn']) ?>';
            const onuId = <?= $currentOnu['id'] ?>;
            const hasTR069 = <?= !empty($currentOnu['tr069_ip']) ? 'true' : 'false' ?>;
            
            function loadWiFiFromTR069() {
                if (!hasTR069) return;
                
                const tbody = document.getElementById('wifiPortsTable');
                const loading = document.getElementById('wifiLoadingIndicator');
                const table = document.getElementById('wifiTable');
                const notConnected = document.getElementById('wifiNotConnected');
                const refreshBtn = document.getElementById('wifiRefreshBtn');
                
                loading.classList.remove('d-none');
                refreshBtn.disabled = true;
                
                fetch('?page=huawei-olt', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'action=get_tr069_wifi&serial=' + encodeURIComponent(onuSerial)
                })
                .then(async r => {
                    const text = await r.text();
                    if (!text || text.trim() === '') {
                        throw new Error('Empty response from server');
                    }
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Invalid JSON response:', text);
                        throw new Error('Invalid response from TR-069 service');
                    }
                })
                .then(data => {
                    loading.classList.add('d-none');
                    refreshBtn.disabled = false;
                    
                    if (!data.success) {
                        tbody.innerHTML = '<tr><td colspan="6" class="text-warning text-center">' + (data.error || 'Failed to load WiFi data') + '</td></tr>';
                        return;
                    }
                    
                    if (!data.interfaces || data.interfaces.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="6" class="text-muted text-center">No WiFi interfaces found on this device</td></tr>';
                        return;
                    }
                    
                    let html = '';
                    data.interfaces.forEach((iface, idx) => {
                        const band = iface.band || (idx === 0 ? '2.4GHz' : '5GHz');
                        const enabled = iface.enabled ? '<span class="text-success">Enabled</span>' : '<span class="text-danger">Disabled</span>';
                        const ssid = iface.ssid || '<span class="text-muted">-</span>';
                        const mode = iface.mode || 'LAN';
                        
                        html += '<tr>';
                        html += '<td>wifi_0/' + (idx + 1) + ' <small class="text-muted">(' + band + ')</small></td>';
                        html += '<td>' + enabled + '</td>';
                        html += '<td>' + mode + '</td>';
                        html += '<td><strong>' + ssid + '</strong></td>';
                        html += '<td>No control</td>';
                        html += '<td><a href="#" class="text-primary" onclick="openTR069WiFiConfig(\'' + onuSerial + '\')">Configure</a></td>';
                        html += '</tr>';
                    });
                    
                    tbody.innerHTML = html;
                    table.classList.remove('d-none');
                    notConnected.classList.add('d-none');
                })
                .catch(err => {
                    loading.classList.add('d-none');
                    refreshBtn.disabled = false;
                    tbody.innerHTML = '<tr><td colspan="6" class="text-danger text-center">Error: ' + err.message + '</td></tr>';
                });
            }
            
            // Auto-load if TR-069 is connected
            if (hasTR069) {
                loadWiFiFromTR069();
            }
            </script>
            
            <!-- Signal History Chart -->
            <div class="row">
                <div class="col-12">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-graph-up me-2"></i>Signal History (Last 7 Days)</span>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-light active" data-days="7">7D</button>
                                <button class="btn btn-outline-light" data-days="30">30D</button>
                                <button class="btn btn-outline-light" data-days="90">90D</button>
                            </div>
                        </div>
                        <div class="card-body">
                            <canvas id="signalHistoryChart" height="120"></canvas>
                            <div id="signalHistoryLoading" class="text-center py-4 d-none">
                                <div class="spinner-border text-primary"></div>
                            </div>
                            <div id="signalHistoryNoData" class="text-center py-4 text-muted d-none">
                                <i class="bi bi-bar-chart fs-1 d-block mb-2"></i>
                                No signal history data available yet
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
            <script>
            (function() {
                const onuId = <?= $currentOnu['id'] ?>;
                let signalChart = null;
                
                async function loadSignalHistory(days = 7) {
                    const loading = document.getElementById('signalHistoryLoading');
                    const noData = document.getElementById('signalHistoryNoData');
                    const canvas = document.getElementById('signalHistoryChart');
                    
                    loading.classList.remove('d-none');
                    canvas.style.display = 'none';
                    noData.classList.add('d-none');
                    
                    try {
                        const resp = await fetch(`?page=huawei-olt&ajax=signal_history&onu_id=${onuId}&days=${days}`);
                        const data = await resp.json();
                        
                        loading.classList.add('d-none');
                        
                        if (!data.success || !data.history || data.history.length === 0) {
                            noData.classList.remove('d-none');
                            return;
                        }
                        
                        canvas.style.display = 'block';
                        
                        const labels = data.history.map(h => {
                            const d = new Date(h.recorded_at);
                            return d.toLocaleDateString('en-US', {month:'short', day:'numeric', hour:'2-digit'});
                        });
                        const rxData = data.history.map(h => h.rx_power);
                        const txData = data.history.map(h => h.tx_power);
                        
                        if (signalChart) signalChart.destroy();
                        
                        signalChart = new Chart(canvas, {
                            type: 'line',
                            data: {
                                labels: labels,
                                datasets: [{
                                    label: 'RX Power (dBm)',
                                    data: rxData,
                                    borderColor: 'rgb(75, 192, 192)',
                                    backgroundColor: 'rgba(75, 192, 192, 0.1)',
                                    tension: 0.3,
                                    fill: true
                                }, {
                                    label: 'TX Power (dBm)',
                                    data: txData,
                                    borderColor: 'rgb(255, 99, 132)',
                                    backgroundColor: 'rgba(255, 99, 132, 0.1)',
                                    tension: 0.3,
                                    fill: true
                                }]
                            },
                            options: {
                                responsive: true,
                                plugins: {
                                    legend: { position: 'top' },
                                    annotation: {
                                        annotations: {
                                            warningLine: {
                                                type: 'line',
                                                yMin: -25,
                                                yMax: -25,
                                                borderColor: 'orange',
                                                borderWidth: 1,
                                                borderDash: [5, 5],
                                                label: { content: 'Warning', display: true }
                                            },
                                            criticalLine: {
                                                type: 'line',
                                                yMin: -28,
                                                yMax: -28,
                                                borderColor: 'red',
                                                borderWidth: 1,
                                                borderDash: [5, 5],
                                                label: { content: 'Critical', display: true }
                                            }
                                        }
                                    }
                                },
                                scales: {
                                    y: {
                                        title: { display: true, text: 'Power (dBm)' },
                                        min: -35,
                                        max: 5
                                    }
                                }
                            }
                        });
                    } catch (e) {
                        console.error('Signal history error:', e);
                        loading.classList.add('d-none');
                        noData.classList.remove('d-none');
                    }
                }
                
                // Period selector buttons
                document.querySelectorAll('[data-days]').forEach(btn => {
                    btn.addEventListener('click', function() {
                        document.querySelectorAll('[data-days]').forEach(b => b.classList.remove('active'));
                        this.classList.add('active');
                        loadSignalHistory(parseInt(this.dataset.days));
                    });
                });
                
                // Initial load
                loadSignalHistory(7);
            })();
            
            // TR-069 IP refresh function
            async function refreshTR069IP() {
                const icon = document.getElementById('tr069RefreshIcon');
                const display = document.getElementById('tr069IpDisplay');
                const onuId = <?= $currentOnu['id'] ?>;
                
                icon.classList.add('spin-animation');
                
                try {
                    const formData = new FormData();
                    formData.append('action', 'refresh_tr069_ip');
                    formData.append('onu_id', onuId);
                    
                    const resp = await fetch('?page=huawei-olt', {
                        method: 'POST',
                        body: formData
                    });
                    
                    // Page will reload with updated IP
                    location.reload();
                } catch (e) {
                    console.error('Failed to refresh TR-069 IP:', e);
                    icon.classList.remove('spin-animation');
                    alert('Failed to refresh TR-069 IP');
                }
            }
            </script>
            <style>.spin-animation { animation: spin 1s linear infinite; } @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }</style>
            
            <script>
            function togglePortFields(portNum, mode) {
                const allowedInput = document.querySelector('.port-allowed-' + portNum);
                if (allowedInput) {
                    allowedInput.style.display = (mode === 'trunk' || mode === 'hybrid') ? 'block' : 'none';
                }
            }
            
            function toggleWanFields() {
                const wanType = document.getElementById('wanType');
                if (wanType) {
                    const pppoeFields = document.getElementById('pppoeFields');
                    const staticFields = document.getElementById('staticFields');
                    if (pppoeFields) pppoeFields.style.display = wanType.value === 'pppoe' ? 'block' : 'none';
                    if (staticFields) staticFields.style.display = wanType.value === 'static' ? 'block' : 'none';
                }
            }
            
            function togglePassword(id) {
                const input = document.getElementById(id);
                input.type = input.type === 'password' ? 'text' : 'password';
            }
            
            async function fetchLiveOnuData() {
                const oltId = <?= $currentOnu['olt_id'] ?? 'null' ?>;
                const frame = <?= $currentOnu['frame'] ?? 0 ?>;
                const slot = <?= $currentOnu['slot'] ?? 'null' ?>;
                const port = <?= $currentOnu['port'] ?? 'null' ?>;
                const onuIdVal = <?= $currentOnu['onu_id'] ?? 'null' ?>;
                const sn = '<?= htmlspecialchars($currentOnu['sn'] ?? '') ?>';
                
                if (!oltId || slot === null) {
                    alert('Missing OLT information');
                    return;
                }
                
                try {
                    const resp = await fetch(`?page=api&action=huawei_live_onu&olt_id=${oltId}&frame=${frame}&slot=${slot}&port=${port}&onu_id=${onuIdVal}&sn=${encodeURIComponent(sn)}`);
                    const data = await resp.json();
                    
                    if (data.success && data.onu) {
                        // Update status display on page
                        const onu = data.onu;
                        const statusEl = document.querySelector('[data-live-status]');
                        const rxEl = document.querySelector('[data-live-rx]');
                        const txEl = document.querySelector('[data-live-tx]');
                        
                        if (statusEl) {
                            const statusClass = {'online': 'success', 'offline': 'secondary', 'los': 'danger'}[onu.status] || 'secondary';
                            statusEl.className = 'text-' + statusClass + ' fw-bold';
                            statusEl.textContent = onu.status ? onu.status.charAt(0).toUpperCase() + onu.status.slice(1) : 'Unknown';
                        }
                        if (rxEl && onu.rx_power !== null) {
                            rxEl.textContent = onu.rx_power.toFixed(2) + ' dBm';
                        }
                        if (txEl && onu.tx_power !== null) {
                            txEl.textContent = onu.tx_power.toFixed(2) + ' dBm';
                        }
                        
                        // Show toast notification
                        const toast = document.createElement('div');
                        toast.className = 'position-fixed bottom-0 end-0 p-3';
                        toast.style.zIndex = '9999';
                        toast.innerHTML = '<div class="toast show bg-success text-white"><div class="toast-body"><i class="bi bi-check-circle me-2"></i>Live status updated: ' + (onu.status || 'Unknown') + ', RX: ' + (onu.rx_power !== null ? onu.rx_power.toFixed(1) + ' dBm' : 'N/A') + '</div></div>';
                        document.body.appendChild(toast);
                        setTimeout(() => toast.remove(), 3000);
                    } else {
                        alert('Could not fetch live data: ' + (data.error || 'ONU not found'));
                    }
                } catch (e) {
                    console.error(e);
                    alert('Error fetching live data');
                }
            }
            
            function updateLiveDisplay(onu) {
                const statusEl = document.getElementById('liveStatus');
                const rxEl = document.getElementById('liveRxPower');
                const txEl = document.getElementById('liveTxPower');
                const qualityEl = document.getElementById('liveSignalQuality');
                const barEl = document.getElementById('liveSignalBar');
                
                const statusMap = {
                    online: { class: 'success', icon: 'check-circle-fill' },
                    offline: { class: 'secondary', icon: 'circle' },
                    los: { class: 'danger', icon: 'exclamation-triangle-fill' },
                    power_fail: { class: 'warning', icon: 'exclamation-circle-fill' }
                };
                const st = statusMap[onu.status] || statusMap.offline;
                statusEl.className = `badge bg-${st.class} fs-6`;
                statusEl.innerHTML = `<i class="bi bi-${st.icon} me-1"></i>${onu.status ? onu.status.charAt(0).toUpperCase() + onu.status.slice(1) : 'Unknown'}`;
                
                const rx = onu.rx_power;
                let rxClass = 'success';
                let quality = 'Excellent';
                let pct = 100;
                
                if (rx !== null) {
                    if (rx <= -30) { rxClass = 'danger'; quality = 'Critical'; pct = 10; }
                    else if (rx <= -28) { rxClass = 'danger'; quality = 'Weak'; pct = 25; }
                    else if (rx <= -27) { rxClass = 'warning'; quality = 'Fair'; pct = 50; }
                    else if (rx <= -24) { rxClass = 'success'; quality = 'Good'; pct = 75; }
                    else { rxClass = 'success'; quality = 'Excellent'; pct = 100; }
                    
                    rxEl.className = `text-${rxClass} fw-bold`;
                    rxEl.textContent = rx.toFixed(1) + ' dBm';
                } else {
                    rxEl.className = 'text-muted fw-bold';
                    rxEl.textContent = 'N/A';
                    quality = 'N/A';
                    pct = 0;
                }
                
                if (onu.tx_power !== null) {
                    txEl.textContent = onu.tx_power.toFixed(1) + ' dBm';
                } else {
                    txEl.textContent = 'N/A';
                }
                
                // Update distance
                const distEl = document.getElementById('liveDistance');
                if (distEl) {
                    distEl.textContent = onu.distance !== null ? onu.distance + ' m' : 'N/A';
                }
                
                qualityEl.className = `badge bg-${rxClass}`;
                qualityEl.textContent = quality;
                
                barEl.className = `progress-bar bg-${rxClass}`;
                barEl.style.width = pct + '%';
            }
            </script>
            
            <!-- TR-069 Configuration Modal -->
            <?php
            // Get TR-069 settings for modal
            $tr069AcsUrl = '';
            $tr069DefaultVlan = '';
            $stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('tr069_acs_url', 'genieacs_url')");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if ($row['setting_key'] === 'tr069_acs_url') $tr069AcsUrl = $row['setting_value'];
                elseif ($row['setting_key'] === 'genieacs_url' && empty($tr069AcsUrl)) $tr069AcsUrl = $row['setting_value'];
            }
            // Get TR-069 VLANs for this OLT
            $tr069Vlans = [];
            $stmt = $db->prepare("SELECT vlan_id, description FROM huawei_vlans WHERE olt_id = ? AND is_tr069 = TRUE AND is_active = TRUE ORDER BY vlan_id");
            $stmt->execute([$currentOnu['olt_id']]);
            $tr069Vlans = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>
            <div class="modal fade" id="tr069ConfigModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-success text-white">
                            <h5 class="modal-title"><i class="bi bi-broadcast me-2"></i>Push TR-069 Configuration</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="post" id="tr069ConfigForm">
                            <div class="modal-body">
                                <input type="hidden" name="action" value="configure_tr069">
                                <input type="hidden" name="onu_id" value="<?= $currentOnu['id'] ?>">
                                
                                <div class="alert alert-info small">
                                    <i class="bi bi-info-circle me-1"></i>
                                    This will push TR-069 configuration directly to the ONU via OMCI commands on the OLT.
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">ACS Server URL</label>
                                    <input type="url" name="acs_url" class="form-control" value="<?= htmlspecialchars($tr069AcsUrl) ?>" placeholder="http://10.200.0.1:7547" required>
                                    <div class="form-text">The TR-069 ACS server URL (e.g., GenieACS)</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">TR-069 Management VLAN</label>
                                    <?php if (!empty($tr069Vlans)): ?>
                                    <select name="tr069_vlan" class="form-select" required>
                                        <?php foreach ($tr069Vlans as $vlan): ?>
                                        <option value="<?= $vlan['vlan_id'] ?>"><?= $vlan['vlan_id'] ?> - <?= htmlspecialchars($vlan['description'] ?: 'TR-069 VLAN') ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php else: ?>
                                    <input type="number" name="tr069_vlan" class="form-control" value="69" min="1" max="4094" required>
                                    <div class="form-text text-warning">No TR-069 VLANs configured for this OLT. Enter VLAN ID manually.</div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">GEM Port</label>
                                    <input type="number" name="gem_port" class="form-control" value="2" min="1" max="128">
                                    <div class="form-text">GEM port for TR-069 traffic (default: 2)</div>
                                </div>
                                
                                <div class="card bg-light">
                                    <div class="card-body small">
                                        <strong>ONU Details:</strong><br>
                                        SN: <?= htmlspecialchars($currentOnu['sn']) ?><br>
                                        Location: <?= $currentOnu['frame'] ?? 0 ?>/<?= $currentOnu['slot'] ?? 0 ?>/<?= $currentOnu['port'] ?? 0 ?> ONU <?= $currentOnu['onu_id'] ?? 'N/A' ?>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-success" id="btnPushTr069">
                                    <i class="bi bi-broadcast me-1"></i> Push Configuration
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <script>
            document.getElementById('tr069ConfigForm').addEventListener('submit', function(e) {
                const btn = document.getElementById('btnPushTr069');
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Pushing...';
            });
            </script>
            
            <!-- WAN Configuration Modal -->
            <div class="modal fade" id="wanConfigModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title"><i class="bi bi-ethernet me-2"></i>Configure Internet WAN</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <form id="wanConfigForm" onsubmit="return submitWanConfig(event)">
                            <div class="modal-body">
                                <input type="hidden" name="onu_id" value="<?= $currentOnu['id'] ?>">
                                
                                <div class="alert alert-info small">
                                    <i class="bi bi-info-circle me-1"></i>
                                    This configures the Internet WAN connection on the ONU via TR-069/GenieACS.
                                    <?php if (empty($currentOnu['tr069_ip'])): ?>
                                    <br><strong class="text-warning">Warning:</strong> TR-069 connection not yet established. Configure TR-069 first.
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">WAN Mode</label>
                                    <select name="wan_mode" id="wanModeSelect" class="form-select" required onchange="toggleWanFields()">
                                        <option value="">-- Select Mode --</option>
                                        <option value="pppoe" <?= ($currentOnu['wan_mode'] ?? '') === 'pppoe' ? 'selected' : '' ?>>PPPoE</option>
                                        <option value="dhcp" <?= ($currentOnu['wan_mode'] ?? '') === 'dhcp' ? 'selected' : '' ?>>DHCP (IPoE)</option>
                                        <option value="static" <?= ($currentOnu['wan_mode'] ?? '') === 'static' ? 'selected' : '' ?>>Static IP</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Service VLAN <small class="text-muted">(from attached VLANs)</small></label>
                                    <select name="service_vlan" id="serviceVlanSelect" class="form-select" required>
                                        <?php if (empty($attachedVlans)): ?>
                                        <option value="">-- No VLANs attached - Add VLANs first --</option>
                                        <?php else: 
                                            foreach ($attachedVlans as $vid): ?>
                                        <option value="<?= $vid ?>" <?= ($currentOnu['vlan_id'] ?? '') == $vid ? 'selected' : '' ?>>
                                            <?= $vid ?>
                                        </option>
                                        <?php endforeach; 
                                        endif; ?>
                                    </select>
                                    <?php if (empty($attachedVlans)): ?>
                                    <div class="form-text text-warning"><i class="bi bi-exclamation-triangle me-1"></i>Attach VLANs to this ONU first before configuring WAN.</div>
                                    <?php endif; ?>
                                </div>
                                
                                <div id="pppoeFields" style="display: none;">
                                    <div class="mb-3">
                                        <label class="form-label">PPPoE Username</label>
                                        <input type="text" name="pppoe_username" class="form-control" value="<?= htmlspecialchars($currentOnu['pppoe_username'] ?? '') ?>" placeholder="username@isp.com">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">PPPoE Password</label>
                                        <input type="text" name="pppoe_password" class="form-control" value="<?= htmlspecialchars($currentOnu['pppoe_password'] ?? '') ?>" placeholder="password">
                                    </div>
                                </div>
                                
                                <div id="staticFields" style="display: none;">
                                    <div class="mb-3">
                                        <label class="form-label">Static IP Address</label>
                                        <input type="text" name="static_ip" class="form-control" placeholder="192.168.1.100">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Subnet Mask</label>
                                        <input type="text" name="subnet_mask" class="form-control" value="255.255.255.0" placeholder="255.255.255.0">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Gateway</label>
                                        <input type="text" name="gateway" class="form-control" placeholder="192.168.1.1">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">DNS Servers</label>
                                        <input type="text" name="dns_servers" class="form-control" value="8.8.8.8,8.8.4.4" placeholder="8.8.8.8,8.8.4.4">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input type="checkbox" name="bind_lan_ports" id="bindLanPorts" class="form-check-input" checked>
                                        <label class="form-check-label" for="bindLanPorts">Bind LAN ports to this WAN</label>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input type="checkbox" name="bind_wifi" id="bindWifi" class="form-check-input" checked>
                                        <label class="form-check-label" for="bindWifi">Bind WiFi to this WAN</label>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary" id="btnConfigWan" <?= (empty($currentOnu['tr069_ip']) || empty($attachedVlans)) ? 'disabled' : '' ?>>
                                    <i class="bi bi-check-circle me-1"></i> Apply Configuration
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Attached VLANs Modal -->
            <div class="modal fade" id="attachedVlansModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-info text-white">
                            <h5 class="modal-title"><i class="bi bi-tags me-2"></i>Manage Attached VLANs</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" id="attachedVlansOnuId" value="<?= $currentOnu['id'] ?>">
                            
                            <div class="alert alert-info small">
                                <i class="bi bi-info-circle me-1"></i>
                                Attached VLANs create service-port entries on the OLT for this ONU. Only attached VLANs can be used as service VLANs for WAN configuration.
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Current Attached VLANs</label>
                                <div id="currentVlansList" class="d-flex flex-wrap gap-2 mb-2">
                                    <?php if (!empty($attachedVlans)): 
                                        foreach ($attachedVlans as $vid): ?>
                                    <span class="badge bg-primary d-flex align-items-center" data-vlan="<?= $vid ?>">
                                        <?= $vid ?>
                                        <button type="button" class="btn-close btn-close-white ms-2" style="font-size: 0.6rem;" onclick="removeAttachedVlan(<?= $vid ?>)" title="Remove"></button>
                                    </span>
                                    <?php endforeach; 
                                    else: ?>
                                    <span class="text-muted" id="noVlansMsg">No VLANs attached</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <div class="mb-3">
                                <label class="form-label">Add VLAN</label>
                                <div class="input-group">
                                    <select id="addVlanSelect" class="form-select">
                                        <option value="">-- Select VLAN --</option>
                                        <?php
                                        $availableVlans = $db->prepare("SELECT vlan_id, description FROM huawei_vlans WHERE olt_id = ? AND is_tr069 = FALSE AND is_active = TRUE ORDER BY vlan_id");
                                        $availableVlans->execute([$currentOnu['olt_id']]);
                                        $oltVlans = $availableVlans->fetchAll(PDO::FETCH_ASSOC);
                                        foreach ($oltVlans as $vlan):
                                            if (!in_array((int)$vlan['vlan_id'], $attachedVlans)):
                                        ?>
                                        <option value="<?= $vlan['vlan_id'] ?>"><?= $vlan['vlan_id'] ?> - <?= htmlspecialchars($vlan['description'] ?: 'Service VLAN') ?></option>
                                        <?php endif; endforeach; ?>
                                    </select>
                                    <button type="button" class="btn btn-success" onclick="addAttachedVlan()">
                                        <i class="bi bi-plus-lg"></i> Add
                                    </button>
                                </div>
                                <div class="form-text">Or enter a custom VLAN ID:</div>
                                <div class="input-group mt-2">
                                    <input type="number" id="customVlanInput" class="form-control" min="1" max="4094" placeholder="VLAN ID (1-4094)">
                                    <button type="button" class="btn btn-outline-success" onclick="addCustomVlan()">
                                        <i class="bi bi-plus-lg"></i> Add Custom
                                    </button>
                                </div>
                            </div>
                            
                            <div id="vlanOperationLog" class="mt-3" style="display: none;">
                                <label class="form-label">Operation Log</label>
                                <pre class="bg-dark text-light p-2 small" style="max-height: 150px; overflow-y: auto;" id="vlanLogContent"></pre>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
            
            <script>
            let currentAttachedVlans = <?= json_encode($attachedVlans) ?>;
            
            async function addAttachedVlan() {
                const select = document.getElementById('addVlanSelect');
                const vlanId = parseInt(select.value);
                if (!vlanId) {
                    alert('Please select a VLAN');
                    return;
                }
                await attachVlanToOnu(vlanId);
            }
            
            async function addCustomVlan() {
                const input = document.getElementById('customVlanInput');
                const vlanId = parseInt(input.value);
                if (!vlanId || vlanId < 1 || vlanId > 4094) {
                    alert('Please enter a valid VLAN ID (1-4094)');
                    return;
                }
                if (currentAttachedVlans.includes(vlanId)) {
                    alert('VLAN ' + vlanId + ' is already attached');
                    return;
                }
                await attachVlanToOnu(vlanId);
                input.value = '';
            }
            
            async function attachVlanToOnu(vlanId) {
                const onuId = document.getElementById('attachedVlansOnuId').value;
                const logDiv = document.getElementById('vlanOperationLog');
                const logContent = document.getElementById('vlanLogContent');
                
                logDiv.style.display = 'block';
                logContent.textContent = 'Attaching VLAN ' + vlanId + '...\n';
                
                try {
                    const formData = new FormData();
                    formData.append('onu_id', onuId);
                    formData.append('vlan_id', vlanId);
                    formData.append('action', 'attach');
                    
                    const resp = await fetch('?page=api&action=manage_onu_vlan', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await resp.json();
                    
                    if (data.success) {
                        logContent.textContent += 'SUCCESS: ' + data.message + '\n';
                        if (data.olt_output) {
                            logContent.textContent += '\nOLT Commands:\n' + data.olt_output;
                        }
                        currentAttachedVlans.push(vlanId);
                        updateVlanDisplay();
                    } else {
                        logContent.textContent += 'ERROR: ' + (data.error || 'Unknown error') + '\n';
                        alert('Failed to attach VLAN: ' + (data.error || 'Unknown error'));
                    }
                } catch (err) {
                    logContent.textContent += 'NETWORK ERROR: ' + err.message + '\n';
                    alert('Network error: ' + err.message);
                }
            }
            
            async function removeAttachedVlan(vlanId) {
                if (!confirm('Remove VLAN ' + vlanId + ' from this ONU? This will delete the service-port on the OLT.')) {
                    return;
                }
                
                const onuId = document.getElementById('attachedVlansOnuId').value;
                const logDiv = document.getElementById('vlanOperationLog');
                const logContent = document.getElementById('vlanLogContent');
                
                logDiv.style.display = 'block';
                logContent.textContent = 'Removing VLAN ' + vlanId + '...\n';
                
                try {
                    const formData = new FormData();
                    formData.append('onu_id', onuId);
                    formData.append('vlan_id', vlanId);
                    formData.append('action', 'detach');
                    
                    const resp = await fetch('?page=api&action=manage_onu_vlan', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await resp.json();
                    
                    if (data.success) {
                        logContent.textContent += 'SUCCESS: ' + data.message + '\n';
                        if (data.olt_output) {
                            logContent.textContent += '\nOLT Commands:\n' + data.olt_output;
                        }
                        currentAttachedVlans = currentAttachedVlans.filter(v => v !== vlanId);
                        updateVlanDisplay();
                    } else {
                        logContent.textContent += 'ERROR: ' + (data.error || 'Unknown error') + '\n';
                        alert('Failed to remove VLAN: ' + (data.error || 'Unknown error'));
                    }
                } catch (err) {
                    logContent.textContent += 'NETWORK ERROR: ' + err.message + '\n';
                    alert('Network error: ' + err.message);
                }
            }
            
            function updateVlanDisplay() {
                const container = document.getElementById('currentVlansList');
                if (currentAttachedVlans.length === 0) {
                    container.innerHTML = '<span class="text-muted" id="noVlansMsg">No VLANs attached</span>';
                } else {
                    container.innerHTML = currentAttachedVlans.map(v => 
                        `<span class="badge bg-primary d-flex align-items-center" data-vlan="${v}">
                            ${v}
                            <button type="button" class="btn-close btn-close-white ms-2" style="font-size: 0.6rem;" onclick="removeAttachedVlan(${v})" title="Remove"></button>
                        </span>`
                    ).join('');
                }
                
                // Update the service VLAN dropdown in WAN config modal
                const serviceVlanSelect = document.getElementById('serviceVlanSelect');
                if (serviceVlanSelect) {
                    if (currentAttachedVlans.length === 0) {
                        serviceVlanSelect.innerHTML = '<option value="">-- No VLANs attached - Add VLANs first --</option>';
                        document.getElementById('btnConfigWan').disabled = true;
                    } else {
                        serviceVlanSelect.innerHTML = currentAttachedVlans.map(v => 
                            `<option value="${v}">${v}</option>`
                        ).join('');
                        // Re-enable button if TR-069 is configured
                        const hasTr069 = <?= !empty($currentOnu['tr069_ip']) ? 'true' : 'false' ?>;
                        document.getElementById('btnConfigWan').disabled = !hasTr069;
                    }
                }
                
                // Update add VLAN dropdown to remove already attached
                const addSelect = document.getElementById('addVlanSelect');
                if (addSelect) {
                    Array.from(addSelect.options).forEach(opt => {
                        if (opt.value && currentAttachedVlans.includes(parseInt(opt.value))) {
                            opt.style.display = 'none';
                        } else {
                            opt.style.display = '';
                        }
                    });
                }
            }
            </script>
            
            <script>
            function toggleWanFields() {
                const mode = document.getElementById('wanModeSelect').value;
                document.getElementById('pppoeFields').style.display = mode === 'pppoe' ? 'block' : 'none';
                document.getElementById('staticFields').style.display = mode === 'static' ? 'block' : 'none';
            }
            toggleWanFields();
            
            async function submitWanConfig(e) {
                e.preventDefault();
                const form = e.target;
                const btn = document.getElementById('btnConfigWan');
                const formData = new FormData(form);
                
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Configuring...';
                
                try {
                    const resp = await fetch('?page=api&action=configure_wan_tr069', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await resp.json();
                    
                    if (data.success) {
                        alert('WAN configuration applied successfully!');
                        bootstrap.Modal.getInstance(document.getElementById('wanConfigModal')).hide();
                        location.reload();
                    } else {
                        alert('Error: ' + (data.error || 'Unknown error'));
                    }
                } catch (err) {
                    alert('Network error: ' + err.message);
                } finally {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Apply Configuration';
                }
                
                return false;
            }
            </script>
            
            <?php elseif ($view === 'onu_types'): ?>
            <?php
            $onuTypes = $db->query("SELECT * FROM huawei_onu_types ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
            $onuCountsByType = [];
            $countStmt = $db->query("SELECT onu_type_id, COUNT(*) as cnt FROM huawei_onus WHERE onu_type_id IS NOT NULL GROUP BY onu_type_id");
            foreach ($countStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $onuCountsByType[$row['onu_type_id']] = $row['cnt'];
            }
            ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0"><i class="bi bi-cpu me-2"></i>ONU Types</h4>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#onuTypeModal" onclick="resetOnuTypeForm()">
                    <i class="bi bi-plus-circle me-1"></i> Add ONU Type
                </button>
            </div>
            
            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <?php if (empty($onuTypes)): ?>
                    <div class="p-4 text-center text-muted">
                        <i class="bi bi-cpu fs-1 mb-2 d-block"></i>
                        No ONU types configured
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Name / Model</th>
                                    <th>ETH Ports</th>
                                    <th>POTS</th>
                                    <th>WiFi</th>
                                    <th>Default Mode</th>
                                    <th>ONUs Using</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($onuTypes as $type): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($type['name']) ?></strong>
                                        <?php if ($type['model'] && $type['model'] !== $type['name']): ?>
                                        <br><small class="text-muted"><?= htmlspecialchars($type['model']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge bg-primary"><?= (int)$type['eth_ports'] ?></span></td>
                                    <td><span class="badge bg-secondary"><?= (int)$type['pots_ports'] ?></span></td>
                                    <td>
                                        <?php if ($type['wifi_capable']): ?>
                                        <i class="bi bi-wifi text-success"></i> Yes
                                        <?php else: ?>
                                        <i class="bi bi-wifi-off text-muted"></i> No
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge bg-<?= $type['default_mode'] === 'router' ? 'info' : 'secondary' ?>"><?= ucfirst($type['default_mode'] ?? 'bridge') ?></span></td>
                                    <td>
                                        <?php $count = $onuCountsByType[$type['id']] ?? 0; ?>
                                        <?php if ($count > 0): ?>
                                        <span class="badge bg-success"><?= $count ?> ONUs</span>
                                        <?php else: ?>
                                        <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-secondary" onclick="editOnuType(<?= htmlspecialchars(json_encode($type)) ?>)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-outline-danger" onclick="deleteOnuType(<?= $type['id'] ?>)" <?= ($onuCountsByType[$type['id']] ?? 0) > 0 ? 'disabled title="Cannot delete - ONUs are using this type"' : '' ?>>
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
            
            <div class="modal fade" id="onuTypeModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST">
                            <input type="hidden" name="action" value="save_onu_type">
                            <input type="hidden" name="id" id="onuTypeId">
                            <div class="modal-header">
                                <h5 class="modal-title" id="onuTypeModalTitle">Add ONU Type</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">Name (Equipment ID)</label>
                                    <input type="text" name="name" id="onuTypeName" class="form-control" required placeholder="e.g. HG8546M">
                                    <small class="text-muted">This should match the Equipment ID reported by the ONU</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Model</label>
                                    <input type="text" name="model" id="onuTypeModel" class="form-control" placeholder="e.g. HG8546M">
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">ETH Ports</label>
                                        <input type="number" name="eth_ports" id="onuTypeEthPorts" class="form-control" value="4" min="0" max="8">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">POTS Ports</label>
                                        <input type="number" name="pots_ports" id="onuTypePotsPorts" class="form-control" value="0" min="0" max="4">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <div class="form-check">
                                            <input type="checkbox" name="wifi_capable" id="onuTypeWifi" class="form-check-input" value="1">
                                            <label class="form-check-label" for="onuTypeWifi">WiFi Capable</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Default Mode</label>
                                        <select name="default_mode" id="onuTypeMode" class="form-select">
                                            <option value="bridge">Bridge</option>
                                            <option value="router">Router</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Save ONU Type</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <script>
            function resetOnuTypeForm() {
                document.getElementById('onuTypeId').value = '';
                document.getElementById('onuTypeName').value = '';
                document.getElementById('onuTypeModel').value = '';
                document.getElementById('onuTypeEthPorts').value = '4';
                document.getElementById('onuTypePotsPorts').value = '0';
                document.getElementById('onuTypeWifi').checked = false;
                document.getElementById('onuTypeMode').value = 'bridge';
                document.getElementById('onuTypeModalTitle').textContent = 'Add ONU Type';
            }
            
            function editOnuType(type) {
                document.getElementById('onuTypeId').value = type.id;
                document.getElementById('onuTypeName').value = type.name || '';
                document.getElementById('onuTypeModel').value = type.model || '';
                document.getElementById('onuTypeEthPorts').value = type.eth_ports || 4;
                document.getElementById('onuTypePotsPorts').value = type.pots_ports || 0;
                document.getElementById('onuTypeWifi').checked = type.wifi_capable;
                document.getElementById('onuTypeMode').value = type.default_mode || 'bridge';
                document.getElementById('onuTypeModalTitle').textContent = 'Edit ONU Type';
                new bootstrap.Modal(document.getElementById('onuTypeModal')).show();
            }
            
            function deleteOnuType(id) {
                if (confirm('Are you sure you want to delete this ONU type?')) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = '<input type="hidden" name="action" value="delete_onu_type"><input type="hidden" name="id" value="' + id + '">';
                    document.body.appendChild(form);
                    form.submit();
                }
            }
            </script>
            
            <?php elseif ($view === 'locations'): ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0"><i class="bi bi-geo-alt me-2"></i>Location Management</h4>
            </div>
            
            <ul class="nav nav-tabs mb-4" id="locationTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="zones-tab" data-bs-toggle="tab" data-bs-target="#zonesTab" type="button">
                        <i class="bi bi-map me-1"></i> Zones <span class="badge bg-secondary"><?= count($zones) ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="subzones-tab" data-bs-toggle="tab" data-bs-target="#subzonesTab" type="button">
                        <i class="bi bi-diagram-3 me-1"></i> Subzones <span class="badge bg-secondary"><?= count($subzones) ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="apartments-tab" data-bs-toggle="tab" data-bs-target="#apartmentsTab" type="button">
                        <i class="bi bi-building me-1"></i> Apartments <span class="badge bg-secondary"><?= count($apartments) ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="odbs-tab" data-bs-toggle="tab" data-bs-target="#odbsTab" type="button">
                        <i class="bi bi-box me-1"></i> ODB Units <span class="badge bg-secondary"><?= count($odbs) ?></span>
                    </button>
                </li>
            </ul>
            
            <div class="tab-content" id="locationTabContent">
                <!-- Zones Tab -->
                <div class="tab-pane fade show active" id="zonesTab" role="tabpanel">
                    <div class="card shadow-sm">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-map me-2"></i>Zones</h5>
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#zoneModal" onclick="resetZoneForm()">
                                <i class="bi bi-plus-circle me-1"></i> Add Zone
                            </button>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($zones)): ?>
                            <div class="p-4 text-center text-muted">
                                <i class="bi bi-map fs-1 mb-2 d-block"></i>
                                No zones configured. Add zones to organize ONU locations.
                            </div>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Zone Name</th>
                                            <th>Description</th>
                                            <th>Subzones</th>
                                            <th>Apartments</th>
                                            <th>ODBs</th>
                                            <th>ONUs</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($zones as $zone): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($zone['name']) ?></strong></td>
                                            <td class="text-muted"><?= htmlspecialchars($zone['description'] ?? '-') ?></td>
                                            <td><span class="badge bg-info"><?= $zone['subzone_count'] ?></span></td>
                                            <td><span class="badge bg-secondary"><?= $zone['apartment_count'] ?></span></td>
                                            <td><span class="badge bg-warning text-dark"><?= $zone['odb_count'] ?></span></td>
                                            <td><span class="badge bg-primary"><?= $zone['onu_count'] ?></span></td>
                                            <td>
                                                <?php if ($zone['is_active']): ?>
                                                <span class="badge bg-success">Active</span>
                                                <?php else: ?>
                                                <span class="badge bg-secondary">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-secondary" onclick="editZone(<?= htmlspecialchars(json_encode($zone)) ?>)">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button class="btn btn-outline-danger" onclick="deleteZone(<?= $zone['id'] ?>, '<?= htmlspecialchars($zone['name']) ?>')">
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
                </div>
                
                <!-- Subzones Tab -->
                <div class="tab-pane fade" id="subzonesTab" role="tabpanel">
                    <div class="card shadow-sm">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-diagram-3 me-2"></i>Subzones</h5>
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#subzoneModal" onclick="resetSubzoneForm()">
                                <i class="bi bi-plus-circle me-1"></i> Add Subzone
                            </button>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($subzones)): ?>
                            <div class="p-4 text-center text-muted">
                                <i class="bi bi-diagram-3 fs-1 mb-2 d-block"></i>
                                No subzones. Subzones help divide zones into smaller areas.
                            </div>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Subzone Name</th>
                                            <th>Zone</th>
                                            <th>Description</th>
                                            <th>Apartments</th>
                                            <th>ODBs</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($subzones as $sz): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($sz['name']) ?></strong></td>
                                            <td><span class="badge bg-primary"><?= htmlspecialchars($sz['zone_name']) ?></span></td>
                                            <td class="text-muted"><?= htmlspecialchars($sz['description'] ?? '-') ?></td>
                                            <td><span class="badge bg-secondary"><?= $sz['apartment_count'] ?></span></td>
                                            <td><span class="badge bg-warning text-dark"><?= $sz['odb_count'] ?></span></td>
                                            <td>
                                                <?php if ($sz['is_active']): ?>
                                                <span class="badge bg-success">Active</span>
                                                <?php else: ?>
                                                <span class="badge bg-secondary">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-secondary" onclick="editSubzone(<?= htmlspecialchars(json_encode($sz)) ?>)">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button class="btn btn-outline-danger" onclick="deleteSubzone(<?= $sz['id'] ?>, '<?= htmlspecialchars($sz['name']) ?>')">
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
                </div>
                
                <!-- Apartments Tab -->
                <div class="tab-pane fade" id="apartmentsTab" role="tabpanel">
                    <div class="card shadow-sm">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-building me-2"></i>Apartments / Buildings</h5>
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#apartmentModal" onclick="resetApartmentForm()">
                                <i class="bi bi-plus-circle me-1"></i> Add Apartment
                            </button>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($apartments)): ?>
                            <div class="p-4 text-center text-muted">
                                <i class="bi bi-building fs-1 mb-2 d-block"></i>
                                No apartments/buildings. Add locations where ONUs are installed.
                            </div>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Name</th>
                                            <th>Zone</th>
                                            <th>Subzone</th>
                                            <th>Address</th>
                                            <th>Floors</th>
                                            <th>ODBs</th>
                                            <th>ONUs</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($apartments as $apt): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($apt['name']) ?></strong></td>
                                            <td><span class="badge bg-primary"><?= htmlspecialchars($apt['zone_name']) ?></span></td>
                                            <td><?= $apt['subzone_name'] ? htmlspecialchars($apt['subzone_name']) : '<span class="text-muted">-</span>' ?></td>
                                            <td class="text-muted small"><?= htmlspecialchars($apt['address'] ?? '-') ?></td>
                                            <td><?= $apt['floors'] ?: '-' ?></td>
                                            <td><span class="badge bg-warning text-dark"><?= $apt['odb_count'] ?></span></td>
                                            <td><span class="badge bg-success"><?= $apt['onu_count'] ?></span></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-secondary" onclick="editApartment(<?= htmlspecialchars(json_encode($apt)) ?>)">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button class="btn btn-outline-danger" onclick="deleteApartment(<?= $apt['id'] ?>, '<?= htmlspecialchars($apt['name']) ?>')">
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
                </div>
                
                <!-- ODBs Tab -->
                <div class="tab-pane fade" id="odbsTab" role="tabpanel">
                    <div class="card shadow-sm">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-box me-2"></i>Optical Distribution Boxes (ODB)</h5>
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#odbModal" onclick="resetOdbForm()">
                                <i class="bi bi-plus-circle me-1"></i> Add ODB
                            </button>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($odbs)): ?>
                            <div class="p-4 text-center text-muted">
                                <i class="bi bi-box fs-1 mb-2 d-block"></i>
                                No ODB units. ODBs are the fiber distribution boxes where ONUs connect.
                            </div>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>ODB Code</th>
                                            <th>Zone</th>
                                            <th>Apartment</th>
                                            <th>Capacity</th>
                                            <th>Used</th>
                                            <th>Location</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($odbs as $odb): ?>
                                        <tr>
                                            <td><strong><code><?= htmlspecialchars($odb['code']) ?></code></strong></td>
                                            <td><span class="badge bg-primary"><?= htmlspecialchars($odb['zone_name']) ?></span></td>
                                            <td><?= $odb['apartment_name'] ? htmlspecialchars($odb['apartment_name']) : '<span class="text-muted">-</span>' ?></td>
                                            <td><?= $odb['capacity'] ?> ports</td>
                                            <td>
                                                <?php 
                                                $usage = $odb['capacity'] > 0 ? ($odb['onu_count'] / $odb['capacity']) * 100 : 0;
                                                $usageClass = $usage >= 90 ? 'danger' : ($usage >= 70 ? 'warning' : 'success');
                                                ?>
                                                <span class="badge bg-<?= $usageClass ?>"><?= $odb['onu_count'] ?>/<?= $odb['capacity'] ?></span>
                                            </td>
                                            <td class="text-muted small"><?= htmlspecialchars($odb['location_description'] ?? '-') ?></td>
                                            <td>
                                                <?php if ($odb['is_active']): ?>
                                                <span class="badge bg-success">Active</span>
                                                <?php else: ?>
                                                <span class="badge bg-secondary">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-secondary" onclick="editOdb(<?= htmlspecialchars(json_encode($odb)) ?>)">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button class="btn btn-outline-danger" onclick="deleteOdb(<?= $odb['id'] ?>, '<?= htmlspecialchars($odb['code']) ?>')">
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
                </div>
            </div>
            
            <?php elseif ($view === 'logs'): ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0"><i class="bi bi-journal-text me-2"></i>Provisioning Logs</h4>
                <form class="d-flex gap-2" method="get">
                    <input type="hidden" name="page" value="huawei-olt">
                    <input type="hidden" name="view" value="logs">
                    <select name="olt_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All OLTs</option>
                        <?php foreach ($olts as $olt): ?>
                        <option value="<?= $olt['id'] ?>" <?= $oltId == $olt['id'] ? 'selected' : '' ?>><?= htmlspecialchars($olt['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="log_action" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Actions</option>
                        <option value="authorize" <?= ($_GET['log_action'] ?? '') === 'authorize' ? 'selected' : '' ?>>Authorize</option>
                        <option value="reboot" <?= ($_GET['log_action'] ?? '') === 'reboot' ? 'selected' : '' ?>>Reboot</option>
                        <option value="delete" <?= ($_GET['log_action'] ?? '') === 'delete' ? 'selected' : '' ?>>Delete</option>
                        <option value="command" <?= ($_GET['log_action'] ?? '') === 'command' ? 'selected' : '' ?>>Command</option>
                    </select>
                </form>
            </div>
            
            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <?php if (empty($logs)): ?>
                    <div class="p-4 text-center text-muted">
                        <i class="bi bi-journal-text fs-1 mb-2 d-block"></i>
                        No logs found
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 small">
                            <thead class="table-light">
                                <tr>
                                    <th>Time</th>
                                    <th>OLT</th>
                                    <th>ONU SN</th>
                                    <th>Action</th>
                                    <th>Status</th>
                                    <th>Message</th>
                                    <th>User</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td class="text-nowrap"><?= date('M j, H:i:s', strtotime($log['created_at'])) ?></td>
                                    <td><?= htmlspecialchars($log['olt_name'] ?? '-') ?></td>
                                    <td><code><?= htmlspecialchars($log['onu_sn'] ?? '-') ?></code></td>
                                    <td><span class="badge bg-secondary"><?= ucfirst($log['action']) ?></span></td>
                                    <td>
                                        <?php
                                        $statusColors = ['success' => 'success', 'failed' => 'danger', 'pending' => 'warning'];
                                        ?>
                                        <span class="badge bg-<?= $statusColors[$log['status']] ?? 'secondary' ?>"><?= ucfirst($log['status']) ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($log['message'] ?: '-') ?></td>
                                    <td><?= htmlspecialchars($log['user_name'] ?? '-') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php elseif ($view === 'alerts'): ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0"><i class="bi bi-bell me-2"></i>Alerts</h4>
                <form method="post">
                    <input type="hidden" name="action" value="mark_alerts_read">
                    <button type="submit" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-check-all me-1"></i> Mark All Read
                    </button>
                </form>
            </div>
            
            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <?php if (empty($alerts)): ?>
                    <div class="p-4 text-center text-muted">
                        <i class="bi bi-check-circle fs-1 text-success mb-2 d-block"></i>
                        No alerts
                    </div>
                    <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($alerts as $alert): ?>
                        <div class="list-group-item <?= !$alert['is_read'] ? 'bg-light' : '' ?>">
                            <div class="d-flex align-items-start">
                                <?php
                                $severityIcons = [
                                    'info' => 'info-circle text-info',
                                    'warning' => 'exclamation-triangle text-warning',
                                    'critical' => 'exclamation-circle text-danger'
                                ];
                                ?>
                                <i class="bi bi-<?= $severityIcons[$alert['severity']] ?? 'info-circle text-info' ?> fs-5 me-3 mt-1"></i>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between">
                                        <strong><?= htmlspecialchars($alert['title']) ?></strong>
                                        <small class="text-muted"><?= date('M j, H:i', strtotime($alert['created_at'])) ?></small>
                                    </div>
                                    <p class="mb-1 text-muted"><?= htmlspecialchars($alert['message']) ?></p>
                                    <small class="text-muted">
                                        <?php if ($alert['olt_name']): ?>OLT: <?= htmlspecialchars($alert['olt_name']) ?><?php endif; ?>
                                        <?php if ($alert['onu_sn']): ?> | ONU: <?= htmlspecialchars($alert['onu_sn']) ?><?php endif; ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php elseif ($view === 'terminal'): ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0"><i class="bi bi-terminal me-2"></i>CLI Terminal</h4>
            </div>
            
            <div class="card shadow-sm">
                <div class="card-body">
                    <form method="post" id="terminalForm">
                        <input type="hidden" name="action" value="execute_command">
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Select OLT</label>
                                <select name="olt_id" class="form-select" required>
                                    <option value="">-- Select OLT --</option>
                                    <?php foreach ($olts as $olt): ?>
                                    <option value="<?= $olt['id'] ?>"><?= htmlspecialchars($olt['name']) ?> (<?= $olt['ip_address'] ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Command</label>
                                <div class="input-group">
                                    <input type="text" name="command" class="form-control font-monospace" placeholder="display ont autofind all" required>
                                    <button type="submit" class="btn btn-primary"><i class="bi bi-play me-1"></i> Execute</button>
                                </div>
                            </div>
                        </div>
                    </form>
                    
                    <div class="mt-3">
                        <label class="form-label">Quick Commands</label>
                        <div class="d-flex flex-wrap gap-2">
                            <button class="btn btn-sm btn-outline-secondary" onclick="setCommand('display ont autofind all')">Unsynced ONTs</button>
                            <button class="btn btn-sm btn-outline-secondary" onclick="setCommand('display board 0')">Board Info</button>
                            <button class="btn btn-sm btn-outline-secondary" onclick="setCommand('display sysman temperature')">Temperature</button>
                            <button class="btn btn-sm btn-outline-secondary" onclick="setCommand('display interface gpon 0/1/0')">PON Port 0/1/0</button>
                            <button class="btn btn-sm btn-outline-secondary" onclick="setCommand('display ont info 0/1/0 all')">ONTs on 0/1/0</button>
                        </div>
                    </div>
                    
                    <?php if (isset($result) && isset($result['output'])): ?>
                    <div class="mt-4">
                        <label class="form-label">Output</label>
                        <pre class="bg-dark text-light p-3 rounded" style="max-height: 400px; overflow: auto;"><?= htmlspecialchars($result['output']) ?></pre>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php elseif ($view === 'topology'): ?>
            <?php
            $topologyOltId = isset($_GET['olt_id']) ? (int)$_GET['olt_id'] : null;
            $topologyData = $huaweiOLT->getTopologyData($topologyOltId);
            ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="page-title mb-1"><i class="bi bi-diagram-3"></i> PON Network Map</h4>
                    <small class="text-muted">Interactive topology visualization</small>
                </div>
                <div class="d-flex gap-2">
                    <select id="topologyOltFilter" class="form-select form-select-sm" style="width: 200px;" onchange="filterTopology(this.value)">
                        <option value="">All OLTs</option>
                        <?php foreach ($olts as $olt): ?>
                        <option value="<?= $olt['id'] ?>" <?= $topologyOltId == $olt['id'] ? 'selected' : '' ?>><?= htmlspecialchars($olt['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-outline-secondary btn-sm" onclick="resetTopologyView()">
                        <i class="bi bi-arrows-angle-contract me-1"></i> Fit View
                    </button>
                    <button class="btn btn-outline-primary btn-sm" onclick="location.reload()">
                        <i class="bi bi-arrow-clockwise me-1"></i> Refresh
                    </button>
                </div>
            </div>
            
            <div class="row g-3 mb-3">
                <div class="col-auto">
                    <div class="d-flex align-items-center gap-2 bg-light rounded px-3 py-2">
                        <span class="topology-legend-dot" style="background: var(--oms-primary);"></span>
                        <small>OLT</small>
                    </div>
                </div>
                <div class="col-auto">
                    <div class="d-flex align-items-center gap-2 bg-light rounded px-3 py-2">
                        <span class="topology-legend-dot" style="background: #8b5cf6;"></span>
                        <small>PON Port</small>
                    </div>
                </div>
                <div class="col-auto">
                    <div class="d-flex align-items-center gap-2 bg-light rounded px-3 py-2">
                        <span class="topology-legend-dot" style="background: var(--oms-success);"></span>
                        <small>Online ONU</small>
                    </div>
                </div>
                <div class="col-auto">
                    <div class="d-flex align-items-center gap-2 bg-light rounded px-3 py-2">
                        <span class="topology-legend-dot" style="background: var(--oms-danger);"></span>
                        <small>LOS ONU</small>
                    </div>
                </div>
                <div class="col-auto">
                    <div class="d-flex align-items-center gap-2 bg-light rounded px-3 py-2">
                        <span class="topology-legend-dot" style="background: #6b7280;"></span>
                        <small>Offline ONU</small>
                    </div>
                </div>
            </div>
            
            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <div id="topologyContainer" style="height: 600px; width: 100%; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); border-radius: 0.375rem;"></div>
                </div>
            </div>
            
            <div id="nodeInfoPanel" class="card shadow-sm mt-3" style="display: none;">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Node Details</h6>
                    <button class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('nodeInfoPanel').style.display='none'">
                        <i class="bi bi-x"></i>
                    </button>
                </div>
                <div class="card-body" id="nodeInfoContent"></div>
            </div>
            
            <script src="https://unpkg.com/vis-network/standalone/umd/vis-network.min.js"></script>
            <style>
                .topology-legend-dot {
                    width: 12px;
                    height: 12px;
                    border-radius: 50%;
                    display: inline-block;
                }
                #topologyContainer .vis-tooltip {
                    background: #1e293b !important;
                    color: #f1f5f9 !important;
                    border: 1px solid #475569 !important;
                    border-radius: 8px !important;
                    padding: 10px 14px !important;
                    font-family: inherit !important;
                    white-space: pre-line !important;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.4) !important;
                }
            </style>
            <script>
            const topologyData = <?= json_encode($topologyData) ?>;
            let network = null;
            
            function initTopology() {
                const container = document.getElementById('topologyContainer');
                
                const nodes = new vis.DataSet(topologyData.nodes.map(node => {
                    let color, shape, size, font;
                    
                    if (node.type === 'olt') {
                        color = { background: '#3b82f6', border: '#1d4ed8', highlight: { background: '#60a5fa', border: '#2563eb' } };
                        shape = 'box';
                        size = 30;
                        font = { color: '#ffffff', size: 14, bold: true };
                    } else if (node.type === 'port') {
                        color = { background: '#8b5cf6', border: '#6d28d9', highlight: { background: '#a78bfa', border: '#7c3aed' } };
                        shape = 'diamond';
                        size = 20;
                        font = { color: '#ffffff', size: 11 };
                    } else {
                        if (node.status === 'online') {
                            color = { background: '#10b981', border: '#059669', highlight: { background: '#34d399', border: '#10b981' } };
                        } else if (node.status === 'los') {
                            color = { background: '#ef4444', border: '#dc2626', highlight: { background: '#f87171', border: '#ef4444' } };
                        } else {
                            color = { background: '#6b7280', border: '#4b5563', highlight: { background: '#9ca3af', border: '#6b7280' } };
                        }
                        shape = 'dot';
                        size = 12;
                        font = { color: '#e2e8f0', size: 10 };
                    }
                    
                    return {
                        id: node.id,
                        label: node.label,
                        title: node.title,
                        color: color,
                        shape: shape,
                        size: size,
                        font: font,
                        nodeData: node
                    };
                }));
                
                const edges = new vis.DataSet(topologyData.edges.map(edge => ({
                    from: edge.from,
                    to: edge.to,
                    color: { color: '#475569', highlight: '#60a5fa', opacity: 0.6 },
                    width: edge.from.startsWith('olt_') ? 3 : 1,
                    smooth: { type: 'cubicBezier', roundness: 0.5 }
                })));
                
                const options = {
                    layout: {
                        hierarchical: {
                            enabled: true,
                            direction: 'UD',
                            sortMethod: 'directed',
                            levelSeparation: 120,
                            nodeSpacing: 80,
                            treeSpacing: 100
                        }
                    },
                    physics: {
                        enabled: false
                    },
                    interaction: {
                        hover: true,
                        tooltipDelay: 100,
                        zoomView: true,
                        dragView: true
                    },
                    nodes: {
                        borderWidth: 2,
                        shadow: { enabled: true, size: 8, x: 2, y: 2 }
                    },
                    edges: {
                        arrows: { to: { enabled: false } }
                    }
                };
                
                network = new vis.Network(container, { nodes, edges }, options);
                
                network.on('click', function(params) {
                    if (params.nodes.length > 0) {
                        const nodeId = params.nodes[0];
                        const node = nodes.get(nodeId);
                        showNodeInfo(node.nodeData);
                    }
                });
                
                network.on('doubleClick', function(params) {
                    if (params.nodes.length > 0) {
                        const nodeId = params.nodes[0];
                        const node = nodes.get(nodeId);
                        if (node.nodeData.type === 'onu' && node.nodeData.db_id) {
                            window.location.href = '?page=huawei-olt&view=onus&onu_id=' + node.nodeData.db_id;
                        }
                    }
                });
            }
            
            function showNodeInfo(node) {
                const panel = document.getElementById('nodeInfoPanel');
                const content = document.getElementById('nodeInfoContent');
                
                let html = '';
                if (node.type === 'olt') {
                    html = `
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Type:</strong> OLT Device</p>
                                <p><strong>Name:</strong> ${node.label}</p>
                                <p><strong>IP Address:</strong> ${node.ip || 'N/A'}</p>
                            </div>
                            <div class="col-md-6 text-end">
                                <a href="?page=huawei-olt&view=olts&edit_olt=${node.id.replace('olt_', '')}" class="btn btn-primary btn-sm">
                                    <i class="bi bi-pencil me-1"></i> Edit OLT
                                </a>
                            </div>
                        </div>`;
                } else if (node.type === 'port') {
                    html = `
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Type:</strong> PON Port</p>
                                <p><strong>Port:</strong> ${node.label}</p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Total ONUs:</strong> ${node.onu_count || 0}</p>
                                <p><span class="text-success">${node.online || 0} Online</span> / <span class="text-danger">${node.los || 0} LOS</span> / <span class="text-secondary">${node.offline || 0} Offline</span></p>
                            </div>
                        </div>`;
                } else {
                    const statusClass = node.status === 'online' ? 'success' : (node.status === 'los' ? 'danger' : 'secondary');
                    html = `
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Type:</strong> ONU</p>
                                <p><strong>Name:</strong> ${node.label}</p>
                                <p><strong>Serial:</strong> <code>${node.serial || 'N/A'}</code></p>
                                <p><strong>Status:</strong> <span class="badge bg-${statusClass}">${node.status}</span></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Rx Power:</strong> ${node.rx_power ? node.rx_power + ' dBm' : 'N/A'}</p>
                                <p><strong>Customer:</strong> ${node.customer || 'Not assigned'}</p>
                                ${node.db_id ? `<a href="?page=huawei-olt&view=onus&onu_id=${node.db_id}" class="btn btn-primary btn-sm"><i class="bi bi-eye me-1"></i> View Details</a>` : ''}
                            </div>
                        </div>`;
                }
                
                content.innerHTML = html;
                panel.style.display = 'block';
            }
            
            function filterTopology(oltId) {
                const url = new URL(window.location);
                if (oltId) {
                    url.searchParams.set('olt_id', oltId);
                } else {
                    url.searchParams.delete('olt_id');
                }
                window.location = url;
            }
            
            function resetTopologyView() {
                if (network) {
                    network.fit({ animation: { duration: 500, easingFunction: 'easeInOutQuad' } });
                }
            }
            
            document.addEventListener('DOMContentLoaded', initTopology);
            </script>
            
            <?php elseif ($view === 'tr069'): ?>
            <?php
            require_once __DIR__ . '/../src/GenieACS.php';
            $genieacs = new \App\GenieACS($db);
            $genieacsEnabled = false;
            $tr069Devices = [];
            try {
                $stmt = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'genieacs_enabled'");
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                $genieacsEnabled = ($row['setting_value'] ?? '0') === '1';
                
                if ($genieacsEnabled) {
                    $stmt = $db->query("SELECT t.*, o.name as onu_name, o.sn as onu_sn FROM tr069_devices t LEFT JOIN huawei_onus o ON t.onu_id = o.id ORDER BY t.last_inform DESC LIMIT 100");
                    $tr069Devices = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                }
            } catch (Exception $e) {}
            ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0"><i class="bi bi-gear-wide-connected me-2"></i>TR-069 / GenieACS</h4>
                <div>
                    <?php if ($genieacsEnabled): ?>
                    <form method="post" class="d-inline">
                        <input type="hidden" name="action" value="sync_tr069_devices">
                        <button type="submit" class="btn btn-outline-primary"><i class="bi bi-arrow-repeat me-1"></i> Sync Devices</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (!$genieacsEnabled): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i>
                <strong>TR-069 / GenieACS is not configured.</strong><br>
                Go to <a href="?page=huawei-olt&view=settings">Settings</a> to configure your GenieACS server connection.
            </div>
            
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h5>What is TR-069?</h5>
                    <p>TR-069 (CWMP) is a remote management protocol that allows you to configure ONU devices over the network. With GenieACS integration, you can:</p>
                    <ul>
                        <li><i class="bi bi-wifi text-primary me-2"></i>Configure WiFi settings (SSID, password, channel)</li>
                        <li><i class="bi bi-telephone text-success me-2"></i>Set up VoIP parameters</li>
                        <li><i class="bi bi-arrow-up-circle text-info me-2"></i>Perform firmware upgrades</li>
                        <li><i class="bi bi-arrow-clockwise text-warning me-2"></i>Reboot devices remotely</li>
                        <li><i class="bi bi-speedometer text-secondary me-2"></i>Monitor device performance</li>
                    </ul>
                    
                    <h6 class="mt-4">Setup Requirements:</h6>
                    <ol>
                        <li>Deploy GenieACS (Docker recommended): <code>docker run -d -p 7547:7547 -p 7557:7557 -p 3000:3000 genieacs/genieacs</code></li>
                        <li>Configure your OLT to push TR-069 ACS URL to ONUs</li>
                        <li>Enter GenieACS NBI URL in Settings (usually <code>http://your-server:7557</code>)</li>
                    </ol>
                </div>
            </div>
            <?php else: ?>
            
            <?php if (empty($tr069Devices)): ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle me-2"></i>
                No TR-069 devices found. Click "Sync Devices" to fetch devices from GenieACS, or ensure your ONUs are connecting to the ACS.
            </div>
            <?php else: ?>
            
            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Serial Number</th>
                                    <th>Linked ONU</th>
                                    <th>Manufacturer</th>
                                    <th>Model</th>
                                    <th>Last Inform</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tr069Devices as $device): ?>
                                <?php
                                $lastInform = $device['last_inform'] ? strtotime($device['last_inform']) : 0;
                                $isOnline = (time() - $lastInform) < 300;
                                ?>
                                <tr>
                                    <td><code><?= htmlspecialchars($device['serial_number']) ?></code></td>
                                    <td>
                                        <?php if ($device['onu_sn']): ?>
                                        <a href="?page=huawei-olt&view=onus&search=<?= urlencode($device['onu_sn']) ?>">
                                            <?= htmlspecialchars($device['onu_name'] ?: $device['onu_sn']) ?>
                                        </a>
                                        <?php else: ?>
                                        <span class="text-muted">Not linked</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($device['manufacturer'] ?: '-') ?></td>
                                    <td><?= htmlspecialchars($device['model'] ?: '-') ?></td>
                                    <td>
                                        <?php if ($device['last_inform']): ?>
                                        <span title="<?= date('Y-m-d H:i:s', $lastInform) ?>">
                                            <?= date('M j, H:i', $lastInform) ?>
                                        </span>
                                        <?php else: ?>
                                        <span class="text-muted">Never</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $isOnline ? 'success' : 'secondary' ?>">
                                            <?= $isOnline ? 'Online' : 'Offline' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-primary" onclick="openWifiConfig('<?= htmlspecialchars($device['device_id']) ?>', '<?= htmlspecialchars($device['serial_number']) ?>')" title="Configure WiFi">
                                                <i class="bi bi-wifi"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-warning" onclick="openAdminPasswordConfig('<?= htmlspecialchars($device['device_id']) ?>', '<?= htmlspecialchars($device['serial_number']) ?>')" title="Change Admin Password">
                                                <i class="bi bi-key"></i>
                                            </button>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="action" value="tr069_refresh">
                                                <input type="hidden" name="device_id" value="<?= htmlspecialchars($device['device_id']) ?>">
                                                <button type="submit" class="btn btn-outline-info" title="Refresh Parameters">
                                                    <i class="bi bi-arrow-repeat"></i>
                                                </button>
                                            </form>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="action" value="tr069_reboot">
                                                <input type="hidden" name="device_id" value="<?= htmlspecialchars($device['device_id']) ?>">
                                                <button type="submit" class="btn btn-outline-warning" title="Reboot" onclick="return confirm('Reboot this device?')">
                                                    <i class="bi bi-power"></i>
                                                </button>
                                            </form>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="action" value="tr069_factory_reset">
                                                <input type="hidden" name="device_id" value="<?= htmlspecialchars($device['device_id']) ?>">
                                                <button type="submit" class="btn btn-outline-danger" title="Factory Reset" onclick="return confirm('Factory reset this device? All settings will be lost!')">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
            
            <?php elseif ($view === 'vpn'): ?>
            <?php
            $wgService = new \App\WireGuardService($db);
            $wgSettings = $wgService->getSettings();
            $wgServers = $wgService->getServers();
            $wgPeers = $wgService->getAllPeers();
            
            $oltsForVpn = [];
            try {
                $oltStmt = $db->query("SELECT id, name FROM huawei_olts ORDER BY name");
                $oltsForVpn = $oltStmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {}
            
            $csrfToken = $_SESSION['csrf_token'] ?? '';
            ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0"><i class="bi bi-shield-lock-fill me-2"></i>VPN Management</h4>
            </div>
            
            <div class="row">
                <div class="col-lg-4">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="bi bi-shield-lock-fill me-2"></i>VPN Settings</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="?page=huawei-olt&view=vpn">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="action" value="save_vpn_settings">
                                
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="vpnEnabled" name="vpn_enabled" <?= $wgSettings['vpn_enabled'] === 'true' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="vpnEnabled">Enable WireGuard VPN</label>
                                </div>
                                
                                <hr>
                                
                                <h6 class="text-muted mb-3"><i class="bi bi-hdd-network me-2"></i>Network Configuration</h6>
                                
                                <div class="mb-3">
                                    <label class="form-label">VPN Gateway IP</label>
                                    <input type="text" class="form-control" name="vpn_gateway_ip" value="<?= htmlspecialchars($wgSettings['vpn_gateway_ip']) ?>" placeholder="10.200.0.1">
                                    <div class="form-text">Server's private IP in the VPN tunnel</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">VPN Network</label>
                                    <input type="text" class="form-control" name="vpn_network" value="<?= htmlspecialchars($wgSettings['vpn_network']) ?>" placeholder="10.200.0.0/24">
                                    <div class="form-text">CIDR notation for VPN subnet</div>
                                </div>
                                
                                <hr>
                                
                                <h6 class="text-muted mb-3"><i class="bi bi-gear-wide-connected me-2"></i>TR-069 Integration</h6>
                                
                                <div class="alert alert-info small mb-3">
                                    <i class="bi bi-info-circle me-1"></i>
                                    <strong>TR-069 ACS URL (auto-generated):</strong><br>
                                    <code><?= htmlspecialchars($wgService->getTR069AcsUrl()) ?></code>
                                    <div class="form-text mt-1">Uses VPN Gateway IP on port 7547</div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-save me-2"></i>Save VPN Settings
                                </button>
                            </form>
                            
                            <hr class="my-3">
                            <form method="POST" action="?page=huawei-olt&view=vpn">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="action" value="sync_wireguard">
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="bi bi-arrow-repeat me-2"></i>Sync & Apply Config
                                </button>
                                <div class="form-text text-center mt-1">Writes config and applies via wg syncconf</div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Quick Ping Test Card -->
                    <div class="card shadow-sm">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="bi bi-broadcast me-2"></i>Quick Ping Test</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small mb-3">Test connectivity to any IP through the VPN tunnel (OLT, TR-069 devices, etc.)</p>
                            <div class="input-group mb-3">
                                <input type="text" class="form-control" id="pingTestIp" placeholder="192.168.1.1" pattern="^(\d{1,3}\.){3}\d{1,3}$">
                                <button class="btn btn-info" type="button" onclick="quickPingTest()" id="pingTestBtn">
                                    <i class="bi bi-broadcast me-1"></i>Ping
                                </button>
                            </div>
                            <div id="pingTestResult" style="display:none;"></div>
                            <div class="small text-muted">
                                <strong>Quick targets:</strong><br>
                                <a href="#" onclick="pingQuickTarget('<?= htmlspecialchars($wgSettings['vpn_gateway_ip']) ?>'); return false;" class="me-2">VPN Gateway</a>
                                <?php
                                $quickTargets = [];
                                try {
                                    $stmt = $db->query("SELECT DISTINCT network_cidr FROM wireguard_subnets WHERE is_active = TRUE LIMIT 3");
                                    $quickTargets = $stmt->fetchAll(\PDO::FETCH_COLUMN);
                                } catch (Exception $e) {}
                                foreach ($quickTargets as $cidr):
                                    $parts = explode('/', $cidr);
                                    $octets = explode('.', $parts[0]);
                                    $octets[3] = '1';
                                    $gwIp = implode('.', $octets);
                                ?>
                                <a href="#" onclick="pingQuickTarget('<?= htmlspecialchars($gwIp) ?>'); return false;" class="me-2"><?= htmlspecialchars($cidr) ?></a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card shadow-sm">
                        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-server me-2"></i>VPN Servers</h5>
                            <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#addServerModal">
                                <i class="bi bi-plus-lg"></i>
                            </button>
                        </div>
                        <div class="list-group list-group-flush">
                            <?php if (empty($wgServers)): ?>
                            <div class="list-group-item text-muted text-center py-4">
                                <i class="bi bi-server fs-3 d-block mb-2"></i>
                                No VPN servers configured
                            </div>
                            <?php else: ?>
                            <?php foreach ($wgServers as $server): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?= htmlspecialchars($server['name']) ?></strong>
                                    <br>
                                    <small class="text-muted">
                                        <?= htmlspecialchars($server['interface_addr']) ?> : <?= $server['listen_port'] ?>
                                    </small>
                                </div>
                                <div>
                                    <span class="badge <?= $server['enabled'] ? 'bg-success' : 'bg-secondary' ?>">
                                        <?= $server['enabled'] ? 'Active' : 'Disabled' ?>
                                    </span>
                                    <div class="btn-group btn-group-sm ms-2">
                                        <button class="btn btn-outline-primary" onclick="viewServerConfig(<?= $server['id'] ?>)" title="View Config">
                                            <i class="bi bi-file-code"></i>
                                        </button>
                                        <button class="btn btn-outline-danger" onclick="deleteServer(<?= $server['id'] ?>)" title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-8">
                    <div class="card shadow-sm">
                        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-diagram-3 me-2"></i>VPN Peers (OLT Sites)</h5>
                            <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#addPeerModal">
                                <i class="bi bi-plus-lg me-1"></i>Add Peer
                            </button>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($wgPeers)): ?>
                            <div class="text-center py-5 text-muted">
                                <i class="bi bi-diagram-3 fs-1 d-block mb-3"></i>
                                <p class="mb-0">No VPN peers configured</p>
                                <p class="small">Add peers to connect to OLT sites</p>
                            </div>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Name</th>
                                            <th>Allowed IPs</th>
                                            <th>Endpoint</th>
                                            <th>OLT Site</th>
                                            <th>Status</th>
                                            <th>Traffic</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($wgPeers as $peer): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($peer['name']) ?></strong>
                                                <?php if ($peer['description']): ?>
                                                <br><small class="text-muted"><?= htmlspecialchars($peer['description']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td><code><?= htmlspecialchars($peer['allowed_ips']) ?></code></td>
                                            <td><?= $peer['endpoint'] ? htmlspecialchars($peer['endpoint']) : '<span class="text-muted">-</span>' ?></td>
                                            <td>
                                                <?php if ($peer['is_olt_site']): ?>
                                                <span class="badge bg-info"><i class="bi bi-hdd-network me-1"></i>OLT</span>
                                                <?php else: ?>
                                                <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($peer['is_active']): ?>
                                                <span class="badge bg-success">Active</span>
                                                <?php else: ?>
                                                <span class="badge bg-secondary">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="small">
                                                <span class="text-success"><i class="bi bi-arrow-down"></i> <?= $wgService->formatBytes($peer['rx_bytes']) ?></span><br>
                                                <span class="text-primary"><i class="bi bi-arrow-up"></i> <?= $wgService->formatBytes($peer['tx_bytes']) ?></span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-success" onclick="testPeerConnectivity(<?= $peer['id'] ?>, '<?= htmlspecialchars($peer['name']) ?>')" title="Test Connectivity">
                                                        <i class="bi bi-wifi"></i>
                                                    </button>
                                                    <button class="btn btn-outline-primary" onclick="viewPeerConfig(<?= $peer['id'] ?>)" title="WireGuard Config">
                                                        <i class="bi bi-download"></i>
                                                    </button>
                                                    <button class="btn btn-outline-info" onclick="viewMikroTikScript(<?= $peer['id'] ?>)" title="MikroTik Script">
                                                        <i class="bi bi-terminal"></i>
                                                    </button>
                                                    <button class="btn btn-outline-warning" onclick="editPeer(<?= $peer['id'] ?>)" title="Edit">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button class="btn btn-outline-danger" onclick="deletePeer(<?= $peer['id'] ?>)" title="Delete">
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
                    
                    <div class="card shadow-sm mt-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="bi bi-diagram-2 me-2"></i>Network Architecture</h5>
                        </div>
                        <div class="card-body">
                            <pre class="bg-light p-3 rounded small mb-0" style="font-family: monospace;">
┌─────────────────────────────────────────────────────────────┐
│                         VPS (Cloud)                         │
│  ┌─────────┐  ┌──────────┐  ┌─────────┐  ┌───────────────┐ │
│  │   CRM   │  │ GenieACS │  │ Postgres│  │   WireGuard   │ │
│  │  (PHP)  │  │  (ACS)   │  │   DB    │  │  <?= htmlspecialchars($wgSettings['vpn_gateway_ip']) ?>   │ │
│  └─────────┘  └──────────┘  └─────────┘  └───────┬───────┘ │
│       Port 80/443   Port 7547                     │         │
└───────────────────────────────────────────────────│─────────┘
                                                    │ VPN Tunnel
┌───────────────────────────────────────────────────│─────────┐
│                    OLT Network                    │         │
│  ┌───────────────┐                    ┌───────────┴───────┐ │
│  │  Huawei OLT   │◄───────────────────│   WireGuard Peer  │ │
│  │   (MA5683T)   │  Telnet/SNMP       │    (Router/GW)    │ │
│  └───────────────┘                    └───────────────────┘ │
│         │                                                    │
│    ┌────┴────┐                                              │
│    │  CPEs   │──────► Internet ──────► GenieACS (TR-069)    │
│    └─────────┘                                              │
└──────────────────────────────────────────────────────────────┘
                            </pre>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="addServerModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST" action="?page=huawei-olt&view=vpn">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="action" value="add_vpn_server">
                            <div class="modal-header bg-dark text-white">
                                <h5 class="modal-title"><i class="bi bi-plus-lg me-2"></i>Add VPN Server</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">Server Name</label>
                                    <input type="text" class="form-control" name="name" required placeholder="Main VPN Server">
                                </div>
                                <div class="row">
                                    <div class="col-md-8 mb-3">
                                        <label class="form-label">Interface Address</label>
                                        <input type="text" class="form-control" name="interface_addr" required placeholder="10.200.0.1/24">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Listen Port</label>
                                        <input type="number" class="form-control" name="listen_port" value="51820" required>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Interface Name</label>
                                        <input type="text" class="form-control" name="interface_name" value="wg0">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">MTU</label>
                                        <input type="number" class="form-control" name="mtu" value="1420">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">DNS Servers</label>
                                    <input type="text" class="form-control" name="dns_servers" placeholder="1.1.1.1, 8.8.8.8">
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-plus-lg me-2"></i>Create Server
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="addPeerModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST" action="?page=huawei-olt&view=vpn">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="action" value="add_vpn_peer">
                            <div class="modal-header bg-success text-white">
                                <h5 class="modal-title"><i class="bi bi-plus-lg me-2"></i>Add VPN Peer</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <?php if (!empty($wgServers)): ?>
                                <div class="mb-3">
                                    <label class="form-label">Server</label>
                                    <select class="form-select" name="server_id">
                                        <option value="">Use default server</option>
                                        <?php foreach ($wgServers as $server): ?>
                                        <option value="<?= $server['id'] ?>"><?= htmlspecialchars($server['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <?php else: ?>
                                <div class="alert alert-info small mb-3">
                                    <i class="bi bi-info-circle me-1"></i>
                                    A default VPN server will be auto-created using your VPN settings.
                                </div>
                                <?php endif; ?>
                                <div class="mb-3">
                                    <label class="form-label">Peer Name</label>
                                    <input type="text" class="form-control" name="name" required placeholder="OLT Site - Location">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <input type="text" class="form-control" name="description" placeholder="Main OLT at data center">
                                </div>
                                <div class="row">
                                    <div class="col-md-8 mb-3">
                                        <label class="form-label">Allowed IPs</label>
                                        <input type="text" class="form-control" name="allowed_ips" required placeholder="10.200.0.2/32">
                                        <div class="form-text">Peer's VPN IP address</div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Keepalive</label>
                                        <input type="number" class="form-control" name="persistent_keepalive" value="25">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Endpoint (Optional)</label>
                                    <input type="text" class="form-control" name="endpoint" placeholder="102.205.239.85:51820">
                                    <div class="form-text">Public IP:Port of the peer's WireGuard</div>
                                </div>
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="isOltSite" name="is_olt_site" value="1">
                                    <label class="form-check-label" for="isOltSite">This is an OLT Site</label>
                                </div>
                                <div class="mb-3" id="oltSelectDiv" style="display: none;">
                                    <label class="form-label">Link to OLT</label>
                                    <select class="form-select" name="olt_id">
                                        <option value="">Select OLT...</option>
                                        <?php foreach ($oltsForVpn as $olt): ?>
                                        <option value="<?= $olt['id'] ?>"><?= htmlspecialchars($olt['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Routed Networks (one per line)</label>
                                    <textarea class="form-control" name="routed_networks" rows="4" placeholder="192.168.1.0/24&#10;10.10.0.0/24&#10;172.16.0.0/24"></textarea>
                                    <div class="form-text">Networks accessible through this peer (OLT management, TR-069 client ranges, etc.)</div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-plus-lg me-2"></i>Add Peer
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Edit Peer Modal -->
            <div class="modal fade" id="editPeerModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST" action="?page=huawei-olt&view=vpn" id="editPeerForm">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="action" value="edit_vpn_peer">
                            <input type="hidden" name="peer_id" id="editPeerId">
                            <div class="modal-header bg-warning">
                                <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit VPN Peer</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">Peer Name</label>
                                    <input type="text" class="form-control" name="name" id="editPeerName" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <input type="text" class="form-control" name="description" id="editPeerDescription">
                                </div>
                                <div class="row">
                                    <div class="col-md-8 mb-3">
                                        <label class="form-label">Allowed IPs</label>
                                        <input type="text" class="form-control" name="allowed_ips" id="editPeerAllowedIps" required>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Keepalive</label>
                                        <input type="number" class="form-control" name="persistent_keepalive" id="editPeerKeepalive">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Endpoint</label>
                                    <input type="text" class="form-control" name="endpoint" id="editPeerEndpoint">
                                </div>
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="editIsOltSite" name="is_olt_site" value="1">
                                    <label class="form-check-label" for="editIsOltSite">This is an OLT Site</label>
                                </div>
                                <div class="mb-3" id="editOltSelectDiv" style="display: none;">
                                    <label class="form-label">Link to OLT</label>
                                    <select class="form-select" name="olt_id" id="editPeerOltId">
                                        <option value="">Select OLT...</option>
                                        <?php foreach ($oltsForVpn as $olt): ?>
                                        <option value="<?= $olt['id'] ?>"><?= htmlspecialchars($olt['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Routed Networks (one per line)</label>
                                    <textarea class="form-control" name="routed_networks" id="editPeerRoutedNetworks" rows="4" placeholder="192.168.1.0/24&#10;10.10.0.0/24"></textarea>
                                    <div class="form-text">Networks accessible through this peer. Changes will restart WireGuard to apply new routes.</div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-warning">
                                    <i class="bi bi-check-lg me-2"></i>Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="configModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header bg-dark text-white">
                            <h5 class="modal-title" id="configModalTitle">Configuration</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <pre id="configContent" class="bg-light p-3 rounded" style="max-height: 400px; overflow-y: auto;"></pre>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-primary" onclick="copyConfig()">
                                <i class="bi bi-clipboard me-2"></i>Copy
                            </button>
                            <button type="button" class="btn btn-success" onclick="downloadConfig()">
                                <i class="bi bi-download me-2"></i>Download
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal fade" id="connectivityModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header bg-success text-white">
                            <h5 class="modal-title" id="connectivityModalTitle"><i class="bi bi-wifi me-2"></i>Connectivity Test</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body" id="connectivityResults">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
            
            <script>
            (function() {
                const isOltSiteEl = document.getElementById('isOltSite');
                const oltSelectDivEl = document.getElementById('oltSelectDiv');
                if (isOltSiteEl && oltSelectDivEl) {
                    isOltSiteEl.addEventListener('change', function() {
                        oltSelectDivEl.style.display = this.checked ? 'block' : 'none';
                    });
                }
                
                const editIsOltSiteEl = document.getElementById('editIsOltSite');
                const editOltSelectDivEl = document.getElementById('editOltSelectDiv');
                if (editIsOltSiteEl && editOltSelectDivEl) {
                    editIsOltSiteEl.addEventListener('change', function() {
                        editOltSelectDivEl.style.display = this.checked ? 'block' : 'none';
                    });
                }
            })();

            function viewServerConfig(serverId) {
                fetch(`?page=huawei-olt&view=vpn&action=get_server_config&server_id=${serverId}`)
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById('configModalTitle').textContent = 'WireGuard Server Configuration';
                            document.getElementById('configContent').textContent = data.config;
                            window.currentConfigName = data.filename || 'wg0.conf';
                            new bootstrap.Modal(document.getElementById('configModal')).show();
                        } else {
                            alert(data.error || 'Failed to get configuration');
                        }
                    });
            }

            function viewPeerConfig(peerId) {
                fetch(`?page=huawei-olt&view=vpn&action=get_peer_config&peer_id=${peerId}`)
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById('configModalTitle').textContent = 'WireGuard Peer Configuration';
                            document.getElementById('configContent').textContent = data.config;
                            window.currentConfigName = data.filename || 'peer.conf';
                            new bootstrap.Modal(document.getElementById('configModal')).show();
                        } else {
                            alert(data.error || 'Failed to get configuration');
                        }
                    });
            }

            function viewMikroTikScript(peerId) {
                fetch(`?page=huawei-olt&view=vpn&action=get_mikrotik_script&peer_id=${peerId}`)
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById('configModalTitle').textContent = 'MikroTik RouterOS Script';
                            document.getElementById('configContent').textContent = data.config;
                            window.currentConfigName = data.name || 'wireguard-setup.rsc';
                            new bootstrap.Modal(document.getElementById('configModal')).show();
                        } else {
                            alert(data.error || 'Failed to generate script');
                        }
                    });
            }

            function testPeerConnectivity(peerId, peerName) {
                document.getElementById('connectivityModalTitle').textContent = `Testing Connectivity: ${peerName}`;
                document.getElementById('connectivityResults').innerHTML = `
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-2 text-muted">Testing connectivity to VPN peer and routed networks...</p>
                    </div>
                `;
                new bootstrap.Modal(document.getElementById('connectivityModal')).show();
                
                fetch(`?page=huawei-olt&view=vpn&action=test_peer_connectivity&peer_id=${peerId}`)
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            const results = data.results;
                            let html = `<div class="mb-3">
                                <h6><i class="bi bi-router me-2"></i>VPN Tunnel: ${results.peer_ip}</h6>
                                <div class="d-flex align-items-center gap-2">
                                    ${results.vpn_reachable 
                                        ? '<span class="badge bg-success"><i class="bi bi-check-lg"></i> Reachable</span>' 
                                        : '<span class="badge bg-danger"><i class="bi bi-x-lg"></i> Unreachable</span>'}
                                    ${results.vpn_latency ? `<span class="text-muted small">${results.vpn_latency.toFixed(1)} ms</span>` : ''}
                                </div>
                            </div>`;
                            
                            if (results.networks && results.networks.length > 0) {
                                html += `<h6 class="mt-4"><i class="bi bi-diagram-3 me-2"></i>Routed Networks</h6>`;
                                html += `<table class="table table-sm table-bordered">
                                    <thead class="table-light">
                                        <tr><th>Network</th><th>Test IP</th><th>Status</th><th>Latency</th></tr>
                                    </thead>
                                    <tbody>`;
                                results.networks.forEach(net => {
                                    html += `<tr>
                                        <td><code>${net.network}</code></td>
                                        <td><code>${net.test_ip}</code></td>
                                        <td>${net.reachable 
                                            ? '<span class="badge bg-success"><i class="bi bi-check-lg"></i> OK</span>' 
                                            : '<span class="badge bg-danger"><i class="bi bi-x-lg"></i> Failed</span>'}</td>
                                        <td>${net.latency ? net.latency.toFixed(1) + ' ms' : '-'}</td>
                                    </tr>`;
                                });
                                html += `</tbody></table>`;
                            } else {
                                html += `<div class="alert alert-info mt-3 mb-0"><i class="bi bi-info-circle me-2"></i>No routed networks configured for this peer.</div>`;
                            }
                            
                            document.getElementById('connectivityResults').innerHTML = html;
                        } else {
                            document.getElementById('connectivityResults').innerHTML = `
                                <div class="alert alert-danger mb-0"><i class="bi bi-exclamation-triangle me-2"></i>${data.error || 'Connectivity test failed'}</div>
                            `;
                        }
                    })
                    .catch(err => {
                        document.getElementById('connectivityResults').innerHTML = `
                            <div class="alert alert-danger mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Error: ${err.message}</div>
                        `;
                    });
            }
            
            function pingQuickTarget(ip) {
                document.getElementById('pingTestIp').value = ip;
                quickPingTest();
            }
            
            function quickPingTest() {
                const ip = document.getElementById('pingTestIp').value.trim();
                if (!ip || !/^(\d{1,3}\.){3}\d{1,3}$/.test(ip)) {
                    document.getElementById('pingTestResult').style.display = 'block';
                    document.getElementById('pingTestResult').innerHTML = `
                        <div class="alert alert-warning py-2 mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Please enter a valid IP address</div>
                    `;
                    return;
                }
                
                const btn = document.getElementById('pingTestBtn');
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Pinging...';
                
                document.getElementById('pingTestResult').style.display = 'block';
                document.getElementById('pingTestResult').innerHTML = `
                    <div class="text-center py-2"><div class="spinner-border spinner-border-sm text-info"></div> Pinging ${ip}...</div>
                `;
                
                fetch(`?page=huawei-olt&view=vpn&action=test_ip&ip=${encodeURIComponent(ip)}`)
                    .then(r => r.json())
                    .then(data => {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="bi bi-broadcast me-1"></i>Ping';
                        
                        if (data.success) {
                            document.getElementById('pingTestResult').innerHTML = `
                                <div class="alert alert-success py-2 mb-0">
                                    <i class="bi bi-check-circle me-2"></i><strong>${ip}</strong> is reachable
                                    <br><small class="text-muted">Latency: ${data.latency_avg ? data.latency_avg.toFixed(1) + ' ms' : 'N/A'} | Packets: ${data.packets_received}/${data.packets_sent}</small>
                                </div>
                            `;
                        } else {
                            document.getElementById('pingTestResult').innerHTML = `
                                <div class="alert alert-danger py-2 mb-0">
                                    <i class="bi bi-x-circle me-2"></i><strong>${ip}</strong> is unreachable
                                    <br><small class="text-muted">Packets: ${data.packets_received || 0}/${data.packets_sent || 3} received</small>
                                </div>
                            `;
                        }
                    })
                    .catch(err => {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="bi bi-broadcast me-1"></i>Ping';
                        document.getElementById('pingTestResult').innerHTML = `
                            <div class="alert alert-danger py-2 mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Error: ${err.message}</div>
                        `;
                    });
            }

            function editPeer(peerId) {
                fetch(`?page=huawei-olt&view=vpn&action=get_peer_data&peer_id=${peerId}`)
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            const peer = data.peer;
                            document.getElementById('editPeerId').value = peer.id;
                            document.getElementById('editPeerName').value = peer.name || '';
                            document.getElementById('editPeerDescription').value = peer.description || '';
                            document.getElementById('editPeerAllowedIps').value = peer.allowed_ips || '';
                            document.getElementById('editPeerKeepalive').value = peer.persistent_keepalive || 25;
                            document.getElementById('editPeerEndpoint').value = peer.endpoint || '';
                            document.getElementById('editIsOltSite').checked = peer.is_olt_site;
                            document.getElementById('editPeerOltId').value = peer.olt_id || '';
                            document.getElementById('editOltSelectDiv').style.display = peer.is_olt_site ? 'block' : 'none';
                            document.getElementById('editPeerRoutedNetworks').value = data.subnets || '';
                            new bootstrap.Modal(document.getElementById('editPeerModal')).show();
                        } else {
                            alert(data.error || 'Failed to load peer data');
                        }
                    });
            }
            
            document.getElementById('editIsOltSite').addEventListener('change', function() {
                document.getElementById('editOltSelectDiv').style.display = this.checked ? 'block' : 'none';
            });

            function deletePeer(peerId) {
                if (confirm('Delete this VPN peer?')) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '?page=huawei-olt&view=vpn';
                    form.innerHTML = `
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="action" value="delete_vpn_peer">
                        <input type="hidden" name="peer_id" value="${peerId}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            }

            function deleteServer(serverId) {
                if (confirm('Delete this VPN server? All peers will be removed.')) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '?page=huawei-olt&view=vpn';
                    form.innerHTML = `
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="action" value="delete_vpn_server">
                        <input type="hidden" name="server_id" value="${serverId}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            }

            function copyConfig() {
                const config = document.getElementById('configContent').textContent;
                navigator.clipboard.writeText(config).then(() => {
                    alert('Configuration copied to clipboard');
                });
            }

            function downloadConfig() {
                const config = document.getElementById('configContent').textContent;
                const blob = new Blob([config], { type: 'text/plain' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = window.currentConfigName || 'wireguard.conf';
                a.click();
                URL.revokeObjectURL(url);
            }
            </script>
            
            <?php elseif ($view === 'migrations'): ?>
            <?php
            $migOltId = isset($_GET['olt_id']) ? (int)$_GET['olt_id'] : null;
            $migSlot = isset($_GET['slot']) ? (int)$_GET['slot'] : null;
            $migPort = isset($_GET['port']) ? (int)$_GET['port'] : null;
            
            $migOnus = [];
            $migPortInfo = ['used_ports' => [], 'available_ports' => []];
            if ($migOltId) {
                $migOnus = $huaweiOLT->getONUsForMigration($migOltId, $migSlot, $migPort);
                $migPortInfo = $huaweiOLT->getAvailablePorts($migOltId);
            }
            ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="page-title mb-1"><i class="bi bi-arrow-left-right"></i> ONU Migrations</h4>
                    <small class="text-muted">Move ONUs between PON ports individually or in bulk</small>
                </div>
            </div>
            
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-funnel me-2"></i>Select Source</h5>
                </div>
                <div class="card-body">
                    <form method="get" class="row g-3">
                        <input type="hidden" name="page" value="huawei-olt">
                        <input type="hidden" name="view" value="migrations">
                        <div class="col-md-4">
                            <label class="form-label">OLT Device</label>
                            <select name="olt_id" id="migOltSelect" class="form-select" required onchange="this.form.submit()">
                                <option value="">-- Select OLT --</option>
                                <?php foreach ($olts as $olt): ?>
                                <option value="<?= $olt['id'] ?>" <?= $migOltId == $olt['id'] ? 'selected' : '' ?>><?= htmlspecialchars($olt['name']) ?> (<?= $olt['ip_address'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if ($migOltId): ?>
                        <div class="col-md-3">
                            <label class="form-label">Filter by Slot</label>
                            <select name="slot" class="form-select" onchange="this.form.submit()">
                                <option value="">All Slots</option>
                                <?php
                                $usedSlots = array_unique(array_column($migPortInfo['used_ports'], 'slot'));
                                sort($usedSlots);
                                foreach ($usedSlots as $s):
                                ?>
                                <option value="<?= $s ?>" <?= $migSlot === $s ? 'selected' : '' ?>>Slot <?= $s ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Filter by Port</label>
                            <select name="port" class="form-select" onchange="this.form.submit()">
                                <option value="">All Ports</option>
                                <?php for ($p = 0; $p < 16; $p++): ?>
                                <option value="<?= $p ?>" <?= $migPort === $p ? 'selected' : '' ?>>Port <?= $p ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i> Filter</button>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            
            <?php if ($migOltId && !empty($migOnus)): ?>
            <ul class="nav nav-tabs mb-4" id="migrationTabs">
                <li class="nav-item">
                    <a class="nav-link active" data-bs-toggle="tab" href="#singleMigration">
                        <i class="bi bi-arrow-right-circle me-1"></i> Single Migration
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#bulkMigration">
                        <i class="bi bi-arrow-left-right me-1"></i> Bulk Migration
                    </a>
                </li>
            </ul>
            
            <div class="tab-content">
                <div class="tab-pane fade show active" id="singleMigration">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>ONUs Available for Migration (<?= count($migOnus) ?>)</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>SN</th>
                                            <th>Name</th>
                                            <th>Customer</th>
                                            <th>Current Location</th>
                                            <th>Status</th>
                                            <th>RX Power</th>
                                            <th class="text-end">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($migOnus as $monu): ?>
                                        <tr>
                                            <td><code><?= htmlspecialchars($monu['sn']) ?></code></td>
                                            <td><?= htmlspecialchars($monu['name'] ?: '-') ?></td>
                                            <td><?= htmlspecialchars($monu['customer_name'] ?: '-') ?></td>
                                            <td><span class="badge bg-secondary"><?= $monu['frame'] ?>/<?= $monu['slot'] ?>/<?= $monu['port'] ?>:<?= $monu['onu_id'] ?></span></td>
                                            <td>
                                                <?php if ($monu['status'] === 'online'): ?>
                                                <span class="badge bg-success">Online</span>
                                                <?php elseif ($monu['status'] === 'offline'): ?>
                                                <span class="badge bg-danger">Offline</span>
                                                <?php else: ?>
                                                <span class="badge bg-warning"><?= ucfirst($monu['status']) ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= $monu['rx_power'] ? number_format((float)$monu['rx_power'], 2) . ' dBm' : '-' ?></td>
                                            <td class="text-end">
                                                <button class="btn btn-sm btn-outline-primary" onclick="openMoveModal(<?= $monu['id'] ?>, '<?= htmlspecialchars($monu['sn']) ?>', <?= $monu['slot'] ?>, <?= $monu['port'] ?>, <?= $monu['onu_id'] ?>)">
                                                    <i class="bi bi-arrow-right-circle"></i> Move
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="tab-pane fade" id="bulkMigration">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="bi bi-arrow-left-right me-2"></i>Bulk Port Migration</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                <strong>Bulk Migration:</strong> Move all ONUs from one port to another. Uses add-first strategy for safety.
                            </div>
                            
                            <form method="post" action="?page=huawei-olt&view=migrations" onsubmit="return confirmBulkMigration()">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="action" value="bulk_move_onus">
                                <input type="hidden" name="olt_id" value="<?= $migOltId ?>">
                                
                                <div class="row g-3 mb-4">
                                    <div class="col-md-6">
                                        <div class="card border">
                                            <div class="card-header bg-light">
                                                <strong>Source Port</strong>
                                            </div>
                                            <div class="card-body">
                                                <div class="row g-2">
                                                    <div class="col-6">
                                                        <label class="form-label">Slot</label>
                                                        <select id="bulkSourceSlot" class="form-select" onchange="updateBulkSourcePort()">
                                                            <?php foreach ($usedSlots as $s): ?>
                                                            <option value="<?= $s ?>" <?= $migSlot === $s ? 'selected' : '' ?>>Slot <?= $s ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-6">
                                                        <label class="form-label">Port</label>
                                                        <select id="bulkSourcePort" class="form-select" onchange="updateBulkPreview()">
                                                            <?php for ($p = 0; $p < 16; $p++): ?>
                                                            <option value="<?= $p ?>" <?= $migPort === $p ? 'selected' : '' ?>>Port <?= $p ?></option>
                                                            <?php endfor; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="mt-2">
                                                    <small class="text-muted">ONUs on this port: <span id="bulkSourceCount"><?= count($migOnus) ?></span></small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="card border">
                                            <div class="card-header bg-light">
                                                <strong>Destination Port</strong>
                                            </div>
                                            <div class="card-body">
                                                <div class="row g-2">
                                                    <div class="col-6">
                                                        <label class="form-label">Slot</label>
                                                        <select id="bulkDestSlot" name="dest_slot" class="form-select" required>
                                                            <?php foreach ($usedSlots as $s): ?>
                                                            <option value="<?= $s ?>">Slot <?= $s ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-6">
                                                        <label class="form-label">Port</label>
                                                        <select id="bulkDestPort" name="dest_port" class="form-select" required>
                                                            <?php for ($p = 0; $p < 16; $p++): ?>
                                                            <option value="<?= $p ?>">Port <?= $p ?></option>
                                                            <?php endfor; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div id="bulkMigrationPreview" class="mb-4" style="display: none;">
                                    <h6>ONUs to Migrate:</h6>
                                    <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                                        <table class="table table-sm table-bordered">
                                            <thead class="table-light">
                                                <tr>
                                                    <th><input type="checkbox" id="selectAllBulk" checked onchange="toggleAllBulk()"></th>
                                                    <th>SN</th>
                                                    <th>Name</th>
                                                    <th>Current</th>
                                                    <th>New</th>
                                                </tr>
                                            </thead>
                                            <tbody id="bulkMigrationTable">
                                                <?php foreach ($migOnus as $idx => $monu): ?>
                                                <tr>
                                                    <td><input type="checkbox" name="migrations[<?= $idx ?>][selected]" class="bulk-select" checked></td>
                                                    <td><code><?= htmlspecialchars($monu['sn']) ?></code></td>
                                                    <td><?= htmlspecialchars($monu['name'] ?: '-') ?></td>
                                                    <td><?= $monu['frame'] ?>/<?= $monu['slot'] ?>/<?= $monu['port'] ?>:<?= $monu['onu_id'] ?></td>
                                                    <td class="bulk-dest">--</td>
                                                    <input type="hidden" name="migrations[<?= $idx ?>][onu_id]" value="<?= $monu['id'] ?>">
                                                    <input type="hidden" name="migrations[<?= $idx ?>][new_slot]" class="bulk-new-slot" value="">
                                                    <input type="hidden" name="migrations[<?= $idx ?>][new_port]" class="bulk-new-port" value="">
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                
                                <button type="button" class="btn btn-secondary me-2" onclick="previewBulkMigration()">
                                    <i class="bi bi-eye me-1"></i> Preview Migration
                                </button>
                                <button type="submit" class="btn btn-warning" id="bulkMigrateBtn" disabled>
                                    <i class="bi bi-arrow-left-right me-1"></i> Execute Bulk Migration
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal fade" id="moveOnuModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="bi bi-arrow-right-circle me-2"></i>Move ONU</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="post" action="?page=huawei-olt&view=migrations">
                            <div class="modal-body">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="action" value="move_onu">
                                <input type="hidden" name="onu_id" id="moveOnuId">
                                <input type="hidden" name="redirect_view" value="migrations&olt_id=<?= $migOltId ?>">
                                
                                <div class="alert alert-info mb-3">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Moving ONU: <strong id="moveOnuSn"></strong><br>
                                    <small>Current: <span id="moveOnuCurrent"></span></small>
                                </div>
                                
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">New Slot</label>
                                        <select name="new_slot" id="moveNewSlot" class="form-select" required>
                                            <?php foreach ($usedSlots as $s): ?>
                                            <option value="<?= $s ?>">Slot <?= $s ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">New Port</label>
                                        <select name="new_port" id="moveNewPort" class="form-select" required>
                                            <?php for ($p = 0; $p < 16; $p++): ?>
                                            <option value="<?= $p ?>">Port <?= $p ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">ONU ID <small class="text-muted">(optional)</small></label>
                                        <input type="number" name="new_onu_id" id="moveNewOnuId" class="form-control" min="0" max="127" placeholder="Auto">
                                    </div>
                                </div>
                                
                                <div class="alert alert-warning mt-3">
                                    <i class="bi bi-exclamation-triangle me-1"></i>
                                    <strong>Warning:</strong> This will briefly disconnect the ONU during migration.
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary"><i class="bi bi-arrow-right-circle me-1"></i> Move ONU</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <script>
            function openMoveModal(onuId, sn, slot, port, onuIdNum) {
                document.getElementById('moveOnuId').value = onuId;
                document.getElementById('moveOnuSn').textContent = sn;
                document.getElementById('moveOnuCurrent').textContent = '0/' + slot + '/' + port + ':' + onuIdNum;
                new bootstrap.Modal(document.getElementById('moveOnuModal')).show();
            }
            
            function previewBulkMigration() {
                const destSlot = document.getElementById('bulkDestSlot').value;
                const destPort = document.getElementById('bulkDestPort').value;
                
                document.querySelectorAll('.bulk-dest').forEach(el => {
                    el.textContent = '0/' + destSlot + '/' + destPort + ':auto';
                });
                document.querySelectorAll('.bulk-new-slot').forEach(el => {
                    el.value = destSlot;
                });
                document.querySelectorAll('.bulk-new-port').forEach(el => {
                    el.value = destPort;
                });
                
                document.getElementById('bulkMigrationPreview').style.display = 'block';
                document.getElementById('bulkMigrateBtn').disabled = false;
            }
            
            function toggleAllBulk() {
                const checked = document.getElementById('selectAllBulk').checked;
                document.querySelectorAll('.bulk-select').forEach(cb => cb.checked = checked);
            }
            
            function confirmBulkMigration() {
                const count = document.querySelectorAll('.bulk-select:checked').length;
                return confirm('Are you sure you want to migrate ' + count + ' ONU(s)? This will briefly disconnect each ONU.');
            }
            </script>
            
            <?php elseif ($migOltId && empty($migOnus)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i>
                No authorized ONUs found for the selected filters. Try adjusting the slot/port filters or select a different OLT.
            </div>
            <?php elseif (!$migOltId): ?>
            <div class="alert alert-secondary">
                <i class="bi bi-arrow-up-circle me-2"></i>
                Please select an OLT device above to view ONUs available for migration.
            </div>
            <?php endif; ?>
            
            <?php elseif ($view === 'settings'): ?>
            <?php
            $settingsTab = $_GET['tab'] ?? 'genieacs';
            
            $genieacsSettings = [];
            try {
                $stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'genieacs_%'");
                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $genieacsSettings[$row['setting_key']] = $row['setting_value'];
                }
            } catch (Exception $e) {}
            
            $csrfToken = $_SESSION['csrf_token'] ?? '';
            ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0"><i class="bi bi-gear me-2"></i>OMS Settings</h4>
            </div>
            
            <ul class="nav nav-tabs mb-4">
                <li class="nav-item">
                    <a class="nav-link <?= $settingsTab === 'genieacs' ? 'active' : '' ?>" href="?page=huawei-olt&view=settings&tab=genieacs">
                        <i class="bi bi-gear-wide-connected me-1"></i> GenieACS / TR-069
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $settingsTab === 'tr069_omci' ? 'active' : '' ?>" href="?page=huawei-olt&view=settings&tab=tr069_omci">
                        <i class="bi bi-broadcast me-1"></i> TR-069 OMCI Settings
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $settingsTab === 'smartolt' ? 'active' : '' ?>" href="?page=huawei-olt&view=settings&tab=smartolt">
                        <i class="bi bi-cloud-download me-1"></i> SmartOLT Import
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $settingsTab === 'onu_types' ? 'active' : '' ?>" href="?page=huawei-olt&view=settings&tab=onu_types">
                        <i class="bi bi-router me-1"></i> ONU Types
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $settingsTab === 'scripts' ? 'active' : '' ?>" href="?page=huawei-olt&view=settings&tab=scripts">
                        <i class="bi bi-terminal me-1"></i> OLT Scripts
                    </a>
                </li>
            </ul>
            
            <?php if ($settingsTab === 'genieacs'): ?>
            <div class="row">
                <div class="col-lg-6">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="bi bi-gear-wide-connected me-2"></i>GenieACS / TR-069 Settings</h5>
                        </div>
                        <div class="card-body">
                            <form method="post">
                                <input type="hidden" name="action" value="save_genieacs_settings">
                                
                                <div class="mb-3 form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="genieacs_enabled" id="genieacsEnabled" <?= ($genieacsSettings['genieacs_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="genieacsEnabled">Enable GenieACS Integration</label>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">GenieACS NBI URL</label>
                                    <input type="url" name="genieacs_url" class="form-control" value="<?= htmlspecialchars($genieacsSettings['genieacs_url'] ?? 'http://localhost:7557') ?>" placeholder="http://genieacs:7557">
                                    <div class="form-text">The NBI (North Bound Interface) URL, usually port 7557</div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-6 mb-3">
                                        <label class="form-label">Username (optional)</label>
                                        <input type="text" name="genieacs_username" class="form-control" value="<?= htmlspecialchars($genieacsSettings['genieacs_username'] ?? '') ?>">
                                    </div>
                                    <div class="col-6 mb-3">
                                        <label class="form-label">Password</label>
                                        <input type="password" name="genieacs_password" class="form-control" value="<?= htmlspecialchars($genieacsSettings['genieacs_password'] ?? '') ?>" placeholder="Leave blank to keep existing">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Timeout (seconds)</label>
                                    <input type="number" name="genieacs_timeout" class="form-control" value="<?= htmlspecialchars($genieacsSettings['genieacs_timeout'] ?? '30') ?>" min="5" max="120">
                                </div>
                                
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Save Settings</button>
                                    <button type="submit" name="action" value="test_genieacs" class="btn btn-outline-secondary"><i class="bi bi-plug me-1"></i> Test Connection</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>GenieACS Setup Guide</h5>
                        </div>
                        <div class="card-body">
                            <h6>1. Deploy GenieACS</h6>
                            <p class="small">Using Docker Compose (recommended):</p>
                            <pre class="bg-light p-2 rounded small">services:
  genieacs:
    image: genieacs/genieacs
    ports:
      - "7547:7547"  # CWMP (for ONUs)
      - "7557:7557"  # NBI (for CRM)
      - "3000:3000"  # Web UI</pre>
                            
                            <h6 class="mt-3">2. Configure OLT TR-069 Profile</h6>
                            <pre class="bg-light p-2 rounded small">ont tr069-server-profile add profile-id 1 \
  url http://YOUR_SERVER:7547/ \
  user admin admin</pre>
                            
                            <h6 class="mt-3">3. Apply to ONUs</h6>
                            <pre class="bg-light p-2 rounded small">interface gpon 0/1
ont tr069-server-config 1 all profile-id 1</pre>
                            
                            <h6 class="mt-3">4. Enter NBI URL Above</h6>
                            <p class="small mb-0">Use <code>http://YOUR_SERVER:7557</code> for the NBI URL in settings.</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php elseif ($settingsTab === 'tr069_omci'): ?>
            <?php
            $tr069Settings = [];
            try {
                $stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'tr069_%'");
                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $tr069Settings[$row['setting_key']] = $row['setting_value'];
                }
            } catch (Exception $e) {}
            ?>
            <div class="row">
                <div class="col-lg-6">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="bi bi-broadcast me-2"></i>TR-069 OMCI Configuration</h5>
                        </div>
                        <div class="card-body">
                            <form method="post">
                                <input type="hidden" name="action" value="save_tr069_omci_settings">
                                
                                <div class="mb-3">
                                    <label class="form-label">TR-069 ACS URL</label>
                                    <input type="url" name="tr069_acs_url" class="form-control" 
                                           value="<?= htmlspecialchars($tr069Settings['tr069_acs_url'] ?? 'http://10.200.0.1:7547') ?>" 
                                           placeholder="http://10.200.0.1:7547">
                                    <div class="form-text">The ACS URL that ONUs will connect to (e.g., http://your-vpn-gateway:7547)</div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Periodic Inform Interval (seconds)</label>
                                        <input type="number" name="tr069_periodic_interval" class="form-control" value="<?= htmlspecialchars($tr069Settings['tr069_periodic_interval'] ?? '300') ?>" min="60" max="86400">
                                        <div class="form-text">How often ONUs report to ACS (300 = 5 min)</div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Default GEM Port for TR-069</label>
                                        <input type="number" name="tr069_default_gem_port" class="form-control" value="<?= htmlspecialchars($tr069Settings['tr069_default_gem_port'] ?? '2') ?>" min="1" max="8">
                                        <div class="form-text">GEM port used for TR-069 traffic</div>
                                    </div>
                                </div>
                                
                                <hr class="my-3">
                                <h6 class="text-muted"><i class="bi bi-shield-lock me-2"></i>ACS Authentication (Optional)</h6>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">ACS Username</label>
                                        <input type="text" name="tr069_acs_username" class="form-control" value="<?= htmlspecialchars($tr069Settings['tr069_acs_username'] ?? '') ?>" placeholder="Optional">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">ACS Password</label>
                                        <input type="password" name="tr069_acs_password" class="form-control" value="<?= htmlspecialchars($tr069Settings['tr069_acs_password'] ?? '') ?>" placeholder="Optional">
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">CPE Username (for ACS to authenticate CPE)</label>
                                        <input type="text" name="tr069_cpe_username" class="form-control" value="<?= htmlspecialchars($tr069Settings['tr069_cpe_username'] ?? '') ?>" placeholder="Optional">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">CPE Password</label>
                                        <input type="password" name="tr069_cpe_password" class="form-control" value="<?= htmlspecialchars($tr069Settings['tr069_cpe_password'] ?? '') ?>" placeholder="Optional">
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Save TR-069 Settings</button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>How TR-069 OMCI Works</h5>
                        </div>
                        <div class="card-body">
                            <p class="small">When you authorize an ONU, the system will automatically:</p>
                            <ol class="small">
                                <li><strong>Find TR-069 VLAN:</strong> Looks for a VLAN with "TR-069 Management VLAN" feature enabled in OLT VLANs</li>
                                <li><strong>Configure Native VLAN:</strong> Sets the TR-069 VLAN on ONU's ETH port 1</li>
                                <li><strong>Set DHCP Mode:</strong> Configures the ONU to get IP via DHCP on TR-069 VLAN</li>
                                <li><strong>Push ACS URL:</strong> Sends your ACS URL to the ONU via OMCI</li>
                                <li><strong>Enable Periodic Inform:</strong> ONU will report to ACS at configured interval</li>
                            </ol>
                            
                            <h6 class="mt-3">Commands Sent to OLT:</h6>
                            <pre class="bg-dark text-light p-2 rounded small" style="font-size: 11px;">
interface gpon 0/X
ont port native-vlan {port} {onu_id} eth 1 vlan {tr069_vlan} priority 0
ont ipconfig {port} {onu_id} ip-index 0 dhcp vlan {tr069_vlan}
ont tr069-server-config {port} {onu_id} acs-url "{acs_url}"
ont tr069-server-config {port} {onu_id} periodic-inform enable interval 300
quit
service-port vlan {tr069_vlan} gpon 0/X/{port} ont {onu_id} gemport 2</pre>
                            
                            <div class="alert alert-warning small mt-3 mb-0">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                <strong>Requirement:</strong> Go to OLT → VLANs, edit a VLAN, and tick "TR-069 Management VLAN" feature for auto-configuration to work.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php elseif ($settingsTab === 'smartolt'): ?>
            <?php
            require_once __DIR__ . '/../src/SmartOLT.php';
            $smartolt = new \App\SmartOLT($db);
            $smartoltConfigured = $smartolt->isConfigured();
            $smartoltSettings = $smartolt->getSettings();
            
            $smartOlts = [];
            $smartOnuCount = 0;
            if ($smartoltConfigured) {
                $oltsResult = $smartolt->getOLTs();
                if ($oltsResult['status'] && isset($oltsResult['response'])) {
                    $smartOlts = $oltsResult['response'];
                }
                $onusResult = $smartolt->getAllONUsDetails();
                if ($onusResult['status'] && isset($onusResult['response'])) {
                    $smartOnuCount = count($onusResult['response']);
                }
            }
            
            $localOlts = $huaweiOLT->getOLTs();
            ?>
            <div class="row">
                <div class="col-lg-6">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="bi bi-cloud me-2"></i>SmartOLT API Settings</h5>
                        </div>
                        <div class="card-body">
                            <form method="post">
                                <input type="hidden" name="action" value="save_smartolt_settings">
                                
                                <div class="mb-3">
                                    <label class="form-label">SmartOLT API URL</label>
                                    <input type="url" name="api_url" class="form-control" value="<?= htmlspecialchars($smartoltSettings['api_url'] ?? '') ?>" placeholder="https://your-smartolt.com">
                                    <div class="form-text">Your SmartOLT server URL (without /api)</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">API Key</label>
                                    <input type="text" name="api_key" class="form-control" value="<?= htmlspecialchars($smartoltSettings['api_key'] ?? '') ?>" placeholder="Your SmartOLT API key">
                                    <div class="form-text">Find this in SmartOLT > Settings > API</div>
                                </div>
                                
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Save Settings</button>
                                    <button type="submit" name="action" value="test_smartolt" class="btn btn-outline-secondary"><i class="bi bi-plug me-1"></i> Test Connection</button>
                                </div>
                            </form>
                            
                            <?php if ($smartoltConfigured): ?>
                            <hr>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-success"><i class="bi bi-check-circle me-1"></i> Connected to SmartOLT</span>
                                <span class="badge bg-primary"><?= $smartOnuCount ?> ONUs available</span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="bi bi-cloud-download me-2"></i>Bulk Import from SmartOLT</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!$smartoltConfigured): ?>
                            <div class="alert alert-warning mb-0">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                Configure SmartOLT API settings first to enable bulk import.
                            </div>
                            <?php elseif (empty($smartOlts)): ?>
                            <div class="alert alert-warning mb-0">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                No OLTs found in SmartOLT. Check your API connection.
                            </div>
                            <?php else: ?>
                            <form method="post" onsubmit="return confirm('Import all ONUs from SmartOLT? This will add new ONUs and update existing ones.')">
                                <input type="hidden" name="action" value="bulk_import_smartolt">
                                
                                <p class="text-muted small">Map each SmartOLT OLT to a local OLT device. Unmapped OLTs will be skipped.</p>
                                
                                <div class="table-responsive mb-3">
                                    <table class="table table-sm table-bordered">
                                        <thead class="table-light">
                                            <tr>
                                                <th>SmartOLT Device</th>
                                                <th>ONUs</th>
                                                <th>Map to Local OLT</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($smartOlts as $solt): 
                                                $existingMapping = null;
                                                foreach ($localOlts as $lo) {
                                                    if ($lo['smartolt_id'] == $solt['id']) {
                                                        $existingMapping = $lo['id'];
                                                        break;
                                                    }
                                                }
                                            ?>
                                            <tr>
                                                <td>
                                                    <strong><?= htmlspecialchars($solt['name'] ?? 'OLT ' . $solt['id']) ?></strong>
                                                    <br><small class="text-muted"><?= htmlspecialchars($solt['ip'] ?? '') ?></small>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-secondary"><?= $solt['onu_count'] ?? '?' ?></span>
                                                </td>
                                                <td>
                                                    <select name="olt_map_<?= $solt['id'] ?>" class="form-select form-select-sm">
                                                        <option value="">-- Skip --</option>
                                                        <?php foreach ($localOlts as $lo): ?>
                                                        <option value="<?= $lo['id'] ?>" <?= $existingMapping == $lo['id'] ? 'selected' : '' ?>><?= htmlspecialchars($lo['name']) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="alert alert-info small">
                                    <i class="bi bi-info-circle me-1"></i>
                                    <strong>Total available:</strong> <?= $smartOnuCount ?> ONUs across <?= count($smartOlts) ?> OLT(s)
                                </div>
                                
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" name="sync_optical" id="syncOptical" checked>
                                    <label class="form-check-label" for="syncOptical">
                                        Sync optical power levels from OLT after import (via SNMP)
                                    </label>
                                </div>
                                
                                <button type="submit" class="btn btn-primary w-100" onclick="showLoading('Importing ONUs from SmartOLT... This may take a while.')">
                                    <i class="bi bi-cloud-download me-1"></i> Import All ONUs
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- CSV Import from SmartOLT Export -->
                    <div class="card mt-3">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="bi bi-file-earmark-spreadsheet me-2"></i>Import from SmartOLT CSV Export</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small mb-3">
                                Upload a CSV file exported from SmartOLT. This preserves correct ONU IDs and mappings.
                            </p>
                            <form method="post" enctype="multipart/form-data" onsubmit="return confirm('Import ONUs from CSV file? This will add new ONUs and update existing ones.')">
                                <input type="hidden" name="action" value="import_smartolt_csv">
                                
                                <div class="mb-3">
                                    <label class="form-label">Target OLT</label>
                                    <select name="olt_id" class="form-select" required>
                                        <option value="">-- Select OLT --</option>
                                        <?php foreach ($olts as $o): ?>
                                        <option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">All ONUs from the CSV will be assigned to this OLT</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">SmartOLT CSV File</label>
                                    <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                                    <div class="form-text">Export from SmartOLT: OLT &rarr; ONUs &rarr; Export CSV</div>
                                </div>
                                
                                <div class="alert alert-info small mb-3">
                                    <i class="bi bi-info-circle me-1"></i>
                                    <strong>CSV Format:</strong> The CSV should have columns: SN, Onu Type, Name, Board, Port, Allocated ONU, Zone, Address, Status, Signal levels, etc.
                                    <br><strong>Note:</strong> ONUs are imported as "already authorized" since they exist on the OLT.
                                </div>
                                
                                <button type="submit" class="btn btn-success w-100" onclick="showLoading('Importing ONUs from CSV...')">
                                    <i class="bi bi-upload me-1"></i> Import from CSV
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Bulk TR-069 Configuration -->
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header bg-info text-white">
                            <i class="bi bi-broadcast me-2"></i>Bulk TR-069 Configuration
                        </div>
                        <div class="card-body">
                            <p class="text-muted small mb-3">
                                Push ACS URL to all authorized ONUs on an OLT. This configures ONUs to connect to your GenieACS server.
                            </p>
                            <form method="post" id="bulkTr069Form">
                                <input type="hidden" name="action" value="bulk_tr069_config">
                                
                                <div class="mb-3">
                                    <label class="form-label">Select OLT</label>
                                    <select name="olt_id" class="form-select" required id="tr069OltSelect">
                                        <option value="">-- Select OLT --</option>
                                        <?php foreach ($olts as $o): ?>
                                        <option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['name']) ?> (<?= $o['onu_count'] ?? 0 ?> ONUs)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">ACS URL (from VPN Gateway)</label>
                                    <?php
                                    require_once __DIR__ . '/../src/WireGuardService.php';
                                    $wgServiceBulk = new \App\WireGuardService($db);
                                    $defaultAcsUrl = $wgServiceBulk->getTR069AcsUrl();
                                    ?>
                                    <input type="url" name="acs_url" class="form-control" required readonly
                                           value="<?= htmlspecialchars($defaultAcsUrl) ?>">
                                    <div class="form-text">Uses VPN Gateway IP on port 7547. Configure in VPN Settings.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">TR-069 VLAN (optional)</label>
                                    <input type="number" name="tr069_vlan" class="form-control" placeholder="e.g., 100">
                                    <div class="form-text">Leave empty to use existing ONU VLAN configuration</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Periodic Inform Interval</label>
                                    <select name="periodic_interval" class="form-select">
                                        <option value="60">1 minute</option>
                                        <option value="300" selected>5 minutes</option>
                                        <option value="600">10 minutes</option>
                                        <option value="1800">30 minutes</option>
                                        <option value="3600">1 hour</option>
                                    </select>
                                </div>
                                
                                <div class="alert alert-warning small mb-3">
                                    <i class="bi bi-exclamation-triangle me-1"></i>
                                    <strong>Note:</strong> This will push commands to all authorized ONUs on the selected OLT. 
                                    ONUs will start connecting to your ACS within the periodic interval.
                                </div>
                                
                                <button type="submit" class="btn btn-info w-100" onclick="showLoading('Configuring TR-069 on all ONUs...')">
                                    <i class="bi bi-broadcast me-1"></i> Configure TR-069 on All ONUs
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php elseif ($settingsTab === 'onu_types'): ?>
            <?php
            $onuTypes = [];
            try {
                $stmt = $db->query("SELECT * FROM huawei_onu_types WHERE is_active = TRUE ORDER BY name");
                $onuTypes = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            } catch (Exception $e) {}
            ?>
            <div class="row">
                <div class="col-lg-8">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-router me-2"></i>ONU Types</h5>
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addOnuTypeModal">
                                <i class="bi bi-plus-lg me-1"></i>Add ONU Type
                            </button>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Name</th>
                                            <th>Model</th>
                                            <th>Ports</th>
                                            <th>Mode</th>
                                            <th>Capabilities</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($onuTypes)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">
                                                <i class="bi bi-info-circle me-1"></i>No ONU types defined. Add some ONU types to use during authorization.
                                            </td>
                                        </tr>
                                        <?php else: ?>
                                        <?php foreach ($onuTypes as $type): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($type['name']) ?></strong></td>
                                            <td><code><?= htmlspecialchars($type['model'] ?? '-') ?></code></td>
                                            <td>
                                                <span class="badge bg-primary"><?= $type['eth_ports'] ?> ETH</span>
                                                <?php if ($type['pots_ports'] > 0): ?>
                                                <span class="badge bg-secondary"><?= $type['pots_ports'] ?> POTS</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge <?= $type['default_mode'] === 'bridge' ? 'bg-info' : 'bg-success' ?>">
                                                    <?= ucfirst($type['default_mode']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($type['wifi_capable']): ?><span class="badge bg-purple" title="WiFi"><i class="bi bi-wifi"></i></span><?php endif; ?>
                                                <?php if ($type['tr069_capable']): ?><span class="badge bg-secondary" title="TR-069"><i class="bi bi-gear-wide-connected"></i></span><?php endif; ?>
                                                <?php if ($type['omci_capable']): ?><span class="badge bg-dark" title="OMCI"><i class="bi bi-cpu"></i></span><?php endif; ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" onclick="editOnuType(<?= htmlspecialchars(json_encode($type)) ?>)">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <form method="post" class="d-inline" onsubmit="return confirm('Delete this ONU type?')">
                                                    <input type="hidden" name="action" value="delete_onu_type">
                                                    <input type="hidden" name="id" value="<?= $type['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white">
                            <h6 class="mb-0"><i class="bi bi-lightbulb me-2"></i>About ONU Types</h6>
                        </div>
                        <div class="card-body">
                            <p class="small">Define ONU types to use during authorization. Each type specifies:</p>
                            <ul class="small">
                                <li><strong>Ports</strong> - Number of Ethernet and POTS ports</li>
                                <li><strong>Mode</strong> - Bridge or Router default configuration</li>
                                <li><strong>T-CONT/GEM</strong> - Traffic container and GEM port counts</li>
                                <li><strong>Profiles</strong> - Recommended line and service profiles</li>
                            </ul>
                            <p class="small text-muted mb-0">
                                Bridge mode pushes configuration via OMCI. Router mode allows TR-069 remote management.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal fade" id="addOnuTypeModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="post">
                            <input type="hidden" name="action" value="save_onu_type">
                            <input type="hidden" name="id" id="onuTypeId">
                            <div class="modal-header">
                                <h5 class="modal-title"><i class="bi bi-router me-2"></i>ONU Type</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-6 mb-3">
                                        <label class="form-label">Name <span class="text-danger">*</span></label>
                                        <input type="text" name="name" id="onuTypeName" class="form-control" required placeholder="e.g., Bridge ONU (1 ETH)">
                                    </div>
                                    <div class="col-6 mb-3">
                                        <label class="form-label">Model</label>
                                        <input type="text" name="model" id="onuTypeModel" class="form-control" placeholder="e.g., HG8010H">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-4 mb-3">
                                        <label class="form-label">ETH Ports</label>
                                        <input type="number" name="eth_ports" id="onuTypeEthPorts" class="form-control" value="1" min="0" max="8">
                                    </div>
                                    <div class="col-4 mb-3">
                                        <label class="form-label">POTS Ports</label>
                                        <input type="number" name="pots_ports" id="onuTypePotsports" class="form-control" value="0" min="0" max="4">
                                    </div>
                                    <div class="col-4 mb-3">
                                        <label class="form-label">Default Mode</label>
                                        <select name="default_mode" id="onuTypeDefaultMode" class="form-select">
                                            <option value="bridge">Bridge</option>
                                            <option value="router">Router</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-6 mb-3">
                                        <label class="form-label">T-CONT Count</label>
                                        <input type="number" name="tcont_count" id="onuTypeTcontCount" class="form-control" value="1" min="1" max="8">
                                    </div>
                                    <div class="col-6 mb-3">
                                        <label class="form-label">GEM Port Count</label>
                                        <input type="number" name="gemport_count" id="onuTypeGemportCount" class="form-control" value="1" min="1" max="32">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Capabilities</label>
                                    <div class="d-flex gap-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="wifi_capable" id="onuTypeWifi" value="1">
                                            <label class="form-check-label" for="onuTypeWifi">WiFi</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="omci_capable" id="onuTypeOmci" value="1" checked>
                                            <label class="form-check-label" for="onuTypeOmci">OMCI</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="tr069_capable" id="onuTypeTr069" value="1" checked>
                                            <label class="form-check-label" for="onuTypeTr069">TR-069</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea name="description" id="onuTypeDescription" class="form-control" rows="2"></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <script>
            function editOnuType(type) {
                document.getElementById('onuTypeId').value = type.id;
                document.getElementById('onuTypeName').value = type.name || '';
                document.getElementById('onuTypeModel').value = type.model || '';
                document.getElementById('onuTypeEthPorts').value = type.eth_ports || 1;
                document.getElementById('onuTypePotsports').value = type.pots_ports || 0;
                document.getElementById('onuTypeDefaultMode').value = type.default_mode || 'bridge';
                document.getElementById('onuTypeTcontCount').value = type.tcont_count || 1;
                document.getElementById('onuTypeGemportCount').value = type.gemport_count || 1;
                document.getElementById('onuTypeWifi').checked = type.wifi_capable;
                document.getElementById('onuTypeOmci').checked = type.omci_capable;
                document.getElementById('onuTypeTr069').checked = type.tr069_capable;
                document.getElementById('onuTypeDescription').value = type.description || '';
                new bootstrap.Modal(document.getElementById('addOnuTypeModal')).show();
            }
            </script>
            
            <?php elseif ($settingsTab === 'scripts'): ?>
            
            <script>
            function generateOLTSetupScript() {
                const ispName = document.getElementById('oltIspName').value.trim() || 'ISP';
                const mgmtVlan = document.getElementById('oltMgmtVlan').value || '100';
                const tr069Vlan = document.getElementById('oltTr069Vlan').value || '101';
                const dataVlan = document.getElementById('oltDataVlan').value || '69';
                const voiceVlan = document.getElementById('oltVoiceVlan').value || '';
                const lineProfileId = document.getElementById('oltLineProfileId').value || '10';
                const srvProfileId = document.getElementById('oltSrvProfileId').value || '10';
                const tr069Enable = document.getElementById('oltTr069Enable').checked;
                const acsUrl = document.getElementById('oltAcsUrl').value.trim();
                const downloadSpeed = document.getElementById('oltDownloadSpeed').value || '30';
                const uploadSpeed = document.getElementById('oltUploadSpeed').value || '15';
                const ontModel = document.getElementById('oltOntModel').value || 'bridge';
                
                let script = `# ================================================================\n`;
                script += `# FRESH OLT SETUP SCRIPT - ${ispName}\n`;
                script += `# Generated: ${new Date().toLocaleString()}\n`;
                script += `# ================================================================\n`;
                script += `# This script configures profiles and VLANs for a new MA5683T/MA5680T\n`;
                script += `# Copy each section and paste into OLT terminal\n`;
                script += `# ================================================================\n\n`;
                
                script += `# ================================================\n`;
                script += `# SECTION 1: VLAN CONFIGURATION\n`;
                script += `# ================================================\n`;
                script += `config\n\n`;
                
                script += `# Management VLAN (for OLT management traffic)\n`;
                script += `vlan ${mgmtVlan} smart\n`;
                script += `vlan desc ${mgmtVlan} Management_VLAN\n\n`;
                
                if (tr069Enable && tr069Vlan) {
                    script += `# TR-069 VLAN (for ONU remote management)\n`;
                    script += `vlan ${tr069Vlan} smart\n`;
                    script += `vlan desc ${tr069Vlan} TR069_ACS_VLAN\n\n`;
                }
                
                script += `# Data/Service VLAN (for customer internet traffic)\n`;
                script += `vlan ${dataVlan} smart\n`;
                script += `vlan desc ${dataVlan} Customer_Data_VLAN\n\n`;
                
                if (voiceVlan) {
                    script += `# Voice VLAN (for VoIP services)\n`;
                    script += `vlan ${voiceVlan} smart\n`;
                    script += `vlan desc ${voiceVlan} Voice_VoIP_VLAN\n\n`;
                }
                
                script += `# ================================================\n`;
                script += `# SECTION 2: DBA PROFILE (Bandwidth Allocation)\n`;
                script += `# ================================================\n`;
                script += `# DBA Profile controls upstream bandwidth\n`;
                const upBw = parseInt(uploadSpeed) * 1024;
                script += `dba-profile add profile-id ${lineProfileId} profile-name "${ispName}_${uploadSpeed}M_UP" type4 max ${upBw}\n\n`;
                
                script += `# ================================================\n`;
                script += `# SECTION 3: LINE PROFILE (T-CONT + GEM configuration)\n`;
                script += `# ================================================\n`;
                script += `ont-lineprofile gpon profile-id ${lineProfileId} profile-name "${ispName}_Line_${downloadSpeed}M"\n`;
                script += `  tcont 1 dba-profile-id ${lineProfileId}\n`;
                script += `  gem add 1 eth tcont 1\n`;
                script += `  gem mapping 1 0 vlan ${dataVlan}\n`;
                if (tr069Enable && tr069Vlan) {
                    script += `  gem add 2 eth tcont 1\n`;
                    script += `  gem mapping 2 1 vlan ${tr069Vlan}\n`;
                }
                script += `  commit\n`;
                script += `  quit\n\n`;
                
                script += `# ================================================\n`;
                script += `# SECTION 4: SERVICE PROFILE (ONU ports configuration)\n`;
                script += `# ================================================\n`;
                script += `ont-srvprofile gpon profile-id ${srvProfileId} profile-name "${ispName}_Srv_${ontModel}"\n`;
                if (ontModel === 'router') {
                    script += `  ont-port pots 2 eth 4\n`;
                } else {
                    script += `  ont-port eth 1\n`;
                }
                script += `  port vlan eth 1 ${dataVlan}\n`;
                script += `  commit\n`;
                script += `  quit\n\n`;
                
                if (tr069Enable && acsUrl) {
                    script += `# ================================================\n`;
                    script += `# SECTION 5: TR-069 ACS CONFIGURATION\n`;
                    script += `# ================================================\n`;
                    script += `# Configure TR-069 server for remote ONU management\n`;
                    script += `tr069-server-config ${srvProfileId} profile-name "${ispName}_TR069"\n`;
                    script += `  acs-url ${acsUrl}\n`;
                    script += `  acs-username ${ispName.toLowerCase()}\n`;
                    script += `  acs-password ${ispName.toLowerCase()}123\n`;
                    script += `  periodic-inform enable\n`;
                    script += `  periodic-inform-interval 3600\n`;
                    script += `  commit\n`;
                    script += `  quit\n\n`;
                }
                
                script += `# ================================================\n`;
                script += `# SECTION 6: TRAFFIC TABLE (QoS / Speed limiting)\n`;
                script += `# ================================================\n`;
                const downBw = parseInt(downloadSpeed) * 1024;
                script += `# Traffic table for ${downloadSpeed}Mbps downstream\n`;
                script += `traffic table ip index ${lineProfileId} name "${ispName}_${downloadSpeed}M" cir ${downBw} priority 0 priority-policy local-Setting\n\n`;
                
                script += `# ================================================\n`;
                script += `# SECTION 7: UPLINK PORT VLAN (Connect to router)\n`;
                script += `# ================================================\n`;
                script += `# Configure uplink port (adjust slot/port as needed)\n`;
                script += `interface eth 0/20/0\n`;
                script += `  port vlan ${mgmtVlan} ${dataVlan}`;
                if (tr069Enable && tr069Vlan) {
                    script += ` ${tr069Vlan}`;
                }
                if (voiceVlan) {
                    script += ` ${voiceVlan}`;
                }
                script += ` 0\n`;
                script += `quit\n\n`;
                
                script += `# ================================================\n`;
                script += `# SETUP COMPLETE!\n`;
                script += `# ================================================\n`;
                script += `# OLT infrastructure is now configured.\n`;
                script += `# \n`;
                script += `# Next steps:\n`;
                script += `# 1. Save configuration: save\n`;
                script += `# 2. Connect ONUs to PON ports\n`;
                script += `# 3. Use OMS web interface to discover and authorize ONUs\n`;
                script += `#    (Navigate to: OMS > Pending Authorization)\n`;
                script += `# ================================================\n`;
                
                document.getElementById('oltSetupScript').textContent = script;
            }

            function copyOLTSetupScript() {
                const script = document.getElementById('oltSetupScript').textContent;
                navigator.clipboard.writeText(script).then(() => {
                    alert('OLT setup script copied to clipboard!');
                }).catch(() => {
                    const textarea = document.createElement('textarea');
                    textarea.value = script;
                    document.body.appendChild(textarea);
                    textarea.select();
                    document.execCommand('copy');
                    document.body.removeChild(textarea);
                    alert('OLT setup script copied to clipboard!');
                });
            }
            </script>

            <div class="row">
                <div class="col-lg-5">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="bi bi-magic me-2"></i>Fresh OLT Configuration Wizard</h5>
                        </div>
                        <div class="card-body">
                            <form id="oltSetupForm">
                                <div class="mb-3">
                                    <label class="form-label">ISP Name</label>
                                    <input type="text" id="oltIspName" class="form-control" placeholder="e.g., MyISP" value="ISP">
                                    <div class="form-text">Used in profile names</div>
                                </div>
                                
                                <hr class="my-3">
                                <h6 class="text-muted mb-3"><i class="bi bi-ethernet me-2"></i>VLAN Configuration</h6>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Management VLAN</label>
                                        <input type="number" id="oltMgmtVlan" class="form-control" value="100" min="1" max="4094">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Data/Internet VLAN</label>
                                        <input type="number" id="oltDataVlan" class="form-control" value="69" min="1" max="4094">
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">TR-069 VLAN</label>
                                        <input type="number" id="oltTr069Vlan" class="form-control" value="101" min="1" max="4094">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Voice VLAN (optional)</label>
                                        <input type="number" id="oltVoiceVlan" class="form-control" placeholder="e.g., 200" min="1" max="4094">
                                    </div>
                                </div>
                                
                                <hr class="my-3">
                                <h6 class="text-muted mb-3"><i class="bi bi-sliders me-2"></i>Profile Configuration</h6>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Line Profile ID</label>
                                        <input type="number" id="oltLineProfileId" class="form-control" value="10" min="1">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Service Profile ID</label>
                                        <input type="number" id="oltSrvProfileId" class="form-control" value="10" min="1">
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Download Speed (Mbps)</label>
                                        <input type="number" id="oltDownloadSpeed" class="form-control" value="30" min="1">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Upload Speed (Mbps)</label>
                                        <input type="number" id="oltUploadSpeed" class="form-control" value="15" min="1">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">ONU Model Type</label>
                                    <select id="oltOntModel" class="form-select">
                                        <option value="bridge">Bridge Mode (1 ETH port)</option>
                                        <option value="router">Router Mode (4 ETH + 2 POTS)</option>
                                    </select>
                                </div>
                                
                                <hr class="my-3">
                                <h6 class="text-muted mb-3"><i class="bi bi-gear-wide-connected me-2"></i>TR-069 Configuration</h6>
                                
                                <div class="form-check mb-3">
                                    <input type="checkbox" class="form-check-input" id="oltTr069Enable" checked>
                                    <label class="form-check-label" for="oltTr069Enable">Enable TR-069 / Remote Management</label>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">ACS Server URL</label>
                                    <input type="text" id="oltAcsUrl" class="form-control" placeholder="http://acs.example.com:7547">
                                    <div class="form-text">GenieACS or other TR-069 server</div>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="button" class="btn btn-success btn-lg" onclick="generateOLTSetupScript()">
                                        <i class="bi bi-magic me-2"></i>Generate OLT Setup Script
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-7">
                    <div class="card shadow-sm">
                        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-terminal me-2"></i>OLT Setup Commands</h5>
                            <button type="button" class="btn btn-sm btn-outline-light" onclick="copyOLTSetupScript()">
                                <i class="bi bi-clipboard me-1"></i> Copy All
                            </button>
                        </div>
                        <div class="card-body p-0">
                            <pre class="mb-0 p-3" style="background: #1e1e1e; color: #d4d4d4; font-family: 'Consolas', 'Monaco', monospace; font-size: 0.85rem; max-height: 700px; overflow-y: auto; border-radius: 0 0 0.375rem 0.375rem;"><code id="oltSetupScript"># Fresh OLT Setup Wizard
# Fill in the form and click "Generate OLT Setup Script"
#
# This wizard generates a complete configuration including:
# - VLAN setup (Management, Data, TR-069, Voice)
# - DBA Profile (upstream bandwidth allocation)
# - Line Profile (T-CONT + GEM port mapping)
# - Service Profile (ONU port configuration)
# - TR-069 ACS configuration (optional)
# - Traffic tables for QoS
# - Uplink port VLAN configuration
#
# The generated script is ready to paste into your MA5683T/MA5680T terminal.</code></pre>
                        </div>
                    </div>
                    
                    <div class="card shadow-sm mt-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="bi bi-lightbulb me-2"></i>Setup Tips</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Before Running Script:</h6>
                                    <ul class="small">
                                        <li>Backup current config: <code>save</code></li>
                                        <li>Check existing profiles: <code>display ont-lineprofile gpon all</code></li>
                                        <li>Check VLANs: <code>display vlan all</code></li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <h6>After Running Script:</h6>
                                    <ul class="small">
                                        <li>Save configuration: <code>save</code></li>
                                        <li>Verify profiles: <code>display ont-srvprofile gpon all</code></li>
                                        <li>Test with one ONU before mass deployment</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php endif; ?>
            
            <?php elseif ($view === 'olt_detail' && $oltId): ?>
            <?php
            $currentOlt = $huaweiOLT->getOLT($oltId);
            $detailTab = $_GET['tab'] ?? 'overview';
            
            $cachedBoards = $huaweiOLT->getCachedBoards($oltId);
            $cachedVLANs = $huaweiOLT->getCachedVLANs($oltId);
            $cachedPorts = $huaweiOLT->getCachedPONPorts($oltId);
            $cachedUplinks = $huaweiOLT->getCachedUplinks($oltId);
            ?>
            <?php if ($currentOlt): ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <a href="?page=huawei-olt&view=olts" class="btn btn-sm btn-outline-secondary me-2">
                        <i class="bi bi-arrow-left"></i> Back
                    </a>
                    <span class="fs-4 fw-bold"><?= htmlspecialchars($currentOlt['name']) ?></span>
                    <span class="text-muted ms-2">(<?= htmlspecialchars($currentOlt['ip_address']) ?>)</span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-<?= $currentOlt['is_active'] ? 'success' : 'secondary' ?>">
                        <?= $currentOlt['is_active'] ? 'Active' : 'Inactive' ?>
                    </span>
                    <form method="post" class="d-inline" onsubmit="showLoading('Running full OLT sync... This may take a few minutes.')">
                        <input type="hidden" name="action" value="sync_all_olt">
                        <input type="hidden" name="olt_id" value="<?= $oltId ?>">
                        <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('Sync all data from OLT? This may take a few minutes.')">
                            <i class="bi bi-arrow-repeat me-1"></i> Sync All from OLT
                        </button>
                    </form>
                </div>
            </div>
            
            <ul class="nav nav-tabs mb-4 flex-nowrap" style="overflow-x: auto;">
                <li class="nav-item">
                    <a class="nav-link <?= $detailTab === 'overview' ? 'active' : '' ?>" href="?page=huawei-olt&view=olt_detail&olt_id=<?= $oltId ?>&tab=overview">
                        <i class="bi bi-info-circle me-1"></i> Overview
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $detailTab === 'boards' ? 'active' : '' ?>" href="?page=huawei-olt&view=olt_detail&olt_id=<?= $oltId ?>&tab=boards">
                        <i class="bi bi-cpu me-1"></i> Cards
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $detailTab === 'ports' ? 'active' : '' ?>" href="?page=huawei-olt&view=olt_detail&olt_id=<?= $oltId ?>&tab=ports">
                        <i class="bi bi-ethernet me-1"></i> PON Ports
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $detailTab === 'uplinks' ? 'active' : '' ?>" href="?page=huawei-olt&view=olt_detail&olt_id=<?= $oltId ?>&tab=uplinks">
                        <i class="bi bi-arrow-up-circle me-1"></i> Uplinks
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $detailTab === 'vlans' ? 'active' : '' ?>" href="?page=huawei-olt&view=olt_detail&olt_id=<?= $oltId ?>&tab=vlans">
                        <i class="bi bi-diagram-2 me-1"></i> VLANs
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $detailTab === 'advanced' ? 'active' : '' ?>" href="?page=huawei-olt&view=olt_detail&olt_id=<?= $oltId ?>&tab=advanced">
                        <i class="bi bi-gear me-1"></i> Advanced
                    </a>
                </li>
            </ul>
            
            <?php if ($detailTab === 'overview'): ?>
            <?php $onusByPort = $huaweiOLT->getONUsBySlotPort($oltId); ?>
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-primary text-white">
                            <i class="bi bi-hdd-rack me-2"></i>Device Information
                        </div>
                        <div class="card-body">
                            <table class="table table-sm mb-0">
                                <tr><td class="text-muted" width="40%">Name</td><td><strong><?= htmlspecialchars($currentOlt['name']) ?></strong></td></tr>
                                <tr><td class="text-muted">IP Address</td><td><code><?= htmlspecialchars($currentOlt['ip_address']) ?></code></td></tr>
                                <tr><td class="text-muted">Model</td><td><?= htmlspecialchars(($currentOlt['hardware_model'] ?? '') ?: ($currentOlt['model'] ?? '-')) ?></td></tr>
                                <tr><td class="text-muted">Software</td><td><small><?= htmlspecialchars($currentOlt['software_version'] ?? '-') ?></small></td></tr>
                                <tr><td class="text-muted">Firmware</td><td><small><?= htmlspecialchars($currentOlt['firmware_version'] ?? '-') ?></small></td></tr>
                                <tr><td class="text-muted">Uptime</td><td><?= htmlspecialchars($currentOlt['uptime'] ?: '-') ?></td></tr>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-success text-white">
                            <i class="bi bi-bar-chart me-2"></i>Inventory
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-6 mb-3">
                                    <div class="fs-3 fw-bold text-primary"><?= count($cachedBoards) ?></div>
                                    <div class="small text-muted">Cards</div>
                                </div>
                                <div class="col-6 mb-3">
                                    <?php 
                                    $totalOnus = array_sum(array_column($onusByPort, 'count'));
                                    $onlineOnus = array_sum(array_column($onusByPort, 'online'));
                                    ?>
                                    <div class="fs-3 fw-bold text-success"><?= $totalOnus ?></div>
                                    <div class="small text-muted">ONUs (<?= $onlineOnus ?> online)</div>
                                </div>
                                <div class="col-6">
                                    <div class="fs-3 fw-bold text-info"><?= count($cachedVLANs) ?></div>
                                    <div class="small text-muted">VLANs</div>
                                </div>
                                <div class="col-6">
                                    <div class="fs-3 fw-bold text-warning"><?= count($cachedUplinks) ?></div>
                                    <div class="small text-muted">Uplinks</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-info text-white">
                            <i class="bi bi-clock-history me-2"></i>Last Sync
                        </div>
                        <div class="card-body">
                            <table class="table table-sm mb-0">
                                <tr>
                                    <td class="text-muted">System</td>
                                    <td><?= !empty($currentOlt['system_synced_at']) ? date('M j, H:i', strtotime($currentOlt['system_synced_at'])) : '<span class="text-warning">Never</span>' ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Boards</td>
                                    <td><?= !empty($currentOlt['boards_synced_at']) ? date('M j, H:i', strtotime($currentOlt['boards_synced_at'])) : '<span class="text-warning">Never</span>' ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">VLANs</td>
                                    <td><?= !empty($currentOlt['vlans_synced_at']) ? date('M j, H:i', strtotime($currentOlt['vlans_synced_at'])) : '<span class="text-warning">Never</span>' ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Ports</td>
                                    <td><?= !empty($currentOlt['ports_synced_at']) ? date('M j, H:i', strtotime($currentOlt['ports_synced_at'])) : '<span class="text-warning">Never</span>' ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- SNMP Monitoring Card -->
            <div class="row g-4 mb-4">
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-broadcast me-2"></i>SNMP Monitoring</span>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="action" value="refresh_snmp_info">
                                <input type="hidden" name="olt_id" value="<?= $oltId ?>">
                                <button type="submit" class="btn btn-sm btn-light">
                                    <i class="bi bi-arrow-repeat me-1"></i> Refresh SNMP
                                </button>
                            </form>
                        </div>
                        <div class="card-body">
                            <?php 
                            $snmpStatus = $currentOlt['snmp_status'] ?? 'unknown';
                            $snmpBadgeClass = match($snmpStatus) {
                                'online' => 'bg-success',
                                'simulated' => 'bg-info',
                                'offline' => 'bg-danger',
                                default => 'bg-secondary'
                            };
                            ?>
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-sm mb-0">
                                        <tr>
                                            <td class="text-muted" width="35%">SNMP Status</td>
                                            <td>
                                                <span class="badge <?= $snmpBadgeClass ?>"><?= ucfirst($snmpStatus) ?></span>
                                                <?php if ($snmpStatus === 'simulated'): ?>
                                                <small class="text-muted ms-2">(Demo mode - no real OLT)</small>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">System Name</td>
                                            <td><strong><?= htmlspecialchars($currentOlt['snmp_sys_name'] ?? '-') ?></strong></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Description</td>
                                            <td><small><?= htmlspecialchars($currentOlt['snmp_sys_descr'] ?? '-') ?></small></td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-sm mb-0">
                                        <tr>
                                            <td class="text-muted" width="35%">System Uptime</td>
                                            <td><?= htmlspecialchars($currentOlt['snmp_sys_uptime'] ?? '-') ?></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Location</td>
                                            <td><?= htmlspecialchars($currentOlt['snmp_sys_location'] ?? '-') ?></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Last SNMP Poll</td>
                                            <td><?= !empty($currentOlt['snmp_last_poll']) ? date('M j, H:i:s', strtotime($currentOlt['snmp_last_poll'])) : '<span class="text-warning">Never</span>' ?></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                            <div class="mt-3 small text-muted">
                                <i class="bi bi-info-circle me-1"></i>
                                SNMP Community: <code><?= htmlspecialchars($currentOlt['snmp_community'] ?? 'public') ?></code> | 
                                Version: <?= htmlspecialchars($currentOlt['snmp_version'] ?? 'v2c') ?> | 
                                Port: <?= $currentOlt['snmp_port'] ?? 161 ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card shadow-sm">
                <div class="card-header bg-dark text-white">
                    <i class="bi bi-grid-3x3 me-2"></i>Chassis Layout - Board/Slot Map
                </div>
                <div class="card-body">
                    <?php if (empty($cachedBoards)): ?>
                    <div class="alert alert-info mb-0">
                        <i class="bi bi-info-circle me-2"></i>No board data cached. Click "Sync All from OLT" to fetch chassis information.
                    </div>
                    <?php else: ?>
                    <div class="row g-2">
                        <?php 
                        $boardsBySlot = [];
                        foreach ($cachedBoards as $board) {
                            $boardsBySlot[$board['slot']] = $board;
                        }
                        
                        $onuCountBySlot = [];
                        foreach ($onusByPort as $p) {
                            $slot = $p['slot'];
                            if (!isset($onuCountBySlot[$slot])) {
                                $onuCountBySlot[$slot] = ['count' => 0, 'online' => 0];
                            }
                            $onuCountBySlot[$slot]['count'] += $p['count'];
                            $onuCountBySlot[$slot]['online'] += $p['online'];
                        }
                        
                        // Only show slots that have boards (detected from OLT)
                        $detectedSlots = array_keys($boardsBySlot);
                        sort($detectedSlots, SORT_NUMERIC);
                        
                        foreach ($detectedSlots as $slot): 
                            $board = $boardsBySlot[$slot];
                            $boardType = $huaweiOLT->getBoardTypeCategory($board['board_name'] ?? '');
                            $slotOnus = $onuCountBySlot[$slot] ?? ['count' => 0, 'online' => 0];
                            
                            $bgColor = 'bg-light border';
                            $textColor = 'text-muted';
                            switch ($boardType) {
                                case 'gpon': $bgColor = 'bg-success bg-opacity-25 border-success'; $textColor = 'text-success'; break;
                                case 'epon': $bgColor = 'bg-info bg-opacity-25 border-info'; $textColor = 'text-info'; break;
                                case 'uplink': $bgColor = 'bg-warning bg-opacity-25 border-warning'; $textColor = 'text-warning'; break;
                                case 'control': $bgColor = 'bg-primary bg-opacity-25 border-primary'; $textColor = 'text-primary'; break;
                                case 'power': $bgColor = 'bg-danger bg-opacity-25 border-danger'; $textColor = 'text-danger'; break;
                                default: $bgColor = 'bg-secondary bg-opacity-25 border-secondary'; $textColor = 'text-secondary';
                            }
                        ?>
                        <div class="col-6 col-md-3 col-lg-2">
                            <div class="card <?= $bgColor ?> h-100" style="min-height: 100px;">
                                <div class="card-body p-2 text-center">
                                    <div class="small text-muted">Slot <?= $slot ?></div>
                                    <div class="fw-bold <?= $textColor ?>" style="font-size: 0.75rem;"><?= htmlspecialchars($board['board_name']) ?></div>
                                    <div class="small">
                                        <span class="badge bg-<?= strtolower($board['status'] ?? '') === 'normal' ? 'success' : 'secondary' ?>" style="font-size: 0.65rem;">
                                            <?= htmlspecialchars($board['status'] ?? '-') ?>
                                        </span>
                                    </div>
                                    <?php if (($boardType === 'gpon' || $boardType === 'epon') && $slotOnus['count'] > 0): ?>
                                    <div class="mt-1 small">
                                        <i class="bi bi-diagram-3"></i> <?= $slotOnus['count'] ?> ONUs
                                        <span class="text-success">(<?= $slotOnus['online'] ?> on)</span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="mt-3 d-flex flex-wrap gap-3 justify-content-center">
                        <span class="badge bg-success bg-opacity-25 text-success border border-success px-3">GPON</span>
                        <span class="badge bg-info bg-opacity-25 text-info border border-info px-3">EPON</span>
                        <span class="badge bg-warning bg-opacity-25 text-warning border border-warning px-3">Uplink</span>
                        <span class="badge bg-primary bg-opacity-25 text-primary border border-primary px-3">Control</span>
                        <span class="badge bg-danger bg-opacity-25 text-danger border border-danger px-3">Power</span>
                        <span class="badge bg-light text-muted border px-3">Empty</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php elseif ($detailTab === 'boards'): ?>
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0 d-inline"><i class="bi bi-cpu me-2"></i>Cards</h5>
                        <?php if ($currentOlt['boards_synced_at']): ?>
                        <small class="text-muted ms-2">Last sync: <?= date('M j, H:i', strtotime($currentOlt['boards_synced_at'])) ?></small>
                        <?php endif; ?>
                    </div>
                    <form method="post" class="d-inline">
                        <input type="hidden" name="action" value="sync_boards">
                        <input type="hidden" name="olt_id" value="<?= $oltId ?>">
                        <button type="submit" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-arrow-clockwise me-1"></i> Sync from OLT
                        </button>
                    </form>
                </div>
                <div class="card-body">
                    <?php if (!empty($cachedBoards)): 
                        $onusBySlot = [];
                        foreach ($huaweiOLT->getONUsBySlotPort($oltId) as $p) {
                            $slot = $p['slot'];
                            if (!isset($onusBySlot[$slot])) $onusBySlot[$slot] = ['count' => 0, 'online' => 0];
                            $onusBySlot[$slot]['count'] += $p['count'];
                            $onusBySlot[$slot]['online'] += $p['online'];
                        }
                    ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Slot</th>
                                    <th>Board Name</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>ONUs</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cachedBoards as $board): 
                                    $boardType = $huaweiOLT->getBoardTypeCategory($board['board_name'] ?? '');
                                    $slotOnus = $onusBySlot[$board['slot']] ?? ['count' => 0, 'online' => 0];
                                    
                                    $typeColors = [
                                        'gpon' => 'success',
                                        'epon' => 'info',
                                        'uplink' => 'warning',
                                        'control' => 'primary',
                                        'power' => 'danger',
                                        'other' => 'secondary'
                                    ];
                                    $typeColor = $typeColors[$boardType] ?? 'secondary';
                                ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($board['slot']) ?></strong></td>
                                    <td><code><?= htmlspecialchars($board['board_name']) ?></code></td>
                                    <td>
                                        <span class="badge bg-<?= $typeColor ?>"><?= strtoupper($boardType) ?></span>
                                    </td>
                                    <td>
                                        <?php
                                        $status = strtolower($board['status'] ?? '');
                                        $statusClass = 'secondary';
                                        if (strpos($status, 'normal') !== false) $statusClass = 'success';
                                        elseif (strpos($status, 'active') !== false) $statusClass = 'primary';
                                        elseif (strpos($status, 'standby') !== false) $statusClass = 'info';
                                        elseif (strpos($status, 'failed') !== false) $statusClass = 'danger';
                                        ?>
                                        <span class="badge bg-<?= $statusClass ?>">
                                            <?= htmlspecialchars($board['status'] ?? '-') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($boardType === 'gpon' || $boardType === 'epon'): ?>
                                            <span class="badge bg-light text-dark border">
                                                <?= $slotOnus['count'] ?> <span class="text-success">(<?= $slotOnus['online'] ?> online)</span>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info mb-0">
                        <i class="bi bi-info-circle me-2"></i>No cached data. Click "Sync from OLT" to fetch board information.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php elseif ($detailTab === 'vlans'): ?>
            <div class="row">
                <div class="col-lg-8">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-0 d-inline"><i class="bi bi-diagram-2 me-2"></i>VLANs</h5>
                                <?php if ($currentOlt['vlans_synced_at']): ?>
                                <small class="text-muted ms-2">Last sync: <?= date('M j, H:i', strtotime($currentOlt['vlans_synced_at'])) ?></small>
                                <?php endif; ?>
                            </div>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="action" value="sync_vlans">
                                <input type="hidden" name="olt_id" value="<?= $oltId ?>">
                                <button type="submit" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-arrow-clockwise me-1"></i> Sync from OLT
                                </button>
                            </form>
                        </div>
                        <div class="card-body p-0">
                            <?php if (!empty($cachedVLANs)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>VLAN ID</th>
                                            <th>Type</th>
                                            <th>Features</th>
                                            <th>Description</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($cachedVLANs as $vlan): ?>
                                        <tr>
                                            <td><strong><?= $vlan['vlan_id'] ?></strong></td>
                                            <td><span class="badge bg-secondary"><?= htmlspecialchars($vlan['vlan_type'] ?? 'smart') ?></span></td>
                                            <td class="text-nowrap">
                                                <?php if (!empty($vlan['is_multicast'])): ?>
                                                    <span class="badge bg-info" title="Multicast (IPTV)"><i class="bi bi-broadcast"></i></span>
                                                <?php endif; ?>
                                                <?php if (!empty($vlan['is_voip'])): ?>
                                                    <span class="badge bg-success" title="VoIP/Management"><i class="bi bi-telephone"></i></span>
                                                <?php endif; ?>
                                                <?php if (!empty($vlan['is_tr069'])): ?>
                                                    <span class="badge bg-purple" title="TR-069 Management VLAN" style="background-color:#6f42c1"><i class="bi bi-gear-wide-connected"></i> TR-069</span>
                                                <?php endif; ?>
                                                <?php if (!empty($vlan['dhcp_snooping'])): ?>
                                                    <span class="badge bg-warning text-dark" title="DHCP Snooping"><i class="bi bi-shield-check"></i></span>
                                                <?php endif; ?>
                                                <?php if (!empty($vlan['lan_to_lan'])): ?>
                                                    <span class="badge bg-primary" title="LAN-to-LAN"><i class="bi bi-arrow-left-right"></i></span>
                                                <?php endif; ?>
                                                <?php if (empty($vlan['is_multicast']) && empty($vlan['is_voip']) && empty($vlan['is_tr069']) && empty($vlan['dhcp_snooping']) && empty($vlan['lan_to_lan'])): ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($vlan['description'])): ?>
                                                    <?= htmlspecialchars($vlan['description']) ?>
                                                <?php else: ?>
                                                    <span class="text-muted fst-italic">No description</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-nowrap">
                                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editVlanModal<?= $vlan['vlan_id'] ?>" title="Edit Description">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <form method="post" class="d-inline" onsubmit="return confirm('Delete VLAN <?= $vlan['vlan_id'] ?>? This cannot be undone.')">
                                                    <input type="hidden" name="action" value="delete_vlan">
                                                    <input type="hidden" name="olt_id" value="<?= $oltId ?>">
                                                    <input type="hidden" name="vlan_id" value="<?= $vlan['vlan_id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete VLAN">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                        
                                        <div class="modal fade" id="editVlanModal<?= $vlan['vlan_id'] ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h6 class="modal-title">Edit VLAN <?= $vlan['vlan_id'] ?></h6>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="post">
                                                        <div class="modal-body">
                                                            <input type="hidden" name="action" value="update_vlan_features">
                                                            <input type="hidden" name="olt_id" value="<?= $oltId ?>">
                                                            <input type="hidden" name="vlan_id" value="<?= $vlan['vlan_id'] ?>">
                                                            <div class="mb-3">
                                                                <label class="form-label">Description</label>
                                                                <input type="text" name="description" class="form-control" 
                                                                       value="<?= htmlspecialchars($vlan['description'] ?? '') ?>" 
                                                                       placeholder="Enter description" maxlength="32">
                                                                <small class="text-muted">Max 32 characters, alphanumeric only</small>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label d-block">VLAN Features</label>
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="checkbox" name="is_multicast" id="editMulticast<?= $vlan['vlan_id'] ?>" value="1" <?= !empty($vlan['is_multicast']) ? 'checked' : '' ?>>
                                                                    <label class="form-check-label" for="editMulticast<?= $vlan['vlan_id'] ?>">
                                                                        <i class="bi bi-broadcast me-1 text-info"></i>Multicast VLAN (IPTV)
                                                                    </label>
                                                                </div>
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="checkbox" name="is_voip" id="editVoip<?= $vlan['vlan_id'] ?>" value="1" <?= !empty($vlan['is_voip']) ? 'checked' : '' ?>>
                                                                    <label class="form-check-label" for="editVoip<?= $vlan['vlan_id'] ?>">
                                                                        <i class="bi bi-telephone me-1 text-success"></i>Management / VoIP VLAN
                                                                    </label>
                                                                </div>
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="checkbox" name="is_tr069" id="editTr069<?= $vlan['vlan_id'] ?>" value="1" <?= !empty($vlan['is_tr069']) ? 'checked' : '' ?>>
                                                                    <label class="form-check-label" for="editTr069<?= $vlan['vlan_id'] ?>">
                                                                        <i class="bi bi-gear me-1 text-warning"></i>TR-069 Management VLAN
                                                                    </label>
                                                                </div>
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="checkbox" name="dhcp_snooping" id="editDhcp<?= $vlan['vlan_id'] ?>" value="1" <?= !empty($vlan['dhcp_snooping']) ? 'checked' : '' ?>>
                                                                    <label class="form-check-label" for="editDhcp<?= $vlan['vlan_id'] ?>">
                                                                        <i class="bi bi-shield-check me-1 text-primary"></i>DHCP Snooping
                                                                    </label>
                                                                </div>
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="checkbox" name="lan_to_lan" id="editL2L<?= $vlan['vlan_id'] ?>" value="1" <?= !empty($vlan['lan_to_lan']) ? 'checked' : '' ?>>
                                                                    <label class="form-check-label" for="editL2L<?= $vlan['vlan_id'] ?>">
                                                                        <i class="bi bi-arrow-left-right me-1 text-secondary"></i>LAN-to-LAN (ONU direct communication)
                                                                    </label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-primary">Save</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="p-3">
                                <div class="alert alert-info mb-0">
                                    <i class="bi bi-info-circle me-2"></i>No cached data. Click "Sync from OLT" to fetch VLANs.
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white">
                            <h6 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Create VLAN</h6>
                        </div>
                        <div class="card-body">
                            <form method="post">
                                <input type="hidden" name="action" value="create_vlan">
                                <input type="hidden" name="olt_id" value="<?= $oltId ?>">
                                
                                <div class="mb-3">
                                    <label class="form-label">VLAN ID</label>
                                    <input type="number" name="vlan_id" class="form-control" min="1" max="4094" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Type</label>
                                    <select name="vlan_type" class="form-select">
                                        <option value="smart">Smart</option>
                                        <option value="common">Common</option>
                                        <option value="mux">MUX</option>
                                        <option value="standard">Standard</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <input type="text" name="description" class="form-control" placeholder="Optional">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label d-block">VLAN Features</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="is_multicast" id="vlanMulticast" value="1">
                                        <label class="form-check-label" for="vlanMulticast">
                                            <i class="bi bi-broadcast me-1 text-info"></i>Multicast VLAN (IPTV)
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="is_voip" id="vlanVoip" value="1">
                                        <label class="form-check-label" for="vlanVoip">
                                            <i class="bi bi-telephone me-1 text-success"></i>Management / VoIP VLAN
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="is_tr069" id="vlanTr069" value="1">
                                        <label class="form-check-label" for="vlanTr069">
                                            <i class="bi bi-gear-wide-connected me-1 text-purple"></i>TR-069 Management VLAN
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="dhcp_snooping" id="vlanDhcp" value="1">
                                        <label class="form-check-label" for="vlanDhcp">
                                            <i class="bi bi-shield-check me-1 text-warning"></i>DHCP Snooping
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="lan_to_lan" id="vlanL2L" value="1">
                                        <label class="form-check-label" for="vlanL2L">
                                            <i class="bi bi-arrow-left-right me-1 text-primary"></i>LAN-to-LAN (ONU direct communication)
                                        </label>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-plus-lg me-1"></i> Create VLAN
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php elseif ($detailTab === 'ports'): ?>
            <div class="row">
                <div class="col-lg-9">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-0 d-inline"><i class="bi bi-ethernet me-2"></i>PON Ports</h5>
                                <?php if (!empty($currentOlt['ports_synced_at'])): ?>
                                <small class="text-muted ms-2">Last sync: <?= date('M j, H:i', strtotime($currentOlt['ports_synced_at'])) ?></small>
                                <?php endif; ?>
                            </div>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="action" value="sync_ports">
                                <input type="hidden" name="olt_id" value="<?= $oltId ?>">
                                <button type="submit" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-arrow-clockwise me-1"></i> Sync from OLT
                                </button>
                            </form>
                        </div>
                        <div class="card-body p-0">
                            <?php if (!empty($cachedPorts)): 
                                // Group ports by slot
                                $portsBySlot = [];
                                foreach ($cachedPorts as $port) {
                                    $portName = $port['port_name'];
                                    if (preg_match('/(\d+)\/(\d+)\/(\d+)/', $portName, $m)) {
                                        $slot = (int)$m[2];
                                        $portsBySlot[$slot][] = $port;
                                    } else {
                                        $portsBySlot[0][] = $port;
                                    }
                                }
                                ksort($portsBySlot);
                            ?>
                            <div class="accordion" id="ponSlotsAccordion">
                                <?php foreach ($portsBySlot as $slotNum => $slotPorts): ?>
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button <?= $slotNum > 1 ? 'collapsed' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#slot<?= $slotNum ?>">
                                            <i class="bi bi-cpu me-2"></i> Slot <?= $slotNum ?> 
                                            <span class="badge bg-secondary ms-2"><?= count($slotPorts) ?> ports</span>
                                        </button>
                                    </h2>
                                    <div id="slot<?= $slotNum ?>" class="accordion-collapse collapse <?= $slotNum <= 1 ? 'show' : '' ?>" data-bs-parent="#ponSlotsAccordion">
                                        <div class="accordion-body p-0">
                                            <div class="table-responsive">
                                                <table class="table table-sm table-hover mb-0">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th style="width: 80px;">Port</th>
                                                            <th style="width: 70px;">Status</th>
                                                            <th style="width: 60px;">ONUs</th>
                                                            <th style="width: 100px;">Default VLAN</th>
                                                            <th>Description</th>
                                                            <th style="width: 140px;" class="text-end">Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($slotPorts as $port): 
                                                            $status = strtolower($port['oper_status'] ?? '');
                                                            $isUp = in_array($status, ['up', 'online', 'normal', 'enable']);
                                                            $adminEnabled = strtolower($port['admin_status'] ?? '') === 'enable';
                                                            $portId = str_replace('/', '_', $port['port_name']);
                                                        ?>
                                                        <tr>
                                                            <td>
                                                                <strong><?= htmlspecialchars($port['port_name']) ?></strong>
                                                            </td>
                                                            <td>
                                                                <span class="badge bg-<?= $isUp ? 'success' : 'secondary' ?>">
                                                                    <?= $isUp ? 'Up' : 'Down' ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <a href="?page=huawei-olt&view=onus&olt_id=<?= $oltId ?>&port=<?= urlencode($port['port_name']) ?>" class="text-decoration-none">
                                                                    <?= $port['onu_count'] ?? 0 ?>
                                                                </a>
                                                            </td>
                                                            <td>
                                                                <?php if (!empty($port['default_vlan'])): ?>
                                                                <span class="badge bg-primary"><?= $port['default_vlan'] ?></span>
                                                                <?php else: ?>
                                                                <span class="text-muted">-</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <input type="text" class="form-control form-control-sm border-0 bg-transparent px-0" 
                                                                       value="<?= htmlspecialchars($port['description'] ?? '') ?>"
                                                                       placeholder="Add description..."
                                                                       onchange="updatePortDescription(<?= $oltId ?>, '<?= htmlspecialchars($port['port_name']) ?>', this.value)"
                                                                       style="min-width: 150px;">
                                                            </td>
                                                            <td class="text-end">
                                                                <div class="btn-group btn-group-sm">
                                                                    <a href="?page=huawei-olt&view=onus&olt_id=<?= $oltId ?>&port=<?= urlencode($port['port_name']) ?>" 
                                                                       class="btn btn-outline-primary" title="View ONUs">
                                                                        <i class="bi bi-eye"></i>
                                                                    </a>
                                                                    <button type="button" class="btn btn-outline-<?= !empty($port['default_vlan']) ? 'primary' : 'secondary' ?>" 
                                                                            data-bs-toggle="modal" data-bs-target="#portSettingsModal<?= $portId ?>" 
                                                                            title="Port Settings">
                                                                        <i class="bi bi-gear"></i>
                                                                    </button>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <?php foreach ($cachedPorts as $port): 
                                $portId = str_replace('/', '_', $port['port_name']);
                                $adminEnabled = strtolower($port['admin_status'] ?? '') === 'enable';
                            ?>
                            <div class="modal fade" id="portSettingsModal<?= $portId ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h6 class="modal-title"><i class="bi bi-gear me-2"></i>Port Settings: <?= htmlspecialchars($port['port_name']) ?></h6>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label">Default VLAN for Authorization</label>
                                                    <select class="form-select" id="defaultVlan<?= $portId ?>">
                                                        <option value="">-- None --</option>
                                                        <?php foreach ($cachedVLANs as $vlan): ?>
                                                        <option value="<?= $vlan['vlan_id'] ?>" <?= ($port['default_vlan'] ?? '') == $vlan['vlan_id'] ? 'selected' : '' ?>>
                                                            VLAN <?= $vlan['vlan_id'] ?><?= $vlan['description'] ? ' - ' . htmlspecialchars($vlan['description']) : '' ?>
                                                        </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <small class="text-muted">Pre-selected when authorizing ONUs on this port</small>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Port Status</label>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <span class="badge bg-<?= $adminEnabled ? 'success' : 'secondary' ?>">
                                                            <?= $adminEnabled ? 'Enabled' : 'Disabled' ?>
                                                        </span>
                                                        <form method="post" class="d-inline">
                                                            <input type="hidden" name="action" value="toggle_port">
                                                            <input type="hidden" name="olt_id" value="<?= $oltId ?>">
                                                            <input type="hidden" name="port_name" value="<?= htmlspecialchars($port['port_name']) ?>">
                                                            <input type="hidden" name="enable" value="<?= $adminEnabled ? '0' : '1' ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-<?= $adminEnabled ? 'warning' : 'success' ?>" 
                                                                    onclick="return confirm('<?= $adminEnabled ? 'Disable' : 'Enable' ?> port <?= $port['port_name'] ?>?')">
                                                                <?= $adminEnabled ? 'Disable' : 'Enable' ?>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                                <div class="col-12">
                                                    <label class="form-label">Description</label>
                                                    <input type="text" class="form-control" id="portDesc<?= $portId ?>" 
                                                           value="<?= htmlspecialchars($port['description'] ?? '') ?>" 
                                                           placeholder="e.g., Building A - Floor 1">
                                                </div>
                                                <div class="col-12">
                                                    <label class="form-label">Assign VLAN to Port</label>
                                                    <div class="input-group">
                                                        <select class="form-select" id="assignVlan<?= $portId ?>">
                                                            <?php foreach ($cachedVLANs as $vlan): ?>
                                                            <option value="<?= $vlan['vlan_id'] ?>"><?= $vlan['vlan_id'] ?> - <?= htmlspecialchars($vlan['description'] ?: 'smart') ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <select class="form-select" id="assignVlanMode<?= $portId ?>" style="max-width: 100px;">
                                                            <option value="tag">Tagged</option>
                                                            <option value="untag">Untag</option>
                                                        </select>
                                                        <button type="button" class="btn btn-outline-primary" 
                                                                onclick="assignPortVlan(<?= $oltId ?>, '<?= htmlspecialchars($port['port_name']) ?>', '<?= $portId ?>')">
                                                            Assign
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                            <button type="button" class="btn btn-primary" 
                                                    onclick="savePortSettings(<?= $oltId ?>, '<?= htmlspecialchars($port['port_name']) ?>', '<?= $portId ?>')">
                                                <i class="bi bi-check me-1"></i> Save Changes
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            
                            <?php else: ?>
                            <div class="alert alert-info m-3">
                                <i class="bi bi-info-circle me-2"></i>No cached data. Click "Sync from OLT" to fetch PON port information.
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white">
                            <h6 class="mb-0"><i class="bi bi-sliders me-2"></i>Bulk Actions</h6>
                        </div>
                        <div class="card-body">
                            <form method="post">
                                <input type="hidden" name="action" value="bulk_port_vlan">
                                <input type="hidden" name="olt_id" value="<?= $oltId ?>">
                                
                                <div class="mb-3">
                                    <label class="form-label small">Apply VLAN to All Ports</label>
                                    <select name="vlan_id" class="form-select form-select-sm">
                                        <?php foreach ($cachedVLANs as $vlan): ?>
                                        <option value="<?= $vlan['vlan_id'] ?>"><?= $vlan['vlan_id'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <button type="submit" class="btn btn-sm btn-outline-primary w-100" onclick="return confirm('Apply this VLAN to all PON ports?')">
                                    <i class="bi bi-check-all me-1"></i> Apply to All
                                </button>
                            </form>
                            
                            <hr>
                            
                            <div class="small text-muted">
                                <strong>Legend:</strong>
                                <div class="mt-2">
                                    <i class="bi bi-play-fill text-success"></i> Enable port<br>
                                    <i class="bi bi-pause-fill text-warning"></i> Disable port<br>
                                    <i class="bi bi-diagram-2 text-info"></i> Assign VLAN
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php elseif ($detailTab === 'uplinks'): ?>
            <div class="row">
                <div class="col-lg-8">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-0 d-inline"><i class="bi bi-arrow-up-circle me-2"></i>Uplink Ports</h5>
                                <?php if (!empty($currentOlt['uplinks_synced_at'])): ?>
                                <small class="text-muted ms-2">Last sync: <?= date('M j, H:i', strtotime($currentOlt['uplinks_synced_at'])) ?></small>
                                <?php endif; ?>
                            </div>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="action" value="sync_uplinks">
                                <input type="hidden" name="olt_id" value="<?= $oltId ?>">
                                <button type="submit" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-arrow-clockwise me-1"></i> Sync from OLT
                                </button>
                            </form>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($cachedUplinks)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Port</th>
                                            <th>Type</th>
                                            <th>VLAN Mode</th>
                                            <th>PVID</th>
                                            <th>Allowed VLANs</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($cachedUplinks as $uplink): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($uplink['port_name']) ?></strong>
                                                <?php if (!empty($uplink['description'])): ?>
                                                <br><small class="text-muted"><?= htmlspecialchars($uplink['description']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td><span class="badge bg-warning"><?= htmlspecialchars($uplink['port_type'] ?? 'GE') ?></span></td>
                                            <td>
                                                <span class="badge bg-<?= ($uplink['vlan_mode'] ?? '') === 'trunk' ? 'primary' : 'secondary' ?>">
                                                    <?= htmlspecialchars(ucfirst($uplink['vlan_mode'] ?? '-')) ?>
                                                </span>
                                            </td>
                                            <td><?= $uplink['pvid'] ?? '-' ?></td>
                                            <td>
                                                <?php if (!empty($uplink['allowed_vlans'])): ?>
                                                <small><?= htmlspecialchars($uplink['allowed_vlans']) ?></small>
                                                <?php else: ?>
                                                <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#uplinkModal<?= str_replace('/', '_', $uplink['port_name']) ?>" title="Configure">
                                                    <i class="bi bi-gear"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        
                                        <div class="modal fade" id="uplinkModal<?= str_replace('/', '_', $uplink['port_name']) ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Configure Uplink <?= htmlspecialchars($uplink['port_name']) ?></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="post">
                                                        <div class="modal-body">
                                                            <input type="hidden" name="action" value="configure_uplink">
                                                            <input type="hidden" name="olt_id" value="<?= $oltId ?>">
                                                            <input type="hidden" name="port_name" value="<?= htmlspecialchars($uplink['port_name']) ?>">
                                                            
                                                            <div class="row g-3">
                                                                <div class="col-md-6">
                                                                    <label class="form-label">VLAN Mode</label>
                                                                    <select name="vlan_mode" class="form-select" id="vlanMode<?= str_replace('/', '_', $uplink['port_name']) ?>">
                                                                        <option value="trunk" <?= ($uplink['vlan_mode'] ?? '') === 'trunk' ? 'selected' : '' ?>>Trunk</option>
                                                                        <option value="access" <?= ($uplink['vlan_mode'] ?? '') === 'access' ? 'selected' : '' ?>>Access</option>
                                                                        <option value="hybrid" <?= ($uplink['vlan_mode'] ?? '') === 'hybrid' ? 'selected' : '' ?>>Hybrid</option>
                                                                    </select>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <label class="form-label">Native/Default VLAN</label>
                                                                    <input type="number" name="pvid" class="form-control" value="<?= $uplink['pvid'] ?? 1 ?>" min="1" max="4094">
                                                                </div>
                                                            </div>
                                                            
                                                            <div class="mt-3">
                                                                <label class="form-label">Allowed VLANs (for Trunk mode)</label>
                                                                <input type="text" name="allowed_vlans" class="form-control" 
                                                                       value="<?= htmlspecialchars($uplink['allowed_vlans'] ?? '') ?>" 
                                                                       placeholder="e.g., 100,200,300-400 or all">
                                                                <small class="text-muted">Comma-separated VLAN IDs or ranges. Use "all" for all VLANs.</small>
                                                            </div>
                                                            
                                                            <div class="mt-3">
                                                                <label class="form-label">Description</label>
                                                                <input type="text" name="description" class="form-control" 
                                                                       value="<?= htmlspecialchars($uplink['description'] ?? '') ?>" 
                                                                       placeholder="Port description">
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-primary">Apply Configuration</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-info mb-0">
                                <i class="bi bi-info-circle me-2"></i>No cached data. Click "Sync from OLT" to fetch uplink port information.
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="card shadow-sm mb-3">
                        <div class="card-header bg-white">
                            <h6 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Quick VLAN Assignment</h6>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($cachedUplinks) && !empty($cachedVLANs)): ?>
                            <form method="post">
                                <input type="hidden" name="action" value="add_vlan_uplink">
                                <input type="hidden" name="olt_id" value="<?= $oltId ?>">
                                
                                <div class="mb-3">
                                    <label class="form-label small">Select Uplink Port</label>
                                    <select name="port_name" class="form-select form-select-sm" required>
                                        <?php foreach ($cachedUplinks as $uplink): ?>
                                        <option value="<?= htmlspecialchars($uplink['port_name']) ?>">
                                            <?= htmlspecialchars($uplink['port_name']) ?>
                                            <?= !empty($uplink['description']) ? ' - ' . htmlspecialchars($uplink['description']) : '' ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label small">Select VLAN</label>
                                    <select name="vlan_id" class="form-select form-select-sm" required>
                                        <?php foreach ($cachedVLANs as $vlan): ?>
                                        <option value="<?= $vlan['vlan_id'] ?>">
                                            <?= $vlan['vlan_id'] ?> - <?= htmlspecialchars($vlan['description'] ?: $vlan['vlan_type'] ?? 'smart') ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <button type="submit" class="btn btn-primary btn-sm w-100">
                                    <i class="bi bi-plus-lg me-1"></i> Add VLAN to Uplink
                                </button>
                            </form>
                            <?php else: ?>
                            <div class="text-muted small">
                                Sync uplinks and VLANs first to enable quick assignment.
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="card shadow-sm">
                        <div class="card-header bg-white">
                            <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Uplink Configuration</h6>
                        </div>
                        <div class="card-body">
                            <div class="small">
                                <p><strong>VLAN Modes:</strong></p>
                                <ul class="ps-3">
                                    <li><strong>Trunk</strong>: Carries multiple VLANs with 802.1Q tagging.</li>
                                    <li><strong>Access</strong>: Single VLAN, untagged traffic.</li>
                                    <li><strong>Hybrid</strong>: Mix of tagged and untagged VLANs.</li>
                                </ul>
                                
                                <p class="mt-3"><strong>Allowed VLANs:</strong></p>
                                <ul class="ps-3 text-muted">
                                    <li><code>all</code> - All VLANs</li>
                                    <li><code>100,200,300</code> - Specific VLANs</li>
                                    <li><code>100-200</code> - VLAN range</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php elseif ($detailTab === 'advanced'): ?>
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="bi bi-terminal me-2"></i>CLI Terminal</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small mb-3">Execute custom commands on this OLT device.</p>
                            <a href="?page=huawei-olt&view=terminal&olt_id=<?= $oltId ?>" class="btn btn-primary">
                                <i class="bi bi-terminal me-2"></i>Open Terminal
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="bi bi-gear me-2"></i>OLT Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <p class="text-muted small mb-2"><i class="bi bi-broadcast me-1"></i> Optical Power Sync:</p>
                                <div class="row g-2 mb-2">
                                    <div class="col-6">
                                        <form method="post">
                                            <input type="hidden" name="action" value="refresh_all_optical_cli">
                                            <input type="hidden" name="olt_id" value="<?= $oltId ?>">
                                            <button type="submit" class="btn btn-outline-primary w-100" onclick="return confirm('Sync optical power via CLI (Telnet)? This connects to each ONU individually and may take some time.')">
                                                <i class="bi bi-terminal me-1"></i>CLI Sync
                                            </button>
                                        </form>
                                    </div>
                                    <div class="col-6">
                                        <form method="post">
                                            <input type="hidden" name="action" value="refresh_all_optical_snmp">
                                            <input type="hidden" name="olt_id" value="<?= $oltId ?>">
                                            <button type="submit" class="btn btn-outline-success w-100" onclick="return confirm('Sync optical power via SNMP? This is faster and includes distance data. Requires SNMP port (161) to be accessible.')">
                                                <i class="bi bi-hdd-network me-1"></i>SNMP Sync
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                <a href="?page=huawei-olt&view=onus&olt_id=<?= $oltId ?>&unconfigured=1" class="btn btn-outline-warning w-100">
                                    <i class="bi bi-question-circle me-2"></i>View Pending Auth ONUs
                                </a>
                                <a href="?page=huawei-olt&view=logs&olt_id=<?= $oltId ?>" class="btn btn-outline-secondary w-100">
                                    <i class="bi bi-journal-text me-2"></i>View Logs
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php else: ?>
            <div class="alert alert-danger">OLT not found.</div>
            <?php endif; ?>
            
            <?php endif; ?>
            
            
        </div>
    </div>
    
    <div class="modal fade" id="oltModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="action" id="oltAction" value="add_olt">
                    <input type="hidden" name="id" id="oltId">
                    <div class="modal-header">
                        <h5 class="modal-title" id="oltModalTitle">Add OLT</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" id="oltName" class="form-control" required>
                        </div>
                        <div class="row">
                            <div class="col-8 mb-3">
                                <label class="form-label">IP Address</label>
                                <input type="text" name="ip_address" id="oltIp" class="form-control" required>
                            </div>
                            <div class="col-4 mb-3">
                                <label class="form-label">Port</label>
                                <input type="number" name="port" id="oltPort" class="form-control" value="23">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Connection Type</label>
                            <select name="connection_type" id="oltConnType" class="form-select">
                                <option value="telnet">Telnet</option>
                                <option value="ssh">SSH</option>
                                <option value="snmp">SNMP Only</option>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" name="username" id="oltUsername" class="form-control">
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" name="password" id="oltPassword" class="form-control" placeholder="Leave blank to keep existing">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label">Vendor</label>
                                <input type="text" name="vendor" id="oltVendor" class="form-control" value="Huawei">
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">Model</label>
                                <input type="text" name="model" id="oltModel" class="form-control" placeholder="MA5800-X15">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Location</label>
                            <input type="text" name="location" id="oltLocation" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Branch</label>
                            <select name="branch_id" id="oltBranchId" class="form-select">
                                <option value="">-- No Branch --</option>
                                <?php foreach ($allBranches as $branch): ?>
                                <option value="<?= $branch['id'] ?>"><?= htmlspecialchars($branch['name']) ?><?= !empty($branch['whatsapp_group']) ? ' (WhatsApp)' : '' ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Link OLT to a branch for notifications</small>
                        </div>
                        <hr>
                        <h6 class="text-muted mb-3">SNMP Configuration</h6>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label">Read Community (Public)</label>
                                <input type="text" name="snmp_read_community" id="oltSnmpRead" class="form-control" value="public" placeholder="public">
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">Write Community (Private)</label>
                                <input type="text" name="snmp_write_community" id="oltSnmpWrite" class="form-control" value="private" placeholder="private">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label">SNMP Version</label>
                                <select name="snmp_version" id="oltSnmpVersion" class="form-select">
                                    <option value="v1">v1</option>
                                    <option value="v2c" selected>v2c</option>
                                </select>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">SNMP Port</label>
                                <input type="number" name="snmp_port" id="oltSnmpPort" class="form-control" value="161">
                            </div>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" name="is_active" id="oltActive" class="form-check-input" value="1" checked>
                            <label class="form-check-label">Active</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save OLT</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="profileModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="action" id="profileAction" value="add_profile">
                    <input type="hidden" name="id" id="profileId">
                    <div class="modal-header">
                        <h5 class="modal-title" id="profileModalTitle">Add Service Profile</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Profile Name</label>
                                <input type="text" name="name" id="profileName" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Type</label>
                                <select name="profile_type" id="profileType" class="form-select">
                                    <option value="internet">Internet</option>
                                    <option value="iptv">IPTV</option>
                                    <option value="voip">VoIP</option>
                                    <option value="enterprise">Enterprise</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">VLAN ID</label>
                                <input type="number" name="vlan_id" id="profileVlan" class="form-control">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">GEM Port</label>
                                <input type="number" name="gem_port" id="profileGemPort" class="form-control">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Native VLAN</label>
                                <input type="number" name="native_vlan" id="profileNativeVlan" class="form-control">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Speed Up</label>
                                <input type="text" name="speed_profile_up" id="profileSpeedUp" class="form-control" placeholder="10M, 50M, 100M...">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Speed Down</label>
                                <input type="text" name="speed_profile_down" id="profileSpeedDown" class="form-control" placeholder="20M, 100M, 200M...">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Line Profile ID</label>
                                <input type="text" name="line_profile" id="profileLineProfile" class="form-control" placeholder="e.g. 10">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Service Profile ID</label>
                                <input type="text" name="srv_profile" id="profileSrvProfile" class="form-control" placeholder="e.g. 10">
                            </div>
                        </div>
                        
                        <hr class="my-3">
                        <h6 class="text-muted mb-3"><i class="bi bi-gear-wide-connected me-2"></i>TR-069 Configuration (Auto-config via OMCI)</h6>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">TR-069 VLAN</label>
                                <input type="number" name="tr069_vlan" id="profileTr069Vlan" class="form-control" placeholder="e.g. 101">
                                <div class="form-text">Leave empty to skip TR-069</div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">TR-069 Profile ID</label>
                                <input type="number" name="tr069_profile_id" id="profileTr069ProfileId" class="form-control" placeholder="e.g. 1">
                                <div class="form-text">OLT TR-069 server profile</div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">TR-069 GEM Port</label>
                                <input type="number" name="tr069_gem_port" id="profileTr069GemPort" class="form-control" value="2">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" id="profileDesc" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input type="checkbox" name="is_default" id="profileDefault" class="form-check-input" value="1">
                                <label class="form-check-label">Default Profile</label>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" name="is_active" id="profileActive" class="form-check-input" value="1" checked>
                                <label class="form-check-label">Active</label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Profile</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="onuModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="action" id="onuAction" value="add_onu">
                    <input type="hidden" name="id" id="onuId">
                    <div class="modal-header">
                        <h5 class="modal-title" id="onuModalTitle">Add ONU</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label class="form-label">Serial Number</label>
                                <input type="text" name="sn" id="onuSn" class="form-control" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">OLT</label>
                                <select name="olt_id" id="onuOltId" class="form-select" required>
                                    <?php foreach ($olts as $olt): ?>
                                    <option value="<?= $olt['id'] ?>"><?= htmlspecialchars($olt['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Name / Description</label>
                            <input type="text" name="name" id="onuName" class="form-control">
                        </div>
                        <div class="row">
                            <div class="col-3 mb-3">
                                <label class="form-label">Frame</label>
                                <input type="number" name="frame" id="onuFrame" class="form-control" value="0">
                            </div>
                            <div class="col-3 mb-3">
                                <label class="form-label">Slot</label>
                                <input type="number" name="slot" id="onuSlot" class="form-control">
                            </div>
                            <div class="col-3 mb-3">
                                <label class="form-label">Port</label>
                                <input type="number" name="port" id="onuPort" class="form-control">
                            </div>
                            <div class="col-3 mb-3">
                                <label class="form-label">ONU ID</label>
                                <input type="number" name="onu_id" id="onuOnuId" class="form-control">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Customer</label>
                            <select name="customer_id" id="onuCustomerId" class="form-select">
                                <option value="">-- Not Linked --</option>
                                <?php foreach ($customers as $cust): ?>
                                <option value="<?= $cust['id'] ?>"><?= htmlspecialchars($cust['name']) ?> (<?= $cust['phone'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Service Profile</label>
                            <select name="service_profile_id" id="onuProfileId" class="form-select">
                                <option value="">-- None --</option>
                                <?php foreach ($profiles as $profile): ?>
                                <option value="<?= $profile['id'] ?>"><?= htmlspecialchars($profile['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save ONU</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="provisionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="action" value="authorize_onu">
                    <input type="hidden" name="onu_id" id="provisionOnuId">
                    <div class="modal-header">
                        <h5 class="modal-title">Provision ONU</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            Provisioning ONU: <strong id="provisionOnuSn"></strong>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Select Service Profile</label>
                            <select name="profile_id" class="form-select" required>
                                <?php foreach ($profiles as $profile): ?>
                                <option value="<?= $profile['id'] ?>" <?= $profile['is_default'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($profile['name']) ?> (VLAN: <?= $profile['vlan_id'] ?: '-' ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success"><i class="bi bi-check-circle me-1"></i> Authorize</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <form method="post" id="actionForm" style="display:none;">
        <input type="hidden" name="action" id="actionType">
        <input type="hidden" name="onu_id" id="actionOnuId">
        <input type="hidden" name="id" id="actionId">
    </form>
    
    <!-- Authorization Modal (Simplified - SmartOLT style) -->
    <div class="modal fade" id="authModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" id="authForm">
                    <input type="hidden" name="action" value="authorize_onu_simple">
                    <input type="hidden" name="onu_id" id="authOnuId">
                    <input type="hidden" name="olt_id" id="authOltId">
                    <input type="hidden" name="sn" id="authSnInput">
                    <input type="hidden" name="frame_slot_port" id="authFsp">
                    <input type="hidden" name="onu_type_id" id="authOnuType">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title"><i class="bi bi-check-circle me-2"></i>Authorize ONU</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info mb-3">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-router fs-4 me-3"></i>
                                <div>
                                    <strong>ONU:</strong> <span id="authOnuSn" class="font-monospace"></span><br>
                                    <small class="text-muted"><span id="authOnuLocation"></span></small>
                                    <small id="authEqidDisplay" class="d-block text-muted"></small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="authName" class="form-control" placeholder="e.g., John Doe" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Zone <span class="text-danger">*</span></label>
                            <select name="zone_id" id="authZoneId" class="form-select" required onchange="updateZoneName(this)">
                                <option value="">-- Select Zone --</option>
                                <?php
                                $zonesStmt = $db->query("SELECT id, name FROM huawei_zones WHERE is_active = true ORDER BY name");
                                while ($zone = $zonesStmt->fetch(PDO::FETCH_ASSOC)): ?>
                                <option value="<?= $zone['id'] ?>" data-name="<?= htmlspecialchars($zone['name']) ?>">
                                    <?= htmlspecialchars($zone['name']) ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                            <input type="hidden" name="zone" id="authZoneName">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <input type="text" name="address" id="authAddress" class="form-control" placeholder="e.g., 123 Main St, Apt 5">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Service VLAN <span class="text-danger">*</span></label>
                            <select name="vlan_id" id="authVlanId" class="form-select" required>
                                <option value="">-- Loading VLANs --</option>
                            </select>
                            <small class="text-muted">Internet VLAN for the customer</small>
                        </div>
                        
                        <div class="alert alert-secondary small mb-0">
                            <i class="bi bi-gear me-2"></i>
                            <strong>Auto-configuration:</strong> TR-069 management WAN will be automatically configured after authorization.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success" id="authSubmitBtn">
                            <i class="bi bi-check-circle me-1"></i> Authorize & Configure
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    function updateZoneName(select) {
        const selectedOption = select.options[select.selectedIndex];
        document.getElementById('authZoneName').value = selectedOption.dataset.name || '';
    }
    </script>
    
    <div class="modal fade" id="wifiConfigModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="action" value="tr069_wifi_advanced">
                    <input type="hidden" name="device_id" id="wifiDeviceId">
                    <div class="modal-header bg-info text-white">
                        <h5 class="modal-title"><i class="bi bi-wifi me-2"></i>Configure Wireless Interfaces</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info small">
                            <i class="bi bi-info-circle me-2"></i>
                            Configuring device: <strong id="wifiDeviceSn"></strong>
                        </div>
                        
                        <ul class="nav nav-tabs mb-3" role="tablist">
                            <li class="nav-item">
                                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#wifi24Tab" type="button">
                                    <i class="bi bi-broadcast me-1"></i> 2.4 GHz
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#wifi5Tab" type="button">
                                    <i class="bi bi-broadcast me-1"></i> 5 GHz
                                </button>
                            </li>
                        </ul>
                        
                        <div class="tab-content">
                            <!-- 2.4 GHz Tab -->
                            <div class="tab-pane fade show active" id="wifi24Tab">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-check form-switch mb-3">
                                            <input type="checkbox" class="form-check-input" name="wifi24_enabled" id="wifi24Enabled" checked>
                                            <label class="form-check-label" for="wifi24Enabled"><strong>Enable 2.4 GHz WiFi</strong></label>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">SSID (Network Name)</label>
                                            <input type="text" name="wifi24_ssid" class="form-control" placeholder="MyNetwork_2.4G">
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Password</label>
                                            <input type="text" name="wifi24_password" class="form-control" placeholder="Min 8 characters" minlength="8">
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Channel</label>
                                            <select name="wifi24_channel" class="form-select">
                                                <option value="0">Auto</option>
                                                <option value="1">1</option>
                                                <option value="6">6</option>
                                                <option value="11">11</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="text-muted mb-3"><i class="bi bi-diagram-3 me-1"></i> Connection Mode & VLAN</h6>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Connection Mode</label>
                                            <select name="wifi24_conn_mode" class="form-select" onchange="toggleWifi24ConnMode(this.value)">
                                                <option value="route">Route (NAT/Routed)</option>
                                                <option value="bridge">Bridge (Transparent)</option>
                                            </select>
                                            <div class="form-text" id="wifi24ConnModeHelp">Traffic is NAT'd through the ONU's WAN connection</div>
                                        </div>
                                        
                                        <div id="wifi24BridgeOptions">
                                            <div class="mb-3">
                                                <label class="form-label">VLAN Mode</label>
                                                <select name="wifi24_vlan_mode" class="form-select" onchange="toggleWifi24VlanFields(this.value)">
                                                    <option value="access">Access (Single VLAN)</option>
                                                    <option value="trunk">Trunk (Tagged VLANs)</option>
                                                </select>
                                            </div>
                                            
                                            <div id="wifi24AccessVlan" class="mb-3">
                                                <label class="form-label">Access VLAN ID</label>
                                                <input type="number" name="wifi24_access_vlan" class="form-control" value="1" min="1" max="4094">
                                                <div class="form-text">Untagged VLAN for this interface</div>
                                            </div>
                                            
                                            <div id="wifi24TrunkVlans" class="mb-3 d-none">
                                                <label class="form-label">Native VLAN</label>
                                                <input type="number" name="wifi24_native_vlan" class="form-control" value="1" min="1" max="4094">
                                                <div class="form-text">Untagged VLAN (PVID)</div>
                                            </div>
                                            
                                            <div id="wifi24AllowedVlans" class="mb-3 d-none">
                                                <label class="form-label">Allowed VLANs</label>
                                                <input type="text" name="wifi24_allowed_vlans" class="form-control" placeholder="e.g., 100,200,300-350">
                                                <div class="form-text">Comma-separated or ranges</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- 5 GHz Tab -->
                            <div class="tab-pane fade" id="wifi5Tab">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-check form-switch mb-3">
                                            <input type="checkbox" class="form-check-input" name="wifi5_enabled" id="wifi5Enabled" checked>
                                            <label class="form-check-label" for="wifi5Enabled"><strong>Enable 5 GHz WiFi</strong></label>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">SSID (Network Name)</label>
                                            <input type="text" name="wifi5_ssid" class="form-control" placeholder="MyNetwork_5G">
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Password</label>
                                            <input type="text" name="wifi5_password" class="form-control" placeholder="Min 8 characters" minlength="8">
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Channel</label>
                                            <select name="wifi5_channel" class="form-select">
                                                <option value="0">Auto</option>
                                                <option value="36">36</option>
                                                <option value="40">40</option>
                                                <option value="44">44</option>
                                                <option value="48">48</option>
                                                <option value="149">149</option>
                                                <option value="153">153</option>
                                                <option value="157">157</option>
                                                <option value="161">161</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="text-muted mb-3"><i class="bi bi-diagram-3 me-1"></i> Connection Mode & VLAN</h6>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Connection Mode</label>
                                            <select name="wifi5_conn_mode" class="form-select" onchange="toggleWifi5ConnMode(this.value)">
                                                <option value="route">Route (NAT/Routed)</option>
                                                <option value="bridge">Bridge (Transparent)</option>
                                            </select>
                                            <div class="form-text" id="wifi5ConnModeHelp">Traffic is NAT'd through the ONU's WAN connection</div>
                                        </div>
                                        
                                        <div id="wifi5BridgeOptions">
                                            <div class="mb-3">
                                                <label class="form-label">VLAN Mode</label>
                                                <select name="wifi5_vlan_mode" class="form-select" onchange="toggleWifi5VlanFields(this.value)">
                                                    <option value="access">Access (Single VLAN)</option>
                                                    <option value="trunk">Trunk (Tagged VLANs)</option>
                                                </select>
                                            </div>
                                            
                                            <div id="wifi5AccessVlan" class="mb-3">
                                                <label class="form-label">Access VLAN ID</label>
                                                <input type="number" name="wifi5_access_vlan" class="form-control" value="1" min="1" max="4094">
                                                <div class="form-text">Untagged VLAN for this interface</div>
                                            </div>
                                            
                                            <div id="wifi5TrunkVlans" class="mb-3 d-none">
                                                <label class="form-label">Native VLAN</label>
                                                <input type="number" name="wifi5_native_vlan" class="form-control" value="1" min="1" max="4094">
                                                <div class="form-text">Untagged VLAN (PVID)</div>
                                            </div>
                                            
                                            <div id="wifi5AllowedVlans" class="mb-3 d-none">
                                                <label class="form-label">Allowed VLANs</label>
                                                <input type="text" name="wifi5_allowed_vlans" class="form-control" placeholder="e.g., 100,200,300-350">
                                                <div class="form-text">Comma-separated or ranges</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <hr>
                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" name="sync_both" id="syncBothWifi">
                            <label class="form-check-label" for="syncBothWifi">Use same SSID/password for both bands</label>
                        </div>
                        
                        <!-- Service VLANs Section -->
                        <div class="card border-secondary">
                            <div class="card-header bg-secondary text-white py-2 d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-diagram-3 me-2"></i>Attached Service VLANs</span>
                                <button type="button" class="btn btn-sm btn-light" onclick="showAddVlanModal()">
                                    <i class="bi bi-plus-lg me-1"></i>Add VLAN
                                </button>
                            </div>
                            <div class="card-body p-2">
                                <div id="wifiServiceVlansContainer">
                                    <div class="text-center text-muted py-3">
                                        <i class="bi bi-hourglass-split me-1"></i> Loading VLANs...
                                    </div>
                                </div>
                                <div class="form-text mt-2">
                                    <i class="bi bi-info-circle me-1"></i>
                                    These VLANs will be available for WiFi interface VLAN configuration
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Apply Settings</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Add VLAN to ONU Modal -->
    <div class="modal fade" id="addVlanModal" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header bg-success text-white py-2">
                    <h6 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Add Service VLAN</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="addVlanOnuId">
                    <div class="mb-3">
                        <label class="form-label">Select VLAN</label>
                        <select id="addVlanSelect" class="form-select">
                            <option value="">-- Select VLAN --</option>
                        </select>
                        <div class="form-text">VLANs from OLT configuration</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">VLAN Name (Optional)</label>
                        <input type="text" id="addVlanName" class="form-control" placeholder="e.g., Internet, IPTV">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Interface Type</label>
                        <select id="addVlanInterfaceType" class="form-select">
                            <option value="wifi">WiFi Only</option>
                            <option value="eth">Ethernet Only</option>
                            <option value="all">All Interfaces</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Port Mode</label>
                        <select id="addVlanPortMode" class="form-select">
                            <option value="access">Access (Untagged)</option>
                            <option value="trunk">Trunk (Tagged)</option>
                        </select>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="addVlanIsNative">
                        <label class="form-check-label" for="addVlanIsNative">Native VLAN (for trunk mode)</label>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success btn-sm" onclick="addServiceVlan()">
                        <i class="bi bi-plus-lg me-1"></i>Add VLAN
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    let currentWifiOnuId = null;
    let currentOltId = null;
    
    function toggleWifi24VlanFields(mode) {
        document.getElementById('wifi24AccessVlan').classList.toggle('d-none', mode === 'trunk');
        document.getElementById('wifi24TrunkVlans').classList.toggle('d-none', mode === 'access');
        document.getElementById('wifi24AllowedVlans').classList.toggle('d-none', mode === 'access');
    }
    function toggleWifi5VlanFields(mode) {
        document.getElementById('wifi5AccessVlan').classList.toggle('d-none', mode === 'trunk');
        document.getElementById('wifi5TrunkVlans').classList.toggle('d-none', mode === 'access');
        document.getElementById('wifi5AllowedVlans').classList.toggle('d-none', mode === 'access');
    }
    function toggleWifi24ConnMode(mode) {
        const help = document.getElementById('wifi24ConnModeHelp');
        const bridgeOpts = document.getElementById('wifi24BridgeOptions');
        if (mode === 'bridge') {
            help.textContent = 'Traffic passes through transparently with VLAN tagging';
            bridgeOpts.classList.remove('d-none');
        } else {
            help.textContent = "Traffic is NAT'd through the ONU's WAN connection";
            bridgeOpts.classList.add('d-none');
        }
    }
    function toggleWifi5ConnMode(mode) {
        const help = document.getElementById('wifi5ConnModeHelp');
        const bridgeOpts = document.getElementById('wifi5BridgeOptions');
        if (mode === 'bridge') {
            help.textContent = 'Traffic passes through transparently with VLAN tagging';
            bridgeOpts.classList.remove('d-none');
        } else {
            help.textContent = "Traffic is NAT'd through the ONU's WAN connection";
            bridgeOpts.classList.add('d-none');
        }
    }
    
    function loadOnuServiceVlans(onuId, oltId) {
        currentWifiOnuId = onuId;
        currentOltId = oltId;
        const container = document.getElementById('wifiServiceVlansContainer');
        container.innerHTML = '<div class="text-center py-2"><span class="spinner-border spinner-border-sm"></span> Loading...</div>';
        
        fetch(`?action=get_onu_service_vlans&onu_id=${onuId}`)
            .then(r => r.json())
            .then(data => {
                if (data.vlans && data.vlans.length > 0) {
                    let html = '<table class="table table-sm table-bordered mb-0">';
                    html += '<thead class="table-light"><tr><th>VLAN</th><th>Name</th><th>Type</th><th>Mode</th><th></th></tr></thead><tbody>';
                    data.vlans.forEach(v => {
                        const nativeBadge = v.is_native ? '<span class="badge bg-warning text-dark ms-1">Native</span>' : '';
                        html += `<tr>
                            <td><span class="badge bg-primary">${v.vlan_id}</span>${nativeBadge}</td>
                            <td>${v.vlan_name || '-'}</td>
                            <td><span class="badge bg-secondary">${v.interface_type}</span></td>
                            <td>${v.port_mode}</td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeServiceVlan(${v.id})" title="Remove">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>`;
                    });
                    html += '</tbody></table>';
                    container.innerHTML = html;
                } else {
                    container.innerHTML = '<div class="text-center text-muted py-2"><i class="bi bi-info-circle me-1"></i>No service VLANs attached</div>';
                }
            })
            .catch(err => {
                container.innerHTML = '<div class="text-danger py-2"><i class="bi bi-exclamation-triangle me-1"></i>Failed to load VLANs</div>';
            });
    }
    
    function showAddVlanModal() {
        if (!currentWifiOnuId || !currentOltId) return;
        document.getElementById('addVlanOnuId').value = currentWifiOnuId;
        
        // Load available VLANs from OLT
        fetch(`?action=get_olt_vlans&olt_id=${currentOltId}`)
            .then(r => r.json())
            .then(data => {
                const select = document.getElementById('addVlanSelect');
                select.innerHTML = '<option value="">-- Select VLAN --</option>';
                if (data.vlans) {
                    data.vlans.forEach(v => {
                        const desc = v.description ? ` - ${v.description}` : '';
                        select.innerHTML += `<option value="${v.vlan_id}">${v.vlan_id}${desc}</option>`;
                    });
                }
            });
        
        new bootstrap.Modal(document.getElementById('addVlanModal')).show();
    }
    
    function addServiceVlan() {
        const onuId = document.getElementById('addVlanOnuId').value;
        const vlanId = document.getElementById('addVlanSelect').value;
        const vlanName = document.getElementById('addVlanName').value;
        const interfaceType = document.getElementById('addVlanInterfaceType').value;
        const portMode = document.getElementById('addVlanPortMode').value;
        const isNative = document.getElementById('addVlanIsNative').checked;
        
        if (!vlanId) {
            alert('Please select a VLAN');
            return;
        }
        
        fetch('?action=add_onu_service_vlan', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `onu_id=${onuId}&vlan_id=${vlanId}&vlan_name=${encodeURIComponent(vlanName)}&interface_type=${interfaceType}&port_mode=${portMode}&is_native=${isNative ? 1 : 0}`
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('addVlanModal')).hide();
                loadOnuServiceVlans(currentWifiOnuId, currentOltId);
            } else {
                alert(data.error || 'Failed to add VLAN');
            }
        })
        .catch(err => alert('Error adding VLAN'));
    }
    
    function removeServiceVlan(vlanRecordId) {
        if (!confirm('Remove this service VLAN?')) return;
        
        fetch('?action=remove_onu_service_vlan', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `id=${vlanRecordId}`
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                loadOnuServiceVlans(currentWifiOnuId, currentOltId);
            } else {
                alert(data.error || 'Failed to remove VLAN');
            }
        })
        .catch(err => alert('Error removing VLAN'));
    }
    
    // Hook into WiFi modal opening
    document.getElementById('wifiConfigModal').addEventListener('show.bs.modal', function(e) {
        const button = e.relatedTarget;
        if (button) {
            const onuId = button.dataset.onuId;
            const oltId = button.dataset.oltId;
            if (onuId && oltId) {
                loadOnuServiceVlans(onuId, oltId);
            }
        }
    });
    </script>

    <!-- Admin Password Change Modal -->
    <div class="modal fade" id="adminPasswordModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="action" value="tr069_admin_password">
                    <input type="hidden" name="device_id" id="adminPassDeviceId">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-key me-2"></i>Change Admin Password</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info small">
                            <i class="bi bi-info-circle me-2"></i>
                            Changing password for device: <strong id="adminPassDeviceSn"></strong>
                        </div>
                        <div class="alert alert-warning small">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            This will change the ONU web interface login password via TR-069.
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Admin Username</label>
                            <input type="text" name="admin_username" class="form-control" value="admin" placeholder="admin">
                            <div class="form-text">Usually "admin" for most devices</div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <div class="input-group">
                                <input type="password" name="new_password" id="newAdminPass" class="form-control" placeholder="Enter new password" required minlength="6">
                                <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('newAdminPass')">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div class="form-text">Minimum 6 characters</div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Confirm Password</label>
                            <input type="password" name="confirm_password" id="confirmAdminPass" class="form-control" placeholder="Confirm new password" required minlength="6">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning" onclick="return validatePasswordMatch()">
                            <i class="bi bi-key me-1"></i> Change Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="onuFullStatusModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title" id="onuFullStatusTitle"><i class="bi bi-clipboard-data me-2"></i>ONU Full Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="onuFullStatusBody">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-3">Fetching ONU status from OLT...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="dbaProfileModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title"><i class="bi bi-speedometer2 me-2"></i>Configure Speed Profile (DBA)</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="?page=huawei-olt">
                    <input type="hidden" name="action" value="update_dba_profile">
                    <input type="hidden" name="onu_id" id="dbaOnuId">
                    <input type="hidden" name="vlan_id" id="dbaVlanId">
                    <div class="modal-body">
                        <div class="alert alert-info small">
                            <i class="bi bi-info-circle me-1"></i>
                            <strong>Speed Profile (DBA)</strong> controls bandwidth allocation for this ONU.
                            This is different from <strong>Service Profile</strong> which corresponds to the ONU type/model.
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">DBA Profile</label>
                            <select name="dba_profile_id" class="form-select" required>
                                <option value="">-- Select DBA Profile --</option>
                                <?php
                                $dbaProfiles = $db->query("SELECT * FROM huawei_dba_profiles ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($dbaProfiles as $dba): ?>
                                <option value="<?= $dba['id'] ?>">
                                    <?= htmlspecialchars($dba['name']) ?>
                                    (<?= isset($dba['max_bandwidth']) ? number_format($dba['max_bandwidth']/1000).' Mbps' : 'N/A' ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">DBA = Dynamic Bandwidth Allocation (controls download/upload speeds)</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-info">
                            <i class="bi bi-check-lg me-1"></i> Apply DBA Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="ethPortModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="ethPortModalLabel"><i class="bi bi-ethernet me-2"></i>Configure ETH Port</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="?page=huawei-olt">
                    <input type="hidden" name="action" value="configure_eth_port_omci">
                    <input type="hidden" name="onu_id" id="ethPortOnuId">
                    <input type="hidden" name="port_num" id="ethPortNum">
                    <div class="modal-body">
                        <div class="alert alert-info small">
                            <i class="bi bi-info-circle me-1"></i>
                            <strong>OMCI Port Configuration</strong> - These settings are pushed directly to the OLT via Telnet/CLI commands.
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Port Mode</label>
                            <select name="port_mode" class="form-select" id="ethPortMode" onchange="toggleEthPortFields()">
                                <option value="transparent">Transparent (Bridge)</option>
                                <option value="access">Access (Untagged)</option>
                                <option value="trunk">Trunk (Tagged)</option>
                                <option value="hybrid">Hybrid</option>
                            </select>
                            <div class="form-text">Transparent passes all VLANs, Access uses single VLAN untagged</div>
                        </div>
                        
                        <div class="mb-3" id="ethPortVlanField">
                            <label class="form-label">Native VLAN ID</label>
                            <input type="number" name="vlan_id" class="form-control" placeholder="e.g. 100" min="1" max="4094">
                            <div class="form-text">VLAN ID for this port (required for Access/Trunk mode)</div>
                        </div>
                        
                        <div class="mb-3" id="ethPortAllowedField" style="display:none;">
                            <label class="form-label">Allowed VLANs</label>
                            <input type="text" name="allowed_vlans" class="form-control" placeholder="e.g. 100-200,300">
                            <div class="form-text">VLAN range for trunk mode</div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Priority (802.1p)</label>
                            <select name="priority" class="form-select">
                                <option value="0">0 (Best Effort)</option>
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                                <option value="4">4</option>
                                <option value="5">5 (Video)</option>
                                <option value="6">6 (Voice)</option>
                                <option value="7">7 (Network Control)</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i> Apply via OMCI
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    function toggleEthPortFields() {
        const mode = document.getElementById('ethPortMode').value;
        document.getElementById('ethPortAllowedField').style.display = (mode === 'trunk' || mode === 'hybrid') ? 'block' : 'none';
    }
    </script>
    
    <div class="modal fade" id="onuConfigModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title"><i class="bi bi-code-slash me-2"></i>ONU Configuration</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="onuConfigBody">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-3">Fetching configuration from OLT...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-primary" onclick="copyOnuConfig()">
                        <i class="bi bi-clipboard me-1"></i> Copy to Clipboard
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="pppoeConfigModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="bi bi-globe me-2"></i>Configure WAN/PPPoE</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="?page=huawei-olt">
                    <input type="hidden" name="action" value="configure_pppoe">
                    <input type="hidden" name="serial" id="pppoeSerialInput">
                    <div class="modal-body">
                        <p class="text-muted small">ONU: <strong id="pppoeDeviceSn"></strong></p>
                        
                        <div class="mb-3">
                            <label class="form-label">Connection Type</label>
                            <select name="connection_type" class="form-select" id="pppoeConnectionType" onchange="togglePPPoEFields()">
                                <option value="pppoe">PPPoE (Router Mode)</option>
                                <option value="dhcp">DHCP (Bridge Mode)</option>
                                <option value="static">Static IP</option>
                            </select>
                        </div>
                        
                        <div id="pppoeCredentials">
                            <div class="mb-3">
                                <label class="form-label">PPPoE Username</label>
                                <input type="text" name="pppoe_username" class="form-control" placeholder="username@isp.com">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">PPPoE Password</label>
                                <input type="password" name="pppoe_password" class="form-control" placeholder="password">
                            </div>
                        </div>
                        
                        <div id="staticIpFields" class="d-none">
                            <div class="mb-3">
                                <label class="form-label">IP Address</label>
                                <input type="text" name="static_ip" class="form-control" placeholder="192.168.1.100">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Subnet Mask</label>
                                <input type="text" name="subnet_mask" class="form-control" value="255.255.255.0">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Gateway</label>
                                <input type="text" name="gateway" class="form-control" placeholder="192.168.1.1">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">DNS Server</label>
                                <input type="text" name="dns" class="form-control" value="8.8.8.8">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">VLAN ID (optional)</label>
                            <input type="number" name="vlan_id" class="form-control" placeholder="Leave empty for default">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check me-1"></i> Apply Configuration
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    function togglePPPoEFields() {
        const type = document.getElementById('pppoeConnectionType').value;
        document.getElementById('pppoeCredentials').classList.toggle('d-none', type !== 'pppoe');
        document.getElementById('staticIpFields').classList.toggle('d-none', type !== 'static');
    }
    </script>
    
    <div class="modal fade" id="pppoeWanModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-router me-2"></i>Add Internet Service (PPPoE)</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="?page=huawei-olt">
                    <input type="hidden" name="action" value="configure_pppoe_wan">
                    <input type="hidden" name="onu_db_id" id="pppoeWanOnuId">
                    <div class="modal-body">
                        <div class="alert alert-info small">
                            <i class="bi bi-info-circle me-1"></i>
                            SmartOLT-style: This configures Internet WAN via TR-069 (GenieACS). LAN ports and WiFi will be bound to this WAN.
                        </div>
                        <p class="text-muted small">ONU: <strong id="pppoeWanOnuSn"></strong></p>
                        
                        <input type="hidden" name="gemport" value="1">
                        <div class="mb-3">
                            <label class="form-label">PPPoE VLAN</label>
                            <input type="number" name="pppoe_vlan" class="form-control" value="902" required>
                            <small class="text-muted">Internet service VLAN</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">PPPoE Username <span class="text-danger">*</span></label>
                            <input type="text" name="pppoe_username" class="form-control" placeholder="user@isp.com" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">PPPoE Password <span class="text-danger">*</span></label>
                            <input type="text" name="pppoe_password" class="form-control" placeholder="password" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Priority</label>
                                <input type="number" name="priority" class="form-control" value="0" min="0" max="7">
                            </div>
                            <div class="col-md-6 mb-3 d-flex align-items-end">
                                <div class="form-check">
                                    <input type="checkbox" name="nat_enabled" class="form-check-input" id="pppoeNatEnabled" checked>
                                    <label class="form-check-label" for="pppoeNatEnabled">Enable NAT</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check me-1"></i> Configure PPPoE
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    function showPPPoEWanModal(onuDbId, serial) {
        document.getElementById('pppoeWanOnuId').value = onuDbId;
        document.getElementById('pppoeWanOnuSn').textContent = serial;
        new bootstrap.Modal(document.getElementById('pppoeWanModal')).show();
    }
    </script>
    
    <div class="modal fade" id="configScriptModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-terminal me-2"></i>OLT Configuration Script</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning small">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Important:</strong> Run these commands on your Huawei OLT via Telnet/SSH before authorizing ONUs.
                        The line profile and service profile IDs must exist on the OLT.
                    </div>
                    
                    <ul class="nav nav-tabs mb-3" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#lineProfilesTab">Line Profiles</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#srvProfilesTab">Service Profiles</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#trafficProfilesTab">Traffic Profiles</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#fullScriptTab">Full Script</button>
                        </li>
                    </ul>
                    
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="lineProfilesTab">
                            <p class="small text-muted">ONT Line Profiles define the TCONT and GEM port mapping for upstream/downstream traffic.</p>
                            <pre class="bg-dark text-light p-3 rounded" style="max-height: 300px; overflow: auto;"><code><?php
foreach ($profiles as $p) {
    if (empty($p['line_profile'])) continue;
    $lpId = htmlspecialchars($p['line_profile']);
    $name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $p['name']);
    $vlan = $p['vlan_id'] ?: 100;
    echo "# Line Profile for: {$p['name']}\n";
    echo "ont-lineprofile gpon profile-id {$lpId} profile-name {$name}\n";
    echo "  tcont 1 dba-profile-id 1\n";
    echo "  gem add 1 eth tcont 1\n";
    echo "  gem mapping 1 0 vlan {$vlan}\n";
    echo "  commit\n";
    echo "  quit\n\n";
}
if (empty(array_filter($profiles, fn($p) => !empty($p['line_profile'])))) {
    echo "# No profiles with Line Profile IDs configured\n";
}
?></code></pre>
                        </div>
                        
                        <div class="tab-pane fade" id="srvProfilesTab">
                            <p class="small text-muted">ONT Service Profiles define the port capabilities (ETH, POTS, WiFi) of the ONU.</p>
                            <pre class="bg-dark text-light p-3 rounded" style="max-height: 300px; overflow: auto;"><code><?php
foreach ($profiles as $p) {
    if (empty($p['srv_profile'])) continue;
    $spId = htmlspecialchars($p['srv_profile']);
    $name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $p['name']);
    echo "# Service Profile for: {$p['name']}\n";
    echo "ont-srvprofile gpon profile-id {$spId} profile-name {$name}\n";
    echo "  ont-port eth adaptive pots 0 catv 0\n";
    echo "  port vlan eth 1 translation {$p['vlan_id']} user-vlan untagged\n";
    echo "  commit\n";
    echo "  quit\n\n";
}
if (empty(array_filter($profiles, fn($p) => !empty($p['srv_profile'])))) {
    echo "# No profiles with Service Profile IDs configured\n";
}
?></code></pre>
                        </div>
                        
                        <div class="tab-pane fade" id="trafficProfilesTab">
                            <p class="small text-muted">Traffic/DBA Profiles define bandwidth allocation for upstream traffic.</p>
                            <pre class="bg-dark text-light p-3 rounded" style="max-height: 300px; overflow: auto;"><code><?php
$speeds = [];
foreach ($profiles as $p) {
    if (!empty($p['speed_profile_up'])) {
        $speeds[$p['speed_profile_up']] = true;
    }
}
if (!empty($speeds)) {
    echo "# DBA Profiles for bandwidth control\n\n";
    $dbaId = 1;
    foreach (array_keys($speeds) as $speed) {
        $speedKbps = ((int)$speed) * 1024;
        echo "# DBA Profile: {$speed}Mbps\n";
        echo "dba-profile add profile-id {$dbaId} profile-name speed_{$speed}m type4 max {$speedKbps}\n\n";
        $dbaId++;
    }
    echo "\n# Traffic Tables for downstream\n\n";
    foreach ($profiles as $p) {
        if (empty($p['speed_profile_down'])) continue;
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $p['name']);
        $downKbps = ((int)$p['speed_profile_down']) * 1024;
        echo "# Traffic table for: {$p['name']}\n";
        echo "traffic table ip index {$p['line_profile']} cir {$downKbps} priority 0 priority-policy local-setting\n\n";
    }
} else {
    echo "# No speed profiles configured\n";
    echo "# Example DBA profile:\n";
    echo "dba-profile add profile-id 1 profile-name speed_50m type4 max 51200\n";
}
?></code></pre>
                        </div>
                        
                        <div class="tab-pane fade" id="fullScriptTab">
                            <p class="small text-muted">Complete configuration script for all profiles. Copy and paste into OLT CLI.</p>
                            <pre class="bg-dark text-light p-3 rounded" style="max-height: 400px; overflow: auto;"><code><?php
echo "# ================================================\n";
echo "# Huawei OLT Configuration Script\n";
echo "# Generated: " . date('Y-m-d H:i:s') . "\n";
echo "# ================================================\n\n";
echo "enable\nconfig\n\n";

echo "# ========== DBA Profiles ==========\n";
$speeds = [];
$dbaId = 1;
foreach ($profiles as $p) {
    if (!empty($p['speed_profile_up']) && !isset($speeds[$p['speed_profile_up']])) {
        $speedKbps = ((int)$p['speed_profile_up']) * 1024;
        echo "dba-profile add profile-id {$dbaId} profile-name speed_{$p['speed_profile_up']}m type4 max {$speedKbps}\n";
        $speeds[$p['speed_profile_up']] = $dbaId;
        $dbaId++;
    }
}
echo "\n";

echo "# ========== Line Profiles ==========\n";
foreach ($profiles as $p) {
    if (empty($p['line_profile'])) continue;
    $lpId = htmlspecialchars($p['line_profile']);
    $name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $p['name']);
    $vlan = $p['vlan_id'] ?: 100;
    $dbaRef = $speeds[$p['speed_profile_up']] ?? 1;
    echo "ont-lineprofile gpon profile-id {$lpId} profile-name {$name}\n";
    echo "  tcont 1 dba-profile-id {$dbaRef}\n";
    echo "  gem add 1 eth tcont 1\n";
    echo "  gem mapping 1 0 vlan {$vlan}\n";
    echo "  commit\n";
    echo "  quit\n\n";
}

echo "# ========== Service Profiles ==========\n";
foreach ($profiles as $p) {
    if (empty($p['srv_profile'])) continue;
    $spId = htmlspecialchars($p['srv_profile']);
    $name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $p['name']);
    $vlan = $p['vlan_id'] ?: 100;
    echo "ont-srvprofile gpon profile-id {$spId} profile-name {$name}\n";
    echo "  ont-port eth adaptive pots 0 catv 0\n";
    echo "  port vlan eth 1 translation {$vlan} user-vlan untagged\n";
    echo "  commit\n";
    echo "  quit\n\n";
}

echo "# ========== Traffic Tables ==========\n";
foreach ($profiles as $p) {
    if (empty($p['speed_profile_down']) || empty($p['line_profile'])) continue;
    $downKbps = ((int)$p['speed_profile_down']) * 1024;
    echo "traffic table ip index {$p['line_profile']} cir {$downKbps} priority 0 priority-policy local-setting\n";
}
echo "\n";

echo "# ========== Service Ports (per VLAN) ==========\n";
$vlans = array_unique(array_filter(array_column($profiles, 'vlan_id')));
foreach ($vlans as $vlan) {
    echo "# Create VLAN {$vlan} if not exists\n";
    echo "vlan {$vlan} smart\n";
    echo "port vlan {$vlan} 0/0 0\n\n";
}

echo "quit\nquit\n";
echo "\n# ================================================\n";
echo "# Script Complete\n";
echo "# ================================================\n";
?></code></pre>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="copyConfigScript()">
                        <i class="bi bi-clipboard me-1"></i> Copy Full Script
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Zone Modal -->
    <div class="modal fade" id="zoneModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="action" id="zoneAction" value="add_zone">
                    <input type="hidden" name="id" id="zoneId">
                    <div class="modal-header">
                        <h5 class="modal-title" id="zoneModalTitle">Add Zone</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Zone Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="zoneName" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" id="zoneDescription" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" name="is_active" id="zoneActive" class="form-check-input" value="1" checked>
                            <label class="form-check-label">Active</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Zone</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Subzone Modal -->
    <div class="modal fade" id="subzoneModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="action" id="subzoneAction" value="add_subzone">
                    <input type="hidden" name="id" id="subzoneId">
                    <div class="modal-header">
                        <h5 class="modal-title" id="subzoneModalTitle">Add Subzone</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Zone <span class="text-danger">*</span></label>
                            <select name="zone_id" id="subzoneZoneId" class="form-select" required>
                                <option value="">-- Select Zone --</option>
                                <?php foreach ($zones as $zone): ?>
                                <option value="<?= $zone['id'] ?>"><?= htmlspecialchars($zone['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Subzone Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="subzoneName" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" id="subzoneDescription" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" name="is_active" id="subzoneActive" class="form-check-input" value="1" checked>
                            <label class="form-check-label">Active</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Subzone</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Apartment Modal -->
    <div class="modal fade" id="apartmentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="action" id="apartmentAction" value="add_apartment">
                    <input type="hidden" name="id" id="apartmentId">
                    <div class="modal-header">
                        <h5 class="modal-title" id="apartmentModalTitle">Add Apartment / Building</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Zone <span class="text-danger">*</span></label>
                                <select name="zone_id" id="apartmentZoneId" class="form-select" required onchange="filterApartmentSubzones()">
                                    <option value="">-- Select Zone --</option>
                                    <?php foreach ($zones as $zone): ?>
                                    <option value="<?= $zone['id'] ?>"><?= htmlspecialchars($zone['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Subzone</label>
                                <select name="subzone_id" id="apartmentSubzoneId" class="form-select">
                                    <option value="">-- None --</option>
                                    <?php foreach ($subzones as $sz): ?>
                                    <option value="<?= $sz['id'] ?>" data-zone="<?= $sz['zone_id'] ?>"><?= htmlspecialchars($sz['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Apartment / Building Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="apartmentName" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <input type="text" name="address" id="apartmentAddress" class="form-control">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Number of Floors</label>
                                <input type="number" name="floors" id="apartmentFloors" class="form-control" min="1">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Units per Floor</label>
                                <input type="number" name="units_per_floor" id="apartmentUnits" class="form-control" min="1">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Apartment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- ODB Modal -->
    <div class="modal fade" id="odbModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="action" id="odbAction" value="add_odb">
                    <input type="hidden" name="id" id="odbId">
                    <div class="modal-header">
                        <h5 class="modal-title" id="odbModalTitle">Add ODB Unit</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Zone <span class="text-danger">*</span></label>
                                <select name="zone_id" id="odbZoneId" class="form-select" required onchange="filterOdbApartments()">
                                    <option value="">-- Select Zone --</option>
                                    <?php foreach ($zones as $zone): ?>
                                    <option value="<?= $zone['id'] ?>"><?= htmlspecialchars($zone['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Apartment</label>
                                <select name="apartment_id" id="odbApartmentId" class="form-select">
                                    <option value="">-- None --</option>
                                    <?php foreach ($apartments as $apt): ?>
                                    <option value="<?= $apt['id'] ?>" data-zone="<?= $apt['zone_id'] ?>"><?= htmlspecialchars($apt['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label class="form-label">ODB Code <span class="text-danger">*</span></label>
                                <input type="text" name="code" id="odbCode" class="form-control" required placeholder="e.g., ODB-001">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Capacity <span class="text-danger">*</span></label>
                                <input type="number" name="capacity" id="odbCapacity" class="form-control" required min="1" value="8">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Location Description</label>
                            <input type="text" name="location_description" id="odbLocation" class="form-control" placeholder="e.g., Floor 2, Near Elevator">
                        </div>
                        <div class="form-check">
                            <input type="checkbox" name="is_active" id="odbActive" class="form-check-input" value="1" checked>
                            <label class="form-check-label">Active</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save ODB</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    // Loading overlay for OLT sync operations
    const loadingMessages = {
        'sync_onus_snmp': 'Syncing ONUs from OLT...',
        'sync_onu_locations': 'Fixing ONU location data from SNMP...',
        'import_smartolt': 'Importing ONUs from SmartOLT...',
        'sync_tr069_devices': 'Syncing TR-069 devices...',
        'sync_boards': 'Syncing board information...',
        'sync_vlans': 'Syncing VLANs from OLT...',
        'sync_ports': 'Syncing PON ports...',
        'sync_uplinks': 'Syncing uplink ports...',
        'sync_all_olt': 'Running full OLT sync...',
        'test_connection': 'Testing connection...',
        'discover_unconfigured': 'Discovering unconfigured ONUs...',
        'get_olt_info_snmp': 'Getting OLT system info...',
        'refresh_onu_optical': 'Reading optical levels...',
        'execute_command': 'Executing CLI command...',
        'authorize_onu': 'Authorizing ONU...',
        'reboot_onu': 'Rebooting ONU...',
        'delete_onu_olt': 'Removing ONU from OLT...',
        'configure_wifi': 'Configuring WiFi...',
        'tr069_refresh': 'Refreshing device...',
        'tr069_reboot': 'Rebooting device...',
        'tr069_factory_reset': 'Factory resetting device...'
    };
    
    function showLoading(message) {
        document.getElementById('loadingText').textContent = message || 'Processing...';
        document.getElementById('loadingOverlay').classList.add('active');
    }
    
    function hideLoading() {
        document.getElementById('loadingOverlay').classList.remove('active');
    }
    
    // Intercept all form submissions that involve OLT operations
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('form').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                const actionInput = form.querySelector('input[name="action"]');
                if (actionInput) {
                    const action = actionInput.value;
                    if (loadingMessages[action]) {
                        showLoading(loadingMessages[action]);
                    }
                }
            });
        });
    });
    
    function copyConfigScript() {
        const fullScriptTab = document.querySelector('#fullScriptTab code');
        navigator.clipboard.writeText(fullScriptTab.textContent).then(() => {
            alert('Configuration script copied to clipboard!');
        });
    }
    
    function resetOltForm() {
        document.getElementById('oltAction').value = 'add_olt';
        document.getElementById('oltId').value = '';
        document.getElementById('oltModalTitle').textContent = 'Add OLT';
        document.getElementById('oltName').value = '';
        document.getElementById('oltIp').value = '';
        document.getElementById('oltPort').value = '23';
        document.getElementById('oltConnType').value = 'telnet';
        document.getElementById('oltUsername').value = '';
        document.getElementById('oltPassword').value = '';
        document.getElementById('oltVendor').value = 'Huawei';
        document.getElementById('oltModel').value = '';
        document.getElementById('oltLocation').value = '';
        document.getElementById('oltBranchId').value = '';
        document.getElementById('oltSnmpRead').value = 'public';
        document.getElementById('oltSnmpWrite').value = 'private';
        document.getElementById('oltSnmpVersion').value = 'v2c';
        document.getElementById('oltSnmpPort').value = '161';
        document.getElementById('oltActive').checked = true;
    }
    
    function editOlt(olt) {
        document.getElementById('oltAction').value = 'update_olt';
        document.getElementById('oltId').value = olt.id;
        document.getElementById('oltModalTitle').textContent = 'Edit OLT';
        document.getElementById('oltName').value = olt.name;
        document.getElementById('oltIp').value = olt.ip_address;
        document.getElementById('oltPort').value = olt.port;
        document.getElementById('oltConnType').value = olt.connection_type;
        document.getElementById('oltUsername').value = olt.username || '';
        document.getElementById('oltPassword').value = '';
        document.getElementById('oltVendor').value = olt.vendor || 'Huawei';
        document.getElementById('oltModel').value = olt.model || '';
        document.getElementById('oltLocation').value = olt.location || '';
        document.getElementById('oltBranchId').value = olt.branch_id || '';
        document.getElementById('oltSnmpRead').value = olt.snmp_read_community || 'public';
        document.getElementById('oltSnmpWrite').value = olt.snmp_write_community || 'private';
        document.getElementById('oltSnmpVersion').value = olt.snmp_version || 'v2c';
        document.getElementById('oltSnmpPort').value = olt.snmp_port || '161';
        document.getElementById('oltActive').checked = olt.is_active;
        new bootstrap.Modal(document.getElementById('oltModal')).show();
    }
    
    function resetProfileForm() {
        document.getElementById('profileAction').value = 'add_profile';
        document.getElementById('profileId').value = '';
        document.getElementById('profileModalTitle').textContent = 'Add Service Profile';
        document.getElementById('profileName').value = '';
        document.getElementById('profileType').value = 'internet';
        document.getElementById('profileVlan').value = '';
        document.getElementById('profileGemPort').value = '';
        document.getElementById('profileNativeVlan').value = '';
        document.getElementById('profileSpeedUp').value = '';
        document.getElementById('profileSpeedDown').value = '';
        document.getElementById('profileLineProfile').value = '';
        document.getElementById('profileSrvProfile').value = '';
        document.getElementById('profileTr069Vlan').value = '';
        document.getElementById('profileTr069ProfileId').value = '';
        document.getElementById('profileTr069GemPort').value = '2';
        document.getElementById('profileDesc').value = '';
        document.getElementById('profileDefault').checked = false;
        document.getElementById('profileActive').checked = true;
    }
    
    function editProfile(profile) {
        document.getElementById('profileAction').value = 'update_profile';
        document.getElementById('profileId').value = profile.id;
        document.getElementById('profileModalTitle').textContent = 'Edit Service Profile';
        document.getElementById('profileName').value = profile.name;
        document.getElementById('profileType').value = profile.profile_type;
        document.getElementById('profileVlan').value = profile.vlan_id || '';
        document.getElementById('profileGemPort').value = profile.gem_port || '';
        document.getElementById('profileNativeVlan').value = profile.native_vlan || '';
        document.getElementById('profileSpeedUp').value = profile.speed_profile_up || '';
        document.getElementById('profileSpeedDown').value = profile.speed_profile_down || '';
        document.getElementById('profileLineProfile').value = profile.line_profile || '';
        document.getElementById('profileSrvProfile').value = profile.srv_profile || '';
        document.getElementById('profileTr069Vlan').value = profile.tr069_vlan || '';
        document.getElementById('profileTr069ProfileId').value = profile.tr069_profile_id || '';
        document.getElementById('profileTr069GemPort').value = profile.tr069_gem_port || '2';
        document.getElementById('profileDesc').value = profile.description || '';
        document.getElementById('profileDefault').checked = profile.is_default;
        document.getElementById('profileActive').checked = profile.is_active;
        new bootstrap.Modal(document.getElementById('profileModal')).show();
    }
    
    function deleteProfile(id) {
        if (confirm('Delete this service profile?')) {
            document.getElementById('actionType').value = 'delete_profile';
            document.getElementById('actionId').value = id;
            document.getElementById('actionForm').submit();
        }
    }
    
    function resetOnuForm() {
        document.getElementById('onuAction').value = 'add_onu';
        document.getElementById('onuId').value = '';
        document.getElementById('onuModalTitle').textContent = 'Add ONU';
        document.getElementById('onuSn').value = '';
        document.getElementById('onuName').value = '';
        document.getElementById('onuFrame').value = '0';
        document.getElementById('onuSlot').value = '';
        document.getElementById('onuPort').value = '';
        document.getElementById('onuOnuId').value = '';
        document.getElementById('onuCustomerId').value = '';
        document.getElementById('onuProfileId').value = '';
    }
    
    function editOnu(onu) {
        document.getElementById('onuAction').value = 'update_onu';
        document.getElementById('onuId').value = onu.id;
        document.getElementById('onuModalTitle').textContent = 'Edit ONU';
        document.getElementById('onuSn').value = onu.sn;
        document.getElementById('onuOltId').value = onu.olt_id;
        document.getElementById('onuName').value = onu.name || '';
        document.getElementById('onuFrame').value = onu.frame || 0;
        document.getElementById('onuSlot').value = onu.slot || '';
        document.getElementById('onuPort').value = onu.port || '';
        document.getElementById('onuOnuId').value = onu.onu_id || '';
        document.getElementById('onuCustomerId').value = onu.customer_id || '';
        document.getElementById('onuProfileId').value = onu.service_profile_id || '';
        new bootstrap.Modal(document.getElementById('onuModal')).show();
    }
    
    function provisionOnu(id, sn) {
        document.getElementById('provisionOnuId').value = id;
        document.getElementById('provisionOnuSn').textContent = sn;
        new bootstrap.Modal(document.getElementById('provisionModal')).show();
    }
    
    function authorizeOnu(id, sn, slot, port, onuTypeId, eqid, defaultMode) {
        document.getElementById('authOnuId').value = id;
        document.getElementById('authOnuSn').textContent = sn;
        document.getElementById('authOnuLocation').textContent = '0/' + (slot || '-') + '/' + (port || '-');
        document.getElementById('authDescription').value = '';
        
        // Set ONU type if matched
        var onuTypeSelect = document.getElementById('authOnuType');
        if (onuTypeSelect && onuTypeId) {
            onuTypeSelect.value = onuTypeId;
        } else if (onuTypeSelect) {
            onuTypeSelect.value = '';
        }
        
        // Show equipment ID if detected
        var eqidDisplay = document.getElementById('authEqidDisplay');
        if (eqidDisplay) {
            if (eqid) {
                eqidDisplay.textContent = 'Detected: ' + eqid;
                eqidDisplay.style.display = 'block';
            } else {
                eqidDisplay.style.display = 'none';
            }
        }
        
        // Set default mode based on ONU type
        if (defaultMode === 'router') {
            document.getElementById('authModeRouter').checked = true;
        } else {
            document.getElementById('authModeBridge').checked = true;
        }
        
        new bootstrap.Modal(document.getElementById('authModal')).show();
    }
    
    function openAuthModal(sn, oltId, frameSlotPort, onuTypeId) {
        document.getElementById('authOnuId').value = '';
        document.getElementById('authOnuSn').textContent = sn;
        document.getElementById('authOnuLocation').textContent = frameSlotPort || '-';
        document.getElementById('authDescription').value = '';
        
        var onuTypeSelect = document.getElementById('authOnuType');
        if (onuTypeSelect && onuTypeId) {
            onuTypeSelect.value = onuTypeId;
        } else if (onuTypeSelect) {
            onuTypeSelect.value = '';
        }
        
        var oltSelect = document.getElementById('authOltId');
        if (oltSelect && oltId) {
            oltSelect.value = oltId;
        }
        
        var snInput = document.getElementById('authSnInput');
        if (snInput) {
            snInput.value = sn;
        }
        
        var fspInput = document.getElementById('authFsp');
        if (fspInput) {
            fspInput.value = frameSlotPort || '';
        }
        
        var authModeBridge = document.getElementById('authModeBridge');
        if (authModeBridge) {
            authModeBridge.checked = true;
        }
        
        // Load VLANs for this specific OLT with PON default
        var vlanSelect = document.getElementById('authVlanId');
        if (!vlanSelect) return;
        vlanSelect.innerHTML = '<option value="">Loading VLANs...</option>';
        
        if (oltId) {
            var url = '?page=huawei-olt&action=get_auth_vlans&olt_id=' + oltId;
            if (frameSlotPort) {
                url += '&fsp=' + encodeURIComponent(frameSlotPort);
            }
            
            fetch(url)
                .then(r => r.json())
                .then(data => {
                    vlanSelect.innerHTML = '<option value="">-- Select Service VLAN --</option>';
                    if (data.success && data.vlans) {
                        data.vlans.forEach(v => {
                            var label = 'VLAN ' + v.vlan_id;
                            if (v.description) label += ' - ' + v.description;
                            var opt = document.createElement('option');
                            opt.value = v.vlan_id;
                            opt.textContent = label;
                            if (data.default_vlan && v.vlan_id == data.default_vlan) {
                                opt.selected = true;
                            }
                            vlanSelect.appendChild(opt);
                        });
                    }
                })
                .catch(e => {
                    vlanSelect.innerHTML = '<option value="">Error loading VLANs</option>';
                });
        } else {
            vlanSelect.innerHTML = '<option value="">-- Select OLT first --</option>';
        }
        
        new bootstrap.Modal(document.getElementById('authModal')).show();
    }
    
    document.querySelectorAll('input[name="auth_method"]').forEach(function(radio) {
        radio.addEventListener('change', function() {
            document.getElementById('loidInputGroup').style.display = this.value === 'loid' ? 'block' : 'none';
            document.getElementById('macInputGroup').style.display = this.value === 'mac' ? 'block' : 'none';
        });
    });
    
    function updateAuthModeFromType(select) {
        var option = select.options[select.selectedIndex];
        if (option && option.dataset.mode) {
            if (option.dataset.mode === 'router') {
                document.getElementById('authModeRouter').checked = true;
            } else {
                document.getElementById('authModeBridge').checked = true;
            }
        }
    }
    
    function setDefaultVlan(oltId, portName, vlanId) {
        fetch('?page=huawei-olt&action=set_pon_default_vlan', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'olt_id=' + oltId + '&port_name=' + encodeURIComponent(portName) + '&vlan_id=' + (vlanId || '')
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Failed to set default VLAN: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(e => alert('Error: ' + e.message));
    }
    
    function updatePortDescription(oltId, portName, description) {
        fetch('?page=huawei-olt&action=update_port_description', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'olt_id=' + oltId + '&port_name=' + encodeURIComponent(portName) + '&description=' + encodeURIComponent(description)
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                alert('Failed to update description: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(e => console.error('Error updating description:', e.message));
    }
    
    function savePortSettings(oltId, portName, portId) {
        var defaultVlan = document.getElementById('defaultVlan' + portId).value;
        var description = document.getElementById('portDesc' + portId).value;
        
        var saveBtn = event.target;
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving...';
        
        Promise.all([
            fetch('?page=huawei-olt&action=set_pon_default_vlan', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'olt_id=' + oltId + '&port_name=' + encodeURIComponent(portName) + '&vlan_id=' + (defaultVlan || '')
            }).then(r => r.json()),
            fetch('?page=huawei-olt&action=update_port_description', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'olt_id=' + oltId + '&port_name=' + encodeURIComponent(portName) + '&description=' + encodeURIComponent(description)
            }).then(r => r.json())
        ])
        .then(results => {
            var errors = results.filter(r => !r.success);
            if (errors.length > 0) {
                alert('Error: ' + (errors[0].error || 'Failed to save'));
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="bi bi-check me-1"></i> Save Changes';
            } else {
                location.reload();
            }
        })
        .catch(e => {
            alert('Error saving settings: ' + e.message);
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<i class="bi bi-check me-1"></i> Save Changes';
        });
    }
    
    function assignPortVlan(oltId, portName, portId) {
        var vlanId = document.getElementById('assignVlan' + portId).value;
        var mode = document.getElementById('assignVlanMode' + portId).value;
        
        var form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input name="action" value="assign_port_vlan"><input name="olt_id" value="' + oltId + '"><input name="port_name" value="' + portName + '"><input name="vlan_id" value="' + vlanId + '"><input name="vlan_mode" value="' + mode + '">';
        document.body.appendChild(form);
        form.submit();
    }
    
    function rebootOnu(id) {
        if (confirm('Reboot this ONU?')) {
            document.getElementById('actionType').value = 'reboot_onu';
            document.getElementById('actionOnuId').value = id;
            document.getElementById('actionForm').submit();
        }
    }
    
    function deleteOnu(id, sn) {
        if (confirm('Delete ONU ' + sn + ' from database?')) {
            document.getElementById('actionType').value = 'delete_onu';
            document.getElementById('actionId').value = id;
            document.getElementById('actionForm').submit();
        }
    }
    
    function refreshOptical(id) {
        document.getElementById('actionType').value = 'refresh_onu_optical';
        document.getElementById('actionOnuId').value = id;
        document.getElementById('actionForm').submit();
    }
    
    function getOnuFullStatus(onuId) {
        const modal = new bootstrap.Modal(document.getElementById('onuFullStatusModal'));
        const body = document.getElementById('onuFullStatusBody');
        
        body.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div><p class="mt-3">Fetching ONU status from OLT...</p></div>';
        modal.show();
        
        fetch('?page=huawei-olt', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=get_onu_full_status&onu_id=' + onuId
        })
        .then(r => {
            if (!r.ok) throw new Error('HTTP error ' + r.status);
            return r.text();
        })
        .then(text => {
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Response:', text.substring(0, 500));
                throw new Error('Invalid JSON response');
            }
        })
        .then(data => {
            if (!data.success) {
                body.innerHTML = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i>' + (data.error || 'Failed to fetch status') + '</div>';
                return;
            }
            
            const s = data.status;
            let html = '';
            
            // Quick Actions bar (only for TR-069 devices)
            if (s.source === 'tr069') {
                html += '<div class="alert alert-info d-flex justify-content-between align-items-center mb-3">';
                html += '<span><i class="bi bi-lightning-charge me-2"></i><strong>TR-069 Connected</strong> - Fast data from GenieACS</span>';
                html += '<div class="btn-group btn-group-sm">';
                html += '<button class="btn btn-primary" onclick="openWifiConfig(null, \'' + s.onu.sn + '\')"><i class="bi bi-wifi me-1"></i>WiFi</button>';
                html += '<button class="btn btn-success" onclick="openPPPoEConfig(\'' + s.onu.sn + '\')"><i class="bi bi-globe me-1"></i>WAN</button>';
                html += '<button class="btn btn-warning" onclick="tr069Reboot(\'' + s.onu.sn + '\')"><i class="bi bi-arrow-clockwise me-1"></i>Reboot</button>';
                html += '</div></div>';
            }
            
            html += '<div class="row">';
            
            // Optical Status
            html += '<div class="col-md-6 mb-3"><div class="card h-100"><div class="card-header bg-info text-white"><i class="bi bi-broadcast me-2"></i>Optical Status</div><div class="card-body"><table class="table table-sm mb-0">';
            if (s.optical) {
                html += '<tr><td>Module Type</td><td><strong>' + (s.optical.module_type || '-') + '</strong></td></tr>';
                html += '<tr><td>ONU Rx Power</td><td><strong>' + (s.optical.rx_power !== null ? s.optical.rx_power + ' dBm' : '-') + '</strong></td></tr>';
                html += '<tr><td>ONU Tx Power</td><td><strong>' + (s.optical.tx_power !== null ? s.optical.tx_power + ' dBm' : '-') + '</strong></td></tr>';
                html += '<tr><td>OLT Rx Power</td><td><strong>' + (s.optical.olt_rx_power !== null ? s.optical.olt_rx_power + ' dBm' : '-') + '</strong></td></tr>';
                html += '<tr><td>Temperature</td><td><strong>' + (s.optical.temperature !== null ? s.optical.temperature + ' C' : '-') + '</strong></td></tr>';
            } else {
                html += '<tr><td colspan="2" class="text-muted">No optical data</td></tr>';
            }
            html += '</table></div></div></div>';
            
            // ONU Details
            html += '<div class="col-md-6 mb-3"><div class="card h-100"><div class="card-header bg-primary text-white"><i class="bi bi-info-circle me-2"></i>ONU Details</div><div class="card-body"><table class="table table-sm mb-0">';
            if (s.details) {
                html += '<tr><td>Run State</td><td><span class="badge bg-' + (s.details.run_state === 'online' ? 'success' : 'secondary') + '">' + (s.details.run_state || '-') + '</span></td></tr>';
                html += '<tr><td>Control Flag</td><td>' + (s.details.control_flag || '-') + '</td></tr>';
                html += '<tr><td>Match State</td><td>' + (s.details.match_state || '-') + '</td></tr>';
                html += '<tr><td>Distance</td><td>' + (s.details.distance ? s.details.distance + ' m' : '-') + '</td></tr>';
                html += '<tr><td>Memory</td><td>' + (s.details.memory_occupation || '-') + '</td></tr>';
                html += '<tr><td>CPU</td><td>' + (s.details.cpu_occupation || '-') + '</td></tr>';
                html += '<tr><td>Temperature</td><td>' + (s.details.temperature || '-') + '</td></tr>';
                html += '<tr><td>Online Duration</td><td>' + (s.details.online_duration || '-') + '</td></tr>';
                html += '<tr><td>Last Down Cause</td><td><span class="text-danger">' + (s.details.last_down_cause || '-') + '</span></td></tr>';
                html += '<tr><td>Last Up Time</td><td>' + (s.details.last_up_time || '-') + '</td></tr>';
                html += '<tr><td>Last Down Time</td><td>' + (s.details.last_down_time || '-') + '</td></tr>';
                html += '<tr><td>Line Profile</td><td>' + (s.details.line_profile || '-') + '</td></tr>';
                html += '<tr><td>Service Profile</td><td>' + (s.details.service_profile || '-') + '</td></tr>';
                html += '<tr><td>TR-069 ACS Profile</td><td>' + (s.details.tr069_acs_profile || '-') + '</td></tr>';
            } else {
                html += '<tr><td colspan="2" class="text-muted">No details available</td></tr>';
            }
            html += '</table></div></div></div>';
            
            // WAN Interfaces with config button
            html += '<div class="col-md-6 mb-3"><div class="card h-100"><div class="card-header bg-success text-white d-flex justify-content-between align-items-center"><span><i class="bi bi-globe me-2"></i>WAN Interfaces</span>';
            if (s.source === 'tr069') {
                html += '<button class="btn btn-sm btn-light" onclick="openPPPoEConfig(\'' + s.onu.sn + '\')"><i class="bi bi-gear me-1"></i>Configure</button>';
            }
            html += '</div><div class="card-body">';
            if (s.wan && s.wan.length > 0) {
                s.wan.forEach((w, i) => {
                    html += '<div class="' + (i > 0 ? 'mt-3 pt-3 border-top' : '') + '"><strong>(' + w.index + ') ' + (w.name || 'WAN') + '</strong>';
                    html += '<table class="table table-sm mb-0 mt-1">';
                    html += '<tr><td>Service Type</td><td>' + (w.service_type || '-') + '</td></tr>';
                    html += '<tr><td>Access Type</td><td>' + (w.ipv4_access_type || '-') + '</td></tr>';
                    html += '<tr><td>Status</td><td><span class="badge bg-' + (w.ipv4_status === 'Connected' ? 'success' : 'secondary') + '">' + (w.ipv4_status || '-') + '</span></td></tr>';
                    html += '<tr><td>IP Address</td><td><strong>' + (w.ipv4_address || '-') + '</strong></td></tr>';
                    html += '<tr><td>Gateway</td><td>' + (w.default_gateway || '-') + '</td></tr>';
                    html += '<tr><td>VLAN</td><td>' + (w.manage_vlan || '-') + '</td></tr>';
                    html += '<tr><td>MAC</td><td><code>' + (w.mac_address || '-') + '</code></td></tr>';
                    html += '</table></div>';
                });
            } else {
                html += '<p class="text-muted mb-0">No WAN interfaces found</p>';
            }
            html += '</div></div></div>';
            
            // LAN Ports
            html += '<div class="col-md-6 mb-3"><div class="card h-100"><div class="card-header bg-secondary text-white"><i class="bi bi-ethernet me-2"></i>LAN Ports</div><div class="card-body">';
            if (s.lan && s.lan.length > 0) {
                html += '<table class="table table-sm mb-0"><thead><tr><th>Port</th><th>Type</th><th>Speed</th><th>Link</th></tr></thead><tbody>';
                s.lan.forEach(p => {
                    html += '<tr><td>' + p.port + '</td><td>' + p.type + '</td><td>' + (p.speed || '-') + '</td>';
                    html += '<td><span class="badge bg-' + (p.link_state === 'up' ? 'success' : 'secondary') + '">' + p.link_state + '</span></td></tr>';
                });
                html += '</tbody></table>';
            } else {
                html += '<p class="text-muted mb-0">No LAN port data</p>';
            }
            html += '</div></div></div>';
            
            // History
            html += '<div class="col-md-6 mb-3"><div class="card h-100"><div class="card-header bg-dark text-white"><i class="bi bi-clock-history me-2"></i>History</div><div class="card-body">';
            if (s.history && s.history.length > 0) {
                html += '<table class="table table-sm mb-0"><thead><tr><th>#</th><th>Up Time</th><th>Down Time</th><th>Reason</th></tr></thead><tbody>';
                s.history.forEach(h => {
                    html += '<tr><td>' + h.index + '</td><td>' + h.up_time + '</td>';
                    html += '<td>' + (h.offline_time || (h.status === 'online' ? '<span class="text-success">Currently Online</span>' : '-')) + '</td>';
                    html += '<td><span class="text-danger">' + (h.down_reason || '-') + '</span></td></tr>';
                });
                html += '</tbody></table>';
            } else {
                html += '<p class="text-muted mb-0">No history data</p>';
            }
            html += '</div></div></div>';
            
            // MAC Addresses
            html += '<div class="col-md-6 mb-3"><div class="card h-100"><div class="card-header bg-warning"><i class="bi bi-card-list me-2"></i>MAC Addresses on OLT</div><div class="card-body">';
            if (s.mac && s.mac.length > 0) {
                html += '<table class="table table-sm mb-0"><thead><tr><th>MAC</th><th>VLAN</th><th>Type</th></tr></thead><tbody>';
                s.mac.forEach(m => {
                    html += '<tr><td><code>' + m.mac + '</code></td><td>' + m.vlan + '</td><td>' + m.learn_type + '</td></tr>';
                });
                html += '</tbody></table>';
            } else {
                html += '<p class="text-muted mb-0">No MAC addresses found</p>';
            }
            html += '</div></div></div>';
            
            html += '</div>';
            body.innerHTML = html;
        })
        .catch(err => {
            body.innerHTML = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i>Error: ' + err.message + '</div>';
        });
    }
    
    function getOnuConfig(onuId) {
        const modal = new bootstrap.Modal(document.getElementById('onuConfigModal'));
        const body = document.getElementById('onuConfigBody');
        
        body.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div><p class="mt-3">Fetching configuration from OLT...</p></div>';
        modal.show();
        
        fetch('?page=huawei-olt', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=get_onu_config&onu_id=' + onuId
        })
        .then(r => {
            if (!r.ok) throw new Error('HTTP error ' + r.status);
            return r.text();
        })
        .then(text => {
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Response:', text.substring(0, 500));
                throw new Error('Invalid JSON response');
            }
        })
        .then(data => {
            if (!data.success) {
                body.innerHTML = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i>' + (data.error || 'Failed to fetch config') + '</div>';
                return;
            }
            
            const c = data.config;
            let html = '';
            
            // ONU Info header
            html += '<div class="alert alert-info small mb-3">';
            html += '<strong>ONU:</strong> ' + (c.onu.sn || '-') + ' | ';
            html += '<strong>Location:</strong> ' + c.onu.frame + '/' + c.onu.slot + '/' + c.onu.port + ' ONU ' + c.onu.onu_id + ' | ';
            html += '<strong>Name:</strong> ' + (c.onu.name || '-');
            html += '</div>';
            
            // Config script
            html += '<div class="mb-3">';
            html += '<label class="form-label fw-bold">OLT Configuration Commands:</label>';
            html += '<pre id="onuConfigText" class="bg-dark text-light p-3 rounded" style="white-space: pre-wrap; font-size: 0.85rem;">' + escapeHtml(c.script || '# No configuration found') + '</pre>';
            html += '</div>';
            
            // Raw output sections
            if (c.raw && c.raw.ont_config) {
                html += '<div class="accordion" id="rawConfigAccordion">';
                html += '<div class="accordion-item">';
                html += '<h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#rawOntConfig">Raw ONT Configuration Output</button></h2>';
                html += '<div id="rawOntConfig" class="accordion-collapse collapse" data-bs-parent="#rawConfigAccordion">';
                html += '<div class="accordion-body"><pre class="bg-secondary text-light p-2 rounded small" style="white-space: pre-wrap; max-height: 300px; overflow: auto;">' + escapeHtml(c.raw.ont_config) + '</pre></div>';
                html += '</div></div>';
                
                if (c.raw.service_ports) {
                    html += '<div class="accordion-item">';
                    html += '<h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#rawSpConfig">Raw Service-Port Output</button></h2>';
                    html += '<div id="rawSpConfig" class="accordion-collapse collapse" data-bs-parent="#rawConfigAccordion">';
                    html += '<div class="accordion-body"><pre class="bg-secondary text-light p-2 rounded small" style="white-space: pre-wrap; max-height: 300px; overflow: auto;">' + escapeHtml(c.raw.service_ports) + '</pre></div>';
                    html += '</div></div>';
                }
                html += '</div>';
            }
            
            body.innerHTML = html;
        })
        .catch(err => {
            body.innerHTML = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i>Error: ' + err.message + '</div>';
        });
    }
    
    function copyOnuConfig() {
        const configText = document.getElementById('onuConfigText');
        if (configText) {
            navigator.clipboard.writeText(configText.textContent).then(() => {
                alert('Configuration copied to clipboard!');
            }).catch(() => {
                // Fallback for older browsers
                const range = document.createRange();
                range.selectNode(configText);
                window.getSelection().removeAllRanges();
                window.getSelection().addRange(range);
                document.execCommand('copy');
                window.getSelection().removeAllRanges();
                alert('Configuration copied to clipboard!');
            });
        }
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function setCommand(cmd) {
        document.querySelector('input[name="command"]').value = cmd;
    }
    
    function openWifiConfig(deviceId, serialNumber) {
        document.getElementById('wifiDeviceId').value = deviceId || '';
        document.getElementById('wifiDeviceSn').textContent = serialNumber;
        // Close the full status modal first
        const statusModal = bootstrap.Modal.getInstance(document.getElementById('onuFullStatusModal'));
        if (statusModal) statusModal.hide();
        new bootstrap.Modal(document.getElementById('wifiConfigModal')).show();
    }
    
    function openPPPoEConfig(serialNumber) {
        // Close the full status modal first
        const statusModal = bootstrap.Modal.getInstance(document.getElementById('onuFullStatusModal'));
        if (statusModal) statusModal.hide();
        
        // Open PPPoE/WAN configuration modal
        document.getElementById('pppoeDeviceSn').textContent = serialNumber;
        document.getElementById('pppoeSerialInput').value = serialNumber;
        new bootstrap.Modal(document.getElementById('pppoeConfigModal')).show();
    }
    
    // SmartOLT-style functions
    function getOnuSWInfo(onuId) {
        const modal = new bootstrap.Modal(document.getElementById('onuFullStatusModal'));
        const title = document.getElementById('onuFullStatusTitle');
        const body = document.getElementById('onuFullStatusBody');
        
        title.textContent = 'ONU Software Information';
        body.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary"></div><p class="mt-3">Fetching software info...</p></div>';
        modal.show();
        
        fetch('?page=huawei-olt', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=get_onu_sw_info&onu_id=' + onuId
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                body.innerHTML = '<div class="alert alert-warning">' + (data.error || 'No SW info available') + '</div>';
                return;
            }
            let html = '<table class="table table-sm">';
            html += '<tr><th>Software Version</th><td>' + (data.sw_version || '-') + '</td></tr>';
            html += '<tr><th>Hardware Version</th><td>' + (data.hw_version || '-') + '</td></tr>';
            html += '<tr><th>ONU Type</th><td>' + (data.onu_type || '-') + '</td></tr>';
            html += '<tr><th>Uptime</th><td>' + (data.uptime || '-') + '</td></tr>';
            html += '</table>';
            if (data.raw_output) {
                html += '<pre class="bg-dark text-light p-2 rounded small" style="max-height: 200px; overflow: auto;">' + escapeHtml(data.raw_output) + '</pre>';
            }
            body.innerHTML = html;
        })
        .catch(err => {
            body.innerHTML = '<div class="alert alert-danger">Error: ' + err.message + '</div>';
        });
    }
    
    function getTR069Stat(onuId) {
        const modal = new bootstrap.Modal(document.getElementById('onuFullStatusModal'));
        const title = document.getElementById('onuFullStatusTitle');
        const body = document.getElementById('onuFullStatusBody');
        
        title.textContent = 'TR-069 Device Info';
        body.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary"></div><p class="mt-3">Fetching TR-069 device info from GenieACS...</p></div>';
        modal.show();
        
        fetch('?page=huawei-olt', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=get_tr069_stat&onu_id=' + onuId
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                body.innerHTML = '<div class="alert alert-warning">' + (data.error || 'No TR-069 data available') + '</div>';
                return;
            }
            
            if (!data.found_in_acs) {
                body.innerHTML = '<div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>Device not found in GenieACS. The ONU may not have connected to the ACS server yet.</div>' +
                    '<div class="text-muted small"><strong>Serial:</strong> ' + escapeHtml(data.serial || '-') + '</div>';
                return;
            }
            
            let statusBadge = data.status === 'connected' 
                ? '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Connected</span>'
                : '<span class="badge bg-warning"><i class="bi bi-clock me-1"></i>Pending</span>';
            
            let html = '<div class="mb-3">' + statusBadge + '</div>';
            
            // General Info Section
            html += '<h6 class="text-primary border-bottom pb-2 mb-3"><i class="bi bi-info-circle me-2"></i>General</h6>';
            html += '<table class="table table-sm table-borderless">';
            html += '<tr><th style="width:40%">Manufacturer</th><td>' + escapeHtml(data.manufacturer || '-') + ' <span class="text-muted">(OUI: ' + escapeHtml(data.oui || '-') + ')</span></td></tr>';
            html += '<tr><th>Model name</th><td><strong>' + escapeHtml(data.model || '-') + '</strong></td></tr>';
            html += '<tr><th>Software version</th><td><code>' + escapeHtml(data.sw_version || '-') + '</code></td></tr>';
            html += '<tr><th>Hardware version</th><td>' + escapeHtml(data.hw_version || '-') + '</td></tr>';
            html += '<tr><th>Provisioning code</th><td>' + escapeHtml(data.provisioning_code || '-') + '</td></tr>';
            html += '<tr><th>Serial number</th><td><code>' + escapeHtml(data.serial || '-') + '</code></td></tr>';
            html += '<tr><th>CPU Usage</th><td>' + escapeHtml(data.cpu_usage || '-') + '</td></tr>';
            html += '<tr><th>Used RAM</th><td>' + escapeHtml(data.ram_usage || '-') + '</td></tr>';
            html += '<tr><th>Uptime</th><td>' + escapeHtml(data.uptime || '-') + '</td></tr>';
            html += '</table>';
            
            // TR-069 Connection Info
            html += '<h6 class="text-primary border-bottom pb-2 mb-3 mt-4"><i class="bi bi-broadcast me-2"></i>TR-069 Connection</h6>';
            html += '<table class="table table-sm table-borderless">';
            html += '<tr><th style="width:40%">Management IP</th><td><code>' + escapeHtml(data.ip || '-') + '</code></td></tr>';
            html += '<tr><th>ACS URL</th><td><small class="text-break">' + escapeHtml(data.acs_url || '-') + '</small></td></tr>';
            html += '<tr><th>Last Inform</th><td>' + escapeHtml(data.last_inform || '-') + '</td></tr>';
            html += '<tr><th>Inform Interval</th><td>' + escapeHtml(data.inform_interval || '-') + ' sec</td></tr>';
            html += '</table>';
            
            // Quick Actions
            html += '<div class="mt-4 pt-3 border-top">';
            html += '<button class="btn btn-outline-primary btn-sm me-2" onclick="openTR069Config(' + onuId + ')"><i class="bi bi-gear me-1"></i>WiFi Config</button>';
            html += '<button class="btn btn-outline-info btn-sm me-2" onclick="openWANConfig(' + onuId + ')"><i class="bi bi-globe me-1"></i>WAN Config</button>';
            html += '<button class="btn btn-outline-warning btn-sm" onclick="rebootONUViaTR069(' + onuId + ')"><i class="bi bi-arrow-clockwise me-1"></i>Reboot</button>';
            html += '</div>';
            
            body.innerHTML = html;
        })
        .catch(err => {
            body.innerHTML = '<div class="alert alert-danger">Error: ' + err.message + '</div>';
        });
    }
    
    let liveInterval = null;
    
    async function fetchLiveOnuData() {
        if (typeof onuOltId === 'undefined' || typeof onuSlot === 'undefined' || onuOltId === null || onuSlot === null) {
            console.error('Missing ONU context for live data');
            return;
        }
        
        try {
            const resp = await fetch(`?page=api&action=huawei_live_onu&olt_id=${onuOltId}&frame=${onuFrame}&slot=${onuSlot}&port=${onuPort}&onu_id=${onuIdVal}&sn=${encodeURIComponent(onuSn)}`);
            const data = await resp.json();
            
            if (data.success && data.onu) {
                const onu = data.onu;
                const statusEl = document.querySelector('[data-live-status]');
                const rxEl = document.querySelector('[data-live-rx]');
                const txEl = document.querySelector('[data-live-tx]');
                
                if (statusEl) {
                    const statusClass = {'online': 'success', 'offline': 'secondary', 'los': 'danger'}[onu.status] || 'secondary';
                    statusEl.className = 'text-' + statusClass + ' fw-bold';
                    statusEl.textContent = onu.status ? onu.status.charAt(0).toUpperCase() + onu.status.slice(1) : 'Unknown';
                }
                if (rxEl && onu.rx_power !== null) {
                    rxEl.textContent = onu.rx_power.toFixed(2) + ' dBm';
                }
                if (txEl && onu.tx_power !== null) {
                    txEl.textContent = onu.tx_power.toFixed(2) + ' dBm';
                }
                
                const toast = document.createElement('div');
                toast.className = 'position-fixed bottom-0 end-0 p-3';
                toast.style.zIndex = '9999';
                toast.innerHTML = '<div class="toast show bg-success text-white"><div class="toast-body"><i class="bi bi-check-circle me-2"></i>Status: ' + (onu.status || 'Unknown') + ', RX: ' + (onu.rx_power !== null ? onu.rx_power.toFixed(1) + ' dBm' : 'N/A') + '</div></div>';
                document.body.appendChild(toast);
                setTimeout(() => toast.remove(), 3000);
            } else {
                console.error('Could not fetch live data:', data.error || 'ONU not found');
            }
        } catch (e) {
            console.error('Error fetching live data:', e);
        }
    }
    
    function toggleLiveMode() {
        const btn = document.getElementById('liveBtn');
        if (liveInterval) {
            clearInterval(liveInterval);
            liveInterval = null;
            btn.classList.remove('btn-danger');
            btn.classList.add('btn-outline-danger');
            btn.innerHTML = '<i class="bi bi-record-circle me-1"></i> LIVE!';
        } else {
            liveInterval = setInterval(fetchLiveOnuData, 5000);
            fetchLiveOnuData();
            btn.classList.remove('btn-outline-danger');
            btn.classList.add('btn-danger');
            btn.innerHTML = '<i class="bi bi-stop-circle me-1"></i> Stop LIVE';
        }
    }
    
    function configureSpeedProfile(onuId, vlanId) {
        // Open DBA profile configuration modal
        const modal = new bootstrap.Modal(document.getElementById('dbaProfileModal'));
        document.getElementById('dbaOnuId').value = onuId;
        document.getElementById('dbaVlanId').value = vlanId;
        modal.show();
    }
    
    function configureEthPort(onuId, portNum) {
        document.getElementById('ethPortOnuId').value = onuId;
        document.getElementById('ethPortNum').value = portNum;
        document.getElementById('ethPortModalLabel').textContent = 'Configure ETH Port ' + portNum;
        new bootstrap.Modal(document.getElementById('ethPortModal')).show();
    }
    
    function tr069Reboot(serialNumber) {
        if (!confirm('Reboot ONU ' + serialNumber + ' via TR-069?')) return;
        
        fetch('?page=huawei-olt', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=tr069_reboot&serial=' + encodeURIComponent(serialNumber)
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('Reboot command sent successfully!');
            } else {
                alert('Error: ' + (data.error || 'Failed to send reboot command'));
            }
        })
        .catch(err => {
            alert('Error: ' + err.message);
        });
    }
    
    function openAdminPasswordConfig(deviceId, serialNumber) {
        document.getElementById('adminPassDeviceId').value = deviceId;
        document.getElementById('adminPassDeviceSn').textContent = serialNumber;
        document.getElementById('newAdminPass').value = '';
        document.getElementById('confirmAdminPass').value = '';
        new bootstrap.Modal(document.getElementById('adminPasswordModal')).show();
    }
    
    function validatePasswordMatch() {
        const pass = document.getElementById('newAdminPass').value;
        const confirm = document.getElementById('confirmAdminPass').value;
        if (pass !== confirm) {
            alert('Passwords do not match!');
            return false;
        }
        return true;
    }
    
    function togglePassword(inputId) {
        const input = document.getElementById(inputId);
        if (input.type === 'password') {
            input.type = 'text';
        } else {
            input.type = 'password';
        }
    }
    
    // Location Management Functions
    function resetZoneForm() {
        document.getElementById('zoneAction').value = 'add_zone';
        document.getElementById('zoneId').value = '';
        document.getElementById('zoneModalTitle').textContent = 'Add Zone';
        document.getElementById('zoneName').value = '';
        document.getElementById('zoneDescription').value = '';
        document.getElementById('zoneActive').checked = true;
    }
    
    function editZone(zone) {
        document.getElementById('zoneAction').value = 'update_zone';
        document.getElementById('zoneId').value = zone.id;
        document.getElementById('zoneModalTitle').textContent = 'Edit Zone';
        document.getElementById('zoneName').value = zone.name;
        document.getElementById('zoneDescription').value = zone.description || '';
        document.getElementById('zoneActive').checked = zone.is_active;
        new bootstrap.Modal(document.getElementById('zoneModal')).show();
    }
    
    function deleteZone(id, name) {
        if (confirm('Delete zone "' + name + '"? This will also remove all subzones, apartments, and ODB units in this zone.')) {
            document.getElementById('actionType').value = 'delete_zone';
            document.getElementById('actionId').value = id;
            document.getElementById('actionForm').submit();
        }
    }
    
    function resetSubzoneForm() {
        document.getElementById('subzoneAction').value = 'add_subzone';
        document.getElementById('subzoneId').value = '';
        document.getElementById('subzoneModalTitle').textContent = 'Add Subzone';
        document.getElementById('subzoneZoneId').value = '';
        document.getElementById('subzoneName').value = '';
        document.getElementById('subzoneDescription').value = '';
        document.getElementById('subzoneActive').checked = true;
    }
    
    function editSubzone(sz) {
        document.getElementById('subzoneAction').value = 'update_subzone';
        document.getElementById('subzoneId').value = sz.id;
        document.getElementById('subzoneModalTitle').textContent = 'Edit Subzone';
        document.getElementById('subzoneZoneId').value = sz.zone_id;
        document.getElementById('subzoneName').value = sz.name;
        document.getElementById('subzoneDescription').value = sz.description || '';
        document.getElementById('subzoneActive').checked = sz.is_active;
        new bootstrap.Modal(document.getElementById('subzoneModal')).show();
    }
    
    function deleteSubzone(id, name) {
        if (confirm('Delete subzone "' + name + '"?')) {
            document.getElementById('actionType').value = 'delete_subzone';
            document.getElementById('actionId').value = id;
            document.getElementById('actionForm').submit();
        }
    }
    
    function resetApartmentForm() {
        document.getElementById('apartmentAction').value = 'add_apartment';
        document.getElementById('apartmentId').value = '';
        document.getElementById('apartmentModalTitle').textContent = 'Add Apartment / Building';
        document.getElementById('apartmentZoneId').value = '';
        document.getElementById('apartmentSubzoneId').value = '';
        document.getElementById('apartmentName').value = '';
        document.getElementById('apartmentAddress').value = '';
        document.getElementById('apartmentFloors').value = '';
        document.getElementById('apartmentUnits').value = '';
        filterApartmentSubzones();
    }
    
    function editApartment(apt) {
        document.getElementById('apartmentAction').value = 'update_apartment';
        document.getElementById('apartmentId').value = apt.id;
        document.getElementById('apartmentModalTitle').textContent = 'Edit Apartment / Building';
        document.getElementById('apartmentZoneId').value = apt.zone_id;
        filterApartmentSubzones();
        setTimeout(function() {
            document.getElementById('apartmentSubzoneId').value = apt.subzone_id || '';
        }, 100);
        document.getElementById('apartmentName').value = apt.name;
        document.getElementById('apartmentAddress').value = apt.address || '';
        document.getElementById('apartmentFloors').value = apt.floors || '';
        document.getElementById('apartmentUnits').value = apt.units_per_floor || '';
        new bootstrap.Modal(document.getElementById('apartmentModal')).show();
    }
    
    function deleteApartment(id, name) {
        if (confirm('Delete apartment "' + name + '"? This will also remove all ODB units in this apartment.')) {
            document.getElementById('actionType').value = 'delete_apartment';
            document.getElementById('actionId').value = id;
            document.getElementById('actionForm').submit();
        }
    }
    
    function filterApartmentSubzones() {
        var zoneId = document.getElementById('apartmentZoneId').value;
        var subzoneSelect = document.getElementById('apartmentSubzoneId');
        var options = subzoneSelect.querySelectorAll('option[data-zone]');
        options.forEach(function(opt) {
            opt.style.display = (!zoneId || opt.dataset.zone === zoneId) ? '' : 'none';
        });
        subzoneSelect.value = '';
    }
    
    function resetOdbForm() {
        document.getElementById('odbAction').value = 'add_odb';
        document.getElementById('odbId').value = '';
        document.getElementById('odbModalTitle').textContent = 'Add ODB Unit';
        document.getElementById('odbZoneId').value = '';
        document.getElementById('odbApartmentId').value = '';
        document.getElementById('odbCode').value = '';
        document.getElementById('odbCapacity').value = '8';
        document.getElementById('odbLocation').value = '';
        document.getElementById('odbActive').checked = true;
        filterOdbApartments();
    }
    
    function editOdb(odb) {
        document.getElementById('odbAction').value = 'update_odb';
        document.getElementById('odbId').value = odb.id;
        document.getElementById('odbModalTitle').textContent = 'Edit ODB Unit';
        document.getElementById('odbZoneId').value = odb.zone_id;
        filterOdbApartments();
        setTimeout(function() {
            document.getElementById('odbApartmentId').value = odb.apartment_id || '';
        }, 100);
        document.getElementById('odbCode').value = odb.code;
        document.getElementById('odbCapacity').value = odb.capacity;
        document.getElementById('odbLocation').value = odb.location_description || '';
        document.getElementById('odbActive').checked = odb.is_active;
        new bootstrap.Modal(document.getElementById('odbModal')).show();
    }
    
    function deleteOdb(id, code) {
        if (confirm('Delete ODB "' + code + '"?')) {
            document.getElementById('actionType').value = 'delete_odb';
            document.getElementById('actionId').value = id;
            document.getElementById('actionForm').submit();
        }
    }
    
    function filterOdbApartments() {
        var zoneId = document.getElementById('odbZoneId').value;
        var aptSelect = document.getElementById('odbApartmentId');
        var options = aptSelect.querySelectorAll('option[data-zone]');
        options.forEach(function(opt) {
            opt.style.display = (!zoneId || opt.dataset.zone === zoneId) ? '' : 'none';
        });
        aptSelect.value = '';
    }
    
    // Live update Non Auth badge from OLT Session Manager
    function updateNonAuthBadge() {
        fetch('api/olt-stats.php')
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const count = (data.unconfigured_onus || 0) + (data.discovered_onus || 0);
                    const badgeClass = count > 0 ? 'badge bg-warning badge-pulse ms-auto' : 'badge bg-secondary ms-auto';
                    const badgeText = count.toString();
                    
                    const desktopBadge = document.getElementById('nonAuthBadgeDesktop');
                    const mobileBadge = document.getElementById('nonAuthBadgeMobile');
                    
                    if (desktopBadge) {
                        desktopBadge.className = badgeClass;
                        desktopBadge.textContent = badgeText;
                    }
                    if (mobileBadge) {
                        mobileBadge.className = count > 0 ? 'badge bg-warning ms-auto' : 'badge bg-secondary ms-auto';
                        mobileBadge.textContent = badgeText;
                    }
                }
            })
            .catch(() => {});
    }
    
    // Update badge on page load and every 30 seconds
    document.addEventListener('DOMContentLoaded', function() {
        updateNonAuthBadge();
        setInterval(updateNonAuthBadge, 30000);
    });
    
    // Open TR-069 WiFi Config Modal
    function openTR069Config(onuId) {
        fetch('?page=api&action=get_onu_details&id=' + onuId)
            .then(r => r.json())
            .then(data => {
                if (data.success && data.onu) {
                    document.getElementById('wifiDeviceId').value = onuId;
                    document.getElementById('wifiDeviceSn').textContent = data.onu.serial_number || data.onu.sn || 'Unknown';
                    new bootstrap.Modal(document.getElementById('wifiConfigModal')).show();
                } else {
                    alert('Error loading ONU details');
                }
            })
            .catch(err => alert('Error: ' + err.message));
    }
    
    // Open WAN Config Modal
    function openWANConfig(onuId) {
        const modal = document.getElementById('wanConfigModal');
        const body = document.getElementById('wanConfigBody');
        document.getElementById('wanConfigOnuId').value = onuId;
        
        body.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div><p class="mt-2 text-muted">Loading WAN configuration...</p></div>';
        new bootstrap.Modal(modal).show();
        
        fetch('?page=api&action=get_wan_config&onu_id=' + onuId)
            .then(r => r.json())
            .then(data => {
                let html = '';
                
                if (data.success) {
                    const onu = data.onu || {};
                    const wans = data.wans || [];
                    
                    html += '<div class="alert alert-light border mb-3">';
                    html += '<div class="d-flex align-items-center">';
                    html += '<i class="bi bi-router fs-4 text-primary me-3"></i>';
                    html += '<div>';
                    html += '<strong>' + escapeHtml(onu.serial_number || onu.sn || 'Unknown') + '</strong>';
                    html += '<div class="small text-muted">' + escapeHtml(onu.description || onu.model || '') + '</div>';
                    html += '</div>';
                    html += '</div>';
                    html += '</div>';
                    
                    if (wans.length > 0) {
                        html += '<h6 class="text-primary mb-3"><i class="bi bi-globe me-2"></i>Configured WAN Interfaces</h6>';
                        wans.forEach((wan, idx) => {
                            const typeLabel = wan.type === 'pppoe' ? 'PPPoE' : (wan.type === 'dhcp' ? 'DHCP' : 'Static IP');
                            const statusClass = wan.connected ? 'success' : 'secondary';
                            html += '<div class="card mb-2">';
                            html += '<div class="card-body py-2">';
                            html += '<div class="d-flex justify-content-between align-items-center">';
                            html += '<div>';
                            html += '<span class="badge bg-' + statusClass + ' me-2">' + (wan.connected ? 'Connected' : 'Disconnected') + '</span>';
                            html += '<strong>' + escapeHtml(wan.name || 'WAN ' + (idx + 1)) + '</strong>';
                            html += '<span class="text-muted ms-2">(' + typeLabel + ')</span>';
                            html += '</div>';
                            html += '<div>';
                            if (wan.ip) html += '<code class="small">' + escapeHtml(wan.ip) + '</code>';
                            html += '</div>';
                            html += '</div>';
                            if (wan.vlan) html += '<div class="small text-muted mt-1">VLAN: ' + wan.vlan + '</div>';
                            html += '</div>';
                            html += '</div>';
                        });
                    } else {
                        html += '<div class="alert alert-warning">';
                        html += '<i class="bi bi-exclamation-triangle me-2"></i>';
                        html += 'No WAN interfaces configured. Use the form below to add one.';
                        html += '</div>';
                    }
                    
                    html += '<hr class="my-3">';
                    html += '<h6 class="text-primary mb-3"><i class="bi bi-plus-circle me-2"></i>Add/Update Internet WAN</h6>';
                    
                    html += '<div class="mb-3">';
                    html += '<label class="form-label">Connection Type</label>';
                    html += '<select name="wan_type" id="wanConnectionType" class="form-select" onchange="toggleWanFields()">';
                    html += '<option value="pppoe">PPPoE</option>';
                    html += '<option value="dhcp">DHCP (IPoE)</option>';
                    html += '<option value="static">Static IP</option>';
                    html += '</select>';
                    html += '</div>';
                    
                    html += '<div class="mb-3">';
                    html += '<label class="form-label">Internet VLAN</label>';
                    html += '<input type="number" name="wan_vlan" class="form-control" value="' + (onu.service_vlan || 902) + '" min="1" max="4094">';
                    html += '<div class="form-text">Service VLAN for internet traffic</div>';
                    html += '</div>';
                    
                    html += '<div id="pppoeFields">';
                    html += '<div class="mb-3">';
                    html += '<label class="form-label">PPPoE Username <span class="text-danger">*</span></label>';
                    html += '<input type="text" name="pppoe_username" class="form-control" placeholder="user@isp.com">';
                    html += '</div>';
                    html += '<div class="mb-3">';
                    html += '<label class="form-label">PPPoE Password <span class="text-danger">*</span></label>';
                    html += '<div class="input-group">';
                    html += '<input type="password" name="pppoe_password" id="wanPppoePass" class="form-control" placeholder="password">';
                    html += '<button type="button" class="btn btn-outline-secondary" onclick="togglePassword(\'wanPppoePass\')"><i class="bi bi-eye"></i></button>';
                    html += '</div>';
                    html += '</div>';
                    html += '</div>';
                    
                    html += '<div id="staticFields" class="d-none">';
                    html += '<div class="row">';
                    html += '<div class="col-md-6 mb-3">';
                    html += '<label class="form-label">IP Address</label>';
                    html += '<input type="text" name="static_ip" class="form-control" placeholder="192.168.1.100">';
                    html += '</div>';
                    html += '<div class="col-md-6 mb-3">';
                    html += '<label class="form-label">Subnet Mask</label>';
                    html += '<input type="text" name="static_mask" class="form-control" value="255.255.255.0">';
                    html += '</div>';
                    html += '</div>';
                    html += '<div class="row">';
                    html += '<div class="col-md-6 mb-3">';
                    html += '<label class="form-label">Gateway</label>';
                    html += '<input type="text" name="static_gateway" class="form-control" placeholder="192.168.1.1">';
                    html += '</div>';
                    html += '<div class="col-md-6 mb-3">';
                    html += '<label class="form-label">DNS Server</label>';
                    html += '<input type="text" name="static_dns" class="form-control" value="8.8.8.8">';
                    html += '</div>';
                    html += '</div>';
                    html += '</div>';
                    
                    html += '<div class="form-check mb-3">';
                    html += '<input type="checkbox" name="nat_enabled" class="form-check-input" id="wanNatEnabled" checked>';
                    html += '<label class="form-check-label" for="wanNatEnabled">Enable NAT</label>';
                    html += '</div>';
                    
                } else {
                    html += '<div class="alert alert-danger">';
                    html += '<i class="bi bi-exclamation-triangle me-2"></i>';
                    html += (data.error || 'Failed to load WAN configuration');
                    html += '</div>';
                    html += '<p class="text-muted">You can still configure WAN manually:</p>';
                    
                    html += '<div class="mb-3">';
                    html += '<label class="form-label">Connection Type</label>';
                    html += '<select name="wan_type" id="wanConnectionType" class="form-select" onchange="toggleWanFields()">';
                    html += '<option value="pppoe">PPPoE</option>';
                    html += '<option value="dhcp">DHCP (IPoE)</option>';
                    html += '<option value="static">Static IP</option>';
                    html += '</select>';
                    html += '</div>';
                    
                    html += '<div class="mb-3">';
                    html += '<label class="form-label">Internet VLAN</label>';
                    html += '<input type="number" name="wan_vlan" class="form-control" value="902" min="1" max="4094">';
                    html += '</div>';
                    
                    html += '<div id="pppoeFields">';
                    html += '<div class="mb-3">';
                    html += '<label class="form-label">PPPoE Username</label>';
                    html += '<input type="text" name="pppoe_username" class="form-control" placeholder="user@isp.com">';
                    html += '</div>';
                    html += '<div class="mb-3">';
                    html += '<label class="form-label">PPPoE Password</label>';
                    html += '<input type="password" name="pppoe_password" class="form-control">';
                    html += '</div>';
                    html += '</div>';
                    
                    html += '<div id="staticFields" class="d-none">';
                    html += '<div class="mb-3"><label class="form-label">IP Address</label><input type="text" name="static_ip" class="form-control"></div>';
                    html += '<div class="mb-3"><label class="form-label">Gateway</label><input type="text" name="static_gateway" class="form-control"></div>';
                    html += '</div>';
                }
                
                body.innerHTML = html;
            })
            .catch(err => {
                body.innerHTML = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i>Error: ' + err.message + '</div>';
            });
    }
    
    function toggleWanFields() {
        const type = document.getElementById('wanConnectionType').value;
        const pppoeFields = document.getElementById('pppoeFields');
        const staticFields = document.getElementById('staticFields');
        
        if (pppoeFields) pppoeFields.classList.toggle('d-none', type !== 'pppoe');
        if (staticFields) staticFields.classList.toggle('d-none', type !== 'static');
    }
    
    function rebootONUViaTR069(onuId) {
        if (!confirm('Reboot this ONU via TR-069?')) return;
        
        fetch('?page=api&action=tr069_reboot_onu&onu_id=' + onuId, { method: 'POST' })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Reboot command sent successfully!');
                } else {
                    alert('Error: ' + (data.error || 'Failed to send reboot command'));
                }
            })
            .catch(err => alert('Error: ' + err.message));
    }
    </script>
    
    <!-- WAN Configuration Modal -->
    <div class="modal fade" id="wanConfigModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="post" id="wanConfigForm">
                    <input type="hidden" name="action" value="configure_wan_tr069">
                    <input type="hidden" name="onu_id" id="wanConfigOnuId">
                    <div class="modal-header bg-info bg-opacity-10 border-bottom">
                        <h5 class="modal-title text-info"><i class="bi bi-globe me-2"></i>WAN Configuration</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" id="wanConfigBody">
                        <div class="text-center py-4">
                            <div class="spinner-border text-primary"></div>
                            <p class="mt-2 text-muted">Loading...</p>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-info">
                            <i class="bi bi-check-circle me-1"></i> Apply WAN Configuration
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
