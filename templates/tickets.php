<?php
$ticketData = null;
$comments = [];
if (($action === 'edit' || $action === 'view') && $id) {
    $ticketData = $ticket->find($id);
    $comments = $ticket->getComments($id);
}

$preselectedCustomer = null;
if (isset($_GET['customer_id'])) {
    $preselectedCustomer = $customer->find((int)$_GET['customer_id']);
}
?>

<?php if ($action === 'create' || $action === 'edit'): ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-ticket-<?= $action === 'create' ? 'perforated' : 'detailed' ?>"></i> <?= $action === 'create' ? 'Create Ticket' : 'Edit Ticket' ?></h2>
    <a href="?page=tickets" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="<?= $action === 'create' ? 'create_ticket' : 'update_ticket' ?>">
            <?php if ($action === 'edit'): ?>
            <input type="hidden" name="id" value="<?= $ticketData['id'] ?>">
            <?php endif; ?>
            
            <div class="row g-3">
                <?php if ($action === 'create'): ?>
                <?php $allCustomers = $customer->getAll(); ?>
                <div class="col-12">
                    <label class="form-label">Customer Type *</label>
                    <div class="btn-group w-100" role="group">
                        <input type="radio" class="btn-check" name="customer_type" id="existingCustomer" value="existing" checked>
                        <label class="btn btn-outline-primary" for="existingCustomer">
                            <i class="bi bi-person-check"></i> Existing Customer
                        </label>
                        <input type="radio" class="btn-check" name="customer_type" id="billingCustomer" value="billing">
                        <label class="btn btn-outline-info" for="billingCustomer">
                            <i class="bi bi-cloud-arrow-down"></i> From Billing
                        </label>
                        <input type="radio" class="btn-check" name="customer_type" id="newCustomer" value="new">
                        <label class="btn btn-outline-success" for="newCustomer">
                            <i class="bi bi-person-plus"></i> New Customer
                        </label>
                    </div>
                </div>
                
                <div class="col-md-6" id="existingCustomerSection">
                    <label class="form-label">Select Customer *</label>
                    <select class="form-select" name="customer_id" id="customerIdSelect">
                        <option value="">Select Customer</option>
                        <?php foreach ($allCustomers as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ($preselectedCustomer && $preselectedCustomer['id'] == $c['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['account_number']) ?><?= !empty($c['username']) ? ' - ' . $c['username'] : '' ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div id="billingCustomerSection" class="col-12" style="display: none;">
                    <div class="card bg-light mb-3">
                        <div class="card-header bg-info text-white">
                            <i class="bi bi-cloud-arrow-down"></i> Search Billing System
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label class="form-label">Search by Name, Username, or Phone</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="billingSearchInput" placeholder="Enter at least 2 characters...">
                                        <button type="button" class="btn btn-info" id="billingSearchBtn">
                                            <i class="bi bi-search"></i> Search
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">&nbsp;</label>
                                    <div id="billingSearchStatus" class="text-muted"></div>
                                </div>
                                <div class="col-12">
                                    <div id="billingSearchResults" style="max-height: 300px; overflow-y: auto;"></div>
                                </div>
                                <div class="col-12" id="selectedBillingCustomer" style="display: none;">
                                    <div class="alert alert-info mb-0">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <strong id="selectedBillingName"></strong>
                                                <span class="badge bg-secondary ms-2" id="selectedBillingUsername"></span>
                                                <br><small id="selectedBillingPhone"></small>
                                                <br><small id="selectedBillingAddress"></small>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="clearBillingSelection()">
                                                <i class="bi bi-x"></i> Clear
                                            </button>
                                        </div>
                                        <input type="hidden" name="billing_customer" id="billingCustomerData">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div id="newCustomerSection" style="display: none;">
                    <div class="card bg-light mb-3">
                        <div class="card-header bg-success text-white">
                            <i class="bi bi-person-plus"></i> New Customer Details
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" name="new_customer_name" id="newCustomerName" placeholder="Enter customer name">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Phone Number *</label>
                                    <input type="tel" class="form-control" name="new_customer_phone" id="newCustomerPhone" placeholder="+254712345678">
                                    <small class="text-muted">Include country code for SMS</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="new_customer_email" placeholder="customer@example.com">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Service Plan *</label>
                                    <?php $servicePlans = $customer->getServicePlans(); ?>
                                    <select class="form-select" name="new_customer_service_plan" id="newCustomerPlan">
                                        <?php foreach ($servicePlans as $key => $label): ?>
                                        <option value="<?= $key ?>"><?= $label ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Installation Address *</label>
                                    <textarea class="form-control" name="new_customer_address" id="newCustomerAddress" rows="2" placeholder="Enter installation address"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="col-md-6">
                    <label class="form-label">Branch *</label>
                    <?php $branchClass = new \App\Branch(); $allBranches = $branchClass->getActive(); ?>
                    <select class="form-select" name="branch_id" required>
                        <option value="">Select Branch</option>
                        <?php foreach ($allBranches as $b): ?>
                        <option value="<?= $b['id'] ?>" <?= ($ticketData['branch_id'] ?? '') == $b['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($b['name']) ?> <?= $b['code'] ? '(' . htmlspecialchars($b['code']) . ')' : '' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Required - Which branch will handle this ticket</small>
                </div>
                
                <?php if ($action === 'create'): ?>
                <div class="col-12" id="assignmentSuggestion" style="display: none;">
                    <div class="alert alert-info mb-0">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <i class="bi bi-lightbulb"></i> <strong>Suggested Assignment:</strong>
                                <span id="suggestionText"></span>
                                <br><small class="text-muted" id="suggestionReason"></small>
                            </div>
                            <button type="button" class="btn btn-sm btn-info" onclick="applySuggestedAssignment()">
                                <i class="bi bi-check2"></i> Apply
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="col-12" id="customerHistorySection" style="display: none;">
                    <div class="card bg-light">
                        <div class="card-header py-2">
                            <i class="bi bi-clock-history"></i> Recent Tickets for this Customer
                        </div>
                        <div class="card-body p-2" id="customerHistoryList"></div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="col-md-6">
                    <label class="form-label">Assign To Team</label>
                    <?php $teams = $ticket->getAllTeams(); ?>
                    <select class="form-select" name="team_id" id="teamSelect">
                        <option value="">No Team</option>
                        <?php foreach ($teams as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= ($ticketData['team_id'] ?? '') == $t['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">All team members will be notified</small>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Assign To Individual</label>
                    <select class="form-select" name="assigned_to" id="assignedToSelect">
                        <option value="">Unassigned</option>
                        <?php foreach ($users as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= ($ticketData['assigned_to'] ?? '') == $u['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['name']) ?> (<?= ucfirst($u['role']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Individual will receive SMS notification</small>
                </div>
                
                <div class="col-12">
                    <label class="form-label">Subject *</label>
                    <input type="text" class="form-control" name="subject" value="<?= htmlspecialchars($ticketData['subject'] ?? '') ?>" required>
                </div>
                
                <div class="col-12">
                    <label class="form-label">Description *</label>
                    <textarea class="form-control" name="description" rows="4" required><?= htmlspecialchars($ticketData['description'] ?? '') ?></textarea>
                </div>
                
                <div class="col-md-4">
                    <label class="form-label">Category *</label>
                    <select class="form-select" name="category" required>
                        <?php foreach ($categories as $key => $label): ?>
                        <option value="<?= $key ?>" <?= ($ticketData['category'] ?? '') === $key ? 'selected' : '' ?>>
                            <?= $label ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-4">
                    <label class="form-label">Priority *</label>
                    <select class="form-select" name="priority" required>
                        <?php foreach ($priorities as $key => $label): ?>
                        <option value="<?= $key ?>" <?= ($ticketData['priority'] ?? 'medium') === $key ? 'selected' : '' ?>>
                            <?= $label ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <?php if ($action === 'edit'): ?>
                <div class="col-md-4">
                    <label class="form-label">Status *</label>
                    <select class="form-select" name="status" required>
                        <?php foreach ($ticketStatuses as $key => $label): ?>
                        <option value="<?= $key ?>" <?= ($ticketData['status'] ?? 'open') === $key ? 'selected' : '' ?>>
                            <?= $label ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Customer will receive SMS on status change</small>
                </div>
                <?php endif; ?>
            </div>
            
            <?php
            $serviceFeeModel = new \App\ServiceFee($db);
            $availableFees = $serviceFeeModel->getFeeTypes(true);
            $existingTicketFees = ($action === 'edit' && $ticketData) ? $serviceFeeModel->getTicketFees($ticketData['id']) : [];
            $existingFeeTypeIds = array_column($existingTicketFees, 'fee_type_id');
            $existingFeeAmounts = [];
            foreach ($existingTicketFees as $etf) {
                $existingFeeAmounts[$etf['fee_type_id']] = $etf['amount'];
            }
            ?>
            <?php if (!empty($availableFees)): ?>
            <div class="row g-3 mt-3">
                <div class="col-12">
                    <div class="card bg-light">
                        <div class="card-header bg-white">
                            <h6 class="mb-0"><i class="bi bi-cash-coin"></i> Service Fees (Optional)</h6>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small mb-3">Select one or more fees to add to this ticket. You can adjust amounts after selection.</p>
                            <div class="row">
                                <?php foreach ($availableFees as $fee): 
                                    $existingAmount = $existingFeeAmounts[$fee['id']] ?? $fee['default_amount'];
                                ?>
                                <div class="col-md-4 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input service-fee-checkbox" type="checkbox" 
                                               name="service_fees[]" 
                                               value="<?= $fee['id'] ?>" 
                                               id="fee_<?= $fee['id'] ?>"
                                               data-amount="<?= $existingAmount ?>"
                                               data-default-amount="<?= $fee['default_amount'] ?>"
                                               data-name="<?= htmlspecialchars($fee['name']) ?>"
                                               <?= in_array($fee['id'], $existingFeeTypeIds) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="fee_<?= $fee['id'] ?>">
                                            <?= htmlspecialchars($fee['name']) ?>
                                            <span class="badge bg-secondary"><?= htmlspecialchars($fee['currency'] ?? 'KES') ?> <?= number_format($fee['default_amount'], 0) ?></span>
                                        </label>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div id="selectedFeesAmounts" class="mt-3" style="display: none;">
                                <hr>
                                <h6>Adjust Amounts:</h6>
                                <div id="feeAmountInputs"></div>
                                <div class="mt-2">
                                    <strong>Total: <span id="feesTotal">0</span></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="mt-4">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg"></i> <?= $action === 'create' ? 'Create Ticket' : 'Update Ticket' ?>
                </button>
                <a href="?page=tickets" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php if ($action === 'create'): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const existingRadio = document.getElementById('existingCustomer');
    const billingRadio = document.getElementById('billingCustomer');
    const newRadio = document.getElementById('newCustomer');
    const existingSection = document.getElementById('existingCustomerSection');
    const billingSection = document.getElementById('billingCustomerSection');
    const newSection = document.getElementById('newCustomerSection');
    const customerIdSelect = document.getElementById('customerIdSelect');
    const newCustomerName = document.getElementById('newCustomerName');
    const newCustomerPhone = document.getElementById('newCustomerPhone');
    const newCustomerPlan = document.getElementById('newCustomerPlan');
    const newCustomerAddress = document.getElementById('newCustomerAddress');
    
    function toggleCustomerSections() {
        existingSection.style.display = 'none';
        billingSection.style.display = 'none';
        newSection.style.display = 'none';
        customerIdSelect.removeAttribute('required');
        newCustomerName.removeAttribute('required');
        newCustomerPhone.removeAttribute('required');
        newCustomerPlan.removeAttribute('required');
        newCustomerAddress.removeAttribute('required');
        
        if (existingRadio.checked) {
            existingSection.style.display = 'block';
            customerIdSelect.setAttribute('required', 'required');
        } else if (billingRadio.checked) {
            billingSection.style.display = 'block';
        } else if (newRadio.checked) {
            newSection.style.display = 'block';
            newCustomerName.setAttribute('required', 'required');
            newCustomerPhone.setAttribute('required', 'required');
            newCustomerPlan.setAttribute('required', 'required');
            newCustomerAddress.setAttribute('required', 'required');
        }
    }
    
    existingRadio.addEventListener('change', toggleCustomerSections);
    billingRadio.addEventListener('change', toggleCustomerSections);
    newRadio.addEventListener('change', toggleCustomerSections);
    
    toggleCustomerSections();
    
    const billingSearchInput = document.getElementById('billingSearchInput');
    const billingSearchBtn = document.getElementById('billingSearchBtn');
    const billingSearchResults = document.getElementById('billingSearchResults');
    const billingSearchStatus = document.getElementById('billingSearchStatus');
    const selectedBillingCustomer = document.getElementById('selectedBillingCustomer');
    const billingCustomerData = document.getElementById('billingCustomerData');
    
    function searchBillingCustomers() {
        const query = billingSearchInput.value.trim();
        if (query.length < 2) {
            billingSearchStatus.innerHTML = '<span class="text-warning">Enter at least 2 characters</span>';
            return;
        }
        
        billingSearchStatus.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Searching...';
        billingSearchResults.innerHTML = '';
        
        fetch('/api/billing.php?action=search&q=' + encodeURIComponent(query))
            .then(r => r.json())
            .then(data => {
                if (data.error) {
                    billingSearchStatus.innerHTML = '<span class="text-danger">' + data.error + '</span>';
                    return;
                }
                
                billingSearchStatus.innerHTML = '<span class="text-success">Found ' + (data.total || 0) + ' customers</span>';
                
                if (!data.customers || data.customers.length === 0) {
                    billingSearchResults.innerHTML = '<div class="alert alert-warning">No customers found</div>';
                    return;
                }
                
                let html = '<div class="list-group">';
                data.customers.forEach(c => {
                    html += '<button type="button" class="list-group-item list-group-item-action" onclick=\'selectBillingCustomer(' + JSON.stringify(c) + ')\'>' +
                        '<div class="d-flex justify-content-between">' +
                        '<div><strong>' + (c.name || 'N/A') + '</strong>' +
                        (c.username ? ' <span class="badge bg-secondary">' + c.username + '</span>' : '') +
                        '<br><small class="text-muted">' + (c.phone || 'No phone') + ' | ' + (c.service_plan || 'N/A') + '</small></div>' +
                        '<small class="text-muted">' + (c.connection_status || '') + '</small>' +
                        '</div></button>';
                });
                html += '</div>';
                billingSearchResults.innerHTML = html;
            })
            .catch(err => {
                billingSearchStatus.innerHTML = '<span class="text-danger">Error: ' + err.message + '</span>';
            });
    }
    
    billingSearchBtn.addEventListener('click', searchBillingCustomers);
    billingSearchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            searchBillingCustomers();
        }
    });
    
    window.selectBillingCustomer = function(customer) {
        document.getElementById('selectedBillingName').textContent = customer.name || 'N/A';
        document.getElementById('selectedBillingUsername').textContent = customer.username || '';
        document.getElementById('selectedBillingPhone').textContent = customer.phone || 'No phone';
        document.getElementById('selectedBillingAddress').textContent = customer.address || 'No address';
        billingCustomerData.value = JSON.stringify(customer);
        selectedBillingCustomer.style.display = 'block';
        billingSearchResults.innerHTML = '';
    };
    
    window.clearBillingSelection = function() {
        selectedBillingCustomer.style.display = 'none';
        billingCustomerData.value = '';
    };
    
    let suggestedAssignment = null;
    
    function fetchCustomerHistory(customerId) {
        if (!customerId) {
            document.getElementById('assignmentSuggestion').style.display = 'none';
            document.getElementById('customerHistorySection').style.display = 'none';
            return;
        }
        
        fetch('/api/customer-history.php?customer_id=' + customerId)
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    if (data.suggested_assignment && (data.suggested_assignment.assigned_to || data.suggested_assignment.team_id)) {
                        suggestedAssignment = data.suggested_assignment;
                        let suggestionText = '';
                        if (suggestedAssignment.technician_name) {
                            suggestionText = suggestedAssignment.technician_name;
                            if (suggestedAssignment.team_name) {
                                suggestionText += ' (Team: ' + suggestedAssignment.team_name + ')';
                            }
                        } else if (suggestedAssignment.team_name) {
                            suggestionText = 'Team: ' + suggestedAssignment.team_name;
                        }
                        document.getElementById('suggestionText').textContent = suggestionText;
                        document.getElementById('suggestionReason').textContent = 
                            'Previously handled ' + suggestedAssignment.ticket_count + ' ticket(s) for this customer successfully';
                        document.getElementById('assignmentSuggestion').style.display = 'block';
                    } else {
                        document.getElementById('assignmentSuggestion').style.display = 'none';
                    }
                    
                    if (data.recent_tickets && data.recent_tickets.length > 0) {
                        let html = '<div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead><tr>' +
                            '<th>Ticket</th><th>Subject</th><th>Status</th><th>Handled By</th><th>Date</th></tr></thead><tbody>';
                        data.recent_tickets.forEach(t => {
                            const handler = t.technician_name || t.team_name || '-';
                            const statusClass = t.status === 'resolved' || t.status === 'closed' ? 'success' : 
                                               (t.status === 'in_progress' ? 'info' : 'secondary');
                            html += '<tr>' +
                                '<td><a href="?page=tickets&action=view&id=' + t.id + '">' + t.ticket_number + '</a></td>' +
                                '<td>' + (t.subject || '').substring(0, 30) + '</td>' +
                                '<td><span class="badge bg-' + statusClass + '">' + t.status + '</span></td>' +
                                '<td>' + handler + '</td>' +
                                '<td>' + new Date(t.created_at).toLocaleDateString() + '</td>' +
                                '</tr>';
                        });
                        html += '</tbody></table></div>';
                        document.getElementById('customerHistoryList').innerHTML = html;
                        document.getElementById('customerHistorySection').style.display = 'block';
                    } else {
                        document.getElementById('customerHistorySection').style.display = 'none';
                    }
                }
            })
            .catch(err => console.log('Error fetching customer history:', err));
    }
    
    window.applySuggestedAssignment = function() {
        if (!suggestedAssignment) return;
        
        if (suggestedAssignment.assigned_to) {
            document.getElementById('assignedToSelect').value = suggestedAssignment.assigned_to;
        }
        if (suggestedAssignment.team_id) {
            document.getElementById('teamSelect').value = suggestedAssignment.team_id;
        }
        
        document.getElementById('assignmentSuggestion').style.display = 'none';
    };
    
    customerIdSelect.addEventListener('change', function() {
        fetchCustomerHistory(this.value);
    });
    
    if (customerIdSelect.value) {
        fetchCustomerHistory(customerIdSelect.value);
    }
    
    const feeCheckboxes = document.querySelectorAll('.service-fee-checkbox');
    const selectedFeesAmounts = document.getElementById('selectedFeesAmounts');
    const feeAmountInputs = document.getElementById('feeAmountInputs');
    const feesTotal = document.getElementById('feesTotal');
    const feeAmounts = {};
    
    feeCheckboxes.forEach(cb => {
        feeAmounts[cb.value] = parseFloat(cb.dataset.amount) || 0;
    });
    
    function captureCurrentAmounts() {
        document.querySelectorAll('.fee-amount-input').forEach(input => {
            const feeId = input.dataset.feeId;
            if (feeId) {
                feeAmounts[feeId] = parseFloat(input.value) || 0;
            }
        });
    }
    
    function updateFeeInputs() {
        if (!feeAmountInputs) return;
        
        captureCurrentAmounts();
        
        let html = '';
        let total = 0;
        
        feeCheckboxes.forEach(cb => {
            if (cb.checked) {
                const feeId = cb.value;
                const amount = feeAmounts[feeId] || 0;
                total += amount;
                html += '<div class="row mb-2 align-items-center">' +
                    '<div class="col-md-6">' + cb.dataset.name + '</div>' +
                    '<div class="col-md-6">' +
                    '<input type="number" class="form-control form-control-sm fee-amount-input" ' +
                    'name="fee_amounts[' + feeId + ']" value="' + amount + '" step="0.01" min="0" ' +
                    'data-fee-id="' + feeId + '">' +
                    '</div></div>';
            }
        });
        
        feeAmountInputs.innerHTML = html;
        if (selectedFeesAmounts) {
            selectedFeesAmounts.style.display = html ? 'block' : 'none';
        }
        if (feesTotal) {
            feesTotal.textContent = total.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }
        
        document.querySelectorAll('.fee-amount-input').forEach(input => {
            input.addEventListener('input', function() {
                feeAmounts[this.dataset.feeId] = parseFloat(this.value) || 0;
                updateFeesTotal();
            });
        });
    }
    
    function updateFeesTotal() {
        let total = 0;
        document.querySelectorAll('.fee-amount-input').forEach(input => {
            total += parseFloat(input.value) || 0;
        });
        if (feesTotal) {
            feesTotal.textContent = total.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }
    }
    
    feeCheckboxes.forEach(cb => {
        cb.addEventListener('change', updateFeeInputs);
    });
    
    updateFeeInputs();
});
</script>
<?php endif; ?>

