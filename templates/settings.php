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
            <i class="bi bi-file-text"></i> Response Templates
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
        <a class="nav-link <?= $subpage === 'contact' ? 'active' : '' ?>" href="?page=settings&subpage=contact">
            <i class="bi bi-telephone"></i> Contact Us
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $subpage === 'mpesa' ? 'active' : '' ?>" href="?page=settings&subpage=mpesa">
            <i class="bi bi-phone"></i> M-Pesa
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $subpage === 'sales' ? 'active' : '' ?>" href="?page=settings&subpage=sales">
            <i class="bi bi-graph-up-arrow"></i> Sales Commissions
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $subpage === 'ticket_commissions' ? 'active' : '' ?>" href="?page=settings&subpage=ticket_commissions">
            <i class="bi bi-ticket-perforated"></i> Ticket Commissions
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
    <li class="nav-item">
        <a class="nav-link <?= $subpage === 'sla' ? 'active' : '' ?>" href="?page=settings&subpage=sla">
            <i class="bi bi-speedometer2"></i> SLA Policies
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $subpage === 'hr_templates' ? 'active' : '' ?>" href="?page=settings&subpage=hr_templates">
            <i class="bi bi-bell"></i> HR Notifications
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $subpage === 'devices' ? 'active' : '' ?>" href="?page=settings&subpage=devices">
            <i class="bi bi-router"></i> Device Monitoring
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $subpage === 'branches' ? 'active' : '' ?>" href="?page=settings&subpage=branches">
            <i class="bi bi-diagram-3"></i> Branches
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $subpage === 'billing_api' ? 'active' : '' ?>" href="?page=settings&subpage=billing_api">
            <i class="bi bi-cloud-arrow-down"></i> Customers API
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $subpage === 'backup' ? 'active' : '' ?>" href="?page=settings&subpage=backup">
            <i class="bi bi-database-down"></i> Database Backup
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
            
            <div class="card mt-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-image"></i> Company Logo</h5>
                </div>
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <div id="logoPreview" class="border rounded p-3 text-center" style="width: 120px; height: 80px; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #1a1c2c 0%, #2d3250 100%);">
                                <?php if (!empty($companyInfo['company_logo'])): ?>
                                    <img src="<?= htmlspecialchars($companyInfo['company_logo']) ?>" alt="Logo" style="max-width: 100%; max-height: 60px;">
                                <?php else: ?>
                                    <span class="text-white"><i class="bi bi-router fs-2"></i></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col">
                            <input type="hidden" name="company_logo" id="companyLogoInput" value="<?= htmlspecialchars($companyInfo['company_logo'] ?? '') ?>">
                            <input type="file" class="form-control mb-2" id="logoUpload" accept="image/*">
                            <small class="text-muted d-block">Recommended: PNG or SVG, max 200x80px, transparent background</small>
                            <?php if (!empty($companyInfo['company_logo'])): ?>
                                <button type="button" class="btn btn-sm btn-outline-danger mt-2" onclick="removeLogo()">
                                    <i class="bi bi-trash"></i> Remove Logo
                                </button>
                            <?php endif; ?>
                        </div>
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
                    <div class="mb-3">
                        <span class="badge bg-success"><i class="bi bi-whatsapp"></i> WhatsApp Enabled</span>
                        <small class="text-muted d-block">WhatsApp messaging is always enabled. Configure settings in the WhatsApp tab.</small>
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

document.getElementById('logoUpload').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;
    
    if (!file.type.startsWith('image/')) {
        alert('Please select an image file');
        return;
    }
    
    if (file.size > 2 * 1024 * 1024) {
        alert('Image size should be less than 2MB');
        return;
    }
    
    const reader = new FileReader();
    reader.onload = function(e) {
        const base64 = e.target.result;
        document.getElementById('companyLogoInput').value = base64;
        document.getElementById('logoPreview').innerHTML = '<img src="' + base64 + '" alt="Logo" style="max-width: 100%; max-height: 60px;">';
    };
    reader.readAsDataURL(file);
});

function removeLogo() {
    if (confirm('Remove the company logo?')) {
        document.getElementById('companyLogoInput').value = '';
        document.getElementById('logoPreview').innerHTML = '<span class="text-white"><i class="bi bi-router fs-2"></i></span>';
    }
}
</script>

<?php elseif ($subpage === 'sms'): ?>

<?php
$testResult = null;
$sendTestResult = null;
$smsSettings = $settings->getSMSSettings();
$primaryGateway = $settings->getPrimaryNotificationGateway();

if (($_GET['action'] ?? '') === 'test') {
    $testResult = $smsGateway->testConnection();
}
if (($_GET['action'] ?? '') === 'send_test' && isset($_GET['phone'])) {
    $sendTestResult = $smsGateway->send($_GET['phone'], 'Test message from ISP CRM System. If you received this, your SMS gateway is working!');
}
?>

<!-- Primary Notification Gateway -->
<div class="card mb-4">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="bi bi-send-check"></i> Primary Notification Gateway</h5>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="save_primary_gateway">
            <div class="row align-items-end">
                <div class="col-md-6">
                    <label class="form-label">Choose Primary Channel for Notifications</label>
                    <select class="form-select" name="primary_notification_gateway">
                        <option value="both" <?= $primaryGateway === 'both' ? 'selected' : '' ?>>Both SMS & WhatsApp</option>
                        <option value="whatsapp" <?= $primaryGateway === 'whatsapp' ? 'selected' : '' ?>>WhatsApp Only</option>
                        <option value="sms" <?= $primaryGateway === 'sms' ? 'selected' : '' ?>>SMS Only</option>
                    </select>
                    <small class="text-muted">This controls how ticket, order, and complaint notifications are sent to customers</small>
                </div>
                <div class="col-md-6">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> Save
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

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
                    <?php if (!empty($sendTestResult['http_code'])): ?>
                    <br><small>HTTP Code: <?= $sendTestResult['http_code'] ?></small>
                    <?php endif; ?>
                    <?php if (!empty($sendTestResult['debug'])): ?>
                    <br><small class="text-muted">URL: <?= htmlspecialchars($sendTestResult['debug']['url'] ?? 'N/A') ?></small>
                    <br><small class="text-muted">Provider: <?= htmlspecialchars($sendTestResult['debug']['provider'] ?? 'N/A') ?></small>
                    <br><small class="text-muted">Partner ID: <?= htmlspecialchars($sendTestResult['debug']['partner_id'] ?? 'N/A') ?></small>
                    <br><small class="text-muted">Shortcode: <?= htmlspecialchars($sendTestResult['debug']['shortcode'] ?? 'N/A') ?></small>
                    <br><small class="text-muted">Response: <?= htmlspecialchars($sendTestResult['debug']['raw_response'] ?? 'N/A') ?></small>
                    <?php endif; ?>
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

