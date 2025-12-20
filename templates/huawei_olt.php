<?php
require_once __DIR__ . '/../src/HuaweiOLT.php';
$huaweiOLT = new \App\HuaweiOLT($db);

$view = $_GET['view'] ?? 'dashboard';
$oltId = isset($_GET['olt_id']) ? (int)$_GET['olt_id'] : null;
$action = $_POST['action'] ?? null;
$message = '';
$messageType = '';

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
                $result = $huaweiOLT->testConnection((int)$_POST['id']);
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'danger';
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
                $huaweiOLT->deleteONU((int)$_POST['id']);
                $message = 'ONU deleted from database';
                $messageType = 'success';
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
                $onuId = (int)$_POST['onu_id'];
                $profileId = (int)$_POST['profile_id'];
                $authMethod = $_POST['auth_method'] ?? 'sn';
                $loid = $_POST['loid'] ?? '';
                $loidPassword = $_POST['loid_password'] ?? '';
                $description = $_POST['description'] ?? '';
                $macAddress = $_POST['mac_address'] ?? '';
                
                // Update ONU record with description and/or MAC address before authorization
                $updateFields = [];
                if (!empty($description)) {
                    $updateFields['description'] = $description;
                }
                if (!empty($macAddress)) {
                    $updateFields['mac_address'] = $macAddress;
                }
                if (!empty($updateFields)) {
                    $huaweiOLT->updateONU($onuId, $updateFields);
                }
                
                // Authorize the ONU with the selected authentication method
                $result = $huaweiOLT->authorizeONU($onuId, $profileId, $authMethod, $loid, $loidPassword);
                $message = $result['message'] ?? ($result['success'] ? 'ONU authorized using ' . strtoupper($authMethod) . ' authentication' : 'Authorization failed');
                $messageType = $result['success'] ? 'success' : 'danger';
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
                    $message = "Location sync: Updated {$result['updated']}/{$result['db_total']} DB ONUs. SNMP found {$result['snmp_total']} ONUs.";
                    if ($result['updated'] == 0 && !empty($result['sample_snmp']) && !empty($result['sample_db'])) {
                        $snmpSamples = implode(', ', $result['sample_snmp']);
                        $dbSamples = implode(', ', $result['sample_db']);
                        $message .= " DEBUG: SNMP serials=[{$snmpSamples}] vs DB serials=[{$dbSamples}]";
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
                $result = $genieacs->rebootDevice($_POST['device_id']);
                $message = $result['success'] ? 'Reboot command sent' : ($result['error'] ?? 'Reboot failed');
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
            case 'tr069_refresh':
                require_once __DIR__ . '/../src/GenieACS.php';
                $genieacs = new \App\GenieACS($db);
                $result = $genieacs->refreshDevice($_POST['device_id']);
                $message = $result['success'] ? 'Refresh task created' : ($result['error'] ?? 'Refresh failed');
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
                $result = $genieacs->factoryReset($_POST['device_id']);
                $message = $result['success'] ? 'Factory reset command sent' : ($result['error'] ?? 'Reset failed');
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
            case 'create_vlan':
                $result = $huaweiOLT->createVLAN(
                    (int)$_POST['olt_id'],
                    (int)$_POST['vlan_id'],
                    $_POST['description'] ?? '',
                    $_POST['vlan_type'] ?? 'smart'
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
            case 'refresh_all_optical':
                $result = $huaweiOLT->refreshAllONUOptical((int)$_POST['olt_id']);
                if ($result['success']) {
                    $message = "Refreshed optical data for {$result['refreshed']}/{$result['total']} ONUs";
                    $messageType = 'success';
                } else {
                    $message = $result['error'] ?? 'Failed to refresh optical data';
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
            case 'configure_onu_service':
                $config = [];
                if (!empty($_POST['ip_mode'])) {
                    $config['ip_mode'] = $_POST['ip_mode'];
                    $config['vlan_id'] = (int)($_POST['mgmt_vlan'] ?? 69);
                    $config['vlan_priority'] = (int)($_POST['vlan_priority'] ?? 0);
                }
                if (!empty($_POST['service_vlan'])) {
                    $config['service_vlan'] = (int)$_POST['service_vlan'];
                    $config['gem_port'] = (int)($_POST['gem_port'] ?? 1);
                    $config['rx_traffic_table'] = (int)($_POST['rx_traffic_table'] ?? 6);
                    $config['tx_traffic_table'] = (int)($_POST['tx_traffic_table'] ?? 6);
                }
                if (!empty($_POST['traffic_table_index'])) {
                    $config['traffic_table_index'] = (int)$_POST['traffic_table_index'];
                }
                $result = $huaweiOLT->configureONUService((int)$_POST['onu_id'], $config);
                $message = $result['success'] ? "ONU service configured successfully" : ($result['message'] ?? 'Configuration failed');
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

if ($view === 'onus' || $view === 'dashboard') {
    $onuFilters = [];
    if ($oltId) $onuFilters['olt_id'] = $oltId;
    if (!empty($_GET['status'])) $onuFilters['status'] = $_GET['status'];
    if (!empty($_GET['search'])) $onuFilters['search'] = $_GET['search'];
    if (isset($_GET['unconfigured'])) $onuFilters['is_authorized'] = false;
    $onus = $huaweiOLT->getONUs($onuFilters);
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

$currentOnu = null;
if ($view === 'onu_detail' && isset($_GET['onu_id'])) {
    $currentOnu = $huaweiOLT->getONU((int)$_GET['onu_id']);
    if (!$currentOnu) {
        header('Location: ?page=huawei-olt&view=onus');
        exit;
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
    <title>Huawei OLT Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .sidebar { background: linear-gradient(180deg, #1a237e 0%, #283593 100%); min-height: 100vh; }
        .sidebar .nav-link { color: rgba(255,255,255,0.8); padding: 0.75rem 1rem; border-radius: 0.5rem; margin: 0.25rem 0.5rem; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background: rgba(255,255,255,0.15); color: #fff; }
        .sidebar .nav-link i { width: 24px; }
        .stat-card { border-radius: 1rem; border: none; transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-2px); }
        .stat-icon { width: 48px; height: 48px; border-radius: 0.75rem; display: flex; align-items: center; justify-content: center; }
        .table-hover tbody tr:hover { background-color: rgba(0,0,0,0.02); }
        .badge-online { background-color: #28a745; }
        .badge-offline { background-color: #6c757d; }
        .badge-los { background-color: #dc3545; }
        .badge-power-fail { background-color: #fd7e14; }
        .olt-card { border-left: 4px solid #1a237e; }
        .olt-card.offline { border-left-color: #dc3545; }
        .brand-title { font-size: 1.25rem; font-weight: 700; color: #fff; }
        .signal-good { color: #28a745; }
        .signal-warning { color: #ffc107; }
        .signal-critical { color: #dc3545; }
        
        /* Loading overlay styles */
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        .loading-overlay.active { display: flex; }
        .loading-spinner-container {
            background: white;
            padding: 2rem 3rem;
            border-radius: 1rem;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #e9ecef;
            border-top-color: #1a237e;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .loading-text {
            color: #1a237e;
            font-weight: 500;
            font-size: 1.1rem;
        }
        .btn-sync { position: relative; }
        .btn-sync .spinner-border { display: none; }
        .btn-sync.syncing .spinner-border { display: inline-block; }
        .btn-sync.syncing .btn-text { display: none; }
    </style>
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
    
    <div class="d-flex">
        <div class="sidebar d-flex flex-column p-3" style="width: 260px;">
            <a href="?page=dashboard" class="text-decoration-none text-warning small mb-2 px-2">
                <i class="bi bi-arrow-left me-1"></i> Back to CRM
            </a>
            <div class="d-flex align-items-center mb-3 px-2">
                <i class="bi bi-router fs-3 text-white me-2"></i>
                <span class="brand-title">Huawei OLT</span>
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
                <a class="nav-link <?= isset($_GET['unconfigured']) ? 'active' : '' ?>" href="?page=huawei-olt&view=onus&unconfigured=1">
                    <i class="bi bi-hourglass-split me-2"></i> Pending Authorization
                    <?php if ($stats['unconfigured_onus'] > 0): ?>
                    <span class="badge bg-warning ms-auto"><?= $stats['unconfigured_onus'] ?></span>
                    <?php endif; ?>
                </a>
                <a class="nav-link <?= $view === 'profiles' ? 'active' : '' ?>" href="?page=huawei-olt&view=profiles">
                    <i class="bi bi-sliders me-2"></i> Service Profiles
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
                <a class="nav-link <?= $view === 'templates' ? 'active' : '' ?>" href="?page=huawei-olt&view=templates">
                    <i class="bi bi-file-earmark-code me-2"></i> Service Templates
                </a>
                <a class="nav-link <?= $view === 'cli_generator' ? 'active' : '' ?>" href="?page=huawei-olt&view=cli_generator">
                    <i class="bi bi-code-square me-2"></i> CLI Script Generator
                </a>
                <hr class="my-2 border-light opacity-25">
                <a class="nav-link <?= $view === 'tr069' ? 'active' : '' ?>" href="?page=huawei-olt&view=tr069">
                    <i class="bi bi-gear-wide-connected me-2"></i> TR-069 / ACS
                </a>
                <a class="nav-link <?= $view === 'settings' ? 'active' : '' ?>" href="?page=huawei-olt&view=settings">
                    <i class="bi bi-gear me-2"></i> Settings
                </a>
            </nav>
        </div>
        
        <div class="flex-grow-1 p-4">
            <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <?php if ($view === 'dashboard'): ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0"><i class="bi bi-speedometer2 me-2"></i>Dashboard</h4>
                <button class="btn btn-outline-primary" onclick="location.reload()">
                    <i class="bi bi-arrow-clockwise me-1"></i> Refresh
                </button>
            </div>
            
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card stat-card shadow-sm">
                        <div class="card-body d-flex align-items-center">
                            <div class="stat-icon bg-primary bg-opacity-10 text-primary me-3">
                                <i class="bi bi-hdd-rack fs-4"></i>
                            </div>
                            <div>
                                <div class="text-muted small">Total OLTs</div>
                                <div class="fs-4 fw-bold"><?= $stats['active_olts'] ?>/<?= $stats['total_olts'] ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card shadow-sm">
                        <div class="card-body d-flex align-items-center">
                            <div class="stat-icon bg-success bg-opacity-10 text-success me-3">
                                <i class="bi bi-wifi fs-4"></i>
                            </div>
                            <div>
                                <div class="text-muted small">Online ONUs</div>
                                <div class="fs-4 fw-bold text-success"><?= $stats['online_onus'] ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card shadow-sm">
                        <div class="card-body d-flex align-items-center">
                            <div class="stat-icon bg-danger bg-opacity-10 text-danger me-3">
                                <i class="bi bi-wifi-off fs-4"></i>
                            </div>
                            <div>
                                <div class="text-muted small">Offline / LOS</div>
                                <div class="fs-4 fw-bold text-danger"><?= $stats['offline_onus'] + $stats['los_onus'] ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card shadow-sm">
                        <div class="card-body d-flex align-items-center">
                            <div class="stat-icon bg-warning bg-opacity-10 text-warning me-3">
                                <i class="bi bi-question-circle fs-4"></i>
                            </div>
                            <div>
                                <div class="text-muted small">Pending Auth</div>
                                <div class="fs-4 fw-bold text-warning"><?= $stats['unconfigured_onus'] ?></div>
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
                    <p class="text-muted">Add your first Huawei OLT device to start managing your fiber network.</p>
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
                                    <button type="submit" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-plug me-1"></i> Test
                                    </button>
                                </form>
                                <button class="btn btn-sm btn-outline-secondary" onclick="editOlt(<?= htmlspecialchars(json_encode($olt)) ?>)">
                                    <i class="bi bi-pencil me-1"></i> Edit
                                </button>
                                <a href="?page=huawei-olt&view=olt_detail&olt_id=<?= $olt['id'] ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-gear me-1"></i> Manage
                                </a>
                                <a href="?page=huawei-olt&view=onus&olt_id=<?= $olt['id'] ?>" class="btn btn-sm btn-outline-info">
                                    <i class="bi bi-diagram-3 me-1"></i> ONUs
                                </a>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="action" value="sync_onus_snmp">
                                    <input type="hidden" name="olt_id" value="<?= $olt['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-success" title="Sync ONUs via SNMP">
                                        <i class="bi bi-arrow-repeat"></i>
                                    </button>
                                </form>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="action" value="discover_unconfigured">
                                    <input type="hidden" name="olt_id" value="<?= $olt['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-warning" title="Discover Unsynced ONUs">
                                        <i class="bi bi-search"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <?php elseif ($view === 'onus'): ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0">
                    <i class="bi bi-<?= isset($_GET['unconfigured']) ? 'hourglass-split' : 'check-circle' ?> me-2"></i>
                    <?= isset($_GET['unconfigured']) ? 'Pending Authorization' : 'Authorized ONUs' ?>
                </h4>
                <div class="d-flex gap-2">
                    <form class="d-flex gap-2" method="get">
                        <input type="hidden" name="page" value="huawei-olt">
                        <input type="hidden" name="view" value="onus">
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
                    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#onuModal" onclick="resetOnuForm()">
                        <i class="bi bi-plus-circle me-1"></i> Add ONU
                    </button>
                    <?php if ($oltId): ?>
                    <div class="btn-group">
                        <form method="post" class="d-inline">
                            <input type="hidden" name="action" value="sync_onus_snmp">
                            <input type="hidden" name="olt_id" value="<?= $oltId ?>">
                            <button type="submit" class="btn btn-info btn-sm" onclick="return confirm('Sync all authorized ONUs from OLT via SNMP?')">
                                <i class="bi bi-arrow-repeat me-1"></i> Sync ONUs
                            </button>
                        </form>
                        <form method="post" class="d-inline">
                            <input type="hidden" name="action" value="sync_onu_locations">
                            <input type="hidden" name="olt_id" value="<?= $oltId ?>">
                            <button type="submit" class="btn btn-secondary btn-sm" onclick="return confirm('Fix ONU location data (frame/slot/port/onu_id) from SNMP? This corrects SmartOLT-imported ONUs.')">
                                <i class="bi bi-geo-alt me-1"></i> Fix Locations
                            </button>
                        </form>
                        <form method="post" class="d-inline">
                            <input type="hidden" name="action" value="refresh_all_optical">
                            <input type="hidden" name="olt_id" value="<?= $oltId ?>">
                            <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('Refresh optical power for all ONUs? This may take a while.')">
                                <i class="bi bi-reception-4 me-1"></i> Refresh Power
                            </button>
                        </form>
                        <form method="post" class="d-inline">
                            <input type="hidden" name="action" value="discover_unconfigured">
                            <input type="hidden" name="olt_id" value="<?= $oltId ?>">
                            <button type="submit" class="btn btn-warning btn-sm">
                                <i class="bi bi-search me-1"></i> Discover Unsynced
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <?php if (empty($onus)): ?>
                    <div class="p-4 text-center text-muted">
                        <i class="bi bi-inbox fs-1 mb-2 d-block"></i>
                        No ONUs found
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Serial Number</th>
                                    <th>Name</th>
                                    <th>OLT / Port</th>
                                    <th>Status</th>
                                    <th>Sync</th>
                                    <th>Signal (RX/TX)</th>
                                    <th>Customer</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($onus as $onu): ?>
                                <tr>
                                    <td><code><?= htmlspecialchars($onu['sn']) ?></code></td>
                                    <td><?= htmlspecialchars($onu['name'] ?: '-') ?></td>
                                    <td>
                                        <span class="text-muted"><?= htmlspecialchars($onu['olt_name'] ?? '-') ?></span>
                                        <br><small><?= $onu['frame'] ?>/<?= $onu['slot'] ?>/<?= $onu['port'] ?> : <?= $onu['onu_id'] ?? '-' ?></small>
                                    </td>
                                    <td>
                                        <?php
                                        $statusClass = ['online' => 'success', 'offline' => 'secondary', 'los' => 'danger', 'power_fail' => 'warning'];
                                        ?>
                                        <span class="badge bg-<?= $statusClass[$onu['status']] ?? 'secondary' ?>">
                                            <?= ucfirst($onu['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($onu['is_authorized']): ?>
                                        <span class="badge bg-success" title="Synced with OLT"><i class="bi bi-check-circle me-1"></i>Synced</span>
                                        <?php else: ?>
                                        <span class="badge bg-warning" title="Not synced with OLT"><i class="bi bi-exclamation-circle me-1"></i>Pending</span>
                                        <?php endif; ?>
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
                                        <span class="signal-<?= $rxClass ?>" title="RX Power"><?= $rx !== null ? number_format($rx, 1) : '-' ?></span>
                                        / <span title="TX Power"><?= $tx !== null ? number_format($tx, 1) : '-' ?></span> dBm
                                        <?php if (!empty($onu['optical_updated_at'])): ?>
                                        <br><small class="text-muted"><?= date('M j H:i', strtotime($onu['optical_updated_at'])) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($onu['customer_name'] ?? '-') ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <?php if (!$onu['is_authorized']): ?>
                                            <button class="btn btn-success" onclick="authorizeOnu(<?= $onu['id'] ?>, '<?= htmlspecialchars($onu['sn']) ?>', <?= isset($onu['slot']) && $onu['slot'] !== null ? $onu['slot'] : 'null' ?>, <?= isset($onu['port']) && $onu['port'] !== null ? $onu['port'] : 'null' ?>)" title="Authorize">
                                                <i class="bi bi-check-circle"></i>
                                            </button>
                                            <?php else: ?>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="action" value="refresh_onu_optical">
                                                <input type="hidden" name="onu_id" value="<?= $onu['id'] ?>">
                                                <button type="submit" class="btn btn-outline-info" title="Refresh Optical Power">
                                                    <i class="bi bi-reception-4"></i>
                                                </button>
                                            </form>
                                            <button class="btn btn-outline-primary" onclick="rebootOnu(<?= $onu['id'] ?>)" title="Reboot">
                                                <i class="bi bi-arrow-clockwise"></i>
                                            </button>
                                            <?php endif; ?>
                                            <a href="?page=huawei-olt&view=onu_detail&onu_id=<?= $onu['id'] ?>" class="btn btn-outline-info" title="Configure">
                                                <i class="bi bi-gear"></i>
                                            </a>
                                            <button class="btn btn-outline-secondary" onclick="refreshOptical(<?= $onu['id'] ?>)" title="Refresh Signal">
                                                <i class="bi bi-reception-4"></i>
                                            </button>
                                            <button class="btn btn-outline-danger" onclick="deleteOnu(<?= $onu['id'] ?>, '<?= htmlspecialchars($onu['sn']) ?>')" title="Delete">
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
            
            <?php elseif ($view === 'onu_detail' && $currentOnu): ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0">
                    <i class="bi bi-router me-2"></i>
                    ONU Configuration: <?= htmlspecialchars($currentOnu['sn']) ?>
                </h4>
                <a href="?page=huawei-olt&view=onus" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Back to Authorized ONUs
                </a>
            </div>
            
            <?php if (!empty($message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-primary text-white">
                            <i class="bi bi-info-circle me-2"></i>ONU Information
                        </div>
                        <div class="card-body">
                            <form method="post">
                                <input type="hidden" name="action" value="update_onu">
                                <input type="hidden" name="id" value="<?= $currentOnu['id'] ?>">
                                
                                <div class="row mb-3">
                                    <div class="col-6">
                                        <label class="form-label">Serial Number</label>
                                        <input type="text" class="form-control" value="<?= htmlspecialchars($currentOnu['sn']) ?>" readonly>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">OLT</label>
                                        <input type="text" class="form-control" value="<?= htmlspecialchars($currentOnu['olt_name'] ?? 'Unknown') ?>" readonly>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Name / Description</label>
                                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($currentOnu['name'] ?? '') ?>" placeholder="Customer name or location">
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-3">
                                        <label class="form-label">Frame</label>
                                        <input type="number" name="frame" class="form-control" value="<?= $currentOnu['frame'] ?? 0 ?>">
                                    </div>
                                    <div class="col-3">
                                        <label class="form-label">Slot</label>
                                        <input type="number" name="slot" class="form-control" value="<?= $currentOnu['slot'] ?? '' ?>">
                                    </div>
                                    <div class="col-3">
                                        <label class="form-label">Port</label>
                                        <input type="number" name="port" class="form-control" value="<?= $currentOnu['port'] ?? '' ?>">
                                    </div>
                                    <div class="col-3">
                                        <label class="form-label">ONU ID</label>
                                        <input type="number" name="onu_id" class="form-control" value="<?= $currentOnu['onu_id'] ?? '' ?>">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Link to Customer</label>
                                    <select name="customer_id" class="form-select">
                                        <option value="">-- Not Linked --</option>
                                        <?php foreach ($customers as $cust): ?>
                                        <option value="<?= $cust['id'] ?>" <?= ($currentOnu['customer_id'] == $cust['id']) ? 'selected' : '' ?>><?= htmlspecialchars($cust['name']) ?> (<?= $cust['phone'] ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Service Profile</label>
                                    <select name="service_profile_id" class="form-select">
                                        <option value="">-- None --</option>
                                        <?php foreach ($profiles as $profile): ?>
                                        <option value="<?= $profile['id'] ?>" <?= ($currentOnu['service_profile_id'] == $profile['id']) ? 'selected' : '' ?>><?= htmlspecialchars($profile['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-lg me-1"></i> Save Changes
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-info text-white">
                            <i class="bi bi-activity me-2"></i>Status & Signal
                        </div>
                        <div class="card-body">
                            <div class="row text-center mb-4">
                                <div class="col-4">
                                    <div class="h6 text-muted">Status</div>
                                    <?php
                                    $statusClass = ['online' => 'success', 'offline' => 'secondary', 'los' => 'danger', 'power_fail' => 'warning'];
                                    ?>
                                    <span class="badge bg-<?= $statusClass[$currentOnu['status']] ?? 'secondary' ?> fs-6">
                                        <?= ucfirst($currentOnu['status'] ?? 'Unknown') ?>
                                    </span>
                                </div>
                                <div class="col-4">
                                    <div class="h6 text-muted">RX Power</div>
                                    <?php
                                    $rx = $currentOnu['rx_power'];
                                    $rxClass = 'success';
                                    if ($rx !== null) {
                                        if ($rx <= -28) $rxClass = 'danger';
                                        elseif ($rx <= -25) $rxClass = 'warning';
                                    }
                                    ?>
                                    <span class="text-<?= $rxClass ?> fw-bold"><?= $rx !== null ? number_format($rx, 1) . ' dBm' : 'N/A' ?></span>
                                </div>
                                <div class="col-4">
                                    <div class="h6 text-muted">TX Power</div>
                                    <span class="fw-bold"><?= $currentOnu['tx_power'] !== null ? number_format($currentOnu['tx_power'], 1) . ' dBm' : 'N/A' ?></span>
                                </div>
                            </div>
                            
                            <div class="row text-center">
                                <div class="col-6">
                                    <div class="h6 text-muted">Authorization</div>
                                    <?php if ($currentOnu['is_authorized']): ?>
                                    <span class="badge bg-success fs-6">Authorized</span>
                                    <?php else: ?>
                                    <span class="badge bg-warning fs-6">Pending</span>
                                    <?php endif; ?>
                                </div>
                                <div class="col-6">
                                    <div class="h6 text-muted">Distance</div>
                                    <span class="fw-bold"><?= $currentOnu['distance'] ? $currentOnu['distance'] . ' m' : 'N/A' ?></span>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <div class="d-flex gap-2 justify-content-center">
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="action" value="refresh_onu_optical">
                                    <input type="hidden" name="onu_id" value="<?= $currentOnu['id'] ?>">
                                    <button type="submit" class="btn btn-outline-info">
                                        <i class="bi bi-arrow-repeat me-1"></i> Refresh Signal
                                    </button>
                                </form>
                                <?php if ($currentOnu['is_authorized']): ?>
                                <form method="post" class="d-inline" onsubmit="return confirm('Reboot this ONU?')">
                                    <input type="hidden" name="action" value="reboot_onu">
                                    <input type="hidden" name="onu_id" value="<?= $currentOnu['id'] ?>">
                                    <button type="submit" class="btn btn-outline-warning">
                                        <i class="bi bi-arrow-clockwise me-1"></i> Reboot ONU
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-secondary text-white">
                            <i class="bi bi-terminal me-2"></i>OLT Commands
                        </div>
                        <div class="card-body">
                            <p class="text-muted small mb-2">Copy these commands to configure this ONU on the Huawei OLT:</p>
                            <pre class="bg-dark text-light p-3 rounded small" style="white-space: pre-wrap;">interface gpon 0/<?= $currentOnu['slot'] ?? '0' ?>/<?= $currentOnu['port'] ?? '0' ?>
ont add <?= $currentOnu['port'] ?? '0' ?> <?= $currentOnu['onu_id'] ?? '0' ?> sn-auth "<?= $currentOnu['sn'] ?>" omci ont-lineprofile-id <?= $currentOnu['line_profile'] ?: '10' ?> ont-srvprofile-id <?= $currentOnu['srv_profile'] ?: '10' ?> desc "<?= $currentOnu['name'] ?: 'Customer' ?>"
quit</pre>
                            <button class="btn btn-sm btn-outline-secondary" onclick="navigator.clipboard.writeText(this.previousElementSibling.textContent); alert('Copied!')">
                                <i class="bi bi-clipboard me-1"></i> Copy Commands
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-warning text-dark">
                            <i class="bi bi-tools me-2"></i>Remote Troubleshooting
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <form method="post" class="d-inline" onsubmit="return confirm('Reset this ONU configuration? The ONU will temporarily go offline.')">
                                    <input type="hidden" name="action" value="reset_onu_config">
                                    <input type="hidden" name="onu_id" value="<?= $currentOnu['id'] ?>">
                                    <button type="submit" class="btn btn-outline-warning w-100">
                                        <i class="bi bi-arrow-counterclockwise me-2"></i>Reset ONU Configuration
                                    </button>
                                </form>
                                
                                <form method="post" class="d-inline" onsubmit="return confirm('Reboot this ONU? It will go offline temporarily.')">
                                    <input type="hidden" name="action" value="reboot_onu">
                                    <input type="hidden" name="onu_id" value="<?= $currentOnu['id'] ?>">
                                    <button type="submit" class="btn btn-outline-primary w-100">
                                        <i class="bi bi-arrow-clockwise me-2"></i>Reboot ONU
                                    </button>
                                </form>
                                
                                <form method="post" class="d-inline" onsubmit="return confirm('WARNING: Delete this ONU from the OLT? Customer will lose connection!')">
                                    <input type="hidden" name="action" value="delete_onu_olt">
                                    <input type="hidden" name="onu_id" value="<?= $currentOnu['id'] ?>">
                                    <button type="submit" class="btn btn-outline-danger w-100">
                                        <i class="bi bi-trash me-2"></i>Delete from OLT
                                    </button>
                                </form>
                            </div>
                            
                            <hr>
                            
                            <h6 class="mb-3"><i class="bi bi-pencil me-2"></i>Update Description on OLT</h6>
                            <form method="post" class="row g-2">
                                <input type="hidden" name="action" value="update_onu_description">
                                <input type="hidden" name="onu_id" value="<?= $currentOnu['id'] ?>">
                                <div class="col-8">
                                    <input type="text" name="description" class="form-control form-control-sm" 
                                           value="<?= htmlspecialchars($currentOnu['description'] ?? '') ?>" 
                                           placeholder="Customer name or location" maxlength="64">
                                </div>
                                <div class="col-4">
                                    <button type="submit" class="btn btn-primary btn-sm w-100">Update</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-success text-white">
                            <i class="bi bi-sliders me-2"></i>Service Configuration
                        </div>
                        <div class="card-body">
                            <form method="post">
                                <input type="hidden" name="action" value="configure_onu_service">
                                <input type="hidden" name="onu_id" value="<?= $currentOnu['id'] ?>">
                                
                                <div class="row mb-3">
                                    <div class="col-6">
                                        <label class="form-label small">IP Mode</label>
                                        <select name="ip_mode" class="form-select form-select-sm">
                                            <option value="">-- No Change --</option>
                                            <option value="dhcp">DHCP</option>
                                            <option value="static">Static</option>
                                            <option value="pppoe">PPPoE</option>
                                        </select>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small">Management VLAN</label>
                                        <input type="number" name="mgmt_vlan" class="form-control form-control-sm" placeholder="e.g., 69">
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-6">
                                        <label class="form-label small">Service VLAN</label>
                                        <input type="number" name="service_vlan" class="form-control form-control-sm" placeholder="e.g., 100">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small">GEM Port</label>
                                        <input type="number" name="gem_port" class="form-control form-control-sm" value="1" min="1" max="8">
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-6">
                                        <label class="form-label small">RX Traffic Table</label>
                                        <input type="number" name="rx_traffic_table" class="form-control form-control-sm" placeholder="Index (e.g., 6)">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small">TX Traffic Table</label>
                                        <input type="number" name="tx_traffic_table" class="form-control form-control-sm" placeholder="Index (e.g., 6)">
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-success w-100" onclick="return confirm('Apply service configuration?')">
                                    <i class="bi bi-check-lg me-1"></i> Apply Configuration
                                </button>
                            </form>
                            
                            <hr>
                            
                            <h6 class="mb-3"><i class="bi bi-arrow-up-circle me-2"></i>Change Service Profile (Plan Upgrade)</h6>
                            <form method="post" onsubmit="return confirm('Change service profile? The ONU will be re-provisioned.')">
                                <input type="hidden" name="action" value="change_onu_profile">
                                <input type="hidden" name="onu_id" value="<?= $currentOnu['id'] ?>">
                                <div class="row g-2">
                                    <div class="col-8">
                                        <select name="new_profile_id" class="form-select form-select-sm" required>
                                            <option value="">-- Select New Profile --</option>
                                            <?php foreach ($profiles as $profile): ?>
                                            <option value="<?= $profile['id'] ?>" <?= ($currentOnu['service_profile_id'] == $profile['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($profile['name']) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-4">
                                        <button type="submit" class="btn btn-primary btn-sm w-100">Upgrade</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php elseif ($view === 'profiles'): ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0"><i class="bi bi-sliders me-2"></i>Service Profiles</h4>
                <div class="btn-group">
                    <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#configScriptModal">
                        <i class="bi bi-terminal me-1"></i> Generate OLT Config
                    </button>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#profileModal" onclick="resetProfileForm()">
                        <i class="bi bi-plus-circle me-1"></i> Add Profile
                    </button>
                </div>
            </div>
            
            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <?php if (empty($profiles)): ?>
                    <div class="p-4 text-center text-muted">
                        <i class="bi bi-sliders fs-1 mb-2 d-block"></i>
                        No service profiles configured
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>VLAN</th>
                                    <th>Speed (Up/Down)</th>
                                    <th>Line Profile</th>
                                    <th>Service Profile</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($profiles as $profile): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($profile['name']) ?></strong>
                                        <?php if ($profile['is_default']): ?>
                                        <span class="badge bg-info ms-1">Default</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge bg-secondary"><?= ucfirst($profile['profile_type']) ?></span></td>
                                    <td><?= $profile['vlan_id'] ?: '-' ?></td>
                                    <td><?= htmlspecialchars($profile['speed_profile_up'] ?: '-') ?> / <?= htmlspecialchars($profile['speed_profile_down'] ?: '-') ?></td>
                                    <td><code><?= htmlspecialchars($profile['line_profile'] ?: '-') ?></code></td>
                                    <td><code><?= htmlspecialchars($profile['srv_profile'] ?: '-') ?></code></td>
                                    <td>
                                        <?php if ($profile['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-secondary" onclick="editProfile(<?= htmlspecialchars(json_encode($profile)) ?>)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-outline-danger" onclick="deleteProfile(<?= $profile['id'] ?>)">
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
            
            <?php elseif ($view === 'templates'): ?>
            <?php $serviceTemplates = $huaweiOLT->getServiceTemplates(); ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0"><i class="bi bi-file-earmark-code me-2"></i>Service Templates</h4>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTemplateModal">
                    <i class="bi bi-plus-lg me-1"></i> New Template
                </button>
            </div>
            
            <p class="text-muted mb-4">Pre-define service profiles (bandwidth, VLAN, QoS) for fast ONU provisioning.</p>
            
            <div class="row g-4">
                <?php if (empty($serviceTemplates)): ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>No service templates defined yet. Create your first template to speed up ONU provisioning.
                    </div>
                </div>
                <?php else: ?>
                <?php foreach ($serviceTemplates as $template): ?>
                <div class="col-md-4">
                    <div class="card h-100 shadow-sm <?= $template['is_default'] ? 'border-primary' : '' ?>">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <strong><?= htmlspecialchars($template['name']) ?></strong>
                            <?php if ($template['is_default']): ?>
                            <span class="badge bg-primary">Default</span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <?php if ($template['description']): ?>
                            <p class="text-muted small"><?= htmlspecialchars($template['description']) ?></p>
                            <?php endif; ?>
                            
                            <div class="row g-2 text-center mb-3">
                                <div class="col-6">
                                    <div class="bg-success bg-opacity-10 rounded p-2">
                                        <div class="small text-muted">Download</div>
                                        <strong class="text-success"><?= $template['downstream_bandwidth'] ?> <?= $template['bandwidth_unit'] ?></strong>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="bg-info bg-opacity-10 rounded p-2">
                                        <div class="small text-muted">Upload</div>
                                        <strong class="text-info"><?= $template['upstream_bandwidth'] ?> <?= $template['bandwidth_unit'] ?></strong>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="small">
                                <?php if ($template['vlan_id']): ?>
                                <div><i class="bi bi-diagram-2 me-1"></i> VLAN: <?= $template['vlan_id'] ?> (<?= $template['vlan_mode'] ?>)</div>
                                <?php endif; ?>
                                <?php if ($template['qos_profile']): ?>
                                <div><i class="bi bi-speedometer me-1"></i> QoS: <?= htmlspecialchars($template['qos_profile']) ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="d-flex gap-1 mt-2">
                                <?php if ($template['iptv_enabled']): ?>
                                <span class="badge bg-warning">IPTV</span>
                                <?php endif; ?>
                                <?php if ($template['voip_enabled']): ?>
                                <span class="badge bg-info">VoIP</span>
                                <?php endif; ?>
                                <?php if ($template['tr069_enabled']): ?>
                                <span class="badge bg-secondary">TR-069</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-footer bg-white d-flex justify-content-between">
                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editTemplateModal<?= $template['id'] ?>">
                                <i class="bi bi-pencil"></i> Edit
                            </button>
                            <form method="post" class="d-inline" onsubmit="return confirm('Delete this template?')">
                                <input type="hidden" name="action" value="delete_template">
                                <input type="hidden" name="template_id" value="<?= $template['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="modal fade" id="editTemplateModal<?= $template['id'] ?>" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Edit Template: <?= htmlspecialchars($template['name']) ?></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="post">
                                <div class="modal-body">
                                    <input type="hidden" name="action" value="update_template">
                                    <input type="hidden" name="template_id" value="<?= $template['id'] ?>">
                                    
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Template Name</label>
                                            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($template['name']) ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Description</label>
                                            <input type="text" name="description" class="form-control" value="<?= htmlspecialchars($template['description'] ?? '') ?>">
                                        </div>
                                    </div>
                                    
                                    <hr class="my-3">
                                    <h6>Bandwidth</h6>
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label">Download</label>
                                            <input type="number" name="downstream_bandwidth" class="form-control" value="<?= $template['downstream_bandwidth'] ?>" min="1">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Upload</label>
                                            <input type="number" name="upstream_bandwidth" class="form-control" value="<?= $template['upstream_bandwidth'] ?>" min="1">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Unit</label>
                                            <select name="bandwidth_unit" class="form-select">
                                                <option value="mbps" <?= $template['bandwidth_unit'] === 'mbps' ? 'selected' : '' ?>>Mbps</option>
                                                <option value="kbps" <?= $template['bandwidth_unit'] === 'kbps' ? 'selected' : '' ?>>Kbps</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <hr class="my-3">
                                    <h6>VLAN & QoS</h6>
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label">VLAN ID</label>
                                            <input type="number" name="vlan_id" class="form-control" value="<?= $template['vlan_id'] ?>" min="1" max="4094">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">VLAN Mode</label>
                                            <select name="vlan_mode" class="form-select">
                                                <option value="tag" <?= $template['vlan_mode'] === 'tag' ? 'selected' : '' ?>>Tagged</option>
                                                <option value="untag" <?= $template['vlan_mode'] === 'untag' ? 'selected' : '' ?>>Untagged</option>
                                                <option value="transparent" <?= $template['vlan_mode'] === 'transparent' ? 'selected' : '' ?>>Transparent</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">QoS Profile</label>
                                            <input type="text" name="qos_profile" class="form-control" value="<?= htmlspecialchars($template['qos_profile'] ?? '') ?>">
                                        </div>
                                    </div>
                                    
                                    <hr class="my-3">
                                    <h6>Services</h6>
                                    <div class="row g-3">
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input type="checkbox" name="iptv_enabled" class="form-check-input" <?= $template['iptv_enabled'] ? 'checked' : '' ?>>
                                                <label class="form-check-label">IPTV</label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input type="checkbox" name="voip_enabled" class="form-check-input" <?= $template['voip_enabled'] ? 'checked' : '' ?>>
                                                <label class="form-check-label">VoIP</label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input type="checkbox" name="tr069_enabled" class="form-check-input" <?= $template['tr069_enabled'] ? 'checked' : '' ?>>
                                                <label class="form-check-label">TR-069</label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input type="checkbox" name="is_default" class="form-check-input" <?= $template['is_default'] ? 'checked' : '' ?>>
                                                <label class="form-check-label">Default</label>
                                            </div>
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
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="modal fade" id="addTemplateModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Create Service Template</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="post">
                            <div class="modal-body">
                                <input type="hidden" name="action" value="create_template">
                                
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Template Name</label>
                                        <input type="text" name="name" class="form-control" placeholder="e.g., Basic 20Mbps" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Description</label>
                                        <input type="text" name="description" class="form-control" placeholder="Optional description">
                                    </div>
                                </div>
                                
                                <hr class="my-3">
                                <h6>Bandwidth</h6>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Download Speed</label>
                                        <input type="number" name="downstream_bandwidth" class="form-control" value="100" min="1">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Upload Speed</label>
                                        <input type="number" name="upstream_bandwidth" class="form-control" value="50" min="1">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Unit</label>
                                        <select name="bandwidth_unit" class="form-select">
                                            <option value="mbps" selected>Mbps</option>
                                            <option value="kbps">Kbps</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <hr class="my-3">
                                <h6>VLAN & QoS</h6>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">VLAN ID</label>
                                        <input type="number" name="vlan_id" class="form-control" placeholder="e.g., 100" min="1" max="4094">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">VLAN Mode</label>
                                        <select name="vlan_mode" class="form-select">
                                            <option value="tag" selected>Tagged</option>
                                            <option value="untag">Untagged</option>
                                            <option value="transparent">Transparent</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">QoS Profile</label>
                                        <input type="text" name="qos_profile" class="form-control" placeholder="e.g., profile_100m">
                                    </div>
                                </div>
                                
                                <hr class="my-3">
                                <h6>Services</h6>
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input type="checkbox" name="iptv_enabled" class="form-check-input">
                                            <label class="form-check-label">IPTV</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input type="checkbox" name="voip_enabled" class="form-check-input">
                                            <label class="form-check-label">VoIP</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input type="checkbox" name="tr069_enabled" class="form-check-input">
                                            <label class="form-check-label">TR-069</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input type="checkbox" name="is_default" class="form-check-input">
                                            <label class="form-check-label">Default</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Create Template</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
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
            
            <?php elseif ($view === 'settings'): ?>
            <?php
            $genieacsSettings = [];
            try {
                $stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'genieacs_%'");
                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $genieacsSettings[$row['setting_key']] = $row['setting_value'];
                }
            } catch (Exception $e) {}
            ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0"><i class="bi bi-gear me-2"></i>Settings</h4>
            </div>
            
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
                    <form method="post" class="d-inline">
                        <input type="hidden" name="action" value="sync_all_olt">
                        <input type="hidden" name="olt_id" value="<?= $oltId ?>">
                        <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('Sync all data from OLT? This may take a moment.')">
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
                        
                        for ($slot = 0; $slot <= 21; $slot++): 
                            $board = $boardsBySlot[$slot] ?? null;
                            $boardType = $board ? $huaweiOLT->getBoardTypeCategory($board['board_name'] ?? '') : 'empty';
                            $slotOnus = $onuCountBySlot[$slot] ?? ['count' => 0, 'online' => 0];
                            
                            $bgColor = 'bg-light border';
                            $textColor = 'text-muted';
                            if ($board) {
                                switch ($boardType) {
                                    case 'gpon': $bgColor = 'bg-success bg-opacity-25 border-success'; $textColor = 'text-success'; break;
                                    case 'epon': $bgColor = 'bg-info bg-opacity-25 border-info'; $textColor = 'text-info'; break;
                                    case 'uplink': $bgColor = 'bg-warning bg-opacity-25 border-warning'; $textColor = 'text-warning'; break;
                                    case 'control': $bgColor = 'bg-primary bg-opacity-25 border-primary'; $textColor = 'text-primary'; break;
                                    case 'power': $bgColor = 'bg-danger bg-opacity-25 border-danger'; $textColor = 'text-danger'; break;
                                    default: $bgColor = 'bg-secondary bg-opacity-25 border-secondary'; $textColor = 'text-secondary';
                                }
                            }
                        ?>
                        <div class="col-6 col-md-3 col-lg-2">
                            <div class="card <?= $bgColor ?> h-100" style="min-height: 100px;">
                                <div class="card-body p-2 text-center">
                                    <div class="small text-muted">Slot <?= $slot ?></div>
                                    <?php if ($board): ?>
                                    <div class="fw-bold <?= $textColor ?>" style="font-size: 0.75rem;"><?= htmlspecialchars($board['board_name']) ?></div>
                                    <div class="small">
                                        <span class="badge bg-<?= strtolower($board['status'] ?? '') === 'normal' ? 'success' : 'secondary' ?>" style="font-size: 0.65rem;">
                                            <?= htmlspecialchars($board['status'] ?? '-') ?>
                                        </span>
                                    </div>
                                    <?php if ($boardType === 'gpon' && $slotOnus['count'] > 0): ?>
                                    <div class="mt-1 small">
                                        <i class="bi bi-diagram-3"></i> <?= $slotOnus['count'] ?> ONUs
                                        <span class="text-success">(<?= $slotOnus['online'] ?> on)</span>
                                    </div>
                                    <?php endif; ?>
                                    <?php else: ?>
                                    <div class="text-muted small mt-2"><i class="bi bi-dash-lg"></i> Empty</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endfor; ?>
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
                                            <th>Description</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($cachedVLANs as $vlan): ?>
                                        <tr>
                                            <td><strong><?= $vlan['vlan_id'] ?></strong></td>
                                            <td><span class="badge bg-secondary"><?= htmlspecialchars($vlan['vlan_type'] ?? 'smart') ?></span></td>
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
                                            <div class="modal-dialog modal-sm">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h6 class="modal-title">Edit VLAN <?= $vlan['vlan_id'] ?></h6>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="post">
                                                        <div class="modal-body">
                                                            <input type="hidden" name="action" value="update_vlan_desc">
                                                            <input type="hidden" name="olt_id" value="<?= $oltId ?>">
                                                            <input type="hidden" name="vlan_id" value="<?= $vlan['vlan_id'] ?>">
                                                            <div class="mb-3">
                                                                <label class="form-label">Description</label>
                                                                <input type="text" name="description" class="form-control" 
                                                                       value="<?= htmlspecialchars($vlan['description'] ?? '') ?>" 
                                                                       placeholder="Enter description" maxlength="32">
                                                                <small class="text-muted">Max 32 characters, alphanumeric only</small>
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
                        <div class="card-body">
                            <?php if (!empty($cachedPorts)): ?>
                            <div class="row g-3">
                                <?php foreach ($cachedPorts as $port): ?>
                                <?php 
                                $status = strtolower($port['oper_status'] ?? '');
                                $isUp = in_array($status, ['up', 'online', 'normal', 'enable']);
                                $adminEnabled = strtolower($port['admin_status'] ?? '') === 'enable';
                                ?>
                                <div class="col-md-4">
                                    <div class="card h-100 <?= $isUp ? 'border-success' : 'border-secondary' ?>">
                                        <div class="card-header bg-transparent d-flex justify-content-between align-items-center py-2">
                                            <strong><?= htmlspecialchars($port['port_name']) ?></strong>
                                            <span class="badge bg-<?= $isUp ? 'success' : 'secondary' ?>">
                                                <?= htmlspecialchars(ucfirst($port['oper_status'] ?? '-')) ?>
                                            </span>
                                        </div>
                                        <div class="card-body text-center py-3">
                                            <i class="bi bi-ethernet fs-2 <?= $isUp ? 'text-success' : 'text-secondary' ?>"></i>
                                            <div class="small text-muted mt-1"><?= htmlspecialchars($port['port_type'] ?? 'GPON') ?></div>
                                            <div class="mt-2 small">
                                                <i class="bi bi-diagram-3 me-1"></i> <?= $port['onu_count'] ?? 0 ?> ONUs
                                            </div>
                                            <?php if (!empty($port['native_vlan'])): ?>
                                            <div class="small text-muted">VLAN: <?= $port['native_vlan'] ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="card-footer bg-transparent d-flex gap-1 flex-wrap justify-content-center py-2">
                                            <a href="?page=huawei-olt&view=onus&olt_id=<?= $oltId ?>&port=<?= urlencode($port['port_name']) ?>" class="btn btn-sm btn-outline-primary" title="View ONUs">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="action" value="toggle_port">
                                                <input type="hidden" name="olt_id" value="<?= $oltId ?>">
                                                <input type="hidden" name="port_name" value="<?= htmlspecialchars($port['port_name']) ?>">
                                                <input type="hidden" name="enable" value="<?= $adminEnabled ? '0' : '1' ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-<?= $adminEnabled ? 'warning' : 'success' ?>" 
                                                        title="<?= $adminEnabled ? 'Disable' : 'Enable' ?> Port" 
                                                        onclick="return confirm('<?= $adminEnabled ? 'Disable' : 'Enable' ?> port <?= $port['port_name'] ?>?')">
                                                    <i class="bi bi-<?= $adminEnabled ? 'pause' : 'play' ?>-fill"></i>
                                                </button>
                                            </form>
                                            <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#vlanModal<?= str_replace('/', '_', $port['port_name']) ?>" title="Assign VLAN">
                                                <i class="bi bi-diagram-2"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="modal fade" id="vlanModal<?= str_replace('/', '_', $port['port_name']) ?>" tabindex="-1">
                                    <div class="modal-dialog modal-sm">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h6 class="modal-title">Assign VLAN to <?= htmlspecialchars($port['port_name']) ?></h6>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="post">
                                                <div class="modal-body">
                                                    <input type="hidden" name="action" value="assign_port_vlan">
                                                    <input type="hidden" name="olt_id" value="<?= $oltId ?>">
                                                    <input type="hidden" name="port_name" value="<?= htmlspecialchars($port['port_name']) ?>">
                                                    <div class="mb-3">
                                                        <label class="form-label">VLAN ID</label>
                                                        <select name="vlan_id" class="form-select" required>
                                                            <?php foreach ($cachedVLANs as $vlan): ?>
                                                            <option value="<?= $vlan['vlan_id'] ?>"><?= $vlan['vlan_id'] ?> - <?= htmlspecialchars($vlan['description'] ?: $vlan['vlan_type'] ?? 'smart') ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Mode</label>
                                                        <select name="vlan_mode" class="form-select">
                                                            <option value="tag">Tagged</option>
                                                            <option value="untag">Untagged</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-primary">Assign</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-info mb-0">
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
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="action" value="refresh_all_optical">
                                    <input type="hidden" name="olt_id" value="<?= $oltId ?>">
                                    <button type="submit" class="btn btn-outline-primary w-100" onclick="return confirm('Refresh optical power for all ONUs? This may take some time.')">
                                        <i class="bi bi-broadcast me-2"></i>Refresh All ONU Optical Power
                                    </button>
                                </form>
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
            
            <?php if ($view === 'cli_generator'): ?>
            <!-- CLI Script Generator View -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0"><i class="bi bi-code-square me-2"></i>CLI Script Generator</h4>
            </div>
            
            <div class="row">
                <div class="col-lg-5">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="bi bi-person-plus me-2"></i>Customer & ONU Details</h5>
                        </div>
                        <div class="card-body">
                            <form id="cliGeneratorForm">
                                <div class="mb-3">
                                    <label class="form-label">Customer Name / Account</label>
                                    <input type="text" id="genCustomerName" class="form-control" placeholder="e.g., SNS001234 or John Doe" required>
                                    <div class="form-text">Used in ONU description</div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Zone</label>
                                        <input type="text" id="genZone" class="form-control" placeholder="e.g., Huruma" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Area / Building</label>
                                        <input type="text" id="genArea" class="form-control" placeholder="e.g., Block A">
                                    </div>
                                </div>
                                
                                <hr class="my-3">
                                <h6 class="text-muted mb-3"><i class="bi bi-router me-2"></i>ONU Location</h6>
                                
                                <div class="row">
                                    <div class="col-4 mb-3">
                                        <label class="form-label">Frame</label>
                                        <input type="number" id="genFrame" class="form-control" value="0" min="0">
                                    </div>
                                    <div class="col-4 mb-3">
                                        <label class="form-label">Slot</label>
                                        <input type="number" id="genSlot" class="form-control" placeholder="e.g., 1" required min="0">
                                    </div>
                                    <div class="col-4 mb-3">
                                        <label class="form-label">Port</label>
                                        <input type="number" id="genPort" class="form-control" placeholder="e.g., 0" required min="0">
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">ONU ID</label>
                                        <input type="number" id="genOnuId" class="form-control" placeholder="e.g., 1" required min="1">
                                        <div class="form-text">Next available ID on the port</div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">ONU Serial Number</label>
                                        <input type="text" id="genOnuSn" class="form-control" placeholder="e.g., 485754438B8C1234" required>
                                    </div>
                                </div>
                                
                                <hr class="my-3">
                                <h6 class="text-muted mb-3"><i class="bi bi-sliders me-2"></i>Service Configuration</h6>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Service Profile</label>
                                        <select id="genServiceProfile" class="form-select">
                                            <?php foreach ($profiles as $profile): ?>
                                            <option value="<?= $profile['id'] ?>" 
                                                    data-vlan="<?= htmlspecialchars($profile['vlan_id'] ?? '') ?>"
                                                    data-gem="<?= htmlspecialchars($profile['gem_port'] ?? '') ?>"
                                                    data-line="<?= htmlspecialchars($profile['line_profile'] ?? '') ?>"
                                                    data-srv="<?= htmlspecialchars($profile['srv_profile'] ?? '') ?>"
                                                    data-speed-up="<?= htmlspecialchars($profile['speed_profile_up'] ?? '') ?>"
                                                    data-speed-down="<?= htmlspecialchars($profile['speed_profile_down'] ?? '') ?>"
                                                    <?= $profile['is_default'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($profile['name']) ?> 
                                                <?php if ($profile['vlan_id']): ?>(VLAN <?= $profile['vlan_id'] ?>)<?php endif; ?>
                                            </option>
                                            <?php endforeach; ?>
                                            <option value="custom">Custom Configuration...</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">VLAN ID</label>
                                        <input type="number" id="genVlan" class="form-control" placeholder="e.g., 69">
                                    </div>
                                </div>
                                
                                <div class="row" id="customProfileFields" style="display: none;">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">GEM Port</label>
                                        <input type="number" id="genGemPort" class="form-control" placeholder="e.g., 1">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Line Profile ID</label>
                                        <input type="number" id="genLineProfile" class="form-control" placeholder="e.g., 10">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Service Profile ID</label>
                                        <input type="number" id="genSrvProfile" class="form-control" placeholder="e.g., 10">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Speed (Mbps Down)</label>
                                        <input type="number" id="genSpeedDown" class="form-control" placeholder="e.g., 20">
                                    </div>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="button" class="btn btn-primary btn-lg" onclick="generateCLIScript()">
                                        <i class="bi bi-lightning-charge me-2"></i>Generate CLI Script
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-7">
                    <div class="card shadow-sm">
                        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-terminal me-2"></i>Generated CLI Commands</h5>
                            <button type="button" class="btn btn-sm btn-outline-light" onclick="copyGeneratedScript()">
                                <i class="bi bi-clipboard me-1"></i> Copy All
                            </button>
                        </div>
                        <div class="card-body p-0">
                            <pre class="mb-0 p-3" style="background: #1e1e1e; color: #d4d4d4; font-family: 'Consolas', 'Monaco', monospace; font-size: 0.85rem; max-height: 600px; overflow-y: auto; border-radius: 0 0 0.375rem 0.375rem;"><code id="generatedScript"># Fill in the form and click "Generate CLI Script"
# The commands will appear here ready to paste into your OLT terminal

# Example output:
# interface gpon 0/1
# ont add 0 1 sn-auth "485754438B8C1234" omci ont-lineprofile-id 10 ont-srvprofile-id 10 desc "SNS001234_zone_Huruma_BlockA_authd_20241220"
# ont port native-vlan 0 1 eth 1 vlan 69 priority 0
# quit
# 
# service-port vlan 69 gpon 0/1/0 ont 1 gemport 1 multi-service user-vlan 69 tag-transform translate</code></pre>
                        </div>
                    </div>
                    
                    <div class="card shadow-sm mt-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Quick Reference</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>ONU Description Format:</h6>
                                    <code class="d-block bg-light p-2 rounded mb-3">CUSTOMER_zone_ZONE_AREA_authd_YYYYMMDD</code>
                                    
                                    <h6>Common Commands:</h6>
                                    <ul class="small">
                                        <li><code>display ont autofind all</code> - Find unconfigured ONUs</li>
                                        <li><code>display ont info 0 1</code> - Show ONUs on port 0/1</li>
                                        <li><code>display service-port port 0/1/0</code> - Show service ports</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <h6>Service Profiles Available:</h6>
                                    <ul class="small list-unstyled">
                                        <?php foreach (array_slice($profiles, 0, 5) as $p): ?>
                                        <li><i class="bi bi-check-circle text-success me-1"></i><?= htmlspecialchars($p['name']) ?> 
                                            <?php if ($p['vlan_id']): ?><span class="text-muted">(VLAN <?= $p['vlan_id'] ?>)</span><?php endif; ?>
                                        </li>
                                        <?php endforeach; ?>
                                        <?php if (count($profiles) > 5): ?>
                                        <li class="text-muted">...and <?= count($profiles) - 5 ?> more</li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <script>
            function updateProfileFields() {
                const profileSelect = document.getElementById('genServiceProfile');
                const customFields = document.getElementById('customProfileFields');
                
                if (profileSelect.value === 'custom') {
                    customFields.style.display = 'flex';
                } else {
                    customFields.style.display = 'none';
                    const option = profileSelect.options[profileSelect.selectedIndex];
                    document.getElementById('genVlan').value = option.dataset.vlan || '';
                    document.getElementById('genGemPort').value = option.dataset.gem || '';
                    document.getElementById('genLineProfile').value = option.dataset.line || '';
                    document.getElementById('genSrvProfile').value = option.dataset.srv || '';
                }
            }
            
            document.getElementById('genServiceProfile').addEventListener('change', updateProfileFields);
            
            // Initialize fields on page load
            document.addEventListener('DOMContentLoaded', function() {
                updateProfileFields();
            });
            
            // Also run immediately in case DOMContentLoaded already fired
            if (document.readyState !== 'loading') {
                updateProfileFields();
            }
            
            function generateCLIScript() {
                const customer = document.getElementById('genCustomerName').value.trim();
                const zone = document.getElementById('genZone').value.trim();
                const area = document.getElementById('genArea').value.trim() || '';
                const frame = document.getElementById('genFrame').value || '0';
                const slot = document.getElementById('genSlot').value;
                const port = document.getElementById('genPort').value;
                const onuId = document.getElementById('genOnuId').value;
                const onuSn = document.getElementById('genOnuSn').value.trim().toUpperCase();
                const vlan = document.getElementById('genVlan').value;
                
                const profileSelect = document.getElementById('genServiceProfile');
                const option = profileSelect.options[profileSelect.selectedIndex];
                
                let lineProfile, srvProfile, gemPort;
                if (profileSelect.value === 'custom') {
                    lineProfile = document.getElementById('genLineProfile').value || '10';
                    srvProfile = document.getElementById('genSrvProfile').value || '10';
                    gemPort = document.getElementById('genGemPort').value || '1';
                } else {
                    lineProfile = option.dataset.line || '10';
                    srvProfile = option.dataset.srv || '10';
                    gemPort = option.dataset.gem || '1';
                }
                
                if (!customer || !zone || !slot || !port || !onuId || !onuSn || !vlan) {
                    alert('Please fill in all required fields');
                    return;
                }
                
                const today = new Date().toISOString().slice(0,10).replace(/-/g, '');
                const areaClean = area.replace(/\s+/g, '');
                const desc = `${customer}_zone_${zone}_${areaClean}_authd_${today}`;
                
                let script = `# ================================================\n`;
                script += `# ONU Provisioning Script\n`;
                script += `# Customer: ${customer}\n`;
                script += `# Zone: ${zone}${area ? ', Area: ' + area : ''}\n`;
                script += `# Generated: ${new Date().toLocaleString()}\n`;
                script += `# ================================================\n\n`;
                
                script += `# Step 1: Enter GPON interface configuration\n`;
                script += `interface gpon ${frame}/${slot}\n\n`;
                
                script += `# Step 2: Add ONU with authentication\n`;
                script += `ont add ${port} ${onuId} sn-auth "${onuSn}" omci ont-lineprofile-id ${lineProfile} ont-srvprofile-id ${srvProfile} desc "${desc}"\n\n`;
                
                script += `# Step 3: Configure native VLAN on ONU ETH port\n`;
                script += `ont port native-vlan ${port} ${onuId} eth 1 vlan ${vlan} priority 0\n\n`;
                
                script += `# Step 4: Exit interface\n`;
                script += `quit\n\n`;
                
                script += `# Step 5: Create service port binding\n`;
                script += `service-port vlan ${vlan} gpon ${frame}/${slot}/${port} ont ${onuId} gemport ${gemPort} multi-service user-vlan ${vlan} tag-transform translate\n\n`;
                
                script += `# ================================================\n`;
                script += `# Verification Commands (optional)\n`;
                script += `# ================================================\n`;
                script += `# display ont info ${port} ${onuId}\n`;
                script += `# display ont optical-info ${port} ${onuId}\n`;
                script += `# display service-port port ${frame}/${slot}/${port} ont ${onuId}\n`;
                
                document.getElementById('generatedScript').textContent = script;
            }
            
            function copyGeneratedScript() {
                const script = document.getElementById('generatedScript').textContent;
                navigator.clipboard.writeText(script).then(() => {
                    alert('CLI script copied to clipboard!');
                }).catch(() => {
                    // Fallback for older browsers
                    const textarea = document.createElement('textarea');
                    textarea.value = script;
                    document.body.appendChild(textarea);
                    textarea.select();
                    document.execCommand('copy');
                    document.body.removeChild(textarea);
                    alert('CLI script copied to clipboard!');
                });
            }
            </script>
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
    
    <div class="modal fade" id="authModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="action" value="authorize_onu">
                    <input type="hidden" name="onu_id" id="authOnuId">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title"><i class="bi bi-check-circle me-2"></i>Authorize ONU</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="bi bi-router me-2"></i>
                            <strong>ONU:</strong> <span id="authOnuSn"></span>
                            <span class="ms-3"><strong>Location:</strong> <span id="authOnuLocation"></span></span>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0"><i class="bi bi-shield-check me-2"></i>Authentication Method</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="radio" name="auth_method" id="authSN" value="sn" checked>
                                            <label class="form-check-label" for="authSN">
                                                <strong>Serial Number (SN)</strong>
                                                <small class="text-muted d-block">Most common. Authenticate by ONU serial number.</small>
                                            </label>
                                        </div>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="radio" name="auth_method" id="authLOID" value="loid">
                                            <label class="form-check-label" for="authLOID">
                                                <strong>LOID (Logical ID)</strong>
                                                <small class="text-muted d-block">Pre-register with Line ID for security.</small>
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="auth_method" id="authMAC" value="mac">
                                            <label class="form-check-label" for="authMAC">
                                                <strong>MAC Address</strong>
                                                <small class="text-muted d-block">Authenticate by MAC address.</small>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div id="loidInputGroup" style="display:none;">
                                    <div class="mb-2">
                                        <label class="form-label small">LOID Value</label>
                                        <input type="text" name="loid" class="form-control form-control-sm" placeholder="Enter LOID">
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small">LOID Password (Optional)</label>
                                        <input type="text" name="loid_password" class="form-control form-control-sm" placeholder="Password if required">
                                    </div>
                                </div>
                                <div id="macInputGroup" style="display:none;">
                                    <div class="mb-2">
                                        <label class="form-label small">MAC Address</label>
                                        <input type="text" name="mac_address" class="form-control form-control-sm" placeholder="XX:XX:XX:XX:XX:XX" pattern="^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$">
                                        <small class="text-muted">Format: XX:XX:XX:XX:XX:XX or XX-XX-XX-XX-XX-XX</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0"><i class="bi bi-sliders me-2"></i>Service Configuration</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">Service Profile</label>
                                            <select name="profile_id" class="form-select" required>
                                                <?php foreach ($profiles as $profile): ?>
                                                <option value="<?= $profile['id'] ?>" <?= $profile['is_default'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($profile['name']) ?>
                                                    (<?= $profile['speed_profile_down'] ?: '-' ?> / <?= $profile['speed_profile_up'] ?: '-' ?>)
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Description</label>
                                            <input type="text" name="description" id="authDescription" class="form-control" placeholder="Customer name or location">
                                        </div>
                                        
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="auto_configure" id="authAutoConfig" checked>
                                            <label class="form-check-label" for="authAutoConfig">
                                                Auto-configure service VLAN
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check-circle me-1"></i> Authorize & Provision
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="wifiConfigModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="action" value="tr069_wifi">
                    <input type="hidden" name="device_id" id="wifiDeviceId">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-wifi me-2"></i>Configure WiFi</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info small">
                            <i class="bi bi-info-circle me-2"></i>
                            Configuring device: <strong id="wifiDeviceSn"></strong>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" name="enabled" id="wifiEnabled" checked>
                            <label class="form-check-label" for="wifiEnabled">Enable WiFi</label>
                        </div>
                        
                        <h6>2.4 GHz WiFi</h6>
                        <div class="row mb-3">
                            <div class="col-8">
                                <label class="form-label">SSID</label>
                                <input type="text" name="ssid" class="form-control" placeholder="Network Name" required>
                            </div>
                            <div class="col-4">
                                <label class="form-label">Channel</label>
                                <select name="channel" class="form-select">
                                    <option value="0">Auto</option>
                                    <option value="1">1</option>
                                    <option value="6">6</option>
                                    <option value="11">11</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="text" name="password" class="form-control" placeholder="WiFi Password" required minlength="8">
                            <div class="form-text">Minimum 8 characters</div>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Loading overlay for OLT sync operations
    const loadingMessages = {
        'sync_onus_snmp': 'Syncing ONUs from OLT...',
        'sync_onu_locations': 'Fixing ONU location data from SNMP...',
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
    
    function authorizeOnu(id, sn, slot, port) {
        document.getElementById('authOnuId').value = id;
        document.getElementById('authOnuSn').textContent = sn;
        document.getElementById('authOnuLocation').textContent = '0/' + (slot || '-') + '/' + (port || '-');
        document.getElementById('authDescription').value = '';
        new bootstrap.Modal(document.getElementById('authModal')).show();
    }
    
    document.querySelectorAll('input[name="auth_method"]').forEach(function(radio) {
        radio.addEventListener('change', function() {
            document.getElementById('loidInputGroup').style.display = this.value === 'loid' ? 'block' : 'none';
            document.getElementById('macInputGroup').style.display = this.value === 'mac' ? 'block' : 'none';
        });
    });
    
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
    
    function setCommand(cmd) {
        document.querySelector('input[name="command"]').value = cmd;
    }
    
    function openWifiConfig(deviceId, serialNumber) {
        document.getElementById('wifiDeviceId').value = deviceId;
        document.getElementById('wifiDeviceSn').textContent = serialNumber;
        new bootstrap.Modal(document.getElementById('wifiConfigModal')).show();
    }
    </script>
</body>
</html>