<?php elseif ($action === 'view' && $ticketData): ?>
<?php
try {
    $timeline = $ticket->getTimeline($ticketData['id']);
} catch (\Throwable $e) {
    $timeline = [];
    error_log("Timeline error: " . $e->getMessage());
}
try {
    $satisfactionRating = $ticket->getSatisfactionRating($ticketData['id']);
} catch (\Throwable $e) {
    $satisfactionRating = null;
    error_log("Satisfaction rating error: " . $e->getMessage());
}
try {
    $escalations = $ticket->getEscalations($ticketData['id']);
} catch (\Throwable $e) {
    $escalations = [];
    error_log("Escalations error: " . $e->getMessage());
}
$isEscalated = $ticketData['is_escalated'] ?? false;
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-1"><i class="bi bi-ticket"></i> Ticket <?= htmlspecialchars($ticketData['ticket_number']) ?></h2>
        <?php if ($isEscalated): ?>
        <span class="badge bg-danger"><i class="bi bi-arrow-up-circle"></i> Escalated</span>
        <?php endif; ?>
        <?php if ($satisfactionRating): ?>
        <span class="badge bg-<?= $satisfactionRating['rating'] >= 4 ? 'success' : ($satisfactionRating['rating'] >= 3 ? 'warning' : 'danger') ?>">
            <i class="bi bi-star-fill"></i> <?= $satisfactionRating['rating'] ?>/5 Rating
        </span>
        <?php endif; ?>
    </div>
    <div>
        <a href="?page=tickets" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back
        </a>
        <a href="?page=tickets&action=edit&id=<?= $ticketData['id'] ?>" class="btn btn-primary">
            <i class="bi bi-pencil"></i> Edit
        </a>
        <?php if (\App\Auth::can('tickets.delete')): ?>
        <button type="button" class="btn btn-danger" onclick="confirmDeleteTicket(<?= $ticketData['id'] ?>, '<?= htmlspecialchars($ticketData['ticket_number']) ?>')">
            <i class="bi bi-trash"></i> Delete
        </button>
        <?php endif; ?>
    </div>
