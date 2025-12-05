<?php
$smartolt = new \App\SmartOLT($db);
$isConfigured = $smartolt->isConfigured();
$view = $_GET['view'] ?? 'dashboard';
$oltId = isset($_GET['olt_id']) ? (int)$_GET['olt_id'] : null;
$filter = $_GET['filter'] ?? null;

$stats = [];
$oltDetails = null;
$filteredONUs = [];
$provisioningOptions = [];

if ($isConfigured) {
    if ($view === 'dashboard' || $view === 'olt') {
        $stats = $smartolt->getDashboardStats();
    }
    
    if ($view === 'olt' && $oltId) {
        $oltResult = $smartolt->getOLTDetails($oltId);
        if ($oltResult['status']) {
            $oltDetails = $oltResult['response'];
        }
    }
    
    if ($filter === 'unconfigured') {
        $provisioningOptions = $smartolt->getProvisioningOptions();
    }
    
    if ($filter) {
        switch ($filter) {
            case 'unconfigured':
                $result = $smartolt->getAllUnconfiguredONUs();
                $filteredONUs = ($result['status'] && isset($result['response'])) ? $result['response'] : [];
                break;
            case 'los':
                $filteredONUs = $smartolt->getONUsByStatus('los');
                break;
            case 'power_fail':
                $filteredONUs = $smartolt->getONUsByStatus('power_fail');
                break;
            case 'critical_power':
                $filteredONUs = $smartolt->getCriticalPowerONUs(-28);
                break;
            case 'low_power':
                $filteredONUs = $smartolt->getCriticalPowerONUs(-25);
                $filteredONUs = array_filter($filteredONUs, fn($o) => ($o['rx_power_value'] ?? 0) > -28);
                break;
            case 'offline':
                $filteredONUs = $smartolt->getONUsByStatus('offline');
                break;
            case 'online':
                $filteredONUs = $smartolt->getONUsByStatus('online');
                break;
        }
    }
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="bi bi-router text-primary me-2"></i>SmartOLT
        </h1>
        <?php if ($isConfigured): ?>
        <a href="?page=smartolt" class="btn btn-outline-primary">
            <i class="bi bi-arrow-clockwise me-1"></i> Refresh Data
        </a>
        <?php endif; ?>
    </div>

    <?php if (!$isConfigured): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <strong>SmartOLT is not configured.</strong> 
        Please go to <a href="?page=settings&tab=smartolt" class="alert-link">Settings &rarr; SmartOLT</a> to configure your API credentials.
    </div>
    <?php else: ?>
    
    <?php if ($filter): ?>
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="?page=smartolt">Dashboard</a></li>
            <li class="breadcrumb-item active"><?= ucwords(str_replace('_', ' ', $filter)) ?> ONUs</li>
        </ol>
    </nav>
    
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <?php
                $filterIcons = [
                    'unconfigured' => 'bi-question-circle text-warning',
                    'los' => 'bi-x-circle text-danger',
                    'power_fail' => 'bi-lightning text-danger',
                    'critical_power' => 'bi-exclamation-triangle text-danger',
                    'low_power' => 'bi-exclamation-circle text-warning',
                    'offline' => 'bi-wifi-off text-secondary',
                    'online' => 'bi-wifi text-success'
                ];
                $icon = $filterIcons[$filter] ?? 'bi-circle';
                ?>
                <i class="bi <?= $icon ?> me-2"></i>
                <?= ucwords(str_replace('_', ' ', $filter)) ?> ONUs (<?= count($filteredONUs) ?>)
            </h5>
        </div>
        <div class="card-body">
            <?php if (empty($filteredONUs)): ?>
            <p class="text-muted mb-0">No ONUs found with this status.</p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Serial Number</th>
                            <?php if ($filter !== 'unconfigured'): ?>
                            <th>Name</th>
                            <?php endif; ?>
                            <th>OLT</th>
                            <th>Board/Port</th>
                            <?php if ($filter === 'unconfigured'): ?>
                            <th>PON Type</th>
                            <th>ONU Type</th>
                            <?php else: ?>
                            <th>Status</th>
                            <?php endif; ?>
                            <?php if (in_array($filter, ['critical_power', 'low_power'])): ?>
                            <th>RX Power</th>
                            <?php endif; ?>
                            <?php if ($filter !== 'unconfigured'): ?>
                            <th>Zone</th>
                            <?php endif; ?>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($filteredONUs as $onu): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($onu['sn'] ?? $onu['serial_number'] ?? 'N/A') ?></code></td>
                            <?php if ($filter !== 'unconfigured'): ?>
                            <td><?= htmlspecialchars($onu['name'] ?? $onu['onu_name'] ?? 'N/A') ?></td>
                            <?php endif; ?>
                            <td><?= htmlspecialchars($onu['olt_name'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars(($onu['board'] ?? '-') . '/' . ($onu['port'] ?? $onu['pon_port'] ?? '-')) ?></td>
                            <?php if ($filter === 'unconfigured'): ?>
                            <td><span class="badge bg-info"><?= htmlspecialchars($onu['pon_type'] ?? 'GPON') ?></span></td>
                            <td><?= htmlspecialchars($onu['onu_type'] ?? 'Auto') ?></td>
                            <?php else: ?>
                            <td>
                                <?php
                                $status = $onu['status'] ?? 'Unknown';
                                $statusLower = strtolower($status);
                                $statusClass = 'secondary';
                                if (strpos($statusLower, 'online') !== false) $statusClass = 'success';
                                elseif (strpos($statusLower, 'los') !== false) $statusClass = 'danger';
                                elseif (strpos($statusLower, 'power') !== false) $statusClass = 'warning';
                                ?>
                                <span class="badge bg-<?= $statusClass ?>"><?= htmlspecialchars($status) ?></span>
                            </td>
                            <?php endif; ?>
                            <?php if (in_array($filter, ['critical_power', 'low_power'])): ?>
                            <td>
                                <?php
                                $rxPower = $onu['rx_power_value'] ?? $onu['onu_rx_power'] ?? 'N/A';
                                $powerClass = 'success';
                                if (is_numeric($rxPower)) {
                                    if ($rxPower <= -28) $powerClass = 'danger';
                                    elseif ($rxPower <= -25) $powerClass = 'warning';
                                }
                                ?>
                                <span class="text-<?= $powerClass ?> fw-bold"><?= is_numeric($rxPower) ? number_format($rxPower, 2) . ' dBm' : htmlspecialchars($rxPower) ?></span>
                            </td>
                            <?php endif; ?>
                            <?php if ($filter !== 'unconfigured'): ?>
                            <td><?= htmlspecialchars($onu['zone'] ?? $onu['zone_name'] ?? 'N/A') ?></td>
                            <?php endif; ?>
                            <td>
                                <?php if ($filter === 'unconfigured'): ?>
                                <button class="btn btn-sm btn-success" onclick="openProvisionModal(<?= htmlspecialchars(json_encode($onu)) ?>)">
                                    <i class="bi bi-plus-circle me-1"></i> Provision
                                </button>
                                <?php elseif (isset($onu['onu_external_id'])): ?>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-primary" onclick="onuAction('reboot', '<?= $onu['onu_external_id'] ?>')" title="Reboot">
                                        <i class="bi bi-arrow-clockwise"></i>
                                    </button>
                                    <button class="btn btn-outline-secondary" onclick="onuAction('resync', '<?= $onu['onu_external_id'] ?>')" title="Resync Config">
                                        <i class="bi bi-arrow-repeat"></i>
                                    </button>
                                </div>
                                <?php else: ?>
                                <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php elseif ($view === 'olt' && $oltDetails): ?>
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="?page=smartolt">Dashboard</a></li>
            <li class="breadcrumb-item active"><?= htmlspecialchars($oltDetails['name'] ?? 'OLT') ?></li>
        </ol>
    </nav>
    
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>OLT Information</h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Name</dt>
                        <dd class="col-sm-8"><?= htmlspecialchars($oltDetails['name'] ?? 'N/A') ?></dd>
                        
                        <dt class="col-sm-4">Hardware</dt>
                        <dd class="col-sm-8"><?= htmlspecialchars($oltDetails['olt_hardware_version'] ?? 'N/A') ?></dd>
                        
                        <dt class="col-sm-4">IP Address</dt>
                        <dd class="col-sm-8"><code><?= htmlspecialchars($oltDetails['ip'] ?? 'N/A') ?></code></dd>
                        
                        <dt class="col-sm-4">Telnet Port</dt>
                        <dd class="col-sm-8"><?= htmlspecialchars($oltDetails['telnet_port'] ?? 'N/A') ?></dd>
                        
                        <dt class="col-sm-4">SNMP Port</dt>
                        <dd class="col-sm-8"><?= htmlspecialchars($oltDetails['snmp_port'] ?? 'N/A') ?></dd>
                        
                        <dt class="col-sm-4">Uptime</dt>
                        <dd class="col-sm-8"><span class="badge bg-success"><?= htmlspecialchars($oltDetails['uptime'] ?? 'N/A') ?></span></dd>
                        
                        <dt class="col-sm-4">Temperature</dt>
                        <dd class="col-sm-8">
                            <?php
                            $temp = $oltDetails['env_temp'] ?? 'N/A';
                            $tempNum = (int)preg_replace('/[^0-9]/', '', $temp);
                            $tempClass = 'success';
                            if ($tempNum > 50) $tempClass = 'danger';
                            elseif ($tempNum > 40) $tempClass = 'warning';
                            ?>
                            <span class="badge bg-<?= $tempClass ?>"><?= htmlspecialchars($temp) ?></span>
                        </dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-cpu me-2"></i>Cards (<?= count($oltDetails['cards'] ?? []) ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($oltDetails['cards'])): ?>
                    <p class="text-muted mb-0">No card information available.</p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>Slot</th>
                                    <th>Type</th>
                                    <th>Ports</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($oltDetails['cards'] as $card): ?>
                                <tr>
                                    <td><?= htmlspecialchars($card['slot'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($card['type'] ?? $card['real_type'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($card['ports_count'] ?? $card['number_of_ports'] ?? 'N/A') ?></td>
                                    <td>
                                        <?php
                                        $cardStatus = $card['status'] ?? 'Unknown';
                                        $cardClass = strtolower($cardStatus) === 'normal' || strtolower($cardStatus) === 'active' ? 'success' : 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $cardClass ?>"><?= htmlspecialchars($cardStatus) ?></span>
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
    
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-ethernet me-2"></i>PON Ports (<?= count($oltDetails['pon_ports'] ?? []) ?>)</h5>
        </div>
        <div class="card-body">
            <?php if (empty($oltDetails['pon_ports'])): ?>
            <p class="text-muted mb-0">No PON port information available.</p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Board/Port</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Total ONUs</th>
                            <th>Online ONUs</th>
                            <th>TX Power</th>
                            <th>Avg Signal</th>
                            <th>Range</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($oltDetails['pon_ports'] as $port): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars(($port['board'] ?? '-') . '/' . ($port['pon_port'] ?? '-')) ?></strong></td>
                            <td><?= htmlspecialchars($port['pon_type'] ?? 'N/A') ?></td>
                            <td>
                                <?php
                                $portStatus = $port['operational_status'] ?? 'Unknown';
                                $portClass = strtolower($portStatus) === 'up' ? 'success' : 'danger';
                                ?>
                                <span class="badge bg-<?= $portClass ?>"><?= htmlspecialchars($portStatus) ?></span>
                            </td>
                            <td><?= htmlspecialchars($port['onus_count'] ?? '0') ?></td>
                            <td>
                                <?php
                                $total = (int)($port['onus_count'] ?? 0);
                                $online = (int)($port['online_onus_count'] ?? 0);
                                $onlineClass = ($total > 0 && $online === $total) ? 'success' : (($online > 0) ? 'warning' : 'secondary');
                                ?>
                                <span class="text-<?= $onlineClass ?>"><?= $online ?></span>
                            </td>
                            <td><?= htmlspecialchars($port['tx_power'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($port['average_signal'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars(($port['min_range'] ?? '0') . ' - ' . ($port['max_range'] ?? 'N/A')) ?></td>
                            <td><?= htmlspecialchars($port['description'] ?? '-') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php else: ?>
    
    <div class="row g-4 mb-4">
        <div class="col-md-4 col-lg-2">
            <div class="card text-center h-100 border-primary">
                <div class="card-body">
                    <i class="bi bi-router display-4 text-primary mb-2"></i>
                    <h3 class="mb-0"><?= $stats['total_olts'] ?? 0 ?></h3>
                    <small class="text-muted">Total OLTs</small>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-lg-2">
            <a href="?page=smartolt&filter=online" class="text-decoration-none">
                <div class="card text-center h-100 border-success">
                    <div class="card-body">
                        <i class="bi bi-wifi display-4 text-success mb-2"></i>
                        <h3 class="mb-0"><?= $stats['online_onus'] ?? 0 ?></h3>
                        <small class="text-muted">Online ONUs</small>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-4 col-lg-2">
            <a href="?page=smartolt&filter=unconfigured" class="text-decoration-none">
                <div class="card text-center h-100 border-warning">
                    <div class="card-body">
                        <i class="bi bi-question-circle display-4 text-warning mb-2"></i>
                        <h3 class="mb-0"><?= $stats['unconfigured_onus'] ?? 0 ?></h3>
                        <small class="text-muted">Unconfigured</small>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-4 col-lg-2">
            <a href="?page=smartolt&filter=los" class="text-decoration-none">
                <div class="card text-center h-100 border-danger">
                    <div class="card-body">
                        <i class="bi bi-x-circle display-4 text-danger mb-2"></i>
                        <h3 class="mb-0"><?= $stats['los_onus'] ?? 0 ?></h3>
                        <small class="text-muted">LOS</small>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-4 col-lg-2">
            <a href="?page=smartolt&filter=power_fail" class="text-decoration-none">
                <div class="card text-center h-100 border-danger">
                    <div class="card-body">
                        <i class="bi bi-lightning display-4 text-danger mb-2"></i>
                        <h3 class="mb-0"><?= $stats['power_fail_onus'] ?? 0 ?></h3>
                        <small class="text-muted">Power Fail</small>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-4 col-lg-2">
            <a href="?page=smartolt&filter=critical_power" class="text-decoration-none">
                <div class="card text-center h-100 border-danger">
                    <div class="card-body">
                        <i class="bi bi-exclamation-triangle display-4 text-danger mb-2"></i>
                        <h3 class="mb-0"><?= $stats['critical_power_onus'] ?? 0 ?></h3>
                        <small class="text-muted">Critical Power</small>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-router me-2"></i>OLT Status</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($stats['olts'])): ?>
                    <p class="text-muted mb-0">No OLTs found or unable to retrieve OLT data.</p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Hardware</th>
                                    <th>IP Address</th>
                                    <th>Uptime</th>
                                    <th>Temperature</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stats['olts'] as $olt): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($olt['name'] ?? 'N/A') ?></strong>
                                    </td>
                                    <td><?= htmlspecialchars($olt['olt_hardware_version'] ?? 'N/A') ?></td>
                                    <td><code><?= htmlspecialchars($olt['ip'] ?? 'N/A') ?></code></td>
                                    <td>
                                        <span class="badge bg-success"><?= htmlspecialchars($olt['uptime'] ?? 'N/A') ?></span>
                                    </td>
                                    <td>
                                        <?php
                                        $temp = $olt['env_temp'] ?? 'N/A';
                                        $tempNum = (int)preg_replace('/[^0-9]/', '', $temp);
                                        $tempClass = 'success';
                                        if ($tempNum > 50) $tempClass = 'danger';
                                        elseif ($tempNum > 40) $tempClass = 'warning';
                                        ?>
                                        <span class="badge bg-<?= $tempClass ?>"><?= htmlspecialchars($temp) ?></span>
                                    </td>
                                    <td>
                                        <a href="?page=smartolt&view=olt&olt_id=<?= $olt['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye"></i> Details
                                        </a>
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
        
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-question-circle text-warning me-2"></i>Unconfigured ONUs</h5>
                    <?php if (!empty($stats['unconfigured_list'])): ?>
                    <a href="?page=smartolt&filter=unconfigured" class="btn btn-sm btn-outline-warning">View All</a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (empty($stats['unconfigured_list'])): ?>
                    <p class="text-muted mb-0">No unconfigured ONUs found.</p>
                    <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach (array_slice($stats['unconfigured_list'], 0, 10) as $onu): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <div>
                                <code class="small"><?= htmlspecialchars($onu['sn'] ?? $onu['serial_number'] ?? 'N/A') ?></code>
                                <br>
                                <small class="text-muted">
                                    <?= htmlspecialchars($onu['olt_name'] ?? 'N/A') ?> - 
                                    <?= htmlspecialchars(($onu['board'] ?? '-') . '/' . ($onu['port'] ?? '-')) ?>
                                </small>
                            </div>
                            <span class="badge bg-warning">Pending</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (count($stats['unconfigured_list']) > 10): ?>
                    <div class="text-center mt-3">
                        <a href="?page=smartolt&filter=unconfigured" class="btn btn-sm btn-warning">
                            View All <?= count($stats['unconfigured_list']) ?> Unconfigured
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-bar-chart me-2"></i>ONU Summary</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span>Configured ONUs</span>
                            <strong><?= $stats['configured_onus'] ?? 0 ?></strong>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-success"><i class="bi bi-check-circle me-1"></i> Online</span>
                            <strong class="text-success"><?= $stats['online_onus'] ?? 0 ?></strong>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-secondary"><i class="bi bi-wifi-off me-1"></i> Offline</span>
                            <strong><?= $stats['offline_onus'] ?? 0 ?></strong>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-danger"><i class="bi bi-x-circle me-1"></i> LOS</span>
                            <strong class="text-danger"><?= $stats['los_onus'] ?? 0 ?></strong>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-danger"><i class="bi bi-lightning me-1"></i> Power Fail</span>
                            <strong class="text-danger"><?= $stats['power_fail_onus'] ?? 0 ?></strong>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-warning"><i class="bi bi-exclamation-circle me-1"></i> Low Power</span>
                            <strong class="text-warning"><?= $stats['low_power_onus'] ?? 0 ?></strong>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i> Critical Power</span>
                            <strong class="text-danger"><?= $stats['critical_power_onus'] ?? 0 ?></strong>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <?php endif; ?>
    
    <?php endif; ?>
</div>

<?php if ($filter === 'unconfigured' && !empty($provisioningOptions)): ?>
<div class="modal fade" id="provisionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Provision ONU</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="provisionForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= \App\Auth::getToken() ?>">
                    <input type="hidden" name="action" value="smartolt_authorize_onu">
                    <input type="hidden" name="olt_id" id="prov_olt_id">
                    <input type="hidden" name="pon_type" id="prov_pon_type">
                    <input type="hidden" name="board" id="prov_board">
                    <input type="hidden" name="port" id="prov_port">
                    <input type="hidden" name="sn" id="prov_sn">
                    
                    <div class="alert alert-info mb-3">
                        <div class="row">
                            <div class="col-md-6">
                                <strong>Serial Number:</strong> <code id="display_sn"></code>
                            </div>
                            <div class="col-md-6">
                                <strong>OLT:</strong> <span id="display_olt"></span> (Board <span id="display_board"></span>/Port <span id="display_port"></span>)
                            </div>
                        </div>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Customer Name / External ID <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" id="prov_name" required placeholder="Enter customer name or ID">
                            <div class="form-text">This will be used as the ONU external ID</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">ONU Type <span class="text-danger">*</span></label>
                            <select class="form-select" name="onu_type" id="prov_onu_type" required>
                                <option value="">Select ONU Type</option>
                                <?php foreach ($provisioningOptions['onu_types'] as $type): ?>
                                <option value="<?= htmlspecialchars($type['id']) ?>" data-pon="<?= htmlspecialchars($type['pon_type'] ?? '') ?>">
                                    <?= htmlspecialchars($type['name'] ?? $type['onu_type'] ?? 'Unknown') ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Zone</label>
                            <select class="form-select" name="zone" id="prov_zone">
                                <option value="">Select Zone (Optional)</option>
                                <?php foreach ($provisioningOptions['zones'] as $zone): ?>
                                <option value="<?= htmlspecialchars($zone['id']) ?>">
                                    <?= htmlspecialchars($zone['name'] ?? 'Zone ' . $zone['id']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">ODB</label>
                            <select class="form-select" name="odb" id="prov_odb">
                                <option value="">Select ODB (Optional)</option>
                                <?php foreach ($provisioningOptions['odbs'] as $odb): ?>
                                <option value="<?= htmlspecialchars($odb['id']) ?>">
                                    <?= htmlspecialchars($odb['name'] ?? 'ODB ' . $odb['id']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">VLAN</label>
                            <select class="form-select" name="vlan" id="prov_vlan">
                                <option value="">Select VLAN (Optional)</option>
                                <?php foreach ($provisioningOptions['vlans'] as $vlan): ?>
                                <option value="<?= htmlspecialchars($vlan['id']) ?>">
                                    <?= htmlspecialchars(($vlan['name'] ?? 'VLAN') . ' (' . ($vlan['vlan_id'] ?? $vlan['id']) . ')') ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Speed Profile</label>
                            <select class="form-select" name="speed_profile" id="prov_speed">
                                <option value="">Select Speed Profile (Optional)</option>
                                <?php foreach ($provisioningOptions['speed_profiles'] as $profile): ?>
                                <option value="<?= htmlspecialchars($profile['id']) ?>">
                                    <?= htmlspecialchars($profile['name'] ?? 'Profile ' . $profile['id']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">ONU Mode <span class="text-danger">*</span></label>
                            <select class="form-select" name="onu_mode" id="prov_mode" required>
                                <option value="routing">Routing (Router Mode)</option>
                                <option value="bridging">Bridging (Bridge Mode)</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Address / Location</label>
                            <input type="text" class="form-control" name="address" id="prov_address" placeholder="Enter installation address">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success" id="provisionBtn">
                        <i class="bi bi-plus-circle me-1"></i> Provision ONU
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function onuAction(action, externalId) {
    if (!confirm('Are you sure you want to ' + action + ' this ONU?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'smartolt_onu_action');
    formData.append('onu_action', action);
    formData.append('external_id', externalId);
    formData.append('csrf_token', '<?= \App\Auth::getToken() ?>');
    
    fetch('?page=api', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Action completed successfully!');
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(error => {
        alert('Error: ' + error.message);
    });
}

<?php if ($filter === 'unconfigured'): ?>
function openProvisionModal(onuData) {
    document.getElementById('prov_olt_id').value = onuData.olt_id || '';
    document.getElementById('prov_pon_type').value = onuData.pon_type || 'gpon';
    document.getElementById('prov_board').value = onuData.board || '';
    document.getElementById('prov_port').value = onuData.port || onuData.pon_port || '';
    document.getElementById('prov_sn').value = onuData.sn || onuData.serial_number || '';
    
    document.getElementById('display_sn').textContent = onuData.sn || onuData.serial_number || 'N/A';
    document.getElementById('display_olt').textContent = onuData.olt_name || 'OLT ' + onuData.olt_id;
    document.getElementById('display_board').textContent = onuData.board || '-';
    document.getElementById('display_port').textContent = onuData.port || onuData.pon_port || '-';
    
    document.getElementById('prov_name').value = '';
    document.getElementById('prov_onu_type').value = '';
    document.getElementById('prov_zone').value = '';
    document.getElementById('prov_odb').value = '';
    document.getElementById('prov_vlan').value = '';
    document.getElementById('prov_speed').value = '';
    document.getElementById('prov_mode').value = 'routing';
    document.getElementById('prov_address').value = '';
    
    new bootstrap.Modal(document.getElementById('provisionModal')).show();
}

document.getElementById('provisionForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const btn = document.getElementById('provisionBtn');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Provisioning...';
    btn.disabled = true;
    
    const formData = new FormData(this);
    
    fetch('?page=api', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('ONU provisioned successfully!');
            bootstrap.Modal.getInstance(document.getElementById('provisionModal')).hide();
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Failed to provision ONU'));
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    })
    .catch(error => {
        alert('Error: ' + error.message);
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
});
<?php endif; ?>
</script>
