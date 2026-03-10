<?php
$currentUserId = \App\Auth::user()['id'] ?? null;
$canViewAllTickets = \App\Auth::can('tickets.view_all') || \App\Auth::isAdmin();

$userFilterId = $canViewAllTickets ? null : $currentUserId;
$dashboardStats = $ticket->getStats($userFilterId);

$trendDb = \Database::getConnection();

$ticketTrendDaily = [];
try {
    $stmt = $trendDb->query("SELECT DATE(created_at) as day, COUNT(*) as cnt FROM tickets WHERE created_at >= CURRENT_DATE - INTERVAL '30 days' GROUP BY DATE(created_at) ORDER BY day ASC");
    $ticketTrendDaily = $stmt->fetchAll(\PDO::FETCH_ASSOC);
} catch (\Throwable $e) { error_log("Dashboard ticket trend error: " . $e->getMessage()); }

$ticketByStatus = [];
try {
    $stmt = $trendDb->query("SELECT status, COUNT(*) as cnt FROM tickets GROUP BY status");
    $ticketByStatus = $stmt->fetchAll(\PDO::FETCH_ASSOC);
} catch (\Throwable $e) { error_log("Dashboard ticket status error: " . $e->getMessage()); }

$kpiDb = \Database::getConnection();

$kpiNetwork = ['total_onus' => 0, 'online_onus' => 0, 'los_onus' => 0, 'olt_count' => 0];
try {
    $stmt = $kpiDb->query("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'online' THEN 1 ELSE 0 END) as online, SUM(CASE WHEN (status = 'los') OR (status = 'offline' AND last_down_cause IS NOT NULL AND last_down_cause != '' AND last_down_cause != '-' AND (LOWER(last_down_cause) LIKE '%los%' OR LOWER(last_down_cause) LIKE '%lob%' OR LOWER(last_down_cause) LIKE '%lofi%')) THEN 1 ELSE 0 END) as los FROM huawei_onus WHERE is_authorized = TRUE");
    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
    if ($row) { $kpiNetwork['total_onus'] = (int)($row['total'] ?? 0); $kpiNetwork['online_onus'] = (int)($row['online'] ?? 0); $kpiNetwork['los_onus'] = (int)($row['los'] ?? 0); }
    $stmt2 = $kpiDb->query("SELECT COUNT(*) as cnt FROM huawei_olts");
    $row2 = $stmt2->fetch(\PDO::FETCH_ASSOC);
    if ($row2) { $kpiNetwork['olt_count'] = (int)($row2['cnt'] ?? 0); }
} catch (\Throwable $e) { error_log("Dashboard KPI network error: " . $e->getMessage()); }

$kpiFinancial = ['today_revenue' => 0, 'outstanding_invoices' => 0, 'monthly_revenue' => 0];
try {
    $stmt = $kpiDb->prepare("SELECT COALESCE(SUM(trans_amount), 0) as total FROM mpesa_c2b_transactions WHERE DATE(trans_time) = CURRENT_DATE");
    $stmt->execute();
    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
    if ($row) { $kpiFinancial['today_revenue'] = (float)($row['total'] ?? 0); }
    try {
        $stmt = $kpiDb->query("SELECT COUNT(*) as cnt FROM accounting_invoices WHERE status != 'paid'");
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($row) { $kpiFinancial['outstanding_invoices'] = (int)($row['cnt'] ?? 0); }
    } catch (\Throwable $e2) { }
    $stmt = $kpiDb->prepare("SELECT COALESCE(SUM(trans_amount), 0) as total FROM mpesa_c2b_transactions WHERE DATE(trans_time) >= date_trunc('month', CURRENT_DATE)");
    $stmt->execute();
    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
    if ($row) { $kpiFinancial['monthly_revenue'] = (float)($row['total'] ?? 0); }
} catch (\Throwable $e) { error_log("Dashboard KPI financial error: " . $e->getMessage()); }

$kpiCustomers = ['active' => 0, 'suspended' => 0];
try {
    $stmt = $kpiDb->query("SELECT SUM(CASE WHEN connection_status = 'active' THEN 1 ELSE 0 END) as active, SUM(CASE WHEN connection_status = 'suspended' THEN 1 ELSE 0 END) as suspended FROM customers");
    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
    if ($row) { $kpiCustomers['active'] = (int)($row['active'] ?? 0); $kpiCustomers['suspended'] = (int)($row['suspended'] ?? 0); }
} catch (\Throwable $e) { error_log("Dashboard KPI customers error: " . $e->getMessage()); }