</div>

<?php if (!in_array($ticketData['status'], ['resolved', 'closed'])): ?>
<div class="card mb-4 border-primary">
    <div class="card-header bg-primary bg-opacity-10">
        <h6 class="mb-0"><i class="bi bi-lightning"></i> Quick Actions</h6>
    </div>
    <div class="card-body">
        <div class="d-flex flex-wrap gap-2 mb-3">
            <strong class="me-2 align-self-center">Change Status:</strong>
            <?php 
            $statusActions = [
                'open' => ['icon' => 'folder2-open', 'color' => 'secondary'],
                'in_progress' => ['icon' => 'play-circle', 'color' => 'info'],
                'pending' => ['icon' => 'pause-circle', 'color' => 'warning'],
                'resolved' => ['icon' => 'check-circle', 'color' => 'success'],
                'closed' => ['icon' => 'x-circle', 'color' => 'dark']
            ];
            foreach ($statusActions as $status => $config): 
                if ($status !== $ticketData['status']):
            ?>
            <form method="POST" class="d-inline">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="quick_status_change">
                <input type="hidden" name="ticket_id" value="<?= $ticketData['id'] ?>">
                <input type="hidden" name="new_status" value="<?= $status ?>">
                <button type="submit" class="btn btn-sm btn-outline-<?= $config['color'] ?>">
                    <i class="bi bi-<?= $config['icon'] ?>"></i> <?= ucwords(str_replace('_', ' ', $status)) ?>
                </button>
            </form>
            <?php endif; endforeach; ?>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#escalateModal">
                <i class="bi bi-arrow-up-circle"></i> Escalate Ticket
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (in_array($ticketData['status'], ['resolved', 'closed']) && !$satisfactionRating): ?>
<div class="card mb-4 border-success">
    <div class="card-header bg-success text-white">
        <h6 class="mb-0"><i class="bi bi-star"></i> Customer Satisfaction Rating</h6>
    </div>
    <div class="card-body">
        <p class="text-muted mb-3">How satisfied was the customer with the resolution of this ticket?</p>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="submit_rating">
            <input type="hidden" name="ticket_id" value="<?= $ticketData['id'] ?>">
            <div class="mb-3">
                <div class="btn-group" role="group">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                    <input type="radio" class="btn-check" name="rating" id="rating<?= $i ?>" value="<?= $i ?>" <?= $i === 5 ? 'checked' : '' ?>>
                    <label class="btn btn-outline-warning" for="rating<?= $i ?>">
                        <?= str_repeat('<i class="bi bi-star-fill"></i>', $i) ?>
                    </label>
                    <?php endfor; ?>
                </div>
            </div>
            <div class="mb-3">
                <textarea class="form-control" name="feedback" rows="2" placeholder="Customer feedback (optional)"></textarea>
            </div>
            <button type="submit" class="btn btn-success btn-sm">
                <i class="bi bi-check-lg"></i> Submit Rating
            </button>
        </form>
    </div>
