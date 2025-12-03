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
require_once __DIR__ . '/../src/Settings.php';
require_once __DIR__ . '/../src/WhatsApp.php';
require_once __DIR__ . '/../src/TemplateEngine.php';
require_once __DIR__ . '/../src/BiometricDevice.php';
require_once __DIR__ . '/../src/ZKTecoDevice.php';
require_once __DIR__ . '/../src/HikvisionDevice.php';
require_once __DIR__ . '/../src/BiometricSyncService.php';
require_once __DIR__ . '/../src/LateDeductionCalculator.php';

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

if ($page === 'api' && $action === 'late_deductions') {
    header('Content-Type: application/json');
    
    if (!\App\Auth::isLoggedIn()) {
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    $employeeId = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;
    $month = $_GET['month'] ?? date('Y-m');
    
    if (!$employeeId) {
        echo json_encode(['error' => 'Employee ID required']);
        exit;
    }
    
    try {
        $apiDb = Database::getConnection();
        $lateCalculator = new \App\LateDeductionCalculator($apiDb);
        $deductions = $lateCalculator->calculateMonthlyDeductions($employeeId, $month);
        echo json_encode($deductions);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

$requestUri = strtok($_SERVER['REQUEST_URI'], '?');
$isHomepage = ($requestUri === '/' || $requestUri === '/index.php') && !isset($_GET['page']);
if ($page === 'landing' || $isHomepage) {
    $settingsObj = new \App\Settings();
    $packages = $settingsObj->getActivePackagesForLanding();
    $company = $settingsObj->getCompanyInfo();
    $landingSettings = $settingsObj->getLandingPageSettings();
    include __DIR__ . '/../templates/landing.php';
    exit;
}

if ($page === 'mpesa_callback') {
    header('Content-Type: application/json');
    
    $callbackType = $_GET['type'] ?? '';
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    
    file_put_contents(__DIR__ . '/../logs/mpesa_' . date('Y-m-d') . '.log', 
        date('Y-m-d H:i:s') . " [{$callbackType}]: " . $rawInput . "\n", 
        FILE_APPEND);
    
    try {
        $mpesa = new \App\Mpesa();
        
        switch ($callbackType) {
            case 'stkpush':
                if ($data) {
                    $mpesa->handleStkCallback($data);
                }
                echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Success']);
                break;
                
            case 'validation':
                $response = $mpesa->handleC2BValidation($data ?? []);
                echo json_encode($response);
                break;
                
            case 'confirmation':
                if ($data) {
                    $mpesa->handleC2BConfirmation($data);
                }
                echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Success']);
                break;
                
            default:
                echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Unknown callback type']);
        }
    } catch (Exception $e) {
        error_log("M-Pesa callback error: " . $e->getMessage());
        echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Error processing callback']);
    }
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
$smsGateway = null;
$employee = new \App\Employee();
$settings = new \App\Settings();
$currentUser = \App\Auth::user();

function getSMSGateway() {
    static $gateway = null;
    if ($gateway === null) {
        $gateway = new \App\SMSGateway();
    }
    return $gateway;
}

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
                    $payrollId = $employee->createPayroll($_POST);
                    
                    if ($payrollId && !empty($_POST['include_late_deductions']) && !empty($_POST['employee_id']) && !empty($_POST['pay_period_start'])) {
                        $payrollDb = Database::getConnection();
                        $lateCalculator = new \App\LateDeductionCalculator($payrollDb);
                        $payPeriodMonth = date('Y-m', strtotime($_POST['pay_period_start']));
                        $lateCalculator->applyDeductionsToPayroll($payrollId, (int)$_POST['employee_id'], $payPeriodMonth);
                        $message = 'Payroll record created with late deductions applied!';
                    } else {
                        $message = 'Payroll record created successfully!';
                    }
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

            case 'save_company_settings':
                try {
                    $settings->saveCompanyInfo($_POST);
                    $message = 'Company settings saved successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error saving settings: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'save_sms_settings':
                try {
                    $settings->saveSMSSettings($_POST);
                    \App\Settings::clearCache();
                    $message = 'SMS settings saved successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error saving SMS settings: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'save_whatsapp_settings':
                try {
                    $whatsappData = $_POST;
                    if (!isset($whatsappData['whatsapp_enabled'])) {
                        $whatsappData['whatsapp_enabled'] = '0';
                    }
                    $settings->saveWhatsAppSettings($whatsappData);
                    \App\Settings::clearCache();
                    $message = 'WhatsApp settings saved successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error saving WhatsApp settings: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'create_package':
                $name = trim($_POST['name'] ?? '');
                $speed = trim($_POST['speed'] ?? '');
                $price = floatval($_POST['price'] ?? 0);
                if (empty($name) || empty($speed) || $price <= 0) {
                    $message = 'Package name, speed, and price are required.';
                    $messageType = 'danger';
                } else {
                    try {
                        $featuresText = trim($_POST['features_text'] ?? '');
                        $features = array_filter(array_map('trim', explode("\n", $featuresText)));
                        $_POST['features'] = $features;
                        $settings->createPackage($_POST);
                        $message = 'Package created successfully!';
                        $messageType = 'success';
                        \App\Auth::regenerateToken();
                        header('Location: ?page=settings&subpage=packages');
                        exit;
                    } catch (Exception $e) {
                        $message = 'Error creating package: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;

            case 'update_package':
                $packageId = (int)($_POST['package_id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $speed = trim($_POST['speed'] ?? '');
                $price = floatval($_POST['price'] ?? 0);
                if (!$packageId || empty($name) || empty($speed) || $price <= 0) {
                    $message = 'Package name, speed, and price are required.';
                    $messageType = 'danger';
                } else {
                    try {
                        $featuresText = trim($_POST['features_text'] ?? '');
                        $features = array_filter(array_map('trim', explode("\n", $featuresText)));
                        $_POST['features'] = $features;
                        $settings->updatePackage($packageId, $_POST);
                        $message = 'Package updated successfully!';
                        $messageType = 'success';
                        \App\Auth::regenerateToken();
                        header('Location: ?page=settings&subpage=packages');
                        exit;
                    } catch (Exception $e) {
                        $message = 'Error updating package: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;

            case 'delete_package':
                if (!\App\Auth::isAdmin()) {
                    $message = 'Only administrators can delete packages.';
                    $messageType = 'danger';
                } else {
                    try {
                        $packageId = (int)($_POST['package_id'] ?? 0);
                        if ($packageId) {
                            $settings->deletePackage($packageId);
                            $message = 'Package deleted successfully!';
                            $messageType = 'success';
                            \App\Auth::regenerateToken();
                        }
                    } catch (Exception $e) {
                        $message = 'Error deleting package: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;

            case 'save_landing_settings':
                try {
                    $settings->saveLandingPageSettings($_POST);
                    \App\Settings::clearCache();
                    $message = 'Landing page settings saved successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error saving landing page settings: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'save_mpesa_settings':
                try {
                    $mpesa = new \App\Mpesa();
                    $mpesa->saveConfig('mpesa_environment', $_POST['mpesa_environment'] ?? 'sandbox');
                    $mpesa->saveConfig('mpesa_shortcode', $_POST['mpesa_shortcode'] ?? '');
                    $mpesa->saveConfig('mpesa_consumer_key', $_POST['mpesa_consumer_key'] ?? '');
                    $mpesa->saveConfig('mpesa_consumer_secret', $_POST['mpesa_consumer_secret'] ?? '');
                    $mpesa->saveConfig('mpesa_passkey', $_POST['mpesa_passkey'] ?? '');
                    $mpesa->saveConfig('mpesa_callback_url', $_POST['mpesa_callback_url'] ?? '');
                    $mpesa->saveConfig('mpesa_validation_url', $_POST['mpesa_validation_url'] ?? '');
                    $mpesa->saveConfig('mpesa_confirmation_url', $_POST['mpesa_confirmation_url'] ?? '');
                    $message = 'M-Pesa settings saved successfully!';
                    $messageType = 'success';
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error saving M-Pesa settings: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'register_c2b':
                try {
                    $mpesa = new \App\Mpesa();
                    $result = $mpesa->registerC2BUrls();
                    if ($result['success']) {
                        $mpesa->saveConfig('c2b_urls_registered', date('Y-m-d H:i:s'));
                        $message = 'C2B URLs registered successfully!';
                        $messageType = 'success';
                    } else {
                        $message = 'Failed to register C2B URLs: ' . ($result['error'] ?? 'Unknown error');
                        $messageType = 'danger';
                    }
                    \App\Auth::regenerateToken();
                } catch (Exception $e) {
                    $message = 'Error registering C2B URLs: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'create_template':
                $name = trim($_POST['name'] ?? '');
                $content = trim($_POST['content'] ?? '');
                if (empty($name) || empty($content)) {
                    $message = 'Template name and content are required.';
                    $messageType = 'danger';
                } else {
                    try {
                        $_POST['created_by'] = $currentUser['id'];
                        $settings->createTemplate($_POST);
                        $message = 'Template created successfully!';
                        $messageType = 'success';
                        \App\Auth::regenerateToken();
                    } catch (Exception $e) {
                        $message = 'Error creating template: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;

            case 'update_template':
                $name = trim($_POST['name'] ?? '');
                $content = trim($_POST['content'] ?? '');
                if (empty($name) || empty($content)) {
                    $message = 'Template name and content are required.';
                    $messageType = 'danger';
                } else {
                    try {
                        $settings->updateTemplate((int)$_POST['id'], $_POST);
                        $message = 'Template updated successfully!';
                        $messageType = 'success';
                        \App\Auth::regenerateToken();
                    } catch (Exception $e) {
                        $message = 'Error updating template: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;

            case 'delete_template':
                if (!\App\Auth::isAdmin()) {
                    $message = 'Only administrators can delete templates.';
                    $messageType = 'danger';
                } else {
                    try {
                        $settings->deleteTemplate((int)$_POST['id']);
                        $message = 'Template deleted successfully!';
                        $messageType = 'success';
                        \App\Auth::regenerateToken();
                    } catch (Exception $e) {
                        $message = 'Error deleting template: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;

            case 'add_biometric_device':
                if (!\App\Auth::isAdmin()) {
                    $message = 'Only administrators can add biometric devices.';
                    $messageType = 'danger';
                } else {
                    try {
                        $biometricService = new \App\BiometricSyncService($db);
                        $deviceData = [
                            'name' => $_POST['name'],
                            'device_type' => $_POST['device_type'],
                            'ip_address' => $_POST['ip_address'],
                            'port' => (int)$_POST['port'],
                            'username' => $_POST['username'] ?? null,
                            'password' => $_POST['password'] ?? null,
                            'sync_interval_minutes' => (int)($_POST['sync_interval_minutes'] ?? 15),
                            'is_active' => isset($_POST['is_active'])
                        ];
                        $biometricService->addDevice($deviceData);
                        $message = 'Biometric device added successfully!';
                        $messageType = 'success';
                        \App\Auth::regenerateToken();
                    } catch (Exception $e) {
                        $message = 'Error adding device: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;

            case 'update_biometric_device':
                if (!\App\Auth::isAdmin()) {
                    $message = 'Only administrators can update biometric devices.';
                    $messageType = 'danger';
                } else {
                    try {
                        $biometricService = new \App\BiometricSyncService($db);
                        $deviceData = [
                            'name' => $_POST['name'],
                            'device_type' => $_POST['device_type'],
                            'ip_address' => $_POST['ip_address'],
                            'port' => (int)$_POST['port'],
                            'username' => $_POST['username'] ?? null,
                            'sync_interval_minutes' => (int)($_POST['sync_interval_minutes'] ?? 15),
                            'is_active' => isset($_POST['is_active'])
                        ];
                        if (!empty($_POST['password'])) {
                            $deviceData['password'] = $_POST['password'];
                        }
                        $biometricService->updateDevice((int)$_POST['device_id'], $deviceData);
                        $message = 'Biometric device updated successfully!';
                        $messageType = 'success';
                        \App\Auth::regenerateToken();
                    } catch (Exception $e) {
                        $message = 'Error updating device: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;

            case 'save_user_mapping':
                if (!\App\Auth::isAdmin()) {
                    $message = 'Only administrators can manage user mappings.';
                    $messageType = 'danger';
                } else {
                    try {
                        $biometricService = new \App\BiometricSyncService($db);
                        $biometricService->saveUserMapping(
                            (int)$_POST['device_id'],
                            $_POST['device_user_id'],
                            (int)$_POST['employee_id'],
                            $_POST['device_user_name'] ?? null
                        );
                        $message = 'User mapping saved successfully!';
                        $messageType = 'success';
                        \App\Auth::regenerateToken();
                    } catch (Exception $e) {
                        $message = 'Error saving mapping: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;

            case 'add_late_rule':
                if (!\App\Auth::isAdmin()) {
                    $message = 'Only administrators can add late rules.';
                    $messageType = 'danger';
                } else {
                    try {
                        $lateCalculator = new \App\LateDeductionCalculator($db);
                        $tiers = [];
                        if (!empty($_POST['tier_min']) && is_array($_POST['tier_min'])) {
                            foreach ($_POST['tier_min'] as $i => $min) {
                                if ($min !== '' && isset($_POST['tier_max'][$i]) && isset($_POST['tier_amount'][$i])) {
                                    $tiers[] = [
                                        'min_minutes' => (int)$min,
                                        'max_minutes' => (int)$_POST['tier_max'][$i],
                                        'amount' => (float)$_POST['tier_amount'][$i]
                                    ];
                                }
                            }
                        }
                        $ruleData = [
                            'name' => $_POST['name'],
                            'work_start_time' => $_POST['work_start_time'],
                            'grace_minutes' => (int)($_POST['grace_minutes'] ?? 15),
                            'deduction_tiers' => $tiers,
                            'currency' => $_POST['currency'] ?? 'KES',
                            'apply_to_department_id' => $_POST['apply_to_department_id'] ?: null,
                            'is_default' => isset($_POST['is_default']),
                            'is_active' => isset($_POST['is_active'])
                        ];
                        $lateCalculator->addRule($ruleData);
                        $message = 'Late deduction rule added successfully!';
                        $messageType = 'success';
                        \App\Auth::regenerateToken();
                    } catch (Exception $e) {
                        $message = 'Error adding rule: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;

            case 'update_late_rule':
                if (!\App\Auth::isAdmin()) {
                    $message = 'Only administrators can update late rules.';
                    $messageType = 'danger';
                } else {
                    try {
                        $lateCalculator = new \App\LateDeductionCalculator($db);
                        $tiers = [];
                        if (!empty($_POST['tier_min']) && is_array($_POST['tier_min'])) {
                            foreach ($_POST['tier_min'] as $i => $min) {
                                if ($min !== '' && isset($_POST['tier_max'][$i]) && isset($_POST['tier_amount'][$i])) {
                                    $tiers[] = [
                                        'min_minutes' => (int)$min,
                                        'max_minutes' => (int)$_POST['tier_max'][$i],
                                        'amount' => (float)$_POST['tier_amount'][$i]
                                    ];
                                }
                            }
                        }
                        $ruleData = [
                            'name' => $_POST['name'],
                            'work_start_time' => $_POST['work_start_time'],
                            'grace_minutes' => (int)($_POST['grace_minutes'] ?? 15),
                            'deduction_tiers' => $tiers,
                            'currency' => $_POST['currency'] ?? 'KES',
                            'apply_to_department_id' => $_POST['apply_to_department_id'] ?: null,
                            'is_default' => isset($_POST['is_default']),
                            'is_active' => isset($_POST['is_active'])
                        ];
                        $lateCalculator->updateRule((int)$_POST['rule_id'], $ruleData);
                        $message = 'Late deduction rule updated successfully!';
                        $messageType = 'success';
                        \App\Auth::regenerateToken();
                    } catch (Exception $e) {
                        $message = 'Error updating rule: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;
        }
    }
}

if ($page === 'settings' && $action === 'delete_device' && isset($_GET['id'])) {
    if (!\App\Auth::isAdmin()) {
        $message = 'Only administrators can delete devices.';
        $messageType = 'danger';
    } else {
        try {
            $biometricService = new \App\BiometricSyncService($db);
            $biometricService->deleteDevice((int)$_GET['id']);
            $message = 'Biometric device deleted successfully!';
            $messageType = 'success';
        } catch (Exception $e) {
            $message = 'Error deleting device: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

if ($page === 'settings' && $action === 'delete_mapping' && isset($_GET['device_id']) && isset($_GET['device_user_id'])) {
    if (!\App\Auth::isAdmin()) {
        $message = 'Only administrators can delete mappings.';
        $messageType = 'danger';
    } else {
        try {
            $biometricService = new \App\BiometricSyncService($db);
            $biometricService->deleteUserMapping((int)$_GET['device_id'], $_GET['device_user_id']);
            $message = 'User mapping deleted successfully!';
            $messageType = 'success';
        } catch (Exception $e) {
            $message = 'Error deleting mapping: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

if ($page === 'settings' && $action === 'delete_rule' && isset($_GET['id'])) {
    if (!\App\Auth::isAdmin()) {
        $message = 'Only administrators can delete rules.';
        $messageType = 'danger';
    } else {
        try {
            $lateCalculator = new \App\LateDeductionCalculator($db);
            $lateCalculator->deleteRule((int)$_GET['id']);
            $message = 'Late deduction rule deleted successfully!';
            $messageType = 'success';
        } catch (Exception $e) {
            $message = 'Error deleting rule: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

if ($page === 'hr' && $action === 'sync_biometric') {
    if (!\App\Auth::isAdmin()) {
        $message = 'Only administrators can sync biometric devices.';
        $messageType = 'danger';
    } else {
        try {
            $biometricService = new \App\BiometricSyncService($db);
            $syncResults = $biometricService->syncAllDevices();
            $successCount = count(array_filter($syncResults, fn($r) => $r['success']));
            $totalCount = count($syncResults);
            if ($successCount === $totalCount) {
                $message = "All {$totalCount} device(s) synced successfully!";
                $messageType = 'success';
            } elseif ($successCount > 0) {
                $message = "{$successCount} of {$totalCount} device(s) synced successfully. Some devices failed to sync.";
                $messageType = 'warning';
            } else {
                $message = "Failed to sync devices. Please check device configuration.";
                $messageType = 'danger';
            }
        } catch (Exception $e) {
            $message = 'Error syncing devices: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

if ($page === 'inventory' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $inventory = new \App\Inventory($db);
    $tab = $_GET['tab'] ?? 'equipment';
    $inventoryAction = $_GET['action'] ?? '';
    
    $csrfValid = \App\Auth::validateToken($_POST['csrf_token'] ?? '');
    if (!$csrfValid) {
        $_SESSION['error_message'] = 'Invalid request. Please try again.';
    } else {
        try {
            switch ($tab) {
                case 'equipment':
                    if ($inventoryAction === 'save') {
                        $data = [
                            'category_id' => $_POST['category_id'] ?: null,
                            'name' => $_POST['name'],
                            'brand' => $_POST['brand'] ?? null,
                            'model' => $_POST['model'] ?? null,
                            'serial_number' => $_POST['serial_number'] ?? null,
                            'mac_address' => $_POST['mac_address'] ?? null,
                            'purchase_date' => $_POST['purchase_date'] ?: null,
                            'purchase_price' => $_POST['purchase_price'] ?: null,
                            'warranty_expiry' => $_POST['warranty_expiry'] ?: null,
                            'condition' => $_POST['condition'] ?? 'new',
                            'status' => $_POST['status'] ?? 'available',
                            'location' => $_POST['location'] ?? null,
                            'notes' => $_POST['notes'] ?? null
                        ];
                        if (!empty($_POST['id'])) {
                            $inventory->updateEquipment((int)$_POST['id'], $data);
                            $_SESSION['success_message'] = 'Equipment updated successfully!';
                        } else {
                            $inventory->addEquipment($data);
                            $_SESSION['success_message'] = 'Equipment added successfully!';
                        }
                        \App\Auth::regenerateToken();
                        header('Location: ?page=inventory&tab=equipment');
                        exit;
                    } elseif ($inventoryAction === 'delete') {
                        if (!\App\Auth::isAdmin()) {
                            $_SESSION['error_message'] = 'Only administrators can delete equipment.';
                        } else {
                            $inventory->deleteEquipment((int)$_POST['id']);
                            $_SESSION['success_message'] = 'Equipment deleted successfully!';
                            \App\Auth::regenerateToken();
                        }
                        header('Location: ?page=inventory&tab=equipment');
                        exit;
                    }
                    break;
                    
                case 'categories':
                    if ($inventoryAction === 'save') {
                        $data = [
                            'name' => $_POST['name'],
                            'description' => $_POST['description'] ?? null
                        ];
                        if (!empty($_POST['id'])) {
                            $inventory->updateCategory((int)$_POST['id'], $data);
                            $_SESSION['success_message'] = 'Category updated successfully!';
                        } else {
                            $inventory->addCategory($data);
                            $_SESSION['success_message'] = 'Category added successfully!';
                        }
                        \App\Auth::regenerateToken();
                        header('Location: ?page=inventory&tab=categories');
                        exit;
                    } elseif ($inventoryAction === 'delete') {
                        if (!\App\Auth::isAdmin()) {
                            $_SESSION['error_message'] = 'Only administrators can delete categories.';
                        } else {
                            $inventory->deleteCategory((int)$_POST['id']);
                            $_SESSION['success_message'] = 'Category deleted successfully!';
                            \App\Auth::regenerateToken();
                        }
                        header('Location: ?page=inventory&tab=categories');
                        exit;
                    }
                    break;
                    
                case 'assignments':
                    if ($inventoryAction === 'assign') {
                        $data = [
                            'equipment_id' => (int)$_POST['equipment_id'],
                            'employee_id' => (int)$_POST['employee_id'],
                            'assigned_date' => $_POST['assigned_date'] ?? date('Y-m-d'),
                            'assigned_by' => $currentUser['id'],
                            'notes' => $_POST['notes'] ?? null
                        ];
                        $inventory->assignToEmployee($data);
                        $_SESSION['success_message'] = 'Equipment assigned successfully!';
                        \App\Auth::regenerateToken();
                        header('Location: ?page=inventory&tab=assignments');
                        exit;
                    } elseif ($inventoryAction === 'return') {
                        $inventory->returnFromEmployee((int)$_POST['assignment_id']);
                        $_SESSION['success_message'] = 'Equipment returned successfully!';
                        \App\Auth::regenerateToken();
                        header('Location: ?page=inventory&tab=assignments');
                        exit;
                    }
                    break;
                    
                case 'loans':
                    if ($inventoryAction === 'loan') {
                        $data = [
                            'equipment_id' => (int)$_POST['equipment_id'],
                            'customer_id' => (int)$_POST['customer_id'],
                            'loan_date' => $_POST['loan_date'] ?? date('Y-m-d'),
                            'expected_return_date' => $_POST['expected_return_date'] ?: null,
                            'loaned_by' => $currentUser['id'],
                            'deposit_amount' => $_POST['deposit_amount'] ?? 0,
                            'deposit_paid' => isset($_POST['deposit_paid']),
                            'notes' => $_POST['notes'] ?? null
                        ];
                        $inventory->loanToCustomer($data);
                        $_SESSION['success_message'] = 'Equipment loaned successfully!';
                        \App\Auth::regenerateToken();
                        header('Location: ?page=inventory&tab=loans');
                        exit;
                    } elseif ($inventoryAction === 'return') {
                        $inventory->returnFromCustomer((int)$_POST['loan_id']);
                        $_SESSION['success_message'] = 'Equipment returned successfully!';
                        \App\Auth::regenerateToken();
                        header('Location: ?page=inventory&tab=loans');
                        exit;
                    }
                    break;
                    
                case 'faults':
                    if ($inventoryAction === 'report') {
                        $data = [
                            'equipment_id' => (int)$_POST['equipment_id'],
                            'reported_date' => $_POST['reported_date'] ?? date('Y-m-d'),
                            'reported_by' => $currentUser['id'],
                            'fault_description' => $_POST['fault_description'],
                            'severity' => $_POST['severity'] ?? 'minor'
                        ];
                        $inventory->reportFault($data);
                        $_SESSION['success_message'] = 'Fault reported successfully!';
                        \App\Auth::regenerateToken();
                        header('Location: ?page=inventory&tab=faults');
                        exit;
                    } elseif ($inventoryAction === 'mark_repaired') {
                        $data = [
                            'repair_date' => $_POST['repair_date'] ?? date('Y-m-d'),
                            'repair_cost' => $_POST['repair_cost'] ?: null,
                            'repair_notes' => $_POST['repair_notes'] ?? null
                        ];
                        $inventory->markFaultRepaired((int)$_POST['fault_id'], $data);
                        $_SESSION['success_message'] = 'Equipment marked as repaired!';
                        \App\Auth::regenerateToken();
                        header('Location: ?page=inventory&tab=faults');
                        exit;
                    }
                    break;
                    
                case 'import':
                    if ($inventoryAction === 'import') {
                        if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
                            $uploadErrors = [
                                UPLOAD_ERR_INI_SIZE => 'File exceeds maximum upload size.',
                                UPLOAD_ERR_FORM_SIZE => 'File exceeds maximum form size.',
                                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
                                UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
                                UPLOAD_ERR_NO_TMP_DIR => 'Server configuration error (no temp folder).',
                                UPLOAD_ERR_CANT_WRITE => 'Server error (cannot write file).',
                            ];
                            $errorCode = $_FILES['import_file']['error'] ?? UPLOAD_ERR_NO_FILE;
                            throw new Exception($uploadErrors[$errorCode] ?? 'Please select a valid file to import.');
                        }
                        $file = $_FILES['import_file'];
                        $maxSize = 10 * 1024 * 1024; // 10MB max
                        if ($file['size'] > $maxSize) {
                            throw new Exception('File is too large. Maximum size is 10MB.');
                        }
                        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                        if (!in_array($ext, ['xlsx', 'xls', 'csv'])) {
                            throw new Exception('Invalid file format. Please use Excel (.xlsx, .xls) or CSV (.csv) files.');
                        }
                        $results = $inventory->importFromExcel($file['tmp_name']);
                        if ($results['success'] > 0) {
                            $msg = "Successfully imported {$results['success']} equipment item(s).";
                            if ($results['failed'] > 0) {
                                $msg .= " {$results['failed']} row(s) failed.";
                            }
                            $_SESSION['success_message'] = $msg;
                        } else {
                            $_SESSION['error_message'] = 'Import failed. ' . implode('; ', $results['errors']);
                        }
                        \App\Auth::regenerateToken();
                        header('Location: ?page=inventory&tab=import');
                        exit;
                    } elseif ($inventoryAction === 'bulk_add') {
                        $items = $_POST['items'] ?? [];
                        $items = array_filter($items, fn($item) => !empty($item['name']));
                        if (empty($items)) {
                            throw new Exception('Please add at least one equipment item with a name.');
                        }
                        $results = $inventory->bulkAddEquipment($items);
                        if ($results['success'] > 0) {
                            $msg = "Successfully added {$results['success']} equipment item(s).";
                            if ($results['failed'] > 0) {
                                $msg .= " {$results['failed']} row(s) failed.";
                            }
                            $_SESSION['success_message'] = $msg;
                        } else {
                            $_SESSION['error_message'] = 'Bulk add failed. ' . implode('; ', $results['errors']);
                        }
                        \App\Auth::regenerateToken();
                        header('Location: ?page=inventory&tab=import');
                        exit;
                    }
                    break;
            }
        } catch (Exception $e) {
            $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
        }
    }
}

// Handle inventory GET actions (template download and export)
if ($page === 'inventory' && isset($_GET['action'])) {
    $inventory = new \App\Inventory();
    $inventoryAction = $_GET['action'];
    
    if ($inventoryAction === 'download_template') {
        $spreadsheet = $inventory->generateImportTemplate();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="equipment_import_template.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    } elseif ($inventoryAction === 'export') {
        $filters = [];
        if (!empty($_GET['status'])) $filters['status'] = $_GET['status'];
        if (!empty($_GET['category_id'])) $filters['category_id'] = (int)$_GET['category_id'];
        $spreadsheet = $inventory->exportEquipment($filters);
        $filename = 'equipment_export_' . date('Y-m-d_His') . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
}

// Handle M-Pesa payments
if ($page === 'payments' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $mpesa = new \App\Mpesa();
    $tab = $_GET['tab'] ?? 'stkpush';
    $paymentAction = $_GET['action'] ?? '';
    
    $csrfValid = \App\Auth::validateToken($_POST['csrf_token'] ?? '');
    if (!$csrfValid) {
        $_SESSION['error_message'] = 'Invalid request. Please try again.';
    } else {
        try {
            if ($tab === 'stkpush' && $paymentAction === 'send') {
                $phone = $_POST['phone'] ?? '';
                $amount = (float)($_POST['amount'] ?? 0);
                $accountRef = $_POST['account_ref'] ?? '';
                $description = $_POST['description'] ?? 'Payment';
                $customerId = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
                
                if (empty($phone) || $amount <= 0 || empty($accountRef)) {
                    $_SESSION['error_message'] = 'Phone number, amount, and account reference are required.';
                } else {
                    $result = $mpesa->stkPush($phone, $amount, $accountRef, $description, $customerId);
                    if ($result['success']) {
                        $_SESSION['success_message'] = $result['message'];
                    } else {
                        $_SESSION['error_message'] = $result['message'];
                    }
                }
                \App\Auth::regenerateToken();
            } elseif ($tab === 'c2b' && $paymentAction === 'register_urls') {
                $result = $mpesa->registerC2BUrls();
                if ($result['success']) {
                    $_SESSION['success_message'] = 'C2B URLs registered successfully!';
                } else {
                    $_SESSION['error_message'] = 'Failed to register C2B URLs: ' . $result['message'];
                }
                \App\Auth::regenerateToken();
            }
        } catch (Exception $e) {
            $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
        }
    }
    header('Location: ?page=payments&tab=' . $tab);
    exit;
}

// Handle M-Pesa GET actions (query status)
if ($page === 'payments' && isset($_GET['action'])) {
    $mpesa = new \App\Mpesa();
    $paymentAction = $_GET['action'];
    $tab = $_GET['tab'] ?? 'stkpush';
    
    if ($paymentAction === 'query' && isset($_GET['id'])) {
        $result = $mpesa->stkQuery($_GET['id']);
        if (isset($result['ResultCode']) && $result['ResultCode'] === '0') {
            $_SESSION['success_message'] = 'Transaction completed successfully!';
        } elseif (isset($result['ResultCode'])) {
            $_SESSION['error_message'] = 'Transaction status: ' . ($result['ResultDesc'] ?? 'Unknown');
        } else {
            $_SESSION['error_message'] = 'Could not query transaction status.';
        }
        header('Location: ?page=payments&tab=' . $tab);
        exit;
    } elseif ($paymentAction === 'export') {
        $transactions = $mpesa->getTransactions([
            'date_from' => $_GET['date_from'] ?? date('Y-m-01'),
            'date_to' => $_GET['date_to'] ?? date('Y-m-d'),
            'limit' => 10000
        ]);
        
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('M-Pesa Transactions');
        
        $headers = ['Date', 'Phone', 'Amount', 'Receipt', 'Account Ref', 'Customer', 'Status', 'Description'];
        foreach ($headers as $col => $header) {
            $sheet->setCellValue([$col + 1, 1], $header);
        }
        $sheet->getStyle('A1:H1')->getFont()->setBold(true);
        
        $row = 2;
        foreach ($transactions as $tx) {
            $sheet->setCellValue([1, $row], $tx['created_at']);
            $sheet->setCellValue([2, $row], $tx['phone_number'] ?? '');
            $sheet->setCellValue([3, $row], $tx['amount'] ?? 0);
            $sheet->setCellValue([4, $row], $tx['mpesa_receipt_number'] ?? '');
            $sheet->setCellValue([5, $row], $tx['account_reference'] ?? '');
            $sheet->setCellValue([6, $row], $tx['customer_name'] ?? '');
            $sheet->setCellValue([7, $row], $tx['status'] ?? '');
            $sheet->setCellValue([8, $row], $tx['result_desc'] ?? '');
            $row++;
        }
        
        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        $filename = 'mpesa_transactions_' . date('Y-m-d_His') . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
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
                <a class="nav-link <?= $page === 'inventory' ? 'active' : '' ?>" href="?page=inventory">
                    <i class="bi bi-box-seam"></i> Inventory
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $page === 'payments' ? 'active' : '' ?>" href="?page=payments">
                    <i class="bi bi-cash-stack"></i> Payments
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $page === 'settings' ? 'active' : '' ?>" href="?page=settings">
                    <i class="bi bi-gear"></i> Settings
                </a>
            </li>
        </ul>
        <div class="mt-auto">
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
            case 'inventory':
                include __DIR__ . '/../templates/inventory.php';
                break;
            case 'payments':
                include __DIR__ . '/../templates/payments.php';
                break;
            case 'settings':
                $smsGateway = getSMSGateway();
                include __DIR__ . '/../templates/settings.php';
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
