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

<?php endif; ?>
