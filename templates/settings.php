<?php
$subpage = $_GET['subpage'] ?? 'company';
$settings = new \App\Settings();
$companyInfo = $settings->getCompanyInfo();
$templates = $settings->getAllTemplates();
$templateCategories = $settings->getDefaultTemplateCategories();
$timezones = $settings->getTimezones();
$currencies = $settings->getCurrencies();
$dateFormats = $settings->getDateFormats();
$timeFormats = $settings->getTimeFormats();
$gatewayInfo = $smsGateway->getGatewayInfo();
$whatsappSettings = $settings->getWhatsAppSettings();
$templateEngine = new \App\TemplateEngine();
$placeholderCategories = $templateEngine->getPlaceholderCategories();
$whatsapp = new \App\WhatsApp();
$dbConn = \Database::getConnection();
$biometricService = new \App\BiometricSyncService($dbConn);
$lateCalculator = new \App\LateDeductionCalculator($dbConn);
$biometricDevices = $biometricService->getDevices(false);
$lateRules = $lateCalculator->getLateRules();
$departments = (new \App\Employee($dbConn))->getAllDepartments();

$editTemplate = null;
if ($action === 'edit_template' && $id) {
    $editTemplate = $settings->getTemplate($id);
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-gear"></i> Settings</h2>
</div>

<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link <?= $subpage === 'company' ? 'active' : '' ?>" href="?page=settings&subpage=company">
            <i class="bi bi-building"></i> Company
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $subpage === 'sms' ? 'active' : '' ?>" href="?page=settings&subpage=sms">
            <i class="bi bi-chat-dots"></i> SMS Gateway
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $subpage === 'whatsapp' ? 'active' : '' ?>" href="?page=settings&subpage=whatsapp">
            <i class="bi bi-whatsapp"></i> WhatsApp
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $subpage === 'templates' ? 'active' : '' ?>" href="?page=settings&subpage=templates">
            <i class="bi bi-file-text"></i> Ticket Templates
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $subpage === 'biometric' ? 'active' : '' ?>" href="?page=settings&subpage=biometric">
            <i class="bi bi-fingerprint"></i> Biometric Devices
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $subpage === 'late_rules' ? 'active' : '' ?>" href="?page=settings&subpage=late_rules">
            <i class="bi bi-clock-history"></i> Late Deductions
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $subpage === 'packages' ? 'active' : '' ?>" href="?page=settings&subpage=packages">
            <i class="bi bi-box-seam"></i> Service Packages
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $subpage === 'landing' ? 'active' : '' ?>" href="?page=settings&subpage=landing">
            <i class="bi bi-globe"></i> Landing Page
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $subpage === 'mpesa' ? 'active' : '' ?>" href="?page=settings&subpage=mpesa">
            <i class="bi bi-phone"></i> M-Pesa
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $subpage === 'sales' ? 'active' : '' ?>" href="?page=settings&subpage=sales">
            <i class="bi bi-graph-up-arrow"></i> Commissions
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $subpage === 'mobile' ? 'active' : '' ?>" href="?page=settings&subpage=mobile">
            <i class="bi bi-phone"></i> Mobile App
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $subpage === 'users' ? 'active' : '' ?>" href="?page=settings&subpage=users">
            <i class="bi bi-shield-lock"></i> Roles & Permissions
        </a>
    </li>
</ul>

<?php if ($subpage === 'company'): ?>

<form method="POST">
    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
    <input type="hidden" name="action" value="save_company_settings">
    
    <div class="row g-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-info-circle"></i> Company Information</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Company Name</label>
                        <input type="text" class="form-control" name="company_name" value="<?= htmlspecialchars($companyInfo['company_name']) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="company_email" value="<?= htmlspecialchars($companyInfo['company_email']) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="tel" class="form-control" name="company_phone" value="<?= htmlspecialchars($companyInfo['company_phone']) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Website</label>
                        <input type="url" class="form-control" name="company_website" value="<?= htmlspecialchars($companyInfo['company_website']) ?>" placeholder="https://">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea class="form-control" name="company_address" rows="2"><?= htmlspecialchars($companyInfo['company_address']) ?></textarea>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-sliders"></i> System Preferences</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Timezone</label>
                            <select class="form-select" name="timezone">
                                <?php foreach ($timezones as $tz => $label): ?>
                                <option value="<?= $tz ?>" <?= $companyInfo['timezone'] === $tz ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Currency</label>
                            <select class="form-select" name="currency" id="currencySelect">
                                <?php foreach ($currencies as $code => $info): ?>
                                <option value="<?= $code ?>" data-symbol="<?= $info['symbol'] ?>" <?= $companyInfo['currency'] === $code ? 'selected' : '' ?>>
                                    <?= $info['name'] ?> (<?= $info['symbol'] ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="currency_symbol" id="currencySymbol" value="<?= htmlspecialchars($companyInfo['currency_symbol']) ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Date Format</label>
                            <select class="form-select" name="date_format">
                                <?php foreach ($dateFormats as $format => $label): ?>
                                <option value="<?= $format ?>" <?= $companyInfo['date_format'] === $format ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Time Format</label>
                            <select class="form-select" name="time_format">
                                <?php foreach ($timeFormats as $format => $label): ?>
                                <option value="<?= $format ?>" <?= $companyInfo['time_format'] === $format ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Working Hours Start</label>
                            <input type="time" class="form-control" name="working_hours_start" value="<?= htmlspecialchars($companyInfo['working_hours_start']) ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Working Hours End</label>
                            <input type="time" class="form-control" name="working_hours_end" value="<?= htmlspecialchars($companyInfo['working_hours_end']) ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-hash"></i> ID Prefixes</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Ticket Prefix</label>
                            <input type="text" class="form-control" name="ticket_prefix" value="<?= htmlspecialchars($companyInfo['ticket_prefix']) ?>" maxlength="10">
                            <small class="text-muted">e.g., TKT-2024-001</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Customer Prefix</label>
                            <input type="text" class="form-control" name="customer_prefix" value="<?= htmlspecialchars($companyInfo['customer_prefix']) ?>" maxlength="10">
                            <small class="text-muted">e.g., CUS-2024-001</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-bell"></i> Notifications</h5>
                </div>
                <div class="card-body">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="sms_enabled" id="smsEnabled" value="1" <?= $companyInfo['sms_enabled'] === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="smsEnabled">Enable SMS Notifications</label>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="whatsapp_enabled" id="whatsappEnabled" value="1" <?= ($companyInfo['whatsapp_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="whatsappEnabled">Enable WhatsApp Messaging</label>
                        <small class="text-muted d-block">Opens WhatsApp Web for quick messaging</small>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="email_notifications" id="emailNotifications" value="1" <?= $companyInfo['email_notifications'] === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="emailNotifications">Enable Email Notifications</label>
                        <small class="text-muted d-block">Requires email server configuration</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="mt-4">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg"></i> Save Settings
        </button>
    </div>
</form>

<script>
document.getElementById('currencySelect').addEventListener('change', function() {
    var symbol = this.options[this.selectedIndex].dataset.symbol;
    document.getElementById('currencySymbol').value = symbol;
});
</script>

<?php elseif ($subpage === 'sms'): ?>

<?php
$testResult = null;
$sendTestResult = null;
$smsSettings = $settings->getSMSSettings();

if (($_GET['action'] ?? '') === 'test') {
    $testResult = $smsGateway->testConnection();
}
if (($_GET['action'] ?? '') === 'send_test' && isset($_GET['phone'])) {
    $sendTestResult = $smsGateway->send($_GET['phone'], 'Test message from ISP CRM System. If you received this, your SMS gateway is working!');
}
?>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-broadcast"></i> Gateway Status</h5>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="stat-icon bg-<?= $gatewayInfo['status'] === 'Enabled' ? 'success' : 'danger' ?> bg-opacity-10 text-<?= $gatewayInfo['status'] === 'Enabled' ? 'success' : 'danger' ?> me-3" style="width: 60px; height: 60px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                        <i class="bi bi-<?= $gatewayInfo['status'] === 'Enabled' ? 'check-circle-fill' : 'x-circle-fill' ?>"></i>
                    </div>
                    <div>
                        <h4 class="mb-0"><?= $gatewayInfo['status'] ?></h4>
                        <small class="text-muted">Provider: <?= $gatewayInfo['type'] ?></small>
                    </div>
                </div>
                
                <?php if ($gatewayInfo['status'] === 'Enabled'): ?>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="?page=settings&subpage=sms&action=test" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-lightning"></i> Test Connection
                    </a>
                    <button type="button" class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#sendTestModal">
                        <i class="bi bi-send"></i> Send Test SMS
                    </button>
                </div>
                <?php endif; ?>
                
                <?php if ($testResult): ?>
                <div class="alert alert-<?= $testResult['success'] ? 'success' : 'danger' ?> mt-3 mb-0">
                    <?php if ($testResult['success']): ?>
                    <strong>Connection Successful!</strong><br>
                    Provider: <?= $testResult['gateway']['type'] ?>
                    <?php else: ?>
                    <strong>Connection Failed:</strong> <?= htmlspecialchars($testResult['error']) ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <?php if ($sendTestResult): ?>
                <div class="alert alert-<?= $sendTestResult['success'] ? 'success' : 'danger' ?> mt-3 mb-0">
                    <?php if ($sendTestResult['success']): ?>
                    <strong>Test SMS Sent!</strong> Check your phone.
                    <?php else: ?>
                    <strong>Failed:</strong> <?= htmlspecialchars($sendTestResult['error'] ?? 'Unknown error') ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-clock-history"></i> Recent SMS Activity</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Time</th>
                                <th>Recipient</th>
                                <th>Type</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $db = \Database::getConnection();
                            $stmt = $db->query("SELECT * FROM sms_logs ORDER BY sent_at DESC LIMIT 10");
                            $smsLogs = $stmt->fetchAll();
                            foreach ($smsLogs as $log):
                            ?>
                            <tr>
                                <td><small><?= date('M j, g:i A', strtotime($log['sent_at'])) ?></small></td>
                                <td><small>...<?= htmlspecialchars(substr($log['recipient_phone'], -4)) ?></small></td>
                                <td><small><?= ucfirst($log['recipient_type']) ?></small></td>
                                <td>
                                    <span class="badge bg-<?= $log['status'] === 'sent' ? 'success' : 'danger' ?>"><?= ucfirst($log['status']) ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($smsLogs)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-3">No SMS sent yet</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mt-4 border-primary">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="bi bi-send-fill"></i> Advanta SMS Configuration</h5>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="save_sms_settings">
            
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">API Key <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="advanta_api_key" 
                           value="<?= htmlspecialchars($smsSettings['advanta_api_key']) ?>" 
                           placeholder="Enter your Advanta API Key">
                    <small class="text-muted">Found in Advanta Dashboard > API Settings</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Partner ID <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="advanta_partner_id" 
                           value="<?= htmlspecialchars($smsSettings['advanta_partner_id']) ?>" 
                           placeholder="e.g., 12345">
                    <small class="text-muted">Your numeric Partner ID</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Shortcode / Sender ID <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="advanta_shortcode" 
                           value="<?= htmlspecialchars($smsSettings['advanta_shortcode']) ?>" 
                           placeholder="e.g., MyISP">
                    <small class="text-muted">This appears as the sender name on customer phones</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label">API URL</label>
                    <input type="url" class="form-control" name="advanta_url" 
                           value="<?= htmlspecialchars($smsSettings['advanta_url'] ?: 'https://quicksms.advantasms.com/api/services/sendsms/') ?>" 
                           placeholder="https://quicksms.advantasms.com/api/services/sendsms/">
                    <small class="text-muted">Leave default unless you have a custom endpoint</small>
                </div>
            </div>
            
            <div class="mt-4">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg"></i> Save SMS Settings
                </button>
            </div>
        </form>
        
        <hr class="my-4">
        
        <div class="alert alert-light border mb-0">
            <h6><i class="bi bi-info-circle"></i> Where to get these details:</h6>
            <ol class="mb-0 small">
                <li>Log in to <a href="https://quicksms.advantasms.com" target="_blank">quicksms.advantasms.com</a></li>
                <li>Go to <strong>API Settings</strong> to find your API Key</li>
                <li>Your <strong>Partner ID</strong> is shown in your account profile</li>
                <li>Your <strong>Shortcode</strong> is your registered Sender ID (alphanumeric)</li>
            </ol>
        </div>
    </div>
</div>

<div class="modal fade" id="sendTestModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-send"></i> Send Test SMS</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="GET">
                <div class="modal-body">
                    <input type="hidden" name="page" value="settings">
                    <input type="hidden" name="subpage" value="sms">
                    <input type="hidden" name="action" value="send_test">
                    <div class="mb-3">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" class="form-control" name="phone" placeholder="e.g., 254712345678" required>
                        <small class="text-muted">Enter phone number in international format (e.g., 254712345678 for Kenya)</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="bi bi-send"></i> Send Test</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php elseif ($subpage === 'whatsapp'): ?>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-whatsapp text-success"></i> WhatsApp Web Status</h5>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="stat-icon bg-<?= $whatsapp->isEnabled() ? 'success' : 'secondary' ?> bg-opacity-10 text-<?= $whatsapp->isEnabled() ? 'success' : 'secondary' ?> me-3" style="width: 60px; height: 60px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                        <i class="bi bi-whatsapp"></i>
                    </div>
                    <div>
                        <h4 class="mb-0"><?= $whatsapp->isEnabled() ? 'Enabled' : 'Disabled' ?></h4>
                        <small class="text-muted">Opens WhatsApp Web with pre-filled messages</small>
                    </div>
                </div>
                
                <div class="alert alert-info mb-0">
                    <i class="bi bi-info-circle"></i> WhatsApp Web integration allows you to send messages directly from ticket pages. Messages open in a new tab with WhatsApp Web, pre-filled with the customer's phone number and message.
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-clock-history"></i> Recent WhatsApp Activity</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Time</th>
                                <th>Recipient</th>
                                <th>Type</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            try {
                                $db = \Database::getConnection();
                                $stmt = $db->query("SELECT * FROM whatsapp_logs ORDER BY sent_at DESC LIMIT 10");
                                $waLogs = $stmt->fetchAll();
                            } catch (Exception $e) {
                                $waLogs = [];
                            }
                            foreach ($waLogs as $log):
                            ?>
                            <tr>
                                <td><small><?= date('M j, g:i A', strtotime($log['sent_at'])) ?></small></td>
                                <td><small>...<?= htmlspecialchars(substr($log['recipient_phone'], -4)) ?></small></td>
                                <td><small><?= ucfirst($log['recipient_type']) ?></small></td>
                                <td>
                                    <span class="badge bg-<?= $log['status'] === 'opened' ? 'success' : 'warning' ?>"><?= ucfirst($log['status']) ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($waLogs)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-3">No WhatsApp messages sent yet</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mt-4 border-success">
    <div class="card-header bg-success text-white">
        <h5 class="mb-0"><i class="bi bi-whatsapp"></i> WhatsApp Settings</h5>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="save_whatsapp_settings">
            
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Default Country Code</label>
                    <div class="input-group">
                        <span class="input-group-text">+</span>
                        <input type="text" class="form-control" name="whatsapp_country_code" 
                               value="<?= htmlspecialchars($whatsappSettings['whatsapp_country_code'] ?? '254') ?>" 
                               placeholder="254">
                    </div>
                    <small class="text-muted">Country code to prepend to phone numbers (e.g., 254 for Kenya, 1 for USA)</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Default Greeting Message</label>
                    <input type="text" class="form-control" name="whatsapp_default_message" 
                           value="<?= htmlspecialchars($whatsappSettings['whatsapp_default_message'] ?? '') ?>" 
                           placeholder="Hello from ISP Support!">
                    <small class="text-muted">Optional greeting to prepend to WhatsApp messages</small>
                </div>
            </div>
            
            <div class="mt-4">
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-check-lg"></i> Save WhatsApp Settings
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="bi bi-info-circle"></i> How WhatsApp Web Integration Works</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h6>For Customers:</h6>
                <ol class="mb-3">
                    <li>Open a ticket in the system</li>
                    <li>Click the <span class="badge bg-success"><i class="bi bi-whatsapp"></i> WhatsApp</span> button</li>
                    <li>WhatsApp Web opens with the customer's phone number</li>
                    <li>Message is pre-filled with ticket information</li>
                    <li>Click send in WhatsApp Web</li>
                </ol>
            </div>
            <div class="col-md-6">
                <h6>For Technicians:</h6>
                <ol class="mb-3">
                    <li>When a ticket is assigned, you can message the customer</li>
                    <li>Click the WhatsApp button next to the customer's phone</li>
                    <li>Send updates, ask questions, or share information</li>
                    <li>All from WhatsApp Web - no API key required!</li>
                </ol>
            </div>
        </div>
        
        <div class="alert alert-light border mb-0">
            <strong>Note:</strong> WhatsApp Web integration requires:
            <ul class="mb-0 mt-2">
                <li>WhatsApp installed on your phone</li>
                <li>WhatsApp Web linked to your phone (visit <a href="https://web.whatsapp.com" target="_blank">web.whatsapp.com</a>)</li>
                <li>Customer phone numbers in international format (or system will auto-convert using country code)</li>
            </ul>
        </div>
    </div>
</div>

<?php elseif ($subpage === 'templates'): ?>

<?php if ($action === 'create_template' || $action === 'edit_template'): ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4><?= $action === 'create_template' ? 'Create Template' : 'Edit Template' ?></h4>
    <a href="?page=settings&subpage=templates" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="<?= $action === 'create_template' ? 'create_template' : 'update_template' ?>">
            <?php if ($action === 'edit_template'): ?>
            <input type="hidden" name="id" value="<?= $editTemplate['id'] ?>">
            <?php endif; ?>
            
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Template Name *</label>
                    <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($editTemplate['name'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Category</label>
                    <select class="form-select" name="category">
                        <option value="">Select Category</option>
                        <?php foreach ($templateCategories as $key => $label): ?>
                        <option value="<?= $key ?>" <?= ($editTemplate['category'] ?? '') === $key ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Subject Line (for emails/ticket responses)</label>
                    <input type="text" class="form-control" name="subject" value="<?= htmlspecialchars($editTemplate['subject'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Template Content *</label>
                    <textarea class="form-control" name="content" rows="8" required id="templateContent"><?= htmlspecialchars($editTemplate['content'] ?? '') ?></textarea>
                    
                    <div class="mt-3">
                        <p class="text-muted mb-2"><strong>Available Placeholders:</strong> <small>(Click to insert)</small></p>
                        <div class="row g-2">
                            <?php foreach ($placeholderCategories as $category => $placeholders): ?>
                            <div class="col-md-4 col-lg-2">
                                <div class="border rounded p-2 h-100">
                                    <h6 class="text-primary mb-2" style="font-size: 0.85rem;"><?= $category ?></h6>
                                    <?php foreach ($placeholders as $placeholder => $desc): ?>
                                    <button type="button" class="btn btn-sm btn-outline-secondary mb-1 w-100 text-start placeholder-btn" 
                                            data-placeholder="<?= htmlspecialchars($placeholder) ?>" 
                                            title="<?= htmlspecialchars($desc) ?>"
                                            style="font-size: 0.75rem; padding: 2px 6px;">
                                        <code><?= htmlspecialchars($placeholder) ?></code>
                                    </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" id="isActive" value="1" <?= ($editTemplate['is_active'] ?? true) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="isActive">Active</label>
                    </div>
                </div>
            </div>
            
            <div class="mt-4">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg"></i> <?= $action === 'create_template' ? 'Create Template' : 'Update Template' ?>
                </button>
                <a href="?page=settings&subpage=templates" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php else: ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <p class="text-muted mb-0">Create reusable response templates for tickets</p>
    </div>
    <a href="?page=settings&subpage=templates&action=create_template" class="btn btn-success">
        <i class="bi bi-plus-circle"></i> Create Template
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Subject</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($templates as $tpl): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($tpl['name']) ?></strong></td>
                        <td>
                            <?php if ($tpl['category']): ?>
                            <span class="badge bg-secondary"><?= htmlspecialchars($templateCategories[$tpl['category']] ?? ucfirst($tpl['category'])) ?></span>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($tpl['subject'] ?? '-') ?></td>
                        <td>
                            <span class="badge bg-<?= $tpl['is_active'] ? 'success' : 'secondary' ?>">
                                <?= $tpl['is_active'] ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td><small><?= date('M j, Y', strtotime($tpl['created_at'])) ?></small></td>
                        <td>
                            <a href="?page=settings&subpage=templates&action=edit_template&id=<?= $tpl['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <?php if (\App\Auth::isAdmin()): ?>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this template?')">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="action" value="delete_template">
                                <input type="hidden" name="id" value="<?= $tpl['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($templates)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            No templates yet. <a href="?page=settings&subpage=templates&action=create_template">Create your first template</a>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header bg-white">
        <h5 class="mb-0">Sample Templates</h5>
    </div>
    <div class="card-body">
        <p class="text-muted">Here are some template examples you can use:</p>
        
        <div class="row g-3">
            <div class="col-md-6">
                <div class="border rounded p-3">
                    <h6>Ticket Acknowledgment (SMS/WhatsApp)</h6>
                    <pre class="bg-light p-2 small mb-0">Dear {customer_name},

Your ticket #{ticket_number} has been received.
Status: {ticket_status}
Technician: {technician_name}
Tech Phone: {technician_phone}

We'll contact you soon.
- {company_name}</pre>
                </div>
            </div>
            <div class="col-md-6">
                <div class="border rounded p-3">
                    <h6>Technician Assignment</h6>
                    <pre class="bg-light p-2 small mb-0">New Ticket Assigned:
#{ticket_number} - {ticket_subject}

Customer: {customer_name}
Phone: {customer_phone}
Priority: {ticket_priority}

Please contact the customer ASAP.
- {company_name}</pre>
                </div>
            </div>
            <div class="col-md-6">
                <div class="border rounded p-3">
                    <h6>Issue Resolved</h6>
                    <pre class="bg-light p-2 small mb-0">Dear {customer_name},

Great news! Ticket #{ticket_number} is resolved.

If you need help, call us at {company_phone} or WhatsApp your technician at {technician_phone}.

Thank you for choosing {company_name}!</pre>
                </div>
            </div>
            <div class="col-md-6">
                <div class="border rounded p-3">
                    <h6>Follow-up Message</h6>
                    <pre class="bg-light p-2 small mb-0">Hi {customer_name},

Following up on ticket #{ticket_number}.
Current status: {ticket_status}

Your technician {technician_name} can be reached at {technician_phone}.

Call us at {company_phone} for any questions.
- {company_name} Support</pre>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.placeholder-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var textarea = document.getElementById('templateContent');
        var placeholder = this.getAttribute('data-placeholder');
        var startPos = textarea.selectionStart;
        var endPos = textarea.selectionEnd;
        var before = textarea.value.substring(0, startPos);
        var after = textarea.value.substring(endPos);
        textarea.value = before + placeholder + after;
        textarea.focus();
        textarea.selectionStart = textarea.selectionEnd = startPos + placeholder.length;
    });
});
</script>

<?php endif; ?>

<?php elseif ($subpage === 'biometric'): ?>

<?php
$editDevice = null;
if ($action === 'edit_device' && $id) {
    $editDevice = $biometricService->getDevice($id);
}
$testDeviceResult = null;
if ($action === 'test_device' && $id) {
    $testDeviceResult = $biometricService->testDevice($id);
}
$syncResult = null;
if ($action === 'sync_device' && $id) {
    $syncResult = $biometricService->syncDevice($id);
}
?>

<div class="row g-4">
    <div class="col-md-<?= ($action === 'add_device' || $editDevice || $action === 'map_users') ? '8' : '12' ?>">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-fingerprint"></i> Biometric Attendance Devices</h5>
                <a href="?page=settings&subpage=biometric&action=add_device" class="btn btn-sm btn-primary">
                    <i class="bi bi-plus-lg"></i> Add Device
                </a>
            </div>
            <div class="card-body">
                <?php if ($testDeviceResult): ?>
                <div class="alert alert-<?= $testDeviceResult['success'] ? 'success' : 'danger' ?> alert-dismissible">
                    <strong><?= $testDeviceResult['success'] ? 'Connection Successful!' : 'Connection Failed' ?></strong><br>
                    <?php if ($testDeviceResult['success']): ?>
                    Device: <?= htmlspecialchars($testDeviceResult['device_name']) ?><br>
                    Serial: <?= htmlspecialchars($testDeviceResult['serial_number']) ?><br>
                    Version: <?= htmlspecialchars($testDeviceResult['version']) ?>
                    <?php else: ?>
                    <?= htmlspecialchars($testDeviceResult['message']) ?>
                    <?php endif; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <?php if ($syncResult): ?>
                <div class="alert alert-<?= $syncResult['success'] ? 'success' : 'danger' ?> alert-dismissible">
                    <strong><?= $syncResult['success'] ? 'Sync Completed!' : 'Sync Failed' ?></strong><br>
                    <?= htmlspecialchars($syncResult['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <?php if (empty($biometricDevices)): ?>
                <div class="text-center text-muted py-4">
                    <i class="bi bi-fingerprint fs-1"></i>
                    <p class="mb-0">No biometric devices configured</p>
                    <p class="small">Add a ZKTeco or Hikvision device to sync attendance</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Device Name</th>
                                <th>Type</th>
                                <th>IP Address</th>
                                <th>Port</th>
                                <th>Status</th>
                                <th>Last Sync</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($biometricDevices as $device): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($device['name']) ?></strong>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $device['device_type'] === 'zkteco' ? 'info' : 'warning' ?>">
                                        <?= strtoupper($device['device_type']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($device['ip_address']) ?></td>
                                <td><?= $device['port'] ?></td>
                                <td>
                                    <?php if ($device['is_active']): ?>
                                    <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($device['last_sync_at']): ?>
                                    <small>
                                        <?= date('M j, g:i A', strtotime($device['last_sync_at'])) ?><br>
                                        <span class="text-<?= $device['last_sync_status'] === 'success' ? 'success' : 'danger' ?>">
                                            <?= ucfirst($device['last_sync_status'] ?? 'N/A') ?>
                                        </span>
                                    </small>
                                    <?php else: ?>
                                    <small class="text-muted">Never</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-info test-connection-btn" 
                                                data-device-id="<?= $device['id'] ?>" 
                                                data-device-name="<?= htmlspecialchars($device['name']) ?>"
                                                title="Test Connection">
                                            <i class="bi bi-plug"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-success sync-device-btn" 
                                                data-device-id="<?= $device['id'] ?>" 
                                                data-device-name="<?= htmlspecialchars($device['name']) ?>"
                                                title="Sync Now">
                                            <i class="bi bi-arrow-repeat"></i>
                                        </button>
                                        <a href="?page=settings&subpage=biometric&action=map_users&id=<?= $device['id'] ?>" 
                                           class="btn btn-outline-primary" title="Map Users">
                                            <i class="bi bi-people"></i>
                                        </a>
                                        <a href="?page=settings&subpage=biometric&action=edit_device&id=<?= $device['id'] ?>" 
                                           class="btn btn-outline-secondary" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="?page=settings&subpage=biometric&action=delete_device&id=<?= $device['id'] ?>" 
                                           class="btn btn-outline-danger" title="Delete"
                                           onclick="return confirm('Are you sure you want to delete this device?')">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-info-circle"></i> Device Setup Guide</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6><i class="bi bi-fingerprint text-info"></i> ZKTeco Devices</h6>
                        <ul class="small">
                            <li>Connect device to same network as server</li>
                            <li>Default port: <code>4370</code> (UDP)</li>
                            <li>Enable network communication in device settings</li>
                            <li>Assign a static IP to the device</li>
                            <li>Supported: F18, K40, X7, ZK-MB360, etc.</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6><i class="bi bi-camera-video text-warning"></i> Hikvision Devices</h6>
                        <ul class="small">
                            <li>Uses ISAPI REST API (HTTP)</li>
                            <li>Default port: <code>80</code></li>
                            <li>Username: Usually <code>admin</code></li>
                            <li>Supports digest authentication</li>
                            <li>Supported: DS-K1T343, MinMoe Series, etc.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($action === 'add_device' || $editDevice): ?>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><?= $editDevice ? 'Edit' : 'Add' ?> Device</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="<?= $editDevice ? 'update_biometric_device' : 'add_biometric_device' ?>">
                    <?php if ($editDevice): ?>
                    <input type="hidden" name="device_id" value="<?= $editDevice['id'] ?>">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label class="form-label">Device Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" required
                               value="<?= htmlspecialchars($editDevice['name'] ?? '') ?>"
                               placeholder="e.g., Main Office Entrance">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Device Type <span class="text-danger">*</span></label>
                        <select class="form-select" name="device_type" id="deviceType" required>
                            <option value="zkteco" <?= ($editDevice['device_type'] ?? '') === 'zkteco' ? 'selected' : '' ?>>ZKTeco</option>
                            <option value="hikvision" <?= ($editDevice['device_type'] ?? '') === 'hikvision' ? 'selected' : '' ?>>Hikvision</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">IP Address <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="ip_address" required
                               value="<?= htmlspecialchars($editDevice['ip_address'] ?? '') ?>"
                               placeholder="192.168.1.201"
                               pattern="^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Port</label>
                        <input type="number" class="form-control" name="port" id="devicePort"
                               value="<?= $editDevice['port'] ?? '4370' ?>"
                               min="1" max="65535">
                        <small class="text-muted">ZKTeco: 4370, Hikvision: 80</small>
                    </div>
                    
                    <div class="mb-3" id="usernameField">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" name="username"
                               value="<?= htmlspecialchars($editDevice['username'] ?? '') ?>"
                               placeholder="admin">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" class="form-control" name="password"
                               placeholder="<?= $editDevice ? 'Leave blank to keep current' : 'Device password' ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Sync Interval (minutes)</label>
                        <input type="number" class="form-control" name="sync_interval_minutes"
                               value="<?= $editDevice['sync_interval_minutes'] ?? 15 ?>"
                               min="5" max="1440">
                    </div>
                    
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="is_active" id="deviceActive" value="1"
                               <?= ($editDevice['is_active'] ?? true) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="deviceActive">Active</label>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> <?= $editDevice ? 'Update' : 'Add' ?> Device
                        </button>
                        <a href="?page=settings&subpage=biometric" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    document.getElementById('deviceType').addEventListener('change', function() {
        var port = this.value === 'zkteco' ? 4370 : 80;
        document.getElementById('devicePort').value = port;
    });
    </script>
    <?php endif; ?>
    
    <?php if ($action === 'map_users' && $id): ?>
    <?php
    $deviceUsers = $biometricService->getDeviceUsers($id);
    $mappings = $biometricService->getUserMappings($id);
    $employees = (new \App\Employee($db))->getAllEmployees();
    $mappedDeviceUsers = array_column($mappings, 'device_user_id');
    ?>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-people"></i> User Mappings</h5>
            </div>
            <div class="card-body">
                <p class="small text-muted">Map device users to employees to sync their attendance.</p>
                
                <?php if (!empty($mappings)): ?>
                <h6>Current Mappings</h6>
                <div class="list-group mb-3">
                    <?php foreach ($mappings as $map): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-muted">Device: <?= htmlspecialchars($map['device_user_id']) ?></small><br>
                            <strong><?= htmlspecialchars($map['employee_name'] ?? 'Unknown') ?></strong>
                            <small class="text-muted">(<?= htmlspecialchars($map['employee_code'] ?? '') ?>)</small>
                        </div>
                        <a href="?page=settings&subpage=biometric&action=delete_mapping&device_id=<?= $id ?>&device_user_id=<?= urlencode($map['device_user_id']) ?>" 
                           class="btn btn-sm btn-outline-danger"
                           onclick="return confirm('Remove this mapping?')">
                            <i class="bi bi-x"></i>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <h6>Add Mapping</h6>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="save_user_mapping">
                    <input type="hidden" name="device_id" value="<?= $id ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Device User ID</label>
                        <?php if (!empty($deviceUsers)): ?>
                        <select class="form-select" name="device_user_id" required>
                            <option value="">Select user from device...</option>
                            <?php foreach ($deviceUsers as $du): ?>
                            <?php if (!in_array($du['device_user_id'], $mappedDeviceUsers)): ?>
                            <option value="<?= htmlspecialchars($du['device_user_id']) ?>">
                                <?= htmlspecialchars($du['device_user_id']) ?> - <?= htmlspecialchars($du['name'] ?: 'No Name') ?>
                            </option>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                        <?php else: ?>
                        <input type="text" class="form-control" name="device_user_id" required placeholder="e.g., 001">
                        <small class="text-muted">Could not fetch users from device. Enter manually.</small>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Employee</label>
                        <select class="form-select" name="employee_id" required>
                            <option value="">Select employee...</option>
                            <?php foreach ($employees as $emp): ?>
                            <option value="<?= $emp['id'] ?>">
                                <?= htmlspecialchars($emp['name']) ?> (<?= htmlspecialchars($emp['employee_id']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-link"></i> Add Mapping
                    </button>
                    <a href="?page=settings&subpage=biometric" class="btn btn-outline-secondary btn-sm">Close</a>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="modal fade" id="testConnectionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plug"></i> Test Biometric Connection</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="testConnectionLoading" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Testing...</span>
                    </div>
                    <p class="mt-2 mb-0">Testing connection to <strong id="testDeviceName"></strong>...</p>
                </div>
                <div id="testConnectionResult" class="d-none">
                    <div id="testSuccess" class="d-none">
                        <div class="alert alert-success mb-3">
                            <i class="bi bi-check-circle-fill"></i> Connection Successful!
                        </div>
                        <table class="table table-sm mb-0">
                            <tr>
                                <th width="40%">Device Name</th>
                                <td id="resultDeviceName">-</td>
                            </tr>
                            <tr>
                                <th>Serial Number</th>
                                <td id="resultSerial">-</td>
                            </tr>
                            <tr>
                                <th>Firmware Version</th>
                                <td id="resultVersion">-</td>
                            </tr>
                        </table>
                    </div>
                    <div id="testFailed" class="d-none">
                        <div class="alert alert-danger mb-0">
                            <i class="bi bi-x-circle-fill"></i> Connection Failed
                            <p class="mb-0 mt-2 small" id="testErrorMessage"></p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="syncDeviceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-arrow-repeat"></i> Sync Biometric Device</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="syncLoading" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Syncing...</span>
                    </div>
                    <p class="mt-2 mb-0">Syncing attendance from <strong id="syncDeviceName"></strong>...</p>
                    <p class="small text-muted">This may take a few minutes...</p>
                </div>
                <div id="syncResult" class="d-none">
                    <div id="syncSuccess" class="d-none">
                        <div class="alert alert-success mb-3">
                            <i class="bi bi-check-circle-fill"></i> Sync Completed!
                        </div>
                        <table class="table table-sm mb-0">
                            <tr>
                                <th width="50%">Records Synced</th>
                                <td id="resultRecordsSynced">0</td>
                            </tr>
                            <tr>
                                <th>Records Processed</th>
                                <td id="resultRecordsProcessed">0</td>
                            </tr>
                            <tr>
                                <th>Message</th>
                                <td id="resultSyncMessage">-</td>
                            </tr>
                        </table>
                    </div>
                    <div id="syncFailed" class="d-none">
                        <div class="alert alert-danger mb-0">
                            <i class="bi bi-x-circle-fill"></i> Sync Failed
                            <p class="mb-0 mt-2 small" id="syncErrorMessage"></p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.test-connection-btn').forEach(btn => {
    btn.addEventListener('click', async function() {
        const deviceId = this.dataset.deviceId;
        const deviceName = this.dataset.deviceName;
        
        document.getElementById('testDeviceName').textContent = deviceName;
        document.getElementById('testConnectionLoading').classList.remove('d-none');
        document.getElementById('testConnectionResult').classList.add('d-none');
        document.getElementById('testSuccess').classList.add('d-none');
        document.getElementById('testFailed').classList.add('d-none');
        
        const modal = new bootstrap.Modal(document.getElementById('testConnectionModal'));
        modal.show();
        
        try {
            const response = await fetch(`?page=api&action=test_biometric_device&device_id=${deviceId}`);
            const result = await response.json();
            
            document.getElementById('testConnectionLoading').classList.add('d-none');
            document.getElementById('testConnectionResult').classList.remove('d-none');
            
            if (result.success) {
                document.getElementById('testSuccess').classList.remove('d-none');
                document.getElementById('resultDeviceName').textContent = result.device_name || '-';
                document.getElementById('resultSerial').textContent = result.serial_number || '-';
                document.getElementById('resultVersion').textContent = result.version || '-';
            } else {
                document.getElementById('testFailed').classList.remove('d-none');
                document.getElementById('testErrorMessage').textContent = result.message || 'Unknown error';
            }
        } catch (error) {
            document.getElementById('testConnectionLoading').classList.add('d-none');
            document.getElementById('testConnectionResult').classList.remove('d-none');
            document.getElementById('testFailed').classList.remove('d-none');
            document.getElementById('testErrorMessage').textContent = 'Network error: ' + error.message;
        }
    });
});

document.querySelectorAll('.sync-device-btn').forEach(btn => {
    btn.addEventListener('click', async function() {
        const deviceId = this.dataset.deviceId;
        const deviceName = this.dataset.deviceName;
        
        document.getElementById('syncDeviceName').textContent = deviceName;
        document.getElementById('syncLoading').classList.remove('d-none');
        document.getElementById('syncResult').classList.add('d-none');
        document.getElementById('syncSuccess').classList.add('d-none');
        document.getElementById('syncFailed').classList.add('d-none');
        
        const modal = new bootstrap.Modal(document.getElementById('syncDeviceModal'));
        modal.show();
        
        try {
            const response = await fetch(`?page=api&action=sync_biometric_device&device_id=${deviceId}`);
            const result = await response.json();
            
            document.getElementById('syncLoading').classList.add('d-none');
            document.getElementById('syncResult').classList.remove('d-none');
            
            if (result.success) {
                document.getElementById('syncSuccess').classList.remove('d-none');
                document.getElementById('resultRecordsSynced').textContent = result.records_synced || 0;
                document.getElementById('resultRecordsProcessed').textContent = result.records_processed || 0;
                document.getElementById('resultSyncMessage').textContent = result.message || 'Sync completed';
            } else {
                document.getElementById('syncFailed').classList.remove('d-none');
                document.getElementById('syncErrorMessage').textContent = result.message || 'Unknown error';
            }
        } catch (error) {
            document.getElementById('syncLoading').classList.add('d-none');
            document.getElementById('syncResult').classList.remove('d-none');
            document.getElementById('syncFailed').classList.remove('d-none');
            document.getElementById('syncErrorMessage').textContent = 'Network error: ' + error.message;
        }
    });
});
</script>

<?php elseif ($subpage === 'late_rules'): ?>

<?php
$editRule = null;
if ($action === 'edit_rule' && $id) {
    $editRule = $lateCalculator->getRule($id);
    if ($editRule && is_string($editRule['deduction_tiers'])) {
        $editRule['deduction_tiers'] = json_decode($editRule['deduction_tiers'], true);
    }
}
?>

<div class="row g-4">
    <div class="col-md-<?= ($action === 'add_rule' || $editRule) ? '7' : '12' ?>">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-clock-history"></i> Late Arrival Deduction Rules</h5>
                <a href="?page=settings&subpage=late_rules&action=add_rule" class="btn btn-sm btn-primary">
                    <i class="bi bi-plus-lg"></i> Add Rule
                </a>
            </div>
            <div class="card-body">
                <p class="text-muted small">
                    Configure automatic deductions for employees who arrive late. Rules can apply to specific departments or as a default for all employees.
                </p>
                
                <?php if (empty($lateRules)): ?>
                <div class="text-center text-muted py-4">
                    <i class="bi bi-clock-history fs-1"></i>
                    <p class="mb-0">No late deduction rules configured</p>
                    <p class="small">Add a rule to automatically calculate deductions for late arrivals</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Rule Name</th>
                                <th>Work Start</th>
                                <th>Grace Period</th>
                                <th>Applies To</th>
                                <th>Deduction Tiers</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lateRules as $rule): ?>
                            <?php 
                            $tiers = is_string($rule['deduction_tiers']) ? json_decode($rule['deduction_tiers'], true) : $rule['deduction_tiers'];
                            ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($rule['name']) ?></strong>
                                    <?php if ($rule['is_default']): ?>
                                    <span class="badge bg-primary">Default</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('g:i A', strtotime($rule['work_start_time'])) ?></td>
                                <td><?= $rule['grace_minutes'] ?> min</td>
                                <td>
                                    <?php if ($rule['department_name']): ?>
                                    <span class="badge bg-info"><?= htmlspecialchars($rule['department_name']) ?></span>
                                    <?php else: ?>
                                    <span class="text-muted">All Departments</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small>
                                    <?php if (!empty($tiers)): ?>
                                        <?php foreach ($tiers as $tier): ?>
                                        <?= $tier['min_minutes'] ?? 0 ?>-<?= $tier['max_minutes'] ?? '∞' ?> min: <?= $rule['currency'] ?> <?= number_format($tier['amount'] ?? 0) ?><br>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="text-muted">No tiers</span>
                                    <?php endif; ?>
                                    </small>
                                </td>
                                <td>
                                    <?php if ($rule['is_active']): ?>
                                    <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="?page=settings&subpage=late_rules&action=edit_rule&id=<?= $rule['id'] ?>" 
                                           class="btn btn-outline-secondary" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="?page=settings&subpage=late_rules&action=delete_rule&id=<?= $rule['id'] ?>" 
                                           class="btn btn-outline-danger" title="Delete"
                                           onclick="return confirm('Are you sure you want to delete this rule?')">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-lightbulb"></i> How Late Deductions Work</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Calculation Process</h6>
                        <ol class="small">
                            <li>Employee clocks in via biometric device</li>
                            <li>System compares clock-in time to work start time</li>
                            <li>If late beyond grace period, late minutes are calculated</li>
                            <li>Deduction amount is determined from tier rules</li>
                            <li>Monthly deductions are applied to payroll</li>
                        </ol>
                    </div>
                    <div class="col-md-6">
                        <h6>Example Configuration</h6>
                        <ul class="small">
                            <li>Work Start: <strong>9:00 AM</strong></li>
                            <li>Grace Period: <strong>15 minutes</strong></li>
                            <li>15-30 min late: <strong>KES 100</strong></li>
                            <li>31-60 min late: <strong>KES 200</strong></li>
                            <li>61+ min late: <strong>KES 500</strong></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($action === 'add_rule' || $editRule): ?>
    <div class="col-md-5">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><?= $editRule ? 'Edit' : 'Add' ?> Late Deduction Rule</h5>
            </div>
            <div class="card-body">
                <form method="POST" id="lateRuleForm">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="<?= $editRule ? 'update_late_rule' : 'add_late_rule' ?>">
                    <?php if ($editRule): ?>
                    <input type="hidden" name="rule_id" value="<?= $editRule['id'] ?>">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label class="form-label">Rule Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" required
                               value="<?= htmlspecialchars($editRule['name'] ?? '') ?>"
                               placeholder="e.g., Standard Late Policy">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Work Start Time <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" name="work_start_time" required
                                   value="<?= $editRule['work_start_time'] ?? '09:00' ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Grace Period (min)</label>
                            <input type="number" class="form-control" name="grace_minutes"
                                   value="<?= $editRule['grace_minutes'] ?? 15 ?>"
                                   min="0" max="120">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Currency</label>
                            <select class="form-select" name="currency">
                                <option value="KES" <?= ($editRule['currency'] ?? 'KES') === 'KES' ? 'selected' : '' ?>>KES</option>
                                <option value="USD" <?= ($editRule['currency'] ?? '') === 'USD' ? 'selected' : '' ?>>USD</option>
                                <option value="EUR" <?= ($editRule['currency'] ?? '') === 'EUR' ? 'selected' : '' ?>>EUR</option>
                                <option value="GBP" <?= ($editRule['currency'] ?? '') === 'GBP' ? 'selected' : '' ?>>GBP</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Apply to Department</label>
                            <select class="form-select" name="apply_to_department_id">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                <option value="<?= $dept['id'] ?>" <?= ($editRule['apply_to_department_id'] ?? '') == $dept['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($dept['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Deduction Tiers</label>
                        <div id="deductionTiers">
                            <?php 
                            $tiers = $editRule['deduction_tiers'] ?? [
                                ['min_minutes' => 16, 'max_minutes' => 30, 'amount' => 100],
                                ['min_minutes' => 31, 'max_minutes' => 60, 'amount' => 200],
                                ['min_minutes' => 61, 'max_minutes' => 9999, 'amount' => 500]
                            ];
                            foreach ($tiers as $i => $tier): 
                            ?>
                            <div class="tier-row mb-2">
                                <div class="input-group input-group-sm">
                                    <input type="number" class="form-control" name="tier_min[]" 
                                           value="<?= $tier['min_minutes'] ?? 0 ?>" placeholder="From (min)" min="0">
                                    <span class="input-group-text">-</span>
                                    <input type="number" class="form-control" name="tier_max[]" 
                                           value="<?= $tier['max_minutes'] ?? 9999 ?>" placeholder="To (min)" min="0">
                                    <span class="input-group-text">min =</span>
                                    <input type="number" class="form-control" name="tier_amount[]" 
                                           value="<?= $tier['amount'] ?? 0 ?>" placeholder="Amount" min="0" step="0.01">
                                    <button type="button" class="btn btn-outline-danger remove-tier" onclick="this.closest('.tier-row').remove()">
                                        <i class="bi bi-x"></i>
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="btn btn-outline-secondary btn-sm mt-2" onclick="addTierRow()">
                            <i class="bi bi-plus"></i> Add Tier
                        </button>
                    </div>
                    
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="is_default" id="isDefault" value="1"
                               <?= ($editRule['is_default'] ?? false) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="isDefault">Set as Default Rule</label>
                        <small class="text-muted d-block">Applies to employees without department-specific rules</small>
                    </div>
                    
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="is_active" id="isActive" value="1"
                               <?= ($editRule['is_active'] ?? true) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="isActive">Active</label>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> <?= $editRule ? 'Update' : 'Add' ?> Rule
                        </button>
                        <a href="?page=settings&subpage=late_rules" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    function addTierRow() {
        var container = document.getElementById('deductionTiers');
        var row = document.createElement('div');
        row.className = 'tier-row mb-2';
        row.innerHTML = '<div class="input-group input-group-sm">' +
            '<input type="number" class="form-control" name="tier_min[]" placeholder="From (min)" min="0">' +
            '<span class="input-group-text">-</span>' +
            '<input type="number" class="form-control" name="tier_max[]" placeholder="To (min)" min="0">' +
            '<span class="input-group-text">min =</span>' +
            '<input type="number" class="form-control" name="tier_amount[]" placeholder="Amount" min="0" step="0.01">' +
            '<button type="button" class="btn btn-outline-danger remove-tier" onclick="this.closest(\'.tier-row\').remove()">' +
            '<i class="bi bi-x"></i></button></div>';
        container.appendChild(row);
    }
    </script>
    <?php endif; ?>
</div>

<?php elseif ($subpage === 'packages'): ?>

<?php
$packages = $settings->getAllPackages();
$billingCycles = $settings->getBillingCycles();
$packageIcons = $settings->getPackageIcons();
$editPackage = null;
if ($action === 'edit_package' && $id) {
    $editPackage = $settings->getPackage($id);
}
?>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-box-seam"></i> Service Packages</h5>
                <a href="?page=landing" target="_blank" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-eye"></i> Preview Landing Page
                </a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($packages)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-box-seam display-4"></i>
                    <p class="mt-3">No packages yet. Create your first package!</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Order</th>
                                <th>Name</th>
                                <th>Speed</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($packages as $pkg): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-secondary"><?= $pkg['display_order'] ?></span>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($pkg['name']) ?></strong>
                                    <?php if ($pkg['is_popular']): ?>
                                    <span class="badge bg-warning text-dark ms-1">Popular</span>
                                    <?php endif; ?>
                                    <?php if (!empty($pkg['badge_text'])): ?>
                                    <span class="badge bg-info ms-1"><?= htmlspecialchars($pkg['badge_text']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($pkg['speed']) ?> <?= htmlspecialchars($pkg['speed_unit']) ?></td>
                                <td><?= htmlspecialchars($pkg['currency']) ?> <?= number_format($pkg['price'], 2) ?>/<?= $pkg['billing_cycle'] ?></td>
                                <td>
                                    <?php if ($pkg['is_active']): ?>
                                    <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="?page=settings&subpage=packages&action=edit_package&id=<?= $pkg['id'] ?>" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this package?')">
                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                        <input type="hidden" name="action" value="delete_package">
                                        <input type="hidden" name="package_id" value="<?= $pkg['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
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
            <div class="card-header bg-white">
                <h5 class="mb-0">
                    <i class="bi bi-<?= $editPackage ? 'pencil' : 'plus-circle' ?>"></i>
                    <?= $editPackage ? 'Edit' : 'Add' ?> Package
                </h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="<?= $editPackage ? 'update_package' : 'create_package' ?>">
                    <?php if ($editPackage): ?>
                    <input type="hidden" name="package_id" value="<?= $editPackage['id'] ?>">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label class="form-label">Package Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" required
                               value="<?= htmlspecialchars($editPackage['name'] ?? '') ?>"
                               placeholder="e.g., Home Basic, Business Pro">
                    </div>
                    
                    <div class="row">
                        <div class="col-8 mb-3">
                            <label class="form-label">Speed <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="speed" required
                                   value="<?= htmlspecialchars($editPackage['speed'] ?? '') ?>"
                                   placeholder="e.g., 10, 50, 100">
                        </div>
                        <div class="col-4 mb-3">
                            <label class="form-label">Unit</label>
                            <select class="form-select" name="speed_unit">
                                <option value="Mbps" <?= ($editPackage['speed_unit'] ?? 'Mbps') === 'Mbps' ? 'selected' : '' ?>>Mbps</option>
                                <option value="Gbps" <?= ($editPackage['speed_unit'] ?? '') === 'Gbps' ? 'selected' : '' ?>>Gbps</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Price <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="price" required step="0.01" min="0"
                                   value="<?= $editPackage['price'] ?? '' ?>"
                                   placeholder="e.g., 2500">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Currency</label>
                            <select class="form-select" name="currency">
                                <?php foreach ($currencies as $code => $info): ?>
                                <option value="<?= $code ?>" <?= ($editPackage['currency'] ?? 'KES') === $code ? 'selected' : '' ?>>
                                    <?= $code ?> (<?= $info['symbol'] ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Billing Cycle</label>
                        <select class="form-select" name="billing_cycle">
                            <?php foreach ($billingCycles as $value => $label): ?>
                            <option value="<?= $value ?>" <?= ($editPackage['billing_cycle'] ?? 'monthly') === $value ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="2"
                                  placeholder="Brief description of the package"><?= htmlspecialchars($editPackage['description'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Features (one per line)</label>
                        <textarea class="form-control" name="features_text" rows="4"
                                  placeholder="Unlimited data&#10;Free router&#10;24/7 support"><?php
                            if (!empty($editPackage['features']) && is_array($editPackage['features'])) {
                                echo htmlspecialchars(implode("\n", $editPackage['features']));
                            }
                        ?></textarea>
                        <small class="text-muted">Enter each feature on a new line</small>
                    </div>
                    
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Icon</label>
                            <select class="form-select" name="icon">
                                <?php foreach ($packageIcons as $value => $label): ?>
                                <option value="<?= $value ?>" <?= ($editPackage['icon'] ?? 'wifi') === $value ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Display Order</label>
                            <input type="number" class="form-control" name="display_order" min="0"
                                   value="<?= $editPackage['display_order'] ?? 0 ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Badge Text</label>
                            <input type="text" class="form-control" name="badge_text"
                                   value="<?= htmlspecialchars($editPackage['badge_text'] ?? '') ?>"
                                   placeholder="e.g., Best Value">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Badge Color</label>
                            <input type="color" class="form-control form-control-color w-100" name="badge_color"
                                   value="<?= htmlspecialchars($editPackage['badge_color'] ?? '#2563eb') ?>">
                        </div>
                    </div>
                    
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="is_popular" id="isPopular" value="1"
                               <?= ($editPackage['is_popular'] ?? false) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="isPopular">Mark as Popular</label>
                    </div>
                    
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="is_active" id="isPackageActive" value="1"
                               <?= ($editPackage['is_active'] ?? true) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="isPackageActive">Active (show on landing page)</label>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> <?= $editPackage ? 'Update' : 'Create' ?> Package
                        </button>
                        <?php if ($editPackage): ?>
                        <a href="?page=settings&subpage=packages" class="btn btn-outline-secondary">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php elseif ($subpage === 'landing'): ?>

<?php
$landingSettings = $settings->getLandingPageSettings();
?>

<div class="row g-4">
    <div class="col-lg-8">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="save_landing_settings">
            
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-stars"></i> Hero Section</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Hero Title</label>
                        <input type="text" class="form-control" name="landing_hero_title"
                               value="<?= htmlspecialchars($landingSettings['hero_title']) ?>"
                               placeholder="Lightning Fast Internet">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Hero Subtitle</label>
                        <textarea class="form-control" name="landing_hero_subtitle" rows="2"
                                  placeholder="Experience blazing fast fiber internet..."><?= htmlspecialchars($landingSettings['hero_subtitle']) ?></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">CTA Button Text</label>
                            <input type="text" class="form-control" name="landing_hero_cta"
                                   value="<?= htmlspecialchars($landingSettings['hero_cta_text']) ?>"
                                   placeholder="Get Started">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">CTA Button Link</label>
                            <input type="text" class="form-control" name="landing_hero_cta_link"
                                   value="<?= htmlspecialchars($landingSettings['hero_cta_link']) ?>"
                                   placeholder="#packages">
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-info-circle"></i> About Section</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Section Title</label>
                        <input type="text" class="form-control" name="landing_about_title"
                               value="<?= htmlspecialchars($landingSettings['about_title']) ?>"
                               placeholder="Why Choose Us?">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Section Description</label>
                        <textarea class="form-control" name="landing_about_description" rows="3"><?= htmlspecialchars($landingSettings['about_description']) ?></textarea>
                    </div>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-palette"></i> Appearance</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Primary Color</label>
                            <input type="color" class="form-control form-control-color w-100" name="landing_primary_color"
                                   value="<?= htmlspecialchars($landingSettings['primary_color']) ?>">
                        </div>
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Footer Text</label>
                            <input type="text" class="form-control" name="landing_footer_text"
                                   value="<?= htmlspecialchars($landingSettings['footer_text']) ?>"
                                   placeholder="Your trusted partner for fast, reliable internet.">
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg"></i> Save Settings
                </button>
                <a href="?page=landing" target="_blank" class="btn btn-outline-secondary">
                    <i class="bi bi-eye"></i> Preview Landing Page
                </a>
            </div>
        </form>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-lightbulb"></i> Tips</h5>
            </div>
            <div class="card-body">
                <ul class="mb-0">
                    <li class="mb-2">The landing page is public and doesn't require login</li>
                    <li class="mb-2">Access it at: <code>/?page=landing</code> or the homepage</li>
                    <li class="mb-2">Add packages in the "Service Packages" tab</li>
                    <li class="mb-2">Company info (phone, email, address) is taken from Company Settings</li>
                    <li class="mb-2">Only active packages are displayed</li>
                    <li class="mb-2">Use the display order to control package arrangement</li>
                </ul>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-link-45deg"></i> Landing Page URL</h5>
            </div>
            <div class="card-body">
                <p class="text-muted mb-2">Share this link with your customers:</p>
                <div class="input-group">
                    <input type="text" class="form-control" id="landingUrl" readonly
                           value="<?= (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] ?>/?page=landing">
                    <button class="btn btn-outline-secondary" type="button" onclick="copyLandingUrl()">
                        <i class="bi bi-clipboard"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function copyLandingUrl() {
    var urlInput = document.getElementById('landingUrl');
    urlInput.select();
    document.execCommand('copy');
    alert('URL copied to clipboard!');
}
</script>

<?php elseif ($subpage === 'mpesa'): ?>
<?php
$mpesa = new \App\Mpesa();
$mpesaConfig = $mpesa->getConfig();
?>

<div class="row">
    <div class="col-lg-8">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="save_mpesa_settings">
            
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-phone"></i> M-Pesa Daraja API Configuration</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Get your credentials from 
                        <a href="https://developer.safaricom.co.ke/" target="_blank">Safaricom Daraja Portal</a>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="bi bi-shield-lock"></i> <strong>Security Tip:</strong> 
                        For production, use environment variables (MPESA_CONSUMER_KEY, MPESA_CONSUMER_SECRET, MPESA_PASSKEY, MPESA_SHORTCODE) instead of storing credentials here.
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Environment</label>
                            <select class="form-select" name="mpesa_environment">
                                <option value="sandbox" <?= ($mpesaConfig['mpesa_environment'] ?? 'sandbox') === 'sandbox' ? 'selected' : '' ?>>Sandbox (Testing)</option>
                                <option value="production" <?= ($mpesaConfig['mpesa_environment'] ?? '') === 'production' ? 'selected' : '' ?>>Production (Live)</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Business Shortcode (Paybill/Till)</label>
                            <input type="text" class="form-control" name="mpesa_shortcode" 
                                   value="<?= htmlspecialchars($mpesaConfig['mpesa_shortcode'] ?? '174379') ?>"
                                   placeholder="174379">
                            <div class="form-text">Use 174379 for sandbox testing</div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Consumer Key *</label>
                            <input type="text" class="form-control" name="mpesa_consumer_key" 
                                   value="<?= htmlspecialchars($mpesaConfig['mpesa_consumer_key'] ?? '') ?>"
                                   placeholder="Your Consumer Key">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Consumer Secret *</label>
                            <input type="password" class="form-control" name="mpesa_consumer_secret" 
                                   value="<?= htmlspecialchars($mpesaConfig['mpesa_consumer_secret'] ?? '') ?>"
                                   placeholder="Your Consumer Secret">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Passkey</label>
                        <input type="text" class="form-control" name="mpesa_passkey" 
                               value="<?= htmlspecialchars($mpesaConfig['mpesa_passkey'] ?? 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919') ?>"
                               placeholder="Lipa na M-Pesa Passkey">
                        <div class="form-text">Sandbox passkey is pre-filled. Get production passkey from Safaricom after going live.</div>
                    </div>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-link-45deg"></i> Callback URLs</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">These URLs receive payment notifications from M-Pesa. They are auto-generated but can be customized.</p>
                    
                    <div class="mb-3">
                        <label class="form-label">STK Push Callback URL</label>
                        <input type="url" class="form-control" name="mpesa_callback_url" 
                               value="<?= htmlspecialchars($mpesaConfig['mpesa_callback_url'] ?? $mpesa->getCallbackUrl()) ?>">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">C2B Validation URL</label>
                            <input type="url" class="form-control" name="mpesa_validation_url" 
                                   value="<?= htmlspecialchars($mpesaConfig['mpesa_validation_url'] ?? $mpesa->getValidationUrl()) ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">C2B Confirmation URL</label>
                            <input type="url" class="form-control" name="mpesa_confirmation_url" 
                                   value="<?= htmlspecialchars($mpesaConfig['mpesa_confirmation_url'] ?? $mpesa->getConfirmationUrl()) ?>">
                        </div>
                    </div>
                    
                    <?php 
                    $c2bRegistered = $mpesaConfig['c2b_urls_registered'] ?? null;
                    ?>
                    <?php if ($c2bRegistered): ?>
                    <div class="alert alert-success py-2 small mb-3">
                        <i class="bi bi-check-circle"></i> C2B URLs registered on <?= htmlspecialchars($c2bRegistered) ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg"></i> Save M-Pesa Settings
                </button>
                <button type="submit" formaction="?page=settings&subpage=mpesa&action=register_c2b" class="btn btn-outline-primary" <?= !$mpesa->isConfigured() ? 'disabled' : '' ?>>
                    <i class="bi bi-link-45deg"></i> Register C2B URLs
                </button>
                <a href="?page=payments" class="btn btn-outline-success">
                    <i class="bi bi-arrow-right"></i> Go to Payments
                </a>
            </div>
        </form>
    </div>
    
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-shield-check"></i> Connection Status</h5>
            </div>
            <div class="card-body">
                <?php if ($mpesa->isConfigured()): ?>
                    <?php 
                    $token = $mpesa->getAccessToken();
                    if ($token): 
                    ?>
                    <div class="alert alert-success mb-0">
                        <i class="bi bi-check-circle"></i> <strong>Connected!</strong><br>
                        <small>Successfully authenticated with M-Pesa API</small>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-danger mb-0">
                        <i class="bi bi-x-circle"></i> <strong>Connection Failed</strong><br>
                        <small>Check your Consumer Key and Secret</small>
                    </div>
                    <?php endif; ?>
                <?php else: ?>
                <div class="alert alert-warning mb-0">
                    <i class="bi bi-exclamation-triangle"></i> <strong>Not Configured</strong><br>
                    <small>Enter your API credentials to enable M-Pesa</small>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-book"></i> Quick Guide</h5>
            </div>
            <div class="card-body small">
                <ol class="mb-0">
                    <li class="mb-2">Register at <a href="https://developer.safaricom.co.ke/" target="_blank">Daraja Portal</a></li>
                    <li class="mb-2">Create a new app and select "Lipa na M-Pesa Sandbox"</li>
                    <li class="mb-2">Copy Consumer Key and Secret here</li>
                    <li class="mb-2">Test with sandbox phone: <code>254708374149</code></li>
                    <li class="mb-2">For production, apply for "Go Live" on Daraja</li>
                </ol>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-bug"></i> Test Credentials</h5>
            </div>
            <div class="card-body small">
                <p class="text-muted mb-2">Sandbox test values:</p>
                <ul class="mb-0">
                    <li>Shortcode: <code>174379</code></li>
                    <li>Phone: <code>254708374149</code></li>
                    <li>Passkey: <code>bfb279...1ed2c919</code> (pre-filled)</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php elseif ($subpage === 'sales'): ?>
<?php
$commissionSettings = [
    'default_commission_type' => $settings->getSetting('default_commission_type') ?? 'percentage',
    'default_commission_value' => $settings->getSetting('default_commission_value') ?? '10',
    'min_order_amount' => $settings->getSetting('min_commission_order_amount') ?? '0',
    'auto_mark_paid' => $settings->getSetting('auto_mark_commission_paid') ?? '0'
];
?>

<div class="row g-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-percent"></i> Commission Settings</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="save_commission_settings">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Default Commission Type</label>
                            <select name="default_commission_type" class="form-select" id="commissionType">
                                <option value="percentage" <?= $commissionSettings['default_commission_type'] === 'percentage' ? 'selected' : '' ?>>
                                    Percentage (%)
                                </option>
                                <option value="fixed" <?= $commissionSettings['default_commission_type'] === 'fixed' ? 'selected' : '' ?>>
                                    Fixed Amount (KES)
                                </option>
                            </select>
                            <small class="text-muted">Applied to new salespersons by default</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Default Commission Value</label>
                            <div class="input-group">
                                <span class="input-group-text" id="commissionPrefix">
                                    <?= $commissionSettings['default_commission_type'] === 'percentage' ? '%' : 'KES' ?>
                                </span>
                                <input type="number" step="0.01" name="default_commission_value" class="form-control" 
                                       value="<?= htmlspecialchars($commissionSettings['default_commission_value']) ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Minimum Order Amount for Commission</label>
                            <div class="input-group">
                                <span class="input-group-text">KES</span>
                                <input type="number" step="0.01" name="min_commission_order_amount" class="form-control" 
                                       value="<?= htmlspecialchars($commissionSettings['min_order_amount']) ?>">
                            </div>
                            <small class="text-muted">Orders below this amount won't earn commission (0 = no minimum)</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Auto-mark Commission as Paid</label>
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="auto_mark_commission_paid" id="autoMarkPaid"
                                       <?= $commissionSettings['auto_mark_paid'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="autoMarkPaid">
                                    Automatically mark commission as paid when order is paid
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> Save Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-info-circle"></i> About Commissions</h5>
            </div>
            <div class="card-body small">
                <p class="mb-2">Commissions are calculated when orders are created with a salesperson assigned.</p>
                <ul class="mb-0">
                    <li><strong>Percentage:</strong> Commission = Order Amount x Rate%</li>
                    <li><strong>Fixed:</strong> Flat amount per order</li>
                </ul>
                <hr>
                <p class="mb-0 text-muted">
                    <i class="bi bi-lightbulb"></i> Individual salesperson commission rates can be set in HR &gt; Salespeople tab.
                </p>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-bar-chart"></i> Quick Stats</h5>
            </div>
            <div class="card-body">
                <?php
                $salespersonModel = new \App\Salesperson($dbConn);
                $allSalespersons = $salespersonModel->getAll();
                $activeSp = array_filter($allSalespersons, fn($s) => $s['is_active']);
                $totalPendingComm = 0;
                $totalPaidComm = 0;
                foreach ($allSalespersons as $sp) {
                    $stats = $salespersonModel->getSalesStats($sp['id']);
                    $totalPendingComm += $stats['pending_commission'] ?? 0;
                    $totalPaidComm += $stats['paid_commission'] ?? 0;
                }
                ?>
                <div class="row text-center">
                    <div class="col-6 mb-3">
                        <h4 class="mb-0 text-primary"><?= count($activeSp) ?></h4>
                        <small class="text-muted">Active Salespeople</small>
                    </div>
                    <div class="col-6 mb-3">
                        <h4 class="mb-0 text-warning">KES <?= number_format($totalPendingComm, 0) ?></h4>
                        <small class="text-muted">Pending Commission</small>
                    </div>
                    <div class="col-12">
                        <h4 class="mb-0 text-success">KES <?= number_format($totalPaidComm, 0) ?></h4>
                        <small class="text-muted">Total Paid Commission</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('commissionType').addEventListener('change', function() {
    document.getElementById('commissionPrefix').textContent = this.value === 'percentage' ? '%' : 'KES';
});
</script>

<?php elseif ($subpage === 'mobile'): ?>

<?php $mobileSettings = $settings->getMobileAppSettings(); ?>

<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-phone"></i> Mobile App Settings</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="save_mobile_settings">
                    
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i> 
                                Mobile app URL: <strong><?= htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'your-domain.com')) ?>/mobile/</strong>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">App Name</label>
                            <input type="text" name="mobile_app_name" class="form-control" 
                                   value="<?= htmlspecialchars($mobileSettings['mobile_app_name']) ?>" 
                                   placeholder="ISP Mobile">
                            <small class="text-muted">Displayed on the mobile app and when installed on devices</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Token Expiry (Days)</label>
                            <input type="number" name="mobile_token_expiry_days" class="form-control" 
                                   value="<?= htmlspecialchars($mobileSettings['mobile_token_expiry_days']) ?>" 
                                   min="1" max="365">
                            <small class="text-muted">How long users stay logged in before needing to re-authenticate</small>
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    <h6 class="mb-3"><i class="bi bi-toggles"></i> Feature Access</h6>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="mobile_enabled" id="mobileEnabled" value="1"
                                       <?= $mobileSettings['mobile_enabled'] === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="mobileEnabled">
                                    <strong>Enable Mobile App</strong>
                                </label>
                            </div>
                            <small class="text-muted">Master switch to enable/disable mobile app access</small>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="mobile_salesperson_enabled" id="salespersonEnabled" value="1"
                                       <?= $mobileSettings['mobile_salesperson_enabled'] === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="salespersonEnabled">
                                    <strong>Salesperson Access</strong>
                                </label>
                            </div>
                            <small class="text-muted">Allow salespersons to use the mobile app</small>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="mobile_technician_enabled" id="technicianEnabled" value="1"
                                       <?= $mobileSettings['mobile_technician_enabled'] === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="technicianEnabled">
                                    <strong>Technician Access</strong>
                                </label>
                            </div>
                            <small class="text-muted">Allow technicians to use the mobile app</small>
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    <h6 class="mb-3"><i class="bi bi-shield-check"></i> Security Options</h6>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="mobile_require_location" id="requireLocation" value="1"
                                       <?= $mobileSettings['mobile_require_location'] === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="requireLocation">
                                    <strong>Require Location for Attendance</strong>
                                </label>
                            </div>
                            <small class="text-muted">Technicians must share location when clocking in/out</small>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="mobile_allow_offline" id="allowOffline" value="1"
                                       <?= $mobileSettings['mobile_allow_offline'] === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="allowOffline">
                                    <strong>Allow Offline Mode</strong>
                                </label>
                            </div>
                            <small class="text-muted">Allow basic viewing when internet is unavailable</small>
                        </div>
                    </div>
                    
                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> Save Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-info-circle"></i> About Mobile App</h5>
            </div>
            <div class="card-body small">
                <p class="mb-2">The mobile app (PWA) provides field access for:</p>
                <ul class="mb-3">
                    <li><strong>Salespersons:</strong> Create orders, view commissions, manage leads</li>
                    <li><strong>Technicians:</strong> View tickets, clock in/out, manage equipment</li>
                </ul>
                <hr>
                <p class="mb-2"><strong>Installation:</strong></p>
                <ol class="mb-0 ps-3">
                    <li>Open <code>/mobile/</code> on a phone browser</li>
                    <li>Tap "Add to Home Screen" or install prompt</li>
                    <li>Login with employee email and password</li>
                </ol>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-people"></i> Active Sessions</h5>
            </div>
            <div class="card-body">
                <?php
                $tokenStmt = $dbConn->query("SELECT COUNT(*) as total, 
                    SUM(CASE WHEN expires_at > NOW() THEN 1 ELSE 0 END) as active 
                    FROM mobile_tokens");
                $tokenStats = $tokenStmt->fetch(\PDO::FETCH_ASSOC);
                ?>
                <div class="row text-center">
                    <div class="col-6">
                        <h4 class="mb-0 text-primary"><?= $tokenStats['active'] ?? 0 ?></h4>
                        <small class="text-muted">Active Sessions</small>
                    </div>
                    <div class="col-6">
                        <h4 class="mb-0 text-secondary"><?= $tokenStats['total'] ?? 0 ?></h4>
                        <small class="text-muted">Total Logins</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php elseif ($subpage === 'users'): ?>

<?php
$roleManager = new \App\Role($dbConn);
$allRoles = $roleManager->getAllRoles();
$allPermissions = $roleManager->getPermissionsByCategory();
$allUsers = $roleManager->getAllUsers();

$editRole = null;
$editRolePermissions = [];
if ($action === 'edit_role' && $id) {
    $editRole = $roleManager->getRole((int)$id);
    $editRolePermissions = $roleManager->getRolePermissionIds((int)$id);
}

$editUser = null;
if ($action === 'edit_user' && $id) {
    $editUser = $roleManager->getUser((int)$id);
}
?>

<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-shield-lock"></i> Roles</h5>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#roleModal" onclick="resetRoleForm()">
                    <i class="bi bi-plus-lg"></i> Add Role
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Role</th>
                                <th class="text-center">Users</th>
                                <th class="text-center">Permissions</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allRoles as $role): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($role['display_name']) ?></strong>
                                    <?php if ($role['is_system']): ?>
                                    <span class="badge bg-secondary ms-1">System</span>
                                    <?php endif; ?>
                                    <?php if ($role['description']): ?>
                                    <br><small class="text-muted"><?= htmlspecialchars($role['description']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-info"><?= $role['user_count'] ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-primary"><?= $role['permission_count'] ?></span>
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                            onclick="editRole(<?= htmlspecialchars(json_encode($role)) ?>, <?= htmlspecialchars(json_encode($roleManager->getRolePermissionIds($role['id']))) ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <?php if (!$role['is_system']): ?>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this role?')">
                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                        <input type="hidden" name="action" value="delete_role">
                                        <input type="hidden" name="role_id" value="<?= $role['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-people"></i> System Users</h5>
                <a href="?page=hr&action=create_employee" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg"></i> Add Employee
                </a>
            </div>
            <div class="card-body p-0">
                <div class="alert alert-info m-3 mb-2">
                    <i class="bi bi-info-circle"></i> Users are managed through <a href="?page=hr&subpage=employees" class="alert-link">HR &gt; Employees</a>. 
                    When adding an employee, you can assign their system role and permissions.
                </div>
                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-hover mb-0">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>User</th>
                                <th>Role</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allUsers as $user): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($user['name']) ?></strong>
                                    <br><small class="text-muted"><?= htmlspecialchars($user['email']) ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $user['role'] === 'admin' ? 'danger' : ($user['role'] === 'manager' ? 'warning' : 'secondary') ?>">
                                        <?= htmlspecialchars($user['role_display_name'] ?? ucfirst($user['role'])) ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                            onclick="editUser(<?= htmlspecialchars(json_encode($user)) ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <?php if ($user['id'] != \App\Auth::userId()): ?>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this user?')">
                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
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

<div class="modal fade" id="roleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="roleForm">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" id="roleAction" value="create_role">
                <input type="hidden" name="role_id" id="editRoleId" value="">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="roleModalTitle"><i class="bi bi-shield-plus"></i> Add Role</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Role Name</label>
                        <input type="text" class="form-control" name="display_name" id="roleDisplayName" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="roleDescription" rows="2"></textarea>
                    </div>
                    
                    <hr>
                    <h6 class="mb-3"><i class="bi bi-key"></i> Permissions</h6>
                    
                    <div class="row">
                        <?php foreach ($allPermissions as $category => $perms): ?>
                        <div class="col-md-6 mb-3">
                            <div class="card">
                                <div class="card-header bg-light py-2">
                                    <div class="form-check">
                                        <input class="form-check-input category-check" type="checkbox" 
                                               id="cat_<?= $category ?>" data-category="<?= $category ?>"
                                               onchange="toggleCategory('<?= $category ?>')">
                                        <label class="form-check-label fw-bold" for="cat_<?= $category ?>">
                                            <?= ucfirst($category) ?>
                                        </label>
                                    </div>
                                </div>
                                <div class="card-body py-2">
                                    <?php foreach ($perms as $perm): ?>
                                    <div class="form-check">
                                        <input class="form-check-input perm-check perm-<?= $category ?>" 
                                               type="checkbox" name="permissions[]" 
                                               value="<?= $perm['id'] ?>" id="perm_<?= $perm['id'] ?>"
                                               onchange="updateCategoryCheck('<?= $category ?>')">
                                        <label class="form-check-label small" for="perm_<?= $perm['id'] ?>">
                                            <?= htmlspecialchars($perm['display_name']) ?>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save Role</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="userForm">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" id="userAction" value="create_user">
                <input type="hidden" name="user_id" id="editUserId" value="">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="userModalTitle"><i class="bi bi-person-plus"></i> Add User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" class="form-control" name="name" id="userName" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" id="userEmail" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="tel" class="form-control" name="phone" id="userPhone">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select class="form-select" name="role_id" id="userRoleId" required>
                            <?php foreach ($allRoles as $role): ?>
                            <option value="<?= $role['id'] ?>"><?= htmlspecialchars($role['display_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password <span id="passwordHint" class="text-muted small">(required)</span></label>
                        <input type="password" class="form-control" name="password" id="userPassword">
                        <small class="text-muted">Minimum 6 characters</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function resetRoleForm() {
    document.getElementById('roleAction').value = 'create_role';
    document.getElementById('editRoleId').value = '';
    document.getElementById('roleDisplayName').value = '';
    document.getElementById('roleDescription').value = '';
    document.getElementById('roleModalTitle').innerHTML = '<i class="bi bi-shield-plus"></i> Add Role';
    document.querySelectorAll('.perm-check').forEach(cb => cb.checked = false);
    document.querySelectorAll('.category-check').forEach(cb => cb.checked = false);
}

function editRole(role, permissionIds) {
    document.getElementById('roleAction').value = 'update_role';
    document.getElementById('editRoleId').value = role.id;
    document.getElementById('roleDisplayName').value = role.display_name;
    document.getElementById('roleDescription').value = role.description || '';
    document.getElementById('roleModalTitle').innerHTML = '<i class="bi bi-pencil"></i> Edit Role';
    
    document.querySelectorAll('.perm-check').forEach(cb => {
        cb.checked = permissionIds.includes(parseInt(cb.value));
    });
    
    document.querySelectorAll('.category-check').forEach(cb => {
        updateCategoryCheck(cb.dataset.category);
    });
    
    new bootstrap.Modal(document.getElementById('roleModal')).show();
}

function toggleCategory(category) {
    const catCheck = document.getElementById('cat_' + category);
    document.querySelectorAll('.perm-' + category).forEach(cb => {
        cb.checked = catCheck.checked;
    });
}

function updateCategoryCheck(category) {
    const perms = document.querySelectorAll('.perm-' + category);
    const checked = document.querySelectorAll('.perm-' + category + ':checked');
    const catCheck = document.getElementById('cat_' + category);
    catCheck.checked = perms.length === checked.length;
    catCheck.indeterminate = checked.length > 0 && checked.length < perms.length;
}

function resetUserForm() {
    document.getElementById('userAction').value = 'create_user';
    document.getElementById('editUserId').value = '';
    document.getElementById('userName').value = '';
    document.getElementById('userEmail').value = '';
    document.getElementById('userPhone').value = '';
    document.getElementById('userRoleId').value = '';
    document.getElementById('userPassword').value = '';
    document.getElementById('userPassword').required = true;
    document.getElementById('passwordHint').textContent = '(required)';
    document.getElementById('userModalTitle').innerHTML = '<i class="bi bi-person-plus"></i> Add User';
}

function editUser(user) {
    document.getElementById('userAction').value = 'update_user';
    document.getElementById('editUserId').value = user.id;
    document.getElementById('userName').value = user.name;
    document.getElementById('userEmail').value = user.email;
    document.getElementById('userPhone').value = user.phone || '';
    document.getElementById('userRoleId').value = user.role_id || '';
    document.getElementById('userPassword').value = '';
    document.getElementById('userPassword').required = false;
    document.getElementById('passwordHint').textContent = '(leave blank to keep current)';
    document.getElementById('userModalTitle').innerHTML = '<i class="bi bi-pencil"></i> Edit User';
    
    new bootstrap.Modal(document.getElementById('userModal')).show();
}
</script>

<?php endif; ?>