</div>
<?php elseif ($satisfactionRating): ?>
<div class="card mb-4 border-<?= $satisfactionRating['rating'] >= 4 ? 'success' : ($satisfactionRating['rating'] >= 3 ? 'warning' : 'danger') ?>">
    <div class="card-header bg-<?= $satisfactionRating['rating'] >= 4 ? 'success' : ($satisfactionRating['rating'] >= 3 ? 'warning' : 'danger') ?> <?= $satisfactionRating['rating'] >= 4 ? 'text-white' : 'text-dark' ?>">
        <h6 class="mb-0"><i class="bi bi-star-fill"></i> Customer Satisfaction: <?= $satisfactionRating['rating'] ?>/5</h6>
    </div>
    <div class="card-body">
        <div class="mb-2">
            <?php for ($i = 1; $i <= 5; $i++): ?>
            <i class="bi bi-star<?= $i <= $satisfactionRating['rating'] ? '-fill text-warning' : '' ?> fs-4"></i>
            <?php endfor; ?>
        </div>
        <?php if ($satisfactionRating['feedback']): ?>
        <p class="mb-0"><strong>Feedback:</strong> <?= htmlspecialchars($satisfactionRating['feedback']) ?></p>
        <?php endif; ?>
        <small class="text-muted">Rated on <?= date('M j, Y g:i A', strtotime($satisfactionRating['rated_at'])) ?></small>
    </div>
