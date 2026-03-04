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
                    <i class="bi bi-shield-lock text-danger" style="font-size: 3rem;"></i>
                    <h5 class="mt-3">License Not Configured</h5>
                    <p class="text-muted">A valid license is required. Enter your License Server URL and License Key below, or set them as environment variables.</p>
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
                            <?php
                                $expiresTs = strtotime($licenseInfo['expires_at']);
                                $daysLeft = max(0, (int)(($expiresTs - time()) / 86400));
                                $expiryClass = $daysLeft <= 7 ? 'text-danger fw-bold' : ($daysLeft <= 30 ? 'text-warning' : '');
                            ?>
                            <span class="<?= $expiryClass ?>"><?= date('M j, Y', $expiresTs) ?></span>
                            <?php if ($daysLeft <= 30): ?>
                            <span class="badge bg-<?= $daysLeft <= 7 ? 'danger' : 'warning' ?> ms-1"><?= $daysLeft ?> days left</span>
                            <?php endif; ?>
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
                        <td><i class="bi bi-wifi me-2"></i>Max Subscribers</td>
                        <td class="text-end fw-bold"><?= $limits['max_subscribers'] ?: '<span class="badge bg-success">Unlimited</span>' ?></td>
                    </tr>
                    <tr>
                        <td><i class="bi bi-router me-2"></i>Max ONUs</td>
                        <td class="text-end fw-bold"><?= $limits['max_onus'] ?: '<span class="badge bg-success">Unlimited</span>' ?></td>
                    </tr>
                    <tr>
                        <td><i class="bi bi-hdd-rack me-2"></i>Max OLTs</td>
                        <td class="text-end fw-bold"><?= $limits['max_olts'] ?: '<span class="badge bg-success">Unlimited</span>' ?></td>
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

<?php if ($isEnabled): ?>
<div class="row mt-4">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                <span><i class="bi bi-phone me-2"></i>Pay with M-Pesa</span>
                <button class="btn btn-sm btn-outline-light" onclick="loadSubscriptionInfo()">
                    <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                </button>
            </div>
            <div class="card-body">
                <div id="subscriptionLoading" class="text-center py-4">
                    <div class="spinner-border text-success" role="status"></div>
                    <p class="text-muted mt-2">Loading subscription info...</p>
                </div>

                <div id="subscriptionContent" style="display:none;">
                    <div class="row">
                        <div class="col-md-5">
                            <div class="bg-light rounded p-3 mb-3">
                                <h6 class="mb-3"><i class="bi bi-credit-card me-2"></i>Current Subscription</h6>
                                <table class="table table-sm table-borderless mb-0">
                                    <tr>
                                        <td class="text-muted">Plan</td>
                                        <td class="fw-bold" id="subTierName">-</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Expires</td>
                                        <td id="subExpires">-</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Monthly</td>
                                        <td id="subPriceMonthly">-</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Yearly</td>
                                        <td id="subPriceYearly">-</td>
                                    </tr>
                                </table>
                            </div>

                            <div class="card border-success mb-3">
                                <div class="card-body">
                                    <h6 class="card-title"><i class="bi bi-phone-fill text-success me-2"></i>Pay via M-Pesa STK Push</h6>
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Billing Cycle</label>
                                        <div class="d-flex gap-2">
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="billingCycle" id="cycleMonthly" value="monthly" checked onchange="updatePayAmount()">
                                                <label class="form-check-label" for="cycleMonthly">Monthly</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="billingCycle" id="cycleYearly" value="yearly" onchange="updatePayAmount()">
                                                <label class="form-check-label" for="cycleYearly">Yearly</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">M-Pesa Phone Number</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                                            <input type="tel" class="form-control" id="mpesaPhone" placeholder="0712345678" maxlength="13">
                                        </div>
                                        <small class="text-muted">Safaricom number to receive the STK prompt</small>
                                    </div>
                                    <div class="d-grid">
                                        <button class="btn btn-success btn-lg" id="payBtn" onclick="initiateMpesaPayment()">
                                            <i class="bi bi-phone me-2"></i>Pay <span id="payAmountLabel">KES 0</span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div id="paymentProgress" style="display:none;">
                                <div class="alert alert-info">
                                    <div class="d-flex align-items-center">
                                        <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                                        <div>
                                            <strong>Waiting for M-Pesa confirmation...</strong>
                                            <p class="mb-0 small" id="paymentStatusText">Check your phone and enter your M-Pesa PIN</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div id="paymentSuccess" style="display:none;">
                                <div class="alert alert-success">
                                    <i class="bi bi-check-circle-fill me-2"></i>
                                    <strong>Payment Successful!</strong>
                                    <p class="mb-0 small">Receipt: <span id="paymentReceipt"></span></p>
                                    <p class="mb-0 small mt-1">Your license has been renewed. The page will refresh shortly.</p>
                                </div>
                            </div>

                            <div id="paymentFailed" style="display:none;">
                                <div class="alert alert-danger">
                                    <i class="bi bi-x-circle-fill me-2"></i>
                                    <strong>Payment Failed</strong>
                                    <p class="mb-0 small" id="paymentErrorText"></p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-7">
                            <h6 class="mb-3"><i class="bi bi-clock-history me-2"></i>Payment History</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Date</th>
                                            <th>Amount</th>
                                            <th>Phone</th>
                                            <th>Receipt</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="paymentHistoryBody">
                                        <tr><td colspan="5" class="text-center text-muted">No payments yet</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="subscriptionError" style="display:none;">
                    <div class="alert alert-warning mb-0">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <span id="subscriptionErrorText">Could not load subscription information.</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let subData = null;

