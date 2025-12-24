<?php
require_once __DIR__ . '/../src/LicenseMiddleware.php';

$client = LicenseMiddleware::getClient();
$licenseStatus = LicenseMiddleware::check();
$licenseInfo = $client->getLicenseInfo();
$features = $client->getFeatures();
$limits = $client->getLimits();

$message = '';
$messageType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'activate':
            $result = $client->activate();
            if ($result['valid']) {
                $message = 'License activated successfully!';
                $messageType = 'success';
                $licenseStatus = $result;
                $licenseInfo = $result['license'] ?? null;
            } else {
                $message = 'Activation failed: ' . ($result['message'] ?? 'Unknown error');
                $messageType = 'danger';
            }
            break;
            
        case 'deactivate':
            if ($client->deactivate()) {
                $message = 'License deactivated. You can now activate on another server.';
                $messageType = 'info';
                $licenseStatus = ['valid' => false];
                $licenseInfo = null;
            } else {
                $message = 'Deactivation failed.';
                $messageType = 'danger';
            }
            break;
    }
}

$isEnabled = $client->isEnabled();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">
        <i class="bi bi-shield-lock me-2"></i>License Management
    </h4>
</div>

<?php if ($message): ?>
<div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
    <?= htmlspecialchars($message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-md-6">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-info-circle me-2"></i>License Status
            </div>
            <div class="card-body">
                <?php if (!$isEnabled): ?>
                <div class="text-center py-4">
                    <i class="bi bi-unlock text-success" style="font-size: 3rem;"></i>
                    <h5 class="mt-3">Unlicensed Mode</h5>
                    <p class="text-muted">All features are enabled. No license server configured.</p>
                    <hr>
                    <p class="small text-muted mb-0">
                        To enable licensing, set the <code>LICENSE_SERVER_URL</code> and <code>LICENSE_KEY</code> environment variables.
                    </p>
                </div>
                
                <?php elseif ($licenseStatus['valid']): ?>
                <div class="text-center py-3">
                    <i class="bi bi-shield-check text-success" style="font-size: 3rem;"></i>
                    <h5 class="mt-3 text-success">License Active</h5>
                    <?php if (!empty($licenseStatus['grace_mode'])): ?>
                    <span class="badge bg-warning">Offline Mode</span>
                    <?php endif; ?>
                </div>
                
                <?php if ($licenseInfo): ?>
                <table class="table table-sm mt-3">
                    <tr>
                        <td class="text-muted">Customer</td>
                        <td class="fw-bold"><?= htmlspecialchars($licenseInfo['customer'] ?? 'N/A') ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Company</td>
                        <td><?= htmlspecialchars($licenseInfo['company'] ?? 'N/A') ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Plan</td>
                        <td><span class="badge bg-info"><?= htmlspecialchars($licenseInfo['tier'] ?? 'Unknown') ?></span></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Expires</td>
                        <td>
                            <?php if (!empty($licenseInfo['expires_at'])): ?>
                            <?= date('M j, Y', strtotime($licenseInfo['expires_at'])) ?>
                            <?php else: ?>
                            <span class="badge bg-success">Lifetime</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                
                <form method="post" class="mt-3">
                    <input type="hidden" name="action" value="deactivate">
                    <button type="submit" class="btn btn-outline-danger w-100" onclick="return confirm('Deactivate this license? You can reactivate on another server.')">
                        <i class="bi bi-x-circle me-2"></i>Deactivate License
                    </button>
                </form>
                <?php endif; ?>
                
                <?php else: ?>
                <div class="text-center py-3">
                    <i class="bi bi-shield-x text-danger" style="font-size: 3rem;"></i>
                    <h5 class="mt-3 text-danger">License Invalid</h5>
                    <p class="text-muted"><?= htmlspecialchars($licenseStatus['message'] ?? 'Unknown error') ?></p>
                </div>
                
                <form method="post">
                    <input type="hidden" name="action" value="activate">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-key me-2"></i>Activate License
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-info text-white">
                <i class="bi bi-box me-2"></i>Features & Limits
            </div>
            <div class="card-body">
                <h6 class="text-muted mb-3">Enabled Features</h6>
                <div class="row g-2 mb-4">
                    <?php 
                    $featureIcons = [
                        'crm' => 'people',
                        'tickets' => 'ticket-detailed',
                        'oms' => 'router',
                        'hr' => 'person-badge',
                        'inventory' => 'boxes',
                        'accounting' => 'calculator',
                        'whitelabel' => 'brush'
                    ];
                    foreach ($features as $feature => $enabled): 
                    ?>
                    <div class="col-6">
                        <div class="d-flex align-items-center p-2 rounded <?= $enabled ? 'bg-success bg-opacity-10' : 'bg-secondary bg-opacity-10' ?>">
                            <i class="bi bi-<?= $enabled ? 'check-circle-fill text-success' : 'x-circle text-secondary' ?> me-2"></i>
                            <span><?= ucfirst($feature) ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <h6 class="text-muted mb-3">Usage Limits</h6>
                <table class="table table-sm">
                    <tr>
                        <td><i class="bi bi-people me-2"></i>Max Users</td>
                        <td class="text-end fw-bold"><?= $limits['max_users'] ?: '<span class="badge bg-success">Unlimited</span>' ?></td>
                    </tr>
                    <tr>
                        <td><i class="bi bi-person-lines-fill me-2"></i>Max Customers</td>
                        <td class="text-end fw-bold"><?= $limits['max_customers'] ?: '<span class="badge bg-success">Unlimited</span>' ?></td>
                    </tr>
                    <tr>
                        <td><i class="bi bi-router me-2"></i>Max ONUs</td>
                        <td class="text-end fw-bold"><?= $limits['max_onus'] ?: '<span class="badge bg-success">Unlimited</span>' ?></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <?php if ($isEnabled): ?>
        <div class="card shadow-sm">
            <div class="card-header bg-secondary text-white">
                <i class="bi bi-gear me-2"></i>Configuration
            </div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr>
                        <td class="text-muted">License Server</td>
                        <td><code class="small"><?= htmlspecialchars(getenv('LICENSE_SERVER_URL') ?: 'Not set') ?></code></td>
                    </tr>
                    <tr>
                        <td class="text-muted">License Key</td>
                        <td>
                            <?php 
                            $key = getenv('LICENSE_KEY');
                            echo $key ? '<code class="small">' . substr($key, 0, 8) . '...' . substr($key, -4) . '</code>' : '<span class="text-muted">Not set</span>';
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted">Grace Period</td>
                        <td>7 days</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Check Interval</td>
                        <td>24 hours</td>
                    </tr>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