$kpiSignalHealth = ['warning_count' => 0];
try {
    $stmt = $kpiDb->query("SELECT COUNT(*) as cnt FROM huawei_onus WHERE is_authorized = TRUE AND rx_power IS NOT NULL AND CAST(rx_power AS DECIMAL) BETWEEN -28 AND -25");
    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
    if ($row) { $kpiSignalHealth['warning_count'] = (int)($row['cnt'] ?? 0); }
} catch (\Throwable $e) { error_log("Dashboard KPI signal health error: " . $e->getMessage()); }
?>
<?php
$activeMaintenanceWindows = [];
try {
    $mwStmt = $db->query("SELECT * FROM maintenance_windows WHERE status = 'active' OR (status = 'scheduled' AND start_time <= NOW() AND end_time >= NOW()) ORDER BY start_time ASC");
    $activeMaintenanceWindows = $mwStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>
<?php foreach ($activeMaintenanceWindows as $mw): ?>
<div class="alert alert-warning alert-dismissible fade show d-flex align-items-center mb-3" role="alert">
    <i class="bi bi-tools me-2 fs-4"></i>
    <div class="flex-grow-1">
        <strong>Scheduled Maintenance:</strong> <?= htmlspecialchars($mw['title']) ?>
        <br><small><?= htmlspecialchars($mw['description'] ?? '') ?> &mdash; <?= date('M j, g:i A', strtotime($mw['start_time'])) ?> to <?= date('M j, g:i A', strtotime($mw['end_time'])) ?></small>
    </div>
    <a href="?page=maintenance" class="btn btn-sm btn-outline-warning ms-2">Details</a>
    <button type="button" class="btn-close ms-2" data-bs-dismiss="alert"></button>
</div>
<?php endforeach; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-speedometer2"></i> Dashboard</h2>
    <div class="d-flex align-items-center gap-3">
        <?php if (!$canViewAllTickets): ?>
            <span class="badge bg-info"><i class="bi bi-person"></i> My Data</span>
        <?php endif; ?>
        <span class="text-muted"><?= date('F j, Y') ?></span>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-12">
        <h6 class="text-muted text-uppercase fw-bold mb-0"><i class="bi bi-hdd-network me-1"></i> Network</h6>
    </div>
    <div class="col-md-3">
        <div class="card stat-card border-start border-4 border-primary">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon bg-primary bg-opacity-10 text-primary me-3">
                    <i class="bi bi-router"></i>
                </div>
                <div>
                    <h3 class="mb-0 text-dark"><?= number_format($kpiNetwork['total_onus']) ?></h3>
                    <small class="text-muted">Total ONUs</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card border-start border-4 border-success">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon bg-success bg-opacity-10 text-success me-3">
                    <i class="bi bi-wifi"></i>
                </div>
                <div>
                    <h3 class="mb-0 text-dark"><?= number_format($kpiNetwork['online_onus']) ?></h3>
                    <small class="text-muted">ONUs Online</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card border-start border-4 border-danger">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon bg-danger bg-opacity-10 text-danger me-3">
                    <i class="bi bi-wifi-off"></i>
                </div>
                <div>
                    <h3 class="mb-0 text-dark"><?= number_format($kpiNetwork['los_onus']) ?></h3>
                    <small class="text-muted">ONUs in LOS</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card border-start border-4 border-info">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon bg-info bg-opacity-10 text-info me-3">
                    <i class="bi bi-hdd-rack"></i>
                </div>
                <div>
                    <h3 class="mb-0 text-dark"><?= number_format($kpiNetwork['olt_count']) ?></h3>
                    <small class="text-muted">OLTs</small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($kpiSignalHealth['warning_count'] > 0): ?>
<div class="row g-3 mb-4">
    <div class="col-12">
        <a href="?page=huawei-olt&view=signal_alerts" class="text-decoration-none">
            <div class="card border-warning shadow-sm">
                <div class="card-body d-flex align-items-center">
                    <div class="stat-icon bg-warning bg-opacity-10 text-warning me-3">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h5 class="mb-1 text-dark">
                            <i class="bi bi-broadcast me-1"></i> Signal Health Warning
                        </h5>
                        <p class="mb-0 text-muted">
                            <strong class="text-warning"><?= number_format($kpiSignalHealth['warning_count']) ?></strong> ONU(s) have degrading signal levels (between -25 and -28 dBm). These are approaching the LOS threshold and may need attention.
                        </p>
                    </div>
                    <div class="ms-3">
                        <span class="badge bg-warning text-dark fs-6"><?= $kpiSignalHealth['warning_count'] ?></span>
                    </div>
                    <i class="bi bi-chevron-right text-muted ms-2"></i>
                </div>
            </div>
        </a>
    </div>
</div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-12">
        <h6 class="text-muted text-uppercase fw-bold mb-0"><i class="bi bi-cash-stack me-1"></i> Financial</h6>
    </div>
    <div class="col-md-4">
        <div class="card stat-card border-start border-4 border-success">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon bg-success bg-opacity-10 text-success me-3">
                    <i class="bi bi-cash"></i>
                </div>
                <div>
                    <h3 class="mb-0 text-dark">KES <?= number_format($kpiFinancial['today_revenue'], 2) ?></h3>
                    <small class="text-muted">Today's Revenue</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <a href="?page=finance" class="text-decoration-none">
            <div class="card stat-card stat-card-clickable border-start border-4 border-warning">
                <div class="card-body d-flex align-items-center">
                    <div class="stat-icon bg-warning bg-opacity-10 text-warning me-3">
                        <i class="bi bi-receipt"></i>
                    </div>
                    <div>
                        <h3 class="mb-0 text-dark"><?= number_format($kpiFinancial['outstanding_invoices']) ?></h3>
                        <small class="text-muted">Outstanding Invoices</small>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <div class="card stat-card border-start border-4 border-primary">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon bg-primary bg-opacity-10 text-primary me-3">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
                <div>
                    <h3 class="mb-0 text-dark">KES <?= number_format($kpiFinancial['monthly_revenue'], 2) ?></h3>
                    <small class="text-muted">Monthly Revenue</small>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-12">
        <h6 class="text-muted text-uppercase fw-bold mb-0"><i class="bi bi-people me-1"></i> Customers</h6>
    </div>
    <div class="col-md-6">
        <a href="?page=customers&status=active" class="text-decoration-none">
            <div class="card stat-card stat-card-clickable border-start border-4 border-success">
                <div class="card-body d-flex align-items-center">
                    <div class="stat-icon bg-success bg-opacity-10 text-success me-3">
                        <i class="bi bi-person-check"></i>
                    </div>
                    <div>
                        <h3 class="mb-0 text-dark"><?= number_format($kpiCustomers['active']) ?></h3>
                        <small class="text-muted">Active Customers</small>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-6">
        <a href="?page=customers&status=suspended" class="text-decoration-none">
            <div class="card stat-card stat-card-clickable border-start border-4 border-danger">
                <div class="card-body d-flex align-items-center">
                    <div class="stat-icon bg-danger bg-opacity-10 text-danger me-3">
                        <i class="bi bi-person-x"></i>
                    </div>
                    <div>
                        <h3 class="mb-0 text-dark"><?= number_format($kpiCustomers['suspended']) ?></h3>
                        <small class="text-muted">Suspended Customers</small>
                    </div>
                </div>
            </div>
        </a>
    </div>
</div>

<?php $totalLowStock = $kpiInventory['low_stock_count'] + $kpiInventory['out_of_stock_count']; ?>
<?php if ($totalLowStock > 0): ?>
<div class="row g-3 mb-4">
    <div class="col-12">
        <h6 class="text-muted text-uppercase fw-bold mb-0"><i class="bi bi-box-seam me-1"></i> Inventory Alerts</h6>
    </div>
    <div class="col-md-4">
        <a href="?page=inventory" class="text-decoration-none">
            <div class="card stat-card stat-card-clickable border-start border-4 border-warning">
                <div class="card-body d-flex align-items-center">
                    <div class="stat-icon bg-warning bg-opacity-10 text-warning me-3">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                    <div>
                        <h3 class="mb-0 text-dark"><?= number_format($totalLowStock) ?></h3>
                        <small class="text-muted">Low Stock Items</small>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="?page=inventory" class="text-decoration-none">
            <div class="card stat-card stat-card-clickable border-start border-4 border-warning">
                <div class="card-body d-flex align-items-center">
                    <div class="stat-icon bg-warning bg-opacity-10 text-warning me-3">
                        <i class="bi bi-arrow-down-circle"></i>
                    </div>
                    <div>
                        <h3 class="mb-0 text-dark"><?= number_format($kpiInventory['low_stock_count']) ?></h3>
                        <small class="text-muted">Below Reorder Point</small>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="?page=inventory" class="text-decoration-none">
            <div class="card stat-card stat-card-clickable border-start border-4 border-danger">
                <div class="card-body d-flex align-items-center">
                    <div class="stat-icon bg-danger bg-opacity-10 text-danger me-3">
                        <i class="bi bi-x-circle"></i>
                    </div>
                    <div>
                        <h3 class="mb-0 text-dark"><?= number_format($kpiInventory['out_of_stock_count']) ?></h3>
                        <small class="text-muted">Out of Stock</small>
                    </div>
                </div>
            </div>
        </a>
    </div>
</div>
<?php endif; ?>

<div class="row g-4 mb-4">
    <div class="col-md-3">
        <a href="?page=tickets" class="text-decoration-none">
            <div class="card stat-card stat-card-clickable">
                <div class="card-body d-flex align-items-center">
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary me-3">
                        <i class="bi bi-ticket"></i>
                    </div>
                    <div>
                        <h3 class="mb-0 text-dark"><?= $dashboardStats['total'] ?? 0 ?></h3>
                        <small class="text-muted">Total Tickets</small>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="?page=tickets&status=open" class="text-decoration-none">
            <div class="card stat-card stat-card-clickable">
                <div class="card-body d-flex align-items-center">
                    <div class="stat-icon bg-warning bg-opacity-10 text-warning me-3">
                        <i class="bi bi-exclamation-circle"></i>
                    </div>
                    <div>
                        <h3 class="mb-0 text-dark"><?= $dashboardStats['open'] ?? 0 ?></h3>
                        <small class="text-muted">Open Tickets</small>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="?page=tickets&status=in_progress" class="text-decoration-none">
            <div class="card stat-card stat-card-clickable">
                <div class="card-body d-flex align-items-center">
                    <div class="stat-icon bg-info bg-opacity-10 text-info me-3">
                        <i class="bi bi-arrow-repeat"></i>
                    </div>
                    <div>
                        <h3 class="mb-0 text-dark"><?= $dashboardStats['in_progress'] ?? 0 ?></h3>
                        <small class="text-muted">In Progress</small>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="?page=tickets&status=resolved" class="text-decoration-none">
            <div class="card stat-card stat-card-clickable">
                <div class="card-body d-flex align-items-center">
                    <div class="stat-icon bg-success bg-opacity-10 text-success me-3">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <div>
                        <h3 class="mb-0 text-dark"><?= $dashboardStats['resolved'] ?? 0 ?></h3>
                        <small class="text-muted">Resolved</small>
                    </div>
                </div>
            </div>
        </a>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-3">
        <a href="?page=tickets&priority=critical" class="text-decoration-none">
            <div class="card stat-card stat-card-clickable border-danger">
                <div class="card-body text-center">
                    <h4 class="text-danger mb-0"><?= $dashboardStats['critical'] ?? 0 ?></h4>
                    <small class="text-muted">Critical Priority</small>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="?page=tickets&priority=high" class="text-decoration-none">
            <div class="card stat-card stat-card-clickable border-warning">
                <div class="card-body text-center">
                    <h4 class="text-warning mb-0"><?= $dashboardStats['high'] ?? 0 ?></h4>
                    <small class="text-muted">High Priority</small>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-6">
        <div class="card stat-card">
            <div class="card-body">
                <h6 class="mb-3">Quick Actions</h6>
                <a href="?page=tickets&action=create" class="btn btn-primary btn-sm me-2">
                    <i class="bi bi-plus-circle"></i> New Ticket
                </a>
                <a href="?page=customers&action=create" class="btn btn-outline-primary btn-sm me-2">
                    <i class="bi bi-person-plus"></i> Add Customer
                </a>
                <a href="?page=orders&action=create" class="btn btn-success btn-sm">
                    <i class="bi bi-cart-plus"></i> New Order
                </a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>

<div class="row g-4 mb-4">
    <div class="col-12">
        <h6 class="text-muted text-uppercase fw-bold mb-0"><i class="bi bi-graph-up me-1"></i> Trends</h6>
    </div>
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-white">
                <h6 class="mb-0">Tickets Created (Last 30 Days)</h6>
            </div>
            <div class="card-body">
                <canvas id="ticketTrendChart" height="120"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-white">
                <h6 class="mb-0">Tickets by Status</h6>
            </div>
            <div class="card-body d-flex justify-content-center align-items-center">
                <canvas id="ticketStatusChart" height="200"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const trendData = <?= json_encode($ticketTrendDaily) ?>;
    const statusData = <?= json_encode($ticketByStatus) ?>;

    const days = [];
    const counts = [];
    const today = new Date();
    for (let i = 29; i >= 0; i--) {
        const d = new Date(today);
        d.setDate(d.getDate() - i);
        const key = d.toISOString().slice(0, 10);
        days.push(d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
        const found = trendData.find(r => r.day === key);
        counts.push(found ? parseInt(found.cnt) : 0);
    }

    new Chart(document.getElementById('ticketTrendChart'), {
        type: 'line',
        data: {
            labels: days,
            datasets: [{
                label: 'Tickets',
                data: counts,
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13,110,253,0.1)',
                fill: true,
                tension: 0.3,
                pointRadius: 2,
                pointHoverRadius: 5
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1, precision: 0 } },
                x: { ticks: { maxTicksLimit: 10 } }
            }
        }
    });

    const statusColors = {
        open: '#ffc107',
        in_progress: '#0dcaf0',
        resolved: '#198754',
        closed: '#6c757d',
        pending: '#fd7e14',
        escalated: '#dc3545'
    };
    const statusLabels = statusData.map(r => r.status.replace('_', ' ').replace(/\b\w/g, c => c.toUpperCase()));
    const statusCounts = statusData.map(r => parseInt(r.cnt));
    const statusBg = statusData.map(r => statusColors[r.status] || '#adb5bd');

    new Chart(document.getElementById('ticketStatusChart'), {
        type: 'doughnut',
        data: {
            labels: statusLabels,
            datasets: [{
                data: statusCounts,
                backgroundColor: statusBg,
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, padding: 10 } }
            }
        }
    });
})();
</script>

