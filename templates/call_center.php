<?php
$pageTitle = 'Call Center';
$currentPage = 'call_center';

require_once __DIR__ . '/../src/CallCenter.php';
$callCenter = new CallCenter($db);

$tab = $_GET['tab'] ?? 'dashboard';
$stats = $callCenter->getDashboardStats();
$extensions = $callCenter->getExtensions();
$queues = $callCenter->getQueues();
$trunks = $callCenter->getTrunks();
$recentCalls = $callCenter->getCalls([], 20);

// Get current user's extension
$userExtension = null;
if (isset($_SESSION['user_id'])) {
    $userExtension = $callCenter->getExtensionByUserId($_SESSION['user_id']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Call Center - ISP CRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --cc-sidebar-width: 220px;
            --cc-primary: #fd7e14;
        }
        body {
            padding-top: 40px;
            background-color: #f8f9fa;
        }
        .cc-sidebar {
            position: fixed;
            top: 40px;
            left: 0;
            width: var(--cc-sidebar-width);
            height: calc(100vh - 40px);
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            z-index: 1000;
            padding-top: 1rem;
        }
        .cc-sidebar .brand {
            padding: 0.5rem 1rem 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 1rem;
        }
        .cc-sidebar .brand h5 {
            color: #fff;
            margin: 0;
            font-weight: 600;
        }
        .cc-sidebar .nav-link {
            color: rgba(255,255,255,0.7);
            padding: 0.75rem 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border-left: 3px solid transparent;
            transition: all 0.2s;
        }
        .cc-sidebar .nav-link:hover {
            color: #fff;
            background: rgba(255,255,255,0.05);
        }
        .cc-sidebar .nav-link.active {
            color: #fff;
            background: rgba(253, 126, 20, 0.15);
            border-left-color: var(--cc-primary);
        }
        .cc-sidebar .nav-link i {
            font-size: 1.1rem;
            width: 24px;
            text-align: center;
        }
        .cc-main {
            margin-left: var(--cc-sidebar-width);
            padding: 1.5rem;
            min-height: calc(100vh - 40px);
        }
        @media (max-width: 768px) {
            .cc-sidebar {
                transform: translateX(-100%);
            }
            .cc-main {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Module Navigation Tabs - Top Bar -->
    <div class="module-top-bar" style="position: fixed; top: 0; left: 0; right: 0; z-index: 1100; background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); padding: 0;">
        <div class="container-fluid px-0">
            <div class="d-flex align-items-center ps-3">
                <ul class="nav nav-pills mb-0" style="gap: 2px;">
                    <li class="nav-item">
                        <a class="nav-link py-2 px-4 text-white" href="?page=dashboard" style="border-radius: 0; background: transparent;">
                            <i class="bi bi-grid-3x3-gap me-1"></i>CRM
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link py-2 px-4 text-white" href="?page=isp" style="border-radius: 0; background: transparent;">
                            <i class="bi bi-broadcast me-1"></i>ISP
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link py-2 px-4 text-white" href="?page=huawei-olt" style="border-radius: 0; background: transparent;">
                            <i class="bi bi-router me-1"></i>OMS
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link py-2 px-4 text-white active" href="?page=call_center" style="border-radius: 0; background: #fd7e14; font-weight: 600;">
                            <i class="bi bi-telephone me-1"></i>Call Centre
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link py-2 px-4 text-white" href="?page=finance" style="border-radius: 0; background: transparent;">
                            <i class="bi bi-bank me-1"></i>Finance
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Call Center Sidebar -->
    <aside class="cc-sidebar">
        <div class="brand">
            <h5><i class="bi bi-telephone-fill me-2"></i>Call Center</h5>
            <?php if ($userExtension): ?>
            <small class="text-success"><i class="bi bi-headset me-1"></i>Ext: <?= htmlspecialchars($userExtension['extension']) ?></small>
            <?php endif; ?>
        </div>
        <nav class="nav flex-column">
            <a class="nav-link <?= $tab === 'dashboard' ? 'active' : '' ?>" href="?page=call_center&tab=dashboard">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
            <a class="nav-link <?= $tab === 'calls' ? 'active' : '' ?>" href="?page=call_center&tab=calls">
                <i class="bi bi-telephone"></i> Calls
            </a>
            <a class="nav-link <?= $tab === 'extensions' ? 'active' : '' ?>" href="?page=call_center&tab=extensions">
                <i class="bi bi-person-badge"></i> Extensions
            </a>
            <a class="nav-link <?= $tab === 'queues' ? 'active' : '' ?>" href="?page=call_center&tab=queues">
                <i class="bi bi-people"></i> Queues
            </a>
            <a class="nav-link <?= $tab === 'trunks' ? 'active' : '' ?>" href="?page=call_center&tab=trunks">
                <i class="bi bi-diagram-3"></i> Trunks
            </a>
            <a class="nav-link <?= $tab === 'inbound' ? 'active' : '' ?>" href="?page=call_center&tab=inbound">
                <i class="bi bi-telephone-inbound"></i> Inbound Routes
            </a>
            <a class="nav-link <?= $tab === 'outbound' ? 'active' : '' ?>" href="?page=call_center&tab=outbound">
                <i class="bi bi-telephone-outbound"></i> Outbound Routes
            </a>
            <a class="nav-link <?= $tab === 'ivr' ? 'active' : '' ?>" href="?page=call_center&tab=ivr">
                <i class="bi bi-menu-button-wide"></i> IVR
            </a>
            <a class="nav-link <?= $tab === 'phonebook' ? 'active' : '' ?>" href="?page=call_center&tab=phonebook">
                <i class="bi bi-book"></i> Phonebook
            </a>
            <a class="nav-link <?= $tab === 'settings' ? 'active' : '' ?>" href="?page=call_center&tab=settings">
                <i class="bi bi-gear"></i> Settings
            </a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="cc-main">
        <?php if ($tab === 'dashboard'): ?>
    <!-- Dashboard Tab -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-subtitle mb-2">Today's Calls</h6>
                            <h2 class="mb-0"><?= number_format($stats['total_calls'] ?? 0) ?></h2>
                        </div>
                        <div class="display-4 opacity-50">
                            <i class="bi bi-telephone"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-subtitle mb-2">Answered</h6>
                            <h2 class="mb-0"><?= number_format($stats['answered_calls'] ?? 0) ?></h2>
                        </div>
                        <div class="display-4 opacity-50">
                            <i class="bi bi-telephone-inbound"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-subtitle mb-2">Missed</h6>
                            <h2 class="mb-0"><?= number_format($stats['missed_calls'] ?? 0) ?></h2>
                        </div>
                        <div class="display-4 opacity-50">
                            <i class="bi bi-telephone-x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-subtitle mb-2">Avg Duration</h6>
                            <h2 class="mb-0"><?= gmdate("i:s", $stats['avg_duration'] ?? 0) ?></h2>
                        </div>
                        <div class="display-4 opacity-50">
                            <i class="bi bi-clock"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-person-badge me-2"></i>Extensions
                </div>
                <div class="card-body">
                    <h3><?= count($extensions) ?></h3>
                    <small class="text-muted">Active Extensions</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-people me-2"></i>Queues
                </div>
                <div class="card-body">
                    <h3><?= count($queues) ?></h3>
                    <small class="text-muted">Call Queues</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-diagram-3 me-2"></i>Trunks
                </div>
                <div class="card-body">
                    <h3><?= count($trunks) ?></h3>
                    <small class="text-muted">SIP Trunks</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Dial -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-telephone-outbound me-2"></i>Quick Dial
                </div>
                <div class="card-body">
                    <form id="quickDialForm" class="row g-3">
                        <div class="col-8">
                            <input type="tel" class="form-control form-control-lg" id="dialNumber" 
                                   placeholder="Enter phone number..." required>
                        </div>
                        <div class="col-4">
                            <button type="submit" class="btn btn-success btn-lg w-100" 
                                    <?= !$userExtension ? 'disabled title="No extension assigned"' : '' ?>>
                                <i class="bi bi-telephone"></i> Call
                            </button>
                        </div>
                    </form>
                    <?php if (!$userExtension): ?>
                    <small class="text-danger mt-2 d-block">
                        <i class="bi bi-exclamation-triangle"></i> 
                        No extension assigned to your account. Contact admin.
                    </small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-clock-history me-2"></i>Recent Calls
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 200px; overflow-y: auto;">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="sticky-top bg-white">
                                <tr>
                                    <th>Time</th>
                                    <th>Number</th>
                                    <th>Duration</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($recentCalls, 0, 10) as $call): ?>
                                <tr>
                                    <td><?= date('H:i', strtotime($call['call_date'])) ?></td>
                                    <td>
                                        <?php if ($call['direction'] === 'inbound'): ?>
                                        <i class="bi bi-telephone-inbound text-success"></i>
                                        <?php else: ?>
                                        <i class="bi bi-telephone-outbound text-primary"></i>
                                        <?php endif; ?>
                                        <?= htmlspecialchars($call['direction'] === 'inbound' ? $call['src'] : $call['dst']) ?>
                                    </td>
                                    <td><?= gmdate("i:s", $call['duration'] ?? 0) ?></td>
                                    <td>
                                        <?php
                                        $statusClass = match($call['disposition']) {
                                            'ANSWERED' => 'success',
                                            'NO ANSWER' => 'warning',
                                            'BUSY' => 'danger',
                                            default => 'secondary'
                                        };
                                        ?>
                                        <span class="badge bg-<?= $statusClass ?>"><?= $call['disposition'] ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Speed Dial & Agent Status Row -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-lightning-fill me-2"></i>Speed Dial</span>
                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#speedDialModal">
                        <i class="bi bi-plus"></i> Add
                    </button>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <?php foreach ($extensions as $ext): ?>
                        <div class="col-6 col-md-4">
                            <button class="btn btn-outline-secondary w-100 text-start py-2" 
                                    onclick="callExtension('<?= htmlspecialchars($ext['extension']) ?>', '<?= htmlspecialchars($ext['name']) ?>')"
                                    <?= !$userExtension ? 'disabled' : '' ?>>
                                <i class="bi bi-person-circle me-1"></i>
                                <strong><?= htmlspecialchars($ext['extension']) ?></strong>
                                <small class="d-block text-muted"><?= htmlspecialchars($ext['name']) ?></small>
                            </button>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($extensions)): ?>
                        <div class="col-12 text-center text-muted py-3">
                            <i class="bi bi-telephone-x"></i> No extensions configured
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-people-fill me-2"></i>Agent Status
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 250px; overflow-y: auto;">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="sticky-top bg-white">
                                <tr>
                                    <th>Agent</th>
                                    <th>Extension</th>
                                    <th>Status</th>
                                    <th>Since</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($extensions as $ext): 
                                    $status = $ext['agent_status'] ?? 'available';
                                    $statusColors = [
                                        'available' => 'success',
                                        'busy' => 'danger',
                                        'away' => 'warning',
                                        'offline' => 'secondary',
                                        'on_call' => 'primary'
                                    ];
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($ext['name']) ?></td>
                                    <td><strong><?= htmlspecialchars($ext['extension']) ?></strong></td>
                                    <td>
                                        <span class="badge bg-<?= $statusColors[$status] ?? 'secondary' ?>">
                                            <?= ucfirst(str_replace('_', ' ', $status)) ?>
                                        </span>
                                    </td>
                                    <td><small class="text-muted">-</small></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Stats Row -->
    <div class="row">
        <div class="col-md-4">
            <div class="card border-success">
                <div class="card-body text-center">
                    <h4 class="text-success mb-1"><?= count(array_filter($extensions, fn($e) => ($e['agent_status'] ?? 'available') === 'available')) ?></h4>
                    <small class="text-muted">Agents Available</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-primary">
                <div class="card-body text-center">
                    <h4 class="text-primary mb-1">0</h4>
                    <small class="text-muted">Calls in Progress</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-warning">
                <div class="card-body text-center">
                    <h4 class="text-warning mb-1">0</h4>
                    <small class="text-muted">Calls Waiting</small>
                </div>
            </div>
        </div>
    </div>

    <?php elseif ($tab === 'calls'): ?>
    <!-- Calls Tab -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-telephone me-2"></i>Call History</span>
            <form class="d-flex gap-2" method="get">
                <input type="hidden" name="page" value="call_center">
                <input type="hidden" name="tab" value="calls">
                <input type="date" name="date_from" class="form-control form-control-sm" 
                       value="<?= $_GET['date_from'] ?? date('Y-m-d') ?>">
                <input type="date" name="date_to" class="form-control form-control-sm"
                       value="<?= $_GET['date_to'] ?? date('Y-m-d') ?>">
                <select name="direction" class="form-select form-select-sm">
                    <option value="">All Directions</option>
                    <option value="inbound" <?= ($_GET['direction'] ?? '') === 'inbound' ? 'selected' : '' ?>>Inbound</option>
                    <option value="outbound" <?= ($_GET['direction'] ?? '') === 'outbound' ? 'selected' : '' ?>>Outbound</option>
                </select>
                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
            </form>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Date/Time</th>
                            <th>Direction</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Extension</th>
                            <th>Duration</th>
                            <th>Status</th>
                            <th>Customer</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $filters = array_filter([
                            'date_from' => $_GET['date_from'] ?? null,
                            'date_to' => $_GET['date_to'] ?? null,
                            'direction' => $_GET['direction'] ?? null
                        ]);
                        $calls = $callCenter->getCalls($filters, 100);
                        foreach ($calls as $call): 
                        ?>
                        <tr>
                            <td><?= date('Y-m-d H:i:s', strtotime($call['call_date'])) ?></td>
                            <td>
                                <?php if ($call['direction'] === 'inbound'): ?>
                                <span class="badge bg-success"><i class="bi bi-telephone-inbound"></i> Inbound</span>
                                <?php else: ?>
                                <span class="badge bg-primary"><i class="bi bi-telephone-outbound"></i> Outbound</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($call['src']) ?></td>
                            <td><?= htmlspecialchars($call['dst']) ?></td>
                            <td><?= htmlspecialchars($call['extension_name'] ?? '-') ?></td>
                            <td><?= gmdate("H:i:s", $call['duration'] ?? 0) ?></td>
                            <td>
                                <?php
                                $statusClass = match($call['disposition']) {
                                    'ANSWERED' => 'success',
                                    'NO ANSWER' => 'warning',
                                    'BUSY' => 'danger',
                                    default => 'secondary'
                                };
                                ?>
                                <span class="badge bg-<?= $statusClass ?>"><?= $call['disposition'] ?></span>
                            </td>
                            <td>
                                <?php if ($call['customer_name']): ?>
                                <a href="?page=customers&action=view&id=<?= $call['customer_id'] ?>">
                                    <?= htmlspecialchars($call['customer_name']) ?>
                                </a>
                                <?php else: ?>
                                <button class="btn btn-sm btn-outline-primary" onclick="linkCustomer(<?= $call['id'] ?>)">
                                    Link Customer
                                </button>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($call['recording_file']): ?>
                                <button class="btn btn-sm btn-outline-secondary" onclick="playRecording('<?= $call['recording_file'] ?>')">
                                    <i class="bi bi-play"></i>
                                </button>
                                <?php endif; ?>
                                <button class="btn btn-sm btn-outline-success" onclick="callBack('<?= $call['direction'] === 'inbound' ? $call['src'] : $call['dst'] ?>')">
                                    <i class="bi bi-telephone"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php elseif ($tab === 'extensions'): ?>
    <!-- Extensions Tab -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-person-badge me-2"></i>Extensions</span>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#extensionModal">
                <i class="bi bi-plus"></i> Add Extension
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Extension</th>
                            <th>Name</th>
                            <th>Assigned User</th>
                            <th>Device Type</th>
                            <th>Caller ID</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($extensions as $ext): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($ext['extension']) ?></strong></td>
                            <td><?= htmlspecialchars($ext['name']) ?></td>
                            <td><?= htmlspecialchars($ext['user_name'] ?? '-') ?></td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($ext['device_type']) ?></span></td>
                            <td><?= htmlspecialchars($ext['caller_id'] ?? '-') ?></td>
                            <td>
                                <?php if ($ext['is_active']): ?>
                                <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                <span class="badge bg-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($userExtension && $userExtension['extension'] !== $ext['extension']): ?>
                                <button class="btn btn-sm btn-success me-1" onclick="callExtension('<?= htmlspecialchars($ext['extension']) ?>', '<?= htmlspecialchars($ext['name']) ?>')" title="Call this extension">
                                    <i class="bi bi-telephone-fill"></i>
                                </button>
                                <?php endif; ?>
                                <button class="btn btn-sm btn-outline-primary" onclick="editExtension(<?= $ext['id'] ?>)">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteExtension(<?= $ext['id'] ?>)">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Quick Dial Modal -->
    <div class="modal fade" id="quickDialModal" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="bi bi-telephone-fill me-2"></i>Call Extension</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <p class="mb-2">Calling:</p>
                    <h4 id="dialExtName" class="mb-1"></h4>
                    <p class="text-muted" id="dialExtNumber"></p>
                    <div id="dialStatus" class="mt-3">
                        <div class="spinner-border text-success" role="status">
                            <span class="visually-hidden">Calling...</span>
                        </div>
                        <p class="mt-2 text-muted">Initiating call...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php elseif ($tab === 'queues'): ?>
    <!-- Queues Tab -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-people me-2"></i>Call Queues</span>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#queueModal">
                <i class="bi bi-plus"></i> Add Queue
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Extension</th>
                            <th>Strategy</th>
                            <th>Members</th>
                            <th>Timeout</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($queues as $queue): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($queue['name']) ?></strong></td>
                            <td><?= htmlspecialchars($queue['extension']) ?></td>
                            <td><span class="badge bg-info"><?= htmlspecialchars($queue['strategy']) ?></span></td>
                            <td><?= $queue['member_count'] ?> agents</td>
                            <td><?= $queue['timeout'] ?>s</td>
                            <td>
                                <?php if ($queue['is_active']): ?>
                                <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                <span class="badge bg-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-info" onclick="manageQueueMembers(<?= $queue['id'] ?>)">
                                    <i class="bi bi-people"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-primary" onclick="editQueue(<?= $queue['id'] ?>)">
                                    <i class="bi bi-pencil"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php elseif ($tab === 'trunks'): ?>
    <!-- Trunks Tab -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-diagram-3 me-2"></i>SIP Trunks</span>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#trunkModal">
                <i class="bi bi-plus"></i> Add Trunk
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Host</th>
                            <th>Port</th>
                            <th>Codecs</th>
                            <th>Max Channels</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($trunks as $trunk): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($trunk['name']) ?></strong></td>
                            <td><span class="badge bg-<?= $trunk['trunk_type'] === 'peer' ? 'primary' : 'info' ?>"><?= htmlspecialchars($trunk['trunk_type']) ?></span></td>
                            <td><?= htmlspecialchars($trunk['host']) ?></td>
                            <td><?= $trunk['port'] ?></td>
                            <td><small><?= htmlspecialchars($trunk['codecs']) ?></small></td>
                            <td><?= $trunk['max_channels'] ?></td>
                            <td>
                                <?php if ($trunk['is_active']): ?>
                                <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                <span class="badge bg-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" onclick="editTrunk(<?= $trunk['id'] ?>)">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteTrunk(<?= $trunk['id'] ?>)">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php elseif ($tab === 'phonebook'): ?>
    <!-- Phonebook Tab -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-book me-2"></i>Phonebook</span>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#phonebookModal">
                <i class="bi bi-plus"></i> Add Contact
            </button>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-6">
                    <input type="text" class="form-control" id="phonebookSearch" placeholder="Search contacts...">
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="phonebookCategory">
                        <option value="">All Categories</option>
                        <option value="customer">Customer</option>
                        <option value="vendor">Vendor</option>
                        <option value="internal">Internal</option>
                        <option value="emergency">Emergency</option>
                    </select>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover" id="phonebookTable">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Mobile</th>
                            <th>Category</th>
                            <th>Company</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><i class="bi bi-person-circle me-2"></i>Emergency Services</td>
                            <td><strong>999</strong></td>
                            <td>-</td>
                            <td><span class="badge bg-danger">Emergency</span></td>
                            <td>-</td>
                            <td>
                                <button class="btn btn-sm btn-success" onclick="callNumber('999', 'Emergency Services')" <?= !$userExtension ? 'disabled' : '' ?>>
                                    <i class="bi bi-telephone-fill"></i>
                                </button>
                            </td>
                        </tr>
                        <tr>
                            <td><i class="bi bi-person-circle me-2"></i>Police</td>
                            <td><strong>112</strong></td>
                            <td>-</td>
                            <td><span class="badge bg-danger">Emergency</span></td>
                            <td>-</td>
                            <td>
                                <button class="btn btn-sm btn-success" onclick="callNumber('112', 'Police')" <?= !$userExtension ? 'disabled' : '' ?>>
                                    <i class="bi bi-telephone-fill"></i>
                                </button>
                            </td>
                        </tr>
                        <tr>
                            <td><i class="bi bi-building me-2"></i>ISP NOC</td>
                            <td><strong>+254700000001</strong></td>
                            <td>+254700000002</td>
                            <td><span class="badge bg-info">Internal</span></td>
                            <td>ISP Company</td>
                            <td>
                                <button class="btn btn-sm btn-success" onclick="callNumber('+254700000001', 'ISP NOC')" <?= !$userExtension ? 'disabled' : '' ?>>
                                    <i class="bi bi-telephone-fill"></i>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php elseif ($tab === 'settings'): ?>
    <!-- Settings Tab -->
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="bi bi-server me-2"></i>PBX Connection
                </div>
                <div class="card-body">
                    <form id="pbxSettingsForm">
                        <div class="mb-3">
                            <label class="form-label">FreePBX/Asterisk Host</label>
                            <input type="text" class="form-control" name="pbx_host" value="<?= htmlspecialchars(getenv('FREEPBX_HOST') ?: 'localhost') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">AMI Port</label>
                            <input type="number" class="form-control" name="ami_port" value="<?= htmlspecialchars(getenv('FREEPBX_AMI_PORT') ?: '5038') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">AMI Username</label>
                            <input type="text" class="form-control" name="ami_user" value="<?= htmlspecialchars(getenv('FREEPBX_AMI_USER') ?: '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">AMI Password</label>
                            <input type="password" class="form-control" name="ami_pass">
                        </div>
                        <button type="button" class="btn btn-secondary" onclick="testPBXConnection()">
                            <i class="bi bi-plug"></i> Test Connection
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Save Settings
                        </button>
                    </form>
                    <div id="pbxTestResult" class="mt-3"></div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="bi bi-sliders me-2"></i>Call Settings
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Default Ring Timeout (seconds)</label>
                        <input type="number" class="form-control" value="30">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Recording Storage Path</label>
                        <input type="text" class="form-control" value="/var/spool/asterisk/monitor">
                    </div>
                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" id="enableRecording" checked>
                        <label class="form-check-label" for="enableRecording">Enable Call Recording</label>
                    </div>
                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" id="enableVoicemail" checked>
                        <label class="form-check-label" for="enableVoicemail">Enable Voicemail</label>
                    </div>
                    <button class="btn btn-primary">
                        <i class="bi bi-save"></i> Save Settings
                    </button>
                </div>
            </div>
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-info-circle me-2"></i>System Status
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr>
                            <td>PBX Connection</td>
                            <td><span class="badge bg-warning">Not Configured</span></td>
                        </tr>
                        <tr>
                            <td>Active Extensions</td>
                            <td><strong><?= count($extensions) ?></strong></td>
                        </tr>
                        <tr>
                            <td>Active Queues</td>
                            <td><strong><?= count($queues) ?></strong></td>
                        </tr>
                        <tr>
                            <td>Active Trunks</td>
                            <td><strong><?= count($trunks) ?></strong></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <?php elseif ($tab === 'inbound'): ?>
    <!-- Inbound Routes Tab -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <span><i class="bi bi-telephone-inbound me-2"></i>Inbound Routes</span>
        <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#inboundRouteModal" onclick="clearInboundForm()">
            <i class="bi bi-plus-lg"></i> Add Inbound Route
        </button>
    </div>
    
    <div class="card">
        <div class="card-body p-0">
            <table class="table table-striped table-hover mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>Name</th>
                        <th>DID Pattern</th>
                        <th>CID Pattern</th>
                        <th>Destination</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th width="120">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $inboundRoutes = $db->query("SELECT * FROM call_center_inbound_routes ORDER BY priority DESC, name")->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($inboundRoutes as $route): 
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($route['name']) ?></strong></td>
                        <td><code><?= htmlspecialchars($route['did_pattern'] ?: 'Any') ?></code></td>
                        <td><code><?= htmlspecialchars($route['cid_pattern'] ?: 'Any') ?></code></td>
                        <td>
                            <span class="badge bg-info"><?= ucfirst($route['destination_type']) ?></span>
                            <?= htmlspecialchars($route['destination_id']) ?>
                        </td>
                        <td><?= $route['priority'] ?></td>
                        <td>
                            <?php if ($route['is_active']): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Disabled</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="editInboundRoute(<?= htmlspecialchars(json_encode($route)) ?>)">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteInboundRoute(<?= $route['id'] ?>)">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($inboundRoutes)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            <i class="bi bi-inbox display-4"></i>
                            <p class="mt-2">No inbound routes configured</p>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php elseif ($tab === 'outbound'): ?>
    <!-- Outbound Routes Tab -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <span><i class="bi bi-telephone-outbound me-2"></i>Outbound Routes</span>
        <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#outboundRouteModal" onclick="clearOutboundForm()">
            <i class="bi bi-plus-lg"></i> Add Outbound Route
        </button>
    </div>
    
    <div class="card">
        <div class="card-body p-0">
            <table class="table table-striped table-hover mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>Name</th>
                        <th>Dial Pattern</th>
                        <th>Prepend/Prefix</th>
                        <th>Trunk</th>
                        <th>Caller ID</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th width="120">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $outboundRoutes = $db->query("SELECT o.*, t.name as trunk_name FROM call_center_outbound_routes o LEFT JOIN call_center_trunks t ON o.trunk_id = t.id ORDER BY o.priority DESC, o.name")->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($outboundRoutes as $route): 
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($route['name']) ?></strong></td>
                        <td><code><?= htmlspecialchars($route['dial_pattern']) ?></code></td>
                        <td>
                            <?php if ($route['prepend']): ?>
                                <span class="badge bg-primary">+<?= htmlspecialchars($route['prepend']) ?></span>
                            <?php endif; ?>
                            <?php if ($route['prefix']): ?>
                                <span class="badge bg-secondary">-<?= htmlspecialchars($route['prefix']) ?></span>
                            <?php endif; ?>
                            <?php if (!$route['prepend'] && !$route['prefix']): ?>-<?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($route['trunk_name'] ?: 'None') ?></td>
                        <td><?= htmlspecialchars($route['caller_id'] ?: 'Default') ?></td>
                        <td><?= $route['priority'] ?></td>
                        <td>
                            <?php if ($route['is_active']): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Disabled</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="editOutboundRoute(<?= htmlspecialchars(json_encode($route)) ?>)">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteOutboundRoute(<?= $route['id'] ?>)">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($outboundRoutes)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            <i class="bi bi-inbox display-4"></i>
                            <p class="mt-2">No outbound routes configured</p>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php elseif ($tab === 'ivr'): ?>
    <!-- IVR Tab -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <span><i class="bi bi-menu-button-wide me-2"></i>IVR Menus</span>
        <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#ivrModal" onclick="clearIvrForm()">
            <i class="bi bi-plus-lg"></i> Add IVR Menu
        </button>
    </div>
    
    <div class="row">
        <?php 
        $ivrMenus = $db->query("SELECT * FROM call_center_ivr ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($ivrMenus as $ivr): 
            $options = $db->prepare("SELECT * FROM call_center_ivr_options WHERE ivr_id = ? ORDER BY digit");
            $options->execute([$ivr['id']]);
            $ivrOptions = $options->fetchAll(PDO::FETCH_ASSOC);
        ?>
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>
                        <i class="bi bi-menu-button-wide me-2"></i>
                        <strong><?= htmlspecialchars($ivr['name']) ?></strong>
                    </span>
                    <span>
                        <?php if ($ivr['is_active']): ?>
                            <span class="badge bg-success">Active</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Disabled</span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="card-body">
                    <?php if ($ivr['description']): ?>
                    <p class="text-muted small"><?= htmlspecialchars($ivr['description']) ?></p>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <small class="text-muted">Announcement:</small><br>
                        <span><?= htmlspecialchars($ivr['announcement'] ?: 'None') ?></span>
                    </div>
                    
                    <h6>Menu Options:</h6>
                    <table class="table table-sm table-bordered">
                        <thead>
                            <tr>
                                <th width="50">Key</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ivrOptions as $opt): ?>
                            <tr>
                                <td class="text-center"><kbd><?= htmlspecialchars($opt['digit']) ?></kbd></td>
                                <td>
                                    <span class="badge bg-info"><?= ucfirst($opt['destination_type']) ?></span>
                                    <?= htmlspecialchars($opt['destination_id']) ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($ivrOptions)): ?>
                            <tr>
                                <td colspan="2" class="text-center text-muted">No options defined</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    
                    <div class="row text-muted small">
                        <div class="col-6">
                            <i class="bi bi-clock"></i> Timeout: <?= $ivr['timeout'] ?>s
                        </div>
                        <div class="col-6">
                            <i class="bi bi-arrow-repeat"></i> Max Loops: <?= $ivr['max_loops'] ?>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button class="btn btn-sm btn-outline-primary" onclick="editIvr(<?= $ivr['id'] ?>)">
                        <i class="bi bi-pencil"></i> Edit
                    </button>
                    <button class="btn btn-sm btn-outline-info" onclick="manageIvrOptions(<?= $ivr['id'] ?>, '<?= htmlspecialchars($ivr['name']) ?>')">
                        <i class="bi bi-list-ol"></i> Options
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteIvr(<?= $ivr['id'] ?>)">
                        <i class="bi bi-trash"></i> Delete
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        
        <?php if (empty($ivrMenus)): ?>
        <div class="col-12">
            <div class="card">
                <div class="card-body text-center text-muted py-5">
                    <i class="bi bi-menu-button-wide display-4"></i>
                    <p class="mt-2">No IVR menus configured</p>
                    <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#ivrModal" onclick="clearIvrForm()">
                        <i class="bi bi-plus-lg"></i> Create Your First IVR
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php endif; ?>