</div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><?= htmlspecialchars($ticketData['subject']) ?></h5>
            </div>
            <div class="card-body">
                <p><?= nl2br(htmlspecialchars($ticketData['description'])) ?></p>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">Comments & Activity</h5>
            </div>
            <div class="card-body">
                <?php if (empty($comments)): ?>
                <p class="text-muted">No comments yet</p>
                <?php endif; ?>
                
                <?php foreach ($comments as $c): ?>
                <div class="border-start border-3 border-<?= $c['is_internal'] ? 'warning' : 'primary' ?> ps-3 mb-3">
                    <div class="d-flex justify-content-between">
                        <strong><?= htmlspecialchars($c['user_name'] ?? 'System') ?></strong>
                        <small class="text-muted"><?= date('M j, Y g:i A', strtotime($c['created_at'])) ?></small>
                    </div>
                    <?php if ($c['is_internal']): ?>
                    <span class="badge bg-warning text-dark mb-1">Internal Note</span>
                    <?php endif; ?>
                    <p class="mb-0"><?= nl2br(htmlspecialchars($c['comment'])) ?></p>
                </div>
                <?php endforeach; ?>
                
                <hr>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="add_comment">
                    <input type="hidden" name="ticket_id" value="<?= $ticketData['id'] ?>">
                    <div class="mb-3">
                        <textarea class="form-control" name="comment" rows="3" placeholder="Add a comment..." required></textarea>
                    </div>
                    <div class="d-flex justify-content-between">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_internal" id="isInternal">
                            <label class="form-check-label" for="isInternal">
                                Internal note (not visible to customer)
                            </label>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-send"></i> Add Comment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0">Ticket Details</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless table-sm">
                    <tr>
                        <th>Status</th>
                        <td><span class="badge badge-status-<?= $ticketData['status'] ?>"><?= ucfirst(str_replace('_', ' ', $ticketData['status'])) ?></span></td>
                    </tr>
                    <tr>
                        <th>Priority</th>
                        <td><span class="badge badge-priority-<?= $ticketData['priority'] ?>"><?= ucfirst($ticketData['priority']) ?></span></td>
                    </tr>
                    <tr>
                        <th>Category</th>
                        <td><?= htmlspecialchars($categories[$ticketData['category']] ?? $ticketData['category']) ?></td>
                    </tr>
                    <tr>
                        <th>Assigned Team</th>
                        <td><?= htmlspecialchars($ticketData['team_name'] ?? 'No Team') ?></td>
                    </tr>
                    <tr>
                        <th>Assigned To</th>
                        <td><?= htmlspecialchars($ticketData['assigned_name'] ?? 'Unassigned') ?></td>
                    </tr>
                    <tr>
                        <th>Created</th>
                        <td><?= date('M j, Y g:i A', strtotime($ticketData['created_at'])) ?></td>
                    </tr>
                    <?php if ($ticketData['resolved_at']): ?>
                    <tr>
                        <th>Completed</th>
                        <td><?= date('M j, Y g:i A', strtotime($ticketData['resolved_at'])) ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
        
        <?php if ($ticketData['sla_policy_id']): 
            $slaStatus = $ticket->getSLAStatus($ticketData['id']);
            $sla = new \App\SLA();
        ?>
        <div class="card mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-speedometer2"></i> SLA Status</h5>
            </div>
            <div class="card-body">
                <p class="small text-muted mb-3">Policy: <?= htmlspecialchars($ticketData['sla_policy_name'] ?? 'Standard') ?></p>
                
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="fw-bold"><i class="bi bi-reply"></i> Response</span>
                        <?php
                        $respStatus = $slaStatus['response']['status'];
                        $respClass = match($respStatus) {
                            'met' => 'success',
                            'on_track' => 'success',
                            'at_risk' => 'warning',
                            'breached' => 'danger',
                            default => 'secondary'
                        };
                        $respIcon = match($respStatus) {
                            'met' => 'check-circle-fill',
                            'on_track' => 'check-circle',
                            'at_risk' => 'exclamation-triangle',
                            'breached' => 'x-circle-fill',
                            default => 'dash-circle'
                        };
                        ?>
                        <span class="badge bg-<?= $respClass ?>">
                            <i class="bi bi-<?= $respIcon ?>"></i> <?= ucfirst(str_replace('_', ' ', $respStatus)) ?>
                        </span>
                    </div>
                    <?php if ($ticketData['first_response_at']): ?>
                    <small class="text-muted">Responded: <?= date('M j, g:i A', strtotime($ticketData['first_response_at'])) ?></small>
                    <?php elseif ($ticketData['sla_response_due'] && $respStatus !== 'breached'): ?>
                    <small class="text-muted">Due: <?= date('M j, g:i A', strtotime($ticketData['sla_response_due'])) ?></small>
                    <?php if (isset($slaStatus['response']['time_left'])): ?>
                    <br><small class="text-<?= $respClass ?>"><?= $sla->formatTimeLeft($slaStatus['response']['time_left']) ?> remaining</small>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
                
                <div>
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="fw-bold"><i class="bi bi-check-circle"></i> Resolution</span>
                        <?php
                        $resStatus = $slaStatus['resolution']['status'];
                        $resClass = match($resStatus) {
                            'met' => 'success',
                            'on_track' => 'success',
                            'at_risk' => 'warning',
                            'breached' => 'danger',
                            default => 'secondary'
                        };
                        $resIcon = match($resStatus) {
                            'met' => 'check-circle-fill',
                            'on_track' => 'check-circle',
                            'at_risk' => 'exclamation-triangle',
                            'breached' => 'x-circle-fill',
                            default => 'dash-circle'
                        };
                        ?>
                        <span class="badge bg-<?= $resClass ?>">
                            <i class="bi bi-<?= $resIcon ?>"></i> <?= ucfirst(str_replace('_', ' ', $resStatus)) ?>
                        </span>
                    </div>
                    <?php if (in_array($ticketData['status'], ['resolved', 'closed']) && $ticketData['resolved_at']): ?>
                    <small class="text-muted">Completed: <?= date('M j, g:i A', strtotime($ticketData['resolved_at'])) ?></small>
                    <?php elseif ($ticketData['sla_resolution_due'] && $resStatus !== 'breached'): ?>
                    <small class="text-muted">Due: <?= date('M j, g:i A', strtotime($ticketData['sla_resolution_due'])) ?></small>
                    <?php if (isset($slaStatus['resolution']['time_left'])): ?>
                    <br><small class="text-<?= $resClass ?>"><?= $sla->formatTimeLeft($slaStatus['resolution']['time_left']) ?> remaining</small>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="card mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0">Customer</h5>
            </div>
            <div class="card-body">
                <p class="mb-1"><strong><?= htmlspecialchars($ticketData['customer_name']) ?></strong></p>
                <p class="mb-1"><small class="text-muted"><?= htmlspecialchars($ticketData['account_number']) ?></small></p>
                <p class="mb-2"><i class="bi bi-telephone"></i> <?= htmlspecialchars($ticketData['customer_phone']) ?></p>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="?page=customers&action=view&id=<?= $ticketData['customer_id'] ?>" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-person"></i> View
                    </a>
                    <?php if (!empty($ticketData['customer_phone'])): ?>
                    <a href="tel:<?= htmlspecialchars($ticketData['customer_phone']) ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-telephone"></i> Call
                    </a>
                    <?php 
                    $waCustomer = new \App\WhatsApp();
                    if ($waCustomer->isEnabled()):
                        $customerMsg = "Hi " . $ticketData['customer_name'] . ",\n\nRegarding ticket #" . $ticketData['ticket_number'] . ":\n";
                    ?>
                    <button type="button" class="btn btn-sm btn-success" onclick="sendTicketWA('quick_customer', <?= htmlspecialchars(json_encode($customerMsg)) ?>)">
                        <i class="bi bi-whatsapp"></i> WhatsApp
                    </button>
                    <?php endif; ?>
                    <?php else: ?>
                    <span class="text-muted small">No phone number</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <?php 
        $serviceFee = new \App\ServiceFee($db);
        $ticketFees = $serviceFee->getTicketFees($ticketData['id']);
        $feesTotal = $serviceFee->getTicketFeesTotal($ticketData['id']);
        $feeTypes = $serviceFee->getFeeTypes(true);
        ?>
        <div class="card mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-receipt"></i> Service Fees</h5>
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addServiceFeeModal">
                    <i class="bi bi-plus"></i> Add Fee
                </button>
            </div>
            <div class="card-body">
                <?php if (!empty($ticketFees)): ?>
                <table class="table table-sm mb-3">
                    <thead>
                        <tr>
                            <th>Fee</th>
                            <th class="text-end">Amount</th>
                            <th class="text-center">Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ticketFees as $fee): ?>
                        <tr>
                            <td>
                                <?= htmlspecialchars($fee['fee_name']) ?>
                                <?php if ($fee['notes']): ?>
                                <br><small class="text-muted"><?= htmlspecialchars($fee['notes']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="text-end"><?= $fee['currency'] ?> <?= number_format($fee['amount'], 2) ?></td>
                            <td class="text-center">
                                <?php if ($fee['is_paid']): ?>
                                <span class="badge bg-success">Paid</span>
                                <?php else: ?>
                                <span class="badge bg-warning">Unpaid</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if (!$fee['is_paid']): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                    <input type="hidden" name="action" value="mark_fee_paid">
                                    <input type="hidden" name="fee_id" value="<?= $fee['id'] ?>">
                                    <button type="submit" class="btn btn-xs btn-success" title="Mark as Paid">
                                        <i class="bi bi-check"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Remove this fee?');">
                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                    <input type="hidden" name="action" value="delete_ticket_fee">
                                    <input type="hidden" name="fee_id" value="<?= $fee['id'] ?>">
                                    <button type="submit" class="btn btn-xs btn-outline-danger" title="Remove">
                                        <i class="bi bi-x"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="fw-bold">
                            <td>Total</td>
                            <td class="text-end">KES <?= number_format($feesTotal['total'], 2) ?></td>
                            <td class="text-center">
                                <?php if ($feesTotal['unpaid'] > 0): ?>
                                <small class="text-warning">Unpaid: <?= number_format($feesTotal['unpaid'], 2) ?></small>
                                <?php else: ?>
                                <small class="text-success">All Paid</small>
                                <?php endif; ?>
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
                <?php else: ?>
                <p class="text-muted text-center mb-0"><small>No service fees added</small></p>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($ticketData['assigned_to']): ?>
        <div class="card mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0">Assigned Technician</h5>
            </div>
            <div class="card-body">
                <p class="mb-1"><strong><?= htmlspecialchars($ticketData['assigned_name']) ?></strong></p>
                <?php if (!empty($ticketData['assigned_phone'])): ?>
                <p class="mb-2"><i class="bi bi-telephone"></i> <?= htmlspecialchars($ticketData['assigned_phone']) ?></p>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="tel:<?= htmlspecialchars($ticketData['assigned_phone']) ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-telephone"></i> Call
                    </a>
                    <?php 
                    if (!isset($waCustomer)) $waCustomer = new \App\WhatsApp();
                    if ($waCustomer->isEnabled()):
                        $techMsg = "Hi " . $ticketData['assigned_name'] . ",\n\nRegarding ticket #" . $ticketData['ticket_number'] . " for " . $ticketData['customer_name'] . ":\n";
                    ?>
                    <button type="button" class="btn btn-sm btn-success" onclick="sendTicketWAToTech(<?= htmlspecialchars(json_encode($techMsg)) ?>, '<?= $ticketData['assigned_phone'] ?>')">
                        <i class="bi bi-whatsapp"></i> WhatsApp
                    </button>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <p class="text-muted small mb-0">No phone number on file</p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php 
        if (!isset($waCustomer)) $waCustomer = new \App\WhatsApp();
        if ($waCustomer->isEnabled() && !empty($ticketData['customer_phone'])): 
        ?>
        <div class="card mb-4 border-success">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-whatsapp"></i> Quick WhatsApp Notifications</h5>
            </div>
            <div class="card-body">
                <p class="small text-muted mb-3">Send WhatsApp messages via session:</p>
                <div id="waTicketStatus" class="alert alert-info d-none mb-3"></div>
                
                <?php
                $customerName = $ticketData['customer_name'];
                $ticketNum = $ticketData['ticket_number'];
                $subject = $ticketData['subject'];
                $status = ucfirst($ticketData['status']);
                
                $waSettings = new \App\Settings();
                $replacements = [
                    '{customer_name}' => $customerName,
                    '{ticket_number}' => $ticketNum,
                    '{subject}' => $subject,
                    '{status}' => $status
                ];
                
                $templates = [
                    'status_update' => str_replace(array_keys($replacements), array_values($replacements), 
                        $waSettings->get('wa_template_status_update', "Hi {customer_name},\n\nThis is an update on your ticket #{ticket_number}.\n\nCurrent Status: {status}\n\nWe're working on resolving your issue. Thank you for your patience.")),
                    'need_info' => str_replace(array_keys($replacements), array_values($replacements),
                        $waSettings->get('wa_template_need_info', "Hi {customer_name},\n\nRegarding ticket #{ticket_number}: {subject}\n\nWe need some additional information to proceed. Could you please provide more details?\n\nThank you.")),
                    'resolved' => str_replace(array_keys($replacements), array_values($replacements),
                        $waSettings->get('wa_template_resolved', "Hi {customer_name},\n\nGreat news! Your ticket #{ticket_number} has been resolved.\n\nIf you have any further questions or issues, please don't hesitate to contact us.\n\nThank you for choosing our services!")),
                    'technician_coming' => str_replace(array_keys($replacements), array_values($replacements),
                        $waSettings->get('wa_template_technician_coming', "Hi {customer_name},\n\nRegarding ticket #{ticket_number}:\n\nOur technician is on the way to your location. Please ensure someone is available to receive them.\n\nThank you.")),
                    'scheduled' => str_replace(array_keys($replacements), array_values($replacements),
                        $waSettings->get('wa_template_scheduled', "Hi {customer_name},\n\nYour service visit for ticket #{ticket_number} has been scheduled.\n\nPlease confirm if this time works for you.\n\nThank you."))
                ];
                ?>
                
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-outline-success btn-sm" onclick="sendTicketWA('status_update', <?= htmlspecialchars(json_encode($templates['status_update'])) ?>)">
                        <i class="bi bi-arrow-repeat"></i> Status Update
                    </button>
                    <button type="button" class="btn btn-outline-warning btn-sm" onclick="sendTicketWA('need_info', <?= htmlspecialchars(json_encode($templates['need_info'])) ?>)">
                        <i class="bi bi-question-circle"></i> Need Info
                    </button>
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="sendTicketWA('resolved', <?= htmlspecialchars(json_encode($templates['resolved'])) ?>)">
                        <i class="bi bi-check-circle"></i> Completed
                    </button>
                    <button type="button" class="btn btn-outline-info btn-sm" onclick="sendTicketWA('technician_coming', <?= htmlspecialchars(json_encode($templates['technician_coming'])) ?>)">
                        <i class="bi bi-truck"></i> Tech Coming
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="sendTicketWA('scheduled', <?= htmlspecialchars(json_encode($templates['scheduled'])) ?>)">
                        <i class="bi bi-calendar-check"></i> Scheduled
                    </button>
                </div>
                
                <hr class="my-3">
                
                <form id="customWhatsAppForm" class="mb-0">
                    <label class="form-label small">Custom Message:</label>
                    <div class="input-group input-group-sm">
                        <textarea class="form-control" id="customWaMessage" rows="2" placeholder="Type your custom message..."><?= "Hi {$customerName},\n\nRegarding ticket #{$ticketNum}:\n\n" ?></textarea>
                    </div>
                    <div class="mt-2">
                        <button type="button" class="btn btn-success btn-sm" onclick="sendTicketWA('custom', document.getElementById('customWaMessage').value)">
                            <i class="bi bi-whatsapp"></i> Send Custom Message
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <script>
        function sendTicketWA(messageType, message) {
            var statusDiv = document.getElementById('waTicketStatus');
            if (statusDiv) {
                statusDiv.className = 'alert alert-info mb-3';
                statusDiv.textContent = 'Sending WhatsApp message...';
                statusDiv.classList.remove('d-none');
            }
            
            fetch('?page=api&action=send_whatsapp', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    ticket_id: <?= $ticketData['id'] ?>,
                    phone: '<?= $ticketData['customer_phone'] ?>',
                    message: message,
                    message_type: messageType
                })
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (statusDiv) {
                    if (data.success) {
                        statusDiv.className = 'alert alert-success mb-3';
                        statusDiv.textContent = 'WhatsApp message sent successfully!';
                    } else {
                        statusDiv.className = 'alert alert-danger mb-3';
                        statusDiv.textContent = 'Failed: ' + (data.error || 'Unknown error');
                    }
                    setTimeout(function() { statusDiv.classList.add('d-none'); }, 5000);
                } else {
                    if (data.success) {
                        alert('WhatsApp message sent successfully!');
                    } else {
                        alert('Failed: ' + (data.error || 'Unknown error'));
                    }
                }
            })
            .catch(function(e) {
                if (statusDiv) {
                    statusDiv.className = 'alert alert-danger mb-3';
                    statusDiv.textContent = 'Error: ' + e.message;
                } else {
                    alert('Error: ' + e.message);
                }
            });
        }
        
        function sendTicketWAToTech(message, phone) {
            fetch('?page=api&action=send_whatsapp', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    ticket_id: <?= $ticketData['id'] ?>,
                    phone: phone,
                    message: message,
                    message_type: 'technician_message'
                })
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    alert('WhatsApp message sent to technician!');
                } else {
                    alert('Failed: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(function(e) {
                alert('Error: ' + e.message);
            });
        }
        </script>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">SMS Log</h5>
            </div>
            <div class="card-body">
                <?php
                $db = Database::getConnection();
                $stmt = $db->prepare("SELECT * FROM sms_logs WHERE ticket_id = ? ORDER BY sent_at DESC LIMIT 5");
                $stmt->execute([$ticketData['id']]);
                $smsLogs = $stmt->fetchAll();
                ?>
                <?php if (empty($smsLogs)): ?>
                <p class="text-muted mb-0">No SMS sent for this ticket</p>
                <?php endif; ?>
                <?php foreach ($smsLogs as $log): ?>
                <div class="mb-2 pb-2 border-bottom">
                    <small class="text-muted"><?= date('M j, g:i A', strtotime($log['sent_at'])) ?></small><br>
                    <span class="badge bg-<?= $log['status'] === 'sent' ? 'success' : 'danger' ?>"><?= ucfirst($log['status']) ?></span>
                    <small><?= ucfirst($log['recipient_type']) ?>: <?= htmlspecialchars($log['recipient_phone']) ?></small>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-whatsapp text-success"></i> WhatsApp Log</h5>
            </div>
            <div class="card-body">
                <?php
                $waStmt = $db->prepare("SELECT * FROM whatsapp_logs WHERE ticket_id = ? ORDER BY sent_at DESC LIMIT 5");
                $waStmt->execute([$ticketData['id']]);
                $waLogs = $waStmt->fetchAll();
                ?>
                <?php if (empty($waLogs)): ?>
                <p class="text-muted mb-0">No WhatsApp messages sent for this ticket</p>
                <?php endif; ?>
                <?php foreach ($waLogs as $log): ?>
                <div class="mb-2 pb-2 border-bottom">
                    <small class="text-muted"><?= date('M j, g:i A', strtotime($log['sent_at'])) ?></small><br>
                    <span class="badge bg-<?= $log['status'] === 'sent' ? 'success' : ($log['status'] === 'opened' ? 'info' : 'danger') ?>"><?= ucfirst($log['status']) ?></span>
                    <small><?= ucfirst($log['recipient_type'] ?? 'customer') ?>: <?= htmlspecialchars($log['recipient_phone']) ?></small>
                    <?php if (!empty($log['message_type'])): ?>
                    <br><small class="text-muted"><?= ucfirst(str_replace('_', ' ', $log['message_type'])) ?></small>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-clock-history"></i> Activity Timeline</h5>
                <span class="badge bg-secondary"><?= count($timeline) ?> events</span>
            </div>
            <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                <?php if (empty($timeline)): ?>
                <p class="text-muted">No activity recorded yet.</p>
                <?php else: ?>
                <div class="timeline">
                    <?php foreach ($timeline as $event): ?>
                    <div class="d-flex mb-3">
                        <div class="flex-shrink-0 me-3">
                            <div class="rounded-circle bg-<?= $event['color'] ?> bg-opacity-10 p-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                <i class="bi bi-<?= $event['icon'] ?> text-<?= $event['color'] ?>"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-start">
                                <strong class="text-<?= $event['color'] ?>"><?= htmlspecialchars($event['title']) ?></strong>
                                <small class="text-muted"><?= date('M j, g:i A', strtotime($event['timestamp'])) ?></small>
                            </div>
                            <p class="text-muted mb-0 small"><?= htmlspecialchars($event['description']) ?></p>
                            <small class="text-muted">by <?= htmlspecialchars($event['user']) ?></small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($escalations)): ?>