<?php
$sla = new \App\SLA();
$slaStats = $sla->getSLAStatistics('30days', $userFilterId);
$breachedTickets = $sla->getBreachedTickets($userFilterId);
$atRiskTickets = $sla->getAtRiskTickets($userFilterId);
?>
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <a href="?page=reports&view=sla" class="text-decoration-none">
            <div class="card stat-card stat-card-clickable">
                <div class="card-body d-flex align-items-center">
                    <div class="stat-icon bg-success bg-opacity-10 text-success me-3">
                        <i class="bi bi-speedometer2"></i>
                    </div>
                    <div>
                        <h3 class="mb-0 text-dark"><?= $slaStats['response_compliance'] ?>%</h3>
                        <small class="text-muted">Response SLA</small>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="?page=reports&view=sla" class="text-decoration-none">
            <div class="card stat-card stat-card-clickable">
                <div class="card-body d-flex align-items-center">
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary me-3">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <div>
                        <h3 class="mb-0 text-dark"><?= $slaStats['resolution_compliance'] ?>%</h3>
                        <small class="text-muted">Resolution SLA</small>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="?page=tickets&sla=breached" class="text-decoration-none">
            <div class="card stat-card stat-card-clickable border-danger">
                <div class="card-body d-flex align-items-center">
                    <div class="stat-icon bg-danger bg-opacity-10 text-danger me-3">
                        <i class="bi bi-x-circle"></i>
                    </div>
                    <div>
                        <h3 class="mb-0 text-dark"><?= count($breachedTickets) ?></h3>
                        <small class="text-muted">SLA Breached</small>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="?page=tickets&sla=at_risk" class="text-decoration-none">
            <div class="card stat-card stat-card-clickable border-warning">
                <div class="card-body d-flex align-items-center">
                    <div class="stat-icon bg-warning bg-opacity-10 text-warning me-3">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                    <div>
                        <h3 class="mb-0 text-dark"><?= count($atRiskTickets) ?></h3>
                        <small class="text-muted">At Risk</small>
                    </div>
                </div>
            </div>
        </a>
    </div>