<!-- Extension Modal -->
<div class="modal fade" id="extensionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add/Edit Extension</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="extensionForm" method="post" action="?page=call_center&action=save_extension">
                <div class="modal-body">
                    <input type="hidden" name="id" id="ext_id">
                    <div class="mb-3">
                        <label class="form-label">Extension Number</label>
                        <input type="text" class="form-control" name="extension" id="ext_extension" required pattern="[0-9]+" placeholder="e.g., 1001">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" class="form-control" name="name" id="ext_name" required placeholder="e.g., Support Agent 1">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Assign to User</label>
                        <select class="form-select" name="user_id" id="ext_user_id">
                            <option value="">-- None --</option>
                            <?php 
                            $usersStmt = $db->query("SELECT id, name FROM users ORDER BY name");
                            while ($user = $usersStmt->fetch(PDO::FETCH_ASSOC)):
                            ?>
                            <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Secret/Password</label>
                        <input type="text" class="form-control" name="secret" id="ext_secret" placeholder="SIP password">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Caller ID</label>
                        <input type="text" class="form-control" name="caller_id" id="ext_caller_id" placeholder="e.g., Support <1234567890>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Device Type</label>
                        <select class="form-select" name="device_type" id="ext_device_type">
                            <option value="softphone">Softphone</option>
                            <option value="ip_phone">IP Phone</option>
                            <option value="webrtc">WebRTC</option>
                        </select>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="is_active" id="ext_is_active" checked>
                        <label class="form-check-label" for="ext_is_active">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Extension</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Trunk Modal -->