function loadSubscriptionInfo() {
    document.getElementById('subscriptionLoading').style.display = '';
    document.getElementById('subscriptionContent').style.display = 'none';
    document.getElementById('subscriptionError').style.display = 'none';

    fetch('?page=license_subscription_info')
        .then(r => r.json())
        .then(data => {
            document.getElementById('subscriptionLoading').style.display = 'none';
            if (data.success && data.license) {
                subData = data;
                renderSubscription(data);
                document.getElementById('subscriptionContent').style.display = '';
            } else {
                document.getElementById('subscriptionErrorText').textContent = data.error || 'Could not load subscription info';
                document.getElementById('subscriptionError').style.display = '';
            }
        })
        .catch(err => {
            document.getElementById('subscriptionLoading').style.display = 'none';
            document.getElementById('subscriptionErrorText').textContent = 'Connection error: ' + err.message;
            document.getElementById('subscriptionError').style.display = '';
        });
}

function renderSubscription(data) {
    const lic = data.license;
    document.getElementById('subTierName').textContent = lic.tier_name || 'N/A';

    if (lic.expires_at) {
        const exp = new Date(lic.expires_at);
        const now = new Date();
        const daysLeft = Math.max(0, Math.ceil((exp - now) / 86400000));
        let badge = '';
        if (daysLeft <= 7) badge = '<span class="badge bg-danger ms-1">' + daysLeft + ' days left</span>';
        else if (daysLeft <= 30) badge = '<span class="badge bg-warning ms-1">' + daysLeft + ' days left</span>';
        document.getElementById('subExpires').innerHTML = exp.toLocaleDateString('en-US', {year:'numeric',month:'short',day:'numeric'}) + ' ' + badge;
    } else {
        document.getElementById('subExpires').innerHTML = '<span class="badge bg-success">Lifetime</span>';
    }

    document.getElementById('subPriceMonthly').textContent = 'KES ' + Number(lic.price_monthly).toLocaleString();
    document.getElementById('subPriceYearly').textContent = 'KES ' + Number(lic.price_yearly).toLocaleString();

    if (lic.customer_phone) {
        document.getElementById('mpesaPhone').value = lic.customer_phone;
    }

    updatePayAmount();

    const tbody = document.getElementById('paymentHistoryBody');
    if (data.payments && data.payments.length > 0) {
        tbody.innerHTML = data.payments.map(p => {
            const date = p.paid_at ? new Date(p.paid_at).toLocaleDateString() : new Date(p.created_at).toLocaleDateString();
            const statusBadge = p.status === 'completed' 
                ? '<span class="badge bg-success">Paid</span>'
                : p.status === 'failed' 
                    ? '<span class="badge bg-danger">Failed</span>'
                    : '<span class="badge bg-warning">Pending</span>';
            return '<tr>' +
                '<td>' + date + '</td>' +
                '<td>KES ' + Number(p.amount).toLocaleString() + '</td>' +
                '<td>' + (p.phone_number || '-') + '</td>' +
                '<td><code class="small">' + (p.mpesa_receipt || '-') + '</code></td>' +
                '<td>' + statusBadge + '</td>' +
                '</tr>';
        }).join('');
    }
}