<div class="card mt-4">
    <div class="card-header bg-info text-white">
        <h5 class="mb-0"><i class="bi bi-chat-square-text"></i> SMS Notification Templates</h5>
    </div>
    <div class="card-body">
        <div class="alert alert-light border mb-4">
            <h6 class="mb-3"><i class="bi bi-info-circle"></i> Template Reference Guide</h6>
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0">
                    <thead class="table-secondary">
                        <tr>
                            <th>Template Name</th>
                            <th>Triggered When</th>
                            <th>Sent To</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><code>Ticket Created</code></td><td>New ticket is created</td><td>Customer</td></tr>
                        <tr><td><code>Ticket Status Updated</code></td><td>Ticket status changes</td><td>Customer</td></tr>
                        <tr><td><code>Ticket Resolved</code></td><td>Ticket marked as resolved</td><td>Customer</td></tr>
                        <tr><td><code>Technician Assigned (to Customer)</code></td><td>Technician assigned to ticket</td><td>Customer</td></tr>
                        <tr><td><code>New Ticket Assigned to Technician</code></td><td>Technician assigned to ticket</td><td>Technician</td></tr>
                        <tr><td><code>Complaint Received</code></td><td>New complaint submitted</td><td>Customer</td></tr>
                        <tr><td><code>Complaint Approved</code></td><td>Complaint converted to ticket</td><td>Customer</td></tr>
                        <tr><td><code>Order Confirmation</code></td><td>New order placed</td><td>Customer</td></tr>
                        <tr><td><code>Order Accepted</code></td><td>Order converted to installation ticket</td><td>Customer</td></tr>
                        <tr><td><code>HR Notice</code></td><td>HR sends employee notification</td><td>Employee</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="save_sms_templates">
            
            <div class="accordion" id="smsTemplatesAccordion">
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#ticketCustomerTemplates">
                            <i class="bi bi-person-check me-2"></i> Ticket → Customer Notifications
                        </button>
                    </h2>
                    <div id="ticketCustomerTemplates" class="accordion-collapse collapse show" data-bs-parent="#smsTemplatesAccordion">
                        <div class="accordion-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Ticket Created</label>
                                    <textarea class="form-control" name="sms_template_ticket_created" rows="3"><?= htmlspecialchars($settings->get('sms_template_ticket_created', 'ISP Support - Ticket #{ticket_number} created. Subject: {subject}. Status: {status}. We will contact you shortly.')) ?></textarea>
                                    <small class="text-muted">Placeholders: {ticket_number}, {subject}, {description}, {status}, {category}, {priority}, {customer_name}, {customer_phone}, {customer_address}, {company_name}</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Ticket Status Updated</label>
                                    <textarea class="form-control" name="sms_template_ticket_updated" rows="3"><?= htmlspecialchars($settings->get('sms_template_ticket_updated', 'ISP Support - Ticket #{ticket_number} Status: {status}. {message}')) ?></textarea>
                                    <small class="text-muted">Placeholders: {ticket_number}, {subject}, {description}, {status}, {message}, {category}, {priority}, {customer_name}, {customer_phone}, {company_name}</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Ticket Resolved</label>
                                    <textarea class="form-control" name="sms_template_ticket_resolved" rows="3"><?= htmlspecialchars($settings->get('sms_template_ticket_resolved', 'ISP Support - Ticket #{ticket_number} has been RESOLVED. Thank you for your patience.')) ?></textarea>
                                    <small class="text-muted">Placeholders: {ticket_number}, {subject}, {description}, {status}, {customer_name}, {customer_phone}, {technician_name}, {technician_phone}, {company_name}</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Technician Assigned (to Customer)</label>
                                    <textarea class="form-control" name="sms_template_ticket_assigned" rows="3"><?= htmlspecialchars($settings->get('sms_template_ticket_assigned', 'ISP Support - Technician {technician_name} ({technician_phone}) has been assigned to your ticket #{ticket_number}.')) ?></textarea>
                                    <small class="text-muted">Placeholders: {ticket_number}, {subject}, {description}, {category}, {priority}, {customer_name}, {customer_phone}, {technician_name}, {technician_phone}, {company_name}</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ticketTechTemplates">
                            <i class="bi bi-tools me-2"></i> Ticket → Technician/Staff Notifications
                        </button>
                    </h2>
                    <div id="ticketTechTemplates" class="accordion-collapse collapse" data-bs-parent="#smsTemplatesAccordion">
                        <div class="accordion-body">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label fw-bold">New Ticket Assigned to Technician</label>
                                    <textarea class="form-control" name="sms_template_technician_assigned" rows="3"><?= htmlspecialchars($settings->get('sms_template_technician_assigned', 'New Ticket #{ticket_number} assigned to you. Customer: {customer_name} ({customer_phone}). Subject: {subject}. Priority: {priority}. Address: {customer_address}')) ?></textarea>
                                    <small class="text-muted">Placeholders: {ticket_number}, {subject}, {description}, {category}, {priority}, {customer_name}, {customer_phone}, {customer_address}, {customer_email}, {technician_name}, {technician_phone}, {company_name}</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#complaintTemplates">
                            <i class="bi bi-exclamation-triangle me-2"></i> Complaint Notifications
                        </button>
                    </h2>
                    <div id="complaintTemplates" class="accordion-collapse collapse" data-bs-parent="#smsTemplatesAccordion">
                        <div class="accordion-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Complaint Received</label>
                                    <textarea class="form-control" name="sms_template_complaint_received" rows="3"><?= htmlspecialchars($settings->get('sms_template_complaint_received', 'Thank you for your feedback. Complaint #{complaint_number} received. Category: {category}. We will review and respond shortly.')) ?></textarea>
                                    <small class="text-muted">Placeholders: {complaint_number}, {subject}, {description}, {category}, {priority}, {customer_name}, {customer_phone}, {customer_email}, {customer_location}, {company_name}</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Complaint Approved → Ticket Created</label>
                                    <textarea class="form-control" name="sms_template_complaint_approved" rows="3"><?= htmlspecialchars($settings->get('sms_template_complaint_approved', 'Your complaint #{complaint_number} has been approved and converted to Ticket #{ticket_number}. A technician will be assigned shortly.')) ?></textarea>
                                    <small class="text-muted">Placeholders: {complaint_number}, {ticket_number}, {subject}, {description}, {category}, {customer_name}, {customer_phone}, {company_name}</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#orderTemplates">
                            <i class="bi bi-cart-check me-2"></i> Order Notifications
                        </button>
                    </h2>
                    <div id="orderTemplates" class="accordion-collapse collapse" data-bs-parent="#smsTemplatesAccordion">
                        <div class="accordion-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Order Confirmation</label>
                                    <textarea class="form-control" name="sms_template_order_confirmation" rows="3"><?= htmlspecialchars($settings->get('sms_template_order_confirmation', 'Thank you! Order #{order_number} received for {package_name}. Amount: KES {amount}. We will contact you to schedule installation.')) ?></textarea>
                                    <small class="text-muted">Placeholders: {order_number}, {package_name}, {amount}, {customer_name}, {customer_phone}, {customer_email}, {customer_address}, {salesperson_name}, {salesperson_phone}, {company_name}</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Order Accepted</label>
                                    <textarea class="form-control" name="sms_template_order_accepted" rows="3"><?= htmlspecialchars($settings->get('sms_template_order_accepted', 'Great news! Order #{order_number} accepted. Ticket #{ticket_number} created for installation. Our team will contact you soon.')) ?></textarea>
                                    <small class="text-muted">Placeholders: {order_number}, {ticket_number}, {package_name}, {amount}, {customer_name}, {customer_phone}, {customer_address}, {company_name}</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#hrTemplates">
                            <i class="bi bi-people me-2"></i> HR / Employee Notifications
                        </button>
                    </h2>
                    <div id="hrTemplates" class="accordion-collapse collapse" data-bs-parent="#smsTemplatesAccordion">
                        <div class="accordion-body">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label fw-bold">HR Notice</label>
                                    <textarea class="form-control" name="sms_template_hr_notice" rows="3"><?= htmlspecialchars($settings->get('sms_template_hr_notice', 'ISP HR Notice - {subject}: {message}')) ?></textarea>
                                    <small class="text-muted">Placeholders: {subject}, {message}, {employee_name}, {employee_phone}, {department}, {company_name}</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#branchTemplates">
                            <i class="bi bi-building me-2"></i> Branch WhatsApp Group Notifications
                        </button>
                    </h2>
                    <div id="branchTemplates" class="accordion-collapse collapse" data-bs-parent="#smsTemplatesAccordion">
                        <div class="accordion-body">
                            <div class="alert alert-info mb-3">
                                <i class="bi bi-info-circle me-2"></i>
                                <strong>Note:</strong> These templates are sent to branch WhatsApp groups. Requires WhatsApp Session provider and branch WhatsApp Group ID configured.
                            </div>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label fw-bold">Ticket Assigned to Branch</label>
                                    <textarea class="form-control" name="wa_template_branch_ticket_assigned" rows="16"><?= htmlspecialchars($settings->get('wa_template_branch_ticket_assigned', "🎫 *NEW TICKET ASSIGNED*\n\n📋 *Ticket:* #{ticket_number}\n📌 *Subject:* {subject}\n🏷️ *Category:* {category}\n⚡ *Priority:* {priority}\n🕐 *Created:* {created_at}\n\n👤 *Customer Details:*\n• Name: {customer_name}\n• Phone: {customer_phone}\n• Email: {customer_email}\n• Account: {customer_account}\n• Username: {customer_username}\n• Address: {customer_address}\n• Location: {customer_location}\n• GPS: {customer_coordinates}\n• Plan: {service_plan}\n\n👷 *{assignment_info}*\n📞 Tech Phone: {technician_phone}\n👥 Team: {team_name}\n👥 Members: {team_members}\n\n🏢 Branch: {branch_name}")) ?></textarea>
                                    <small class="text-muted d-block mb-2"><strong>Ticket:</strong> {ticket_number}, {subject}, {description}, {category}, {priority}, {created_at}</small>
                                    <small class="text-muted d-block mb-2"><strong>Customer:</strong> {customer_name}, {customer_phone}, {customer_email}, {customer_account}, {customer_username}, {customer_address}, {customer_location}, {customer_coordinates}, {service_plan}</small>
                                    <small class="text-muted d-block"><strong>Assignment:</strong> {technician_name}, {technician_phone}, {team_name}, {team_members}, {assignment_info}, {branch_name}, {branch_code}</small>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-bold">Daily Branch Summary</label>
                                    <textarea class="form-control" name="wa_template_branch_daily_summary" rows="8"><?= htmlspecialchars($settings->get('wa_template_branch_daily_summary', "📊 *DAILY BRANCH SUMMARY*\n🏢 Branch: {branch_name}\n📅 Date: {date}\n\n📈 *Ticket Statistics:*\n• New Tickets: {new_tickets}\n• Resolved: {resolved_tickets}\n• In Progress: {in_progress_tickets}\n• Open: {open_tickets}\n• SLA Breached: {sla_breached}\n\n👥 *Team Performance:*\n{team_performance}\n\n⏰ Generated at {time}")) ?></textarea>
                                    <small class="text-muted">Placeholders: {branch_name}, {branch_code}, {date}, {time}, {new_tickets}, {resolved_tickets}, {in_progress_tickets}, {open_tickets}, {closed_tickets}, {sla_breached}, {escalated_tickets}, {team_performance}, {top_performer}</small>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-bold">Daily Operations Summary</label>
                                    <textarea class="form-control" name="wa_template_operations_daily_summary" rows="10"><?= htmlspecialchars($settings->get('wa_template_operations_daily_summary', "📊 *DAILY OPERATIONS SUMMARY*\n📅 Date: {date}\n🏢 Company: {company_name}\n\n👥 *ATTENDANCE OVERVIEW*\n• Total Employees: {total_employees}\n• Present: {total_present}\n• Absent: {total_absent}\n• Late: {total_late}\n• Hours Worked: {total_hours}\n\n📈 *TICKET STATISTICS*\n• Total Tickets Today: {total_tickets}\n• Resolved: {total_resolved}\n• In Progress: {total_in_progress}\n• Open: {total_open}\n• SLA Breached: {total_sla_breached}\n\n🏢 *BRANCH BREAKDOWN*\n{branch_summaries}\n\n🏆 *TOP PERFORMERS*\n{top_performers}\n\n⏰ Generated at {time}")) ?></textarea>
                                    <small class="text-muted">Placeholders: {date}, {time}, {company_name}, {total_tickets}, {total_resolved}, {total_in_progress}, {total_open}, {total_sla_breached}, {total_employees}, {total_present}, {total_absent}, {total_late}, {total_hours}, {branch_summaries}, {top_performers}, {branch_count}</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="mt-4">
                <button type="submit" class="btn btn-info text-white">
                    <i class="bi bi-check-lg"></i> Save All Templates
                </button>
                <button type="button" class="btn btn-outline-secondary ms-2" onclick="resetToDefaults()">
                    <i class="bi bi-arrow-counterclockwise"></i> Reset to Defaults
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function resetToDefaults() {
    if (!confirm('Are you sure you want to reset all templates to defaults?')) return;
    
    const defaults = {
        'sms_template_ticket_created': 'ISP Support - Ticket #{ticket_number} created. Subject: {subject}. Status: {status}. We will contact you shortly.',
        'sms_template_ticket_updated': 'ISP Support - Ticket #{ticket_number} Status: {status}. {message}',
        'sms_template_ticket_resolved': 'ISP Support - Ticket #{ticket_number} has been RESOLVED. Thank you for your patience.',
        'sms_template_ticket_assigned': 'ISP Support - Technician {technician_name} has been assigned to your ticket #{ticket_number}.',
        'sms_template_technician_assigned': 'New Ticket #{ticket_number} assigned to you. Customer: {customer_name} ({customer_phone}). Subject: {subject}. Priority: {priority}. Address: {customer_address}',
        'sms_template_complaint_received': 'Thank you for your feedback. Complaint #{complaint_number} received. Category: {category}. We will review and respond shortly.',
        'sms_template_complaint_approved': 'Your complaint #{complaint_number} has been approved and converted to Ticket #{ticket_number}. A technician will be assigned shortly.',
        'sms_template_order_confirmation': 'Thank you! Order #{order_number} received for {package_name}. Amount: KES {amount}. We will contact you to schedule installation.',
        'sms_template_order_accepted': 'Great news! Order #{order_number} accepted. Ticket #{ticket_number} created for installation. Our team will contact you soon.',
        'sms_template_hr_notice': 'ISP HR Notice - {subject}: {message}',
        'wa_template_branch_ticket_assigned': '🎫 *NEW TICKET ASSIGNED*\n\n📋 *Ticket:* #{ticket_number}\n📌 *Subject:* {subject}\n🏷️ *Category:* {category}\n⚡ *Priority:* {priority}\n🕐 *Created:* {created_at}\n\n👤 *Customer Details:*\n• Name: {customer_name}\n• Phone: {customer_phone}\n• Email: {customer_email}\n• Account: {customer_account}\n• Username: {customer_username}\n• Address: {customer_address}\n• Location: {customer_location}\n• GPS: {customer_coordinates}\n• Plan: {service_plan}\n\n👷 *{assignment_info}*\n📞 Tech Phone: {technician_phone}\n👥 Team: {team_name}\n👥 Members: {team_members}\n\n🏢 Branch: {branch_name}',
        'wa_template_branch_daily_summary': '📊 *DAILY BRANCH SUMMARY*\n🏢 Branch: {branch_name}\n📅 Date: {date}\n\n📈 *Ticket Statistics:*\n• New Tickets: {new_tickets}\n• Resolved: {resolved_tickets}\n• In Progress: {in_progress_tickets}\n• Open: {open_tickets}\n• SLA Breached: {sla_breached}\n\n👥 *Team Performance:*\n{team_performance}\n\n⏰ Generated at {time}'
    };
    
    for (const [name, value] of Object.entries(defaults)) {
        const field = document.querySelector(`textarea[name="${name}"]`);
        if (field) field.value = value;
    }
}
</script>

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
        <h5 class="mb-0"><i class="bi bi-whatsapp"></i> WhatsApp Gateway Settings</h5>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="save_whatsapp_settings">
            
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">WhatsApp Provider</label>
                    <select class="form-select" name="whatsapp_provider" id="whatsappProvider" onchange="toggleWhatsAppProvider()">
                        <option value="web" <?= ($whatsappSettings['whatsapp_provider'] ?? 'web') === 'web' ? 'selected' : '' ?>>WhatsApp Web Links (Manual)</option>
                        <option value="session" <?= ($whatsappSettings['whatsapp_provider'] ?? '') === 'session' ? 'selected' : '' ?>>WhatsApp Web Session (Automated)</option>
                        <option value="meta" <?= ($whatsappSettings['whatsapp_provider'] ?? '') === 'meta' ? 'selected' : '' ?>>Meta WhatsApp Business API</option>
                        <option value="waha" <?= ($whatsappSettings['whatsapp_provider'] ?? '') === 'waha' ? 'selected' : '' ?>>WAHA (Self-Hosted)</option>
                        <option value="ultramsg" <?= ($whatsappSettings['whatsapp_provider'] ?? '') === 'ultramsg' ? 'selected' : '' ?>>UltraMsg API</option>
                        <option value="custom" <?= ($whatsappSettings['whatsapp_provider'] ?? '') === 'custom' ? 'selected' : '' ?>>Custom API Gateway</option>
                    </select>
                    <small class="text-muted">Select how to send WhatsApp messages</small>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Default Country Code</label>
                    <div class="input-group">
                        <span class="input-group-text">+</span>
                        <input type="text" class="form-control" name="whatsapp_country_code" 
                               value="<?= htmlspecialchars($whatsappSettings['whatsapp_country_code'] ?? '254') ?>" 
                               placeholder="254">
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Default Greeting</label>
                    <input type="text" class="form-control" name="whatsapp_default_message" 
                           value="<?= htmlspecialchars($whatsappSettings['whatsapp_default_message'] ?? '') ?>" 
                           placeholder="Hello!">
                </div>
            </div>
            
            <!-- Provider-specific configuration - shows immediately after selecting provider -->
            <div id="waProviderSession" class="provider-config mt-4 border rounded p-3 bg-light" style="display: none;">
                <h6 class="text-success mb-3"><i class="bi bi-whatsapp"></i> WhatsApp Web Session Configuration</h6>
                <div class="alert alert-warning mb-3">
                    <i class="bi bi-exclamation-triangle"></i> <strong>WhatsApp Web Session</strong> - Sends messages automatically via your WhatsApp account. Scan QR code to connect.
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Session Service URL</label>
                        <input type="url" class="form-control" name="whatsapp_session_url" 
                               value="<?= htmlspecialchars($settings->get('whatsapp_session_url', 'http://localhost:3001')) ?>" 
                               placeholder="http://localhost:3001">
                        <small class="text-muted">URL of the WhatsApp session service (e.g., http://localhost:3001)</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Session API Secret</label>
                        <input type="password" class="form-control" name="whatsapp_session_secret" id="waSessionSecret"
                               value="<?= htmlspecialchars($settings->get('whatsapp_session_secret', '')) ?>" 
                               placeholder="Enter the API secret from WhatsApp service">
                        <small class="text-muted">Required to authenticate with the WhatsApp session service</small>
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-12">
                        <label class="form-label">Session Status</label>
                        <div id="waSessionStatus" class="border rounded p-3 bg-white">
                            <span class="text-muted">Click "Check Status" to view connection status</span>
                        </div>
                    </div>
                </div>
                <div class="d-flex gap-2 flex-wrap mb-3">
                    <button type="button" class="btn btn-primary" onclick="checkSessionStatus()">
                        <i class="bi bi-arrow-repeat"></i> Check Status
                    </button>
                    <button type="button" class="btn btn-success" onclick="initializeSession()">
                        <i class="bi bi-play-fill"></i> Start Session
                    </button>
                    <button type="button" class="btn btn-outline-danger" onclick="logoutSession()">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </button>
                    <button type="button" class="btn btn-outline-info" onclick="loadGroups()">
                        <i class="bi bi-people"></i> Load Groups
                    </button>
                </div>
                <div id="waQRContainer" class="text-center mb-3" style="display: none;">
                    <h6 class="mb-2">Scan this QR Code with WhatsApp</h6>
                    <img id="waQRCode" src="" alt="QR Code" style="max-width: 300px;">
                    <p class="text-muted small mt-2">Open WhatsApp on your phone > Menu > Linked Devices > Link a Device</p>
                </div>
                <div id="waGroupsList" class="mb-3" style="display: none;">
                    <h6 class="mb-2">Your WhatsApp Groups</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Group Name</th>
                                    <th>Members</th>
                                    <th>Test</th>
                                </tr>
                            </thead>
                            <tbody id="waGroupsBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div id="waProviderMeta" class="provider-config mt-4 border rounded p-3 bg-light" style="display: none;">
                <h6 class="text-primary mb-3"><i class="bi bi-facebook"></i> Meta WhatsApp Business API</h6>
                <div class="alert alert-info mb-3">
                    <i class="bi bi-info-circle"></i> Requires Facebook Business account and WhatsApp Business API access.
                    <a href="https://developers.facebook.com/docs/whatsapp/cloud-api/get-started" target="_blank" class="alert-link">Setup Guide</a>
                </div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Access Token</label>
                        <input type="password" class="form-control" name="whatsapp_meta_token" 
                               value="<?= htmlspecialchars($settings->get('whatsapp_meta_token', '')) ?>" 
                               placeholder="Your Meta access token">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Phone Number ID</label>
                        <input type="text" class="form-control" name="whatsapp_phone_number_id" 
                               value="<?= htmlspecialchars($settings->get('whatsapp_phone_number_id', '')) ?>" 
                               placeholder="Phone number ID from Meta">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Business ID</label>
                        <input type="text" class="form-control" name="whatsapp_business_id" 
                               value="<?= htmlspecialchars($settings->get('whatsapp_business_id', '')) ?>" 
                               placeholder="WhatsApp Business Account ID">
                    </div>
                </div>
            </div>
            
            <div id="waProviderWaha" class="provider-config mt-4 border rounded p-3 bg-light" style="display: none;">
                <h6 class="text-info mb-3"><i class="bi bi-server"></i> WAHA (Self-Hosted)</h6>
                <div class="alert alert-info mb-3">
                    <i class="bi bi-info-circle"></i> Self-hosted WhatsApp API. 
                    <a href="https://waha.devlike.pro/" target="_blank" class="alert-link">Learn More</a>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">WAHA Server URL</label>
                        <input type="url" class="form-control" name="whatsapp_waha_url" 
                               value="<?= htmlspecialchars($settings->get('whatsapp_waha_url', '')) ?>" 
                               placeholder="http://localhost:3000">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">API Key (Optional)</label>
                        <input type="password" class="form-control" name="whatsapp_waha_api_key" 
                               value="<?= htmlspecialchars($settings->get('whatsapp_waha_api_key', '')) ?>" 
                               placeholder="WAHA API key if configured">
                    </div>
                </div>
            </div>
            
            <div id="waProviderUltramsg" class="provider-config mt-4 border rounded p-3 bg-light" style="display: none;">
                <h6 class="text-warning mb-3"><i class="bi bi-cloud"></i> UltraMsg API</h6>
                <div class="alert alert-info mb-3">
                    <i class="bi bi-info-circle"></i> Cloud WhatsApp API service.
                    <a href="https://ultramsg.com/" target="_blank" class="alert-link">Get API Key</a>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Instance ID</label>
                        <input type="text" class="form-control" name="whatsapp_ultramsg_instance" 
                               value="<?= htmlspecialchars($settings->get('whatsapp_ultramsg_instance', '')) ?>" 
                               placeholder="instance12345">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">API Token</label>
                        <input type="password" class="form-control" name="whatsapp_ultramsg_token" 
                               value="<?= htmlspecialchars($settings->get('whatsapp_ultramsg_token', '')) ?>" 
                               placeholder="Your UltraMsg token">
                    </div>
                </div>
            </div>
            
            <div id="waProviderCustom" class="provider-config mt-4 border rounded p-3 bg-light" style="display: none;">
                <h6 class="text-secondary mb-3"><i class="bi bi-code-slash"></i> Custom API Gateway</h6>
                <div class="alert alert-warning mb-3">
                    <i class="bi bi-exclamation-triangle"></i> For other WhatsApp gateways. Must accept POST with JSON body: <code>{"phone": "...", "message": "..."}</code>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">API URL</label>
                        <input type="url" class="form-control" name="whatsapp_custom_url" 
                               value="<?= htmlspecialchars($settings->get('whatsapp_custom_url', '')) ?>" 
                               placeholder="https://api.example.com/whatsapp/send">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">API Key / Bearer Token</label>
                        <input type="password" class="form-control" name="whatsapp_custom_api_key" 
                               value="<?= htmlspecialchars($settings->get('whatsapp_custom_api_key', '')) ?>" 
                               placeholder="Authorization token">
                    </div>
                </div>
            </div>
            
            <div class="card mt-4 border-info">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0"><i class="bi bi-clock-history"></i> Daily Summary Notifications</h6>
                </div>
                <div class="card-body">
                    <div class="alert alert-info small mb-3">
                        <i class="bi bi-info-circle"></i> <strong>Summary Notifications:</strong><br>
                        • <strong>On Clock Out:</strong> Each employee receives their personal summary directly via WhatsApp<br>
                        • <strong>Scheduled Team Summary:</strong> Team summaries sent to selected groups at 7 AM and 6 PM daily
                    </div>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <label class="form-label">Morning Summary Time</label>
                            <select class="form-select" name="daily_summary_morning_hour">
                                <?php for ($h = 5; $h <= 10; $h++): ?>
                                <option value="<?= $h ?>" <?= $settings->get('daily_summary_morning_hour', '7') == $h ? 'selected' : '' ?>><?= sprintf('%02d:00', $h) ?> (<?= date('h:i A', strtotime("$h:00")) ?>)</option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Evening Summary Time</label>
                            <select class="form-select" name="daily_summary_evening_hour">
                                <?php for ($h = 16; $h <= 20; $h++): ?>
                                <option value="<?= $h ?>" <?= $settings->get('daily_summary_evening_hour', '18') == $h ? 'selected' : '' ?>><?= sprintf('%02d:00', $h) ?> (<?= date('h:i A', strtotime("$h:00")) ?>)</option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Minimum Clock Out Time</label>
                            <select class="form-select" name="min_clock_out_hour">
                                <?php for ($h = 14; $h <= 20; $h++): ?>
                                <option value="<?= $h ?>" <?= $settings->get('min_clock_out_hour', '17') == $h ? 'selected' : '' ?>><?= sprintf('%02d:00', $h) ?> (<?= date('h:i A', strtotime("$h:00")) ?>)</option>
                                <?php endfor; ?>
                            </select>
                            <small class="text-muted">Employees cannot clock out before this time</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Cron Setup (Run every 5 mins)</label>
                            <div class="input-group">
                                <input type="text" class="form-control form-control-sm bg-light" readonly 
                                       value="*/5 * * * * curl -s '<?= (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'your-domain.com') ?>/cron.php?action=check_schedule&secret=<?= htmlspecialchars($settings->get('cron_secret', 'isp-crm-cron-2024')) ?>'">
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="navigator.clipboard.writeText(this.previousElementSibling.value); alert('Copied!')">
                                    <i class="bi bi-clipboard"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <hr class="my-3">
                    
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Select WhatsApp Groups for Team Summary</label>
                            <div id="waSummaryGroupsContainer" class="border rounded p-2 bg-light" style="min-height: 100px;">
                                <span class="text-muted small">Click "Fetch Groups" to load available WhatsApp groups</span>
                            </div>
                            <input type="hidden" name="whatsapp_daily_summary_groups" id="waDailySummaryGroups" 
                                   value="<?= htmlspecialchars($settings->get('whatsapp_daily_summary_groups', '[]')) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-outline-success" onclick="fetchSummaryGroups()">
                                    <i class="bi bi-people"></i> Fetch Groups
                                </button>
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="testDailySummary()">
                                    <i class="bi bi-send"></i> Send Test Summary Now
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row g-3 mt-2">
                        <div class="col-md-12">
                            <label class="form-label">Fallback: Phone Numbers (if groups not available)</label>
                            <input type="text" class="form-control" name="whatsapp_summary_groups" 
                                   value="<?= htmlspecialchars($settings->get('whatsapp_summary_groups', '')) ?>" 
                                   placeholder="254712345678, 254798765432">
                            <small class="text-muted">Comma-separated phone numbers to receive summaries if WhatsApp groups don't work</small>
                        </div>
                    </div>
                    
                    <div class="row g-3 mt-3">
                        <div class="col-md-8">
                            <label class="form-label fw-bold"><i class="bi bi-clipboard-data text-primary"></i> Daily Operations WhatsApp Group</label>
                            <input type="text" class="form-control" name="whatsapp_operations_group_id" id="whatsappOperationsGroupId"
                                   value="<?= htmlspecialchars($settings->get('whatsapp_operations_group_id', '')) ?>" 
                                   placeholder="e.g., 254712345678-1234567890@g.us">
                            <small class="text-muted">This group receives consolidated daily summaries including tickets, attendance, and all branch activities. Leave empty to disable.</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button type="button" class="btn btn-outline-primary" onclick="selectOperationsGroup()">
                                    <i class="bi bi-people"></i> Select from Groups
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <hr class="my-3">
                    
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Department-Specific Groups</label>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Department</th>
                                            <th>WhatsApp Group ID or Phone</th>
                                            <th style="width: 120px;">Select Group</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $deptStmt = $dbConn->query("SELECT id, name FROM departments ORDER BY name");
                                        $departments = $deptStmt->fetchAll(PDO::FETCH_ASSOC);
                                        foreach ($departments as $dept):
                                        ?>
                                        <tr>
                                            <td><?= htmlspecialchars($dept['name']) ?></td>
                                            <td>
                                                <input type="text" class="form-control form-control-sm dept-group-input" 
                                                       name="whatsapp_group_dept_<?= $dept['id'] ?>" 
                                                       id="deptGroup<?= $dept['id'] ?>"
                                                       value="<?= htmlspecialchars($settings->get('whatsapp_group_dept_' . $dept['id'], '')) ?>" 
                                                       placeholder="Group ID or phone number">
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-outline-secondary btn-sm w-100" onclick="selectGroupFor('deptGroup<?= $dept['id'] ?>')">
                                                    <i class="bi bi-list"></i> Select
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($departments)): ?>
                                        <tr><td colspan="3" class="text-center text-muted">No departments configured</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <small class="text-muted">Department employees' clock-out summaries will be sent to their department's group</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal fade" id="groupSelectModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Select WhatsApp Group</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div id="groupSelectList" class="list-group">
                                <span class="text-muted">Loading groups...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="mt-4">
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-check-lg"></i> Save WhatsApp Settings
                </button>
                <button type="button" class="btn btn-outline-primary ms-2" onclick="testWhatsAppGateway()">
                    <i class="bi bi-send"></i> Test Connection
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleWhatsAppProvider() {
    document.querySelectorAll('.provider-config').forEach(el => el.style.display = 'none');
    const provider = document.getElementById('whatsappProvider').value;
    if (provider !== 'web') {
        const configDiv = document.getElementById('waProvider' + provider.charAt(0).toUpperCase() + provider.slice(1));
        if (configDiv) configDiv.style.display = 'block';
    }
}
document.addEventListener('DOMContentLoaded', toggleWhatsAppProvider);