<div class="modal fade" id="trunkModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add/Edit SIP Trunk</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="trunkForm" method="post" action="?page=call_center&action=save_trunk">
                <div class="modal-body">
                    <input type="hidden" name="id" id="trunk_id">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Trunk Name</label>
                            <input type="text" class="form-control" name="name" id="trunk_name" required placeholder="e.g., Safaricom SIP">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Trunk Type</label>
                            <select class="form-select" name="trunk_type" id="trunk_type">
                                <option value="peer">Peer (IP-based)</option>
                                <option value="registration">Registration (User/Pass)</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Host/IP Address</label>
                            <input type="text" class="form-control" name="host" id="trunk_host" required placeholder="e.g., sip.provider.com">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Port</label>
                            <input type="number" class="form-control" name="port" id="trunk_port" value="5060">
                        </div>
                    </div>
                    <div class="row" id="trunkCredentials">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" name="username" id="trunk_username">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Secret/Password</label>
                            <input type="password" class="form-control" name="secret" id="trunk_secret">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Codecs (comma-separated)</label>
                            <input type="text" class="form-control" name="codecs" id="trunk_codecs" value="ulaw,alaw,g729">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Max Channels</label>
                            <input type="number" class="form-control" name="max_channels" id="trunk_max_channels" value="30">
                        </div>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="is_active" id="trunk_is_active" checked>
                        <label class="form-check-label" for="trunk_is_active">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Trunk</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Queue Modal -->
