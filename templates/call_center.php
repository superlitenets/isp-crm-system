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
    <?php endif; ?>
</div>

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

<script>
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
    document.getElementById('dialStatus').innerHTML = `
        <div class="spinner-border text-success" role="status">
            <span class="visually-hidden">Calling...</span>
        </div>
        <p class="mt-2 text-muted">Initiating call...</p>
    `;
    
    var modal = new bootstrap.Modal(document.getElementById('quickDialModal'));
    modal.show();
    
    var formData = new FormData();
    formData.append('phone', extension);
    
    fetch('?page=call_center&action=originate_call', {
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
                    <p class="small text-muted">Your phone will ring first, then connect to ${name}</p>
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
</script>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
