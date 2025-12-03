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
if (($_GET['action'] ?? '') === 'test') {
    $testResult = $smsGateway->testConnection();
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
                <a href="?page=settings&subpage=sms&action=test" class="btn btn-outline-primary">
                    <i class="bi bi-lightning"></i> Test Connection
                </a>
                <?php endif; ?>
                
                <?php if ($testResult): ?>
                <div class="alert alert-<?= $testResult['success'] ? 'success' : 'danger' ?> mt-3">
                    <?php if ($testResult['success']): ?>
                    <strong>Connection Successful!</strong><br>
                    Provider: <?= $testResult['gateway']['type'] ?>
                    <?php else: ?>
                    <strong>Connection Failed:</strong> <?= htmlspecialchars($testResult['error']) ?>
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
        <div class="alert alert-info">
            <h6 class="alert-heading"><i class="bi bi-info-circle"></i> How to Configure Advanta SMS</h6>
            <p>Go to the <strong>Secrets</strong> tab in Replit and add these 4 values from your Advanta account:</p>
        </div>
        
        <table class="table table-bordered">
            <thead class="table-light">
                <tr>
                    <th style="width: 200px;">Secret Name</th>
                    <th>Description</th>
                    <th>Where to Find</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code class="bg-light p-1">ADVANTA_API_KEY</code></td>
                    <td>Your Advanta API Key</td>
                    <td>Advanta Dashboard > API Settings</td>
                </tr>
                <tr>
                    <td><code class="bg-light p-1">ADVANTA_PARTNER_ID</code></td>
                    <td>Your Partner ID (numeric)</td>
                    <td>Advanta Dashboard > Account Info</td>
                </tr>
                <tr>
                    <td><code class="bg-light p-1">ADVANTA_SHORTCODE</code></td>
                    <td>Sender ID (appears as sender name)</td>
                    <td>Your registered Sender ID, e.g., "MyISP"</td>
                </tr>
                <tr>
                    <td><code class="bg-light p-1">ADVANTA_URL</code></td>
                    <td>API Endpoint (optional)</td>
                    <td>Default: <code>https://quicksms.advantasms.com/api/services/sendsms/</code></td>
                </tr>
            </tbody>
        </table>
        
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card bg-light">
                    <div class="card-body">
                        <h6><i class="bi bi-1-circle"></i> Step 1: Get Credentials</h6>
                        <p class="mb-0 small">Log in to <a href="https://quicksms.advantasms.com" target="_blank">quicksms.advantasms.com</a> and get your API Key, Partner ID, and Shortcode.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card bg-light">
                    <div class="card-body">
                        <h6><i class="bi bi-2-circle"></i> Step 2: Add Secrets</h6>
                        <p class="mb-0 small">In Replit, go to <strong>Tools > Secrets</strong> and add each value with the exact names shown above.</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="alert alert-success mt-4 mb-0">
            <strong><i class="bi bi-check-circle"></i> That's it!</strong> Once all secrets are added, the system will automatically use Advanta SMS for notifications.
        </div>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="bi bi-question-circle"></i> Alternative Providers</h5>
    </div>
    <div class="card-body">
        <p class="text-muted">If you don't use Advanta SMS, you can configure other providers:</p>
        
        <div class="accordion" id="altProviders">
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#twilioConfig">
                        Twilio SMS
                    </button>
                </h2>
                <div id="twilioConfig" class="accordion-collapse collapse" data-bs-parent="#altProviders">
                    <div class="accordion-body">
                        <ul class="mb-0">
                            <li><code>TWILIO_ACCOUNT_SID</code> - Your Twilio Account SID</li>
                            <li><code>TWILIO_AUTH_TOKEN</code> - Your Twilio Auth Token</li>
                            <li><code>TWILIO_PHONE_NUMBER</code> - Your Twilio phone number</li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#customConfig">
                        Custom SMS API
                    </button>
                </h2>
                <div id="customConfig" class="accordion-collapse collapse" data-bs-parent="#altProviders">
                    <div class="accordion-body">
                        <ul class="mb-0">
                            <li><code>SMS_API_URL</code> - Your SMS gateway API endpoint</li>
                            <li><code>SMS_API_KEY</code> - API key or authentication token</li>
                            <li><code>SMS_SENDER_ID</code> - Sender ID</li>
                            <li><code>SMS_API_METHOD</code> - POST or GET</li>
                            <li><code>SMS_CONTENT_TYPE</code> - json or form</li>
                            <li><code>SMS_PHONE_PARAM</code> - Parameter name for phone</li>
                            <li><code>SMS_MESSAGE_PARAM</code> - Parameter name for message</li>
                        </ul>
                    </div>
                </div>
            </div>
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
                    <textarea class="form-control" name="content" rows="8" required><?= htmlspecialchars($editTemplate['content'] ?? '') ?></textarea>
                    <small class="text-muted">
                        Available placeholders: <code>{customer_name}</code>, <code>{ticket_number}</code>, <code>{ticket_status}</code>, <code>{technician_name}</code>, <code>{company_name}</code>
                    </small>
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
                    <h6>Ticket Acknowledgment</h6>
                    <pre class="bg-light p-2 small mb-0">Dear {customer_name},

Thank you for contacting us. Your ticket #{ticket_number} has been received and assigned to our team.

We will get back to you within 24 hours.

Best regards,
{company_name} Support</pre>
                </div>
            </div>
            <div class="col-md-6">
                <div class="border rounded p-3">
                    <h6>Issue Resolved</h6>
                    <pre class="bg-light p-2 small mb-0">Dear {customer_name},

Great news! Your ticket #{ticket_number} has been resolved.

If you have any further questions, please don't hesitate to contact us.

Thank you for choosing {company_name}!</pre>
                </div>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<?php endif; ?>