<div class="modal fade" id="queueModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add/Edit Queue</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="queueForm" method="post" action="?page=call_center&action=save_queue">
                <div class="modal-body">
                    <input type="hidden" name="id" id="queue_id">
                    <div class="mb-3">
                        <label class="form-label">Queue Name</label>
                        <input type="text" class="form-control" name="name" id="queue_name" required placeholder="e.g., Support Queue">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Extension</label>
                        <input type="text" class="form-control" name="extension" id="queue_extension" required pattern="[0-9]+" placeholder="e.g., 8001">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Ring Strategy</label>
                        <select class="form-select" name="strategy" id="queue_strategy">
                            <option value="ringall">Ring All</option>
                            <option value="leastrecent">Least Recent</option>
                            <option value="fewestcalls">Fewest Calls</option>
                            <option value="random">Random</option>
                            <option value="rrmemory">Round Robin (Memory)</option>
                            <option value="linear">Linear</option>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Ring Timeout (sec)</label>
                            <input type="number" class="form-control" name="timeout" id="queue_timeout" value="30">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Wrapup Time (sec)</label>
                            <input type="number" class="form-control" name="wrapup_time" id="queue_wrapup_time" value="5">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Max Wait Time (sec)</label>
                        <input type="number" class="form-control" name="max_wait_time" id="queue_max_wait_time" value="300">
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="is_active" id="queue_is_active" checked>
                        <label class="form-check-label" for="queue_is_active">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Queue</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Inbound Route Modal -->
