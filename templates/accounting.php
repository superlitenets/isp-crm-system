<?php
$accounting = new \App\Accounting($db);
$mpesa = new \App\Mpesa();
$customerModel = new \App\Customer($db);

$subpage = $_GET['subpage'] ?? 'dashboard';
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

$stats = $accounting->getDashboardStats();
$taxRates = $accounting->getTaxRates();
$products = $accounting->getProducts();
$vendors = $accounting->getVendors();
$customers = $customerModel->getAll();
$expenseCategories = $accounting->getExpenseCategories();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-calculator"></i> Accounting</h2>
</div>

<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link <?= $subpage === 'dashboard' ? 'active' : '' ?>" href="?page=accounting&subpage=dashboard">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $subpage === 'invoices' ? 'active' : '' ?>" href="?page=accounting&subpage=invoices">
            <i class="bi bi-receipt"></i> Invoices
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $subpage === 'recurring' ? 'active' : '' ?>" href="?page=accounting&subpage=recurring">
            <i class="bi bi-arrow-repeat"></i> Recurring
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $subpage === 'quotes' ? 'active' : '' ?>" href="?page=accounting&subpage=quotes">
            <i class="bi bi-file-text"></i> Quotes
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $subpage === 'payments' ? 'active' : '' ?>" href="?page=accounting&subpage=payments">
            <i class="bi bi-cash-stack"></i> Payments
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $subpage === 'vendors' ? 'active' : '' ?>" href="?page=accounting&subpage=vendors">
            <i class="bi bi-building"></i> Vendors
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $subpage === 'bills' ? 'active' : '' ?>" href="?page=accounting&subpage=bills">
            <i class="bi bi-file-earmark-text"></i> Bills
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $subpage === 'expenses' ? 'active' : '' ?>" href="?page=accounting&subpage=expenses">
            <i class="bi bi-wallet2"></i> Expenses
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $subpage === 'products' ? 'active' : '' ?>" href="?page=accounting&subpage=products">
            <i class="bi bi-box"></i> Products
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $subpage === 'reports' ? 'active' : '' ?>" href="?page=accounting&subpage=reports">
            <i class="bi bi-graph-up"></i> Reports
        </a>
    </li>
</ul>

<?php if ($subpage === 'dashboard'): ?>

<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card bg-success bg-opacity-10 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-muted mb-1">Accounts Receivable</h6>
                        <h3 class="mb-0 text-success">KES <?= number_format($stats['total_receivable'], 2) ?></h3>
                        <small class="text-muted"><?= $stats['invoices_count'] ?> unpaid invoices</small>
                    </div>
                    <i class="bi bi-arrow-down-circle text-success" style="font-size: 2rem;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-danger bg-opacity-10 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-muted mb-1">Accounts Payable</h6>
                        <h3 class="mb-0 text-danger">KES <?= number_format($stats['total_payable'], 2) ?></h3>
                        <small class="text-muted"><?= $stats['bills_count'] ?> unpaid bills</small>
                    </div>
                    <i class="bi bi-arrow-up-circle text-danger" style="font-size: 2rem;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-primary bg-opacity-10 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-muted mb-1">Received This Month</h6>
                        <h3 class="mb-0 text-primary">KES <?= number_format($stats['month_received'], 2) ?></h3>
                        <small class="text-muted"><?= date('F Y') ?></small>
                    </div>
                    <i class="bi bi-cash text-primary" style="font-size: 2rem;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning bg-opacity-10 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-muted mb-1">Expenses This Month</h6>
                        <h3 class="mb-0 text-warning">KES <?= number_format($stats['month_expenses'], 2) ?></h3>
                        <small class="text-muted"><?= date('F Y') ?></small>
                    </div>
                    <i class="bi bi-wallet2 text-warning" style="font-size: 2rem;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($stats['overdue_receivable'] > 0 || $stats['overdue_payable'] > 0): ?>
