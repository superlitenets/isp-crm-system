<?php
$fleet = new \App\FleetManagement();
$fleetTab = $_GET['fleet_tab'] ?? 'overview';
$vehicles = $fleet->getVehicles();
$employees = $fleet->getEmployees();
$csrfToken = \App\Auth::generateToken();
$fleetPage = ($_GET['page'] ?? 'inventory') === 'isp_inventory' ? 'isp_inventory' : 'inventory';
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>

<style>
    .fleet-map { height: 500px; width: 100%; border-radius: 0.375rem; }
    .fleet-map-lg { height: 600px; width: 100%; border-radius: 0.375rem; }
    .vehicle-status-active { color: #198754; }
    .vehicle-status-inactive { color: #6c757d; }
    .vehicle-status-maintenance { color: #ffc107; }
    .acc-on { color: #198754; font-weight: bold; }
    .acc-off { color: #dc3545; }
    .vehicle-info-panel { background: #f8f9fa; border-radius: 0.375rem; padding: 1rem; }
    .playback-controls { background: #f8f9fa; border-radius: 0.375rem; padding: 0.75rem; }
</style>

<?php if (!$fleet->isConfigured()): ?>
<div class="alert alert-warning alert-dismissible fade show">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <strong>Fleet Tracking Not Configured.</strong> Protrack API credentials are not set.
    <a href="?page=settings" class="alert-link">Go to Settings</a> to configure Protrack integration.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<ul class="nav nav-pills mb-4">
    <li class="nav-item">
        <a class="nav-link <?= $fleetTab === 'overview' ? 'active' : '' ?>" href="?page=<?= $fleetPage ?>&tab=fleet&fleet_tab=overview">
            <i class="bi bi-speedometer2"></i> Overview
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $fleetTab === 'vehicles' ? 'active' : '' ?>" href="?page=<?= $fleetPage ?>&tab=fleet&fleet_tab=vehicles">
            <i class="bi bi-truck"></i> Vehicles
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $fleetTab === 'tracking' ? 'active' : '' ?>" href="?page=<?= $fleetPage ?>&tab=fleet&fleet_tab=tracking">
            <i class="bi bi-geo-alt"></i> Live Tracking
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $fleetTab === 'playback' ? 'active' : '' ?>" href="?page=<?= $fleetPage ?>&tab=fleet&fleet_tab=playback">
            <i class="bi bi-play-circle"></i> Playback
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $fleetTab === 'geofences' ? 'active' : '' ?>" href="?page=<?= $fleetPage ?>&tab=fleet&fleet_tab=geofences">
            <i class="bi bi-circle"></i> Geofences
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $fleetTab === 'alarms' ? 'active' : '' ?>" href="?page=<?= $fleetPage ?>&tab=fleet&fleet_tab=alarms">
            <i class="bi bi-bell"></i> Alarms
            <?php $stats = $fleet->getStats(); if ($stats['unacknowledged_alarms'] > 0): ?>
            <span class="badge bg-danger"><?= $stats['unacknowledged_alarms'] ?></span>
            <?php endif; ?>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $fleetTab === 'commands' ? 'active' : '' ?>" href="?page=<?= $fleetPage ?>&tab=fleet&fleet_tab=commands">
            <i class="bi bi-terminal"></i> Commands
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $fleetTab === 'reports' ? 'active' : '' ?>" href="?page=<?= $fleetPage ?>&tab=fleet&fleet_tab=reports">
            <i class="bi bi-file-earmark-bar-graph"></i> Reports
        </a>
    </li>
</ul>

<?php if ($fleetTab === 'overview'): ?>
<?php $stats = $stats ?? $fleet->getStats(); ?>
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h6 class="card-title"><i class="bi bi-truck"></i> Total Vehicles</h6>
                <h2 class="mb-0"><?= $stats['total_vehicles'] ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h6 class="card-title"><i class="bi bi-check-circle"></i> Active</h6>
                <h2 class="mb-0"><?= $stats['active_vehicles'] ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <h6 class="card-title"><i class="bi bi-person-badge"></i> Assigned</h6>
                <h2 class="mb-0"><?= $stats['assigned_vehicles'] ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-danger text-white">
            <div class="card-body">
                <h6 class="card-title"><i class="bi bi-bell-fill"></i> Unack. Alarms</h6>
                <h2 class="mb-0"><?= $stats['unacknowledged_alarms'] ?></h2>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-map"></i> Live Vehicle Map</h5>
        <div class="d-flex align-items-center gap-3">
            <span id="fleet-live-summary"></span>
            <span class="badge bg-secondary" id="map-update-status">Updating...</span>
        </div>
    </div>
    <div class="card-body p-0">
        <div id="overview-map" class="fleet-map"></div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h6 class="mb-0"><i class="bi bi-list-ul"></i> Vehicle Status</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Vehicle</th>
                        <th>Plate</th>
                        <th>Status</th>
                        <th>Speed</th>
                        <th>ACC</th>
                        <th>Last Update</th>
                    </tr>
                </thead>
                <tbody id="fleet-live-table">
                    <tr><td colspan="6" class="text-center text-muted py-3">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function() {
    const map = L.map('overview-map').setView([-1.286389, 36.817223], 7);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 19
    }).addTo(map);

    const markers = L.markerClusterGroup();
    map.addLayer(markers);
    let firstLoad = true;

    function formatTime(ts) {
        if (!ts) return 'N/A';
        const d = new Date(ts * 1000);
        return d.toLocaleString();
    }

    function accLabel(status) {
        if (status === 1) return '<span class="acc-on">ON</span>';
        if (status === 0) return '<span class="acc-off">OFF</span>';
        return '<span class="text-muted">Unknown</span>';
    }

    function statusInfo(t) {
        const ds = t.datastatus;
        if (ds === 1) return { text: 'Moving', cls: 'text-success', icon: '🟢' };
        if (ds === 2) return { text: 'Idle', cls: 'text-primary', icon: '🔵' };
        if (ds === 3) return { text: 'Recently Offline', cls: 'text-warning', icon: '🟡' };
        if (ds === 4) return { text: 'Offline', cls: 'text-danger', icon: '🔴' };
        return { text: 'Unknown', cls: 'text-muted', icon: '⚪' };
    }

    function updatePositions() {
        document.getElementById('map-update-status').textContent = 'Updating...';
        fetch('?page=<?= $fleetPage ?>&tab=fleet&action=ajax_track')
            .then(r => r.json())
            .then(data => {
                const trackingData = data.tracking || data.record || [];
                if (data.success || (data.code === 0 && data.record)) {
                    markers.clearLayers();
                    const vehicleMap = {};
                    if (data.vehicles) {
                        data.vehicles.forEach(function(v) {
                            if (v.imei) vehicleMap[v.imei] = { name: v.name || '', plate: v.plate_number || '', type: v.vehicle_type || 'car' };
                        });
                    }
                    <?php foreach ($vehicles as $v): ?>
                    if (!vehicleMap['<?= htmlspecialchars($v['imei'] ?? '') ?>']) {
                        vehicleMap['<?= htmlspecialchars($v['imei'] ?? '') ?>'] = {
                            name: '<?= htmlspecialchars(addslashes($v['name'] ?? '')) ?>',
                            plate: '<?= htmlspecialchars(addslashes($v['plate_number'] ?? '')) ?>',
                            type: '<?= htmlspecialchars($v['vehicle_type'] ?? 'car') ?>'
                        };
                    }
                    <?php endforeach; ?>

                    let moving = 0, idle = 0, offline = 0, total = 0;
                    const listHtml = [];

                    trackingData.forEach(function(t) {
                        total++;
                        const st = statusInfo(t);
                        if (t.datastatus === 1) moving++;
                        else if (t.datastatus === 2) idle++;
                        else offline++;

                        if (t.latitude && t.longitude && t.latitude !== 0 && t.longitude !== 0) {
                            const vInfo = vehicleMap[t.imei] || { name: t.imei, plate: '' };
                            const iconColor = t.datastatus === 1 ? '#198754' : (t.datastatus === 2 ? '#0d6efd' : '#dc3545');
                            const markerIcon = L.divIcon({
                                className: 'custom-marker',
                                html: '<div style="background:' + iconColor + ';width:12px;height:12px;border-radius:50%;border:2px solid white;box-shadow:0 1px 3px rgba(0,0,0,0.3);"></div>',
                                iconSize: [12, 12],
                                iconAnchor: [6, 6]
                            });
                            const m = L.marker([t.latitude, t.longitude], { icon: markerIcon });
                            const mileageKm = t.mileage > 0 ? (t.mileage / 1000).toFixed(1) : '0';
                            m.bindPopup(
                                '<strong>' + vInfo.name + '</strong><br>' +
                                (vInfo.plate ? 'Plate: ' + vInfo.plate + '<br>' : '') +
                                'Status: <span class="' + st.cls + '">' + st.text + '</span><br>' +
                                'Speed: ' + (t.speed || 0) + ' km/h<br>' +
                                'ACC: ' + accLabel(t.accstatus) + '<br>' +
                                'Mileage: ' + mileageKm + ' km<br>' +
                                'Updated: ' + formatTime(t.hearttime)
                            );
                            markers.addLayer(m);
                            listHtml.push(
                                '<tr>' +
                                '<td><strong>' + vInfo.name + '</strong></td>' +
                                '<td>' + (vInfo.plate || '-') + '</td>' +
                                '<td><span class="' + st.cls + '">' + st.icon + ' ' + st.text + '</span></td>' +
                                '<td>' + (t.speed || 0) + ' km/h</td>' +
                                '<td>' + accLabel(t.accstatus) + '</td>' +
                                '<td><small>' + formatTime(t.hearttime) + '</small></td>' +
                                '</tr>'
                            );
                        }
                    });
                    if (firstLoad && markers.getLayers().length > 0) {
                        map.fitBounds(markers.getBounds(), { padding: [30, 30] });
                        firstLoad = false;
                    }

                    const summaryEl = document.getElementById('fleet-live-summary');
                    if (summaryEl) {
                        summaryEl.innerHTML =
                            '<span class="badge bg-success me-2">' + moving + ' Moving</span>' +
                            '<span class="badge bg-primary me-2">' + idle + ' Idle</span>' +
                            '<span class="badge bg-danger me-2">' + offline + ' Offline</span>' +
                            '<span class="badge bg-secondary">' + total + ' Total</span>';
                    }

                    const tableBody = document.getElementById('fleet-live-table');
                    if (tableBody) {
                        tableBody.innerHTML = listHtml.length > 0 ? listHtml.join('') : '<tr><td colspan="6" class="text-center text-muted">No tracking data available</td></tr>';
                    }
                }
                document.getElementById('map-update-status').textContent = 'Last updated: ' + new Date().toLocaleTimeString();
            })
            .catch(function(err) {
                console.error('Tracking error:', err);
                document.getElementById('map-update-status').textContent = 'Update failed';
            });
    }

    updatePositions();
    setInterval(updatePositions, 30000);
})();
</script>

<?php elseif ($fleetTab === 'vehicles'): ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-truck"></i> Vehicle List</h5>
    <div>
        <button class="btn btn-outline-info me-2" id="btn-sync-protrack">
            <i class="bi bi-arrow-repeat"></i> Sync from Protrack
        </button>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#vehicleModal" onclick="openVehicleForm()">
            <i class="bi bi-plus-lg"></i> Add Vehicle
        </button>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Plate #</th>
                        <th>IMEI</th>
                        <th>Type</th>
                        <th>Assigned To</th>
                        <th>Status</th>
                        <th>Last Location</th>
                        <th>Last Update</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($vehicles)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">No vehicles found. Add your first vehicle or sync from Protrack.</td></tr>
                    <?php else: ?>
                    <?php foreach ($vehicles as $v): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($v['name']) ?></strong></td>
                        <td><?= htmlspecialchars($v['plate_number'] ?? '-') ?></td>
                        <td><code><?= htmlspecialchars($v['imei'] ?? '-') ?></code></td>
                        <td><span class="badge bg-secondary"><?= ucfirst(htmlspecialchars($v['vehicle_type'] ?? 'car')) ?></span></td>
                        <td><?= $v['employee_name'] ? htmlspecialchars($v['employee_name']) : '<span class="text-muted">Unassigned</span>' ?></td>
                        <td>
                            <?php
                            $statusClass = match($v['status']) {
                                'active' => 'bg-success',
                                'inactive' => 'bg-secondary',
                                'maintenance' => 'bg-warning text-dark',
                                default => 'bg-secondary'
                            };
                            ?>
                            <span class="badge <?= $statusClass ?>"><?= ucfirst($v['status']) ?></span>
                        </td>
                        <td>
                            <?php if ($v['last_latitude'] && $v['last_longitude']): ?>
                            <small><?= number_format($v['last_latitude'], 5) ?>, <?= number_format($v['last_longitude'], 5) ?></small>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($v['last_update']): ?>
                            <small><?= date('M j, Y H:i', strtotime($v['last_update'])) ?></small>
                            <?php else: ?>
                            <span class="text-muted">Never</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-primary" onclick="editVehicle(<?= htmlspecialchars(json_encode($v)) ?>)" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <a href="?page=<?= $fleetPage ?>&tab=fleet&fleet_tab=tracking&vehicle_id=<?= $v['id'] ?>" class="btn btn-outline-success" title="Track">
                                    <i class="bi bi-geo-alt"></i>
                                </a>
                                <button class="btn btn-outline-danger" onclick="confirmDeleteVehicle(<?= $v['id'] ?>, '<?= htmlspecialchars(addslashes($v['name'])) ?>')" title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="vehicleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="?page=<?= $fleetPage ?>&tab=fleet&action=save_vehicle">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="vehicle_id" id="vehicle_id" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="vehicleModalTitle">Add Vehicle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Vehicle Name *</label>
                            <input type="text" class="form-control" name="name" id="v_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Plate Number</label>
                            <input type="text" class="form-control" name="plate_number" id="v_plate_number">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">IMEI (Tracker)</label>
                            <input type="text" class="form-control" name="imei" id="v_imei">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Vehicle Type</label>
                            <select class="form-select" name="vehicle_type" id="v_vehicle_type">
                                <option value="car">Car</option>
                                <option value="van">Van</option>
                                <option value="truck">Truck</option>
                                <option value="motorcycle">Motorcycle</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Make</label>
                            <input type="text" class="form-control" name="make" id="v_make">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Model</label>
                            <input type="text" class="form-control" name="model" id="v_model">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Year</label>
                            <input type="number" class="form-control" name="year" id="v_year" min="1990" max="2030">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Color</label>
                            <input type="text" class="form-control" name="color" id="v_color">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Assigned Employee</label>
                            <select class="form-select" name="assigned_employee_id" id="v_assigned_employee_id">
                                <option value="">-- Unassigned --</option>
                                <?php foreach ($employees as $emp): ?>
                                <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" id="v_status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="maintenance">Maintenance</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Fuel Rate (L/100km)</label>
                            <input type="number" step="0.1" min="0" class="form-control" name="fuel_rate" id="v_fuel_rate" placeholder="e.g. 8.5">
                            <small class="text-muted">Liters per 100 km for fuel consumption estimates</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" id="v_notes" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save Vehicle</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteVehicleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="?page=<?= $fleetPage ?>&tab=fleet&action=delete_vehicle">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="vehicle_id" id="delete_vehicle_id" value="">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete vehicle <strong id="delete_vehicle_name"></strong>?</p>
                    <p class="text-danger"><small>This action cannot be undone. All tracking data and command history will be lost.</small></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="bi bi-trash"></i> Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openVehicleForm() {
    document.getElementById('vehicleModalTitle').textContent = 'Add Vehicle';
    document.getElementById('vehicle_id').value = '';
    document.getElementById('v_name').value = '';
    document.getElementById('v_plate_number').value = '';
    document.getElementById('v_imei').value = '';
    document.getElementById('v_vehicle_type').value = 'car';
    document.getElementById('v_make').value = '';
    document.getElementById('v_model').value = '';
    document.getElementById('v_year').value = '';
    document.getElementById('v_color').value = '';
    document.getElementById('v_assigned_employee_id').value = '';
    document.getElementById('v_status').value = 'active';
    document.getElementById('v_fuel_rate').value = '';
    document.getElementById('v_notes').value = '';
}

function editVehicle(v) {
    document.getElementById('vehicleModalTitle').textContent = 'Edit Vehicle';
    document.getElementById('vehicle_id').value = v.id;
    document.getElementById('v_name').value = v.name || '';
    document.getElementById('v_plate_number').value = v.plate_number || '';
    document.getElementById('v_imei').value = v.imei || '';
    document.getElementById('v_vehicle_type').value = v.vehicle_type || 'car';
    document.getElementById('v_make').value = v.make || '';
    document.getElementById('v_model').value = v.model || '';
    document.getElementById('v_year').value = v.year || '';
    document.getElementById('v_color').value = v.color || '';
    document.getElementById('v_assigned_employee_id').value = v.assigned_employee_id || '';
    document.getElementById('v_status').value = v.status || 'active';
    document.getElementById('v_fuel_rate').value = v.fuel_rate || '';
    document.getElementById('v_notes').value = v.notes || '';
    new bootstrap.Modal(document.getElementById('vehicleModal')).show();
}

function confirmDeleteVehicle(id, name) {
    document.getElementById('delete_vehicle_id').value = id;
    document.getElementById('delete_vehicle_name').textContent = name;
    new bootstrap.Modal(document.getElementById('deleteVehicleModal')).show();
}

document.getElementById('btn-sync-protrack').addEventListener('click', function() {
    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Syncing...';
    fetch('?page=<?= $fleetPage ?>&tab=fleet&action=ajax_sync_devices')
        .then(r => {
            if (!r.ok) throw new Error('Server returned ' + r.status);
            return r.text();
        })
        .then(text => {
            try {
                return JSON.parse(text);
            } catch(e) {
                console.error('Non-JSON response:', text.substring(0, 500));
                throw new Error('Invalid response from server');
            }
        })
        .then(data => {
            if (data.success) {
                alert('Sync complete! ' + data.added + ' new device(s) added, ' + data.synced + ' total synced.');
                location.reload();
            } else {
                alert('Sync failed: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(err => alert('Sync request failed: ' + err.message))
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Sync from Protrack';
        });
});
</script>

<?php elseif ($fleetTab === 'tracking'): ?>
<?php $selectedVehicleId = (int)($_GET['vehicle_id'] ?? 0); ?>
<div class="row mb-3">
    <div class="col-md-4">
        <label class="form-label"><strong>Select Vehicle</strong></label>
        <select class="form-select" id="tracking-vehicle-select">
            <option value="">-- Select Vehicle --</option>
            <?php foreach ($vehicles as $v): ?>
            <option value="<?= $v['id'] ?>" data-imei="<?= htmlspecialchars($v['imei'] ?? '') ?>" <?= $v['id'] == $selectedVehicleId ? 'selected' : '' ?>>
                <?= htmlspecialchars($v['name']) ?> <?= $v['plate_number'] ? '(' . htmlspecialchars($v['plate_number']) . ')' : '' ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-8 d-flex align-items-end">
        <div class="btn-group">
            <button class="btn btn-danger" id="btn-stop-engine" disabled title="Stop Engine">
                <i class="bi bi-stop-circle"></i> Stop Engine
            </button>
            <button class="btn btn-success" id="btn-restore-engine" disabled title="Restore Engine">
                <i class="bi bi-play-circle"></i> Restore Engine
            </button>
            <button class="btn btn-warning" id="btn-lock-door" disabled title="Lock Door">
                <i class="bi bi-lock"></i> Lock
            </button>
            <button class="btn btn-info" id="btn-unlock-door" disabled title="Unlock Door">
                <i class="bi bi-unlock"></i> Unlock
            </button>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-9">
        <div class="card">
            <div class="card-body p-0">
                <div id="tracking-map" class="fleet-map-lg"></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-info-circle"></i> Vehicle Info</h6></div>
            <div class="card-body vehicle-info-panel" id="vehicle-info-panel">
                <p class="text-muted text-center">Select a vehicle to view info</p>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const map = L.map('tracking-map').setView([-1.286389, 36.817223], 7);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 19
    }).addTo(map);

    let marker = null;
    let trackingInterval = null;
    const select = document.getElementById('tracking-vehicle-select');
    const infoPanel = document.getElementById('vehicle-info-panel');
    const cmdButtons = ['btn-stop-engine', 'btn-restore-engine', 'btn-lock-door', 'btn-unlock-door'];

    function formatTime(ts) {
        if (!ts) return 'N/A';
        const d = new Date(ts * 1000);
        return d.toLocaleString();
    }

    function updateTracking() {
        const vehicleId = select.value;
        if (!vehicleId) return;
        fetch('?page=<?= $fleetPage ?>&tab=fleet&action=ajax_track_vehicle&vehicle_id=' + vehicleId)
            .then(r => r.json())
            .then(data => {
                if (data.success && data.track) {
                    const t = data.track;
                    if (t.latitude && t.longitude && t.latitude !== 0) {
                        const pos = [t.latitude, t.longitude];
                        if (marker) {
                            marker.setLatLng(pos);
                        } else {
                            marker = L.marker(pos).addTo(map);
                        }
                        map.setView(pos, 15);
                        const v = data.vehicle;
                        const label = v ? (v.name || v.plate_number || 'Vehicle') : 'Vehicle';
                        marker.bindPopup('<strong>' + label + '</strong><br>Speed: ' + (t.speed || 0) + ' km/h').openPopup();
                    }
                    const accOn = t.accstatus === 1;
                    const mileage = t.mileage > 0 ? (t.mileage / 1000).toFixed(1) + ' km' : 'N/A';
                    const battery = t.battery >= 0 ? t.battery + '%' : 'N/A';
                    const datastatus = t.datastatus;
                    let statusText = 'Unknown';
                    let statusClass = 'text-muted';
                    if (datastatus === 1) { statusText = 'Online (Moving)'; statusClass = 'text-success'; }
                    else if (datastatus === 2) { statusText = 'Online (Idle)'; statusClass = 'text-primary'; }
                    else if (datastatus === 3) { statusText = 'Offline (Recently)'; statusClass = 'text-warning'; }
                    else if (datastatus === 4) { statusText = 'Offline'; statusClass = 'text-danger'; }
                    infoPanel.innerHTML =
                        '<div class="mb-2"><strong>Status</strong><br><span class="' + statusClass + '">' + statusText + '</span></div>' +
                        '<div class="mb-2"><strong>Speed</strong><br><span class="fs-4">' + (t.speed || 0) + ' km/h</span></div>' +
                        '<div class="mb-2"><strong>ACC</strong><br>' + (accOn ? '<span class="acc-on">ON</span>' : '<span class="acc-off">OFF</span>') + '</div>' +
                        '<div class="mb-2"><strong>Battery</strong><br>' + battery + '</div>' +
                        '<div class="mb-2"><strong>Mileage</strong><br>' + mileage + '</div>' +
                        '<div class="mb-2"><strong>Coordinates</strong><br><small>' + (t.latitude || 0).toFixed(6) + ', ' + (t.longitude || 0).toFixed(6) + '</small></div>' +
                        '<div class="mb-2"><strong>Last Update</strong><br><small>' + formatTime(t.hearttime) + '</small></div>';
                } else if (data.success && !data.track) {
                    infoPanel.innerHTML = '<p class="text-warning text-center">No tracking data available for this vehicle</p>';
                } else {
                    infoPanel.innerHTML = '<p class="text-danger text-center">' + (data.error || 'Tracking failed') + '</p>';
                }
            })
            .catch(err => {
                console.error('Tracking error:', err);
                infoPanel.innerHTML = '<p class="text-danger text-center">Failed to fetch tracking data</p>';
            });
    }

    function sendCommand(command) {
        const vehicleId = select.value;
        if (!vehicleId) return;
        if (!confirm('Send command: ' + command + '?')) return;

        fetch('?page=<?= $fleetPage ?>&tab=fleet&action=ajax_send_command', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'vehicle_id=' + vehicleId + '&command=' + encodeURIComponent(command) + '&csrf_token=' + encodeURIComponent('<?= htmlspecialchars($csrfToken) ?>')
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('Command sent successfully!');
            } else {
                alert('Command failed: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(() => alert('Failed to send command.'));
    }

    select.addEventListener('change', function() {
        if (trackingInterval) clearInterval(trackingInterval);
        if (marker) { map.removeLayer(marker); marker = null; }
        const hasVehicle = !!this.value;
        cmdButtons.forEach(id => document.getElementById(id).disabled = !hasVehicle);
        if (hasVehicle) {
            updateTracking();
            trackingInterval = setInterval(updateTracking, 10000);
        } else {
            infoPanel.innerHTML = '<p class="text-muted text-center">Select a vehicle to view info</p>';
            map.setView([-1.286389, 36.817223], 7);
        }
    });

    document.getElementById('btn-stop-engine').addEventListener('click', () => sendCommand('RELAY,1'));
    document.getElementById('btn-restore-engine').addEventListener('click', () => sendCommand('RELAY,0'));
    document.getElementById('btn-lock-door').addEventListener('click', () => sendCommand('LOCK'));
    document.getElementById('btn-unlock-door').addEventListener('click', () => sendCommand('UNLOCK'));

    if (select.value) select.dispatchEvent(new Event('change'));
})();
</script>

<?php elseif ($fleetTab === 'playback'): ?>
<div class="row mb-3">
    <div class="col-md-3">
        <label class="form-label"><strong>Select Vehicle</strong></label>
        <select class="form-select" id="playback-vehicle-select">
            <option value="">-- Select Vehicle --</option>
            <?php foreach ($vehicles as $v): ?>
            <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['name']) ?> <?= $v['plate_number'] ? '(' . htmlspecialchars($v['plate_number']) . ')' : '' ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label"><strong>Start Date/Time</strong></label>
        <input type="datetime-local" class="form-control" id="playback-begin">
    </div>
    <div class="col-md-3">
        <label class="form-label"><strong>End Date/Time</strong></label>
        <input type="datetime-local" class="form-control" id="playback-end">
    </div>
    <div class="col-md-3 d-flex align-items-end">
        <button class="btn btn-primary w-100" id="btn-fetch-playback">
            <i class="bi bi-search"></i> Fetch Playback
        </button>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div id="playback-map" class="fleet-map-lg"></div>
    </div>
    <div class="card-footer">
        <div class="playback-controls d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-success" id="btn-play" disabled><i class="bi bi-play-fill"></i> Play</button>
            <button class="btn btn-sm btn-warning" id="btn-pause" disabled><i class="bi bi-pause-fill"></i> Pause</button>
            <button class="btn btn-sm btn-secondary" id="btn-reset" disabled><i class="bi bi-skip-start-fill"></i> Reset</button>
            <input type="range" class="form-range flex-grow-1" id="playback-slider" min="0" max="100" value="0" disabled>
            <span class="badge bg-secondary" id="playback-status">No data</span>
        </div>
    </div>
</div>

<script>
(function() {
    const map = L.map('playback-map').setView([-1.286389, 36.817223], 7);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 19
    }).addTo(map);

    let routeLine = null;
    let marker = null;
    let points = [];
    let animIndex = 0;
    let animInterval = null;
    let playing = false;

    const slider = document.getElementById('playback-slider');
    const statusEl = document.getElementById('playback-status');

    function setDefaults() {
        const now = new Date();
        const start = new Date(now);
        start.setHours(0, 0, 0, 0);
        document.getElementById('playback-begin').value = start.toISOString().slice(0, 16);
        document.getElementById('playback-end').value = now.toISOString().slice(0, 16);
    }
    setDefaults();

    document.getElementById('btn-fetch-playback').addEventListener('click', function() {
        const vehicleId = document.getElementById('playback-vehicle-select').value;
        const begin = document.getElementById('playback-begin').value;
        const end = document.getElementById('playback-end').value;
        if (!vehicleId || !begin || !end) { alert('Please select vehicle and date range.'); return; }

        const beginTs = Math.floor(new Date(begin).getTime() / 1000);
        const endTs = Math.floor(new Date(end).getTime() / 1000);

        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Loading...';
        stopAnimation();

        fetch('?page=<?= $fleetPage ?>&tab=fleet&action=ajax_playback&vehicle_id=' + vehicleId + '&begintime=' + beginTs + '&endtime=' + endTs)
            .then(r => r.json())
            .then(data => {
                if (routeLine) { map.removeLayer(routeLine); routeLine = null; }
                if (marker) { map.removeLayer(marker); marker = null; }
                points = [];

                const rawPoints = data.points || data.record || [];
                if ((data.success || data.code === 0) && rawPoints.length > 0) {
                    rawPoints.forEach(function(p) {
                        const lat = p.lat || p.latitude || 0;
                        const lng = p.lng || p.longitude || 0;
                        if (lat && lng && lat !== 0) {
                            points.push([lat, lng]);
                        }
                    });
                }

                if (points.length > 0) {
                    routeLine = L.polyline(points, { color: '#0d6efd', weight: 3 }).addTo(map);
                    map.fitBounds(routeLine.getBounds(), { padding: [30, 30] });
                    marker = L.marker(points[0]).addTo(map);
                    animIndex = 0;
                    slider.max = points.length - 1;
                    slider.value = 0;
                    slider.disabled = false;
                    document.getElementById('btn-play').disabled = false;
                    document.getElementById('btn-reset').disabled = false;
                    statusEl.textContent = points.length + ' points loaded';
                } else {
                    statusEl.textContent = 'No data found';
                    slider.disabled = true;
                }
            })
            .catch(() => { statusEl.textContent = 'Fetch failed'; })
            .finally(() => {
                const btn = document.getElementById('btn-fetch-playback');
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-search"></i> Fetch Playback';
            });
    });

    function stopAnimation() {
        if (animInterval) { clearInterval(animInterval); animInterval = null; }
        playing = false;
        document.getElementById('btn-pause').disabled = true;
    }

    document.getElementById('btn-play').addEventListener('click', function() {
        if (points.length === 0) return;
        playing = true;
        this.disabled = true;
        document.getElementById('btn-pause').disabled = false;
        animInterval = setInterval(function() {
            if (animIndex >= points.length - 1) { stopAnimation(); document.getElementById('btn-play').disabled = false; return; }
            animIndex++;
            marker.setLatLng(points[animIndex]);
            map.panTo(points[animIndex]);
            slider.value = animIndex;
            statusEl.textContent = 'Point ' + (animIndex + 1) + ' / ' + points.length;
        }, 200);
    });

    document.getElementById('btn-pause').addEventListener('click', function() {
        stopAnimation();
        document.getElementById('btn-play').disabled = false;
    });

    document.getElementById('btn-reset').addEventListener('click', function() {
        stopAnimation();
        animIndex = 0;
        if (marker && points.length > 0) {
            marker.setLatLng(points[0]);
            map.setView(points[0], 14);
        }
        slider.value = 0;
        document.getElementById('btn-play').disabled = false;
        statusEl.textContent = 'Reset';
    });

    slider.addEventListener('input', function() {
        animIndex = parseInt(this.value);
        if (marker && points[animIndex]) {
            marker.setLatLng(points[animIndex]);
            map.panTo(points[animIndex]);
            statusEl.textContent = 'Point ' + (animIndex + 1) + ' / ' + points.length;
        }
    });
})();
</script>

<?php elseif ($fleetTab === 'geofences'): ?>
<?php $geofences = $fleet->getGeofences(); ?>
<div class="row">
    <div class="col-md-5">
        <div class="card mb-3">
            <div class="card-header"><h5 class="mb-0"><i class="bi bi-plus-circle"></i> Add Geofence</h5></div>
            <div class="card-body">
                <form method="POST" action="?page=<?= $fleetPage ?>&tab=fleet&action=save_geofence">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <div class="mb-3">
                        <label class="form-label">Name *</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Latitude *</label>
                            <input type="number" step="0.000001" class="form-control" name="latitude" id="geo_lat" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Longitude *</label>
                            <input type="number" step="0.000001" class="form-control" name="longitude" id="geo_lng" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Radius (meters)</label>
                            <input type="number" class="form-control" name="radius" value="500" min="50" max="50000">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Alarm Type</label>
                            <select class="form-select" name="alarm_type">
                                <option value="1">Enter</option>
                                <option value="2" selected>Exit</option>
                                <option value="3">Enter & Exit</option>
                            </select>
                        </div>
                    </div>
                    <p class="text-muted small">Click on the map to set coordinates.</p>
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-save"></i> Save Geofence</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h5 class="mb-0"><i class="bi bi-list"></i> Geofences (<?= count($geofences) ?>)</h5></div>
            <div class="card-body p-0">
                <?php if (empty($geofences)): ?>
                <p class="text-muted text-center py-3 mb-0">No geofences defined.</p>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($geofences as $gf): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <strong><?= htmlspecialchars($gf['name']) ?></strong><br>
                            <small class="text-muted">
                                <?= number_format($gf['latitude'], 5) ?>, <?= number_format($gf['longitude'], 5) ?>
                                | R: <?= $gf['radius'] ?>m
                                | <?= $gf['alarm_type'] == 1 ? 'Enter' : ($gf['alarm_type'] == 2 ? 'Exit' : 'Enter/Exit') ?>
                            </small>
                        </div>
                        <form method="POST" action="?page=<?= $fleetPage ?>&tab=fleet&action=delete_geofence" class="d-inline" onsubmit="return confirm('Delete this geofence?')">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <input type="hidden" name="geofence_id" value="<?= $gf['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-7">
        <div class="card">
            <div class="card-body p-0">
                <div id="geofence-map" class="fleet-map-lg"></div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const map = L.map('geofence-map').setView([-1.286389, 36.817223], 7);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 19
    }).addTo(map);

    const geofences = <?= json_encode(array_map(function($gf) {
        return ['name' => $gf['name'], 'lat' => (float)$gf['latitude'], 'lng' => (float)$gf['longitude'], 'radius' => (int)$gf['radius']];
    }, $geofences)) ?>;

    geofences.forEach(function(gf) {
        if (gf.lat && gf.lng) {
            L.circle([gf.lat, gf.lng], { radius: gf.radius, color: '#0d6efd', fillOpacity: 0.15 })
                .bindPopup('<strong>' + gf.name + '</strong><br>Radius: ' + gf.radius + 'm')
                .addTo(map);
        }
    });

    if (geofences.length > 0) {
        const bounds = geofences.filter(g => g.lat && g.lng).map(g => [g.lat, g.lng]);
        if (bounds.length > 0) map.fitBounds(bounds, { padding: [30, 30] });
    }

    map.on('click', function(e) {
        document.getElementById('geo_lat').value = e.latlng.lat.toFixed(6);
        document.getElementById('geo_lng').value = e.latlng.lng.toFixed(6);
    });
})();
</script>