<div class="modal fade" id="inboundRouteModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="bi bi-telephone-inbound me-2"></i>Inbound Route</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="inboundRouteForm" method="post" action="?page=call_center&action=save_inbound_route">
                <div class="modal-body">
                    <input type="hidden" name="id" id="inbound_id">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Route Name *</label>
                            <input type="text" class="form-control" name="name" id="inbound_name" required placeholder="e.g., Main Number">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Priority</label>
                            <input type="number" class="form-control" name="priority" id="inbound_priority" value="0">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="inbound_description" rows="2"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">DID Pattern</label>
                            <input type="text" class="form-control" name="did_pattern" id="inbound_did_pattern" placeholder="e.g., 0722XXXXXXX or leave blank for any">
                            <small class="text-muted">Leave blank to match any DID</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Caller ID Pattern</label>
                            <input type="text" class="form-control" name="cid_pattern" id="inbound_cid_pattern" placeholder="e.g., 254XXXXXXXXX">
                            <small class="text-muted">Leave blank to match any caller ID</small>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Destination Type *</label>
                            <select class="form-select" name="destination_type" id="inbound_destination_type" required>
                                <option value="extension">Extension</option>
                                <option value="queue">Queue</option>
                                <option value="ivr">IVR</option>
                                <option value="ring_group">Ring Group</option>
                                <option value="voicemail">Voicemail</option>
                                <option value="hangup">Hangup</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Destination</label>
                            <input type="text" class="form-control" name="destination_id" id="inbound_destination_id" placeholder="e.g., 101 or queue number">
                        </div>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="is_active" id="inbound_is_active" checked>
                        <label class="form-check-label" for="inbound_is_active">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Save Route</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Outbound Route Modal -->