<div class="row mt-4">
    <div class="col-12">
        <div class="card border-danger">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i class="bi bi-arrow-up-circle"></i> Escalation History (<?= count($escalations) ?>)</h5>
            </div>
            <div class="card-body">
                <?php foreach ($escalations as $esc): ?>
                <div class="border-start border-3 border-danger ps-3 mb-3">
                    <div class="d-flex justify-content-between">
                        <strong>Escalated by <?= htmlspecialchars($esc['escalated_by_name'] ?? 'Unknown') ?></strong>
                        <small class="text-muted"><?= date('M j, Y g:i A', strtotime($esc['created_at'])) ?></small>
                    </div>
                    <p class="mb-1"><?= htmlspecialchars($esc['reason']) ?></p>
                    <?php if ($esc['escalated_to_name']): ?>
                    <small class="text-muted">Assigned to: <?= htmlspecialchars($esc['escalated_to_name']) ?></small>
                    <?php endif; ?>
                    <?php if ($esc['new_priority'] && $esc['new_priority'] !== $esc['previous_priority']): ?>
                    <br><small class="text-muted">Priority: <?= ucfirst($esc['previous_priority']) ?> → <?= ucfirst($esc['new_priority']) ?></small>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="modal fade" id="escalateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="escalate_ticket">
                <input type="hidden" name="ticket_id" value="<?= $ticketData['id'] ?>">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-arrow-up-circle"></i> Escalate Ticket</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Reason for Escalation *</label>
                        <textarea class="form-control" name="reason" rows="3" required placeholder="Explain why this ticket needs to be escalated..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Escalate To</label>
                        <select class="form-select" name="escalated_to">
                            <option value="">Select User (Optional)</option>
                            <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?> (<?= ucfirst($u['role']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Change Priority To</label>
                        <select class="form-select" name="new_priority">
                            <option value="">Keep Current (<?= ucfirst($ticketData['priority']) ?>)</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-arrow-up-circle"></i> Escalate Ticket
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="addServiceFeeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="add_ticket_service_fee">
                <input type="hidden" name="ticket_id" value="<?= $ticketData['id'] ?>">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-receipt"></i> Add Service Fee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Fee Type</label>
                        <select class="form-select" name="fee_type_id" id="feeTypeSelect" onchange="updateFeeDefaults()">
                            <option value="">-- Custom Fee --</option>
                            <?php foreach ($feeTypes as $ft): ?>
                            <option value="<?= $ft['id'] ?>" data-name="<?= htmlspecialchars($ft['name']) ?>" data-amount="<?= $ft['default_amount'] ?>">
                                <?= htmlspecialchars($ft['name']) ?> (<?= $ft['currency'] ?> <?= number_format($ft['default_amount'], 2) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Fee Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="fee_name" id="feeNameInput" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Amount (KES) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="amount" id="feeAmountInput" step="0.01" min="0" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-plus"></i> Add Fee</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
function updateFeeDefaults() {
    const select = document.getElementById('feeTypeSelect');
    const nameInput = document.getElementById('feeNameInput');
    const amountInput = document.getElementById('feeAmountInput');
    const option = select.options[select.selectedIndex];
    if (option.dataset.name) {
        nameInput.value = option.dataset.name;
        amountInput.value = option.dataset.amount;
    }
}
</script>

<?php if (\App\Auth::can('tickets.delete')): ?>
<div class="modal fade" id="deleteTicketModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle"></i> Delete Ticket</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="delete_ticket">
                    <input type="hidden" name="id" id="deleteTicketId">
                    <p>Are you sure you want to delete ticket <strong id="deleteTicketNumber"></strong>?</p>
                    <p class="text-danger mb-0"><i class="bi bi-exclamation-circle"></i> This action cannot be undone. All comments, fees, and related data will be permanently deleted.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="bi bi-trash"></i> Delete Ticket</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
function confirmDeleteTicket(ticketId, ticketNumber) {
    document.getElementById('deleteTicketId').value = ticketId;
    document.getElementById('deleteTicketNumber').textContent = ticketNumber;
    new bootstrap.Modal(document.getElementById('deleteTicketModal')).show();
}
</script>
<?php endif; ?>

<?php else: ?>
<?php 
$dashboardStats = $ticket->getDashboardStats();
$overdueTickets = $ticket->getOverdueTickets();
$escalatedFilter = $_GET['escalated'] ?? '';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-ticket"></i> Tickets</h2>
    <a href="?page=tickets&action=create" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> Create Ticket
    </a>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-2">
        <div class="card bg-primary text-white h-100">
            <div class="card-body text-center">
                <h3 class="mb-0"><?= $dashboardStats['open_tickets'] ?? 0 ?></h3>
                <small>Open</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-info text-white h-100">
            <div class="card-body text-center">
                <h3 class="mb-0"><?= $dashboardStats['in_progress_tickets'] ?? 0 ?></h3>
                <small>In Progress</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-success text-white h-100">
            <div class="card-body text-center">
                <h3 class="mb-0"><?= $dashboardStats['resolved_tickets'] ?? 0 ?></h3>
                <small>Completed</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-danger text-white h-100">
            <div class="card-body text-center">
                <h3 class="mb-0"><?= $dashboardStats['sla_breached'] ?? 0 ?></h3>
                <small>SLA Breached</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-warning h-100">
            <div class="card-body text-center">
                <h3 class="mb-0"><?= $dashboardStats['escalated_tickets'] ?? 0 ?></h3>
                <small>Escalated</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-secondary text-white h-100">
            <div class="card-body text-center">
                <h3 class="mb-0"><?= $dashboardStats['avg_satisfaction'] ? number_format($dashboardStats['avg_satisfaction'], 1) : '-' ?></h3>
                <small><i class="bi bi-star-fill"></i> Avg Rating</small>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($overdueTickets)): ?>
<div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
    <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
    <div>
        <strong><?= count($overdueTickets) ?> overdue ticket(s) require immediate attention!</strong>
        <a href="?page=tickets&sla_breached=1" class="alert-link ms-2">View all</a>
    </div>
</div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <input type="hidden" name="page" value="tickets">
            <div class="col-md-3">
                <input type="text" class="form-control" name="search" placeholder="Search tickets..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-2">
                <select class="form-select" name="status">
                    <option value="">All Status</option>
                    <?php foreach ($ticketStatuses as $key => $label): ?>
                    <option value="<?= $key ?>" <?= $statusFilter === $key ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" name="priority">
                    <option value="">All Priority</option>
                    <?php foreach ($priorities as $key => $label): ?>
                    <option value="<?= $key ?>" <?= $priorityFilter === $key ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" name="escalated">
                    <option value="">All Tickets</option>
                    <option value="1" <?= $escalatedFilter === '1' ? 'selected' : '' ?>>Escalated Only</option>
                    <option value="0" <?= $escalatedFilter === '0' ? 'selected' : '' ?>>Not Escalated</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-search"></i> Filter
                </button>
                <a href="?page=tickets" class="btn btn-outline-secondary">Clear</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Ticket #</th>
                        <th>Customer</th>
                        <th>Subject</th>
                        <th>Priority</th>
                        <th>SLA</th>
                        <th>Assigned</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $filters = [];
                    if ($statusFilter) $filters['status'] = $statusFilter;
                    if ($priorityFilter) $filters['priority'] = $priorityFilter;
                    if ($search) $filters['search'] = $search;
                    if ($escalatedFilter !== '') $filters['escalated'] = $escalatedFilter;
                    if (!empty($_GET['sla_breached'])) $filters['sla_breached'] = true;
                    if (!\App\Auth::can('tickets.view_all') && !\App\Auth::isAdmin()) {
                        $filters['user_id'] = $_SESSION['user_id'];
                    }
                    try {
                        $tickets = $ticket->getAll($filters);
                    } catch (\Throwable $e) {
                        $tickets = [];
                        error_log("Ticket list error: " . $e->getMessage());
                    }
                    $slaHelper = new \App\SLA();
                    foreach ($tickets as $t):
                        $slaStatus = $slaHelper->getSLAStatus($t);
                        $hasBreached = $t['sla_response_breached'] || $t['sla_resolution_breached'];
                        $isAtRisk = ($slaStatus['response']['status'] === 'at_risk' || $slaStatus['resolution']['status'] === 'at_risk');
                        $ticketIsEscalated = $t['is_escalated'] ?? false;
                    ?>
                    <tr class="<?= $hasBreached ? 'table-danger' : ($isAtRisk ? 'table-warning' : '') ?>">
                        <td>
                            <a href="?page=tickets&action=view&id=<?= $t['id'] ?>"><?= htmlspecialchars($t['ticket_number']) ?></a>
                            <?php if ($ticketIsEscalated): ?>
                            <br><span class="badge bg-danger" title="Escalated"><i class="bi bi-arrow-up-circle"></i></span>
                            <?php endif; ?>
                            <?php if ($t['satisfaction_rating'] ?? null): ?>
                            <span class="badge bg-<?= $t['satisfaction_rating'] >= 4 ? 'success' : ($t['satisfaction_rating'] >= 3 ? 'warning' : 'danger') ?>" title="Rating: <?= $t['satisfaction_rating'] ?>/5">
                                <i class="bi bi-star-fill"></i> <?= $t['satisfaction_rating'] ?>
                            </span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($t['customer_name'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars(substr($t['subject'], 0, 30)) ?><?= strlen($t['subject']) > 30 ? '...' : '' ?></td>
                        <td><span class="badge badge-priority-<?= $t['priority'] ?>"><?= ucfirst($t['priority']) ?></span></td>
                        <td>
                            <?php if ($t['sla_policy_id']): ?>
                                <?php if ($hasBreached): ?>
                                <span class="badge bg-danger" title="SLA Breached"><i class="bi bi-x-circle-fill"></i> Breached</span>
                                <?php elseif ($isAtRisk): ?>
                                <span class="badge bg-warning text-dark" title="SLA At Risk"><i class="bi bi-exclamation-triangle-fill"></i> At Risk</span>
                                <?php elseif (in_array($t['status'], ['resolved', 'closed'])): ?>
                                <span class="badge bg-success" title="SLA Met"><i class="bi bi-check-circle-fill"></i> Met</span>
                                <?php else: ?>
                                <span class="badge bg-success" title="SLA On Track"><i class="bi bi-check-circle"></i> On Track</span>
                                <?php endif; ?>
                            <?php else: ?>
                            <span class="badge bg-secondary">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($t['team_name']): ?>
                            <small class="text-info"><?= htmlspecialchars($t['team_name']) ?></small><br>
                            <?php endif; ?>
                            <?= htmlspecialchars($t['assigned_name'] ?? '-') ?>
                        </td>
                        <td><span class="badge badge-status-<?= $t['status'] ?>"><?= ucfirst(str_replace('_', ' ', $t['status'])) ?></span></td>
                        <td><?= date('M j', strtotime($t['created_at'])) ?></td>
                        <td>
                            <a href="?page=tickets&action=view&id=<?= $t['id'] ?>" class="btn btn-sm btn-outline-primary" title="View">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="?page=tickets&action=edit&id=<?= $t['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-success" title="Repost to WhatsApp" 
                                    onclick="repostTicketToWhatsApp(<?= $t['id'] ?>, '<?= htmlspecialchars($t['ticket_number']) ?>')">
                                <i class="bi bi-whatsapp"></i>
                            </button>
                            <?php if (\App\Auth::can('tickets.delete')): ?>
                            <button type="button" class="btn btn-sm btn-outline-danger" title="Delete" 
                                    onclick="confirmDeleteTicket(<?= $t['id'] ?>, '<?= htmlspecialchars($t['ticket_number']) ?>')">
                                <i class="bi bi-trash"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($tickets)): ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">
                            No tickets found. <a href="?page=tickets&action=create">Create your first ticket</a>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteTicketModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle"></i> Delete Ticket</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="delete_ticket">
                    <input type="hidden" name="id" id="deleteTicketId">
                    <p>Are you sure you want to delete ticket <strong id="deleteTicketNumber"></strong>?</p>
                    <p class="text-danger mb-0"><i class="bi bi-exclamation-circle"></i> This action cannot be undone. All comments, fees, and related data will be permanently deleted.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="bi bi-trash"></i> Delete Ticket</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function confirmDeleteTicket(ticketId, ticketNumber) {
    document.getElementById('deleteTicketId').value = ticketId;
    document.getElementById('deleteTicketNumber').textContent = ticketNumber;
    new bootstrap.Modal(document.getElementById('deleteTicketModal')).show();
}

function repostTicketToWhatsApp(ticketId, ticketNumber) {
    if (!confirm('Repost ticket ' + ticketNumber + ' to WhatsApp groups?')) return;
    
    const btn = event.target.closest('button');
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    
    fetch('?page=api&action=repost_single_ticket&ticket_id=' + ticketId)
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
            if (data.success) {
                alert('Ticket ' + ticketNumber + ' reposted to WhatsApp successfully!\\nGroups notified: ' + (data.groups_sent || 0));
            } else {
                alert('Failed to repost: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
            alert('Error: ' + err.message);
        });
}
</script>
<?php endif; ?>