<?php elseif ($fleetTab === 'alarms'): ?>
<?php
$alarmFilters = [];
if (!empty($_GET['alarm_vehicle_id'])) $alarmFilters['vehicle_id'] = (int)$_GET['alarm_vehicle_id'];
if (isset($_GET['alarm_status']) && $_GET['alarm_status'] !== '') $alarmFilters['acknowledged'] = $_GET['alarm_status'] === '1';
$alarms = $fleet->getAlarms($alarmFilters);
?>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <input type="hidden" name="page" value="<?= $fleetPage ?>">
            <input type="hidden" name="tab" value="fleet">
            <input type="hidden" name="fleet_tab" value="alarms">
            <div class="col-md-3">
                <label class="form-label">Vehicle</label>
                <select class="form-select" name="alarm_vehicle_id">
                    <option value="">All Vehicles</option>
                    <?php foreach ($vehicles as $v): ?>
                    <option value="<?= $v['id'] ?>" <?= ($_GET['alarm_vehicle_id'] ?? '') == $v['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($v['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select class="form-select" name="alarm_status">
                    <option value="">All</option>
                    <option value="0" <?= (isset($_GET['alarm_status']) && $_GET['alarm_status'] === '0') ? 'selected' : '' ?>>Pending</option>
                    <option value="1" <?= (isset($_GET['alarm_status']) && $_GET['alarm_status'] === '1') ? 'selected' : '' ?>>Acknowledged</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-filter"></i> Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-bell"></i> Alarm History (<?= count($alarms) ?>)</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Alarm Type</th>
                        <th>Vehicle</th>
                        <th>Location</th>
                        <th>Speed</th>
                        <th>Time</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($alarms)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No alarms found.</td></tr>
                    <?php else: ?>
                    <?php foreach ($alarms as $alarm): ?>
                    <tr>
                        <td>
                            <span class="badge bg-danger"><?= htmlspecialchars($alarm['alarm_name'] ?? 'Unknown') ?></span>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($alarm['vehicle_name'] ?? 'N/A') ?></strong>
                            <?php if ($alarm['plate_number']): ?>
                            <br><small class="text-muted"><?= htmlspecialchars($alarm['plate_number']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($alarm['latitude'] && $alarm['longitude']): ?>
                            <small><?= number_format($alarm['latitude'], 5) ?>, <?= number_format($alarm['longitude'], 5) ?></small>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $alarm['speed'] ?? 0 ?> km/h</td>
                        <td><small><?= $alarm['alarm_time'] ? date('M j, Y H:i:s', strtotime($alarm['alarm_time'])) : 'N/A' ?></small></td>
                        <td>
                            <?php if ($alarm['acknowledged']): ?>
                            <span class="badge bg-success">Acknowledged</span>
                            <?php else: ?>
                            <span class="badge bg-warning text-dark">Pending</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!$alarm['acknowledged']): ?>
                            <form method="POST" action="?page=<?= $fleetPage ?>&tab=fleet&action=acknowledge_alarm" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                <input type="hidden" name="alarm_id" value="<?= $alarm['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-success" title="Acknowledge">
                                    <i class="bi bi-check-lg"></i> Ack
                                </button>
                            </form>
                            <?php else: ?>
                            <small class="text-muted">
                                <?= $alarm['acknowledged_at'] ? date('M j H:i', strtotime($alarm['acknowledged_at'])) : '' ?>
                            </small>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php elseif ($fleetTab === 'commands'): ?>
<?php
$cmdVehicleId = (int)($_GET['cmd_vehicle_id'] ?? 0);
$commandLog = [];
if ($cmdVehicleId > 0) {
    $commandLog = $fleet->getCommandLog($cmdVehicleId, 50);
}
?>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <input type="hidden" name="page" value="<?= $fleetPage ?>">
            <input type="hidden" name="tab" value="fleet">
            <input type="hidden" name="fleet_tab" value="commands">
            <div class="col-md-4">
                <label class="form-label">Select Vehicle</label>
                <select class="form-select" name="cmd_vehicle_id">
                    <option value="">-- Select Vehicle --</option>
                    <?php foreach ($vehicles as $v): ?>
                    <option value="<?= $v['id'] ?>" <?= $cmdVehicleId == $v['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($v['name']) ?> <?= $v['plate_number'] ? '(' . htmlspecialchars($v['plate_number']) . ')' : '' ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> View Log</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-terminal"></i> Command Log</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Command</th>
                        <th>IMEI</th>
                        <th>Status</th>
                        <th>Response</th>
                        <th>Sent By</th>
                        <th>Sent At</th>
                        <th>Responded At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$cmdVehicleId): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">Select a vehicle to view command history.</td></tr>
                    <?php elseif (empty($commandLog)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No commands sent to this vehicle yet.</td></tr>
                    <?php else: ?>
                    <?php foreach ($commandLog as $cmd): ?>
                    <tr>
                        <td><code><?= htmlspecialchars($cmd['command']) ?></code></td>
                        <td><small><?= htmlspecialchars($cmd['imei'] ?? '') ?></small></td>
                        <td>
                            <?php
                            $cmdStatusClass = match($cmd['status']) {
                                'sent' => 'bg-info',
                                'responded' => 'bg-success',
                                'failed' => 'bg-danger',
                                default => 'bg-secondary'
                            };
                            ?>
                            <span class="badge <?= $cmdStatusClass ?>"><?= ucfirst($cmd['status']) ?></span>
                        </td>
                        <td><?= htmlspecialchars($cmd['response'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($cmd['sent_by_name'] ?? 'System') ?></td>
                        <td><small><?= $cmd['sent_at'] ? date('M j, Y H:i:s', strtotime($cmd['sent_at'])) : 'N/A' ?></small></td>
                        <td><small><?= $cmd['responded_at'] ? date('M j, Y H:i:s', strtotime($cmd['responded_at'])) : '-' ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php elseif ($fleetTab === 'reports'): ?>
<?php
$reportType = $_GET['report'] ?? 'daily';
$reportDate = $_GET['report_date'] ?? date('Y-m-d');
$reportStart = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$reportEnd = $_GET['end_date'] ?? date('Y-m-d');
$reportVehicle = !empty($_GET['vehicle_id']) ? (int)$_GET['vehicle_id'] : null;
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="btn-group flex-wrap" role="group">
            <a href="?page=<?= $fleetPage ?>&tab=fleet&fleet_tab=reports&report=daily" class="btn btn-<?= $reportType === 'daily' ? 'primary' : 'outline-primary' ?>">
                <i class="bi bi-calendar-day"></i> Daily Report
            </a>
            <a href="?page=<?= $fleetPage ?>&tab=fleet&fleet_tab=reports&report=fuel" class="btn btn-<?= $reportType === 'fuel' ? 'primary' : 'outline-primary' ?>">
                <i class="bi bi-fuel-pump"></i> Fuel Consumption
            </a>
            <a href="?page=<?= $fleetPage ?>&tab=fleet&fleet_tab=reports&report=swaps" class="btn btn-<?= $reportType === 'swaps' ? 'primary' : 'outline-primary' ?>">
                <i class="bi bi-arrow-left-right"></i> Vehicle Swaps
            </a>
            <a href="?page=<?= $fleetPage ?>&tab=fleet&fleet_tab=reports&report=vehicle_daily" class="btn btn-<?= $reportType === 'vehicle_daily' ? 'primary' : 'outline-primary' ?>">
                <i class="bi bi-truck"></i> Vehicle Daily Log
            </a>
            <a href="?page=<?= $fleetPage ?>&tab=fleet&fleet_tab=reports&report=mileage" class="btn btn-<?= $reportType === 'mileage' ? 'primary' : 'outline-primary' ?>">
                <i class="bi bi-speedometer"></i> Mileage Trend
            </a>
        </div>
    </div>
</div>

<?php if ($reportType === 'daily'): ?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="mb-0"><i class="bi bi-calendar-day"></i> Daily Vehicle Report</h5>
        <form class="d-flex gap-2 align-items-center flex-wrap" method="GET">
            <input type="hidden" name="page" value="<?= $fleetPage ?>">
            <input type="hidden" name="tab" value="fleet">
            <input type="hidden" name="fleet_tab" value="reports">
            <input type="hidden" name="report" value="daily">
            <select name="vehicle_id" class="form-select form-select-sm" style="width:auto;">
                <option value="">All Vehicles</option>
                <?php foreach ($vehicles as $v): ?>
                <option value="<?= $v['id'] ?>" <?= $reportVehicle == $v['id'] ? 'selected' : '' ?>><?= htmlspecialchars($v['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="report_date" class="form-control form-control-sm" style="width:auto;" value="<?= htmlspecialchars($reportDate) ?>">
            <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-search"></i> View</button>
        </form>
    </div>
    <div class="card-body">
        <?php $dailyReport = $fleet->getDailyReport($reportDate, $reportVehicle); ?>
        <?php if (empty($dailyReport)): ?>
        <div class="alert alert-info mb-0"><i class="bi bi-info-circle"></i> No vehicles with IMEI found for this date.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Vehicle</th>
                        <th>Plate</th>
                        <th>Type</th>
                        <th>Assigned To</th>
                        <th class="text-end">Distance (km)</th>
                        <th class="text-end">Fuel Est. (L)</th>
                        <th class="text-center">Alarms</th>
                        <th class="text-center">Commands</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $totalKm = 0; $totalFuel = 0; $totalAlarms = 0;
                    foreach ($dailyReport as $dr):
                        $totalKm += $dr['daily_mileage'];
                        $totalFuel += $dr['fuel_consumed'];
                        $totalAlarms += $dr['alarm_count'];
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($dr['name']) ?></strong><br><small class="text-muted"><?= htmlspecialchars(($dr['make'] ?? '') . ' ' . ($dr['model'] ?? '')) ?></small></td>
                        <td><?= htmlspecialchars($dr['plate_number'] ?? '-') ?></td>
                        <td><span class="badge bg-secondary"><?= ucfirst($dr['vehicle_type']) ?></span></td>
                        <td><?= htmlspecialchars($dr['assigned_to'] ?? 'Unassigned') ?></td>
                        <td class="text-end"><strong><?= number_format($dr['daily_mileage'], 2) ?></strong></td>
                        <td class="text-end">
                            <?php if ($dr['fuel_consumed'] > 0): ?>
                            <?= number_format($dr['fuel_consumed'], 2) ?>
                            <?php elseif (($dr['fuel_rate'] ?? 0) == 0): ?>
                            <small class="text-muted">No rate set</small>
                            <?php else: ?>
                            -
                            <?php endif; ?>
                        </td>
                        <td class="text-center"><?= $dr['alarm_count'] > 0 ? '<span class="badge bg-danger">'.$dr['alarm_count'].'</span>' : '0' ?></td>
                        <td class="text-center"><?= $dr['command_count'] ?></td>
                        <td><span class="badge bg-<?= $dr['status'] === 'active' ? 'success' : ($dr['status'] === 'maintenance' ? 'warning' : 'secondary') ?>"><?= ucfirst($dr['status']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-dark">
                    <tr>
                        <th colspan="4">Totals (<?= count($dailyReport) ?> vehicles)</th>
                        <th class="text-end"><?= number_format($totalKm, 2) ?> km</th>
                        <th class="text-end"><?= number_format($totalFuel, 2) ?> L</th>
                        <th class="text-center"><?= $totalAlarms ?></th>
                        <th colspan="2"></th>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php elseif ($reportType === 'fuel'): ?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="mb-0"><i class="bi bi-fuel-pump"></i> Fuel Consumption Report</h5>
        <form class="d-flex gap-2 align-items-center flex-wrap" method="GET">
            <input type="hidden" name="page" value="<?= $fleetPage ?>">
            <input type="hidden" name="tab" value="fleet">
            <input type="hidden" name="fleet_tab" value="reports">
            <input type="hidden" name="report" value="fuel">
            <select name="vehicle_id" class="form-select form-select-sm" style="width:auto;">
                <option value="">All Vehicles</option>
                <?php foreach ($vehicles as $v): ?>
                <option value="<?= $v['id'] ?>" <?= $reportVehicle == $v['id'] ? 'selected' : '' ?>><?= htmlspecialchars($v['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="start_date" class="form-control form-control-sm" style="width:auto;" value="<?= htmlspecialchars($reportStart) ?>">
            <span class="text-muted">to</span>
            <input type="date" name="end_date" class="form-control form-control-sm" style="width:auto;" value="<?= htmlspecialchars($reportEnd) ?>">
            <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-search"></i> View</button>
        </form>
    </div>
    <div class="card-body">
        <?php $fuelReport = $fleet->getFuelReport($reportStart, $reportEnd, $reportVehicle); ?>
        <?php if (empty($fuelReport)): ?>
        <div class="alert alert-info mb-0"><i class="bi bi-info-circle"></i> No data available for this period.</div>
        <?php else: ?>
        <div class="alert alert-light border mb-3">
            <i class="bi bi-info-circle"></i> Fuel consumption is estimated based on each vehicle's configured fuel rate (L/100km) and total distance traveled. Set fuel rates in the Vehicles tab when adding or editing a vehicle.
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Vehicle</th>
                        <th>Plate</th>
                        <th>Type</th>
                        <th>Assigned To</th>
                        <th class="text-end">Fuel Rate (L/100km)</th>
                        <th class="text-end">Total Distance (km)</th>
                        <th class="text-end">Est. Fuel (L)</th>
                        <th class="text-center">Days Reported</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $grandKm = 0; $grandFuel = 0;
                    foreach ($fuelReport as $fr):
                        $grandKm += (float)$fr['total_mileage'];
                        $grandFuel += $fr['fuel_consumed'];
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($fr['name']) ?></strong><br><small class="text-muted"><?= htmlspecialchars(($fr['make'] ?? '') . ' ' . ($fr['model'] ?? '')) ?></small></td>
                        <td><?= htmlspecialchars($fr['plate_number'] ?? '-') ?></td>
                        <td><span class="badge bg-secondary"><?= ucfirst($fr['vehicle_type'] ?? '') ?></span></td>
                        <td><?= htmlspecialchars($fr['assigned_to'] ?? 'Unassigned') ?></td>
                        <td class="text-end">
                            <?php if (($fr['fuel_rate'] ?? 0) > 0): ?>
                            <?= number_format((float)$fr['fuel_rate'], 1) ?>
                            <?php else: ?>
                            <span class="text-danger">Not set</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end"><?= number_format((float)$fr['total_mileage'], 2) ?></td>
                        <td class="text-end">
                            <?php if ($fr['fuel_consumed'] > 0): ?>
                            <strong><?= number_format($fr['fuel_consumed'], 2) ?></strong>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center"><?= $fr['days_reported'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-dark">
                    <tr>
                        <th colspan="5">Totals</th>
                        <th class="text-end"><?= number_format($grandKm, 2) ?> km</th>
                        <th class="text-end"><?= number_format($grandFuel, 2) ?> L</th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php elseif ($reportType === 'swaps'): ?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="mb-0"><i class="bi bi-arrow-left-right"></i> Vehicle Assignment / Swap History</h5>
        <form class="d-flex gap-2 align-items-center flex-wrap" method="GET">
            <input type="hidden" name="page" value="<?= $fleetPage ?>">
            <input type="hidden" name="tab" value="fleet">
            <input type="hidden" name="fleet_tab" value="reports">
            <input type="hidden" name="report" value="swaps">
            <select name="vehicle_id" class="form-select form-select-sm" style="width:auto;">
                <option value="">All Vehicles</option>
                <?php foreach ($vehicles as $v): ?>
                <option value="<?= $v['id'] ?>" <?= $reportVehicle == $v['id'] ? 'selected' : '' ?>><?= htmlspecialchars($v['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="start_date" class="form-control form-control-sm" style="width:auto;" value="<?= htmlspecialchars($reportStart) ?>">
            <span class="text-muted">to</span>
            <input type="date" name="end_date" class="form-control form-control-sm" style="width:auto;" value="<?= htmlspecialchars($reportEnd) ?>">
            <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-search"></i> View</button>
        </form>
    </div>
    <div class="card-body">
        <?php $swapHistory = $fleet->getSwapHistory($reportStart, $reportEnd, $reportVehicle); ?>
        <?php if (empty($swapHistory)): ?>
        <div class="alert alert-info mb-0"><i class="bi bi-info-circle"></i> No vehicle swaps/assignments found in this period.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Vehicle</th>
                        <th>Plate</th>
                        <th>Employee</th>
                        <th>Assigned Date</th>
                        <th>Returned Date</th>
                        <th>Duration</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($swapHistory as $swap): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($swap['vehicle_name']) ?></strong></td>
                        <td><?= htmlspecialchars($swap['plate_number'] ?? '-') ?></td>
                        <td><i class="bi bi-person"></i> <?= htmlspecialchars($swap['employee_name']) ?></td>
                        <td><?= date('M j, Y H:i', strtotime($swap['assigned_at'])) ?></td>
                        <td>
                            <?php if ($swap['returned_at']): ?>
                            <?= date('M j, Y H:i', strtotime($swap['returned_at'])) ?>
                            <?php else: ?>
                            <span class="badge bg-success">Current</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $start = new \DateTime($swap['assigned_at']);
                            $end = $swap['returned_at'] ? new \DateTime($swap['returned_at']) : new \DateTime();
                            $diff = $start->diff($end);
                            if ($diff->days > 0) echo $diff->days . 'd ';
                            echo $diff->h . 'h ' . $diff->i . 'm';
                            ?>
                        </td>
                        <td><small><?= htmlspecialchars($swap['notes'] ?? '') ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php elseif ($reportType === 'vehicle_daily'): ?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="mb-0"><i class="bi bi-truck"></i> Vehicle Daily Log</h5>
        <form class="d-flex gap-2 align-items-center flex-wrap" method="GET">
            <input type="hidden" name="page" value="<?= $fleetPage ?>">
            <input type="hidden" name="tab" value="fleet">
            <input type="hidden" name="fleet_tab" value="reports">
            <input type="hidden" name="report" value="vehicle_daily">
            <select name="vehicle_id" class="form-select form-select-sm" style="width:auto;" required>
                <option value="">-- Select Vehicle --</option>
                <?php foreach ($vehicles as $v): ?>
                <option value="<?= $v['id'] ?>" <?= $reportVehicle == $v['id'] ? 'selected' : '' ?>><?= htmlspecialchars($v['name']) ?> (<?= htmlspecialchars($v['plate_number'] ?? 'No plate') ?>)</option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="start_date" class="form-control form-control-sm" style="width:auto;" value="<?= htmlspecialchars($reportStart) ?>">
            <span class="text-muted">to</span>
            <input type="date" name="end_date" class="form-control form-control-sm" style="width:auto;" value="<?= htmlspecialchars($reportEnd) ?>">
            <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-search"></i> View</button>
        </form>
    </div>
    <div class="card-body">
        <?php if (!$reportVehicle): ?>
        <div class="alert alert-info mb-0"><i class="bi bi-info-circle"></i> Select a vehicle and date range to view its daily activity log.</div>
        <?php else: ?>
        <?php $dailyLog = $fleet->getVehicleDailyLog($reportVehicle, $reportStart, $reportEnd); ?>
        <?php if (empty($dailyLog)): ?>
        <div class="alert alert-warning mb-0"><i class="bi bi-exclamation-triangle"></i> No data found. Make sure the vehicle has an IMEI configured.</div>
        <?php else: ?>
        <?php $veh = $dailyLog['vehicle']; $logDays = $dailyLog['days']; ?>

        <div class="row mb-3">
            <div class="col-md-6">
                <div class="d-flex align-items-center gap-3">
                    <div>
                        <h6 class="mb-0"><?= htmlspecialchars($veh['name']) ?></h6>
                        <small class="text-muted"><?= htmlspecialchars(($veh['make'] ?? '') . ' ' . ($veh['model'] ?? '')) ?> &bull; <?= htmlspecialchars($veh['plate_number'] ?? 'No plate') ?></small>
                    </div>
                    <span class="badge bg-<?= $veh['status'] === 'active' ? 'success' : ($veh['status'] === 'maintenance' ? 'warning' : 'secondary') ?>"><?= ucfirst($veh['status']) ?></span>
                </div>
                <small class="text-muted">Assigned to: <?= htmlspecialchars($veh['assigned_to'] ?? 'Unassigned') ?></small>
            </div>
            <div class="col-md-6 text-md-end">
                <?php
                $totalKm = array_sum(array_column($logDays, 'mileage_km'));
                $totalFuel = array_sum(array_column($logDays, 'fuel_consumed'));
                $totalAlarms = array_sum(array_column($logDays, 'alarm_count'));
                $activeDays = count(array_filter($logDays, fn($d) => $d['mileage_km'] > 0));
                $maxSpeedAll = max(array_column($logDays, 'max_speed'));
                ?>
                <div class="d-flex gap-3 justify-content-md-end flex-wrap">
                    <div class="text-center"><strong><?= number_format($totalKm, 1) ?></strong><br><small class="text-muted">Total km</small></div>
                    <div class="text-center"><strong><?= number_format($totalFuel, 1) ?></strong><br><small class="text-muted">Fuel (L)</small></div>
                    <div class="text-center"><strong><?= $activeDays ?>/<?= count($logDays) ?></strong><br><small class="text-muted">Active Days</small></div>
                    <div class="text-center"><strong><?= number_format($maxSpeedAll, 0) ?></strong><br><small class="text-muted">Max km/h</small></div>
                    <div class="text-center"><strong><?= $totalAlarms ?></strong><br><small class="text-muted">Alarms</small></div>
                </div>
            </div>
        </div>

        <?php if (count($logDays) > 1): ?>
        <canvas id="vehicleDailyChart" height="80" class="mb-3"></canvas>
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
        <script>
        (function() {
            const days = <?= json_encode($logDays) ?>;
            new Chart(document.getElementById('vehicleDailyChart'), {
                type: 'bar',
                data: {
                    labels: days.map(d => d.date + ' (' + d.day_name + ')'),
                    datasets: [{
                        label: 'Distance (km)',
                        data: days.map(d => d.mileage_km),
                        backgroundColor: days.map(d => d.mileage_km > 0 ? 'rgba(54, 162, 235, 0.6)' : 'rgba(200, 200, 200, 0.3)'),
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: { beginAtZero: true, title: { display: true, text: 'Distance (km)' } }
                    },
                    plugins: {
                        legend: { display: false },
                        title: { display: true, text: 'Daily Distance - <?= htmlspecialchars($veh['name']) ?>' }
                    }
                }
            });
        })();
        </script>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table table-sm table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Date</th>
                        <th>Day</th>
                        <th class="text-end">Distance (km)</th>
                        <th class="text-end">Fuel Est. (L)</th>
                        <th class="text-end">Max Speed</th>
                        <th class="text-center">First Move</th>
                        <th class="text-center">Last Move</th>
                        <th class="text-center">Alarms</th>
                        <th class="text-center">Playback</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logDays as $day): ?>
                    <tr class="<?= $day['mileage_km'] == 0 ? 'table-light text-muted' : '' ?>">
                        <td><?= date('M j, Y', strtotime($day['date'])) ?></td>
                        <td><?= $day['day_name'] ?></td>
                        <td class="text-end"><strong><?= number_format($day['mileage_km'], 2) ?></strong></td>
                        <td class="text-end">
                            <?php if ($day['fuel_consumed'] > 0): ?>
                            <?= number_format($day['fuel_consumed'], 2) ?>
                            <?php elseif (($veh['fuel_rate'] ?? 0) == 0 && $day['mileage_km'] > 0): ?>
                            <small class="text-muted">No rate</small>
                            <?php else: ?>
                            -
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <?php if ($day['max_speed'] > 0): ?>
                            <?= number_format($day['max_speed'], 0) ?> km/h
                            <?php if ($day['max_speed'] > 100): ?>
                            <i class="bi bi-exclamation-triangle-fill text-warning" title="High speed detected"></i>
                            <?php endif; ?>
                            <?php else: ?>
                            -
                            <?php endif; ?>
                        </td>
                        <td class="text-center"><?= $day['first_move'] ?? '-' ?></td>
                        <td class="text-center"><?= $day['last_move'] ?? '-' ?></td>
                        <td class="text-center"><?= $day['alarm_count'] > 0 ? '<span class="badge bg-danger">'.$day['alarm_count'].'</span>' : '0' ?></td>
                        <td class="text-center">
                            <?php if ($day['mileage_km'] > 0): ?>
                            <a href="?page=<?= $fleetPage ?>&tab=fleet&fleet_tab=playback&vehicle_id=<?= $reportVehicle ?>&playback_date=<?= $day['date'] ?>" class="btn btn-sm btn-outline-primary" title="View route playback">
                                <i class="bi bi-play-circle"></i>
                            </a>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-dark">
                    <tr>
                        <th colspan="2">Totals (<?= count($logDays) ?> days)</th>
                        <th class="text-end"><?= number_format($totalKm, 2) ?> km</th>
                        <th class="text-end"><?= $totalFuel > 0 ? number_format($totalFuel, 2) . ' L' : '-' ?></th>
                        <th class="text-end"><?= number_format($maxSpeedAll, 0) ?> km/h</th>
                        <th colspan="2"></th>
                        <th class="text-center"><?= $totalAlarms ?></th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>
        </div>

        <?php if ($activeDays > 0 && $totalKm > 0): ?>
        <div class="row mt-3">
            <div class="col-md-4">
                <div class="card bg-light">
                    <div class="card-body p-2 text-center">
                        <small class="text-muted">Avg Daily Distance</small>
                        <h5 class="mb-0"><?= number_format($totalKm / $activeDays, 1) ?> km</h5>
                        <small class="text-muted">(active days only)</small>
                    </div>
                </div>
            </div>
            <?php if ($totalFuel > 0): ?>
            <div class="col-md-4">
                <div class="card bg-light">
                    <div class="card-body p-2 text-center">
                        <small class="text-muted">Avg Daily Fuel</small>
                        <h5 class="mb-0"><?= number_format($totalFuel / $activeDays, 1) ?> L</h5>
                        <small class="text-muted">(active days only)</small>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <div class="col-md-4">
                <div class="card bg-light">
                    <div class="card-body p-2 text-center">
                        <small class="text-muted">Utilization Rate</small>
                        <h5 class="mb-0"><?= round($activeDays / count($logDays) * 100) ?>%</h5>
                        <small class="text-muted"><?= $activeDays ?> of <?= count($logDays) ?> days</small>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php elseif ($reportType === 'mileage'): ?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="mb-0"><i class="bi bi-speedometer"></i> Mileage Trend</h5>
        <form class="d-flex gap-2 align-items-center flex-wrap" method="GET">
            <input type="hidden" name="page" value="<?= $fleetPage ?>">
            <input type="hidden" name="tab" value="fleet">
            <input type="hidden" name="fleet_tab" value="reports">
            <input type="hidden" name="report" value="mileage">
            <select name="vehicle_id" class="form-select form-select-sm" style="width:auto;" required>
                <option value="">-- Select Vehicle --</option>
                <?php foreach ($vehicles as $v): ?>
                <option value="<?= $v['id'] ?>" <?= $reportVehicle == $v['id'] ? 'selected' : '' ?>><?= htmlspecialchars($v['name']) ?> (<?= htmlspecialchars($v['plate_number'] ?? 'No plate') ?>)</option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-search"></i> View</button>
        </form>
    </div>
    <div class="card-body">
        <?php if (!$reportVehicle): ?>
        <div class="alert alert-info mb-0"><i class="bi bi-info-circle"></i> Select a vehicle to view its mileage trend over the last 30 days.</div>
        <?php else: ?>
        <?php $mileageTrend = $fleet->getMileageTrend($reportVehicle, 30); ?>
        <?php if (empty($mileageTrend)): ?>
        <div class="alert alert-warning mb-0"><i class="bi bi-exclamation-triangle"></i> No mileage data recorded for this vehicle yet. Mileage data is stored when daily reports are generated.</div>
        <?php else: ?>
        <canvas id="mileageChart" height="100"></canvas>
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
        <script>
        (function() {
            const data = <?= json_encode($mileageTrend) ?>;
            const labels = data.map(d => d.report_date);
            const values = data.map(d => parseFloat(d.mileage));
            new Chart(document.getElementById('mileageChart'), {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Daily Distance (km)',
                        data: values,
                        backgroundColor: 'rgba(54, 162, 235, 0.6)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: { beginAtZero: true, title: { display: true, text: 'Distance (km)' } },
                        x: { title: { display: true, text: 'Date' } }
                    },
                    plugins: {
                        legend: { display: false },
                        title: { display: true, text: 'Daily Mileage - Last 30 Days' }
                    }
                }
            });
        })();
        </script>

        <div class="table-responsive mt-3">
            <table class="table table-sm">
                <thead class="table-light">
                    <tr><th>Date</th><th class="text-end">Distance (km)</th></tr>
                </thead>
                <tbody>
                    <?php $sum = 0; foreach (array_reverse($mileageTrend) as $mt): $sum += (float)$mt['mileage']; ?>
                    <tr>
                        <td><?= date('M j, Y (D)', strtotime($mt['report_date'])) ?></td>
                        <td class="text-end"><?= number_format((float)$mt['mileage'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-dark">
                    <tr><th>Total (<?= count($mileageTrend) ?> days)</th><th class="text-end"><?= number_format($sum, 2) ?> km</th></tr>
                </tfoot>
            </table>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>