const waApiBase = '?page=api&action=whatsapp_session&op=';

function checkSessionStatus() {
    const statusDiv = document.getElementById('waSessionStatus');
    statusDiv.innerHTML = '<span class="text-info"><i class="bi bi-hourglass-split"></i> Checking...</span>';
    
    fetch(waApiBase + 'status')
        .then(r => r.json())
        .then(data => {
            let html = '';
            if (data.status === 'connected') {
                html = '<span class="text-success"><i class="bi bi-check-circle-fill"></i> Connected</span>';
                if (data.info) {
                    html += '<br><small class="text-muted">Phone: ' + (data.info.phone || 'N/A') + '</small>';
                    html += '<br><small class="text-muted">Name: ' + (data.info.pushname || 'N/A') + '</small>';
                }
                document.getElementById('waQRContainer').style.display = 'none';
            } else if (data.status === 'qr_ready') {
                html = '<span class="text-warning"><i class="bi bi-qr-code"></i> Waiting for QR scan</span>';
                fetchQRCode();
            } else if (data.status === 'initializing') {
                html = '<span class="text-info"><i class="bi bi-hourglass-split"></i> Initializing...</span>';
                setTimeout(checkSessionStatus, 2000);
            } else if (data.status === 'service_unavailable') {
                html = '<span class="text-danger"><i class="bi bi-x-circle"></i> Service not running</span><br><small class="text-muted">Start the WhatsApp service first</small>';
            } else {
                html = '<span class="text-secondary"><i class="bi bi-x-circle"></i> ' + (data.status || 'Disconnected') + '</span>';
            }
            statusDiv.innerHTML = html;
        })
        .catch(err => {
            statusDiv.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle"></i> Error checking status</span>';
        });
}

function fetchQRCode() {
    fetch(waApiBase + 'qr')
        .then(r => r.json())
        .then(data => {
            if (data.qr) {
                document.getElementById('waQRCode').src = data.qr;
                document.getElementById('waQRContainer').style.display = 'block';
                setTimeout(checkSessionStatus, 5000);
            }
        })
        .catch(err => console.error('QR fetch error:', err));
}

function initializeSession() {
    const statusDiv = document.getElementById('waSessionStatus');
    statusDiv.innerHTML = '<span class="text-info"><i class="bi bi-hourglass-split"></i> Starting session...</span>';
    
    fetch(waApiBase + 'initialize', { method: 'POST' })
        .then(r => r.json())
        .then(data => {
            setTimeout(checkSessionStatus, 3000);
        })
        .catch(err => {
            statusDiv.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle"></i> Failed to start</span>';
        });
}

function logoutSession() {
    if (!confirm('Are you sure you want to logout from WhatsApp?')) return;
    
    const statusDiv = document.getElementById('waSessionStatus');
    statusDiv.innerHTML = '<span class="text-info"><i class="bi bi-hourglass-split"></i> Logging out...</span>';
    
    fetch(waApiBase + 'logout', { method: 'POST' })
        .then(r => r.json())
        .then(data => {
            statusDiv.innerHTML = '<span class="text-secondary"><i class="bi bi-check-circle"></i> Logged out</span>';
            document.getElementById('waQRContainer').style.display = 'none';
            document.getElementById('waGroupsList').style.display = 'none';
        })
        .catch(err => {
            statusDiv.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle"></i> Logout failed</span>';
        });
}

function loadGroups() {
    const groupsBody = document.getElementById('waGroupsBody');
    groupsBody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">Loading...</td></tr>';
    document.getElementById('waGroupsList').style.display = 'block';
    
    fetch(waApiBase + 'groups')
        .then(r => r.json())
        .then(data => {
            if (data.groups && data.groups.length > 0) {
                groupsBody.innerHTML = data.groups.map(g => 
                    '<tr><td>' + g.name + '</td><td>' + (g.participantsCount || 'N/A') + '</td><td><button class="btn btn-sm btn-outline-success" onclick="testGroupMessage(\'' + g.id + '\')"><i class="bi bi-send"></i></button></td></tr>'
                ).join('');
            } else {
                groupsBody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">No groups found</td></tr>';
            }
        })
        .catch(err => {
            groupsBody.innerHTML = '<tr><td colspan="3" class="text-center text-danger">Failed to load groups</td></tr>';
        });
}

