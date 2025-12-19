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
                $huaweiOLT->addONU($_POST);
                $message = 'ONU added successfully';
                $messageType = 'success';
                break;
            case 'update_onu':
                $huaweiOLT->updateONU((int)$_POST['id'], $_POST);
                $message = 'ONU updated successfully';
                $messageType = 'success';
                break;
            case 'delete_onu':
                $huaweiOLT->deleteONU((int)$_POST['id']);
                $message = 'ONU deleted from database';
                $messageType = 'success';
                break;
            case 'authorize_onu':
                $result = $huaweiOLT->authorizeONU((int)$_POST['onu_id'], (int)$_POST['profile_id']);
                $message = $result['message'];
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
            case 'refresh_onu_optical':
                $onu = $huaweiOLT->getONU((int)$_POST['onu_id']);
                if ($onu) {
                    $result = $huaweiOLT->getONUOpticalInfoViaSNMP(
                        $onu['olt_id'], $onu['frame'], $onu['slot'], $onu['port'], $onu['onu_id']
                    );
                    if ($result['success']) {
                        $optical = $result['optical'];
                        $huaweiOLT->updateONU((int)$_POST['onu_id'], [
                            'rx_power' => $optical['rx_power'],
                            'tx_power' => $optical['tx_power']
                        ]);
                        $message = "Optical: RX={$optical['rx_power']}dBm, TX={$optical['tx_power']}dBm";
                        $messageType = 'success';
                    } else {
                        $message = $result['error'] ?? 'Failed to get optical info';
                        $messageType = 'danger';
                    }
                }
                break;
            case 'discover_unconfigured':
                $result = $huaweiOLT->discoverUnconfiguredONUs((int)$_POST['olt_id']);
                if ($result['success']) {
                    $message = "Found {$result['count']} unconfigured ONUs";
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
    </style>
</head>
<body>
    <div class="d-flex">
        <div class="sidebar d-flex flex-column p-3" style="width: 260px;">
            <div class="d-flex align-items-center mb-4 px-2">
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
                <a class="nav-link <?= $view === 'onus' ? 'active' : '' ?>" href="?page=huawei-olt&view=onus">
                    <i class="bi bi-diagram-3 me-2"></i> ONU Inventory
                </a>
                <a class="nav-link <?= $view === 'unconfigured' ? 'active' : '' ?>" href="?page=huawei-olt&view=onus&unconfigured=1">
                    <i class="bi bi-question-circle me-2"></i> Unconfigured ONUs
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
                <hr class="my-2 border-light opacity-25">
                <a class="nav-link <?= $view === 'tr069' ? 'active' : '' ?>" href="?page=huawei-olt&view=tr069">
                    <i class="bi bi-gear-wide-connected me-2"></i> TR-069 / ACS
                </a>
                <a class="nav-link <?= $view === 'settings' ? 'active' : '' ?>" href="?page=huawei-olt&view=settings">
                    <i class="bi bi-gear me-2"></i> Settings
                </a>
            </nav>
            <hr class="my-3 border-light">
            <a class="nav-link text-warning" href="?page=dashboard" target="_self">
                <i class="bi bi-arrow-left me-2"></i> Back to CRM
            </a>
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
                                <div class="text-muted small">Unconfigured</div>
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
                <div class="col-md-4">
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
                                    <button type="submit" class="btn btn-sm btn-outline-warning" title="Discover Unconfigured ONUs">
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
                    <i class="bi bi-diagram-3 me-2"></i>
                    <?= isset($_GET['unconfigured']) ? 'Unconfigured ONUs' : 'ONU Inventory' ?>
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
                            <input type="hidden" name="action" value="discover_unconfigured">
                            <input type="hidden" name="olt_id" value="<?= $oltId ?>">
                            <button type="submit" class="btn btn-warning btn-sm">
                                <i class="bi bi-search me-1"></i> Discover Unconfigured
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
                                    <th>Signal (RX/TX)</th>
                                    <th>Customer</th>
                                    <th>Profile</th>
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
                                        <?php if (!$onu['is_authorized']): ?>
                                        <span class="badge bg-warning">Unconfigured</span>
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
                                        <span class="signal-<?= $rxClass ?>"><?= $rx !== null ? number_format($rx, 1) : '-' ?></span>
                                        / <?= $tx !== null ? number_format($tx, 1) : '-' ?> dBm
                                    </td>
                                    <td><?= htmlspecialchars($onu['customer_name'] ?? '-') ?></td>
                                    <td><small><?= htmlspecialchars($onu['profile_name'] ?? '-') ?></small></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <?php if (!$onu['is_authorized']): ?>
                                            <button class="btn btn-success" onclick="provisionOnu(<?= $onu['id'] ?>, '<?= htmlspecialchars($onu['sn']) ?>')" title="Provision">
                                                <i class="bi bi-plus-circle"></i>
                                            </button>
                                            <?php else: ?>
                                            <button class="btn btn-outline-primary" onclick="rebootOnu(<?= $onu['id'] ?>)" title="Reboot">
                                                <i class="bi bi-arrow-clockwise"></i>
                                            </button>
                                            <?php endif; ?>
                                            <button class="btn btn-outline-secondary" onclick="editOnu(<?= htmlspecialchars(json_encode($onu)) ?>)" title="Edit">
                                                <i class="bi bi-pencil"></i>
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
            
            <?php elseif ($view === 'profiles'): ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0"><i class="bi bi-sliders me-2"></i>Service Profiles</h4>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#profileModal" onclick="resetProfileForm()">
                    <i class="bi bi-plus-circle me-1"></i> Add Profile
                </button>
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
                            <button class="btn btn-sm btn-outline-secondary" onclick="setCommand('display ont autofind all')">Unconfigured ONTs</button>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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