<div class="modal fade" id="outboundRouteModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="bi bi-telephone-outbound me-2"></i>Outbound Route</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="outboundRouteForm" method="post" action="?page=call_center&action=save_outbound_route">
                <div class="modal-body">
                    <input type="hidden" name="id" id="outbound_id">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Route Name *</label>
                            <input type="text" class="form-control" name="name" id="outbound_name" required placeholder="e.g., Local Calls">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Priority</label>
                            <input type="number" class="form-control" name="priority" id="outbound_priority" value="0">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="outbound_description" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Dial Pattern *</label>
                        <input type="text" class="form-control" name="dial_pattern" id="outbound_dial_pattern" required placeholder="e.g., 0XXXXXXXXX or _254XXXXXXXXX">
                        <small class="text-muted">Use _ for pattern matching (e.g., _0X. matches 0 followed by any digits)</small>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Prepend</label>
                            <input type="text" class="form-control" name="prepend" id="outbound_prepend" placeholder="Digits to add before dialing">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Prefix (Strip)</label>
                            <input type="text" class="form-control" name="prefix" id="outbound_prefix" placeholder="Digits to remove from beginning">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Trunk *</label>
                            <select class="form-select" name="trunk_id" id="outbound_trunk_id" required>
                                <option value="">-- Select Trunk --</option>
                                <?php foreach ($trunks as $trunk): ?>
                                <option value="<?= $trunk['id'] ?>"><?= htmlspecialchars($trunk['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Caller ID Override</label>
                            <input type="text" class="form-control" name="caller_id" id="outbound_caller_id" placeholder="Override caller ID">
                        </div>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="is_active" id="outbound_is_active" checked>
                        <label class="form-check-label" for="outbound_is_active">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Save Route</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- IVR Modal -->
<div class="modal fade" id="ivrModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="bi bi-menu-button-wide me-2"></i>IVR Menu</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="ivrForm" method="post" action="?page=call_center&action=save_ivr">
                <div class="modal-body">
                    <input type="hidden" name="id" id="ivr_id">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label">IVR Name *</label>
                            <input type="text" class="form-control" name="name" id="ivr_name" required placeholder="e.g., Main Menu">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Timeout (sec)</label>
                            <input type="number" class="form-control" name="timeout" id="ivr_timeout" value="10">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="ivr_description" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Announcement</label>
                        <textarea class="form-control" name="announcement" id="ivr_announcement" rows="3" placeholder="e.g., Welcome to our company. Press 1 for Sales, 2 for Support..."></textarea>
                        <small class="text-muted">Text to be played to callers (or path to audio file)</small>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Timeout Destination</label>
                            <select class="form-select" name="timeout_destination_type" id="ivr_timeout_destination_type">
                                <option value="hangup">Hangup</option>
                                <option value="repeat">Repeat Menu</option>
                                <option value="extension">Extension</option>
                                <option value="queue">Queue</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Timeout Destination ID</label>
                            <input type="text" class="form-control" name="timeout_destination_id" id="ivr_timeout_destination_id">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Invalid Destination</label>
                            <select class="form-select" name="invalid_destination_type" id="ivr_invalid_destination_type">
                                <option value="repeat">Repeat Menu</option>
                                <option value="hangup">Hangup</option>
                                <option value="extension">Extension</option>
                                <option value="queue">Queue</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Invalid Destination ID</label>
                            <input type="text" class="form-control" name="invalid_destination_id" id="ivr_invalid_destination_id" placeholder="e.g., 101 or queue number">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Max Loops</label>
                            <input type="number" class="form-control" name="max_loops" id="ivr_max_loops" value="3">
                        </div>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="is_active" id="ivr_is_active" checked>
                        <label class="form-check-label" for="ivr_is_active">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Save IVR</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- IVR Options Modal -->
<div class="modal fade" id="ivrOptionsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="bi bi-list-ol me-2"></i>IVR Options - <span id="ivrOptionsTitle"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="ivrOptionsId">
                <div class="mb-4">
                    <h6>Add New Option</h6>
                    <form id="ivrOptionForm" class="row g-2">
                        <div class="col-md-2">
                            <input type="text" class="form-control" name="digit" id="option_digit" placeholder="Key" maxlength="2" required>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" name="destination_type" id="option_destination_type" required>
                                <option value="extension">Extension</option>
                                <option value="queue">Queue</option>
                                <option value="ivr">IVR</option>
                                <option value="voicemail">Voicemail</option>
                                <option value="hangup">Hangup</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <input type="text" class="form-control" name="destination_id" id="option_destination_id" placeholder="Destination">
                        </div>
                        <div class="col-md-3">
                            <input type="text" class="form-control" name="description" id="option_description" placeholder="Description">
                        </div>
                        <div class="col-md-1">
                            <button type="submit" class="btn btn-success"><i class="bi bi-plus"></i></button>
                        </div>
                    </form>
                </div>
                <h6>Current Options</h6>
                <table class="table table-sm" id="ivrOptionsTable">
                    <thead>
                        <tr>
                            <th>Key</th>
                            <th>Destination</th>
                            <th>Description</th>
                            <th width="60">Action</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
// Current user's extension for internal calls
const myExtension = <?= json_encode($userExtension ? $userExtension['extension'] : null) ?>;

// Quick dial
document.getElementById('quickDialForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const number = document.getElementById('dialNumber').value;
    
    try {
        const response = await fetch('?page=call_center&action=originate&ajax=1', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({destination: number})
        });
        const result = await response.json();
        
        if (result.success) {
            showToast('Call initiated to ' + number, 'success');
        } else {
            showToast(result.error || 'Failed to initiate call', 'danger');
        }
    } catch (err) {
        showToast('Error: ' + err.message, 'danger');
    }
});