function testGroupMessage(groupId) {
    const message = prompt('Enter test message for this group:');
    if (!message) return;
    
    fetch(waApiBase + 'send-group', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ groupId, message })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('Message sent successfully!');
        } else {
            alert('Failed: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(err => alert('Error: ' + err.message));
}

function testWhatsAppGateway() {
    const provider = document.getElementById('whatsappProvider').value;
    if (provider === 'session') {
        checkSessionStatus();
    } else {
        alert('WhatsApp gateway test - Coming soon! For now, save settings and try sending a message from a ticket.');
    }
}

let cachedGroups = [];
let targetInputId = null;

function fetchSummaryGroups() {
    const container = document.getElementById('waSummaryGroupsContainer');
    container.innerHTML = '<span class="text-info"><i class="bi bi-hourglass-split"></i> Loading groups...</span>';
    
    fetch(waApiBase + 'groups')
        .then(r => r.json())
        .then(data => {
            cachedGroups = data.groups || [];
            if (cachedGroups.length > 0) {
                renderSummaryGroupCheckboxes();
            } else {
                container.innerHTML = '<span class="text-warning"><i class="bi bi-exclamation-triangle"></i> No groups found. Make sure WhatsApp is connected.</span>';
            }
        })
        .catch(err => {
            container.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle"></i> Failed to load groups: ' + err.message + '</span>';
        });
}

function renderSummaryGroupCheckboxes() {
    const container = document.getElementById('waSummaryGroupsContainer');
    const savedGroups = JSON.parse(document.getElementById('waDailySummaryGroups').value || '[]');
    const savedIds = savedGroups.map(g => g.id || g);
    
    let html = '<div class="row g-2">';
    cachedGroups.forEach(g => {
        const checked = savedIds.includes(g.id) ? 'checked' : '';
        html += `
            <div class="col-md-6">
                <div class="form-check">
                    <input class="form-check-input summary-group-check" type="checkbox" value="${g.id}" id="grp_${g.id.replace(/[^a-z0-9]/gi, '')}" ${checked} onchange="updateSummaryGroups()">
                    <label class="form-check-label small" for="grp_${g.id.replace(/[^a-z0-9]/gi, '')}">
                        <strong>${g.name}</strong> <small class="text-muted">(${g.participantsCount || '?'} members)</small>
                    </label>
                </div>
            </div>`;
    });
    html += '</div>';
    container.innerHTML = html;
}

function updateSummaryGroups() {
    const checkboxes = document.querySelectorAll('.summary-group-check:checked');
    const selectedGroups = [];
    checkboxes.forEach(cb => {
        const group = cachedGroups.find(g => g.id === cb.value);
        if (group) {
            selectedGroups.push({ id: group.id, name: group.name });
        }
    });
    document.getElementById('waDailySummaryGroups').value = JSON.stringify(selectedGroups);
}

function selectOperationsGroup() {
    const listContainer = document.getElementById('groupSelectList');
    
    if (cachedGroups.length === 0) {
        listContainer.innerHTML = '<span class="text-muted">Loading groups...</span>';
        fetch(waApiBase + 'groups')
            .then(r => r.json())
            .then(data => {
                cachedGroups = data.groups || [];
                renderOperationsGroupList();
            })
            .catch(err => {
                listContainer.innerHTML = '<span class="text-danger">Failed to load: ' + err.message + '</span>';
            });
    } else {
        renderOperationsGroupList();
    }
    
    new bootstrap.Modal(document.getElementById('groupSelectModal')).show();
}

function renderOperationsGroupList() {
    const listContainer = document.getElementById('groupSelectList');
    const currentValue = document.getElementById('whatsappOperationsGroupId').value;
    
    let html = '<div class="list-group">';
    cachedGroups.forEach(g => {
        const selected = g.id === currentValue ? 'active' : '';
        html += `<a href="#" class="list-group-item list-group-item-action ${selected}" onclick="selectOperationsGroupItem('${g.id}', '${g.name.replace(/'/g, "\\'")}'); return false;">
            <i class="bi bi-people me-2"></i> ${g.name}
            <small class="text-muted d-block">${g.id}</small>
        </a>`;
    });
    html += '</div>';
    listContainer.innerHTML = html;
}

function selectOperationsGroupItem(groupId, groupName) {
    document.getElementById('whatsappOperationsGroupId').value = groupId;
    bootstrap.Modal.getInstance(document.getElementById('groupSelectModal')).hide();
}

function selectGroupFor(inputId) {
    targetInputId = inputId;
    const listContainer = document.getElementById('groupSelectList');
    
    if (cachedGroups.length === 0) {
        listContainer.innerHTML = '<span class="text-muted">Loading groups...</span>';
        fetch(waApiBase + 'groups')
            .then(r => r.json())
            .then(data => {
                cachedGroups = data.groups || [];
                renderGroupSelectList();
            })
            .catch(err => {
                listContainer.innerHTML = '<span class="text-danger">Failed to load groups</span>';
            });
    } else {
        renderGroupSelectList();
    }
    
    new bootstrap.Modal(document.getElementById('groupSelectModal')).show();
}

function renderGroupSelectList() {
    const listContainer = document.getElementById('groupSelectList');
    if (cachedGroups.length === 0) {
        listContainer.innerHTML = '<span class="text-warning">No groups found. Connect WhatsApp first.</span>';
        return;
    }
    
    let html = '';
    cachedGroups.forEach(g => {
        html += `<button type="button" class="list-group-item list-group-item-action" onclick="selectGroup('${g.id}', '${g.name.replace(/'/g, "\\'")}')">
            <strong>${g.name}</strong> <small class="text-muted">(${g.participantsCount || '?'} members)</small>
        </button>`;
    });
    listContainer.innerHTML = html;
}

function selectGroup(groupId, groupName) {
    if (targetInputId) {
        document.getElementById(targetInputId).value = groupId;
    }
    bootstrap.Modal.getInstance(document.getElementById('groupSelectModal')).hide();
}

function testDailySummary() {
    if (!confirm('Send a test daily summary to all configured groups now?')) return;
    
    fetch('cron.php?action=daily_summary&secret=<?= htmlspecialchars($settings->get("cron_secret", "isp-crm-cron-2024")) ?>')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('Test summary sent! Groups: ' + data.groups_sent + ', Employees: ' + data.employees_count + ', Tickets: ' + data.tickets_count);
            } else {
                alert('Failed: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(err => alert('Error: ' + err.message));
}

document.addEventListener('DOMContentLoaded', function() {
    const savedGroups = document.getElementById('waDailySummaryGroups')?.value;
    if (savedGroups && savedGroups !== '[]') {
        const groups = JSON.parse(savedGroups);
        if (groups.length > 0) {
            const container = document.getElementById('waSummaryGroupsContainer');
            let html = '<div class="mb-2"><small class="text-success"><i class="bi bi-check-circle"></i> ' + groups.length + ' group(s) selected</small></div>';
            html += '<ul class="list-unstyled small mb-0">';
            groups.forEach(g => {
                html += '<li><i class="bi bi-people-fill text-primary"></i> ' + (g.name || g.id) + '</li>';
            });
            html += '</ul><div class="mt-2"><small class="text-muted">Click "Fetch Groups" to modify selection</small></div>';
            container.innerHTML = html;
        }
    }
});
</script>

<div class="card mt-4 border-success">
    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-chat-text"></i> WhatsApp Message Templates</h5>
        <small class="text-white-50">Customize your notification messages</small>
    </div>
    <div class="card-body">
        <div class="alert alert-info mb-4">
            <i class="bi bi-info-circle"></i> <strong>Available Variables:</strong> 
            <code>{customer_name}</code>, <code>{ticket_number}</code>, <code>{order_number}</code>, <code>{complaint_number}</code>, 
            <code>{subject}</code>, <code>{status}</code>, <code>{category}</code>, <code>{package_name}</code>, <code>{amount}</code>
        </div>
        
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="save_whatsapp_templates">
            
            <?php
            $waTemplates = [
                'wa_template_status_update' => ['label' => 'Ticket Status Update', 'icon' => 'arrow-repeat', 'group' => 'Tickets'],
                'wa_template_need_info' => ['label' => 'Need More Information', 'icon' => 'question-circle', 'group' => 'Tickets'],
                'wa_template_resolved' => ['label' => 'Ticket Resolved', 'icon' => 'check-circle', 'group' => 'Tickets'],
                'wa_template_technician_coming' => ['label' => 'Technician On The Way', 'icon' => 'truck', 'group' => 'Tickets'],
                'wa_template_scheduled' => ['label' => 'Service Scheduled', 'icon' => 'calendar-check', 'group' => 'Tickets'],
                'wa_template_order_confirmation' => ['label' => 'Order Confirmation', 'icon' => 'bag-check', 'group' => 'Orders'],
                'wa_template_order_processing' => ['label' => 'Order Processing', 'icon' => 'clock', 'group' => 'Orders'],
                'wa_template_order_installation' => ['label' => 'Schedule Installation', 'icon' => 'tools', 'group' => 'Orders'],
                'wa_template_complaint_received' => ['label' => 'Complaint Received', 'icon' => 'envelope-check', 'group' => 'Complaints'],
                'wa_template_complaint_review' => ['label' => 'Under Review', 'icon' => 'hourglass-split', 'group' => 'Complaints'],
                'wa_template_complaint_approved' => ['label' => 'Complaint Approved', 'icon' => 'check-circle', 'group' => 'Complaints'],
                'wa_template_complaint_rejected' => ['label' => 'Complaint Rejected', 'icon' => 'x-circle', 'group' => 'Complaints']
            ];
            
            $waTemplateDefaults = [
                'wa_template_status_update' => "Hi {customer_name},\n\nThis is an update on your ticket #{ticket_number}.\n\nCurrent Status: {status}\n\nWe're working on resolving your issue. Thank you for your patience.",
                'wa_template_need_info' => "Hi {customer_name},\n\nRegarding ticket #{ticket_number}: {subject}\n\nWe need some additional information to proceed. Could you please provide more details?\n\nThank you.",
                'wa_template_resolved' => "Hi {customer_name},\n\nGreat news! Your ticket #{ticket_number} has been resolved.\n\nIf you have any further questions or issues, please don't hesitate to contact us.\n\nThank you for choosing our services!",
                'wa_template_technician_coming' => "Hi {customer_name},\n\nRegarding ticket #{ticket_number}:\n\nOur technician is on the way to your location. Please ensure someone is available to receive them.\n\nThank you.",
                'wa_template_scheduled' => "Hi {customer_name},\n\nYour service visit for ticket #{ticket_number} has been scheduled.\n\nPlease confirm if this time works for you.\n\nThank you.",
                'wa_template_order_confirmation' => "Hi {customer_name},\n\nThank you for your order #{order_number}!\n\nPackage: {package_name}\nAmount: KES {amount}\n\nWe will contact you shortly to schedule installation.\n\nThank you for choosing our services!",
                'wa_template_order_processing' => "Hi {customer_name},\n\nYour order #{order_number} is being processed.\n\nOur team will contact you to schedule the installation.\n\nThank you!",
                'wa_template_order_installation' => "Hi {customer_name},\n\nWe're ready to install your service for order #{order_number}.\n\nPlease let us know a convenient time for installation.\n\nThank you!",
                'wa_template_complaint_received' => "Hi {customer_name},\n\nWe have received your complaint (Ref: {complaint_number}).\n\nCategory: {category}\n\nOur team will review and respond within 24 hours.\n\nThank you for your feedback.",
                'wa_template_complaint_review' => "Hi {customer_name},\n\nRegarding your complaint {complaint_number}:\n\nWe are currently reviewing your issue and will update you soon.\n\nThank you for your patience.",
                'wa_template_complaint_approved' => "Hi {customer_name},\n\nYour complaint {complaint_number} has been approved and a support ticket will be created.\n\nOur team will contact you shortly to resolve the issue.\n\nThank you!",
                'wa_template_complaint_rejected' => "Hi {customer_name},\n\nRegarding your complaint {complaint_number}:\n\nAfter careful review, we were unable to proceed with this complaint.\n\nIf you have any questions, please contact our support team.\n\nThank you."
            ];
            ?>
            
            <?php 
            $groups = [];
            foreach ($waTemplates as $key => $template) {
                $groups[$template['group']][$key] = $template;
            }
            ?>
            
            <?php foreach ($groups as $groupName => $groupTemplates): ?>
            <h6 class="text-muted mb-3 mt-<?= $groupName === 'Tickets' ? '0' : '4' ?>">
                <i class="bi bi-<?= $groupName === 'Tickets' ? 'ticket' : ($groupName === 'Orders' ? 'cart' : 'exclamation-triangle') ?>"></i> 
                <?= $groupName ?>
            </h6>
            <div class="row g-3 mb-3">
                <?php foreach ($groupTemplates as $key => $template): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100">
                        <div class="card-header bg-light py-2">
                            <i class="bi bi-<?= $template['icon'] ?> text-success"></i> <?= $template['label'] ?>
                        </div>
                        <div class="card-body p-2">
                            <textarea class="form-control" name="<?= $key ?>" rows="5" 
                                      style="font-size: 0.8rem;"><?= htmlspecialchars($settings->get($key, $waTemplateDefaults[$key] ?? '')) ?></textarea>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
            
            <div class="mt-4">
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-check-lg"></i> Save WhatsApp Templates
                </button>
                <button type="button" class="btn btn-outline-secondary" onclick="resetTemplates()">
                    <i class="bi bi-arrow-counterclockwise"></i> Reset to Defaults
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function resetTemplates() {
    if (!confirm('Reset all WhatsApp templates to defaults?')) return;
    
    const defaults = {
        'wa_template_status_update': `Hi {customer_name},

This is an update on your ticket #{ticket_number}.

Current Status: {status}

We're working on resolving your issue. Thank you for your patience.`,
        'wa_template_need_info': `Hi {customer_name},

Regarding ticket #{ticket_number}: {subject}

We need some additional information to proceed. Could you please provide more details?

Thank you.`,
        'wa_template_resolved': `Hi {customer_name},

Great news! Your ticket #{ticket_number} has been resolved.

If you have any further questions or issues, please don't hesitate to contact us.

Thank you for choosing our services!`,
        'wa_template_technician_coming': `Hi {customer_name},

Regarding ticket #{ticket_number}:

Our technician is on the way to your location. Please ensure someone is available to receive them.

Thank you.`,
        'wa_template_scheduled': `Hi {customer_name},

Your service visit for ticket #{ticket_number} has been scheduled.

Please confirm if this time works for you.

Thank you.`,
        'wa_template_order_confirmation': `Hi {customer_name},

Thank you for your order #{order_number}!

Package: {package_name}
Amount: KES {amount}

We will contact you shortly to schedule installation.

Thank you for choosing our services!`,
        'wa_template_order_processing': `Hi {customer_name},

Your order #{order_number} is being processed.

Our team will contact you to schedule the installation.

Thank you!`,
        'wa_template_order_installation': `Hi {customer_name},

We're ready to install your service for order #{order_number}.

Please let us know a convenient time for installation.

Thank you!`,
        'wa_template_complaint_received': `Hi {customer_name},

We have received your complaint (Ref: {complaint_number}).

Category: {category}

Our team will review and respond within 24 hours.

Thank you for your feedback.`,
        'wa_template_complaint_review': `Hi {customer_name},

Regarding your complaint {complaint_number}:

We are currently reviewing your issue and will update you soon.

Thank you for your patience.`,
        'wa_template_complaint_approved': `Hi {customer_name},

Your complaint {complaint_number} has been approved and a support ticket will be created.

Our team will contact you shortly to resolve the issue.

Thank you!`,
        'wa_template_complaint_rejected': `Hi {customer_name},

Regarding your complaint {complaint_number}:

After careful review, we were unable to proceed with this complaint.

If you have any questions, please contact our support team.

Thank you.`
    };
    
    for (const [key, value] of Object.entries(defaults)) {
        const textarea = document.querySelector(`textarea[name="${key}"]`);
        if (textarea) textarea.value = value;
    }
}
</script>

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
                                    <span class="badge bg-<?= $device['device_type'] === 'zkteco' ? 'info' : ($device['device_type'] === 'biotime_cloud' ? 'success' : 'warning') ?>">
                                        <?= $device['device_type'] === 'biotime_cloud' ? 'BIOTIME' : strtoupper($device['device_type']) ?>
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
                                                title="Sync Attendance">
                                            <i class="bi bi-arrow-repeat"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-warning fetch-users-btn" 
                                                data-device-id="<?= $device['id'] ?>" 
                                                data-device-name="<?= htmlspecialchars($device['name']) ?>"
                                                title="View Registered Users">
                                            <i class="bi bi-person-lines-fill"></i>
                                        </button>
                                        <a href="?page=settings&subpage=biometric&action=map_users&id=<?= $device['id'] ?>" 
                                           class="btn btn-outline-primary" title="Map Users to Employees">
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
        
        <div class="card mt-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-cloud-upload text-success"></i> Push Protocol (Real-Time)</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info small mb-3">
                    <strong>Recommended for Real-Time Attendance!</strong><br>
                    Push Protocol makes your device send attendance data to your server instantly when employees clock in/out.
                </div>
                
                <h6>Push Server URL:</h6>
                <div class="input-group mb-3">
                    <input type="text" class="form-control form-control-sm bg-light" readonly 
                           value="<?= (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'your-domain.com') ?>/biometric-api.php" id="pushUrl">
                    <button class="btn btn-outline-secondary btn-sm" type="button" onclick="copyPushUrl()">
                        <i class="bi bi-clipboard"></i> Copy
                    </button>
                </div>
                
                <h6>ZKTeco K40/K50/K60 Setup:</h6>
                <ol class="small mb-3">
                    <li>On device menu: <strong>COMM</strong> → <strong>Cloud Server Setting</strong> (or <strong>Ethernet</strong> → <strong>Push Options</strong>)</li>
                    <li>Enable: <strong>Enable Cloud Server</strong></li>
                    <li>Set <strong>Server Address</strong> to your domain (without https://)</li>
                    <li>Set <strong>Server Port</strong>: <code>443</code> (for HTTPS) or <code>80</code> (for HTTP)</li>
                    <li>Save and restart the device</li>
                </ol>
                
                <h6>Alternative: Using Push URL directly (newer firmware):</h6>
                <ol class="small mb-3">
                    <li>On device: <strong>COMM</strong> → <strong>Push Options</strong></li>
                    <li>Set <strong>Push Server Address</strong> to the URL above</li>
                    <li>Enable <strong>Push</strong></li>
                </ol>
                
                <div class="alert alert-success small mb-0">
                    <strong>Important:</strong> Make sure you add the device's <strong>Serial Number</strong> in the device settings above. 
                    The device sends its serial number with each push, and the system uses it to identify which device is sending data.
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
                            <option value="zkteco" <?= ($editDevice['device_type'] ?? '') === 'zkteco' ? 'selected' : '' ?>>ZKTeco (Direct)</option>
                            <option value="hikvision" <?= ($editDevice['device_type'] ?? '') === 'hikvision' ? 'selected' : '' ?>>Hikvision</option>
                            <option value="biotime_cloud" <?= ($editDevice['device_type'] ?? '') === 'biotime_cloud' ? 'selected' : '' ?>>BioTime Cloud (ZKTeco)</option>
                        </select>
                        <small class="text-muted" id="deviceTypeHelp">Select BioTime Cloud for real-time attendance sync via BioTime API</small>
                    </div>
                    
                    <div class="mb-3" id="ipAddressField">
                        <label class="form-label">IP Address / Server <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="ip_address" id="deviceIpAddress"
                               value="<?= htmlspecialchars($editDevice['ip_address'] ?? '') ?>"
                               placeholder="192.168.1.201">
                        <small class="text-muted" id="ipHelp">Device IP for direct connection, or BioTime server IP/hostname</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Port</label>
                        <input type="number" class="form-control" name="port" id="devicePort"
                               value="<?= $editDevice['port'] ?? '4370' ?>"
                               min="1" max="65535">
                        <small class="text-muted" id="portHelp">ZKTeco: 4370, Hikvision: 80, BioTime: 8090</small>
                    </div>
                    
                    <div class="mb-3" id="apiBaseUrlField" style="display: none;">
                        <label class="form-label">API Base URL</label>
                        <input type="text" class="form-control" name="api_base_url" id="apiBaseUrl"
                               value="<?= htmlspecialchars($editDevice['api_base_url'] ?? '') ?>"
                               placeholder="https://companyname.biotimecloud.com or http://192.168.1.100:8090">
                        <small class="text-muted">For hosted BioTime Cloud, use https://yourcompany.biotimecloud.com</small>
                    </div>
                    
                    <div class="mb-3" id="companyNameField" style="display: none;">
                        <label class="form-label">Company Name (for BioTime Cloud)</label>
                        <input type="text" class="form-control" name="company_name" id="companyName"
                               value="<?= htmlspecialchars($editDevice['company_name'] ?? '') ?>"
                               placeholder="Your registered company name">
                        <small class="text-muted">Required for hosted biotimecloud.com. Leave blank for on-premise BioTime.</small>
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
                        <label class="form-label">Serial Number</label>
                        <input type="text" class="form-control" name="serial_number"
                               value="<?= htmlspecialchars($editDevice['serial_number'] ?? '') ?>"
                               placeholder="For Push Protocol identification">
                        <small class="text-muted">Required for Push Protocol. Find in device menu or test connection.</small>
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
        var type = this.value;
        var port = type === 'zkteco' ? 4370 : (type === 'biotime_cloud' ? 8090 : 80);
        document.getElementById('devicePort').value = port;
        
        var ipInput = document.getElementById('deviceIpAddress');
        var ipHelp = document.getElementById('ipHelp');
        var portHelp = document.getElementById('portHelp');
        var apiBaseUrlField = document.getElementById('apiBaseUrlField');
        
        var companyNameField = document.getElementById('companyNameField');
        
        if (type === 'biotime_cloud') {
            ipInput.placeholder = 'biotime.example.com or 192.168.1.100';
            ipHelp.textContent = 'BioTime Cloud server IP or hostname';
            ipInput.removeAttribute('pattern');
            apiBaseUrlField.style.display = 'block';
            companyNameField.style.display = 'block';
        } else {
            ipInput.placeholder = '192.168.1.201';
            ipHelp.textContent = 'Device IP for direct connection';
            ipInput.setAttribute('pattern', '^(?:[0-9]{1,3}\\.){3}[0-9]{1,3}$');
            apiBaseUrlField.style.display = 'none';
            companyNameField.style.display = 'none';
        }
    });
    
    document.getElementById('deviceType').dispatchEvent(new Event('change'));
    </script>
    <?php endif; ?>
    
    <?php if ($action === 'map_users' && $id): ?>
    <?php
    $deviceUsers = [];
    $deviceConnectionError = null;
    $mappings = [];
    $employees = [];
    $mappedDeviceUsers = [];
    
    try {
        $deviceUsers = $biometricService->getDeviceUsers($id);
    } catch (\Throwable $e) {
        $deviceConnectionError = $e->getMessage();
    }
    
    try {
        $mappings = $biometricService->getUserMappings($id);
        $mappedDeviceUsers = array_column($mappings, 'device_user_id');
    } catch (\Throwable $e) {
        // Mappings table might not exist
    }
    
    try {
        $employees = (new \App\Employee($dbConn))->getEmployees();
    } catch (\Throwable $e) {
        // Employees might not be available
    }
    ?>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-people"></i> User Mappings</h5>
            </div>
            <div class="card-body">
                <?php if (empty($deviceUsers)): ?>
                <div class="alert alert-warning small">
                    <i class="bi bi-exclamation-triangle"></i> 
                    <?php if ($deviceConnectionError): ?>
                    Could not connect to device: <?= htmlspecialchars($deviceConnectionError) ?>
                    <?php else: ?>
                    Could not fetch users from device. The server may not be able to reach the biometric device. You can still add mappings manually.
                    <?php endif; ?>
                </div>
                <?php endif; ?>
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
                <form method="POST" action="?page=settings&subpage=biometric&action=map_users&id=<?= $id ?>">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="save_user_mapping">
                    <input type="hidden" name="device_id" value="<?= $id ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Device User ID</label>
                        <?php if (!empty($deviceUsers)): ?>
                        <select class="form-select" name="device_user_id" required>
                            <option value="">Select user from device...</option>
                            <?php foreach ($deviceUsers as $du): ?>
                            <?php $duId = $du['device_user_id'] ?? $du['user_id'] ?? ''; ?>
                            <?php if ($duId && !in_array($duId, $mappedDeviceUsers)): ?>
                            <option value="<?= htmlspecialchars($duId) ?>">
                                <?= htmlspecialchars($duId) ?> - <?= htmlspecialchars($du['name'] ?: 'No Name') ?>
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

<div class="modal fade" id="deviceUsersModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-lines-fill"></i> Registered Users - <span id="usersDeviceName"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="usersLoading" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 mb-0">Fetching users from device...</p>
                    <p class="small text-muted">This may take a moment...</p>
                </div>
                <div id="usersResult" class="d-none">
                    <div id="usersSuccess" class="d-none">
                        <div class="alert alert-info mb-3">
                            <i class="bi bi-info-circle"></i> Found <strong id="usersCount">0</strong> registered users
                        </div>
                        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                            <table class="table table-sm table-striped mb-0">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th>UID</th>
                                        <th>User ID</th>
                                        <th>Name</th>
                                        <th>Role</th>
                                        <th>Card No</th>
                                    </tr>
                                </thead>
                                <tbody id="usersTableBody">
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div id="usersEmpty" class="d-none">
                        <div class="alert alert-warning mb-0">
                            <i class="bi bi-exclamation-triangle"></i> No users found on device
                            <p class="mb-0 mt-2 small">The device may not have any registered users, or the data format is not supported.</p>
                        </div>
                    </div>
                    <div id="usersFailed" class="d-none">
                        <div class="alert alert-danger mb-0">
                            <i class="bi bi-x-circle-fill"></i> Failed to fetch users
                            <p class="mb-0 mt-2 small" id="usersErrorMessage"></p>
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

document.querySelectorAll('.fetch-users-btn').forEach(btn => {
    btn.addEventListener('click', async function() {
        const deviceId = this.dataset.deviceId;
        const deviceName = this.dataset.deviceName;
        
        document.getElementById('usersDeviceName').textContent = deviceName;
        document.getElementById('usersLoading').classList.remove('d-none');
        document.getElementById('usersResult').classList.add('d-none');
        document.getElementById('usersSuccess').classList.add('d-none');
        document.getElementById('usersEmpty').classList.add('d-none');
        document.getElementById('usersFailed').classList.add('d-none');
        document.getElementById('usersTableBody').innerHTML = '';
        
        const modal = new bootstrap.Modal(document.getElementById('deviceUsersModal'));
        modal.show();
        
        try {
            const response = await fetch(`?page=api&action=fetch_biometric_users&device_id=${deviceId}`);
            const result = await response.json();
            
            document.getElementById('usersLoading').classList.add('d-none');
            document.getElementById('usersResult').classList.remove('d-none');
            
            if (result.success) {
                if (result.users && result.users.length > 0) {
                    document.getElementById('usersSuccess').classList.remove('d-none');
                    document.getElementById('usersCount').textContent = result.count || result.users.length;
                    
                    const tbody = document.getElementById('usersTableBody');
                    result.users.forEach(user => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${user.uid || '-'}</td>
                            <td><strong>${user.device_user_id || '-'}</strong></td>
                            <td>${user.name || '<em class="text-muted">No Name</em>'}</td>
                            <td>${user.role === 0 ? 'User' : (user.role === 14 ? 'Admin' : user.role)}</td>
                            <td>${user.card_no && user.card_no > 0 ? user.card_no : '-'}</td>
                        `;
                        tbody.appendChild(row);
                    });
                } else {
                    document.getElementById('usersEmpty').classList.remove('d-none');
                }
            } else {
                document.getElementById('usersFailed').classList.remove('d-none');
                document.getElementById('usersErrorMessage').textContent = result.message || 'Unknown error';
            }
        } catch (error) {
            document.getElementById('usersLoading').classList.add('d-none');
            document.getElementById('usersResult').classList.remove('d-none');
            document.getElementById('usersFailed').classList.remove('d-none');
            document.getElementById('usersErrorMessage').textContent = 'Network error: ' + error.message;
        }
    });
});

function copyPushUrl() {
    const pushUrl = document.getElementById('pushUrl');
    pushUrl.select();
    pushUrl.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(pushUrl.value).then(() => {
        const btn = event.target.closest('button');
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check"></i> Copied!';
        btn.classList.remove('btn-outline-secondary');
        btn.classList.add('btn-success');
        setTimeout(() => {
            btn.innerHTML = originalHtml;
            btn.classList.remove('btn-success');
            btn.classList.add('btn-outline-secondary');
        }, 2000);
    });
}
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
$latePenaltiesEnabled = $settings->get('late_penalties_enabled', '1') === '1';
?>

<!-- Global Late Penalty Toggle -->
<div class="card mb-4 border-warning">
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h5 class="mb-1"><i class="bi bi-toggle-on text-warning"></i> Late Arrival Penalties</h5>
                <p class="text-muted mb-0">When disabled, no late penalties will be calculated or applied to attendance records.</p>
            </div>
            <div class="col-md-4 text-end">
                <form method="POST" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="toggle_late_penalties">
                    <input type="hidden" name="enabled" value="<?= $latePenaltiesEnabled ? '0' : '1' ?>">
                    <?php if ($latePenaltiesEnabled): ?>
                    <button type="submit" class="btn btn-warning" onclick="return confirm('Disable late penalties? No new penalties will be calculated.')">
                        <i class="bi bi-toggle-on"></i> Enabled - Click to Disable
                    </button>
                    <?php else: ?>
                    <button type="submit" class="btn btn-outline-secondary">
                        <i class="bi bi-toggle-off"></i> Disabled - Click to Enable
                    </button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>

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

<?php elseif ($subpage === 'contact'): ?>
<?php $contactSettings = $settings->getContactSettings(); ?>

<form method="POST">
    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
    <input type="hidden" name="action" value="save_contact_settings">
    
    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-telephone"></i> Contact Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Primary Phone Number</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                                <input type="tel" class="form-control" name="contact_phone" 
                                       value="<?= htmlspecialchars($contactSettings['contact_phone']) ?>"
                                       placeholder="+254 700 000 000">
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Secondary Phone Number</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                                <input type="tel" class="form-control" name="contact_phone_2" 
                                       value="<?= htmlspecialchars($contactSettings['contact_phone_2']) ?>"
                                       placeholder="+254 700 000 001">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">General Email</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                <input type="email" class="form-control" name="contact_email" 
                                       value="<?= htmlspecialchars($contactSettings['contact_email']) ?>"
                                       placeholder="info@company.com">
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Support Email</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-headset"></i></span>
                                <input type="email" class="form-control" name="contact_email_support" 
                                       value="<?= htmlspecialchars($contactSettings['contact_email_support']) ?>"
                                       placeholder="support@company.com">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">WhatsApp Number</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-whatsapp"></i></span>
                                <input type="tel" class="form-control" name="contact_whatsapp" 
                                       value="<?= htmlspecialchars($contactSettings['contact_whatsapp']) ?>"
                                       placeholder="+254 700 000 000">
                            </div>
                            <div class="form-text">Include country code for WhatsApp link to work</div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Street Address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-geo-alt"></i></span>
                            <input type="text" class="form-control" name="contact_address" 
                                   value="<?= htmlspecialchars($contactSettings['contact_address']) ?>"
                                   placeholder="123 Main Street, Building Name">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">City</label>
                            <input type="text" class="form-control" name="contact_city" 
                                   value="<?= htmlspecialchars($contactSettings['contact_city']) ?>"
                                   placeholder="Nairobi">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Country</label>
                            <input type="text" class="form-control" name="contact_country" 
                                   value="<?= htmlspecialchars($contactSettings['contact_country']) ?>"
                                   placeholder="Kenya">
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-clock"></i> Business Hours</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Working Days</label>
                            <input type="text" class="form-control" name="working_days" 
                                   value="<?= htmlspecialchars($contactSettings['working_days']) ?>"
                                   placeholder="Monday - Friday">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Working Hours</label>
                            <input type="text" class="form-control" name="working_hours" 
                                   value="<?= htmlspecialchars($contactSettings['working_hours']) ?>"
                                   placeholder="8:00 AM - 5:00 PM">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Support Hours</label>
                            <input type="text" class="form-control" name="support_hours" 
                                   value="<?= htmlspecialchars($contactSettings['support_hours']) ?>"
                                   placeholder="24/7">
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-share"></i> Social Media Links</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="bi bi-facebook text-primary"></i> Facebook</label>
                            <input type="url" class="form-control" name="social_facebook" 
                                   value="<?= htmlspecialchars($contactSettings['social_facebook']) ?>"
                                   placeholder="https://facebook.com/yourpage">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="bi bi-twitter-x"></i> Twitter / X</label>
                            <input type="url" class="form-control" name="social_twitter" 
                                   value="<?= htmlspecialchars($contactSettings['social_twitter']) ?>"
                                   placeholder="https://twitter.com/yourhandle">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="bi bi-instagram text-danger"></i> Instagram</label>
                            <input type="url" class="form-control" name="social_instagram" 
                                   value="<?= htmlspecialchars($contactSettings['social_instagram']) ?>"
                                   placeholder="https://instagram.com/yourpage">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="bi bi-linkedin text-primary"></i> LinkedIn</label>
                            <input type="url" class="form-control" name="social_linkedin" 
                                   value="<?= htmlspecialchars($contactSettings['social_linkedin']) ?>"
                                   placeholder="https://linkedin.com/company/yourcompany">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="bi bi-youtube text-danger"></i> YouTube</label>
                            <input type="url" class="form-control" name="social_youtube" 
                                   value="<?= htmlspecialchars($contactSettings['social_youtube']) ?>"
                                   placeholder="https://youtube.com/yourchannel">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="bi bi-tiktok"></i> TikTok</label>
                            <input type="url" class="form-control" name="social_tiktok" 
                                   value="<?= htmlspecialchars($contactSettings['social_tiktok']) ?>"
                                   placeholder="https://tiktok.com/@yourhandle">
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-map"></i> Map Location</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Google Maps Embed URL</label>
                        <input type="url" class="form-control" name="map_embed_url" 
                               value="<?= htmlspecialchars($contactSettings['map_embed_url']) ?>"
                               placeholder="https://www.google.com/maps/embed?pb=...">
                        <div class="form-text">
                            Go to Google Maps, find your location, click "Share" > "Embed a map" and paste the URL here.
                        </div>
                    </div>
                    <?php if (!empty($contactSettings['map_embed_url'])): ?>
                    <div class="ratio ratio-16x9 mt-3">
                        <iframe src="<?= htmlspecialchars($contactSettings['map_embed_url']) ?>" 
                                style="border:0; border-radius: 8px;" allowfullscreen="" loading="lazy"></iframe>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="bi bi-save"></i> Save Contact Settings
            </button>
        </div>
        
        <div class="col-lg-4">
            <div class="card bg-light">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-lightbulb"></i> Tips</h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2"><i class="bi bi-check-circle text-success"></i> Include country code for phone numbers</li>
                        <li class="mb-2"><i class="bi bi-check-circle text-success"></i> Use full URLs for social media links</li>
                        <li class="mb-2"><i class="bi bi-check-circle text-success"></i> WhatsApp number enables click-to-chat</li>
                        <li class="mb-2"><i class="bi bi-check-circle text-success"></i> Map embed shows your location on landing page</li>
                    </ul>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-eye"></i> Preview</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-2">These details will appear on your landing page's contact section.</p>
                    <a href="?page=landing#contact" target="_blank" class="btn btn-outline-primary w-100">
                        <i class="bi bi-box-arrow-up-right"></i> View Landing Page
                    </a>
                </div>
            </div>
        </div>
    </div>
</form>

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
                    <h5 class="mb-0"><i class="bi bi-phone"></i> M-Pesa API Credentials</h5>
                </div>
                <div class="card-body">
                    <input type="hidden" name="mpesa_environment" value="production">
                    
                    <div class="mb-3">
                        <label class="form-label">Shortcode (Paybill/Till)</label>
                        <input type="text" class="form-control" name="mpesa_shortcode" 
                               value="<?= htmlspecialchars($mpesaConfig['mpesa_shortcode'] ?? '') ?>"
                               placeholder="e.g. 174379">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Consumer Key</label>
                            <input type="text" class="form-control" name="mpesa_consumer_key" 
                                   value="<?= htmlspecialchars($mpesaConfig['mpesa_consumer_key'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Consumer Secret</label>
                            <input type="password" class="form-control" name="mpesa_consumer_secret" 
                                   value="<?= htmlspecialchars($mpesaConfig['mpesa_consumer_secret'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Passkey</label>
                        <input type="text" class="form-control" name="mpesa_passkey" 
                               value="<?= htmlspecialchars($mpesaConfig['mpesa_passkey'] ?? '') ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Callback URL</label>
                        <input type="url" class="form-control" name="mpesa_callback_url" 
                               value="<?= htmlspecialchars($mpesaConfig['mpesa_callback_url'] ?? $mpesa->getCallbackUrl()) ?>">
                    </div>
                    
                    <input type="hidden" name="mpesa_validation_url" value="<?= htmlspecialchars($mpesaConfig['mpesa_validation_url'] ?? $mpesa->getValidationUrl()) ?>">
                    <input type="hidden" name="mpesa_confirmation_url" value="<?= htmlspecialchars($mpesaConfig['mpesa_confirmation_url'] ?? $mpesa->getConfirmationUrl()) ?>">
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> Save Settings
                    </button>
                </div>
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
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-phone"></i> Test STK Push</h5>
            </div>
            <div class="card-body">
                <?php if ($mpesa->isConfigured()): ?>
                <div class="mb-3">
                    <label class="form-label">Phone Number</label>
                    <input type="tel" class="form-control" id="testPhone" placeholder="254712345678">
                    <small class="text-muted">Format: 254XXXXXXXXX</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">Amount (KES)</label>
                    <input type="number" class="form-control" id="testAmount" value="1" min="1">
                </div>
                <button type="button" class="btn btn-success w-100" onclick="testStkPush()">
                    <i class="bi bi-send"></i> Send Test STK Push
                </button>
                <div id="stkResult" class="mt-3"></div>
                <?php else: ?>
                <div class="alert alert-warning mb-0">
                    <i class="bi bi-exclamation-triangle"></i> Configure M-Pesa first to test STK Push
                </div>
                <?php endif; ?>
            </div>
        </div>
        
    </div>
</div>

<script>
async function testStkPush() {
    const phone = document.getElementById('testPhone').value.trim();
    const amount = document.getElementById('testAmount').value;
    const resultDiv = document.getElementById('stkResult');
    
    if (!phone || !amount) {
        resultDiv.innerHTML = '<div class="alert alert-warning">Please enter phone and amount</div>';
        return;
    }
    
    if (!phone.match(/^254\d{9}$/)) {
        resultDiv.innerHTML = '<div class="alert alert-warning">Phone must be in format 254XXXXXXXXX</div>';
        return;
    }
    
    resultDiv.innerHTML = '<div class="alert alert-info"><i class="bi bi-hourglass-split"></i> Sending STK Push...</div>';
    
    try {
        const response = await fetch('/api/mpesa-test.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({phone, amount: parseFloat(amount)})
        });
        
        const data = await response.json();
        
        if (data.success) {
            resultDiv.innerHTML = `<div class="alert alert-success">
                <i class="bi bi-check-circle"></i> <strong>STK Push Sent!</strong><br>
                <small>Check your phone for the payment prompt.</small><br>
                <small class="text-muted">Checkout ID: ${data.checkoutRequestId || 'N/A'}</small>
            </div>`;
        } else {
            resultDiv.innerHTML = `<div class="alert alert-danger">
                <i class="bi bi-x-circle"></i> <strong>Failed</strong><br>
                <small>${data.error || 'Unknown error'}</small>
            </div>`;
        }
    } catch (e) {
        resultDiv.innerHTML = `<div class="alert alert-danger">Error: ${e.message}</div>`;
    }
}
</script>

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
                    
                    <hr class="my-4">
                    <h6 class="mb-3"><i class="bi bi-geo-alt"></i> IP-Based Clock-in Restriction</h6>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="mobile_restrict_clockin_ip" id="restrictClockInIp" value="1"
                                       <?= ($mobileSettings['mobile_restrict_clockin_ip'] ?? '0') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="restrictClockInIp">
                                    <strong>Restrict Clock-in by IP Address</strong>
                                </label>
                            </div>
                            <small class="text-muted">Only allow clock-in from specific network IP addresses</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Allowed IP Addresses</label>
                            <textarea name="mobile_allowed_ips" class="form-control" rows="3" 
                                      placeholder="Enter one IP per line, e.g.&#10;102.205.239.250&#10;102.205.239.251"><?= htmlspecialchars($mobileSettings['mobile_allowed_ips'] ?? '') ?></textarea>
                            <small class="text-muted">One IP address per line. Leave empty to allow all IPs when restriction is disabled.</small>
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

<?php elseif ($subpage === 'sla'): 
$sla = new \App\SLA();
$slaPolicies = $sla->getAllPolicies();
$businessHours = $sla->getBusinessHours();
$holidays = $sla->getHolidays();
$users = $db->query("SELECT id, name, email FROM users ORDER BY name")->fetchAll();
$dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
?>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-speedometer2"></i> SLA Policies</h5>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#slaPolicyModal" onclick="resetPolicyForm()">
                    <i class="bi bi-plus-lg"></i> Add Policy
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Priority</th>
                                <th>Response Time</th>
                                <th>Resolution Time</th>
                                <th>Escalation</th>
                                <th>Status</th>
                                <th width="100">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($slaPolicies)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    <i class="bi bi-speedometer2 fs-1"></i>
                                    <p class="mt-2">No SLA policies defined yet</p>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($slaPolicies as $policy): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($policy['name']) ?></strong>
                                    <?php if ($policy['is_default']): ?>
                                    <span class="badge bg-info">Default</span>
                                    <?php endif; ?>
                                    <?php if ($policy['description']): ?>
                                    <br><small class="text-muted"><?= htmlspecialchars($policy['description']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $priorityColors = ['critical' => 'danger', 'high' => 'warning', 'medium' => 'primary', 'low' => 'secondary'];
                                    $color = $priorityColors[$policy['priority']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?= $color ?>"><?= ucfirst($policy['priority']) ?></span>
                                </td>
                                <td>
                                    <i class="bi bi-reply text-success"></i> <?= $policy['response_time_hours'] ?> hours
                                </td>
                                <td>
                                    <i class="bi bi-check-circle text-primary"></i> <?= $policy['resolution_time_hours'] ?> hours
                                </td>
                                <td>
                                    <?php if ($policy['escalation_time_hours']): ?>
                                    <i class="bi bi-arrow-up-circle text-warning"></i> <?= $policy['escalation_time_hours'] ?>h
                                    <?php if ($policy['escalation_name']): ?>
                                    <br><small class="text-muted">to <?= htmlspecialchars($policy['escalation_name']) ?></small>
                                    <?php endif; ?>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($policy['is_active']): ?>
                                    <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick='editPolicy(<?= json_encode($policy) ?>)'>
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this SLA policy?')">
                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                        <input type="hidden" name="action" value="delete_sla_policy">
                                        <input type="hidden" name="policy_id" value="<?= $policy['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-clock"></i> Business Hours</h5>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">SLA timers only count during business hours. Configure when your support team is available.</p>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="save_business_hours">
                    
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>Day</th>
                                    <th>Working Day</th>
                                    <th>Start Time</th>
                                    <th>End Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($businessHours as $hours): ?>
                                <tr>
                                    <td class="fw-bold"><?= $dayNames[$hours['day_of_week']] ?></td>
                                    <td>
                                        <input type="hidden" name="hours[<?= $hours['day_of_week'] ?>][day_of_week]" value="<?= $hours['day_of_week'] ?>">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="hours[<?= $hours['day_of_week'] ?>][is_working_day]" value="1" 
                                                <?= $hours['is_working_day'] ? 'checked' : '' ?>>
                                        </div>
                                    </td>
                                    <td>
                                        <input type="time" class="form-control form-control-sm" name="hours[<?= $hours['day_of_week'] ?>][start_time]" 
                                            value="<?= $hours['start_time'] ?>" style="width: 120px;">
                                    </td>
                                    <td>
                                        <input type="time" class="form-control form-control-sm" name="hours[<?= $hours['day_of_week'] ?>][end_time]" 
                                            value="<?= $hours['end_time'] ?>" style="width: 120px;">
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> Save Business Hours
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-calendar-event"></i> Holidays</h5>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#holidayModal">
                    <i class="bi bi-plus-lg"></i>
                </button>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php if (empty($holidays)): ?>
                    <li class="list-group-item text-center text-muted py-4">
                        <i class="bi bi-calendar-x"></i> No holidays defined
                    </li>
                    <?php else: ?>
                    <?php foreach ($holidays as $holiday): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <strong><?= htmlspecialchars($holiday['name']) ?></strong><br>
                            <small class="text-muted">
                                <?= date('M d, Y', strtotime($holiday['holiday_date'])) ?>
                                <?= $holiday['is_recurring'] ? '<span class="badge bg-info">Recurring</span>' : '' ?>
                            </small>
                        </div>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Remove this holiday?')">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="action" value="delete_holiday">
                            <input type="hidden" name="holiday_id" value="<?= $holiday['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </li>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-info-circle"></i> How SLA Works</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <h6 class="text-primary"><i class="bi bi-reply"></i> Response Time</h6>
                    <p class="small text-muted mb-0">Time until first response/comment on a ticket from staff.</p>
                </div>
                <div class="mb-3">
                    <h6 class="text-success"><i class="bi bi-check-circle"></i> Resolution Time</h6>
                    <p class="small text-muted mb-0">Time until ticket is marked as resolved.</p>
                </div>
                <div class="mb-3">
                    <h6 class="text-warning"><i class="bi bi-arrow-up-circle"></i> Escalation</h6>
                    <p class="small text-muted mb-0">Auto-escalate and notify manager if SLA is about to breach.</p>
                </div>
                <hr>
                <p class="small text-muted mb-0">
                    <i class="bi bi-lightbulb"></i> SLA timers pause outside business hours and on holidays.
                </p>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="slaPolicyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="slaPolicyForm">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" id="policyAction" value="create_sla_policy">
                <input type="hidden" name="policy_id" id="editPolicyId" value="">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="policyModalTitle"><i class="bi bi-speedometer2"></i> Add SLA Policy</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Policy Name *</label>
                            <input type="text" class="form-control" name="name" id="policyName" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Priority *</label>
                            <select class="form-select" name="priority" id="policyPriority" required>
                                <option value="critical">Critical</option>
                                <option value="high">High</option>
                                <option value="medium" selected>Medium</option>
                                <option value="low">Low</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" id="policyDescription" rows="2"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Response Time (hours) *</label>
                            <input type="number" class="form-control" name="response_time_hours" id="policyResponseTime" min="1" value="4" required>
                            <small class="text-muted">Time to first staff response</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Resolution Time (hours) *</label>
                            <input type="number" class="form-control" name="resolution_time_hours" id="policyResolutionTime" min="1" value="24" required>
                            <small class="text-muted">Time to resolve ticket</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Escalation Time (hours)</label>
                            <input type="number" class="form-control" name="escalation_time_hours" id="policyEscalationTime" min="1">
                            <small class="text-muted">When to escalate (optional)</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Escalate To</label>
                            <select class="form-select" name="escalation_to" id="policyEscalationTo">
                                <option value="">-- No Escalation --</option>
                                <?php foreach ($users as $user): ?>
                                <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch mt-4">
                                <input class="form-check-input" type="checkbox" name="notify_on_breach" id="policyNotify" value="1" checked>
                                <label class="form-check-label" for="policyNotify">Notify on SLA Breach</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch mt-4">
                                <input class="form-check-input" type="checkbox" name="is_default" id="policyDefault" value="1">
                                <label class="form-check-label" for="policyDefault">Default for Priority</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" id="policyActive" value="1" checked>
                                <label class="form-check-label" for="policyActive">Active</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save Policy</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="holidayModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="add_holiday">
                
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-calendar-plus"></i> Add Holiday</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Holiday Name *</label>
                        <input type="text" class="form-control" name="name" required placeholder="e.g., Christmas Day">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Date *</label>
                        <input type="date" class="form-control" name="holiday_date" required>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_recurring" value="1" id="recurringHoliday">
                        <label class="form-check-label" for="recurringHoliday">
                            Recurring every year
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Add Holiday</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function resetPolicyForm() {
    document.getElementById('policyAction').value = 'create_sla_policy';
    document.getElementById('editPolicyId').value = '';
    document.getElementById('policyName').value = '';
    document.getElementById('policyDescription').value = '';
    document.getElementById('policyPriority').value = 'medium';
    document.getElementById('policyResponseTime').value = '4';
    document.getElementById('policyResolutionTime').value = '24';
    document.getElementById('policyEscalationTime').value = '';
    document.getElementById('policyEscalationTo').value = '';
    document.getElementById('policyNotify').checked = true;
    document.getElementById('policyDefault').checked = false;
    document.getElementById('policyActive').checked = true;
    document.getElementById('policyModalTitle').innerHTML = '<i class="bi bi-speedometer2"></i> Add SLA Policy';
}

function editPolicy(policy) {
    document.getElementById('policyAction').value = 'update_sla_policy';
    document.getElementById('editPolicyId').value = policy.id;
    document.getElementById('policyName').value = policy.name;
    document.getElementById('policyDescription').value = policy.description || '';
    document.getElementById('policyPriority').value = policy.priority;
    document.getElementById('policyResponseTime').value = policy.response_time_hours;
    document.getElementById('policyResolutionTime').value = policy.resolution_time_hours;
    document.getElementById('policyEscalationTime').value = policy.escalation_time_hours || '';
    document.getElementById('policyEscalationTo').value = policy.escalation_to || '';
    document.getElementById('policyNotify').checked = policy.notify_on_breach;
    document.getElementById('policyDefault').checked = policy.is_default;
    document.getElementById('policyActive').checked = policy.is_active;
    document.getElementById('policyModalTitle').innerHTML = '<i class="bi bi-pencil"></i> Edit SLA Policy';
    
    new bootstrap.Modal(document.getElementById('slaPolicyModal')).show();
}
</script>

<?php elseif ($subpage === 'hr_templates'): ?>

<?php
$rtProcessor = new \App\RealTimeAttendanceProcessor($dbConn);
$hrTemplates = $rtProcessor->getHRTemplates();
$eventTypes = $rtProcessor->getEventTypes();
$hrPlaceholders = $templateEngine->getHRPlaceholders();
$editHRTemplate = null;
if ($action === 'edit_hr_template' && $id) {
    $editHRTemplate = $rtProcessor->getHRTemplate($id);
}
?>

<?php if ($action === 'create_hr_template' || $action === 'edit_hr_template'): ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4><i class="bi bi-<?= $editHRTemplate ? 'pencil' : 'plus-circle' ?>"></i> <?= $editHRTemplate ? 'Edit' : 'Create' ?> HR Notification Template</h4>
    <a href="?page=settings&subpage=hr_templates" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back to Templates
    </a>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="<?= $editHRTemplate ? 'update_hr_template' : 'create_hr_template' ?>">
                    <?php if ($editHRTemplate): ?>
                    <input type="hidden" name="template_id" value="<?= $editHRTemplate['id'] ?>">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label class="form-label">Template Name *</label>
                        <input type="text" name="name" class="form-control" required
                               value="<?= htmlspecialchars($editHRTemplate['name'] ?? '') ?>">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Category</label>
                            <select name="category" class="form-select">
                                <option value="attendance" <?= ($editHRTemplate['category'] ?? '') === 'attendance' ? 'selected' : '' ?>>Attendance</option>
                                <option value="payroll" <?= ($editHRTemplate['category'] ?? '') === 'payroll' ? 'selected' : '' ?>>Payroll</option>
                                <option value="hr" <?= ($editHRTemplate['category'] ?? '') === 'hr' ? 'selected' : '' ?>>HR General</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Event Type *</label>
                            <select name="event_type" class="form-select" required>
                                <?php foreach ($eventTypes as $value => $label): ?>
                                <option value="<?= $value ?>" <?= ($editHRTemplate['event_type'] ?? '') === $value ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Subject (for emails/notifications)</label>
                        <input type="text" name="subject" class="form-control"
                               value="<?= htmlspecialchars($editHRTemplate['subject'] ?? '') ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">SMS Template *</label>
                        <textarea name="sms_template" class="form-control" rows="4" required
                                  placeholder="Dear {employee_name}, You checked in at {clock_in_time}..."><?= htmlspecialchars($editHRTemplate['sms_template'] ?? '') ?></textarea>
                        <small class="text-muted">Use placeholders from the list on the right</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email Template (optional)</label>
                        <textarea name="email_template" class="form-control" rows="6"
                                  placeholder="Optional email content..."><?= htmlspecialchars($editHRTemplate['email_template'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <div class="form-check">
                                <input type="checkbox" name="is_active" class="form-check-input" id="hrTemplateActive" value="1"
                                       <?= !isset($editHRTemplate['is_active']) || $editHRTemplate['is_active'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="hrTemplateActive">Active</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input type="checkbox" name="send_sms" class="form-check-input" id="hrTemplateSMS" value="1"
                                       <?= !isset($editHRTemplate['send_sms']) || $editHRTemplate['send_sms'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="hrTemplateSMS">Send SMS</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input type="checkbox" name="send_email" class="form-check-input" id="hrTemplateEmail" value="1"
                                       <?= ($editHRTemplate['send_email'] ?? false) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="hrTemplateEmail">Send Email</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> <?= $editHRTemplate ? 'Update Template' : 'Create Template' ?>
                        </button>
                        <a href="?page=settings&subpage=hr_templates" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-braces"></i> Available Placeholders
            </div>
            <div class="card-body" style="max-height: 500px; overflow-y: auto;">
                <small class="text-muted">Click to copy</small>
                <table class="table table-sm mt-2">
                    <?php foreach ($hrPlaceholders as $placeholder => $description): ?>
                    <tr>
                        <td>
                            <code class="user-select-all" style="cursor: pointer;" onclick="navigator.clipboard.writeText('<?= $placeholder ?>')"><?= $placeholder ?></code>
                        </td>
                        <td><small class="text-muted"><?= $description ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header">
                <i class="bi bi-eye"></i> Preview
            </div>
            <div class="card-body">
                <p class="text-muted small">Sample preview with test data:</p>
                <div id="hrTemplatePreview" class="p-3 bg-light rounded" style="white-space: pre-wrap; font-size: 0.9rem;">
                    Enter text in SMS Template to see preview
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelector('textarea[name="sms_template"]').addEventListener('input', function() {
    const template = this.value;
    const sampleData = {
        '{employee_name}': 'John Kamau',
        '{employee_id}': 'EMP-2024-0012',
        '{employee_phone}': '+254712345678',
        '{employee_email}': 'john.kamau@company.com',
        '{department_name}': 'Technical Support',
        '{position}': 'Senior Technician',
        '{clock_in_time}': '09:25 AM',
        '{clock_out_time}': '05:30 PM',
        '{work_start_time}': '09:00 AM',
        '{late_minutes}': '25',
        '{deduction_amount}': '150.00',
        '{currency}': 'KES',
        '{attendance_date}': new Date().toLocaleDateString('en-US', {month: 'short', day: 'numeric', year: 'numeric'}),
        '{hours_worked}': '8.0',
        '{company_name}': 'ISP Company',
        '{company_phone}': '+254700000000',
        '{current_date}': new Date().toISOString().split('T')[0],
        '{current_time}': new Date().toLocaleTimeString('en-US', {hour: '2-digit', minute: '2-digit'})
    };
    
    let preview = template;
    for (const [key, value] of Object.entries(sampleData)) {
        preview = preview.split(key).join(value);
    }
    
    document.getElementById('hrTemplatePreview').textContent = preview || 'Enter text in SMS Template to see preview';
});

document.querySelector('textarea[name="sms_template"]').dispatchEvent(new Event('input'));
</script>

<?php else: ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-bell"></i> HR Notification Templates</h4>
        <p class="text-muted mb-0">Configure automatic notifications for attendance events like late arrivals</p>
    </div>
    <a href="?page=settings&subpage=hr_templates&action=create_hr_template" class="btn btn-success">
        <i class="bi bi-plus-lg"></i> New Template
    </a>
</div>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body text-center">
                <h3 class="mb-0"><?= count($hrTemplates) ?></h3>
                <small>Total Templates</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body text-center">
                <h3 class="mb-0"><?= count(array_filter($hrTemplates, fn($t) => $t['is_active'])) ?></h3>
                <small>Active</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body text-center">
                <h3 class="mb-0"><?= count(array_filter($hrTemplates, fn($t) => $t['send_sms'])) ?></h3>
                <small>SMS Enabled</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-dark">
            <div class="card-body text-center">
                <?php
                $notifLogs = $rtProcessor->getNotificationLogs(null, date('Y-m-d'), date('Y-m-d'), 1000);
                $sentCount = count(array_filter($notifLogs, fn($l) => $l['status'] === 'sent'));
                ?>
                <h3 class="mb-0"><?= $sentCount ?></h3>
                <small>Sent Today</small>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <i class="bi bi-list"></i> Notification Templates
    </div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Event Type</th>
                    <th>SMS</th>
                    <th>Status</th>
                    <th width="120">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($hrTemplates as $tpl): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($tpl['name']) ?></strong>
                        <?php if (!empty($tpl['subject'])): ?>
                        <br><small class="text-muted"><?= htmlspecialchars($tpl['subject']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge bg-secondary"><?= ucfirst($tpl['category']) ?></span></td>
                    <td><?= $eventTypes[$tpl['event_type']] ?? $tpl['event_type'] ?></td>
                    <td>
                        <?php if ($tpl['send_sms']): ?>
                        <span class="badge bg-success"><i class="bi bi-check"></i> SMS</span>
                        <?php endif; ?>
                        <?php if ($tpl['send_email']): ?>
                        <span class="badge bg-info"><i class="bi bi-envelope"></i> Email</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($tpl['is_active']): ?>
                        <span class="badge bg-success">Active</span>
                        <?php else: ?>
                        <span class="badge bg-secondary">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="?page=settings&subpage=hr_templates&action=edit_hr_template&id=<?= $tpl['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this template?');">
                            <input type="hidden" name="action" value="delete_hr_template">
                            <input type="hidden" name="template_id" value="<?= $tpl['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($hrTemplates)): ?>
                <tr>
                    <td colspan="6" class="text-center text-muted py-4">
                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                        No notification templates yet. <a href="?page=settings&subpage=hr_templates&action=create_hr_template">Create your first template</a>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-clock-history"></i> Recent Notification Logs</span>
        <small class="text-muted">Last 20 notifications</small>
    </div>
    <div class="card-body p-0">
        <table class="table table-sm table-hover mb-0">
            <thead>
                <tr>
                    <th>Date/Time</th>
                    <th>Employee</th>
                    <th>Event</th>
                    <th>Late</th>
                    <th>Deduction</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $recentLogs = $rtProcessor->getNotificationLogs(null, null, null, 20);
                foreach ($recentLogs as $log): 
                ?>
                <tr>
                    <td>
                        <small><?= date('M j, Y', strtotime($log['attendance_date'])) ?></small><br>
                        <small class="text-muted"><?= $log['clock_in_time'] ? date('h:i A', strtotime($log['clock_in_time'])) : '-' ?></small>
                    </td>
                    <td>
                        <?= htmlspecialchars($log['employee_name'] ?? 'Unknown') ?>
                        <?php if ($log['employee_code']): ?>
                        <br><small class="text-muted"><?= $log['employee_code'] ?></small>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge bg-secondary"><?= $log['template_name'] ?? 'Unknown' ?></span></td>
                    <td><?= $log['late_minutes'] ?> min</td>
                    <td><?= number_format($log['deduction_amount'], 2) ?></td>
                    <td>
                        <?php if ($log['status'] === 'sent'): ?>
                        <span class="badge bg-success">Sent</span>
                        <?php elseif ($log['status'] === 'failed'): ?>
                        <span class="badge bg-danger">Failed</span>
                        <?php else: ?>
                        <span class="badge bg-warning">Pending</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($recentLogs)): ?>
                <tr>
                    <td colspan="6" class="text-center text-muted py-3">
                        No notification logs yet
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card mt-4 border-info">
    <div class="card-header bg-info text-white">
        <i class="bi bi-info-circle"></i> Biometric API Endpoint
    </div>
    <div class="card-body">
        <p>Configure your biometric devices to push attendance data to this endpoint for real-time processing:</p>
        <div class="bg-light p-3 rounded mb-3">
            <code><?= (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'yourdomain.com') ?>/biometric-api.php?action=push</code>
        </div>
        <p class="mb-2"><strong>Supported Actions:</strong></p>
        <ul class="mb-0">
            <li><code>push</code> or <code>attendance</code> - Process single attendance event</li>
            <li><code>bulk-push</code> - Process multiple attendance records</li>
            <li><code>clock-in</code> - Manual clock in by employee ID</li>
            <li><code>clock-out</code> - Manual clock out by employee ID</li>
            <li><code>zkteco-push</code> - ZKTeco device push protocol</li>
            <li><code>hikvision-push</code> - Hikvision device push protocol</li>
            <li><code>stats</code> - Get real-time attendance statistics</li>
            <li><code>late-arrivals</code> - Get today's late arrivals</li>
        </ul>
    </div>
</div>

<?php endif; ?>

<?php elseif ($subpage === 'devices'): ?>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-router"></i> Device Monitoring Configuration</h5>
            </div>
            <div class="card-body">
                <p class="text-muted mb-4">Configure SNMP and Telnet settings for monitoring your network devices (OLTs, switches, routers).</p>
                
                <div class="alert alert-info mb-3">
                    <i class="bi bi-info-circle me-2"></i>
                    Devices are managed in the <strong>Network Devices</strong> page. Go there to add, edit, or monitor your OLTs and switches.
                </div>
                
                <a href="?page=devices" class="btn btn-primary">
                    <i class="bi bi-speedometer2 me-1"></i> Open Network Devices
                </a>
            </div>
        </div>
        
        <div class="card mt-4 border-info">
            <div class="card-header bg-info text-white">
                <i class="bi bi-info-circle"></i> Supported Protocols
            </div>
            <div class="card-body">
                <ul class="mb-0">
                    <li><strong>SNMP v1/v2c:</strong> Community-based authentication</li>
                    <li><strong>SNMP v3:</strong> User-based security (USM) with authentication and encryption</li>
                    <li><strong>Telnet:</strong> Direct command-line access to devices</li>
                    <li><strong>SSH:</strong> Secure shell access (where supported)</li>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-list-check"></i> Device Monitoring Features</h5>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">The device monitoring system provides:</p>
                <ul class="mb-0">
                    <li><strong>Device Management:</strong> Add OLTs, switches, routers, and access points</li>
                    <li><strong>SNMP Polling:</strong> Monitor device status, interfaces, and traffic</li>
                    <li><strong>Interface Monitoring:</strong> View port status, traffic counters, and errors</li>
                    <li><strong>ONU Tracking:</strong> Discover and manage ONUs per OLT</li>
                    <li><strong>Telnet Console:</strong> Send commands directly to devices</li>
                    <li><strong>Connection Testing:</strong> Test ping, SNMP, and Telnet connectivity</li>
                    <li><strong>Activity Logging:</strong> Track all monitoring events and changes</li>
                </ul>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-hdd-network"></i> Supported Vendors</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-6">
                        <ul class="mb-0">
                            <li>Huawei</li>
                            <li>ZTE</li>
                            <li>Cisco</li>
                        </ul>
                    </div>
                    <div class="col-6">
                        <ul class="mb-0">
                            <li>Mikrotik</li>
                            <li>Ubiquiti</li>
                            <li>Nokia</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php elseif ($subpage === 'ticket_commissions'): ?>

<?php
$ticketCommission = new \App\TicketCommission($db);
$commissionRates = $ticketCommission->getCommissionRates();
$editRate = null;
if ($action === 'edit_rate' && $id) {
    $editRate = array_filter($commissionRates, fn($r) => $r['id'] == $id);
    $editRate = reset($editRate) ?: null;
}

$ticketModel = new \App\Ticket($db);
$categories = $ticketModel->getCategories();
$allCategories = $ticketModel->getAllCategories();
$editCategory = null;
if ($action === 'edit_category' && $id) {
    $editCategory = $ticketModel->getCategory((int)$id);
}

$categoryColors = [
    'primary' => 'Primary (Blue)',
    'secondary' => 'Secondary (Gray)',
    'success' => 'Success (Green)',
    'danger' => 'Danger (Red)',
    'warning' => 'Warning (Yellow)',
    'info' => 'Info (Cyan)',
    'dark' => 'Dark',
    'light' => 'Light'
];
?>

<!-- Ticket Categories Section -->
<div class="row g-4 mb-4">
    <div class="col-md-<?= ($action === 'add_category' || $editCategory) ? '7' : '12' ?>">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-tags text-primary"></i> Ticket Categories</h5>
                <div>
                    <?php if (empty($allCategories)): ?>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="action" value="seed_ticket_categories">
                        <button type="submit" class="btn btn-sm btn-outline-success me-2">
                            <i class="bi bi-magic"></i> Load Defaults
                        </button>
                    </form>
                    <?php endif; ?>
                    <a href="?page=settings&subpage=ticket_commissions&action=add_category" class="btn btn-sm btn-primary">
                        <i class="bi bi-plus-lg"></i> Add Category
                    </a>
                </div>
            </div>
            <div class="card-body">
                <p class="text-muted small">
                    Manage ticket categories that are used throughout the system. These categories define the types of tickets your team handles.
                </p>
                
                <?php if (empty($allCategories)): ?>
                <div class="text-center text-muted py-4">
                    <i class="bi bi-tags fs-1"></i>
                    <p class="mb-0">No categories configured</p>
                    <p class="small">Load default categories or add your own</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Order</th>
                                <th>Key</th>
                                <th>Label</th>
                                <th>Description</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allCategories as $cat): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-secondary"><?= $cat['display_order'] ?></span>
                                </td>
                                <td>
                                    <code><?= htmlspecialchars($cat['key']) ?></code>
                                </td>
                                <td>
                                    <span class="badge bg-<?= htmlspecialchars($cat['color'] ?? 'primary') ?>"><?= htmlspecialchars($cat['label']) ?></span>
                                </td>
                                <td class="text-muted small"><?= htmlspecialchars($cat['description'] ?? '-') ?></td>
                                <td>
                                    <?php if ($cat['is_active']): ?>
                                    <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="?page=settings&subpage=ticket_commissions&action=edit_category&id=<?= $cat['id'] ?>" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this category? This may affect existing tickets.')">
                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                        <input type="hidden" name="action" value="delete_ticket_category">
                                        <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
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
    
    <?php if ($action === 'add_category' || $editCategory): ?>
    <div class="col-md-5">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">
                    <i class="bi bi-<?= $editCategory ? 'pencil' : 'plus-lg' ?>"></i>
                    <?= $editCategory ? 'Edit' : 'Add' ?> Category
                </h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="<?= $editCategory ? 'update_ticket_category' : 'add_ticket_category' ?>">
                    <?php if ($editCategory): ?>
                    <input type="hidden" name="category_id" value="<?= $editCategory['id'] ?>">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label class="form-label">Key <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="key" required
                               value="<?= htmlspecialchars($editCategory['key'] ?? '') ?>"
                               placeholder="e.g., installation, maintenance"
                               pattern="[a-zA-Z0-9_]+"
                               <?= $editCategory ? 'readonly' : '' ?>>
                        <small class="text-muted">Lowercase letters, numbers, and underscores only. Cannot be changed after creation.</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Label <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="label" required
                               value="<?= htmlspecialchars($editCategory['label'] ?? '') ?>"
                               placeholder="e.g., New Installation">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="2"
                                  placeholder="Brief description of this category"><?= htmlspecialchars($editCategory['description'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Color</label>
                            <select class="form-select" name="color">
                                <?php foreach ($categoryColors as $colorKey => $colorLabel): ?>
                                <option value="<?= $colorKey ?>" <?= ($editCategory && ($editCategory['color'] ?? 'primary') === $colorKey) ? 'selected' : '' ?>>
                                    <?= $colorLabel ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Display Order</label>
                            <input type="number" class="form-control" name="display_order" min="0"
                                   value="<?= $editCategory['display_order'] ?? 0 ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" id="category_is_active"
                                   <?= (!$editCategory || $editCategory['is_active']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="category_is_active">Active</label>
                        </div>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> <?= $editCategory ? 'Update' : 'Add' ?> Category
                        </button>
                        <a href="?page=settings&subpage=ticket_commissions" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Commission Rates Section -->
<div class="row g-4">
    <div class="col-md-<?= ($action === 'add_rate' || $editRate) ? '7' : '12' ?>">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-ticket-perforated text-success"></i> Ticket Commission Rates</h5>
                <div>
                    <?php if (empty($commissionRates)): ?>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="action" value="seed_commission_rates">
                        <button type="submit" class="btn btn-sm btn-outline-success me-2">
                            <i class="bi bi-magic"></i> Load Defaults
                        </button>
                    </form>
                    <?php endif; ?>
                    <a href="?page=settings&subpage=ticket_commissions&action=add_rate" class="btn btn-sm btn-primary">
                        <i class="bi bi-plus-lg"></i> Add Rate
                    </a>
                </div>
            </div>
            <div class="card-body">
                <p class="text-muted small">
                    Configure commission rates for each ticket category. When a ticket is closed, the assigned employee earns the configured amount. For team assignments, the commission is split equally among team members.
                </p>
                
                <?php if (empty($commissionRates)): ?>
                <div class="text-center text-muted py-4">
                    <i class="bi bi-ticket-perforated fs-1"></i>
                    <p class="mb-0">No commission rates configured</p>
                    <p class="small">Add rates to enable ticket-based earnings for employees</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Category</th>
                                <th>Rate</th>
                                <th>SLA Required</th>
                                <th>Description</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($commissionRates as $rate): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-primary"><?= htmlspecialchars(ucfirst($rate['category'])) ?></span>
                                </td>
                                <td>
                                    <strong><?= $rate['currency'] ?> <?= number_format($rate['rate'], 2) ?></strong>
                                </td>
                                <td>
                                    <?php if (!empty($rate['require_sla_compliance'])): ?>
                                    <span class="badge bg-info"><i class="bi bi-speedometer2"></i> Yes</span>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted small"><?= htmlspecialchars($rate['description'] ?? '-') ?></td>
                                <td>
                                    <?php if ($rate['is_active']): ?>
                                    <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="?page=settings&subpage=ticket_commissions&action=edit_rate&id=<?= $rate['id'] ?>" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this rate?')">
                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                        <input type="hidden" name="action" value="delete_commission_rate">
                                        <input type="hidden" name="rate_id" value="<?= $rate['id'] ?>">
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
        
        <div class="card mt-4 border-info">
            <div class="card-header bg-info text-white">
                <i class="bi bi-info-circle"></i> How Ticket Commissions Work
            </div>
            <div class="card-body">
                <ul class="mb-0">
                    <li><strong>Individual Assignment:</strong> When a ticket is closed and assigned to a single technician, they receive the full commission amount.</li>
                    <li><strong>Team Assignment:</strong> When a ticket is assigned to a team, the commission is split equally among all active team members.</li>
                    <li><strong>Payroll Integration:</strong> Pending commissions are automatically added to payroll when processing monthly salaries.</li>
                    <li><strong>Mobile App:</strong> Employees can view their earnings and team earnings through the mobile app.</li>
                </ul>
            </div>
        </div>
    </div>
    
    <?php if ($action === 'add_rate' || $editRate): ?>
    <div class="col-md-5">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">
                    <i class="bi bi-<?= $editRate ? 'pencil' : 'plus-lg' ?>"></i>
                    <?= $editRate ? 'Edit' : 'Add' ?> Commission Rate
                </h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="<?= $editRate ? 'update_commission_rate' : 'add_commission_rate' ?>">
                    <?php if ($editRate): ?>
                    <input type="hidden" name="rate_id" value="<?= $editRate['id'] ?>">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label class="form-label">Category <span class="text-danger">*</span></label>
                        <select class="form-select" name="category" required <?= $editRate ? 'disabled' : '' ?>>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $key => $label): ?>
                            <option value="<?= $key ?>" <?= ($editRate && $editRate['category'] === $key) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($editRate): ?>
                        <input type="hidden" name="category" value="<?= $editRate['category'] ?>">
                        <?php endif; ?>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-8">
                            <label class="form-label">Rate Amount <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control" name="rate" 
                                   value="<?= $editRate['rate'] ?? '' ?>" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label">Currency</label>
                            <select class="form-select" name="currency">
                                <option value="KES" <?= ($editRate && $editRate['currency'] === 'KES') ? 'selected' : '' ?>>KES</option>
                                <option value="USD" <?= ($editRate && $editRate['currency'] === 'USD') ? 'selected' : '' ?>>USD</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="2"><?= htmlspecialchars($editRate['description'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" id="is_active"
                                   <?= (!$editRate || $editRate['is_active']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="is_active">Active</label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="require_sla_compliance" id="require_sla"
                                   <?= ($editRate && !empty($editRate['require_sla_compliance'])) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="require_sla">
                                Require SLA Compliance
                            </label>
                        </div>
                        <small class="text-muted">
                            If enabled, commission will only be paid if the ticket met SLA response and resolution targets.
                        </small>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> <?= $editRate ? 'Update' : 'Add' ?> Rate
                        </button>
                        <a href="?page=settings&subpage=ticket_commissions" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php elseif ($subpage === 'branches'): ?>
<?php
$branchClass = new \App\Branch();
$branches = $branchClass->getAll();
$allEmployees = (new \App\Employee($dbConn))->getEmployees();
$users = $dbConn->query("SELECT id, name FROM users WHERE role IN ('admin', 'manager') ORDER BY name")->fetchAll(\PDO::FETCH_ASSOC);

$editBranch = null;
$branchEmployees = [];
if ($action === 'edit_branch' && $id) {
    $editBranch = $branchClass->get($id);
    $branchEmployees = $branchClass->getEmployees($id);
}
?>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-diagram-3"></i> Branches</h5>
                <a href="?page=settings&subpage=branches&action=add_branch" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg"></i> Add Branch
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($branches)): ?>
                <div class="alert alert-info">No branches configured yet. Add your first branch.</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Code</th>
                                <th>Manager</th>
                                <th>Employees</th>
                                <th>Teams</th>
                                <th>WhatsApp Group</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($branches as $branch): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($branch['name']) ?></strong>
                                    <?php if ($branch['address']): ?>
                                    <br><small class="text-muted"><?= htmlspecialchars($branch['address']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($branch['code'] ?? '-') ?></span></td>
                                <td><?= htmlspecialchars($branch['manager_name'] ?? '-') ?></td>
                                <td><span class="badge bg-info"><?= $branch['employee_count'] ?? 0 ?></span></td>
                                <td><span class="badge bg-secondary"><?= $branch['team_count'] ?? 0 ?></span></td>
                                <td>
                                    <?php if ($branch['whatsapp_group']): ?>
                                    <span class="badge bg-success"><i class="bi bi-whatsapp"></i> Configured</span>
                                    <?php else: ?>
                                    <span class="badge bg-warning">Not Set</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($branch['is_active']): ?>
                                    <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                    <span class="badge bg-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="?page=settings&subpage=branches&action=edit_branch&id=<?= $branch['id'] ?>" 
                                       class="btn btn-sm btn-outline-primary" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="?page=settings&subpage=branches&action=manage_employees&id=<?= $branch['id'] ?>" 
                                       class="btn btn-sm btn-outline-info" title="Manage Employees">
                                        <i class="bi bi-people"></i>
                                    </a>
                                    <a href="?page=settings&subpage=branches&action=manage_teams&id=<?= $branch['id'] ?>" 
                                       class="btn btn-sm btn-outline-warning" title="Manage Teams">
                                        <i class="bi bi-diagram-3"></i>
                                    </a>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this branch?')">
                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                        <input type="hidden" name="action" value="delete_branch">
                                        <input type="hidden" name="branch_id" value="<?= $branch['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
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
    
    <div class="col-md-4">
        <?php if ($action === 'add_branch' || $action === 'edit_branch'): ?>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><?= $editBranch ? 'Edit Branch' : 'Add New Branch' ?></h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="<?= $editBranch ? 'update_branch' : 'create_branch' ?>">
                    <?php if ($editBranch): ?>
                    <input type="hidden" name="branch_id" value="<?= $editBranch['id'] ?>">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label class="form-label">Branch Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" required
                               value="<?= htmlspecialchars($editBranch['name'] ?? '') ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Branch Code</label>
                        <input type="text" class="form-control" name="code" placeholder="e.g., HQ, NBI, MSA"
                               value="<?= htmlspecialchars($editBranch['code'] ?? '') ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea class="form-control" name="address" rows="2"><?= htmlspecialchars($editBranch['address'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label">Phone</label>
                            <input type="text" class="form-control" name="phone"
                                   value="<?= htmlspecialchars($editBranch['phone'] ?? '') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email"
                                   value="<?= htmlspecialchars($editBranch['email'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Manager</label>
                        <select class="form-select" name="manager_id">
                            <option value="">Select Manager</option>
                            <?php foreach ($users as $user): ?>
                            <option value="<?= $user['id'] ?>" <?= ($editBranch && $editBranch['manager_id'] == $user['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($user['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">WhatsApp Group</label>
                        <div class="input-group">
                            <select class="form-select" name="whatsapp_group" id="branchWhatsAppGroup">
                                <option value="">Select a group...</option>
                                <?php if (!empty($editBranch['whatsapp_group'])): ?>
                                <option value="<?= htmlspecialchars($editBranch['whatsapp_group']) ?>" selected>
                                    <?= htmlspecialchars($editBranch['whatsapp_group']) ?>
                                </option>
                                <?php endif; ?>
                            </select>
                            <button type="button" class="btn btn-outline-secondary" id="refreshGroupsBtn" title="Refresh groups">
                                <i class="bi bi-arrow-clockwise"></i>
                            </button>
                        </div>
                        <small class="text-muted">Daily summaries will be sent to this group</small>
                        <div id="groupsLoadingMsg" class="text-muted small mt-1" style="display:none;">
                            <span class="spinner-border spinner-border-sm"></span> Loading groups...
                        </div>
                        <div id="groupsErrorMsg" class="text-danger small mt-1" style="display:none;"></div>
                    </div>
                    <script>
                    (function() {
                        const select = document.getElementById('branchWhatsAppGroup');
                        const refreshBtn = document.getElementById('refreshGroupsBtn');
                        const loadingMsg = document.getElementById('groupsLoadingMsg');
                        const errorMsg = document.getElementById('groupsErrorMsg');
                        const currentValue = '<?= htmlspecialchars($editBranch['whatsapp_group'] ?? '') ?>';
                        
                        function loadGroups() {
                            loadingMsg.style.display = 'block';
                            errorMsg.style.display = 'none';
                            
                            fetch('?page=api&action=whatsapp_session&op=groups')
                                .then(r => r.json())
                                .then(data => {
                                    loadingMsg.style.display = 'none';
                                    if (data.success && data.groups) {
                                        select.innerHTML = '<option value="">Select a group...</option>';
                                        data.groups.forEach(g => {
                                            const opt = document.createElement('option');
                                            opt.value = g.id;
                                            opt.textContent = g.name + ' (' + (g.participantsCount || '?') + ' members)';
                                            if (g.id === currentValue) opt.selected = true;
                                            select.appendChild(opt);
                                        });
                                        if (data.groups.length === 0) {
                                            errorMsg.textContent = 'No groups found. Make sure WhatsApp is connected.';
                                            errorMsg.style.display = 'block';
                                        }
                                    } else {
                                        errorMsg.textContent = data.error || 'Failed to load groups';
                                        errorMsg.style.display = 'block';
                                    }
                                })
                                .catch(err => {
                                    loadingMsg.style.display = 'none';
                                    errorMsg.textContent = 'Connection error: ' + err.message;
                                    errorMsg.style.display = 'block';
                                });
                        }
                        
                        refreshBtn.addEventListener('click', loadGroups);
                        loadGroups();
                    })();
                    </script>
                    
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" id="branch_is_active"
                                   <?= (!$editBranch || $editBranch['is_active']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="branch_is_active">Active</label>
                        </div>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> <?= $editBranch ? 'Update' : 'Create' ?> Branch
                        </button>
                        <a href="?page=settings&subpage=branches" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
        
        <?php elseif ($action === 'manage_employees' && $id): ?>
        <?php $branch = $branchClass->get($id); $branchEmployees = $branchClass->getEmployees($id); ?>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-people"></i> Employees - <?= htmlspecialchars($branch['name']) ?></h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="update_branch_employees">
                    <input type="hidden" name="branch_id" value="<?= $id ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Select Employees</label>
                        <?php 
                        $assignedIds = array_column($branchEmployees, 'id');
                        foreach ($allEmployees as $emp): 
                        ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="employee_ids[]" 
                                   value="<?= $emp['id'] ?>" id="emp_<?= $emp['id'] ?>"
                                   <?= in_array($emp['id'], $assignedIds) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="emp_<?= $emp['id'] ?>">
                                <?= htmlspecialchars($emp['name']) ?>
                                <small class="text-muted">(<?= htmlspecialchars($emp['department_name'] ?? 'No Dept') ?>)</small>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> Update Employees
                        </button>
                        <a href="?page=settings&subpage=branches" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
        
        <?php elseif ($action === 'manage_teams' && $id): ?>
        <?php 
        $branch = $branchClass->get($id); 
        $allTeams = $db->query("SELECT id, name, description FROM teams WHERE is_active = true ORDER BY name")->fetchAll(\PDO::FETCH_ASSOC);
        $branchTeamIds = $db->prepare("SELECT id FROM teams WHERE branch_id = ?");
        $branchTeamIds->execute([$id]);
        $assignedTeamIds = array_column($branchTeamIds->fetchAll(\PDO::FETCH_ASSOC), 'id');
        ?>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-diagram-3"></i> Teams - <?= htmlspecialchars($branch['name']) ?></h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="update_branch_teams">
                    <input type="hidden" name="branch_id" value="<?= $id ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Select Teams to Assign</label>
                        <?php if (empty($allTeams)): ?>
                        <div class="alert alert-warning">No teams found. Create teams first in Settings → Teams.</div>
                        <?php else: ?>
                        <?php foreach ($allTeams as $team): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="team_ids[]" 
                                   value="<?= $team['id'] ?>" id="team_<?= $team['id'] ?>"
                                   <?= in_array($team['id'], $assignedTeamIds) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="team_<?= $team['id'] ?>">
                                <?= htmlspecialchars($team['name']) ?>
                                <?php if ($team['description']): ?>
                                <small class="text-muted">- <?= htmlspecialchars($team['description']) ?></small>
                                <?php endif; ?>
                            </label>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> Update Teams
                        </button>
                        <a href="?page=settings&subpage=branches" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
        
        <?php else: ?>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-info-circle"></i> About Branches</h5>
            </div>
            <div class="card-body">
                <p>Branches allow you to organize your ISP operations across multiple locations.</p>
                <ul class="mb-0">
                    <li>Assign employees to branches</li>
                    <li>Link tickets to specific branches</li>
                    <li>Send daily summaries to branch WhatsApp groups</li>
                    <li>Track performance per branch</li>
                </ul>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php elseif ($subpage === 'billing_api'): ?>
<?php
require_once __DIR__ . '/../src/OneISP.php';
$oneIsp = new \App\OneISP();
$billingToken = $settings->get('oneisp_api_token', '');
$testResult = null;
if (($_GET['action'] ?? '') === 'test_billing') {
    $testResult = $oneIsp->testConnection();
}
?>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-cloud-arrow-down"></i> Customers API</h5>
            </div>
            <div class="card-body">
                <?php if ($testResult): ?>
                <div class="alert alert-<?= $testResult['success'] ? 'success' : 'danger' ?> alert-dismissible">
                    <strong><?= $testResult['success'] ? 'Connection Successful!' : 'Connection Failed' ?></strong>
                    <?php if (!$testResult['success']): ?>
                    <br><?= htmlspecialchars($testResult['error'] ?? 'Unknown error') ?>
                    <?php endif; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="save_billing_api">
                    
                    <div class="mb-3">
                        <label class="form-label">API Key</label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="oneisp_api_token" id="billingApiToken"
                                   value="<?= htmlspecialchars($billingToken) ?>" placeholder="Enter your API key">
                            <button class="btn btn-outline-secondary" type="button" onclick="toggleTokenVisibility()">
                                <i class="bi bi-eye" id="toggleIcon"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> Save
                        </button>
                        <a href="?page=settings&subpage=billing_api&action=test_billing" class="btn btn-outline-info">
                            <i class="bi bi-arrow-repeat"></i> Test Connection
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="bi bi-plug"></i> Status</h5>
            </div>
            <div class="card-body text-center">
                <?php if ($oneIsp->isConfigured()): ?>
                <div class="text-success mb-2">
                    <i class="bi bi-check-circle-fill display-4"></i>
                </div>
                <h5 class="text-success">Connected</h5>
                <?php else: ?>
                <div class="text-secondary mb-2">
                    <i class="bi bi-x-circle-fill display-4"></i>
                </div>
                <h5 class="text-secondary">Not Configured</h5>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function toggleTokenVisibility() {
    var input = document.getElementById('billingApiToken');
    var icon = document.getElementById('toggleIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'bi bi-eye';
    }
}
</script>

<?php elseif ($subpage === 'backup'): ?>

<?php
$backupDir = __DIR__ . '/../backups';
if (!is_dir($backupDir)) {
    @mkdir($backupDir, 0755, true);
}
$backups = [];
if (is_dir($backupDir)) {
    $files = glob($backupDir . '/*.sql');
    foreach ($files as $file) {
        $backups[] = [
            'filename' => basename($file),
            'size' => filesize($file),
            'created' => filemtime($file)
        ];
    }
    usort($backups, fn($a, $b) => $b['created'] - $a['created']);
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-database-down"></i> Database Backup</h4>
        <p class="text-muted mb-0">Create and manage database backups</p>
    </div>
</div>

<?php if (isset($_SESSION['backup_success'])): ?>
<div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle"></i> <?= htmlspecialchars($_SESSION['backup_success']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php unset($_SESSION['backup_success']); endif; ?>

<?php if (isset($_SESSION['backup_error'])): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($_SESSION['backup_error']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php unset($_SESSION['backup_error']); endif; ?>

<div class="row">
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-plus-circle"></i> Create Backup
            </div>
            <div class="card-body">
                <p class="text-muted">Create a new backup of your entire database including all tables and data.</p>
                <form method="POST" onsubmit="this.querySelector('button').disabled=true; this.querySelector('button').innerHTML='<span class=\'spinner-border spinner-border-sm\'></span> Creating...';">
                    <input type="hidden" name="action" value="create_backup">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-database-add"></i> Create Backup Now
                    </button>
                </form>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <i class="bi bi-upload"></i> Upload Backup
            </div>
            <div class="card-body">
                <p class="text-muted">Upload an existing SQL backup file to restore later.</p>
                <form method="POST" enctype="multipart/form-data" onsubmit="this.querySelector('button').disabled=true; this.querySelector('button').innerHTML='<span class=\'spinner-border spinner-border-sm\'></span> Uploading...';">
                    <input type="hidden" name="action" value="upload_backup">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <div class="mb-3">
                        <input type="file" name="backup_file" class="form-control" accept=".sql" required>
                        <small class="text-muted">Max size: 50MB. Only .sql files allowed.</small>
                    </div>
                    <button type="submit" class="btn btn-success w-100">
                        <i class="bi bi-upload"></i> Upload Backup
                    </button>
                </form>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header bg-info text-white">
                <i class="bi bi-info-circle"></i> Backup Info
            </div>
            <div class="card-body">
                <ul class="list-unstyled mb-0">
                    <li class="mb-2"><i class="bi bi-folder text-primary"></i> <strong>Location:</strong> <code>/backups/</code></li>
                    <li class="mb-2"><i class="bi bi-file-earmark-code text-success"></i> <strong>Format:</strong> SQL dump</li>
                    <li><i class="bi bi-clock text-warning"></i> <strong>Total Backups:</strong> <?= count($backups) ?></li>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-list"></i> Backup History</span>
                <small class="text-muted"><?= count($backups) ?> backup(s)</small>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Filename</th>
                            <th>Size</th>
                            <th>Created</th>
                            <th width="150">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($backups as $backup): ?>
                        <tr>
                            <td>
                                <i class="bi bi-file-earmark-code text-primary"></i>
                                <?= htmlspecialchars($backup['filename']) ?>
                            </td>
                            <td><?= number_format($backup['size'] / 1024, 2) ?> KB</td>
                            <td><?= date('M j, Y g:i A', $backup['created']) ?></td>
                            <td>
                                <a href="?page=settings&subpage=backup&action=download_backup&file=<?= urlencode($backup['filename']) ?>" 
                                   class="btn btn-sm btn-outline-success" title="Download">
                                    <i class="bi bi-download"></i>
                                </a>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this backup?');">
                                    <input type="hidden" name="action" value="delete_backup">
                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                    <input type="hidden" name="filename" value="<?= htmlspecialchars($backup['filename']) ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($backups)): ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">
                                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                No backups yet. Create your first backup above.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card mt-4 border-warning">
    <div class="card-header bg-warning text-dark">
        <i class="bi bi-exclamation-triangle"></i> Restore Instructions
    </div>
    <div class="card-body">
        <p>To restore a backup, download the SQL file and run it on your database server:</p>
        <div class="bg-light p-3 rounded">
            <code>docker exec -i isp_crm_db psql -U crm -d isp_crm &lt; backup_file.sql</code>
        </div>
        <p class="mt-3 mb-0 text-muted"><i class="bi bi-info-circle"></i> Always test backups on a staging environment before restoring to production.</p>
    </div>
</div>

<?php endif; ?>
