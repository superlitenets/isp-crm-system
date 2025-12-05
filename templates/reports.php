<?php
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$selectedUser = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$selectedTab = $_GET['tab'] ?? 'overview';

$ticketStats = ['total_tickets' => 0, 'open_tickets' => 0, 'in_progress_tickets' => 0, 'resolved_tickets' => 0, 'sla_breached' => 0, 'avg_resolution_hours' => 0];
$orderStats = ['total_orders' => 0, 'new_orders' => 0, 'confirmed_orders' => 0, 'completed_orders' => 0, 'paid_orders' => 0, 'total_revenue' => 0];
$complaintStats = ['total_complaints' => 0, 'pending_complaints' => 0, 'approved_complaints' => 0, 'rejected_complaints' => 0, 'converted_complaints' => 0];
$userSummary = [];
$ticketsByUser = [];
$ordersBySalesperson = [];
$complaintsByReviewer = [];
$allUsers = [];
$allEmployees = [];
$recentActivities = [];
$allTickets = [];
$allOrders = [];
$allComplaints = [];

try {
    $reports = new \App\Reports();
    $activityLog = new \App\ActivityLog();

    $filters = [
        'date_from' => $dateFrom,
        'date_to' => $dateTo
    ];

    $ticketStats = $reports->getTicketStats($filters) ?: $ticketStats;
    $orderStats = $reports->getOrderStats($filters) ?: $orderStats;
    $complaintStats = $reports->getComplaintStats($filters) ?: $complaintStats;
    $userSummary = $reports->getUserSummary($filters) ?: [];
    $ticketsByUser = $reports->getTicketsByUser($filters) ?: [];
    $ordersBySalesperson = $reports->getOrdersBySalesperson($filters) ?: [];
    $complaintsByReviewer = $reports->getComplaintsByReviewer($filters) ?: [];
    $allUsers = $reports->getAllUsers() ?: [];
    $allEmployees = $reports->getAllEmployees() ?: [];
    
    $allTickets = $reports->getAllTickets($filters) ?: [];
    $allOrders = $reports->getAllOrders($filters) ?: [];
    $allComplaints = $reports->getAllComplaints($filters) ?: [];

    $activityFilters = [
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'limit' => 50
    ];
    if ($selectedUser) {
        $activityFilters['user_id'] = $selectedUser;
    }
    $recentActivities = $activityLog->getActivities($activityFilters) ?: [];
} catch (Exception $e) {
    error_log("Reports page error: " . $e->getMessage());
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-graph-up"></i> Reports & Activity Logs</h2>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <input type="hidden" name="page" value="reports">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($selectedTab) ?>">
            <div class="col-md-3">
                <label class="form-label">Date From</label>
                <input type="date" class="form-control" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Date To</label>
                <input type="date" class="form-control" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Filter by User/Employee</label>
                <select class="form-select" name="user_id">
                    <option value="">All Users & Employees</option>
                    <?php if (!empty($allUsers)): ?>
                    <optgroup label="System Users">
                        <?php foreach ($allUsers as $user): ?>
                            <option value="<?= $user['id'] ?>" <?= $selectedUser == $user['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($user['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                    <?php endif; ?>
                    <?php if (!empty($allEmployees)): ?>
                    <optgroup label="Employees">
                        <?php foreach ($allEmployees as $emp): ?>
                            <?php if ($emp['user_id']): ?>
                            <option value="<?= $emp['user_id'] ?>" <?= $selectedUser == $emp['user_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($emp['name']) ?> (<?= htmlspecialchars($emp['position'] ?? 'Staff') ?>)
                            </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </optgroup>
                    <?php endif; ?>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-funnel"></i> Apply Filters
                </button>
            </div>
        </form>
    </div>
</div>

<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link <?= $selectedTab === 'overview' ? 'active' : '' ?>" 
           href="?page=reports&tab=overview&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?><?= $selectedUser ? '&user_id='.$selectedUser : '' ?>">
            <i class="bi bi-speedometer2"></i> Overview
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $selectedTab === 'tickets' ? 'active' : '' ?>" 
           href="?page=reports&tab=tickets&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?><?= $selectedUser ? '&user_id='.$selectedUser : '' ?>">
            <i class="bi bi-ticket"></i> Tickets
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $selectedTab === 'orders' ? 'active' : '' ?>" 
           href="?page=reports&tab=orders&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?><?= $selectedUser ? '&user_id='.$selectedUser : '' ?>">
            <i class="bi bi-cart"></i> Orders
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $selectedTab === 'complaints' ? 'active' : '' ?>" 
           href="?page=reports&tab=complaints&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?><?= $selectedUser ? '&user_id='.$selectedUser : '' ?>">
            <i class="bi bi-exclamation-triangle"></i> Complaints
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $selectedTab === 'activity' ? 'active' : '' ?>" 
           href="?page=reports&tab=activity&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?><?= $selectedUser ? '&user_id='.$selectedUser : '' ?>">
            <i class="bi bi-activity"></i> Activity Log
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $selectedTab === 'users' ? 'active' : '' ?>" 
           href="?page=reports&tab=users&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?><?= $selectedUser ? '&user_id='.$selectedUser : '' ?>">
            <i class="bi bi-people"></i> User Performance
        </a>
    </li>
</ul>

<?php if ($selectedTab === 'overview'): ?>
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-ticket"></i> Ticket Summary
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span>Total Tickets:</span>
                    <strong><?= number_format($ticketStats['total_tickets'] ?? 0) ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Open:</span>
                    <span class="badge bg-warning"><?= $ticketStats['open_tickets'] ?? 0 ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>In Progress:</span>
                    <span class="badge bg-info"><?= $ticketStats['in_progress_tickets'] ?? 0 ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Resolved:</span>
                    <span class="badge bg-success"><?= $ticketStats['resolved_tickets'] ?? 0 ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>SLA Breached:</span>
                    <span class="badge bg-danger"><?= $ticketStats['sla_breached'] ?? 0 ?></span>
                </div>
                <hr>
                <div class="d-flex justify-content-between">
                    <span>Avg Resolution:</span>
                    <strong><?= round($ticketStats['avg_resolution_hours'] ?? 0, 1) ?> hrs</strong>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header bg-success text-white">
                <i class="bi bi-cart"></i> Order Summary
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span>Total Orders:</span>
                    <strong><?= number_format($orderStats['total_orders'] ?? 0) ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>New:</span>
                    <span class="badge bg-primary"><?= $orderStats['new_orders'] ?? 0 ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Confirmed:</span>
                    <span class="badge bg-info"><?= $orderStats['confirmed_orders'] ?? 0 ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Completed:</span>
                    <span class="badge bg-success"><?= $orderStats['completed_orders'] ?? 0 ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Paid:</span>
                    <span class="badge bg-success"><?= $orderStats['paid_orders'] ?? 0 ?></span>
                </div>
                <hr>
                <div class="d-flex justify-content-between">
                    <span>Total Revenue:</span>
                    <strong>KES <?= number_format($orderStats['total_revenue'] ?? 0) ?></strong>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header bg-warning text-dark">
                <i class="bi bi-exclamation-triangle"></i> Complaint Summary
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span>Total Complaints:</span>
                    <strong><?= number_format($complaintStats['total_complaints'] ?? 0) ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Pending:</span>
                    <span class="badge bg-warning"><?= $complaintStats['pending_complaints'] ?? 0 ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Approved:</span>
                    <span class="badge bg-success"><?= $complaintStats['approved_complaints'] ?? 0 ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Rejected:</span>
                    <span class="badge bg-danger"><?= $complaintStats['rejected_complaints'] ?? 0 ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Converted to Ticket:</span>
                    <span class="badge bg-info"><?= $complaintStats['converted_complaints'] ?? 0 ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php elseif ($selectedTab === 'tickets'): ?>
<?php if (!empty($ticketsByUser)): ?>
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-people"></i> Tickets by Assigned User</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>User</th>
                        <th class="text-center">Assigned</th>
                        <th class="text-center">Resolved</th>
                        <th class="text-center">In Progress</th>
                        <th class="text-center">SLA Breached</th>
                        <th class="text-center">Avg Resolution</th>
                        <th class="text-center">Resolution Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ticketsByUser as $row): ?>
                    <tr>
                        <td>
                            <a href="?page=reports&tab=activity&user_id=<?= $row['user_id'] ?>&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>">
                                <?= htmlspecialchars($row['user_name']) ?>
                            </a>
                        </td>
                        <td class="text-center"><?= $row['assigned_count'] ?></td>
                        <td class="text-center"><span class="badge bg-success"><?= $row['resolved_count'] ?></span></td>
                        <td class="text-center"><span class="badge bg-info"><?= $row['in_progress_count'] ?></span></td>
                        <td class="text-center">
                            <?php if ($row['sla_breached_count'] > 0): ?>
                                <span class="badge bg-danger"><?= $row['sla_breached_count'] ?></span>
                            <?php else: ?>
                                <span class="badge bg-success">0</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center"><?= $row['avg_resolution_hours'] ?? '-' ?> hrs</td>
                        <td class="text-center">
                            <?php 
                            $rate = $row['assigned_count'] > 0 ? round(($row['resolved_count'] / $row['assigned_count']) * 100) : 0;
                            $badgeClass = $rate >= 80 ? 'bg-success' : ($rate >= 50 ? 'bg-warning' : 'bg-danger');
                            ?>
                            <span class="badge <?= $badgeClass ?>"><?= $rate ?>%</span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-ticket"></i> All Tickets (<?= count($allTickets) ?>)</h5>
    </div>
    <div class="card-body">
        <?php if (empty($allTickets)): ?>
            <p class="text-muted mb-0">No tickets found for this period.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Ticket #</th>
                        <th>Subject</th>
                        <th>Customer</th>
                        <th>Assigned To</th>
                        <th class="text-center">Priority</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">SLA</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allTickets as $ticket): ?>
                    <tr>
                        <td>
                            <a href="?page=tickets&action=view&id=<?= $ticket['id'] ?>">
                                <?= htmlspecialchars($ticket['ticket_number']) ?>
                            </a>
                        </td>
                        <td><?= htmlspecialchars(substr($ticket['subject'], 0, 40)) ?><?= strlen($ticket['subject']) > 40 ? '...' : '' ?></td>
                        <td><?= htmlspecialchars($ticket['customer_name'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($ticket['assigned_to_name'] ?? '<span class="text-muted">Unassigned</span>') ?></td>
                        <td class="text-center">
                            <span class="badge badge-priority-<?= $ticket['priority'] ?>"><?= ucfirst($ticket['priority']) ?></span>
                        </td>
                        <td class="text-center">
                            <span class="badge badge-status-<?= $ticket['status'] ?>"><?= ucfirst(str_replace('_', ' ', $ticket['status'])) ?></span>
                        </td>
                        <td class="text-center">
                            <?php if ($ticket['sla_response_breached'] || $ticket['sla_resolution_breached']): ?>
                                <span class="badge bg-danger">Breached</span>
                            <?php else: ?>
                                <span class="badge bg-success">OK</span>
                            <?php endif; ?>
                        </td>
                        <td><small><?= date('M j, Y', strtotime($ticket['created_at'])) ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php elseif ($selectedTab === 'orders'): ?>
<?php if (!empty($ordersBySalesperson)): ?>
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-people"></i> Orders by Salesperson</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Salesperson</th>
                        <th class="text-center">Total Orders</th>
                        <th class="text-center">Completed</th>
                        <th class="text-end">Total Sales</th>
                        <th class="text-end">Paid Amount</th>
                        <th class="text-center">Conversion Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ordersBySalesperson as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['salesperson_name']) ?></td>
                        <td class="text-center"><?= $row['order_count'] ?></td>
                        <td class="text-center"><span class="badge bg-success"><?= $row['completed_count'] ?></span></td>
                        <td class="text-end">KES <?= number_format($row['total_sales']) ?></td>
                        <td class="text-end">KES <?= number_format($row['paid_amount']) ?></td>
                        <td class="text-center">
                            <?php 
                            $rate = $row['order_count'] > 0 ? round(($row['completed_count'] / $row['order_count']) * 100) : 0;
                            $badgeClass = $rate >= 80 ? 'bg-success' : ($rate >= 50 ? 'bg-warning' : 'bg-danger');
                            ?>
                            <span class="badge <?= $badgeClass ?>"><?= $rate ?>%</span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-cart"></i> All Orders (<?= count($allOrders) ?>)</h5>
    </div>
    <div class="card-body">
        <?php if (empty($allOrders)): ?>
            <p class="text-muted mb-0">No orders found for this period.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Phone</th>
                        <th>Package</th>
                        <th>Salesperson</th>
                        <th class="text-end">Amount</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Payment</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allOrders as $order): ?>
                    <tr>
                        <td>
                            <a href="?page=orders&action=view&id=<?= $order['id'] ?>">
                                <?= htmlspecialchars($order['order_number']) ?>
                            </a>
                        </td>
                        <td><?= htmlspecialchars($order['customer_name']) ?></td>
                        <td><?= htmlspecialchars($order['customer_phone']) ?></td>
                        <td><?= htmlspecialchars($order['package_name'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($order['salesperson_name'] ?? '-') ?></td>
                        <td class="text-end">KES <?= number_format($order['amount'] ?? 0) ?></td>
                        <td class="text-center">
                            <?php
                            $statusClass = match($order['order_status']) {
                                'new' => 'bg-primary',
                                'confirmed' => 'bg-info',
                                'completed' => 'bg-success',
                                'cancelled' => 'bg-danger',
                                default => 'bg-secondary'
                            };
                            ?>
                            <span class="badge <?= $statusClass ?>"><?= ucfirst($order['order_status']) ?></span>
                        </td>
                        <td class="text-center">
                            <?php
                            $paymentClass = match($order['payment_status']) {
                                'paid' => 'bg-success',
                                'partial' => 'bg-warning',
                                'pending' => 'bg-secondary',
                                default => 'bg-secondary'
                            };
                            ?>
                            <span class="badge <?= $paymentClass ?>"><?= ucfirst($order['payment_status']) ?></span>
                        </td>
                        <td><small><?= date('M j, Y', strtotime($order['created_at'])) ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php elseif ($selectedTab === 'complaints'): ?>
<?php if (!empty($complaintsByReviewer)): ?>
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-people"></i> Complaints by Reviewer</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Reviewer</th>
                        <th class="text-center">Total Reviewed</th>
                        <th class="text-center">Approved</th>
                        <th class="text-center">Rejected</th>
                        <th class="text-center">Converted to Ticket</th>
                        <th class="text-center">Approval Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($complaintsByReviewer as $row): ?>
                    <tr>
                        <td>
                            <a href="?page=reports&tab=activity&user_id=<?= $row['user_id'] ?>&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>">
                                <?= htmlspecialchars($row['user_name']) ?>
                            </a>
                        </td>
                        <td class="text-center"><?= $row['reviewed_count'] ?></td>
                        <td class="text-center"><span class="badge bg-success"><?= $row['approved_count'] ?></span></td>
                        <td class="text-center"><span class="badge bg-danger"><?= $row['rejected_count'] ?></span></td>
                        <td class="text-center"><span class="badge bg-info"><?= $row['converted_count'] ?></span></td>
                        <td class="text-center">
                            <?php 
                            $rate = $row['reviewed_count'] > 0 ? round(($row['approved_count'] / $row['reviewed_count']) * 100) : 0;
                            ?>
                            <span class="badge bg-primary"><?= $rate ?>%</span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-exclamation-triangle"></i> All Complaints (<?= count($allComplaints) ?>)</h5>
    </div>
    <div class="card-body">
        <?php if (empty($allComplaints)): ?>
            <p class="text-muted mb-0">No complaints found for this period.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Complaint #</th>
                        <th>Customer</th>
                        <th>Phone</th>
                        <th>Category</th>
                        <th class="text-center">Status</th>
                        <th>Reviewed By</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allComplaints as $complaint): ?>
                    <tr>
                        <td>
                            <a href="?page=complaints&action=view&id=<?= $complaint['id'] ?>">
                                <?= htmlspecialchars($complaint['complaint_number']) ?>
                            </a>
                        </td>
                        <td><?= htmlspecialchars($complaint['customer_name']) ?></td>
                        <td><?= htmlspecialchars($complaint['customer_phone']) ?></td>
                        <td><?= htmlspecialchars(ucfirst($complaint['category'] ?? '-')) ?></td>
                        <td class="text-center">
                            <?php
                            $statusClass = match($complaint['status']) {
                                'pending' => 'bg-warning',
                                'approved' => 'bg-success',
                                'rejected' => 'bg-danger',
                                'converted' => 'bg-info',
                                default => 'bg-secondary'
                            };
                            ?>
                            <span class="badge <?= $statusClass ?>"><?= ucfirst($complaint['status']) ?></span>
                        </td>
                        <td><?= htmlspecialchars($complaint['reviewed_by_name'] ?? '-') ?></td>
                        <td><small><?= date('M j, Y', strtotime($complaint['created_at'])) ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php elseif ($selectedTab === 'activity'): ?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-activity"></i> Activity Log</h5>
        <?php if ($selectedUser): ?>
            <?php 
            $selectedUserName = '';
            foreach ($allUsers as $u) {
                if ($u['id'] == $selectedUser) {
                    $selectedUserName = $u['name'];
                    break;
                }
            }
            ?>
            <span class="badge bg-info">Filtered: <?= htmlspecialchars($selectedUserName) ?></span>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if (empty($recentActivities)): ?>
            <p class="text-muted mb-0">No activities found for this period.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Date/Time</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Entity</th>
                        <th>Reference</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentActivities as $activity): ?>
                    <tr>
                        <td class="text-nowrap">
                            <small><?= date('M j, Y g:i A', strtotime($activity['created_at'])) ?></small>
                        </td>
                        <td>
                            <a href="?page=reports&tab=activity&user_id=<?= $activity['user_id'] ?>&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>">
                                <?= htmlspecialchars($activity['user_name'] ?? 'System') ?>
                            </a>
                        </td>
                        <td>
                            <?php
                            $actionBadge = match($activity['action_type']) {
                                'create' => 'bg-success',
                                'update' => 'bg-info',
                                'delete' => 'bg-danger',
                                'assign' => 'bg-primary',
                                'resolve' => 'bg-success',
                                'approve' => 'bg-success',
                                'reject' => 'bg-danger',
                                'convert' => 'bg-info',
                                default => 'bg-secondary'
                            };
                            ?>
                            <span class="badge <?= $actionBadge ?>"><?= ucfirst($activity['action_type']) ?></span>
                        </td>
                        <td>
                            <?php
                            $entityIcon = match($activity['entity_type']) {
                                'ticket' => 'bi-ticket',
                                'order' => 'bi-cart',
                                'complaint' => 'bi-exclamation-triangle',
                                'customer' => 'bi-person',
                                'employee' => 'bi-person-badge',
                                default => 'bi-file'
                            };
                            ?>
                            <i class="bi <?= $entityIcon ?>"></i> <?= ucfirst($activity['entity_type']) ?>
                        </td>
                        <td>
                            <?php if ($activity['entity_reference']): ?>
                                <code><?= htmlspecialchars($activity['entity_reference']) ?></code>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <small class="text-muted"><?= htmlspecialchars($activity['details'] ?? '-') ?></small>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php elseif ($selectedTab === 'users'): ?>
<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-people"></i> User Performance Summary</h5>
    </div>
    <div class="card-body">
        <?php if (empty($userSummary)): ?>
            <p class="text-muted mb-0">No user data found.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th class="text-center">Tickets Assigned</th>
                        <th class="text-center">Tickets Resolved</th>
                        <th class="text-center">Complaints Reviewed</th>
                        <th class="text-center">Comments Added</th>
                        <th class="text-center">Total Activities</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($userSummary as $user): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($user['name']) ?></strong>
                            <br><small class="text-muted"><?= htmlspecialchars($user['email']) ?></small>
                        </td>
                        <td>
                            <span class="badge bg-secondary"><?= htmlspecialchars($user['role_name'] ?? 'No Role') ?></span>
                        </td>
                        <td class="text-center"><?= $user['tickets_assigned'] ?></td>
                        <td class="text-center">
                            <?php if ($user['tickets_resolved'] > 0): ?>
                                <span class="badge bg-success"><?= $user['tickets_resolved'] ?></span>
                            <?php else: ?>
                                0
                            <?php endif; ?>
                        </td>
                        <td class="text-center"><?= $user['complaints_reviewed'] ?></td>
                        <td class="text-center"><?= $user['comments_added'] ?></td>
                        <td class="text-center">
                            <strong><?= $user['total_activities'] ?></strong>
                        </td>
                        <td class="text-center">
                            <a href="?page=reports&tab=activity&user_id=<?= $user['id'] ?>&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>" 
                               class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i> View Activity
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
<?php endif; ?>