// Trunk type toggle
document.getElementById('trunk_type')?.addEventListener('change', function() {
    document.getElementById('trunkCredentials').style.display = 
        this.value === 'registration' ? 'flex' : 'none';
});

function callBack(number) {
    document.getElementById('dialNumber').value = number;
    document.getElementById('quickDialForm').dispatchEvent(new Event('submit'));
}

function callNumber(number, name) {
    callExtension(number, name);
}

function linkCustomer(callId) {
    const customerId = prompt('Enter Customer ID to link:');
    if (customerId) {
        fetch('?page=call_center&action=link_customer', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({call_id: callId, customer_id: customerId})
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast('Customer linked successfully', 'success');
                location.reload();
            } else {
                showToast(data.error || 'Failed to link customer', 'danger');
            }
        });
    }
}

function playRecording(file) {
    const modal = document.createElement('div');
    modal.innerHTML = `
        <div class="modal fade show" style="display:block; background:rgba(0,0,0,0.5);">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-play-circle me-2"></i>Call Recording</h5>
                        <button type="button" class="btn-close" onclick="this.closest('.modal').parentElement.remove()"></button>
                    </div>
                    <div class="modal-body text-center">
                        <audio controls autoplay style="width:100%">
                            <source src="${file}" type="audio/wav">
                            Your browser does not support audio playback.
                        </audio>
                    </div>
                </div>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
}

function testPBXConnection() {
    document.getElementById('pbxTestResult').innerHTML = `
        <div class="alert alert-info">
            <i class="bi bi-hourglass-split me-2"></i>Testing connection...
        </div>
    `;
    
    fetch('?page=call_center&action=test_connection')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('pbxTestResult').innerHTML = `
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle me-2"></i>Connection successful! PBX is online.
                    </div>
                `;
            } else {
                document.getElementById('pbxTestResult').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-x-circle me-2"></i>${data.error || 'Connection failed'}
                    </div>
                `;
            }
        })
        .catch(err => {
            document.getElementById('pbxTestResult').innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-x-circle me-2"></i>Error: ${err.message}
                </div>
            `;
        });
}

function showToast(message, type) {
    const toast = document.createElement('div');
    toast.className = `alert alert-${type} position-fixed`;
    toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999;';
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

function editExtension(id) {
    fetch('?page=call_center&action=get_extension&id=' + id)
        .then(r => r.json())
        .then(data => {
            document.getElementById('ext_id').value = data.id;
            document.getElementById('ext_extension').value = data.extension;
            document.getElementById('ext_name').value = data.name;
            document.getElementById('ext_user_id').value = data.user_id || '';
            document.getElementById('ext_secret').value = data.secret || '';
            document.getElementById('ext_caller_id').value = data.caller_id || '';
            document.getElementById('ext_device_type').value = data.device_type;
            document.getElementById('ext_is_active').checked = data.is_active;
            new bootstrap.Modal(document.getElementById('extensionModal')).show();
        });
}

function editTrunk(id) {
    fetch('?page=call_center&action=get_trunk&id=' + id)
        .then(r => r.json())
        .then(data => {
            document.getElementById('trunk_id').value = data.id;
            document.getElementById('trunk_name').value = data.name;
            document.getElementById('trunk_type').value = data.trunk_type;
            document.getElementById('trunk_host').value = data.host;
            document.getElementById('trunk_port').value = data.port;
            document.getElementById('trunk_username').value = data.username || '';
            document.getElementById('trunk_secret').value = data.secret || '';
            document.getElementById('trunk_codecs').value = data.codecs;
            document.getElementById('trunk_max_channels').value = data.max_channels;
            document.getElementById('trunk_is_active').checked = data.is_active;
            new bootstrap.Modal(document.getElementById('trunkModal')).show();
        });
}

function deleteExtension(id) {
    if (confirm('Delete this extension?')) {
        window.location = '?page=call_center&action=delete_extension&id=' + id;
    }
}

function deleteTrunk(id) {
    if (confirm('Delete this trunk?')) {
        window.location = '?page=call_center&action=delete_trunk&id=' + id;
    }
}

function callExtension(extension, name) {
    document.getElementById('dialExtName').textContent = name;
    document.getElementById('dialExtNumber').textContent = 'Extension: ' + extension;
    
    // Check if user has an extension assigned
    if (!myExtension) {
        document.getElementById('dialStatus').innerHTML = `
            <div class="text-danger">
                <i class="bi bi-x-circle-fill" style="font-size: 3rem;"></i>
                <p class="mt-2">No Extension Assigned</p>
                <p class="small text-muted">Contact your administrator to assign an extension to your account</p>
            </div>
        `;
        var modal = new bootstrap.Modal(document.getElementById('quickDialModal'));
        modal.show();
        return;
    }
    
    document.getElementById('dialStatus').innerHTML = `
        <div class="spinner-border text-success" role="status">
            <span class="visually-hidden">Calling...</span>
        </div>
        <p class="mt-2 text-muted">Initiating call from Ext ${myExtension}...</p>
    `;
    
    var modal = new bootstrap.Modal(document.getElementById('quickDialModal'));
    modal.show();
    
    var formData = new FormData();
    formData.append('from_extension', myExtension);
    formData.append('to_extension', extension);
    
    fetch('?page=call_center&action=internal_call', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.getElementById('dialStatus').innerHTML = `
                <div class="text-success">
                    <i class="bi bi-check-circle-fill" style="font-size: 3rem;"></i>
                    <p class="mt-2">Call initiated successfully!</p>
                    <p class="small text-muted">Your phone (Ext ${myExtension}) will ring first, then connect to ${name}</p>
                </div>
            `;
        } else {
            document.getElementById('dialStatus').innerHTML = `
                <div class="text-danger">
                    <i class="bi bi-x-circle-fill" style="font-size: 3rem;"></i>
                    <p class="mt-2">Call failed</p>
                    <p class="small text-muted">${data.error || 'Unknown error'}</p>
                </div>
            `;
        }
    })
    .catch(err => {
        document.getElementById('dialStatus').innerHTML = `
            <div class="text-danger">
                <i class="bi bi-x-circle-fill" style="font-size: 3rem;"></i>
                <p class="mt-2">Connection error</p>
                <p class="small text-muted">${err.message}</p>
            </div>
        `;
    });
}

// Inbound Route Functions
function clearInboundForm() {
    document.getElementById('inbound_id').value = '';
    document.getElementById('inbound_name').value = '';
    document.getElementById('inbound_description').value = '';
    document.getElementById('inbound_did_pattern').value = '';
    document.getElementById('inbound_cid_pattern').value = '';
    document.getElementById('inbound_destination_type').value = 'extension';
    document.getElementById('inbound_destination_id').value = '';
    document.getElementById('inbound_priority').value = '0';
    document.getElementById('inbound_is_active').checked = true;
}

function editInboundRoute(data) {
    document.getElementById('inbound_id').value = data.id;
    document.getElementById('inbound_name').value = data.name;
    document.getElementById('inbound_description').value = data.description || '';
    document.getElementById('inbound_did_pattern').value = data.did_pattern || '';
    document.getElementById('inbound_cid_pattern').value = data.cid_pattern || '';
    document.getElementById('inbound_destination_type').value = data.destination_type;
    document.getElementById('inbound_destination_id').value = data.destination_id || '';
    document.getElementById('inbound_priority').value = data.priority;
    document.getElementById('inbound_is_active').checked = data.is_active;
    new bootstrap.Modal(document.getElementById('inboundRouteModal')).show();
}

function deleteInboundRoute(id) {
    if (confirm('Delete this inbound route?')) {
        window.location = '?page=call_center&action=delete_inbound_route&id=' + id;
    }
}

// Outbound Route Functions
function clearOutboundForm() {
    document.getElementById('outbound_id').value = '';
    document.getElementById('outbound_name').value = '';
    document.getElementById('outbound_description').value = '';
    document.getElementById('outbound_dial_pattern').value = '';
    document.getElementById('outbound_prepend').value = '';
    document.getElementById('outbound_prefix').value = '';
    document.getElementById('outbound_trunk_id').value = '';
    document.getElementById('outbound_caller_id').value = '';
    document.getElementById('outbound_priority').value = '0';
    document.getElementById('outbound_is_active').checked = true;
}

function editOutboundRoute(data) {
    document.getElementById('outbound_id').value = data.id;
    document.getElementById('outbound_name').value = data.name;
    document.getElementById('outbound_description').value = data.description || '';
    document.getElementById('outbound_dial_pattern').value = data.dial_pattern;
    document.getElementById('outbound_prepend').value = data.prepend || '';
    document.getElementById('outbound_prefix').value = data.prefix || '';
    document.getElementById('outbound_trunk_id').value = data.trunk_id || '';
    document.getElementById('outbound_caller_id').value = data.caller_id || '';
    document.getElementById('outbound_priority').value = data.priority;
    document.getElementById('outbound_is_active').checked = data.is_active;
    new bootstrap.Modal(document.getElementById('outboundRouteModal')).show();
}

function deleteOutboundRoute(id) {
    if (confirm('Delete this outbound route?')) {
        window.location = '?page=call_center&action=delete_outbound_route&id=' + id;
    }
}

// IVR Functions
function clearIvrForm() {
    document.getElementById('ivr_id').value = '';
    document.getElementById('ivr_name').value = '';
    document.getElementById('ivr_description').value = '';
    document.getElementById('ivr_announcement').value = '';
    document.getElementById('ivr_timeout').value = '10';
    document.getElementById('ivr_timeout_destination_type').value = 'hangup';
    document.getElementById('ivr_timeout_destination_id').value = '';
    document.getElementById('ivr_invalid_destination_type').value = 'repeat';
    document.getElementById('ivr_invalid_destination_id').value = '';
    document.getElementById('ivr_max_loops').value = '3';
    document.getElementById('ivr_is_active').checked = true;
}

function editIvr(id) {
    fetch('?page=call_center&action=get_ivr&id=' + id)
        .then(r => r.json())
        .then(data => {
            document.getElementById('ivr_id').value = data.id;
            document.getElementById('ivr_name').value = data.name;
            document.getElementById('ivr_description').value = data.description || '';
            document.getElementById('ivr_announcement').value = data.announcement || '';
            document.getElementById('ivr_timeout').value = data.timeout;
            document.getElementById('ivr_timeout_destination_type').value = data.timeout_destination_type;
            document.getElementById('ivr_timeout_destination_id').value = data.timeout_destination_id || '';
            document.getElementById('ivr_invalid_destination_type').value = data.invalid_destination_type;
            document.getElementById('ivr_invalid_destination_id').value = data.invalid_destination_id || '';
            document.getElementById('ivr_max_loops').value = data.max_loops;
            document.getElementById('ivr_is_active').checked = data.is_active;
            new bootstrap.Modal(document.getElementById('ivrModal')).show();
        });
}

function deleteIvr(id) {
    if (confirm('Delete this IVR menu? All associated options will also be deleted.')) {
        window.location = '?page=call_center&action=delete_ivr&id=' + id;
    }
}

function manageIvrOptions(id, name) {
    document.getElementById('ivrOptionsId').value = id;
    document.getElementById('ivrOptionsTitle').textContent = name;
    loadIvrOptions(id);
    new bootstrap.Modal(document.getElementById('ivrOptionsModal')).show();
}

function loadIvrOptions(ivrId) {
    fetch('?page=call_center&action=get_ivr_options&id=' + ivrId)
        .then(r => r.json())
        .then(options => {
            const tbody = document.querySelector('#ivrOptionsTable tbody');
            tbody.innerHTML = '';
            options.forEach(opt => {
                tbody.innerHTML += `
                    <tr>
                        <td><kbd>${opt.digit}</kbd></td>
                        <td><span class="badge bg-info">${opt.destination_type}</span> ${opt.destination_id || ''}</td>
                        <td>${opt.description || ''}</td>
                        <td>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteIvrOption(${opt.id}, ${ivrId})">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
            if (options.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No options defined</td></tr>';
            }
        });
}

document.getElementById('ivrOptionForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const ivrId = document.getElementById('ivrOptionsId').value;
    const formData = new FormData(this);
    formData.append('ivr_id', ivrId);
    
    fetch('?page=call_center&action=save_ivr_option', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            loadIvrOptions(ivrId);
            this.reset();
        } else {
            alert(data.error || 'Failed to save option');
        }
    });
});

function deleteIvrOption(optionId, ivrId) {
    if (confirm('Delete this option?')) {
        fetch('?page=call_center&action=delete_ivr_option&id=' + optionId)
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    loadIvrOptions(ivrId);
                }
            });
    }
}
</script>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
