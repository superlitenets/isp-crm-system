<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Device Monitoring - ISP CRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns"></script>
    <style>
        :root {
            --primary-color: #0d6efd;
            --sidebar-width: 250px;
        }
        body { background-color: #f8f9fa; }
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: linear-gradient(135deg, #1a1c23 0%, #2d3748 100%);
            padding-top: 1rem;
            z-index: 1000;
            overflow-y: auto;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.7);
            padding: 0.75rem 1rem;
            margin: 0.2rem 0.5rem;
            border-radius: 0.5rem;
            transition: all 0.3s;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: #fff;
        }
        .sidebar .nav-link i { margin-right: 0.5rem; width: 20px; text-align: center; }
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
        }
        .stat-card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
            transition: transform 0.3s;
        }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-card .icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        .device-status {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }
        .device-status::before {
            content: '';
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }
        .device-status.online::before { background-color: #28a745; }
        .device-status.offline::before { background-color: #dc3545; }
        .device-status.unknown::before { background-color: #6c757d; }
        .onu-card {
            border-left: 4px solid #0d6efd;
            transition: all 0.3s;
        }
        .onu-card.online { border-left-color: #28a745; }
        .onu-card.offline { border-left-color: #dc3545; }
        .onu-card.los { border-left-color: #ffc107; }
        .signal-indicator {
            font-weight: bold;
        }
        .signal-good { color: #28a745; }
        .signal-warning { color: #ffc107; }
        .signal-bad { color: #dc3545; }
        .brand-header {
            padding: 1rem;
            margin-bottom: 1rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .brand-header h4 { color: #fff; margin: 0; }
    </style>
</head>
<body>
    <nav class="sidebar">
        <div class="brand-header">
            <h4><i class="bi bi-router"></i> ISP CRM</h4>
        </div>
        <ul class="nav flex-column">
            <li class="nav-item"><a class="nav-link" href="/?page=dashboard"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
            <li class="nav-item"><a class="nav-link" href="/?page=customers"><i class="bi bi-people"></i> Customers</a></li>
            <li class="nav-item"><a class="nav-link" href="/?page=tickets"><i class="bi bi-ticket"></i> Tickets</a></li>
            <li class="nav-item"><a class="nav-link" href="/?page=inventory"><i class="bi bi-box-seam"></i> Inventory</a></li>
            <li class="nav-item"><a class="nav-link" href="/?page=orders"><i class="bi bi-cart"></i> Orders</a></li>
            <li class="nav-item"><a class="nav-link" href="/?page=complaints"><i class="bi bi-exclamation-triangle"></i> Complaints</a></li>
            <li class="nav-item"><a class="nav-link" href="/?page=payments"><i class="bi bi-credit-card"></i> Payments</a></li>
            <li class="nav-item"><a class="nav-link" href="/?page=sales"><i class="bi bi-graph-up"></i> Sales</a></li>
            <li class="nav-item"><a class="nav-link active" href="/?page=devices"><i class="bi bi-hdd-network"></i> Network Devices</a></li>
            <li class="nav-item"><a class="nav-link" href="/?page=hr"><i class="bi bi-person-badge"></i> HR</a></li>
            <li class="nav-item"><a class="nav-link" href="/?page=reports"><i class="bi bi-file-earmark-bar-graph"></i> Reports</a></li>
            <li class="nav-item"><a class="nav-link" href="/?page=settings"><i class="bi bi-gear"></i> Settings</a></li>
            <li class="nav-item mt-3"><a class="nav-link text-danger" href="/?action=logout"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
        </ul>
    </nav>

    <main class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-0">Network Device Monitoring</h2>
                <p class="text-muted mb-0">Monitor OLTs, switches, and routers via SNMP/Telnet</p>
            </div>
            <div>
                <button class="btn btn-outline-secondary me-2" onclick="refreshAll()">
                    <i class="bi bi-arrow-clockwise"></i> Refresh All
                </button>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDeviceModal">
                    <i class="bi bi-plus-lg"></i> Add Device
                </button>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card stat-card h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="icon bg-primary bg-opacity-10 text-primary me-3">
                            <i class="bi bi-hdd-network"></i>
                        </div>
                        <div>
                            <h3 class="mb-0" id="totalDevices"><?= $stats['total_devices'] ?? 0 ?></h3>
                            <small class="text-muted">Total Devices</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="icon bg-success bg-opacity-10 text-success me-3">
                            <i class="bi bi-check-circle"></i>
                        </div>
                        <div>
                            <h3 class="mb-0" id="onlineDevices"><?= $stats['devices_by_status']['online'] ?? 0 ?></h3>
                            <small class="text-muted">Online</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="icon bg-danger bg-opacity-10 text-danger me-3">
                            <i class="bi bi-x-circle"></i>
                        </div>
                        <div>
                            <h3 class="mb-0" id="offlineDevices"><?= $stats['devices_by_status']['offline'] ?? 0 ?></h3>
                            <small class="text-muted">Offline</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="icon bg-info bg-opacity-10 text-info me-3">
                            <i class="bi bi-router"></i>
                        </div>
                        <div>
                            <h3 class="mb-0" id="totalOnus"><?= $stats['total_onus'] ?? 0 ?></h3>
                            <small class="text-muted">Total ONUs</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <ul class="nav nav-tabs mb-4" id="deviceTabs">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="tab" href="#devicesTab">
                    <i class="bi bi-hdd-network"></i> Devices
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#onusTab">
                    <i class="bi bi-router"></i> ONUs/ONTs
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#interfacesTab">
                    <i class="bi bi-ethernet"></i> Interfaces
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#telnetTab">
                    <i class="bi bi-terminal"></i> Telnet Console
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#graphsTab">
                    <i class="bi bi-graph-up"></i> Traffic Graphs
                </a>
            </li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="devicesTab">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Network Devices</h5>
                        <div>
                            <select class="form-select form-select-sm d-inline-block w-auto" id="deviceTypeFilter">
                                <option value="">All Types</option>
                                <option value="olt">OLT</option>
                                <option value="switch">Switch</option>
                                <option value="router">Router</option>
                                <option value="ap">Access Point</option>
                            </select>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Name</th>
                                        <th>Type</th>
                                        <th>Vendor/Model</th>
                                        <th>IP Address</th>
                                        <th>Status</th>
                                        <th>Last Polled</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="devicesTableBody">
                                    <?php if (!empty($devices)): ?>
                                        <?php foreach ($devices as $device): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($device['name']) ?></strong></td>
                                            <td><span class="badge bg-secondary"><?= strtoupper($device['device_type']) ?></span></td>
                                            <td><?= htmlspecialchars(($device['vendor'] ?? '') . ' ' . ($device['model'] ?? '')) ?></td>
                                            <td><code><?= htmlspecialchars($device['ip_address']) ?></code></td>
                                            <td><span class="device-status <?= $device['status'] ?>"><?= ucfirst($device['status']) ?></span></td>
                                            <td><?= $device['last_polled'] ? date('M d, H:i', strtotime($device['last_polled'])) : 'Never' ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-primary" onclick="testDevice(<?= $device['id'] ?>)" title="Test Connection">
                                                        <i class="bi bi-plug"></i>
                                                    </button>
                                                    <button class="btn btn-outline-info" onclick="pollDevice(<?= $device['id'] ?>)" title="Poll Device">
                                                        <i class="bi bi-arrow-repeat"></i>
                                                    </button>
                                                    <button class="btn btn-outline-secondary" onclick="editDevice(<?= $device['id'] ?>)" title="Edit">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button class="btn btn-outline-danger" onclick="deleteDevice(<?= $device['id'] ?>)" title="Delete">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4 text-muted">
                                                <i class="bi bi-hdd-network fs-1 d-block mb-2"></i>
                                                No devices configured. Click "Add Device" to get started.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="onusTab">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">ONU/ONT Devices</h5>
                        <div class="d-flex gap-2">
                            <select class="form-select form-select-sm" id="onuDeviceFilter" style="width: 200px;">
                                <option value="">All OLTs</option>
                                <?php foreach ($devices ?? [] as $device): ?>
                                    <?php if ($device['device_type'] === 'olt'): ?>
                                    <option value="<?= $device['id'] ?>"><?= htmlspecialchars($device['name']) ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                            <select class="form-select form-select-sm" id="onuStatusFilter" style="width: 150px;">
                                <option value="">All Status</option>
                                <option value="online">Online</option>
                                <option value="offline">Offline</option>
                                <option value="los">LOS</option>
                            </select>
                            <button class="btn btn-sm btn-outline-primary" onclick="refreshOnus()">
                                <i class="bi bi-arrow-repeat"></i> Refresh
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row g-3" id="onusContainer">
                            <?php if (!empty($onus)): ?>
                                <?php foreach ($onus as $onu): ?>
                                <div class="col-md-4">
                                    <div class="card onu-card <?= $onu['status'] ?>">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h6 class="mb-0"><?= htmlspecialchars($onu['serial_number'] ?? $onu['onu_id']) ?></h6>
                                                <span class="badge bg-<?= $onu['status'] === 'online' ? 'success' : ($onu['status'] === 'offline' ? 'danger' : 'warning') ?>">
                                                    <?= ucfirst($onu['status']) ?>
                                                </span>
                                            </div>
                                            <p class="text-muted small mb-2">
                                                <i class="bi bi-geo-alt"></i> Port: <?= htmlspecialchars($onu['pon_port'] ?? 'N/A') ?>
                                            </p>
                                            <?php if ($onu['customer_name']): ?>
                                            <p class="mb-2">
                                                <i class="bi bi-person"></i> <?= htmlspecialchars($onu['customer_name']) ?>
                                            </p>
                                            <?php endif; ?>
                                            <div class="d-flex justify-content-between small">
                                                <span>RX: <span class="signal-indicator <?= $onu['rx_power'] > -25 ? 'signal-good' : ($onu['rx_power'] > -28 ? 'signal-warning' : 'signal-bad') ?>"><?= $onu['rx_power'] ? $onu['rx_power'] . ' dBm' : 'N/A' ?></span></span>
                                                <span>TX: <?= $onu['tx_power'] ? $onu['tx_power'] . ' dBm' : 'N/A' ?></span>
                                            </div>
                                            <?php if ($onu['distance']): ?>
                                            <p class="text-muted small mt-1 mb-0">Distance: <?= $onu['distance'] ?>m</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="col-12 text-center py-5 text-muted">
                                    <i class="bi bi-router fs-1 d-block mb-2"></i>
                                    No ONUs found. Poll an OLT device to discover ONUs.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="interfacesTab">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Device Interfaces</h5>
                        <select class="form-select form-select-sm w-auto" id="interfaceDeviceFilter">
                            <option value="">Select Device</option>
                            <?php foreach ($devices ?? [] as $device): ?>
                            <option value="<?= $device['id'] ?>"><?= htmlspecialchars($device['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Index</th>
                                        <th>Description</th>
                                        <th>Status</th>
                                        <th>In Traffic</th>
                                        <th>Out Traffic</th>
                                        <th>Errors</th>
                                    </tr>
                                </thead>
                                <tbody id="interfacesTableBody">
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">
                                            Select a device to view interfaces
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="telnetTab">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Telnet Console</h5>
                        <select class="form-select form-select-sm w-auto" id="telnetDeviceSelect">
                            <option value="">Select Device</option>
                            <?php foreach ($devices ?? [] as $device): ?>
                                <?php if (!empty($device['telnet_username'])): ?>
                                <option value="<?= $device['id'] ?>"><?= htmlspecialchars($device['name']) ?> (<?= $device['ip_address'] ?>)</option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Quick Commands</label>
                            <div class="btn-group flex-wrap">
                                <button class="btn btn-outline-secondary btn-sm" onclick="sendCommand('display version')">Version</button>
                                <button class="btn btn-outline-secondary btn-sm" onclick="sendCommand('display interface brief')">Interfaces</button>
                                <button class="btn btn-outline-secondary btn-sm" onclick="sendCommand('display ont info summary 0')">ONU Summary</button>
                                <button class="btn btn-outline-secondary btn-sm" onclick="sendCommand('display current-configuration')">Config</button>
                            </div>
                        </div>
                        <div class="input-group mb-3">
                            <input type="text" class="form-control font-monospace" id="telnetCommand" placeholder="Enter command...">
                            <button class="btn btn-primary" onclick="sendCommand()">
                                <i class="bi bi-send"></i> Send
                            </button>
                        </div>
                        <div class="bg-dark text-success p-3 rounded font-monospace" style="height: 400px; overflow-y: auto;" id="telnetOutput">
                            <pre class="mb-0">Telnet console ready. Select a device and enter commands.</pre>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="graphsTab">
                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <select class="form-select" id="graphDeviceSelect" onchange="loadDeviceGraphs()">
                            <option value="">Select Device</option>
                            <?php foreach ($devices ?? [] as $device): ?>
                            <option value="<?= $device['id'] ?>"><?= htmlspecialchars($device['name']) ?> (<?= $device['ip_address'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" id="graphTimeRange" onchange="loadDeviceGraphs()">
                            <option value="1">Last 1 Hour</option>
                            <option value="6">Last 6 Hours</option>
                            <option value="24" selected>Last 24 Hours</option>
                            <option value="168">Last 7 Days</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-outline-primary" onclick="loadDeviceGraphs()">
                            <i class="bi bi-arrow-repeat"></i> Refresh
                        </button>
                    </div>
                </div>

                <div class="row g-4" id="graphsContainer">
                    <div class="col-12 text-center text-muted py-5">
                        <i class="bi bi-graph-up fs-1 d-block mb-3"></i>
                        <p>Select a device to view traffic graphs</p>
                    </div>
                </div>

                <div class="row g-4 mt-4">
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header bg-white">
                                <h6 class="mb-0"><i class="bi bi-arrow-down-up text-primary me-2"></i>Total Bandwidth (In/Out)</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="totalBandwidthChart" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header bg-white">
                                <h6 class="mb-0"><i class="bi bi-exclamation-triangle text-warning me-2"></i>Interface Errors</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="errorsChart" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Interface Traffic Summary</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Interface</th>
                                        <th>Avg In</th>
                                        <th>Avg Out</th>
                                        <th>Max In</th>
                                        <th>Max Out</th>
                                        <th>Errors</th>
                                        <th>Graph</th>
                                    </tr>
                                </thead>
                                <tbody id="trafficSummaryBody">
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">Select a device to view traffic data</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <div class="modal fade" id="addDeviceModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-lg"></i> Add Network Device</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addDeviceForm" onsubmit="saveDevice(event)">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <div class="modal-body">
                        <ul class="nav nav-pills mb-3" id="addDeviceTabs">
                            <li class="nav-item">
                                <a class="nav-link active" data-bs-toggle="pill" href="#basicInfo">Basic Info</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="pill" href="#snmpConfig">SNMP</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="pill" href="#telnetConfig">Telnet/SSH</a>
                            </li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="basicInfo">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Device Name *</label>
                                        <input type="text" class="form-control" name="name" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Device Type *</label>
                                        <select class="form-select" name="device_type" required>
                                            <option value="olt">OLT (Optical Line Terminal)</option>
                                            <option value="switch">Switch</option>
                                            <option value="router">Router</option>
                                            <option value="ap">Access Point</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Vendor</label>
                                        <select class="form-select" name="vendor">
                                            <option value="">Select Vendor</option>
                                            <option value="Huawei">Huawei</option>
                                            <option value="ZTE">ZTE</option>
                                            <option value="Cisco">Cisco</option>
                                            <option value="Mikrotik">Mikrotik</option>
                                            <option value="Ubiquiti">Ubiquiti</option>
                                            <option value="Nokia">Nokia</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Model</label>
                                        <input type="text" class="form-control" name="model" placeholder="e.g., MA5683T">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">IP Address *</label>
                                        <input type="text" class="form-control" name="ip_address" required pattern="^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$" placeholder="e.g., 192.168.1.1">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Location</label>
                                        <input type="text" class="form-control" name="location" placeholder="e.g., Main POP">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Notes</label>
                                        <textarea class="form-control" name="notes" rows="2"></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="snmpConfig">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">SNMP Version</label>
                                        <select class="form-select" name="snmp_version" onchange="toggleSnmpV3(this.value)">
                                            <option value="v2c">SNMP v2c</option>
                                            <option value="v1">SNMP v1</option>
                                            <option value="v3">SNMP v3</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">SNMP Port</label>
                                        <input type="number" class="form-control" name="snmp_port" value="161">
                                    </div>
                                    <div class="col-md-6" id="snmpCommunityGroup">
                                        <label class="form-label">Community String</label>
                                        <input type="text" class="form-control" name="snmp_community" value="public">
                                    </div>
                                    <div class="col-12" id="snmpV3Group" style="display: none;">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label">Username</label>
                                                <input type="text" class="form-control" name="snmpv3_username">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Auth Protocol</label>
                                                <select class="form-select" name="snmpv3_auth_protocol">
                                                    <option value="SHA">SHA</option>
                                                    <option value="MD5">MD5</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Auth Password</label>
                                                <input type="password" class="form-control" name="snmpv3_auth_password">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Privacy Protocol</label>
                                                <select class="form-select" name="snmpv3_priv_protocol">
                                                    <option value="AES">AES</option>
                                                    <option value="DES">DES</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Privacy Password</label>
                                                <input type="password" class="form-control" name="snmpv3_priv_password">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="telnetConfig">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Telnet Username</label>
                                        <input type="text" class="form-control" name="telnet_username">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Telnet Password</label>
                                        <input type="password" class="form-control" name="telnet_password">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Telnet Port</label>
                                        <input type="number" class="form-control" name="telnet_port" value="23">
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check mt-4">
                                            <input type="checkbox" class="form-check-input" name="ssh_enabled" id="sshEnabled">
                                            <label class="form-check-label" for="sshEnabled">Enable SSH</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">SSH Port</label>
                                        <input type="number" class="form-control" name="ssh_port" value="22">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Device</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="testResultModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Connection Test Results</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="testResultContent">
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSnmpV3(version) {
            const v3Group = document.getElementById('snmpV3Group');
            const communityGroup = document.getElementById('snmpCommunityGroup');
            if (version === 'v3') {
                v3Group.style.display = 'block';
                communityGroup.style.display = 'none';
            } else {
                v3Group.style.display = 'none';
                communityGroup.style.display = 'block';
            }
        }

        async function saveDevice(e) {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);
            formData.append('action', 'add_device');
            
            try {
                const response = await fetch('/?page=devices', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + data.error);
                }
            } catch (err) {
                alert('Error saving device');
            }
        }

        async function testDevice(id) {
            try {
                const response = await fetch('/?page=devices', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({action: 'test_device', id: id})
                });
                const data = await response.json();
                
                let html = '<table class="table table-sm">';
                html += '<tr><td><i class="bi bi-' + (data.results?.ping ? 'check-circle text-success' : 'x-circle text-danger') + '"></i></td><td>Ping</td><td>' + (data.results?.ping ? 'OK' : 'Failed') + '</td></tr>';
                html += '<tr><td><i class="bi bi-' + (data.results?.snmp ? 'check-circle text-success' : 'x-circle text-danger') + '"></i></td><td>SNMP</td><td>' + (data.results?.snmp ? 'OK' : 'Failed') + '</td></tr>';
                html += '<tr><td><i class="bi bi-' + (data.results?.telnet ? 'check-circle text-success' : 'x-circle text-danger') + '"></i></td><td>Telnet</td><td>' + (data.results?.telnet ? 'OK' : 'Failed') + '</td></tr>';
                if (data.results?.snmp_info) {
                    html += '<tr><td colspan="3"><small class="text-muted">' + data.results.snmp_info + '</small></td></tr>';
                }
                html += '</table>';
                
                document.getElementById('testResultContent').innerHTML = html;
                new bootstrap.Modal(document.getElementById('testResultModal')).show();
            } catch (err) {
                alert('Error testing device');
            }
        }

        async function pollDevice(id) {
            try {
                const response = await fetch('/?page=devices', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({action: 'poll_device', id: id})
                });
                const data = await response.json();
                if (data.success) {
                    alert('Device polled successfully. Found ' + (data.interfaces?.length || 0) + ' interfaces.');
                    location.reload();
                } else {
                    alert('Poll failed: ' + data.error);
                }
            } catch (err) {
                alert('Error polling device');
            }
        }

        async function deleteDevice(id) {
            if (!confirm('Are you sure you want to delete this device?')) return;
            
            try {
                const response = await fetch('/?page=devices', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({action: 'delete_device', id: id})
                });
                const data = await response.json();
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + data.error);
                }
            } catch (err) {
                alert('Error deleting device');
            }
        }

        function editDevice(id) {
            alert('Edit functionality coming soon');
        }

        function refreshAll() {
            location.reload();
        }

        function refreshOnus() {
            const deviceId = document.getElementById('onuDeviceFilter').value;
            const status = document.getElementById('onuStatusFilter').value;
            window.location.href = '/?page=devices&tab=onus&device=' + deviceId + '&status=' + status;
        }

        async function sendCommand(cmd) {
            const deviceId = document.getElementById('telnetDeviceSelect').value;
            if (!deviceId) {
                alert('Please select a device first');
                return;
            }
            
            const command = cmd || document.getElementById('telnetCommand').value;
            if (!command) return;
            
            const output = document.getElementById('telnetOutput');
            output.innerHTML += '\n<span class="text-warning">&gt; ' + command + '</span>\n';
            
            try {
                const response = await fetch('/?page=devices', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({action: 'telnet_command', id: deviceId, command: command})
                });
                const data = await response.json();
                if (data.success) {
                    output.innerHTML += '<pre>' + data.output + '</pre>';
                } else {
                    output.innerHTML += '<span class="text-danger">Error: ' + data.error + '</span>\n';
                }
            } catch (err) {
                output.innerHTML += '<span class="text-danger">Connection error</span>\n';
            }
            
            output.scrollTop = output.scrollHeight;
            document.getElementById('telnetCommand').value = '';
        }

        document.getElementById('telnetCommand')?.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') sendCommand();
        });

        document.getElementById('interfaceDeviceFilter')?.addEventListener('change', async function() {
            const deviceId = this.value;
            if (!deviceId) return;
            
            try {
                const response = await fetch('/?page=devices', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({action: 'get_interfaces', id: deviceId})
                });
                const data = await response.json();
                
                const tbody = document.getElementById('interfacesTableBody');
                if (data.interfaces?.length) {
                    tbody.innerHTML = data.interfaces.map(i => `
                        <tr>
                            <td>${i.if_index}</td>
                            <td>${i.if_descr}</td>
                            <td><span class="badge bg-${i.if_status === 'up' ? 'success' : 'danger'}">${i.if_status}</span></td>
                            <td>${formatBytes(i.in_octets)}</td>
                            <td>${formatBytes(i.out_octets)}</td>
                            <td>${i.in_errors + i.out_errors}</td>
                        </tr>
                    `).join('');
                } else {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No interfaces found</td></tr>';
                }
            } catch (err) {
                console.error(err);
            }
        });

        function formatBytes(bytes) {
            if (!bytes) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        function formatBits(bps) {
            if (!bps || bps < 0) return '0 bps';
            const k = 1000;
            const sizes = ['bps', 'Kbps', 'Mbps', 'Gbps', 'Tbps'];
            const i = Math.floor(Math.log(bps) / Math.log(k));
            return parseFloat((bps / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        let bandwidthChart = null;
        let errorsChart = null;
        let interfaceCharts = {};

        async function loadDeviceGraphs() {
            const deviceId = document.getElementById('graphDeviceSelect').value;
            const hours = document.getElementById('graphTimeRange').value;
            
            if (!deviceId) {
                document.getElementById('graphsContainer').innerHTML = `
                    <div class="col-12 text-center text-muted py-5">
                        <i class="bi bi-graph-up fs-1 d-block mb-3"></i>
                        <p>Select a device to view traffic graphs</p>
                    </div>`;
                return;
            }

            try {
                const response = await fetch('/?page=devices', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({action: 'get_traffic_summary', device_id: deviceId, hours: hours})
                });
                const data = await response.json();
                
                if (data.success && data.data) {
                    renderTrafficSummary(data.data);
                    renderBandwidthChart(data.data);
                    renderErrorsChart(data.data);
                }
            } catch (err) {
                console.error('Error loading graphs:', err);
            }
        }

        function renderTrafficSummary(interfaces) {
            const tbody = document.getElementById('trafficSummaryBody');
            if (!interfaces.length) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No traffic data available. Poll the device to collect data.</td></tr>';
                return;
            }
            
            tbody.innerHTML = interfaces.map(i => `
                <tr>
                    <td><strong>${i.if_descr || 'Port ' + i.if_index}</strong></td>
                    <td><span class="text-success">${formatBits(i.avg_in_rate)}</span></td>
                    <td><span class="text-primary">${formatBits(i.avg_out_rate)}</span></td>
                    <td><span class="text-success">${formatBits(i.max_in_rate)}</span></td>
                    <td><span class="text-primary">${formatBits(i.max_out_rate)}</span></td>
                    <td><span class="badge ${parseInt(i.total_in_errors) + parseInt(i.total_out_errors) > 0 ? 'bg-warning' : 'bg-secondary'}">
                        ${parseInt(i.total_in_errors) + parseInt(i.total_out_errors)}
                    </span></td>
                    <td><button class="btn btn-sm btn-outline-info" onclick="showInterfaceGraph(${i.id}, '${i.if_descr}')">
                        <i class="bi bi-graph-up"></i>
                    </button></td>
                </tr>
            `).join('');
        }

        function renderBandwidthChart(interfaces) {
            const ctx = document.getElementById('totalBandwidthChart').getContext('2d');
            
            if (bandwidthChart) {
                bandwidthChart.destroy();
            }

            const labels = interfaces.map(i => i.if_descr || 'Port ' + i.if_index).slice(0, 10);
            const inData = interfaces.map(i => Math.round(i.avg_in_rate / 1000000)).slice(0, 10);
            const outData = interfaces.map(i => Math.round(i.avg_out_rate / 1000000)).slice(0, 10);

            bandwidthChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'In (Mbps)',
                            data: inData,
                            backgroundColor: 'rgba(40, 167, 69, 0.7)',
                            borderColor: 'rgba(40, 167, 69, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Out (Mbps)',
                            data: outData,
                            backgroundColor: 'rgba(13, 110, 253, 0.7)',
                            borderColor: 'rgba(13, 110, 253, 1)',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top' },
                        title: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: { display: true, text: 'Mbps' }
                        }
                    }
                }
            });
        }

        function renderErrorsChart(interfaces) {
            const ctx = document.getElementById('errorsChart').getContext('2d');
            
            if (errorsChart) {
                errorsChart.destroy();
            }

            const labels = interfaces.map(i => i.if_descr || 'Port ' + i.if_index).slice(0, 10);
            const inErrors = interfaces.map(i => parseInt(i.total_in_errors) || 0).slice(0, 10);
            const outErrors = interfaces.map(i => parseInt(i.total_out_errors) || 0).slice(0, 10);

            errorsChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'In Errors',
                            data: inErrors,
                            backgroundColor: 'rgba(255, 193, 7, 0.7)',
                            borderColor: 'rgba(255, 193, 7, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Out Errors',
                            data: outErrors,
                            backgroundColor: 'rgba(220, 53, 69, 0.7)',
                            borderColor: 'rgba(220, 53, 69, 1)',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top' }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: { display: true, text: 'Error Count' }
                        }
                    }
                }
            });
        }

        async function showInterfaceGraph(interfaceId, name) {
            const hours = document.getElementById('graphTimeRange').value;
            
            try {
                const response = await fetch('/?page=devices', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({action: 'get_interface_history', interface_id: interfaceId, hours: hours})
                });
                const data = await response.json();
                
                if (data.success && data.data.length) {
                    showInterfaceModal(name, data.data);
                } else {
                    alert('No historical data available for this interface. Poll the device to collect data.');
                }
            } catch (err) {
                console.error('Error loading interface history:', err);
            }
        }

        function showInterfaceModal(name, history) {
            const modal = document.createElement('div');
            modal.className = 'modal fade';
            modal.innerHTML = `
                <div class="modal-dialog modal-xl">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="bi bi-graph-up"></i> Traffic Graph: ${name}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <canvas id="interfaceDetailChart" height="300"></canvas>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
            
            modal.addEventListener('shown.bs.modal', () => {
                const ctx = document.getElementById('interfaceDetailChart').getContext('2d');
                
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: history.map(h => new Date(h.recorded_at).toLocaleTimeString()),
                        datasets: [
                            {
                                label: 'In (bps)',
                                data: history.map(h => h.in_rate),
                                borderColor: 'rgba(40, 167, 69, 1)',
                                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                                fill: true,
                                tension: 0.4
                            },
                            {
                                label: 'Out (bps)',
                                data: history.map(h => h.out_rate),
                                borderColor: 'rgba(13, 110, 253, 1)',
                                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                                fill: true,
                                tension: 0.4
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { intersect: false, mode: 'index' },
                        plugins: {
                            legend: { position: 'top' },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return context.dataset.label + ': ' + formatBits(context.raw);
                                    }
                                }
                            }
                        },
                        scales: {
                            x: { display: true, title: { display: true, text: 'Time' } },
                            y: {
                                display: true,
                                title: { display: true, text: 'Traffic (bps)' },
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) { return formatBits(value); }
                                }
                            }
                        }
                    }
                });
            });
            
            modal.addEventListener('hidden.bs.modal', () => {
                modal.remove();
            });
        }
    </script>
</body>
</html>
