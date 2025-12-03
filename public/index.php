<?php

error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/init_db.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Customer.php';
require_once __DIR__ . '/../src/Ticket.php';
require_once __DIR__ . '/../src/SMS.php';
require_once __DIR__ . '/../src/SMSGateway.php';
require_once __DIR__ . '/../src/Employee.php';

initializeDatabase();

\App\Auth::init();

$page = $_GET['page'] ?? 'dashboard';
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if ($page === 'logout') {
    \App\Auth::logout();
    header('Location: ?page=login');
    exit;
}

if ($page === 'login') {
    $loginError = '';
    $csrfToken = \App\Auth::generateToken();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $submittedToken = $_POST['csrf_token'] ?? '';
        
        if (!\App\Auth::validateToken($submittedToken)) {
            $loginError = 'Invalid request. Please try again.';
        } else {
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            
            if (\App\Auth::login($email, $password)) {
                header('Location: ?page=dashboard');
                exit;
            } else {
                $loginError = 'Invalid email or password';
            }
        }
        $csrfToken = \App\Auth::generateToken();
    }
    include __DIR__ . '/../templates/login.php';
    exit;
}

\App\Auth::requireLogin();

$customer = new \App\Customer();
$ticket = new \App\Ticket();
$sms = new \App\SMS();
$smsGateway = new \App\SMSGateway();
$employee = new \App\Employee();
$currentUser = \App\Auth::user();

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!\App\Auth::validateToken($csrfToken)) {
        $message = 'Invalid request. Please try again.';
        $messageType = 'danger';
    } else {
        $postAction = $_POST['action'] ?? '';
        
        switch ($postAction) {
            case 'create_customer':
                $name = trim($_POST['name'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $address = trim($_POST['address'] ?? '');
                $servicePlan = trim($_POST['service_plan'] ?? '');
                
                if (empty($name) || empty($phone) || empty($address) || empty($servicePlan)) {
                    $message = 'Please fill in all required fields.';
                    $messageType = 'danger';
                } else {
                    try {
                        $customerId = $customer->create($_POST);
                        $message = 'Customer created successfully!';
                        $messageType = 'success';
                        \App\Auth::regenerateToken();
                    } catch (Exception $e) {
                        $message = 'Error creating customer: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'update_customer':
                $name = trim($_POST['name'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $address = trim($_POST['address'] ?? '');
                $servicePlan = trim($_POST['service_plan'] ?? '');
                
                if (empty($name) || empty($phone) || empty($address) || empty($servicePlan)) {
                    $message = 'Please fill in all required fields.';
                    $messageType = 'danger';
                } else {
                    try {
                        $customer->update((int)$_POST['id'], $_POST);
                        $message = 'Customer updated successfully!';
                        $messageType = 'success';
                        \App\Auth::regenerateToken();
                    } catch (Exception $e) {
                        $message = 'Error updating customer: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'delete_customer':
                if (!\App\Auth::isAdmin()) {
                    $message = 'Only administrators can delete customers.';
                    $messageType = 'danger';
                } else {
                    try {
                        $customer->delete((int)$_POST['id']);
                        $message = 'Customer deleted successfully!';
                        $messageType = 'success';
                        \App\Auth::regenerateToken();
                        header('Location: ?page=customers');
                        exit;
                    } catch (Exception $e) {
                        $message = 'Error deleting customer: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'create_ticket':
                $customerId = (int)($_POST['customer_id'] ?? 0);
                $subject = trim($_POST['subject'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $category = trim($_POST['category'] ?? '');
                
                if (empty($customerId) || empty($subject) || empty($description) || empty($category)) {
                    $message = 'Please fill in all required fields.';
                    $messageType = 'danger';
                } elseif (!$customer->find($customerId)) {
                    $message = 'Selected customer not found.';
                    $messageType = 'danger';
                } else {
                    try {
                        $ticketId = $ticket->create($_POST);
                        $message = 'Ticket created successfully! SMS notifications sent.';
                        $messageType = 'success';
                        \App\Auth::regenerateToken();
                    } catch (Exception $e) {
                        $message = 'Error creating ticket: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'update_ticket':
                $subject = trim($_POST['subject'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $category = trim($_POST['category'] ?? '');
                $status = trim($_POST['status'] ?? '');
                
                if (empty($subject) || empty($description) || empty($category) || empty($status)) {
                    $message = 'Please fill in all required fields.';
                    $messageType = 'danger';
                } else {
                    try {
                        $ticket->update((int)$_POST['id'], $_POST);
                        $message = 'Ticket updated successfully! SMS notification sent to customer.';
                        $messageType = 'success';
                        \App\Auth::regenerateToken();
                    } catch (Exception $e) {
                        $message = 'Error updating ticket: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'add_comment':
                $comment = trim($_POST['comment'] ?? '');
                if (empty($comment)) {
                    $message = 'Comment cannot be empty.';
                    $messageType = 'danger';
                } else {
                    try {
                        $ticket->addComment(
                            (int)$_POST['ticket_id'], 
                            $currentUser['id'], 
                            $comment, 
                            isset($_POST['is_internal'])
                        );
                        $message = 'Comment added successfully!';
                        $messageType = 'success';
                        \App\Auth::regenerateToken();
                    } catch (Exception $e) {
                        $message = 'Error adding comment: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'create_employee':
                $name = trim($_POST['name'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $position = trim($_POST['position'] ?? '');
                
                if (empty($name) || empty($phone) || empty($position)) {
                    $message = 'Please fill in all required fields.';
                    $messageType = 'danger';
                } else {
                    try {
                        $employee->create($_POST);
                        if (!empty($_POST['user_id'])) {
                            $empId = (int)Database::getConnection()->lastInsertId();
                            $employee->linkToUser($empId, (int)$_POST['user_id']);
                        }
                        $message = 'Employee added successfully!';
                        $messageType = 'success';
                        \App\Auth::regenerateToken();
                    } catch (Exception $e) {
                        $message = 'Error adding employee: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'update_employee':
                $name = trim($_POST['name'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $position = trim($_POST['position'] ?? '');
                
                if (empty($name) || empty($phone) || empty($position)) {
                    $message = 'Please fill in all required fields.';
                    $messageType = 'danger';
                } else {
                    try {
                        $employee->update((int)$_POST['id'], $_POST);
                        if (isset($_POST['user_id'])) {
                            $employee->linkToUser((int)$_POST['id'], (int)$_POST['user_id'] ?: null);
                        }
                        $message = 'Employee updated successfully!';
                        $messageType = 'success';
                        \App\Auth::regenerateToken();
                    } catch (Exception $e) {
                        $message = 'Error updating employee: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'delete_employee':
                if (!\App\Auth::isAdmin()) {
                    $message = 'Only administrators can delete employees.';
                    $messageType = 'danger';
                } else {
                    try {
                        $employee->delete((int)$_POST['id']);
                        $message = 'Employee deleted successfully!';
                        $messageType = 'success';
                        \App\Auth::regenerateToken();
                    } catch (Exception $e) {
                        $message = 'Error deleting employee: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'create_department':
                $name = trim($_POST['name'] ?? '');
                if (empty($name)) {
                    $message = 'Department name is required.';
                    $messageType = 'danger';
                } else {
                    try {
                        $employee->createDepartment($_POST);
                        $message = 'Department created successfully!';
                        $messageType = 'success';
                        \App\Auth::regenerateToken();
                    } catch (Exception $e) {
                        $message = 'Error creating department: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'update_department':
                $name = trim($_POST['name'] ?? '');
                if (empty($name)) {
                    $message = 'Department name is required.';
                    $messageType = 'danger';
                } else {
                    try {
                        $employee->updateDepartment((int)$_POST['id'], $_POST);
                        $message = 'Department updated successfully!';
                        $messageType = 'success';
                        \App\Auth::regenerateToken();
                    } catch (Exception $e) {
                        $message = 'Error updating department: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'delete_department':
                if (!\App\Auth::isAdmin()) {
                    $message = 'Only administrators can delete departments.';
                    $messageType = 'danger';
                } else {
                    try {
                        $employee->deleteDepartment((int)$_POST['id']);
                        $message = 'Department deleted successfully!';
                        $messageType = 'success';
                        \App\Auth::regenerateToken();
                    } catch (Exception $e) {
                        $message = 'Error deleting department: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;

            case 'record_attendance':
                try {
                    $employee->recordAttendance($_POST);
                    $message = 'Attendance recorded successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error recording attendance: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'update_attendance':
                try {
                    $employee->updateAttendance((int)$_POST['id'], $_POST);
                    $message = 'Attendance updated successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error updating attendance: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'delete_attendance':
                if (!\App\Auth::isAdmin()) {
                    $message = 'Only administrators can delete attendance records.';
                    $messageType = 'danger';
                } else {
                    try {
                        $employee->deleteAttendance((int)$_POST['id']);
                        $message = 'Attendance record deleted successfully!';
                        $messageType = 'success';
                        \App\Auth::regenerateToken();
                    } catch (Exception $e) {
                        $message = 'Error deleting attendance: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;

            case 'create_payroll':
                try {
                    $employee->createPayroll($_POST);
                    $message = 'Payroll record created successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error creating payroll: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'update_payroll':
                try {
                    $employee->updatePayroll((int)$_POST['id'], $_POST);
                    $message = 'Payroll record updated successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error updating payroll: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'delete_payroll':
                if (!\App\Auth::isAdmin()) {
                    $message = 'Only administrators can delete payroll records.';
                    $messageType = 'danger';
                } else {
                    try {
                        $employee->deletePayroll((int)$_POST['id']);
                        $message = 'Payroll record deleted successfully!';
                        $messageType = 'success';
                        \App\Auth::regenerateToken();
                    } catch (Exception $e) {
                        $message = 'Error deleting payroll: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;

            case 'create_performance':
                try {
                    $employee->createPerformanceReview($_POST);
                    $message = 'Performance review created successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error creating performance review: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'update_performance':
                try {
                    $employee->updatePerformanceReview((int)$_POST['id'], $_POST);
                    $message = 'Performance review updated successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error updating performance review: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'delete_performance':
                if (!\App\Auth::isAdmin()) {
                    $message = 'Only administrators can delete performance reviews.';
                    $messageType = 'danger';
                } else {
                    try {
                        $employee->deletePerformanceReview((int)$_POST['id']);
                        $message = 'Performance review deleted successfully!';
                        $messageType = 'success';
                        \App\Auth::regenerateToken();
                    } catch (Exception $e) {
                        $message = 'Error deleting performance review: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;
        }
    }
}

$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$priorityFilter = $_GET['priority'] ?? '';

$stats = $ticket->getStats();
$users = $ticket->getAllUsers();
$servicePlans = $customer->getServicePlans();
$connectionStatuses = $customer->getConnectionStatuses();
$categories = $ticket->getCategories();
$priorities = $ticket->getPriorities();
$ticketStatuses = $ticket->getStatuses();
$csrfToken = \App\Auth::generateToken();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ISP CRM & Ticketing System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #0d6efd;
            --sidebar-width: 250px;
        }
        body {
            background-color: #f8f9fa;
        }
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: linear-gradient(135deg, #1a1c2c 0%, #2d3250 100%);
            padding-top: 1rem;
            z-index: 1000;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 0.75rem 1.5rem;
            border-radius: 0;
            transition: all 0.3s;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: #fff;
            border-left: 3px solid var(--primary-color);
        }
        .sidebar .nav-link i {
            margin-right: 0.75rem;
            width: 20px;
        }
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
        }
        .brand {
            color: #fff;
            font-size: 1.5rem;
            font-weight: bold;
            padding: 0 1.5rem 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 1rem;
        }
        .stat-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-card .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        .badge-priority-critical { background-color: #dc3545; }
        .badge-priority-high { background-color: #fd7e14; }
        .badge-priority-medium { background-color: #ffc107; color: #000; }
        .badge-priority-low { background-color: #198754; }
        .badge-status-open { background-color: #0d6efd; }
        .badge-status-in_progress { background-color: #6f42c1; }
        .badge-status-pending { background-color: #ffc107; color: #000; }
        .badge-status-resolved { background-color: #198754; }
        .badge-status-closed { background-color: #6c757d; }
        .table-hover tbody tr:hover {
            background-color: rgba(13, 110, 253, 0.05);
        }
        .sms-status {
            font-size: 0.75rem;
        }
        .sms-enabled { color: #198754; }
        .sms-disabled { color: #dc3545; }
        .user-info {
            color: rgba(255,255,255,0.8);
            padding: 0.5rem 1.5rem;
            font-size: 0.875rem;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <nav class="sidebar d-flex flex-column">
        <div class="brand">
            <i class="bi bi-router"></i> ISP CRM
        </div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?= $page === 'dashboard' ? 'active' : '' ?>" href="?page=dashboard">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $page === 'customers' ? 'active' : '' ?>" href="?page=customers">
                    <i class="bi bi-people"></i> Customers
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $page === 'tickets' ? 'active' : '' ?>" href="?page=tickets">
                    <i class="bi bi-ticket"></i> Tickets
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $page === 'hr' ? 'active' : '' ?>" href="?page=hr">
                    <i class="bi bi-people-fill"></i> HR
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $page === 'sms_settings' ? 'active' : '' ?>" href="?page=sms_settings">
                    <i class="bi bi-chat-dots"></i> SMS Settings
                </a>
            </li>
        </ul>
        <div class="mt-auto">
            <div class="sms-status <?= $sms->isEnabled() ? 'sms-enabled' : 'sms-disabled' ?> px-3 pb-2">
                <i class="bi bi-chat-dots"></i>
                SMS: <?= $sms->isEnabled() ? 'Enabled' : 'Not Configured' ?>
            </div>
            <div class="user-info">
                <i class="bi bi-person-circle"></i> <?= htmlspecialchars($currentUser['name']) ?>
                <br>
                <small class="text-muted"><?= ucfirst($currentUser['role']) ?></small>
                <br>
                <a href="?page=logout" class="text-danger small"><i class="bi bi-box-arrow-right"></i> Logout</a>
            </div>
        </div>
    </nav>

    <main class="main-content">
        <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php
        switch ($page) {
            case 'dashboard':
                include __DIR__ . '/../templates/dashboard.php';
                break;
            case 'customers':
                include __DIR__ . '/../templates/customers.php';
                break;
            case 'tickets':
                include __DIR__ . '/../templates/tickets.php';
                break;
            case 'hr':
                include __DIR__ . '/../templates/hr.php';
                break;
            case 'sms_settings':
                include __DIR__ . '/../templates/sms_settings.php';
                break;
            default:
                include __DIR__ . '/../templates/dashboard.php';
        }
        ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelectorAll('.delete-confirm').forEach(btn => {
            btn.addEventListener('click', (e) => {
                if (!confirm('Are you sure you want to delete this item?')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>
