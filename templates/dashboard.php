<?php
$currentUserId = \App\Auth::user()['id'] ?? null;
$canViewAllTickets = \App\Auth::can('tickets.view_all') || \App\Auth::isAdmin();

$userFilterId = $canViewAllTickets ? null : $currentUserId;
$dashboardStats = $ticket->getStats($userFilterId);
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-speedometer2"></i> Dashboard</h2>
    <div class="d-flex align-items-center gap-3">
        <?php if (!$canViewAllTickets): ?>
            <span class="badge bg-info"><i class="bi bi-person"></i> My Data</span>
        <?php endif; ?>
        <span class="text-muted"><?= date('F j, Y') ?></span>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon bg-primary bg-opacity-10 text-primary me-3">
                    <i class="bi bi-ticket"></i>
                </div>
                <div>
                    <h3 class="mb-0"><?= $dashboardStats['total'] ?? 0 ?></h3>
                    <small class="text-muted">Total Tickets</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon bg-warning bg-opacity-10 text-warning me-3">
                    <i class="bi bi-exclamation-circle"></i>
                </div>
                <div>
                    <h3 class="mb-0"><?= $dashboardStats['open'] ?? 0 ?></h3>
                    <small class="text-muted">Open Tickets</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon bg-info bg-opacity-10 text-info me-3">
                    <i class="bi bi-arrow-repeat"></i>
                </div>
                <div>
                    <h3 class="mb-0"><?= $dashboardStats['in_progress'] ?? 0 ?></h3>
                    <small class="text-muted">In Progress</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon bg-success bg-opacity-10 text-success me-3">
                    <i class="bi bi-check-circle"></i>
                </div>
                <div>
                    <h3 class="mb-0"><?= $dashboardStats['resolved'] ?? 0 ?></h3>
                    <small class="text-muted">Resolved</small>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card stat-card border-danger">
            <div class="card-body text-center">
                <h4 class="text-danger mb-0"><?= $dashboardStats['critical'] ?? 0 ?></h4>
                <small class="text-muted">Critical Priority</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card border-warning">
            <div class="card-body text-center">
                <h4 class="text-warning mb-0"><?= $dashboardStats['high'] ?? 0 ?></h4>
                <small class="text-muted">High Priority</small>
            </div>
        </div>
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

<?php
$sla = new \App\SLA();
$slaStats = $sla->getSLAStatistics('30days', $userFilterId);
$breachedTickets = $sla->getBreachedTickets($userFilterId);
$atRiskTickets = $sla->getAtRiskTickets($userFilterId);
?>
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon bg-success bg-opacity-10 text-success me-3">
                    <i class="bi bi-speedometer2"></i>
                </div>
                <div>
                    <h3 class="mb-0"><?= $slaStats['response_compliance'] ?>%</h3>
                    <small class="text-muted">Response SLA</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon bg-primary bg-opacity-10 text-primary me-3">
                    <i class="bi bi-check-circle"></i>
                </div>
                <div>
                    <h3 class="mb-0"><?= $slaStats['resolution_compliance'] ?>%</h3>
                    <small class="text-muted">Resolution SLA</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card border-danger">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon bg-danger bg-opacity-10 text-danger me-3">
                    <i class="bi bi-x-circle"></i>
                </div>
                <div>
                    <h3 class="mb-0"><?= count($breachedTickets) ?></h3>
                    <small class="text-muted">SLA Breached</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card border-warning">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon bg-warning bg-opacity-10 text-warning me-3">
                    <i class="bi bi-exclamation-triangle"></i>
                </div>
                <div>
                    <h3 class="mb-0"><?= count($atRiskTickets) ?></h3>
                    <small class="text-muted">At Risk</small>
                </div>
            </div>
        </div>
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
    <?php if ($canViewAllTickets): ?>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">Team</h5>
            </div>
            <div class="card-body">
                <?php foreach ($users as $u): ?>
                <div class="d-flex align-items-center mb-3">
                    <div class="bg-primary bg-opacity-10 rounded-circle p-2 me-3">
                        <i class="bi bi-person text-primary"></i>
                    </div>
                    <div>
                        <strong><?= htmlspecialchars($u['name']) ?></strong>
                        <br><small class="text-muted"><?= ucfirst($u['role']) ?></small>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">My Summary</h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span>Assigned to me:</span>
                    <strong><?= $dashboardStats['total'] ?? 0 ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Open:</span>
                    <span class="badge bg-warning"><?= $dashboardStats['open'] ?? 0 ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>In Progress:</span>
                    <span class="badge bg-info"><?= $dashboardStats['in_progress'] ?? 0 ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Resolved:</span>
                    <span class="badge bg-success"><?= $dashboardStats['resolved'] ?? 0 ?></span>
                </div>
                <hr>
                <div class="d-flex justify-content-between">
                    <span>SLA Breached:</span>
                    <span class="badge bg-danger"><?= count($breachedTickets) ?></span>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
