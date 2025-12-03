<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-chat-dots"></i> SMS Gateway Settings</h2>
</div>

<?php
$gatewayInfo = $smsGateway->getGatewayInfo();
$testResult = null;
if ($_GET['action'] ?? '' === 'test') {
    $testResult = $smsGateway->testConnection();
}
?>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">Gateway Status</h5>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="stat-icon bg-<?= $gatewayInfo['status'] === 'Enabled' ? 'success' : 'danger' ?> bg-opacity-10 text-<?= $gatewayInfo['status'] === 'Enabled' ? 'success' : 'danger' ?> me-3" style="width: 50px; height: 50px; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-<?= $gatewayInfo['status'] === 'Enabled' ? 'check-circle' : 'x-circle' ?>"></i>
                    </div>
                    <div>
                        <h4 class="mb-0"><?= $gatewayInfo['status'] ?></h4>
                        <small class="text-muted">Gateway Type: <?= $gatewayInfo['type'] ?></small>
                    </div>
                </div>
                
                <?php if ($gatewayInfo['status'] === 'Enabled'): ?>
                <a href="?page=sms_settings&action=test" class="btn btn-outline-primary">
                    <i class="bi bi-lightning"></i> Test Connection
                </a>
                <?php endif; ?>
                
                <?php if ($testResult): ?>
                <div class="alert alert-<?= $testResult['success'] ? 'success' : 'danger' ?> mt-3">
                    <?php if ($testResult['success']): ?>
                    <strong>Connection Successful!</strong><br>
                    Gateway: <?= $testResult['gateway']['type'] ?>
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
                <h5 class="mb-0">Recent SMS Activity</h5>
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
                            $db = Database::getConnection();
                            $stmt = $db->query("SELECT * FROM sms_logs ORDER BY sent_at DESC LIMIT 10");
                            $smsLogs = $stmt->fetchAll();
                            foreach ($smsLogs as $log):
                            ?>
                            <tr>
                                <td><small><?= date('M j, g:i A', strtotime($log['sent_at'])) ?></small></td>
                                <td><small><?= htmlspecialchars(substr($log['recipient_phone'], -4)) ?></small></td>
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

<div class="card mt-4">
    <div class="card-header bg-white">
        <h5 class="mb-0">Configuration Guide</h5>
    </div>
    <div class="card-body">
        <h6>Custom SMS Gateway</h6>
        <p>Set these environment variables to use a custom SMS gateway:</p>
        <table class="table table-bordered">
            <thead class="table-light">
                <tr>
                    <th>Variable</th>
                    <th>Description</th>
                    <th>Example</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>SMS_API_URL</code></td>
                    <td>Your SMS gateway API endpoint</td>
                    <td>https://api.yoursms.com/send</td>
                </tr>
                <tr>
                    <td><code>SMS_API_KEY</code></td>
                    <td>API key or authentication token</td>
                    <td>your-api-key-here</td>
                </tr>
                <tr>
                    <td><code>SMS_SENDER_ID</code></td>
                    <td>Sender ID or phone number</td>
                    <td>ISP-CRM or +1234567890</td>
                </tr>
                <tr>
                    <td><code>SMS_API_METHOD</code></td>
                    <td>HTTP method</td>
                    <td><strong>POST</strong> or <strong>GET</strong></td>
                </tr>
                <tr>
                    <td><code>SMS_CONTENT_TYPE</code></td>
                    <td>Request content type (for POST)</td>
                    <td><strong>json</strong> (default) or <strong>form</strong></td>
                </tr>
                <tr>
                    <td><code>SMS_AUTH_HEADER</code></td>
                    <td>Authorization header type</td>
                    <td><strong>Bearer</strong> (default), <strong>Basic</strong>, <strong>X-API-Key</strong>, or custom</td>
                </tr>
                <tr>
                    <td><code>SMS_PHONE_PARAM</code></td>
                    <td>Parameter name for phone number</td>
                    <td>phone, to, recipient, mobile</td>
                </tr>
                <tr>
                    <td><code>SMS_MESSAGE_PARAM</code></td>
                    <td>Parameter name for message</td>
                    <td>message, text, body, content</td>
                </tr>
                <tr>
                    <td><code>SMS_SENDER_PARAM</code></td>
                    <td>Parameter name for sender ID</td>
                    <td>sender, from, sender_id, source</td>
                </tr>
            </tbody>
        </table>
        
        <div class="alert alert-info mt-3">
            <h6 class="alert-heading"><i class="bi bi-info-circle"></i> API Flexibility</h6>
            <p class="mb-2">The SMS gateway supports any REST API that accepts phone number and message parameters:</p>
            <ul class="mb-0">
                <li><strong>POST with JSON:</strong> Set <code>SMS_API_METHOD=POST</code> and <code>SMS_CONTENT_TYPE=json</code></li>
                <li><strong>POST with Form Data:</strong> Set <code>SMS_API_METHOD=POST</code> and <code>SMS_CONTENT_TYPE=form</code></li>
                <li><strong>GET with Query Params:</strong> Set <code>SMS_API_METHOD=GET</code> (parameters added to URL)</li>
            </ul>
        </div>
        
        <hr>
        
        <h6>Twilio (Alternative)</h6>
        <p>If custom gateway is not configured, the system will fall back to Twilio if these are set:</p>
        <ul>
            <li><code>TWILIO_ACCOUNT_SID</code> - Your Twilio Account SID</li>
            <li><code>TWILIO_AUTH_TOKEN</code> - Your Twilio Auth Token</li>
            <li><code>TWILIO_PHONE_NUMBER</code> - Your Twilio phone number</li>
        </ul>
    </div>
</div>