<div class="row g-4 mb-4">
    <?php if ($stats['overdue_receivable'] > 0): ?>
    <div class="col-md-6">
        <div class="alert alert-warning mb-0">
            <i class="bi bi-exclamation-triangle"></i>
            <strong>KES <?= number_format($stats['overdue_receivable'], 2) ?></strong> overdue from customers
            <a href="?page=accounting&subpage=reports&tab=ar_aging" class="alert-link">View aging report</a>
        </div>
    </div>
    <?php endif; ?>
    <?php if ($stats['overdue_payable'] > 0): ?>
    <div class="col-md-6">
        <div class="alert alert-danger mb-0">
            <i class="bi bi-exclamation-triangle"></i>
            <strong>KES <?= number_format($stats['overdue_payable'], 2) ?></strong> overdue to vendors
            <a href="?page=accounting&subpage=reports&tab=ap_aging" class="alert-link">View aging report</a>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-receipt"></i> Recent Invoices</h5>
                <a href="?page=accounting&subpage=invoices&action=create" class="btn btn-sm btn-primary">
                    <i class="bi bi-plus"></i> New Invoice
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Invoice #</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $recentInvoices = $accounting->getInvoices(['limit' => 5]);
                            if (empty($recentInvoices)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-3">No invoices yet</td></tr>
                            <?php else: ?>
                            <?php foreach ($recentInvoices as $inv): ?>
                            <tr>
                                <td><a href="?page=accounting&subpage=invoices&action=view&id=<?= $inv['id'] ?>"><?= htmlspecialchars($inv['invoice_number']) ?></a></td>
                                <td><?= htmlspecialchars($inv['customer_name'] ?? 'N/A') ?></td>
                                <td>KES <?= number_format($inv['total_amount'], 2) ?></td>
                                <td>
                                    <?php
                                    $statusClass = match($inv['status']) {
                                        'paid' => 'success',
                                        'sent' => 'primary',
                                        'partial' => 'warning',
                                        'overdue' => 'danger',
                                        default => 'secondary'
                                    };
                                    ?>
                                    <span class="badge bg-<?= $statusClass ?>"><?= ucfirst($inv['status']) ?></span>
                                </td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-file-earmark-text"></i> Recent Bills</h5>
                <a href="?page=accounting&subpage=bills&action=create" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-plus"></i> New Bill
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Bill #</th>
                                <th>Vendor</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $recentBills = $accounting->getBills(['limit' => 5]);
                            if (empty($recentBills)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-3">No bills yet</td></tr>
                            <?php else: ?>
                            <?php foreach ($recentBills as $bill): ?>
                            <tr>
                                <td><a href="?page=accounting&subpage=bills&action=view&id=<?= $bill['id'] ?>"><?= htmlspecialchars($bill['bill_number']) ?></a></td>
                                <td><?= htmlspecialchars($bill['vendor_name'] ?? 'N/A') ?></td>
                                <td>KES <?= number_format($bill['total_amount'], 2) ?></td>
                                <td>
                                    <?php
                                    $statusClass = match($bill['status']) {
                                        'paid' => 'success',
                                        'partial' => 'warning',
                                        default => 'danger'
                                    };
                                    ?>
                                    <span class="badge bg-<?= $statusClass ?>"><?= ucfirst($bill['status']) ?></span>
                                </td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php elseif ($subpage === 'invoices'): ?>

<?php if ($action === 'create' || $action === 'edit'): ?>
<?php $invoice = $action === 'edit' && $id ? $accounting->getInvoice($id) : null; ?>

<div class="card">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="bi bi-receipt"></i> <?= $invoice ? 'Edit Invoice' : 'Create Invoice' ?></h5>
    </div>
    <div class="card-body">
        <form method="POST" id="invoiceForm">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="<?= $invoice ? 'update_invoice' : 'create_invoice' ?>">
            <?php if ($invoice): ?>
            <input type="hidden" name="invoice_id" value="<?= $invoice['id'] ?>">
            <?php endif; ?>
            
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label">Customer *</label>
                    <ul class="nav nav-tabs nav-tabs-sm mb-2" id="invCustomerTabs">
                        <li class="nav-item">
                            <a class="nav-link active py-1 px-2" data-bs-toggle="tab" href="#invCrmCustomer">CRM Customers</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link py-1 px-2" data-bs-toggle="tab" href="#invBillingCustomer">Billing Customers</a>
                        </li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="invCrmCustomer">
                            <select class="form-select" name="customer_id" id="invCustomerSelect">
                                <option value="">Select Customer</option>
                                <?php foreach ($customers as $cust): ?>
                                <option value="<?= $cust['id'] ?>" <?= ($invoice['customer_id'] ?? '') == $cust['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cust['name']) ?> (<?= htmlspecialchars($cust['account_number']) ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="tab-pane fade" id="invBillingCustomer">
                            <div class="input-group mb-2">
                                <input type="text" class="form-control" id="invBillingSearch" placeholder="Search by name, username or phone...">
                                <button type="button" class="btn btn-outline-primary" onclick="searchInvBillingCustomer()"><i class="bi bi-search"></i></button>
                            </div>
                            <div id="invBillingResults" class="small" style="max-height: 200px; overflow-y: auto;"></div>
                            <div id="invSelectedBilling" class="alert alert-success py-2 mt-2" style="display: none;">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong id="invBillingName"></strong> <span class="badge bg-secondary" id="invBillingUsername"></span><br>
                                        <small id="invBillingPhone"></small>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="clearInvBilling()"><i class="bi bi-x"></i></button>
                                </div>
                            </div>
                            <input type="hidden" name="billing_customer" id="invBillingData">
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Invoice Date</label>
                    <input type="date" class="form-control" name="issue_date" value="<?= $invoice['issue_date'] ?? date('Y-m-d') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Due Date</label>
                    <input type="date" class="form-control" name="due_date" value="<?= $invoice['due_date'] ?? date('Y-m-d', strtotime('+30 days')) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="draft" <?= ($invoice['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="sent" <?= ($invoice['status'] ?? '') === 'sent' ? 'selected' : '' ?>>Sent</option>
                        <option value="paid" <?= ($invoice['status'] ?? '') === 'paid' ? 'selected' : '' ?>>Paid</option>
                    </select>
                </div>
            </div>
            
            <h6 class="mb-3">Line Items</h6>
            <div class="table-responsive mb-3">
                <table class="table table-bordered" id="lineItemsTable">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 30%">Description</th>
                            <th style="width: 15%">Product/Service</th>
                            <th style="width: 10%">Qty</th>
                            <th style="width: 15%">Unit Price</th>
                            <th style="width: 10%">Tax</th>
                            <th style="width: 15%">Total</th>
                            <th style="width: 5%"></th>
                        </tr>
                    </thead>
                    <tbody id="lineItems">
                        <?php if ($invoice && !empty($invoice['items'])): ?>
                        <?php foreach ($invoice['items'] as $idx => $item): ?>
                        <tr class="line-item">
                            <td><input type="text" class="form-control form-control-sm" name="items[<?= $idx ?>][description]" value="<?= htmlspecialchars($item['description']) ?>" required></td>
                            <td>
                                <select class="form-select form-select-sm product-select" name="items[<?= $idx ?>][product_id]">
                                    <option value="">-</option>
                                    <?php foreach ($products as $prod): ?>
                                    <option value="<?= $prod['id'] ?>" data-price="<?= $prod['unit_price'] ?>" data-tax="<?= $prod['tax_rate_id'] ?? '' ?>" <?= ($item['product_id'] ?? '') == $prod['id'] ? 'selected' : '' ?>><?= htmlspecialchars($prod['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input type="number" class="form-control form-control-sm qty-input" name="items[<?= $idx ?>][quantity]" value="<?= $item['quantity'] ?>" min="0.01" step="0.01" required></td>
                            <td><input type="number" class="form-control form-control-sm price-input" name="items[<?= $idx ?>][unit_price]" value="<?= $item['unit_price'] ?>" min="0" step="0.01" required></td>
                            <td>
                                <select class="form-select form-select-sm tax-select" name="items[<?= $idx ?>][tax_rate_id]">
                                    <option value="">No Tax</option>
                                    <?php foreach ($taxRates as $tax): ?>
                                    <option value="<?= $tax['id'] ?>" data-rate="<?= $tax['rate'] ?>" <?= ($item['tax_rate_id'] ?? '') == $tax['id'] ? 'selected' : '' ?>><?= htmlspecialchars($tax['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input type="text" class="form-control form-control-sm line-total" readonly value="<?= number_format($item['line_total'], 2) ?>"></td>
                            <td><button type="button" class="btn btn-sm btn-outline-danger remove-line"><i class="bi bi-x"></i></button></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <tr class="line-item">
                            <td><input type="text" class="form-control form-control-sm" name="items[0][description]" placeholder="Item description" required></td>
                            <td>
                                <select class="form-select form-select-sm product-select" name="items[0][product_id]">
                                    <option value="">-</option>
                                    <?php foreach ($products as $prod): ?>
                                    <option value="<?= $prod['id'] ?>" data-price="<?= $prod['unit_price'] ?>" data-tax="<?= $prod['tax_rate_id'] ?? '' ?>"><?= htmlspecialchars($prod['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input type="number" class="form-control form-control-sm qty-input" name="items[0][quantity]" value="1" min="0.01" step="0.01" required></td>
                            <td><input type="number" class="form-control form-control-sm price-input" name="items[0][unit_price]" value="0" min="0" step="0.01" required></td>
                            <td>
                                <select class="form-select form-select-sm tax-select" name="items[0][tax_rate_id]">
                                    <option value="">No Tax</option>
                                    <?php foreach ($taxRates as $tax): ?>
                                    <option value="<?= $tax['id'] ?>" data-rate="<?= $tax['rate'] ?>" <?= $tax['is_default'] ? 'selected' : '' ?>><?= htmlspecialchars($tax['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input type="text" class="form-control form-control-sm line-total" readonly value="0.00"></td>
                            <td><button type="button" class="btn btn-sm btn-outline-danger remove-line"><i class="bi bi-x"></i></button></td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="7">
                                <button type="button" class="btn btn-sm btn-outline-primary" id="addLineItem">
                                    <i class="bi bi-plus"></i> Add Line
                                </button>
                            </td>
                        </tr>
                        <tr class="table-light">
                            <td colspan="5" class="text-end"><strong>Subtotal:</strong></td>
                            <td colspan="2"><span id="subtotal">0.00</span></td>
                        </tr>
                        <tr class="table-light">
                            <td colspan="5" class="text-end"><strong>Tax:</strong></td>
                            <td colspan="2"><span id="taxTotal">0.00</span></td>
                        </tr>
                        <tr class="table-light">
                            <td colspan="5" class="text-end"><strong>Total:</strong></td>
                            <td colspan="2"><strong><span id="grandTotal">0.00</span></strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <input type="hidden" name="subtotal" id="subtotalInput" value="0">
            <input type="hidden" name="tax_amount" id="taxInput" value="0">
            <input type="hidden" name="total_amount" id="totalInput" value="0">
            
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label">Notes</label>
                    <textarea class="form-control" name="notes" rows="3" placeholder="Additional notes for the customer"><?= htmlspecialchars($invoice['notes'] ?? '') ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Terms</label>
                    <textarea class="form-control" name="terms" rows="3" placeholder="Payment terms and conditions"><?= htmlspecialchars($invoice['terms'] ?? 'Payment due within 30 days.') ?></textarea>
                </div>
            </div>
            
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save Invoice</button>
                <a href="?page=accounting&subpage=invoices" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let lineIndex = document.querySelectorAll('.line-item').length;
    
    function calculateTotals() {
        let subtotal = 0;
        let taxTotal = 0;
        
        document.querySelectorAll('.line-item').forEach(row => {
            const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
            const price = parseFloat(row.querySelector('.price-input').value) || 0;
            const taxSelect = row.querySelector('.tax-select');
            const taxRate = parseFloat(taxSelect.options[taxSelect.selectedIndex]?.dataset?.rate) || 0;
            
            const lineSubtotal = qty * price;
            const lineTax = lineSubtotal * (taxRate / 100);
            const lineTotal = lineSubtotal + lineTax;
            
            row.querySelector('.line-total').value = lineTotal.toFixed(2);
            subtotal += lineSubtotal;
            taxTotal += lineTax;
        });
        
        document.getElementById('subtotal').textContent = subtotal.toFixed(2);
        document.getElementById('taxTotal').textContent = taxTotal.toFixed(2);
        document.getElementById('grandTotal').textContent = (subtotal + taxTotal).toFixed(2);
        document.getElementById('subtotalInput').value = subtotal.toFixed(2);
        document.getElementById('taxInput').value = taxTotal.toFixed(2);
        document.getElementById('totalInput').value = (subtotal + taxTotal).toFixed(2);
    }
    
    document.getElementById('addLineItem').addEventListener('click', function() {
        const tbody = document.getElementById('lineItems');
        const template = `
            <tr class="line-item">
                <td><input type="text" class="form-control form-control-sm" name="items[${lineIndex}][description]" placeholder="Item description" required></td>
                <td>
                    <select class="form-select form-select-sm product-select" name="items[${lineIndex}][product_id]">
                        <option value="">-</option>
                        <?php foreach ($products as $prod): ?>
                        <option value="<?= $prod['id'] ?>" data-price="<?= $prod['unit_price'] ?>" data-tax="<?= $prod['tax_rate_id'] ?? '' ?>"><?= htmlspecialchars($prod['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td><input type="number" class="form-control form-control-sm qty-input" name="items[${lineIndex}][quantity]" value="1" min="0.01" step="0.01" required></td>
                <td><input type="number" class="form-control form-control-sm price-input" name="items[${lineIndex}][unit_price]" value="0" min="0" step="0.01" required></td>
                <td>
                    <select class="form-select form-select-sm tax-select" name="items[${lineIndex}][tax_rate_id]">
                        <option value="">No Tax</option>
                        <?php foreach ($taxRates as $tax): ?>
                        <option value="<?= $tax['id'] ?>" data-rate="<?= $tax['rate'] ?>"><?= htmlspecialchars($tax['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td><input type="text" class="form-control form-control-sm line-total" readonly value="0.00"></td>
                <td><button type="button" class="btn btn-sm btn-outline-danger remove-line"><i class="bi bi-x"></i></button></td>
            </tr>
        `;
        tbody.insertAdjacentHTML('beforeend', template);
        lineIndex++;
        attachEventListeners();
    });
    
    function attachEventListeners() {
        document.querySelectorAll('.qty-input, .price-input, .tax-select').forEach(el => {
            el.removeEventListener('change', calculateTotals);
            el.addEventListener('change', calculateTotals);
        });
        
        document.querySelectorAll('.product-select').forEach(el => {
            el.removeEventListener('change', handleProductChange);
            el.addEventListener('change', handleProductChange);
        });
        
        document.querySelectorAll('.remove-line').forEach(btn => {
            btn.removeEventListener('click', removeLine);
            btn.addEventListener('click', removeLine);
        });
    }
    
    function handleProductChange(e) {
        const row = e.target.closest('tr');
        const option = e.target.options[e.target.selectedIndex];
        if (option.dataset.price) {
            row.querySelector('.price-input').value = option.dataset.price;
        }
        if (option.dataset.tax) {
            row.querySelector('.tax-select').value = option.dataset.tax;
        }
        row.querySelector('input[name*="description"]').value = option.textContent.trim();
        calculateTotals();
    }
    
    function removeLine(e) {
        if (document.querySelectorAll('.line-item').length > 1) {
            e.target.closest('tr').remove();
            calculateTotals();
        }
    }
    
    attachEventListeners();
    calculateTotals();
    
    // Billing customer search for invoices
    window.searchInvBillingCustomer = function() {
        const query = document.getElementById('invBillingSearch').value.trim();
        if (query.length < 2) {
            document.getElementById('invBillingResults').innerHTML = '<div class="text-muted">Enter at least 2 characters</div>';
            return;
        }
        document.getElementById('invBillingResults').innerHTML = '<div class="text-muted">Searching...</div>';
        fetch('/api/billing.php?action=search&q=' + encodeURIComponent(query))
            .then(r => r.json())
            .then(data => {
                if (data.error) {
                    document.getElementById('invBillingResults').innerHTML = '<div class="text-danger">' + data.error + '</div>';
                    return;
                }
                if (!data.customers || data.customers.length === 0) {
                    document.getElementById('invBillingResults').innerHTML = '<div class="alert alert-warning py-1">No customers found</div>';
                    return;
                }
                let html = '<div class="list-group">';
                data.customers.forEach(c => {
                    html += '<button type="button" class="list-group-item list-group-item-action py-1" onclick=\'selectInvBilling(' + JSON.stringify(c) + ')\'>' +
                        '<strong>' + (c.name || 'N/A') + '</strong>' +
                        (c.username ? ' <span class="badge bg-secondary">' + c.username + '</span>' : '') +
                        '<br><small class="text-muted">' + (c.phone || 'No phone') + '</small></button>';
                });
                html += '</div>';
                document.getElementById('invBillingResults').innerHTML = html;
            })
            .catch(err => {
                document.getElementById('invBillingResults').innerHTML = '<div class="text-danger">Error: ' + err.message + '</div>';
            });
    };
    
    window.selectInvBilling = function(customer) {
        document.getElementById('invBillingName').textContent = customer.name || 'N/A';
        document.getElementById('invBillingUsername').textContent = customer.username || '';
        document.getElementById('invBillingPhone').textContent = customer.phone || 'No phone';
        document.getElementById('invBillingData').value = JSON.stringify(customer);
        document.getElementById('invSelectedBilling').style.display = 'block';
        document.getElementById('invBillingResults').innerHTML = '';
        document.getElementById('invCustomerSelect').value = '';
    };
    
    window.clearInvBilling = function() {
        document.getElementById('invSelectedBilling').style.display = 'none';
        document.getElementById('invBillingData').value = '';
    };
    
    document.getElementById('invBillingSearch')?.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') { e.preventDefault(); searchInvBillingCustomer(); }
    });
});
</script>

<?php elseif ($action === 'view' && $id): ?>
<?php $invoice = $accounting->getInvoice($id); ?>
<?php if (!$invoice): ?>
<div class="alert alert-danger">Invoice not found.</div>
<?php else: ?>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Invoice <?= htmlspecialchars($invoice['invoice_number']) ?></h5>
                <div class="btn-group">
                    <a href="?page=accounting&subpage=invoices&action=edit&id=<?= $invoice['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Edit</a>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="action" value="download_invoice_pdf">
                        <input type="hidden" name="invoice_id" value="<?= $invoice['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-file-pdf"></i> PDF</button>
                    </form>
                    <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#sendInvoiceEmailModal"><i class="bi bi-envelope"></i> Email</button>
                    <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#sendInvoiceWhatsAppModal"><i class="bi bi-whatsapp"></i> WhatsApp</button>
                    <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#recordPaymentModal"><i class="bi bi-cash"></i> Record Payment</button>
                    <?php if ($invoice['balance_due'] > 0 && $mpesa->isConfigured()): ?>
                    <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#mpesaPaymentModal"><i class="bi bi-phone"></i> M-Pesa</button>
                    <?php endif; ?>
                    <?php if (!$invoice['is_recurring']): ?>
                    <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#makeRecurringModal"><i class="bi bi-arrow-repeat"></i> Make Recurring</button>
                    <?php else: ?>
                    <span class="btn btn-sm btn-warning disabled"><i class="bi bi-arrow-repeat"></i> Recurring (<?= ucfirst($invoice['recurring_interval']) ?>)</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6>Bill To:</h6>
                        <strong><?= htmlspecialchars($invoice['customer_name'] ?? 'N/A') ?></strong><br>
                        <?= htmlspecialchars($invoice['customer_email'] ?? '') ?><br>
                        <?= htmlspecialchars($invoice['customer_phone'] ?? '') ?>
                    </div>
                    <div class="col-md-6 text-end">
                        <p><strong>Invoice Date:</strong> <?= date('M j, Y', strtotime($invoice['issue_date'])) ?></p>
                        <p><strong>Due Date:</strong> <?= date('M j, Y', strtotime($invoice['due_date'])) ?></p>
                        <p><strong>Status:</strong> 
                            <?php
                            $statusClass = match($invoice['status']) {
                                'paid' => 'success',
                                'sent' => 'primary',
                                'partial' => 'warning',
                                'overdue' => 'danger',
                                default => 'secondary'
                            };
                            ?>
                            <span class="badge bg-<?= $statusClass ?>"><?= ucfirst($invoice['status']) ?></span>
                        </p>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Description</th>
                                <th class="text-center">Qty</th>
                                <th class="text-end">Unit Price</th>
                                <th class="text-end">Tax</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($invoice['items'] as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['description']) ?></td>
                                <td class="text-center"><?= $item['quantity'] ?></td>
                                <td class="text-end">KES <?= number_format($item['unit_price'], 2) ?></td>
                                <td class="text-end">KES <?= number_format($item['tax_amount'], 2) ?></td>
                                <td class="text-end">KES <?= number_format($item['line_total'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="4" class="text-end"><strong>Subtotal:</strong></td>
                                <td class="text-end">KES <?= number_format($invoice['subtotal'], 2) ?></td>
                            </tr>
                            <tr>
                                <td colspan="4" class="text-end"><strong>Tax:</strong></td>
                                <td class="text-end">KES <?= number_format($invoice['tax_amount'], 2) ?></td>
                            </tr>
                            <tr class="table-light">
                                <td colspan="4" class="text-end"><strong>Total:</strong></td>
                                <td class="text-end"><strong>KES <?= number_format($invoice['total_amount'], 2) ?></strong></td>
                            </tr>
                            <?php if ($invoice['amount_paid'] > 0): ?>
                            <tr>
                                <td colspan="4" class="text-end">Amount Paid:</td>
                                <td class="text-end text-success">KES <?= number_format($invoice['amount_paid'], 2) ?></td>
                            </tr>
                            <tr class="table-warning">
                                <td colspan="4" class="text-end"><strong>Balance Due:</strong></td>
                                <td class="text-end"><strong>KES <?= number_format($invoice['balance_due'], 2) ?></strong></td>
                            </tr>
                            <?php endif; ?>
                        </tfoot>
                    </table>
                </div>
                
                <?php if ($invoice['notes']): ?>
                <div class="mt-3">
                    <strong>Notes:</strong><br>
                    <?= nl2br(htmlspecialchars($invoice['notes'])) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-white">
                <h6 class="mb-0">Payment History</h6>
            </div>
            <div class="card-body">
                <?php 
                $payments = $accounting->getCustomerPayments(['invoice_id' => $invoice['id']]);
                if (empty($payments)): ?>
                <p class="text-muted">No payments recorded</p>
                <?php else: ?>
                <?php foreach ($payments as $payment): ?>
                <div class="border-bottom py-2">
                    <div class="d-flex justify-content-between">
                        <div>
                            <small class="text-muted"><?= date('M j, Y', strtotime($payment['payment_date'])) ?></small><br>
                            <?= htmlspecialchars($payment['payment_method']) ?>
                        </div>
                        <strong class="text-success">KES <?= number_format($payment['amount'], 2) ?></strong>
                    </div>
                    <div class="mt-1">
                        <button type="button" class="btn btn-outline-info btn-sm py-0" data-bs-toggle="modal" data-bs-target="#sendReceiptEmailModal<?= $payment['id'] ?>">
                            <i class="bi bi-envelope"></i>
                        </button>
                        <button type="button" class="btn btn-success btn-sm py-0" data-bs-toggle="modal" data-bs-target="#sendReceiptWhatsAppModal<?= $payment['id'] ?>">
                            <i class="bi bi-whatsapp"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Receipt Email Modal for Payment <?= $payment['id'] ?> -->
                <div class="modal fade" id="sendReceiptEmailModal<?= $payment['id'] ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="POST">
                                <div class="modal-header bg-info text-white">
                                    <h5 class="modal-title"><i class="bi bi-envelope"></i> Send Receipt via Email</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                    <input type="hidden" name="action" value="send_receipt_email">
                                    <input type="hidden" name="payment_id" value="<?= $payment['id'] ?>">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Recipient Email *</label>
                                        <input type="email" class="form-control" name="to_email" value="<?= htmlspecialchars($invoice['customer_email'] ?? '') ?>" required>
                                    </div>
                                    <div class="alert alert-info small mb-0">
                                        <i class="bi bi-receipt me-1"></i>
                                        Receipt for KES <?= number_format($payment['amount'], 2) ?> will be attached as PDF.
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-info"><i class="bi bi-send"></i> Send Receipt</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Receipt WhatsApp Modal for Payment <?= $payment['id'] ?> -->
                <div class="modal fade" id="sendReceiptWhatsAppModal<?= $payment['id'] ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="POST">
                                <div class="modal-header bg-success text-white">
                                    <h5 class="modal-title"><i class="bi bi-whatsapp"></i> Send Receipt via WhatsApp</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                    <input type="hidden" name="action" value="send_receipt_whatsapp">
                                    <input type="hidden" name="payment_id" value="<?= $payment['id'] ?>">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Phone Number *</label>
                                        <input type="text" class="form-control" name="phone" value="<?= htmlspecialchars($invoice['customer_phone'] ?? '') ?>" required placeholder="e.g., 0712345678">
                                    </div>
                                    <div class="alert alert-success small mb-0">
                                        <i class="bi bi-receipt me-1"></i>
                                        Receipt for KES <?= number_format($payment['amount'], 2) ?> will be sent as PDF.
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-success"><i class="bi bi-whatsapp"></i> Send Receipt</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Send Invoice Email Modal -->
<div class="modal fade" id="sendInvoiceEmailModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title"><i class="bi bi-envelope"></i> Send Invoice via Email</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="send_invoice_email">
                    <input type="hidden" name="invoice_id" value="<?= $invoice['id'] ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Recipient Email *</label>
                        <input type="email" class="form-control" name="to_email" value="<?= htmlspecialchars($invoice['customer_email'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Subject (optional)</label>
                        <input type="text" class="form-control" name="email_subject" placeholder="Leave blank for default subject">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Message (optional)</label>
                        <textarea class="form-control" name="email_message" rows="3" placeholder="Leave blank for default message"></textarea>
                    </div>
                    <div class="alert alert-info small mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        Invoice #<?= htmlspecialchars($invoice['invoice_number']) ?> for KES <?= number_format($invoice['total_amount'] ?? 0, 2) ?> will be attached as PDF.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info"><i class="bi bi-send"></i> Send Email</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Send Invoice WhatsApp Modal -->
<div class="modal fade" id="sendInvoiceWhatsAppModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="bi bi-whatsapp"></i> Send Invoice via WhatsApp</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="send_invoice_whatsapp">
                    <input type="hidden" name="invoice_id" value="<?= $invoice['id'] ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Phone Number *</label>
                        <input type="text" class="form-control" name="phone" value="<?= htmlspecialchars($invoice['customer_phone'] ?? '') ?>" required placeholder="e.g., 0712345678">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Caption (optional)</label>
                        <textarea class="form-control" name="caption" rows="2" placeholder="Leave blank for default caption"></textarea>
                    </div>
                    <div class="alert alert-success small mb-0">
                        <i class="bi bi-file-pdf me-1"></i>
                        Invoice #<?= htmlspecialchars($invoice['invoice_number']) ?> will be sent as a PDF document.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="bi bi-whatsapp"></i> Send WhatsApp</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="recordPaymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Record Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="record_customer_payment">
                    <input type="hidden" name="invoice_id" value="<?= $invoice['id'] ?>">
                    <input type="hidden" name="customer_id" value="<?= $invoice['customer_id'] ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Amount *</label>
                        <div class="input-group">
                            <span class="input-group-text">KES</span>
                            <input type="number" class="form-control" name="amount" value="<?= $invoice['balance_due'] ?>" min="0.01" step="0.01" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payment Date</label>
                        <input type="date" class="form-control" name="payment_date" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payment Method *</label>
                        <select class="form-select" name="payment_method" required>
                            <option value="M-Pesa">M-Pesa</option>
                            <option value="Cash">Cash</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Cheque">Cheque</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reference</label>
                        <input type="text" class="form-control" name="reference" placeholder="M-Pesa code, cheque number, etc.">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Record Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($invoice['balance_due'] > 0 && $mpesa->isConfigured()): ?>
<?php 
$customerPhone = $invoice['customer_phone'] ?? '';
if (empty($customerPhone) && !empty($invoice['customer_id'])) {
    $custData = (new \App\Customer(Database::getConnection()))->get($invoice['customer_id']);
    $customerPhone = $custData['phone'] ?? '';
}
$maxPayment = floor($invoice['balance_due']);
if ($maxPayment < 1 && $invoice['balance_due'] > 0) {
    $maxPayment = 1;
}
?>
<div class="modal fade" id="mpesaPaymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="mpesaPaymentForm">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="bi bi-phone"></i> Pay with M-Pesa</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="mpesa_invoice_stkpush">
                    <input type="hidden" name="invoice_id" value="<?= $invoice['id'] ?>">
                    <input type="hidden" name="max_amount" value="<?= $maxPayment ?>">
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> An M-Pesa prompt will be sent to the phone number. The customer will need to enter their PIN to complete payment.
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Phone Number *</label>
                        <input type="tel" class="form-control" name="phone" id="mpesaPhone" value="<?= htmlspecialchars($customerPhone) ?>" placeholder="e.g., 0712345678" pattern="^(0|254|\+254)?[17][0-9]{8}$" required>
                        <div class="form-text">Kenyan phone number (Safaricom)</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Amount (KES) *</label>
                        <div class="input-group">
                            <span class="input-group-text">KES</span>
                            <input type="number" class="form-control" name="amount" id="mpesaAmount" value="<?= $maxPayment ?>" min="1" max="<?= $maxPayment ?>" step="1" required>
                        </div>
                        <div class="form-text">Balance due: KES <?= number_format($invoice['balance_due'], 2) ?> (Max: KES <?= number_format($maxPayment, 0) ?>)</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="bi bi-send"></i> Send Payment Request</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.getElementById('mpesaPaymentForm').addEventListener('submit', function(e) {
    const amount = parseInt(document.getElementById('mpesaAmount').value);
    const max = <?= $maxPayment ?>;
    if (amount < 1 || amount > max) {
        e.preventDefault();
        alert('Amount must be between KES 1 and KES ' + max);
        return false;
    }
    const phone = document.getElementById('mpesaPhone').value.trim();
    if (!phone.match(/^(0|254|\+254)?[17][0-9]{8}$/)) {
        e.preventDefault();
        alert('Please enter a valid Kenyan phone number');
        return false;
    }
});
</script>
<?php endif; ?>

<!-- Make Recurring Modal -->
<?php if (!$invoice['is_recurring']): ?>
<div class="modal fade" id="makeRecurringModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title"><i class="bi bi-arrow-repeat"></i> Make Invoice Recurring</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="make_recurring">
                    <input type="hidden" name="invoice_id" value="<?= $invoice['id'] ?>">
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> This will use this invoice as a template. New invoices will be automatically generated on the schedule you set.
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Invoice Template</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($invoice['invoice_number']) ?> - <?= htmlspecialchars($invoice['customer_name'] ?? 'N/A') ?> (KES <?= number_format($invoice['total_amount'], 2) ?>)" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Recurring Interval *</label>
                        <select name="recurring_interval" class="form-select" required>
                            <option value="weekly">Weekly</option>
                            <option value="biweekly">Bi-Weekly</option>
                            <option value="monthly" selected>Monthly</option>
                            <option value="quarterly">Quarterly</option>
                            <option value="semi-annually">Semi-Annually</option>
                            <option value="annually">Annually</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">First Invoice Date *</label>
                        <input type="date" name="next_recurring_date" class="form-control" value="<?= date('Y-m-d', strtotime('+1 month')) ?>" required>
                        <div class="form-text">When should the first recurring invoice be generated?</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning"><i class="bi bi-arrow-repeat"></i> Make Recurring</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>

<?php else: ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div></div>
    <a href="?page=accounting&subpage=invoices&action=create" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> New Invoice
    </a>
</div>

<?php $invoices = $accounting->getInvoices(); ?>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Invoice #</th>
                        <th>Customer</th>
                        <th>Date</th>
                        <th>Due Date</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Balance</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($invoices)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No invoices yet. <a href="?page=accounting&subpage=invoices&action=create">Create your first invoice</a></td></tr>
                    <?php else: ?>
                    <?php foreach ($invoices as $inv): ?>
                    <tr>
                        <td><a href="?page=accounting&subpage=invoices&action=view&id=<?= $inv['id'] ?>"><?= htmlspecialchars($inv['invoice_number']) ?></a></td>
                        <td><?= htmlspecialchars($inv['customer_name'] ?? 'N/A') ?></td>
                        <td><?= date('M j, Y', strtotime($inv['issue_date'])) ?></td>
                        <td><?= date('M j, Y', strtotime($inv['due_date'])) ?></td>
                        <td class="text-end">KES <?= number_format($inv['total_amount'], 2) ?></td>
                        <td class="text-end"><?= $inv['balance_due'] > 0 ? 'KES ' . number_format($inv['balance_due'], 2) : '-' ?></td>
                        <td>
                            <?php
                            $statusClass = match($inv['status']) {
                                'paid' => 'success',
                                'sent' => 'primary',
                                'partial' => 'warning',
                                'overdue' => 'danger',
                                default => 'secondary'
                            };
                            ?>
                            <span class="badge bg-<?= $statusClass ?>"><?= ucfirst($inv['status']) ?></span>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="?page=accounting&subpage=invoices&action=view&id=<?= $inv['id'] ?>" class="btn btn-outline-primary" title="View"><i class="bi bi-eye"></i></a>
                                <a href="?page=accounting&subpage=invoices&action=edit&id=<?= $inv['id'] ?>" class="btn btn-outline-secondary" title="Edit"><i class="bi bi-pencil"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php endif; ?>

<?php elseif ($subpage === 'vendors'): ?>

<?php if ($action === 'create' || $action === 'edit'): ?>
<?php $vendor = $action === 'edit' && $id ? $accounting->getVendor($id) : null; ?>

<div class="card">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="bi bi-building"></i> <?= $vendor ? 'Edit Vendor' : 'Add Vendor' ?></h5>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="<?= $vendor ? 'update_vendor' : 'create_vendor' ?>">
            <?php if ($vendor): ?>
            <input type="hidden" name="vendor_id" value="<?= $vendor['id'] ?>">
            <?php endif; ?>
            
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Vendor Name *</label>
                    <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($vendor['name'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Contact Person</label>
                    <input type="text" class="form-control" name="contact_person" value="<?= htmlspecialchars($vendor['contact_person'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($vendor['email'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Phone</label>
                    <input type="text" class="form-control" name="phone" value="<?= htmlspecialchars($vendor['phone'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tax PIN</label>
                    <input type="text" class="form-control" name="tax_pin" value="<?= htmlspecialchars($vendor['tax_pin'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Address</label>
                    <textarea class="form-control" name="address" rows="2"><?= htmlspecialchars($vendor['address'] ?? '') ?></textarea>
                </div>
                <div class="col-md-3">
                    <label class="form-label">City</label>
                    <input type="text" class="form-control" name="city" value="<?= htmlspecialchars($vendor['city'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Payment Terms (days)</label>
                    <input type="number" class="form-control" name="payment_terms" value="<?= $vendor['payment_terms'] ?? 30 ?>" min="0">
                </div>
                <div class="col-12">
                    <label class="form-label">Notes</label>
                    <textarea class="form-control" name="notes" rows="2"><?= htmlspecialchars($vendor['notes'] ?? '') ?></textarea>
                </div>
            </div>
            
            <div class="mt-4">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save Vendor</button>
                <a href="?page=accounting&subpage=vendors" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php else: ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div></div>
    <a href="?page=accounting&subpage=vendors&action=create" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> Add Vendor
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Vendor Name</th>
                        <th>Contact</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Payment Terms</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($vendors)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No vendors yet. <a href="?page=accounting&subpage=vendors&action=create">Add your first vendor</a></td></tr>
                    <?php else: ?>
                    <?php foreach ($vendors as $v): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($v['name']) ?></strong></td>
                        <td><?= htmlspecialchars($v['contact_person'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($v['phone'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($v['email'] ?? '-') ?></td>
                        <td><?= $v['payment_terms'] ?> days</td>
                        <td>
                            <a href="?page=accounting&subpage=vendors&action=edit&id=<?= $v['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php endif; ?>

<?php elseif ($subpage === 'expenses'): ?>

<?php if ($action === 'create'): ?>

<div class="card">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="bi bi-wallet2"></i> Record Expense</h5>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="create_expense">
            
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Category *</label>
                    <select class="form-select" name="category_id" required>
                        <option value="">Select Category</option>
                        <?php foreach ($expenseCategories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Vendor (Optional)</label>
                    <select class="form-select" name="vendor_id">
                        <option value="">Select Vendor</option>
                        <?php foreach ($vendors as $v): ?>
                        <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Date *</label>
                    <input type="date" class="form-control" name="expense_date" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Amount *</label>
                    <div class="input-group">
                        <span class="input-group-text">KES</span>
                        <input type="number" class="form-control" name="amount" min="0.01" step="0.01" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Payment Method</label>
                    <select class="form-select" name="payment_method">
                        <option value="Cash">Cash</option>
                        <option value="M-Pesa">M-Pesa</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                        <option value="Cheque">Cheque</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Reference</label>
                    <input type="text" class="form-control" name="reference" placeholder="Receipt/Transaction number">
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea class="form-control" name="description" rows="2"></textarea>
                </div>
            </div>
            
            <div class="mt-4">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save Expense</button>
                <a href="?page=accounting&subpage=expenses" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php else: ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div></div>
    <a href="?page=accounting&subpage=expenses&action=create" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> Record Expense
    </a>
</div>

<?php $expenses = $accounting->getExpenses(); ?>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Category</th>
                        <th>Vendor</th>
                        <th>Description</th>
                        <th>Payment Method</th>
                        <th class="text-end">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($expenses)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No expenses recorded. <a href="?page=accounting&subpage=expenses&action=create">Record your first expense</a></td></tr>
                    <?php else: ?>
                    <?php foreach ($expenses as $exp): ?>
                    <tr>
                        <td><?= date('M j, Y', strtotime($exp['expense_date'])) ?></td>
                        <td><span class="badge bg-secondary"><?= htmlspecialchars($exp['category_name'] ?? 'Uncategorized') ?></span></td>
                        <td><?= htmlspecialchars($exp['vendor_name'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($exp['description'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($exp['payment_method'] ?? '-') ?></td>
                        <td class="text-end"><strong>KES <?= number_format($exp['total_amount'], 2) ?></strong></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php endif; ?>

<?php elseif ($subpage === 'payments'): ?>

<?php $mpesaStats = $accounting->getMpesaPaymentStats(); ?>
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card bg-success bg-opacity-10 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-muted mb-1">M-Pesa Today</h6>
                        <h3 class="mb-0 text-success">KES <?= number_format($mpesaStats['today_total'], 2) ?></h3>
                        <small class="text-muted"><?= $mpesaStats['today_count'] ?> transaction<?= $mpesaStats['today_count'] != 1 ? 's' : '' ?></small>
                    </div>
                    <i class="bi bi-phone text-success" style="font-size: 2rem;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-primary bg-opacity-10 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-muted mb-1">M-Pesa This Month</h6>
                        <h3 class="mb-0 text-primary">KES <?= number_format($mpesaStats['month_total'], 2) ?></h3>
                        <small class="text-muted"><?= $mpesaStats['month_count'] ?> transaction<?= $mpesaStats['month_count'] != 1 ? 's' : '' ?> in <?= date('F') ?></small>
                    </div>
                    <i class="bi bi-calendar-check text-primary" style="font-size: 2rem;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-info bg-opacity-10 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-muted mb-1">M-Pesa All Time</h6>
                        <h3 class="mb-0 text-info">KES <?= number_format($mpesaStats['all_time_total'], 2) ?></h3>
                        <small class="text-muted"><?= $mpesaStats['all_time_count'] ?> total transaction<?= $mpesaStats['all_time_count'] != 1 ? 's' : '' ?></small>
                    </div>
                    <i class="bi bi-graph-up text-info" style="font-size: 2rem;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-cash"></i> Record Payment</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="record_customer_payment">
                    
                    <div class="mb-3">
                        <label class="form-label">Customer *</label>
                        <select class="form-select" name="customer_id" required>
                            <option value="">Select Customer</option>
                            <?php foreach ($customers as $cust): ?>
                            <option value="<?= $cust['id'] ?>"><?= htmlspecialchars($cust['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Invoice (Optional)</label>
                        <select class="form-select" name="invoice_id">
                            <option value="">Select Invoice</option>
                            <?php 
                            $unpaidInvoices = $accounting->getInvoices(['status' => 'sent']);
                            foreach ($unpaidInvoices as $inv): ?>
                            <option value="<?= $inv['id'] ?>"><?= htmlspecialchars($inv['invoice_number']) ?> - KES <?= number_format($inv['balance_due'], 2) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Amount *</label>
                        <div class="input-group">
                            <span class="input-group-text">KES</span>
                            <input type="number" class="form-control" name="amount" min="0.01" step="0.01" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payment Method *</label>
                        <select class="form-select" name="payment_method" required>
                            <option value="M-Pesa">M-Pesa</option>
                            <option value="Cash">Cash</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Cheque">Cheque</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reference</label>
                        <input type="text" class="form-control" name="reference" placeholder="M-Pesa code, cheque #">
                    </div>
                    <button type="submit" class="btn btn-success w-100">
                        <i class="bi bi-check-lg"></i> Record Payment
                    </button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-list"></i> Recent Payments</h5>
            </div>
            <div class="card-body p-0">
                <?php $payments = $accounting->getCustomerPayments(['limit' => 20]); ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Payment #</th>
                                <th>Customer</th>
                                <th>Invoice</th>
                                <th>Method</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($payments)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No payments recorded</td></tr>
                            <?php else: ?>
                            <?php foreach ($payments as $p): ?>
                            <tr>
                                <td><?= date('M j, Y', strtotime($p['payment_date'])) ?></td>
                                <td><?= htmlspecialchars($p['payment_number']) ?></td>
                                <td><?= htmlspecialchars($p['customer_name'] ?? 'N/A') ?></td>
                                <td><?= $p['invoice_number'] ? htmlspecialchars($p['invoice_number']) : '-' ?></td>
                                <td><?= htmlspecialchars($p['payment_method']) ?></td>
                                <td class="text-end text-success"><strong>KES <?= number_format($p['amount'], 2) ?></strong></td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($mpesa->isConfigured()): ?>
<div class="row g-4 mt-2">
    <div class="col-md-4">
        <div class="card border-success">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-phone"></i> M-Pesa STK Push</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="?page=accounting&subpage=payments" id="stkPushForm">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="accounting_stkpush">
                    
                    <div class="mb-3">
                        <label class="form-label">Customer</label>
                        <select class="form-select" name="customer_id" id="stkCustomer">
                            <option value="">Select Customer (Optional)</option>
                            <?php foreach ($customers as $cust): ?>
                            <option value="<?= $cust['id'] ?>" data-phone="<?= htmlspecialchars($cust['phone'] ?? '') ?>"><?= htmlspecialchars($cust['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone Number *</label>
                        <input type="tel" class="form-control" name="phone" id="stkPhone" placeholder="0712345678" required>
                        <div class="form-text">Safaricom number</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Amount (KES) *</label>
                        <input type="number" class="form-control" name="amount" min="1" step="1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Invoice (Optional)</label>
                        <select class="form-select" name="invoice_id">
                            <option value="">No Invoice</option>
                            <?php 
                            $unpaidInvoices = $accounting->getInvoices(['status' => 'sent']);
                            foreach ($unpaidInvoices as $inv): ?>
                            <option value="<?= $inv['id'] ?>"><?= htmlspecialchars($inv['invoice_number']) ?> - KES <?= number_format($inv['balance_due'], 2) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reference</label>
                        <input type="text" class="form-control" name="reference" placeholder="Payment reference">
                    </div>
                    <button type="submit" class="btn btn-success w-100">
                        <i class="bi bi-send"></i> Send STK Push
                    </button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-list-check"></i> M-Pesa Transactions</h5>
            </div>
            <div class="card-body p-0">
                <?php $mpesaTxns = $mpesa->getTransactions(20); ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Phone</th>
                                <th>Reference</th>
                                <th>Receipt</th>
                                <th class="text-end">Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($mpesaTxns)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No M-Pesa transactions yet</td></tr>
                            <?php else: ?>
                            <?php foreach ($mpesaTxns as $tx): ?>
                            <tr>
                                <td><?= date('M j, H:i', strtotime($tx['created_at'])) ?></td>
                                <td><?= htmlspecialchars($tx['phone_number'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($tx['account_reference'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($tx['mpesa_receipt_number'] ?? '-') ?></td>
                                <td class="text-end">KES <?= number_format($tx['amount'] ?? 0, 2) ?></td>
                                <td>
                                    <?php
                                    $statusClass = match($tx['status'] ?? 'pending') {
                                        'completed' => 'success',
                                        'failed' => 'danger',
                                        default => 'warning'
                                    };
                                    ?>
                                    <span class="badge bg-<?= $statusClass ?>"><?= ucfirst($tx['status'] ?? 'pending') ?></span>
                                </td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
document.getElementById('stkCustomer').addEventListener('change', function() {
    const option = this.options[this.selectedIndex];
    if (option.dataset.phone) {
        document.getElementById('stkPhone').value = option.dataset.phone;
    }
});
</script>
<?php endif; ?>

<?php elseif ($subpage === 'products'): ?>

<?php if ($action === 'create' || $action === 'edit'): ?>
<?php $product = $action === 'edit' && $id ? $accounting->getProduct($id) : null; ?>

<div class="card">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="bi bi-box"></i> <?= $product ? 'Edit Product/Service' : 'Add Product/Service' ?></h5>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="<?= $product ? 'update_product' : 'create_product' ?>">
            <?php if ($product): ?>
            <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
            <?php endif; ?>
            
            <div class="row g-3">
                <div class="col-md-2">
                    <label class="form-label">Code</label>
                    <input type="text" class="form-control" name="code" value="<?= htmlspecialchars($product['code'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Name *</label>
                    <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($product['name'] ?? '') ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Type</label>
                    <select class="form-select" name="type">
                        <option value="service" <?= ($product['type'] ?? '') === 'service' ? 'selected' : '' ?>>Service</option>
                        <option value="product" <?= ($product['type'] ?? '') === 'product' ? 'selected' : '' ?>>Product</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Selling Price *</label>
                    <div class="input-group">
                        <span class="input-group-text">KES</span>
                        <input type="number" class="form-control" name="unit_price" value="<?= $product['unit_price'] ?? 0 ?>" min="0" step="0.01" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Cost Price</label>
                    <div class="input-group">
                        <span class="input-group-text">KES</span>
                        <input type="number" class="form-control" name="cost_price" value="<?= $product['cost_price'] ?? 0 ?>" min="0" step="0.01">
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tax Rate</label>
                    <select class="form-select" name="tax_rate_id">
                        <option value="">No Tax</option>
                        <?php foreach ($taxRates as $tax): ?>
                        <option value="<?= $tax['id'] ?>" <?= ($product['tax_rate_id'] ?? '') == $tax['id'] ? 'selected' : '' ?>><?= htmlspecialchars($tax['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea class="form-control" name="description" rows="2"><?= htmlspecialchars($product['description'] ?? '') ?></textarea>
                </div>
            </div>
            
            <div class="mt-4">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save</button>
                <a href="?page=accounting&subpage=products" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php else: ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div></div>
    <a href="?page=accounting&subpage=products&action=create" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> Add Product/Service
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th class="text-end">Selling Price</th>
                        <th class="text-end">Cost Price</th>
                        <th>Tax</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No products/services. <a href="?page=accounting&subpage=products&action=create">Add your first</a></td></tr>
                    <?php else: ?>
                    <?php foreach ($products as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['code'] ?? '-') ?></td>
                        <td><strong><?= htmlspecialchars($p['name']) ?></strong></td>
                        <td><span class="badge bg-<?= $p['type'] === 'service' ? 'info' : 'secondary' ?>"><?= ucfirst($p['type']) ?></span></td>
                        <td class="text-end">KES <?= number_format($p['unit_price'], 2) ?></td>
                        <td class="text-end"><?= $p['cost_price'] > 0 ? 'KES ' . number_format($p['cost_price'], 2) : '-' ?></td>
                        <td><?= htmlspecialchars($p['tax_name'] ?? 'No Tax') ?></td>
                        <td>
                            <a href="?page=accounting&subpage=products&action=edit&id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php endif; ?>

<?php elseif ($subpage === 'recurring'): ?>

<?php
$recurringInvoices = $accounting->getRecurringInvoices();
$recurringStats = $accounting->getRecurringStats();
$intervals = [
    'weekly' => 'Weekly',
    'biweekly' => 'Bi-Weekly',
    'monthly' => 'Monthly',
    'quarterly' => 'Quarterly',
    'semi-annually' => 'Semi-Annually',
    'annually' => 'Annually'
];
?>

<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card bg-primary bg-opacity-10 h-100">
            <div class="card-body">
                <h6 class="text-muted mb-1">Active Recurring</h6>
                <h3 class="mb-0 text-primary"><?= $recurringStats['total_recurring'] ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-warning bg-opacity-10 h-100">
            <div class="card-body">
                <h6 class="text-muted mb-1">Due Today</h6>
                <h3 class="mb-0 text-warning"><?= $recurringStats['due_today'] ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-success bg-opacity-10 h-100">
            <div class="card-body">
                <h6 class="text-muted mb-1">Recurring Value</h6>
                <h3 class="mb-0 text-success">KES <?= number_format($recurringStats['monthly_recurring_value'], 2) ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-arrow-repeat"></i> Recurring Invoices</h5>
        <div>
            <form method="post" class="d-inline">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="process_recurring">
                <button type="submit" class="btn btn-success btn-sm">
                    <i class="bi bi-play-fill"></i> Process Due Invoices
                </button>
            </form>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($recurringInvoices)): ?>
        <div class="text-center text-muted py-5">
            <i class="bi bi-arrow-repeat display-4"></i>
            <p class="mt-2">No recurring invoices set up yet.</p>
            <p class="small">To create a recurring invoice, go to an existing invoice and click "Make Recurring".</p>
            <a href="?page=accounting&subpage=invoices" class="btn btn-primary">
                <i class="bi bi-receipt"></i> View Invoices
            </a>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Interval</th>
                        <th>Next Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recurringInvoices as $inv): ?>
                    <tr>
                        <td>
                            <a href="?page=accounting&subpage=invoices&action=view&id=<?= $inv['id'] ?>">
                                <?= htmlspecialchars($inv['invoice_number']) ?>
                            </a>
                        </td>
                        <td><?= htmlspecialchars($inv['customer_name'] ?? 'N/A') ?></td>
                        <td>KES <?= number_format($inv['total_amount'], 2) ?></td>
                        <td>
                            <span class="badge bg-info"><?= $intervals[$inv['recurring_interval']] ?? ucfirst($inv['recurring_interval']) ?></span>
                        </td>
                        <td>
                            <?php 
                            $nextDate = $inv['next_recurring_date'] ? new DateTime($inv['next_recurring_date']) : null;
                            $today = new DateTime();
                            $isDue = $nextDate && $nextDate <= $today;
                            ?>
                            <span class="<?= $isDue ? 'text-danger fw-bold' : '' ?>">
                                <?= $nextDate ? $nextDate->format('M d, Y') : 'Not set' ?>
                                <?php if ($isDue): ?>
                                    <span class="badge bg-danger ms-1">Due</span>
                                <?php endif; ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-success">Active</span>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="?page=accounting&subpage=invoices&action=view&id=<?= $inv['id'] ?>" class="btn btn-outline-primary" title="View">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <button type="button" class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#editRecurringModal<?= $inv['id'] ?>" title="Edit Schedule">
                                    <i class="bi bi-calendar-event"></i>
                                </button>
                                <form method="post" class="d-inline" onsubmit="return confirm('Stop recurring for this invoice?');">
                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                    <input type="hidden" name="action" value="stop_recurring">
                                    <input type="hidden" name="invoice_id" value="<?= $inv['id'] ?>">
                                    <button type="submit" class="btn btn-outline-danger" title="Stop Recurring">
                                        <i class="bi bi-stop-circle"></i>
                                    </button>
                                </form>
                            </div>
                            
                            <!-- Edit Recurring Modal -->
                            <div class="modal fade" id="editRecurringModal<?= $inv['id'] ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form method="post">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Edit Recurring Schedule</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                <input type="hidden" name="action" value="update_recurring">
                                                <input type="hidden" name="invoice_id" value="<?= $inv['id'] ?>">
                                                <div class="mb-3">
                                                    <label class="form-label">Invoice</label>
                                                    <input type="text" class="form-control" value="<?= htmlspecialchars($inv['invoice_number']) ?> - <?= htmlspecialchars($inv['customer_name'] ?? 'N/A') ?>" readonly>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Recurring Interval</label>
                                                    <select name="recurring_interval" class="form-select" required>
                                                        <?php foreach ($intervals as $key => $label): ?>
                                                        <option value="<?= $key ?>" <?= $inv['recurring_interval'] === $key ? 'selected' : '' ?>><?= $label ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Next Invoice Date</label>
                                                    <input type="date" name="next_recurring_date" class="form-control" value="<?= $inv['next_recurring_date'] ?>" required>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-primary">Update Schedule</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
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

<?php elseif ($subpage === 'quotes'): ?>

<?php if ($action === 'create' || $action === 'edit'): ?>
<?php 
$quote = ($action === 'edit' && $id) ? $accounting->getQuote($id) : null;
$defaultTax = $accounting->getDefaultTaxRate();
?>

<div class="card">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="bi bi-file-text"></i> <?= $action === 'edit' ? 'Edit Quote' : 'New Quote' ?></h5>
    </div>
    <div class="card-body">
        <form method="POST" id="quoteForm">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="<?= $action === 'edit' ? 'update_quote' : 'create_quote' ?>">
            <?php if ($quote): ?>
            <input type="hidden" name="quote_id" value="<?= $quote['id'] ?>">
            <?php endif; ?>
            
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label">Customer <span class="text-danger">*</span></label>
                    <ul class="nav nav-tabs nav-tabs-sm mb-2" id="quoteCustomerTabs">
                        <li class="nav-item">
                            <a class="nav-link active py-1 px-2" data-bs-toggle="tab" href="#quoteCrmCustomer">CRM Customers</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link py-1 px-2" data-bs-toggle="tab" href="#quoteBillingCustomer">Billing Customers</a>
                        </li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="quoteCrmCustomer">
                            <select name="customer_id" class="form-select" id="quoteCustomerSelect">
                                <option value="">Select Customer</option>
                                <?php foreach ($customers as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= ($quote['customer_id'] ?? '') == $c['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="tab-pane fade" id="quoteBillingCustomer">
                            <div class="input-group mb-2">
                                <input type="text" class="form-control" id="quoteBillingSearch" placeholder="Search by name, username or phone...">
                                <button type="button" class="btn btn-outline-primary" onclick="searchQuoteBillingCustomer()"><i class="bi bi-search"></i></button>
                            </div>
                            <div id="quoteBillingResults" class="small" style="max-height: 200px; overflow-y: auto;"></div>
                            <div id="quoteSelectedBilling" class="alert alert-success py-2 mt-2" style="display: none;">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong id="quoteBillingName"></strong> <span class="badge bg-secondary" id="quoteBillingUsername"></span><br>
                                        <small id="quoteBillingPhone"></small>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="clearQuoteBilling()"><i class="bi bi-x"></i></button>
                                </div>
                            </div>
                            <input type="hidden" name="billing_customer" id="quoteBillingData">
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Issue Date</label>
                    <input type="date" name="issue_date" class="form-control" value="<?= $quote['issue_date'] ?? date('Y-m-d') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Expiry Date</label>
                    <input type="date" name="expiry_date" class="form-control" value="<?= $quote['expiry_date'] ?? date('Y-m-d', strtotime('+30 days')) ?>">
                </div>
            </div>
            
            <h6 class="mb-3">Line Items</h6>
            <div class="table-responsive mb-3">
                <table class="table table-bordered" id="quoteItemsTable">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 35%">Description</th>
                            <th style="width: 10%">Qty</th>
                            <th style="width: 15%">Unit Price</th>
                            <th style="width: 15%">Tax Rate</th>
                            <th style="width: 15%">Total</th>
                            <th style="width: 10%"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($quote && !empty($quote['items'])): ?>
                        <?php foreach ($quote['items'] as $idx => $item): ?>
                        <tr class="quote-item-row">
                            <td><input type="text" name="items[<?= $idx ?>][description]" class="form-control item-desc" value="<?= htmlspecialchars($item['description']) ?>" required></td>
                            <td><input type="number" name="items[<?= $idx ?>][quantity]" class="form-control item-qty" step="0.01" min="0.01" value="<?= $item['quantity'] ?>" required></td>
                            <td><input type="number" name="items[<?= $idx ?>][unit_price]" class="form-control item-price" step="0.01" min="0" value="<?= $item['unit_price'] ?>" required></td>
                            <td>
                                <select name="items[<?= $idx ?>][tax_rate]" class="form-select item-tax">
                                    <option value="0" <?= $item['tax_rate'] == 0 ? 'selected' : '' ?>>No Tax</option>
                                    <?php foreach ($taxRates as $tr): ?>
                                    <option value="<?= $tr['rate'] ?>" <?= $item['tax_rate'] == $tr['rate'] ? 'selected' : '' ?>><?= htmlspecialchars($tr['name']) ?> (<?= $tr['rate'] ?>%)</option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td class="item-total text-end align-middle">KES <?= number_format($item['line_total'] + $item['tax_amount'], 2) ?></td>
                            <td><button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="bi bi-trash"></i></button></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <tr class="quote-item-row">
                            <td><input type="text" name="items[0][description]" class="form-control item-desc" required></td>
                            <td><input type="number" name="items[0][quantity]" class="form-control item-qty" step="0.01" min="0.01" value="1" required></td>
                            <td><input type="number" name="items[0][unit_price]" class="form-control item-price" step="0.01" min="0" value="0" required></td>
                            <td>
                                <select name="items[0][tax_rate]" class="form-select item-tax">
                                    <option value="0">No Tax</option>
                                    <?php foreach ($taxRates as $tr): ?>
                                    <option value="<?= $tr['rate'] ?>" <?= ($defaultTax && $defaultTax['id'] == $tr['id']) ? 'selected' : '' ?>><?= htmlspecialchars($tr['name']) ?> (<?= $tr['rate'] ?>%)</option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td class="item-total text-end align-middle">KES 0.00</td>
                            <td><button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="bi bi-trash"></i></button></td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <button type="button" class="btn btn-outline-secondary mb-4" id="addQuoteRow">
                <i class="bi bi-plus"></i> Add Line
            </button>
            
            <div class="row justify-content-end mb-4">
                <div class="col-md-4">
                    <table class="table table-sm">
                        <tr>
                            <td>Subtotal</td>
                            <td class="text-end" id="quoteSubtotal">KES 0.00</td>
                        </tr>
                        <tr>
                            <td>Tax</td>
                            <td class="text-end" id="quoteTax">KES 0.00</td>
                        </tr>
                        <tr class="fw-bold">
                            <td>Total</td>
                            <td class="text-end" id="quoteTotal">KES 0.00</td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($quote['notes'] ?? '') ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Terms & Conditions</label>
                    <textarea name="terms" class="form-control" rows="3"><?= htmlspecialchars($quote['terms'] ?? '') ?></textarea>
                </div>
            </div>
            
            <input type="hidden" name="subtotal" id="quoteSubtotalInput" value="0">
            <input type="hidden" name="tax_amount" id="quoteTaxInput" value="0">
            <input type="hidden" name="total_amount" id="quoteTotalInput" value="0">
            
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> <?= $action === 'edit' ? 'Update Quote' : 'Create Quote' ?></button>
                <a href="?page=accounting&subpage=quotes" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const table = document.getElementById('quoteItemsTable').getElementsByTagName('tbody')[0];
    let rowIndex = table.rows.length;
    
    function calculateRowTotal(row) {
        const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
        const price = parseFloat(row.querySelector('.item-price').value) || 0;
        const taxRate = parseFloat(row.querySelector('.item-tax').value) || 0;
        const lineTotal = qty * price;
        const tax = lineTotal * (taxRate / 100);
        row.querySelector('.item-total').textContent = 'KES ' + (lineTotal + tax).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        return { subtotal: lineTotal, tax: tax };
    }
    
    function calculateTotals() {
        let subtotal = 0, tax = 0;
        document.querySelectorAll('.quote-item-row').forEach(row => {
            const totals = calculateRowTotal(row);
            subtotal += totals.subtotal;
            tax += totals.tax;
        });
        document.getElementById('quoteSubtotal').textContent = 'KES ' + subtotal.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        document.getElementById('quoteTax').textContent = 'KES ' + tax.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        document.getElementById('quoteTotal').textContent = 'KES ' + (subtotal + tax).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        document.getElementById('quoteSubtotalInput').value = subtotal.toFixed(2);
        document.getElementById('quoteTaxInput').value = tax.toFixed(2);
        document.getElementById('quoteTotalInput').value = (subtotal + tax).toFixed(2);
    }
    
    document.getElementById('addQuoteRow').addEventListener('click', function() {
        const newRow = table.insertRow();
        newRow.className = 'quote-item-row';
        newRow.innerHTML = `
            <td><input type="text" name="items[${rowIndex}][description]" class="form-control item-desc" required></td>
            <td><input type="number" name="items[${rowIndex}][quantity]" class="form-control item-qty" step="0.01" min="0.01" value="1" required></td>
            <td><input type="number" name="items[${rowIndex}][unit_price]" class="form-control item-price" step="0.01" min="0" value="0" required></td>
            <td>
                <select name="items[${rowIndex}][tax_rate]" class="form-select item-tax">
                    <option value="0">No Tax</option>
                    <?php foreach ($taxRates as $tr): ?>
                    <option value="<?= $tr['rate'] ?>"><?= htmlspecialchars($tr['name']) ?> (<?= $tr['rate'] ?>%)</option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td class="item-total text-end align-middle">KES 0.00</td>
            <td><button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="bi bi-trash"></i></button></td>
        `;
        rowIndex++;
        attachRowEvents(newRow);
    });
    
    function attachRowEvents(row) {
        row.querySelector('.item-qty').addEventListener('input', calculateTotals);
        row.querySelector('.item-price').addEventListener('input', calculateTotals);
        row.querySelector('.item-tax').addEventListener('change', calculateTotals);
        row.querySelector('.remove-row').addEventListener('click', function() {
            if (document.querySelectorAll('.quote-item-row').length > 1) {
                row.remove();
                calculateTotals();
            }
        });
    }
    
    document.querySelectorAll('.quote-item-row').forEach(attachRowEvents);
    calculateTotals();
    
    // Billing customer search for quotes
    window.searchQuoteBillingCustomer = function() {
        const query = document.getElementById('quoteBillingSearch').value.trim();
        if (query.length < 2) {
            document.getElementById('quoteBillingResults').innerHTML = '<div class="text-muted">Enter at least 2 characters</div>';
            return;
        }
        document.getElementById('quoteBillingResults').innerHTML = '<div class="text-muted">Searching...</div>';
        fetch('/api/billing.php?action=search&q=' + encodeURIComponent(query))
            .then(r => r.json())
            .then(data => {
                if (data.error) {
                    document.getElementById('quoteBillingResults').innerHTML = '<div class="text-danger">' + data.error + '</div>';
                    return;
                }
                if (!data.customers || data.customers.length === 0) {
                    document.getElementById('quoteBillingResults').innerHTML = '<div class="alert alert-warning py-1">No customers found</div>';
                    return;
                }
                let html = '<div class="list-group">';
                data.customers.forEach(c => {
                    html += '<button type="button" class="list-group-item list-group-item-action py-1" onclick=\'selectQuoteBilling(' + JSON.stringify(c) + ')\'>' +
                        '<strong>' + (c.name || 'N/A') + '</strong>' +
                        (c.username ? ' <span class="badge bg-secondary">' + c.username + '</span>' : '') +
                        '<br><small class="text-muted">' + (c.phone || 'No phone') + '</small></button>';
                });
                html += '</div>';
                document.getElementById('quoteBillingResults').innerHTML = html;
            })
            .catch(err => {
                document.getElementById('quoteBillingResults').innerHTML = '<div class="text-danger">Error: ' + err.message + '</div>';
            });
    };
    
    window.selectQuoteBilling = function(customer) {
        document.getElementById('quoteBillingName').textContent = customer.name || 'N/A';
        document.getElementById('quoteBillingUsername').textContent = customer.username || '';
        document.getElementById('quoteBillingPhone').textContent = customer.phone || 'No phone';
        document.getElementById('quoteBillingData').value = JSON.stringify(customer);
        document.getElementById('quoteSelectedBilling').style.display = 'block';
        document.getElementById('quoteBillingResults').innerHTML = '';
        document.getElementById('quoteCustomerSelect').value = '';
    };
    
    window.clearQuoteBilling = function() {
        document.getElementById('quoteSelectedBilling').style.display = 'none';
        document.getElementById('quoteBillingData').value = '';
    };
    
    document.getElementById('quoteBillingSearch')?.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') { e.preventDefault(); searchQuoteBillingCustomer(); }
    });
});
</script>

<?php elseif ($action === 'view' && $id): ?>
<?php $quote = $accounting->getQuote($id); ?>
<?php if ($quote): ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <a href="?page=accounting&subpage=quotes" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
    <div class="d-flex gap-2">
        <form method="POST" class="d-inline">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="download_quote_pdf">
            <input type="hidden" name="quote_id" value="<?= $quote['id'] ?>">
            <button type="submit" class="btn btn-outline-danger"><i class="bi bi-file-pdf"></i> PDF</button>
        </form>
        <?php if ($quote['status'] !== 'converted'): ?>
        <a href="?page=accounting&subpage=quotes&action=edit&id=<?= $quote['id'] ?>" class="btn btn-outline-primary"><i class="bi bi-pencil"></i> Edit</a>
        <button type="button" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#sendQuoteEmailModal"><i class="bi bi-envelope"></i> Email</button>
        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#sendQuoteWhatsAppModal"><i class="bi bi-whatsapp"></i> WhatsApp</button>
        <form method="POST" class="d-inline" onsubmit="return confirm('Convert this quote to an invoice?');">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="convert_quote_to_invoice">
            <input type="hidden" name="quote_id" value="<?= $quote['id'] ?>">
            <button type="submit" class="btn btn-primary"><i class="bi bi-receipt"></i> Convert to Invoice</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <div class="row mb-4">
            <div class="col-md-6">
                <h4>Quote <?= htmlspecialchars($quote['quote_number']) ?></h4>
                <p class="text-muted mb-0">
                    Status: 
                    <?php
                    $statusColors = ['draft' => 'secondary', 'sent' => 'info', 'accepted' => 'success', 'declined' => 'danger', 'expired' => 'warning', 'converted' => 'primary'];
                    ?>
                    <span class="badge bg-<?= $statusColors[$quote['status']] ?? 'secondary' ?>"><?= ucfirst($quote['status']) ?></span>
                    <?php if ($quote['status'] === 'converted' && $quote['converted_to_invoice_id']): ?>
                    <a href="?page=accounting&subpage=invoices&action=view&id=<?= $quote['converted_to_invoice_id'] ?>">View Invoice</a>
                    <?php endif; ?>
                </p>
            </div>
            <div class="col-md-6 text-end">
                <p class="mb-1"><strong>Issue Date:</strong> <?= date('M d, Y', strtotime($quote['issue_date'])) ?></p>
                <p class="mb-0"><strong>Expiry Date:</strong> <?= date('M d, Y', strtotime($quote['expiry_date'])) ?></p>
            </div>
        </div>
        
        <div class="row mb-4">
            <div class="col-md-6">
                <h6 class="text-muted">Customer</h6>
                <p class="mb-0"><strong><?= htmlspecialchars($quote['customer_name']) ?></strong></p>
                <?php if ($quote['customer_email']): ?><p class="mb-0 text-muted"><?= htmlspecialchars($quote['customer_email']) ?></p><?php endif; ?>
                <?php if ($quote['customer_phone']): ?><p class="mb-0 text-muted"><?= htmlspecialchars($quote['customer_phone']) ?></p><?php endif; ?>
            </div>
        </div>
        
        <div class="table-responsive mb-4">
            <table class="table">
                <thead class="table-light">
                    <tr>
                        <th>Description</th>
                        <th class="text-end">Qty</th>
                        <th class="text-end">Unit Price</th>
                        <th class="text-end">Tax</th>
                        <th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($quote['items'] as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['description']) ?></td>
                        <td class="text-end"><?= number_format($item['quantity'], 2) ?></td>
                        <td class="text-end">KES <?= number_format($item['unit_price'], 2) ?></td>
                        <td class="text-end"><?= $item['tax_rate'] ?>%</td>
                        <td class="text-end">KES <?= number_format($item['line_total'] + $item['tax_amount'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" class="text-end"><strong>Subtotal</strong></td>
                        <td class="text-end">KES <?= number_format($quote['subtotal'], 2) ?></td>
                    </tr>
                    <tr>
                        <td colspan="4" class="text-end"><strong>Tax</strong></td>
                        <td class="text-end">KES <?= number_format($quote['tax_amount'], 2) ?></td>
                    </tr>
                    <tr class="table-dark">
                        <td colspan="4" class="text-end"><strong>Total</strong></td>
                        <td class="text-end"><strong>KES <?= number_format($quote['total_amount'], 2) ?></strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        <?php if ($quote['notes'] || $quote['terms']): ?>
        <div class="row">
            <?php if ($quote['notes']): ?>
            <div class="col-md-6">
                <h6 class="text-muted">Notes</h6>
                <p><?= nl2br(htmlspecialchars($quote['notes'])) ?></p>
            </div>
            <?php endif; ?>
            <?php if ($quote['terms']): ?>
            <div class="col-md-6">
                <h6 class="text-muted">Terms & Conditions</h6>
                <p><?= nl2br(htmlspecialchars($quote['terms'])) ?></p>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Send Quote Email Modal -->
<div class="modal fade" id="sendQuoteEmailModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title"><i class="bi bi-envelope"></i> Send Quote via Email</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="send_quote_email">
                    <input type="hidden" name="quote_id" value="<?= $quote['id'] ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Recipient Email *</label>
                        <input type="email" class="form-control" name="to_email" value="<?= htmlspecialchars($quote['customer_email'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Subject (optional)</label>
                        <input type="text" class="form-control" name="email_subject" placeholder="Leave blank for default subject">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Message (optional)</label>
                        <textarea class="form-control" name="email_message" rows="3" placeholder="Leave blank for default message"></textarea>
                    </div>
                    <div class="alert alert-info small mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        Quote #<?= htmlspecialchars($quote['quote_number']) ?> for KES <?= number_format($quote['total'] ?? 0, 2) ?> will be attached as PDF.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info"><i class="bi bi-send"></i> Send Email</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Send Quote WhatsApp Modal -->
<div class="modal fade" id="sendQuoteWhatsAppModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="bi bi-whatsapp"></i> Send Quote via WhatsApp</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="send_quote_whatsapp">
                    <input type="hidden" name="quote_id" value="<?= $quote['id'] ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Phone Number *</label>
                        <input type="text" class="form-control" name="phone" value="<?= htmlspecialchars($quote['customer_phone'] ?? '') ?>" required placeholder="e.g., 0712345678">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Caption (optional)</label>
                        <textarea class="form-control" name="caption" rows="2" placeholder="Leave blank for default caption"></textarea>
                    </div>
                    <div class="alert alert-success small mb-0">
                        <i class="bi bi-file-pdf me-1"></i>
                        Quote #<?= htmlspecialchars($quote['quote_number']) ?> will be sent as a PDF document.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="bi bi-whatsapp"></i> Send WhatsApp</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php else: ?>
<div class="alert alert-warning">Quote not found.</div>
<?php endif; ?>

<?php else: ?>

<?php $quotes = $accounting->getQuotes(); ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="mb-0">All Quotes</h5>
    <a href="?page=accounting&subpage=quotes&action=create" class="btn btn-primary"><i class="bi bi-plus"></i> New Quote</a>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Quote #</th>
                        <th>Customer</th>
                        <th>Issue Date</th>
                        <th>Expiry Date</th>
                        <th class="text-end">Amount</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($quotes)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No quotes yet. <a href="?page=accounting&subpage=quotes&action=create">Create your first quote</a></td></tr>
                    <?php else: ?>
                    <?php foreach ($quotes as $q): ?>
                    <?php
                    $statusColors = ['draft' => 'secondary', 'sent' => 'info', 'accepted' => 'success', 'declined' => 'danger', 'expired' => 'warning', 'converted' => 'primary'];
                    ?>
                    <tr>
                        <td><a href="?page=accounting&subpage=quotes&action=view&id=<?= $q['id'] ?>"><?= htmlspecialchars($q['quote_number']) ?></a></td>
                        <td><?= htmlspecialchars($q['customer_name'] ?? 'N/A') ?></td>
                        <td><?= date('M d, Y', strtotime($q['issue_date'])) ?></td>
                        <td><?= date('M d, Y', strtotime($q['expiry_date'])) ?></td>
                        <td class="text-end">KES <?= number_format($q['total_amount'], 2) ?></td>
                        <td><span class="badge bg-<?= $statusColors[$q['status']] ?? 'secondary' ?>"><?= ucfirst($q['status']) ?></span></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="?page=accounting&subpage=quotes&action=view&id=<?= $q['id'] ?>" class="btn btn-outline-primary" title="View"><i class="bi bi-eye"></i></a>
                                <?php if ($q['status'] !== 'converted'): ?>
                                <a href="?page=accounting&subpage=quotes&action=edit&id=<?= $q['id'] ?>" class="btn btn-outline-secondary" title="Edit"><i class="bi bi-pencil"></i></a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php endif; ?>

<?php elseif ($subpage === 'bills'): ?>

<?php if ($action === 'create' || $action === 'edit'): ?>
<?php 
$bill = ($action === 'edit' && $id) ? $accounting->getBill($id) : null;
$defaultTax = $accounting->getDefaultTaxRate();
?>

<div class="card">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="bi bi-file-earmark-text"></i> <?= $action === 'edit' ? 'Edit Bill' : 'New Bill' ?></h5>
    </div>
    <div class="card-body">
        <form method="POST" id="billForm">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="<?= $action === 'edit' ? 'update_bill' : 'create_bill' ?>">
            <?php if ($bill): ?>
            <input type="hidden" name="bill_id" value="<?= $bill['id'] ?>">
            <?php endif; ?>
            
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label class="form-label">Vendor <span class="text-danger">*</span></label>
                    <select name="vendor_id" class="form-select" required>
                        <option value="">Select Vendor</option>
                        <?php foreach ($vendors as $v): ?>
                        <option value="<?= $v['id'] ?>" <?= ($bill['vendor_id'] ?? '') == $v['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($v['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Bill Date</label>
                    <input type="date" name="bill_date" class="form-control" value="<?= $bill['bill_date'] ?? date('Y-m-d') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Due Date</label>
                    <input type="date" name="due_date" class="form-control" value="<?= $bill['due_date'] ?? date('Y-m-d', strtotime('+30 days')) ?>">
                </div>
            </div>
            
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label">Reference Number</label>
                    <input type="text" name="reference" class="form-control" value="<?= htmlspecialchars($bill['reference'] ?? '') ?>" placeholder="Vendor invoice/reference number">
                </div>
            </div>
            
            <h6 class="mb-3">Line Items</h6>
            <div class="table-responsive mb-3">
                <table class="table table-bordered" id="billItemsTable">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 40%">Description</th>
                            <th style="width: 10%">Qty</th>
                            <th style="width: 15%">Unit Price</th>
                            <th style="width: 15%">Tax Rate</th>
                            <th style="width: 15%">Total</th>
                            <th style="width: 5%"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($bill && !empty($bill['items'])): ?>
                        <?php foreach ($bill['items'] as $idx => $item): ?>
                        <tr class="bill-item-row">
                            <td><input type="text" name="items[<?= $idx ?>][description]" class="form-control item-desc" value="<?= htmlspecialchars($item['description']) ?>" required></td>
                            <td><input type="number" name="items[<?= $idx ?>][quantity]" class="form-control item-qty" step="0.01" min="0.01" value="<?= $item['quantity'] ?>" required></td>
                            <td><input type="number" name="items[<?= $idx ?>][unit_price]" class="form-control item-price" step="0.01" min="0" value="<?= $item['unit_price'] ?>" required></td>
                            <td>
                                <select name="items[<?= $idx ?>][tax_rate]" class="form-select item-tax">
                                    <option value="0" <?= $item['tax_rate'] == 0 ? 'selected' : '' ?>>No Tax</option>
                                    <?php foreach ($taxRates as $tr): ?>
                                    <option value="<?= $tr['rate'] ?>" <?= $item['tax_rate'] == $tr['rate'] ? 'selected' : '' ?>><?= htmlspecialchars($tr['name']) ?> (<?= $tr['rate'] ?>%)</option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td class="item-total text-end align-middle">KES <?= number_format($item['line_total'] + $item['tax_amount'], 2) ?></td>
                            <td><button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="bi bi-trash"></i></button></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <tr class="bill-item-row">
                            <td><input type="text" name="items[0][description]" class="form-control item-desc" required></td>
                            <td><input type="number" name="items[0][quantity]" class="form-control item-qty" step="0.01" min="0.01" value="1" required></td>
                            <td><input type="number" name="items[0][unit_price]" class="form-control item-price" step="0.01" min="0" value="0" required></td>
                            <td>
                                <select name="items[0][tax_rate]" class="form-select item-tax">
                                    <option value="0">No Tax</option>
                                    <?php foreach ($taxRates as $tr): ?>
                                    <option value="<?= $tr['rate'] ?>"><?= htmlspecialchars($tr['name']) ?> (<?= $tr['rate'] ?>%)</option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td class="item-total text-end align-middle">KES 0.00</td>
                            <td><button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="bi bi-trash"></i></button></td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <button type="button" class="btn btn-outline-secondary mb-4" id="addBillRow">
                <i class="bi bi-plus"></i> Add Line
            </button>
            
            <div class="row justify-content-end mb-4">
                <div class="col-md-4">
                    <table class="table table-sm">
                        <tr><td>Subtotal</td><td class="text-end" id="billSubtotal">KES 0.00</td></tr>
                        <tr><td>Tax</td><td class="text-end" id="billTax">KES 0.00</td></tr>
                        <tr class="fw-bold"><td>Total</td><td class="text-end" id="billTotal">KES 0.00</td></tr>
                    </table>
                </div>
            </div>
            
            <div class="mb-4">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($bill['notes'] ?? '') ?></textarea>
            </div>
            
            <input type="hidden" name="subtotal" id="billSubtotalInput" value="0">
            <input type="hidden" name="tax_amount" id="billTaxInput" value="0">
            <input type="hidden" name="total_amount" id="billTotalInput" value="0">
            
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> <?= $action === 'edit' ? 'Update Bill' : 'Create Bill' ?></button>
                <a href="?page=accounting&subpage=bills" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const table = document.getElementById('billItemsTable').getElementsByTagName('tbody')[0];
    let rowIndex = table.rows.length;
    
    function calculateRowTotal(row) {
        const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
        const price = parseFloat(row.querySelector('.item-price').value) || 0;
        const taxRate = parseFloat(row.querySelector('.item-tax').value) || 0;
        const lineTotal = qty * price;
        const tax = lineTotal * (taxRate / 100);
        row.querySelector('.item-total').textContent = 'KES ' + (lineTotal + tax).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        return { subtotal: lineTotal, tax: tax };
    }
    
    function calculateTotals() {
        let subtotal = 0, tax = 0;
        document.querySelectorAll('.bill-item-row').forEach(row => {
            const totals = calculateRowTotal(row);
            subtotal += totals.subtotal;
            tax += totals.tax;
        });
        document.getElementById('billSubtotal').textContent = 'KES ' + subtotal.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        document.getElementById('billTax').textContent = 'KES ' + tax.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        document.getElementById('billTotal').textContent = 'KES ' + (subtotal + tax).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        document.getElementById('billSubtotalInput').value = subtotal.toFixed(2);
        document.getElementById('billTaxInput').value = tax.toFixed(2);
        document.getElementById('billTotalInput').value = (subtotal + tax).toFixed(2);
    }
    
    document.getElementById('addBillRow').addEventListener('click', function() {
        const newRow = table.insertRow();
        newRow.className = 'bill-item-row';
        newRow.innerHTML = `
            <td><input type="text" name="items[${rowIndex}][description]" class="form-control item-desc" required></td>
            <td><input type="number" name="items[${rowIndex}][quantity]" class="form-control item-qty" step="0.01" min="0.01" value="1" required></td>
            <td><input type="number" name="items[${rowIndex}][unit_price]" class="form-control item-price" step="0.01" min="0" value="0" required></td>
            <td>
                <select name="items[${rowIndex}][tax_rate]" class="form-select item-tax">
                    <option value="0">No Tax</option>
                    <?php foreach ($taxRates as $tr): ?>
                    <option value="<?= $tr['rate'] ?>"><?= htmlspecialchars($tr['name']) ?> (<?= $tr['rate'] ?>%)</option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td class="item-total text-end align-middle">KES 0.00</td>
            <td><button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="bi bi-trash"></i></button></td>
        `;
        rowIndex++;
        attachRowEvents(newRow);
    });
    
    function attachRowEvents(row) {
        row.querySelector('.item-qty').addEventListener('input', calculateTotals);
        row.querySelector('.item-price').addEventListener('input', calculateTotals);
        row.querySelector('.item-tax').addEventListener('change', calculateTotals);
        row.querySelector('.remove-row').addEventListener('click', function() {
            if (document.querySelectorAll('.bill-item-row').length > 1) {
                row.remove();
                calculateTotals();
            }
        });
    }
    
    document.querySelectorAll('.bill-item-row').forEach(attachRowEvents);
    calculateTotals();
});
</script>

<?php else: ?>

<?php $bills = $accounting->getBills(); ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="mb-0">Vendor Bills</h5>
    <a href="?page=accounting&subpage=bills&action=create" class="btn btn-primary"><i class="bi bi-plus"></i> New Bill</a>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Bill #</th>
                        <th>Vendor</th>
                        <th>Bill Date</th>
                        <th>Due Date</th>
                        <th class="text-end">Amount</th>
                        <th class="text-end">Balance</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bills)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No bills yet. <a href="?page=accounting&subpage=bills&action=create">Record your first bill</a></td></tr>
                    <?php else: ?>
                    <?php foreach ($bills as $b): ?>
                    <?php
                    $statusColors = ['unpaid' => 'warning', 'partial' => 'info', 'paid' => 'success', 'overdue' => 'danger'];
                    ?>
                    <tr>
                        <td><a href="?page=accounting&subpage=bills&action=view&id=<?= $b['id'] ?>"><?= htmlspecialchars($b['bill_number']) ?></a></td>
                        <td><?= htmlspecialchars($b['vendor_name'] ?? 'N/A') ?></td>
                        <td><?= date('M d, Y', strtotime($b['bill_date'])) ?></td>
                        <td><?= date('M d, Y', strtotime($b['due_date'])) ?></td>
                        <td class="text-end">KES <?= number_format($b['total_amount'], 2) ?></td>
                        <td class="text-end">KES <?= number_format($b['balance_due'], 2) ?></td>
                        <td><span class="badge bg-<?= $statusColors[$b['status']] ?? 'secondary' ?>"><?= ucfirst($b['status']) ?></span></td>
                        <td>
                            <a href="?page=accounting&subpage=bills&action=view&id=<?= $b['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php endif; ?>

<?php elseif ($subpage === 'reports'): ?>

<?php
$reportTab = $_GET['tab'] ?? 'profit_loss';
$fromDate = $_GET['from_date'] ?? date('Y-m-01');
$toDate = $_GET['to_date'] ?? date('Y-m-d');
?>

<ul class="nav nav-pills mb-4">
    <li class="nav-item">
        <a class="nav-link <?= $reportTab === 'profit_loss' ? 'active' : '' ?>" href="?page=accounting&subpage=reports&tab=profit_loss">Profit & Loss</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $reportTab === 'ar_aging' ? 'active' : '' ?>" href="?page=accounting&subpage=reports&tab=ar_aging">AR Aging</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $reportTab === 'ap_aging' ? 'active' : '' ?>" href="?page=accounting&subpage=reports&tab=ap_aging">AP Aging</a>
    </li>
</ul>

<?php if ($reportTab === 'profit_loss'): ?>
<?php $plReport = $accounting->getProfitLossReport($fromDate, $toDate); ?>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <input type="hidden" name="page" value="accounting">
            <input type="hidden" name="subpage" value="reports">
            <input type="hidden" name="tab" value="profit_loss">
            <div class="col-md-3">
                <label class="form-label">From Date</label>
                <input type="date" class="form-control" name="from_date" value="<?= $fromDate ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">To Date</label>
                <input type="date" class="form-control" name="to_date" value="<?= $toDate ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-filter"></i> Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header bg-white">
        <h5 class="mb-0">Profit & Loss Statement</h5>
        <small class="text-muted"><?= date('M j, Y', strtotime($fromDate)) ?> - <?= date('M j, Y', strtotime($toDate)) ?></small>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h6 class="text-success">Revenue</h6>
                <table class="table table-sm">
                    <tr>
                        <td>Invoice Payments Received</td>
                        <td class="text-end">KES <?= number_format($plReport['total_revenue'], 2) ?></td>
                    </tr>
                    <tr class="table-success">
                        <td><strong>Total Revenue</strong></td>
                        <td class="text-end"><strong>KES <?= number_format($plReport['total_revenue'], 2) ?></strong></td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6 class="text-danger">Expenses</h6>
                <table class="table table-sm">
                    <?php foreach ($plReport['expenses'] as $exp): ?>
                    <tr>
                        <td><?= htmlspecialchars($exp['category'] ?? 'Uncategorized') ?></td>
                        <td class="text-end">KES <?= number_format($exp['total'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="table-danger">
                        <td><strong>Total Expenses</strong></td>
                        <td class="text-end"><strong>KES <?= number_format($plReport['total_expenses'], 2) ?></strong></td>
                    </tr>
                </table>
            </div>
        </div>
        <hr>
        <div class="row">
            <div class="col-md-12">
                <table class="table table-sm">
                    <tr class="<?= $plReport['net_profit'] >= 0 ? 'table-success' : 'table-danger' ?>">
                        <td><strong>Net <?= $plReport['net_profit'] >= 0 ? 'Profit' : 'Loss' ?></strong></td>
                        <td class="text-end"><strong>KES <?= number_format(abs($plReport['net_profit']), 2) ?></strong></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<?php elseif ($reportTab === 'ar_aging'): ?>
<?php $arAging = $accounting->getAgingReport('receivable'); ?>

<div class="card">
    <div class="card-header bg-white">
        <h5 class="mb-0">Accounts Receivable Aging</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Invoice #</th>
                        <th>Customer</th>
                        <th>Due Date</th>
                        <th class="text-end">Balance</th>
                        <th>Aging</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($arAging)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">No outstanding receivables</td></tr>
                    <?php else: ?>
                    <?php foreach ($arAging as $ar): ?>
                    <tr>
                        <td><a href="?page=accounting&subpage=invoices&action=view&id=<?= $ar['id'] ?>"><?= htmlspecialchars($ar['invoice_number']) ?></a></td>
                        <td><?= htmlspecialchars($ar['customer_name'] ?? 'N/A') ?></td>
                        <td><?= date('M j, Y', strtotime($ar['due_date'])) ?></td>
                        <td class="text-end">KES <?= number_format($ar['balance_due'], 2) ?></td>
                        <td>
                            <?php
                            $agingClass = match($ar['aging_bucket']) {
                                'current' => 'success',
                                '1-30' => 'warning',
                                '31-60' => 'orange',
                                default => 'danger'
                            };
                            ?>
                            <span class="badge bg-<?= $agingClass ?>"><?= $ar['aging_bucket'] ?> days</span>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php elseif ($reportTab === 'ap_aging'): ?>
<?php $apAging = $accounting->getAgingReport('payable'); ?>

<div class="card">
    <div class="card-header bg-white">
        <h5 class="mb-0">Accounts Payable Aging</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Bill #</th>
                        <th>Vendor</th>
                        <th>Due Date</th>
                        <th class="text-end">Balance</th>
                        <th>Aging</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($apAging)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">No outstanding payables</td></tr>
                    <?php else: ?>
                    <?php foreach ($apAging as $ap): ?>
                    <tr>
                        <td><?= htmlspecialchars($ap['bill_number']) ?></td>
                        <td><?= htmlspecialchars($ap['vendor_name'] ?? 'N/A') ?></td>
                        <td><?= date('M j, Y', strtotime($ap['due_date'])) ?></td>
                        <td class="text-end">KES <?= number_format($ap['balance_due'], 2) ?></td>
                        <td>
                            <?php
                            $agingClass = match($ap['aging_bucket']) {
                                'current' => 'success',
                                '1-30' => 'warning',
                                default => 'danger'
                            };
                            ?>
                            <span class="badge bg-<?= $agingClass ?>"><?= $ap['aging_bucket'] ?> days</span>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php endif; ?>

<?php endif; ?>
