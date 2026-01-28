<?php
$config = require __DIR__ . '/../config/database.php';

try {
    $dsn = "pgsql:host={$config['host']};port={$config['port']};dbname={$config['database']}";
    $db = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die('Database connection failed');
}

$licenseKey = $_GET['key'] ?? $_POST['license_key'] ?? '';
$license = null;
$message = '';
$messageType = 'info';

if ($licenseKey) {
    $stmt = $db->prepare("
        SELECT l.*, t.price_monthly, t.price_yearly, t.name as tier_name, t.max_users, t.max_customers, t.max_onus,
               c.name as customer_name, c.email, c.phone
        FROM licenses l
        JOIN license_tiers t ON l.tier_id = t.id
        LEFT JOIN license_customers c ON l.customer_id = c.id
        WHERE l.license_key = ?
    ");
    $stmt->execute([$licenseKey]);
    $license = $stmt->fetch();
}

$tiers = $db->query("SELECT * FROM license_tiers WHERE is_active = TRUE ORDER BY price_monthly")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>License Subscription - ISP CRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .hero { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 200px; }
        .pricing-card { transition: transform 0.3s; }
        .pricing-card:hover { transform: translateY(-5px); }
        .pricing-card.recommended { border: 2px solid #667eea; }
        .feature-list li { padding: 8px 0; border-bottom: 1px solid #eee; }
        .feature-list li:last-child { border-bottom: none; }
    </style>
</head>
<body class="bg-light">
    <div class="hero text-white py-5">
        <div class="container text-center">
            <h1><i class="bi bi-shield-lock me-2"></i>ISP CRM License</h1>
            <p class="lead mb-0">Manage your subscription and payments</p>
        </div>
    </div>
    
    <div class="container py-5">
        <?php if ($license): ?>
        <div class="row justify-content-center mb-5">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-key me-2"></i>Your License</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>License Key:</strong><br><code><?= htmlspecialchars($license['license_key']) ?></code></p>
                                <p><strong>Customer:</strong> <?= htmlspecialchars($license['customer_name']) ?></p>
                                <p><strong>Tier:</strong> <span class="badge bg-info"><?= htmlspecialchars($license['tier_name']) ?></span></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Status:</strong> 
                                    <?php if ($license['is_suspended']): ?>
                                    <span class="badge bg-danger">Suspended</span>
                                    <?php elseif ($license['expires_at'] && strtotime($license['expires_at']) < time()): ?>
                                    <span class="badge bg-warning">Expired</span>
                                    <?php elseif ($license['is_active']): ?>
                                    <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </p>
                                <p><strong>Expires:</strong> 
                                    <?= $license['expires_at'] ? date('F j, Y', strtotime($license['expires_at'])) : '<span class="text-success">Lifetime</span>' ?>
                                </p>
                                <p><strong>Limits:</strong> 
                                    <?= $license['max_users'] ?: '&infin;' ?> users, 
                                    <?= $license['max_customers'] ?: '&infin;' ?> customers,
                                    <?= $license['max_onus'] ?: '&infin;' ?> ONUs
                                </p>
                            </div>
                        </div>
                        
                        <?php if ($license['price_monthly'] > 0): ?>
                        <hr>
                        <h5>Renew / Extend License</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-body text-center">
                                        <h4>KES <?= number_format($license['price_monthly']) ?></h4>
                                        <p class="text-muted mb-3">Monthly</p>
                                        <button class="btn btn-primary w-100 pay-btn" data-cycle="monthly" data-amount="<?= $license['price_monthly'] ?>">
                                            <i class="bi bi-phone me-2"></i>Pay with M-Pesa
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card mb-3 border-success">
                                    <div class="card-body text-center">
                                        <h4>KES <?= number_format($license['price_yearly']) ?></h4>
                                        <p class="text-muted mb-3">Yearly <span class="badge bg-success">Save <?= round((1 - $license['price_yearly'] / ($license['price_monthly'] * 12)) * 100) ?>%</span></p>
                                        <button class="btn btn-success w-100 pay-btn" data-cycle="yearly" data-amount="<?= $license['price_yearly'] ?>">
                                            <i class="bi bi-phone me-2"></i>Pay with M-Pesa
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div id="paymentForm" class="d-none mt-3">
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>Enter your M-Pesa phone number to receive a payment prompt
                            </div>
                            <div class="input-group">
                                <span class="input-group-text">+254</span>
                                <input type="tel" id="phoneNumber" class="form-control" placeholder="7XXXXXXXX" maxlength="9" value="<?= substr($license['phone'] ?? '', -9) ?>">
                                <button class="btn btn-primary" id="confirmPayBtn" type="button">
                                    <span class="spinner-border spinner-border-sm d-none" id="paySpinner"></span>
                                    Send Payment Request
                                </button>
                            </div>
                            <input type="hidden" id="billingCycle" value="monthly">
                        </div>
                        
                        <div id="paymentResult" class="mt-3 d-none"></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        
        <div class="row justify-content-center mb-4">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-body">
                        <h5 class="card-title">Enter Your License Key</h5>
                        <form method="get">
                            <div class="input-group">
                                <input type="text" name="key" class="form-control" placeholder="XXXXXXXX-XXXXXXXX-XXXXXXXX-XXXXXXXX" required>
                                <button class="btn btn-primary" type="submit">View License</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <h3 class="text-center mb-4">Available Plans</h3>
        <div class="row justify-content-center">
            <?php foreach ($tiers as $i => $tier): ?>
            <div class="col-md-4 mb-4">
                <div class="card pricing-card h-100 shadow <?= $i === 1 ? 'recommended' : '' ?>">
                    <?php if ($i === 1): ?>
                    <div class="card-header bg-primary text-white text-center">
                        <strong>Most Popular</strong>
                    </div>
                    <?php endif; ?>
                    <div class="card-body text-center">
                        <h4><?= htmlspecialchars($tier['name']) ?></h4>
                        <h2 class="my-3">KES <?= number_format($tier['price_monthly']) ?><small class="text-muted">/mo</small></h2>
                        <p class="text-muted">or KES <?= number_format($tier['price_yearly']) ?>/year</p>
                        <hr>
                        <ul class="list-unstyled feature-list text-start">
                            <li><i class="bi bi-check-circle text-success me-2"></i><?= $tier['max_users'] ?: 'Unlimited' ?> Users</li>
                            <li><i class="bi bi-check-circle text-success me-2"></i><?= $tier['max_customers'] ?: 'Unlimited' ?> Customers</li>
                            <li><i class="bi bi-check-circle text-success me-2"></i><?= $tier['max_onus'] ?: 'Unlimited' ?> ONUs</li>
                            <?php 
                            $features = json_decode($tier['features'] ?: '{}', true);
                            foreach ($features as $f => $enabled):
                                if ($enabled):
                            ?>
                            <li><i class="bi bi-check-circle text-success me-2"></i><?= ucfirst(str_replace('_', ' ', $f)) ?></li>
                            <?php endif; endforeach; ?>
                        </ul>
                    </div>
                    <div class="card-footer bg-transparent text-center">
                        <a href="mailto:sales@example.com?subject=License Request - <?= urlencode($tier['name']) ?>" class="btn btn-outline-primary w-100">
                            Contact Sales
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <footer class="bg-dark text-light py-4 mt-5">
        <div class="container text-center">
            <p class="mb-0">&copy; <?= date('Y') ?> ISP CRM. All rights reserved.</p>
        </div>
    </footer>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php if ($license): ?>
    <script>
    const licenseKey = '<?= $license['license_key'] ?>';
    
    document.querySelectorAll('.pay-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('billingCycle').value = this.dataset.cycle;
            document.getElementById('paymentForm').classList.remove('d-none');
            document.getElementById('paymentResult').classList.add('d-none');
        });
    });
    
    document.getElementById('confirmPayBtn').addEventListener('click', async function() {
        const phone = '254' + document.getElementById('phoneNumber').value.replace(/^0/, '');
        const cycle = document.getElementById('billingCycle').value;
        const spinner = document.getElementById('paySpinner');
        const resultDiv = document.getElementById('paymentResult');
        
        if (phone.length !== 12) {
            resultDiv.className = 'mt-3 alert alert-danger';
            resultDiv.textContent = 'Please enter a valid phone number';
            resultDiv.classList.remove('d-none');
            return;
        }
        
        this.disabled = true;
        spinner.classList.remove('d-none');
        
        try {
            const response = await fetch('api/pay.php?action=initiate', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    license_key: licenseKey,
                    phone: phone,
                    billing_cycle: cycle
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                resultDiv.className = 'mt-3 alert alert-success';
                resultDiv.innerHTML = '<i class="bi bi-check-circle me-2"></i>' + data.message + '<br><small>Check your phone and enter your M-Pesa PIN to complete payment.</small>';
            } else {
                resultDiv.className = 'mt-3 alert alert-danger';
                resultDiv.textContent = data.error || 'Payment request failed';
            }
        } catch (e) {
            resultDiv.className = 'mt-3 alert alert-danger';
            resultDiv.textContent = 'Network error. Please try again.';
        }
        
        this.disabled = false;
        spinner.classList.add('d-none');
        resultDiv.classList.remove('d-none');
    });
    </script>
    <?php endif; ?>
</body>
</html>