function updatePayAmount() {
    if (!subData) return;
    const cycle = document.querySelector('input[name="billingCycle"]:checked').value;
    const amount = cycle === 'yearly' ? subData.license.price_yearly : subData.license.price_monthly;
    document.getElementById('payAmountLabel').textContent = 'KES ' + Number(amount).toLocaleString();
}

function initiateMpesaPayment() {
    const phone = document.getElementById('mpesaPhone').value.trim();
    if (!phone || phone.length < 10) {
        alert('Please enter a valid phone number');
        return;
    }

    const cycle = document.querySelector('input[name="billingCycle"]:checked').value;
    const btn = document.getElementById('payBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Sending STK Push...';

    document.getElementById('paymentProgress').style.display = 'none';
    document.getElementById('paymentSuccess').style.display = 'none';
    document.getElementById('paymentFailed').style.display = 'none';

    fetch('?page=license_pay_initiate', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ phone: phone, billing_cycle: cycle })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.checkout_request_id) {
            document.getElementById('paymentProgress').style.display = '';
            document.getElementById('paymentStatusText').textContent = data.message || 'Check your phone and enter your M-Pesa PIN';
            pollPaymentStatus(data.checkout_request_id, 0);
        } else {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-phone me-2"></i>Pay <span id="payAmountLabel">KES ' + (subData ? (cycle === 'yearly' ? subData.license.price_yearly : subData.license.price_monthly) : '0') + '</span>';
            document.getElementById('paymentFailed').style.display = '';
            document.getElementById('paymentErrorText').textContent = data.error || 'Failed to initiate payment';
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-phone me-2"></i>Pay';
        document.getElementById('paymentFailed').style.display = '';
        document.getElementById('paymentErrorText').textContent = 'Connection error: ' + err.message;
    });
}

function pollPaymentStatus(checkoutRequestId, attempt) {
    if (attempt > 40) {
        document.getElementById('paymentProgress').style.display = 'none';
        document.getElementById('paymentFailed').style.display = '';
        document.getElementById('paymentErrorText').textContent = 'Payment confirmation timed out. If you completed the payment, it will be reflected shortly.';
        resetPayButton();
        return;
    }

    setTimeout(() => {
        fetch('?page=license_pay_status', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ checkout_request_id: checkoutRequestId })
        })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'completed') {
                document.getElementById('paymentProgress').style.display = 'none';
                document.getElementById('paymentSuccess').style.display = '';
                document.getElementById('paymentReceipt').textContent = data.mpesa_receipt || 'N/A';
                resetPayButton();
                setTimeout(() => location.reload(), 3000);
            } else if (data.status === 'failed') {
                document.getElementById('paymentProgress').style.display = 'none';
                document.getElementById('paymentFailed').style.display = '';
                document.getElementById('paymentErrorText').textContent = 'Payment was cancelled or failed. Please try again.';
                resetPayButton();
            } else {
                pollPaymentStatus(checkoutRequestId, attempt + 1);
            }
        })
        .catch(() => {
            pollPaymentStatus(checkoutRequestId, attempt + 1);
        });
    }, 3000);
}

function resetPayButton() {
    const btn = document.getElementById('payBtn');
    btn.disabled = false;
    const cycle = document.querySelector('input[name="billingCycle"]:checked')?.value || 'monthly';
    const amount = subData ? (cycle === 'yearly' ? subData.license.price_yearly : subData.license.price_monthly) : 0;
    btn.innerHTML = '<i class="bi bi-phone me-2"></i>Pay KES ' + Number(amount).toLocaleString();
}

document.addEventListener('DOMContentLoaded', loadSubscriptionInfo);
</script>
<?php endif; ?>
