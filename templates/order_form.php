<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order - <?= htmlspecialchars($company['company_name'] ?? 'ISP Provider') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: <?= htmlspecialchars($landingSettings['primary_color'] ?? '#2563eb') ?>;
        }
        
        * {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        
        .order-container {
            max-width: 600px;
            width: 100%;
        }
        
        .order-card {
            background: #fff;
            border-radius: 1.5rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            overflow: hidden;
        }
        
        .order-header {
            background: linear-gradient(135deg, var(--primary-color), #7c3aed);
            color: #fff;
            padding: 2rem;
            text-align: center;
        }
        
        .order-header h1 {
            font-weight: 700;
            font-size: 1.75rem;
            margin-bottom: 0.5rem;
        }
        
        .package-summary {
            background: rgba(255,255,255,0.15);
            border-radius: 1rem;
            padding: 1rem;
            margin-top: 1rem;
        }
        
        .package-summary .speed {
            font-size: 2rem;
            font-weight: 800;
        }
        
        .package-summary .price {
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        .order-body {
            padding: 2rem;
        }
        
        .form-label {
            font-weight: 500;
            color: #374151;
        }
        
        .form-control {
            border-radius: 0.75rem;
            padding: 0.75rem 1rem;
            border: 2px solid #e5e7eb;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), #7c3aed);
            border: none;
            padding: 1rem 2rem;
            font-weight: 600;
            border-radius: 0.75rem;
            width: 100%;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 40px rgba(37, 99, 235, 0.3);
        }
        
        .payment-options {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .payment-option {
            flex: 1;
            border: 2px solid #e5e7eb;
            border-radius: 0.75rem;
            padding: 1rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .payment-option:hover {
            border-color: var(--primary-color);
        }
        
        .payment-option.active {
            border-color: var(--primary-color);
            background: rgba(37, 99, 235, 0.05);
        }
        
        .payment-option input {
            display: none;
        }
        
        .payment-option i {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .mpesa-icon {
            color: #4ade80;
        }
        
        .back-link {
            text-align: center;
            margin-top: 1.5rem;
        }
        
        .back-link a {
            color: #6b7280;
            text-decoration: none;
        }
        
        .back-link a:hover {
            color: var(--primary-color);
        }
        
        .success-message {
            text-align: center;
            padding: 3rem 2rem;
        }
        
        .success-message i {
            font-size: 4rem;
            color: #10b981;
            margin-bottom: 1rem;
        }
        
        .success-message h2 {
            color: #1f2937;
            margin-bottom: 0.5rem;
        }
        
        .order-number {
            background: #f3f4f6;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            display: inline-block;
            font-family: monospace;
            font-size: 1.25rem;
            margin: 1rem 0;
        }
    </style>
</head>
<body>
    <div class="order-container">
        <?php if ($orderSuccess ?? false): ?>
        <div class="order-card">
            <div class="success-message">
                <i class="bi bi-check-circle-fill"></i>
                <h2>Order Submitted!</h2>
                <p class="text-muted">Thank you for your order. Our team will contact you shortly.</p>
                <div class="order-number"><?= htmlspecialchars($orderNumber ?? '') ?></div>
                <?php if ($paymentInitiated ?? false): ?>
                <div class="alert alert-info mt-3">
                    <i class="bi bi-phone"></i> Check your phone for M-Pesa payment prompt
                </div>
                <?php endif; ?>
                <div class="mt-4">
                    <a href="/" class="btn btn-primary">
                        <i class="bi bi-house"></i> Back to Home
                    </a>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="order-card">
            <div class="order-header">
                <a href="/" class="text-white text-decoration-none">
                    <i class="bi bi-router"></i> <?= htmlspecialchars($company['company_name'] ?? 'ISP Provider') ?>
                </a>
                <h1 class="mt-3">Get Connected</h1>
                <p class="mb-0 opacity-75">Complete your order below</p>
                
                <?php if ($package): ?>
                <div class="package-summary">
                    <div class="speed"><?= htmlspecialchars($package['speed']) ?> <?= htmlspecialchars($package['speed_unit'] ?? 'Mbps') ?></div>
                    <div><?= htmlspecialchars($package['name']) ?></div>
                    <div class="price mt-2">
                        <?= htmlspecialchars($package['currency'] ?? 'KES') ?> <?= number_format($package['price']) ?>
                        <small>/<?= htmlspecialchars($package['billing_cycle'] ?? 'month') ?></small>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="order-body">
                <?php if ($error ?? false): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="?page=order&action=submit">
                    <input type="hidden" name="package_id" value="<?= htmlspecialchars($package['id'] ?? '') ?>">
                    <input type="hidden" name="amount" value="<?= htmlspecialchars($package['price'] ?? '') ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Full Name *</label>
                        <input type="text" class="form-control" name="customer_name" required
                               placeholder="Enter your full name" value="<?= htmlspecialchars($_POST['customer_name'] ?? '') ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Phone Number *</label>
                        <input type="tel" class="form-control" name="customer_phone" required
                               placeholder="e.g., 0712345678" value="<?= htmlspecialchars($_POST['customer_phone'] ?? '') ?>">
                        <div class="form-text">We'll use this to contact you and for M-Pesa payments</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <input type="email" class="form-control" name="customer_email"
                               placeholder="your@email.com" value="<?= htmlspecialchars($_POST['customer_email'] ?? '') ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Installation Address *</label>
                        <textarea class="form-control" name="customer_address" rows="2" required
                                  placeholder="Enter your full address for installation"><?= htmlspecialchars($_POST['customer_address'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Additional Notes</label>
                        <textarea class="form-control" name="notes" rows="2"
                                  placeholder="Any special instructions or preferred contact time"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
                    </div>
                    
                    <hr class="my-4">
                    
                    <label class="form-label mb-3">Payment Method</label>
                    <div class="payment-options">
                        <label class="payment-option" id="opt-mpesa">
                            <input type="radio" name="payment_method" value="mpesa">
                            <div>
                                <i class="bi bi-phone mpesa-icon"></i>
                                <div class="fw-semibold">M-Pesa</div>
                                <small class="text-muted">Pay now</small>
                            </div>
                        </label>
                        <label class="payment-option active" id="opt-later">
                            <input type="radio" name="payment_method" value="later" checked>
                            <div>
                                <i class="bi bi-clock text-secondary"></i>
                                <div class="fw-semibold">Pay Later</div>
                                <small class="text-muted">On installation</small>
                            </div>
                        </label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> Submit Order
                    </button>
                </form>
                
                <div class="back-link">
                    <a href="/"><i class="bi bi-arrow-left"></i> Back to packages</a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
        document.querySelectorAll('.payment-option').forEach(opt => {
            opt.addEventListener('click', function() {
                document.querySelectorAll('.payment-option').forEach(o => o.classList.remove('active'));
                this.classList.add('active');
                this.querySelector('input').checked = true;
            });
        });
    </script>
</body>
</html>