</div>

<div class="row g-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Recent Tickets</h5>
                <a href="?page=tickets" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Ticket #</th>
                                <th>Customer</th>
                                <th>Subject</th>
                                <th>Priority</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $ticketFilters = $canViewAllTickets ? [] : ['user_id' => $currentUserId];
                            $recentTickets = $ticket->getAll($ticketFilters, 5);
                            foreach ($recentTickets as $t):
                            ?>
                            <tr>
                                <td>
                                    <a href="?page=tickets&action=view&id=<?= $t['id'] ?>">
                                        <?= htmlspecialchars($t['ticket_number']) ?>
                                    </a>
                                </td>
                                <td><?= htmlspecialchars($t['customer_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars(substr($t['subject'], 0, 40)) ?>...</td>
                                <td>
                                    <span class="badge badge-priority-<?= $t['priority'] ?>">
                                        <?= ucfirst($t['priority']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-status-<?= $t['status'] ?>">
                                        <?= ucfirst(str_replace('_', ' ', $t['status'])) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recentTickets)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">No tickets yet</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-lightning-charge me-2"></i>Quick Access</h5>
            </div>
            <div class="card-body p-2">
                <div class="list-group list-group-flush">
                    <a href="?page=tickets&priority=critical" class="list-group-item list-group-item-action d-flex align-items-center py-2">
                        <div class="bg-danger bg-opacity-10 rounded p-2 me-3">
                            <i class="bi bi-exclamation-octagon text-danger"></i>
                        </div>
                        <div class="flex-grow-1">
                            <strong>Critical Tickets</strong>
                            <span class="badge bg-danger float-end"><?= $dashboardStats['critical'] ?? 0 ?></span>
                        </div>
                    </a>
                    <a href="?page=tickets&priority=high" class="list-group-item list-group-item-action d-flex align-items-center py-2">
                        <div class="bg-warning bg-opacity-10 rounded p-2 me-3">
                            <i class="bi bi-exclamation-triangle text-warning"></i>
                        </div>
                        <div class="flex-grow-1">
                            <strong>High Priority</strong>
                            <span class="badge bg-warning float-end"><?= $dashboardStats['high'] ?? 0 ?></span>
                        </div>
                    </a>
                    <a href="?page=tickets&status=open" class="list-group-item list-group-item-action d-flex align-items-center py-2">
                        <div class="bg-primary bg-opacity-10 rounded p-2 me-3">
                            <i class="bi bi-folder2-open text-primary"></i>
                        </div>
                        <div class="flex-grow-1">
                            <strong>Open Tickets</strong>
                            <span class="badge bg-primary float-end"><?= $dashboardStats['open'] ?? 0 ?></span>
                        </div>
                    </a>
                    <a href="?page=tickets&sla=breached" class="list-group-item list-group-item-action d-flex align-items-center py-2">
                        <div class="bg-danger bg-opacity-10 rounded p-2 me-3">
                            <i class="bi bi-clock-history text-danger"></i>
                        </div>
                        <div class="flex-grow-1">
                            <strong>SLA Breached</strong>
                            <span class="badge bg-danger float-end"><?= count($breachedTickets) ?></span>
                        </div>
                    </a>
                    <a href="?page=customers" class="list-group-item list-group-item-action d-flex align-items-center py-2">
                        <div class="bg-info bg-opacity-10 rounded p-2 me-3">
                            <i class="bi bi-people text-info"></i>
                        </div>
                        <div class="flex-grow-1">
                            <strong>Customers</strong>
                        </div>
                        <i class="bi bi-chevron-right text-muted"></i>
                    </a>
                    <a href="?page=orders" class="list-group-item list-group-item-action d-flex align-items-center py-2">
                        <div class="bg-success bg-opacity-10 rounded p-2 me-3">
                            <i class="bi bi-cart3 text-success"></i>
                        </div>
                        <div class="flex-grow-1">
                            <strong>Orders</strong>
                        </div>
                        <i class="bi bi-chevron-right text-muted"></i>
                    </a>
                    <a href="?page=reports" class="list-group-item list-group-item-action d-flex align-items-center py-2">
                        <div class="bg-secondary bg-opacity-10 rounded p-2 me-3">
                            <i class="bi bi-graph-up text-secondary"></i>
                        </div>
                        <div class="flex-grow-1">
                            <strong>Reports</strong>
                        </div>
                        <i class="bi bi-chevron-right text-muted"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
